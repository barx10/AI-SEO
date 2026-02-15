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
                <label for="ai_seo_meta_title"><?php echo ai_seo_t('SEO-tittel', 'SEO Title'); ?></label>
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
                <label for="ai_seo_meta_description"><?php echo ai_seo_t('Metabeskrivelse', 'Meta Description'); ?></label>
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
                <label for="ai_seo_focus_keyword"><?php echo ai_seo_t('Fokus-søkeord', 'Focus Keyword'); ?></label>
                <input type="text"
                       id="ai_seo_focus_keyword"
                       name="ai_seo_focus_keyword"
                       value="<?php echo esc_attr( $focus_keyword ); ?>"
                       class="regular-text" />
            </div>

            <!-- Robots Meta -->
            <div class="ai-seo-field">
                <label><?php echo ai_seo_t('Robots-metatagger', 'Robots Meta Tags'); ?></label>
                <div class="ai-seo-checkbox-group">
                    <label>
                        <input type="checkbox" name="ai_seo_robots_meta[]" value="noindex" <?php checked( in_array( 'noindex', $robots_meta, true ) ); ?> />
                        noindex
                        <span class="ai-seo-tooltip" data-tip="<?php echo ai_seo_t('Siden vil ikke dukke opp i Google-s&oslash;k. Bruk dette p&aring; sider som ikke trenger organisk trafikk, f.eks. takkesider eller interne landingssider.', 'The page will not appear in Google search. Use this for pages that don&rsquo;t need organic traffic, e.g. thank-you pages or internal landing pages.'); ?>">?</span>
                    </label>
                    <label>
                        <input type="checkbox" name="ai_seo_robots_meta[]" value="nofollow" <?php checked( in_array( 'nofollow', $robots_meta, true ) ); ?> />
                        nofollow
                        <span class="ai-seo-tooltip" data-tip="<?php echo ai_seo_t('S&oslash;kemotorer vil ikke f&oslash;lge lenker p&aring; denne siden. Lenke-juice overf&oslash;res ikke til sidene du lenker til herfra.', 'Search engines will not follow links on this page. Link juice will not be passed to the pages you link to from here.'); ?>">?</span>
                    </label>
                    <label>
                        <input type="checkbox" name="ai_seo_robots_meta[]" value="noarchive" <?php checked( in_array( 'noarchive', $robots_meta, true ) ); ?> />
                        noarchive
                        <span class="ai-seo-tooltip" data-tip="<?php echo ai_seo_t('Google vil ikke vise &laquo;Hurtigbufret&raquo;-lenken i s&oslash;keresultatet. Nyttig for innhold som endres ofte.', 'Google will not show a &laquo;Cached&raquo; link in the search result. Useful for content that changes frequently.'); ?>">?</span>
                    </label>
                    <label>
                        <input type="checkbox" name="ai_seo_robots_meta[]" value="nosnippet" <?php checked( in_array( 'nosnippet', $robots_meta, true ) ); ?> />
                        nosnippet
                        <span class="ai-seo-tooltip" data-tip="<?php echo ai_seo_t('Ingen tekstutdrag vises under tittelen i s&oslash;keresultatet. Siden f&aring;r kun tittel og URL &mdash; kan redusere klikkrate betraktelig.', 'No text snippet will be shown below the title in search results. The page will only get a title and URL &mdash; can reduce click-through rate significantly.'); ?>">?</span>
                    </label>
                </div>
            </div>

            <!-- Cornerstone Content -->
            <div class="ai-seo-field">
                <label>
                    <input type="checkbox" name="ai_seo_cornerstone" value="1" <?php checked( $cornerstone, '1' ); ?> />
                    <strong><?php echo ai_seo_t('Cornerstone-innhold', 'Cornerstone Content'); ?></strong>
                    <span class="description"><?php echo ai_seo_t(' – Marker som viktig innhold for intern lenking', ' – Mark as important content for internal linking'); ?></span>
                </label>
            </div>

            <!-- Schema Type -->
            <div class="ai-seo-field">
                <label for="ai_seo_schema_type"><?php echo ai_seo_t('Schema-type', 'Schema Type'); ?></label>
                <select name="ai_seo_schema_type" id="ai_seo_schema_type">
                    <option value="" <?php selected( $schema_type, '' ); ?>><?php echo ai_seo_t('Standard (Article)', 'Default (Article)'); ?></option>
                    <option value="faq" <?php selected( $schema_type, 'faq' ); ?>><?php echo ai_seo_t('FAQPage (bruker H3 som spørsmål)', 'FAQPage (uses H3 as questions)'); ?></option>
                    <option value="howto" <?php selected( $schema_type, 'howto' ); ?>><?php echo ai_seo_t('HowTo (bruker H3 som steg)', 'HowTo (uses H3 as steps)'); ?></option>
                </select>
            </div>

            <!-- Social Image -->
            <div class="ai-seo-field">
                <label><?php echo ai_seo_t('Sosialt bilde (OpenGraph / Twitter)', 'Social Image (OpenGraph / Twitter)'); ?></label>
                <div class="ai-seo-social-image">
                    <input type="hidden" name="ai_seo_social_image_id" id="ai_seo_social_image_id" value="<?php echo esc_attr( $social_image_id ); ?>" />
                    <?php if ( $social_image_url ) : ?>
                        <div id="ai-seo-social-image-preview">
                            <img src="<?php echo esc_url( $social_image_url ); ?>" style="max-width:300px;height:auto;" />
                        </div>
                    <?php else : ?>
                        <div id="ai-seo-social-image-preview" style="display:none;"></div>
                    <?php endif; ?>
                    <button type="button" class="button" id="ai-seo-upload-social-image"><?php echo ai_seo_t('Velg bilde', 'Select Image'); ?></button>
                    <button type="button" class="button" id="ai-seo-remove-social-image" <?php echo $social_image_id ? '' : 'style="display:none;"'; ?>><?php echo ai_seo_t('Fjern bilde', 'Remove Image'); ?></button>
                    <p class="description"><?php echo ai_seo_t('Overskriver fremhevet bilde for sosiale medier. Anbefalt: 1200x630 piksler.', 'Overrides the featured image for social media. Recommended: 1200x630 pixels.'); ?></p>
                </div>
            </div>

            <!-- SERP Preview -->
            <div class="ai-seo-serp-preview">
                <h4><?php echo ai_seo_t('Forhåndsvisning i søkeresultat', 'Search Result Preview'); ?></h4>
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
                    <?php echo ai_seo_t('SEO-analyse', 'SEO Analysis'); ?>
                    <button type="button" class="button button-small" id="ai-seo-refresh-score" data-post-id="<?php echo esc_attr( $post->ID ); ?>" style="margin-left:10px;">
                        <?php echo ai_seo_t('Oppdater analyse', 'Refresh Analysis'); ?>
                    </button>
                </h4>
                <div class="ai-seo-readability-score ai-seo-score-<?php echo esc_attr( $seo_score['rating'] ); ?>" id="ai-seo-score-badge">
                    <strong><?php echo ai_seo_t('SEO-score:', 'SEO Score:'); ?> <span id="ai-seo-score-value"><?php echo esc_html( $seo_score['score'] ); ?></span>/100</strong>
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
                <h4><?php echo ai_seo_t('Lesbarhetsanalyse', 'Readability Analysis'); ?></h4>
                <?php if ( ! empty( $content ) ) : ?>
                    <div class="ai-seo-readability-score ai-seo-score-<?php echo esc_attr( $score_data['rating'] ); ?>">
                        <strong>Score: <?php echo esc_html( $score_data['score'] ); ?>/100</strong>
                        — <?php echo esc_html( $score_data['label'] ); ?>
                        <?php if ( isset( $score_data['flesch_kincaid'] ) ) : ?>
                            | Flesch: <?php echo esc_html( $score_data['flesch_kincaid'] ); ?>/100
                        <?php endif; ?>
                    </div>
                    <ul class="ai-seo-readability-details">
                        <li><?php echo ai_seo_t('Gjennomsnittlig setningslengde:', 'Average sentence length:'); ?> <?php echo esc_html( $score_data['avg_sentence_length'] ); ?> <?php echo ai_seo_t('ord', 'words'); ?></li>
                        <li><?php echo ai_seo_t('Gjennomsnittlig ordlengde:', 'Average word length:'); ?> <?php echo esc_html( $score_data['avg_word_length'] ); ?> <?php echo ai_seo_t('tegn', 'chars'); ?></li>
                        <li><?php echo ai_seo_t('Antall setninger:', 'Sentence count:'); ?> <?php echo esc_html( $score_data['sentence_count'] ); ?></li>
                        <li><?php echo ai_seo_t('Antall ord:', 'Word count:'); ?> <?php echo esc_html( $score_data['word_count'] ); ?></li>
                        <?php if ( isset( $score_data['passive_percentage'] ) ) : ?>
                            <li><?php echo ai_seo_t('Passiv stemme:', 'Passive voice:'); ?> <?php echo esc_html( $score_data['passive_percentage'] ); ?> %</li>
                        <?php endif; ?>
                        <?php if ( isset( $score_data['transition_percentage'] ) ) : ?>
                            <li><?php echo ai_seo_t('Overgangsord:', 'Transition words:'); ?> <?php echo esc_html( $score_data['transition_percentage'] ); ?> %</li>
                        <?php endif; ?>
                        <?php if ( isset( $score_data['long_sentences_pct'] ) ) : ?>
                            <li><?php echo ai_seo_t('Lange setninger (>25 ord):', 'Long sentences (>25 words):'); ?> <?php echo esc_html( $score_data['long_sentences_pct'] ); ?> %</li>
                        <?php endif; ?>
                    </ul>
                    <?php if ( ! empty( $score_data['suggestions'] ) ) : ?>
                        <div class="ai-seo-suggestions">
                            <strong><?php echo ai_seo_t('Forbedringsforslag:', 'Suggestions for improvement:'); ?></strong>
                            <ul>
                                <?php foreach ( $score_data['suggestions'] as $suggestion ) : ?>
                                    <li><?php echo esc_html( $suggestion ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <button type="button" class="button button-small" id="ai-seo-highlight-readability" data-post-id="<?php echo esc_attr( $post->ID ); ?>" style="margin-top:10px;">
                        <?php echo ai_seo_t('Vis i teksten', 'Show in text'); ?>
                    </button>
                    <div class="ai-seo-highlight-panel" id="ai-seo-highlight-panel" style="display:none;">
                        <div class="ai-seo-highlight-legend">
                            <span class="ai-seo-hl-long"><?php echo ai_seo_t('Lang setning (&gt;25 ord)', 'Long sentence (&gt;25 words)'); ?></span>
                            <span class="ai-seo-hl-passive"><?php echo ai_seo_t('Passiv stemme', 'Passive voice'); ?></span>
                        </div>
                        <div class="ai-seo-highlight-content" id="ai-seo-highlight-content"></div>
                    </div>
                <?php else : ?>
                    <p class="description"><?php echo ai_seo_t('Legg til innhold for å se lesbarhetsanalyse.', 'Add content to see readability analysis.'); ?></p>
                <?php endif; ?>
            </div>

            <!-- AI Actions -->
            <div class="ai-seo-ai-actions">
                <h4><?php echo ai_seo_t('AI-verktøy', 'AI Tools'); ?></h4>
                <p class="ai-seo-workflow-hint"><?php echo ai_seo_t('Skriv innholdet ferdig først, så bruk knappene under til å fylle ut SEO-feltene automatisk.', 'Finish writing the content first, then use the buttons below to fill in the SEO fields automatically.'); ?></p>
                <div class="ai-seo-button-group">
                    <button type="button" class="button button-secondary" id="ai-seo-suggest-keyword" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                        <?php echo ai_seo_t('Foreslå fokusord', 'Suggest Focus Keyword'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="ai-seo-suggest-title" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                        <?php echo ai_seo_t('Foreslå tittel', 'Suggest Title'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="ai-seo-generate-desc" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                        <?php echo ai_seo_t('Generer metabeskrivelse', 'Generate Meta Description'); ?>
                    </button>
                </div>
                <div class="ai-seo-button-group ai-seo-button-group-secondary">
                    <button type="button" class="button button-secondary" id="ai-seo-analyze-keywords" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                        <?php echo ai_seo_t('Analyser søkeord', 'Analyze Keywords'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="ai-seo-suggest-links" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                        <?php echo ai_seo_t('Foreslå interne lenker', 'Suggest Internal Links'); ?>
                    </button>
                </div>
                <div class="ai-seo-spinner" id="ai-seo-spinner" style="display:none;">
                    <span class="spinner is-active"></span> <?php echo ai_seo_t('Venter på AI-svar&hellip;', 'Waiting for AI response&hellip;'); ?>
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
                    <h4><?php echo ai_seo_t('Cornerstone-innhold du kan lenke til', 'Cornerstone content you can link to'); ?></h4>
                    <ul>
                        <?php foreach ( $cornerstone_posts as $cp ) : ?>
                            <li>
                                <a href="<?php echo esc_url( get_permalink( $cp->ID ) ); ?>" target="_blank">
                                    <?php echo esc_html( $cp->post_title ); ?>
                                </a>
                                <code class="ai-seo-copy-url" title="<?php echo ai_seo_t('Klikk for å kopiere', 'Click to copy'); ?>"><?php echo esc_url( get_permalink( $cp->ID ) ); ?></code>
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
