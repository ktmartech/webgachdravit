<?php
declare(strict_types=1);

use Dravit\Core\Template;

defined('ABSPATH') || exit;

/**
 * Bảng thông số kỹ thuật 2 tầng (`drv_ui('spec-table')`, `IL-03` `P-2`) + 4 cam kết dịch vụ + Lưới mặt vân + 3 Lưu ý thi công (`03-product-single.html`).
 *
 * @var array<string, mixed> $args
 */
$view       = isset($args['view']) && is_array($args['view']) ? $args['view'] : [];
$specs_core = isset($view['specs_core']) && is_array($view['specs_core']) ? $view['specs_core'] : [];
$specs_ext  = isset($view['specs_ext']) && is_array($view['specs_ext']) ? $view['specs_ext'] : [];
$faces      = isset($view['faces']) && is_array($view['faces']) ? $view['faces'] : [];
$notes      = isset($view['notes']) && is_array($view['notes']) ? $view['notes'] : [];
$title      = (string) ($view['title'] ?? 'Mẫu gạch DRAVIT');
$sku        = (string) ($view['sku'] ?? '');
$id         = (int) ($view['id'] ?? 0);
$default_m2 = (float) ($view['default_m2'] ?? 10.0);
$init_boxes = (string) ($view['init_calc']['boxText'] ?? '1 thùng');

$has_more = ($specs_ext !== [] || $faces !== [] || $notes !== []);

/**
 * Danh sách thông số dạng `ul.drv-pdp-spec-list` (mockup 03). `value_html` đã escape + gắn link IL-03 trong ProductViewData.
 *
 * @param array<int, array{label?: string, value_html?: string}> $rows
 */
$render_spec_list = static function (array $rows, string $aria_label): void {
    if ($rows === []) {
        return;
    }
    echo '<ul class="drv-pdp-spec-list" aria-label="' . esc_attr($aria_label) . '">';
    foreach ($rows as $row) {
        $label = trim((string) ($row['label'] ?? ''));
        $value = trim((string) ($row['value_html'] ?? ''));
        if ($label === '' || $value === '') {
            continue;
        }
        echo '<li class="drv-pdp-spec-item">';
        echo '<span class="drv-pdp-spec-label">' . esc_html($label) . ':</span>';
        echo '<span class="drv-pdp-spec-val">' . wp_kses_post($value) . '</span>';
        echo '</li>';
    }
    echo '</ul>';
};
?>
<section id="sec-specs" class="drv-pdp-spec-box" aria-labelledby="pdp-spec-heading">
    <h2 id="pdp-spec-heading" class="u-sr-only">Thông số kỹ thuật và cam kết dịch vụ</h2>

    <div class="drv-pdp-perks">
        <button type="button"
                class="drv-pdp-perk"
                data-drv-action="pdp-quote-modal"
                data-pdp-quote-modal-open
                data-product-id="<?php echo esc_attr((string) $id); ?>"
                data-drv-sku="<?php echo esc_attr($sku); ?>"
                data-drv-title="<?php echo esc_attr($title); ?>"
                data-drv-m2="<?php echo esc_attr((string) $default_m2); ?>"
                data-drv-boxes-text="<?php echo esc_attr($init_boxes); ?>">
            <span class="drv-pdp-perk__icon" aria-hidden="true">
                <span class="drv-pdp-perk__badge">Free</span>
                <i class="ph-fill ph-cube"></i>
            </span>
            <span>Bóc tách khối lượng <i class="ph ph-caret-right" aria-hidden="true"></i></span>
        </button>

        <div class="drv-pdp-perk">
            <span class="drv-pdp-perk__icon" aria-hidden="true">
                <i class="ph-fill ph-truck"></i>
            </span>
            <span>Giao xe tải toàn quốc</span>
        </div>

        <div class="drv-pdp-perk">
            <span class="drv-pdp-perk__icon" aria-hidden="true">
                <i class="ph-fill ph-package"></i>
            </span>
            <span>Xuất nguyên thùng đồng lô</span>
        </div>

        <div class="drv-pdp-perk">
            <span class="drv-pdp-perk__icon" aria-hidden="true">
                <i class="ph-fill ph-shield-check"></i>
            </span>
            <span>Đổi bù vỡ hỏng khi giao</span>
        </div>
    </div>

    <?php $render_spec_list($specs_core, 'Thông số kỹ thuật cốt lõi của ' . $title); ?>

    <?php if ($has_more) : ?>
        <details class="drv-pdp-spec-more">
            <summary class="drv-pdp-spec-more__summary">
                <span class="drv-pdp-spec-more__btn">
                    <span class="drv-pdp-spec-more__text" data-spec-more-label>Xem tất cả</span>
                    <i class="ph-bold ph-caret-down drv-icon--xs drv-pdp-spec-more__caret" aria-hidden="true"></i>
                </span>
            </summary>

            <div class="drv-pdp-spec-more__content">
                <?php $render_spec_list($specs_ext, 'Thông số cơ lý mở rộng của ' . $title); ?>

                <?php
                Template::render_part('template-parts/product/faces-grid', [
                    'view' => $view,
                ]);

                Template::render_part('template-parts/product/notes', [
                    'notes' => $notes,
                ]);
                ?>
            </div>
        </details>
    <?php endif; ?>
</section>
