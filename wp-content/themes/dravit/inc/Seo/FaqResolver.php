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
                $meta_items = self::parseFaqItems(get_post_meta($post_id, 'drv_faq', true));
                if ($meta_items !== []) {
                    return $meta_items;
                }
                if (function_exists('is_singular') && is_singular('post') && function_exists('get_post')) {
                    $post = get_post($post_id);
                    if ($post instanceof \WP_Post && trim((string) $post->post_content) !== '') {
                        return self::extractFromPostContent((string) $post->post_content);
                    }
                }
            }
        }

        return [];
    }

    /**
     * Bóc tách danh sách câu hỏi FAQ từ nội dung bài viết Cẩm nang (`[drv_faq]` / `[drv_faq_item]` / `.drv-faq-group`)
     * ngay trong pha `wp_head` trước khi `the_content` chạy, đảm bảo `FAQPage` JSON-LD khớp 100% DOM hiển thị (docs/20 §3.1.B).
     *
     * @return array<int, array{q: string, a: string}>
     */
    public static function extractFromPostContent(string $raw_content): array {
        if (trim($raw_content) === '') {
            return [];
        }

        $collected = [];
        $seen_q    = [];
        $strip     = static fn (string $s): string => function_exists('wp_strip_all_tags')
            ? trim(wp_strip_all_tags($s))
            : trim(strip_tags($s));

        $push = static function (string $q, string $a) use (&$collected, &$seen_q, $strip): void {
            $clean_q = $strip(html_entity_decode($q, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $clean_a = trim($a);
            if ($clean_q === '' || $strip($clean_a) === '') {
                return;
            }
            $norm_key = mb_strtolower($clean_q, 'UTF-8');
            if (isset($seen_q[$norm_key])) {
                return;
            }
            $seen_q[$norm_key] = true;
            $collected[]       = ['q' => $clean_q, 'a' => $clean_a];
        };

        // 1. Shortcode `[drv_faq items="..."]` chứa mảng JSON.
        if (preg_match_all('/\[drv_faq\b([^\]]*)\]/iu', $raw_content, $faq_tags, PREG_SET_ORDER)) {
            foreach ($faq_tags as $tag_match) {
                $attr_str = $tag_match[1];
                if (preg_match('/\bitems\s*=\s*(["\'])(.*?)\1/isu', $attr_str, $im)) {
                    $json_str = html_entity_decode($im[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    foreach (self::parseFaqItems($json_str) as $item) {
                        $push($item['q'], $item['a']);
                    }
                }
            }
        }

        // 2. Shortcode lồng nhau `[drv_faq_item q="..." / question="..."]...[/drv_faq_item]`.
        if (preg_match_all('/\[drv_faq_item\b([^\]]*)\](.*?)\[\/drv_faq_item\]/isu', $raw_content, $item_matches, PREG_SET_ORDER)) {
            foreach ($item_matches as $im) {
                $attr_str = $im[1];
                $body     = trim($im[2]);
                $question = '';
                if (preg_match('/\b(?:q|question)\s*=\s*(["\'])(.*?)\1/isu', $attr_str, $qm)) {
                    $question = $qm[2];
                }
                if ($question !== '' && $body !== '') {
                    $push($question, $body);
                }
            }
        }

        // 3. HTML trực tiếp `<div class="...drv-faq-group...">...<details>...<summary>Q</summary>A</details>...</div>`.
        if (preg_match_all('/<div\b[^>]*class=(["\'])[^"\']*\bdrv-faq-group\b[^"\']*\1[^>]*>(.*?)<\/div>/isu', $raw_content, $group_matches)) {
            foreach ($group_matches[2] as $group_html) {
                if (preg_match_all('/<details\b[^>]*>\s*<summary\b[^>]*>(.*?)<\/summary>(.*?)<\/details>/isu', $group_html, $det_matches, PREG_SET_ORDER)) {
                    foreach ($det_matches as $dm) {
                        $push($dm[1], $dm[2]);
                    }
                }
            }
        }

        return $collected;
    }
}

