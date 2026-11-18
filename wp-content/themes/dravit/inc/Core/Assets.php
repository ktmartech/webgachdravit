<?php
declare(strict_types=1);

namespace Dravit\Core;

use Dravit\Catalog\SearchController;

defined('ABSPATH') || exit;

final class Assets implements ServiceProviderInterface {
    /** @var array<string, bool> */
    private static array $enqueued_entries = [];

    /** @var array<string, string> Mapping template → critical CSS key. */
    private const CRITICAL_MAP = [
        'home'     => 'home',
        'catalog'  => 'catalog',
        'product'  => 'product',
        'blog'     => 'blog',
        'commerce' => 'commerce',
        'page'     => 'page',
    ];

    public function register(): void {
        add_action('wp_head', [$this, 'print_critical_css'], 1);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_global_assets'], 10);
        add_action('wp_head', [$this, 'print_font_preloads'], 1);
        add_action('wp_head', [$this, 'print_vite_hmr_client'], 2);
        add_action('wp_head', [$this, 'print_modulepreload_links'], 3);
        add_action('wp_head', [$this, 'print_runtime_config'], 4);
    }

    /**
     * Detect current template key and inline critical CSS.
     * Hook: wp_head priority 1.
     */
    public function print_critical_css(): void {
        $key = $this->detect_template_key();
        if ($key !== '') {
            self::inline_critical_css($key);
        }
    }

    /**
     * Detect template key dựa trên conditional tags.
     */
    private function detect_template_key(): string {
        if (function_exists('is_front_page') && is_front_page()) {
            return 'home';
        }

        if (
            drv_is_woo_active()
            && (
                (function_exists('is_shop') && is_shop())
                || (function_exists('is_product_category') && is_product_category())
                || (function_exists('is_tax') && is_tax(['pa_bo-suu-tap', 'pa_xuat-xu']))
            )
        ) {
            return 'catalog';
        }

        if (drv_is_woo_active() && function_exists('is_product') && is_product()) {
            return 'product';
        }

        if (
            drv_is_woo_active()
            && (
                (function_exists('is_cart') && is_cart())
                || (function_exists('is_checkout') && is_checkout())
                || (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received'))
            )
        ) {
            return 'commerce';
        }

        if (
            (function_exists('is_home') && is_home())
            || (function_exists('is_category') && is_category())
            || (function_exists('is_tag') && is_tag())
            || (function_exists('is_singular') && is_singular('post'))
        ) {
            return 'blog';
        }

        return 'page';
    }

    /**
     * Inline critical CSS vào <head> từ file source.
     *
     * Đọc assets/src/css/critical/critical-{$template_key}.css và output
     * <style id="drv-critical-{key}">...</style>.
     * Dùng cho trước khi full CSS được tải bất đồng bộ.
     */
    public static function inline_critical_css(string $template_key): void {
        $allowed = array_keys(self::CRITICAL_MAP);
        if (!in_array($template_key, $allowed, true)) {
            return;
        }

        $file = DRV_DIR . '/assets/src/css/critical/critical-' . $template_key . '.css';
        if (!is_file($file)) {
            return;
        }

        $css = file_get_contents($file); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file
        if ($css === false || $css === '') {
            return;
        }

        $key = esc_attr($template_key);
        echo '<style id="drv-critical-' . $key . '">' . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS from theme source
    }

    public function print_runtime_config(): void {
        $config = [
            'restUrl'                 => untrailingslashit(rest_url('dravit/v1')),
            'nonceEndpoint'           => untrailingslashit(rest_url('dravit/v1/nonce')),
            'catalogEndpoint'         => untrailingslashit(rest_url('dravit/v1/catalog')),
            'productsEndpoint'        => untrailingslashit(rest_url('dravit/v1/products')),
            'wcStoreCartUrl'          => untrailingslashit(rest_url('wc/store/v1/cart')),
            'wcStoreAddUrl'           => untrailingslashit(rest_url('wc/store/v1/cart/add-item')),
            'searchEndpoint'          => untrailingslashit(rest_url('dravit/v1/search')),
            'searchHitEndpoint'       => untrailingslashit(rest_url('dravit/v1/search/hit')),
            'advisorEndpoint'         => untrailingslashit(rest_url('dravit/v1/advisor')),
            'advisorFeedbackEndpoint' => untrailingslashit(rest_url('dravit/v1/advisor/feedback')),
            'aiEnabled'               => (bool) drv_setting('features.ai_enabled', true),
            'searchInitial'           => SearchController::getInitialPayload(),
            'homeUrl'                 => untrailingslashit(home_url('/')),
            'gachUrl'                 => untrailingslashit(home_url('/gach')),
            'boqUrl'                  => untrailingslashit(home_url('/danh-sach-bao-gia')),
            'aiFinderUrl'             => untrailingslashit(home_url('/tro-ly-ai-tim-gach')),
            'zaloPhone'               => drv_clean_phone((string) drv_setting('contact.zalo', '')),
            'quoteRoles'              => array_values(array_map('strval', (array) drv_setting('quote.roles', []))),
        ];
        $json = wp_json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($json) && $json !== '') {
            echo '<script>window.DRV_CONFIG=' . $json . ';</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe JSON via wp_json_encode
        }
    }

    public function enqueue_global_assets(): void {
        self::enqueue_style('drv-global', 'assets/src/css/global.css');
        self::enqueue_module('drv-core', 'assets/src/js/core.js');

        if (function_exists('is_page_template') && is_page_template('page-templates/template-ui-kit.php')) {
            self::enqueue_style('drv-page-ui-kit', 'assets/src/css/pages/ui-kit.css');
        }

        if (
            drv_is_woo_active()
            && (
                (function_exists('is_shop') && is_shop())
                || (function_exists('is_product_category') && is_product_category())
                || (function_exists('is_tax') && is_tax(['pa_bo-suu-tap', 'pa_xuat-xu']))
            )
        ) {
            self::enqueue_style('drv-page-catalog', 'assets/src/css/pages/catalog.css');
            self::enqueue_module('drv-catalog', 'assets/src/js/catalog.js');
        }

        if (
            drv_is_woo_active()
            && function_exists('is_product')
            && is_product()
        ) {
            self::enqueue_style('drv-page-product', 'assets/src/css/pages/product.css');
            self::enqueue_module('drv-product', 'assets/src/js/product.js');
        }

        // Step 9: Giỏ hàng & Checkout VN (Classic Shortcode D-15) — CSS/JS riêng, không nạp trên trang khác.
        if (drv_is_woo_active() && function_exists('is_cart') && is_cart()) {
            self::enqueue_style('drv-page-cart', 'assets/src/css/pages/cart.css');
            self::enqueue_module('drv-cart', 'assets/src/js/cart.js');
        }
        if (
            drv_is_woo_active()
            && function_exists('is_checkout') && is_checkout()
            && (!function_exists('is_wc_endpoint_url') || !is_wc_endpoint_url('order-received'))
        ) {
            self::enqueue_style('drv-page-checkout', 'assets/src/css/pages/checkout.css');
            self::enqueue_module('drv-checkout', 'assets/src/js/checkout.js');
        }
        // Đặt hàng thành công: chỉ CSS (nút sao chép dùng action `copy-text` của core.js), không nạp checkout.js.
        if (drv_is_woo_active() && function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received')) {
            self::enqueue_style('drv-page-order', 'assets/src/css/pages/order.css');
        }
        // Tài khoản khi chưa đăng nhập: đăng nhập · đăng ký · quên/đặt lại mật khẩu.
        if (drv_is_woo_active() && function_exists('is_account_page') && is_account_page() && !is_user_logged_in()) {
            self::enqueue_style('drv-page-account', 'assets/src/css/pages/account.css');
            self::enqueue_module('drv-account', 'assets/src/js/account.js');
        }

        // Nạp CSS/JS trong <head> (tránh FOUC) cho 2 trang Step 8; SectionRegistry vẫn tự dedupe.
        if (function_exists('is_page_template') && is_page_template('page-templates/template-quote.php')) {
            self::enqueue_section_style('assets/src/css/pages/quote.css');
            self::enqueue_section_script('assets/src/js/quote.js');
        }
        if (function_exists('is_page_template') && is_page_template('page-templates/template-contact.php')) {
            self::enqueue_section_style('assets/src/css/pages/contact.css');
        }

        if (
            (bool) drv_setting('features.ai_enabled', true)
            && (
                (function_exists('is_page_template') && is_page_template('page-templates/template-ai-finder.php'))
                || (function_exists('is_page') && is_page('tro-ly-ai-tim-gach'))
            )
        ) {
            self::enqueue_section_style('assets/src/css/pages/ai-finder.css');
            self::enqueue_section_script('assets/src/js/chunks/ai-finder.js');
        }

        // Step 10: Cẩm nang Chọn gạch GEO, Chính sách pháp lý, Giới thiệu & 404.
        $is_blog_search = function_exists('is_search')
            && is_search()
            && function_exists('get_query_var')
            && (get_query_var('post_type') === 'post' || get_query_var('post_type') === ['post']);

        $is_blog_or_policy_interactive = (function_exists('is_home') && is_home())
            || (function_exists('is_category') && is_category())
            || (function_exists('is_tag') && is_tag())
            || (function_exists('is_singular') && is_singular('post'))
            || (function_exists('is_page_template') && is_page_template('page-templates/template-policy.php'));

        if ($is_blog_or_policy_interactive) {
            self::enqueue_style('drv-page-blog', 'assets/src/css/pages/blog.css');
            self::enqueue_module('drv-blog', 'assets/src/js/blog.js');
        } elseif (
            $is_blog_search
            || (function_exists('is_page_template') && is_page_template('page-templates/template-about.php'))
            || (function_exists('is_page') && is_page('gioi-thieu'))
        ) {
            self::enqueue_style('drv-page-blog', 'assets/src/css/pages/blog.css');
        }

        if (function_exists('is_404') && is_404()) {
            self::enqueue_section_style('assets/src/css/pages/contact.css');
        }
    }

    public function print_font_preloads(): void {
        if (Vite::is_dev_server_running()) {
            return;
        }

        $fonts = array_filter([
            self::dist_font_url('quicksand-vf-vi'),
            self::dist_font_url('phosphor-subset'),
        ]);
        foreach ($fonts as $font_url) {
            printf(
                '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
                esc_url($font_url)
            );
        }
    }

    /**
     * URL font đã build (`assets/dist/fonts/{name}-{hash}.woff2`) để preload trùng khớp URL mà CSS tham chiếu.
     * Vite xóa sạch `dist/` mỗi lần build nên chỉ còn 1 bản hash; fallback tên không hash cho bản build cũ.
     */
    public static function dist_font_url(string $basename): string {
        static $cache = [];
        if (isset($cache[$basename])) {
            return $cache[$basename];
        }

        $dir   = DRV_DIR . '/assets/dist/fonts/';
        $found = glob($dir . $basename . '-*.woff2') ?: [];
        if ($found === [] && is_file($dir . $basename . '.woff2')) {
            $found = [$dir . $basename . '.woff2'];
        }

        $cache[$basename] = $found !== [] ? DRV_URI . '/assets/dist/fonts/' . basename((string) $found[0]) : '';
        return $cache[$basename];
    }

    public static function enqueue_style(string $handle, string $entry): void {
        if (isset(self::$enqueued_entries[$entry])) {
            return;
        }

        $url = Vite::asset_url($entry);
        if ($url === null || !function_exists('wp_enqueue_style')) {
            return;
        }

        self::$enqueued_entries[$entry] = true;
        wp_enqueue_style($handle, $url, [], null);
    }

    public static function enqueue_module(string $handle, string $entry): void {
        if (isset(self::$enqueued_entries[$entry])) {
            return;
        }

        $url = Vite::asset_url($entry);
        if ($url === null) {
            return;
        }

        self::$enqueued_entries[$entry] = true;

        if (function_exists('wp_enqueue_script_module')) {
            wp_enqueue_script_module($handle, $url, [], null);
            return;
        }

        if (function_exists('wp_enqueue_script')) {
            wp_enqueue_script($handle, $url, [], null, ['strategy' => 'defer', 'in_footer' => true]);
        }
    }

    /**
     * Phục vụ SectionRegistry (Phương án S-3, ADR D-34): nạp CSS riêng của section nếu có.
     */
    public static function enqueue_section_style(string $entry): void {
        $handle = 'drv-sec-' . sanitize_key(basename($entry, '.css'));
        self::enqueue_style($handle, $entry);
    }

    /**
     * Phục vụ SectionRegistry (Phương án S-3, ADR D-34): nạp JS module riêng của section nếu có.
     */
    public static function enqueue_section_script(string $entry): void {
        $handle = 'drv-sec-' . sanitize_key(basename($entry, '.js'));
        self::enqueue_module($handle, $entry);
    }

    public function print_vite_hmr_client(): void {
        if (!Vite::is_dev_server_running()) {
            return;
        }

        $client_url = Vite::dev_server_url() . '/@vite/client';
        printf('<script type="module" src="%s"></script>' . "\n", esc_url($client_url));
    }

    public function print_modulepreload_links(): void {
        $preloads = [];
        foreach (array_keys(self::$enqueued_entries) as $entry) {
            foreach (Vite::preload_urls($entry) as $preload_url) {
                $preloads[$preload_url] = true;
            }
        }

        foreach (array_keys($preloads) as $url) {
            printf('<link rel="modulepreload" href="%s">' . "\n", esc_url($url));
        }
    }
}
