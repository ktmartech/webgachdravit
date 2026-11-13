<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Catalog\SearchIndex;
use Dravit\Catalog\SearchNormalizer;
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
}
