<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Catalog\SearchIndex;
use Dravit\Catalog\SearchNormalizer;
use Dravit\Catalog\SearchTrending;
use Dravit\Core\Config;
use Dravit\Core\Settings;
use Dravit\Seo\LlmsTxt;
use PHPUnit\Framework\TestCase;

final class SearchNormalizerTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Settings::flush_cache();

        Functions\stubs([
            'wp_parse_url' => static fn (string $url, int $component = -1): mixed => parse_url($url, $component),
            'home_url'     => static fn (string $path = ''): string => 'https://gachdravit.vn' . ($path !== '' && $path !== '/' ? '/' . ltrim($path, '/') : ''),
            'get_option'   => static fn (): array => [],
        ]);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_vietnamese_diacritics_and_mm_to_cm_dimension_normalization(): void {
        // Test chuẩn Gate 3 trong docs/21_Theme_Build_Execution_Roadmap.md
        self::assertSame(
            'gach lat nen da mo 80x80',
            SearchNormalizer::normalize('Gạch lát nền Đá mờ 800x800')
        );

        // Chiều ngược mm (1200x600 -> 60x120) & gạch thẻ lẻ (75x300 -> 7-5x30) & NFD combining marks
        self::assertSame('da nung ket 60x120', SearchNormalizer::normalize('Đá nung kết 1200 x 600 mm'));
        self::assertSame('gach the 7-5x30', SearchNormalizer::normalize('Gạch thẻ 75x300'));
        self::assertSame('gach the 7-5x30', SearchNormalizer::normalize('Gạch thẻ 7,5x30cm'));
        self::assertSame('100x100', SearchNormalizer::normalize('100x100'));
        self::assertSame('100x100', SearchNormalizer::normalize('1000x1000'));
        self::assertSame('10x20', SearchNormalizer::normalize('100x200'));

        $nfd = "Ga\u{0323}ch Đô\u{0300}ng Châ\u{0301}t DRV-8801M";
        self::assertSame('gach dong chat drv-8801m', SearchNormalizer::normalize($nfd));
    }

    public function test_search_index_builder_and_collection_synonym_matching(): void {
        $index = SearchIndex::buildIndexString([
            'sku'   => 'DRV-8801M',
            'name'  => 'Gạch Lát Nền Vân Đá Marble 80x80',
            'shelf' => 'A-08',
            'w_mm'  => 800,
            'h_mm'  => 800,
            'terms' => ['Mờ (Matt)', 'R10', 'Phòng khách'],
        ]);

        self::assertStringContainsString('drv-8801m', $index);
        self::assertStringContainsString('drv8801m', $index);
        self::assertStringContainsString('8801m', $index);
        self::assertStringContainsString('80x80', $index);
        self::assertStringContainsString('800x800', $index);
        self::assertStringContainsString('phong', $index);
        self::assertStringContainsString('r10', $index);

        $collections = Config::get('collections');
        self::assertContains('gach-nha-tam', SearchNormalizer::matchCollectionSlugs('gạch wc chống trơn', $collections));
        self::assertContains('gach-gia-go', SearchNormalizer::matchCollectionSlugs('sàn gỗ phòng ngủ', $collections));
        self::assertContains('gach-uu-dai', SearchNormalizer::matchCollectionSlugs('xả kho khuyến mãi', $collections));
        self::assertContains('gach-80x80', SearchNormalizer::matchCollectionSlugs('800x800', $collections));
        self::assertSame(['8801'], SearchNormalizer::tokenize('8801'));
        self::assertSame([], SearchNormalizer::matchCollectionSlugs('8801', $collections));
    }

    public function test_llms_txt_outputs_valid_utf8_markdown_with_all_28_clean_collections(): void {
        $collections = Config::get('collections');
        $md          = LlmsTxt::buildMarkdown(Settings::defaults(), $collections, 'https://gachdravit.vn');

        self::assertTrue(mb_check_encoding($md, 'UTF-8'));
        self::assertStringStartsWith('# DRAVIT -', $md);

        foreach (array_keys($collections) as $slug) {
            self::assertStringContainsString('(https://gachdravit.vn/' . $slug . ')', $md, "Thiếu URL sạch /{$slug} trong /llms.txt");
            self::assertStringNotContainsString('(https://gachdravit.vn/' . $slug . '/)', $md, "Phát hiện dấu / cuối trên /{$slug} trong /llms.txt");
        }
    }

    public function test_hybrid_trending_merges_pinned_learned_and_fallback_tiers_without_duplicates(): void {
        // Cold-start website mới: chưa ghim tay, chưa có lượt click tìm kiếm -> lấy đủ 4 SP từ Tầng 3 Fallback
        self::assertSame(
            [101, 102, 103, 104],
            SearchTrending::mergeTierIds([], [], [101, 102, 103, 104, 105], 4)
        );

        // Khi có dữ liệu Tầng 2 (_drv_search_hits >= 3) -> ưu tiên Tầng 2 trước rồi bù Tầng 3 không trùng
        self::assertSame(
            [108, 103, 101, 102],
            SearchTrending::mergeTierIds([], [108, 103], [101, 102, 103, 104], 4)
        );

        // Khi Admin ghim tay Tầng 1 -> luôn đứng đầu, tiếp theo Tầng 2, cuối cùng bù Tầng 3
        self::assertSame(
            [205, 108, 103, 101],
            SearchTrending::mergeTierIds([205], [205, 108, 103], [101, 102, 103], 4)
        );

        // Kiểm tra từ khóa mở rộng ngữ nghĩa cho chip "Khổ lớn 60x120" & "Chống trượt R10"
        $slab_idx = SearchIndex::buildIndexString([
            'sku'   => 'DRV-61209',
            'name'  => 'Gạch Vân Xi Măng 60x120',
            'w_mm'  => 600,
            'h_mm'  => 1200,
            'terms' => ['R10', 'Mờ'],
        ]);
        self::assertStringContainsString('kho', $slab_idx);
        self::assertStringContainsString('lon', $slab_idx);
        self::assertStringContainsString('chong', $slab_idx);
        self::assertStringContainsString('truot', $slab_idx);

        // Kiểm tra sanitize pinned_product_ids trong Settings: giới hạn cứng tối đa 4 SP (MAX_PINNED) & xóa sạch khi gửi ['0']
        $sanitized = Settings::sanitize([
            'search' => [
                'pinned_product_ids' => ['105', -4, '105', '208', '301', '402', '509', '610', 0],
                'popular_keywords'   => '80x80, Terrazzo, 80x80, Khổ lớn 60x120',
            ],
        ]);
        self::assertSame([105, 208, 301, 402], $sanitized['search']['pinned_product_ids']);
        self::assertSame(['80x80', 'Terrazzo', 'Khổ lớn 60x120'], $sanitized['search']['popular_keywords']);

        $cleared = Settings::sanitize([
            'search' => [
                'pinned_product_ids' => ['0'],
            ],
        ]);
        self::assertSame([], $cleared['search']['pinned_product_ids']);
    }
}
