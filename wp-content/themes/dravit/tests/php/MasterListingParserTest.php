<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Dravit\Catalog\AttributeDeriver;
use Dravit\Catalog\MasterListingImporter;
use Dravit\Catalog\MasterListingParser;
use Dravit\Catalog\PrimaryCategory;
use Dravit\Catalog\RuleMatcher;
use Dravit\Core\Config;
use Dravit\Units\Calc;
use PHPUnit\Framework\TestCase;

final class MasterListingParserTest extends TestCase {
    /** @var array<string, array<string, mixed>> */
    private array $collections = [];

    /** @var array<string, mixed> */
    private array $facets = [];

    protected function setUp(): void {
        parent::setUp();
        Config::flush_cache();
        $this->collections = Config::get('collections');
        $this->facets      = Config::get('facets');
    }

    public function test_compound_column_parsers_extract_exact_atomic_specs(): void {
        $wt = MasterListingParser::parseWaterAndThickness('≤ 0,5% · Dày 9,5mm');
        self::assertSame('0.5', $wt['water_absorption']);
        self::assertSame(9.5, $wt['thickness_mm']);

        $wt2 = MasterListingParser::parseWaterAndThickness('≤ 0,1% · Dày 11,5mm');
        self::assertSame('0.1', $wt2['water_absorption']);
        self::assertSame(11.5, $wt2['thickness_mm']);

        $pr = MasterListingParser::parsePeiAndRectified('PEI IV ( ≥ 1300N ) · Mài cạnh (Rectified)');
        self::assertSame('PEI IV', $pr['pei']);
        self::assertSame(1300, $pr['breaking_strength_n']);
        self::assertSame('1', $pr['rectified']);

        $pr2 = MasterListingParser::parsePeiAndRectified('PEI V ( ≥ 1500N ) · Mài cạnh (Rectified)');
        self::assertSame('PEI V', $pr2['pei']);
        self::assertSame(1500, $pr2['breaking_strength_n']);
        self::assertSame('1', $pr2['rectified']);

        $fs = MasterListingParser::parseFacesAndShade('10 Faces · V3 (Biến thiên tự nhiên)');
        self::assertSame(10, $fs['faces']);
        self::assertSame('V3', $fs['shade']);
        self::assertFalse($fs['bookmatch']);

        $fs_bm = MasterListingParser::parseFacesAndShade('4 Faces Liên Vân (Bookmatch)');
        self::assertSame(4, $fs_bm['faces']);
        self::assertSame('', $fs_bm['shade']);
        self::assertTrue($fs_bm['bookmatch']);

        self::assertSame(
            'https://photos.app.goo.gl/1kRGiVaDenUYQw4C6',
            MasterListingParser::extractUrl('HYPERLINK("https://photos.app.goo.gl/1kRGiVaDenUYQw4C6", "https://photos.app.goo.gl/1kRGiVaDenUYQw4C6")')
        );
        self::assertSame(
            'gach-dravit-60x120-drv-va6120-b01',
            MasterListingParser::normalizeProductSlug('/san-pham/gach-dravit-60x120-drv-va6120-b01')
        );
    }

    public function test_expanded_facet_terms_exist_in_config_and_resolve_synonyms(): void {
        $attrs = $this->facets['attributes'];

        self::assertSame(['line-punch'], MasterListingParser::resolveTaxonomySlugs('pa_be-mat', 'Định hình khuôn (Line Punch)'));
        self::assertArrayHasKey('line-punch', $attrs['be-mat']['terms']);

        self::assertSame(['xuong-trang'], MasterListingParser::resolveTaxonomySlugs('pa_chat-lieu', 'Xương trắng (White Body)'));
        self::assertArrayHasKey('xuong-trang', $attrs['chat-lieu']['terms']);

        self::assertSame(['dong-chat'], MasterListingParser::resolveTaxonomySlugs('pa_chat-lieu', 'Đồng chất (Full Body)'));
        self::assertArrayHasKey('dong-chat', $attrs['chat-lieu']['terms']);

        self::assertSame(['be'], MasterListingParser::resolveTaxonomySlugs('pa_mau-sac', 'Be'));
        self::assertArrayHasKey('be', $attrs['mau-sac']['terms']);

        self::assertSame(
            ['phong-khach', 'nha-tam', 'quan-cafe'],
            MasterListingParser::resolveTaxonomySlugs('pa_khong-gian', 'Phòng khách, Phòng tắm, Quán Cafe')
        );
        self::assertArrayHasKey('quan-cafe', $attrs['khong-gian']['terms']);
        self::assertArrayHasKey('sanh-thuong-mai', $attrs['khong-gian']['terms']);
        self::assertArrayHasKey('biet-thu', $attrs['khong-gian']['terms']);

        self::assertSame(
            ['chau-au', 'sang-trong'],
            MasterListingParser::resolveTaxonomySlugs('pa_phong-cach', 'Châu Âu, Sang trọng')
        );
        self::assertArrayHasKey('chau-au', $attrs['phong-cach']['terms']);
        self::assertArrayHasKey('sang-trong', $attrs['phong-cach']['terms']);

        self::assertSame(['tieu-chuan-eu'], MasterListingParser::resolveTaxonomySlugs('pa_xuat-xu', 'Tiêu chuẩn EU'));
        self::assertArrayHasKey('tieu-chuan-eu', $attrs['xuat-xu']['terms']);

        // Kiểm chứng bộ biến thể con mở rộng chuẩn thị trường (Bảng 2)
        self::assertSame(
            ['baby-skin', 'satin', 'vi-tinh-the', 'honed', 'khuon-noi-3d', 'granilia'],
            MasterListingParser::resolveTaxonomySlugs('pa_be-mat', 'Baby Skin, Men Satin, Microcrystal, Mài mờ (Honed), 3D Relief, Dry Granula')
        );
        self::assertSame(
            ['van-da-travertine', 'van-da-onyx', 'van-da-hoa-cuong', 'van-da-bazan', 'van-kim-loai', 'van-dat-nung', 'hoa-van-co-dien'],
            MasterListingParser::resolveTaxonomySlugs('pa_van-gach', 'Travertine, Đá Onyx, Hoa cương, Đá chẻ Slate, Metallic, Terracotta, Gạch thảm')
        );
        self::assertSame(
            ['ban-su', 'cotto-dat-nung', 'thuy-tinh'],
            MasterListingParser::resolveTaxonomySlugs('pa_chat-lieu', 'Semi-Porcelain, Clinker, Glass Mosaic')
        );
        self::assertSame(
            ['op-mat-bep-lavabo', 'gach-san-nang', 'bac-cau-thang'],
            MasterListingParser::resolveTaxonomySlugs('pa_cong-nang', 'Countertop, Outdoor Paver, Len chân tường')
        );
        self::assertSame(
            ['gara-o-to', 'hanh-lang-cau-thang', 'phong-tho', 'khach-san-resort'],
            MasterListingParser::resolveTaxonomySlugs('pa_khong-gian', 'Gara ô tô, Cầu thang, Phòng thờ, Resort')
        );
        self::assertSame(
            ['ghi-sang', 'xam-dam', 'do-dat-nung', 'vang-dong'],
            MasterListingParser::resolveTaxonomySlugs('pa_mau-sac', 'Light Grey, Charcoal, Đỏ đất nung, Ánh kim')
        );
        self::assertSame(
            ['cong-nghiep', 'dia-trung-hai', 'bac-au'],
            MasterListingParser::resolveTaxonomySlugs('pa_phong-cach', 'Industrial, Địa Trung Hải, Scandinavian')
        );
        self::assertSame(
            ['kitkat', 'tron-dong-xu', 'bat-giac', 'song-luon'],
            MasterListingParser::resolveTaxonomySlugs('pa_hinh-dang', 'Finger Mosaic, Penny Round, Octagon, Fluted')
        );
        self::assertSame(
            ['thai-lan', 'indonesia'],
            MasterListingParser::resolveTaxonomySlugs('pa_xuat-xu', 'Thailand, Indonesia')
        );
        self::assertSame('sieu-day-20mm', AttributeDeriver::deriveThicknessSlug(20.0));
        self::assertArrayHasKey('sieu-day-20mm', $attrs['do-day']['terms']);

        // Kiểm chứng tự gán vào đúng Smart Collections cho các biến thể con mới
        $stone_matched = RuleMatcher::matchCollections($this->collections, [
            'line'  => 'gach',
            'attrs' => ['pa_van-gach' => ['van-da-travertine'], 'pa_khong-gian' => ['gara-o-to'], 'pa_kich-thuoc' => ['15x60']],
            'flags' => ['on_sale' => false, 'large_format' => false],
        ]);
        self::assertContains('gach-van-da', $stone_matched);
        self::assertContains('gach-san-vuon', $stone_matched);
        self::assertContains('gach-15x80-20x100', $stone_matched);

        $carpet_matched = RuleMatcher::matchCollections($this->collections, [
            'line'  => 'gach',
            'attrs' => ['pa_van-gach' => ['hoa-van-co-dien']],
            'flags' => ['on_sale' => false, 'large_format' => false],
        ]);
        self::assertContains('gach-trang-tri', $carpet_matched);
    }

    public function test_real_master_xlsx_18_skus_parse_and_match_collections_accurately(): void {
        $xlsx_path = dirname(__DIR__, 5) . '/docs/branding/Master_Product_Listing_GachDravit_VA_v2.xlsx';
        self::assertFileExists($xlsx_path);

        $importer = new MasterListingImporter();
        $records  = $importer->readRecordsFromFile($xlsx_path);

        $valid_items = [];
        foreach ($records as $row) {
            $parsed = MasterListingParser::parseRow($row);
            if ($parsed['is_valid_sku']) {
                $valid_items[$parsed['sku']] = $parsed;
            }
        }

        self::assertCount(18, $valid_items, 'Phải bóc tách đủ đúng 18 SKU từ Master_Product_Listing_GachDravit_VA_v2.xlsx.');

        $facets_attrs = $this->facets['attributes'];

        foreach ($valid_items as $sku => $item) {
            self::assertNotEmpty($item['post_title'], "SKU {$sku} phải có post_title chuẩn SEO.");
            self::assertNotEmpty($item['post_name'], "SKU {$sku} phải có post_name sạch.");
            self::assertStringNotContainsString('/san-pham/', $item['post_name']);
            self::assertStringStartsWith('https://photos.app.goo.gl/', (string) $item['meta']['drv_google_photos_url']);

            // Mọi term slug phân giải ra đều phải có trong `config/facets.php`
            foreach ($item['taxonomies'] as $tax => $slugs) {
                $attr_key = substr($tax, 3);
                self::assertArrayHasKey($attr_key, $facets_attrs, "Taxonomy {$tax} không tồn tại trong config.");
                foreach ($slugs as $t_slug) {
                    self::assertArrayHasKey(
                        $t_slug,
                        $facets_attrs[$attr_key]['terms'],
                        "Term {$t_slug} của {$tax} (SKU {$sku}) chưa được khai báo trong config/facets.php."
                    );
                }
            }

            // Kiểm tra khớp Smart Collections & PrimaryCategory
            $w_mm    = (int) $item['meta']['drv_piece_w_mm'];
            $h_mm    = (int) $item['meta']['drv_piece_h_mm'];
            $matched = RuleMatcher::matchCollections($this->collections, [
                'line'  => 'gach',
                'attrs' => $item['taxonomies'],
                'flags' => [
                    'on_sale'      => false,
                    'large_format' => AttributeDeriver::isLargeFormat($w_mm, $h_mm),
                ],
            ]);

            self::assertContains('gach', $matched);
            $resolved_primary = PrimaryCategory::resolvePrimarySlug(
                $matched,
                $this->collections,
                $item['primary_cat_slug'],
                true
            );
            self::assertSame(
                $item['primary_cat_slug'],
                $resolved_primary,
                "SKU {$sku} phải giữ đúng Danh mục chính từ cột 15 ({$item['primary_cat_slug']})."
            );

            // Kiểm tra hồ sơ AI Cột 37 -> 48
            $ai = $item['meta']['drv_ai_profile'];
            self::assertIsArray($ai);
            self::assertGreaterThan(0.0, $ai['min_room_m2']);
            self::assertGreaterThan($ai['min_room_m2'], $ai['max_room_m2']);
            self::assertGreaterThanOrEqual(5.0, $ai['wastage_pct']);
            self::assertNotEmpty($ai['why_buy_one_liner']);
            self::assertStringContainsString("[SKU: {$sku}]", (string) $ai['rag_summary_chunk']);
        }

        // Kiểm chứng cụ thể vài SKU tiêu biểu (60x120 Line Punch, 120x120 White Body Big Slab, 120x240 Bookmatch)
        $lp05 = $valid_items['DRV-VA6120-LP05'];
        self::assertSame('gach-op-tuong', $lp05['primary_cat_slug']);
        self::assertSame(['line-punch'], $lp05['taxonomies']['pa_be-mat']);
        self::assertSame(['r11'], $lp05['taxonomies']['pa_dcof']);
        self::assertSame('352800', (string) Calc::pricePerPiece(490000, Calc::pieceAreaM2(600, 1200)));

        $wb14 = $valid_items['DRV-VA1212-WB14'];
        self::assertSame('da-nung-ket', $wb14['primary_cat_slug']);
        self::assertContains('xuong-trang', $wb14['taxonomies']['pa_chat-lieu']);
        self::assertContains('da-nung-ket', $wb14['taxonomies']['pa_chat-lieu']);
        self::assertSame(['120x120'], $wb14['taxonomies']['pa_kich-thuoc']);

        $b12 = $valid_items['DRV-VA1224-B12'];
        self::assertSame('1', $b12['meta']['drv_bookmatch']);
        self::assertSame('10', $b12['meta']['drv_waste_rate']);
        self::assertSame(3600000, Calc::pricePerPiece(1250000, Calc::pieceAreaM2(1200, 2400)));
    }
}

