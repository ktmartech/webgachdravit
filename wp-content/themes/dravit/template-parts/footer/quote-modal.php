<?php
declare(strict_types=1);

use Dravit\Core\Integrations\FluentFormsBridge;

defined('ABSPATH') || exit;

/*
 * Dialog "Nhận báo giá chiết khấu công trình" toàn site (`#drv-quote-modal`, slot `quote_modal`, D-31).
 * Ngữ cảnh SP / m² được JS bơm vào khi mở từ PDP (`drv:lead-context`).
 */
?>
<dialog id="drv-quote-modal" class="drv-dialog drv-quote-modal" aria-labelledby="quote-modal-title">
    <div class="drv-dialog__head">
        <div class="l-stack l-stack--xs">
            <span class="u-text-xs u-bold u-uppercase u-text-moss">Hỗ trợ chủ nhà, thợ thầu &amp; kiến trúc sư</span>
            <h2 id="quote-modal-title" class="u-text-lg">Nhận báo giá chiết khấu công trình</h2>
        </div>
        <button type="button" class="drv-btn drv-btn--sm drv-btn--ghost drv-btn--icon" data-drv-action="dialog-close" aria-label="Đóng hộp thoại">
            <i class="ph ph-x drv-icon" aria-hidden="true"></i>
        </button>
    </div>

    <div class="drv-dialog__body">
        <?php
        if (!FluentFormsBridge::render_slot('quote_modal')) {
            get_template_part('template-parts/leads/native-form', null, [
                'source'       => 'quote_modal',
                'id_prefix'    => 'modal-quote',
                'fields'       => ['province', 'area', 'interest', 'message', 'role', 'email'],
                'message_label'=> 'Ghi chú thêm (thời gian cần hàng, xuất hóa đơn VAT...)',
                'message_placeholder' => 'VD: Nhà phố 3 tầng, cần giao xe cẩu vào thứ 7...',
                'submit_label' => 'Gửi yêu cầu báo giá',
            ]);
        }
        ?>
    </div>
</dialog>
