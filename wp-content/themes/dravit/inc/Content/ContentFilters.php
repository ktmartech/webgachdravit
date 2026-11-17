<?php
declare(strict_types=1);

namespace Dravit\Content;

use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Chuẩn hóa HTML nội dung bài viết Cẩm nang & trang Chính sách (Phương án A1, ADR D-28, D-36, STRIP-18, STRIP-20):
 * 1. Khử sạch các thuộc tính inline Microdata (`itemscope`, `itemtype`, `itemprop`) tránh nhân đôi Schema với Rank Math.
 * 2. Tự động bọc mọi thẻ `<table>` trần trong `.drv-geo-table-wrap` (cuộn ngang mobile + `scope="col"` cho AI/GEO).
 * 3. Tự động gắn thuộc tính `data-pswp-item` cho ảnh bài viết để click phóng to mượt bằng PhotoSwipe 5 dùng chung PDP.
 */
final class ContentFilters implements ServiceProviderInterface {
    public function register(): void {
        add_filter('the_content', [$this, 'enhance_editorial_content'], 14);
    }

    public function enhance_editorial_content(string $content): string {
        if (is_admin() || $content === '' || !function_exists('is_main_query') || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $is_post   = function_exists('is_singular') && is_singular('post');
        $is_policy = function_exists('is_page_template') && is_page_template('page-templates/template-policy.php');

        if (!$is_post && !$is_policy) {
            return $content;
        }

        $out = self::stripMicrodataAttributes($content);
        $out = self::wrapStandaloneTables($out);

        if ($is_post) {
            $out = self::enhanceProseImagesForLightbox($out);
        }

        return $out;
    }

    /**
     * Gỡ bỏ `itemscope`, `itemtype="..."`, `itemprop="..."` nếu biên tập viên dán nhầm từ HTML ngoài (`STRIP-20`).
     */
    public static function stripMicrodataAttributes(string $html): string {
        if (stripos($html, 'item') === false) {
            return $html;
        }

        $cleaned = (string) preg_replace('/\s+item(?:type|prop)\s*=\s*(["\'])[^"\']*\1/iu', '', $html);
        $cleaned = (string) preg_replace('/\s+itemscope(?:\s*=\s*(["\'])[^"\']*\1)?/iu', '', $cleaned);

        return $cleaned;
    }

    /**
     * Bọc các thẻ `<table>` chưa có class `.drv-geo-table` / `.drv-spec-table` vào `.drv-geo-table-wrap`
     * và gắn `scope="col"` cho các thẻ `<th>` trong `<thead>` (`docs/20 §3.2.2`).
     */
    public static function wrapStandaloneTables(string $html): string {
        if (stripos($html, '<table') === false) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/<table(\s[^>]*)?>(.*?)<\/table>/isu',
            static function (array $m): string {
                $attrs = $m[1];
                $inner = $m[2];

                // Bỏ qua các bảng đã được shortcode `[drv_price_table]` hoặc `drv_ui('geo-table')` chuẩn hóa sẵn.
                if (preg_match('/\bclass\s*=\s*(["\'])[^"\']*\b(?:drv-geo-table|drv-spec-table|drv-r-table)\b/i', $attrs) === 1) {
                    return $m[0];
                }

                // Đảm bảo mọi `<th>` trong `<thead>` đều có `scope="col"`.
                $inner = (string) preg_replace_callback(
                    '/<thead\b[^>]*>(.*?)<\/thead>/isu',
                    static function (array $tm): string {
                        return (string) preg_replace_callback(
                            '/<th(\s[^>]*)?>/iu',
                            static function (array $thm): string {
                                $th_attrs = isset($thm[1]) ? (string) $thm[1] : '';
                                if (stripos($th_attrs, 'scope=') !== false) {
                                    return $thm[0];
                                }
                                return '<th scope="col"' . $th_attrs . '>';
                            },
                            $tm[0]
                        );
                    },
                    $inner
                );

                if (preg_match('/\bclass\s*=\s*(["\'])([^"\']*)\1/i', $attrs) === 1) {
                    $new_attrs = (string) preg_replace(
                        '/\bclass\s*=\s*(["\'])([^"\']*)\1/i',
                        'class=$1$2 drv-geo-table$1',
                        $attrs,
                        1
                    );
                } else {
                    $new_attrs = ' class="drv-geo-table"' . $attrs;
                }

                return '<div class="drv-geo-table-wrap" tabindex="0" role="region" aria-label="Bảng thông số kỹ thuật (vuốt ngang để xem thêm)">'
                    . '<div class="drv-table-scroll-hint"><i class="ph-bold ph-arrows-out-line-horizontal drv-icon--xs" aria-hidden="true"></i>'
                    . '<span>Vuốt ngang bảng để xem đầy đủ các cột thông số</span></div>'
                    . '<table' . $new_attrs . '>' . $inner . '</table>'
                    . '</div>';
            },
            $html
        );
    }

    /**
     * Gắn `data-pswp-item` cho các thẻ `<figure>` / ảnh độc lập trong bài viết (bỏ qua card sản phẩm & thanh Shop the Look).
     */
    public static function enhanceProseImagesForLightbox(string $html): string {
        if (stripos($html, '<img') === false) {
            return $html;
        }

        // Bọc thẻ `<img>` nằm trong `<figure>` (Classic Editor caption / `.drv-figure-scene` / `.drv-wp-gallery__item`).
        return (string) preg_replace_callback(
            '/<figure\b([^>]*)>(.*?)<\/figure>/isu',
            static function (array $fm): string {
                $fig_attrs = (string) $fm[1];
                $fig_inner = (string) $fm[2];

                if (
                    stripos($fig_attrs, 'wp-block-pullquote') !== false
                    || stripos($fig_inner, 'data-pswp-item') !== false
                    || stripos($fig_inner, '<img') === false
                ) {
                    return $fm[0];
                }

                if (preg_match('/<img\b([^>]*)>/iu', $fig_inner, $im) !== 1) {
                    return $fm[0];
                }

                $img_tag   = (string) $im[0];
                $img_attrs = (string) $im[1];
                $meta      = self::extractImageLightboxMeta($img_attrs, $fig_inner);
                if ($meta['src'] === '') {
                    return $fm[0];
                }

                $safe_src = function_exists('esc_url') ? esc_url($meta['src']) : htmlspecialchars($meta['src'], ENT_QUOTES, 'UTF-8');
                $safe_cap = function_exists('esc_attr') ? esc_attr($meta['caption']) : htmlspecialchars($meta['caption'], ENT_QUOTES, 'UTF-8');
                $safe_lbl = function_exists('esc_attr')
                    ? esc_attr('Phóng to ảnh: ' . ($meta['alt'] !== '' ? $meta['alt'] : 'Chi tiết kỹ thuật'))
                    : 'Phóng to ảnh';

                // Nếu `<img>` chưa nằm trong `<a>`, bọc bằng `<a data-pswp-item>` chuẩn PhotoSwipe 5.
                if (stripos($fig_inner, '<a ') === false) {
                    $wrapped_img = sprintf(
                        '<a href="%s" class="drv-prose-zoom" data-pswp-item data-pswp-src="%s" data-pswp-width="%d" data-pswp-height="%d" data-pswp-caption="%s" aria-label="%s">%s</a>',
                        $safe_src,
                        $safe_src,
                        $meta['width'],
                        $meta['height'],
                        $safe_cap,
                        $safe_lbl,
                        self::ensureImgDimensionsAndLazy($img_tag)
                    );
                    $fig_inner = str_replace($img_tag, $wrapped_img, $fig_inner);
                }

                return '<figure' . $fig_attrs . '>' . $fig_inner . '</figure>';
            },
            $html
        );
    }

    /**
     * @return array{src: string, width: int, height: int, alt: string, caption: string}
     */
    private static function extractImageLightboxMeta(string $img_attrs, string $figure_inner): array {
        $src    = self::readAttr($img_attrs, 'src');
        $alt    = self::readAttr($img_attrs, 'alt');
        $width  = max(0, (int) self::readAttr($img_attrs, 'width'));
        $height = max(0, (int) self::readAttr($img_attrs, 'height'));
        $cls    = self::readAttr($img_attrs, 'class');

        // Nếu là ảnh Media Library `wp-image-{id}`, ưu tiên lấy ảnh gốc `full` để zoom nét mặt vân gạch.
        if (preg_match('/\bwp-image-(\d+)\b/', $cls, $cm) === 1 && function_exists('wp_get_attachment_image_src')) {
            $att_id   = (int) $cm[1];
            $full_src = wp_get_attachment_image_src($att_id, 'full');
            if (is_array($full_src) && !empty($full_src[0])) {
                $src    = (string) $full_src[0];
                $width  = max($width, (int) ($full_src[1] ?? 1200));
                $height = max($height, (int) ($full_src[2] ?? 800));
            }
        }

        $caption = $alt;
        if (preg_match('/<figcaption\b[^>]*>(.*?)<\/figcaption>/isu', $figure_inner, $cap_m) === 1) {
            $cap_txt = function_exists('wp_strip_all_tags') ? wp_strip_all_tags((string) $cap_m[1]) : strip_tags((string) $cap_m[1]);
            $cap_txt = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($cap_txt, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if ($cap_txt !== '') {
                $caption = $cap_txt;
            }
        }

        return [
            'src'     => $src,
            'width'   => $width >= 400 ? $width : 1200,
            'height'  => $height >= 300 ? $height : 800,
            'alt'     => $alt,
            'caption' => $caption,
        ];
    }

    private static function ensureImgDimensionsAndLazy(string $img_tag): string {
        $out = $img_tag;
        if (stripos($out, 'decoding=') === false) {
            $out = (string) preg_replace('/<img\b/i', '<img decoding="async"', $out, 1);
        }
        if (stripos($out, 'loading=') === false && stripos($out, 'fetchpriority="high"') === false) {
            $out = (string) preg_replace('/<img\b/i', '<img loading="lazy"', $out, 1);
        }

        return $out;
    }

    private static function readAttr(string $attrs, string $name): string {
        $quoted = sprintf('/\b%s\s*=\s*(["\'])([^"\']*)\1/i', preg_quote($name, '/'));
        if (preg_match($quoted, $attrs, $m) === 1) {
            return html_entity_decode(trim($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return '';
    }
}
