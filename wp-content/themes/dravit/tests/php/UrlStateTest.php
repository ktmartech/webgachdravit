<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Core\Settings;
use Dravit\Filters\FacetRegistry;
use Dravit\Filters\UrlState;
use PHPUnit\Framework\TestCase;

final class UrlStateTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Settings::flush_cache();

        Functions\stubs([
            'wp_parse_url'      => static fn (string $url, int $component = -1): mixed => parse_url($url, $component),
            'wp_strip_all_tags' => static fn (string $text): string => strip_tags($text),
            'get_option'        => static fn (): array => [],
        ]);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_query_grammar_orders_params_sorts_values_dedups_and_strips_garbage(): void {
        // R-4, R-5, R-6, R-8: Tham số sai thứ tự + trùng giá trị + hoa thường + rác + sort=moi-ve mặc định
        $state = UrlState::fromArray(
            'product_cat:gach-phong-khach',
            [
                'gia'        => '350k-600k,200k-350k',
                'be-mat'     => 'mo',
                'kich-thuoc' => ['80X80', '60x60', '80x80', 'khong-ton-tai'],
                'mau-sac'    => 'xam,trang',
                'foo_hack'   => 'DROP TABLE wp_posts',
                'sort'       => 'moi-ve',
                'per_page'   => 999,
            ],
            1,
            null,
            'https://gachdravit.vn'
        );

        self::assertSame(48, $state->perPage(), 'X-2: per_page phải bị chặn trần ở 48');
        self::assertSame('moi-ve', $state->sort());
        self::assertSame(
            'https://gachdravit.vn/gach-phong-khach?kich-thuoc=60x60,80x80&mau-sac=trang,xam&be-mat=mo&gia=200k-350k,350k-600k',
            $state->url()
        );
        self::assertSame('https://gachdravit.vn/gach-phong-khach', $state->canonical());
        self::assertSame('noindex, nofollow', $state->robots(12, 1), 'M-5: Có tham số gia (tech) -> noindex, nofollow');
    }

    public function test_single_value_on_root_gach_resolves_static_redirect_only_when_mapped(): void {
        // R-3: Đứng ở /gach chọn đúng 1 giá trị đã có Collection/Attribute tĩnh -> trả redirect_to không dấu / cuối
        $silo_lat_nen = UrlState::fromArray(
            'product_cat:gach',
            ['cong-nang' => 'lat-nen'],
            1,
            24,
            'https://gachdravit.vn'
        );
        self::assertSame('https://gachdravit.vn/gach-lat-nen', $silo_lat_nen->redirectTo());

        $silo_bst = UrlState::fromArray(
            'product_cat:gach',
            ['bo-suu-tap' => 'dravit-luxury'],
            1,
            24,
            'https://gachdravit.vn'
        );
        self::assertSame('https://gachdravit.vn/bo-suu-tap/dravit-luxury', $silo_bst->redirectTo());

        // Không có trang tĩnh riêng (mau-sac=trang) hoặc chọn >= 2 giá trị hoặc có sort -> giữ null
        $unmapped = UrlState::fromArray(
            'product_cat:gach',
            ['mau-sac' => 'trang'],
            1,
            24,
            'https://gachdravit.vn'
        );
        self::assertNull($unmapped->redirectTo());

        $multi = UrlState::fromArray(
            'product_cat:gach',
            ['cong-nang' => 'lat-nen,op-tuong'],
            1,
            24,
            'https://gachdravit.vn'
        );
        self::assertNull($multi->redirectTo());
    }

    public function test_robots_canonical_and_title_matrix_across_clean_paged_and_filtered_states(): void {
        // M-1 & M-2: Trang sạch trang 1 & trang 2 -> index, follow; canonical giữ /page/2 ở trang sạch
        $clean_p1 = UrlState::fromArray('product_cat:gach-80x80', [], 1, 24, 'https://gachdravit.vn');
        self::assertSame('index, follow', $clean_p1->robots(18, 1));
        self::assertSame('https://gachdravit.vn/gach-80x80', $clean_p1->canonical());
        self::assertStringNotContainsString('—', $clean_p1->title('Gạch 80x80'));

        $clean_p2 = UrlState::fromArray('product_cat:gach-80x80', [], 2, 24, 'https://gachdravit.vn');
        self::assertSame('index, follow', $clean_p2->robots(36, 2));
        self::assertSame('https://gachdravit.vn/gach-80x80/page/2', $clean_p2->canonical());

        // M-3, M-4, M-7: Lọc nhóm SEO ở trang 2 -> noindex, follow; canonical trỏ về trang 1 sạch
        $seo_filtered_p2 = UrlState::fromArray(
            'product_cat:gach-80x80',
            ['be-mat' => 'mo', 'mau-sac' => 'trang'],
            2,
            24,
            'https://gachdravit.vn'
        );
        self::assertSame('noindex, follow', $seo_filtered_p2->robots(30, 2));
        self::assertSame('https://gachdravit.vn/gach-80x80', $seo_filtered_p2->canonical());
        self::assertSame('https://gachdravit.vn/gach-80x80/page/2?mau-sac=trang&be-mat=mo', $seo_filtered_p2->url());

        // M-6: Có tham số sort -> noindex, nofollow
        $sorted = UrlState::fromArray('product_cat:gach-80x80', ['sort' => 'gia-tang'], 1, 24, 'https://gachdravit.vn');
        self::assertSame('noindex, nofollow', $sorted->robots(10, 1));
    }

    public function test_f14_dimension_one_duplicate_facet_hiding_and_m13_anti_spider_trap_links(): void {
        // F-14: /gach-phong-khach ẩn đúng giá trị 'phong-khach' và gọt bỏ nếu truyền dư trên URL
        $pk_state = UrlState::fromArray(
            'product_cat:gach-phong-khach',
            ['khong-gian' => 'phong-khach,nha-tam'],
            1,
            24,
            'https://gachdravit.vn'
        );
        self::assertSame(['phong-khach'], $pk_state->hiddenTerms()['khong-gian'] ?? []);
        self::assertSame(['nha-tam'], $pk_state->facets()['khong-gian'] ?? []);

        // F-14: /gach-80x80 ẩn toàn bộ nhóm kich-thuoc; /gach-15x80-20x100 giữ nguyên để chọn 15x90 / 20x100
        $ctx_80x80 = FacetRegistry::resolveHiddenContext('product_cat', 'gach-80x80');
        self::assertContains('kich-thuoc', $ctx_80x80['hidden_facets']);

        $ctx_wood = FacetRegistry::resolveHiddenContext('product_cat', 'gach-15x80-20x100');
        self::assertNotContains('kich-thuoc', $ctx_wood['hidden_facets']);

        $ctx_bst = FacetRegistry::resolveHiddenContext('pa_bo-suu-tap', 'dravit-luxury');
        self::assertContains('bo-suu-tap', $ctx_bst['hidden_facets']);

        // M-13: Trang sạch 0 tham số -> nhóm SEO sinh URL sạch hoặc 1-param, nhóm TECH luôn trả null
        self::assertSame(
            'https://gachdravit.vn/gach-lat-nen',
            FacetRegistry::resolveOptionHref('cong-nang', 'lat-nen', 'product_cat', 'gach', 'https://gachdravit.vn/gach', false, 12)
        );
        self::assertSame(
            'https://gachdravit.vn/gach-phong-khach?kich-thuoc=80x80',
            FacetRegistry::resolveOptionHref('kich-thuoc', '80x80', 'product_cat', 'gach-phong-khach', 'https://gachdravit.vn/gach-phong-khach', false, 6)
        );
        self::assertNull(
            FacetRegistry::resolveOptionHref('dcof', 'r10', 'product_cat', 'gach-phong-khach', 'https://gachdravit.vn/gach-phong-khach', false, 6),
            'M-13: Nhóm tech (dcof) không bao giờ được gắn <a href>'
        );
        self::assertNull(
            FacetRegistry::resolveOptionHref('showroom', '1', 'product_cat', 'gach-phong-khach', 'https://gachdravit.vn/gach-phong-khach', false, 4),
            'M-13: Showroom toggle không bao giờ được gắn <a href>'
        );
        self::assertNull(
            FacetRegistry::resolveOptionHref('mau-sac', 'trang', 'product_cat', 'gach-phong-khach', 'https://gachdravit.vn/gach-phong-khach', true, 5),
            'M-13: Khi đã có >= 1 tham số đang bật, toàn bộ link <a> bộ lọc phải bị khóa'
        );
        self::assertNull(
            FacetRegistry::resolveOptionHref('mau-sac', 'den', 'product_cat', 'gach-phong-khach', 'https://gachdravit.vn/gach-phong-khach', false, 0),
            'M-13: Mục count = 0 không gắn <a href>'
        );
    }
}
