<?php
declare(strict_types=1);

use Dravit\Home\HomeData;

defined('ABSPATH') || exit;

/**
 * Section: Editorial Guides (`template-parts/sections/editorial-guides.php`, `S-3`, `docs/07 §3.8`, `docs/20`).
 * Bố cục tạp chí `8/4`: 1 bài Pillar chủ đạo + 3 bài chuyên sâu + Cột phải `01-05` đánh số bằng font `Anton`.
 *
 * @var array<string, mixed> $args
 */

$eyebrow   = trim((string) ($args['eyebrow'] ?? '06 · Cẩm nang chọn gạch & thi công'));
$title     = trim((string) ($args['title'] ?? 'Kiến thức thực tế từ kỹ sư vật liệu'));
$desc      = trim((string) ($args['description'] ?? ''));
$cta_label = trim((string) ($args['cta_label'] ?? 'Xem tất cả bài viết'));
$cta_path  = trim((string) ($args['cta_path'] ?? '/cam-nang'));
$limit     = max(1, min(6, (int) ($args['limit'] ?? 3)));
$surface   = (string) ($args['surface'] ?? 'ivory');

$sec_cls = 'l-section';
if ($surface === 'white') {
    $sec_cls .= ' l-section--white';
} elseif ($surface === 'champagne') {
    $sec_cls .= ' l-section--champagne';
}

$editorial  = HomeData::getEditorialGuides($limit);
$featured   = $editorial['featured'];
$posts      = $editorial['posts'];
$quick_list = $editorial['quick_list'];
$blog_url   = HomeData::siteUrl($cta_path);
?>
<section class="<?php echo esc_attr($sec_cls); ?>" id="home-guides">
    <div class="l-container">
        <?php
        drv_ui('section-head', [
            'eyebrow'      => $eyebrow,
            'title'        => $title,
            'desc'         => $desc,
            'heading_tag'  => 'h2',
            'action_label' => $cta_label,
            'action_url'   => $blog_url,
            'reveal'       => 'fade-up',
        ]);
        ?>

        <div class="l-split l-split--8-4 drv-editorial">
            <div class="drv-editorial__main">
                <article class="drv-post-card drv-post-card--feature" data-drv-reveal="fade-up">
                    <a href="<?php echo esc_url($featured['url']); ?>" class="drv-post-card__media" tabindex="-1" aria-hidden="true">
                        <img
                            src="<?php echo esc_url($featured['img_url']); ?>"
                            alt="<?php echo esc_attr($featured['img_alt']); ?>"
                            width="800"
                            height="450"
                            loading="lazy"
                            decoding="async"
                        >
                    </a>
                    <div class="drv-post-card__body">
                        <div class="drv-post-card__kicker">
                            <?php drv_ui('badge', ['text' => $featured['category'], 'variant' => 'teal']); ?>
                            <span class="drv-post-card__meta"><?php echo esc_html($featured['meta']); ?></span>
                        </div>
                        <h3 class="drv-post-card__title">
                            <a href="<?php echo esc_url($featured['url']); ?>" class="drv-post-card__link">
                                <?php echo esc_html($featured['title']); ?>
                            </a>
                        </h3>
                        <p class="drv-post-card__excerpt"><?php echo esc_html($featured['excerpt']); ?></p>
                    </div>
                </article>

                <div class="l-grid l-grid--3 drv-editorial__subgrid" data-drv-stagger="65">
                    <?php foreach ($posts as $post_card) : ?>
                        <article class="drv-post-card">
                            <a href="<?php echo esc_url($post_card['url']); ?>" class="drv-post-card__media" tabindex="-1" aria-hidden="true">
                                <img
                                    src="<?php echo esc_url($post_card['img_url']); ?>"
                                    alt="<?php echo esc_attr($post_card['img_alt']); ?>"
                                    width="400"
                                    height="250"
                                    loading="lazy"
                                    decoding="async"
                                >
                            </a>
                            <div class="drv-post-card__body">
                                <span class="drv-post-card__cat"><?php echo esc_html($post_card['category']); ?></span>
                                <h3 class="drv-post-card__title">
                                    <a href="<?php echo esc_url($post_card['url']); ?>" class="drv-post-card__link">
                                        <?php echo esc_html($post_card['title']); ?>
                                    </a>
                                </h3>
                                <span class="drv-post-card__meta"><?php echo esc_html($post_card['meta']); ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <aside class="drv-numbered-list" aria-labelledby="home-quick-guide-title" data-drv-reveal="fade-up">
                <div class="drv-numbered-list__head">
                    <span class="drv-numbered-list__eyebrow"> Sổ tay công trình</span>
                    <h3 id="home-quick-guide-title" class="drv-numbered-list__title">5 câu hỏi thường gặp khi chọn &amp; lát gạch</h3>
                </div>

                <ol class="drv-numbered-list__ol">
                    <?php foreach ($quick_list as $q_item) : ?>
                        <li class="drv-numbered-list__item">
                            <span class="drv-numbered-list__num" aria-hidden="true"><?php echo esc_html($q_item['num']); ?></span>
                            <div class="drv-numbered-list__content">
                                <a href="<?php echo esc_url($q_item['url']); ?>" class="drv-numbered-list__link">
                                    <?php echo esc_html($q_item['title']); ?>
                                </a>
                                <span class="drv-numbered-list__meta"><?php echo esc_html($q_item['meta']); ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>

                <div class="drv-numbered-list__foot">
                    <a href="<?php echo esc_url($blog_url); ?>" class="drv-btn drv-btn--soft drv-btn--sm drv-btn--full">
                        <?php drv_ui('icon', ['name' => 'book-open-text', 'size' => 'sm']); ?>
                        <span>Đọc trọn bộ Cẩm nang kỹ thuật</span>
                    </a>
                </div>
            </aside>
        </div>
    </div>
</section>

