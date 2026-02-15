<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Adds SEO columns to post list views with inline editing.
 */
class AI_SEO_Bulk_Columns {

    public function init() {
        $post_types = get_post_types( array( 'public' => true ), 'names' );
        unset( $post_types['attachment'] );

        foreach ( $post_types as $post_type ) {
            add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_columns' ) );
            add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
        }

        add_action( 'wp_ajax_ai_seo_inline_save_meta', array( $this, 'ajax_save_meta' ) );
    }

    /**
     * Register the SEO columns.
     */
    public function add_columns( $columns ) {
        $new = array();

        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            // Insert SEO columns after the title column.
            if ( $key === 'title' ) {
                $new['ai_seo_title']       = 'SEO-tittel';
                $new['ai_seo_description'] = 'Metabeskrivelse';
                $new['ai_seo_score']       = 'SEO';
            }
        }

        return $new;
    }

    /**
     * Render column content for each post.
     */
    public function render_column( $column, $post_id ) {
        switch ( $column ) {
            case 'ai_seo_title':
                $value = get_post_meta( $post_id, '_ai_seo_meta_title', true );
                $display = $value ? mb_substr( $value, 0, 40 ) . ( mb_strlen( $value ) > 40 ? '…' : '' ) : '—';
                printf(
                    '<div class="ai-seo-inline-edit" data-post-id="%d" data-field="meta_title" data-max="70" title="Klikk for å redigere">'
                    . '<span class="ai-seo-inline-value %s">%s</span>'
                    . '<input type="text" class="ai-seo-inline-input" value="%s" maxlength="70" style="display:none;" />'
                    . '</div>',
                    esc_attr( $post_id ),
                    $value ? '' : 'ai-seo-inline-empty',
                    esc_html( $display ),
                    esc_attr( $value )
                );
                break;

            case 'ai_seo_description':
                $value = get_post_meta( $post_id, '_ai_seo_meta_description', true );
                printf(
                    '<div class="ai-seo-inline-edit" data-post-id="%d" data-field="meta_description" data-max="160" title="Klikk for å redigere">'
                    . '<span class="ai-seo-inline-value %s">%s</span>'
                    . '<input type="text" class="ai-seo-inline-input" value="%s" maxlength="160" style="display:none;" />'
                    . '</div>',
                    esc_attr( $post_id ),
                    $value ? '' : 'ai-seo-inline-empty',
                    esc_html( $value ? mb_substr( $value, 0, 60 ) . ( mb_strlen( $value ) > 60 ? '...' : '' ) : '—' ),
                    esc_attr( $value )
                );
                break;

            case 'ai_seo_score':
                $post           = get_post( $post_id );
                $meta_title     = get_post_meta( $post_id, '_ai_seo_meta_title', true );
                $meta_desc      = get_post_meta( $post_id, '_ai_seo_meta_description', true );
                $focus_keyword  = get_post_meta( $post_id, '_ai_seo_focus_keyword', true );
                $seo_score      = AI_SEO_Score::analyze( $post, $focus_keyword, $meta_title, $meta_desc );

                printf(
                    '<span class="ai-seo-col-score ai-seo-col-score-%s" title="%d/100">%d</span>',
                    esc_attr( $seo_score['rating'] ),
                    esc_attr( $seo_score['score'] ),
                    esc_html( $seo_score['score'] )
                );
                break;
        }
    }

    /**
     * AJAX handler for inline meta saves.
     */
    public function ajax_save_meta() {
        check_ajax_referer( 'ai_seo_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Ingen tilgang.' );
        }

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $field   = isset( $_POST['field'] ) ? sanitize_text_field( wp_unslash( $_POST['field'] ) ) : '';
        $value   = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';

        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( 'Ingen tilgang til dette innlegget.' );
        }

        $allowed_fields = array(
            'meta_title'       => '_ai_seo_meta_title',
            'meta_description' => '_ai_seo_meta_description',
        );

        if ( ! isset( $allowed_fields[ $field ] ) ) {
            wp_send_json_error( 'Ugyldig felt.' );
        }

        update_post_meta( $post_id, $allowed_fields[ $field ], $value );

        wp_send_json_success( array( 'value' => $value ) );
    }
}
