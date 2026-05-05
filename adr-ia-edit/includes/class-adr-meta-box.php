<?php
/**
 * Clase para gestionar el Meta Box del plugin ADR-IA-Edit en el editor de posts
 *
 * @package ADR_IA_Edit
 */

// Seguridad: bloquear acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Clase ADR_Meta_Box
 *
 * Agrega un panel (meta box) en el editor de posts para interactuar con la IA.
 */
class ADR_Meta_Box {

    /**
     * Constructor: registra el hook para agregar el meta box.
     */
    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ] );
        add_action( 'save_post', [ $this, 'save_meta_data' ] );
    }

    /**
     * Registrar el meta box en el editor de posts.
     */
    public function register_meta_box(): void {
        add_meta_box(
            'adr-ia-edit-meta-box',                        // ID único
            __( 'ADR-IA-Edit – Asistente de Diseño', 'adr-ia-edit' ), // Título del panel
            [ $this, 'render_meta_box' ],                  // Callback de render
            [ 'post', 'page' ],                            // Tipos de post
            'side',                                        // Contexto: lateral
            'high'                                         // Prioridad: alta (arriba del todo)
        );
    }

    /**
     * Renderizar el contenido del meta box – delega al template.
     *
     * @param WP_Post $post El post actual.
     */
    public function render_meta_box( WP_Post $post ): void {
        // Nonce para seguridad
        wp_nonce_field( 'adr_ia_edit_meta_box_nonce', 'adr_ia_edit_nonce_field' );

        $template = ADR_IA_EDIT_PLUGIN_DIR . 'templates/meta-box.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
    }

    /**
     * Guardar metadatos del post si se enviaron.
     * (Por ahora guardamos el último prompt usado, puede expandirse)
     *
     * @param int $post_id ID del post que se está guardando.
     */
    public function save_meta_data( int $post_id ): void {
        // Verificar nonce
        if ( ! isset( $_POST['adr_ia_edit_nonce_field'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['adr_ia_edit_nonce_field'] ) ), 'adr_ia_edit_meta_box_nonce' ) ) {
            return;
        }

        // Evitar autoguardado
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Verificar permisos
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Guardar el último prompt si existe
        if ( isset( $_POST['adr_ia_last_prompt'] ) ) {
            update_post_meta(
                $post_id,
                '_adr_ia_last_prompt',
                sanitize_textarea_field( wp_unslash( $_POST['adr_ia_last_prompt'] ) )
            );
        }
    }
}
