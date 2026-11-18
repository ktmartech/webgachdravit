<?php
declare(strict_types=1);

namespace Dravit\Catalog;

use Dravit\Core\Config;
use Dravit\Seo\InternalLinks;
use Dravit\Seo\UrlNormalizer;
use Dravit\Units\Calc;
use Dravit\Units\PriceState;

defined('ABSPATH') || exit;

/**
 * ViewModel trung tâm chuẩn bị toàn bộ dữ liệu SSR cho Trang Chi Tiết Sản Phẩm (`single-product.php` - Step 7):
 * - Gallery 6 vai trò ảnh (`drv_img_role`) + file tải Map CAD/4K (`drv_map_file_id`), đúng 1 ảnh LCP duy nhất (`P-6`).
 * - Bảng thông số 2 tầng (`8` dòng cốt lõi + mở rộng) tự động gắn link Silo tĩnh `IL-03` (`P-2`, 0 `?param`).
 * - Lưới mặt vân ngẫu nhiên (`drv_face_image_ids`) + 3 lưu ý kỹ thuật & vận chuyển động (`D-26`, `D-30`).
 * - Danh sách sản phẩm tương tự cô lập Silo Gạch (`IL-06`).
 */
final class ProductViewData {
    /** @var array<string, string> */
    private const IMG_ROLE_LABELS = [
        'room'      => 'Phối cảnh thực tế',
        'closeup'   => 'Cận cảnh bề mặt vân',
        'lighting'  => 'Dưới ánh đèn thực tế',
        'grout'     => 'Chi tiết mạch ron',
        'showroom'  => 'Mẫu thực tế tại kệ Showroom',
        'edge'      => 'Chi tiết cạnh & xương gạch',
        'dimension' => 'Quy cách kích thước',
    ];

    /**
     * Xây dựng đầy đủ gói dữ liệu hiển thị cho `content-single-product.php`.
     *
     * @return array<string, mixed>
     */
    public static function forProduct(\WC_Product $product): array {
        $id          = $product->get_id();
        $sku         = trim((string) $product->get_sku());
        $title       = trim($product->get_name());
        $permalink   = function_exists('get_permalink') && $id > 0
            ? UrlNormalizer::untrailingslashUrl((string) get_permalink($id))
            : UrlNormalizer::untrailingslashUrl(home_url('/san-pham/' . $product->get_slug()));
        $price_state = PriceState::resolve($product);
        $is_quote    = ($price_state === PriceState::QUOTE_ONLY);
        $is_out      = ($price_state === PriceState::OUT_OF_STOCK || !$product->is_in_stock());
        $unit_cfg    = PriceState::readUnitConfig($product);

        $reg_m2      = max(0, (int) $product->get_meta('drv_price_m2', true));
        $sale_m2     = max(0, (int) $product->get_meta('drv_sale_price_m2', true));
        $eff_m2      = $is_quote ? 0 : (($price_state === PriceState::SALE && $sale_m2 > 0 && $sale_m2 < $reg_m2) ? $sale_m2 : $reg_m2);
        $is_sale     = (!$is_quote && $sale_m2 > 0 && $sale_m2 < $reg_m2 && $price_state === PriceState::SALE);
        $discount    = ($is_sale && $reg_m2 > 0) ? (int) round((1 - ($eff_m2 / $reg_m2)) * 100) : 0;
        $price_piece = $is_quote ? 0 : max(0, (int) round((float) $product->get_price()));

        $w_mm        = max(0, (int) $product->get_meta('drv_piece_w_mm', true));
        $h_mm        = max(0, (int) $product->get_meta('drv_piece_h_mm', true));
        $thick_mm    = max(0.0, (float) $product->get_meta('drv_thickness_mm', true));
        $ppb         = $unit_cfg['pieces_per_box'];
        $unit_label  = $unit_cfg['unit_label'];
        $area_piece  = Calc::pieceAreaM2($w_mm, $h_mm);
        $m2_box      = (float) $product->get_meta('drv_m2_per_box', true);
        if ($m2_box <= 0.0 && $area_piece > 0.0) {
            $m2_box = round($area_piece * $ppb, 4);
        }
        $kg_box      = max(0.0, (float) $product->get_meta('drv_kg_per_box', true));
        if ($kg_box <= 0.0) {
            $kg_box = max(0.0, (float) $product->get_meta('drv_weight_kg_per_box', true));
        }

        $raw_waste   = trim((string) $product->get_meta('drv_waste_rate', true));
        $waste_def   = ($raw_waste !== '' && is_numeric($raw_waste))
            ? max(0, min(20, (int) round((float) $raw_waste)))
            : (int) drv_setting('order.waste_default', 5);
        if (!in_array($waste_def, [0, 5, 10], true)) {
            $waste_def = 5;
        }

        $default_m2  = 10.0;
        $def_unit    = $unit_cfg['default_buy_unit'];
        $round_mode  = $unit_cfg['round_mode'];
        $qty_min     = $unit_cfg['qty_min'];

        if ($area_piece > 0.0) {
            if ($def_unit === 'vien') {
                $init_calc = Calc::calcFromPieces([
                    'pieces'       => $qty_min,
                    'areaPiece'    => $area_piece,
                    'piecesPerBox' => $ppb,
                    'roundMode'    => $round_mode,
                    'qtyMin'       => $qty_min,
                    'priceM2'      => $eff_m2,
                    'kgPerBox'     => $kg_box,
                    'unitLabel'    => $unit_label,
                ]);
                $default_m2 = max(0.5, round($init_calc['actualM2'], 2));
            } elseif ($def_unit === 'thung') {
                $init_boxes = max(1, (int) ceil(($qty_min - Calc::EPSILON) / $ppb));
                $init_calc  = Calc::calcFromBoxes([
                    'boxes'        => $init_boxes,
                    'areaPiece'    => $area_piece,
                    'piecesPerBox' => $ppb,
                    'qtyMin'       => $qty_min,
                    'priceM2'      => $eff_m2,
                    'kgPerBox'     => $kg_box,
                    'unitLabel'    => $unit_label,
                ]);
                $default_m2 = max(0.5, round($init_calc['actualM2'], 2));
            } else {
                $init_calc = Calc::calcFromM2([
                    'areaM2'       => $default_m2,
                    'wastePct'     => (float) $waste_def,
                    'areaPiece'    => $area_piece,
                    'piecesPerBox' => $ppb,
                    'roundMode'    => $round_mode,
                    'qtyMin'       => $qty_min,
                    'priceM2'      => $eff_m2,
                    'kgPerBox'     => $kg_box,
                    'unitLabel'    => $unit_label,
                ]);
            }

            if (!$is_quote && $price_piece > 0) {
                $init_calc['pricePiece'] = $price_piece;
                $init_calc['totalVnd']   = $init_calc['pieces'] * $price_piece;
            }
        } else {
            $init_calc = [
                'pieces'      => $qty_min,
                'fullBoxes'   => max(1, (int) floor($qty_min / $ppb)),
                'loosePieces' => $qty_min % $ppb,
                'boxText'     => max(1, (int) ceil($qty_min / $ppb)) . ' thùng',
                'needM2'      => 0.0,
                'actualM2'    => 0.0,
                'totalKg'     => 0.0,
                'pricePiece'  => $price_piece,
                'totalVnd'    => $price_piece * $qty_min,
                'isQuote'     => $is_quote,
            ];
        }

        $stock_boxes = null;
        if ($product->managing_stock() && $product->get_stock_quantity() !== null) {
            $stock_qty   = max(0, (int) $product->get_stock_quantity());
            $stock_boxes = (int) floor($stock_qty / $ppb);
        }

        $shelf       = trim((string) $product->get_meta('drv_showroom_shelf', true));
        $map_file_id = max(0, (int) $product->get_meta('drv_map_file_id', true));
        $map_url     = ($map_file_id > 0 && function_exists('wp_get_attachment_url'))
            ? (string) (wp_get_attachment_url($map_file_id) ?: '')
            : '';

        $gallery     = self::buildGalleryItems($product, $shelf);
        $faces       = self::buildFaceItems($product, $gallery);
        $spec_input  = self::extractSpecInputFromProduct($product, $w_mm, $h_mm, $thick_mm, $ppb, $m2_box, $kg_box, $unit_label);
        $specs       = self::buildSpecRows($spec_input);
        $notes       = self::buildResponsibilityNotes($spec_input);
        $swatches    = SeriesSwatchResolver::resolveForProduct($product);
        $primary_cat = self::resolvePrimaryCategoryInfo($product);
        $similar_ids = self::resolveSimilarProductIds($product, $primary_cat['slug'], 8);

        return [
            'id'           => $id,
            'sku'          => $sku,
            'title'        => $title,
            'permalink'    => $permalink,
            'price_state'  => $price_state,
            'is_quote'     => $is_quote,
            'is_sale'      => $is_sale,
            'is_out'       => $is_out,
            'price_m2'     => $eff_m2,
            'regular_m2'   => $reg_m2,
            'discount_pct' => $discount,
            'price_piece'  => $price_piece,
            'w_mm'         => $w_mm,
            'h_mm'         => $h_mm,
            'thick_mm'     => $thick_mm,
            'ppb'          => $ppb,
            'm2_box'       => $m2_box,
            'kg_box'       => $kg_box,
            'unit_label'   => $unit_label,
            'unit_cfg'     => $unit_cfg,
            'waste_def'    => $waste_def,
            'default_m2'   => $default_m2,
            'init_calc'    => $init_calc,
            'stock_boxes'  => $stock_boxes,
            'shelf'        => $shelf,
            'map_url'      => $map_url,
            'gallery'      => $gallery,
            'faces'        => $faces,
            'faces_count'  => max(count($faces), max(0, (int) $product->get_meta('drv_faces', true))),
            'specs_core'   => $specs['core'],
            'specs_ext'    => $specs['extended'],
            'notes'        => $notes,
            'swatches'     => $swatches,
            'primary_cat'  => $primary_cat,
            'similar_ids'  => $similar_ids,
            'sla_text'     => (string) drv_setting('order.sla_text', ''),
        ];
    }

    /**
     * Hàm thuần (Pure Function) xây dựng bảng thông số 2 tầng (`core` 8 dòng + `extended`) kèm liên kết Silo tĩnh `IL-03`.
     * Mọi URL sinh ra cam kết không chứa `?param` và không có dấu `/` cuối.
     *
     * @param array<string, mixed>      $input
     * @param array<string, mixed>|null $facets_config
     * @return array{
     *     core: array<int, array{label: string, value_html: string}>,
     *     extended: array<int, array{label: string, value_html: string}>
     * }
     */
    public static function buildSpecRows(array $input, ?array $facets_config = null): array {
        $core     = [];
        $extended = [];

        $render_terms = static function (string $taxonomy, array $terms) use ($facets_config): string {
            $parts = [];
            foreach ($terms as $t) {
                if (!is_array($t)) {
                    continue;
                }
                $name = trim((string) ($t['name'] ?? ''));
                $slug = trim((string) ($t['slug'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $url = $slug !== '' ? InternalLinks::resolveTermUrl($taxonomy, $slug, $facets_config) : null;
                if ($url !== null) {
                    $parts[] = sprintf('<a href="%s" class="drv-link">%s</a>', esc_url($url), esc_html($name));
                } else {
                    $parts[] = esc_html($name);
                }
            }
            return implode(', ', $parts);
        };

        // 1. Công năng (`pa_cong-nang` -> IL-03)
        $usage_html = $render_terms('pa_cong-nang', is_array($input['cong_nang'] ?? null) ? $input['cong_nang'] : []);
        if ($usage_html !== '') {
            $core[] = ['label' => 'Công năng ốp lát', 'value_html' => $usage_html];
        }

        // 2. Không gian phù hợp (`pa_khong-gian` -> IL-03)
        $space_html = $render_terms('pa_khong-gian', is_array($input['khong_gian'] ?? null) ? $input['khong_gian'] : []);
        if ($space_html !== '') {
            $core[] = ['label' => 'Không gian phù hợp', 'value_html' => $space_html];
        }

        // 3. Kích thước & Độ dày (`pa_kich-thuoc` -> IL-03)
        $w_mm      = max(0, (int) ($input['w_mm'] ?? 0));
        $h_mm      = max(0, (int) ($input['h_mm'] ?? 0));
        $thick_mm  = max(0.0, (float) ($input['thick_mm'] ?? 0.0));
        $size_html = $render_terms('pa_kich-thuoc', is_array($input['kich_thuoc'] ?? null) ? $input['kich_thuoc'] : []);
        $dim_parts = [];
        if ($size_html !== '') {
            // Tên term `pa_kich-thuoc` có thể đã mang đơn vị ("80x80 cm") → không ghép thêm " cm".
            $dim_parts[] = preg_match('/cm\s*$/iu', strip_tags($size_html)) === 1 ? $size_html : $size_html . ' cm';
        }
        if ($w_mm > 0 && $h_mm > 0) {
            $mm_str = sprintf('%d × %d mm', $w_mm, $h_mm);
            if ($thick_mm > 0.0) {
                $mm_str .= ' · Dày ' . drv_format_decimal($thick_mm, 1) . ' mm';
            }
            $dim_parts[] = esc_html($mm_str);
        }
        if ($dim_parts !== []) {
            $core[] = ['label' => 'Kích thước & Độ dày', 'value_html' => implode(' · ', $dim_parts)];
        }

        // 4. Chất liệu xương gạch (`pa_chat-lieu` -> IL-03)
        $mat_html = $render_terms('pa_chat-lieu', is_array($input['chat_lieu'] ?? null) ? $input['chat_lieu'] : []);
        if ($mat_html !== '') {
            $core[] = ['label' => 'Chất liệu xương gạch', 'value_html' => $mat_html];
        }

        // 5. Hệ vân & Mặt vân (`pa_van-gach` -> IL-03)
        $vein_html = $render_terms('pa_van-gach', is_array($input['van_gach'] ?? null) ? $input['van_gach'] : []);
        $faces_cnt = max(0, (int) ($input['faces'] ?? 0));
        if ($vein_html !== '' || $faces_cnt > 0) {
            $v_parts = [];
            if ($vein_html !== '') {
                $v_parts[] = $vein_html;
            }
            if ($faces_cnt > 0) {
                $v_parts[] = esc_html($faces_cnt . ' mặt vân ngẫu nhiên');
            }
            $core[] = ['label' => 'Hệ vân & Mặt vân', 'value_html' => implode(' · ', $v_parts)];
        }

        // 6. Bề mặt & Chống trượt (`pa_be-mat` + `pa_dcof`)
        $surf_html = $render_terms('pa_be-mat', is_array($input['be_mat'] ?? null) ? $input['be_mat'] : []);
        $dcof_html = $render_terms('pa_dcof', is_array($input['dcof'] ?? null) ? $input['dcof'] : []);
        if ($surf_html !== '' || $dcof_html !== '') {
            $s_parts = array_values(array_filter([$surf_html, $dcof_html !== '' ? ('Chống trượt ' . $dcof_html) : '']));
            $core[]  = ['label' => 'Bề mặt & Ma sát', 'value_html' => implode(' · ', $s_parts)];
        }

        // 7. Bộ sưu tập & Xuất xứ (`pa_bo-suu-tap`, `pa_xuat-xu` -> IL-03)
        $bst_html    = $render_terms('pa_bo-suu-tap', is_array($input['bo_suu_tap'] ?? null) ? $input['bo_suu_tap'] : []);
        $origin_html = $render_terms('pa_xuat-xu', is_array($input['xuat_xu'] ?? null) ? $input['xuat_xu'] : []);
        if ($bst_html !== '' || $origin_html !== '') {
            $bo_parts = array_values(array_filter([$bst_html, $origin_html]));
            $core[]   = ['label' => 'Bộ sưu tập & Xuất xứ', 'value_html' => implode(' · ', $bo_parts)];
        }

        // 8. Quy cách thùng & Biến thiên màu thiết kế (`drv_shade`, tách bạch với `_drv_lot_code` theo D-30)
        $ppb        = max(1, (int) ($input['ppb'] ?? 1));
        $m2_box     = max(0.0, (float) ($input['m2_box'] ?? 0.0));
        $kg_box     = max(0.0, (float) ($input['kg_box'] ?? 0.0));
        $unit_label = trim((string) ($input['unit_label'] ?? 'viên')) ?: 'viên';
        $shade      = strtoupper(trim((string) ($input['shade'] ?? '')));

        $pack_parts = [sprintf('%d %s/thùng', $ppb, $unit_label)];
        if ($m2_box > 0.0) {
            $pack_parts[] = drv_format_decimal($m2_box, 2) . ' m²';
        }
        if ($kg_box > 0.0) {
            $pack_parts[] = drv_format_decimal($kg_box, 1) . ' kg';
        }
        if (in_array($shade, ['V1', 'V2', 'V3', 'V4'], true)) {
            $pack_parts[] = 'Biến thiên vân ' . $shade;
        }
        $core[] = ['label' => 'Quy cách đóng gói', 'value_html' => esc_html(implode(' · ', $pack_parts))];

        // --- Tầng 2: Thông số kỹ thuật chuyên sâu (`extended`) ---
        $water_abs = trim((string) ($input['water_absorption'] ?? ''));
        $water_tax = $render_terms('pa_do-hut-nuoc', is_array($input['do_hut_nuoc'] ?? null) ? $input['do_hut_nuoc'] : []);
        if ($water_abs !== '' && is_numeric(str_replace(',', '.', $water_abs))) {
            $extended[] = [
                'label'      => 'Độ hút nước (E)',
                'value_html' => esc_html('E ≤ ' . drv_format_decimal((float) str_replace(',', '.', $water_abs), 2) . '% (ISO 13006)'),
            ];
        } elseif ($water_tax !== '') {
            $extended[] = ['label' => 'Độ hút nước (E)', 'value_html' => $water_tax];
        }

        $bending  = max(0.0, (float) ($input['bending_strength'] ?? 0.0));
        $breaking = max(0, (int) ($input['breaking_strength'] ?? 0));
        if ($bending > 0.0 || $breaking > 0) {
            $mech = [];
            if ($bending > 0.0) {
                $mech[] = 'Chịu uốn ≥ ' . drv_format_decimal($bending, 1) . ' N/mm²';
            }
            if ($breaking > 0) {
                $mech[] = 'Lực phá vỡ ≥ ' . number_format($breaking, 0, ',', '.') . ' N';
            }
            $extended[] = ['label' => 'Cường độ cơ lý', 'value_html' => esc_html(implode(' · ', $mech))];
        }

        $pei   = strtoupper(trim((string) ($input['pei'] ?? '')));
        $mohs  = max(0, (int) ($input['mohs'] ?? 0));
        $stain = max(0, (int) ($input['stain_class'] ?? 0));
        if ($pei !== '' || $mohs > 0 || $stain > 0) {
            $wear = [];
            if ($pei !== '') {
                $wear[] = 'PEI ' . $pei;
            }
            if ($mohs > 0) {
                $wear[] = 'Độ cứng Mohs ' . $mohs;
            }
            if ($stain > 0) {
                $wear[] = 'Chống bám bẩn Class ' . $stain;
            }
            $extended[] = ['label' => 'Độ bền mài mòn & Bề mặt', 'value_html' => esc_html(implode(' · ', $wear))];
        }

        $rectified = (($input['rectified'] ?? 'yes') !== 'no');
        $grout_min = max(0.5, (float) ($input['grout_min_mm'] ?? 1.5));
        $grout_max = max($grout_min, (float) ($input['grout_max_mm'] ?? 2.0));
        $edge_txt  = ($rectified ? 'Mài cạnh phẳng (Rectified)' : 'Cạnh tiêu chuẩn')
            . sprintf(' · Mạch ron khuyến nghị %s - %s mm', drv_format_decimal($grout_min, 1), drv_format_decimal($grout_max, 1));
        $extended[] = ['label' => 'Xử lý cạnh & Đường ron', 'value_html' => esc_html($edge_txt)];

        $color_name = trim((string) ($input['color_name'] ?? ''));
        $color_html = $render_terms('pa_mau-sac', is_array($input['mau_sac'] ?? null) ? $input['mau_sac'] : []);
        if ($color_name !== '' || $color_html !== '') {
            $extended[] = [
                'label'      => 'Tông màu thương mại',
                'value_html' => $color_name !== '' ? esc_html($color_name) : $color_html,
            ];
        }

        $lighting = trim((string) ($input['lighting_note'] ?? ''));
        if ($lighting !== '') {
            $extended[] = ['label' => 'Ánh sáng đèn phù hợp', 'value_html' => esc_html($lighting)];
        }

        $warranty = trim((string) ($input['warranty'] ?? ''));
        if ($warranty !== '') {
            $extended[] = ['label' => 'Bảo hành chất lượng', 'value_html' => esc_html($warranty)];
        }

        return [
            'core'     => $core,
            'extended' => $extended,
        ];
    }

    /**
     * @param array<string, mixed> $spec_input
     * @return array<int, array{title: string, body: string, variant: string}>
     */
    private static function buildResponsibilityNotes(array $spec_input): array {
        $shade     = strtoupper(trim((string) ($spec_input['shade'] ?? '')));
        $shade_lbl = in_array($shade, ['V1', 'V2', 'V3', 'V4'], true) ? ('Shade ' . $shade) : 'Đồng bộ lô sản xuất';
        $rectified = (($spec_input['rectified'] ?? 'yes') !== 'no');
        $grout_min = max(0.5, (float) ($spec_input['grout_min_mm'] ?? 1.5));
        $grout_max = max($grout_min, (float) ($spec_input['grout_max_mm'] ?? 2.0));
        $grout_txt = drv_format_decimal($grout_min, 1) . ' - ' . drv_format_decimal($grout_max, 1) . ' mm';

        return [
            [
                'title'   => '1. Kiểm soát đồng lô màu (' . $shade_lbl . '):',
                'body'    => 'Mỗi đợt nung lò có sắc độ và cỡ thực tế (Caliber) riêng. Khuyến nghị tính dư 5% hao hụt ngay trong 1 lần đặt để kho DRAVIT xuất nguyên thùng 100% cùng mã lô sản xuất.',
                'variant' => 'warn',
            ],
            [
                'title'   => '2. Khuyến nghị thi công mạch ron ' . $grout_txt . ':',
                'body'    => $rectified
                    ? 'Viên gạch đã mài cạnh phẳng (Rectified), nên đi ke cân bằng ' . $grout_txt . ' và thi công bằng keo dán gạch chuyên dụng để mặt sàn phẳng đều, hạn chế bộp rỗng.'
                    : 'Nên giữ khe co giãn mạch ron ' . $grout_txt . ' và kiểm tra chiều mũi tên dưới đáy viên gạch trước khi lát đại trà.',
                'variant' => 'default',
            ],
            [
                'title'   => '3. Kiểm hàng & Báo phí vận chuyển minh bạch:',
                'body'    => 'Phí xe tải và bốc xếp tính theo tổng khối lượng thực tế và vị trí công trình, thông báo rõ trước khi giao. Đổi bù miễn phí nếu phát sinh nứt vỡ trong quá trình vận chuyển.',
                'variant' => 'default',
            ],
        ];
    }

    /**
     * Kích thước thật của SVG placeholder trong theme (đọc `viewBox`), quy về cạnh dài 1600px.
     * PhotoSwipe dùng width/height khai báo để fit ảnh vào khung; khai báo sai tỉ lệ → ảnh bị thu nhỏ, viền thừa.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function localSvgSize(string $url): ?array {
        $base = defined('DRV_URI') ? DRV_URI . '/' : '';
        if ($base === '' || !str_ends_with(strtolower($url), '.svg') || !str_starts_with($url, $base)) {
            return null;
        }

        $path = DRV_DIR . '/' . ltrim(substr($url, strlen($base)), '/');
        $head = is_readable($path) ? (string) file_get_contents($path, false, null, 0, 600) : '';
        if (preg_match('/viewBox="\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)\s*"/', $head, $m) !== 1) {
            return null;
        }

        $w = (float) $m[1];
        $h = (float) $m[2];
        if ($w <= 0.0 || $h <= 0.0) {
            return null;
        }

        $scale = 1600 / max($w, $h);
        return [(int) round($w * $scale), (int) round($h * $scale)];
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     src_large: string,
     *     src_full: string,
     *     width: int,
     *     height: int,
     *     alt: string,
     *     role_label: string,
     *     caption: string,
     *     is_lcp: bool,
     *     is_wide: bool
     * }>
     */
    private static function buildGalleryItems(\WC_Product $product, string $shelf): array {
        $ids = array_values(array_unique(array_filter(array_merge(
            [(int) $product->get_image_id()],
            array_map('intval', $product->get_gallery_image_ids())
        ))));

        $title = trim($product->get_name());
        $sku   = trim((string) $product->get_sku());
        $items = [];

        $default_roles = ['room', 'closeup', 'lighting', 'grout', 'showroom', 'edge'];

        if ($ids !== [] && function_exists('wp_get_attachment_image_src')) {
            foreach (array_slice($ids, 0, 8) as $idx => $aid) {
                $full  = wp_get_attachment_image_src($aid, 'full');
                $large = wp_get_attachment_image_src($aid, 'large');
                if (!is_array($full) || empty($full[0])) {
                    continue;
                }

                $role_key   = function_exists('get_post_meta')
                    ? strtolower(trim((string) get_post_meta($aid, 'drv_img_role', true)))
                    : '';
                $role_label = self::IMG_ROLE_LABELS[$role_key] ?? '';
                if ($role_label === '' && isset($default_roles[$idx])) {
                    $fallback_key = $default_roles[$idx];
                    if ($fallback_key === 'showroom' && $shelf !== '') {
                        $role_label = 'Mẫu thực tế tại ' . drv_shelf_label($shelf);
                    } else {
                        $role_label = self::IMG_ROLE_LABELS[$fallback_key];
                    }
                }

                $alt = function_exists('get_post_meta')
                    ? trim((string) get_post_meta($aid, '_wp_attachment_image_alt', true))
                    : '';
                if ($alt === '') {
                    $alt = $title . ($role_label !== '' ? ' - ' . $role_label : '');
                }

                $caption = function_exists('wp_get_attachment_caption')
                    ? trim((string) wp_get_attachment_caption($aid))
                    : '';
                if ($caption === '') {
                    $caption = $alt . ($sku !== '' ? ' (' . $sku . ')' : '');
                }

                $items[] = [
                    'id'         => $aid,
                    'src_large'  => is_array($large) && !empty($large[0]) ? (string) $large[0] : (string) $full[0],
                    'src_full'   => (string) $full[0],
                    'width'      => max(400, (int) ($full[1] ?? 1200)),
                    'height'     => max(400, (int) ($full[2] ?? 1200)),
                    'alt'        => $alt,
                    'role_label' => $role_label,
                    'caption'    => $caption,
                    'is_lcp'     => ($idx === 0),
                    'is_wide'    => ($idx >= 4),
                ];
            }
        }

        if ($items === []) {
            $fallback    = PriceState::resolveFallbackImages($product->get_id());
            $ph_base     = DRV_URI . '/assets/images/placeholders/';
            $w_mm        = max(0, (int) $product->get_meta('drv_piece_w_mm', true));
            $h_mm        = max(0, (int) $product->get_meta('drv_piece_h_mm', true));
            $thick_mm    = max(0.0, (float) $product->get_meta('drv_thickness_mm', true));
            $grout_mm    = (float) ($product->get_meta('drv_grout_min_mm', true) ?: ($product->get_meta('drv_grout_mm', true) ?: 1.5));
            $size_short  = ($w_mm > 0 && $h_mm > 0) ? AttributeDeriver::deriveSizeSlug($w_mm, $h_mm) : '';
            $room_tag    = 'Phối cảnh thực tế' . ($size_short !== '' ? ' ' . $size_short : '');
            $shelf_tag   = 'Mẫu thực tế tại ' . ($shelf !== '' ? drv_shelf_label($shelf) : 'Showroom');
            $edge_tag    = $thick_mm > 0.0
                ? ('Cạnh xương gạch dày ' . drv_format_decimal($thick_mm, 1) . 'mm')
                : 'Chi tiết cạnh & xương gạch';
            $grout_tag   = 'Mạch ghép ron ' . drv_format_decimal($grout_mm, 1) . 'mm';
            $sku_suffix  = $sku !== '' ? ' (' . $sku . ')' : '';

            $presets = [
                [
                    'src'        => $fallback['hover'],
                    'w'          => 1200,
                    'h'          => 1200,
                    'role_label' => $room_tag,
                    'is_lcp'     => true,
                    'is_wide'    => false,
                ],
                [
                    'src'        => $fallback['primary'],
                    'w'          => 1200,
                    'h'          => 1200,
                    'role_label' => 'Cận cảnh bề mặt vân',
                    'is_lcp'     => false,
                    'is_wide'    => false,
                ],
                [
                    'src'        => $ph_base . 'showroom-surface-board.svg',
                    'w'          => 1200,
                    'h'          => 1200,
                    'role_label' => 'Góc nghiêng ánh sáng 4000K',
                    'is_lcp'     => false,
                    'is_wide'    => false,
                ],
                [
                    'src'        => $ph_base . 'detail-grout-line.svg',
                    'w'          => 1200,
                    'h'          => 1200,
                    'role_label' => $grout_tag,
                    'is_lcp'     => false,
                    'is_wide'    => false,
                ],
                [
                    'src'        => $ph_base . 'room-showroom-shelf.svg',
                    'w'          => 1280,
                    'h'          => 720,
                    'role_label' => $shelf_tag,
                    'is_lcp'     => false,
                    'is_wide'    => true,
                ],
                [
                    'src'        => $ph_base . 'detail-edge-10mm.svg',
                    'w'          => 1280,
                    'h'          => 720,
                    'role_label' => $edge_tag,
                    'is_lcp'     => false,
                    'is_wide'    => true,
                ],
            ];

            foreach ($presets as $p_idx => $p) {
                $p_alt   = $title . ' - ' . $p['role_label'];
                $p_size  = self::localSvgSize((string) $p['src']) ?? [$p['w'], $p['h']];
                $items[] = [
                    'id'         => 0,
                    'src_large'  => $p['src'],
                    'src_full'   => $p['src'],
                    'width'      => $p_size[0],
                    'height'     => $p_size[1],
                    'alt'        => $p_alt,
                    'role_label' => $p['role_label'],
                    'caption'    => sprintf('Ảnh %d/6: %s - %s%s', $p_idx + 1, $p['role_label'], $title, $sku_suffix),
                    'is_lcp'     => $p['is_lcp'],
                    'is_wide'    => $p['is_wide'],
                ];
            }
        }

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $gallery
     * @return array<int, array{src_thumb: string, src_full: string, width: int, height: int, alt: string, caption: string, variant_idx: int}>
     */
    private static function buildFaceItems(\WC_Product $product, array $gallery): array {
        $raw_faces = $product->get_meta('drv_face_image_ids', true);
        $face_ids  = [];

        if (is_array($raw_faces)) {
            $face_ids = array_values(array_filter(array_map('absint', $raw_faces)));
        } elseif (is_string($raw_faces) && trim($raw_faces) !== '') {
            $decoded = json_decode($raw_faces, true);
            if (is_array($decoded)) {
                $face_ids = array_values(array_filter(array_map('absint', $decoded)));
            }
        }

        $title = trim($product->get_name());
        $out   = [];

        if ($face_ids !== [] && function_exists('wp_get_attachment_image_src')) {
            foreach (array_slice($face_ids, 0, 12) as $i => $fid) {
                $full  = wp_get_attachment_image_src($fid, 'full');
                $thumb = wp_get_attachment_image_src($fid, 'thumbnail');
                if (!is_array($full) || empty($full[0])) {
                    continue;
                }
                $label = sprintf('Mặt vân %d - %s', $i + 1, $title);
                $out[] = [
                    'src_thumb'   => is_array($thumb) && !empty($thumb[0]) ? (string) $thumb[0] : (string) $full[0],
                    'src_full'    => (string) $full[0],
                    'width'       => max(400, (int) ($full[1] ?? 1000)),
                    'height'      => max(400, (int) ($full[2] ?? 1000)),
                    'alt'         => $label,
                    'caption'     => $label,
                    'variant_idx' => 0,
                ];
            }
        }

        if ($out !== []) {
            return $out;
        }

        $declared_faces = max(0, (int) $product->get_meta('drv_faces', true));
        if ($declared_faces >= 2 && isset($gallery[0])) {
            $base  = $gallery[1] ?? $gallery[0];
            $limit = min(6, $declared_faces);
            for ($i = 1; $i <= $limit; $i++) {
                $label = sprintf('Mặt vân %d/%d - %s', $i, $declared_faces, $title);
                $out[] = [
                    'src_thumb'   => (string) $base['src_large'],
                    'src_full'    => (string) $base['src_full'],
                    'width'       => (int) $base['width'],
                    'height'      => (int) $base['height'],
                    'alt'         => $label,
                    'caption'     => $label,
                    'variant_idx' => $i,
                ];
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function extractSpecInputFromProduct(
        \WC_Product $product,
        int $w_mm,
        int $h_mm,
        float $thick_mm,
        int $ppb,
        float $m2_box,
        float $kg_box,
        string $unit_label
    ): array {
        $id = $product->get_id();

        return [
            'cong_nang'         => self::termPairs($id, 'pa_cong-nang'),
            'khong_gian'        => self::termPairs($id, 'pa_khong-gian'),
            'kich_thuoc'        => self::termPairs($id, 'pa_kich-thuoc'),
            'chat_lieu'         => self::termPairs($id, 'pa_chat-lieu'),
            'van_gach'          => self::termPairs($id, 'pa_van-gach'),
            'be_mat'            => self::termPairs($id, 'pa_be-mat'),
            'dcof'              => self::termPairs($id, 'pa_dcof'),
            'bo_suu_tap'        => self::termPairs($id, 'pa_bo-suu-tap'),
            'xuat_xu'           => self::termPairs($id, 'pa_xuat-xu'),
            'mau_sac'           => self::termPairs($id, 'pa_mau-sac'),
            'do_hut_nuoc'       => self::termPairs($id, 'pa_do-hut-nuoc'),
            'w_mm'              => $w_mm,
            'h_mm'              => $h_mm,
            'thick_mm'          => $thick_mm,
            'ppb'               => $ppb,
            'm2_box'            => $m2_box,
            'kg_box'            => $kg_box,
            'unit_label'        => $unit_label,
            'faces'             => max(0, (int) $product->get_meta('drv_faces', true)),
            'shade'             => trim((string) $product->get_meta('drv_shade', true)),
            'water_absorption'  => trim((string) $product->get_meta('drv_water_absorption', true)),
            'pei'               => trim((string) ($product->get_meta('drv_pei', true) ?: $product->get_meta('drv_pei_rating', true))),
            'mohs'              => max(0, (int) $product->get_meta('drv_mohs', true)),
            'breaking_strength' => max(0, (int) $product->get_meta('drv_breaking_strength', true)),
            'bending_strength'  => max(0.0, (float) $product->get_meta('drv_bending_strength', true)),
            'stain_class'       => max(0, (int) $product->get_meta('drv_stain_class', true)),
            'rectified'         => trim((string) ($product->get_meta('drv_rectified', true) ?: 'yes')),
            'grout_min_mm'      => (float) ($product->get_meta('drv_grout_min_mm', true) ?: 1.5),
            'grout_max_mm'      => (float) ($product->get_meta('drv_grout_max_mm', true) ?: 2.0),
            'color_name'        => trim((string) $product->get_meta('drv_color_name', true)),
            'lighting_note'     => trim((string) $product->get_meta('drv_lighting_note', true)),
            'warranty'          => trim((string) $product->get_meta('drv_warranty', true)),
        ];
    }

    /**
     * @return array<int, array{slug: string, name: string}>
     */
    private static function termPairs(int $product_id, string $taxonomy): array {
        if ($product_id <= 0 || !function_exists('get_the_terms') || !function_exists('taxonomy_exists') || !taxonomy_exists($taxonomy)) {
            return [];
        }
        $terms = get_the_terms($product_id, $taxonomy);
        if (!is_array($terms)) {
            return [];
        }
        $out = [];
        foreach ($terms as $t) {
            if ($t instanceof \WP_Term && $t->slug !== '' && $t->name !== '') {
                $out[] = ['slug' => $t->slug, 'name' => $t->name];
            }
        }
        return $out;
    }

    /**
     * @return array{slug: string, name: string, url: string}
     */
    private static function resolvePrimaryCategoryInfo(\WC_Product $product): array {
        $id          = $product->get_id();
        $collections = Config::get('collections');
        $primary_tid = $id > 0 && function_exists('get_post_meta')
            ? (int) get_post_meta($id, 'rank_math_primary_product_cat', true)
            : 0;

        if ($primary_tid > 0 && function_exists('get_term')) {
            $term = get_term($primary_tid, 'product_cat');
            if ($term instanceof \WP_Term && $term->slug !== '' && $term->slug !== 'gach') {
                return [
                    'slug' => $term->slug,
                    'name' => $term->name,
                    'url'  => UrlNormalizer::untrailingslashUrl(home_url('/' . $term->slug)),
                ];
            }
        }

        $assigned = [];
        if ($id > 0 && function_exists('get_the_terms')) {
            $terms = get_the_terms($id, 'product_cat');
            if (is_array($terms)) {
                foreach ($terms as $t) {
                    if ($t instanceof \WP_Term) {
                        $assigned[] = $t->slug;
                    }
                }
            }
        }

        $best_slug = PrimaryCategory::resolvePrimarySlug($assigned, $collections);
        if ($best_slug !== null && $best_slug !== '' && isset($collections[$best_slug])) {
            return [
                'slug' => $best_slug,
                'name' => (string) ($collections[$best_slug]['name'] ?? $best_slug),
                'url'  => UrlNormalizer::untrailingslashUrl(home_url('/' . $best_slug)),
            ];
        }

        return [
            'slug' => 'gach',
            'name' => 'Gạch ốp lát',
            'url'  => UrlNormalizer::untrailingslashUrl(home_url('/gach')),
        ];
    }

    /**
     * Truy vấn tối đa `$limit` sản phẩm tương tự trong cùng Silo Gạch (`IL-06` — cấm lẫn vật tư/ngành hàng khác).
     *
     * @return array<int, int>
     */
    private static function resolveSimilarProductIds(\WC_Product $product, string $primary_cat_slug, int $limit = 8): array {
        if (!function_exists('get_posts')) {
            return [];
        }

        $exclude_id = $product->get_id();
        $cat_slug   = $primary_cat_slug !== '' ? $primary_cat_slug : 'gach';

        $ids = get_posts([
            'post_type'              => 'product',
            'post_status'            => 'publish',
            'posts_per_page'         => $limit,
            'post__not_in'           => [$exclude_id],
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'tax_query'              => [
                'relation' => 'AND',
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => [$cat_slug],
                ],
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => ['gach'],
                ],
            ],
            'orderby'                => [
                'menu_order' => 'ASC',
                'date'       => 'DESC',
            ],
        ]);

        $collected = is_array($ids) ? array_values(array_map('intval', $ids)) : [];

        if (count($collected) < $limit && $cat_slug !== 'gach') {
            $more = get_posts([
                'post_type'              => 'product',
                'post_status'            => 'publish',
                'posts_per_page'         => $limit - count($collected),
                'post__not_in'           => array_merge([$exclude_id], $collected),
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'tax_query'              => [
                    [
                        'taxonomy' => 'product_cat',
                        'field'    => 'slug',
                        'terms'    => ['gach'],
                    ],
                ],
                'orderby'                => [
                    'menu_order' => 'ASC',
                    'date'       => 'DESC',
                ],
            ]);
            if (is_array($more)) {
                $collected = array_merge($collected, array_map('intval', $more));
            }
        }

        return array_slice(array_values(array_unique(array_filter($collected))), 0, $limit);
    }
}
