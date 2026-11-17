<?php
declare(strict_types=1);

use Dravit\Content\BlogSetup;

defined('ABSPATH') || exit;

get_header();

$paged    = max(1, (int) get_query_var('paged', 1));
$is_paged = ($paged >= 2);
?>
<main id="main-content" class="drv-blog-hub">
    <?php
    get_template_part('template-parts/blog/hero', null, [
        'title'      => $is_paged ? sprintf('Cẩm nang chọn gạch & kỹ thuật ốp lát - Trang %d', $paged) : 'Cẩm nang chọn gạch & kỹ thuật ốp lát',
        'tools_link' => !$is_paged,
    ]);
    ?>

    <?php get_template_part('template-parts/blog/subnav', null, ['active_slug' => '']); ?>

    <?php if (!$is_paged) : ?>
        <?php $vm = BlogSetup::getArchiveViewModel(); ?>

        <?php if ($vm['pillar'] !== null) : ?>
            <?php
            get_template_part('template-parts/blog/bento-hero', null, [
                'pillar'  => $vm['pillar'],
                'recent'  => $vm['recent'],
                'popular' => $vm['popular'],
            ]);
            ?>
        <?php endif; ?>

        <?php if ($vm['clusters'] !== []) : ?>
            <?php foreach ($vm['clusters'] as $c_idx => $cluster) : ?>
                <?php
                get_template_part('template-parts/blog/topic-cluster', null, [
                    'cluster' => $cluster,
                    'index'   => $c_idx,
                ]);
                ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php get_template_part('template-parts/blog/tools-strip', null, ['r_rows' => $vm['r_rows']]); ?>

        <?php if ($vm['clusters'] === [] && have_posts()) : ?>

            <section class="l-section">
                <div class="l-container l-stack l-stack--lg">
                    <div class="l-grid l-grid--3">
                        <?php
                        while (have_posts()) :
                            the_post();
                            $p_obj = get_post();
                            if (!$p_obj instanceof \WP_Post) {
                                continue;
                            }
                            get_template_part('template-parts/blog/card', null, [
                                'card'        => BlogSetup::mapPostCard($p_obj),
                                'heading_tag' => 'h2',
                            ]);
                        endwhile;
                        ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    <?php else : ?>
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
                    <p class="u-text-muted">Hiện chưa có thêm bài viết ở trang này.</p>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php get_template_part('template-parts/blog/cro-strip'); ?>
</main>
<?php
get_footer();
