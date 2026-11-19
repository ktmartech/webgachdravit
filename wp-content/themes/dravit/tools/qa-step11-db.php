<?php
/**
 * QA Step 11 (docs/09 §3.1, §3.2, §3.7) chạy trên DB local, chỉ đọc (không ghi, không tạo đơn).
 * Chạy:  tools/wp-cli.ps1 eval-file tools/qa-step11-db.php
 */
declare(strict_types=1);

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

// ---- §3.1: 8 SKU seed ----
$expected = [
    'DRV-8801M'  => [['gach', 'gach-lat-nen', 'gach-phong-khach', 'gach-mat-tien', 'gach-van-da', 'gach-porcelain', 'gach-80x80'], 'gach-lat-nen'],
    'DRV-3605S'  => [['gach', 'gach-lat-nen', 'gach-op-tuong', 'gach-nha-tam', 'gach-phong-bep', 'gach-van-da', 'gach-porcelain', 'gach-30x60'], 'gach-op-tuong'],
    'DRV-61209'  => [['gach', 'gach-lat-nen', 'gach-op-tuong', 'gach-phong-khach', 'gach-nha-tam', 'gach-phong-ngu', 'gach-van-xi-mang', 'gach-porcelain', 'gach-60x120'], 'gach-60x120'],
    'DRV-SLAB01' => [['gach', 'gach-lat-nen', 'gach-op-tuong', 'gach-trang-tri', 'gach-phong-khach', 'gach-mat-tien', 'gach-phong-bep', 'da-nung-ket', 'gach-van-da'], 'da-nung-ket'],
    'DRV-W1590'  => [['gach', 'gach-lat-nen', 'gach-op-tuong', 'gach-phong-ngu', 'gach-phong-khach', 'gach-ngoai-troi', 'gach-gia-go', 'gach-porcelain', 'gach-15x80-20x100'], 'gach-gia-go'],
    'DRV-TRZ60'  => [['gach', 'gach-lat-nen', 'gach-op-tuong', 'gach-trang-tri', 'gach-phong-khach', 'gach-nha-tam', 'gach-phong-bep', 'gach-ngoai-troi', 'gach-terrazzo', 'gach-porcelain', 'gach-60x60'], 'gach-terrazzo'],
    'DRV-SV40'   => [['gach', 'gach-lat-nen', 'gach-san-vuon', 'gach-ngoai-troi', 'gach-van-da', 'gach-porcelain'], 'gach-san-vuon'],
    'DRV-TH7530' => [['gach', 'gach-op-tuong', 'gach-trang-tri', 'gach-the', 'gach-phong-bep', 'gach-nha-tam'], 'gach-the'],
];
foreach ($expected as $sku => [$slugs, $primary]) {
    $id = wc_get_product_id_by_sku($sku);
    if ($id <= 0) {
        $ok("3.1 $sku", false, 'không tìm thấy SKU');
        continue;
    }
    $terms = wp_get_post_terms($id, 'product_cat', ['fields' => 'slugs']);
    $terms = is_array($terms) ? $terms : [];
    $managed = array_flip(array_keys((array) (require get_template_directory() . '/config/collections.php')));
    $have = array_values(array_filter($terms, static fn ($s) => isset($managed[$s]) || $s === 'gach'));
    $missing = array_diff($slugs, $have);
    $extra = array_diff($have, $slugs);
    $prim = (string) get_post_meta($id, 'rank_math_primary_product_cat', true);
    $primSlug = $prim !== '' ? (string) (get_term((int) $prim, 'product_cat')->slug ?? '') : '';
    $ok("3.1 $sku", $missing === [] && $extra === [] && $primSlug === $primary,
        'thiếu=[' . implode(',', $missing) . '] thừa=[' . implode(',', $extra) . '] primary=' . $primSlug . '/' . $primary);
}

// ---- §3.2: U-1, U-2, U-3 ----
$p = wc_get_product(wc_get_product_id_by_sku('DRV-8801M'));
if ($p instanceof WC_Product) {
    $ok('U-1 m2_per_box 1,92', abs((float) $p->get_meta('drv_m2_per_box') - 1.92) < 0.0001, (string) $p->get_meta('drv_m2_per_box'));
    $ok('U-1 _regular_price 246400', (int) round((float) $p->get_regular_price()) === 246400, (string) $p->get_regular_price());
}
$p2 = wc_get_product(wc_get_product_id_by_sku('DRV-TH7530'));
if ($p2 instanceof WC_Product) {
    $ok('U-2 giá/viên 10125 (7,5x30)', (int) round((float) $p2->get_regular_price()) === 10125, (string) $p2->get_regular_price());
}
foreach (['DRV-SLAB02', 'DRV-3612T'] as $sku) {
    $q = wc_get_product(wc_get_product_id_by_sku($sku));
    if ($q instanceof WC_Product) {
        $ok("U-11 $sku quote: không mua được, giá rỗng", !$q->is_purchasable() && (string) $q->get_regular_price() === '', 'purchasable=' . var_export($q->is_purchasable(), true));
        $band = wp_get_post_terms($q->get_id(), 'drv_price_band', ['fields' => 'slugs']);
        $ok("U-11 $sku price_band gia-du-an", is_array($band) && in_array('gia-du-an', $band, true), is_array($band) ? implode(',', $band) : '');
    }
}

// ---- §3.7 C-15: slug trạng thái ----
foreach (wc_get_order_statuses() as $slug => $label) {
    if (strlen($slug) > 20) {
        $ok("C-15 $slug", false, strlen($slug) . ' ký tự');
    }
}
$ok('C-15 wc-cho-xac-nhan đã đăng ký, 15 ký tự', isset(wc_get_order_statuses()['wc-cho-xac-nhan']) && strlen('wc-cho-xac-nhan') === 15);
$ok('HPOS bật', \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled());

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAIL\n";
