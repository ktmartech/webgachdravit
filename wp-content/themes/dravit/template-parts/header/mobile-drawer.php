<?php
declare(strict_types=1);

use Dravit\Core\Menu\MegaMenuRenderer;

defined('ABSPATH') || exit;

$hotline_raw = (string) drv_setting('contact.hotline', '0909 888 777');
$hotline_tel = drv_clean_phone($hotline_raw);
?>
<dialog id="drv-mobile-menu-drawer" class="drv-dialog drv-dialog--drawer-left" aria-labelledby="mobile-menu-title">
    <div class="drv-dialog__head">
        <h2 id="mobile-menu-title" class="u-text-base u-bold">Danh mục Gạch DRAVIT</h2>
        <button type="button" class="drv-btn drv-btn--sm drv-btn--ghost drv-btn--icon" data-drv-action="dialog-close" aria-label="Đóng menu">
            <i class="ph ph-x drv-icon" aria-hidden="true"></i>
        </button>
    </div>
    <div class="drv-dialog__body drv-mob-nav">
        <?php
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Đã escape toàn bộ bên trong MegaMenuRenderer.
        echo MegaMenuRenderer::get_fragments()['mobile'];
        ?>
    </div>
    <div class="drv-dialog__foot l-grid l-grid--2 l-grid--gap-sm">
        <a href="<?php echo esc_url('tel:' . $hotline_tel); ?>" class="drv-btn drv-btn--outline drv-btn--sm drv-btn--full">
            <i class="ph ph-phone-call drv-icon--xs" aria-hidden="true"></i>
            <span><?php echo esc_html($hotline_raw); ?></span>
        </a>
        <button type="button" class="drv-btn drv-btn--moss drv-btn--sm drv-btn--full" data-drv-action="dialog-open" data-drv-target="#drv-quote-modal">
            <i class="ph ph-file-text drv-icon--xs" aria-hidden="true"></i>
            <span>Báo giá</span>
        </button>
    </div>
</dialog>

