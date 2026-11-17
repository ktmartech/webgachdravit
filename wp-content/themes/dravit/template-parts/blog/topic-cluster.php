<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Cụm chủ đề 3 bài (mockup `07-blog-archive.html` Sections 4-6): `.drv-topic-head` + `l-grid--3`.
 *
 * @var array<string, mixed> $args
 */
$cluster = isset($args['cluster']) && is_array($args['cluster']) ? $args['cluster'] : [];
$index   = isset($args['index']) ? (int) $args['index'] : 0;

$slug  = (string) ($cluster['slug'] ?? '');
$name  = (string) ($cluster['name'] ?? '');
$url   = (string) ($cluster['url'] ?? '');
$count = (int) ($cluster['count'] ?? 0);
$posts = isset($cluster['posts']) && is_array($cluster['posts']) ? $cluster['posts'] : [];

if ($name === '' || $posts === []) {
    return;
}

$surface = ($index % 2 === 1) ? 'l-section--ivory' : 'l-section--white';
?>
<section id="<?php echo esc_attr($slug); ?>" class="l-section <?php echo esc_attr($surface); ?> u-bd-b" style="padding-block: var(--s-5)">
    <div class="l-container">
        <header class="drv-topic-head">
            <h2><?php echo esc_html($name); ?></h2>
            <?php if ($url !== '') : ?>
                <a href="<?php echo esc_url($url); ?>" class="drv-more-btn">
                    <span><?php echo esc_html(sprintf('Xem tất cả %d bài viết', $count)); ?></span>
                    <i class="ph-bold ph-caret-right drv-icon--xs" aria-hidden="true"></i>
                </a>
            <?php endif; ?>
        </header>

        <div class="l-grid l-grid--3" data-drv-stagger>
            <?php foreach ($posts as $post_card) : ?>
                <?php get_template_part('template-parts/blog/card', null, ['card' => $post_card, 'heading_tag' => 'h3']); ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
