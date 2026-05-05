<?php
/**
 * Proveedor de IA: Google Gemini
 *
 * @package ADR_IA_Edit
 */

// Seguridad: bloquear acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Clase ADR_Provider_Gemini
 *
 * Implementa la integración con la API de Google Gemini.
 * Documentación: https://ai.google.dev/docs
 */
class ADR_Provider_Gemini extends ADR_Provider_Base {

    /**
     * URL base de la API de Google Gemini.
     */
    const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /**
     * {@inheritDoc}
     */
    public function get_default_model(): string {
        return 'gemini-2.0-flash';
    }

    /**
     * {@inheritDoc}
     */
    public function get_provider_name(): string {
        return 'Google Gemini';
    }

    /**
     * Enviar mensaje a Gemini y obtener la respuesta.
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
                'error'   => __( 'La API Key de Google Gemini no está configurada.', 'adr-ia-edit' ),
            ];
        }

        $url = self::API_BASE . $this->model . ':generateContent?key=' . rawurlencode( $this->api_key );

        $headers = [
            'Content-Type' => 'application/json',
        ];

        // Gemini usa systemInstruction para el prompt del sistema
        $body = [
            'systemInstruction' => [
                'parts' => [
                    [ 'text' => $system_prompt ],
                ],
            ],
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        [ 'text' => $user_message ],
                    ],
                ],
            ],
            'generationConfig' => [
                'maxOutputTokens' => 4096,
                'temperature'     => 0.7,
            ],
        ];

        $result = $this->http_post( $url, $headers, $body );

        if ( ! $result['success'] ) {
            return [
                'success' => false,
                'content' => '',
                'error'   => $result['error'],
            ];
        }

        // Extraer el texto de la respuesta de Gemini
        $content = $result['data']['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if ( empty( $content ) ) {
            // Verificar si hay un finish reason de error
            $finish_reason = $result['data']['candidates'][0]['finishReason'] ?? '';
            if ( 'SAFETY' === $finish_reason ) {
                return [
                    'success' => false,
                    'content' => '',
                    'error'   => __( 'La respuesta fue bloqueada por filtros de seguridad de Gemini.', 'adr-ia-edit' ),
                ];
            }

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
