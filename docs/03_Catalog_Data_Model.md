# 03. Mô hình dữ liệu Catalog

> Nguồn chuẩn URL/H1/breadcrumb/phase: [branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.md](branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.md) và [branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.xlsx](branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.xlsx).
> Quyết định: D-02, D-14, D-20, D-24, D-26, D-27, D-29, D-30. Từ điển kiểu dữ liệu & sanitize chi tiết: [14_Data_Dictionary_And_Settings.md](14_Data_Dictionary_And_Settings.md).
> Skill: `wp-plugin-development` (hook/data), `programmatic-seo`, `wp-rest-api`.

## 1. Tổng quan ánh xạ

| Khái niệm trong tài liệu Silo | Triển khai WooCommerce |
|---|---|
| Bảng `products` (1 SKU = 1 URL) | Simple product, URL `/san-pham/{slug}` (`product_base = /san-pham`, không dấu `/` cuối) |
| `primary_collection_id` | Post meta Rank Math `rank_math_primary_product_cat` |
| `attributes JSONB` (tag 4 chiều) | Global attributes `pa_*` gán vào sản phẩm |
| Bảng `smart_collections` + `query_rule` | Term `product_cat` + `config/collections.php` (rule) + term meta |
| Tự động gom sản phẩm theo rule | `Catalog\CollectionSync`: khi lưu sản phẩm, tính rule -> gán `product_cat` |
| URL phẳng `/gach-lat-nen` | Rank Math: `wc_remove_category_base` + `wc_remove_category_parent_slugs` |

## 2. Thuộc tính (Global Attributes)

Tạo trong **Sản phẩm -> Thuộc tính**. Slug giá trị **bắt buộc ASCII, không dấu, dấu chấm đổi thành gạch** (`7.5x30` -> `7-5x30`, `0.5` -> `0-5`). Nhãn lọc mở rộng, nhãn rút gọn, mã màu swatch và icon của từng term khai báo qua term meta (§6.1).

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
| `pa_dcof` | `dcof` | `r9` · `r10` · `r11` · `r12` · `r13` | Nhập tay (tách riêng từng mức `r9`..`r13`) |
| `pa_do-hut-nuoc` | `do-hut-nuoc` | `duoi-0-5`: ≤ 0,5% · `0-5-3`: 0,5–3% · `3-6`: 3–6% · `6-10`: 6–10% · `tren-10`: > 10% | **Tự sinh** từ `drv_water_absorption` |
| `pa_do-day` | `do-day` | `mong`: ≤ 6mm · `tieu-chuan`: 6,1–11mm · `day`: ≥ 12mm (chịu lực, gara) | **Tự sinh** từ `drv_thickness_mm` |

### 2.3 Nhóm public archive (FLT-05)

| Attribute | URL archive | Giá trị ví dụ |
|---|---|---|
| `pa_bo-suu-tap` (Enable archives ✓) | `/bo-suu-tap/{slug}` | `dravit-luxury`, `dravit-signature`, `dravit-eco` |
| `pa_xuat-xu` (Enable archives ✓) | `/xuat-xu/{slug}` | `tay-ban-nha`, `an-do`, `y`, `viet-nam` |

`attribute_base` (Cài đặt -> Đường dẫn tĩnh) để trống -> rewrite = `{attribute_name}/{term}`.

### 2.4 Taxonomy ẩn (không public, `show_ui` chỉ cho admin)

| Taxonomy | Mục đích | Giá trị |
|---|---|---|
| `drv_price_band` | Lọc khoảng giá/m² (FLT-13) bằng `tax_query` nhanh | `duoi-200k`, `200k-350k`, `350k-600k`, `tren-600k`, `gia-du-an` (Liên hệ báo giá). **Tự sinh** từ `drv_price_m2`: nếu `drv_price_m2 > 0` gán theo ngưỡng trong `config/facets.php`; nếu để trống hoặc `≤ 0` tự gán `gia-du-an` |
| `drv_flag` | Cờ lọc tiện ích (FLT-14) | `showroom` (có mẫu thực tế tại showroom; **tự gán** khi `drv_showroom_shelf` khác rỗng hoặc admin tích chọn tay) |

### 2.5 Chuẩn hóa so với ví dụ trong tài liệu Silo

| Ví dụ trong tài liệu Silo / Prototype | Slug chuẩn |
|---|---|
| `be-mat=bong` | `bong-kieng` |
| `be-mat=mo-matt` | `mo` |
| `be-mat=nham-san` | `nham` |
| `dcof=r10-r11`, `dcof=r11-r13`, `dcof=r12-r13` | Chọn nhiều giá trị đơn: `dcof=r10,r11` hoặc `dcof=r12,r13` |
| `do-hut-nuoc=duoi-0.5` | `duoi-0-5` |
| `mau-sac=xanh`, `xanh-ngoc`, `xam-trang`, `vang-kem`, `nau-go` | Gán nhiều màu cơ bản: `xanh-la`, `xam,trang`, `vang,kem`, `nau` |
| `chiu-luc=cao` | `do-day=day` |
| `van-gach=van-da` | `van-gach=van-da-marble,van-da-tu-nhien` |
| `van-gach=da-nung-ket` | `chat-lieu=da-nung-ket` (tách đúng param `chat-lieu`) |
| `kich-thuoc=7.5x30` | `7-5x30` |
| Khoảng `6–10%` độ hút nước (tài liệu Silo bỏ trống) | Bổ sung `6-10` |
| Seed DRV-TRZ60 có tag `porcelain` nhưng danh sách URL thiếu `/gach-porcelain` | Theo rule: SP xuất hiện ở `/gach-porcelain` (11 URL) |

## 3. 28 Collections (`product_cat`)

### 3.1 Cây danh mục

```text
gach (DRV-C01, root, /gach)
├── 7 công năng     (C02–C08)
├── 8 không gian    (S01–S08)
├── 7 vân/chất liệu (M01–M07)
└── 5 kích thước    (Z01–Z05)
```

Tất cả con trực tiếp của `gach`, URL phẳng nhờ Rank Math bỏ parent slug. Root mở rộng sau: `keo-cha-ron`, `vat-tu-hoan-thien`...
Danh mục mặc định WC "Uncategorized" đổi tên **"Chưa phân loại"** (`chua-phan-loai`), `noindex`, loại khỏi sitemap, tự gỡ khi sản phẩm có danh mục khác.

### 3.2 Bảng rule & Trọng số Primary Category (`D-27`)

Ký hiệu: `A ∋ x` = attribute A chứa giá trị x. Nhiều điều kiện trong một ô = **OR**. `priority` dùng khi tự chọn primary category (số lớn ưu tiên hơn; đã hiệu chỉnh theo `D-27` để khớp chính xác 8/8 SKU chuẩn nghiệm thu tại `09 §3.1`).

| ID | Slug | Nhóm | Rule | Priority | Phase |
|---|---|---|---|---|---|
| C01 | `gach` | root | `line = gach` (mọi sản phẩm gạch) | 0 | 1 |
| C02 | `gach-lat-nen` | function | `cong-nang ∋ lat-nen` | 40 | 1 |
| C03 | `gach-op-tuong` | function | `cong-nang ∋ op-tuong` | 42 | 1 |
| C04 | `gach-trang-tri` | function | `cong-nang ∋ trang-tri` | 30 | 1 |
| C05 | `gach-bong` | function | `cong-nang ∋ gach-bong` | 70 | 2 |
| C06 | `gach-bong-gio` | function | `cong-nang ∋ bong-gio` | 70 | 2 |
| C07 | `gach-the` | function | `cong-nang ∋ gach-the` | 70 | 2 |
| C08 | `gach-uu-dai` | function | `flag: on_sale` | 5 | 2 |
| S01 | `gach-phong-khach` | space | `khong-gian ∋ phong-khach` | 20 | 1 |
| S02 | `gach-nha-tam` | space | `khong-gian ∋ nha-tam` | 20 | 1 |
| S03 | `gach-phong-bep` | space | `khong-gian ∋ phong-bep` | 20 | 1 |
| S04 | `gach-san-vuon` | space | `khong-gian ∋ san-vuon` | 55 | 1 |
| S05 | `gach-ngoai-troi` | space | `khong-gian ∋ ngoai-troi` | 15 | 2 |
| S06 | `gach-mat-tien` | space | `khong-gian ∋ mat-tien` | 15 | 2 |
| S07 | `gach-phong-ngu` | space | `khong-gian ∋ phong-ngu` | 15 | 2 |
| S08 | `gach-ho-boi` | space | `khong-gian ∋ ho-boi` | 25 | 2 |
| M01 | `gach-gia-go` | material | `van-gach ∋ gia-go` | 60 | 1 |
| M02 | `gach-van-da` | material | `van-gach ∋ van-da-marble` · `van-gach ∋ van-da-tu-nhien` | 30 | 1 |
| M03 | `gach-terrazzo` | material | `van-gach ∋ terrazzo` | 60 | 1 |
| M04 | `gach-van-xi-mang` | material | `van-gach ∋ van-xi-mang` | 42 | 1 |
| M05 | `gach-mosaic` | material | `van-gach ∋ mosaic` | 60 | 2 |
| M06 | `da-nung-ket` | material | `chat-lieu ∋ da-nung-ket` · `flag: large_format` | 80 | 1 |
| M07 | `gach-porcelain` | material | `chat-lieu ∋ porcelain` · `chat-lieu ∋ granite` | 10 | 2 |
| Z01 | `gach-60x60` | size | `kich-thuoc ∋ 60x60` | 35 | 1 |
| Z02 | `gach-80x80` | size | `kich-thuoc ∋ 80x80` | 35 | 1 |
| Z03 | `gach-30x60` | size | `kich-thuoc ∋ 30x60` | 35 | 1 |
| Z04 | `gach-60x120` | size | `kich-thuoc ∋ 60x120` | 45 | 1 |
| Z05 | `gach-15x80-20x100` | size | `kich-thuoc ∋ 15x80, 15x90, 20x100, 20x120` | 35 | 2 |

- `flag: large_format` = cạnh dài viên **> 1200mm** (vd 120x240, 160x320). 60x120 không tính.
- `flag: on_sale` = `$product->is_on_sale('edit')` và sản phẩm có giá niêm yết (`drv_price_m2 > 0`).
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
        'priority' => 30,
        'rule'     => ['any' => [
            ['attr' => 'pa_van-gach', 'in' => ['van-da-marble', 'van-da-tu-nhien']],
        ]],
        'h1'       => 'Gạch Vân Đá Tự Nhiên & Vân Đá Marble Cao Cấp',
        'related'  => ['gach-80x80', 'gach-phong-khach', 'da-nung-ket'],   // Sub-Silo Tiles mặc định (IL-02)
    ],
    // ...
];
// Điều kiện hỗ trợ: ['attr'=>..., 'in'=>[...]] | ['flag'=>'on_sale'|'large_format'] | ['line'=>'gach']
// 'any' = OR, 'all' = AND (có thể lồng).
// Mở rộng qua filter: apply_filters('drv/catalog/collections', $config)
```

### 3.4 Seeder & đồng bộ cấu hình

**Công cụ -> Dravit Catalog** (`Admin\ToolsResync`, quyền `manage_woocommerce`):
1. **Tạo/cập nhật danh mục từ config**: idempotent; tạo term thiếu, đặt parent, ghi `drv_collection_id`, `drv_group`, `drv_h1`, Rank Math `rank_math_title`/`rank_math_description` **chỉ khi đang trống** (không ghi đè nội dung đã sửa).
2. **Tạo attribute + term chuẩn** (§2) kèm term meta mặc định (§6.1).
3. **Đồng bộ lại toàn bộ sản phẩm**: chạy nền qua Action Scheduler, batch 50 sản phẩm, có tiến độ.
4. **Báo cáo kiểm tra**: sản phẩm thiếu primary category, thiếu kích thước, thiếu quy cách thùng, danh mục rỗng.

## 4. Cơ chế tự gán danh mục (`Catalog\CollectionSync`)

### 4.1 Trigger

- `woocommerce_after_product_object_save` (bắt cả admin, quick/bulk edit, CSV import, REST).
- `wc_after_products_starting_sales` / `wc_after_products_ending_sales` (KM theo lịch) -> đồng bộ lại `gach-uu-dai` và xóa cache trang liên quan.
- Đổi `config/collections.php` -> chạy "Đồng bộ lại toàn bộ".

### 4.2 Thuật toán

```text
sync(product):
  if guard.running(product.id): return            # chống vòng lặp save
  derive_attributes(product)                       # §5.2: kich-thuoc, do-day, do-hut-nuoc, price_band, flags, qty_step
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
  clean_term_cache, xóa transient thống kê danh mục (§6.2) & cache facet (tag drv_catalog)
  do_action('drv/catalog/synced', product.id, matched)
```

### 4.3 Primary category

- Admin **ưu tiên chọn tay** (hộp "Danh mục chính" của Rank Math trong màn hình sản phẩm).
- Khi để trống hoặc primary không còn thuộc danh mục của sản phẩm, `PrimaryCategory` tự chọn theo trọng số `priority` lớn nhất trong `config/collections.php` (`D-27`, khớp 8/8 SKU seed); admin thấy thông báo "Danh mục chính được chọn tự động".
- Breadcrumb sản phẩm: `Trang chủ / Gạch / {Primary} / {Tên SP}` (IL-04, `06` §5).

### 4.4 SlugGuard (`D-29`)

- Danh sách slug dành riêng: toàn bộ 28 slug collection + `gach`, `san-pham`, `cam-nang`, `chu-de`, `bo-suu-tap`, `xuat-xu`, `gio-hang`, `thanh-toan`, `dat-hang-thanh-cong`, `tai-khoan`, `danh-sach-bao-gia`, `tro-ly-ai-tim-gach`, `lien-he`, `showroom`, `gioi-thieu`, `dieu-khoan`, `tim-kiem`.
- Page/Post mới tạo ngoài mục đích trang hệ thống nếu có slug trùng danh sách bảo vệ (đặc biệt trùng 28 collection, `bo-suu-tap`, `xuat-xu`) -> tự thêm hậu tố `-trang` + admin notice (filter `wp_unique_post_slug`).
- Tạo `product_cat` trùng slug Page hệ thống đã có -> chặn, báo lỗi (`pre_insert_term`).

## 5. Product meta

Nhập trong tab **"Đơn vị & Thông số"** của Product Data (`Admin\ProductDataTab`), lưu qua WC CRUD (`$product->update_meta_data()`). Chi tiết kiểu dữ liệu, mặc định và hàm sanitize xem [14_Data_Dictionary_And_Settings.md](14_Data_Dictionary_And_Settings.md).

### 5.1 Danh sách Product Meta

#### A. Định giá, Đơn vị mua & Quy cách đóng gói (`D-20`, `D-24`)

| Meta key | Kiểu | Nhãn admin / Ý nghĩa | Bắt buộc (gạch) |
|---|---|---|---|
| `drv_line` | select | Ngành hàng (`gach`, mở rộng sau `keo-cha-ron`...) | ✓ (mặc định `gach`) |
| `drv_pricing_model` | select | Mô hình giá (`area_pack` / `piece` / `set` / `pack`), xem `04` | ✓ (mặc định `area_pack`) |
| `drv_price_m2` | int (VND) | Giá niêm yết / m² (để trống hoặc `0` = **Liên hệ báo giá**, `D-20`) | Tùy chọn |
| `drv_sale_price_m2` | int (VND) | Giá KM / m² (chỉ áp dụng khi `drv_price_m2 > 0`; lịch KM dùng trường WC) | |
| `drv_unit_label` | select | Nhãn đơn vị cơ sở hiển thị trên UI: `viên` (mặc định) \| `tấm` (dùng cho Big Slab khổ lớn) | ✓ |
| `drv_buy_units` | `string[]` | Các đơn vị khách được chọn mua trên PDP & Giỏ hàng: tập con khác rỗng của `['m2', 'thung', 'vien']`, mặc định `['m2', 'thung']` (`D-24`) | ✓ |
| `drv_default_buy_unit` | select | Đơn vị mở sẵn mặc định trên `<drv-unit-calc>` & quyết định hành vi Thêm nhanh trên Card: `'m2' \| 'thung' \| 'vien'`, mặc định `'m2'` (phải thuộc `drv_buy_units`) | ✓ |
| `drv_round_mode` | select | Chế độ làm tròn số lượng (`D-24`): `'box'` (mặc định - bắt buộc chẵn thùng, tự khóa về `'box'` nếu `drv_buy_units` không tích `'vien'`) \| `'piece'` (cho phép mua lẻ viên) | ✓ |
| `drv_qty_min` | int | Số lượng mua tối thiểu tính theo **viên** (mặc định `= drv_pieces_per_box` khi `box`, `= 1` khi `piece`; cho phép ghi đè từng SKU) | |
| `drv_qty_step` | int | Bước nhảy số lượng tính theo **viên** (**tự sinh, chỉ đọc** từ `drv_round_mode`: `= drv_pieces_per_box` khi `box`, `= 1` khi `piece`) | auto |
| `drv_piece_w_mm`, `drv_piece_h_mm` | int | Kích thước thực tế viên gạch Rộng × Dài (mm) | ✓ |
| `drv_thickness_mm` | float | Độ dày viên gạch (mm) | ✓ |
| `drv_pieces_per_box` | int | Số viên/thùng | ✓ |
| `drv_m2_per_box` | float | Diện tích m²/thùng (tự tính, chỉ đọc) | auto |
| `drv_kg_per_box` | float | Khối lượng/thùng (kg) - dùng tính tải trọng vận chuyển | ✓ (khuyến nghị) |
| `drv_waste_rate` | float (%) | Ghi đè tỷ lệ hao hụt mặc định riêng cho SKU (vd gạch xương cá `10`); trống thì dùng `drv_settings.order.waste_default` | |

#### B. Thông số kỹ thuật, Biến thể, Trưng bày & Tài nguyên KTS

| Meta key | Kiểu | Nhãn admin / Ý nghĩa | Bắt buộc (gạch) |
|---|---|---|---|
| `drv_water_absorption` | float (%) | Độ hút nước E (%) | ✓ |
| `drv_pei` | select I–V | Độ chịu mài mòn bề mặt PEI | |
| `drv_mohs` | int (1–10) | Độ cứng bề mặt Mohs | |
| `drv_breaking_strength` | int (N) | Lực phá vỡ (Newton, ISO 10545-4) | |
| `drv_bending_strength` | int (N/mm²) | Độ bền uốn / Modulus of rupture (N/mm² - tách biệt với `drv_breaking_strength` tính bằng N) | |
| `drv_stain_class` | int (1–5) | Cấp độ chống bám bẩn (Class 1–5, ISO 10545-14) | |
| `drv_rectified` | bool | Mài cạnh (Rectified) | |
| `drv_grout_min_mm`, `drv_grout_max_mm` | float (mm) | Độ rộng đường ron khuyến nghị tối thiểu & tối đa (vd `1.5` và `2.0`) | |
| `drv_shade` | select V1–V4 | Mức biến thiên vân/màu thiết kế của mẫu gạch (`V1` đồng nhất -> `V4` ngẫu nhiên mạnh) | |
| `drv_faces` | int | Số mặt vân (random faces) khác nhau của mẫu | |
| `drv_face_image_ids` | `int[]` | Danh sách attachment ID các mặt vân phẳng thực tế (hiển thị lưới mặt vân trên PDP) | |
| `drv_map_file_id` | int | Attachment ID file `.zip` Map vân/CAD/3D độ phân giải cao cho KTS tải về | |
| `drv_lighting_note` | string | Ghi chú hiệu ứng bề mặt dưới nhiệt độ màu đèn (`3000K` / `4000K` / `6500K`) | |
| `drv_series` | string | Mã dòng sản phẩm nhóm các SKU anh em để render swatch `<a href>` (§5.4) | |
| `drv_color_name` | string | Tên tông màu chi tiết hiển thị trên PDP & trục màu của `drv_series` (vd: `Trắng vân vàng Calacatta`) | |
| `drv_showroom_shelf` | string | Mã kệ trưng bày tại showroom (vd: `A-08`); khác rỗng tự động gán `drv_flag = showroom` | |
| `drv_card_label` | string | Nhãn phụ tối đa 20 ký tự trên chip Card (vd: `Bán chạy`, `Mới`); trống thì fallback tự động | |
| `drv_warranty` | string | Chính sách bảo hành bề mặt/xương gạch | |
| `drv_video_url` | url | URL video thực tế sản phẩm | |
| `drv_ai_blurb` | string | Mô tả tóm tắt ngữ cảnh cho Trợ lý AI tìm gạch (dự phòng Phase 3) | |
| GTIN | native WC `global_unique_id` | Mã vạch thương phẩm | |

> **Phân biệt `drv_shade` và `_drv_lot_code` (`D-30`):**
> - `drv_shade` (`V1`–`V4`) là **thuộc tính thiết kế cố định của mẫu sản phẩm**, cho biết mức độ chênh lệch vân/sắc độ giữa các viên trong cùng một thùng.
> - `_drv_lot_code` (vd: `2410A-K2`) là **mã lô nung xuất kho thực tế**, không lưu trên sản phẩm mà do nhân viên kho nhập trên từng dòng đơn hàng (`order item meta`) khi xuất hàng để phục vụ tra cứu/mua bổ sung đồng màu về sau.

Trường WC native vẫn dùng: SKU, tồn kho (đơn vị **viên**), lịch KM, ảnh đại diện + gallery, mô tả ngắn, mô tả, khối lượng (`_weight` kg/viên, tự tính `= drv_kg_per_box / drv_pieces_per_box` nếu có). Công thức đặt tên SP chuẩn SEO: `Gạch {Công năng chính} Dravit {Vân/Chất liệu} {Tên mẫu} {Kích thước cm} {Bề mặt}`.

### 5.2 Thuộc tính & dữ liệu tự sinh khi lưu

| Đầu vào | Sinh ra |
|---|---|
| `drv_piece_w_mm`, `drv_piece_h_mm` | `pa_kich-thuoc` = `{w/10}x{h/10}` (cm, cạnh ngắn trước, `.` -> `-`); `flag large_format` nếu cạnh dài > 1200mm |
| `drv_pieces_per_box` + kích thước | `drv_m2_per_box` = `round(w * h * pieces_per_box / 1_000_000, 4)` |
| `drv_kg_per_box`, `drv_pieces_per_box` | `_weight` (WC) = `round(drv_kg_per_box / drv_pieces_per_box, 3)` kg/viên |
| `drv_buy_units`, `drv_round_mode` | Nếu `'vien'` không nằm trong `drv_buy_units` -> ép `drv_round_mode = 'box'`. Sinh `drv_qty_step = drv_pieces_per_box` (khi `box`) hoặc `1` (khi `piece`); chuẩn hóa `drv_qty_min >= drv_qty_step` |
| `drv_thickness_mm` | `pa_do-day` (`mong` / `tieu-chuan` / `day`) |
| `drv_water_absorption` | `pa_do-hut-nuoc` |
| `drv_showroom_shelf` | Nếu khác rỗng -> tự gán term `showroom` của taxonomy `drv_flag` |
| `drv_price_m2 > 0` / `drv_sale_price_m2` | `_regular_price` / `_sale_price` (giá/viên, `04` §2) + `drv_price_band` theo ngưỡng giá |
| `drv_price_m2` trống hoặc `≤ 0` | Xóa `_regular_price`, `_sale_price`, `_price` về rỗng `''` + gán `drv_price_band = gia-du-an` (trạng thái **Liên hệ báo giá**, `D-20`) |
| Giá + tồn kho hiện hành | Trạng thái suy dẫn `drv_price_state` (helper runtime, không cần nhập tay): `out_of_stock` \| `quote_only` \| `sale` \| `priced` |

Attribute tự sinh hiển thị "chỉ đọc" trong tab Thuộc tính để tránh nhập trùng.

### 5.3 Quy chuẩn Gallery 6 ảnh & Attachment Meta

Để giao diện PDP, hiệu ứng đổi ảnh hover trên Card và PhotoSwipe Lightbox hiển thị thống nhất, bộ ảnh sản phẩm tuân theo thứ tự 6 vai trò chuẩn:

| Thứ tự | Vị trí lưu WC | `drv_img_role` | Tỷ lệ chuẩn | Nội dung & Vai trò hiển thị |
|---|---|---|---|---|
| 1 | Ảnh đại diện (`_thumbnail_id`) | `room` | `1:1` / `4:3` | **Phối cảnh không gian thực tế:** Ảnh chính trên Card và phần tử **LCP** trên PDP (`fetchpriority="high"`, không `loading="lazy"`). |
| 2 | `gallery[0]` | `face` | `1:1` | **Mặt vân phẳng trực diện:** Dùng làm **ảnh hover thứ 2 trên Card danh mục** (theo quy ước vị trí `gallery[0]`, không sinh meta riêng); trên PDP kèm nút tải Map CAD/3D nếu có `drv_map_file_id`. |
| 3 | `gallery[1]` | `light` | `1:1` / `4:3` | **Góc nghiêng dưới ánh sáng:** Thể hiện rõ độ bóng kiếng, mờ matt, hạt sugar hoặc rãnh carving. |
| 4 | `gallery[2]` | `grout` | `1:1` / `4:3` | **Cận cảnh đường ron & mép mài cạnh:** Minh họa mật độ ron khi ốp lát thực tế. |
| 5 | `gallery[3]` | `showroom` | `16:9` | **Ảnh tấm mẫu trên kệ showroom:** Tăng độ tin cậy vật lý (kèm mã kệ `drv_showroom_shelf` nếu có). |
| 6 | `gallery[4]` | `edge` | `16:9` | **Cận cảnh xương gạch & cạnh độ dày:** Thể hiện kết cấu xương porcelain/đá nung kết và độ dày viên. |

**Attachment meta (trên post type `attachment`):**
- `drv_img_role`: enum `room | face | light | grout | showroom | edge` (nếu bỏ trống, suy dẫn tự động theo vị trí 1..6 như bảng trên).
- `drv_img_tag`: chuỗi nhãn ngắn hiển thị góc ảnh trên gallery PDP & chú thích trong PhotoSwipe (vd: `Phối cảnh phòng khách`, `Ánh đèn vàng 3000K`, `Kệ A-08 Showroom`).

### 5.4 Nhóm biến thể anh em (`drv_series` - `D-26`)

- **Nguyên tắc kiến trúc:** Mỗi quy cách (mẫu + màu + kích thước + bề mặt) là **1 Simple Product độc lập có 1 URL riêng** (`/san-pham/{slug}`). Tuyệt đối không dùng Variable Product của WooCommerce và **cấm** đổi giá/SKU/thông số giả ngay tại chỗ bằng JS như bản prototype.
- **Liên kết qua `drv_series`:** Các SKU cùng bộ thiết kế được điền chung mã chuỗi `drv_series` (vd: `calacatta-luna`), phân biệt nhau trên tối đa **3 trục**:
  1. **Màu sắc:** Nhãn hiển thị lấy từ `drv_color_name`, chấm màu lấy từ `drv_swatch_hex` của term đầu tiên trong `pa_mau-sac`.
  2. **Kích thước:** Lấy từ `pa_kich-thuoc` (sắp theo diện tích viên tăng dần).
  3. **Bề mặt:** Lấy từ `pa_be-mat`.
- **Render SSR & xử lý ma trận thưa:**
  - Server truy vấn danh sách các sản phẩm `publish` cùng `drv_series` (cache theo nhóm series, xóa khi `drv/catalog/synced`).
  - Mỗi lựa chọn hợp lệ render bằng thẻ `<a href="{permalink}">` chuẩn SSR; lựa chọn đang khớp với SKU hiện tại gắn `aria-current="page"`.
  - Nếu một trục có giá trị tồn tại trong series nhưng **không có SKU khớp đúng tổ hợp 2 trục đang chọn** (ma trận thưa): nếu có SKU cùng giá trị trục đó ở quy cách lân cận thì link sang SKU gần nhất kèm chú thích quy cách, hoặc nếu tổ hợp hoàn toàn ngừng bán/khuyết thì render phần tử `<span aria-disabled="true" class="is-disabled">` mờ để khách nắm rõ các quy cách nhà máy sản xuất.

## 6. Term meta (`product_cat`)

Chi tiết kiểu dữ liệu và cấu trúc JSON của từng trường xem tại [14_Data_Dictionary_And_Settings.md](14_Data_Dictionary_And_Settings.md).

| Meta key | Kiểu | Dùng ở |
|---|---|---|
| `drv_collection_id` | string | Mã định danh `DRV-Cxx / Sxx / Mxx / Zxx` |
| `drv_group` | string | Nhóm danh mục (`root \| function \| space \| material \| size`) |
| `drv_h1` | string | Thẻ `<h1>` trang danh mục (tách độc lập khỏi tên ngắn của term) |
| `drv_intro` | HTML ngắn (≤ 60 chữ) | Đoạn tóm tắt "quick facts" ngay dưới H1; sanitize chỉ cho phép thẻ inline `<strong>` và `<em>` |
| `drv_hero_image` | int (attachment ID) | Ảnh nền/banner đầu trang danh mục & ảnh OG mặc định của danh mục |
| `drv_thumb_id` | int (attachment ID) | Ảnh đại diện tỷ lệ `4:3` / `1:1` cho Sub-Silo Tiles, ô danh mục Trang chủ và cột Preview Mega Menu |
| `drv_related` | `int[]` (term ID) | Danh sách danh mục liên quan mặc định (khởi tạo từ `related` trong `config/collections.php`) |
| `drv_tile_items` | `array` (JSON) | Cấu hình Visual Sub-Silo Tiles đầu trang danh mục (ghi đè `drv_related` khi cần nhãn rút gọn/ảnh riêng; ưu tiên URL tĩnh theo `D-29`) |
| `drv_quick_chips` | `array` (JSON) | Các chip lọc gợi ý nhanh hiển thị trên thanh toolbar danh mục |
| `drv_geo_table` | `array` (JSON rows) | Bảng thông số kỹ thuật chuẩn GEO của dòng gạch (`<table>` + `<caption>`, `06 §7`) |
| `drv_seo_footer` | HTML (`wp_editor`) | Khối bài viết hướng dẫn chọn gạch cuối trang danh mục (IL-01, 600–1.200 chữ) |
| `drv_faq` | `array` `{q, a}[]` | Danh sách câu hỏi thường gặp hiển thị trên trang & nguồn duy nhất sinh `FAQPage` schema (`06 §4`) |
| `drv_menu_badge` | string | Nhãn ngắn gắn cạnh tên danh mục trên Mega Menu (vd: `Phổ biến`, `Mới`) |
| `drv_menu_desc` | string | Dòng mô tả ngắn hiển thị ở Cột 5 Live Preview của Mega Menu (`D-18`, `D-29`) |
| `drv_home_spec` | string | Chuỗi thông số tóm tắt hiển thị trên thẻ Không gian / Explorer ở Trang chủ |
| `drv_lookbook` | `array` (JSON) | Cấu hình ảnh phối cảnh & danh sách SKU gắn điểm nhìn (hotspot) cho khối Explorer Trang chủ |

Mô tả term (`description`) chuẩn của WP để trống, tránh trùng lặp nội dung với `drv_intro` và `drv_seo_footer`.

### 6.1 Term meta cho thuộc tính (`pa_*`)

| Meta key | Áp dụng cho | Kiểu | Ý nghĩa |
|---|---|---|---|
| `drv_filter_label` | Mọi `pa_*` | string | Nhãn giải nghĩa đầy đủ trong sidebar lọc (vd cho `r10`: `R10: Phòng tắm khô, bếp, lối đi`) |
| `drv_short_label` | Mọi `pa_*` | string | Nhãn cực gọn hiển thị trên chip thông số của Card sản phẩm (vd: `Matt`, `Porcelain`) |
| `drv_swatch_hex` | `pa_mau-sac` | string | Mã màu `#RRGGBB` (hoặc chuỗi gradient cho `da-sac`) hiển thị ô màu trong bộ lọc & PDP |
| `drv_icon` | `pa_hinh-dang` | string | Tên class icon Phosphor (vd: `ph-square`, `ph-rectangle`, `ph-hexagon`) hiển thị trong bộ lọc hình dạng |

### 6.2 Dữ liệu tính sẵn & Cache Transient danh mục

Để không chạy truy vấn tổng hợp nặng trên mỗi lượt tải trang, `Catalog\CollectionSync` tính sẵn và lưu vào object cache / transient `drv_cat_stats` (tự động xóa và tính lại khi hook `drv/catalog/synced` kích hoạt hoặc khi sản phẩm đổi giá/tồn kho):

| Trường cache | Kiểu | Ý nghĩa & Nơi dùng |
|---|---|---|
| `price_min_m2` | int (VND) | Giá `đ/m²` thấp nhất trong các sản phẩm `priced` đang hiển thị của term (dùng cho khối Tóm tắt nhanh `<dl>`, Meta description tự sinh và Cột 5 Mega Menu) |
| `has_showroom` | bool | Danh mục có ít nhất 1 sản phẩm gắn `drv_flag = showroom` (dùng để hiển thị thông tin mẫu trưng bày và bật/ẩn thẻ gạt Showroom) |

## 7. Import CSV

- Dùng **WooCommerce -> Sản phẩm -> Nhập** (importer native).
- Đăng ký cột custom qua `woocommerce_csv_product_import_mapping_options` + `woocommerce_csv_product_import_mapping_default_columns` + `woocommerce_product_import_pre_insert_product_object`.
- Danh sách cột chuẩn: `SKU, Tên, Mô tả ngắn, Mô tả, Ảnh, Tồn kho (viên), Giá/m², Giá KM/m², Đơn vị tính hiển thị, Đơn vị cho phép mua, Đơn vị mặc định, Chế độ làm tròn, Mua tối thiểu (viên), Rộng mm, Dài mm, Dày mm, Viên/thùng, Kg/thùng, Hao hụt %, Độ hút nước %, PEI, Mohs, Lực phá vỡ, Độ bền uốn, Chống bám bẩn, Ron tối thiểu mm, Ron tối đa mm, Mài cạnh, Shade, Số mặt, Mã dòng, Tên màu chi tiết, Vị trí kệ showroom, Nhãn card, Công năng, Không gian, Vân gạch, Chất liệu, Màu sắc, Bề mặt, Phong cách, Hình dạng, DCOF, Bộ sưu tập, Xuất xứ, Danh mục chính, Có mẫu showroom` (bảng ánh xạ tên cột CSV -> meta key chi tiết tại [14_Data_Dictionary_And_Settings.md](14_Data_Dictionary_And_Settings.md)).
- Giá trị nhiều lựa chọn phân cách bằng dấu `|` (vd: `m2|thung|vien`, `phong-khach|nha-tam`). Sau khi lưu từng dòng import, `CollectionSync` tự động sinh thuộc tính dẫn xuất và gán danh mục.
- File mẫu: `docs/templates/import-gach-mau.csv` (tạo ở Phase 2 từ 8 SKU seed).

## 8. Seed data (nghiệm thu)

8 SKU chuẩn trong tài liệu Silo (Phần 4). Sau khi chạy seeder và đồng bộ, mỗi SKU phải xuất hiện **đúng 100%** tập URL danh mục tĩnh mong đợi và tự nhận đúng `Primary Category` theo trọng số `priority` tại §3.2. Test case nghiệm thu chi tiết: `09` §3.1.
