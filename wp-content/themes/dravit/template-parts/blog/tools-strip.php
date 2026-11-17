<?php
declare(strict_types=1);

use Dravit\Content\BlogSetup;
use Dravit\Home\HomeData;

defined('ABSPATH') || exit;

/**
 * Khối "Công cụ tra cứu nhanh" (mockup `07-blog-archive.html` Section 7): Hỏi Trợ lý AI + Bảng chống trượt R9 - R13.
 * Khi `features.ai_enabled = false` thẻ trái chuyển sang Dự toán BOQ (D-32).
 *
 * @var array<string, mixed> $args
 */
$r_rows     = isset($args['r_rows']) && is_array($args['r_rows']) && $args['r_rows'] !== []
    ? $args['r_rows']
    : BlogSetup::getAntiSlipReferenceRows();
$ai_enabled = (bool) drv_setting('features.ai_enabled', true);
$boq_url    = HomeData::siteUrl('/danh-sach-bao-gia');
$anti_url   = '';
foreach ($r_rows as $row) {
    if (strtoupper((string) ($row['code'] ?? '')) === 'R10') {
        $anti_url = (string) ($row['collection_url'] ?? '');
        break;
    }
}
if ($anti_url === '') {
    $anti_url = HomeData::siteUrl('/gach');
}

$quick_prompts = [
    ['Phòng khách 25m² cần bao nhiêu thùng?', 'Phòng khách 25m2 nên chọn gạch khổ nào và cần bao nhiêu thùng?'],
    ['Nhà tắm chọn bề mặt nào không trơn?', 'Nhà tắm nên chọn gạch bề mặt nào để không bị trơn?'],
    ['Gạch sân vườn chống trượt', 'Tư vấn gạch sân vườn 40m2 chống trượt, ngân sách vừa phải.'],
];
?>
<section id="quick-tools" class="l-section l-section--ivory u-bd-b" style="padding-block: var(--s-5)">
    <div class="l-container">
        <header class="drv-topic-head">
            <h2>Công cụ tra cứu nhanh</h2>
            <span class="u-text-xs u-text-muted u-hide-mobile">Hỏi AI hoặc tra nhanh bảng chỉ số chống trượt</span>
        </header>

        <div class="drv-blog-tools-grid">
            <div id="quick-ai" class="drv-tool-card drv-ai-cta" role="region" aria-label="<?php echo $ai_enabled ? 'Chat với Trợ lý AI Dravit' : 'Dự toán BOQ công trình'; ?>">
                <?php if ($ai_enabled) : ?>
                    <div class="drv-tool-card__head">
                        <div class="l-cluster l-cluster--xs">
                            <i class="ph-fill ph-robot u-text-moss drv-icon--sm" aria-hidden="true"></i>
                            <h3 class="u-text-base u-bold u-text-navy" style="margin:0">Hỏi Trợ lý AI Dravit</h3>
                        </div>
                        <span class="drv-badge drv-badge--outline"><span class="drv-ai-cta__dot" aria-hidden="true"></span>Trực tuyến</span>
                    </div>

                    <div class="drv-tool-card__body">
                        <p class="drv-ai-cta__lead">Nói diện tích và không gian cần lát. Trợ lý sẽ gợi ý khổ gạch, bề mặt chống trượt và tính sẵn số thùng cho bạn.</p>

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

                        <div class="l-cluster l-cluster--between" style="margin-top: auto">
                            <button type="button" class="drv-btn drv-btn--moss" data-drv-action="ai-finder-open" data-drv-prompt="Tôi cần tư vấn chọn gạch và tính số thùng cho công trình.">
                                <i class="ph-fill ph-robot drv-icon--sm" aria-hidden="true"></i>
                                <span>Chat với AI tư vấn</span>
                            </button>
                            <a href="<?php echo esc_url($boq_url); ?>" class="drv-btn drv-btn--outline drv-btn--sm">
                                <i class="ph ph-file-text drv-icon--xs" aria-hidden="true"></i>
                                <span>Nhận báo giá công trình</span>
                            </a>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="drv-tool-card__head">
                        <div class="l-cluster l-cluster--xs">
                            <i class="ph-fill ph-calculator u-text-moss drv-icon--sm" aria-hidden="true"></i>
                            <h3 class="u-text-base u-bold u-text-navy" style="margin:0">Dự toán khối lượng theo phòng</h3>
                        </div>
                    </div>
                    <div class="drv-tool-card__body">
                        <p class="drv-ai-cta__lead">Quy đổi diện tích m² cộng hao hụt 5 - 10% ra số thùng nguyên, tính tổng trọng lượng để điều phối xe tải phù hợp.</p>
                        <div class="l-cluster l-cluster--between" style="margin-top: auto">
                            <a href="<?php echo esc_url($boq_url); ?>" class="drv-btn drv-btn--moss">
                                <i class="ph-bold ph-clipboard-text drv-icon--sm" aria-hidden="true"></i>
                                <span>Mở bảng dự toán BOQ</span>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="drv-tool-card" role="region" aria-label="Bảng tra cứu chỉ số chống trượt R9 đến R13">
                <div class="drv-tool-card__head">
                    <div class="l-cluster l-cluster--xs">
                        <i class="ph-bold ph-shield-check u-text-teal drv-icon--sm" aria-hidden="true"></i>
                        <h3 class="u-text-base u-bold u-text-navy" style="margin:0">Bảng chỉ số chống trượt R9 – R13 (DIN 51130)</h3>
                    </div>
                </div>

                <div class="drv-tool-card__body">
                    <div style="overflow-x: auto">
                        <table class="drv-r-table">
                            <thead>
                                <tr>
                                    <th scope="col">Chỉ số</th>
                                    <th scope="col">Góc dốc</th>
                                    <th scope="col">Bề mặt</th>
                                    <th scope="col">Khu vực sử dụng phù hợp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($r_rows as $row) :
                                    $code    = (string) ($row['code'] ?? '');
                                    $code_lc = strtolower($code);
                                    ?>
                                    <tr>
                                        <td><span class="drv-r-badge drv-r-badge--<?php echo esc_attr($code_lc); ?>"><?php echo esc_html($code); ?></span></td>
                                        <td class="u-tabular"><?php echo esc_html((string) ($row['angle'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ($row['surface'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ($row['area'] ?? '')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="l-cluster l-cluster--between" style="margin-top: auto; padding-top: var(--s-1)">
                        <span class="u-text-xs u-text-muted">Nhà tắm gia đình khuyến nghị chọn từ <strong>R10</strong> trở lên.</span>
                        <a href="<?php echo esc_url($anti_url); ?>" class="drv-btn drv-btn--outline drv-btn--sm">
                            <span>Xem gạch chống trơn R10–R11</span>
                            <i class="ph ph-arrow-right drv-icon--xs" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
