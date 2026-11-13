<?php
declare(strict_types=1);

namespace Dravit\Seo;

use Dravit\Core\Config;

defined('ABSPATH') || exit;

/**
 * Lõi chuẩn hóa URL không dấu `/` cuối, ngữ pháp Query bộ lọc 14 Facets và Redirect 301 Silo (ADR D-21, docs/05 §3, Test R-1..R-10).
 */
final class UrlNormalizer {
    /** @var array<int, string> */
    private const TRACKING_EXACT = [
        'gclid',
        'fbclid',
        'zarsrc',
        'msclkid',
        'ttclid',
    ];

    /** @var array<int, string> */
    private const UTILITY_PARAMS = [
        'quote',
        'ai_query',
    ];

    /** @var array<int, string> */
    private const NON_PUBLIC_PA_BASES = [
        'cong-nang',
        'khong-gian',
        'van-gach',
        'chat-lieu',
        'kich-thuoc',
        'mau-sac',
        'be-mat',
        'phong-cach',
        'hinh-dang',
        'dcof',
        'do-hut-nuoc',
        'do-day',
    ];

    public static function isTrackingParam(string $key): bool {
        $lower = strtolower($key);
        return str_starts_with($lower, 'utm_') || in_array($lower, self::TRACKING_EXACT, true);
    }

    public static function isUtilityParam(string $key): bool {
        return in_array(strtolower($key), self::UTILITY_PARAMS, true);
    }

    /**
     * Bóc tách bản đồ cấu hình tham số lọc từ `config/facets.php`.
     *
     * @param array<string, mixed>|null $facets_config
     * @return array<string, array{param: string, order: int, group: string, taxonomy: string, terms: array<string, mixed>, redirect_map: array<string, string>, static_map: array<string, string>}>
     */
    public static function buildFacetParamMap(?array $facets_config = null): array {
        $cfg = $facets_config ?? Config::get('facets');
        $map = [];

        $attrs = isset($cfg['attributes']) && is_array($cfg['attributes']) ? $cfg['attributes'] : [];
        foreach ($attrs as $key => $def) {
            if (!is_array($def)) {
                continue;
            }
            $param       = (string) ($def['param'] ?? $key);
            $static_map  = isset($def['static_map']) && is_array($def['static_map']) ? $def['static_map'] : [];
            $redir_map   = isset($def['redirect_map']) && is_array($def['redirect_map']) ? $def['redirect_map'] : $static_map;
            $map[$param] = [
                'param'        => $param,
                'order'        => (int) ($def['order'] ?? 50),
                'group'        => (string) ($def['group'] ?? 'seo'),
                'taxonomy'     => (string) ($def['taxonomy'] ?? ('pa_' . $key)),
                'terms'        => isset($def['terms']) && is_array($def['terms']) ? $def['terms'] : [],
                'redirect_map' => $redir_map,
                'static_map'   => $static_map,
            ];
        }

        $internal = isset($cfg['internal_taxonomies']) && is_array($cfg['internal_taxonomies']) ? $cfg['internal_taxonomies'] : [];
        foreach ($internal as $tax => $def) {
            if (!is_array($def)) {
                continue;
            }
            $param       = (string) ($def['param'] ?? $tax);
            $map[$param] = [
                'param'        => $param,
                'order'        => (int) ($def['order'] ?? 130),
                'group'        => (string) ($def['group'] ?? 'tech'),
                'taxonomy'     => (string) $tax,
                'terms'        => isset($def['terms']) && is_array($def['terms']) ? $def['terms'] : [],
                'redirect_map' => [],
                'static_map'   => [],
            ];
        }

        return $map;
    }

    /**
     * Phân loại các tham số query hiện tại để phục vụ `RobotsMatrix`, `Canonical` và `SchemaGraph`.
     *
     * @param array<string, mixed>      $query_params
     * @param array<string, mixed>|null $facets_config
     * @return array{has_any_filter: bool, has_seo_filter: bool, has_tech_filter: bool, has_utility_param: bool, seo_params: array<int, string>, tech_params: array<int, string>}
     */
    public static function analyzeFilterParams(array $query_params, ?array $facets_config = null): array {
        $facet_map   = self::buildFacetParamMap($facets_config);
        $seo_params  = [];
        $tech_params = [];
        $has_utility = false;

        foreach ($query_params as $raw_key => $raw_val) {
            $key = strtolower((string) $raw_key);
            if ($raw_val === '' || $raw_val === null || $raw_val === []) {
                continue;
            }

            if (self::isTrackingParam($key)) {
                continue;
            }

            if (self::isUtilityParam($key)) {
                $has_utility = true;
                continue;
            }

            if ($key === 'sort') {
                $tech_params[] = 'sort';
                continue;
            }

            if (isset($facet_map[$key])) {
                if ($facet_map[$key]['group'] === 'tech') {
                    $tech_params[] = $key;
                } else {
                    $seo_params[] = $key;
                }
            }
        }

        $seo_params  = array_values(array_unique($seo_params));
        $tech_params = array_values(array_unique($tech_params));

        return [
            'has_any_filter'    => ($seo_params !== [] || $tech_params !== []),
            'has_seo_filter'    => ($seo_params !== []),
            'has_tech_filter'   => ($tech_params !== []),
            'has_utility_param' => $has_utility,
            'seo_params'        => $seo_params,
            'tech_params'       => $tech_params,
        ];
    }

    /**
     * Đánh giá một request frontend và trả về quyết định (`ok` | `redirect` | `404`).
     *
     * @param array<string, mixed> $query_params
     * @param array<string, mixed> $context
     * @return array{action: string, status: int, location: ?string}
     */
    public static function evaluateRequest(
        string $request_path,
        array $query_params = [],
        array $context = []
    ): array {
        if (!empty($context['is_non_public_pa_archive'])) {
            return ['action' => '404', 'status' => 404, 'location' => null];
        }

        $path = $request_path !== '' ? $request_path : '/';
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        if (self::shouldBypassPath($path, $query_params)) {
            return ['action' => 'ok', 'status' => 200, 'location' => null];
        }

        $facets_cfg      = isset($context['facets_config']) && is_array($context['facets_config'])
            ? $context['facets_config']
            : Config::get('facets');
        $collections_cfg = isset($context['collections_config']) && is_array($context['collections_config'])
            ? $context['collections_config']
            : Config::get('collections');

        $needs_redirect = false;
        $clean_path     = $path;

        // R-1: Khử dấu `/` cuối trên mọi đường dẫn khác `/`.
        if ($clean_path !== '/' && str_ends_with($clean_path, '/')) {
            $clean_path     = rtrim($clean_path, '/');
            $needs_redirect = true;
        }
        if ($clean_path === '') {
            $clean_path = '/';
        }

        // Tách hậu tố phân trang `/page/{n}` nếu có.
        $path_paged = 1;
        $base_path  = $clean_path;
        if (preg_match('#^(.*?)/page/([0-9]+)$#i', $clean_path, $pm)) {
            $base_path  = $pm[1] !== '' ? $pm[1] : '/';
            $path_paged = (int) $pm[2];
            if ($path_paged <= 1) {
                $path_paged     = 1;
                $needs_redirect = true;
            }
        }

        // Chuyển hướng đường dẫn hệ thống & WooCommerce cũ (R-2, R-10, D-29).
        if ($base_path === '/showroom') {
            $base_path      = '/lien-he';
            $path_paged     = 1;
            $needs_redirect = true;
        } elseif ($base_path === '/san-pham') {
            $base_path      = '/gach';
            $needs_redirect = true;
        } elseif (preg_match('#^/product-category/(.+)$#i', $base_path, $cm)) {
            $segments       = array_values(array_filter(explode('/', trim($cm[1], '/'))));
            $leaf           = $segments !== [] ? end($segments) : 'gach';
            $base_path      = '/' . strtolower($leaf);
            $needs_redirect = true;
        }

        // M-9: Chặn truy cập trực tiếp archive của 12 thuộc tính `pa_*` không có trang tĩnh.
        if (preg_match('#^/([a-z0-9\-]+)/([a-z0-9\-]+)$#i', $base_path, $nm)) {
            $prefix = strtolower($nm[1]);
            if (in_array($prefix, self::NON_PUBLIC_PA_BASES, true)) {
                return ['action' => '404', 'status' => 404, 'location' => null];
            }
        }

        // Nhận diện ngữ cảnh Catalog Archive (`/gach`, 27 collections, `/bo-suu-tap/{slug}`, `/xuat-xu/{slug}`).
        $archive_info = self::detectCatalogArchive($base_path, $collections_cfg);

        // Xử lý `?page={n}` / `?paged={n}` trên Catalog Archive.
        $working_query = $query_params;
        if ($archive_info !== null) {
            foreach (['page', 'paged'] as $p_key) {
                if (array_key_exists($p_key, $working_query)) {
                    $q_page = (int) $working_query[$p_key];
                    unset($working_query[$p_key]);
                    $needs_redirect = true;
                    if ($q_page >= 2 && $path_paged <= 1) {
                        $path_paged = $q_page;
                    }
                }
            }
        }

        // R-9: Trang phân trang vượt quá `max_pages` -> trả HTTP 404 thật.
        if ($path_paged >= 2 && array_key_exists('max_pages', $context) && $context['max_pages'] !== null) {
            $max_pages = max(0, (int) $context['max_pages']);
            if ($path_paged > max(1, $max_pages) || $max_pages === 0) {
                return ['action' => '404', 'status' => 404, 'location' => null];
            }
        }

        if ($archive_info === null) {
            $final_non_cat_path = $base_path === '/'
                ? ($path_paged >= 2 ? '/page/' . $path_paged : '/')
                : $base_path . ($path_paged >= 2 ? '/page/' . $path_paged : '');

            if ($needs_redirect || $final_non_cat_path !== $request_path) {
                $qs = self::buildStandardQueryString($working_query);
                return [
                    'action'   => 'redirect',
                    'status'   => 301,
                    'location' => $final_non_cat_path . ($qs !== '' ? '?' . $qs : ''),
                ];
            }

            return ['action' => 'ok', 'status' => 200, 'location' => null];
        }

        // Chuẩn hóa toàn bộ tham số lọc trên Catalog Archive (R-3..R-8).
        $facet_map       = self::buildFacetParamMap($facets_cfg);
        $sort_options    = isset($facets_cfg['sort_options']) && is_array($facets_cfg['sort_options'])
            ? $facets_cfg['sort_options']
            : [];
        $clean_facets    = [];
        $clean_sort      = null;
        $preserved_extra = [];

        foreach ($working_query as $raw_key => $raw_val) {
            $orig_key = (string) $raw_key;
            $key      = strtolower($orig_key);
            if ($key !== $orig_key) {
                $needs_redirect = true;
            }

            if (self::isTrackingParam($key) || self::isUtilityParam($key)) {
                $str_val = is_scalar($raw_val) ? trim((string) $raw_val) : '';
                if ($str_val !== '') {
                    $preserved_extra[$orig_key] = $str_val;
                } else {
                    $needs_redirect = true;
                }
                continue;
            }

            if ($key === 'sort') {
                $sort_val = is_scalar($raw_val) ? strtolower(trim((string) $raw_val)) : '';
                if ($sort_val !== '' && $sort_val !== 'moi-ve' && isset($sort_options[$sort_val])) {
                    $clean_sort = $sort_val;
                    if ((string) $raw_val !== $sort_val) {
                        $needs_redirect = true;
                    }
                } else {
                    $needs_redirect = true;
                }
                continue;
            }

            if (!isset($facet_map[$key])) {
                // R-6: Tham số rác không thuộc whitelist -> loại bỏ.
                $needs_redirect = true;
                continue;
            }

            $raw_str = is_array($raw_val)
                ? implode(',', array_map('strval', $raw_val))
                : (string) $raw_val;

            $pieces = explode(',', $raw_str);
            $valid  = [];
            foreach ($pieces as $piece) {
                $slug = strtolower(trim($piece));
                if ($slug === '') {
                    $needs_redirect = true;
                    continue;
                }

                $normalized_slug = self::validateAndNormalizeTermSlug($key, $slug, $facet_map[$key]);
                if ($normalized_slug === null) {
                    // R-8: Giá trị lọc không tồn tại -> loại bỏ.
                    $needs_redirect = true;
                    continue;
                }

                // Khử giá trị trùng chiều 1 của chính trang danh mục hiện tại.
                if (
                    $archive_info['locked_param'] === $key
                    && $archive_info['locked_value'] === $normalized_slug
                ) {
                    $needs_redirect = true;
                    continue;
                }

                $valid[$normalized_slug] = true;
            }

            $deduped = array_map('strval', array_keys($valid));
            sort($deduped, SORT_STRING);

            if ($deduped === []) {
                $needs_redirect = true;
                continue;
            }

            if (implode(',', $deduped) !== $raw_str) {
                $needs_redirect = true;
            }

            $clean_facets[$key] = $deduped;
        }

        // R-3: Ở `/gach` (trang 1, không sort) chọn đúng 1 giá trị của đúng 1 facet có trang tĩnh -> 301 về URL tĩnh.
        if (
            $archive_info['type'] === 'collection'
            && $archive_info['slug'] === 'gach'
            && $path_paged === 1
            && $clean_sort === null
            && count($clean_facets) === 1
        ) {
            $only_param = (string) array_key_first($clean_facets);
            $only_vals  = $clean_facets[$only_param];
            if (count($only_vals) === 1) {
                $single_val  = $only_vals[0];
                $static_dest = self::resolveSingleStaticTarget($only_param, $single_val, $facet_map[$only_param]);
                if ($static_dest !== null) {
                    $base_path      = $static_dest;
                    $clean_facets   = [];
                    $needs_redirect = true;
                }
            }
        }

        // R-4: Sắp xếp thứ tự tham số lọc cố định theo `order` trong `config/facets.php`.
        uksort($clean_facets, static function (string $a, string $b) use ($facet_map): int {
            return ($facet_map[$a]['order'] ?? 999) <=> ($facet_map[$b]['order'] ?? 999);
        });

        $ordered_pairs = [];
        foreach ($clean_facets as $f_key => $f_vals) {
            $ordered_pairs[] = $f_key . '=' . implode(',', $f_vals);
        }
        if ($clean_sort !== null) {
            $ordered_pairs[] = 'sort=' . $clean_sort;
        }
        foreach ($preserved_extra as $e_key => $e_val) {
            $ordered_pairs[] = rawurlencode($e_key) . '=' . rawurlencode($e_val);
        }

        $final_qs   = implode('&', $ordered_pairs);
        $final_path = $base_path . ($path_paged >= 2 ? '/page/' . $path_paged : '');

        $incoming_order_sig = self::buildIncomingSignature($query_params);
        $final_order_sig    = self::buildFinalSignature($clean_facets, $clean_sort, $preserved_extra);

        if ($needs_redirect || $final_path !== $request_path || $incoming_order_sig !== $final_order_sig) {
            return [
                'action'   => 'redirect',
                'status'   => 301,
                'location' => $final_path . ($final_qs !== '' ? '?' . $final_qs : ''),
            ];
        }

        return ['action' => 'ok', 'status' => 200, 'location' => null];
    }

    /**
     * Khử dấu `/` cuối trên một URL đầy đủ (trừ đúng trang chủ gốc `/`).
     */
    public static function untrailingslashUrl(string $url): string {
        if ($url === '' || $url === '/') {
            return $url;
        }

        $parts = wp_parse_url($url);
        if (!is_array($parts)) {
            return rtrim($url, '/');
        }

        $path = $parts['path'] ?? '';
        if ($path === '' || $path === '/') {
            // Nếu URL là gốc domain `https://gachdravit.vn/` -> chuẩn hóa không dấu `/` cuối `https://gachdravit.vn`.
            if (isset($parts['scheme'], $parts['host']) && empty($parts['query']) && empty($parts['fragment'])) {
                return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
            }
            return $url;
        }

        $clean_path = rtrim($path, '/');
        $rebuilt    = '';
        if (isset($parts['scheme'], $parts['host'])) {
            $rebuilt .= $parts['scheme'] . '://' . $parts['host'];
            if (isset($parts['port'])) {
                $rebuilt .= ':' . $parts['port'];
            }
        }
        $rebuilt .= $clean_path;
        if (isset($parts['query']) && $parts['query'] !== '') {
            $rebuilt .= '?' . $parts['query'];
        }
        if (isset($parts['fragment']) && $parts['fragment'] !== '') {
            $rebuilt .= '#' . $parts['fragment'];
        }

        return $rebuilt;
    }

    /**
     * @param array<string, mixed> $query_params
     */
    private static function shouldBypassPath(string $path, array $query_params): bool {
        if (isset($query_params['wc-ajax']) || isset($query_params['preview'])) {
            return true;
        }

        $bypass_prefixes = [
            '/wp-admin',
            '/wp-json',
            '/wp-login.php',
            '/xmlrpc.php',
            '/wc-api',
            '/llms.txt',
            '/robots.txt',
        ];

        foreach ($bypass_prefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        if (str_ends_with($path, '.xml') || str_ends_with($path, '.xsl')) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $collections_cfg
     * @return array{type: string, slug: string, locked_param: ?string, locked_value: ?string}|null
     */
    private static function detectCatalogArchive(string $base_path, array $collections_cfg): ?array {
        $trimmed = ltrim($base_path, '/');
        if ($trimmed !== '' && !str_contains($trimmed, '/') && isset($collections_cfg[$trimmed])) {
            $locked_param = null;
            $locked_value = null;

            $rule = $collections_cfg[$trimmed]['rule'] ?? [];
            if (is_array($rule) && isset($rule['any']) && is_array($rule['any']) && count($rule['any']) === 1) {
                $cond = $rule['any'][0];
                if (
                    is_array($cond)
                    && isset($cond['attr'], $cond['in'])
                    && is_array($cond['in'])
                    && count($cond['in']) === 1
                ) {
                    $attr_name    = (string) $cond['attr'];
                    $locked_param = str_starts_with($attr_name, 'pa_') ? substr($attr_name, 3) : $attr_name;
                    $locked_value = (string) $cond['in'][0];
                }
            }

            return [
                'type'         => 'collection',
                'slug'         => $trimmed,
                'locked_param' => $locked_param,
                'locked_value' => $locked_value,
            ];
        }

        if (preg_match('#^/(bo-suu-tap|xuat-xu)/([a-z0-9\-]+)$#i', $base_path, $m)) {
            $attr_base = strtolower($m[1]);
            $term_slug = strtolower($m[2]);
            return [
                'type'         => 'attribute',
                'slug'         => $term_slug,
                'locked_param' => $attr_base,
                'locked_value' => $term_slug,
            ];
        }

        return null;
    }

    /**
     * @param array{param: string, order: int, group: string, taxonomy: string, terms: array<string, mixed>, redirect_map: array<string, string>, static_map: array<string, string>} $facet_def
     */
    private static function validateAndNormalizeTermSlug(string $param, string $slug, array $facet_def): ?string {
        if ($param === 'showroom') {
            return in_array($slug, ['1', 'showroom'], true) ? '1' : null;
        }

        if (isset($facet_def['terms'][$slug])) {
            return $slug;
        }

        $tax = $facet_def['taxonomy'];
        if (function_exists('taxonomy_exists') && function_exists('term_exists') && taxonomy_exists($tax)) {
            $exists = term_exists($slug, $tax);
            if ($exists !== null && $exists !== 0) {
                return $slug;
            }
        }

        return null;
    }

    /**
     * @param array{param: string, order: int, group: string, taxonomy: string, terms: array<string, mixed>, redirect_map: array<string, string>, static_map: array<string, string>} $facet_def
     */
    private static function resolveSingleStaticTarget(string $param, string $value, array $facet_def): ?string {
        if ($param === 'bo-suu-tap') {
            return '/bo-suu-tap/' . $value;
        }

        if ($param === 'xuat-xu') {
            return '/xuat-xu/' . $value;
        }

        if (isset($facet_def['redirect_map'][$value])) {
            return '/' . ltrim((string) $facet_def['redirect_map'][$value], '/');
        }

        return null;
    }

    /**
     * @param array<string, mixed> $query_params
     */
    private static function buildIncomingSignature(array $query_params): string {
        $parts = [];
        foreach ($query_params as $k => $v) {
            if (is_array($v)) {
                $parts[] = $k . '=' . implode(',', array_map('strval', $v));
            } else {
                $parts[] = $k . '=' . (string) $v;
            }
        }
        return implode('&', $parts);
    }

    /**
     * @param array<string, array<int, string>> $clean_facets
     * @param array<string, string>             $preserved_extra
     */
    private static function buildFinalSignature(array $clean_facets, ?string $clean_sort, array $preserved_extra): string {
        $parts = [];
        foreach ($clean_facets as $k => $vals) {
            $parts[] = $k . '=' . implode(',', $vals);
        }
        if ($clean_sort !== null) {
            $parts[] = 'sort=' . $clean_sort;
        }
        foreach ($preserved_extra as $k => $val) {
            $parts[] = $k . '=' . $val;
        }
        return implode('&', $parts);
    }

    /**
     * @param array<string, mixed> $query_params
     */
    private static function buildStandardQueryString(array $query_params): string {
        if ($query_params === []) {
            return '';
        }
        return http_build_query($query_params, '', '&', PHP_QUERY_RFC3986);
    }
}
