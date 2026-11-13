<?php
declare(strict_types=1);

namespace Dravit\Seo;

use Dravit\Catalog\AttributeDeriver;
use Dravit\Core\ServiceProviderInterface;
use Dravit\Core\Settings;
use Dravit\Units\Calc;
use Dravit\Units\PriceState;

defined('ABSPATH') || exit;

/**
 * Kiến trúc Đồ thị Thực thể JSON-LD `@graph` duy nhất trên mỗi trang (docs/06 §5, ADR D-20, Test M-12..M-16):
 * - Tích hợp sâu vào `rank_math/json_ld` khi Rank Math hoạt động.
 * - Tự động phát fallback `@graph` hợp nhất trong `wp_head` khi chưa bật Rank Math.
 * - Vô hiệu hóa `WC_Structured_Data` mặc định của WooCommerce để triệt tiêu Schema `Product` trùng lặp / sai đơn vị.
 */
final class SchemaGraph implements ServiceProviderInterface {
    public function register(): void {
        add_action('init', [$this, 'disable_default_woo_structured_data'], 20);
        add_filter('rank_math/json_ld', [$this, 'filter_rank_math_graph'], 99, 2);

        if (!drv_is_rank_math_active()) {
            add_action('wp_head', [$this, 'print_standalone_graph'], 25);
        }
    }

    public function disable_default_woo_structured_data(): void {
        if (!function_exists('WC')) {
            return;
        }
        $wc              = WC();
        $vars            = is_object($wc) ? get_object_vars($wc) : [];
        $structured_data = $vars['structured_data'] ?? null;
        if (is_object($structured_data)) {
            remove_action('woocommerce_before_main_content', [$structured_data, 'generate_website_data'], 30);
            remove_action('woocommerce_breadcrumb', [$structured_data, 'generate_breadcrumblist_data'], 10);
            remove_action('woocommerce_single_product_summary', [$structured_data, 'generate_product_data'], 60);
            remove_action('wp_footer', [$structured_data, 'output_structured_data'], 10);
        }
    }

    /**
     * Xây thực thể `Product` từ đối tượng `\WC_Product` thực tế.
     *
     * @return array<string, mixed>
     */
    public static function buildProductNode(\WC_Product $product, string $site_origin = ''): array {
        $id           = $product->get_id();
        $state        = PriceState::resolve($product);
        $unit_cfg     = PriceState::readUnitConfig($product);
        $model        = (string) ($product->get_meta('drv_pricing_model', true) ?: 'area_pack');
        $reg_m2       = max(0, (int) $product->get_meta('drv_price_m2', true));
        $sale_m2      = max(0, (int) $product->get_meta('drv_sale_price_m2', true));
        $eff_m2       = ($state === PriceState::SALE && $sale_m2 > 0 && $sale_m2 < $reg_m2) ? $sale_m2 : $reg_m2;
        $price_piece  = ($state === PriceState::QUOTE_ONLY) ? 0 : (int) round((float) $product->get_price());

        $w_mm         = max(0, (int) $product->get_meta('drv_piece_w_mm', true));
        $h_mm         = max(0, (int) $product->get_meta('drv_piece_h_mm', true));
        $thickness_mm = max(0.0, (float) $product->get_meta('drv_thickness_mm', true));
        $kg_per_box   = max(0.0, (float) $product->get_meta('drv_weight_kg_per_box', true));
        $ppb          = $unit_cfg['pieces_per_box'];
        $area_piece   = Calc::pieceAreaM2($w_mm, $h_mm);
        $m2_box       = (float) $product->get_meta('drv_m2_per_box', true);
        if ($m2_box <= 0.0 && $area_piece > 0.0) {
            $m2_box = round($area_piece * $ppb, 4);
        }

        $sale_to      = $product->get_date_on_sale_to();
        $valid_until  = $sale_to !== null ? $sale_to->date('Y-m-d') : gmdate('Y') . '-12-31';

        $images = [];
        $img_ids = array_values(array_filter(array_merge(
            [(int) $product->get_image_id()],
            array_map('intval', $product->get_gallery_image_ids())
        )));
        if (function_exists('wp_get_attachment_image_url')) {
            foreach ($img_ids as $aid) {
                $u = wp_get_attachment_image_url($aid, 'full');
                if (is_string($u) && $u !== '') {
                    $images[] = $u;
                }
            }
        }
        if ($images === []) {
            $images[] = DRV_URI . '/assets/images/placeholders/tile-marble-calacatta.svg';
        }

        $short_desc = trim(wp_strip_all_tags($product->get_short_description()));
        if ($short_desc === '') {
            $short_desc = MetaTags::buildProductFallbackDescription($product);
        }

        $permalink = function_exists('get_permalink') && $id > 0
            ? (string) get_permalink($id)
            : self::resolveOrigin($site_origin) . '/san-pham/' . $product->get_slug();

        return self::buildProductNodeFromData([
            'name'             => $product->get_name(),
            'url'              => $permalink,
            'sku'              => (string) $product->get_sku(),
            'description'      => $short_desc,
            'images'           => $images,
            'pricing_model'    => $model,
            'price_state'      => $state,
            'price_piece'      => $price_piece,
            'price_m2'         => $eff_m2,
            'in_stock'         => $product->is_in_stock(),
            'price_valid_until'=> $valid_until,
            'unit_label'       => $unit_cfg['unit_label'],
            'pieces_per_box'   => $ppb,
            'm2_per_box'       => $m2_box,
            'w_mm'             => $w_mm,
            'h_mm'             => $h_mm,
            'thickness_mm'     => $thickness_mm,
            'kg_per_box'       => $kg_per_box,
            'material'         => self::joinedTermNames($id, 'pa_chat-lieu'),
            'color'            => self::joinedTermNames($id, 'pa_mau-sac'),
            'pattern'          => self::joinedTermNames($id, 'pa_van-gach'),
            'series'           => self::joinedTermNames($id, 'pa_bo-suu-tap') ?: trim((string) $product->get_meta('drv_series', true)),
            'origin'           => self::joinedTermNames($id, 'pa_xuat-xu'),
            'surface'          => self::joinedTermNames($id, 'pa_be-mat'),
            'dcof'             => self::joinedTermNames($id, 'pa_dcof'),
            'water_absorption' => trim((string) $product->get_meta('drv_water_absorption', true)) ?: self::joinedTermNames($id, 'pa_do-hut-nuoc'),
            'pei_rating'       => trim((string) $product->get_meta('drv_pei_rating', true)),
        ], $site_origin);
    }

    /**
     * Hàm thuần xây dựng thực thể `Product` chuẩn Schema.org (docs/06 §5.2, Test M-13, M-16):
     * - Không bao giờ chèn `drv_lot_code` / `drv_shade` vào `mpn`.
     * - Khóa bỏ hoàn toàn `offers` khi `price_state === quote_only` hoặc giá <= 0 (ADR D-20).
     * - Gắn đủ 2 `UnitPriceSpecification` (`C62` viên + `MTK` m²) khi `priced` / `sale`.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function buildProductNodeFromData(array $data, string $site_origin = ''): array {
        $origin      = self::resolveOrigin($site_origin);
        $clean_url   = UrlNormalizer::untrailingslashUrl((string) ($data['url'] ?? ($origin . '/gach')));
        $name        = trim((string) ($data['name'] ?? ''));
        $sku         = trim((string) ($data['sku'] ?? ''));
        $desc        = trim((string) ($data['description'] ?? ''));
        $images      = isset($data['images']) && is_array($data['images']) ? array_values($data['images']) : [];
        $price_state = (string) ($data['price_state'] ?? PriceState::PRICED);
        $model       = (string) ($data['pricing_model'] ?? 'area_pack');
        $price_piece = max(0, (int) ($data['price_piece'] ?? 0));
        $price_m2    = max(0, (int) ($data['price_m2'] ?? 0));
        $in_stock    = !array_key_exists('in_stock', $data) || !empty($data['in_stock']);
        $valid_until = trim((string) ($data['price_valid_until'] ?? (gmdate('Y') . '-12-31')));
        $unit_label  = trim((string) ($data['unit_label'] ?? 'viên')) ?: 'viên';
        $ppb         = max(1, (int) ($data['pieces_per_box'] ?? 1));
        $m2_box      = max(0.0, (float) ($data['m2_per_box'] ?? 0.0));
        $w_mm        = max(0, (int) ($data['w_mm'] ?? 0));
        $h_mm        = max(0, (int) ($data['h_mm'] ?? 0));
        $thick_mm    = max(0.0, (float) ($data['thickness_mm'] ?? 0.0));
        $kg_per_box  = max(0.0, (float) ($data['kg_per_box'] ?? 0.0));

        $node = [
            '@type' => 'Product',
            '@id'   => $clean_url . '#product',
            'name'  => $name,
            'url'   => $clean_url,
            'brand' => [
                '@type' => 'Brand',
                'name'  => (string) drv_setting('company.brand_name', 'DRAVIT'),
            ],
        ];

        if ($sku !== '') {
            $node['sku'] = $sku;
        }
        if ($desc !== '') {
            $node['description'] = $desc;
        }
        if ($images !== []) {
            $node['image'] = $images;
        }

        foreach (['material', 'color', 'pattern'] as $field) {
            $val = trim((string) ($data[$field] ?? ''));
            if ($val !== '') {
                $node[$field] = $val;
            }
        }

        if ($w_mm > 0) {
            $node['width'] = [
                '@type'    => 'QuantitativeValue',
                'value'    => $w_mm,
                'unitCode' => 'MMT',
            ];
        }
        if ($h_mm > 0) {
            $node['height'] = [
                '@type'    => 'QuantitativeValue',
                'value'    => $h_mm,
                'unitCode' => 'MMT',
            ];
        }
        if ($thick_mm > 0.0) {
            $node['depth'] = [
                '@type'    => 'QuantitativeValue',
                'value'    => $thick_mm,
                'unitCode' => 'MMT',
            ];
        }
        if ($kg_per_box > 0.0 && $ppb > 0) {
            $node['weight'] = [
                '@type'    => 'QuantitativeValue',
                'value'    => round($kg_per_box / $ppb, 2),
                'unitCode' => 'KGM',
            ];
        }

        $props = [];
        $series = trim((string) ($data['series'] ?? ''));
        if ($series !== '') {
            $props[] = ['@type' => 'PropertyValue', 'name' => 'Bộ sưu tập', 'value' => $series];
        }
        $origin_country = trim((string) ($data['origin'] ?? ''));
        if ($origin_country !== '') {
            $props[] = ['@type' => 'PropertyValue', 'name' => 'Xuất xứ', 'value' => $origin_country];
        }
        if ($w_mm > 0 && $h_mm > 0) {
            $size_lbl = AttributeDeriver::deriveSizeLabel($w_mm, $h_mm);
            if ($size_lbl !== '') {
                $props[] = ['@type' => 'PropertyValue', 'name' => 'Kích thước danh định', 'value' => $size_lbl];
            }
        }
        $surface = trim((string) ($data['surface'] ?? ''));
        if ($surface !== '') {
            $props[] = ['@type' => 'PropertyValue', 'name' => 'Bề mặt', 'value' => $surface];
        }
        $water = trim((string) ($data['water_absorption'] ?? ''));
        if ($water !== '') {
            $water_txt = str_contains($water, '%') ? $water : ('≤ ' . $water . '%');
            $props[]   = ['@type' => 'PropertyValue', 'name' => 'Độ hút nước', 'value' => $water_txt];
        }
        $dcof = trim((string) ($data['dcof'] ?? ''));
        if ($dcof !== '') {
            $props[] = ['@type' => 'PropertyValue', 'name' => 'Chỉ số chống trượt', 'value' => $dcof];
        }
        $pei = trim((string) ($data['pei_rating'] ?? ''));
        if ($pei !== '') {
            $props[] = ['@type' => 'PropertyValue', 'name' => 'Độ chịu mài mòn (PEI)', 'value' => $pei];
        }
        if ($m2_box > 0.0) {
            $props[] = [
                '@type' => 'PropertyValue',
                'name'  => 'Quy cách đóng gói',
                'value' => sprintf('%d %s/thùng (%s m²/thùng)', $ppb, $unit_label, drv_format_decimal($m2_box, 2)),
            ];
        }

        if ($props !== []) {
            $node['additionalProperty'] = $props;
        }

        // ADR D-20 & M-13: Tuyệt đối KHÔNG xuất `offers` khi `quote_only` hoặc giá = 0đ.
        $is_quote = ($price_state === PriceState::QUOTE_ONLY)
            || ($price_piece <= 0)
            || ($model === 'area_pack' && $price_m2 <= 0);

        if (!$is_quote) {
            $price_specs = [
                [
                    '@type'         => 'UnitPriceSpecification',
                    'price'         => $price_piece,
                    'priceCurrency' => 'VND',
                    'unitCode'      => 'C62',
                    'unitText'      => $unit_label,
                ],
            ];

            if ($model === 'area_pack' && $price_m2 > 0) {
                $price_specs[] = [
                    '@type'             => 'UnitPriceSpecification',
                    'price'             => $price_m2,
                    'priceCurrency'     => 'VND',
                    'unitCode'          => 'MTK',
                    'unitText'          => 'm²',
                    'referenceQuantity' => [
                        '@type'    => 'QuantitativeValue',
                        'value'    => 1,
                        'unitCode' => 'MTK',
                    ],
                ];
            }

            $node['offers'] = [
                '@type'              => 'Offer',
                '@id'                => $clean_url . '#offer',
                'url'                => $clean_url,
                'priceCurrency'      => 'VND',
                'price'              => $price_piece,
                'priceValidUntil'    => $valid_until,
                'itemCondition'      => 'https://schema.org/NewCondition',
                'availability'       => $in_stock
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'seller'             => [
                    '@id' => $origin . '#organization',
                ],
                'priceSpecification' => $price_specs,
            ];
        }

        return $node;
    }

    /**
     * Xây các thực thể `CollectionPage`, `ItemList` (tối đa 24 SP) và `FAQPage` cho trang danh mục (docs/06 §5.3, Test M-12, M-14).
     *
     * @param array<int, array{name: string, url: string}> $products
     * @param array<int, array{q: string, a: string}>      $faq_items
     * @return array<string, array<string, mixed>>
     */
    public static function buildCollectionEntities(
        string $collection_url,
        string $name,
        string $description = '',
        array $products = [],
        array $faq_items = [],
        int $paged = 1,
        bool $has_filter_params = false,
        bool $is_sub_collection = true,
        string $site_origin = ''
    ): array {
        $origin    = self::resolveOrigin($site_origin);
        $clean_url = UrlNormalizer::untrailingslashUrl($collection_url);
        $gach_url  = $origin . '/gach';

        $entities = [];

        $collection_page = [
            '@type'    => 'CollectionPage',
            '@id'      => $clean_url . '#webpage',
            'url'      => $clean_url,
            'name'     => $name,
            'isPartOf' => [
                '@id' => $is_sub_collection ? ($gach_url . '#webpage') : ($origin . '#website'),
            ],
        ];
        if ($description !== '') {
            $collection_page['description'] = $description;
        }

        $is_clean_page_one = ($paged <= 1 && !$has_filter_params);

        // M-12: Chỉ phát `ItemList` ở trang 1 sạch không có param lọc và có >= 1 sản phẩm.
        if ($is_clean_page_one && $products !== []) {
            $slice         = array_slice($products, 0, 24);
            $list_elements = [];
            foreach ($slice as $idx => $item) {
                $item_url  = UrlNormalizer::untrailingslashUrl((string) ($item['url'] ?? ''));
                $item_name = trim((string) ($item['name'] ?? ''));
                if ($item_url === '' || $item_name === '') {
                    continue;
                }
                $list_elements[] = [
                    '@type'    => 'ListItem',
                    'position' => $idx + 1,
                    'url'      => $item_url,
                    'name'     => $item_name,
                ];
            }

            if ($list_elements !== []) {
                $item_list_id                  = $clean_url . '#itemlist';
                $collection_page['mainEntity'] = ['@id' => $item_list_id];
                $entities['itemList']          = [
                    '@type'           => 'ItemList',
                    '@id'             => $item_list_id,
                    'name'            => $name,
                    'numberOfItems'   => count($list_elements),
                    'itemListElement' => $list_elements,
                ];
            }
        }

        $entities['collectionPage'] = $collection_page;

        // M-14: Chỉ phát `FAQPage` khi có các cặp Q&A hiển thị thật ở trang 1 sạch.
        if ($is_clean_page_one && $faq_items !== []) {
            $faq_node = self::buildFaqNode($clean_url, $faq_items);
            if ($faq_node !== null) {
                $entities['faqPage'] = $faq_node;
            }
        }

        return $entities;
    }

    /**
     * Xây thực thể `FAQPage` từ mảng `{q, a}` đã kiểm duyệt.
     *
     * @param array<int, array{q: string, a: string}> $faq_items
     * @return array<string, mixed>|null
     */
    public static function buildFaqNode(string $page_url, array $faq_items): ?array {
        $parsed = FaqResolver::parseFaqItems($faq_items);
        if ($parsed === []) {
            return null;
        }

        $clean_url = UrlNormalizer::untrailingslashUrl($page_url);
        $questions = [];
        foreach ($parsed as $pair) {
            $questions[] = [
                '@type'          => 'Question',
                'name'           => $pair['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => trim(wp_strip_all_tags($pair['a'])),
                ],
            ];
        }

        return [
            '@type'      => 'FAQPage',
            '@id'        => $clean_url . '#faq',
            'mainEntity' => $questions,
        ];
    }

    /**
     * Xây thực thể `Organization` và chuỗi `HomeAndConstructionBusiness` từ `drv_settings` (docs/06 §5.4).
     *
     * @param array<string, mixed>|null $settings
     * @return array<string, array<string, mixed>>
     */
    public static function buildShowroomEntities(
        ?array $settings = null,
        bool $include_contact_page = false,
        string $site_origin = ''
    ): array {
        $cfg        = $settings ?? Settings::all();
        $origin     = self::resolveOrigin($site_origin);
        $brand_name = (string) ($cfg['company']['brand_name'] ?? 'DRAVIT');
        $legal_name = (string) ($cfg['company']['legal_name'] ?? $brand_name);
        $tax_id     = trim((string) ($cfg['company']['tax_id'] ?? ''));
        $hotline    = trim((string) ($cfg['contact']['hotline'] ?? ''));
        $email      = trim((string) ($cfg['contact']['email'] ?? ($cfg['company']['email'] ?? '')));
        $open_h     = str_pad((string) max(0, min(23, (int) ($cfg['contact']['open_hour'] ?? 8))), 2, '0', STR_PAD_LEFT) . ':00';
        $close_h    = str_pad((string) max(1, min(23, (int) ($cfg['contact']['close_hour'] ?? 21))), 2, '0', STR_PAD_LEFT) . ':00';

        $same_as = [];
        if (isset($cfg['social']) && is_array($cfg['social'])) {
            foreach ($cfg['social'] as $s_url) {
                $u = trim((string) $s_url);
                if ($u !== '') {
                    $same_as[] = $u;
                }
            }
        }

        $org = [
            '@type'     => 'Organization',
            '@id'       => $origin . '#organization',
            'name'      => $brand_name,
            'legalName' => $legal_name,
            'url'       => $origin,
        ];
        if ($tax_id !== '') {
            $org['taxID'] = $tax_id;
        }
        if ($hotline !== '') {
            $org['telephone'] = $hotline;
        }
        if ($email !== '') {
            $org['email'] = $email;
        }
        if ($same_as !== []) {
            $org['sameAs'] = $same_as;
        }

        $entities = [
            'organization' => $org,
        ];

        $showrooms = isset($cfg['showrooms']) && is_array($cfg['showrooms']) ? $cfg['showrooms'] : [];
        foreach ($showrooms as $idx => $sr) {
            if (!is_array($sr) || trim((string) ($sr['name'] ?? '')) === '') {
                continue;
            }
            $sr_key = 'showroom_' . ($idx + 1);
            $sr_id  = $origin . '#showroom-' . ($idx + 1);
            $sr_node = [
                '@type'                    => 'HomeAndConstructionBusiness',
                '@id'                      => $sr_id,
                'name'                     => $brand_name . ' - ' . trim((string) $sr['name']),
                'url'                      => $origin . '/lien-he',
                'telephone'                => trim((string) ($sr['phone'] ?? $hotline)),
                'priceRange'               => '$$',
                'parentOrganization'       => ['@id' => $origin . '#organization'],
                'address'                  => [
                    '@type'           => 'PostalAddress',
                    'streetAddress'   => trim((string) ($sr['address'] ?? '')),
                    'addressLocality' => 'TP. Hồ Chí Minh',
                    'addressCountry'  => 'VN',
                ],
                'openingHoursSpecification' => [
                    [
                        '@type'     => 'OpeningHoursSpecification',
                        'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                        'opens'     => $open_h,
                        'closes'    => $close_h,
                    ],
                ],
            ];
            if ($email !== '') {
                $sr_node['email'] = $email;
            }
            if (!empty($sr['maps_url'])) {
                $sr_node['hasMap'] = trim((string) $sr['maps_url']);
            }

            $entities[$sr_key] = $sr_node;
        }

        if ($include_contact_page) {
            $entities['contactPage'] = [
                '@type'    => 'ContactPage',
                '@id'      => $origin . '/lien-he#webpage',
                'url'      => $origin . '/lien-he',
                'name'     => 'Liên hệ & Hệ thống Showroom ' . $brand_name,
                'isPartOf' => ['@id' => $origin . '#website'],
                'about'    => ['@id' => $origin . '#organization'],
            ];
        }

        return $entities;
    }

    /**
     * Chuẩn hóa đồ thị cho bài viết Cẩm nang (`single.php`, docs/06 §5.5, Test M-15):
     * - Ép `Article` / `NewsArticle` thành `BlogPosting`.
     * - Gỡ sạch `HowTo` lỗi thời.
     * - Gắn `reviewedBy` (`Person`) khi có người kiểm duyệt kỹ thuật.
     *
     * @param array<string, mixed> $graph_data
     * @return array<string, mixed>
     */
    public static function sanitizeBlogEntities(
        array $graph_data,
        string $reviewer_name = '',
        string $reviewer_title = 'Kỹ sư Vật liệu Xây dựng'
    ): array {
        $cleaned = [];

        foreach ($graph_data as $key => $entity) {
            if (!is_array($entity)) {
                $cleaned[$key] = $entity;
                continue;
            }

            $type = $entity['@type'] ?? '';
            $types = is_array($type) ? $type : [$type];

            // Loại bỏ hoàn toàn HowTo deprecated.
            if (in_array('HowTo', $types, true)) {
                continue;
            }

            // Ép Article / NewsArticle thành BlogPosting.
            if (in_array('Article', $types, true) || in_array('NewsArticle', $types, true) || in_array('BlogPosting', $types, true)) {
                $entity['@type'] = 'BlogPosting';
                if ($reviewer_name !== '') {
                    $entity['reviewedBy'] = [
                        '@type'    => 'Person',
                        'name'     => $reviewer_name,
                        'jobTitle' => $reviewer_title,
                    ];
                }
            }

            if (in_array('WebPage', $types, true) && $reviewer_name !== '') {
                $entity['reviewedBy'] = [
                    '@type'    => 'Person',
                    'name'     => $reviewer_name,
                    'jobTitle' => $reviewer_title,
                ];
            }

            $cleaned[$key] = $entity;
        }

        return $cleaned;
    }

    /**
     * Bộ lọc chính hợp nhất vào đồ thị `@graph` của Rank Math (`rank_math/json_ld`).
     *
     * @param array<string, mixed> $data
     * @param mixed                $jsonld
     * @return array<string, mixed>
     */
    public function filter_rank_math_graph(array $data, mixed $jsonld = null): array {
        unset($jsonld);

        // Khử dấu `/` cuối trên mọi `url` và `@id` trong các node có sẵn của Rank Math.
        $data = $this->normalize_urls_recursive($data);

        // 1. PDP (`is_product()`): thay thế hoàn toàn node `Product` của Rank Math bằng node chuẩn Dravit.
        if (drv_is_woo_active() && is_product() && function_exists('wc_get_product')) {
            $product = wc_get_product(get_queried_object_id());
            if ($product instanceof \WC_Product) {
                foreach (array_keys($data) as $key) {
                    if (is_array($data[$key]) && ($data[$key]['@type'] ?? '') === 'Product') {
                        unset($data[$key]);
                    }
                }
                $data['richSnippet'] = self::buildProductNode($product);
            }
            return $data;
        }

        // 2. Collection & Public Attribute Archive: gắn `CollectionPage`, `ItemList`, `FAQPage`.
        if ($this->is_catalog_archive_view()) {
            $col_entities = $this->build_current_collection_entities();
            foreach ($col_entities as $k => $entity) {
                $data[$k] = $entity;
            }
            return $data;
        }

        // 3. Trang chủ `/` & Trang Liên hệ `/lien-he`: gắn `HomeAndConstructionBusiness` + `ContactPage`.
        if ((is_front_page() && !is_home()) || is_page('lien-he')) {
            $sr_entities = self::buildShowroomEntities(null, is_page('lien-he'));
            foreach ($sr_entities as $k => $entity) {
                $data[$k] = $entity;
            }
            $faq = FaqResolver::resolveForCurrentView();
            if ($faq !== []) {
                $faq_node = self::buildFaqNode((string) get_permalink(), $faq);
                if ($faq_node !== null) {
                    $data['faqPage'] = $faq_node;
                }
            }
            return $data;
        }

        // 4. Bài viết Cẩm nang (`single.php`): ép `BlogPosting`, gỡ `HowTo`, gắn `reviewedBy` + `FAQPage` nếu có.
        if (is_singular('post')) {
            $post_id       = get_queried_object_id();
            $reviewer_name = $post_id > 0 ? trim((string) get_post_meta($post_id, '_drv_reviewer_name', true)) : '';
            $reviewer_role = $post_id > 0 ? trim((string) get_post_meta($post_id, '_drv_reviewer_role', true)) : '';
            $data          = self::sanitizeBlogEntities(
                $data,
                $reviewer_name,
                $reviewer_role !== '' ? $reviewer_role : 'Kỹ sư Vật liệu Xây dựng'
            );

            $faq = FaqResolver::resolveForCurrentView();
            if ($faq !== []) {
                $faq_node = self::buildFaqNode((string) get_permalink($post_id), $faq);
                if ($faq_node !== null) {
                    $data['faqPage'] = $faq_node;
                }
            }
        }

        return $data;
    }

    /**
     * Xuất 1 thẻ `<script type="application/ld+json">` `@graph` duy nhất khi Rank Math chưa được kích hoạt.
     */
    public function print_standalone_graph(): void {
        if (is_404() || is_search()) {
            return;
        }

        $origin = self::resolveOrigin('');
        $graph  = [];

        // Website + Organization nền.
        $sr_entities = self::buildShowroomEntities(null, is_page('lien-he'), $origin);
        $graph[]     = $sr_entities['organization'];
        $graph[]     = [
            '@type'     => 'WebSite',
            '@id'       => $origin . '#website',
            'url'       => $origin,
            'name'      => (string) drv_setting('company.brand_name', 'DRAVIT'),
            'publisher' => ['@id' => $origin . '#organization'],
            'inLanguage'=> 'vi',
        ];

        // BreadcrumbList duy nhất.
        $breadcrumb = $this->build_breadcrumb_node();
        if ($breadcrumb !== null) {
            $graph[] = $breadcrumb;
        }

        if ((is_front_page() && !is_home()) || is_page('lien-he')) {
            foreach ($sr_entities as $k => $node) {
                if ($k !== 'organization') {
                    $graph[] = $node;
                }
            }
        } elseif (drv_is_woo_active() && is_product() && function_exists('wc_get_product')) {
            $product = wc_get_product(get_queried_object_id());
            if ($product instanceof \WC_Product) {
                $graph[] = self::buildProductNode($product, $origin);
            }
        } elseif ($this->is_catalog_archive_view()) {
            foreach ($this->build_current_collection_entities() as $node) {
                $graph[] = $node;
            }
        } elseif (is_singular('post')) {
            $post_id = get_queried_object_id();
            $post    = $post_id > 0 ? get_post($post_id) : null;
            if ($post instanceof \WP_Post) {
                $post_url      = UrlNormalizer::untrailingslashUrl((string) get_permalink($post_id));
                $reviewer_name = trim((string) get_post_meta($post_id, '_drv_reviewer_name', true));
                $reviewer_role = trim((string) get_post_meta($post_id, '_drv_reviewer_role', true)) ?: 'Kỹ sư Vật liệu Xây dựng';
                $blog_entities = self::sanitizeBlogEntities([
                    'blog' => [
                        '@type'         => 'BlogPosting',
                        '@id'           => $post_url . '#richSnippet',
                        'headline'      => get_the_title($post_id),
                        'url'           => $post_url,
                        'datePublished' => get_the_date(DATE_ATOM, $post_id),
                        'dateModified'  => get_the_modified_date(DATE_ATOM, $post_id),
                        'inLanguage'    => 'vi',
                        'publisher'     => ['@id' => $origin . '#organization'],
                    ],
                ], $reviewer_name, $reviewer_role);

                foreach ($blog_entities as $node) {
                    $graph[] = $node;
                }

                $faq = FaqResolver::resolveForCurrentView();
                if ($faq !== []) {
                    $faq_node = self::buildFaqNode($post_url, $faq);
                    if ($faq_node !== null) {
                        $graph[] = $faq_node;
                    }
                }
            }
        }

        $payload = [
            '@context' => 'https://schema.org',
            '@graph'   => $graph,
        ];

        $json = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($json) && $json !== '') {
            echo '<script type="application/ld+json" class="drv-schema-graph">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Encoded safely by wp_json_encode
        }
    }

    private function is_catalog_archive_view(): bool {
        if (drv_is_woo_active() && (is_shop() || is_product_category())) {
            return true;
        }
        return is_tax(['pa_bo-suu-tap', 'pa_xuat-xu']);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function build_current_collection_entities(): array {
        global $wp_query;

        $paged = max(1, (int) get_query_var('paged', 1), (int) get_query_var('page', 1));

        $raw_uri           = isset($_SERVER['REQUEST_URI'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_URI']))
            : '';
        $qs                = (string) wp_parse_url($raw_uri, PHP_URL_QUERY);
        $query_params      = [];
        if ($qs !== '') {
            parse_str($qs, $query_params);
        }
        $analysis          = UrlNormalizer::analyzeFilterParams($query_params);
        $has_filter_params = $analysis['has_any_filter'] || $analysis['has_utility_param'];

        $url               = home_url('/gach');
        $name              = 'Kho Gạch Ốp Lát Chính Hãng DRAVIT';
        $desc              = '';
        $is_sub_collection = false;

        if (drv_is_woo_active() && is_product_category()) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $url               = home_url('/' . $term->slug);
                $h1_meta           = trim((string) get_term_meta($term->term_id, 'drv_h1_override', true));
                $name              = $h1_meta !== '' ? $h1_meta : $term->name;
                $desc              = trim(wp_strip_all_tags($term->description));
                $is_sub_collection = ($term->slug !== 'gach');
            }
        } elseif (is_tax(['pa_bo-suu-tap', 'pa_xuat-xu'])) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $link              = get_term_link($term);
                $url               = is_string($link) ? $link : home_url('/gach');
                $h1_meta           = trim((string) get_term_meta($term->term_id, 'drv_h1_override', true));
                $name              = $h1_meta !== '' ? $h1_meta : $term->name;
                $desc              = trim(wp_strip_all_tags($term->description));
                $is_sub_collection = true;
            }
        }

        $products = [];
        if ($paged <= 1 && !$has_filter_params && $wp_query instanceof \WP_Query && is_array($wp_query->posts)) {
            foreach (array_slice($wp_query->posts, 0, 24) as $post_item) {
                $pid = $post_item instanceof \WP_Post ? (int) $post_item->ID : (int) $post_item;
                if ($pid <= 0) {
                    continue;
                }
                $products[] = [
                    'name' => get_the_title($pid),
                    'url'  => (string) get_permalink($pid),
                ];
            }
        }

        $faq_items = FaqResolver::resolveForCurrentView();

        return self::buildCollectionEntities(
            $url,
            $name,
            $desc,
            $products,
            $faq_items,
            $paged,
            $has_filter_params,
            $is_sub_collection
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function build_breadcrumb_node(): ?array {
        $trail = Breadcrumbs::getCurrentTrail();
        if (count($trail) < 2) {
            return null;
        }

        $elements = [];
        foreach ($trail as $idx => $crumb) {
            $elem = [
                '@type'    => 'ListItem',
                'position' => $idx + 1,
                'name'     => $crumb['label'],
            ];
            if ($crumb['url'] !== '') {
                $elem['item'] = $crumb['url'];
            }
            $elements[] = $elem;
        }

        return [
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private function normalize_urls_recursive(array $node): array {
        foreach ($node as $k => $v) {
            if (is_array($v)) {
                $node[$k] = $this->normalize_urls_recursive($v);
            } elseif (is_string($v) && in_array($k, ['url', '@id', 'item'], true) && str_starts_with($v, 'http')) {
                $node[$k] = UrlNormalizer::untrailingslashUrl($v);
            }
        }
        return $node;
    }

    private static function joinedTermNames(int $product_id, string $taxonomy): string {
        if ($product_id <= 0 || !function_exists('get_the_terms')) {
            return '';
        }
        $terms = get_the_terms($product_id, $taxonomy);
        if (!is_array($terms) || $terms === []) {
            return '';
        }
        $names = [];
        foreach ($terms as $t) {
            if ($t instanceof \WP_Term) {
                $names[] = $t->name;
            }
        }
        return implode(', ', $names);
    }

    private static function resolveOrigin(string $explicit): string {
        if ($explicit !== '') {
            return UrlNormalizer::untrailingslashUrl($explicit);
        }
        if (function_exists('home_url')) {
            return UrlNormalizer::untrailingslashUrl(home_url('/'));
        }
        return 'https://gachdravit.vn';
    }
}
