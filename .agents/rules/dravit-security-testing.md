# Dravit Security & Testing Rules

> Áp dụng cho toàn bộ quy trình phát triển, kiểm thử và nghiệm thu từng Phase trên `gachdravit.vn`.
> Tham chiếu bắt buộc: `docs/02_Technical_Architecture.md` §9, `docs/09_Roadmap_QA_Testplan.md`.

---

## 1. Quy tắc Bảo mật Bắt buộc (Security Hard Rules)

### 1.1 Ngăn truy cập trực tiếp & Ẩn thông tin hệ thống
- Mọi file PHP xử lý logic phải có `defined('ABSPATH') || exit;` ngay đầu file.
- Gỡ `wp_generator`, bật `wc_remove_generator` của Rank Math; không để lộ phiên bản WordPress/WooCommerce/PHP trong HTML hoặc REST header.

### 1.2 Sanitize Input & Whitelist Validation
- **Không bao giờ tin dữ liệu từ client** (`$_GET`, `$_POST`, REST params, cookies).
- Với tham số bộ lọc trên URL và REST `/wp-json/dravit/v1/catalog`:
  - Chỉ chấp nhận các key nằm trong `config/facets.php`.
  - Mỗi slug giá trị phải qua `sanitize_title()` và kiểm tra tồn tại trong taxonomy/config tương ứng.
  - Giới hạn cứng `per_page <= 48`, `page >= 1`.
- Với Checkout, Báo giá BOQ (`/wp-json/dravit/v1/quote/submit`) và Form Lead (`/wp-json/dravit/v1/lead` → `drv_lead`):
  - Dưới LiteSpeed Page Cache, lấy token tươi qua `GET /wp-json/dravit/v1/nonce` (`Cache-Control: no-store`) trước khi gửi (`D-30`), kiểm tra `wp_verify_nonce()` + `Origin` header.
  - Validate SĐT Việt Nam bằng regex `^(0|\+84)(3|5|7|8|9)\d{8}$`; MST (khi tick VAT) bằng regex `^\d{10}(-\d{3})?$`.
  - Áp dụng honeypot (`drv_website`) + time-trap (`≥ 3s`) + giới hạn tần suất gửi (rate-limit bằng transient theo IP) + giới hạn số dòng BOQ (`≤ 30` hạng mục). **Server bắt buộc tính lại 100% số lượng & đơn giá**, không tin số tiền từ client.

### 1.3 Escape Output & Database Safety
- Mọi biến PHP xuất ra HTML bắt buộc dùng đúng hàm escape ngay tại điểm xuất:
  - Văn bản thường: `esc_html()`
  - Thuộc tính HTML: `esc_attr()`
  - Đường dẫn URL: `esc_url()`
  - Nội dung bài viết/SEO footer từ trình soạn thảo: `wp_kses_post()`
  - Dữ liệu JSON nhúng vào HTML/script: `wp_json_encode($data, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE)`
- Mọi câu lệnh SQL trực tiếp (như đếm số lượng facet trong `FacetCounter`) **BẮT BUỘC** dùng `$wpdb->prepare()`.
- Các thao tác Admin (Resync Catalog, Order Action xác nhận đơn): bắt buộc kiểm tra `current_user_can('manage_woocommerce')` + `check_admin_referer()`.

---

## 2. Quy tắc Kiểm thử & Nghiệm thu theo Phase (Mandatory Verification)

> Không được chuyển sang Phase tiếp theo nếu bất kỳ kiểm thử bắt buộc nào của Phase hiện tại chưa đạt 100%.

### Phase 1 — Theme Skeleton & Brand System
- [ ] Kích hoạt theme `dravit` không phát sinh bất kỳ PHP Warning/Notice/Deprecated nào trong `debug.log`.
- [ ] `tokens.json` biên dịch ra đúng `tokens.css` và `theme.json`; Vite build ra `assets/dist/` có `.vite/manifest.json`.
- [ ] Đã gỡ toàn bộ CSS mặc định của WooCommerce (`woocommerce-layout`, `woocommerce-smallscreen`, `woocommerce-general`) và `wp-block-library` trên các trang cửa hàng.
- [ ] Điểm Lighthouse Mobile trên khung trang chủ/trang trống đạt **≥ 98**, CLS = `0`.

### Phase 2 — Catalog, 28 Collections & Đơn vị Giá
- [ ] Chạy seeder tạo đủ 28 `product_cat`, các thuộc tính `pa_*` và nạp 8 SKU mẫu từ tài liệu Silo.
- [ ] **Kiểm thử 8 SKU mẫu:** Mỗi SKU tự động xuất hiện chính xác tại danh sách URL danh mục tĩnh và có đúng `Primary Category` như bảng tại `docs/09_Roadmap_QA_Testplan.md` §3.1 (riêng `DRV-TRZ60` xuất hiện ở 11 URL bao gồm `/gach-porcelain`).
- [ ] **Kiểm thử công thức giá:** Nhập `800×800 mm`, `3 viên/thùng`, `385.000đ/m²` → tự tính `drv_m2_per_box = 1,92`, giá WC `_regular_price = 246.400` (`đ/viên`), hiển thị chuẩn cả 2 mức giá.
- [ ] Tạo thử Page có slug `gach-80x80` → `SlugGuard` tự động đổi thành `gach-80x80-trang`.

### Phase 3 — Trang Danh mục, Bộ lọc AJAX & Ma trận SEO
- [ ] Truy cập `/gach-lat-nen` → `200 OK`, `robots = index, follow`, `canonical = https://gachdravit.vn/gach-lat-nen`, có `CollectionPage` + `ItemList` JSON-LD.
- [ ] Truy cập `/gach-lat-nen?kich-thuoc=60x60` → `robots = noindex, follow`, `canonical = https://gachdravit.vn/gach-lat-nen`, không có `ItemList`, không sinh thêm thẻ `<a href>` lọc tổ hợp.
- [ ] Truy cập `/gach-nha-tam?dcof=r10` → `robots = noindex, nofollow`, `canonical = https://gachdravit.vn/gach-nha-tam`.
- [ ] Lọc AJAX, nút Load More và nút Back/Forward trình duyệt hoạt động mượt mà; khi tắt JavaScript trên trình duyệt, bộ lọc và phân trang vẫn hoạt động bình thường qua GET.

### Phase 4 & 5 — Trang Sản phẩm, Checkout VN & Luồng Đơn hàng
- [ ] Bộ chuyển đổi `m² ⇄ Viên` trên trang sản phẩm tính đúng số viên, số thùng, diện tích thực nhận và tổng tiền (VD: `28 m²` + `5% hao hụt` + `chẵn thùng` cho gạch 80x80 3 viên/thùng → `48 viên = 16 thùng = 30,72 m²`).
- [ ] Bảng thông số kỹ thuật tự động gắn link `<a href>` về đúng các URL danh mục tĩnh (`IL-03`); Rich Results Test cho `Product` + `BreadcrumbList` hợp lệ 100% (0 lỗi).
- [ ] Checkout chọn Tỉnh/TP tải đúng danh sách Phường/Xã; đặt đơn COD và BACS đều vào trạng thái **Chờ xác nhận (`wc-cho-xac-nhan`)**, hiển thị *"Phí vận chuyển: báo sau khi xác nhận đơn"*.
- [ ] Trong Admin, thêm phí vận chuyển + bấm **"Xác nhận đơn & gửi email cho khách"** → đơn BACS chuyển sang **Chờ chuyển khoản** và email xuất hiện mã **VietQR** đúng tổng tiền cuối + nội dung `DRV{mã_đơn}`.

### Phase 6 — QA Toàn diện & Go-Live
- [ ] PageSpeed Insights Mobile đạt **≥ 90** trên cả 5 loại URL: Trang chủ, `/gach`, URL có lọc, Trang sản phẩm, Bài viết Cẩm nang.
- [ ] Kiểm tra WCAG 2.2 AA: điều hướng bàn phím, focus ring, nhãn form, độ tương phản màu đạt 100%.

