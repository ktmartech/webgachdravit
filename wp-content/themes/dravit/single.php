<?php
declare(strict_types=1);

use Dravit\Content\AuthorProfile;
use Dravit\Content\BlogSetup;
use Dravit\Content\PostMeta;
use Dravit\Content\ReadingTime;
use Dravit\Content\Toc;
use Dravit\Home\HomeData;
use Dravit\Seo\UrlNormalizer;

defined('ABSPATH') || exit;

get_header();
?>
<div class="drv-read-progress" data-drv-read-progress aria-hidden="true"></div>

<main id="main-content" class="drv-single-post">
    <?php
    while (have_posts()) :
        the_post();
        $post_obj = get_post();
        if (!$post_obj instanceof \WP_Post) {
            continue;
        }

        $post_id     = (int) $post_obj->ID;
        $raw_content = (string) $post_obj->post_content;

        // Render nội dung 1 lần duy nhất để đồng bộ `Toc::getCurrentItems()` cho cả Sidebar và Mobile Drawer.
        Toc::resetCurrentItems();
        ob_start();
        the_content();
        $rendered_content = (string) ob_get_clean();
        $toc_items        = Toc::getCurrentItems();

        $permalink     = UrlNormalizer::untrailingslashUrl((string) get_permalink($post_id));
        $reading_label = ReadingTime::formatLabel(ReadingTime::forPost($post_obj));
        $author        = AuthorProfile::resolvePostAuthor($post_obj);
        $reviewer      = AuthorProfile::resolvePostReviewer($post_obj);
        $updated_disp  = (string) get_the_modified_date('d/m/Y', $post_id);
        $updated_iso   = (string) get_the_modified_date('Y-m-d', $post_id);
        $lead          = trim((string) $post_obj->post_excerpt);

        $cat_slug = 'ky-thuat-op-lat';
        $cats     = get_the_category($post_id);
        if (is_array($cats) && isset($cats[0]) && $cats[0] instanceof \WP_Term && $cats[0]->slug !== 'uncategorized') {
            $cat_slug = $cats[0]->slug;
        }

        $takeaways          = PostMeta::getKeyTakeaways($post_id);
        $has_inline_summary = str_contains($raw_content, '[drv_takeaways') || str_contains($rendered_content, 'drv-key-takeaways');

        $tags          = get_the_tags($post_id);
        $prev_post     = get_previous_post();
        $next_post     = get_next_post();
        $related_posts = BlogSetup::getRelatedArticles($post_id, 3);
        $related_silos = BlogSetup::getRelatedSiloTiles($cat_slug, PostMeta::getRelatedCollectionSlugs($post_id));
        ?>
        <div class="l-container drv-single-post__crumb">
            <?php drv_ui('breadcrumb'); ?>
        </div>

        <section class="l-section" style="padding-top: var(--s-4); padding-bottom: var(--s-6);">
            <div class="l-container">
                <div class="drv-blog-single-layout">
                    <article id="post-<?php echo esc_attr((string) $post_id); ?>" <?php post_class('drv-article-main'); ?> data-drv-article>
                        <header class="drv-article-header">
                            <h1 class="drv-article-header__title"><?php the_title(); ?></h1>

                            <div class="drv-article-header__meta-row">
                                <div class="drv-article-header__meta-left">
                                    <span class="drv-article-meta-item">
                                        <i class="ph ph-user drv-icon--xs" aria-hidden="true"></i>
                                        <a href="#author-credentials"><?php echo esc_html((string) $author['name']); ?></a>
                                    </span>
                                    <span class="drv-meta-sep" aria-hidden="true"></span>
                                    <span class="drv-article-meta-item">
                                        <i class="ph ph-clock drv-icon--xs" aria-hidden="true"></i>
                                        <span><?php echo esc_html($reading_label); ?></span>
                                    </span>
                                    <span class="drv-meta-sep" aria-hidden="true"></span>
                                    <span class="drv-article-meta-item">
                                        <i class="ph ph-calendar-blank drv-icon--xs" aria-hidden="true"></i>
                                        <time datetime="<?php echo esc_attr($updated_iso); ?>"><?php echo esc_html($updated_disp); ?></time>
                                    </span>
                                </div>

                                <div class="drv-share" data-drv-share>
                                    <button type="button"
                                            class="drv-btn-share-ghost"
                                            data-drv-action="share-toggle"
                                            aria-haspopup="true"
                                            aria-expanded="false"
                                            aria-controls="drv-share-menu"
                                            title="Chia sẻ bài viết"
                                            aria-label="Chia sẻ bài viết">
                                        <i class="ph-bold ph-share-network" aria-hidden="true"></i>
                                    </button>
                                    <div class="drv-share__menu" id="drv-share-menu" role="menu" aria-label="Chia sẻ bài viết" hidden>
                                        <a role="menuitem"
                                           class="drv-share__item"
                                           href="<?php echo esc_url('https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($permalink)); ?>"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           data-drv-action="share-close">
                                            <i class="ph ph-facebook-logo drv-icon--sm" aria-hidden="true"></i>
                                            <span>Chia sẻ Facebook</span>
                                        </a>
                                        <button type="button"
                                                role="menuitem"
                                                class="drv-share__item"
                                                data-drv-action="share-zalo"
                                                data-share-url="<?php echo esc_attr($permalink); ?>">
                                            <i class="ph ph-chat-circle-dots drv-icon--sm" aria-hidden="true"></i>
                                            <span>Gửi qua Zalo</span>
                                        </button>
                                        <button type="button"
                                                role="menuitem"
                                                class="drv-share__item"
                                                data-drv-action="copy-text"
                                                data-copy-value="<?php echo esc_attr($permalink); ?>">
                                            <i class="ph ph-copy drv-icon--sm" aria-hidden="true"></i>
                                            <span>Sao chép liên kết</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <?php if ($lead !== '') : ?>
                                <p class="drv-article-header__lead"><?php echo esc_html($lead); ?></p>
                            <?php endif; ?>
                        </header>

                        <?php if ($takeaways !== [] && !$has_inline_summary) : ?>
                            <section class="drv-key-takeaways" aria-labelledby="key-takeaways-heading">
                                <div class="drv-key-takeaways__top">
                                    <div class="drv-key-takeaways__head" id="key-takeaways-heading">
                                        <i class="ph-fill ph-bookmarks-simple drv-icon" aria-hidden="true"></i>
                                        <span>Những Điểm Chính (Key Takeaways) — Tóm Tắt Cho KTS &amp; Chủ Nhà</span>
                                    </div>
                                </div>
                                <ul class="drv-key-takeaways__list">
                                    <?php foreach ($takeaways as $point) : ?>
                                        <li class="drv-key-takeaways__item">
                                            <i class="ph-bold ph-check-square drv-icon--xs" aria-hidden="true"></i>
                                            <div><?php echo esc_html($point); ?></div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        <?php endif; ?>

                        <div class="drv-prose" data-drv-prose data-pswp-gallery>
                            <?php
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Standard WP `the_content` filtered output
                            echo $rendered_content;
                            ?>

                            <?php if (is_array($tags) && $tags !== []) : ?>
                                <div class="drv-post-tags" aria-label="Thẻ chủ đề bài viết">
                                    <span class="u-text-xs u-bold u-text-muted" style="margin-right: 4px;">
                                        <i class="ph-fill ph-tag drv-icon--xs" aria-hidden="true"></i> Chủ đề:
                                    </span>
                                    <?php foreach ($tags as $tag_term) :
                                        if (!$tag_term instanceof \WP_Term) {
                                            continue;
                                        }
                                        $t_link = get_term_link($tag_term);
                                        if (!is_string($t_link) || $t_link === '') {
                                            continue;
                                        }
                                        ?>
                                        <a href="<?php echo esc_url(UrlNormalizer::untrailingslashUrl($t_link)); ?>" class="drv-post-tag">#<?php echo esc_html($tag_term->name); ?></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php
                            get_template_part('template-parts/blog/author-box', null, [
                                'author'   => $author,
                                'reviewer' => $reviewer,
                            ]);
                            ?>

                            <?php if ($prev_post instanceof \WP_Post || $next_post instanceof \WP_Post) : ?>
                                <nav class="drv-post-nav" aria-label="Điều hướng bài viết trước và tiếp theo">
                                    <?php if ($prev_post instanceof \WP_Post) : ?>
                                        <a href="<?php echo esc_url(UrlNormalizer::untrailingslashUrl((string) get_permalink($prev_post->ID))); ?>" class="drv-post-nav__link">
                                            <span class="drv-post-nav__label">
                                                <i class="ph-bold ph-arrow-left drv-icon--xs" aria-hidden="true"></i>
                                                <span>Bài viết trước</span>
                                            </span>
                                            <span class="drv-post-nav__title"><?php echo esc_html(get_the_title($prev_post->ID)); ?></span>
                                        </a>
                                    <?php else : ?>
                                        <span></span>
                                    <?php endif; ?>

                                    <?php if ($next_post instanceof \WP_Post) : ?>
                                        <a href="<?php echo esc_url(UrlNormalizer::untrailingslashUrl((string) get_permalink($next_post->ID))); ?>" class="drv-post-nav__link drv-post-nav__link--next">
                                            <span class="drv-post-nav__label">
                                                <span>Bài viết tiếp theo</span>
                                                <i class="ph-bold ph-arrow-right drv-icon--xs" aria-hidden="true"></i>
                                            </span>
                                            <span class="drv-post-nav__title"><?php echo esc_html(get_the_title($next_post->ID)); ?></span>
                                        </a>
                                    <?php endif; ?>
                                </nav>
                            <?php endif; ?>
                        </div>
                    </article>

                    <?php
                    get_template_part('template-parts/blog/sidebar', null, [
                        'toc_items' => $toc_items,
                        'post_id'   => $post_id,
                    ]);
                    ?>
                </div>
            </div>
        </section>

        <?php if ($related_posts !== [] || $related_silos !== []) : ?>
            <section class="l-section" style="padding-top: var(--s-5); padding-bottom: var(--s-7); background-color: var(--c-ivory); border-top: var(--bw);" aria-labelledby="related-articles-heading">
                <div class="l-container">
                    <?php if ($related_posts !== []) : ?>
                        <div class="drv-sec-head" style="margin-bottom: var(--s-4);">
                            <div class="l-stack l-stack--xs">
                                <span class="drv-sec-head__eyebrow">Cẩm nang kỹ thuật cùng chuyên đề</span>
                                <h2 id="related-articles-heading" style="font-size: 1.5rem; margin: 0; text-wrap: normal;">Bài viết liên quan cho công trình của bạn</h2>
                            </div>
                            <a href="<?php echo esc_url(HomeData::siteUrl('/cam-nang')); ?>" class="drv-more-btn">
                                <span>Xem toàn bộ Cẩm nang</span>
                                <i class="ph-bold ph-caret-right drv-icon--xs" aria-hidden="true"></i>
                            </a>
                        </div>

                        <div class="drv-related-posts-grid" data-drv-stagger>
                            <?php foreach ($related_posts as $rel) :
                                $silo = isset($rel['silo_pills'][0]) && is_array($rel['silo_pills'][0]) ? $rel['silo_pills'][0] : null;
                                ?>
                                <article class="drv-related-card">
                                    <a href="<?php echo esc_url((string) $rel['url']); ?>" class="drv-related-card__media" tabindex="-1" aria-hidden="true">
                                        <img src="<?php echo esc_url((string) $rel['img_url']); ?>" alt="<?php echo esc_attr((string) $rel['img_alt']); ?>" width="420" height="236" loading="lazy" decoding="async">
                                    </a>
                                    <div class="drv-related-card__body">
                                        <div class="l-cluster l-cluster--xs">
                                            <span class="drv-badge drv-badge--teal" style="font-size: 12px;"><?php echo esc_html((string) $rel['cat_name']); ?></span>
                                            <span class="u-text-xs u-text-muted"><i class="ph ph-clock drv-icon--xs" aria-hidden="true"></i> <?php echo esc_html((string) $rel['reading_label']); ?></span>
                                        </div>
                                        <h3 class="drv-related-card__title">
                                            <a href="<?php echo esc_url((string) $rel['url']); ?>"><?php echo esc_html((string) $rel['title']); ?></a>
                                        </h3>
                                        <?php if (!empty($rel['excerpt'])) : ?>
                                            <p class="u-text-xs u-text-muted" style="margin: 0; line-height: 1.5;"><?php echo esc_html((string) $rel['excerpt']); ?></p>
                                        <?php endif; ?>
                                        <div style="margin-top: auto; padding-top: var(--s-2); border-top: var(--bw); display: flex; align-items: center; justify-content: space-between;">
                                            <?php if ($silo !== null) : ?>
                                                <a href="<?php echo esc_url((string) ($silo['url'] ?? '')); ?>" class="u-text-xs u-bold u-text-moss" style="text-decoration: none;">
                                                    <i class="ph-fill ph-squares-four drv-icon--xs" aria-hidden="true"></i> <?php echo esc_html((string) ($silo['label'] ?? '')); ?>
                                                </a>
                                            <?php else : ?>
                                                <span></span>
                                            <?php endif; ?>
                                            <a href="<?php echo esc_url((string) $rel['url']); ?>" class="u-text-xs u-bold u-text-navy" style="text-decoration: none;">
                                                Đọc bài <i class="ph-bold ph-arrow-right drv-icon--xs" aria-hidden="true"></i>
                                            </a>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($related_silos !== []) : ?>
                        <div style="margin-top: var(--s-4); padding: var(--s-3) var(--s-4); background-color: var(--c-white); border: var(--bw); border-radius: var(--r-sm); display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--s-2);">
                            <span class="u-text-xs u-bold u-text-navy">
                                <i class="ph-fill ph-compass drv-icon--xs u-text-moss" aria-hidden="true"></i>
                                Khám phá nhanh các Danh mục Silo liên quan:
                            </span>
                            <div class="l-cluster l-cluster--xs">
                                <?php foreach ($related_silos as $tile) : ?>
                                    <a href="<?php echo esc_url((string) ($tile['url'] ?? '')); ?>" class="drv-post-tag"><?php echo esc_html((string) ($tile['name'] ?? '')); ?></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($toc_items !== []) : ?>
            <div class="drv-mobile-toc-fab drv-no-print">
                <button type="button" class="drv-btn drv-btn--navy drv-btn--sm" data-drv-action="dialog-open" data-drv-target="#drv-mobile-toc" aria-haspopup="dialog" aria-controls="drv-mobile-toc" style="flex: 1; justify-content: center;">
                    <i class="ph-bold ph-list-bullets drv-icon--xs" aria-hidden="true"></i>
                    <span>Mục lục (<span data-reading-progress-mob>0%</span>)</span>
                </button>
                <?php if ((bool) drv_setting('features.ai_enabled', true)) : ?>
                    <button type="button" class="drv-btn drv-btn--moss drv-btn--sm" data-drv-action="ai-finder-open" data-drv-prompt="<?php echo esc_attr('Tôi vừa đọc bài "' . get_the_title($post_id) . '", cần tư vấn chọn gạch và tính khối lượng cho công trình.'); ?>" style="flex: 1; justify-content: center;">
                        <i class="ph-fill ph-robot drv-icon--xs" aria-hidden="true"></i>
                        <span>Hỏi AI tư vấn</span>
                    </button>
                <?php endif; ?>
            </div>
            <?php get_template_part('template-parts/blog/toc', null, ['items' => $toc_items, 'mode' => 'mobile']); ?>
        <?php endif; ?>
    <?php endwhile; ?>
</main>
<?php
get_footer();
