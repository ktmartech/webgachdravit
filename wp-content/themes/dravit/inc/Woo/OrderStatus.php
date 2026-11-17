<?php
declare(strict_types=1);

namespace Dravit\Woo;

use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Trạng thái đơn tối thiểu cho trang Đặt hàng thành công (docs/18 §1.1, §1.4, docs/14 §7.1, D-22):
 * - Đăng ký `wc-cho-xac-nhan` (15 ký tự ≤ 20, C-15) làm trạng thái khởi tạo cho COD & BACS, trừ tồn kho ngay.
 * - Đổi nhãn `wc-on-hold` → "Chờ chuyển khoản".
 * - Mã đơn hiển thị `DRV-{id}`.
 * - Gỡ hướng dẫn chuyển khoản mặc định của BACS (trang cảm ơn + email) để khách không chuyển thiếu phí ship.
 *
 * Phase sau (9C - HPOS Ops): Metabox điều vận, Order Action "Xác nhận đơn & gửi email", Action Scheduler,
 * Email E1..E7, cột/bộ lọc danh sách đơn, tìm kiếm `DRV-{id}`.
 */
final class OrderStatus implements ServiceProviderInterface {
    /** Slug không tiền tố (`$order->has_status()`, `update_status()`). */
    public const PENDING_CONFIRM = 'cho-xac-nhan';

    /** Giới hạn cột `wc_orders.status` / `posts.post_status` (varchar(20)). */
    public const MAX_SLUG_LENGTH = 20;

    /**
     * Trạng thái tùy biến do theme đăng ký (slug đầy đủ có `wc-` → nhãn).
     *
     * @return array<string, string>
     */
    public static function custom(): array {
        return [
            'wc-' . self::PENDING_CONFIRM => 'Chờ xác nhận',
        ];
    }

    /**
     * Nhãn tiếng Việt cho trạng thái native (docs/18 §1.1).
     *
     * @return array<string, string>
     */
    public static function nativeLabels(): array {
        return [
            'wc-pending'    => 'Chờ thanh toán',
            'wc-on-hold'    => 'Chờ chuyển khoản',
            'wc-processing' => 'Đang xử lý',
            'wc-completed'  => 'Hoàn thành',
            'wc-cancelled'  => 'Đã hủy',
            'wc-refunded'   => 'Đã hoàn tiền',
            'wc-failed'     => 'Thất bại',
        ];
    }

    /**
     * Hàm thuần: chèn trạng thái tùy biến ngay sau `wc-pending` và áp nhãn tiếng Việt.
     *
     * @param array<string, string> $statuses
     * @return array<string, string>
     */
    public static function mergeStatuses(array $statuses): array {
        $out = [];
        foreach ($statuses as $key => $label) {
            $out[$key] = self::nativeLabels()[$key] ?? $label;
            if ($key === 'wc-pending') {
                $out += self::custom();
            }
        }
        return $out + self::custom();
    }

    /**
     * Hàm thuần: mã đơn hiển thị (`DRV-{id}`).
     */
    public static function displayNumber(int $order_id): string {
        return 'DRV-' . $order_id;
    }

    /**
     * Hàm thuần: nội dung chuyển khoản (`DRV{id}`, chỉ chữ in hoa + số — docs/18 §1.4).
     */
    public static function transferRef(int $order_id, string $saved = ''): string {
        $clean = (string) preg_replace('/[^A-Z0-9]/', '', strtoupper($saved));
        return $clean !== '' ? $clean : ('DRV' . $order_id);
    }

    public function register(): void {
        add_action('init', [$this, 'register_post_statuses'], 9);
        add_filter('wc_order_statuses', [self::class, 'mergeStatuses'], 20);
        add_filter('woocommerce_cod_process_payment_order_status', [$this, 'initial_status'], 20);
        add_filter('woocommerce_bacs_process_payment_order_status', [$this, 'initial_status'], 20);
        add_action('woocommerce_order_status_' . self::PENDING_CONFIRM, 'wc_maybe_reduce_stock_levels');
        add_filter('woocommerce_order_number', [$this, 'filter_order_number'], 20, 2);
        add_filter('woocommerce_valid_order_statuses_for_cancel', [$this, 'cancellable_statuses'], 20);
        add_action('wc_payment_gateways_initialized', [$this, 'unhook_bacs_instructions']);
    }

    public function register_post_statuses(): void {
        foreach (self::custom() as $slug => $label) {
            register_post_status($slug, [
                'label'                     => $label,
                'public'                    => false,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                /* translators: %s: số đơn */
                'label_count'               => _n_noop($label . ' <span class="count">(%s)</span>', $label . ' <span class="count">(%s)</span>', 'dravit'),
            ]);
        }
    }

    public function initial_status(): string {
        return self::PENDING_CONFIRM;
    }

    /**
     * @param string|int $number
     */
    public function filter_order_number($number, \WC_Order $order): string {
        unset($number);
        return self::displayNumber((int) $order->get_id());
    }

    /**
     * Khách được tự hủy đơn khi còn Chờ xác nhận (hoàn tồn kho qua `wc_maybe_increase_stock_levels`).
     *
     * @param array<int, string> $statuses
     * @return array<int, string>
     */
    public function cancellable_statuses(array $statuses): array {
        $statuses[] = self::PENDING_CONFIRM;
        return array_values(array_unique($statuses));
    }

    /**
     * docs/04 §7: gỡ `thankyou_page` + `email_instructions` mặc định của BACS (số TK + tổng chưa gồm ship).
     */
    public function unhook_bacs_instructions(\WC_Payment_Gateways $gateways): void {
        $bacs = $gateways->payment_gateways()['bacs'] ?? null;
        if (!$bacs instanceof \WC_Gateway_BACS) {
            return;
        }
        remove_action('woocommerce_thankyou_bacs', [$bacs, 'thankyou_page']);
        remove_action('woocommerce_email_before_order_table', [$bacs, 'email_instructions']);
    }
}
