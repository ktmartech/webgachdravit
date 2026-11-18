<?php
/**
 * Template Name: Trang Giới thiệu
 */
declare(strict_types=1);

use Dravit\Content\AuthorProfile;
use Dravit\Core\Config;
use Dravit\Core\SectionRegistry;
use Dravit\Home\HomeData;

defined('ABSPATH') || exit;

get_header();

$brand_name = (string) drv_setting('company.brand_name', 'DRAVIT');
$legal_name = (string) drv_setting('company.legal_name', '');
$tagline    = (string) drv_setting('company.tagline', 'Thẩm mỹ từ những đường nét nhỏ nhất.');
$experts    = AuthorProfile::getPublicExperts();
$org_team   = AuthorProfile::organizationFallback();
$gach_url   = HomeData::siteUrl('/gach');
$boq_url    = HomeData::siteUrl('/danh-sach-bao-gia');
?>
<main id="main-content" class="drv-about-page">
    <header class="drv-about-hero">
        <div class="l-container l-stack l-stack--md">
            <?php drv_ui('breadcrumb'); ?>

            <div class="drv-about-hero__grid">
                <div class="l-stack l-stack--sm">
                    <span class="drv-sec-head__eyebrow">
                        <?php drv_ui('icon', ['name' => 'shield-check', 'size' => 'sm']); ?>
                        <span><?php echo esc_html($legal_name); ?></span>
                    </span>
                    <h1 class="drv-about-hero__title">Vật liệu chuẩn xác từ đường mạch ron đầu tiên</h1>
                    <p class="drv-about-hero__lead">
                        <?php echo esc_html($tagline . ' DRAVIT cung ứng gạch Porcelain xương đá và đá nung kết khổ lớn với thông số kỹ thuật minh bạch, niêm yết giá song song theo m² và viên, kiểm soát đồng bộ mã màu (Shade) cùng kích cỡ (Caliber) trước khi xuất kho.'); ?>
                    </p>
                    <div class="l-cluster l-cluster--sm">
                        <a href="<?php echo esc_url($gach_url); ?>" class="drv-btn drv-btn--primary">
                            <?php drv_ui('icon', ['name' => 'squares-four', 'size' => 'sm']); ?>
                            <span>Khám phá 28 danh mục gạch</span>
                        </a>
                        <a href="<?php echo esc_url($boq_url); ?>" class="drv-btn drv-btn--outline">
                            <?php drv_ui('icon', ['name' => 'clipboard-text', 'size' => 'sm']); ?>
                            <span>Lập bảng dự toán BOQ</span>
                        </a>
                    </div>
                </div>

                <div class="drv-about-stats">
                    <div class="drv-about-stat">
                        <span class="drv-about-stat__num u-tabular">&le; 0,5%</span>
                        <span class="drv-about-stat__label">Độ hút nước xương Porcelain nhóm BIa (ISO 13006)</span>
                    </div>
                    <div class="drv-about-stat">
                        <span class="drv-about-stat__num u-tabular">R9 - R13</span>
                        <span class="drv-about-stat__label">Phân cấp chống trượt rõ ràng theo từng không gian ướt / khô</span>
                    </div>
                    <div class="drv-about-stat">
                        <span class="drv-about-stat__num u-tabular">100%</span>
                        <span class="drv-about-stat__label">Đối chiếu đồng bộ mã lô (Lot), mã màu (Shade) &amp; cỡ (Caliber)</span>
                    </div>
                    <div class="drv-about-stat">
                        <span class="drv-about-stat__num u-tabular">3000K - 6500K</span>
                        <span class="drv-about-stat__label">Hệ đèn thử màu thực tế tại Showroom <?php echo esc_html($brand_name); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <?php
    while (have_posts()) :
        the_post();
        $raw_page_body = trim((string) get_the_content());
        if ($raw_page_body !== '') :
            ?>
            <section class="l-section l-section--white">
                <div class="l-container">
                    <div class="drv-prose drv-about-prose">
                        <?php the_content(); ?>
                    </div>
                </div>
            </section>
            <?php
        endif;
    endwhile;
    ?>

    <?php SectionRegistry::render_stack(Config::get('pages/about')); ?>

    <section class="l-section drv-about-team-sec" id="doi-ngu" aria-labelledby="drv-team-heading">
        <div class="l-container l-stack l-stack--lg">
            <header class="drv-sec-head">
                <div class="l-stack l-stack--xs">
                    <span class="drv-sec-head__eyebrow">
                        <?php drv_ui('icon', ['name' => 'users', 'size' => 'sm']); ?>
                        <span>Hội đồng biên soạn &amp; Kiểm định kỹ thuật</span>
                    </span>
                    <h2 id="drv-team-heading">Đội ngũ Kỹ thuật &amp; Kiểm soát Chất lượng <?php echo esc_html($brand_name); ?></h2>
                    <p class="drv-sec-head__desc">
                        Mọi thông số trong Cẩm nang Chọn gạch và bảng quy đổi định mức đều được đối chiếu theo tiêu chuẩn TCVN 7745, ISO 13006 và tài liệu kỹ thuật nhà máy.
                    </p>
                </div>
            </header>

            <div class="drv-team-grid">
                <article class="drv-team-card drv-team-card--org">
                    <div class="drv-team-card__avatar">
                        <img src="<?php echo esc_url((string) $org_team['avatar_url']); ?>"
                             alt="<?php echo esc_attr((string) $org_team['name']); ?>"
                             width="64"
                             height="64"
                             loading="lazy"
                             decoding="async">
                    </div>
                    <div class="drv-team-card__body">
                        <span class="drv-author-box__eyebrow">Bộ phận chuyên trách</span>
                        <h3 class="drv-team-card__name"><?php echo esc_html((string) $org_team['name']); ?></h3>
                        <p class="drv-team-card__role"><?php echo esc_html((string) $org_team['job_title']); ?></p>
                        <p class="drv-team-card__bio"><?php echo esc_html((string) $org_team['credentials']); ?></p>
                    </div>
                </article>

                <?php foreach ($experts as $expert) : ?>
                    <article id="person-<?php echo esc_attr((string) $expert['id']); ?>" class="drv-team-card">
                        <div class="drv-team-card__avatar">
                            <img src="<?php echo esc_url((string) $expert['avatar_url']); ?>"
                                 alt="<?php echo esc_attr((string) $expert['name']); ?>"
                                 width="72"
                                 height="72"
                                 loading="lazy"
                                 decoding="async">
                        </div>
                        <div class="drv-team-card__body">
                            <span class="drv-author-box__eyebrow">
                                <?php drv_ui('icon', ['name' => 'seal-check', 'size' => 'sm']); ?>
                                <span>Chuyên gia xác thực</span>
                            </span>
                            <h3 class="drv-team-card__name"><?php echo esc_html((string) $expert['name']); ?></h3>
                            <p class="drv-team-card__role"><?php echo esc_html((string) $expert['job_title']); ?></p>
                            <p class="drv-team-card__bio"><?php echo esc_html((string) $expert['credentials']); ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</main>
<?php
get_footer();
