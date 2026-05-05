<?php
/**
 * Manejador de peticiones AJAX para la IA en ADR-IA-Edit
 *
 * @package ADR_IA_Edit
 */

// Seguridad: bloquear acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Clase ADR_AI_Handler
 *
 * Gestiona las peticiones AJAX entre el editor de WordPress y los proveedores de IA.
 * Recibe el prompt y el contenido del post, selecciona el proveedor correcto,
 * construye el mensaje y retorna el código generado.
 */
class ADR_AI_Handler {

    /**
     * Constructor: registra los hooks AJAX.
     */
    public function __construct() {
        // Solo para usuarios autenticados
        add_action( 'wp_ajax_adr_ia_generate', [ $this, 'handle_generate_request' ] );
        add_action( 'wp_ajax_adr_ia_test_connection', [ $this, 'handle_test_connection' ] );
    }

    /**
     * Manejar la petición AJAX de generación de contenido.
     */
    public function handle_generate_request(): void {
        // Verificar nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'adr_ia_edit_nonce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Error de seguridad. Recargá la página e intentá de nuevo.', 'adr-ia-edit' ) ] );
        }

        // Verificar permisos
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => __( 'No tenés permisos para usar esta función.', 'adr-ia-edit' ) ] );
        }

        // Obtener y sanear datos del formulario
        $prompt       = sanitize_textarea_field( wp_unslash( $_POST['prompt'] ?? '' ) );
        $post_content = wp_kses_post( wp_unslash( $_POST['post_content'] ?? '' ) );
        $action_type  = sanitize_text_field( wp_unslash( $_POST['action_type'] ?? 'generate_shortcode' ) );
        $post_id      = absint( $_POST['post_id'] ?? 0 );

        if ( empty( $prompt ) ) {
            wp_send_json_error( [ 'message' => __( 'El prompt no puede estar vacío.', 'adr-ia-edit' ) ] );
        }

        // Obtener configuración
        $options          = ADR_Options::get_options();
        $active_provider  = $options['active_provider'] ?? 'anthropic';
        $system_prompt    = ! empty( $options['system_prompt'] ) ? $options['system_prompt'] : ADR_Options::get_default_system_prompt();

        // Instanciar el proveedor de IA correcto
        $provider = $this->get_provider_instance( $active_provider, $options );

        if ( null === $provider ) {
            wp_send_json_error( [
                'message' => sprintf(
                    /* translators: %s: nombre del proveedor */
                    __( 'El proveedor "%s" no está disponible. Verificá la configuración.', 'adr-ia-edit' ),
                    $active_provider
                ),
            ] );
        }

        if ( ! $provider->has_api_key() ) {
            wp_send_json_error( [
                'message' => sprintf(
                    /* translators: %s: nombre del proveedor */
                    __( 'No hay API Key configurada para %s. Andá a ADR-IA-Edit → Opciones.', 'adr-ia-edit' ),
                    $provider->get_provider_name()
                ),
            ] );
        }

        // Construir el mensaje del usuario
        $user_message = $this->build_user_message( $prompt, $post_content, $action_type, $post_id );

        /**
         * Filtro para modificar el mensaje del usuario antes de enviarlo a la IA.
         *
         * @param string $user_message Mensaje construido.
         * @param string $prompt       Prompt original del usuario.
         * @param string $action_type  Tipo de acción seleccionada.
         * @param int    $post_id      ID del post.
         */
        $user_message = apply_filters( 'adr_ia_edit_user_message', $user_message, $prompt, $action_type, $post_id );

        /**
         * Filtro para modificar el prompt del sistema.
         *
         * @param string $system_prompt Prompt del sistema.
         * @param string $active_provider Proveedor activo.
         * @param string $action_type   Tipo de acción.
         */
        $system_prompt = apply_filters( 'adr_ia_edit_system_prompt', $system_prompt, $active_provider, $action_type );

        // Enviar a la IA
        $result = $provider->send_message( $system_prompt, $user_message );

        if ( ! $result['success'] ) {
            wp_send_json_error( [
                'message' => $result['error'] ?? __( 'Error desconocido al conectar con la IA.', 'adr-ia-edit' ),
            ] );
        }

        // Limpiar la respuesta (quitar markdown si lo hay)
        $generated_code = $this->clean_response( $result['content'] );

        // Guardar el último prompt usado en el post
        if ( $post_id > 0 ) {
            update_post_meta( $post_id, '_adr_ia_last_prompt', $prompt );
            update_post_meta( $post_id, '_adr_ia_last_generated', $generated_code );
        }

        /**
         * Acción ejecutada después de una generación exitosa.
         *
         * @param string $generated_code Código generado.
         * @param int    $post_id        ID del post.
         * @param string $provider_name  Nombre del proveedor.
         */
        do_action( 'adr_ia_edit_after_generate', $generated_code, $post_id, $provider->get_provider_name() );

        wp_send_json_success( [
            'code'     => $generated_code,
            'provider' => $provider->get_provider_name(),
        ] );
    }

    /**
     * Manejar la petición de prueba de conexión con la IA.
     */
    public function handle_test_connection(): void {
        // Verificar nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'adr_ia_edit_nonce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Error de seguridad.', 'adr-ia-edit' ) ] );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'adr-ia-edit' ) ] );
        }

        $provider_slug = sanitize_text_field( wp_unslash( $_POST['provider'] ?? '' ) );
        $options       = ADR_Options::get_options();
        $provider      = $this->get_provider_instance( $provider_slug, $options );

        if ( null === $provider || ! $provider->has_api_key() ) {
            wp_send_json_error( [ 'message' => __( 'API Key no configurada.', 'adr-ia-edit' ) ] );
        }

        // Petición de prueba simple
        $result = $provider->send_message(
            'Sos un asistente de prueba. Responde solo con "OK".',
            'Responde "Conexión exitosa con ' . $provider->get_provider_name() . '".'
        );

        if ( $result['success'] ) {
            wp_send_json_success( [
                'message'  => sprintf(
                    /* translators: %s: nombre del proveedor */
                    __( '✅ Conexión exitosa con %s', 'adr-ia-edit' ),
                    $provider->get_provider_name()
                ),
                'response' => substr( $result['content'], 0, 200 ),
            ] );
        } else {
            wp_send_json_error( [
                'message' => sprintf(
                    /* translators: 1: nombre del proveedor, 2: mensaje de error */
                    __( '❌ Error con %1$s: %2$s', 'adr-ia-edit' ),
                    $provider->get_provider_name(),
                    $result['error'] ?? __( 'Error desconocido', 'adr-ia-edit' )
                ),
            ] );
        }
    }

    // ─── MÉTODOS PRIVADOS ─────────────────────────────────────────────────────

    /**
     * Obtener la instancia del proveedor de IA según el slug.
     *
     * @param string $provider_slug Slug del proveedor.
     * @param array  $options       Opciones del plugin.
     * @return ADR_Provider_Base|null
     */
    private function get_provider_instance( string $provider_slug, array $options ): ?ADR_Provider_Base {
        switch ( $provider_slug ) {
            case 'anthropic':
                return new ADR_Provider_Anthropic( $options['anthropic_api_key'] ?? '' );

            case 'gemini':
                return new ADR_Provider_Gemini( $options['gemini_api_key'] ?? '' );

            default:
                /**
                 * Filtro para registrar nuevos proveedores de IA.
                 *
                 * @param ADR_Provider_Base|null $instance     Instancia del proveedor (null por defecto).
                 * @param string                 $provider_slug Slug del proveedor solicitado.
                 * @param array                  $options       Opciones del plugin.
                 */
                return apply_filters( 'adr_ia_edit_provider_instance', null, $provider_slug, $options );
        }
    }

    /**
     * Construir el mensaje del usuario para enviar a la IA.
     *
     * @param string $prompt       Instrucción del usuario.
     * @param string $post_content Contenido actual del post.
     * @param string $action_type  Tipo de acción seleccionada.
     * @param int    $post_id      ID del post.
     * @return string
     */
    private function build_user_message( string $prompt, string $post_content, string $action_type, int $post_id ): string {
        $action_labels = [
            'generate_shortcode' => 'Generar shortcodes de WPBakery',
            'generate_html'      => 'Generar HTML/CSS',
            'improve_content'    => 'Mejorar el contenido existente',
        ];

        $action_label = $action_labels[ $action_type ] ?? $action_type;

        $message = "## Tarea: {$action_label}\n\n";
        $message .= "## Instrucción del usuario:\n{$prompt}\n\n";

        if ( ! empty( $post_content ) ) {
            // Limpiar el contenido del post para no enviar HTML excesivo
            $clean_content = wp_strip_all_tags( $post_content );
            $clean_content = substr( $clean_content, 0, 3000 ); // Límite de contexto
            $message .= "## Contenido actual del post:\n{$clean_content}\n\n";
        }

        if ( $post_id > 0 ) {
            $post  = get_post( $post_id );
            $title = $post ? $post->post_title : '';
            if ( $title ) {
                $message .= "## Título del post: {$title}\n\n";
            }
        }

        $message .= "## Resultado esperado:\nDevolvé ÚNICAMENTE el código generado, sin explicaciones ni bloques markdown.";

        return $message;
    }

    /**
     * Limpiar la respuesta de la IA quitando bloques de código markdown si los hay.
     *
     * @param string $content Respuesta de la IA.
     * @return string
     */
    private function clean_response( string $content ): string {
        // Quitar bloques de código markdown ```...```
        $content = preg_replace( '/^```(?:html|php|shortcode|wordpress|xml|css)?\n/im', '', $content );
        $content = preg_replace( '/\n?```\s*$/m', '', $content );

        return trim( $content );
    }
}
