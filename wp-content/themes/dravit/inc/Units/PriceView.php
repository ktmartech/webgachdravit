<?php
declare(strict_types=1);

namespace Dravit\Units;

defined('ABSPATH') || exit;

/**
 * Value Object chứa thông tin giá đã chuẩn hóa để hiển thị trên UI (docs/04 §1.3).
 */
final class PriceView {
    public function __construct(
        public readonly int $amount,
        public readonly int $regular,
        public readonly string $unit,
        public readonly int $discount_pct = 0,
        public readonly bool $is_quote = false
    ) {
    }
}

