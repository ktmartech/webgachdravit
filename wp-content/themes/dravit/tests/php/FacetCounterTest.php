<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Core\Settings;
use Dravit\Filters\FacetCounter;
use Dravit\Filters\Renderer;
use Dravit\Filters\UrlState;
use PHPUnit\Framework\TestCase;

final class FacetCounterTest extends TestCase {
    /** @var array<int, int> */
    private array $baseIds = [101, 102, 103, 104];

    /** @var array<int, array<string, array<int, string>>> */
    private array $termMap = [
        101 => [
            'pa_kich-thuoc' => ['80x80'],
            'pa_be-mat'     => ['bong'],
            'pa_dcof'       => ['r9'],
            'drv_flag'      => ['showroom'],
        ],
        102 => [
            'pa_kich-thuoc' => ['80x80'],
            'pa_be-mat'     => ['mo'],
            'pa_dcof'       => ['r10'],
            'drv_flag'      => [],
        ],
        103 => [
            'pa_kich-thuoc' => ['60x60'],
            'pa_be-mat'     => ['mo'],
            'pa_dcof'       => ['r10'],
            'drv_flag'      => ['showroom'],
        ],
        104 => [
            'pa_kich-thuoc' => ['60x120'],
            'pa_be-mat'     => ['nham'],
            'pa_dcof'       => ['r11'],
            'drv_flag'      => [],
        ],
    ];

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Settings::flush_cache();

        Functions\stubs([
            'wp_parse_url' => static fn (string $url, int $component = -1): mixed => parse_url($url, $component),
            'get_option'   => static fn (): array => [],
        ]);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_f9_except_self_counts_preserve_sibling_options_and_cross_filter_other_groups(): void {
        // 1. Khi chỉ lọc kich-thuoc=80x80:
        //    - Nhóm kich-thuoc bỏ tự lọc -> vẫn thấy đủ 80x80 (2), 60x60 (1), 60x120 (1)
        //    - Các nhóm khác bị thu hẹp theo 80x80 -> be-mat: bong (1), mo (1), nham (0); dcof: r9 (1), r10 (1), r11 (0)
        $counts_single = FacetCounter::computeExceptSelfCounts(
            $this->baseIds,
            $this->termMap,
            ['kich-thuoc' => ['80x80']]
        );

        self::assertSame(2, $counts_single['kich-thuoc']['80x80']);
        self::assertSame(1, $counts_single['kich-thuoc']['60x60']);
        self::assertSame(1, $counts_single['kich-thuoc']['60x120']);

        self::assertSame(1, $counts_single['be-mat']['bong']);
        self::assertSame(1, $counts_single['be-mat']['mo']);
        self::assertSame(0, $counts_single['be-mat']['nham']);
        self::assertSame(0, $counts_single['dcof']['r11']);
        self::assertSame(1, $counts_single['showroom']['showroom']);

        // 2. Khi lọc đồng thời kich-thuoc=80x80 VÀ be-mat=mo:
        //    - Tổng sản phẩm khớp cả hai = 1 (PID 102)
        //    - Nhóm kich-thuoc phản ánh be-mat=mo -> 80x80 (1), 60x60 (1), 60x120 (0)
        //    - Nhóm be-mat phản ánh kich-thuoc=80x80 -> bong (1), mo (1), nham (0)
        $active_both = [
            'kich-thuoc' => ['80x80'],
            'be-mat'     => ['mo'],
        ];
        $counts_both = FacetCounter::computeExceptSelfCounts($this->baseIds, $this->termMap, $active_both);

        self::assertSame(1, FacetCounter::countMatchingProducts($this->baseIds, $this->termMap, $active_both));
        self::assertSame(1, $counts_both['kich-thuoc']['80x80']);
        self::assertSame(1, $counts_both['kich-thuoc']['60x60']);
        self::assertSame(0, $counts_both['kich-thuoc']['60x120']);
        self::assertSame(1, $counts_both['be-mat']['bong']);
        self::assertSame(1, $counts_both['be-mat']['mo']);
        self::assertSame(0, $counts_both['be-mat']['nham']);
    }

    public function test_renderer_marks_zero_count_options_disabled_without_removing_base_options(): void {
        $base_counts   = FacetCounter::computeExceptSelfCounts($this->baseIds, $this->termMap, []);
        $filtered_st   = UrlState::fromArray('product_cat:gach-phong-khach', ['kich-thuoc' => '80x80'], 1, 24, 'https://gachdravit.vn');
        $active_counts = FacetCounter::computeExceptSelfCounts($this->baseIds, $this->termMap, $filtered_st->facets());

        $view_data = Renderer::buildFilterViewData($filtered_st, $active_counts, $base_counts);

        $surface_facet = null;
        foreach ($view_data['facets'] as $f) {
            if ($f['param'] === 'be-mat') {
                $surface_facet = $f;
                break;
            }
        }

        self::assertNotNull($surface_facet);
        $opts_by_slug = [];
        foreach ($surface_facet['options'] as $opt) {
            $opts_by_slug[$opt['slug']] = $opt;
        }

        // 'nham' có trong danh mục nền (base_count = 1) nhưng về 0 khi chọn 80x80 -> giữ nguyên trên UI ở trạng thái disabled
        self::assertArrayHasKey('nham', $opts_by_slug);
        self::assertSame(0, $opts_by_slug['nham']['count']);
        self::assertTrue($opts_by_slug['nham']['disabled']);
        self::assertNull($opts_by_slug['nham']['href']);
    }
}

