<?php
/**
 * Section: Trung tâm Liên hệ & Showroom (`09-contact.html`): thông tin pháp nhân + hệ thống showroom (cột trái)
 * và form Lead slot `contact_page` Dual-Mode Fluent Forms / Native (cột phải, D-31).
 *
 * @var array<string, mixed> $args
 */
declare(strict_types=1);

use Dravit\Core\Integrations\FluentFormsBridge;

defined('ABSPATH') || exit;

$title       = (string) ($args['title'] ?? get_the_title());
$legal       = (string) drv_setting('company.legal_name', '');
$tax_id      = (string) drv_setting('company.tax_id', '');
$hotline     = (string) drv_setting('contact.hotline', '');
$hotline_tel = drv_clean_phone($hotline);
$zalo        = drv_clean_phone((string) drv_setting('contact.zalo', $hotline_tel));
$email       = (string) drv_setting('contact.email', '');
$b2b_email   = (string) drv_setting('contact.project_email', '');
$hours       = (string) drv_setting('contact.working_hours', '');
$showrooms   = array_values(array_filter((array) drv_setting('showrooms', []), 'is_array'));
$maps_url    = (string) ($showrooms[0]['maps_url'] ?? '');
?>
<section class="l-section l-section--white u-bd-b" style="padding-block: var(--s-5)">
    <div class="l-container l-stack l-stack--xs">
        <?php drv_ui('breadcrumb'); ?>
        <h1 class="drv-page-title"><?php echo esc_html(mb_strtoupper($title)); ?></h1>
        <p class="u-text-sm u-text-muted">Trải nghiệm mẫu gạch thực tế tại Showroom hoặc gửi yêu cầu nhận mẫu &amp; báo giá công trình.</p>
    </div>
</section>

<section class="l-section" style="padding-block: var(--s-6)">
    <div class="l-container">
        <div class="drv-contact-layout">
            <div class="drv-contact-info">
                <div class="drv-contact-block">
                    <div class="drv-contact-block__head">
                        <i class="ph-fill ph-buildings drv-icon u-text-moss" aria-hidden="true"></i>
                        <h2 class="drv-contact-block__title">Thông tin công ty</h2>
                    </div>
                    <div class="drv-contact-block__body">
                        <?php if ($legal !== '') : ?>
                            <strong class="u-text-base u-text-navy"><?php echo esc_html(mb_strtoupper($legal)); ?></strong>
                        <?php endif; ?>
                        <dl class="drv-contact-list">
                            <?php if ($tax_id !== '') : ?>
                                <div class="drv-contact-list__row">
                                    <dt><i class="ph ph-identification-card" aria-hidden="true"></i> Mã số thuế:</dt>
                                    <dd class="u-tabular"><strong><?php echo esc_html($tax_id); ?></strong> (Xuất hóa đơn VAT 100%)</dd>
                                </div>
                            <?php endif; ?>
                            <?php if ($hotline !== '') : ?>
                                <div class="drv-contact-list__row">
                                    <dt><i class="ph ph-phone-call" aria-hidden="true"></i> Hotline / Zalo:</dt>
                                    <dd><a href="<?php echo esc_url('tel:' . $hotline_tel); ?>" class="u-bold u-text-moss u-tabular"><?php echo esc_html($hotline); ?></a><?php echo $hours !== '' ? ' (' . esc_html($hours) . ')' : ''; ?></dd>
                                </div>
                            <?php endif; ?>
                            <?php if ($email !== '') : ?>
                                <div class="drv-contact-list__row">
                                    <dt><i class="ph ph-envelope-simple" aria-hidden="true"></i> Email CSKH:</dt>
                                    <dd><a href="<?php echo esc_url('mailto:' . $email); ?>"><?php echo esc_html($email); ?></a></dd>
                                </div>
                            <?php endif; ?>
                            <?php if ($b2b_email !== '') : ?>
                                <div class="drv-contact-list__row">
                                    <dt><i class="ph ph-briefcase" aria-hidden="true"></i> Phòng Dự án B2B:</dt>
                                    <dd><a href="<?php echo esc_url('mailto:' . $b2b_email); ?>"><?php echo esc_html($b2b_email); ?></a></dd>
                                </div>
                            <?php endif; ?>
                        </dl>
                    </div>
                </div>

                <?php if ($showrooms !== []) : ?>
                    <div class="drv-contact-block">
                        <div class="drv-contact-block__head">
                            <i class="ph-fill ph-map-pin drv-icon u-text-moss" aria-hidden="true"></i>
                            <h2 class="drv-contact-block__title">Showroom &amp; Tổng kho</h2>
                        </div>
                        <div class="drv-contact-block__body">
                            <?php foreach ($showrooms as $room) : ?>
                                <div class="drv-contact-loc">
                                    <div class="l-cluster l-cluster--between">
                                        <strong class="u-text-sm u-text-navy"><?php echo esc_html((string) ($room['name'] ?? '')); ?></strong>
                                        <?php if (!empty($room['hours'])) : ?>
                                            <span class="drv-badge drv-badge--showroom"><?php echo esc_html((string) $room['hours']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="u-text-sm u-text-muted"><?php echo esc_html((string) ($room['address'] ?? '')); ?></p>
                                    <?php if (!empty($room['phone'])) : ?>
                                        <span class="u-text-xs u-text-muted"><i class="ph-bold ph-phone u-text-moss" aria-hidden="true"></i> <?php echo esc_html((string) $room['phone']); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>

                            <div class="l-grid l-grid--2" style="margin-top: var(--s-2)">
                                <?php if ($maps_url !== '') : ?>
                                    <a href="<?php echo esc_url($maps_url); ?>" target="_blank" rel="noopener noreferrer" class="drv-btn drv-btn--outline drv-btn--sm drv-btn--full">
                                        <i class="ph ph-navigation-arrow drv-icon--xs" aria-hidden="true"></i>
                                        <span>Chỉ đường Google Maps</span>
                                    </a>
                                <?php endif; ?>
                                <?php if ($zalo !== '') : ?>
                                    <a href="<?php echo esc_url('https://zalo.me/' . $zalo); ?>" target="_blank" rel="noopener noreferrer" class="drv-btn drv-btn--sm drv-btn--full">
                                        <i class="ph ph-chat-circle-dots drv-icon--xs" aria-hidden="true"></i>
                                        <span>Chat Zalo Kỹ thuật</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="drv-contact-form-card" id="drv-contact-form">
                <div class="drv-contact-block__head">
                    <i class="ph-fill ph-paper-plane-tilt drv-icon u-text-moss" aria-hidden="true"></i>
                    <h2 class="drv-contact-block__title">Gửi yêu cầu tư vấn &amp; báo giá</h2>
                </div>
                <div class="drv-contact-form-card__body">
                    <?php
                    if (!FluentFormsBridge::render_slot('contact_page')) {
                        get_template_part('template-parts/leads/native-form', null, [
                            'source'        => 'contact',
                            'id_prefix'     => 'contact',
                            'fields'        => ['email', 'role', 'province', 'area', 'message', 'sample_box'],
                            'message_label' => 'Nội dung chi tiết (mã gạch, diện tích m² hoặc khu vực giao hàng)',
                            'submit_label'  => 'Gửi thông tin liên hệ',
                            'show_feedback' => true,
                            'full'          => true,
                        ]);
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</section>
