<?php
/**
 * Section: Không gian làm việc 2 cột Trợ lý AI Tìm gạch & Dự toán vật tư (`docs/layout/01.1-ai-finder.html`, `docs/13`, `D-32`).
 *
 * @var array<string, mixed> $args
 */
declare(strict_types=1);

use Dravit\Advisor\AdvisorService;

defined('ABSPATH') || exit;

$title   = (string) ($args['title'] ?? 'Trợ lý AI Tìm gạch & Dự toán vật tư');
$zaloUrl = (string) drv_setting('contact.zalo', '');
if ($zaloUrl === '') {
    $rawPhone = drv_clean_phone((string) drv_setting('contact.hotline', '0909888777'));
    $zaloUrl  = 'https://zalo.me/' . $rawPhone;
}
$gachUrl = untrailingslashit(home_url('/gach'));
$boqUrl  = untrailingslashit(home_url('/danh-sach-bao-gia'));

$showcase = ['count' => 0, 'summary' => '', 'html' => '', 'context_facets' => []];
if (class_exists(AdvisorService::class)) {
    try {
        $showcase = (new AdvisorService())->getInitialShowcase();
    } catch (\Throwable $e) {
        unset($e);
    }
}

$initialCount   = (int) ($showcase['count'] ?? 0);
$initialSummary = (string) ($showcase['summary'] ?? 'Hệ thống tự động khóa chuẩn chống trượt DCOF R9 - R12 và quy đổi chẵn thùng theo quy cách đóng gói thực tế tại kho DRAVIT.');
$initialHtml    = (string) ($showcase['html'] ?? '');

$quickPrompts = [
    [
        'label'  => 'Phòng khách 35m² vân đá',
        'prompt' => 'Tư vấn gạch lát phòng khách 35m² vân đá sang trọng, dễ vệ sinh',
    ],
    [
        'label'  => 'Nhà tắm 6m² chống trượt R11',
        'prompt' => 'Nhà tắm 6m² có người lớn tuổi, cần chống trơn R11 ngân sách dưới 400k/m²',
    ],
    [
        'label'  => 'Phòng ngủ 20m² giả gỗ',
        'prompt' => 'Phòng ngủ 20m² thích gạch vân gỗ ấm cúng, bề mặt mờ êm chân',
    ],
    [
        'label'  => 'Sân vườn 40m² chịu lực',
        'prompt' => 'Gạch lát sân vườn ngoài trời 40m² chống rêu mốc, chịu lực đậu ô tô',
    ],
];
?>
<section class="drv-aif-page" aria-labelledby="drv-aif-title">
    <drv-ai-finder
        class="drv-aif-host"
        data-layout-mode="split"
        data-advisor-endpoint="<?php echo esc_url(untrailingslashit(rest_url('dravit/v1/advisor'))); ?>"
        data-feedback-endpoint="<?php echo esc_url(untrailingslashit(rest_url('dravit/v1/advisor/feedback'))); ?>"
        data-nonce-endpoint="<?php echo esc_url(untrailingslashit(rest_url('dravit/v1/nonce'))); ?>"
        data-default-filter-url="<?php echo esc_url($gachUrl); ?>"
        data-boq-url="<?php echo esc_url($boqUrl); ?>"
        data-zalo-url="<?php echo esc_url($zaloUrl); ?>"
    >
        <!-- TOP WORKSPACE BAR -->
        <header class="drv-aif-topbar">
            <div class="l-container drv-aif-topbar__inner">
                <div class="drv-aif-topbar__brand">
                    <div>
                        <?php
                        drv_ui('breadcrumb', [
                            'items' => [
                                ['label' => 'Trang chủ', 'url' => untrailingslashit(home_url('/'))],
                                ['label' => 'Gạch ốp lát', 'url' => $gachUrl],
                                ['label' => 'Trợ lý AI Tìm gạch', 'url' => ''],
                            ],
                        ]);
                        ?>
                        <div class="drv-aif-topbar__heading-row">
                            <h1 id="drv-aif-title" class="drv-aif-topbar__title"><?php echo esc_html($title); ?></h1>
                            <span class="drv-aif-topbar__badge">
                                <i class="ph-fill ph-sparkle" aria-hidden="true"></i>
                                <span>Chuẩn DCOF R9 - R12 · Tự tính thùng</span>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="drv-aif-topbar__controls">
                    <div class="drv-aif-modes" role="group" aria-label="Chế độ hiển thị khung tư vấn">
                        <button type="button" class="drv-aif-mode-btn is-active" data-aif-mode="split" aria-pressed="true">
                            <i class="ph-bold ph-columns" aria-hidden="true"></i>
                            <span>2 Cột</span>
                        </button>
                        <button type="button" class="drv-aif-mode-btn" data-aif-mode="stream" aria-pressed="false">
                            <i class="ph-bold ph-chat-centered-text" aria-hidden="true"></i>
                            <span>Chat đơn</span>
                        </button>
                    </div>

                    <button type="button" class="drv-aif-quote-btn" data-aif-open-quote-drawer aria-label="Mở danh sách báo giá đã chọn">
                        <i class="ph-bold ph-clipboard-text" aria-hidden="true"></i>
                        <span>Báo giá</span>
                        <span class="drv-aif-quote-btn__sub u-tabular" data-aif-quote-boxes-total>0 thùng</span>
                        <span class="drv-aif-quote-btn__badge u-tabular" data-drv-quote-count>0</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- MOBILE SEGMENTED TABS -->
        <div class="l-container drv-aif-mobile-tabs-wrap">
            <div class="drv-aif-mobile-tabs" role="tablist" aria-label="Chuyển đổi khung làm việc Trợ lý AI">
                <button
                    type="button"
                    class="drv-aif-tab is-active"
                    role="tab"
                    aria-selected="true"
                    data-aif-mobile-tab="chat"
                >
                    <i class="ph-fill ph-sparkle" aria-hidden="true"></i>
                    <span>Trợ lý AI Tư vấn</span>
                </button>
                <button
                    type="button"
                    class="drv-aif-tab"
                    role="tab"
                    aria-selected="false"
                    data-aif-mobile-tab="results"
                >
                    <i class="ph-bold ph-squares-four" aria-hidden="true"></i>
                    <span>Đề xuất &amp; Tính thùng</span>
                    <span class="drv-aif-tab__count u-tabular" data-aif-tab-count><?php echo esc_html((string) $initialCount); ?></span>
                </button>
            </div>
        </div>

        <!-- MAIN WORKSPACE STAGE -->
        <div class="l-container drv-aif-stage-wrap">
            <div class="drv-aif-workspace" data-aif-stage data-active-pane="chat">
                <!-- CỘT TRÁI: HỘI THOẠI TƯ VẤN KỸ THUẬT -->
                <section class="drv-aif-chat" aria-label="Hội thoại tư vấn kỹ thuật">
                    <div class="drv-aif-chat__head">
                        <div class="drv-aif-chat__agent">
                            <div class="drv-aif-chat__avatar" aria-hidden="true">
                                <i class="ph-fill ph-robot"></i>
                                <span class="drv-aif-chat__dot"></span>
                            </div>
                            <div>
                                <div class="drv-aif-chat__title-row">
                                    <strong class="drv-aif-chat__title">Dravit AI</strong>
                                    <span class="drv-aif-chat__status-pill">Trực tuyến</span>
                                </div>
                                <span class="drv-aif-chat__sub">Tư vấn thông số &amp; tính số thùng</span>
                            </div>
                        </div>

                        <div class="drv-aif-chat__head-actions">
                            <button
                                type="button"
                                class="drv-aif-icon-btn"
                                data-aif-expand-split
                                hidden
                                title="Mở rộng bảng so sánh 2 cột"
                                aria-label="Mở rộng bảng so sánh 2 cột"
                            >
                                <i class="ph-bold ph-arrows-out-simple" aria-hidden="true"></i>
                            </button>
                            <button
                                type="button"
                                class="drv-aif-icon-btn"
                                data-aif-reset
                                title="Làm mới hội thoại"
                                aria-label="Làm mới hội thoại"
                            >
                                <i class="ph-bold ph-arrows-counter-clockwise" aria-hidden="true"></i>
                            </button>
                            <a
                                href="<?php echo esc_url($gachUrl); ?>"
                                class="drv-aif-icon-btn"
                                data-aif-filter-link
                                title="Chuyển sang bộ lọc truyền thống 14 tiêu chí"
                                aria-label="Chuyển sang bộ lọc truyền thống 14 tiêu chí"
                            >
                                <i class="ph-bold ph-funnel" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>

                    <!-- ACTIVE CONTEXT FILTER CHIPS -->
                    <div class="drv-aif-context" data-aif-context hidden>
                        <span class="drv-aif-context__label">
                            <i class="ph-fill ph-funnel" aria-hidden="true"></i>
                            <span>Lọc:</span>
                        </span>
                        <div class="drv-aif-context__tags" data-aif-context-tags></div>
                        <button type="button" class="drv-aif-context__clear" data-aif-clear-slots>Đặt lại</button>
                    </div>

                    <!-- MESSAGE STREAM -->
                    <div class="drv-aif-messages" role="log" aria-live="polite" data-aif-messages>
                        <div class="drv-aif-msg drv-aif-msg--bot" data-aif-welcome>
                            <div class="drv-aif-msg__avatar" aria-hidden="true">
                                <i class="ph-fill ph-sparkle"></i>
                            </div>
                            <div class="drv-aif-msg__bubble">
                                <div class="drv-aif-msg__sender">Cố vấn kỹ thuật Dravit</div>
                                <p class="drv-aif-msg__text">
                                    Mô tả <strong>không gian</strong>, <strong>diện tích (m²)</strong> hoặc <strong>ngân sách</strong>. Hệ thống tự động khóa chuẩn chống trượt DCOF R9 - R12 và quy đổi chẵn thùng theo quy cách đóng gói thực tế.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- QUICK SCENARIO CHIPS -->
                    <div class="drv-aif-suggestions" data-aif-quick-row aria-label="Kịch bản tìm gạch mẫu">
                        <?php foreach ($quickPrompts as $qp) : ?>
                            <button
                                type="button"
                                class="drv-aif-chip"
                                data-aif-prompt="<?php echo esc_attr($qp['prompt']); ?>"
                            >
                                <i class="ph ph-sparkle" aria-hidden="true"></i>
                                <span><?php echo esc_html($qp['label']); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- INPUT COMPOSER -->
                    <form class="drv-aif-form" data-aif-form novalidate>
                        <div class="drv-aif-hp" aria-hidden="true">
                            <label>
                                Website
                                <input type="text" name="drv_website" tabindex="-1" autocomplete="off" value="">
                            </label>
                        </div>

                        <div class="drv-aif-input-box">
                            <label for="drv-aif-textarea" class="u-sr-only">Nhập nhu cầu tìm gạch</label>
                            <textarea
                                id="drv-aif-textarea"
                                class="drv-aif-input"
                                rows="2"
                                maxlength="500"
                                placeholder="Nhập nhu cầu (VD: Phòng khách 35m², nhà tắm 6m² chống trơn, mã DRV...)..."
                                data-aif-input
                            ></textarea>

                            <div class="drv-aif-form__bar">
                                <label class="drv-aif-waste-toggle" title="Tự động cộng thêm 5% diện tích dự phòng cắt gạch góc cạnh">
                                    <input type="checkbox" value="5" checked data-aif-waste>
                                    <span class="drv-aif-waste-toggle__pill">
                                        <i class="ph-bold ph-check" aria-hidden="true"></i>
                                        <span>+5% hao hụt</span>
                                    </span>
                                </label>

                                <div class="drv-aif-form__right">
                                    <span class="drv-aif-char-count u-tabular" data-aif-char-count>0/500</span>
                                    <button type="submit" class="drv-aif-send-btn" data-aif-submit aria-label="Gửi yêu cầu tư vấn">
                                        <span>Gửi</span>
                                        <i class="ph-bold ph-paper-plane-right" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </section>

                <!-- CỘT PHẢI: ĐỀ XUẤT SẢN PHẨM & TÍNH SỐ THÙNG -->
                <section class="drv-aif-results" aria-label="Bảng đề xuất sản phẩm &amp; tính số thùng">
                    <div class="drv-aif-results__head">
                        <div class="drv-aif-results__head-main">
                            <div class="drv-aif-results__title-row">
                                <h2 class="drv-aif-results__title" data-aif-result-title>Đề xuất &amp; Dự toán khối lượng</h2>
                                <span class="drv-aif-pill drv-aif-pill--teal u-tabular" data-aif-result-pill>
                                    <?php echo esc_html($initialCount . ' mẫu phù hợp'); ?>
                                </span>
                            </div>
                            <p class="drv-aif-results__sub" data-aif-result-sub>
                                Chọn kịch bản bên trái hoặc nhập diện tích (m²) để tính số thùng chẵn
                            </p>
                        </div>

                        <div class="drv-aif-results__actions">
                            <button
                                type="button"
                                class="drv-btn drv-btn--outline drv-btn--sm"
                                data-aif-toggle-refine
                                aria-expanded="false"
                                aria-controls="drv-aif-refine-bar"
                            >
                                <i class="ph-bold ph-sliders-horizontal drv-icon--xs" aria-hidden="true"></i>
                                <span>Lọc thông số</span>
                            </button>
                            <button
                                type="button"
                                class="drv-btn drv-btn--primary drv-btn--sm"
                                data-aif-quote-all
                                <?php echo $initialCount === 0 ? 'hidden' : ''; ?>
                            >
                                <i class="ph-bold ph-plus-circle drv-icon--xs" aria-hidden="true"></i>
                                <span>Báo giá tất cả</span>
                            </button>
                        </div>
                    </div>

                    <!-- TOGGLEABLE QUICK TECHNICAL REFINE DRAWER -->
                    <div id="drv-aif-refine-bar" class="drv-aif-refine" data-aif-refine hidden aria-label="Tinh chỉnh tiêu chí kỹ thuật">
                        <span class="drv-aif-refine__label">Tinh chỉnh nhanh:</span>
                        <div class="drv-aif-refine__chips">
                            <button type="button" class="drv-chip drv-chip--square" data-aif-refine-slot="dcof" data-aif-refine-val="r10">
                                Chống trượt ≥ R10
                            </button>
                            <button type="button" class="drv-chip drv-chip--square" data-aif-refine-slot="dcof" data-aif-refine-val="r11">
                                Khu ướt ≥ R11
                            </button>
                            <button type="button" class="drv-chip drv-chip--square" data-aif-refine-slot="size" data-aif-refine-val="80x80">
                                Khổ lớn 80×80
                            </button>
                            <button type="button" class="drv-chip drv-chip--square" data-aif-refine-slot="surface" data-aif-refine-val="mo-matt">
                                Bề mặt Mờ (Matt)
                            </button>
                            <button type="button" class="drv-chip drv-chip--square" data-aif-refine-slot="budget_max_vnd" data-aif-refine-val="400000">
                                ≤ 400.000đ/m²
                            </button>
                            <button type="button" class="drv-chip drv-chip--square" data-aif-refine-slot="showroom_only" data-aif-refine-val="1">
                                Sẵn mẫu Showroom
                            </button>
                        </div>
                    </div>

                    <!-- SCROLLABLE RESULTS BODY -->
                    <div class="drv-aif-results__scroll" data-aif-body>
                        <!-- AI EXPLANATION & TRADEOFF BANNER -->
                        <div class="drv-aif-explain" data-aif-explain>
                            <div class="drv-aif-explain__icon" aria-hidden="true">
                                <i class="ph-fill ph-sparkle"></i>
                            </div>
                            <div class="drv-aif-explain__content">
                                <p class="drv-aif-explain__text" data-aif-explain-text><?php echo esc_html($initialSummary); ?></p>
                                <div class="drv-aif-tradeoff" data-aif-tradeoff hidden>
                                    <i class="ph-fill ph-warning-circle" aria-hidden="true"></i>
                                    <p data-aif-tradeoff-text></p>
                                </div>
                                <div class="drv-aif-kb-sources" data-aif-kb hidden></div>
                            </div>
                        </div>

                        <!-- SKELETON SHIMMER GRID -->
                        <div class="drv-aif-product-grid" data-aif-skeleton hidden aria-hidden="true">
                            <div class="drv-aif-skeleton-card"></div>
                            <div class="drv-aif-skeleton-card"></div>
                            <div class="drv-aif-skeleton-card"></div>
                            <div class="drv-aif-skeleton-card"></div>
                        </div>

                        <!-- EMPTY / HANDOFF STATE -->
                        <div class="drv-aif-empty" data-aif-empty <?php echo $initialHtml !== '' ? 'hidden' : ''; ?>>
                            <div class="drv-aif-empty__icon" aria-hidden="true">
                                <i class="ph-fill ph-sparkle"></i>
                            </div>
                            <h3 class="drv-aif-empty__title" data-aif-empty-title>Chưa có mẫu khớp đồng thời mọi điều kiện</h3>
                            <p class="drv-aif-empty__desc" data-aif-empty-desc>
                                Bạn có thể nới bớt một tiêu chí lọc hoặc kết nối trực tiếp kỹ thuật viên DRAVIT để tra cứu lô hàng dự án.
                            </p>
                            <div class="drv-aif-empty__actions">
                                <a class="drv-btn drv-btn--primary drv-btn--sm" href="<?php echo esc_url($zaloUrl); ?>" target="_blank" rel="noopener noreferrer" data-aif-zalo-cta>
                                    <i class="ph-fill ph-chat-circle-dots drv-icon--xs" aria-hidden="true"></i>
                                    <span>Nhắn kỹ thuật viên qua Zalo</span>
                                </a>
                                <a class="drv-btn drv-btn--outline drv-btn--sm" href="<?php echo esc_url($boqUrl); ?>">
                                    <i class="ph-bold ph-clipboard-text drv-icon--xs" aria-hidden="true"></i>
                                    <span>Gửi danh sách BOQ</span>
                                </a>
                            </div>
                        </div>

                        <!-- RECOMMENDATION PRODUCT CARDS GRID -->
                        <div class="drv-aif-product-grid" data-aif-grid <?php echo $initialHtml === '' ? 'hidden' : ''; ?>>
                            <?php echo $initialHtml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside Renderer & drv_ui('product-card'). ?>
                        </div>

                        <!-- FEEDBACK & ONE-CLICK REFINEMENT -->
                        <div class="drv-aif-feedback" data-aif-feedback <?php echo $initialCount === 0 ? 'hidden' : ''; ?>>
                            <div class="drv-aif-feedback__row">
                                <span class="drv-aif-feedback__label">
                                    <i class="ph-fill ph-chat-circle-text drv-icon u-text-moss" aria-hidden="true"></i>
                                    <span>Đề xuất này có sát nhu cầu công trình của bạn?</span>
                                </span>
                                <div class="drv-aif-feedback__btns">
                                    <button type="button" class="drv-btn drv-btn--outline drv-btn--sm" data-aif-rate="up">
                                        <i class="ph-bold ph-thumbs-up drv-icon--xs" aria-hidden="true"></i>
                                        <span>Hữu ích</span>
                                    </button>
                                    <button type="button" class="drv-btn drv-btn--outline drv-btn--sm" data-aif-rate="down">
                                        <i class="ph-bold ph-thumbs-down drv-icon--xs" aria-hidden="true"></i>
                                        <span>Cần điều chỉnh</span>
                                    </button>
                                </div>
                            </div>
                            <p class="drv-aif-feedback__thanks" data-aif-feedback-thanks hidden>
                                Cảm ơn đánh giá của bạn! Hệ thống đã ghi nhận để tối ưu độ chính xác.
                            </p>
                            <div class="drv-aif-feedback__reasons" data-aif-feedback-reasons hidden>
                                <button type="button" class="drv-chip drv-chip--square" data-aif-reason="need_higher_slip" data-aif-reason-slot="dcof" data-aif-reason-val="r11">
                                    Chống trơn cao hơn
                                </button>
                                <button type="button" class="drv-chip drv-chip--square" data-aif-reason="wrong_size" data-aif-reason-slot="size" data-aif-reason-val="80x80">
                                    Khổ gạch lớn hơn
                                </button>
                                <button type="button" class="drv-chip drv-chip--square" data-aif-reason="wrong_budget" data-aif-reason-slot="budget_max_vnd" data-aif-reason-val="350000">
                                    Chưa đúng tầm giá
                                </button>
                                <button type="button" class="drv-chip drv-chip--square" data-aif-reason="need_showroom" data-aif-reason-slot="showroom_only" data-aif-reason-val="1">
                                    Sẵn mẫu tại Showroom
                                </button>
                            </div>
                        </div>

                        <p class="drv-aif-disclaimer">
                            Thông số dự toán mang tính tham khảo kỹ thuật dựa trên diện tích nhập vào. Vui lòng đối chiếu bản vẽ thực tế cùng chuyên viên DRAVIT trước khi chốt đơn.
                        </p>
                    </div>
                </section>
            </div>
        </div>

        <!-- NATIVE DIALOG: QUICK TECHNICAL SPECIFICATION MODAL -->
        <dialog class="drv-dialog drv-aif-spec-dialog" data-aif-spec-dialog aria-labelledby="drv-aif-spec-title">
            <div class="drv-dialog__head">
                <div class="drv-dialog__title">
                    <i class="ph-fill ph-shield-check u-text-moss" aria-hidden="true"></i>
                    <span id="drv-aif-spec-title" data-aif-spec-modal-title>Thông số kỹ thuật</span>
                </div>
                <button type="button" class="drv-aif-icon-btn" data-aif-close-spec aria-label="Đóng bảng thông số">
                    <i class="ph-bold ph-x" aria-hidden="true"></i>
                </button>
            </div>
            <div class="drv-dialog__body" data-aif-spec-modal-body></div>
            <div class="drv-dialog__foot drv-aif-spec-dialog__foot">
                <a href="<?php echo esc_url($gachUrl); ?>" class="drv-btn drv-btn--outline drv-btn--sm" data-aif-spec-modal-link>
                    <span>Xem trang sản phẩm</span>
                    <i class="ph ph-arrow-up-right drv-icon--xs" aria-hidden="true"></i>
                </a>
                <button type="button" class="drv-btn drv-btn--primary drv-btn--sm" data-aif-spec-modal-quote>
                    <i class="ph-bold ph-plus-circle drv-icon--xs" aria-hidden="true"></i>
                    <span>Thêm vào báo giá</span>
                </button>
            </div>
        </dialog>

        <!-- NATIVE DIALOG: OFFCANVAS QUOTE DRAWER (SYNCED WITH drv_quote_v2) -->
        <dialog class="drv-dialog drv-dialog--drawer-right drv-qp-dialog" data-aif-quote-dialog aria-labelledby="drv-aif-qp-title">
            <div class="drv-qp__head">
                <div>
                    <h2 id="drv-aif-qp-title" class="drv-qp__title">Danh sách Báo giá &amp; Giữ mẫu</h2>
                    <p class="drv-qp__sub u-tabular" data-aif-qp-summary>0 mẫu · Tổng 0 thùng</p>
                </div>
                <button type="button" class="drv-aif-icon-btn" data-aif-close-quote aria-label="Đóng ngăn báo giá">
                    <i class="ph-bold ph-x" aria-hidden="true"></i>
                </button>
            </div>

            <div class="drv-qp__body" data-aif-qp-list></div>

            <div class="drv-qp__foot">
                <div class="drv-qp__totals">
                    <div>
                        <span class="drv-qp__tot-label">Tổng mẫu chọn</span>
                        <strong class="drv-qp__tot-val u-tabular" data-aif-qp-skus>0 SKU</strong>
                    </div>
                    <div>
                        <span class="drv-qp__tot-label">Tổng diện tích phủ</span>
                        <strong class="drv-qp__tot-val u-tabular" data-aif-qp-m2>0 m²</strong>
                    </div>
                    <div class="drv-qp__tot-right">
                        <span class="drv-qp__tot-label">Tổng quy đổi</span>
                        <strong class="drv-qp__tot-boxes u-tabular" data-aif-qp-boxes>0 thùng</strong>
                    </div>
                </div>

                <a href="<?php echo esc_url($boqUrl); ?>" class="drv-btn drv-btn--primary drv-qp__cta">
                    <i class="ph-bold ph-paper-plane-tilt drv-icon--xs" aria-hidden="true"></i>
                    <span>Mở bảng Dự toán BOQ &amp; Gửi yêu cầu</span>
                </a>

                <button type="button" class="drv-qp__clear-btn" data-aif-qp-clear hidden>
                    <i class="ph-bold ph-trash" aria-hidden="true"></i>
                    <span>Xóa toàn bộ danh sách</span>
                </button>
            </div>
        </dialog>
    </drv-ai-finder>
</section>
