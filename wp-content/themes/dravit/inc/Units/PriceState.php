<?php
declare(strict_types=1);

namespace Dravit\Units;

defined('ABSPATH') || exit;

/**
 * Suy dẫn trạng thái giá & dữ liệu hiển thị thẻ sản phẩm (docs/14 §2.4, ADR D-20, D-24).
 */
final class PriceState {
    public const PRICED       = 'priced';
    public const SALE         = 'sale';
    public const QUOTE_ONLY   = 'quote_only';
    public const OUT_OF_STOCK = 'out_of_stock';

    /**
     * Trả về 1 trong 4 trạng thái chuẩn: `priced | sale | quote_only | out_of_stock`.
     */
    public static function resolve(\WC_Product $product): string {
        $model = (string) ($product->get_meta('drv_pricing_model', true) ?: 'area_pack');
        if ($model === 'area_pack') {
            $price_m2 = (int) $product->get_meta('drv_price_m2', true);
            if ($price_m2 <= 0 || $product->get_regular_price('edit') === '') {
                return self::QUOTE_ONLY;
            }
        } elseif ($product->get_regular_price('edit') === '' || (float) $product->get_regular_price('edit') <= 0.0) {
            return self::QUOTE_ONLY;
        }

        if (!$product->is_in_stock()) {
            return self::OUT_OF_STOCK;
        }

        if ($product->is_on_sale()) {
            return self::SALE;
        }

        return self::PRICED;
    }

    /**
     * Đọc cấu hình D-24 chuẩn hóa của sản phẩm (buy_units, default_buy_unit, round_mode, qty_min, qty_step).
     *
     * @return array{buy_units: array<int, string>, default_buy_unit: string, round_mode: string, pieces_per_box: int, qty_min: int, qty_step: int, unit_label: string}
     */
    public static function readUnitConfig(\WC_Product $product): array {
        $ppb        = max(1, (int) ($product->get_meta('drv_pieces_per_box', true) ?: 1));
        $unit_label = (string) ($product->get_meta('drv_unit_label', true) ?: 'viên');
        if (!in_array($unit_label, ['viên', 'tấm'], true)) {
            $unit_label = 'viên';
        }

        $raw_units = $product->get_meta('drv_buy_units', true);
        if (is_string($raw_units) && $raw_units !== '') {
            $decoded = json_decode($raw_units, true);
            $units   = is_array($decoded) ? $decoded : explode('|', $raw_units);
        } elseif (is_array($raw_units)) {
            $units = $raw_units;
        } else {
            $units = ['m2', 'thung'];
        }

        $default_unit = (string) ($product->get_meta('drv_default_buy_unit', true) ?: 'm2');
        $round_mode   = (string) ($product->get_meta('drv_round_mode', true) ?: 'box');
        $qty_min_raw  = (int) ($product->get_meta('drv_qty_min', true) ?: 0);

        $normalized = self::normalizeBuyUnitConfig(
            array_map('strval', $units),
            $default_unit,
            $round_mode,
            $ppb,
            $qty_min_raw
        );

        $normalized['pieces_per_box'] = $ppb;
        $normalized['unit_label']     = $unit_label;

        return $normalized;
    }

    /**
     * Hàm thuần chuẩn hóa cấu hình D-24 khi lưu SP hoặc import CSV (docs/14 §2.2 & §2.4).
     *
     * @param array<int, string> $buy_units
     * @return array{buy_units: array<int, string>, default_buy_unit: string, round_mode: string, qty_min: int, qty_step: int}
     */
    public static function normalizeBuyUnitConfig(
        array $buy_units,
        string $default_unit,
        string $round_mode,
        int $pieces_per_box,
        int $qty_min
    ): array {
        $ppb     = max(1, $pieces_per_box);
        $allowed = ['m2', 'thung', 'vien'];
        $ordered = [];
        foreach ($allowed as $candidate) {
            if (in_array($candidate, $buy_units, true)) {
                $ordered[] = $candidate;
            }
        }
        if ($ordered === []) {
            $ordered = ['m2', 'thung'];
        }

        $clean_round = in_array($round_mode, ['box', 'piece'], true) ? $round_mode : 'box';
        if (!in_array('vien', $ordered, true)) {
            $clean_round = 'box';
        }

        $clean_default = in_array($default_unit, $ordered, true) ? $default_unit : $ordered[0];
        $qty_step      = ($clean_round === 'box') ? $ppb : 1;

        $base_min = $qty_min > 0 ? $qty_min : (($clean_round === 'box') ? $ppb : 1);
        if ($clean_round === 'box') {
            $clean_min = (int) (ceil(($base_min - Calc::EPSILON) / $ppb) * $ppb);
        } else {
            $clean_min = max(1, $base_min);
        }

        return [
            'buy_units'        => $ordered,
            'default_buy_unit' => $clean_default,
            'round_mode'       => $clean_round,
            'qty_min'          => $clean_min,
            'qty_step'         => $qty_step,
        ];
    }

    /**
     * Chuẩn hóa dữ liệu từ `WC_Product` sang mảng hiển thị cho `product-card.php` & `price.php`.
     *
     * @return array<string, mixed>
     */
    public static function toCardData(\WC_Product $product): array {
        $id           = $product->get_id();
        $state        = self::resolve($product);
        $unit_cfg     = self::readUnitConfig($product);
        $reg_m2       = max(0, (int) $product->get_meta('drv_price_m2', true));
        $sale_m2      = max(0, (int) $product->get_meta('drv_sale_price_m2', true));
        $is_sale      = ($state === self::SALE && $sale_m2 > 0 && $sale_m2 < $reg_m2);
        $eff_m2       = $is_sale ? $sale_m2 : $reg_m2;
        $price_piece  = ($state === self::QUOTE_ONLY) ? 0 : (int) round((float) $product->get_price());

        $w_mm         = max(0, (int) $product->get_meta('drv_piece_w_mm', true));
        $h_mm         = max(0, (int) $product->get_meta('drv_piece_h_mm', true));
        $area_piece   = Calc::pieceAreaM2($w_mm, $h_mm);
        $m2_box       = (float) $product->get_meta('drv_m2_per_box', true);
        if ($m2_box <= 0.0 && $area_piece > 0.0) {
            $m2_box = round($area_piece * $unit_cfg['pieces_per_box'], 4);
        }

        $stock_boxes = null;
        $low_stock   = false;
        if ($product->managing_stock() && $product->get_stock_quantity() !== null) {
            $raw_stock   = max(0, (int) $product->get_stock_quantity());
            $stock_boxes = intdiv($raw_stock, $unit_cfg['pieces_per_box']);
            $low_amount  = (int) ($product->get_low_stock_amount() ?: 0);
            if ($low_amount > 0 && $raw_stock > 0 && $raw_stock <= $low_amount) {
                $low_stock = true;
            }
        }

        $card_label = trim((string) $product->get_meta('drv_card_label', true));
        if ($card_label === '' && $state !== self::OUT_OF_STOCK) {
            $bestseller = (int) drv_setting('catalog.bestseller_threshold', 20);
            $new_days   = (int) drv_setting('catalog.new_days', 45);
            if ($bestseller > 0 && (int) $product->get_total_sales() >= $bestseller) {
                $card_label = 'Bán chạy';
            } else {
                $created = $product->get_date_created();
                if ($created !== null && (time() - $created->getTimestamp()) <= ($new_days * 86400)) {
                    $card_label = 'Mới';
                }
            }
        }

        $thumb_id    = (int) $product->get_image_id();
        $gallery_ids = $product->get_gallery_image_ids();
        $hover_id    = isset($gallery_ids[0]) ? (int) $gallery_ids[0] : 0;
        $img_primary = ($thumb_id > 0 && function_exists('wp_get_attachment_image_url'))
            ? (string) wp_get_attachment_image_url($thumb_id, 'woocommerce_thumbnail')
            : '';
        $img_hover   = ($hover_id > 0 && function_exists('wp_get_attachment_image_url'))
            ? (string) wp_get_attachment_image_url($hover_id, 'woocommerce_thumbnail')
            : '';

        $tags = [];
        foreach (['pa_kich-thuoc', 'pa_be-mat', 'pa_dcof'] as $tax) {
            $label = self::firstTermShortLabel($id, $tax);
            if ($label !== '') {
                $tags[] = $label;
            }
            if (count($tags) >= 3) {
                break;
            }
        }

        $quick_pieces = Calc::quickAddPieces([
            'defaultBuyUnit' => $unit_cfg['default_buy_unit'],
            'roundMode'      => $unit_cfg['round_mode'],
            'piecesPerBox'   => $unit_cfg['pieces_per_box'],
            'qtyMin'         => $unit_cfg['qty_min'],
        ]);

        return [
            'id'               => $id,
            'sku'              => (string) $product->get_sku(),
            'title'            => $product->get_name(),
            'url'              => function_exists('get_permalink') ? (get_permalink($id) ?: home_url('/gach')) : '/san-pham/' . $product->get_slug(),
            'price_state'      => $state,
            'price_m2'         => ($state === self::QUOTE_ONLY) ? 0 : $eff_m2,
            'regular_price_m2' => ($state === self::QUOTE_ONLY) ? 0 : $reg_m2,
            'price_piece'      => $price_piece,
            'pieces_per_box'   => $unit_cfg['pieces_per_box'],
            'm2_per_box'       => $m2_box > 0.0 ? $m2_box : 1.44,
            'unit_label'       => $unit_cfg['unit_label'],
            'buy_units'        => $unit_cfg['buy_units'],
            'default_buy_unit' => $unit_cfg['default_buy_unit'],
            'round_mode'       => $unit_cfg['round_mode'],
            'qty_min'          => $unit_cfg['qty_min'],
            'qty_step'         => $unit_cfg['qty_step'],
            'quick_pieces'     => $quick_pieces,
            'default_m2'       => $area_piece > 0.0 ? round($quick_pieces * $area_piece, 4) : $m2_box,
            'showroom_shelf'   => trim((string) $product->get_meta('drv_showroom_shelf', true)),
            'card_label'       => $card_label,
            'in_stock'         => $product->is_in_stock(),
            'stock_boxes'      => $stock_boxes,
            'low_stock'        => $low_stock,
            'img_primary'      => $img_primary,
            'img_hover'        => $img_hover,
            'tags'             => $tags,
        ];
    }

    private static function firstTermShortLabel(int $product_id, string $taxonomy): string {
        if ($product_id <= 0 || !function_exists('get_the_terms')) {
            return '';
        }
        $terms = get_the_terms($product_id, $taxonomy);
        if (!is_array($terms) || $terms === []) {
            return '';
        }
        $term = reset($terms);
        if (!$term instanceof \WP_Term) {
            return '';
        }
        if (function_exists('get_term_meta')) {
            $short = trim((string) get_term_meta($term->term_id, 'drv_short_label', true));
            if ($short !== '') {
                return $short;
            }
        }
        return $term->name;
    }
}

