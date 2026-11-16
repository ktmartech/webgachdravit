<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Dravit\Quote\BoqService;
use Dravit\Units\Calc;
use PHPUnit\Framework\TestCase;

/**
 * Step 8 · Q-3 / Q-4 / Q-7: Server tính lại 100% phiếu BOQ từ dữ liệu SP thật, bỏ qua giá client gửi lên.
 */
final class BoqServiceTest extends TestCase {
    /** @var array<int, array<string, mixed>> */
    private const PRODUCTS = [
        101 => [
            'id' => 101, 'sku' => 'DRV-8801M', 'name' => 'Marble Calacatta 80x80', 'price_state' => 'priced',
            'price_m2' => 385000, 'price_piece' => 246400, 'area_piece' => 0.64, 'pieces_per_box' => 3,
            'kg_per_box' => 42.0, 'unit_label' => 'viên', 'round_mode' => 'box', 'qty_min' => 3, 'size_label' => '80×80 cm', 'stock_qty' => 600,
        ],
        105 => [
            'id' => 105, 'sku' => 'DRV-SLAB01', 'name' => 'Big Slab Onyx', 'price_state' => 'quote_only',
            'price_m2' => 0, 'price_piece' => 0, 'area_piece' => 2.88, 'pieces_per_box' => 1,
            'kg_per_box' => 68.0, 'unit_label' => 'tấm', 'round_mode' => 'piece', 'qty_min' => 1, 'size_label' => '120×240 cm', 'stock_qty' => null,
        ],
    ];

    private function service(): BoqService {
        return new BoqService(static fn (int $id): ?array => self::PRODUCTS[$id] ?? null);
    }

    public function test_normalize_accepts_both_payload_shapes_and_drops_client_prices(): void {
        $input = BoqService::normalizeInput([
            'waste_pct'    => 5,
            'project_name' => 'Biệt thự <b>Long Thành</b>',
            'rooms'        => [[
                'name'  => 'Phòng khách',
                'slots' => [
                    ['role' => 'Lát nền chính', 'product_id' => 101, 'target_m2' => 42, 'price_m2' => 1000, 'line_total' => 1],
                    ['role' => 'Vai trò lạ', 'product_id' => 105, 'input_mode' => 'vien', 'pieces' => 4],
                    ['product_id' => 0, 'target_m2' => 9],
                    ['product_id' => 101, 'target_m2' => 0],
                ],
            ]],
        ], [0, 5, 10], ['Lát nền chính', 'Ốp tường']);

        self::assertSame([], $input['errors']);
        self::assertSame(2, $input['item_count']);
        self::assertSame('Lát nền chính', $input['rooms'][0]['slots'][1]['role'], 'Vai trò ngoài từ điển rơi về hạng mục mặc định');
        self::assertArrayNotHasKey('price_m2', $input['rooms'][0]['slots'][0]);
        self::assertStringNotContainsString('<b>', $input['project']);
    }

    public function test_rejects_empty_boq_invalid_waste_and_over_limit(): void {
        $empty = BoqService::normalizeInput(['waste_pct' => 5, 'rooms' => []]);
        self::assertArrayHasKey('rooms', $empty['errors']);

        $waste = BoqService::normalizeInput(['waste_pct' => 7, 'rooms' => [['name' => 'A', 'slots' => [['product_id' => 101, 'target_m2' => 5]]]]]);
        self::assertArrayHasKey('waste_pct', $waste['errors']);

        $slots = [];
        for ($i = 1; $i <= 31; $i++) {
            $slots[] = ['product_id' => $i, 'target_m2' => 1];
        }
        $over = BoqService::normalizeInput(['waste_pct' => 5, 'rooms' => [['name' => 'A', 'slots' => $slots]]], [0, 5, 10], [], 30);
        self::assertArrayHasKey('rooms', $over['errors']);
    }

    public function test_server_recompute_mixed_priced_and_quote_only(): void {
        $input  = BoqService::normalizeInput([
            'waste_pct' => 5,
            'rooms'     => [[
                'name'  => 'Phòng khách',
                'slots' => [
                    ['role' => 'Lát nền chính', 'product_id' => 101, 'input_mode' => 'm2', 'target_m2' => 42],
                    ['role' => 'Lát nền chính', 'product_id' => 105, 'input_mode' => 'vien', 'pieces' => 4],
                ],
            ]],
        ]);
        $result = $this->service()->compute($input['rooms'], $input['waste_pct'], [['min_m2' => 30, 'discount_pct' => 5], ['min_m2' => 100, 'discount_pct' => 8]]);
        $slots  = $result['rooms'][0]['slots'];
        $totals = $result['totals'];

        // 42 m² × 1,05 = 44,1 m² -> 23 thùng = 69 viên = 44,16 m², 69 × 246.400đ.
        self::assertSame(69, $slots[0]['pieces']);
        self::assertSame(23, $slots[0]['boxes']);
        self::assertSame(44.16, $slots[0]['actual_m2']);
        self::assertSame(69 * 246400, $slots[0]['line_total']);

        // Mẫu quote_only: vẫn có m²/kg nhưng thành tiền 0, gắn cờ is_quote.
        self::assertTrue($slots[1]['is_quote']);
        self::assertSame(0, $slots[1]['line_total']);
        self::assertSame(11.52, $slots[1]['actual_m2']);

        self::assertSame(55.68, $totals['total_m2']);
        self::assertSame(69 * 246400, $totals['subtotal_vnd']);
        self::assertSame(5.0, $totals['discount_rate']);
        self::assertSame((int) round(69 * 246400 * 0.05), $totals['discount_est_vnd']);
        self::assertTrue($totals['has_quote_items']);
        self::assertSame(1, $totals['priced_count']);
        self::assertSame(1, $totals['quote_count']);
        self::assertSame(round(69 * 14 + 4 * 68, 1), $totals['total_kg']);
    }

    public function test_quote_only_board_has_no_discount_and_missing_product_is_reported(): void {
        $input  = BoqService::normalizeInput(['waste_pct' => 10, 'rooms' => [['name' => 'Sảnh', 'slots' => [
            ['product_id' => 105, 'input_mode' => 'm2', 'target_m2' => 120],
            ['product_id' => 999, 'input_mode' => 'm2', 'target_m2' => 5],
        ]]]]);
        $result = $this->service()->compute($input['rooms'], $input['waste_pct']);

        self::assertSame(0, $result['totals']['subtotal_vnd']);
        self::assertSame(0.0, $result['totals']['discount_rate'], 'Không hiện chiết khấu khi chưa có mẫu có giá');
        self::assertArrayHasKey('rooms', $result['errors']);
        self::assertStringContainsString('#999', $result['errors']['rooms']);
    }

    public function test_compute_slot_parity_with_input_modes(): void {
        $p = ['areaPiece' => 0.64, 'piecesPerBox' => 3, 'kgPerBox' => 42, 'priceM2' => 385000, 'roundMode' => 'box', 'qtyMin' => 3];
        self::assertSame(30, Calc::computeSlot($p, ['inputMode' => 'thung', 'pieces' => 30], 10)['pieces'], 'Giữ số thùng, bỏ qua hao hụt');
        self::assertSame(9, Calc::computeSlot($p, ['inputMode' => 'vien', 'pieces' => 7], 5)['pieces'], 'Viên lẻ làm tròn lên bội số thùng');
        self::assertSame(0, Calc::computeSlot($p, ['inputMode' => 'm2', 'targetM2' => 0], 5)['pieces']);
        self::assertSame('80×80 cm', BoqService::sizeLabel(800, 800));
        self::assertSame('7,5×30 cm', BoqService::sizeLabel(75, 300));
    }
}
