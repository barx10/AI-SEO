<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_SEO_Score {

    /**
     * Analyze a post and return an SEO checklist with scores.
     *
     * @param  WP_Post $post    The post object.
     * @param  string  $keyword Focus keyword.
     * @param  string  $meta_title   Custom SEO title.
     * @param  string  $meta_description Custom meta description.
     * @return array   Checklist items and total score.
     */
    public static function analyze( $post, $keyword = '', $meta_title = '', $meta_description = '' ) {
        $checks = array();
        $content      = $post->post_content;
        $plain        = wp_strip_all_tags( $content );
        $title        = $meta_title ? $meta_title : $post->post_title;
        $description  = $meta_description;
        $word_count   = str_word_count( $plain );
        $keyword_lower = mb_strtolower( $keyword );

        // 1. Meta title length.
        $title_len = mb_strlen( $title );
        $checks[] = array(
            'id'     => 'title_length',
            'label'  => 'SEO-tittel har god lengde (30–60 tegn)',
            'pass'   => $title_len >= 30 && $title_len <= 60,
            'detail' => sprintf( '%d tegn', $title_len ),
            'weight' => 10,
        );

        // 2. Meta description set and good length.
        $desc_len = mb_strlen( $description );
        $checks[] = array(
            'id'     => 'description_set',
            'label'  => 'Metabeskrivelse er satt (120–160 tegn)',
            'pass'   => $desc_len >= 120 && $desc_len <= 160,
            'detail' => $desc_len > 0 ? sprintf( '%d tegn', $desc_len ) : 'Ikke satt',
            'weight' => 10,
        );

        // 3. Content length.
        $checks[] = array(
            'id'     => 'content_length',
            'label'  => 'Innholdet har tilstrekkelig lengde (300+ ord)',
            'pass'   => $word_count >= 300,
            'detail' => sprintf( '%d ord', $word_count ),
            'weight' => 10,
        );

        // 4. Focus keyword set.
        $has_keyword = ! empty( $keyword );
        $checks[] = array(
            'id'     => 'keyword_set',
            'label'  => 'Fokus-søkeord er definert',
            'pass'   => $has_keyword,
            'detail' => $has_keyword ? $keyword : 'Ikke satt',
            'weight' => 5,
        );

        // Keyword-dependent checks.
        if ( $has_keyword ) {
            // 5. Keyword in title.
            $title_normalized   = self::normalize_for_comparison( $title );
            $keyword_normalized = self::normalize_for_comparison( $keyword );
            $checks[] = array(
                'id'     => 'keyword_in_title',
                'label'  => 'Fokus-søkeord finnes i tittelen',
                'pass'   => mb_stripos( $title, $keyword ) !== false || mb_stripos( $title_normalized, $keyword_normalized ) !== false,
                'detail' => '',
                'weight' => 10,
            );

            // 6. Keyword in description.
            $checks[] = array(
                'id'     => 'keyword_in_desc',
                'label'  => 'Fokus-søkeord finnes i metabeskrivelsen',
                'pass'   => ! empty( $description ) && ( mb_stripos( $description, $keyword ) !== false || mb_stripos( self::normalize_for_comparison( $description ), $keyword_normalized ) !== false ),
                'detail' => '',
                'weight' => 10,
            );

            // 7. Keyword in first paragraph.
            $first_para = self::get_first_paragraph( $content );
            $checks[] = array(
                'id'     => 'keyword_in_intro',
                'label'  => 'Fokus-søkeord finnes i første avsnitt',
                'pass'   => mb_stripos( $first_para, $keyword ) !== false || mb_stripos( self::normalize_for_comparison( $first_para ), $keyword_normalized ) !== false,
                'detail' => '',
                'weight' => 10,
            );

            // 8. Keyword in headings.
            $headings = self::extract_headings( $content );
            $keyword_in_heading = false;
            foreach ( $headings as $h ) {
                if ( mb_stripos( $h, $keyword ) !== false || mb_stripos( self::normalize_for_comparison( $h ), $keyword_normalized ) !== false ) {
                    $keyword_in_heading = true;
                    break;
                }
            }
            $checks[] = array(
                'id'     => 'keyword_in_heading',
                'label'  => 'Fokus-søkeord finnes i en overskrift (H2–H6)',
                'pass'   => $keyword_in_heading,
                'detail' => count( $headings ) . ' overskrifter funnet',
                'weight' => 5,
            );

            // 9. Keyword density.
            $density = self::keyword_density( $plain, $keyword );
            $checks[] = array(
                'id'     => 'keyword_density',
                'label'  => 'Søkeordtetthet er i anbefalt område (1–3 %)',
                'pass'   => $density >= 1.0 && $density <= 3.0,
                'detail' => sprintf( '%.1f %%', $density ),
                'weight' => 5,
            );
        }

        // 10. Images have alt text.
        $image_check = self::check_images( $content );
        $checks[] = array(
            'id'     => 'images_alt',
            'label'  => 'Alle bilder har alt-tekst',
            'pass'   => $image_check['pass'],
            'detail' => $image_check['detail'],
            'weight' => 5,
        );

        // 11. Internal links present.
        $internal_count = self::count_internal_links( $content );
        $checks[] = array(
            'id'     => 'internal_links',
            'label'  => 'Innholdet har interne lenker',
            'pass'   => $internal_count >= 1,
            'detail' => sprintf( '%d interne lenker', $internal_count ),
            'weight' => 5,
        );

        // 12. External links present.
        $external_count = self::count_external_links( $content );
        $checks[] = array(
            'id'     => 'external_links',
            'label'  => 'Innholdet har eksterne lenker',
            'pass'   => $external_count >= 1,
            'detail' => sprintf( '%d eksterne lenker', $external_count ),
            'weight' => 5,
        );

        // 13. Heading structure.
        $has_h2 = (bool) preg_match( '/<h2[\s>]/i', $content );
        $checks[] = array(
            'id'     => 'has_subheadings',
            'label'  => 'Innholdet bruker underoverskrifter (H2)',
            'pass'   => $has_h2,
            'detail' => '',
            'weight' => 5,
        );

        // 14. Featured image set.
        $has_thumb = has_post_thumbnail( $post->ID );
        $checks[] = array(
            'id'     => 'featured_image',
            'label'  => 'Fremhevet bilde er satt',
            'pass'   => $has_thumb,
            'detail' => '',
            'weight' => 5,
        );

        // Calculate total score.
        $max_weight   = 0;
        $earned_weight = 0;
        foreach ( $checks as $check ) {
            $max_weight += $check['weight'];
            if ( $check['pass'] ) {
                $earned_weight += $check['weight'];
            }
        }

        $score = $max_weight > 0 ? (int) round( ( $earned_weight / $max_weight ) * 100 ) : 0;

        $rating = 'poor';
        if ( $score >= 80 ) {
            $rating = 'good';
        } elseif ( $score >= 50 ) {
            $rating = 'ok';
        }

        return array(
            'score'  => $score,
            'rating' => $rating,
            'checks' => $checks,
        );
    }

    private static function get_first_paragraph( $html ) {
        if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $html, $m ) ) {
            return wp_strip_all_tags( $m[1] );
        }
        $plain = wp_strip_all_tags( $html );
        $parts = preg_split( '/\n{2,}/', $plain, 2 );
        return isset( $parts[0] ) ? $parts[0] : '';
    }

    private static function extract_headings( $html ) {
        $headings = array();
        if ( preg_match_all( '/<h[2-6][^>]*>(.*?)<\/h[2-6]>/is', $html, $matches ) ) {
            foreach ( $matches[1] as $h ) {
                $headings[] = wp_strip_all_tags( $h );
            }
        }
        return $headings;
    }

    private static function keyword_density( $text, $keyword ) {
        $word_count = str_word_count( $text );
        if ( $word_count === 0 ) {
            return 0;
        }
        $keyword_count = mb_substr_count( mb_strtolower( $text ), mb_strtolower( $keyword ) );
        $keyword_words = str_word_count( $keyword );
        if ( $keyword_words === 0 ) {
            $keyword_words = 1;
        }
        return round( ( $keyword_count * $keyword_words / $word_count ) * 100, 1 );
    }

    private static function check_images( $html ) {
        if ( ! preg_match_all( '/<img[^>]*>/i', $html, $matches ) ) {
            return array( 'pass' => true, 'detail' => 'Ingen bilder i innholdet' );
        }

        $total   = count( $matches[0] );
        $missing = 0;
        foreach ( $matches[0] as $img ) {
            if ( ! preg_match( '/alt\s*=\s*["\'][^"\']+["\']/i', $img ) ) {
                $missing++;
            }
        }

        if ( $missing === 0 ) {
            return array( 'pass' => true, 'detail' => sprintf( '%d bilder, alle med alt-tekst', $total ) );
        }

        return array( 'pass' => false, 'detail' => sprintf( '%d av %d bilder mangler alt-tekst', $missing, $total ) );
    }

    private static function count_internal_links( $html ) {
        $home = wp_parse_url( home_url(), PHP_URL_HOST );
        if ( ! preg_match_all( '/<a[^>]+href\s*=\s*["\']([^"\']+)["\']/i', $html, $matches ) ) {
            return 0;
        }
        $count = 0;
        foreach ( $matches[1] as $url ) {
            $host = wp_parse_url( $url, PHP_URL_HOST );
            if ( $host === $home || ( empty( $host ) && strpos( $url, '/' ) === 0 ) ) {
                $count++;
            }
        }
        return $count;
    }

    private static function normalize_for_comparison( $text ) {
        // Replace hyphens, en-dashes and em-dashes with spaces for flexible matching.
        $text = str_replace( array( '-', "\xE2\x80\x93", "\xE2\x80\x94" ), ' ', $text );
        // Collapse multiple spaces.
        $text = preg_replace( '/\s+/', ' ', trim( $text ) );
        return $text;
    }

    private static function count_external_links( $html ) {
        $home = wp_parse_url( home_url(), PHP_URL_HOST );
        if ( ! preg_match_all( '/<a[^>]+href\s*=\s*["\']([^"\']+)["\']/i', $html, $matches ) ) {
            return 0;
        }
        $count = 0;
        foreach ( $matches[1] as $url ) {
            $host = wp_parse_url( $url, PHP_URL_HOST );
            if ( ! empty( $host ) && $host !== $home ) {
                $count++;
            }
        }
        return $count;
    }
}
