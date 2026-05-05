<?php
/**
 * Clase para actualizar el plugin ADR-IA-Edit desde GitHub Releases
 *
 * Funcionamiento:
 * 1. Consultá GitHub API para buscar el último Release
 * 2. Compará la versión con la instalada
 * 3. Si hay nueva versión, WordPress la muestra en el dashboard como cualquier plugin
 * 4. El ZIP del release (adr-ia-edit.zip) se descarga e instala automáticamente
 *
 * Para publicar una actualización:
 * - Actualizá la versión en adr-ia-edit.php (ADR_IA_EDIT_VERSION)
 * - Creá un Release en GitHub con ese tag (ej: v1.1.0)
 * - Adjuntá el archivo adr-ia-edit.zip al Release
 *
 * @package ADR_IA_Edit
 */

// Seguridad: bloquear acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Clase ADR_Updater
 *
 * Integra el plugin con el sistema de actualizaciones de WordPress
 * usando GitHub Releases como fuente.
 */
class ADR_Updater {

    /**
     * Repositorio de GitHub en formato "usuario/repositorio".
     */
    const GITHUB_REPO = 'adrweb-uy/IA-Impreza-WPBakery';

    /**
     * URL base de la API de GitHub.
     */
    const GITHUB_API_URL = 'https://api.github.com/repos/adrweb-uy/IA-Impreza-WPBakery/releases/latest';

    /**
     * Nombre del archivo ZIP que debe estar adjunto al Release de GitHub.
     */
    const RELEASE_ZIP_NAME = 'adr-ia-edit.zip';

    /**
     * Nombre del transient de WordPress para cachear la respuesta de GitHub.
     */
    const TRANSIENT_KEY = 'adr_ia_edit_github_release';

    /**
     * Tiempo de vida del caché en segundos (12 horas).
     */
    const CACHE_TTL = 43200;

    /**
     * Archivo principal del plugin (relativo a plugins/).
     *
     * @var string
     */
    private string $plugin_file;

    /**
     * Versión actual instalada del plugin.
     *
     * @var string
     */
    private string $current_version;

    /**
     * Constructor.
     *
     * @param string $plugin_file     Ruta relativa del plugin (plugin-dir/plugin-file.php).
     * @param string $current_version Versión actual del plugin.
     */
    public function __construct( string $plugin_file, string $current_version ) {
        $this->plugin_file     = $plugin_file;
        $this->current_version = $current_version;

        $this->init_hooks();
    }

    /**
     * Registrar todos los hooks necesarios.
     */
    private function init_hooks(): void {
        // Inyectar info de actualización en el transient de WordPress
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_updates' ] );

        // Proveer info del plugin para la pantalla de detalles del update
        add_filter( 'plugins_api', [ $this, 'plugin_info' ], 10, 3 );

        // Renombrar carpeta después de instalar (GitHub nombra la carpeta diferente)
        add_filter( 'upgrader_source_selection', [ $this, 'fix_folder_name' ], 10, 4 );

        // Limpiar caché cuando se limpia el caché de actualizaciones
        add_action( 'delete_site_transient_update_plugins', [ $this, 'clear_cache' ] );

        // Limpiar el transient de WordPress después de instalar la actualización
        add_action( 'upgrader_process_complete', [ $this, 'after_update' ], 10, 2 );

        // Agregar link "Buscar actualizaciones" en la lista de plugins
        add_filter( 'plugin_action_links_' . $this->plugin_file, [ $this, 'add_check_update_link' ] );

        // Manejar la acción manual de buscar actualizaciones
        add_action( 'admin_init', [ $this, 'handle_manual_check' ] );

        // Mostrar aviso después de verificar manualmente
        add_action( 'admin_notices', [ $this, 'show_update_notice' ] );
    }

    /**
     * Limpiar el transient de actualizaciones justo después de que
     * WordPress instala una actualización de este plugin.
     * Evita que WordPress siga mostrando la misma versión como "pendiente".
     *
     * @param WP_Upgrader $upgrader  Instancia del upgrader.
     * @param array       $hook_extra Datos del hook (tipo, plugins, etc.).
     */
    public function after_update( $upgrader, array $hook_extra ): void {
        // Solo actuar si fue una actualización de plugins (no temas ni core)
        if ( ( $hook_extra['type'] ?? '' ) !== 'plugin' ) {
            return;
        }

        $updated_plugins = $hook_extra['plugins'] ?? [];

        // Verificar si nuestro plugin estaba en la lista de actualizados
        if ( ! in_array( $this->plugin_file, $updated_plugins, true ) ) {
            return;
        }

        // 1. Limpiar el caché de GitHub para que la próxima consulta sea fresca
        $this->clear_cache();

        // 2. Remover nuestro plugin del response[] del transient de WordPress
        //    para que no siga apareciendo como "hay actualización disponible"
        $update_transient = get_site_transient( 'update_plugins' );

        if ( $update_transient && isset( $update_transient->response[ $this->plugin_file ] ) ) {
            unset( $update_transient->response[ $this->plugin_file ] );

            // Mover a no_update[] con la versión actual
            $update_transient->no_update[ $this->plugin_file ] = (object) [
                'slug'        => ADR_IA_EDIT_SLUG,
                'plugin'      => $this->plugin_file,
                'new_version' => $this->current_version,
                'url'         => 'https://github.com/' . self::GITHUB_REPO,
                'package'     => '',
            ];

            set_site_transient( 'update_plugins', $update_transient );
        }
    }

    // ─── NÚCLEO: VERIFICAR ACTUALIZACIONES ────────────────────────────────────

    /**
     * Verificar si hay actualizaciones disponibles en GitHub.
     * Se engancha al transient de WordPress para actualizaciones de plugins.
     *
     * @param object $transient Transient de actualizaciones de WordPress.
     * @return object
     */
    public function check_for_updates( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->get_github_release();

        if ( ! $release ) {
            return $transient;
        }

        $latest_version = $this->parse_version( $release['tag_name'] ?? '' );
        $download_url   = $this->get_zip_download_url( $release );

        if ( ! $latest_version || ! $download_url ) {
            return $transient;
        }

        // Si la versión de GitHub es mayor, notificar a WordPress
        if ( version_compare( $latest_version, $this->current_version, '>' ) ) {
            $transient->response[ $this->plugin_file ] = (object) [
                'slug'        => ADR_IA_EDIT_SLUG,
                'plugin'      => $this->plugin_file,
                'new_version' => $latest_version,
                'url'         => 'https://github.com/' . self::GITHUB_REPO,
                'package'     => $download_url,
                'tested'      => get_bloginfo( 'version' ),
                'requires'    => '5.8',
                'icons'       => [],
                'banners'     => [],
            ];
        } else {
            // Sin actualizaciones: asegurarse de que no quede en la lista de updates
            if ( isset( $transient->response[ $this->plugin_file ] ) ) {
                unset( $transient->response[ $this->plugin_file ] );
            }
            $transient->no_update[ $this->plugin_file ] = (object) [
                'slug'        => ADR_IA_EDIT_SLUG,
                'plugin'      => $this->plugin_file,
                'new_version' => $this->current_version,
                'url'         => 'https://github.com/' . self::GITHUB_REPO,
                'package'     => '',
            ];
        }

        return $transient;
    }

    /**
     * Proveer información del plugin para la pantalla de detalles del update.
     *
     * @param false|object $result Resultado actual.
     * @param string       $action Acción solicitada.
     * @param object       $args   Argumentos.
     * @return false|object
     */
    public function plugin_info( $result, string $action, object $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }

        if ( ! isset( $args->slug ) || $args->slug !== ADR_IA_EDIT_SLUG ) {
            return $result;
        }

        $release = $this->get_github_release();

        if ( ! $release ) {
            return $result;
        }

        $latest_version = $this->parse_version( $release['tag_name'] ?? '' );
        $download_url   = $this->get_zip_download_url( $release );
        $changelog      = $this->format_changelog( $release['body'] ?? '' );
        $published_at   = $release['published_at'] ?? '';

        return (object) [
            'name'              => 'ADR-IA-Edit',
            'slug'              => ADR_IA_EDIT_SLUG,
            'version'           => $latest_version,
            'author'            => '<a href="https://adrianraineri.com" target="_blank">Luis Adrián Raineri</a>',
            'author_profile'    => 'https://adrianraineri.com',
            'homepage'          => 'https://github.com/' . self::GITHUB_REPO,
            'download_link'     => $download_url,
            'requires'          => '5.8',
            'tested'            => get_bloginfo( 'version' ),
            'requires_php'      => '7.4',
            'last_updated'      => $published_at ? date( 'Y-m-d', strtotime( $published_at ) ) : '',
            'sections'          => [
                'description' => '<p>Plugin de WordPress que integra IAs (Anthropic Claude y Google Gemini) para generar diseños con Impreza y WPBakery.</p>',
                'changelog'   => $changelog ?: '<p>Ver el <a href="' . esc_url( 'https://github.com/' . self::GITHUB_REPO . '/releases' ) . '" target="_blank">historial de releases en GitHub</a>.</p>',
            ],
        ];
    }

    /**
     * Corregir el nombre de la carpeta después de instalar la actualización.
     * GitHub puede nombrar la carpeta extraída de forma diferente.
     *
     * @param string      $source        Ruta de origen de la instalación.
     * @param string      $remote_source Fuente remota.
     * @param WP_Upgrader $upgrader      Instancia del upgrader.
     * @param array       $hook_extra    Datos extra del hook.
     * @return string
     */
    public function fix_folder_name( string $source, string $remote_source, $upgrader, array $hook_extra ): string {
        global $wp_filesystem;

        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_file ) {
            return $source;
        }

        $correct_folder = trailingslashit( $remote_source ) . ADR_IA_EDIT_SLUG . '/';

        // Si la carpeta ya tiene el nombre correcto, no hacer nada
        if ( $source === $correct_folder ) {
            return $source;
        }

        // Renombrar a la carpeta correcta
        if ( $wp_filesystem->move( $source, $correct_folder ) ) {
            return $correct_folder;
        }

        return $source;
    }

    // ─── VERIFICACIÓN MANUAL ──────────────────────────────────────────────────

    /**
     * Manejar la verificación manual de actualizaciones vía URL.
     */
    public function handle_manual_check(): void {
        if (
            ! isset( $_GET['adr_check_update'] ) ||
            '1' !== $_GET['adr_check_update'] ||
            ! current_user_can( 'update_plugins' ) ||
            ! isset( $_GET['_wpnonce'] ) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'adr_check_update' )
        ) {
            return;
        }

        // Limpiar caché para forzar nueva consulta
        $this->clear_cache();

        // Forzar verificación de actualizaciones de WordPress
        delete_site_transient( 'update_plugins' );
        wp_update_plugins();

        $release = $this->get_github_release();
        $latest  = $release ? $this->parse_version( $release['tag_name'] ?? '' ) : null;

        if ( $latest && version_compare( $latest, $this->current_version, '>' ) ) {
            $status = 'available';
            $version = $latest;
        } elseif ( $latest ) {
            $status = 'uptodate';
            $version = $latest;
        } else {
            $status = 'error';
            $version = '';
        }

        wp_safe_redirect(
            add_query_arg(
                [
                    'adr_update_status' => $status,
                    'adr_latest'        => urlencode( $version ),
                ],
                admin_url( 'plugins.php' )
            )
        );
        exit;
    }

    /**
     * Mostrar aviso admin después de verificar actualizaciones manualmente.
     */
    public function show_update_notice(): void {
        if ( ! isset( $_GET['adr_update_status'] ) ) {
            return;
        }

        $status  = sanitize_text_field( $_GET['adr_update_status'] );
        $latest  = sanitize_text_field( urldecode( $_GET['adr_latest'] ?? '' ) );
        $message = '';
        $type    = 'info';

        switch ( $status ) {
            case 'available':
                $type    = 'warning';
                $message = sprintf(
                    /* translators: %s: número de versión */
                    __( '🚀 <strong>ADR-IA-Edit:</strong> Hay una nueva versión disponible: <strong>%s</strong>. Podés actualizarla desde la lista de plugins.', 'adr-ia-edit' ),
                    esc_html( $latest )
                );
                break;

            case 'uptodate':
                $type    = 'success';
                $message = sprintf(
                    /* translators: %s: número de versión */
                    __( '✅ <strong>ADR-IA-Edit:</strong> Ya tenés la última versión instalada (<strong>%s</strong>).', 'adr-ia-edit' ),
                    esc_html( $latest )
                );
                break;

            case 'error':
                $type    = 'error';
                $message = __( '⚠️ <strong>ADR-IA-Edit:</strong> No se pudo conectar con GitHub para verificar actualizaciones. Revisá tu conexión.', 'adr-ia-edit' );
                break;
        }

        if ( $message ) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr( $type ),
                wp_kses( $message, [ 'strong' => [] ] )
            );
        }
    }

    /**
     * Agregar link "Buscar actualizaciones" en la lista de plugins.
     *
     * @param array $links Links de acción existentes.
     * @return array
     */
    public function add_check_update_link( array $links ): array {
        $check_url = wp_nonce_url(
            add_query_arg( 'adr_check_update', '1', admin_url( 'plugins.php' ) ),
            'adr_check_update'
        );

        $links['check_update'] = sprintf(
            '<a href="%s" title="%s">%s</a>',
            esc_url( $check_url ),
            esc_attr__( 'Buscar actualizaciones en GitHub', 'adr-ia-edit' ),
            esc_html__( '🔄 Buscar actualizaciones', 'adr-ia-edit' )
        );

        return $links;
    }

    // ─── HELPERS PRIVADOS ─────────────────────────────────────────────────────

    /**
     * Obtener el último release de GitHub (con caché).
     *
     * @return array|null Datos del release o null si falla.
     */
    private function get_github_release(): ?array {
        // Verificar caché
        $cached = get_site_transient( self::TRANSIENT_KEY );
        if ( false !== $cached ) {
            return $cached ?: null;
        }

        // Consultar la API de GitHub
        $response = wp_remote_get(
            self::GITHUB_API_URL,
            [
                'timeout' => 15,
                'headers' => [
                    'Accept'     => 'application/vnd.github.v3+json',
                    'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
                ],
            ]
        );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            // Guardar null en caché por 1 hora para no spamear la API
            set_site_transient( self::TRANSIENT_KEY, false, HOUR_IN_SECONDS );
            return null;
        }

        $body    = wp_remote_retrieve_body( $response );
        $release = json_decode( $body, true );

        if ( ! is_array( $release ) || empty( $release['tag_name'] ) ) {
            set_site_transient( self::TRANSIENT_KEY, false, HOUR_IN_SECONDS );
            return null;
        }

        // Guardar en caché
        set_site_transient( self::TRANSIENT_KEY, $release, self::CACHE_TTL );

        return $release;
    }

    /**
     * Obtener la URL de descarga del ZIP del release.
     * Busca primero un asset llamado 'adr-ia-edit.zip', si no usa el ZIP del source.
     *
     * @param array $release Datos del release de GitHub.
     * @return string URL de descarga o vacío si no se encuentra.
     */
    private function get_zip_download_url( array $release ): string {
        // Primero buscar un asset específico llamado adr-ia-edit.zip
        if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
            foreach ( $release['assets'] as $asset ) {
                if ( isset( $asset['name'] ) && self::RELEASE_ZIP_NAME === $asset['name'] ) {
                    return $asset['browser_download_url'] ?? '';
                }
            }
        }

        // Fallback: ZIP automático del source code de GitHub
        $tag = $release['tag_name'] ?? '';
        if ( $tag ) {
            return 'https://github.com/' . self::GITHUB_REPO . '/archive/refs/tags/' . rawurlencode( $tag ) . '.zip';
        }

        return '';
    }

    /**
     * Limpiar el caché de versión de GitHub.
     */
    public function clear_cache(): void {
        delete_site_transient( self::TRANSIENT_KEY );
    }

    /**
     * Limpiar el prefijo "v" de un tag de versión de GitHub (ej: "v1.2.0" → "1.2.0").
     *
     * @param string $tag Tag del release.
     * @return string Versión limpia.
     */
    private function parse_version( string $tag ): string {
        return ltrim( $tag, 'vV' );
    }

    /**
     * Formatear el changelog de Markdown a HTML básico para WordPress.
     *
     * @param string $markdown Contenido en Markdown del release.
     * @return string HTML básico.
     */
    private function format_changelog( string $markdown ): string {
        if ( empty( $markdown ) ) {
            return '';
        }

        // Conversión básica de Markdown a HTML
        $html = esc_html( $markdown );
        $html = preg_replace( '/^#{1,3}\s+(.+)$/m', '<h4>$1</h4>', $html );
        $html = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html );
        $html = preg_replace( '/^[\*\-]\s+(.+)$/m', '<li>$1</li>', $html );
        $html = preg_replace( '/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html );
        $html = nl2br( $html );

        return $html;
    }
}
