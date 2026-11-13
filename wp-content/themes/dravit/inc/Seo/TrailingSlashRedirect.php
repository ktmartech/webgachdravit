<?php
declare(strict_types=1);

namespace Dravit\Seo;

use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Thực thi Redirect 301 chuẩn hóa URL không dấu `/` cuối, khử URL cũ của WooCommerce,
 * chuẩn hóa thứ tự tham số lọc và ép HTTP 404 cho phân trang lố / `pa_*` ẩn (ADR D-21, R-1..R-10, M-9).
 */
final class TrailingSlashRedirect implements ServiceProviderInterface {
    public function register(): void {
        add_filter('user_trailingslashit', [$this, 'untrailingslash_non_root'], 20, 2);
        add_filter('redirect_canonical', [$this, 'filter_wp_redirect_canonical'], 20, 2);
        add_action('template_redirect', [$this, 'handle_request'], 1);

        // Chuẩn hóa mọi permalink sinh từ WP/WC không có dấu `/` cuối.
        $link_filters = [
            'post_link',
            'page_link',
            'post_type_link',
            'term_link',
            'post_type_archive_link',
            'get_pagenum_link',
            'paginate_links',
        ];
        foreach ($link_filters as $filter) {
            add_filter($filter, [$this, 'clean_generated_link'], 20, 1);
        }
    }

    public function untrailingslash_non_root(string $url, string $type): string {
        unset($type);
        if ($url === '/' || $url === '') {
            return $url;
        }

        return UrlNormalizer::untrailingslashUrl($url);
    }

    public function clean_generated_link(mixed $url): mixed {
        if (!is_string($url) || $url === '') {
            return $url;
        }

        return UrlNormalizer::untrailingslashUrl($url);
    }

    /**
     * Ngăn `redirect_canonical()` của WordPress tự gắn lại dấu `/` cuối gây vòng lặp redirect.
     *
     * @param string|false $redirect_url
     * @return string|false
     */
    public function filter_wp_redirect_canonical(string|false $redirect_url, string $requested_url): string|false {
        if ($redirect_url === false || $redirect_url === '') {
            return false;
        }

        $clean_redirect  = UrlNormalizer::untrailingslashUrl($redirect_url);
        $clean_requested = UrlNormalizer::untrailingslashUrl($requested_url);

        if ($clean_redirect === $clean_requested) {
            return false;
        }

        return $clean_redirect;
    }

    public function handle_request(): void {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        $method = isset($_SERVER['REQUEST_METHOD'])
            ? strtoupper(sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_METHOD'])))
            : 'GET';
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return;
        }

        $raw_uri = isset($_SERVER['REQUEST_URI'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_URI']))
            : '/';
        $parsed  = wp_parse_url($raw_uri);
        $req_path = is_array($parsed) && isset($parsed['path']) ? (string) $parsed['path'] : '/';

        // Tách tiền tố thư mục con nếu WP cài trong subdirectory.
        $home_path = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
        $home_path = rtrim($home_path, '/');
        if ($home_path !== '' && str_starts_with($req_path, $home_path)) {
            $req_path = substr($req_path, strlen($home_path));
            if ($req_path === '') {
                $req_path = '/';
            }
        }

        $query_params = [];
        if (is_array($parsed) && isset($parsed['query']) && $parsed['query'] !== '') {
            parse_str((string) $parsed['query'], $query_params);
        }

        $context = $this->build_runtime_context();
        $decision = UrlNormalizer::evaluateRequest($req_path, $query_params, $context);

        if ($decision['action'] === '404') {
            $this->trigger_404();
            return;
        }

        if ($decision['action'] === 'redirect' && is_string($decision['location']) && $decision['location'] !== '') {
            $destination = home_url($decision['location']);
            $destination = UrlNormalizer::untrailingslashUrl($destination);
            wp_safe_redirect($destination, $decision['status']);
            exit;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function build_runtime_context(): array {
        global $wp_query;

        $is_non_public_pa = false;
        if (is_tax()) {
            $queried = get_queried_object();
            if ($queried instanceof \WP_Term && str_starts_with($queried->taxonomy, 'pa_')) {
                if (!in_array($queried->taxonomy, ['pa_bo-suu-tap', 'pa_xuat-xu'], true)) {
                    $is_non_public_pa = true;
                }
            }
        }

        $max_pages = null;
        if ($wp_query instanceof \WP_Query && (is_archive() || is_home())) {
            $max_pages = (int) $wp_query->max_num_pages;
        }

        return [
            'is_non_public_pa_archive' => $is_non_public_pa,
            'max_pages'                => $max_pages,
        ];
    }

    private function trigger_404(): void {
        global $wp_query;
        if ($wp_query instanceof \WP_Query) {
            $wp_query->set_404();
        }
        status_header(404);
        nocache_headers();
    }
}

