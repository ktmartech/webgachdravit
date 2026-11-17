<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Units\Calc;
use Dravit\Units\PriceState;
use Dravit\Woo\CartHooks;
use Dravit\Woo\CartPresenter;
use PHPUnit\Framework\TestCase;

/**
 * Step 9A · docs/04 §4, docs/14 §7.1, docs/15 §3.2: dòng giỏ quy đổi đúng thùng/m²/kg theo số NGUYÊN viên,
 * bước nhảy theo `drv_round_mode`, cùng product_id gộp 1 dòng.
 */
final class CartQuantityStepTest extends TestCase {
    /** DRV-8801M: 80x80, 3 viên/thùng, 42 kg/thùng, 385.000đ/m² → 246.400đ/viên. */
    private const SNAP = [
        'model'            => 'area_pack',
        'price_state'      => 'priced',
        'price_m2'         => 385000,
        'regular_price_m2' => 385000,
        'price_piece'      => 246400,
        'unit_label'       => 'viên',
        'area_piece'       => 0.64,
        'pieces_per_box'   => 3,
        'm2_per_box'       => 1.92,
        'kg_per_box'       => 42.0,
        'buy_units'        => ['m2', 'thung'],
        'default_buy_unit' => 'm2',
        'round_mode'       => 'box',
        'qty_min'          => 3,
        'qty_step'         => 3,
        'max_stock'        => null,
        'shade'            => 'V2',
    ];

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\stubs([
            'wp_json_encode' => static fn (mixed $v): string => (string) json_encode($v),
        ]);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_box_mode_line_matches_store_api_contract(): void {
        $line = CartPresenter::line(self::SNAP, 18, ['drv_m2_input' => 11.0, 'drv_waste_pct' => 5, 'drv_input_unit' => 'm2']);

        self::assertSame(6, $line['boxes']);
        self::assertSame(0, $line['loose_pieces']);
        self::assertSame('6 thùng', $line['box_text']);
        self::assertSame(11.52, $line['m2_qty']);
        self::assertSame(252.0, $line['kg']);
        self::assertSame(246400, $line['price_piece']);
        self::assertSame(739200, $line['price_box']);
        self::assertSame(18 * 246400, $line['line_total']);
        self::assertSame(3, $line['qty_step']);
        self::assertSame(11.0, $line['m2_input']);
        self::assertSame(5, $line['waste_pct']);
    }

    public function test_piece_mode_reports_loose_pieces_and_pieces_needed_for_next_box(): void {
        $snap               = self::SNAP;
        $snap['round_mode'] = 'piece';
        $snap['qty_step']   = 1;
        $snap['qty_min']    = 1;
        $snap['buy_units']  = ['m2', 'thung', 'vien'];

        $line = CartPresenter::line($snap, 20);

        self::assertSame(6, $line['boxes']);
        self::assertSame(2, $line['loose_pieces']);
        self::assertSame(1, $line['needed_for_next_box']);
        self::assertSame('6 thùng + 2 viên lẻ', $line['box_text']);
        self::assertSame(1, $line['qty_step']);
    }

    public function test_round_mode_box_forces_step_equal_pieces_per_box(): void {
        $box = PriceState::normalizeBuyUnitConfig(['m2', 'thung'], 'm2', 'piece', 4, 0);
        self::assertSame('box', $box['round_mode'], 'Không có đơn vị "viên" thì không cho mua lẻ.');
        self::assertSame(4, $box['qty_step']);
        self::assertSame(4, $box['qty_min']);

        $piece = PriceState::normalizeBuyUnitConfig(['m2', 'thung', 'vien'], 'vien', 'piece', 4, 0);
        self::assertSame('piece', $piece['round_mode']);
        self::assertSame(1, $piece['qty_step']);
        self::assertSame(1, $piece['qty_min']);
    }

    public function test_summary_totals_and_vehicle_hint(): void {
        $a       = CartPresenter::line(self::SNAP, 18);
        $b       = CartPresenter::line(self::SNAP, 60);
        $a['qty'] = 18;
        $b['qty'] = 60;

        $sum = CartPresenter::summarize([$a, $b], Calc::DEFAULT_VEHICLE_THRESHOLDS, 'Báo sau khi xác nhận đơn');

        self::assertSame(2, $sum['line_count']);
        self::assertSame(78, $sum['total_pieces']);
        self::assertSame(26, $sum['total_boxes']);
        self::assertSame(49.92, $sum['total_m2']);
        self::assertSame(1092.0, $sum['total_kg']);
        self::assertSame('Xe tải 2,5 tấn', $sum['vehicle_hint']);
        self::assertFalse($sum['has_loose_items']);
        self::assertSame('Báo sau khi xác nhận đơn', $sum['shipping_notice']);
        self::assertSame('26 thùng', $sum['boxes_text']);
    }

    public function test_same_product_merges_into_one_cart_line_regardless_of_calc_context(): void {
        $first  = CartHooks::stableCartId(101, 0, [], ['drv_m2_input' => 28.0, 'drv_waste_pct' => 5, 'drv_input_unit' => 'm2']);
        $second = CartHooks::stableCartId(101, 0, [], ['drv_m2_input' => 11.5, 'drv_waste_pct' => 10, 'drv_input_unit' => 'thung']);
        $plain  = CartHooks::stableCartId(101, 0, [], []);

        self::assertSame($first, $second);
        self::assertSame($first, $plain);
        self::assertNotSame($first, CartHooks::stableCartId(102, 0, [], []));
        self::assertNotSame($first, CartHooks::stableCartId(101, 0, [], ['plugin_addon' => 'x']), 'Dữ liệu plugin khác vẫn tách dòng.');
    }
}
