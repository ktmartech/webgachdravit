<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Core\Integrations\LiteSpeedCompat;
use PHPUnit\Framework\TestCase;

final class LiteSpeedCompatTest extends TestCase {
    private LiteSpeedCompat $sut;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        $this->sut = new LiteSpeedCompat();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        unset($_SERVER['REQUEST_URI']);
        parent::tearDown();
    }

    // ─── register() ─────────────────────────────────────────

    public function test_register_hooks_all_expected_actions_and_filters(): void {
        $this->sut->register();

        self::assertNotFalse(has_action('wp', [$this->sut, 'tag_or_exclude_current_request']));
        self::assertNotFalse(has_action('save_post_product', [$this->sut, 'on_save_product']));
        self::assertNotFalse(has_action('drv/catalog/synced', [$this->sut, 'on_catalog_synced']));
        self::assertNotFalse(has_filter('rest_post_dispatch', [$this->sut, 'maybe_set_rest_no_store']));
    }

    // ─── tag_or_exclude_current_request() ───────────────────

    public function test_admin_requests_are_skipped(): void {
        Functions\when('is_admin')->justReturn(true);

        $tags = [];
        Functions\when('has_action')->justReturn(true);
        Functions\when('do_action')->alias(static function (string $hook, mixed ...$args) use (&$tags): void {
            if ($hook === 'litespeed_tag_add') {
                $tags[] = $args[0];
            }
        });

        $this->sut->tag_or_exclude_current_request();

        self::assertEmpty($tags, 'Không gắn tag nào trên admin');
    }

    public function test_woo_transactional_pages_marked_no_cache(): void {
        if (!drv_is_woo_active()) {
            self::markTestSkipped('WooCommerce class not loaded in test env');
        }
        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_cart')->justReturn(true);
        Functions\when('is_checkout')->justReturn(false);
        Functions\when('is_account_page')->justReturn(false);

        $no_cache_reasons = [];
        Functions\when('has_action')->justReturn(true);
        Functions\when('do_action')->alias(static function (string $hook, mixed ...$args) use (&$no_cache_reasons): void {
            if ($hook === 'litespeed_control_set_nocache') {
                $no_cache_reasons[] = $args[0];
            }
        });

        $this->sut->tag_or_exclude_current_request();

        self::assertContains('transactional-woo-page', $no_cache_reasons);
    }

    public function test_boq_page_excluded_from_cache(): void {
        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_cart')->justReturn(false);
        Functions\when('is_checkout')->justReturn(false);
        Functions\when('is_account_page')->justReturn(false);
        $_SERVER['REQUEST_URI'] = '/danh-sach-bao-gia';

        $no_cache_reasons = [];
        Functions\when('has_action')->justReturn(true);
        Functions\when('do_action')->alias(static function (string $hook, mixed ...$args) use (&$no_cache_reasons): void {
            if ($hook === 'litespeed_control_set_nocache') {
                $no_cache_reasons[] = $args[0];
            }
        });

        $this->sut->tag_or_exclude_current_request();

        self::assertContains('personalized-page', $no_cache_reasons);
    }

    public function test_boq_page_with_trailing_slash_excluded(): void {
        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_cart')->justReturn(false);
        Functions\when('is_checkout')->justReturn(false);
        Functions\when('is_account_page')->justReturn(false);
        $_SERVER['REQUEST_URI'] = '/danh-sach-bao-gia/';

        $no_cache_reasons = [];
        Functions\when('has_action')->justReturn(true);
        Functions\when('do_action')->alias(static function (string $hook, mixed ...$args) use (&$no_cache_reasons): void {
            if ($hook === 'litespeed_control_set_nocache') {
                $no_cache_reasons[] = $args[0];
            }
        });

        $this->sut->tag_or_exclude_current_request();

        self::assertContains('personalized-page', $no_cache_reasons);
    }

    public function test_archive_product_gets_catalog_tag(): void {
        if (!drv_is_woo_active()) {
            self::markTestSkipped('WooCommerce class not loaded in test env');
        }
        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_cart')->justReturn(false);
        Functions\when('is_checkout')->justReturn(false);
        Functions\when('is_account_page')->justReturn(false);
        Functions\when('is_shop')->justReturn(true);
        Functions\when('is_product_taxonomy')->justReturn(false);
        Functions\when('is_product')->justReturn(false);
        $_SERVER['REQUEST_URI'] = '/gach';

        $tags = [];
        Functions\when('has_action')->justReturn(true);
        Functions\when('do_action')->alias(static function (string $hook, mixed ...$args) use (&$tags): void {
            if ($hook === 'litespeed_tag_add') {
                $tags[] = $args[0];
            }
        });

        $this->sut->tag_or_exclude_current_request();

        self::assertContains('drv_global_shell', $tags);
        self::assertContains('drv_catalog', $tags);
    }

    public function test_product_taxonomy_gets_catalog_tag(): void {
        if (!drv_is_woo_active()) {
            self::markTestSkipped('WooCommerce class not loaded in test env');
        }
        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_cart')->justReturn(false);
        Functions\when('is_checkout')->justReturn(false);
        Functions\when('is_account_page')->justReturn(false);
        Functions\when('is_shop')->justReturn(false);
        Functions\when('is_product_taxonomy')->justReturn(true);
        Functions\when('is_product')->justReturn(false);
        $_SERVER['REQUEST_URI'] = '/gach-phong-khach';

        $tags = [];
        Functions\when('has_action')->justReturn(true);
        Functions\when('do_action')->alias(static function (string $hook, mixed ...$args) use (&$tags): void {
            if ($hook === 'litespeed_tag_add') {
                $tags[] = $args[0];
            }
        });

        $this->sut->tag_or_exclude_current_request();

        self::assertContains('drv_global_shell', $tags);
        self::assertContains('drv_catalog', $tags);
    }

    public function test_single_product_gets_product_and_catalog_tags(): void {
        if (!drv_is_woo_active()) {
            self::markTestSkipped('WooCommerce class not loaded in test env');
        }
        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_cart')->justReturn(false);
        Functions\when('is_checkout')->justReturn(false);
        Functions\when('is_account_page')->justReturn(false);
        Functions\when('is_shop')->justReturn(false);
        Functions\when('is_product_taxonomy')->justReturn(false);
        Functions\when('is_product')->justReturn(true);
        Functions\when('get_queried_object_id')->justReturn(42);
        $_SERVER['REQUEST_URI'] = '/san-pham/gach-test';

        $tags = [];
        Functions\when('has_action')->justReturn(true);
        Functions\when('do_action')->alias(static function (string $hook, mixed ...$args) use (&$tags): void {
            if ($hook === 'litespeed_tag_add') {
                $tags[] = $args[0];
            }
        });

        $this->sut->tag_or_exclude_current_request();

        self::assertContains('drv_global_shell', $tags);
        self::assertContains('drv_prod_42', $tags);
        self::assertContains('drv_catalog', $tags);
    }

    public function test_generic_page_only_gets_global_shell(): void {
        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_cart')->justReturn(false);
        Functions\when('is_checkout')->justReturn(false);
        Functions\when('is_account_page')->justReturn(false);
        Functions\when('is_shop')->justReturn(false);
        Functions\when('is_product_taxonomy')->justReturn(false);
        Functions\when('is_product')->justReturn(false);
        $_SERVER['REQUEST_URI'] = '/gioi-thieu';

        $tags = [];
        Functions\when('has_action')->justReturn(true);
        Functions\when('do_action')->alias(static function (string $hook, mixed ...$args) use (&$tags): void {
            if ($hook === 'litespeed_tag_add') {
                $tags[] = $args[0];
            }
        });

        $this->sut->tag_or_exclude_current_request();

        self::assertContains('drv_global_shell', $tags);
        self::assertCount(1, $tags, 'Chỉ drv_global_shell, không gắn thêm');
    }

    // ─── on_save_product() ──────────────────────────────────

    public function test_save_product_purges_product_and_catalog_tags(): void {
        $purged = [];
        Functions\when('has_action')->justReturn(true);
        Functions\when('do_action')->alias(static function (string $hook, mixed ...$args) use (&$purged): void {
            if ($hook === 'litespeed_purge') {
                $purged[] = $args[0];
            }
        });

        $this->sut->on_save_product(99);

        self::assertContains('drv_prod_99', $purged);
        self::assertContains('drv_catalog', $purged);
    }

    public function test_save_product_skips_invalid_id(): void {
        $purged = [];
        Functions\when('has_action')->justReturn(true);
        Functions\when('do_action')->alias(static function (string $hook, mixed ...$args) use (&$purged): void {
            if ($hook === 'litespeed_purge') {
                $purged[] = $args[0];
            }
        });

        $this->sut->on_save_product(0);

        self::assertEmpty($purged);
    }

    // ─── on_catalog_synced() ────────────────────────────────

    public function test_catalog_synced_purges_catalog_tag(): void {
        $purged = [];
        Functions\when('has_action')->justReturn(true);
        Functions\when('do_action')->alias(static function (string $hook, mixed ...$args) use (&$purged): void {
            if ($hook === 'litespeed_purge') {
                $purged[] = $args[0];
            }
        });

        $this->sut->on_catalog_synced();

        self::assertContains('drv_catalog', $purged);
    }

    // ─── maybe_set_rest_no_store() ──────────────────────────

    /**
     * @dataProvider noStoreRoutesProvider
     */
    public function test_rest_no_store_routes(string $route): void {
        Functions\when('has_action')->justReturn(true);
        $no_cache_called = false;
        Functions\when('do_action')->alias(static function (string $hook) use (&$no_cache_called): void {
            if ($hook === 'litespeed_control_set_nocache') {
                $no_cache_called = true;
            }
        });

        $request = new \WP_REST_Request();
        $request->set_route($route);

        $response = new \WP_REST_Response();
        $server   = new \WP_REST_Server();

        $this->sut->maybe_set_rest_no_store($response, $server, $request);

        self::assertTrue($no_cache_called, 'LiteSpeed nocache phải được gọi cho ' . $route);
        $headers = $response->get_headers();
        self::assertArrayHasKey('Cache-Control', $headers);
        self::assertStringContainsString('no-store', $headers['Cache-Control']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function noStoreRoutesProvider(): array {
        return [
            '/quote/submit' => ['/dravit/v1/quote/submit'],
            '/lead'         => ['/dravit/v1/lead'],
        ];
    }

    public function test_rest_cacheable_route_not_modified(): void {
        Functions\when('has_action')->justReturn(true);

        $request = new \WP_REST_Request();
        $request->set_route('/dravit/v1/catalog');

        $response = new \WP_REST_Response();
        $server   = new \WP_REST_Server();

        $result = $this->sut->maybe_set_rest_no_store($response, $server, $request);

        self::assertSame($response, $result);
        self::assertEmpty($response->get_headers(), 'Không thêm header cho route cacheable');
    }

    // ─── Static helpers ─────────────────────────────────────

    public function test_add_cache_tag_calls_litespeed_hook(): void {
        $tags = [];
        Functions\when('has_action')->justReturn(true);
        Functions\when('do_action')->alias(static function (string $hook, mixed ...$args) use (&$tags): void {
            if ($hook === 'litespeed_tag_add') {
                $tags[] = $args[0];
            }
        });

        LiteSpeedCompat::add_cache_tag('drv_test');

        self::assertContains('drv_test', $tags);
    }

    public function test_add_cache_tag_noop_without_litespeed(): void {
        Functions\when('has_action')->justReturn(false);

        // Không throw, không gọi do_action — chỉ cần không fatal.
        LiteSpeedCompat::add_cache_tag('drv_test');
        self::assertTrue(true);
    }

    public function test_purge_tag_calls_litespeed_hook(): void {
        $purged = [];
        Functions\when('has_action')->justReturn(true);
        Functions\when('do_action')->alias(static function (string $hook, mixed ...$args) use (&$purged): void {
            if ($hook === 'litespeed_purge') {
                $purged[] = $args[0];
            }
        });

        LiteSpeedCompat::purge_tag('drv_catalog');

        self::assertContains('drv_catalog', $purged);
    }

    public function test_mark_no_cache_calls_litespeed_hook(): void {
        $reasons = [];
        Functions\when('has_action')->justReturn(true);
        Functions\when('do_action')->alias(static function (string $hook, mixed ...$args) use (&$reasons): void {
            if ($hook === 'litespeed_control_set_nocache') {
                $reasons[] = $args[0];
            }
        });

        LiteSpeedCompat::mark_no_cache('test-reason');

        self::assertContains('test-reason', $reasons);
    }
}
