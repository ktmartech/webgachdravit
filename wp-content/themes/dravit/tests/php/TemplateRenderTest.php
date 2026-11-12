<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class TemplateRenderTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        Functions\stubs([
            'esc_html'            => static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'),
            'esc_attr'            => static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'),
            'esc_url'             => static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8'),
            'esc_textarea'        => static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'),
            'wp_kses_post'        => static fn (mixed $v): string => (string) $v,
            'wpautop'             => static fn (mixed $v): string => '<p>' . trim((string) $v) . '</p>',
            'sanitize_html_class' => static fn (mixed $v): string => (string) preg_replace('/[^A-Za-z0-9_-]/', '', (string) $v),
            'sanitize_key'        => static fn (mixed $v): string => (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $v)),
            'untrailingslashit'   => static fn (string $v): string => rtrim($v, '/\\'),
            'home_url'            => static fn (string $path = ''): string => 'https://gachdravit.vn' . ($path !== '' ? '/' . ltrim($path, '/') : ''),
            'wp_rand'             => static fn (int $min = 0, int $max = 9999): int => $min + 1,
        ]);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_btn_primitive_renders_button_or_anchor_and_escapes_xss(): void {
        $btnHtml = drv_ui_html('btn', [
            'label'   => 'Nhận báo giá <script>alert(1)</script>',
            'variant' => 'moss',
            'size'    => 'sm',
            'action'  => 'dialog-open',
            'target'  => '#drv-quote-modal',
        ]);

        self::assertStringContainsString('<button type="button"', $btnHtml);
        self::assertStringContainsString('drv-btn drv-btn--moss drv-btn--sm', $btnHtml);
        self::assertStringContainsString('data-drv-action="dialog-open"', $btnHtml);
        self::assertStringNotContainsString('<script>', $btnHtml);
        self::assertStringContainsString('&lt;script&gt;', $btnHtml);

        $linkHtml = drv_ui_html('btn', [
            'label'      => 'Xem danh mục',
            'href'       => 'https://gachdravit.vn/gach-lat-nen',
            'icon_right' => 'arrow-right',
        ]);

        self::assertStringContainsString('<a href="https://gachdravit.vn/gach-lat-nen"', $linkHtml);
        self::assertStringContainsString('ph-arrow-right', $linkHtml);
    }

    public function test_product_card_renders_priced_sale_and_quote_states_without_wishlist(): void {
        $saleCard = drv_ui_html('product-card', [
            'product' => [
                'id'               => 101,
                'sku'              => 'DRV-8801M',
                'title'            => 'Gạch Marble Calacatta 80x80',
                'url'              => 'https://gachdravit.vn/san-pham/drv-8801m/',
                'price_m2'         => 385000,
                'regular_price_m2' => 450000,
                'price_piece'      => 246400,
                'pieces_per_box'   => 3,
                'stock_boxes'      => 46,
                'tags'             => ['80x80', 'Bóng kiếng'],
            ],
        ]);

        self::assertStringContainsString('drv-card--sale', $saleCard);
        self::assertStringContainsString('-14%', $saleCard);
        self::assertStringContainsString('385.000đ/m²', $saleCard);
        self::assertStringContainsString('246.400đ/viên', $saleCard);
        self::assertStringContainsString('href="https://gachdravit.vn/san-pham/drv-8801m"', $saleCard);
        self::assertStringNotContainsString('drv-card__wish', $saleCard);

        $quoteCard = drv_ui_html('product-card', [
            'product' => [
                'id'       => 104,
                'sku'      => 'DRV-SL1224',
                'title'    => 'Đá Nung Kết Armani Dark 120x240',
                'url'      => 'https://gachdravit.vn/san-pham/drv-sl1224',
                'price_m2' => 0,
            ],
        ]);

        self::assertStringContainsString('Liên hệ báo giá', $quoteCard);
        self::assertStringNotContainsString('0đ/m²', $quoteCard);
        self::assertStringNotContainsString('drv-card__wish', $quoteCard);
    }

    public function test_chip_copy_inline_and_steps_primitives(): void {
        $chip = drv_ui_html('chip', ['label' => 'Gạch 80x80', 'count' => 48, 'active' => true]);
        self::assertStringContainsString('aria-pressed="true"', $chip);
        self::assertStringContainsString('48', $chip);

        $copy = drv_ui_html('copy-inline', ['value' => 'DRV-8801M']);
        self::assertStringContainsString('data-drv-action="copy-text"', $copy);
        self::assertStringContainsString('data-drv-copy-text="DRV-8801M"', $copy);

        $steps = drv_ui_html('steps', [
            'steps' => [
                ['label' => 'Giỏ hàng', 'state' => 'done', 'url' => 'https://gachdravit.vn/gio-hang'],
                ['label' => 'Thanh toán', 'state' => 'current'],
            ],
        ]);
        self::assertStringContainsString('aria-current="step"', $steps);
        self::assertStringContainsString('is-done', $steps);
    }
}

