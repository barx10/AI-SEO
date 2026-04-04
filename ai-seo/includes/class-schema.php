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

        $this->output_video_schema( $post );
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

        $focus_keyword = get_post_meta( $post_id, '_ai_seo_focus_keyword', true );
        if ( ! empty( $focus_keyword ) ) {
            $schema['keywords'] = $focus_keyword;
        } else {
            $tags = get_the_tags( $post_id );
            if ( ! empty( $tags ) ) {
                $schema['keywords'] = implode( ', ', wp_list_pluck( $tags, 'name' ) );
            }
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

    private function output_video_schema( $post ) {
        $post_id   = $post->ID;
        $embed_url = get_post_meta( $post_id, '_ai_seo_video_embed_url', true );

        if ( empty( $embed_url ) ) {
            return;
        }

        $meta_title       = get_post_meta( $post_id, '_ai_seo_meta_title', true );
        $meta_description = get_post_meta( $post_id, '_ai_seo_meta_description', true );
        $video_name       = get_post_meta( $post_id, '_ai_seo_video_name', true );
        $video_desc       = get_post_meta( $post_id, '_ai_seo_video_description', true );
        $thumbnail        = get_post_meta( $post_id, '_ai_seo_video_thumbnail_url', true );
        $upload_date      = get_post_meta( $post_id, '_ai_seo_video_upload_date', true );
        $duration         = get_post_meta( $post_id, '_ai_seo_video_duration', true );

        $name = $video_name
            ? $video_name
            : ( $meta_title ? $meta_title : get_the_title( $post_id ) );

        $description = $video_desc
            ? $video_desc
            : ( $meta_description ? $meta_description : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '...' ) );

        $schema = array(
            '@context'    => 'https://schema.org',
            '@type'       => 'VideoObject',
            'name'        => $name,
            'description' => $description,
            'embedUrl'    => esc_url_raw( $embed_url ),
            'url'         => get_permalink( $post_id ),
        );

        if ( $thumbnail ) {
            $schema['thumbnailUrl'] = esc_url_raw( $thumbnail );
        }

        if ( $upload_date ) {
            $schema['uploadDate'] = sanitize_text_field( $upload_date );
        }

        if ( $duration ) {
            $schema['duration'] = sanitize_text_field( $duration );
        }

        $this->render_json_ld( $schema );
    }

    public function output_organization_schema() {
        $options  = get_option( 'ai_seo_options', array() );
        $org_type = isset( $options['schema_org_type'] ) ? $options['schema_org_type'] : '';

        if ( empty( $org_type ) ) {
            return;
        }

        // Person schema: output on front page and on the about page.
        if ( 'Person' === $org_type ) {
            $this->maybe_output_person_schema( $options );
            return;
        }

        // Organization/business schema: front page only.
        if ( ! is_front_page() ) {
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

    private function maybe_output_person_schema( $options ) {
        $about_url = ! empty( $options['schema_person_about_url'] )
            ? $options['schema_person_about_url']
            : '/om-laererliv/';

        // Resolve relative URL to absolute for comparison.
        $about_url_absolute = ( strpos( $about_url, 'http' ) === 0 )
            ? $about_url
            : home_url( $about_url );

        $is_about_page = is_page() && trailingslashit( get_permalink() ) === trailingslashit( $about_url_absolute );

        // Output full @graph (WebSite + Person) on front page and about page.
        // On all other pages, output only the Person node for entity recognition.
        $full_graph = is_front_page() || $is_about_page;

        // Build Person object.
        $person = array(
            '@type' => 'Person',
            '@id'   => home_url( '/#person' ),
        );

        if ( ! empty( $options['schema_person_name'] ) ) {
            $person['name'] = $options['schema_person_name'];
        }

        $person['url'] = $about_url_absolute;

        if ( ! empty( $options['schema_person_job_title'] ) ) {
            $person['jobTitle'] = $options['schema_person_job_title'];
        }
        if ( ! empty( $options['schema_person_email'] ) ) {
            $person['email'] = $options['schema_person_email'];
        }

        if ( ! empty( $options['schema_person_same_as'] ) ) {
            $urls = array_filter( array_map( 'trim', explode( "\n", $options['schema_person_same_as'] ) ) );
            if ( ! empty( $urls ) ) {
                $person['sameAs'] = array_values( $urls );
            }
        }

        // Build WebSite object.
        $website = array(
            '@type'       => 'WebSite',
            '@id'         => home_url( '/#website' ),
            'name'        => get_bloginfo( 'name' ),
            'url'         => home_url( '/' ),
            'inLanguage'  => 'nb-NO',
            'potentialAction' => array(
                '@type'       => 'SearchAction',
                'target'      => home_url( '/?s={search_term_string}' ),
                'query-input' => 'required name=search_term_string',
            ),
        );

        $description = get_bloginfo( 'description' );
        if ( $description ) {
            $website['description'] = $description;
        }

        if ( $full_graph ) {
            $schema = array(
                '@context' => 'https://schema.org',
                '@graph'   => array( $website, $person ),
            );
        } else {
            $schema = array(
                '@context' => 'https://schema.org',
                '@graph'   => array( $person ),
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
