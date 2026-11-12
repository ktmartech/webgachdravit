<?php
declare(strict_types=1);

namespace Dravit\Units\PricingModel;

use Dravit\Units\PriceState;
use Dravit\Units\PriceView;
use Dravit\Units\PricingModelInterface;

defined('ABSPATH') || exit;

/**
 * Mô hình bán theo chiếc (`piece`) cho phụ kiện / thiết bị rời (docs/04 §1.2).
 */
class Piece implements PricingModelInterface {
    protected string $default_unit_label = 'chiếc';
    protected string $default_schema_code = 'C62';

    public function sync_wc_prices(\WC_Product $product): void {
        $product->update_meta_data('drv_qty_min', (string) max(1, (int) ($product->get_meta('drv_qty_min', true) ?: 1)));
        $product->update_meta_data('drv_qty_step', '1');
    }

    public function primary_price(\WC_Product $product): PriceView {
        $state = PriceState::resolve($product);
        $unit  = (string) ($product->get_meta('drv_unit_label', true) ?: $this->default_unit_label);
        if ($state === PriceState::QUOTE_ONLY) {
            return new PriceView(0, 0, $unit, 0, true);
        }

        $cur = (int) round((float) $product->get_price());
        $reg = (int) round((float) $product->get_regular_price());
        $pct = ($state === PriceState::SALE && $reg > $cur && $reg > 0)
            ? (int) round((1.0 - ($cur / $reg)) * 100.0)
            : 0;

        return new PriceView($cur, max($cur, $reg), $unit, $pct, false);
    }

    public function secondary_price(\WC_Product $product): ?PriceView {
        unset($product);
        return null;
    }

    public function qty_summary(\WC_Product $product, int $qty): string {
        $unit = (string) ($product->get_meta('drv_unit_label', true) ?: $this->default_unit_label);
        return max(1, $qty) . ' ' . $unit;
    }

    public function quantity_args(\WC_Product $product): array {
        $min  = max(1, (int) ($product->get_meta('drv_qty_min', true) ?: 1));
        $unit = (string) ($product->get_meta('drv_unit_label', true) ?: $this->default_unit_label);
        $max  = null;
        if ($product->managing_stock() && !$product->backorders_allowed() && $product->get_stock_quantity() !== null) {
            $max = max(0, (int) $product->get_stock_quantity());
        }

        return [
            'min'            => $min,
            'step'           => 1,
            'max'            => $max,
            'buy_units'      => ['vien'],
            'default_unit'   => 'vien',
            'round_mode'     => 'piece',
            'pieces_per_box' => 1,
            'unit_label'     => $unit,
        ];
    }

    public function has_calculator(): bool {
        return false;
    }

    public function schema_price_spec(\WC_Product $product): array {
        $primary = $this->primary_price($product);
        if ($primary->is_quote || $primary->amount <= 0) {
            return [];
        }

        return [
            '@type'             => 'UnitPriceSpecification',
            'price'             => $primary->amount,
            'priceCurrency'     => 'VND',
            'referenceQuantity' => [
                '@type'    => 'QuantitativeValue',
                'value'    => 1,
                'unitCode' => $this->default_schema_code,
            ],
        ];
    }
}
