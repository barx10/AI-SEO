<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin page: llms.txt generator and manual editor.
 */
class AI_SEO_LLMS_Page {

    public function init() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_init', array( $this, 'handle_save' ) );
    }

    public function add_menu_page() {
        add_submenu_page(
            'ai-seo',
            'AI SEO – llms.txt',
            'llms.txt',
            'manage_options',
            'ai-seo-llms',
            array( $this, 'render_page' )
        );
    }

    /**
     * Persist a manual override (or clear it) to the plugin options.
     */
    public function handle_save() {
        if ( ! isset( $_POST['ai_seo_llms_nonce'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        check_admin_referer( 'ai_seo_save_llms', 'ai_seo_llms_nonce' );

        $options = get_option( 'ai_seo_options', array() );
        $content = isset( $_POST['llms_txt_content'] ) ? trim( wp_unslash( $_POST['llms_txt_content'] ) ) : '';

        // sanitize_textarea_field collapses newlines; preserve them by only
        // stripping tags and invalid characters.
        $options['llms_txt_content'] = wp_check_invalid_utf8( wp_strip_all_tags( $content ) );
        update_option( 'ai_seo_options', $options );

        add_settings_error( 'ai_seo_llms', 'saved', 'llms.txt-innhold lagret.', 'updated' );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $options   = get_option( 'ai_seo_options', array() );
        $manual    = isset( $options['llms_txt_content'] ) ? $options['llms_txt_content'] : '';
        $generated = AI_SEO_LLMS_Txt::generate_content();
        $llms_url  = home_url( '/llms.txt' );
        ?>
        <div class="wrap ai-seo-llms">
            <h1>llms.txt</h1>
            <?php settings_errors( 'ai_seo_llms' ); ?>
            <p>
                <code>llms.txt</code> hjelper KI-assistenter (ChatGPT, Claude, Perplexity m.fl.)
                å forstå og sitere nettstedet ditt. Filen genereres automatisk fra nettstedstittel,
                beskrivelse og publiserte sider/innlegg, men du kan overstyre den manuelt nedenfor.
            </p>
            <p>
                Verifiser her:
                <a href="<?php echo esc_url( $llms_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $llms_url ); ?></a>
            </p>

            <h2>Forhåndsvisning (automatisk generert)</h2>
            <textarea readonly rows="14" class="large-text code" style="font-family:monospace;"><?php echo esc_textarea( $generated ); ?></textarea>

            <form method="post" action="">
                <?php wp_nonce_field( 'ai_seo_save_llms', 'ai_seo_llms_nonce' ); ?>
                <h2>Manuell overstyring (valgfritt)</h2>
                <p class="description">Lar du feltet stå tomt, brukes den automatisk genererte filen over.</p>
                <textarea name="llms_txt_content" rows="14" class="large-text code" style="font-family:monospace;" placeholder="Lim inn egen llms.txt her for å overstyre den automatiske."><?php echo esc_textarea( $manual ); ?></textarea>
                <?php submit_button( 'Lagre llms.txt' ); ?>
            </form>
        </div>
        <?php
    }
}
