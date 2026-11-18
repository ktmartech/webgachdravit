<?php
declare(strict_types=1);

/**
 * Menu chia sẻ dùng chung (Cẩm nang + trang sản phẩm). Script: `assets/src/js/chunks/share-menu.js`.
 *
 * @var array{url?: string, label?: string, menu_id?: string} $args
 */

defined('ABSPATH') || exit;

$args    = isset($args) && is_array($args) ? $args : [];
$url     = (string) ($args['url'] ?? '');
$label   = (string) ($args['label'] ?? 'Chia sẻ');
$menu_id = (string) ($args['menu_id'] ?? 'drv-share-menu');

if ($url === '') {
    return;
}
?>
<div class="drv-share" data-drv-share>
    <button type="button"
            class="drv-btn-share-ghost"
            data-drv-action="share-toggle"
            aria-haspopup="true"
            aria-expanded="false"
            aria-controls="<?php echo esc_attr($menu_id); ?>"
            title="<?php echo esc_attr($label); ?>"
            aria-label="<?php echo esc_attr($label); ?>">
        <i class="ph-bold ph-share-network" aria-hidden="true"></i>
    </button>
    <div class="drv-share__menu" id="<?php echo esc_attr($menu_id); ?>" role="menu" aria-label="<?php echo esc_attr($label); ?>" hidden>
        <a role="menuitem"
           class="drv-share__item"
           href="<?php echo esc_url('https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url)); ?>"
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
                data-share-url="<?php echo esc_attr($url); ?>">
            <i class="ph ph-chat-circle-dots drv-icon--sm" aria-hidden="true"></i>
            <span>Gửi qua Zalo</span>
        </button>
        <button type="button"
                role="menuitem"
                class="drv-share__item"
                data-drv-action="copy-text"
                data-copy-value="<?php echo esc_attr($url); ?>">
            <i class="ph ph-copy drv-icon--sm" aria-hidden="true"></i>
            <span>Sao chép liên kết</span>
        </button>
    </div>
</div>
