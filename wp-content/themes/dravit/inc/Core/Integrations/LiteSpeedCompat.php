<?php
declare(strict_types=1);

namespace Dravit\Core\Integrations;

use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * LiteSpeed Cache tương thích layer (docs/08 §7, D-29, D-30).
 *
 * Gắn cache tags, exclude URI cá nhân hóa, purge khi dữ liệu thay đổi,
 * và đảm bảo REST endpoints nhạy cảm không bị cache.
 */
final class LiteSpeedCompat implements ServiceProviderInterface {

    /** REST routes phải trả no-store (ngoài /nonce đã xử lý riêng trong NonceController). */
    private const NO_STORE_ROUTES = [
        '/dravit/v1/quote/submit',
        '/dravit/v1/lead',
    ];

    /** URI trang cá nhân hóa cần exclude khỏi page cache. */
    private const EXCLUDE_URIS = [
        '/danh-sach-bao-gia',
    ];

    public function register(): void {
        // Frontend: gắn tag hoặc exclude theo request hiện tại.
        add_action('wp', [$this, 'tag_or_exclude_current_request']);

        // Purge khi sản phẩm lưu (tạo/sửa/xóa).
        add_action('save_post_product', [$this, 'on_save_product'], 20, 1);

        // Purge khi CollectionSync hoàn tất đồng bộ hàng loạt.
        add_action('drv/catalog/synced', [$this, 'on_catalog_synced'], 20);

        // REST: đảm bảo no-store cho /quote/submit và /lead.
        add_filter('rest_post_dispatch', [$this, 'maybe_set_rest_no_store'], 20, 3);
    }

    // ─── Frontend tag / exclude ──────────────────────────────

    public function tag_or_exclude_current_request(): void {
        if (is_admin()) {
            return;
        }

        // WC transactional pages: no cache.
        if (drv_is_woo_active() && (is_cart() || is_checkout() || is_account_page())) {
            self::mark_no_cache('transactional-woo-page');
            return;
        }

        // BOQ page: cá nhân hóa localStorage → exclude.
        if (self::is_excluded_uri()) {
            self::mark_no_cache('personalized-page');
            return;
        }

        // Global shell tag cho mọi trang cacheable.
        self::add_cache_tag('drv_global_shell');

        // Archive product & taxonomy product_cat.
        if (drv_is_woo_active() && (is_shop() || is_product_taxonomy())) {
            self::add_cache_tag('drv_catalog');
            return;
        }

        // Single product: tag riêng + catalog.
        if (drv_is_woo_active() && is_product()) {
            $post_id = get_queried_object_id();
            if ($post_id > 0) {
                self::add_cache_tag('drv_prod_' . $post_id);
            }
            self::add_cache_tag('drv_catalog');
            return;
        }
    }

    // ─── Purge hooks ────────────────────────────────────────

    /**
     * Khi sản phẩm lưu: purge tag riêng + catalog tổng.
     */
    public function on_save_product(int $post_id): void {
        if ($post_id <= 0) {
            return;
        }

        // Bỏ qua autosave / revision.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        self::purge_tag('drv_prod_' . $post_id);
        self::purge_tag('drv_catalog');
    }

    /**
     * Khi CollectionSync hoàn tất (action `drv/catalog/synced`).
     */
    public function on_catalog_synced(): void {
        self::purge_tag('drv_catalog');
    }

    // ─── REST no-store ──────────────────────────────────────

    /**
     * Gắn `Cache-Control: no-store` cho REST routes nhạy cảm.
     *
     * @param \WP_REST_Response|\WP_HTTP_Response $result
     * @param \WP_REST_Server                     $server
     * @param \WP_REST_Request                    $request
     * @return \WP_REST_Response|\WP_HTTP_Response
     */
    public function maybe_set_rest_no_store(
        \WP_REST_Response|\WP_HTTP_Response $result,
        \WP_REST_Server $server,
        \WP_REST_Request $request,
    ): \WP_REST_Response|\WP_HTTP_Response {
        $route = $request->get_route();

        foreach (self::NO_STORE_ROUTES as $pattern) {
            if (str_starts_with($route, $pattern)) {
                if ($result instanceof \WP_REST_Response) {
                    $result->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
                    $result->header('Pragma', 'no-cache');
                }
                self::mark_no_cache('rest-no-store');
                break;
            }
        }

        return $result;
    }

    // ─── Static helpers (public API cho các module khác) ─────

    public static function add_cache_tag(string $tag): void {
        if (has_action('litespeed_tag_add')) {
            do_action('litespeed_tag_add', $tag);
        }
    }

    public static function purge_tag(string $tag): void {
        if (has_action('litespeed_purge')) {
            do_action('litespeed_purge', $tag);
        }
    }

    public static function mark_no_cache(string $reason = 'dynamic'): void {
        if (has_action('litespeed_control_set_nocache')) {
            do_action('litespeed_control_set_nocache', $reason);
        }
    }

    // ─── Internal ───────────────────────────────────────────

    /**
     * Kiểm tra URI hiện tại thuộc danh sách exclude.
     */
    private static function is_excluded_uri(): bool {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parse_url extracts path only, no DB/output use.
        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        $path = (string) parse_url((string) $uri, PHP_URL_PATH);
        $path = rtrim($path, '/');

        foreach (self::EXCLUDE_URIS as $exclude) {
            if ($path === $exclude) {
                return true;
            }
        }

        return false;
    }
}
