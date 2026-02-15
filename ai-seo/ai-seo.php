<?php
/**
 * Plugin Name: AI SEO
 * Plugin URI:  https://example.com/ai-seo
 * Description: AI-powered SEO plugin for WordPress with meta tags, sitemap, schema markup, readability analysis, redirects, and breadcrumbs.
 * Version:     2.0.0
 * Author:      AI SEO
 * License:     GPL-2.0-or-later
 * Text Domain: ai-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'AI_SEO_VERSION', '2.2.0' );
define( 'AI_SEO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_SEO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load plugin classes.
 */
require_once AI_SEO_PLUGIN_DIR . 'includes/i18n.php';
require_once AI_SEO_PLUGIN_DIR . 'includes/class-ai-client.php';
require_once AI_SEO_PLUGIN_DIR . 'includes/class-meta-handler.php';
require_once AI_SEO_PLUGIN_DIR . 'includes/class-sitemap.php';
require_once AI_SEO_PLUGIN_DIR . 'includes/class-schema.php';
require_once AI_SEO_PLUGIN_DIR . 'includes/class-readability.php';
require_once AI_SEO_PLUGIN_DIR . 'includes/class-seo-score.php';
require_once AI_SEO_PLUGIN_DIR . 'includes/class-redirects.php';
require_once AI_SEO_PLUGIN_DIR . 'includes/class-breadcrumbs.php';
require_once AI_SEO_PLUGIN_DIR . 'includes/class-dashboard-widget.php';
require_once AI_SEO_PLUGIN_DIR . 'includes/class-migration.php';
require_once AI_SEO_PLUGIN_DIR . 'admin/settings-page.php';
require_once AI_SEO_PLUGIN_DIR . 'admin/meta-box.php';
require_once AI_SEO_PLUGIN_DIR . 'admin/redirects-page.php';
require_once AI_SEO_PLUGIN_DIR . 'admin/migration-page.php';
require_once AI_SEO_PLUGIN_DIR . 'admin/bulk-columns.php';

/**
 * Initialize plugin components.
 */
function ai_seo_init() {
    $options = get_option( 'ai_seo_options', array() );

    // Always initialize meta handler.
    $meta_handler = new AI_SEO_Meta_Handler();
    $meta_handler->init();

    // Sitemap module.
    if ( ! isset( $options['enable_sitemap'] ) || $options['enable_sitemap'] ) {
        $sitemap = new AI_SEO_Sitemap();
        $sitemap->init();
    }

    // Schema module.
    if ( ! isset( $options['enable_schema'] ) || $options['enable_schema'] ) {
        $schema = new AI_SEO_Schema();
        $schema->init();
    }

    // Breadcrumbs module.
    if ( ! isset( $options['enable_breadcrumbs'] ) || $options['enable_breadcrumbs'] ) {
        $breadcrumbs = new AI_SEO_Breadcrumbs();
        $breadcrumbs->init();
    }

    // Redirects module.
    if ( ! isset( $options['enable_redirects'] ) || $options['enable_redirects'] ) {
        $redirects = new AI_SEO_Redirects();
        $redirects->init();
    }

    // Settings page.
    $settings = new AI_SEO_Settings_Page();
    $settings->init();

    // Meta box.
    $meta_box = new AI_SEO_Meta_Box();
    $meta_box->init();

    // Redirects admin page.
    $redirects_page = new AI_SEO_Redirects_Page();
    $redirects_page->init();

    // Dashboard widget.
    $dashboard = new AI_SEO_Dashboard_Widget();
    $dashboard->init();

    // Migration page.
    $migration_page = new AI_SEO_Migration_Page();
    $migration_page->init();

    // Bulk columns in post list views.
    $bulk_columns = new AI_SEO_Bulk_Columns();
    $bulk_columns->init();
}
add_action( 'plugins_loaded', 'ai_seo_init' );

/**
 * Enqueue admin assets.
 */
function ai_seo_enqueue_admin_assets( $hook ) {
    $screen = get_current_screen();

    $is_editor     = in_array( $hook, array( 'post.php', 'post-new.php' ), true );
    $is_settings   = ( $screen && $screen->id === 'settings_page_ai-seo' );
    $is_dashboard  = ( $screen && $screen->id === 'dashboard' );
    $is_migration  = ( $screen && $screen->id === 'tools_page_ai-seo-migration' );
    $is_list       = ( $hook === 'edit.php' );
    $is_redirects  = ( $screen && $screen->id === 'tools_page_ai-seo-redirects' );

    if ( ! $is_editor && ! $is_settings && ! $is_dashboard && ! $is_migration && ! $is_list && ! $is_redirects ) {
        return;
    }

    wp_enqueue_style(
        'ai-seo-admin',
        AI_SEO_PLUGIN_URL . 'assets/admin.css',
        array(),
        AI_SEO_VERSION
    );

    wp_enqueue_script(
        'ai-seo-admin',
        AI_SEO_PLUGIN_URL . 'assets/admin.js',
        array(),
        AI_SEO_VERSION,
        true
    );

    // Enqueue media library for social image upload.
    if ( $is_editor ) {
        wp_enqueue_media();
    }

    wp_localize_script( 'ai-seo-admin', 'aiSeo', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'ai_seo_nonce' ),
    ) );
}
add_action( 'admin_enqueue_scripts', 'ai_seo_enqueue_admin_assets' );

/**
 * AJAX handler: Generate meta description.
 */
function ai_seo_ajax_generate_description() {
    check_ajax_referer( 'ai_seo_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( ai_seo_t( 'Ingen tilgang.', 'Access denied.' ) );
    }

    if ( ! AI_SEO_Settings_Page::check_rate_limit() ) {
        wp_send_json_error( ai_seo_t( 'For mange forespørsler. Vent litt og prøv igjen.', 'Too many requests. Please wait and try again.' ) );
    }

    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    if ( ! $post_id ) {
        wp_send_json_error( ai_seo_t( 'Ugyldig innleggs-ID.', 'Invalid post ID.' ) );
    }

    $post = get_post( $post_id );
    if ( ! $post ) {
        wp_send_json_error( ai_seo_t( 'Innlegget ble ikke funnet.', 'Post not found.' ) );
    }

    $content = wp_strip_all_tags( $post->post_content );
    $content = mb_substr( $content, 0, 3000 );

    $prompt = ai_seo_t(
        "Du er en SEO-ekspert. Skriv en kort og engasjerende metabeskrivelse (maks 160 tegn) for følgende innhold. Svar kun med metabeskrivelsen, uten anførselstegn eller ekstra tekst.\n\nInnhold:\n",
        "You are an SEO expert. Write a short and engaging meta description (max 160 characters) for the following content. Reply only with the meta description, without quotes or extra text.\n\nContent:\n"
    ) . $content;

    $client = new AI_SEO_Client();
    $result = $client->send_request( $prompt );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    wp_send_json_success( array( 'text' => sanitize_text_field( $result ) ) );
}
add_action( 'wp_ajax_ai_seo_generate_description', 'ai_seo_ajax_generate_description' );

/**
 * AJAX handler: Suggest titles.
 */
function ai_seo_ajax_suggest_title() {
    check_ajax_referer( 'ai_seo_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( ai_seo_t( 'Ingen tilgang.', 'Access denied.' ) );
    }

    if ( ! AI_SEO_Settings_Page::check_rate_limit() ) {
        wp_send_json_error( ai_seo_t( 'For mange forespørsler. Vent litt og prøv igjen.', 'Too many requests. Please wait and try again.' ) );
    }

    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    $keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';

    if ( ! $post_id ) {
        wp_send_json_error( ai_seo_t( 'Ugyldig innleggs-ID.', 'Invalid post ID.' ) );
    }

    $post = get_post( $post_id );
    if ( ! $post ) {
        wp_send_json_error( ai_seo_t( 'Innlegget ble ikke funnet.', 'Post not found.' ) );
    }

    $content = wp_strip_all_tags( $post->post_content );
    $content = mb_substr( $content, 0, 3000 );

    $prompt = ai_seo_t(
        "Du er en SEO-ekspert. Foreslå 3 SEO-optimaliserte titler for følgende innhold.",
        "You are an SEO expert. Suggest 3 SEO-optimized titles for the following content."
    );
    $prompt .= ai_seo_t(
        " Hver tittel SKAL være mellom 30 og 60 tegn lang. IKKE bruk kolon (:) i titlene.",
        " Each title MUST be between 30 and 60 characters long. Do NOT use colons (:) in the titles."
    );
    if ( $keyword ) {
        $prompt .= ai_seo_t(
            " Søkeordet som skal inkluderes er: «{$keyword}».",
            " The keyword to include is: \"{$keyword}\"."
        );
    }
    $prompt .= ai_seo_t(
        " Svar med kun de 3 titlene, én per linje, nummerert 1-3. Ingen ekstra tekst.\n\nInnhold:\n",
        " Reply with only the 3 titles, one per line, numbered 1-3. No extra text.\n\nContent:\n"
    ) . $content;

    $client = new AI_SEO_Client();
    $result = $client->send_request( $prompt );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    wp_send_json_success( array( 'text' => sanitize_text_field( $result ) ) );
}
add_action( 'wp_ajax_ai_seo_suggest_title', 'ai_seo_ajax_suggest_title' );

/**
 * AJAX handler: Suggest focus keyword.
 */
function ai_seo_ajax_suggest_keyword() {
    check_ajax_referer( 'ai_seo_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( ai_seo_t( 'Ingen tilgang.', 'Access denied.' ) );
    }

    if ( ! AI_SEO_Settings_Page::check_rate_limit() ) {
        wp_send_json_error( ai_seo_t( 'For mange forespørsler. Vent litt og prøv igjen.', 'Too many requests. Please wait and try again.' ) );
    }

    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

    if ( ! $post_id ) {
        wp_send_json_error( ai_seo_t( 'Ugyldig innleggs-ID.', 'Invalid post ID.' ) );
    }

    $post = get_post( $post_id );
    if ( ! $post ) {
        wp_send_json_error( ai_seo_t( 'Innlegget ble ikke funnet.', 'Post not found.' ) );
    }

    $content = wp_strip_all_tags( $post->post_content );
    $content = mb_substr( $content, 0, 3000 );
    $title   = $post->post_title;

    $prompt = ai_seo_t(
        "Du er en SEO-ekspert. Basert på tittelen og innholdet nedenfor, foreslå det beste fokus-søkeordet (1–3 ord) for denne siden. Søkeordet skal være det mest relevante søkeordet brukere vil søke etter i Google for å finne denne siden. Svar KUN med selve søkeordet – ingen forklaring, ingen anførselstegn, ingen ekstra tekst.\n\nTittel: ",
        "You are an SEO expert. Based on the title and content below, suggest the best focus keyword (1–3 words) for this page. The keyword should be the most relevant term users would search for on Google to find this page. Reply ONLY with the keyword itself – no explanation, no quotes, no extra text.\n\nTitle: "
    ) . $title . ai_seo_t( "\n\nInnhold:\n", "\n\nContent:\n" ) . $content;

    $client = new AI_SEO_Client();
    $result = $client->send_request( $prompt );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    $keyword = sanitize_text_field( trim( $result ) );

    wp_send_json_success( array( 'keyword' => $keyword ) );
}
add_action( 'wp_ajax_ai_seo_suggest_keyword', 'ai_seo_ajax_suggest_keyword' );

/**
 * AJAX handler: Analyze keywords.
 */
function ai_seo_ajax_analyze_keywords() {
    check_ajax_referer( 'ai_seo_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( ai_seo_t( 'Ingen tilgang.', 'Access denied.' ) );
    }

    if ( ! AI_SEO_Settings_Page::check_rate_limit() ) {
        wp_send_json_error( ai_seo_t( 'For mange forespørsler. Vent litt og prøv igjen.', 'Too many requests. Please wait and try again.' ) );
    }

    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

    if ( ! $post_id ) {
        wp_send_json_error( ai_seo_t( 'Ugyldig innleggs-ID.', 'Invalid post ID.' ) );
    }

    $post = get_post( $post_id );
    if ( ! $post ) {
        wp_send_json_error( ai_seo_t( 'Innlegget ble ikke funnet.', 'Post not found.' ) );
    }

    $content = wp_strip_all_tags( $post->post_content );
    $content = mb_substr( $content, 0, 3000 );

    $prompt = ai_seo_t(
        "Du er en SEO-ekspert. Analyser søkeordtettheten i følgende tekst. List opp de 10 mest brukte ordene (ekskluder stoppord), vis prosentandel for hvert, og gi 3 konkrete forslag for å forbedre SEO-en. Svar på norsk.\n\nInnhold:\n",
        "You are an SEO expert. Analyze the keyword density of the following text. List the 10 most used words (excluding stop words), show the percentage for each, and provide 3 specific suggestions to improve the SEO. Reply in English.\n\nContent:\n"
    ) . $content;

    $client = new AI_SEO_Client();
    $result = $client->send_request( $prompt );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    wp_send_json_success( array( 'text' => wp_kses_post( $result ) ) );
}
add_action( 'wp_ajax_ai_seo_analyze_keywords', 'ai_seo_ajax_analyze_keywords' );

/**
 * AJAX handler: Suggest internal links.
 */
function ai_seo_ajax_suggest_links() {
    check_ajax_referer( 'ai_seo_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( ai_seo_t( 'Ingen tilgang.', 'Access denied.' ) );
    }

    if ( ! AI_SEO_Settings_Page::check_rate_limit() ) {
        wp_send_json_error( ai_seo_t( 'For mange forespørsler. Vent litt og prøv igjen.', 'Too many requests. Please wait and try again.' ) );
    }

    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

    if ( ! $post_id ) {
        wp_send_json_error( ai_seo_t( 'Ugyldig innleggs-ID.', 'Invalid post ID.' ) );
    }

    $post = get_post( $post_id );
    if ( ! $post ) {
        wp_send_json_error( ai_seo_t( 'Innlegget ble ikke funnet.', 'Post not found.' ) );
    }

    $content = wp_strip_all_tags( $post->post_content );
    $content = mb_substr( $content, 0, 2000 );

    // Get other published posts to suggest links to.
    $other_posts = get_posts( array(
        'post_type'      => array( 'post', 'page' ),
        'post_status'    => 'publish',
        'posts_per_page' => 30,
        'exclude'        => array( $post_id ),
        'orderby'        => 'date',
        'order'          => 'DESC',
    ) );

    $titles_list = '';
    foreach ( $other_posts as $op ) {
        $titles_list .= '- ' . $op->post_title . ' (' . get_permalink( $op->ID ) . ")\n";
    }

    $prompt = ai_seo_t(
        "Du er en SEO-ekspert. Basert på innholdet nedenfor, foreslå hvilke av de eksisterende sidene på nettstedet det er mest relevant å lenke til internt. Forklar kort hvorfor og foreslå hvilken ankertekst som bør brukes. Svar på norsk.\n\nInnhold:\n",
        "You are an SEO expert. Based on the content below, suggest which of the existing pages on the site are most relevant for internal linking. Briefly explain why and suggest what anchor text should be used. Reply in English.\n\nContent:\n"
    ) . $content . ai_seo_t( "\n\nEksisterende sider:\n", "\n\nExisting pages:\n" ) . $titles_list;

    $client = new AI_SEO_Client();
    $result = $client->send_request( $prompt );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    wp_send_json_success( array( 'text' => wp_kses_post( $result ) ) );
}
add_action( 'wp_ajax_ai_seo_suggest_links', 'ai_seo_ajax_suggest_links' );

/**
 * AJAX handler: Refresh SEO score and checklist.
 */
function ai_seo_ajax_refresh_score() {
    check_ajax_referer( 'ai_seo_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( ai_seo_t( 'Ingen tilgang.', 'Access denied.' ) );
    }

    $post_id          = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    $meta_title       = isset( $_POST['seo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['seo_title'] ) ) : '';
    $meta_description = isset( $_POST['seo_description'] ) ? sanitize_text_field( wp_unslash( $_POST['seo_description'] ) ) : '';
    $focus_keyword    = isset( $_POST['seo_keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['seo_keyword'] ) ) : '';

    if ( ! $post_id ) {
        wp_send_json_error( ai_seo_t( 'Ugyldig innleggs-ID.', 'Invalid post ID.' ) );
    }

    $post = get_post( $post_id );
    if ( ! $post ) {
        wp_send_json_error( ai_seo_t( 'Innlegget ble ikke funnet.', 'Post not found.' ) );
    }

    // Use current editor content instead of saved post_content so unsaved changes are analyzed.
    $editor_content = isset( $_POST['post_content'] ) ? wp_kses_post( wp_unslash( $_POST['post_content'] ) ) : '';
    if ( ! empty( $editor_content ) ) {
        $post->post_content = $editor_content;
    }

    // SEO score with current (unsaved) field values.
    $seo_score = AI_SEO_Score::analyze( $post, $focus_keyword, $meta_title, $meta_description );

    wp_send_json_success( $seo_score );
}
add_action( 'wp_ajax_ai_seo_refresh_score', 'ai_seo_ajax_refresh_score' );

/**
 * AJAX handler: Readability highlight.
 */
function ai_seo_ajax_readability_highlight() {
    check_ajax_referer( 'ai_seo_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( ai_seo_t( 'Ingen tilgang.', 'Access denied.' ) );
    }

    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    if ( ! $post_id ) {
        wp_send_json_error( ai_seo_t( 'Ugyldig innleggs-ID.', 'Invalid post ID.' ) );
    }

    // Use current editor content if available, otherwise saved content.
    $content = isset( $_POST['post_content'] ) ? wp_kses_post( wp_unslash( $_POST['post_content'] ) ) : '';
    if ( empty( $content ) ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_send_json_error( ai_seo_t( 'Innlegget ble ikke funnet.', 'Post not found.' ) );
        }
        $content = $post->post_content;
    }

    $readability = new AI_SEO_Readability();
    $highlighted = $readability->highlight( $content );

    wp_send_json_success( array( 'html' => $highlighted ) );
}
add_action( 'wp_ajax_ai_seo_readability_highlight', 'ai_seo_ajax_readability_highlight' );

/**
 * AJAX handler: Run SEO migration.
 */
function ai_seo_ajax_run_migration() {
    check_ajax_referer( 'ai_seo_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( ai_seo_t( 'Ingen tilgang.', 'Access denied.' ) );
    }

    $source    = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '';
    $overwrite = ! empty( $_POST['overwrite'] );

    if ( ! in_array( $source, array( 'yoast', 'rankmath' ), true ) ) {
        wp_send_json_error( ai_seo_t( 'Ugyldig kilde.', 'Invalid source.' ) );
    }

    $result = AI_SEO_Migration::run( $source, $overwrite );

    if ( isset( $result['error'] ) ) {
        wp_send_json_error( $result['error'] );
    }

    wp_send_json_success( $result );
}
add_action( 'wp_ajax_ai_seo_run_migration', 'ai_seo_ajax_run_migration' );

/**
 * Flush rewrite rules and create DB tables on activation.
 */
function ai_seo_activate() {
    $sitemap = new AI_SEO_Sitemap();
    $sitemap->init();

    AI_SEO_Redirects::create_table();

    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'ai_seo_activate' );

/**
 * Flush rewrite rules on deactivation.
 */
function ai_seo_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ai_seo_deactivate' );
