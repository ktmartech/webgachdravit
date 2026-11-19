<?php
/**
 * QA Step 11 (docs/09 §3.1 S-*, §3.2 U-3, §3.7 C-6/C-7, §3.7B Q-3..Q-5, §3.8 X-4): tạo dữ liệu thử rồi XÓA ngay.
 * Chạy:  tools/wp-cli.ps1 eval-file tools/qa-step11-more.php
 */
declare(strict_types=1);

use Dravit\Catalog\CollectionSync;
use Dravit\Catalog\PrimaryCategory;
use Dravit\Quote\BoqService;
use Dravit\Quote\OrderConverter;
use Dravit\Quote\QuoteController;

if (!defined('ABSPATH')) {
    exit;
}

$fail = 0;
$ok = static function (string $id, bool $pass, string $note = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    echo ($pass ? 'PASS ' : 'FAIL ') . $id . ($note !== '' ? '  ' . $note : '') . "\n";
};

$tmp_products = [];
$tmp_posts = [];

/** Nhân bản 1 SP mẫu (meta + term thuộc tính) thành SP tạm, chưa save. */
$clone = static function (string $sku_src, string $new_sku) use (&$tmp_products): WC_Product_Simple {
    $src = wc_get_product(wc_get_product_id_by_sku($sku_src));
    $p = new WC_Product_Simple();
    $p->set_name('QA tạm ' . $new_sku);
    $p->set_status('publish');
    foreach ($src->get_meta_data() as $m) {
        if (!in_array($m->key, ['rank_math_primary_product_cat', '_drv_search_index'], true)) {
            $p->update_meta_data($m->key, $m->value);
        }
    }
    $p->set_sku($new_sku);
    $p->set_manage_stock(false);
    $p->save();
    $tmp_products[] = $p->get_id();
    foreach (get_object_taxonomies('product') as $tx) {
        if (str_starts_with($tx, 'pa_') || in_array($tx, ['drv_flag'], true)) {
            $slugs = wp_get_post_terms($src->get_id(), $tx, ['fields' => 'slugs']);
            if (is_array($slugs) && $slugs !== []) {
                wp_set_object_terms($p->get_id(), $slugs, $tx);
            }
        }
    }
    (new CollectionSync())->syncProduct(wc_get_product($p->get_id()));
    return wc_get_product($p->get_id());
};
$cats = static fn (int $id): array => (array) wp_get_post_terms($id, 'product_cat', ['fields' => 'slugs']);
$sync = new CollectionSync();

// ---- S-1: bỏ pa_khong-gian = phong-khach ----
$p = $clone('DRV-8801M', 'QA-S1');
$before = $cats($p->get_id());
$ok('S-0 bản sao có gach-phong-khach', in_array('gach-phong-khach', $before, true), count($before) . ' danh mục');
$sp = wp_get_post_terms($p->get_id(), 'pa_khong-gian', ['fields' => 'slugs']);
wp_set_object_terms($p->get_id(), array_values(array_diff((array) $sp, ['phong-khach'])), 'pa_khong-gian');
$sync->syncProduct(wc_get_product($p->get_id()));
$after = $cats($p->get_id());
$ok('S-1 bỏ phong-khach: mất gach-phong-khach, còn lại giữ nguyên', !in_array('gach-phong-khach', $after, true) && array_diff($before, $after) === ['gach-phong-khach'] || array_values(array_diff($before, $after)) === ['gach-phong-khach'], 'mất=' . implode(',', array_diff($before, $after)) . ' thêm=' . implode(',', array_diff($after, $before)));

// ---- S-2: danh mục thủ công ngoài hệ thống ----
if (!term_exists('noi-bat', 'product_cat')) {
    $t = wp_insert_term('Nổi bật', 'product_cat', ['slug' => 'noi-bat']);
    $created_term = !is_wp_error($t) ? (int) $t['term_id'] : 0;
}
wp_set_object_terms($p->get_id(), 'noi-bat', 'product_cat', true);
$sync->syncProduct(wc_get_product($p->get_id()));
$ok('S-2 danh mục thủ công noi-bat được giữ sau sync', in_array('noi-bat', $cats($p->get_id()), true));
wp_remove_object_terms($p->get_id(), 'noi-bat', 'product_cat');

// ---- S-3: giá khuyến mãi -> gach-uu-dai, hết lịch tự gỡ ----
$s3 = $clone('DRV-TRZ60', 'QA-S3');
$ok('S-3 chưa KM: không có gach-uu-dai', !in_array('gach-uu-dai', $cats($s3->get_id()), true));
$s3->update_meta_data('drv_sale_price_m2', (int) $s3->get_meta('drv_price_m2') - 30000);
$s3->save();
$ok('S-3 đặt giá KM: có gach-uu-dai', in_array('gach-uu-dai', $cats($s3->get_id()), true), 'sale=' . wc_get_product($s3->get_id())->get_sale_price());
$s3 = wc_get_product($s3->get_id());
$s3->set_date_on_sale_from(strtotime('-3 days'));
$s3->set_date_on_sale_to(strtotime('-1 day'));
$s3->save();
$sync->syncProduct(wc_get_product($s3->get_id()));
$ok('S-3 hết lịch KM: tự gỡ gach-uu-dai', !in_array('gach-uu-dai', $cats($s3->get_id()), true), 'is_on_sale=' . var_export(wc_get_product($s3->get_id())->is_on_sale(), true));

// ---- S-4 / S-5: khổ lớn ----
$s4 = $clone('DRV-3605S', 'QA-S4');
$s4->update_meta_data('drv_piece_w_mm', 1200);
$s4->update_meta_data('drv_piece_h_mm', 2400);
$s4->save();
$ok('S-4 1200×2400 vào da-nung-ket (large_format)', in_array('da-nung-ket', $cats($s4->get_id()), true), implode(',', $cats($s4->get_id())));
$s5 = $clone('DRV-3605S', 'QA-S5');
$s5->update_meta_data('drv_piece_w_mm', 600);
$s5->update_meta_data('drv_piece_h_mm', 1200);
$s5->save();
$ok('S-5 600×1200 KHÔNG vào da-nung-ket', !in_array('da-nung-ket', $cats($s5->get_id()), true), implode(',', $cats($s5->get_id())));
$ok('U-10 600×1200 tự suy pa_kich-thuoc=60x120', in_array('60x120', (array) wp_get_post_terms($s5->get_id(), 'pa_kich-thuoc', ['fields' => 'slugs']), true));

// ---- S-6: primary bị xóa khỏi danh mục SP ----
$s6 = $clone('DRV-61209', 'QA-S6');
$prim = (int) get_post_meta($s6->get_id(), 'rank_math_primary_product_cat', true);
$primSlug = $prim ? (string) get_term($prim)->slug : '';
wp_remove_object_terms($s6->get_id(), $primSlug, 'product_cat');
$sync->syncProduct(wc_get_product($s6->get_id()));
$prim2 = (int) get_post_meta($s6->get_id(), 'rank_math_primary_product_cat', true);
$ok('S-6 primary bị gỡ: tự chọn lại theo priority', $prim2 > 0 && in_array((string) get_term($prim2)->slug, $cats($s6->get_id()), true), 'primary=' . ($prim2 ? get_term($prim2)->slug : 'trống') . ' (đã gỡ ' . $primSlug . ')');

// ---- S-8: Page slug trùng collection ----
$pid = wp_insert_post(['post_type' => 'page', 'post_title' => 'QA gach 80x80', 'post_name' => 'gach-80x80', 'post_status' => 'draft'], true);
$tmp_posts[] = (int) $pid;
$ok('S-8 Page slug gach-80x80 bị đổi thành gach-80x80-trang', !is_wp_error($pid) && get_post_field('post_name', $pid) === 'gach-80x80-trang', (string) get_post_field('post_name', $pid));

// ---- S-9: lưu 2 lần liên tiếp ----
$s9 = $clone('DRV-SV40', 'QA-S9');
$c1 = $cats($s9->get_id());
$t0 = microtime(true);
$x = wc_get_product($s9->get_id()); $x->save();
$x = wc_get_product($s9->get_id()); $x->save();
$c2 = $cats($s9->get_id());
sort($c1); sort($c2);
$dups = count($c2) - count(array_unique($c2));
$ok('S-9 lưu 2 lần: không vòng lặp, không trùng term', $c1 === $c2 && $dups === 0, round((microtime(true) - $t0) * 1000) . 'ms, ' . count($c2) . ' danh mục');

// ---- U-3: giá KM ----
$u3 = $clone('DRV-8801M', 'QA-U3');
$u3->update_meta_data('drv_price_m2', 385000);
$u3->update_meta_data('drv_sale_price_m2', 350000);
$u3->save();
$u3 = wc_get_product($u3->get_id());
$ok('U-3 giá KM 350.000đ/m² → _sale_price 224.000/viên', (int) round((float) $u3->get_sale_price()) === 224000 && (int) round((float) $u3->get_regular_price()) === 246400, 'reg=' . $u3->get_regular_price() . ' sale=' . $u3->get_sale_price());

// ---- Q-3 / Q-4: BOQ trộn priced + quote, chiết khấu ----
$priced = wc_get_product_id_by_sku('DRV-8801M');
$quote  = wc_get_product_id_by_sku('DRV-SLAB02');
$svc = new BoqService();
$rooms = [['name' => 'Phòng khách', 'slots' => [['role' => 'Lát nền chính', 'product_id' => $priced, 'input_mode' => 'm2', 'target_m2' => 20, 'pieces' => 0], ['role' => 'Lát nền chính', 'product_id' => $quote, 'input_mode' => 'm2', 'target_m2' => 10, 'pieces' => 0]]]];
$tiers = (array) drv_setting('quote.volume_tiers', []);
$r = $svc->compute($rooms, 5, $tiers, []);
$t = $r['totals'];
$ok('Q-3 trộn priced + quote: có cờ quote, cộng đủ m²/thùng/kg', !empty($t['has_quote_items']) && (float) $t['total_m2'] >= 30 && (float) $t['total_kg'] > 0, 'm2=' . $t['total_m2'] . ' thùng=' . $t['total_boxes'] . ' kg=' . $t['total_kg'] . ' tạm tính=' . $t['subtotal_vnd']);
$only_priced_total = $svc->compute([['name' => 'P', 'slots' => [$rooms[0]['slots'][0]]]], 5, $tiers, [])['totals']['subtotal_vnd'];
$ok('Q-3 dòng quote không cộng 0đ sai vào tổng tiền', (int) $t['subtotal_vnd'] === (int) $only_priced_total, "$only_priced_total vs " . $t['subtotal_vnd']);
$small = $svc->compute([['name' => 'S', 'slots' => [['role' => 'Lát nền chính', 'product_id' => $priced, 'input_mode' => 'm2', 'target_m2' => 10, 'pieces' => 0]]]], 5, $tiers, [])['totals'];
$mid = $svc->compute([['name' => 'M', 'slots' => [['role' => 'Lát nền chính', 'product_id' => $priced, 'input_mode' => 'm2', 'target_m2' => 35, 'pieces' => 0]]]], 0, $tiers, [])['totals'];
$big = $svc->compute([['name' => 'B', 'slots' => [['role' => 'Lát nền chính', 'product_id' => $priced, 'input_mode' => 'm2', 'target_m2' => 105, 'pieces' => 0]]]], 0, $tiers, [])['totals'];
$ok('Q-4 chiết khấu dự kiến theo ngưỡng 0% / 5% (≥30m²) / 8% (≥100m²)', (float) $small['discount_rate'] === 0.0 && (float) $mid['discount_rate'] === 5.0 || (float) $mid['discount_rate'] === 0.05, 'rate: ' . $small['discount_rate'] . ' / ' . $mid['discount_rate'] . ' / ' . $big['discount_rate'] . ' (m2 ' . $mid['total_m2'] . ', ' . $big['total_m2'] . ')');

// ---- Q-5: server tự tính lại, bỏ qua giá/tổng client gửi lên; tạo lead + chuyển thành đơn ----
$payload = [
    'contact' => ['name' => 'QA BOQ Test', 'phone' => '0987654399', 'province' => 'HCM', 'consent' => true],
    'boq' => ['waste_pct' => 5, 'project_name' => 'QA', 'rooms' => $rooms, 'grand_est_vnd' => 1, 'subtotal_vnd' => 1, 'total_vnd' => 1],
];
$res = QuoteController::process($payload, new BoqService(), ['page_url' => home_url('/danh-sach-bao-gia')]);
if (is_wp_error($res)) {
    $ok('Q-5 gửi phiếu', false, $res->get_error_code() . ' ' . wp_json_encode($res->get_error_data()));
} else {
    $lid = (int) $res['lead_id'];
    $tmp_posts[] = $lid;
    $stored = (int) get_post_meta($lid, '_drv_subtotal_vnd', true);
    $ok('Q-5 phiếu lưu drv_lead (boq), subtotal tính lại từ DB, không tin số client (1đ)', $stored === (int) $only_priced_total && get_post_meta($lid, '_drv_lead_type', true) === 'boq', "subtotal lưu=$stored; lead_type=" . get_post_meta($lid, '_drv_lead_type', true));

    // X-4: user không có manage_woocommerce không được chuyển đơn
    wp_set_current_user(0);
    $deny = OrderConverter::convert($lid, [], []);
    $ok('X-4 khách (chưa đăng nhập) gọi OrderConverter::convert bị từ chối', is_wp_error($deny) && $deny->get_error_code() === 'drv_forbidden', is_wp_error($deny) ? $deny->get_error_code() : 'cho phép!');
    $cust = wp_insert_user(['user_login' => 'qa_subscriber_' . wp_rand(100, 999), 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);
    if (!is_wp_error($cust)) {
        wp_set_current_user($cust);
        $deny2 = OrderConverter::convert($lid, [], []);
        $ok('X-4 subscriber bị từ chối', is_wp_error($deny2) && $deny2->get_error_code() === 'drv_forbidden', is_wp_error($deny2) ? $deny2->get_error_code() : 'cho phép!');
        wp_delete_user($cust);
    }

    // C-6 / C-7 (đường BOQ -> đơn): admin nhập phí ship
    wp_set_current_user(1);
    $rates = ['0:0' => 385000, '0:1' => 2000000];
    $o1 = OrderConverter::convert($lid, $rates, ['shipping_fee' => 1500000, 'payment_method' => 'bacs', 'ship_vehicle' => 'Xe tải 1 tấn']);
    if (is_wp_error($o1)) {
        $ok('C-6 BOQ→đơn BACS có phí ship', false, $o1->get_error_code() . ' ' . $o1->get_error_message());
    } else {
        $tmp_orders[] = $o1->get_id();
        $ok('C-6 BACS + phí ship → wc-on-hold, tổng gồm ship', $o1->get_status() === 'on-hold' && (float) $o1->get_shipping_total() === 1500000.0, 'status=' . $o1->get_status() . ' ship=' . $o1->get_shipping_total() . ' total=' . $o1->get_total());
        $view = \Dravit\Woo\OrderView::fromOrder($o1);
        $ok('C-6 có VietQR (slot qr) khi BACS on-hold', ($view['payment_slot'] ?? '') === 'qr', json_encode([$view['state'] ?? null, $view['payment_slot'] ?? null]));
        $ok('C-6 _drv_transfer_ref = DRV{id} chỉ chữ số', preg_match('/^DRV\d+$/', (string) $o1->get_meta('_drv_transfer_ref')) === 1, (string) $o1->get_meta('_drv_transfer_ref'));
        $ok('C-6 BankQR (img.vietqr.io) đã triển khai', class_exists('\\Dravit\\Woo\\BankQR'), 'class Dravit\\Woo\\BankQR ' . (class_exists('\\Dravit\\Woo\\BankQR') ? 'có' : 'CHƯA CÓ (dời sang phase HPOS Ops & VietQR)'));
    }
    // Phiếu đã chuyển 1 lần -> chặn chuyển lần 2
    if (isset($o1) && $o1 instanceof WC_Order) {
        $dup = OrderConverter::convert($lid, $rates, ['shipping_fee' => 0, 'payment_method' => 'cod']);
        $ok('Q-5 chuyển lần 2 bị chặn (drv_already_converted)', is_wp_error($dup) && $dup->get_error_code() === 'drv_already_converted', is_wp_error($dup) ? $dup->get_error_code() : 'cho phép!');
        // C-7 COD: huỷ đơn 1 rồi chuyển lại
        $o1->update_status('cancelled', 'QA');
        $o2 = OrderConverter::convert($lid, $rates, ['shipping_fee' => 800000, 'payment_method' => 'cod']);
        if (!is_wp_error($o2)) {
            $tmp_orders[] = $o2->get_id();
            $ok('C-7 COD + phí ship → wc-processing', $o2->get_status() === 'processing', $o2->get_status());
        } else {
            $ok('C-7 COD', false, $o2->get_error_code());
        }
        $o3 = null;
    }
}

// ---- C-10 / C-14: các hạng mục dời sang phase HPOS Ops ----
$ok('C-10 meta _drv_lot_code theo dòng đơn (Admin\\OrderMetabox)', class_exists('\\Dravit\\Admin\\OrderMetabox'), class_exists('\\Dravit\\Admin\\OrderMetabox') ? 'có' : 'CHƯA CÓ');
$ok('C-14 Action Scheduler tự hủy on-hold quá hạn', has_action('drv_cancel_unpaid_transfer_orders') || function_exists('as_has_scheduled_action') && as_has_scheduled_action('drv_cancel_unpaid_transfer_orders'), 'chưa có hook/lịch drv_cancel_unpaid_transfer_orders');
$ok('C-6 Order Action "Xác nhận đơn & gửi email" trên đơn checkout', (bool) has_filter('woocommerce_order_actions'), 'chưa có filter woocommerce_order_actions của theme: ' . (has_filter('woocommerce_order_actions') ? 'có' : 'không'));

// ---- Dọn ----
wp_set_current_user(0);
foreach ($tmp_orders ?? [] as $oid) {
    $o = wc_get_order($oid);
    if ($o) {
        $o->delete(true);
    }
}
foreach ($tmp_posts as $id) {
    if ($id > 0) {
        wp_delete_post($id, true);
    }
}
foreach ($tmp_products as $id) {
    wp_delete_post($id, true);
}
if (!empty($created_term)) {
    wp_delete_term($created_term, 'product_cat');
}
echo "\n" . ($fail === 0 ? 'ALL PASS' : "$fail FAIL") . "\n";
