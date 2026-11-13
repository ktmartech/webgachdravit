<?php
declare(strict_types=1);

use Dravit\Home\HomeData;

defined('ABSPATH') || exit;

/**
 * Section: Hero Advisor — Void AI Motion Engine & Glass Card (`01-home.html`, `S-3`, `docs/07 §3.1`, `D-25`, `D-32`).
 * - Nền Void Gradient + `<canvas>` 4-Layer AI Motion Engine (chỉ chạy khi hiển thị & cho phép chuyển động).
 * - Cột trái: AI Beacon, Headline Anton 3 dòng, 3 thẻ cam kết kỹ thuật quét spotlight tuần tự.
 * - Cột phải: Thẻ kính viền tia sáng xoay, LiDAR scan, bóc tách thông số trực tiếp & dự toán thùng (+5%).
 * - Tôn trọng cờ `drv_settings.features.ai_enabled` (fallback tĩnh về Smart Collections khi tắt).
 *
 * @var array<string, mixed> $args
 */

$ai_enabled  = (bool) drv_setting('features.ai_enabled', true);
$show_search = (bool) ($args['show_search'] ?? true);

$eyebrow         = trim((string) ($args['eyebrow'] ?? drv_setting('home.hero_eyebrow', $ai_enabled ? 'TRỢ LÝ AI CHỌN GẠCH & DỰ TOÁN' : 'HỆ THỐNG CHỌN GẠCH & DỰ TOÁN VẬT TƯ')));
$headline_line1  = trim((string) ($args['headline_top'] ?? drv_setting('home.hero_headline_top', 'CHỌN ĐÚNG GẠCH.')));
$headline_accent = trim((string) ($args['headline_accent'] ?? drv_setting('home.hero_headline_accent', 'CHUẨN THÔNG SỐ, SÁT')));
$headline_line3  = trim((string) ($args['headline_bottom'] ?? drv_setting('home.hero_headline_bottom', 'DỰ TOÁN.')));
$subhead         = trim((string) ($args['subhead'] ?? drv_setting('home.hero_subhead', 'Chỉ cần mô tả không gian, diện tích hoặc ngân sách — hệ thống tự động đối chiếu thông số kỹ thuật (độ nhám R, xương gạch) và quy đổi chính xác số thùng cần mua.')));

$ai_url      = HomeData::siteUrl('/tro-ly-ai-tim-gach');
$catalog_url = HomeData::siteUrl('/gach');

$prompt_chips = [
    [
        'label'         => 'Phòng khách 50m² vân đá sáng',
        'prompt'        => 'Phòng khách 50m², thích gạch vân đá Marble sáng bóng dễ lau chùi',
        'fallback_path' => '/gach-phong-khach',
    ],
    [
        'label'         => 'Nhà tắm 6m² chống trơn người già',
        'prompt'        => 'Nhà tắm 6m² có người lớn tuổi, cần gạch chống trơn R11 an toàn',
        'fallback_path' => '/gach-nha-tam',
    ],
    [
        'label'         => 'Sân thượng 30m² chống nóng rêu mốc',
        'prompt'        => 'Sân thượng 30m² ngoài trời nắng mưa, cần gạch thoát nước chống rêu R11',
        'fallback_path' => '/gach-ngoai-troi',
    ],
    [
        'label'         => 'Phòng ngủ 20m² gạch vân gỗ ấm',
        'prompt'        => 'Phòng ngủ 20m² thích gạch giả gỗ thanh dài 15×90 tông ấm đi êm chân',
        'fallback_path' => '/gach-phong-ngu',
    ],
];
?>
<section class="drv-hero-void" id="home-hero" aria-labelledby="home-hero-title">
    <canvas class="drv-hero-canvas" id="aiMotionCanvas" data-drv-hero-canvas aria-hidden="true"></canvas>
    <div class="drv-hero-ambient-glow" id="cardAmbientGlow" data-drv-hero-glow aria-hidden="true"></div>

    <div class="l-container drv-hero-wrap">
        <div class="drv-hero-left">
            <div class="drv-ai-badge">
                <span class="drv-ai-badge__beacon" aria-hidden="true"></span>
                <span class="drv-ai-badge__dot" aria-hidden="true"></span>
                <span><?php echo esc_html($eyebrow); ?></span>
            </div>

            <h1 id="home-hero-title" class="drv-hero-headline">
                <?php echo esc_html($headline_line1); ?><br>
                <span class="drv-headline-shimmer"><?php echo esc_html($headline_accent); ?></span><br>
                <?php echo esc_html($headline_line3); ?>
            </h1>

            <p class="drv-hero-subhead"><?php echo esc_html($subhead); ?></p>

            <h2 class="u-sr-only">Ba tiêu chuẩn chọn gạch và dự toán vật tư</h2>
            <div class="drv-spec-cards-grid" data-hero-spec-grid>
                <article class="drv-spec-card is-active" data-spec-step="0">
                    <div class="drv-spec-card__head">
                        <div class="drv-spec-card__icon" aria-hidden="true">
                            <i class="ph-fill ph-house-line"></i>
                        </div>
                        <h3 class="drv-spec-card__title">Đúng công năng</h3>
                    </div>
                    <p class="drv-spec-card__desc">Gợi ý sát từng khu vực sử dụng</p>
                </article>

                <article class="drv-spec-card" data-spec-step="1">
                    <div class="drv-spec-card__head">
                        <div class="drv-spec-card__icon" aria-hidden="true">
                            <i class="ph-fill ph-shield-check"></i>
                        </div>
                        <h3 class="drv-spec-card__title">Chuẩn thông số</h3>
                    </div>
                    <p class="drv-spec-card__desc">An toàn chống trơn, xương gạch bền</p>
                </article>

                <article class="drv-spec-card" data-spec-step="2">
                    <div class="drv-spec-card__head">
                        <div class="drv-spec-card__icon" aria-hidden="true">
                            <i class="ph-fill ph-calculator"></i>
                        </div>
                        <h3 class="drv-spec-card__title">Sát dự toán</h3>
                    </div>
                    <p class="drv-spec-card__desc">Quy đổi m² sang thùng +5% hao hụt</p>
                </article>
            </div>
        </div>

        <?php if ($show_search) : ?>
            <div class="drv-hero-right">
                <div
                    class="drv-ai-card-wrapper has-beam"
                    id="aiPromptCard"
                    data-drv-hero-advisor
                    data-ai-enabled="<?php echo $ai_enabled ? '1' : '0'; ?>"
                    data-ai-url="<?php echo esc_url($ai_url); ?>"
                    data-catalog-url="<?php echo esc_url($catalog_url); ?>"
                >
                    <form
                        class="drv-ai-glass"
                        action="<?php echo esc_url($ai_enabled ? $ai_url : $catalog_url); ?>"
                        method="get"
                        data-hero-advisor-form
                    >
                        <div class="drv-ai-top-accent" aria-hidden="true"></div>
                        <div class="drv-ai-scan-overlay" id="cardScanOverlay" data-hero-scan-overlay aria-hidden="true"></div>

                        <div class="drv-ai-header">
                            <label for="aiPromptInput" class="drv-ai-label">
                                <span>Mô tả nhu cầu của bạn</span>
                            </label>
                            <span class="drv-ai-status" id="aiStatusBadge" data-hero-status>
                                <span class="drv-ai-status__dot" data-hero-status-dot aria-hidden="true"></span>
                                <span class="drv-ai-equalizer" data-hero-equalizer aria-hidden="true">
                                    <span></span><span></span><span></span><span></span>
                                </span>
                                <span data-hero-status-text><?php echo esc_html($ai_enabled ? 'Sẵn sàng tư vấn' : '28 Danh mục chuẩn'); ?></span>
                            </span>
                        </div>

                        <div class="drv-ai-input-wrap">
                            <textarea
                                id="aiPromptInput"
                                name="<?php echo esc_attr($ai_enabled ? 'prompt' : 's'); ?>"
                                class="drv-ai-textarea"
                                rows="3"
                                maxlength="320"
                                placeholder="Ví dụ: Phòng khách 50m², thích vân đá sáng bóng dễ lau chùi, ngân sách khoảng 400k/m²..."
                                data-hero-prompt-input
                            ></textarea>

                            <div class="drv-ai-nlp-box" id="extractedEntitiesBox" data-hero-nlp-box hidden>
                                <div class="drv-ai-nlp-head">
                                    <span class="drv-ai-nlp-title">
                                        <span>Thông số đã nhận diện:</span>
                                    </span>
                                    <button type="button" class="drv-ai-reset-btn" data-hero-reset>
                                        <i class="ph-bold ph-arrow-counter-clockwise" aria-hidden="true"></i>
                                        <span>Làm mới</span>
                                    </button>
                                </div>
                                <div class="drv-ai-nlp-tags" data-hero-nlp-tags></div>
                            </div>
                        </div>

                        <div class="drv-ai-chips-section">
                            <span class="drv-ai-chips-label">Gợi ý nhanh (chạm để điền mẫu):</span>
                            <div class="drv-ai-chips-wrap" role="group" aria-label="Mẫu nhu cầu chọn gạch phổ biến">
                                <?php foreach ($prompt_chips as $chip) : ?>
                                    <button
                                        type="button"
                                        class="drv-ai-prompt-chip"
                                        data-hero-chip
                                        data-prompt="<?php echo esc_attr($chip['prompt']); ?>"
                                        data-fallback-url="<?php echo esc_url(HomeData::siteUrl($chip['fallback_path'])); ?>"
                                    >
                                        <span><?php echo esc_html($chip['label']); ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="drv-ai-card-foot">
                            <span class="drv-ai-foot-hint">Tự động nhận diện diện tích, khổ gạch &amp; ngân sách</span>
                            <button type="submit" class="drv-ai-cta-btn" data-hero-submit>
                                <span><?php echo esc_html($ai_enabled ? 'Phân tích & Gợi ý gạch' : 'Xem dòng gạch phù hợp'); ?></span>
                                <i class="ph-bold ph-arrow-right cta-arrow" aria-hidden="true"></i>
                            </button>
                        </div>

                        <div class="drv-ai-result-panel" id="aiResultPanel" data-hero-result-box aria-live="polite" hidden>
                            <div class="drv-ai-result__top">
                                <span class="drv-ai-result__kicker">
                                    <span>Đề xuất tối ưu từ hệ thống</span>
                                </span>
                                <span class="drv-ai-result__match" data-hero-result-match>98% phù hợp</span>
                            </div>

                            <p class="drv-ai-result__title" data-hero-result-summary></p>

                            <div class="drv-ai-result__metrics">
                                <div class="drv-ai-result__metric">
                                    <span class="drv-ai-result__metric-label">Thông số chuẩn</span>
                                    <strong class="drv-ai-result__metric-val" data-hero-out-spec>Porcelain · R9</strong>
                                </div>
                                <div class="drv-ai-result__metric">
                                    <span class="drv-ai-result__metric-label">Số lượng (+5%)</span>
                                    <strong class="drv-ai-result__metric-val u-tabular" data-hero-result-calc>--</strong>
                                </div>
                                <div class="drv-ai-result__metric">
                                    <span class="drv-ai-result__metric-label">Dự toán sát</span>
                                    <strong class="drv-ai-result__metric-val drv-ai-result__metric-val--accent u-tabular" data-hero-out-budget>--</strong>
                                </div>
                            </div>

                            <div class="drv-ai-result__actions">
                                <a
                                    href="<?php echo esc_url($ai_enabled ? $ai_url : $catalog_url); ?>"
                                    class="drv-ai-result__btn drv-ai-result__btn--primary"
                                    data-hero-open-ai
                                >
                                    <i class="ph-bold ph-chat-circle-dots" aria-hidden="true"></i>
                                    <span><?php echo esc_html($ai_enabled ? 'Mở tư vấn chi tiết & mẫu gạch' : 'Mở danh mục đề xuất'); ?></span>
                                </a>
                                <a
                                    href="<?php echo esc_url($catalog_url); ?>"
                                    class="drv-ai-result__btn drv-ai-result__btn--ghost"
                                    data-hero-open-cat
                                >
                                    <i class="ph-bold ph-list-bullets" aria-hidden="true"></i>
                                    <span>Xem kho gạch</span>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
