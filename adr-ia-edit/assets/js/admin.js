/**
 * ADR-IA-Edit — JavaScript del panel de administración
 * Autor: Luis Adrián Raineri | adrianraineri.com
 *
 * Funcionalidades:
 * - Generar código con IA via AJAX
 * - Insertar código en el editor de WordPress (TinyMCE / Bloque)
 * - Copiar código al portapapeles
 * - Mostrar/ocultar API Keys
 * - Probar conexión con la IA
 * - Reset del prompt del sistema
 */

/* global adrIaEditData, tinymce, wp */

(function ($) {
    'use strict';

    // ─── MÓDULO: META BOX ──────────────────────────────────────────────────────

    const MetaBox = {

        /**
         * Inicializar el meta box del editor de posts.
         */
        init() {
            if (!$('#adr-ia-edit-panel').length) return;

            this.bindEvents();
        },

        /**
         * Enlazar eventos del meta box.
         */
        bindEvents() {
            // Botón "Generar con IA"
            $('#adr-generate-btn').on('click', this.handleGenerate.bind(this));

            // Botón "Copiar código"
            $('#adr-copy-btn').on('click', this.handleCopy.bind(this));

            // Botón "Insertar en el post"
            $('#adr-insert-btn').on('click', this.handleInsert.bind(this));
        },

        /**
         * Manejar la petición de generación.
         */
        async handleGenerate() {
            const prompt = $('#adr_prompt').val().trim();

            if (!prompt) {
                this.showError(adrIaEditData.strings.emptyPrompt);
                return;
            }

            // Obtener el contenido actual del editor
            const includeContent = $('#adr_include_post_content').is(':checked');
            const postContent    = includeContent ? this.getPostContent() : '';
            const postId         = $('#adr_post_id').val();
            const actionType     = $('#adr_action_type').val();

            // Mostrar estado de carga
            this.setLoading(true);
            this.hideError();
            this.hideResult();

            try {
                const response = await $.ajax({
                    url:    adrIaEditData.ajaxUrl,
                    method: 'POST',
                    data: {
                        action:       'adr_ia_generate',
                        nonce:        adrIaEditData.nonce,
                        prompt:       prompt,
                        post_content: postContent,
                        action_type:  actionType,
                        post_id:      postId,
                    },
                });

                if (response.success) {
                    this.showResult(response.data.code, response.data.provider);
                } else {
                    this.showError(response.data.message || adrIaEditData.strings.error);
                }
            } catch (error) {
                this.showError(adrIaEditData.strings.error);
                console.error('[ADR-IA-Edit] Error AJAX:', error);
            } finally {
                this.setLoading(false);
            }
        },

        /**
         * Obtener el contenido actual del editor de WordPress.
         * Soporta TinyMCE (editor clásico) y el editor de bloques (Gutenberg).
         *
         * @returns {string} Contenido del editor.
         */
        getPostContent() {
            // Editor de bloques (Gutenberg)
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                try {
                    return wp.data.select('core/editor').getEditedPostContent() || '';
                } catch (e) {
                    // Ignorar errores del editor de bloques
                }
            }

            // Editor clásico (TinyMCE)
            if (typeof tinymce !== 'undefined') {
                const editor = tinymce.get('content');
                if (editor && !editor.isHidden()) {
                    return editor.getContent() || '';
                }
            }

            // Fallback: textarea del editor clásico
            return $('#content').val() || '';
        },

        /**
         * Insertar el código generado en el editor de WordPress.
         */
        handleInsert() {
            const code = $('#adr-result-code').val().trim();

            if (!code) {
                alert(adrIaEditData.strings.noContent);
                return;
            }

            let inserted = false;

            // Editor de bloques (Gutenberg) — insertar como bloque HTML
            if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch('core/editor')) {
                try {
                    const blocks = wp.blocks.rawHandler({ HTML: code });
                    if (blocks && blocks.length) {
                        wp.data.dispatch('core/editor').insertBlocks(blocks);
                        inserted = true;
                    }
                } catch (e) {
                    // Si falla, intentar con bloque HTML puro
                    try {
                        const htmlBlock = wp.blocks.createBlock('core/html', { content: code });
                        wp.data.dispatch('core/editor').insertBlocks([htmlBlock]);
                        inserted = true;
                    } catch (e2) {
                        console.warn('[ADR-IA-Edit] No se pudo insertar en Gutenberg:', e2);
                    }
                }
            }

            // Editor clásico (TinyMCE)
            if (!inserted && typeof tinymce !== 'undefined') {
                const editor = tinymce.get('content');
                if (editor && !editor.isHidden()) {
                    editor.setContent(code);
                    inserted = true;
                }
            }

            // Fallback: textarea del editor clásico
            if (!inserted) {
                const $textarea = $('#content');
                if ($textarea.length) {
                    $textarea.val(code);
                    inserted = true;
                }
            }

            if (inserted) {
                this.showInsertFeedback();
            } else {
                alert('No se pudo insertar automáticamente. Por favor, copiá el código manualmente.');
            }
        },

        /**
         * Copiar el código al portapapeles.
         */
        async handleCopy() {
            const code = $('#adr-result-code').val();
            if (!code) return;

            try {
                await navigator.clipboard.writeText(code);
                const $btn = $('#adr-copy-btn');
                const original = $btn.text();
                $btn.text('✅ ¡Copiado!');
                setTimeout(() => $btn.text(original), 2000);
            } catch (e) {
                // Fallback: seleccionar el textarea
                $('#adr-result-code').select();
                document.execCommand('copy');
            }
        },

        /**
         * Mostrar el código generado en el panel de resultado.
         *
         * @param {string} code     Código generado.
         * @param {string} provider Nombre del proveedor.
         */
        showResult(code, provider) {
            $('#adr-result-code').val(code);
            $('#adr-result-area').slideDown(250);

            // Actualizar el título con el proveedor
            if (provider) {
                $('.adr-result-title').text(`✅ Generado con ${provider}`);
            }
        },

        /**
         * Ocultar el área de resultado.
         */
        hideResult() {
            $('#adr-result-area').hide();
        },

        /**
         * Mostrar el mensaje de error.
         *
         * @param {string} message Mensaje de error.
         */
        showError(message) {
            $('#adr-error-message').text(message);
            $('#adr-error-area').slideDown(200);
        },

        /**
         * Ocultar el área de error.
         */
        hideError() {
            $('#adr-error-area').hide();
        },

        /**
         * Activar/desactivar estado de carga en el botón.
         *
         * @param {boolean} loading true para mostrar cargando.
         */
        setLoading(loading) {
            const $btn     = $('#adr-generate-btn');
            const $text    = $btn.find('.adr-btn-text');
            const $loadMsg = $btn.find('.adr-btn-loading');
            const $icon    = $btn.find('.adr-btn-icon');

            if (loading) {
                $btn.prop('disabled', true);
                $text.hide();
                $icon.hide();
                $loadMsg.css('display', 'inline-flex');
            } else {
                $btn.prop('disabled', false);
                $text.show();
                $icon.show();
                $loadMsg.hide();
            }
        },

        /**
         * Mostrar feedback visual al insertar el código.
         */
        showInsertFeedback() {
            const $btn = $('#adr-insert-btn');
            const original = $btn.text();
            $btn.text(adrIaEditData.strings.inserted).addClass('button-secondary').removeClass('button-primary');
            setTimeout(() => {
                $btn.text(original).removeClass('button-secondary').addClass('button-primary');
            }, 2500);
        },
    };


    // ─── MÓDULO: PÁGINA DE OPCIONES ───────────────────────────────────────────

    const OptionsPage = {

        /**
         * Inicializar la página de opciones.
         */
        init() {
            if (!$('#adr-ia-edit-form').length && !$('#adr-test-connection').length) return;

            this.bindEvents();
        },

        /**
         * Enlazar eventos de la página de opciones.
         */
        bindEvents() {
            // Mostrar/ocultar API Keys
            $(document).on('click', '.adr-toggle-key', function () {
                const targetId = $(this).data('target');
                const $input   = $('#' + targetId);
                const isPass   = $input.attr('type') === 'password';

                $input.attr('type', isPass ? 'text' : 'password');
                $(this).text(isPass ? '🙈 Ocultar' : '👁 Mostrar');
            });

            // Probar conexión
            $('#adr-test-connection').on('click', this.handleTestConnection.bind(this));

            // Restaurar prompt del sistema por defecto
            $('#adr_reset_system_prompt').on('click', function () {
                const defaultPrompt = $('#adr_default_system_prompt').val();
                if (confirm('¿Restaurar el prompt del sistema por defecto?')) {
                    $('#adr_system_prompt').val(defaultPrompt);
                }
            });
        },

        /**
         * Manejar la prueba de conexión con la IA.
         */
        async handleTestConnection() {
            const $btn    = $('#adr-test-connection');
            const $result = $('#adr-test-result');

            // Obtener el proveedor activo seleccionado en el form
            const provider = $('input[name="adr_ia_edit_settings[active_provider]"]:checked').val() || 'anthropic';

            $btn.text('⏳ Probando...').prop('disabled', true);
            $result.removeClass('adr-test-result--success adr-test-result--error').show();
            $result.text('Conectando con la IA...');

            try {
                const response = await $.ajax({
                    url:    adrIaEditData.ajaxUrl,
                    method: 'POST',
                    data: {
                        action:   'adr_ia_test_connection',
                        nonce:    adrIaEditData.nonce,
                        provider: provider,
                    },
                });

                if (response.success) {
                    $result.addClass('adr-test-result--success').text(response.data.message);
                } else {
                    $result.addClass('adr-test-result--error').text(response.data.message);
                }
            } catch (e) {
                $result.addClass('adr-test-result--error').text('Error de conexión. Verificá la consola del navegador.');
            } finally {
                $btn.text('▶ Probar ahora').prop('disabled', false);
            }
        },
    };


    // ─── INICIALIZACIÓN ───────────────────────────────────────────────────────

    $(document).ready(function () {
        MetaBox.init();
        OptionsPage.init();
    });

})(jQuery);
