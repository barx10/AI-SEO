<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_SEO_Readability {

    /**
     * Analyze text readability based on sentence length and word length.
     *
     * @param  string $text Plain text content (no HTML).
     * @return array  Analysis results with score, rating, label, and stats.
     */
    public function analyze( $text ) {
        $defaults = array(
            'score'               => 0,
            'rating'              => 'none',
            'label'               => 'Ingen innhold å analysere',
            'avg_sentence_length' => 0,
            'avg_word_length'     => 0,
            'sentence_count'      => 0,
            'word_count'          => 0,
        );

        $text = trim( $text );
        if ( empty( $text ) ) {
            return $defaults;
        }

        $sentences = $this->split_sentences( $text );
        $sentence_count = count( $sentences );

        if ( 0 === $sentence_count ) {
            return $defaults;
        }

        $words = $this->extract_words( $text );
        $word_count = count( $words );

        if ( 0 === $word_count ) {
            return $defaults;
        }

        $avg_sentence_length = round( $word_count / $sentence_count, 1 );
        $avg_word_length     = $this->average_word_length( $words );

        $score = $this->calculate_score( $avg_sentence_length, $avg_word_length );
        $rating = $this->get_rating( $score );
        $label  = $this->get_label( $score );

        return array(
            'score'               => $score,
            'rating'              => $rating,
            'label'               => $label,
            'avg_sentence_length' => $avg_sentence_length,
            'avg_word_length'     => $avg_word_length,
            'sentence_count'      => $sentence_count,
            'word_count'          => $word_count,
        );
    }

    /**
     * Split text into sentences.
     */
    private function split_sentences( $text ) {
        // Split on sentence-ending punctuation followed by whitespace or end of string.
        $parts = preg_split( '/[.!?]+[\s]+|[.!?]+$/', $text, -1, PREG_SPLIT_NO_EMPTY );
        return array_filter( array_map( 'trim', $parts ) );
    }

    /**
     * Extract individual words from text.
     */
    private function extract_words( $text ) {
        // Match word characters including unicode letters.
        preg_match_all( '/[\p{L}\p{N}]+/u', $text, $matches );
        return $matches[0];
    }

    /**
     * Calculate average word length in characters.
     */
    private function average_word_length( $words ) {
        if ( empty( $words ) ) {
            return 0;
        }

        $total_length = 0;
        foreach ( $words as $word ) {
            $total_length += mb_strlen( $word );
        }

        return round( $total_length / count( $words ), 1 );
    }

    /**
     * Calculate readability score (0-100).
     *
     * Scoring logic:
     * - Ideal sentence length: 15-20 words (scores high).
     * - Ideal word length: 4-6 characters (scores high).
     * - Score is penalized as values deviate from ideals.
     */
    private function calculate_score( $avg_sentence_length, $avg_word_length ) {
        // Sentence length score (50 points max).
        // Ideal range: 15-20 words per sentence.
        $sentence_score = 50;
        if ( $avg_sentence_length < 10 ) {
            // Too short — choppy writing.
            $sentence_score = max( 0, 50 - ( ( 10 - $avg_sentence_length ) * 5 ) );
        } elseif ( $avg_sentence_length > 20 ) {
            // Too long — hard to read.
            $sentence_score = max( 0, 50 - ( ( $avg_sentence_length - 20 ) * 3 ) );
        } elseif ( $avg_sentence_length < 15 ) {
            // Slightly short.
            $sentence_score = 40 + ( ( $avg_sentence_length - 10 ) * 2 );
        }
        // 15-20 gets full 50 points.

        // Word length score (50 points max).
        // Ideal range: 4-6 characters per word.
        $word_score = 50;
        if ( $avg_word_length < 3 ) {
            $word_score = max( 0, 50 - ( ( 3 - $avg_word_length ) * 15 ) );
        } elseif ( $avg_word_length > 8 ) {
            $word_score = max( 0, 50 - ( ( $avg_word_length - 8 ) * 10 ) );
        } elseif ( $avg_word_length > 6 ) {
            $word_score = 50 - ( ( $avg_word_length - 6 ) * 5 );
        } elseif ( $avg_word_length < 4 ) {
            $word_score = 35 + ( ( $avg_word_length - 3 ) * 15 );
        }
        // 4-6 gets full 50 points.

        $total = (int) round( $sentence_score + $word_score );
        return max( 0, min( 100, $total ) );
    }

    /**
     * Get rating category from score.
     */
    private function get_rating( $score ) {
        if ( $score >= 80 ) {
            return 'good';
        }
        if ( $score >= 50 ) {
            return 'ok';
        }
        return 'poor';
    }

    /**
     * Get human-readable label from score.
     */
    private function get_label( $score ) {
        if ( $score >= 80 ) {
            return 'God lesbarhet';
        }
        if ( $score >= 50 ) {
            return 'Middels lesbarhet';
        }
        return 'Dårlig lesbarhet – vurder kortere setninger og enklere ord';
    }
}
