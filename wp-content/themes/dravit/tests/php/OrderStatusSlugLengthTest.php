<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Dravit\Woo\OrderStatus;
use PHPUnit\Framework\TestCase;

/**
 * Step 9 · C-15 (docs/09 §3.7, docs/18 §1.1, D-22): mọi slug trạng thái đơn (tính cả `wc-`) ≤ 20 ký tự,
 * mã đơn hiển thị `DRV-{id}`, nội dung chuyển khoản `DRV{id}` chỉ gồm chữ in hoa + số.
 */
final class OrderStatusSlugLengthTest extends TestCase {
    public function test_custom_statuses_fit_varchar_20_with_prefix(): void {
        self::assertNotEmpty(OrderStatus::custom());
        foreach (array_keys(OrderStatus::custom()) as $slug) {
            self::assertStringStartsWith('wc-', $slug);
            self::assertLessThanOrEqual(OrderStatus::MAX_SLUG_LENGTH, strlen($slug), $slug);
            self::assertMatchesRegularExpression('/^wc-[a-z0-9-]+$/', $slug);
        }
    }

    public function test_pending_confirm_slug_is_15_chars(): void {
        $slug = 'wc-' . OrderStatus::PENDING_CONFIRM;
        self::assertSame('wc-cho-xac-nhan', $slug);
        self::assertSame(15, strlen($slug));
        self::assertSame('Chờ xác nhận', OrderStatus::custom()[$slug]);
    }

    public function test_merge_inserts_custom_after_pending_and_relabels_native(): void {
        $merged = OrderStatus::mergeStatuses([
            'wc-pending'    => 'Pending payment',
            'wc-processing' => 'Processing',
            'wc-on-hold'    => 'On hold',
            'wc-completed'  => 'Completed',
            'wc-cancelled'  => 'Cancelled',
        ]);

        self::assertSame(['wc-pending', 'wc-cho-xac-nhan', 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-cancelled'], array_keys($merged));
        self::assertSame('Chờ chuyển khoản', $merged['wc-on-hold']);
        self::assertSame('Đang xử lý', $merged['wc-processing']);
        foreach (array_keys($merged) as $slug) {
            self::assertLessThanOrEqual(OrderStatus::MAX_SLUG_LENGTH, strlen($slug), $slug);
        }
    }

    public function test_merge_appends_custom_when_pending_missing(): void {
        $merged = OrderStatus::mergeStatuses(['wc-processing' => 'Processing']);
        self::assertArrayHasKey('wc-cho-xac-nhan', $merged);
    }

    public function test_display_number_and_transfer_ref_format(): void {
        self::assertSame('DRV-202688', OrderStatus::displayNumber(202688));
        self::assertSame('DRV202688', OrderStatus::transferRef(202688));
        self::assertSame('DRV169', OrderStatus::transferRef(169, 'drv-169 '));
        self::assertMatchesRegularExpression('/^[A-Z0-9]+$/', OrderStatus::transferRef(5, '#DRV-5'));
    }
}
