<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Lưới các mặt vân ngẫu nhiên (`drv_face_image_ids`) + nút tải file Map CAD/3D (`drv_map_file_id`) + PhotoSwipe 5 zoom.
 *
 * @var array<string, mixed> $args
 */
$view        = isset($args['view']) && is_array($args['view']) ? $args['view'] : [];
$faces       = isset($view['faces']) && is_array($view['faces']) ? $view['faces'] : [];
$faces_count = max(count($faces), (int) ($view['faces_count'] ?? 0));
$map_url     = trim((string) ($view['map_url'] ?? ''));

if ($faces === []) {
    return;
}
?>
<div class="drv-pdp-faces-wrap">
    <div class="l-cluster l-cluster--between">
        <span class="u-text-xs u-bold u-text-navy">
            <?php echo esc_html($faces_count . ' mặt vân ngẫu nhiên (Random Faces)'); ?>
        </span>

        <?php if ($map_url !== '') : ?>
            <a href="<?php echo esc_url($map_url); ?>" class="drv-link u-text-xs u-bold" download>
                <i class="ph-bold ph-download-simple drv-icon--xs" aria-hidden="true"></i>
                <span>Tải file Map CAD/3D</span>
            </a>
        <?php endif; ?>
    </div>

    <div class="drv-faces-grid"
         data-pswp-gallery="pdp-faces"
         data-pswp-title="<?php echo esc_attr($faces_count . ' mặt vân ngẫu nhiên' . (($view['sku'] ?? '') !== '' ? ' (Mã ' . $view['sku'] . ')' : '')); ?>"
         <?php echo $map_url !== '' ? 'data-pswp-map-url="' . esc_url($map_url) . '"' : ''; ?>
         aria-label="Danh sách các mặt vân ngẫu nhiên">
        <?php foreach ($faces as $f_idx => $face) :
            if (!is_array($face)) {
                continue;
            }
            $variant_idx = (int) ($face['variant_idx'] ?? 0);
            $tile_cls    = 'drv-face-tile' . ($variant_idx > 1 ? ' drv-face-tile--f' . min(6, $variant_idx) : '');
            ?>
            <a href="<?php echo esc_url((string) $face['src_full']); ?>"
               class="<?php echo esc_attr($tile_cls); ?>"
               data-pswp-item
               data-pswp-index="<?php echo esc_attr((string) $f_idx); ?>"
               data-pswp-width="<?php echo esc_attr((string) $face['width']); ?>"
               data-pswp-height="<?php echo esc_attr((string) $face['height']); ?>"
               data-pswp-caption="<?php echo esc_attr((string) $face['caption']); ?>"
               aria-label="<?php echo esc_attr((string) $face['alt']); ?>">
                <img src="<?php echo esc_url((string) $face['src_thumb']); ?>"
                     width="96"
                     height="96"
                     loading="lazy"
                     decoding="async"
                     alt="<?php echo esc_attr((string) $face['alt']); ?>">
            </a>
        <?php endforeach; ?>
    </div>
</div>
