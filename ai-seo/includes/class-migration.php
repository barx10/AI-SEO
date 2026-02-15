<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Metadata migration from Yoast SEO and Rank Math.
 */
class AI_SEO_Migration {

    /**
     * Meta key mapping: source plugin => AI SEO.
     */
    private static $yoast_map = array(
        '_yoast_wpseo_title'     => '_ai_seo_meta_title',
        '_yoast_wpseo_metadesc'  => '_ai_seo_meta_description',
        '_yoast_wpseo_focuskw'   => '_ai_seo_focus_keyword',
    );

    private static $rankmath_map = array(
        'rank_math_title'       => '_ai_seo_meta_title',
        'rank_math_description' => '_ai_seo_meta_description',
        'rank_math_focus_keyword' => '_ai_seo_focus_keyword',
    );

    /**
     * Detect which SEO plugins have data in the database.
     *
     * @return array Associative array with 'yoast' and 'rankmath' counts.
     */
    public static function detect_plugins() {
        global $wpdb;

        $yoast_count = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM $wpdb->postmeta
             WHERE meta_key IN ('_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_focuskw')
             AND meta_value != ''"
        );

        $rankmath_count = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM $wpdb->postmeta
             WHERE meta_key IN ('rank_math_title', 'rank_math_description', 'rank_math_focus_keyword')
             AND meta_value != ''"
        );

        return array(
            'yoast'    => $yoast_count,
            'rankmath' => $rankmath_count,
        );
    }

    /**
     * Run migration from a given source.
     *
     * @param string $source   'yoast' or 'rankmath'.
     * @param bool   $overwrite Whether to overwrite existing AI SEO data.
     * @return array Result with 'migrated' count and 'skipped' count.
     */
    public static function run( $source, $overwrite = false ) {
        if ( 'yoast' === $source ) {
            return self::migrate_yoast( $overwrite );
        }

        if ( 'rankmath' === $source ) {
            return self::migrate_rankmath( $overwrite );
        }

        return array( 'migrated' => 0, 'skipped' => 0, 'error' => 'Ukjent kilde.' );
    }

    /**
     * Migrate from Yoast SEO.
     */
    private static function migrate_yoast( $overwrite ) {
        global $wpdb;

        $migrated = 0;
        $skipped  = 0;

        // Get all post IDs with Yoast data.
        $post_ids = $wpdb->get_col(
            "SELECT DISTINCT post_id FROM $wpdb->postmeta
             WHERE meta_key IN ('_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_focuskw')
             AND meta_value != ''"
        );

        foreach ( $post_ids as $post_id ) {
            $post_id = (int) $post_id;
            $changed = false;

            // Map standard fields.
            foreach ( self::$yoast_map as $yoast_key => $ai_seo_key ) {
                $value = get_post_meta( $post_id, $yoast_key, true );
                if ( empty( $value ) ) {
                    continue;
                }

                // Strip Yoast variable placeholders like %%title%%, %%sep%%, etc.
                $value = self::strip_yoast_variables( $value );
                if ( empty( $value ) ) {
                    continue;
                }

                $existing = get_post_meta( $post_id, $ai_seo_key, true );
                if ( ! empty( $existing ) && ! $overwrite ) {
                    continue;
                }

                update_post_meta( $post_id, $ai_seo_key, sanitize_text_field( $value ) );
                $changed = true;
            }

            // Robots meta.
            $robots = array();
            $noindex  = get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true );
            $nofollow = get_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', true );

            if ( '1' === $noindex ) {
                $robots[] = 'noindex';
            }
            if ( '1' === $nofollow ) {
                $robots[] = 'nofollow';
            }

            if ( ! empty( $robots ) ) {
                $existing_robots = get_post_meta( $post_id, '_ai_seo_robots_meta', true );
                if ( empty( $existing_robots ) || $overwrite ) {
                    update_post_meta( $post_id, '_ai_seo_robots_meta', $robots );
                    $changed = true;
                }
            }

            // Social image.
            $social_image = get_post_meta( $post_id, '_yoast_wpseo_opengraph-image-id', true );
            if ( ! empty( $social_image ) ) {
                $existing_social = get_post_meta( $post_id, '_ai_seo_social_image_id', true );
                if ( empty( $existing_social ) || $overwrite ) {
                    update_post_meta( $post_id, '_ai_seo_social_image_id', absint( $social_image ) );
                    $changed = true;
                }
            }

            // Cornerstone content.
            $cornerstone = get_post_meta( $post_id, '_yoast_wpseo_is_cornerstone', true );
            if ( '1' === $cornerstone ) {
                $existing_corner = get_post_meta( $post_id, '_ai_seo_cornerstone', true );
                if ( empty( $existing_corner ) || $overwrite ) {
                    update_post_meta( $post_id, '_ai_seo_cornerstone', '1' );
                    $changed = true;
                }
            }

            if ( $changed ) {
                $migrated++;
            } else {
                $skipped++;
            }
        }

        return array( 'migrated' => $migrated, 'skipped' => $skipped );
    }

    /**
     * Migrate from Rank Math.
     */
    private static function migrate_rankmath( $overwrite ) {
        global $wpdb;

        $migrated = 0;
        $skipped  = 0;

        $post_ids = $wpdb->get_col(
            "SELECT DISTINCT post_id FROM $wpdb->postmeta
             WHERE meta_key IN ('rank_math_title', 'rank_math_description', 'rank_math_focus_keyword')
             AND meta_value != ''"
        );

        foreach ( $post_ids as $post_id ) {
            $post_id = (int) $post_id;
            $changed = false;

            // Map standard fields.
            foreach ( self::$rankmath_map as $rm_key => $ai_seo_key ) {
                $value = get_post_meta( $post_id, $rm_key, true );
                if ( empty( $value ) ) {
                    continue;
                }

                // Strip Rank Math variable placeholders like %title%, %sep%, etc.
                $value = self::strip_rankmath_variables( $value );
                if ( empty( $value ) ) {
                    continue;
                }

                // Rank Math focus keyword can be comma-separated; take the first one.
                if ( 'rank_math_focus_keyword' === $rm_key && strpos( $value, ',' ) !== false ) {
                    $value = trim( explode( ',', $value )[0] );
                }

                $existing = get_post_meta( $post_id, $ai_seo_key, true );
                if ( ! empty( $existing ) && ! $overwrite ) {
                    continue;
                }

                update_post_meta( $post_id, $ai_seo_key, sanitize_text_field( $value ) );
                $changed = true;
            }

            // Robots meta.
            $rm_robots = get_post_meta( $post_id, 'rank_math_robots', true );
            if ( ! empty( $rm_robots ) && is_array( $rm_robots ) ) {
                $allowed = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet' );
                $robots  = array_intersect( $rm_robots, $allowed );

                if ( ! empty( $robots ) ) {
                    $existing_robots = get_post_meta( $post_id, '_ai_seo_robots_meta', true );
                    if ( empty( $existing_robots ) || $overwrite ) {
                        update_post_meta( $post_id, '_ai_seo_robots_meta', array_values( $robots ) );
                        $changed = true;
                    }
                }
            }

            // Social image.
            $social_image = get_post_meta( $post_id, 'rank_math_facebook_image_id', true );
            if ( ! empty( $social_image ) ) {
                $existing_social = get_post_meta( $post_id, '_ai_seo_social_image_id', true );
                if ( empty( $existing_social ) || $overwrite ) {
                    update_post_meta( $post_id, '_ai_seo_social_image_id', absint( $social_image ) );
                    $changed = true;
                }
            }

            // Pillar / cornerstone content.
            $pillar = get_post_meta( $post_id, 'rank_math_pillar_content', true );
            if ( 'on' === $pillar || '1' === $pillar ) {
                $existing_corner = get_post_meta( $post_id, '_ai_seo_cornerstone', true );
                if ( empty( $existing_corner ) || $overwrite ) {
                    update_post_meta( $post_id, '_ai_seo_cornerstone', '1' );
                    $changed = true;
                }
            }

            if ( $changed ) {
                $migrated++;
            } else {
                $skipped++;
            }
        }

        return array( 'migrated' => $migrated, 'skipped' => $skipped );
    }

    /**
     * Strip Yoast variable placeholders.
     * Patterns like %%title%%, %%sep%%, %%sitename%%, etc.
     */
    private static function strip_yoast_variables( $value ) {
        $cleaned = preg_replace( '/%%[a-z_]+%%/i', '', $value );
        return trim( preg_replace( '/\s+/', ' ', $cleaned ) );
    }

    /**
     * Strip Rank Math variable placeholders.
     * Patterns like %title%, %sep%, %sitename%, etc.
     */
    private static function strip_rankmath_variables( $value ) {
        $cleaned = preg_replace( '/%[a-z_]+%/i', '', $value );
        return trim( preg_replace( '/\s+/', ' ', $cleaned ) );
    }
}
