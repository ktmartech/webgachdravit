<?php
/**
 * Tạo / xóa 10 bài Cẩm nang thử phân trang (Gate 10). Chỉ dùng ở local.
 *
 * Chạy:  tools/wp-cli.ps1 eval-file tools/seed-test-posts.php           (tạo đủ 10 bài, idempotent)
 *        tools/wp-cli.ps1 eval-file tools/seed-test-posts.php remove    (xóa vĩnh viễn các bài thử)
 *
 * Bài thử có meta `_drv_test_seed = 1` và tiêu đề tiền tố "[TEST]". Phải ở trạng thái `publish` thì mới xuất hiện ở
 * `/cam-nang` và `/chu-de/{slug}`; bản nháp không hiện ra frontend nên không thử phân trang được.
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$existing = get_posts([
    'post_type'      => 'post',
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'meta_key'       => '_drv_test_seed',
    'meta_value'     => '1',
]);

if (in_array('remove', (array) ($args ?? []), true)) {
    foreach ($existing as $id) {
        wp_delete_post((int) $id, true);
    }
    echo 'Đã xóa ' . count($existing) . " bài thử.\n";
    return;
}

$cats = get_categories(['hide_empty' => false, 'fields' => 'ids']);
$cat  = get_category_by_slug('chon-gach-theo-phong');
$cat_id = $cat ? (int) $cat->term_id : (int) ($cats[0] ?? 0);

$made = 0;
for ($i = count($existing) + 1; $i <= 10; $i++) {
    $id = wp_insert_post([
        'post_type'    => 'post',
        'post_status'  => 'publish',
        'post_title'   => sprintf('[TEST] Bài thử phân trang số %02d', $i),
        'post_name'    => sprintf('test-phan-trang-%02d', $i),
        'post_content' => "<p>Bài thử phân trang số {$i}. Xóa bằng tham số remove.</p>",
        'post_excerpt' => "Bài thử phân trang số {$i}.",
        'post_date'    => gmdate('Y-m-d H:i:s', time() - $i * 3600),
        'post_category' => $cat_id > 0 ? [$cat_id] : [],
    ], true);
    if (!is_wp_error($id)) {
        update_post_meta((int) $id, '_drv_test_seed', '1');
        $made++;
    }
}
echo "Đã tạo {$made} bài thử (tổng " . (count($existing) + $made) . ").\n";
