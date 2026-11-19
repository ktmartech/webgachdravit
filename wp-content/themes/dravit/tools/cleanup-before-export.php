<?php
/**
 * Dọn dữ liệu test trước khi export All-in-One WP Migration (bản demo). Chỉ dùng ở local.
 *
 * Mặc định là DRY-RUN (chỉ đếm, không xóa). Thêm `apply` để xóa thật.
 *
 *   tools/wp-cli.ps1 eval-file tools/cleanup-before-export.php                 # dry-run
 *   tools/wp-cli.ps1 eval-file tools/cleanup-before-export.php apply           # xóa đơn QA + rác
 *   tools/wp-cli.ps1 eval-file tools/cleanup-before-export.php apply all-orders customers
 *
 * Cờ tùy chọn:
 *   all-orders  xóa MỌI đơn hàng (mặc định chỉ xóa đơn QA: tên billing bắt đầu bằng "QA"/"Test").
 *   customers   xóa user role `customer`.
 *
 * Luôn xóa: bài [TEST] (`_drv_test_seed`), CPT `drv_lead`, revision/auto-draft/thùng rác, comment spam/trash,
 * transient, session WC, Action Scheduler đã kết thúc, entry Fluent Forms, rồi purge cache.
 * KHÔNG đụng: sản phẩm, trang, bài thật, menu, `drv_settings`, user admin. Hãy backup DB (hoặc export .wpress) trước.
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit("Chạy file này thông qua WP-CLI eval-file.\n");
}

global $wpdb;

$flags      = array_map('strval', (array) ($args ?? []));
$apply      = in_array('apply', $flags, true);
$all_orders = in_array('all-orders', $flags, true);
$customers  = in_array('customers', $flags, true);

echo ($apply ? "== APPLY: xóa thật ==\n" : "== DRY-RUN: chỉ đếm, thêm `apply` để xóa ==\n");

$report = static function (string $label, int $n): void {
    printf("  %-48s %d\n", $label, $n);
};

$table_exists = static function (string $table) use ($wpdb): bool {
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
};

// ── 1. Đơn hàng (HPOS, qua WC CRUD) ─────────────────────────────────────────
$statuses = array_merge(array_keys(wc_get_order_statuses()), ['trash', 'checkout-draft']);
$order_ids = wc_get_orders(['type' => 'shop_order', 'status' => $statuses, 'limit' => -1, 'return' => 'ids']);
$target_orders = [];
foreach ($order_ids as $oid) {
    $order = wc_get_order($oid);
    if (!$order instanceof WC_Order) {
        continue;
    }
    $name = strtolower(trim($order->get_billing_first_name()));
    if ($all_orders || $name === '' || str_starts_with($name, 'qa') || str_starts_with($name, 'test')) {
        $target_orders[] = $oid;
    }
}
$report($all_orders ? 'Đơn hàng (tất cả)' : 'Đơn hàng QA/test/rỗng tên', count($target_orders));
$report('Đơn hàng còn lại (giữ)', count($order_ids) - count($target_orders));
if ($apply) {
    foreach ($target_orders as $oid) {
        $order = wc_get_order($oid);
        if ($order instanceof WC_Order) {
            $order->delete(true);
        }
    }
}

// ── 2. Lead BOQ / form liên hệ (CPT private drv_lead, chứa PII) ─────────────
$lead_ids = get_posts(['post_type' => 'drv_lead', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids']);
$report('Lead drv_lead', count($lead_ids));
if ($apply) {
    foreach ($lead_ids as $id) {
        wp_delete_post((int) $id, true);
    }
}

// ── 3. Bài viết [TEST] ──────────────────────────────────────────────────────
$test_posts = get_posts([
    'post_type' => 'any', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids',
    'meta_key' => '_drv_test_seed', 'meta_value' => '1',
]);
$report('Bài thử (_drv_test_seed)', count($test_posts));
if ($apply) {
    foreach ($test_posts as $id) {
        wp_delete_post((int) $id, true);
    }
}

// ── 4. Revision / auto-draft / thùng rác ────────────────────────────────────
$junk_posts = $wpdb->get_col(
    "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision' OR post_status IN ('auto-draft','trash')"
);
$report('Revision + auto-draft + thùng rác', count($junk_posts));
if ($apply) {
    foreach ($junk_posts as $id) {
        wp_delete_post((int) $id, true);
    }
}

// ── 5. Comment spam / trash ─────────────────────────────────────────────────
$bad_comments = array_merge(
    get_comments(['status' => 'spam', 'fields' => 'ids']),
    get_comments(['status' => 'trash', 'fields' => 'ids'])
);
$report('Comment spam/trash', count($bad_comments));
if ($apply) {
    foreach ($bad_comments as $cid) {
        wp_delete_comment((int) $cid, true);
    }
}

// ── 6. User customer (tùy chọn) ─────────────────────────────────────────────
$customer_ids = get_users(['role' => 'customer', 'fields' => 'ID']);
$report('User role customer' . ($customers ? '' : ' (giữ, thêm `customers` để xóa)'), count($customer_ids));
if ($apply && $customers) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($customer_ids as $uid) {
        wp_delete_user((int) $uid);
    }
}

// ── 7. Transient + session WC ───────────────────────────────────────────────
$transients = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_%' OR option_name LIKE '\_site\_transient\_%'"
);
$report('Transient', $transients);
if ($apply) {
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_%' OR option_name LIKE '\_site\_transient\_%'"
    );
}

$sessions_table = $wpdb->prefix . 'woocommerce_sessions';
if ($table_exists($sessions_table)) {
    $report('Session WC', (int) $wpdb->get_var("SELECT COUNT(*) FROM {$sessions_table}"));
    if ($apply) {
        $wpdb->query("TRUNCATE TABLE {$sessions_table}");
    }
}

// ── 8. Action Scheduler đã kết thúc (giữ các action pending) ────────────────
$as_actions = $wpdb->prefix . 'actionscheduler_actions';
$as_logs    = $wpdb->prefix . 'actionscheduler_logs';
if ($table_exists($as_actions)) {
    $report(
        'Action Scheduler đã complete/canceled/failed',
        (int) $wpdb->get_var("SELECT COUNT(*) FROM {$as_actions} WHERE status IN ('complete','canceled','failed')")
    );
    if ($apply) {
        $wpdb->query("DELETE FROM {$as_actions} WHERE status IN ('complete','canceled','failed')");
        if ($table_exists($as_logs)) {
            $wpdb->query("DELETE l FROM {$as_logs} l LEFT JOIN {$as_actions} a ON a.action_id = l.action_id WHERE a.action_id IS NULL");
        }
    }
}

// ── 9. Entry Fluent Forms (chỉ dữ liệu gửi, giữ cấu hình form) ──────────────
foreach (['fluentform_submissions', 'fluentform_entry_details', 'fluentform_submission_meta', 'fluentform_logs'] as $t) {
    $full = $wpdb->prefix . $t;
    if ($table_exists($full)) {
        $report("Fluent Forms: {$t}", (int) $wpdb->get_var("SELECT COUNT(*) FROM {$full}"));
        if ($apply) {
            $wpdb->query("TRUNCATE TABLE {$full}");
        }
    }
}

// ── 10. Cache ───────────────────────────────────────────────────────────────
if ($apply) {
    wp_cache_flush();
    do_action('litespeed_purge_all');
    echo "  Đã flush object cache + purge LiteSpeed.\n";
}

// ── Nhắc kiểm tra thủ công ──────────────────────────────────────────────────
$uikit = get_page_by_path('ui-kit', OBJECT, 'page');
if ($uikit instanceof WP_Post && $uikit->post_status === 'publish') {
    echo "\nLƯU Ý: trang /ui-kit đang publish. Chuyển sang Nháp nếu không muốn lộ trên demo.\n";
}
echo "LƯU Ý: xóa đơn không hoàn tồn kho. Kiểm tra lại tồn kho các SKU từng dùng test (vd DRV-TH7530).\n";
echo $apply ? "\nXong. Chạy lại không có `apply` để xác nhận mọi thứ về 0.\n" : "\nDry-run xong, chưa xóa gì.\n";
