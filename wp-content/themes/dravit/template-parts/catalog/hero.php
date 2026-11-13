<?php
declare(strict_types=1);

use Dravit\Filters\UrlState;

defined('ABSPATH') || exit;

/**
 * Compact Silo Hero cho trang Danh mục / Bộ sưu tập / Xuất xứ (02-catalog-collection.html, docs/07 §4, docs/21 Step 6).
 *
 * @var array<string, mixed> $args
 */
$state        = ($args['state'] ?? null) instanceof UrlState ? $args['state'] : UrlState::fromCurrentRequest();
$h1           = trim((string) ($args['h1'] ?? 'Kho Gạch Ốp Lát Chính Hãng DRAVIT'));
$intro        = trim((string) ($args['intro'] ?? ''));
$has_geo_sec  = !empty($args['has_geo_section']);
$base_stats   = isset($args['base_stats']) && is_array($args['base_stats']) ? $args['base_stats'] : ['total' => 0, 'min_price_m2' => 0, 'showroom_count' => 0];
$total_base   = max(0, (int) ($base_stats['total'] ?? 0));
$min_price_m2 = max(0, (int) ($base_stats['min_price_m2'] ?? 0));
$showroom_cnt = max(0, (int) ($base_stats['showroom_count'] ?? 0));
?>
<section class="drv-silo-hero" aria-labelledby="silo-page-title">
    <div class="l-container">
        <div class="drv-silo-hero__top">
            <?php drv_ui('breadcrumb'); ?>

            <?php if ($has_geo_sec) : ?>
                <a href="#bang-thong-so-geo" class="drv-silo-hero__spec-link">
                    <i class="ph-bold ph-table drv-icon--xs" aria-hidden="true"></i>
                    <span>Bảng tiêu chuẩn kỹ thuật</span>
                    <i class="ph-bold ph-arrow-down drv-icon--xs" aria-hidden="true"></i>
                </a>
            <?php endif; ?>
        </div>

        <div class="drv-silo-hero__main">
            <div class="drv-silo-hero__content">
                <h1 id="silo-page-title" class="drv-silo-hero__title"><?php echo esc_html($h1); ?></h1>
                <?php if ($intro !== '') : ?>
                    <p class="drv-silo-hero__desc"><?php echo esc_html($intro); ?></p>
                <?php endif; ?>

                <div class="drv-silo-hero__stats" aria-label="Thống kê nhanh danh mục">
                    <span class="drv-silo-stat">
                        <i class="ph-fill ph-squares-four drv-icon--xs" aria-hidden="true"></i>
                        <span><strong class="u-tabular" data-hero-total><?php echo esc_html((string) $total_base); ?></strong> mẫu trong danh mục</span>
                    </span>

                    <?php if ($min_price_m2 > 0) : ?>
                        <span class="drv-silo-stat">
                            <i class="ph-fill ph-tag drv-icon--xs" aria-hidden="true"></i>
                            <span>Giá từ <strong class="u-tabular"><?php echo esc_html(drv_format_vnd($min_price_m2) . '/m²'); ?></strong></span>
                        </span>
                    <?php endif; ?>

                    <?php if ($showroom_cnt > 0) : ?>
                        <span class="drv-silo-stat drv-silo-stat--moss">
                            <i class="ph-fill ph-seal-check drv-icon--xs" aria-hidden="true"></i>
                            <span><strong class="u-tabular"><?php echo esc_html((string) $showroom_cnt); ?></strong> mẫu có kệ thật tại Showroom</span>
                        </span>
                    <?php endif; ?>

                    <span class="drv-silo-stat">
                        <i class="ph-fill ph-package drv-icon--xs" aria-hidden="true"></i>
                        <span>Xuất nguyên thùng đồng lô màu</span>
                    </span>
                </div>
            </div>
        </div>
    </div>
</section>

