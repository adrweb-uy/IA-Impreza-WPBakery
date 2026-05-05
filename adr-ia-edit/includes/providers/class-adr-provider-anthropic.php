<?php
/**
 * Proveedor de IA: Anthropic Claude
 *
 * @package ADR_IA_Edit
 */

// Seguridad: bloquear acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Clase ADR_Provider_Anthropic
 *
 * Implementa la integración con la API de Anthropic (Claude).
 * Documentación: https://docs.anthropic.com/en/api/getting-started
 */
class ADR_Provider_Anthropic extends ADR_Provider_Base {

    /**
     * URL base de la API de Anthropic.
     */
    const API_URL = 'https://api.anthropic.com/v1/messages';

    /**
     * Versión de la API de Anthropic.
     */
    const API_VERSION = '2023-06-01';

    /**
     * Tokens máximos en la respuesta.
     */
    const MAX_TOKENS = 4096;

    /**
     * {@inheritDoc}
     */
    public function get_default_model(): string {
        return 'claude-opus-4-5';
    }

    /**
     * {@inheritDoc}
     */
    public function get_provider_name(): string {
        return 'Anthropic (Claude)';
    }

    /**
     * Enviar mensaje a Claude y obtener la respuesta.
     *
     * @param string $system_prompt Instrucciones del sistema.
     * @param string $user_message  Mensaje del usuario.
     * @return array{success: bool, content: string, error?: string}
     */
    public function send_message( string $system_prompt, string $user_message ): array {
        if ( ! $this->has_api_key() ) {
            return [
                'success' => false,
                'content' => '',
                'error'   => __( 'La API Key de Anthropic no está configurada.', 'adr-ia-edit' ),
            ];
        }

        $headers = [
            'Content-Type'      => 'application/json',
            'x-api-key'         => $this->api_key,
            'anthropic-version' => self::API_VERSION,
        ];

        $body = [
            'model'      => $this->model,
            'max_tokens' => self::MAX_TOKENS,
            'system'     => $system_prompt,
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => $user_message,
                ],
            ],
        ];

        $result = $this->http_post( self::API_URL, $headers, $body );

        if ( ! $result['success'] ) {
            return [
                'success' => false,
                'content' => '',
                'error'   => $result['error'],
            ];
        }

        // Extraer el texto de la respuesta de Anthropic
        $content = $result['data']['content'][0]['text'] ?? '';

        if ( empty( $content ) ) {
            return [
                'success' => false,
                'content' => '',
                'error'   => __( 'La IA no devolvió contenido. Intentá de nuevo.', 'adr-ia-edit' ),
            ];
        }

        return [
            'success' => true,
            'content' => $content,
        ];
    }
}
