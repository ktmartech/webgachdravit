<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

get_header();
?>
<div class="l-container l-section">
    <?php while (have_posts()) : the_post(); ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class('drv-page'); ?>>
            <h1 class="drv-page__title"><?php the_title(); ?></h1>
            <div class="drv-page__content">
                <?php the_content(); ?>
            </div>
        </article>
    <?php endwhile; ?>
</div>
<?php
get_footer();

