<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_SEO_Client {

    private $provider;
    private $model;
    private $api_key;

    public function __construct() {
        $options = get_option( 'ai_seo_options', array() );

        $this->provider = isset( $options['ai_provider'] ) ? $options['ai_provider'] : 'anthropic';
        $this->model    = isset( $options['ai_model'] ) ? $options['ai_model'] : 'claude-sonnet-4-5-20250929';
        $this->api_key  = '';

        if ( ! empty( $options['api_key'] ) ) {
            $this->api_key = AI_SEO_Settings_Page::decrypt_key( $options['api_key'] );
        }
    }

    /**
     * Send a prompt to the configured AI provider.
     *
     * @param  string $prompt The prompt text.
     * @return string|WP_Error Plain text response or WP_Error on failure.
     */
    public function send_request( $prompt ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'ai_seo_no_key', 'API-nøkkel er ikke konfigurert. Gå til Innstillinger > AI SEO.' );
        }

        if ( 'anthropic' === $this->provider ) {
            return $this->request_anthropic( $prompt );
        }

        if ( 'openai' === $this->provider ) {
            return $this->request_openai( $prompt );
        }

        return new WP_Error( 'ai_seo_invalid_provider', 'Ugyldig AI-leverandør konfigurert.' );
    }

    /**
     * Send request to the Anthropic Messages API.
     */
    private function request_anthropic( $prompt ) {
        $url = 'https://api.anthropic.com/v1/messages';

        $body = wp_json_encode( array(
            'model'      => $this->model,
            'max_tokens' => 1024,
            'messages'   => array(
                array(
                    'role'    => 'user',
                    'content' => $prompt,
                ),
            ),
        ) );

        $response = wp_remote_post( $url, array(
            'timeout' => 60,
            'headers' => array(
                'Content-Type'      => 'application/json',
                'x-api-key'         => $this->api_key,
                'anthropic-version' => '2023-06-01',
            ),
            'body' => $body,
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'ai_seo_request_failed', 'Forespørselen feilet: ' . $response->get_error_message() );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $data   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status !== 200 ) {
            $error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Ukjent feil fra Anthropic API.';
            return new WP_Error( 'ai_seo_api_error', 'Anthropic API-feil (' . $status . '): ' . $error_msg );
        }

        if ( isset( $data['content'][0]['text'] ) ) {
            return trim( $data['content'][0]['text'] );
        }

        return new WP_Error( 'ai_seo_parse_error', 'Kunne ikke lese svaret fra Anthropic API.' );
    }

    /**
     * Send request to the OpenAI Chat Completions API.
     */
    private function request_openai( $prompt ) {
        $url = 'https://api.openai.com/v1/chat/completions';

        $body = wp_json_encode( array(
            'model'      => $this->model,
            'max_tokens' => 1024,
            'messages'   => array(
                array(
                    'role'    => 'user',
                    'content' => $prompt,
                ),
            ),
        ) );

        $response = wp_remote_post( $url, array(
            'timeout' => 60,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ),
            'body' => $body,
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'ai_seo_request_failed', 'Forespørselen feilet: ' . $response->get_error_message() );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $data   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status !== 200 ) {
            $error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Ukjent feil fra OpenAI API.';
            return new WP_Error( 'ai_seo_api_error', 'OpenAI API-feil (' . $status . '): ' . $error_msg );
        }

        if ( isset( $data['choices'][0]['message']['content'] ) ) {
            return trim( $data['choices'][0]['message']['content'] );
        }

        return new WP_Error( 'ai_seo_parse_error', 'Kunne ikke lese svaret fra OpenAI API.' );
    }
}
