<?php
declare(strict_types=1);

namespace Dravit\Quote;

use Dravit\Leads\LeadValidator;
use Dravit\Units\Calc;
use Dravit\Units\PriceState;

defined('ABSPATH') || exit;

/**
 * Validate cấu trúc phòng/hạng mục và tính lại 100% khối lượng, thành tiền phía server (docs/17 §3, §4.3).
 * Client chỉ gửi định danh SP + khối lượng đầu vào; mọi giá tiền client gửi lên đều bị bỏ qua (Q-07).
 */
final class BoqService {
    public const DEFAULT_ROOM = 'Hạng mục chung';

    /** @var callable(int): (array<string, mixed>|null) */
    private $productLoader;

    /**
     * @param (callable(int): (array<string, mixed>|null))|null $product_loader Trả về SP đã chuẩn hóa hoặc `null` nếu không khả dụng.
     */
    public function __construct(?callable $product_loader = null) {
        $this->productLoader = $product_loader ?? [self::class, 'loadProduct'];
    }

    /**
     * Chuẩn hóa payload BOQ (chấp nhận cả 2 dạng `{boq:{rooms}}` docs/17 và `{rooms, waste_pct}` docs/15).
     *
     * @param array<string, mixed>  $boq
     * @param array<int, int>       $waste_options
     * @param array<int, string>    $roles
     * @return array{waste_pct: int, project: string, rooms: array<int, array{name: string, slots: array<int, array<string, mixed>>}>, item_count: int, errors: array<string, string>}
     */
    public static function normalizeInput(array $boq, array $waste_options = [0, 5, 10], array $roles = [], int $max_items = 30, int $max_rooms = 12): array {
        $errors = [];
        $roles  = $roles !== [] ? array_values($roles) : ['Lát nền chính'];

        $waste_raw = $boq['waste_pct'] ?? 5;
        $waste     = is_numeric($waste_raw) ? (int) $waste_raw : -1;
        $options   = array_map('intval', $waste_options !== [] ? $waste_options : [0, 5, 10]);
        if (!in_array($waste, $options, true)) {
            $errors['waste_pct'] = 'Mức hao hụt không hợp lệ.';
            $waste = in_array(5, $options, true) ? 5 : (int) $options[0];
        }

        $rooms_out  = [];
        $item_count = 0;
        $rooms_in   = is_array($boq['rooms'] ?? null) ? $boq['rooms'] : [];

        foreach (array_slice(array_values($rooms_in), 0, max(1, $max_rooms)) as $room) {
            if (!is_array($room)) {
                continue;
            }
            $name  = LeadValidator::text($room['name'] ?? '', 60);
            $slots = [];
            foreach ((array) ($room['slots'] ?? []) as $slot) {
                if (!is_array($slot)) {
                    continue;
                }
                $pid       = max(0, (int) ($slot['product_id'] ?? 0));
                $target_m2 = max(0.0, min(10000.0, round((float) ($slot['target_m2'] ?? 0), 2)));
                $pieces    = max(0, min(50000, (int) round((float) ($slot['pieces'] ?? 0))));
                $mode_raw  = (string) ($slot['input_mode'] ?? 'm2');
                $mode      = in_array($mode_raw, ['m2', 'thung', 'vien'], true) ? $mode_raw : 'm2';

                if ($pid <= 0 || ($mode === 'm2' ? $target_m2 <= 0.0 : $pieces <= 0)) {
                    continue;
                }
                if ($item_count >= $max_items) {
                    $errors['rooms'] = sprintf('Bảng dự toán tối đa %d hạng mục.', $max_items);
                    break 2;
                }

                $role = LeadValidator::text($slot['role'] ?? '', 60);
                if (!in_array($role, $roles, true)) {
                    $role = $roles[0];
                }

                $slots[] = [
                    'role'       => $role,
                    'product_id' => $pid,
                    'input_mode' => $mode,
                    'target_m2'  => $target_m2,
                    'pieces'     => $pieces,
                ];
                $item_count++;
            }

            if ($slots !== []) {
                $rooms_out[] = ['name' => $name !== '' ? $name : self::DEFAULT_ROOM, 'slots' => $slots];
            }
        }

        if ($item_count === 0 && !isset($errors['rooms'])) {
            $errors['rooms'] = 'Bảng dự toán chưa có mẫu gạch nào kèm diện tích.';
        }

        return [
            'waste_pct'  => $waste,
            'project'    => LeadValidator::text($boq['project_name'] ?? ($boq['project'] ?? ''), 120),
            'rooms'      => $rooms_out,
            'item_count' => $item_count,
            'errors'     => $errors,
        ];
    }

    /**
     * Tính lại toàn phiếu từ dữ liệu SP thật.
     *
     * @param array<int, array{name: string, slots: array<int, array<string, mixed>>}> $rooms
     * @param array<int, array<string, mixed>> $tiers
     * @param array<int, array<string, mixed>> $vehicles
     * @return array{rooms: array<int, array<string, mixed>>, totals: array<string, mixed>, errors: array<string, string>}
     */
    public function compute(array $rooms, int $waste_pct, array $tiers = [], array $vehicles = []): array {
        $out_rooms = [];
        $errors    = [];
        $totals    = [
            'room_count'   => 0,
            'item_count'   => 0,
            'total_m2'     => 0.0,
            'total_boxes'  => 0,
            'total_loose'  => 0,
            'total_kg'     => 0.0,
            'subtotal_vnd' => 0,
            'priced_count' => 0,
            'quote_count'  => 0,
        ];

        foreach ($rooms as $room) {
            $out_slots = [];
            foreach ($room['slots'] as $slot) {
                $product = ($this->productLoader)((int) $slot['product_id']);
                if (!is_array($product)) {
                    $errors['rooms'] = sprintf('Mẫu #%d hiện ngừng kinh doanh. Vui lòng chọn mẫu thay thế hoặc xóa dòng này.', (int) $slot['product_id']);
                    continue;
                }

                $price_m2 = $product['price_state'] === PriceState::QUOTE_ONLY ? 0 : (int) $product['price_m2'];
                $calc     = Calc::computeSlot([
                    'areaPiece'    => (float) $product['area_piece'],
                    'piecesPerBox' => (int) $product['pieces_per_box'],
                    'kgPerBox'     => (float) $product['kg_per_box'],
                    'priceM2'      => $price_m2,
                    'pricePiece'   => $price_m2 > 0 && (int) ($product['price_piece'] ?? 0) > 0 ? (int) $product['price_piece'] : null,
                    'roundMode'    => (string) $product['round_mode'],
                    'qtyMin'       => (int) $product['qty_min'],
                    'unitLabel'    => (string) $product['unit_label'],
                ], [
                    'inputMode' => $slot['input_mode'],
                    'targetM2'  => $slot['target_m2'],
                    'pieces'    => $slot['pieces'],
                ], $waste_pct);

                $is_quote = $calc['isQuote'];
                $out_slots[] = [
                    'role'        => $slot['role'],
                    'product_id'  => (int) $slot['product_id'],
                    'sku'         => (string) $product['sku'],
                    'name'        => (string) $product['name'],
                    'size_label'  => (string) ($product['size_label'] ?? ''),
                    'price_state' => (string) $product['price_state'],
                    'input_mode'  => $calc['inputMode'],
                    'target_m2'   => $calc['targetM2'],
                    'pieces'      => $calc['pieces'],
                    'boxes'       => $calc['fullBoxes'],
                    'loose'       => $calc['loosePieces'],
                    'box_text'    => $calc['boxText'],
                    'actual_m2'   => $calc['actualM2'],
                    'kg'          => $calc['totalKg'],
                    'price_m2'    => $price_m2,
                    'price_piece' => $calc['pricePiece'],
                    'line_total'  => $is_quote ? 0 : $calc['totalVnd'],
                    'is_quote'    => $is_quote,
                ];

                $totals['item_count']++;
                $totals['total_m2']    += $calc['actualM2'];
                $totals['total_boxes'] += $calc['fullBoxes'];
                $totals['total_loose'] += $calc['loosePieces'];
                $totals['total_kg']    += $calc['totalKg'];
                if ($is_quote) {
                    $totals['quote_count']++;
                } else {
                    $totals['priced_count']++;
                    $totals['subtotal_vnd'] += $calc['totalVnd'];
                }
            }

            if ($out_slots !== []) {
                $out_rooms[] = ['name' => $room['name'], 'slots' => $out_slots];
            }
        }

        $totals['room_count'] = count($out_rooms);
        $totals['total_m2']   = round($totals['total_m2'], 2);
        $totals['total_kg']   = round($totals['total_kg'], 1);

        $discount = Calc::calcVolumeDiscount($totals['subtotal_vnd'], $totals['total_m2'], $tiers !== [] ? $tiers : Calc::DEFAULT_VOLUME_TIERS);
        $has_sub  = $totals['subtotal_vnd'] > 0;

        $totals['discount_rate']    = $has_sub ? (float) $discount['rate'] : 0.0;
        $totals['discount_est_vnd'] = $has_sub ? (int) $discount['discountVnd'] : 0;
        $totals['grand_est_vnd']    = $totals['subtotal_vnd'] - $totals['discount_est_vnd'];
        $totals['has_quote_items']  = $totals['quote_count'] > 0;
        $totals['vehicle_hint']     = Calc::resolveVehicleHint((float) $totals['total_kg'], $vehicles !== [] ? $vehicles : Calc::DEFAULT_VEHICLE_THRESHOLDS);

        return ['rooms' => $out_rooms, 'totals' => $totals, 'errors' => $errors];
    }

    /**
     * Nạp SP thật từ DB (chỉ `publish`, đúng ngành `gach`) và chuẩn hóa sang mảng tính toán.
     *
     * @return array<string, mixed>|null
     */
    public static function loadProduct(int $product_id): ?array {
        if ($product_id <= 0 || !function_exists('wc_get_product')) {
            return null;
        }
        $product = wc_get_product($product_id);
        if (!$product instanceof \WC_Product || $product->get_status() !== 'publish') {
            return null;
        }
        $line = (string) ($product->get_meta('drv_line', true) ?: 'gach');
        if ($line !== 'gach') {
            return null;
        }
        return self::productData($product);
    }

    /**
     * @return array<string, mixed>
     */
    public static function productData(\WC_Product $product): array {
        $card  = PriceState::toCardData($product);
        $w_mm  = max(0, (int) $product->get_meta('drv_piece_w_mm', true));
        $h_mm  = max(0, (int) $product->get_meta('drv_piece_h_mm', true));
        $stock = $product->managing_stock() ? $product->get_stock_quantity() : null;

        return [
            'id'             => $product->get_id(),
            'sku'            => (string) $product->get_sku(),
            'name'           => $product->get_name(),
            'price_state'    => (string) $card['price_state'],
            'price_m2'       => (int) $card['price_m2'],
            'price_piece'    => (int) $card['price_piece'],
            'area_piece'     => Calc::pieceAreaM2($w_mm, $h_mm),
            'pieces_per_box' => (int) $card['pieces_per_box'],
            'kg_per_box'     => max(0.0, (float) $product->get_meta('drv_kg_per_box', true)),
            'unit_label'     => (string) $card['unit_label'],
            'round_mode'     => (string) $card['round_mode'],
            'qty_min'        => (int) $card['qty_min'],
            'size_label'     => self::sizeLabel($w_mm, $h_mm),
            'stock_qty'      => $stock === null ? null : max(0, (int) $stock),
        ];
    }

    public static function sizeLabel(int $w_mm, int $h_mm): string {
        if ($w_mm <= 0 || $h_mm <= 0) {
            return '';
        }
        $fmt = static fn (int $mm): string => rtrim(rtrim(number_format($mm / 10, 1, ',', ''), '0'), ',');
        return $fmt($w_mm) . '×' . $fmt($h_mm) . ' cm';
    }
}
