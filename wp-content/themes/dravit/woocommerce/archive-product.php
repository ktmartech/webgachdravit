<?php
/**
 * The Template for displaying product archives, including the main shop page which is a post type archive
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 8.6.0
 */
declare(strict_types=1);

use Dravit\Core\Config;
use Dravit\Core\Integrations\LiteSpeedCompat;
use Dravit\Core\Template;
use Dravit\Filters\FacetCounter;
use Dravit\Filters\Renderer;
use Dravit\Filters\UrlState;
use Dravit\Seo\UrlNormalizer;

defined('ABSPATH') || exit;

if (class_exists(LiteSpeedCompat::class)) {
    LiteSpeedCompat::add_cache_tag('drv_catalog');
}

get_header();

global $wp_query;

$state        = UrlState::fromCurrentRequest();
$collections  = Config::get('collections');
$archive_name = Renderer::resolveArchiveName($state);

$queried_term = null;
if (function_exists('is_shop') && is_shop()) {
    $gach_term    = get_term_by('slug', 'gach', 'product_cat');
    $queried_term = ($gach_term instanceof \WP_Term) ? $gach_term : null;
} else {
    $obj          = get_queried_object();
    $queried_term = ($obj instanceof \WP_Term) ? $obj : null;
}

$term_id = ($queried_term instanceof \WP_Term) ? (int) $queried_term->term_id : 0;

$h1 = $term_id > 0 ? trim((string) get_term_meta($term_id, 'drv_h1', true)) : '';
if ($h1 === '' && $term_id > 0) {
    $h1 = trim((string) get_term_meta($term_id, 'drv_h1_override', true));
}
if ($h1 === '' && $state->baseTaxonomy() === 'product_cat' && isset($collections[$state->baseSlug()]['h1'])) {
    $h1 = trim((string) $collections[$state->baseSlug()]['h1']);
}
if ($h1 === '') {
    $h1 = $archive_name;
}

$intro = $term_id > 0 ? trim((string) get_term_meta($term_id, 'drv_intro', true)) : '';
if ($intro === '' && $queried_term instanceof \WP_Term && trim($queried_term->description) !== '') {
    $intro = trim(wp_strip_all_tags($queried_term->description));
}
if ($intro === '' && $state->baseTaxonomy() === 'product_cat' && isset($collections[$state->baseSlug()]['preview_desc'])) {
    $intro = trim((string) $collections[$state->baseSlug()]['preview_desc']);
}

$seo_footer_text = $term_id > 0 ? trim((string) get_term_meta($term_id, 'drv_seo_footer', true)) : '';
if ($seo_footer_text === '' && $term_id > 0) {
    $seo_footer_text = trim((string) get_term_meta($term_id, 'drv_bottom_guide', true));
}

$base_stats  = FacetCounter::getBaseStats($state);
$counts      = FacetCounter::getCounts($state);
$clean_state = UrlState::fromArray($state->base(), [], 1, $state->perPage());
$base_counts = $state->hasActiveFilters() ? FacetCounter::getCounts($clean_state) : $counts;
$view_data   = Renderer::buildFilterViewData($state, $counts, $base_counts);

$products = [];
if (have_posts() && function_exists('wc_get_product')) {
    while (have_posts()) {
        the_post();
        $product_obj = wc_get_product(get_the_ID());
        if ($product_obj instanceof \WC_Product) {
            $products[] = $product_obj;
        }
    }
    wp_reset_postdata();
}

$total_found = ($wp_query instanceof \WP_Query) ? max(0, (int) $wp_query->found_posts) : count($products);
$total_pages = ($wp_query instanceof \WP_Query) ? max(0, (int) $wp_query->max_num_pages) : 1;
$offset      = ($state->page() - 1) * $state->perPage();
$shown_count = $total_found > 0 ? min($total_found, $offset + count($products)) : 0;
$next_url    = $state->buildNextPageUrl($total_pages);

$is_clean_first_page = (!$state->hasAnyActiveParam() && $state->page() === 1);

$raw_geo_meta  = $term_id > 0 ? get_term_meta($term_id, 'drv_geo_table', true) : '';
$fallback_rows = [
    ['Phòng khách & đại sảnh', 'E ≤ 0,5% (Porcelain / Đá nung kết)', 'R9 - R10', '80x80, 60x120 cm'],
    ['Nhà tắm & sàn vùng ướt', 'E ≤ 0,5% (Porcelain nhám mịn)', 'R10 - R11', '30x60, 60x60 cm'],
    ['Tường bếp & vách trang trí', 'E ≤ 3% (Men chống bám dầu mỡ)', 'Men phẳng dễ lau', '30x60, 60x120, gạch thẻ'],
    ['Sân vườn & gara ô tô', 'E ≤ 0,1% - 0,5% (Xương dày 12 - 20 mm)', 'R11 - R12', '60x60 cm chịu tải'],
];

$silo_current_slug = ($state->baseTaxonomy() === 'product_cat') ? $state->baseSlug() : '';
?>
<main id="main-content"
      data-drv-catalog-page
      data-base="<?php echo esc_attr($state->base()); ?>"
      data-base-url="<?php echo esc_attr($state->baseUrl()); ?>">
    <?php
    Template::render_part('template-parts/catalog/hero', [
        'state'           => $state,
        'h1'              => $h1,
        'intro'           => $intro,
        'base_stats'      => $base_stats,
        'has_geo_section' => $is_clean_first_page,
    ]);
    ?>

    <div class="drv-silo-strip">
        <div class="l-container">
            <?php
            drv_section('silo-tiles-bar', [
                'current_slug' => $silo_current_slug,
                'limit'        => 6,
            ]);
            ?>
        </div>
    </div>

    <section id="drv-catalog-section" class="l-section l-section--tight drv-catalog-sec" aria-label="Danh sách mẫu gạch và bộ lọc thông số">
        <div class="l-container">
            <div id="drv-catalog-layout" class="l-split l-split--catalog">
                <aside id="drv-filter-sidebar"
                       class="drv-filter-sidebar"
                       aria-label="Bộ lọc thông số kỹ thuật gạch"
                       data-filter-desktop-slot>
                    <?php
                    Template::render_part('template-parts/catalog/filters', [
                        'state'        => $state,
                        'archive_name' => $archive_name,
                        'showroom'     => $view_data['showroom'],
                        'ui_groups'    => $view_data['ui_groups'],
                        'total_found'  => $total_found,
                    ]);
                    ?>
                </aside>

                <div class="drv-catalog-main">
                    <?php
                    Template::render_part('template-parts/catalog/toolbar', [
                        'state'        => $state,
                        'shown'        => $shown_count,
                        'total'        => $total_found,
                        'archive_name' => $archive_name,
                    ]);
                    ?>

                    <div class="drv-catalog-grid-stage" data-drv-grid-stage>
                        <div class="drv-catalog-loader" aria-hidden="true">
                            <div class="drv-catalog-loader__pill">
                                <span class="drv-spin-ring"></span>
                                <span>Đang lọc mẫu gạch...</span>
                            </div>
                        </div>

                        <div id="drv-product-grid"
                             class="drv-catalog-grid<?php echo $products === [] ? ' is-empty' : ''; ?>"
                             data-drv-product-grid
                             aria-busy="false">
                            <?php echo Renderer::renderItemsHtml($products, $offset, $state, $archive_name); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside product-card.php & empty.php ?>
                        </div>
                    </div>

                    <div id="drv-catalog-pagination" class="drv-catalog-pagination" data-drv-pagination-wrap>
                        <?php
                        drv_ui('load-more', [
                            'shown'    => $shown_count,
                            'total'    => $total_found,
                            'next_url' => $next_url,
                            'per_page' => $state->perPage(),
                        ]);
                        ?>

                        <?php if ($total_pages > 1 && function_exists('paginate_links')) :
                            $page_base = $state->baseUrl() . '%_%';
                            $qs        = $state->queryString();
                            $raw_links = paginate_links([
                                'base'      => $page_base,
                                'format'    => '/page/%#%',
                                'current'   => $state->page(),
                                'total'     => $total_pages,
                                'type'      => 'list',
                                'prev_text' => 'Trang trước',
                                'next_text' => 'Trang tiếp',
                                'add_args'  => false,
                            ]);
                            if (is_string($raw_links) && $raw_links !== '') :
                                // Bổ sung chuỗi query chuẩn hóa nếu đang có bộ lọc & khử dấu `/` cuối cho trang 1.
                                $clean_links = (string) preg_replace_callback(
                                    '#href="([^"]+)"#i',
                                    static function (array $m) use ($qs): string {
                                        $u = UrlNormalizer::untrailingslashUrl($m[1]);
                                        if ($qs !== '' && !str_contains($u, '?')) {
                                            $u .= '?' . $qs;
                                        }
                                        return 'href="' . esc_url($u) . '"';
                                    },
                                    $raw_links
                                );
                                ?>
                                <nav class="drv-catalog-nojs-pagination" aria-label="Phân trang danh mục">
                                    <?php echo wp_kses_post($clean_links); ?>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if ($is_clean_first_page) : ?>
        <?php
        $geo_props = [
            'title' => 'Bảng đối chiếu tiêu chuẩn kỹ thuật khi chọn ' . mb_strtolower($archive_name),
            'desc'  => 'Mọi mã gạch tại DRAVIT đều công bố rõ độ hút nước, chỉ số ma sát chống trượt và quy cách thùng để chủ nhà, thợ thầu và kiến trúc sư chọn đúng công năng.',
        ];
        if (is_string($raw_geo_meta) && trim($raw_geo_meta) !== '' || is_array($raw_geo_meta) && $raw_geo_meta !== []) {
            $geo_props['raw_geo'] = $raw_geo_meta;
        } else {
            $geo_props['headers'] = ['Khu vực sử dụng', 'Độ hút nước khuyến nghị', 'Chỉ số chống trượt', 'Khổ gạch phổ biến'];
            $geo_props['rows']    = $fallback_rows;
        }
        drv_section('geo-spec-section', $geo_props);

        if ($seo_footer_text !== '') {
            Template::render_part('template-parts/catalog/seo-footer', [
                'heading' => 'Hướng dẫn chọn mua & tính hao hụt ' . mb_strtolower($archive_name),
                'content' => $seo_footer_text,
            ]);
        }

        drv_section('faq-section', [
            'title' => 'Câu hỏi thường gặp khi chọn mua & nhận báo giá ' . mb_strtolower($archive_name),
        ]);
        ?>
    <?php endif; ?>
</main>

<?php
Template::render_part('template-parts/catalog/mobile-filter-sheet', [
    'state'       => $state,
    'total_found' => $total_found,
]);

get_footer();
