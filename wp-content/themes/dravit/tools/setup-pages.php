<?php
/**
 * Tạo / đồng bộ các trang văn bản hệ thống ở chân trang (Hướng dẫn · Chính sách · Đối tác).
 *
 * Nội dung lấy từ `config/pages/system-pages.php`; thông tin thương hiệu trong bài là shortcode `[drv_info]`
 * đọc `drv_settings`, nên đổi ở Admin → Cài đặt Hệ thống DRAVIT là mọi trang tự cập nhật, không cần chạy lại file này.
 *
 * Chạy:  tools/wp-cli.ps1 eval-file tools/setup-pages.php            (chỉ tạo mới + cập nhật trang chưa bị biên tập tay)
 *        tools/wp-cli.ps1 eval-file tools/setup-pages.php force    (ghi đè cả trang đã sửa tay)
 *
 * Idempotent: lưu `_drv_seed_hash` = md5 nội dung đã nạp; trang chỉ bị ghi đè khi nội dung hiện tại còn khớp hash đó,
 * đang trống, hoặc có đối số `force`.
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$force = in_array('force', (array) ($args ?? []), true);
$defs  = (array) require get_template_directory() . '/config/pages/system-pages.php';

foreach ($defs as $slug => $def) {
    $title   = (string) $def['title'];
    $content = trim((string) $def['body']);
    $tpl     = (string) $def['template'];
    $page    = get_page_by_path((string) $slug, OBJECT, 'page');

    if (!$page instanceof WP_Post) {
        $page_id = (int) wp_insert_post([
            'post_type'      => 'page',
            'post_title'     => $title,
            'post_name'      => (string) $slug,
            'post_content'   => $content,
            'post_status'    => 'publish',
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
        ], true);
        echo "CREATED  {$slug} (#{$page_id})\n";
    } else {
        $page_id = (int) $page->ID;
        $current = trim((string) $page->post_content);
        $seeded  = (string) get_post_meta($page_id, '_drv_seed_hash', true);
        $pristine = $current === '' || ($seeded !== '' && hash_equals($seeded, md5($current)));

        if (!$force && !$pristine) {
            echo "SKIPPED  {$slug} (đã được biên tập tay; dùng đối số force để ghi đè)\n";
            continue;
        }

        wp_update_post([
            'ID'           => $page_id,
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'publish',
        ]);
        echo "UPDATED  {$slug} (#{$page_id})\n";
    }

    update_post_meta($page_id, '_wp_page_template', $tpl);
    update_post_meta($page_id, '_drv_system_page', '1');
    update_post_meta($page_id, '_drv_seed_hash', md5($content));
    if ((string) get_post_meta($page_id, 'drv_effective_date', true) === '') {
        update_post_meta($page_id, 'drv_effective_date', wp_date('d/m/Y'));
    }
}

// Menu chân trang: "Hợp tác Kiến trúc sư & Nhà thầu" trỏ về trang riêng thay vì /lien-he.
$items = get_posts(['post_type' => 'nav_menu_item', 'numberposts' => -1, 'post_status' => 'any']);
foreach ($items as $item) {
    if ($item->post_title === 'Hợp tác Kiến trúc sư & Nhà thầu' || $item->post_title === 'Hợp tác Kiến trúc sư &amp; Nhà thầu') {
        $old = (string) get_post_meta($item->ID, '_menu_item_url', true);
        $new = home_url('/hop-tac-kts-nha-thau');
        if (rtrim($old, '/') !== rtrim($new, '/')) {
            update_post_meta($item->ID, '_menu_item_url', $new);
            echo "MENU     #{$item->ID} {$old} -> {$new}\n";
        }
    }
}

flush_rewrite_rules(false);
echo "done\n";
