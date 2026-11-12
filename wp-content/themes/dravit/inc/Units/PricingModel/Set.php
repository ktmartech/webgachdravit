<?php
declare(strict_types=1);

namespace Dravit\Units\PricingModel;

defined('ABSPATH') || exit;

/**
 * Mô hình bán theo bộ (`set`) kế thừa `Piece` (docs/04 §1.2).
 */
final class Set extends Piece {
    protected string $default_unit_label = 'bộ';
    protected string $default_schema_code = 'SET';
}

