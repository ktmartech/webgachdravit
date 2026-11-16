<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Catalog\ProductViewData;
use PHPUnit\Framework\TestCase;

/**
 * Step 7 · IL-03: Bảng thông số PDP gắn link Silo tĩnh (0 `?param`, không `/` cuối);
 * giá trị không có trang tĩnh render text thuần.
 */
final class SpecInternalLinkTest extends TestCase {
    private const FACETS = [
        'attributes' => [
            'kich-thuoc' => ['static_map' => ['80x80' => 'gach-80x80', '60x60' => 'gach-60x60']],
            'cong-nang'  => ['static_map' => ['lat-nen' => 'gach-lat-nen']],
        ],
    ];

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

    /**
     * @return array<string, mixed>
     */
    private static function input(): array {
        return [
            'cong_nang'  => [['name' => 'Lát nền', 'slug' => 'lat-nen']],
            'kich_thuoc' => [['name' => '80x80', 'slug' => '80x80']],
            'w_mm'       => 800,
            'h_mm'       => 800,
            'thick_mm'   => 9.5,
            'chat_lieu'  => [['name' => 'Porcelain', 'slug' => 'porcelain']],
            'van_gach'   => [['name' => 'Vân đá Marble', 'slug' => 'van-da-marble']],
            'faces'      => 6,
            'be_mat'     => [['name' => 'Bóng kính', 'slug' => 'bong-kinh']],
            'dcof'       => [['name' => 'R10', 'slug' => 'r10']],
            'bo_suu_tap' => [['name' => 'Calacatta', 'slug' => 'calacatta']],
            'xuat_xu'    => [['name' => 'Ấn Độ', 'slug' => 'an-do']],
            'ppb'        => 3,
            'm2_box'     => 1.92,
            'kg_box'     => 42.5,
            'shade'      => 'v2',
        ];
    }

    /**
     * @param array<int, array{label: string, value_html: string}> $rows
     */
    private static function rowHtml(array $rows, string $label): string {
        foreach ($rows as $r) {
            if ($r['label'] === $label) {
                return $r['value_html'];
            }
        }
        self::fail('Missing spec row ' . $label);
    }

    public function test_core_rows_link_to_static_silo_urls(): void {
        $rows = ProductViewData::buildSpecRows(self::input(), self::FACETS)['core'];

        self::assertStringContainsString('href="https://gachdravit.vn/gach-lat-nen"', self::rowHtml($rows, 'Công năng ốp lát'));
        self::assertStringContainsString('href="https://gachdravit.vn/gach-80x80"', self::rowHtml($rows, 'Kích thước & Độ dày'));
        self::assertStringContainsString('href="https://gachdravit.vn/gach-porcelain"', self::rowHtml($rows, 'Chất liệu xương gạch'));
        self::assertStringContainsString('href="https://gachdravit.vn/gach-van-da"', self::rowHtml($rows, 'Hệ vân & Mặt vân'));

        $bst = self::rowHtml($rows, 'Bộ sưu tập & Xuất xứ');
        self::assertStringContainsString('href="https://gachdravit.vn/bo-suu-tap/calacatta"', $bst);
        self::assertStringContainsString('href="https://gachdravit.vn/xuat-xu/an-do"', $bst);
    }

    public function test_no_link_contains_query_or_trailing_slash(): void {
        $all  = ProductViewData::buildSpecRows(self::input(), self::FACETS);
        $html = implode("\n", array_column(array_merge($all['core'], $all['extended']), 'value_html'));

        preg_match_all('/href="([^"]+)"/', $html, $m);
        self::assertNotEmpty($m[1]);
        foreach ($m[1] as $href) {
            self::assertStringNotContainsString('?', $href);
            self::assertStringEndsNotWith('/', $href);
        }
    }

    public function test_values_without_static_page_render_as_plain_text(): void {
        $surf = self::rowHtml(ProductViewData::buildSpecRows(self::input(), self::FACETS)['core'], 'Bề mặt & Ma sát');
        self::assertStringNotContainsString('<a ', $surf);
        self::assertStringContainsString('Chống trượt R10', $surf);
    }

    public function test_pack_row_shows_shade_and_never_lot_code(): void {
        $pack = self::rowHtml(ProductViewData::buildSpecRows(self::input(), self::FACETS)['core'], 'Quy cách đóng gói');
        self::assertSame('3 viên/thùng · 1,92 m² · 42,5 kg · Biến thiên vân V2', html_entity_decode($pack));
    }
}
