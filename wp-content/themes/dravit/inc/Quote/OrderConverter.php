<?php
declare(strict_types=1);

namespace Dravit\Quote;

use Dravit\Leads\LeadRepository;

defined('ABSPATH') || exit;

/**
 * Chuyển phiếu `drv_lead` đã chốt giá thành đơn WooCommerce HPOS qua WC Order CRUD (docs/17 §5.3, D-22, D-23).
 * - Không sửa giá gốc Catalog: giá chốt chỉ ghi vào line item (`subtotal` = niêm yết, `total` = giá chốt).
 * - `drv_lead` là CPT nên đọc meta qua post meta; đơn hàng chỉ dùng `$order->update_meta_data()`.
 */
final class OrderConverter {
    /**
     * Ghép dòng đơn từ snapshot BOQ + đơn giá chốt (đ/m²) theo khóa `"{roomIdx}:{slotIdx}"`.
     *
     * @param array<string, mixed>                              $boq
     * @param array<string, int|float|string>                   $agreed_rates
     * @param callable(int): (array<string, mixed>|null)        $product_loader Trả `BoqService::productData()` hoặc `null`.
     * @return array<int, array<string, mixed>>|\WP_Error
     */
    public static function prepareLines(array $boq, array $agreed_rates, callable $product_loader): array|\WP_Error {
        $rooms = is_array($boq['rooms'] ?? null) ? $boq['rooms'] : [];
        if ($rooms === []) {
            return new \WP_Error('drv_empty_boq', 'Phiếu chưa có danh mục sản phẩm hợp lệ để tạo đơn.');
        }

        $waste = (int) ($boq['waste_pct'] ?? 5);
        $lines = [];

        foreach (array_values($rooms) as $r_idx => $room) {
            $room_name = (string) ($room['name'] ?? BoqService::DEFAULT_ROOM);
            foreach (array_values((array) ($room['slots'] ?? [])) as $s_idx => $slot) {
                $pid     = (int) ($slot['product_id'] ?? 0);
                $pieces  = max(1, (int) ($slot['pieces'] ?? 0));
                $product = $product_loader($pid);
                if (!is_array($product)) {
                    return new \WP_Error('drv_invalid_product', sprintf('Sản phẩm ID #%d không còn khả dụng.', $pid));
                }

                $key    = $r_idx . ':' . $s_idx;
                $raw    = (string) ($agreed_rates[$key] ?? '');
                $agreed = $raw !== '' ? (int) preg_replace('/\D/', '', $raw) : (int) ($slot['price_m2'] ?? 0);
                if ($agreed <= 0) {
                    return new \WP_Error(
                        'drv_missing_agreed_price',
                        sprintf('Vui lòng nhập Đơn giá chốt (đ/m²) lớn hơn 0 cho mẫu %s.', (string) ($product['sku'] ?: '#' . $pid))
                    );
                }

                $area_piece = (float) $product['area_piece'];
                $ppb        = max(1, (int) $product['pieces_per_box']);
                $kg_box     = (float) $product['kg_per_box'];
                $list_m2    = max($agreed, (int) $product['price_m2']);

                $lines[] = [
                    'product_id'      => $pid,
                    'sku'             => (string) $product['sku'],
                    'pieces'          => $pieces,
                    'room_name'       => $room_name,
                    'role'            => (string) ($slot['role'] ?? ''),
                    'target_m2'       => (float) ($slot['target_m2'] ?? 0.0),
                    'waste_pct'       => $waste,
                    'agreed_price_m2' => $agreed,
                    'area_piece'      => $area_piece,
                    'pieces_per_box'  => $ppb,
                    'kg_per_box'      => $kg_box,
                    'unit_label'      => (string) $product['unit_label'],
                    'line_subtotal'   => (int) round($list_m2 * $area_piece) * $pieces,
                    'line_total'      => (int) round($agreed * $area_piece) * $pieces,
                    'm2_qty'          => round($pieces * $area_piece, 4),
                    'boxes'           => intdiv($pieces, $ppb),
                    'loose'           => $pieces % $ppb,
                    'kg'              => round($pieces * ($kg_box / $ppb), 1),
                ];
            }
        }

        return $lines;
    }

    /**
     * Trạng thái khởi tạo: chưa có phí ship -> `cho-xac-nhan` (nếu Step 9 đã đăng ký, ngược lại `pending`);
     * đã chốt phí ship -> `on-hold` (BACS, kèm VietQR) hoặc `processing` (COD).
     *
     * @param array<int, string> $registered_statuses Khóa `wc_get_order_statuses()` (có tiền tố `wc-`).
     */
    public static function resolveTargetStatus(?int $shipping_fee, string $payment_method, array $registered_statuses): string {
        if ($shipping_fee === null || $shipping_fee < 0) {
            return in_array('wc-cho-xac-nhan', $registered_statuses, true) ? 'cho-xac-nhan' : 'pending';
        }
        return $payment_method === 'cod' ? 'processing' : 'on-hold';
    }

    /**
     * @param array<string, int|float|string> $agreed_rates
     * @param array<string, mixed>            $ops {shipping_fee: ?int, payment_method: 'bacs'|'cod', ship_vehicle, ship_note, vat: array}
     */
    public static function convert(int $lead_id, array $agreed_rates, array $ops): \WC_Order|\WP_Error {
        if (!current_user_can('manage_woocommerce')) {
            return new \WP_Error('drv_forbidden', 'Bạn không có quyền tạo đơn hàng.');
        }
        $lead = get_post($lead_id);
        if (!$lead instanceof \WP_Post || $lead->post_type !== LeadRepository::POST_TYPE) {
            return new \WP_Error('drv_not_found', 'Không tìm thấy phiếu báo giá.');
        }

        $existing_id = (int) LeadRepository::meta($lead_id, '_drv_converted_order_id', 0);
        if ($existing_id > 0) {
            $existing = wc_get_order($existing_id);
            if ($existing instanceof \WC_Order && !in_array($existing->get_status(), ['cancelled', 'trash', 'failed'], true)) {
                return new \WP_Error('drv_already_converted', sprintf('Phiếu này đã được tạo thành đơn DRV-%d.', $existing_id));
            }
        }

        $boq = self::boqForLead($lead_id);
        $lines = self::prepareLines($boq, $agreed_rates, [BoqService::class, 'loadProduct']);
        if ($lines instanceof \WP_Error) {
            return $lines;
        }

        $order = wc_create_order(['created_via' => 'drv_boq_lead']);
        if ($order instanceof \WP_Error) {
            return $order;
        }

        $order->set_billing_first_name((string) LeadRepository::meta($lead_id, '_drv_name'));
        $order->set_billing_phone((string) LeadRepository::meta($lead_id, '_drv_phone'));
        $order->set_billing_email((string) LeadRepository::meta($lead_id, '_drv_email'));
        $order->set_billing_country('VN');
        $order->set_billing_state((string) LeadRepository::meta($lead_id, '_drv_province'));
        $order->set_billing_city((string) LeadRepository::meta($lead_id, '_drv_ward'));
        $order->set_customer_note((string) LeadRepository::meta($lead_id, '_drv_message'));

        $payment  = ($ops['payment_method'] ?? 'bacs') === 'cod' ? 'cod' : 'bacs';
        $gateways = WC()->payment_gateways()->payment_gateways();
        if (isset($gateways[$payment])) {
            $order->set_payment_method($gateways[$payment]);
        } else {
            $order->set_payment_method($payment);
        }

        $total_m2 = 0.0;
        $total_bx = 0;
        $total_kg = 0.0;
        foreach ($lines as $line) {
            $product = wc_get_product((int) $line['product_id']);
            if (!$product instanceof \WC_Product) {
                continue;
            }
            $item = new \WC_Order_Item_Product();
            $item->set_product($product);
            $item->set_quantity((int) $line['pieces']);
            $item->set_subtotal((string) $line['line_subtotal']);
            $item->set_total((string) $line['line_total']);

            $item->add_meta_data('_drv_model', 'area_pack', true);
            $item->add_meta_data('_drv_unit', $line['unit_label'], true);
            $item->add_meta_data('_drv_price_m2', $line['agreed_price_m2'], true);
            $item->add_meta_data('_drv_area_piece', $line['area_piece'], true);
            $item->add_meta_data('_drv_pieces_per_box', $line['pieces_per_box'], true);
            $item->add_meta_data('_drv_kg_per_box', $line['kg_per_box'], true);
            $item->add_meta_data('_drv_m2_qty', $line['m2_qty'], true);
            $item->add_meta_data('_drv_boxes', $line['boxes'], true);
            $item->add_meta_data('_drv_loose_pieces', $line['loose'], true);
            $item->add_meta_data('_drv_kg', $line['kg'], true);
            $item->add_meta_data('_drv_m2_input', $line['target_m2'], true);
            $item->add_meta_data('_drv_waste_pct', $line['waste_pct'], true);
            $item->add_meta_data('_drv_lot_code', '', true);
            $item->add_meta_data('_drv_boq_room', $line['room_name'], true);
            $item->add_meta_data('_drv_boq_role', $line['role'], true);
            $order->add_item($item);

            $total_m2 += (float) $line['m2_qty'];
            $total_bx += (int) $line['boxes'];
            $total_kg += (float) $line['kg'];
        }

        $fee_raw  = $ops['shipping_fee'] ?? null;
        $fee      = ($fee_raw === null || $fee_raw === '') ? null : max(0, (int) $fee_raw);
        $statuses = function_exists('wc_get_order_statuses') ? array_keys(wc_get_order_statuses()) : [];
        $target   = self::resolveTargetStatus($fee, $payment, $statuses);

        $ship = new \WC_Order_Item_Shipping();
        $ship->set_method_id('drv_quote_later');
        if ($fee !== null) {
            $ship->set_method_title('Vận chuyển tận công trình');
            $ship->set_total((string) $fee);
            $order->update_meta_data('_drv_confirmed_at', current_time('c'));
            $order->update_meta_data('_drv_confirmed_by', (string) get_current_user_id());
        } else {
            $ship->set_method_title((string) drv_setting('order.shipping_label', 'Báo sau khi xác nhận đơn'));
            $ship->set_total('0');
        }
        $order->add_item($ship);
        $order->save();

        $order_id = $order->get_id();
        $code     = (string) LeadRepository::meta($lead_id, '_drv_quote_code');

        $order->update_meta_data('_drv_source_lead_id', (string) $lead_id);
        $order->update_meta_data('_drv_total_m2', (string) round($total_m2, 2));
        $order->update_meta_data('_drv_total_boxes', (string) $total_bx);
        $order->update_meta_data('_drv_total_kg', (string) round($total_kg, 1));
        $order->update_meta_data('_drv_transfer_ref', 'DRV' . $order_id);
        $order->update_meta_data('_drv_ship_vehicle', sanitize_text_field((string) ($ops['ship_vehicle'] ?? '')));
        $order->update_meta_data('_drv_ship_note', sanitize_text_field((string) ($ops['ship_note'] ?? '')));

        $vat = is_array($ops['vat'] ?? null) ? $ops['vat'] : [];
        if (!empty($vat['required'])) {
            $order->update_meta_data('_drv_vat_required', '1');
            $order->update_meta_data('_drv_vat_company', sanitize_text_field((string) ($vat['company'] ?? '')));
            $order->update_meta_data('_drv_vat_tax_id', sanitize_text_field((string) ($vat['tax_id'] ?? '')));
            $order->update_meta_data('_drv_vat_address', sanitize_text_field((string) ($vat['address'] ?? '')));
            $order->update_meta_data('_drv_vat_email', sanitize_email((string) ($vat['email'] ?? '')));
        } else {
            $order->update_meta_data('_drv_vat_required', '0');
        }

        $order->calculate_totals(false);
        $order->set_status($target, sprintf('Khởi tạo từ phiếu báo giá #%s bởi nhân viên ID #%d.', $code, get_current_user_id()));
        $order->save();

        wc_maybe_reduce_stock_levels($order_id);

        update_post_meta($lead_id, '_drv_status', 'won');
        update_post_meta($lead_id, '_drv_converted_order_id', $order_id);
        update_post_meta($lead_id, '_drv_converted_at', current_time('c'));
        LeadRepository::flushNewCount();

        return $order;
    }

    /**
     * Snapshot BOQ của lead; lead đơn (không qua BOQ) có `_drv_product_id` được quy về 1 dòng `Hạng mục chung`.
     *
     * @return array<string, mixed>
     */
    public static function boqForLead(int $lead_id): array {
        $boq = LeadRepository::boq($lead_id);
        if (!empty($boq['rooms'])) {
            return $boq;
        }

        $pid  = (int) LeadRepository::meta($lead_id, '_drv_product_id', 0);
        $area = (float) LeadRepository::meta($lead_id, '_drv_area_m2', 0);
        if ($pid <= 0 || $area <= 0.0) {
            return [];
        }

        $service  = new BoqService();
        $computed = $service->compute([[
            'name'  => BoqService::DEFAULT_ROOM,
            'slots' => [['role' => 'Lát nền chính', 'product_id' => $pid, 'input_mode' => 'm2', 'target_m2' => $area, 'pieces' => 0]],
        ]], (int) drv_setting('order.default_waste_pct', 5));

        return ['waste_pct' => (int) drv_setting('order.default_waste_pct', 5), 'rooms' => $computed['rooms']];
    }
}
