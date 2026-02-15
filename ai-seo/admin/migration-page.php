<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_SEO_Migration_Page {

    public function init() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
    }

    public function add_menu_page() {
        add_submenu_page(
            'ai-seo',
            ai_seo_t( 'SEO-migrering', 'SEO Migration' ),
            ai_seo_t( 'Migrering', 'Migration' ),
            'manage_options',
            'ai-seo-migration',
            array( $this, 'render_page' )
        );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $detected = AI_SEO_Migration::detect_plugins();
        ?>
        <div class="wrap ai-seo-migration">
            <h1><?php echo esc_html( ai_seo_t( 'SEO-migrering', 'SEO Migration' ) ); ?></h1>
            <p><?php echo ai_seo_t( 'Importer SEO-metadata fra andre programtillegg til AI SEO. Eksisterende AI SEO-data blir <strong>ikke</strong> overskrevet med mindre du krysser av for det.', 'Import SEO metadata from other plugins to AI SEO. Existing AI SEO data will <strong>not</strong> be overwritten unless you check the box.' ); ?></p>

            <?php if ( $detected['yoast'] === 0 && $detected['rankmath'] === 0 ) : ?>
                <div class="notice notice-info">
                    <p><?php echo esc_html( ai_seo_t( 'Ingen SEO-data fra Yoast SEO eller Rank Math ble funnet i databasen.', 'No SEO data from Yoast SEO or Rank Math was found in the database.' ) ); ?></p>
                </div>
            <?php else : ?>

                <?php if ( $detected['yoast'] > 0 ) : ?>
                <div class="ai-seo-migration-card">
                    <h2>Yoast SEO</h2>
                    <p><?php echo esc_html( ai_seo_t( 'Fant data for', 'Found data for' ) ); ?> <strong><?php echo esc_html( $detected['yoast'] ); ?></strong> <?php echo esc_html( ai_seo_t( 'innlegg/sider', 'posts/pages' ) ); ?>.</p>
                    <p class="description"><?php echo esc_html( ai_seo_t( 'Importerer: SEO-tittel, metabeskrivelse, fokus-søkeord, robots-meta, sosialt bilde og hjørnesteininnhold.', 'Imports: SEO title, meta description, focus keyword, robots meta, social image and cornerstone content.' ) ); ?></p>
                    <label class="ai-seo-migration-overwrite">
                        <input type="checkbox" id="ai-seo-yoast-overwrite" />
                        <?php echo esc_html( ai_seo_t( 'Overskriv eksisterende AI SEO-data', 'Overwrite existing AI SEO data' ) ); ?>
                    </label>
                    <p>
                        <button type="button" class="button button-primary ai-seo-migrate-btn" data-source="yoast">
                            <?php echo esc_html( ai_seo_t( 'Importer fra Yoast SEO', 'Import from Yoast SEO' ) ); ?>
                        </button>
                    </p>
                    <div class="ai-seo-migration-result" id="ai-seo-yoast-result"></div>
                </div>
                <?php endif; ?>

                <?php if ( $detected['rankmath'] > 0 ) : ?>
                <div class="ai-seo-migration-card">
                    <h2>Rank Math</h2>
                    <p><?php echo esc_html( ai_seo_t( 'Fant data for', 'Found data for' ) ); ?> <strong><?php echo esc_html( $detected['rankmath'] ); ?></strong> <?php echo esc_html( ai_seo_t( 'innlegg/sider', 'posts/pages' ) ); ?>.</p>
                    <p class="description"><?php echo esc_html( ai_seo_t( 'Importerer: SEO-tittel, metabeskrivelse, fokus-søkeord, robots-meta, sosialt bilde og søyleinnhold.', 'Imports: SEO title, meta description, focus keyword, robots meta, social image and pillar content.' ) ); ?></p>
                    <label class="ai-seo-migration-overwrite">
                        <input type="checkbox" id="ai-seo-rankmath-overwrite" />
                        <?php echo esc_html( ai_seo_t( 'Overskriv eksisterende AI SEO-data', 'Overwrite existing AI SEO data' ) ); ?>
                    </label>
                    <p>
                        <button type="button" class="button button-primary ai-seo-migrate-btn" data-source="rankmath">
                            <?php echo esc_html( ai_seo_t( 'Importer fra Rank Math', 'Import from Rank Math' ) ); ?>
                        </button>
                    </p>
                    <div class="ai-seo-migration-result" id="ai-seo-rankmath-result"></div>
                </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
        <?php
    }
}
