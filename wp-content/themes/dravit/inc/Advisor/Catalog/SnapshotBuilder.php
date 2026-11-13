<?php
declare(strict_types=1);

namespace Dravit\Advisor\Catalog;

use Dravit\Core\ServiceProviderInterface;
use Dravit\Units\PriceState;

defined('ABSPATH') || exit;

/**
 * Dựng và tự động cập nhật `wp-content/uploads/dravit-cache/catalog-snapshot.json`
 * cho Trợ lý AI Tìm Gạch mà không tạo bảng DB riêng (ADR D-07, D-32, D-49; docs/13 §2.1).
 */
final class SnapshotBuilder implements ServiceProviderInterface {
    public const TRANSIENT_KEY = 'drv_advisor_snapshot_v1';
    public const CRON_HOOK     = 'drv_advisor_nightly_snapshot';

    /** @var array<int, string> */
    private const ATTRIBUTE_KEYS = [
        'pa_cong-nang',
        'pa_khong-gian',
        'pa_van-gach',
        'pa_chat-lieu',
        'pa_kich-thuoc',
        'pa_bo-suu-tap',
        'pa_xuat-xu',
        'pa_mau-sac',
        'pa_be-mat',
        'pa_phong-cach',
        'pa_hinh-dang',
        'pa_dcof',
        'pa_do-hut-nuoc',
        'pa_do-day',
    ];

    private static bool $pendingRebuild = false;

    public function register(): void {
        add_action('woocommerce_after_product_object_save', [$this, 'scheduleShutdownRebuild'], 30, 0);
        add_action('woocommerce_product_set_stock', [$this, 'scheduleShutdownRebuild'], 30, 0);
        add_action('woocommerce_product_set_stock_status', [$this, 'scheduleShutdownRebuild'], 30, 0);
        add_action('deleted_post', [$this, 'onPostDeleted'], 20, 2);
        add_action('drv/catalog/synced', [$this, 'scheduleShutdownRebuild'], 20, 0);
        add_action(self::CRON_HOOK, [$this, 'runCronBuild']);
        add_action('init', [$this, 'ensureScheduledCron'], 30);
        add_action('admin_notices', [$this, 'renderStaleNotice']);
    }

    public function runCronBuild(): void {
        $this->build();
    }

    public function scheduleShutdownRebuild(): void {
        if (self::$pendingRebuild) {
            return;
        }
        self::$pendingRebuild = true;
        SnapshotSearch::flushMemory();
        add_action('shutdown', [$this, 'runShutdownRebuild'], 20);
    }

    public function runShutdownRebuild(): void {
        if (!self::$pendingRebuild) {
            return;
        }
        self::$pendingRebuild = false;
        $this->build();
    }

    public function onPostDeleted(int $postId, \WP_Post $post): void {
        unset($postId);
        if ($post->post_type === 'product') {
            $this->scheduleShutdownRebuild();
        }
    }

    public function ensureScheduledCron(): void {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
            return;
        }
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 3600, 'daily', self::CRON_HOOK);
        }
    }

    public function renderStaleNotice(): void {
        if (!function_exists('current_user_can') || !current_user_can('manage_woocommerce')) {
            return;
        }
        if (!(bool) drv_setting('features.ai_enabled', false)) {
            return;
        }

        $path = self::snapshotFilePath();
        if ($path === '' || !is_readable($path)) {
            return;
        }

        $mtime = (int) filemtime($path);
        if ($mtime > 0 && (time() - $mtime) > DAY_IN_SECONDS) {
            echo '<div class="notice notice-warning is-dismissible"><p>'
                . esc_html('Snapshot dữ liệu Trợ lý AI Tìm Gạch đã quá 24 giờ chưa cập nhật. Bạn có thể lưu lại một sản phẩm hoặc chạy lệnh WP-CLI `wp dravit build-ai-snapshot` để làm mới.')
                . '</p></div>';
        }
    }

    /**
     * Dựng lại toàn bộ snapshot từ WooCommerce, lưu file JSON + transient.
     *
     * @return array<string, mixed>
     */
    public function build(): array {
        $items = [];

        if (function_exists('wc_get_products')) {
            $page = 1;
            do {
                /** @var array<int, \WC_Product> $batch */
                $batch = wc_get_products([
                    'limit'  => 100,
                    'page'   => $page,
                    'status' => ['publish'],
                    'return' => 'objects',
                ]);

                foreach ($batch as $product) {
                    $normalized = self::normalizeProduct($product);
                    if ($normalized !== null) {
                        $items[] = $normalized;
                    }
                }
                $page++;
            } while ($batch !== []);
        }

        $builtAt = gmdate('c');
        $hashSrc = (string) wp_json_encode($items);
        $version = substr(hash('sha256', $hashSrc), 0, 12);

        $snapshot = [
            'version'  => $version,
            'built_at' => $builtAt,
            'ts'       => time(),
            'count'    => count($items),
            'items'    => $items,
        ];

        $filePath = self::snapshotFilePath();
        if ($filePath !== '') {
            $dir = dirname($filePath);
            if (!is_dir($dir) && function_exists('wp_mkdir_p')) {
                wp_mkdir_p($dir);
            }
            if (is_dir($dir) && is_writable($dir)) {
                $encoded = wp_json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (is_string($encoded)) {
                    $tmpFile = $filePath . '.tmp.' . wp_generate_password(6, false);
                    if (@file_put_contents($tmpFile, $encoded) !== false) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                        @rename($tmpFile, $filePath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.rename_rename
                    }
                }
            }
        }

        if (function_exists('set_transient')) {
            set_transient(self::TRANSIENT_KEY, $snapshot, DAY_IN_SECONDS);
        }

        SnapshotSearch::primeMemory($snapshot);

        return $snapshot;
    }

    /**
     * Chuẩn hóa 1 đối tượng `\WC_Product` thành bản ghi snapshot gọn nhẹ.
     *
     * @return array<string, mixed>|null
     */
    public static function normalizeProduct(\WC_Product $product): ?array {
        $id = (int) $product->get_id();
        if ($id <= 0) {
            return null;
        }

        $line = (string) $product->get_meta('drv_line', true);
        if ($line !== '' && $line !== 'gach') {
            return null;
        }

        $sku   = trim((string) $product->get_sku());
        $title = trim((string) $product->get_name());
        if ($title === '') {
            return null;
        }

        $state      = PriceState::resolve($product);
        $regM2      = max(0, (int) $product->get_meta('drv_price_m2', true));
        $saleM2     = max(0, (int) $product->get_meta('drv_sale_price_m2', true));
        $isSale     = ($state === PriceState::SALE && $saleM2 > 0 && $saleM2 < $regM2);
        $effM2      = ($state === PriceState::QUOTE_ONLY) ? 0 : ($isSale ? $saleM2 : $regM2);
        $wMm        = (float) $product->get_meta('drv_piece_w_mm', true);
        $hMm        = (float) $product->get_meta('drv_piece_h_mm', true);
        $thickMm    = (float) $product->get_meta('drv_thickness_mm', true);
        $pcsPerBox  = max(1, (int) $product->get_meta('drv_pieces_per_box', true));
        $kgPerBox   = (float) $product->get_meta('drv_kg_per_box', true);
        $waterAbs   = $product->get_meta('drv_water_absorption', true);
        $waterFloat = ($waterAbs !== '' && $waterAbs !== null) ? (float) $waterAbs : null;
        $roundMode  = (string) $product->get_meta('drv_round_mode', true);
        $roundMode  = in_array($roundMode, ['box', 'piece'], true) ? $roundMode : 'box';
        $qtyMin     = max(1, (int) $product->get_meta('drv_qty_min', true));
        $series     = trim((string) $product->get_meta('drv_series', true));
        $shelf      = trim((string) $product->get_meta('drv_showroom_shelf', true));
        $blurb      = trim((string) $product->get_meta('drv_ai_blurb', true));

        $areaPiece = ($wMm > 0.0 && $hMm > 0.0) ? round(($wMm * $hMm) / 1000000.0, 6) : 0.0;
        $areaBox   = $areaPiece > 0.0 ? round($areaPiece * $pcsPerBox, 4) : 0.0;

        $attrs = [];
        foreach (self::ATTRIBUTE_KEYS as $tax) {
            $attrs[$tax] = self::readTermSlugs($id, $tax);
        }

        // Bổ sung suy diễn dự phòng nếu sản phẩm chưa kịp chạy derived taxonomy
        if ($attrs['pa_kich-thuoc'] === [] && $wMm > 0.0 && $hMm > 0.0) {
            $derivedSize = self::deriveSizeSlug($wMm, $hMm);
            if ($derivedSize !== '') {
                $attrs['pa_kich-thuoc'] = [$derivedSize];
            }
        }
        if ($attrs['pa_do-day'] === [] && $thickMm > 0.0) {
            $attrs['pa_do-day'] = [self::deriveThicknessSlug($thickMm)];
        }
        if ($attrs['pa_do-hut-nuoc'] === [] && $waterFloat !== null) {
            $attrs['pa_do-hut-nuoc'] = [self::deriveWaterSlug($waterFloat)];
        }

        $dcofSlug  = $attrs['pa_dcof'][0] ?? '';
        $dcofLevel = self::dcofSlugToLevel($dcofSlug);

        $collections = self::readTermSlugs($id, 'product_cat');
        $flags       = self::readTermSlugs($id, 'drv_flag');
        $hasShowroom = $shelf !== '' || in_array('showroom', $flags, true);

        $stockQty    = $product->managing_stock() ? (int) $product->get_stock_quantity() : null;
        $stockStatus = (string) $product->get_stock_status();
        $thumbId     = (int) $product->get_image_id();
        $imgPrimary  = ($thumbId > 0 && function_exists('wp_get_attachment_image_url'))
            ? (string) wp_get_attachment_image_url($thumbId, 'woocommerce_thumbnail')
            : '';

        return [
            'id'                 => $id,
            'sku'                => $sku,
            'title'              => $title,
            'url'                => untrailingslashit((string) $product->get_permalink()),
            'img_primary'        => $imgPrimary,
            'short_description'  => wp_strip_all_tags((string) $product->get_short_description()),
            'ai_blurb'           => $blurb,
            'series'             => $series,
            'showroom_shelf'     => $shelf,
            'has_showroom'       => $hasShowroom,
            'price_state'        => $state,
            'price_m2'           => ($state === PriceState::QUOTE_ONLY) ? null : $regM2,
            'sale_price_m2'      => $isSale ? $saleM2 : null,
            'effective_price_m2' => ($state === PriceState::QUOTE_ONLY) ? null : $effM2,
            'w_mm'               => $wMm,
            'h_mm'               => $hMm,
            'thickness_mm'       => $thickMm,
            'pieces_per_box'     => $pcsPerBox,
            'area_piece'         => $areaPiece,
            'area_box'           => $areaBox,
            'kg_per_box'         => $kgPerBox,
            'round_mode'         => $roundMode,
            'qty_min'            => $qtyMin,
            'water_absorption'   => $waterFloat,
            'dcof_slug'          => $dcofSlug,
            'dcof_level'         => $dcofLevel,
            'stock_status'       => $stockStatus,
            'stock_qty_pieces'   => $stockQty,
            'collections'        => $collections,
            'attrs'              => $attrs,
        ];
    }

    public static function snapshotFilePath(): string {
        if (function_exists('wp_upload_dir')) {
            $uploads = wp_upload_dir();
            if (is_array($uploads) && empty($uploads['error']) && !empty($uploads['basedir'])) {
                return rtrim((string) $uploads['basedir'], '/\\') . '/dravit-cache/catalog-snapshot.json';
            }
        }

        return '';
    }

    /**
     * @return array<int, string>
     */
    private static function readTermSlugs(int $productId, string $taxonomy): array {
        if (!function_exists('wp_get_post_terms') || !function_exists('taxonomy_exists') || !taxonomy_exists($taxonomy)) {
            return [];
        }

        $terms = wp_get_post_terms($productId, $taxonomy, ['fields' => 'slugs']);
        if (!is_array($terms)) {
            return [];
        }

        $slugs = [];
        foreach ($terms as $slug) {
            if (is_string($slug) && $slug !== '') {
                $slugs[] = $slug;
            }
        }

        return array_values(array_unique($slugs));
    }

    private static function dcofSlugToLevel(string $slug): int {
        return match (strtolower($slug)) {
            'r9'    => 9,
            'r10'   => 10,
            'r11'   => 11,
            'r12'   => 12,
            'r13'   => 13,
            default => 0,
        };
    }

    private static function deriveSizeSlug(float $wMm, float $hMm): string {
        $minCm = min($wMm, $hMm) / 10.0;
        $maxCm = max($wMm, $hMm) / 10.0;
        $fmt   = static function (float $cm): string {
            if (abs($cm - round($cm)) < 0.01) {
                return (string) (int) round($cm);
            }
            return str_replace('.', '-', rtrim(rtrim(number_format($cm, 1, '.', ''), '0'), '.'));
        };

        return $fmt($minCm) . 'x' . $fmt($maxCm);
    }

    private static function deriveThicknessSlug(float $thickMm): string {
        if ($thickMm <= 6.0) {
            return 'mong';
        }
        if ($thickMm < 12.0) {
            return 'tieu-chuan';
        }
        if ($thickMm < 18.0) {
            return 'day';
        }
        return 'sieu-day-20mm';
    }

    private static function deriveWaterSlug(float $waterPct): string {
        if ($waterPct <= 0.5) {
            return 'duoi-0-5';
        }
        if ($waterPct <= 3.0) {
            return '0-5-3';
        }
        if ($waterPct <= 6.0) {
            return '3-6';
        }
        if ($waterPct <= 10.0) {
            return '6-10';
        }
        return 'tren-10';
    }
}
