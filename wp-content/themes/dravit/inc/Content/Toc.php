<?php
declare(strict_types=1);

namespace Dravit\Content;

use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Bộ phân tích Heading Server-side sinh Mục lục tự động `<drv-toc>` (docs/20 §5.1, Test `TocGeneratorTest`):
 * - Chạy ở `the_content` priority 12 (sau `do_shortcode`).
 * - Loại trừ heading nằm trong các hộp nhúng (`.drv-key-takeaways`, `.drv-faq-group`, `.drv-card`, `.drv-howto-grid`...).
 * - Tự ẩn TOC nếu số thẻ `<h2>` hợp lệ < ngưỡng tối thiểu (`3` trên bài Cẩm nang, `2` trên trang Chính sách).
 * - Sinh `id` ASCII tất định không dấu và tự thêm hậu tố `-2`, `-3` khi trùng tiêu đề.
 */
final class Toc implements ServiceProviderInterface {
    /** @var array<int, array{id: string, text: string, level: int}> */
    private static array $current_items = [];

    /**
     * Các class container nhúng mà heading bên trong KHÔNG được đưa vào Mục lục bài viết.
     *
     * @var array<int, string>
     */
    private const EXCLUDED_CLASS_MARKERS = [
        'drv-key-takeaways',
        'drv-takeaways',
        'drv-faq-group',
        'drv-faq',
        'drv-card',
        'drv-product-embed-box',
        'drv-inline-lead-box',
        'drv-inline-related',
        'drv-blog-ai-card',
        'drv-howto-grid',
        'drv-author-box',
    ];

    public function register(): void {
        add_filter('the_content', [$this, 'filter_the_content'], 12);
    }

    public function filter_the_content(string $content): string {
        if (is_admin() || !function_exists('is_main_query') || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        if (function_exists('is_singular') && is_singular('post')) {
            $post_id = (int) get_the_ID();
            $enabled = $post_id > 0 ? (string) get_post_meta($post_id, 'drv_toc_enabled', true) : '1';
            if ($enabled === '0') {
                self::$current_items = [];
                return $content;
            }

            $result              = self::processHtml($content, 2);
            self::$current_items = $result['items'];

            return $result['html'];
        }

        if (function_exists('is_page_template') && is_page_template('page-templates/template-policy.php')) {
            $result              = self::processHtml($content, 2);
            self::$current_items = $result['items'];

            return $result['html'];
        }

        return $content;
    }

    /**
     * @return array<int, array{id: string, text: string, level: int}>
     */
    public static function getCurrentItems(): array {
        return self::$current_items;
    }

    public static function resetCurrentItems(): void {
        self::$current_items = [];
    }

    /**
     * Tính trước danh sách mục lục cho trường hợp cột trái (như `template-policy.php` 3/9)
     * cần hiển thị `<drv-toc>` trước khi `the_content()` ở cột phải được gọi.
     *
     * @return array<int, array{id: string, text: string, level: int}>
     */
    public static function previewItemsFromContent(string $raw_content, int $min_h2 = 2): array {
        $expanded = function_exists('do_shortcode') ? do_shortcode($raw_content) : $raw_content;
        $result   = self::processHtml($expanded, $min_h2);

        return $result['items'];
    }

    /**
     * Hàm thuần phân tích HTML, gắn `id` cho `<h2>`/`<h3>` hợp lệ và trả danh sách mục lục.
     *
     * @return array{html: string, items: array<int, array{id: string, text: string, level: int}>}
     */
    public static function processHtml(string $html, int $min_h2 = 2): array {
        if ($html === '' || stripos($html, '<h2') === false) {
            return ['html' => $html, 'items' => []];
        }

        $excluded_ranges = self::findExcludedContainerRanges($html);

        $pattern = '/<h([23])(\s[^>]*)?>(.*?)<\/h\1>/isu';
        if (preg_match_all($pattern, $html, $matches, PREG_OFFSET_CAPTURE) === false || $matches[0] === []) {
            return ['html' => $html, 'items' => []];
        }

        $candidates = [];
        $h2_count   = 0;

        foreach ($matches[0] as $idx => $full_match) {
            $offset   = (int) $full_match[1];
            $full_tag = (string) $full_match[0];
            $level    = (int) $matches[1][$idx][0];
            $attrs    = isset($matches[2][$idx][0]) ? (string) $matches[2][$idx][0] : '';
            $inner    = (string) $matches[3][$idx][0];

            if (self::isOffsetInRanges($offset, $excluded_ranges) || self::hasExcludedClass($attrs)) {
                continue;
            }

            $plain_text = self::cleanHeadingText($inner);
            if ($plain_text === '') {
                continue;
            }

            if ($level === 2) {
                $h2_count++;
            }

            $candidates[] = [
                'offset'   => $offset,
                'length'   => strlen($full_tag),
                'level'    => $level,
                'attrs'    => $attrs,
                'inner'    => $inner,
                'text'     => $plain_text,
            ];
        }

        if ($h2_count < max(1, $min_h2)) {
            return ['html' => $html, 'items' => []];
        }

        /** @var array<string, int> $seen_ids */
        $seen_ids     = [];
        $items        = [];
        $replacements = [];

        foreach ($candidates as $cand) {
            $existing_id = self::extractExistingId($cand['attrs']);
            $base_slug   = $existing_id !== ''
                ? self::slugify($existing_id)
                : self::slugify($cand['text']);

            if ($base_slug === '') {
                $base_slug = 'muc';
            }

            if (!isset($seen_ids[$base_slug])) {
                $seen_ids[$base_slug] = 1;
                $final_id             = $base_slug;
            } else {
                $seen_ids[$base_slug]++;
                $suffix   = $seen_ids[$base_slug];
                $final_id = $base_slug . '-' . $suffix;
                while (isset($seen_ids[$final_id])) {
                    $suffix++;
                    $final_id = $base_slug . '-' . $suffix;
                }
                $seen_ids[$final_id] = 1;
            }

            $new_attrs = self::injectIdAttribute($cand['attrs'], $final_id);
            $new_tag   = sprintf('<h%d%s>%s</h%d>', $cand['level'], $new_attrs, $cand['inner'], $cand['level']);

            $replacements[] = [
                'offset' => $cand['offset'],
                'length' => $cand['length'],
                'tag'    => $new_tag,
            ];

            $items[] = [
                'id'    => $final_id,
                'text'  => $cand['text'],
                'level' => $cand['level'],
            ];
        }

        // Thay thế ngược từ cuối chuỗi về đầu để không lệch byte offset.
        $updated_html = $html;
        for ($i = count($replacements) - 1; $i >= 0; $i--) {
            $rep          = $replacements[$i];
            $updated_html = substr_replace($updated_html, $rep['tag'], $rep['offset'], $rep['length']);
        }

        return [
            'html'  => $updated_html,
            'items' => $items,
        ];
    }

    /**
     * Chuẩn hóa chuỗi tiêu đề sang anchor ID ASCII không dấu, tối đa 60 ký tự.
     */
    public static function slugify(string $text): string {
        $folded = self::asciiFoldPreserveNumbers($text);
        $slug   = (string) preg_replace('/[^a-z0-9]+/', '-', $folded);
        $slug   = trim($slug, '-');

        if (strlen($slug) > 60) {
            $cut  = substr($slug, 0, 60);
            $last = strrpos($cut, '-');
            $slug = ($last !== false && $last >= 24) ? substr($cut, 0, $last) : rtrim($cut, '-');
        }

        return $slug;
    }

    private static function cleanHeadingText(string $inner_html): string {
        $stripped = function_exists('wp_strip_all_tags')
            ? wp_strip_all_tags($inner_html)
            : strip_tags($inner_html);
        $decoded  = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $decoded));
    }

    private static function extractExistingId(string $attrs): string {
        if ($attrs === '') {
            return '';
        }
        if (preg_match('/\bid\s*=\s*(["\'])([^"\']+)\1/i', $attrs, $m) === 1) {
            return trim($m[2]);
        }

        return '';
    }

    private static function injectIdAttribute(string $attrs, string $id): string {
        $safe_id = function_exists('esc_attr') ? esc_attr($id) : htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        if ($attrs !== '' && preg_match('/\bid\s*=\s*(["\'])[^"\']*\1/i', $attrs) === 1) {
            return (string) preg_replace('/\bid\s*=\s*(["\'])[^"\']*\1/i', 'id="' . $safe_id . '"', $attrs, 1);
        }

        return ' id="' . $safe_id . '"' . $attrs;
    }

    private static function hasExcludedClass(string $attrs): bool {
        if ($attrs === '' || preg_match('/\bclass\s*=\s*(["\'])([^"\']+)\1/i', $attrs, $m) !== 1) {
            return false;
        }
        $class_str = ' ' . strtolower($m[2]) . ' ';
        foreach (self::EXCLUDED_CLASS_MARKERS as $marker) {
            if (str_contains($class_str, ' ' . $marker . ' ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tìm phạm vi byte `[start, end]` của các khối container nhúng không thuộc luồng tiêu đề chính.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    private static function findExcludedContainerRanges(string $html): array {
        $tag_pattern = '/<(\/?)(section|aside|article|div|details)\b([^>]*)>/iu';
        if (preg_match_all($tag_pattern, $html, $tokens, PREG_OFFSET_CAPTURE) === false || $tokens[0] === []) {
            return [];
        }

        $ranges = [];
        /** @var array<int, array{tag: string, excluded: bool, start: int}> $stack */
        $stack  = [];

        foreach ($tokens[0] as $i => $tok) {
            $offset     = (int) $tok[1];
            $tag_len    = strlen((string) $tok[0]);
            $is_closing = $tokens[1][$i][0] === '/';
            $tag_name   = strtolower((string) $tokens[2][$i][0]);
            $attrs      = (string) $tokens[3][$i][0];

            if (!$is_closing) {
                $stack[] = [
                    'tag'      => $tag_name,
                    'excluded' => self::hasExcludedClass($attrs),
                    'start'    => $offset,
                ];
                continue;
            }

            // Tìm thẻ mở khớp gần nhất trên stack.
            for ($s = count($stack) - 1; $s >= 0; $s--) {
                if ($stack[$s]['tag'] === $tag_name) {
                    $opened = $stack[$s];
                    array_splice($stack, $s);
                    if ($opened['excluded']) {
                        $ranges[] = [$opened['start'], $offset + $tag_len];
                    }
                    break;
                }
            }
        }

        return $ranges;
    }

    /**
     * @param array<int, array{0: int, 1: int}> $ranges
     */
    private static function isOffsetInRanges(int $offset, array $ranges): bool {
        foreach ($ranges as [$start, $end]) {
            if ($offset >= $start && $offset <= $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * Khử dấu tiếng Việt sang chữ thường ASCII nhưng giữ nguyên con số gốc (không đổi `800x800` sang `80x80`).
     */
    private static function asciiFoldPreserveNumbers(string $text): string {
        $str = mb_strtolower(trim($text), 'UTF-8');
        $map = [
            'à' => 'a', 'á' => 'a', 'ạ' => 'a', 'ả' => 'a', 'ã' => 'a',
            'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ậ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a',
            'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ặ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a',
            'è' => 'e', 'é' => 'e', 'ẹ' => 'e', 'ẻ' => 'e', 'ẽ' => 'e',
            'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ệ' => 'e', 'ể' => 'e', 'ễ' => 'e',
            'ì' => 'i', 'í' => 'i', 'ị' => 'i', 'ỉ' => 'i', 'ĩ' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ọ' => 'o', 'ỏ' => 'o', 'õ' => 'o',
            'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ộ' => 'o', 'ổ' => 'o', 'ỗ' => 'o',
            'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ợ' => 'o', 'ở' => 'o', 'ỡ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'ụ' => 'u', 'ủ' => 'u', 'ũ' => 'u',
            'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ự' => 'u', 'ử' => 'u', 'ữ' => 'u',
            'ỳ' => 'y', 'ý' => 'y', 'ỵ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y',
            'đ' => 'd', '×' => 'x', '²' => '2',
        ];

        return strtr($str, $map);
    }
}
