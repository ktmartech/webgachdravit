<?php
declare(strict_types=1);

namespace Dravit\Woo;

use Dravit\Units\Calc;
use Dravit\Units\PriceState;

defined('ABSPATH') || exit;

/**
 * Một nguồn dữ liệu quy đổi duy nhất cho Giỏ hàng SSR, Mini-cart (Store API `extensions.dravit`),
 * Checkout `review-order.php` và Snapshot order item (docs/04 §4, docs/15 §3.2, docs/14 §7).
 *
 * Toàn bộ hàm tính là hàm thuần trên mảng `snapshot` để PHPUnit kiểm được không cần WooCommerce.
 */
final class CartPresenter {
    /**
     * Đóng băng thông số quy đổi của sản phẩm tại thời điểm đọc giỏ.
     *
     * @return array{model: string, price_state: string, price_m2: int, regular_price_m2: int, price_piece: int, unit_label: string, area_piece: float, pieces_per_box: int, m2_per_box: float, kg_per_box: float, buy_units: array<int, string>, default_buy_unit: string, round_mode: string, qty_min: int, qty_step: int, max_stock: int|null, shade: string, size_label: string}
     */
    public static function snapshotFromProduct(\WC_Product $product): array {
        $state    = PriceState::resolve($product);
        $cfg      = PriceState::readUnitConfig($product);
        $reg_m2   = max(0, (int) $product->get_meta('drv_price_m2', true));
        $sale_m2  = max(0, (int) $product->get_meta('drv_sale_price_m2', true));
        $eff_m2   = ($state === PriceState::SALE && $sale_m2 > 0 && $sale_m2 < $reg_m2) ? $sale_m2 : $reg_m2;
        $w_mm     = max(0, (int) $product->get_meta('drv_piece_w_mm', true));
        $h_mm     = max(0, (int) $product->get_meta('drv_piece_h_mm', true));
        $area     = Calc::pieceAreaM2($w_mm, $h_mm);
        $kg_box   = max(0.0, (float) $product->get_meta('drv_kg_per_box', true));
        $model    = (string) ($product->get_meta('drv_pricing_model', true) ?: 'area_pack');

        $max_stock = null;
        if ($product->managing_stock() && !$product->backorders_allowed() && $product->get_stock_quantity() !== null) {
            $max_stock = max(0, (int) $product->get_stock_quantity());
        }

        return [
            'model'            => $model,
            'price_state'      => $state,
            'price_m2'         => $state === PriceState::QUOTE_ONLY ? 0 : $eff_m2,
            'regular_price_m2' => $state === PriceState::QUOTE_ONLY ? 0 : $reg_m2,
            'price_piece'      => $state === PriceState::QUOTE_ONLY ? 0 : (int) round((float) $product->get_price()),
            'unit_label'       => $cfg['unit_label'],
            'area_piece'       => $area,
            'pieces_per_box'   => $cfg['pieces_per_box'],
            'm2_per_box'       => $area > 0.0 ? round($area * $cfg['pieces_per_box'], 4) : 0.0,
            'kg_per_box'       => $kg_box,
            'buy_units'        => $cfg['buy_units'],
            'default_buy_unit' => $cfg['default_buy_unit'],
            'round_mode'       => $cfg['round_mode'],
            'qty_min'          => $cfg['qty_min'],
            'qty_step'         => $cfg['qty_step'],
            'max_stock'        => $max_stock,
            'shade'            => trim((string) $product->get_meta('drv_shade', true)),
            'size_label'       => ($w_mm > 0 && $h_mm > 0) ? sprintf('%dx%d', (int) round($w_mm / 10), (int) round($h_mm / 10)) : '',
        ];
    }

    /**
     * Dữ liệu quy đổi của 1 dòng giỏ (khớp hợp đồng `extensions.dravit` của CartItemSchema).
     *
     * @param array<string, mixed> $snap      Kết quả `snapshotFromProduct()`.
     * @param array<string, mixed> $item_data Cart item data (`drv_m2_input`, `drv_waste_pct`, `drv_input_unit`).
     * @return array<string, mixed>
     */
    public static function line(array $snap, int $qty, array $item_data = []): array {
        $ppb        = max(1, (int) ($snap['pieces_per_box'] ?? 1));
        $area       = (float) ($snap['area_piece'] ?? 0.0);
        $kg_box     = (float) ($snap['kg_per_box'] ?? 0.0);
        $unit_label = (string) ($snap['unit_label'] ?? 'viên');
        $price_pc   = (int) ($snap['price_piece'] ?? 0);

        $calc = Calc::calcCartLine([
            'pieces'       => max(1, $qty),
            'piecesPerBox' => $ppb,
            'areaPiece'    => $area,
            'kgPerBox'     => $kg_box,
            'pricePiece'   => $price_pc,
            'priceM2'      => (int) ($snap['price_m2'] ?? 0),
            'unitLabel'    => $unit_label,
        ]);

        return [
            'model'              => (string) ($snap['model'] ?? 'area_pack'),
            'price_state'        => (string) ($snap['price_state'] ?? PriceState::PRICED),
            'price_m2'           => (int) ($snap['price_m2'] ?? 0),
            'regular_price_m2'   => (int) ($snap['regular_price_m2'] ?? 0),
            'price_piece'        => $calc['pricePiece'],
            'price_box'          => $calc['pricePiece'] * $ppb,
            'unit_label'         => $unit_label,
            'area_piece'         => $area,
            'pieces_per_box'     => $ppb,
            'm2_per_box'         => (float) ($snap['m2_per_box'] ?? round($area * $ppb, 4)),
            'kg_per_box'         => $kg_box,
            'kg_per_piece'       => $ppb > 0 ? round($kg_box / $ppb, 3) : 0.0,
            'm2_qty'             => round($calc['actualM2'], 2),
            'boxes'              => $calc['fullBoxes'],
            'loose_pieces'       => $calc['loosePieces'],
            'needed_for_next_box' => $calc['neededForNextBox'],
            'box_text'           => $calc['boxText'],
            'kg'                 => $calc['totalKg'],
            'line_total'         => $calc['lineTotalVnd'],
            'buy_units'          => array_values((array) ($snap['buy_units'] ?? ['m2', 'thung'])),
            'default_buy_unit'   => (string) ($snap['default_buy_unit'] ?? 'm2'),
            'round_mode'         => (string) ($snap['round_mode'] ?? 'box'),
            'qty_min'            => (int) ($snap['qty_min'] ?? $ppb),
            'qty_step'           => (int) ($snap['qty_step'] ?? $ppb),
            'max_stock'          => isset($snap['max_stock']) ? (int) $snap['max_stock'] : null,
            'shade'              => (string) ($snap['shade'] ?? ''),
            'm2_input'           => round((float) ($item_data['drv_m2_input'] ?? 0.0), 2),
            'waste_pct'          => (int) ($item_data['drv_waste_pct'] ?? 0),
            'input_unit'         => (string) ($item_data['drv_input_unit'] ?? ''),
        ];
    }

    /**
     * Tổng hợp cấp giỏ hàng (khớp `extensions.dravit` của CartSchema).
     *
     * @param array<int, array<string, mixed>> $lines
     * @param array<int, array<string, mixed>> $vehicle_thresholds
     * @return array{line_count: int, total_pieces: int, total_m2: float, total_boxes: int, total_loose_pieces: int, total_kg: float, vehicle_hint: string, has_loose_items: bool, shipping_notice: string, boxes_text: string}
     */
    public static function summarize(array $lines, array $vehicle_thresholds = Calc::DEFAULT_VEHICLE_THRESHOLDS, string $shipping_notice = ''): array {
        $pieces = 0;
        $m2     = 0.0;
        $boxes  = 0;
        $loose  = 0;
        $kg     = 0.0;

        foreach ($lines as $l) {
            $qty     = (int) ($l['qty'] ?? $l['quantity'] ?? 0);
            $pieces += $qty;
            $m2     += (float) ($l['m2_qty'] ?? 0.0);
            $boxes  += (int) ($l['boxes'] ?? 0);
            $loose  += (int) ($l['loose_pieces'] ?? 0);
            $kg     += (float) ($l['kg'] ?? 0.0);
        }

        $m2 = round($m2, 2);
        $kg = round($kg, 1);

        $boxes_text = $boxes . ' thùng';
        if ($loose > 0) {
            $boxes_text .= ' + ' . $loose . ' viên lẻ';
        }

        return [
            'line_count'         => count($lines),
            'total_pieces'       => $pieces,
            'total_m2'           => $m2,
            'total_boxes'        => $boxes,
            'total_loose_pieces' => $loose,
            'total_kg'           => $kg,
            'vehicle_hint'       => $kg > 0.0 ? Calc::resolveVehicleHint($kg, $vehicle_thresholds) : '',
            'has_loose_items'    => $loose > 0,
            'shipping_notice'    => $shipping_notice !== '' ? $shipping_notice : self::shippingLabel(),
            'boxes_text'         => $boxes_text,
        ];
    }

    /**
     * Ngưỡng gợi ý xe từ `drv_settings.order.vehicle_thresholds` (fallback hằng mặc định của Calc).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function vehicleThresholds(): array {
        $raw = function_exists('drv_setting') ? drv_setting('order.vehicle_thresholds', []) : [];
        if (!is_array($raw) || $raw === []) {
            return Calc::DEFAULT_VEHICLE_THRESHOLDS;
        }
        $clean = [];
        foreach ($raw as $row) {
            if (is_array($row) && isset($row['max_kg'], $row['label'])) {
                $clean[] = ['max_kg' => (float) $row['max_kg'], 'label' => (string) $row['label']];
            }
        }
        usort($clean, static fn (array $a, array $b): int => $a['max_kg'] <=> $b['max_kg']);
        return $clean !== [] ? $clean : Calc::DEFAULT_VEHICLE_THRESHOLDS;
    }

    public static function shippingLabel(): string {
        $label = function_exists('drv_setting') ? trim((string) drv_setting('order.shipping_label', '')) : '';
        return $label !== '' ? $label : 'Báo sau khi xác nhận đơn';
    }

    /**
     * Ngưỡng diện tích gợi ý chuyển giỏ sang Phiếu báo giá công trình (docs/04 §4, D-23).
     */
    public static function quoteThresholdM2(): float {
        $tiers = function_exists('drv_setting') ? drv_setting('quote.volume_tiers', []) : [];
        $min   = 0.0;
        if (is_array($tiers)) {
            foreach ($tiers as $t) {
                $v = is_array($t) ? (float) ($t['min_m2'] ?? 0) : 0.0;
                if ($v > 0.0 && ($min === 0.0 || $v < $min)) {
                    $min = $v;
                }
            }
        }
        return $min > 0.0 ? $min : 30.0;
    }

    /**
     * Đọc toàn bộ dòng giỏ WooCommerce hiện hành → [cart_item_key => line + product + qty].
     *
     * @return array<string, array<string, mixed>>
     */
    public static function fromCart(?\WC_Cart $cart = null): array {
        $cart = $cart ?? (function_exists('WC') && WC()->cart ? WC()->cart : null);
        if (!$cart instanceof \WC_Cart) {
            return [];
        }

        $out = [];
        foreach ($cart->get_cart() as $key => $item) {
            $product = $item['data'] ?? null;
            if (!$product instanceof \WC_Product) {
                continue;
            }
            $qty  = (int) ($item['quantity'] ?? 0);
            $line = self::line(self::snapshotFromProduct($product), $qty, $item);
            $line['qty']        = $qty;
            $line['key']        = (string) $key;
            $line['product']    = $product;
            $line['product_id'] = (int) ($item['product_id'] ?? $product->get_id());
            $line['line_total'] = (int) round((float) ($item['line_total'] ?? $line['line_total']));
            $out[(string) $key] = $line;
        }
        return $out;
    }
}
