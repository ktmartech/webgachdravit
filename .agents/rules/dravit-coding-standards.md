# Dravit Coding Standards & Documentation Rules

> Áp dụng cho mọi file PHP, JS, CSS trong `wp-content/themes/dravit/`.
> Tham chiếu bắt buộc: `docs/README.md`, `docs/02_Technical_Architecture.md`, `docs/10_Brand_Voice_And_Content_Guide.md`.

---

## 1. Tài liệu Tham chiếu Bắt buộc (Single Source of Truth)

Trước khi thực hiện bất kỳ task nào, **BẮT BUỘC** đọc file `docs/` tương ứng:

| Task | Tài liệu phải đọc |
|---|---|
| Quyết định dự án, phạm vi, ADR | `docs/00_Project_Brief_Decisions.md` |
| Màu sắc, font, spacing, component UI | `docs/01_Brand_Design_System.md` |
| Kiến trúc theme, Vite, cấu trúc file | `docs/02_Technical_Architecture.md` |
| Danh mục, thuộc tính, tự gán 28 URL, CSV | `docs/03_Catalog_Data_Model.md` + `docs/branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.md` |
| Giá/m², giá/viên, máy tính, giỏ, checkout, VietQR | `docs/04_Pricing_Units_Commerce.md` |
| Bộ lọc 14 facets, AJAX, load more, robots | `docs/05_Faceted_Filter_AJAX.md` |
| Rank Math, Schema JSON-LD, Internal Link, GEO | `docs/06_SEO_GEO_Spec.md` |
| Bố cục từng trang, wireframe, microcopy | `docs/07_UI_UX_Page_Specs.md` |
| Tốc độ, ảnh, font, LiteSpeed Cache | `docs/08_Performance_Hosting.md` |
| Lộ trình Phase 0–6, test cases nghiệm thu | `docs/09_Roadmap_QA_Testplan.md` |
| Giọng văn thương hiệu, từ vựng, chống văn AI | `docs/10_Brand_Voice_And_Content_Guide.md` |

---

## 2. Quy ước Đặt tên (Naming Conventions)

| Thành phần | Quy ước | Ví dụ đúng |
|---|---|---|
| PHP Namespace & Class | `Dravit\{Module}\{ClassName}` (`PascalCase`) | `Dravit\Catalog\CollectionSync` |
| PHP Method & Variable | `snake_case` | `sync_product_categories()`, `$price_m2` |
| Hằng số PHP | `DRV_*` (`UPPER_SNAKE`) | `DRV_VERSION`, `DRV_DIR`, `DRV_URI` |
| Hook / Filter custom | `drv/{module}/{event}` | `drv/catalog/synced`, `drv/units/models` |
| Template tag | `drv_*()` | `drv_price_html()`, `drv_qty_summary()` |
| Post Meta / Term Meta | `drv_*` (hoặc `_drv_*` cho order item snapshot) | `drv_price_m2`, `drv_h1`, `_drv_m2_qty` |
| Option | `drv_settings` (1 mảng duy nhất) | `get_option('drv_settings')` |
| Transient / Cache group | Prefix `drv_` / group `dravit` | `drv_facets_{hash}` |
| REST API Namespace | `dravit/v1` | `/wp-json/dravit/v1/catalog` |
| CSS Class | BEM prefix `drv-` | `.drv-card`, `.drv-card__price--sale` |
| Data Attribute (JS hook) | `data-drv-*` | `data-drv-filter`, `data-drv-calc` |

---

## 3. Quy chuẩn PHP (PHP 8.1+)

- Mọi file PHP (trừ template view thuần HTML) bắt đầu bằng:
  ```php
  <?php
  declare(strict_types=1);
  defined('ABSPATH') || exit;
  ```
- Khai báo đầy đủ kiểu dữ liệu cho tham số, giá trị trả về và thuộc tính của class (`typed properties`, `return types`).
- **Đơn ngôn ngữ Tiếng Việt:** Viết chuỗi Tiếng Việt trực tiếp, rõ ràng, kèm escape khi xuất ra HTML (`esc_html()`, `esc_attr()`). **Không** tạo file `.pot/.po/.mo`, không dùng Polylang hay bất kỳ logic đa ngôn ngữ nào.
- Đọc/ghi dữ liệu sản phẩm bắt buộc qua đối tượng `WC_Product` (`$product->get_meta()`, `$product->update_meta_data()`, `$product->save()`), không thao tác trực tiếp bảng `wp_postmeta` trừ các truy vấn tổng hợp chỉ đọc đã tối ưu.

---

## 4. Quy chuẩn Định dạng Số, Đơn vị & Văn bản Tiếng Việt

- **Tiền tệ:** Dấu chấm `.` phân cách hàng nghìn, không số thập phân, ký hiệu `đ` viết thường sát số: `385.000đ/m²`, `246.400đ/viên`.
  - PHP: dùng `number_format_i18n($amount, 0) . 'đ'` hoặc `wc_price()`.
  - JS: dùng `new Intl.NumberFormat('vi-VN').format(amount) + 'đ'`.
- **Số thập phân (diện tích, độ dày, độ hút nước):** Dùng dấu phẩy `,` theo chuẩn Việt Nam: `1,92 m²`, `0,5%`, `8,5 mm`.
- **Đơn vị diện tích:** Luôn dùng `m²` (số 2 siêu chỉ số), cách số 1 khoảng trắng (`30,72 m²`). **CẤM** viết `m2`.
- **Dấu gạch ngang trên UI:** Chỉ dùng gạch nối ngắn `-` hoặc dấu `:`, **CẤM** dùng em-dash `—` trên giao diện.

