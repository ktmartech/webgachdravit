# 05. Bộ lọc AJAX, Load more & Ma trận SEO

> Nguồn: [branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.md](branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.md) Phần 1.2 + Phần 3.
> Quyết định: D-03, D-20, D-21, D-28, D-29. Hợp đồng API & DOM chi tiết: [15_REST_API_And_JS_Contract.md](15_REST_API_And_JS_Contract.md).
> Skill: `wp-rest-api`, `javascript-pro`, `seo-structure-architect`, `fixing-accessibility`, `wp-performance`.

## 1. Nguyên tắc cốt lõi

1. **Mọi trạng thái lọc có URL thật** và PHP render đầy đủ (SSR). Bot tìm kiếm và trình duyệt tắt JS vẫn lọc và phân trang đầy đủ.
2. **AJAX là lớp tăng cường:** thay thế vùng kết quả không tải lại trang, đồng bộ URL bằng History API (`pushState` / `popstate`).
3. **Ma trận Index rõ ràng (`D-21`):** Chỉ 28 URL tĩnh Collection, `/bo-suu-tap/{slug}` và `/xuat-xu/{slug}` (0 tham số lọc) được `index, follow`. **Mọi URL có `?param` đều `noindex`** (`seo` -> `noindex, follow`; có `tech` -> `noindex, nofollow`). Toàn bộ URL trên site **không** có dấu `/` cuối.
4. **Whitelist tuyệt đối:** Tên tham số URL và slug giá trị chỉ nhận từ `config/facets.php` và hệ thuộc tính chuẩn tại `03 §2`.

## 2. Registry 14 Facet & Thứ tự hiển thị UI · `config/facets.php`

### 2.1 Bảng ánh xạ 14 Facet (`FLT-01..14`)

Lưu ý: `van-gach` và `chat-lieu` là **2 tham số URL độc lập** (dù chung 1 nhóm accordion UI *"Vân gạch & Chất liệu"*); tương tự `bo-suu-tap` và `xuat-xu` là **2 tham số URL độc lập**; `pa_dcof` tách riêng từng mức `r9`, `r10`, `r11`, `r12`, `r13` (không gộp `r12-r13`).

| FLT | Param URL (tách riêng) | Nguồn dữ liệu | Kiểu UI | Quy tắc sinh thẻ HTML | Nhóm SEO |
|---|---|---|---|---|---|
| 01 | `cong-nang` | `pa_cong-nang` | Checkbox list | Trên `/gach` (0 param): `<a href>` tới URL tĩnh collection. Trên collection khác (0 param): `<a href>` 1-param | `seo` |
| 02 | `khong-gian` | `pa_khong-gian` | Checkbox list | Như FLT-01 | `seo` |
| 03 | `van-gach`, `chat-lieu` | `pa_van-gach`, `pa_chat-lieu` | Checkbox list (chung nhóm UI *"Vân gạch & Chất liệu"*, tách `name` riêng) | Trên `/gach` (0 param): giá trị có collection riêng -> `<a href>` URL tĩnh; còn lại `<a href>` 1-param | `seo` |
| 04 | `kich-thuoc` | `pa_kich-thuoc` | Chip grid | Trên `/gach` (0 param): 5 kích thước chính -> `<a href>` URL tĩnh; kích thước lẻ -> `<a href="?kich-thuoc=...">` | `seo` |
| 05 | `bo-suu-tap`, `xuat-xu` | `pa_bo-suu-tap`, `pa_xuat-xu` | Checkbox list (chung nhóm UI *"Bộ sưu tập & Xuất xứ"*, tách `name` riêng) | Trên `/gach` (0 param): `<a href>` tới `/bo-suu-tap/{slug}` hoặc `/xuat-xu/{slug}`; trên collection khác (0 param): `<a href>` 1-param | `seo` |
| 06 | `mau-sac` | `pa_mau-sac` | Swatch màu (`drv_swatch_hex`) + nhãn text (`trang`, `xam`, `kem`, `vang`, `nau`, `den`, `xanh-duong`, `xanh-la`, `hong`, `da-sac`) | Khi URL sạch: `<a href>` 1-param; khi đã có param: `<input type="checkbox">` | `seo` |
| 07 | `be-mat` | `pa_be-mat` | Checkbox list (`bong-kieng`, `mo`, `ban-bong`, `nham`, `sugar`, `carving`) | Khi URL sạch: `<a href>` 1-param | `seo` |
| 08 | `phong-cach` | `pa_phong-cach` | Checkbox list | Khi URL sạch: `<a href>` 1-param | `seo` |
| 09 | `hinh-dang` | `pa_hinh-dang` | Icon (`drv_icon`) + nhãn | Khi URL sạch: `<a href>` 1-param | `seo` |
| 10 | `dcof` | `pa_dcof` | Checkbox (`r9`, `r10`, `r11`, `r12`, `r13` + `drv_filter_label`) | Luôn là `<input>` / `<button>`, **cấm `<a href>`** | `tech` |
| 11 | `do-hut-nuoc` | `pa_do-hut-nuoc` | Checkbox (`duoi-0-5`, `0-5-3`, `3-6`, `6-10`, `tren-10`) | Luôn là `<input>`, **cấm `<a href>`** | `tech` |
| 12 | `do-day` | `pa_do-day` | Checkbox (`mong`, `tieu-chuan`, `day`) | Luôn là `<input>`, **cấm `<a href>`** | `tech` |
| 13 | `gia` | `drv_price_band` | Radio (`duoi-200k`, `200k-350k`, `350k-600k`, `tren-600k`, `gia-du-an`) | Luôn là `<input type="radio">`, **cấm `<a href>`** | `tech` |
| 14 | `showroom` · `sort` | `drv_flag` (`showroom=1`) · `orderby` (`moi-ve \| ban-chay \| gia-tang \| gia-giam`) | Toggle card đầu sidebar (`showroom`) + Select trên toolbar (`sort`) | Luôn là `<input>` / `<select>`, **cấm `<a href>`** | `tech` |

```php
// config/facets.php (trích cấu trúc chuẩn)
return [
    'showroom' => [
        'flt' => 'FLT-14', 'label' => 'Có mẫu tại Showroom', 'ui' => 'toggle_card',
        'seo' => 'tech', 'order' => 5, 'ui_order' => 1,
        'source' => ['tax' => 'drv_flag', 'term' => 'showroom', 'value' => '1'],
    ],
    'kich-thuoc' => [
        'flt' => 'FLT-04', 'label' => 'Kích thước', 'source' => ['tax' => 'pa_kich-thuoc'],
        'ui' => 'chips', 'seo' => 'seo', 'operator' => 'IN', 'order' => 40, 'ui_order' => 2,
        'static_map' => [
            '60x60' => 'gach-60x60', '80x80' => 'gach-80x80', '30x60' => 'gach-30x60',
            '60x120' => 'gach-60x120', '15x80' => 'gach-15x80-20x100', '15x90' => 'gach-15x80-20x100',
            '20x100' => 'gach-15x80-20x100', '20x120' => 'gach-15x80-20x100',
        ],
    ],
    'gia' => [
        'flt' => 'FLT-13', 'label' => 'Khoảng giá (đ/m²)', 'source' => ['tax' => 'drv_price_band'],
        'ui' => 'radio', 'seo' => 'tech', 'order' => 130, 'ui_order' => 3,
        'bands' => [
            'duoi-200k' => [1, 199999], '200k-350k' => [200000, 350000],
            '350k-600k' => [350001, 600000], 'tren-600k' => [600001, null], 'gia-du-an' => [0, 0],
        ],
    ],
    'be-mat' => [
        'flt' => 'FLT-07', 'label' => 'Bề mặt', 'source' => ['tax' => 'pa_be-mat'],
        'ui' => 'checkbox', 'seo' => 'seo', 'operator' => 'IN', 'order' => 70, 'ui_order' => 4,
    ],
    'sort' => [
        'flt' => 'FLT-14', 'label' => 'Sắp xếp', 'seo' => 'tech', 'ui' => 'select', 'order' => 140,
        'default' => 'moi-ve',
        'values' => [
            'moi-ve'   => 'date',
            'ban-chay' => 'popularity',
            'gia-tang' => 'price_m2_asc',
            'gia-giam' => 'price_m2_desc',
        ],
    ],
    // ... các facet còn lại: cong-nang(10), khong-gian(20), van-gach(30), chat-lieu(35),
    // bo-suu-tap(50), xuat-xu(55), mau-sac(60), phong-cach(80), hinh-dang(90), dcof(100), do-hut-nuoc(110), do-day(120)
];
```

### 2.2 Thứ tự hiển thị UI & Quy tắc ẩn theo ngữ cảnh trang

1. **Vị trí trên Sidebar / Filter Sheet:**
   - **Đứng đầu tiên (`ui_order = 1`):** Thẻ gạt nổi bật **"Có mẫu tại Showroom"** (`?showroom=1`; tự ẩn nếu danh mục hiện tại có `has_showroom = false`).
   - **Tiếp theo là 13 nhóm lọc accordion (`<details>`):** Kích thước -> Khoảng giá -> Bề mặt -> Màu sắc -> Vân gạch & Chất liệu -> Không gian -> Công năng -> Chống trượt R -> Phong cách -> Hình dạng -> Độ hút nước -> Độ dày -> Bộ sưu tập & Xuất xứ (tham số `sort` tách riêng đặt trên thanh Sticky Toolbar phía trên lưới sản phẩm).
   - **Trạng thái đóng/mở mặc định:** **3 nhóm đầu tiên** (Kích thước, Khoảng giá, Bề mặt) cùng mọi nhóm đang có điều kiện lọc `selected` được mở sẵn (`<details open>`); các nhóm còn lại đóng gọn. Tiêu đề mỗi nhóm hiển thị badge số lượng giá trị đang chọn (vd `(2)`).
2. **Ẩn Facet / Giá trị trùng Chiều 1 của trang hiện tại:**
   - Để người dùng không lọc lặp lại điều kiện gốc của chính danh mục đang xem, `Filters\Renderer` tự động ẩn giá trị hoặc nhóm lọc tương ứng với rule chiều 1 của trang:
     - Trên `/gach-phong-khach`: ẩn giá trị `phong-khach` khỏi nhóm `khong-gian` (các không gian khác nếu sản phẩm kiêm nhiệm vẫn hiện để lọc giao thoa, hoặc chuyển sang trang tĩnh qua Sub-Silo Tiles).
     - Trên các Collection kích thước đơn lẻ (`/gach-60x60`, `/gach-80x80`, `/gach-30x60`, `/gach-60x120`): ẩn nhóm `kich-thuoc` khỏi sidebar (việc chuyển sang kích thước khác thực hiện qua dải Sub-Silo Tiles đầu trang); riêng `/gach-15x80-20x100` vẫn hiện nhóm `kich-thuoc` để lọc chi tiết giữa `15x80`, `15x90`, `20x100`, `20x120`.
     - Trên `/bo-suu-tap/{slug}`: ẩn phần `bo-suu-tap`; trên `/xuat-xu/{slug}`: ẩn phần `xuat-xu`.
3. **Trạng thái 0 kết quả:** Nhóm facet không có bất kỳ giá trị nào khả dụng trong phạm vi danh mục gốc thì ẩn hoàn toàn; giá trị lẻ có `count = 0` do tổ hợp lọc hiện tại thì hiển thị mờ + `disabled` (trừ giá trị đang được tick chọn để khách có thể bỏ chọn).

## 3. URL grammar & Chuẩn hóa (`Filters\UrlState`)

```text
{base}[/page/{n}][?{param}={v1},{v2}&{param2}={v}...]
base = URL tĩnh không dấu '/' cuối: /gach, /gach-lat-nen, ..., /bo-suu-tap/{slug}, /xuat-xu/{slug}
```

| Quy tắc | Chi tiết xử lý |
|---|---|
| Nhiều giá trị cùng facet | Phân cách `,` -> toán tử **OR** (vd `?mau-sac=den,nau` hoặc `?be-mat=mo,nham`) |
| Khác facet | Toán tử **AND** (vd `?kich-thuoc=80x80&be-mat=mo&do-hut-nuoc=duoi-0-5`) |
| Chuẩn hóa thứ tự & chữ thường | Các param sắp xếp cố định theo `order` trong `config/facets.php`; các slug giá trị trong cùng param sắp a->z, chữ thường, khử trùng lặp |
| Giá trị mặc định của `sort` & `showroom` | `sort=moi-ve` là mặc định -> không ghi lên URL (nếu truy cập `?sort=moi-ve` thì 301 bỏ `sort`). `showroom` chỉ nhận giá trị `1` (`?showroom=1`); mọi giá trị khác bị loại bỏ |
| URL sai thứ tự / dư dấu `,` | **301 Redirect** về URL chuẩn |
| Param lạ ngoài whitelist | Bỏ qua khi query và **301 Redirect** về URL sạch param lạ (riêng nhóm tracking `utm_*`, `gclid`, `fbclid`, `zarsrc` được giữ nguyên trên thanh địa chỉ nhưng không đưa vào `canonical` hay cache key lọc; riêng `?quote=` và `?ai_query=` giữ cho UI xử lý và ép `noindex, nofollow`) |
| Giá trị slug không tồn tại | Loại khỏi URL -> **301 Redirect** |
| Lọc lại đúng giá trị chiều 1 của trang | Tự loại bỏ giá trị thừa -> **301 Redirect** (vd `/gach-phong-khach?khong-gian=phong-khach` -> `/gach-phong-khach`) |
| Ở `/gach` chọn đúng 1 điều kiện đã có Collection tĩnh | **301 Redirect** thẳng sang URL tĩnh (vd `/gach?cong-nang=lat-nen` -> `/gach-lat-nen`; `/gach?chat-lieu=da-nung-ket` -> `/da-nung-ket`) |
| Phân trang | `/page/{n}` giữ nguyên query string phía sau (`/gach-phong-khach/page/2?kich-thuoc=80x80`). `?page={n}` -> 301 về `/page/{n}`. `n > max_pages` -> trả mã `404` |
| Dấu `/` cuối URL (`D-21`) | **Tuyệt đối không dùng dấu `/` cuối** ở mọi URL; biến thể dư `/` được **301 Redirect** về không `/` |

## 4. Truy vấn (`Filters\QueryBuilder`)

- Hook vào `pre_get_posts` cho main query (`is_product_category()` / `is_tax(['pa_bo-suu-tap', 'pa_xuat-xu'])`) và tái sử dụng nguyên bản trong REST `CatalogController` -> **một nguồn truy vấn duy nhất**.
- Mỗi facet đang chọn sinh 1 mệnh đề `tax_query` (`operator => 'IN'`), nối giữa các facet bằng `relation => 'AND'`, luôn giới hạn trong term gốc của trang hiện tại.
- Sắp xếp (`sort`):
  - `moi-ve` (mặc định): `menu_order ASC, date DESC`.
  - `ban-chay`: `total_sales DESC, date DESC`.
  - `gia-tang` / `gia-giam`: sắp theo meta số `drv_price_m2`. Các sản phẩm không niêm yết giá (`drv_price_m2` trống hoặc `≤ 0`, thuộc `gia-du-an`) luôn được xếp **sau** toàn bộ sản phẩm có giá ở cả 2 chiều tăng/giảm để không đẩy mẫu `0đ` lên đầu khi chọn giá thấp đến cao (`D-20`).
- `per_page = 24` (lấy từ `drv_settings.catalog.per_page`). Chỉ lấy sản phẩm hiển thị trong catalog (`product_visibility`), đẩy sản phẩm hết hàng (`outofstock`) xuống cuối danh sách.

## 5. Đếm Facet (`Filters\FacetCounter`)

- Với mỗi facet `F`, số đếm hiển thị bên cạnh từng giá trị là số sản phẩm khớp với **tất cả các điều kiện lọc đang áp dụng NGOẠI TRỪ chính `F`** (giúp khách biết rõ nếu chọn thêm 1 màu hoặc 1 bề mặt cùng nhóm thì có bao nhiêu mẫu).
- Thực thi bằng truy vấn SQL chuẩn bị sẵn (`$wpdb->prepare`) nhóm `GROUP BY term_taxonomy_id` trên tập ID sản phẩm của base collection.
- Lưu vào object cache / transient `drv_facets_{md5(state)}`, TTL 12 giờ; tự động xóa theo nhóm tag `drv_catalog` khi `drv/catalog/synced` hoặc thay đổi giá/tồn kho.

## 6. Ma trận Robots & Canonical (`Seo\RobotsMatrix`, `Seo\Canonical` - `D-21`)

Áp dụng trực tiếp ở SSR qua filter `rank_math/frontend/robots` và `rank_math/frontend/canonical`. **Khẳng định quy tắc `D-21`: bất kỳ URL nào có chứa tham số lọc (`?param=...`), dù chỉ 1 tham số đơn lẻ thuộc nhóm `seo`, đều bắt buộc là `noindex`.**

| Trạng thái URL | Meta `robots` | Thẻ `canonical` (không `/` cuối) | Schema JSON-LD xuất kèm |
|---|---|---|---|
| URL tĩnh sạch (`/gach`, 27 collection, `/bo-suu-tap/{slug}`, `/xuat-xu/{slug}`), trang 1 (`≥ 1` SP) | `index, follow` | Chính nó (vd `https://gachdravit.vn/gach-phong-khach`) | `BreadcrumbList` + `CollectionPage` + `ItemList` (24 SP) + `FAQPage` (nếu có `drv_faq`) |
| URL tĩnh sạch phân trang `/page/{n}` (`n ≥ 2`, không param) | `index, follow` | Chính nó (vd `https://gachdravit.vn/gach-phong-khach/page/2`) | `BreadcrumbList` + `CollectionPage` (ẩn `ItemList` & `FAQPage`) |
| Có tham số **chỉ** thuộc nhóm `seo` (FLT-01..09), bất kể 1 hay nhiều tham số | `noindex, follow` | URL tĩnh gốc (bỏ toàn bộ query & bỏ `/page/{n}`) | `BreadcrumbList` (ẩn `ItemList` & `FAQPage`) |
| Có **ít nhất 1** tham số nhóm `tech` (FLT-10..14: `dcof`, `do-hut-nuoc`, `do-day`, `gia`, `sort`, `showroom`) | `noindex, nofollow` | URL tĩnh gốc (bỏ toàn bộ query & bỏ `/page/{n}`) | `BreadcrumbList` |
| Có tham số tiện ích nội bộ (`?quote=...`, `?ai_query=...`) | `noindex, nofollow` | URL tĩnh gốc | `BreadcrumbList` |
| Danh mục rỗng (`0` sản phẩm) | `noindex, follow` | Chính nó | `BreadcrumbList` |
| Endpoint REST `/wp-json/dravit/v1/*` | Header `X-Robots-Tag: noindex, nofollow` | - | - |

**Quy tắc sinh thẻ HTML chống Spider Trap:**
- Chỉ khi người dùng/bot đang ở **URL tĩnh sạch (0 tham số lọc)**, các lựa chọn thuộc nhóm `seo` (FLT-01..09) mới được render kèm `<a href="...">` (trỏ về URL tĩnh nếu có trong `static_map`, ngược lại trỏ URL 1-param).
- Ngay khi URL **đã có ít nhất 1 tham số**, mọi lựa chọn trong form lọc chuyển sang render bằng `<input type="checkbox">` / `<input type="radio">` thuần (không sinh thêm `<a href>` nối 2–3 tham số). Riêng các dải Sub-Silo Tiles dẫn sang Collection tĩnh vẫn giữ `<a href>` bình thường.
- Nhóm `tech` (FLT-10..14) **luôn luôn** là form control (`<input>`, `<select>`, `<button>`), không bao giờ sinh `<a href>`.

### 6.1 Nội dung `robots.txt` (cấu hình trong Rank Math -> General -> Edit robots.txt)

```txt
User-agent: *
Allow: /

# Tầng 4: tham số kỹ thuật & sắp xếp UI
Disallow: /*?*dcof=
Disallow: /*?*do-hut-nuoc=
Disallow: /*?*do-day=
Disallow: /*?*gia=
Disallow: /*?*sort=
Disallow: /*?*showroom=

# Tham số tiện ích báo giá & trợ lý AI
Disallow: /*?*quote=
Disallow: /*?*ai_query=
Disallow: /danh-sach-bao-gia

# Tổ hợp nhiều tham số (chống spider trap)
Disallow: /*?mau-sac=*&
Disallow: /*?be-mat=*&
Disallow: /*?phong-cach=*&
Disallow: /*?hinh-dang=
Disallow: /*?*&*&*

# WooCommerce & REST nội bộ
Disallow: /*?*add-to-cart=
Disallow: /gio-hang
Disallow: /thanh-toan
Disallow: /tai-khoan
Disallow: /wp-json/dravit/

Sitemap: https://gachdravit.vn/sitemap_index.xml
```

> Lưu ý: Không chặn bằng `robots.txt` đối với các URL 1-param nhóm `seo` (như `?kich-thuoc=80x80`) để Googlebot vẫn đọc được thẻ `<meta name="robots" content="noindex, follow">` trên trang và tuân thủ chuẩn ma trận SEO.

## 7. REST API (`Filters\CatalogController`)

`GET /wp-json/dravit/v1/catalog` (chi tiết đầy đủ schema và mã lỗi tại [15_REST_API_And_JS_Contract.md](15_REST_API_And_JS_Contract.md)).

| Param | Kiểu | Mô tả |
|---|---|---|
| `base` | string | Bắt buộc: `product_cat:{slug}` \| `pa_bo-suu-tap:{slug}` \| `pa_xuat-xu:{slug}` |
| `{facet}` | string | Giá trị phân cách bằng `,`, chỉ nhận các key hợp lệ trong `config/facets.php` |
| `page` | int ≥ 1 | Trang hiện tại (mặc định `1`) |
| `per_page` | int | Mặc định `24`, giới hạn tối đa `48` |
| `fragment` | enum | `grid` (mặc định: trả lưới + facets + chips) \| `items` (chỉ trả thẻ SP nối thêm cho Load More) \| `count` (chỉ trả `total`, `facets`, `active` siêu nhẹ để cập nhật tức thời nút *"Xem {n} sản phẩm"* trong Mobile Filter Sheet) |
| `include_items` | `0\|1` | Tùy chọn (`1`): đính kèm mảng dữ liệu `items[]` JSON rút gọn cho GA4 `view_item_list` và đồng bộ trạng thái nút Báo giá |

**Response `200 OK` (`fragment=grid`):**

```json
{
  "url": "/gach-phong-khach?kich-thuoc=80x80&be-mat=mo",
  "canonical": "/gach-phong-khach",
  "robots": "noindex, follow",
  "title": "Gạch Phòng Khách 80x80 Mờ (Matt) | Gạch Dravit",
  "total": 36,
  "loaded": 24,
  "page": 1,
  "max_pages": 2,
  "next_url": "/gach-phong-khach/page/2?kich-thuoc=80x80&be-mat=mo",
  "active": [
    {
      "param": "kich-thuoc",
      "slug": "80x80",
      "group_label": "Kích thước",
      "label": "80x80",
      "remove_url": "/gach-phong-khach?be-mat=mo"
    },
    {
      "param": "be-mat",
      "slug": "mo",
      "group_label": "Bề mặt",
      "label": "Mờ (Matt)",
      "remove_url": "/gach-phong-khach?kich-thuoc=80x80"
    }
  ],
  "items_html": "<li class=\"drv-grid__item\">...</li>",
  "facets_html": "<div class=\"drv-filters__groups\">...</div>",
  "chips_html": "<ul class=\"drv-active-chips\">...</ul>",
  "facets": {
    "mau-sac": {
      "label": "Màu sắc",
      "values": [
        {
          "slug": "nau",
          "label": "Nâu",
          "count": 8,
          "selected": false,
          "disabled": false,
          "swatch": "#78350F",
          "href": null
        },
        {
          "slug": "den",
          "label": "Đen",
          "count": 0,
          "selected": false,
          "disabled": true,
          "swatch": "#1E293B",
          "href": null
        }
      ]
    }
  },
  "items": [
    {
      "id": 1024,
      "sku": "DRV-8801M",
      "name": "Gạch Lát Nền Dravit Vân Đá Marble Calacatta 80x80",
      "price_m2": 385000,
      "price_piece": 246400,
      "price_state": "priced",
      "category": "Gạch lát nền"
    }
  ]
}
```

- `items_html`, `facets_html`, `chips_html` được render từ **cùng bộ template part PHP** (`template-parts/catalog/*`) như SSR -> đảm bảo đồng nhất 100% markup.
- Mã lỗi chuẩn: `400 drv_invalid_param`, `404 drv_base_not_found`.
- Header HTTP: `Cache-Control: public, max-age=300`, `X-Robots-Tag: noindex, nofollow`, gắn tag LiteSpeed `drv_catalog` (`do_action('litespeed_tag_add', 'drv_catalog')`) và tự động purge khi sản phẩm đổi dữ liệu.

## 8. Kiến trúc DOM & Hành vi JS (`assets/src/js/catalog.js`)

### 8.1 Một nút `<form>` duy nhất dùng chung Desktop & Mobile

- **Cấm nhân đôi HTML form lọc:** Server chỉ render `<form data-drv-filter-form method="get">` **đúng 1 lần** bên trong `<aside class="drv-catalog__sidebar">`.
- Khi ở màn hình mobile (`< 1024px`) hoặc khi người dùng mở `<dialog data-drv-filter-sheet>` (bottom sheet), custom element `<drv-filter-form>` di chuyển trực tiếp node `<form>` này sang vùng chứa bên trong `<dialog>`, và trả lại vào `<aside>` khi viewport mở rộng lên `≥ 1024px` (`matchMedia('(min-width: 1024px)')`).
- Kiến trúc này loại bỏ ~450 dòng HTML lặp lại trên mỗi trang danh mục, đồng thời triệt tiêu lỗi trùng lặp thuộc tính `name` / `id` khi serialize dữ liệu form.

### 8.2 Thanh công cụ dính (Sticky Toolbar), Active Chips & Cấm Badge Debug

- **Sticky Toolbar:** Đặt ngay phía trên lưới sản phẩm, bao gồm:
  1. Nút **Ẩn / Hiện bộ lọc** trên desktop (`data-drv-action="toggle-filter-sidebar"`): đóng mở mượt cột sidebar, chuyển lưới sản phẩm từ **3 cột** (khi mở sidebar) sang **4 cột** (khi ẩn sidebar).
  2. Nút mở **Bộ lọc** trên mobile kèm số lượng điều kiện đang chọn.
  3. Tổng số sản phẩm khớp điều kiện (`aria-live="polite"`).
  4. Dải Quick Chips gợi ý nhanh (`drv_quick_chips`) và ô `<select name="sort">` chọn thứ tự sắp xếp.
- **Active Filter Chips (`template-parts/catalog/active-chips.php`):** Mỗi chip đang lọc hiển thị kèm **tiền tố tên nhóm** để rõ nghĩa (vd: `Kích thước: 80x80 ×`, `Bề mặt: Nhám sần ×`, `Độ hút nước: ≤ 0,5% ×`, `Có mẫu Showroom ×`), kèm nút **"Xóa tất cả"** khi có từ 2 điều kiện trở lên.
- **Quy tắc Production UI:** Tuyệt đối **không render** huy hiệu debug trạng thái SEO/Robots (như chip `INDEX, FOLLOW` / `NOINDEX` dùng kiểm thử nội bộ trong bản prototype) ra HTML giao diện thật.

### 8.3 Bảng sự kiện tương tác

| Tương tác | Hành vi xử lý |
|---|---|
| Đổi checkbox/radio/toggle trên **Desktop** hoặc đổi `sort` | Debounce `250ms` -> gọi `GET /wp-json/dravit/v1/catalog?fragment=grid` (`AbortController` hủy request cũ nếu khách bấm liên tiếp) -> cập nhật `items_html`, `facets_html`, `chips_html`, số đếm, `<meta name="robots">`, `<link rel="canonical">` -> `history.pushState(url)` + `document.title = title` |
| Tick chọn trong **`<dialog>` Mobile Sheet** | Không tải lại lưới ngay; debounce `200ms` gọi `fragment=count` -> cập nhật con số trên nút **"Xem {n} sản phẩm"** và trạng thái `disabled`/count của các ô khác. Chỉ khi bấm **"Xem {n} sản phẩm"** mới gọi `fragment=grid`, đóng `<dialog>`, trả focus và `pushState` |
| Click `<a href>` facet 1-param hoặc chip gỡ lọc | `preventDefault()`, chạy luồng AJAX `fragment=grid` như trên |
| Click link sang **URL tĩnh Collection khác** (Sub-Silo Tiles / FLT-01..05 tĩnh) | `fetch` chính URL tĩnh đó (tận dụng HTML đã cache bởi LiteSpeed) -> `DOMParser` thay thế `<main>`, cập nhật `title`, `canonical`, `robots` và khối JSON-LD -> `history.pushState` |
| `popstate` (nút Back / Forward trình duyệt) | Khôi phục từ bộ nhớ đệm theo URL trong phiên, hoặc gọi lại REST `fragment=grid` nếu chưa có |
| Trạng thái đang tải / Lỗi mạng | Đặt `aria-busy="true"` trên lưới, phủ lớp mờ/skeleton giữ nguyên kích thước chống nhảy CLS. Nếu lỗi mạng (khác `AbortError`), hiện toast báo lỗi và tự động điều hướng thường `window.location.href = targetUrl` |
| Trình duyệt tắt JS | `<form method="get">` hiển thị nút submit **"Áp dụng"** (ẩn bằng CSS khi có `js` class trên `<html>`); phân trang số chuẩn hoạt động đầy đủ |

## 9. Load More & Khôi phục vị trí cuộn

- SSR render nút `<a class="drv-load-more" rel="next" href="/gach-lat-nen/page/2?...">Xem thêm 24 sản phẩm</a>` đi kèm thanh tiến độ `"Đang xem {loaded} / {total} sản phẩm"` và `<nav>` phân trang số chuẩn (ẩn bằng CSS khi JS hoạt động, giữ nguyên cho bot tìm kiếm và người dùng tắt JS).
- Khi click nút Load More, JS chặn điều hướng -> gọi `fragment=items&page=n+1` -> nối (`append`) thẻ sản phẩm mới vào cuối lưới, cập nhật thanh tiến độ `loaded / total`, đổi `href` sang `next_url` (hoặc ẩn nút khi `page >= max_pages`).
- **Không** gọi `pushState` sang `/page/2` khi bấm Load More (giữ URL trang hiện tại). Lưu trạng thái `{ loadedPage, htmlSnapshot, scrollY }` vào `sessionStorage` theo key URL lọc hiện hành để khi khách bấm vào xem chi tiết 1 viên gạch rồi bấm **Back** quay lại, danh sách đã mở rộng và vị trí cuộn được khôi phục tức thì.
- Ảnh trong các card mới nối thêm dùng `loading="lazy"`; chuyển focus bàn phím vào sản phẩm đầu tiên của lô mới tải để đảm bảo trợ năng (WCAG 2.2 AA).

## 10. Trường hợp biên

| Trường hợp | Cách xử lý |
|---|---|
| `0` kết quả khớp tổ hợp lọc | Hiển thị trạng thái rỗng (`template-parts/catalog/empty-state.php`): danh sách chip gỡ bớt từng điều kiện, nút **"Xóa toàn bộ bộ lọc"**, gợi ý các Collection liên quan và CTA **Nhận tư vấn mẫu tương đương qua Zalo / Báo giá** |
| Nhóm facet chỉ có 1 giá trị duy nhất và không đang được chọn | Tự ẩn nhóm đó để giảm nhiễu thị giác |
| Sản phẩm tạm hết hàng (`outofstock`) | Luôn xếp cuối danh sách, hiển thị nhãn `Tạm hết` trên ảnh Card |
| Truy cập `?page=2` dạng query string | `301 Redirect` về `/page/2` (giữ các param lọc hợp lệ khác) |
| Khách chọn đồng thời trên 5 facet | Vẫn hoạt động bình thường trên UI; phía bot đã được khóa bằng `noindex, nofollow` + `robots.txt Disallow` |
