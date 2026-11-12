<?php
declare(strict_types=1);

use Dravit\Core\Menu\MegaMenuRenderer;

defined('ABSPATH') || exit;

$hotline_raw   = (string) drv_setting('contact.hotline', '0909 888 777');
$hotline_tel   = drv_clean_phone($hotline_raw);
$zalo_clean    = drv_clean_phone((string) drv_setting('contact.zalo', $hotline_raw));
$project_email = (string) drv_setting('contact.project_email', 'duan@gachdravit.vn');
$open_hour     = (int) drv_setting('contact.open_hour', 8);
$close_hour    = (int) drv_setting('contact.close_hour', 21);
$hours_badge   = sprintf('%02d:00 - %02d:00', $open_hour, $close_hour);

$showrooms     = drv_setting('showrooms', []);
$primary_sr    = (is_array($showrooms) && isset($showrooms[0]) && is_array($showrooms[0])) ? $showrooms[0] : [];
$sr_name       = (string) ($primary_sr['name'] ?? 'Showroom Flagship Sala');
$sr_address    = (string) ($primary_sr['address'] ?? drv_setting('company.address', '68 Nguyễn Cơ Thạch, Phường An Khánh, TP. Hồ Chí Minh'));
$sr_maps_url   = (string) ($primary_sr['maps_url'] ?? MegaMenuRenderer::internal_url('/lien-he'));
$contact_url   = MegaMenuRenderer::internal_url('/lien-he');
?>
<section class="drv-footer-dock" aria-label="Trung tâm tư vấn kỹ thuật, Showroom và Báo giá công trình">
    <div class="l-container">
        <div class="drv-footer-dock__grid">
            <div class="drv-footer-dock__item">
                <div class="l-stack l-stack--xs">
                    <div class="drv-footer-dock__head">
                        <span class="drv-footer-dock__kicker">
                            <i class="ph ph-headset drv-icon--sm" aria-hidden="true"></i>
                            <span>Hotline &amp; Tư vấn Kỹ thuật</span>
                        </span>
                        <span class="drv-footer-dock__status drv-footer-dock__status--online">
                            <span class="drv-footer-dock__status-dot" aria-hidden="true"></span>
                            <span><?php echo esc_html($hours_badge); ?></span>
                        </span>
                    </div>
                    <a href="<?php echo esc_url('tel:' . $hotline_tel); ?>" class="drv-footer-dock__hotline u-tabular"><?php echo esc_html($hotline_raw); ?></a>
                    <p class="drv-footer-dock__desc">Tư vấn độ chống trượt R9 - R13, chọn khổ gạch theo diện tích phòng và kiểm tra tồn kho cùng lô màu (Shade/Caliber).</p>
                </div>
                <div class="drv-footer-dock__actions">
                    <a href="<?php echo esc_url('tel:' . $hotline_tel); ?>" class="drv-footer-dock__action drv-footer-dock__action--outline">
                        <i class="ph ph-phone-call drv-icon--xs" aria-hidden="true"></i>
                        <span>Gọi ngay</span>
                    </a>
                    <a href="<?php echo esc_url('https://zalo.me/' . $zalo_clean); ?>" class="drv-footer-dock__action drv-footer-dock__action--teal" target="_blank" rel="noopener noreferrer">
                        <i class="ph ph-chat-circle-dots drv-icon--xs" aria-hidden="true"></i>
                        <span>Chat Zalo</span>
                    </a>
                </div>
            </div>

            <div class="drv-footer-dock__item">
                <div class="l-stack l-stack--xs">
                    <div class="drv-footer-dock__head">
                        <span class="drv-footer-dock__kicker">
                            <i class="ph ph-map-pin drv-icon--sm" aria-hidden="true"></i>
                            <span><?php echo esc_html($sr_name); ?></span>
                        </span>
                        <span class="drv-footer-dock__status drv-footer-dock__status--neutral">Mở cửa T2 - CN</span>
                    </div>
                    <span class="drv-footer-dock__title"><?php echo esc_html($sr_address); ?></span>
                    <p class="drv-footer-dock__desc">Trải nghiệm thực tế mẫu gạch khổ lớn Big Slab và kiểm tra chất men dưới hệ đèn ánh sáng 3000K - 6500K.</p>
                </div>
                <div class="drv-footer-dock__actions">
                    <a href="<?php echo esc_url($sr_maps_url); ?>" class="drv-footer-dock__action drv-footer-dock__action--outline" target="_blank" rel="noopener noreferrer">
                        <i class="ph ph-navigation-arrow drv-icon--xs" aria-hidden="true"></i>
                        <span>Chỉ đường Maps</span>
                    </a>
                    <a href="<?php echo esc_url($contact_url); ?>" class="drv-footer-dock__action drv-footer-dock__action--navy">
                        <i class="ph ph-calendar-check drv-icon--xs" aria-hidden="true"></i>
                        <span>Đặt lịch xem mẫu</span>
                    </a>
                </div>
            </div>

            <div class="drv-footer-dock__item">
                <div class="l-stack l-stack--xs">
                    <div class="drv-footer-dock__head">
                        <span class="drv-footer-dock__kicker">
                            <i class="ph ph-file-text drv-icon--sm" aria-hidden="true"></i>
                            <span>Phòng Dự Án &amp; Nhà Thầu</span>
                        </span>
                        <span class="drv-footer-dock__status drv-footer-dock__status--amber">Chiết khấu B2B</span>
                    </div>
                    <span class="drv-footer-dock__title">Bóc tách khối lượng theo bản vẽ CAD / PDF</span>
                    <p class="drv-footer-dock__desc">Hỗ trợ tính số viên, số thùng nguyên đai, giữ kho cùng lô màu và xuất hóa đơn VAT 100% cho công trình.</p>
                </div>
                <div class="drv-footer-dock__actions">
                    <button type="button" class="drv-footer-dock__action drv-footer-dock__action--teal" data-drv-action="dialog-open" data-drv-target="#drv-quote-modal">
                        <i class="ph ph-paper-plane-tilt drv-icon--xs" aria-hidden="true"></i>
                        <span>Nhận báo giá</span>
                    </button>
                    <a href="<?php echo esc_url('mailto:' . $project_email); ?>" class="drv-footer-dock__action drv-footer-dock__action--outline">
                        <i class="ph ph-envelope-simple drv-icon--xs" aria-hidden="true"></i>
                        <span><?php echo esc_html($project_email); ?></span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

