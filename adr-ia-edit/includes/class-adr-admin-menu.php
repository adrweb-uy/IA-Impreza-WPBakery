<?php
/**
 * Clase para registrar el menú lateral de administración del plugin ADR-IA-Edit
 *
 * @package ADR_IA_Edit
 */

// Seguridad: bloquear acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Clase ADR_Admin_Menu
 *
 * Registra el menú principal y submenús en el panel lateral de WordPress.
 */
class ADR_Admin_Menu {

    /**
     * Constructor: registra el hook para agregar el menú.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
    }

    /**
     * Registrar menú principal y submenús.
     */
    public function register_menus(): void {
        // Menú principal
        add_menu_page(
            __( 'ADR-IA-Edit', 'adr-ia-edit' ),         // Título de la página
            __( 'ADR-IA-Edit', 'adr-ia-edit' ),         // Texto del menú
            'edit_posts',                                 // Capacidad requerida
            ADR_IA_EDIT_SLUG,                            // Slug del menú
            [ $this, 'render_main_page' ],               // Callback de la página
            $this->get_menu_icon(),                      // Ícono SVG inline
            58                                           // Posición (después de Páginas)
        );

        // Submenú: Opciones (única sección por ahora)
        add_submenu_page(
            ADR_IA_EDIT_SLUG,                            // Slug del menú padre
            __( 'Opciones – ADR-IA-Edit', 'adr-ia-edit' ), // Título de la página
            __( 'Opciones', 'adr-ia-edit' ),             // Texto del submenú
            'manage_options',                            // Capacidad requerida
            'adr-ia-edit-options',                       // Slug del submenú
            [ $this, 'render_options_page' ]             // Callback
        );

        // Remover el submenú duplicado del menú principal
        remove_submenu_page( ADR_IA_EDIT_SLUG, ADR_IA_EDIT_SLUG );
    }

    /**
     * Página principal del plugin (redirecciona a Opciones).
     */
    public function render_main_page(): void {
        wp_safe_redirect( admin_url( 'admin.php?page=adr-ia-edit-options' ) );
        exit;
    }

    /**
     * Página de Opciones – delega al template.
     */
    public function render_options_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No tenés permisos para acceder a esta página.', 'adr-ia-edit' ) );
        }

        $template = ADR_IA_EDIT_PLUGIN_DIR . 'templates/options-page.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
    }

    /**
     * Retorna el ícono SVG del menú (robot/IA) codificado en base64.
     *
     * @return string URL data del ícono SVG.
     */
    private function get_menu_icon(): string {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">'
            . '<rect x="3" y="7" width="18" height="13" rx="2"/>'
            . '<path d="M8 7V5a4 4 0 0 1 8 0v2"/>'
            . '<circle cx="9" cy="12" r="1" fill="currentColor" stroke="none"/>'
            . '<circle cx="15" cy="12" r="1" fill="currentColor" stroke="none"/>'
            . '<path d="M9 16s1 1 3 1 3-1 3-1"/>'
            . '<path d="M12 3v2"/>'
            . '</svg>';

        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
        return 'data:image/svg+xml;base64,' . base64_encode( $svg );
    }
}
