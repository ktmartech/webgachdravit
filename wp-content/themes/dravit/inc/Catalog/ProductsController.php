<?php
declare(strict_types=1);

namespace Dravit\Catalog;

use Dravit\Core\ServiceProviderInterface;
use Dravit\Units\PriceState;

defined('ABSPATH') || exit;

/**
 * REST Endpoint `GET /wp-json/dravit/v1/products` (docs/15 §2.3, Step 7 `drv_viewed` & Step 8 BOQ Hydrate):
 * - Nhận `include=101,102,...` (tối đa 24 ID), giữ nguyên thứ tự truyền vào (mới xem nhất đứng đầu).
 * - Trả về mảng `items` (metadata chuẩn `PriceState::toCardData`) và tùy chọn `cards_html` từ `drv_ui_html('product-card')`.
 * - Chỉ xuất sản phẩm `publish` thuộc catalog hiển thị, gắn header `X-Robots-Tag: noindex, nofollow`.
 */
final class ProductsController implements ServiceProviderInterface {
    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        register_rest_route('dravit/v1', '/products', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'handle_get_products'],
            'permission_callback' => '__return_true',
            'args'                => [
                'include'    => [
                    'type'              => 'string',
                    'required'          => false,
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'cards_html' => [
                    'type'    => 'boolean',
                    'default' => false,
                ],
            ],
        ]);
    }

    public function handle_get_products(\WP_REST_Request $request): \WP_REST_Response {
        $raw_include = (string) $request->get_param('include');
        $want_html   = (bool) $request->get_param('cards_html');

        $ids = [];
        foreach (explode(',', $raw_include) as $part) {
            $pid = absint(trim($part));
            if ($pid > 0 && !in_array($pid, $ids, true)) {
                $ids[] = $pid;
            }
            if (count($ids) >= 24) {
                break;
            }
        }

        $items      = [];
        $cards_list = [];

        if ($ids !== [] && function_exists('wc_get_product')) {
            foreach ($ids as $pid) {
                $product = wc_get_product($pid);
                if (!$product instanceof \WC_Product || $product->get_status() !== 'publish') {
                    continue;
                }
                if ($product->get_catalog_visibility() === 'hidden') {
                    continue;
                }

                $card_data = PriceState::toCardData($product);
                $items[]   = $card_data;

                if ($want_html) {
                    $cards_list[] = drv_ui_html('product-card', [
                        'product'     => $product,
                        'context'     => 'related',
                        'heading_tag' => 'h3',
                    ]);
                }
            }
        }

        $resp = new \WP_REST_Response([
            'items'      => $items,
            'total'      => count($items),
            'cards_html' => implode("\n", $cards_list),
        ], 200);

        $resp->header('X-Robots-Tag', 'noindex, nofollow');
        $resp->header('Cache-Control', 'public, max-age=180');

        return $resp;
    }
}
