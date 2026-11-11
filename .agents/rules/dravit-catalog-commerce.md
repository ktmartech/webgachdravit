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
- **Hiển thị giá thống nhất & Chế độ Liên hệ báo giá (D-20):**
  - Mọi template bắt buộc gọi `drv_price_html($product, $context)` và `drv_qty_summary($product, $qty)`, **CẤM** tự viết công thức tính giá lẻ tẻ trong từng file template.
  - **Sản phẩm không niêm yết giá (`drv_price_m2` trống hoặc `≤ 0` - trạng thái `quote`):** Xóa `_regular_price`, `_sale_price`, `_price` về `''`, trả `is_purchasable = false`, tự gán `drv_price_band = gia-du-an`, không gắn chip `% giảm`, ẩn `Offer` giá 0đ trong JSON-LD. Trên Card/PDP hiển thị nhãn **"Liên hệ báo giá"**, giữ nguyên bộ quy đổi `m² ⇄ viên ⇄ thùng ⇄ kg` trong `<drv-unit-calc>` (ẩn dòng Tổng tiền) và đổi CTA sang **`Báo giá`** / **`Thêm vào DS báo giá`** (`data-drv-action="quote-add"`).
- **Cấu hình Đơn vị Mua Theo Từng Sản Phẩm (`m²` / `thùng` / `viên` - D-24):**
  - Trong tab *Đơn vị & Thông số* của từng SKU, Admin thiết lập:
    1. `drv_buy_units` (đa chọn `m2`, `thung`, `vien`): các tab đơn vị khách được chọn nhập trên PDP (`<drv-unit-calc>`) & Giỏ hàng (`<drv-qty-input>`).
    2. `drv_default_buy_unit` (`m2` | `thung` | `vien`): đơn vị mở sẵn mặc định & hành vi nút Thêm nhanh trên Card (`m2`/`thung` → thêm 1 thùng = `pieces_per_box` viên; `vien` → thêm `max(1, drv_qty_min)` viên).
    3. `drv_round_mode` (`box` = bắt buộc chẵn thùng, `drv_qty_step = drv_pieces_per_box` | `piece` = cho phép mua lẻ viên, `drv_qty_step = 1`) và `drv_qty_min` (tối thiểu tính theo viên).
  - Client (`assets/src/js/utils/calc.js`) và Server (`Dravit\Units\Calc`) dùng chung **1 lõi tính toán duy nhất**; giá trị `quantity` gửi vào WC luôn là **số nguyên viên**.
- **Snapshot vào Order Item Meta:** Khi tạo đơn, lưu `_drv_model`, `_drv_unit`, `_drv_price_m2`, `_drv_area_piece`, `_drv_pieces_per_box`, `_drv_kg_per_box`, `_drv_m2_qty`, `_drv_boxes`, `_drv_loose_pieces`, `_drv_kg`, `_drv_m2_input`, `_drv_waste_pct` và `_drv_lot_code` (kho điền khi xuất; tách bạch với `drv_shade` V1–V4 của SP theo `D-30`).

---

## 3. Vận chuyển, Checkout VN, BOQ & Luồng Đơn hàng (`Woo` & `Quote`)

- **Địa chỉ Việt Nam 2 cấp (từ 01/07/2025) & Hóa đơn VAT (`D-16`, `D-30`):** Chỉ bán tại Việt Nam. Trường địa chỉ gồm: `Tỉnh/Thành phố` (34 tỉnh/thành trong `config/provinces-vn.php`) → `Phường/Xã` (`billing_city` lưu tên + `_drv_ward_code` lưu mã, nạp lazy từ `assets/data/wards/{code}.json`, cấm autoselect phường đầu) → `Địa chỉ cụ thể`. Hỗ trợ checkbox xuất hóa đơn VAT (`_drv_vat_required/_company/_tax_id/_address/_email`). Không tick sẵn điều khoản.
- **Vận chuyển báo phí sau xác nhận:**
  - Dùng phương thức `drv_quote_later` (phí 0), thay nhãn `"0đ"` bằng **"Phí vận chuyển: báo sau khi xác nhận đơn"**.
- **Trạng thái đơn HPOS (`≤ 20` ký tự) & VietQR (`D-22`):**
  - Mọi đơn mới (cả COD và Chuyển khoản BACS) đều vào trạng thái **`wc-cho-xac-nhan` (Chờ xác nhận)** và trừ tồn kho ngay.
  - **CẤM** render ảnh VietQR ở trạng thái `wc-cho-xac-nhan` (trước khi có phí vận chuyển); cấm hứa đối soát ngân hàng tự động.
  - Khi nhân viên thêm phí vận chuyển trong Admin và chạy Order Action **"Xác nhận đơn & gửi email cho khách"**:
    - Đơn BACS chuyển sang `wc-on-hold` (đổi nhãn *Chờ chuyển khoản*) và hiển thị/gửi email kèm mã **VietQR** (`img.vietqr.io`) đúng tổng tiền cuối + nội dung `DRV{order_id}` (chỉ chữ số; mã đơn hiển thị `DRV-{order_id}`).
    - Đơn COD chuyển sang `wc-processing` (Đang xử lý).
- **Danh sách Báo giá Công trình BOQ (`/danh-sach-bao-gia`) & Lead (`D-23`):**
  - Bản nháp lưu `localStorage` (`drv_quote_v2`); khi gửi lưu vào CPT private `drv_lead` (`_drv_lead_type = boq`), server tính lại 100% số liệu. Bậc chiết khấu khối lượng chỉ hiển thị dạng *"Chiết khấu dự kiến"* trên BOQ, không tự trừ vào Giỏ hàng bán lẻ.

