<?php
declare(strict_types=1);

namespace Dravit\Admin;

use Dravit\Content\PostMeta;
use Dravit\Content\ReadingTime;
use Dravit\Core\Config;
use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Metabox cấu hình Bài viết Cẩm nang, Trang Chính sách & Thanh chèn nhanh 12 Shortcodes trên Classic Editor (Phương án A1, ADR D-36, docs/20 §2.1).
 */
final class PostFields implements ServiceProviderInterface {
    private const NONCE_ACTION = 'drv_save_post_editorial_meta';
    private const NONCE_NAME   = '_drv_editorial_nonce';

    public function register(): void {
        if (!is_admin()) {
            return;
        }

        add_action('add_meta_boxes', [$this, 'register_metaboxes']);
        add_action('save_post_post', [$this, 'save_post_meta'], 10, 2);
        add_action('save_post_page', [$this, 'save_page_meta'], 10, 2);
        add_action('media_buttons', [$this, 'render_classic_editor_shortcode_picker'], 15);
        add_action('admin_print_footer_scripts-post.php', [$this, 'print_shortcode_picker_script']);
        add_action('admin_print_footer_scripts-post-new.php', [$this, 'print_shortcode_picker_script']);
    }

    public function register_metaboxes(): void {
        add_meta_box(
            'drv-post-editorial-meta',
            'Cấu hình GEO, E-E-A-T & Liên kết Silo (DRAVIT)',
            [$this, 'render_post_metabox'],
            'post',
            'normal',
            'high'
        );

        add_meta_box(
            'drv-policy-page-meta',
            'Thông tin Hiệu lực Chính sách (DRAVIT)',
            [$this, 'render_policy_metabox'],
            'page',
            'side',
            'default'
        );
    }

    /**
     * Nút chọn nhanh 12 khối Shortcode chuẩn DRAVIT ngay trên thanh công cụ Classic Editor (TinyMCE / Text, ADR D-36).
     */
    public function render_classic_editor_shortcode_picker(string $editor_id): void {
        if ($editor_id !== 'content' || !function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen instanceof \WP_Screen || $screen->post_type !== 'post') {
            return;
        }
        ?>
        <span class="drv-sc-picker-wrap" style="display:inline-flex;align-items:center;gap:4px;margin-left:6px;">
            <label for="drv-shortcode-template" class="screen-reader-text">Chèn khối Cẩm nang DRAVIT</label>
            <select id="drv-shortcode-template" style="height:30px;max-width:260px;">
                <option value="">+ Chèn khối Cẩm nang DRAVIT...</option>
                <optgroup label="1. Khối trình bày kỹ thuật &amp; GEO (6 mẫu)">
                    <option value="takeaways">Hộp Tóm tắt nhanh GEO (Key Takeaways)</option>
                    <option value="callout_note">Ghi chú kỹ thuật (Callout Note)</option>
                    <option value="callout_tip">Mẹo thi công chuẩn (Callout Tip)</option>
                    <option value="callout_warn">Cảnh báo sai lầm (Callout Warning)</option>
                    <option value="pros_cons">Bảng Ưu điểm / Lưu ý thi công (2 cột)</option>
                    <option value="formula">Hộp Công thức quy đổi số thùng</option>
                    <option value="steps">Quy trình các bước thi công (01-04)</option>
                    <option value="faq">Khối Câu hỏi thường gặp (FAQ Schema)</option>
                </optgroup>
                <optgroup label="2. Khối thương mại động từ Kho WC (6 mẫu)">
                    <option value="products">Lưới / Băng chuyền sản phẩm [drv_products]</option>
                    <option value="shop_look">Thanh gắn mã gạch dưới ảnh [drv_shop_the_look]</option>
                    <option value="price_table">Bảng báo giá &amp; quy cách động [drv_price_table]</option>
                    <option value="lead_sample">Form đăng ký nhận mẫu gạch thật [drv_lead_form]</option>
                    <option value="related_posts">Hộp bài viết liên quan giữa bài [drv_related_posts]</option>
                    <option value="ai_card">Thẻ gợi ý hỏi Trợ lý AI [drv_ai_card]</option>
                </optgroup>
            </select>
        </span>
        <?php
    }

    public function print_shortcode_picker_script(): void {
        if (!function_exists('get_current_screen')) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen instanceof \WP_Screen || $screen->post_type !== 'post') {
            return;
        }
        ?>
        <script>
        document.addEventListener('change', function (e) {
            var sel = e.target;
            if (!sel || sel.id !== 'drv-shortcode-template' || !sel.value) return;
            var snippets = {
                takeaways: '[drv_takeaways title="Tóm tắt nhanh cho gia chủ"]\n• Sàn nhà tắm ướt xà phòng cần chọn xương Porcelain (E ≤ 0,5%) và bề mặt chống trượt từ R10 trở lên.\n• Khổ gạch 30x60 cm dễ tạo độ dốc thoát sàn hơn khổ lớn trong phòng tắm dưới 5 m².\n• Cộng dự phòng 5 - 10% hao hụt cắt gạch để xuất đủ cùng một mã lô (Lot).\n[/drv_takeaways]\n',
                callout_note: '[drv_callout type="note" title="Lưu ý kiểm soát đồng bộ mã lô (Lot)"]Khi nhận gạch tại công trình, hãy đối chiếu mã Lot và cỡ chuẩn (Caliber) trên toàn bộ vỏ thùng trước khi bóc hộp lát đại trà.[/drv_callout]\n',
                callout_tip: '[drv_callout type="tip" title="Mẹo chọn màu keo chà ron tiệp màu nền"]Ưu tiên keo chà ron chống thấm màu xám nhạt hoặc kem ngà đồng tông nền gạch để mạch ron 1,5 mm liền khối và dễ vệ sinh.[/drv_callout]\n',
                callout_warn: '[drv_callout type="warning" title="Không lát gạch Porcelain khổ lớn bằng hồ dầu xi măng"]Xương Porcelain có độ hút nước E ≤ 0,5% nên hồ dầu truyền thống không thể bám mao dẫn, dễ bong bộp sau 12 - 18 tháng. Cần dùng keo dán gạch cấp C2 kết hợp kéo bay răng cưa 2 mặt.[/drv_callout]\n',
                pros_cons: '[drv_pros_cons pros="Giảm hơn 50% mật độ đường ron trên mặt sàn rộng|Xương đá Porcelain hút nước ≤ 0,5%, chống thấm ngược ổn định|Bề mặt Matt R10 bám chân khi ướt nhưng vẫn dễ lau sạch" cons="Phòng tắm dưới 5 m² khó tạo dốc 4 hướng với gạch khổ lớn|Trọng lượng nặng (~42 kg/thùng), cần ke cân bằng 1,5 mm và keo dán C2"]\n',
                formula: '[drv_formula title="Công thức quy đổi số thùng gạch 80x80 cm" sub="1 viên = 0,64 m² · 1 thùng = 3 viên = 1,92 m²" cells="1. Số thùng gạch cần đặt::Làm tròn lên [(m² sàn × 1,05) ÷ 1,92]::Đã gồm 5% hao hụt cắt góc|2. Định mức keo dán C2::Số bao (25 kg) = Làm tròn lên [m² sàn ÷ 5]::Định mức ~5 kg/m² với bay răng cưa 8 mm|3. Định mức keo chà ron::Lượng keo ≈ 0,20 kg/m²::Mạch ron chuẩn 1,5 - 2,0 mm"]\n',
                steps: '[drv_steps items="Kiểm tra độ phẳng cốt nền::Thước nhôm 2m::Đảm bảo cốt nền khô sạch, độ chênh phẳng ≤ 2 mm trên thước 2 m và đối chiếu cùng mã Lot trên vỏ thùng.|Trét keo dán gạch C2 hai mặt::Bay răng cưa 8mm::Kéo keo nghiêng 60 độ trên sàn và phết mỏng 1 lớp mặt lưng viên gạch để phủ kín không rỗng khí.|Cài chốt ke cân bằng 1,5 mm::Ke cân bằng::Đặt tối thiểu 2 ke mỗi cạnh gạch và gõ đều bằng búa cao su trắng từ tâm ra mép.|Vệ sinh khe và bơm keo ron::Sau 24 giờ::Chờ keo dán đóng rắn hoàn toàn, tháo ke, hút sạch bụi khe mạch và chà keo ron chống bám bẩn."]\n',
                faq: '[drv_faq]\n[drv_faq_item q="Gạch bề mặt Matt R10 có bị bám bẩn khó lau chùi không?"]Không. Bề mặt Matt R10 có độ nhám vi mô vừa đủ chống trượt nước xà phòng (DCOF ≥ 0,42) nhưng không gồ ghề giữ cặn như gạch sân vườn R12, lau sạch dễ dàng bằng cây lau nhà thường.[/drv_faq_item]\n[drv_faq_item q="Một thùng gạch 80x80 cm đóng bao nhiêu viên và nặng bao nhiêu kg?"]Quy cách chuẩn tại kho DRAVIT là 1 thùng = 3 viên = 1,92 m² (mỗi viên 0,64 m²), trọng lượng trung bình khoảng 42 kg/thùng.[/drv_faq_item]\n[/drv_faq]\n',
                products: '[drv_products collection="gach-terrazzo" limit="4" layout="grid" title="Mẫu gạch Terrazzo chống trượt tiêu biểu sẵn kho"]\n',
                shop_look: '[drv_shop_the_look sku="" role="Gạch lát sàn trong ảnh" caption="Phối cảnh thực tế dưới ánh đèn vàng ấm 3000K"]\n',
                price_table: '[drv_price_table collection="gach-80x80" limit="6" caption="Bảng quy cách đóng gói và đơn giá niêm yết cập nhật từ kho DRAVIT"]\n',
                lead_sample: '[drv_lead_form type="sample" title="Đăng ký nhận mẫu gạch thật đối chiếu tại công trình"]\n',
                related_posts: '[drv_related_posts limit="2" label="Xem thêm hướng dẫn kỹ thuật liên quan"]\n',
                ai_card: '[drv_ai_card prompts="Nhà tắm 6 m² nên lát gạch 30x60 hay 60x60 chống trượt R10?|Tính giúp số thùng gạch 80x80 cho phòng khách 32 m²"]\n'
            };
            var code = snippets[sel.value] || '';
            sel.value = '';
            if (!code) return;
            if (typeof window.send_to_editor === 'function') {
                window.send_to_editor(code);
            } else {
                var ta = document.getElementById('content');
                if (ta) { ta.value += '\n' + code; }
            }
        });
        </script>
        <?php
    }

    public function render_post_metabox(\WP_Post $post): void {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $post_id      = (int) $post->ID;
        $is_pillar    = (string) get_post_meta($post_id, 'drv_is_pillar', true) === '1';
        $toc_enabled  = (string) get_post_meta($post_id, 'drv_toc_enabled', true) !== '0';
        $reading_min  = ReadingTime::forPost($post);
        $takeaways    = PostMeta::getKeyTakeaways($post_id);
        $rel_cols     = PostMeta::getRelatedCollectionSlugs($post_id);
        $rel_posts    = PostMeta::getRelatedPostIds($post_id);
        $reviewer_id  = (int) get_post_meta($post_id, 'drv_reviewer_id', true);
        $reviewed_at  = trim((string) get_post_meta($post_id, 'drv_reviewed_at', true));
        $ai_prompts   = PostMeta::getAiPrompts($post_id);
        $collections  = Config::get('collections');
        $users        = get_users(['fields' => ['ID', 'display_name'], 'orderby' => 'display_name', 'number' => 100]);
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">Hiển thị &amp; Mục lục</th>
                <td>
                    <label style="display:block;margin-bottom:6px;">
                        <input type="checkbox" name="drv_is_pillar" value="1" <?php checked($is_pillar); ?>>
                        <strong>Bài Pillar trọng tâm</strong> (Ghim lên ô lớn 5 cột đầu trang <code>/cam-nang</code>)
                    </label>
                    <label style="display:block;margin-bottom:6px;">
                        <input type="hidden" name="drv_toc_enabled" value="0">
                        <input type="checkbox" name="drv_toc_enabled" value="1" <?php checked($toc_enabled); ?>>
                        Tự động sinh Mục lục <code>&lt;drv-toc&gt;</code> khi bài có &ge; 3 tiêu đề <code>&lt;h2&gt;</code>
                    </label>
                    <p class="description">Thời lượng đọc tự tính hiện tại: <strong><?php echo esc_html(ReadingTime::formatLabel($reading_min)); ?></strong> (~200 từ/phút).</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="drv-key-takeaways">Tóm tắt nhanh GEO đầu bài (3-5 ý)</label></th>
                <td>
                    <textarea id="drv-key-takeaways" name="drv_key_takeaways" rows="4" class="large-text" placeholder="Mỗi dòng 1 ý ngắn chứa thông số cụ thể (VD: E <= 0,5%, chống trượt R10, ron 1,5 - 2 mm)..."><?php echo esc_textarea(implode("\n", $takeaways)); ?></textarea>
                    <p class="description">Tự động hiển thị ngay trên đầu bài viết nếu trong nội dung chưa đặt shortcode <code>[drv_takeaways]</code>.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Danh mục Gạch liên quan <code>IL-05</code> (tối đa 4)</th>
                <td>
                    <fieldset style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:4px 14px;max-height:190px;overflow-y:auto;padding:8px;border:1px solid #dcdcde;background:#fff;">
                        <?php foreach ($collections as $slug => $cfg) :
                            $s = (string) $slug;
                            ?>
                            <label>
                                <input type="checkbox" name="drv_related_collections[]" value="<?php echo esc_attr($s); ?>" <?php checked(in_array($s, $rel_cols, true)); ?>>
                                <?php echo esc_html((string) ($cfg['name'] ?? $s)); ?>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <p class="description">Hiển thị dải điều hướng Silo tĩnh cuối bài và gắn vào thuộc tính <code>mentions</code> của Schema <code>BlogPosting</code>.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="drv-reviewer-id">Kỹ sư thẩm định kỹ thuật (E-E-A-T)</label></th>
                <td>
                    <select id="drv-reviewer-id" name="drv_reviewer_id">
                        <option value="0">-- Chưa gán người thẩm định riêng --</option>
                        <?php foreach ($users as $u) : ?>
                            <option value="<?php echo esc_attr((string) $u->ID); ?>" <?php selected($reviewer_id, (int) $u->ID); ?>>
                                <?php echo esc_html((string) $u->display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    &nbsp;&nbsp;
                    <label for="drv-reviewed-at">Ngày rà soát:</label>
                    <input id="drv-reviewed-at" type="date" name="drv_reviewed_at" value="<?php echo esc_attr($reviewed_at); ?>">
                    <p class="description">Chỉ xuất <code>reviewedBy</code> trong JSON-LD khi tài khoản được chọn đã điền <em>Chức danh chuyên môn thật</em> trong Hồ sơ thành viên (<code>O-13</code>).</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="drv-related-posts">ID Bài viết liên quan (tùy chọn)</label></th>
                <td>
                    <input id="drv-related-posts" type="text" class="regular-text" name="drv_related_posts" value="<?php echo esc_attr(implode(', ', $rel_posts)); ?>" placeholder="VD: 142, 158, 165">
                    <p class="description">Tối đa 4 Post ID cách nhau dấu phẩy. Để trống sẽ tự lấy bài cùng chuyên mục.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="drv-ai-prompts">Câu hỏi gợi mở Trợ lý AI (tối đa 3)</label></th>
                <td>
                    <textarea id="drv-ai-prompts" name="drv_ai_prompts" rows="3" class="large-text" placeholder="Mỗi dòng 1 câu hỏi gợi ý sát chủ đề bài viết..."><?php echo esc_textarea(implode("\n", $ai_prompts)); ?></textarea>
                </td>
            </tr>
        </table>
        <?php
    }

    public function render_policy_metabox(\WP_Post $post): void {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        $eff_date = trim((string) get_post_meta($post->ID, 'drv_effective_date', true));
        ?>
        <p>
            <label for="drv-effective-date"><strong>Ngày hiệu lực chính sách:</strong></label><br>
            <input id="drv-effective-date" type="date" class="widefat" name="drv_effective_date" value="<?php echo esc_attr($eff_date); ?>">
        </p>
        <p class="description">Áp dụng cho mẫu giao diện <em>Trang Chính sách</em> (để trống sẽ hiển thị ngày cập nhật trang).</p>
        <?php
    }

    public function save_post_meta(int $post_id, \WP_Post $post): void {
        unset($post);
        if (!$this->can_save($post_id)) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Đã xác thực nonce trong `$this->can_save($post_id)`.
        $is_pillar = isset($_POST['drv_is_pillar']) ? '1' : '0';
        update_post_meta($post_id, 'drv_is_pillar', $is_pillar);

        $toc_enabled = isset($_POST['drv_toc_enabled']) && sanitize_text_field(wp_unslash((string) $_POST['drv_toc_enabled'])) === '0' ? '0' : '1';
        update_post_meta($post_id, 'drv_toc_enabled', $toc_enabled);

        if (isset($_POST['drv_key_takeaways'])) {
            $lines = PostMeta::parseStringList(sanitize_textarea_field(wp_unslash((string) $_POST['drv_key_takeaways'])), 5, 220);
            update_post_meta($post_id, 'drv_key_takeaways', (string) wp_json_encode($lines, JSON_UNESCAPED_UNICODE));
        }

        $collections = Config::get('collections');
        $raw_cols    = isset($_POST['drv_related_collections']) && is_array($_POST['drv_related_collections'])
            ? array_map('sanitize_key', wp_unslash($_POST['drv_related_collections']))
            : [];
        $valid_cols  = [];
        foreach ($raw_cols as $col_slug) {
            if (isset($collections[$col_slug]) && !in_array($col_slug, $valid_cols, true)) {
                $valid_cols[] = $col_slug;
            }
            if (count($valid_cols) >= 4) {
                break;
            }
        }
        update_post_meta($post_id, 'drv_related_collections', (string) wp_json_encode($valid_cols, JSON_UNESCAPED_UNICODE));

        $reviewer_id = isset($_POST['drv_reviewer_id']) ? max(0, (int) $_POST['drv_reviewer_id']) : 0;
        if ($reviewer_id > 0 && !get_userdata($reviewer_id)) {
            $reviewer_id = 0;
        }
        update_post_meta($post_id, 'drv_reviewer_id', $reviewer_id);

        $reviewed_at = isset($_POST['drv_reviewed_at']) ? sanitize_text_field(wp_unslash((string) $_POST['drv_reviewed_at'])) : '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $reviewed_at) !== 1) {
            $reviewed_at = '';
        }
        update_post_meta($post_id, 'drv_reviewed_at', $reviewed_at);

        if (isset($_POST['drv_related_posts'])) {
            $raw_rel = sanitize_text_field(wp_unslash((string) $_POST['drv_related_posts']));
            $ids     = [];
            foreach (preg_split('/[\s,]+/', $raw_rel) ?: [] as $part) {
                $pid = max(0, (int) $part);
                if ($pid > 0 && $pid !== $post_id && !in_array($pid, $ids, true)) {
                    $ids[] = $pid;
                }
                if (count($ids) >= 4) {
                    break;
                }
            }
            update_post_meta($post_id, 'drv_related_posts', (string) wp_json_encode($ids));
        }

        if (isset($_POST['drv_ai_prompts'])) {
            $prompts = PostMeta::parseStringList(sanitize_textarea_field(wp_unslash((string) $_POST['drv_ai_prompts'])), 3, 200);
            update_post_meta($post_id, 'drv_ai_prompts', (string) wp_json_encode($prompts, JSON_UNESCAPED_UNICODE));
        }
        // phpcs:enable
    }

    public function save_page_meta(int $post_id, \WP_Post $post): void {
        unset($post);
        if (!$this->can_save($post_id)) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Đã xác thực nonce trong `$this->can_save($post_id)`.
        if (isset($_POST['drv_effective_date'])) {
            $date = sanitize_text_field(wp_unslash((string) $_POST['drv_effective_date']));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
                $date = '';
            }
            update_post_meta($post_id, 'drv_effective_date', $date);
        }
        // phpcs:enable
    }

    private function can_save(int $post_id): bool {
        if ($post_id <= 0 || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) {
            return false;
        }

        if (!isset($_POST[self::NONCE_NAME])) {
            return false;
        }

        $nonce = sanitize_text_field(wp_unslash((string) $_POST[self::NONCE_NAME]));
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return false;
        }

        return current_user_can('edit_post', $post_id);
    }
}
