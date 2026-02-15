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
        $this->api_key  = AI_SEO_Settings_Page::get_api_key();
    }

    /**
     * Send a prompt to the configured AI provider.
     *
     * @param  string $prompt The prompt text.
     * @return string|WP_Error Plain text response or WP_Error on failure.
     */
    public function send_request( $prompt ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'ai_seo_no_key', ai_seo_t( 'API-nøkkel er ikke konfigurert. Gå til Innstillinger > AI SEO.', 'API key is not configured. Go to Settings > AI SEO.' ) );
        }

        if ( 'anthropic' === $this->provider ) {
            return $this->request_anthropic( $prompt );
        }

        if ( 'openai' === $this->provider ) {
            return $this->request_openai( $prompt );
        }

        if ( 'google' === $this->provider ) {
            return $this->request_gemini( $prompt );
        }

        return new WP_Error( 'ai_seo_invalid_provider', ai_seo_t( 'Ugyldig AI-leverandør konfigurert.', 'Invalid AI provider configured.' ) );
    }

    /**
     * Send request to the Anthropic Messages API.
     */
    private function request_anthropic( $prompt ) {
        $url = 'https://api.anthropic.com/v1/messages';

        $body = wp_json_encode( array(
            'model'      => $this->model,
            'max_tokens' => 4096,
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
            return new WP_Error( 'ai_seo_request_failed', ai_seo_t( 'Forespørselen feilet: ', 'Request failed: ' ) . $response->get_error_message() );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $data   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status !== 200 ) {
            $error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : ai_seo_t( 'Ukjent feil fra Anthropic API.', 'Unknown error from Anthropic API.' );
            return new WP_Error( 'ai_seo_api_error', ai_seo_t( 'Anthropic API-feil (', 'Anthropic API error (' ) . $status . '): ' . $error_msg );
        }

        if ( isset( $data['content'][0]['text'] ) ) {
            return trim( $data['content'][0]['text'] );
        }

        return new WP_Error( 'ai_seo_parse_error', ai_seo_t( 'Kunne ikke lese svaret fra Anthropic API.', 'Could not parse the response from Anthropic API.' ) );
    }

    /**
     * Send request to the OpenAI Chat Completions API.
     */
    private function request_openai( $prompt ) {
        $url = 'https://api.openai.com/v1/chat/completions';

        $body = wp_json_encode( array(
            'model'      => $this->model,
            'max_tokens' => 4096,
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
            return new WP_Error( 'ai_seo_request_failed', ai_seo_t( 'Forespørselen feilet: ', 'Request failed: ' ) . $response->get_error_message() );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $data   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status !== 200 ) {
            $error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : ai_seo_t( 'Ukjent feil fra OpenAI API.', 'Unknown error from OpenAI API.' );
            return new WP_Error( 'ai_seo_api_error', ai_seo_t( 'OpenAI API-feil (', 'OpenAI API error (' ) . $status . '): ' . $error_msg );
        }

        if ( isset( $data['choices'][0]['message']['content'] ) ) {
            return trim( $data['choices'][0]['message']['content'] );
        }

        return new WP_Error( 'ai_seo_parse_error', ai_seo_t( 'Kunne ikke lese svaret fra OpenAI API.', 'Could not parse the response from OpenAI API.' ) );
    }

    /**
     * Send request to the Google Gemini API.
     */
    private function request_gemini( $prompt ) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode( $this->model ) . ':generateContent';

        $body = wp_json_encode( array(
            'contents' => array(
                array(
                    'parts' => array(
                        array(
                            'text' => $prompt,
                        ),
                    ),
                ),
            ),
            'generationConfig' => array(
                'maxOutputTokens' => 4096,
            ),
            'safetySettings' => array(
                array(
                    'category'  => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_ONLY_HIGH',
                ),
                array(
                    'category'  => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_ONLY_HIGH',
                ),
                array(
                    'category'  => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_ONLY_HIGH',
                ),
                array(
                    'category'  => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_ONLY_HIGH',
                ),
            ),
        ) );

        $response = wp_remote_post( $url, array(
            'timeout' => 60,
            'headers' => array(
                'Content-Type'    => 'application/json',
                'x-goog-api-key'  => $this->api_key,
            ),
            'body' => $body,
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'ai_seo_request_failed', ai_seo_t( 'Forespørselen feilet: ', 'Request failed: ' ) . $response->get_error_message() );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $data   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status !== 200 ) {
            $error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : ai_seo_t( 'Ukjent feil fra Gemini API.', 'Unknown error from Gemini API.' );
            return new WP_Error( 'ai_seo_api_error', ai_seo_t( 'Gemini API-feil (', 'Gemini API error (' ) . $status . '): ' . $error_msg );
        }

        // Check for prompt feedback (content filters on input).
        if ( isset( $data['promptFeedback']['blockReason'] ) ) {
            $block_reason = $data['promptFeedback']['blockReason'];
            return new WP_Error( 'ai_seo_blocked', ai_seo_t( 'Gemini blokkerte forespørselen: ', 'Gemini blocked the request: ' ) . $block_reason );
        }

        if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
            $text = trim( $data['candidates'][0]['content']['parts'][0]['text'] );

            // Check finish reason.
            $finish_reason = isset( $data['candidates'][0]['finishReason'] ) ? $data['candidates'][0]['finishReason'] : 'UNKNOWN';
            if ( 'STOP' !== $finish_reason && 'UNKNOWN' !== $finish_reason ) {
                // Append warning if stopped for non-natural reason.
                $text .= "\n\n" . ai_seo_t( '[Advarsel: Svaret ble kuttet av. Årsak: ', '[Warning: Response was truncated. Reason: ' ) . $finish_reason . ']';
            }

            return $text;
        }

        return new WP_Error( 'ai_seo_parse_error', ai_seo_t( 'Kunne ikke lese svaret fra Gemini API.', 'Could not parse the response from Gemini API.' ) );
    }
}
