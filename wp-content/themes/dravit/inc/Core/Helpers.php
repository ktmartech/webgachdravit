<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

use Dravit\Core\SectionRegistry;
use Dravit\Core\Settings;
use Dravit\Core\Template;
use Dravit\Units\Calc;
use Dravit\Units\UnitRegistry;

/**
 * Đọc giá trị cấu hình từ option hợp nhất `drv_settings` theo đường dẫn dấu chấm (VD: `contact.hotline`).
 */
function drv_setting(string $path, mixed $default = null): mixed {
    return Settings::get($path, $default);
}

/**
 * Render một UI primitive chuẩn hóa tại `template-parts/ui/{name}.php` (Tầng 2, ADR D-17).
 *
 * @param array<string, mixed> $props
 */
function drv_ui(string $name, array $props = []): void {
    Template::render_ui($name, $props);
}

/**
 * Trả về chuỗi HTML của một UI primitive chuẩn hóa tại `template-parts/ui/{name}.php`.
 *
 * @param array<string, mixed> $props
 */
function drv_ui_html(string $name, array $props = []): string {
    return Template::get_ui_html($name, $props);
}

/**
 * Render một module Section tái sử dụng tại `template-parts/sections/{name}.php` (Tầng 4, Phương án S-3, ADR D-34).
 *
 * @param array<string, mixed> $props
 */
function drv_section(string $name, array $props = []): void {
    SectionRegistry::render($name, $props);
}

/**
 * Định dạng tiền Việt Nam chuẩn Dravit: dấu chấm phân cách nghìn, ký hiệu `đ` viết thường sát số (`385.000đ`).
 */
function drv_format_vnd(int|float $amount): string {
    $rounded = (int) round((float) $amount);
    return number_format($rounded, 0, ',', '.') . 'đ';
}

/**
 * Định dạng số thập phân chuẩn Việt Nam: dấu phẩy thập phân, cắt số 0 vô nghĩa ở cuối (`1,92`, `8,5`).
 */
function drv_format_decimal(int|float $value, int $decimals = 2): string {
    $formatted = number_format((float) $value, $decimals, ',', '.');
    if ($decimals > 0) {
        $formatted = rtrim(rtrim($formatted, '0'), ',');
    }
    return $formatted;
}

/**
 * Chuẩn hóa số điện thoại để gắn vào `tel:` hoặc `https://zalo.me/`.
 */
function drv_clean_phone(string $phone): string {
    return (string) preg_replace('/[^0-9+]/', '', $phone);
}

function drv_is_woo_active(): bool {
    return class_exists('WooCommerce', false);
}

function drv_is_rank_math_active(): bool {
    return defined('RANK_MATH_VERSION') || class_exists('RankMath', false);
}

function drv_is_fluent_forms_active(): bool {
    return defined('FLUENTFORM') || function_exists('wpFluentForm');
}

function drv_is_litespeed_active(): bool {
    return defined('LSCWP_V') || (function_exists('has_action') && (bool) has_action('litespeed_init'));
}

/**
 * Render Logo thương hiệu từ WP Media Library (`header_logo_id` / `footer_logo_id`),
 * tự động fallback về bộ WebP 1x/2x + PNG mặc định của theme nếu chưa cấu hình.
 */
function drv_render_brand_logo(string $slot = 'header', bool $lazy = false): void {
    $is_footer  = ($slot === 'footer');
    $brand_name = (string) drv_setting('company.brand_name', 'DRAVIT');
    $setting_id = $is_footer
        ? (int) drv_setting('company.footer_logo_id', 0)
        : (int) drv_setting('company.header_logo_id', 0);

    if (!$is_footer && $setting_id <= 0 && function_exists('get_theme_mod')) {
        $setting_id = (int) get_theme_mod('custom_logo', 0);
    }

    if ($setting_id > 0 && function_exists('wp_get_attachment_image')) {
        $img_attrs = [
            'alt'      => $brand_name,
            'decoding' => 'async',
            'loading'  => $lazy ? 'lazy' : false,
        ];
        $attachment_html = wp_get_attachment_image($setting_id, 'full', false, $img_attrs);
        if (is_string($attachment_html) && $attachment_html !== '') {
            echo $attachment_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by wp_get_attachment_image
            return;
        }
    }

    $prefix   = $is_footer ? 'logo-ivory' : 'logo-navy';
    $logo_1x  = DRV_URI . '/assets/images/' . $prefix . '@1x.webp';
    $logo_2x  = DRV_URI . '/assets/images/' . $prefix . '@2x.webp';
    $logo_png = DRV_URI . '/assets/images/' . $prefix . '.png';
    $width    = $is_footer ? 140 : 150;
    $height   = $is_footer ? 40 : 44;
    ?>
    <picture>
        <source srcset="<?php echo esc_url($logo_1x); ?> 1x, <?php echo esc_url($logo_2x); ?> 2x" type="image/webp">
        <img src="<?php echo esc_url($logo_png); ?>"
             width="<?php echo esc_attr((string) $width); ?>"
             height="<?php echo esc_attr((string) $height); ?>"
             <?php echo $lazy ? 'loading="lazy" ' : ''; ?>decoding="async"
             alt="<?php echo esc_attr($brand_name); ?>">
    </picture>
    <?php
}

/**
 * Render HTML cụm giá sản phẩm chuẩn Dravit (`priced`, `sale`, `quote_only`).
 *
 * @param \WC_Product|array<string, mixed> $product
 */
function drv_price_html(\WC_Product|array $product, string $context = 'card'): string {
    return drv_ui_html('price', [
        'product' => $product,
        'context' => $context,
    ]);
}

/**
 * Chuỗi quy đổi số lượng giao dịch sang thùng · m² · kg cho giỏ hàng, thanh toán & email.
 *
 * @param \WC_Product|array<string, mixed> $product
 */
function drv_qty_summary(\WC_Product|array $product, int $qty): string {
    if ($product instanceof \WC_Product) {
        return UnitRegistry::modelForProduct($product)->qty_summary($product, $qty);
    }

    $area_piece = isset($product['area_piece'])
        ? (float) $product['area_piece']
        : Calc::pieceAreaM2((int) ($product['piece_w'] ?? 0), (int) ($product['piece_h'] ?? 0));
    $ppb        = max(1, (int) ($product['pieces_per_box'] ?? 1));
    $kg_per_box = max(0.0, (float) ($product['kg_per_box'] ?? ($product['weight_per_box'] ?? 0)));
    $unit_label = (string) ($product['unit_label'] ?? 'viên');

    $line  = Calc::calcCartLine([
        'pieces'       => max(1, $qty),
        'areaPiece'    => $area_piece,
        'piecesPerBox' => $ppb,
        'kgPerBox'     => $kg_per_box,
        'unitLabel'    => $unit_label,
    ]);
    $parts = [$line['boxText']];
    if ($line['actualM2'] > 0.0) {
        $parts[] = drv_format_decimal($line['actualM2'], 2) . ' m²';
    }
    if ($line['totalKg'] > 0.0) {
        $parts[] = drv_format_decimal($line['totalKg'], 1) . ' kg';
    }

    return implode(' · ', $parts);
}
