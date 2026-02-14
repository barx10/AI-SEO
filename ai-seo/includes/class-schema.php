<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_SEO_Schema {

    public function init() {
        add_action( 'wp_head', array( $this, 'output_schema' ), 10 );
    }

    /**
     * Output Schema.org Article JSON-LD on single posts.
     */
    public function output_schema() {
        if ( ! is_singular( 'post' ) ) {
            return;
        }

        $post_id = get_the_ID();
        $post    = get_post( $post_id );

        if ( ! $post ) {
            return;
        }

        $meta_title       = get_post_meta( $post_id, '_ai_seo_meta_title', true );
        $meta_description = get_post_meta( $post_id, '_ai_seo_meta_description', true );

        $title   = $meta_title ? $meta_title : get_the_title( $post_id );
        $desc    = $meta_description ? $meta_description : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '...' );
        $url     = get_permalink( $post_id );
        $author  = get_the_author_meta( 'display_name', $post->post_author );

        $schema = array(
            '@context'         => 'https://schema.org',
            '@type'            => 'Article',
            'headline'         => $title,
            'description'      => $desc,
            'url'              => $url,
            'datePublished'    => get_the_date( 'c', $post_id ),
            'dateModified'     => get_the_modified_date( 'c', $post_id ),
            'author'           => array(
                '@type' => 'Person',
                'name'  => $author,
            ),
            'publisher'        => array(
                '@type' => 'Organization',
                'name'  => get_bloginfo( 'name' ),
            ),
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id'   => $url,
            ),
        );

        // Add featured image if available.
        if ( has_post_thumbnail( $post_id ) ) {
            $image_id   = get_post_thumbnail_id( $post_id );
            $image_data = wp_get_attachment_image_src( $image_id, 'full' );

            if ( $image_data ) {
                $schema['image'] = array(
                    '@type'  => 'ImageObject',
                    'url'    => $image_data[0],
                    'width'  => $image_data[1],
                    'height' => $image_data[2],
                );
            }
        }

        // Add publisher logo if site icon exists.
        $site_icon_id = get_option( 'site_icon' );
        if ( $site_icon_id ) {
            $icon_data = wp_get_attachment_image_src( $site_icon_id, 'full' );
            if ( $icon_data ) {
                $schema['publisher']['logo'] = array(
                    '@type'  => 'ImageObject',
                    'url'    => $icon_data[0],
                    'width'  => $icon_data[1],
                    'height' => $icon_data[2],
                );
            }
        }

        // Add word count.
        $word_count = str_word_count( wp_strip_all_tags( $post->post_content ) );
        $schema['wordCount'] = $word_count;

        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        echo "\n" . '</script>' . "\n";
    }
}
