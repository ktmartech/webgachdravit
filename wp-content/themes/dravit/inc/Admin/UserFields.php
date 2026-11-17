<?php
declare(strict_types=1);

namespace Dravit\Admin;

use Dravit\Content\AuthorProfile;
use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Trường thông tin Hồ sơ Chuyên gia E-E-A-T trên trang Thành viên WP Admin (`O-13`, docs/20 §2.2.A).
 */
final class UserFields implements ServiceProviderInterface {
    private const NONCE_ACTION = 'drv_save_user_eeat_profile';
    private const NONCE_NAME   = '_drv_user_eeat_nonce';

    public function register(): void {
        if (!is_admin()) {
            return;
        }

        add_action('show_user_profile', [$this, 'render_profile_fields']);
        add_action('edit_user_profile', [$this, 'render_profile_fields']);
        add_action('personal_options_update', [$this, 'save_profile_fields']);
        add_action('edit_user_profile_update', [$this, 'save_profile_fields']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_media_on_profile']);
    }

    public function enqueue_media_on_profile(string $hook_suffix): void {
        if (!in_array($hook_suffix, ['profile.php', 'user-edit.php'], true)) {
            return;
        }
        wp_enqueue_media();
    }

    public function render_profile_fields(\WP_User $user): void {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $uid         = (int) $user->ID;
        $job_title   = (string) get_user_meta($uid, 'drv_job_title', true);
        $credentials = (string) get_user_meta($uid, 'drv_credentials', true);
        $avatar_id   = (int) get_user_meta($uid, 'drv_avatar_id', true);
        $same_as     = AuthorProfile::parseSameAs(get_user_meta($uid, 'drv_same_as', true));
        $is_public   = (string) get_user_meta($uid, 'drv_public_expert', true) === '1';
        $avatar_url  = AuthorProfile::resolveAvatarUrl($uid);
        ?>
        <h2>Hồ sơ Chuyên môn &amp; Thẩm định Kỹ thuật E-E-A-T (DRAVIT)</h2>
        <p class="description">Thông tin thật phục vụ bài viết Cẩm nang và Schema <code>BlogPosting</code>. Khi <em>Chức danh chuyên môn</em> để trống, bài viết tự hiển thị là <strong>Ban Kỹ thuật DRAVIT</strong>.</p>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="drv-user-job-title">Chức danh chuyên môn thực tế</label></th>
                <td>
                    <input id="drv-user-job-title" type="text" name="drv_job_title" class="regular-text" maxlength="80" value="<?php echo esc_attr($job_title); ?>" placeholder="VD: Kỹ sư Vật liệu Ốp lát / Chuyên viên Kỹ thuật DRAVIT">
                </td>
            </tr>
            <tr>
                <th><label for="drv-user-credentials">Kinh nghiệm thực chiến &amp; Chứng chỉ</label></th>
                <td>
                    <textarea id="drv-user-credentials" name="drv_credentials" rows="3" class="large-text" maxlength="220" placeholder="Tóm tắt kinh nghiệm thực tế kiểm định vật liệu, giám sát thi công (tối đa 200 ký tự)..."><?php echo esc_textarea($credentials); ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label for="drv-user-avatar-id">Ảnh chân dung tự host</label></th>
                <td>
                    <img id="drv-user-avatar-preview" src="<?php echo esc_url($avatar_url); ?>" alt="" width="64" height="64" style="object-fit:cover;border-radius:4px;border:1px solid #dcdcde;display:block;margin-bottom:8px;">
                    <input id="drv-user-avatar-id" type="hidden" name="drv_avatar_id" value="<?php echo esc_attr((string) $avatar_id); ?>">
                    <button type="button" class="button" id="drv-pick-user-avatar">Chọn ảnh từ Media</button>
                    <button type="button" class="button-link button-link-delete" id="drv-clear-user-avatar" style="margin-left:8px;">Xóa ảnh</button>
                    <p class="description">Tự host trên <code>gachdravit.vn</code> (không gọi Gravatar ngoài).</p>
                    <script>
                    (function () {
                        var pick = document.getElementById('drv-pick-user-avatar');
                        var clear = document.getElementById('drv-clear-user-avatar');
                        var input = document.getElementById('drv-user-avatar-id');
                        var prev = document.getElementById('drv-user-avatar-preview');
                        if (pick && typeof wp !== 'undefined' && wp.media) {
                            pick.addEventListener('click', function (e) {
                                e.preventDefault();
                                var frame = wp.media({ title: 'Chọn ảnh chân dung nhân sự', multiple: false, library: { type: 'image' } });
                                frame.on('select', function () {
                                    var att = frame.state().get('selection').first().toJSON();
                                    if (input) input.value = String(att.id || 0);
                                    if (prev && att.url) prev.src = att.url;
                                });
                                frame.open();
                            });
                        }
                        if (clear) {
                            clear.addEventListener('click', function (e) {
                                e.preventDefault();
                                if (input) input.value = '0';
                            });
                        }
                    })();
                    </script>
                </td>
            </tr>
            <tr>
                <th><label for="drv-user-same-as">Liên kết hồ sơ nghề nghiệp (<code>sameAs</code>)</label></th>
                <td>
                    <textarea id="drv-user-same-as" name="drv_same_as" rows="3" class="large-text" placeholder="Mỗi dòng 1 URL chính chủ (LinkedIn, bài báo chuyên ngành, tối đa 5 URL)..."><?php echo esc_textarea(implode("\n", $same_as)); ?></textarea>
                </td>
            </tr>
            <tr>
                <th>Hiển thị công khai</th>
                <td>
                    <label>
                        <input type="checkbox" name="drv_public_expert" value="1" <?php checked($is_public); ?>>
                        Hiển thị nhân sự này tại khu vực <code>/gioi-thieu#doi-ngu</code>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_profile_fields(int $user_id): void {
        if ($user_id <= 0 || !current_user_can('edit_user', $user_id)) {
            return;
        }

        if (!isset($_POST[self::NONCE_NAME])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash((string) $_POST[self::NONCE_NAME]));
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }

        $job_title = isset($_POST['drv_job_title'])
            ? mb_substr(sanitize_text_field(wp_unslash((string) $_POST['drv_job_title'])), 0, 80, 'UTF-8')
            : '';
        update_user_meta($user_id, 'drv_job_title', $job_title);

        $credentials = isset($_POST['drv_credentials'])
            ? mb_substr(sanitize_textarea_field(wp_unslash((string) $_POST['drv_credentials'])), 0, 220, 'UTF-8')
            : '';
        update_user_meta($user_id, 'drv_credentials', $credentials);

        $avatar_id = isset($_POST['drv_avatar_id']) ? max(0, (int) $_POST['drv_avatar_id']) : 0;
        if ($avatar_id > 0 && !wp_attachment_is_image($avatar_id)) {
            $avatar_id = 0;
        }
        update_user_meta($user_id, 'drv_avatar_id', $avatar_id);

        $raw_same_as = isset($_POST['drv_same_as']) ? sanitize_textarea_field(wp_unslash((string) $_POST['drv_same_as'])) : '';
        $urls        = AuthorProfile::parseSameAs($raw_same_as);
        update_user_meta($user_id, 'drv_same_as', (string) wp_json_encode($urls, JSON_UNESCAPED_SLASHES));

        $is_public = isset($_POST['drv_public_expert']) ? '1' : '0';
        update_user_meta($user_id, 'drv_public_expert', $is_public);
    }
}
