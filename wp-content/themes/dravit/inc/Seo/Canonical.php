<?php
declare(strict_types=1);

namespace Dravit\Seo;

use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Quản lý thẻ `<link rel="canonical">` nhất quán toàn site (ADR D-21, docs/05 §6.2, docs/06 §2, Test M-1..M-8, R-7):
 * - Không bao giờ có dấu `/` cuối (trừ gốc domain).
 * - Loại bỏ toàn bộ tracking params (`utm_*`, `gclid`, `fbclid`, `zarsrc`).
 * - Trang danh mục sạch phân trang `/page/2` giữ self-canonical `/page/2` (M-2).
 * - Trang danh mục có bất kỳ tham số lọc/utility nào -> trỏ canonical về URL tĩnh gốc trang 1 không query (M-3..M-7).
 */
final class Canonical implements ServiceProviderInterface {
    public function register(): void {
        add_filter('rank_math/frontend/canonical', [$this, 'filter_rank_math_canonical'], 20, 1);
        add_filter('get_canonical_url', [$this, 'filter_wp_canonical_url'], 20, 2);

        if (!drv_is_rank_math_active()) {
            remove_action('wp_head', 'rel_canonical');
            add_action('wp_head', [$this, 'print_fallback_canonical'], 5);
        }
    }

    /**
     * Thuật toán thuần giải bài toán Canonical cho mọi tổ hợp đường dẫn, phân trang & tham số lọc.
     *
     * @param array<string, mixed>      $query_params
     * @param array<string, mixed>|null $facets_config
     */
    public static function resolveCanonical(
        string $base_url,
        int $paged = 1,
        array $query_params = [],
        ?array $facets_config = null
    ): string {
        if ($base_url === '') {
            return '';
        }

        // Bóc bỏ query string, fragment và hậu tố `/page/{n}` có sẵn trên base_url.
        $parts = wp_parse_url($base_url);
        if (!is_array($parts)) {
            return UrlNormalizer::untrailingslashUrl($base_url);
        }

        $origin = '';
        if (isset($parts['scheme'], $parts['host'])) {
            $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        }

        $path = isset($parts['path']) ? (string) $parts['path'] : '/';
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        if (preg_match('#^(.*?)/page/[0-9]+$#i', $path, $m)) {
            $path = $m[1] !== '' ? $m[1] : '/';
        }

        $analysis          = UrlNormalizer::analyzeFilterParams($query_params, $facets_config);
        $has_search_or_flt = $analysis['has_any_filter']
            || $analysis['has_utility_param']
            || (isset($query_params['s']) && trim((string) $query_params['s']) !== '');

        // M-3..M-7: Nếu có tham số lọc/utility -> luôn trỏ về URL tĩnh trang 1, bỏ `/page/{n}`.
        if (!$has_search_or_flt && $paged >= 2) {
            $path = ($path === '/' ? '' : $path) . '/page/' . $paged;
        }

        if ($path === '/' || $path === '') {
            return $origin !== '' ? $origin : '/';
        }

        return $origin . $path;
    }

    public function filter_rank_math_canonical(string $canonical): string {
        $resolved = $this->compute_current_canonical($canonical);
        return $resolved !== '' ? $resolved : UrlNormalizer::untrailingslashUrl($canonical);
    }

    public function filter_wp_canonical_url(string $canonical_url, \WP_Post $post): string {
        unset($post);
        return UrlNormalizer::untrailingslashUrl($canonical_url);
    }

    public function print_fallback_canonical(): void {
        if (is_404() || is_search()) {
            return;
        }

        $canonical = $this->compute_current_canonical('');
        if ($canonical === '') {
            return;
        }

        printf('<link rel="canonical" href="%s">' . "\n", esc_url($canonical));
    }

    private function compute_current_canonical(string $fallback): string {
        if (is_404() || is_search()) {
            return '';
        }

        $paged        = max(1, (int) get_query_var('paged', 1), (int) get_query_var('page', 1));
        $query_params = $this->read_sanitized_query_params();
        $base_url     = '';

        if (is_front_page() && !is_home()) {
            $base_url = home_url('/');
            $paged    = 1;
        } elseif (is_home()) {
            $posts_page_id = (int) get_option('page_for_posts', 0);
            $base_url      = $posts_page_id > 0 ? (string) get_permalink($posts_page_id) : home_url('/cam-nang');
        } elseif (drv_is_woo_active() && is_shop()) {
            $base_url = home_url('/gach');
        } elseif (is_tax() || is_category() || is_tag()) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                if ($term->taxonomy === 'product_cat') {
                    $base_url = home_url('/' . $term->slug);
                } else {
                    $link     = get_term_link($term);
                    $base_url = is_string($link) ? $link : '';
                }
            }
        } elseif (is_singular()) {
            $post_id  = get_queried_object_id();
            $base_url = $post_id > 0 ? (string) get_permalink($post_id) : $fallback;
            $paged    = 1;
        } else {
            $base_url = $fallback;
        }

        return self::resolveCanonical($base_url, $paged, $query_params);
    }

    /**
     * @return array<string, mixed>
     */
    private function read_sanitized_query_params(): array {
        $raw_uri = isset($_SERVER['REQUEST_URI'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_URI']))
            : '';
        $qs      = (string) wp_parse_url($raw_uri, PHP_URL_QUERY);
        if ($qs === '') {
            return [];
        }

        $parsed = [];
        parse_str($qs, $parsed);
        return $parsed;
    }
}

