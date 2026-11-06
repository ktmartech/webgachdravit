# 05. Bộ lọc AJAX, Load more & Ma trận SEO

> Nguồn: [branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.md](branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.md) Phần 1.2 + Phần 3. Quyết định D-03.
> Skill: `wp-rest-api`, `javascript-pro`, `seo-structure-architect`, `fixing-accessibility`, `wp-performance`.

## 1. Nguyên tắc

1. **Mọi trạng thái lọc có URL thật** và PHP render đầy đủ (SSR). Bot và người dùng tắt JS vẫn lọc/phân trang được.
2. AJAX là **lớp tăng cường**: thay vùng kết quả không tải lại trang, cập nhật URL bằng History API.
3. Chiều 1 = URL tĩnh (index). Chiều 2+ = `?param` (noindex). Thuộc tính kỹ thuật = không sinh `<a>` (noindex,nofollow).
4. Tên param và slug **chỉ** lấy từ `config/facets.php` (whitelist).

## 2. Registry 14 facet · `config/facets.php`

| FLT | Param | Nguồn dữ liệu | UI | Link type | Nhóm SEO |
|---|---|---|---|---|---|
| 01 | `cong-nang` | `pa_cong-nang` | Checkbox list | Trên `/gach`: `<a>` → URL tĩnh collection. Trên collection khác: `<a>` 1-param (từ URL sạch) | `seo` |
| 02 | `khong-gian` | `pa_khong-gian` | Checkbox list | như 01 | `seo` |
| 03 | `van-gach` / `chat-lieu` | `pa_van-gach`, `pa_chat-lieu` | Checkbox list (gộp 1 nhóm UI "Vân gạch & Chất liệu") | Giá trị có collection → URL tĩnh; còn lại `?param` | `seo` |
| 04 | `kich-thuoc` | `pa_kich-thuoc` | Chip grid | 5 nhóm có collection → URL tĩnh; kích thước lẻ → `?kich-thuoc=` | `seo` |
| 05 | `bo-suu-tap` / `xuat-xu` | `pa_bo-suu-tap`, `pa_xuat-xu` | Checkbox | `<a>` 1-param | `seo` |
| 06 | `mau-sac` | `pa_mau-sac` | Swatch màu (có nhãn text) | `<a>` 1-param | `seo` |
| 07 | `be-mat` | `pa_be-mat` | Checkbox | `<a>` 1-param | `seo` |
| 08 | `phong-cach` | `pa_phong-cach` | Checkbox | `<a>` 1-param | `seo` |
| 09 | `hinh-dang` | `pa_hinh-dang` | Icon + nhãn | `<a>` 1-param | `seo` |
| 10 | `dcof` | `pa_dcof` | Checkbox | `<button>`/checkbox, **không `<a>`** | `tech` |
| 11 | `do-hut-nuoc` | `pa_do-hut-nuoc` | Checkbox | không `<a>` | `tech` |
| 12 | `do-day` | `pa_do-day` | Checkbox | không `<a>` | `tech` |
| 13 | `gia` | `drv_price_band` | Radio (đ/m²) | không `<a>` | `tech` |
| 14 | `sort`, `showroom` | orderby / `drv_flag` | Select + toggle | không `<a>` | `tech` |

```php
// config/facets.php (trích)
return [
    'kich-thuoc' => [
        'flt' => 'FLT-04', 'label' => 'Kích thước', 'source' => ['tax' => 'pa_kich-thuoc'],
        'ui' => 'chips', 'seo' => 'seo', 'operator' => 'IN', 'order' => 40,
        'static_map' => ['60x60' => 'gach-60x60', '80x80' => 'gach-80x80', '30x60' => 'gach-30x60',
                         '60x120' => 'gach-60x120', '15x80' => 'gach-15x80-20x100', /* ... */],
    ],
    'gia' => [
        'flt' => 'FLT-13', 'label' => 'Khoảng giá (đ/m²)', 'source' => ['tax' => 'drv_price_band'], 'ui' => 'radio', 'seo' => 'tech',
        'bands' => ['duoi-200k' => [0, 199999], '200k-350k' => [200000, 350000], '350k-600k' => [350001, 600000], 'tren-600k' => [600001, null]],
    ],
    'sort' => ['flt' => 'FLT-14', 'seo' => 'tech', 'ui' => 'select',
               'values' => ['moi-ve' => 'date', 'ban-chay' => 'popularity', 'gia-tang' => 'price_m2_asc', 'gia-giam' => 'price_m2_desc']],
    'showroom' => ['flt' => 'FLT-14', 'seo' => 'tech', 'ui' => 'toggle', 'source' => ['tax' => 'drv_flag', 'term' => 'showroom']],
];
```

Thứ tự hiển thị facet theo ngữ cảnh: ẩn facet đang là "chiều 1" của trang (vd ở `/gach-80x80` ẩn nhóm kích thước, hoặc hiển thị dạng link sang kích thước khác), ẩn facet có 0 kết quả, giá trị count 0 hiển thị mờ + disabled.

## 3. URL grammar & chuẩn hóa (`Filters\UrlState`)

```text
{base}[/page/{n}][?{param}={v1},{v2}&{param2}={v}...]
base  = URL tĩnh collection (/gach, /gach-lat-nen, ...), /bo-suu-tap/{slug}, /xuat-xu/{slug}
```

| Quy tắc | Chi tiết |
|---|---|
| Nhiều giá trị cùng facet | Phân cách `,` → **OR** (`?mau-sac=trang,xam`) |
| Khác facet | **AND** |
| Chuẩn hóa | Param sắp theo thứ tự `order` trong config; giá trị sắp a→z, bỏ trùng, lowercase |
| URL không chuẩn | **301** về dạng chuẩn (thứ tự param/giá trị khác, viết hoa, `,` thừa) |
| Param lạ | Bỏ qua khi render, **301** về URL đã loại bỏ param lạ (trừ `utm_*`, `gclid`, `fbclid`: giữ nguyên, không ảnh hưởng truy vấn) |
| Giá trị không tồn tại | Loại khỏi URL, 301 |
| Chọn giá trị là chiều 1 của chính trang | Bỏ (đã ngầm định) |
| Ở `/gach` chọn đúng 1 giá trị có collection (FLT-01..04) và không có filter khác | Điều hướng sang URL tĩnh collection |
| Phân trang | `/page/{n}` (WP chuẩn) giữ query string. `n` > max → 404 |
| Trailing slash | Không dùng (theo tài liệu Silo); biến thể có `/` được 301 |

## 4. Truy vấn (`Filters\QueryBuilder`)

- Hook `pre_get_posts` (main query, `is_product_category()`/`is_tax('pa_bo-suu-tap'|'pa_xuat-xu')`) và dùng lại trong REST controller → **một nguồn logic**.
- Mỗi facet → 1 mệnh đề `tax_query` (`operator IN`), các facet nối `relation AND`, trong khung term của trang hiện tại.
- `sort`: `date` | `popularity` (`total_sales`) | `price_m2_asc/desc` (meta `drv_price_m2`, `meta_value_num`). Mặc định: thứ tự menu_order + mới nhất.
- `per_page` = 24 (cấu hình). Chỉ lấy sản phẩm `visible`, tồn kho bất kỳ (hết hàng xếp cuối qua `orderby` phụ `_stock_status`).

## 5. Đếm facet (`Filters\FacetCounter`)

- Với mỗi facet F: đếm số sản phẩm khớp **tất cả filter khác ngoại trừ F** (để người dùng thấy kết quả nếu chọn thêm giá trị cùng nhóm).
- SQL trên `wp_term_relationships` + `wp_term_taxonomy` với tập ID sản phẩm của base (cache), 1 truy vấn GROUP BY cho mỗi facet.
- Cache: object cache (LiteSpeed Object Cache/Redis nếu có, fallback transient) key `drv_facets_{md5(state)}`, TTL 12h; xóa theo nhóm khi `drv/catalog/synced` hoặc đổi tồn kho/giá.

## 6. Ma trận SEO (`Seo\RobotsMatrix`, `Seo\Canonical`)

Áp dụng khi **tải trực tiếp** URL (SSR) qua filter `rank_math/frontend/robots` và `rank_math/frontend/canonical`.

| Trạng thái URL | Meta robots | Canonical | Breadcrumb/Schema |
|---|---|---|---|
| URL tĩnh, không param, trang 1 | `index, follow` | Chính nó | BreadcrumbList + CollectionPage + ItemList |
| URL tĩnh `/page/n` (n ≥ 2), không param | `index, follow` | Chính nó (`/page/n`) | BreadcrumbList + CollectionPage |
| Có param chỉ thuộc nhóm `seo` (FLT-01..09), bất kể số lượng | `noindex, follow` | URL tĩnh gốc (bỏ query + bỏ `/page/n`) | BreadcrumbList, **không** ItemList |
| Có ít nhất 1 param nhóm `tech` (FLT-10..14) | `noindex, nofollow` | URL tĩnh gốc | BreadcrumbList |
| Collection rỗng (0 sản phẩm) | `noindex, follow` + hiển thị gợi ý | Chính nó | |
| Endpoint REST `/wp-json/dravit/v1/*` | Header `X-Robots-Tag: noindex, nofollow` | | |

**Quy tắc sinh link chống spider trap:**
- Chỉ khi đang ở **URL sạch** (không param) mới render giá trị facet nhóm `seo` dạng `<a href>` 1-param.
- Khi URL đã có param: mọi giá trị facet là `<input type="checkbox">`/`<button>` (không href). Riêng link sang URL tĩnh collection khác vẫn là `<a>`.
- Facet nhóm `tech` luôn là control, không bao giờ là `<a>`.

### 6.1 robots.txt (cấu hình trong Rank Math → General → Edit robots.txt)

```txt
User-agent: *
Allow: /

# Tầng 4: tham số kỹ thuật & tiện ích UI
Disallow: /*?*dcof=
Disallow: /*?*do-hut-nuoc=
Disallow: /*?*do-day=
Disallow: /*?*gia=
Disallow: /*?*sort=
Disallow: /*?*showroom=

# Tổ hợp nhiều tham số (spider trap)
Disallow: /*?mau-sac=*&
Disallow: /*?be-mat=*&
Disallow: /*?phong-cach=*&
Disallow: /*?hinh-dang=
Disallow: /*?*&*&*

# WooCommerce & hệ thống
Disallow: /*?*add-to-cart=
Disallow: /gio-hang
Disallow: /thanh-toan
Disallow: /tai-khoan
Disallow: /wp-json/dravit/

Sitemap: https://gachdravit.vn/sitemap_index.xml
```

> Rank Math sinh sitemap tại `/sitemap_index.xml` (tài liệu Silo ghi `/sitemap.xml`; Rank Math tự redirect `/sitemap.xml` → index).

## 7. REST API (`Filters\CatalogController`)

`GET /wp-json/dravit/v1/catalog`

| Param | Kiểu | Ghi chú |
|---|---|---|
| `base` | string | `product_cat:{slug}` \| `pa_bo-suu-tap:{slug}` \| `pa_xuat-xu:{slug}` (bắt buộc) |
| `{facet}` | string | Giá trị phân cách `,`, chỉ nhận key trong `facets.php` |
| `page` | int ≥ 1 | |
| `per_page` | int | Mặc định 24, tối đa 48 |
| `fragment` | enum | `grid` (mặc định: grid + facets) \| `items` (chỉ items, cho load more) |

**Response 200**

```json
{
  "url": "/gach-phong-khach?kich-thuoc=80x80",
  "canonical": "/gach-phong-khach",
  "robots": "noindex, follow",
  "title": "Gạch Phòng Khách 80x80 | Gạch Dravit",
  "total": 36,
  "page": 1,
  "max_pages": 2,
  "next_url": "/gach-phong-khach/page/2?kich-thuoc=80x80",
  "items_html": "<li class=\"drv-grid__item\">...</li>",
  "facets_html": "<div class=\"drv-filters\">...</div>",
  "chips_html": "<ul class=\"drv-active-chips\">...</ul>",
  "facets": { "be-mat": [ { "slug": "bong-kieng", "label": "Bóng kiếng", "count": 12, "selected": false } ] }
}
```

- HTML do **cùng template part PHP** với SSR render (`template-parts/catalog/*`) → không lệch giao diện.
- Lỗi: `400 drv_invalid_param`, `404 drv_base_not_found`.
- Header: `Cache-Control: public, max-age=300`, `X-Robots-Tag: noindex`, tag LiteSpeed `drv_catalog` (`do_action('litespeed_tag_add', 'drv_catalog')`).
- Purge: khi lưu/xóa sản phẩm, đổi giá/tồn kho → `do_action('litespeed_purge', 'drv_catalog')` + xóa cache facet.

## 8. Hành vi JS (`assets/src/js/catalog.js`, ~5KB gzip)

| Sự kiện | Hành vi |
|---|---|
| Đổi checkbox/radio/select trong form lọc | Debounce 250ms → `fetch` REST (`AbortController` hủy request cũ) → thay `items_html`, `facets_html`, `chips_html`, cập nhật số kết quả → `history.pushState(url)` → `document.title = title` |
| Click `<a>` facet 1-param hoặc chip bỏ lọc | `preventDefault`, xử lý như trên |
| Click link sang **URL tĩnh collection** (chiều 1) | `fetch` chính URL đó (HTML trang đầy đủ, đã cache LiteSpeed) → `DOMParser` lấy `<main>` thay vào → cập nhật `title`, `<link rel=canonical>`, `<meta name=robots>`, JSON-LD → `pushState` |
| `popstate` (Back/Forward) | Khôi phục từ cache trong bộ nhớ theo URL, nếu không có thì fetch lại |
| Mobile | Bộ lọc trong `<dialog>` bottom sheet: chọn nhiều rồi bấm **"Xem {n} sản phẩm"** (count cập nhật trực tiếp); không fetch grid mỗi lần tick |
| Đang tải | `aria-busy="true"` trên grid, skeleton giữ chiều cao (không CLS), nút disabled |
| Lỗi mạng | Toast "Không tải được kết quả, thử lại" + fallback: điều hướng thường (`location.href = url`) |
| Không JS | Form `method="get"` + nút "Áp dụng" (ẩn khi JS chạy); link phân trang thường |
| A11y | Vùng kết quả `aria-live="polite"` đọc "Đã tìm thấy 36 sản phẩm"; focus không nhảy; mọi control có label |

## 9. Load more

- SSR render `<a class="drv-load-more" rel="next" href="/gach-lat-nen/page/2?...">Xem thêm 24 sản phẩm</a>` + "Đang xem 24/180 sản phẩm" + `<nav>` phân trang số (ẩn bằng CSS khi JS chạy, giữ cho bot và no-JS).
- JS chặn click → `fetch` `fragment=items&page=n+1` → **append** items, cập nhật `next_url`, ẩn nút khi hết trang.
- **Không** `pushState` khi load more (URL giữ trang 1). Lưu `{items đã tải, scrollY}` vào `sessionStorage` theo URL; khi Back từ trang sản phẩm về → khôi phục danh sách và vị trí cuộn.
- Ảnh trong item append dùng `loading="lazy"`; focus chuyển tới sản phẩm đầu tiên của lô mới (a11y).
- `<link rel="next">`/`prev` trong head không cần (Google không dùng), giữ link `<a rel="next">` trong body.

## 10. Trường hợp biên

| Trường hợp | Xử lý |
|---|---|
| 0 kết quả | Thông báo + chips gỡ từng filter + gợi ý collection liên quan + CTA báo giá |
| Facet có 1 giá trị duy nhất | Ẩn facet |
| Sản phẩm hết hàng | Hiển thị cuối danh sách, badge "Tạm hết" |
| `?page=` kiểu query | 301 về `/page/n` |
| Quá 5 facet cùng lúc | Vẫn cho phép (noindex,nofollow + Disallow), không giới hạn UX |

