<?php
declare(strict_types=1);

namespace Dravit\Admin;

use Dravit\Core\Config;
use Dravit\Core\ServiceProviderInterface;
use Dravit\Units\Calc;
use Dravit\Units\PriceState;

defined('ABSPATH') || exit;

/**
 * Tab dữ liệu sản phẩm WooCommerce "Đơn vị & Thông số" (ADR D-20, D-24, docs/14 §2.1–2.3).
 * Sử dụng 100% class Admin chuẩn của WordPress / WooCommerce, không chèn `<style>` tùy chỉnh.
 */
final class ProductFields implements ServiceProviderInterface {
    public function register(): void {
        add_filter('woocommerce_product_data_tabs', [$this, 'add_product_tab'], 15);
        add_action('woocommerce_product_data_panels', [$this, 'render_product_panel']);
        add_action('woocommerce_admin_process_product_object', [$this, 'save_product_fields'], 10, 1);
        add_action('add_meta_boxes', [$this, 'cleanup_meta_boxes'], 40);
        add_action('admin_notices', [$this, 'render_primary_reassigned_notice']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_style']);
    }

    /**
     * @param array<string, mixed> $tabs
     * @return array<string, mixed>
     */
    public function add_product_tab(array $tabs): array {
        $tabs['drv_tile_specs'] = [
            'label'    => 'Đơn vị & Thông số Dravit',
            'target'   => 'drv_tile_specs_data',
            'class'    => ['show_if_simple', 'show_if_variable'],
            'priority' => 15,
        ];
        return $tabs;
    }

    public function enqueue_admin_style(string $hook): void {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }
        wp_add_inline_style(
            'common',
            '#woocommerce-product-data input[readonly]{background-color:#fff;color:#50575e;cursor:default;}'
        );
    }

    public function cleanup_meta_boxes(): void {
        remove_meta_box('postcustom', 'product', 'normal');
    }

    public function render_product_panel(): void {
        global $product_object;
        if (!$product_object instanceof \WC_Product) {
            return;
        }

        $unit_cfg   = PriceState::readUnitConfig($product_object);
        $line       = (string) ($product_object->get_meta('drv_line', true) ?: 'gach');
        $model      = (string) ($product_object->get_meta('drv_pricing_model', true) ?: 'area_pack');
        $price_m2   = (int) $product_object->get_meta('drv_price_m2', true);
        $sale_m2    = (int) $product_object->get_meta('drv_sale_price_m2', true);
        $w_mm       = (int) $product_object->get_meta('drv_piece_w_mm', true);
        $h_mm       = (int) $product_object->get_meta('drv_piece_h_mm', true);
        $area_piece = Calc::pieceAreaM2($w_mm, $h_mm);
        $m2_box     = (float) $product_object->get_meta('drv_m2_per_box', true);
        $price_pc   = ($price_m2 > 0 && $area_piece > 0.0) ? Calc::pricePerPiece($price_m2, $area_piece) : 0;

        $primary_tid  = (int) get_post_meta($product_object->get_id(), 'rank_math_primary_product_cat', true);
        $primary_auto = (string) get_post_meta($product_object->get_id(), 'drv_primary_auto', true);
        $manual_val   = ($primary_tid > 0 && $primary_auto === '0') ? (string) $primary_tid : 'auto';

        $collections_cfg = Config::get('collections');
        $primary_options = ['auto' => 'Tự động chọn theo trọng số ưu tiên (D-27)'];
        foreach ($collections_cfg as $slug => $c_item) {
            if ($slug === 'gach') {
                continue;
            }
            $t = get_term_by('slug', (string) $slug, 'product_cat');
            if ($t instanceof \WP_Term) {
                $primary_options[(string) $t->term_id] = sprintf(
                    '%s (Ưu tiên %d)',
                    (string) ($c_item['name'] ?? $t->name),
                    (int) ($c_item['priority'] ?? 0)
                );
            }
        }

        if (function_exists('wc_enqueue_js')) {
            wc_enqueue_js("
                (function($){
                    function drvSyncPriceReadonly() {
                        var isArea = $('#drv_pricing_model').val() === 'area_pack';
                        $('#_regular_price, #_sale_price').prop('readonly', isArea);
                    }
                    $(document).on('change', '#drv_pricing_model', drvSyncPriceReadonly);
                    $(document).on('click', '.drv-subtabs .nav-tab', function(e){
                        e.preventDefault();
                        var n = $(this).data('drvTab');
                        $('.drv-subtabs .nav-tab').removeClass('nav-tab-active');
                        $(this).addClass('nav-tab-active');
                        $('.drv-subpanel').prop('hidden', true).filter('[data-drv-panel=' + n + ']').prop('hidden', false);
                    });
                    drvSyncPriceReadonly();
                })(jQuery);
            ");
        }
        ?>
        <div id="drv_tile_specs_data" class="panel woocommerce_options_panel">
            <?php wp_nonce_field('drv_save_product_specs', 'drv_product_specs_nonce'); ?>

            <h2 class="nav-tab-wrapper drv-subtabs" style="padding:9px 12px 0;">
                <?php foreach ([1 => 'Phân hệ & Giá (D-20)', 2 => 'Quy cách & Đơn vị mua (D-24)', 3 => 'Kỹ thuật & Thi công', 4 => 'Showroom & AI'] as $n => $t) : ?>
                    <a href="#drv-sub-<?php echo esc_attr((string) $n); ?>" class="nav-tab<?php echo $n === 1 ? ' nav-tab-active' : ''; ?>" data-drv-tab="<?php echo esc_attr((string) $n); ?>"><?php echo esc_html($t); ?></a>
                <?php endforeach; ?>
            </h2>

            <div class="options_group drv-subpanel" data-drv-panel="1">
                <h4 style="padding:0 12px;margin:1em 0 .5em;"><?php echo esc_html('1. Phân hệ & Định giá theo m² (ADR D-20)'); ?></h4>
                <?php
                woocommerce_wp_select([
                    'id'          => 'drv_line',
                    'value'       => $line,
                    'label'       => 'Phân hệ sản phẩm',
                    'options'     => [
                        'gach'     => 'Gạch ốp lát & Đá nung kết (Tự gán 28 Smart Collections)',
                        'phu_kien' => 'Phụ kiện thi công (Keo dán gạch, keo chà ron, nẹp)',
                    ],
                ]);

                woocommerce_wp_select([
                    'id'          => 'drv_pricing_model',
                    'value'       => $model,
                    'label'       => 'Mô hình tính giá',
                    'options'     => [
                        'area_pack' => 'Theo diện tích m² quy đổi ra viên/thùng (area_pack)',
                        'piece'     => 'Theo đơn chiếc / viên rời (piece)',
                        'set'       => 'Theo bộ hoàn chỉnh (set)',
                        'pack'      => 'Theo bao / túi vật tư (pack)',
                    ],
                ]);

                woocommerce_wp_text_input([
                    'id'                => 'drv_price_m2',
                    'value'             => $price_m2 > 0 ? (string) $price_m2 : '',
                    'label'             => 'Giá niêm yết (đ/m²)',
                    'type'              => 'number',
                    'custom_attributes' => ['min' => '0', 'step' => '1000'],
                    'description'       => 'Để trống hoặc 0 nếu là hàng dự án "Liên hệ báo giá" (tự khóa giỏ hàng).',
                    'desc_tip'          => true,
                ]);

                woocommerce_wp_text_input([
                    'id'                => 'drv_sale_price_m2',
                    'value'             => $sale_m2 > 0 ? (string) $sale_m2 : '',
                    'label'             => 'Giá ưu đãi (đ/m²)',
                    'type'              => 'number',
                    'custom_attributes' => ['min' => '0', 'step' => '1000'],
                    'description'       => 'Nhỏ hơn giá niêm yết -> tự động gán vào danh mục Gạch Ưu Đãi.',
                    'desc_tip'          => true,
                ]);
                ?>
                <p class="form-field">
                    <label>Quy đổi tự động</label>
                    <span class="description">
                        <?php
                        echo esc_html(sprintf(
                            '1 thùng = %s m² · Giá giao dịch WooCommerce: %s/%s',
                            drv_format_decimal($m2_box, 4),
                            $price_pc > 0 ? drv_format_vnd($price_pc) : 'Liên hệ báo giá',
                            $unit_cfg['unit_label']
                        ));
                        ?>
                    </span>
                </p>
            </div>

            <div class="options_group drv-subpanel" data-drv-panel="2" hidden>
                <h4 style="padding:0 12px;margin:1em 0 .5em;"><?php echo esc_html('2. Kích thước, Quy cách thùng & Đơn vị mua từng SP (ADR D-24)'); ?></h4>
                <?php
                woocommerce_wp_text_input([
                    'id'                => 'drv_piece_w_mm',
                    'value'             => $w_mm > 0 ? (string) $w_mm : '',
                    'label'             => 'Chiều rộng viên (mm)',
                    'type'              => 'number',
                    'custom_attributes' => ['min' => '1', 'step' => '1'],
                ]);

                woocommerce_wp_text_input([
                    'id'                => 'drv_piece_h_mm',
                    'value'             => $h_mm > 0 ? (string) $h_mm : '',
                    'label'             => 'Chiều dài viên (mm)',
                    'type'              => 'number',
                    'custom_attributes' => ['min' => '1', 'step' => '1'],
                ]);

                woocommerce_wp_text_input([
                    'id'                => 'drv_thickness_mm',
                    'value'             => (string) $product_object->get_meta('drv_thickness_mm', true),
                    'label'             => 'Độ dày xương (mm)',
                    'type'              => 'number',
                    'custom_attributes' => ['min' => '1', 'step' => '0.1'],
                ]);

                woocommerce_wp_text_input([
                    'id'                => 'drv_pieces_per_box',
                    'value'             => (string) $unit_cfg['pieces_per_box'],
                    'label'             => 'Số viên / tấm mỗi thùng',
                    'type'              => 'number',
                    'custom_attributes' => ['min' => '1', 'step' => '1'],
                ]);

                woocommerce_wp_text_input([
                    'id'                => 'drv_kg_per_box',
                    'value'             => (string) $product_object->get_meta('drv_kg_per_box', true),
                    'label'             => 'Khối lượng 1 thùng (kg)',
                    'type'              => 'number',
                    'custom_attributes' => ['min' => '0', 'step' => '0.1'],
                ]);

                woocommerce_wp_select([
                    'id'      => 'drv_unit_label',
                    'value'   => $unit_cfg['unit_label'],
                    'label'   => 'Nhãn đơn vị cơ sở',
                    'options' => [
                        'viên' => 'viên (Gạch tiêu chuẩn)',
                        'tấm'  => 'tấm (Đá nung kết / Big Slab)',
                    ],
                ]);
                ?>
                <fieldset class="form-field">
                    <legend>Đơn vị cho phép khách chọn</legend>
                    <ul class="wc-radios">
                        <?php
                        $unit_choices = [
                            'm2'    => 'Theo m²',
                            'thung' => 'Theo thùng',
                            'vien'  => 'Theo viên / tấm',
                        ];
                        foreach ($unit_choices as $u_key => $u_lbl) :
                            ?>
                            <li>
                                <label>
                                    <input type="checkbox" class="checkbox" name="drv_buy_units[]"
                                           value="<?php echo esc_attr($u_key); ?>"
                                           <?php checked(in_array($u_key, $unit_cfg['buy_units'], true)); ?>>
                                    <?php echo esc_html($u_lbl); ?>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </fieldset>
                <?php
                woocommerce_wp_select([
                    'id'      => 'drv_default_buy_unit',
                    'value'   => $unit_cfg['default_buy_unit'],
                    'label'   => 'Tab đơn vị mặc định trên PDP',
                    'options' => [
                        'm2'    => 'Theo m²',
                        'thung' => 'Theo thùng',
                        'vien'  => 'Theo viên / tấm',
                    ],
                ]);

                woocommerce_wp_select([
                    'id'          => 'drv_round_mode',
                    'value'       => $unit_cfg['round_mode'],
                    'label'       => 'Chế độ làm tròn khi mua',
                    'options'     => [
                        'box'   => 'Bắt buộc chẵn thùng (qty_step = số viên/thùng)',
                        'piece' => 'Cho phép mua lẻ viên/tấm (qty_step = 1)',
                    ],
                    'description' => 'Nếu không tích cho phép mua "Theo viên/tấm", hệ thống tự khóa về chẵn thùng.',
                    'desc_tip'    => true,
                ]);

                woocommerce_wp_text_input([
                    'id'                => 'drv_qty_min',
                    'value'             => (string) $unit_cfg['qty_min'],
                    'label'             => 'Số viên tối thiểu mỗi đơn',
                    'type'              => 'number',
                    'custom_attributes' => ['min' => '1', 'step' => '1'],
                ]);
                ?>
            </div>

            <div class="options_group drv-subpanel" data-drv-panel="3" hidden>
                <h4 style="padding:0 12px;margin:1em 0 .5em;"><?php echo esc_html('3. Thông số Kỹ thuật ISO & Khuyến nghị Thi công'); ?></h4>
                <?php
                woocommerce_wp_text_input([
                    'id'          => 'drv_water_absorption',
                    'value'       => (string) $product_object->get_meta('drv_water_absorption', true),
                    'label'       => 'Độ hút nước E (%)',
                    'type'        => 'text',
                    'description' => 'VD: 0.12 (tự động suy dẫn nhóm pa_do-hut-nuoc ISO 10545-3).',
                    'desc_tip'    => true,
                ]);

                woocommerce_wp_select([
                    'id'      => 'drv_pei',
                    'value'   => (string) $product_object->get_meta('drv_pei', true),
                    'label'   => 'Cấp chống mài mòn men (PEI)',
                    'options' => [
                        ''       => 'Không áp dụng / Xương đồng chất',
                        'PEI II' => 'PEI II (Tường & phòng tắm nhẹ)',
                        'PEI III'=> 'PEI III (Nhà ở dân dụng)',
                        'PEI IV' => 'PEI IV (Phòng khách, sảnh, thương mại vừa)',
                        'PEI V'  => 'PEI V (Mặt bằng công cộng mật độ cao)',
                    ],
                ]);

                woocommerce_wp_checkbox([
                    'id'          => 'drv_rectified',
                    'value'       => ((string) $product_object->get_meta('drv_rectified', true) === '1') ? 'yes' : 'no',
                    'label'       => 'Mài cạnh phẳng (Rectified)',
                    'description' => 'Cho phép đi đường ron mảnh 1,5 - 2 mm',
                ]);

                woocommerce_wp_text_input([
                    'id'                => 'drv_faces',
                    'value'             => (string) $product_object->get_meta('drv_faces', true),
                    'label'             => 'Số mặt vân ngẫu nhiên (Faces)',
                    'type'              => 'number',
                    'custom_attributes' => ['min' => '1', 'step' => '1'],
                ]);

                woocommerce_wp_select([
                    'id'      => 'drv_shade',
                    'value'   => (string) $product_object->get_meta('drv_shade', true),
                    'label'   => 'Độ biến thiên sắc độ (V1 - V4)',
                    'options' => [
                        ''   => 'Chưa thiết lập',
                        'V1' => 'V1 - Đồng nhất cao',
                        'V2' => 'V2 - Biến thiên nhẹ tự nhiên',
                        'V3' => 'V3 - Biến thiên rõ cá tính',
                        'V4' => 'V4 - Biến thiên ngẫu nhiên mạnh',
                    ],
                ]);

                woocommerce_wp_text_input([
                    'id'                => 'drv_waste_rate',
                    'value'             => (string) $product_object->get_meta('drv_waste_rate', true),
                    'label'             => 'Tỷ lệ hao hụt khuyến nghị riêng (%)',
                    'type'              => 'number',
                    'custom_attributes' => ['min' => '0', 'max' => '30', 'step' => '0.5'],
                    'description'       => 'Để trống dùng mặc định 5% (hoặc 8-10% cho gạch khổ lớn Big Slab).',
                    'desc_tip'          => true,
                ]);

                woocommerce_wp_text_input([
                    'id'                => 'drv_grout_mm',
                    'value'             => (string) $product_object->get_meta('drv_grout_mm', true),
                    'label'             => 'Độ rộng đường ron khuyến nghị (mm)',
                    'type'              => 'number',
                    'custom_attributes' => ['min' => '0.5', 'step' => '0.5'],
                ]);

                woocommerce_wp_text_input([
                    'id'    => 'drv_grout_color',
                    'value' => (string) $product_object->get_meta('drv_grout_color', true),
                    'label' => 'Màu keo chà ron gợi ý',
                ]);

                woocommerce_wp_text_input([
                    'id'    => 'drv_pack_unit_label',
                    'value' => (string) $product_object->get_meta('drv_pack_unit_label', true),
                    'label' => 'Nhãn quy cách phụ kiện (VD: bao 25kg)',
                ]);

                woocommerce_wp_text_input([
                    'id'                => 'drv_coverage_m2_per_unit',
                    'value'             => (string) $product_object->get_meta('drv_coverage_m2_per_unit', true),
                    'label'             => 'Định mức phủ m²/đơn vị phụ kiện',
                    'type'              => 'number',
                    'custom_attributes' => ['min' => '0', 'step' => '0.1'],
                ]);

                woocommerce_wp_text_input([
                    'id'    => 'drv_joint_note',
                    'value' => (string) $product_object->get_meta('drv_joint_note', true),
                    'label' => 'Ghi chú thi công phụ kiện',
                ]);
                ?>
            </div>

            <div class="options_group drv-subpanel" data-drv-panel="4" hidden>
                <h4 style="padding:0 12px;margin:1em 0 .5em;"><?php echo esc_html('4. Showroom, Danh mục chính, Tư liệu KTS & Trợ lý AI'); ?></h4>
                <?php
                woocommerce_wp_select([
                    'id'          => 'drv_manual_primary_cat',
                    'value'       => $manual_val,
                    'label'       => 'Danh mục chính (Breadcrumb)',
                    'options'     => $primary_options,
                    'description' => 'Để Tự động để hệ thống chọn theo trọng số D-27 khớp thông số sản phẩm.',
                    'desc_tip'    => true,
                ]);

                woocommerce_wp_text_input([
                    'id'    => 'drv_series',
                    'value' => (string) $product_object->get_meta('drv_series', true),
                    'label' => 'Tên dòng phối bộ (Series)',
                ]);

                woocommerce_wp_text_input([
                    'id'    => 'drv_color_name',
                    'value' => (string) $product_object->get_meta('drv_color_name', true),
                    'label' => 'Tên mẫu vân / Tông màu chi tiết',
                ]);

                woocommerce_wp_text_input([
                    'id'    => 'drv_factory_code',
                    'value' => (string) $product_object->get_meta('drv_factory_code', true),
                    'label' => 'Mã mẫu gốc nhà máy',
                ]);

                woocommerce_wp_text_input([
                    'id'          => 'drv_google_photos_url',
                    'value'       => (string) $product_object->get_meta('drv_google_photos_url', true),
                    'label'       => 'Link Album Google Photos đối chiếu',
                    'type'        => 'url',
                ]);

                woocommerce_wp_text_input([
                    'id'          => 'drv_showroom_shelf',
                    'value'       => (string) $product_object->get_meta('drv_showroom_shelf', true),
                    'label'       => 'Mã kệ trưng bày Showroom',
                    'description' => 'Điền mã kệ (VD: A-12) sẽ tự bật bộ lọc "Có mẫu tại Showroom".',
                    'desc_tip'    => true,
                ]);

                woocommerce_wp_text_input([
                    'id'    => 'drv_card_label',
                    'value' => (string) $product_object->get_meta('drv_card_label', true),
                    'label' => 'Nhãn nổi bật trên Card (tối đa 16 ký tự)',
                ]);

                woocommerce_wp_text_input([
                    'id'          => 'drv_face_image_ids',
                    'value'       => (string) $product_object->get_meta('drv_face_image_ids', true),
                    'label'       => 'Danh sách Attachment ID các mặt vân',
                    'description' => 'Nhập các ID ảnh cách nhau dấu phẩy (VD: 101,102,103,104).',
                    'desc_tip'    => true,
                ]);

                woocommerce_wp_text_input([
                    'id'                => 'drv_map_file_id',
                    'value'             => (string) $product_object->get_meta('drv_map_file_id', true),
                    'label'             => 'Attachment ID file ZIP Texture 3D',
                    'type'              => 'number',
                    'custom_attributes' => ['min' => '0', 'step' => '1'],
                ]);

                woocommerce_wp_textarea_input([
                    'id'          => 'drv_ai_blurb',
                    'value'       => (string) $product_object->get_meta('drv_ai_blurb', true),
                    'label'       => 'Mô tả súc tích cho Trợ lý AI (≤ 200 ký tự)',
                    'rows'        => 2,
                ]);
                ?>
            </div>
        </div>
        <?php
    }

    public function save_product_fields(\WC_Product $product): void {
        if (!current_user_can('edit_products')) {
            return;
        }

        $nonce = isset($_POST['drv_product_specs_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['drv_product_specs_nonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'drv_save_product_specs')) {
            return;
        }

        $line = isset($_POST['drv_line']) ? sanitize_key(wp_unslash((string) $_POST['drv_line'])) : 'gach';
        if (!in_array($line, ['gach', 'phu_kien'], true)) {
            $line = 'gach';
        }
        $product->update_meta_data('drv_line', $line);

        $model = isset($_POST['drv_pricing_model']) ? sanitize_key(wp_unslash((string) $_POST['drv_pricing_model'])) : 'area_pack';
        if (!in_array($model, ['area_pack', 'piece', 'set', 'pack'], true)) {
            $model = 'area_pack';
        }
        $product->update_meta_data('drv_pricing_model', $model);

        $int_fields = [
            'drv_price_m2',
            'drv_sale_price_m2',
            'drv_piece_w_mm',
            'drv_piece_h_mm',
            'drv_pieces_per_box',
            'drv_qty_min',
            'drv_faces',
            'drv_map_file_id',
        ];
        foreach ($int_fields as $key) {
            $val = isset($_POST[$key]) ? absint(wp_unslash((string) $_POST[$key])) : 0;
            $product->update_meta_data($key, (string) $val);
        }

        $float_fields = [
            'drv_thickness_mm',
            'drv_kg_per_box',
            'drv_grout_mm',
            'drv_waste_rate',
            'drv_coverage_m2_per_unit',
        ];
        foreach ($float_fields as $key) {
            $raw = isset($_POST[$key]) ? str_replace(',', '.', sanitize_text_field(wp_unslash((string) $_POST[$key]))) : '0';
            $val = max(0.0, (float) $raw);
            $product->update_meta_data($key, $val > 0.0 ? (string) $val : '');
        }

        $water = isset($_POST['drv_water_absorption'])
            ? str_replace(',', '.', sanitize_text_field(wp_unslash((string) $_POST['drv_water_absorption'])))
            : '';
        $product->update_meta_data('drv_water_absorption', $water);

        $unit_label = isset($_POST['drv_unit_label']) ? sanitize_text_field(wp_unslash((string) $_POST['drv_unit_label'])) : 'viên';
        $product->update_meta_data('drv_unit_label', in_array($unit_label, ['viên', 'tấm'], true) ? $unit_label : 'viên');

        $raw_buy_units = isset($_POST['drv_buy_units']) && is_array($_POST['drv_buy_units'])
            ? array_values(array_map('sanitize_key', array_map('strval', wp_unslash($_POST['drv_buy_units'])))) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via sanitize_key right here
            : ['m2', 'thung'];
        $product->update_meta_data('drv_buy_units', $raw_buy_units);

        $default_unit = isset($_POST['drv_default_buy_unit'])
            ? sanitize_key(wp_unslash((string) $_POST['drv_default_buy_unit']))
            : 'm2';
        $product->update_meta_data('drv_default_buy_unit', $default_unit);

        $round_mode = isset($_POST['drv_round_mode'])
            ? sanitize_key(wp_unslash((string) $_POST['drv_round_mode']))
            : 'box';
        $product->update_meta_data('drv_round_mode', $round_mode);

        $pei = isset($_POST['drv_pei']) ? sanitize_text_field(wp_unslash((string) $_POST['drv_pei'])) : '';
        if (!in_array($pei, ['', 'PEI II', 'PEI III', 'PEI IV', 'PEI V'], true)) {
            $pei = '';
        }
        $product->update_meta_data('drv_pei', $pei);

        $rectified = isset($_POST['drv_rectified']) ? '1' : '0';
        $product->update_meta_data('drv_rectified', $rectified);

        $shade = isset($_POST['drv_shade']) ? sanitize_text_field(wp_unslash((string) $_POST['drv_shade'])) : '';
        if (!in_array($shade, ['', 'V1', 'V2', 'V3', 'V4'], true)) {
            $shade = '';
        }
        $product->update_meta_data('drv_shade', $shade);

        $gphotos = isset($_POST['drv_google_photos_url'])
            ? esc_url_raw(wp_unslash((string) $_POST['drv_google_photos_url']))
            : '';
        $product->update_meta_data('drv_google_photos_url', $gphotos);

        $text_fields = [
            'drv_grout_color'     => 60,
            'drv_pack_unit_label' => 40,
            'drv_joint_note'      => 160,
            'drv_series'          => 80,
            'drv_color_name'      => 120,
            'drv_factory_code'    => 60,
            'drv_showroom_shelf'  => 30,
            'drv_card_label'      => 16,
            'drv_ai_blurb'        => 200,
        ];
        foreach ($text_fields as $key => $max_len) {
            $raw_txt = isset($_POST[$key]) ? sanitize_text_field(wp_unslash((string) $_POST[$key])) : '';
            $trimmed = function_exists('mb_substr') ? mb_substr($raw_txt, 0, $max_len) : substr($raw_txt, 0, $max_len);
            $product->update_meta_data($key, $trimmed);
        }

        $face_ids_raw = isset($_POST['drv_face_image_ids']) ? sanitize_text_field(wp_unslash((string) $_POST['drv_face_image_ids'])) : '';
        $face_ids     = array_values(array_filter(array_map('absint', explode(',', $face_ids_raw))));
        $product->update_meta_data('drv_face_image_ids', implode(',', $face_ids));

        // Lựa chọn Danh mục chính thủ công hoặc tự động.
        $manual_primary = isset($_POST['drv_manual_primary_cat'])
            ? sanitize_text_field(wp_unslash((string) $_POST['drv_manual_primary_cat']))
            : 'auto';

        $pid = $product->get_id();
        if ($pid > 0) {
            if ($manual_primary === 'auto' || (int) $manual_primary <= 0) {
                update_post_meta($pid, 'drv_primary_auto', '1');
            } else {
                update_post_meta($pid, 'rank_math_primary_product_cat', (int) $manual_primary);
                update_post_meta($pid, 'drv_primary_auto', '0');
            }
        }
    }

    public function render_primary_reassigned_notice(): void {
        if (!function_exists('get_current_screen')) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'product') {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only post ID on admin edit screen
        $post_id = isset($_GET['post']) ? absint(wp_unslash((string) $_GET['post'])) : 0;
        if ($post_id <= 0) {
            return;
        }

        $msg = get_transient('drv_primary_reassigned_' . $post_id);
        if (!is_string($msg) || $msg === '') {
            return;
        }

        delete_transient('drv_primary_reassigned_' . $post_id);
        echo '<div class="notice notice-info is-dismissible"><p>' . esc_html($msg) . '</p></div>';
    }
}
