<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Dravit\Units\Calc;
use Dravit\Units\PriceState;
use PHPUnit\Framework\TestCase;

final class CalcParityTest extends TestCase {
    /** @var array<string, mixed> */
    private array $vectors = [];

    protected function setUp(): void {
        parent::setUp();
        $json          = (string) file_get_contents(DRV_DIR . '/tests/fixtures/calc-vectors.json');
        $decoded       = json_decode($json, true);
        $this->vectors = is_array($decoded) ? $decoded : [];
    }

    public function test_calc_from_m2_vectors(): void {
        $cases = $this->vectors['fromM2'] ?? [];
        self::assertNotEmpty($cases);

        foreach ($cases as $case) {
            $actual = Calc::calcFromM2($case['input']);
            foreach ($case['expected'] as $key => $expectedVal) {
                if (is_float($expectedVal)) {
                    self::assertEqualsWithDelta($expectedVal, $actual[$key], 1e-6, "{$case['id']} -> {$key}");
                } else {
                    self::assertEquals($expectedVal, $actual[$key], "{$case['id']} -> {$key}");
                }
            }
        }
    }

    public function test_calc_from_boxes_vectors(): void {
        foreach ($this->vectors['fromBoxes'] ?? [] as $case) {
            $actual = Calc::calcFromBoxes($case['input']);
            foreach ($case['expected'] as $key => $expectedVal) {
                if (is_float($expectedVal)) {
                    self::assertEqualsWithDelta($expectedVal, $actual[$key], 1e-6, "{$case['id']} -> {$key}");
                } else {
                    self::assertEquals($expectedVal, $actual[$key], "{$case['id']} -> {$key}");
                }
            }
        }
    }

    public function test_calc_from_pieces_and_cart_line_vectors(): void {
        foreach ($this->vectors['fromPieces'] ?? [] as $case) {
            $actual = Calc::calcFromPieces($case['input']);
            foreach ($case['expected'] as $key => $expectedVal) {
                if (is_float($expectedVal)) {
                    self::assertEqualsWithDelta($expectedVal, $actual[$key], 1e-6, "{$case['id']} -> {$key}");
                } else {
                    self::assertEquals($expectedVal, $actual[$key], "{$case['id']} -> {$key}");
                }
            }
        }

        foreach ($this->vectors['cartLine'] ?? [] as $case) {
            $actual = Calc::calcCartLine($case['input']);
            foreach ($case['expected'] as $key => $expectedVal) {
                if (is_float($expectedVal)) {
                    self::assertEqualsWithDelta($expectedVal, $actual[$key], 1e-6, "{$case['id']} -> {$key}");
                } else {
                    self::assertEquals($expectedVal, $actual[$key], "{$case['id']} -> {$key}");
                }
            }
        }
    }

    public function test_boq_slot_volume_discount_vehicle_and_quick_add(): void {
        foreach ($this->vectors['boqSlot'] ?? [] as $case) {
            $actual = Calc::calcBoqSlot($case['input']);
            foreach ($case['expected'] as $key => $expectedVal) {
                if (is_float($expectedVal)) {
                    self::assertEqualsWithDelta($expectedVal, $actual[$key], 1e-6);
                } else {
                    self::assertEquals($expectedVal, $actual[$key]);
                }
            }
        }

        foreach ($this->vectors['volumeDiscount'] ?? [] as $v) {
            $disc = Calc::calcVolumeDiscount((int) $v['subtotal'], (float) $v['m2']);
            self::assertEqualsWithDelta((float) $v['rate'], $disc['rate'], 1e-6);
            self::assertSame($v['discountVnd'], $disc['discountVnd']);
            self::assertSame($v['grandVnd'], $disc['grandVnd']);
        }

        foreach ($this->vectors['vehicleHint'] ?? [] as $vh) {
            self::assertSame($vh['expected'], Calc::resolveVehicleHint((float) $vh['kg']));
        }

        foreach ($this->vectors['quickAdd'] ?? [] as $qa) {
            self::assertSame($qa['expected'], Calc::quickAddPieces($qa['input']));
        }
    }

    public function test_d24_normalize_buy_unit_config_invariants(): void {
        // Bỏ tích `vien` -> ép `round_mode = 'box'` và `default_buy_unit` lùi về đơn vị đầu tiên hợp lệ.
        $cfg1 = PriceState::normalizeBuyUnitConfig(['m2', 'thung'], 'vien', 'piece', 3, 4);
        self::assertSame(['m2', 'thung'], $cfg1['buy_units']);
        self::assertSame('m2', $cfg1['default_buy_unit']);
        self::assertSame('box', $cfg1['round_mode']);
        self::assertSame(6, $cfg1['qty_min']);
        self::assertSame(3, $cfg1['qty_step']);

        // Tích `vien` + `round_mode = piece` -> giữ nguyên lẻ viên `qty_step = 1`.
        $cfg2 = PriceState::normalizeBuyUnitConfig(['vien', 'm2'], 'vien', 'piece', 44, 10);
        self::assertSame(['m2', 'vien'], $cfg2['buy_units']);
        self::assertSame('vien', $cfg2['default_buy_unit']);
        self::assertSame('piece', $cfg2['round_mode']);
        self::assertSame(10, $cfg2['qty_min']);
        self::assertSame(1, $cfg2['qty_step']);

        // Rỗng `buy_units` -> tự phục hồi về `['m2', 'thung']`.
        $cfg3 = PriceState::normalizeBuyUnitConfig([], 'm2', 'box', 8, 0);
        self::assertSame(['m2', 'thung'], $cfg3['buy_units']);
        self::assertSame(8, $cfg3['qty_min']);
    }
}
