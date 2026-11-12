<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Dravit\Catalog\AttributeDeriver;
use Dravit\Catalog\PrimaryCategory;
use Dravit\Catalog\ProductSeeder;
use Dravit\Catalog\RuleMatcher;
use Dravit\Catalog\SlugGuard;
use Dravit\Core\Config;
use PHPUnit\Framework\TestCase;

final class CollectionSyncTest extends TestCase {
    /** @var array<string, array<string, mixed>> */
    private array $collections = [];

    protected function setUp(): void {
        parent::setUp();
        Config::flush_cache();
        $this->collections = Config::get('collections');
    }

    public function test_config_has_exact_28_smart_collections_and_14_facets(): void {
        self::assertCount(28, $this->collections);
        self::assertArrayHasKey('gach', $this->collections);

        $facets = Config::get('facets');
        self::assertCount(14, $facets['attributes']);
        self::assertArrayHasKey('drv_price_band', $facets['internal_taxonomies']);
        self::assertArrayHasKey('drv_flag', $facets['internal_taxonomies']);
    }

    public function test_all_8_canonical_and_2_quote_skus_match_expected_collections_and_primary_category(): void {
        $definitions = ProductSeeder::definitions();
        self::assertCount(10, $definitions);

        foreach ($definitions as $def) {
            $sku   = (string) $def['sku'];
            $meta  = $def['meta'];
            $attrs = $def['attributes'];

            $w_mm      = (int) $meta['drv_piece_w_mm'];
            $h_mm      = (int) $meta['drv_piece_h_mm'];
            $size_slug = AttributeDeriver::deriveSizeSlug($w_mm, $h_mm);
            if ($size_slug !== '') {
                $attrs['pa_kich-thuoc'] = [$size_slug];
            }

            $context = [
                'line'  => (string) $meta['drv_line'],
                'attrs' => $attrs,
                'flags' => [
                    'on_sale'      => ((int) $meta['drv_sale_price_m2'] > 0 && (int) $meta['drv_sale_price_m2'] < (int) $meta['drv_price_m2']),
                    'large_format' => AttributeDeriver::isLargeFormat($w_mm, $h_mm),
                ],
            ];

            $matched  = RuleMatcher::matchCollections($this->collections, $context);
            $expected = $def['expected_collections'];

            $sorted_matched  = $matched;
            $sorted_expected = $expected;
            sort($sorted_matched);
            sort($sorted_expected);

            self::assertSame(
                $sorted_expected,
                $sorted_matched,
                sprintf('SKU %s phải khớp chính xác tập Smart Collections.', $sku)
            );

            $primary = PrimaryCategory::resolvePrimarySlug($matched, $this->collections);
            self::assertSame(
                $def['expected_primary'],
                $primary,
                sprintf('SKU %s phải chọn đúng PrimaryCategory theo trọng số D-27.', $sku)
            );
        }
    }

    public function test_attribute_deriver_edge_cases(): void {
        // Cạnh nhỏ đứng trước, dấu chấm thập phân đổi thành gạch ngang (S-2).
        self::assertSame('7-5x30', AttributeDeriver::deriveSizeSlug(75, 300));
        self::assertSame('7-5x30', AttributeDeriver::deriveSizeSlug(300, 75));
        self::assertSame('7,5x30 cm', AttributeDeriver::deriveSizeLabel(75, 300));
        self::assertSame('7,5x30', AttributeDeriver::deriveSizeShortLabel(75, 300));

        // Cờ khổ lớn Big Slab > 1200mm (S-3).
        self::assertFalse(AttributeDeriver::isLargeFormat(600, 1200));
        self::assertTrue(AttributeDeriver::isLargeFormat(1200, 2400));
        self::assertTrue(AttributeDeriver::isLargeFormat(1600, 3200));

        // Độ dày pa_do-day.
        self::assertSame('mong', AttributeDeriver::deriveThicknessSlug(6.0));
        self::assertSame('tieu-chuan', AttributeDeriver::deriveThicknessSlug(9.5));
        self::assertSame('day', AttributeDeriver::deriveThicknessSlug(15.0));

        // Độ hút nước ISO 10545-3 pa_do-hut-nuoc.
        self::assertSame('duoi-0-5', AttributeDeriver::deriveWaterAbsorptionSlug('0,12%'));
        self::assertSame('0-5-3', AttributeDeriver::deriveWaterAbsorptionSlug(2.5));
        self::assertSame('3-6', AttributeDeriver::deriveWaterAbsorptionSlug(4.5));
        self::assertSame('6-10', AttributeDeriver::deriveWaterAbsorptionSlug(8.0));
        self::assertSame('tren-10', AttributeDeriver::deriveWaterAbsorptionSlug(11.5));

        // Dải giá drv_price_band & cờ showroom.
        self::assertSame('gia-du-an', AttributeDeriver::derivePriceBandSlug(0));
        self::assertSame('duoi-200k', AttributeDeriver::derivePriceBandSlug(185000));
        self::assertSame('200k-350k', AttributeDeriver::derivePriceBandSlug(320000));
        self::assertSame('350k-600k', AttributeDeriver::derivePriceBandSlug(520000));
        self::assertSame('tren-600k', AttributeDeriver::derivePriceBandSlug(1250000));
        self::assertSame(['showroom'], AttributeDeriver::deriveFlagSlugs('A-01'));
        self::assertSame([], AttributeDeriver::deriveFlagSlugs('   '));
    }

    public function test_accessory_line_and_sale_flag_rules(): void {
        // S-9: Phụ kiện `phu_kien` không bao giờ lọt vào 28 Smart Collections gạch.
        $acc_matched = RuleMatcher::matchCollections($this->collections, [
            'line'  => 'phu_kien',
            'attrs' => ['pa_cong-nang' => ['lat-nen']],
            'flags' => ['on_sale' => true, 'large_format' => false],
        ]);
        self::assertSame([], $acc_matched);

        // S-4: Cờ khuyến mãi `on_sale` tự thêm `gach-uu-dai`.
        $sale_matched = RuleMatcher::matchCollections($this->collections, [
            'line'  => 'gach',
            'attrs' => ['pa_cong-nang' => ['lat-nen']],
            'flags' => ['on_sale' => true, 'large_format' => false],
        ]);
        self::assertContains('gach-uu-dai', $sale_matched);
    }

    public function test_primary_category_manual_override_and_reassignment(): void {
        $matched = ['gach', 'gach-lat-nen', 'gach-phong-khach', 'gach-80x80'];

        // S-5: Giữ nguyên lựa chọn tay hợp lệ dù priority thấp hơn (`gach-phong-khach` = 20 < `gach-lat-nen` = 40).
        self::assertSame(
            'gach-phong-khach',
            PrimaryCategory::resolvePrimarySlug($matched, $this->collections, 'gach-phong-khach', true)
        );

        // S-6: Khi danh mục chọn tay không còn trong tập matched -> tự chuyển về priority cao nhất (`gach-lat-nen` = 40).
        self::assertSame(
            'gach-lat-nen',
            PrimaryCategory::resolvePrimarySlug($matched, $this->collections, 'da-nung-ket', true)
        );
    }

    public function test_slug_guard_protects_28_collections_and_system_slugs(): void {
        // S-8: Tạo Page trùng slug Smart Collection -> tự động nối `-trang`.
        self::assertSame(
            'gach-lat-nen-trang',
            SlugGuard::sanitizePostSlug('gach-lat-nen', 'page', $this->collections, false)
        );
        self::assertSame(
            'da-nung-ket-trang',
            SlugGuard::sanitizePostSlug('da-nung-ket', 'post', $this->collections, false)
        );
        // Trang hệ thống chính chủ được miễn trừ.
        self::assertSame(
            'gio-hang',
            SlugGuard::sanitizePostSlug('gio-hang', 'page', $this->collections, true)
        );
        // Chặn tạo term product_cat đụng trang hệ thống.
        self::assertTrue(SlugGuard::isReservedTermSlug('lien-he'));
        self::assertTrue(SlugGuard::isReservedTermSlug('danh-sach-bao-gia'));
        self::assertFalse(SlugGuard::isReservedTermSlug('gach'));
        self::assertFalse(SlugGuard::isReservedTermSlug('gach-lat-nen'));
    }
}

