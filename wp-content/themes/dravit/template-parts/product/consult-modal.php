<?php
/**
 * Dialog "Tư vấn kỹ thuật" trên trang Sản phẩm (`#drv-pdp-consult-modal`, slot `pdp_consult`, D-31).
 * Ngữ cảnh SP được SSR sẵn; m² vừa tính được JS bơm khi mở từ PDP (`drv:lead-context`).
 *
 * @var array<string, mixed> $args
 */
declare(strict_types=1);

use Dravit\Core\Integrations\FluentFormsBridge;

defined('ABSPATH') || exit;

$product_id = max(0, (int) ($args['product_id'] ?? 0));
$sku        = (string) ($args['sku'] ?? '');
$name       = (string) ($args['name'] ?? '');
?>
<dialog id="drv-pdp-consult-modal" class="drv-dialog drv-quote-modal" aria-labelledby="pdp-consult-title">
    <div class="drv-dialog__head">
        <div class="l-stack l-stack--xs">
            <span class="u-text-xs u-bold u-uppercase u-text-moss">Tư vấn kỹ thuật &amp; báo giá</span>
            <h2 id="pdp-consult-title" class="u-text-lg"><?php echo esc_html($name !== '' ? 'Tư vấn mẫu ' . $name : 'Tư vấn mẫu gạch này'); ?></h2>
        </div>
        <button type="button" class="drv-btn drv-btn--sm drv-btn--ghost drv-btn--icon" data-drv-action="dialog-close" aria-label="Đóng hộp thoại">
            <i class="ph ph-x drv-icon" aria-hidden="true"></i>
        </button>
    </div>
    <div class="drv-dialog__body">
        <?php
        $context = array_filter([
            'drv_product_id'   => $product_id > 0 ? (string) $product_id : '',
            'drv_sku'          => $sku,
            'drv_product_name' => $name,
        ]);
        if (!FluentFormsBridge::render_slot('pdp_consult', $context)) {
            get_template_part('template-parts/leads/native-form', null, [
                'source'              => 'pdp',
                'id_prefix'           => 'pdp-consult',
                'product_id'          => $product_id,
                'fields'              => ['province', 'area', 'interest', 'message', 'role', 'email'],
                'message_label'       => 'Câu hỏi kỹ thuật hoặc ghi chú thêm',
                'message_placeholder' => 'VD: Cần gạch chống trượt R11 cho ban công, thi công keo dán loại nào...',
                'submit_label'        => 'Gửi yêu cầu tư vấn',
            ]);
        }
        ?>
    </div>
</dialog>
