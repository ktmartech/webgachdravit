<?php
declare(strict_types=1);

namespace Dravit\Seo;

use Dravit\Core\Config;
use Dravit\Core\ServiceProviderInterface;
use Dravit\Units\PriceState;

defined('ABSPATH') || exit;

/**
 * Chuẩn hóa `<title>` (`<= 60` ký tự, cấm dấu gạch ngang dài `—`) và `meta description` tự động
 * cho 28 Smart Collections & trang chi tiết sản phẩm PDP (docs/06 §3).
 */
final class MetaTags implements ServiceProviderInterface {
    public function register(): void {
        add_filter('document_title_separator', static fn (): string => '|', 20);
        add_filter('rank_math/frontend/title', [$this, 'filter_title'], 20, 1);
        add_filter('rank_math/frontend/description', [$this, 'filter_description'], 20, 1);

        if (!drv_is_rank_math_active()) {
            add_filter('pre_get_document_title', [$this, 'filter_wp_document_title'], 20, 1);
            add_action('wp_head', [$this, 'print_fallback_meta_description'], 6);
        }
    }

    /**
     * Khử dấu `—` / `–` và cắt gọn thông minh nếu vượt giới hạn ký tự.
     */
    public static function sanitizeTitle(string $title, int $max_chars = 60): string {
        $clean = str_replace(['—', '–'], ['-', '-'], trim(wp_strip_all_tags($title)));
        $clean = (string) preg_replace('/\s+/', ' ', $clean);

        if (mb_strlen($clean) <= $max_chars) {
            return $clean;
        }

        // Ưu tiên bỏ hậu tố phụ nhưng vẫn giữ thương hiệu `| DRAVIT` nếu có.
        if (str_ends_with($clean, '| DRAVIT')) {
            $suffix = ' | DRAVIT';
            $budget = max(20, $max_chars - mb_strlen($suffix));
            $head   = trim(mb_substr($clean, 0, mb_strlen($clean) - mb_strlen($suffix)));
            $head   = rtrim($head, '|- ');
            if (mb_strlen($head) > $budget) {
                $cut  = mb_substr($head, 0, $budget);
                $sp   = mb_strrpos($cut, ' ');
                $head = ($sp !== false && $sp > 15) ? mb_substr($cut, 0, $sp) : $cut;
            }
            return trim($head) . $suffix;
        }

        $cut = mb_substr($clean, 0, $max_chars);
        $sp  = mb_strrpos($cut, ' ');
        return ($sp !== false && $sp > 20) ? trim(mb_substr($cut, 0, $sp)) : trim($cut);
    }

    public static function buildCollectionFallbackDescription(string $slug, string $name, int $count = 0): string {
        $collections  = Config::get('collections');
        $preview_desc = trim((string) ($collections[$slug]['preview_desc'] ?? ''));

        $prefix = $count > 0
            ? sprintf('Khám phá %d mẫu %s chính hãng tại DRAVIT.', $count, mb_strtolower($name))
            : sprintf('Tổng hợp các mẫu %s chính hãng tại kho DRAVIT.', mb_strtolower($name));

        if ($preview_desc !== '') {
            $combined = $prefix . ' ' . $preview_desc;
            return mb_strlen($combined) <= 160 ? $combined : mb_substr($combined, 0, 157) . '...';
        }

        return $prefix . ' Báo giá minh bạch theo m², kiểm soát đồng lô nguyên thùng.';
    }

    public static function buildProductFallbackDescription(\WC_Product $product): string {
        $name  = $product->get_name();
        $sku   = $product->get_sku();
        $state = PriceState::resolve($product);
        $ppb   = max(1, (int) ($product->get_meta('drv_pieces_per_box', true) ?: 1));
        $m2_bx = (float) ($product->get_meta('drv_m2_per_box', true) ?: 0);

        $pack_str = $m2_bx > 0.0
            ? sprintf('%d viên/thùng (%s m²)', $ppb, drv_format_decimal($m2_bx, 2))
            : sprintf('%d viên/thùng', $ppb);

        if ($state === PriceState::QUOTE_ONLY) {
            $desc = sprintf(
                '%s%s quy cách %s. Nhận báo giá chiết khấu theo khối lượng thực tế tại kho DRAVIT.',
                $name,
                $sku !== '' ? ' (' . $sku . ')' : '',
                $pack_str
            );
        } else {
            $reg_m2  = max(0, (int) $product->get_meta('drv_price_m2', true));
            $sale_m2 = max(0, (int) $product->get_meta('drv_sale_price_m2', true));
            $eff_m2  = ($state === PriceState::SALE && $sale_m2 > 0 && $sale_m2 < $reg_m2) ? $sale_m2 : $reg_m2;
            $desc    = sprintf(
                '%s%s giá %s/m², quy cách %s, giao nguyên đai đồng lô từ DRAVIT.',
                $name,
                $sku !== '' ? ' (' . $sku . ')' : '',
                drv_format_vnd($eff_m2),
                $pack_str
            );
        }

        return mb_strlen($desc) <= 160 ? $desc : mb_substr($desc, 0, 157) . '...';
    }

    public function filter_title(string $title): string {
        $computed = $this->resolve_contextual_title($title);
        return self::sanitizeTitle($computed);
    }

    public function filter_wp_document_title(string $title): string {
        $computed = $this->resolve_contextual_title($title);
        if ($computed === '') {
            return '';
        }
        return self::sanitizeTitle($computed);
    }

    public function filter_description(string $description): string {
        $clean = trim(str_replace('—', '-', $description));
        if ($clean !== '') {
            return $clean;
        }

        return $this->resolve_contextual_description();
    }

    public function print_fallback_meta_description(): void {
        if (is_404() || is_search()) {
            return;
        }

        $desc = $this->resolve_contextual_description();
        if ($desc === '') {
            return;
        }

        printf('<meta name="description" content="%s">' . "\n", esc_attr($desc));
    }

    private function resolve_contextual_title(string $existing): string {
        $brand = (string) drv_setting('company.brand_name', 'DRAVIT');

        if (is_front_page() && !is_home()) {
            return sprintf('%s | Gạch Ốp Lát Kiến Trúc & Đá Nung Kết', $brand);
        }

        if (drv_is_woo_active() && is_shop()) {
            return sprintf('Kho Gạch Ốp Lát Chính Hãng | %s', $brand);
        }

        if (drv_is_woo_active() && is_product_category()) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $rm_title = trim((string) get_term_meta($term->term_id, 'rank_math_title', true));
                if ($rm_title !== '' && $existing !== '') {
                    return $existing;
                }
                return sprintf('%s Đẹp, Báo Giá m² | %s', $term->name, $brand);
            }
        }

        if (drv_is_woo_active() && is_product()) {
            $pid      = get_queried_object_id();
            $rm_title = $pid > 0 ? trim((string) get_post_meta($pid, 'rank_math_title', true)) : '';
            if ($rm_title !== '' && $existing !== '') {
                return $existing;
            }
            if ($pid > 0 && function_exists('wc_get_product')) {
                $product = wc_get_product($pid);
                if ($product instanceof \WC_Product) {
                    $sku = $product->get_sku();
                    $raw = $sku !== '' && !str_contains($product->get_name(), $sku)
                        ? sprintf('%s %s | %s', $product->get_name(), $sku, $brand)
                        : sprintf('%s | %s', $product->get_name(), $brand);
                    return $raw;
                }
            }
        }

        if ($existing !== '') {
            return $existing;
        }

        if (is_singular()) {
            return sprintf('%s | %s', get_the_title(), $brand);
        }

        return '';
    }

    private function resolve_contextual_description(): string {
        if (is_front_page() && !is_home()) {
            return (string) drv_setting('seo.geo_brand_summary', '');
        }

        if (drv_is_woo_active() && is_shop()) {
            return self::buildCollectionFallbackDescription('gach', 'Gạch ốp lát', 0);
        }

        if (drv_is_woo_active() && is_product_category()) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $rm_desc = trim((string) get_term_meta($term->term_id, 'rank_math_description', true));
                if ($rm_desc !== '') {
                    return str_replace('—', '-', $rm_desc);
                }
                return self::buildCollectionFallbackDescription($term->slug, $term->name, (int) $term->count);
            }
        }

        if (drv_is_woo_active() && is_product() && function_exists('wc_get_product')) {
            $pid     = get_queried_object_id();
            $rm_desc = $pid > 0 ? trim((string) get_post_meta($pid, 'rank_math_description', true)) : '';
            if ($rm_desc !== '') {
                return str_replace('—', '-', $rm_desc);
            }
            $product = $pid > 0 ? wc_get_product($pid) : null;
            if ($product instanceof \WC_Product) {
                return self::buildProductFallbackDescription($product);
            }
        }

        if (is_singular()) {
            $pid     = get_queried_object_id();
            $rm_desc = $pid > 0 ? trim((string) get_post_meta($pid, 'rank_math_description', true)) : '';
            if ($rm_desc !== '') {
                return str_replace('—', '-', $rm_desc);
            }
            $excerpt = has_excerpt($pid) ? get_the_excerpt($pid) : '';
            if ($excerpt !== '') {
                return str_replace('—', '-', wp_strip_all_tags($excerpt));
            }
        }

        return '';
    }
}

