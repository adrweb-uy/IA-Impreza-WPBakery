<?php
/**
 * Template: Página de Opciones del plugin ADR-IA-Edit
 *
 * @package ADR_IA_Edit
 */

// Seguridad: bloquear acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap adr-ia-edit-options-page">

    <!-- ─── HEADER ──────────────────────────────────────────────── -->
    <div class="adr-page-header">
        <div class="adr-page-header__logo">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" width="40" height="40">
                <rect x="3" y="7" width="18" height="13" rx="2"/>
                <path d="M8 7V5a4 4 0 0 1 8 0v2"/>
                <circle cx="9" cy="12" r="1" fill="currentColor" stroke="none"/>
                <circle cx="15" cy="12" r="1" fill="currentColor" stroke="none"/>
                <path d="M9 16s1 1 3 1 3-1 3-1"/>
                <path d="M12 3v2"/>
            </svg>
        </div>
        <div class="adr-page-header__info">
            <h1 class="adr-page-header__title">ADR-IA-Edit</h1>
            <p class="adr-page-header__subtitle">
                <?php esc_html_e( 'Asistente de Diseño IA para WordPress', 'adr-ia-edit' ); ?>
                &nbsp;|&nbsp;
                <span class="adr-version">v<?php echo esc_html( ADR_IA_EDIT_VERSION ); ?></span>
            </p>
        </div>
        <div class="adr-page-header__status">
            <?php
            $options          = ADR_Options::get_options();
            $active_provider  = $options['active_provider'] ?? 'anthropic';
            $providers        = ADR_Options::get_available_providers();
            $provider_label   = $providers[ $active_provider ] ?? $active_provider;
            ?>
            <span class="adr-status-badge">
                <?php
                printf(
                    /* translators: %s: nombre del proveedor activo */
                    esc_html__( 'Proveedor activo: %s', 'adr-ia-edit' ),
                    esc_html( $provider_label )
                );
                ?>
            </span>
        </div>
    </div>

    <!-- ─── NOTIFICACIONES ──────────────────────────────────────── -->
    <?php settings_errors( ADR_Options::OPTION_GROUP ); ?>

    <!-- ─── FORMULARIO ──────────────────────────────────────────── -->
    <div class="adr-settings-layout">
        <!-- Columna principal: formulario -->
        <div class="adr-settings-main">
            <form method="post" action="options.php" id="adr-ia-edit-form">
                <?php
                settings_fields( ADR_Options::OPTION_GROUP );
                do_settings_sections( 'adr-ia-edit-options' );
                submit_button( __( '💾 Guardar Opciones', 'adr-ia-edit' ), 'primary adr-save-btn', 'submit', true );
                ?>
            </form>
        </div>

        <!-- Columna lateral: info y prueba -->
        <div class="adr-settings-sidebar">

            <!-- Test de conexión -->
            <div class="adr-sidebar-card">
                <h3 class="adr-sidebar-card__title">
                    🔌 <?php esc_html_e( 'Probar conexión', 'adr-ia-edit' ); ?>
                </h3>
                <p><?php esc_html_e( 'Verificá que la API Key del proveedor activo funcione correctamente.', 'adr-ia-edit' ); ?></p>
                <button type="button" id="adr-test-connection" class="button button-secondary adr-test-btn">
                    <?php esc_html_e( '▶ Probar ahora', 'adr-ia-edit' ); ?>
                </button>
                <div id="adr-test-result" class="adr-test-result" style="display:none;"></div>
            </div>

            <!-- Proveedores disponibles -->
            <div class="adr-sidebar-card">
                <h3 class="adr-sidebar-card__title">
                    🤖 <?php esc_html_e( 'Proveedores disponibles', 'adr-ia-edit' ); ?>
                </h3>
                <ul class="adr-provider-list">
                    <li class="adr-provider-item">
                        <strong>Anthropic Claude</strong><br>
                        <small>
                            <a href="https://console.anthropic.com/account/keys" target="_blank" rel="noopener noreferrer">console.anthropic.com</a>
                        </small>
                    </li>
                    <li class="adr-provider-item">
                        <strong>Google Gemini</strong><br>
                        <small>
                            <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer">aistudio.google.com</a>
                        </small>
                    </li>
                </ul>
            </div>

            <!-- Info del plugin -->
            <div class="adr-sidebar-card adr-sidebar-card--dark">
                <h3 class="adr-sidebar-card__title">📌 <?php esc_html_e( 'Sobre el plugin', 'adr-ia-edit' ); ?></h3>
                <ul class="adr-info-list">
                    <li><strong><?php esc_html_e( 'Versión:', 'adr-ia-edit' ); ?></strong> <?php echo esc_html( ADR_IA_EDIT_VERSION ); ?></li>
                    <li><strong><?php esc_html_e( 'Autor:', 'adr-ia-edit' ); ?></strong> <a href="https://adrianraineri.com" target="_blank" rel="noopener noreferrer">Luis Adrián Raineri</a></li>
                    <li><strong><?php esc_html_e( 'Tema:', 'adr-ia-edit' ); ?></strong> Impreza + WPBakery</li>
                </ul>
            </div>

        </div><!-- /.adr-settings-sidebar -->
    </div><!-- /.adr-settings-layout -->

</div><!-- /.wrap -->
