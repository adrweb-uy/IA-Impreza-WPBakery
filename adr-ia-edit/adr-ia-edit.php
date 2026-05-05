<?php
/**
 * Plugin Name: ADR-IA-Edit
 * Plugin URI:  https://adrianraineri.com
 * Description: Integra IAs (Anthropic Claude y Google Gemini) en el editor de WordPress para generar diseños con Impreza y WPBakery Page Builder.
 * Version:     1.3.1
 * Author:      Luis Adrián Raineri
 * Author URI:  https://adrianraineri.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: adr-ia-edit
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Seguridad: bloquear acceso directo al archivo
if (!defined('ABSPATH')) {
    exit;
}

// Constantes del plugin
// La versión se lee dinámicamente del header del plugin para que siempre esté sincronizada
$_adr_plugin_data = get_file_data(__FILE__, ['Version' => 'Version']);
define('ADR_IA_EDIT_VERSION', $_adr_plugin_data['Version'] ?? '1.0.0');
define('ADR_IA_EDIT_PLUGIN_FILE', __FILE__);
define('ADR_IA_EDIT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ADR_IA_EDIT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ADR_IA_EDIT_SLUG', 'adr-ia-edit');
unset($_adr_plugin_data); // Limpiar variable temporal


/**
 * Clase principal del plugin ADR-IA-Edit
 */
final class ADR_IA_Edit
{

    /**
     * Instancia única (Singleton)
     *
     * @var ADR_IA_Edit|null
     */
    private static ?ADR_IA_Edit $instance = null;

    /**
     * Obtener instancia única
     */
    public static function get_instance(): ADR_IA_Edit
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor privado
     */
    private function __construct()
    {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Cargar clases necesarias
     */
    private function load_dependencies(): void
    {
        // Sistema de actualizaciones desde GitHub
        require_once ADR_IA_EDIT_PLUGIN_DIR . 'includes/class-adr-updater.php';

        // Proveedor base (abstract)
        require_once ADR_IA_EDIT_PLUGIN_DIR . 'includes/providers/class-adr-provider-base.php';

        // Proveedores de IA
        require_once ADR_IA_EDIT_PLUGIN_DIR . 'includes/providers/class-adr-provider-anthropic.php';
        require_once ADR_IA_EDIT_PLUGIN_DIR . 'includes/providers/class-adr-provider-gemini.php';

        // Manejador de IA
        require_once ADR_IA_EDIT_PLUGIN_DIR . 'includes/class-adr-ai-handler.php';

        // Menú de administración
        require_once ADR_IA_EDIT_PLUGIN_DIR . 'includes/class-adr-admin-menu.php';

        // Página de opciones
        require_once ADR_IA_EDIT_PLUGIN_DIR . 'includes/class-adr-options.php';

        // Meta Box en editor
        require_once ADR_IA_EDIT_PLUGIN_DIR . 'includes/class-adr-meta-box.php';
    }

    /**
     * Registrar hooks de WordPress
     */
    private function init_hooks(): void
    {
        // Inicializar componentes en el hook 'init'
        add_action('init', [$this, 'init_components']);

        // Sistema de actualizaciones desde GitHub Releases
        new ADR_Updater(
            plugin_basename(ADR_IA_EDIT_PLUGIN_FILE),
            ADR_IA_EDIT_VERSION
        );

        // Cargar traducciones
        add_action('plugins_loaded', [$this, 'load_textdomain']);

        // Scripts y estilos del admin
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Hacer que los links de la lista de plugins abran en pestaña nueva
        add_filter('plugin_row_meta', [$this, 'modify_plugin_row_meta'], 10, 2);
    }

    /**
     * Modificar los links en la fila del plugin para que abran en pestaña nueva.
     *
     * @param array  $plugin_meta Array de links meta.
     * @param string $plugin_file Path del archivo del plugin.
     * @return array
     */
    public function modify_plugin_row_meta(array $plugin_meta, string $plugin_file): array
    {
        if (strpos($plugin_file, ADR_IA_EDIT_SLUG) !== false) {
            foreach ($plugin_meta as &$meta) {
                if (strpos($meta, 'href=') !== false && strpos($meta, 'target=') === false) {
                    $meta = str_replace('<a ', '<a target="_blank" rel="noopener noreferrer" ', $meta);
                }
            }
        }
        return $plugin_meta;
    }

    /**
     * Inicializar componentes del plugin
     */
    public function init_components(): void
    {
        // Inicializar menú de admin
        new ADR_Admin_Menu();

        // Inicializar opciones
        new ADR_Options();

        // Inicializar meta box
        new ADR_Meta_Box();

        // Inicializar manejador AJAX de IA
        new ADR_AI_Handler();
    }

    /**
     * Cargar traducciones del plugin
     */
    public function load_textdomain(): void
    {
        load_plugin_textdomain(
            'adr-ia-edit',
            false,
            dirname(plugin_basename(ADR_IA_EDIT_PLUGIN_FILE)) . '/languages/'
        );
    }

    /**
     * Encolar scripts y estilos en el admin
     *
     * @param string $hook Sufijo de la página admin actual.
     */
    public function enqueue_admin_assets(string $hook): void
    {
        // Solo cargar en páginas relevantes del plugin o en el editor de posts
        $allowed_pages = [
            'post.php',
            'post-new.php',
            'toplevel_page_' . ADR_IA_EDIT_SLUG,
            ADR_IA_EDIT_SLUG . '_page_adr-ia-edit-options',
        ];

        if (!in_array($hook, $allowed_pages, true)) {
            return;
        }

        // Estilos del admin
        wp_enqueue_style(
            'adr-ia-edit-admin',
            ADR_IA_EDIT_PLUGIN_URL . 'assets/css/admin.css',
            [],
            ADR_IA_EDIT_VERSION
        );

        // Script del admin
        wp_enqueue_script(
            'adr-ia-edit-admin',
            ADR_IA_EDIT_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            ADR_IA_EDIT_VERSION,
            true
        );

        // Pasar datos al JS via wp_localize_script
        wp_localize_script(
            'adr-ia-edit-admin',
            'adrIaEditData',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('adr_ia_edit_nonce'),
                'strings' => [
                    'generating' => __('Generando con IA...', 'adr-ia-edit'),
                    'error' => __('Error al conectar con la IA. Verificá la API Key.', 'adr-ia-edit'),
                    'inserted' => __('¡Código insertado en el editor!', 'adr-ia-edit'),
                    'noContent' => __('No hay contenido para insertar.', 'adr-ia-edit'),
                    'emptyPrompt' => __('Por favor escribí una instrucción antes de generar.', 'adr-ia-edit'),
                    'noApiKey' => __('No hay API Key configurada. Andá a ADR-IA-Edit → Opciones.', 'adr-ia-edit'),
                ],
            ]
        );
    }
}

/**
 * Función de acceso global al plugin
 */
function adr_ia_edit(): ADR_IA_Edit
{
    return ADR_IA_Edit::get_instance();
}

// Iniciar el plugin
adr_ia_edit();
