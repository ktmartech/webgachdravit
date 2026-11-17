<?php
/**
 * Template Name: Trang Chính sách
 */
declare(strict_types=1);

use Dravit\Content\BlogSetup;
use Dravit\Content\Toc;

defined('ABSPATH') || exit;

get_header();
?>
<div class="drv-read-progress" data-drv-read-progress aria-hidden="true"></div>

<main id="main-content" class="drv-policy-page">
    <?php
    while (have_posts()) :
        the_post();
        $page_obj = get_post();
        if (!$page_obj instanceof \WP_Post) {
            continue;
        }

        $page_id   = (int) $page_obj->ID;
        $page_slug = (string) $page_obj->post_name;

        Toc::resetCurrentItems();
        ob_start();
        the_content();
        $rendered_content = (string) ob_get_clean();
        $toc_items        = Toc::getCurrentItems();

        $is_guide       = isset(BlogSetup::GUIDE_PAGES[$page_slug]);
        $policy_nav     = BlogSetup::getPolicyNavItems($page_id, $page_slug);
        $effective_date = trim((string) get_post_meta($page_id, 'drv_effective_date', true));
        if ($effective_date === '') {
            $effective_date = (string) get_the_modified_date('d/m/Y', $page_id);
        }

        $legal_name = (string) drv_setting('company.legal_name', 'Công ty TNHH Vật Liệu Kiến Trúc DRAVIT');
        $tax_id     = (string) drv_setting('company.tax_id', '');
        $comp_addr  = (string) drv_setting('company.address', '');
        $hotline    = (string) drv_setting('contact.hotline', '0909 888 777');
        $email      = (string) drv_setting('contact.email', 'cskh@gachdravit.vn');
        ?>
        <div class="l-container drv-single-post__crumb">
            <?php drv_ui('breadcrumb'); ?>
        </div>

        <div class="l-container drv-policy-layout">
            <aside class="drv-policy-sidebar" aria-label="Danh mục trang và mục lục">
                <div class="drv-article-sidebar__sticky">
                    <nav class="drv-policy-nav" aria-label="Các chính sách tại DRAVIT">
                        <div class="drv-policy-nav__head">
                            <?php drv_ui('icon', ['name' => 'shield-check', 'size' => 'sm']); ?>
                            <span><?php echo esc_html($is_guide ? 'Hướng dẫn & Hợp tác' : 'Hệ thống Chính sách'); ?></span>
                        </div>
                        <ul class="drv-policy-nav__list">
                            <?php foreach ($policy_nav as $nav_item) : ?>
                                <li>
                                    <a href="<?php echo esc_url($nav_item['url']); ?>"
                                       class="drv-policy-nav__link<?php echo $nav_item['active'] ? ' is-active' : ''; ?>"
                                       <?php echo $nav_item['active'] ? 'aria-current="page"' : ''; ?>>
                                        <?php drv_ui('icon', ['name' => $nav_item['icon'], 'size' => 'sm']); ?>
                                        <span><?php echo esc_html($nav_item['title']); ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>

                    <?php if ($toc_items !== []) : ?>
                        <?php
                        get_template_part('template-parts/blog/toc', null, [
                            'items' => $toc_items,
                            'mode'  => 'desktop',
                            'title' => 'Nội dung văn bản',
                        ]);
                        ?>
                    <?php endif; ?>
                </div>
            </aside>

            <article id="post-<?php echo esc_attr((string) $page_id); ?>" <?php post_class('drv-policy-main'); ?> data-drv-article>
                <header class="drv-policy-head">
                    <span class="drv-sec-head__eyebrow"><?php echo esc_html($is_guide ? 'Hướng dẫn & Hợp tác' : 'Văn bản chính sách & Cam kết dịch vụ'); ?></span>
                    <h1 class="drv-policy-head__title"><?php the_title(); ?></h1>
                    <p class="drv-policy-head__meta u-tabular">
                        <?php drv_ui('icon', ['name' => 'calendar-blank', 'size' => 'sm']); ?>
                        <span><?php echo esc_html(($is_guide ? 'Cập nhật: ' : 'Áp dụng từ: ') . $effective_date); ?></span>
                        <span aria-hidden="true">&middot;</span>
                        <span>Pháp nhân: <?php echo esc_html($legal_name); ?></span>
                    </p>
                </header>

                <div class="drv-prose drv-policy-prose" data-drv-prose>
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Standard WP `the_content` filtered output
                    echo $rendered_content;
                    ?>
                </div>

                <footer class="drv-policy-legal-box" aria-label="Thông tin pháp nhân và hỗ trợ khách hàng">
                    <div class="drv-policy-legal-box__head">
                        <?php drv_ui('icon', ['name' => 'shield-check', 'size' => 'sm']); ?>
                        <strong>Thông tin pháp nhân &amp; đầu mối tiếp nhận phản hồi</strong>
                    </div>
                    <dl class="drv-policy-legal-box__dl">
                        <div>
                            <dt>Đơn vị chủ quản</dt>
                            <dd><?php echo esc_html($legal_name . ($tax_id !== '' ? ' (MST: ' . $tax_id . ')' : '')); ?></dd>
                        </div>
                        <?php if ($comp_addr !== '') : ?>
                            <div>
                                <dt>Trụ sở giao dịch</dt>
                                <dd><?php echo esc_html($comp_addr); ?></dd>
                            </div>
                        <?php endif; ?>
                        <div>
                            <dt>Hotline &amp; Email CSKH</dt>
                            <dd class="u-tabular">
                                <a href="<?php echo esc_url('tel:' . drv_clean_phone($hotline)); ?>"><?php echo esc_html($hotline); ?></a>
                                <span>&middot;</span>
                                <a href="<?php echo esc_url('mailto:' . $email); ?>"><?php echo esc_html($email); ?></a>
                            </dd>
                        </div>
                    </dl>
                </footer>
            </article>
        </div>
    <?php endwhile; ?>
</main>
<?php
get_footer();
