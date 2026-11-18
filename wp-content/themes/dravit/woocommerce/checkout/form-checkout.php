<?php
/**
 * Checkout 2 cột VN (docs/layout/05-checkout.html, docs/04 §5, D-15/D-16/D-30).
 *
 * Trái: 4 khối (Người nhận · Địa chỉ 2 cấp + Ghi chú + VAT · Vận chuyển báo sau · Thanh toán).
 * Phải (sticky): `#order_review` (fragment AJAX `review-order.php`) + cam kết + điều khoản + nút đặt hàng.
 * Không coupon, không ship-to-different-address, terms không tick sẵn.
 *
 * @package Dravit
 * @version 9.4.0
 *
 * @var \WC_Checkout $checkout
 */
declare(strict_types=1);

use Dravit\Woo\CartPresenter;
use Dravit\Woo\CheckoutVN;
use Dravit\Woo\Wards;

defined('ABSPATH') || exit;

do_action('woocommerce_before_checkout_form', $checkout);

if (!$checkout->is_registration_enabled() && $checkout->is_registration_required() && !is_user_logged_in()) {
    echo esc_html((string) apply_filters('woocommerce_checkout_must_be_logged_in_message', 'Vui lòng đăng nhập để đặt hàng.'));
    return;
}

$fields      = $checkout->get_checkout_fields();
$billing     = $fields['billing'] ?? [];
$province    = (string) ($checkout->get_value('billing_state') ?: CheckoutVN::current_province());
$province    = Wards::isProvince($province) ? $province : '';
$ward_name   = (string) $checkout->get_value('billing_city');
$wards       = $province !== '' ? Wards::forProvince($province) : [];
$ward        = $province !== '' ? Wards::findByName($province, $ward_name) : null;
$hints       = CheckoutVN::zone_hints();
$zone_text   = $province !== '' ? ($hints['map'][$province] ?? $hints['default']) : 'Chọn Tỉnh / Thành phố để xem tuyến giao dự kiến.';
$chips       = array_values(array_filter(array_map('strval', (array) drv_setting('order.note_chips', []))));
$lines       = CartPresenter::fromCart();
$summary     = CartPresenter::summarize(array_values($lines), CartPresenter::vehicleThresholds());
$cart_url    = untrailingslashit((string) wc_get_cart_url());
$vat_on      = in_array((string) $checkout->get_value('require_vat_invoice'), ['1', 'yes'], true);
$extra_keys  = array_diff(array_keys($billing), CheckoutVN::OWN_BILLING_FIELDS);

/**
 * Giá trị đã nhập (giữ lại khi validate lỗi không JS).
 */
$val = static function (string $key) use ($checkout): string {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Chỉ đọc lại giá trị hiển thị, không xử lý dữ liệu.
    if (isset($_POST[$key]) && is_scalar($_POST[$key])) {
        return sanitize_text_field(wp_unslash((string) $_POST[$key])); // phpcs:ignore WordPress.Security.NonceVerification.Missing
    }
    return (string) $checkout->get_value($key);
};
?>
<section class="l-section l-section--white u-bd-b drv-checkout-hero-sec">
    <div class="l-container l-stack l-stack--md">
        <?php
        drv_ui('breadcrumb', [
            'items' => [
                ['label' => 'Trang chủ', 'url' => untrailingslashit(home_url('/'))],
                ['label' => 'Giỏ hàng', 'url' => $cart_url],
                ['label' => 'Thanh toán'],
            ],
        ]);
        ?>
        <div class="l-stack l-stack--sm">
            <span class="drv-sec-head__eyebrow">Bước 2/3 · Địa chỉ hành chính 2 cấp (34 Tỉnh/TP)</span>
            <h1 class="drv-checkout-hero__title">Thông tin giao hàng &amp; thanh toán</h1>
        </div>
        <?php
        drv_ui('steps', [
            'steps' => [
                ['label' => 'Giỏ hàng & quy đổi khối lượng', 'sub' => sprintf('%d mã · %s m² · %s kg', $summary['line_count'], drv_format_decimal($summary['total_m2'], 2), drv_format_decimal($summary['total_kg'], 0)), 'state' => 'done', 'url' => $cart_url],
                ['label' => 'Thông tin giao hàng', 'sub' => 'Địa chỉ 2 cấp · Chọn COD hoặc chuyển khoản', 'state' => 'current'],
                ['label' => 'Xác nhận đơn & báo phí vận chuyển', 'sub' => 'Nhân viên gọi xác nhận trong giờ làm việc', 'state' => 'upcoming'],
            ],
        ]);
        ?>
    </div>
</section>

<section class="l-section" aria-label="Biểu mẫu giao hàng và tóm tắt đơn hàng">
    <div class="l-container">
        <details class="drv-checkout-mobile-summary" data-checkout-mobile-summary>
            <summary class="drv-checkout-mobile-summary__toggle">
                <span class="drv-checkout-mobile-summary__left">
                    <?php drv_ui('icon', ['name' => 'shopping-cart', 'weight' => 'bold', 'size' => 'xs']); ?>
                    <span data-checkout-mini-label><?php echo esc_html(sprintf('Đơn hàng: %d mã · %s m²', $summary['line_count'], drv_format_decimal($summary['total_m2'], 2))); ?></span>
                    <i class="ph ph-caret-down drv-icon drv-icon--xs drv-checkout-mobile-summary__arrow" aria-hidden="true"></i>
                </span>
                <strong class="drv-checkout-mobile-summary__total u-tabular" data-checkout-mini-total><?php echo wp_kses_post(WC()->cart->get_total()); ?></strong>
            </summary>
            <div class="drv-checkout-mobile-summary__content" data-checkout-mini-content></div>
        </details>

        <form
            id="drv-checkout-form"
            name="checkout"
            method="post"
            class="checkout woocommerce-checkout drv-checkout-layout"
            action="<?php echo esc_url(wc_get_checkout_url()); ?>"
            enctype="multipart/form-data"
            aria-label="Thông tin thanh toán"
            novalidate
            data-drv-checkout
            data-wards-base="<?php echo esc_url(Wards::dataUrlBase()); ?>"
            data-zone-hints="<?php echo esc_attr((string) wp_json_encode($hints, JSON_UNESCAPED_UNICODE)); ?>">

            <div class="l-stack l-stack--lg drv-checkout-main">
                <?php do_action('woocommerce_checkout_before_customer_details'); ?>

                <div id="customer_details" class="l-stack l-stack--lg">
                    <?php do_action('woocommerce_checkout_billing'); ?>

                    <section class="drv-checkout-block woocommerce-billing-fields" aria-labelledby="checkout-block-recipient">
                        <div class="drv-checkout-block__head">
                            <div class="drv-checkout-block__title-wrap">
                                <span class="drv-checkout-block__num" aria-hidden="true">01</span>
                                <h2 id="checkout-block-recipient" class="drv-checkout-block__title">Người nhận hàng tại công trình</h2>
                            </div>
                            <span class="u-text-xs u-text-muted">Không cần tạo tài khoản</span>
                        </div>
                        <div class="drv-checkout-block__body">
                            <?php do_action('woocommerce_before_checkout_billing_form', $checkout); ?>
                            <div class="drv-checkout-grid-2 drv-checkout-grid-2--mobile">
                                <div class="drv-field form-row validate-required" id="billing_first_name_field">
                                    <label for="billing_first_name" class="drv-label">
                                        <span>Họ và tên <span class="drv-label__req" aria-hidden="true">*</span></span>
                                        <span class="drv-label__hint u-hide-mobile">Chủ nhà hoặc giám sát</span>
                                    </label>
                                    <input type="text" id="billing_first_name" name="billing_first_name" class="drv-input input-text" value="<?php echo esc_attr($val('billing_first_name')); ?>" placeholder="Ví dụ: Nguyễn Văn An" autocomplete="name" required aria-required="true" aria-describedby="err-billing_first_name" data-error-empty="Vui lòng nhập họ và tên người nhận hàng.">
                                    <span id="err-billing_first_name" class="drv-field__error" role="alert" hidden></span>
                                </div>
                                <div class="drv-field form-row validate-required" id="billing_phone_field">
                                    <label for="billing_phone" class="drv-label">
                                        <span>Số điện thoại <span class="drv-label__req" aria-hidden="true">*</span></span>
                                        <span id="hint-billing_phone" class="drv-label__hint u-hide-mobile">Để gọi xác nhận và báo phí xe</span>
                                    </label>
                                    <input type="tel" id="billing_phone" name="billing_phone" class="drv-input input-text u-tabular" value="<?php echo esc_attr($val('billing_phone')); ?>" placeholder="Ví dụ: 0909 385 880" inputmode="tel" autocomplete="tel" required aria-required="true" aria-describedby="hint-billing_phone err-billing_phone" data-drv-phone data-error-empty="Vui lòng nhập số điện thoại để nhân viên gọi xác nhận đơn." data-error-pattern="Số điện thoại cần đủ 10 số, bắt đầu bằng 03, 05, 07, 08 hoặc 09.">
                                    <span id="err-billing_phone" class="drv-field__error" role="alert" hidden></span>
                                </div>
                            </div>
                            <div class="drv-field form-row" id="billing_email_field">
                                <label for="billing_email" class="drv-label">
                                    <span>Email nhận xác nhận đơn</span>
                                    <span id="hint-billing_email" class="drv-label__hint">Không bắt buộc · Nên nhập để nhận thông báo đơn và mã VietQR</span>
                                </label>
                                <input type="email" id="billing_email" name="billing_email" class="drv-input input-text" value="<?php echo esc_attr($val('billing_email')); ?>" placeholder="Ví dụ: tenban@gmail.com" inputmode="email" autocomplete="email" aria-describedby="hint-billing_email err-billing_email" data-error-pattern="Email chưa đúng định dạng (ví dụ ten@congty.vn). Có thể để trống.">
                                <span id="err-billing_email" class="drv-field__error" role="alert" hidden></span>
                            </div>
                            <?php
                            foreach ($extra_keys as $extra_key) {
                                woocommerce_form_field($extra_key, $billing[$extra_key], $checkout->get_value($extra_key));
                            }
                            do_action('woocommerce_after_checkout_billing_form', $checkout);
                            ?>
                        </div>
                    </section>

                    <section class="drv-checkout-block" aria-labelledby="checkout-block-address">
                        <div class="drv-checkout-block__head">
                            <div class="drv-checkout-block__title-wrap">
                                <span class="drv-checkout-block__num" aria-hidden="true">02</span>
                                <h2 id="checkout-block-address" class="drv-checkout-block__title">Địa chỉ công trình nhận hàng</h2>
                            </div>
                            <span class="drv-badge drv-badge--outline">34 Tỉnh/TP mới</span>
                        </div>
                        <div class="drv-checkout-block__body">
                            <input type="hidden" name="billing_country" id="billing_country" value="VN">
                            <input type="hidden" name="drv_ward_code" id="drv_ward_code" value="<?php echo esc_attr($ward['code'] ?? ''); ?>" data-drv-ward-code>
                            <div class="drv-checkout-grid-2 drv-checkout-grid-2--mobile">
                                <div class="drv-field form-row validate-required address-field" id="billing_state_field">
                                    <label for="billing_state" class="drv-label">
                                        <span>Tỉnh / Thành phố <span class="drv-label__req" aria-hidden="true">*</span></span>
                                    </label>
                                    <select id="billing_state" name="billing_state" class="drv-select" autocomplete="address-level1" required aria-required="true" aria-describedby="err-billing_state" data-drv-province data-error-empty="Vui lòng chọn Tỉnh / Thành phố giao hàng.">
                                        <option value="">Chọn Tỉnh / Thành phố</option>
                                        <?php foreach (Wards::provinces() as $p_key => $p_name) : ?>
                                            <option value="<?php echo esc_attr($p_key); ?>" <?php selected($province, $p_key); ?>><?php echo esc_html($p_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span id="err-billing_state" class="drv-field__error" role="alert" hidden></span>
                                </div>
                                <div class="drv-field form-row validate-required address-field" id="billing_city_field">
                                    <label for="billing_city" class="drv-label">
                                        <span>Phường / Xã <span class="drv-label__req" aria-hidden="true">*</span></span>
                                        <span class="drv-label__hint u-hide-mobile" data-ward-status><?php echo esc_html($province === '' ? 'Chọn Tỉnh/TP trước' : count($wards) . ' phường/xã'); ?></span>
                                    </label>
                                    <select id="billing_city" name="billing_city" class="drv-select" autocomplete="address-level2" required aria-required="true" aria-describedby="err-billing_city" data-drv-ward <?php disabled($province === ''); ?> data-error-empty="Vui lòng chọn Phường / Xã nơi nhận hàng.">
                                        <option value="">Chọn Phường / Xã</option>
                                        <?php foreach ($wards as $w) : ?>
                                            <option value="<?php echo esc_attr($w['name']); ?>" data-code="<?php echo esc_attr($w['code']); ?>" <?php selected($ward_name, $w['name']); ?>><?php echo esc_html($w['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span id="err-billing_city" class="drv-field__error" role="alert" hidden></span>
                                </div>
                            </div>
                            <div class="drv-field form-row validate-required address-field" id="billing_address_1_field">
                                <label for="billing_address_1" class="drv-label">
                                    <span>Địa chỉ cụ thể (số nhà, tên đường, khu dân cư / công trình) <span class="drv-label__req" aria-hidden="true">*</span></span>
                                </label>
                                <input type="text" id="billing_address_1" name="billing_address_1" class="drv-input input-text" value="<?php echo esc_attr($val('billing_address_1')); ?>" placeholder="Ví dụ: Số 12 đường B2, KĐT Sala (hoặc điểm mốc gần công trình)" autocomplete="street-address" required aria-required="true" aria-describedby="err-billing_address_1" data-error-empty="Vui lòng nhập số nhà, tên đường hoặc vị trí công trình.">
                                <span id="err-billing_address_1" class="drv-field__error" role="alert" hidden></span>
                            </div>

                            <div class="drv-field form-row" id="order_comments_field">
                                <label for="order_comments" class="drv-label">
                                    <span>Ghi chú giao hàng &amp; đường vào công trình</span>
                                </label>
                                <?php if ($chips !== []) : ?>
                                    <div class="drv-note-chips" role="group" aria-label="Chèn nhanh ghi chú giao hàng">
                                        <?php foreach ($chips as $chip) : ?>
                                            <button type="button" class="drv-note-chip" data-drv-note-chip="<?php echo esc_attr($chip); ?>" aria-pressed="false">
                                                <span><?php echo esc_html($chip); ?></span>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <textarea id="order_comments" name="order_comments" class="drv-textarea input-text" rows="2" placeholder="Thời gian nhận hàng, độ rộng hẻm cho xe tải, dặn tài xế gọi đội thợ..."><?php echo esc_textarea($val('order_comments')); ?></textarea>
                            </div>

                            <div class="drv-vat-box" data-drv-vat>
                                <label class="drv-check" for="require_vat_invoice">
                                    <input type="checkbox" id="require_vat_invoice" name="require_vat_invoice" value="1" <?php checked($vat_on); ?> aria-controls="drv-vat-fields" aria-expanded="<?php echo $vat_on ? 'true' : 'false'; ?>">
                                    <span>Yêu cầu xuất hóa đơn GTGT (VAT) cho công ty / chủ đầu tư</span>
                                </label>
                                <div id="drv-vat-fields" class="drv-vat-box__fields" data-drv-vat-fields <?php echo $vat_on ? '' : 'hidden'; ?>>
                                    <p class="u-text-xs u-text-muted">Giá niêm yết đã gồm thuế GTGT. Hóa đơn điện tử gửi qua email khi đơn xuất kho.</p>
                                    <div class="drv-checkout-grid-2">
                                        <div class="drv-field form-row" id="vat_company_field">
                                            <label for="vat_company" class="drv-label"><span>Tên công ty / đơn vị <span class="drv-label__req" aria-hidden="true">*</span></span></label>
                                            <input type="text" id="vat_company" name="vat_company" class="drv-input input-text" maxlength="200" value="<?php echo esc_attr($val('vat_company')); ?>" placeholder="Ví dụ: Công ty TNHH Xây dựng Nhà Việt" autocomplete="organization" aria-describedby="err-vat_company" data-vat-required data-error-empty="Vui lòng nhập tên công ty / đơn vị xuất hóa đơn.">
                                            <span id="err-vat_company" class="drv-field__error" role="alert" hidden></span>
                                        </div>
                                        <div class="drv-field form-row" id="vat_tax_id_field">
                                            <label for="vat_tax_id" class="drv-label"><span>Mã số thuế <span class="drv-label__req" aria-hidden="true">*</span></span></label>
                                            <input type="text" id="vat_tax_id" name="vat_tax_id" class="drv-input input-text u-tabular" maxlength="14" value="<?php echo esc_attr($val('vat_tax_id')); ?>" placeholder="Ví dụ: 0318688999" inputmode="numeric" aria-describedby="err-vat_tax_id" data-vat-required data-drv-tax-id data-error-empty="Vui lòng nhập mã số thuế doanh nghiệp." data-error-pattern="Mã số thuế gồm 10 chữ số (hoặc 10 số + &quot;-&quot; + 3 số chi nhánh).">
                                            <span id="err-vat_tax_id" class="drv-field__error" role="alert" hidden></span>
                                        </div>
                                    </div>
                                    <div class="drv-checkout-grid-2">
                                        <div class="drv-field form-row" id="vat_address_field">
                                            <label for="vat_address" class="drv-label"><span>Địa chỉ đăng ký kinh doanh <span class="drv-label__req" aria-hidden="true">*</span></span></label>
                                            <input type="text" id="vat_address" name="vat_address" class="drv-input input-text" maxlength="250" value="<?php echo esc_attr($val('vat_address')); ?>" placeholder="Địa chỉ ghi trên giấy phép kinh doanh" aria-describedby="err-vat_address" data-vat-required data-error-empty="Vui lòng nhập địa chỉ đăng ký kinh doanh trên hóa đơn.">
                                            <span id="err-vat_address" class="drv-field__error" role="alert" hidden></span>
                                        </div>
                                        <div class="drv-field form-row" id="vat_email_field">
                                            <label for="vat_email" class="drv-label"><span>Email nhận hóa đơn điện tử</span></label>
                                            <input type="email" id="vat_email" name="vat_email" class="drv-input input-text" value="<?php echo esc_attr($val('vat_email')); ?>" placeholder="Ví dụ: ketoan@congty.vn" inputmode="email" aria-describedby="err-vat_email" data-error-pattern="Email nhận hóa đơn chưa đúng định dạng.">
                                            <span id="err-vat_email" class="drv-field__error" role="alert" hidden></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <?php do_action('woocommerce_checkout_shipping'); ?>

                    <section class="drv-checkout-block" aria-labelledby="checkout-block-shipping">
                        <div class="drv-checkout-block__head">
                            <div class="drv-checkout-block__title-wrap">
                                <span class="drv-checkout-block__num" aria-hidden="true">03</span>
                                <h2 id="checkout-block-shipping" class="drv-checkout-block__title">Vận chuyển &amp; điều phối xe</h2>
                            </div>
                            <span class="drv-badge drv-badge--showroom u-tabular"><?php echo esc_html('Tổng khối lượng: ' . drv_format_decimal($summary['total_kg'], 0) . ' kg'); ?></span>
                        </div>
                        <div class="drv-checkout-block__body">
                            <div class="drv-ship-method-card">
                                <div class="l-cluster l-cluster--xs">
                                    <?php drv_ui('icon', ['name' => 'truck', 'weight' => 'fill']); ?>
                                    <strong class="u-text-base u-text-navy">Giao gạch bằng xe tải / chành xe công trình</strong>
                                </div>
                                <p class="u-text-xs u-text-muted">
                                    Tuyến giao dự kiến: <strong class="u-text-navy" data-checkout-shipping-zone><?php echo esc_html($zone_text); ?></strong>
                                    <?php if ($summary['vehicle_hint'] !== '') : ?>
                                        <br>Xe gợi ý theo khối lượng: <strong class="u-text-navy"><?php echo esc_html($summary['vehicle_hint']); ?></strong>
                                    <?php endif; ?>
                                </p>
                                <p class="u-text-sm">
                                    Phí vận chuyển: <strong class="u-text-moss"><?php echo esc_html(CartPresenter::shippingLabel()); ?></strong>
                                </p>
                                <div class="drv-ship-steps-mini" aria-label="Quy trình báo phí vận chuyển">
                                    <div class="drv-ship-steps-mini__item">
                                        <strong class="u-text-navy">1. Giữ hàng cùng lô</strong>
                                        <span class="u-text-muted">Đơn được giữ tồn kho ngay khi bạn bấm đặt hàng.</span>
                                    </div>
                                    <div class="drv-ship-steps-mini__item">
                                        <strong class="u-text-navy">2. Nhân viên gọi xác nhận</strong>
                                        <span class="u-text-muted">Kiểm tra đường vào và loại xe phù hợp để báo cước hợp lý nhất.</span>
                                    </div>
                                    <div class="drv-ship-steps-mini__item">
                                        <strong class="u-text-navy">3. Đồng ý rồi mới thanh toán</strong>
                                        <span class="u-text-muted">Bạn chỉ thanh toán khi đã đồng ý tổng tiền gồm phí vận chuyển.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="drv-checkout-block" aria-labelledby="checkout-block-payment">
                        <div class="drv-checkout-block__head">
                            <div class="drv-checkout-block__title-wrap">
                                <span class="drv-checkout-block__num" aria-hidden="true">04</span>
                                <h2 id="checkout-block-payment" class="drv-checkout-block__title">Phương thức thanh toán</h2>
                            </div>
                            <span class="u-text-xs u-text-moss u-bold">Chưa cần thanh toán lúc này</span>
                        </div>
                        <div class="drv-checkout-block__body">
                            <?php woocommerce_checkout_payment(); ?>
                        </div>
                    </section>
                </div>

                <?php do_action('woocommerce_checkout_after_customer_details'); ?>
            </div>

            <aside class="drv-summary-box drv-summary-box--sticky" aria-labelledby="order_review_heading">
                <div class="l-cluster l-cluster--between u-bd-b drv-summary-box__head">
                    <?php do_action('woocommerce_checkout_before_order_review_heading'); ?>
                    <h2 id="order_review_heading" class="u-text-base u-bold u-text-navy u-m-0">Tóm tắt đơn hàng</h2>
                    <a href="<?php echo esc_url($cart_url); ?>" class="drv-btn drv-btn--outline drv-btn--sm">
                        <?php drv_ui('icon', ['name' => 'pencil-simple', 'size' => 'xs']); ?>
                        <span>Sửa giỏ</span>
                    </a>
                </div>

                <?php do_action('woocommerce_checkout_before_order_review'); ?>
                <div id="order_review" class="woocommerce-checkout-review-order">
                    <?php do_action('woocommerce_checkout_order_review'); ?>
                </div>
                <?php do_action('woocommerce_checkout_after_order_review'); ?>

                <div class="drv-checkout-guarantee" role="note">
                    <div class="l-cluster l-cluster--xs">
                        <?php drv_ui('icon', ['name' => 'seal-check', 'weight' => 'fill', 'size' => 'sm']); ?>
                        <strong class="u-text-navy">Quy trình xác nhận đơn</strong>
                    </div>
                    <p><?php echo esc_html((string) drv_setting('order.checkout_ship_notice', 'Nhân viên Dravit sẽ gọi xác nhận tồn kho cùng lô màu và báo phí vận chuyển tốt nhất trước khi giao.')); ?></p>
                    <ul class="drv-checkout-guarantee__list">
                        <li class="drv-checkout-guarantee__item">
                            <?php drv_ui('icon', ['name' => 'check', 'weight' => 'bold', 'size' => 'xs']); ?>
                            <span>Gọi xác nhận trong giờ làm việc <?php echo esc_html((string) drv_setting('contact.working_hours', '')); ?>.</span>
                        </li>
                        <li class="drv-checkout-guarantee__item">
                            <?php drv_ui('icon', ['name' => 'check', 'weight' => 'bold', 'size' => 'xs']); ?>
                            <span>Xuất kho cùng mã lô màu (Shade) và cỡ gạch (Caliber).</span>
                        </li>
                        <li class="drv-checkout-guarantee__item">
                            <?php drv_ui('icon', ['name' => 'check', 'weight' => 'bold', 'size' => 'xs']); ?>
                            <span>Chỉ thanh toán khi đã đồng ý phí vận chuyển.</span>
                        </li>
                    </ul>
                </div>

                <?php wc_get_template('checkout/terms.php'); ?>

                <?php do_action('woocommerce_review_order_before_submit'); ?>
                <div class="l-stack l-stack--xs">
                    <button type="submit" class="drv-btn drv-btn--lg drv-btn--full" name="woocommerce_checkout_place_order" id="place_order" value="Xác nhận đặt hàng" data-value="Xác nhận đặt hàng">
                        <?php drv_ui('icon', ['name' => 'check-circle', 'weight' => 'bold']); ?>
                        <span>Xác nhận đặt hàng</span>
                    </button>
                    <p class="u-text-xs u-text-muted u-text-center">Chưa cần chuyển khoản · Nhân viên sẽ gọi chốt đơn và báo phí vận chuyển.</p>
                </div>
                <?php do_action('woocommerce_review_order_after_submit'); ?>
                <?php wp_nonce_field('woocommerce-process_checkout', 'woocommerce-process-checkout-nonce'); ?>
            </aside>
        </form>
    </div>
</section>

<div class="drv-checkout-sticky-cta" aria-label="Đặt hàng nhanh trên di động">
    <div class="drv-checkout-sticky-cta__inner">
        <div class="drv-checkout-sticky-cta__info">
            <span class="drv-checkout-sticky-cta__label">Tổng tạm tính:</span>
            <strong class="drv-checkout-sticky-cta__price u-tabular" data-checkout-mini-total><?php echo wp_kses_post(WC()->cart->get_total()); ?></strong>
            <span class="drv-checkout-sticky-cta__sub">Đã gồm VAT · Phí ship báo sau</span>
        </div>
        <button type="submit" form="drv-checkout-form" class="drv-btn drv-btn--lg drv-checkout-sticky-cta__btn">
            <?php drv_ui('icon', ['name' => 'check-circle', 'weight' => 'bold']); ?>
            <span>Đặt hàng</span>
        </button>
    </div>
</div>

<?php
do_action('woocommerce_after_checkout_form', $checkout);
