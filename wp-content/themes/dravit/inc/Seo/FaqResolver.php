<?php
declare(strict_types=1);

namespace Dravit\Seo;

defined('ABSPATH') || exit;

/**
 * Bộ giải dữ liệu `drv_faq` & `drv_geo_table` dùng chung cho cả Template HTML và `SchemaGraph` (`wp_head`),
 * đảm bảo 100% `FAQPage` JSON-LD khớp tuyệt đối nội dung FAQ hiển thị thật trên trang (docs/06 §5.3, M-14).
 */
final class FaqResolver {
    /** @var array<int, array{q: string, a: string}> */
    private static array $registered_runtime_items = [];

    /**
     * Chuẩn hóa dữ liệu FAQ thô (JSON string hoặc array) thành danh sách cặp `{q, a}` hợp lệ.
     *
     * @return array<int, array{q: string, a: string}>
     */
    public static function parseFaqItems(mixed $raw): array {
        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '') {
                return [];
            }
            $decoded = json_decode($trimmed, true);
            $raw     = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $q = trim(wp_strip_all_tags((string) ($entry['q'] ?? ($entry['question'] ?? ''))));
            $a = trim((string) ($entry['a'] ?? ($entry['answer'] ?? '')));
            if ($q === '' || trim(wp_strip_all_tags($a)) === '') {
                continue;
            }
            $items[] = [
                'q' => $q,
                'a' => $a,
            ];
        }

        return $items;
    }

    /**
     * Chuẩn hóa dữ liệu bảng thông số GEO `drv_geo_table` (hỗ trợ cả `[{spec, val}]` và `{headers, rows, bullets}`).
     *
     * @return array{headers: array<int, string>, rows: array<int, array<int, string>>, bullets: array<int, string>}
     */
    public static function parseGeoTable(mixed $raw): array {
        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '') {
                return ['headers' => [], 'rows' => [], 'bullets' => []];
            }
            $decoded = json_decode($trimmed, true);
            $raw     = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($raw) || $raw === []) {
            return ['headers' => [], 'rows' => [], 'bullets' => []];
        }

        // Cấu trúc mở rộng `{headers: [...], rows: [...], bullets: [...]}`.
        if (isset($raw['rows']) && is_array($raw['rows'])) {
            $headers = isset($raw['headers']) && is_array($raw['headers'])
                ? array_values(array_map('strval', $raw['headers']))
                : ['Tiêu chí kỹ thuật', 'Thông số khuyến nghị'];
            $bullets = isset($raw['bullets']) && is_array($raw['bullets'])
                ? array_values(array_map('strval', $raw['bullets']))
                : [];
            $rows    = [];
            foreach ($raw['rows'] as $r) {
                if (!is_array($r)) {
                    continue;
                }
                if (isset($r['spec']) || isset($r['val'])) {
                    $spec = trim((string) ($r['spec'] ?? ''));
                    $val  = trim((string) ($r['val'] ?? ''));
                    if ($spec !== '' && $val !== '') {
                        $rows[] = [$spec, $val];
                    }
                } else {
                    $cells = array_values(array_map('strval', $r));
                    if (count($cells) >= 2 && trim($cells[0]) !== '') {
                        $rows[] = $cells;
                    }
                }
            }
            return ['headers' => $headers, 'rows' => $rows, 'bullets' => $bullets];
        }

        // Cấu trúc mặc định từ TermFields `[{"spec": "...", "val": "..."}]`.
        $rows = [];
        foreach ($raw as $r) {
            if (!is_array($r)) {
                continue;
            }
            $spec = trim((string) ($r['spec'] ?? ($r[0] ?? '')));
            $val  = trim((string) ($r['val'] ?? ($r[1] ?? '')));
            if ($spec !== '' && $val !== '') {
                $rows[] = [$spec, $val];
            }
        }

        return [
            'headers' => $rows !== [] ? ['Tiêu chí kỹ thuật', 'Thông số khuyến nghị'] : [],
            'rows'    => $rows,
            'bullets' => [],
        ];
    }

    /**
     * Ghi nhận thêm các mục FAQ render động (dùng trong kiểm thử hoặc trang tĩnh tùy biến).
     *
     * @param array<int, mixed> $items
     */
    public static function registerItems(array $items): void {
        foreach (self::parseFaqItems($items) as $parsed) {
            self::$registered_runtime_items[] = $parsed;
        }
    }

    /**
     * @return array<int, array{q: string, a: string}>
     */
    public static function getRegisteredItems(): array {
        return self::$registered_runtime_items;
    }

    public static function resetRegisteredItems(): void {
        self::$registered_runtime_items = [];
    }

    /**
     * Trả về danh sách FAQ sẽ hiển thị thật trên view hiện tại (chỉ trả trên trang 1 sạch không có param lọc).
     *
     * @return array<int, array{q: string, a: string}>
     */
    public static function resolveForCurrentView(): array {
        if (self::$registered_runtime_items !== []) {
            return self::$registered_runtime_items;
        }

        $paged = function_exists('get_query_var')
            ? max(1, (int) get_query_var('paged', 1), (int) get_query_var('page', 1))
            : 1;
        if ($paged >= 2) {
            return [];
        }

        $raw_uri = isset($_SERVER['REQUEST_URI'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_URI']))
            : '';
        $qs      = (string) wp_parse_url($raw_uri, PHP_URL_QUERY);
        if ($qs !== '') {
            $query_params = [];
            parse_str($qs, $query_params);
            $analysis = UrlNormalizer::analyzeFilterParams($query_params);
            if ($analysis['has_any_filter'] || $analysis['has_utility_param']) {
                return [];
            }
        }

        if (drv_is_woo_active() && function_exists('is_shop') && is_shop()) {
            $gach_term = get_term_by('slug', 'gach', 'product_cat');
            if ($gach_term instanceof \WP_Term) {
                return self::parseFaqItems(get_term_meta($gach_term->term_id, 'drv_faq', true));
            }
            return [];
        }

        if (function_exists('is_tax') && (is_tax('product_cat') || is_tax(['pa_bo-suu-tap', 'pa_xuat-xu']))) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                return self::parseFaqItems(get_term_meta($term->term_id, 'drv_faq', true));
            }
        }

        if (function_exists('is_singular') && is_singular()) {
            $post_id = get_queried_object_id();
            if ($post_id > 0) {
                return self::parseFaqItems(get_post_meta($post_id, 'drv_faq', true));
            }
        }

        return [];
    }
}

