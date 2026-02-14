<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_SEO_Sitemap {

    const CACHE_KEY        = 'ai_seo_sitemap_cache';
    const CACHE_EXPIRY     = 3600;
    const URLS_PER_SITEMAP = 1000;

    public function init() {
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'render_sitemap' ) );

        // Invalidate cache on content changes.
        add_action( 'save_post', array( $this, 'invalidate_cache' ) );
        add_action( 'delete_post', array( $this, 'invalidate_cache' ) );
        add_action( 'created_term', array( $this, 'invalidate_cache' ) );
        add_action( 'edited_term', array( $this, 'invalidate_cache' ) );
        add_action( 'delete_term', array( $this, 'invalidate_cache' ) );

        // Ping search engines on publish.
        add_action( 'publish_post', array( __CLASS__, 'ping_search_engines' ) );
        add_action( 'publish_page', array( __CLASS__, 'ping_search_engines' ) );
    }

    public function add_rewrite_rules() {
        add_rewrite_rule( 'sitemap\.xml$', 'index.php?ai_seo_sitemap=index', 'top' );
        add_rewrite_rule( 'sitemap-([a-z0-9_]+)-?(\d*)\.xml$', 'index.php?ai_seo_sitemap=$matches[1]&ai_seo_sitemap_page=$matches[2]', 'top' );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'ai_seo_sitemap';
        $vars[] = 'ai_seo_sitemap_page';
        return $vars;
    }

    public function render_sitemap() {
        $sitemap_type = get_query_var( 'ai_seo_sitemap' );
        if ( ! $sitemap_type ) {
            return;
        }

        header( 'Content-Type: application/xml; charset=UTF-8' );
        header( 'X-Robots-Tag: noindex' );

        $page = max( 1, (int) get_query_var( 'ai_seo_sitemap_page', 1 ) );

        $cache_key = self::CACHE_KEY . '_' . $sitemap_type . '_' . $page;
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) {
            echo $cached;
            exit;
        }

        ob_start();

        if ( 'index' === $sitemap_type ) {
            $this->render_sitemap_index();
        } else {
            $this->render_sub_sitemap( $sitemap_type, $page );
        }

        $output = ob_get_clean();
        set_transient( $cache_key, $output, self::CACHE_EXPIRY );

        echo $output;
        exit;
    }

    private function render_sitemap_index() {
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Posts.
        $post_count = $this->count_items( 'post' );
        $post_pages = max( 1, ceil( $post_count / self::URLS_PER_SITEMAP ) );
        for ( $i = 1; $i <= $post_pages; $i++ ) {
            echo $this->build_sitemap_entry( home_url( '/sitemap-posts-' . $i . '.xml' ) );
        }

        // Pages.
        $page_count = $this->count_items( 'page' );
        if ( $page_count > 0 ) {
            $pages = max( 1, ceil( $page_count / self::URLS_PER_SITEMAP ) );
            for ( $i = 1; $i <= $pages; $i++ ) {
                echo $this->build_sitemap_entry( home_url( '/sitemap-pages-' . $i . '.xml' ) );
            }
        }

        // Custom post types.
        foreach ( $this->get_public_cpt() as $cpt ) {
            $cpt_count = $this->count_items( $cpt );
            if ( $cpt_count > 0 ) {
                $cpt_pages = max( 1, ceil( $cpt_count / self::URLS_PER_SITEMAP ) );
                for ( $i = 1; $i <= $cpt_pages; $i++ ) {
                    echo $this->build_sitemap_entry( home_url( '/sitemap-' . $cpt . '-' . $i . '.xml' ) );
                }
            }
        }

        // Categories.
        $cat_count = wp_count_terms( array( 'taxonomy' => 'category', 'hide_empty' => true ) );
        if ( $cat_count > 0 ) {
            echo $this->build_sitemap_entry( home_url( '/sitemap-categories.xml' ) );
        }

        // Tags.
        $tag_count = wp_count_terms( array( 'taxonomy' => 'post_tag', 'hide_empty' => true ) );
        if ( $tag_count > 0 ) {
            echo $this->build_sitemap_entry( home_url( '/sitemap-tags.xml' ) );
        }

        echo '</sitemapindex>' . "\n";
    }

    private function render_sub_sitemap( $type, $page ) {
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        switch ( $type ) {
            case 'posts':
                $this->render_post_type_entries( 'post', $page, 'weekly', '0.8' );
                break;
            case 'pages':
                $this->render_post_type_entries( 'page', $page, 'monthly', '0.6' );
                break;
            case 'categories':
                $this->render_taxonomy_entries( 'category' );
                break;
            case 'tags':
                $this->render_taxonomy_entries( 'post_tag' );
                break;
            default:
                $cpt_types = $this->get_public_cpt();
                if ( in_array( $type, $cpt_types, true ) ) {
                    $this->render_post_type_entries( $type, $page, 'weekly', '0.7' );
                }
                break;
        }

        echo '</urlset>' . "\n";
    }

    private function render_post_type_entries( $post_type, $page, $changefreq, $priority ) {
        if ( 'post' === $post_type && $page === 1 ) {
            echo $this->build_url_entry( home_url( '/' ), current_time( 'c' ), 'daily', '1.0' );
        }

        $offset = ( $page - 1 ) * self::URLS_PER_SITEMAP;

        $posts = get_posts( array(
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => self::URLS_PER_SITEMAP,
            'offset'         => $offset,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ) );

        foreach ( $posts as $post ) {
            $robots = get_post_meta( $post->ID, '_ai_seo_robots_meta', true );
            if ( ! empty( $robots ) && in_array( 'noindex', (array) $robots, true ) ) {
                continue;
            }

            if ( 'page' === $post_type
                && 'page' === get_option( 'show_on_front' )
                && (int) get_option( 'page_on_front' ) === $post->ID ) {
                continue;
            }

            echo $this->build_url_entry(
                get_permalink( $post ),
                get_the_modified_date( 'c', $post ),
                $changefreq,
                $priority
            );
        }
    }

    private function render_taxonomy_entries( $taxonomy ) {
        $terms = get_terms( array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'number'     => self::URLS_PER_SITEMAP,
        ) );

        if ( is_wp_error( $terms ) ) {
            return;
        }

        foreach ( $terms as $term ) {
            echo $this->build_url_entry(
                get_term_link( $term ),
                '',
                'weekly',
                '0.5'
            );
        }
    }

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

    private function build_sitemap_entry( $loc ) {
        $xml  = "  <sitemap>\n";
        $xml .= "    <loc>" . esc_url( $loc ) . "</loc>\n";
        $xml .= "    <lastmod>" . esc_html( current_time( 'c' ) ) . "</lastmod>\n";
        $xml .= "  </sitemap>\n";
        return $xml;
    }

    private function count_items( $post_type ) {
        $counts = wp_count_posts( $post_type );
        return isset( $counts->publish ) ? (int) $counts->publish : 0;
    }

    private function get_public_cpt() {
        $types = get_post_types( array(
            'public'   => true,
            '_builtin' => false,
        ), 'names' );
        return array_values( $types );
    }

    public function invalidate_cache() {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_" . self::CACHE_KEY . "%' OR option_name LIKE '_transient_timeout_" . self::CACHE_KEY . "%'"
        );
    }

    public static function ping_search_engines() {
        $sitemap_url = home_url( '/sitemap.xml' );

        wp_remote_get( 'https://www.google.com/ping?sitemap=' . urlencode( $sitemap_url ), array(
            'timeout'  => 5,
            'blocking' => false,
        ) );

        wp_remote_get( 'https://www.bing.com/ping?sitemap=' . urlencode( $sitemap_url ), array(
            'timeout'  => 5,
            'blocking' => false,
        ) );
    }
}
