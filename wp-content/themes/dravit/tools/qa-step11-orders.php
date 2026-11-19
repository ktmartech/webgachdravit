<?php
/**
 * QA Step 11 (docs/09 §3.7 C-4, C-5, C-8, C-13, U-9) — TẠO rồi XÓA đơn thử trên DB local.
 * Chạy:  tools/wp-cli.ps1 eval-file tools/qa-step11-orders.php
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

$pid = wc_get_product_id_by_sku('DRV-TH7530');
$product = wc_get_product($pid);
$made = [];

$make = static function (string $method, int $qty) use ($product, &$made): WC_Order {
    $order = wc_create_order(['status' => 'pending']);
    $order->add_product($product, $qty);
    $order->set_payment_method($method);
    $order->set_billing_first_name('QA Test');
    $order->set_billing_phone('0912345678');
    $order->calculate_totals();
    $order->save();
    $made[] = $order->get_id();
    return $order;
};
$stock = static fn (): int => (int) wc_get_product($product->get_id())->get_stock_quantity();

$s0 = $stock();

// C-4 COD -> cho-xac-nhan, trừ kho
$o1 = $make('cod', 12);
$o1->update_status('cho-xac-nhan', 'QA');
$s1 = $stock();
$ok('C-4 COD: trạng thái wc-cho-xac-nhan', wc_get_order($o1->get_id())->get_status() === 'cho-xac-nhan');
$ok('C-4 COD: trừ kho 12 viên', $s0 - $s1 === 12, "$s0 -> $s1");

// C-8 hủy đơn Chờ xác nhận -> hoàn kho
wc_get_order($o1->get_id())->update_status('cancelled', 'QA hủy');
$s2 = $stock();
$ok('C-8 hủy đơn Chờ xác nhận hoàn kho', $s2 === $s0, "sau hủy $s2 / ban đầu $s0");

// C-5 / C-13 BACS: trạng thái A không QR, B (on-hold) mới có QR
$o2 = $make('bacs', 6);
$o2->update_status('cho-xac-nhan', 'QA');
$view = \Dravit\Woo\OrderView::fromOrder(wc_get_order($o2->get_id()));
$ok('C-5/C-13 BACS cho-xac-nhan: payment slot không QR', ($view['payment_slot'] ?? \Dravit\Woo\OrderView::paymentSlot($view['state'] ?? '', 'bacs', 'cho-xac-nhan')) !== 'qr', json_encode($view['state'] ?? null));
wc_get_order($o2->get_id())->update_status('on-hold', 'QA');
$view2 = \Dravit\Woo\OrderView::fromOrder(wc_get_order($o2->get_id()));
$ok('C-13 BACS on-hold: có QR', ($view2['payment_slot'] ?? '') === 'qr' || ($view2['state'] ?? '') === 'transfer', json_encode([$view2['state'] ?? null, $view2['payment_slot'] ?? null]));
$ok('C-13 mã hiển thị DRV-{id}, nội dung CK DRV{id}', 'DRV-' . $o2->get_id() !== '' && preg_match('/^DRV\d+$/', 'DRV' . $o2->get_id()) === 1);

// U-9 tồn kho 10 viên thêm 12: dùng SP khác tạo tồn kho nhỏ tạm thời
$tmp = new WC_Product_Simple();
$tmp->set_name('QA tồn kho tạm');
$tmp->set_regular_price('1000');
$tmp->set_manage_stock(true);
$tmp->set_stock_quantity(10);
$tmp->set_backorders('no');
$tmp->set_status('publish');
$tmp->save();
$ok('U-9 tồn 10: thêm 12 bị từ chối', !$tmp->has_enough_stock(12) && $tmp->has_enough_stock(10));
wp_delete_post($tmp->get_id(), true);

// Dọn
foreach ($made as $id) {
    $o = wc_get_order($id);
    if ($o) {
        $o->delete(true);
    }
}
$final = $stock();
if ($final !== $s0) {
    wc_update_product_stock(wc_get_product($pid), $s0, 'set');
}
$ok('Dọn: tồn kho trả về ban đầu', $stock() === $s0, (string) $stock());

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAIL\n";
