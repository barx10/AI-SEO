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
        unset( $post_types['attachment'] );

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
        $robots_meta      = get_post_meta( $post->ID, '_ai_seo_robots_meta', true );
        $cornerstone      = get_post_meta( $post->ID, '_ai_seo_cornerstone', true );
        $schema_type      = get_post_meta( $post->ID, '_ai_seo_schema_type', true );
        $social_image_id  = get_post_meta( $post->ID, '_ai_seo_social_image_id', true );

        if ( ! is_array( $robots_meta ) ) {
            $robots_meta = array();
        }

        // Readability analysis.
        $readability = new AI_SEO_Readability();
        $content     = wp_strip_all_tags( $post->post_content );
        $score_data  = $readability->analyze( $content );

        // SEO score.
        $seo_score = AI_SEO_Score::analyze( $post, $focus_keyword, $meta_title, $meta_description );

        // Social image URL.
        $social_image_url = '';
        if ( $social_image_id ) {
            $img_data = wp_get_attachment_image_src( $social_image_id, 'medium' );
            if ( $img_data ) {
                $social_image_url = $img_data[0];
            }
        }
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

            <!-- Robots Meta -->
            <div class="ai-seo-field">
                <label>Robots-metatagger</label>
                <div class="ai-seo-checkbox-group">
                    <label>
                        <input type="checkbox" name="ai_seo_robots_meta[]" value="noindex" <?php checked( in_array( 'noindex', $robots_meta, true ) ); ?> />
                        noindex
                        <span class="ai-seo-tooltip" data-tip="Siden vil ikke dukke opp i Google-s&oslash;k. Bruk dette p&aring; sider som ikke trenger organisk trafikk, f.eks. takkesider eller interne landingssider.">?</span>
                    </label>
                    <label>
                        <input type="checkbox" name="ai_seo_robots_meta[]" value="nofollow" <?php checked( in_array( 'nofollow', $robots_meta, true ) ); ?> />
                        nofollow
                        <span class="ai-seo-tooltip" data-tip="S&oslash;kemotorer vil ikke f&oslash;lge lenker p&aring; denne siden. Lenke-juice overf&oslash;res ikke til sidene du lenker til herfra.">?</span>
                    </label>
                    <label>
                        <input type="checkbox" name="ai_seo_robots_meta[]" value="noarchive" <?php checked( in_array( 'noarchive', $robots_meta, true ) ); ?> />
                        noarchive
                        <span class="ai-seo-tooltip" data-tip="Google vil ikke vise &laquo;Hurtigbufret&raquo;-lenken i s&oslash;keresultatet. Nyttig for innhold som endres ofte.">?</span>
                    </label>
                    <label>
                        <input type="checkbox" name="ai_seo_robots_meta[]" value="nosnippet" <?php checked( in_array( 'nosnippet', $robots_meta, true ) ); ?> />
                        nosnippet
                        <span class="ai-seo-tooltip" data-tip="Ingen tekstutdrag vises under tittelen i s&oslash;keresultatet. Siden f&aring;r kun tittel og URL &mdash; kan redusere klikkrate betraktelig.">?</span>
                    </label>
                </div>
            </div>

            <!-- Cornerstone Content -->
            <div class="ai-seo-field">
                <label>
                    <input type="checkbox" name="ai_seo_cornerstone" value="1" <?php checked( $cornerstone, '1' ); ?> />
                    <strong>Cornerstone-innhold</strong>
                    <span class="description"> – Marker som viktig innhold for intern lenking</span>
                </label>
            </div>

            <!-- Schema Type -->
            <div class="ai-seo-field">
                <label for="ai_seo_schema_type">Schema-type</label>
                <select name="ai_seo_schema_type" id="ai_seo_schema_type">
                    <option value="" <?php selected( $schema_type, '' ); ?>>Standard (Article)</option>
                    <option value="faq" <?php selected( $schema_type, 'faq' ); ?>>FAQPage (bruker H3 som spørsmål)</option>
                    <option value="howto" <?php selected( $schema_type, 'howto' ); ?>>HowTo (bruker H3 som steg)</option>
                </select>
            </div>

            <!-- Social Image -->
            <div class="ai-seo-field">
                <label>Sosialt bilde (OpenGraph / Twitter)</label>
                <div class="ai-seo-social-image">
                    <input type="hidden" name="ai_seo_social_image_id" id="ai_seo_social_image_id" value="<?php echo esc_attr( $social_image_id ); ?>" />
                    <?php if ( $social_image_url ) : ?>
                        <div id="ai-seo-social-image-preview">
                            <img src="<?php echo esc_url( $social_image_url ); ?>" style="max-width:300px;height:auto;" />
                        </div>
                    <?php else : ?>
                        <div id="ai-seo-social-image-preview" style="display:none;"></div>
                    <?php endif; ?>
                    <button type="button" class="button" id="ai-seo-upload-social-image">Velg bilde</button>
                    <button type="button" class="button" id="ai-seo-remove-social-image" <?php echo $social_image_id ? '' : 'style="display:none;"'; ?>>Fjern bilde</button>
                    <p class="description">Overskriver fremhevet bilde for sosiale medier. Anbefalt: 1200x630 piksler.</p>
                </div>
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

            <!-- SEO Score / Checklist -->
            <div class="ai-seo-seo-score">
                <h4>
                    SEO-analyse
                    <button type="button" class="button button-small" id="ai-seo-refresh-score" data-post-id="<?php echo esc_attr( $post->ID ); ?>" style="margin-left:10px;">
                        Oppdater analyse
                    </button>
                </h4>
                <div class="ai-seo-readability-score ai-seo-score-<?php echo esc_attr( $seo_score['rating'] ); ?>" id="ai-seo-score-badge">
                    <strong>SEO-score: <span id="ai-seo-score-value"><?php echo esc_html( $seo_score['score'] ); ?></span>/100</strong>
                </div>
                <ul class="ai-seo-checklist" id="ai-seo-checklist">
                    <?php foreach ( $seo_score['checks'] as $check ) : ?>
                        <li class="ai-seo-check-<?php echo $check['pass'] ? 'pass' : 'fail'; ?>">
                            <span class="ai-seo-check-icon"><?php echo $check['pass'] ? '&#10004;' : '&#10008;'; ?></span>
                            <?php echo esc_html( $check['label'] ); ?>
                            <?php if ( ! empty( $check['detail'] ) ) : ?>
                                <span class="ai-seo-check-detail">(<?php echo esc_html( $check['detail'] ); ?>)</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Readability -->
            <div class="ai-seo-readability">
                <h4>Lesbarhetsanalyse</h4>
                <?php if ( ! empty( $content ) ) : ?>
                    <div class="ai-seo-readability-score ai-seo-score-<?php echo esc_attr( $score_data['rating'] ); ?>">
                        <strong>Score: <?php echo esc_html( $score_data['score'] ); ?>/100</strong>
                        — <?php echo esc_html( $score_data['label'] ); ?>
                        <?php if ( isset( $score_data['flesch_kincaid'] ) ) : ?>
                            | Flesch: <?php echo esc_html( $score_data['flesch_kincaid'] ); ?>/100
                        <?php endif; ?>
                    </div>
                    <ul class="ai-seo-readability-details">
                        <li>Gjennomsnittlig setningslengde: <?php echo esc_html( $score_data['avg_sentence_length'] ); ?> ord</li>
                        <li>Gjennomsnittlig ordlengde: <?php echo esc_html( $score_data['avg_word_length'] ); ?> tegn</li>
                        <li>Antall setninger: <?php echo esc_html( $score_data['sentence_count'] ); ?></li>
                        <li>Antall ord: <?php echo esc_html( $score_data['word_count'] ); ?></li>
                        <?php if ( isset( $score_data['passive_percentage'] ) ) : ?>
                            <li>Passiv stemme: <?php echo esc_html( $score_data['passive_percentage'] ); ?> %</li>
                        <?php endif; ?>
                        <?php if ( isset( $score_data['transition_percentage'] ) ) : ?>
                            <li>Overgangsord: <?php echo esc_html( $score_data['transition_percentage'] ); ?> %</li>
                        <?php endif; ?>
                        <?php if ( isset( $score_data['long_sentences_pct'] ) ) : ?>
                            <li>Lange setninger (>25 ord): <?php echo esc_html( $score_data['long_sentences_pct'] ); ?> %</li>
                        <?php endif; ?>
                    </ul>
                    <?php if ( ! empty( $score_data['suggestions'] ) ) : ?>
                        <div class="ai-seo-suggestions">
                            <strong>Forbedringsforslag:</strong>
                            <ul>
                                <?php foreach ( $score_data['suggestions'] as $suggestion ) : ?>
                                    <li><?php echo esc_html( $suggestion ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <button type="button" class="button button-small" id="ai-seo-highlight-readability" data-post-id="<?php echo esc_attr( $post->ID ); ?>" style="margin-top:10px;">
                        Vis i teksten
                    </button>
                    <div class="ai-seo-highlight-panel" id="ai-seo-highlight-panel" style="display:none;">
                        <div class="ai-seo-highlight-legend">
                            <span class="ai-seo-hl-long">Lang setning (&gt;25 ord)</span>
                            <span class="ai-seo-hl-passive">Passiv stemme</span>
                        </div>
                        <div class="ai-seo-highlight-content" id="ai-seo-highlight-content"></div>
                    </div>
                <?php else : ?>
                    <p class="description">Legg til innhold for å se lesbarhetsanalyse.</p>
                <?php endif; ?>
            </div>

            <!-- AI Actions -->
            <div class="ai-seo-ai-actions">
                <h4>AI-verktøy</h4>
                <p class="ai-seo-workflow-hint">Skriv innholdet ferdig først, så bruk knappene under til å fylle ut SEO-feltene automatisk.</p>
                <div class="ai-seo-button-group">
                    <button type="button" class="button button-secondary" id="ai-seo-suggest-keyword" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                        Foreslå fokusord
                    </button>
                    <button type="button" class="button button-secondary" id="ai-seo-suggest-title" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                        Foreslå tittel
                    </button>
                    <button type="button" class="button button-secondary" id="ai-seo-generate-desc" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                        Generer metabeskrivelse
                    </button>
                </div>
                <div class="ai-seo-button-group ai-seo-button-group-secondary">
                    <button type="button" class="button button-secondary" id="ai-seo-analyze-keywords" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                        Analyser søkeord
                    </button>
                    <button type="button" class="button button-secondary" id="ai-seo-suggest-links" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                        Foreslå interne lenker
                    </button>
                </div>
                <div class="ai-seo-spinner" id="ai-seo-spinner" style="display:none;">
                    <span class="spinner is-active"></span> Venter på AI-svar&hellip;
                </div>
                <div class="ai-seo-result" id="ai-seo-result" style="display:none;"></div>
                <div class="ai-seo-error" id="ai-seo-error" style="display:none;"></div>
            </div>

            <!-- Cornerstone Links -->
            <?php
            $cornerstone_posts = get_posts( array(
                'post_type'      => 'any',
                'post_status'    => 'publish',
                'meta_key'       => '_ai_seo_cornerstone',
                'meta_value'     => '1',
                'posts_per_page' => 10,
                'exclude'        => array( $post->ID ),
            ) );
            ?>
            <?php if ( ! empty( $cornerstone_posts ) ) : ?>
                <div class="ai-seo-cornerstone-links">
                    <h4>Cornerstone-innhold du kan lenke til</h4>
                    <ul>
                        <?php foreach ( $cornerstone_posts as $cp ) : ?>
                            <li>
                                <a href="<?php echo esc_url( get_permalink( $cp->ID ) ); ?>" target="_blank">
                                    <?php echo esc_html( $cp->post_title ); ?>
                                </a>
                                <code class="ai-seo-copy-url" title="Klikk for å kopiere"><?php echo esc_url( get_permalink( $cp->ID ) ); ?></code>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
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

        // Text fields.
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

        // Robots meta (array of checkboxes).
        if ( isset( $_POST['ai_seo_robots_meta'] ) && is_array( $_POST['ai_seo_robots_meta'] ) ) {
            $allowed = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet' );
            $robots  = array_intersect( array_map( 'sanitize_text_field', $_POST['ai_seo_robots_meta'] ), $allowed );
            update_post_meta( $post_id, '_ai_seo_robots_meta', $robots );
        } else {
            delete_post_meta( $post_id, '_ai_seo_robots_meta' );
        }

        // Cornerstone.
        if ( ! empty( $_POST['ai_seo_cornerstone'] ) ) {
            update_post_meta( $post_id, '_ai_seo_cornerstone', '1' );
        } else {
            delete_post_meta( $post_id, '_ai_seo_cornerstone' );
        }

        // Schema type.
        if ( isset( $_POST['ai_seo_schema_type'] ) ) {
            $allowed_types = array( '', 'faq', 'howto' );
            $type = sanitize_text_field( $_POST['ai_seo_schema_type'] );
            if ( in_array( $type, $allowed_types, true ) ) {
                update_post_meta( $post_id, '_ai_seo_schema_type', $type );
            }
        }

        // Social image.
        if ( isset( $_POST['ai_seo_social_image_id'] ) ) {
            $image_id = absint( $_POST['ai_seo_social_image_id'] );
            if ( $image_id ) {
                update_post_meta( $post_id, '_ai_seo_social_image_id', $image_id );
            } else {
                delete_post_meta( $post_id, '_ai_seo_social_image_id' );
            }
        }
    }
}
