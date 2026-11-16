<?php
declare(strict_types=1);

namespace Dravit\Leads;

defined('ABSPATH') || exit;

/**
 * Email HTML nội bộ cho bộ phận kinh doanh ngay khi có Lead / BOQ mới (docs/17 §6.4).
 * Không dùng em-dash, không emoji (Q-12).
 */
final class LeadNotifier {
    public static function send(int $lead_id): bool {
        if (!function_exists('wp_mail')) {
            return false;
        }

        $recipients = array_values(array_filter((array) drv_setting('quote.notify_emails', [])));
        if ($recipients === []) {
            $fallback   = (string) drv_setting('contact.project_email', '') ?: (string) get_option('admin_email');
            $recipients = $fallback !== '' ? [$fallback] : [];
        }
        if ($recipients === []) {
            return false;
        }

        $type     = (string) LeadRepository::meta($lead_id, '_drv_lead_type', 'contact');
        $code     = (string) LeadRepository::meta($lead_id, '_drv_quote_code', '');
        $name     = (string) LeadRepository::meta($lead_id, '_drv_name', '');
        $phone    = (string) LeadRepository::meta($lead_id, '_drv_phone', '');
        $email    = (string) LeadRepository::meta($lead_id, '_drv_email', '');
        $province = self::provinceName((string) LeadRepository::meta($lead_id, '_drv_province', ''));

        $subject = self::subject($type, $code, $name, $phone, $province, (float) LeadRepository::meta($lead_id, '_drv_total_m2', 0));
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        if ($email !== '' && is_email($email)) {
            $headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
        }

        return (bool) wp_mail($recipients, $subject, self::body($lead_id), $headers);
    }

    public static function subject(string $type, string $code, string $name, string $phone, string $province, float $total_m2): string {
        if ($type === 'boq') {
            return sprintf('[DRAVIT BOQ #%s] %s - %s (%s m²)', $code, $name, $phone, drv_format_decimal($total_m2, 1));
        }
        $label = LeadValidator::SOURCE_LABELS[$type] ?? 'Liên hệ';
        return sprintf('[DRAVIT LEAD - %s] %s - %s%s', $label, $name, $phone, $province !== '' ? ' (' . $province . ')' : '');
    }

    private static function body(int $lead_id): string {
        $get   = static fn (string $k): string => (string) LeadRepository::meta($lead_id, $k, '');
        $phone = $get('_drv_phone');
        $role  = LeadValidator::ROLES[$get('_drv_role')] ?? '';
        $sla   = (string) drv_setting('order.sla_text', 'Phản hồi trong 30 phút giờ làm việc');

        $rows = [
            'Mã phiếu'     => '#' . $get('_drv_quote_code'),
            'Nguồn'        => LeadValidator::SOURCE_LABELS[$get('_drv_lead_type')] ?? $get('_drv_lead_type'),
            'Khách hàng'   => $get('_drv_name') . ($role !== '' ? ' (' . $role . ')' : ''),
            'Điện thoại'   => $phone,
            'Email'        => $get('_drv_email'),
            'Khu vực'      => trim(self::provinceName($get('_drv_province')) . ' ' . $get('_drv_ward')),
            'Công trình'   => $get('_drv_project'),
            'Diện tích'    => (float) $get('_drv_area_m2') > 0 ? drv_format_decimal((float) $get('_drv_area_m2'), 2) . ' m²' : '',
            'Ghi chú'      => $get('_drv_message'),
            'Trang gửi'    => $get('_drv_page_url'),
        ];

        $html  = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#0B192C;max-width:680px">';
        $html .= '<p style="margin:0 0 12px;padding:10px 12px;background:#F4F1EA;border:1px solid #CBD5E1"><strong>Yêu cầu mới cần xử lý.</strong> Cam kết: ' . esc_html($sla) . '</p>';
        $html .= '<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%">';
        foreach ($rows as $label => $value) {
            if ($value === '' || $value === '#') {
                continue;
            }
            $html .= '<tr><td style="border:1px solid #E2E8F0;width:140px;color:#64748B">' . esc_html($label) . '</td><td style="border:1px solid #E2E8F0">' . nl2br(esc_html($value)) . '</td></tr>';
        }
        $html .= '</table>';

        $boq = LeadRepository::boq($lead_id);
        if (!empty($boq['rooms']) && is_array($boq['rooms'])) {
            $html .= '<h3 style="margin:18px 0 8px">Bảng khối lượng theo phòng (hao hụt ' . (int) ($boq['waste_pct'] ?? 0) . '%)</h3>';
            $html .= '<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:13px">';
            $html .= '<tr style="background:#083A4F;color:#fff"><th align="left">Phòng / Hạng mục</th><th align="left">SKU</th><th align="right">Khối lượng</th><th align="right">Thành tiền</th></tr>';
            foreach ($boq['rooms'] as $room) {
                foreach ((array) ($room['slots'] ?? []) as $slot) {
                    $amount = !empty($slot['is_quote']) ? 'Chờ báo giá' : drv_format_vnd((int) ($slot['line_total'] ?? 0));
                    $html  .= '<tr>'
                        . '<td style="border:1px solid #E2E8F0">' . esc_html((string) ($room['name'] ?? '') . ' · ' . (string) ($slot['role'] ?? '')) . '</td>'
                        . '<td style="border:1px solid #E2E8F0">' . esc_html((string) ($slot['sku'] ?? '')) . '</td>'
                        . '<td style="border:1px solid #E2E8F0" align="right">' . esc_html((string) ($slot['box_text'] ?? '') . ' · ' . drv_format_decimal((float) ($slot['actual_m2'] ?? 0), 2) . ' m²') . '</td>'
                        . '<td style="border:1px solid #E2E8F0" align="right">' . esc_html($amount) . '</td>'
                        . '</tr>';
                }
            }
            $html .= '</table>';
            $html .= '<p style="margin:10px 0 0">Tổng: <strong>' . esc_html(drv_format_decimal((float) $get('_drv_total_m2'), 2) . ' m² · ' . (int) $get('_drv_total_boxes') . ' thùng · ' . drv_format_decimal((float) $get('_drv_total_kg'), 0) . ' kg') . '</strong>'
                . ' · Dự toán tạm tính: <strong>' . esc_html(drv_format_vnd((int) $get('_drv_grand_est_vnd'))) . '</strong>'
                . ($get('_drv_has_quote_items') === '1' ? ' (+ mẫu báo giá riêng)' : '') . '</p>';
        }

        $edit_url = function_exists('admin_url') ? admin_url('post.php?post=' . $lead_id . '&action=edit') : '';
        $html    .= '<p style="margin:20px 0 0">'
            . '<a href="' . esc_url($edit_url) . '" style="display:inline-block;padding:10px 16px;background:#083A4F;color:#fff;text-decoration:none;margin-right:8px">Mở phiếu xử lý trong Admin</a>'
            . '<a href="' . esc_url('https://zalo.me/' . $phone) . '" style="display:inline-block;padding:10px 16px;background:#4A6B3C;color:#fff;text-decoration:none">Nhắn Zalo cho khách</a>'
            . '</p></div>';

        return $html;
    }

    public static function provinceName(string $code): string {
        static $map = null;
        if ($map === null) {
            $file = DRV_DIR . '/config/provinces-vn.php';
            $map  = is_readable($file) ? (array) require $file : [];
        }
        return (string) ($map[$code] ?? $code);
    }
}
