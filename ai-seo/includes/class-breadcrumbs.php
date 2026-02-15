<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_SEO_Breadcrumbs {

    public function init() {
        add_shortcode( 'ai_seo_breadcrumbs', array( $this, 'render_shortcode' ) );
        add_action( 'wp_head', array( $this, 'output_schema' ), 5 );
    }

    /**
     * Build the breadcrumb trail as an array of items.
     *
     * @return array Each item has 'name' and 'url'.
     */
    public function get_trail() {
        $trail = array();

        $trail[] = array(
            'name' => 'Hjem',
            'url'  => home_url( '/' ),
        );

        if ( is_singular() ) {
            $post = get_queried_object();

            if ( 'post' === $post->post_type ) {
                $categories = get_the_category( $post->ID );
                if ( ! empty( $categories ) ) {
                    // Use primary (first) category.
                    $cat = $categories[0];
                    // Build parent chain.
                    $parents = array();
                    $current_cat = $cat;
                    while ( $current_cat->parent ) {
                        $parent_cat = get_category( $current_cat->parent );
                        if ( ! $parent_cat || is_wp_error( $parent_cat ) ) {
                            break;
                        }
                        $parents[] = array(
                            'name' => $parent_cat->name,
                            'url'  => get_category_link( $parent_cat->term_id ),
                        );
                        $current_cat = $parent_cat;
                    }
                    $parents = array_reverse( $parents );
                    foreach ( $parents as $p ) {
                        $trail[] = $p;
                    }
                    $trail[] = array(
                        'name' => $cat->name,
                        'url'  => get_category_link( $cat->term_id ),
                    );
                }
            } elseif ( 'page' === $post->post_type && $post->post_parent ) {
                $ancestors = get_post_ancestors( $post->ID );
                $ancestors = array_reverse( $ancestors );
                foreach ( $ancestors as $ancestor_id ) {
                    $trail[] = array(
                        'name' => get_the_title( $ancestor_id ),
                        'url'  => get_permalink( $ancestor_id ),
                    );
                }
            } else {
                // Custom post type.
                $pt_obj = get_post_type_object( $post->post_type );
                if ( $pt_obj && $pt_obj->has_archive ) {
                    $trail[] = array(
                        'name' => $pt_obj->labels->name,
                        'url'  => get_post_type_archive_link( $post->post_type ),
                    );
                }
            }

            $trail[] = array(
                'name' => get_the_title( $post->ID ),
                'url'  => get_permalink( $post->ID ),
            );

        } elseif ( is_category() ) {
            $cat = get_queried_object();
            if ( $cat->parent ) {
                $parents = array();
                $current = $cat;
                while ( $current->parent ) {
                    $parent = get_category( $current->parent );
                    if ( ! $parent || is_wp_error( $parent ) ) {
                        break;
                    }
                    $parents[] = array(
                        'name' => $parent->name,
                        'url'  => get_category_link( $parent->term_id ),
                    );
                    $current = $parent;
                }
                foreach ( array_reverse( $parents ) as $p ) {
                    $trail[] = $p;
                }
            }
            $trail[] = array(
                'name' => $cat->name,
                'url'  => get_category_link( $cat->term_id ),
            );

        } elseif ( is_tag() ) {
            $trail[] = array(
                'name' => single_tag_title( '', false ),
                'url'  => get_tag_link( get_queried_object_id() ),
            );

        } elseif ( is_post_type_archive() ) {
            $trail[] = array(
                'name' => post_type_archive_title( '', false ),
                'url'  => get_post_type_archive_link( get_query_var( 'post_type' ) ),
            );

        } elseif ( is_search() ) {
            $trail[] = array(
                'name' => 'Søkeresultater for: ' . get_search_query(),
                'url'  => get_search_link(),
            );

        } elseif ( is_404() ) {
            $trail[] = array(
                'name' => '404 – Ikke funnet',
                'url'  => '',
            );
        }

        return $trail;
    }

    /**
     * Render breadcrumbs via shortcode.
     */
    public function render_shortcode( $atts ) {
        $trail = $this->get_trail();

        if ( count( $trail ) <= 1 ) {
            return '';
        }

        $html = '<nav class="ai-seo-breadcrumbs" aria-label="Brødsmuler">';
        $html .= '<ol class="ai-seo-breadcrumb-list">';

        $last_index = count( $trail ) - 1;
        foreach ( $trail as $i => $item ) {
            $html .= '<li class="ai-seo-breadcrumb-item">';
            if ( $i < $last_index && ! empty( $item['url'] ) ) {
                $html .= '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['name'] ) . '</a>';
            } else {
                $html .= '<span aria-current="page">' . esc_html( $item['name'] ) . '</span>';
            }
            $html .= '</li>';
        }

        $html .= '</ol>';
        $html .= '</nav>';

        return $html;
    }

    /**
     * Output BreadcrumbList JSON-LD schema.
     */
    public function output_schema() {
        if ( is_front_page() || is_home() ) {
            return;
        }

        $trail = $this->get_trail();

        if ( count( $trail ) <= 1 ) {
            return;
        }

        $items = array();
        foreach ( $trail as $i => $crumb ) {
            $items[] = array(
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $crumb['name'],
                'item'     => ! empty( $crumb['url'] ) ? $crumb['url'] : null,
            );
        }

        // Remove null items.
        $items = array_map( function ( $item ) {
            return array_filter( $item, function ( $v ) {
                return $v !== null;
            } );
        }, $items );

        $schema = array(
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        );

        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        echo "\n</script>\n";
    }
}
