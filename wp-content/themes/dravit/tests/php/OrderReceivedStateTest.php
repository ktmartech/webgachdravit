<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Dravit\Core\Settings;
use Dravit\Woo\OrderView;
use PHPUnit\Framework\TestCase;

/**
 * Step 9 · Trang Đặt hàng thành công (docs/04 §9, docs/18 §4.3):
 * ma trận trạng thái A/B/C/D, timeline 4 bước, và guard C-5/C-13 — không xuất QR/số TK ở Chờ xác nhận.
 */
final class OrderReceivedStateTest extends TestCase {
    private const ACCOUNT_NO = '0938258668';

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Settings::flush_cache();
        Functions\stubs([
            'esc_html'   => static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'),
            'esc_attr'   => static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'),
            'get_option' => static fn (string $key, mixed $default = false): mixed => $key === Settings::OPTION_KEY
                ? ['bank' => ['bank_bin' => '970422', 'account_no' => self::ACCOUNT_NO, 'account_name' => 'CONG TY DRAVIT']]
                : $default,
        ]);
    }

    protected function tearDown(): void {
        Settings::flush_cache();
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function stateMatrix(): array {
        return [
            'COD vừa đặt'            => ['cho-xac-nhan', 'cod', OrderView::STATE_PENDING],
            'BACS vừa đặt (C-5)'     => ['wc-cho-xac-nhan', 'bacs', OrderView::STATE_PENDING],
            'BACS pending'           => ['pending', 'bacs', OrderView::STATE_PENDING],
            'COD on-hold (tải số)'   => ['on-hold', 'cod', OrderView::STATE_PENDING],
            'BACS đã chốt ship (C-6)' => ['on-hold', 'bacs', OrderView::STATE_TRANSFER],
            'COD đã chốt (C-7)'      => ['processing', 'cod', OrderView::STATE_FULFIL],
            'BACS đã nhận tiền'      => ['processing', 'bacs', OrderView::STATE_FULFIL],
            'Hoàn thành'             => ['completed', 'cod', OrderView::STATE_FULFIL],
            'Hủy (C-8)'              => ['cancelled', 'bacs', OrderView::STATE_CANCELLED],
            'Hoàn tiền'              => ['refunded', 'cod', OrderView::STATE_CANCELLED],
            'Thất bại'               => ['failed', 'bacs', OrderView::STATE_CANCELLED],
        ];
    }

    /**
     * @dataProvider stateMatrix
     */
    public function test_state_matrix(string $status, string $pm, string $expected): void {
        self::assertSame($expected, OrderView::state($status, $pm));
    }

    public function test_qr_slot_only_for_bacs_on_hold(): void {
        $statuses = ['cho-xac-nhan', 'wc-cho-xac-nhan', 'pending', 'on-hold', 'wc-on-hold', 'processing', 'completed', 'cancelled', 'refunded', 'failed'];
        foreach ($statuses as $status) {
            foreach (['bacs', 'cod', ''] as $pm) {
                $slot     = OrderView::paymentSlot($status, $pm);
                $is_allow = $pm === 'bacs' && in_array($status, ['on-hold', 'wc-on-hold'], true);
                self::assertSame($is_allow, $slot === 'qr', sprintf('%s/%s → %s', $status, $pm, $slot));
            }
        }
        self::assertSame('none', OrderView::paymentSlot('cho-xac-nhan', 'bacs'));
        self::assertSame('cod', OrderView::paymentSlot('processing', 'cod'));
        self::assertSame('paid', OrderView::paymentSlot('completed', 'bacs'));
    }

    public function test_timeline_progression(): void {
        $states = static fn (array $steps): array => array_column($steps, 'state');

        self::assertSame(['done', 'active', 'pending', 'pending'], $states(OrderView::timeline(OrderView::STATE_PENDING, 'bacs')));
        self::assertSame(['done', 'done', 'active', 'pending'], $states(OrderView::timeline(OrderView::STATE_TRANSFER, 'bacs')));
        self::assertSame(['done', 'done', 'done', 'active'], $states(OrderView::timeline(OrderView::STATE_FULFIL, 'cod', 'processing')));
        self::assertSame(['done', 'done', 'done', 'done'], $states(OrderView::timeline(OrderView::STATE_FULFIL, 'cod', 'completed')));
        self::assertSame(['done', 'cancelled', 'cancelled', 'cancelled'], $states(OrderView::timeline(OrderView::STATE_CANCELLED, 'cod')));

        $cod = OrderView::timeline(OrderView::STATE_PENDING, 'cod');
        self::assertSame('Điều xe', $cod[2]['title']);
        self::assertSame('qr-code', OrderView::timeline(OrderView::STATE_PENDING, 'bacs')[2]['icon']);
    }

    public function test_heading_badges(): void {
        self::assertSame('Chờ xác nhận', OrderView::heading(OrderView::STATE_PENDING, 'bacs')['badge']);
        self::assertSame('Chờ chuyển khoản', OrderView::heading(OrderView::STATE_TRANSFER, 'bacs')['badge']);
        self::assertSame('Đã hủy', OrderView::heading(OrderView::STATE_CANCELLED, 'cod')['badge']);
    }

    /**
     * C-5 / C-13: ở Chờ xác nhận, DOM không chứa ảnh VietQR, số tài khoản, và không gọi hook BankQR.
     */
    public function test_pending_payment_box_never_renders_qr_or_account(): void {
        Actions\expectDone('drv_order_payment_box')->never();

        $html = $this->renderPaymentBox($this->view('cho-xac-nhan', 'bacs'));

        self::assertStringContainsString('Chưa cần thanh toán', $html);
        self::assertStringContainsString('0912345678', $html);
        self::assertStringNotContainsString('img.vietqr.io', $html);
        self::assertStringNotContainsString(self::ACCOUNT_NO, $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('3 phút', $html);
    }

    public function test_transfer_payment_box_delegates_to_bankqr_hook(): void {
        Actions\expectDone('drv_order_payment_box')->once();

        $html = $this->renderPaymentBox($this->view('on-hold', 'bacs'));

        // Chưa gắn BankQR (phase sau) → fallback text, vẫn không lộ số TK.
        self::assertStringContainsString('Chờ chuyển khoản', $html);
        self::assertStringNotContainsString(self::ACCOUNT_NO, $html);
    }

    /**
     * @return array<string, mixed>
     */
    private function view(string $status, string $pm): array {
        return [
            'number'       => 'DRV-169',
            'transfer_ref' => 'DRV169',
            'state'        => OrderView::state($status, $pm),
            'payment'      => $pm,
            'payment_slot' => OrderView::paymentSlot($status, $pm),
            'phone'        => '0912345678',
            'total'        => 4690200,
        ];
    }

    /**
     * @param array<string, mixed> $view
     */
    private function renderPaymentBox(array $view): string {
        $args = ['view' => $view, 'order' => null];
        ob_start();
        (static function () use ($args): void {
            include DRV_DIR . '/template-parts/order/payment-box.php';
        })();
        return (string) ob_get_clean();
    }
}
