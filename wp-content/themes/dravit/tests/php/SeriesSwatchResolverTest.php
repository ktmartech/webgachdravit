<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Catalog\SeriesSwatchResolver;
use PHPUnit\Framework\TestCase;

/**
 * Step 7 · D-26: Swatch `<a href>` SSR theo `drv_series` (màu · kích thước · bề mặt), URL không dấu `/` cuối.
 */
final class SeriesSwatchResolverTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\stubs([
            'wp_parse_url' => static fn (string $url, int $component = -1): mixed => parse_url($url, $component),
        ]);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private static function rec(int $id, string $color, string $size, float $area, string $surface): array {
        return [
            'id'            => $id,
            'url'           => 'https://gachdravit.vn/san-pham/sp-' . $id . '/',
            'color_key'     => $color,
            'color_label'   => ucfirst($color),
            'color_hex'     => '',
            'thumb_url'     => '',
            'size_key'      => $size,
            'size_label'    => str_replace('x', '×', $size),
            'size_area'     => $area,
            'surface_key'   => $surface,
            'surface_label' => ucfirst($surface),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function series(): array {
        return [
            self::rec(11, 'trang', '80x80', 0.64, 'bong'),
            self::rec(12, 'trang', '60x60', 0.36, 'bong'),
            self::rec(13, 'trang', '80x80', 0.64, 'mo'),
            self::rec(14, 'xam', '80x80', 0.64, 'bong'),
            self::rec(15, 'xam', '60x60', 0.36, 'mo'),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $axes
     * @return array<string, mixed>
     */
    private static function axis(array $axes, string $name): array {
        foreach ($axes as $a) {
            if ($a['axis'] === $name) {
                return $a;
            }
        }
        self::fail('Missing axis ' . $name);
    }

    public function test_builds_three_axes_with_single_active_item_each(): void {
        $axes = SeriesSwatchResolver::buildAxesFromRecords(11, self::series());

        self::assertSame(['color', 'size', 'surface'], array_column($axes, 'axis'));
        foreach ($axes as $a) {
            self::assertCount(1, array_filter($a['items'], static fn (array $i): bool => $i['active']));
        }
        self::assertSame('Trang', self::axis($axes, 'color')['current_value_label']);
    }

    public function test_sibling_pick_keeps_other_axes_and_urls_have_no_trailing_slash(): void {
        $axes = SeriesSwatchResolver::buildAxesFromRecords(11, self::series());

        // Đổi màu sang "xám" phải giữ nguyên 80x80 bóng => SP 14.
        self::assertSame('https://gachdravit.vn/san-pham/sp-14', self::axis($axes, 'color')['items'][1]['url']);

        // Đổi bề mặt sang "mờ" phải giữ màu trắng 80x80 => SP 13.
        self::assertSame('https://gachdravit.vn/san-pham/sp-13', self::axis($axes, 'surface')['items'][1]['url']);

        foreach ($axes as $a) {
            foreach ($a['items'] as $i) {
                self::assertStringEndsNotWith('/', $i['url']);
                self::assertStringNotContainsString('?', $i['url']);
            }
        }
    }

    public function test_size_axis_sorted_by_area_ascending(): void {
        $axes = SeriesSwatchResolver::buildAxesFromRecords(11, self::series());
        self::assertSame(['60×60', '80×80'], array_column(self::axis($axes, 'size')['items'], 'label'));
    }

    public function test_single_product_or_unknown_current_returns_no_axes(): void {
        self::assertSame([], SeriesSwatchResolver::buildAxesFromRecords(11, [self::rec(11, 'trang', '80x80', 0.64, 'bong')]));
        self::assertSame([], SeriesSwatchResolver::buildAxesFromRecords(999, self::series()));
    }

    public function test_axis_with_only_one_value_is_omitted(): void {
        $axes = SeriesSwatchResolver::buildAxesFromRecords(21, [
            self::rec(21, 'trang', '80x80', 0.64, 'bong'),
            self::rec(22, 'trang', '60x60', 0.36, 'bong'),
        ]);
        self::assertSame(['size'], array_column($axes, 'axis'));
    }
}
