<?php
declare(strict_types=1);

namespace Dravit\Core\Menu;

use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Đăng ký 6 vùng Menu chuẩn WP Native + Custom Fields trên từng mục menu (Phương án M-1, ADR D-35).
 */
final class MenuSetup implements ServiceProviderInterface {
    /** @var array<string, string> */
    public const LOCATIONS = [
        'mega_menu'        => 'Mega Menu 4+1 (Hệ thống danh mục gạch)',
        'primary_silo_nav' => 'Thanh điều hướng tầng 2 (3 cấp Silo)',
        'primary_utility'  => 'Menu tiện ích phải tầng 2 & Mobile',
        'footer_col_2'     => 'Footer Cột 2 (Hướng dẫn mua hàng)',
        'footer_col_3'     => 'Footer Cột 3 (Chính sách & Bảo hành)',
        'footer_col_4'     => 'Footer Cột 4 (Đối tác & Doanh nghiệp)',
    ];

    /** @var array<string, string> */
    public const ITEM_META_KEYS = [
        '_drv_icon'          => 'text',
        '_drv_badge'         => 'text',
        '_drv_highlight'     => 'select',
        '_drv_preview_badge' => 'text',
        '_drv_preview_price' => 'text',
        '_drv_preview_desc'  => 'textarea',
        '_drv_preview_img'   => 'text',
    ];

    public function register(): void {
        add_action('after_setup_theme', [$this, 'register_locations']);
        add_action('wp_nav_menu_item_custom_fields', [$this, 'render_item_custom_fields'], 10, 4);
        add_action('wp_update_nav_menu_item', [$this, 'save_item_custom_fields'], 10, 3);
        add_action('wp_update_nav_menu', [MegaMenuRenderer::class, 'purge_cache']);
        add_action('wp_delete_nav_menu', [MegaMenuRenderer::class, 'purge_cache']);
        add_action('admin_notices', [$this, 'render_url_guard_notice']);
    }

    public function register_locations(): void {
        register_nav_menus(self::LOCATIONS);
    }

    /**
     * Hiển thị các trường tùy chỉnh Dravit ngay trong màn hình Giao diện -> Menu.
     *
     * @param int|string $item_id
     * @param object     $menu_item
     * @param int        $depth
     * @param object     $args
     */
    public function render_item_custom_fields(int|string $item_id, object $menu_item, int $depth, object $args): void {
        unset($menu_item, $depth, $args);
        $id        = (int) $item_id;
        $icon      = (string) get_post_meta($id, '_drv_icon', true);
        $badge     = (string) get_post_meta($id, '_drv_badge', true);
        $highlight = (string) get_post_meta($id, '_drv_highlight', true);
        $p_badge   = (string) get_post_meta($id, '_drv_preview_badge', true);
        $p_price   = (string) get_post_meta($id, '_drv_preview_price', true);
        $p_desc    = (string) get_post_meta($id, '_drv_preview_desc', true);
        $p_img     = (string) get_post_meta($id, '_drv_preview_img', true);
        ?>
        <div class="drv-menu-fields description-wide notice notice-info inline" style="margin: 8px 0; padding: 10px;">
            <strong style="display:block; margin-bottom:6px;">Cấu hình hiển thị DRAVIT (Tùy chọn)</strong>
            <p class="field-drv-icon description description-thin">
                <label for="edit-drv-icon-<?php echo esc_attr((string) $id); ?>">
                    Icon Phosphor (VD: <code>ph-grid-four</code>)<br>
                    <input type="text" id="edit-drv-icon-<?php echo esc_attr((string) $id); ?>" class="widefat" name="drv_menu_meta[<?php echo esc_attr((string) $id); ?>][_drv_icon]" value="<?php echo esc_attr($icon); ?>">
                </label>
            </p>
            <p class="field-drv-badge description description-thin">
                <label for="edit-drv-badge-<?php echo esc_attr((string) $id); ?>">
                    Nhãn ngắn (VD: <code>Mới</code>, <code>Sale</code>)<br>
                    <input type="text" id="edit-drv-badge-<?php echo esc_attr((string) $id); ?>" class="widefat" name="drv_menu_meta[<?php echo esc_attr((string) $id); ?>][_drv_badge]" value="<?php echo esc_attr($badge); ?>">
                </label>
            </p>
            <p class="field-drv-highlight description description-wide">
                <label for="edit-drv-highlight-<?php echo esc_attr((string) $id); ?>">
                    Kiểu nhấn mục menu<br>
                    <select id="edit-drv-highlight-<?php echo esc_attr((string) $id); ?>" class="widefat" name="drv_menu_meta[<?php echo esc_attr((string) $id); ?>][_drv_highlight]">
                        <option value="" <?php selected($highlight, ''); ?>>Mặc định</option>
                        <option value="teal" <?php selected($highlight, 'teal'); ?>>Nhấn xanh Teal (Trợ lý AI / Tư vấn)</option>
                        <option value="sale" <?php selected($highlight, 'sale'); ?>>Nhấn đỏ Ưu đãi (Gạch ưu đãi)</option>
                    </select>
                </label>
            </p>
            <details style="margin-top:6px;">
                <summary style="cursor:pointer; font-weight:600;">Cấu hình thẻ Preview Cột 5 Mega Menu</summary>
                <p class="description description-thin" style="margin-top:6px;">
                    <label for="edit-drv-pbadge-<?php echo esc_attr((string) $id); ?>">
                        Badge Preview<br>
                        <input type="text" id="edit-drv-pbadge-<?php echo esc_attr((string) $id); ?>" class="widefat" name="drv_menu_meta[<?php echo esc_attr((string) $id); ?>][_drv_preview_badge]" value="<?php echo esc_attr($p_badge); ?>">
                    </label>
                </p>
                <p class="description description-thin" style="margin-top:6px;">
                    <label for="edit-drv-pprice-<?php echo esc_attr((string) $id); ?>">
                        Giá tham chiếu (VD: <code>Từ 385.000đ/m²</code>)<br>
                        <input type="text" id="edit-drv-pprice-<?php echo esc_attr((string) $id); ?>" class="widefat" name="drv_menu_meta[<?php echo esc_attr((string) $id); ?>][_drv_preview_price]" value="<?php echo esc_attr($p_price); ?>">
                    </label>
                </p>
                <p class="description description-wide">
                    <label for="edit-drv-pimg-<?php echo esc_attr((string) $id); ?>">
                        URL ảnh Preview (hoặc tên file placeholder SVG)<br>
                        <input type="text" id="edit-drv-pimg-<?php echo esc_attr((string) $id); ?>" class="widefat" name="drv_menu_meta[<?php echo esc_attr((string) $id); ?>][_drv_preview_img]" value="<?php echo esc_attr($p_img); ?>">
                    </label>
                </p>
                <p class="description description-wide">
                    <label for="edit-drv-pdesc-<?php echo esc_attr((string) $id); ?>">
                        Mô tả ngắn Preview (1-2 câu)<br>
                        <textarea id="edit-drv-pdesc-<?php echo esc_attr((string) $id); ?>" class="widefat" rows="2" name="drv_menu_meta[<?php echo esc_attr((string) $id); ?>][_drv_preview_desc]"><?php echo esc_textarea($p_desc); ?></textarea>
                    </label>
                </p>
            </details>
        </div>
        <?php
    }

    /**
     * Lưu Custom Fields của từng mục menu & kiểm soát chuẩn URL D-21 (0 dấu `/` cuối, 0 `?param` trong Silo/Mega).
     *
     * @param int                  $menu_id
     * @param int                  $menu_item_db_id
     * @param array<string, mixed> $args
     */
    public function save_item_custom_fields(int $menu_id, int $menu_item_db_id, array $args): void {
        unset($args);
        if (!current_user_can('edit_theme_options')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Đã được core wp_nav_menu_update_menu_items kiểm tra nonce `update-nav_menu`; từng trường lá được sanitize theo kiểu ở dòng 144-148.
        $posted_all = isset($_POST['drv_menu_meta']) && is_array($_POST['drv_menu_meta']) ? wp_unslash($_POST['drv_menu_meta']) : [];
        $item_post  = isset($posted_all[$menu_item_db_id]) && is_array($posted_all[$menu_item_db_id]) ? $posted_all[$menu_item_db_id] : [];

        foreach (self::ITEM_META_KEYS as $meta_key => $field_type) {
            if (!array_key_exists($meta_key, $item_post)) {
                continue;
            }
            $raw = (string) $item_post[$meta_key];
            $val = match ($field_type) {
                'textarea' => sanitize_textarea_field($raw),
                'select'   => in_array($raw, ['', 'teal', 'sale'], true) ? $raw : '',
                default    => sanitize_text_field($raw),
            };

            if ($meta_key === '_drv_icon' && $val !== '') {
                $val = (string) preg_replace('/[^a-z0-9\-\s]/i', '', $val);
            }

            if ($val === '') {
                delete_post_meta($menu_item_db_id, $meta_key);
            } else {
                update_post_meta($menu_item_db_id, $meta_key, $val);
            }
        }

        $item_type = (string) get_post_meta($menu_item_db_id, '_menu_item_type', true);
        if ($item_type === 'custom') {
            $raw_url     = (string) get_post_meta($menu_item_db_id, '_menu_item_url', true);
            $locations   = get_nav_menu_locations();
            $silo_menus  = [
                (int) ($locations['mega_menu'] ?? 0),
                (int) ($locations['primary_silo_nav'] ?? 0),
            ];
            $strip_query = in_array($menu_id, $silo_menus, true);
            $clean_url   = self::sanitize_menu_url($raw_url, $strip_query);

            if ($clean_url !== $raw_url) {
                update_post_meta($menu_item_db_id, '_menu_item_url', $clean_url);
                if ($strip_query && str_contains($raw_url, '?')) {
                    set_transient('drv_menu_url_warning', '1', 60);
                }
            }
        }

        MegaMenuRenderer::purge_cache();
    }

    public function render_url_guard_notice(): void {
        if (!function_exists('get_transient') || get_transient('drv_menu_url_warning') !== '1') {
            return;
        }
        delete_transient('drv_menu_url_warning');
        echo '<div class="notice notice-warning is-dismissible"><p>';
        echo esc_html('DRAVIT SEO Guard (D-21): Đã tự động chuẩn hóa URL trong Menu Silo/Mega Menu (loại bỏ tham số ?param, đuôi .html và dấu / cuối).');
        echo '</p></div>';
    }

    /**
     * Chuẩn hóa URL trong Menu theo chuẩn SEO D-21:
     * - Chặn link prototype `.html`.
     * - Bỏ dấu `/` cuối cho mọi URL nội bộ (trừ trang chủ `/`).
     * - Khi `$strip_query = true` (áp dụng cho Mega Menu & Silo Nav): loại bỏ toàn bộ `?query` để giữ 100% link tĩnh.
     */
    public static function sanitize_menu_url(string $url, bool $strip_query = false): string {
        $trimmed = trim($url);
        if ($trimmed === '' || $trimmed === '#') {
            return '#';
        }

        if (str_starts_with($trimmed, '#') || str_starts_with($trimmed, 'tel:') || str_starts_with($trimmed, 'mailto:')) {
            return $trimmed;
        }

        $home      = function_exists('home_url') ? rtrim((string) home_url(), '/') : 'https://gachdravit.vn';
        $home_host = (string) parse_url($home, PHP_URL_HOST);
        $url_host  = parse_url($trimmed, PHP_URL_HOST);

        $is_internal = ($url_host === null || $url_host === '' || ($home_host !== '' && strcasecmp((string) $url_host, $home_host) === 0));
        if (!$is_internal) {
            return $trimmed;
        }

        $fragment = parse_url($trimmed, PHP_URL_FRAGMENT);
        $query    = parse_url($trimmed, PHP_URL_QUERY);
        $path     = (string) (parse_url($trimmed, PHP_URL_PATH) ?? '/');

        if (str_ends_with(strtolower($path), '.html')) {
            $path = '/gach';
        }

        if ($path === '' || $path === '/') {
            $base = ($url_host !== null && $url_host !== '') ? $home . '/' : '/';
        } else {
            $clean_path = '/' . trim($path, '/');
            $base       = ($url_host !== null && $url_host !== '') ? $home . $clean_path : $clean_path;
        }

        if (!$strip_query && is_string($query) && $query !== '') {
            $base .= '?' . $query;
        }

        if (is_string($fragment) && $fragment !== '') {
            $base .= '#' . $fragment;
        }

        return $base;
    }
}
