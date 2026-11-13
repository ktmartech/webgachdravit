<?php
declare(strict_types=1);

namespace Dravit\Seo;

use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Ma trận điều khiển Meta Robots `index/noindex, follow/nofollow` tập trung toàn site
 * (ADR D-21, docs/05 §6.2, docs/06 §1, Test M-1..M-10).
 */
final class RobotsMatrix implements ServiceProviderInterface {
    /** @var array<int, string> */
    private const NOINDEX_NOFOLLOW_SLUGS = [
        'gio-hang',
        'thanh-toan',
        'dat-hang-thanh-cong',
        'tai-khoan',
        'ui-kit',
    ];

    public function register(): void {
        add_filter('rank_math/frontend/robots', [$this, 'filter_rank_math_robots'], 20, 1);

        if (!drv_is_rank_math_active()) {
            add_filter('wp_robots', [$this, 'filter_wp_robots'], 20, 1);
        }
    }

    /**
     * Hàm thuần quyết định cặp chỉ thị `['index' => 'index'|'noindex', 'follow' => 'follow'|'nofollow']`.
     *
     * @param array<string, mixed>      $state
     * @param array<string, mixed>|null $facets_config
     * @return array{index: string, follow: string}
     */
    public static function resolveRobots(array $state, ?array $facets_config = null): array {
        $view             = (string) ($state['view'] ?? 'page');
        $page_slug        = strtolower(trim((string) ($state['page_slug'] ?? '')));
        $is_transactional = !empty($state['is_transactional']);
        $post_count       = array_key_exists('post_count', $state) && $state['post_count'] !== null
            ? (int) $state['post_count']
            : null;
        $paged            = max(1, (int) ($state['paged'] ?? 1));
        $max_pages        = array_key_exists('max_pages', $state) && $state['max_pages'] !== null
            ? (int) $state['max_pages']
            : null;
        $ai_enabled       = array_key_exists('ai_enabled', $state)
            ? (bool) $state['ai_enabled']
            : (bool) drv_setting('features.ai_enabled', false);
        $query_params     = isset($state['query_params']) && is_array($state['query_params'])
            ? $state['query_params']
            : [];

        // 1. 404, `pa_*` không public (M-9), trang giao dịch giỏ hàng/thanh toán/tài khoản, UI Kit -> noindex, nofollow.
        if (
            in_array($view, ['404', 'attribute_private'], true)
            || $is_transactional
            || in_array($page_slug, self::NOINDEX_NOFOLLOW_SLUGS, true)
        ) {
            return ['index' => 'noindex', 'follow' => 'nofollow'];
        }

        // 2. Phân tích tham số lọc & utility trên query string (M-3..M-7).
        $analysis = UrlNormalizer::analyzeFilterParams($query_params, $facets_config);
        if ($analysis['has_tech_filter'] || $analysis['has_utility_param']) {
            return ['index' => 'noindex', 'follow' => 'nofollow'];
        }
        if ($analysis['has_seo_filter']) {
            return ['index' => 'noindex', 'follow' => 'follow'];
        }

        // 3. Kết quả tìm kiếm nội bộ `/?s=...` -> noindex, follow.
        if ($view === 'search') {
            return ['index' => 'noindex', 'follow' => 'follow'];
        }

        // 4. Tag bài viết `/the/*`, archive tác giả, ngày tháng -> noindex, follow.
        if (in_array($view, ['tag', 'author', 'date'], true)) {
            return ['index' => 'noindex', 'follow' => 'follow'];
        }

        // 5. Trang hệ thống BOQ `/danh-sach-bao-gia` & Trợ lý AI khi tắt feature flag -> noindex, follow.
        if ($page_slug === 'danh-sach-bao-gia') {
            return ['index' => 'noindex', 'follow' => 'follow'];
        }
        if ($page_slug === 'tro-ly-ai-tim-gach' && !$ai_enabled) {
            return ['index' => 'noindex', 'follow' => 'follow'];
        }

        // 6. Danh mục sản phẩm / thuộc tính public / chuyên mục blog (M-1, M-2, M-8, M-10, R-9).
        if (in_array($view, ['collection', 'attribute_public', 'category'], true)) {
            if ($paged >= 2 && $max_pages !== null && ($paged > max(1, $max_pages) || $max_pages === 0)) {
                return ['index' => 'noindex', 'follow' => 'nofollow'];
            }
            if ($post_count !== null && $post_count <= 0) {
                return ['index' => 'noindex', 'follow' => 'follow'];
            }
            return ['index' => 'index', 'follow' => 'follow'];
        }

        return ['index' => 'index', 'follow' => 'follow'];
    }

    /**
     * @param array<string, string> $robots
     * @return array<string, string>
     */
    public function filter_rank_math_robots(array $robots): array {
        $resolved          = self::resolveRobots($this->capture_runtime_state());
        $robots['index']   = $resolved['index'];
        $robots['follow']  = $resolved['follow'];
        return $robots;
    }

    /**
     * @param array<string, bool|string> $robots
     * @return array<string, bool|string>
     */
    public function filter_wp_robots(array $robots): array {
        $resolved = self::resolveRobots($this->capture_runtime_state());

        unset($robots['index'], $robots['noindex'], $robots['follow'], $robots['nofollow']);
        $robots[$resolved['index']]  = true;
        $robots[$resolved['follow']] = true;

        return $robots;
    }

    /**
     * @return array<string, mixed>
     */
    private function capture_runtime_state(): array {
        global $wp_query;

        $view             = 'page';
        $page_slug        = '';
        $is_transactional = false;
        $post_count       = null;
        $max_pages        = null;
        $paged            = max(1, (int) get_query_var('paged', 1), (int) get_query_var('page', 1));

        if (drv_is_woo_active()) {
            if (is_cart() || is_checkout() || is_account_page() || is_wc_endpoint_url()) {
                $is_transactional = true;
            }
        }

        if (is_404()) {
            $view = '404';
        } elseif (is_search()) {
            $view = 'search';
        } elseif (is_front_page() && !is_home()) {
            $view = 'home';
        } elseif (is_home()) {
            $view = 'blog_index';
        } elseif (drv_is_woo_active() && is_shop()) {
            $view = 'collection';
            if ($wp_query instanceof \WP_Query) {
                $post_count = (int) $wp_query->found_posts;
                $max_pages  = (int) $wp_query->max_num_pages;
            }
        } elseif (drv_is_woo_active() && is_product_category()) {
            $view = 'collection';
            if ($wp_query instanceof \WP_Query) {
                $post_count = (int) $wp_query->found_posts;
                $max_pages  = (int) $wp_query->max_num_pages;
            }
        } elseif (is_tax()) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                if (in_array($term->taxonomy, ['pa_bo-suu-tap', 'pa_xuat-xu'], true)) {
                    $view = 'attribute_public';
                } elseif (str_starts_with($term->taxonomy, 'pa_')) {
                    $view = 'attribute_private';
                } else {
                    $view = 'collection';
                }
            }
            if ($wp_query instanceof \WP_Query) {
                $post_count = (int) $wp_query->found_posts;
                $max_pages  = (int) $wp_query->max_num_pages;
            }
        } elseif (is_category()) {
            $view = 'category';
            if ($wp_query instanceof \WP_Query) {
                $post_count = (int) $wp_query->found_posts;
                $max_pages  = (int) $wp_query->max_num_pages;
            }
        } elseif (is_tag()) {
            $view = 'tag';
        } elseif (is_author()) {
            $view = 'author';
        } elseif (is_date()) {
            $view = 'date';
        } elseif (drv_is_woo_active() && is_product()) {
            $view = 'product';
        } elseif (is_singular('post')) {
            $view = 'post';
        } elseif (is_page()) {
            $view = 'page';
            $obj  = get_queried_object();
            if ($obj instanceof \WP_Post) {
                $page_slug = $obj->post_name;
                if (is_page_template('page-templates/template-ui-kit.php')) {
                    $page_slug = 'ui-kit';
                }
            }
        }

        $raw_uri      = isset($_SERVER['REQUEST_URI'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_URI']))
            : '';
        $qs           = (string) wp_parse_url($raw_uri, PHP_URL_QUERY);
        $query_params = [];
        if ($qs !== '') {
            parse_str($qs, $query_params);
        }

        return [
            'view'             => $view,
            'page_slug'        => $page_slug,
            'is_transactional' => $is_transactional,
            'post_count'       => $post_count,
            'paged'            => $paged,
            'max_pages'        => $max_pages,
            'ai_enabled'       => (bool) drv_setting('features.ai_enabled', false),
            'query_params'     => $query_params,
        ];
    }
}
