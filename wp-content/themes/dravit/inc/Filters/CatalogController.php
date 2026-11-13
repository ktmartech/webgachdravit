<?php
declare(strict_types=1);

namespace Dravit\Filters;

use Dravit\Core\Integrations\LiteSpeedCompat;
use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * REST Endpoint công khai `GET /wp-json/dravit/v1/catalog` phục vụ lọc AJAX, Load More và xem trước số lượng trên Mobile
 * (docs/05 §5, docs/15 §2.1, Test F-1..F-15, M-11).
 */
final class CatalogController implements ServiceProviderInterface {
    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        register_rest_route('dravit/v1', '/catalog', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'handle_catalog'],
            'permission_callback' => '__return_true',
            'args'                => $this->build_route_args(),
        ]);
    }

    public function handle_catalog(\WP_REST_Request $request): \WP_REST_Response {
        $state        = UrlState::fromRestRequest($request);
        $fragment     = (string) ($request->get_param('fragment') ?: 'grid');
        $include_meta = (bool) $request->get_param('include_items_meta');
        $archive_name = Renderer::resolveArchiveName($state);

        if (!in_array($fragment, ['grid', 'items', 'count'], true)) {
            $fragment = 'grid';
        }

        $products    = [];
        $total       = 0;
        $total_pages = 0;
        $shown       = 0;

        if ($fragment === 'count') {
            $total       = FacetCounter::getQuickTotal($state);
            $total_pages = $total > 0 ? (int) ceil($total / $state->perPage()) : 0;
            $shown       = min($total, $state->page() * $state->perPage());
        } else {
            $query_args  = QueryBuilder::buildQueryArgs($state);
            $wp_query    = new \WP_Query($query_args);
            $total       = max(0, (int) $wp_query->found_posts);
            $total_pages = max(0, (int) $wp_query->max_num_pages);

            if (is_array($wp_query->posts) && function_exists('wc_get_product')) {
                foreach ($wp_query->posts as $post_or_id) {
                    $pid     = $post_or_id instanceof \WP_Post ? (int) $post_or_id->ID : (int) $post_or_id;
                    $product = $pid > 0 ? wc_get_product($pid) : null;
                    if ($product instanceof \WC_Product) {
                        $products[] = $product;
                    }
                }
            }

            $offset = ($state->page() - 1) * $state->perPage();
            $shown  = $total > 0 ? min($total, $offset + count($products)) : 0;
        }

        $facets_payload = [];
        $facets_html    = '';
        $chips_html     = Renderer::renderChipsHtml($state, $archive_name);

        if ($fragment !== 'items') {
            $counts         = FacetCounter::getCounts($state);
            $clean_state    = UrlState::fromArray($state->base(), [], 1, $state->perPage());
            $base_counts    = $state->hasActiveFilters() ? FacetCounter::getCounts($clean_state) : $counts;
            $view_data      = Renderer::buildFilterViewData($state, $counts, $base_counts);
            $facets_payload = $view_data['facets'];
            $facets_html    = Renderer::renderFacetsHtml($state, $view_data, $total);
        }

        $items_html = '';
        if ($fragment !== 'count') {
            $start_idx  = ($state->page() - 1) * $state->perPage();
            $items_html = Renderer::renderItemsHtml($products, $start_idx, $state, $archive_name);
        }

        $payload = [
            'total'        => $total,
            'shown'        => $shown,
            'page'         => $state->page(),
            'per_page'     => $state->perPage(),
            'total_pages'  => $total_pages,
            'url'          => $state->url(),
            'canonical'    => $state->canonical(),
            'robots'       => $state->robots($total, $total_pages),
            'title'        => $state->title($archive_name),
            'redirect_to'  => $state->redirectTo(),
            'next_url'     => $state->buildNextPageUrl($total_pages),
            'active_chips' => $state->activeChips(),
            'facets'       => $facets_payload,
            'items_html'   => $items_html,
            'facets_html'  => $facets_html,
            'chips_html'   => $chips_html,
        ];

        if ($include_meta && $fragment !== 'count') {
            $payload['items_meta'] = Renderer::buildItemsMeta($products);
        }

        if (class_exists(LiteSpeedCompat::class)) {
            LiteSpeedCompat::add_cache_tag('drv_catalog');
        }

        $response = new \WP_REST_Response($payload, 200);
        $response->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60');
        $response->header('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function build_route_args(): array {
        $default_per_page = max(1, min(48, (int) drv_setting('catalog.per_page', 24)));

        $args = [
            'base'               => [
                'type'              => 'string',
                'default'           => 'product_cat:gach',
                'sanitize_callback' => static fn (mixed $v): string => mb_substr(sanitize_text_field((string) $v), 0, 80),
            ],
            'page'               => [
                'type'              => 'integer',
                'default'           => 1,
                'sanitize_callback' => static fn (mixed $v): int => max(1, (int) $v),
            ],
            'per_page'           => [
                'type'              => 'integer',
                'default'           => $default_per_page,
                'sanitize_callback' => static fn (mixed $v): int => max(1, min(48, (int) $v)),
            ],
            'sort'               => [
                'type'              => 'string',
                'default'           => 'moi-ve',
                'sanitize_callback' => static fn (mixed $v): string => sanitize_key((string) $v),
            ],
            'fragment'           => [
                'type'              => 'string',
                'default'           => 'grid',
                'enum'              => ['grid', 'items', 'count'],
                'sanitize_callback' => static fn (mixed $v): string => sanitize_key((string) $v),
            ],
            'include_items_meta' => [
                'type'              => 'boolean',
                'default'           => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ],
        ];

        foreach (array_keys(FacetRegistry::getFacets()) as $param) {
            $args[$param] = [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => static fn (mixed $v): string => is_array($v)
                    ? implode(',', array_map(static fn (mixed $i): string => sanitize_text_field((string) $i), $v))
                    : mb_substr(sanitize_text_field((string) $v), 0, 250),
            ];
        }

        return $args;
    }
}

