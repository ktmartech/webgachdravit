<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Core\Settings;
use PHPUnit\Framework\TestCase;

final class SettingsHelperTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Settings::flush_cache();

        Functions\stubs([
            'sanitize_text_field'     => static fn (mixed $v): string => trim(strip_tags((string) $v)),
            'sanitize_textarea_field' => static fn (mixed $v): string => trim(strip_tags((string) $v)),
            'sanitize_email'          => static fn (mixed $v): string => filter_var((string) $v, FILTER_SANITIZE_EMAIL) ?: '',
            'esc_url_raw'             => static fn (mixed $v): string => trim((string) $v),
            'sanitize_key'            => static fn (mixed $v): string => (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $v)),
        ]);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_defaults_and_nested_dot_lookup(): void {
        Functions\when('get_option')->justReturn([
            'company' => [
                'tax_id' => '0399888777',
            ],
            'features' => [
                'ai_enabled' => false,
            ],
        ]);

        self::assertSame('0399888777', drv_setting('company.tax_id'));
        self::assertSame('DRAVIT', drv_setting('company.brand_name'));
        self::assertFalse(drv_setting('features.ai_enabled'));
        self::assertSame(24, drv_setting('catalog.per_page'));
        self::assertSame('default-val', drv_setting('unknown.deep.key', 'default-val'));
    }

    public function test_sanitize_enforces_types_ranges_and_strips_xss(): void {
        Functions\when('get_option')->justReturn([]);

        $sanitized = Settings::sanitize([
            'company' => [
                'legal_name' => '<b>Công ty DRAVIT</b><script>alert(1)</script>',
                'email'      => 'sales@gachdravit.vn',
            ],
            'contact' => [
                'open_hour'  => '7',
                'close_hour' => '25',
            ],
            'bank' => [
                'bank_bin'   => '970436-abc',
                'account_no' => '0071 000 888 999',
            ],
            'catalog' => [
                'per_page' => '999',
            ],
            'forms' => [
                'quote_modal' => '12',
            ],
            'ai' => [
                'provider' => 'invalid_driver',
            ],
        ]);

        self::assertSame('Công ty DRAVITalert(1)', $sanitized['company']['legal_name']);
        self::assertSame('sales@gachdravit.vn', $sanitized['company']['email']);
        self::assertSame(7, $sanitized['contact']['open_hour']);
        self::assertSame(24, $sanitized['contact']['close_hour']);
        self::assertSame('970436', $sanitized['bank']['bank_bin']);
        self::assertSame('0071000888999', $sanitized['bank']['account_no']);
        self::assertSame(48, $sanitized['catalog']['per_page']);
        self::assertSame(12, $sanitized['forms']['quote_modal']);
        self::assertSame('rule_only', $sanitized['ai']['provider']);
    }

    public function test_option_update_purges_mega_menu_transient_and_litespeed_tag(): void {
        $deleted = [];
        $purged  = [];

        Functions\when('delete_transient')->alias(static function (string $key) use (&$deleted): bool {
            $deleted[] = $key;
            return true;
        });
        Functions\when('do_action')->alias(static function (string $hook, mixed ...$args) use (&$purged): void {
            $purged[$hook] = $args;
        });

        Settings::on_option_updated();

        self::assertContains('drv_mega_html', $deleted);
        self::assertSame(['drv_global_shell'], $purged['litespeed_purge'] ?? null);
    }

    public function test_phone_vnd_and_decimal_helpers(): void {
        self::assertSame('0909888777', drv_clean_phone('0909.888.777 (Zalo)'));
        self::assertSame('+84909888777', drv_clean_phone('+84 909 888 777'));
        self::assertSame('0đ', drv_format_vnd(0));
        self::assertSame('1.250.000đ', drv_format_vnd(1250000));
        self::assertSame('11,52', drv_format_decimal(11.52));
        self::assertSame('42', drv_format_decimal(42.0, 1));
    }

    public function test_brand_logo_media_id_sanitize_and_render_fallback(): void {
        if (!defined('DRV_URI')) {
            define('DRV_URI', 'http://localhost:10029/wp-content/themes/dravit');
        }
        Functions\stubs([
            'esc_url'  => static fn (mixed $v): string => (string) $v,
            'esc_attr' => static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'),
        ]);

        Functions\when('get_option')->justReturn([]);
        $sanitized = Settings::sanitize([
            'company' => [
                'header_logo_id' => '45',
                'footer_logo_id' => '-9',
            ],
        ]);
        self::assertSame(45, $sanitized['company']['header_logo_id']);
        self::assertSame(0, $sanitized['company']['footer_logo_id']);

        Settings::flush_cache();
        Functions\when('get_option')->justReturn([
            'company' => [
                'header_logo_id' => 77,
                'footer_logo_id' => 0,
            ],
        ]);
        Functions\when('wp_get_attachment_image')->alias(
            static fn (int $id): string => $id === 77 ? '<img src="https://cdn.example/media-logo.webp" alt="DRAVIT">' : ''
        );

        ob_start();
        drv_render_brand_logo('header', false);
        $header_html = (string) ob_get_clean();
        self::assertStringContainsString('media-logo.webp', $header_html);

        ob_start();
        drv_render_brand_logo('footer', true);
        $footer_html = (string) ob_get_clean();
        self::assertStringContainsString('logo-ivory@1x.webp', $footer_html);
        self::assertStringContainsString('loading="lazy"', $footer_html);
    }
}
