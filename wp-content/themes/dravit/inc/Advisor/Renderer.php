<?php
declare(strict_types=1);

namespace Dravit\Advisor;

use Dravit\Units\PriceState;

defined('ABSPATH') || exit;

/**
 * Render danh sách thẻ sản phẩm gợi ý của Trợ lý AI bằng `drv_ui('product-card', ['context' => 'advisor'])` (`D-17`),
 * đọc lại giá/tồn kho trực tiếp từ WooCommerce theo ID ở bước cuối (`docs/13` §2.1) và cấp dữ liệu cho Quick Spec Modal + BOQ.
 */
final class Renderer {
    /** @var array<string, string> */
    private const SURFACE_LABELS = [
        'bong-kieng' => 'Bóng kiếng',
        'mo-matt'    => 'Mờ (Matt)',
        'nham-san'   => 'Nhám sần',
        'sugar-lap'  => 'Bán bóng (Lappato)',
        'carving'    => 'Chạm khắc (Carving)',
    ];

    /** @var array<string, string> */
    private const MATERIAL_LABELS = [
        'porcelain'     => 'Xương Porcelain',
        'da-nung-ket'   => 'Đá nung kết',
        'granite'       => 'Xương Granite',
        'ceramic'       => 'Xương Ceramic',
        'mosaic'        => 'Gạch Mosaic',
    ];

    /**
     * Render HTML danh sách thẻ gợi ý cho panel kết quả bên phải.
     *
     * @param array<int, array<string, mixed>> $items
     * @param array<int, array{id: int, why: string}> $whyList
     */
    public function renderRecommendations(array $items, array $whyList = []): string {
        if ($items === []) {
            return '';
        }

        $whyMap = [];
        foreach ($whyList as $w) {
            if (isset($w['id'], $w['why']) && is_string($w['why'])) {
                $whyMap[(int) $w['id']] = trim($w['why']);
            }
        }

        $chunks = [];
        foreach ($items as $index => $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $liveProduct = function_exists('wc_get_product') ? wc_get_product($id) : null;
            if ($liveProduct instanceof \WC_Product) {
                $liveState                  = PriceState::resolve($liveProduct);
                $regM2                      = max(0, (int) $liveProduct->get_meta('drv_price_m2', true));
                $saleM2                     = max(0, (int) $liveProduct->get_meta('drv_sale_price_m2', true));
                $isSale                     = ($liveState === PriceState::SALE && $saleM2 > 0 && $saleM2 < $regM2);
                $effM2                      = ($liveState === PriceState::QUOTE_ONLY) ? null : ($isSale ? $saleM2 : $regM2);
                $item['price_state']        = $liveState;
                $item['price_m2']           = ($liveState === PriceState::QUOTE_ONLY) ? null : $regM2;
                $item['sale_price_m2']      = $isSale ? $saleM2 : null;
                $item['effective_price_m2'] = $effM2;
                $item['stock_status']       = (string) $liveProduct->get_stock_status();
                $item['stock_qty_pieces']   = $liveProduct->managing_stock() ? (int) $liveProduct->get_stock_quantity() : null;
                $item['stock_label']        = QueryEngine::formatStockLabel($item);

                if (isset($item['calc']) && is_array($item['calc']) && isset($item['calc']['input_area_m2'])) {
                    $item['calc'] = QueryEngine::calculateRequirement(
                        $item,
                        (float) $item['calc']['input_area_m2'],
                        (int) ($item['calc']['waste_pct'] ?? 5)
                    );
                }

                $cardSource = $liveProduct;
            } else {
                $cardSource = $this->toFallbackCardData($item);
            }

            $whyText     = $whyMap[$id] ?? (string) ($item['ai_blurb'] ?? ($item['short_description'] ?? ''));
            $advisorMeta = $this->buildAdvisorMeta($item, $liveProduct, $whyText);

            $cardHtml = drv_ui_html('product-card', [
                'product'       => $cardSource,
                'context'       => 'advisor',
                'advisor_meta'  => $advisorMeta,
                'priority_load' => $index < 2,
                'heading_tag'   => 'h3',
            ]);

            if ($cardHtml !== '') {
                $chunks[] = $cardHtml;
            }
        }

        return implode("\n", $chunks);
    }

    /**
     * Dựng metadata kỹ thuật cho thẻ gợi ý AI, hộp thoại Quick Spec và nút Thêm báo giá (`drv_quote_v2`).
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function buildAdvisorMeta(array $item, ?\WC_Product $liveProduct, string $whyText): array {
        $id         = (int) ($item['id'] ?? 0);
        $sku        = (string) ($item['sku'] ?? '');
        $title      = (string) ($item['title'] ?? '');
        $url        = (string) ($item['url'] ?? '/gach');
        $matchScore = max(50, min(99, (int) ($item['match_score'] ?? 90)));
        $dcofLevel  = (int) ($item['dcof_level'] ?? 0);
        $stockLabel = (string) ($item['stock_label'] ?? (($item['stock_status'] ?? 'instock') === 'outofstock' ? 'Đặt trước' : 'Sẵn kho'));
        $priceState = (string) ($item['price_state'] ?? PriceState::QUOTE_ONLY);
        $effPriceM2 = ($priceState !== PriceState::QUOTE_ONLY) ? (int) ($item['effective_price_m2'] ?? 0) : 0;
        $perBox     = max(1, (int) ($item['pieces_per_box'] ?? 4));
        $areaBox    = (float) ($item['area_box'] ?? 1.44);
        $kgPerBox   = (float) ($item['kg_per_box'] ?? 0.0);
        $thickMm    = (float) ($item['thickness_mm'] ?? 0.0);
        $waterFloat = isset($item['water_absorption']) && is_numeric($item['water_absorption'])
            ? (float) $item['water_absorption']
            : null;
        $shelf      = trim((string) ($item['showroom_shelf'] ?? ''));

        $attrs = isset($item['attrs']) && is_array($item['attrs']) ? $item['attrs'] : [];

        $sizeLabel    = $this->resolveSizeLabel($item, $liveProduct, $attrs);
        $surfaceLabel = $this->resolveTermOrMap($id, 'pa_be-mat', $attrs['pa_be-mat'][0] ?? '', self::SURFACE_LABELS);
        $matLabel     = $this->resolveTermOrMap($id, 'pa_chat-lieu', $attrs['pa_chat-lieu'][0] ?? '', self::MATERIAL_LABELS);
        $originLabel  = $this->resolveTermOrMap($id, 'pa_xuat-xu', $attrs['pa_xuat-xu'][0] ?? '', []);
        $waterLabel   = $waterFloat !== null
            ? 'Hút nước ≤ ' . str_replace('.', ',', rtrim(rtrim(number_format($waterFloat, 2, '.', ''), '0'), '.')) . '%'
            : ($matLabel !== '' ? $matLabel : '');

        $imgUrl = '';
        if ($liveProduct instanceof \WC_Product) {
            $thumbId = (int) $liveProduct->get_image_id();
            if ($thumbId > 0 && function_exists('wp_get_attachment_image_url')) {
                $imgUrl = (string) wp_get_attachment_image_url($thumbId, 'woocommerce_thumbnail');
            }
        }
        if ($imgUrl === '' && !empty($item['img_primary'])) {
            $imgUrl = (string) $item['img_primary'];
        }
        if ($imgUrl === '' && defined('DRV_URI')) {
            $imgUrl = DRV_URI . '/assets/images/placeholders/tile-marble-calacatta.svg';
        }

        $calc = isset($item['calc']) && is_array($item['calc']) ? $item['calc'] : null;

        $quotePayload = [
            'product_id'     => $id,
            'sku'            => $sku,
            'title'          => $title,
            'url'            => $url,
            'img'            => $imgUrl,
            'size_label'     => $sizeLabel,
            'price_state'    => $priceState,
            'price_m2'       => $effPriceM2 > 0 ? $effPriceM2 : null,
            'pieces_per_box' => $perBox,
            'm2_per_box'     => $areaBox > 0 ? $areaBox : 1.44,
            'kg_per_box'     => $kgPerBox,
            'area_m2'        => $calc !== null ? (float) ($calc['input_area_m2'] ?? $areaBox) : ($areaBox > 0 ? $areaBox : 1.44),
            'waste_pct'      => $calc !== null ? (int) ($calc['waste_pct'] ?? 5) : 5,
            'boxes'          => $calc !== null ? max(1, (int) ($calc['boxes'] ?? 1)) : 1,
            'pieces'         => $calc !== null ? max(1, (int) ($calc['pieces'] ?? $perBox)) : $perBox,
            'actual_m2'      => $calc !== null ? (float) ($calc['actual_m2'] ?? $areaBox) : ($areaBox > 0 ? $areaBox : 1.44),
            'weight_kg'      => $calc !== null ? (float) ($calc['weight_kg'] ?? $kgPerBox) : $kgPerBox,
            'est_total_vnd'  => (!$this->isQuote($priceState, $effPriceM2) && $calc !== null) ? ($calc['est_total_vnd'] ?? null) : null,
        ];

        $specPayload = [
            'product_id'     => $id,
            'sku'            => $sku,
            'title'          => $title,
            'url'            => $url,
            'img'            => $imgUrl,
            'price_state'    => $priceState,
            'price_m2'       => $effPriceM2 > 0 ? $effPriceM2 : null,
            'size_label'     => $sizeLabel !== '' ? $sizeLabel : 'Tiêu chuẩn',
            'material_label' => $matLabel !== '' ? $matLabel : 'Porcelain cao cấp',
            'surface_label'  => $surfaceLabel !== '' ? $surfaceLabel : 'Tiêu chuẩn',
            'dcof_label'     => $dcofLevel > 0 ? 'R' . $dcofLevel : 'Tiêu chuẩn nội thất',
            'water_label'    => $waterFloat !== null
                ? '≤ ' . str_replace('.', ',', rtrim(rtrim(number_format($waterFloat, 2, '.', ''), '0'), '.')) . '%'
                : '≤ 0,5% (Nhóm BIa)',
            'thickness_label'=> $thickMm > 0 ? rtrim(rtrim(number_format($thickMm, 1, ',', '.'), '0'), ',') . ' mm' : '9,5 mm',
            'pack_label'     => sprintf(
                '%d viên/thùng (%s m²%s)',
                $perBox,
                rtrim(rtrim(number_format($areaBox > 0 ? $areaBox : 1.44, 2, ',', '.'), '0'), ','),
                $kgPerBox > 0 ? ' · ' . rtrim(rtrim(number_format($kgPerBox, 1, ',', '.'), '0'), ',') . ' kg' : ''
            ),
            'origin_label'   => $originLabel !== '' ? $originLabel : ($shelf !== '' ? 'Kệ mẫu ' . $shelf : 'Kho trung tâm DRAVIT'),
            'stock_label'    => $stockLabel,
            'why'            => $whyText,
            'quote'          => $quotePayload,
        ];

        return [
            'match_score'   => $matchScore,
            'dcof_level'    => $dcofLevel,
            'stock_label'   => $stockLabel,
            'water_label'   => $waterLabel,
            'size_label'    => $sizeLabel,
            'surface_label' => $surfaceLabel,
            'why'           => $whyText,
            'calc'          => $calc,
            'quote_json'    => $this->encodeJson($quotePayload),
            'spec_json'     => $this->encodeJson($specPayload),
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $attrs
     */
    private function resolveSizeLabel(array $item, ?\WC_Product $product, array $attrs): string {
        $id = (int) ($item['id'] ?? 0);
        if ($product instanceof \WC_Product && $id > 0 && function_exists('get_the_terms')) {
            $terms = get_the_terms($id, 'pa_kich-thuoc');
            if (is_array($terms) && isset($terms[0]) && $terms[0] instanceof \WP_Term) {
                return $terms[0]->name;
            }
        }

        $wMm = (float) ($item['w_mm'] ?? 0.0);
        $hMm = (float) ($item['h_mm'] ?? 0.0);
        if ($wMm > 0.0 && $hMm > 0.0) {
            $wCm = (int) round($wMm / 10.0);
            $hCm = (int) round($hMm / 10.0);
            return $wCm . '×' . $hCm . ' cm';
        }

        $slug = (string) ($attrs['pa_kich-thuoc'][0] ?? '');
        if ($slug !== '') {
            return str_replace('x', '×', $slug) . ' cm';
        }

        return '';
    }

    /**
     * @param array<string, string> $fallbackMap
     */
    private function resolveTermOrMap(int $productId, string $taxonomy, string $slug, array $fallbackMap): string {
        if ($productId > 0 && function_exists('get_the_terms')) {
            $terms = get_the_terms($productId, $taxonomy);
            if (is_array($terms) && isset($terms[0]) && $terms[0] instanceof \WP_Term) {
                return $terms[0]->name;
            }
        }

        if ($slug !== '' && isset($fallbackMap[$slug])) {
            return $fallbackMap[$slug];
        }

        return '';
    }

    private function isQuote(string $priceState, int $effPriceM2): bool {
        return $priceState === PriceState::QUOTE_ONLY || $effPriceM2 <= 0;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encodeJson(array $data): string {
        $encoded = function_exists('wp_json_encode')
            ? wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '{}';
    }

    /**
     * Chuyển bản ghi snapshot sang cấu trúc mảng đầu vào của `template-parts/ui/product-card.php` khi chạy kiểm thử không có WC DB.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function toFallbackCardData(array $item): array {
        $priceState = (string) ($item['price_state'] ?? 'quote_only');
        $effM2      = ($priceState !== 'quote_only') ? (int) ($item['effective_price_m2'] ?? 0) : 0;
        $regM2      = ($priceState !== 'quote_only') ? (int) ($item['price_m2'] ?? 0) : 0;
        $areaPiece  = (float) ($item['area_piece'] ?? 0.36);

        return [
            'id'               => (int) ($item['id'] ?? 0),
            'sku'              => (string) ($item['sku'] ?? ''),
            'title'            => (string) ($item['title'] ?? ''),
            'url'              => (string) ($item['url'] ?? '/gach'),
            'price_state'      => $priceState,
            'price_m2'         => $effM2,
            'regular_price_m2' => $regM2,
            'price_piece'      => $effM2 > 0 ? (int) round($effM2 * $areaPiece) : 0,
            'pieces_per_box'   => max(1, (int) ($item['pieces_per_box'] ?? 4)),
            'm2_per_box'       => (float) ($item['area_box'] ?? 1.44),
            'showroom_shelf'   => (string) ($item['showroom_shelf'] ?? ''),
            'in_stock'         => ($item['stock_status'] ?? 'instock') !== 'outofstock',
            'img_primary'      => (string) ($item['img_primary'] ?? ''),
        ];
    }
}
