# Dravit Faceted Filter, SEO & GEO Rules

> Áp dụng cho hệ thống lọc danh mục, định tuyến URL, cấu hình Rank Math, Schema JSON-LD, liên kết nội bộ và tối ưu AI (GEO).
> Tham chiếu bắt buộc: `docs/05_Faceted_Filter_AJAX.md`, `docs/06_SEO_GEO_Spec.md`, `docs/branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.md`.

---

## 1. Hệ thống 14 Bộ lọc AJAX + Load More (`Filters`)

- **Nguyên tắc SSR-First:** Mọi trạng thái lọc phải có URL thật và render đầy đủ bằng PHP khi truy cập trực tiếp. AJAX (`GET /wp-json/dravit/v1/catalog`) chỉ thay thế vùng HTML (dùng chung template part với SSR) và cập nhật URL qua `history.pushState()`.
- **Danh sách tham số hợp lệ duy nhất (`config/facets.php`):**
  - Nhóm `seo` (FLT-01..09): `cong-nang`, `khong-gian`, `van-gach`, `chat-lieu`, `kich-thuoc`, `bo-suu-tap`, `xuat-xu`, `mau-sac`, `be-mat`, `phong-cach`, `hinh-dang`.
  - Nhóm `tech` (FLT-10..14): `dcof`, `do-hut-nuoc`, `do-day`, `gia`, `sort`, `showroom`.
- **Quy tắc sinh thẻ HTML chống Spider Trap:**
  - Khi đang ở **URL tĩnh sạch (0 tham số)**: các giá trị thuộc nhóm `seo` được render dạng `<a href>` (nếu ở `/gach` và giá trị có trang Collection riêng thì trỏ thẳng về URL tĩnh `/{slug}`; ngược lại trỏ về `?{param}={slug}`).
  - Khi URL **đã có bất kỳ tham số lọc nào**: toàn bộ lựa chọn lọc chỉ render bằng `<input type="checkbox">` / `<button>` (không sinh `<a href>` tổ hợp nhiều tham số).
  - Nhóm `tech` (FLT-10..14): **LUÔN LUÔN** render bằng `<button>` / `<input>` / `<select>`, **CẤM** dùng `<a href>`.
- **Load More:** Nút Load More trên HTML là thẻ `<a rel="next" href=".../page/2?...">Xem thêm 24 sản phẩm</a>` để bot cào được; JS chặn sự kiện click để tải nối tiếp (`fragment=items`) và lưu vị trí cuộn vào `sessionStorage`.

---

## 2. Ma trận Robots, Canonical & Chuẩn hóa URL (`Seo`)

Can thiệp qua `rank_math/frontend/robots` và `rank_math/frontend/canonical`:

| Trạng thái URL | Meta Robots | Thẻ Canonical | JSON-LD `ItemList` |
|---|---|---|---|
| URL tĩnh (`/gach`, 27 collection, `/bo-suu-tap/*`, `/xuat-xu/*`), không param | `index, follow` | Tự trỏ về chính nó (kể cả `/page/n`) | Có (trang 1) |
| Có tham số chỉ thuộc nhóm `seo` (FLT-01..09) | `noindex, follow` | Trỏ về URL danh mục tĩnh gốc (bỏ query & `/page/n`) | **Ẩn** |
| Có ít nhất 1 tham số thuộc nhóm `tech` (FLT-10..14) | `noindex, nofollow` | Trỏ về URL danh mục tĩnh gốc | **Ẩn** |
| Danh mục rỗng (0 sản phẩm) | `noindex, follow` | Tự trỏ về chính nó | **Ẩn** |
| REST API `/wp-json/dravit/v1/*` | Header `X-Robots-Tag: noindex, nofollow` | — | — |

- **Chuẩn hóa URL 301:** URL có tham số sai thứ tự `order`, giá trị không sắp xếp a–z, dư dấu `/`, chứa param lạ (trừ `utm_*`, `gclid`, `fbclid`), hoặc lọc đơn 1 giá trị trên `/gach` mà đã có URL tĩnh → bắt buộc `301 Redirect` về URL chuẩn.

---

## 3. Quy tắc Liên kết Nội bộ Tự động (`IL-01` đến `IL-06`)

- **`IL-01` & `IL-02`:** Đầu trang `/gach` và 27 collection phải có thanh **Visual Sub-Silo Tiles** (`<a href>` tĩnh) dẫn tới các collection con/liên quan + khối bài viết SEO Footer cuối trang.
- **`IL-03`:** Trong **Bảng Thông số Kỹ thuật** trên trang Chi tiết Sản phẩm, mọi giá trị thuộc tính có trang Collection tương ứng (Công năng, Không gian, Vân/Chất liệu, Kích thước, Bộ sưu tập, Xuất xứ) phải tự động render thành thẻ `<a href>` trỏ về URL tĩnh đó.
- **`IL-04`:** Trang sản phẩm chỉ xuất **1 đường dẫn Breadcrumb duy nhất** theo `rank_math_primary_product_cat`. **CẤM** gọi `woocommerce_breadcrumb()` gây trùng lặp với Rank Math.
- **`IL-05`:** Bài viết Cẩm nang (`/cam-nang/{slug}`) đặt link về Collection mục tiêu trong 150 chữ đầu (hộp Key Takeaways) và hỗ trợ shortcode `[drv_products]` kèm thanh **Shop the Look** dưới ảnh phối cảnh.
- **`IL-06` (Cô lập Silo):** Trong vùng `<main>` của nhánh Gạch, tuyệt đối không chèn link ngữ cảnh hoặc sản phẩm gợi ý thuộc ngành hàng khác.

---

## 4. Schema JSON-LD & GEO (Tối ưu cho AI đọc)

- **Schema bắt buộc (mở rộng trên graph của Rank Math qua `rank_math/json_ld`):**
  - Collection: `BreadcrumbList` + `CollectionPage` (`isPartOf` trỏ về `/gach#webpage`) + `ItemList` (24 SP đầu khi index) + `FAQPage` (**chỉ** lấy từ mảng `drv_faq` đang hiển thị thực tế trên trang).
  - Sản phẩm: `Product` với đầy đủ `sku`, `brand` (`"DRAVIT"`), `material`, `color`, `pattern`, kích thước/khối lượng, mảng `additionalProperty` và `Offer` chứa 2 `UnitPriceSpecification` (`C62` cho `viên` và `MTK` cho `m²`; ẩn `Offer` nếu SP thuộc diện Liên hệ báo giá `D-20`). **CẤM** nhồi mã lô vào `mpn`.
  - Cẩm nang: `BlogPosting` + `author` (`Person`) + `reviewedBy` trên `WebPage` + `FAQPage` (khi có FAQ hiển thị). **CẤM** xuất `HowTo` (đã deprecated) và **CẤM** chèn microdata `itemscope` trùng lặp với JSON-LD.
  - Liên hệ & Trang chủ: `ContactPage` + `HomeAndConstructionBusiness` lấy trực tiếp từ `drv_settings.showrooms`.
  - **CẤM** xuất `aggregateRating` hoặc `review` giả khi chưa có đánh giá thật.
- **Chuẩn GEO:**
  - Mọi thông số kỹ thuật, giá bán, tóm tắt nhanh (`<dl>`) và FAQ phải nằm trong HTML tĩnh từ server (`<table>`, `<dl>`, `<details>`), **CẤM** nhốt thông số quan trọng trong file ảnh hoặc chỉ render bằng JS.
  - Cấu hình module `llms.txt` của Rank Math xuất danh sách 28 Smart Collections, thông tin Showroom, chính sách giao hàng và bài viết Cẩm nang.

