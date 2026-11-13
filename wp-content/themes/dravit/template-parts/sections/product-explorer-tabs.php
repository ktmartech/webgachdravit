<?php
declare(strict_types=1);

use Dravit\Home\HomeData;

defined('ABSPATH') || exit;

/**
 * Section: Product Explorer Tabs (`template-parts/sections/product-explorer-tabs.php`, `S-3`, `docs/07 §3.4`).
 * 4 tab công năng (`Gạch lát nền`, `Gạch ốp tường`, `Gạch trang trí`, `Đá nung kết`) + chip Smart Collection tĩnh + Lookbook `<dl>` + lưới SP chuẩn mockup.
 *
 * @var array<string, mixed> $args
 */

$eyebrow = trim((string) ($args['eyebrow'] ?? '02 · Khám phá kho gạch thực tế'));
$title   = trim((string) ($args['title'] ?? 'Sản phẩm nổi bật theo công năng'));
$desc    = trim((string) ($args['description'] ?? ''));
$per_tab = max(1, (int) ($args['per_tab'] ?? 3));
$surface = (string) ($args['surface'] ?? 'white');

$sec_cls = 'l-section';
if ($surface === 'white') {
    $sec_cls .= ' l-section--white';
} elseif ($surface === 'champagne') {
    $sec_cls .= ' l-section--champagne';
}

$tabs = HomeData::getExplorerTabs($per_tab);
?>
<section class="<?php echo esc_attr($sec_cls); ?>" id="home-explorer">
    <div class="l-container">
        <?php
        drv_ui('section-head', [
            'eyebrow'      => $eyebrow,
            'title'        => $title,
            'desc'         => $desc,
            'heading_tag'  => 'h2',
            'action_label' => 'Xem kho gạch đầy đủ',
            'action_url'   => HomeData::siteUrl('/gach'),
            'reveal'       => 'fade-up',
        ]);
        ?>

        <div class="drv-explorer" data-drv-tabs>
            <div class="drv-tabs" role="tablist" aria-label="Chọn nhóm công năng gạch">
                <?php foreach ($tabs as $idx => $tab) : ?>
                    <?php $is_active = ($idx === 0); ?>
                    <button
                        type="button"
                        id="<?php echo esc_attr($tab['tab_id']); ?>"
                        class="drv-tab<?php echo $is_active ? ' is-active' : ''; ?>"
                        role="tab"
                        aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
                        aria-controls="<?php echo esc_attr($tab['panel_id']); ?>"
                        tabindex="<?php echo $is_active ? '0' : '-1'; ?>"
                        data-drv-action="tab-switch"
                    >
                        <span><?php echo esc_html($tab['label']); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <?php foreach ($tabs as $idx => $tab) : ?>
                <?php
                $is_active = ($idx === 0);
                $lb        = $tab['lookbook'];
                ?>
                <div
                    id="<?php echo esc_attr($tab['panel_id']); ?>"
                    class="drv-tab-panel"
                    role="tabpanel"
                    aria-labelledby="<?php echo esc_attr($tab['tab_id']); ?>"
                    <?php echo $is_active ? '' : 'hidden'; ?>
                >
                    <div class="drv-pill-row drv-explorer__chips">
                        <?php foreach ($tab['chips'] as $chip) : ?>
                            <a
                                href="<?php echo esc_url($chip['url']); ?>"
                                class="drv-chip<?php echo $chip['active'] ? ' is-active' : ''; ?>"
                            >
                                <span><?php echo esc_html($chip['label']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="drv-explorer__body">
                        <aside class="drv-lookbook-card"<?php echo $is_active ? ' data-drv-reveal="fade-up"' : ''; ?>>
                            <div class="drv-lookbook-card__media">
                                <img
                                    src="<?php echo esc_url($lb['img_url']); ?>"
                                    alt="<?php echo esc_attr($lb['img_alt']); ?>"
                                    width="600"
                                    height="450"
                                    loading="lazy"
                                    decoding="async"
                                >
                                <span class="drv-lookbook-card__kicker"><?php echo esc_html($lb['kicker']); ?></span>
                            </div>
                            <div class="drv-lookbook-card__body">
                                <h3 class="drv-lookbook-card__title"><?php echo esc_html($lb['title']); ?></h3>
                                <dl class="drv-lookbook-card__dl">
                                    <?php foreach ($lb['specs'] as $spec) : ?>
                                        <div class="drv-lookbook-card__row">
                                            <dt><?php echo esc_html($spec['dt']); ?></dt>
                                            <dd><?php echo esc_html($spec['dd']); ?></dd>
                                        </div>
                                    <?php endforeach; ?>
                                </dl>
                                <a href="<?php echo esc_url($lb['cta_url']); ?>" class="drv-btn drv-btn--outline drv-btn--sm drv-btn--full">
                                    <span><?php echo esc_html($lb['cta_label']); ?></span>
                                    <?php drv_ui('icon', ['name' => 'arrow-right', 'size' => 'xs']); ?>
                                </a>
                            </div>
                        </aside>

                        <div class="drv-explorer__grid drv-snap-row"<?php echo $is_active ? ' data-drv-stagger="65"' : ''; ?>>
                            <?php foreach ($tab['products'] as $product_item) : ?>
                                <?php
                                drv_ui('product-card', [
                                    'product'     => $product_item,
                                    'context'     => 'home',
                                    'heading_tag' => 'h3',
                                ]);
                                ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
