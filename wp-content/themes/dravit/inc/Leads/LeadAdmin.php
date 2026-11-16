<?php
declare(strict_types=1);

namespace Dravit\Leads;

use Dravit\Core\ServiceProviderInterface;
use Dravit\Quote\BoqService;
use Dravit\Quote\OrderConverter;

defined('ABSPATH') || exit;

/**
 * Màn hình quản trị `drv_lead` (docs/17 §5.1 – §5.2): 8 cột, bộ lọc, tìm kiếm meta, 3 metabox,
 * nút "Tạo đơn hàng từ báo giá" (`OrderConverter`) và xuất CSV UTF-8 BOM chống Formula Injection.
 */
final class LeadAdmin implements ServiceProviderInterface {
    private const SAVE_NONCE    = 'drv_lead_save';
    private const CONVERT_NONCE = 'drv_convert_lead';
    private const EXPORT_NONCE  = 'drv_export_leads';

    public function register(): void {
        $pt = LeadRepository::POST_TYPE;
        add_filter("manage_{$pt}_posts_columns", [$this, 'columns']);
        add_action("manage_{$pt}_posts_custom_column", [$this, 'render_column'], 10, 2);
        add_filter('post_row_actions', [$this, 'row_actions'], 10, 2);
        add_action('restrict_manage_posts', [$this, 'filters']);
        add_action('pre_get_posts', [$this, 'apply_filters']);
        add_action('add_meta_boxes_' . $pt, [$this, 'meta_boxes']);
        add_action('save_post_' . $pt, [$this, 'save'], 10, 2);
        add_action('admin_post_drv_convert_lead_to_order', [$this, 'handle_convert']);
        add_action('admin_post_drv_export_leads_csv', [$this, 'handle_export']);
        add_action('admin_notices', [$this, 'notices']);
        add_action('admin_head', [$this, 'admin_css']);
    }

    /**
     * @param array<string, string> $cols
     * @return array<string, string>
     */
    public function columns(array $cols): array {
        return [
            'cb'           => $cols['cb'] ?? '<input type="checkbox">',
            'drv_code'     => 'Mã phiếu',
            'drv_type'     => 'Nguồn',
            'drv_customer' => 'Khách hàng & SĐT',
            'drv_role'     => 'Vai trò / Khu vực',
            'drv_volume'   => 'Khối lượng',
            'drv_estimate' => 'Dự toán',
            'drv_status'   => 'Trạng thái',
            'drv_date'     => 'Thời gian',
        ];
    }

    public function render_column(string $col, int $post_id): void {
        $m = static fn (string $k, mixed $d = ''): mixed => LeadRepository::meta($post_id, $k, $d);
        switch ($col) {
            case 'drv_code':
                printf(
                    '<a class="row-title" href="%s"><strong>#%s</strong></a>%s',
                    esc_url((string) get_edit_post_link($post_id)),
                    esc_html((string) $m('_drv_quote_code')),
                    $m('_drv_sample_box') === '1' ? ' <span class="dashicons dashicons-archive" title="Yêu cầu hộp mẫu"></span>' : ''
                );
                break;
            case 'drv_type':
                $type = (string) $m('_drv_lead_type');
                printf('<span class="drv-lead-pill drv-lead-pill--%s">%s</span>', esc_attr($type), esc_html(LeadValidator::SOURCE_LABELS[$type] ?? $type));
                break;
            case 'drv_customer':
                $phone = (string) $m('_drv_phone');
                printf(
                    '%s<br><span class="drv-lead-mono">%s</span> · <a href="%s">Gọi</a> · <a href="%s" target="_blank" rel="noopener">Zalo</a>',
                    esc_html((string) $m('_drv_name')),
                    esc_html($phone),
                    esc_url('tel:' . $phone),
                    esc_url('https://zalo.me/' . $phone)
                );
                break;
            case 'drv_role':
                echo esc_html(LeadValidator::ROLES[(string) $m('_drv_role')] ?? '');
                $prov = LeadNotifier::provinceName((string) $m('_drv_province'));
                echo $prov !== '' ? '<br>' . esc_html($prov) : '';
                break;
            case 'drv_volume':
                if ($m('_drv_lead_type') === 'boq') {
                    echo esc_html(sprintf('%s m² · %d thùng · %s kg', drv_format_decimal((float) $m('_drv_total_m2', 0), 2), (int) $m('_drv_total_boxes', 0), drv_format_decimal((float) $m('_drv_total_kg', 0), 0)));
                } elseif ((float) $m('_drv_area_m2', 0) > 0) {
                    echo esc_html(drv_format_decimal((float) $m('_drv_area_m2', 0), 2) . ' m²');
                }
                $pid = (int) $m('_drv_product_id', 0);
                if ($pid > 0 && function_exists('wc_get_product') && ($p = wc_get_product($pid)) instanceof \WC_Product) {
                    echo '<br><span class="drv-lead-mono">' . esc_html($p->get_sku()) . '</span>';
                }
                break;
            case 'drv_estimate':
                if ($m('_drv_lead_type') === 'boq') {
                    echo '<span class="drv-lead-mono">' . esc_html(drv_format_vnd((int) $m('_drv_grand_est_vnd', 0))) . '</span>';
                    if ($m('_drv_has_quote_items') === '1') {
                        echo '<br><span class="drv-lead-pill drv-lead-pill--warn">+ Mẫu báo giá riêng</span>';
                    }
                }
                break;
            case 'drv_status':
                $status = (string) $m('_drv_status', 'new');
                printf('<span class="drv-lead-pill drv-lead-status--%s">%s</span>', esc_attr($status), esc_html(LeadRepository::STATUS_LABELS[$status] ?? $status));
                $oid = (int) $m('_drv_converted_order_id', 0);
                if ($oid > 0 && function_exists('wc_get_order') && ($o = wc_get_order($oid)) instanceof \WC_Order) {
                    printf('<br><a href="%s">#DRV-%d</a>', esc_url($o->get_edit_order_url()), $oid);
                }
                break;
            case 'drv_date':
                $ts = (int) get_post_time('U', true, $post_id);
                echo esc_html(wp_date('H:i d/m/Y', $ts));
                if ($m('_drv_status', 'new') === 'new' && time() - $ts > 30 * MINUTE_IN_SECONDS) {
                    echo '<br><span class="drv-lead-late">Quá 30p chưa xử lý</span>';
                }
                break;
        }
    }

    /**
     * @param array<string, string> $actions
     * @return array<string, string>
     */
    public function row_actions(array $actions, \WP_Post $post): array {
        if ($post->post_type !== LeadRepository::POST_TYPE) {
            return $actions;
        }
        unset($actions['inline hide-if-no-js'], $actions['view']);
        return $actions;
    }

    public function filters(string $post_type): void {
        if ($post_type !== LeadRepository::POST_TYPE) {
            return;
        }
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Bộ lọc GET chỉ đọc.
        $selects = [
            'drv_status' => ['Mọi trạng thái', LeadRepository::STATUS_LABELS],
            'drv_type'   => ['Mọi nguồn', LeadValidator::SOURCE_LABELS],
            'drv_role'   => ['Mọi vai trò', LeadValidator::ROLES],
        ];
        foreach ($selects as $name => [$placeholder, $options]) {
            $current = isset($_GET[$name]) ? sanitize_key(wp_unslash((string) $_GET[$name])) : '';
            printf('<select name="%s"><option value="">%s</option>', esc_attr($name), esc_html($placeholder));
            foreach ($options as $val => $label) {
                printf('<option value="%s"%s>%s</option>', esc_attr($val), selected($current, $val, false), esc_html($label));
            }
            echo '</select>';
        }
        // phpcs:enable
        $query  = [];
        foreach (['drv_status', 'drv_type', 'drv_role', 'm', 's'] as $k) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Chuyển tiếp bộ lọc GET sang nút xuất CSV.
            if (!empty($_GET[$k])) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $query[$k] = sanitize_text_field(wp_unslash((string) $_GET[$k]));
            }
        }
        $export = wp_nonce_url(add_query_arg(['action' => 'drv_export_leads_csv'] + $query, admin_url('admin-post.php')), self::EXPORT_NONCE);
        printf('<a class="button" href="%s">Xuất CSV theo bộ lọc hiện tại</a>', esc_url($export));
    }

    public function apply_filters(\WP_Query $query): void {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== LeadRepository::POST_TYPE) {
            return;
        }
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Bộ lọc GET chỉ đọc.
        $args = self::filterArgs($_GET);
        // phpcs:enable
        foreach ($args as $k => $v) {
            $query->set($k, $v);
        }
    }

    /**
     * @param array<string, mixed> $get
     * @return array<string, mixed>
     */
    private static function filterArgs(array $get): array {
        $meta = [];
        $map  = ['drv_status' => '_drv_status', 'drv_type' => '_drv_lead_type', 'drv_role' => '_drv_role'];
        foreach ($map as $param => $key) {
            $val = isset($get[$param]) ? sanitize_key(wp_unslash((string) $get[$param])) : '';
            if ($val !== '') {
                $meta[] = ['key' => $key, 'value' => $val];
            }
        }

        $out = [];
        $s   = isset($get['s']) ? trim(sanitize_text_field(wp_unslash((string) $get['s']))) : '';
        if ($s !== '') {
            // Tìm xuyên meta: mã phiếu, tên, SĐT, công trình, SKU trong snapshot BOQ.
            $or = ['relation' => 'OR'];
            foreach (['_drv_quote_code', '_drv_name', '_drv_phone', '_drv_project', '_drv_boq'] as $key) {
                $or[] = ['key' => $key, 'value' => ltrim($s, '#'), 'compare' => 'LIKE'];
            }
            $meta[]   = $or;
            $out['s'] = '';
        }
        if ($meta !== []) {
            $out['meta_query'] = ['relation' => 'AND'] + $meta; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        }
        return $out;
    }

    public function meta_boxes(\WP_Post $post): void {
        remove_meta_box('submitdiv', LeadRepository::POST_TYPE, 'side');
        add_meta_box('drv-lead-profile', 'A. Hồ sơ khách hàng & bối cảnh yêu cầu', [$this, 'box_profile'], null, 'normal', 'high');
        add_meta_box('drv-lead-boq', 'B. Bóc tách khối lượng & chốt giá đơn hàng', [$this, 'box_boq'], null, 'normal', 'high');
        add_meta_box('drv-lead-status', 'C. Trạng thái & tạo đơn', [$this, 'box_status'], null, 'side', 'high');
        unset($post);
    }

    public function box_profile(\WP_Post $post): void {
        $id = $post->ID;
        $m  = static fn (string $k): string => (string) LeadRepository::meta($id, $k, '');
        wp_nonce_field(self::SAVE_NONCE, '_drv_lead_nonce');
        $provinces = (array) require DRV_DIR . '/config/provinces-vn.php';
        ?>
        <table class="form-table drv-lead-form">
            <tr><th>Mã phiếu</th><td><strong>#<?php echo esc_html($m('_drv_quote_code')); ?></strong> · <?php echo esc_html(LeadValidator::SOURCE_LABELS[$m('_drv_lead_type')] ?? $m('_drv_lead_type')); ?></td></tr>
            <tr><th><label for="drv_lead_name">Họ tên</label></th><td><input id="drv_lead_name" class="regular-text" name="drv_lead[name]" value="<?php echo esc_attr($m('_drv_name')); ?>"></td></tr>
            <tr><th><label for="drv_lead_phone">Điện thoại</label></th><td><input id="drv_lead_phone" class="regular-text" name="drv_lead[phone]" value="<?php echo esc_attr($m('_drv_phone')); ?>"> <a class="button" href="<?php echo esc_url('https://zalo.me/' . $m('_drv_phone')); ?>" target="_blank" rel="noopener">Zalo</a></td></tr>
            <tr><th><label for="drv_lead_email">Email</label></th><td><input id="drv_lead_email" type="email" class="regular-text" name="drv_lead[email]" value="<?php echo esc_attr($m('_drv_email')); ?>"></td></tr>
            <tr><th><label for="drv_lead_province">Tỉnh / TP</label></th><td>
                <select id="drv_lead_province" name="drv_lead[province]"><option value="">-</option>
                    <?php foreach ($provinces as $code => $label) : ?>
                        <option value="<?php echo esc_attr((string) $code); ?>" <?php selected($m('_drv_province'), (string) $code); ?>><?php echo esc_html((string) $label); ?></option>
                    <?php endforeach; ?>
                </select>
                <input class="regular-text" name="drv_lead[ward]" placeholder="Phường / Xã" value="<?php echo esc_attr($m('_drv_ward')); ?>">
            </td></tr>
            <tr><th><label for="drv_lead_role">Vai trò</label></th><td><select id="drv_lead_role" name="drv_lead[role]">
                <?php foreach (LeadValidator::ROLES as $val => $label) : ?>
                    <option value="<?php echo esc_attr($val); ?>" <?php selected($m('_drv_role'), $val); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select></td></tr>
            <tr><th><label for="drv_lead_project">Công trình</label></th><td><input id="drv_lead_project" class="large-text" name="drv_lead[project]" value="<?php echo esc_attr($m('_drv_project')); ?>"></td></tr>
            <tr><th><label for="drv_lead_message">Ghi chú khách</label></th><td><textarea id="drv_lead_message" class="large-text" rows="3" name="drv_lead[message]"><?php echo esc_textarea($m('_drv_message')); ?></textarea></td></tr>
            <tr><th>Hộp mẫu</th><td><label><input type="checkbox" name="drv_lead[sample_box]" value="1" <?php checked($m('_drv_sample_box'), '1'); ?>> Khách yêu cầu gửi mẫu thật</label></td></tr>
            <tr><th>Truy vết</th><td class="drv-lead-muted">
                Trang gửi: <?php echo $m('_drv_page_url') !== '' ? '<a href="' . esc_url($m('_drv_page_url')) . '" target="_blank" rel="noopener">' . esc_html($m('_drv_page_url')) . '</a>' : '-'; ?><br>
                UTM: <code><?php echo esc_html($m('_drv_utm') ?: '-'); ?></code><br>
                Đồng ý xử lý dữ liệu lúc: <?php echo esc_html($m('_drv_consent_at') ?: '-'); ?>
                <?php if ($m('_drv_preferred_time') !== '') : ?><br>Lịch hẹn: <strong><?php echo esc_html($m('_drv_preferred_time')); ?></strong><?php endif; ?>
            </td></tr>
        </table>
        <?php
    }

    public function box_boq(\WP_Post $post): void {
        $id    = $post->ID;
        $boq   = OrderConverter::boqForLead($id);
        $rate  = (float) LeadRepository::meta($id, '_drv_discount_rate', 0);
        $rooms = is_array($boq['rooms'] ?? null) ? $boq['rooms'] : [];
        $done  = (int) LeadRepository::meta($id, '_drv_converted_order_id', 0) > 0;

        if ($rooms === []) {
            echo '<p class="drv-lead-muted">Lead này không có bảng khối lượng (chưa gắn sản phẩm + diện tích). Liên hệ khách để lập BOQ.</p>';
            return;
        }
        ?>
        <p class="drv-lead-muted">Hao hụt tính sẵn: <strong><?php echo (int) ($boq['waste_pct'] ?? 0); ?>%</strong> · Chiết khấu dự kiến: <strong><?php echo esc_html(drv_format_decimal($rate, 0)); ?>%</strong> (giá chốt điền sẵn đã trừ chiết khấu, sửa theo thỏa thuận cuối).</p>
        <table class="widefat striped drv-lead-boq">
            <thead><tr><th>Phòng / Hạng mục</th><th>SKU & Tên gạch</th><th>Tồn kho</th><th>Diện tích</th><th>Quy cách xuất</th><th>kg</th><th>Niêm yết đ/m²</th><th>Đơn giá chốt đ/m²</th></tr></thead>
            <tbody>
            <?php foreach (array_values($rooms) as $r => $room) :
                foreach (array_values((array) ($room['slots'] ?? [])) as $s => $slot) :
                    $pid      = (int) ($slot['product_id'] ?? 0);
                    $product  = function_exists('wc_get_product') ? wc_get_product($pid) : null;
                    $stock    = $product instanceof \WC_Product && $product->managing_stock() ? (int) $product->get_stock_quantity() : null;
                    $need     = (int) ($slot['pieces'] ?? 0);
                    $price_m2 = (int) ($slot['price_m2'] ?? 0);
                    $default  = $price_m2 > 0 ? (int) round($price_m2 * (1 - $rate / 100)) : '';
                    ?>
                    <tr>
                        <td><?php echo esc_html((string) ($room['name'] ?? '')); ?><br><span class="drv-lead-muted"><?php echo esc_html((string) ($slot['role'] ?? '')); ?></span></td>
                        <td><span class="drv-lead-mono"><?php echo esc_html((string) ($slot['sku'] ?? '')); ?></span><br><?php echo esc_html((string) ($slot['name'] ?? '')); ?></td>
                        <td><?php
                        if ($stock === null) {
                            echo '<span class="drv-lead-muted">Không quản lý kho</span>';
                        } elseif ($stock >= $need) {
                            printf('<span class="drv-lead-ok">Đủ kho (%d)</span>', (int) $stock);
                        } else {
                            printf('<span class="drv-lead-late">Thiếu kho (cần %d, còn %d)</span>', (int) $need, (int) $stock);
                        }
                        ?></td>
                        <td class="drv-lead-mono"><?php echo esc_html(drv_format_decimal((float) ($slot['actual_m2'] ?? 0), 2)); ?> m²</td>
                        <td><?php echo esc_html((string) ($slot['box_text'] ?? '')); ?></td>
                        <td class="drv-lead-mono"><?php echo esc_html(drv_format_decimal((float) ($slot['kg'] ?? 0), 1)); ?></td>
                        <td class="drv-lead-mono"><?php echo $price_m2 > 0 ? esc_html(drv_format_vnd($price_m2)) : '<em>Liên hệ</em>'; ?></td>
                        <td><input type="text" inputmode="numeric" class="drv-lead-price<?php echo $price_m2 > 0 ? '' : ' is-required'; ?>"
                                   form="drv-lead-convert-form"
                                   name="drv_agreed_price_m2[<?php echo esc_attr($r . ':' . $s); ?>]"
                                   value="<?php echo esc_attr((string) $default); ?>"
                                   placeholder="<?php echo $price_m2 > 0 ? '' : esc_attr('Nhập giá chốt / m² (*)'); ?>"
                                   <?php disabled($done); ?>></td>
                    </tr>
                <?php endforeach;
            endforeach; ?>
            </tbody>
        </table>
        <?php if (!$done) : ?>
            <h4>Vận chuyển, thanh toán & hóa đơn</h4>
            <table class="form-table drv-lead-form">
                <tr><th><label for="drv_ship_fee">Phí vận chuyển thỏa thuận (VND)</label></th><td><input id="drv_ship_fee" form="drv-lead-convert-form" name="drv_ops[shipping_fee]" inputmode="numeric" placeholder="Để trống = tạo đơn Chờ xác nhận"></td></tr>
                <tr><th><label for="drv_pay">Phương thức thanh toán</label></th><td><select id="drv_pay" form="drv-lead-convert-form" name="drv_ops[payment_method]"><option value="bacs">Chuyển khoản VietQR</option><option value="cod">COD (thanh toán khi nhận)</option></select></td></tr>
                <tr><th><label for="drv_vehicle">Phương tiện điều phối</label></th><td><input id="drv_vehicle" form="drv-lead-convert-form" name="drv_ops[ship_vehicle]" class="regular-text"></td></tr>
                <tr><th><label for="drv_shipnote">Ghi chú giao nhận</label></th><td><input id="drv_shipnote" form="drv-lead-convert-form" name="drv_ops[ship_note]" class="large-text"></td></tr>
                <tr><th>Hóa đơn VAT</th><td>
                    <label><input type="checkbox" form="drv-lead-convert-form" name="drv_ops[vat][required]" value="1"> Xuất hóa đơn GTGT</label><br>
                    <input form="drv-lead-convert-form" name="drv_ops[vat][company]" class="regular-text" placeholder="Tên công ty">
                    <input form="drv-lead-convert-form" name="drv_ops[vat][tax_id]" placeholder="Mã số thuế">
                    <input form="drv-lead-convert-form" name="drv_ops[vat][address]" class="regular-text" placeholder="Địa chỉ ĐKKD">
                    <input form="drv-lead-convert-form" name="drv_ops[vat][email]" type="email" placeholder="Email kế toán">
                </td></tr>
            </table>
        <?php endif;
    }

    public function box_status(\WP_Post $post): void {
        $id     = $post->ID;
        $status = (string) LeadRepository::meta($id, '_drv_status', 'new');
        $oid    = (int) LeadRepository::meta($id, '_drv_converted_order_id', 0);
        $order  = $oid > 0 && function_exists('wc_get_order') ? wc_get_order($oid) : null;
        ?>
        <p><label for="drv_lead_status"><strong>Trạng thái xử lý</strong></label><br>
            <select id="drv_lead_status" name="drv_lead[status]" style="width:100%">
                <?php foreach (LeadRepository::STATUS_LABELS as $val => $label) : ?>
                    <option value="<?php echo esc_attr($val); ?>" <?php selected($status, $val); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select></p>
        <p><label for="drv_lead_note"><strong>Ghi chú nội bộ</strong></label><br>
            <textarea id="drv_lead_note" name="drv_lead[admin_note]" rows="4" style="width:100%"><?php echo esc_textarea((string) LeadRepository::meta($id, '_drv_admin_note', '')); ?></textarea></p>
        <input type="hidden" name="post_status" value="private">
        <input type="hidden" name="visibility" value="private">
        <p><button type="submit" name="save" value="1" class="button button-primary" style="width:100%">Lưu cập nhật Lead</button></p>
        <hr>
        <?php if ($order instanceof \WC_Order && !in_array($order->get_status(), ['cancelled', 'trash', 'failed'], true)) : ?>
            <div class="drv-lead-done">
                Đã tạo đơn <a href="<?php echo esc_url($order->get_edit_order_url()); ?>"><strong>#DRV-<?php echo (int) $oid; ?></strong></a><br>
                <span class="drv-lead-muted"><?php echo esc_html((string) LeadRepository::meta($id, '_drv_converted_at', '')); ?></span>
            </div>
        <?php else : ?>
            <p class="drv-lead-muted">Kiểm tra giá chốt từng dòng ở hộp B rồi tạo đơn. Mẫu "Liên hệ" bắt buộc nhập giá &gt; 0.</p>
            <button type="submit" form="drv-lead-convert-form" class="button button-primary button-large" style="width:100%">Tạo đơn hàng từ báo giá</button>
        <?php endif;
        // Form tạo đơn nằm ngoài form post (tránh lồng form); các input ở hộp B gắn qua thuộc tính form="".
        add_action('admin_footer', static function () use ($id): void {
            printf(
                '<form id="drv-lead-convert-form" method="post" action="%s"><input type="hidden" name="action" value="drv_convert_lead_to_order"><input type="hidden" name="lead_id" value="%d">%s</form>',
                esc_url(admin_url('admin-post.php')),
                (int) $id,
                wp_nonce_field(self::CONVERT_NONCE . '_' . $id, '_drv_convert_nonce', true, false) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            );
        });
    }

    public function save(int $post_id, \WP_Post $post): void {
        unset($post);
        if (
            !isset($_POST['_drv_lead_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['_drv_lead_nonce'])), self::SAVE_NONCE)
            || !current_user_can('manage_woocommerce')
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        ) {
            return;
        }
        $in = isset($_POST['drv_lead']) && is_array($_POST['drv_lead']) ? wp_unslash($_POST['drv_lead']) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Làm sạch từng trường bên dưới.

        $phone = LeadValidator::normalizePhone((string) ($in['phone'] ?? ''));
        $map   = [
            '_drv_name'       => LeadValidator::text($in['name'] ?? '', 80),
            '_drv_email'      => sanitize_email((string) ($in['email'] ?? '')),
            '_drv_province'   => strtoupper(sanitize_key((string) ($in['province'] ?? ''))),
            '_drv_ward'       => LeadValidator::text($in['ward'] ?? '', 80),
            '_drv_role'       => array_key_exists((string) ($in['role'] ?? ''), LeadValidator::ROLES) ? (string) $in['role'] : 'chu-nha',
            '_drv_project'    => LeadValidator::text($in['project'] ?? '', 120),
            '_drv_message'    => LeadValidator::textarea($in['message'] ?? '', 1000),
            '_drv_sample_box' => !empty($in['sample_box']) ? '1' : '0',
            '_drv_admin_note' => LeadValidator::textarea($in['admin_note'] ?? '', 2000),
        ];
        if ($phone !== '') {
            $map['_drv_phone'] = $phone;
        }
        $status = (string) ($in['status'] ?? '');
        if (in_array($status, LeadRepository::STATUSES, true)) {
            $map['_drv_status'] = $status;
        }
        foreach ($map as $k => $v) {
            update_post_meta($post_id, $k, $v);
        }
        LeadRepository::flushNewCount();
    }

    public function handle_convert(): void {
        $lead_id = isset($_POST['lead_id']) ? absint($_POST['lead_id']) : 0;
        check_admin_referer(self::CONVERT_NONCE . '_' . $lead_id, '_drv_convert_nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Bạn không có quyền tạo đơn hàng.', '', ['response' => 403]);
        }

        $rates = isset($_POST['drv_agreed_price_m2']) && is_array($_POST['drv_agreed_price_m2'])
            ? array_map(static fn ($v): string => (string) preg_replace('/\D/', '', (string) $v), wp_unslash($_POST['drv_agreed_price_m2']))
            : [];
        $ops_raw = isset($_POST['drv_ops']) && is_array($_POST['drv_ops']) ? wp_unslash($_POST['drv_ops']) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Làm sạch trong OrderConverter.
        $fee_raw = trim((string) ($ops_raw['shipping_fee'] ?? ''));
        $ops     = [
            'shipping_fee'   => $fee_raw === '' ? null : (int) preg_replace('/\D/', '', $fee_raw),
            'payment_method' => ($ops_raw['payment_method'] ?? 'bacs') === 'cod' ? 'cod' : 'bacs',
            'ship_vehicle'   => (string) ($ops_raw['ship_vehicle'] ?? ''),
            'ship_note'      => (string) ($ops_raw['ship_note'] ?? ''),
            'vat'            => is_array($ops_raw['vat'] ?? null) ? $ops_raw['vat'] : [],
        ];

        $result   = OrderConverter::convert($lead_id, $rates, $ops);
        $redirect = (string) get_edit_post_link($lead_id, 'url');
        if ($result instanceof \WP_Error) {
            set_transient('drv_lead_notice_' . get_current_user_id(), ['type' => 'error', 'msg' => $result->get_error_message()], 60);
        } else {
            set_transient('drv_lead_notice_' . get_current_user_id(), ['type' => 'success', 'msg' => sprintf('Đã tạo đơn hàng #DRV-%d từ phiếu báo giá.', $result->get_id())], 60);
        }
        wp_safe_redirect($redirect);
        exit;
    }

    public function notices(): void {
        $key    = 'drv_lead_notice_' . get_current_user_id();
        $notice = get_transient($key);
        if (!is_array($notice)) {
            return;
        }
        delete_transient($key);
        printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr($notice['type'] === 'error' ? 'error' : 'success'), esc_html((string) $notice['msg']));
    }

    public function handle_export(): void {
        check_admin_referer(self::EXPORT_NONCE);
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Không có quyền.', '', ['response' => 403]);
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Đã check_admin_referer phía trên.
        $args = self::filterArgs($_GET) + [
            'post_type'      => LeadRepository::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => 2000,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ];
        $ids = get_posts($args);

        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="dravit-leads-' . gmdate('Ymd') . '.csv"');
        echo "\xEF\xBB\xBF"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UTF-8 BOM.

        $head = ['Mã phiếu', 'Nguồn', 'Trạng thái', 'Họ tên', 'SĐT', 'Email', 'Tỉnh/TP', 'Vai trò', 'Công trình', 'Tổng m²', 'Dự toán (VND)', 'Ghi chú', 'Thời gian'];
        echo implode(',', array_map([self::class, 'csvCell'], $head)) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        foreach ($ids as $id) {
            $id  = $id instanceof \WP_Post ? $id->ID : (int) $id;
            $m   = static fn (string $k): string => (string) LeadRepository::meta($id, $k, '');
            $row = [
                $m('_drv_quote_code'),
                LeadValidator::SOURCE_LABELS[$m('_drv_lead_type')] ?? $m('_drv_lead_type'),
                LeadRepository::STATUS_LABELS[$m('_drv_status')] ?? $m('_drv_status'),
                $m('_drv_name'),
                $m('_drv_phone'),
                $m('_drv_email'),
                LeadNotifier::provinceName($m('_drv_province')),
                LeadValidator::ROLES[$m('_drv_role')] ?? '',
                $m('_drv_project'),
                $m('_drv_lead_type') === 'boq' ? $m('_drv_total_m2') : $m('_drv_area_m2'),
                $m('_drv_grand_est_vnd'),
                $m('_drv_message'),
                wp_date('Y-m-d H:i', (int) get_post_time('U', true, $id)),
            ];
            echo implode(',', array_map([self::class, 'csvCell'], $row)) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        exit;
    }

    /**
     * Ô CSV an toàn: bỏ xuống dòng, vô hiệu hóa công thức (`= + - @ \t \r`) và bọc ngoặc kép (docs/17 §6.2).
     */
    public static function csvCell(mixed $raw): string {
        $str  = trim((string) preg_replace('/\r?\n/', ' ', is_scalar($raw) ? (string) $raw : ''));
        $safe = preg_match('/^[=+\-@\t\r]/', $str) === 1 ? "'" . $str : $str;
        return '"' . str_replace('"', '""', $safe) . '"';
    }

    public function admin_css(): void {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== LeadRepository::POST_TYPE) {
            return;
        }
        ?>
        <style>
            .drv-lead-pill{display:inline-block;padding:2px 8px;border-radius:999px;background:#eef2f6;font-size:12px;font-weight:600}
            .drv-lead-pill--boq{background:#083a4f;color:#fff}.drv-lead-pill--warn{background:#fff4e5;color:#9a5b00}
            .drv-lead-status--new{background:#fde8e8;color:#b42318}.drv-lead-status--contacted{background:#fff4e5;color:#9a5b00}
            .drv-lead-status--quoted{background:#e8f1fb;color:#175cd3}.drv-lead-status--won{background:#e7f6ec;color:#15803d}
            .drv-lead-status--lost{background:#f2f4f7;color:#667085}.drv-lead-mono{font-variant-numeric:tabular-nums;font-family:ui-monospace,monospace}
            .drv-lead-late{color:#b42318;font-weight:600}.drv-lead-ok{color:#15803d;font-weight:600}.drv-lead-muted{color:#667085}
            .drv-lead-price{width:120px}.drv-lead-price.is-required{border-color:#d97706;box-shadow:0 0 0 1px #d97706}
            .drv-lead-done{padding:10px;background:#e7f6ec;border:1px solid #15803d}
            .drv-lead-boq td{vertical-align:top}
        </style>
        <?php
    }
}
