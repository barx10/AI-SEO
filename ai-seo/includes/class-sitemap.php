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
        $is_post_type = in_array( $type, array( 'posts', 'pages' ), true ) || in_array( $type, $this->get_public_cpt(), true );

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        if ( $is_post_type ) {
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
        } else {
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        }

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

            $images = $this->get_post_images( $post );

            echo $this->build_url_entry(
                get_permalink( $post ),
                get_the_modified_date( 'c', $post ),
                $changefreq,
                $priority,
                $images
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

    private function build_url_entry( $loc, $lastmod = '', $changefreq = 'weekly', $priority = '0.5', $images = array() ) {
        $xml  = "  <url>\n";
        $xml .= "    <loc>" . esc_url( $loc ) . "</loc>\n";
        if ( $lastmod ) {
            $xml .= "    <lastmod>" . esc_html( $lastmod ) . "</lastmod>\n";
        }
        $xml .= "    <changefreq>" . esc_html( $changefreq ) . "</changefreq>\n";
        $xml .= "    <priority>" . esc_html( $priority ) . "</priority>\n";

        foreach ( $images as $image ) {
            $xml .= "    <image:image>\n";
            $xml .= "      <image:loc>" . esc_url( $image['url'] ) . "</image:loc>\n";
            if ( ! empty( $image['title'] ) ) {
                $xml .= "      <image:title>" . esc_html( $image['title'] ) . "</image:title>\n";
            }
            if ( ! empty( $image['alt'] ) ) {
                $xml .= "      <image:caption>" . esc_html( $image['alt'] ) . "</image:caption>\n";
            }
            $xml .= "    </image:image>\n";
        }

        $xml .= "  </url>\n";
        return $xml;
    }

    /**
     * Extract images from a post (featured image + content images).
     * Google allows up to 1000 images per page in the sitemap.
     *
     * @param WP_Post $post Post object.
     * @return array Array of image data with 'url', 'title', 'alt'.
     */
    private function get_post_images( $post ) {
        $images = array();
        $seen   = array();

        // Featured image.
        if ( has_post_thumbnail( $post->ID ) ) {
            $thumb_id  = get_post_thumbnail_id( $post->ID );
            $thumb_url = wp_get_attachment_url( $thumb_id );
            if ( $thumb_url ) {
                $thumb_post = get_post( $thumb_id );
                $images[]   = array(
                    'url'   => $thumb_url,
                    'title' => $thumb_post ? $thumb_post->post_title : '',
                    'alt'   => get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ),
                );
                $seen[ $thumb_url ] = true;
            }
        }

        // Content images.
        if ( ! empty( $post->post_content ) && preg_match_all( '/<img\s[^>]*src=["\']([^"\']+)["\'][^>]*>/i', $post->post_content, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                $url = $match[1];

                // Skip data URIs and external tracking pixels.
                if ( strpos( $url, 'data:' ) === 0 ) {
                    continue;
                }

                // Make relative URLs absolute.
                if ( strpos( $url, '//' ) === false ) {
                    $url = home_url( $url );
                }

                if ( isset( $seen[ $url ] ) ) {
                    continue;
                }
                $seen[ $url ] = true;

                $alt   = '';
                $title = '';
                if ( preg_match( '/alt=["\']([^"\']*)["\']/', $match[0], $alt_match ) ) {
                    $alt = $alt_match[1];
                }
                if ( preg_match( '/title=["\']([^"\']*)["\']/', $match[0], $title_match ) ) {
                    $title = $title_match[1];
                }

                $images[] = array(
                    'url'   => $url,
                    'title' => $title,
                    'alt'   => $alt,
                );
            }
        }

        return $images;
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
