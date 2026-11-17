<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Dravit\Woo\CartPresenter;
use Dravit\Woo\OrderSnapshot;
use PHPUnit\Framework\TestCase;

/**
 * Step 9B · U-8 (docs/14 §7.3): snapshot order item đóng băng thông số lúc đặt hàng —
 * sửa giá / quy cách sản phẩm sau đó không làm lệch số liệu đơn cũ.
 */
final class OrderSnapshotImmutabilityTest extends TestCase {
    private const SNAP = [
        'model'          => 'area_pack',
        'price_state'    => 'priced',
        'price_m2'       => 385000,
        'price_piece'    => 246400,
        'unit_label'     => 'viên',
        'area_piece'     => 0.64,
        'pieces_per_box' => 3,
        'm2_per_box'     => 1.92,
        'kg_per_box'     => 42.0,
        'round_mode'     => 'box',
        'qty_min'        => 3,
        'qty_step'       => 3,
    ];

    public function test_item_meta_contains_full_snapshot_keys(): void {
        $meta = OrderSnapshot::itemMeta(CartPresenter::line(self::SNAP, 18, ['drv_m2_input' => 11.0, 'drv_waste_pct' => 5]), 18);

        self::assertSame('area_pack', $meta['_drv_model']);
        self::assertSame('viên', $meta['_drv_unit']);
        self::assertSame(385000, $meta['_drv_price_m2']);
        self::assertSame(0.64, $meta['_drv_area_piece']);
        self::assertSame(3, $meta['_drv_pieces_per_box']);
        self::assertSame(42.0, $meta['_drv_kg_per_box']);
        self::assertSame(11.52, $meta['_drv_m2_qty']);
        self::assertSame(6, $meta['_drv_boxes']);
        self::assertSame(0, $meta['_drv_loose_pieces']);
        self::assertSame(252.0, $meta['_drv_kg']);
        self::assertSame(11.0, $meta['_drv_m2_input']);
        self::assertSame(5, $meta['_drv_waste_pct']);
    }

    public function test_snapshot_is_not_affected_by_later_product_changes(): void {
        $at_order = OrderSnapshot::itemMeta(CartPresenter::line(self::SNAP, 18), 18);

        // Sau khi đặt: Admin tăng giá và đổi quy cách 4 viên/thùng.
        $changed                   = self::SNAP;
        $changed['price_m2']       = 420000;
        $changed['price_piece']    = 268800;
        $changed['pieces_per_box'] = 4;
        $changed['kg_per_box']     = 56.0;
        $now = OrderSnapshot::itemMeta(CartPresenter::line($changed, 18), 18);

        self::assertNotSame($at_order, $now);
        self::assertSame(385000, $at_order['_drv_price_m2']);
        self::assertSame(6, $at_order['_drv_boxes']);
        self::assertSame(0, $at_order['_drv_loose_pieces']);
        self::assertSame(4, $now['_drv_boxes']);
        self::assertSame(2, $now['_drv_loose_pieces']);
    }

    public function test_order_totals_sum_from_item_snapshots(): void {
        $totals = OrderSnapshot::orderTotals([
            ['_drv_m2_qty' => 11.52, '_drv_boxes' => 6, '_drv_kg' => 252.0],
            ['_drv_m2_qty' => 38.4, '_drv_boxes' => 20, '_drv_kg' => 840.0],
        ]);

        self::assertSame(49.92, $totals['_drv_total_m2']);
        self::assertSame(26, $totals['_drv_total_boxes']);
        self::assertSame(1092.0, $totals['_drv_total_kg']);
    }

    public function test_hidden_keys_cover_snapshot_but_keep_lot_code_editable(): void {
        $hidden = OrderSnapshot::hiddenKeys();
        self::assertContains('_drv_price_m2', $hidden);
        self::assertContains('_drv_qty_pieces', $hidden);
        self::assertCount(13, $hidden);
        self::assertNotContains('_drv_lot_code', $hidden);
        foreach ($hidden as $key) {
            self::assertStringStartsWith('_drv_', $key);
        }
    }

    public function test_display_rows_are_readable_vietnamese(): void {
        $meta = OrderSnapshot::itemMeta(CartPresenter::line(self::SNAP, 18, ['drv_m2_input' => 11.0, 'drv_waste_pct' => 5]), 18);

        $customer = OrderSnapshot::rowsFromMeta($meta, 18);
        self::assertSame(['Đơn giá / m²', 'Quy đổi'], array_column($customer, 'label'));
        self::assertSame('385.000đ/m²', $customer[0]['value']);
        self::assertSame('18 viên · 6 thùng · 11,52 m² · 252 kg', $customer[1]['value']);

        $admin = array_column(OrderSnapshot::rowsFromMeta($meta + ['_drv_lot_code' => 'V2-0611'], 18, true), 'value', 'label');
        self::assertSame('1 thùng = 3 viên · 1,92 m² · 42 kg · 0,64 m²/viên', $admin['Quy cách']);
        self::assertSame('11 m² + hao hụt 5%', $admin['Khách nhập']);
        self::assertSame('V2-0611', $admin['Mã lô xuất kho']);
        foreach (array_keys($admin) as $label) {
            self::assertStringNotContainsString('_drv', $label);
        }
    }
}
