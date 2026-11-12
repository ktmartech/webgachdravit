<?php
declare(strict_types=1);

use Dravit\Core\Integrations\FluentFormsBridge;

defined('ABSPATH') || exit;

$provinces_file = DRV_DIR . '/config/provinces-vn.php';
$provinces      = is_readable($provinces_file) ? require $provinces_file : [];
$sla_text       = (string) drv_setting('order.sla_text', 'Kiểm tra tồn kho cùng lô màu (Shade/Caliber) và phản hồi trong 30 phút.');
?>
<dialog id="drv-quote-modal" class="drv-dialog" aria-labelledby="quote-modal-title">
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
        <?php if (!FluentFormsBridge::render_slot('quote_modal')) : ?>
            <form class="l-stack l-stack--md" data-drv-lead-form data-drv-lead-type="quote_modal" novalidate>
                <div class="l-grid l-grid--2">
                    <div class="drv-field">
                        <label for="modal-quote-name" class="drv-label">
                            <span>Họ và tên <span class="drv-label__req">*</span></span>
                        </label>
                        <input id="modal-quote-name" name="customer_name" type="text" class="drv-input" required autocomplete="name" placeholder="Nhập họ và tên">
                        <span class="drv-field__error" role="alert" hidden></span>
                    </div>
                    <div class="drv-field">
                        <label for="modal-quote-phone" class="drv-label">
                            <span>Số điện thoại Zalo <span class="drv-label__req">*</span></span>
                        </label>
                        <input id="modal-quote-phone" name="customer_phone" type="tel" inputmode="tel" class="drv-input" required autocomplete="tel" placeholder="09xx xxx xxx">
                        <span class="drv-field__error" role="alert" hidden></span>
                    </div>
                </div>

                <div class="l-grid l-grid--2">
                    <div class="drv-field">
                        <label for="modal-quote-prov" class="drv-label">Tỉnh / Thành phố giao hàng</label>
                        <select id="modal-quote-prov" name="province_code" class="drv-select" data-drv-province>
                            <option value="">Chọn Tỉnh / Thành phố</option>
                            <?php if (is_array($provinces)) :
                                foreach ($provinces as $code => $prov) :
                                    $p_name = is_array($prov) && isset($prov['name']) ? (string) $prov['name'] : (string) $prov;
                                    ?>
                                    <option value="<?php echo esc_attr((string) $code); ?>"><?php echo esc_html($p_name); ?></option>
                                <?php endforeach;
                            endif; ?>
                        </select>
                    </div>
                    <div class="drv-field">
                        <label for="modal-quote-area" class="drv-label">Tổng diện tích dự kiến (m²)</label>
                        <input id="modal-quote-area" name="estimated_m2" type="number" class="drv-input" value="60" min="1">
                    </div>
                </div>

                <div class="drv-field">
                    <label for="modal-quote-product" class="drv-label">Mã gạch hoặc hạng mục quan tâm</label>
                    <input id="modal-quote-product" name="interested_items" type="text" class="drv-input" placeholder="VD: Gạch lát phòng khách 80x80 &amp; gạch nhà tắm R10" data-quote-modal-sku>
                </div>

                <div class="drv-field">
                    <label for="modal-quote-note" class="drv-label">Ghi chú thêm (Thời gian cần hàng, xuất hóa đơn VAT...)</label>
                    <textarea id="modal-quote-note" name="customer_note" class="drv-textarea" rows="3" placeholder="VD: Nhà phố 3 tầng, cần giao xe cẩu vào thứ 7..."></textarea>
                </div>

                <div class="u-sr-only" aria-hidden="true">
                    <label for="modal-quote-hp">Không nhập trường này</label>
                    <input id="modal-quote-hp" name="drv_website_hp" type="text" tabindex="-1" autocomplete="off">
                </div>

                <div class="l-cluster l-cluster--between">
                    <span class="u-text-xs u-text-muted"><?php echo esc_html($sla_text); ?></span>
                    <button type="submit" class="drv-btn drv-btn--moss">Gửi yêu cầu báo giá</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</dialog>

