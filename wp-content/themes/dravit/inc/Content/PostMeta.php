<?php
declare(strict_types=1);

namespace Dravit\Content;

use Dravit\Core\Config;
use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Đăng ký và đọc/chuẩn hóa Post Meta của bài viết Cẩm nang & trang Chính sách (docs/20 §2.1, docs/16 §1.1).
 */
final class PostMeta implements ServiceProviderInterface {
    public function register(): void {
        add_action('init', [$this, 'register_meta_keys']);
        add_action('save_post_post', [$this, 'sync_computed_post_meta'], 20, 2);
        add_action('deleted_post', [$this, 'purge_blog_cache'], 10, 2);
    }

    public function register_meta_keys(): void {
        if (!function_exists('register_post_meta')) {
            return;
        }

        $string_meta = [
            'drv_is_pillar'           => '0',
            'drv_toc_enabled'         => '1',
            'drv_key_takeaways'       => '[]',
            'drv_related_collections' => '[]',
            'drv_related_posts'       => '[]',
            'drv_reviewed_at'         => '',
            'drv_ai_prompts'          => '[]',
        ];

        foreach ($string_meta as $key => $default) {
            register_post_meta('post', $key, [
                'type'              => 'string',
                'single'            => true,
                'default'           => $default,
                'show_in_rest'      => false,
                'sanitize_callback' => static fn (mixed $v): string => is_scalar($v) ? trim((string) $v) : $default,
            ]);
        }

        foreach (['drv_reading_time', 'drv_reviewer_id'] as $int_key) {
            register_post_meta('post', $int_key, [
                'type'              => 'integer',
                'single'            => true,
                'default'           => 0,
                'show_in_rest'      => false,
                'sanitize_callback' => static fn (mixed $v): int => max(0, (int) $v),
            ]);
        }

        register_post_meta('page', 'drv_effective_date', [
            'type'              => 'string',
            'single'            => true,
            'default'           => '',
            'show_in_rest'      => false,
            'sanitize_callback' => static fn (mixed $v): string => sanitize_text_field((string) $v),
        ]);
    }

    public function sync_computed_post_meta(int $post_id, \WP_Post $post): void {
        if (
            $post_id <= 0
            || (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id))
            || (function_exists('wp_is_post_autosave') && wp_is_post_autosave($post_id))
        ) {
            return;
        }

        $minutes = ReadingTime::calculateMinutes((string) $post->post_content);
        update_post_meta($post_id, 'drv_reading_time', $minutes);

        if (function_exists('do_action')) {
            do_action('litespeed_purge', 'drv_blog');
            do_action('litespeed_purge', 'drv_home');
        }
    }

    public function purge_blog_cache(int $post_id, \WP_Post $post): void {
        unset($post_id);
        if ($post->post_type === 'post' && function_exists('do_action')) {
            do_action('litespeed_purge', 'drv_blog');
            do_action('litespeed_purge', 'drv_home');
        }
    }

    /**
     * Đọc mảng 3-5 gạch đầu dòng `drv_key_takeaways` (mỗi dòng <= 220 ký tự).
     *
     * @return array<int, string>
     */
    public static function getKeyTakeaways(int $post_id): array {
        if ($post_id <= 0 || !function_exists('get_post_meta')) {
            return [];
        }

        return self::parseStringList(get_post_meta($post_id, 'drv_key_takeaways', true), 6, 240);
    }

    /**
     * Đọc danh sách slug Smart Collections liên quan (`drv_related_collections`, tối đa 4 URL tĩnh hợp lệ).
     * Hỗ trợ lưu cả mảng `term_id` (theo `20 §2.1`) lẫn mảng `slug` để dễ migrate/seed.
     *
     * @return array<int, string>
     */
    public static function getRelatedCollectionSlugs(int $post_id): array {
        if ($post_id <= 0 || !function_exists('get_post_meta')) {
            return [];
        }

        $raw         = get_post_meta($post_id, 'drv_related_collections', true);
        $decoded     = is_string($raw) ? json_decode($raw, true) : $raw;
        $candidates  = is_array($decoded) ? $decoded : [];
        $collections = Config::get('collections');
        $slugs       = [];

        foreach ($candidates as $item) {
            $slug = '';
            if (is_int($item) || (is_string($item) && ctype_digit($item))) {
                $tid = (int) $item;
                if ($tid > 0 && function_exists('get_term')) {
                    $term = get_term($tid, 'product_cat');
                    if ($term instanceof \WP_Term) {
                        $slug = $term->slug;
                    }
                }
            } elseif (is_string($item)) {
                $slug = strtolower(trim($item));
            }

            if ($slug !== '' && isset($collections[$slug]) && !in_array($slug, $slugs, true)) {
                $slugs[] = $slug;
            }
            if (count($slugs) >= 4) {
                break;
            }
        }

        return $slugs;
    }

    /**
     * @return array<int, int>
     */
    public static function getRelatedPostIds(int $post_id): array {
        if ($post_id <= 0 || !function_exists('get_post_meta')) {
            return [];
        }

        $raw     = get_post_meta($post_id, 'drv_related_posts', true);
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($decoded)) {
            return [];
        }

        $ids = [];
        foreach ($decoded as $candidate) {
            $id = max(0, (int) $candidate);
            if ($id > 0 && $id !== $post_id && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
            if (count($ids) >= 4) {
                break;
            }
        }

        return $ids;
    }

    /**
     * @return array<int, string>
     */
    public static function getAiPrompts(int $post_id): array {
        if ($post_id <= 0 || !function_exists('get_post_meta')) {
            return [];
        }

        return self::parseStringList(get_post_meta($post_id, 'drv_ai_prompts', true), 3, 200);
    }

    /**
     * @return array<int, string>
     */
    public static function parseStringList(mixed $raw, int $max_items = 5, int $max_len = 220): array {
        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '') {
                return [];
            }
            $decoded = json_decode($trimmed, true);
            $raw     = is_array($decoded) ? $decoded : (preg_split('/[\r\n]+/', $trimmed) ?: []);
        }

        if (!is_array($raw)) {
            return [];
        }

        $list = [];
        foreach ($raw as $line) {
            $text = function_exists('sanitize_text_field')
                ? sanitize_text_field((string) $line)
                : trim(strip_tags((string) $line));
            $text = trim((string) preg_replace('/^[•\-\*]\s*/u', '', $text));
            if ($text === '') {
                continue;
            }
            if (mb_strlen($text, 'UTF-8') > $max_len) {
                $text = rtrim(mb_substr($text, 0, $max_len, 'UTF-8'));
            }
            $list[] = $text;
            if (count($list) >= $max_items) {
                break;
            }
        }

        return $list;
    }
}
