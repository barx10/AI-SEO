<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_SEO_Dashboard_Widget {

    public function init() {
        add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
    }

    public function register_widget() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }

        wp_add_dashboard_widget(
            'ai_seo_dashboard_widget',
            ai_seo_t( 'AI SEO – Oversikt', 'AI SEO – Overview' ),
            array( $this, 'render_widget' )
        );
    }

    public function render_widget() {
        $stats = $this->gather_stats();
        ?>
        <div class="ai-seo-dashboard">
            <div class="ai-seo-dash-grid">
                <div class="ai-seo-dash-card">
                    <span class="ai-seo-dash-number"><?php echo esc_html( $stats['total_posts'] ); ?></span>
                    <span class="ai-seo-dash-label"><?php echo esc_html( ai_seo_t( 'Publiserte innlegg', 'Published Posts' ) ); ?></span>
                </div>
                <div class="ai-seo-dash-card ai-seo-dash-warning">
                    <span class="ai-seo-dash-number"><?php echo esc_html( $stats['missing_description'] ); ?></span>
                    <span class="ai-seo-dash-label"><?php echo esc_html( ai_seo_t( 'Mangler metabeskrivelse', 'Missing Meta Description' ) ); ?></span>
                </div>
                <div class="ai-seo-dash-card ai-seo-dash-warning">
                    <span class="ai-seo-dash-number"><?php echo esc_html( $stats['missing_title'] ); ?></span>
                    <span class="ai-seo-dash-label"><?php echo esc_html( ai_seo_t( 'Mangler SEO-tittel', 'Missing SEO Title' ) ); ?></span>
                </div>
                <div class="ai-seo-dash-card ai-seo-dash-warning">
                    <span class="ai-seo-dash-number"><?php echo esc_html( $stats['missing_keyword'] ); ?></span>
                    <span class="ai-seo-dash-label"><?php echo esc_html( ai_seo_t( 'Mangler fokus-søkeord', 'Missing Focus Keyword' ) ); ?></span>
                </div>
                <div class="ai-seo-dash-card ai-seo-dash-warning">
                    <span class="ai-seo-dash-number"><?php echo esc_html( $stats['missing_image'] ); ?></span>
                    <span class="ai-seo-dash-label"><?php echo esc_html( ai_seo_t( 'Mangler fremhevet bilde', 'Missing Featured Image' ) ); ?></span>
                </div>
                <div class="ai-seo-dash-card">
                    <span class="ai-seo-dash-number"><?php echo esc_html( $stats['cornerstone_count'] ); ?></span>
                    <span class="ai-seo-dash-label"><?php echo esc_html( ai_seo_t( 'Cornerstone-innlegg', 'Cornerstone Posts' ) ); ?></span>
                </div>
            </div>

            <?php if ( $stats['poor_readability_posts'] ) : ?>
                <h4><?php echo esc_html( ai_seo_t( 'Innlegg med dårlig lesbarhet', 'Posts with Poor Readability' ) ); ?></h4>
                <ul class="ai-seo-dash-list">
                    <?php foreach ( $stats['poor_readability_posts'] as $p ) : ?>
                        <li>
                            <a href="<?php echo esc_url( get_edit_post_link( $p['id'] ) ); ?>">
                                <?php echo esc_html( $p['title'] ); ?>
                            </a>
                            <span class="ai-seo-dash-badge ai-seo-dash-badge-poor">Score: <?php echo esc_html( $p['score'] ); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="ai-seo-dash-ok"><?php echo esc_html( ai_seo_t( 'Alle innlegg har akseptabel lesbarhet.', 'All posts have acceptable readability.' ) ); ?></p>
            <?php endif; ?>

            <?php
            $options   = get_option( 'ai_seo_options', array() );
            $provider  = isset( $options['ai_provider'] ) ? $options['ai_provider'] : 'anthropic';
            $has_key   = ! empty( $options['api_key'] );
            $providers = array( 'anthropic' => 'Claude', 'openai' => 'OpenAI', 'google' => 'Gemini' );
            ?>
            <h4><?php echo esc_html( ai_seo_t( 'AI-status', 'AI Status' ) ); ?></h4>
            <p>
                <?php echo esc_html( ai_seo_t( 'Leverandør:', 'Provider:' ) ); ?> <strong><?php echo esc_html( $providers[ $provider ] ?? $provider ); ?></strong><br>
                <?php echo esc_html( ai_seo_t( 'API-nøkkel:', 'API Key:' ) ); ?> <?php echo $has_key ? '<span style="color:#00a32a;">' . esc_html( ai_seo_t( 'Konfigurert', 'Configured' ) ) . '</span>' : '<span style="color:#d63638;">' . esc_html( ai_seo_t( 'Ikke satt', 'Not set' ) ) . '</span>'; ?>
            </p>
        </div>
        <?php
    }

    private function gather_stats() {
        $published = get_posts( array(
            'post_type'      => array( 'post', 'page' ),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) );

        $total          = count( $published );
        $missing_desc   = 0;
        $missing_title  = 0;
        $missing_keyword = 0;
        $missing_image  = 0;
        $cornerstone    = 0;
        $poor_posts     = array();

        $readability = new AI_SEO_Readability();

        foreach ( $published as $pid ) {
            if ( ! get_post_meta( $pid, '_ai_seo_meta_description', true ) ) {
                $missing_desc++;
            }
            if ( ! get_post_meta( $pid, '_ai_seo_meta_title', true ) ) {
                $missing_title++;
            }
            if ( ! get_post_meta( $pid, '_ai_seo_focus_keyword', true ) ) {
                $missing_keyword++;
            }
            if ( ! has_post_thumbnail( $pid ) ) {
                $missing_image++;
            }
            if ( get_post_meta( $pid, '_ai_seo_cornerstone', true ) ) {
                $cornerstone++;
            }

            $post    = get_post( $pid );
            $content = wp_strip_all_tags( $post->post_content );
            if ( ! empty( $content ) ) {
                $result = $readability->analyze( $content );
                if ( $result['rating'] === 'poor' ) {
                    $poor_posts[] = array(
                        'id'    => $pid,
                        'title' => get_the_title( $pid ),
                        'score' => $result['score'],
                    );
                }
            }
        }

        // Limit poor readability list.
        usort( $poor_posts, function ( $a, $b ) {
            return $a['score'] - $b['score'];
        } );
        $poor_posts = array_slice( $poor_posts, 0, 5 );

        return array(
            'total_posts'          => $total,
            'missing_description'  => $missing_desc,
            'missing_title'        => $missing_title,
            'missing_keyword'      => $missing_keyword,
            'missing_image'        => $missing_image,
            'cornerstone_count'    => $cornerstone,
            'poor_readability_posts' => $poor_posts,
        );
    }
}
