<?php
declare(strict_types=1);

use Dravit\Home\HomeData;

defined('ABSPATH') || exit;

/**
 * Section: Product Grid Block (`01-home.html` Section Deals, `S-3`, `docs/07 §3.6`).
 * Module lưới sản phẩm tái sử dụng: chế độ `deals` (Ưu đãi lẻ lô tồn kho thùng/m²), `collection`, `custom`, `latest`.
 *
 * @var array<string, mixed> $args
 */

$mode          = (string) ($args['mode'] ?? 'deals');
$eyebrow       = trim((string) ($args['eyebrow'] ?? ($mode === 'deals' ? 'Khuyến mãi đặc biệt' : 'Sản phẩm chọn lọc')));
$title         = trim((string) ($args['title'] ?? ($mode === 'deals' ? 'Chương Trình Khuyến Mãi & Giảm Giá' : 'Danh sách sản phẩm')));
$desc          = trim((string) ($args['description'] ?? ''));
$cta_label     = trim((string) ($args['cta_label'] ?? ($mode === 'deals' ? 'Xem tất cả khuyến mãi' : 'Xem toàn bộ')));
$cta_path      = trim((string) ($args['cta_path'] ?? ($mode === 'deals' ? '/gach-uu-dai' : '/gach')));
$limit         = max(1, min(16, (int) ($args['limit'] ?? 4)));
$columns       = max(2, min(4, (int) ($args['columns'] ?? 4)));
$surface       = (string) ($args['surface'] ?? 'white');
$show_notice   = (bool) ($args['show_notice'] ?? ($mode === 'deals'));
$category_slug = trim((string) ($args['category_slug'] ?? ''));
$product_ids   = isset($args['product_ids']) && is_array($args['product_ids'])
    ? array_map('intval', $args['product_ids'])
    : [];

$sec_cls = 'l-section';
if ($surface === 'white') {
    $sec_cls .= ' l-section--white';
} elseif ($surface === 'champagne') {
    $sec_cls .= ' l-section--champagne';
}

$products = HomeData::getGridProducts($mode, $limit, $product_ids, $category_slug);
if ($products === []) {
    return;
}

$card_context = ($mode === 'deals') ? 'deals' : 'catalog';
?>
<section class="<?php echo esc_attr($sec_cls); ?>" id="home-<?php echo esc_attr($mode); ?>">
    <div class="l-container">
        <?php
        drv_ui('section-head', [
            'eyebrow'      => $eyebrow,
            'title'        => $title,
            'desc'         => $desc,
            'heading_tag'  => 'h2',
            'action_label' => $cta_label,
            'action_url'   => HomeData::siteUrl($cta_path),
            'action_style' => 'more-btn',
            'reveal'       => 'fade-up',
        ]);
        ?>

        <div class="l-grid l-grid--<?php echo (int) $columns; ?> drv-snap-row" data-drv-stagger="65">
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
