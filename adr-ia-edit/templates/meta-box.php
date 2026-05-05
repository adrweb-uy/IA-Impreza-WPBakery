<?php
/**
 * Template: Meta Box del plugin ADR-IA-Edit en el editor de posts
 *
 * Variables disponibles:
 * - $post (WP_Post): El post actual
 *
 * @package ADR_IA_Edit
 */

// Seguridad: bloquear acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Obtener el último prompt guardado para este post
$last_prompt = get_post_meta( $post->ID, '_adr_ia_last_prompt', true );
$options     = ADR_Options::get_options();
$provider    = $options['active_provider'] ?? 'anthropic';
$providers   = ADR_Options::get_available_providers();
$has_api_key = false;

switch ( $provider ) {
    case 'anthropic':
        $has_api_key = ! empty( $options['anthropic_api_key'] );
        break;
    case 'gemini':
        $has_api_key = ! empty( $options['gemini_api_key'] );
        break;
}

$provider_label = $providers[ $provider ] ?? $provider;
?>

<div class="adr-meta-box" id="adr-ia-edit-panel">

    <!-- ─── ESTADO / PROVEEDOR ACTIVO ─────────────────────────── -->
    <div class="adr-meta-provider-badge <?php echo $has_api_key ? 'adr-meta-provider-badge--ok' : 'adr-meta-provider-badge--error'; ?>">
        <?php if ( $has_api_key ) : ?>
            <span class="adr-status-dot adr-status-dot--ok"></span>
            <?php echo esc_html( $provider_label ); ?>
        <?php else : ?>
            <span class="adr-status-dot adr-status-dot--error"></span>
            <?php
            printf(
                /* translators: %s: URL de opciones */
                wp_kses(
                    __( 'Sin API Key — <a href="%s">Configurar</a>', 'adr-ia-edit' ),
                    [ 'a' => [ 'href' => [] ] ]
                ),
                esc_url( admin_url( 'admin.php?page=adr-ia-edit-options' ) )
            );
            ?>
        <?php endif; ?>
    </div>

    <!-- ─── TIPO DE ACCIÓN ─────────────────────────────────────── -->
    <div class="adr-meta-field">
        <label for="adr_action_type" class="adr-meta-label">
            <?php esc_html_e( 'Tipo de generación:', 'adr-ia-edit' ); ?>
        </label>
        <select id="adr_action_type" name="adr_action_type" class="adr-select widefat">
            <option value="generate_shortcode">
                🧩 <?php esc_html_e( 'Shortcodes de WPBakery', 'adr-ia-edit' ); ?>
            </option>
            <option value="generate_html">
                🌐 <?php esc_html_e( 'HTML / CSS personalizado', 'adr-ia-edit' ); ?>
            </option>
            <option value="improve_content">
                ✍️ <?php esc_html_e( 'Mejorar contenido existente', 'adr-ia-edit' ); ?>
            </option>
        </select>
    </div>

    <!-- ─── CAMPO DE PROMPT ────────────────────────────────────── -->
    <div class="adr-meta-field">
        <label for="adr_prompt" class="adr-meta-label">
            <?php esc_html_e( 'Tu instrucción para la IA:', 'adr-ia-edit' ); ?>
        </label>
        <textarea
            id="adr_prompt"
            name="adr_ia_last_prompt"
            class="adr-prompt-textarea widefat"
            rows="5"
            placeholder="<?php esc_attr_e( 'Ej: Creame una sección hero con título grande, subtítulo y un botón azul. Estilo profesional y moderno.', 'adr-ia-edit' ); ?>"
        ><?php echo esc_textarea( $last_prompt ); ?></textarea>
    </div>

    <!-- ─── OPCIONES ADICIONALES ───────────────────────────────── -->
    <div class="adr-meta-field adr-meta-options">
        <label class="adr-meta-checkbox">
            <input type="checkbox" id="adr_include_post_content" checked>
            <?php esc_html_e( 'Incluir contenido actual del post como contexto', 'adr-ia-edit' ); ?>
        </label>
    </div>

    <!-- ─── BOTÓN GENERAR ─────────────────────────────────────── -->
    <div class="adr-meta-actions">
        <button
            type="button"
            id="adr-generate-btn"
            class="button button-primary adr-generate-btn"
            <?php echo ! $has_api_key ? 'disabled' : ''; ?>
        >
            <span class="adr-btn-icon">✨</span>
            <span class="adr-btn-text"><?php esc_html_e( 'Generar con IA', 'adr-ia-edit' ); ?></span>
            <span class="adr-btn-loading" style="display:none;">
                <span class="adr-spinner"></span>
                <?php esc_html_e( 'Generando...', 'adr-ia-edit' ); ?>
            </span>
        </button>
    </div>

    <!-- ─── ÁREA DE RESULTADO ─────────────────────────────────── -->
    <div id="adr-result-area" class="adr-result-area" style="display:none;">

        <!-- Cabecera del resultado -->
        <div class="adr-result-header">
            <span class="adr-result-title">
                ✅ <?php esc_html_e( 'Código generado', 'adr-ia-edit' ); ?>
            </span>
            <div class="adr-result-actions">
                <button type="button" id="adr-copy-btn" class="button button-small">
                    📋 <?php esc_html_e( 'Copiar', 'adr-ia-edit' ); ?>
                </button>
                <button type="button" id="adr-insert-btn" class="button button-primary button-small">
                    ⬆ <?php esc_html_e( 'Insertar en el post', 'adr-ia-edit' ); ?>
                </button>
            </div>
        </div>

        <!-- Código generado -->
        <textarea
            id="adr-result-code"
            class="adr-result-code widefat"
            rows="8"
            readonly
        ></textarea>

        <!-- Nota de uso -->
        <p class="adr-result-note">
            <?php esc_html_e( '💡 Podés editar el código antes de insertar. Al insertar, se reemplaza todo el contenido del editor.', 'adr-ia-edit' ); ?>
        </p>

    </div><!-- /#adr-result-area -->

    <!-- ─── MENSAJES DE ERROR ──────────────────────────────────── -->
    <div id="adr-error-area" class="adr-error-area" style="display:none;">
        <span class="adr-error-icon">⚠️</span>
        <span id="adr-error-message"></span>
    </div>

    <!-- Datos ocultos para el script -->
    <input type="hidden" id="adr_post_id" value="<?php echo esc_attr( $post->ID ); ?>">

</div><!-- /.adr-meta-box -->
