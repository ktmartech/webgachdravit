<?php
declare(strict_types=1);

use Dravit\Seo\UrlNormalizer;
use Dravit\Units\PriceState;

defined('ABSPATH') || exit;

/**
 * UI Primitive: Thanh gắn mã gạch dưới ảnh phối cảnh (`drv_ui('shop-the-look')`, docs/16 §3.2, docs/20 §4.2).
 * Hỗ trợ:
 * - Truyền mảng `items` (1..2 mẫu: gạch lát nền + gạch ốp tường trong cùng ảnh phối cảnh).
 * - Truyền đơn `product` (`\WC_Product` hoặc mảng tóm tắt cho `/ui-kit`).
 *
 * @var array<string, mixed> $args
 */
$room_img = isset($args['room_img']) && is_string($args['room_img']) ? trim($args['room_img']) : '';
$room_alt = isset($args['room_alt']) && is_string($args['room_alt']) ? trim($args['room_alt']) : '';
$caption  = isset($args['caption']) && is_string($args['caption']) ? trim($args['caption']) : '';
$def_role = isset($args['role']) && is_string($args['role']) && trim($args['role']) !== ''
    ? trim($args['role'])
    : 'Mã gạch trong ảnh';

/**
 * Chuẩn hóa 1 phần tử sản phẩm sang mảng hiển thị thống nhất.
 *
 * @return array{
 *   id: int,
 *   sku: string,
 *   title: string,
 *   url: string,
 *   swatch: string,
 *   price_text: string,
 *   is_quote: bool,
 *   role: string
 * }|null
 */
$normalize_item = null;
$normalize_item = static function (mixed $raw, string $role_label) use (&$normalize_item): ?array {
    if ($raw instanceof \WC_Product) {
        $id          = $raw->get_id();
        $sku         = (string) $raw->get_sku();
        $title       = $raw->get_name();
        $permalink   = function_exists('get_permalink') && $id > 0 ? (string) get_permalink($id) : '';
        $url         = $permalink !== '' ? UrlNormalizer::untrailingslashUrl($permalink) : UrlNormalizer::untrailingslashUrl(home_url('/gach'));
        $state       = PriceState::resolve($raw);
        $is_quote    = ($state === PriceState::QUOTE_ONLY);
        $reg_m2      = max(0, (int) $raw->get_meta('drv_price_m2', true));
        $sale_m2     = max(0, (int) $raw->get_meta('drv_sale_price_m2', true));
        $eff_m2      = ($state === PriceState::SALE && $sale_m2 > 0 && $sale_m2 < $reg_m2) ? $sale_m2 : $reg_m2;
        $price_text  = (!$is_quote && $eff_m2 > 0) ? (drv_format_vnd($eff_m2) . '/m²') : 'Liên hệ báo giá';
        $thumb_id    = (int) $raw->get_image_id();
        $swatch      = ($thumb_id > 0 && function_exists('wp_get_attachment_image_url'))
            ? (string) wp_get_attachment_image_url($thumb_id, 'thumbnail')
            : '';

        if ($swatch === '') {
            $swatch = DRV_URI . '/assets/images/placeholders/tile-marble-calacatta.svg';
        }

        return [
            'id'         => $id,
            'sku'        => $sku,
            'title'      => $title,
            'url'        => $url,
            'swatch'     => $swatch,
            'price_text' => $price_text,
            'is_quote'   => $is_quote,
            'role'       => $role_label,
        ];
    }

    if (!is_array($raw)) {
        return null;
    }

    $product_obj = $raw['product'] ?? null;
    $custom_role = isset($raw['role']) && is_string($raw['role']) && trim($raw['role']) !== ''
        ? trim($raw['role'])
        : $role_label;

    if ($product_obj instanceof \WC_Product) {
        return $normalize_item($product_obj, $custom_role);
    }

    $title = trim((string) ($raw['title'] ?? ''));
    if ($title === '') {
        return null;
    }

    $id         = max(0, (int) ($raw['id'] ?? 0));
    $sku        = trim((string) ($raw['sku'] ?? ''));
    $raw_url    = trim((string) ($raw['url'] ?? (function_exists('home_url') ? home_url('/gach') : '/gach')));
    $url        = UrlNormalizer::untrailingslashUrl($raw_url);
    $price_m2   = (float) ($raw['price_m2'] ?? 0);
    $is_quote   = !empty($raw['is_quote']) || $price_m2 <= 0;
    $price_text = !$is_quote ? (drv_format_vnd($price_m2) . '/m²') : 'Liên hệ báo giá';
    $swatch     = trim((string) ($raw['swatch_url'] ?? ''));
    if ($swatch === '') {
        $swatch = DRV_URI . '/assets/images/placeholders/tile-marble-calacatta.svg';
    }

    return [
        'id'         => $id,
        'sku'        => $sku,
        'title'      => $title,
        'url'        => $url,
        'swatch'     => $swatch,
        'price_text' => $price_text,
        'is_quote'   => $is_quote,
        'role'       => $custom_role,
    ];
};

$resolved_items = [];
if (isset($args['items']) && is_array($args['items']) && $args['items'] !== []) {
    foreach (array_slice($args['items'], 0, 2) as $entry) {
        $norm = $normalize_item($entry, $def_role);
        if ($norm !== null) {
            $resolved_items[] = $norm;
        }
    }
} else {
    $single_src = $args['product'] ?? $args;
    $norm       = $normalize_item($single_src, $def_role);
    if ($norm !== null) {
        $resolved_items[] = $norm;
    }
}

if ($resolved_items === []) {
    return;
}

$first = $resolved_items[0];
?>
<div class="drv-stl-wrap" role="region" aria-label="Sản phẩm sử dụng trong ảnh phối cảnh">
    <?php if ($room_img !== '') : ?>
        <img src="<?php echo esc_url($room_img); ?>"
             width="760"
             height="440"
             loading="lazy"
             decoding="async"
             alt="<?php echo esc_attr($room_alt !== '' ? $room_alt : $first['title']); ?>"
             class="drv-shop-look__scene">
    <?php endif; ?>

    <div class="drv-stl-bar">
        <?php foreach ($resolved_items as $item) : ?>
            <div class="drv-stl-item">
                <a href="<?php echo esc_url($item['url']); ?>" class="drv-stl-item__thumb" aria-hidden="true" tabindex="-1">
                    <img src="<?php echo esc_url($item['swatch']); ?>"
                         width="64"
                         height="64"
                         loading="lazy"
                         decoding="async"
                         alt="<?php echo esc_attr($item['sku'] !== '' ? $item['sku'] : $item['title']); ?>"
                         class="drv-stl-item__swatch">
                </a>

                <div class="drv-stl-item__info">
                    <div class="drv-stl-item__meta">
                        <span class="drv-stl-item__role">
                            <?php echo esc_html($item['role']); ?>
                        </span>
                        <?php if ($item['sku'] !== '') : ?>
                            <span class="drv-stl-item__sku u-tabular"><?php echo esc_html($item['sku']); ?></span>
                        <?php endif; ?>
                    </div>
                    <a class="drv-stl-item__title" href="<?php echo esc_url($item['url']); ?>">
                        <?php echo esc_html($item['title']); ?>
                    </a>
                </div>

                <div class="drv-stl-item__action-group">
                    <div class="drv-stl-item__price-wrap">
                        <strong class="drv-stl-item__price u-tabular<?php echo $item['is_quote'] ? ' is-quote' : ''; ?>">
                            <?php echo esc_html($item['price_text']); ?>
                        </strong>
                    </div>
                    <div class="drv-stl-item__btns">
                        <?php if ($item['id'] > 0) : ?>
                            <button type="button"
                                    class="drv-btn drv-btn--sm drv-btn--teal-subtle"
                                    data-drv-action="quote-add"
                                    data-product-id="<?php echo esc_attr((string) $item['id']); ?>"
                                    data-sku="<?php echo esc_attr($item['sku']); ?>"
                                    data-drv-area="15">
                                <?php drv_ui('icon', ['name' => 'plus', 'size' => 'xs']); ?>
                                <span>Báo giá</span>
                            </button>
                        <?php endif; ?>
                        <a href="<?php echo esc_url($item['url']); ?>" class="drv-btn drv-btn--sm drv-btn--outline">
                            <span>Xem mẫu</span>
                            <?php drv_ui('icon', ['name' => 'arrow-right', 'size' => 'xs']); ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($caption !== '') : ?>
            <div class="drv-stl-caption">
                <?php drv_ui('icon', ['name' => 'lightbulb', 'size' => 'xs']); ?>
                <span><?php echo esc_html($caption); ?></span>
            </div>
        <?php endif; ?>
    </div>
</div>
