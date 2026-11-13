<?php
declare(strict_types=1);

use Dravit\Home\HomeData;

defined('ABSPATH') || exit;

/**
 * Section: Showroom Showcase (`01-home.html`, `S-3`, `docs/07 §3.7`, `15 §5.5`).
 * - Đọc `drv_settings.showrooms` & `drv_settings.contact`.
 * - Tính "Đang mở cửa" / "Đã đóng cửa" bằng PHP theo múi giờ `Asia/Ho_Chi_Minh`.
 * - Thanh kéo đối chiếu ảnh trước / sau khi lát gạch + 3 khu vực trưng bày + 3 trải nghiệm tương tác, 0 hotlink Unsplash/Pexels.
 *
 * @var array<string, mixed> $args
 */

$eyebrow          = trim((string) ($args['eyebrow'] ?? 'Trải nghiệm thực tế tại TP.HCM'));
$title            = trim((string) ($args['title'] ?? 'Xem Mẫu Gạch Thật Tại Showroom DRAVIT'));
$desc             = trim((string) ($args['description'] ?? ''));
$show_booking_cta = (bool) ($args['show_booking_cta'] ?? true);
$surface          = (string) ($args['surface'] ?? 'ivory');

$sec_cls = 'l-section';
if ($surface === 'white') {
    $sec_cls .= ' l-section--white';
} elseif ($surface === 'champagne') {
    $sec_cls .= ' l-section--champagne';
}

$vm     = HomeData::getShowroomViewModel($args);
$status = is_array($vm['status'] ?? null) ? $vm['status'] : ['is_open' => true, 'badge_label' => 'Đang mở cửa', 'detail_label' => ''];
$zones  = is_array($vm['zones'] ?? null) ? $vm['zones'] : [];

$first_zone  = $zones['slabs'] ?? reset($zones);
$first_slide = (is_array($first_zone) && isset($first_zone['slides'][0]) && is_array($first_zone['slides'][0]))
    ? $first_zone['slides'][0]
    : [
        'after'      => HomeData::roomUrl('room-living-calacatta.jpg'),
        'before'      => HomeData::roomUrl('room-living-calacatta.jpg'),
        'real_before' => false,
        'alt'       => 'Không gian trưng bày gạch khổ lớn tại Showroom DRAVIT',
        'caption'   => 'Kệ cánh lật 120 tấm khổ 80×80, 60×120 & đá nung kết — kéo thanh trượt để so sánh trước và sau khi lát gạch',
    ];
?>
<section class="<?php echo esc_attr($sec_cls); ?>" id="home-showroom">
    <div class="l-container">
        <?php
        drv_ui('section-head', [
            'eyebrow'     => $eyebrow,
            'title'       => $title,
            'desc'        => $desc,
            'heading_tag' => 'h2',
            'reveal'      => 'fade-up',
        ]);
        ?>

        <drv-showroom class="drv-sr">
            <div class="l-split l-split--7-5">
                <div class="drv-sr__main" data-drv-reveal="fade-up">
                    <div class="drv-pill-row drv-sr__tabs" role="group" aria-label="Chọn khu vực trưng bày tại Showroom">
                        <?php foreach ($zones as $z_key => $z_item) : ?>
                            <?php $z_active = !empty($z_item['active']); ?>
                            <button
                                type="button"
                                class="drv-chip<?php echo $z_active ? ' is-active' : ''; ?>"
                                data-sr-zone="<?php echo esc_attr((string) $z_key); ?>"
                                aria-pressed="<?php echo $z_active ? 'true' : 'false'; ?>"
                            >
                                <?php drv_ui('icon', ['name' => (string) ($z_item['icon'] ?? 'storefront'), 'size' => 'sm']); ?>
                                <span><?php echo esc_html((string) ($z_item['label'] ?? '')); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="drv-sr__stage" data-sr-stage>
                        <img
                            class="drv-sr__img drv-sr__img--after"
                            data-sr-img-after
                            src="<?php echo esc_url((string) $first_slide['after']); ?>"
                            alt="<?php echo esc_attr((string) $first_slide['alt']); ?>"
                            width="960"
                            height="600"
                            loading="lazy"
                            decoding="async"
                        >
                        <div class="drv-sr__before" data-sr-before style="clip-path: inset(0 50% 0 0);">
                            <img
                                class="drv-sr__img drv-sr__img--before<?php echo !empty($first_slide['real_before']) ? ' is-real-before' : ''; ?>"
                                data-sr-img-before
                                src="<?php echo esc_url((string) ($first_slide['before'] ?: $first_slide['after'])); ?>"
                                alt=""
                                aria-hidden="true"
                                width="960"
                                height="600"
                                loading="lazy"
                                decoding="async"
                            >
                        </div>

                        <span class="drv-sr__tag drv-sr__tag--before">Trước khi lát</span>
                        <span class="drv-sr__tag drv-sr__tag--after">Sau khi lát</span>

                        <div
                            class="drv-sr__divider"
                            data-sr-divider
                            style="left: 50%;"
                            role="slider"
                            tabindex="0"
                            aria-label="Kéo ngang để so sánh không gian trước và sau khi lát gạch"
                            aria-valuemin="5"
                            aria-valuemax="95"
                            aria-valuenow="50"
                        >
                            <span class="drv-sr__handle" aria-hidden="true">
                                <i class="ph-bold ph-arrows-left-right drv-sr__icon"></i>
                            </span>
                        </div>

                        <p class="drv-sr__caption" data-sr-caption><?php echo esc_html((string) $first_slide['caption']); ?></p>
                    </div>

                    <?php foreach ($zones as $z_key => $z_item) : ?>
                        <?php
                        $z_active = !empty($z_item['active']);
                        $slides   = isset($z_item['slides']) && is_array($z_item['slides']) ? $z_item['slides'] : [];
                        ?>
                        <div class="drv-sr__thumbs" data-sr-group="<?php echo esc_attr((string) $z_key); ?>"<?php echo $z_active ? '' : ' hidden'; ?>>
                            <?php foreach ($slides as $s_idx => $slide) : ?>
                                <?php $thumb_active = ($z_active && $s_idx === 0); ?>
                                <button
                                    type="button"
                                    class="drv-sr__thumb<?php echo $thumb_active ? ' is-active' : ''; ?>"
                                    data-sr-after="<?php echo esc_url((string) ($slide['after'] ?? '')); ?>"
                                    data-sr-before="<?php echo esc_url((string) ($slide['before'] ?: ($slide['after'] ?? ''))); ?>"
                                    data-sr-real-before="<?php echo !empty($slide['real_before']) ? '1' : '0'; ?>"
                                    data-sr-alt="<?php echo esc_attr((string) ($slide['alt'] ?? '')); ?>"
                                    data-sr-caption="<?php echo esc_attr((string) ($slide['caption'] ?? '')); ?>"
                                >
                                    <img
                                        src="<?php echo esc_url((string) ($slide['thumb'] ?? $slide['after'] ?? '')); ?>"
                                        alt="<?php echo esc_attr((string) ($slide['alt'] ?? '')); ?>"
                                        width="320"
                                        height="180"
                                        loading="lazy"
                                        decoding="async"
                                    >
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <aside class="drv-sr__info" data-drv-reveal="fade-up">
                    <div class="drv-sr__cover">
                        <img
                            src="<?php echo esc_url((string) $vm['cover_img']); ?>"
                            alt="<?php echo esc_attr((string) $vm['name']); ?>"
                            width="600"
                            height="180"
                            loading="lazy"
                            decoding="async"
                        >
                        <div class="drv-sr__cover-shade" aria-hidden="true"></div>
                        <div class="drv-sr__cover-body">
                            <div>
                                <span class="drv-sr__cover-eyebrow">Trung tâm trưng bày &amp; dự toán</span>
                                <h3><?php echo esc_html((string) $vm['name']); ?></h3>
                            </div>
                            <span class="drv-badge <?php echo !empty($status['is_open']) ? 'drv-badge--success drv-badge--pulse' : 'drv-badge--champagne'; ?>">
                                <?php echo esc_html((string) ($status['badge_label'] ?? 'Đang mở cửa')); ?>
                            </span>
                        </div>
                    </div>

                    <div class="drv-sr__info-body">
                        <dl class="drv-sr__dl">
                            <div class="drv-sr__row">
                                <dt><i class="ph-fill ph-map-pin" aria-hidden="true"></i> Địa chỉ</dt>
                                <dd>
                                    <span><?php echo esc_html((string) $vm['address']); ?></span>
                                    <button
                                        type="button"
                                        class="drv-sr__copy"
                                        data-sr-copy="<?php echo esc_attr((string) $vm['address']); ?>"
                                        aria-label="Sao chép địa chỉ Showroom"
                                    >
                                        <i class="ph ph-copy" aria-hidden="true"></i>
                                    </button>
                                </dd>
                            </div>
                            <div class="drv-sr__row">
                                <dt><i class="ph-fill ph-clock" aria-hidden="true"></i> Giờ mở cửa</dt>
                                <dd>
                                    <span>
                                        <?php echo esc_html((string) $vm['hours']); ?> · T2 – CN<br>
                                        <small><?php echo esc_html((string) ($status['detail_label'] ?: 'Mở cả Chủ nhật & ngày lễ')); ?></small>
                                    </span>
                                </dd>
                            </div>
                            <div class="drv-sr__row">
                                <dt><i class="ph-fill ph-phone" aria-hidden="true"></i> Tư vấn nhanh</dt>
                                <dd>
                                    <a href="tel:<?php echo esc_attr((string) $vm['phone_clean']); ?>"><?php echo esc_html((string) $vm['phone']); ?></a>
                                    <button
                                        type="button"
                                        class="drv-sr__copy"
                                        data-sr-copy="<?php echo esc_attr((string) $vm['phone_clean']); ?>"
                                        aria-label="Sao chép số điện thoại"
                                    >
                                        <i class="ph ph-copy" aria-hidden="true"></i>
                                    </button>
                                </dd>
                            </div>
                            <div class="drv-sr__row">
                                <dt><i class="ph-fill ph-car" aria-hidden="true"></i> Đậu xe &amp; mẫu</dt>
                                <dd><span>Bãi ô tô miễn phí trước cửa · Cho mượn 2–3 viên mẫu mang về công trình</span></dd>
                            </div>
                        </dl>

                        <div class="drv-sr__exp">
                            <span class="drv-sr__exp-title">3 trải nghiệm nên thử khi ghé</span>
                            <button type="button" class="drv-sr__exp-item" data-sr-exp="lighting">
                                <span class="drv-sr__exp-icon"><i class="ph-bold ph-lightbulb" aria-hidden="true"></i></span>
                                <span><strong>So màu dưới 3 nhiệt độ đèn</strong> — 3000K vàng ấm, 4000K trung tính &amp; 6500K trắng ngày</span>
                            </button>
                            <button type="button" class="drv-sr__exp-item" data-sr-exp="bath">
                                <span class="drv-sr__exp-icon"><i class="ph-bold ph-bathtub" aria-hidden="true"></i></span>
                                <span><strong>Cabin bếp &amp; nhà tắm mẫu</strong> — xem mạch ron 1,5 mm thực tế trên mảng tường lớn</span>
                            </button>
                            <button type="button" class="drv-sr__exp-item" data-sr-exp="lab">
                                <span class="drv-sr__exp-icon"><i class="ph-bold ph-drop-half-bottom" aria-hidden="true"></i></span>
                                <span><strong>Bàn thử nước độ nhám R9–R12</strong> — cảm nhận độ bám chân thật trước khi chọn lát sàn ướt</span>
                            </button>
                        </div>

                        <div class="drv-sr__cta">
                            <a
                                href="<?php echo esc_url((string) $vm['maps_url']); ?>"
                                class="drv-btn drv-btn--outline"
                                <?php echo !empty($vm['is_ext_map']) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
                            >
                                <i class="ph-bold ph-navigation-arrow drv-icon--sm" aria-hidden="true"></i>
                                <span>Chỉ đường Google Maps</span>
                            </a>
                            <?php if ($show_booking_cta) : ?>
                                <button
                                    type="button"
                                    class="drv-btn drv-btn--primary"
                                    data-drv-action="dialog-open"
                                    data-drv-target="#drv-quote-modal"
                                >
                                    <i class="ph-bold ph-calendar-check drv-icon--sm" aria-hidden="true"></i>
                                    <span>Đặt lịch tư vấn</span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </aside>
            </div>
        </drv-showroom>
    </div>
</section>
