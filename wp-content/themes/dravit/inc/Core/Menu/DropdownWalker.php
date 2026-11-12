<?php
declare(strict_types=1);

namespace Dravit\Core\Menu;

defined('ABSPATH') || exit;

if (!class_exists('\Walker_Nav_Menu')) {
    /**
     * Stub nhẹ phục vụ môi trường CLI/PHPUnit độc lập khi chưa nạp lõi WP.
     */
    class WalkerNavMenuBase {
        /** @var array<string, string> */
        public $db_fields = ['parent' => 'menu_item_parent', 'id' => 'db_id'];
        /** @var bool */
        public $has_children = false;
    }
} else {
    class WalkerNavMenuBase extends \Walker_Nav_Menu {
    }
}

/**
 * Walker 3 tầng cho thanh điều hướng Silo tầng 2 (`primary_silo_nav`) + Tiện ích phải (`primary_utility`).
 * Đạt chuẩn WCAG 2.1.1: tách `<a class="drv-nav__link">` và `<button class="drv-nav__sub-toggle">` cho mục có menu con.
 */
final class DropdownWalker extends WalkerNavMenuBase {
    /**
     * @param string   $output
     * @param int      $depth
     * @param object   $args
     */
    public function start_lvl(&$output, $depth = 0, $args = null): void {
        unset($args);
        if ($depth === 0) {
            $output .= '<ul class="drv-dropdown" aria-label="Menu danh mục cấp 2">';
            return;
        }
        $output .= '<ul class="drv-dropdown__flyout" aria-label="Menu danh mục cấp 3">';
    }

    /**
     * @param string   $output
     * @param int      $depth
     * @param object   $args
     */
    public function end_lvl(&$output, $depth = 0, $args = null): void {
        unset($depth, $args);
        $output .= '</ul>';
    }

    /**
     * @param string   $output
     * @param object   $data_object
     * @param int      $depth
     * @param object   $args
     * @param int      $current_object_id
     */
    public function start_el(&$output, $data_object, $depth = 0, $args = null, $current_object_id = 0): void {
        unset($current_object_id);
        $item_id      = isset($data_object->ID) ? (int) $data_object->ID : 0;
        $title        = isset($data_object->title) ? (string) $data_object->title : '';
        $raw_url      = isset($data_object->url) ? (string) $data_object->url : '/gach';
        $url          = MenuSetup::sanitize_menu_url($raw_url, true);
        $has_children = (is_object($args) && isset($args->walker) && is_object($args->walker) && !empty($args->walker->has_children))
            || !empty($this->has_children);

        $icon      = ($item_id > 0 && function_exists('get_post_meta')) ? trim((string) get_post_meta($item_id, '_drv_icon', true)) : '';
        $badge     = ($item_id > 0 && function_exists('get_post_meta')) ? trim((string) get_post_meta($item_id, '_drv_badge', true)) : '';
        $highlight = ($item_id > 0 && function_exists('get_post_meta')) ? trim((string) get_post_meta($item_id, '_drv_highlight', true)) : '';

        if ($depth === 0) {
            $output .= '<li class="drv-nav__item">';
            $link_cls = 'drv-nav__link';
            if ($highlight === 'sale') {
                $link_cls .= ' drv-nav__link--sale';
            } elseif ($highlight === 'teal') {
                $link_cls .= ' u-text-moss u-bold';
            }

            $inner = '';
            if ($icon !== '') {
                $inner .= '<i class="ph ' . esc_attr($icon) . ' drv-icon--xs" aria-hidden="true"></i>';
            }
            $inner .= '<span>' . esc_html($title) . '</span>';
            if ($badge !== '') {
                $inner .= '<span class="drv-mega__badge">' . esc_html($badge) . '</span>';
            }

            if ($has_children) {
                $output .= '<div class="drv-nav__item-head">';
                $output .= '<a href="' . esc_url($url) . '" class="' . esc_attr($link_cls) . '">' . $inner . '</a>';
                $output .= '<button type="button" class="drv-nav__sub-toggle" aria-expanded="false" aria-label="' . esc_attr('Mở danh mục con ' . $title) . '">';
                $output .= '<i class="ph ph-caret-down drv-icon drv-icon--xs" aria-hidden="true"></i>';
                $output .= '</button>';
                $output .= '</div>';
            } else {
                $output .= '<a href="' . esc_url($url) . '" class="' . esc_attr($link_cls) . '">' . $inner . '</a>';
            }
            return;
        }

        if ($depth === 1) {
            $output .= '<li class="drv-dropdown__item">';
            $output .= '<a href="' . esc_url($url) . '" class="drv-dropdown__link">';
            $output .= '<span class="l-cluster l-cluster--sm">';
            if ($icon !== '') {
                $output .= '<i class="ph ' . esc_attr($icon) . ' drv-icon" aria-hidden="true"></i>';
            }
            $output .= '<span>' . esc_html($title) . '</span>';
            $output .= '</span>';
            if ($has_children) {
                $output .= '<i class="ph ph-caret-right drv-dropdown__arrow" aria-hidden="true"></i>';
            } elseif ($badge !== '') {
                $output .= '<span class="drv-mega__badge">' . esc_html($badge) . '</span>';
            }
            $output .= '</a>';
            return;
        }

        $count = 0;
        if (isset($data_object->object, $data_object->object_id) && $data_object->object === 'product_cat' && function_exists('get_term')) {
            $term = get_term((int) $data_object->object_id, 'product_cat');
            if (is_object($term) && !is_wp_error($term) && property_exists($term, 'count')) {
                $count = (int) $term->count;
            }
        }

        $output .= '<li>';
        $output .= '<a href="' . esc_url($url) . '" class="drv-dropdown__sublink">';
        $output .= '<span>' . esc_html($title) . '</span>';
        if ($count > 0) {
            $output .= '<span class="u-text-xs u-text-muted u-tabular">' . esc_html((string) $count) . '</span>';
        }
        $output .= '</a>';
    }

    /**
     * @param string   $output
     * @param object   $data_object
     * @param int      $depth
     * @param object   $args
     */
    public function end_el(&$output, $data_object, $depth = 0, $args = null): void {
        unset($data_object, $depth, $args);
        $output .= '</li>';
    }

    /**
     * Render các mục Silo tầng 2 bên trái (nằm ngay sau nút mở Mega Menu 28 danh mục).
     */
    public static function render_silo_items(): string {
        if (function_exists('has_nav_menu') && has_nav_menu('primary_silo_nav') && function_exists('wp_nav_menu')) {
            $rendered = wp_nav_menu([
                'theme_location' => 'primary_silo_nav',
                'container'      => '',
                'items_wrap'     => '%3$s',
                'depth'          => 3,
                'echo'           => false,
                'fallback_cb'    => false,
                'walker'         => new self(),
            ]);
            if (is_string($rendered) && trim($rendered) !== '') {
                return $rendered;
            }
        }

        return self::render_fallback_silo_items();
    }

    /**
     * Fallback tự động 3 cấp từ `config/collections.php` khi chưa gán menu `primary_silo_nav`.
     */
    public static function render_fallback_silo_items(): string {
        $groups = [
            [
                'label' => 'Theo công năng',
                'slug'  => 'gach-lat-nen',
                'subs'  => [
                    [
                        'label' => 'Gạch lát nền',
                        'slug'  => 'gach-lat-nen',
                        'icon'  => 'ph-grid-four',
                        'fly'   => [
                            ['label' => 'Gạch 80x80 cm', 'slug' => 'gach-80x80'],
                            ['label' => 'Khổ lớn 60x120 cm', 'slug' => 'gach-60x120'],
                            ['label' => 'Gạch giả gỗ', 'slug' => 'gach-gia-go'],
                        ],
                    ],
                    [
                        'label' => 'Gạch ốp tường',
                        'slug'  => 'gach-op-tuong',
                        'icon'  => 'ph-wall',
                        'fly'   => [
                            ['label' => 'Gạch ốp 30x60 cm', 'slug' => 'gach-30x60'],
                            ['label' => 'Gạch thẻ kiến trúc', 'slug' => 'gach-the'],
                            ['label' => 'Gạch bông gió', 'slug' => 'gach-bong-gio'],
                        ],
                    ],
                    [
                        'label' => 'Trang trí & Mosaic',
                        'slug'  => 'gach-trang-tri',
                        'icon'  => 'ph-sparkle',
                        'fly'   => [
                            ['label' => 'Gạch trang trí', 'slug' => 'gach-trang-tri'],
                            ['label' => 'Gạch bông', 'slug' => 'gach-bong'],
                            ['label' => 'Gạch Mosaic', 'slug' => 'gach-mosaic'],
                        ],
                    ],
                ],
            ],
            [
                'label' => 'Theo không gian',
                'slug'  => 'gach-phong-khach',
                'subs'  => [
                    [
                        'label' => 'Phòng khách & Sảnh',
                        'slug'  => 'gach-phong-khach',
                        'icon'  => 'ph-armchair',
                        'fly'   => [
                            ['label' => 'Gạch phòng khách', 'slug' => 'gach-phong-khach'],
                            ['label' => 'Gạch phòng ngủ', 'slug' => 'gach-phong-ngu'],
                            ['label' => 'Đá nung kết đại sảnh', 'slug' => 'da-nung-ket'],
                        ],
                    ],
                    [
                        'label' => 'Bếp & Nhà tắm',
                        'slug'  => 'gach-nha-tam',
                        'icon'  => 'ph-drop',
                        'fly'   => [
                            ['label' => 'Gạch nhà tắm R10', 'slug' => 'gach-nha-tam'],
                            ['label' => 'Gạch phòng bếp', 'slug' => 'gach-phong-bep'],
                        ],
                    ],
                    [
                        'label' => 'Ngoại thất & Sân vườn',
                        'slug'  => 'gach-san-vuon',
                        'icon'  => 'ph-sun',
                        'fly'   => [
                            ['label' => 'Gạch sân vườn', 'slug' => 'gach-san-vuon'],
                            ['label' => 'Gạch ngoài trời', 'slug' => 'gach-ngoai-troi'],
                            ['label' => 'Gạch mặt tiền', 'slug' => 'gach-mat-tien'],
                            ['label' => 'Gạch hồ bơi', 'slug' => 'gach-ho-boi'],
                        ],
                    ],
                ],
            ],
        ];

        $flat = [
            ['label' => 'Vân & Chất liệu', 'slug' => 'gach-van-da', 'sale' => false],
            ['label' => 'Theo kích thước', 'slug' => 'gach-80x80', 'sale' => false],
            ['label' => 'Đá nung kết', 'slug' => 'da-nung-ket', 'sale' => false],
            ['label' => 'Gạch ưu đãi', 'slug' => 'gach-uu-dai', 'sale' => true],
        ];

        ob_start();
        foreach ($groups as $grp) :
            $grp_url = MegaMenuRenderer::collection_url($grp['slug']);
            ?>
            <li class="drv-nav__item">
                <div class="drv-nav__item-head">
                    <a href="<?php echo esc_url($grp_url); ?>" class="drv-nav__link">
                        <span><?php echo esc_html($grp['label']); ?></span>
                    </a>
                    <button type="button" class="drv-nav__sub-toggle" aria-expanded="false" aria-label="<?php echo esc_attr('Mở danh mục con ' . $grp['label']); ?>">
                        <i class="ph ph-caret-down drv-icon drv-icon--xs" aria-hidden="true"></i>
                    </button>
                </div>
                <ul class="drv-dropdown" aria-label="<?php echo esc_attr('Menu cấp 2 - ' . $grp['label']); ?>">
                    <?php foreach ($grp['subs'] as $sub) :
                        $sub_url = MegaMenuRenderer::collection_url($sub['slug']);
                        ?>
                        <li class="drv-dropdown__item">
                            <a href="<?php echo esc_url($sub_url); ?>" class="drv-dropdown__link">
                                <span class="l-cluster l-cluster--sm">
                                    <i class="ph <?php echo esc_attr($sub['icon']); ?> drv-icon" aria-hidden="true"></i>
                                    <span><?php echo esc_html($sub['label']); ?></span>
                                </span>
                                <i class="ph ph-caret-right drv-dropdown__arrow" aria-hidden="true"></i>
                            </a>
                            <ul class="drv-dropdown__flyout" aria-label="<?php echo esc_attr('Menu cấp 3 - ' . $sub['label']); ?>">
                                <?php foreach ($sub['fly'] as $fly) : ?>
                                    <li>
                                        <a href="<?php echo esc_url(MegaMenuRenderer::collection_url($fly['slug'])); ?>" class="drv-dropdown__sublink">
                                            <span><?php echo esc_html($fly['label']); ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </li>
        <?php endforeach; ?>

        <?php foreach ($flat as $f) :
            $cls = 'drv-nav__link' . ($f['sale'] ? ' drv-nav__link--sale' : '');
            ?>
            <li class="drv-nav__item">
                <a href="<?php echo esc_url(MegaMenuRenderer::collection_url($f['slug'])); ?>" class="<?php echo esc_attr($cls); ?>">
                    <?php echo esc_html($f['label']); ?>
                </a>
            </li>
        <?php endforeach; ?>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Danh sách tiện ích bên phải tầng 2 & trong Mobile Drawer (đọc từ WP Menu `primary_utility` hoặc fallback chuẩn).
     *
     * @return array<int, array{label: string, url: string, icon: string, highlight: string}>
     */
    public static function get_utility_entries(): array {
        $ai_enabled = (bool) drv_setting('features.ai_enabled', true);

        if (function_exists('get_nav_menu_locations') && function_exists('wp_get_nav_menu_items')) {
            $locations = get_nav_menu_locations();
            $menu_id   = isset($locations['primary_utility']) ? (int) $locations['primary_utility'] : 0;
            if ($menu_id > 0) {
                $raw = wp_get_nav_menu_items($menu_id, ['update_post_term_cache' => false]);
                if (is_array($raw) && $raw !== []) {
                    $entries = [];
                    foreach ($raw as $item) {
                        if (!is_object($item) || !isset($item->ID)) {
                            continue;
                        }
                        $url = MenuSetup::sanitize_menu_url((string) ($item->url ?? '/'), false);
                        if (!$ai_enabled && str_contains($url, 'tro-ly-ai-tim-gach')) {
                            continue;
                        }
                        $id        = (int) $item->ID;
                        $icon      = function_exists('get_post_meta') ? trim((string) get_post_meta($id, '_drv_icon', true)) : '';
                        $highlight = function_exists('get_post_meta') ? trim((string) get_post_meta($id, '_drv_highlight', true)) : '';
                        $entries[] = [
                            'label'     => (string) ($item->title ?? ''),
                            'url'       => $url,
                            'icon'      => $icon,
                            'highlight' => $highlight,
                        ];
                    }
                    if ($entries !== []) {
                        return $entries;
                    }
                }
            }
        }

        $defaults = [];
        if ($ai_enabled) {
            $defaults[] = [
                'label'     => 'Trợ lý AI Tìm gạch',
                'url'       => MegaMenuRenderer::internal_url('/tro-ly-ai-tim-gach'),
                'icon'      => 'ph-fill ph-robot',
                'highlight' => 'teal',
            ];
        }
        $defaults[] = [
            'label'     => 'Cẩm nang kỹ thuật',
            'url'       => MegaMenuRenderer::internal_url('/cam-nang'),
            'icon'      => 'ph-book-open',
            'highlight' => '',
        ];
        $defaults[] = [
            'label'     => 'Liên hệ & Showroom',
            'url'       => MegaMenuRenderer::internal_url('/lien-he'),
            'icon'      => 'ph-map-pin',
            'highlight' => '',
        ];

        return $defaults;
    }

    public static function render_utility_items(): string {
        $entries = self::get_utility_entries();
        ob_start();
        foreach ($entries as $entry) :
            $cls = 'drv-nav__link';
            if ($entry['highlight'] === 'teal') {
                $cls .= ' u-text-moss u-bold';
            } elseif ($entry['highlight'] === 'sale') {
                $cls .= ' drv-nav__link--sale';
            }
            ?>
            <li class="drv-nav__item">
                <a href="<?php echo esc_url($entry['url']); ?>" class="<?php echo esc_attr($cls); ?>">
                    <?php if ($entry['icon'] !== '') : ?>
                        <i class="ph <?php echo esc_attr($entry['icon']); ?> drv-icon--xs" aria-hidden="true"></i>
                    <?php endif; ?>
                    <span><?php echo esc_html($entry['label']); ?></span>
                </a>
            </li>
        <?php endforeach;
        return (string) ob_get_clean();
    }
}
