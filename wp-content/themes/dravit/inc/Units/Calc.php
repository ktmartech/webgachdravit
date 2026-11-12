<?php
declare(strict_types=1);

namespace Dravit\Units;

defined('ABSPATH') || exit;

/**
 * Lõi tính toán quy đổi đơn vị ngành gạch duy nhất phía Server (docs/04 §3.3, docs/15 §4.1, ADR D-20, D-24).
 * Đối xứng 100% đầu ra với `assets/src/js/utils/calc.js` qua `tests/fixtures/calc-vectors.json`.
 */
final class Calc {
    public const EPSILON = 1.0e-9;

    /** @var array<int, array{min_m2: float|int, rate: float|int}> */
    public const DEFAULT_VOLUME_TIERS = [
        ['min_m2' => 100, 'rate' => 8],
        ['min_m2' => 30,  'rate' => 5],
        ['min_m2' => 0,   'rate' => 0],
    ];

    /** @var array<int, array{max_kg: float|int, label: string}> */
    public const DEFAULT_VEHICLE_THRESHOLDS = [
        ['max_kg' => 1000,   'label' => 'Xe tải nhẹ 1 tấn'],
        ['max_kg' => 2500,   'label' => 'Xe tải 2,5 tấn'],
        ['max_kg' => 5000,   'label' => 'Xe tải 5 tấn'],
        ['max_kg' => 999999, 'label' => 'Xe tải 8 tấn / chia chuyến'],
    ];

    /**
     * Diện tích 1 viên gạch (m²) từ kích thước rộng × dài (mm).
     */
    public static function pieceAreaM2(int|float $width_mm, int|float $height_mm): float {
        $w = (float) $width_mm;
        $h = (float) $height_mm;
        if ($w <= 0.0 || $h <= 0.0) {
            return 0.0;
        }
        return round(($w * $h) / 1_000_000.0, 6);
    }

    /**
     * Giá 1 viên gạch (VND nguyên), khớp `_regular_price` / `_sale_price` của WooCommerce.
     */
    public static function pricePerPiece(int|float $price_m2, float $area_piece): int {
        $p = (float) $price_m2;
        if ($p <= 0.0 || $area_piece <= 0.0) {
            return 0;
        }
        return (int) round($p * $area_piece);
    }

    /**
     * Chuẩn hóa số lượng viên theo cấu hình D-24 của từng SKU.
     *
     * @param array{piecesPerBox?: int, roundMode?: string, qtyMin?: int} $options
     */
    public static function normalizePieces(int|float $raw_pieces, array $options = []): int {
        $ppb        = max(1, (int) ($options['piecesPerBox'] ?? 1));
        $min_p      = max(1, (int) ($options['qtyMin'] ?? 1));
        $round_mode = (string) ($options['roundMode'] ?? 'box');

        $pieces = max($min_p, (int) ceil(((float) $raw_pieces) - self::EPSILON));
        if ($round_mode === 'box') {
            $pieces = (int) (ceil(($pieces - self::EPSILON) / $ppb) * $ppb);
        }
        return $pieces;
    }

    /**
     * Tách tổng số viên thành thùng nguyên + viên lẻ + chuỗi hiển thị chuẩn tiếng Việt.
     *
     * @return array{fullBoxes: int, loosePieces: int, boxText: string}
     */
    public static function boxBreakdown(int $pieces, int $pieces_per_box, string $unit_label = 'viên'): array {
        $qty          = max(0, $pieces);
        $ppb          = max(1, $pieces_per_box);
        $label        = $unit_label !== '' ? $unit_label : 'viên';
        $full_boxes   = intdiv($qty, $ppb);
        $loose_pieces = $qty % $ppb;

        $box_text = $full_boxes . ' thùng';
        if ($loose_pieces > 0) {
            $box_text = $full_boxes > 0
                ? sprintf('%d thùng + %d %s lẻ', $full_boxes, $loose_pieces, $label)
                : sprintf('%d %s lẻ', $loose_pieces, $label);
        }

        return [
            'fullBoxes'   => $full_boxes,
            'loosePieces' => $loose_pieces,
            'boxText'     => $box_text,
        ];
    }

    /**
     * Tổng hợp đầy đủ chỉ số đầu ra từ số viên nguyên đã chốt.
     *
     * @param array<string, mixed> $args
     * @return array{pieces: int, fullBoxes: int, loosePieces: int, boxText: string, needM2: float, actualM2: float, totalKg: float, pricePiece: int, totalVnd: int, isQuote: bool}
     */
    public static function buildCalcResult(array $args): array {
        $pieces               = max(0, (int) ($args['pieces'] ?? 0));
        $need_m2              = (float) ($args['needM2'] ?? 0.0);
        $area_piece           = (float) ($args['areaPiece'] ?? 0.0);
        $ppb                  = max(1, (int) ($args['piecesPerBox'] ?? 1));
        $kg_box               = max(0.0, (float) ($args['kgPerBox'] ?? 0.0));
        $price_m2             = (float) ($args['priceM2'] ?? 0.0);
        $explicit_price_piece = $args['pricePiece'] ?? null;
        $unit_label           = (string) ($args['unitLabel'] ?? 'viên');

        $is_quote = !($price_m2 > 0.0);
        if ($is_quote) {
            $resolved_price_piece = 0;
        } elseif ($explicit_price_piece !== null && (float) $explicit_price_piece > 0.0) {
            $resolved_price_piece = (int) round((float) $explicit_price_piece);
        } else {
            $resolved_price_piece = self::pricePerPiece($price_m2, $area_piece);
        }

        $actual_m2    = round($pieces * $area_piece, 4);
        $kg_per_piece = $ppb > 0 ? ($kg_box / $ppb) : 0.0;
        $total_kg     = round($pieces * $kg_per_piece, 1);
        $total_vnd    = $resolved_price_piece > 0 ? ($pieces * $resolved_price_piece) : 0;
        $breakdown    = self::boxBreakdown($pieces, $ppb, $unit_label);

        return [
            'pieces'      => $pieces,
            'fullBoxes'   => $breakdown['fullBoxes'],
            'loosePieces' => $breakdown['loosePieces'],
            'boxText'     => $breakdown['boxText'],
            'needM2'      => round($need_m2, 4),
            'actualM2'    => $actual_m2,
            'totalKg'     => $total_kg,
            'pricePiece'  => $resolved_price_piece,
            'totalVnd'    => $total_vnd,
            'isQuote'     => $is_quote,
        ];
    }

    /**
     * 1. Nhập theo diện tích m² (cộng % hao hụt và tùy chọn làm tròn chẵn thùng).
     *
     * @param array<string, mixed> $params
     * @return array{pieces: int, fullBoxes: int, loosePieces: int, boxText: string, needM2: float, actualM2: float, totalKg: float, pricePiece: int, totalVnd: int, isQuote: bool}
     */
    public static function calcFromM2(array $params): array {
        $net_m2           = max(0.0, (float) ($params['areaM2'] ?? 0.0));
        $waste            = max(0.0, (float) ($params['wastePct'] ?? 0.0));
        $force_round_box  = $params['forceRoundBox'] ?? null;
        $area_piece       = (float) ($params['areaPiece'] ?? 0.0);
        $ppb              = max(1, (int) ($params['piecesPerBox'] ?? 1));
        $kg_box           = max(0.0, (float) ($params['kgPerBox'] ?? 0.0));
        $price_m2         = (float) ($params['priceM2'] ?? 0.0);
        $price_piece      = $params['pricePiece'] ?? null;
        $round_mode       = (string) ($params['roundMode'] ?? 'box');
        $qty_min          = max(1, (int) ($params['qtyMin'] ?? 1));
        $unit_label       = (string) ($params['unitLabel'] ?? 'viên');

        $need_m2              = $net_m2 * (1.0 + ($waste / 100.0));
        $effective_round_mode = ($round_mode === 'box' || $force_round_box === true) ? 'box' : 'piece';
        $raw_pieces           = ($need_m2 > 0.0 && $area_piece > 0.0) ? ($need_m2 / $area_piece) : $qty_min;
        $pieces               = self::normalizePieces($raw_pieces, [
            'piecesPerBox' => $ppb,
            'roundMode'    => $effective_round_mode,
            'qtyMin'       => $qty_min,
        ]);

        return self::buildCalcResult([
            'pieces'       => $pieces,
            'needM2'       => $need_m2,
            'areaPiece'    => $area_piece,
            'piecesPerBox' => $ppb,
            'kgPerBox'     => $kg_box,
            'priceM2'      => $price_m2,
            'pricePiece'   => $price_piece,
            'unitLabel'    => $unit_label,
        ]);
    }

    /**
     * 2. Nhập trực tiếp theo số thùng nguyên.
     *
     * @param array<string, mixed> $params
     * @return array{pieces: int, fullBoxes: int, loosePieces: int, boxText: string, needM2: float, actualM2: float, totalKg: float, pricePiece: int, totalVnd: int, isQuote: bool}
     */
    public static function calcFromBoxes(array $params): array {
        $boxes_in    = (float) ($params['boxes'] ?? 1.0);
        $area_piece  = (float) ($params['areaPiece'] ?? 0.0);
        $ppb         = max(1, (int) ($params['piecesPerBox'] ?? 1));
        $kg_box      = max(0.0, (float) ($params['kgPerBox'] ?? 0.0));
        $price_m2    = (float) ($params['priceM2'] ?? 0.0);
        $price_piece = $params['pricePiece'] ?? null;
        $qty_min     = max(1, (int) ($params['qtyMin'] ?? 1));
        $unit_label  = (string) ($params['unitLabel'] ?? 'viên');

        $base_boxes  = $boxes_in > 0.0 ? $boxes_in : 1.0;
        $valid_boxes = max(1, (int) ceil($base_boxes - self::EPSILON));
        $pieces      = self::normalizePieces($valid_boxes * $ppb, [
            'piecesPerBox' => $ppb,
            'roundMode'    => 'box',
            'qtyMin'       => $qty_min,
        ]);

        return self::buildCalcResult([
            'pieces'       => $pieces,
            'needM2'       => $pieces * $area_piece,
            'areaPiece'    => $area_piece,
            'piecesPerBox' => $ppb,
            'kgPerBox'     => $kg_box,
            'priceM2'      => $price_m2,
            'pricePiece'   => $price_piece,
            'unitLabel'    => $unit_label,
        ]);
    }

    /**
     * 3. Nhập trực tiếp theo số viên / tấm.
     *
     * @param array<string, mixed> $params
     * @return array{pieces: int, fullBoxes: int, loosePieces: int, boxText: string, needM2: float, actualM2: float, totalKg: float, pricePiece: int, totalVnd: int, isQuote: bool}
     */
    public static function calcFromPieces(array $params): array {
        $raw_in      = (float) ($params['pieces'] ?? 1.0);
        $area_piece  = (float) ($params['areaPiece'] ?? 0.0);
        $ppb         = max(1, (int) ($params['piecesPerBox'] ?? 1));
        $kg_box      = max(0.0, (float) ($params['kgPerBox'] ?? 0.0));
        $price_m2    = (float) ($params['priceM2'] ?? 0.0);
        $price_piece = $params['pricePiece'] ?? null;
        $round_mode  = (string) ($params['roundMode'] ?? 'piece');
        $qty_min     = max(1, (int) ($params['qtyMin'] ?? 1));
        $unit_label  = (string) ($params['unitLabel'] ?? 'viên');

        $rounded_in = max(1, (int) round($raw_in > 0.0 ? $raw_in : 1.0));
        $pieces     = self::normalizePieces($rounded_in, [
            'piecesPerBox' => $ppb,
            'roundMode'    => $round_mode,
            'qtyMin'       => $qty_min,
        ]);

        return self::buildCalcResult([
            'pieces'       => $pieces,
            'needM2'       => $pieces * $area_piece,
            'areaPiece'    => $area_piece,
            'piecesPerBox' => $ppb,
            'kgPerBox'     => $kg_box,
            'priceM2'      => $price_m2,
            'pricePiece'   => $price_piece,
            'unitLabel'    => $unit_label,
        ]);
    }

    /**
     * 4. Tính dòng Giỏ hàng / Đơn hàng từ số viên nguyên đã lưu trong WooCommerce.
     *
     * @param array<string, mixed> $params
     * @return array{pieces: int, fullBoxes: int, loosePieces: int, neededForNextBox: int, boxText: string, actualM2: float, totalKg: float, pricePiece: int, lineTotalVnd: int}
     */
    public static function calcCartLine(array $params): array {
        $qty_in      = (int) ($params['pieces'] ?? 1);
        $qty         = max(1, $qty_in);
        $ppb         = max(1, (int) ($params['piecesPerBox'] ?? 1));
        $area_piece  = (float) ($params['areaPiece'] ?? 0.0);
        $kg_box      = max(0.0, (float) ($params['kgPerBox'] ?? 0.0));
        $price_piece = (float) ($params['pricePiece'] ?? 0.0);
        $price_m2    = (float) ($params['priceM2'] ?? 0.0);
        $unit_label  = (string) ($params['unitLabel'] ?? 'viên');

        $unit_vnd           = $price_piece > 0.0 ? (int) round($price_piece) : self::pricePerPiece($price_m2, $area_piece);
        $actual_m2          = round($qty * $area_piece, 4);
        $kg_per_piece       = $ppb > 0 ? ($kg_box / $ppb) : 0.0;
        $total_kg           = round($qty * $kg_per_piece, 1);
        $breakdown          = self::boxBreakdown($qty, $ppb, $unit_label);
        $needed_for_next    = $breakdown['loosePieces'] > 0 ? ($ppb - $breakdown['loosePieces']) : 0;

        return [
            'pieces'           => $qty,
            'fullBoxes'        => $breakdown['fullBoxes'],
            'loosePieces'      => $breakdown['loosePieces'],
            'neededForNextBox' => $needed_for_next,
            'boxText'          => $breakdown['boxText'],
            'actualM2'         => $actual_m2,
            'totalKg'          => $total_kg,
            'pricePiece'       => $unit_vnd,
            'lineTotalVnd'     => $qty * $unit_vnd,
        ];
    }

    /**
     * 5. Tính dòng hạng mục dự toán BOQ (`0 m² -> 0 thùng`; `> 0 m²` luôn quy chẵn thùng nguyên).
     *
     * @param array<string, mixed> $params
     * @return array{pieces: int, fullBoxes: int, loosePieces: int, boxText: string, needM2: float, actualM2: float, totalKg: float, pricePiece: int, totalVnd: int, isQuote: bool}
     */
    public static function calcBoqSlot(array $params): array {
        $net_m2   = max(0.0, (float) ($params['targetM2'] ?? 0.0));
        $price_m2 = (float) ($params['priceM2'] ?? 0.0);
        if ($net_m2 <= 0.0) {
            return [
                'pieces'      => 0,
                'fullBoxes'   => 0,
                'loosePieces' => 0,
                'boxText'     => '0 thùng',
                'needM2'      => 0.0,
                'actualM2'    => 0.0,
                'totalKg'     => 0.0,
                'pricePiece'  => 0,
                'totalVnd'    => 0,
                'isQuote'     => !($price_m2 > 0.0),
            ];
        }

        $ppb = max(1, (int) ($params['piecesPerBox'] ?? 1));
        return self::calcFromM2([
            'areaM2'        => $net_m2,
            'wastePct'      => $params['wastePct'] ?? 5,
            'forceRoundBox' => true,
            'areaPiece'     => $params['areaPiece'] ?? 0.0,
            'piecesPerBox'  => $ppb,
            'kgPerBox'      => $params['kgPerBox'] ?? 0.0,
            'priceM2'       => $price_m2,
            'pricePiece'    => $params['pricePiece'] ?? null,
            'roundMode'     => 'box',
            'qtyMin'        => $ppb,
            'unitLabel'     => $params['unitLabel'] ?? 'viên',
        ]);
    }

    /**
     * 6. Tính chiết khấu khối lượng dự kiến trên bảng BOQ (ADR D-23).
     *
     * @param array<int, array<string, mixed>> $tiers
     * @return array{rate: float, minM2: float, discountVnd: int, grandVnd: int, nextTier: array{min_m2: float, rate: float}|null}
     */
    public static function calcVolumeDiscount(int|float $subtotal_vnd, float $total_m2, array $tiers = self::DEFAULT_VOLUME_TIERS): array {
        $sub        = max(0, (int) round((float) $subtotal_vnd));
        $m2         = max(0.0, $total_m2);
        $raw_tiers  = $tiers !== [] ? $tiers : self::DEFAULT_VOLUME_TIERS;
        $normalized = [];

        foreach ($raw_tiers as $t) {
            $normalized[] = [
                'min_m2' => (float) ($t['min_m2'] ?? 0.0),
                'rate'   => (float) ($t['rate'] ?? $t['discount_pct'] ?? 0.0),
            ];
        }

        usort($normalized, static fn (array $a, array $b): int => $b['min_m2'] <=> $a['min_m2']);

        $matched = ['min_m2' => 0.0, 'rate' => 0.0];
        foreach ($normalized as $tier) {
            if ($m2 + self::EPSILON >= $tier['min_m2']) {
                $matched = $tier;
                break;
            }
        }

        $next_tier = null;
        for ($i = count($normalized) - 1; $i >= 0; $i--) {
            if ($normalized[$i]['min_m2'] > $m2 + self::EPSILON) {
                $next_tier = $normalized[$i];
                break;
            }
        }

        $discount_vnd = (int) round(($sub * $matched['rate']) / 100.0);

        return [
            'rate'        => $matched['rate'],
            'minM2'       => $matched['min_m2'],
            'discountVnd' => $discount_vnd,
            'grandVnd'    => $sub - $discount_vnd,
            'nextTier'    => $next_tier,
        ];
    }

    /**
     * 7. Gợi ý phương tiện vận chuyển theo tổng khối lượng kg.
     *
     * @param array<int, array<string, mixed>> $thresholds
     */
    public static function resolveVehicleHint(float $total_kg, array $thresholds = self::DEFAULT_VEHICLE_THRESHOLDS): string {
        if ($total_kg <= 0.0) {
            return 'Chưa có khối lượng';
        }
        $list = $thresholds !== [] ? $thresholds : self::DEFAULT_VEHICLE_THRESHOLDS;
        foreach ($list as $t) {
            if ($total_kg <= (float) ($t['max_kg'] ?? 0.0)) {
                return (string) ($t['label'] ?? '');
            }
        }
        return 'Xe tải 8 tấn / chia chuyến';
    }

    /**
     * 8. Số lượng viên mặc định khi bấm nút Thêm nhanh trên Card danh mục (docs/04 §3.4, D-24).
     *
     * @param array{defaultBuyUnit?: string, roundMode?: string, piecesPerBox?: int, qtyMin?: int} $args
     */
    public static function quickAddPieces(array $args = []): int {
        $default_unit = (string) ($args['defaultBuyUnit'] ?? 'm2');
        $round_mode   = (string) ($args['roundMode'] ?? 'box');
        $ppb          = max(1, (int) ($args['piecesPerBox'] ?? 1));
        $min_p        = max(1, (int) ($args['qtyMin'] ?? 1));

        if ($round_mode === 'box') {
            $target = max($ppb, $min_p);
            return (int) (ceil(($target - self::EPSILON) / $ppb) * $ppb);
        }
        if ($default_unit === 'm2' || $default_unit === 'thung') {
            return max($ppb, $min_p);
        }
        return $min_p;
    }
}

