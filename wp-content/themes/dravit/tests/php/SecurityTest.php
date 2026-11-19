<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Core\Security;
use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_hooks_headers_rest_and_xmlrpc(): void {
        (new Security())->register();

        self::assertTrue(has_action('send_headers'));
        self::assertTrue(has_filter('xmlrpc_enabled'));
        self::assertTrue(has_filter('rest_endpoints'));
    }

    public function test_guest_loses_user_routes_but_keeps_others(): void {
        Functions\when('is_user_logged_in')->justReturn(false);
        $routes = [
            '/wp/v2/users'                  => [],
            '/wp/v2/users/(?P<id>[\d]+)'    => [],
            '/wp/v2/users/me'               => [],
            '/wp/v2/posts'                  => [],
            '/dravit/v1/search'             => [],
        ];

        $out = (new Security())->hide_user_endpoints_from_guests($routes);

        self::assertSame(['/wp/v2/posts', '/dravit/v1/search'], array_keys($out));
    }

    public function test_logged_in_user_keeps_all_routes(): void {
        Functions\when('is_user_logged_in')->justReturn(true);
        $routes = ['/wp/v2/users' => [], '/wp/v2/posts' => []];

        self::assertSame($routes, (new Security())->hide_user_endpoints_from_guests($routes));
    }

    public function test_header_whitelist_has_the_four_baseline_headers(): void {
        self::assertSame(
            ['X-Content-Type-Options', 'X-Frame-Options', 'Referrer-Policy', 'Permissions-Policy'],
            array_keys(Security::HEADERS)
        );
        self::assertSame('nosniff', Security::HEADERS['X-Content-Type-Options']);
    }
}
