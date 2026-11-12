<?php
/**
 * Template Name: Master UI Kit (Dev Only)
 *
 * Chỉ phục vụ kiểm thử trực quan 22 primitives `drv_ui()`, Global Shell & `@layer plugins` tại môi trường dev/staging.
 * Tự động trả 404 khi `DRV_ENV === 'production'` (Gate 1 — docs/21_Theme_Build_Execution_Roadmap.md).
 */
declare(strict_types=1);

use Dravit\Core\Integrations\FluentFormsBridge;
use Dravit\Core\Menu\MegaMenuRenderer;

defined('ABSPATH') || exit;

if (DRV_ENV === 'production') {
    global $wp_query;
    if (is_object($wp_query) && method_exists($wp_query, 'set_404')) {
        $wp_query->set_404();
    }
    status_header(404);
    nocache_headers();
    $not_found = get_404_template();
    if (is_string($not_found) && $not_found !== '' && is_readable($not_found)) {
        include $not_found;
    } else {
        include DRV_DIR . '/index.php';
    }
    exit;
}

add_filter(
    'rank_math/frontend/robots',
    static fn (): array => ['index' => 'noindex', 'follow' => 'nofollow'],
    99
);

add_action(
    'wp_head',
    static function (): void {
        if (!drv_is_rank_math_active()) {
            echo '<meta name="robots" content="noindex, nofollow">' . "\n";
        }
    },
    1
);

$provinces_file = DRV_DIR . '/config/provinces-vn.php';
$provinces_raw  = is_readable($provinces_file) ? require $provinces_file : [];
$province_opts  = ['' => 'Chọn Tỉnh / Thành phố (34 đơn vị mới)'];
if (is_array($provinces_raw)) {
    foreach ($provinces_raw as $code => $info) {
        $province_opts[(string) $code] = is_array($info) && isset($info['name']) ? (string) $info['name'] : (string) $info;
    }
}

$ph_dir = DRV_URI . '/assets/images/placeholders/';
$gach_url = MegaMenuRenderer::collection_url('gach');

get_header();
?>
<main id="main-content">
    <section class="l-section l-section--white">
        <div class="l-container l-stack l-stack--lg">
            <?php
            drv_ui('breadcrumb', [
                'items' => [
                    ['label' => 'Trang chủ', 'url' => MegaMenuRenderer::internal_url('/')],
                    ['label' => 'Master UI Kit & Design System (Step 1)'],
                ],
            ]);
            ?>

            <div class="l-cluster l-cluster--between">
                <div class="l-stack l-stack--xs">
                    <span class="drv-sec-head__eyebrow">Step 1 · Master UI Kit &amp; Design System (22 Primitives drv_ui)</span>
                    <h1>HỆ THỐNG NHẬN DIỆN LƯỚI KIẾN TRÚC ĐƯỜNG RON DRAVIT</h1>
                    <p class="u-text-muted">Trình diễn trực tiếp Design Tokens, CSS 8 tầng @layer, 22 UI Primitives drv_ui(), Light DOM Web Components và Global Shell.</p>
                </div>
                <div class="l-cluster l-cluster--sm">
                    <?php
                    drv_ui('btn', ['label' => 'Mở Modal Báo Giá', 'variant' => 'moss', 'size' => 'sm', 'action' => 'dialog-open', 'target' => '#drv-quote-modal']);
                    drv_ui('btn', ['label' => 'Mở Mini-Cart Drawer', 'variant' => 'outline', 'size' => 'sm', 'action' => 'dialog-open', 'target' => '#drv-minicart-drawer']);
                    drv_ui('btn', ['label' => 'Mở Dialog Mẫu', 'variant' => 'soft', 'size' => 'sm', 'action' => 'dialog-open', 'target' => '#drv-uikit-sample-dialog']);
                    ?>
                </div>
            </div>

            <nav class="drv-pill-row" aria-label="Mục lục thành phần UI Kit">
                <?php
                drv_ui('chip', ['label' => '1. Bảng màu WCAG AA', 'href' => '#sec-tokens', 'active' => true]);
                drv_ui('chip', ['label' => '2. Typography', 'href' => '#sec-typography']);
                drv_ui('chip', ['label' => '3. Atoms & Form Controls', 'href' => '#sec-atoms']);
                drv_ui('chip', ['label' => '4. Product Card & Sub-Silo', 'href' => '#sec-cards']);
                drv_ui('chip', ['label' => '5. Bộ tính m² & Shop the Look', 'href' => '#sec-calculators']);
                drv_ui('chip', ['label' => '6. Bảng thông số, GEO & FAQ', 'href' => '#sec-tables-geo']);
                drv_ui('chip', ['label' => '7. Motion & Fluent Forms Skin', 'href' => '#sec-motion']);
                ?>
            </nav>
        </div>
    </section>

    <section id="sec-tokens" class="l-section">
        <div class="l-container">
            <?php
            drv_ui('section-head', [
                'eyebrow'       => '01. Color Tokens (tokens.css)',
                'title'         => 'Bảng màu thương hiệu & Tỷ lệ tương phản WCAG 2.2 AA',
                'desc'          => 'Màu chính thương hiệu Dravit Navy + Deep Ink + Brand Teal + Warm Amber trên nền Clean Slate.',
                'badge_text'    => 'Tương phản WCAG 2.2 AA',
                'badge_variant' => 'showroom',
            ]);
            ?>

            <div class="l-grout-grid l-grout-grid--4">
                <div class="l-stack l-stack--sm">
                    <div class="drv-uikit-swatch-bar drv-uikit-swatch-bar--navy">
                        <span class="u-bold">--c-navy-900</span>
                        <span class="u-text-xs u-tabular">Navy Primary</span>
                    </div>
                    <div class="u-text-sm u-bold">Brand Primary Dravit (11,04:1)</div>
                    <p class="u-text-xs u-text-muted">Header text, thẻ H1 - H6, Nút chính Primary, Footer, Logo.</p>
                </div>

                <div class="l-stack l-stack--sm">
                    <div class="drv-uikit-swatch-bar drv-uikit-swatch-bar--ink">
                        <span class="u-bold">--c-text-main</span>
                        <span class="u-text-xs u-tabular">Deep Ink</span>
                    </div>
                    <div class="u-text-sm u-bold">Deep Ink Text (16,3:1)</div>
                    <p class="u-text-xs u-text-muted">Chữ nội dung chính, bảng thông số, form input đọc siêu nét.</p>
                </div>

                <div class="l-stack l-stack--sm">
                    <div class="drv-uikit-swatch-bar drv-uikit-swatch-bar--line">
                        <span class="u-bold">--c-navy-200</span>
                        <span class="u-text-xs u-tabular">Grout 1px</span>
                    </div>
                    <div class="u-text-sm u-bold">Đường kẻ ron 1px (--c-line)</div>
                    <p class="u-text-xs u-text-muted">Dùng cho lưới .l-grout-grid, viền card và thanh ngăn cách.</p>
                </div>

                <div class="l-stack l-stack--sm">
                    <div class="drv-uikit-swatch-bar drv-uikit-swatch-bar--teal">
                        <span class="u-bold">--c-teal-500</span>
                        <span class="u-text-xs u-tabular">Brand Teal</span>
                    </div>
                    <div class="u-text-sm u-bold">Brand Teal (4,62:1 chữ trắng)</div>
                    <p class="u-text-xs u-text-muted">Nền nút phụ Báo giá, viền focus, trạng thái active.</p>
                </div>

                <div class="l-stack l-stack--sm">
                    <div class="drv-uikit-swatch-bar drv-uikit-swatch-bar--teal-dark">
                        <span class="u-bold">--c-teal-700</span>
                        <span class="u-text-xs u-tabular">Teal Link</span>
                    </div>
                    <div class="u-text-sm u-bold">Teal Text/Link (6,37:1 trên nền sáng)</div>
                    <p class="u-text-xs u-text-muted">Bắt buộc dùng khi hiển thị chữ/link màu teal trên nền sáng.</p>
                </div>

                <div class="l-stack l-stack--sm">
                    <div class="drv-uikit-swatch-bar drv-uikit-swatch-bar--amber">
                        <span class="u-bold">--c-amber-500</span>
                        <span class="u-text-xs u-tabular">Warm Amber</span>
                    </div>
                    <div class="u-text-sm u-bold">Warm Amber Pop</div>
                    <p class="u-text-xs u-text-muted">Badge Showroom tại kệ, điểm nhấn nổi bật.</p>
                </div>

                <div class="l-stack l-stack--sm">
                    <div class="drv-uikit-swatch-bar drv-uikit-swatch-bar--ivory">
                        <span class="u-bold">--c-ivory</span>
                        <span class="u-text-xs u-tabular">Clean Ground</span>
                    </div>
                    <div class="u-text-sm u-bold">Clean Ground Slate (15,6:1)</div>
                    <p class="u-text-xs u-text-muted">Nền chính toàn trang sạch bóng, tôn vinh ảnh vân gạch.</p>
                </div>

                <div class="l-stack l-stack--sm">
                    <div class="l-grid l-grid--3 l-grid--gap-sm drv-uikit-func-grid">
                        <div class="drv-uikit-func-chip drv-uikit-func-chip--sale">Sale</div>
                        <div class="drv-uikit-func-chip drv-uikit-func-chip--success">Success</div>
                        <div class="drv-uikit-func-chip drv-uikit-func-chip--warning">Warning</div>
                    </div>
                    <div class="u-text-sm u-bold">Functional: Sale · Success · Warning</div>
                    <p class="u-text-xs u-text-muted">Chỉ dùng cho giá KM, tồn kho và cảnh báo lô màu Shade.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="sec-typography" class="l-section l-section--white">
        <div class="l-container">
            <?php
            drv_ui('section-head', [
                'eyebrow' => '02. Typography System',
                'title'   => 'Thang chữ Anton (Display) & Quicksand Variable (UI/Body)',
                'desc'    => 'Anton tự host cho H1, tiêu đề lớn và số thứ tự 01 - 05. Quicksand Variable tự host cho H2 - H6, nội dung và giá tiền tabular-nums.',
            ]);
            ?>

            <div class="l-grout-grid">
                <div class="l-cluster l-cluster--between">
                    <?php drv_ui('badge', ['text' => '--fs-display · Anton 400', 'variant' => 'outline']); ?>
                    <p class="u-display">GẠCH ỐP LÁT CHÍNH HÃNG DRAVIT</p>
                </div>
                <div class="l-cluster l-cluster--between">
                    <?php drv_ui('badge', ['text' => '--fs-h2 · Quicksand 700', 'variant' => 'outline']); ?>
                    <h2>Chọn gạch đúng thông số cho từng không gian sống</h2>
                </div>
                <div class="l-cluster l-cluster--between">
                    <?php drv_ui('badge', ['text' => '--fs-h3 · Quicksand 700', 'variant' => 'outline']); ?>
                    <h3>Xương đá Porcelain đặc chắc - Độ hút nước dưới 0,5%</h3>
                </div>
                <div class="l-cluster l-cluster--between">
                    <?php drv_ui('badge', ['text' => '--fs-base · Quicksand 500', 'variant' => 'outline']); ?>
                    <p>Gạch xương đá Porcelain khổ 80x80 với nền trắng vân xám Calacatta sắc nét, cạnh mài phẳng chuẩn xác đi mạch ron 1,5 - 2 mm.</p>
                </div>
                <div class="l-cluster l-cluster--between">
                    <?php drv_ui('badge', ['text' => '--fs-price · drv_ui(price)', 'variant' => 'outline']); ?>
                    <?php
                    drv_ui('price', [
                        'context' => 'pdp',
                        'product' => [
                            'price_m2'         => 385000,
                            'regular_price_m2' => 450000,
                            'price_piece'      => 246400,
                            'pieces_per_box'   => 3,
                            'm2_per_box'       => 1.92,
                            'unit_label'       => 'viên',
                        ],
                    ]);
                    ?>
                </div>
            </div>
        </div>
    </section>

    <section id="sec-atoms" class="l-section">
        <div class="l-container l-stack l-stack--xl">
            <?php
            drv_ui('section-head', [
                'eyebrow'      => '03. Atoms, Swatches, Steps & Form Primitives',
                'title'        => 'Hệ thống Nút bấm, Nhãn, Chip, Swatch, Steps & Biểu mẫu WCAG 2.2 AA',
                'action_label' => 'Xem kho 28 danh mục gạch',
                'action_url'   => $gach_url,
            ]);

            drv_ui('steps', [
                'steps' => [
                    ['label' => 'Giỏ hàng & Quy đổi thùng', 'sub' => 'Kiểm tra số viên / m²', 'state' => 'done', 'url' => MegaMenuRenderer::internal_url('/gio-hang')],
                    ['label' => 'Thông tin giao công trình', 'sub' => 'Địa chỉ 2 cấp · Báo cước sau', 'state' => 'current'],
                    ['label' => 'Xác nhận & Giữ lô màu', 'sub' => 'Thanh toán VietQR / COD', 'state' => 'upcoming'],
                ],
            ]);
            ?>

            <div class="l-grout-grid l-grout-grid--2">
                <div class="l-stack l-stack--md">
                    <h3>Biến thể Nút bấm drv_ui('btn')</h3>
                    <div class="l-cluster">
                        <?php
                        drv_ui('btn', ['label' => 'Đặt mua ngay (Primary)']);
                        drv_ui('btn', ['label' => 'Nhận báo giá (Moss)', 'variant' => 'moss', 'action' => 'dialog-open', 'target' => '#drv-quote-modal']);
                        drv_ui('btn', ['label' => 'Thêm vào giỏ (Outline)', 'variant' => 'outline']);
                        drv_ui('btn', ['label' => 'Tải Map CAD/3D (Soft)', 'variant' => 'soft']);
                        drv_ui('btn', ['label' => 'Tạm hết kho', 'disabled' => true]);
                        ?>
                    </div>

                    <div class="l-cluster">
                        <?php
                        drv_ui('btn', ['label' => 'Cỡ nhỏ 36px', 'size' => 'sm']);
                        drv_ui('btn', ['label' => 'Cỡ chuẩn 44px']);
                        drv_ui('btn', ['label' => 'Cỡ lớn 52px', 'size' => 'lg']);
                        drv_ui('btn', ['label' => 'Xem 28 danh mục', 'variant' => 'link', 'href' => $gach_url, 'icon_right' => 'arrow-right']);
                        ?>
                    </div>

                    <hr>

                    <h3>Nhãn drv_ui('badge'), Chip drv_ui('chip') &amp; Swatch drv_ui('swatch')</h3>
                    <div class="l-cluster l-cluster--sm">
                        <?php
                        drv_ui('badge', ['text' => '-15% Ưu đãi kho', 'variant' => 'sale']);
                        drv_ui('badge', ['text' => 'Có mẫu tại Showroom', 'variant' => 'showroom']);
                        drv_ui('badge', ['text' => 'Mẫu mới 2026', 'variant' => 'moss']);
                        drv_ui('badge', ['text' => 'Porcelain ≤ 0,5%', 'variant' => 'champagne']);
                        drv_ui('badge', ['text' => 'Còn hàng đồng lô', 'variant' => 'success', 'icon' => 'check-circle']);
                        drv_ui('badge', ['text' => 'Lưu ý Shade V2', 'variant' => 'warning']);
                        ?>
                    </div>

                    <div class="l-cluster l-cluster--sm">
                        <?php
                        drv_ui('chip', ['label' => 'Gạch 80x80', 'count' => 48, 'active' => true, 'icon' => 'check']);
                        drv_ui('chip', ['label' => 'Gạch 60x120', 'count' => 32]);
                        drv_ui('chip', ['label' => 'Bề mặt Matt R10', 'count' => 64]);
                        ?>
                    </div>

                    <?php
                    drv_ui('swatch', [
                        'axis'        => 'size',
                        'group_label' => 'Kích thước cùng bộ sưu tập:',
                        'items'       => [
                            ['label' => '60x60 cm', 'url' => MegaMenuRenderer::collection_url('gach-60x60')],
                            ['label' => '80x80 cm', 'url' => MegaMenuRenderer::collection_url('gach-80x80'), 'active' => true],
                            ['label' => '60x120 cm', 'url' => MegaMenuRenderer::collection_url('gach-60x120')],
                        ],
                    ]);

                    drv_ui('swatch', [
                        'axis'        => 'color',
                        'group_label' => 'Màu vân cùng dòng:',
                        'items'       => [
                            ['label' => 'Trắng Calacatta', 'thumb_url' => $ph_dir . 'tile-marble-calacatta.svg', 'active' => true],
                            ['label' => 'Kem Terrazzo', 'thumb_url' => $ph_dir . 'tile-terrazzo-ivory.svg', 'url' => MegaMenuRenderer::collection_url('gach-terrazzo')],
                            ['label' => 'Xám Xi Măng', 'thumb_url' => $ph_dir . 'tile-cement-grey.svg', 'url' => MegaMenuRenderer::collection_url('gach-van-xi-mang')],
                        ],
                    ]);
                    ?>
                </div>

                <div class="l-stack l-stack--md">
                    <h3>Form Controls drv_ui('field') &amp; Summary Box drv_ui('summary-box')</h3>
                    <div class="l-grid l-grid--2">
                        <?php
                        drv_ui('field', [
                            'id'           => 'uikit-name',
                            'label'        => 'Họ và tên người nhận',
                            'required'     => true,
                            'value'        => 'Nguyễn Minh Tuấn',
                            'autocomplete' => 'name',
                        ]);
                        drv_ui('field', [
                            'id'        => 'uikit-phone',
                            'type'      => 'tel',
                            'inputmode' => 'tel',
                            'label'     => 'Số điện thoại Zalo',
                            'required'  => true,
                            'value'     => '0909',
                            'hint'      => '10 chữ số chuẩn VN',
                            'error'     => 'Số điện thoại chưa đủ 10 số (VD: 0909123456).',
                        ]);
                        ?>
                    </div>

                    <?php
                    drv_ui('field', [
                        'id'      => 'uikit-prov',
                        'type'    => 'select',
                        'label'   => 'Tỉnh / Thành phố (34 đơn vị hành chính mới)',
                        'options' => $province_opts,
                        'value'   => '79',
                    ]);
                    ?>

                    <div class="l-cluster">
                        <?php
                        drv_ui('field', ['id' => 'uikit-chk-waste', 'type' => 'checkbox', 'label' => 'Cộng 5% hao hụt thi công', 'checked' => true]);
                        drv_ui('field', ['id' => 'uikit-chk-box', 'type' => 'checkbox', 'label' => 'Làm tròn chẵn thùng nguyên đai', 'checked' => true]);
                        ?>
                    </div>

                    <?php
                    drv_ui('summary-box', [
                        'metrics' => [
                            ['label' => 'Tổng diện tích thực', 'value' => '11,52 m² (6 thùng)'],
                            ['label' => 'Khối lượng ước tính', 'value' => '252 kg (18 viên)'],
                        ],
                        'rows' => [
                            ['label' => 'Tạm tính tiền gạch:', 'value' => '4.435.200đ', 'muted' => true],
                            ['label' => 'Phí vận chuyển xe tải:', 'value' => 'Báo sau khi xác nhận đơn', 'muted' => true, 'highlight' => true],
                        ],
                        'total_label' => 'Tổng giá trị đơn:',
                        'total_html'  => '4.435.200đ',
                        'footnote'    => 'Cam kết xuất kho 100% cùng lô sản xuất (Shade/Caliber).',
                    ]);
                    ?>
                </div>
            </div>
        </div>
    </section>

    <section id="sec-cards" class="l-section l-section--white">
        <div class="l-container l-stack l-stack--lg">
            <div class="drv-sec-head">
                <div class="l-stack l-stack--xs">
                    <span class="drv-sec-head__eyebrow">04. Catalog Molecules</span>
                    <h2>Visual Sub-Silo Tiles, 4 Trạng Thái Product Card &amp; Load More</h2>
                    <p class="drv-sec-head__desc">Không có nút Wishlist thừa; tự động chuyển đổi giữa chế độ có giá niêm yết (priced/sale) và Liên hệ báo giá (quote).</p>
                </div>
                <?php
                drv_ui('btn', [
                    'label'     => 'Chạy lại Stagger Reveal',
                    'variant'   => 'outline',
                    'size'      => 'sm',
                    'icon_left' => 'sparkle',
                    'action'    => 'motion-replay-stagger',
                    'target'    => '#drv-uikit-card-grid',
                ]);
                ?>
            </div>

            <div class="drv-pill-row" data-drv-stagger>
                <?php
                drv_ui('sub-silo-tile', ['name' => 'Gạch 80x80 cm', 'count' => 48, 'url' => MegaMenuRenderer::collection_url('gach-80x80'), 'thumb_url' => $ph_dir . 'tile-marble-calacatta.svg', 'active' => true]);
                drv_ui('sub-silo-tile', ['name' => 'Khổ lớn 60x120', 'count' => 32, 'url' => MegaMenuRenderer::collection_url('gach-60x120'), 'thumb_url' => $ph_dir . 'tile-sintered-emperador.svg']);
                drv_ui('sub-silo-tile', ['name' => 'Vân đá Marble', 'count' => 54, 'url' => MegaMenuRenderer::collection_url('gach-van-da'), 'thumb_url' => $ph_dir . 'tile-marble-calacatta.svg']);
                drv_ui('sub-silo-tile', ['name' => 'Gạch Terrazzo', 'count' => 26, 'url' => MegaMenuRenderer::collection_url('gach-terrazzo'), 'thumb_url' => $ph_dir . 'tile-terrazzo-ivory.svg']);
                drv_ui('sub-silo-tile', ['name' => 'Gạch giả gỗ', 'count' => 38, 'url' => MegaMenuRenderer::collection_url('gach-gia-go'), 'thumb_url' => $ph_dir . 'tile-wood-oak.svg']);
                ?>
            </div>

            <div id="drv-uikit-card-grid" class="l-grid l-grid--4" data-drv-stagger>
                <?php
                drv_ui('product-card', [
                    'priority_load' => true,
                    'product'       => [
                        'id'               => 101,
                        'sku'              => 'DRV-8801M',
                        'title'            => 'Gạch Lát Nền Dravit Vân Đá Marble Calacatta Trắng Khói 80x80 Bóng Kiếng',
                        'url'              => MegaMenuRenderer::collection_url('gach-80x80'),
                        'price_m2'         => 385000,
                        'regular_price_m2' => 450000,
                        'price_piece'      => 246400,
                        'pieces_per_box'   => 3,
                        'm2_per_box'       => 1.92,
                        'stock_boxes'      => 46,
                        'showroom_shelf'   => 'A-04',
                        'img_primary'      => $ph_dir . 'tile-marble-calacatta.svg',
                        'img_hover'        => $ph_dir . 'room-living-calacatta.svg',
                        'tags'             => ['80x80', 'Bóng kiếng'],
                    ],
                ]);

                drv_ui('product-card', [
                    'product' => [
                        'id'             => 102,
                        'sku'            => 'DRV-TRZ80',
                        'title'          => 'Gạch Lát Nền Dravit Terrazzo Kem Đá Hạt Nhuyễn 80x80 Mờ Mịn R10',
                        'url'            => MegaMenuRenderer::collection_url('gach-terrazzo'),
                        'price_m2'       => 395000,
                        'price_piece'    => 252800,
                        'pieces_per_box' => 3,
                        'm2_per_box'     => 1.92,
                        'stock_boxes'    => 43,
                        'img_primary'    => $ph_dir . 'tile-terrazzo-ivory.svg',
                        'img_hover'      => $ph_dir . 'room-bathroom-terrazzo.svg',
                        'tags'           => ['80x80', 'Matt R10'],
                    ],
                ]);

                drv_ui('product-card', [
                    'product' => [
                        'id'               => 103,
                        'sku'              => 'DRV-6604C',
                        'title'            => 'Gạch Lát Nền Dravit Vân Xi Măng Xám Nhạt 60x60 Chống Trượt R10',
                        'url'              => MegaMenuRenderer::collection_url('gach-van-xi-mang'),
                        'price_m2'         => 215000,
                        'regular_price_m2' => 300000,
                        'price_piece'      => 77400,
                        'pieces_per_box'   => 4,
                        'm2_per_box'       => 1.44,
                        'stock_boxes'      => 16,
                        'card_label'       => 'Lẻ lô',
                        'img_primary'      => $ph_dir . 'tile-cement-grey.svg',
                        'img_hover'        => $ph_dir . 'room-kitchen-cement.svg',
                        'tags'             => ['60x60', 'Matt R10'],
                    ],
                ]);

                drv_ui('product-card', [
                    'product' => [
                        'id'             => 104,
                        'sku'            => 'DRV-SL1224',
                        'title'          => 'Đá Nung Kết Khổ Lớn Dravit Armani Dark 120x240 Bề Mặt Carving Tinh Xảo',
                        'url'            => MegaMenuRenderer::collection_url('da-nung-ket'),
                        'price_m2'       => 0,
                        'pieces_per_box' => 1,
                        'm2_per_box'     => 2.88,
                        'card_label'     => 'Big Slab',
                        'img_primary'    => $ph_dir . 'tile-sintered-emperador.svg',
                        'img_hover'      => $ph_dir . 'room-living-calacatta.svg',
                        'tags'           => ['120x240', 'Carving R10'],
                    ],
                ]);
                ?>
            </div>

            <?php
            drv_ui('load-more', [
                'shown'    => 24,
                'total'    => 96,
                'per_page' => 24,
                'next_url' => MegaMenuRenderer::internal_url('/gach/page/2'),
            ]);
            ?>
        </div>
    </section>

    <section id="sec-calculators" class="l-section">
        <div class="l-container l-stack l-stack--lg">
            <?php
            drv_ui('section-head', [
                'eyebrow' => '05. Interactive Commerce Organisms',
                'title'   => 'Bộ chuyển đổi <drv-unit-calc>, <drv-qty-input> & Thanh Shop the Look',
                'desc'    => 'Light DOM Custom Elements tự động quy đổi m² - viên - thùng - kg tức thì theo cấu hình làm tròn của từng sản phẩm.',
            ]);
            ?>

            <div class="l-split l-split--7-5">
                <div class="l-stack l-stack--md">
                    <?php
                    drv_ui('shop-the-look', [
                        'room_img'   => $ph_dir . 'room-living-calacatta.svg',
                        'room_alt'   => 'Phối cảnh phòng khách lát gạch vân đá Calacatta 80x80',
                        'sku'        => 'DRV-8801M',
                        'title'      => 'Gạch Lát Nền Dravit Marble Calacatta 80x80',
                        'price_m2'   => 385000,
                        'url'        => MegaMenuRenderer::collection_url('gach-80x80'),
                        'swatch_url' => $ph_dir . 'tile-marble-calacatta.svg',
                    ]);
                    ?>

                    <div class="u-bd u-rounded-md u-pad-4 l-cluster l-cluster--between l-section--white">
                        <div class="l-stack l-stack--xs">
                            <span class="u-text-sm u-bold">Bộ tăng giảm giỏ hàng &lt;drv-qty-input&gt; (chế độ mua lẻ viên + nút +1 thùng):</span>
                            <span class="u-text-xs u-text-muted">Tự động cập nhật số thùng, m² và trọng lượng.</span>
                        </div>
                        <?php
                        drv_ui('qty-input', [
                            'value_pieces'   => 18,
                            'price_unit'     => 246400,
                            'area_piece'     => 0.64,
                            'pieces_per_box' => 3,
                            'weight_piece'   => 14,
                            'round_mode'     => 'piece',
                            'aria_label'     => 'Số viên DRV-8801M',
                        ]);
                        ?>
                    </div>
                </div>

                <div class="l-stack l-stack--sm">
                    <?php
                    drv_ui('unit-calc', [
                        'id'              => 8801,
                        'sku'             => 'DRV-8801M',
                        'price_m2'        => 385000,
                        'piece_w'         => 800,
                        'piece_h'         => 800,
                        'pieces_per_box'  => 3,
                        'weight_per_box'  => 42,
                        'round_mode'      => 'box',
                        'default_area_m2' => 10,
                    ]);
                    ?>
                </div>
            </div>
        </div>
    </section>

    <section id="sec-tables-geo" class="l-section l-section--white">
        <div class="l-container">
            <?php
            drv_ui('section-head', [
                'eyebrow' => '06. Structured Data & GEO Components',
                'title'   => 'Bảng Thông số Kỹ thuật (IL-03), Hộp Key Takeaways GEO & FAQ Accordion',
            ]);
            ?>

            <div class="l-split l-split--7-5">
                <div class="l-stack l-stack--sm">
                    <?php
                    drv_ui('spec-table', [
                        'sku'  => 'DRV-8801M',
                        'rows' => [
                            ['label' => 'Công năng sử dụng', 'value_html' => '<a href="' . esc_url(MegaMenuRenderer::collection_url('gach-lat-nen')) . '">Gạch lát nền</a>, <a href="' . esc_url(MegaMenuRenderer::collection_url('gach-mat-tien')) . '">Gạch ốp mặt tiền</a>'],
                            ['label' => 'Không gian phù hợp', 'value_html' => '<a href="' . esc_url(MegaMenuRenderer::collection_url('gach-phong-khach')) . '">Gạch phòng khách</a>, Sảnh lớn, Phòng ngủ'],
                            ['label' => 'Kích thước danh định', 'value_html' => '<a href="' . esc_url(MegaMenuRenderer::collection_url('gach-80x80')) . '">Gạch 80x80 cm</a> (Thực tế: 800 x 800 x 10 mm)'],
                            ['label' => 'Xương gạch & Độ hút nước', 'value' => 'Xương đá Porcelain · Độ hút nước E ≤ 0,5%'],
                            ['label' => 'Bề mặt & Chống trượt', 'value' => 'Bóng kiếng toàn phần (Polished) · Chỉ số R9 · Độ cứng PEI IV'],
                            ['label' => 'Quy cách đóng thùng', 'value' => '3 viên/thùng = 1,92 m² · Khối lượng: 42 kg/thùng'],
                        ],
                    ]);
                    ?>
                </div>

                <div class="l-stack l-stack--md">
                    <?php
                    drv_ui('geo-table', [
                        'title'   => 'Những điểm chính (Key Takeaways - GEO Box)',
                        'badge'   => 'Kiểm duyệt kỹ thuật',
                        'bullets' => [
                            'Gạch khổ <a href="' . esc_url(MegaMenuRenderer::collection_url('gach-80x80')) . '">Gạch 80x80</a> giúp sàn phòng khách từ 20 m² giảm 35% đường ron so với khổ 60x60.',
                            'Khu vực nhà tắm và sân phơi ưu tiên bề mặt mờ nhám R10 - R11 thuộc dòng <a href="' . esc_url(MegaMenuRenderer::collection_url('gach-terrazzo')) . '">Gạch Terrazzo</a>.',
                            'Cạnh mài phẳng (Rectified) đi ke mạch ron 1,5 - 2 mm giúp mặt sàn liền lạc và dễ vệ sinh.',
                        ],
                        'headers' => ['Khu vực ốp lát', 'Độ nhám khuyến nghị', 'Khổ gạch tối ưu'],
                        'rows'    => [
                            ['Phòng khách (> 20 m²)', 'R9 (Bóng / Satin)', '80x80 hoặc 60x120 cm'],
                            ['Nhà tắm / Khu ướt', 'R10 - R11 (Matt)', '30x60 hoặc 60x60 cm'],
                            ['Sân vườn / Gara ô tô', 'R11 - R12 (Dày 20mm)', '60x60 cm chịu lực'],
                        ],
                    ]);

                    drv_ui('faq', [
                        'items' => [
                            [
                                'q' => 'Vì sao trên web hiển thị giá theo m² nhưng giỏ hàng tính theo viên?',
                                'a' => 'Giá niêm yết theo <strong>đ/m²</strong> giúp gia chủ và nhà thầu dễ so sánh mặt bằng giá, còn khi xuất kho thực tế gạch giao dịch theo <strong>số nguyên viên</strong> (hoặc chẵn thùng). Bộ tính tự động quy đổi chính xác cả hai đơn vị.',
                            ],
                            [
                                'q' => 'Vì sao phí vận chuyển được báo sau khi xác nhận đơn hàng?',
                                'a' => 'Gạch ốp lát có trọng lượng trung bình 22 - 30 kg/m². Điều vận viên DRAVIT kiểm tra lộ trình xe tải/hẻm công trình để báo mức cước tối ưu nhất trước khi khách xác nhận thanh toán.',
                            ],
                        ],
                    ]);
                    ?>
                </div>
            </div>
        </div>
    </section>

    <section id="sec-motion" class="l-section">
        <div class="l-container l-stack l-stack--lg">
            <?php
            drv_ui('section-head', [
                'eyebrow'       => '07. Motion System & @layer plugins (Fluent Forms Skin)',
                'title'         => 'Vi tương tác CSS GPU + Anime.js v3.2.2 & Tích hợp Fluent Forms',
                'badge_text'    => '60fps · CLS = 0 · Reduced-Motion Safe',
                'badge_variant' => 'moss',
            ]);
            ?>

            <div class="drv-motion-grid">
                <div class="drv-motion-card">
                    <div class="drv-motion-card__head">
                        <div class="l-stack l-stack--xs">
                            <span class="u-text-xs u-bold u-uppercase u-text-moss">Option A · 0KB JS</span>
                            <h3 class="u-text-base">CSS GPU Hover &amp; Tactile Press</h3>
                        </div>
                        <?php drv_ui('badge', ['text' => 'Compositor Only', 'variant' => 'outline']); ?>
                    </div>
                    <p class="u-text-xs u-text-muted">Chuyển động chạy hoàn toàn bằng <code>transform</code> và <code>opacity</code> trên luồng GPU.</p>
                    <div class="drv-motion-stage">
                        <div class="l-cluster l-cluster--sm">
                            <?php
                            drv_ui('btn', ['label' => 'Hover trượt mũi tên', 'size' => 'sm', 'icon_right' => 'arrow-right']);
                            drv_ui('btn', ['label' => 'Nhấn thử (Scale 0.98)', 'variant' => 'moss', 'size' => 'sm', 'icon_left' => 'shopping-bag', 'action' => 'add-to-cart', 'attrs' => ['data-drv-sku' => 'DRV-8801M']]);
                            ?>
                        </div>
                        <div class="l-cluster l-cluster--between">
                            <a href="#sec-cards" class="drv-link-draw u-text-xs">
                                <span>Hiệu ứng vẽ đường gạch chân (scaleX)</span>
                            </a>
                            <?php drv_ui('copy-inline', ['value' => 'DRV-8801M', 'label' => 'Chép DRV-8801M']); ?>
                        </div>
                    </div>
                </div>

                <div class="drv-motion-card">
                    <div class="drv-motion-card__head">
                        <div class="l-stack l-stack--xs">
                            <span class="u-text-xs u-bold u-uppercase u-text-moss">Option B · Anime.js Stagger</span>
                            <h3 class="u-text-base">Staggered Tile Grid Reveal</h3>
                        </div>
                        <?php
                        drv_ui('btn', [
                            'label'     => 'Phát lại',
                            'variant'   => 'soft',
                            'size'      => 'sm',
                            'icon_left' => 'arrows-clockwise',
                            'action'    => 'motion-replay-stagger',
                            'target'    => '#drv-stagger-demo-grid',
                        ]);
                        ?>
                    </div>
                    <div class="drv-motion-stage">
                        <div id="drv-stagger-demo-grid" class="drv-stagger-demo" data-drv-stagger>
                            <div class="drv-stagger-tile">80x80<span>Viên #01</span></div>
                            <div class="drv-stagger-tile">60x120<span>Viên #02</span></div>
                            <div class="drv-stagger-tile">120x240<span>Viên #03</span></div>
                            <div class="drv-stagger-tile">60x60<span>Viên #04</span></div>
                            <div class="drv-stagger-tile">30x60<span>Viên #05</span></div>
                            <div class="drv-stagger-tile">15x90<span>Viên #06</span></div>
                        </div>
                    </div>
                </div>

                <div class="drv-motion-card">
                    <div class="drv-motion-card__head">
                        <div class="l-stack l-stack--xs">
                            <span class="u-text-xs u-bold u-uppercase u-text-moss">Option B · Odometer &amp; Spring</span>
                            <h3 class="u-text-base">Đếm số Odometer &amp; Nảy giỏ hàng</h3>
                        </div>
                        <span id="drv-demo-cart-badge" class="drv-badge drv-badge--moss u-tabular">Giỏ demo</span>
                    </div>
                    <div class="drv-motion-stage">
                        <div class="drv-odometer-grid">
                            <div class="drv-odometer-box">
                                <div class="drv-odometer-box__label">Diện tích thực</div>
                                <div id="drv-odo-area" class="drv-odometer-box__val" data-drv-val="38.4">38,4 m²</div>
                            </div>
                            <div class="drv-odometer-box">
                                <div class="drv-odometer-box__label">Quy đổi xuất kho</div>
                                <div class="drv-odometer-box__val">
                                    <span id="drv-odo-pieces" data-drv-val="60">60 viên</span> · <span id="drv-odo-boxes" class="u-text-moss" data-drv-val="20">20 thùng</span>
                                </div>
                            </div>
                        </div>
                        <div class="u-text-xs u-text-muted">Tạm tính: <strong id="drv-odo-total" class="u-text-sale u-tabular" data-drv-val="14784000">14.784.000đ</strong></div>
                    </div>
                    <div class="l-cluster l-cluster--xs">
                        <button type="button" class="drv-chip" data-drv-action="motion-demo-odometer" data-drv-area="18">Phòng ngủ 18m²</button>
                        <button type="button" class="drv-chip is-active" data-drv-action="motion-demo-odometer" data-drv-area="36">Phòng khách 36m²</button>
                        <button type="button" class="drv-chip" data-drv-action="motion-demo-odometer" data-drv-area="85">Biệt thự 85m²</button>
                    </div>
                </div>
            </div>

            <div class="u-bd u-rounded-md u-pad-5 l-section--white l-stack l-stack--md">
                <div class="l-cluster l-cluster--between">
                    <div class="l-stack l-stack--xs">
                        <span class="u-text-xs u-bold u-uppercase u-text-moss">@layer plugins · Fluent Forms Skin (ADR D-31)</span>
                        <h3 class="u-text-base">Đồng bộ giao diện Fluent Forms theo Design Tokens DRAVIT (0 !important)</h3>
                    </div>
                    <?php drv_ui('badge', ['text' => drv_is_fluent_forms_active() ? 'Fluent Forms Active' : 'Skin Ready', 'variant' => 'showroom']); ?>
                </div>
                <?php if (!FluentFormsBridge::render_slot('contact_page')) : ?>
                    <form class="fluentform frm-fluent-form" onsubmit="return false;">
                        <div class="ff-t-container">
                            <div class="ff-t-cell">
                                <div class="ff-el-group">
                                    <div class="ff-el-input--label"><label for="ff-demo-name">Họ tên chủ đầu tư / KTS</label></div>
                                    <div class="ff-el-input--content"><input id="ff-demo-name" type="text" class="ff-el-form-control" value="KTS. Trần Quốc Bảo"></div>
                                </div>
                            </div>
                            <div class="ff-t-cell">
                                <div class="ff-el-group">
                                    <div class="ff-el-input--label"><label for="ff-demo-phone">Số điện thoại nhận báo giá</label></div>
                                    <div class="ff-el-input--content"><input id="ff-demo-phone" type="tel" class="ff-el-form-control" placeholder="0909 xxx xxx"></div>
                                </div>
                            </div>
                        </div>
                        <div class="ff-el-group">
                            <button type="button" class="ff-btn ff-btn-submit">Gửi qua Fluent Forms Skin</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>
<?php
drv_ui('dialog', [
    'id'        => 'drv-uikit-sample-dialog',
    'eyebrow'   => 'Kiểm thử Primitive drv_ui(dialog)',
    'title'     => 'Hộp thoại chuẩn Accessibility WCAG 2.2 AA',
    'body_html' => '<p>Nhấn phím <strong>Escape</strong> hoặc bấm ra vùng nền tối bên ngoài để đóng hộp thoại và trả con trỏ focus về đúng nút vừa mở.</p>',
]);

get_footer();

