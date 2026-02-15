<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Return the appropriate string based on the content language setting.
 *
 * Usage: ai_seo_t( 'Norsk tekst', 'English text' )
 *
 * @param  string $nb Norwegian text (default).
 * @param  string $en English text.
 * @return string
 */
function ai_seo_t( $nb, $en ) {
    static $is_en = null;
    if ( null === $is_en ) {
        $options = get_option( 'ai_seo_options', array() );
        $is_en   = isset( $options['content_language'] ) && 'en' === $options['content_language'];
    }
    return $is_en ? $en : $nb;
}
