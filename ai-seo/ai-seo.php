<?php
/**
 * Plugin Name: AI SEO
 * Plugin URI:  https://example.com/ai-seo
 * Description: AI-drevet SEO-programtillegg for WordPress med støtte for metatagger, sitemap, schema-markering og lesbarhetsanalyse.
 * Version:     1.0.0
 * Author:      AI SEO
 * License:     GPL-2.0-or-later
 * Text Domain: ai-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'AI_SEO_VERSION', '1.0.0' );
define( 'AI_SEO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_SEO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load plugin classes.
 */
require_once AI_SEO_PLUGIN_DIR . 'includes/class-ai-client.php';
require_once AI_SEO_PLUGIN_DIR . 'includes/class-meta-handler.php';
require_once AI_SEO_PLUGIN_DIR . 'includes/class-sitemap.php';
require_once AI_SEO_PLUGIN_DIR . 'includes/class-schema.php';
require_once AI_SEO_PLUGIN_DIR . 'includes/class-readability.php';
require_once AI_SEO_PLUGIN_DIR . 'admin/settings-page.php';
require_once AI_SEO_PLUGIN_DIR . 'admin/meta-box.php';

/**
 * Initialize plugin components.
 */
function ai_seo_init() {
    $options = get_option( 'ai_seo_options', array() );

    // Always initialize meta handler (titles, descriptions, OG, canonical).
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

    // Settings page.
    $settings = new AI_SEO_Settings_Page();
    $settings->init();

    // Meta box.
    $meta_box = new AI_SEO_Meta_Box();
    $meta_box->init();
}
add_action( 'plugins_loaded', 'ai_seo_init' );

/**
 * Enqueue admin assets.
 */
function ai_seo_enqueue_admin_assets( $hook ) {
    $screen = get_current_screen();

    $is_editor  = in_array( $hook, array( 'post.php', 'post-new.php' ), true );
    $is_settings = ( $screen && $screen->id === 'settings_page_ai-seo' );

    if ( ! $is_editor && ! $is_settings ) {
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
        wp_send_json_error( 'Ingen tilgang.' );
    }

    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    if ( ! $post_id ) {
        wp_send_json_error( 'Ugyldig innleggs-ID.' );
    }

    $post = get_post( $post_id );
    if ( ! $post ) {
        wp_send_json_error( 'Innlegget ble ikke funnet.' );
    }

    $content = wp_strip_all_tags( $post->post_content );
    $content = mb_substr( $content, 0, 3000 );

    $prompt = "Du er en SEO-ekspert. Skriv en kort og engasjerende metabeskrivelse (maks 160 tegn) for følgende innhold. Svar kun med metabeskrivelsen, uten anførselstegn eller ekstra tekst.\n\nInnhold:\n" . $content;

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
        wp_send_json_error( 'Ingen tilgang.' );
    }

    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    $keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';

    if ( ! $post_id ) {
        wp_send_json_error( 'Ugyldig innleggs-ID.' );
    }

    $post = get_post( $post_id );
    if ( ! $post ) {
        wp_send_json_error( 'Innlegget ble ikke funnet.' );
    }

    $content = wp_strip_all_tags( $post->post_content );
    $content = mb_substr( $content, 0, 3000 );

    $prompt = "Du er en SEO-ekspert. Foreslå 3 SEO-optimaliserte titler for følgende innhold.";
    if ( $keyword ) {
        $prompt .= " Søkeordet som skal inkluderes er: «{$keyword}».";
    }
    $prompt .= " Svar med kun de 3 titlene, én per linje, nummerert 1-3. Ingen ekstra tekst.\n\nInnhold:\n" . $content;

    $client = new AI_SEO_Client();
    $result = $client->send_request( $prompt );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    wp_send_json_success( array( 'text' => sanitize_text_field( $result ) ) );
}
add_action( 'wp_ajax_ai_seo_suggest_title', 'ai_seo_ajax_suggest_title' );

/**
 * AJAX handler: Analyze keywords.
 */
function ai_seo_ajax_analyze_keywords() {
    check_ajax_referer( 'ai_seo_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Ingen tilgang.' );
    }

    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

    if ( ! $post_id ) {
        wp_send_json_error( 'Ugyldig innleggs-ID.' );
    }

    $post = get_post( $post_id );
    if ( ! $post ) {
        wp_send_json_error( 'Innlegget ble ikke funnet.' );
    }

    $content = wp_strip_all_tags( $post->post_content );
    $content = mb_substr( $content, 0, 3000 );

    $prompt = "Du er en SEO-ekspert. Analyser søkeordtettheten i følgende tekst. List opp de 10 mest brukte ordene (ekskluder stoppord), vis prosentandel for hvert, og gi 3 konkrete forslag for å forbedre SEO-en. Svar på norsk.\n\nInnhold:\n" . $content;

    $client = new AI_SEO_Client();
    $result = $client->send_request( $prompt );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    wp_send_json_success( array( 'text' => wp_kses_post( $result ) ) );
}
add_action( 'wp_ajax_ai_seo_analyze_keywords', 'ai_seo_ajax_analyze_keywords' );

/**
 * Flush rewrite rules on activation (for sitemap).
 */
function ai_seo_activate() {
    $sitemap = new AI_SEO_Sitemap();
    $sitemap->init();
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
