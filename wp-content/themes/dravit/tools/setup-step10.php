<?php
/**
 * Idempotent Step 10 Seed & Configuration Script (chạy qua WP-CLI `eval-file`).
 * - Tạo 4 chuyên mục `/chu-de/{slug}` chuẩn (`docs/20 §1.2`).
 * - Gán template `page-templates/template-about.php` cho `/gioi-thieu`.
 * - Điền nội dung chính sách pháp lý 2-3 cấp H2/H3 cho 5 trang Chính sách (`docs/07 §8.1`).
 * - Tạo 6 bài viết Cẩm nang kỹ thuật (gồm 1 bài Pillar đầy đủ 6 khối biên tập + 6 shortcode thương mại động, 0 giá cứng, 0 nhân sự giả).
 */
declare(strict_types=1);

use Dravit\Content\BlogSetup;
use Dravit\Content\ReadingTime;

if (!defined('ABSPATH')) {
    exit("Script này phải được chạy qua WP-CLI eval-file.\n");
}

// 1. Dọn bài mẫu `hello-world` mặc định nếu còn tồn tại.
$hello = get_page_by_path('hello-world', OBJECT, 'post');
if ($hello instanceof WP_Post && $hello->post_status !== 'trash') {
    wp_trash_post($hello->ID);
}

// 2. Tạo / cập nhật 4 chuyên mục Cẩm nang chuẩn (`/chu-de/{slug}`).
$cat_ids = [];
foreach (BlogSetup::CATEGORIES as $slug => $cfg) {
    $existing = get_term_by('slug', $slug, 'category');
    if ($existing instanceof WP_Term) {
        wp_update_term((int) $existing->term_id, 'category', [
            'name'        => $cfg['name'],
            'description' => $cfg['description'],
        ]);
        $cat_ids[$slug] = (int) $existing->term_id;
    } else {
        $inserted = wp_insert_term($cfg['name'], 'category', [
            'slug'        => $slug,
            'description' => $cfg['description'],
        ]);
        if (!is_wp_error($inserted) && isset($inserted['term_id'])) {
            $cat_ids[$slug] = (int) $inserted['term_id'];
        }
    }
}

// 3. Gán `template-about.php` cho `/gioi-thieu`.
$about_page = get_page_by_path('gioi-thieu', OBJECT, 'page');
$about_html = <<<'HTML'
<h2>Triết lý tuyển chọn vật liệu kiến trúc tại DRAVIT</h2>
<p>Khi bước vào một công trình hoàn thiện, cảm nhận sang trọng hay rời rạc phụ thuộc trực tiếp vào độ phẳng của bề mặt gạch, chiều rộng mạch ron 1,5 - 2 mm và sự đồng nhất sắc độ giữa các viên lát cạnh nhau. DRAVIT tập trung chuyên sâu vào dòng gạch Porcelain xương đá (độ hút nước E &le; 0,5% theo ISO 13006) và đá nung kết khổ lớn phục vụ nhà phố, biệt thự và căn hộ cao cấp.</p>
<p>Thay vì chỉ đăng hình phối cảnh, mỗi mã sản phẩm trên hệ thống đều công khai đầy đủ kích thước thực tế (mm), số viên mỗi thùng, diện tích phủ mỗi thùng (m²), khối lượng (kg) và cấp độ chống trượt R9 - R13 để chủ nhà, kiến trúc sư và nhà thầu tính toán chính xác ngay từ khâu thiết kế.</p>
HTML;

if ($about_page instanceof WP_Post) {
    wp_update_post([
        'ID'           => $about_page->ID,
        'post_title'   => 'Giới thiệu DRAVIT',
        'post_content' => trim((string) $about_page->post_content) !== '' ? $about_page->post_content : $about_html,
        'post_status'  => 'publish',
    ]);
    update_post_meta($about_page->ID, '_wp_page_template', 'page-templates/template-about.php');
    update_post_meta($about_page->ID, '_drv_system_page', '1');
}

// 4. Điền nội dung có cấu trúc H2/H3 cho 5 trang Chính sách (`template-policy.php`).
$policy_contents = [
    'chinh-sach-van-chuyen' => [
        'title' => 'Chính sách vận chuyển & giao nhận',
        'body'  => <<<'HTML'
<h2>1. Nguyên tắc báo phí vận chuyển theo khối lượng thực tế</h2>
<p>Gạch ốp lát và đá nung kết là vật liệu nặng (trung bình 32 - 58 kg/thùng tùy quy cách). Để khách hàng không phải trả mức phí ước tính cao hơn thực tế, DRAVIT áp dụng cơ chế <strong>báo phí vận chuyển chính xác sau khi xác nhận tổng khối lượng đơn hàng</strong> và điều kiện đường vào công trình.</p>
<h3>Phạm vi giao hàng nội thành TP. Hồ Chí Minh</h3>
<p>Đơn hàng có sẵn tại kho được điều phối bằng xe tải nhẹ 1 - 2,5 tấn hoặc xe tải 5 tấn trong vòng 24 - 48 giờ làm việc sau khi chốt mã lô xuất kho.</p>
<h3>Tuyến lân cận và liên tỉnh</h3>
<p>Hỗ trợ đóng đai kiện gỗ gia cố góc và giao qua xe tải nguyên chuyến hoặc chành xe chỉ định theo tiến độ thi công từng tầng.</p>

<h2>2. Quy định bốc xếp và hạ hàng tại chân công trình</h2>
<p>Phương tiện giao hàng mặc định dừng đỗ tại vị trí xe tải tiếp cận an toàn gần nhất ở tầng trệt chân công trình. Trường hợp hẻm nhỏ dưới 2,5 m, hầm chung cư giới hạn chiều cao hoặc cần đăng ký thang tải hàng theo khung giờ riêng, quý khách vui lòng ghi chú trước khi xác nhận đơn.</p>

<h2>3. Đồng kiểm ngoại quan và mã lô khi nhận gạch</h2>
<p>Khi nhận hàng, quý khách hoặc giám sát công trình kiểm tra trực tiếp số lượng thùng nguyên đai, mã sản phẩm (SKU), mã lô sản xuất (Lot), mã sắc độ (Shade) và cỡ (Caliber) in trên vỏ thùng trước khi ký biên bản bàn giao.</p>
HTML,
    ],
    'chinh-sach-doi-tra' => [
        'title' => 'Chính sách đổi trả & kiểm hàng',
        'body'  => <<<'HTML'
<h2>1. Điều kiện đổi trả thùng gạch dư nguyên đai</h2>
<p>DRAVIT hỗ trợ nhận lại hoặc đổi mẫu đối với các thùng gạch dư sau thi công trong vòng <strong>07 ngày</strong> kể từ ngày giao hàng khi đáp ứng đồng thời các điều kiện sau:</p>
<ul>
<li>Thùng gạch còn nguyên đai kiện nhà máy, vỏ thùng khô ráo, không rách nát hoặc dính vữa xi măng, keo dán.</li>
<li>Viên gạch chưa qua ngâm nước, chưa cắt mài, không trầy xước men bề mặt.</li>
<li>Không áp dụng trả lẻ viên hoặc hàng cắt gia công len chân tường, bậc cầu thang theo kích thước riêng.</li>
</ul>

<h2>2. Xử lý bể vỡ do quá trình vận chuyển</h2>
<p>Nếu phát hiện nứt cạnh hoặc bể vỡ ngay thời điểm hạ hàng tại chân công trình, quý khách chụp ảnh thực tế cùng nhân viên giao nhận và ghi nhận vào phiếu giao hàng. DRAVIT bù đổi miễn phí số viên/thùng tương ứng thuộc cùng mã màu (Shade) trong chuyến giao kế tiếp.</p>

<h2>3. Lưu ý kiểm tra trước khi trét mạch hoàn thiện</h2>
<p>Đội thợ cần trải ướm khô từ 4 - 6 viên trên sàn dưới ánh sáng đầy đủ để kiểm tra sự hài hòa của vân ngẫu nhiên (Random Faces) và độ phẳng trước khi thi công hàng loạt.</p>
HTML,
    ],
    'chinh-sach-thanh-toan' => [
        'title' => 'Chính sách thanh toán',
        'body'  => <<<'HTML'
<h2>1. Quy trình xác nhận tồn kho đồng lô trước khi thanh toán</h2>
<p>Khi quý khách đặt hàng trên website, đơn hàng được tiếp nhận ở trạng thái <strong>Chờ xác nhận</strong>. Nhân viên kho kiểm tra số lượng thùng thực tế thuộc cùng mã màu (Shade) &amp; cỡ (Caliber), tính phí vận chuyển tối ưu và liên hệ xác nhận tổng giá trị cuối cùng.</p>

<h2>2. Các hình thức thanh toán được chấp nhận</h2>
<h3>Chuyển khoản ngân hàng qua mã VietQR</h3>
<p>Sau khi đơn hàng được xác nhận và chuyển sang trạng thái chờ chuyển khoản, hệ thống sinh mã VietQR chính xác theo số tiền đơn hàng kèm nội dung <code>DRV{Mã đơn}</code> để đối soát tự động nhanh chóng.</p>
<h3>Thanh toán khi nhận hàng (COD)</h3>
<p>Áp dụng cho đơn hàng nội thành theo hạn mức quy định hoặc phần thanh toán còn lại sau khi đặt cọc giữ lô hàng tại kho.</p>

<h2>3. Xuất hóa đơn giá trị gia tăng (VAT)</h2>
<p>DRAVIT xuất đầy đủ hóa đơn điện tử GTGT theo thông tin pháp nhân, mã số thuế và email nhận hóa đơn quý khách cung cấp tại bước thanh toán.</p>
HTML,
    ],
    'chinh-sach-bao-mat' => [
        'title' => 'Chính sách bảo mật thông tin',
        'body'  => <<<'HTML'
<h2>1. Phạm vi thông tin thu thập</h2>
<p>DRAVIT chỉ thu thập các thông tin liên hệ thiết yếu do quý khách chủ động cung cấp khi đặt hàng, lập bảng dự toán BOQ hoặc gửi yêu cầu tư vấn kỹ thuật, bao gồm: họ tên, số điện thoại, địa chỉ giao nhận công trình, email và thông tin xuất hóa đơn VAT (nếu có).</p>

<h2>2. Mục đích sử dụng dữ liệu</h2>
<ul>
<li>Liên hệ xác nhận tồn kho đồng bộ mã lô, thông báo lịch xe giao gạch và gửi chứng từ bảo hành/hóa đơn.</li>
<li>Lưu trữ bảng bóc tách khối lượng BOQ theo từng hạng mục phòng để hỗ trợ bổ sung vật tư đúng mã màu khi công trình phát sinh.</li>
</ul>

<h2>3. Thời hạn lưu trữ và quyền của khách hàng</h2>
<p>Dữ liệu đơn hàng được lưu trữ theo quy định kế toán hiện hành; hồ sơ yêu cầu báo giá chưa chuyển thành đơn hàng được rà soát định kỳ. Quý khách có quyền yêu cầu tra cứu, chỉnh sửa hoặc ẩn thông tin liên hệ bất kỳ lúc nào qua email CSKH.</p>
HTML,
    ],
    'dieu-khoan' => [
        'title' => 'Điều khoản sử dụng',
        'body'  => <<<'HTML'
<h2>1. Quy ước đơn vị niêm yết và đơn vị giao dịch</h2>
<p>Trên hệ thống DRAVIT, đơn giá tham chiếu chính được hiển thị theo <strong>đ/m²</strong> để thuận tiện so sánh dự toán diện tích; đơn giá giao dịch trong giỏ hàng được tính theo <strong>số nguyên viên hoặc thùng nguyên đai</strong> theo quy cách đóng gói thực tế của từng nhà máy.</p>

<h2>2. Dung sai thông số và sắc độ vật liệu nung</h2>
<p>Gạch Porcelain và đá nung kết trải qua quá trình nung ở nhiệt độ cao nên luôn có mã lô sản xuất (Lot), mã sắc độ (Shade) và dung sai kích cỡ (Caliber) theo tiêu chuẩn TCVN 7745 / ISO 13006. Màu sắc hiển thị trên màn hình thiết bị có thể chênh lệch nhẹ so với thực tế dưới đèn 3000K - 6500K; khuyến khích quý khách xem viên mẫu thật trước khi chốt khối lượng lớn.</p>

<h2>3. Quyền sở hữu nội dung kỹ thuật</h2>
<p>Toàn bộ bảng quy đổi định mức, hướng dẫn thi công và dữ liệu phân loại trên website thuộc bản quyền của DRAVIT. Mọi trích dẫn cần ghi rõ nguồn liên kết gốc.</p>
HTML,
    ],
];

foreach ($policy_contents as $slug => $p_data) {
    $p_obj = get_page_by_path($slug, OBJECT, 'page');
    if ($p_obj instanceof WP_Post) {
        wp_update_post([
            'ID'           => $p_obj->ID,
            'post_title'   => $p_data['title'],
            'post_content' => $p_data['body'],
            'post_status'  => 'publish',
        ]);
        update_post_meta($p_obj->ID, '_wp_page_template', 'page-templates/template-policy.php');
        update_post_meta($p_obj->ID, 'drv_effective_date', '01/01/2026');
        update_post_meta($p_obj->ID, '_drv_system_page', '1');
    }
}

// 5. Lấy danh sách SKU thật đang publish trong WooCommerce để nhúng vào Shortcodes bài viết.
$live_skus = [];
if (function_exists('wc_get_products')) {
    foreach (wc_get_products(['limit' => 8, 'status' => 'publish']) as $prod) {
        if ($prod instanceof WC_Product && $prod->get_sku() !== '') {
            $live_skus[] = $prod->get_sku();
        }
    }
}
$sku_1 = $live_skus[0] ?? 'DRV-VA6060-M18';
$sku_2 = $live_skus[1] ?? 'DRV-VA8080-B16';
$sku_3 = $live_skus[2] ?? 'DRV-VA8080-M17';
$sku_4 = $live_skus[3] ?? 'DRV-VA6120-ES07';
$table_skus = implode(',', array_slice($live_skus !== [] ? $live_skus : [$sku_1, $sku_2, $sku_3, $sku_4], 0, 4));
$stl_skus   = implode(',', array_slice([$sku_1, $sku_4], 0, 2));

// 6. Seed 6 bài viết Cẩm nang kỹ thuật (idempotent theo `post_name`).
$seed_posts = [
    [
        'slug'        => 'tieu-chuan-chon-gach-lat-nha-tam-chong-truot-r10-r11',
        'title'       => 'Tiêu chuẩn chọn gạch lát nhà tắm chống trượt R10 - R11 và quy trình thi công sàn ướt',
        'excerpt'     => 'Hướng dẫn chọn đúng cấp độ chống trượt R10 cho sàn khô ướt xen kẽ, R11 cho buồng tắm đứng, kết hợp xương Porcelain hút nước dưới 0,5% và độ dốc thoát sàn 1,5 - 2%.',
        'cat'         => 'ky-thuat-op-lat',
        'is_pillar'   => '1',
        'collections' => ['gach-nha-tam', 'gach-lat-nen', 'gach-30x60', 'gach-60x60'],
        'takeaways'   => [
            'Sàn nhà tắm gia đình bắt buộc đạt tối thiểu R10 (góc nghiêng 10 - 19 độ theo DIN 51130); khu tắm đứng không vách kính hoặc có người lớn tuổi ưu tiên R11.',
            'Chọn xương gạch Porcelain nhóm BIa (độ hút nước E <= 0,5%) để tránh ố mạch, bong bộp và mùi ẩm mốc lâu ngày.',
            'Sàn ướt ưu tiên khổ 30x60 hoặc 60x60 cm để thợ dễ tạo độ dốc thu nước 1,5 - 2% về phễu thoát sàn mà không phải cắt chéo nhiều đường.',
            'Sử dụng keo dán gạch gốc polymer biến tính đạt chuẩn C2 kết hợp ke cân bằng mạch ron 1,5 - 2 mm và keo chà ron chống thấm.',
        ],
        'ai_prompts'  => [
            'Nhà tắm 5 m² có người lớn tuổi nên chọn gạch lát nền nào đạt R10 dễ cọ rửa?',
            'Tính số thùng gạch lát sàn 60x60 và gạch ốp tường 30x60 cho WC 4,5 m² cao 2,6 m',
        ],
        'tags'        => ['Chống trượt R10', 'Gạch nhà tắm', 'Keo dán gạch C2', 'Xương Porcelain'],
        'content'     => <<<HTML
<h2>1. Vì sao sàn nhà tắm không nên chọn cảm tính bằng tay khô?</h2>
<p>Khi sờ tay khô tại cửa hàng, bề mặt men mờ mịn (Matt) và men lappato có cảm giác ma sát khá giống nhau. Tuy nhiên, khi phủ một lớp nước xà phòng mỏng 1 - 2 mm, hệ số ma sát động giảm mạnh khiến gạch bóng kiếng hoặc bán bóng (R9) gây trượt chân ngay ở bước xoay người.</p>

[drv_callout type="warning" title="Sai lầm phổ biến khi chọn gạch đồng bộ sàn và tường"]Không dùng chung mẫu gạch bóng kiếng ốp tường (R9) hạ xuống lát sàn khu vực tắm đứng. Gạch ốp tường có thể dùng bề mặt bóng hoặc satin để dễ lau vết cặn canxi, nhưng viên lát sàn bắt buộc đạt từ <strong>R10 trở lên</strong>.[/drv_callout]

<h2>2. Phân biệt cấp chống trượt R10 và R11 cho từng phân khu WC</h2>
<p>Tiêu chuẩn Đức DIN 51130 phân cấp khả năng bám chân trên mặt phẳng nghiêng có dầu bôi trơn từ R9 đến R13. Đối với phòng vệ sinh dân dụng tại Việt Nam, hai ngưỡng kỹ thuật quan trọng nhất là <strong>R10</strong> và <strong>R11</strong>:</p>

<table>
<thead>
<tr>
<th>Khu vực trong phòng tắm</th>
<th>Cấp chống trượt khuyến nghị</th>
<th>Bề mặt phù hợp</th>
<th>Khổ gạch tối ưu</th>
</tr>
</thead>
<tbody>
<tr>
<td>Khu khô (bồn cầu, lavabo)</td>
<td>R10</td>
<td>Mờ Matt mịn (Micro-matt)</td>
<td>60x60 cm hoặc 30x60 cm</td>
</tr>
<tr>
<td>Buồng tắm đứng vách kính</td>
<td>R10 - R11</td>
<td>Matt nhám hạt mịn</td>
<td>30x60 cm hoặc 60x60 cm cắt rãnh</td>
</tr>
<tr>
<td>Phòng tắm người cao tuổi / trẻ nhỏ</td>
<td>R11</td>
<td>Nhám định hình nhẹ, dễ thoát nước</td>
<td>30x30 cm hoặc 30x60 cm</td>
</tr>
</tbody>
</table>

[drv_pros_cons pros_title="Ưu điểm bề mặt Matt R10 thế hệ mới" cons_title="Lưu ý với bề mặt nhám sâu R11 - R12" pros="Đủ độ bám chân an toàn khi sàn ướt xà phòng|Hạt men khép kín, chà rửa bằng bàn chải mềm không bám cặn đen|Dịu mắt dưới đèn vàng ấm 3000K, không lóa bóng" cons="Nhám sần quá sâu (R12 ngoài trời) đưa vào nhà tắm sẽ giữ bọt xà phòng nếu thiếu độ dốc|Cần lau sạch lớp xi măng/keo chà ron ngay trong 15 phút đầu sau khi trét mạch"]

[drv_products collection="gach-nha-tam" skus="{$sku_1},{$sku_4}" limit="4" layout="grid" title="Các mẫu gạch nhám mờ & Porcelain phù hợp khu vực ẩm ướt"]

<h2>3. Công thức tính khối lượng gạch lát sàn và ốp tường nhà tắm</h2>
<p>Phòng vệ sinh có nhiều góc cắt quanh cổ ống thoát sàn, bồn cầu âm tường và hộp kỹ thuật (gen nước). Do đó tỷ lệ hao hụt cắt gạch thực tế thường nằm ở mức <strong>7 - 10%</strong> thay vì 5% như phòng khách vuông vức.</p>

[drv_formula title="Định mức quy đổi số thùng gạch cho phòng tắm" sub="Làm tròn lên chẵn thùng nguyên đai" cells="Diện tích sàn cần mua::S_mua = S_sàn x 1,08::Cộng 8% hao hụt cắt góc thu phễu | Diện tích tường ốp kịch trần::S_tường = Chu_vi x Chiều_cao - S_cửa::Nhân thêm 1,07 nếu ốp so le | Số thùng nguyên cần đặt::Số thùng = CEIL(S_mua / m²_mỗi_thùng)::Luôn giữ dư 2 - 3 viên cùng mã Shade để dự phòng bảo trì đường nước"]

[drv_shop_the_look sku="{$stl_skus}" role="Gạch lát sàn chống trượt|Gạch ốp tường phối đồng tông" caption="Phối nền xám nhám R10 cùng mảng tường khổ 60x120 ít đường mạch ron giúp phòng tắm 5 m² thoáng rộng và sạch sẽ hơn."]

<h2>4. Quy trình 4 bước thi công sàn ướt không đọng vũng và không bong bộp</h2>
<p>Gạch Porcelain có xương đặc chắc (độ hút nước dưới 0,5%) nên hồ dầu xi măng thuần rất khó bám dính lâu dài, đặc biệt ở mảng tường ốp cao 2,6 - 2,8 m. Đội thợ cần tuân thủ quy trình thi công bằng keo dán chuyên dụng:</p>

[drv_steps label="Quy trình ốp lát khu vực ẩm ướt" items="Nghiệm thu lớp chống thấm & tạo dốc 1,5 - 2%::Bước 1::Ngâm thử nước 24 giờ cho cổ ống và sàn âm trước khi cán vữa tạo dốc hướng thẳng về phễu thu nước. | Đối chiếu mã Lot, Shade & Caliber trên vỏ thùng::Bước 2::Xếp toàn bộ thùng gạch cùng khu vực ra kiểm tra đúng một mã màu (Shade) và cỡ (Caliber) trước khi bóc đai. | Trải keo dán C2 hai mặt (Back-buttering)::Bước 3::Dùng bay răng cưa 8 - 10 mm kéo keo đều trên nền và phết một lớp mỏng sau lưng viên gạch để triệt tiêu bọt khí rỗng. | Cài ke cân bằng 2 mm & chà ron chống thấm::Bước 4::Chờ keo đóng rắn đủ 24 giờ mới tháo chốt ke và chà mạch bằng keo hai thành phần hoặc keo chống nấm mốc."]

[drv_price_table skus="{$table_skus}" caption="Bảng thông số quy cách đóng gói và đơn giá tham khảo trực tiếp từ kho DRAVIT"]

[drv_ai_card title="Cần gợi ý nhanh mẫu gạch nhà tắm đúng diện tích thực tế?"]

[drv_lead_form type="sample" title="Đăng ký nhận mẫu gạch nhám R10 thử nước trực tiếp tại công trình" sku="{$sku_1}"]

<h2>5. Câu hỏi thường gặp khi chọn gạch phòng tắm</h2>
[drv_faq open_first="1"]
[drv_faq_item q="Nhà tắm 4 m² có lát được gạch khổ 60x60 cm không?"]Hoàn toàn lát được nếu sử dụng phễu thu nước dài sát chân tường (linear drain) để mặt sàn dốc theo một chiều duy nhất. Nếu dùng phễu vuông đặt giữa sàn, thợ sẽ phải cắt chéo 4 đường chụm vào tâm phễu hoặc chuyển sang khổ 30x60 cm để dễ lấy dốc hơn.[/drv_faq_item]
[drv_faq_item q="Độ hút nước E dưới 0,5% trên vỏ thùng gạch có ý nghĩa gì?"]Đây là ngưỡng tiêu chuẩn ISO 13006 phân loại xương gạch Porcelain nhóm BIa (E &le; 0,5%). Nhờ kết cấu nén chặt ở nhiệt độ khoảng 1200 độ C, xương gạch gần như không hút nước ngược từ sàn ẩm lên mặt men, ngăn ngừa hiện tượng loang ố sẫm màu quanh mạch ron.[/drv_faq_item]
[drv_faq_item q="Nên để độ rộng đường ron nhà tắm bao nhiêu mm là bền nhất?"]Với gạch mài cạnh phẳng (Rectified), chiều rộng mạch ron tối ưu trong khu vực ướt là 1,5 - 2 mm. Không nên ghép khít dưới 1 mm vì keo chà ron không thể lấp sâu xuống toàn bộ chiều dày cạnh gạch để ngăn nước thấm xuống lớp keo bên dưới.[/drv_faq_item]
[/drv_faq]

[drv_related_posts limit="2"]
HTML,
    ],
    [
        'slug'        => 'cong-thuc-tinh-so-thung-gach-va-ty-le-hao-hut-thuc-te',
        'title'       => 'Công thức tính số thùng gạch 60x60, 80x80, 60x120 và tỷ lệ hao hụt theo kiểu lát',
        'excerpt'     => 'Bảng quy đổi diện tích thực tế sang số thùng nguyên đai cho các khổ gạch phổ biến, kèm định mức hao hụt 5% khi lát thẳng và 10% khi lát chéo hoặc xương cá.',
        'cat'         => 'dinh-muc-bao-gia',
        'is_pillar'   => '0',
        'collections' => ['gach-80x80', 'gach-60x60', 'gach-60x120', 'gach-lat-nen'],
        'takeaways'   => [
            'Một thùng gạch 80x80 (3 viên) phủ đúng 1,92 m²; gạch 60x60 (4 viên) phủ 1,44 m²; gạch 60x120 (2 viên) phủ 1,44 m².',
            'Lát thẳng vuông góc tường cộng thêm 5% hao hụt; lát so le 1/3 hoặc lát chéo 45 độ cộng thêm 8 - 10%.',
            'Đặt dư 1 thùng cùng mã màu (Shade) ngay từ đầu rẻ và an toàn hơn rất nhiều so với việc tìm mua bổ sung khác lô sản xuất.',
        ],
        'ai_prompts'  => [],
        'tags'        => ['Định mức vật tư', 'Gạch 80x80', 'Gạch 60x120'],
        'content'     => <<<HTML
<h2>1. Quy cách đóng gói tiêu chuẩn của các khổ gạch thông dụng</h2>
<p>Mỗi nhà máy đóng gói số viên trong một thùng dựa trên giới hạn trọng lượng bốc xếp an toàn (khoảng 30 - 50 kg/thùng). Do đó diện tích phủ thực tế của một thùng hiếm khi tròn 1,00 m² hay 2,00 m²:</p>
<ul>
<li><strong>Khổ 60x60 cm (600x600 mm):</strong> 4 viên/thùng = <strong>1,44 m²/thùng</strong> (nặng khoảng 30 - 34 kg).</li>
<li><strong>Khổ 80x80 cm (800x800 mm):</strong> 3 viên/thùng = <strong>1,92 m²/thùng</strong> (nặng khoảng 42 - 46 kg).</li>
<li><strong>Khổ 60x120 cm (600x1200 mm):</strong> 2 viên/thùng = <strong>1,44 m²/thùng</strong> (nặng khoảng 34 - 38 kg).</li>
<li><strong>Khổ 30x60 cm (300x600 mm):</strong> 8 viên/thùng = <strong>1,44 m²/thùng</strong> (thường dùng ốp tường hoặc lát WC).</li>
</ul>

<h2>2. Tỷ lệ hao hụt thực tế theo mặt bằng và kiểu lát</h2>
<p>Phần gạch cắt sát chân tường, góc cột âm dương hoặc cắt chéo tạo phễu thoát nước thường không tận dụng lại được cho vị trí khác do lệch chiều vân và cạnh mài.</p>

[drv_formula title="Bảng hệ số hao hụt khuyến nghị theo kiểu thi công" sub="Áp dụng cho mặt bằng dân dụng" cells="Lát thẳng chữ thập phòng vuông::+5%::Phòng khách, phòng ngủ ít góc xéo | Ốp lát phòng tắm nhiều cổ ống::+8%::Có hộp gen, bệ âm tường, dốc phễu | Lát xương cá / lát chéo 45 độ::+10%::Gạch giả gỗ thanh dài hoặc điểm nhấn sảnh"]

<h2>3. Ví dụ tính thực tế cho sàn phòng khách 35 m² lát gạch 80x80</h2>
<p>Giả sử diện tích thông thủy đo trên bản vẽ là 35 m², lát thẳng bằng gạch 80x80 cm (quy cách 3 viên = 1,92 m²/thùng):</p>
<ol>
<li>Diện tích cần mua cả hao hụt 5%: <code>35 x 1,05 = 36,75 m²</code>.</li>
<li>Chia cho diện tích mỗi thùng: <code>36,75 / 1,92 = 19,14 thùng</code>.</li>
<li>Làm tròn lên chẵn thùng nguyên: <strong>20 thùng</strong> (tương đương <strong>60 viên</strong> = <strong>38,40 m²</strong>).</li>
</ol>

[drv_price_table skus="{$table_skus}" caption="Tra cứu nhanh quy cách đóng gói m²/thùng và trọng lượng các mẫu sẵn kho"]

[drv_faq open_first="1"]
[drv_faq_item q="Tại sao không nên mua vừa khít diện tích bản vẽ rồi thiếu mới mua thêm?"]Gạch nung qua từng đợt lò khác nhau luôn có độ lệch sắc độ nhẹ (khác mã Shade) và dung sai kích cỡ khoảng 0,5 mm (khác mã Caliber). Nếu vài tuần sau mới đặt lẻ 1 - 2 thùng mà lô cũ đã hết, viên lát bổ sung đặt cạnh viên cũ sẽ thấy rõ ranh giới lệch tông màu.[/drv_faq_item]
[drv_faq_item q=" Phần gạch cắt làm len chân tường tính riêng hay tính chung?"]Nếu cắt từ chính viên gạch lát nền 80x80 ra thanh len cao 10 - 12 cm (mỗi viên cắt được 6 thanh dài 80 cm = 4,8 mét dài), bạn cần đo tổng chu vi chân tường trừ cửa đi để cộng thêm số viên tương ứng vào đơn đặt hàng ngay từ đầu.[/drv_faq_item]
[/drv_faq]
HTML,
    ],
    [
        'slug'        => 'chon-kich-thuoc-gach-lat-phong-khach-theo-dien-tich-va-tran-nha',
        'title'       => 'Chọn kích thước gạch lát phòng khách 25 - 45 m² theo diện tích và chiều cao trần',
        'excerpt'     => 'Đối chiếu tỷ lệ đường mạch ron giữa khổ 60x60, 80x80 và 60x120 cm trên mặt bằng nhà phố 4 - 5 m ngang và căn hộ chung cư trần cao 2,65 - 2,8 m.',
        'cat'         => 'chon-gach-theo-phong',
        'is_pillar'   => '0',
        'collections' => ['gach-phong-khach', 'gach-80x80', 'gach-60x120', 'da-nung-ket'],
        'takeaways'   => [
            'Nhà phố ngang 4 - 5 m hoặc chung cư từ 22 m² trở lên phù hợp nhất với khổ 80x80 hoặc 60x120 cm để giảm gần 45% tổng chiều dài mạch ron so với 60x60.',
            'Phòng khách trần thấp dưới 2,7 m ưu tiên bề mặt mờ mịn (Satin/Matt) hoặc bán bóng để tránh phản chiếu chói lóa hệ đèn âm trần.',
            'Khổ lớn từ 60x120 và 100x100 cm trở lên đòi hỏi cốt nền cán phẳng (sai số <= 2 mm trên thước 2 m) trước khi trải keo.',
        ],
        'ai_prompts'  => [],
        'tags'        => ['Phòng khách', 'Gạch 80x80', 'Gạch khổ lớn'],
        'content'     => <<<HTML
<h2>1. Tương quan giữa chiều ngang phòng và số mạch cắt</h2>
<p>Một phòng khách nhà phố điển hình tại Việt Nam có chiều ngang lọt lòng khoảng 3,8 - 4,8 m. Nếu chọn gạch <strong>80x80 cm</strong>, chiều ngang 4,0 m xếp vừa khít 5 viên nguyên mà không xuất hiện dải gạch vụn nhỏ sát chân tường, giúp không gian liền lạc và cân đối ngay từ cửa chính nhìn vào.</p>

[drv_pros_cons pros_title="Khổ vuông 80x80 cm" cons_title="Khổ chữ nhật 60x120 cm" pros="Dễ thi công, tối ưu chi phí nhân công cho nhà phố và căn hộ|Ít cong vênh, kiểm soát cao độ 4 góc bằng ke cân bằng nhanh|Hao hụt cắt góc thấp (chỉ khoảng 5%)" cons="Tạo hiệu ứng kéo dài chiều sâu không gian rất tốt nhưng cần 2 thợ cùng đặt viên|Bắt buộc lát trùng mạch hoặc so le tối đa 1/4 - 1/3 chiều dài viên, tuyệt đối không lát so le 50/50"]

<h2>2. Chọn độ bóng bề mặt theo hướng nắng và cao độ trần</h2>
<p>Gạch bóng kiếng toàn phần (Polished) giúp khuếch tán ánh sáng tự nhiên cho nhà ống thiếu cửa sổ bên hông. Tuy nhiên với căn hộ có hệ vách kính lớn hướng Tây hoặc lắp nhiều đèn spotlight chiếu điểm, bề mặt <strong>mờ mịn (Matt/Satin)</strong> giữ được chiều sâu vân đá tự nhiên và êm chân hơn khi đi chân trần.</p>

[drv_products collection="gach-phong-khach" skus="{$sku_2},{$sku_3}" limit="4" layout="grid" title="Mẫu gạch lát phòng khách 80x80 & 60x120 sẵn kho"]

<h2>3. Kiểm tra thang hàng và lối vận chuyển cho gạch khổ lớn</h2>
<p>Trước khi chốt các mẫu khổ 100x100 hoặc 120x240 cm cho căn hộ chung cư, cần đo kích thước cửa và chiều chéo cabin thang tải hàng. Khổ 60x120 và 80x80 cm lọt vừa hầu hết mọi thang máy dân dụng mà không phát sinh chi phí cẩu tời mặt ngoài.</p>

[drv_faq open_first="1"]
[drv_faq_item q="Phòng khách 20 m² lát gạch 80x80 có bị cảm giác chật không?"]Không chật nếu chọn tông màu sáng trung tính (trắng ngà, kem ấm, xám nhạt) và chà ron tiệp màu gạch. Ít đường chia ô giúp mắt nhìn mặt sàn như một tấm phẳng rộng hơn.[/drv_faq_item]
[drv_faq_item q="Tại sao gạch 60x120 không được lát so le giữa viên (1/2)?"]Ở giữa chiều dài 120 cm luôn có độ vồng vi mô tự nhiên sau khi nung trong giới hạn tiêu chuẩn. Nếu đặt điểm giữa viên này sát đầu mép viên kia (so le 50/50), độ chênh mép (lippage) sẽ lộ rõ nhất; tiêu chuẩn thi công chỉ cho phép so le tối đa 25 - 30% chiều dài viên.[/drv_faq_item]
[/drv_faq]
HTML,
    ],
    [
        'slug'        => 'kiem-tra-sac-do-gach-van-da-marble-va-terrazzo-duoi-den-3000k-6500k',
        'title'       => 'Cách kiểm tra sắc độ gạch vân đá Marble và Terrazzo dưới ánh đèn 3000K - 6500K',
        'excerpt'     => 'Giải thích hiện tượng đổi tông màu gạch xám ấm (Greige), trắng Calacatta và hạt đá Terrazzo dưới 3 nhiệt độ màu đèn phổ biến trong nội thất.',
        'cat'         => 'phoi-mau-xu-huong',
        'is_pillar'   => '0',
        'collections' => ['gach-van-da', 'gach-terrazzo', 'gach-phong-khach', 'gach-phong-bep'],
        'takeaways'   => [
            'Cùng một viên gạch xám trung tính có thể ngả xanh lạnh dưới đèn trắng 6500K nhưng chuyển sang be ấm dưới đèn vàng 3000K.',
            'Luôn soi viên mẫu dưới đúng nhiệt độ màu đèn dự kiến lắp đặt (phổ biến nhất cho nhà ở hiện đại là 4000K trung tính).',
            'Kiểm tra số lượng mặt vân ngẫu nhiên (Faces) của dòng gạch vân đá để tránh lặp lại một vệt vân đậm ở hai viên nằm sát nhau.',
        ],
        'ai_prompts'  => [],
        'tags'        => ['Vân đá Marble', 'Terrazzo', 'Ánh sáng nội thất'],
        'content'     => <<<HTML
<h2>1. Ba ngưỡng nhiệt độ màu đèn làm thay đổi màu gạch thực tế</h2>
<p>Nhiều chủ nhà chọn gạch ngoài trời nắng gắt buổi trưa nhưng khi lát vào phòng ăn bật đèn vàng lại thấy nền ngả kem đậm hơn hình dung. Nguyên nhân nằm ở chỉ số nhiệt độ màu (Kelvin) và độ hoàn màu (CRI &ge; 90) của hệ thống chiếu sáng:</p>
<ul>
<li><strong>3000K (Vàng ấm):</strong> Tôn lên các đường vân vàng đồng (Calacatta Gold), Travertine kem và gạch giả gỗ ấm áp; nhưng có thể làm gạch trắng tinh hơi ngả ngà.</li>
<li><strong>4000K (Trung tính):</strong> Giữ trung thực nhất cả tông xám xi măng, hạt đá Terrazzo đa sắc lẫn vân đá trắng xám; khuyên dùng cho phòng khách và bếp.</li>
<li><strong>6500K (Trắng ban ngày):</strong> Làm nổi bật độ sắc nét nhưng dễ gây cảm giác lạnh nếu kết hợp với gạch bóng kiếng xám đậm.</li>
</ul>

[drv_callout type="tip" title="Kinh nghiệm thử mẫu tại Showroom"]Đặt viên gạch nền nằm ngang dưới sàn thay vì dựng đứng trên kệ, sau đó đặt mảnh gỗ tủ bếp hoặc màu sơn tường sát cạnh rồi chuyển lần lượt 3 chế độ đèn 3000K - 4000K - 6500K để quan sát độ phản xạ thật.[/drv_callout]

<h2>2. Lưu ý về số mặt vân ngẫu nhiên (Random Faces)</h2>
<p>Dòng gạch Porcelain in kỹ thuật số cao cấp có từ 6 đến 12 mặt vân khác nhau trong cùng một mã sản phẩm. Khi bóc thùng thi công, thợ cần lấy xen kẽ từ 2 - 3 thùng và xoay hướng viên theo mũi tên đáy gạch (hoặc phân bố ngẫu nhiên với vân tự do) để mặt sàn tự nhiên như đá cắt nguyên khối.</p>

[drv_shop_the_look sku="{$stl_skus}" role="Gạch nền vân đá tự nhiên|Gạch mảng nhấn khổ lớn" caption="Kết hợp nền trung tính Mờ Matt cùng mảng tường vân đá sắc độ ấm dưới ánh sáng 4000K."]

[drv_faq open_first="1"]
[drv_faq_item q="Có nên chọn màu keo chà ron trắng tinh cho sàn gạch vân đá trắng không?"]Nên ưu tiên màu xám bạc nhạt hoặc trắng ngà tiệp với màu đường vân thay vì trắng tinh, vì sàn nhà đi lại thường xuyên sẽ giữ màu ron xám nhạt sạch và đồng đều lâu hơn rất nhiều.[/drv_faq_item]
[drv_faq_item q="Gạch Terrazzo hạt nhỏ và hạt lớn hợp với diện tích nào?"]Terrazzo hạt nhuyễn (Micro-terrazzo) dễ phối đồ nội thất tối giản cho phòng tắm và bếp 6 - 15 m²; Terrazzo hạt đá lớn tạo điểm nhấn cá tính phù hợp quán cà phê, sảnh rộng hoặc mảng tường lavabo.[/drv_faq_item]
[/drv_faq]
HTML,
    ],
    [
        'slug'        => 'phan-biet-ma-lo-lot-ma-mau-shade-va-co-caliber-khi-nghiem-thu-gach',
        'title'       => 'Phân biệt mã lô (Lot), mã màu (Shade) và cỡ (Caliber) khi nghiệm thu gạch tại công trình',
        'excerpt'     => 'Ba ký hiệu kỹ thuật in trên vỏ thùng quyết định 90% độ đều màu và độ thẳng của đường mạch ron mà chủ nhà và giám sát cần kiểm tra trước khi bóc thùng.',
        'cat'         => 'ky-thuat-op-lat',
        'is_pillar'   => '0',
        'collections' => ['gach-porcelain', 'gach-lat-nen', 'gach-op-tuong', 'da-nung-ket'],
        'takeaways'   => [
            'Lot là đợt sản xuất; Shade (Tone) là mã sắc độ màu thực tế sau nung; Caliber (Work Size) là nhóm dung sai kích thước thực tế từng ly.',
            'Một mặt sàn liền thông bắt buộc dùng 100% các thùng có trùng mã Shade và trùng mã Caliber.',
            'Nếu công trình lớn buộc phải dùng 2 mã Shade khác nhau, hãy tách riêng theo từng tầng hoặc từng phòng có cửa ngăn cách.',
        ],
        'ai_prompts'  => [],
        'tags'        => ['Nghiệm thu vật tư', 'Mã Shade', 'Caliber'],
        'content'     => <<<HTML
<h2>1. Ý nghĩa 3 thông số đóng tem trên thành vỏ thùng gạch</h2>
<p>Vì cốt liệu đất sét, tràng thạch và thạch anh co ngót tự nhiên ở nhiệt độ nung khoảng 1200 độ C, dây chuyền quang học cuối lò nung sẽ phân loại tự động từng viên gạch vào các nhóm chuẩn xác trước khi đóng hộp:</p>
<ul>
<li><strong>Mã lô sản xuất (Batch / Lot):</strong> Cho biết ngày giờ và ca vận hành của lò nung.</li>
<li><strong>Mã sắc độ (Shade / Tone):</strong> Ký hiệu độ đậm nhạt vi mô của men bề mặt (ví dụ: <code>S12</code>, <code>A4</code>). Hai thùng cùng mã SKU nhưng lệch mã Shade khi lát sát nhau dưới ánh đèn xiên sẽ thấy một vùng hơi ấm hơn vùng còn lại.</li>
<li><strong>Mã kích cỡ (Caliber / Size code):</strong> Ví dụ gạch danh nghĩa 800x800 mm có thể được phân thành nhóm cỡ <code>0</code> (800,0 mm) hoặc cỡ <code>1</code> (800,4 mm). Trộn lẫn hai cỡ trên cùng một hàng lát sẽ làm đường ron bị lệch dần sau mỗi 4 - 5 viên.</li>
</ul>

[drv_callout type="warning" title="Nguyên tắc vàng trước khi xé vỏ thùng"]Tuyệt đối giữ nguyên vỏ hộp khi phát hiện lệch mã Shade hoặc Caliber và báo ngay cho bộ phận điều vận kho để đổi sang kiện đồng nhất. Đừng bóc toàn bộ gạch ra ngâm nước hoặc xếp chồng mất dấu vỏ thùng.[/drv_callout]

<h2>2. Quy trình 4 bước kiểm hàng nhanh trong 5 phút tại chân công trình</h2>
<p>Giám sát công trình hoặc chủ nhà có thể tự kiểm tra lô gạch vừa hạ xuống xe tải chỉ với 4 thao tác đơn giản:</p>

[drv_steps label="Quy trình đồng kiểm gạch giao tới công trình" items="Đếm tổng số thùng theo từng hạng mục phòng::Bước 1::Đối chiếu số lượng thùng thực nhận với phiếu xuất kho cho từng mã SKU. | Đọc cụm ký hiệu Shade & Caliber bên hông hộp::Bước 2::Đảm bảo toàn bộ các thùng dùng cho cùng một mặt sàn có chuỗi ký tự Shade và Caliber giống hệt nhau. | Lắc nhẹ kiểm tra góc cạnh kiện hàng::Bước 3::Tách riêng ngay bất kỳ thùng nào bị móp góc đai hoặc có tiếng cọ mảnh vỡ bên trong để lập biên bản bù đổi. | Mở kiểm tra ngẫu nhiên 4 viên trải dưới sáng::Bước 4::Ghép thử 4 viên từ 2 thùng khác nhau trên nền phẳng để kiểm tra cạnh mài và sắc độ thực tế trước khi ký nhận."]

[drv_faq open_first="1"]
[drv_faq_item q="Mã Shade và mã Lot có phải là một không?"]Không giống nhau. Trong cùng một đợt sản xuất (Lot), sự dao động nhiệt đầu ca và cuối ca vẫn có thể tạo ra hai nhóm sắc độ (Shade) khác nhau. Khi nghiệm thu tính đồng màu, thông số quyết định trực tiếp là mã Shade cùng mã Caliber.[/drv_faq_item]
[drv_faq_item q="Giữ lại vỏ thùng sau khi lát xong để làm gì?"]Bạn nên chụp ảnh lại tem thông số của 1 vỏ thùng cho mỗi phòng và cất cùng hồ sơ hoàn công. Nếu vài năm sau cần cải tạo đường ống âm sàn, bức ảnh này giúp tra cứu chính xác quy cách và tông màu gốc.[/drv_faq_item]
[/drv_faq]
HTML,
    ],
    [
        'slug'        => 'chon-gach-lat-san-vuon-va-gara-o-to-chiu-tai-chong-truot-r11-r12',
        'title'       => 'Chọn gạch lát sân vườn và gara ô tô chịu tải: độ dày xương đá và cấp nhám R11 - R12',
        'excerpt'     => 'Tiêu chí chọn độ dày xương gạch Porcelain từ 10 - 20 mm, kích thước 30x60 - 60x60 cm và bề mặt chống trượt R11 - R12 chịu mưa nắng ngoài trời.',
        'cat'         => 'chon-gach-theo-phong',
        'is_pillar'   => '0',
        'collections' => ['gach-san-vuon', 'gach-ngoai-troi', 'gach-porcelain', 'gach-60x60'],
        'takeaways'   => [
            'Khu vực sân đỗ ô tô con 4 - 7 chỗ cần gạch xương đá Porcelain dày tối thiểu 10 - 12 mm (hoặc dòng chuyên dụng 20 mm) cán trên nền bê tông cốt thép đầm kỹ.',
            'Sân hiên không mái che tiếp xúc trực tiếp nước mưa và rêu ẩm cần bề mặt nhám định hình đạt R11 (sân phẳng) hoặc R12 (ram dốc lên xuống).',
            'Ngoài trời chịu giãn nở nhiệt lớn nên mạch ron bắt buộc rộng 3 - 5 mm kèm khe co giãn định kỳ mỗi 12 - 16 m² sàn.',
        ],
        'ai_prompts'  => [],
        'tags'        => ['Gạch sân vườn', 'Chống trượt R11', 'Gạch chịu lực'],
        'content'     => <<<HTML
<h2>1. Ba tác động khắc nghiệt lên gạch lát ngoài trời tại Việt Nam</h2>
<p>Khác với nền trong nhà luôn ổn định nhiệt độ, mặt sân trước và sân thượng phải chịu chênh lệch nhiệt từ gần 50 độ C dưới nắng gắt buổi trưa xuống 25 độ C ngay khi mưa rào đổ xuống, cộng thêm tải trọng tập trung dưới bánh lốp xe ô tô đánh lái tại chỗ.</p>
<p>Gạch Ceramic xương đất nung xốp (hút nước trên 6%) lát ngoài trời rất nhanh bị rạn men chân chim, rêu bám xanh mạch và nứt góc. Vật liệu bắt buộc cho khu vực này là <strong>Porcelain đồng chất hoặc xương đá nén lực cao</strong>.</p>

<h2>2. Chọn khổ gạch và độ dày cho lối xe chạy</h2>
<p>Với cùng một chất liệu xương đá, viên gạch có diện tích nhỏ hơn và dày hơn sẽ chịu uốn gãy tốt hơn khi bánh xe đi qua vị trí lớp keo bên dưới không đặc hoàn toàn:</p>
<ul>
<li><strong>Sân đi bộ, ban công, sân thượng:</strong> Khổ 30x60 hoặc 60x60 cm, dày 9,5 - 10 mm, cấp nhám <strong>R11</strong>.</li>
<li><strong>Sân đỗ ô tô gia đình:</strong> Khổ 30x60 hoặc 60x60 cm xương dày <strong>11 - 20 mm</strong>, trải keo phủ kín 100% mặt lưng trên bản bê tông cốt thép phẳng, tuyệt đối không lát trên nền cát đầm thủ công.</li>
<li><strong>Ram dốc gara ngoài trời:</strong> Chọn bề mặt sần định hình hoặc xẻ rãnh đạt <strong>R12</strong> để bám lốp và an toàn khi đi bộ trời mưa.</li>
</ul>

[drv_products collection="gach-lat-nen" skus="{$sku_1},{$sku_3}" limit="4" layout="grid" title="Mẫu gạch xương đá nhám mờ phù hợp khu vực bán lộ thiên & sân hiên"]

[drv_lead_form type="consult" title="Gửi diện tích sân vườn / gara để kỹ thuật viên tư vấn độ dày xương gạch"]

[drv_faq open_first="1"]
[drv_faq_item q="Tại sao lát gạch sân thượng rất dễ bị phồng rộp bung lên sau 1 - 2 mùa nắng?"]Nguyên nhân chính là lát quá khít mạch (dưới 2 mm) và không chừa khe co giãn dọc chân tường lan can. Dưới nắng nóng, mảng gạch giãn nở không có khoảng thở sẽ tự đội vồng lên ở giữa sân. Ngoài trời cần để ron 3 - 5 mm và chừa khe đàn hồi 8 - 10 mm sát tường bơm keo trám khe ngoài trời.[/drv_faq_item]
[drv_faq_item q="Bề mặt R11 ngoài trời bị bám bụi đất đỏ vệ sinh bằng cách nào?"]Dùng vòi xịt áp lực gia đình kết hợp chổi cước cứng vừa phải theo chiều thoát nước là sạch hoàn toàn mà không ảnh hưởng đến lớp xương đá Porcelain.[/drv_faq_item]
[/drv_faq]
HTML,
    ],
];

$created_post_ids = [];
foreach ($seed_posts as $item) {
    $existing = get_page_by_path($item['slug'], OBJECT, 'post');
    $cat_id   = $cat_ids[$item['cat']] ?? (int) get_option('default_category', 1);

    $post_arr = [
        'post_type'      => 'post',
        'post_name'      => $item['slug'],
        'post_title'     => $item['title'],
        'post_excerpt'   => $item['excerpt'],
        'post_content'   => $item['content'],
        'post_status'    => 'publish',
        'comment_status' => 'closed',
        'ping_status'    => 'closed',
        'post_category'  => [$cat_id],
    ];

    if ($existing instanceof WP_Post) {
        $post_arr['ID'] = $existing->ID;
        $pid            = (int) wp_update_post($post_arr);
    } else {
        $pid = (int) wp_insert_post($post_arr);
    }

    if ($pid <= 0) {
        continue;
    }

    $created_post_ids[] = $pid;
    wp_set_post_terms($pid, $item['tags'], 'post_tag', false);

    update_post_meta($pid, 'drv_is_pillar', $item['is_pillar']);
    update_post_meta($pid, 'drv_toc_enabled', '1');
    update_post_meta($pid, 'drv_key_takeaways', (string) wp_json_encode($item['takeaways'], JSON_UNESCAPED_UNICODE));
    update_post_meta($pid, 'drv_related_collections', (string) wp_json_encode($item['collections'], JSON_UNESCAPED_UNICODE));
    update_post_meta($pid, 'drv_ai_prompts', (string) wp_json_encode($item['ai_prompts'], JSON_UNESCAPED_UNICODE));
    update_post_meta($pid, 'drv_reading_time', ReadingTime::calculateMinutes($item['content']));
    update_post_meta($pid, 'rank_math_primary_category', $cat_id);
}

// Liên kết chéo `drv_related_posts` cho bài Pillar đầu tiên.
if (count($created_post_ids) >= 3) {
    update_post_meta(
        $created_post_ids[0],
        'drv_related_posts',
        (string) wp_json_encode(array_slice($created_post_ids, 1, 3))
    );
}

flush_rewrite_rules(false);

if (class_exists('WP_CLI')) {
    WP_CLI::success(sprintf(
        'Step 10 seeded: %d categories, 5 policy pages, /gioi-thieu template, %d technical blog posts.',
        count($cat_ids),
        count($created_post_ids)
    ));
}
