<?php
/**
 * Section: Bảng dự toán BOQ đa phòng (`04.1-quote-cart.html`, docs/17 §2–§6, D-23).
 * SSR khung tĩnh an toàn LiteSpeed Cache; `assets/src/js/quote.js` dựng phòng/hạng mục từ QuoteStore.
 *
 * @var array<string, mixed> $args
 */
declare(strict_types=1);

use Dravit\Leads\LeadValidator;
use Dravit\Units\Calc;

defined('ABSPATH') || exit;

$title         = (string) ($args['title'] ?? 'Bảng báo giá gạch theo từng phòng');
$roles         = array_values(array_map('strval', (array) drv_setting('quote.roles', [])));
$default_rooms = array_values(array_map('strval', (array) drv_setting('quote.default_rooms', [])));
$waste_options = array_values(array_map('intval', (array) drv_setting('order.waste_options', [0, 5, 10])));
$waste_default = (int) drv_setting('order.default_waste_pct', 5);
$valid_days    = (int) drv_setting('quote.valid_days', 7);
$hotline       = (string) drv_setting('contact.hotline', '0909 888 777');
$hotline_tel   = drv_clean_phone($hotline);
$zalo          = drv_clean_phone((string) drv_setting('contact.zalo', $hotline_tel));
$provinces     = (array) require DRV_DIR . '/config/provinces-vn.php';
$gach_url      = untrailingslashit(home_url('/gach'));
$cart_url      = function_exists('wc_get_cart_url') ? untrailingslashit(wc_get_cart_url()) : untrailingslashit(home_url('/gio-hang'));
$privacy_url   = untrailingslashit(home_url('/chinh-sach-bao-mat'));
$today         = wp_date('d/m/Y');

$tiers = [];
foreach ((array) drv_setting('quote.volume_tiers', []) as $tier) {
    $tiers[] = ['min_m2' => (float) ($tier['min_m2'] ?? 0), 'rate' => (float) ($tier['discount_pct'] ?? $tier['rate'] ?? 0)];
}

$size_terms = get_terms([
    'taxonomy'   => 'pa_kich-thuoc',
    'hide_empty' => true,
    'orderby'    => 'count',
    'order'      => 'DESC',
    'number'     => 8,
]);
$size_terms = is_array($size_terms) ? $size_terms : [];

$space_terms = get_terms([
    'taxonomy'   => 'pa_khong-gian',
    'hide_empty' => true,
    'orderby'    => 'count',
    'order'      => 'DESC',
    'number'     => 20,
]);
$space_terms = is_array($space_terms) ? $space_terms : [];

$pattern_terms = get_terms([
    'taxonomy'   => 'pa_van-gach',
    'hide_empty' => true,
    'orderby'    => 'count',
    'order'      => 'DESC',
    'number'     => 20,
]);
$pattern_terms = is_array($pattern_terms) ? $pattern_terms : [];

$surface_terms = get_terms([
    'taxonomy'   => 'pa_be-mat',
    'hide_empty' => true,
    'orderby'    => 'count',
    'order'      => 'DESC',
    'number'     => 10,
]);
$surface_terms = is_array($surface_terms) ? $surface_terms : [];

$config = [
    'roles'         => $roles,
    'defaultRooms'  => $default_rooms,
    'wasteOptions'  => $waste_options,
    'wasteDefault'  => $waste_default,
    'tiers'         => $tiers !== [] ? $tiers : Calc::DEFAULT_VOLUME_TIERS,
    'vehicles'      => Calc::DEFAULT_VEHICLE_THRESHOLDS,
    'maxItems'      => (int) drv_setting('quote.max_items', 30),
    'maxRooms'      => (int) drv_setting('quote.max_rooms', 12),
    'validDays'     => $valid_days,
    'cartUrl'       => $cart_url,
    'gachUrl'       => $gach_url,
    'hotline'       => $hotline,
    'zalo'          => $zalo,
    'brand'         => (string) drv_setting('company.brand_name', 'DRAVIT'),
    'legalName'     => (string) drv_setting('company.legal_name', ''),
    'address'       => (string) drv_setting('company.address', ''),
    'siteHost'      => (string) wp_parse_url(home_url('/'), PHP_URL_HOST),
];

$breadcrumb = [
    ['label' => 'Trang chủ', 'url' => home_url('/')],
    ['label' => 'Gạch ốp lát', 'url' => $gach_url],
    ['label' => 'Bảng báo giá dự toán', 'url' => ''],
];
?>
<script type="application/json" id="drv-boq-config"><?php echo wp_json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>

<drv-boq class="drv-boq" data-drv-boq>
    <!-- 1. BREADCRUMB · TIÊU ĐỀ · XUẤT FILE · META PHIẾU -->
    <section class="l-section l-section--white u-bd-b drv-no-print" style="padding-block: var(--s-4)">
        <div class="l-container l-stack l-stack--sm">
            <div class="l-cluster l-cluster--between">
                <?php drv_ui('breadcrumb', ['items' => $breadcrumb]); ?>
                <div class="l-cluster l-cluster--xs">
                    <a href="<?php echo esc_url($cart_url); ?>" class="drv-chip">
                        <i class="ph ph-shopping-bag drv-icon--xs" aria-hidden="true"></i>
                        <span>Giỏ hàng đặt lẻ</span>
                    </a>
                    <span class="drv-chip is-active" aria-current="page">
                        <i class="ph-fill ph-files drv-icon--xs" aria-hidden="true"></i>
                        <span>Báo giá theo phòng</span>
                    </span>
                </div>
            </div>

            <div class="drv-boq-head">
                <h1><?php echo esc_html(mb_strtoupper($title)); ?></h1>
                <div class="drv-boq-tools" role="group" aria-label="Xuất và chia sẻ bảng dự toán">
                    <button type="button" class="drv-btn drv-btn--outline drv-btn--sm" data-qs-action="pdf" data-qs-needs-items disabled>
                        <i class="ph-bold ph-file-pdf drv-icon--xs" aria-hidden="true"></i>
                        <span>In / Lưu PDF (A4)</span>
                    </button>
                    <button type="button" class="drv-btn drv-btn--outline drv-btn--sm" data-qs-action="csv" data-qs-needs-items disabled>
                        <i class="ph-bold ph-file-xls drv-icon--xs" aria-hidden="true"></i>
                        <span>Tải file CSV (Excel)</span>
                    </button>
                    <button type="button" class="drv-btn drv-btn--outline drv-btn--sm" data-qs-action="share" data-qs-needs-items disabled>
                        <i class="ph-bold ph-link drv-icon--xs" aria-hidden="true"></i>
                        <span>Sao chép link chia sẻ</span>
                    </button>
                </div>
            </div>

            <div class="drv-meta-strip" role="region" aria-label="Thông tin phiếu báo giá">
                <div class="drv-meta-strip__item">
                    <span class="drv-meta-strip__label">Mã phiếu</span>
                    <strong class="drv-meta-strip__val u-tabular" data-qs-code>Bản nháp</strong>
                </div>
                <div class="drv-meta-strip__item">
                    <span class="drv-meta-strip__label">Ngày lập</span>
                    <strong class="drv-meta-strip__val u-tabular" data-qs-date><?php echo esc_html($today); ?></strong>
                </div>
                <div class="drv-meta-strip__item">
                    <span class="drv-meta-strip__label">Hiệu lực</span>
                    <strong class="drv-meta-strip__val u-tabular"><?php echo esc_html(sprintf('%02d ngày', $valid_days)); ?></strong>
                </div>
                <div class="drv-meta-strip__item drv-meta-strip__project">
                    <label for="qs-project-name" class="drv-meta-strip__label">Công trình:</label>
                    <input id="qs-project-name" type="text" class="drv-input drv-input--sm" maxlength="120" placeholder="VD: Nhà phố 3 tầng Thủ Đức" data-qs-project autocomplete="off">
                </div>
                <div class="drv-meta-strip__item drv-meta-strip__item--end">
                    <label for="qs-waste-select" class="drv-meta-strip__label">Hao hụt cắt gạch:</label>
                    <select id="qs-waste-select" class="drv-select drv-input--sm" data-qs-waste-select>
                        <?php foreach ($waste_options as $opt) :
                            $hint = $opt === 0 ? 'chuẩn diện tích' : ($opt === $waste_default ? 'khuyên dùng' : 'ốp chéo / phức tạp');
                            ?>
                            <option value="<?php echo esc_attr((string) $opt); ?>" <?php selected($opt, $waste_default); ?>><?php echo esc_html(sprintf('%d%% (%s)', $opt, $hint)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </section>

    <!-- 2. PHÒNG / HẠNG MỤC · TỔNG HỢP · CTA -->
    <section class="l-section drv-no-print" aria-label="Bảng kê từng phòng" style="padding-top: var(--s-5)">
        <div class="l-container l-stack l-stack--lg">

            <div class="drv-boq-empty" data-qs-empty>
                <i class="ph-fill ph-stack" aria-hidden="true"></i>
                <h2 class="drv-boq-empty__title">Bảng dự toán đang trống</h2>
                <p class="drv-boq-empty__sub">Chọn nhanh khu vực cần ốp lát, sau đó thêm mẫu gạch và nhập diện tích m². Hệ thống tự quy đổi ra số thùng chẵn, tổng kg và chiết khấu dự kiến.</p>
                <div class="drv-boq-empty__chips" role="group" aria-label="Tạo nhanh khu vực">
                    <?php foreach ($default_rooms as $room_name) : ?>
                        <button type="button" class="drv-chip drv-chip--square" data-qs-add-preset="<?php echo esc_attr($room_name); ?>">
                            <i class="ph ph-plus drv-icon--xs" aria-hidden="true"></i><span><?php echo esc_html($room_name); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="l-cluster l-cluster--sm" style="justify-content: center">
                    <button type="button" class="drv-btn drv-btn--moss" data-qs-action="open-picker">
                        <i class="ph-bold ph-squares-four drv-icon--xs" aria-hidden="true"></i>
                        <span>Chọn mẫu gạch từ danh mục</span>
                    </button>
                    <a href="<?php echo esc_url($gach_url); ?>" class="drv-link u-text-sm u-bold">Khám phá 28 danh mục gạch</a>
                </div>
                <noscript><p class="u-text-xs u-text-muted">Bảng dự toán cần bật JavaScript. Bạn vẫn có thể gọi <?php echo esc_html($hotline); ?> để được bóc tách khối lượng miễn phí.</p></noscript>
            </div>

            <div id="qs-rooms-container" class="l-stack l-stack--md" data-qs-rooms aria-live="polite"></div>

            <div class="drv-add-room-card" data-qs-add-room hidden>
                <div class="drv-add-room-card__head">
                    <i class="ph-fill ph-plus-circle drv-icon u-text-moss" aria-hidden="true"></i>
                    <div>
                        <strong class="u-text-sm u-text-navy">Thêm không gian khác vào dự toán</strong>
                        <span class="u-text-xs u-text-muted" style="display: block">Chọn nhanh phòng gợi ý hoặc tự nhập tên khu vực (tối đa <?php echo (int) $config['maxRooms']; ?> phòng)</span>
                    </div>
                </div>
                <div class="drv-add-room-card__body">
                    <div class="drv-add-room-card__presets" role="group" aria-label="Phòng gợi ý">
                        <span class="drv-add-room-card__label">Gợi ý</span>
                        <?php foreach ($default_rooms as $room_name) : ?>
                            <button type="button" class="drv-chip drv-chip--square" data-qs-add-preset="<?php echo esc_attr($room_name); ?>">
                                <i class="ph ph-plus drv-icon--xs" aria-hidden="true"></i><span><?php echo esc_html($room_name); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <form class="drv-add-room-card__form" data-qs-custom-room-form>
                        <div class="drv-add-room-card__field">
                            <label for="qs-custom-room-name" class="u-sr-only">Tên phòng tùy chỉnh</label>
                            <input id="qs-custom-room-name" type="text" class="drv-input drv-input--sm" maxlength="60" placeholder="Tên khu vực khác, VD: Tầng lửng" data-qs-custom-room-input required>
                            <button type="submit" class="drv-btn drv-btn--navy drv-btn--sm">
                                <i class="ph-bold ph-plus drv-icon--xs" aria-hidden="true"></i>
                                <span>Thêm phòng</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="drv-totals-card" data-qs-totals hidden>
                <div class="drv-totals-card__metrics">
                    <div class="drv-totals-card__metric">
                        <span class="drv-totals-card__label">Tổng diện tích thực xuất</span>
                        <strong class="drv-totals-card__val u-text-moss u-tabular" data-qs-total-m2>0 m²</strong>
                    </div>
                    <div class="drv-totals-card__metric">
                        <span class="drv-totals-card__label">Quy cách đóng gói</span>
                        <strong class="drv-totals-card__val u-tabular" data-qs-total-boxes>0 thùng</strong>
                    </div>
                    <div class="drv-totals-card__metric">
                        <span class="drv-totals-card__label">Trọng lượng dự kiến</span>
                        <strong class="drv-totals-card__val u-tabular" data-qs-total-kg>0 kg</strong>
                    </div>
                    <div class="drv-totals-card__metric">
                        <span class="drv-totals-card__label" data-qs-subtotal-label>Tạm tính niêm yết</span>
                        <strong class="drv-totals-card__val u-tabular" data-qs-subtotal>0đ</strong>
                    </div>
                    <div class="drv-totals-card__metric">
                        <span class="drv-totals-card__label">Chiết khấu dự kiến (<span data-qs-rate>0%</span>)</span>
                        <strong class="drv-totals-card__val drv-totals-card__val--sale u-tabular" data-qs-discount>0đ</strong>
                    </div>
                    <div class="drv-totals-card__metric drv-totals-card__metric--grand">
                        <span class="drv-totals-card__label">Tổng dự toán tạm tính</span>
                        <strong class="drv-totals-card__val u-tabular" data-qs-grand>0đ</strong>
                    </div>
                </div>

                <p class="drv-qs-quote-note" data-qs-quote-note hidden>
                    <i class="ph-fill ph-tag" aria-hidden="true"></i>
                    <span data-qs-quote-note-text></span>
                </p>

                <div class="drv-totals-card__tier-progress">
                    <div class="l-cluster l-cluster--between u-text-xs">
                        <span data-qs-tier-status>Thêm mẫu gạch để tính chiết khấu dự kiến.</span>
                        <span class="u-text-muted">Chiết khấu dự kiến - chuyên viên xác nhận theo lô</span>
                    </div>
                </div>
                <div class="drv-qs-foot-notes">
                    <span>Chưa bao gồm phí vận chuyển &amp; hạ hàng tại công trình. Đơn giá đã gồm VAT.</span>
                </div>
            </div>

            <div class="drv-quote-cta" data-qs-cta hidden>
                <div class="drv-quote-cta__text">
                    <h2 class="drv-quote-cta__title">Nhận báo giá sỉ &amp; xác nhận tồn kho cùng lô</h2>
                    <p class="drv-quote-cta__sub">Chuyên viên DRAVIT kiểm tra tồn kho mã lô (Shade), gửi báo giá sỉ kèm cước vận chuyển tận công trình qua Zalo. <?php echo esc_html((string) drv_setting('order.sla_text', '')); ?></p>
                </div>
                <div class="drv-quote-cta__action">
                    <button type="button" class="drv-btn drv-btn--moss drv-btn--lg" data-qs-action="open-lead">
                        <i class="ph-bold ph-paper-plane-tilt drv-icon" aria-hidden="true"></i>
                        <span>GỬI YÊU CẦU BÁO GIÁ CÔNG TRÌNH</span>
                    </button>
                    <div class="drv-quote-cta__secondary">
                        <button type="button" class="drv-btn drv-btn--outline drv-btn--sm" data-qs-action="to-cart" data-qs-to-cart hidden>
                            <i class="ph-bold ph-shopping-cart-simple drv-icon--xs" aria-hidden="true"></i>
                            <span>Đặt mua ngay các mẫu có giá</span>
                        </button>
                        <button type="button" class="drv-btn drv-btn--ghost drv-btn--sm" data-qs-action="copy-zalo">
                            <i class="ph ph-copy drv-icon--xs" aria-hidden="true"></i>
                            <span>Sao chép gửi Zalo</span>
                        </button>
                    </div>
                    <div class="l-cluster l-cluster--sm" style="justify-content: center; margin-top: var(--s-2)">
                        <a href="<?php echo esc_url('tel:' . $hotline_tel); ?>" class="drv-link u-text-xs u-text-navy u-bold">
                            <i class="ph-fill ph-phone-call drv-icon--xs" aria-hidden="true"></i>
                            <span>Hotline: <?php echo esc_html($hotline); ?></span>
                        </a>
                        <span class="u-text-muted" aria-hidden="true">·</span>
                        <button type="button" class="drv-btn-text u-text-xs u-text-muted" data-qs-action="clear">Xóa bảng nháp</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. BẢN IN A4 (chỉ hiện khi in / lưu PDF) -->
    <section class="drv-boq-print" aria-hidden="true">
        <div class="drv-boq-print__head">
            <div>
                <strong class="drv-boq-print__brand"><?php echo esc_html($config['brand'] . ' - GẠCH ỐP LÁT'); ?></strong>
                <span><?php echo esc_html($config['legalName']); ?></span><br>
                <span><?php echo esc_html($config['address']); ?></span><br>
                <span><?php echo esc_html($config['siteHost'] . ' · Hotline / Zalo: ' . $hotline); ?></span>
            </div>
            <div style="text-align: right">
                <strong class="drv-boq-print__title">BẢNG DỰ TOÁN KHỐI LƯỢNG GẠCH ỐP LÁT</strong>
                <span>Mã phiếu: <strong data-qs-print-code>Bản nháp</strong></span><br>
                <span>Ngày lập: <strong data-qs-print-date><?php echo esc_html($today); ?></strong> · Hiệu lực <?php echo (int) $valid_days; ?> ngày</span>
            </div>
        </div>
        <div class="drv-boq-print__project">
            <span><strong>Công trình:</strong> <span data-qs-print-project>-</span></span>
            <span><strong>Hao hụt đã cộng:</strong> <span data-qs-print-waste>5%</span></span>
        </div>
        <div data-qs-print-body></div>
        <div class="drv-boq-print__totals">
            <div>
                Tổng diện tích thực xuất: <strong data-qs-print-m2>0 m²</strong><br>
                Đóng gói: <strong data-qs-print-boxes>0 thùng</strong><br>
                Trọng lượng: <strong data-qs-print-kg>0 kg</strong>
            </div>
            <div style="text-align: right">
                <div>Tạm tính các mẫu có giá: <strong data-qs-print-subtotal>0đ</strong></div>
                <div>Chiết khấu dự kiến (<span data-qs-print-rate>0%</span>): <strong data-qs-print-discount>0đ</strong></div>
                <div class="drv-boq-print__grand"><strong>TỔNG DỰ TOÁN TẠM TÍNH: <span data-qs-print-grand>0đ</span></strong></div>
                <small data-qs-print-quote-note></small>
            </div>
        </div>
        <p class="drv-boq-print__note">Bảng dự toán phục vụ bóc tách khối lượng tham khảo. Đơn giá thực tế, phí vận chuyển và mã lô xuất kho được xác nhận chính thức khi chốt đơn (hiệu lực báo giá: <?php echo esc_html(sprintf('%02d', $valid_days)); ?> ngày).</p>
        <div class="drv-boq-print__signatures">
            <div><strong>KHÁCH HÀNG</strong><span>(Ký, ghi rõ họ tên)</span></div>
            <div><strong><?php echo esc_html($config['brand']); ?></strong><span>Cam kết xuất kho cùng 1 lô màu (Shade / Caliber)</span></div>
        </div>
    </section>

    <!-- MODAL 1: CHỌN MẪU GẠCH (hydrate `GET /dravit/v1/products`) -->
    <dialog id="qs-picker-dialog" class="drv-dialog drv-picker-modal" aria-labelledby="qs-picker-title">
        <div class="drv-dialog__head">
            <div class="l-stack l-stack--2xs">
                <span class="u-text-xs u-bold u-uppercase u-text-moss">Duyệt danh mục gạch <?php echo esc_html($config['brand']); ?></span>
                <h2 id="qs-picker-title" class="u-text-lg" style="margin: 0">Chọn gạch cho: <span class="u-text-moss" data-picker-target-name>Hạng mục chung</span></h2>
            </div>
            <button type="button" class="drv-btn drv-btn--sm drv-btn--ghost drv-btn--icon" data-drv-action="dialog-close" aria-label="Đóng">
                <i class="ph ph-x drv-icon" aria-hidden="true"></i>
            </button>
        </div>
        <div class="drv-dialog__body l-stack l-stack--sm">
            <div class="drv-picker-facet-bar">
                <div class="drv-picker-facet-bar__row1">
                    <div class="drv-input-icon drv-picker-facet-search">
                        <i class="ph ph-magnifying-glass drv-icon--xs" aria-hidden="true"></i>
                        <label for="qs-picker-search" class="u-sr-only">Tìm mẫu gạch</label>
                        <input id="qs-picker-search" type="search" class="drv-input drv-input--sm" placeholder="Tìm tên, mã gạch..." data-picker-search autocomplete="off">
                    </div>
                    <?php
                    $facet_configs = [
                        'space'   => ['label' => 'Không gian', 'title' => 'KHÔNG GIAN:', 'terms' => $space_terms, 'attr' => 'data-picker-space'],
                        'size'    => ['label' => 'Kích thước', 'title' => 'KÍCH THƯỚC:', 'terms' => $size_terms, 'attr' => 'data-picker-size'],
                        'pattern' => ['label' => 'Vân gạch', 'title' => 'VÂN GẠCH:', 'terms' => $pattern_terms, 'attr' => 'data-picker-pattern'],
                        'surface' => ['label' => 'Bề mặt', 'title' => 'BỀ MẶT:', 'terms' => $surface_terms, 'attr' => 'data-picker-surface'],
                    ];
                    ?>
                    <div class="drv-picker-facet-selects">
                        <?php foreach ($facet_configs as $key => $f) : ?>
                            <div class="drv-picker-dropdown" data-picker-facet="<?php echo esc_attr($key); ?>">
                                <input type="hidden" <?php echo esc_attr($f['attr']); ?> value="" data-default-label="<?php echo esc_attr($f['label']); ?>">
                                <button type="button" class="drv-picker-dropdown__btn" aria-haspopup="listbox" aria-expanded="false" data-picker-dropdown-trigger="<?php echo esc_attr($key); ?>">
                                    <span class="drv-picker-dropdown__label"><?php echo esc_html($f['label']); ?></span>
                                    <i class="ph ph-caret-down drv-icon--xs" aria-hidden="true"></i>
                                </button>
                                <div class="drv-picker-dropdown__menu drv-move-popover" role="listbox" hidden>
                                    <div class="drv-move-popover__title"><?php echo esc_html($f['title']); ?></div>
                                    <div class="drv-move-popover__list drv-picker-dropdown__list">
                                        <button type="button" class="drv-move-popover__item is-selected" data-picker-val="" data-picker-name="<?php echo esc_attr($f['label']); ?>" role="option">
                                            <span>Tất cả <?php echo esc_html(mb_strtolower($f['label'])); ?></span>
                                        </button>
                                        <?php foreach ($f['terms'] as $term) : ?>
                                            <button type="button" class="drv-move-popover__item" data-picker-val="<?php echo esc_attr($term->slug); ?>" data-picker-name="<?php echo esc_attr($term->name); ?>" role="option">
                                                <span><?php echo esc_html($term->name); ?></span>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="drv-picker-active-chips" data-picker-active-filters hidden>
                    <button type="button" class="drv-link u-text-xs drv-picker-reset-all" data-picker-reset-all>
                        <i class="ph ph-x-circle drv-icon--xs" aria-hidden="true"></i> Xóa lọc
                    </button>
                </div>
            </div>
            <div class="drv-picker-grid" data-picker-grid role="list" aria-live="polite"></div>
            <div class="drv-picker-more"><button type="button" class="drv-btn drv-btn--outline drv-btn--sm" data-picker-more hidden>Xem thêm mẫu</button></div>
        </div>
        <div class="drv-dialog__foot l-cluster l-cluster--between">
            <span class="u-text-xs u-text-muted">Đang hiển thị <strong data-picker-count>0</strong> mẫu gạch</span>
            <button type="button" class="drv-btn drv-btn--outline drv-btn--sm" data-drv-action="dialog-close">Đóng lại</button>
        </div>
    </dialog>

    <!-- MODAL 2: GỬI YÊU CẦU BÁO GIÁ CÔNG TRÌNH (`POST /dravit/v1/quote/submit`) -->
    <dialog id="drv-qs-lead" class="drv-dialog drv-qs-lead" aria-labelledby="qs-lead-title">
        <div class="drv-dialog__head">
            <div class="l-stack l-stack--2xs">
                <span class="u-text-xs u-bold u-uppercase u-text-moss">Gửi kèm bảng dự toán</span>
                <h2 id="qs-lead-title" class="u-text-lg" style="margin: 0">Nhận báo giá sỉ &amp; xác nhận tồn kho</h2>
            </div>
            <button type="button" class="drv-btn drv-btn--sm drv-btn--ghost drv-btn--icon" data-drv-action="dialog-close" aria-label="Đóng">
                <i class="ph ph-x drv-icon" aria-hidden="true"></i>
            </button>
        </div>

        <div class="drv-dialog__body l-stack l-stack--sm">
            <div class="drv-boq-success" data-qs-success hidden tabindex="-1" role="status">
                <span class="u-text-xs u-bold u-uppercase u-text-moss">Đã tiếp nhận phiếu báo giá</span>
                <strong class="drv-boq-success__code" data-qs-success-code></strong>
                <p class="u-text-sm u-text-muted" style="margin: 0" data-qs-success-msg></p>
                <div class="l-cluster l-cluster--xs">
                    <button type="button" class="drv-btn drv-btn--outline drv-btn--sm" data-qs-action="pdf">In PDF A4</button>
                    <button type="button" class="drv-btn drv-btn--outline drv-btn--sm" data-qs-action="copy-zalo">Sao chép gửi Zalo</button>
                    <button type="button" class="drv-btn drv-btn--ghost drv-btn--sm" data-qs-action="clear">Xóa bảng nháp để lập công trình mới</button>
                </div>
            </div>

            <form class="l-stack l-stack--sm" data-qs-lead-form novalidate>
                <div class="drv-qs-lead__summary">
                    <div class="l-cluster l-cluster--between">
                        <span class="u-text-xs u-bold u-text-navy">Các hạng mục gửi kèm</span>
                        <span class="u-text-xs u-text-muted" data-qs-lead-meta>0 hạng mục</span>
                    </div>
                    <div class="drv-qs-lead__items" data-qs-lead-list></div>
                    <div class="l-cluster l-cluster--between drv-qs-lead__total">
                        <span class="u-text-xs u-text-muted">Tổng dự toán tạm tính</span>
                        <strong class="u-tabular u-text-navy" data-qs-lead-total>0đ</strong>
                    </div>
                </div>

                <div class="l-grid l-grid--2">
                    <div class="drv-field">
                        <label for="qs-lead-name" class="drv-label"><span>Họ tên <span class="drv-label__req" aria-hidden="true">*</span></span></label>
                        <input id="qs-lead-name" name="name" type="text" class="drv-input" autocomplete="name" maxlength="80" required placeholder="Nguyễn Văn Minh">
                        <span class="drv-field__error" role="alert" hidden></span>
                    </div>
                    <div class="drv-field">
                        <label for="qs-lead-phone" class="drv-label"><span>Số điện thoại Zalo <span class="drv-label__req" aria-hidden="true">*</span></span></label>
                        <input id="qs-lead-phone" name="phone" type="tel" inputmode="tel" class="drv-input u-tabular" autocomplete="tel" maxlength="16" required placeholder="0909 123 456">
                        <span class="drv-field__error" role="alert" hidden></span>
                    </div>
                </div>
                <div class="l-grid l-grid--2">
                    <div class="drv-field">
                        <label for="qs-lead-prov" class="drv-label"><span>Tỉnh / TP giao hàng <span class="drv-label__req" aria-hidden="true">*</span></span></label>
                        <select id="qs-lead-prov" name="province" class="drv-select" required autocomplete="address-level1">
                            <option value="">Chọn Tỉnh / Thành phố</option>
                            <?php foreach ($provinces as $code => $label) : ?>
                                <option value="<?php echo esc_attr((string) $code); ?>"><?php echo esc_html((string) $label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="drv-field__error" role="alert" hidden></span>
                    </div>
                    <div class="drv-field">
                        <label for="qs-lead-role" class="drv-label">Bạn là</label>
                        <select id="qs-lead-role" name="role" class="drv-select">
                            <?php foreach (LeadValidator::ROLES as $val => $label) : ?>
                                <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="l-grid l-grid--2">
                    <div class="drv-field">
                        <label for="qs-lead-email" class="drv-label">Email (nhận bản báo giá)</label>
                        <input id="qs-lead-email" name="email" type="email" inputmode="email" class="drv-input" autocomplete="email" placeholder="ten@congty.vn">
                        <span class="drv-field__error" role="alert" hidden></span>
                    </div>
                    <div class="drv-field">
                        <label for="qs-lead-project" class="drv-label">Tên công trình</label>
                        <input id="qs-lead-project" name="project" type="text" class="drv-input" maxlength="120" placeholder="VD: Biệt thự Thảo Điền" data-qs-lead-project>
                    </div>
                </div>
                <div class="drv-field">
                    <label for="qs-lead-note" class="drv-label">Ghi chú công trình (tiến độ, đường vào, VAT...)</label>
                    <textarea id="qs-lead-note" name="message" class="drv-textarea" rows="2" maxlength="1000" placeholder="VD: Cần xem mẫu thực tế tại showroom, hẻm xe tải nhỏ..."></textarea>
                </div>
                <label class="drv-check" for="qs-lead-sample">
                    <input id="qs-lead-sample" type="checkbox" name="sample_box" value="1">
                    <span class="u-text-xs">Gửi kèm hộp mẫu thật để đối chiếu màu tại công trình</span>
                </label>
                <div class="drv-field">
                    <label class="drv-check" for="qs-lead-consent">
                        <input id="qs-lead-consent" type="checkbox" name="consent" value="1" required>
                        <span class="u-text-xs">Tôi đồng ý để DRAVIT liên hệ tư vấn và xử lý thông tin theo <a href="<?php echo esc_url($privacy_url); ?>" target="_blank" rel="noopener">Chính sách bảo mật dữ liệu cá nhân</a>.</span>
                    </label>
                    <span class="drv-field__error" role="alert" hidden></span>
                </div>
                <div class="drv-hp" aria-hidden="true">
                    <label for="qs-lead-website">Không điền trường này</label>
                    <input id="qs-lead-website" name="drv_website" type="text" tabindex="-1" autocomplete="off">
                </div>
                <p class="drv-lead-form__status" data-lead-form-status role="alert" hidden></p>
                <div class="drv-lead-form__foot">
                    <span class="u-text-xs u-text-muted"><i class="ph-fill ph-lock-simple drv-icon--xs" aria-hidden="true"></i> Cam kết bảo mật &amp; chỉ dùng để báo giá</span>
                    <button type="submit" class="drv-btn drv-btn--moss drv-btn--sm">
                        <i class="ph-bold ph-paper-plane-tilt drv-icon--xs" aria-hidden="true"></i>
                        <span>GỬI YÊU CẦU BÁO GIÁ</span>
                    </button>
                </div>
            </form>
        </div>
    </dialog>

    <!-- MODAL 3: MỞ LINK CHIA SẺ KHI ĐANG CÓ BẢNG NHÁP KHÁC -->
    <dialog id="qs-share-dialog" class="drv-dialog" aria-labelledby="qs-share-title">
        <div class="drv-dialog__head">
            <h2 id="qs-share-title" class="u-text-lg" style="margin: 0">Mở bảng dự toán được chia sẻ</h2>
        </div>
        <div class="drv-dialog__body">
            <p class="u-text-sm" data-qs-share-text></p>
        </div>
        <div class="drv-dialog__foot l-cluster l-cluster--between">
            <button type="button" class="drv-btn drv-btn--ghost drv-btn--sm" data-qs-share-choice="cancel">Giữ bảng hiện tại</button>
            <div class="l-cluster l-cluster--xs">
                <button type="button" class="drv-btn drv-btn--outline drv-btn--sm" data-qs-share-choice="merge">Gộp thêm vào</button>
                <button type="button" class="drv-btn drv-btn--moss drv-btn--sm" data-qs-share-choice="replace">Thay thế</button>
            </div>
        </div>
    </dialog>
</drv-boq>
