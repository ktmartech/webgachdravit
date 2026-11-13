<?php
declare(strict_types=1);

namespace Dravit\Seo;

use Dravit\Core\Config;

defined('ABSPATH') || exit;

/**
 * Bộ giải liên kết nội bộ Silo chuẩn SEO `IL-01..IL-06` (docs/06 §4, ADR D-29):
 * Cam kết 100% URL tĩnh không dấu `/` cuối, 0 link chứa `?param`.
 */
final class InternalLinks {
    /** @var array<int, string> */
    private const STONE_VEIN_SLUGS = [
        'van-da-marble',
        'van-da-tu-nhien',
        'van-da-travertine',
        'van-da-onyx',
        'van-da-hoa-cuong',
        'van-da-bazan',
    ];

    /**
     * Giải URL trang tĩnh chuẩn cho một giá trị thuộc tính trên bảng thông số PDP (`IL-03`).
     * Nếu thuộc tính không có trang tĩnh riêng -> trả `null` để render text thuần (tuyệt đối không gắn `?param`).
     *
     * @param array<string, mixed>|null $facets_config
     */
    public static function resolveTermUrl(string $taxonomy, string $slug, ?array $facets_config = null): ?string {
        $clean_slug = strtolower(trim($slug));
        if ($clean_slug === '') {
            return null;
        }

        if ($taxonomy === 'product_cat') {
            return self::siteUrl('/' . $clean_slug);
        }

        if ($taxonomy === 'pa_bo-suu-tap') {
            return self::siteUrl('/bo-suu-tap/' . $clean_slug);
        }

        if ($taxonomy === 'pa_xuat-xu') {
            return self::siteUrl('/xuat-xu/' . $clean_slug);
        }

        if ($taxonomy === 'pa_van-gach' && in_array($clean_slug, self::STONE_VEIN_SLUGS, true)) {
            return self::siteUrl('/gach-van-da');
        }

        if ($taxonomy === 'pa_chat-lieu' && in_array($clean_slug, ['porcelain', 'granite', 'xuong-trang', 'dong-chat'], true)) {
            return self::siteUrl('/gach-porcelain');
        }

        $attr_key   = str_starts_with($taxonomy, 'pa_') ? substr($taxonomy, 3) : $taxonomy;
        $facets_cfg = $facets_config ?? Config::get('facets');
        $static_map = $facets_cfg['attributes'][$attr_key]['static_map'] ?? null;

        if (is_array($static_map) && isset($static_map[$clean_slug])) {
            return self::siteUrl('/' . ltrim((string) $static_map[$clean_slug], '/'));
        }

        return null;
    }

    /**
     * Lấy danh sách các thẻ Silo liên quan (`IL-01` / `IL-02`) từ cấu hình `config/collections.php`.
     *
     * @param array<int, string> $explicit_slugs
     * @return array<int, array{slug: string, name: string, url: string, count: int, thumb_id: int, thumb_url: string}>
     */
    public static function getSiloTiles(
        string $current_slug = '',
        array $explicit_slugs = [],
        int $limit = 6
    ): array {
        $collections = Config::get('collections');
        $target_list = [];

        if ($explicit_slugs !== []) {
            $target_list = $explicit_slugs;
        } elseif ($current_slug !== '' && isset($collections[$current_slug]['related']) && is_array($collections[$current_slug]['related'])) {
            $target_list = $collections[$current_slug]['related'];
        } else {
            $target_list = ['gach-lat-nen', 'gach-op-tuong', 'gach-phong-khach', 'gach-nha-tam', 'gach-80x80', 'da-nung-ket'];
        }

        $items = [];
        foreach ($target_list as $raw_slug) {
            $slug = strtolower(trim((string) $raw_slug));
            if ($slug === '' || $slug === $current_slug || !isset($collections[$slug])) {
                continue;
            }

            $cfg       = $collections[$slug];
            $count     = 0;
            $thumb_id  = 0;
            $thumb_url = '';

            if (function_exists('get_term_by') && function_exists('taxonomy_exists') && taxonomy_exists('product_cat')) {
                $term = get_term_by('slug', $slug, 'product_cat');
                if ($term instanceof \WP_Term) {
                    $count    = (int) $term->count;
                    $thumb_id = function_exists('get_term_meta')
                        ? (int) get_term_meta($term->term_id, 'thumbnail_id', true)
                        : 0;
                }
            }

            $preview_img = (string) ($cfg['preview_img'] ?? '');
            if ($thumb_id <= 0 && $preview_img !== '') {
                $thumb_url = DRV_URI . '/assets/images/placeholders/' . $preview_img;
            }

            $items[] = [
                'slug'      => $slug,
                'name'      => (string) ($cfg['name'] ?? $slug),
                'url'       => self::siteUrl('/' . $slug),
                'count'     => $count,
                'thumb_id'  => $thumb_id,
                'thumb_url' => $thumb_url,
            ];

            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    private static function siteUrl(string $path): string {
        if (function_exists('home_url')) {
            return UrlNormalizer::untrailingslashUrl(home_url($path));
        }

        return 'https://gachdravit.vn' . rtrim($path, '/');
    }
}

