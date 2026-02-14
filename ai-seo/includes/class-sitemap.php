<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_SEO_Sitemap {

    public function init() {
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'render_sitemap' ) );
    }

    /**
     * Register rewrite rule for /sitemap.xml.
     */
    public function add_rewrite_rules() {
        add_rewrite_rule( 'sitemap\.xml$', 'index.php?ai_seo_sitemap=1', 'top' );
    }

    /**
     * Register custom query variable.
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'ai_seo_sitemap';
        return $vars;
    }

    /**
     * Render the XML sitemap when requested.
     */
    public function render_sitemap() {
        if ( ! get_query_var( 'ai_seo_sitemap' ) ) {
            return;
        }

        header( 'Content-Type: application/xml; charset=UTF-8' );
        header( 'X-Robots-Tag: noindex' );

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Home page.
        echo $this->build_url_entry( home_url( '/' ), current_time( 'c' ), 'daily', '1.0' );

        // Posts.
        $posts = get_posts( array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 1000,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ) );

        foreach ( $posts as $post ) {
            echo $this->build_url_entry(
                get_permalink( $post ),
                get_the_modified_date( 'c', $post ),
                'weekly',
                '0.8'
            );
        }

        // Pages.
        $pages = get_posts( array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 500,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ) );

        foreach ( $pages as $page ) {
            // Skip the front page if it's a static page (already included above).
            if ( 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $page->ID ) {
                continue;
            }

            echo $this->build_url_entry(
                get_permalink( $page ),
                get_the_modified_date( 'c', $page ),
                'monthly',
                '0.6'
            );
        }

        // Categories.
        $categories = get_categories( array(
            'hide_empty' => true,
        ) );

        foreach ( $categories as $category ) {
            echo $this->build_url_entry(
                get_category_link( $category->term_id ),
                '',
                'weekly',
                '0.5'
            );
        }

        echo '</urlset>' . "\n";
        exit;
    }

    /**
     * Build a single <url> entry for the sitemap.
     */
    private function build_url_entry( $loc, $lastmod = '', $changefreq = 'weekly', $priority = '0.5' ) {
        $xml  = "  <url>\n";
        $xml .= "    <loc>" . esc_url( $loc ) . "</loc>\n";
        if ( $lastmod ) {
            $xml .= "    <lastmod>" . esc_html( $lastmod ) . "</lastmod>\n";
        }
        $xml .= "    <changefreq>" . esc_html( $changefreq ) . "</changefreq>\n";
        $xml .= "    <priority>" . esc_html( $priority ) . "</priority>\n";
        $xml .= "  </url>\n";
        return $xml;
    }
}
