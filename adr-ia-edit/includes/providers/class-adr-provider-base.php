<?php
/**
 * Clase base abstracta para proveedores de IA
 *
 * @package ADR_IA_Edit
 */

// Seguridad: bloquear acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Clase ADR_Provider_Base
 *
 * Define la interfaz que deben implementar todos los proveedores de IA.
 * Para agregar un nuevo proveedor, extendé esta clase e implementá los métodos abstractos.
 */
abstract class ADR_Provider_Base {

    /**
     * API Key del proveedor.
     *
     * @var string
     */
    protected string $api_key;

    /**
     * Modelo de IA a usar.
     *
     * @var string
     */
    protected string $model;

    /**
     * Timeout de las peticiones HTTP en segundos.
     *
     * @var int
     */
    protected int $timeout = 60;

    /**
     * Constructor.
     *
     * @param string $api_key Clave de API del proveedor.
     * @param string $model   Modelo de IA a usar (opcional).
     */
    public function __construct( string $api_key, string $model = '' ) {
        $this->api_key = $api_key;
        $this->model   = $model ?: $this->get_default_model();
    }

    /**
     * Enviar un mensaje a la IA y obtener la respuesta.
     *
     * @param string $system_prompt Prompt del sistema (contexto base).
     * @param string $user_message  Mensaje del usuario (prompt + contenido del post).
     * @return array{success: bool, content: string, error?: string}
     */
    abstract public function send_message( string $system_prompt, string $user_message ): array;

    /**
     * Retorna el modelo por defecto del proveedor.
     *
     * @return string
     */
    abstract public function get_default_model(): string;

    /**
     * Retorna el nombre legible del proveedor.
     *
     * @return string
     */
    abstract public function get_provider_name(): string;

    /**
     * Verificar si la API key está configurada.
     *
     * @return bool
     */
    public function has_api_key(): bool {
        return ! empty( $this->api_key );
    }

    /**
     * Hacer una petición HTTP POST usando wp_remote_post.
     *
     * @param string $url     URL del endpoint.
     * @param array  $headers Headers HTTP.
     * @param array  $body    Cuerpo de la petición (se codifica en JSON).
     * @return array{success: bool, data?: array, error?: string}
     */
    protected function http_post( string $url, array $headers, array $body ): array {
        $response = wp_remote_post(
            $url,
            [
                'headers' => $headers,
                'body'    => wp_json_encode( $body ),
                'timeout' => $this->timeout,
                'method'  => 'POST',
            ]
        );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body_raw    = wp_remote_retrieve_body( $response );
        $data        = json_decode( $body_raw, true );

        if ( $status_code < 200 || $status_code >= 300 ) {
            $error_message = $data['error']['message'] ?? $data['error'] ?? "HTTP Error {$status_code}";
            return [
                'success' => false,
                'error'   => is_string( $error_message ) ? $error_message : wp_json_encode( $error_message ),
            ];
        }

        return [
            'success' => true,
            'data'    => $data,
        ];
    }
}
