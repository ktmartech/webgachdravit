<?php
declare(strict_types=1);

namespace Dravit\Content;

use Dravit\Core\Config;
use Dravit\Core\ServiceProviderInterface;
use Dravit\Home\HomeData;
use Dravit\Seo\InternalLinks;
use Dravit\Seo\UrlNormalizer;

defined('ABSPATH') || exit;

/**
 * Kiến trúc Taxonomy Silo Cẩm nang, định tuyến phân trang và ViewModels cho `home.php`, `category.php`, `single.php` & `template-policy.php`
 * (docs/20 §1, docs/07 §7–§8).
 */
final class BlogSetup implements ServiceProviderInterface {
    /**
     * 4 chuyên mục Cẩm nang chuẩn (`20 §1.2`) kèm ánh xạ Silo thương mại `IL-05`.
     *
     * @var array<string, array{
     *   name: string,
     *   icon: string,
     *   description: string,
     *   related_collections: array<int, string>,
     *   placeholder: string
     * }>
     */
    public const CATEGORIES = [
        'chon-gach-theo-phong' => [
            'name'                => 'Chọn gạch theo phòng',
            'icon'                => 'house-line',
            'description'         => 'Kinh nghiệm chọn khổ gạch, bề mặt và chỉ số chống trượt R cho phòng khách, nhà tắm, bếp, sân vườn, ban công.',
            'related_collections' => ['gach-phong-khach', 'gach-nha-tam', 'gach-phong-bep', 'gach-san-vuon'],
            'placeholder'         => 'rooms/room-bathroom-terrazzo.jpg',
        ],
        'ky-thuat-op-lat'      => [
            'name'                => 'Kỹ thuật ốp lát',
            'icon'                => 'ruler',
            'description'         => 'Tiêu chuẩn chống trượt R9 - R13, độ hút nước E%, ke cân bằng đường ron 1,5 - 2 mm, keo dán C2 và đối chiếu Shade/Lot.',
            'related_collections' => ['gach-lat-nen', 'gach-op-tuong', 'gach-porcelain', 'da-nung-ket'],
            'placeholder'         => 'detail-grout-line.svg',
        ],
        'phoi-mau-xu-huong'    => [
            'name'                => 'Phối màu & Xu hướng',
            'icon'                => 'palette',
            'description'         => 'Nguyên tắc phối gạch vân đá Marble, Terrazzo, giả gỗ theo phong cách hiện đại, Japandi, Indochine dưới đèn 3000K - 6500K.',
            'related_collections' => ['gach-van-da', 'gach-gia-go', 'gach-terrazzo', 'gach-trang-tri'],
            'placeholder'         => 'rooms/room-living-calacatta.jpg',
        ],
        'dinh-muc-bao-gia'     => [
            'name'                => 'Định mức & Báo giá',
            'icon'                => 'calculator',
            'description'         => 'Công thức quy đổi m² sang số thùng nguyên, tỷ lệ hao hụt 5 - 10% theo kiểu lát và kinh nghiệm dự toán vật tư công trình.',
            'related_collections' => ['gach-80x80', 'gach-60x60', 'gach-60x120', 'gach-30x60'],
            'placeholder'         => 'rooms/room-kitchen-cement.jpg',
        ],
    ];

    /**
     * Danh mục 5 trang Chính sách chuẩn (`07 §8.1`, `setup-step0.php`).
     *
     * @var array<string, array{title: string, icon: string}>
     */
    public const POLICY_PAGES = [
        'chinh-sach-van-chuyen' => ['title' => 'Vận chuyển & Giao nhận', 'icon' => 'truck'],
        'chinh-sach-doi-tra'    => ['title' => 'Đổi trả & Kiểm hàng', 'icon' => 'shield-check'],
        'chinh-sach-thanh-toan' => ['title' => 'Chính sách thanh toán', 'icon' => 'credit-card'],
        'chinh-sach-bao-mat'    => ['title' => 'Bảo mật thông tin', 'icon' => 'lock-simple'],
        'dieu-khoan'            => ['title' => 'Điều khoản sử dụng', 'icon' => 'file-text'],
    ];

    /**
     * Nhóm trang Hướng dẫn & Hợp tác dùng chung `template-policy.php` (menu trái riêng).
     *
     * @var array<string, array{title: string, icon: string}>
     */
    public const GUIDE_PAGES = [
        'huong-dan-mua-hang'   => ['title' => 'Quy trình mua gạch & tính thùng', 'icon' => 'calculator'],
        'hop-tac-kts-nha-thau' => ['title' => 'Hợp tác Kiến trúc sư & Nhà thầu', 'icon' => 'handshake'],
    ];

    public function register(): void {
        add_action('pre_get_posts', [$this, 'configure_blog_queries'], 15);
        add_action('template_redirect', [$this, 'redirect_disabled_blog_archives'], 5);
    }

    public function configure_blog_queries(\WP_Query $query): void {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($query->is_home()) {
            $paged = max(1, (int) $query->get('paged', 1));
            $query->set('post_type', 'post');
            $query->set('post_status', 'publish');
            $query->set('ignore_sticky_posts', true);
            $query->set('posts_per_page', $paged >= 2 ? 12 : 9);
            return;
        }

        if ($query->is_category() || $query->is_tag()) {
            $query->set('post_type', 'post');
            $query->set('post_status', 'publish');
            $query->set('posts_per_page', 12);
        }
    }

    /**
     * Vô hiệu hóa Author Archive & Date Archive theo `20 §1.1` và `06 §2`:
     * - `/author/*` -> 301 về `/gioi-thieu#doi-ngu`
     * - `/2026/*` -> 301 về `/cam-nang`
     */
    public function redirect_disabled_blog_archives(): void {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        if (is_author()) {
            $dest = UrlNormalizer::untrailingslashUrl(home_url('/gioi-thieu')) . '#doi-ngu';
            wp_safe_redirect($dest, 301);
            exit;
        }

        if (is_date()) {
            $dest = UrlNormalizer::untrailingslashUrl(home_url('/cam-nang'));
            wp_safe_redirect($dest, 301);
            exit;
        }
    }

    /**
     * Danh sách mục điều hướng cho thanh Subnav Cẩm nang (`template-parts/blog/subnav.php`).
     *
     * @return array<int, array{slug: string, name: string, url: string, icon: string, count: int, active: bool}>
     */
    public static function getSubnavItems(string $active_slug = ''): array {
        $blog_url = HomeData::siteUrl('/cam-nang');
        $items    = [
            [
                'slug'   => '',
                'name'   => 'Tất cả bài viết',
                'url'    => $blog_url,
                'icon'   => 'squares-four',
                'count'  => 0,
                'active' => ($active_slug === ''),
            ],
        ];

        foreach (self::CATEGORIES as $slug => $meta) {
            $url   = HomeData::siteUrl('/chu-de/' . $slug);
            $count = 0;

            if (function_exists('get_term_by')) {
                $term = get_term_by('slug', $slug, 'category');
                if ($term instanceof \WP_Term) {
                    $link = get_term_link($term);
                    if (is_string($link) && $link !== '') {
                        $url = UrlNormalizer::untrailingslashUrl($link);
                    }
                    $count = (int) $term->count;
                }
            }

            $items[] = [
                'slug'   => $slug,
                'name'   => $meta['name'],
                'url'    => $url,
                'icon'   => $meta['icon'],
                'count'  => $count,
                'active' => ($active_slug === $slug),
            ];
        }

        return $items;
    }

    /**
     * Chuyển `\WP_Post` sang mảng dữ liệu Card bài viết thống nhất toàn site.
     *
     * @return array{
     *   id: int,
     *   title: string,
     *   url: string,
     *   excerpt: string,
     *   cat_name: string,
     *   cat_slug: string,
     *   cat_url: string,
     *   reading_min: int,
     *   reading_label: string,
     *   author_name: string,
     *   date_display: string,
     *   date_iso: string,
     *   img_url: string,
     *   img_alt: string,
     *   silo_pills: array<int, array{label: string, url: string}>
     * }
     */
    public static function mapPostCard(\WP_Post $post): array {
        $post_id  = (int) $post->ID;
        $title    = function_exists('get_the_title') ? get_the_title($post_id) : (string) $post->post_title;
        $raw_link = function_exists('get_permalink') ? (string) get_permalink($post_id) : ('/cam-nang/' . $post->post_name);
        $url      = UrlNormalizer::untrailingslashUrl($raw_link);

        $cat_name    = 'Kỹ thuật ốp lát';
        $cat_slug    = 'ky-thuat-op-lat';
        $cat_url     = HomeData::siteUrl('/chu-de/ky-thuat-op-lat');
        $placeholder = 'rooms/room-living-calacatta.jpg';

        if (function_exists('get_the_category')) {
            $cats = get_the_category($post_id);
            if (is_array($cats) && isset($cats[0]) && $cats[0] instanceof \WP_Term) {
                $cat_name = $cats[0]->name;
                $cat_slug = $cats[0]->slug;
                $c_link   = get_term_link($cats[0]);
                if (is_string($c_link) && $c_link !== '') {
                    $cat_url = UrlNormalizer::untrailingslashUrl($c_link);
                }
                if (isset(self::CATEGORIES[$cat_slug])) {
                    $placeholder = self::CATEGORIES[$cat_slug]['placeholder'];
                }
            }
        }

        $excerpt = trim((string) $post->post_excerpt);
        if ($excerpt === '') {
            $stripped = (string) preg_replace('/\[\/?[a-zA-Z0-9_\-]+(?:\s+[^\]]*)?\]/u', ' ', (string) $post->post_content);
            $plain    = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($stripped) : strip_tags($stripped);
            $excerpt  = function_exists('wp_trim_words') ? wp_trim_words($plain, 28, '...') : mb_substr(trim($plain), 0, 160, 'UTF-8');
        }

        $thumb_id = function_exists('get_post_thumbnail_id') ? (int) get_post_thumbnail_id($post_id) : 0;
        $img_url  = HomeData::attachmentUrl($thumb_id, 'medium_large', $placeholder);
        $img_alt  = '';
        if ($thumb_id > 0 && function_exists('get_post_meta')) {
            $img_alt = trim((string) get_post_meta($thumb_id, '_wp_attachment_image_alt', true));
        }
        if ($img_alt === '') {
            $img_alt = $title;
        }

        $minutes = ReadingTime::forPost($post);
        $author  = AuthorProfile::resolvePostAuthor($post);

        $rel_slugs   = PostMeta::getRelatedCollectionSlugs($post_id);
        $collections = Config::get('collections');
        if ($rel_slugs === [] && isset(self::CATEGORIES[$cat_slug])) {
            $rel_slugs = array_slice(self::CATEGORIES[$cat_slug]['related_collections'], 0, 2);
        }

        $silo_pills = [];
        foreach (array_slice($rel_slugs, 0, 2) as $s_slug) {
            if (!isset($collections[$s_slug])) {
                continue;
            }
            $silo_pills[] = [
                'label' => (string) ($collections[$s_slug]['name'] ?? $s_slug),
                'url'   => HomeData::siteUrl('/' . $s_slug),
            ];
        }

        return [
            'id'            => $post_id,
            'title'         => $title,
            'url'           => $url,
            'excerpt'       => $excerpt,
            'cat_name'      => $cat_name,
            'cat_slug'      => $cat_slug,
            'cat_url'       => $cat_url,
            'reading_min'   => $minutes,
            'reading_label' => ReadingTime::formatLabel($minutes),
            'author_name'   => $author['name'],
            'date_display'  => function_exists('get_the_modified_date') ? (string) get_the_modified_date('d/m/Y', $post_id) : '',
            'date_iso'      => function_exists('get_the_modified_date') ? (string) get_the_modified_date('Y-m-d', $post_id) : '',
            'img_url'       => $img_url,
            'img_alt'       => $img_alt,
            'silo_pills'    => $silo_pills,
        ];
    }

    /**
     * Dựng toàn bộ ViewModel cho Trang chủ Cẩm nang `home.php` (`/cam-nang` trang 1, docs/20 §1.3.B).
     *
     * @return array{
     *   pillar: array<string, mixed>|null,
     *   recent: array<int, array<string, mixed>>,
     *   popular: array<int, array<string, mixed>>,
     *   clusters: array<int, array{slug: string, name: string, url: string, count: int, posts: array<int, array<string, mixed>>}>,
     *   r_rows: array<int, array{code: string, angle: string, surface: string, area: string, collection_label: string, collection_url: string}>
     * }
     */
    public static function getArchiveViewModel(): array {
        if (!function_exists('get_posts')) {
            return [
                'pillar'   => null,
                'recent'   => [],
                'popular'  => [],
                'clusters' => [],
                'r_rows'   => self::getAntiSlipReferenceRows(),
            ];
        }

        // 1. Bài Pillar trọng tâm (5 cột trái Bento): ưu tiên `drv_is_pillar = '1'`, fallback bài mới nhất.
        $pillar_posts = get_posts([
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'posts_per_page'      => 1,
            'meta_key'            => 'drv_is_pillar',
            'meta_value'          => '1',
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
        ]);

        if (!is_array($pillar_posts) || $pillar_posts === []) {
            $pillar_posts = get_posts([
                'post_type'           => 'post',
                'post_status'         => 'publish',
                'posts_per_page'      => 1,
                'ignore_sticky_posts' => true,
                'no_found_rows'       => true,
            ]);
        }

        $pillar_post = (is_array($pillar_posts) && isset($pillar_posts[0]) && $pillar_posts[0] instanceof \WP_Post && $pillar_posts[0]->post_name !== 'hello-world')
            ? $pillar_posts[0]
            : null;
        $pillar_id   = $pillar_post !== null ? (int) $pillar_post->ID : 0;
        $pillar_card = $pillar_post !== null ? self::mapPostCard($pillar_post) : null;

        // 2. 3 Bài mới xuất bản (4 cột giữa Bento).
        $recent_raw = get_posts([
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'posts_per_page'      => 4,
            'post__not_in'        => $pillar_id > 0 ? [$pillar_id] : [],
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
        ]);

        $recent = [];
        if (is_array($recent_raw)) {
            foreach ($recent_raw as $rp) {
                if ($rp instanceof \WP_Post && $rp->post_name !== 'hello-world') {
                    $recent[] = self::mapPostCard($rp);
                }
                if (count($recent) >= 3) {
                    break;
                }
            }
        }

        // 3. 5 Bài tra cứu nhiều nhất (3 cột phải Bento — Anton 01..05).
        $popular_ids = drv_setting('blog.popular_post_ids', []);
        $popular_ids = is_array($popular_ids)
            ? array_values(array_filter(array_map('intval', $popular_ids), static fn (int $v): bool => $v > 0))
            : [];

        $popular_posts = [];
        if ($popular_ids !== []) {
            $queried_pop = get_posts([
                'post_type'           => 'post',
                'post_status'         => 'publish',
                'post__in'            => array_slice($popular_ids, 0, 5),
                'orderby'             => 'post__in',
                'posts_per_page'      => 5,
                'ignore_sticky_posts' => true,
                'no_found_rows'       => true,
            ]);
            if (is_array($queried_pop)) {
                $popular_posts = $queried_pop;
            }
        }

        if (count($popular_posts) < 5) {
            $exclude_pop = array_map(static fn (mixed $p): int => $p instanceof \WP_Post ? $p->ID : 0, $popular_posts);
            $fallback_pop = get_posts([
                'post_type'           => 'post',
                'post_status'         => 'publish',
                'posts_per_page'      => 6,
                'post__not_in'        => array_values(array_filter($exclude_pop)),
                'category_name'       => 'ky-thuat-op-lat,dinh-muc-bao-gia',
                'ignore_sticky_posts' => true,
                'no_found_rows'       => true,
            ]);
            if (!is_array($fallback_pop) || $fallback_pop === []) {
                $fallback_pop = get_posts([
                    'post_type'           => 'post',
                    'post_status'         => 'publish',
                    'posts_per_page'      => 6,
                    'post__not_in'        => array_values(array_filter($exclude_pop)),
                    'ignore_sticky_posts' => true,
                    'no_found_rows'       => true,
                ]);
            }
            if (is_array($fallback_pop)) {
                foreach ($fallback_pop as $fp) {
                    if ($fp instanceof \WP_Post && $fp->post_name !== 'hello-world') {
                        $popular_posts[] = $fp;
                    }
                    if (count($popular_posts) >= 5) {
                        break;
                    }
                }
            }
        }

        $popular = [];
        foreach (array_slice($popular_posts, 0, 5) as $idx => $pp) {
            if (!$pp instanceof \WP_Post) {
                continue;
            }
            $mapped        = self::mapPostCard($pp);
            $mapped['num'] = sprintf('%02d', $idx + 1);
            $popular[]     = $mapped;
        }

        // 4. 4 Cụm chủ đề chuyên sâu (Topic Clusters).
        $clusters = [];
        foreach (self::CATEGORIES as $slug => $meta) {
            $term  = function_exists('get_term_by') ? get_term_by('slug', $slug, 'category') : null;
            $c_url = HomeData::siteUrl('/chu-de/' . $slug);
            $count = 0;

            if ($term instanceof \WP_Term) {
                $t_link = get_term_link($term);
                if (is_string($t_link) && $t_link !== '') {
                    $c_url = UrlNormalizer::untrailingslashUrl($t_link);
                }
                $count = (int) $term->count;
            }

            $cluster_posts = [];
            if ($term instanceof \WP_Term && $count > 0) {
                $c_raw = get_posts([
                    'post_type'           => 'post',
                    'post_status'         => 'publish',
                    'cat'                 => (int) $term->term_id,
                    'posts_per_page'      => 3,
                    'ignore_sticky_posts' => true,
                    'no_found_rows'       => true,
                ]);
                if (is_array($c_raw)) {
                    foreach ($c_raw as $cp) {
                        if ($cp instanceof \WP_Post && $cp->post_name !== 'hello-world') {
                            $cluster_posts[] = self::mapPostCard($cp);
                        }
                    }
                }
            }

            if ($cluster_posts !== []) {
                $clusters[] = [
                    'slug'  => $slug,
                    'name'  => $meta['name'],
                    'url'   => $c_url,
                    'count' => max(count($cluster_posts), $count),
                    'posts' => $cluster_posts,
                ];
            }
        }

        return [
            'pillar'   => $pillar_card,
            'recent'   => $recent,
            'popular'  => $popular,
            'clusters' => $clusters,
            'r_rows'   => self::getAntiSlipReferenceRows(),
        ];
    }

    /**
     * Bảng tra cứu nhanh chỉ số Chống trượt R9 - R13 theo không gian (`20 §1.3.B.4`, nối thẳng URL tĩnh Collection).
     *
     * @return array<int, array{code: string, angle: string, surface: string, area: string, collection_label: string, collection_url: string}>
     */
    public static function getAntiSlipReferenceRows(): array {
        return [
            [
                'code'             => 'R9',
                'angle'            => '6° - 10°',
                'surface'          => 'Bóng kiếng / Mờ mịn',
                'area'             => 'Phòng khách, phòng ngủ, sảnh khô ráo',
                'collection_label' => 'Gạch phòng khách',
                'collection_url'   => HomeData::siteUrl('/gach-phong-khach'),
            ],
            [
                'code'             => 'R10',
                'angle'            => '10° - 19°',
                'surface'          => 'Mờ Matt mịn (DCOF ≥ 0,42)',
                'area'             => 'Nhà tắm gia đình, phòng bếp, hành lang',
                'collection_label' => 'Gạch nhà tắm',
                'collection_url'   => HomeData::siteUrl('/gach-nha-tam'),
            ],
            [
                'code'             => 'R11',
                'angle'            => '19° - 27°',
                'surface'          => 'Nhám định hình (Structured)',
                'area'             => 'Sân vườn, ban công, hiên ngoài trời đọng mưa',
                'collection_label' => 'Gạch sân vườn',
                'collection_url'   => HomeData::siteUrl('/gach-san-vuon'),
            ],
            [
                'code'             => 'R12',
                'angle'            => '27° - 35°',
                'surface'          => 'Nhám sần hạt sâu',
                'area'             => 'Lối dốc gara ô tô, bậc tam cấp ngoài trời',
                'collection_label' => 'Gạch ngoài trời',
                'collection_url'   => HomeData::siteUrl('/gach-ngoai-troi'),
            ],
            [
                'code'             => 'R13',
                'angle'            => '> 35°',
                'surface'          => 'Gân nổi / Mosaic thành bể',
                'area'             => 'Bậc hồ bơi, sàn dốc ướt thường xuyên',
                'collection_label' => 'Gạch hồ bơi',
                'collection_url'   => HomeData::siteUrl('/gach-ho-boi'),
            ],
        ];
    }

    /**
     * Lấy 4 thẻ Smart Collections liên quan cho chân trang `category.php` hoặc `single.php` (`IL-05`).
     *
     * @param array<int, string> $explicit_slugs
     * @return array<int, array<string, mixed>>
     */
    public static function getRelatedSiloTiles(string $category_slug = '', array $explicit_slugs = []): array {
        $slugs = $explicit_slugs;
        if ($slugs === []) {
            $slugs = self::CATEGORIES[$category_slug]['related_collections']
                ?? ['gach-lat-nen', 'gach-phong-khach', 'gach-nha-tam', 'gach-80x80'];
        }

        return InternalLinks::getSiloTiles('', $slugs, 4);
    }

    /**
     * Lấy tối đa `$limit` bài viết liên quan cho cuối bài `single.php`.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getRelatedArticles(int $post_id, int $limit = 3): array {
        if ($post_id <= 0 || !function_exists('get_posts')) {
            return [];
        }

        $safe_limit = max(1, min(6, $limit));
        $manual_ids = PostMeta::getRelatedPostIds($post_id);
        $posts      = [];

        if ($manual_ids !== []) {
            $queried = get_posts([
                'post_type'           => 'post',
                'post_status'         => 'publish',
                'post__in'            => array_slice($manual_ids, 0, $safe_limit),
                'orderby'             => 'post__in',
                'posts_per_page'      => $safe_limit,
                'ignore_sticky_posts' => true,
                'no_found_rows'       => true,
            ]);
            if (is_array($queried)) {
                $posts = $queried;
            }
        }

        if (count($posts) < $safe_limit) {
            $exclude = array_merge([$post_id], array_map(static fn (mixed $p): int => $p instanceof \WP_Post ? $p->ID : 0, $posts));
            $cat_ids = [];
            if (function_exists('wp_get_post_categories')) {
                $raw_cats = wp_get_post_categories($post_id);
                if (is_array($raw_cats)) {
                    $cat_ids = array_map('intval', $raw_cats);
                }
            }

            $args = [
                'post_type'           => 'post',
                'post_status'         => 'publish',
                'posts_per_page'      => $safe_limit - count($posts),
                'post__not_in'        => array_values(array_unique(array_filter($exclude))),
                'ignore_sticky_posts' => true,
                'no_found_rows'       => true,
            ];
            if ($cat_ids !== []) {
                $args['category__in'] = $cat_ids;
            }

            $more = get_posts($args);
            if ((!is_array($more) || $more === []) && isset($args['category__in'])) {
                unset($args['category__in']);
                $more = get_posts($args);
            }
            if (is_array($more)) {
                $posts = array_merge($posts, $more);
            }
        }

        $cards = [];
        foreach ($posts as $item) {
            if ($item instanceof \WP_Post && $item->post_name !== 'hello-world') {
                $cards[] = self::mapPostCard($item);
            }
            if (count($cards) >= $safe_limit) {
                break;
            }
        }

        return $cards;
    }

    /**
     * Danh sách menu điều hướng cột trái cho 5 trang Chính sách (`template-policy.php`, `07 §8.1`).
     *
     * @return array<int, array{slug: string, title: string, url: string, icon: string, active: bool}>
     */
    public static function getPolicyNavItems(int $current_page_id = 0, string $current_slug = ''): array {
        $items = [];

        $group = isset(self::GUIDE_PAGES[$current_slug]) ? self::GUIDE_PAGES : self::POLICY_PAGES;

        foreach ($group as $slug => $meta) {
            $url    = HomeData::siteUrl('/' . $slug);
            $title  = $meta['title'];
            $active = ($current_slug === $slug);

            if (function_exists('get_page_by_path')) {
                $page = get_page_by_path($slug, OBJECT, 'page');
                if ($page instanceof \WP_Post && $page->post_status === 'publish') {
                    $p_link = get_permalink($page->ID);
                    if (is_string($p_link) && $p_link !== '') {
                        $url = UrlNormalizer::untrailingslashUrl($p_link);
                    }
                    if ($current_page_id > 0 && (int) $page->ID === $current_page_id) {
                        $active = true;
                    }
                }
            }

            $items[] = [
                'slug'   => $slug,
                'title'  => $title,
                'url'    => $url,
                'icon'   => $meta['icon'],
                'active' => $active,
            ];
        }

        return $items;
    }
}
