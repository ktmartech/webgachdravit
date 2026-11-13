<?php
declare(strict_types=1);

use Dravit\Seo\FaqResolver;

defined('ABSPATH') || exit;

/**
 * Section Câu hỏi thường gặp chuẩn hóa (`drv_section('faq-section')`, docs/06 §5.3, Test M-14):
 * Đồng bộ 1-1 giữa HTML hiển thị và thực thể `FAQPage` trong JSON-LD `@graph`.
 *
 * @var array<string, mixed> $args
 */
$raw_items = isset($args['items']) && is_array($args['items']) && $args['items'] !== []
    ? FaqResolver::parseFaqItems($args['items'])
    : FaqResolver::resolveForCurrentView();

if ($raw_items === []) {
    return;
}

$eyebrow    = trim((string) ($args['eyebrow'] ?? 'Giải đáp kỹ thuật'));
$title      = trim((string) ($args['title'] ?? 'Câu hỏi thường gặp khi chọn gạch & tính khối lượng'));
$desc       = trim((string) ($args['desc'] ?? ''));
$open_first = array_key_exists('open_first', $args) ? (bool) $args['open_first'] : true;
?>
<section id="cau-hoi-thuong-gap" class="l-section drv-faq-section" aria-labelledby="drv-faq-sec-heading">
    <div class="l-container l-stack l-stack--lg">
        <div class="drv-sec-head">
            <div class="l-stack l-stack--xs">
                <?php if ($eyebrow !== '') : ?>
                    <span class="drv-sec-head__eyebrow"><?php echo esc_html($eyebrow); ?></span>
                <?php endif; ?>
                <h2 id="drv-faq-sec-heading"><?php echo esc_html($title); ?></h2>
                <?php if ($desc !== '') : ?>
                    <p class="drv-sec-head__desc"><?php echo esc_html($desc); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php
        drv_ui('faq', [
            'items'           => $raw_items,
            'open_first'      => $open_first,
            'register_schema' => true,
        ]);
        ?>
    </div>
</section>

