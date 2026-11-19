<?php
declare(strict_types=1);

use Dravit\Home\HomeData;

defined('ABSPATH') || exit;

/**
 * Section: Product Grid Block (`01-home.html` Section Deals, `03-product-single.html` Similar & Viewed, `S-3`).
 * Module lưới sản phẩm tái sử dụng: chế độ `deals`, `collection`, `custom`, `latest`, `viewed` (`drv_viewed` localStorage).
 *
 * @var array<string, mixed> $args
 */
$mode          = (string) ($args['mode'] ?? 'deals');
$is_viewed     = ($mode === 'viewed');
$framed        = !empty($args['framed']);
$section_id    = trim((string) ($args['section_id'] ?? ($is_viewed ? 'sec-recently-viewed' : ('home-' . $mode))));
$eyebrow       = trim((string) ($args['eyebrow'] ?? ($mode === 'deals' ? 'Khuyến mãi đặc biệt' : ($is_viewed ? '' : 'Sản phẩm chọn lọc'))));
$title         = trim((string) ($args['title'] ?? ($mode === 'deals' ? 'Chương Trình Khuyến Mãi & Giảm Giá' : ($is_viewed ? 'Sản phẩm vừa xem' : 'Danh sách sản phẩm'))));
$desc          = trim((string) ($args['description'] ?? ''));
$has_cta       = array_key_exists('cta_label', $args);
$cta_label     = $has_cta ? trim((string) $args['cta_label']) : ($mode === 'deals' ? 'Xem tất cả khuyến mãi' : ($is_viewed ? '' : 'Xem toàn bộ'));
$cta_path      = trim((string) ($args['cta_path'] ?? ($mode === 'deals' ? '/gach-uu-dai' : '/gach')));
$limit         = max(1, min(16, (int) ($args['limit'] ?? 4)));
$columns       = max(2, min(4, (int) ($args['columns'] ?? 4)));
$surface       = (string) ($args['surface'] ?? 'white');
$show_notice   = (bool) ($args['show_notice'] ?? ($mode === 'deals'));
$category_slug = trim((string) ($args['category_slug'] ?? ''));
$current_pid   = max(0, (int) ($args['current_product_id'] ?? 0));
$product_ids   = isset($args['product_ids']) && is_array($args['product_ids'])
    ? array_map('intval', $args['product_ids'])
    : [];

$products = $is_viewed ? [] : HomeData::getGridProducts($mode, $limit, $product_ids, $category_slug);
if (!$is_viewed && $products === []) {
    return;
}

$card_context = ($mode === 'deals') ? 'deals' : ($framed ? 'related' : 'catalog');

if ($framed) :
    ?>
    <section class="drv-pdp-panel"
             id="<?php echo esc_attr($section_id); ?>"
             aria-label="<?php echo esc_attr($title); ?>"
             data-drv-slider
             <?php echo $is_viewed ? 'data-drv-viewed-section data-current-product-id="' . esc_attr((string) $current_pid) . '" hidden' : ''; ?>>
        <div class="drv-pdp-panel__head">
            <h2 class="drv-pdp-panel__title"><?php echo esc_html($title); ?></h2>

            <?php if ($is_viewed) : ?>
                <button type="button" class="drv-pdp-clear-viewed u-text-xs u-text-muted" data-drv-clear-viewed>
                    Xóa lịch sử
                </button>
            <?php elseif ($desc !== '') : ?>
                <span class="u-text-xs u-text-muted"><?php echo esc_html($desc); ?></span>
            <?php endif; ?>
        </div>

        <div class="drv-pdp-slider-viewport">
            <button type="button"
                    class="drv-pdp-slider__btn drv-pdp-slider__btn--prev"
                    data-drv-slider-prev
                    aria-label="Xem sản phẩm trước">
                <i class="ph-bold ph-caret-left" aria-hidden="true"></i>
            </button>

            <div class="<?php echo esc_attr($is_viewed ? 'drv-pdp-viewed-grid' : 'drv-pdp-mini-grid'); ?>"
                 data-drv-slider-track
                 <?php echo $is_viewed ? 'data-drv-viewed-grid' : 'data-drv-stagger="55"'; ?>>
                <?php foreach ($products as $item) : ?>
                    <?php
                    drv_ui('product-card', [
                        'product'     => $item,
                        'context'     => $card_context,
                        'heading_tag' => 'h3',
                    ]);
                    ?>
                <?php endforeach; ?>
            </div>

            <button type="button"
                    class="drv-pdp-slider__btn drv-pdp-slider__btn--next"
                    data-drv-slider-next
                    aria-label="Xem sản phẩm tiếp theo">
                <i class="ph-bold ph-caret-right" aria-hidden="true"></i>
            </button>
        </div>

        <?php if (!$is_viewed && $cta_label !== '' && $cta_path !== '') : ?>
            <a href="<?php echo esc_url(HomeData::siteUrl($cta_path)); ?>" class="drv-more-btn">
                <span><?php echo esc_html($cta_label); ?></span>
                <i class="ph-bold ph-caret-right drv-icon--xs" aria-hidden="true"></i>
            </a>
        <?php endif; ?>
    </section>
    <?php
    return;
endif;

$sec_cls = 'l-section';
if ($surface === 'white') {
    $sec_cls .= ' l-section--white';
} elseif ($surface === 'champagne') {
    $sec_cls .= ' l-section--champagne';
}
?>
<section class="<?php echo esc_attr($sec_cls); ?>"
         id="<?php echo esc_attr($section_id); ?>"
         <?php echo $is_viewed ? 'data-drv-viewed-section data-current-product-id="' . esc_attr((string) $current_pid) . '" hidden' : ''; ?>>
    <div class="l-container">
        <?php
        drv_ui('section-head', [
            'eyebrow'      => $eyebrow,
            'title'        => $title,
            'desc'         => $desc,
            'heading_tag'  => 'h2',
            'action_label' => $cta_label !== '' ? $cta_label : null,
            'action_url'   => $cta_label !== '' ? HomeData::siteUrl($cta_path) : null,
            'action_style' => 'more-btn',
            'reveal'       => 'fade-up',
        ]);
        ?>

        <div class="l-grid l-grid--<?php echo (int) $columns; ?> drv-snap-row"
             <?php echo $is_viewed ? 'data-drv-viewed-grid' : 'data-drv-stagger="65"'; ?>>
            <?php foreach ($products as $item) : ?>
                <?php
                drv_ui('product-card', [
                    'product'     => $item,
                    'context'     => $card_context,
                    'heading_tag' => 'h3',
                ]);
                ?>
            <?php endforeach; ?>
        </div>

        <?php if ($show_notice && $mode === 'deals') : ?>
            <p class="drv-deal-frame__note" data-drv-reveal="fade-up">
                <i class="ph-fill ph-seal-percent drv-icon--sm" aria-hidden="true"></i>
                <span>Tất cả sản phẩm khuyến mãi đều là gạch loại 1 nguyên thùng, kiểm tra sẵn đồng nhất mã lô màu (Shade) &amp; kích thước thực tế (Caliber). Giá ưu đãi áp dụng đến khi hết số thùng hiển thị.</span>
            </p>
        <?php endif; ?>
    </div>
</section>
