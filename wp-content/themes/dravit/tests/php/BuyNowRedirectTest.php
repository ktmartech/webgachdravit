<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Units\WooProductHooks;
use PHPUnit\Framework\TestCase;

/**
 * Step 7 · P-7 / D-30: Nút "Mua ngay" (`drv_buy_now=1`) chuyển thẳng sang `/thanh-toan` kể cả khi tắt JS.
 */
final class BuyNowRedirectTest extends TestCase {
    private const DEFAULT_URL  = 'https://gachdravit.vn/san-pham/gach-calacatta-80x80';
    private const CHECKOUT_URL = 'https://gachdravit.vn/thanh-toan/';

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\stubs([
            'wp_parse_url' => static fn (string $url, int $component = -1): mixed => parse_url($url, $component),
        ]);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_buy_now_flag_redirects_to_checkout_without_trailing_slash(): void {
        $url = WooProductHooks::resolveBuyNowRedirect(self::DEFAULT_URL, ['drv_buy_now' => '1', 'quantity' => '18'], self::CHECKOUT_URL);
        self::assertSame('https://gachdravit.vn/thanh-toan', $url);
    }

    public function test_plain_add_to_cart_keeps_default_redirect(): void {
        self::assertSame(self::DEFAULT_URL, WooProductHooks::resolveBuyNowRedirect(self::DEFAULT_URL, ['quantity' => '18'], self::CHECKOUT_URL));
        self::assertSame(self::DEFAULT_URL, WooProductHooks::resolveBuyNowRedirect(self::DEFAULT_URL, ['drv_buy_now' => '0'], self::CHECKOUT_URL));
        self::assertSame(self::DEFAULT_URL, WooProductHooks::resolveBuyNowRedirect(self::DEFAULT_URL, ['drv_buy_now' => 'yes'], self::CHECKOUT_URL));
    }

    public function test_non_scalar_flag_or_empty_checkout_is_ignored(): void {
        self::assertSame(self::DEFAULT_URL, WooProductHooks::resolveBuyNowRedirect(self::DEFAULT_URL, ['drv_buy_now' => ['1']], self::CHECKOUT_URL));
        self::assertSame(self::DEFAULT_URL, WooProductHooks::resolveBuyNowRedirect(self::DEFAULT_URL, ['drv_buy_now' => '1'], '   '));
    }

    public function test_validation_errors_keep_customer_on_product_page(): void {
        // Lỗi qty_min / tồn kho => giữ khách ở PDP để thấy thông báo, không ném sang checkout.
        self::assertSame(
            self::DEFAULT_URL,
            WooProductHooks::resolveBuyNowRedirect(self::DEFAULT_URL, ['drv_buy_now' => '1'], self::CHECKOUT_URL, true)
        );
    }

    public function test_cart_item_calc_meta_is_whitelisted(): void {
        $meta = WooProductHooks::extractCartItemCalcMeta([
            'drv_m2_input'   => '25,5',
            'drv_waste_pct'  => '5',
            'drv_input_unit' => 'M2',
            'evil'           => '<script>',
        ]);
        self::assertSame(['drv_m2_input' => 25.5, 'drv_waste_pct' => 5, 'drv_input_unit' => 'm2'], $meta);

        self::assertSame([], WooProductHooks::extractCartItemCalcMeta([
            'drv_m2_input'   => '-3',
            'drv_waste_pct'  => '7',
            'drv_input_unit' => 'kg',
        ]));
    }
}
