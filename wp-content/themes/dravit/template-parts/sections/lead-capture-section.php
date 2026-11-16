<?php
/**
 * Section: Khối thu Lead tái sử dụng (Showroom booking, Cẩm nang, Landing) – Dual-Mode D-31.
 * Props: form_slot (slot `drv_settings.forms`), lead_type (nguồn native), title, sub, fields, product_id, post_id, surface.
 *
 * @var array<string, mixed> $args
 */
declare(strict_types=1);

use Dravit\Core\Integrations\FluentFormsBridge;

defined('ABSPATH') || exit;

$slot    = sanitize_key((string) ($args['form_slot'] ?? 'contact_page'));
$type    = sanitize_key((string) ($args['lead_type'] ?? 'contact'));
$title   = (string) ($args['title'] ?? 'Nhận tư vấn & báo giá từ chuyên viên DRAVIT');
$sub     = (string) ($args['sub'] ?? (string) drv_setting('order.sla_text', ''));
$surface = sanitize_html_class((string) ($args['surface'] ?? 'ivory'));
$fields  = isset($args['fields']) && is_array($args['fields']) ? $args['fields'] : ['email', 'province', 'message'];
$anchor  = sanitize_html_class((string) ($args['anchor'] ?? 'drv-lead-' . $slot));
?>
<section class="l-section l-section--<?php echo esc_attr($surface); ?>" id="<?php echo esc_attr($anchor); ?>" aria-labelledby="<?php echo esc_attr($anchor . '-title'); ?>">
    <div class="l-container">
        <div class="drv-contact-form-card drv-lead-section__card">
            <div class="drv-contact-block__head">
                <i class="ph-fill ph-paper-plane-tilt drv-icon u-text-moss" aria-hidden="true"></i>
                <h2 id="<?php echo esc_attr($anchor . '-title'); ?>" class="drv-contact-block__title"><?php echo esc_html($title); ?></h2>
            </div>
            <div class="drv-contact-form-card__body l-stack l-stack--sm">
                <?php if ($sub !== '') : ?>
                    <p class="u-text-sm u-text-muted" style="margin: 0"><?php echo esc_html($sub); ?></p>
                <?php endif; ?>
                <?php
                $context = array_filter([
                    'drv_product_id' => !empty($args['product_id']) ? (string) (int) $args['product_id'] : '',
                    'drv_sku'        => (string) ($args['sku'] ?? ''),
                ]);
                if (!FluentFormsBridge::render_slot($slot, $context)) {
                    get_template_part('template-parts/leads/native-form', null, [
                        'source'        => $type,
                        'id_prefix'     => 'lead-' . $slot,
                        'fields'        => $fields,
                        'product_id'    => (int) ($args['product_id'] ?? 0),
                        'post_id'       => (int) ($args['post_id'] ?? 0),
                        'submit_label'  => (string) ($args['submit_label'] ?? 'Gửi yêu cầu tư vấn'),
                        'show_feedback' => true,
                    ]);
                }
                ?>
            </div>
        </div>
    </div>
</section>
