# 03. Mô hình dữ liệu Catalog

> Nguồn chuẩn URL/H1/breadcrumb/phase: [branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.md](branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.md) và [branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.xlsx](branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.xlsx). Tài liệu này chuyển đặc tả đó sang cấu trúc WooCommerce (D-02).
> Skill: `wp-plugin-development` (hook/data), `programmatic-seo`, `wp-rest-api`.

## 1. Tổng quan ánh xạ

| Khái niệm trong tài liệu Silo | Triển khai WooCommerce |
|---|---|
| Bảng `products` (1 SKU = 1 URL) | Simple product, URL `/san-pham/{slug}` (`product_base = /san-pham`) |
| `primary_collection_id` | Post meta Rank Math `rank_math_primary_product_cat` |
| `attributes JSONB` (tag 4 chiều) | Global attributes `pa_*` gán vào sản phẩm |
| Bảng `smart_collections` + `query_rule` | Term `product_cat` + `config/collections.php` (rule) + term meta |
| Tự động gom sản phẩm theo rule | `Catalog\CollectionSync`: khi lưu sản phẩm, tính rule → gán `product_cat` |
| URL phẳng `/gach-lat-nen` | Rank Math: `wc_remove_category_base` + `wc_remove_category_parent_slugs` |

## 2. Thuộc tính (Global Attributes)

Tạo trong **Sản phẩm → Thuộc tính**. Slug giá trị **bắt buộc ASCII, không dấu, dấu chấm đổi thành gạch** (`7.5x30` → `7-5x30`, `0.5` → `0-5`).

### 2.1 Nhóm Silo (dùng cho rule tự gán danh mục, không public archive)

| Attribute | Param URL | Giá trị (slug: nhãn) |
|---|---|---|
| `pa_cong-nang` | `cong-nang` | `lat-nen`: Lát nền · `op-tuong`: Ốp tường · `trang-tri`: Trang trí · `gach-bong`: Gạch bông · `bong-gio`: Bông gió · `gach-the`: Gạch thẻ |
| `pa_khong-gian` | `khong-gian` | `phong-khach` · `nha-tam` · `phong-bep` · `san-vuon` · `ngoai-troi` · `mat-tien` · `phong-ngu` · `ho-boi` |
| `pa_van-gach` | `van-gach` | `gia-go`: Giả gỗ · `van-da-marble`: Vân đá Marble · `van-da-tu-nhien`: Vân đá tự nhiên · `terrazzo` · `van-xi-mang`: Vân xi măng · `mosaic` |
| `pa_chat-lieu` | `chat-lieu` | `porcelain` · `granite` · `ceramic` · `da-nung-ket`: Đá nung kết · `xi-mang`: Xi măng |
| `pa_kich-thuoc` | `kich-thuoc` | **Tự sinh** từ kích thước viên (§5.2): `60x60`, `80x80`, `30x60`, `60x120`, `15x80`, `15x90`, `20x100`, `20x120`, `120x240`, `20x20`, `30x30`, `40x40`, `7-5x30`... |

### 2.2 Nhóm Facet (chỉ dùng để lọc)

| Attribute | Param | Giá trị | Nguồn |
|---|---|---|---|
| `pa_mau-sac` | `mau-sac` | `trang` · `xam` · `kem` · `vang` · `nau` · `den` · `xanh-duong` · `xanh-la` · `hong` · `da-sac` | Nhập tay (được chọn nhiều) |
| `pa_be-mat` | `be-mat` | `bong-kieng`: Bóng kiếng (Polished) · `mo`: Mờ (Matt) · `ban-bong`: Bán bóng (Lappato) · `nham`: Nhám sần (Structured) · `sugar`: Hiệu ứng Sugar · `carving`: Carving | Nhập tay |
| `pa_phong-cach` | `phong-cach` | `hien-dai` · `toi-gian` · `wabi-sabi` · `japandi` · `indochine` · `tan-co-dien` | Nhập tay |
| `pa_hinh-dang` | `hinh-dang` | `vuong` · `chu-nhat` · `luc-giac` · `vay-ca` · `long-vu` · `den-long` · `ziczac` | Nhập tay |
| `pa_dcof` | `dcof` | `r9` · `r10` · `r11` · `r12` · `r13` | Nhập tay |
| `pa_do-hut-nuoc` | `do-hut-nuoc` | `duoi-0-5`: ≤ 0,5% · `0-5-3`: 0,5–3% · `3-6`: 3–6% · `6-10`: 6–10% · `tren-10`: > 10% | **Tự sinh** từ `drv_water_absorption` |
| `pa_do-day` | `do-day` | `mong`: ≤ 6mm · `tieu-chuan`: 6,1–11mm · `day`: ≥ 12mm (chịu lực, gara) | **Tự sinh** từ `drv_thickness_mm` |

### 2.3 Nhóm public archive (FLT-05)

| Attribute | URL archive | Giá trị ví dụ |
|---|---|---|
| `pa_bo-suu-tap` (Enable archives ✓) | `/bo-suu-tap/{slug}` | `dravit-luxury`, `dravit-signature`, `dravit-eco` |
| `pa_xuat-xu` (Enable archives ✓) | `/xuat-xu/{slug}` | `tay-ban-nha`, `an-do`, `y`, `viet-nam` |

`attribute_base` (Cài đặt → Đường dẫn tĩnh) để trống → rewrite = `{attribute_name}/{term}`.

### 2.4 Taxonomy ẩn (không public, `show_ui` chỉ cho admin)

| Taxonomy | Mục đích | Giá trị |
|---|---|---|
| `drv_price_band` | Lọc khoảng giá/m² (FLT-13) bằng tax_query nhanh | `duoi-200k`, `200k-350k`, `350k-600k`, `tren-600k`, `gia-du-an`. **Tự sinh** từ `drv_price_m2` (ngưỡng trong `config/facets.php`) |
| `drv_flag` | Cờ lọc tiện ích (FLT-14) | `showroom` (có mẫu tại showroom) |

### 2.5 Chuẩn hóa so với ví dụ trong tài liệu Silo

| Ví dụ trong tài liệu Silo | Slug chuẩn |
|---|---|
| `be-mat=bong` | `bong-kieng` |
| `be-mat=mo-matt` | `mo` |
| `dcof=r10-r11`, `dcof=r11-r13` | Chọn nhiều: `dcof=r10,r11` |
| `do-hut-nuoc=duoi-0.5` | `duoi-0-5` |
| `mau-sac=xanh`, `xanh-ngoc`, `xam-trang`, `vang-kem`, `nau-go` | Gán nhiều màu cơ bản: `xanh-la`, `xam,trang`, `vang,kem`, `nau` |
| `chiu-luc=cao` | `do-day=day` |
| `van-gach=van-da` | `van-gach=van-da-marble,van-da-tu-nhien` |
| `kich-thuoc=7.5x30` | `7-5x30` |
| Khoảng `6–10%` độ hút nước (tài liệu Silo bỏ trống) | Bổ sung `6-10` |
| Seed DRV-TRZ60 có tag `porcelain` nhưng danh sách URL thiếu `/gach-porcelain` | Theo rule: SP xuất hiện ở `/gach-porcelain` (11 URL) |

## 3. 28 Collections (`product_cat`)

### 3.1 Cây danh mục

```text
gach (DRV-C01, root, /gach)
├── 7 công năng   (C02–C08)
├── 8 không gian  (S01–S08)
├── 7 vân/chất liệu (M01–M07)
└── 5 kích thước  (Z01–Z05)
```

Tất cả con trực tiếp của `gach`, URL phẳng nhờ Rank Math bỏ parent slug. Root mở rộng sau: `keo-cha-ron`, `vat-tu-hoan-thien`...
Danh mục mặc định WC "Uncategorized" đổi tên **"Chưa phân loại"** (`chua-phan-loai`), `noindex`, loại khỏi sitemap, tự gỡ khi sản phẩm có danh mục khác.

### 3.2 Bảng rule

Ký hiệu: `A ∋ x` = attribute A chứa giá trị x. Nhiều điều kiện trong một ô = **OR**. `priority` dùng khi tự chọn primary category (số lớn ưu tiên).

| ID | Slug | Nhóm | Rule | Priority | Phase |
|---|---|---|---|---|---|
| C01 | `gach` | root | `line = gach` (mọi sản phẩm gạch) | 0 | 1 |
| C02 | `gach-lat-nen` | function | `cong-nang ∋ lat-nen` | 40 | 1 |
| C03 | `gach-op-tuong` | function | `cong-nang ∋ op-tuong` | 40 | 1 |
| C04 | `gach-trang-tri` | function | `cong-nang ∋ trang-tri` | 30 | 1 |
| C05 | `gach-bong` | function | `cong-nang ∋ gach-bong` | 70 | 2 |
| C06 | `gach-bong-gio` | function | `cong-nang ∋ bong-gio` | 70 | 2 |
| C07 | `gach-the` | function | `cong-nang ∋ gach-the` | 70 | 2 |
| C08 | `gach-uu-dai` | function | `flag: on_sale` | 5 | 2 |
| S01 | `gach-phong-khach` | space | `khong-gian ∋ phong-khach` | 20 | 1 |
| S02 | `gach-nha-tam` | space | `khong-gian ∋ nha-tam` | 20 | 1 |
| S03 | `gach-phong-bep` | space | `khong-gian ∋ phong-bep` | 20 | 1 |
| S04 | `gach-san-vuon` | space | `khong-gian ∋ san-vuon` | 25 | 1 |
| S05 | `gach-ngoai-troi` | space | `khong-gian ∋ ngoai-troi` | 15 | 2 |
| S06 | `gach-mat-tien` | space | `khong-gian ∋ mat-tien` | 15 | 2 |
| S07 | `gach-phong-ngu` | space | `khong-gian ∋ phong-ngu` | 15 | 2 |
| S08 | `gach-ho-boi` | space | `khong-gian ∋ ho-boi` | 25 | 2 |
| M01 | `gach-gia-go` | material | `van-gach ∋ gia-go` | 60 | 1 |
| M02 | `gach-van-da` | material | `van-gach ∋ van-da-marble` · `van-gach ∋ van-da-tu-nhien` | 50 | 1 |
| M03 | `gach-terrazzo` | material | `van-gach ∋ terrazzo` | 60 | 1 |
| M04 | `gach-van-xi-mang` | material | `van-gach ∋ van-xi-mang` | 60 | 1 |
| M05 | `gach-mosaic` | material | `van-gach ∋ mosaic` | 60 | 2 |
| M06 | `da-nung-ket` | material | `chat-lieu ∋ da-nung-ket` · `flag: large_format` | 80 | 1 |
| M07 | `gach-porcelain` | material | `chat-lieu ∋ porcelain` · `chat-lieu ∋ granite` | 10 | 2 |
| Z01 | `gach-60x60` | size | `kich-thuoc ∋ 60x60` | 35 | 1 |
| Z02 | `gach-80x80` | size | `kich-thuoc ∋ 80x80` | 35 | 1 |
| Z03 | `gach-30x60` | size | `kich-thuoc ∋ 30x60` | 35 | 1 |
| Z04 | `gach-60x120` | size | `kich-thuoc ∋ 60x120` | 45 | 1 |
| Z05 | `gach-15x80-20x100` | size | `kich-thuoc ∋ 15x80, 15x90, 20x100, 20x120` | 35 | 2 |

- `flag: large_format` = cạnh dài viên **> 1200mm** (vd 120x240, 160x320). 60x120 không tính.
- `flag: on_sale` = `$product->is_on_sale('edit')`.
- H1, meta title, meta description, breadcrumb: lấy từ tài liệu Silo, nạp vào term meta + Rank Math term meta bằng seeder (§3.4).

### 3.3 Định dạng `config/collections.php`

```php
return [
    'gach-van-da' => [
        'id'       => 'DRV-M02',
        'name'     => 'Gạch Vân Đá Marble',
        'group'    => 'material',          // root|function|space|material|size
        'parent'   => 'gach',
        'phase'    => 1,
        'priority' => 50,
        'rule'     => ['any' => [
            ['attr' => 'pa_van-gach', 'in' => ['van-da-marble', 'van-da-tu-nhien']],
        ]],
        'h1'       => 'Gạch Vân Đá Tự Nhiên & Vân Đá Marble Cao Cấp',
        'related'  => ['gach-80x80', 'gach-phong-khach', 'da-nung-ket'],   // pills "Gợi ý theo nhu cầu" (IL-02)
    ],
    // ...
];
// Điều kiện hỗ trợ: ['attr'=>..., 'in'=>[...]] | ['flag'=>'on_sale'|'large_format'] | ['line'=>'gach']
// 'any' = OR, 'all' = AND (có thể lồng).
// Mở rộng qua filter: apply_filters('drv/catalog/collections', $config)
```

### 3.4 Seeder & đồng bộ cấu hình

**Công cụ → Dravit Catalog** (`Admin\ToolsResync`, quyền `manage_woocommerce`):
1. **Tạo/cập nhật danh mục từ config**: idempotent; tạo term thiếu, đặt parent, ghi `drv_collection_id`, `drv_group`, `drv_h1`, Rank Math `rank_math_title`/`rank_math_description` **chỉ khi đang trống** (không ghi đè nội dung đã sửa).
2. **Tạo attribute + term chuẩn** (§2).
3. **Đồng bộ lại toàn bộ sản phẩm**: chạy nền qua Action Scheduler, batch 50 sản phẩm, có tiến độ.
4. **Báo cáo kiểm tra**: sản phẩm thiếu primary category, thiếu kích thước, thiếu giá/m², danh mục rỗng.

## 4. Cơ chế tự gán danh mục (`Catalog\CollectionSync`)

### 4.1 Trigger

- `woocommerce_after_product_object_save` (bắt cả admin, quick/bulk edit, CSV import, REST).
- `wc_after_products_starting_sales` / `wc_after_products_ending_sales` (KM theo lịch) → đồng bộ lại `gach-uu-dai`.
- Đổi `config/collections.php` → chạy "Đồng bộ lại toàn bộ".

### 4.2 Thuật toán

```text
sync(product):
  if guard.running(product.id): return            # chống vòng lặp save
  derive_attributes(product)                       # §5.2: kich-thuoc, do-day, do-hut-nuoc, price_band, flags
  ctx   = { attrs: taxonomy => [slugs], flags: {on_sale, large_format}, line: product.drv_line }
  rules = collections config
  matched  = [term_id(c) for c in rules if RuleMatcher.match(c.rule, ctx)]
  managed  = [term_id(c) for c in rules]                       # 28 term do hệ thống quản lý
  manual   = product.category_ids - managed - [uncategorized]  # danh mục gán tay ngoài hệ thống: giữ nguyên
  final    = unique(manual + matched)
  if final == []: final = [uncategorized]
  if final != product.category_ids:
      wp_set_object_terms(product.id, final, 'product_cat')    # không gọi $product->save() lần nữa
  primary = meta rank_math_primary_product_cat
  if primary empty OR primary not in final:
      primary = argmax(priority) trong matched, bỏ qua root 'gach'
      update meta rank_math_primary_product_cat = primary ; đánh dấu drv_primary_auto = 1
  clean_term_cache, xóa cache facet (tag drv_catalog)
  do_action('drv/catalog/synced', product.id, matched)
```

### 4.3 Primary category

- Admin **nên chọn tay** (hộp "Danh mục chính" của Rank Math trong màn hình sản phẩm). Seed 8 SKU dùng đúng primary trong tài liệu Silo.
- Chỉ tự chọn theo `priority` khi trống hoặc primary không còn thuộc danh mục của sản phẩm; admin thấy cảnh báo "Danh mục chính được chọn tự động".
- Breadcrumb sản phẩm: `Trang chủ > Gạch > {Primary} > {Tên SP}` (IL-04, `06` §5).

### 4.4 SlugGuard

- Danh sách slug dành riêng: 28 slug collection + `gach`, `san-pham`, `cam-nang`, `chu-de`, `bo-suu-tap`, `xuat-xu`, `gio-hang`, `thanh-toan`, `tai-khoan`, `showroom`, `lien-he`, `gioi-thieu`, `tim-kiem`.
- Page/Post có slug trùng → tự thêm hậu tố `-trang` + admin notice (filter `wp_unique_post_slug`).
- Tạo `product_cat` trùng slug Page đã có → chặn, báo lỗi (`pre_insert_term`).

## 5. Product meta

Nhập trong tab **"Đơn vị & Thông số"** của Product Data (`Admin\ProductDataTab`), lưu bằng WC CRUD.

### 5.1 Danh sách

| Meta key | Kiểu | Nhãn admin | Bắt buộc (gạch) |
|---|---|---|---|
| `drv_line` | select | Ngành hàng (`gach`, sau này `keo-cha-ron`...) | ✓ (mặc định `gach`) |
| `drv_pricing_model` | select | Mô hình giá (`area_pack` / `piece` / `set` / `pack`), xem `04` | ✓ |
| `drv_price_m2` | int (VND) | Giá niêm yết / m² | ✓ |
| `drv_sale_price_m2` | int | Giá KM / m² (lịch KM dùng trường WC) | |
| `drv_piece_w_mm`, `drv_piece_h_mm` | int | Kích thước viên (mm) | ✓ |
| `drv_thickness_mm` | float | Độ dày (mm) | ✓ |
| `drv_pieces_per_box` | int | Số viên/thùng | ✓ |
| `drv_m2_per_box` | float | m²/thùng (tự tính, chỉ đọc) | auto |
| `drv_kg_per_box` | float | Khối lượng/thùng (kg) | |
| `drv_qty_min`, `drv_qty_step` | int | Mua tối thiểu / bước (viên). Mặc định 1/1; đặt `step = pieces_per_box` để bắt mua chẵn thùng | |
| `drv_water_absorption` | float (%) | Độ hút nước | ✓ |
| `drv_pei` | select I–V | Độ chịu mài mòn PEI | |
| `drv_mohs` | int | Độ cứng Mohs | |
| `drv_breaking_strength` | int (N) | Lực phá vỡ | |
| `drv_rectified` | bool | Mài cạnh (rectified) | |
| `drv_shade` | select V1–V4 | Mức biến thiên màu | |
| `drv_faces` | int | Số mặt vân khác nhau | |
| `drv_series` | string | Mã dòng (nhóm các SKU cùng mẫu khác kích thước/bề mặt → swatch) | |
| `drv_warranty` | string | Bảo hành | |
| `drv_video_url` | url | Video sản phẩm | |
| GTIN | native WC `global_unique_id` | Mã vạch | |

Trường WC native vẫn dùng: SKU, tồn kho (đơn vị **viên**), lịch KM, ảnh/gallery, mô tả ngắn, mô tả, khối lượng (kg/viên, tự tính = kg/thùng ÷ viên/thùng nếu có).

### 5.2 Thuộc tính & dữ liệu tự sinh khi lưu

| Đầu vào | Sinh ra |
|---|---|
| `drv_piece_w_mm`, `drv_piece_h_mm` | `pa_kich-thuoc` = `{w/10}x{h/10}` (cm, cạnh ngắn trước, `.` → `-`); `flag large_format` nếu cạnh dài > 1200 |
| `drv_pieces_per_box` + kích thước | `drv_m2_per_box` = w × h × viên / 1.000.000 (làm tròn 4 chữ số) |
| `drv_thickness_mm` | `pa_do-day` |
| `drv_water_absorption` | `pa_do-hut-nuoc` |
| `drv_price_m2` / `drv_sale_price_m2` | `_regular_price` / `_sale_price` (giá/viên, `04` §2) + `drv_price_band` |

Attribute tự sinh hiển thị "chỉ đọc" trong tab Thuộc tính để tránh nhập trùng.

## 6. Term meta (`product_cat`)

| Meta key | Kiểu | Dùng ở |
|---|---|---|
| `drv_collection_id` | string | Mã DRV-xxx |
| `drv_group` | string | Nhóm (lọc pills, mega menu) |
| `drv_h1` | string | H1 trang danh mục (tách khỏi tên term) |
| `drv_intro` | text ≤ 60 chữ | Đoạn tóm tắt "quick facts" dưới H1 |
| `drv_seo_footer` | HTML (wp_editor) | Khối nội dung SEO cuối trang (IL-01) |
| `drv_faq` | mảng `{q, a}` | Khối FAQ + `FAQPage` schema |
| `drv_hero_image` | attachment ID | Ảnh đầu trang / mega menu / thẻ collection ở home |
| `drv_related` | mảng term ID | Pills "Gợi ý theo nhu cầu" (mặc định lấy `related` trong config) |

Mô tả term (`description`) chuẩn của WP không dùng, tránh trùng nội dung với `drv_intro`.

## 7. Import CSV

- Dùng **WooCommerce → Sản phẩm → Nhập** (importer native).
- Thêm cột custom qua `woocommerce_csv_product_import_mapping_options` + `woocommerce_csv_product_import_mapping_default_columns` + `woocommerce_product_import_pre_insert_product_object`.
- Cột: `SKU, Tên, Mô tả ngắn, Mô tả, Ảnh, Tồn kho (viên), Giá/m², Giá KM/m², Rộng mm, Dài mm, Dày mm, Viên/thùng, Kg/thùng, Độ hút nước %, PEI, Mohs, Lực phá vỡ, Mài cạnh, Shade, Số mặt, Mã dòng, Công năng, Không gian, Vân gạch, Chất liệu, Màu sắc, Bề mặt, Phong cách, Hình dạng, DCOF, Bộ sưu tập, Xuất xứ, Danh mục chính, Có mẫu showroom`.
- Giá trị nhiều lựa chọn phân cách `|` (vd `phong-khach|nha-tam`). Sau import, sync chạy tự động cho từng sản phẩm.
- File mẫu: `docs/templates/import-gach-mau.csv` (tạo ở Phase 2 từ 8 SKU seed).

## 8. Seed data (nghiệm thu)

8 SKU trong tài liệu Silo, Phần 4. Sau khi seed, mỗi SKU phải xuất hiện **đúng** tập URL ghi trong cột "Các URL Danh mục Tĩnh tự động hiển thị SP này" và có breadcrumb theo cột "Primary Category". Test case chi tiết: `09` §3.1.
