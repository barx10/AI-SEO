<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_SEO_Schema {

    public function init() {
        add_action( 'wp_head', array( $this, 'output_schema' ), 10 );
        add_action( 'wp_head', array( $this, 'output_organization_schema' ), 11 );
    }

    /**
     * Output Schema.org JSON-LD on single posts/pages.
     */
    public function output_schema() {
        if ( ! is_singular() ) {
            return;
        }

        $post_id = get_the_ID();
        $post    = get_post( $post_id );

        if ( ! $post ) {
            return;
        }

        $schema_type = get_post_meta( $post_id, '_ai_seo_schema_type', true );

        if ( 'faq' === $schema_type ) {
            $this->output_faq_schema( $post );
            return;
        }

        if ( 'howto' === $schema_type ) {
            $this->output_howto_schema( $post );
            return;
        }

        if ( 'post' === $post->post_type ) {
            $this->output_article_schema( $post );
        }
    }

    private function output_article_schema( $post ) {
        $post_id          = $post->ID;
        $meta_title       = get_post_meta( $post_id, '_ai_seo_meta_title', true );
        $meta_description = get_post_meta( $post_id, '_ai_seo_meta_description', true );

        $title  = $meta_title ? $meta_title : get_the_title( $post_id );
        $desc   = $meta_description ? $meta_description : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '...' );
        $url    = get_permalink( $post_id );
        $author = get_the_author_meta( 'display_name', $post->post_author );

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
                'url'   => get_author_posts_url( $post->post_author ),
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

        $schema['wordCount'] = str_word_count( wp_strip_all_tags( $post->post_content ) );

        $categories = get_the_category( $post_id );
        if ( ! empty( $categories ) ) {
            $schema['keywords'] = implode( ', ', wp_list_pluck( $categories, 'name' ) );
        }

        $this->render_json_ld( $schema );
    }

    private function output_faq_schema( $post ) {
        $faq_items = $this->extract_faq_items( $post->post_content );

        if ( empty( $faq_items ) ) {
            return;
        }

        $schema = array(
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => array(),
        );

        foreach ( $faq_items as $item ) {
            $schema['mainEntity'][] = array(
                '@type'          => 'Question',
                'name'           => $item['question'],
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text'  => $item['answer'],
                ),
            );
        }

        $this->render_json_ld( $schema );
    }

    private function output_howto_schema( $post ) {
        $meta_title       = get_post_meta( $post->ID, '_ai_seo_meta_title', true );
        $meta_description = get_post_meta( $post->ID, '_ai_seo_meta_description', true );

        $title = $meta_title ? $meta_title : get_the_title( $post->ID );
        $desc  = $meta_description ? $meta_description : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '...' );

        $steps = $this->extract_howto_steps( $post->post_content );

        $schema = array(
            '@context'    => 'https://schema.org',
            '@type'       => 'HowTo',
            'name'        => $title,
            'description' => $desc,
            'step'        => array(),
        );

        if ( has_post_thumbnail( $post->ID ) ) {
            $image_data = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'full' );
            if ( $image_data ) {
                $schema['image'] = $image_data[0];
            }
        }

        foreach ( $steps as $i => $step ) {
            $schema['step'][] = array(
                '@type'    => 'HowToStep',
                'position' => $i + 1,
                'name'     => $step['title'],
                'text'     => $step['text'],
            );
        }

        if ( ! empty( $schema['step'] ) ) {
            $this->render_json_ld( $schema );
        }
    }

    public function output_organization_schema() {
        if ( ! is_front_page() ) {
            return;
        }

        $options  = get_option( 'ai_seo_options', array() );
        $org_type = isset( $options['schema_org_type'] ) ? $options['schema_org_type'] : '';

        if ( empty( $org_type ) ) {
            return;
        }

        $schema = array(
            '@context' => 'https://schema.org',
            '@type'    => $org_type,
            'name'     => get_bloginfo( 'name' ),
            'url'      => home_url( '/' ),
        );

        $description = get_bloginfo( 'description' );
        if ( $description ) {
            $schema['description'] = $description;
        }

        $site_icon_id = get_option( 'site_icon' );
        if ( $site_icon_id ) {
            $icon_data = wp_get_attachment_image_src( $site_icon_id, 'full' );
            if ( $icon_data ) {
                $schema['logo'] = $icon_data[0];
            }
        }

        if ( ! empty( $options['schema_org_phone'] ) ) {
            $schema['telephone'] = $options['schema_org_phone'];
        }
        if ( ! empty( $options['schema_org_email'] ) ) {
            $schema['email'] = $options['schema_org_email'];
        }
        if ( ! empty( $options['schema_org_address'] ) ) {
            $schema['address'] = array(
                '@type'         => 'PostalAddress',
                'streetAddress' => $options['schema_org_address'],
            );
        }

        $this->render_json_ld( $schema );
    }

    private function extract_faq_items( $html ) {
        $items = array();

        if ( ! preg_match_all( '/<h3[^>]*>(.*?)<\/h3>(.*?)(?=<h[23]|$)/is', $html, $matches ) ) {
            return $items;
        }

        for ( $i = 0; $i < count( $matches[1] ); $i++ ) {
            $question = wp_strip_all_tags( $matches[1][ $i ] );
            $answer   = wp_strip_all_tags( trim( $matches[2][ $i ] ) );

            if ( ! empty( $question ) && ! empty( $answer ) ) {
                $items[] = array(
                    'question' => $question,
                    'answer'   => $answer,
                );
            }
        }

        return $items;
    }

    private function extract_howto_steps( $html ) {
        $steps = array();

        if ( ! preg_match_all( '/<h3[^>]*>(.*?)<\/h3>(.*?)(?=<h[23]|$)/is', $html, $matches ) ) {
            return $steps;
        }

        for ( $i = 0; $i < count( $matches[1] ); $i++ ) {
            $title = wp_strip_all_tags( $matches[1][ $i ] );
            $text  = wp_strip_all_tags( trim( $matches[2][ $i ] ) );

            if ( ! empty( $title ) ) {
                $steps[] = array(
                    'title' => $title,
                    'text'  => $text,
                );
            }
        }

        return $steps;
    }

    private function render_json_ld( $schema ) {
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        echo "\n</script>\n";
    }
}
