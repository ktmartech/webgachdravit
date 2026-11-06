# 06. SEO & GEO

> Skill: `seo-fundamentals`, `seo-audit`, `schema-markup`, `programmatic-seo`, `seo-structure-architect`, `seo-meta-optimizer`, `fixing-metadata`, `analytics-tracking`.

## 1. Cấu hình đường dẫn

| Mục | Giá trị |
|---|---|
| Permalink bài viết | `/cam-nang/%postname%` (**không** trailing slash) |
| Category base (bài viết) | `chu-de` → `/chu-de/{slug}` |
| Tag base | `the` (noindex) |
| Product base | `/san-pham` → `/san-pham/{slug}` |
| Product category base | Rank Math bỏ base + bỏ parent slug → `/{slug}` |
| Attribute base | trống → `/bo-suu-tap/{slug}`, `/xuat-xu/{slug}` |
| Trang WC | Cửa hàng `san-pham` (Phase 1: **301 → `/gach`**), Giỏ `gio-hang`, Thanh toán `thanh-toan`, Tài khoản `tai-khoan`, Điều khoản `dieu-khoan` |
| Tìm kiếm | `/?s={q}&post_type=product`, `noindex, follow` |

## 2. Cấu hình Rank Math (checklist)

| Màn hình | Thiết lập |
|---|---|
| Dashboard → Modules | Bật: SEO Analysis, Sitemap, Schema, WooCommerce, Redirections, 404 Monitor, Instant Indexing (IndexNow), Image SEO, **LLMS Txt**, Robots Txt. Tắt: module không dùng |
| General → Links | Strip Category Base: **tắt** (dùng `chu-de`); Redirect Attachments: bật |
| General → Breadcrumbs | Bật; separator `/`; Home label "Trang chủ"; Hide taxonomy name; Show Category(s) cho sản phẩm (dùng primary term); Show Blog Page: tắt |
| General → WooCommerce | Remove base: **tắt** (D-14); Remove category base: **bật**; Remove parent slugs: **bật**; Remove generator: bật; Remove schema data on shop/category: **bật** (theme tự thêm ItemList, §4); Brand: Dravit (text) |
| General → robots.txt | Nội dung `05` §6.1 (không tạo file robots.txt vật lý) |
| General → LLMS Txt | Post types: page, post, product (giới hạn 100); Extra content: §7 |
| Titles & Meta → Global | Separator `|`; Noindex empty category & tag archives: **bật**; Noindex paginated pages: **tắt**; Noindex archive subpages: **tắt**; Noindex password protected: bật |
| Titles & Meta → Local SEO | Organization: "DRAVIT", logo PNG navy, URL, SĐT, sameAs (Facebook, Zalo OA, YouTube, Google Business Profile) |
| Titles & Meta → Products | Primary taxonomy: `product_cat`; schema: Product (WooCommerce) |
| Titles & Meta → Taxonomies | `product_cat`: index; `pa_bo-suu-tap`, `pa_xuat-xu`: index; mọi `pa_*` khác, `product_tag`, `post_tag`, `drv_*`: **noindex** + loại khỏi sitemap |
| Titles & Meta → Others | Author archives: tắt (hoặc noindex nếu 1 tác giả); Date archives: tắt |
| Sitemap | Gồm: page, post, product, `product_cat`, `pa_bo-suu-tap`, `pa_xuat-xu`. Loại: attachment, mọi taxonomy khác, `chua-phan-loai`. Ảnh trong sitemap: bật. Exclude empty terms: bật |

Filter bổ sung trong theme:
- `rank_math/frontend/breadcrumb/is_using_shop_base` → `false` (breadcrumb sản phẩm không chèn trang "Sản phẩm").
- `rank_math/frontend/robots`, `rank_math/frontend/canonical` → ma trận lọc (`05` §6).
- `rank_math/json_ld` → bổ sung schema (§4).
- `rank_math/sitemap/exclude_taxonomy`, `rank_math/llms_txt/terms` → như trên.

## 3. Meta template

| Loại trang | Title | Meta description |
|---|---|---|
| Home | `Gạch Ốp Lát Chính Hãng DRAVIT - Thẩm Mỹ Từ Những Đường Nét Nhỏ Nhất` | Viết tay, 140–160 ký tự, có showroom + giá/m² + giao hàng |
| Collection (28) | `meta_title` theo tài liệu Silo (seeder điền vào Rank Math term meta) | `meta_description` tài liệu Silo; nếu trống: `{H1}. {n}+ mẫu, kích thước {top sizes}, giá từ {min}đ/m². Có mẫu tại showroom Dravit.` |
| Sản phẩm | `{Tên SP} \| Gạch Dravit` (≤ 60 ký tự, cắt hậu tố nếu dài) | Nếu trống tự sinh: `{Tên} {kích thước}, bề mặt {be-mat}, {chat-lieu}, độ hút nước {x}%. Giá {price_m2}đ/m². Chính hãng Dravit.` |
| Bộ sưu tập / Xuất xứ | `Bộ Sưu Tập {name} \| Gạch Dravit` / `Gạch Nhập Khẩu {name} \| Gạch Dravit` | Tự sinh tương tự collection |
| Cẩm nang | `{Tiêu đề} \| Cẩm nang Dravit` | Viết tay |
| Trang tĩnh | `{Tiêu đề} \| DRAVIT` | Viết tay |

- Mỗi trang có **1 H1 duy nhất**. Collection dùng `drv_h1`; sản phẩm dùng tên SP.
- OG image: collection → `drv_hero_image`; sản phẩm → ảnh đại diện; mặc định → ảnh thương hiệu 1200×630.

## 4. Structured data (graph Rank Math + bổ sung)

| Trang | Thực thể |
|---|---|
| Mọi trang | `Organization` (logo, sameAs, contactPoint), `WebSite` + `SearchAction` (`/?s={search_term_string}&post_type=product`), `WebPage` |
| Home, Showroom | + `HomeAndConstructionBusiness` cho từng showroom (address, geo, openingHoursSpecification, telephone, priceRange) |
| Collection | `BreadcrumbList`, `CollectionPage` (con có `isPartOf` → `/gach#webpage`), `ItemList` (24 SP đầu, **chỉ** khi index), `FAQPage` (khi có FAQ hiển thị) |
| Sản phẩm | `BreadcrumbList` (theo primary), `Product` + `Offer` (bổ sung bên dưới) |
| Cẩm nang | `BlogPosting` + `author` (`Person` có trang tác giả/bio), `dateModified`, `image` |

**Product (bổ sung vào entity Rank Math qua `rank_math/json_ld`):**

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
  "color": "Trắng",
  "pattern": "Vân đá Marble",
  "image": ["https://gachdravit.vn/wp-content/uploads/.../drv8801m-1.webp"],
  "additionalProperty": [
    { "@type": "PropertyValue", "name": "Kích thước", "value": "80x80", "unitText": "cm" },
    { "@type": "PropertyValue", "name": "Độ dày", "value": 10, "unitCode": "MMT" },
    { "@type": "PropertyValue", "name": "Bề mặt", "value": "Bóng kiếng" },
    { "@type": "PropertyValue", "name": "Độ hút nước", "value": "≤ 0,5", "unitText": "%" },
    { "@type": "PropertyValue", "name": "Chống trượt", "value": "R9" },
    { "@type": "PropertyValue", "name": "Độ chịu mài mòn", "value": "PEI IV" },
    { "@type": "PropertyValue", "name": "Quy cách", "value": "3 viên/thùng (1,92 m²)" }
  ],
  "offers": {
    "@type": "Offer",
    "url": "https://gachdravit.vn/san-pham/gach-lat-nen-dravit-van-da-marble-calacatta-80x80-drv8801m",
    "priceCurrency": "VND",
    "price": 246400,
    "priceSpecification": [
      { "@type": "UnitPriceSpecification", "price": 246400, "priceCurrency": "VND",
        "referenceQuantity": { "@type": "QuantitativeValue", "value": 1, "unitCode": "C62", "unitText": "viên" } },
      { "@type": "UnitPriceSpecification", "price": 385000, "priceCurrency": "VND",
        "referenceQuantity": { "@type": "QuantitativeValue", "value": 1, "unitCode": "MTK", "unitText": "m²" } }
    ],
    "availability": "https://schema.org/InStock",
    "itemCondition": "https://schema.org/NewCondition",
    "seller": { "@id": "https://gachdravit.vn/#organization" }
  }
}
```

- `price` của Offer = giá/viên (đơn vị giao dịch, khớp giỏ hàng); giá/m² nằm trong `UnitPriceSpecification`.
- Không tạo `aggregateRating`/`review` khi chưa có đánh giá thật.
- Không xuất 2 BreadcrumbList: không dùng `woocommerce_breadcrumb()`, chỉ dùng Rank Math.
- Kiểm tra: Rich Results Test + Schema Markup Validator cho mỗi loại trang.

## 5. Internal linking (IL-01..06)

| Mã | Triển khai | Template part |
|---|---|---|
| IL-01 | `/gach`: Quick Category Pills (27 link `<a>`, chia 4 nhóm) đầu danh sách + SEO footer có link | `catalog/pills.php`, `catalog/seo-footer.php` |
| IL-02 | 27 collection: breadcrumb (Rank Math) + pills "Gợi ý theo nhu cầu" từ `drv_related` | `common/breadcrumb.php`, `catalog/pills.php` |
| IL-03 | Sản phẩm: bảng thông số, giá trị thuộc collection thành `<a>` (Công năng → `/gach-lat-nen`, Không gian, Vân, Kích thước); thuộc tính khác là text | `product/specs-table.php` |
| IL-04 | Sản phẩm: breadcrumb duy nhất theo primary category | Rank Math + `PrimaryCategory` |
| IL-05 | Cẩm nang: link collection trong 150 chữ đầu (biên tập); shortcode `[drv_products collection="gach-van-da" limit="8"]` → grid + CTA "Xem trọn bộ {n}+ mẫu {tên collection} chính hãng" | `blog/product-embed.php` |
| IL-06 | Silo isolation: trong `<main>` của nhánh gạch, sản phẩm liên quan/cross-sell chỉ lấy trong `line = gach`; link ngành khác chỉ ở mega menu và footer | `product/related.php` |

Sản phẩm liên quan: cùng `drv_series` (swatch) → cùng primary category + cùng kích thước → cùng vân. Tối đa 8.

## 6. Quy chuẩn nội dung

**Collection (tránh thin content, `programmatic-seo`):**
1. H1 + `drv_intro` ≤ 60 chữ.
2. **Khối "Tóm tắt nhanh"** sinh từ dữ liệu (`<dl>`): số mẫu, khoảng giá/m², kích thước phổ biến, bề mặt phổ biến, có mẫu showroom. Mỗi collection có số liệu riêng.
3. Pills → filter + grid → load more.
4. SEO footer 600–1.200 chữ **viết riêng cho từng collection**: tiêu chí chọn, bảng so sánh kích thước/bề mặt, ứng dụng, lưu ý thi công, link collection liên quan.
5. FAQ 4–6 câu, câu đầu tiên của mỗi câu trả lời trả lời thẳng.

**Sản phẩm:** mô tả ngắn 2–3 câu (nêu ứng dụng + điểm khác biệt), "Điểm nổi bật" 3–5 gạch đầu dòng, bảng thông số đầy đủ, "Phù hợp cho" (link collection không gian), lưu ý lô màu, hướng dẫn tính số lượng (máy tính), mô tả dài (tùy chọn).

**Ảnh:** tên file `gach-van-da-calacatta-80x80-drv8801m-1.webp`; alt mô tả thực tế ("Gạch vân đá Marble Calacatta 80x80 bóng kiếng DRV-8801M", "Phòng khách lát gạch vân đá 80x80 DRV-8801M"). Không nhồi từ khóa.

## 7. GEO: tối ưu cho AI đọc & trích dẫn

| Hạng mục | Yêu cầu |
|---|---|
| SSR | Mọi nội dung chính có trong HTML ban đầu (không phụ thuộc JS). Accordion dùng `<details>` |
| HTML ngữ nghĩa | `<header>`, `<nav aria-label>`, `<main>`, `<article>`, `<aside>`, `<footer>`; heading đúng cấp; bảng `<table>` + `<caption>` + `<th scope>`; dữ kiện `<dl>` |
| Dữ kiện trước | "Tóm tắt nhanh" đầu trang collection/sản phẩm; số liệu có đơn vị rõ (mm, %, đ/m²) |
| Nhất quán thực thể | Tên "DRAVIT", địa chỉ, SĐT giống nhau ở footer, schema, Google Business Profile, llms.txt |
| FAQ thật | Câu hỏi khách thật hỏi, trả lời trực tiếp, hiển thị trên trang |
| E-E-A-T | Cẩm nang có tác giả (bio, kinh nghiệm), ngày cập nhật, dẫn tiêu chuẩn (ISO 13006, TCVN 7745) khi nói thông số |
| llms.txt | Rank Math module. Extra content: giới thiệu DRAVIT (2–3 câu), danh sách 28 collection (tên + URL + 1 dòng mô tả), showroom (địa chỉ, giờ), chính sách giao hàng/thanh toán tóm tắt, liên hệ. Thêm term qua `rank_math/llms_txt/terms` |
| AI crawler | robots.txt `User-agent: *` Allow (GPTBot, Google-Extended, PerplexityBot, ClaudeBot không bị chặn). Nếu chủ dự án muốn chặn → ghi ADR |

## 8. Chỉ mục & giám sát

- Google Search Console: sitemap, Coverage (URL lọc phải nằm ở "Excluded by noindex"/"Blocked by robots.txt"), CWV, Enhancements (Product, Breadcrumb, FAQ).
- Bing Webmaster Tools + IndexNow (Rank Math Instant Indexing).
- Rank Math 404 Monitor + Redirections: 301 cho URL cũ/đổi slug.
- Kiểm tra định kỳ hàng tháng: crawl Screaming Frog (≤ 500 URL free) xác nhận không có URL `?param` bị index và không có link vỡ.

## 9. Đo lường (GA4)

- Tải GA4 bằng `gtag` async, `defer` sau tương tác đầu tiên hoặc `requestIdleCallback` (không chặn LCP/INP).
- Sự kiện: `view_item_list` (collection), `select_item`, `view_item`, `add_to_cart` (kèm `quantity` viên + `m2`), `begin_checkout`, `purchase` (trang cảm ơn), `generate_lead` (form báo giá), `click_call`, `click_zalo`, `filter_apply` (facet, value), `load_more`, `calculator_use`, `search`.
- Đẩy qua `window.dataLayer` từ 1 module `core.js` → dễ đổi công cụ sau này.

