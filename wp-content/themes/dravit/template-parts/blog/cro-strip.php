<?php
declare(strict_types=1);

use Dravit\Home\HomeData;

defined('ABSPATH') || exit;

/**
 * Dải CRO 2 ô cuối trang Cẩm nang (mockup `07-blog-archive.html` Section 8): Showroom + Báo giá công trình.
 */
$showrooms   = drv_setting('showrooms', []);
$room        = (is_array($showrooms) && isset($showrooms[0]) && is_array($showrooms[0])) ? $showrooms[0] : [];
$sr_name     = (string) ($room['name'] ?? drv_setting('company.brand_name', ''));
$sr_addr     = (string) ($room['address'] ?? drv_setting('company.address', ''));
$sla_text    = (string) drv_setting('order.sla_text', '');
$hotline     = (string) drv_setting('contact.hotline', '');
$hotline_tel = drv_clean_phone($hotline);
$contact_url = HomeData::siteUrl('/lien-he');
$boq_url     = HomeData::siteUrl('/danh-sach-bao-gia');
?>
<section class="l-section l-section--white" style="padding-block: var(--s-5)" aria-label="Xem mẫu thật và nhận báo giá công trình">
    <div class="l-container">
        <div class="l-grid l-grid--2" style="gap: var(--s-4)">
            <div class="drv-cro-card">
                <div class="drv-cro-card__head">
                    <i class="ph-bold ph-storefront u-text-moss drv-icon--sm" aria-hidden="true"></i>
                    <h2 class="drv-cro-card__title"><?php echo esc_html('Xem mẫu gạch thực tế tại ' . $sr_name); ?></h2>
                </div>
                <p class="drv-cro-card__desc">
                    <?php echo esc_html('Đối chiếu bề mặt bóng kiếng, men Matt R10–R11 và ướm màu đường ron trực tiếp dưới ánh đèn vàng 3000K và trắng 6500K tại ' . $sr_addr . '.'); ?>
                </p>
                <div class="drv-cro-card__actions">
                    <a href="<?php echo esc_url($contact_url); ?>" class="drv-btn drv-btn--outline">
                        <i class="ph ph-map-pin drv-icon--xs" aria-hidden="true"></i>
                        <span>Xem địa chỉ &amp; Chỉ đường</span>
                    </a>
                    <?php if ($hotline_tel !== '') : ?>
                        <a href="<?php echo esc_url('tel:' . $hotline_tel); ?>" class="drv-btn drv-btn--ghost u-tabular">
                            <i class="ph ph-phone-call drv-icon--xs u-text-moss" aria-hidden="true"></i>
                            <span><?php echo esc_html($hotline); ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="drv-cro-card">
                <div class="drv-cro-card__head">
                    <i class="ph-bold ph-file-text u-text-teal drv-icon--sm" aria-hidden="true"></i>
                    <h2 class="drv-cro-card__title">Bóc tách khối lượng &amp; Báo giá chiết khấu công trình</h2>
                </div>
                <p class="drv-cro-card__desc">
                    Gửi diện tích mặt bằng hoặc danh mục mã gạch cần dùng, kỹ thuật viên DRAVIT sẽ kiểm tra tồn kho cùng lô màu (Shade) và gửi bảng tính số thùng qua Zalo. <?php echo esc_html(rtrim($sla_text, '.')); ?>.
                </p>
                <div class="drv-cro-card__actions">
                    <button type="button" class="drv-btn drv-btn--moss" data-drv-action="dialog-open" data-drv-target="#drv-quote-modal">
                        <i class="ph ph-paper-plane-tilt drv-icon--xs" aria-hidden="true"></i>
                        <span>Gửi yêu cầu báo giá công trình</span>
                    </button>
                    <a href="<?php echo esc_url($boq_url); ?>" class="drv-btn drv-btn--outline">
                        <span>Tạo bảng dự toán BOQ</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
