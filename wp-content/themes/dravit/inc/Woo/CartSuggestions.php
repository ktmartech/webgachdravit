<?php
declare(strict_types=1);

namespace Dravit\Woo;

use Dravit\Units\PriceState;

defined('ABSPATH') || exit;

/**
 * Gợi ý trên trang Giỏ hàng (`04-cart.html`):
 * - `accessories()`: "Vật tư thi công đề xuất" (FBT) từ các SKU phụ kiện `drv_line = phu_kien` (keo dán, ke cân bằng...),
 *   số lượng tính theo định mức `drv_coverage_m2_per_unit` trên tổng m² gạch trong giỏ (làm tròn lên nguyên đơn vị).
 * - `relatedTiles()`: mẫu gạch phối đồng bộ cho phòng khác (cùng silo `gach`, loại các mã đã có trong giỏ).
 *
 * Tắt hoàn toàn bằng `drv_settings.features.cart_accessories = false`.
 */
final class CartSuggestions {
    public const MAX_ACCESSORIES = 4;

    /**
     * Số đơn vị phụ kiện cần cho `$total_m2` theo định mức phủ (>= 1).
     */
    public static function quantityFor(float $total_m2, float $coverage_m2_per_unit): int {
        if ($coverage_m2_per_unit <= 0.0 || $total_m2 <= 0.0) {
            return 1;
        }

        return max(1, (int) ceil($total_m2 / $coverage_m2_per_unit - 1e-9));
    }

    /**
     * @param array<int, int> $exclude_product_ids Mã sản phẩm đã có trong giỏ.
     * @return array<int, array{id: int, sku: string, name: string, url: string, img: string, unit: string, qty: int, unit_price: int, total: int, badge: string, per_unit_label: string}>
     */
    public static function accessories(float $total_m2, array $exclude_product_ids = []): array {
        if (!function_exists('wc_get_products') || !(bool) drv_setting('features.cart_accessories', true)) {
            return [];
        }

        $products = wc_get_products([
            'status'       => 'publish',
            'limit'        => self::MAX_ACCESSORIES * 2,
            'exclude'      => array_values(array_filter(array_map('intval', $exclude_product_ids))),
            'meta_key'     => 'drv_line',
            'meta_value'   => 'phu_kien',
            'orderby'      => 'menu_order title',
            'order'        => 'ASC',
            'stock_status' => 'instock',
        ]);

        $items = [];
        foreach ($products as $product) {
            if (!$product instanceof \WC_Product || !$product->is_purchasable()) {
                continue;
            }
            $unit_price = (int) round((float) $product->get_price());
            if ($unit_price <= 0) {
                continue;
            }

            $coverage = (float) $product->get_meta('drv_coverage_m2_per_unit', true);
            $unit     = trim((string) ($product->get_meta('drv_unit_label', true) ?: 'bao'));
            $pack     = trim((string) $product->get_meta('drv_pack_unit_label', true));
            $qty      = self::quantityFor($total_m2, $coverage);

            $badge = $qty . ' ' . $unit;
            if ($coverage > 0.0 && $total_m2 > 0.0) {
                $badge .= ' · Đủ lát ' . drv_format_decimal($qty * $coverage, 2) . ' m²';
            } elseif ($pack !== '') {
                $badge .= ' · ' . $pack;
            }

            $img_id = (int) $product->get_image_id();
            $img    = $img_id > 0 ? (string) wp_get_attachment_image_url($img_id, 'thumbnail') : '';
            if ($img === '') {
                $fallback = PriceState::resolveFallbackImages((int) $product->get_id());
                $img      = (string) ($fallback['primary'] ?? '');
            }

            $items[] = [
                'id'             => (int) $product->get_id(),
                'sku'            => (string) $product->get_sku(),
                'name'           => (string) $product->get_name(),
                'url'            => (string) $product->get_permalink(),
                'img'            => $img,
                'unit'           => $unit,
                'qty'            => $qty,
                'unit_price'     => $unit_price,
                'total'          => $unit_price * $qty,
                'badge'          => $badge,
                'per_unit_label' => $qty > 1 ? drv_format_vnd($unit_price) . '/' . $unit : '',
            ];

            if (count($items) >= self::MAX_ACCESSORIES) {
                break;
            }
        }

        return $items;
    }

    /**
     * @param array<int, int> $exclude_product_ids
     * @return array<int, int> Danh sách ID sản phẩm gạch gợi ý.
     */
    public static function relatedTileIds(array $exclude_product_ids = [], int $limit = 4): array {
        if (!function_exists('wc_get_products')) {
            return [];
        }

        $ids = wc_get_products([
            'status'       => 'publish',
            'limit'        => max(1, $limit),
            'exclude'      => array_values(array_filter(array_map('intval', $exclude_product_ids))),
            'return'       => 'ids',
            'orderby'      => 'date',
            'order'        => 'DESC',
            'stock_status' => 'instock',
            'meta_query'   => [
                'relation' => 'OR',
                ['key' => 'drv_line', 'value' => 'gach'],
                ['key' => 'drv_line', 'compare' => 'NOT EXISTS'],
            ],
        ]);

        return array_values(array_map('intval', is_array($ids) ? $ids : []));
    }
}
