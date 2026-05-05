<?php
/**
 * Clase para gestionar las opciones del plugin ADR-IA-Edit
 *
 * @package ADR_IA_Edit
 */

// Seguridad: bloquear acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Clase ADR_Options
 *
 * Registra y gestiona todas las opciones del plugin usando la Settings API de WordPress.
 */
class ADR_Options {

    /**
     * Prefijo de opciones en la base de datos
     */
    const OPTION_GROUP = 'adr_ia_edit_options';
    const OPTION_NAME  = 'adr_ia_edit_settings';

    /**
     * Constructor: registra hooks para la Settings API.
     */
    public function __construct() {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    /**
     * Registrar ajustes, secciones y campos con la Settings API.
     */
    public function register_settings(): void {
        // Registrar el grupo de opciones
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            [ $this, 'sanitize_options' ]
        );

        // ─── SECCIÓN 1: Proveedor de IA ───────────────────────────────────────
        add_settings_section(
            'adr_section_provider',
            __( '🤖 Proveedor de IA', 'adr-ia-edit' ),
            [ $this, 'section_provider_description' ],
            'adr-ia-edit-options'
        );

        add_settings_field(
            'active_provider',
            __( 'Proveedor Activo', 'adr-ia-edit' ),
            [ $this, 'field_active_provider' ],
            'adr-ia-edit-options',
            'adr_section_provider'
        );

        // ─── SECCIÓN 2: API Keys ──────────────────────────────────────────────
        add_settings_section(
            'adr_section_api_keys',
            __( '🔑 Claves de API', 'adr-ia-edit' ),
            [ $this, 'section_api_keys_description' ],
            'adr-ia-edit-options'
        );

        add_settings_field(
            'anthropic_api_key',
            __( 'Anthropic (Claude) API Key', 'adr-ia-edit' ),
            [ $this, 'field_anthropic_api_key' ],
            'adr-ia-edit-options',
            'adr_section_api_keys'
        );

        add_settings_field(
            'gemini_api_key',
            __( 'Google Gemini API Key', 'adr-ia-edit' ),
            [ $this, 'field_gemini_api_key' ],
            'adr-ia-edit-options',
            'adr_section_api_keys'
        );

        // ─── SECCIÓN 3: Tema / Page Builder ───────────────────────────────────
        add_settings_section(
            'adr_section_theme',
            __( '🎨 Tema y Page Builder', 'adr-ia-edit' ),
            [ $this, 'section_theme_description' ],
            'adr-ia-edit-options'
        );

        add_settings_field(
            'active_theme',
            __( 'Tema activo', 'adr-ia-edit' ),
            [ $this, 'field_active_theme' ],
            'adr-ia-edit-options',
            'adr_section_theme'
        );

        // ─── SECCIÓN 4: Prompt del sistema ────────────────────────────────────
        add_settings_section(
            'adr_section_system_prompt',
            __( '📝 Instrucciones del Sistema', 'adr-ia-edit' ),
            [ $this, 'section_system_prompt_description' ],
            'adr-ia-edit-options'
        );

        add_settings_field(
            'system_prompt',
            __( 'Prompt base del sistema', 'adr-ia-edit' ),
            [ $this, 'field_system_prompt' ],
            'adr-ia-edit-options',
            'adr_section_system_prompt'
        );
    }

    // ─── DESCRIPCIONES DE SECCIONES ───────────────────────────────────────────

    public function section_provider_description(): void {
        echo '<p class="adr-section-description">' . esc_html__( 'Seleccioná cuál IA vas a usar para generar el contenido.', 'adr-ia-edit' ) . '</p>';
    }

    public function section_api_keys_description(): void {
        echo '<p class="adr-section-description">' . esc_html__( 'Ingresá las claves de API de los proveedores que quieras usar. Las claves se guardan en la base de datos de WordPress.', 'adr-ia-edit' ) . '</p>';
    }

    public function section_theme_description(): void {
        echo '<p class="adr-section-description">' . esc_html__( 'Indicá con qué tema y page builder estás trabajando para que la IA genere el código adecuado.', 'adr-ia-edit' ) . '</p>';
    }

    public function section_system_prompt_description(): void {
        echo '<p class="adr-section-description">' . esc_html__( 'Este prompt se enviará siempre al inicio de cada conversación con la IA para darle contexto de tu sitio.', 'adr-ia-edit' ) . '</p>';
    }

    // ─── RENDERIZADO DE CAMPOS ────────────────────────────────────────────────

    /**
     * Campo: proveedor activo
     */
    public function field_active_provider(): void {
        $options  = $this->get_options();
        $current  = $options['active_provider'] ?? 'anthropic';
        $providers = $this->get_available_providers();
        ?>
        <div class="adr-radio-group">
            <?php foreach ( $providers as $key => $label ) : ?>
                <label class="adr-radio-label">
                    <input
                        type="radio"
                        name="<?php echo esc_attr( self::OPTION_NAME ); ?>[active_provider]"
                        value="<?php echo esc_attr( $key ); ?>"
                        <?php checked( $current, $key ); ?>
                    >
                    <?php echo esc_html( $label ); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Campo: Anthropic API Key
     */
    public function field_anthropic_api_key(): void {
        $options = $this->get_options();
        $value   = $options['anthropic_api_key'] ?? '';
        ?>
        <div class="adr-field-group">
            <input
                type="password"
                id="adr_anthropic_api_key"
                name="<?php echo esc_attr( self::OPTION_NAME ); ?>[anthropic_api_key]"
                value="<?php echo esc_attr( $value ); ?>"
                class="adr-api-key-input"
                placeholder="sk-ant-api03-..."
                autocomplete="new-password"
            >
            <button type="button" class="button adr-toggle-key" data-target="adr_anthropic_api_key">
                👁 <?php esc_html_e( 'Mostrar', 'adr-ia-edit' ); ?>
            </button>
        </div>
        <p class="description">
            <?php
            printf(
                /* translators: %s: enlace a la consola de Anthropic */
                esc_html__( 'Obtenés tu clave en %s', 'adr-ia-edit' ),
                '<a href="https://console.anthropic.com/account/keys" target="_blank" rel="noopener noreferrer">console.anthropic.com</a>'
            );
            ?>
        </p>
        <?php
    }

    /**
     * Campo: Google Gemini API Key
     */
    public function field_gemini_api_key(): void {
        $options = $this->get_options();
        $value   = $options['gemini_api_key'] ?? '';
        ?>
        <div class="adr-field-group">
            <input
                type="password"
                id="adr_gemini_api_key"
                name="<?php echo esc_attr( self::OPTION_NAME ); ?>[gemini_api_key]"
                value="<?php echo esc_attr( $value ); ?>"
                class="adr-api-key-input"
                placeholder="AIzaSy..."
                autocomplete="new-password"
            >
            <button type="button" class="button adr-toggle-key" data-target="adr_gemini_api_key">
                👁 <?php esc_html_e( 'Mostrar', 'adr-ia-edit' ); ?>
            </button>
        </div>
        <p class="description">
            <?php
            printf(
                /* translators: %s: enlace a Google AI Studio */
                esc_html__( 'Obtenés tu clave en %s', 'adr-ia-edit' ),
                '<a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer">Google AI Studio</a>'
            );
            ?>
        </p>
        <?php
    }

    /**
     * Campo: Tema activo
     */
    public function field_active_theme(): void {
        $options = $this->get_options();
        $current = $options['active_theme'] ?? 'impreza_wpbakery';
        $themes  = $this->get_available_themes();
        ?>
        <select
            name="<?php echo esc_attr( self::OPTION_NAME ); ?>[active_theme]"
            id="adr_active_theme"
            class="adr-select"
        >
            <?php foreach ( $themes as $key => $data ) : ?>
                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>>
                    <?php echo esc_html( $data['label'] ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e( 'La IA usará shortcodes y estructura adecuada para el tema seleccionado.', 'adr-ia-edit' ); ?></p>
        <?php
    }

    /**
     * Campo: Prompt del sistema
     */
    public function field_system_prompt(): void {
        $options = $this->get_options();
        $value   = $options['system_prompt'] ?? $this->get_default_system_prompt();
        ?>
        <textarea
            name="<?php echo esc_attr( self::OPTION_NAME ); ?>[system_prompt]"
            id="adr_system_prompt"
            class="adr-textarea large-text"
            rows="8"
        ><?php echo esc_textarea( $value ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Podés personalizar el comportamiento base de la IA. Se usa en cada petición.', 'adr-ia-edit' ); ?>
        </p>
        <button type="button" class="button" id="adr_reset_system_prompt">
            <?php esc_html_e( '↩ Restaurar por defecto', 'adr-ia-edit' ); ?>
        </button>
        <input type="hidden" id="adr_default_system_prompt" value="<?php echo esc_attr( $this->get_default_system_prompt() ); ?>">
        <?php
    }

    // ─── SANEAMIENTO ──────────────────────────────────────────────────────────

    /**
     * Sanear las opciones antes de guardar.
     *
     * @param array $input Datos del formulario.
     * @return array Datos saneados.
     */
    public function sanitize_options( array $input ): array {
        $sanitized = [];

        // Proveedor activo
        $allowed_providers = array_keys( $this->get_available_providers() );
        $sanitized['active_provider'] = in_array( $input['active_provider'] ?? '', $allowed_providers, true )
            ? $input['active_provider']
            : 'anthropic';

        // API Keys – solo sanitize básico (no escapar caracteres especiales de las keys)
        $sanitized['anthropic_api_key'] = sanitize_text_field( $input['anthropic_api_key'] ?? '' );
        $sanitized['gemini_api_key']    = sanitize_text_field( $input['gemini_api_key'] ?? '' );

        // Tema activo
        $allowed_themes = array_keys( $this->get_available_themes() );
        $sanitized['active_theme'] = in_array( $input['active_theme'] ?? '', $allowed_themes, true )
            ? $input['active_theme']
            : 'impreza_wpbakery';

        // Prompt del sistema
        $sanitized['system_prompt'] = sanitize_textarea_field( $input['system_prompt'] ?? '' );

        return $sanitized;
    }

    // ─── HELPERS PÚBLICOS ─────────────────────────────────────────────────────

    /**
     * Obtener todas las opciones guardadas.
     *
     * @return array
     */
    public static function get_options(): array {
        return (array) get_option( self::OPTION_NAME, [] );
    }

    /**
     * Obtener un valor de opción específico.
     *
     * @param string $key     Clave de la opción.
     * @param mixed  $default Valor por defecto.
     * @return mixed
     */
    public static function get_option( string $key, $default = null ) {
        $options = self::get_options();
        return $options[ $key ] ?? $default;
    }

    /**
     * Retorna los proveedores de IA disponibles.
     * Extensible via filtro 'adr_ia_edit_providers'.
     *
     * @return array<string, string>
     */
    public static function get_available_providers(): array {
        $providers = [
            'anthropic' => '🔵 Anthropic (Claude)',
            'gemini'    => '🟢 Google Gemini',
        ];

        /**
         * Filtro para agregar nuevos proveedores de IA.
         *
         * @param array $providers Array de proveedores ['slug' => 'Nombre'].
         */
        return apply_filters( 'adr_ia_edit_providers', $providers );
    }

    /**
     * Retorna los temas/page builders disponibles.
     * Extensible via filtro 'adr_ia_edit_themes'.
     *
     * @return array<string, array>
     */
    public static function get_available_themes(): array {
        $themes = [
            'impreza_wpbakery' => [
                'label'       => '🎨 Impreza + WPBakery Page Builder',
                'description' => 'Genera shortcodes de WPBakery y elementos propios de Impreza.',
            ],
        ];

        /**
         * Filtro para agregar nuevos temas y page builders.
         *
         * @param array $themes Array de temas.
         */
        return apply_filters( 'adr_ia_edit_themes', $themes );
    }

    /**
     * Retorna el prompt del sistema por defecto según el tema activo.
     *
     * @return string
     */
    public static function get_default_system_prompt(): string {
        $options      = self::get_options();
        $active_theme = $options['active_theme'] ?? 'impreza_wpbakery';

        $prompts = [
            'impreza_wpbakery' => "Sos un experto en WordPress, el tema Impreza y WPBakery Page Builder.\n\n"
                . "Tu tarea es generar código de shortcodes de WPBakery o HTML/CSS compatible con el tema Impreza.\n\n"
                . "REGLAS IMPORTANTES:\n"
                . "- Siempre respondé ÚNICAMENTE con el código, sin explicaciones adicionales.\n"
                . "- Usá shortcodes válidos de WPBakery (vc_row, vc_column, vc_column_text, vc_single_image, etc.).\n"
                . "- Si el usuario pide diseño, generá shortcodes que funcionen con el constructor visual de WPBakery.\n"
                . "- Podés usar shortcodes propios de Impreza si son necesarios para el diseño.\n"
                . "- El código debe ser listo para pegar en el modo de texto del editor de WordPress.\n"
                . "- No incluyas bloques de código markdown (``` o ~~~), solo el código puro.\n"
                . "- Si el usuario te da el contenido actual del post, tenelo en cuenta para generar código coherente.",
        ];

        /**
         * Filtro para agregar prompts de sistema por defecto para nuevos temas.
         *
         * @param array  $prompts      Array de prompts por tema.
         * @param string $active_theme Tema activo.
         */
        $prompts = apply_filters( 'adr_ia_edit_default_system_prompts', $prompts, $active_theme );

        return $prompts[ $active_theme ] ?? $prompts['impreza_wpbakery'];
    }
}
