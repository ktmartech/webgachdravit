<?php
declare(strict_types=1);

namespace Dravit\Core\Integrations;

use Dravit\Core\ServiceProviderInterface;
use Dravit\Core\Settings;

defined('ABSPATH') || exit;

/**
 * Cầu nối tích hợp Dual-Mode giữa 5 slot form báo giá/tư vấn và Fluent Forms (ADR D-31).
 */
final class FluentFormsBridge implements ServiceProviderInterface {
    public function register(): void {
        add_filter('fluentform/disable_default_style', '__return_false');
        add_action('fluentform/submission_inserted', [$this, 'on_submission_inserted'], 10, 3);
    }

    /**
     * Trả về ID Fluent Form nếu slot đã được cấu hình trong `drv_settings.forms.{slot}`.
     */
    public static function slot_form_id(string $slot): int {
        $raw = Settings::get('forms.' . $slot, 0);
        return is_numeric($raw) ? max(0, (int) $raw) : 0;
    }

    public static function has_slot_form(string $slot): bool {
        return drv_is_fluent_forms_active() && self::slot_form_id($slot) > 0;
    }

    /**
     * Render shortcode Fluent Forms nếu slot đã được gắn Form ID hợp lệ; trả về `true` nếu đã render.
     */
    public static function render_slot(string $slot): bool {
        if (!self::has_slot_form($slot) || !function_exists('do_shortcode')) {
            return false;
        }

        $form_id = self::slot_form_id($slot);
        $output  = do_shortcode(sprintf('[fluentform id="%d"]', $form_id));
        if (trim($output) === '' || str_contains($output, '[fluentform')) {
            return false;
        }

        echo '<div class="drv-ff-slot" data-drv-ff-slot="' . esc_attr($slot) . '">';
        echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered by Fluent Forms shortcode.
        echo '</div>';
        return true;
    }

    /**
     * Hook mở sẵn cho Step 8: đồng bộ bản ghi từ Fluent Forms ngược về CPT private `drv_lead`.
     *
     * @param int                  $entry_id
     * @param array<string, mixed> $form_data
     * @param object               $form
     */
    public function on_submission_inserted(int $entry_id, array $form_data, object $form): void {
        do_action('drv/integrations/fluentform_submitted', $entry_id, $form_data, $form);
    }
}

