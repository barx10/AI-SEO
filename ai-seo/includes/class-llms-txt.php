<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * llms.txt generator.
 *
 * Serves /llms.txt dynamically via rewrite rules (same approach as the XML
 * sitemap) so no file needs to be written to the site root.  The content is
 * generated automatically from the site title, description and published
 * pages/posts, but can be overridden manually via the admin page (stored in
 * the `llms_txt_content` option key).
 */
class AI_SEO_LLMS_Txt {

    const MAX_ITEMS = 200;

    public function init() {
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'render' ) );
    }

    public function add_rewrite_rules() {
        add_rewrite_rule( '^llms\.txt$', 'index.php?ai_seo_llms=1', 'top' );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'ai_seo_llms';
        return $vars;
    }

    public function render() {
        if ( ! get_query_var( 'ai_seo_llms' ) ) {
            return;
        }

        header( 'Content-Type: text/plain; charset=UTF-8' );

        $options = get_option( 'ai_seo_options', array() );
        $manual  = isset( $options['llms_txt_content'] ) ? trim( $options['llms_txt_content'] ) : '';

        echo $manual !== '' ? $manual : self::generate_content();
        exit;
    }

    /**
     * Build the llms.txt content from site metadata and published content.
     *
     * @return string
     */
    public static function generate_content() {
        $name = get_bloginfo( 'name' );
        $desc = get_bloginfo( 'description' );

        $lines   = array();
        $lines[] = '# ' . $name;
        $lines[] = '';
        if ( ! empty( $desc ) ) {
            $lines[] = '> ' . $desc;
            $lines[] = '';
        }
        $lines[] = 'URL: ' . home_url( '/' );
        $lines[] = '';

        $sections = array(
            'page' => 'Pages',
            'post' => 'Posts',
        );

        foreach ( $sections as $post_type => $heading ) {
            $items = get_posts( array(
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => self::MAX_ITEMS,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            ) );

            if ( empty( $items ) ) {
                continue;
            }

            $rows = array();
            foreach ( $items as $item ) {
                // Respect noindex: skip content excluded from search.
                $robots = get_post_meta( $item->ID, '_ai_seo_robots_meta', true );
                if ( is_array( $robots ) && in_array( 'noindex', $robots, true ) ) {
                    continue;
                }

                $excerpt = self::get_excerpt( $item );
                $row     = '- [' . $item->post_title . '](' . get_permalink( $item->ID ) . ')';
                if ( $excerpt !== '' ) {
                    $row .= ': ' . $excerpt;
                }
                $rows[] = $row;
            }

            if ( empty( $rows ) ) {
                continue;
            }

            $lines[] = '## ' . $heading;
            $lines[] = '';
            $lines   = array_merge( $lines, $rows );
            $lines[] = '';
        }

        return rtrim( implode( "\n", $lines ) ) . "\n";
    }

    /**
     * Short single-line excerpt for an item.
     */
    private static function get_excerpt( $post ) {
        $raw = has_excerpt( $post->ID ) ? $post->post_excerpt : $post->post_content;

        // Clean up: remove shortcodes, HTML, entities, bare URLs and embeds so
        // the excerpt reads as a plain, human-friendly summary line.
        $text = strip_shortcodes( $raw );
        $text = wp_strip_all_tags( $text );
        $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
        $text = preg_replace( '#https?://\S+#', '', $text ); // Drop bare URLs (e.g. Spotify links).
        $text = preg_replace( '/\s+/', ' ', $text );
        $text = trim( $text );

        if ( '' === $text ) {
            return '';
        }

        // Cap length with a proper ellipsis so lines never end mid-word.
        return wp_trim_words( $text, 30, '…' );
    }
}
