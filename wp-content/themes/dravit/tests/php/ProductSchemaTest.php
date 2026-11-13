<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Core\Settings;
use Dravit\Seo\InternalLinks;
use Dravit\Seo\SchemaGraph;
use Dravit\Units\PriceState;
use PHPUnit\Framework\TestCase;

final class ProductSchemaTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Settings::flush_cache();

        Functions\stubs([
            'wp_parse_url'      => static fn (string $url, int $component = -1): mixed => parse_url($url, $component),
            'wp_strip_all_tags' => static fn (string $s): string => strip_tags($s),
            'home_url'          => static fn (string $path = ''): string => 'https://gachdravit.vn' . ($path !== '' && $path !== '/' ? '/' . ltrim($path, '/') : ''),
            'get_option'        => static fn (): array => [],
        ]);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_priced_product_emits_dual_unit_price_specification_and_never_leaks_lot_or_shade_in_mpn(): void {
        $node = SchemaGraph::buildProductNodeFromData([
            'name'              => 'Gạch Vân Đá Marble Calacatta 80x80',
            'url'               => 'https://gachdravit.vn/san-pham/gach-calacatta-80x80/',
            'sku'               => 'DRV-8801M',
            'description'       => 'Xương đá Porcelain mài cạnh phẳng.',
            'images'            => ['https://gachdravit.vn/uploads/drv-8801m.webp'],
            'pricing_model'     => 'area_pack',
            'price_state'       => PriceState::SALE,
            'price_piece'       => 224000,
            'price_m2'          => 350000,
            'in_stock'          => true,
            'price_valid_until' => '2026-12-31',
            'unit_label'        => 'viên',
            'pieces_per_box'    => 3,
            'm2_per_box'        => 1.92,
            'w_mm'              => 800,
            'h_mm'              => 800,
            'thickness_mm'      => 9.5,
            'kg_per_box'        => 42.6,
            'material'          => 'Porcelain',
            'color'             => 'Trắng',
            'pattern'           => 'Vân đá Marble',
            'dcof'              => 'R9',
            'water_absorption'  => '0.18',
            'pei_rating'        => 'PEI 4',
        ]);

        self::assertSame('Product', $node['@type']);
        self::assertSame('https://gachdravit.vn/san-pham/gach-calacatta-80x80', $node['url']);
        self::assertSame('DRV-8801M', $node['sku']);
        self::assertArrayNotHasKey('mpn', $node, 'M-16: Cấm xuất mpn chứa mã lô/shade nội bộ');
        self::assertArrayNotHasKey('aggregateRating', $node);
        self::assertSame('DRAVIT', $node['brand']['name']);
        self::assertSame(800, $node['width']['value']);
        self::assertSame('MMT', $node['width']['unitCode']);
        self::assertSame(14.2, $node['weight']['value']);
        self::assertSame('KGM', $node['weight']['unitCode']);

        self::assertArrayHasKey('offers', $node);
        self::assertSame(224000, $node['offers']['price']);
        self::assertSame('VND', $node['offers']['priceCurrency']);
        self::assertCount(2, $node['offers']['priceSpecification']);
        self::assertSame('C62', $node['offers']['priceSpecification'][0]['unitCode']);
        self::assertSame(224000, $node['offers']['priceSpecification'][0]['price']);
        self::assertSame('MTK', $node['offers']['priceSpecification'][1]['unitCode']);
        self::assertSame(350000, $node['offers']['priceSpecification'][1]['price']);
    }

    public function test_quote_only_product_completely_omits_offers_per_adr_d20_and_m13(): void {
        $node = SchemaGraph::buildProductNodeFromData([
            'name'          => 'Đá Nung Kết Big Slab 120x240 Emperador',
            'url'           => 'https://gachdravit.vn/san-pham/da-nung-ket-120x240',
            'sku'           => 'DRV-SLAB-01',
            'pricing_model' => 'area_pack',
            'price_state'   => PriceState::QUOTE_ONLY,
            'price_piece'   => 0,
            'price_m2'      => 0,
            'w_mm'          => 1200,
            'h_mm'          => 2400,
            'thickness_mm'  => 12.0,
        ]);

        self::assertSame('Product', $node['@type']);
        self::assertSame('DRV-SLAB-01', $node['sku']);
        self::assertArrayNotHasKey('offers', $node, 'M-13 & D-20: Sản phẩm quote_only cấm tuyệt đối xuất Offer 0đ');
    }

    public function test_collection_entities_emit_itemlist_and_faqpage_only_on_clean_page_one(): void {
        $products = [
            ['name' => 'Gạch 80x80 Mẫu 1', 'url' => 'https://gachdravit.vn/san-pham/mau-1/'],
            ['name' => 'Gạch 80x80 Mẫu 2', 'url' => 'https://gachdravit.vn/san-pham/mau-2/'],
        ];
        $faqs = [
            ['q' => '1 thùng gạch 80x80 bao nhiêu m²?', 'a' => '<p>1 thùng 3 viên tương đương 1,92 m².</p>'],
        ];

        // Trang 1 sạch -> có đủ CollectionPage + ItemList + FAQPage
        $clean = SchemaGraph::buildCollectionEntities(
            'https://gachdravit.vn/gach-80x80/',
            'Gạch Lát Nền 80x80 cm',
            'Mô tả danh mục',
            $products,
            $faqs,
            1,
            false
        );
        self::assertArrayHasKey('collectionPage', $clean);
        self::assertArrayHasKey('itemList', $clean);
        self::assertArrayHasKey('faqPage', $clean);
        self::assertSame(2, $clean['itemList']['numberOfItems']);
        self::assertSame('https://gachdravit.vn/san-pham/mau-1', $clean['itemList']['itemListElement'][0]['url']);
        self::assertSame('1 thùng 3 viên tương đương 1,92 m².', $clean['faqPage']['mainEntity'][0]['acceptedAnswer']['text']);

        // M-12 & M-14: Trang phân trang page 2 hoặc có param lọc -> CẤM xuất ItemList & FAQPage
        $paged2 = SchemaGraph::buildCollectionEntities('https://gachdravit.vn/gach-80x80', 'Gạch 80x80', '', $products, $faqs, 2, false);
        self::assertArrayNotHasKey('itemList', $paged2);
        self::assertArrayNotHasKey('faqPage', $paged2);

        $filtered = SchemaGraph::buildCollectionEntities('https://gachdravit.vn/gach-80x80', 'Gạch 80x80', '', $products, $faqs, 1, true);
        self::assertArrayNotHasKey('itemList', $filtered);
        self::assertArrayNotHasKey('faqPage', $filtered);
    }

    public function test_showroom_blog_and_internal_link_contracts(): void {
        $sr = SchemaGraph::buildShowroomEntities(null, true, 'https://gachdravit.vn');
        self::assertArrayHasKey('organization', $sr);
        self::assertArrayHasKey('showroom_1', $sr);
        self::assertArrayHasKey('contactPage', $sr);
        self::assertSame('HomeAndConstructionBusiness', $sr['showroom_1']['@type']);

        // M-15: Ép Article -> BlogPosting, gỡ HowTo, gắn reviewedBy
        $blog = SchemaGraph::sanitizeBlogEntities([
            'article' => ['@type' => 'Article', 'headline' => 'Cách tính gạch'],
            'howto'   => ['@type' => 'HowTo', 'name' => 'HowTo cũ'],
        ], 'KS. Nguyễn Minh Quân');
        self::assertArrayNotHasKey('howto', $blog);
        self::assertSame('BlogPosting', $blog['article']['@type']);
        self::assertSame('KS. Nguyễn Minh Quân', $blog['article']['reviewedBy']['name']);

        // IL-03: Chỉ trả URL Silo tĩnh không chứa `?`, thuộc tính không có trang tĩnh trả null
        self::assertSame('https://gachdravit.vn/gach-80x80', InternalLinks::resolveTermUrl('pa_kich-thuoc', '80x80'));
        self::assertSame('https://gachdravit.vn/gach-van-da', InternalLinks::resolveTermUrl('pa_van-gach', 'van-da-marble'));
        self::assertSame('https://gachdravit.vn/bo-suu-tap/dravit-luxury', InternalLinks::resolveTermUrl('pa_bo-suu-tap', 'dravit-luxury'));
        self::assertNull(InternalLinks::resolveTermUrl('pa_mau-sac', 'trang'));
        self::assertNull(InternalLinks::resolveTermUrl('pa_dcof', 'r10'));
    }
}

