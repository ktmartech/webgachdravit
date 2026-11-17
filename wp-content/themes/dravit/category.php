<?php
declare(strict_types=1);

use Dravit\Content\BlogSetup;

defined('ABSPATH') || exit;

get_header();

$term        = get_queried_object();
$cat_slug    = ($term instanceof \WP_Term) ? $term->slug : '';
$cat_title   = ($term instanceof \WP_Term) ? $term->name : (string) single_cat_title('', false);

global $wp_query;
$found_count = ($wp_query instanceof \WP_Query) ? (int) $wp_query->found_posts : 0;
$paged       = max(1, (int) get_query_var('paged', 1));
$silo_tiles  = BlogSetup::getRelatedSiloTiles($cat_slug);
?>
<main id="main-content" class="drv-blog-archive">
    <?php
    get_template_part('template-parts/blog/hero', null, [
        'title' => $paged >= 2 ? sprintf('%s - Trang %d', $cat_title, $paged) : $cat_title,
    ]);
    ?>

    <?php get_template_part('template-parts/blog/subnav', null, ['active_slug' => $cat_slug]); ?>

    <section class="l-section">
        <div class="l-container l-stack l-stack--lg">
            <?php if (have_posts()) : ?>
                <div class="l-grid l-grid--3">
                    <?php
                    $idx = 0;
                    while (have_posts()) :
                        the_post();
                        $p_obj = get_post();
                        if (!$p_obj instanceof \WP_Post) {
                            continue;
                        }
                        get_template_part('template-parts/blog/card', null, [
                            'card'          => BlogSetup::mapPostCard($p_obj),
                            'heading_tag'   => 'h2',
                            'priority_load' => ($idx === 0),
                        ]);
                        $idx++;
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
            <?php else : ?>
                <div class="drv-blog-empty">
                    <p class="u-text-muted">Chuyên mục này đang cập nhật bài viết kỹ thuật mới. Bạn có thể tham khảo các danh mục gạch tương ứng bên dưới.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($silo_tiles !== []) : ?>
        <section class="l-section drv-blog-silos-sec" aria-label="Danh mục gạch ốp lát liên quan">
            <div class="l-container l-stack l-stack--md">
                <header class="drv-sec-head">
                    <div class="l-stack l-stack--xs">
                        <span class="drv-sec-head__eyebrow">Tra cứu mẫu gạch thực tế</span>
                        <h2>Danh mục gạch thuộc chủ đề <?php echo esc_html($cat_title); ?></h2>
                    </div>
                </header>
                <div class="l-grid l-grid--auto-sm">
                    <?php foreach ($silo_tiles as $tile) : ?>
                        <?php drv_ui('sub-silo-tile', $tile); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php get_template_part('template-parts/blog/cro-strip'); ?>
</main>
<?php
get_footer();
