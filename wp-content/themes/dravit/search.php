<?php
declare(strict_types=1);

use Dravit\Catalog\SearchNormalizer;
use Dravit\Core\Config;

defined('ABSPATH') || exit;

get_header();

global $wp_query;
$raw_query     = trim((string) get_search_query());
$found_count   = ($wp_query instanceof \WP_Query) ? (int) $wp_query->found_posts : 0;
$matched_silos = $raw_query !== ''
    ? SearchNormalizer::matchCollectionSlugs($raw_query, Config::get('collections'), 6)
    : [];
?>
<main id="main-content">
    <div class="l-container u-py-md">
        <?php drv_ui('breadcrumb'); ?>
    </div>

    <?php if (have_posts()) : ?>
        <section class="l-section">
            <div class="l-container l-stack l-stack--lg">
                <header class="drv-sec-head">
                    <div class="l-stack l-stack--xs">
                        <span class="drv-sec-head__eyebrow">Tra cứu kho gạch DRAVIT</span>
                        <h1>
                            <?php
                            echo esc_html(
                                $raw_query !== ''
                                    ? sprintf('Kết quả tìm kiếm cho "%s"', $raw_query)
                                    : 'Tất cả kết quả tìm kiếm'
                            );
                            ?>
                        </h1>
                        <p class="drv-sec-head__desc u-tabular">
                            <?php echo esc_html(sprintf('Tìm thấy %d mẫu gạch khớp thông số kỹ thuật hoặc mã SKU.', $found_count)); ?>
                        </p>
                    </div>
                </header>

                <?php if ($matched_silos !== []) : ?>
                    <?php
                    drv_section('silo-tiles-bar', [
                        'title' => 'Danh mục chuyên sâu liên quan',
                        'slugs' => $matched_silos,
                        'limit' => 6,
                    ]);
                    ?>
                <?php endif; ?>

                <div class="l-grout-grid" data-cols="4">
                    <?php
                    $card_idx = 0;
                    while (have_posts()) :
                        the_post();
                        $product = function_exists('wc_get_product') ? wc_get_product(get_the_ID()) : null;
                        if (!$product instanceof \WC_Product) {
                            continue;
                        }
                        drv_ui('product-card', [
                            'product'       => $product,
                            'priority_load' => ($card_idx < 4),
                            'heading_tag'   => 'h2',
                        ]);
                        $card_idx++;
                    endwhile;
                    ?>
                </div>

                <?php
                $pagination = get_the_posts_pagination([
                    'mid_size'  => 2,
                    'prev_text' => 'Trang trước',
                    'next_text' => 'Trang tiếp',
                ]);
                if (is_string($pagination) && $pagination !== '') {
                    echo wp_kses_post($pagination);
                }
                ?>
            </div>
        </section>
    <?php else : ?>
        <?php
        drv_section('error-404-recovery', [
            'eyebrow' => 'Không có kết quả trực tiếp',
            'heading' => $raw_query !== ''
                ? sprintf('Không tìm thấy mẫu gạch khớp "%s"', $raw_query)
                : 'Chưa có từ khóa tìm kiếm',
            'message' => 'Hãy thử tìm bằng kích thước chuẩn (60x60, 80x80, 60x120), vân gạch (Marble, Terrazzo, Giả gỗ) hoặc gửi ảnh mẫu công trình qua Zalo để kỹ thuật viên tra kho ngay.',
            'is_h1'   => true,
        ]);
        ?>
    <?php endif; ?>
</main>
<?php
get_footer();

