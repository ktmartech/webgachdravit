<?php
/**
 * Seed idempotent cho Step 1 (chạy qua WP-CLI `eval-file`):
 * 1. Tạo trang `/ui-kit` gắn template `page-templates/template-ui-kit.php`.
 * 2. Khởi tạo 6 Menu chuẩn WP có thể chỉnh sửa trong Giao diện -> Menu (`D-35` / `Phương án M-1`).
 */
declare(strict_types=1);

use Dravit\Core\Menu\MegaMenuRenderer;

if (!defined('ABSPATH')) {
    exit("Chạy file này thông qua WP-CLI eval-file.\n");
}

// 1. Seed trang /ui-kit
$existing_page = get_page_by_path('ui-kit', OBJECT, 'page');
if ($existing_page instanceof \WP_Post) {
    $uikit_id = (int) $existing_page->ID;
    wp_update_post([
        'ID'          => $uikit_id,
        'post_title'  => 'Master UI Kit & Design System',
        'post_status' => 'publish',
    ]);
} else {
    $uikit_id = (int) wp_insert_post([
        'post_title'   => 'Master UI Kit & Design System',
        'post_name'    => 'ui-kit',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_content' => '',
    ]);
}
update_post_meta($uikit_id, '_wp_page_template', 'page-templates/template-ui-kit.php');

/**
 * Helper tạo/lấy menu theo tên và xóa item cũ để seed idempotent sạch 100%.
 */
$ensure_clean_menu = static function (string $menu_name): int {
    $menu_obj = wp_get_nav_menu_object($menu_name);
    if ($menu_obj instanceof \WP_Term) {
        $menu_id = (int) $menu_obj->term_id;
        $old     = wp_get_nav_menu_items($menu_id);
        if (is_array($old)) {
            foreach ($old as $item) {
                if (is_object($item) && isset($item->ID)) {
                    wp_delete_post((int) $item->ID, true);
                }
            }
        }
        return $menu_id;
    }

    $created = wp_create_nav_menu($menu_name);
    return is_wp_error($created) ? 0 : (int) $created;
};

/**
 * @param array<string, string> $meta
 */
$add_custom_item = static function (int $menu_id, string $title, string $path, int $parent_id = 0, int $pos = 1, array $meta = []): int {
    $url     = MegaMenuRenderer::internal_url($path);
    $item_id = wp_update_nav_menu_item($menu_id, 0, [
        'menu-item-title'     => $title,
        'menu-item-url'       => $url,
        'menu-item-status'    => 'publish',
        'menu-item-type'      => 'custom',
        'menu-item-parent-id' => $parent_id,
        'menu-item-position'  => $pos,
    ]);
    if (is_wp_error($item_id)) {
        return 0;
    }
    foreach ($meta as $k => $v) {
        if ($v !== '') {
            update_post_meta((int) $item_id, $k, $v);
        }
    }
    return (int) $item_id;
};

// 2. Seed Mega Menu 4+1 (đầy đủ 27 danh mục con + root /gach = 28 collections)
$mega_id = $ensure_clean_menu('DRAVIT - Mega Menu 28 Danh Mục');
$cols    = MegaMenuRenderer::build_columns_from_config();
$pos     = 1;
foreach ($cols as $col) {
    $col_path = (string) (parse_url((string) ($col['url'] ?? '/gach'), PHP_URL_PATH) ?: '/gach');
    $col_id   = $add_custom_item($mega_id, (string) $col['heading'], $col_path, 0, $pos++);
    $sgs      = isset($col['subgroups']) && is_array($col['subgroups']) ? $col['subgroups'] : [];
    foreach ($sgs as $sg) {
        $sg_path = (string) (parse_url((string) ($sg['url'] ?? '/gach'), PHP_URL_PATH) ?: '/gach');
        $sg_id   = $add_custom_item(
            $mega_id,
            (string) $sg['title'],
            $sg_path,
            $col_id,
            $pos++,
            ['_drv_icon' => (string) ($sg['icon'] ?? 'ph-squares-four')]
        );
        $items = isset($sg['items']) && is_array($sg['items']) ? $sg['items'] : [];
        foreach ($items as $leaf) {
            $leaf_path = (string) (parse_url((string) ($leaf['url'] ?? '/gach'), PHP_URL_PATH) ?: '/gach');
            $add_custom_item(
                $mega_id,
                (string) $leaf['label'],
                $leaf_path,
                $sg_id,
                $pos++,
                [
                    '_drv_badge'         => (string) ($leaf['badge'] ?? ''),
                    '_drv_preview_badge' => (string) ($leaf['preview_badge'] ?? ''),
                    '_drv_preview_price' => (string) ($leaf['preview_price'] ?? ''),
                    '_drv_preview_desc'  => (string) ($leaf['preview_desc'] ?? ''),
                    '_drv_preview_img'   => basename((string) ($leaf['preview_img'] ?? 'room-living-calacatta.svg')),
                ]
            );
        }
    }
}

// 3. Seed Primary Silo Nav (3 cấp)
$silo_id = $ensure_clean_menu('DRAVIT - Thanh Điều Hướng Tầng 2');
$p       = 1;

$c1    = $add_custom_item($silo_id, 'Theo công năng', '/gach-lat-nen', 0, $p++);
$c1_s1 = $add_custom_item($silo_id, 'Gạch lát nền', '/gach-lat-nen', $c1, $p++, ['_drv_icon' => 'ph-grid-four']);
$add_custom_item($silo_id, 'Gạch 80x80 cm', '/gach-80x80', $c1_s1, $p++);
$add_custom_item($silo_id, 'Khổ lớn 60x120 cm', '/gach-60x120', $c1_s1, $p++);
$add_custom_item($silo_id, 'Gạch giả gỗ', '/gach-gia-go', $c1_s1, $p++);

$c1_s2 = $add_custom_item($silo_id, 'Gạch ốp tường', '/gach-op-tuong', $c1, $p++, ['_drv_icon' => 'ph-wall']);
$add_custom_item($silo_id, 'Gạch ốp 30x60 cm', '/gach-30x60', $c1_s2, $p++);
$add_custom_item($silo_id, 'Gạch thẻ kiến trúc', '/gach-the', $c1_s2, $p++);
$add_custom_item($silo_id, 'Gạch bông gió', '/gach-bong-gio', $c1_s2, $p++);

$c1_s3 = $add_custom_item($silo_id, 'Trang trí & Mosaic', '/gach-trang-tri', $c1, $p++, ['_drv_icon' => 'ph-sparkle']);
$add_custom_item($silo_id, 'Gạch trang trí', '/gach-trang-tri', $c1_s3, $p++);
$add_custom_item($silo_id, 'Gạch bông', '/gach-bong', $c1_s3, $p++);
$add_custom_item($silo_id, 'Gạch Mosaic', '/gach-mosaic', $c1_s3, $p++);

$c2    = $add_custom_item($silo_id, 'Theo không gian', '/gach-phong-khach', 0, $p++);
$c2_s1 = $add_custom_item($silo_id, 'Phòng khách & Sảnh', '/gach-phong-khach', $c2, $p++, ['_drv_icon' => 'ph-armchair']);
$add_custom_item($silo_id, 'Gạch phòng khách', '/gach-phong-khach', $c2_s1, $p++);
$add_custom_item($silo_id, 'Gạch phòng ngủ', '/gach-phong-ngu', $c2_s1, $p++);
$add_custom_item($silo_id, 'Đá nung kết đại sảnh', '/da-nung-ket', $c2_s1, $p++);

$c2_s2 = $add_custom_item($silo_id, 'Bếp & Nhà tắm', '/gach-nha-tam', $c2, $p++, ['_drv_icon' => 'ph-drop']);
$add_custom_item($silo_id, 'Gạch nhà tắm R10', '/gach-nha-tam', $c2_s2, $p++);
$add_custom_item($silo_id, 'Gạch phòng bếp', '/gach-phong-bep', $c2_s2, $p++);

$c2_s3 = $add_custom_item($silo_id, 'Ngoại thất & Sân vườn', '/gach-san-vuon', $c2, $p++, ['_drv_icon' => 'ph-sun']);
$add_custom_item($silo_id, 'Gạch sân vườn', '/gach-san-vuon', $c2_s3, $p++);
$add_custom_item($silo_id, 'Gạch ngoài trời', '/gach-ngoai-troi', $c2_s3, $p++);
$add_custom_item($silo_id, 'Gạch mặt tiền', '/gach-mat-tien', $c2_s3, $p++);
$add_custom_item($silo_id, 'Gạch hồ bơi', '/gach-ho-boi', $c2_s3, $p++);

$add_custom_item($silo_id, 'Vân & Chất liệu', '/gach-van-da', 0, $p++);
$add_custom_item($silo_id, 'Theo kích thước', '/gach-80x80', 0, $p++);
$add_custom_item($silo_id, 'Đá nung kết', '/da-nung-ket', 0, $p++);
$add_custom_item($silo_id, 'Gạch ưu đãi', '/gach-uu-dai', 0, $p++, ['_drv_highlight' => 'sale']);

// 4. Seed Primary Utility Nav
$util_id = $ensure_clean_menu('DRAVIT - Tiện Ích Tầng 2');
$add_custom_item($util_id, 'Trợ lý AI Tìm gạch', '/tro-ly-ai-tim-gach', 0, 1, ['_drv_icon' => 'ph-fill ph-robot', '_drv_highlight' => 'teal']);
$add_custom_item($util_id, 'Cẩm nang kỹ thuật', '/cam-nang', 0, 2, ['_drv_icon' => 'ph-book-open']);
$add_custom_item($util_id, 'Liên hệ & Showroom', '/lien-he', 0, 3, ['_drv_icon' => 'ph-map-pin']);

// 5. Seed 3 cột Footer có thể sửa trong WP Admin
$f2_id = $ensure_clean_menu('Hướng dẫn');
$add_custom_item($f2_id, 'Quy trình mua gạch & tính thùng', '/huong-dan-mua-hang', 0, 1);
$add_custom_item($f2_id, 'Trợ lý AI tìm gạch theo phòng', '/tro-ly-ai-tim-gach', 0, 2);
$add_custom_item($f2_id, 'Lập bảng báo giá BOQ công trình', '/danh-sach-bao-gia', 0, 3);
$add_custom_item($f2_id, 'Cẩm nang chọn gạch & thi công', '/cam-nang', 0, 4);

$f3_id = $ensure_clean_menu('Chính sách');
$add_custom_item($f3_id, 'Giao hàng xe cẩu & đồng kiểm', '/chinh-sach-van-chuyen', 0, 1);
$add_custom_item($f3_id, 'Đổi trả gạch dư & bảo hành lô màu', '/chinh-sach-doi-tra', 0, 2);
$add_custom_item($f3_id, 'Thanh toán VietQR & xuất VAT', '/chinh-sach-thanh-toan', 0, 3);
$add_custom_item($f3_id, 'Chính sách bảo mật thông tin', '/chinh-sach-bao-mat', 0, 4);

$f4_id = $ensure_clean_menu('Đối tác & Doanh nghiệp');
$add_custom_item($f4_id, 'Giới thiệu thương hiệu DRAVIT', '/gioi-thieu', 0, 1);
$add_custom_item($f4_id, 'Hệ thống Showroom trưng bày', '/lien-he', 0, 2);
$add_custom_item($f4_id, 'Hợp tác Kiến trúc sư & Nhà thầu', '/lien-he', 0, 3);
$add_custom_item($f4_id, 'Kho 28 danh mục gạch chính hãng', '/gach', 0, 4);

// 6. Gán cả 6 Menu vào theme_mod `nav_menu_locations`
$locations                     = get_theme_mod('nav_menu_locations', []);
$locations                     = is_array($locations) ? $locations : [];
$locations['mega_menu']        = $mega_id;
$locations['primary_silo_nav'] = $silo_id;
$locations['primary_utility']  = $util_id;
$locations['footer_col_2']     = $f2_id;
$locations['footer_col_3']     = $f3_id;
$locations['footer_col_4']     = $f4_id;
set_theme_mod('nav_menu_locations', $locations);

MegaMenuRenderer::purge_cache();
flush_rewrite_rules(false);

echo sprintf(
    "Step 1 Seed OK: /ui-kit (ID=%d), mega_menu=%d, primary_silo_nav=%d, primary_utility=%d, footer_col_2=%d, footer_col_3=%d, footer_col_4=%d\n",
    $uikit_id,
    $mega_id,
    $silo_id,
    $util_id,
    $f2_id,
    $f3_id,
    $f4_id
);
