<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_SEO_Redirects_Page {

    public function init() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
    }

    public function add_menu_page() {
        add_submenu_page(
            'ai-seo',
            ai_seo_t( 'AI SEO – Omdirigeringer', 'AI SEO – Redirects' ),
            ai_seo_t( 'Omdirigeringer', 'Redirects' ),
            'manage_options',
            'ai-seo-redirects',
            array( $this, 'render_page' )
        );
    }

    /**
     * Handle add/delete actions.
     */
    public function handle_actions() {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'ai-seo-redirects' ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Add redirect.
        if ( isset( $_POST['ai_seo_add_redirect'] ) ) {
            check_admin_referer( 'ai_seo_redirect_add' );

            $source = isset( $_POST['source_url'] ) ? sanitize_text_field( wp_unslash( $_POST['source_url'] ) ) : '';
            $target = isset( $_POST['target_url'] ) ? esc_url_raw( wp_unslash( $_POST['target_url'] ) ) : '';
            $type   = isset( $_POST['redirect_type'] ) ? absint( $_POST['redirect_type'] ) : 301;

            if ( ! empty( $source ) && ! empty( $target ) ) {
                $result = AI_SEO_Redirects::add( $source, $target, $type );
                if ( is_wp_error( $result ) ) {
                    add_settings_error( 'ai_seo_redirects', 'duplicate', $result->get_error_message(), 'error' );
                } else {
                    add_settings_error( 'ai_seo_redirects', 'added', ai_seo_t( 'Omdirigering lagt til.', 'Redirect added.' ), 'success' );
                }
            } else {
                add_settings_error( 'ai_seo_redirects', 'missing', ai_seo_t( 'Begge URL-feltene er påkrevde.', 'Both URL fields are required.' ), 'error' );
            }
        }

        // Delete redirect.
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['redirect_id'] ) ) {
            check_admin_referer( 'ai_seo_redirect_delete_' . $_GET['redirect_id'] );
            AI_SEO_Redirects::delete( absint( $_GET['redirect_id'] ) );
            wp_safe_redirect( admin_url( 'admin.php?page=ai-seo-redirects&deleted=1' ) );
            exit;
        }
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $page      = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $per_page  = 50;
        $redirects = AI_SEO_Redirects::get_all( $per_page, $page );
        $total     = AI_SEO_Redirects::count_all();
        $pages     = ceil( $total / $per_page );

        if ( isset( $_GET['deleted'] ) ) {
            add_settings_error( 'ai_seo_redirects', 'deleted', ai_seo_t( 'Omdirigering slettet.', 'Redirect deleted.' ), 'success' );
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( ai_seo_t( 'Omdirigeringer', 'Redirects' ) ); ?></h1>

            <?php settings_errors( 'ai_seo_redirects' ); ?>

            <div class="ai-seo-redirect-form">
                <h2><?php echo esc_html( ai_seo_t( 'Legg til omdirigering', 'Add Redirect' ) ); ?></h2>
                <form method="post">
                    <?php wp_nonce_field( 'ai_seo_redirect_add' ); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="source_url"><?php echo esc_html( ai_seo_t( 'Kilde-URL (sti)', 'Source URL (path)' ) ); ?></label></th>
                            <td>
                                <input type="text" name="source_url" id="source_url" class="regular-text" placeholder="/gammel-side" required />
                                <p class="description"><?php echo esc_html( ai_seo_t( 'Relativ sti, f.eks.', 'Relative path, e.g.' ) ); ?> <code>/gammel-side</code></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="target_url"><?php echo esc_html( ai_seo_t( 'Mål-URL', 'Target URL' ) ); ?></label></th>
                            <td>
                                <input type="url" name="target_url" id="target_url" class="regular-text" placeholder="https://example.com/ny-side" required />
                                <p class="description"><?php echo esc_html( ai_seo_t( 'Full URL eller relativ sti til det nye målet.', 'Full URL or relative path to the new target.' ) ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="redirect_type">Type</label></th>
                            <td>
                                <select name="redirect_type" id="redirect_type">
                                    <option value="301"><?php echo esc_html( ai_seo_t( '301 – Permanent', '301 – Permanent' ) ); ?></option>
                                    <option value="302"><?php echo esc_html( ai_seo_t( '302 – Midlertidig', '302 – Temporary' ) ); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( ai_seo_t( 'Legg til omdirigering', 'Add Redirect' ), 'primary', 'ai_seo_add_redirect' ); ?>
                </form>
            </div>

            <?php
            // Detect redirect chains and loops.
            $chain_issues = AI_SEO_Redirects::detect_chains();
            if ( ! empty( $chain_issues ) ) :
                $loops  = array_filter( $chain_issues, function( $i ) { return $i['type'] === 'loop'; } );
                $chains = array_filter( $chain_issues, function( $i ) { return $i['type'] === 'chain'; } );
            ?>
                <div class="ai-seo-chain-warnings">
                    <h2><?php echo esc_html( ai_seo_t( 'Advarsler', 'Warnings' ) ); ?></h2>
                    <?php if ( ! empty( $loops ) ) : ?>
                        <?php foreach ( $loops as $issue ) : ?>
                            <div class="ai-seo-chain-warning ai-seo-chain-loop">
                                <strong><?php echo esc_html( ai_seo_t( 'Omdirigeringsloop oppdaget:', 'Redirect loop detected:' ) ); ?></strong>
                                <code><?php echo esc_html( implode( ' &rarr; ', $issue['path'] ) ); ?></code>
                                <br><span class="description"><?php echo esc_html( ai_seo_t( 'Denne loopen vil aldri nå et endelig mål. Fjern eller rett opp en av omdirigeringene.', 'This loop will never reach a final target. Remove or fix one of the redirects.' ) ); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ( ! empty( $chains ) ) : ?>
                        <?php foreach ( $chains as $issue ) : ?>
                            <div class="ai-seo-chain-warning ai-seo-chain-chain">
                                <strong><?php echo esc_html( ai_seo_t( 'Omdirigeringskjede', 'Redirect chain' ) ); ?> (<?php echo count( $issue['path'] ); ?> <?php echo esc_html( ai_seo_t( 'hopp', 'hops' ) ); ?>):</strong>
                                <code><?php echo esc_html( implode( ' &rarr; ', $issue['path'] ) ); ?></code>
                                <br><span class="description"><?php echo esc_html( ai_seo_t( 'Flere hopp forsinker brukeren og reduserer lenke-juice. Vurder å peke den første omdirigeringen direkte til det endelige målet.', 'Multiple hops slow down the user and reduce link juice. Consider pointing the first redirect directly to the final target.' ) ); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <h2><?php echo esc_html( ai_seo_t( 'Eksisterende omdirigeringer', 'Existing Redirects' ) ); ?> (<?php echo esc_html( $total ); ?>)</h2>

            <?php if ( empty( $redirects ) ) : ?>
                <p><?php echo esc_html( ai_seo_t( 'Ingen omdirigeringer er lagt til ennå.', 'No redirects have been added yet.' ) ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html( ai_seo_t( 'Kilde-URL', 'Source URL' ) ); ?></th>
                            <th><?php echo esc_html( ai_seo_t( 'Mål-URL', 'Target URL' ) ); ?></th>
                            <th>Type</th>
                            <th><?php echo esc_html( ai_seo_t( 'Treff', 'Hits' ) ); ?></th>
                            <th><?php echo esc_html( ai_seo_t( 'Opprettet', 'Created' ) ); ?></th>
                            <th><?php echo esc_html( ai_seo_t( 'Handling', 'Action' ) ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $redirects as $r ) : ?>
                            <tr>
                                <td><code><?php echo esc_html( $r->source_url ); ?></code></td>
                                <td><a href="<?php echo esc_url( $r->target_url ); ?>" target="_blank"><?php echo esc_html( $r->target_url ); ?></a></td>
                                <td><?php echo esc_html( $r->type ); ?></td>
                                <td><?php echo esc_html( $r->hits ); ?></td>
                                <td><?php echo esc_html( date_i18n( 'Y-m-d H:i', strtotime( $r->created_at ) ) ); ?></td>
                                <td>
                                    <a href="<?php echo esc_url( wp_nonce_url(
                                        admin_url( 'admin.php?page=ai-seo-redirects&action=delete&redirect_id=' . $r->id ),
                                        'ai_seo_redirect_delete_' . $r->id
                                    ) ); ?>" class="ai-seo-delete-link" onclick="return confirm('<?php echo esc_js( ai_seo_t( 'Er du sikker på at du vil slette denne omdirigeringen?', 'Are you sure you want to delete this redirect?' ) ); ?>');"><?php echo esc_html( ai_seo_t( 'Slett', 'Delete' ) ); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ( $pages > 1 ) : ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php
                            echo paginate_links( array(
                                'base'    => add_query_arg( 'paged', '%#%' ),
                                'format'  => '',
                                'current' => $page,
                                'total'   => $pages,
                            ) );
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
}
