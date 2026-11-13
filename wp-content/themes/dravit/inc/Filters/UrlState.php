<?php
declare(strict_types=1);

namespace Dravit\Filters;

use Dravit\Core\Config;
use Dravit\Seo\Canonical;
use Dravit\Seo\MetaTags;
use Dravit\Seo\RobotsMatrix;
use Dravit\Seo\UrlNormalizer;

defined('ABSPATH') || exit;

/**
 * Trạng thái bất biến (Immutable Value Object) của ngữ pháp URL bộ lọc danh mục
 * (ADR D-21, docs/05 §3, docs/15 §2.1, Test R-3..R-8, M-1..M-8, F-14).
 */
final class UrlState {
    private string $base_taxonomy;
    private string $base_slug;
    private string $base_url;

    /** @var array<string, array<int, string>> */
    private array $facets;

    private string $sort;
    private int $page;
    private int $per_page;

    /** @var array<int, string> */
    private array $hidden_facets;

    /** @var array<string, array<int, string>> */
    private array $hidden_terms;

    /** @var array<string, mixed> */
    private array $facets_config;

    /**
     * @param array<string, array<int, string>> $facets
     * @param array<int, string>                $hidden_facets
     * @param array<string, array<int, string>> $hidden_terms
     * @param array<string, mixed>              $facets_config
     */
    private function __construct(
        string $base_taxonomy,
        string $base_slug,
        string $base_url,
        array $facets,
        string $sort,
        int $page,
        int $per_page,
        array $hidden_facets,
        array $hidden_terms,
        array $facets_config
    ) {
        $this->base_taxonomy = $base_taxonomy;
        $this->base_slug     = $base_slug;
        $this->base_url      = $base_url;
        $this->facets        = $facets;
        $this->sort          = $sort;
        $this->page          = $page;
        $this->per_page      = $per_page;
        $this->hidden_facets = $hidden_facets;
        $this->hidden_terms  = $hidden_terms;
        $this->facets_config = $facets_config;
    }

    /**
     * Khởi tạo `UrlState` từ tham số thô (dùng chung cho SSR, REST `/dravit/v1/catalog` & PHPUnit).
     *
     * @param array<string, mixed>      $raw_params
     * @param array<string, mixed>|null $facets_config
     * @param array<string, mixed>|null $collections_config
     */
    public static function fromArray(
        string $base,
        array $raw_params = [],
        int $page = 1,
        ?int $per_page = null,
        ?string $origin = null,
        ?array $facets_config = null,
        ?array $collections_config = null
    ): self {
        $f_cfg = $facets_config ?? Config::get('facets');
        $c_cfg = $collections_config ?? Config::get('collections');

        [$base_tax, $base_slug] = self::parseBaseIdentifier($base, $c_cfg);
        $base_url               = self::resolveBaseUrl($base_tax, $base_slug, $origin);
        $hidden_ctx             = FacetRegistry::resolveHiddenContext($base_tax, $base_slug, $c_cfg);
        $facet_defs             = FacetRegistry::getFacets($f_cfg);
        $sort_options           = FacetRegistry::getSortOptions($f_cfg);

        $default_per_page = (int) drv_setting('catalog.per_page', 24);
        if ($default_per_page < 1) {
            $default_per_page = 24;
        }
        $resolved_per_page = $per_page !== null ? max(1, min(48, $per_page)) : max(1, min(48, $default_per_page));
        $resolved_page     = max(1, $page);

        if (isset($raw_params['page']) && $page <= 1) {
            $resolved_page = max(1, (int) $raw_params['page']);
        }
        if (isset($raw_params['per_page']) && $per_page === null) {
            $resolved_per_page = max(1, min(48, (int) $raw_params['per_page']));
        }

        $resolved_sort = 'moi-ve';
        if (isset($raw_params['sort']) && is_scalar($raw_params['sort'])) {
            $candidate_sort = strtolower(trim((string) $raw_params['sort']));
            if ($candidate_sort !== '' && isset($sort_options[$candidate_sort])) {
                $resolved_sort = $candidate_sort;
            }
        }

        $clean_facets = [];
        foreach ($facet_defs as $param => $def) {
            if (in_array($param, $hidden_ctx['hidden_facets'], true)) {
                continue;
            }
            if (!array_key_exists($param, $raw_params)) {
                continue;
            }

            $raw_val = $raw_params[$param];
            if ($raw_val === null || $raw_val === '' || $raw_val === []) {
                continue;
            }

            $raw_str = is_array($raw_val)
                ? implode(',', array_map('strval', $raw_val))
                : (string) $raw_val;

            $known_terms = FacetRegistry::getTermsForFacet($param, $f_cfg);
            $locked_vals = $hidden_ctx['hidden_terms'][$param] ?? [];
            $valid_map   = [];

            foreach (explode(',', $raw_str) as $piece) {
                $slug = strtolower(trim($piece));
                if ($slug === '') {
                    continue;
                }

                if ($param === 'showroom') {
                    if (in_array($slug, ['1', 'true', 'showroom'], true)) {
                        $valid_map['1'] = true;
                    }
                    continue;
                }

                if (in_array($slug, $locked_vals, true)) {
                    continue;
                }

                if (isset($known_terms[$slug])) {
                    $valid_map[$slug] = true;
                }
            }

            if ($valid_map === []) {
                continue;
            }

            $slugs = array_map('strval', array_keys($valid_map));
            sort($slugs, SORT_STRING);
            $clean_facets[$param] = $slugs;
        }

        return new self(
            $base_tax,
            $base_slug,
            $base_url,
            $clean_facets,
            $resolved_sort,
            $resolved_page,
            $resolved_per_page,
            $hidden_ctx['hidden_facets'],
            $hidden_ctx['hidden_terms'],
            $f_cfg
        );
    }

    /**
     * Khởi tạo `UrlState` trực tiếp từ ngữ cảnh WordPress Main Query hiện tại (SSR).
     */
    public static function fromCurrentRequest(): self {
        $base = 'product_cat:gach';
        if (function_exists('is_tax') && is_tax(['pa_bo-suu-tap', 'pa_xuat-xu'])) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $base = $term->taxonomy . ':' . $term->slug;
            }
        } elseif (function_exists('is_product_category') && is_product_category()) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $base = 'product_cat:' . $term->slug;
            }
        }

        $paged = 1;
        if (function_exists('get_query_var')) {
            $paged = max(1, (int) get_query_var('paged', 1), (int) get_query_var('page', 1));
        }

        $raw_uri      = isset($_SERVER['REQUEST_URI'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_URI']))
            : '';
        $qs           = (string) wp_parse_url($raw_uri, PHP_URL_QUERY);
        $query_params = [];
        if ($qs !== '') {
            parse_str($qs, $query_params);
        }

        return self::fromArray($base, $query_params, $paged);
    }

    /**
     * Khởi tạo `UrlState` từ đối tượng `\WP_REST_Request` của `/dravit/v1/catalog`.
     */
    public static function fromRestRequest(\WP_REST_Request $request): self {
        $params   = $request->get_params();
        $base     = isset($params['base']) && is_string($params['base']) && trim($params['base']) !== ''
            ? trim($params['base'])
            : 'product_cat:gach';
        $page     = isset($params['page']) ? max(1, (int) $params['page']) : 1;
        $per_page = isset($params['per_page']) ? max(1, min(48, (int) $params['per_page'])) : null;

        return self::fromArray($base, is_array($params) ? $params : [], $page, $per_page);
    }

    public function base(): string {
        return $this->base_taxonomy . ':' . $this->base_slug;
    }

    public function baseTaxonomy(): string {
        return $this->base_taxonomy;
    }

    public function baseSlug(): string {
        return $this->base_slug;
    }

    public function baseUrl(): string {
        return $this->base_url;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function facets(): array {
        return $this->facets;
    }

    public function sort(): string {
        return $this->sort;
    }

    public function page(): int {
        return $this->page;
    }

    public function perPage(): int {
        return $this->per_page;
    }

    /**
     * @return array<int, string>
     */
    public function hiddenFacets(): array {
        return $this->hidden_facets;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function hiddenTerms(): array {
        return $this->hidden_terms;
    }

    public function hasActiveFilters(): bool {
        return $this->facets !== [];
    }

    public function hasAnyActiveParam(): bool {
        return $this->facets !== [] || $this->sort !== 'moi-ve';
    }

    /**
     * Tổng số giá trị lọc đang bật (phục vụ badge đếm trên nút Bộ lọc mobile).
     */
    public function activeCount(): int {
        $total = 0;
        foreach ($this->facets as $values) {
            $total += count($values);
        }
        return $total;
    }

    /**
     * URL chuẩn hóa hoàn chỉnh `{base_url}[/page/{n}][?{param}={v1},{v2}&...]` không dấu `/` cuối (`D-21`).
     */
    public function url(?int $override_page = null): string {
        $target_page = $override_page !== null ? max(1, $override_page) : $this->page;
        $path        = $this->base_url . ($target_page >= 2 ? '/page/' . $target_page : '');
        $qs          = $this->queryString();

        return $qs !== '' ? $path . '?' . $qs : $path;
    }

    /**
     * Chuỗi query chuẩn hóa đúng thứ tự `order` và `a–z` (`sort=moi-ve` mặc định luôn được lược bỏ).
     */
    public function queryString(): string {
        return self::buildQueryStringFrom($this->facets, $this->sort);
    }

    /**
     * Khi ở `/gach` (trang 1, không sort) chọn đúng 1 giá trị có trang Collection/Attribute tĩnh -> trả URL đích để redirect (`R-3`).
     */
    public function redirectTo(): ?string {
        if (
            $this->base_taxonomy !== 'product_cat'
            || $this->base_slug !== 'gach'
            || $this->page !== 1
            || $this->sort !== 'moi-ve'
            || count($this->facets) !== 1
        ) {
            return null;
        }

        $only_param = (string) array_key_first($this->facets);
        $only_vals  = $this->facets[$only_param];
        if (count($only_vals) !== 1) {
            return null;
        }

        $static_path = FacetRegistry::resolveSingleStaticPath($only_param, $only_vals[0], $this->facets_config);
        if ($static_path === null) {
            return null;
        }

        return self::replaceUrlPath($this->base_url, $static_path);
    }

    /**
     * Trả về URL `<link rel="canonical">` đồng bộ 100% với `Dravit\Seo\Canonical`.
     */
    public function canonical(): string {
        return Canonical::resolveCanonical(
            $this->base_url,
            $this->page,
            $this->seoQueryParams(),
            $this->facets_config
        );
    }

    /**
     * Trả về chuỗi chỉ thị `<meta name="robots">` đồng bộ 100% với `Dravit\Seo\RobotsMatrix`.
     */
    public function robots(?int $post_count = null, ?int $max_pages = null): string {
        $view = ($this->base_taxonomy === 'product_cat') ? 'collection' : 'attribute_public';

        $pair = RobotsMatrix::resolveRobots([
            'view'         => $view,
            'post_count'   => $post_count,
            'paged'        => $this->page,
            'max_pages'    => $max_pages,
            'query_params' => $this->seoQueryParams(),
        ], $this->facets_config);

        return $pair['index'] . ', ' . $pair['follow'];
    }

    /**
     * Sinh thẻ `<title>` ngữ cảnh gọn sạch không dùng `—` (`<= 60` ký tự, docs/06 §3, docs/15 §2.1).
     */
    public function title(string $archive_name): string {
        $brand      = (string) drv_setting('company.brand_name', 'DRAVIT');
        $clean_name = trim($archive_name) !== '' ? trim($archive_name) : 'Gạch Ốp Lát';

        if (!$this->hasActiveFilters()) {
            if ($this->base_taxonomy === 'product_cat' && $this->base_slug === 'gach') {
                $base_title = sprintf('Kho Gạch Ốp Lát Chính Hãng | %s', $brand);
            } else {
                $base_title = sprintf('%s Đẹp, Báo Giá m² | %s', $clean_name, $brand);
            }
            if ($this->page >= 2) {
                $base_title = sprintf('%s - Trang %d | %s', $clean_name, $this->page, $brand);
            }
            return MetaTags::sanitizeTitle($base_title);
        }

        $chips  = $this->activeChips();
        $labels = [];
        foreach (array_slice($chips, 0, 2) as $chip) {
            $labels[] = $chip['label'];
        }
        $suffix_filter = implode(', ', $labels);

        return MetaTags::sanitizeTitle(sprintf('%s %s | %s', $clean_name, $suffix_filter, $brand));
    }

    /**
     * Xây dựng danh sách chip đang lọc kèm URL gỡ từng chip (reset về trang 1).
     *
     * @return array<int, array{param: string, group_label: string, value: string, label: string, remove_url: string}>
     */
    public function activeChips(): array {
        if ($this->facets === []) {
            return [];
        }

        $defs  = FacetRegistry::getFacets($this->facets_config);
        $chips = [];

        foreach ($this->facets as $param => $values) {
            if (!isset($defs[$param])) {
                continue;
            }
            $group_label = $defs[$param]['label'];
            $terms_map   = FacetRegistry::getTermsForFacet($param, $this->facets_config);

            foreach ($values as $val) {
                $lookup_key = ($param === 'showroom') ? 'showroom' : $val;
                $term_info  = $terms_map[$lookup_key] ?? null;
                $item_label = $term_info !== null
                    ? ($term_info['short_label'] !== '' ? $term_info['short_label'] : $term_info['name'])
                    : $val;

                $next_facets = $this->facets;
                $remaining   = array_values(array_filter(
                    $next_facets[$param],
                    static fn (string $v): bool => $v !== $val
                ));
                if ($remaining === []) {
                    unset($next_facets[$param]);
                } else {
                    $next_facets[$param] = $remaining;
                }

                $remove_qs  = self::buildQueryStringFrom($next_facets, $this->sort);
                $remove_url = $remove_qs !== '' ? $this->base_url . '?' . $remove_qs : $this->base_url;

                $chips[] = [
                    'param'       => $param,
                    'group_label' => $group_label,
                    'value'       => $val,
                    'label'       => $item_label,
                    'remove_url'  => $remove_url,
                ];
            }
        }

        return $chips;
    }

    /**
     * Xây dựng URL trang kế tiếp phục vụ `<a rel="next" data-drv-load-more>` và No-JS fallback.
     */
    public function buildNextPageUrl(int $total_pages): ?string {
        if ($this->page >= $total_pages || $total_pages <= 1) {
            return null;
        }

        return $this->url($this->page + 1);
    }

    /**
     * Chữ ký ổn định phục vụ khóa cache `FacetCounter` (không phụ thuộc `page`, `per_page` hay `sort`).
     */
    public function cacheSignature(): string {
        $parts = [$this->base()];
        foreach ($this->facets as $param => $vals) {
            $parts[] = $param . '=' . implode(',', $vals);
        }
        return implode('|', $parts);
    }

    /**
     * Tóm tắt ngắn các tiêu chí đang lọc để điền sẵn vào Modal Báo Giá khi kho 0 kết quả.
     */
    public function buildCriteriaSummary(string $archive_name): string {
        $chips = $this->activeChips();
        if ($chips === []) {
            return $archive_name;
        }

        $segments = [];
        foreach ($chips as $chip) {
            $segments[] = $chip['group_label'] . ': ' . $chip['label'];
        }

        return trim($archive_name . ' (' . implode('; ', $segments) . ')');
    }

    /**
     * @return array<string, string>
     */
    private function seoQueryParams(): array {
        $params = [];
        foreach ($this->facets as $param => $vals) {
            $params[$param] = implode(',', $vals);
        }
        if ($this->sort !== 'moi-ve') {
            $params['sort'] = $this->sort;
        }
        return $params;
    }

    /**
     * @param array<string, array<int, string>> $facets
     */
    private static function buildQueryStringFrom(array $facets, string $sort): string {
        $pairs = [];
        foreach ($facets as $param => $vals) {
            if ($vals === []) {
                continue;
            }
            $encoded_vals = array_map('rawurlencode', $vals);
            $pairs[]      = rawurlencode($param) . '=' . implode(',', $encoded_vals);
        }
        if ($sort !== '' && $sort !== 'moi-ve') {
            $pairs[] = 'sort=' . rawurlencode($sort);
        }
        return implode('&', $pairs);
    }

    /**
     * @param array<string, mixed> $collections_config
     * @return array{0: string, 1: string}
     */
    private static function parseBaseIdentifier(string $raw_base, array $collections_config): array {
        $clean = strtolower(trim($raw_base));
        if ($clean === '') {
            return ['product_cat', 'gach'];
        }

        if (str_contains($clean, ':')) {
            [$tax, $slug] = explode(':', $clean, 2);
            $tax          = (string) preg_replace('/[^a-z0-9_\-]/', '', $tax);
            $slug         = (string) preg_replace('/[^a-z0-9_\-]/', '', $slug);
            if (in_array($tax, ['product_cat', 'pa_bo-suu-tap', 'pa_xuat-xu'], true) && $slug !== '') {
                return [$tax, $slug];
            }
            return ['product_cat', 'gach'];
        }

        $slug = (string) preg_replace('/[^a-z0-9_\-]/', '', ltrim($clean, '/'));
        if ($slug !== '' && isset($collections_config[$slug])) {
            return ['product_cat', $slug];
        }

        return ['product_cat', 'gach'];
    }

    private static function resolveBaseUrl(string $taxonomy, string $slug, ?string $origin): string {
        $path = match ($taxonomy) {
            'pa_bo-suu-tap' => '/bo-suu-tap/' . $slug,
            'pa_xuat-xu'    => '/xuat-xu/' . $slug,
            default         => '/' . $slug,
        };

        if ($origin !== null && $origin !== '') {
            return rtrim($origin, '/') . $path;
        }

        if (function_exists('home_url')) {
            return UrlNormalizer::untrailingslashUrl(home_url($path));
        }

        return 'https://gachdravit.vn' . $path;
    }

    private static function replaceUrlPath(string $reference_url, string $new_path): string {
        $parts = function_exists('wp_parse_url') ? wp_parse_url($reference_url) : parse_url($reference_url);
        if (is_array($parts) && isset($parts['scheme'], $parts['host'])) {
            $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
            return $origin . '/' . ltrim(rtrim($new_path, '/'), '/');
        }
        return '/' . ltrim(rtrim($new_path, '/'), '/');
    }
}
