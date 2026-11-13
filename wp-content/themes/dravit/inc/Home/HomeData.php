<?php
declare(strict_types=1);

namespace Dravit\Home;

use Dravit\Core\Config;
use Dravit\Seo\UrlNormalizer;
use Dravit\Units\PriceState;

defined('ABSPATH') || exit;

/**
 * Bộ giải dữ liệu hiển thị cho 8 module Trang chủ (`01-home.html` -> `S-3`, `docs/07 §3`, `docs/16`, `D-34`).
 * Cam kết: 100% URL tĩnh không dấu `/` cuối, 0 `?param`, 0 hotlink Unsplash/Pexels, 0 truy vấn N+1.
 */
final class HomeData {
    public static function siteUrl(string $path): string {
        $clean_path = '/' . ltrim((string) (parse_url($path, PHP_URL_PATH) ?: '/'), '/');
        if ($clean_path !== '/') {
            $clean_path = rtrim($clean_path, '/');
        }

        if (function_exists('home_url')) {
            return UrlNormalizer::untrailingslashUrl(home_url($clean_path));
        }

        return $clean_path === '/' ? 'https://gachdravit.vn' : ('https://gachdravit.vn' . $clean_path);
    }

    public static function placeholderUrl(string $filename): string {
        return DRV_URI . '/assets/images/placeholders/' . ltrim($filename, '/');
    }

    public static function roomUrl(string $filename): string {
        return DRV_URI . '/assets/images/rooms/' . ltrim($filename, '/');
    }

    public static function attachmentUrl(int $attachment_id, string $size = 'large', string $fallback_placeholder = ''): string {
        if ($attachment_id > 0 && function_exists('wp_get_attachment_image_url')) {
            $url = wp_get_attachment_image_url($attachment_id, $size);
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        if ($fallback_placeholder === '') {
            return '';
        }

        if (str_starts_with($fallback_placeholder, 'rooms/')) {
            return self::roomUrl(substr($fallback_placeholder, 6));
        }

        return self::placeholderUrl($fallback_placeholder);
    }

    public static function collectionCount(string $slug, int $fallback = 0): int {
        if (function_exists('get_term_by') && function_exists('taxonomy_exists') && taxonomy_exists('product_cat')) {
            $term = get_term_by('slug', $slug, 'product_cat');
            if ($term instanceof \WP_Term && (int) $term->count > 0) {
                return (int) $term->count;
            }
        }

        return $fallback;
    }

    /**
     * 8 ô không gian chuẩn `pa_khong-gian` -> 8 Smart Collection URLs tĩnh (`docs/03 §3.1`).
     *
     * @return array<int, array{
     *   slug: string,
     *   name: string,
     *   url: string,
     *   spec: string,
     *   count: int,
     *   img_url: string,
     *   img_w: int,
     *   img_h: int,
     *   layout: 'featured'|'standard'|'wide',
     *   alt: string
     * }>
     */
    public static function getSpaceBentoItems(): array {
        $defs = [
            [
                'slug'        => 'gach-phong-khach',
                'name'        => 'Phòng khách',
                'spec'        => 'Khổ 80×80 · 60×120 · R9 · Ít đường ron',
                'fallback_n'  => 92,
                'placeholder' => 'rooms/room-living-calacatta.jpg',
                'img_w'       => 800,
                'img_h'       => 600,
                'layout'      => 'featured',
                'alt'         => 'Không gian phòng khách lát gạch khổ lớn 80x80 vân đá Calacatta',
            ],
            [
                'slug'        => 'gach-nha-tam',
                'name'        => 'Nhà tắm',
                'spec'        => 'Nhám R10–R11 · DCOF ≥ 0,42 chống trượt',
                'fallback_n'  => 74,
                'placeholder' => 'rooms/room-bathroom-terrazzo.jpg',
                'img_w'       => 600,
                'img_h'       => 375,
                'layout'      => 'standard',
                'alt'         => 'Không gian nhà tắm lát gạch Terrazzo chống trơn R10',
            ],
            [
                'slug'        => 'gach-phong-bep',
                'name'        => 'Phòng bếp',
                'spec'        => 'Men kháng ố cấp 5 · Lau dầu mỡ dễ',
                'fallback_n'  => 58,
                'placeholder' => 'rooms/room-kitchen-cement.jpg',
                'img_w'       => 600,
                'img_h'       => 375,
                'layout'      => 'standard',
                'alt'         => 'Không gian phòng bếp ốp lát gạch vân xi măng kháng ố bẩn',
            ],
            [
                'slug'        => 'gach-phong-ngu',
                'name'        => 'Phòng ngủ',
                'spec'        => 'Vân gỗ 15×90 · Satin ấm êm chân',
                'fallback_n'  => 46,
                'placeholder' => 'rooms/room-bedroom-wood.jpg',
                'img_w'       => 600,
                'img_h'       => 375,
                'layout'      => 'standard',
                'alt'         => 'Không gian phòng ngủ lát gạch vân gỗ 15x90 tông sồi ấm',
            ],
            [
                'slug'        => 'gach-mat-tien',
                'name'        => 'Mặt tiền',
                'spec'        => 'Hút nước ≤ 0,5% · Bền màu nắng mưa',
                'fallback_n'  => 34,
                'placeholder' => 'rooms/room-facade-stone.jpg',
                'img_w'       => 600,
                'img_h'       => 375,
                'layout'      => 'standard',
                'alt'         => 'Mặt tiền công trình ốp đá nung kết khổ lớn chống thấm',
            ],
            [
                'slug'        => 'gach-san-vuon',
                'name'        => 'Sân vườn & Lối đi',
                'spec'        => 'Structured R11–R12 · Dày 12–20mm chịu tải gara',
                'fallback_n'  => 38,
                'placeholder' => 'rooms/room-outdoor-patio.jpg',
                'img_w'       => 600,
                'img_h'       => 260,
                'layout'      => 'wide',
                'alt'         => 'Sân vườn ngoài trời lát gạch nhám sần R11 chịu tải trọng cao',
            ],
            [
                'slug'        => 'gach-ngoai-troi',
                'name'        => 'Ban công & Sân thượng',
                'spec'        => 'Xương Porcelain BIa · Thoát nước nhanh · Kháng rêu',
                'fallback_n'  => 29,
                'placeholder' => 'rooms/room-balcony-terrace.jpg',
                'img_w'       => 600,
                'img_h'       => 260,
                'layout'      => 'wide',
                'alt'         => 'Ban công và sân thượng lát gạch xương đá chống thấm rêu mốc',
            ],
            [
                'slug'        => 'gach-ho-boi',
                'name'        => 'Hồ bơi & Tiểu cảnh nước',
                'spec'        => 'Mosaic thủy tinh & sứ · Nhám R12–R13 · Bền hóa chất clo',
                'fallback_n'  => 22,
                'placeholder' => 'rooms/room-pool-mosaic.jpg',
                'img_w'       => 600,
                'img_h'       => 260,
                'layout'      => 'wide',
                'alt'         => 'Hồ bơi và tiểu cảnh nước ốp lát gạch Mosaic chống hóa chất',
            ],
        ];

        $items = [];
        foreach ($defs as $def) {
            $slug     = $def['slug'];
            $thumb_id = 0;

            if (function_exists('get_term_by') && function_exists('taxonomy_exists') && taxonomy_exists('product_cat')) {
                $term = get_term_by('slug', $slug, 'product_cat');
                if ($term instanceof \WP_Term && function_exists('get_term_meta')) {
                    $thumb_id = (int) get_term_meta($term->term_id, 'thumbnail_id', true);
                }
            }

            $items[] = [
                'slug'    => $slug,
                'name'    => $def['name'],
                'url'     => self::siteUrl('/' . $slug),
                'spec'    => $def['spec'],
                'count'   => self::collectionCount($slug, $def['fallback_n']),
                'img_url' => self::attachmentUrl($thumb_id, 'medium_large', $def['placeholder']),
                'img_w'   => $def['img_w'],
                'img_h'   => $def['img_h'],
                'layout'  => $def['layout'],
                'alt'     => $def['alt'],
            ];
        }

        return $items;
    }

    /**
     * 4 Tab công năng (`Gạch lát nền`, `Gạch ốp tường`, `Gạch trang trí`, `Đá nung kết`) + chips tĩnh + thẻ Lookbook + SP thật.
     *
     * @return array<int, array{
     *   key: string,
     *   tab_id: string,
     *   panel_id: string,
     *   label: string,
     *   url: string,
     *   chips: array<int, array{label: string, url: string, active: bool}>,
     *   lookbook: array{
     *     kicker: string,
     *     title: string,
     *     img_url: string,
     *     img_alt: string,
     *     specs: array<int, array{dt: string, dd: string}>,
     *     cta_label: string,
     *     cta_url: string
     *   },
     *   products: array<int, mixed>
     * }>
     */
    public static function getExplorerTabs(int $per_tab = 3): array {
        $limit = max(1, $per_tab);

        $tab_defs = [
            [
                'key'      => 'floor',
                'slug'     => 'gach-lat-nen',
                'label'    => 'Gạch lát nền',
                'chips'    => [
                    ['label' => 'Tất cả lát nền', 'slug' => 'gach-lat-nen', 'active' => true],
                    ['label' => 'Khổ 80×80', 'slug' => 'gach-80x80', 'active' => false],
                    ['label' => 'Khổ 60×60', 'slug' => 'gach-60x60', 'active' => false],
                    ['label' => 'Khổ lớn 60×120', 'slug' => 'gach-60x120', 'active' => false],
                    ['label' => 'Vân đá Marble', 'slug' => 'gach-van-da', 'active' => false],
                    ['label' => 'Giả gỗ ấm', 'slug' => 'gach-gia-go', 'active' => false],
                    ['label' => 'Vân xi măng', 'slug' => 'gach-van-xi-mang', 'active' => false],
                ],
                'lookbook' => [
                    'kicker'      => 'Phối cảnh sàn chính',
                    'title'       => 'Sàn mở liên thông khách - bếp',
                    'placeholder' => 'rooms/room-living-calacatta.jpg',
                    'img_alt'     => 'Phối cảnh gạch lát nền 80x80 xương đá Porcelain cho phòng khách liên thông bếp',
                    'specs'       => [
                        ['dt' => 'Xương gạch', 'dd' => 'Porcelain BIa (≤ 0,5%)'],
                        ['dt' => 'Khổ chủ đạo', 'dd' => '80×80 cm · 60×120 cm'],
                        ['dt' => 'Bề mặt', 'dd' => 'Matt mịn R10 / Bóng kiếng'],
                        ['dt' => 'Độ rộng ron', 'dd' => '1,5 - 2,0 mm (cạnh mài)'],
                    ],
                    'cta_label'   => 'Xem toàn bộ gạch lát nền',
                ],
            ],
            [
                'key'      => 'wall',
                'slug'     => 'gach-op-tuong',
                'label'    => 'Gạch ốp tường',
                'chips'    => [
                    ['label' => 'Tất cả ốp tường', 'slug' => 'gach-op-tuong', 'active' => true],
                    ['label' => 'Khổ 30×60 chuẩn', 'slug' => 'gach-30x60', 'active' => false],
                    ['label' => 'Khổ 60×120 kịch trần', 'slug' => 'gach-60x120', 'active' => false],
                    ['label' => 'Ốp nhà tắm', 'slug' => 'gach-nha-tam', 'active' => false],
                    ['label' => 'Ốp tường bếp', 'slug' => 'gach-phong-bep', 'active' => false],
                    ['label' => 'Ốp mặt tiền', 'slug' => 'gach-mat-tien', 'active' => false],
                ],
                'lookbook' => [
                    'kicker'      => 'Phối cảnh mảng tường',
                    'title'       => 'Ốp tường nhà tắm & vách bếp sạch dầu mỡ',
                    'placeholder' => 'rooms/room-bathroom-terrazzo.jpg',
                    'img_alt'     => 'Phối cảnh gạch ốp tường 30x60 và 60x120 cho phòng tắm hiện đại',
                    'specs'       => [
                        ['dt' => 'Quy cách chuẩn', 'dd' => '30×60 cm · 60×120 cm'],
                        ['dt' => 'Trọng lượng', 'dd' => 'Nhẹ tường, bám keo C2 tốt'],
                        ['dt' => 'Kháng ố bẩn', 'dd' => 'Cấp 4 - 5 dễ lau chùi'],
                        ['dt' => 'Kiểu mạch', 'dd' => 'Ốp ngang đồng mạch sàn'],
                    ],
                    'cta_label'   => 'Xem toàn bộ gạch ốp tường',
                ],
            ],
            [
                'key'      => 'decor',
                'slug'     => 'gach-trang-tri',
                'label'    => 'Gạch trang trí',
                'chips'    => [
                    ['label' => 'Tất cả trang trí', 'slug' => 'gach-trang-tri', 'active' => true],
                    ['label' => 'Gạch thẻ kiến trúc', 'slug' => 'gach-the', 'active' => false],
                    ['label' => 'Gạch bông Porcelain', 'slug' => 'gach-bong', 'active' => false],
                    ['label' => 'Gạch bông gió', 'slug' => 'gach-bong-gio', 'active' => false],
                    ['label' => 'Gạch Mosaic vỉ', 'slug' => 'gach-mosaic', 'active' => false],
                    ['label' => 'Hạt đá Terrazzo', 'slug' => 'gach-terrazzo', 'active' => false],
                ],
                'lookbook' => [
                    'kicker'      => 'Điểm nhấn kiến trúc',
                    'title'       => 'Mảng nhấn bồn rửa, quầy bar & giếng trời',
                    'placeholder' => 'tile-decor-fluted.svg',
                    'img_alt'     => 'Gạch thẻ rãnh đũa và mosaic trang trí mảng nhấn kiến trúc',
                    'specs'       => [
                        ['dt' => 'Dòng vật liệu', 'dd' => 'Thẻ rãnh đũa · Mosaic · Bông'],
                        ['dt' => 'Hiệu ứng sáng', 'dd' => 'Bắt đèn hắt khe trần 3000K'],
                        ['dt' => 'Chống thấm', 'dd' => 'Xương sứ không cần phủ sáp'],
                        ['dt' => 'Kiểu ốp', 'dd' => 'Dọc so le · Xương cá 45°'],
                    ],
                    'cta_label'   => 'Xem toàn bộ gạch trang trí',
                ],
            ],
            [
                'key'      => 'slab',
                'slug'     => 'da-nung-ket',
                'label'    => 'Đá nung kết',
                'chips'    => [
                    ['label' => 'Tất cả đá nung kết', 'slug' => 'da-nung-ket', 'active' => true],
                    ['label' => 'Khổ 60×120', 'slug' => 'gach-60x120', 'active' => false],
                    ['label' => 'Vân đá tự nhiên', 'slug' => 'gach-van-da', 'active' => false],
                    ['label' => 'Vách TV & Đại sảnh', 'slug' => 'gach-phong-khach', 'active' => false],
                    ['label' => 'Bàn đảo & Mặt bếp', 'slug' => 'gach-phong-bep', 'active' => false],
                    ['label' => 'Mặt tiền biệt thự', 'slug' => 'gach-mat-tien', 'active' => false],
                ],
                'lookbook' => [
                    'kicker'      => 'Vật liệu khổ lớn',
                    'title'       => 'Đá nung kết Big Slab liền mạch vách TV & đảo bếp',
                    'placeholder' => 'tile-sintered-emperador.svg',
                    'img_alt'     => 'Đá nung kết khổ lớn ốp vách tivi và mặt bàn đảo bếp',
                    'specs'       => [
                        ['dt' => 'Độ cứng bề mặt', 'dd' => 'Mohs ≥ 6 chống trầy dao'],
                        ['dt' => 'Độ dày tấm', 'dd' => '9 mm · 12 mm · 15 mm'],
                        ['dt' => 'Chịu nhiệt', 'dd' => 'Đặt nồi nóng trực tiếp'],
                        ['dt' => 'Báo giá', 'dd' => 'Theo lô & bóc tách bản vẽ'],
                    ],
                    'cta_label'   => 'Xem toàn bộ đá nung kết',
                ],
            ],
        ];

        $result = [];
        foreach ($tab_defs as $tab) {
            $slug  = $tab['slug'];
            $chips = [];
            foreach ($tab['chips'] as $chip) {
                $chips[] = [
                    'label'  => $chip['label'],
                    'url'    => self::siteUrl('/' . $chip['slug']),
                    'active' => $chip['active'],
                ];
            }

            $lb = $tab['lookbook'];

            $result[] = [
                'key'      => $tab['key'],
                'tab_id'   => 'tab-exp-' . $tab['key'],
                'panel_id' => 'panel-exp-' . $tab['key'],
                'label'    => $tab['label'],
                'url'      => self::siteUrl('/' . $slug),
                'chips'    => $chips,
                'lookbook' => [
                    'kicker'    => $lb['kicker'],
                    'title'     => $lb['title'],
                    'img_url'   => self::attachmentUrl(0, 'large', $lb['placeholder']),
                    'img_alt'   => $lb['img_alt'],
                    'specs'     => $lb['specs'],
                    'cta_label' => $lb['cta_label'],
                    'cta_url'   => self::siteUrl('/' . $slug),
                ],
                'products' => self::queryProductsByCategory($slug, $limit, $tab['key']),
            ];
        }

        return $result;
    }

    /**
     * Lấy danh sách sản phẩm cho `product-grid-block.php` (chế độ `deals`, `collection`, `custom`, `latest`).
     *
     * @param array<int, int> $product_ids
     * @return array<int, mixed>
     */
    public static function getGridProducts(
        string $mode = 'deals',
        int $limit = 4,
        array $product_ids = [],
        string $category_slug = ''
    ): array {
        $safe_limit = max(1, min(16, $limit));

        if (!function_exists('wc_get_products')) {
            return self::fallbackProducts($mode === 'deals' ? 'deals' : 'floor', $safe_limit);
        }

        $products = [];

        if ($mode === 'custom' && $product_ids !== []) {
            $clean_ids = array_values(array_filter(array_map('intval', $product_ids), static fn (int $v): bool => $v > 0));
            if ($clean_ids !== []) {
                $queried  = wc_get_products([
                    'status'  => 'publish',
                    'include' => $clean_ids,
                    'limit'   => $safe_limit,
                    'orderby' => 'post__in',
                ]);
                $products = is_array($queried) ? $queried : [];
            }
        } elseif ($mode === 'deals') {
            $sale_ids = function_exists('wc_get_product_ids_on_sale') ? wc_get_product_ids_on_sale() : [];
            $sale_ids = is_array($sale_ids)
                ? array_values(array_filter(array_map('intval', $sale_ids), static fn (int $v): bool => $v > 0))
                : [];

            if ($sale_ids !== []) {
                $queried  = wc_get_products([
                    'status'  => 'publish',
                    'include' => $sale_ids,
                    'limit'   => $safe_limit,
                    'orderby' => 'menu_order',
                    'order'   => 'ASC',
                ]);
                $products = is_array($queried) ? $queried : [];
            }

            if (count($products) < $safe_limit) {
                $exclude  = array_map(
                    static fn (mixed $p): int => $p instanceof \WC_Product ? $p->get_id() : 0,
                    $products
                );
                $more = wc_get_products([
                    'status'   => 'publish',
                    'category' => ['gach-uu-dai'],
                    'exclude'  => array_filter($exclude),
                    'limit'    => $safe_limit - count($products),
                ]);
                if (is_array($more) && $more !== []) {
                    $products = array_merge($products, $more);
                }
            }
        } elseif ($category_slug !== '') {
            $queried  = wc_get_products([
                'status'   => 'publish',
                'category' => [$category_slug],
                'limit'    => $safe_limit,
                'orderby'  => 'menu_order',
                'order'    => 'ASC',
            ]);
            $products = is_array($queried) ? $queried : [];
        }

        if ($products === []) {
            $queried  = wc_get_products([
                'status'  => 'publish',
                'limit'   => $safe_limit,
                'orderby' => 'date',
                'order'   => 'DESC',
            ]);
            $products = is_array($queried) ? $queried : [];
        }

        if ($products === []) {
            return self::fallbackProducts($mode === 'deals' ? 'deals' : 'floor', $safe_limit);
        }

        self::primeProductCaches($products);

        return $products;
    }

    /**
     * Cấu hình dữ liệu `<drv-size-guide>` từ `config/size-guide.php` kèm URL tĩnh đã chuẩn hóa.
     *
     * @return array<string, mixed>
     */
    public static function getSizeGuideData(): array {
        $cfg = Config::get('size-guide');
        if ($cfg === []) {
            return [];
        }

        $sizes_out = [];
        $raw_sizes = isset($cfg['sizes']) && is_array($cfg['sizes']) ? $cfg['sizes'] : [];
        foreach ($raw_sizes as $key => $item) {
            if (!is_array($item)) {
                continue;
            }
            $slug             = (string) ($item['collection_slug'] ?? 'gach');
            $sizes_out[$key]  = [
                'label' => (string) ($item['label'] ?? $key),
                'meta'  => (string) ($item['meta'] ?? ''),
                'w'     => (int) ($item['w'] ?? 60),
                'h'     => (int) ($item['h'] ?? 60),
                'rate'  => (string) ($item['rate'] ?? ''),
                'box'   => (string) ($item['box'] ?? ''),
                'grout' => (string) ($item['grout'] ?? ''),
                'url'   => self::siteUrl('/' . $slug),
            ];
        }

        $surfaces_out = [];
        $raw_surfaces = isset($cfg['surfaces']) && is_array($cfg['surfaces']) ? $cfg['surfaces'] : [];
        foreach ($raw_surfaces as $surf) {
            if (!is_array($surf)) {
                continue;
            }
            $slug           = (string) ($surf['collection_slug'] ?? 'gach');
            $swatch         = (string) ($surf['swatch'] ?? 'tile-marble-calacatta.svg');
            $zones          = isset($surf['zones']) && is_array($surf['zones'])
                ? array_values(array_map('strval', $surf['zones']))
                : ['dry'];
            $surfaces_out[] = [
                'key'           => (string) ($surf['key'] ?? ''),
                'zones'         => $zones,
                'name'          => (string) ($surf['name'] ?? ''),
                'badge'         => (string) ($surf['badge'] ?? ''),
                'badge_variant' => (string) ($surf['badge_variant'] ?? 'outline'),
                'desc'          => (string) ($surf['desc'] ?? ''),
                'swatch_url'    => self::placeholderUrl($swatch),
                'url'           => self::siteUrl('/' . $slug),
            ];
        }

        return [
            'default_size'  => (string) ($cfg['default_size'] ?? '80x80'),
            'baseline_size' => (string) ($cfg['baseline_size'] ?? '60x60'),
            'sizes'         => $sizes_out,
            'rooms'         => isset($cfg['rooms']) && is_array($cfg['rooms']) ? $cfg['rooms'] : [],
            'zones'         => isset($cfg['zones']) && is_array($cfg['zones']) ? $cfg['zones'] : [],
            'surfaces'      => $surfaces_out,
        ];
    }

    /**
     * Dữ liệu hiển thị cho `<drv-showroom>` đọc từ `drv_settings.showrooms` & `drv_settings.contact` (`O-10`, `15 §5.5`).
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function getShowroomViewModel(array $overrides = []): array {
        $showrooms = drv_setting('showrooms', []);
        $primary   = (is_array($showrooms) && isset($showrooms[0]) && is_array($showrooms[0]))
            ? $showrooms[0]
            : [];

        $name     = trim((string) ($overrides['name'] ?? $primary['name'] ?? 'Showroom Flagship Sala'));
        $address  = trim((string) ($overrides['address'] ?? $primary['address'] ?? '128 Nguyễn Cơ Thạch, Phường An Khánh, TP.HCM'));
        $phone    = trim((string) ($overrides['phone'] ?? $primary['phone'] ?? drv_setting('contact.hotline', '0909 888 777')));
        $hours    = trim((string) ($overrides['hours'] ?? $primary['hours'] ?? '08:00 - 20:30'));
        $maps_url = trim((string) ($overrides['maps_url'] ?? $primary['maps_url'] ?? ''));

        $after_id = (int) ($overrides['after_img_id'] ?? $primary['after_img_id'] ?? 0);
        $before_id = (int) ($overrides['before_img_id'] ?? $primary['before_img_id'] ?? 0);

        $after_custom = $after_id > 0 ? self::attachmentUrl($after_id, 'large') : '';
        $before_custom = $before_id > 0 ? self::attachmentUrl($before_id, 'large') : '';

        $has_real_pair = ($after_id > 0 && $before_id > 0 && $after_id !== $before_id && $after_custom !== '' && $before_custom !== '' && $after_custom !== $before_custom);

        $main_after = $after_custom !== '' ? $after_custom : self::roomUrl('room-living-calacatta.jpg');
        $main_before = $has_real_pair ? $before_custom : $main_after;

        $status = ShowroomStatus::fromSettings($hours);

        $zones = [
            'slabs' => [
                'label'   => 'Khu trưng bày tấm lớn',
                'icon'    => 'squares-four',
                'active'  => true,
                'slides'  => [
                    [
                        'after'      => $main_after,
                        'before'      => $main_before,
                        'real_before' => $has_real_pair,
                        'thumb'     => $main_after,
                        'alt'       => 'Khu trưng bày tấm gạch lớn 60x120 và 80x80 tại Showroom DRAVIT',
                        'caption'   => 'Kệ cánh lật 120 tấm khổ 80×80, 60×120 & đá nung kết — kéo thanh trượt để so sánh trước và sau khi lát gạch',
                    ],
                    [
                        'after'      => self::roomUrl('room-facade-stone.jpg'),
                        'before'      => self::roomUrl('room-facade-stone.jpg'),
                        'real_before' => false,
                        'thumb'     => self::roomUrl('room-facade-stone.jpg'),
                        'alt'       => 'Sảnh trưng bày gạch vân đá khổ lớn liền mạch',
                        'caption'   => 'Sảnh lát thực tế khổ 60×120 vân đá tự nhiên — dễ hình dung tỷ lệ đường ron trên mảng sàn rộng',
                    ],
                    [
                        'after'      => self::roomUrl('room-bedroom-wood.jpg'),
                        'before'      => self::roomUrl('room-bedroom-wood.jpg'),
                        'real_before' => false,
                        'thumb'     => self::roomUrl('room-bedroom-wood.jpg'),
                        'alt'       => 'Khu phối màu vân đá, giả gỗ và thanh keo chà ron thực tế',
                        'caption'   => 'Bàn phối mẫu: đặt gạch nền cạnh gạch ốp, nẹp đồng & 24 thanh màu keo chà ron thực tế',
                    ],
                ],
            ],
            'bath'  => [
                'label'   => 'Phòng mẫu Bếp · Tắm',
                'icon'    => 'bathtub',
                'active'  => false,
                'slides'  => [
                    [
                        'after'      => self::roomUrl('room-bathroom-terrazzo.jpg'),
                        'before'      => self::roomUrl('room-bathroom-terrazzo.jpg'),
                        'real_before' => false,
                        'thumb'     => self::roomUrl('room-bathroom-terrazzo.jpg'),
                        'alt'       => 'Phòng mẫu nhà tắm lát gạch Terrazzo chống trơn R10',
                        'caption'   => 'Cabin tắm mẫu 6 m²: ốp tường 30×60 sáng không gian + lát sàn nhám R11 an toàn khu ướt',
                    ],
                    [
                        'after'      => self::roomUrl('room-kitchen-cement.jpg'),
                        'before'      => self::roomUrl('room-kitchen-cement.jpg'),
                        'real_before' => false,
                        'thumb'     => self::roomUrl('room-kitchen-cement.jpg'),
                        'alt'       => 'Góc bếp mẫu ốp đá nung kết và gạch kháng ố dầu mỡ',
                        'caption'   => 'Góc bếp mẫu: mặt bàn đảo đá nung kết dày 12 mm + vách bếp men kháng ố dễ lau dầu mỡ',
                    ],
                    [
                        'after'      => self::roomUrl('room-pool-mosaic.jpg'),
                        'before'      => self::roomUrl('room-pool-mosaic.jpg'),
                        'real_before' => false,
                        'thumb'     => self::roomUrl('room-pool-mosaic.jpg'),
                        'alt'       => 'Khu trưng bày gạch Mosaic vỉ và gạch trang trí nhà tắm',
                        'caption'   => 'Khu lavabo & tiểu cảnh mẫu: phối gạch thẻ dọc cùng Mosaic vỉ dưới đèn gương ấm 3000K',
                    ],
                ],
            ],
            'lab'   => [
                'label'   => 'Bàn thử R10 · Đèn 3000K/6500K',
                'icon'    => 'lightbulb',
                'active'  => false,
                'slides'  => [
                    [
                        'after'      => self::roomUrl('room-balcony-terrace.jpg'),
                        'before'      => self::roomUrl('room-balcony-terrace.jpg'),
                        'real_before' => false,
                        'thumb'     => self::roomUrl('room-balcony-terrace.jpg'),
                        'alt'       => 'Khu hộp đèn đổi nhiệt độ màu 3000K - 4000K - 6500K tại Showroom',
                        'caption'   => 'Hộp đèn đổi màu 3000K · 4000K · 6500K — kiểm tra độ lệch tông màu gạch trước khi chốt lô',
                    ],
                    [
                        'after'      => self::roomUrl('room-outdoor-patio.jpg'),
                        'before'      => self::roomUrl('room-outdoor-patio.jpg'),
                        'real_before' => false,
                        'thumb'     => self::roomUrl('room-outdoor-patio.jpg'),
                        'alt'       => 'Khay dốc xịt nước thử độ bám bề mặt gạch nhám R10 đến R12',
                        'caption'   => 'Khay dốc xịt nước thực tế: thử trực tiếp độ bám chân giữa bề mặt R9, R10, R11 và R12',
                    ],
                    [
                        'after'      => self::placeholderUrl('detail-edge-10mm.svg'),
                        'before'      => self::placeholderUrl('detail-edge-10mm.svg'),
                        'real_before' => false,
                        'thumb'     => self::placeholderUrl('detail-edge-10mm.svg'),
                        'alt'       => 'Mẫu cắt mặt ngang xương gạch Porcelain BIa nén đặc độ hút nước dưới 0,5%',
                        'caption'   => 'Mẫu cắt mặt ngang xương gạch Porcelain BIa — kiểm chứng độ đặc và độ hút nước E ≤ 0,5%',
                    ],
                ],
            ],
        ];

        return [
            'name'          => $name,
            'address'       => $address,
            'phone'         => $phone,
            'phone_clean'   => drv_clean_phone($phone),
            'hours'         => $hours !== '' ? $hours : $status['hours_range'],
            'maps_url'      => $maps_url !== '' ? $maps_url : self::siteUrl('/lien-he'),
            'is_ext_map'    => $maps_url !== '' && str_starts_with($maps_url, 'http'),
            'contact_url'   => self::siteUrl('/lien-he'),
            'cover_img'     => self::roomUrl('room-facade-stone.jpg'),
            'has_compare'   => true,
            'has_real_pair' => $has_real_pair,
            'status'        => $status,
            'zones'         => $zones,
        ];
    }

    /**
     * Bài viết Cẩm nang kỹ thuật (`8/4` Magazine Layout: 1 bài Pillar + 3 bài chủ đề + 5 mục tra cứu nhanh `01-05`).
     *
     * @return array{
     *   blog_url: string,
     *   featured: array{title: string, url: string, category: string, excerpt: string, meta: string, img_url: string, img_alt: string},
     *   posts: array<int, array{title: string, url: string, category: string, meta: string, img_url: string, img_alt: string}>,
     *   quick_list: array<int, array{num: string, title: string, meta: string, url: string}>
     * }
     */
    public static function getEditorialGuides(int $limit = 3): array {
        $blog_url   = self::siteUrl('/cam-nang');
        $sub_limit  = max(1, min(6, $limit));
        $real_posts = [];

        if (function_exists('get_posts')) {
            $queried = get_posts([
                'post_type'           => 'post',
                'post_status'         => 'publish',
                'posts_per_page'      => $sub_limit + 6,
                'ignore_sticky_posts' => true,
                'no_found_rows'       => true,
            ]);

            if (is_array($queried)) {
                foreach ($queried as $post) {
                    if (!$post instanceof \WP_Post) {
                        continue;
                    }
                    // Bỏ qua bài mặc định Hello world chưa biên tập của WP mới cài
                    if ($post->post_name === 'hello-world') {
                        continue;
                    }
                    $real_posts[] = self::mapPostToGuideCard($post);
                }
            }
        }

        $fallbacks = self::fallbackEditorialCards($blog_url);

        $featured = $real_posts[0] ?? $fallbacks['featured'];
        $subs     = array_slice($real_posts, 1, $sub_limit);

        if (count($subs) < $sub_limit) {
            foreach ($fallbacks['posts'] as $fb_post) {
                $subs[] = $fb_post;
                if (count($subs) >= $sub_limit) {
                    break;
                }
            }
        }

        $quick_list = [];
        $pool       = array_slice($real_posts, 1 + $sub_limit, 5);
        foreach ($pool as $idx => $p) {
            $quick_list[] = [
                'num'   => sprintf('%02d', $idx + 1),
                'title' => $p['title'],
                'meta'  => $p['meta'],
                'url'   => $p['url'],
            ];
        }

        if (count($quick_list) < 5) {
            foreach ($fallbacks['quick_list'] as $fb_item) {
                if (count($quick_list) >= 5) {
                    break;
                }
                $fb_item['num'] = sprintf('%02d', count($quick_list) + 1);
                $quick_list[]   = $fb_item;
            }
        }

        return [
            'blog_url'   => $blog_url,
            'featured'   => $featured,
            'posts'      => $subs,
            'quick_list' => $quick_list,
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function queryProductsByCategory(string $slug, int $limit, string $fallback_group): array {
        if (!function_exists('wc_get_products')) {
            return self::fallbackProducts($fallback_group, $limit);
        }

        $products = wc_get_products([
            'status'   => 'publish',
            'category' => [$slug],
            'limit'    => $limit,
            'orderby'  => 'menu_order',
            'order'    => 'ASC',
        ]);

        if (!is_array($products) || $products === []) {
            $products = wc_get_products([
                'status'  => 'publish',
                'limit'   => $limit,
                'orderby' => 'date',
                'order'   => 'DESC',
            ]);
        }

        if (!is_array($products) || $products === []) {
            return self::fallbackProducts($fallback_group, $limit);
        }

        self::primeProductCaches($products);

        return $products;
    }

    /**
     * Prime post/meta/term caches 1 lần để triệt tiêu truy vấn N+1 khi render lưới thẻ sản phẩm (`docs/08 §6`).
     *
     * @param array<int, mixed> $products
     */
    private static function primeProductCaches(array $products): void {
        if (!function_exists('update_meta_cache') || !function_exists('update_object_term_cache')) {
            return;
        }

        $ids = [];
        foreach ($products as $p) {
            if ($p instanceof \WC_Product && $p->get_id() > 0) {
                $ids[] = $p->get_id();
            }
        }

        if ($ids !== []) {
            update_meta_cache('post', $ids);
            update_object_term_cache($ids, 'product');
        }
    }

    /**
     * @return array{title: string, url: string, category: string, excerpt: string, meta: string, img_url: string, img_alt: string}
     */
    private static function mapPostToGuideCard(\WP_Post $post): array {
        $post_id = (int) $post->ID;
        $raw_url = function_exists('get_permalink') ? (string) get_permalink($post_id) : ('/cam-nang/' . $post->post_name);
        $url     = UrlNormalizer::untrailingslashUrl($raw_url);

        $cat_name = 'Cẩm nang kỹ thuật';
        if (function_exists('get_the_category')) {
            $cats = get_the_category($post_id);
            if (is_array($cats) && isset($cats[0]) && $cats[0] instanceof \WP_Term) {
                $cat_name = $cats[0]->name;
            }
        }

        $thumb_id = function_exists('get_post_thumbnail_id') ? (int) get_post_thumbnail_id($post_id) : 0;
        $img_url  = self::attachmentUrl($thumb_id, 'medium_large', 'room-living-calacatta.svg');

        $plain_content = function_exists('wp_strip_all_tags')
            ? wp_strip_all_tags((string) $post->post_content)
            : strip_tags((string) $post->post_content);

        $words = str_word_count($plain_content);
        $mins  = max(3, (int) ceil($words / 220));

        $excerpt = trim((string) $post->post_excerpt);
        if ($excerpt === '' && function_exists('wp_trim_words')) {
            $excerpt = wp_trim_words($plain_content, 26, '...');
        }

        return [
            'title'    => (string) $post->post_title,
            'url'      => $url,
            'category' => $cat_name,
            'excerpt'  => $excerpt,
            'meta'     => $mins . ' phút đọc · Đội ngũ Kỹ thuật DRAVIT',
            'img_url'  => $img_url,
            'img_alt'  => (string) $post->post_title,
        ];
    }

    /**
     * @return array{
     *   featured: array{title: string, url: string, category: string, excerpt: string, meta: string, img_url: string, img_alt: string},
     *   posts: array<int, array{title: string, url: string, category: string, meta: string, img_url: string, img_alt: string}>,
     *   quick_list: array<int, array{num: string, title: string, meta: string, url: string}>
     * }
     */
    private static function fallbackEditorialCards(string $blog_url): array {
        return [
            'featured'   => [
                'title'    => 'Chỉ số chống trơn R9, R10, R11, R12 là gì? Bảng chọn đúng chuẩn cho từng phòng',
                'url'      => $blog_url,
                'category' => 'Tiêu chuẩn an toàn',
                'excerpt'  => 'Giải thích thực tế tiêu chuẩn DIN 51130 và DCOF giúp gia chủ chọn đúng bề mặt gạch cho phòng khách, bếp, nhà tắm có người lớn tuổi và sân ướt.',
                'meta'     => '7 phút đọc · Đội ngũ Kỹ thuật DRAVIT',
                'img_url'  => self::placeholderUrl('room-bathroom-terrazzo.svg'),
                'img_alt'  => 'Minh họa tiêu chuẩn chống trơn trượt R9 đến R12 cho sàn nhà tắm và sân vườn',
            ],
            'posts'      => [
                [
                    'title'    => 'Cách tính số thùng gạch 60×60, 80×80, 60×120 và tỷ lệ hao hụt chuẩn',
                    'url'      => $blog_url,
                    'category' => 'Dự toán vật tư',
                    'meta'     => '5 phút đọc · Có bảng tra nhanh',
                    'img_url'  => self::placeholderUrl('detail-grout-line.svg'),
                    'img_alt'  => 'Hướng dẫn tính số thùng gạch và hao hụt thi công theo diện tích thực tế',
                ],
                [
                    'title'    => 'Phân biệt gạch xương đá Porcelain, Semi-Porcelain và Ceramic bằng 3 bước',
                    'url'      => $blog_url,
                    'category' => 'Vật liệu xương gạch',
                    'meta'     => '6 phút đọc · Kiểm chứng thực tế',
                    'img_url'  => self::placeholderUrl('detail-edge-10mm.svg'),
                    'img_alt'  => 'Mặt cắt cạnh gạch xương đá Porcelain nén đặc độ hút nước dưới 0,5%',
                ],
                [
                    'title'    => 'Kinh nghiệm kiểm tra mã lô màu (Shade) và cỡ gạch (Caliber) khi nhận hàng',
                    'url'      => $blog_url,
                    'category' => 'Nghiệm thu công trình',
                    'meta'     => '4 phút đọc · Checklist công trường',
                    'img_url'  => self::placeholderUrl('room-showroom-shelf.svg'),
                    'img_alt'  => 'Kiểm tra tem thông số lô màu Shade và kích thước thực tế Caliber trên vỏ thùng',
                ],
            ],
            'quick_list' => [
                [
                    'num'   => '01',
                    'title' => 'Chọn keo dán gạch C1 hay C2 cho khổ lớn 80×80 và 60×120?',
                    'meta'  => 'Thi công ốp lát · 5 phút đọc',
                    'url'   => $blog_url,
                ],
                [
                    'num'   => '02',
                    'title' => 'Độ rộng đường ron 1,5 mm hay 2 mm để tránh phồng rộp sàn?',
                    'meta'  => 'Kỹ thuật mạch gạch · 4 phút đọc',
                    'url'   => $blog_url,
                ],
                [
                    'num'   => '03',
                    'title' => 'Nhà hướng Tây hoặc thiếu sáng tự nhiên nên phối tông gạch nào?',
                    'meta'  => 'Ánh sáng & Màu sắc · 6 phút đọc',
                    'url'   => $blog_url,
                ],
                [
                    'num'   => '04',
                    'title' => 'Quy tắc lát gạch giả gỗ thanh dài 15×90 lệch 1/4 không bị vênh mép',
                    'meta'  => 'Sàn phòng ngủ · 5 phút đọc',
                    'url'   => $blog_url,
                ],
                [
                    'num'   => '05',
                    'title' => 'Đá nung kết làm mặt bếp cần lưu ý gì khi khoét chậu rửa và bếp từ?',
                    'meta'  => 'Gia công đá tấm · 6 phút đọc',
                    'url'   => $blog_url,
                ],
            ],
        ];
    }

    /**
     * Dữ liệu SP dự phòng khi chạy Unit Test độc lập không nạp WooCommerce DB.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function fallbackProducts(string $group, int $limit): array {
        $catalog_url = self::siteUrl('/gach');
        $pool        = [
            'floor' => [
                [
                    'id'               => 101,
                    'sku'              => 'DRV-8801M',
                    'title'            => 'Gạch Vân Đá Calacatta Gold 80×80 Bóng Kiếng',
                    'url'              => $catalog_url,
                    'price_state'      => PriceState::PRICED,
                    'price_m2'         => 385000,
                    'regular_price_m2' => 385000,
                    'price_piece'      => 246400,
                    'pieces_per_box'   => 3,
                    'm2_per_box'       => 1.92,
                    'stock_boxes'      => 46,
                    'showroom_shelf'   => 'A-04',
                    'img_primary'      => self::placeholderUrl('tile-marble-calacatta.svg'),
                    'img_hover'        => self::roomUrl('room-living-calacatta.jpg'),
                    'tags'             => ['80×80', 'Bóng kiếng', 'R9'],
                ],
                [
                    'id'               => 102,
                    'sku'              => 'DRV-6602C',
                    'title'            => 'Gạch Vân Xi Măng Greige 60×60 Men Matt Mịn',
                    'url'              => $catalog_url,
                    'price_state'      => PriceState::PRICED,
                    'price_m2'         => 295000,
                    'regular_price_m2' => 295000,
                    'price_piece'      => 106200,
                    'pieces_per_box'   => 4,
                    'm2_per_box'       => 1.44,
                    'stock_boxes'      => 62,
                    'showroom_shelf'   => 'B-02',
                    'img_primary'      => self::placeholderUrl('tile-cement-grey.svg'),
                    'img_hover'        => self::roomUrl('room-kitchen-cement.jpg'),
                    'tags'             => ['60×60', 'Men Matt', 'R10'],
                ],
                [
                    'id'               => 103,
                    'sku'              => 'DRV-1590W',
                    'title'            => 'Gạch Giả Gỗ Sồi Tự Nhiên 15×90 Satin Êm Chân',
                    'url'              => $catalog_url,
                    'price_state'      => PriceState::PRICED,
                    'price_m2'         => 340000,
                    'regular_price_m2' => 340000,
                    'price_piece'      => 45900,
                    'pieces_per_box'   => 8,
                    'm2_per_box'       => 1.08,
                    'stock_boxes'      => 38,
                    'showroom_shelf'   => 'C-07',
                    'img_primary'      => self::placeholderUrl('tile-wood-oak.svg'),
                    'img_hover'        => self::roomUrl('room-bedroom-wood.jpg'),
                    'tags'             => ['15×90', 'Vân gỗ', 'R10'],
                ],
            ],
            'deals' => [
                [
                    'id'               => 201,
                    'sku'              => 'DRV-8809S',
                    'title'            => 'Gạch Vân Đá Pietra Grey 80×80 Men Matt Mịn',
                    'url'              => self::siteUrl('/gach-uu-dai'),
                    'price_state'      => PriceState::SALE,
                    'price_m2'         => 315000,
                    'regular_price_m2' => 390000,
                    'price_piece'      => 201600,
                    'pieces_per_box'   => 3,
                    'm2_per_box'       => 1.92,
                    'stock_boxes'      => 18,
                    'low_stock'        => true,
                    'img_primary'      => self::placeholderUrl('tile-cement-dark.svg'),
                    'img_hover'        => self::roomUrl('room-living-calacatta.jpg'),
                    'tags'             => ['80×80', 'Men Matt', 'R10'],
                ],
                [
                    'id'               => 202,
                    'sku'              => 'DRV-6615T',
                    'title'            => 'Gạch Terrazzo Kem Hạt Mịn 60×60 Xương Đá',
                    'url'              => self::siteUrl('/gach-uu-dai'),
                    'price_state'      => PriceState::SALE,
                    'price_m2'         => 265000,
                    'regular_price_m2' => 325000,
                    'price_piece'      => 95400,
                    'pieces_per_box'   => 4,
                    'm2_per_box'       => 1.44,
                    'stock_boxes'      => 24,
                    'img_primary'      => self::placeholderUrl('tile-terrazzo-ivory.svg'),
                    'img_hover'        => self::roomUrl('room-bathroom-terrazzo.jpg'),
                    'tags'             => ['60×60', 'Terrazzo', 'R10'],
                ],
                [
                    'id'               => 203,
                    'sku'              => 'DRV-3608W',
                    'title'            => 'Gạch Ốp Tường Nhà Tắm Vân Đá Trắng 30×60',
                    'url'              => self::siteUrl('/gach-uu-dai'),
                    'price_state'      => PriceState::SALE,
                    'price_m2'         => 235000,
                    'regular_price_m2' => 285000,
                    'price_piece'      => 42300,
                    'pieces_per_box'   => 8,
                    'm2_per_box'       => 1.44,
                    'stock_boxes'      => 30,
                    'img_primary'      => self::placeholderUrl('tile-marble-calacatta.svg'),
                    'img_hover'        => self::roomUrl('room-bathroom-terrazzo.jpg'),
                    'tags'             => ['30×60', 'Men mờ', 'Ốp tường'],
                ],
                [
                    'id'               => 204,
                    'sku'              => 'DRV-6620G',
                    'title'            => 'Gạch Sân Vườn Nhám Định Hình R11 60×60',
                    'url'              => self::siteUrl('/gach-uu-dai'),
                    'price_state'      => PriceState::SALE,
                    'price_m2'         => 280000,
                    'regular_price_m2' => 340000,
                    'price_piece'      => 100800,
                    'pieces_per_box'   => 4,
                    'm2_per_box'       => 1.44,
                    'stock_boxes'      => 15,
                    'low_stock'        => true,
                    'img_primary'      => self::placeholderUrl('tile-structured-outdoor.svg'),
                    'img_hover'        => self::roomUrl('room-outdoor-patio.jpg'),
                    'tags'             => ['60×60', 'Structured', 'R11'],
                ],
            ],
        ];

        $selected = $pool[$group] ?? $pool['floor'];
        if ($group === 'slab') {
            $selected = [
                [
                    'id'             => 104,
                    'sku'            => 'DRV-SL1224',
                    'title'          => 'Đá Nung Kết Emperador Dark 120×240 Dày 12mm',
                    'url'            => self::siteUrl('/da-nung-ket'),
                    'price_state'    => PriceState::QUOTE_ONLY,
                    'price_m2'       => 0,
                    'pieces_per_box' => 1,
                    'm2_per_box'     => 2.88,
                    'unit_label'     => 'tấm',
                    'showroom_shelf' => 'S-01',
                    'img_primary'    => self::placeholderUrl('tile-sintered-emperador.svg'),
                    'img_hover'      => self::roomUrl('room-facade-stone.jpg'),
                    'tags'           => ['120×240', 'Đá nung kết', 'Dày 12mm'],
                ],
                ...array_slice($pool['floor'], 0, 2),
            ];
        }

        return array_slice($selected, 0, $limit);
    }
}
