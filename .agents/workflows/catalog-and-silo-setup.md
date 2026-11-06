---
description: Quy trình khởi tạo 28 Smart Collections, thuộc tính, đơn vị bán m2/viên và nạp dữ liệu sản phẩm
---

# Catalog, Smart Collections & Pricing Units Workflow

> **Docs bắt buộc đọc:** `docs/03_Catalog_Data_Model.md`, `docs/04_Pricing_Units_Commerce.md`, `docs/branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.md`
> **Rules:** `.agents/rules/dravit-catalog-commerce.md`, `.agents/rules/dravit-security-testing.md`
> **Skills:** `wordpress-theme-development`, `php-pro`, `clean-code`

---

## BƯỚC 1: KHAI BÁO CONFIG (`config/collections.php` & `config/units.php`)

1. **`config/collections.php`:** Khai báo đủ 28 Smart Collections (`DRV-C01` đến `DRV-Z05`) với đầy đủ `id`, `name`, `group` (`root|function|space|material|size`), `parent` (`gach`), `priority`, `phase`, `rule`, `h1`, `related`.
2. **`config/units.php`:** Khai báo 8 đơn vị (`m2`, `vien`, `thung`, `chiec`, `bo`, `tuyp`, `bao`, `kg`) và 4 pricing models (`area_pack`, `piece`, `set`, `pack`).

---

## BƯỚC 2: KHỞI TẠO TAXONOMY & THUỘC TÍNH CHUẨN

Chạy Seeder trong `Admin\ToolsResync` (idempotent — chạy nhiều lần không tạo trùng):
1. **Thuộc tính Silo (không bật public archive):** `pa_cong-nang`, `pa_khong-gian`, `pa_van-gach`, `pa_chat-lieu`, `pa_kich-thuoc`.
2. **Thuộc tính Facet (không bật public archive):** `pa_mau-sac`, `pa_be-mat`, `pa_phong-cach`, `pa_hinh-dang`, `pa_dcof`, `pa_do-hut-nuoc`, `pa_do-day`.
3. **Thuộc tính Public Archive (`FLT-05`):** `pa_bo-suu-tap` (`/bo-suu-tap/{slug}`), `pa_xuat-xu` (`/xuat-xu/{slug}`).
4. **Taxonomy nội bộ ẩn:** `drv_price_band` (`duoi-200k`, `200k-350k`, `350k-600k`, `tren-600k`), `drv_flag` (`showroom`).
5. **28 Danh mục `product_cat`:** Tạo `gach` (root) và 27 danh mục con; điền `drv_h1`, `drv_collection_id`, `drv_group`, Rank Math meta title/description (nếu đang trống). Đổi tên `uncategorized` thành `Chưa phân loại` (`noindex`).

---

## BƯỚC 3: TRIỂN KHAI TỰ ĐỘNG HÓA KHI LƯU SẢN PHẨM (`woocommerce_after_product_object_save`)

Khi một sản phẩm được lưu (từ Admin, Quick Edit, REST hoặc CSV Import), thực thi theo đúng thứ tự:

1. **Tính giá & quy cách (`Units\PricingModel\AreaPack`):**
   - Tính diện tích 1 viên: `area_piece = (w_mm * h_mm) / 1_000_000`.
   - Tính `drv_m2_per_box = round(area_piece * pieces_per_box, 4)`.
   - Tính giá/viên lưu vào WooCommerce: `_regular_price = round(drv_price_m2 * area_piece)`, `_sale_price = round(drv_sale_price_m2 * area_piece)`.
2. **Sinh thuộc tính dẫn xuất (`Catalog\CollectionSync::derive_attributes`):**
   - `pa_kich-thuoc` từ `w_mm × h_mm` (đổi sang cm, cạnh ngắn đứng trước, dấu `.` đổi thành `-`, VD: `7-5x30`).
   - `pa_do-day` từ `drv_thickness_mm` (`mong <= 6`, `tieu-chuan 6.1..11`, `day >= 12`).
   - `pa_do-hut-nuoc` từ `drv_water_absorption` (`duoi-0-5`, `0-5-3`, `3-6`, `6-10`, `tren-10`).
   - `drv_price_band` từ giá/m² hiện hành.
3. **Khớp Rule & Gán `product_cat` (`Catalog\RuleMatcher`):**
   - Đánh giá toàn bộ 28 rule trong `config/collections.php` (bao gồm cờ `on_sale` và `large_format` khi cạnh dài `> 1200mm`).
   - Giữ nguyên các danh mục ngoài hệ thống 28 collection mà admin gán tay.
   - Ghi bằng `wp_set_object_terms($product_id, $final_cat_ids, 'product_cat')` (có cờ khóa chống đệ quy).
4. **Đặt Primary Category (`rank_math_primary_product_cat`):**
   - Nếu sản phẩm chưa có Primary Category hoặc Primary Category không còn nằm trong `$final_cat_ids`, tự chọn danh mục có `priority` cao nhất trong các collection khớp (bỏ qua root `gach`).
5. **Xóa Cache:** Xóa cache đếm facet (`drv_facets_*`) và gọi `do_action('litespeed_purge', 'drv_catalog')`.

---

## BƯỚC 4: KIỂM THỬ NGHIỆM THU VỚI 8 SKU MẪU

Nạp 8 SKU mẫu trong `docs/branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.md` (Phần 4) và chạy script kiểm tra:
- [ ] `DRV-8801M` → khớp đúng 7 URL, Primary = `/gach-lat-nen`.
- [ ] `DRV-3605S` → khớp đúng 8 URL, Primary = `/gach-op-tuong`.
- [ ] `DRV-61209` → khớp đúng 9 URL, Primary = `/gach-60x120`.
- [ ] `DRV-SLAB01` → khớp đúng 9 URL (có `/da-nung-ket`), Primary = `/da-nung-ket`.
- [ ] `DRV-W1590` → khớp đúng 9 URL (có `/gach-15x80-20x100`), Primary = `/gach-gia-go`.
- [ ] `DRV-TRZ60` → khớp đúng 11 URL (gồm cả `/gach-porcelain`), Primary = `/gach-terrazzo`.
- [ ] `DRV-SV40` → khớp đúng 6 URL tĩnh + lọc được tại `/gach-san-vuon?kich-thuoc=40x40`, Primary = `/gach-san-vuon`.
- [ ] `DRV-TH7530` → khớp đúng 6 URL tĩnh + lọc được tại `/gach-the?mau-sac=xanh-la`, Primary = `/gach-the`.

