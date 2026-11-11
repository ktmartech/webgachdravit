# 15. Hợp đồng REST API, Store API & Kiến trúc JS Frontend

> **Vai trò tài liệu:** Hợp đồng tích hợp bắt buộc giữa Backend PHP 8.1+ (`Dravit\*`) và Frontend Vanilla ES2022 (`assets/src/js/*`) của dự án `gachdravit.vn`.
> **Ràng buộc kiến trúc:** SSR-first, không jQuery mới, không framework JS (React/Vue/Alpine), Light DOM Web Components (`<drv-*>`), Global Action Registry (`data-drv-action`), 1 lõi tính toán duy nhất (`utils/calc.js` ⇄ `Dravit\Units\Calc`), tương thích 100% với LiteSpeed Page Cache (`D-08`, `D-30`). Không dùng dấu em-dash trong chuỗi hiển thị UI.
> **Tham chiếu chéo:** [00_Project_Brief_Decisions.md](00_Project_Brief_Decisions.md) · [04_Pricing_Units_Commerce.md](04_Pricing_Units_Commerce.md) · [05_Faceted_Filter_AJAX.md](05_Faceted_Filter_AJAX.md) · [14_Data_Dictionary_And_Settings.md](14_Data_Dictionary_And_Settings.md)

---

## 1. Nguyên tắc Giao tiếp Server ↔ Client

### 1.1 SSR-First & Một Nguồn HTML Duy Nhất
1. **SSR-First:** Mọi trang danh mục, trạng thái lọc (`?param`), phân trang (`/page/n`), tìm kiếm (`/?s=...`) và nút *"Mua ngay"* đều hoạt động trọn vẹn bằng PHP khi tắt JavaScript.
2. **Single Source of Markup:** Các endpoint REST trả về mảnh giao diện (`items_html`, `facets_html`, `chips_html`) **bắt buộc** render bằng chính các file `template-parts/*` và hàm `drv_ui()` dùng trong SSR (`ob_start()` -> `drv_ui(...)` -> `ob_get_clean()`). Nghiêm cấm dựng lại HTML thẻ sản phẩm bằng template literal phía JS trên trang Danh mục.
3. **Header Chống Lập Chỉ Mục REST:** Filter `rest_post_dispatch` trong `Dravit\Core\RestSecurity` tự động gắn header `X-Robots-Tag: noindex, nofollow` cho mọi route bắt đầu bằng `/dravit/v1/`.

### 1.2 Chuẩn Mã Lỗi `WP_Error` Thống Nhất

Mọi lỗi trả về từ namespace `dravit/v1` tuân theo cấu trúc JSON chuẩn của WordPress REST API, bổ sung `data.fields` cho lỗi `422` để hàm form validator phía JS gắn `aria-invalid="true"` đúng ô nhập:

| HTTP Status | Mã `code` chuẩn | Tình huống áp dụng | Cấu trúc `data` bổ sung |
|---:|---|---|---|
| `400` | `drv_invalid_param` | Tham số query/body sai kiểu, vượt ngưỡng hoặc ngoài whitelist | `{ "status": 400, "param": "per_page" }` |
| `403` | `drv_bad_nonce` | Token nonce hết hạn hoặc không hợp lệ (client tự lấy lại nonce mới 1 lần) | `{ "status": 403, "retryable": true }` |
| `403` | `drv_bad_origin` | Header `Origin` / `Referer` khác domain `home_url()` | `{ "status": 403, "retryable": false }` |
| `404` | `drv_not_found` | Collection gốc không tồn tại hoặc tính năng đang tắt (`drv_feature_disabled`) | `{ "status": 404 }` |
| `422` | `drv_validation_failed` | Dữ liệu form khách nhập thiếu hoặc sai định dạng (SĐT, MST, BOQ rỗng...) | `{ "status": 422, "fields": { "phone": "Số điện thoại gồm 10 chữ số chuẩn VN." } }` |
| `429` | `drv_rate_limited` | Vượt giới hạn tần suất theo IP (kèm HTTP header `Retry-After: {seconds}`) | `{ "status": 429, "retry_after": 300 }` |

### 1.3 Chiến lược Nonce & Chống Spam Dưới LiteSpeed Page Cache (`D-30`)

Do LiteSpeed Page Cache lưu bản HTML tĩnh của trang chủ, danh mục, sản phẩm và cẩm nang từ `24 giờ` đến `7 ngày` (dài hơn vòng đời `12 - 24 giờ` của WordPress Nonce), **nghiêm cấm in cứng `wp_create_nonce()` vào HTML của các trang public**.

**Quy trình chuẩn 5 lớp bảo vệ cho mọi POST public (`/quote/submit`, `/lead`):**
1. **Lấy Nonce Tươi (`GET /wp-json/dravit/v1/nonce`):**
   - Gọi lazy khi người dùng `focusin` lần đầu vào bất kỳ form lead/BOQ nào, hoặc gọi ngay trước khi `fetch POST` nếu bộ nhớ đệm trên tab chưa có hoặc đã quá `600 giây`.
   - Gửi lên server qua HTTP header `X-DRV-Nonce: {token}`. Nếu nhận `403 drv_bad_nonce`, client xóa token trong RAM, gọi lại `/nonce` đúng 1 lần và gửi lại request.
2. **Kiểm tra `Origin` / `Referer`:** Server đối chiếu `parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST)` khớp tuyệt đối với host của `home_url()`.
3. **Honeypot (`drv_website`):** Trường `<input type="text" name="drv_website" tabindex="-1" autocomplete="off" aria-hidden="true">` ẩn bằng CSS ngoài màn hình. Nếu `drv_website !== ''`, server trả giả lập `200 OK` (không lưu DB, không gửi mail) để đánh lừa bot.
4. **Time-Trap (`elapsed_ms`):** Lúc form hiển thị hoặc nhận focus đầu tiên, JS ghi `form._startedAt = performance.now()` và gửi `meta.elapsed_ms = Math.round(performance.now() - form._startedAt)`. Server từ chối (`403`) nếu `elapsed_ms < 3000` (dưới 3 giây).
5. **IP Rate-Limit:** Băm địa chỉ IP bằng `hash_hmac('sha256', $ip, wp_salt('auth'))`, đếm qua transient `drv_rl_lead_{ip_hash}` (tối đa `drv_setting('quote.rate_limit_15m', 5)` lượt trong `900 giây`).

---

## 2. Chi tiết các Endpoint `dravit/v1`

### Tổng quan Bảng Định tuyến (`Dravit\*Controller`)

| Method | Endpoint | Controller PHP | Quyền | Cache Header / LiteSpeed |
|---|---|---|---|---|
| `GET` | `/wp-json/dravit/v1/catalog` | `Filters\CatalogController` | Public | `public, max-age=300, stale-while-revalidate=60` · Tag `drv_catalog` |
| `GET` | `/wp-json/dravit/v1/search` | `Core\SearchController` | Public | `public, max-age=300` · Transient 10 phút + Rate-limit 60/phút/IP |
| `GET` | `/wp-json/dravit/v1/products` | `Catalog\ProductsRestController` | Public | `public, max-age=300` · Tag `drv_catalog` |
| `GET` | `/wp-json/dravit/v1/nonce` | `Core\NonceController` | Public | `no-store, no-cache, must-revalidate, max-age=0` · LSCache `nocache` |
| `POST` | `/wp-json/dravit/v1/quote/submit` | `Quote\QuoteRestController` | Public + Nonce | `no-store` · Rate-limit `5 / 15 phút / IP` |
| `POST` | `/wp-json/dravit/v1/lead` | `Leads\LeadRestController` | Public + Nonce | `no-store` · Rate-limit `5 / 15 phút / IP` |
| `GET` | `/wp-content/themes/dravit/assets/data/wards/{code}.json` | File JSON tĩnh | Public | `public, max-age=31536000, immutable` (`?v={DRV_VERSION}`) |
| `POST` | `/wp-json/dravit/v1/advisor` *(Phase 3)* | `Advisor\AdvisorController` | Public + Nonce | Tắt ở Phase 1 (`404 drv_feature_disabled`) |

---

### 2.1 `GET /wp-json/dravit/v1/catalog`

Phục vụ lọc AJAX không tải lại trang, đếm trước số kết quả trên Mobile Bottom Sheet và nút *Xem thêm* (Load More).

#### Tham số Query (`args`)
| Param | Kiểu | Mặc định | Ràng buộc & Validate |
|---|---|---|---|
| `base` | `string` | *(bắt buộc)* | Định dạng `{taxonomy}:{slug}`, chỉ chấp nhận `product_cat:{slug}`, `pa_bo-suu-tap:{slug}`, hoặc `pa_xuat-xu:{slug}`. Trả `404` nếu term không tồn tại. |
| `{facet}` | `string` | `''` | Một hoặc nhiều key nằm trong `config/facets.php` (`cong-nang`, `khong-gian`, `van-gach`, `chat-lieu`, `kich-thuoc`, `bo-suu-tap`, `xuat-xu`, `mau-sac`, `be-mat`, `phong-cach`, `hinh-dang`, `dcof`, `do-hut-nuoc`, `do-day`, `gia`, `showroom`, `sort`). Đa giá trị phân cách `,`. Slug không hợp lệ bị lọc bỏ qua `UrlState`. |
| `page` | `int` | `1` | `max(1, absint($page))` |
| `per_page` | `int` | `24` | `min(48, max(1, absint($per_page)))` |
| `fragment` | `string` | `'grid'` | Enum `'grid'` (đầy đủ HTML lưới + facets + chips) \| `'items'` (chỉ `items_html` cho Load More) \| `'count'` (chỉ số lượng `total` + `facets` count cho Mobile Drawer, không render HTML card). |
| `include_items_meta` | `bool` | `false` | Khi `true`, trả thêm mảng `items[]` gọn phục vụ bắn sự kiện GA4 `view_item_list`. |

#### Ví dụ Request & Response (`200 OK` khi `fragment=grid`)
`GET /wp-json/dravit/v1/catalog?base=product_cat:gach-phong-khach&kich-thuoc=80x80&be-mat=bong-kieng,mo&fragment=grid`

```json
{
  "url": "/gach-phong-khach?kich-thuoc=80x80&be-mat=bong-kieng,mo",
  "redirect_to": null,
  "canonical": "https://gachdravit.vn/gach-phong-khach",
  "robots": "noindex, follow",
  "title": "Gạch Phòng Khách 80x80 | Gạch Dravit",
  "h1": "Gạch Lát Nền & Ốp Tường Phòng Khách Sang Trọng",
  "total": 36,
  "showing": 24,
  "page": 1,
  "per_page": 24,
  "max_pages": 2,
  "next_url": "/gach-phong-khach/page/2?kich-thuoc=80x80&be-mat=bong-kieng,mo",
  "active": [
    {
      "param": "kich-thuoc",
      "slug": "80x80",
      "label": "80x80 cm",
      "remove_url": "/gach-phong-khach?be-mat=bong-kieng,mo"
    },
    {
      "param": "be-mat",
      "slug": "bong-kieng",
      "label": "Bóng kiếng",
      "remove_url": "/gach-phong-khach?kich-thuoc=80x80&be-mat=mo"
    },
    {
      "param": "be-mat",
      "slug": "mo",
      "label": "Mờ (Matt)",
      "remove_url": "/gach-phong-khach?kich-thuoc=80x80&be-mat=bong-kieng"
    }
  ],
  "items_html": "<li class=\"drv-grid__item\"><article class=\"drv-card\" data-product-id=\"101\" ...>...</article></li>",
  "facets_html": "<div class=\"drv-filters\" data-facets-inner>...</div>",
  "chips_html": "<ul class=\"drv-active-chips\">...</ul>",
  "facets": {
    "kich-thuoc": {
      "flt": "FLT-04",
      "label": "Kích thước (cm)",
      "ui": "chips",
      "seo": "seo",
      "values": [
        {
          "slug": "80x80",
          "label": "80x80 cm",
          "count": 36,
          "selected": true,
          "disabled": false,
          "href": null
        },
        {
          "slug": "120x240",
          "label": "120x240 cm",
          "count": 0,
          "selected": false,
          "disabled": true,
          "href": null
        }
      ]
    },
    "mau-sac": {
      "flt": "FLT-06",
      "label": "Tông màu chủ đạo",
      "ui": "swatch",
      "seo": "seo",
      "values": [
        {
          "slug": "trang",
          "label": "Trắng / Trắng khói",
          "swatch": "#FBF9F5",
          "count": 14,
          "selected": false,
          "disabled": false,
          "href": null
        }
      ]
    }
  }
}
```

> **Quy tắc `redirect_to`:** Nếu khách đang ở `base=product_cat:gach` và chỉ chọn duy nhất 1 giá trị có trang Collection tĩnh (ví dụ `cong-nang=lat-nen`), server trả `redirect_to: "/gach-lat-nen"` để JS điều hướng sang URL tĩnh chuẩn SEO (`D-21`). Khi `fragment=items`, `facets_html`, `chips_html` và `facets` đặt là `null` để tiết kiệm băng thông. Khi `fragment=count`, `items_html`, `facets_html`, `chips_html` đều là `null`.

---

### 2.2 `GET /wp-json/dravit/v1/search`

Phục vụ hộp tìm kiếm tức thời 2 cột (Cột trái: Danh mục Silo gợi ý · Cột phải: Sản phẩm khớp mã SKU / tên không dấu).

#### Tham số Query
- `q` (`string`, mặc định `''`, cắt tối đa `80` ký tự).
- `limit` (`int`, mặc định `6`, tối đa `12`).

#### Hành vi Tìm kiếm Tiếng Việt Không Dấu
1. Khi `q === ''` hoặc dài `< 2` ký tự: không truy vấn DB sản phẩm; trả về `popular_keywords` và danh sách `silos` nổi bật từ `drv_settings.search` (kèm số mẫu từ `drv_term_stats_{id}`).
2. Khi `mb_strlen($q) >= 2`:
   - Chuẩn hóa `$q_ascii = Helpers::ascii_fold($q)` (bỏ dấu tiếng Việt, đổi `đ -> d`, thường hóa, gom khoảng trắng; đồng thời chuẩn hóa `800x800` -> `80x80`).
   - Tìm trong 28 `product_cat` có tên/slug chứa `$q_ascii` (tối đa 4 silos).
   - Tìm `product` (`post_status = 'publish'`, `drv_line = 'gach'`) theo thứ tự ưu tiên: (1) `_sku` bắt đầu bằng `$q` -> (2) `post_title` chứa cụm từ gốc -> (3) meta `_drv_search_index` chứa đủ tất cả token của `$q_ascii`.

#### Ví dụ Response (`200 OK`)
`GET /wp-json/dravit/v1/search?q=van%20da%2080x80&limit=6`

```json
{
  "query": "van da 80x80",
  "popular_keywords": [
    "Gạch 80x80 bóng kiếng",
    "Gạch giả gỗ 15x90",
    "Gạch nhà tắm R10"
  ],
  "silos": [
    {
      "id": 18,
      "slug": "gach-van-da",
      "name": "Gạch Vân Đá Marble",
      "url": "/gach-van-da",
      "group": "Vân & Chất liệu",
      "count": 68,
      "thumb": "https://gachdravit.vn/wp-content/uploads/2026/11/thumb-van-da-96x96.webp"
    },
    {
      "id": 24,
      "slug": "gach-80x80",
      "name": "Gạch 80x80",
      "url": "/gach-80x80",
      "group": "Kích thước",
      "count": 52,
      "thumb": "https://gachdravit.vn/wp-content/uploads/2026/11/thumb-80x80-96x96.webp"
    }
  ],
  "products": [
    {
      "id": 101,
      "sku": "DRV-8801M",
      "name": "Gạch Lát Nền Dravit Vân Đá Marble Calacatta 80x80 Bóng Kiếng",
      "url": "/san-pham/gach-lat-nen-dravit-van-da-marble-calacatta-80x80-drv8801m",
      "thumb": "https://gachdravit.vn/wp-content/uploads/2026/11/drv8801m-1-240x240.webp",
      "size": "80x80 cm",
      "surface_label": "Bóng kiếng",
      "price_state": "sale",
      "price_m2": 350000,
      "regular_price_m2": 385000,
      "price_piece": 224000,
      "unit_label": "viên",
      "stock_label": "Sẵn kho",
      "showroom_shelf": "A-08"
    }
  ],
  "total": 19,
  "view_all": "/?s=van+da+80x80&post_type=product"
}
```

---

### 2.3 `GET /wp-json/dravit/v1/products`

Cung cấp dữ liệu sản phẩm chuẩn xác từ DB để hydrate cho: (1) Modal chọn gạch trong trang BOQ (`/danh-sach-bao-gia`), (2) Ngăn kéo `<drv-quote-panel>`, (3) Khối *"Sản phẩm đã xem gần đây"* (`drv_viewed`) dưới chân PDP. Thay thế hoàn toàn các mảng JSON giả lập trong prototype.

#### Tham số Query
| Param | Kiểu | Mặc định | Mô tả |
|---|---|---|---|
| `include` | `string` | `''` | Danh sách `product_id` phân cách dấu phẩy (tối đa 30 ID), giữ nguyên thứ tự truyền vào (`orderby => 'post__in'`). |
| `skus` | `string` | `''` | Danh sách mã SKU phân cách dấu phẩy (tối đa 30 SKU, dùng khi migrate dữ liệu báo giá cũ). |
| `search` | `string` | `''` | Từ khóa tìm nhanh trong Modal chọn gạch BOQ (`≤ 60` ký tự, hỗ trợ không dấu). |
| `size` | `string` | `''` | Lọc theo slug `pa_kich-thuoc` (vd `80x80`, `60x120`). |
| `space` | `string` | `''` | Lọc theo slug `pa_khong-gian` (vd `phong-khach`, `nha-tam`). |
| `page` | `int` | `1` | Trang kết quả (`≥ 1`). |
| `per_page` | `int` | `12` | Số sản phẩm mỗi trang (`1..30`). |

#### Ví dụ Response (`200 OK`)
`GET /wp-json/dravit/v1/products?include=101,105`

```json
{
  "total": 2,
  "page": 1,
  "max_pages": 1,
  "items": [
    {
      "id": 101,
      "sku": "DRV-8801M",
      "name": "Gạch Lát Nền Dravit Vân Đá Marble Calacatta 80x80 Bóng Kiếng",
      "url": "/san-pham/gach-lat-nen-dravit-van-da-marble-calacatta-80x80-drv8801m",
      "thumb": "https://gachdravit.vn/wp-content/uploads/2026/11/drv8801m-1-240x240.webp",
      "color_name": "Trắng khói Calacatta",
      "swatch_hex": "#FBF9F5",
      "size": "80x80",
      "surface_label": "Bóng kiếng",
      "dcof": "R9",
      "price_state": "priced",
      "price_m2": 385000,
      "regular_price_m2": 385000,
      "price_piece": 246400,
      "piece_w_mm": 800,
      "piece_h_mm": 800,
      "area_piece": 0.64,
      "pieces_per_box": 3,
      "m2_per_box": 1.92,
      "kg_per_box": 42,
      "kg_per_piece": 14,
      "unit_label": "viên",
      "buy_units": ["m2", "thung"],
      "default_buy_unit": "m2",
      "round_mode": "box",
      "qty_min": 3,
      "qty_step": 3,
      "waste_rate": 5,
      "in_stock": true,
      "stock_pieces": 138,
      "stock_boxes": 46,
      "showroom_shelf": "A-08"
    },
    {
      "id": 105,
      "sku": "DRV-SLAB01",
      "name": "Đá Nung Kết Dravit Big Slab 120x240 Vân Đá Onyx Cao Cấp",
      "url": "/san-pham/da-nung-ket-dravit-big-slab-120x240-van-da-onyx-drvslab01",
      "thumb": "https://gachdravit.vn/wp-content/uploads/2026/11/drvslab01-1-240x240.webp",
      "color_name": "Kem ngọc Onyx",
      "swatch_hex": "#EDE6D8",
      "size": "120x240",
      "surface_label": "Bóng kiếng",
      "dcof": "R9",
      "price_state": "quote_only",
      "price_m2": 0,
      "regular_price_m2": 0,
      "price_piece": 0,
      "piece_w_mm": 1200,
      "piece_h_mm": 2400,
      "area_piece": 2.88,
      "pieces_per_box": 1,
      "m2_per_box": 2.88,
      "kg_per_box": 68,
      "kg_per_piece": 68,
      "unit_label": "tấm",
      "buy_units": ["m2", "vien"],
      "default_buy_unit": "vien",
      "round_mode": "piece",
      "qty_min": 1,
      "qty_step": 1,
      "waste_rate": 10,
      "in_stock": true,
      "stock_pieces": 24,
      "stock_boxes": 24,
      "showroom_shelf": "S-01"
    }
  ]
}
```

---

### 2.4 `GET /wp-json/dravit/v1/nonce`

Cấp phát bộ token tươi vượt qua LiteSpeed Page Cache (`D-30`). Trong controller bắt buộc gọi `do_action('litespeed_control_set_nocache', 'dravit fresh nonce')` và gắn header `Cache-Control: no-store, no-cache, must-revalidate, max-age=0`.

#### Response (`200 OK`)
```json
{
  "nonces": {
    "lead": "9f82c41a0b",
    "quote": "3d71e902ca",
    "wc_store_api": "74b1a09f2e"
  },
  "ts": 1762854000,
  "ttl": 600
}
```
- `lead`: verify bằng `wp_verify_nonce($token, 'drv_submit_lead')`.
- `quote`: verify bằng `wp_verify_nonce($token, 'drv_submit_quote')`.
- `wc_store_api`: sinh bằng `wp_create_nonce('wc_store_api')` để gửi kèm header `Nonce` khi gọi `POST /wp-json/wc/store/v1/cart/*` từ các trang HTML đang được cache.

---

### 2.5 `POST /wp-json/dravit/v1/quote/submit`

Tiếp nhận bảng dự toán công trình BOQ từ `/danh-sach-bao-gia` (`D-23`).

#### Headers bắt buộc
`Content-Type: application/json` · `X-DRV-Nonce: {nonces.quote}`

#### Request Payload JSON
```json
{
  "customer": {
    "name": "KTS. Trần Minh Hoàng",
    "phone": "0918668899",
    "email": "hoang.tran@nhaviet.vn",
    "province": "DNA",
    "ward": "Phường Long Thành",
    "role": "kts",
    "project": "Biệt thự song lập Long Thành",
    "message": "Cần giữ cùng lô cho phòng khách và sảnh tầng 1, giao hàng tuần tới.",
    "consent": true
  },
  "waste_pct": 5,
  "rooms": [
    {
      "id": "space-living",
      "name": "Phòng khách",
      "slots": [
        { "role": "Lát nền chính", "product_id": 101, "target_m2": 42 },
        { "role": "Ốp vách Tivi", "product_id": 105, "target_m2": 8.5 }
      ]
    }
  ],
  "meta": {
    "page_url": "https://gachdravit.vn/danh-sach-bao-gia",
    "utm": { "utm_source": "zalo", "utm_medium": "cpc" },
    "elapsed_ms": 18450,
    "drv_website": ""
  }
}
```

#### Quy tắc Xử lý & Tái tính toán trên Server (`Dravit\Quote\QuoteRestController`)
1. Chạy đủ 5 lớp bảo vệ chống spam ở §1.3. Kiểm tra `customer.consent === true`.
2. Đếm tổng số slot có `product_id > 0` và `target_m2 > 0`: phải từ `1` đến `drv_setting('quote.max_items', 30)` mục; nếu `0` mục trả `422` (`fields.rooms`).
3. Validate `waste_pct` thuộc `drv_setting('order.waste_options', [0, 5, 10])`.
4. Với từng slot, nạp `wc_get_product($product_id)` thật từ CSDL (bỏ qua mọi giá tiền nếu client tự gửi lên), gọi **`Dravit\Units\Calc::calcBoqSlot()`** tính lại số thùng chẵn (`boxesForArea`), số viên, diện tích thực nhận `actual_m2`, khối lượng `kg`, đơn giá và thành tiền (nếu `price_state === 'quote_only'` thì `line_total = 0` và bật cờ `has_quote_items = true`).
5. Tính bậc chiết khấu khối lượng dự kiến qua `Calc::calcVolumeDiscount($subtotal_vnd, $total_m2, $tiers)` (bao gồm cả m² của các dòng `quote_only` khi xét bậc diện tích).
6. Lưu bản ghi CPT private `drv_lead` (`_drv_lead_type = 'boq'`, mã `_drv_quote_code = 'BG-' . wp_date('Ymd') . '-' . wp_rand(1000, 9999)`), gửi email tới `quote.notify_emails` và email xác nhận cho khách (nếu có `customer.email`).

#### Response (`201 Created`)
```json
{
  "ok": true,
  "lead_id": 842,
  "quote_code": "BG-20261111-4821",
  "sla_text": "Phản hồi trong 30 phút (giờ làm việc 08:00 - 21:00)",
  "summary": {
    "room_count": 1,
    "item_count": 2,
    "total_m2": 53.76,
    "total_boxes": 26,
    "total_kg": 1196,
    "vehicle_hint": "Xe tải 2,5 tấn",
    "subtotal_vnd": 17325000,
    "discount_rate": 5,
    "discount_est_vnd": 866250,
    "grand_est_vnd": 16458750,
    "has_quote_items": true
  }
}
```

---

### 2.6 `POST /wp-json/dravit/v1/lead`

Endpoint thu nhận Lead hợp nhất cho toàn bộ 6 biểu mẫu tư vấn ngoài BOQ.

#### Headers bắt buộc
`Content-Type: application/json` · `X-DRV-Nonce: {nonces.lead}`

#### Request Payload JSON
```json
{
  "source": "quote_modal",
  "name": "Nguyễn Thanh Tùng",
  "phone": "0909385880",
  "email": "",
  "province": "HCM",
  "ward": "Phường Thảo Điền",
  "role": "chu-nha",
  "area_m2": 32.5,
  "product_id": 105,
  "post_id": 0,
  "project": "Nhà phố 3 tầng",
  "message": "Báo giá giúp tôi 32,5 m² đá nung kết DRV-SLAB01 giao Thảo Điền.",
  "consent": true,
  "meta": {
    "page_url": "https://gachdravit.vn/san-pham/da-nung-ket-dravit-big-slab-120x240-van-da-onyx-drvslab01",
    "utm": {},
    "elapsed_ms": 9200,
    "drv_website": ""
  }
}
```

#### Ma trận Trường Bắt buộc theo `source`
| `source` | Trường bắt buộc ngoài `name`, `phone`, `consent` |
|---|---|
| `quote_modal` | `province` (nếu mở trên PDP có sẵn `product_id`) |
| `contact` | `message` (`≥ 5` ký tự) |
| `sample` | `product_id`, `province`, `message` (địa chỉ nhận mẫu) |
| `showroom_visit` | *(không bắt buộc thêm; khuyến khích ngày/giờ hẹn trong `message`)* |
| `blog_inline` | `post_id` (tự điền ẩn từ bài viết) |
| `ai_handoff` *(Phase 3)* | Chỉ hoạt động khi `features.ai_enabled === true` |

#### Response Thành công (`201 Created`) & Lỗi Validate (`422 Unprocessable Entity`)
```json
// 201 Created
{
  "ok": true,
  "lead_id": 843,
  "lead_code": "LD-20261111-7190",
  "message": "Đã tiếp nhận yêu cầu! Chuyên viên Dravit sẽ liên hệ số 0909385880 trong 30 phút (giờ làm việc 08:00 - 21:00)."
}

// 422 Unprocessable Entity
{
  "code": "drv_validation_failed",
  "message": "Vui lòng kiểm tra lại thông tin liên hệ.",
  "data": {
    "status": 422,
    "fields": {
      "phone": "Số điện thoại không hợp lệ (gồm 10 chữ số chuẩn VN, ví dụ: 0909123456).",
      "consent": "Vui lòng xác nhận đồng ý để Dravit liên hệ tư vấn."
    }
  }
}
```

---

### 2.7 Static JSON Phường/Xã: `GET /wp-content/themes/dravit/assets/data/wards/{province_code}.json`

Sinh từ nguồn dữ liệu hành chính chính thức (`O-06`) qua script `tools/build-wards.mjs`. Chỉ tải lazy khi người dùng chọn Tỉnh/TP trên Checkout, BOQ hoặc form nhận mẫu.

```json
{
  "province_code": "HCM",
  "province_name": "TP. Hồ Chí Minh",
  "updated_at": "2025-07-01",
  "wards": [
    { "code": "HCM-001", "name": "Phường Sài Gòn", "type": "phuong" },
    { "code": "HCM-002", "name": "Phường Tân Định", "type": "phuong" },
    { "code": "HCM-088", "name": "Xã Bình Hưng", "type": "xa" }
  ]
}
```
> Trên Checkout, `<select id="billing_city">` dùng `ward.name` làm `value` (để địa chỉ chuẩn của WooCommerce lưu tên Phường/Xã đọc được ngay), đồng thời ghi `ward.code` vào `<input type="hidden" name="drv_ward_code">` để lưu meta `_drv_ward_code`.

---

### 2.8 Ghi chú Endpoint Trợ lý AI Phase 3 (`/advisor`, `/advisor/feedback`)
- Đường dẫn dự kiến: `POST /wp-json/dravit/v1/advisor` và `POST /wp-json/dravit/v1/advisor/feedback`.
- Ở Phase 1 (`drv_setting('features.ai_enabled') === false` theo `D-25`): không đăng ký JS widget ngoài frontend; nếu gọi trực tiếp vào route REST sẽ nhận `404` (`drv_feature_disabled`). Đặc tả chi tiết xem tại [13_AI_Advisor_MVP_Spec.md](13_AI_Advisor_MVP_Spec.md).

---

## 3. Tích hợp WooCommerce AJAX & Store API (`/wc/store/v1/cart`)

### 3.1 Thêm vào Giỏ (`add-to-cart`) & Nút "Mua ngay" (`D-30`)

1. **Giao thức Thêm vào Giỏ bằng AJAX (Card & PDP):**
   - Gọi `POST /wp-json/wc/store/v1/cart/add-item` kèm header `Nonce: {nonces.wc_store_api}` và `Content-Type: application/json`:
   ```json
   {
     "id": 101,
     "quantity": 48,
     "drv_cart_data": {
       "m2_input": 28.0,
       "waste_pct": 5,
       "input_unit": "m2"
     }
   }
   ```
   - **Quy tắc sống còn:** Trường `quantity` **bắt buộc là kiểu số nguyên `number` (đơn vị viên/tấm)**, tuyệt đối không gửi chuỗi định dạng như `"48 viên (16 thùng)"`.
   - Trên PHP, hook `woocommerce_store_api_add_to_cart_data` đọc mảng `drv_cart_data` trong request JSON, sanitize và ghi vào cart item session (`drv_m2_input`, `drv_waste_pct`, `drv_input_unit`).
2. **Form HTML Chuẩn trên PDP cho No-JS & Nút "Mua ngay" (`D-30`):**
   ```html
   <form class="drv-pdp-buy-form" method="post" action="/san-pham/gach-lat-nen-dravit-van-da-marble-calacatta-80x80-drv8801m">
     <input type="hidden" name="add-to-cart" value="101">
     <input type="hidden" name="quantity" value="48" data-drv-qty-pieces>
     <input type="hidden" name="drv_m2_input" value="28" data-drv-m2-input>
     <input type="hidden" name="drv_waste_pct" value="5" data-drv-waste-input>
     <input type="hidden" name="drv_input_unit" value="m2" data-drv-unit-input>

     <button type="submit" name="drv_buy_now" value="1" class="drv-btn drv-btn--primary">
       <span>Mua ngay</span>
     </button>
     <button type="submit" class="drv-btn drv-btn--moss"
             data-drv-action="add-to-cart" data-product-id="101" data-drv-sync-calc="true" data-drv-open-drawer="true">
       <i class="ph ph-shopping-cart-simple" aria-hidden="true"></i>
       <span>Thêm <strong data-sync-calc-pieces>48</strong> viên vào giỏ</span>
     </button>
   </form>
   ```
   - Nút **Thêm vào giỏ**: Khi có JS, `data-drv-action="add-to-cart"` chặn `submit` (`preventDefault`), đọc số nguyên từ `[data-drv-qty-pieces]`, gọi Store API `add-item` và mở `#drv-minicart-drawer`. Khi tắt JS, form submit POST chuẩn của WooCommerce.
   - Nút **Mua ngay** (`name="drv_buy_now" value="1"`): Không chặn bằng AJAX; submit POST thẳng lên server -> WC thêm vào giỏ -> filter `woocommerce_add_to_cart_redirect` kiểm tra `!empty($_POST['drv_buy_now'])` và trả về `wc_get_checkout_url()` (`/thanh-toan`).

### 3.2 Mở rộng Store API Schema (`Dravit\Woo\StoreApiExtend`)

Đăng ký qua `woocommerce_store_api_register_endpoint_data` với `namespace => 'dravit'` cho cả `CartItemSchema::IDENTIFIER` và `CartSchema::IDENTIFIER` để Mini-cart Drawer và trang Giỏ hàng có đủ dữ liệu quy đổi mà không phải gọi thêm endpoint phụ:

```json
// Trích xuất từ response của GET /wp-json/wc/store/v1/cart (hoặc POST add-item / update-item)
{
  "items_count": 48,
  "items": [
    {
      "key": "a87ff679a2f3e71d9181a67b7542122c",
      "id": 101,
      "quantity": 48,
      "quantity_limits": { "minimum": 3, "maximum": 138, "multiple_of": 3, "editable": true },
      "name": "Gạch Lát Nền Dravit Vân Đá Marble Calacatta 80x80 Bóng Kiếng",
      "sku": "DRV-8801M",
      "permalink": "https://gachdravit.vn/san-pham/gach-lat-nen-dravit-van-da-marble-calacatta-80x80-drv8801m",
      "totals": { "line_subtotal": "11827200", "line_total": "11827200", "currency_code": "VND" },
      "extensions": {
        "dravit": {
          "model": "area_pack",
          "price_state": "priced",
          "price_m2": 385000,
          "regular_price_m2": 385000,
          "price_piece": 246400,
          "unit_label": "viên",
          "area_piece": 0.64,
          "pieces_per_box": 3,
          "m2_per_box": 1.92,
          "kg_per_box": 42,
          "kg_per_piece": 14,
          "m2_qty": 30.72,
          "boxes": 16,
          "loose_pieces": 0,
          "box_text": "16 thùng",
          "kg": 672,
          "buy_units": ["m2", "thung"],
          "default_buy_unit": "m2",
          "round_mode": "box",
          "qty_min": 3,
          "qty_step": 3,
          "m2_input": 28,
          "waste_pct": 5
        }
      }
    }
  ],
  "totals": { "total_items": "11827200", "total_price": "11827200", "currency_code": "VND" },
  "extensions": {
    "dravit": {
      "line_count": 1,
      "total_pieces": 48,
      "total_m2": 30.72,
      "total_boxes": 16,
      "total_loose_pieces": 0,
      "total_kg": 672,
      "vehicle_hint": "Xe tải nhẹ 1 tấn",
      "has_loose_items": false,
      "shipping_notice": "Báo sau khi xác nhận đơn"
    }
  }
}
```

### 3.3 Chiến lược Cookie Hydration cho Mini-cart (Zero `wc-cart-fragments`)
1. `Woo\AssetsDequeue` gỡ bỏ hoàn toàn script `wc-cart-fragments` của WooCommerce trên toàn site.
2. Trong HTML SSR được cache bởi LiteSpeed, badge giỏ hàng `[data-drv-cart-count]` luôn render mặc định `0` với thuộc tính `hidden`.
3. Khi `core.js` khởi chạy, kiểm tra nhanh `/(?:^|;\s*)woocommerce_items_in_cart=([1-9]\d*)/.test(document.cookie)`:
   - **Không có cookie** (95% khách mới xem trang): **Không gửi bất kỳ request mạng nào**.
   - **Có cookie:** Gọi `GET /wp-json/wc/store/v1/cart` trong `requestIdleCallback`, cập nhật số dòng `items.length` (hoặc tổng thùng) lên `[data-drv-cart-count]`, bỏ `hidden` và lưu cache state trong bộ nhớ. Chỉ dựng DOM chi tiết các dòng sản phẩm khi người dùng mở `#drv-minicart-drawer` hoặc vừa bấm `add-to-cart`.

---

## 4. Lõi Tính toán Dùng chung (`assets/src/js/utils/calc.js` ⇄ `Dravit\Units\Calc`, `D-24`)

Cả Client (ES Module `assets/src/js/utils/calc.js`) và Server (`inc/Units/Calc.php`) **bắt buộc cài đặt cùng một tập hàm thuần khiết (pure functions)**, dùng cùng hằng số `EPSILON = 1e-9` để triệt tiêu lỗi làm tròn số thực nhị phân IEEE-754 (ví dụ `1.92 / 0.64 = 3.0000000000000004` không bao giờ bị `ceil` nhầm lên `4` viên).

### 4.1 Mã Tham chiếu Chuẩn (`assets/src/js/utils/calc.js`)

```js
export const EPSILON = 1e-9;
export const DEFAULT_WASTE_PCT = 5;

/** Diện tích 1 viên gạch tính theo m² */
export function pieceAreaM2(widthMm, heightMm) {
  return (Number(widthMm) * Number(heightMm)) / 1_000_000;
}

/** Giá 1 viên gạch (VND nguyên), khớp _regular_price / _sale_price của WC */
export function pricePerPiece(priceM2, areaPiece) {
  if (!(priceM2 > 0) || !(areaPiece > 0)) return 0;
  return Math.round(priceM2 * areaPiece);
}

/**
 * Chuẩn hóa số lượng viên theo cấu hình D-24 của từng sản phẩm:
 * - roundMode === 'box'  -> ép lên bội số chẵn thùng (piecesPerBox)
 * - roundMode === 'piece' -> giữ nguyên số nguyên viên (step = 1)
 * - Luôn >= qtyMin
 */
export function normalizePieces(rawPieces, { piecesPerBox, roundMode = 'box', qtyMin = 1 }) {
  const ppb = Math.max(1, Math.trunc(piecesPerBox));
  const minP = Math.max(1, Math.trunc(qtyMin));
  let pieces = Math.max(minP, Math.ceil(rawPieces - EPSILON));
  if (roundMode === 'box') {
    pieces = Math.ceil((pieces - EPSILON) / ppb) * ppb;
  }
  return pieces;
}

/** Tách tổng số viên thành thùng nguyên + viên lẻ + chuỗi hiển thị chuẩn VN */
export function boxBreakdown(pieces, piecesPerBox, unitLabel = 'viên') {
  const ppb = Math.max(1, Math.trunc(piecesPerBox));
  const fullBoxes = Math.floor(pieces / ppb);
  const loosePieces = pieces % ppb;
  let boxText = `${fullBoxes} thùng`;
  if (loosePieces > 0) {
    boxText = fullBoxes > 0
      ? `${fullBoxes} thùng + ${loosePieces} ${unitLabel} lẻ`
      : `${loosePieces} ${unitLabel} lẻ`;
  }
  return { fullBoxes, loosePieces, boxText };
}

/** Tổng hợp đầy đủ chỉ số đầu ra từ số viên nguyên đã chốt */
export function buildCalcResult({
  pieces, needM2, areaPiece, piecesPerBox, kgPerBox = 0, priceM2 = 0, unitLabel = 'viên',
}) {
  const pricePiece = pricePerPiece(priceM2, areaPiece);
  const actualM2 = Number((pieces * areaPiece).toFixed(4));
  const kgPerPiece = piecesPerBox > 0 ? kgPerBox / piecesPerBox : 0;
  const totalKg = Number((pieces * kgPerPiece).toFixed(1));
  const totalVnd = pricePiece > 0 ? pieces * pricePiece : 0;
  const { fullBoxes, loosePieces, boxText } = boxBreakdown(pieces, piecesPerBox, unitLabel);

  return {
    pieces,
    fullBoxes,
    loosePieces,
    boxText,
    needM2: Number(needM2.toFixed(4)),
    actualM2,
    totalKg,
    pricePiece,
    totalVnd,
    isQuote: !(priceM2 > 0),
  };
}

/** 1. Nhập theo diện tích m² (có cộng % hao hụt và tùy chọn làm tròn thùng) */
export function calcFromM2({
  areaM2, wastePct = 0, forceRoundBox = null,
  areaPiece, piecesPerBox, kgPerBox = 0, priceM2 = 0,
  roundMode = 'box', qtyMin = 1, unitLabel = 'viên',
}) {
  const netM2 = Math.max(0, Number(areaM2) || 0);
  const needM2 = netM2 * (1 + Math.max(0, Number(wastePct) || 0) / 100);
  const effectiveRoundMode = roundMode === 'box' || forceRoundBox === true ? 'box' : 'piece';
  const rawPieces = needM2 > 0 ? needM2 / areaPiece : qtyMin;
  const pieces = normalizePieces(rawPieces, { piecesPerBox, roundMode: effectiveRoundMode, qtyMin });

  return buildCalcResult({ pieces, needM2, areaPiece, piecesPerBox, kgPerBox, priceM2, unitLabel });
}

/** 2. Nhập trực tiếp theo số thùng nguyên */
export function calcFromBoxes({
  boxes, areaPiece, piecesPerBox, kgPerBox = 0, priceM2 = 0, qtyMin = 1, unitLabel = 'viên',
}) {
  const validBoxes = Math.max(1, Math.ceil((Number(boxes) || 1) - EPSILON));
  const pieces = normalizePieces(validBoxes * piecesPerBox, { piecesPerBox, roundMode: 'box', qtyMin });
  return buildCalcResult({
    pieces, needM2: pieces * areaPiece, areaPiece, piecesPerBox, kgPerBox, priceM2, unitLabel,
  });
}

/** 3. Nhập trực tiếp theo số viên/tấm */
export function calcFromPieces({
  pieces: inputPieces, areaPiece, piecesPerBox, kgPerBox = 0, priceM2 = 0,
  roundMode = 'piece', qtyMin = 1, unitLabel = 'viên',
}) {
  const pieces = normalizePieces(Number(inputPieces) || 1, { piecesPerBox, roundMode, qtyMin });
  return buildCalcResult({
    pieces, needM2: pieces * areaPiece, areaPiece, piecesPerBox, kgPerBox, priceM2, unitLabel,
  });
}

/** 4. Tính dòng Giỏ hàng / Đơn hàng từ số viên nguyên đã lưu trong WC */
export function calcCartLine({
  pieces, areaPiece, piecesPerBox, kgPerBox = 0, pricePiece = 0, priceM2 = 0, unitLabel = 'viên',
}) {
  const qty = Math.max(1, Math.trunc(Number(pieces) || 1));
  const unitVnd = pricePiece > 0 ? Math.round(pricePiece) : pricePerPiece(priceM2, areaPiece);
  const actualM2 = Number((qty * areaPiece).toFixed(4));
  const kgPerPiece = piecesPerBox > 0 ? kgPerBox / piecesPerBox : 0;
  const totalKg = Number((qty * kgPerPiece).toFixed(1));
  const { fullBoxes, loosePieces, boxText } = boxBreakdown(qty, piecesPerBox, unitLabel);
  const neededForNextBox = loosePieces > 0 ? piecesPerBox - loosePieces : 0;

  return {
    pieces: qty,
    fullBoxes,
    loosePieces,
    neededForNextBox,
    boxText,
    actualM2,
    totalKg,
    pricePiece: unitVnd,
    lineTotalVnd: qty * unitVnd,
  };
}

/** 5. Tính dòng hạng mục BOQ (0 m² -> 0 thùng; > 0 m² luôn quy chẵn thùng nguyên) */
export function calcBoqSlot({
  targetM2, wastePct = 5, areaPiece, piecesPerBox, kgPerBox = 0, priceM2 = 0, unitLabel = 'viên',
}) {
  const netM2 = Math.max(0, Number(targetM2) || 0);
  if (netM2 <= 0) {
    return { pieces: 0, fullBoxes: 0, loosePieces: 0, boxText: '0 thùng', needM2: 0, actualM2: 0, totalKg: 0, pricePiece: 0, totalVnd: 0, isQuote: !(priceM2 > 0) };
  }
  return calcFromM2({
    areaM2: netM2, wastePct, forceRoundBox: true,
    areaPiece, piecesPerBox, kgPerBox, priceM2, roundMode: 'box', qtyMin: piecesPerBox, unitLabel,
  });
}

/** 6. Tính chiết khấu khối lượng dự kiến trên BOQ (D-23) */
export function calcVolumeDiscount(subtotalVnd, totalM2, tiers = [{ min_m2: 100, rate: 8 }, { min_m2: 30, rate: 5 }, { min_m2: 0, rate: 0 }]) {
  const sorted = [...tiers].sort((a, b) => b.min_m2 - a.min_m2);
  const matched = sorted.find((t) => totalM2 + EPSILON >= t.min_m2) || { min_m2: 0, rate: 0 };
  const nextTier = [...sorted].reverse().find((t) => t.min_m2 > totalM2 + EPSILON) || null;
  const discountVnd = Math.round((subtotalVnd * matched.rate) / 100);
  return {
    rate: matched.rate,
    minM2: matched.min_m2,
    discountVnd,
    grandVnd: subtotalVnd - discountVnd,
    nextTier,
  };
}

/** 7. Gợi ý phương tiện vận chuyển theo tổng khối lượng kg */
export function resolveVehicleHint(totalKg, thresholds = []) {
  if (!(totalKg > 0)) return 'Chưa có khối lượng';
  for (const t of thresholds) {
    if (totalKg <= t.max_kg) return t.label;
  }
  return 'Xe tải 8 tấn / chia chuyến';
}
```

### 4.2 Vector Kiểm thử Đối chứng Bắt buộc (`PHPUnit` ⇄ `node --test`)

| Case | Đầu vào SKU | Thao tác | Kết quả bắt buộc khớp 100% ở cả PHP & JS |
|---|---|---|---|
| `T-01` | `800×800`, `3 v/thùng`, `42 kg/thùng`, `385.000đ/m²` | `calcFromM2(28, waste=5, box)` | `needM2 = 29.4`, `pieces = 48`, `fullBoxes = 16`, `actualM2 = 30.72`, `totalKg = 672`, `pricePiece = 246400`, `totalVnd = 11827200` |
| `T-02` | Như `T-01`, `roundMode = 'piece'` | `calcFromM2(28, waste=5, forceRoundBox=false)` | `pieces = 46`, `fullBoxes = 15`, `loosePieces = 1`, `actualM2 = 29.44`, `totalVnd = 11334400` |
| `T-03` | `800×800` (`areaPiece = 0.64`), `3 v/thùng` | `calcFromM2(1.92, waste=0, box)` *(bẫy IEEE-754)* | `1.92 / 0.64` ra đúng `pieces = 3` (`1 thùng`), **không** bị vọt lên `6 viên` |
| `T-04` | `75×300` (`areaPiece = 0.0225`), `44 v/thùng`, `450.000đ/m²` | `pricePerPiece` & `calcFromBoxes(2)` | `pricePiece = 10125`, `pieces = 88`, `actualM2 = 1.98`, `totalVnd = 891000` |
| `T-05` | `1200×2400`, `1 tấm/thùng`, `priceM2 = 0` (`quote_only`) | `calcFromM2(15, waste=10, piece)` | `needM2 = 16.5`, `pieces = 6` (`6 tấm`), `actualM2 = 17.28`, `isQuote = true`, `totalVnd = 0` |

---

## 5. Hợp đồng Light DOM Custom Elements (`<drv-*>`)

### Quy tắc Chung cho Toàn bộ `<drv-*>`
1. **Light DOM 100%:** Kế thừa `HTMLElement`, tuyệt đối không gọi `attachShadow()` để hưởng trọn hệ thống CSS `@layer` và hỗ trợ form submit chuẩn.
2. **Idempotent & Dọn dẹp Bộ nhớ:** `connectedCallback()` dùng cờ `this._mounted` kết hợp `this._abort = new AbortController()` truyền `{ signal: this._abort.signal }` vào mọi listener; hủy trong `disconnectedCallback()` để an toàn khi thẻ được di chuyển trong DOM (ví dụ `<drv-filter-form>`) hoặc chèn mới qua AJAX.
3. **Nghiêm cấm Fallback Dữ liệu Sản phẩm Cứng:** Nếu `<drv-unit-calc>` hoặc `<drv-qty-input>` thiếu các `data-*` kích thước/quy cách hợp lệ (`!(pieceW > 0 && pieceH > 0 && piecesPerBox >= 1)`), phải gọi `Dravit.reportError('unit-calc', ...)` và khóa các nút tính toán, **tuyệt đối không fallback sang thông số mẫu `DRV-8801M`**.

---

### 5.1 `<drv-unit-calc>` (Máy tính Đa Đơn vị trên PDP - `D-20`, `D-24`)

Chunk nạp: `assets/src/js/product.js` (import `components/unit-calc.js`).

#### Thuộc tính `data-*` bắt buộc PHP render trên thẻ `<drv-unit-calc>`
| Attribute | Kiểu / Giá trị mẫu | Nguồn PHP |
|---|---|---|
| `data-product-id` | `"101"` | `$product->get_id()` |
| `data-sku` | `"DRV-8801M"` | `$product->get_sku()` |
| `data-price-state` | `"priced"` \| `"sale"` \| `"quote_only"` \| `"out_of_stock"` | `PriceState::resolve($product)` |
| `data-price-m2` | `"385000"` (`"0"` nếu `quote_only`) | Giá hiệu lực / m² (nếu đang sale truyền `drv_sale_price_m2`, ngược lại `drv_price_m2`) |
| `data-piece-w` | `"800"` | `drv_piece_w_mm` (`> 0`) |
| `data-piece-h` | `"800"` | `drv_piece_h_mm` (`> 0`) |
| `data-pieces-per-box` | `"3"` | `drv_pieces_per_box` (`>= 1`) |
| `data-kg-per-box` | `"42"` | `drv_kg_per_box` (`>= 0`) |
| `data-unit-label` | `"viên"` \| `"tấm"` | `drv_unit_label` |
| `data-buy-units` | `'["m2","thung"]'` | JSON của `drv_buy_units` (`D-24`) |
| `data-default-unit` | `"m2"` | `drv_default_buy_unit` (`D-24`) |
| `data-round-mode` | `"box"` \| `"piece"` | `drv_round_mode` (`D-24`) |
| `data-qty-min` | `"3"` | `drv_qty_min` (viên) |
| `data-qty-step` | `"3"` | `drv_qty_step` (viên) |
| `data-waste-default` | `"5"` | `drv_waste_rate !== '' ? drv_waste_rate : drv_setting('order.waste_default', 5)` |

#### Các Hook Phần tử Con bên trong `<drv-unit-calc>`
| Selector con | Vai trò & Hành vi tương tác |
|---|---|
| `[data-calc-unit-tab="m2\|thung\|vien"]` | Nút chuyển chế độ nhập (`role="tab"`). PHP chỉ render các tab có mặt trong `drv_buy_units` (nếu mảng chỉ có 1 đơn vị thì ẩn thanh tab). |
| `[data-calc-unit-panel="m2\|thung\|vien"]` | Khung nhập tương ứng với từng tab (`hidden` khi không active). |
| `[data-calc-input="m2"]` | `<input type="number" step="0.5" min="0.5">` diện tích cần ốp lát. |
| `[data-calc-input="thung"]` | `<input type="number" step="1" min="1">` số thùng nguyên. |
| `[data-calc-input="vien"]` | `<input type="number" step="{qty_step}" min="{qty_min}">` số viên/tấm. |
| `[data-calc-step="-1\|1"][data-calc-target="m2\|thung\|vien"]` | Nút `-` / `+` tăng giảm giá trị của ô nhập đang mở. |
| `[data-calc-preset="15\|25\|40"]` | Chip chọn nhanh diện tích mẫu (chỉ hiển thị trong panel `m2`). |
| `[data-calc-waste]` | `<select>` hoặc radio chọn tỷ lệ hao hụt `0` / `5` / `10` (%) (chỉ áp dụng khi đang nhập ở tab `m2`). |
| `[data-calc-round-box]` | Checkbox *"Làm tròn chẵn thùng"*: nếu `data-round-mode="box"` thì khóa `checked + disabled` (kèm nhãn *"Xuất nguyên thùng"*); nếu `data-round-mode="piece"` thì cho phép khách bật/tắt tự do. |
| `[data-calc-out="summary"]` | Xuất chuỗi tổng hợp: `48 viên = 16 thùng (30,72 m² · 672 kg)`. |
| `[data-calc-out="boxes"]` | Xuất quy cách thùng: `16 thùng` (nổi bật cho cả `priced` và `quote_only`). |
| `[data-calc-out="actual-m2"]` | Xuất diện tích thực nhận: `30,72 m²`. |
| `[data-calc-out="kg"]` | Xuất tổng khối lượng: `672 kg` (ẩn nếu `kg_per_box == 0`). |
| `[data-calc-out="total-vnd"]` | Xuất thành tiền bằng hiệu ứng Odometer Anime.js (PHP không render phần tử này khi `price_state === 'quote_only'`). |
| `[data-calc-out="raw-need"]` | Dòng giải thích diện tích sau cộng hao hụt: `Đã gồm 5% hao hụt cắt gạch: 29,4 m²`. |

**Đồng bộ ra ngoài PDP:** Mỗi lần tính lại, cập nhật `.value` của `<input name="quantity" data-drv-qty-pieces>`, `[data-drv-m2-input]`, `[data-drv-waste-input]`, `[data-drv-unit-input]`, các nhãn `[data-sync-calc-pieces]`, `[data-sync-calc-total]`, `[data-sync-calc-sticky-meta]` trên thanh Sticky Purchase Bar, thuộc tính `data-drv-pieces` / `data-drv-area` trên nút `quote-add`, và phát sự kiện `drv:calc-change`.

---

### 5.2 `<drv-qty-input>` (Bộ Chỉnh Số lượng Giỏ hàng & Mini-cart - `D-24`)

Chunk nạp: `assets/src/js/cart.js` và lazy chunk `mini-cart.js`.

| Attribute trên `<drv-qty-input>` | Ý nghĩa & Hành vi |
|---|---|
| `data-cart-key` | Khóa dòng giỏ hàng `cart_item_key` của WooCommerce. |
| `data-product-id` | ID sản phẩm nguyên. |
| `data-model` | `'area_pack'` \| `'piece'` \| `'set'` \| `'pack'`. |
| `data-round-mode` | `'box'` (nút `+/-` tăng giảm theo `pieces_per_box`, tự làm tròn lên chẵn thùng khi blur) \| `'piece'` (nút `+/-` tăng giảm `1` viên). |
| `data-pieces-per-box` | Số viên/thùng (`>= 1`). |
| `data-area-piece` | Diện tích 1 viên (`m²`). |
| `data-kg-per-piece` | Khối lượng 1 viên (`kg`). |
| `data-price-unit` | Giá giao dịch 1 viên (`VND`). |
| `data-qty-min` / `data-qty-step` | Ràng buộc số viên tối thiểu & bước nhảy. |
| `data-max-stock` | Tồn kho tối đa (viên) nếu sản phẩm bật quản lý kho. |
| `data-unit-label` | `'viên'` \| `'tấm'`. |

**Hooks bên trong dòng `[data-cart-row]`:**
- `input[type="number"]`: Giá trị luôn là **số nguyên viên**.
- `[data-qty-step="-1\|1"]`: Nhân với `data-qty-step` của sản phẩm (`pieces_per_box` ở chế độ `box`, `1` ở chế độ `piece`).
- `[data-qty-add-box]`: Nút phụ `+1 thùng` (cộng thêm `pieces_per_box` viên).
- `[data-row-loose-warning]` & `[data-qty-round-box]`: Khi `round_mode === 'piece'` và `qty % pieces_per_box > 0`, hiện cảnh báo *"Đang lẻ {loose} viên - thêm {need} viên để chẵn {boxes + 1} thùng"* kèm nút bấm làm tròn 1 chạm.
- Khi giá trị đổi (debounce `400ms`), gọi `POST /wp-json/wc/store/v1/cart/update-item` (`{ key, quantity }`), cập nhật lại toàn bộ tổng `m² / thùng / kg / gợi ý xe` từ `extensions.dravit` của response và phát `drv:cart-updated`.

---

### 5.3 `<drv-filter-form>` (Bộ lọc 14 Facets Dùng Chung Desktop & Mobile)

Chunk nạp: `assets/src/js/catalog.js`.

1. **Chống nhân đôi `<form>` (khắc phục triệt để lỗi trùng `name` của prototype):**
   - Toàn trang Danh mục chỉ render **duy nhất 1 phần tử `<drv-filter-form>`** bọc quanh `<form id="drv-catalog-filter-form" method="get" action="{clean_base_url}">` đặt sẵn trong `<aside id="drv-filter-sidebar">`.
   - Khi màn hình `< 1024px` và người dùng mở `<dialog id="drv-filter-drawer">`, JS di chuyển nguyên vẹn node `<drv-filter-form>` vào vùng `.drv-dialog__body` của drawer; khi đóng drawer hoặc `matchMedia('(min-width: 1024px)')` khớp trở lại, trả node về `#drv-filter-sidebar`.
2. **Hành vi Desktop (`≥ 1024px`):**
   - Thay đổi bất kỳ input nào hoặc click `<a data-facet-link>` -> debounce `250ms` -> hủy request cũ bằng `AbortController` -> gọi `GET /wp-json/dravit/v1/catalog?...&fragment=grid`.
   - Cập nhật `#drv-product-grid` (`items_html`), `[data-facets-inner]` (`facets_html`), `#drv-active-chips` (`chips_html`), `[data-catalog-total]`, `<title>`, `<meta name="robots">`, `<link rel="canonical">` -> gọi `history.pushState({ drvCatalog: true }, '', res.url)` -> thông báo vào vùng `aria-live="polite"` (*"Đã tìm thấy {total} mẫu gạch"*).
3. **Hành vi Mobile (`< 1024px` trong `<dialog>`):**
   - Khi người dùng tick chọn nhiều tiêu chí trong drawer, chỉ gọi nhẹ `fragment=count` để cập nhật con số trên nút xác nhận `[data-filter-apply]` (*"Xem {total} sản phẩm"*). Chỉ khi bấm nút đó mới đóng `<dialog>` và tải `fragment=grid`.

---

### 5.4 `<drv-size-guide>` (Sơ đồ Tỷ lệ Khổ Gạch & Mạch Ron trên Trang chủ)

Chunk nạp: `assets/src/js/home.js`.
- Dữ liệu cấu hình các khổ chuẩn nhúng qua `<script type="application/json" data-sg-config>` bên trong thẻ (do PHP xuất để đồng bộ URL tĩnh).
- Hooks con: `[data-sg-size]` (chọn khổ `15x90`, `30x60`, `60x60`, `80x80`, `60x120`), `[data-sg-room]` (gợi ý theo diện tích phòng), `[data-sg-mode="scale\|grout"]`, `[data-sg-zone="all\|dry\|wet\|outdoor"]`, `[data-sg-ref]` (ẩn/hiện bóng người/đồ nội thất tham chiếu), `[data-sg-catalog]` (**bắt buộc cập nhật `href` về URL tĩnh** `/gach-80x80`, `/gach-60x120`, `/gach-60x60`, `/gach-30x60`, `/gach-15x80-20x100` theo `D-29`).

---

### 5.5 `<drv-showroom>` (Trải nghiệm Showroom & Thanh Kéo Nhiệt độ Màu 3000K / 6500K)

Chunk nạp: `assets/src/js/home.js` hoặc `contact.js`.
- **Quy tắc trung thực hình ảnh (`O-10`):** Mỗi nút ảnh nhỏ `.drv-sr__thumb` phải khai báo tách biệt `data-cool-src="{url_6500k}"` và `data-warm-src="{url_3000k}"`. Nếu mục đó chỉ có 1 ảnh đơn (`!thumb.dataset.warmSrc || thumb.dataset.warmSrc === thumb.dataset.coolSrc`), component tự động đặt `hidden` cho `[data-sr-warm]` và thanh kéo `[data-sr-divider]` (không bao giờ lấy cùng 1 bức ảnh phủ bộ lọc giả để so sánh đèn).
- `[data-sr-divider]`: Có `role="slider"`, `tabindex="0"`, `aria-label="Kéo so sánh ánh đèn vàng 3000K và trắng 6500K"`, `aria-valuemin="8"`, `aria-valuemax="92"`, `aria-valuenow="50"`, hỗ trợ kéo Pointer Events và phím `ArrowLeft` / `ArrowRight`.
- Nhãn trạng thái mở cửa `[data-sr-open-badge]` do **PHP tính sẵn theo `wp_date('G')` (`Asia/Ho_Chi_Minh`)** từ `drv_settings.contact.open_hour` / `close_hour`, không dùng giờ máy khách tránh lệch múi giờ.

---

### 5.6 `<drv-quote-panel>` (Drawer Xem nhanh Danh sách Báo giá BOQ)

Chunk nạp: `import('./components/quote-panel.js')` động ngay lần đầu người dùng bấm `quote-add` hoặc `quote-open`.
- Đặt bên trong `<dialog id="drv-quote-drawer" class="drv-drawer">`.
- Lắng nghe sự kiện `drv:quote-updated`, đọc danh sách `product_id` từ `localStorage['drv_quote_v2']`, gọi `GET /wp-json/dravit/v1/products?include=...` (có cache trong bộ nhớ theo ID) để hiển thị ảnh, tên, giá/m² (hoặc *Liên hệ báo giá*), tổng m²/thùng tạm tính và nút chuyển thẳng sang trang đầy đủ `/danh-sach-bao-gia`.

---

### 5.7 `<drv-toc>` (Mục lục Bài viết Cẩm nang Tự động)

Chunk nạp: `assets/src/js/blog.js` (tổng ngân sách JS trang Blog `≤ 10KB` gzip theo `D-28`).
- PHP (`Content\TableOfContents`) gắn `id` không dấu cho các thẻ `<h2>`, `<h3>` trong `the_content` và render sẵn danh sách `<a class="drv-toc__link" href="#{id}">` vào HTML SSR.
- `<drv-toc>` chỉ gắn `IntersectionObserver` (`rootMargin: '-15% 0px -70% 0px'`) để tô sáng mục đang đọc (`.is-active`) và cập nhật thanh tiến độ đọc `[data-toc-progress]` bằng `transform: scaleX(...)` (compositor-only, không gây reflow).

---

## 6. Registry `data-drv-action`, Custom Events, Client Storage & GA4

### 6.1 Global Action Registry (`Dravit.registerAction`)

Trong `assets/src/js/core.js`, đăng ký 1 listener `click` duy nhất trên `document`. Các module theo từng trang tự đăng ký handler vào bảng băm `Map` thay vì viết khối `switch-case` khổng lồ:

```js
// Khai báo trong core.js, export trên module utils/actions.js và gắn vào window.Dravit
Dravit.registerAction(name, async (actionEl, event) => { /* ... */ });
```

#### Bảng Chuẩn hóa Toàn bộ `data-drv-action` (Phase 1)
| Tên `data-drv-action` chuẩn | Module xử lý | Các `data-*` đi kèm trên nút bấm | Mô tả hành vi |
|---|---|---|---|
| `dialog-open` | `core.js` | `data-drv-target="#id"` | Mở `<dialog>` bằng `showModal()`, khóa cuộn nền, lưu phần tử kích hoạt để trả focus khi đóng |
| `dialog-close` | `core.js` | `data-drv-target="#id"` *(tùy chọn)* | Đóng `<dialog>` gần nhất (có chuyển tiếp CSS `.is-closing`) và trả focus |
| `mega-toggle` | `core.js` | `aria-controls="drv-mega-menu"` | Bật/tắt Mega Menu 4+1 trên Header |
| `search-toggle` | `core.js` | - | Mở thanh Live Search, tự động `import('./components/live-search.js')` lần đầu và focus ô nhập |
| `tab-switch` | `core.js` | `data-drv-group`, `data-drv-tab` | Chuyển tab WAI-ARIA chuẩn (hỗ trợ phím `ArrowLeft/Right`, `Home`, `End`) |
| `copy-text` | `core.js` | `data-drv-value`, `data-drv-feedback` | Sao chép mã SKU / số TK / số tiền / nội dung CK VietQR vào clipboard + hiện Toast |
| `chip-insert` | `core.js` | `data-drv-target`, `data-drv-text` | Bật/tắt cụm ghi chú giao hàng mẫu vào ô `#order_comments` trên Checkout |
| `scroll-carousel` | `core.js` | `data-drv-target`, `data-drv-dir="prev\|next"` | Cuộn ngang mượt dải `scroll-snap` / pills |
| `cart-drawer-open` | `core.js` | - | Nạp chunk `mini-cart.js` (nếu chưa nạp), đồng bộ Store API và mở `#drv-minicart-drawer` |
| `add-to-cart` | `core.js` -> `cart-client.js` | `data-product-id`, `data-drv-pieces` *(hoặc `data-drv-sync-calc="true"`)*, `data-drv-open-drawer` | Gửi số nguyên viên lên Store API `/wc/store/v1/cart/add-item`, rung badge giỏ, phát `drv:cart-updated` |
| `quote-add` | `core.js` -> `quote-store.js` | `data-product-id`, `data-sku`, `data-drv-area`, `data-drv-room`, `data-drv-role` | **Action duy nhất** thêm sản phẩm vào `localStorage.drv_quote_v2` (thay thế cả 4 tên rời rạc của prototype), cập nhật `[data-drv-quote-count]` và mở tùy chọn xem BOQ |
| `quote-open` | `core.js` | - | Mở ngăn kéo `<drv-quote-panel>` (`#drv-quote-drawer`) |
| `lead-open` | `core.js` | `data-lead-source`, `data-product-id`, `data-product-name`, `data-drv-area` | Mở `#drv-lead-modal`, tự động điền ngữ cảnh SP/diện tích và nạp trước nonce tươi |
| `lightbox-open` | `product.js` / `blog.js` | `data-pswp-index` | `import('photoswipe/lightbox')` động và mở PhotoSwipe 5 tại vị trí ảnh vừa bấm |
| `download-map` | `product.js` | `data-map-url`, `data-sku` | Tải gói file Map 3D / CAD thật từ `drv_map_file_id` và bắn GA4 event |
| `sidebar-toggle` | `catalog.js` | - | Thu gọn / mở rộng cột lọc 280px trên Desktop |
| `facets-collapse` | `catalog.js` | - | Thu gọn / mở toàn bộ các nhóm `<details>` trong bộ lọc |

> **Danh sách Action bị loại bỏ khỏi bản Production:** `wish-toggle` (bỏ Wishlist theo `D-26`), `checkout-demo-*`, `motion-replay-stagger`, `motion-demo-odometer`, `lightbox-thumb`/`lightbox-nav` tự chế (đã thay bằng PhotoSwipe 5 theo `D-28`), và các alias trùng `open-dialog` / `close-dialog` / `copy-sku` / `quote-add-item`.

---

### 6.2 Hợp đồng `data-*` trên Thẻ Sản phẩm (`.drv-card`)

Template `template-parts/ui/product-card.php` render cấu trúc thuộc tính thống nhất sau cho cả SSR và REST `/catalog`:

```html
<article class="drv-card drv-card--priced"
         data-product-id="101"
         data-sku="DRV-8801M"
         data-price-state="priced"
         data-price-m2="385000"
         data-default-pieces="3"
         data-default-m2="1.92">
  <!-- Nút hành động chính: đổi tự động theo PriceState::resolve($product) -->
  <!-- Khi priced / sale: -->
  <button type="button" class="drv-btn drv-btn--sm drv-btn--moss"
          data-drv-action="add-to-cart"
          data-product-id="101"
          data-drv-pieces="3"
          data-drv-open-drawer="true">
    <i class="ph ph-shopping-cart-simple" aria-hidden="true"></i>
    <span>Thêm giỏ</span>
  </button>

  <!-- Khi quote_only (hoặc out_of_stock): -->
  <button type="button" class="drv-btn drv-btn--sm drv-btn--outline"
          data-drv-action="quote-add"
          data-product-id="101"
          data-sku="DRV-8801M"
          data-drv-area="10">
    <i class="ph ph-file-text" aria-hidden="true"></i>
    <span>Báo giá</span>
  </button>
</article>
```
> Số viên mặc định khi bấm *Thêm giỏ* nhanh trên Card (`data-drv-pieces`) luôn bằng `max(drv_qty_min, drv_pieces_per_box)` (tương đương tối thiểu 1 thùng nguyên hoặc mức tối thiểu của SKU đó theo `D-24`).

---

### 6.3 Bảng Sự kiện Tùy chỉnh (`CustomEvent` trên `window` / Element)

| Tên sự kiện | Đối tượng phát | Cấu trúc `event.detail` | Nơi lắng nghe |
|---|---|---|---|
| `drv:calc-change` | `<drv-unit-calc>` (`bubbles: true`) | `{ productId, sku, unit, inputM2, wastePct, pieces, fullBoxes, loosePieces, actualM2, totalKg, totalVnd, isQuote }` | PDP Sticky Bar, Form hidden inputs, Analytics |
| `drv:cart-updated` | `window` | `{ cart, source: 'add' \| 'update' \| 'remove' \| 'hydrate' }` (`cart` là payload Store API) | Header `[data-drv-cart-count]`, Mini-cart Drawer, Trang Giỏ hàng, Analytics |
| `drv:quote-updated` | `window` | `{ state, itemCount, totalTargetM2, action: 'add' \| 'update' \| 'remove' \| 'clear' \| 'submit' }` | Header `[data-drv-quote-count]`, `<drv-quote-panel>`, Trang BOQ `/danh-sach-bao-gia` |
| `drv:filter-updated` | `window` | `{ url, base, active, total, page, fragment }` | Thanh Toolbar danh mục, Lưu cuộn `sessionStorage`, Analytics |
| `drv:lead-submitted` | `window` | `{ leadType, leadId, code, valueVnd, totalM2 }` | Đóng modal, xóa nháp BOQ (nếu chọn), bắn GA4 `generate_lead` |
| `drv:error` | `window` | `{ scope: string, message: string, context: object \| null, time: number }` | Ghi tối đa 50 lỗi gần nhất vào `window.__DRV_ERRORS__` phục vụ QA |

---

### 6.4 Hợp đồng Client Storage (`localStorage` & `sessionStorage`)

Mọi thao tác đọc/ghi `Storage` bắt buộc bọc trong `try / catch` (phòng chế độ ẩn danh Safari hoặc đầy bộ nhớ) qua helper `assets/src/js/utils/storage.js`.

#### 1. `localStorage['drv_quote_v2']` (Bản nháp Báo giá BOQ xuyên suốt phiên làm việc)
```json
{
  "version": 2,
  "updated_at": 1762854120000,
  "waste_pct": 5,
  "rooms": [
    {
      "id": "room-1",
      "name": "Phòng khách",
      "type": "fixed",
      "slots": [
        {
          "id": "slot-101",
          "role": "Lát nền chính",
          "product_id": 101,
          "sku": "DRV-8801M",
          "target_m2": 42
        }
      ]
    }
  ]
}
```
- **Quy tắc gộp khi bấm `quote-add` từ Card / PDP / Giỏ hàng:** Nếu khách không chỉ định phòng (`data-drv-room`), thêm vào phòng `"Danh sách chọn nhanh"` (tạo mới nếu chưa có); nếu cùng `product_id` đã tồn tại trong phòng đó thì cộng dồn `target_m2` (hoặc highlight dòng đó). Badge `[data-drv-quote-count]` trên Header đếm tổng số `slots` có `product_id > 0`.
- **Migration một chiều từ v1:** Khi khởi chạy `quote-store.js`, nếu chưa có `drv_quote_v2` nhưng phát hiện `localStorage['drv_quote_items_v1']`, tự động chuyển đổi sang phòng `"Danh sách chọn nhanh"` (dùng `/dravit/v1/products?skus=...` để lấy `product_id` nếu mục cũ chỉ lưu `sku`), ghi sang `drv_quote_v2` và `removeItem('drv_quote_items_v1')`.
- **TTL:** Tự bỏ qua và khởi tạo mới nếu `Date.now() - updated_at > 30 * 86400 * 1000` (30 ngày).

#### 2. `localStorage['drv_viewed']` (Sản phẩm Đã xem Gần đây)
- Giá trị: Mảng JSON tối đa **8 số nguyên `product_id`**, sản phẩm vừa xem đứng đầu, không trùng lặp: `[105, 101, 118, 94]`.
- Trên PDP, `product.js` ghi ID sản phẩm hiện tại vào đầu mảng sau khi trang tải xong, và dùng danh sách 7 ID còn lại để gọi `GET /wp-json/dravit/v1/products?include=...` khi khối *Đã xem gần đây* đi vào vùng nhìn (`IntersectionObserver`).

#### 3. `sessionStorage['drv_catalog_scroll:{pathname+search}']` (Khôi phục Load More khi bấm Back)
- Giá trị: `{ page: 3, nextUrl: "/gach-lat-nen/page/4", appendedHtml: "<li...>...</li>", scrollY: 1840, ts: 1762854000000 }`.
- TTL `15 phút`; xóa ngay khi nhận sự kiện `drv:filter-updated` đổi tiêu chí lọc mới.

---

### 6.5 Hợp đồng GA4 `window.dataLayer` Event Payloads

Tất cả sự kiện đẩy qua hàm duy nhất `Dravit.track(eventName, params)` trong `assets/src/js/utils/analytics.js` (`window.dataLayer = window.dataLayer || []; window.dataLayer.push(...)`). Với sản phẩm `quote_only`, trường `price` trong mảng `items` đặt là `0` và luôn đính kèm `price_state: 'quote_only'`.

| Tên Event (`event`) | Thời điểm kích hoạt | Payload chính trong `dataLayer.push({...})` |
|---|---|---|
| `view_item_list` | Tải trang Danh mục / kết quả lọc mới | `{ event: 'view_item_list', ecommerce: { item_list_id: 'gach-phong-khach', item_list_name: 'Gạch Phòng Khách', items: [{ item_id: 'DRV-8801M', item_name, price: 246400, price_m2: 385000, price_state: 'priced', index: 1 }] } }` |
| `select_item` | Click vào thẻ sản phẩm `.drv-card` | `{ event: 'select_item', ecommerce: { item_list_id, items: [{ item_id: 'DRV-8801M', item_name }] } }` |
| `view_item` | Tải trang Chi tiết sản phẩm (PDP) | `{ event: 'view_item', ecommerce: { currency: 'VND', value: 246400, items: [{ item_id: 'DRV-8801M', item_name, item_category: 'Gạch lát nền', price: 246400, price_m2: 385000, price_state: 'priced' }] } }` |
| `calculator_use` | Đổi giá trị `<drv-unit-calc>` (debounce `1500ms`) | `{ event: 'calculator_use', sku: 'DRV-8801M', input_unit: 'm2', actual_m2: 30.72, boxes_qty: 16, pieces_qty: 48 }` |
| `add_to_cart` | `POST /wc/store/v1/cart/add-item` thành công | `{ event: 'add_to_cart', ecommerce: { currency: 'VND', value: 11827200, items: [{ item_id: 'DRV-8801M', item_name, price: 246400, quantity: 48, m2_qty: 30.72, boxes_qty: 16 }] } }` |
| `remove_from_cart` | Xóa dòng sản phẩm khỏi Giỏ / Mini-cart | `{ event: 'remove_from_cart', ecommerce: { currency: 'VND', value: 11827200, items: [{ item_id: 'DRV-8801M', quantity: 48 }] } }` |
| `quote_add_item` | Bấm `data-drv-action="quote-add"` | `{ event: 'quote_add_item', sku: 'DRV-SLAB01', price_state: 'quote_only', target_m2: 15 }` |
| `begin_checkout` | Tải trang `/thanh-toan` | `{ event: 'begin_checkout', ecommerce: { currency: 'VND', value: 11827200, total_m2: 30.72, total_kg: 672, items: [...] } }` |
| `purchase` | Tải trang cảm ơn `order-received` lần đầu (chặn bắn lặp bằng meta/sessionStorage theo `order_id`) | `{ event: 'purchase', ecommerce: { transaction_id: 'DRV-1234', currency: 'VND', value: 11827200, payment_type: 'bacs', total_m2: 30.72, items: [...] } }` |
| `generate_lead` | Gửi thành công `/quote/submit` hoặc `/lead` | `{ event: 'generate_lead', lead_type: 'boq', lead_code: 'BG-20261111-4821', currency: 'VND', value: 16458750, total_m2: 53.76 }` |
| `filter_apply` | Lọc AJAX hoàn tất trên trang Danh mục | `{ event: 'filter_apply', collection: 'gach-phong-khach', active_facets: 'kich-thuoc=80x80&be-mat=mo', results_count: 36 }` |
| `load_more` | Tải thêm trang sản phẩm thành công | `{ event: 'load_more', collection: 'gach-phong-khach', page_loaded: 2 }` |
| `search` | Gõ Live Search dừng `800ms` hoặc submit trang tìm kiếm | `{ event: 'search', search_term: 'van da 80x80', results_count: 19 }` |
| `click_call` / `click_zalo` | Click vào bất kỳ link `tel:` hoặc `zalo.me` trên site | `{ event: 'click_call' \| 'click_zalo', placement: 'header' \| 'pdp' \| 'floating_dock' \| 'thankyou', page_path: location.pathname }` |
