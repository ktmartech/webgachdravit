<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

$legal_name = (string) drv_setting('company.legal_name', 'Công ty TNHH Vật Liệu Kiến Trúc DRAVIT');
$year       = gmdate('Y');
?>
<div class="drv-footer__bottom">
    <div class="drv-footer__legal">
        <span>© <?php echo esc_html($year); ?> <?php echo esc_html($legal_name); ?>. Bảo lưu mọi quyền.</span>
        <span>Đơn vị giao dịch chuẩn: Viên / Thùng nguyên đai · Giá niêm yết tham chiếu theo m² đã bao gồm VAT.</span>
    </div>
    <div class="drv-footer__trust-bar" aria-label="Phương thức thanh toán & giao nhận">
        <span class="drv-footer__pill">
            <i class="ph ph-qr-code drv-icon--xs" aria-hidden="true"></i>
            <span>VietQR 24/7</span>
        </span>
        <span class="drv-footer__pill">
            <i class="ph ph-package drv-icon--xs" aria-hidden="true"></i>
            <span>COD Đồng kiểm</span>
        </span>
        <span class="drv-footer__pill">
            <i class="ph ph-receipt drv-icon--xs" aria-hidden="true"></i>
            <span>Hóa đơn VAT</span>
        </span>
        <span class="drv-footer__pill">
            <i class="ph ph-truck drv-icon--xs" aria-hidden="true"></i>
            <span>Giao xe cẩu tận nơi</span>
        </span>
    </div>
</div>

