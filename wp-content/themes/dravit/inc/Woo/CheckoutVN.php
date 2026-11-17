<?php
declare(strict_types=1);

namespace Dravit\Woo;

use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Checkout Việt Nam (docs/04 §5–§6, docs/14 §7, D-15/D-16/D-22/D-30):
 * - 34 Tỉnh/TP (`billing_state`) → Phường/Xã (`billing_city` = tên có dấu, `_drv_ward_code` = mã TCTK).
 * - Gỡ `last_name`, `company`, `address_2`, `postcode`; email tùy chọn; SĐT VN; khối VAT `_drv_vat_*`.
 * - Phương thức vận chuyển `drv_quote_later` "Báo sau khi xác nhận đơn" (không hiện `0đ`).
 * - Snapshot order item + tổng m²/thùng/kg qua WC CRUD (HPOS).
 */
final class CheckoutVN implements ServiceProviderInterface {
    /** Khóa trường tùy biến POST ngoài `woocommerce_checkout_fields`. */
    public const EXTRA_KEYS = ['require_vat_invoice', 'vat_company', 'vat_tax_id', 'vat_address', 'vat_email', 'drv_ward_code'];

    /** Trường billing do template `form-checkout.php` tự dựng (còn lại render bằng `woocommerce_form_field`). */
    public const OWN_BILLING_FIELDS = [
        'billing_first_name',
        'billing_phone',
        'billing_email',
        'billing_state',
        'billing_city',
        'billing_address_1',
        'billing_country',
    ];

    public function register(): void {
        add_filter('woocommerce_states', [$this, 'filter_states']);
        add_filter('woocommerce_get_country_locale', [$this, 'filter_country_locale']);
        add_filter('woocommerce_localisation_address_formats', [$this, 'filter_address_formats']);
        add_filter('default_checkout_billing_country', static fn (): string => 'VN');
        add_filter('woocommerce_checkout_fields', [$this, 'filter_checkout_fields'], 20);
        add_filter('woocommerce_cart_needs_shipping_address', '__return_false', 20);
        add_filter('woocommerce_ship_to_different_address_checked', '__return_false', 20);
        add_filter('woocommerce_checkout_required_field_notice', [$this, 'filter_required_notice'], 10, 3);
        add_filter('woocommerce_process_checkout_field_billing_phone', [CheckoutValidator::class, 'normalizePhone']);
        add_filter('gettext_woocommerce', [$this, 'translate_core_strings'], 20, 2);

        add_action('woocommerce_after_checkout_validation', [$this, 'validate'], 20, 2);
        add_action('woocommerce_checkout_create_order', [$this, 'save_order_meta'], 20, 2);
        add_action('woocommerce_checkout_create_order_line_item', [OrderSnapshot::class, 'onCreateLineItem'], 20, 4);
        add_action('woocommerce_checkout_order_created', [$this, 'after_order_created'], 20);
        add_filter('woocommerce_order_item_get_formatted_meta_data', [$this, 'filter_formatted_meta'], 20, 2);
        add_filter('woocommerce_hidden_order_itemmeta', [$this, 'filter_hidden_itemmeta']);
        add_action('woocommerce_after_order_itemmeta', [$this, 'render_admin_item_rows'], 10, 2);

        add_filter('woocommerce_shipping_methods', [$this, 'register_shipping_method']);
        add_filter('woocommerce_cart_shipping_method_full_label', [$this, 'filter_shipping_label'], 20, 2);
        add_filter('woocommerce_order_shipping_to_display', [$this, 'filter_order_shipping_display'], 20, 2);

        add_action('init', [$this, 'unhook_defaults'], 30);
        add_action('woocommerce_before_checkout_form', [$this, 'unhook_default_address_forms'], 1);
        add_action('wp_enqueue_scripts', [$this, 'dequeue_select2'], 100);
    }

    /**
     * @param array<string, mixed> $states
     * @return array<string, mixed>
     */
    public function filter_states(array $states): array {
        $states['VN'] = Wards::provinces();
        return $states;
    }

    /**
     * @param array<string, mixed> $locale
     * @return array<string, mixed>
     */
    public function filter_country_locale(array $locale): array {
        $locale['VN'] = [
            'first_name' => ['label' => 'Họ và tên', 'priority' => 10, 'class' => ['form-row-wide']],
            'last_name'  => ['required' => false, 'hidden' => true],
            'company'    => ['required' => false, 'hidden' => true],
            'state'      => ['label' => 'Tỉnh / Thành phố', 'required' => true, 'hidden' => false, 'priority' => 40],
            'city'       => ['label' => 'Phường / Xã', 'required' => true, 'priority' => 50],
            'address_1'  => ['label' => 'Địa chỉ cụ thể', 'required' => true, 'priority' => 60],
            'address_2'  => ['required' => false, 'hidden' => true],
            'postcode'   => ['required' => false, 'hidden' => true],
        ];
        return $locale;
    }

    /**
     * @param array<string, string> $formats
     * @return array<string, string>
     */
    public function filter_address_formats(array $formats): array {
        $formats['VN'] = "{name}\n{address_1}\n{city}, {state}";
        return $formats;
    }

    /**
     * @param array<string, array<string, array<string, mixed>>> $fields
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function filter_checkout_fields(array $fields): array {
        $billing = $fields['billing'] ?? [];
        foreach (['billing_last_name', 'billing_company', 'billing_address_2', 'billing_postcode'] as $drop) {
            unset($billing[$drop]);
        }

        $billing['billing_first_name'] = array_merge($billing['billing_first_name'] ?? [], [
            'label'        => 'Họ và tên',
            'required'     => true,
            'priority'     => 10,
            'autocomplete' => 'name',
            'placeholder'  => 'Ví dụ: Nguyễn Văn An',
        ]);
        $billing['billing_phone'] = array_merge($billing['billing_phone'] ?? [], [
            'label'        => 'Số điện thoại',
            'type'         => 'tel',
            'required'     => true,
            'priority'     => 20,
            'validate'     => [],
            'autocomplete' => 'tel',
        ]);
        $billing['billing_email'] = array_merge($billing['billing_email'] ?? [], [
            'label'    => 'Email',
            'required' => false,
            'priority' => 30,
        ]);
        $billing['billing_country'] = array_merge($billing['billing_country'] ?? [], [
            'type'     => 'country',
            'required' => true,
            'priority' => 35,
            'default'  => 'VN',
        ]);
        $billing['billing_state'] = array_merge($billing['billing_state'] ?? [], [
            'label'    => 'Tỉnh / Thành phố',
            'type'     => 'state',
            'required' => true,
            'priority' => 40,
            'validate' => ['state'],
        ]);
        $billing['billing_city'] = array_merge($billing['billing_city'] ?? [], [
            'label'    => 'Phường / Xã',
            'type'     => 'select',
            'required' => true,
            'priority' => 50,
            'options'  => ['' => 'Chọn Phường / Xã'],
        ]);
        $billing['billing_address_1'] = array_merge($billing['billing_address_1'] ?? [], [
            'label'       => 'Địa chỉ cụ thể',
            'required'    => true,
            'priority'    => 60,
            'placeholder' => 'Số nhà, tên đường, khu dân cư hoặc tên công trình',
        ]);

        $fields['billing']  = $billing;
        $fields['shipping'] = [];
        if (isset($fields['order']['order_comments'])) {
            $fields['order']['order_comments']['label']       = 'Ghi chú giao hàng';
            $fields['order']['order_comments']['placeholder'] = 'Thời gian nhận hàng, độ rộng hẻm cho xe tải, dặn tài xế gọi đội thợ...';
        }
        return $fields;
    }

    public function filter_required_notice(string $notice, string $field_label, string $key = ''): string {
        unset($notice, $key);
        $label = trim((string) preg_replace('/^(Billing|Shipping)\s+/i', '', wp_strip_all_tags($field_label)));
        return sprintf('Vui lòng nhập %s.', '<strong>' . esc_html(mb_strtolower($label)) . '</strong>');
    }

    /**
     * Một số thông báo lõi WooCommerce xuất hiện ở checkout khi site chưa có gói ngôn ngữ `vi`.
     */
    public function translate_core_strings(string $translation, string $text): string {
        static $map = [
            'Please read and accept the terms and conditions to proceed with your order.' => 'Vui lòng đồng ý Điều khoản mua hàng để tiếp tục đặt hàng.',
            'Invalid payment method.'                                                       => 'Phương thức thanh toán không hợp lệ.',
            'No shipping method has been selected. Please double check your address, or contact us if you need any help.' => 'Chưa có phương thức giao hàng. Vui lòng kiểm tra lại địa chỉ hoặc gọi hotline để được hỗ trợ.',
            'There are some issues with the items in your cart. Please go back to the cart page and resolve these issues before checking out.' => 'Giỏ hàng có sản phẩm cần điều chỉnh. Vui lòng quay lại giỏ hàng trước khi đặt hàng.',
            'Sorry, your session has expired.'                                              => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.',
            // Màn chặn xem đơn khi chưa đăng nhập (`WC_Shortcode_Checkout::order_received()`) + `global/form-login.php`.
            'Please log in to your account to view this order.'                            => 'Đơn hàng này thuộc một tài khoản. Vui lòng đăng nhập để xem chi tiết đơn.',
            'Username or email'                                                             => 'Email hoặc tên đăng nhập',
            'Password'                                                                      => 'Mật khẩu',
            'Required'                                                                      => 'Bắt buộc',
            'Remember me'                                                                   => 'Ghi nhớ đăng nhập',
            'Login'                                                                         => 'Đăng nhập',
            'Lost your password?'                                                           => 'Quên mật khẩu?',
            'Thank you. Your order has been received.'                                      => 'Cảm ơn Quý khách. Đơn hàng đã được tiếp nhận.',
        ];
        return $map[$text] ?? $translation;
    }

    /**
     * Đọc các trường POST tùy biến (WC đã xác thực nonce `woocommerce-process-checkout-nonce` trước hook này).
     *
     * @return array<string, string>
     */
    public static function posted_extras(): array {
        $out = [];
        foreach (self::EXTRA_KEYS as $key) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce đã được WC_Checkout::process_checkout() kiểm.
            $raw = isset($_POST[$key]) && is_scalar($_POST[$key]) ? wp_unslash((string) $_POST[$key]) : '';
            $out[$key] = $key === 'vat_email' ? sanitize_email($raw) : sanitize_text_field($raw);
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function validate(array $data, \WP_Error $errors): void {
        $existing = $errors->get_error_codes();
        foreach (CheckoutValidator::validate(array_merge($data, self::posted_extras())) as $field => $message) {
            if (in_array($field . '_required', $existing, true) || in_array($field . '_validation', $existing, true)) {
                continue;
            }
            $errors->add($field . '_validation', $message, ['id' => $field]);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save_order_meta(\WC_Order $order, array $data): void {
        $extras   = self::posted_extras();
        $province = (string) ($data['billing_state'] ?? '');
        $ward     = Wards::find($province, $extras['drv_ward_code'])
            ?? Wards::findByName($province, (string) ($data['billing_city'] ?? ''));

        $order->update_meta_data('_drv_ward_code', $ward['code'] ?? '');
        foreach (CheckoutValidator::vatMeta(array_merge($data, $extras)) as $key => $value) {
            $order->update_meta_data($key, $value);
        }
        OrderSnapshot::applyOrderTotals($order);
    }

    public function after_order_created(\WC_Order $order): void {
        $order->update_meta_data('_drv_transfer_ref', 'DRV' . $order->get_id());
        $order->save();
    }

    /**
     * Ẩn meta kỹ thuật `_drv_*`, thêm dòng tiếng Việt "Đơn giá / m² · Quy đổi · Mã lô" cho Email & Admin.
     *
     * @param array<int|string, object> $formatted
     * @return array<int|string, object>
     */
    public function filter_formatted_meta(array $formatted, \WC_Order_Item $item): array {
        if (!$item instanceof \WC_Order_Item_Product || $item->get_meta('_drv_model') === '') {
            return $formatted;
        }
        foreach (OrderSnapshot::displayRows($item) as $row) {
            $formatted[$row['key']] = (object) [
                'key'           => $row['key'],
                'value'         => $row['value'],
                'display_key'   => $row['label'],
                'display_value' => wpautop(esc_html($row['value'])),
            ];
        }
        return $formatted;
    }

    /**
     * Ẩn meta snapshot `_drv_*` thô khỏi màn sửa đơn Admin (đã có dòng tiếng Việt thay thế).
     *
     * @param array<int, string> $keys
     * @return array<int, string>
     */
    public function filter_hidden_itemmeta(array $keys): array {
        return array_values(array_unique(array_merge($keys, OrderSnapshot::hiddenKeys())));
    }

    /**
     * Màn sửa đơn Admin (chỉ hook này, không lẫn sang email khách): thêm Quy cách đóng thùng & Khách nhập m² + hao hụt.
     */
    public function render_admin_item_rows(mixed $item_id, \WC_Order_Item $item): void {
        unset($item_id);
        if (!$item instanceof \WC_Order_Item_Product || $item->get_meta('_drv_model') === '') {
            return;
        }
        $customer_keys = array_column(OrderSnapshot::displayRows($item), 'key');
        $rows          = array_filter(
            OrderSnapshot::displayRows($item, true),
            static fn (array $row): bool => !in_array($row['key'], $customer_keys, true)
        );
        if ($rows === []) {
            return;
        }
        echo '<table cellspacing="0" class="display_meta drv-admin-item-meta">';
        foreach ($rows as $row) {
            printf('<tr><th>%s:</th><td><p>%s</p></td></tr>', esc_html($row['label']), esc_html($row['value']));
        }
        echo '</table>';
    }

    /**
     * @param array<string, mixed> $methods
     * @return array<string, mixed>
     */
    public function register_shipping_method(array $methods): array {
        if (class_exists(QuoteShippingMethod::class)) {
            $methods[QuoteShippingMethod::ID] = QuoteShippingMethod::class;
        }
        return $methods;
    }

    /**
     * @param \WC_Shipping_Rate $method
     */
    public function filter_shipping_label(string $label, $method): string {
        if ($method instanceof \WC_Shipping_Rate && $method->get_method_id() === QuoteShippingMethod::ID) {
            return esc_html($method->get_label()) . ': <span class="u-text-moss u-bold">' . esc_html(QuoteShippingMethod::label()) . '</span>';
        }
        return $label;
    }

    public function filter_order_shipping_display(string $shipping, \WC_Order $order): string {
        $has_quote_later = false;
        foreach ($order->get_items('shipping') as $ship) {
            if ($ship instanceof \WC_Order_Item_Shipping && $ship->get_method_id() === QuoteShippingMethod::ID && (float) $ship->get_total() <= 0.0) {
                $has_quote_later = true;
            }
        }
        return ($has_quote_later && $order->get_meta('_drv_confirmed_at') === '') ? esc_html(QuoteShippingMethod::label()) : $shipping;
    }

    public function unhook_defaults(): void {
        // Payment được đặt ở cột trái (khối 04), không nằm trong `#order_review` (05-checkout.html).
        remove_action('woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20);
        // Khung điều khoản do `checkout/terms.php` dựng gọn: không bung nội dung trang `/dieu-khoan` & đoạn privacy mặc định.
        remove_action('woocommerce_checkout_terms_and_conditions', 'wc_terms_and_conditions_page_content', 30);
        remove_action('woocommerce_checkout_terms_and_conditions', 'wc_checkout_privacy_policy_text', 20);
        // Coupon tắt toàn site (Phase 1) — gỡ luôn form/notice ở đầu checkout.
        remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10);
    }

    /**
     * `form-checkout.php` tự dựng 4 khối: gỡ form billing/shipping mặc định để không in trùng trường.
     * Giữ nguyên 2 action `woocommerce_checkout_billing|shipping` cho plugin khác móc vào.
     */
    public function unhook_default_address_forms(): void {
        if (!class_exists(\WC_Checkout::class)) {
            return;
        }
        $checkout = \WC_Checkout::instance();
        remove_action('woocommerce_checkout_billing', [$checkout, 'checkout_form_billing']);
        remove_action('woocommerce_checkout_shipping', [$checkout, 'checkout_form_shipping']);
    }

    public function dequeue_select2(): void {
        if (function_exists('is_checkout') && is_checkout()) {
            wp_dequeue_script('selectWoo');
            wp_dequeue_style('select2');
        }
    }

    /**
     * Tỉnh hiện hành (POST → khách hàng phiên → rỗng) để SSR danh sách Phường/Xã.
     */
    public static function current_province(): string {
        $province = '';
        if (function_exists('WC') && WC()->customer) {
            $province = (string) WC()->customer->get_billing_state();
        }
        return Wards::isProvince($province) ? $province : '';
    }

    /**
     * Gợi ý tuyến giao theo tỉnh (`drv_settings.order.shipping_zone_hints`).
     *
     * @return array{map: array<string, string>, default: string}
     */
    public static function zone_hints(): array {
        $raw     = drv_setting('order.shipping_zone_hints', []);
        $map     = [];
        $default = 'Tuyến liên tỉnh: điều phối xe tải hoặc chành xe uy tín (2 - 4 ngày).';
        if (is_array($raw)) {
            foreach ($raw as $group => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $text = trim((string) ($row['text'] ?? ''));
                if ($group === 'default') {
                    $default = $text !== '' ? $text : $default;
                    continue;
                }
                foreach ((array) ($row['provinces'] ?? []) as $code) {
                    if (is_string($code) && $text !== '') {
                        $map[$code] = $text;
                    }
                }
            }
        }
        return ['map' => $map, 'default' => $default];
    }
}
