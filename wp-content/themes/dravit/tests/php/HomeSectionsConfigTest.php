<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Core\Config;
use Dravit\Core\SectionRegistry;
use Dravit\Core\Settings;
use Dravit\Home\HomeData;
use PHPUnit\Framework\TestCase;

final class HomeSectionsConfigTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Config::flush_cache();
        Settings::flush_cache();
        SectionRegistry::reset_cache();

        Functions\stubs([
            'esc_html'     => static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'),
            'esc_attr'     => static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'),
            'esc_url'      => static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'),
            'sanitize_key' => static fn (mixed $v): string => (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $v)),
            'home_url'     => static fn (string $path = '/'): string => 'https://gachdravit.vn/' . ltrim($path, '/'),
            'wp_parse_url' => static fn (string $url, int $component = -1): mixed => parse_url($url, $component),
            'get_option'   => static fn (): array => [],
        ]);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_home_page_config_declares_all_mockup_sections_and_preserves_section_registry(): void {
        $stack    = Config::get('pages/home');
        $registry = Config::get('sections');

        $expectedOrder = [
            'hero-advisor',
            'space-bento',
            'product-explorer-tabs',
            'size-r-guide',
            'product-grid-block',
            'showroom-showcase',
            'editorial-guides',
        ];

        $actualOrder = array_map(
            static fn (array $row): string => (string) ($row['section'] ?? ''),
            $stack
        );

        self::assertSame($expectedOrder, $actualOrder);

        $allRegistered = array_merge($expectedOrder, ['trust-strip']);
        foreach ($allRegistered as $slug) {
            self::assertArrayHasKey($slug, $registry, "Section {$slug} must be registered in config/sections.php");
            self::assertFileExists(
                DRV_DIR . '/template-parts/sections/' . $slug . '.php',
                "Section view template {$slug}.php must exist"
            );
        }
    }

    public function test_space_bento_and_explorer_tabs_use_100_percent_clean_static_collection_urls(): void {
        $collections = Config::get('collections');

        $spaces = HomeData::getSpaceBentoItems();
        self::assertCount(8, $spaces);

        foreach ($spaces as $space) {
            self::assertArrayHasKey($space['slug'], $collections);
            self::assertStringNotContainsString('?', $space['url']);
            self::assertFalse(str_ends_with($space['url'], '/'), "Trailing slash forbidden on {$space['url']}");
        }

        $tabs = HomeData::getExplorerTabs(3);
        self::assertCount(4, $tabs);

        foreach ($tabs as $tab) {
            self::assertStringNotContainsString('?', $tab['url']);
            self::assertFalse(str_ends_with($tab['url'], '/'));
            foreach ($tab['chips'] as $chip) {
                self::assertStringNotContainsString('?', $chip['url']);
                self::assertFalse(str_ends_with($chip['url'], '/'));
            }
        }
    }

    public function test_size_guide_and_showroom_view_models_respect_static_urls_and_local_assets(): void {
        $sg = HomeData::getSizeGuideData();
        self::assertCount(5, $sg['sizes']);
        self::assertCount(4, $sg['surfaces']);

        foreach ($sg['sizes'] as $size) {
            self::assertStringNotContainsString('?', $size['url']);
            self::assertFalse(str_ends_with($size['url'], '/'));
        }
        foreach ($sg['surfaces'] as $surf) {
            self::assertStringNotContainsString('?', $surf['url']);
            self::assertFalse(str_ends_with($surf['url'], '/'));
        }

        $vm = HomeData::getShowroomViewModel();
        self::assertTrue($vm['has_compare']);
        self::assertFalse($vm['has_real_pair']);

        $serialized = (string) json_encode($vm);
        self::assertStringNotContainsString('unsplash.com', $serialized);
        self::assertStringNotContainsString('pexels.com', $serialized);
    }

    public function test_full_home_stack_renders_valid_heading_hierarchy_and_single_lcp_image(): void {
        $stack = Config::get('pages/home');

        ob_start();
        try {
            SectionRegistry::render_stack($stack);
            $html = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertNotSame('', $html);
        self::assertStringNotContainsString('unsplash.com', $html);
        self::assertStringNotContainsString('pexels.com', $html);

        // Duy nhất 1 ảnh LCP có fetchpriority="high" và ảnh đó không được gán loading="lazy"
        preg_match_all('/<img\b[^>]*>/i', $html, $imgMatches);
        $highPriorityImgs = array_values(array_filter(
            $imgMatches[0],
            static fn (string $tag): bool => str_contains($tag, 'fetchpriority="high"')
        ));
        self::assertCount(1, $highPriorityImgs);
        self::assertStringNotContainsString('loading="lazy"', $highPriorityImgs[0]);

        // Kiểm tra phân cấp Heading h1 -> h2 -> h3 (không nhảy cóc từ h1 sang h3)
        preg_match_all('/<h([1-6])\b/i', $html, $headingMatches);
        $levels = array_map('intval', $headingMatches[1]);

        self::assertNotEmpty($levels);
        self::assertSame(1, $levels[0], 'First heading on homepage must be H1');
        self::assertCount(1, array_filter($levels, static fn (int $lvl): bool => $lvl === 1), 'Homepage must have exactly 1 H1');

        $prev = 1;
        foreach ($levels as $idx => $lvl) {
            self::assertLessThanOrEqual(
                $prev + 1,
                $lvl,
                "Heading jump detected at index {$idx}: H{$prev} -> H{$lvl}"
            );
            $prev = $lvl;
        }
    }
}
