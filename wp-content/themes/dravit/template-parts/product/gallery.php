<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Gallery 6 vai trò ảnh kiến trúc (`drv_img_role`) + Tải Map CAD/4K (`drv_map_file_id`) + PhotoSwipe 5 trigger (`D-28`, `P-6`).
 * Đúng 1 ảnh LCP duy nhất (`fetchpriority="high"`, không `loading="lazy"`).
 *
 * @var array<string, mixed> $args
 */
$view    = isset($args['view']) && is_array($args['view']) ? $args['view'] : [];
$gallery = isset($view['gallery']) && is_array($view['gallery']) ? $view['gallery'] : [];
$sku     = trim((string) ($view['sku'] ?? ''));
$title   = trim((string) ($view['title'] ?? 'Mẫu gạch DRAVIT'));
$map_url = trim((string) ($view['map_url'] ?? ''));

if ($gallery === []) {
    return;
}

$total_images  = count($gallery);
$is_single_img = $total_images === 1;
$has_more_mob  = $total_images > 4;
$remaining_mob = $total_images - 3;
?>
<div class="drv-pdp-gallery<?php echo $is_single_img ? ' drv-pdp-gallery--single' : ''; ?><?php echo $has_more_mob ? ' drv-pdp-gallery--has-more' : ''; ?>"
     data-pswp-gallery="pdp-main"
     data-pswp-title="<?php echo esc_attr('Chi tiết thực tế ' . $title . ($sku !== '' ? ' (Mã ' . $sku . ')' : '')); ?>"
     <?php echo $map_url !== '' ? 'data-pswp-map-url="' . esc_url($map_url) . '"' : ''; ?>
     aria-label="<?php echo esc_attr('Bộ ảnh thực tế và chi tiết kỹ thuật ' . ($sku !== '' ? $sku : $title)); ?>">
    <?php foreach ($gallery as $idx => $item) :
        if (!is_array($item)) {
            continue;
        }
        $is_lcp     = !empty($item['is_lcp']);
        $is_wide    = !empty($item['is_wide']);
        $src_large  = (string) ($item['src_large'] ?? '');
        $src_full   = (string) ($item['src_full'] ?? $src_large);
        $width      = max(400, (int) ($item['width'] ?? 1200));
        $height     = max(400, (int) ($item['height'] ?? 1200));
        $alt        = (string) ($item['alt'] ?? $title);
        $role_label = trim((string) ($item['role_label'] ?? ''));
        $caption    = (string) ($item['caption'] ?? $alt);
        $show_map   = ($map_url !== '' && ($idx === 1 || ($is_single_img && $idx === 0)));

        $fig_cls = 'drv-pdp-gallery__item';
        if ($is_wide) {
            $fig_cls .= ' drv-pdp-gallery__item--wide';
        }
        if ($has_more_mob && $idx === 3) {
            $fig_cls .= ' drv-pdp-gallery__item--more-trigger';
        }
        ?>
        <figure class="<?php echo esc_attr($fig_cls); ?>">
            <a href="<?php echo esc_url($src_full); ?>"
               class="drv-pdp-gallery__zoom-link"
               data-pswp-item
               data-pswp-index="<?php echo esc_attr((string) $idx); ?>"
               data-pswp-width="<?php echo esc_attr((string) $width); ?>"
               data-pswp-height="<?php echo esc_attr((string) $height); ?>"
               data-pswp-caption="<?php echo esc_attr($caption); ?>"
               aria-label="<?php echo esc_attr('Phóng to ảnh ' . ($idx + 1) . ': ' . ($role_label !== '' ? $role_label : $alt)); ?>">
                <img src="<?php echo esc_url($src_large); ?>"
                     width="<?php echo esc_attr((string) ($is_wide ? 640 : 560)); ?>"
                     height="<?php echo esc_attr((string) ($is_wide ? 360 : 560)); ?>"
                     <?php echo $is_lcp ? 'fetchpriority="high"' : 'loading="lazy"'; ?>
                     decoding="async"
                     alt="<?php echo esc_attr($alt); ?>">
            </a>

            <?php if ($show_map) : ?>
                <a href="<?php echo esc_url($map_url); ?>"
                   class="drv-pdp-gallery__map-pill"
                   download
                   aria-label="<?php echo esc_attr('Tải file Map gạch CAD/3D độ phân giải cao cho mã ' . ($sku !== '' ? $sku : $title)); ?>">
                    <span>Tải Map CAD/3D</span>
                    <i class="ph-bold ph-download-simple drv-icon--xs" aria-hidden="true"></i>
                </a>
            <?php elseif ($role_label !== '') : ?>
                <figcaption class="drv-pdp-gallery__tag"><?php echo esc_html($role_label); ?></figcaption>
            <?php endif; ?>

            <?php if ($has_more_mob && $idx === 3) : ?>
                <div class="drv-pdp-gallery__more-overlay" aria-hidden="true">
                    <i class="ph-bold ph-images drv-pdp-gallery__more-icon" aria-hidden="true"></i>
                    <span class="drv-pdp-gallery__more-count">+<?php echo esc_html((string) $remaining_mob); ?></span>
                    <span class="drv-pdp-gallery__more-label">Xem tất cả</span>
                </div>
            <?php endif; ?>
        </figure>
    <?php endforeach; ?>
</div>
