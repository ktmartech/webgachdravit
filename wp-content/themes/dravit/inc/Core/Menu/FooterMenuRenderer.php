<?php
declare(strict_types=1);

namespace Dravit\Core\Menu;

defined('ABSPATH') || exit;

/**
 * Render 3 cột menu có thể chỉnh sửa ở Footer (`footer_col_2`, `footer_col_3`, `footer_col_4`).
 * Tiêu đề cột tự động lấy theo tên Menu được gán trong Giao diện -> Menu; URL nội bộ tự bỏ dấu `/` cuối (D-21, D-35).
 */
final class FooterMenuRenderer {
    /**
     * @param array<int, array{label: string, path: string}> $fallback_links
     */
    public static function render_column(string $location, string $default_title, string $title_icon, array $fallback_links): void {
        $title = $default_title;
        $links = [];

        if (function_exists('get_nav_menu_locations') && function_exists('wp_get_nav_menu_object') && function_exists('wp_get_nav_menu_items')) {
            $locations = get_nav_menu_locations();
            $menu_id   = isset($locations[$location]) ? (int) $locations[$location] : 0;
            if ($menu_id > 0) {
                $menu_obj = wp_get_nav_menu_object($menu_id);
                if (is_object($menu_obj) && property_exists($menu_obj, 'name') && trim((string) $menu_obj->name) !== '') {
                    $title = (string) $menu_obj->name;
                }
                $raw_items = wp_get_nav_menu_items($menu_id, ['update_post_term_cache' => false]);
                if (is_array($raw_items)) {
                    foreach ($raw_items as $item) {
                        if (!is_object($item) || !isset($item->ID)) {
                            continue;
                        }
                        $id      = (int) $item->ID;
                        $icon    = function_exists('get_post_meta') ? trim((string) get_post_meta($id, '_drv_icon', true)) : '';
                        $links[] = [
                            'label' => (string) ($item->title ?? ''),
                            'url'   => MenuSetup::sanitize_menu_url((string) ($item->url ?? '/'), false),
                            'icon'  => $icon !== '' ? $icon : 'ph-caret-right',
                        ];
                    }
                }
            }
        }

        if ($links === []) {
            foreach ($fallback_links as $fb) {
                $links[] = [
                    'label' => $fb['label'],
                    'url'   => MegaMenuRenderer::internal_url($fb['path']),
                    'icon'  => 'ph-caret-right',
                ];
            }
        }
        ?>
        <div>
            <h3 class="drv-footer__title">
                <i class="ph <?php echo esc_attr($title_icon); ?> drv-icon--xs" aria-hidden="true"></i>
                <span><?php echo esc_html($title); ?></span>
            </h3>
            <ul class="drv-footer__links">
                <?php foreach ($links as $lnk) : ?>
                    <li>
                        <a href="<?php echo esc_url($lnk['url']); ?>">
                            <i class="ph <?php echo esc_attr($lnk['icon']); ?> drv-icon--xs" aria-hidden="true"></i>
                            <span><?php echo esc_html($lnk['label']); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }
}
