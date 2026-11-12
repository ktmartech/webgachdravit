<?php
declare(strict_types=1);

namespace Dravit\Units\PricingModel;

use Dravit\Units\Calc;
use Dravit\Units\PriceState;
use Dravit\Units\PriceView;
use Dravit\Units\PricingModelInterface;

defined('ABSPATH') || exit;

/**
 * Mô hình định giá chủ lực ngành gạch `area_pack`: Giá hiển thị theo `đ/m²`, giao dịch & kho theo `viên` (docs/04 §2).
 */
final class AreaPack implements PricingModelInterface {
    public function sync_wc_prices(\WC_Product $product): void {
        $w_mm       = max(0, (int) $product->get_meta('drv_piece_w_mm', true));
        $h_mm       = max(0, (int) $product->get_meta('drv_piece_h_mm', true));
        $ppb        = max(1, (int) ($product->get_meta('drv_pieces_per_box', true) ?: 1));
        $kg_per_box = max(0.0, (float) $product->get_meta('drv_kg_per_box', true));
        $price_m2   = max(0, (int) $product->get_meta('drv_price_m2', true));
        $sale_m2    = max(0, (int) $product->get_meta('drv_sale_price_m2', true));

        $area_piece = Calc::pieceAreaM2($w_mm, $h_mm);
        $m2_per_box = $area_piece > 0.0 ? round($area_piece * $ppb, 4) : 0.0;

        $product->update_meta_data('drv_pieces_per_box', (string) $ppb);
        $product->update_meta_data('drv_m2_per_box', (string) $m2_per_box);

        if ($kg_per_box > 0.0) {
            $product->set_weight((string) round($kg_per_box / $ppb, 3));
        }

        // Chuẩn hóa cấu hình đơn vị mua D-24.
        $unit_cfg = PriceState::readUnitConfig($product);
        $units_json = function_exists('wp_json_encode')
            ? (string) wp_json_encode($unit_cfg['buy_units'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : json_encode($unit_cfg['buy_units'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $product->update_meta_data('drv_buy_units', (string) $units_json);
        $product->update_meta_data('drv_default_buy_unit', $unit_cfg['default_buy_unit']);
        $product->update_meta_data('drv_round_mode', $unit_cfg['round_mode']);
        $product->update_meta_data('drv_qty_min', (string) $unit_cfg['qty_min']);
        $product->update_meta_data('drv_qty_step', (string) $unit_cfg['qty_step']);
        $product->update_meta_data('drv_unit_label', $unit_cfg['unit_label']);

        if ($price_m2 > 0 && $area_piece > 0.0) {
            $reg_piece = Calc::pricePerPiece($price_m2, $area_piece);
            $product->set_regular_price((string) $reg_piece);

            if ($sale_m2 > 0 && $sale_m2 < $price_m2) {
                $sale_piece = Calc::pricePerPiece($sale_m2, $area_piece);
                $product->set_sale_price((string) $sale_piece);
                if (!$product->get_date_on_sale_from('edit') && !$product->get_date_on_sale_to('edit')) {
                    $product->set_price((string) $sale_piece);
                }
            } else {
                $product->update_meta_data('drv_sale_price_m2', '0');
                $product->set_sale_price('');
                $product->set_price((string) $reg_piece);
            }
        } else {
            // Chế độ Liên hệ báo giá (D-20): khóa rỗng giá WC.
            $product->update_meta_data('drv_price_m2', '0');
            $product->update_meta_data('drv_sale_price_m2', '0');
            $product->set_regular_price('');
            $product->set_sale_price('');
            $product->set_price('');
        }
    }

    public function primary_price(\WC_Product $product): PriceView {
        $state = PriceState::resolve($product);
        if ($state === PriceState::QUOTE_ONLY) {
            return new PriceView(0, 0, 'm²', 0, true);
        }

        $reg_m2  = max(0, (int) $product->get_meta('drv_price_m2', true));
        $sale_m2 = max(0, (int) $product->get_meta('drv_sale_price_m2', true));
        if ($state === PriceState::SALE && $sale_m2 > 0 && $sale_m2 < $reg_m2) {
            $pct = (int) round((1.0 - ($sale_m2 / $reg_m2)) * 100.0);
            return new PriceView($sale_m2, $reg_m2, 'm²', $pct, false);
        }

        return new PriceView($reg_m2, $reg_m2, 'm²', 0, false);
    }

    public function secondary_price(\WC_Product $product): ?PriceView {
        $state = PriceState::resolve($product);
        if ($state === PriceState::QUOTE_ONLY) {
            return null;
        }

        $unit_label = (string) ($product->get_meta('drv_unit_label', true) ?: 'viên');
        $cur_piece  = (int) round((float) $product->get_price());
        $reg_piece  = (int) round((float) $product->get_regular_price());

        return new PriceView($cur_piece, max($cur_piece, $reg_piece), $unit_label, 0, false);
    }

    public function qty_summary(\WC_Product $product, int $qty): string {
        $w_mm       = max(0, (int) $product->get_meta('drv_piece_w_mm', true));
        $h_mm       = max(0, (int) $product->get_meta('drv_piece_h_mm', true));
        $area_piece = Calc::pieceAreaM2($w_mm, $h_mm);
        $ppb        = max(1, (int) ($product->get_meta('drv_pieces_per_box', true) ?: 1));
        $kg_per_box = max(0.0, (float) $product->get_meta('drv_kg_per_box', true));
        $unit_label = (string) ($product->get_meta('drv_unit_label', true) ?: 'viên');

        $line = Calc::calcCartLine([
            'pieces'       => max(1, $qty),
            'areaPiece'    => $area_piece,
            'piecesPerBox' => $ppb,
            'kgPerBox'     => $kg_per_box,
            'unitLabel'    => $unit_label,
        ]);

        $parts = [$line['boxText']];
        if ($line['actualM2'] > 0.0) {
            $parts[] = drv_format_decimal($line['actualM2'], 2) . ' m²';
        }
        if ($line['totalKg'] > 0.0) {
            $parts[] = drv_format_decimal($line['totalKg'], 1) . ' kg';
        }

        return implode(' · ', $parts);
    }

    public function quantity_args(\WC_Product $product): array {
        $cfg = PriceState::readUnitConfig($product);
        $max = null;
        if ($product->managing_stock() && !$product->backorders_allowed() && $product->get_stock_quantity() !== null) {
            $max = max(0, (int) $product->get_stock_quantity());
        }

        return [
            'min'            => $cfg['qty_min'],
            'step'           => $cfg['qty_step'],
            'max'            => $max,
            'buy_units'      => $cfg['buy_units'],
            'default_unit'   => $cfg['default_buy_unit'],
            'round_mode'     => $cfg['round_mode'],
            'pieces_per_box' => $cfg['pieces_per_box'],
            'unit_label'     => $cfg['unit_label'],
        ];
    }

    public function has_calculator(): bool {
        return true;
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
                'unitCode' => 'MTK',
            ],
        ];
    }
}
