<?php
declare(strict_types=1);

namespace Dravit\Catalog;

defined('ABSPATH') || exit;

/**
 * Suy dẫn thuần (pure functions) các thuộc tính kỹ thuật & dải giá từ thông số gốc của SKU (docs/03 §3.2, docs/14 §2.4).
 */
final class AttributeDeriver {
    /**
     * Suy dẫn slug kích thước theo cm (cạnh nhỏ đứng trước, dấu `.` đổi thành `-`, VD: `75x300` -> `7-5x30`).
     */
    public static function deriveSizeSlug(int $w_mm, int $h_mm): string {
        if ($w_mm <= 0 || $h_mm <= 0) {
            return '';
        }

        $min_mm = min($w_mm, $h_mm);
        $max_mm = max($w_mm, $h_mm);

        return self::formatCmPart($min_mm, '-') . 'x' . self::formatCmPart($max_mm, '-');
    }

    /**
     * Suy dẫn nhãn hiển thị kích thước theo cm (VD: `75x300` -> `7,5x30 cm`).
     */
    public static function deriveSizeLabel(int $w_mm, int $h_mm): string {
        $short = self::deriveSizeShortLabel($w_mm, $h_mm);
        return $short !== '' ? $short . ' cm' : '';
    }

    /**
     * Suy dẫn nhãn ngắn kích thước cho card tag (VD: `75x300` -> `7,5x30`).
     */
    public static function deriveSizeShortLabel(int $w_mm, int $h_mm): string {
        if ($w_mm <= 0 || $h_mm <= 0) {
            return '';
        }

        $min_mm = min($w_mm, $h_mm);
        $max_mm = max($w_mm, $h_mm);

        return self::formatCmPart($min_mm, ',') . 'x' . self::formatCmPart($max_mm, ',');
    }

    /**
     * Cờ khổ lớn Big Slab: ít nhất 1 cạnh > 1200 mm (`120x240`, `160x320`... -> true; `60x120` -> false).
     */
    public static function isLargeFormat(int $w_mm, int $h_mm): bool {
        if ($w_mm <= 0 || $h_mm <= 0) {
            return false;
        }
        return max($w_mm, $h_mm) > 1200;
    }

    /**
     * Suy dẫn nhóm độ dày `pa_do-day` từ `drv_thickness_mm`.
     */
    public static function deriveThicknessSlug(float $thickness_mm): string {
        if ($thickness_mm <= 0.0) {
            return '';
        }
        if ($thickness_mm <= 6.0) {
            return 'mong';
        }
        if ($thickness_mm < 12.0) {
            return 'tieu-chuan';
        }
        if ($thickness_mm < 18.0) {
            return 'day';
        }
        return 'sieu-day-20mm';
    }

    /**
     * Suy dẫn nhóm độ hút nước ISO 10545-3 `pa_do-hut-nuoc` từ `drv_water_absorption` (%).
     */
    public static function deriveWaterAbsorptionSlug(float|int|string|null $raw): string {
        if ($raw === null || $raw === '') {
            return '';
        }

        if (is_string($raw)) {
            $clean = str_replace(',', '.', trim($raw));
            if (preg_match('/([0-9]+(?:\.[0-9]+)?)/', $clean, $matches) !== 1) {
                return '';
            }
            $val = (float) $matches[1];
        } else {
            $val = (float) $raw;
        }

        if ($val < 0.0) {
            return '';
        }
        if ($val <= 0.5) {
            return 'duoi-0-5';
        }
        if ($val <= 3.0) {
            return '0-5-3';
        }
        if ($val <= 6.0) {
            return '3-6';
        }
        if ($val <= 10.0) {
            return '6-10';
        }
        return 'tren-10';
    }

    /**
     * Suy dẫn dải giá ẩn `drv_price_band` từ giá m² hiệu lực (`<= 0` -> `gia-du-an`).
     */
    public static function derivePriceBandSlug(int $effective_price_m2): string {
        if ($effective_price_m2 <= 0) {
            return 'gia-du-an';
        }
        if ($effective_price_m2 < 200000) {
            return 'duoi-200k';
        }
        if ($effective_price_m2 <= 350000) {
            return '200k-350k';
        }
        if ($effective_price_m2 <= 600000) {
            return '350k-600k';
        }
        return 'tren-600k';
    }

    /**
     * Suy dẫn danh sách cờ `drv_flag` từ kệ trưng bày showroom.
     *
     * @return array<int, string>
     */
    public static function deriveFlagSlugs(string $showroom_shelf): array {
        return trim($showroom_shelf) !== '' ? ['showroom'] : [];
    }

    private static function formatCmPart(int $mm, string $decimal_sep): string {
        $cm        = round($mm / 10, 2);
        $formatted = number_format($cm, 2, '.', '');
        $trimmed   = rtrim(rtrim($formatted, '0'), '.');
        return $decimal_sep === '.' ? $trimmed : str_replace('.', $decimal_sep, $trimmed);
    }
}

