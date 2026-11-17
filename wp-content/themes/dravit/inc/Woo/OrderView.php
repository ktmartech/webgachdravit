<?php
declare(strict_types=1);

namespace Dravit\Woo;

defined('ABSPATH') || exit;

/**
 * View-model trang Đặt hàng thành công / Xem đơn (docs/04 §9, docs/18 §4.3, docs/07 §6).
 * Phần quyết định trạng thái là hàm thuần (`state()`, `timeline()`, `paymentSlot()`) để unit test C-5/C-13.
 */
final class OrderView {
    public const STATE_PENDING   = 'pending';   // A: Chờ xác nhận (cả COD & BACS).
    public const STATE_TRANSFER  = 'transfer';  // B: BACS đã chốt phí ship — Chờ chuyển khoản.
    public const STATE_FULFIL    = 'fulfil';    // C: Đang xử lý / Hoàn thành.
    public const STATE_CANCELLED = 'cancelled'; // D: Hủy / Hoàn tiền / Thất bại.

    /**
     * Hàm thuần: ma trận 4 trạng thái từ slug trạng thái (không `wc-`) + cổng thanh toán.
     */
    public static function state(string $status, string $payment_method): string {
        $status = (string) preg_replace('/^wc-/', '', $status);
        return match (true) {
            in_array($status, ['cancelled', 'refunded', 'failed'], true)    => self::STATE_CANCELLED,
            in_array($status, ['processing', 'completed'], true)            => self::STATE_FULFIL,
            $status === 'on-hold' && $payment_method === 'bacs'             => self::STATE_TRANSFER,
            default                                                         => self::STATE_PENDING,
        };
    }

    /**
     * Hàm thuần: khối thanh toán được phép render ở từng trạng thái.
     * `qr` chỉ khi BACS + on-hold (D-22, C-5, C-13); `none` tuyệt đối không xuất số TK/QR lên DOM.
     */
    public static function paymentSlot(string $status, string $payment_method): string {
        $status = (string) preg_replace('/^wc-/', '', $status);
        if ($payment_method === 'bacs' && $status === 'on-hold') {
            return 'qr';
        }
        if ($payment_method === 'bacs' && in_array($status, ['processing', 'completed'], true)) {
            return 'paid';
        }
        if ($payment_method === 'cod' && in_array($status, ['processing', 'completed'], true)) {
            return 'cod';
        }
        return 'none';
    }

    /**
     * Hàm thuần: 4 bước timeline với trạng thái `done | active | pending | cancelled`.
     *
     * @return array<int, array{key: string, title: string, sub: string, icon: string, state: string}>
     */
    public static function timeline(string $state, string $payment_method, string $status = ''): array {
        $status   = (string) preg_replace('/^wc-/', '', $status);
        $is_cod   = $payment_method === 'cod';
        $pay_sub  = $is_cod ? 'Thu tiền khi giao' : 'Chuyển khoản VietQR';
        $steps    = [
            ['key' => 'received', 'title' => 'Tiếp nhận', 'sub' => 'Đã ghi nhận đơn', 'icon' => 'check'],
            ['key' => 'confirm', 'title' => 'Xác nhận', 'sub' => 'Lô gạch · phí ship', 'icon' => 'phone-call'],
            ['key' => 'payment', 'title' => $is_cod ? 'Điều xe' : 'Thanh toán', 'sub' => $pay_sub, 'icon' => $is_cod ? 'package' : 'qr-code'],
            ['key' => 'delivery', 'title' => 'Giao hàng', 'sub' => 'Tận chân công trình', 'icon' => 'truck'],
        ];

        $active = match ($state) {
            self::STATE_TRANSFER => 2,
            self::STATE_FULFIL   => $status === 'completed' ? 4 : 3,
            self::STATE_CANCELLED => -1,
            default              => 1,
        };

        $out = [];
        foreach ($steps as $i => $step) {
            if ($active === -1) {
                $st = $i === 0 ? 'done' : 'cancelled';
            } else {
                $st = $i < $active ? 'done' : ($i === $active ? 'active' : 'pending');
            }
            $out[] = [
                'key'   => $step['key'],
                'title' => $step['title'],
                'sub'   => $step['sub'],
                'icon'  => $st === 'done' ? 'check' : $step['icon'],
                'state' => $st,
            ];
        }
        return $out;
    }

    /**
     * Hàm thuần: nhãn badge + mô tả hero theo trạng thái.
     *
     * @return array{badge: string, tone: string, title: string}
     */
    public static function heading(string $state, string $payment_method): array {
        return match ($state) {
            self::STATE_TRANSFER  => ['badge' => 'Chờ chuyển khoản', 'tone' => 'amber', 'title' => 'Đơn hàng đã được xác nhận'],
            self::STATE_FULFIL    => ['badge' => $payment_method === 'cod' ? 'Đang điều phối giao' : 'Đã nhận thanh toán', 'tone' => 'moss', 'title' => 'Đơn hàng đang được xử lý'],
            self::STATE_CANCELLED => ['badge' => 'Đã hủy', 'tone' => 'sale', 'title' => 'Đơn hàng đã hủy'],
            default               => ['badge' => 'Chờ xác nhận', 'tone' => 'amber', 'title' => 'Đặt hàng thành công'],
        };
    }

    /**
     * Gom dữ liệu hiển thị từ `WC_Order` (HPOS CRUD, 0 `get_post_meta`).
     *
     * @return array<string, mixed>
     */
    public static function fromOrder(\WC_Order $order): array {
        $id      = (int) $order->get_id();
        $status  = (string) $order->get_status();
        $pm      = (string) $order->get_payment_method();
        $state   = self::state($status, $pm);
        $ship    = 0.0;
        $has_fee = false;
        foreach ($order->get_items('shipping') as $item) {
            if ($item instanceof \WC_Order_Item_Shipping) {
                $ship += (float) $item->get_total() + (float) $item->get_total_tax();
                if ($item->get_method_id() !== QuoteShippingMethod::ID) {
                    $has_fee = true;
                }
            }
        }
        $has_fee = $has_fee || $ship > 0;

        $lines = [];
        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof \WC_Order_Item_Product) {
                continue;
            }
            $lines[] = self::lineFromItem($item, $order);
        }

        $ward_code = (string) $order->get_meta('_drv_ward_code');
        $state_key = (string) $order->get_billing_state();

        return [
            'id'            => $id,
            'number'        => OrderStatus::displayNumber($id),
            'transfer_ref'  => OrderStatus::transferRef($id, (string) $order->get_meta('_drv_transfer_ref')),
            'status'        => $status,
            'status_label'  => (string) wc_get_order_status_name($status),
            'payment'       => $pm,
            'payment_title' => (string) $order->get_payment_method_title(),
            'state'         => $state,
            'heading'       => self::heading($state, $pm),
            'timeline'      => self::timeline($state, $pm, $status),
            'payment_slot'  => self::paymentSlot($status, $pm),
            'created'       => $order->get_date_created(),
            'lines'         => $lines,
            'total_m2'      => (float) $order->get_meta('_drv_total_m2'),
            'total_boxes'   => (int) $order->get_meta('_drv_total_boxes'),
            'total_kg'      => (float) $order->get_meta('_drv_total_kg'),
            'subtotal'      => (int) round((float) $order->get_subtotal() + (float) $order->get_cart_tax()),
            'ship_total'    => (int) round($ship),
            'has_ship_fee'  => $has_fee,
            'total'         => (int) round((float) $order->get_total()),
            'name'          => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            'phone'         => (string) $order->get_billing_phone(),
            'email'         => (string) $order->get_billing_email(),
            'address'       => (string) $order->get_billing_address_1(),
            'ward'          => (string) $order->get_billing_city(),
            'ward_code'     => $ward_code,
            'province'      => (string) (Wards::provinces()[$state_key] ?? $state_key),
            'note'          => (string) $order->get_customer_note(),
            'vat'           => [
                'required' => in_array((string) $order->get_meta('_drv_vat_required'), ['1', 'yes'], true),
                'company'  => (string) $order->get_meta('_drv_vat_company'),
                'tax_id'   => (string) $order->get_meta('_drv_vat_tax_id'),
                'address'  => (string) $order->get_meta('_drv_vat_address'),
                'email'    => (string) $order->get_meta('_drv_vat_email'),
            ],
            'lot_codes'     => array_values(array_filter(array_map(static fn (array $l): string => (string) $l['lot'], $lines))),
            'ship_vehicle'  => (string) $order->get_meta('_drv_ship_vehicle'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function lineFromItem(\WC_Order_Item_Product $item, \WC_Order $order): array {
        $qty     = (int) $item->get_quantity();
        $unit    = (string) ($item->get_meta('_drv_unit') ?: 'viên');
        $boxes   = (int) $item->get_meta('_drv_boxes');
        $loose   = (int) $item->get_meta('_drv_loose_pieces');
        $m2      = (float) $item->get_meta('_drv_m2_qty');
        $kg      = (float) $item->get_meta('_drv_kg');
        $price   = (int) $item->get_meta('_drv_price_m2');
        $product = $item->get_product();

        $box_text = $boxes > 0 ? $boxes . ' thùng' . ($loose > 0 ? ' + ' . $loose . ' ' . $unit . ' lẻ' : '') : '';
        $inner    = $qty . ' ' . $unit . ($m2 > 0 ? ' · ' . drv_format_decimal($m2, 2) . ' m²' : '');
        $spec     = [$box_text !== '' ? sprintf('%s (%s)', $box_text, $inner) : $inner];
        if ($kg > 0) {
            $spec[] = drv_format_decimal($kg, 1) . ' kg';
        }
        $lot = trim((string) $item->get_meta('_drv_lot_code'));
        if ($lot !== '') {
            $spec[] = 'Lô ' . $lot;
        }

        $line_total = (int) round((float) $order->get_line_subtotal($item, true));
        $unit_price = $price > 0
            ? drv_format_vnd($price) . '/m²'
            : drv_format_vnd($qty > 0 ? (int) round($line_total / $qty) : 0) . '/' . $unit;

        return [
            'name'       => (string) $item->get_name(),
            'sku'        => $product instanceof \WC_Product ? (string) $product->get_sku() : '',
            'product'    => $product instanceof \WC_Product ? $product : null,
            'url'        => $product instanceof \WC_Product && $product->is_visible() ? (string) $product->get_permalink() : '',
            'spec'       => implode(' · ', $spec),
            'unit_price' => $unit_price,
            'total'      => $line_total,
            'lot'        => $lot,
        ];
    }
}
