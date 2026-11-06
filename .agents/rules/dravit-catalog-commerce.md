# Dravit Catalog, Pricing Units & Commerce Rules

> Áp dụng cho dữ liệu sản phẩm, danh mục, đơn vị bán, giỏ hàng, checkout và đơn hàng.
> Tham chiếu bắt buộc: `docs/03_Catalog_Data_Model.md`, `docs/04_Pricing_Units_Commerce.md`, `docs/07_UI_UX_Page_Specs.md`.

---

## 1. Cấu trúc Catalog & Tự gán 28 Smart Collections (`Catalog`)

- **1 SKU = 1 Simple Product = 1 URL duy nhất:** `/san-pham/{slug}`. Các mẫu cùng dòng khác kích thước/bề mặt liên kết qua meta `drv_series` để hiển thị swatch dạng thẻ `<a href>`.
- **28 Smart Collections = `product_cat`:**
  - Danh mục gốc: `gach` (`/gach`) và 27 danh mục con trực tiếp (7 Công năng, 8 Không gian, 7 Vân/Chất liệu, 5 Kích thước).
  - Bật `wc_remove_category_base` + `wc_remove_category_parent_slugs` trong Rank Math để tạo URL phẳng `/{slug}`.
- **Tự động gán danh mục (`Catalog\CollectionSync`):**
  - Chạy trên hook `woocommerce_after_product_object_save` và khi bắt đầu/kết thúc khuyến mãi.
  - Tự sinh các thuộc tính dẫn xuất trước khi khớp rule: `pa_kich-thuoc` (từ `drv_piece_w_mm`, `drv_piece_h_mm`), `pa_do-day` (từ `drv_thickness_mm`), `pa_do-hut-nuoc` (từ `drv_water_absorption`), `drv_price_band` (từ `drv_price_m2`), cờ `large_format` (cạnh dài > 1200mm).
  - Giữ nguyên các danh mục ngoài hệ thống 28 collection mà admin gán tay; dùng `wp_set_object_terms()` kèm cờ chống vòng lặp save.
- **Primary Category (`rank_math_primary_product_cat`):** Quyết định Breadcrumb cố định duy nhất cho mỗi sản phẩm (`Trang chủ / Gạch / {Primary Category} / {Tên SP}`). Nếu trống, tự chọn theo `priority` trong `config/collections.php`.
- **SlugGuard:** Chặn tạo Page/Post trùng slug với 28 collection và các đường dẫn hệ thống.

---

## 2. Hệ Đơn vị Bán & Công thức Giá (`Units`)

- **Mô hình giá mở rộng (`config/units.php`):** Hỗ trợ `area_pack` (gạch), `piece` (chiếc), `set` (bộ), `pack` (tuýp/bao).
- **Quy tắc bất biến cho Gạch (`area_pack`):**
  - **Giá hiển thị chính:** `đ/m²` (`drv_price_m2`, `drv_sale_price_m2`).
  - **Giá hiển thị phụ & Đơn vị giao dịch trong WooCommerce:** `đ/viên` (số lượng trong giỏ và tồn kho luôn là **số nguyên viên**).
  - **Công thức tự động khi lưu sản phẩm:**
    ```text
    area_piece (m²)   = (drv_piece_w_mm * drv_piece_h_mm) / 1_000_000
    drv_m2_per_box    = round(area_piece * drv_pieces_per_box, 4)
    _regular_price    = round(drv_price_m2 * area_piece)
    _sale_price       = round(drv_sale_price_m2 * area_piece)
    ```
- **Hiển thị giá thống nhất:** Mọi template bắt buộc gọi `drv_price_html($product, $context)` và `drv_qty_summary($product, $qty)`, **CẤM** tự viết công thức tính giá lẻ tẻ trong từng file template.
- **Bộ chuyển đổi Kép `m² ⇄ Viên` trực tiếp (Trang sản phẩm):**
  - Cho phép nhập `m²` hoặc `viên` liên kết 2 chiều, có checkbox *Cộng 5% hao hụt* và *Làm tròn chẵn thùng*, hiển thị trực tiếp: `Thực nhận: {X} viên = {Y} thùng ({Z} m²)` và **`Tổng tạm tính`** cập nhật tức thì.
- **Snapshot vào Order Item Meta:** Khi tạo đơn, lưu `_drv_model`, `_drv_unit`, `_drv_price_m2`, `_drv_area_piece`, `_drv_pieces_per_box`, `_drv_m2_qty` vào dòng đơn hàng để lịch sử đơn không đổi khi sửa giá sản phẩm.

---

## 3. Vận chuyển, Checkout VN & Luồng Đơn hàng (`Woo`)

- **Địa chỉ Việt Nam 2 cấp (từ 01/07/2025):** Chỉ bán tại Việt Nam. Trường địa chỉ gồm: `Tỉnh/Thành phố` (34 tỉnh/thành trong `config/provinces-vn.php`) → `Phường/Xã` (nạp lazy từ `assets/data/wards/{code}.json`) → `Địa chỉ cụ thể`. Ẩn Postcode, Company, Last name.
- **Vận chuyển báo phí sau xác nhận:**
  - Dùng phương thức `drv_quote_later` (phí 0), thay nhãn `"0đ"` bằng **"Phí vận chuyển: báo sau khi xác nhận đơn"**.
- **Trạng thái đơn & VietQR:**
  - Mọi đơn mới (cả COD và Chuyển khoản BACS) đều vào trạng thái **`wc-cho-xac-nhan` (Chờ xác nhận)** và trừ tồn kho ngay.
  - Gỡ bỏ thông tin tài khoản/QR mặc định của BACS ở màn hình Cảm ơn khi đơn chưa có phí vận chuyển.
  - Khi nhân viên thêm phí vận chuyển trong Admin và chạy Order Action **"Xác nhận đơn & gửi email cho khách"**:
    - Đơn BACS chuyển sang `on-hold` (Chờ chuyển khoản) và gửi email kèm mã **VietQR** tự sinh đúng tổng tiền cuối + nội dung `DRV{mã_đơn}`.
    - Đơn COD chuyển sang `processing` (Đang xử lý).

