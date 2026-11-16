<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Dravit\Leads\LeadAdmin;
use Dravit\Leads\LeadRepository;
use Dravit\Quote\OrderConverter;
use PHPUnit\Framework\TestCase;

/**
 * Step 8 · Q-10: chuyển phiếu BOQ thành đơn HPOS – bắt buộc giá chốt cho mẫu `quote_only`,
 * line item giữ giá niêm yết ở `subtotal` và giá chốt ở `total`, trạng thái khởi tạo đúng luồng D-22.
 */
final class OrderConverterTest extends TestCase {
    /** @var array<string, mixed> */
    private const BOQ = [
        'waste_pct' => 5,
        'rooms'     => [
            ['name' => 'Phòng khách', 'slots' => [
                ['role' => 'Lát nền chính', 'product_id' => 101, 'pieces' => 69, 'target_m2' => 42.0, 'price_m2' => 385000],
            ]],
            ['name' => 'Sảnh', 'slots' => [
                ['role' => 'Ốp tường', 'product_id' => 105, 'pieces' => 4, 'target_m2' => 11.52, 'price_m2' => 0],
            ]],
        ],
    ];

    private static function loader(): callable {
        $products = [
            101 => ['sku' => 'DRV-8801M', 'price_m2' => 385000, 'area_piece' => 0.64, 'pieces_per_box' => 3, 'kg_per_box' => 42.0, 'unit_label' => 'viên'],
            105 => ['sku' => 'DRV-SLAB01', 'price_m2' => 0, 'area_piece' => 2.88, 'pieces_per_box' => 1, 'kg_per_box' => 68.0, 'unit_label' => 'tấm'],
        ];
        return static fn (int $id): ?array => $products[$id] ?? null;
    }

    public function test_quote_only_line_requires_agreed_price(): void {
        $result = OrderConverter::prepareLines(self::BOQ, ['0:0' => '365000'], self::loader());
        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('drv_missing_agreed_price', $result->get_error_code());
        self::assertStringContainsString('DRV-SLAB01', $result->get_error_message());
    }

    public function test_lines_keep_list_price_as_subtotal_and_agreed_price_as_total(): void {
        $lines = OrderConverter::prepareLines(self::BOQ, ['0:0' => '365.750', '1:0' => '1.200.000'], self::loader());
        self::assertIsArray($lines);
        self::assertCount(2, $lines);

        [$tile, $slab] = $lines;
        self::assertSame(69, $tile['pieces']);
        self::assertSame(365750, $tile['agreed_price_m2']);
        self::assertSame((int) round(385000 * 0.64) * 69, $tile['line_subtotal'], 'Subtotal = giá niêm yết (giữ dấu vết chiết khấu)');
        self::assertSame((int) round(365750 * 0.64) * 69, $tile['line_total']);
        self::assertSame(23, $tile['boxes']);
        self::assertSame(0, $tile['loose']);
        self::assertSame(966.0, $tile['kg']);
        self::assertSame('Phòng khách', $tile['room_name']);

        // quote_only: niêm yết 0 -> subtotal lấy chính giá chốt (không âm, không 0đ).
        self::assertSame((int) round(1200000 * 2.88) * 4, $slab['line_subtotal']);
        self::assertSame($slab['line_subtotal'], $slab['line_total']);
        self::assertSame('tấm', $slab['unit_label']);
    }

    public function test_default_agreed_price_falls_back_to_snapshot_and_missing_product_errors(): void {
        $only = ['waste_pct' => 5, 'rooms' => [self::BOQ['rooms'][0]]];
        $lines = OrderConverter::prepareLines($only, [], self::loader());
        self::assertIsArray($lines);
        self::assertSame(385000, $lines[0]['agreed_price_m2']);

        $gone = ['rooms' => [['name' => 'A', 'slots' => [['product_id' => 999, 'pieces' => 3]]]]];
        $err  = OrderConverter::prepareLines($gone, [], self::loader());
        self::assertInstanceOf(\WP_Error::class, $err);
        self::assertSame('drv_invalid_product', $err->get_error_code());

        self::assertInstanceOf(\WP_Error::class, OrderConverter::prepareLines([], [], self::loader()));
    }

    public function test_target_status_follows_hpos_flow(): void {
        $registered = ['wc-pending', 'wc-cho-xac-nhan', 'wc-on-hold', 'wc-processing'];
        self::assertSame('cho-xac-nhan', OrderConverter::resolveTargetStatus(null, 'bacs', $registered));
        self::assertSame('on-hold', OrderConverter::resolveTargetStatus(350000, 'bacs', $registered));
        self::assertSame('processing', OrderConverter::resolveTargetStatus(0, 'cod', $registered));
        self::assertSame('pending', OrderConverter::resolveTargetStatus(null, 'bacs', ['wc-pending']), 'Fallback khi Step 9 chưa đăng ký trạng thái');
        self::assertLessThanOrEqual(20, strlen('wc-cho-xac-nhan'), 'D-22: slug trạng thái ≤ 20 ký tự');
    }

    public function test_lead_code_format_and_csv_injection_guard(): void {
        $now  = gmmktime(3, 0, 0, 11, 11, 2026);
        $code = LeadRepository::generateCode('boq', $now);
        self::assertMatchesRegularExpression('/^BG-20261111-\d{4}$/', $code);
        self::assertMatchesRegularExpression('/^LD-20261111-\d{4}$/', LeadRepository::generateCode('contact', $now));

        $seen = [];
        $code = LeadRepository::generateCode('boq', $now, static function (string $c) use (&$seen): bool {
            $seen[] = $c;
            return count($seen) < 3;
        });
        self::assertCount(3, $seen, 'Sinh lại khi trùng mã');

        self::assertSame("\"'=CMD|' /C calc'!A0\"", LeadAdmin::csvCell("=CMD|' /C calc'!A0"));
        self::assertSame('"Phòng ""khách"" Tầng 1"', LeadAdmin::csvCell("Phòng \"khách\"\nTầng 1"));
    }
}
