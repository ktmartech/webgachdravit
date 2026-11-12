<?php
declare(strict_types=1);

namespace Dravit\Catalog;

use Dravit\Core\Config;
use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Bảo vệ 28 URL Smart Collections và danh sách URL trang hệ thống khỏi xung đột slug (docs/03 §3.5, Test S-8).
 */
final class SlugGuard implements ServiceProviderInterface {
    /** @var array<int, string> */
    public const SYSTEM_SLUGS = [
        'gach',
        'san-pham',
        'cam-nang',
        'chu-de',
        'bo-suu-tap',
        'xuat-xu',
        'gio-hang',
        'thanh-toan',
        'dat-hang-thanh-cong',
        'tai-khoan',
        'danh-sach-bao-gia',
        'tro-ly-ai-tim-gach',
        'lien-he',
        'showroom',
        'gioi-thieu',
        'dieu-khoan',
        'tim-kiem',
    ];

    public function register(): void {
        add_filter('wp_unique_post_slug', [$this, 'guard_post_slug'], 10, 6);
        add_filter('wp_insert_post_data', [$this, 'guard_insert_post_data'], 10, 2);
        add_filter('pre_insert_term', [$this, 'guard_product_cat_term'], 10, 3);
        add_action('admin_notices', [$this, 'render_admin_notice']);
    }

    /**
     * Thuần kiểm tra xem `$slug` của Post/Page có đụng 28 Smart Collections không.
     *
     * @param array<string, mixed> $collections_config
     */
    public static function sanitizePostSlug(
        string $slug,
        string $post_type,
        array $collections_config,
        bool $is_designated_system_page = false
    ): string {
        if (!in_array($post_type, ['page', 'post'], true) || $is_designated_system_page) {
            return $slug;
        }

        if (isset($collections_config[$slug])) {
            return $slug . '-trang';
        }

        if ($post_type === 'post' && in_array($slug, self::SYSTEM_SLUGS, true)) {
            return $slug . '-trang';
        }

        return $slug;
    }

    /**
     * Kiểm tra xem term `product_cat` mới tạo có đụng slug hệ thống phi-danh-mục không.
     */
    public static function isReservedTermSlug(string $slug): bool {
        $non_cat_system = array_diff(self::SYSTEM_SLUGS, ['gach']);
        return in_array($slug, $non_cat_system, true);
    }

    public function guard_post_slug(
        string $slug,
        int $post_ID,
        string $post_status,
        string $post_type,
        int $post_parent,
        string $original_slug
    ): string {
        unset($post_status, $post_parent, $original_slug);

        if (!in_array($post_type, ['page', 'post'], true)) {
            return $slug;
        }

        $is_system_page = ($post_type === 'page' && $this->is_designated_wc_or_dravit_page($post_ID, $slug));
        $collections    = Config::get('collections');
        $resolved       = self::sanitizePostSlug($slug, $post_type, $collections, $is_system_page);

        if ($resolved !== $slug && function_exists('set_transient') && function_exists('get_current_user_id')) {
            $uid = get_current_user_id();
            if ($uid > 0) {
                set_transient(
                    'drv_slug_guard_notice_' . $uid,
                    sprintf(
                        'Đường dẫn "/%s" đã được dành riêng cho hệ thống danh mục gạch Dravit nên trang vừa lưu được tự động đổi thành "/%s".',
                        $slug,
                        $resolved
                    ),
                    60
                );
            }
        }

        return $resolved;
    }

    /**
     * Bảo vệ cả trường hợp bài viết/trang ở trạng thái `draft` hoặc `pending` (khi WP Core bỏ qua `wp_unique_post_slug`).
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $postarr
     * @return array<string, mixed>
     */
    public function guard_insert_post_data(array $data, array $postarr): array {
        $post_type = isset($data['post_type']) ? (string) $data['post_type'] : '';
        $slug      = isset($data['post_name']) ? (string) $data['post_name'] : '';

        if ($slug === '' || !in_array($post_type, ['page', 'post'], true)) {
            return $data;
        }

        $post_id        = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;
        $is_system_page = ($post_type === 'page' && $this->is_designated_wc_or_dravit_page($post_id, $slug));
        $resolved       = self::sanitizePostSlug($slug, $post_type, Config::get('collections'), $is_system_page);

        if ($resolved !== $slug) {
            $data['post_name'] = $resolved;
        }

        return $data;
    }

    /**
     * @param string|\WP_Error     $term
     * @param array<string, mixed> $args
     * @return string|\WP_Error
     */
    public function guard_product_cat_term(mixed $term, string $taxonomy, array $args = []): mixed {
        if ($term instanceof \WP_Error || $taxonomy !== 'product_cat' || !is_string($term)) {
            return $term;
        }

        $candidate_slug = isset($args['slug']) && is_string($args['slug']) && $args['slug'] !== ''
            ? sanitize_title($args['slug'])
            : sanitize_title($term);

        if (self::isReservedTermSlug($candidate_slug)) {
            return new \WP_Error(
                'drv_reserved_slug',
                sprintf('Slug "%s" đã thuộc về trang hệ thống Dravit, vui lòng chọn tên/slug danh mục khác.', $candidate_slug)
            );
        }

        return $term;
    }

    public function render_admin_notice(): void {
        if (!function_exists('get_current_user_id')) {
            return;
        }
        $uid = get_current_user_id();
        if ($uid <= 0) {
            return;
        }

        $msg = get_transient('drv_slug_guard_notice_' . $uid);
        if (!is_string($msg) || $msg === '') {
            return;
        }

        delete_transient('drv_slug_guard_notice_' . $uid);
        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html($msg) . '</p></div>';
    }

    private function is_designated_wc_or_dravit_page(int $post_id, string $slug): bool {
        if ($post_id <= 0) {
            return false;
        }

        if ((bool) get_post_meta($post_id, '_drv_system_page', true)) {
            return true;
        }

        $wc_page_opts = [
            'woocommerce_shop_page_id',
            'woocommerce_cart_page_id',
            'woocommerce_checkout_page_id',
            'woocommerce_myaccount_page_id',
            'woocommerce_terms_page_id',
        ];
        foreach ($wc_page_opts as $opt) {
            if ((int) get_option($opt, 0) === $post_id) {
                return true;
            }
        }

        return in_array($slug, ['gio-hang', 'thanh-toan', 'tai-khoan', 'danh-sach-bao-gia', 'tro-ly-ai-tim-gach', 'lien-he', 'showroom', 'gioi-thieu', 'dieu-khoan'], true)
            && !isset(Config::get('collections')[$slug]);
    }
}
