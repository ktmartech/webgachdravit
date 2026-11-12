<?php
declare(strict_types=1);

namespace Dravit\Admin;

use Dravit\Core\Config;
use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Trường cấu hình Term Meta cho 28 Smart Collections (`product_cat`) và 14 thuộc tính `pa_*` (docs/14 §4.1–§4.3).
 * Dùng 100% bảng `form-table` chuẩn của WordPress Admin.
 */
final class TermFields implements ServiceProviderInterface {
    public function register(): void {
        add_action('product_cat_edit_form_fields', [$this, 'render_product_cat_fields'], 20, 1);
        add_action('edited_product_cat', [$this, 'save_product_cat_fields'], 20, 1);
        add_action('admin_init', [$this, 'register_attribute_term_hooks']);
    }

    public function register_attribute_term_hooks(): void {
        $facets = Config::get('facets');
        $attrs  = isset($facets['attributes']) && is_array($facets['attributes']) ? $facets['attributes'] : [];

        foreach ($attrs as $slug => $cfg) {
            if (!is_array($cfg)) {
                continue;
            }
            $tax = (string) ($cfg['taxonomy'] ?? ('pa_' . $slug));
            add_action($tax . '_edit_form_fields', [$this, 'render_facet_term_fields'], 20, 1);
            add_action('edited_' . $tax, [$this, 'save_facet_term_fields'], 20, 1);
        }
    }

    public function render_product_cat_fields(\WP_Term $term): void {
        $tid           = (int) $term->term_id;
        $collection_id = (string) get_term_meta($tid, 'drv_collection_id', true);
        $group         = (string) get_term_meta($tid, 'drv_group', true);
        $h1            = (string) get_term_meta($tid, 'drv_h1', true);
        $intro         = (string) get_term_meta($tid, 'drv_intro', true);
        $hero_image    = (string) get_term_meta($tid, 'drv_hero_image', true);
        $thumb_id      = (string) get_term_meta($tid, 'drv_thumb_id', true);
        $menu_badge    = (string) get_term_meta($tid, 'drv_menu_badge', true);
        $menu_desc     = (string) get_term_meta($tid, 'drv_menu_desc', true);
        $home_spec     = (string) get_term_meta($tid, 'drv_home_spec', true);
        $geo_table     = (string) get_term_meta($tid, 'drv_geo_table', true);
        $faq_json      = (string) get_term_meta($tid, 'drv_faq', true);
        $seo_footer    = (string) get_term_meta($tid, 'drv_seo_footer', true);
        ?>
        <tr class="form-field">
            <th scope="row"><label>Mã Smart Collection</label></th>
            <td>
                <?php wp_nonce_field('drv_save_term_meta', 'drv_term_meta_nonce'); ?>
                <input type="text" class="regular-text" readonly value="<?php echo esc_attr($collection_id !== '' ? $collection_id . ' (' . $group . ')' : 'Danh mục thủ công'); ?>">
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="drv_h1">Tiêu đề H1 trang danh mục</label></th>
            <td>
                <input type="text" name="drv_h1" id="drv_h1" class="large-text" value="<?php echo esc_attr($h1); ?>">
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="drv_intro">Đoạn dẫn nhập ngắn dưới H1</label></th>
            <td>
                <textarea name="drv_intro" id="drv_intro" rows="3" class="large-text"><?php echo esc_textarea($intro); ?></textarea>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="drv_menu_badge">Nhãn Preview Mega Menu</label></th>
            <td>
                <input type="text" name="drv_menu_badge" id="drv_menu_badge" class="regular-text" value="<?php echo esc_attr($menu_badge); ?>">
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="drv_menu_desc">Mô tả Preview Mega Menu</label></th>
            <td>
                <textarea name="drv_menu_desc" id="drv_menu_desc" rows="2" class="large-text"><?php echo esc_textarea($menu_desc); ?></textarea>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="drv_home_spec">Thông số ngắn trên Bento Trang chủ</label></th>
            <td>
                <input type="text" name="drv_home_spec" id="drv_home_spec" class="regular-text" value="<?php echo esc_attr($home_spec); ?>">
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="drv_hero_image">Attachment ID ảnh Hero danh mục</label></th>
            <td>
                <input type="number" name="drv_hero_image" id="drv_hero_image" class="small-text" min="0" value="<?php echo esc_attr($hero_image); ?>">
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="drv_thumb_id">Attachment ID ảnh đại diện vuông</label></th>
            <td>
                <input type="number" name="drv_thumb_id" id="drv_thumb_id" class="small-text" min="0" value="<?php echo esc_attr($thumb_id); ?>">
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="drv_geo_table">Bảng thông số GEO (JSON)</label></th>
            <td>
                <textarea name="drv_geo_table" id="drv_geo_table" rows="4" class="large-text code"><?php echo esc_textarea($geo_table); ?></textarea>
                <p class="description">Mảng JSON các dòng <code>[{"spec":"Độ hút nước","val":"≤ 0,5%"}]</code>.</p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="drv_faq">Câu hỏi thường gặp FAQ (JSON)</label></th>
            <td>
                <textarea name="drv_faq" id="drv_faq" rows="4" class="large-text code"><?php echo esc_textarea($faq_json); ?></textarea>
                <p class="description">Mảng JSON <code>[{"q":"Câu hỏi?","a":"Câu trả lời."}]</code> phục vụ FAQPage Schema.</p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="drv_seo_footer">Bài viết hướng dẫn cuối danh mục (HTML)</label></th>
            <td>
                <textarea name="drv_seo_footer" id="drv_seo_footer" rows="6" class="large-text"><?php echo esc_textarea($seo_footer); ?></textarea>
            </td>
        </tr>
        <?php
    }

    public function save_product_cat_fields(int $term_id): void {
        if (!current_user_can('manage_product_terms')) {
            return;
        }

        $nonce = isset($_POST['drv_term_meta_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['drv_term_meta_nonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'drv_save_term_meta')) {
            return;
        }

        $text_keys = [
            'drv_h1',
            'drv_intro',
            'drv_menu_badge',
            'drv_menu_desc',
            'drv_home_spec',
        ];
        foreach ($text_keys as $key) {
            if (isset($_POST[$key])) {
                update_term_meta($term_id, $key, sanitize_textarea_field(wp_unslash((string) $_POST[$key])));
            }
        }

        foreach (['drv_hero_image', 'drv_thumb_id'] as $id_key) {
            if (isset($_POST[$id_key])) {
                update_term_meta($term_id, $id_key, absint(wp_unslash((string) $_POST[$id_key])));
            }
        }

        foreach (['drv_geo_table', 'drv_faq'] as $json_key) {
            if (!isset($_POST[$json_key])) {
                continue;
            }
            $raw = trim(wp_unslash((string) $_POST[$json_key])); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded & re-encoded right below
            if ($raw === '') {
                update_term_meta($term_id, $json_key, '');
                continue;
            }
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                update_term_meta($term_id, $json_key, (string) wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }

        if (isset($_POST['drv_seo_footer'])) {
            update_term_meta($term_id, 'drv_seo_footer', wp_kses_post(wp_unslash((string) $_POST['drv_seo_footer'])));
        }

        delete_transient('drv_mega_html');
    }

    public function render_facet_term_fields(\WP_Term $term): void {
        $tid          = (int) $term->term_id;
        $filter_label = (string) get_term_meta($tid, 'drv_filter_label', true);
        $short_label  = (string) get_term_meta($tid, 'drv_short_label', true);
        $swatch_hex   = (string) get_term_meta($tid, 'drv_swatch_hex', true);
        $icon         = (string) get_term_meta($tid, 'drv_icon', true);
        ?>
        <tr class="form-field">
            <th scope="row"><label for="drv_filter_label">Nhãn hiển thị trên Bộ lọc</label></th>
            <td>
                <?php wp_nonce_field('drv_save_facet_term_meta', 'drv_facet_term_nonce'); ?>
                <input type="text" name="drv_filter_label" id="drv_filter_label" class="regular-text" value="<?php echo esc_attr($filter_label); ?>">
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="drv_short_label">Nhãn rút gọn trên Thẻ sản phẩm</label></th>
            <td>
                <input type="text" name="drv_short_label" id="drv_short_label" class="regular-text" value="<?php echo esc_attr($short_label); ?>">
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="drv_swatch_hex">Mã màu Swatch (#HEX hoặc multi)</label></th>
            <td>
                <input type="text" name="drv_swatch_hex" id="drv_swatch_hex" class="regular-text" value="<?php echo esc_attr($swatch_hex); ?>">
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="drv_icon">Tên Phosphor Icon (VD: ph-square)</label></th>
            <td>
                <input type="text" name="drv_icon" id="drv_icon" class="regular-text" value="<?php echo esc_attr($icon); ?>">
            </td>
        </tr>
        <?php
    }

    public function save_facet_term_fields(int $term_id): void {
        if (!current_user_can('manage_product_terms')) {
            return;
        }

        $nonce = isset($_POST['drv_facet_term_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['drv_facet_term_nonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'drv_save_facet_term_meta')) {
            return;
        }

        foreach (['drv_filter_label', 'drv_short_label', 'drv_swatch_hex', 'drv_icon'] as $key) {
            if (isset($_POST[$key])) {
                update_term_meta($term_id, $key, sanitize_text_field(wp_unslash((string) $_POST[$key])));
            }
        }
    }
}

