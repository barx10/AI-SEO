<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_SEO_Redirects {

    const TABLE_NAME = 'ai_seo_redirects';

    public function init() {
        add_action( 'template_redirect', array( $this, 'handle_redirect' ), 1 );
    }

    /**
     * Create the redirects database table.
     */
    public static function create_table() {
        global $wpdb;

        $table_name      = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_url varchar(500) NOT NULL,
            target_url varchar(500) NOT NULL,
            type int(3) NOT NULL DEFAULT 301,
            hits bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY source_url (source_url(191))
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Check if current request matches a redirect and perform it.
     */
    public function handle_redirect() {
        if ( is_admin() ) {
            return;
        }

        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE_NAME;
        $request = wp_unslash( $_SERVER['REQUEST_URI'] ?? '' );
        $path    = '/' . ltrim( wp_parse_url( $request, PHP_URL_PATH ), '/' );

        $redirect = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table WHERE source_url = %s LIMIT 1", $path )
        );

        if ( ! $redirect ) {
            return;
        }

        // Increment hit counter.
        $wpdb->update( $table, array( 'hits' => $redirect->hits + 1 ), array( 'id' => $redirect->id ) );

        $type = in_array( (int) $redirect->type, array( 301, 302 ), true ) ? (int) $redirect->type : 301;
        wp_redirect( $redirect->target_url, $type );
        exit;
    }

    /**
     * Get all redirects, optionally paginated.
     */
    public static function get_all( $per_page = 50, $page = 1 ) {
        global $wpdb;
        $table  = $wpdb->prefix . self::TABLE_NAME;
        $offset = ( $page - 1 ) * $per_page;

        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset )
        );
    }

    /**
     * Count total redirects.
     */
    public static function count_all() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
    }

    /**
     * Add a redirect.
     */
    public static function add( $source, $target, $type = 301 ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        $source = '/' . ltrim( sanitize_text_field( $source ), '/' );
        $target = esc_url_raw( $target );
        $type   = in_array( (int) $type, array( 301, 302 ), true ) ? (int) $type : 301;

        // Check for duplicate source.
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE source_url = %s", $source ) );
        if ( $exists ) {
            return new WP_Error( 'duplicate', 'En omdirigering med denne kilde-URL-en finnes allerede.' );
        }

        $wpdb->insert( $table, array(
            'source_url' => $source,
            'target_url' => $target,
            'type'       => $type,
        ), array( '%s', '%s', '%d' ) );

        return $wpdb->insert_id;
    }

    /**
     * Delete a redirect by ID.
     */
    public static function delete( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        return $wpdb->delete( $table, array( 'id' => absint( $id ) ), array( '%d' ) );
    }

    /**
     * Detect redirect chains and loops.
     *
     * Returns an array of issues, each with:
     *   'type'  => 'chain' | 'loop'
     *   'path'  => array of source URLs forming the chain/loop
     *   'ids'   => array of redirect IDs involved
     */
    public static function detect_chains() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        $redirects = $wpdb->get_results( "SELECT * FROM $table ORDER BY id ASC" );
        if ( empty( $redirects ) ) {
            return array();
        }

        // Build lookup: source_url => redirect row.
        $by_source = array();
        foreach ( $redirects as $r ) {
            $by_source[ $r->source_url ] = $r;
        }

        // Also index by target path for matching targets that are relative paths.
        $issues  = array();
        $checked = array();

        foreach ( $redirects as $r ) {
            if ( isset( $checked[ $r->source_url ] ) ) {
                continue;
            }

            $chain   = array();
            $ids     = array();
            $visited = array();
            $current = $r;

            while ( $current ) {
                if ( isset( $visited[ $current->source_url ] ) ) {
                    // Loop detected.
                    $chain[] = $current->source_url;
                    $ids[]   = $current->id;
                    $issues[] = array(
                        'type' => 'loop',
                        'path' => $chain,
                        'ids'  => $ids,
                    );
                    break;
                }

                $visited[ $current->source_url ] = true;
                $checked[ $current->source_url ]  = true;
                $chain[] = $current->source_url;
                $ids[]   = $current->id;

                // Normalize target to a path for comparison.
                $target_path = wp_parse_url( $current->target_url, PHP_URL_PATH );
                if ( $target_path ) {
                    $target_path = '/' . ltrim( $target_path, '/' );
                }

                if ( $target_path && isset( $by_source[ $target_path ] ) ) {
                    $current = $by_source[ $target_path ];
                } else {
                    $current = null;
                }

                // If chain has more than 1 hop and we ended (no loop), it's a chain.
                if ( ! $current && count( $chain ) > 1 ) {
                    $issues[] = array(
                        'type' => 'chain',
                        'path' => $chain,
                        'ids'  => $ids,
                    );
                }
            }
        }

        return $issues;
    }
}
