<?php
declare(strict_types=1);

namespace Dravit\Woo;

defined('ABSPATH') || exit;

/**
 * Đóng băng thông số quy đổi vào Order Item & Order Meta lúc đặt hàng (docs/04 §4, docs/14 §7.2–§7.3, U-8).
 * 100% qua WC CRUD (`$item->add_meta_data()`, `$order->update_meta_data()`), tương thích HPOS.
 */
final class OrderSnapshot {
    /**
     * Hàm thuần: mảng meta snapshot của 1 dòng từ dữ liệu `CartPresenter::line()`.
     *
     * @param array<string, mixed> $line
     * @return array<string, int|float|string>
     */
    public static function itemMeta(array $line, int $qty): array {
        return [
            '_drv_model'          => (string) ($line['model'] ?? 'area_pack'),
            '_drv_unit'           => (string) ($line['unit_label'] ?? 'viên'),
            '_drv_price_m2'       => (int) ($line['price_m2'] ?? 0),
            '_drv_area_piece'     => (float) ($line['area_piece'] ?? 0.0),
            '_drv_pieces_per_box' => max(1, (int) ($line['pieces_per_box'] ?? 1)),
            '_drv_kg_per_box'     => (float) ($line['kg_per_box'] ?? 0.0),
            '_drv_m2_qty'         => round((float) ($line['m2_qty'] ?? 0.0), 2),
            '_drv_boxes'          => (int) ($line['boxes'] ?? 0),
            '_drv_loose_pieces'   => (int) ($line['loose_pieces'] ?? 0),
            '_drv_kg'             => round((float) ($line['kg'] ?? 0.0), 1),
            '_drv_m2_input'       => round((float) ($line['m2_input'] ?? 0.0), 2),
            '_drv_waste_pct'      => (int) ($line['waste_pct'] ?? 0),
            '_drv_qty_pieces'     => $qty,
        ];
    }

    /**
     * Hàm thuần: tổng hợp cấp đơn từ meta snapshot các dòng.
     *
     * @param array<int, array<string, mixed>> $items_meta
     * @return array{_drv_total_m2: float, _drv_total_boxes: int, _drv_total_kg: float}
     */
    public static function orderTotals(array $items_meta): array {
        $m2    = 0.0;
        $boxes = 0;
        $kg    = 0.0;
        foreach ($items_meta as $m) {
            $m2    += (float) ($m['_drv_m2_qty'] ?? 0.0);
            $boxes += (int) ($m['_drv_boxes'] ?? 0);
            $kg    += (float) ($m['_drv_kg'] ?? 0.0);
        }
        return [
            '_drv_total_m2'    => round($m2, 2),
            '_drv_total_boxes' => $boxes,
            '_drv_total_kg'    => round($kg, 1),
        ];
    }

    /**
     * `woocommerce_checkout_create_order_line_item`.
     *
     * @param array<string, mixed> $values Cart item.
     */
    public static function onCreateLineItem(\WC_Order_Item_Product $item, string $cart_item_key, array $values, \WC_Order $order): void {
        unset($cart_item_key, $order);
        $product = $values['data'] ?? null;
        if (!$product instanceof \WC_Product) {
            return;
        }
        $qty  = (int) ($values['quantity'] ?? $item->get_quantity());
        $line = CartPresenter::line(CartPresenter::snapshotFromProduct($product), $qty, $values);
        foreach (self::itemMeta($line, $qty) as $key => $value) {
            $item->add_meta_data($key, $value, true);
        }
    }

    /**
     * Tính tổng m²/thùng/kg của đơn từ các dòng đã snapshot (dùng lúc tạo đơn & khi Admin sửa dòng).
     */
    public static function applyOrderTotals(\WC_Order $order): void {
        $metas = [];
        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof \WC_Order_Item_Product) {
                continue;
            }
            $metas[] = [
                '_drv_m2_qty' => (float) $item->get_meta('_drv_m2_qty'),
                '_drv_boxes'  => (int) $item->get_meta('_drv_boxes'),
                '_drv_kg'     => (float) $item->get_meta('_drv_kg'),
            ];
        }
        // Meta lưu dạng chuỗi số chuẩn (wc_orders_meta.meta_value là longtext); đọc lại bằng (float)/(int).
        foreach (self::orderTotals($metas) as $key => $value) {
            $order->update_meta_data($key, wc_format_decimal($value));
        }
    }

    /**
     * Khóa snapshot nội bộ — ẩn khỏi màn sửa đơn Admin (`woocommerce_hidden_order_itemmeta`), thay bằng `displayRows()`.
     * `_drv_lot_code` KHÔNG ẩn: kho cần nhập mã lô khi xuất (C-10) cho tới khi có Metabox điều vận.
     *
     * @return array<int, string>
     */
    public static function hiddenKeys(): array {
        return array_keys(self::itemMeta([], 0));
    }

    /**
     * Dòng hiển thị tiếng Việt trong Email/Admin/Trang đơn (`woocommerce_order_item_get_formatted_meta_data`).
     *
     * @return array<int, array{key: string, label: string, value: string}>
     */
    public static function displayRows(\WC_Order_Item_Product $item, bool $admin = false): array {
        $meta = [];
        foreach (array_merge(self::hiddenKeys(), ['_drv_lot_code']) as $key) {
            $meta[$key] = $item->get_meta($key);
        }
        return self::rowsFromMeta($meta, (int) $item->get_quantity(), $admin);
    }

    /**
     * Hàm thuần: dựng các dòng hiển thị từ meta snapshot.
     * Khách & email: Đơn giá/m², Quy đổi, Mã lô. Admin thêm: Quy cách đóng thùng, Khách nhập (m² + hao hụt).
     *
     * @param array<string, mixed> $meta
     * @return array<int, array{key: string, label: string, value: string}>
     */
    public static function rowsFromMeta(array $meta, int $qty, bool $admin = false): array {
        $rows     = [];
        $price_m2 = (int) ($meta['_drv_price_m2'] ?? 0);
        $unit     = (string) (($meta['_drv_unit'] ?? '') ?: 'viên');
        $m2       = (float) ($meta['_drv_m2_qty'] ?? 0);
        $boxes    = (int) ($meta['_drv_boxes'] ?? 0);
        $loose    = (int) ($meta['_drv_loose_pieces'] ?? 0);
        $kg       = (float) ($meta['_drv_kg'] ?? 0);
        $ppb      = (int) ($meta['_drv_pieces_per_box'] ?? 0);
        $area     = (float) ($meta['_drv_area_piece'] ?? 0);
        $kg_box   = (float) ($meta['_drv_kg_per_box'] ?? 0);
        $m2_input = (float) ($meta['_drv_m2_input'] ?? 0);
        $waste    = (int) ($meta['_drv_waste_pct'] ?? 0);
        $lot      = trim((string) ($meta['_drv_lot_code'] ?? ''));

        if ($price_m2 > 0) {
            $rows[] = ['key' => 'drv_price_m2', 'label' => 'Đơn giá / m²', 'value' => drv_format_vnd($price_m2) . '/m²'];
        }
        if ($boxes > 0 || $loose > 0 || $m2 > 0) {
            $box_text = $boxes . ' thùng' . ($loose > 0 ? ' + ' . $loose . ' ' . $unit . ' lẻ' : '');
            $parts    = [$qty . ' ' . $unit, $box_text];
            if ($m2 > 0) {
                $parts[] = drv_format_decimal($m2, 2) . ' m²';
            }
            if ($kg > 0) {
                $parts[] = drv_format_decimal($kg, 1) . ' kg';
            }
            $rows[] = ['key' => 'drv_conversion', 'label' => 'Quy đổi', 'value' => implode(' · ', $parts)];
        }
        if ($admin && $ppb > 0) {
            $spec = [sprintf('1 thùng = %d %s', $ppb, $unit)];
            if ($area > 0) {
                $spec[] = drv_format_decimal($area * $ppb, 2) . ' m²';
            }
            if ($kg_box > 0) {
                $spec[] = drv_format_decimal($kg_box, 1) . ' kg';
            }
            if ($area > 0) {
                $spec[] = drv_format_decimal($area, 4) . ' m²/' . $unit;
            }
            $rows[] = ['key' => 'drv_pack_spec', 'label' => 'Quy cách', 'value' => implode(' · ', $spec)];
        }
        if ($admin && $m2_input > 0) {
            $rows[] = [
                'key'   => 'drv_customer_input',
                'label' => 'Khách nhập',
                'value' => drv_format_decimal($m2_input, 2) . ' m²' . ($waste > 0 ? ' + hao hụt ' . $waste . '%' : ''),
            ];
        }
        if ($lot !== '') {
            $rows[] = ['key' => 'drv_lot_code', 'label' => 'Mã lô xuất kho', 'value' => $lot];
        }
        return $rows;
    }
}
