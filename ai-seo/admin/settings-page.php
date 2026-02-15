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
        add_menu_page(
            'AI SEO',
            'AI SEO',
            'manage_options',
            'ai-seo',
            array( $this, 'render_page' ),
            'dashicons-chart-area',
            80
        );

        add_submenu_page(
            'ai-seo',
            'AI SEO Innstillinger',
            'Innstillinger',
            'manage_options',
            'ai-seo',
            array( $this, 'render_page' )
        );
    }

    public function register_settings() {
        register_setting( 'ai_seo_settings', 'ai_seo_options', array(
            'sanitize_callback' => array( $this, 'sanitize_options' ),
        ) );

        // General section.
        add_settings_section( 'ai_seo_general_section', 'Generelt', array( $this, 'render_general_section' ), 'ai-seo' );

        add_settings_field( 'content_language', 'Innholdsspråk', array( $this, 'render_language_field' ), 'ai-seo', 'ai_seo_general_section' );

        // API section.
        add_settings_section( 'ai_seo_api_section', 'API-innstillinger', array( $this, 'render_api_section' ), 'ai-seo' );

        add_settings_field( 'ai_provider', 'AI-leverandør', array( $this, 'render_provider_field' ), 'ai-seo', 'ai_seo_api_section' );
        add_settings_field( 'ai_model', 'Modell', array( $this, 'render_model_field' ), 'ai-seo', 'ai_seo_api_section' );
        add_settings_field( 'api_key', 'API-nøkkel', array( $this, 'render_api_key_field' ), 'ai-seo', 'ai_seo_api_section' );

        // Modules section.
        add_settings_section( 'ai_seo_modules_section', 'Moduler', array( $this, 'render_modules_section' ), 'ai-seo' );

        add_settings_field( 'enable_sitemap', 'XML-sitemap', array( $this, 'render_sitemap_field' ), 'ai-seo', 'ai_seo_modules_section' );
        add_settings_field( 'enable_schema', 'Schema.org-markering', array( $this, 'render_schema_field' ), 'ai-seo', 'ai_seo_modules_section' );
        add_settings_field( 'enable_opengraph', 'OpenGraph / Twitter Cards', array( $this, 'render_opengraph_field' ), 'ai-seo', 'ai_seo_modules_section' );
        add_settings_field( 'enable_breadcrumbs', 'Brødsmulesti', array( $this, 'render_breadcrumbs_field' ), 'ai-seo', 'ai_seo_modules_section' );
        add_settings_field( 'enable_redirects', 'Omdirigeringer', array( $this, 'render_redirects_field' ), 'ai-seo', 'ai_seo_modules_section' );

        // Social section.
        add_settings_section( 'ai_seo_social_section', 'Sosiale medier', array( $this, 'render_social_section' ), 'ai-seo' );

        add_settings_field( 'twitter_handle', 'Twitter / X-brukernavn', array( $this, 'render_twitter_field' ), 'ai-seo', 'ai_seo_social_section' );

        // Organization / LocalBusiness section.
        add_settings_section( 'ai_seo_org_section', 'Organisasjon / Bedrift', array( $this, 'render_org_section' ), 'ai-seo' );

        add_settings_field( 'schema_org_type', 'Organisasjonstype', array( $this, 'render_org_type_field' ), 'ai-seo', 'ai_seo_org_section' );
        add_settings_field( 'schema_org_phone', 'Telefonnummer', array( $this, 'render_org_phone_field' ), 'ai-seo', 'ai_seo_org_section' );
        add_settings_field( 'schema_org_email', 'E-post', array( $this, 'render_org_email_field' ), 'ai-seo', 'ai_seo_org_section' );
        add_settings_field( 'schema_org_address', 'Adresse', array( $this, 'render_org_address_field' ), 'ai-seo', 'ai_seo_org_section' );
    }

    public function sanitize_options( $input ) {
        $sanitized = array();

        // Content language.
        $sanitized['content_language'] = isset( $input['content_language'] ) && in_array( $input['content_language'], array( 'nb', 'en' ), true )
            ? $input['content_language']
            : 'nb';

        $sanitized['ai_provider'] = isset( $input['ai_provider'] ) && in_array( $input['ai_provider'], array( 'anthropic', 'openai', 'google' ), true )
            ? $input['ai_provider']
            : 'anthropic';

        $allowed_models = array(
            'claude-sonnet-4-5-20250929',
            'gpt-5-mini',
            'gpt-4.1-mini',
            'gpt-4o',
            'gemini-3-flash-preview',
            'gemini-2.5-flash',
        );
        $sanitized['ai_model'] = isset( $input['ai_model'] ) && in_array( $input['ai_model'], $allowed_models, true )
            ? $input['ai_model']
            : 'claude-sonnet-4-5-20250929';

        // Encrypt API key (skip if defined as constant in wp-config.php).
        if ( defined( 'AI_SEO_API_KEY' ) ) {
            // Constant takes precedence — don't store anything in DB.
            $sanitized['api_key'] = '';
        } elseif ( ! empty( $input['api_key'] ) ) {
            $sanitized['api_key'] = wp_hash( '__ai_seo_salt__' ) !== $input['api_key']
                ? self::encrypt_key( sanitize_text_field( $input['api_key'] ) )
                : self::get_stored_key_raw();
        } else {
            $sanitized['api_key'] = '';
        }

        // Modules.
        $sanitized['enable_sitemap']     = ! empty( $input['enable_sitemap'] ) ? 1 : 0;
        $sanitized['enable_schema']      = ! empty( $input['enable_schema'] ) ? 1 : 0;
        $sanitized['enable_opengraph']   = ! empty( $input['enable_opengraph'] ) ? 1 : 0;
        $sanitized['enable_breadcrumbs'] = ! empty( $input['enable_breadcrumbs'] ) ? 1 : 0;
        $sanitized['enable_redirects']   = ! empty( $input['enable_redirects'] ) ? 1 : 0;

        // Social.
        $twitter = isset( $input['twitter_handle'] ) ? sanitize_text_field( $input['twitter_handle'] ) : '';
        if ( ! empty( $twitter ) && strpos( $twitter, '@' ) !== 0 ) {
            $twitter = '@' . $twitter;
        }
        $sanitized['twitter_handle'] = $twitter;

        // Organization.
        $allowed_org_types = array( '', 'Organization', 'LocalBusiness', 'Restaurant', 'Store', 'MedicalBusiness', 'LegalService', 'FinancialService' );
        $sanitized['schema_org_type'] = isset( $input['schema_org_type'] ) && in_array( $input['schema_org_type'], $allowed_org_types, true )
            ? $input['schema_org_type']
            : '';

        $sanitized['schema_org_phone']   = isset( $input['schema_org_phone'] ) ? sanitize_text_field( $input['schema_org_phone'] ) : '';
        $sanitized['schema_org_email']   = isset( $input['schema_org_email'] ) ? sanitize_email( $input['schema_org_email'] ) : '';
        $sanitized['schema_org_address'] = isset( $input['schema_org_address'] ) ? sanitize_text_field( $input['schema_org_address'] ) : '';

        return $sanitized;
    }

    /**
     * Encrypt an API key.
     * Uses sodium if available, otherwise falls back to XOR.
     */
    public static function encrypt_key( $plain_key ) {
        if ( empty( $plain_key ) ) {
            return '';
        }

        // Try sodium first.
        if ( function_exists( 'sodium_crypto_secretbox' ) ) {
            $key   = self::get_sodium_key();
            $nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
            $encrypted = sodium_crypto_secretbox( $plain_key, $nonce, $key );
            return 'sodium:' . base64_encode( $nonce . $encrypted );
        }

        // Fallback to XOR.
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

        // Check for sodium prefix.
        if ( strpos( $encrypted_key, 'sodium:' ) === 0 && function_exists( 'sodium_crypto_secretbox_open' ) ) {
            $key     = self::get_sodium_key();
            $decoded = base64_decode( substr( $encrypted_key, 7 ) );
            if ( false === $decoded || strlen( $decoded ) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
                return '';
            }
            $nonce      = substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
            $ciphertext = substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
            $decrypted  = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );
            return false === $decrypted ? '' : $decrypted;
        }

        // XOR fallback.
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
     * Derive a sodium-compatible 32-byte key.
     */
    private static function get_sodium_key() {
        $salt = wp_hash( 'ai_seo_sodium_key_v1' );
        return hash( 'sha256', $salt, true );
    }

    private static function get_stored_key_raw() {
        $options = get_option( 'ai_seo_options', array() );
        return isset( $options['api_key'] ) ? $options['api_key'] : '';
    }

    /**
     * Get the decrypted API key.
     *
     * Priority: wp-config.php constant > encrypted DB value.
     */
    public static function get_api_key() {
        if ( defined( 'AI_SEO_API_KEY' ) && AI_SEO_API_KEY ) {
            return AI_SEO_API_KEY;
        }

        $options = get_option( 'ai_seo_options', array() );
        if ( ! empty( $options['api_key'] ) ) {
            return self::decrypt_key( $options['api_key'] );
        }

        return '';
    }

    /**
     * Check rate limit for AI API calls.
     *
     * @return bool True if within limit, false if rate limited.
     */
    public static function check_rate_limit() {
        $user_id   = get_current_user_id();
        $key       = 'ai_seo_rate_' . $user_id;
        $max_calls = 30; // Per minute.
        $window    = 60;

        $current = get_transient( $key );
        if ( false === $current ) {
            set_transient( $key, 1, $window );
            return true;
        }

        if ( (int) $current >= $max_calls ) {
            return false;
        }

        set_transient( $key, (int) $current + 1, $window );
        return true;
    }

    // --- Render callbacks ---

    public function render_general_section() {
        echo '<p>Grunnleggende innstillinger for innholdsanalyse.</p>';
    }

    public function render_language_field() {
        $options = get_option( 'ai_seo_options', array() );
        $lang    = isset( $options['content_language'] ) ? $options['content_language'] : 'nb';
        ?>
        <select name="ai_seo_options[content_language]">
            <option value="nb" <?php selected( $lang, 'nb' ); ?>>Norsk</option>
            <option value="en" <?php selected( $lang, 'en' ); ?>>English</option>
        </select>
        <p class="description">Velg språket innholdet ditt er skrevet på. Dette påvirker lesbarhetsanalysen (Flesch-Kincaid-koeffisient, passiv stemme, overgangsord).</p>
        <?php
    }

    public function render_api_section() {
        echo '<p>Konfigurer tilkoblingen til AI-tjenesten.</p>';
    }

    public function render_modules_section() {
        echo '<p>Aktiver eller deaktiver individuelle SEO-moduler.</p>';
    }

    public function render_social_section() {
        echo '<p>Innstillinger for sosiale medier.</p>';
    }

    public function render_org_section() {
        echo '<p>Legg til organisasjons- eller bedriftsinformasjon for Schema.org-markering på forsiden.</p>';
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
                <option value="gpt-5-mini" <?php selected( $model, 'gpt-5-mini' ); ?>>GPT-5 Mini</option>
                <option value="gpt-4.1-mini" <?php selected( $model, 'gpt-4.1-mini' ); ?>>GPT-4.1 Mini</option>
                <option value="gpt-4o" <?php selected( $model, 'gpt-4o' ); ?>>GPT-4o</option>
            </optgroup>
            <optgroup label="Google" class="ai-seo-model-group" data-provider="google">
                <option value="gemini-3-flash-preview" <?php selected( $model, 'gemini-3-flash-preview' ); ?>>Gemini 3 Flash Preview</option>
                <option value="gemini-2.5-flash" <?php selected( $model, 'gemini-2.5-flash' ); ?>>Gemini 2.5 Flash</option>
            </optgroup>
        </select>
        <p class="description">Velg modellen som skal brukes for AI-generering.</p>
        <?php
    }

    public function render_api_key_field() {
        $options      = get_option( 'ai_seo_options', array() );
        $has_key      = ! empty( $options['api_key'] );
        $has_constant = defined( 'AI_SEO_API_KEY' ) && AI_SEO_API_KEY;

        if ( $has_constant ) : ?>
            <input type="text" value="Definert via AI_SEO_API_KEY i wp-config.php" class="regular-text" disabled />
            <p class="description" style="color: #00a32a;">
                API-nøkkelen leses fra <code>wp-config.php</code>-konstanten <code>AI_SEO_API_KEY</code>. Dette er den sikreste metoden &mdash; nøkkelen lagres aldri i databasen.
            </p>
        <?php else : ?>
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
            <p class="description">
                <strong>Anbefalt:</strong> Definer nøkkelen i <code>wp-config.php</code> i stedet:<br />
                <code>define( 'AI_SEO_API_KEY', 'din-nøkkel-her' );</code>
            </p>
        <?php endif;
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
            Aktiver Schema.org-markering på innlegg
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

    public function render_breadcrumbs_field() {
        $options = get_option( 'ai_seo_options', array() );
        $enabled = isset( $options['enable_breadcrumbs'] ) ? $options['enable_breadcrumbs'] : 1;
        ?>
        <label>
            <input type="checkbox" name="ai_seo_options[enable_breadcrumbs]" value="1" <?php checked( $enabled, 1 ); ?> />
            Aktiver brødsmuler (shortcode <code>[ai_seo_breadcrumbs]</code> og BreadcrumbList JSON-LD)
        </label>
        <?php
    }

    public function render_redirects_field() {
        $options = get_option( 'ai_seo_options', array() );
        $enabled = isset( $options['enable_redirects'] ) ? $options['enable_redirects'] : 1;
        ?>
        <label>
            <input type="checkbox" name="ai_seo_options[enable_redirects]" value="1" <?php checked( $enabled, 1 ); ?> />
            Aktiver omdirigeringsmodul (301/302)
        </label>
        <p class="description">Administrer omdirigeringer under <a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-seo-redirects' ) ); ?>">AI SEO > Omdirigeringer</a>.</p>
        <?php
    }

    public function render_twitter_field() {
        $options = get_option( 'ai_seo_options', array() );
        $handle  = isset( $options['twitter_handle'] ) ? $options['twitter_handle'] : '';
        ?>
        <input type="text"
               name="ai_seo_options[twitter_handle]"
               value="<?php echo esc_attr( $handle ); ?>"
               class="regular-text"
               placeholder="@dittbrukernavn" />
        <p class="description">Brukes i Twitter Card-metatagger (<code>twitter:site</code> og <code>twitter:creator</code>).</p>
        <?php
    }

    public function render_org_type_field() {
        $options  = get_option( 'ai_seo_options', array() );
        $org_type = isset( $options['schema_org_type'] ) ? $options['schema_org_type'] : '';
        ?>
        <select name="ai_seo_options[schema_org_type]">
            <option value="" <?php selected( $org_type, '' ); ?>>Ingen (deaktivert)</option>
            <option value="Organization" <?php selected( $org_type, 'Organization' ); ?>>Organisasjon</option>
            <option value="LocalBusiness" <?php selected( $org_type, 'LocalBusiness' ); ?>>Lokal bedrift</option>
            <option value="Restaurant" <?php selected( $org_type, 'Restaurant' ); ?>>Restaurant</option>
            <option value="Store" <?php selected( $org_type, 'Store' ); ?>>Butikk</option>
            <option value="MedicalBusiness" <?php selected( $org_type, 'MedicalBusiness' ); ?>>Medisinsk virksomhet</option>
            <option value="LegalService" <?php selected( $org_type, 'LegalService' ); ?>>Juridisk tjeneste</option>
            <option value="FinancialService" <?php selected( $org_type, 'FinancialService' ); ?>>Finanstjeneste</option>
        </select>
        <p class="description">Vises som JSON-LD på forsiden.</p>
        <?php
    }

    public function render_org_phone_field() {
        $options = get_option( 'ai_seo_options', array() );
        $phone   = isset( $options['schema_org_phone'] ) ? $options['schema_org_phone'] : '';
        ?>
        <input type="tel" name="ai_seo_options[schema_org_phone]" value="<?php echo esc_attr( $phone ); ?>" class="regular-text" placeholder="+47 12 34 56 78" />
        <?php
    }

    public function render_org_email_field() {
        $options = get_option( 'ai_seo_options', array() );
        $email   = isset( $options['schema_org_email'] ) ? $options['schema_org_email'] : '';
        ?>
        <input type="email" name="ai_seo_options[schema_org_email]" value="<?php echo esc_attr( $email ); ?>" class="regular-text" placeholder="post@eksempel.no" />
        <?php
    }

    public function render_org_address_field() {
        $options = get_option( 'ai_seo_options', array() );
        $address = isset( $options['schema_org_address'] ) ? $options['schema_org_address'] : '';
        ?>
        <input type="text" name="ai_seo_options[schema_org_address]" value="<?php echo esc_attr( $address ); ?>" class="regular-text" placeholder="Storgata 1, 0001 Oslo" />
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
