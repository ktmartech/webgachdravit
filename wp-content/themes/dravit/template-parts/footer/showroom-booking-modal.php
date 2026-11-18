<?php
declare(strict_types=1);

use Dravit\Core\Integrations\FluentFormsBridge;

defined('ABSPATH') || exit;

/*
 * Dialog "Đặt lịch hẹn xem mẫu" (`#drv-showroom-booking-modal`, slot `showroom_booking`, D-31).
 * Chỉ dựng khi trang có section `showroom-showcase` (cờ `$GLOBALS['drv_needs_showroom_booking']`).
 */
?>
<dialog id="drv-showroom-booking-modal" class="drv-dialog drv-quote-modal" aria-labelledby="showroom-booking-title">
    <div class="drv-dialog__head">
        <div class="l-stack l-stack--xs">
            <span class="u-text-xs u-bold u-uppercase u-text-moss">Trải nghiệm mẫu thật</span>
            <h2 id="showroom-booking-title" class="u-text-lg">Đặt lịch hẹn xem mẫu tại Showroom</h2>
        </div>
        <button type="button" class="drv-btn drv-btn--sm drv-btn--ghost drv-btn--icon" data-drv-action="dialog-close" aria-label="Đóng hộp thoại">
            <i class="ph ph-x drv-icon" aria-hidden="true"></i>
        </button>
    </div>
    <div class="drv-dialog__body">
        <?php
        if (!FluentFormsBridge::render_slot('showroom_booking')) {
            get_template_part('template-parts/leads/native-form', null, [
                'source'              => 'showroom_visit',
                'id_prefix'           => 'showroom-booking',
                'fields'              => ['email', 'preferred_time', 'interest', 'message'],
                'message_label'       => 'Ghi chú thêm (số người đi, mẫu muốn xem...)',
                'message_placeholder' => 'VD: Muốn xem mẫu 80x80 vân đá cho phòng khách...',
                'submit_label'        => 'Đặt lịch hẹn',
            ]);
        }
        ?>
    </div>
</dialog>
