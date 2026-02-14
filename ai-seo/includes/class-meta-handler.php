<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_SEO_Meta_Handler {

    public function init() {
        add_action( 'wp_head', array( $this, 'output_meta_tags' ), 1 );
        add_action( 'wp_head', array( $this, 'output_canonical' ), 2 );
        add_action( 'wp_head', array( $this, 'output_opengraph' ), 3 );
        add_action( 'wp_head', array( $this, 'output_twitter_card' ), 4 );
    }

    /**
     * Output meta title and description tags.
     */
    public function output_meta_tags() {
        if ( ! is_singular() ) {
            return;
        }

        $post_id         = get_the_ID();
        $meta_title      = get_post_meta( $post_id, '_ai_seo_meta_title', true );
        $meta_description = get_post_meta( $post_id, '_ai_seo_meta_description', true );

        if ( $meta_description ) {
            echo '<meta name="description" content="' . esc_attr( $meta_description ) . '" />' . "\n";
        }

        // Override document title if custom SEO title is set.
        if ( $meta_title ) {
            add_filter( 'pre_get_document_title', function () use ( $meta_title ) {
                return $meta_title;
            } );
        }
    }

    /**
     * Output canonical URL.
     */
    public function output_canonical() {
        if ( ! is_singular() ) {
            return;
        }

        // Remove WordPress default canonical to avoid duplicates.
        remove_action( 'wp_head', 'rel_canonical' );

        $canonical = wp_get_canonical_url();
        if ( $canonical ) {
            echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
        }
    }

    /**
     * Output OpenGraph meta tags.
     */
    public function output_opengraph() {
        $options = get_option( 'ai_seo_options', array() );
        if ( isset( $options['enable_opengraph'] ) && ! $options['enable_opengraph'] ) {
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

        if ( has_post_thumbnail( $post_id ) ) {
            $image = wp_get_attachment_image_url( get_post_thumbnail_id( $post_id ), 'large' );
            if ( $image ) {
                echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
            }
        }

        if ( 'article' === $og_type ) {
            echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c', $post_id ) ) . '" />' . "\n";
            echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c', $post_id ) ) . '" />' . "\n";
        }
    }

    /**
     * Output Twitter Card meta tags.
     */
    public function output_twitter_card() {
        $options = get_option( 'ai_seo_options', array() );
        if ( isset( $options['enable_opengraph'] ) && ! $options['enable_opengraph'] ) {
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

        $card_type = has_post_thumbnail( $post_id ) ? 'summary_large_image' : 'summary';

        echo '<meta name="twitter:card" content="' . esc_attr( $card_type ) . '" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '" />' . "\n";

        if ( has_post_thumbnail( $post_id ) ) {
            $image = wp_get_attachment_image_url( get_post_thumbnail_id( $post_id ), 'large' );
            if ( $image ) {
                echo '<meta name="twitter:image" content="' . esc_url( $image ) . '" />' . "\n";
            }
        }
    }
}
