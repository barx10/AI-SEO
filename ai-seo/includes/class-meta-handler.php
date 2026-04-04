<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_SEO_Meta_Handler {

    public function init() {
        add_action( 'wp_head', array( $this, 'output_meta_tags' ), 1 );
        add_action( 'wp_head', array( $this, 'output_canonical' ), 2 );
        add_action( 'wp_head', array( $this, 'output_robots_meta' ), 2 );
        add_action( 'wp_head', array( $this, 'output_opengraph' ), 3 );
        add_action( 'wp_head', array( $this, 'output_twitter_card' ), 4 );

        // Noindex feeds to prevent thin content indexing.
        add_action( 'template_redirect', array( $this, 'noindex_feeds' ) );
    }

    public function noindex_feeds() {
        if ( is_feed() ) {
            header( 'X-Robots-Tag: noindex, follow', true );
        }
    }

    public function output_meta_tags() {
        // Front page with "latest posts" is not is_singular().
        if ( is_front_page() && ! is_singular() ) {
            $options  = get_option( 'ai_seo_options', array() );
            $meta_desc = ! empty( $options['homepage_meta_description'] )
                ? $options['homepage_meta_description']
                : ( ! empty( $options['homepage_og_description'] ) ? $options['homepage_og_description'] : '' );

            if ( $meta_desc ) {
                echo '<meta name="description" content="' . esc_attr( $meta_desc ) . '" />' . "\n";
            }

            $meta_title = ! empty( $options['homepage_og_title'] ) ? $options['homepage_og_title'] : '';
            if ( $meta_title ) {
                add_filter( 'pre_get_document_title', function () use ( $meta_title ) {
                    return $meta_title;
                } );
            }
            return;
        }

        if ( is_category() ) {
            $term = get_queried_object();
            $desc = term_description( $term->term_id, 'category' );
            if ( $desc ) {
                echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $desc ) ) . '" />' . "\n";
            }
            return;
        }

        if ( ! is_singular() ) {
            return;
        }

        $post_id          = get_the_ID();
        $meta_title       = get_post_meta( $post_id, '_ai_seo_meta_title', true );
        $meta_description = get_post_meta( $post_id, '_ai_seo_meta_description', true );

        if ( $meta_description ) {
            echo '<meta name="description" content="' . esc_attr( $meta_description ) . '" />' . "\n";
        }

        if ( $meta_title ) {
            add_filter( 'pre_get_document_title', function () use ( $meta_title ) {
                return $meta_title;
            } );
        }
    }

    public function output_canonical() {
        remove_action( 'wp_head', 'rel_canonical' );

        if ( is_front_page() ) {
            echo '<link rel="canonical" href="' . esc_url( home_url( '/' ) ) . '" />' . "\n";
            return;
        }

        if ( ! is_singular() ) {
            return;
        }

        $canonical = wp_get_canonical_url();
        if ( $canonical ) {
            echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
        }
    }

    /**
     * Output robots meta tag per post.
     */
    public function output_robots_meta() {
        if ( ! is_singular() ) {
            return;
        }

        $post_id = get_the_ID();
        $robots  = get_post_meta( $post_id, '_ai_seo_robots_meta', true );

        if ( empty( $robots ) || ! is_array( $robots ) ) {
            return;
        }

        $directives = array_map( 'sanitize_text_field', $robots );
        if ( ! empty( $directives ) ) {
            echo '<meta name="robots" content="' . esc_attr( implode( ', ', $directives ) ) . '" />' . "\n";
        }
    }

    public function output_opengraph() {
        $options = get_option( 'ai_seo_options', array() );
        if ( isset( $options['enable_opengraph'] ) && ! $options['enable_opengraph'] ) {
            return;
        }

        if ( is_front_page() ) {
            $og_title = ! empty( $options['homepage_og_title'] ) ? $options['homepage_og_title'] : get_bloginfo( 'name' );
            $og_desc  = ! empty( $options['homepage_og_description'] ) ? $options['homepage_og_description'] : get_bloginfo( 'description' );

            echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
            echo '<meta property="og:description" content="' . esc_attr( $og_desc ) . '" />' . "\n";
            echo '<meta property="og:url" content="' . esc_url( home_url( '/' ) ) . '" />' . "\n";
            echo '<meta property="og:type" content="website" />' . "\n";
            echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
            echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '" />' . "\n";

            $image_id = ! empty( $options['homepage_og_image_id'] ) ? (int) $options['homepage_og_image_id'] : 0;
            if ( $image_id ) {
                $image_data = wp_get_attachment_image_src( $image_id, 'large' );
                if ( $image_data ) {
                    echo '<meta property="og:image" content="' . esc_url( $image_data[0] ) . '" />' . "\n";
                    echo '<meta property="og:image:width" content="' . esc_attr( $image_data[1] ) . '" />' . "\n";
                    echo '<meta property="og:image:height" content="' . esc_attr( $image_data[2] ) . '" />' . "\n";
                }
            }
            return;
        }

        if ( ! is_singular() ) {
            return;
        }

        $post_id          = get_the_ID();
        $meta_title       = get_post_meta( $post_id, '_ai_seo_meta_title', true );
        $meta_description = get_post_meta( $post_id, '_ai_seo_meta_description', true );

        $og_title = $meta_title ? $meta_title : get_the_title( $post_id );
        $og_desc  = $meta_description ? $meta_description : wp_trim_words( get_the_excerpt( $post_id ), 30, '...' );
        $og_url   = get_permalink( $post_id );
        $og_type  = is_single() ? 'article' : 'website';

        echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr( $og_desc ) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url( $og_url ) . '" />' . "\n";
        echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '" />' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
        echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '" />' . "\n";

        // Custom social image or featured image.
        $social_image_id = get_post_meta( $post_id, '_ai_seo_social_image_id', true );

        if ( $social_image_id ) {
            $social_data = wp_get_attachment_image_src( $social_image_id, 'large' );
            if ( $social_data ) {
                echo '<meta property="og:image" content="' . esc_url( $social_data[0] ) . '" />' . "\n";
                echo '<meta property="og:image:width" content="' . esc_attr( $social_data[1] ) . '" />' . "\n";
                echo '<meta property="og:image:height" content="' . esc_attr( $social_data[2] ) . '" />' . "\n";
            }
        } elseif ( has_post_thumbnail( $post_id ) ) {
            $thumb_data = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'large' );
            if ( $thumb_data ) {
                echo '<meta property="og:image" content="' . esc_url( $thumb_data[0] ) . '" />' . "\n";
                echo '<meta property="og:image:width" content="' . esc_attr( $thumb_data[1] ) . '" />' . "\n";
                echo '<meta property="og:image:height" content="' . esc_attr( $thumb_data[2] ) . '" />' . "\n";
            }
        } elseif ( ! empty( $options['homepage_og_image_id'] ) ) {
            $fallback_data = wp_get_attachment_image_src( (int) $options['homepage_og_image_id'], 'large' );
            if ( $fallback_data ) {
                echo '<meta property="og:image" content="' . esc_url( $fallback_data[0] ) . '" />' . "\n";
                echo '<meta property="og:image:width" content="' . esc_attr( $fallback_data[1] ) . '" />' . "\n";
                echo '<meta property="og:image:height" content="' . esc_attr( $fallback_data[2] ) . '" />' . "\n";
            }
        }

        if ( 'article' === $og_type ) {
            echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c', $post_id ) ) . '" />' . "\n";
            echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c', $post_id ) ) . '" />' . "\n";

            $tags = get_the_tags( $post_id );
            if ( $tags ) {
                foreach ( $tags as $tag ) {
                    echo '<meta property="article:tag" content="' . esc_attr( $tag->name ) . '" />' . "\n";
                }
            }

            $categories = get_the_category( $post_id );
            if ( ! empty( $categories ) ) {
                echo '<meta property="article:section" content="' . esc_attr( $categories[0]->name ) . '" />' . "\n";
            }
        }
    }

    public function output_twitter_card() {
        $options = get_option( 'ai_seo_options', array() );
        if ( isset( $options['enable_opengraph'] ) && ! $options['enable_opengraph'] ) {
            return;
        }

        if ( is_front_page() ) {
            $og_title = ! empty( $options['homepage_og_title'] ) ? $options['homepage_og_title'] : get_bloginfo( 'name' );
            $og_desc  = ! empty( $options['homepage_og_description'] ) ? $options['homepage_og_description'] : get_bloginfo( 'description' );
            $image_id = ! empty( $options['homepage_og_image_id'] ) ? (int) $options['homepage_og_image_id'] : 0;

            $card_type = $image_id ? 'summary_large_image' : 'summary';
            echo '<meta name="twitter:card" content="' . esc_attr( $card_type ) . '" />' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
            echo '<meta name="twitter:description" content="' . esc_attr( $og_desc ) . '" />' . "\n";

            $twitter_handle = isset( $options['twitter_handle'] ) ? $options['twitter_handle'] : '';
            if ( ! empty( $twitter_handle ) ) {
                echo '<meta name="twitter:site" content="' . esc_attr( $twitter_handle ) . '" />' . "\n";
            }

            if ( $image_id ) {
                $image_url = wp_get_attachment_image_url( $image_id, 'large' );
                if ( $image_url ) {
                    echo '<meta name="twitter:image" content="' . esc_url( $image_url ) . '" />' . "\n";
                }
            }
            return;
        }

        if ( ! is_singular() ) {
            return;
        }

        $post_id          = get_the_ID();
        $meta_title       = get_post_meta( $post_id, '_ai_seo_meta_title', true );
        $meta_description = get_post_meta( $post_id, '_ai_seo_meta_description', true );

        $title = $meta_title ? $meta_title : get_the_title( $post_id );
        $desc  = $meta_description ? $meta_description : wp_trim_words( get_the_excerpt( $post_id ), 30, '...' );

        $social_image_id = get_post_meta( $post_id, '_ai_seo_social_image_id', true );
        $has_image       = false;
        $image_id        = 0;

        if ( $social_image_id ) {
            $has_image = true;
            $image_id  = $social_image_id;
        } elseif ( has_post_thumbnail( $post_id ) ) {
            $has_image = true;
            $image_id  = get_post_thumbnail_id( $post_id );
        } elseif ( ! empty( $options['homepage_og_image_id'] ) ) {
            $has_image = true;
            $image_id  = (int) $options['homepage_og_image_id'];
        }

        $card_type = $has_image ? 'summary_large_image' : 'summary';

        echo '<meta name="twitter:card" content="' . esc_attr( $card_type ) . '" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '" />' . "\n";

        $twitter_handle = isset( $options['twitter_handle'] ) ? $options['twitter_handle'] : '';
        if ( ! empty( $twitter_handle ) ) {
            echo '<meta name="twitter:site" content="' . esc_attr( $twitter_handle ) . '" />' . "\n";
            echo '<meta name="twitter:creator" content="' . esc_attr( $twitter_handle ) . '" />' . "\n";
        }

        if ( $has_image && $image_id ) {
            $image = wp_get_attachment_image_url( $image_id, 'large' );
            if ( $image ) {
                echo '<meta name="twitter:image" content="' . esc_url( $image ) . '" />' . "\n";
            }
        }
    }
}
