<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_SEO_Meta_Box {

    public function init() {
        add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
        add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );
    }

    public function register_meta_box() {
        $post_types = get_post_types( array( 'public' => true ), 'names' );

        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'ai_seo_meta_box',
                'AI SEO',
                array( $this, 'render_meta_box' ),
                $post_type,
                'normal',
                'high'
            );
        }
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'ai_seo_meta_box', 'ai_seo_meta_box_nonce' );

        $meta_title       = get_post_meta( $post->ID, '_ai_seo_meta_title', true );
        $meta_description = get_post_meta( $post->ID, '_ai_seo_meta_description', true );
        $focus_keyword    = get_post_meta( $post->ID, '_ai_seo_focus_keyword', true );

        $readability = new AI_SEO_Readability();
        $content     = wp_strip_all_tags( $post->post_content );
        $score_data  = $readability->analyze( $content );
        ?>
        <div class="ai-seo-meta-box">
            <!-- SEO Fields -->
            <div class="ai-seo-field">
                <label for="ai_seo_meta_title">SEO-tittel</label>
                <input type="text"
                       id="ai_seo_meta_title"
                       name="ai_seo_meta_title"
                       value="<?php echo esc_attr( $meta_title ); ?>"
                       class="large-text"
                       maxlength="70" />
                <span class="ai-seo-char-count" data-target="ai_seo_meta_title" data-max="70">
                    <?php echo esc_html( mb_strlen( $meta_title ) ); ?>/70
                </span>
            </div>

            <div class="ai-seo-field">
                <label for="ai_seo_meta_description">Metabeskrivelse</label>
                <textarea id="ai_seo_meta_description"
                          name="ai_seo_meta_description"
                          class="large-text"
                          rows="3"
                          maxlength="160"><?php echo esc_textarea( $meta_description ); ?></textarea>
                <span class="ai-seo-char-count" data-target="ai_seo_meta_description" data-max="160">
                    <?php echo esc_html( mb_strlen( $meta_description ) ); ?>/160
                </span>
            </div>

            <div class="ai-seo-field">
                <label for="ai_seo_focus_keyword">Fokus-søkeord</label>
                <input type="text"
                       id="ai_seo_focus_keyword"
                       name="ai_seo_focus_keyword"
                       value="<?php echo esc_attr( $focus_keyword ); ?>"
                       class="regular-text" />
            </div>

            <!-- SERP Preview -->
            <div class="ai-seo-serp-preview">
                <h4>Forhåndsvisning i søkeresultat</h4>
                <div class="ai-seo-serp">
                    <div class="ai-seo-serp-title" id="ai-seo-serp-title">
                        <?php echo esc_html( $meta_title ? $meta_title : $post->post_title ); ?>
                    </div>
                    <div class="ai-seo-serp-url">
                        <?php echo esc_url( get_permalink( $post->ID ) ); ?>
                    </div>
                    <div class="ai-seo-serp-desc" id="ai-seo-serp-desc">
                        <?php echo esc_html( $meta_description ? $meta_description : mb_substr( wp_strip_all_tags( $post->post_content ), 0, 160 ) ); ?>
                    </div>
                </div>
            </div>

            <!-- Readability -->
            <div class="ai-seo-readability">
                <h4>Lesbarhetsanalyse</h4>
                <?php if ( ! empty( $content ) ) : ?>
                    <div class="ai-seo-readability-score ai-seo-score-<?php echo esc_attr( $score_data['rating'] ); ?>">
                        <strong>Score: <?php echo esc_html( $score_data['score'] ); ?>/100</strong>
                        — <?php echo esc_html( $score_data['label'] ); ?>
                    </div>
                    <ul class="ai-seo-readability-details">
                        <li>Gjennomsnittlig setningslengde: <?php echo esc_html( $score_data['avg_sentence_length'] ); ?> ord</li>
                        <li>Gjennomsnittlig ordlengde: <?php echo esc_html( $score_data['avg_word_length'] ); ?> tegn</li>
                        <li>Antall setninger: <?php echo esc_html( $score_data['sentence_count'] ); ?></li>
                        <li>Antall ord: <?php echo esc_html( $score_data['word_count'] ); ?></li>
                    </ul>
                <?php else : ?>
                    <p class="description">Legg til innhold for å se lesbarhetsanalyse.</p>
                <?php endif; ?>
            </div>

            <!-- AI Actions -->
            <div class="ai-seo-ai-actions">
                <h4>AI-verktøy</h4>
                <div class="ai-seo-button-group">
                    <button type="button" class="button button-secondary" id="ai-seo-generate-desc" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                        Generer metabeskrivelse
                    </button>
                    <button type="button" class="button button-secondary" id="ai-seo-suggest-title" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                        Foreslå tittel
                    </button>
                    <button type="button" class="button button-secondary" id="ai-seo-analyze-keywords" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                        Analyser søkeord
                    </button>
                </div>
                <div class="ai-seo-spinner" id="ai-seo-spinner" style="display:none;">
                    <span class="spinner is-active"></span> Venter på AI-svar&hellip;
                </div>
                <div class="ai-seo-result" id="ai-seo-result" style="display:none;"></div>
                <div class="ai-seo-error" id="ai-seo-error" style="display:none;"></div>
            </div>
        </div>
        <?php
    }

    public function save_meta_box( $post_id, $post ) {
        if ( ! isset( $_POST['ai_seo_meta_box_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['ai_seo_meta_box_nonce'], 'ai_seo_meta_box' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_posts', $post_id ) ) {
            return;
        }

        $fields = array(
            'ai_seo_meta_title'       => '_ai_seo_meta_title',
            'ai_seo_meta_description' => '_ai_seo_meta_description',
            'ai_seo_focus_keyword'    => '_ai_seo_focus_keyword',
        );

        foreach ( $fields as $field_name => $meta_key ) {
            if ( isset( $_POST[ $field_name ] ) ) {
                $value = sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) );
                update_post_meta( $post_id, $meta_key, $value );
            }
        }
    }
}
