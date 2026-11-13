<?php
declare(strict_types=1);

use Dravit\Home\HomeData;

defined('ABSPATH') || exit;

/**
 * Section: Space Bento Grid (`01-home.html`, `S-3`, `docs/03 §3.1`, `docs/07 §3.3`).
 * Trình diễn đầy đủ 8 không gian `pa_khong-gian`, 100% trỏ về URL tĩnh Smart Collection (0 `?param`, 0 dấu `/` cuối).
 *
 * @var array<string, mixed> $args
 */

$eyebrow   = trim((string) ($args['eyebrow'] ?? 'Chọn theo không gian'));
$title     = trim((string) ($args['title'] ?? 'Bạn Đang Tìm Gạch Cho Khu Vực Nào?'));
$desc      = trim((string) ($args['description'] ?? 'Mỗi không gian cần chuẩn độ nhám R và xương gạch khác nhau — chọn đúng ngay từ đầu.'));
$cta_label = trim((string) ($args['cta_label'] ?? 'Xem tất cả 28 danh mục'));
$cta_path  = trim((string) ($args['cta_path'] ?? '/gach'));
$surface   = (string) ($args['surface'] ?? 'ivory');

$sec_cls = 'l-section';
if ($surface === 'white') {
    $sec_cls .= ' l-section--white';
} elseif ($surface === 'champagne') {
    $sec_cls .= ' l-section--champagne';
}

$spaces = HomeData::getSpaceBentoItems();
?>
<section class="<?php echo esc_attr($sec_cls); ?>" id="home-spaces">
    <div class="l-container">
        <?php
        drv_ui('section-head', [
            'eyebrow'      => $eyebrow,
            'title'        => $title,
            'desc'         => $desc,
            'heading_tag'  => 'h2',
            'action_label' => $cta_label,
            'action_url'   => HomeData::siteUrl($cta_path),
            'action_style' => 'more-btn',
            'reveal'       => 'fade-up',
        ]);
        ?>

        <div class="drv-space-bento" data-drv-stagger="60">
            <?php foreach ($spaces as $idx => $space) : ?>
                <?php
                $card_cls = 'drv-space-card';
                if ($space['layout'] === 'featured') {
                    $card_cls .= ' drv-space-card--featured';
                } elseif ($space['layout'] === 'wide') {
                    $card_cls .= ' drv-space-card--wide';
                }
                $is_lcp_img = ($idx === 0);
                ?>
                <a href="<?php echo esc_url($space['url']); ?>" class="<?php echo esc_attr($card_cls); ?>">
                    <div class="drv-space-card__media">
                        <img
                            src="<?php echo esc_url($space['img_url']); ?>"
                            alt="<?php echo esc_attr($space['alt']); ?>"
                            width="<?php echo (int) $space['img_w']; ?>"
                            height="<?php echo (int) $space['img_h']; ?>"
                            <?php echo $is_lcp_img ? 'fetchpriority="high"' : 'loading="lazy"'; ?>
                            decoding="async"
                        >
                    </div>
                    <div class="drv-space-card__body">
                        <div class="l-cluster l-cluster--between">
                            <h3 class="drv-space-card__name"><?php echo esc_html($space['name']); ?></h3>
                            <span class="drv-space-card__count u-tabular"><?php echo (int) $space['count']; ?> mẫu</span>
                        </div>
                        <span class="drv-space-card__spec"><?php echo esc_html($space['spec']); ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
