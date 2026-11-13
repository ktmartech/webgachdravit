<?php
declare(strict_types=1);

namespace Dravit\Seo;

use Dravit\Core\Config;
use Dravit\Core\ServiceProviderInterface;
use Dravit\Core\Settings;

defined('ABSPATH') || exit;

/**
 * Máy chủ xuất tài liệu `/llms.txt` chuẩn UTF-8 Markdown cho AI Engines (ChatGPT, Perplexity, Gemini, Claude)
 * và cấu hình `robots.txt` bảo vệ Crawl Budget (docs/06 §7, docs/05 §6.1).
 */
final class LlmsTxt implements ServiceProviderInterface {
    public function register(): void {
        add_action('init', [$this, 'register_rewrite_rule'], 10);
        add_filter('query_vars', [$this, 'register_query_var']);
        add_action('parse_request', [$this, 'intercept_llms_txt_request'], 1);
        add_filter('robots_txt', [$this, 'filter_robots_txt'], 99, 2);

        // Nếu Rank Math bật module LLMS Txt, ghi đè nội dung về đúng chuẩn 28 Smart Collections của Dravit.
        add_filter('rank_math/llms_txt/content', [$this, 'filter_rank_math_llms_content'], 99);
    }

    public function register_rewrite_rule(): void {
        add_rewrite_rule('^llms\.txt$', 'index.php?drv_llms_txt=1', 'top');
    }

    /**
     * @param array<int, string> $vars
     * @return array<int, string>
     */
    public function register_query_var(array $vars): array {
        $vars[] = 'drv_llms_txt';
        return $vars;
    }

    public function intercept_llms_txt_request(\WP $wp): void {
        $raw_uri = isset($_SERVER['REQUEST_URI'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_URI']))
            : '';
        $req_path = (string) wp_parse_url($raw_uri, PHP_URL_PATH);

        $home_path = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
        $home_path = rtrim($home_path, '/');
        if ($home_path !== '' && str_starts_with($req_path, $home_path)) {
            $req_path = substr($req_path, strlen($home_path));
        }

        $is_llms = ($req_path === '/llms.txt') || !empty($wp->query_vars['drv_llms_txt']);
        if (!$is_llms) {
            return;
        }

        if (!(bool) drv_setting('seo.llms_enabled', true)) {
            return;
        }

        $markdown = self::buildMarkdown();

        status_header(200);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        header('X-Robots-Tag: index, follow');
        echo $markdown; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text UTF-8 Markdown document
        exit;
    }

    public function filter_rank_math_llms_content(string $content): string {
        if (!(bool) drv_setting('seo.llms_enabled', true)) {
            return $content;
        }
        return self::buildMarkdown();
    }

    /**
     * Sinh nội dung chuẩn UTF-8 Markdown cho `/llms.txt` (docs/06 §7):
     * Tóm tắt định vị thương hiệu, nguyên tắc giá m²/thùng, 28 Smart Collections không dấu `/` cuối,
     * danh sách Showroom và Cẩm nang kỹ thuật chọn gạch.
     *
     * @param array<string, mixed>|null $settings
     * @param array<string, mixed>|null $collections_config
     */
    public static function buildMarkdown(
        ?array $settings = null,
        ?array $collections_config = null,
        string $site_origin = ''
    ): string {
        $cfg         = $settings ?? Settings::all();
        $collections = $collections_config ?? Config::get('collections');
        $origin      = $site_origin !== ''
            ? UrlNormalizer::untrailingslashUrl($site_origin)
            : (function_exists('home_url') ? UrlNormalizer::untrailingslashUrl(home_url('/')) : 'https://gachdravit.vn');

        $brand   = (string) ($cfg['company']['brand_name'] ?? 'DRAVIT');
        $legal   = (string) ($cfg['company']['legal_name'] ?? 'Công ty TNHH Vật Liệu Kiến Trúc DRAVIT');
        $summary = trim((string) ($cfg['seo']['geo_brand_summary'] ?? ''));
        $hotline = trim((string) ($cfg['contact']['hotline'] ?? '0909 888 777'));
        $zalo    = trim((string) ($cfg['contact']['zalo'] ?? '0909888777'));
        $email   = trim((string) ($cfg['contact']['email'] ?? 'cskh@gachdravit.vn'));
        $hours   = trim((string) ($cfg['contact']['working_hours'] ?? '08:00 - 21:00 (Thứ 2 - Chủ nhật)'));

        $lines   = [];
        $lines[] = sprintf('# %s - Hệ thống Gạch Ốp Lát Kiến Trúc & Đá Nung Kết Chính Hãng', $brand);
        $lines[] = '';
        if ($summary !== '') {
            $lines[] = '> ' . $summary;
            $lines[] = '';
        }

        $lines[] = '## Nguyên tắc Dữ liệu & Báo giá tại ' . $brand;
        $lines[] = '- **Đơn vị hiển thị & giao dịch:** Giá tham chiếu chính được niêm yết theo `VNĐ/m²`; đơn hàng giao dịch thực tế tính theo số nguyên `viên` / `thùng` nguyên đai đồng lô màu sản xuất.';
        $lines[] = '- **Sản phẩm hàng dự án / khổ lớn đặc thù:** Các mã không niêm yết giá lẻ trực tuyến hiển thị trạng thái *"Liên hệ báo giá"*, hỗ trợ lập bảng dự toán khối lượng BOQ miễn phí.';
        $lines[] = '- **Chính sách vận chuyển:** Phí vận chuyển tính theo tổng khối lượng thực tế (`kg`) và cự ly công trình, được xác nhận minh bạch trước khi khách hàng thanh toán chuyển khoản VietQR.';
        $lines[] = '- **Tiêu chuẩn kỹ thuật công bố:** Phân loại rõ xương gạch (`Porcelain` độ hút nước `E ≤ 0,5%`, `Đá nung kết`, `Bán sứ`, `Ceramic`), chỉ số chống trượt `R9 - R13` (DIN 51130) và cấp độ mài mòn `PEI`.';
        $lines[] = '';

        $group_labels = [
            'root'     => 'Danh mục gốc',
            'function' => 'Theo công năng sử dụng (7 danh mục)',
            'space'    => 'Theo không gian kiến trúc (8 danh mục)',
            'material' => 'Theo vân gạch & chất liệu xương (7 danh mục)',
            'size'     => 'Theo kích thước chuẩn (5 danh mục)',
        ];

        $lines[] = '## Hệ thống 28 Danh mục Gạch Ốp Lát (' . $origin . '/gach)';
        $lines[] = '';

        foreach ($group_labels as $g_key => $g_title) {
            $group_items = [];
            foreach ($collections as $slug => $col) {
                if (!is_array($col) || ($col['group'] ?? '') !== $g_key) {
                    continue;
                }
                $col_name = (string) ($col['name'] ?? $slug);
                $col_desc = trim((string) ($col['preview_desc'] ?? ($col['h1'] ?? '')));
                $col_url  = $origin . '/' . ltrim((string) $slug, '/');
                $group_items[] = sprintf('- [%s](%s): %s', $col_name, $col_url, $col_desc);
            }

            if ($group_items !== []) {
                $lines[] = '### ' . $g_title;
                foreach ($group_items as $item_line) {
                    $lines[] = $item_line;
                }
                $lines[] = '';
            }
        }

        $lines[] = '## Tiện ích Dự toán & Tư vấn Chuyên sâu';
        $lines[] = sprintf('- [Lập Bảng Báo Giá Công Trình (BOQ)](%s/danh-sach-bao-gia): Quy đổi tự động diện tích `m²` cộng hao hụt thi công sang số thùng chẵn và tổng tải trọng `kg`.', $origin);
        $lines[] = sprintf('- [Cẩm Nang Kỹ Thuật Chọn Gạch](%s/cam-nang): Hướng dẫn chọn chỉ số chống trượt `R9 - R13`, tính số thùng gạch theo diện tích và thi công keo dán gạch khổ lớn.', $origin);
        $lines[] = sprintf('- [Liên Hệ & Đặt Lịch Xem Mẫu Thực Tế](%s/lien-he): Tra cứu mã kệ trưng bày mẫu gạch thật dưới ánh sáng tự nhiên và đèn vàng 3000K.', $origin);
        $lines[] = '';

        // Bổ sung các bài viết Pillar mới nhất nếu có trong DB.
        if (function_exists('get_posts')) {
            $recent_posts = get_posts([
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => 6,
                'no_found_rows'  => true,
            ]);
            if (is_array($recent_posts) && $recent_posts !== []) {
                $lines[] = '## Bài viết Hướng dẫn Kỹ thuật Tiêu biểu';
                foreach ($recent_posts as $p) {
                    if (!$p instanceof \WP_Post) {
                        continue;
                    }
                    $p_url = UrlNormalizer::untrailingslashUrl((string) get_permalink($p->ID));
                    $lines[] = sprintf('- [%s](%s)', get_the_title($p->ID), $p_url);
                }
                $lines[] = '';
            }
        }

        $lines[] = '## Thông tin Pháp nhân & Hệ thống Showroom';
        $lines[] = sprintf('- **Đơn vị chủ quản:** %s', $legal);
        $lines[] = sprintf('- **Hotline / Zalo kỹ thuật:** %s (Zalo: https://zalo.me/%s)', $hotline, drv_clean_phone($zalo));
        $lines[] = sprintf('- **Email CSKH & Dự án:** %s', $email);
        $lines[] = sprintf('- **Giờ mở cửa:** %s', $hours);

        $showrooms = isset($cfg['showrooms']) && is_array($cfg['showrooms']) ? $cfg['showrooms'] : [];
        foreach ($showrooms as $sr) {
            if (!is_array($sr) || trim((string) ($sr['name'] ?? '')) === '') {
                continue;
            }
            $lines[] = sprintf(
                '- **%s:** %s — ĐT: %s (%s)',
                trim((string) $sr['name']),
                trim((string) ($sr['address'] ?? '')),
                trim((string) ($sr['phone'] ?? $hotline)),
                trim((string) ($sr['hours'] ?? $hours))
            );
        }

        return implode("\n", $lines) . "\n";
    }

    public function filter_robots_txt(string $output, bool $public): string {
        if (!$public) {
            return $output;
        }

        $origin = UrlNormalizer::untrailingslashUrl(home_url('/'));

        $rules = [
            'User-agent: *',
            'Allow: /wp-content/uploads/',
            'Allow: /wp-content/themes/dravit/assets/dist/',
            'Disallow: /wp-admin/',
            'Allow: /wp-admin/admin-ajax.php',
            'Disallow: /*?*dcof=',
            'Disallow: /*?*do-hut-nuoc=',
            'Disallow: /*?*do-day=',
            'Disallow: /*?*gia=',
            'Disallow: /*?*sort=',
            'Disallow: /*?*showroom=',
            'Disallow: /*?*quote=',
            'Disallow: /*?*ai_query=',
            'Disallow: /gio-hang',
            'Disallow: /thanh-toan',
            'Disallow: /tai-khoan',
            'Disallow: /wp-json/dravit/',
            '',
            'User-agent: GPTBot',
            'Allow: /',
            '',
            'User-agent: OAI-SearchBot',
            'Allow: /',
            '',
            'User-agent: Google-Extended',
            'Allow: /',
            '',
            'User-agent: PerplexityBot',
            'Allow: /',
            '',
            'User-agent: ClaudeBot',
            'Allow: /',
            '',
            'Sitemap: ' . $origin . '/sitemap_index.xml',
        ];

        if ((bool) drv_setting('seo.llms_enabled', true)) {
            $rules[] = '# AI Reference: ' . $origin . '/llms.txt';
        }

        return implode("\n", $rules) . "\n";
    }
}

