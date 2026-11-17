<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Bento 12 cột `5 / 4 / 3` (mockup `07-blog-archive.html` Section 3): Pillar · 3 bài mới · Top 5 tra cứu.
 *
 * @var array<string, mixed> $args
 */
$pillar  = isset($args['pillar']) && is_array($args['pillar']) ? $args['pillar'] : null;
$recent  = isset($args['recent']) && is_array($args['recent']) ? $args['recent'] : [];
$popular = isset($args['popular']) && is_array($args['popular']) ? $args['popular'] : [];

if ($pillar === null) {
    return;
}
?>
<section class="l-section l-section--ivory u-bd-b" style="padding-block: var(--s-5)">
    <div class="l-container">
        <div class="drv-blog-bento" data-drv-stagger>
            <?php
            get_template_part('template-parts/blog/card', null, [
                'card'          => $pillar,
                'heading_tag'   => 'h2',
                'feature'       => true,
                'show_author'   => true,
                'priority_load' => true,
                'class'         => 'drv-blog-bento__feature',
            ]);
            ?>

            <div class="drv-blog-bento__mid">
                <?php foreach ($recent as $item) : ?>
                    <?php get_template_part('template-parts/blog/card-compact', null, ['card' => $item, 'heading_tag' => 'h3']); ?>
                <?php endforeach; ?>
            </div>

            <aside class="drv-blog-bento__right" aria-label="Chủ đề được tra cứu nhiều nhất">
                <div class="drv-blog-bento__right-head">
                    <h3 class="drv-blog-bento__right-title">Chủ đề được tra cứu nhiều</h3>
                    <i class="ph-bold ph-bookmarks u-text-moss drv-icon--sm" aria-hidden="true"></i>
                </div>
                <?php if ($popular !== []) : ?>
                    <ol class="drv-ranked-list">
                        <?php foreach ($popular as $pop) : ?>
                            <li class="drv-ranked-item">
                                <span class="drv-ranked-num" aria-hidden="true"><?php echo esc_html((string) ($pop['num'] ?? '01')); ?></span>
                                <div class="drv-ranked-content">
                                    <h4 class="drv-ranked-title">
                                        <a href="<?php echo esc_url((string) $pop['url']); ?>" class="drv-ranked-link"><?php echo esc_html((string) $pop['title']); ?></a>
                                    </h4>
                                    <span class="u-text-xs u-text-muted"><?php echo esc_html((string) $pop['cat_name'] . ' · ' . (string) $pop['reading_label']); ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>
