<?php
declare(strict_types=1);

use Dravit\Home\HomeData;

defined('ABSPATH') || exit;

/**
 * Section: Size & R-Rating Interactive Guide (`template-parts/sections/size-r-guide.php`, `S-3`, `docs/07 §3.5`, `15 §5.4`).
 * Custom Element `<drv-size-guide>` đối chiếu tỷ lệ 5 khổ gạch thật trên khung 1 m², mô phỏng mật độ đường ron & lọc R9-R12.
 *
 * @var array<string, mixed> $args
 */

$eyebrow = trim((string) ($args['eyebrow'] ?? '03 · Cẩm nang chọn khổ gạch & bề mặt'));
$title   = trim((string) ($args['title'] ?? 'Tỷ lệ kích thước thật & tiêu chuẩn chống trượt'));
$desc    = trim((string) ($args['description'] ?? ''));
$surface = (string) ($args['surface'] ?? 'ivory');

$sec_cls = 'l-section';
if ($surface === 'white') {
    $sec_cls .= ' l-section--white';
} elseif ($surface === 'champagne') {
    $sec_cls .= ' l-section--champagne';
}

$sg_data      = HomeData::getSizeGuideData();
$default_size = (string) ($sg_data['default_size'] ?? '80x80');
$sizes        = isset($sg_data['sizes']) && is_array($sg_data['sizes']) ? $sg_data['sizes'] : [];
$rooms        = isset($sg_data['rooms']) && is_array($sg_data['rooms']) ? $sg_data['rooms'] : [];
$zones        = isset($sg_data['zones']) && is_array($sg_data['zones']) ? $sg_data['zones'] : [];
$surfaces     = isset($sg_data['surfaces']) && is_array($sg_data['surfaces']) ? $sg_data['surfaces'] : [];

$initial_size = isset($sizes[$default_size]) && is_array($sizes[$default_size])
    ? $sizes[$default_size]
    : [
        'label' => '80×80 cm',
        'meta'  => 'Sàn chuẩn hiện đại',
        'w'     => 80,
        'h'     => 80,
        'rate'  => '1,56 viên/m²',
        'box'   => '3 viên = 1,92 m²',
        'grout' => '1,5 - 2,0 mm',
        'url'   => HomeData::siteUrl('/gach-80x80'),
    ];

$ai_enabled = (bool) drv_setting('features.ai_enabled', true);
$calc_url   = $ai_enabled ? HomeData::siteUrl('/tro-ly-ai-tim-gach') : HomeData::siteUrl('/danh-sach-bao-gia');

$json_flags  = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
$encoded_cfg = function_exists('wp_json_encode')
    ? (string) wp_json_encode($sg_data, $json_flags)
    : (string) json_encode($sg_data, $json_flags);
?>
<section class="<?php echo esc_attr($sec_cls); ?>" id="home-size-guide">
    <div class="l-container">
        <?php
        drv_ui('section-head', [
            'eyebrow'     => $eyebrow,
            'title'       => $title,
            'desc'        => $desc,
            'heading_tag' => 'h2',
            'reveal'      => 'fade-up',
        ]);
        ?>

        <drv-size-guide class="drv-sg" data-drv-reveal="fade-up">
            <script type="application/json" data-sg-config><?php echo $encoded_cfg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>

            <div class="l-grid l-grid--2 drv-sg__grid">
                <div class="drv-scale-stage">
                    <div class="drv-scale-stage__head">
                        <h3 class="drv-scale-stage__title">1. Tỷ lệ kích thước thật (khung chuẩn 1 m²)</h3>
                        <div class="drv-sg-controls">
                            <div class="drv-sg-modes" role="group" aria-label="Chế độ xem tỷ lệ">
                                <button type="button" class="drv-sg-mode is-active" data-sg-view="scale" aria-pressed="true">
                                    Tỷ lệ viên
                                </button>
                                <button type="button" class="drv-sg-mode" data-sg-view="floor" aria-pressed="false">
                                    Mật độ ron
                                </button>
                            </div>
                            <label class="drv-sg-ref-toggle">
                                <input type="checkbox" checked data-sg-ref>
                                <span>Khung 1 m²</span>
                            </label>
                        </div>
                    </div>

                    <div class="drv-scale-canvas" data-sg-canvas>
                        <div class="drv-scale-ref" data-sg-ref-box aria-hidden="true">
                            <span>Khung 1 m² (100×100 cm)</span>
                        </div>

                        <div class="drv-scale-bars" data-sg-scale-panel role="group" aria-label="Chọn khổ gạch để so sánh">
                            <?php foreach ($sizes as $size_key => $size_item) : ?>
                                <?php
                                $is_sel = ((string) $size_key === $default_size);
                                $w_cm   = max(15, (int) ($size_item['w'] ?? 60));
                                $h_cm   = max(15, (int) ($size_item['h'] ?? 60));
                                // Tỷ lệ trực quan chuẩn với chiều cao tối đa 120cm = 100%
                                $rel_w  = round(($w_cm / 120) * 100, 1);
                                $rel_h  = round(($h_cm / 120) * 100, 1);
                                ?>
                                <button
                                    type="button"
                                    class="drv-scale-item<?php echo $is_sel ? ' is-active' : ''; ?>"
                                    data-sg-size="<?php echo esc_attr((string) $size_key); ?>"
                                    aria-pressed="<?php echo $is_sel ? 'true' : 'false'; ?>"
                                    style="--tile-w: <?php echo esc_attr((string) $rel_w); ?>%; --tile-h: <?php echo esc_attr((string) $rel_h); ?>%;"
                                >
                                    <span class="drv-scale-item__shape" aria-hidden="true">
                                        <span class="drv-scale-item__dim"><?php echo esc_html(str_replace(' cm', '', (string) $size_item['label'])); ?></span>
                                    </span>
                                    <span class="drv-scale-item__meta"><?php echo esc_html((string) $size_item['meta']); ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <div class="drv-sg-floor" data-sg-floor-panel hidden>
                            <div class="drv-sg-floor__head">
                                <span class="drv-sg-floor__title" data-sg-floor-label>Mô phỏng mặt sàn 3,6 × 2,4 m lát khổ 80×80 cm</span>
                                <span class="drv-badge drv-badge--teal" data-sg-grout-diff>Ít hơn ~25% đường ron so với 60×60</span>
                            </div>
                            <div class="drv-sg-floor__grid" data-sg-floor-grid style="--sg-cell-w: 26.67%; --sg-cell-h: 33.33%;" aria-hidden="true">
                                <?php for ($cell = 0; $cell < 24; $cell++) : ?>
                                    <span class="drv-sg-floor__tile"></span>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>

                    <dl class="drv-sg-spec">
                        <div class="drv-sg-spec__item">
                            <dt>Khổ đang xem</dt>
                            <dd data-sg-out="label"><?php echo esc_html((string) $initial_size['label']); ?></dd>
                        </div>
                        <div class="drv-sg-spec__item">
                            <dt>Định mức / m²</dt>
                            <dd data-sg-out="rate"><?php echo esc_html((string) $initial_size['rate']); ?></dd>
                        </div>
                        <div class="drv-sg-spec__item">
                            <dt>Đóng thùng chuẩn</dt>
                            <dd data-sg-out="box"><?php echo esc_html((string) $initial_size['box']); ?></dd>
                        </div>
                        <div class="drv-sg-spec__item">
                            <dt>Mạch ron khuyến nghị</dt>
                            <dd data-sg-out="grout"><?php echo esc_html((string) $initial_size['grout']); ?></dd>
                        </div>
                    </dl>

                    <div class="drv-sg-rooms">
                        <span class="drv-sg-rooms__label">Chọn nhanh theo diện tích phòng:</span>
                        <div class="drv-sg-rooms__btns" role="group" aria-label="Gợi ý khổ gạch theo diện tích phòng">
                            <?php foreach ($rooms as $room) : ?>
                                <button
                                    type="button"
                                    class="drv-chip drv-chip--square"
                                    data-sg-room="<?php echo esc_attr((string) ($room['key'] ?? '')); ?>"
                                    data-target-size="<?php echo esc_attr((string) ($room['target_size'] ?? '80x80')); ?>"
                                    data-recommend="<?php echo esc_attr((string) ($room['recommend'] ?? '')); ?>"
                                >
                                    <span><?php echo esc_html((string) ($room['label'] ?? '')); ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="drv-scale-stage">
                    <div class="drv-scale-stage__head">
                        <h3 class="drv-scale-stage__title">2. Chọn bề mặt &amp; cấp nhám R theo khu vực</h3>
                    </div>

                    <div class="drv-pill-row drv-sg-zones" role="group" aria-label="Lọc bề mặt theo độ ẩm khu vực">
                        <?php foreach ($zones as $z_idx => $zone) : ?>
                            <?php $z_active = ($z_idx === 0); ?>
                            <button
                                type="button"
                                class="drv-chip drv-chip--square<?php echo $z_active ? ' is-active' : ''; ?>"
                                data-sg-zone-btn="<?php echo esc_attr((string) ($zone['key'] ?? 'all')); ?>"
                                aria-pressed="<?php echo $z_active ? 'true' : 'false'; ?>"
                            >
                                <span><?php echo esc_html((string) ($zone['label'] ?? '')); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="drv-surface-list" data-sg-surfaces>
                        <?php foreach ($surfaces as $surf) : ?>
                            <?php
                            $zones_attr = implode(',', is_array($surf['zones'] ?? null) ? $surf['zones'] : ['dry']);
                            ?>
                            <a
                                href="<?php echo esc_url((string) $surf['url']); ?>"
                                class="drv-surface-card"
                                data-sg-surface
                                data-zones="<?php echo esc_attr($zones_attr); ?>"
                            >
                                <img
                                    class="drv-surface-card__swatch"
                                    src="<?php echo esc_url((string) $surf['swatch_url']); ?>"
                                    alt="<?php echo esc_attr('Bề mặt ' . (string) $surf['name']); ?>"
                                    width="64"
                                    height="64"
                                    loading="lazy"
                                    decoding="async"
                                >
                                <div class="drv-surface-card__body">
                                    <div class="drv-surface-card__head">
                                        <span class="drv-surface-card__name"><?php echo esc_html((string) $surf['name']); ?></span>
                                        <?php
                                        drv_ui('badge', [
                                            'text'    => (string) $surf['badge'],
                                            'variant' => (string) ($surf['badge_variant'] ?? 'outline'),
                                        ]);
                                        ?>
                                    </div>
                                    <p class="drv-surface-card__desc"><?php echo esc_html((string) $surf['desc']); ?></p>
                                </div>
                                <span class="drv-surface-card__arrow" aria-hidden="true">
                                    <?php drv_ui('icon', ['name' => 'arrow-right', 'size' => 'xs']); ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="drv-sg-tip">
                        <i class="ph ph-info" aria-hidden="true"></i>
                        <p>
                            <strong>Lưu ý kỹ thuật:</strong> Nhà có người lớn tuổi hoặc trẻ nhỏ nên ưu tiên bề mặt đạt
                            <strong>R10 (DCOF ≥ 0,42)</strong> trở lên cho sàn bếp và tối thiểu <strong>R11</strong> cho khu tắm ướt.
                        </p>
                    </div>
                </div>
            </div>

            <div class="drv-sg-bar">
                <p class="drv-sg-bar__summary" data-sg-bar-summary>
                    Đang xem khổ <strong><?php echo esc_html((string) $initial_size['label']); ?></strong>:
                    <?php echo esc_html((string) $initial_size['box']); ?>, mạch ron <?php echo esc_html((string) $initial_size['grout']); ?>.
                </p>
                <div class="drv-sg-bar__actions">
                    <a
                        href="<?php echo esc_url((string) $initial_size['url']); ?>"
                        class="drv-btn drv-btn--moss drv-btn--sm"
                        data-sg-cat-link
                    >
                        <span data-sg-cat-label>Xem gạch <?php echo esc_html((string) $initial_size['label']); ?></span>
                        <?php drv_ui('icon', ['name' => 'arrow-right', 'size' => 'xs']); ?>
                    </a>
                    <a href="<?php echo esc_url($calc_url); ?>" class="drv-btn drv-btn--outline drv-btn--sm">
                        <?php drv_ui('icon', ['name' => 'calculator', 'size' => 'sm']); ?>
                        <span>Tính số thùng theo diện tích</span>
                    </a>
                </div>
            </div>
        </drv-size-guide>
    </div>
</section>

