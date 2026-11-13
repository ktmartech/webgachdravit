<?php
declare(strict_types=1);

use Dravit\Seo\FaqResolver;

defined('ABSPATH') || exit;

/**
 * Section thông số kỹ thuật chuẩn GEO & bài tư vấn chân trang danh mục (`drv_section('geo-spec-section')`, docs/06 §7).
 *
 * @var array<string, mixed> $args
 */
$eyebrow = trim((string) ($args['eyebrow'] ?? 'Tiêu chuẩn kỹ thuật & Kinh nghiệm chọn gạch'));
$title   = trim((string) ($args['title'] ?? ''));
$desc    = trim((string) ($args['desc'] ?? ''));
$intro   = trim((string) ($args['intro'] ?? ''));
$note    = trim((string) ($args['note'] ?? 'Thông số tổng hợp từ tiêu chuẩn ISO 13006 / TCVN và quy cách đóng gói thực tế tại kho DRAVIT.'));

$headers = isset($args['headers']) && is_array($args['headers']) ? $args['headers'] : [];
$rows    = isset($args['rows']) && is_array($args['rows']) ? $args['rows'] : [];
$bullets = isset($args['bullets']) && is_array($args['bullets']) ? $args['bullets'] : [];

if ($rows === [] && isset($args['raw_geo'])) {
    $parsed  = FaqResolver::parseGeoTable($args['raw_geo']);
    $headers = $headers !== [] ? $headers : $parsed['headers'];
    $rows    = $parsed['rows'];
    $bullets = $bullets !== [] ? $bullets : $parsed['bullets'];
}

if ($rows === [] && $intro === '') {
    $term = null;
    if (drv_is_woo_active() && function_exists('is_shop') && is_shop()) {
        $gach = get_term_by('slug', 'gach', 'product_cat');
        $term = ($gach instanceof \WP_Term) ? $gach : null;
    } elseif (function_exists('is_tax') && (is_tax('product_cat') || is_tax(['pa_bo-suu-tap', 'pa_xuat-xu']))) {
        $queried = get_queried_object();
        $term    = ($queried instanceof \WP_Term) ? $queried : null;
    }

    if ($term instanceof \WP_Term) {
        if ($title === '') {
            $title = 'Thông số khuyến nghị khi chọn ' . $term->name;
        }
        $intro  = trim((string) get_term_meta($term->term_id, 'drv_bottom_guide', true));
        $parsed = FaqResolver::parseGeoTable(get_term_meta($term->term_id, 'drv_geo_table', true));
        if ($headers === []) {
            $headers = $parsed['headers'];
        }
        $rows = $parsed['rows'];
        if ($bullets === []) {
            $bullets = $parsed['bullets'];
        }
    }
}

if ($rows === [] && $bullets === [] && $intro === '') {
    return;
}

if ($title === '') {
    $title = 'Bảng thông số kỹ thuật tham chiếu';
}
?>
<section id="bang-thong-so-geo" class="l-section drv-geo-section" aria-labelledby="drv-geo-sec-heading">
    <div class="l-container l-stack l-stack--lg">
        <div class="drv-sec-head">
            <div class="l-stack l-stack--xs">
                <?php if ($eyebrow !== '') : ?>
                    <span class="drv-sec-head__eyebrow"><?php echo esc_html($eyebrow); ?></span>
                <?php endif; ?>
                <h2 id="drv-geo-sec-heading"><?php echo esc_html($title); ?></h2>
                <?php if ($desc !== '') : ?>
                    <p class="drv-sec-head__desc"><?php echo esc_html($desc); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($rows !== [] || $bullets !== []) : ?>
            <?php
            drv_ui('geo-table', [
                'title'   => 'Bảng tóm tắt tiêu chuẩn kỹ thuật',
                'caption' => $title,
                'headers' => $headers,
                'rows'    => $rows,
                'bullets' => $bullets,
                'note'    => $note,
            ]);
            ?>
        <?php endif; ?>

        <?php if ($intro !== '') : ?>
            <div class="drv-prose u-text-sm">
                <?php echo wp_kses_post(wpautop($intro)); ?>
            </div>
        <?php endif; ?>
    </div>
</section>

