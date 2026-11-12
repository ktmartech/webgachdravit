<?php
declare(strict_types=1);

namespace Dravit\Units;

defined('ABSPATH') || exit;

/**
 * Hợp đồng chung cho mọi mô hình đơn vị bán của DRAVIT (docs/04_Pricing_Units_Commerce.md §1.3).
 */
interface PricingModelInterface {
    public function sync_wc_prices(\WC_Product $product): void;

    public function primary_price(\WC_Product $product): PriceView;

    public function secondary_price(\WC_Product $product): ?PriceView;

    public function qty_summary(\WC_Product $product, int $qty): string;

    /**
     * @return array{min: int, step: int, max: int|null, buy_units: array<int, string>, default_unit: string, round_mode: string, pieces_per_box: int, unit_label: string}
     */
    public function quantity_args(\WC_Product $product): array;

    public function has_calculator(): bool;

    /**
     * @return array<string, mixed>
     */
    public function schema_price_spec(\WC_Product $product): array;
}

