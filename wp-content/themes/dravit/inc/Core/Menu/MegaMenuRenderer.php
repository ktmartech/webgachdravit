<?php
declare(strict_types=1);

namespace Dravit\Core\Menu;

use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Render & Cache Mega Menu 4+1 (Desktop) + Mobile Accordion từ WP Menu `mega_menu`
 * hoặc tự động fallback sang `config/collections.php` (28 Smart Collections tĩnh, 0 `?param`, 0 `/` cuối).
 */
final class MegaMenuRenderer implements ServiceProviderInterface {
    public const TRANSIENT_KEY = 'drv_mega_html';

    /** @var array{desktop: string, mobile: string}|null */
    private static ?array $memory_cache = null;

    public function register(): void {
        $purge = [self::class, 'purge_cache'];
        add_action('created_product_cat', $purge);
        add_action('edited_product_cat', $purge);
        add_action('delete_product_cat', $purge);
        add_action('drv/catalog/synced', $purge);
        add_action('update_option_drv_settings', $purge);
        add_action('wp_update_nav_menu', $purge);
        add_action('wp_delete_nav_menu', $purge);
    }

    public static function purge_cache(): void {
        self::$memory_cache = null;
        if (function_exists('delete_transient')) {
            delete_transient(self::TRANSIENT_KEY);
        }
        if (function_exists('do_action')) {
            do_action('litespeed_purge', 'drv_global_shell');
        }
    }

    /**
     * @return array{desktop: string, mobile: string}
     */
    public static function get_fragments(bool $bypass_cache = false): array {
        if (!$bypass_cache && self::$memory_cache !== null) {
            return self::$memory_cache;
        }

        if (!$bypass_cache && function_exists('get_transient')) {
            $cached = get_transient(self::TRANSIENT_KEY);
            if (is_array($cached) && isset($cached['desktop'], $cached['mobile']) && is_string($cached['desktop']) && is_string($cached['mobile'])) {
                self::$memory_cache = [
                    'desktop' => $cached['desktop'],
                    'mobile'  => $cached['mobile'],
                ];
                return self::$memory_cache;
            }
        }

        $columns   = self::resolve_columns();
        $fragments = [
            'desktop' => self::render_desktop_html($columns),
            'mobile'  => self::render_mobile_html($columns),
        ];

        self::$memory_cache = $fragments;
        if (!$bypass_cache && function_exists('set_transient')) {
            $ttl = defined('DAY_IN_SECONDS') ? (int) DAY_IN_SECONDS : 86400;
            set_transient(self::TRANSIENT_KEY, $fragments, $ttl);
        }

        return $fragments;
    }

    /**
     * Ưu tiên đọc cây WP Menu tại vùng `mega_menu`; nếu chưa gán hoặc rỗng thì fallback về `config/collections.php`.
     *
     * @return array<int, array{
     *   heading: string,
     *   url: string,
     *   subgroups: array<int, array{
     *     title: string,
     *     icon: string,
     *     url: string,
     *     items: array<int, array{
     *       label: string,
     *       url: string,
     *       slug: string,
     *       count: int,
     *       badge: string,
     *       glyph: string,
     *       swatch: string,
     *       preview_badge: string,
     *       preview_title: string,
     *       preview_desc: string,
     *       preview_price: string,
     *       preview_img: string
     *     }>
     *   }>
     * }>
     */
    public static function resolve_columns(): array {
        $from_wp_menu = self::build_columns_from_wp_menu();
        if ($from_wp_menu !== []) {
            return $from_wp_menu;
        }

        return self::build_columns_from_config();
    }

    /**
     * Đọc cấu trúc 3 tầng từ WP Menu gán vào vị trí `mega_menu`.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function build_columns_from_wp_menu(): array {
        if (!function_exists('get_nav_menu_locations') || !function_exists('wp_get_nav_menu_items')) {
            return [];
        }

        $locations = get_nav_menu_locations();
        $menu_id   = isset($locations['mega_menu']) ? (int) $locations['mega_menu'] : 0;
        if ($menu_id <= 0) {
            return [];
        }

        $raw_items = wp_get_nav_menu_items($menu_id, ['update_post_term_cache' => false]);
        if (!is_array($raw_items) || $raw_items === []) {
            return [];
        }

        $collections = self::load_collections_config();
        $by_parent   = [];
        foreach ($raw_items as $item) {
            if (!is_object($item) || !isset($item->ID)) {
                continue;
            }
            $parent_id               = isset($item->menu_item_parent) ? (int) $item->menu_item_parent : 0;
            $by_parent[$parent_id][] = $item;
        }

        $top_cols = $by_parent[0] ?? [];
        if ($top_cols === []) {
            return [];
        }

        $columns = [];
        foreach (array_slice($top_cols, 0, 4) as $col_item) {
            $col_id       = (int) $col_item->ID;
            $col_children = $by_parent[$col_id] ?? [];
            $subgroups    = [];
            $loose_items  = [];

            foreach ($col_children as $lvl2) {
                $lvl2_id       = (int) $lvl2->ID;
                $lvl3_children = $by_parent[$lvl2_id] ?? [];

                if ($lvl3_children !== []) {
                    $sub_items = [];
                    foreach ($lvl3_children as $lvl3) {
                        $sub_items[] = self::normalize_menu_leaf($lvl3, $collections);
                    }
                    $sub_icon    = function_exists('get_post_meta') ? (string) get_post_meta($lvl2_id, '_drv_icon', true) : '';
                    $subgroups[] = [
                        'title' => (string) ($lvl2->title ?? ''),
                        'icon'  => $sub_icon !== '' ? $sub_icon : 'ph-squares-four',
                        'url'   => MenuSetup::sanitize_menu_url((string) ($lvl2->url ?? '/gach'), true),
                        'items' => $sub_items,
                    ];
                } else {
                    $loose_items[] = self::normalize_menu_leaf($lvl2, $collections);
                }
            }

            if ($loose_items !== []) {
                $col_icon    = function_exists('get_post_meta') ? (string) get_post_meta($col_id, '_drv_icon', true) : '';
                $subgroups[] = [
                    'title' => (string) ($col_item->title ?? 'Danh mục'),
                    'icon'  => $col_icon !== '' ? $col_icon : 'ph-squares-four',
                    'url'   => MenuSetup::sanitize_menu_url((string) ($col_item->url ?? '/gach'), true),
                    'items' => $loose_items,
                ];
            }

            if ($subgroups !== []) {
                $columns[] = [
                    'heading'   => (string) ($col_item->title ?? ''),
                    'url'       => MenuSetup::sanitize_menu_url((string) ($col_item->url ?? '/gach'), true),
                    'subgroups' => $subgroups,
                ];
            }
        }

        return $columns;
    }

    /**
     * Xây dựng 4 cột chuẩn chứa đầy đủ 27 danh mục con (+ `/gach` = 28 Smart Collections) từ `config/collections.php`.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function build_columns_from_config(): array {
        $collections = self::load_collections_config();

        $blueprint = [
            [
                'heading'   => '01. Theo công năng (7)',
                'slug'      => 'gach-lat-nen',
                'subgroups' => [
                    [
                        'title' => 'Lát nền & Ốp tường',
                        'icon'  => 'ph-grid-four',
                        'slug'  => 'gach-lat-nen',
                        'slugs' => ['gach-lat-nen', 'gach-op-tuong', 'gach-uu-dai'],
                    ],
                    [
                        'title' => 'Trang trí & Mỹ thuật',
                        'icon'  => 'ph-sparkle',
                        'slug'  => 'gach-trang-tri',
                        'slugs' => ['gach-trang-tri', 'gach-bong', 'gach-bong-gio', 'gach-the'],
                    ],
                ],
            ],
            [
                'heading'   => '02. Theo không gian (8)',
                'slug'      => 'gach-phong-khach',
                'subgroups' => [
                    [
                        'title' => 'Không gian nội thất',
                        'icon'  => 'ph-armchair',
                        'slug'  => 'gach-phong-khach',
                        'slugs' => ['gach-phong-khach', 'gach-phong-ngu', 'gach-phong-bep', 'gach-nha-tam'],
                    ],
                    [
                        'title' => 'Sân vườn & Ngoại thất',
                        'icon'  => 'ph-sun',
                        'slug'  => 'gach-san-vuon',
                        'slugs' => ['gach-san-vuon', 'gach-ngoai-troi', 'gach-mat-tien', 'gach-ho-boi'],
                    ],
                ],
            ],
            [
                'heading'   => '03. Vân & Chất liệu (7)',
                'slug'      => 'gach-van-da',
                'subgroups' => [
                    [
                        'title' => 'Họa tiết bề mặt',
                        'icon'  => 'ph-paint-brush',
                        'slug'  => 'gach-van-da',
                        'slugs' => ['gach-van-da', 'gach-gia-go', 'gach-terrazzo', 'gach-van-xi-mang'],
                    ],
                    [
                        'title' => 'Xương đá & Mosaic',
                        'icon'  => 'ph-diamonds-four',
                        'slug'  => 'da-nung-ket',
                        'slugs' => ['da-nung-ket', 'gach-porcelain', 'gach-mosaic'],
                    ],
                ],
            ],
            [
                'heading'   => '04. Kích thước chuẩn (5)',
                'slug'      => 'gach-80x80',
                'subgroups' => [
                    [
                        'title' => 'Khổ lớn & Thanh dài',
                        'icon'  => 'ph-arrows-out',
                        'slug'  => 'gach-60x120',
                        'slugs' => ['gach-60x120', 'gach-15x80-20x100'],
                    ],
                    [
                        'title' => 'Khổ tiêu chuẩn',
                        'icon'  => 'ph-bounding-box',
                        'slug'  => 'gach-80x80',
                        'slugs' => ['gach-80x80', 'gach-60x60', 'gach-30x60'],
                    ],
                ],
            ],
        ];

        $columns = [];
        foreach ($blueprint as $col_def) {
            $subgroups = [];
            foreach ($col_def['subgroups'] as $sg_def) {
                $items = [];
                foreach ($sg_def['slugs'] as $slug) {
                    if (!isset($collections[$slug]) || !is_array($collections[$slug])) {
                        continue;
                    }
                    $items[] = self::build_leaf_from_collection($slug, $collections[$slug]);
                }
                if ($items !== []) {
                    $subgroups[] = [
                        'title' => $sg_def['title'],
                        'icon'  => $sg_def['icon'],
                        'url'   => self::collection_url($sg_def['slug']),
                        'items' => $items,
                    ];
                }
            }

            $columns[] = [
                'heading'   => $col_def['heading'],
                'url'       => self::collection_url($col_def['slug']),
                'subgroups' => $subgroups,
            ];
        }

        return $columns;
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     */
    private static function render_desktop_html(array $columns): string {
        $root_url    = self::collection_url('gach');
        $hotline_raw = (string) drv_setting('contact.hotline', '0909 888 777');
        $contact_url = self::internal_url('/lien-he#showroom');

        $first_leaf = self::first_leaf($columns);
        $col_count  = count($columns);

        ob_start();
        ?>
        <div id="drv-mega-menu" class="drv-mega" hidden aria-label="Ma trận 28 danh mục gạch ốp lát">
            <div class="l-container">
                <div class="drv-mega__matrix">
                    <?php foreach ($columns as $col_idx => $col) : ?>
                        <div class="drv-mega__col">
                            <span class="drv-mega__heading"><?php echo esc_html((string) ($col['heading'] ?? '')); ?></span>
                            <?php
                            $subgroups = isset($col['subgroups']) && is_array($col['subgroups']) ? $col['subgroups'] : [];
                            foreach ($subgroups as $sg_idx => $sg) :
                                $sg_icon  = trim((string) ($sg['icon'] ?? 'ph-squares-four'));
                                $sg_items = isset($sg['items']) && is_array($sg['items']) ? $sg['items'] : [];
                                ?>
                                <div class="drv-mega__subgroup">
                                    <span class="drv-mega__subheading">
                                        <i class="ph <?php echo esc_attr($sg_icon); ?> drv-icon--xs u-text-teal" aria-hidden="true"></i>
                                        <span><?php echo esc_html((string) ($sg['title'] ?? '')); ?></span>
                                    </span>
                                    <ul class="drv-mega__links">
                                        <?php foreach ($sg_items as $item_idx => $leaf) :
                                            $is_first = ($col_idx === 0 && $sg_idx === 0 && $item_idx === 0);
                                            $classes  = 'drv-mega__link' . ($is_first ? ' is-active' : '');
                                            $glyph    = (string) ($leaf['glyph'] ?? '');
                                            $swatch   = (string) ($leaf['swatch'] ?? '');
                                            $count    = (int) ($leaf['count'] ?? 0);
                                            $badge    = (string) ($leaf['badge'] ?? '');
                                            ?>
                                            <li>
                                                <a href="<?php echo esc_url((string) ($leaf['url'] ?? $root_url)); ?>"
                                                   class="<?php echo esc_attr($classes); ?>"
                                                   data-drv-preview-img="<?php echo esc_url((string) ($leaf['preview_img'] ?? '')); ?>"
                                                   data-drv-preview-badge="<?php echo esc_attr((string) ($leaf['preview_badge'] ?? '')); ?>"
                                                   data-drv-preview-title="<?php echo esc_attr((string) ($leaf['preview_title'] ?? '')); ?>"
                                                   data-drv-preview-desc="<?php echo esc_attr((string) ($leaf['preview_desc'] ?? '')); ?>"
                                                   data-drv-preview-price="<?php echo esc_attr((string) ($leaf['preview_price'] ?? '')); ?>">
                                                    <span class="drv-mega__label">
                                                        <?php if ($glyph !== '') : ?>
                                                            <span class="drv-size-glyph drv-size-glyph--<?php echo esc_attr($glyph); ?>" aria-hidden="true"></span>
                                                        <?php elseif ($swatch !== '') : ?>
                                                            <span class="drv-swatch-dot drv-swatch-dot--<?php echo esc_attr($swatch); ?>" aria-hidden="true"></span>
                                                        <?php endif; ?>
                                                        <span><?php echo esc_html((string) ($leaf['label'] ?? '')); ?></span>
                                                    </span>
                                                    <?php if ($badge !== '') : ?>
                                                        <span class="drv-mega__badge"><?php echo esc_html($badge); ?></span>
                                                    <?php elseif ($count > 0) : ?>
                                                        <span class="drv-mega__count"><?php echo esc_html((string) $count); ?></span>
                                                    <?php endif; ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endforeach; ?>

                            <?php if ($col_idx === $col_count - 1) : ?>
                                <a href="<?php echo esc_url($root_url); ?>" class="drv-link u-text-xs">
                                    <span>Xem tất cả 28 danh mục</span>
                                    <i class="ph ph-arrow-right drv-icon--xs" aria-hidden="true"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="drv-mega__col drv-mega__col--featured">
                        <div class="l-stack l-stack--sm">
                            <div class="l-cluster l-cluster--between">
                                <span id="drv-mega-preview-badge" class="drv-badge drv-badge--moss" data-mega-out="badge"><?php echo esc_html($first_leaf['preview_badge']); ?></span>
                                <span id="drv-mega-preview-price" class="u-text-xs u-bold u-text-sale u-tabular" data-mega-out="price"><?php echo esc_html($first_leaf['preview_price']); ?></span>
                            </div>
                            <img id="drv-mega-preview-img"
                                 data-mega-out="img"
                                 src="<?php echo esc_url($first_leaf['preview_img']); ?>"
                                 width="240"
                                 height="145"
                                 loading="lazy"
                                 decoding="async"
                                 alt="<?php echo esc_attr($first_leaf['preview_title']); ?>"
                                 class="u-rounded-sm u-bd drv-mega-preview__img">
                            <h3 id="drv-mega-preview-title" class="u-text-base u-bold" data-mega-out="title"><?php echo esc_html($first_leaf['preview_title']); ?></h3>
                            <p id="drv-mega-preview-desc" class="u-text-xs u-text-muted" data-mega-out="desc"><?php echo esc_html($first_leaf['preview_desc']); ?></p>
                        </div>
                        <a id="drv-mega-preview-btn" data-mega-out="cta" href="<?php echo esc_url($first_leaf['url']); ?>" class="drv-btn drv-btn--sm drv-btn--full">
                            <span>Xem danh mục</span>
                            <i class="ph ph-arrow-right drv-icon--xs" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>

                <div class="drv-mega__bottom">
                    <div class="l-cluster l-cluster--sm">
                        <span class="u-text-xs u-text-muted">
                            <i class="ph ph-headset drv-icon--xs u-text-teal" aria-hidden="true"></i>
                            <span>Hotline bóc tách số lượng theo bản vẽ:</span>
                            <strong class="u-text-navy u-tabular"><?php echo esc_html($hotline_raw); ?></strong>
                        </span>
                    </div>
                    <div class="l-cluster l-cluster--sm">
                        <a href="<?php echo esc_url($contact_url); ?>" class="drv-link u-text-xs">
                            <i class="ph ph-map-pin drv-icon--xs" aria-hidden="true"></i>
                            <span>Xem mẫu thực tế tại Showroom</span>
                        </a>
                        <button type="button" class="drv-btn drv-btn--soft drv-btn--sm" data-drv-action="dialog-open" data-drv-target="#drv-quote-modal">
                            <i class="ph ph-file-text drv-icon--xs" aria-hidden="true"></i>
                            <span>Báo giá chiết khấu công trình</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     */
    private static function render_mobile_html(array $columns): string {
        $root_url        = self::collection_url('gach');
        $utility_entries = DropdownWalker::get_utility_entries();

        ob_start();
        foreach ($columns as $col_idx => $col) :
            $subgroups = isset($col['subgroups']) && is_array($col['subgroups']) ? $col['subgroups'] : [];
            ?>
            <div class="drv-mob-group">
                <span class="drv-mob-group__title"><?php echo esc_html((string) ($col['heading'] ?? '')); ?></span>
                <?php foreach ($subgroups as $sg_idx => $sg) :
                    $is_open    = ($col_idx === 0 && $sg_idx === 0);
                    $sg_icon    = trim((string) ($sg['icon'] ?? 'ph-squares-four'));
                    $sg_items   = isset($sg['items']) && is_array($sg['items']) ? $sg['items'] : [];
                    $group_sum  = 0;
                    foreach ($sg_items as $leaf) {
                        $group_sum += (int) ($leaf['count'] ?? 0);
                    }
                    ?>
                    <details class="drv-mob-accordion"<?php echo $is_open ? ' open' : ''; ?>>
                        <summary class="drv-mob-accordion__summary">
                            <span class="l-cluster l-cluster--xs">
                                <i class="ph <?php echo esc_attr($sg_icon); ?> drv-icon" aria-hidden="true"></i>
                                <span><?php echo esc_html((string) ($sg['title'] ?? '')); ?></span>
                            </span>
                            <span class="l-cluster l-cluster--xs">
                                <?php if ($group_sum > 0) : ?>
                                    <span class="drv-mega__badge"><?php echo esc_html((string) $group_sum); ?></span>
                                <?php endif; ?>
                                <i class="ph ph-caret-down drv-mob-accordion__caret" aria-hidden="true"></i>
                            </span>
                        </summary>
                        <div class="drv-mob-accordion__body">
                            <div class="drv-mob-sublist">
                                <?php foreach ($sg_items as $leaf) :
                                    $count = (int) ($leaf['count'] ?? 0);
                                    ?>
                                    <a href="<?php echo esc_url((string) ($leaf['url'] ?? $root_url)); ?>" class="drv-mob-sublink">
                                        <span><?php echo esc_html((string) ($leaf['label'] ?? '')); ?></span>
                                        <?php if ($count > 0) : ?>
                                            <span class="u-text-xs u-text-muted u-tabular"><?php echo esc_html((string) $count); ?></span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <div class="drv-mob-group">
            <span class="drv-mob-group__title">Tiện ích &amp; Showroom</span>
            <div class="drv-mob-sublist">
                <a href="<?php echo esc_url($root_url); ?>" class="drv-mob-viewall">
                    <span>Xem tất cả 28 danh mục gạch</span>
                    <i class="ph ph-arrow-right" aria-hidden="true"></i>
                </a>
                <?php foreach ($utility_entries as $entry) : ?>
                    <a href="<?php echo esc_url($entry['url']); ?>" class="drv-mob-sublink">
                        <span class="l-cluster l-cluster--xs">
                            <?php if ($entry['icon'] !== '') : ?>
                                <i class="ph <?php echo esc_attr($entry['icon']); ?> drv-icon" aria-hidden="true"></i>
                            <?php endif; ?>
                            <span><?php echo esc_html($entry['label']); ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param object                              $item
     * @param array<string, array<string, mixed>> $collections
     * @return array<string, mixed>
     */
    private static function normalize_menu_leaf(object $item, array $collections): array {
        $item_id = isset($item->ID) ? (int) $item->ID : 0;
        $raw_url = isset($item->url) ? (string) $item->url : '/gach';
        $url     = MenuSetup::sanitize_menu_url($raw_url, true);
        $path    = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $parts   = $path !== '' ? explode('/', $path) : [];
        $slug    = end($parts) ?: 'gach';

        $col_cfg = isset($collections[$slug]) && is_array($collections[$slug]) ? $collections[$slug] : [];
        $base    = self::build_leaf_from_collection($slug, $col_cfg);

        $label = isset($item->title) && trim((string) $item->title) !== '' ? (string) $item->title : $base['label'];
        $count = $base['count'];

        if (isset($item->object, $item->object_id) && $item->object === 'product_cat' && function_exists('get_term')) {
            $term = get_term((int) $item->object_id, 'product_cat');
            if (is_object($term) && !is_wp_error($term) && property_exists($term, 'count')) {
                $count = (int) $term->count;
            }
        }

        $meta = static function (string $key) use ($item_id): string {
            if ($item_id <= 0 || !function_exists('get_post_meta')) {
                return '';
            }
            return trim((string) get_post_meta($item_id, $key, true));
        };

        $badge   = $meta('_drv_badge');
        $p_badge = $meta('_drv_preview_badge');
        $p_price = $meta('_drv_preview_price');
        $p_desc  = $meta('_drv_preview_desc');
        $p_img   = $meta('_drv_preview_img');

        return [
            'label'         => $label,
            'url'           => $url,
            'slug'          => $slug,
            'count'         => $count,
            'badge'         => $badge,
            'glyph'         => $base['glyph'],
            'swatch'        => $base['swatch'],
            'preview_badge' => $p_badge !== '' ? $p_badge : $base['preview_badge'],
            'preview_title' => $col_cfg !== [] && isset($col_cfg['h1']) ? (string) $col_cfg['h1'] : $label,
            'preview_desc'  => $p_desc !== '' ? $p_desc : $base['preview_desc'],
            'preview_price' => $p_price !== '' ? $p_price : $base['preview_price'],
            'preview_img'   => $p_img !== '' ? self::resolve_preview_image($p_img) : $base['preview_img'],
        ];
    }

    /**
     * @param array<string, mixed> $cfg
     * @return array{
     *   label: string,
     *   url: string,
     *   slug: string,
     *   count: int,
     *   badge: string,
     *   glyph: string,
     *   swatch: string,
     *   preview_badge: string,
     *   preview_title: string,
     *   preview_desc: string,
     *   preview_price: string,
     *   preview_img: string
     * }
     */
    private static function build_leaf_from_collection(string $slug, array $cfg): array {
        $label = isset($cfg['name']) && is_string($cfg['name']) ? $cfg['name'] : $slug;
        $url   = self::collection_url($slug);
        $count = 0;

        if (function_exists('get_term_by')) {
            $term = get_term_by('slug', $slug, 'product_cat');
            if (is_object($term) && property_exists($term, 'count')) {
                $count = (int) $term->count;
            }
        }

        $glyph_map = [
            'gach-80x80'        => '80x80',
            'gach-60x60'        => '60x60',
            'gach-30x60'        => '30x60',
            'gach-60x120'       => '60x120',
            'gach-15x80-20x100' => '15x90',
        ];

        $swatch_map = [
            'gach-van-da'      => 'ivory',
            'gach-terrazzo'    => 'sand',
            'gach-van-xi-mang' => 'slate',
            'da-nung-ket'      => 'dark',
            'gach-gia-go'      => 'wood',
            'gach-porcelain'   => 'ivory',
        ];

        $price_map = [
            'gach-uu-dai'       => 'Ưu đãi tại kho',
            'da-nung-ket'       => 'Từ 550.000đ/m²',
            'gach-60x120'       => 'Từ 450.000đ/m²',
            'gach-80x80'        => 'Từ 385.000đ/m²',
            'gach-15x80-20x100' => 'Từ 295.000đ/m²',
            'gach-60x60'        => 'Từ 215.000đ/m²',
            'gach-30x60'        => 'Từ 195.000đ/m²',
        ];

        $raw_img = isset($cfg['preview_img']) && is_string($cfg['preview_img']) ? $cfg['preview_img'] : 'room-living-calacatta.svg';

        return [
            'label'         => $label,
            'url'           => $url,
            'slug'          => $slug,
            'count'         => $count,
            'badge'         => $slug === 'gach-uu-dai' ? 'Sale' : '',
            'glyph'         => $glyph_map[$slug] ?? '',
            'swatch'        => $swatch_map[$slug] ?? '',
            'preview_badge' => isset($cfg['preview_badge']) && is_string($cfg['preview_badge']) ? $cfg['preview_badge'] : ' Sẵn kho đồng lô',
            'preview_title' => isset($cfg['h1']) && is_string($cfg['h1']) ? $cfg['h1'] : $label,
            'preview_desc'  => isset($cfg['preview_desc']) && is_string($cfg['preview_desc'])
                ? $cfg['preview_desc']
                : 'Tra cứu mẫu gạch chính hãng kiểm soát đồng lô Shade/Caliber tại kho DRAVIT.',
            'preview_price' => $price_map[$slug] ?? 'Giá niêm yết đ/m²',
            'preview_img'   => self::resolve_preview_image($raw_img),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     * @return array{url: string, preview_badge: string, preview_title: string, preview_desc: string, preview_price: string, preview_img: string}
     */
    private static function first_leaf(array $columns): array {
        foreach ($columns as $col) {
            $sgs = isset($col['subgroups']) && is_array($col['subgroups']) ? $col['subgroups'] : [];
            foreach ($sgs as $sg) {
                $items = isset($sg['items']) && is_array($sg['items']) ? $sg['items'] : [];
                if ($items !== [] && is_array($items[0])) {
                    $first = $items[0];
                    return [
                        'url'           => (string) ($first['url'] ?? self::collection_url('gach')),
                        'preview_badge' => (string) ($first['preview_badge'] ?? 'Hệ thống 28 danh mục'),
                        'preview_title' => (string) ($first['preview_title'] ?? 'Kho Gạch Ốp Lát Chính Hãng DRAVIT'),
                        'preview_desc'  => (string) ($first['preview_desc'] ?? ''),
                        'preview_price' => (string) ($first['preview_price'] ?? 'Từ 385.000đ/m²'),
                        'preview_img'   => (string) ($first['preview_img'] ?? self::resolve_preview_image('room-living-calacatta.svg')),
                    ];
                }
            }
        }

        return [
            'url'           => self::collection_url('gach'),
            'preview_badge' => 'Hệ thống 28 danh mục',
            'preview_title' => 'Kho Gạch Ốp Lát Chính Hãng DRAVIT',
            'preview_desc'  => 'Tra cứu toàn bộ mẫu gạch lát nền, ốp tường và đá nung kết chính hãng.',
            'preview_price' => 'Từ 215.000đ/m²',
            'preview_img'   => self::resolve_preview_image('room-living-calacatta.svg'),
        ];
    }

    public static function resolve_preview_image(string $raw): string {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return DRV_URI . '/assets/images/placeholders/room-living-calacatta.svg';
        }
        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://') || str_starts_with($trimmed, '/')) {
            return $trimmed;
        }
        return DRV_URI . '/assets/images/placeholders/' . ltrim($trimmed, '/');
    }

    public static function collection_url(string $slug): string {
        $clean_slug = trim($slug, '/');
        return self::internal_url('/' . ($clean_slug !== '' ? $clean_slug : 'gach'));
    }

    public static function internal_url(string $path): string {
        $base = function_exists('home_url') ? rtrim((string) home_url(), '/') : 'https://gachdravit.vn';
        if ($path === '' || $path === '/') {
            return $base . '/';
        }
        return MenuSetup::sanitize_menu_url($base . '/' . ltrim($path, '/'), true);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function load_collections_config(): array {
        $file = DRV_DIR . '/config/collections.php';
        if (!is_readable($file)) {
            return [];
        }
        $data = require $file;
        return is_array($data) ? $data : [];
    }
}
