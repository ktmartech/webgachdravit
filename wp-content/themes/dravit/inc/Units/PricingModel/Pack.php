<?php
declare(strict_types=1);

namespace Dravit\Units\PricingModel;

use Dravit\Units\PriceState;
use Dravit\Units\PriceView;

defined('ABSPATH') || exit;

/**
 * Mô hình bán theo tuýp / bao (`pack`) cho keo chà ron, vữa dự phòng Phase 2 (docs/04 §1.2).
 */
final class Pack extends Piece {
    protected string $default_unit_label = 'bao';
    protected string $default_schema_code = 'C62';

    public function secondary_price(\WC_Product $product): ?PriceView {
        if (PriceState::resolve($product) === PriceState::QUOTE_ONLY) {
            return null;
        }
        $weight = (float) $product->get_weight();
        $price  = (float) $product->get_price();
        if ($weight <= 0.0 || $price <= 0.0) {
            return null;
        }
        $per_kg = (int) round($price / $weight);
        return new PriceView($per_kg, $per_kg, 'kg', 0, false);
    }
}

