<?php
declare(strict_types=1);

namespace Dravit\Content;

defined('ABSPATH') || exit;

/**
 * Tính toán thời lượng đọc bài viết Tiếng Việt (~200 từ/phút, docs/20 §2.1).
 */
final class ReadingTime {
    public const DEFAULT_WPM = 200;

    /**
     * Đếm số từ thực tế sau khi gỡ bỏ shortcode, thẻ HTML và khoảng trắng thừa.
     */
    public static function countWords(string $content): int {
        if (trim($content) === '') {
            return 0;
        }

        // Gỡ bỏ các shortcode tự đóng hoặc bao bọc nhưng giữ lại văn bản bên trong shortcode nội dung.
        $cleaned = (string) preg_replace('/\[\/?[a-zA-Z0-9_\-]+(?:\s+[^\]]*)?\]/u', ' ', $content);
        $cleaned = function_exists('wp_strip_all_tags')
            ? wp_strip_all_tags($cleaned)
            : strip_tags($cleaned);
        $cleaned = html_entity_decode($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cleaned = trim($cleaned);

        if ($cleaned === '') {
            return 0;
        }

        $tokens = preg_split('/\s+/u', $cleaned, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($tokens) ? count($tokens) : 0;
    }

    /**
     * Quy đổi nội dung HTML sang số phút đọc (tối thiểu 1 phút).
     */
    public static function calculateMinutes(string $content, int $wpm = self::DEFAULT_WPM): int {
        $safe_wpm = max(50, $wpm);
        $words    = self::countWords($content);

        if ($words <= 0) {
            return 1;
        }

        return max(1, (int) ceil($words / $safe_wpm));
    }

    /**
     * Đọc thời lượng đọc từ post meta `drv_reading_time`, tự tính trực tiếp nếu chưa lưu.
     */
    public static function forPost(\WP_Post|int $post): int {
        $post_obj = is_int($post) && function_exists('get_post') ? get_post($post) : $post;
        if (!$post_obj instanceof \WP_Post) {
            return 1;
        }

        if (function_exists('get_post_meta')) {
            $stored = (int) get_post_meta($post_obj->ID, 'drv_reading_time', true);
            if ($stored > 0) {
                return $stored;
            }
        }

        return self::calculateMinutes((string) $post_obj->post_content);
    }

    public static function formatLabel(int $minutes): string {
        return sprintf('%d phút đọc', max(1, $minutes));
    }
}
