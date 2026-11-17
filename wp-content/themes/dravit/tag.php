<?php
declare(strict_types=1);

use Dravit\Content\BlogSetup;

defined('ABSPATH') || exit;

get_header();

$term        = get_queried_object();
$tag_title   = ($term instanceof \WP_Term) ? $term->name : (string) single_tag_title('', false);
$tag_desc    = ($term instanceof \WP_Term) ? trim(wp_strip_all_tags(term_description($term))) : '';

global $wp_query;
$found_count = ($wp_query instanceof \WP_Query) ? (int) $wp_query->found_posts : 0;
?>
<main id="main-content" class="drv-blog-archive">
    <?php
    get_template_part('template-parts/blog/hero', null, [
        'title'      => 'Bài viết gắn thẻ: ' . $tag_title,
        'tools_link' => false,
    ]);
    ?>

    <?php get_template_part('template-parts/blog/subnav', null, ['active_slug' => '']); ?>

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
                <p class="u-text-muted">Chưa có bài viết nào thuộc thẻ này.</p>
            <?php endif; ?>
        </div>
    </section>

    <?php get_template_part('template-parts/blog/cro-strip'); ?>
</main>
<?php
get_footer();
