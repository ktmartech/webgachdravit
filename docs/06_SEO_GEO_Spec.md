# 06. SEO & GEO

> Quyết định: D-02, D-14, D-20, D-21, D-25, D-27, D-29, D-30.
> Tài liệu liên quan: [05_Faceted_Filter_AJAX.md](05_Faceted_Filter_AJAX.md) (ma trận robots lọc), [14_Data_Dictionary_And_Settings.md](14_Data_Dictionary_And_Settings.md) (meta & `showrooms`), [15_REST_API_And_JS_Contract.md](15_REST_API_And_JS_Contract.md) (GA4 `dataLayer`), [20_Blog_Editorial_And_Content_Model.md](20_Blog_Editorial_And_Content_Model.md) (E-E-A-T Cẩm nang).
> Skill: `seo-fundamentals`, `seo-audit`, `schema-markup`, `programmatic-seo`, `seo-structure-architect`, `seo-meta-optimizer`, `fixing-metadata`, `analytics-tracking`.

## 1. Cấu hình đường dẫn (`D-14`, `D-21`, `D-29`)

**Quy tắc toàn cục (`D-21`):** Toàn bộ URL trên website **tuyệt đối không có dấu `/` ở cuối (zero trailing slash)** (ngoại trừ trang gốc `/`). Mọi truy cập có dấu `/` cuối đều được `301 Redirect` về URL chuẩn không dấu `/`.

| Hạng mục | Cấu trúc URL chuẩn | Chỉ mục (Robots) |
|---|---|---|
| Trang chủ | `/` | `index, follow` |
| 28 Smart Collections (`product_cat`) | `/gach`, `/gach-lat-nen`, `/da-nung-ket`... (Rank Math bỏ base + bỏ parent slug) | `index, follow` (0 param, `≥ 1` SP) |
| Lưu trữ Bộ sưu tập & Xuất xứ | `/bo-suu-tap/{slug}`, `/xuat-xu/{slug}` (`attribute_base` để trống) | `index, follow` (0 param, `≥ 1` SP) |
| Chi tiết sản phẩm | `/san-pham/{slug}` (`product_base = /san-pham`, `D-14`) | `index, follow` |
| Trang Cửa hàng gốc WC (`san-pham`) | `/san-pham` -> **301 Redirect sang `/gach`** ở Phase 1 | - |
| Cẩm nang (trang danh sách & bài viết) | `/cam-nang`, `/cam-nang/%postname%` | `index, follow` |
| Chuyên mục Cẩm nang (`category`) | `/chu-de/{slug}` (`category_base = chu-de`) | `index, follow` (`≥ 1` bài) |
| Thẻ bài viết (`post_tag`) | `/the/{slug}` (`tag_base = the`) | `noindex, follow` |
| Liên hệ & Hệ thống Showroom (`D-29`) | `/lien-he` (thiết lập **301 Redirect** từ `/showroom` -> `/lien-he`) | `index, follow` |
| Giới thiệu & Trang chính sách | `/gioi-thieu`, `/dieu-khoan`, `/chinh-sach-van-chuyen`, `/chinh-sach-doi-tra`, `/chinh-sach-thanh-toan`, `/chinh-sach-bao-mat` | `index, follow` |
| Danh sách Báo giá Công trình BOQ (`D-23`) | `/danh-sach-bao-gia` | `noindex, follow` (dữ liệu cá nhân hóa theo trình duyệt) |
| Trợ lý AI Tìm gạch (`D-25`) | `/tro-ly-ai-tim-gach` | `noindex, follow` ở Phase 1 (mở `index` ở Phase 3) |
| Giỏ hàng / Thanh toán / Tài khoản | `/gio-hang`, `/thanh-toan`, `/tai-khoan` | `noindex, nofollow` |
| Endpoint Đặt hàng thành công WC | `/thanh-toan/dat-hang-thanh-cong/{order_id}` (`order-received` = `dat-hang-thanh-cong`) | `noindex, nofollow` |
| Tìm kiếm nội bộ | `/?s={q}&post_type=product` | `noindex, follow` |
| Tham số tiện ích `?quote=`, `?ai_query=` | Bất kỳ URL kèm `?quote=...` hoặc `?ai_query=...` | `noindex, nofollow` |

## 2. Cấu hình Rank Math (Checklist)

| Màn hình | Thiết lập |
|---|---|
| Dashboard -> Modules | Bật: SEO Analysis, Sitemap, Schema, WooCommerce, Redirections, 404 Monitor, Instant Indexing (IndexNow), Image SEO, **LLMS Txt**, Robots Txt. Tắt toàn bộ module không dùng |
| General -> Links | Strip Category Base: **tắt** (giữ `/chu-de/{slug}`); Redirect Attachments: **bật** |
| General -> Breadcrumbs | Bật; separator `/`; Home label `"Trang chủ"`; Hide taxonomy name: bật; Show Category(s) cho sản phẩm (dùng primary term); Show Blog Page: can thiệp qua filter bên dưới để đồng bộ cả chuyên mục & bài viết |
| General -> WooCommerce | Remove base: **tắt** (`D-14`); Remove category base: **bật**; Remove parent slugs: **bật**; Remove generator: **bật**; Remove schema data on shop/category: **bật** (theme tự xuất `CollectionPage` + `ItemList`, §4); Brand: **`DRAVIT`** (Custom text) |
| General -> robots.txt | Nội dung theo `05` §6.1 (không tạo file `robots.txt` vật lý trên đĩa) |
| General -> LLMS Txt | Post types: `page`, `post`, `product` (giới hạn 100); Extra content: §7 |
| Titles & Meta -> Global | Separator `\|`; Noindex empty category & tag archives: **bật**; Noindex paginated pages: **tắt**; Noindex archive subpages: **tắt**; Noindex password protected: **bật** |
| Titles & Meta -> Local SEO | Organization: `"DRAVIT"`, logo thương hiệu, URL `https://gachdravit.vn`, SĐT chính thức (`O-03`), `sameAs` (Facebook, Zalo OA, YouTube, Google Business Profile - `O-14`) |
| Titles & Meta -> Products | Primary taxonomy: `product_cat`; Schema Type: `Product` (WooCommerce) |
| Titles & Meta -> Taxonomies | `product_cat`: `index`; `pa_bo-suu-tap`, `pa_xuat-xu`: `index`; mọi `pa_*` khác, `product_tag`, `post_tag`, `drv_*`: **`noindex`** + loại khỏi sitemap |
| Titles & Meta -> Others | Author archives: tắt (hoặc `noindex`); Date archives: **tắt** |
| Sitemap | Gồm: `page` (loại trừ `/danh-sach-bao-gia`, `/tro-ly-ai-tim-gach` ở Phase 1, `/gio-hang`, `/thanh-toan`, `/tai-khoan`), `post`, `product`, `product_cat` (loại `chua-phan-loai`), `pa_bo-suu-tap`, `pa_xuat-xu`. Loại: `attachment` và mọi taxonomy còn lại. Include Images: **bật**. Exclude Empty Terms: **bật** |

**Các Filter bắt buộc trong module `Dravit\Seo\*`:**
- `rank_math/frontend/breadcrumb/is_using_shop_base` -> `false` (breadcrumb sản phẩm hiển thị `Trang chủ / Gạch / {Primary Category} / {Tên SP}`, không chèn trang `/san-pham`).
- `rank_math/frontend/breadcrumb/items` -> trên bài viết đơn (`is_singular('post')`) và trang chuyên mục (`is_category()`), chèn cố định nút **`Cẩm nang` (`/cam-nang`)** ngay sau `Trang chủ` để luôn đạt cấu trúc chuẩn: `Trang chủ / Cẩm nang / {Chuyên mục} / {Tiêu đề bài viết}`.
- `rank_math/frontend/robots` & `rank_math/frontend/canonical` -> thực thi ma trận lọc (`05 §6`) và ép `noindex` cho `/danh-sach-bao-gia`, `/tro-ly-ai-tim-gach` (khi `features.ai_enabled = false`), `?quote=`, `?ai_query=`.
- `rank_math/json_ld` -> mở rộng và làm sạch đồ thị Schema (§4).
- `user_trailingslashit` & `rank_math/frontend/canonical` -> gỡ bỏ triệt để dấu `/` cuối URL (`D-21`).

## 3. Meta template & Giới hạn ký tự

**Quy tắc bắt buộc:** Thẻ `<title>` của **28 Collection** và **tất cả trang Sản phẩm** phải dài **tối đa 60 ký tự** để không bị Google cắt cụt trên SERP di động. Không dùng dấu gạch ngang dài em-dash (`U+2014`) trong `<title>` hoặc `meta description` (chỉ dùng `-`, `:` hoặc `|`).

| Loại trang | Mẫu `<title>` (`≤ 60` ký tự) | Mẫu `meta description` (`140–160` ký tự) |
|---|---|---|
| Trang chủ | `Gạch Ốp Lát Chính Hãng DRAVIT - Chuẩn Mực Từng Đường Nét` | Viết tay, nêu rõ gạch ốp lát chính hãng, giá niêm yết theo m², có mẫu thực tế tại showroom và giao hàng tận công trình |
| Collection (28) | `meta_title` từ tài liệu Silo (kiểm tra `mb_strlen ≤ 60`; nếu vượt ngưỡng thì rút hậu tố `\| Gạch Dravit` thành `\| DRAVIT` hoặc bỏ hậu tố) | `meta_description` từ tài liệu Silo; nếu trống tự sinh: `{H1}. {n}+ mẫu chọn lọc, kích thước {top sizes}, giá từ {price_min_m2}đ/m². Xem mẫu thực tế tại showroom DRAVIT.` |
| Sản phẩm (PDP) | `{Tên SP} \| Gạch Dravit` (**bắt buộc `≤ 60` ký tự**: nếu dài hơn 60 thì đổi sang `{Tên SP} \| DRAVIT`, nếu vẫn vượt thì giữ `{Tên SP}` cắt ở ranh giới từ) | Nếu trống tự sinh: `{Tên} kích thước {size}cm, bề mặt {be-mat}, xương {chat-lieu}, hút nước {x}%. {Nếu priced: "Giá {price_m2}đ/m²." \| Nếu quote: "Báo giá theo khối lượng công trình."} Chính hãng DRAVIT.` |
| Bộ sưu tập / Xuất xứ | `Bộ Sưu Tập {name} \| DRAVIT` / `Gạch {name} \| DRAVIT` (`≤ 60` ký tự) | Tự sinh tương tự Collection |
| Bài viết Cẩm nang | `{Tiêu đề bài viết} \| Cẩm nang DRAVIT` | Viết tay tóm tắt giải pháp & thông số cốt lõi |
| Trang tĩnh (`/lien-he`...) | `{Tiêu đề trang} \| DRAVIT` | Viết tay |

- Mỗi trang chỉ có **đúng 1 thẻ `<h1>`**. Trang Collection render từ `drv_h1`; trang Sản phẩm render tên sản phẩm; Cẩm nang render tiêu đề bài viết.
- Ảnh Open Graph (`og:image`): Collection -> `drv_hero_image` (fallback `drv_thumb_id`); Sản phẩm -> ảnh đại diện phối cảnh (`_thumbnail_id`); mặc định -> ảnh thương hiệu `1200×630`.

## 4. Structured Data (`rank_math/json_ld`)

Toàn bộ dữ liệu có cấu trúc tập trung duy nhất vào 1 khối `<script type="application/ld+json">` do Rank Math + `Seo\SchemaGraph` xuất ra. **Nghiêm cấm gắn inline microdata (`itemscope`, `itemtype`, `itemprop`) trong HTML template** để tránh sinh thực thể trùng lặp (như 2 `BreadcrumbList` hoặc 2 `Article`).

| Loại trang | Các thực thể trong `@graph` | Quy tắc ràng buộc |
|---|---|---|
| Mọi trang | `Organization` (`#organization`), `WebSite` + `SearchAction` (`/?s={search_term_string}&post_type=product`), `WebPage` | Đồng nhất tên `"DRAVIT"`, logo, SĐT và `sameAs` |
| Trang chủ (`/`) & Liên hệ (`/lien-he`) | + `ContactPage` (trên `/lien-he`) + danh sách `HomeAndConstructionBusiness` | Sinh động trực tiếp từ mảng `drv_settings.showrooms` (`name`, `address` hành chính 2 cấp mới, `geo` `latitude`/`longitude`, `openingHoursSpecification`, `telephone`, `hasMap`, `parentOrganization` -> `#organization`) |
| Collection (`/gach`, 27 con, `/bo-suu-tap/*`, `/xuat-xu/*`) | `BreadcrumbList`, `CollectionPage` (`isPartOf` -> `/gach#webpage`), `ItemList`, `FAQPage` | - `ItemList`: gồm đúng **24 sản phẩm đầu tiên**, **chỉ xuất ở trang 1 sạch (0 param)**.<br>- `FAQPage`: **chỉ serialize trực tiếp từ mảng `drv_faq` đang thực sự hiển thị trên HTML trang đó**; nếu không có khối FAQ hiển thị hoặc URL đang có `?param` thì tuyệt đối không xuất `FAQPage`. |
| Chi tiết sản phẩm (PDP) | `BreadcrumbList` (theo Primary Category), `Product` + `Offer` | Chi tiết chuẩn JSON-LD bên dưới |
| Bài viết Cẩm nang (`single.php`) | `BreadcrumbList`, `WebPage` (có `reviewedBy` + `lastReviewed`), `BlogPosting` + `author` (`Person`), `FAQPage` (tùy bài) | - Dùng đúng `@type: "BlogPosting"`.<br>- `author` và `reviewedBy` (`Person`) đọc từ hồ sơ nhân sự thật (`O-13`: `name`, `jobTitle`, `description`, `image`, `sameAs`), cấm dùng tên hư cấu.<br>- `FAQPage` chỉ xuất khi bài viết có block FAQ hiển thị thực tế.<br>- **Cấm xuất `HowTo`** (Google đã ngừng hỗ trợ). |

### 4.1 Cấu trúc `Product` JSON-LD chuẩn trên PDP

```json
{
  "@type": "Product",
  "@id": "https://gachdravit.vn/san-pham/gach-lat-nen-dravit-van-da-marble-calacatta-80x80-drv8801m#product",
  "name": "Gạch Lát Nền Dravit Vân Đá Marble Calacatta 80x80 Bóng Kiếng",
  "sku": "DRV-8801M",
  "gtin13": "8930000000000",
  "brand": { "@type": "Brand", "name": "DRAVIT" },
  "category": "Gạch lát nền",
  "material": "Porcelain",
  "color": "Trắng vân vàng Calacatta",
  "pattern": "Vân đá Marble",
  "width": { "@type": "QuantitativeValue", "value": 800, "unitCode": "MMT" },
  "height": { "@type": "QuantitativeValue", "value": 800, "unitCode": "MMT" },
  "depth": { "@type": "QuantitativeValue", "value": 10, "unitCode": "MMT" },
  "weight": { "@type": "QuantitativeValue", "value": 14, "unitCode": "KGM" },
  "image": ["https://gachdravit.vn/wp-content/uploads/.../drv8801m-1.webp"],
  "additionalProperty": [
    { "@type": "PropertyValue", "name": "Bộ sưu tập", "value": "Dravit Luxury" },
    { "@type": "PropertyValue", "name": "Kích thước", "value": "80x80", "unitText": "cm" },
    { "@type": "PropertyValue", "name": "Bề mặt", "value": "Bóng kiếng" },
    { "@type": "PropertyValue", "name": "Độ hút nước", "value": "≤ 0,5", "unitText": "%" },
    { "@type": "PropertyValue", "name": "Chống trượt", "value": "R9" },
    { "@type": "PropertyValue", "name": "Độ chịu mài mòn", "value": "PEI IV" },
    { "@type": "PropertyValue", "name": "Quy cách đóng gói", "value": "3 viên/thùng (1,92 m² · 42 kg)" }
  ],
  "offers": {
    "@type": "Offer",
    "url": "https://gachdravit.vn/san-pham/gach-lat-nen-dravit-van-da-marble-calacatta-80x80-drv8801m",
    "priceCurrency": "VND",
    "price": 246400,
    "priceValidUntil": "2027-12-31",
    "priceSpecification": [
      {
        "@type": "UnitPriceSpecification",
        "price": 246400,
        "priceCurrency": "VND",
        "referenceQuantity": { "@type": "QuantitativeValue", "value": 1, "unitCode": "C62", "unitText": "viên" }
      },
      {
        "@type": "UnitPriceSpecification",
        "price": 385000,
        "priceCurrency": "VND",
        "referenceQuantity": { "@type": "QuantitativeValue", "value": 1, "unitCode": "MTK", "unitText": "m²" }
      }
    ],
    "availability": "https://schema.org/InStock",
    "itemCondition": "https://schema.org/NewCondition",
    "seller": { "@id": "https://gachdravit.vn/#organization" }
  }
}
```

**Ràng buộc bắt buộc cho `Product` Schema:**
- `brand.name` luôn cố định là **`"DRAVIT"`**. Tên Bộ sưu tập (`pa_bo-suu-tap`) đặt vào mảng `additionalProperty`, không ghi đè vào `brand`.
- **Cấm tuyệt đối** đưa mã lô xuất kho (`_drv_lot_code`) hoặc mức biến thiên màu (`drv_shade` V1–V4) vào trường `mpn` (nếu khai báo `mpn` thì chỉ dùng mã `sku`, hoặc lược bỏ `mpn` khi đã có `sku`).
- Khai báo đầy đủ `width` (`drv_piece_w_mm`), `height` (`drv_piece_h_mm`), `depth` (`drv_thickness_mm`) với `unitCode: "MMT"`, và `weight` trên 1 viên (`round(drv_kg_per_box / drv_pieces_per_box, 2)`, `unitCode: "KGM"` khi `drv_kg_per_box > 0`).
- `offers.price` là đơn giá theo **viên** (`_price` giao dịch thực trong giỏ WC); đơn giá `/m²` khai báo trong `UnitPriceSpecification` thứ hai (`unitCode: "MTK"`).
- `priceValidUntil`: khi sản phẩm đang có lịch khuyến mãi (`_sale_price_dates_to`), xuất đúng ngày kết thúc KM dạng `YYYY-MM-DD`; nếu không có ngày kết thúc cụ thể thì điền ngày cuối năm dương lịch kế tiếp để tránh cảnh báo thiếu trường của Google Search Console.
- **Sản phẩm không niêm yết giá (`drv_price_m2` trống hoặc `≤ 0`, `D-20`):** Loại bỏ hoàn toàn thuộc tính `offers` khỏi thực thể `Product` (tuyệt đối không xuất `price: 0`).
- **Cấm** tạo `aggregateRating` hoặc `review` khi chưa có hệ thống đánh giá khách mua thật.
- Chỉ xuất **1 `BreadcrumbList` duy nhất** từ Rank Math (gỡ `woocommerce_breadcrumb()`).

## 5. Liên kết nội bộ tự động (`IL-01` đến `IL-06` - `D-29`)

**Nguyên tắc bất biến (`D-29`):** Tuyệt đối **0 đường dẫn chứa `?param`** trong Header, Mega Menu, Mobile Drawer, Footer và nội dung bài viết Cẩm nang. Toàn bộ liên kết điều hướng cấu trúc phải trỏ về URL tĩnh sạch không dấu `/` cuối.

| Mã | Quy tắc triển khai | Template part / Thành phần |
|---|---|---|
| **IL-01** | Trang gốc `/gach`: hiển thị đầy đủ Visual Sub-Silo Tiles dẫn tới 27 Collection con chia theo 4 nhóm ở đầu trang + bài viết SEO Footer cuối trang có link ngữ cảnh | `catalog/sub-silo-tiles.php`, `catalog/seo-footer.php` |
| **IL-02** | Trên 27 Collection: Breadcrumb (`Trang chủ / Gạch / {Collection}`) + dải **Visual Sub-Silo Tiles** (`drv_tile_items` hoặc `drv_related`). **Bắt buộc ưu tiên trỏ tới URL tĩnh của Collection** nếu đã có trang tĩnh trong 28 collections; chỉ fallback dùng link 1-param (`?{param}={slug}`, chỉ render khi đang ở URL sạch 0 param) cho quy cách ngách chưa có trang tĩnh riêng | `common/breadcrumb.php`, `catalog/sub-silo-tiles.php` |
| **IL-03** | Trên trang Sản phẩm (Bảng thông số kỹ thuật): chỉ render thẻ `<a href>` cho các giá trị có **trang lưu trữ tĩnh `index` được** gồm: Công năng (`/gach-lat-nen`...), Không gian (`/gach-phong-khach`...), Vân & Chất liệu có Collection (`/gach-van-da`, `/da-nung-ket`, `/gach-porcelain`...), Kích thước có Collection (`/gach-80x80`...), Bộ sưu tập (`/bo-suu-tap/{slug}`) và Xuất xứ (`/xuat-xu/{slug}`). Mọi thuộc tính còn lại (Bề mặt, Màu sắc, Chống trượt R, PEI, Độ hút nước, Độ dày, Shade) **render dạng text thuần**, cấm sinh link `?param` | `product/specs-table.php` |
| **IL-04** | Trên trang Sản phẩm: xuất duy nhất 1 đường dẫn Breadcrumb cố định theo `rank_math_primary_product_cat` (`Trang chủ / Gạch / {Primary Category} / {Tên SP}`) | Rank Math + `Catalog\PrimaryCategory` |
| **IL-05** | Trên bài viết Cẩm nang (`/cam-nang/{slug}`):<br>1. Đặt link ngữ cảnh về Collection tĩnh mục tiêu trong 150 chữ đầu (hoặc hộp *Tóm tắt nhanh / Key Takeaways*).<br>2. Nhúng lưới sản phẩm động bằng `[drv_products]` (đọc giá/tồn kho realtime, cấm viết cứng giá vào bài) kèm nút xem trọn bộ trỏ về URL tĩnh Collection.<br>3. Hỗ trợ `[drv_shop_the_look]` gắn các SKU thực tế ngay dưới ảnh phối cảnh.<br>4. Cuối bài hiển thị dải Silo tĩnh liên quan từ post meta `drv_related_collections` (chỉ link tới 28 URL tĩnh) | `blog/key-takeaways.php`, `blog/product-embed.php`, `blog/shop-the-look.php`, `blog/related-collections.php` |
| **IL-06** | **Cô lập Silo (Silo Isolation):** Trong vùng `<main>` của toàn bộ nhánh Gạch, sản phẩm tương tự / gợi ý chỉ lấy trong `drv_line = gach`. Liên kết sang ngành hàng khác (ở các phase sau) chỉ đặt tại Mega Menu và Footer | `product/related.php` |

Thứ tự ưu tiên lấy **Sản phẩm tương tự** trên PDP (tối đa 8 mẫu, loại trừ các SKU anh em đã có trên thanh swatch `drv_series`): cùng Primary Category + cùng Kích thước -> cùng Vân gạch -> cùng Không gian.

## 6. Quy chuẩn nội dung chống Thin Content

**Trang Collection (`programmatic-seo`):**
1. Thẻ `<h1>` (`drv_h1`) + đoạn dẫn `drv_intro` (`≤ 60` chữ, nhấn mạnh thông số bằng `<strong>`).
2. **Khối "Tóm tắt nhanh" (`<dl>`):** Tự sinh từ dữ liệu thật của danh mục: tổng số mẫu hiện có, giá tham khảo từ `{price_min_m2}đ/m²`, kích thước chủ đạo, bề mặt phổ biến, tình trạng mẫu trưng bày showroom (`has_showroom`).
3. Dải Sub-Silo Tiles -> Sticky Toolbar + Bộ lọc + Lưới sản phẩm -> Load More.
4. **Bảng thông số kỹ thuật chuẩn GEO (`drv_geo_table`, §7)** + bài viết hướng dẫn chọn gạch `drv_seo_footer` (`600–1.200` chữ viết riêng cho từng danh mục: tiêu chí kỹ thuật, độ chống trượt R khuyến nghị, lưu ý ron/keo, liên kết các danh mục liên quan).
5. **Khối Câu hỏi thường gặp (`drv_faq`, 4–6 câu):** Câu đầu tiên của mỗi phần trả lời đi thẳng vào số liệu/kết luận, render bằng `<details>` chuẩn SSR.

**Trang Sản phẩm:** Mô tả ngắn 2–3 câu nêu rõ ứng dụng thực tế và đặc điểm bề mặt/xương gạch; máy tính quy đổi `<drv-unit-calc>`; bảng thông số kỹ thuật đầy đủ (8 dòng chính + phần mở rộng); lưới các mặt vân (`drv_face_image_ids`); hộp lưu ý thi công & phối trộn thùng gạch (`<details>`, chèn động `drv_shade` và độ rộng ron `drv_grout_min_mm`–`drv_grout_max_mm`).

**Quy chuẩn đặt tên file & Alt ảnh:** Tên file ASCII không dấu dạng `gach-van-da-calacatta-80x80-drv8801m-1.webp`; thẻ `alt` mô tả đúng vai trò bức ảnh (`"Phối cảnh phòng khách lát gạch vân đá Calacatta 80x80 DRV-8801M"`, `"Bề mặt vân phẳng gạch bóng kiếng 80x80 DRV-8801M"`), không nhồi nhét từ khóa lặp lại.

## 7. GEO: Tối ưu cho công cụ AI đọc & trích dẫn

| Hạng mục | Quy chuẩn thực thi |
|---|---|
| **100% SSR cho dữ kiện cốt lõi** | Giá `đ/m²`, quy cách thùng/`kg`, thông số kỹ thuật, bảng GEO và câu hỏi FAQ đều có sẵn trong HTML tĩnh trả về từ PHP; không khóa nội dung trong ảnh chụp hay tab render bằng JS |
| **Bảng thông số GEO trên Collection (`drv_geo_table`)** | Render tại `template-parts/catalog/geo-specs.php` bằng thẻ `<table>` chuẩn ngữ nghĩa, bắt buộc có `<caption>` ghi rõ tên dòng gạch (vd `"Bảng tiêu chuẩn kỹ thuật & ứng dụng dòng Gạch Lát Nền Phòng Khách DRAVIT"`) và `<th scope="col">` / `<th scope="row">` rõ ràng để các mô hình LLM bóc tách chính xác từng tiêu chí |
| **HTML ngữ nghĩa & Dữ kiện đi trước** | Phân vùng `<header>`, `<nav aria-label>`, `<main>`, `<article>`, `<aside>`, `<footer>`; thứ bậc heading tuần tự (`h1 -> h2 -> h3`, cấm nhảy cóc từ `h1` xuống `h3`); hộp `<dl>` Tóm tắt nhanh ở đầu Collection & hộp Key Takeaways ở đầu bài Cẩm nang; đơn vị đo rõ ràng (`mm`, `%`, `N/mm²`, `đ/m²`) |
| **Nhất quán thực thể (Entity Consistency)** | Tên thương hiệu `"DRAVIT"`, pháp nhân (`O-09`), địa chỉ hành chính 2 cấp mới và SĐT trùng khớp tuyệt đối giữa Footer, trang `/lien-he`, Schema `Organization`/`HomeAndConstructionBusiness`, Google Business Profile và `/llms.txt` |
| **E-E-A-T thực chứng trên Cẩm nang** | Mỗi bài hướng dẫn kỹ thuật gắn hồ sơ tác giả thật và kỹ sư thẩm định thật (`O-13`), hiển thị ngày rà soát kỹ thuật (`drv_reviewed_at`) và trích dẫn đúng tiêu chuẩn quốc gia/quốc tế (`TCVN 7745`, `ISO 13006`, `ISO 10545`, `DIN 51130`) |
| **`/llms.txt`** | Kích hoạt qua Rank Math LLMS Txt + filter `rank_math/llms_txt/terms`: xuất đoạn giới thiệu ngắn DRAVIT (`drv_settings.seo.llms_intro`), danh sách đầy đủ 28 Smart Collections (Tên + URL không `/` cuối + mô tả tóm tắt), địa chỉ & giờ mở cửa Showroom/Kho, tóm tắt quy tắc bán theo `m²`/thùng & chính sách giao hàng báo phí sau, cùng các bài Cẩm nang trụ cột |
| **Quyền truy cập AI Crawler** | `robots.txt` mở `Allow: /` cho `GPTBot`, `OAI-SearchBot`, `Google-Extended`, `PerplexityBot`, `ClaudeBot` trên toàn bộ trang nội dung và danh mục tĩnh (chỉ chặn các đường dẫn giỏ hàng, thanh toán, báo giá cá nhân và tham số kỹ thuật tại `05 §6.1`) |

## 8. Chỉ mục & Giám sát định kỳ

- **Google Search Console & Bing Webmaster Tools:** Gửi `/sitemap_index.xml`, bật IndexNow (Rank Math Instant Indexing) tự ping khi thêm/cập nhật sản phẩm và bài viết.
- **Kiểm soát Coverage:** Theo dõi báo cáo Indexing đảm bảo 28 URL Collection nằm trong nhóm *Indexed*, toàn bộ URL lọc `?param` nằm đúng nhóm *Excluded by 'noindex' tag* hoặc *Blocked by robots.txt*.
- **Kiểm định định kỳ hàng tháng:** Quét toàn site bằng Screaming Frog xác nhận: `0` link nội bộ chứa `?param` trên Shell/Blog, `0` URL có dấu `/` cuối bị lọt, `1` `BreadcrumbList` duy nhất trên mọi trang, `FAQPage` khớp 100% nội dung hiển thị.

## 9. Đo lường chuyển đổi (GA4 `dataLayer`)

- Nạp thẻ `gtag.js` bất đồng bộ sau tương tác đầu tiên hoặc trong `requestIdleCallback` để không chiếm băng thông trước LCP và không gây nghẽn INP.
- Toàn bộ sự kiện đẩy qua `window.dataLayer` từ một helper trung tâm trong `assets/src/js/core.js` (chi tiết cấu trúc tham số từng sự kiện xem tại [15_REST_API_And_JS_Contract.md](15_REST_API_And_JS_Contract.md)):
  - **Thương mại điện tử chuẩn:** `view_item_list` (trang danh mục & kết quả lọc), `select_item`, `view_item` (PDP), `add_to_cart` (kèm `quantity` viên, `m2`, `boxes`), `begin_checkout`, `purchase` (trang `dat-hang-thanh-cong`).
  - **Báo giá BOQ & Thu nhận Lead:** `add_to_quote` (thêm mẫu vào DS báo giá từ Card, PDP hoặc Giỏ hàng), `quote_submit` (gửi phiếu BOQ `/danh-sach-bao-gia`, kèm `total_m2` và số lượng hạng mục), `generate_lead` (gửi form tư vấn nhanh / liên hệ).
  - **Công cụ chuyên môn & Tương tác nội dung:** `calculator_use` (đổi diện tích/đơn vị trên `<drv-unit-calc>`), `download_map` (KTS tải file Map vân/CAD `.zip` trên PDP, kèm `sku`), `copy_sku` (sao chép mã SKU hoặc số TK/nội dung chuyển khoản), `filter_apply` (kèm `facet`, `value`), `load_more`, `search`, `toc_click` (chuyển mục lục Cẩm nang), `share` (chia sẻ sản phẩm, bài viết hoặc phiếu BOQ), `click_call`, `click_zalo`.
