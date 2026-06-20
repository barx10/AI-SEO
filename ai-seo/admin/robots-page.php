<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin page: AI crawler (robots.txt) analysis + one-click blocking.
 *
 * Fetches the site's robots.txt and reports, for each known AI crawler,
 * whether it is allowed, blocked or not mentioned — with a recommendation.
 * The user can tick which crawlers to block and the plugin injects the
 * Disallow rules into the robots.txt WordPress already serves (via the
 * `robots_txt` filter), so no file needs to be edited by hand.
 */
class AI_SEO_Robots_Page {

    /**
     * Known AI crawlers.
     *
     * 'recommend' => 'block' | 'allow' : the recommended default stance.
     * 'note'      => short human-readable rationale.
     */
    private static function get_bots() {
        return array(
            'GPTBot'             => array( 'recommend' => 'allow', 'note' => 'OpenAIs treningsrobot. Tillat for å bli sitert i ChatGPT.' ),
            'ChatGPT-User'       => array( 'recommend' => 'allow', 'note' => 'Henter sider når en ChatGPT-bruker ber om dem live.' ),
            'OAI-SearchBot'      => array( 'recommend' => 'allow', 'note' => 'OpenAIs søkeindeksering. Tillat for synlighet i ChatGPT-søk.' ),
            'ClaudeBot'          => array( 'recommend' => 'allow', 'note' => 'Anthropics robot for Claude. Tillat for å bli sitert.' ),
            'anthropic-ai'       => array( 'recommend' => 'allow', 'note' => 'Eldre Anthropic-agent. Tillat for kompatibilitet.' ),
            'PerplexityBot'      => array( 'recommend' => 'allow', 'note' => 'Perplexity AI-søk. Tillat for å vises som kilde.' ),
            'Google-Extended'    => array( 'recommend' => 'allow', 'note' => 'Styrer Google Gemini/Vertex. Påvirker ikke vanlig Google-indeksering.' ),
            'Applebot-Extended'  => array( 'recommend' => 'allow', 'note' => 'Apple Intelligence-trening. Påvirker ikke Siri/Spotlight-indeksering.' ),
            'cohere-ai'          => array( 'recommend' => 'allow', 'note' => 'Cohere-robot. Tillat eller blokker etter ønske.' ),
            'CCBot'              => array( 'recommend' => 'block', 'note' => 'Common Crawl — brukes til trening av mange modeller uten attribusjon. Mange velger å blokkere.' ),
            'YouBot'             => array( 'recommend' => 'allow', 'note' => 'You.com AI-søk. Tillat for synlighet.' ),
            'Bytespider'         => array( 'recommend' => 'block', 'note' => 'ByteDance/TikTok. Aggressiv crawling, ofte blokkert.' ),
            'ImagesiftBot'       => array( 'recommend' => 'block', 'note' => 'Samler bilder for KI-trening. Ofte blokkert.' ),
            'facebookexternalhit' => array( 'recommend' => 'allow', 'note' => 'Brukes til delingsforhåndsvisninger på Facebook/Meta. Bør tillates.' ),
        );
    }

    public function init() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_init', array( $this, 'handle_save' ) );
    }

    public function add_menu_page() {
        add_submenu_page(
            'ai-seo',
            'AI SEO – KI-roboter',
            'KI-roboter',
            'manage_options',
            'ai-seo-robots',
            array( $this, 'render_page' )
        );
    }

    /**
     * Persist the user's blocked-bot selection.
     */
    public function handle_save() {
        if ( ! isset( $_POST['ai_seo_robots_nonce'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        check_admin_referer( 'ai_seo_save_robots', 'ai_seo_robots_nonce' );

        $known    = array_keys( self::get_bots() );
        $selected = ( isset( $_POST['geo_blocked_bots'] ) && is_array( $_POST['geo_blocked_bots'] ) )
            ? array_map( 'sanitize_text_field', wp_unslash( $_POST['geo_blocked_bots'] ) )
            : array();

        $options = get_option( 'ai_seo_options', array() );
        $options['geo_blocked_bots'] = array_values( array_intersect( $known, $selected ) );
        update_option( 'ai_seo_options', $options );

        add_settings_error( 'ai_seo_robots', 'saved', 'Lagret. robots.txt er oppdatert automatisk.', 'updated' );
    }

    /**
     * The crawlers the user has chosen to block.
     *
     * @return string[]
     */
    public static function get_blocked() {
        $options = get_option( 'ai_seo_options', array() );
        if ( empty( $options['geo_blocked_bots'] ) || ! is_array( $options['geo_blocked_bots'] ) ) {
            return array();
        }
        $known = array_keys( self::get_bots() );
        return array_values( array_intersect( $known, $options['geo_blocked_bots'] ) );
    }

    /**
     * Inject Disallow rules for blocked crawlers into the served robots.txt.
     *
     * Hooked on `robots_txt`. Only applies to WordPress' virtual robots.txt —
     * if a static robots.txt file exists in the site root, WordPress serves
     * that instead and this filter has no effect.
     *
     * @param string $output The robots.txt output.
     * @param bool   $public Whether the site is publicly reachable.
     * @return string
     */
    public static function filter_robots_txt( $output, $public ) {
        $blocked = self::get_blocked();
        if ( empty( $blocked ) ) {
            return $output;
        }

        $lines = array( '', '# KI-roboter blokkert av AI SEO (GEO)' );
        foreach ( $blocked as $bot ) {
            $lines[] = 'User-agent: ' . $bot;
            $lines[] = 'Disallow: /';
            $lines[] = '';
        }

        return rtrim( $output ) . "\n" . implode( "\n", $lines );
    }

    /**
     * Fetch the current robots.txt contents.
     *
     * @return string|WP_Error
     */
    private function fetch_robots_txt() {
        $response = wp_remote_get( home_url( '/robots.txt' ), array( 'timeout' => 10 ) );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return new WP_Error( 'http', 'Kunne ikke hente robots.txt (HTTP ' . wp_remote_retrieve_response_code( $response ) . ').' );
        }
        return (string) wp_remote_retrieve_body( $response );
    }

    /**
     * Parse robots.txt into [ user-agent (lowercase) => [ disallow[], allow[] ] ].
     */
    private function parse_groups( $robots_txt ) {
        $groups          = array();
        $current_agents  = array();
        $lines           = preg_split( '/\r\n|\r|\n/', $robots_txt );
        $expecting_rules = false;

        foreach ( $lines as $line ) {
            $line = trim( preg_replace( '/#.*$/', '', $line ) );
            if ( '' === $line ) {
                continue;
            }
            if ( false === strpos( $line, ':' ) ) {
                continue;
            }
            list( $field, $value ) = array_pad( explode( ':', $line, 2 ), 2, '' );
            $field = strtolower( trim( $field ) );
            $value = trim( $value );

            if ( 'user-agent' === $field ) {
                // A new block of agents begins after a rule line was seen.
                if ( $expecting_rules ) {
                    $current_agents  = array();
                    $expecting_rules = false;
                }
                $agent            = strtolower( $value );
                $current_agents[] = $agent;
                if ( ! isset( $groups[ $agent ] ) ) {
                    $groups[ $agent ] = array( 'disallow' => array(), 'allow' => array() );
                }
            } elseif ( 'disallow' === $field ) {
                $expecting_rules = true;
                foreach ( $current_agents as $agent ) {
                    $groups[ $agent ]['disallow'][] = $value;
                }
            } elseif ( 'allow' === $field ) {
                $expecting_rules = true;
                foreach ( $current_agents as $agent ) {
                    $groups[ $agent ]['allow'][] = $value;
                }
            }
        }

        return $groups;
    }

    /**
     * Determine status for a single bot: 'blocked', 'allowed' or 'not_mentioned'.
     */
    private function bot_status( $bot, $groups ) {
        $key = strtolower( $bot );
        if ( ! isset( $groups[ $key ] ) ) {
            return 'not_mentioned';
        }
        if ( in_array( '/', $groups[ $key ]['disallow'], true ) ) {
            return 'blocked';
        }
        return 'allowed';
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $bots       = self::get_bots();
        $options    = get_option( 'ai_seo_options', array() );
        $configured = isset( $options['geo_blocked_bots'] ) && is_array( $options['geo_blocked_bots'] );
        $blocked    = self::get_blocked();

        $robots_txt = $this->fetch_robots_txt();
        $error      = is_wp_error( $robots_txt ) ? $robots_txt->get_error_message() : '';
        $groups     = $error ? array() : $this->parse_groups( $robots_txt );

        $labels = array(
            'allowed'       => array( 'Tillatt', 'good' ),
            'blocked'       => array( 'Blokkert', 'poor' ),
            'not_mentioned' => array( 'Ikke nevnt', 'none' ),
        );
        ?>
        <div class="wrap ai-seo-robots">
            <h1>KI-robot-analyse</h1>
            <p>Sjekker <code><?php echo esc_html( home_url( '/robots.txt' ) ); ?></code> mot kjente KI-roboter.
               Kryss av hvilke du vil blokkere og trykk <strong>Lagre</strong> &mdash; plugin-en oppdaterer robots.txt for deg.</p>

            <?php settings_errors( 'ai_seo_robots' ); ?>

            <?php if ( $error ) : ?>
                <div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field( 'ai_seo_save_robots', 'ai_seo_robots_nonce' ); ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:60px;">Blokker</th>
                            <th>Robot</th>
                            <th style="width:110px;">Status</th>
                            <th>Anbefaling</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $bots as $bot => $info ) :
                            $status = $this->bot_status( $bot, $groups );
                            list( $label, $color ) = $labels[ $status ];
                            $rec_text  = 'block' === $info['recommend'] ? 'Anbefalt: blokker' : 'Anbefalt: tillat';
                            // Before the user has saved anything, pre-tick the
                            // recommended-block crawlers as a sensible default.
                            $is_checked = $configured ? in_array( $bot, $blocked, true ) : ( 'block' === $info['recommend'] );
                            ?>
                            <tr>
                                <td style="text-align:center;">
                                    <input type="checkbox" name="geo_blocked_bots[]" value="<?php echo esc_attr( $bot ); ?>" <?php checked( $is_checked ); ?> />
                                </td>
                                <td><code><?php echo esc_html( $bot ); ?></code></td>
                                <td>
                                    <span class="ai-seo-readability-score ai-seo-score-<?php echo esc_attr( $color ); ?>" style="display:inline-block;padding:2px 8px;">
                                        <?php echo esc_html( $label ); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo esc_html( $rec_text ); ?>.</strong>
                                    <?php echo esc_html( $info['note'] ); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php submit_button( 'Lagre og oppdater robots.txt' ); ?>
            </form>

            <p class="description">
                Merk: Dette virker når WordPress lager robots.txt automatisk. Hvis du har en egen
                <code>robots.txt</code>-fil i rotmappen, overstyrer den WordPress &mdash; da må reglene legges inn der.
                Statusen over kan henge etter til hurtigbufferen er tømt.
            </p>
        </div>
        <?php
    }
}
