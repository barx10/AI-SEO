<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin page: AI crawler (robots.txt) analysis.
 *
 * Fetches the site's robots.txt and reports, for each known AI crawler,
 * whether it is allowed, blocked or not mentioned — with a recommendation
 * and a ready-to-paste robots.txt block.
 */
class AI_SEO_Robots_Page {

    /**
     * Known AI crawlers.
     *
     * 'recommend' => 'block' | 'allow' : the recommended default stance.
     * 'note'      => short human-readable rationale.
     */
    private function get_bots() {
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
     * Parse robots.txt into [ user-agent (lowercase) => [ 'allow' => bool ] ].
     *
     * A bot is considered "blocked" if its (or the wildcard) group contains a
     * bare "Disallow: /".
     */
    private function parse_groups( $robots_txt ) {
        $groups        = array();
        $current_agents = array();
        $lines         = preg_split( '/\r\n|\r|\n/', $robots_txt );
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
                $agent = strtolower( $value );
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
        // Blocked if a bare "Disallow: /" exists for this agent.
        if ( in_array( '/', $groups[ $key ]['disallow'], true ) ) {
            return 'blocked';
        }
        return 'allowed';
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $bots       = $this->get_bots();
        $robots_txt = $this->fetch_robots_txt();
        $error      = is_wp_error( $robots_txt ) ? $robots_txt->get_error_message() : '';
        $groups     = $error ? array() : $this->parse_groups( $robots_txt );

        // Build a recommended robots.txt block from the per-bot recommendation.
        $block_lines = array();
        foreach ( $bots as $bot => $info ) {
            $block_lines[] = 'User-agent: ' . $bot;
            $block_lines[] = 'block' === $info['recommend'] ? 'Disallow: /' : 'Disallow:';
            $block_lines[] = '';
        }
        $recommended_block = rtrim( implode( "\n", $block_lines ) ) . "\n";

        $labels = array(
            'allowed'       => array( 'Tillatt', 'good' ),
            'blocked'       => array( 'Blokkert', 'poor' ),
            'not_mentioned' => array( 'Ikke nevnt', 'none' ),
        );
        ?>
        <div class="wrap ai-seo-robots">
            <h1>KI-robot-analyse</h1>
            <p>Sjekker <code><?php echo esc_html( home_url( '/robots.txt' ) ); ?></code> mot kjente KI-roboter.</p>

            <?php if ( $error ) : ?>
                <div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
            <?php endif; ?>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Robot</th>
                        <th>Status</th>
                        <th>Anbefaling</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $bots as $bot => $info ) :
                        $status = $this->bot_status( $bot, $groups );
                        list( $label, $color ) = $labels[ $status ];
                        $rec_text = 'block' === $info['recommend'] ? 'Anbefalt: blokker' : 'Anbefalt: tillat';
                        ?>
                        <tr>
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

            <h2>Anbefalt robots.txt-blokk</h2>
            <p>Kopier og lim inn i robots.txt-filen din:</p>
            <textarea readonly rows="<?php echo esc_attr( count( $bots ) * 3 ); ?>" class="large-text code" id="ai-seo-robots-block" style="font-family:monospace;"><?php echo esc_textarea( $recommended_block ); ?></textarea>
            <p>
                <button type="button" class="button button-primary" onclick="navigator.clipboard.writeText(document.getElementById('ai-seo-robots-block').value);this.textContent='Kopiert!';">
                    Kopier til utklippstavle
                </button>
            </p>
        </div>
        <?php
    }
}
