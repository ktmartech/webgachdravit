<?php
declare(strict_types=1);

use Dravit\Core\Menu\FooterMenuRenderer;
use Dravit\Core\Menu\MegaMenuRenderer;

defined('ABSPATH') || exit;

$home_url    = MegaMenuRenderer::internal_url('/');
$brand_name  = (string) drv_setting('company.brand_name', 'DRAVIT');
$legal_name  = (string) drv_setting('company.legal_name', 'Công ty TNHH Vật Liệu Kiến Trúc DRAVIT');
$tax_id      = (string) drv_setting('company.tax_id', '0318688999');
$address     = (string) drv_setting('company.address', '68 Nguyễn Cơ Thạch, Phường An Khánh, TP. Hồ Chí Minh');
$email       = (string) drv_setting('company.email', 'cskh@gachdravit.vn');
$bct_url     = trim((string) drv_setting('company.bct_url', ''));
$hotline_raw = (string) drv_setting('contact.hotline', '0909 888 777');
$hotline_tel = drv_clean_phone($hotline_raw);
$zalo_clean  = drv_clean_phone((string) drv_setting('contact.zalo', $hotline_raw));
$hours_text  = (string) drv_setting('contact.working_hours', '08:00 - 21:00');

$facebook  = trim((string) drv_setting('social.facebook', ''));
$youtube   = trim((string) drv_setting('social.youtube', ''));
$tiktok    = trim((string) drv_setting('social.tiktok', ''));
$instagram = trim((string) drv_setting('social.instagram', ''));
?>
<div class="drv-footer__grid">
    <div class="l-stack l-stack--sm">
        <a href="<?php echo esc_url($home_url); ?>" class="drv-logo" aria-label="<?php echo esc_attr($brand_name . ' - Trang chủ'); ?>">
            <?php drv_render_brand_logo('footer', true); ?>
        </a>
        <div>
            <h3 class="drv-footer__corp-name"><?php echo esc_html(mb_strtoupper($legal_name, 'UTF-8')); ?></h3>
            <ul class="drv-footer__contact-list">
                <?php if ($tax_id !== '') : ?>
                    <li>
                        <i class="ph ph-identification-card" aria-hidden="true"></i>
                        <span>MST: <strong class="u-tabular"><?php echo esc_html($tax_id); ?></strong></span>
                    </li>
                <?php endif; ?>
                <li>
                    <i class="ph ph-phone-call" aria-hidden="true"></i>
                    <span>Hotline: <a href="<?php echo esc_url('tel:' . $hotline_tel); ?>" class="u-tabular u-bold"><?php echo esc_html($hotline_raw); ?></a> (<?php echo esc_html($hours_text); ?>)</span>
                </li>
                <li>
                    <i class="ph ph-envelope-simple" aria-hidden="true"></i>
                    <span>Email: <a href="<?php echo esc_url('mailto:' . $email); ?>"><?php echo esc_html($email); ?></a></span>
                </li>
                <li>
                    <i class="ph ph-map-pin" aria-hidden="true"></i>
                    <span>Showroom: <?php echo esc_html($address); ?></span>
                </li>
            </ul>
        </div>

        <div class="drv-footer__social-group">
            <span class="drv-footer__social-label">Kênh kết nối chính thức</span>
            <div class="drv-footer__socials">
                <a href="<?php echo esc_url('https://zalo.me/' . $zalo_clean); ?>" class="drv-footer__social-btn drv-footer__social-btn--zalo" aria-label="Zalo DRAVIT" target="_blank" rel="noopener noreferrer">Zalo</a>
                <?php if ($facebook !== '') : ?>
                    <a href="<?php echo esc_url($facebook); ?>" class="drv-footer__social-btn" aria-label="Facebook DRAVIT" target="_blank" rel="noopener noreferrer"><i class="ph ph-facebook-logo" aria-hidden="true"></i></a>
                <?php endif; ?>
                <?php if ($youtube !== '') : ?>
                    <a href="<?php echo esc_url($youtube); ?>" class="drv-footer__social-btn" aria-label="YouTube DRAVIT" target="_blank" rel="noopener noreferrer"><i class="ph ph-youtube-logo" aria-hidden="true"></i></a>
                <?php endif; ?>
                <?php if ($tiktok !== '') : ?>
                    <a href="<?php echo esc_url($tiktok); ?>" class="drv-footer__social-btn" aria-label="TikTok DRAVIT" target="_blank" rel="noopener noreferrer"><i class="ph ph-tiktok-logo" aria-hidden="true"></i></a>
                <?php endif; ?>
                <?php if ($instagram !== '') : ?>
                    <a href="<?php echo esc_url($instagram); ?>" class="drv-footer__social-btn" aria-label="Instagram DRAVIT" target="_blank" rel="noopener noreferrer"><i class="ph ph-instagram-logo" aria-hidden="true"></i></a>
                <?php endif; ?>
            </div>
            <?php if ($bct_url !== '') : ?>
                <a href="<?php echo esc_url($bct_url); ?>" class="drv-footer__bct-badge" rel="nofollow noopener" target="_blank">
                    <i class="ph ph-shield-check drv-icon--xs" aria-hidden="true"></i>
                    <span>Đã thông báo Bộ Công Thương</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php
    FooterMenuRenderer::render_column(
        'footer_col_2',
        'Hướng dẫn',
        'ph-shopping-bag-open',
        [
            ['label' => 'Quy trình mua gạch & tính thùng', 'path' => '/huong-dan-mua-hang'],
            ['label' => 'Trợ lý AI tìm gạch theo phòng', 'path' => '/tro-ly-ai-tim-gach'],
            ['label' => 'Lập bảng báo giá BOQ công trình', 'path' => '/danh-sach-bao-gia'],
            ['label' => 'Cẩm nang chọn gạch & thi công', 'path' => '/cam-nang'],
        ]
    );

    FooterMenuRenderer::render_column(
        'footer_col_3',
        'Chính sách',
        'ph-shield-check',
        [
            ['label' => 'Giao hàng xe cẩu & đồng kiểm', 'path' => '/chinh-sach-van-chuyen'],
            ['label' => 'Đổi trả gạch dư & bảo hành lô màu', 'path' => '/chinh-sach-doi-tra'],
            ['label' => 'Thanh toán VietQR & xuất VAT', 'path' => '/chinh-sach-thanh-toan'],
            ['label' => 'Chính sách bảo mật thông tin', 'path' => '/chinh-sach-bao-mat'],
        ]
    );

    FooterMenuRenderer::render_column(
        'footer_col_4',
        'Đối tác & Doanh nghiệp',
        'ph-handshake',
        [
            ['label' => 'Giới thiệu thương hiệu DRAVIT', 'path' => '/gioi-thieu'],
            ['label' => 'Hệ thống Showroom trưng bày', 'path' => '/lien-he'],
            ['label' => 'Hợp tác Kiến trúc sư & Nhà thầu', 'path' => '/lien-he'],
            ['label' => 'Kho 28 danh mục gạch chính hãng', 'path' => '/gach'],
        ]
    );
    ?>
</div>

