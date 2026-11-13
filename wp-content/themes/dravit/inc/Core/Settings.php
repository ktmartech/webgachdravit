<?php
declare(strict_types=1);

namespace Dravit\Core;

defined('ABSPATH') || exit;

final class Settings implements ServiceProviderInterface {
    public const OPTION_KEY = 'drv_settings';

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public function register(): void {
        add_action('update_option_' . self::OPTION_KEY, [self::class, 'on_option_updated']);
        add_action('add_option_' . self::OPTION_KEY, [self::class, 'on_option_updated']);
    }

    public static function on_option_updated(): void {
        self::flush_cache();
        if (function_exists('delete_transient')) {
            delete_transient('drv_mega_html');
        }
        if (function_exists('do_action')) {
            do_action('litespeed_purge', 'drv_global_shell');
        }
    }

    /**
     * Giá trị mặc định toàn hệ thống (9 nhóm cấu hình theo docs/14_Data_Dictionary_And_Settings.md §8 & D-31..D-35).
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array {
        return [
            'company'  => [
                'legal_name'     => 'Công ty TNHH Vật Liệu Kiến Trúc DRAVIT',
                'brand_name'     => 'DRAVIT',
                'tagline'        => 'Thẩm mỹ từ những đường nét nhỏ nhất.',
                'tax_id'         => '0318688999',
                'address'        => '68 Nguyễn Cơ Thạch, Phường An Khánh, TP. Hồ Chí Minh',
                'email'          => 'cskh@gachdravit.vn',
                'bct_url'        => '',
                'header_logo_id' => 0,
                'footer_logo_id' => 0,
            ],
            'contact'  => [
                'hotline'        => '0909 888 777',
                'zalo'           => '0909888777',
                'project_email'  => 'duan@gachdravit.vn',
                'email'          => 'cskh@gachdravit.vn',
                'working_hours'  => '08:00 - 21:00 (Thứ 2 - Chủ nhật)',
                'open_hour'      => 8,
                'close_hour'     => 21,
            ],
            'social'   => [
                'facebook'  => 'https://facebook.com/gachdravit',
                'youtube'   => 'https://youtube.com/@gachdravit',
                'tiktok'    => 'https://tiktok.com/@gachdravit',
                'instagram' => '',
                'pinterest' => '',
            ],
            'showrooms' => [
                [
                    'name'        => 'Showroom Flagship Sala',
                    'address'     => '68 Nguyễn Cơ Thạch, Phường An Khánh, TP. Hồ Chí Minh',
                    'phone'       => '0909 888 777',
                    'hours'       => '08:00 - 21:00 (T2 - CN)',
                    'maps_url'    => 'https://maps.google.com',
                    'after_img_id' => 0,
                    'before_img_id' => 0,
                ],
            ],
            'bank'     => [
                'bank_bin'     => '970436',
                'bank_name'    => 'Vietcombank',
                'account_no'   => '',
                'account_name' => 'CONG TY TNHH VAT LIEU KIEN TRUC DRAVIT',
                'branch'       => 'CN TP. Hồ Chí Minh',
            ],
            'order'    => [
                'default_waste_pct' => 5,
                'waste_options'     => [0, 5, 10],
                'transfer_due_days' => 3,
                'auto_cancel_hours' => 72,
                'sla_text'          => 'Phản hồi trong 30 phút giờ làm việc (08:00 - 21:00)',
            ],
            'quote'    => [
                'max_items'    => 30,
                'volume_tiers' => [
                    ['min_m2' => 30, 'discount_pct' => 5],
                    ['min_m2' => 100, 'discount_pct' => 8],
                ],
            ],
            'home'     => [
                'hero_eyebrow' => 'Kho gạch kiến trúc kiểm soát đồng lô',
                'hero_title'   => 'CHỌN ĐÚNG MẪU GẠCH THEO TỪNG KHÔNG GIAN',
                'hero_sub'     => 'Tra cứu thông số chống trượt R9 - R12, tính chính xác số thùng nguyên đai trước khi đặt hàng.',
                'hero_img_id'  => 0,
            ],
            'catalog'  => [
                'per_page'  => 24,
                'new_days'  => 45,
                '404_silos' => ['gach-lat-nen', 'gach-phong-khach', 'gach-van-da', 'gach-80x80'],
            ],
            'search'   => [
                'popular_keywords'   => ['80x80', 'Vân đá Marble', 'Terrazzo', 'Khổ lớn 60x120', 'Chống trượt R10'],
                'featured_silos'     => [
                    'gach-phong-khach',
                    'gach-nha-tam',
                    'gach-phong-bep',
                    'gach-san-vuon',
                    'gach-mat-tien',
                    'gach-80x80',
                ],
                'pinned_product_ids' => [],
            ],
            'seo'      => [
                'llms_enabled'      => true,
                'geo_brand_summary' => 'DRAVIT là hệ thống phân phối gạch ốp lát kiến trúc và đá nung kết chính hãng tại Việt Nam, niêm yết giá minh bạch theo m² và giao dịch theo số nguyên viên/thùng.',
            ],
            'forms'    => [
                'quote_modal'      => 0,
                'pdp_consult'      => 0,
                'contact_page'     => 0,
                'showroom_booking' => 0,
                'blog_inline'      => 0,
            ],
            'features' => [
                'ai_enabled' => true,
            ],
            'ai'       => [
                'provider' => 'rule_only',
                'api_key'  => '',
                'model'    => '',
            ],
        ];
    }

    /**
     * Làm sạch toàn bộ dữ liệu nhập từ trang Cài đặt Dravit trước khi lưu vào DB.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function sanitize(array $input): array {
        $base = self::all();

        $text = static fn (mixed $v): string => function_exists('sanitize_text_field')
            ? sanitize_text_field((string) $v)
            : trim(strip_tags((string) $v));
        $url = static fn (mixed $v): string => function_exists('esc_url_raw')
            ? esc_url_raw(trim((string) $v))
            : (filter_var(trim((string) $v), FILTER_SANITIZE_URL) ?: '');
        $email = static fn (mixed $v): string => function_exists('sanitize_email')
            ? sanitize_email((string) $v)
            : trim((string) $v);

        if (isset($input['company']) && is_array($input['company'])) {
            foreach (['legal_name', 'brand_name', 'tagline', 'tax_id', 'address'] as $k) {
                if (array_key_exists($k, $input['company'])) {
                    $base['company'][$k] = $text($input['company'][$k]);
                }
            }
            if (array_key_exists('email', $input['company'])) {
                $base['company']['email'] = $email($input['company']['email']);
            }
            if (array_key_exists('bct_url', $input['company'])) {
                $base['company']['bct_url'] = $url($input['company']['bct_url']);
            }
            if (array_key_exists('header_logo_id', $input['company'])) {
                $base['company']['header_logo_id'] = max(0, (int) $input['company']['header_logo_id']);
            }
            if (array_key_exists('footer_logo_id', $input['company'])) {
                $base['company']['footer_logo_id'] = max(0, (int) $input['company']['footer_logo_id']);
            }
        }

        if (isset($input['contact']) && is_array($input['contact'])) {
            foreach (['hotline', 'zalo', 'working_hours'] as $k) {
                if (array_key_exists($k, $input['contact'])) {
                    $base['contact'][$k] = $text($input['contact'][$k]);
                }
            }
            foreach (['email', 'project_email'] as $k) {
                if (array_key_exists($k, $input['contact'])) {
                    $base['contact'][$k] = $email($input['contact'][$k]);
                }
            }
            if (isset($input['contact']['open_hour'])) {
                $base['contact']['open_hour'] = max(0, min(23, (int) $input['contact']['open_hour']));
            }
            if (isset($input['contact']['close_hour'])) {
                $base['contact']['close_hour'] = max(1, min(24, (int) $input['contact']['close_hour']));
            }
        }

        if (isset($input['social']) && is_array($input['social'])) {
            foreach (['facebook', 'youtube', 'tiktok', 'instagram', 'pinterest'] as $k) {
                if (array_key_exists($k, $input['social'])) {
                    $base['social'][$k] = $url($input['social'][$k]);
                }
            }
        }

        if (isset($input['showrooms']) && is_array($input['showrooms'])) {
            $clean_rooms = [];
            foreach ($input['showrooms'] as $sr) {
                if (!is_array($sr) || trim((string) ($sr['name'] ?? '')) === '') {
                    continue;
                }
                $clean_rooms[] = [
                    'name'        => $text($sr['name'] ?? ''),
                    'address'     => $text($sr['address'] ?? ''),
                    'phone'       => $text($sr['phone'] ?? ''),
                    'hours'       => $text($sr['hours'] ?? ''),
                    'maps_url'    => $url($sr['maps_url'] ?? ''),
                    'after_img_id' => max(0, (int) ($sr['after_img_id'] ?? 0)),
                    'before_img_id' => max(0, (int) ($sr['before_img_id'] ?? 0)),
                ];
            }
            if ($clean_rooms !== []) {
                $base['showrooms'] = $clean_rooms;
            }
        }

        if (isset($input['bank']) && is_array($input['bank'])) {
            $base['bank']['bank_bin']     = (string) preg_replace('/[^0-9]/', '', (string) ($input['bank']['bank_bin'] ?? $base['bank']['bank_bin']));
            $base['bank']['bank_name']    = $text($input['bank']['bank_name'] ?? $base['bank']['bank_name']);
            $base['bank']['account_no']   = (string) preg_replace('/[^0-9A-Za-z]/', '', (string) ($input['bank']['account_no'] ?? $base['bank']['account_no']));
            $base['bank']['account_name'] = strtoupper($text($input['bank']['account_name'] ?? $base['bank']['account_name']));
            $base['bank']['branch']       = $text($input['bank']['branch'] ?? $base['bank']['branch']);
        }

        if (isset($input['order']) && is_array($input['order'])) {
            $base['order']['default_waste_pct'] = max(0, min(25, (int) ($input['order']['default_waste_pct'] ?? 5)));
            $base['order']['transfer_due_days'] = max(1, min(14, (int) ($input['order']['transfer_due_days'] ?? 3)));
            $base['order']['auto_cancel_hours'] = max(12, min(336, (int) ($input['order']['auto_cancel_hours'] ?? 72)));
            if (array_key_exists('sla_text', $input['order'])) {
                $base['order']['sla_text'] = $text($input['order']['sla_text']);
            }
        }

        if (isset($input['quote']) && is_array($input['quote'])) {
            $base['quote']['max_items'] = max(5, min(50, (int) ($input['quote']['max_items'] ?? 30)));
        }

        if (isset($input['home']) && is_array($input['home'])) {
            foreach (['hero_eyebrow', 'hero_title', 'hero_sub'] as $k) {
                if (array_key_exists($k, $input['home'])) {
                    $base['home'][$k] = $text($input['home'][$k]);
                }
            }
            if (isset($input['home']['hero_img_id'])) {
                $base['home']['hero_img_id'] = max(0, (int) $input['home']['hero_img_id']);
            }
        }

        if (isset($input['catalog']) && is_array($input['catalog'])) {
            if (isset($input['catalog']['per_page'])) {
                $base['catalog']['per_page'] = max(12, min(48, (int) $input['catalog']['per_page']));
            }
            if (isset($input['catalog']['new_days'])) {
                $base['catalog']['new_days'] = max(7, min(180, (int) $input['catalog']['new_days']));
            }
            if (isset($input['catalog']['404_silos']) && is_array($input['catalog']['404_silos'])) {
                $silos = [];
                foreach ($input['catalog']['404_silos'] as $silo) {
                    $clean_silo = function_exists('sanitize_key')
                        ? sanitize_key((string) $silo)
                        : (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $silo));
                    if ($clean_silo !== '') {
                        $silos[] = $clean_silo;
                    }
                }
                if ($silos !== []) {
                    $base['catalog']['404_silos'] = $silos;
                }
            }
        }

        if (isset($input['search']) && is_array($input['search'])) {
            if (array_key_exists('popular_keywords', $input['search'])) {
                $raw_kws = $input['search']['popular_keywords'];
                if (is_string($raw_kws)) {
                    $raw_kws = preg_split('/[\r\n,]+/', $raw_kws) ?: [];
                }
                if (is_array($raw_kws)) {
                    $keywords = [];
                    foreach ($raw_kws as $kw) {
                        $clean_kw = $text($kw);
                        if ($clean_kw !== '' && !in_array($clean_kw, $keywords, true)) {
                            $keywords[] = $clean_kw;
                        }
                    }
                    $base['search']['popular_keywords'] = $keywords !== []
                        ? array_slice($keywords, 0, 8)
                        : self::defaults()['search']['popular_keywords'];
                }
            }
            if (isset($input['search']['featured_silos']) && is_array($input['search']['featured_silos'])) {
                $f_silos = [];
                foreach ($input['search']['featured_silos'] as $silo) {
                    $clean_silo = function_exists('sanitize_key')
                        ? sanitize_key((string) $silo)
                        : (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $silo));
                    if ($clean_silo !== '' && !in_array($clean_silo, $f_silos, true)) {
                        $f_silos[] = $clean_silo;
                    }
                }
                $base['search']['featured_silos'] = $f_silos !== []
                    ? array_slice($f_silos, 0, 6)
                    : self::defaults()['search']['featured_silos'];
            }
            if (isset($input['search']['pinned_product_ids']) && is_array($input['search']['pinned_product_ids'])) {
                $pinned = [];
                foreach ($input['search']['pinned_product_ids'] as $raw_pid) {
                    $pid = max(0, (int) $raw_pid);
                    if ($pid > 0 && !in_array($pid, $pinned, true)) {
                        $pinned[] = $pid;
                    }
                }
                $base['search']['pinned_product_ids'] = array_slice($pinned, 0, \Dravit\Catalog\SearchTrending::MAX_PINNED);
            }
        }

        if (isset($input['seo']) && is_array($input['seo'])) {
            $base['seo']['llms_enabled'] = !empty($input['seo']['llms_enabled']);
            if (array_key_exists('geo_brand_summary', $input['seo'])) {
                $base['seo']['geo_brand_summary'] = $text($input['seo']['geo_brand_summary']);
            }
        }

        if (isset($input['forms']) && is_array($input['forms'])) {
            foreach (['quote_modal', 'pdp_consult', 'contact_page', 'showroom_booking', 'blog_inline'] as $slot) {
                if (array_key_exists($slot, $input['forms'])) {
                    $base['forms'][$slot] = max(0, (int) $input['forms'][$slot]);
                }
            }
        }

        if (isset($input['features']) && is_array($input['features'])) {
            $base['features']['ai_enabled'] = !empty($input['features']['ai_enabled']);
        }

        if (isset($input['ai']) && is_array($input['ai'])) {
            $provider = (string) ($input['ai']['provider'] ?? 'rule_only');
            $base['ai']['provider'] = in_array($provider, ['rule_only', 'gemini', 'anthropic'], true) ? $provider : 'rule_only';
            if (array_key_exists('api_key', $input['ai'])) {
                $base['ai']['api_key'] = $text($input['ai']['api_key']);
            }
            if (array_key_exists('model', $input['ai'])) {
                $base['ai']['model'] = $text($input['ai']['model']);
            }
        }

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array {
        if (self::$cache === null) {
            $stored      = function_exists('get_option') ? get_option(self::OPTION_KEY, []) : [];
            $stored      = is_array($stored) ? $stored : [];
            self::$cache = self::mergeDefaults(self::defaults(), $stored);
        }

        return self::$cache;
    }

    /**
     * Hợp nhất đệ quy mảng kết hợp nhưng thay thế nguyên vẹn mảng tuần tự (`array_is_list`)
     * để tránh rò rỉ phần tử mặc định khi Admin lưu danh sách ngắn hơn.
     *
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $stored
     * @return array<string, mixed>
     */
    private static function mergeDefaults(array $defaults, array $stored): array {
        foreach ($stored as $key => $val) {
            if (!is_string($key)) {
                continue;
            }
            if (
                isset($defaults[$key])
                && is_array($defaults[$key])
                && is_array($val)
                && !array_is_list($defaults[$key])
                && !array_is_list($val)
            ) {
                $defaults[$key] = self::mergeDefaults($defaults[$key], $val);
            } else {
                $defaults[$key] = $val;
            }
        }

        return $defaults;
    }

    public static function get(string $path, mixed $default = null): mixed {
        $segments = explode('.', trim($path));
        $cursor   = self::all();

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return $default;
            }
            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    public static function flush_cache(): void {
        self::$cache = null;
    }
}
