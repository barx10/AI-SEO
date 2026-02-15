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
            'SEO-migrering',
            'Migrering',
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
            <h1>SEO-migrering</h1>
            <p>Importer SEO-metadata fra andre programtillegg til AI SEO. Eksisterende AI SEO-data blir <strong>ikke</strong> overskrevet med mindre du krysser av for det.</p>

            <?php if ( $detected['yoast'] === 0 && $detected['rankmath'] === 0 ) : ?>
                <div class="notice notice-info">
                    <p>Ingen SEO-data fra Yoast SEO eller Rank Math ble funnet i databasen.</p>
                </div>
            <?php else : ?>

                <?php if ( $detected['yoast'] > 0 ) : ?>
                <div class="ai-seo-migration-card">
                    <h2>Yoast SEO</h2>
                    <p>Fant data for <strong><?php echo esc_html( $detected['yoast'] ); ?></strong> innlegg/sider.</p>
                    <p class="description">Importerer: SEO-tittel, metabeskrivelse, fokus-søkeord, robots-meta, sosialt bilde og hjørnesteininnhold.</p>
                    <label class="ai-seo-migration-overwrite">
                        <input type="checkbox" id="ai-seo-yoast-overwrite" />
                        Overskriv eksisterende AI SEO-data
                    </label>
                    <p>
                        <button type="button" class="button button-primary ai-seo-migrate-btn" data-source="yoast">
                            Importer fra Yoast SEO
                        </button>
                    </p>
                    <div class="ai-seo-migration-result" id="ai-seo-yoast-result"></div>
                </div>
                <?php endif; ?>

                <?php if ( $detected['rankmath'] > 0 ) : ?>
                <div class="ai-seo-migration-card">
                    <h2>Rank Math</h2>
                    <p>Fant data for <strong><?php echo esc_html( $detected['rankmath'] ); ?></strong> innlegg/sider.</p>
                    <p class="description">Importerer: SEO-tittel, metabeskrivelse, fokus-søkeord, robots-meta, sosialt bilde og søyleinnhold.</p>
                    <label class="ai-seo-migration-overwrite">
                        <input type="checkbox" id="ai-seo-rankmath-overwrite" />
                        Overskriv eksisterende AI SEO-data
                    </label>
                    <p>
                        <button type="button" class="button button-primary ai-seo-migrate-btn" data-source="rankmath">
                            Importer fra Rank Math
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
