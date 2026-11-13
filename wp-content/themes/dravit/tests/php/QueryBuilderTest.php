<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Core\Settings;
use Dravit\Filters\QueryBuilder;
use Dravit\Filters\UrlState;
use PHPUnit\Framework\TestCase;

final class QueryBuilderTest extends TestCase {
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

    public function test_build_tax_query_enforces_and_between_facets_and_or_within_facet(): void {
        Functions\when('wc_get_product_visibility_term_ids')->justReturn([
            'exclude-from-catalog' => 17,
            'outofstock'           => 19,
        ]);

        $state = UrlState::fromArray(
            'product_cat:gach-nha-tam',
            [
                'kich-thuoc' => '30x60,60x60',
                'dcof'       => 'r10,r11',
                'showroom'   => '1',
            ],
            1,
            24,
            'https://gachdravit.vn'
        );

        $tax_query = QueryBuilder::buildTaxQuery($state);

        self::assertSame('AND', $tax_query['relation']);

        // 0: Danh mục gốc gach-nha-tam (include_children = false để không lẫn danh mục khác)
        self::assertSame('product_cat', $tax_query[0]['taxonomy']);
        self::assertSame(['gach-nha-tam'], $tax_query[0]['terms']);
        self::assertFalse($tax_query[0]['include_children']);

        // 1: Loại sản phẩm ẩn khỏi catalog
        self::assertSame('product_visibility', $tax_query[1]['taxonomy']);
        self::assertSame([17], $tax_query[1]['terms']);
        self::assertSame('NOT IN', $tax_query[1]['operator']);

        // 2: Showroom (order = 5) ánh xạ sang drv_flag = showroom
        self::assertSame('drv_flag', $tax_query[2]['taxonomy']);
        self::assertSame(['showroom'], $tax_query[2]['terms']);
        self::assertSame('IN', $tax_query[2]['operator']);

        // 3: Kích thước (order = 40, OR / IN giữa 30x60 và 60x60)
        self::assertSame('pa_kich-thuoc', $tax_query[3]['taxonomy']);
        self::assertSame(['30x60', '60x60'], $tax_query[3]['terms']);
        self::assertSame('IN', $tax_query[3]['operator']);

        // 4: DCOF (order = 100, OR / IN giữa r10 và r11)
        self::assertSame('pa_dcof', $tax_query[4]['taxonomy']);
        self::assertSame(['r10', 'r11'], $tax_query[4]['terms']);
        self::assertSame('IN', $tax_query[4]['operator']);

        // Riêng danh mục gốc /gach cho phép include_children = true
        $root_state = UrlState::fromArray('product_cat:gach', [], 1, 24, 'https://gachdravit.vn');
        $root_tax   = QueryBuilder::buildTaxQuery($root_state);
        self::assertTrue($root_tax[0]['include_children']);
    }

    public function test_sort_sql_clauses_push_outofstock_and_quote_only_products_last(): void {
        $asc = QueryBuilder::buildSortSqlClauses('gia-tang', 'wp_posts', 'wp_postmeta', 'wp_term_relationships', 19);
        self::assertStringContainsString('drv_oos.term_taxonomy_id = 19', $asc['join']);
        self::assertStringContainsString('CASE WHEN drv_oos.object_id IS NOT NULL THEN 1 ELSE 0 END ASC', $asc['orderby']);
        self::assertStringContainsString('<= 0 THEN 1 ELSE 0 END ASC', $asc['orderby'], 'D-20: SP báo giá (<= 0đ/m²) phải xếp sau SP có giá khi giá tăng');
        self::assertStringContainsString(') ASC', $asc['orderby']);

        $desc = QueryBuilder::buildSortSqlClauses('gia-giam', 'wp_posts', 'wp_postmeta', 'wp_term_relationships', 19);
        self::assertStringContainsString('<= 0 THEN 1 ELSE 0 END ASC', $desc['orderby'], 'D-20: SP báo giá (<= 0đ/m²) vẫn phải xếp sau SP có giá khi giá giảm');
        self::assertStringContainsString(') DESC', $desc['orderby']);

        $showroom = QueryBuilder::buildSortSqlClauses('showroom-truoc', 'wp_posts', 'wp_postmeta', 'wp_term_relationships', 19, 42);
        self::assertStringContainsString('drv_sr.term_taxonomy_id = 42', $showroom['join']);
        self::assertStringContainsString('CASE WHEN drv_sr.object_id IS NOT NULL THEN 0 ELSE 1 END ASC', $showroom['orderby']);

        $size_desc = QueryBuilder::buildSortSqlClauses('kho-lon', 'wp_posts', 'wp_postmeta', 'wp_term_relationships', 0, 0);
        self::assertStringContainsString("meta_key = 'drv_piece_w_mm'", $size_desc['join']);
        self::assertStringContainsString("meta_key = 'drv_piece_h_mm'", $size_desc['join']);
        self::assertStringContainsString('<= 0 THEN 1 ELSE 0 END ASC', $size_desc['orderby']);
        self::assertStringContainsString('DESC', $size_desc['orderby']);

        $size_asc = QueryBuilder::buildSortSqlClauses('kho-nho', 'wp_posts', 'wp_postmeta', 'wp_term_relationships', 0, 0);
        self::assertStringContainsString('ASC', $size_asc['orderby']);
    }
}
