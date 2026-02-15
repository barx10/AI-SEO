<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_SEO_Readability {

    /** @var array|null Cached language configuration. */
    private $cfg = null;

    /**
     * Return the language configuration based on the content_language setting.
     *
     * @return array
     */
    private function cfg() {
        if ( null !== $this->cfg ) {
            return $this->cfg;
        }

        $options = get_option( 'ai_seo_options', array() );
        $lang    = isset( $options['content_language'] ) ? $options['content_language'] : 'nb';

        if ( 'en' === $lang ) {
            $this->cfg = array(
                'lang'             => 'en',
                'vowel_regex'      => '/[aeiouy]+/u',
                'flesch_coeff'     => 84.6,
                'passive_markers'  => array( 'was', 'were', 'been', 'being', 'is', 'are', 'got' ),
                'passive_regex'    => '/\b(was|were|been|being|is|are|got)\s+\w+(ed|en)\b/iu',
                'transition_words' => array(
                    'furthermore', 'moreover', 'in addition', 'firstly', 'secondly',
                    'for example', 'for instance', 'such as', 'in other words', 'that is',
                    'on the other hand', 'however', 'nevertheless', 'nonetheless', 'despite',
                    'although', 'because', 'since', 'therefore', 'consequently',
                    'as a result', 'in conclusion', 'to summarize', 'overall',
                    'in summary', 'in short', 'above all', 'finally',
                    'meanwhile', 'subsequently', 'then', 'ultimately',
                    'but', 'also', 'thus', 'hence', 'namely', 'otherwise',
                ),
            );
        } else {
            $this->cfg = array(
                'lang'             => 'nb',
                'vowel_regex'      => '/[aeiouyæøå]+/u',
                'flesch_coeff'     => 66.0,
                'passive_markers'  => array( 'ble', 'blitt', 'blir', 'blei' ),
                'passive_regex'    => '/\b(ble|blitt|blir|blei)\s+\w+(t|et|dd)\b/u',
                'transition_words' => array(
                    'dessuten', 'videre', 'i tillegg', 'for det første', 'for det andre',
                    'for eksempel', 'blant annet', 'det vil si', 'med andre ord',
                    'på den andre siden', 'derimot', 'imidlertid', 'likevel', 'til tross for',
                    'selv om', 'fordi', 'ettersom', 'derfor', 'dermed', 'følgelig',
                    'som et resultat', 'konklusjonen er', 'oppsummert', 'alt i alt',
                    'for å oppsummere', 'kort sagt', 'først og fremst', 'til slutt',
                    'samtidig', 'i mellomtiden', 'deretter', 'så', 'endelig',
                    'men', 'også', 'slik', 'altså', 'nemlig', 'ellers',
                ),
            );
        }

        return $this->cfg;
    }

    /**
     * Analyze text readability with extended metrics.
     *
     * @param  string $text Plain text content (no HTML).
     * @return array  Analysis results.
     */
    public function analyze( $text ) {
        $cfg      = $this->cfg();
        $is_en    = 'en' === $cfg['lang'];
        $defaults = array(
            'score'               => 0,
            'rating'              => 'none',
            'label'               => $is_en ? 'No content to analyze' : 'Ingen innhold å analysere',
            'avg_sentence_length' => 0,
            'avg_word_length'     => 0,
            'sentence_count'      => 0,
            'word_count'          => 0,
            'flesch_kincaid'      => 0,
            'passive_percentage'  => 0,
            'transition_percentage' => 0,
            'long_sentences_pct'  => 0,
            'long_paragraphs'     => 0,
            'suggestions'         => array(),
        );

        $text = trim( $text );
        if ( empty( $text ) ) {
            return $defaults;
        }

        $sentences      = $this->split_sentences( $text );
        $sentence_count = count( $sentences );

        if ( 0 === $sentence_count ) {
            return $defaults;
        }

        $words      = $this->extract_words( $text );
        $word_count = count( $words );

        if ( 0 === $word_count ) {
            return $defaults;
        }

        $avg_sentence_length = round( $word_count / $sentence_count, 1 );
        $avg_word_length     = $this->average_word_length( $words );
        $syllable_count      = $this->count_syllables_total( $words );

        $flesch_kincaid = $this->flesch_kincaid_score( $word_count, $sentence_count, $syllable_count );

        // Passive voice percentage.
        $passive_count = $this->count_passive_sentences( $sentences );
        $passive_pct   = round( ( $passive_count / $sentence_count ) * 100, 1 );

        // Transition word percentage.
        $transition_count = $this->count_transition_sentences( $sentences );
        $transition_pct   = round( ( $transition_count / $sentence_count ) * 100, 1 );

        // Long sentences (>25 words).
        $long_sentences = 0;
        foreach ( $sentences as $s ) {
            $s_words = $this->extract_words( $s );
            if ( count( $s_words ) > 25 ) {
                $long_sentences++;
            }
        }
        $long_sentences_pct = round( ( $long_sentences / $sentence_count ) * 100, 1 );

        // Long paragraphs (>150 words).
        $paragraphs      = preg_split( '/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY );
        $long_paragraphs = 0;
        foreach ( $paragraphs as $p ) {
            if ( str_word_count( $p ) > 150 ) {
                $long_paragraphs++;
            }
        }

        // Calculate composite score.
        $score = $this->calculate_composite_score(
            $avg_sentence_length,
            $avg_word_length,
            $flesch_kincaid,
            $passive_pct,
            $transition_pct,
            $long_sentences_pct
        );

        $rating      = $this->get_rating( $score );
        $label       = $this->get_label( $score );
        $suggestions = $this->build_suggestions(
            $avg_sentence_length,
            $passive_pct,
            $transition_pct,
            $long_sentences_pct,
            $long_paragraphs,
            $flesch_kincaid,
            $word_count
        );

        return array(
            'score'                 => $score,
            'rating'                => $rating,
            'label'                 => $label,
            'avg_sentence_length'   => $avg_sentence_length,
            'avg_word_length'       => $avg_word_length,
            'sentence_count'        => $sentence_count,
            'word_count'            => $word_count,
            'flesch_kincaid'        => $flesch_kincaid,
            'passive_percentage'    => $passive_pct,
            'transition_percentage' => $transition_pct,
            'long_sentences_pct'    => $long_sentences_pct,
            'long_paragraphs'       => $long_paragraphs,
            'suggestions'           => $suggestions,
        );
    }

    private function split_sentences( $text ) {
        $parts = preg_split( '/[.!?]+[\s]+|[.!?]+$/', $text, -1, PREG_SPLIT_NO_EMPTY );
        return array_filter( array_map( 'trim', $parts ) );
    }

    private function extract_words( $text ) {
        preg_match_all( '/[\p{L}\p{N}]+/u', $text, $matches );
        return $matches[0];
    }

    private function average_word_length( $words ) {
        if ( empty( $words ) ) {
            return 0;
        }
        $total = 0;
        foreach ( $words as $word ) {
            $total += mb_strlen( $word );
        }
        return round( $total / count( $words ), 1 );
    }

    private function count_syllables_total( $words ) {
        $total = 0;
        foreach ( $words as $word ) {
            $total += $this->count_syllables( $word );
        }
        return $total;
    }

    /**
     * Approximate syllable count.
     *
     * Norwegian includes æ, ø, å as vowels.
     * English uses standard a-e-i-o-u-y.
     */
    private function count_syllables( $word ) {
        $word  = mb_strtolower( $word );
        $cfg   = $this->cfg();
        $count = preg_match_all( $cfg['vowel_regex'], $word );
        return max( 1, $count );
    }

    /**
     * Calculate Flesch Reading Ease.
     *
     * Norwegian uses a reduced syllable coefficient (66.0) because
     * compound words and inflectional morphology yield more syllables.
     * English uses the standard coefficient (84.6).
     */
    private function flesch_kincaid_score( $words, $sentences, $syllables ) {
        if ( $sentences === 0 || $words === 0 ) {
            return 0;
        }
        $cfg   = $this->cfg();
        $score = 206.835
            - ( 1.015 * ( $words / $sentences ) )
            - ( $cfg['flesch_coeff'] * ( $syllables / $words ) );

        return (int) round( max( 0, min( 100, $score ) ) );
    }

    private function count_passive_sentences( $sentences ) {
        $cfg   = $this->cfg();
        $count = 0;
        foreach ( $sentences as $s ) {
            $lower = mb_strtolower( $s );
            foreach ( $cfg['passive_markers'] as $marker ) {
                if ( mb_strpos( $lower, $marker ) !== false ) {
                    if ( preg_match( $cfg['passive_regex'], $lower ) ) {
                        $count++;
                        break;
                    }
                }
            }
        }
        return $count;
    }

    private function count_transition_sentences( $sentences ) {
        $cfg   = $this->cfg();
        $count = 0;
        foreach ( $sentences as $s ) {
            $lower = mb_strtolower( $s );
            foreach ( $cfg['transition_words'] as $tw ) {
                if ( mb_strpos( $lower, $tw ) !== false ) {
                    $count++;
                    break;
                }
            }
        }
        return $count;
    }

    /**
     * Calculate composite readability score (0-100).
     */
    private function calculate_composite_score( $avg_sentence_length, $avg_word_length, $flesch, $passive_pct, $transition_pct, $long_pct ) {
        // Sentence length (20 pts).
        $sentence_score = 20;
        if ( $avg_sentence_length < 10 ) {
            $sentence_score = max( 0, 20 - ( ( 10 - $avg_sentence_length ) * 2 ) );
        } elseif ( $avg_sentence_length > 20 ) {
            $sentence_score = max( 0, 20 - ( ( $avg_sentence_length - 20 ) * 1.5 ) );
        } elseif ( $avg_sentence_length < 15 ) {
            $sentence_score = 15 + ( ( $avg_sentence_length - 10 ) * 1 );
        }

        // Word length (15 pts).
        $word_score = 15;
        if ( $avg_word_length < 3 ) {
            $word_score = max( 0, 15 - ( ( 3 - $avg_word_length ) * 5 ) );
        } elseif ( $avg_word_length > 8 ) {
            $word_score = max( 0, 15 - ( ( $avg_word_length - 8 ) * 3 ) );
        } elseif ( $avg_word_length > 6 ) {
            $word_score = 15 - ( ( $avg_word_length - 6 ) * 2 );
        } elseif ( $avg_word_length < 4 ) {
            $word_score = 10 + ( ( $avg_word_length - 3 ) * 5 );
        }

        // Flesch-Kincaid (25 pts).
        $flesch_score = round( ( $flesch / 100 ) * 25 );

        // Passive voice penalty (15 pts).
        $passive_score = 15;
        if ( $passive_pct > 25 ) {
            $passive_score = 0;
        } elseif ( $passive_pct > 10 ) {
            $passive_score = max( 0, 15 - ( ( $passive_pct - 10 ) * 1 ) );
        }

        // Transition words (15 pts).
        $transition_score = 15;
        if ( $transition_pct < 10 ) {
            $transition_score = max( 0, (int) round( ( $transition_pct / 10 ) * 8 ) );
        } elseif ( $transition_pct < 30 ) {
            $transition_score = 8 + (int) round( ( ( $transition_pct - 10 ) / 20 ) * 7 );
        }

        // Long sentences penalty (10 pts).
        $long_score = 10;
        if ( $long_pct > 40 ) {
            $long_score = 0;
        } elseif ( $long_pct > 25 ) {
            $long_score = max( 0, 10 - (int) round( ( $long_pct - 25 ) * 0.67 ) );
        }

        $total = $sentence_score + $word_score + $flesch_score + $passive_score + $transition_score + $long_score;
        return max( 0, min( 100, (int) round( $total ) ) );
    }

    private function get_rating( $score ) {
        if ( $score >= 80 ) {
            return 'good';
        }
        if ( $score >= 50 ) {
            return 'ok';
        }
        return 'poor';
    }

    private function get_label( $score ) {
        $is_en = 'en' === $this->cfg()['lang'];

        if ( $score >= 80 ) {
            return $is_en ? 'Good readability' : 'God lesbarhet';
        }
        if ( $score >= 50 ) {
            return $is_en ? 'Fair readability' : 'Middels lesbarhet';
        }
        return $is_en ? 'Poor readability' : 'Dårlig lesbarhet';
    }

    /**
     * Return HTML with sentences highlighted by readability issue.
     *
     * @param  string $html Post content (may contain HTML).
     * @return string Annotated HTML.
     */
    public function highlight( $html ) {
        // Strip tags but keep paragraph breaks for display.
        $text = wp_strip_all_tags( $html );
        $text = trim( $text );

        if ( empty( $text ) ) {
            return '';
        }

        // Split into sentences while keeping delimiters.
        $parts = preg_split( '/([.!?]+[\s]+|[.!?]+$)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );

        $output    = '';
        $sentence  = '';

        foreach ( $parts as $part ) {
            // Delimiters (punctuation + space) — append to current sentence then flush.
            if ( preg_match( '/^[.!?]+[\s]*$/u', $part ) ) {
                $sentence .= $part;
                $output   .= $this->wrap_sentence( $sentence );
                $sentence  = '';
            } else {
                $sentence = $part;
            }
        }

        // Flush remaining.
        if ( $sentence !== '' ) {
            $output .= $this->wrap_sentence( $sentence );
        }

        return $output;
    }

    /**
     * Wrap a single sentence in a <span> if it has readability issues.
     */
    private function wrap_sentence( $sentence ) {
        $trimmed = trim( $sentence );
        if ( $trimmed === '' ) {
            return $sentence;
        }

        $cfg     = $this->cfg();
        $is_en   = 'en' === $cfg['lang'];
        $classes = array();
        $tips    = array();

        // Long sentence check (>25 words).
        $words = $this->extract_words( $trimmed );
        $wc    = count( $words );
        if ( $wc > 25 ) {
            $classes[] = 'ai-seo-hl-long';
            $tips[]    = $is_en
                ? $wc . ' words – try splitting this sentence'
                : $wc . ' ord – forsøk å dele opp';
        }

        // Passive voice check.
        $lower = mb_strtolower( $trimmed );
        foreach ( $cfg['passive_markers'] as $marker ) {
            if ( mb_strpos( $lower, $marker ) !== false ) {
                if ( preg_match( $cfg['passive_regex'], $lower ) ) {
                    $classes[] = 'ai-seo-hl-passive';
                    $tips[]    = $is_en ? 'passive voice' : 'passiv stemme';
                    break;
                }
            }
        }

        if ( empty( $classes ) ) {
            return esc_html( $sentence );
        }

        return '<span class="' . implode( ' ', $classes ) . '" data-tip="' . esc_attr( implode( ' | ', $tips ) ) . '">'
            . esc_html( $sentence )
            . '</span>';
    }

    private function build_suggestions( $avg_sentence, $passive_pct, $transition_pct, $long_pct, $long_paras, $flesch, $word_count ) {
        $is_en       = 'en' === $this->cfg()['lang'];
        $suggestions = array();

        if ( $avg_sentence > 20 ) {
            $suggestions[] = $is_en
                ? 'Average sentence length is ' . $avg_sentence . ' words. Try to keep it under 20.'
                : 'Gjennomsnittlig setningslengde er ' . $avg_sentence . ' ord. Forsøk å holde den under 20.';
        }

        if ( $passive_pct > 10 ) {
            $suggestions[] = $is_en
                ? sprintf( '%.0f%% of sentences use passive voice. Try to keep it under 10%%.', $passive_pct )
                : sprintf( '%.0f %% av setningene bruker passiv stemme. Forsøk å holde det under 10 %%.', $passive_pct );
        }

        if ( $transition_pct < 30 ) {
            $suggestions[] = $is_en
                ? sprintf( 'Only %.0f%% of sentences contain transition words. Use more for better flow (aim for 30%%+).', $transition_pct )
                : sprintf( 'Kun %.0f %% av setningene inneholder overgangsord. Bruk flere for bedre flyt (mål: 30 %%+).', $transition_pct );
        }

        if ( $long_pct > 25 ) {
            $suggestions[] = $is_en
                ? sprintf( '%.0f%% of sentences are over 25 words. Try splitting the longest sentences.', $long_pct )
                : sprintf( '%.0f %% av setningene er over 25 ord. Forsøk å dele opp de lengste setningene.', $long_pct );
        }

        if ( $long_paras > 0 ) {
            $suggestions[] = $is_en
                ? sprintf( '%d paragraphs have over 150 words. Use shorter paragraphs for better readability.', $long_paras )
                : sprintf( '%d avsnitt har over 150 ord. Bruk kortere avsnitt for bedre lesbarhet.', $long_paras );
        }

        if ( $flesch < 50 ) {
            $suggestions[] = $is_en
                ? 'Flesch readability index is low (' . $flesch . '/100). Consider simpler sentence structures and shorter words.'
                : 'Flesch-lesbarhetsindeksen er lav (' . $flesch . '/100). Vurder enklere setningsstrukturer og kortere ord.';
        }

        if ( $word_count < 300 ) {
            $suggestions[] = $is_en
                ? 'The content has only ' . $word_count . ' words. Search engines prefer at least 300 words.'
                : 'Innholdet har kun ' . $word_count . ' ord. Søkemotorer foretrekker minst 300 ord.';
        }

        return $suggestions;
    }
}
