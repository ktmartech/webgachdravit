<?php
declare(strict_types=1);

namespace Dravit\Admin;

use Dravit\Catalog\SearchTrending;
use Dravit\Core\Config;
use Dravit\Core\ServiceProviderInterface;
use Dravit\Core\Settings;

defined('ABSPATH') || exit;

/**
 * Trang cấu hình tập trung `drv_settings` 9 Tabs trong `Giao diện -> Cài đặt Dravit` (docs/14 §8, Step 1).
 */
final class SettingsPage implements ServiceProviderInterface {
    public const MENU_SLUG = 'drv-settings';

    public function register(): void {
        if (!is_admin()) {
            return;
        }
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_setting']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function enqueue_admin_assets(string $hook_suffix): void {
        if ($hook_suffix !== 'appearance_page_' . self::MENU_SLUG) {
            return;
        }
        wp_enqueue_media();
        wp_add_inline_script('media-editor', $this->get_media_picker_script());

        if (drv_is_woo_active()) {
            wp_enqueue_style('woocommerce_admin_styles');
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_script('wc-enhanced-select');
            wp_add_inline_script('wc-enhanced-select', $this->get_search_pin_guard_script());
        } else {
            wp_add_inline_script('media-editor', $this->get_search_pin_guard_script());
        }
    }

    public function register_menu(): void {
        $cap = current_user_can('manage_woocommerce') ? 'manage_woocommerce' : 'manage_options';
        add_theme_page(
            'Cài đặt Hệ thống DRAVIT',
            'Cài đặt Dravit',
            $cap,
            self::MENU_SLUG,
            [$this, 'render_page']
        );
    }

    public function register_setting(): void {
        register_setting(
            'drv_settings_group',
            Settings::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize_callback'],
                'default'           => Settings::defaults(),
            ]
        );
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    public function sanitize_callback(mixed $raw): array {
        $input = is_array($raw) ? $raw : [];

        if (isset($input['search']['pinned_product_ids']) && is_array($input['search']['pinned_product_ids']) && function_exists('add_settings_error')) {
            $submitted_pinned = [];
            foreach ($input['search']['pinned_product_ids'] as $raw_pid) {
                $pid = max(0, (int) $raw_pid);
                if ($pid > 0 && !in_array($pid, $submitted_pinned, true)) {
                    $submitted_pinned[] = $pid;
                }
            }
            if (count($submitted_pinned) > SearchTrending::MAX_PINNED) {
                add_settings_error(
                    Settings::OPTION_KEY,
                    'drv_pinned_overflow',
                    sprintf(
                        'Chỉ cho phép ghim tối đa %d sản phẩm lên gợi ý tìm kiếm. Hệ thống đã giữ %d sản phẩm đầu tiên và bỏ qua phần vượt quá.',
                        SearchTrending::MAX_PINNED,
                        SearchTrending::MAX_PINNED
                    ),
                    'warning'
                );
            }
        }

        if (isset($input['search']['featured_silos']) && is_array($input['search']['featured_silos']) && function_exists('add_settings_error')) {
            $submitted_silos = array_values(array_unique(array_filter(array_map('strval', $input['search']['featured_silos']))));
            if (count($submitted_silos) > 6) {
                add_settings_error(
                    Settings::OPTION_KEY,
                    'drv_silos_overflow',
                    'Cột Danh mục phổ biến chỉ hiển thị tối đa 6 danh mục. Hệ thống đã giữ 6 danh mục đầu tiên.',
                    'warning'
                );
            }
        }

        $clean = Settings::sanitize($input);
        $clean = $this->handle_logo_uploads($clean);

        if (isset($input['company']) && is_array($input['company']) && array_key_exists('header_logo_id', $input['company']) && function_exists('set_theme_mod')) {
            $header_id = (int) ($clean['company']['header_logo_id'] ?? 0);
            if ($header_id > 0) {
                set_theme_mod('custom_logo', $header_id);
            } elseif (function_exists('remove_theme_mod')) {
                remove_theme_mod('custom_logo');
            }
        }

        return $clean;
    }

    /**
     * Xử lý tải file logo trực tiếp từ máy tính và lưu vào WP Media Library.
     *
     * @param array<string, mixed> $clean
     * @return array<string, mixed>
     */
    private function handle_logo_uploads(array $clean): array {
        if (!is_admin() || !current_user_can('upload_files')) {
            return $clean;
        }
        if (!isset($_POST['option_page']) || sanitize_key((string) wp_unslash($_POST['option_page'])) !== 'drv_settings_group') {
            return $clean;
        }
        check_admin_referer('drv_settings_group-options');

        $upload_map = [
            'drv_upload_header_logo' => 'header_logo_id',
            'drv_upload_footer_logo' => 'footer_logo_id',
        ];

        foreach ($upload_map as $file_field => $setting_key) {
            if (!isset($_FILES[$file_field]['error'], $_FILES[$file_field]['name'])) {
                continue;
            }
            $error_code = (int) $_FILES[$file_field]['error'];
            if ($error_code === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $file_name = sanitize_file_name((string) wp_unslash($_FILES[$file_field]['name']));
            if ($error_code !== UPLOAD_ERR_OK || $file_name === '') {
                add_settings_error(Settings::OPTION_KEY, $file_field . '_err', 'Không thể tải file logo lên. Vui lòng kiểm tra dung lượng tệp.');
                continue;
            }

            if (!function_exists('media_handle_upload')) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
            }

            $attachment_id = media_handle_upload($file_field, 0);
            if (is_wp_error($attachment_id)) {
                add_settings_error(Settings::OPTION_KEY, $file_field . '_wp_err', $attachment_id->get_error_message());
                continue;
            }

            if (!wp_attachment_is_image($attachment_id)) {
                wp_delete_attachment($attachment_id, true);
                add_settings_error(Settings::OPTION_KEY, $file_field . '_mime', 'Tệp tải lên phải là định dạng hình ảnh hợp lệ (WebP, PNG, SVG, JPG).');
                continue;
            }

            if (!isset($clean['company']) || !is_array($clean['company'])) {
                $clean['company'] = Settings::defaults()['company'];
            }
            $clean['company'][$setting_key] = (int) $attachment_id;
        }

        return $clean;
    }

    public function render_page(): void {
        if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
            wp_die('Bạn không có quyền truy cập trang này.');
        }

        $tabs = [
            'company' => '1. Công ty & Pháp lý',
            'contact' => '2. Liên hệ & Showroom',
            'bank'    => '3. Ngân hàng VietQR',
            'order'   => '4. Đơn hàng & Vận chuyển',
            'quote'   => '5. Báo giá BOQ',
            'home'    => '6. Trang chủ',
            'seo'     => '7. SEO & GEO (/llms.txt)',
            'forms'   => '8. Fluent Forms Slots',
            'ai'      => '9. Trợ lý AI Tìm gạch',
            'search'  => '10. Tìm kiếm & Gợi ý',
        ];

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab switcher
        $active_tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : 'company';
        if (!array_key_exists($active_tab, $tabs)) {
            $active_tab = 'company';
        }

        $s    = Settings::all();
        $room = $s['showrooms'][0] ?? Settings::defaults()['showrooms'][0];
        ?>
        <div class="wrap">
            <h1>Cài đặt Hệ thống Gạch Ốp Lát DRAVIT</h1>
            <?php settings_errors(); ?>
            <nav class="nav-tab-wrapper wp-clearfix" aria-label="Nhóm cấu hình Dravit">
                <?php foreach ($tabs as $slug => $label) :
                    $tab_url = add_query_arg(['page' => self::MENU_SLUG, 'tab' => $slug], admin_url('themes.php'));
                    $cls     = 'nav-tab' . ($active_tab === $slug ? ' nav-tab-active' : '');
                    ?>
                    <a href="<?php echo esc_url($tab_url); ?>" class="<?php echo esc_attr($cls); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>

            <form method="post" action="options.php" enctype="multipart/form-data">
                <?php settings_fields('drv_settings_group'); ?>

                <?php if ($active_tab === 'company') : ?>
                    <table class="form-table" role="presentation">
                        <?php
                        $this->render_logo_row(
                            'Logo Header (nền sáng)',
                            'header_logo_id',
                            'drv_upload_header_logo',
                            (int) ($s['company']['header_logo_id'] ?? 0),
                            DRV_URI . '/assets/images/logo-navy.png',
                            false
                        );
                        $this->render_logo_row(
                            'Logo Footer (nền tối)',
                            'footer_logo_id',
                            'drv_upload_footer_logo',
                            (int) ($s['company']['footer_logo_id'] ?? 0),
                            DRV_URI . '/assets/images/logo-ivory.png',
                            true
                        );
                        ?>
                        <tr>
                            <th scope="row"><label for="drv-legal-name">Tên pháp nhân công ty</label></th>
                            <td><input id="drv-legal-name" type="text" class="regular-text" name="drv_settings[company][legal_name]" value="<?php echo esc_attr((string) $s['company']['legal_name']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="drv-tax-id">Mã số thuế (MST)</label></th>
                            <td><input id="drv-tax-id" type="text" class="regular-text" name="drv_settings[company][tax_id]" value="<?php echo esc_attr((string) $s['company']['tax_id']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="drv-comp-addr">Địa chỉ trụ sở (Chuẩn 2 cấp)</label></th>
                            <td><input id="drv-comp-addr" type="text" class="large-text" name="drv_settings[company][address]" value="<?php echo esc_attr((string) $s['company']['address']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="drv-bct-url">Link Bộ Công Thương (nếu đã duyệt)</label></th>
                            <td><input id="drv-bct-url" type="url" class="large-text" name="drv_settings[company][bct_url]" value="<?php echo esc_attr((string) $s['company']['bct_url']); ?>"></td>
                        </tr>
                    </table>
                <?php elseif ($active_tab === 'contact') : ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="drv-hotline">Hotline tư vấn chính</label></th>
                            <td><input id="drv-hotline" type="text" class="regular-text" name="drv_settings[contact][hotline]" value="<?php echo esc_attr((string) $s['contact']['hotline']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="drv-zalo">Số Zalo OA / Tư vấn</label></th>
                            <td><input id="drv-zalo" type="text" class="regular-text" name="drv_settings[contact][zalo]" value="<?php echo esc_attr((string) $s['contact']['zalo']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="drv-email-proj">Email phòng dự án B2B</label></th>
                            <td><input id="drv-email-proj" type="email" class="regular-text" name="drv_settings[contact][project_email]" value="<?php echo esc_attr((string) $s['contact']['project_email']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="drv-hours">Khung giờ làm việc</label></th>
                            <td><input id="drv-hours" type="text" class="regular-text" name="drv_settings[contact][working_hours]" value="<?php echo esc_attr((string) $s['contact']['working_hours']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="drv-sr-name">Tên Showroom chính</label></th>
                            <td><input id="drv-sr-name" type="text" class="regular-text" name="drv_settings[showrooms][0][name]" value="<?php echo esc_attr((string) $room['name']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="drv-sr-addr">Địa chỉ Showroom</label></th>
                            <td><input id="drv-sr-addr" type="text" class="large-text" name="drv_settings[showrooms][0][address]" value="<?php echo esc_attr((string) $room['address']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="drv-sr-maps">Google Maps URL</label></th>
                            <td><input id="drv-sr-maps" type="url" class="large-text" name="drv_settings[showrooms][0][maps_url]" value="<?php echo esc_attr((string) $room['maps_url']); ?>"></td>
                        </tr>
                    </table>
                <?php elseif ($active_tab === 'bank') : ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="drv-bank-bin">Mã BIN ngân hàng (NAPAS)</label></th>
                            <td><input id="drv-bank-bin" type="text" class="regular-text" name="drv_settings[bank][bank_bin]" value="<?php echo esc_attr((string) $s['bank']['bank_bin']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="drv-bank-name">Tên ngân hàng</label></th>
                            <td><input id="drv-bank-name" type="text" class="regular-text" name="drv_settings[bank][bank_name]" value="<?php echo esc_attr((string) $s['bank']['bank_name']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="drv-bank-acc">Số tài khoản nhận VietQR</label></th>
                            <td><input id="drv-bank-acc" type="text" class="regular-text" name="drv_settings[bank][account_no]" value="<?php echo esc_attr((string) $s['bank']['account_no']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="drv-bank-holder">Chủ tài khoản (Không dấu)</label></th>
                            <td><input id="drv-bank-holder" type="text" class="large-text" name="drv_settings[bank][account_name]" value="<?php echo esc_attr((string) $s['bank']['account_name']); ?>"></td>
                        </tr>
                    </table>
                <?php elseif ($active_tab === 'order') : ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="drv-waste">Tỷ lệ hao hụt mặc định (%)</label></th>
                            <td><input id="drv-waste" type="number" min="0" max="25" name="drv_settings[order][default_waste_pct]" value="<?php echo esc_attr((string) $s['order']['default_waste_pct']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="drv-sla">Cam kết thời gian phản hồi (SLA)</label></th>
                            <td><input id="drv-sla" type="text" class="large-text" name="drv_settings[order][sla_text]" value="<?php echo esc_attr((string) $s['order']['sla_text']); ?>"></td>
                        </tr>
                    </table>
                <?php elseif ($active_tab === 'quote') : ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="drv-quote-max">Số hạng mục tối đa trên bảng BOQ</label></th>
                            <td><input id="drv-quote-max" type="number" min="5" max="50" name="drv_settings[quote][max_items]" value="<?php echo esc_attr((string) $s['quote']['max_items']); ?>"></td>
                        </tr>
                    </table>
                <?php elseif ($active_tab === 'home') : ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="drv-hero-title">Tiêu đề Hero Trang chủ</label></th>
                            <td><input id="drv-hero-title" type="text" class="large-text" name="drv_settings[home][hero_title]" value="<?php echo esc_attr((string) $s['home']['hero_title']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="drv-hero-sub">Mô tả phụ Hero</label></th>
                            <td><input id="drv-hero-sub" type="text" class="large-text" name="drv_settings[home][hero_sub]" value="<?php echo esc_attr((string) $s['home']['hero_sub']); ?>"></td>
                        </tr>
                    </table>
                <?php elseif ($active_tab === 'seo') : ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">Xuất bản động <code>/llms.txt</code></th>
                            <td>
                                <label>
                                    <input type="hidden" name="drv_settings[seo][llms_enabled]" value="0">
                                    <input type="checkbox" name="drv_settings[seo][llms_enabled]" value="1" <?php checked(!empty($s['seo']['llms_enabled'])); ?>>
                                    Cho phép AI Search Engines đọc hồ sơ năng lực &amp; 28 danh mục gạch chuẩn
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="drv-geo-sum">Tóm tắt thương hiệu GEO</label></th>
                            <td><input id="drv-geo-sum" type="text" class="large-text" name="drv_settings[seo][geo_brand_summary]" value="<?php echo esc_attr((string) $s['seo']['geo_brand_summary']); ?>"></td>
                        </tr>
                    </table>
                <?php elseif ($active_tab === 'forms') : ?>
                    <p class="description">Nhập Form ID của Fluent Forms cho từng vị trí (để <code>0</code> để dùng form native chống spam mặc định của theme theo ADR D-31).</p>
                    <table class="form-table" role="presentation">
                        <?php
                        $form_labels = [
                            'quote_modal'      => 'Modal Báo giá công trình toàn trang',
                            'pdp_consult'      => 'Tư vấn kỹ thuật trên trang Sản phẩm',
                            'contact_page'     => 'Form trang Liên hệ (/lien-he)',
                            'showroom_booking' => 'Form Đặt lịch hẹn xem mẫu Showroom',
                            'blog_inline'      => 'Form nhận báo giá trong bài Cẩm nang',
                        ];
                        foreach ($form_labels as $slot => $lbl) :
                            ?>
                            <tr>
                                <th scope="row"><label for="<?php echo esc_attr('drv-ff-' . $slot); ?>"><?php echo esc_html($lbl); ?></label></th>
                                <td><input id="<?php echo esc_attr('drv-ff-' . $slot); ?>" type="number" min="0" name="drv_settings[forms][<?php echo esc_attr($slot); ?>]" value="<?php echo esc_attr((string) ($s['forms'][$slot] ?? 0)); ?>"></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php elseif ($active_tab === 'ai') : ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">Kích hoạt Trợ lý AI Tìm gạch</th>
                            <td>
                                <label>
                                    <input type="hidden" name="drv_settings[features][ai_enabled]" value="0">
                                    <input type="checkbox" name="drv_settings[features][ai_enabled]" value="1" <?php checked(!empty($s['features']['ai_enabled'])); ?>>
                                    Hiển thị nút Trợ lý AI trên Header &amp; cho phép truy cập <code>/tro-ly-ai-tim-gach</code>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="drv-ai-provider">Engine xử lý (D-32)</label></th>
                            <td>
                                <select id="drv-ai-provider" name="drv_settings[ai][provider]">
                                    <option value="rule_only" <?php selected((string) $s['ai']['provider'], 'rule_only'); ?>>RuleOnlyDriver (0đ phí API, &lt; 200ms - Khuyên dùng MVP)</option>
                                    <option value="gemini" <?php selected((string) $s['ai']['provider'], 'gemini'); ?>>Google Gemini Structured JSON</option>
                                    <option value="anthropic" <?php selected((string) $s['ai']['provider'], 'anthropic'); ?>>Anthropic Claude Closed-World</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="drv-ai-key">API Key (chỉ cần khi chọn Gemini/Anthropic)</label></th>
                            <td><input id="drv-ai-key" type="password" class="regular-text" autocomplete="off" name="drv_settings[ai][api_key]" value="<?php echo esc_attr((string) $s['ai']['api_key']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="drv-ai-model">Tên Model tùy chọn (để trống dùng mặc định)</label></th>
                            <td><input id="drv-ai-model" type="text" class="regular-text" placeholder="gemini-2.5-flash / claude-haiku-4-5-20251001" name="drv_settings[ai][model]" value="<?php echo esc_attr((string) ($s['ai']['model'] ?? '')); ?>"></td>
                        </tr>
                    </table>
                <?php elseif ($active_tab === 'search') : ?>
                    <?php $this->render_search_tab($s); ?>
                <?php endif; ?>

                <?php submit_button('Lưu cấu hình Dravit'); ?>
            </form>
        </div>
        <?php
    }

    private function render_logo_row(
        string $label,
        string $setting_key,
        string $upload_name,
        int $attachment_id,
        string $default_src,
        bool $dark_preview
    ): void {
        $preview_src = $default_src;
        if ($attachment_id > 0) {
            $media_url = wp_get_attachment_image_url($attachment_id, 'full');
            if (is_string($media_url) && $media_url !== '') {
                $preview_src = $media_url;
            } else {
                $attachment_id = 0;
            }
        }

        $bg_style = $dark_preview
            ? 'background:#083a4f;padding:8px 12px;border-radius:4px;max-height:36px;width:auto;display:block;margin-bottom:8px;'
            : 'background:#ffffff;border:1px solid #dcdcde;padding:8px 12px;border-radius:4px;max-height:36px;width:auto;display:block;margin-bottom:8px;';
        $field_id = 'drv-' . str_replace('_', '-', $setting_key);
        ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr($field_id . '-file'); ?>"><?php echo esc_html($label); ?></label></th>
            <td data-drv-media-field>
                <img src="<?php echo esc_url($preview_src); ?>"
                     data-drv-media-preview
                     data-default-src="<?php echo esc_url($default_src); ?>"
                     style="<?php echo esc_attr($bg_style); ?>"
                     alt="<?php echo esc_attr($label); ?>">
                <input type="hidden"
                       id="<?php echo esc_attr($field_id); ?>"
                       name="drv_settings[company][<?php echo esc_attr($setting_key); ?>]"
                       value="<?php echo esc_attr((string) $attachment_id); ?>"
                       data-drv-media-input>
                <p>
                    <button type="button" class="button button-secondary" data-drv-media-pick>Chọn từ Thư viện Media</button>
                    &nbsp;
                    <button type="button"
                            class="button-link button-link-delete"
                            data-drv-media-clear
                            <?php echo $attachment_id <= 0 ? 'hidden' : ''; ?>>Khôi phục logo mặc định</button>
                </p>
                <p class="description">
                    Hoặc tải file trực tiếp lên Media khi bấm <strong>Lưu cấu hình Dravit</strong>:
                    <input type="file"
                           id="<?php echo esc_attr($field_id . '-file'); ?>"
                           name="<?php echo esc_attr($upload_name); ?>"
                           accept="image/webp,image/png,image/jpeg,image/svg+xml"
                           data-drv-media-file>
                </p>
            </td>
        </tr>
        <?php
    }

    private function get_media_picker_script(): string {
        return <<<'JS'
document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) return;

    const pickBtn = target.closest('[data-drv-media-pick]');
    if (pickBtn) {
        event.preventDefault();
        const wrap = pickBtn.closest('[data-drv-media-field]');
        if (!wrap || typeof wp === 'undefined' || !wp.media) return;

        const frame = wp.media({
            title: 'Chọn hoặc tải Logo lên Thư viện Media',
            button: { text: 'Sử dụng Logo này' },
            library: { type: 'image' },
            multiple: false,
        });

        frame.on('select', () => {
            const attachment = frame.state().get('selection').first().toJSON();
            const input = wrap.querySelector('[data-drv-media-input]');
            const preview = wrap.querySelector('[data-drv-media-preview]');
            const clearBtn = wrap.querySelector('[data-drv-media-clear]');
            const fileInput = wrap.querySelector('[data-drv-media-file]');
            if (input) input.value = String(attachment.id || 0);
            if (preview && attachment.url) preview.src = attachment.url;
            if (clearBtn) clearBtn.hidden = false;
            if (fileInput) fileInput.value = '';
        });

        frame.open();
        return;
    }

    const clearBtn = target.closest('[data-drv-media-clear]');
    if (clearBtn) {
        event.preventDefault();
        const wrap = clearBtn.closest('[data-drv-media-field]');
        if (!wrap) return;
        const input = wrap.querySelector('[data-drv-media-input]');
        const preview = wrap.querySelector('[data-drv-media-preview]');
        const fileInput = wrap.querySelector('[data-drv-media-file]');
        if (input) input.value = '0';
        if (preview && preview.dataset.defaultSrc) preview.src = preview.dataset.defaultSrc;
        if (fileInput) fileInput.value = '';
        clearBtn.hidden = true;
    }
});

document.addEventListener('change', (event) => {
    const fileInput = event.target instanceof HTMLInputElement && event.target.matches('[data-drv-media-file]') ? event.target : null;
    if (!fileInput || !fileInput.files || !fileInput.files[0]) return;
    const wrap = fileInput.closest('[data-drv-media-field]');
    const preview = wrap ? wrap.querySelector('[data-drv-media-preview]') : null;
    if (preview) {
        preview.src = URL.createObjectURL(fileInput.files[0]);
    }
});
JS;
    }

    /**
     * @param array<string, mixed> $s
     */
    private function render_search_tab(array $s): void {
        $max_pinned   = SearchTrending::MAX_PINNED;
        $pinned_ids   = SearchTrending::getPinnedIds();
        $pinned_items = SearchTrending::describePinnedProducts($pinned_ids);
        $pinned_count = count($pinned_items);
        $is_full      = $pinned_count >= $max_pinned;

        $popular_kws = isset($s['search']['popular_keywords']) && is_array($s['search']['popular_keywords'])
            ? implode(', ', array_map('strval', $s['search']['popular_keywords']))
            : '80x80, Vân đá Marble, Terrazzo, Khổ lớn 60x120, Chống trượt R10';

        $selected_silos = isset($s['search']['featured_silos']) && is_array($s['search']['featured_silos'])
            ? array_values(array_map('strval', $s['search']['featured_silos']))
            : [];
        $collections = Config::get('collections');
        ?>
        <p class="description">
            Cấu hình cơ chế <strong>Hybrid 3 tầng</strong> cho khung tìm kiếm tức thì:
            <strong>Tầng 1 (Ghim tay bên dưới, tối đa <?php echo esc_html((string) $max_pinned); ?> SP)</strong> &rarr;
            <strong>Tầng 2 (Tự học theo lượt click tìm kiếm &ge; <?php echo esc_html((string) SearchTrending::MIN_LEARNED_HITS); ?> lượt)</strong> &rarr;
            <strong>Tầng 3 (Tự động bù bằng SP Nổi bật / Bán chạy khi website mới hoặc ghim chưa đủ <?php echo esc_html((string) $max_pinned); ?> SP)</strong>.
        </p>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="drv-pinned-products">Sản phẩm ghim cố định</label>
                    <p class="description" style="font-weight:400;margin-top:4px;">
                        <strong id="drv-pin-counter" data-max="<?php echo esc_attr((string) $max_pinned); ?>">
                            Đã ghim: <?php echo esc_html($pinned_count . '/' . $max_pinned); ?> sản phẩm
                        </strong>
                    </p>
                </th>
                <td>
                    <input type="hidden" name="drv_settings[search][pinned_product_ids][]" value="0">
                    <select id="drv-pinned-products"
                            class="wc-product-search"
                            multiple="multiple"
                            style="width:100%;max-width:640px;"
                            name="drv_settings[search][pinned_product_ids][]"
                            data-placeholder="Gõ tên gạch hoặc mã SKU (VD: DRV-8801)..."
                            data-action="woocommerce_json_search_products"
                            data-minimum_input_length="2"
                            data-sortable="true"
                            data-maximum-selection-length="<?php echo esc_attr((string) $max_pinned); ?>">
                        <?php foreach ($pinned_items as $item) :
                            $opt_label = $item['title'] . ' (' . $item['sku'] . ')' . ($item['in_stock'] ? '' : ' — [Tạm hết hàng]');
                            ?>
                            <option value="<?php echo esc_attr((string) $item['id']); ?>" selected="selected">
                                <?php echo esc_html($opt_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div id="drv-pin-full-alert"
                         class="notice notice-warning inline"
                         style="max-width:640px;margin:8px 0 0;padding:8px 12px;"
                         <?php echo $is_full ? '' : 'hidden'; ?>>
                        <p style="margin:0;">
                            <strong>Đã đạt tối đa <?php echo esc_html($max_pinned . '/' . $max_pinned); ?> sản phẩm ghim.</strong>
                            Hệ thống đã khóa chọn thêm — hãy bấm dấu <strong>&times;</strong> gỡ bớt ít nhất 1 sản phẩm trước khi ghim mẫu mới.
                        </p>
                    </div>

                    <p class="description">
                        Kéo-thả các thẻ đã chọn để đổi thứ tự hiển thị #1 &rarr; #<?php echo esc_html((string) $max_pinned); ?>. Bạn cũng có thể tích ghim nhanh ngay ở cột phải trong trang sửa từng Sản phẩm.
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="drv-popular-kws">Từ khóa phổ biến (tối đa 8 chip)</label></th>
                <td>
                    <input id="drv-popular-kws"
                           type="text"
                           class="large-text"
                           name="drv_settings[search][popular_keywords]"
                           value="<?php echo esc_attr($popular_kws); ?>">
                    <p class="description">Ngăn cách bằng dấu phẩy. Khi khách bấm vào chip sẽ kích hoạt lọc kết quả tức thì.</p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    Danh mục phổ biến cột trái
                    <p class="description" style="font-weight:400;margin-top:4px;">
                        <strong id="drv-silo-counter" data-max="6">
                            Đã chọn: <?php echo esc_html(count($selected_silos) . '/6'); ?> danh mục
                        </strong>
                    </p>
                </th>
                <td>
                    <div id="drv-silo-full-alert"
                         class="notice notice-info inline"
                         style="max-width:640px;margin:0 0 8px;padding:6px 12px;"
                         <?php echo count($selected_silos) >= 6 ? '' : 'hidden'; ?>>
                        <p style="margin:0;">Đã chọn đủ <strong>6/6</strong> danh mục phổ biến. Bỏ tích 1 mục nếu muốn đổi sang danh mục khác.</p>
                    </div>
                    <fieldset style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:6px 16px;max-width:760px;">
                        <?php foreach ($collections as $slug => $cfg) :
                            $slug_str = (string) $slug;
                            if ($slug_str === 'gach') {
                                continue;
                            }
                            $checked  = in_array($slug_str, $selected_silos, true);
                            $disabled = !$checked && count($selected_silos) >= 6;
                            ?>
                            <label>
                                <input type="checkbox"
                                       name="drv_settings[search][featured_silos][]"
                                       value="<?php echo esc_attr($slug_str); ?>"
                                       data-drv-silo-checkbox
                                       <?php checked($checked); ?>
                                       <?php disabled($disabled); ?>>
                                <?php echo esc_html((string) ($cfg['name'] ?? $slug_str)); ?>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                </td>
            </tr>
        </table>
        <?php
    }

    private function get_search_pin_guard_script(): string {
        return <<<'JS'
(function ($) {
    if (typeof $ !== 'function') return;

    $(function () {
        var $pinSelect = $('#drv-pinned-products');
        var $pinCounter = $('#drv-pin-counter');
        var $pinAlert = $('#drv-pin-full-alert');
        var maxPinned = parseInt($pinCounter.attr('data-max') || '4', 10) || 4;

        function getSelectedPinCount() {
            var val = $pinSelect.val();
            if (!Array.isArray(val)) return 0;
            return val.filter(function (v) { return parseInt(v, 10) > 0; }).length;
        }

        function syncPinLimitUi() {
            if (!$pinSelect.length) return;
            var count = getSelectedPinCount();
            var isFull = count >= maxPinned;
            $pinCounter.text('Đã ghim: ' + count + '/' + maxPinned + ' sản phẩm');
            $pinAlert.prop('hidden', !isFull);
        }

        if ($pinSelect.length) {
            $pinSelect.on('change', syncPinLimitUi);
            $pinSelect.on('select2:selecting', function (e) {
                if (getSelectedPinCount() >= maxPinned) {
                    e.preventDefault();
                    $pinAlert.prop('hidden', false);
                }
            });
            $pinSelect.on('select2:opening', function (e) {
                if (getSelectedPinCount() >= maxPinned) {
                    e.preventDefault();
                    $pinAlert.prop('hidden', false);
                }
            });
            syncPinLimitUi();
        }

        var $siloBoxes = $('[data-drv-silo-checkbox]');
        var $siloCounter = $('#drv-silo-counter');
        var $siloAlert = $('#drv-silo-full-alert');
        var maxSilos = parseInt($siloCounter.attr('data-max') || '6', 10) || 6;

        function syncSiloLimitUi() {
            if (!$siloBoxes.length) return;
            var checkedCount = $siloBoxes.filter(':checked').length;
            var full = checkedCount >= maxSilos;
            $siloCounter.text('Đã chọn: ' + checkedCount + '/' + maxSilos + ' danh mục');
            $siloAlert.prop('hidden', !full);
            $siloBoxes.each(function () {
                if (!this.checked) {
                    this.disabled = full;
                }
            });
        }

        if ($siloBoxes.length) {
            $siloBoxes.on('change', syncSiloLimitUi);
            syncSiloLimitUi();
        }
    });
})(window.jQuery);
JS;
    }
}

