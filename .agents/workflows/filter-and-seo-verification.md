---
description: Quy trình triển khai và kiểm định bộ lọc AJAX 14 facets, ma trận SEO robots/canonical, Schema JSON-LD và GEO
---

# Faceted Filter, Technical SEO & GEO Verification Workflow

> **Docs bắt buộc đọc:** `docs/05_Faceted_Filter_AJAX.md`, `docs/06_SEO_GEO_Spec.md`
> **Rules:** `.agents/rules/dravit-filter-seo-geo.md`, `.agents/rules/dravit-security-testing.md`
> **Skills:** `wp-rest-api`, `seo-structure-architect`, `schema-markup`, `programmatic-seo`, `seo-audit`

---

## BƯỚC 1: CHUẨN HÓA URL & PARAMS (`Filters\UrlState`)

1. Đọc danh sách param hợp lệ từ `config/facets.php`.
2. Trên mọi request vào trang danh mục (`template_redirect` ưu tiên cao):
   - Bỏ qua các param tracking (`utm_*`, `gclid`, `fbclid`) khi xét lọc.
   - Nếu có param lạ không nằm trong `config/facets.php` hoặc giá trị slug không tồn tại → loại bỏ và `wp_safe_redirect($clean_url, 301)`.
   - Nếu đang ở `/gach` và chỉ lọc duy nhất 1 giá trị thuộc `FLT-01..04` mà giá trị đó có trang Collection tĩnh riêng → `wp_safe_redirect(home_url('/' . $collection_slug), 301)`.
   - Nếu thứ tự các param khác thứ tự `order` trong `facets.php`, hoặc các giá trị cách nhau bởi dấu `,` chưa sắp xếp `a -> z` → `wp_safe_redirect($canonicalized_filter_url, 301)`.

---

## BƯỚC 2: TRIỂN KHAI BỘ LỌC SSR + AJAX + LOAD MORE

1. **QueryBuilder (`Filters\QueryBuilder`):**
   - Dùng chung cho cả `pre_get_posts` (SSR) và `GET /wp-json/dravit/v1/catalog` (AJAX).
   - Mỗi facet là 1 nhánh `tax_query` (`operator => 'IN'`), giữa các facet nối bằng `relation => 'AND'`.
   - Hỗ trợ `sort`: `moi-ve` (`date`), `ban-chay` (`total_sales`), `gia-tang` / `gia-giam` (`drv_price_m2` `meta_value_num`); đẩy sản phẩm hết hàng xuống cuối danh sách.
2. **Quy tắc render HTML Facet (`Filters\Renderer`):**
   - Kiểm tra `$url_state->has_any_filter()`:
     - Nếu `false` (URL tĩnh sạch): render các mục nhóm `seo` (`FLT-01..09`) bằng thẻ `<a href="...">` (1 tham số hoặc link sang URL tĩnh).
     - Nếu `true` (đã có ít nhất 1 tham số): render toàn bộ mục lọc bằng `<input type="checkbox">` / `<button>`, **không** sinh thêm `<a href>` nhiều tham số.
   - Các mục nhóm `tech` (`FLT-10..14`: `dcof`, `do-hut-nuoc`, `do-day`, `gia`, `sort`, `showroom`): luôn render bằng `<button>` / `<input>` / `<select>`, không bao giờ dùng `<a href>`.
3. **AJAX & Load More (`assets/src/js/catalog.js`):**
   - Sử dụng `AbortController` để hủy request cũ khi người dùng tick nhanh nhiều ô.
   - Cập nhật `items_html`, `facets_html`, `chips_html`, `document.title` và `history.pushState()`.
   - Nút Load More dùng `<a rel="next" href=".../page/2?...">`; khi click gọi `fragment=items`, nối thêm card vào lưới và lưu trạng thái vào `sessionStorage` để giữ vị trí khi bấm Back.

---

## BƯỚC 3: MA TRẬN ROBOTS, CANONICAL & SCHEMA (`Seo`)

1. **Filter `rank_math/frontend/robots`:**
   - Có bất kỳ param nhóm `tech` (`dcof`, `do-hut-nuoc`, `do-day`, `gia`, `sort`, `showroom`) → `['index' => 'noindex', 'follow' => 'nofollow']`.
   - Có bất kỳ param nhóm `seo` (nhưng không có param `tech`) hoặc danh mục 0 sản phẩm → `['index' => 'noindex', 'follow' => 'follow']`.
   - URL tĩnh sạch (kể cả `/page/n` có sản phẩm) → `['index' => 'index', 'follow' => 'follow']`.
2. **Filter `rank_math/frontend/canonical`:**
   - Khi có bất kỳ param lọc nào → trả về URL tĩnh gốc của danh mục (bỏ toàn bộ query string và bỏ `/page/n`).
3. **Filter `rank_math/json_ld`:**
   - Trang Collection đang `index`: giữ `BreadcrumbList` + `CollectionPage` (`isPartOf` → `/gach#webpage`) + thêm `ItemList` (24 sản phẩm đầu) + `FAQPage` (nếu có `drv_faq`).
   - Trang Collection đang `noindex` (do có `?filter`): ẩn `ItemList` và `FAQPage`.
   - Trang Sản phẩm: bổ sung `additionalProperty` (7+ thông số kỹ thuật) và 2 `UnitPriceSpecification` (`C62` cho `viên`, `MTK` cho `m²`) vào `Product`.

---

## BƯỚC 4: KIỂM ĐỊNH TRƯỚC KHI ĐÓNG TASK (QA Commands)

Chạy kiểm tra bằng `curl -I` / kiểm tra HTML đầu ra trên các URL mẫu:
- [ ] `/gach-phong-khach` → `robots="index, follow"`, `canonical="/gach-phong-khach"`, có `CollectionPage` + `ItemList`.
- [ ] `/gach-phong-khach?kich-thuoc=80x80` → `robots="noindex, follow"`, `canonical="/gach-phong-khach"`, không còn thẻ `<a href>` lọc tổ hợp 2 param.
- [ ] `/gach-nha-tam?dcof=r10` → `robots="noindex, nofollow"`, `canonical="/gach-nha-tam"`.
- [ ] `/gach?cong-nang=lat-nen` → `301 Redirect` sang `/gach-lat-nen`.
- [ ] `/san-pham/{slug}` → chỉ có 1 `BreadcrumbList` duy nhất khớp `Primary Category`; bảng thông số có link `<a href>` về đúng các trang Collection (`IL-03`).
- [ ] `/llms.txt` và `/robots.txt` trả về `200 OK` đúng nội dung đặc tả.

