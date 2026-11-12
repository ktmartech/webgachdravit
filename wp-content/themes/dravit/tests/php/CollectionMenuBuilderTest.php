<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Core\Menu\CollectionMenuBuilder;
use Dravit\Core\Menu\MegaMenuRenderer;
use Dravit\Core\Menu\MenuSetup;
use Dravit\Core\Settings;
use PHPUnit\Framework\TestCase;

final class CollectionMenuBuilderTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Settings::flush_cache();
        CollectionMenuBuilder::purge_cache();

        Functions\stubs([
            'esc_html'          => static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'),
            'esc_attr'          => static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'),
            'esc_url'           => static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8'),
            'untrailingslashit' => static fn (string $v): string => rtrim($v, '/\\'),
            'home_url'          => static fn (string $path = ''): string => 'https://gachdravit.vn' . ($path !== '' ? '/' . ltrim($path, '/') : ''),
            'get_option'        => static fn (): array => [],
        ]);
    }

    protected function tearDown(): void {
        CollectionMenuBuilder::purge_cache();
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_fallback_config_builds_all_28_static_collections_with_zero_query_or_trailing_slash(): void {
        Functions\when('get_nav_menu_locations')->justReturn([]);

        $collections = MegaMenuRenderer::load_collections_config();
        self::assertCount(28, $collections);

        $fragments = CollectionMenuBuilder::build(true);
        self::assertNotEmpty($fragments['desktop']);
        self::assertNotEmpty($fragments['mobile']);

        foreach (array_keys($collections) as $slug) {
            $expectedUrl = 'https://gachdravit.vn/' . $slug;
            self::assertStringContainsString('href="' . $expectedUrl . '"', $fragments['desktop'], "Thiếu collection {$slug} trong Desktop Mega Menu");
            self::assertStringContainsString('href="' . $expectedUrl . '"', $fragments['mobile'], "Thiếu collection {$slug} trong Mobile Accordion");
        }

        $this->assertCleanStaticMenuHrefs($fragments['desktop']);
        $this->assertCleanStaticMenuHrefs($fragments['mobile']);
    }

    public function test_editable_wp_menu_sanitizes_dirty_custom_urls_and_renders_clean_silo_links(): void {
        Functions\when('get_nav_menu_locations')->justReturn(['mega_menu' => 99]);
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('get_term')->justReturn((object) ['count' => 42]);

        Functions\when('wp_get_nav_menu_items')->justReturn([
            (object) [
                'ID'               => 1,
                'menu_item_parent' => 0,
                'title'            => '01. Theo công năng',
                'url'              => 'https://gachdravit.vn/gach-lat-nen/',
            ],
            (object) [
                'ID'               => 2,
                'menu_item_parent' => 1,
                'title'            => 'Gạch Lát Nền Chủ Lực',
                'url'              => 'https://gachdravit.vn/gach-lat-nen/?kich-thuoc=80x80',
            ],
            (object) [
                'ID'               => 3,
                'menu_item_parent' => 2,
                'title'            => 'Gạch Phòng Khách 80x80',
                'url'              => 'https://gachdravit.vn/gach-80x80/?be-mat=bong',
                'object'           => 'product_cat',
                'object_id'        => 15,
            ],
            (object) [
                'ID'               => 4,
                'menu_item_parent' => 2,
                'title'            => 'Link Prototype Cũ',
                'url'              => '02-catalog-collection.html?van=marble',
            ],
        ]);

        $fragments = CollectionMenuBuilder::build(true);

        self::assertStringContainsString('Gạch Phòng Khách 80x80', $fragments['desktop']);
        self::assertStringContainsString('href="https://gachdravit.vn/gach-80x80"', $fragments['desktop']);
        self::assertStringContainsString('>42<', $fragments['desktop']);

        $this->assertCleanStaticMenuHrefs($fragments['desktop']);
        $this->assertCleanStaticMenuHrefs($fragments['mobile']);
    }

    public function test_menu_setup_url_sanitizer_rules(): void {
        self::assertSame(
            'https://gachdravit.vn/gach-lat-nen',
            MenuSetup::sanitize_menu_url('https://gachdravit.vn/gach-lat-nen/?kich-thuoc=80x80', true)
        );
        self::assertSame(
            '/lien-he#showroom',
            MenuSetup::sanitize_menu_url('/lien-he/#showroom', false)
        );
        self::assertSame(
            '/gach',
            MenuSetup::sanitize_menu_url('02-catalog-collection.html?nhom=lat-nen', true)
        );
        self::assertSame(
            'https://zalo.me/0909888777',
            MenuSetup::sanitize_menu_url('https://zalo.me/0909888777', true)
        );
    }

    private function assertCleanStaticMenuHrefs(string $html): void {
        preg_match_all('/href="([^"]+)"/', $html, $matches);
        $hrefs = $matches[1] ?? [];
        self::assertNotEmpty($hrefs);

        foreach ($hrefs as $href) {
            self::assertStringNotContainsString('?', $href, "Phát hiện query param cấm trong Mega/Mobile Menu: {$href}");
            self::assertStringNotContainsString('.html', $href, "Phát hiện link .html thừa trong Menu: {$href}");

            $path = (string) parse_url($href, PHP_URL_PATH);
            if ($path !== '' && $path !== '/') {
                self::assertFalse(str_ends_with($path, '/'), "Phát hiện dấu / cuối vi phạm D-21: {$href}");
            }
        }
    }
}

