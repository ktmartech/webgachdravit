<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Catalog\MasterListingParser;
use Dravit\Catalog\ProductViewData;
use PHPUnit\Framework\TestCase;

/**
 * Step 7 · Sửa hiển thị PDP: nhãn kệ showroom, tên SP không lặp kích thước, thông số không lặp đơn vị "cm".
 */
final class PdpDisplayFixesTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\stubs([
            'wp_parse_url' => static fn (string $url, int $component = -1): mixed => parse_url($url, $component),
            'home_url'     => static fn (string $path = ''): string => 'https://gachdravit.vn' . ($path !== '' && $path !== '/' ? '/' . ltrim($path, '/') : ''),
            'esc_url'      => static fn (string $u): string => htmlspecialchars($u, ENT_QUOTES),
            'esc_html'     => static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES),
        ]);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_shelf_label_distinguishes_real_shelf_code_from_generic_showroom_flag(): void {
        self::assertSame('Kệ A-08', drv_shelf_label('A-08'));
        self::assertSame('Kệ B-04', drv_shelf_label(' B-04 '));
        self::assertSame('Showroom', drv_shelf_label('Showroom'));
        self::assertSame('Showroom', drv_shelf_label('Có'));
        self::assertSame('', drv_shelf_label(''));
    }

    public function test_title_parts_skip_size_and_surface_already_in_pattern_name(): void {
        $title = MasterListingParser::joinTitleParts([
            'Gạch lát nền', 'Dravit', 'Vân xi măng', 'Xám Xi Măng Mờ 80x80', '80x80', 'Mờ', 'VA8080M-17',
        ]);
        self::assertSame('Gạch lát nền Dravit Vân xi măng Xám Xi Măng Mờ 80x80 VA8080M-17', $title);
    }

    public function test_title_parts_keep_distinct_parts_and_match_whole_words_only(): void {
        $title = MasterListingParser::joinTitleParts([
            'Đá nung kết', 'Dravit', 'Vân đá Marble', 'Onyx Ngọc Trai Khổ Lớn', '80x160', 'Bóng', '', 'VA8160B-08',
        ]);
        self::assertSame('Đá nung kết Dravit Vân đá Marble Onyx Ngọc Trai Khổ Lớn 80x160 Bóng VA8160B-08', $title);

        // "Mờ" không bị coi là trùng với "Mờ Matt" vì phải khớp nguyên cụm từ trong chuỗi trước đó.
        self::assertSame('Gạch 60×60 Mờ', MasterListingParser::joinTitleParts(['Gạch 60×60', '60x60', 'Mờ']));
    }

    public function test_local_svg_placeholder_size_follows_viewbox_ratio(): void {
        // viewBox 800×600 → 1600×1200 (trước đây khai báo 1200×1200 làm PhotoSwipe thu nhỏ ảnh)
        self::assertSame([1600, 1200], ProductViewData::localSvgSize(DRV_URI . '/assets/images/placeholders/showroom-surface-board.svg'));
        self::assertSame([1600, 1600], ProductViewData::localSvgSize(DRV_URI . '/assets/images/placeholders/detail-grout-line.svg'));
        self::assertNull(ProductViewData::localSvgSize(DRV_URI . '/assets/images/placeholders/khong-ton-tai.svg'));
        self::assertNull(ProductViewData::localSvgSize('https://cdn.example.com/a.svg'));
        self::assertNull(ProductViewData::localSvgSize(DRV_URI . '/assets/images/logo.png'));
    }

    public function test_spec_size_row_does_not_duplicate_cm_unit(): void {
        $rows = ProductViewData::buildSpecRows([
            'kich_thuoc' => [['name' => '80x80 cm', 'slug' => '80x80']],
            'w_mm'       => 800,
            'h_mm'       => 800,
        ], ['attributes' => ['kich-thuoc' => ['static_map' => ['80x80' => 'gach-80x80']]]])['core'];

        $size = '';
        foreach ($rows as $r) {
            if ($r['label'] === 'Kích thước & Độ dày') {
                $size = strip_tags($r['value_html']);
            }
        }
        self::assertStringStartsWith('80x80 cm · 800 × 800 mm', $size);
        self::assertStringNotContainsString('cm cm', $size);
    }
}
