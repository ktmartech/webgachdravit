<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$src       = $args['product'] ?? null;
$room_img  = isset($args['room_img']) && is_string($args['room_img']) ? trim($args['room_img']) : '';
$room_alt  = isset($args['room_alt']) && is_string($args['room_alt']) ? trim($args['room_alt']) : '';

if ($src instanceof \WC_Product) {
    $sku       = (string) $src->get_sku();
    $title     = $src->get_name();
    $url       = get_permalink($src->get_id()) ?: home_url('/gach');
    $price_m2  = (float) $src->get_meta('drv_price_m2');
    $thumb_id  = (int) $src->get_image_id();
    $swatch    = $thumb_id > 0 ? (string) wp_get_attachment_image_url($thumb_id, 'drv_card_sm') : '';
} else {
    $arr      = is_array($src) ? $src : $args;
    $sku      = (string) ($arr['sku'] ?? '');
    $title    = (string) ($arr['title'] ?? '');
    $url      = (string) ($arr['url'] ?? home_url('/gach'));
    $price_m2 = (float) ($arr['price_m2'] ?? 0);
    $swatch   = (string) ($arr['swatch_url'] ?? '');
}

if ($title === '') {
    return;
}

if ($swatch === '') {
    $swatch = DRV_URI . '/assets/images/placeholders/tile-marble-calacatta.svg';
}

$price_suffix = $price_m2 > 0 ? ' · ' . drv_format_vnd($price_m2) . '/m²' : ' · Liên hệ báo giá';
?>
<div>
    <?php if ($room_img !== '') : ?>
        <img src="<?php echo esc_url($room_img); ?>"
             width="760"
             height="440"
             loading="lazy"
             decoding="async"
             alt="<?php echo esc_attr($room_alt !== '' ? $room_alt : $title); ?>"
             class="drv-shop-look__scene">
    <?php endif; ?>
    <div class="drv-shop-look">
        <div class="drv-shop-look__product">
            <img src="<?php echo esc_url($swatch); ?>"
                 width="44"
                 height="44"
                 loading="lazy"
                 decoding="async"
                 alt="<?php echo esc_attr($sku !== '' ? $sku : $title); ?>"
                 class="drv-shop-look__swatch">
            <div class="l-stack l-stack--xs">
                <div class="l-cluster l-cluster--xs">
                    <span class="u-text-xs u-bold u-uppercase u-text-moss">Mã gạch trong ảnh:</span>
                    <?php if ($sku !== '') : ?>
                        <?php drv_ui('badge', ['text' => $sku, 'variant' => 'outline']); ?>
                    <?php endif; ?>
                </div>
                <span class="u-text-sm u-bold"><?php echo esc_html($title . $price_suffix); ?></span>
            </div>
        </div>
        <?php
        drv_ui('btn', [
            'label'      => 'Xem & Tính số viên',
            'href'       => untrailingslashit($url),
            'size'       => 'sm',
            'icon_right' => 'arrow-right',
        ]);
        ?>
    </div>
</div>

