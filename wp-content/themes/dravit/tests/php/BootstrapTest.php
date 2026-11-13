<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Advisor\AdvisorController;
use Dravit\Advisor\Log\CptAdvisorLogger;
use Dravit\Catalog\SearchController;
use Dravit\Catalog\SlugGuard;
use Dravit\Core\Assets;
use Dravit\Core\Cleanup;
use Dravit\Core\Menu\MegaMenuRenderer;
use Dravit\Core\Menu\MenuSetup;
use Dravit\Core\NonceController;
use Dravit\Core\RestSecurity;
use Dravit\Core\SectionRegistry;
use Dravit\Core\ServiceProviderInterface;
use Dravit\Core\Settings;
use Dravit\Core\Setup;
use Dravit\Core\Vite;
use Dravit\Seo\Breadcrumbs;
use Dravit\Seo\Canonical;
use Dravit\Seo\LlmsTxt;
use Dravit\Seo\MetaTags;
use Dravit\Seo\RobotsMatrix;
use Dravit\Seo\SchemaGraph;
use Dravit\Seo\TrailingSlashRedirect;
use Dravit\Theme;
use PHPUnit\Framework\TestCase;

final class BootstrapTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Theme::reset_for_tests();
        Settings::flush_cache();
        Vite::reset_cache();
        SectionRegistry::reset_cache();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_psr4_autoloader_loads_core_classes_and_interfaces(): void {
        self::assertTrue(interface_exists(ServiceProviderInterface::class));
        self::assertTrue(class_exists(Theme::class));
        self::assertTrue(class_exists(Setup::class));
        self::assertTrue(class_exists(Vite::class));
        self::assertTrue(class_exists(SectionRegistry::class));
    }

    public function test_theme_boot_gracefully_degrades_when_optional_plugins_are_missing(): void {
        // Khi chưa có WooCommerce, Rank Math, Fluent Forms hay LiteSpeed Cache trong runtime test:
        Theme::boot();

        $expected = [
            Settings::class,
            Setup::class,
            RestSecurity::class,
            NonceController::class,
            SlugGuard::class,
            TrailingSlashRedirect::class,
            Canonical::class,
            RobotsMatrix::class,
            Breadcrumbs::class,
            MetaTags::class,
            SchemaGraph::class,
            LlmsTxt::class,
            SearchController::class,
            CptAdvisorLogger::class,
            AdvisorController::class,
            MenuSetup::class,
            MegaMenuRenderer::class,
            Cleanup::class,
            Assets::class,
        ];

        $providers = Theme::registered_providers();
        self::assertSame($expected, array_map(static fn (object $p): string => $p::class, $providers));

        // Gọi boot() lần 2 phải đảm bảo idempotent (không nhân đôi đăng ký hook).
        Theme::boot();
        self::assertCount(count($expected), Theme::registered_providers());
    }

    public function test_settings_merges_nested_options_over_defaults(): void {
        Functions\when('get_option')->justReturn([
            'contact' => [
                'hotline' => '0909123456',
            ],
        ]);

        self::assertSame('0909123456', drv_setting('contact.hotline'));
        self::assertSame('08:00 - 21:00 (Thứ 2 - Chủ nhật)', drv_setting('contact.working_hours'));
        self::assertSame('rule_only', drv_setting('ai.provider'));
        self::assertSame('fallback', drv_setting('khong.ton.tai', 'fallback'));
    }

    public function test_vietnamese_number_and_currency_helpers_match_dravit_spec(): void {
        self::assertSame('385.000đ', drv_format_vnd(385000));
        self::assertSame('246.400đ', drv_format_vnd(246400.2));
        self::assertSame('1,92', drv_format_decimal(1.92));
        self::assertSame('8,5', drv_format_decimal(8.50));
        self::assertSame('30', drv_format_decimal(30.00));
    }

    public function test_setup_forces_vietnamese_lang_and_strips_trailing_slash(): void {
        Functions\when('untrailingslashit')->alias(static fn (string $str): string => rtrim($str, '/\\'));

        $setup = new Setup();
        self::assertSame('lang="vi"', $setup->force_vietnamese_lang('lang="en-US"'));
        self::assertSame('lang="vi" dir="ltr"', $setup->force_vietnamese_lang('dir="ltr"'));
        self::assertSame('/', $setup->untrailingslash_non_root('/', 'home'));
        self::assertSame('/gach-lat-nen', $setup->untrailingslash_non_root('/gach-lat-nen/', 'category'));
    }

    public function test_vite_manifest_resolves_hashed_urls_and_preloads(): void {
        $tmpManifest = tempnam(sys_get_temp_dir(), 'drv_manifest_');
        self::assertIsString($tmpManifest);

        file_put_contents(
            $tmpManifest,
            (string) json_encode([
                'assets/src/js/core.js' => [
                    'file'    => 'js/core-abc123.js',
                    'imports' => ['_format-def456.js'],
                ],
                '_format-def456.js' => [
                    'file' => 'js/chunks/format-def456.js',
                ],
            ])
        );

        try {
            self::assertSame(
                'https://gachdravit.vn/wp-content/themes/dravit/assets/dist/js/core-abc123.js',
                Vite::asset_url('assets/src/js/core.js', $tmpManifest)
            );
            self::assertSame(
                ['https://gachdravit.vn/wp-content/themes/dravit/assets/dist/js/chunks/format-def456.js'],
                Vite::preload_urls('assets/src/js/core.js', $tmpManifest)
            );
            self::assertNull(Vite::asset_url('assets/src/js/missing.js', $tmpManifest));
        } finally {
            @unlink($tmpManifest);
        }
    }

    public function test_section_registry_exposes_all_16_reusable_sections(): void {
        $definitions = SectionRegistry::definitions();
        self::assertCount(16, $definitions);
        self::assertArrayHasKey('hero-advisor', $definitions);
        self::assertArrayHasKey('boq-workspace', $definitions);
        self::assertArrayHasKey('ai-finder-workspace', $definitions);
    }
}

