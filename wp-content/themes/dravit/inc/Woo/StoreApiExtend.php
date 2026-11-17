<?php
declare(strict_types=1);

namespace Dravit\Woo;

use Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;
use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Mở rộng Store API `/wc/store/v1/cart` dưới namespace `dravit` (docs/15 §3.2, docs/04 §4.1):
 * Mini-cart & Giỏ hàng nhận đủ dữ liệu quy đổi `m² / thùng / kg / gợi ý xe` trong 1 request, 0 `wc-cart-fragments`.
 */
final class StoreApiExtend implements ServiceProviderInterface {
    public const NAMESPACE = 'dravit';

    public function register(): void {
        // Theme nạp sau `plugins_loaded`: hook `woocommerce_blocks_loaded` thường đã chạy xong.
        if (did_action('woocommerce_blocks_loaded')) {
            $this->register_endpoint_data();
            return;
        }
        add_action('woocommerce_blocks_loaded', [$this, 'register_endpoint_data']);
    }

    public function register_endpoint_data(): void {
        if (!function_exists('woocommerce_store_api_register_endpoint_data')) {
            return;
        }

        woocommerce_store_api_register_endpoint_data([
            'endpoint'        => CartItemSchema::IDENTIFIER,
            'namespace'       => self::NAMESPACE,
            'data_callback'   => [self::class, 'cart_item_data'],
            'schema_callback' => [self::class, 'cart_item_schema'],
            'schema_type'     => ARRAY_A,
        ]);

        woocommerce_store_api_register_endpoint_data([
            'endpoint'        => CartSchema::IDENTIFIER,
            'namespace'       => self::NAMESPACE,
            'data_callback'   => [self::class, 'cart_data'],
            'schema_callback' => [self::class, 'cart_schema'],
            'schema_type'     => ARRAY_A,
        ]);
    }

    /**
     * @param array<string, mixed> $cart_item
     * @return array<string, mixed>
     */
    public static function cart_item_data(array $cart_item): array {
        $product = $cart_item['data'] ?? null;
        if (!$product instanceof \WC_Product) {
            return [];
        }
        $line = CartPresenter::line(
            CartPresenter::snapshotFromProduct($product),
            (int) ($cart_item['quantity'] ?? 0),
            $cart_item
        );
        unset($line['line_total']);
        return $line;
    }

    /**
     * @return array<string, mixed>
     */
    public static function cart_data(): array {
        $lines = array_values(CartPresenter::fromCart());
        foreach ($lines as &$l) {
            unset($l['product']);
        }
        unset($l);
        $summary = CartPresenter::summarize($lines, CartPresenter::vehicleThresholds());
        $summary['quote_threshold_m2'] = CartPresenter::quoteThresholdM2();
        return $summary;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function cart_item_schema(): array {
        $num  = ['type' => 'number', 'context' => ['view', 'edit'], 'readonly' => true];
        $int  = ['type' => 'integer', 'context' => ['view', 'edit'], 'readonly' => true];
        $str  = ['type' => 'string', 'context' => ['view', 'edit'], 'readonly' => true];
        $list = ['type' => 'array', 'items' => ['type' => 'string'], 'context' => ['view', 'edit'], 'readonly' => true];

        return [
            'model'               => $str + ['description' => 'Pricing model (area_pack | piece | set | pack).'],
            'price_state'         => $str,
            'price_m2'            => $int + ['description' => 'Giá hiệu lực / m² (VND).'],
            'regular_price_m2'    => $int,
            'price_piece'         => $int + ['description' => 'Giá giao dịch / viên (VND).'],
            'price_box'           => $int,
            'unit_label'          => $str,
            'area_piece'          => $num,
            'pieces_per_box'      => $int,
            'm2_per_box'          => $num,
            'kg_per_box'          => $num,
            'kg_per_piece'        => $num,
            'm2_qty'              => $num,
            'boxes'               => $int,
            'loose_pieces'        => $int,
            'needed_for_next_box' => $int,
            'box_text'            => $str,
            'kg'                  => $num,
            'buy_units'           => $list,
            'default_buy_unit'    => $str,
            'round_mode'          => $str,
            'qty_min'             => $int,
            'qty_step'            => $int,
            'max_stock'           => ['type' => ['integer', 'null'], 'context' => ['view', 'edit'], 'readonly' => true],
            'shade'               => $str,
            'm2_input'            => $num,
            'waste_pct'           => $int,
            'input_unit'          => $str,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function cart_schema(): array {
        $num = ['type' => 'number', 'context' => ['view', 'edit'], 'readonly' => true];
        $int = ['type' => 'integer', 'context' => ['view', 'edit'], 'readonly' => true];
        $str = ['type' => 'string', 'context' => ['view', 'edit'], 'readonly' => true];

        return [
            'line_count'         => $int,
            'total_pieces'       => $int,
            'total_m2'           => $num,
            'total_boxes'        => $int,
            'total_loose_pieces' => $int,
            'total_kg'           => $num,
            'vehicle_hint'       => $str,
            'has_loose_items'    => ['type' => 'boolean', 'context' => ['view', 'edit'], 'readonly' => true],
            'shipping_notice'    => $str,
            'boxes_text'         => $str,
            'quote_threshold_m2' => $num,
        ];
    }
}
