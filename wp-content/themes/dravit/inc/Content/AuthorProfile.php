<?php
declare(strict_types=1);

namespace Dravit\Content;

use Dravit\Core\ServiceProviderInterface;
use Dravit\Seo\UrlNormalizer;

defined('ABSPATH') || exit;

/**
 * Quản lý hồ sơ nhân sự chuyên môn E-E-A-T (`O-13`, docs/20 §2.2):
 * - Cấm bịa đặt tên nhân sự giả hoặc dùng ảnh stock/Gravatar ngoài.
 * - Khi tác giả chưa khai báo `drv_job_title`, tự động fallback trung thực về tổ chức `Ban Kỹ thuật DRAVIT`.
 * - Định danh `Person.url` trỏ về `/gioi-thieu#person-{user_id}` khi tắt Author Archive.
 */
final class AuthorProfile implements ServiceProviderInterface {
    /** Đang dựng Admin Bar: ảnh đại diện mặc định phải vuông, không dùng logo ngang (bị méo trong khung tròn). */
    private static bool $square_default = false;

    public function register(): void {
        add_action('admin_bar_menu', static function (): void {
            self::$square_default = true;
        }, 0);
        add_action('admin_bar_menu', static function (): void {
            self::$square_default = false;
        }, 9999);

        // Chặn tuyệt đối mọi kết nối ra gravatar.com (bảo vệ LCP & quyền riêng tư theo docs/20 §2.2.A).
        add_filter('pre_get_avatar_data', [$this, 'filter_avatar_data'], 20, 2);
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function filter_avatar_data(array $args, mixed $id_or_email): array {
        $user_id = 0;
        if (is_numeric($id_or_email)) {
            $user_id = (int) $id_or_email;
        } elseif ($id_or_email instanceof \WP_User) {
            $user_id = (int) $id_or_email->ID;
        } elseif ($id_or_email instanceof \WP_Post) {
            $user_id = (int) $id_or_email->post_author;
        } elseif ($id_or_email instanceof \WP_Comment) {
            $user_id = (int) $id_or_email->user_id;
        }

        $avatar_url          = self::resolveAvatarUrl($user_id);
        $args['url']         = $avatar_url;
        $args['found_avatar'] = true;

        return $args;
    }

    public static function resolveAvatarUrl(int $user_id): string {
        if ($user_id > 0 && function_exists('get_user_meta') && function_exists('wp_get_attachment_image_url')) {
            $att_id = (int) get_user_meta($user_id, 'drv_avatar_id', true);
            if ($att_id > 0) {
                $url = wp_get_attachment_image_url($att_id, 'thumbnail');
                if (is_string($url) && $url !== '') {
                    return $url;
                }
            }
        }

        return (self::$square_default || (function_exists('is_admin') && is_admin()))
            ? DRV_URI . '/assets/images/avatar-admin.svg'
            : DRV_URI . '/assets/images/logo-navy@1x.webp';
    }

    /**
     * Chuẩn hóa mảng URL hồ sơ nghề nghiệp `drv_same_as` (tối đa 5 URL hợp lệ).
     *
     * @return array<int, string>
     */
    public static function parseSameAs(mixed $raw): array {
        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '') {
                return [];
            }
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                $raw = preg_split('/[\r\n,]+/', $trimmed) ?: [];
            }
        }

        if (!is_array($raw)) {
            return [];
        }

        $urls = [];
        foreach ($raw as $candidate) {
            $clean = function_exists('esc_url_raw')
                ? esc_url_raw(trim((string) $candidate))
                : (filter_var(trim((string) $candidate), FILTER_VALIDATE_URL) ?: '');
            if (is_string($clean) && $clean !== '' && str_starts_with($clean, 'http') && !in_array($clean, $urls, true)) {
                $urls[] = $clean;
            }
            if (count($urls) >= 5) {
                break;
            }
        }

        return $urls;
    }

    /**
     * Dựng hồ sơ chuyên gia từ dữ liệu thô (thuần, phục vụ cả Runtime & Unit Test).
     *
     * @param array<string, mixed> $data
     * @return array{
     *   id: int,
     *   name: string,
     *   job_title: string,
     *   credentials: string,
     *   avatar_url: string,
     *   url: string,
     *   same_as: array<int, string>,
     *   is_organization: bool
     * }
     */
    public static function buildProfileFromData(array $data, string $site_origin = ''): array {
        $origin      = self::resolveOrigin($site_origin);
        $user_id     = max(0, (int) ($data['id'] ?? 0));
        $name        = trim((string) ($data['name'] ?? ''));
        $job_title   = trim((string) ($data['job_title'] ?? ''));
        $credentials = trim((string) ($data['credentials'] ?? ''));
        $avatar_url  = trim((string) ($data['avatar_url'] ?? ''));
        $same_as     = self::parseSameAs($data['same_as'] ?? []);

        // Quy tắc trung thực 20 §2.2.C: Chưa có chức danh chuyên môn thật hoặc là user admin mặc định -> Ban Kỹ thuật DRAVIT.
        $is_generic_admin = in_array(mb_strtolower($name, 'UTF-8'), ['admin', 'administrator', 'quản trị viên'], true);
        if ($user_id <= 0 || $name === '' || $job_title === '' || $is_generic_admin) {
            return self::organizationFallback($origin);
        }

        return [
            'id'              => $user_id,
            'name'            => $name,
            'job_title'       => $job_title,
            'credentials'     => $credentials !== ''
                ? $credentials
                : 'Chuyên viên kỹ thuật vật liệu ốp lát và kiểm soát đồng bộ mã lô xuất kho tại DRAVIT.',
            'avatar_url'      => $avatar_url !== '' ? $avatar_url : (DRV_URI . '/assets/images/logo-navy@1x.webp'),
            'url'             => $origin . '/gioi-thieu#person-' . $user_id,
            'same_as'         => $same_as,
            'is_organization' => false,
        ];
    }

    /**
     * Fallback tổ chức trung thực khi chưa gán hồ sơ nhân sự riêng (`O-13`).
     *
     * @return array{
     *   id: int,
     *   name: string,
     *   job_title: string,
     *   credentials: string,
     *   avatar_url: string,
     *   url: string,
     *   same_as: array<int, string>,
     *   is_organization: bool
     * }
     */
    public static function organizationFallback(string $site_origin = ''): array {
        $origin = self::resolveOrigin($site_origin);

        return [
            'id'              => 0,
            'name'            => 'Ban Kỹ thuật DRAVIT',
            'job_title'       => 'Bộ phận Kiểm định Vật liệu & Tư vấn Thi công',
            'credentials'     => 'Biên soạn dựa trên tiêu chuẩn cơ lý TCVN 7745, ISO 13006, DIN 51130 và quy trình kiểm soát đồng bộ mã lô xuất kho của DRAVIT.',
            'avatar_url'      => DRV_URI . '/assets/images/logo-navy@1x.webp',
            'url'             => $origin . '/gioi-thieu#doi-ngu',
            'same_as'         => [],
            'is_organization' => true,
        ];
    }

    /**
     * Lấy hồ sơ nhân sự từ ID user WordPress (trả `null` nếu không phải chuyên gia thật).
     *
     * @return array{
     *   id: int,
     *   name: string,
     *   job_title: string,
     *   credentials: string,
     *   avatar_url: string,
     *   url: string,
     *   same_as: array<int, string>,
     *   is_organization: bool
     * }|null
     */
    public static function resolveVerifiedUser(int $user_id, string $site_origin = ''): ?array {
        if ($user_id <= 0 || !function_exists('get_userdata') || !function_exists('get_user_meta')) {
            return null;
        }

        $user = get_userdata($user_id);
        if (!$user instanceof \WP_User) {
            return null;
        }

        $job_title   = trim((string) get_user_meta($user_id, 'drv_job_title', true));
        $credentials = trim((string) get_user_meta($user_id, 'drv_credentials', true));
        if ($credentials === '') {
            $credentials = trim((string) $user->description);
        }

        $profile = self::buildProfileFromData([
            'id'          => $user_id,
            'name'        => (string) $user->display_name,
            'job_title'   => $job_title,
            'credentials' => $credentials,
            'avatar_url'  => self::resolveAvatarUrl($user_id),
            'same_as'     => get_user_meta($user_id, 'drv_same_as', true),
        ], $site_origin);

        return $profile['is_organization'] ? null : $profile;
    }

    /**
     * Giải hồ sơ Tác giả của bài viết (có fallback về `Ban Kỹ thuật DRAVIT`).
     *
     * @return array{
     *   id: int,
     *   name: string,
     *   job_title: string,
     *   credentials: string,
     *   avatar_url: string,
     *   url: string,
     *   same_as: array<int, string>,
     *   is_organization: bool
     * }
     */
    public static function resolvePostAuthor(\WP_Post|int $post, string $site_origin = ''): array {
        $post_obj = is_int($post) && function_exists('get_post') ? get_post($post) : $post;
        if (!$post_obj instanceof \WP_Post) {
            return self::organizationFallback($site_origin);
        }

        $verified = self::resolveVerifiedUser((int) $post_obj->post_author, $site_origin);

        return $verified ?? self::organizationFallback($site_origin);
    }

    /**
     * Giải hồ sơ Kỹ sư Thẩm định của bài viết (`drv_reviewer_id`, trả `null` nếu chưa gán).
     *
     * @return array{
     *   id: int,
     *   name: string,
     *   job_title: string,
     *   credentials: string,
     *   avatar_url: string,
     *   url: string,
     *   same_as: array<int, string>,
     *   is_organization: bool,
     *   reviewed_at: string
     * }|null
     */
    public static function resolvePostReviewer(\WP_Post|int $post, string $site_origin = ''): ?array {
        $post_id = $post instanceof \WP_Post ? $post->ID : (int) $post;
        if ($post_id <= 0 || !function_exists('get_post_meta')) {
            return null;
        }

        $reviewer_id = (int) get_post_meta($post_id, 'drv_reviewer_id', true);
        if ($reviewer_id <= 0) {
            return null;
        }

        $verified = self::resolveVerifiedUser($reviewer_id, $site_origin);
        if ($verified === null) {
            return null;
        }

        $reviewed_at = trim((string) get_post_meta($post_id, 'drv_reviewed_at', true));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $reviewed_at) !== 1) {
            $reviewed_at = '';
        }

        $verified['reviewed_at'] = $reviewed_at;

        return $verified;
    }

    /**
     * Danh sách chuyên gia công khai cho khu vực `#doi-ngu` trên trang `/gioi-thieu` (docs/20 §2.2.B).
     *
     * @return array<int, array{
     *   id: int,
     *   name: string,
     *   job_title: string,
     *   credentials: string,
     *   avatar_url: string,
     *   url: string,
     *   same_as: array<int, string>,
     *   is_organization: bool
     * }>
     */
    public static function getPublicExperts(string $site_origin = ''): array {
        if (!function_exists('get_users')) {
            return [];
        }

        $users = get_users([
            'meta_key'   => 'drv_public_expert',
            'meta_value' => '1',
            'number'     => 12,
            'orderby'    => 'display_name',
            'order'      => 'ASC',
        ]);

        if (!is_array($users) || $users === []) {
            return [];
        }

        $experts = [];
        foreach ($users as $user) {
            if (!$user instanceof \WP_User) {
                continue;
            }
            $resolved = self::resolveVerifiedUser((int) $user->ID, $site_origin);
            if ($resolved !== null) {
                $experts[] = $resolved;
            }
        }

        return $experts;
    }

    private static function resolveOrigin(string $site_origin): string {
        if ($site_origin !== '') {
            return rtrim($site_origin, '/');
        }
        if (function_exists('home_url')) {
            return UrlNormalizer::untrailingslashUrl((string) home_url('/'));
        }

        return 'https://gachdravit.vn';
    }
}
