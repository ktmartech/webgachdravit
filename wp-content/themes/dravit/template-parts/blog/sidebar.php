<?php
declare(strict_types=1);

use Dravit\Home\HomeData;

defined('ABSPATH') || exit;

/**
 * Cột phải bài viết (mockup `08-blog-single.html`): thẻ Showroom tĩnh + nhóm Sticky (Mục lục có thanh tiến độ, CTA Trợ lý AI).
 *
 * @var array<string, mixed> $args
 */
$toc_items  = isset($args['toc_items']) && is_array($args['toc_items']) ? $args['toc_items'] : [];
$ai_enabled = (bool) drv_setting('features.ai_enabled', true);

$boq_url     = HomeData::siteUrl('/danh-sach-bao-gia');
$gach_url    = HomeData::siteUrl('/gach');


$quick_prompts = [
    ['Phòng khách 30m² cần bao nhiêu thùng?', 'Phòng khách 30m2 cần bao nhiêu thùng gạch và bao nhiêu keo dán C2?'],
    ['Gạch nào hợp lát nhà tắm?', 'Nhà tắm nên chọn gạch bề mặt nào để chống trơn?'],
    ['Cần bao nhiêu keo dán và keo ron?', 'Cần bao nhiêu keo dán C2 và keo ron cho 30m2 gạch 80x80?'],
];
?>
<aside class="drv-blog-sidebar" aria-label="Công cụ tra cứu và Mục lục bài viết">
    <div class="drv-sidebar-sticky-group">
        <?php if ($toc_items !== []) : ?>
            <?php get_template_part('template-parts/blog/toc', null, ['items' => $toc_items, 'mode' => 'desktop']); ?>
        <?php endif; ?>

        <div class="drv-sidebar-widget drv-ai-cta" id="quick-ai" role="region" aria-label="<?php echo $ai_enabled ? 'Chat với Trợ lý AI Dravit' : 'Dự toán BOQ công trình'; ?>">
            <?php if ($ai_enabled) : ?>
                <div class="drv-sidebar-widget__head">
                    <span style="display: inline-flex; align-items: center; gap: 6px;">
                        <i class="ph-fill ph-robot drv-icon--xs u-text-moss" aria-hidden="true"></i>
                        <span>Hỏi Trợ lý AI Dravit</span>
                    </span>
                    <span class="drv-badge drv-badge--outline" style="font-size: 12px;"><span class="drv-ai-cta__dot" aria-hidden="true"></span>Trực tuyến</span>
                </div>
                <div class="drv-sidebar-widget__body">
                    <p class="drv-ai-cta__lead">Cho biết diện tích và không gian cần lát. Trợ lý gợi ý khổ gạch, bề mặt chống trượt và tính sẵn số thùng, keo dán.</p>

                    <ul class="drv-ai-cta__prompts" role="list">
                        <?php foreach ($quick_prompts as [$label, $prompt]) : ?>
                            <li>
                                <button type="button" class="drv-chip drv-chip--square" data-drv-action="ai-finder-open" data-drv-prompt="<?php echo esc_attr($prompt); ?>">
                                    <i class="ph ph-chat-circle-text drv-icon--xs" aria-hidden="true"></i>
                                    <span><?php echo esc_html($label); ?></span>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div style="display: grid; grid-template-columns: 1fr; gap: 6px;">
                        <button type="button" class="drv-btn drv-btn--moss drv-btn--sm" data-drv-action="ai-finder-open" data-drv-prompt="Tôi vừa đọc một bài trong Cẩm nang, cần tư vấn chọn gạch và tính khối lượng cho công trình." style="width: 100%; justify-content: center;">
                            <i class="ph-fill ph-robot drv-icon--xs" aria-hidden="true"></i>
                            <span>Chat với AI tư vấn</span>
                        </button>
                        <a href="<?php echo esc_url($gach_url); ?>" class="drv-btn drv-btn--outline drv-btn--sm" style="width: 100%; justify-content: center;">
                            <i class="ph ph-squares-four drv-icon--xs" aria-hidden="true"></i>
                            <span>Xem toàn bộ kho gạch</span>
                        </a>
                    </div>
                </div>
            <?php else : ?>
                <div class="drv-sidebar-widget__head">
                    <span style="display: inline-flex; align-items: center; gap: 6px;">
                        <i class="ph-fill ph-calculator drv-icon--xs u-text-moss" aria-hidden="true"></i>
                        <span>Dự toán BOQ miễn phí</span>
                    </span>
                </div>
                <div class="drv-sidebar-widget__body">
                    <p class="drv-ai-cta__lead">Thêm các mã gạch đang cân nhắc vào bảng dự toán theo từng phòng, quy đổi thùng nguyên và xuất báo giá nhanh.</p>
                    <a href="<?php echo esc_url($boq_url); ?>" class="drv-btn drv-btn--moss drv-btn--sm" style="width: 100%; justify-content: center;">
                        <i class="ph-bold ph-clipboard-text drv-icon--xs" aria-hidden="true"></i>
                        <span>Lập bảng dự toán BOQ</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</aside>
