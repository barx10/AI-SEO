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
        $text_lower    = mb_strtolower( self::normalize_for_comparison( $text ) );
        $keyword_lower = mb_strtolower( self::normalize_for_comparison( $keyword ) );
        $keyword_count = substr_count( $text_lower, $keyword_lower );
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

    /**
     * Run AI quality checks for a post.
     * Results are cached for 7 days keyed on content hash.
     *
     * @param WP_Post $post
     * @param string  $seo_title       The SEO title (may differ from post_title)
     * @param string  $meta_description
     * @return array  ['checks' => array, 'cached' => bool]
     */
    public static function analyze_ai_quality( $post, $seo_title = '', $meta_description = '' ) {
        $title   = ! empty( $seo_title ) ? $seo_title : $post->post_title;
        $content = wp_strip_all_tags( $post->post_content );

        // Cache key: invalidates when title or content changes
        $cache_key = 'ai_seo_quality_' . $post->ID . '_' . md5( $title . $content );
        $cached    = get_transient( $cache_key );
        if ( false !== $cached && is_array( $cached ) ) {
            return [ 'checks' => $cached, 'cached' => true ];
        }

        $client         = new AI_SEO_Client();
        $checks         = [];
        $content_sample = self::truncate_at_sentence( $content, 1500 );
        $html_sample    = mb_substr( $post->post_content, 0, 3000, 'UTF-8' );
        // Strip Gutenberg block comments so AI sees clean HTML
        $html_sample = preg_replace( '/<!--\s*\/?wp:[^>]*-->\s*/', '', $html_sample );

        // 1. Semantic alignment
        $checks[] = self::check_semantic_alignment( $client, $title, $content_sample );

        // 2. Grammar & typos
        $checks[] = self::check_grammar( $client, $content_sample );

        // 3. Authenticity & originality
        $checks[] = self::check_authenticity( $client, $content_sample );

        // 4. Personality & engagement
        $checks[] = self::check_personality( $client, $content_sample );

        // 5. Structure quality (uses HTML)
        $checks[] = self::check_structure( $client, $html_sample );

        // 6. Readability (Flesch + optional AI feedback)
        $checks[] = self::check_readability( $client, $content );

        // 7. Topic completeness
        $checks[] = self::check_topic_completeness( $client, $title, $content_sample );

        // Cache for 7 days
        set_transient( $cache_key, $checks, 7 * DAY_IN_SECONDS );

        return [ 'checks' => $checks, 'cached' => false ];
    }

    /**
     * Truncate text at sentence boundary.
     */
    private static function truncate_at_sentence( $text, $max_chars = 1500 ) {
        if ( mb_strlen( $text ) <= $max_chars ) {
            return $text;
        }
        $truncated = mb_substr( $text, 0, $max_chars );
        $last      = max(
            mb_strrpos( $truncated, '.' ) ?: 0,
            mb_strrpos( $truncated, '!' ) ?: 0,
            mb_strrpos( $truncated, '?' ) ?: 0
        );
        if ( $last > $max_chars * 0.6 ) {
            return mb_substr( $truncated, 0, $last + 1 );
        }
        return $truncated;
    }

    /**
     * Parse JSON from AI response, stripping markdown code fences.
     */
    private static function parse_ai_json( $response ) {
        $response = preg_replace( '/```json\s*/', '', $response );
        $response = preg_replace( '/```\s*$/', '', $response );
        return json_decode( trim( $response ), true );
    }

    /**
     * Format feedback: strip markdown lists/headers, truncate at 150 chars.
     */
    private static function format_feedback( $text ) {
        if ( empty( $text ) || ! is_string( $text ) ) {
            return '';
        }
        $text = preg_replace( '/^#{1,6}\s+/m', '', $text );
        $text = preg_replace( '/^[-*\x{2022}]\s+/mu', '', $text );
        $text = preg_replace( '/^\d+[.)]\s+/m', '', $text );
        $text = preg_replace( '/\s+/', ' ', $text );
        $text = trim( $text );
        if ( strlen( $text ) > 150 ) {
            $cut    = substr( $text, 0, 150 );
            $period = strrpos( $cut, '.' );
            $text   = ( $period !== false && $period > 80 )
                ? substr( $cut, 0, $period + 1 )
                : $cut . '...';
        }
        return $text;
    }

    private static function check_semantic_alignment( $client, $title, $content_sample ) {
        $base = [
            'id'       => 'ai_semantic_alignment',
            'label'    => 'Semantisk samsvar (tittel vs. innhold)',
            'pass'     => false,
            'detail'   => '',
            'weight'   => 0,
            'ai'       => true,
            'score'    => 'NA',
            'feedback' => '',
        ];
        $prompt   = "Rate how well this title matches the content (0.0 to 1.0). Be generous — only give low scores if title is misleading. Respond with ONLY a number.\n\nTitle: {$title}\n\nContent preview: {$content_sample}";
        $response = $client->send_request( $prompt );
        if ( is_wp_error( $response ) ) {
            return $base;
        }
        $score = max( 0.0, min( 1.0, floatval( trim( $response ) ) ) );
        $pct   = round( $score * 100 );
        return array_merge( $base, [
            'pass'   => $pct >= 70,
            'detail' => $pct . '/100',
            'score'  => $pct,
        ] );
    }

    private static function check_grammar( $client, $content_sample ) {
        $base     = [
            'id' => 'ai_grammar_typos', 'label' => 'Grammatikk og skrivefeil',
            'pass' => false, 'detail' => '', 'weight' => 0, 'ai' => true, 'score' => 'NA', 'feedback' => '',
        ];
        $prompt   = "Grammar check. Score 0-100. Only flag clear mistakes: misspellings, broken syntax, wrong conjugations, missing words. Ignore stylistic choices. The text may be truncated — do NOT flag last sentence as incomplete. If < 80, list 2 issues max. Very brief.\n\nText:\n{$content_sample}\n\nJSON: {\"score\": X, \"feedback\": \"...\"}";
        $response = $client->send_request( $prompt );
        if ( is_wp_error( $response ) ) {
            return $base;
        }
        $result = self::parse_ai_json( $response );
        if ( ! $result || ! isset( $result['score'] ) ) {
            return $base;
        }
        $score = intval( $result['score'] );
        if ( $score < 0 || $score > 100 ) {
            return $base;
        }
        return array_merge( $base, [
            'pass'     => $score >= 70,
            'detail'   => $score . '/100',
            'score'    => $score,
            'feedback' => self::format_feedback( $result['feedback'] ?? '' ),
        ] );
    }

    private static function check_authenticity( $client, $content_sample ) {
        $base     = [
            'id' => 'ai_authenticity', 'label' => 'Autentisitet og originalitet',
            'pass' => false, 'detail' => '', 'weight' => 0, 'ai' => true, 'score' => 'NA', 'feedback' => '',
        ];
        $prompt   = "Originality check. Score 0-100. Only flag obviously templated or AI-generated phrasing. Creative writing is NOT generic. Be lenient — most human-written content should score 70+. If < 70, list 2 generic phrases max. Very brief.\n\nText:\n{$content_sample}\n\nJSON: {\"score\": X, \"feedback\": \"...\"}";
        $response = $client->send_request( $prompt );
        if ( is_wp_error( $response ) ) {
            return $base;
        }
        $result = self::parse_ai_json( $response );
        if ( ! $result || ! isset( $result['score'] ) ) {
            return $base;
        }
        $score = intval( $result['score'] );
        if ( $score < 0 || $score > 100 ) {
            return $base;
        }
        return array_merge( $base, [
            'pass'     => $score >= 70,
            'detail'   => $score . '/100',
            'score'    => $score,
            'feedback' => self::format_feedback( $result['feedback'] ?? '' ),
        ] );
    }

    private static function check_personality( $client, $content_sample ) {
        $base     = [
            'id' => 'ai_personality', 'label' => 'Personlighet og engasjement',
            'pass' => false, 'detail' => '', 'weight' => 0, 'ai' => true, 'score' => 'NA', 'feedback' => '',
        ];
        $prompt   = "Personality check. Score 0-100. Does the text have a human voice, personal touch? If < 70, list 2 suggestions max. Very brief.\n\nText:\n{$content_sample}\n\nJSON: {\"score\": X, \"feedback\": \"...\"}";
        $response = $client->send_request( $prompt );
        if ( is_wp_error( $response ) ) {
            return $base;
        }
        $result = self::parse_ai_json( $response );
        if ( ! $result || ! isset( $result['score'] ) ) {
            return $base;
        }
        $score = intval( $result['score'] );
        if ( $score < 0 || $score > 100 ) {
            return $base;
        }
        return array_merge( $base, [
            'pass'     => $score >= 70,
            'detail'   => $score . '/100',
            'score'    => $score,
            'feedback' => self::format_feedback( $result['feedback'] ?? '' ),
        ] );
    }

    private static function check_structure( $client, $html_sample ) {
        $base = [
            'id' => 'ai_structure', 'label' => 'Innholdsstruktur',
            'pass' => false, 'detail' => '', 'weight' => 0, 'ai' => true, 'score' => 'NA', 'feedback' => '',
        ];
        if ( empty( $html_sample ) ) {
            return array_merge( $base, [ 'pass' => true, 'detail' => '100/100', 'score' => 100 ] );
        }
        $prompt   = "Analyze this HTML content structure. Rate 0-100. Only penalize real problems: wall of text with no paragraphs, extremely long paragraphs (300+ words), missing subheadings in long content (1000+ words). Do NOT penalize normal paragraph lengths or absence of bullet lists. Most well-structured articles should score 80+.\n\nHTML:\n{$html_sample}\n\nRespond with ONLY a number.";
        $response = $client->send_request( $prompt );
        if ( is_wp_error( $response ) ) {
            return $base;
        }
        $score = intval( trim( $response ) );
        if ( $score < 0 || $score > 100 ) {
            return $base;
        }
        return array_merge( $base, [
            'pass'   => $score >= 70,
            'detail' => $score . '/100',
            'score'  => $score,
        ] );
    }

    private static function check_readability( $client, $content ) {
        $base = [
            'id' => 'ai_readability', 'label' => 'Lesbarhet (Flesch)',
            'pass' => false, 'detail' => '', 'weight' => 0, 'ai' => true, 'score' => 'NA', 'feedback' => '',
        ];
        if ( empty( $content ) ) {
            return array_merge( $base, [ 'pass' => true, 'detail' => '100/100', 'score' => 100 ] );
        }

        // Calculate Flesch Reading Ease locally (no AI needed for the number)
        $sentences      = preg_split( '/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY );
        $sentence_count = max( 1, count( $sentences ) );
        $word_count     = max( 1, str_word_count( $content ) );
        $syllables      = preg_match_all( '/[aeiouyæøå]+/iu', $content );
        $flesch         = 206.835 - ( 1.015 * ( $word_count / $sentence_count ) ) - ( 84.6 * ( $syllables / $word_count ) );
        $flesch         = max( 0, min( 100, $flesch ) );

        // Map Flesch to 0-100 score
        if ( $flesch >= 60 ) {
            $score = 100;
        } elseif ( $flesch >= 30 ) {
            $score = (int) ( 70 + ( ( $flesch - 30 ) / 30 ) * 30 );
        } else {
            $score = (int) ( 40 + ( $flesch / 30 ) * 30 );
        }

        $feedback = '';
        // Only call AI for feedback when score is low
        if ( $score < 70 ) {
            $sample   = substr( $content, 0, 1000 );
            $prompt   = "Readability check. List 2 specific improvements max. Very brief.\n\nText:\n{$sample}";
            $response = $client->send_request( $prompt );
            if ( ! is_wp_error( $response ) ) {
                $feedback = self::format_feedback( trim( $response ) );
            }
        }

        return array_merge( $base, [
            'pass'     => $score >= 70,
            'detail'   => $score . '/100 (Flesch: ' . round( $flesch ) . ')',
            'score'    => $score,
            'feedback' => $feedback,
        ] );
    }

    private static function check_topic_completeness( $client, $title, $content_sample ) {
        $base     = [
            'id' => 'ai_topic_completeness', 'label' => 'Emne-fullstendighet',
            'pass' => false, 'detail' => '', 'weight' => 0, 'ai' => true, 'score' => 'NA', 'feedback' => '',
        ];
        $prompt   = "Topic coverage for '{$title}'. Score 0-100. If < 80, list 2 missing subtopics max. Very brief.\n\nContent:\n{$content_sample}\n\nJSON: {\"score\": X, \"feedback\": \"...\"}";
        $response = $client->send_request( $prompt );
        if ( is_wp_error( $response ) ) {
            return $base;
        }
        $result = self::parse_ai_json( $response );
        if ( ! $result || ! isset( $result['score'] ) ) {
            return $base;
        }
        $score = intval( $result['score'] );
        if ( $score < 0 || $score > 100 ) {
            return $base;
        }
        return array_merge( $base, [
            'pass'     => $score >= 70,
            'detail'   => $score . '/100',
            'score'    => $score,
            'feedback' => self::format_feedback( $result['feedback'] ?? '' ),
        ] );
    }
}
