<?php
declare(strict_types=1);

namespace Dravit\Catalog;

use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Tự động đồng bộ lại `gach-uu-dai`, `drv_price_band` & cache khi cron khuyến mãi hoặc tồn kho thay đổi (docs/03 §3.3, Test S-4).
 */
final class SaleSync implements ServiceProviderInterface {
    public function register(): void {
        add_action('wc_after_products_starting_sales', [$this, 'handle_sale_products'], 10, 1);
        add_action('wc_after_products_ending_sales', [$this, 'handle_sale_products'], 10, 1);
        add_action('woocommerce_product_set_stock_status', [$this, 'handle_stock_status'], 20, 1);
    }

    /**
     * @param mixed $product_ids
     */
    public function handle_sale_products(mixed $product_ids): void {
        if (!is_array($product_ids) || $product_ids === [] || !function_exists('wc_get_product')) {
            return;
        }

        $sync = new CollectionSync();
        foreach ($product_ids as $raw_id) {
            $id      = (int) $raw_id;
            $product = $id > 0 ? wc_get_product($id) : false;
            if ($product instanceof \WC_Product) {
                $sync->syncProduct($product);
            }
        }

        do_action('litespeed_purge', 'drv_catalog');
    }

    public function handle_stock_status(int $product_id): void {
        if ($product_id <= 0) {
            return;
        }

        $cur_ver = (int) get_option('drv_cache_ver', 1);
        update_option('drv_cache_ver', $cur_ver + 1, true);
        do_action('litespeed_purge', 'drv_catalog');
        do_action('litespeed_purge', 'drv_prod_' . $product_id);
    }
}

