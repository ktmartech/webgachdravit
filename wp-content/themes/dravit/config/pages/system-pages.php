<?php
/**
 * Nội dung các trang văn bản hệ thống (chân trang: Hướng dẫn · Chính sách · Đối tác).
 *
 * - Không viết cứng thông tin thương hiệu: mọi tên, số liên hệ, giờ làm việc, SLA, thông số đơn hàng đều gọi
 *   `[drv_info key="..."]` (đọc `drv_settings`), nên sửa ở Admin → Cài đặt Hệ thống DRAVIT là toàn bộ trang tự đồng bộ.
 * - Nạp bởi `tools/setup-pages.php` (idempotent, không đè nội dung đã được biên tập tay).
 *
 * @return array<string, array{title: string, template: string, group: string, body: string}>
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

$policy_tpl = 'page-templates/template-policy.php';

return [
    // ---------------------------------------------------------------------
    // HƯỚNG DẪN
    // ---------------------------------------------------------------------
    'huong-dan-mua-hang' => [
        'title'    => 'Quy trình mua gạch & tính thùng',
        'template' => $policy_tpl,
        'group'    => 'guide',
        'body'     => <<<'HTML'
<h2>1. Chọn mẫu và đối chiếu thông số</h2>
<p>Mỗi mã gạch tại [drv_info key="brand"] công khai kích thước thực tế, độ hút nước, cấp chống trượt R9 - R13, số viên và diện tích của một thùng. Bạn lọc theo công năng, không gian, vân gạch hoặc kích thước; nhà tắm và sân vườn nên chọn từ cấp R10 trở lên.</p>
<p>Muốn xem bề mặt và sắc độ thật dưới đèn 3000K - 6500K, mời bạn đến [drv_info key="showroom_name"] tại [drv_info key="showroom_address"]. Giờ mở cửa: [drv_info key="showroom_hours"].</p>

<h2>2. Tính số thùng từ diện tích phòng</h2>
<p>Trang sản phẩm có máy tính quy đổi giữa m², viên và thùng. Công thức gọn như sau:</p>
<ol>
<li>Diện tích cần lát (m²) nhân với hệ số hao hụt: [drv_info key="waste_pct"]% cho kiểu lát thẳng, 7% cho lát so le, 10% cho lát xương cá hoặc phòng nhiều góc cắt.</li>
<li>Chia cho diện tích phủ của một thùng (m²/thùng).</li>
<li>Làm tròn lên thành số thùng nguyên. Gạch được bán theo thùng đai nhà máy để giữ nguyên mã lô.</li>
</ol>
<p>Ví dụ: sàn 35 m², lát thẳng, thùng 3 viên phủ 1,92 m². Cần 35 × 1,05 = 36,75 m², chia 1,92 được 19,14 nên đặt 20 thùng.</p>

<h2>3. Lập danh sách báo giá cho công trình nhiều phòng</h2>
<p>Với nhà phố, căn hộ hay công trình có nhiều hạng mục, hãy thêm từng mã vào <a href="/danh-sach-bao-gia">Danh sách báo giá (BOQ)</a>. Hệ thống tự cộng khối lượng theo phòng, quy đổi ra thùng và tổng trọng lượng. Khi gửi danh sách, bộ phận kỹ thuật phản hồi [drv_info key="sla"].</p>
<p>Chưa biết chọn khổ nào? Hãy hỏi <a href="/tro-ly-ai-tim-gach">Trợ lý AI tìm gạch</a>: nói diện tích, không gian và ngân sách, trợ lý gợi ý mã phù hợp kèm số thùng.</p>

<h2>4. Đặt hàng và xác nhận đơn</h2>
<ol>
<li>Thêm gạch vào giỏ, điền địa chỉ công trình (Tỉnh/Thành phố → Phường/Xã) và thông tin xuất hóa đơn VAT nếu cần.</li>
<li>Đơn tiếp nhận ở trạng thái <strong>Chờ xác nhận</strong> và tồn kho được giữ ngay. Phí vận chuyển chưa tính ở bước này.</li>
<li>Nhân viên kiểm tra tồn kho cùng mã lô, báo phí vận chuyển theo khối lượng và gọi xác nhận.</li>
<li>Đơn chuyển khoản nhận mã VietQR sau khi xác nhận; đơn COD chuyển sang chuẩn bị giao hàng.</li>
</ol>
<p>Chi tiết xem thêm tại <a href="/chinh-sach-van-chuyen">chính sách vận chuyển</a> và <a href="/chinh-sach-thanh-toan">chính sách thanh toán</a>.</p>

<h2>5. Nhận hàng và thi công</h2>
<p>Khi nhận hàng, bạn kiểm tra số thùng, mã SKU, mã lô (Lot), mã sắc độ (Shade) và cỡ (Caliber) in trên vỏ thùng. Trước khi dán hàng loạt, hãy trải thử 4 - 6 viên dưới ánh sáng đầy đủ để xem vân và độ phẳng. Cần hỗ trợ kỹ thuật, gọi [drv_info key="hotline" link="tel"] ([drv_info key="hours"]) hoặc nhắn Zalo [drv_info key="zalo" link="zalo"].</p>
HTML,
    ],

    // ---------------------------------------------------------------------
    // CHÍNH SÁCH
    // ---------------------------------------------------------------------
    'chinh-sach-van-chuyen' => [
        'title'    => 'Chính sách vận chuyển & giao nhận',
        'template' => $policy_tpl,
        'group'    => 'policy',
        'body'     => <<<'HTML'
<h2>1. Báo phí vận chuyển sau khi xác nhận đơn</h2>
<p>Gạch ốp lát và đá nung kết nặng từ 30 đến hơn 50 kg mỗi thùng, nên phí vận chuyển phụ thuộc trực tiếp vào tổng khối lượng, loại xe và đường vào công trình. Vì vậy [drv_info key="brand"] không ghi mức phí ước tính ở bước đặt hàng. Sau khi nhận đơn, nhân viên kiểm tra tồn kho, tính xe phù hợp rồi báo phí cụ thể trước khi bạn thanh toán.</p>
<ul>
<li>Xe tải 1 tấn cho đơn dưới 1.000 kg.</li>
<li>Xe tải 2,5 tấn cho đơn đến 2.500 kg.</li>
<li>Xe tải 5 tấn cho đơn đến 5.000 kg; đơn lớn hơn được chia chuyến.</li>
</ul>

<h2>2. Phạm vi và thời gian giao hàng</h2>
<h3>Nội thành TP. Hồ Chí Minh</h3>
<p>Xe tải nhẹ giao tận chân công trình, thường trong 24 giờ làm việc sau khi chốt mã lô xuất kho.</p>
<h3>Các tỉnh lân cận</h3>
<p>Giao bằng xe tải nguyên chuyến hoặc ghép chuyến trong 24 - 48 giờ.</p>
<h3>Tuyến liên tỉnh</h3>
<p>Điều phối xe tải hoặc chành xe uy tín trong 2 - 4 ngày. Gạch được đóng kiện gia cố góc cho đường dài.</p>

<h2>3. Điều kiện hạ hàng tại công trình</h2>
<p>Xe dừng ở vị trí tiếp cận an toàn gần nhất. Nếu hẻm nhỏ, hầm chung cư giới hạn chiều cao hoặc cần đăng ký thang hàng, bạn ghi chú trong đơn để chúng tôi sắp xếp xe và nhân sự phù hợp. Nhân viên giao hàng sẽ gọi trước 60 phút.</p>

<h2>4. Đồng kiểm khi nhận hàng</h2>
<p>Bạn hoặc người giám sát công trình kiểm tra số thùng còn nguyên đai, mã SKU, mã lô (Lot), mã sắc độ (Shade) và cỡ (Caliber) trên vỏ thùng trước khi ký nhận. Thùng nứt vỡ phát hiện lúc hạ hàng cần được chụp ảnh và ghi vào phiếu giao; xử lý theo <a href="/chinh-sach-doi-tra">chính sách đổi trả</a>.</p>
<p>Cần hỗ trợ lịch giao, gọi [drv_info key="hotline" link="tel"] trong giờ làm việc ([drv_info key="hours"]).</p>
HTML,
    ],

    'chinh-sach-doi-tra' => [
        'title'    => 'Chính sách đổi trả & bảo hành lô màu',
        'template' => $policy_tpl,
        'group'    => 'policy',
        'body'     => <<<'HTML'
<h2>1. Nhận lại thùng gạch dư</h2>
<p>[drv_info key="brand"] nhận lại hoặc đổi mẫu thùng gạch dư sau thi công trong <strong>07 ngày</strong> kể từ ngày giao, khi đáp ứng đủ các điều kiện:</p>
<ul>
<li>Thùng còn nguyên đai nhà máy, vỏ khô ráo, không rách hay dính vữa, keo.</li>
<li>Viên gạch chưa ngâm nước, chưa cắt mài, men bề mặt không trầy xước.</li>
<li>Không nhận trả lẻ từng viên và hàng đã cắt gia công theo kích thước riêng (len chân tường, bậc cầu thang).</li>
</ul>

<h2>2. Gạch nứt vỡ khi vận chuyển</h2>
<p>Nếu phát hiện cạnh nứt hoặc viên vỡ lúc hạ hàng, bạn chụp ảnh cùng nhân viên giao nhận và ghi vào phiếu giao hàng. Chúng tôi bù đổi số viên tương ứng thuộc cùng mã sắc độ (Shade) trong chuyến giao kế tiếp, không thu thêm phí.</p>

<h2>3. Bảo hành lô màu</h2>
<p>Gạch nung ở nhiệt độ cao nên các lô sản xuất khác nhau có thể lệch sắc độ nhẹ. Khi bạn đặt đủ khối lượng trong một đơn, kho xuất cùng một mã lô (Lot), cùng sắc độ (Shade) và cỡ (Caliber).</p>
<p>Với công trình cần bổ sung vật tư về sau, hãy gửi mã SKU và mã lô của đợt trước cho chúng tôi để kiểm tra tồn kho cùng lô. Khuyến nghị đặt dư [drv_info key="waste_pct"]% - 10% ngay từ đầu để không phải bổ sung.</p>

<h2>4. Kiểm tra trước khi thi công hàng loạt</h2>
<p>Bạn nên trải thử 4 - 6 viên dưới ánh sáng đầy đủ để kiểm tra vân và độ phẳng.</p>
<p>Gửi yêu cầu đổi trả qua [drv_info key="email" link="mail"] hoặc [drv_info key="hotline" link="tel"], kèm mã đơn, ảnh thùng và mã lô.</p>
HTML,
    ],

    'chinh-sach-thanh-toan' => [
        'title'    => 'Chính sách thanh toán & xuất hóa đơn VAT',
        'template' => $policy_tpl,
        'group'    => 'policy',
        'body'     => <<<'HTML'
<h2>1. Từ lúc đặt hàng đến lúc thanh toán</h2>
<p>Đơn hàng mới ở trạng thái <strong>Chờ xác nhận</strong>, tồn kho được giữ ngay. Nhân viên kiểm tra số thùng cùng mã sắc độ (Shade) và cỡ (Caliber), báo phí vận chuyển rồi xác nhận tổng giá trị đơn. Bạn chỉ thanh toán sau khi nhận được xác nhận này.</p>

<h2>2. Hình thức thanh toán</h2>
<h3>Chuyển khoản qua mã VietQR</h3>
<p>Đơn được xác nhận sẽ chuyển sang trạng thái chờ chuyển khoản và hiển thị mã VietQR đúng số tiền, đã gồm phí vận chuyển. Nội dung chuyển khoản có dạng <code>DRV</code> kèm mã đơn để đối soát. Thông tin nhận tiền:</p>
<ul>
<li>Ngân hàng: [drv_info key="bank_name"] ([drv_info key="bank_branch"])</li>
<li>Chủ tài khoản: [drv_info key="bank_account_name"]</li>
</ul>
<p>Đơn chưa thanh toán được giữ hàng trong [drv_info key="transfer_due_days"] ngày; sau [drv_info key="auto_cancel_hours"] giờ không có thanh toán, hệ thống tự hủy đơn và trả tồn kho về lại.</p>
<h3>Thanh toán khi nhận hàng (COD)</h3>
<p>Áp dụng theo hạn mức do nhân viên xác nhận khi gọi điện, hoặc cho phần còn lại sau khi đặt cọc giữ lô hàng.</p>

<h2>3. Hóa đơn VAT</h2>
<p>Đơn hàng được xuất hóa đơn điện tử theo thông tin bạn nhập ở bước thanh toán: tên đơn vị, mã số thuế, địa chỉ và email nhận hóa đơn. Hóa đơn do [drv_info key="legal_name"] (MST [drv_info key="tax_id"]) phát hành. Hãy nhập đúng thông tin ngay khi đặt hàng, vì hóa đơn đã phát hành khó điều chỉnh.</p>
<p>Cần hỗ trợ về thanh toán, liên hệ [drv_info key="email" link="mail"] hoặc [drv_info key="hotline" link="tel"].</p>
HTML,
    ],

    'chinh-sach-bao-mat' => [
        'title'    => 'Chính sách bảo mật thông tin',
        'template' => $policy_tpl,
        'group'    => 'policy',
        'body'     => <<<'HTML'
<h2>1. Thông tin chúng tôi thu thập</h2>
<p>[drv_info key="legal_name"] chỉ thu thập thông tin bạn tự cung cấp khi đặt hàng, lập danh sách báo giá hoặc gửi yêu cầu tư vấn: họ tên, số điện thoại, email, địa chỉ giao hàng, thông tin xuất hóa đơn VAT và nội dung bạn muốn trao đổi.</p>

<h2>2. Mục đích sử dụng</h2>
<ul>
<li>Xác nhận tồn kho cùng mã lô, báo phí vận chuyển và thông báo lịch giao hàng.</li>
<li>Phát hành hóa đơn, chứng từ và xử lý đổi trả, bảo hành.</li>
<li>Phản hồi yêu cầu báo giá và tư vấn kỹ thuật ([drv_info key="sla"]).</li>
<li>Lưu bảng khối lượng theo từng phòng để bổ sung vật tư đúng mã màu khi công trình phát sinh.</li>
</ul>
<p>Chúng tôi không bán hay chia sẻ thông tin của bạn cho bên thứ ba vì mục đích quảng cáo. Thông tin chỉ được chuyển cho đơn vị vận chuyển, ngân hàng hoặc cơ quan có thẩm quyền khi cần để hoàn tất đơn hàng hoặc theo quy định pháp luật.</p>

<h2>3. Lưu trữ và bảo vệ dữ liệu</h2>
<p>Dữ liệu đơn hàng được lưu theo thời hạn kế toán hiện hành. Yêu cầu báo giá chưa thành đơn được rà soát định kỳ. Biểu mẫu trên website dùng cơ chế chống spam và chỉ nhận dữ liệu qua kết nối bảo mật.</p>

<h2>4. Quyền của bạn</h2>
<p>Bạn có quyền yêu cầu xem, chỉnh sửa hoặc xóa thông tin cá nhân đã cung cấp, và rút lại sự đồng ý liên hệ bất kỳ lúc nào. Gửi yêu cầu tới [drv_info key="email" link="mail"] hoặc gọi [drv_info key="hotline" link="tel"]; chúng tôi xử lý trong thời gian sớm nhất. Trụ sở: [drv_info key="address"].</p>
HTML,
    ],

    'dieu-khoan' => [
        'title'    => 'Điều khoản sử dụng',
        'template' => $policy_tpl,
        'group'    => 'policy',
        'body'     => <<<'HTML'
<h2>1. Đơn vị niêm yết và đơn vị giao dịch</h2>
<p>Giá tham chiếu hiển thị theo đồng trên mét vuông (đ/m²) để bạn so sánh dự toán theo diện tích. Khi đặt hàng, số lượng được tính theo số nguyên viên hoặc thùng nguyên đai theo quy cách đóng gói của từng mã. Sản phẩm chưa niêm yết giá hiển thị "Liên hệ báo giá" và được báo giá riêng ([drv_info key="sla"]).</p>

<h2>2. Dung sai thông số và sắc độ</h2>
<p>Gạch Porcelain và đá nung kết có mã lô (Lot), sắc độ (Shade) và cỡ (Caliber) theo tiêu chuẩn TCVN 7745 và ISO 13006. Màu hiển thị trên màn hình có thể lệch so với thực tế dưới ánh sáng 3000K - 6500K. Với khối lượng lớn, bạn nên xem mẫu thật tại [drv_info key="showroom_name"] trước khi chốt.</p>

<h2>3. Giá và tồn kho</h2>
<p>Giá và tồn kho có thể thay đổi theo thời điểm. Đơn hàng chỉ có hiệu lực khi được [drv_info key="brand"] xác nhận ở bước kiểm tra tồn kho cùng mã lô. Giá vận chuyển được báo riêng sau xác nhận, theo <a href="/chinh-sach-van-chuyen">chính sách vận chuyển</a>.</p>

<h2>4. Nội dung kỹ thuật trên website</h2>
<p>Bảng quy đổi, định mức và hướng dẫn thi công là thông tin tham khảo, không thay thế thiết kế và chỉ định của đơn vị thi công. Nội dung thuộc quyền của [drv_info key="legal_name"]; khi trích dẫn, vui lòng ghi rõ nguồn và dẫn liên kết gốc.</p>

<h2>5. Liên hệ</h2>
<p>Mọi thắc mắc về điều khoản, gửi tới [drv_info key="email" link="mail"] hoặc gọi [drv_info key="hotline" link="tel"] ([drv_info key="hours"]).</p>
HTML,
    ],

    // ---------------------------------------------------------------------
    // ĐỐI TÁC & DOANH NGHIỆP
    // ---------------------------------------------------------------------
    'hop-tac-kts-nha-thau' => [
        'title'    => 'Hợp tác Kiến trúc sư & Nhà thầu',
        'template' => $policy_tpl,
        'group'    => 'guide',
        'body'     => <<<'HTML'
<h2>1. Dành cho ai</h2>
<p>Chương trình dành cho kiến trúc sư, đơn vị thiết kế nội thất, nhà thầu thi công và chủ đầu tư có nhu cầu gạch ốp lát cho nhà phố, biệt thự, căn hộ, showroom hoặc công trình thương mại. [drv_info key="brand"] cung cấp gạch Porcelain xương đá và đá nung kết khổ lớn có thông số công khai, đồng bộ mã lô cho toàn công trình.</p>

<h2>2. Bạn nhận được gì</h2>
<ul>
<li><strong>Bóc tách khối lượng:</strong> gửi mặt bằng hoặc danh sách phòng, kỹ thuật viên quy đổi ra m², viên, thùng và tổng trọng lượng, đã tính hao hụt theo kiểu lát.</li>
<li><strong>Giữ đồng bộ mã lô:</strong> kiểm tra tồn kho theo mã sắc độ (Shade) và cỡ (Caliber) để cả mặt sàn cùng một lô; giữ hàng theo tiến độ từng tầng khi cần.</li>
<li><strong>Báo giá theo dự án:</strong> bảng giá theo m² và theo thùng, điều kiện chiết khấu theo khối lượng, trao đổi trực tiếp với phòng dự án.</li>
<li><strong>Mẫu thật:</strong> xem bề mặt dưới đèn 3000K - 6500K tại [drv_info key="showroom_name"], hoặc nhận hộp mẫu để đối chiếu màu tại công trình.</li>
<li><strong>Hỗ trợ kỹ thuật:</strong> tư vấn cấp chống trượt R, chọn keo dán và đường ron phù hợp với khổ gạch.</li>
</ul>

<h2>3. Quy trình làm việc</h2>
<ol>
<li>Gửi yêu cầu qua biểu mẫu <a href="/lien-he">Liên hệ</a> hoặc lập <a href="/danh-sach-bao-gia">Danh sách báo giá (BOQ)</a> và gửi trực tiếp.</li>
<li>Kỹ thuật viên phản hồi [drv_info key="sla"], xác nhận khối lượng và tồn kho cùng lô.</li>
<li>Hai bên chốt mã gạch, báo giá và lịch giao theo tiến độ công trình.</li>
<li>Giao hàng, đồng kiểm tại chân công trình theo <a href="/chinh-sach-van-chuyen">chính sách vận chuyển</a>; xuất hóa đơn VAT theo thông tin đơn vị.</li>
</ol>

<h2>4. Liên hệ phòng dự án</h2>
<p>Email: [drv_info key="project_email" link="mail"]. Hotline: [drv_info key="hotline" link="tel"] ([drv_info key="hours"]). Zalo: [drv_info key="zalo" link="zalo"]. Địa chỉ: [drv_info key="showroom_address"].</p>
HTML,
    ],
];
