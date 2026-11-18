<?php
/**
 * Form Lead native siêu nhẹ dùng chung cho 5 slot (D-31 khi chưa gắn Fluent Form ID) – docs/17 §4.1, §4.4.
 * - Có JS: `chunks/lead-form.js` gửi AJAX `POST /dravit/v1/lead` (nonce tươi + time-trap).
 * - Tắt JS: POST thẳng `admin-post.php?action=drv_submit_lead` rồi redirect 303 kèm mã xác nhận.
 * - Không in nonce vào HTML (trang được LiteSpeed cache dài hạn).
 *
 * @var array<string, mixed> $args
 */
declare(strict_types=1);

use Dravit\Leads\LeadValidator;

defined('ABSPATH') || exit;

$source     = LeadValidator::normalizeSource((string) ($args['source'] ?? 'contact')) ?: 'contact';
$prefix     = sanitize_html_class((string) ($args['id_prefix'] ?? 'drv-lead-' . $source));
$fields     = isset($args['fields']) && is_array($args['fields']) ? $args['fields'] : ['email', 'province', 'role', 'message'];
$submit     = (string) ($args['submit_label'] ?? 'Gửi yêu cầu tư vấn');
$msg_label  = (string) ($args['message_label'] ?? 'Nội dung cần hỗ trợ');
$msg_ph     = (string) ($args['message_placeholder'] ?? 'Ví dụ: Cần báo giá mẫu 80x80 cho 85 m² giao tận công trình...');
$msg_req    = in_array($source, ['contact', 'sample'], true);
$sla        = (string) ($args['sla'] ?? drv_setting('order.sla_text', ''));
$product_id = max(0, (int) ($args['product_id'] ?? 0));
$post_id    = max(0, (int) ($args['post_id'] ?? 0));
$area       = (float) ($args['area_m2'] ?? 0);
$provinces  = (array) require DRV_DIR . '/config/provinces-vn.php';
$privacy    = untrailingslashit(home_url('/chinh-sach-bao-mat'));
$has        = static fn (string $f): bool => in_array($f, $fields, true);

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Chỉ đọc trạng thái hiển thị sau redirect 303 (không ghi dữ liệu).
$sent_code = isset($_GET['lead_sent'], $_GET['code']) ? sanitize_text_field(wp_unslash((string) $_GET['code'])) : '';
$err_code  = isset($_GET['lead_error']) ? sanitize_key(wp_unslash((string) $_GET['lead_error'])) : '';
// phpcs:enable
$show_feedback = !empty($args['show_feedback']) && ($sent_code !== '' || $err_code !== '');
?>
<?php if ($show_feedback) : ?>
    <div id="drv-lead-feedback" class="drv-lead-success<?php echo $err_code !== '' ? ' is-error' : ''; ?>" role="status" tabindex="-1">
        <i class="ph-fill <?php echo $err_code !== '' ? 'ph-warning-circle' : 'ph-seal-check'; ?> drv-icon" aria-hidden="true"></i>
        <?php if ($sent_code !== '') : ?>
            <strong class="drv-lead-success__title"><?php echo esc_html(sprintf('Đã gửi yêu cầu #%s', $sent_code)); ?></strong>
            <p class="drv-lead-success__msg"><?php echo esc_html($sla); ?></p>
        <?php else : ?>
            <strong class="drv-lead-success__title">Chưa gửi được yêu cầu</strong>
            <p class="drv-lead-success__msg"><?php echo esc_html($err_code === 'drv_rate_limited' ? 'Bạn đã gửi quá nhiều yêu cầu trong 15 phút. Vui lòng gọi hotline để được hỗ trợ ngay.' : 'Vui lòng kiểm tra lại họ tên, số điện thoại và ô đồng ý rồi gửi lại.'); ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<form class="drv-lead-form l-stack l-stack--sm"
      method="post"
      action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
      data-drv-lead-form
      data-drv-lead-type="<?php echo esc_attr($source); ?>"
      novalidate>
    <input type="hidden" name="action" value="drv_submit_lead">
    <input type="hidden" name="source" value="<?php echo esc_attr($source); ?>">
    <input type="hidden" name="product_id" value="<?php echo $product_id > 0 ? esc_attr((string) $product_id) : ''; ?>">
    <?php if ($post_id > 0) : ?>
        <input type="hidden" name="post_id" value="<?php echo esc_attr((string) $post_id); ?>">
    <?php endif; ?>

    <div class="l-grid l-grid--2">
        <div class="drv-field">
            <label for="<?php echo esc_attr($prefix . '-name'); ?>" class="drv-label"><span>Họ và tên <span class="drv-label__req" aria-hidden="true">*</span></span></label>
            <input id="<?php echo esc_attr($prefix . '-name'); ?>" name="name" type="text" class="drv-input" required maxlength="80" autocomplete="name" placeholder="Nguyễn Văn An">
            <span class="drv-field__error" role="alert" hidden></span>
        </div>
        <div class="drv-field">
            <label for="<?php echo esc_attr($prefix . '-phone'); ?>" class="drv-label"><span>Số điện thoại Zalo <span class="drv-label__req" aria-hidden="true">*</span></span></label>
            <input id="<?php echo esc_attr($prefix . '-phone'); ?>" name="phone" type="tel" inputmode="tel" class="drv-input u-tabular" required maxlength="16" autocomplete="tel" placeholder="0909 123 456">
            <span class="drv-field__error" role="alert" hidden></span>
        </div>
    </div>

    <?php if ($has('email') || $has('role')) : ?>
        <div class="l-grid l-grid--2">
            <?php if ($has('email')) : ?>
                <div class="drv-field">
                    <label for="<?php echo esc_attr($prefix . '-email'); ?>" class="drv-label">Email (nhận báo giá PDF)</label>
                    <input id="<?php echo esc_attr($prefix . '-email'); ?>" name="email" type="email" inputmode="email" class="drv-input" autocomplete="email" placeholder="ten@congty.vn">
                    <span class="drv-field__error" role="alert" hidden></span>
                </div>
            <?php endif; ?>
            <?php if ($has('role')) : ?>
                <div class="drv-field">
                    <label for="<?php echo esc_attr($prefix . '-role'); ?>" class="drv-label">Bạn là</label>
                    <select id="<?php echo esc_attr($prefix . '-role'); ?>" name="role" class="drv-select">
                        <?php foreach (LeadValidator::ROLES as $val => $label) : ?>
                            <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($has('province') || $has('area')) : ?>
        <div class="l-grid l-grid--2">
            <?php if ($has('province')) : ?>
                <div class="drv-field">
                    <label for="<?php echo esc_attr($prefix . '-province'); ?>" class="drv-label">Tỉnh / Thành phố giao hàng</label>
                    <select id="<?php echo esc_attr($prefix . '-province'); ?>" name="province" class="drv-select" autocomplete="address-level1">
                        <option value="">Chọn Tỉnh / Thành phố</option>
                        <?php foreach ($provinces as $code => $label) : ?>
                            <option value="<?php echo esc_attr((string) $code); ?>"><?php echo esc_html((string) $label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <?php if ($has('area')) : ?>
                <div class="drv-field">
                    <label for="<?php echo esc_attr($prefix . '-area'); ?>" class="drv-label">Tổng diện tích dự kiến (m²)</label>
                    <input id="<?php echo esc_attr($prefix . '-area'); ?>" name="area_m2" type="number" inputmode="decimal" min="0" max="50000" step="0.1" class="drv-input u-tabular" value="<?php echo $area > 0 ? esc_attr((string) $area) : ''; ?>" placeholder="VD: 60">
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($has('interest')) : ?>
        <div class="drv-field">
            <label for="<?php echo esc_attr($prefix . '-interest'); ?>" class="drv-label">Mã gạch hoặc hạng mục quan tâm</label>
            <input id="<?php echo esc_attr($prefix . '-interest'); ?>" name="interest" type="text" class="drv-input" maxlength="200" placeholder="VD: Gạch lát phòng khách 80x80 &amp; gạch nhà tắm R10" data-quote-modal-sku>
        </div>
    <?php endif; ?>

    <?php if ($has('preferred_time')) : ?>
        <div class="drv-field">
            <label for="<?php echo esc_attr($prefix . '-time'); ?>" class="drv-label"><span>Ngày / khung giờ muốn đến xem mẫu <span class="drv-label__req" aria-hidden="true">*</span></span></label>
            <input id="<?php echo esc_attr($prefix . '-time'); ?>" name="preferred_time" type="text" class="drv-input" maxlength="60" required placeholder="VD: Sáng thứ 7, 9:00 - 10:00">
            <span class="drv-field__error" role="alert" hidden></span>
        </div>
    <?php endif; ?>

    <?php if ($has('message')) : ?>
        <div class="drv-field">
            <label for="<?php echo esc_attr($prefix . '-message'); ?>" class="drv-label"><span><?php echo esc_html($msg_label); ?><?php if ($msg_req) : ?> <span class="drv-label__req" aria-hidden="true">*</span><?php endif; ?></span></label>
            <textarea id="<?php echo esc_attr($prefix . '-message'); ?>" name="message" class="drv-textarea" rows="3" maxlength="1000" <?php echo $msg_req ? 'required' : ''; ?> placeholder="<?php echo esc_attr($msg_ph); ?>"></textarea>
            <span class="drv-field__error" role="alert" hidden></span>
        </div>
    <?php endif; ?>

    <?php if ($has('sample_box')) : ?>
        <label class="drv-check" for="<?php echo esc_attr($prefix . '-sample'); ?>">
            <input id="<?php echo esc_attr($prefix . '-sample'); ?>" type="checkbox" name="sample_box" value="1">
            <span class="u-text-xs">Gửi kèm hộp mẫu thật để đối chiếu màu tại công trình</span>
        </label>
    <?php endif; ?>

    <div class="drv-field">
        <label class="drv-check" for="<?php echo esc_attr($prefix . '-consent'); ?>">
            <input id="<?php echo esc_attr($prefix . '-consent'); ?>" type="checkbox" name="consent" value="1" required>
            <span class="u-text-xs">Tôi đồng ý để DRAVIT liên hệ tư vấn và xử lý thông tin theo <a href="<?php echo esc_url($privacy); ?>" target="_blank" rel="noopener">Chính sách bảo mật dữ liệu cá nhân</a>.</span>
        </label>
        <span class="drv-field__error" role="alert" hidden></span>
    </div>

    <div class="drv-hp" aria-hidden="true">
        <label for="<?php echo esc_attr($prefix . '-website'); ?>">Không điền trường này</label>
        <input id="<?php echo esc_attr($prefix . '-website'); ?>" name="drv_website" type="text" tabindex="-1" autocomplete="off">
    </div>

    <div class="drv-lead-form__foot">
        <button type="submit" class="drv-btn drv-btn--moss<?php echo !empty($args['full']) ? ' drv-btn--full' : ''; ?>">
            <i class="ph-bold ph-paper-plane-tilt drv-icon--xs" aria-hidden="true"></i>
            <span><?php echo esc_html($submit); ?></span>
        </button>
        <span class="u-text-xs u-text-muted">
            <i class="ph-fill ph-shield-check u-text-moss" aria-hidden="true"></i>
            <?php echo esc_html($sla); ?>
        </span>
    </div>
</form>
