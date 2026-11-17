<?php
declare(strict_types=1);

namespace Dravit\Woo;

defined('ABSPATH') || exit;

if (!class_exists(\WC_Shipping_Method::class)) {
    return;
}

/**
 * Phương thức vận chuyển `drv_quote_later` (docs/04 §6, D-10): phí = 0 lúc đặt, nhân viên báo phí sau khi xác nhận đơn.
 * Nhãn hiển thị "Báo sau khi xác nhận đơn" thay vì "0đ" (C-9).
 */
final class QuoteShippingMethod extends \WC_Shipping_Method {
    public const ID = 'drv_quote_later';

    /**
     * @param int $instance_id
     */
    public function __construct($instance_id = 0) {
        $this->id                 = self::ID;
        $this->instance_id        = absint($instance_id);
        $this->method_title       = 'Giao gạch - báo phí sau khi xác nhận đơn';
        $this->method_description = 'Phí vận chuyển = 0 lúc đặt hàng. Nhân viên điều vận nhập phí thực tế trong đơn trước khi khách thanh toán.';
        $this->supports           = ['shipping-zones', 'instance-settings'];
        $this->init();
    }

    public function init(): void {
        $this->instance_form_fields = [
            'title' => [
                'title'   => 'Tên hiển thị',
                'type'    => 'text',
                'default' => 'Giao gạch tận công trình',
            ],
        ];
        $this->init_settings();
        $this->title   = (string) $this->get_option('title', 'Giao gạch tận công trình');
        // Chỉ có cài đặt theo instance (zone) — WooCommerce tự lưu qua AJAX màn Shipping Zones.
        $this->enabled = 'yes';
    }

    /**
     * @param array<string, mixed> $package
     */
    public function calculate_shipping($package = []): void {
        unset($package);
        $this->add_rate([
            'id'        => $this->get_rate_id(),
            'label'     => $this->title,
            'cost'      => 0,
            'taxes'     => false,
            'meta_data' => ['drv_quote_later' => '1'],
        ]);
    }

    public static function label(): string {
        return CartPresenter::shippingLabel();
    }
}
