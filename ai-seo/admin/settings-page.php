<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_SEO_Settings_Page {

    public function init() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function add_menu_page() {
        add_options_page(
            'AI SEO Innstillinger',
            'AI SEO',
            'manage_options',
            'ai-seo',
            array( $this, 'render_page' )
        );
    }

    public function register_settings() {
        register_setting( 'ai_seo_settings', 'ai_seo_options', array(
            'sanitize_callback' => array( $this, 'sanitize_options' ),
        ) );

        // API section.
        add_settings_section(
            'ai_seo_api_section',
            'API-innstillinger',
            array( $this, 'render_api_section' ),
            'ai-seo'
        );

        add_settings_field(
            'ai_provider',
            'AI-leverandør',
            array( $this, 'render_provider_field' ),
            'ai-seo',
            'ai_seo_api_section'
        );

        add_settings_field(
            'ai_model',
            'Modell',
            array( $this, 'render_model_field' ),
            'ai-seo',
            'ai_seo_api_section'
        );

        add_settings_field(
            'api_key',
            'API-nøkkel',
            array( $this, 'render_api_key_field' ),
            'ai-seo',
            'ai_seo_api_section'
        );

        // Modules section.
        add_settings_section(
            'ai_seo_modules_section',
            'Moduler',
            array( $this, 'render_modules_section' ),
            'ai-seo'
        );

        add_settings_field(
            'enable_sitemap',
            'XML-sitemap',
            array( $this, 'render_sitemap_field' ),
            'ai-seo',
            'ai_seo_modules_section'
        );

        add_settings_field(
            'enable_schema',
            'Schema.org-markering',
            array( $this, 'render_schema_field' ),
            'ai-seo',
            'ai_seo_modules_section'
        );

        add_settings_field(
            'enable_opengraph',
            'OpenGraph / Twitter Cards',
            array( $this, 'render_opengraph_field' ),
            'ai-seo',
            'ai_seo_modules_section'
        );
    }

    public function sanitize_options( $input ) {
        $sanitized = array();

        $sanitized['ai_provider'] = isset( $input['ai_provider'] ) && in_array( $input['ai_provider'], array( 'anthropic', 'openai', 'google' ), true )
            ? $input['ai_provider']
            : 'anthropic';

        $allowed_models = array(
            'claude-sonnet-4-5-20250929',
            'gpt-4o',
            'gemini-3-flash-preview',
        );
        $sanitized['ai_model'] = isset( $input['ai_model'] ) && in_array( $input['ai_model'], $allowed_models, true )
            ? $input['ai_model']
            : 'claude-sonnet-4-5-20250929';

        // Encrypt API key before storage.
        if ( ! empty( $input['api_key'] ) ) {
            $sanitized['api_key'] = wp_hash( '__ai_seo_salt__' ) !== $input['api_key']
                ? self::encrypt_key( sanitize_text_field( $input['api_key'] ) )
                : self::get_stored_key_raw();
        } else {
            $sanitized['api_key'] = '';
        }

        $sanitized['enable_sitemap']   = ! empty( $input['enable_sitemap'] ) ? 1 : 0;
        $sanitized['enable_schema']    = ! empty( $input['enable_schema'] ) ? 1 : 0;
        $sanitized['enable_opengraph'] = ! empty( $input['enable_opengraph'] ) ? 1 : 0;

        return $sanitized;
    }

    /**
     * Encrypt an API key using wp_hash as the basis for a simple XOR cipher.
     */
    public static function encrypt_key( $plain_key ) {
        if ( empty( $plain_key ) ) {
            return '';
        }
        $hash   = wp_hash( 'ai_seo_encryption_key' );
        $result = '';
        for ( $i = 0; $i < strlen( $plain_key ); $i++ ) {
            $result .= chr( ord( $plain_key[ $i ] ) ^ ord( $hash[ $i % strlen( $hash ) ] ) );
        }
        return base64_encode( $result );
    }

    /**
     * Decrypt an API key.
     */
    public static function decrypt_key( $encrypted_key ) {
        if ( empty( $encrypted_key ) ) {
            return '';
        }
        $decoded = base64_decode( $encrypted_key );
        if ( false === $decoded ) {
            return '';
        }
        $hash   = wp_hash( 'ai_seo_encryption_key' );
        $result = '';
        for ( $i = 0; $i < strlen( $decoded ); $i++ ) {
            $result .= chr( ord( $decoded[ $i ] ) ^ ord( $hash[ $i % strlen( $hash ) ] ) );
        }
        return $result;
    }

    /**
     * Get the raw stored (encrypted) key.
     */
    private static function get_stored_key_raw() {
        $options = get_option( 'ai_seo_options', array() );
        return isset( $options['api_key'] ) ? $options['api_key'] : '';
    }

    // --- Render callbacks ---

    public function render_api_section() {
        echo '<p>Konfigurer tilkoblingen til AI-tjenesten.</p>';
    }

    public function render_modules_section() {
        echo '<p>Aktiver eller deaktiver individuelle SEO-moduler.</p>';
    }

    public function render_provider_field() {
        $options  = get_option( 'ai_seo_options', array() );
        $provider = isset( $options['ai_provider'] ) ? $options['ai_provider'] : 'anthropic';
        ?>
        <select name="ai_seo_options[ai_provider]" id="ai_seo_provider">
            <option value="anthropic" <?php selected( $provider, 'anthropic' ); ?>>Claude (Anthropic)</option>
            <option value="openai" <?php selected( $provider, 'openai' ); ?>>OpenAI</option>
            <option value="google" <?php selected( $provider, 'google' ); ?>>Google (Gemini)</option>
        </select>
        <?php
    }

    public function render_model_field() {
        $options = get_option( 'ai_seo_options', array() );
        $model   = isset( $options['ai_model'] ) ? $options['ai_model'] : 'claude-sonnet-4-5-20250929';
        ?>
        <select name="ai_seo_options[ai_model]" id="ai_seo_model">
            <optgroup label="Anthropic" class="ai-seo-model-group" data-provider="anthropic">
                <option value="claude-sonnet-4-5-20250929" <?php selected( $model, 'claude-sonnet-4-5-20250929' ); ?>>Claude Sonnet 4.5</option>
            </optgroup>
            <optgroup label="OpenAI" class="ai-seo-model-group" data-provider="openai">
                <option value="gpt-4o" <?php selected( $model, 'gpt-4o' ); ?>>GPT-4o</option>
            </optgroup>
            <optgroup label="Google" class="ai-seo-model-group" data-provider="google">
                <option value="gemini-3-flash-preview" <?php selected( $model, 'gemini-3-flash-preview' ); ?>>Gemini 3 Flash Preview</option>
            </optgroup>
        </select>
        <p class="description">Velg modellen som skal brukes for AI-generering.</p>
        <?php
    }

    public function render_api_key_field() {
        $options      = get_option( 'ai_seo_options', array() );
        $has_key      = ! empty( $options['api_key'] );
        $display_value = $has_key ? '••••••••••••••••' : '';
        ?>
        <input type="password"
               name="ai_seo_options[api_key]"
               id="ai_seo_api_key"
               value="<?php echo esc_attr( $has_key ? wp_hash( '__ai_seo_salt__' ) : '' ); ?>"
               class="regular-text"
               autocomplete="off" />
        <button type="button" class="button button-secondary" id="ai-seo-toggle-key">Vis/skjul</button>
        <?php if ( $has_key ) : ?>
            <p class="description">API-nøkkel er lagret (kryptert). Lim inn en ny nøkkel for å erstatte den.</p>
        <?php else : ?>
            <p class="description">Skriv inn API-nøkkelen din. Den vil bli kryptert ved lagring.</p>
        <?php endif; ?>
        <?php
    }

    public function render_sitemap_field() {
        $options = get_option( 'ai_seo_options', array() );
        $enabled = isset( $options['enable_sitemap'] ) ? $options['enable_sitemap'] : 1;
        ?>
        <label>
            <input type="checkbox" name="ai_seo_options[enable_sitemap]" value="1" <?php checked( $enabled, 1 ); ?> />
            Aktiver XML-sitemap på <code>/sitemap.xml</code>
        </label>
        <?php
    }

    public function render_schema_field() {
        $options = get_option( 'ai_seo_options', array() );
        $enabled = isset( $options['enable_schema'] ) ? $options['enable_schema'] : 1;
        ?>
        <label>
            <input type="checkbox" name="ai_seo_options[enable_schema]" value="1" <?php checked( $enabled, 1 ); ?> />
            Aktiver Schema.org Article-markering på innlegg
        </label>
        <?php
    }

    public function render_opengraph_field() {
        $options = get_option( 'ai_seo_options', array() );
        $enabled = isset( $options['enable_opengraph'] ) ? $options['enable_opengraph'] : 1;
        ?>
        <label>
            <input type="checkbox" name="ai_seo_options[enable_opengraph]" value="1" <?php checked( $enabled, 1 ); ?> />
            Aktiver OpenGraph og Twitter Card-metatagger
        </label>
        <?php
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap ai-seo-settings">
            <h1>AI SEO Innstillinger</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'ai_seo_settings' );
                do_settings_sections( 'ai-seo' );
                submit_button( 'Lagre innstillinger' );
                ?>
            </form>
        </div>
        <?php
    }
}
