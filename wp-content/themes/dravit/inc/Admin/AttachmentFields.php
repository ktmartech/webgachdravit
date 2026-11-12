<?php
declare(strict_types=1);

namespace Dravit\Admin;

use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Bổ sung 2 trường metadata `drv_img_role` và `drv_img_tag` trong Media Library (docs/14 §3).
 */
final class AttachmentFields implements ServiceProviderInterface {
    private const ALLOWED_ROLES = [
        ''         => 'Mặc định (Tự động)',
        'room'     => 'Phối cảnh phòng thực tế (room)',
        'face'     => 'Mặt vân gạch phẳng (face)',
        'light'    => 'Cận cảnh ánh sáng & bề mặt men (light)',
        'grout'    => 'Chi tiết mạch ron & cạnh gạch (grout)',
        'showroom' => 'Ảnh chụp thực tế tại kệ Showroom (showroom)',
        'edge'     => 'Cận cảnh xương gạch & độ dày (edge)',
    ];

    public function register(): void {
        add_filter('attachment_fields_to_edit', [$this, 'add_fields'], 10, 2);
        add_filter('attachment_fields_to_save', [$this, 'save_fields'], 10, 2);
    }

    /**
     * @param array<string, mixed> $form_fields
     * @return array<string, mixed>
     */
    public function add_fields(array $form_fields, \WP_Post $post): array {
        $role = (string) get_post_meta($post->ID, 'drv_img_role', true);
        $tag  = (string) get_post_meta($post->ID, 'drv_img_tag', true);

        $select_html = '<select name="attachments[' . (int) $post->ID . '][drv_img_role]" id="attachments-' . (int) $post->ID . '-drv_img_role">';
        foreach (self::ALLOWED_ROLES as $val => $label) {
            $selected     = ($role === $val) ? ' selected="selected"' : '';
            $select_html .= '<option value="' . esc_attr($val) . '"' . $selected . '>' . esc_html($label) . '</option>';
        }
        $select_html .= '</select>';

        $form_fields['drv_img_role'] = [
            'label' => 'Vai trò ảnh (Dravit)',
            'input' => 'html',
            'html'  => $select_html,
        ];

        $form_fields['drv_img_tag'] = [
            'label' => 'Nhãn góc ảnh (≤ 30 ký tự)',
            'input' => 'text',
            'value' => $tag,
            'helps' => 'VD: Phối cảnh phòng khách 28 m², Chụp tại kệ A-01...',
        ];

        return $form_fields;
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $attachment
     * @return array<string, mixed>
     */
    public function save_fields(array $post, array $attachment): array {
        $post_id = isset($post['ID']) ? (int) $post['ID'] : 0;
        if ($post_id <= 0 || !current_user_can('edit_post', $post_id)) {
            return $post;
        }

        if (isset($attachment['drv_img_role'])) {
            $role = sanitize_key((string) $attachment['drv_img_role']);
            if (!array_key_exists($role, self::ALLOWED_ROLES)) {
                $role = '';
            }
            update_post_meta($post_id, 'drv_img_role', $role);
        }

        if (isset($attachment['drv_img_tag'])) {
            $raw_tag = sanitize_text_field((string) $attachment['drv_img_tag']);
            $tag     = function_exists('mb_substr') ? mb_substr($raw_tag, 0, 30) : substr($raw_tag, 0, 30);
            update_post_meta($post_id, 'drv_img_tag', $tag);
        }

        return $post;
    }
}

