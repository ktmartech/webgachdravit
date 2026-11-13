<?php
declare(strict_types=1);

namespace Dravit\Core;

use Dravit\Catalog\SearchController;

defined('ABSPATH') || exit;

final class Assets implements ServiceProviderInterface {
    /** @var array<string, bool> */
    private static array $enqueued_entries = [];

    public function register(): void {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_global_assets'], 10);
        add_action('wp_head', [$this, 'print_font_preloads'], 1);
        add_action('wp_head', [$this, 'print_vite_hmr_client'], 2);
        add_action('wp_head', [$this, 'print_modulepreload_links'], 3);
        add_action('wp_head', [$this, 'print_runtime_config'], 4);
    }

    public function print_runtime_config(): void {
        $config = [
            'restUrl'                 => untrailingslashit(rest_url('dravit/v1')),
            'nonceEndpoint'           => untrailingslashit(rest_url('dravit/v1/nonce')),
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
            'zaloPhone'               => drv_clean_phone((string) drv_setting('contact.zalo', '0909888777')),
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
            (bool) drv_setting('features.ai_enabled', true)
            && (
                (function_exists('is_page_template') && is_page_template('page-templates/template-ai-finder.php'))
                || (function_exists('is_page') && is_page('tro-ly-ai-tim-gach'))
            )
        ) {
            self::enqueue_section_style('assets/src/css/pages/ai-finder.css');
            self::enqueue_section_script('assets/src/js/chunks/ai-finder.js');
        }
    }

    public function print_font_preloads(): void {
        if (Vite::is_dev_server_running()) {
            return;
        }

        $body_font_url = DRV_URI . '/assets/dist/fonts/quicksand-vf-vi.woff2';
        printf(
            '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
            esc_url($body_font_url)
        );
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
