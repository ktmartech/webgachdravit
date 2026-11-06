---
description: Quy trình chuẩn thiết kế, dựng giao diện và tối ưu tốc độ cho theme dravit (gachdravit.vn)
---

# Frontend Design & UX Workflow — DRAVIT (`gachdravit.vn`)

> **Docs bắt buộc đọc:** `docs/01_Brand_Design_System.md`, `docs/07_UI_UX_Page_Specs.md`, `docs/08_Performance_Hosting.md`
> **Rules:** `.agents/rules/dravit-frontend-ui.md`, `.agents/rules/dravit-coding-standards.md`
> **Skills:** `design-taste-frontend`, `frontend-design`, `ui-ux-designer`, `baseline-ui`, `fixing-accessibility`, `web-performance-optimization`

---

## BƯỚC 0: ĐỊNH HƯỚNG THẨM MỸ (Bắt buộc trước khi code)

**Design Read cố định cho dự án:**
> "Website TMĐT bán lẻ gạch ốp lát DRAVIT — Architectural Grout-Line Grid (Lưới kiến trúc đường ron 1px), ảnh vật liệu thật dẫn dắt, nền phẳng Ivory/Champagne, Navy `#083A4F` chủ đạo, Rêu `#3C7D7C` nhấn, hiển thị kép `đ/m²` & `đ/viên`."

**Cấm tuyệt đối (Anti-Slop):**
- ❌ Banner dọc bám 2 bên màn hình, đồng hồ Flash Sale giả, tem "GIẢM SỐC" răng cưa.
- ❌ Icon danh mục hình tròn (cắt góc viên gạch), carousel tự chạy, hiệu ứng fade/reveal khi cuộn trang.
- ❌ Cột "Sản phẩm đã xem" nhét trong Hero hoặc khung carousel khuyến mãi chiếm đầu trang danh mục.
- ❌ Hard-code mã màu HEX trong component, dùng `!important`, dùng em-dash `—` trên UI.

---

## BƯỚC 1: DESIGN TOKENS & TYPOGRAPHY

Mọi token khai báo tại `assets/src/tokens/tokens.json` và biên dịch ra `tokens.css` + `theme.json`:

```css
:root {
  /* Brand Colors */
  --c-navy-900: #083A4F;      /* Chữ chính, Header/Footer, Nút chính */
  --c-navy-800: #386172;      /* Chữ phụ, meta, SKU */
  --c-navy-600: #6A8994;      /* Viền đậm (CẤM dùng cho chữ) */
  --c-navy-400: #9CB0B9;      /* Viền input, disabled */
  --c-navy-200: #CDD8DB;      /* Đường kẻ ron 1px (--bw) */
  --c-moss-500: #3C7D7C;      /* Nền nút phụ, trạng thái active */
  --c-moss-700: #2F6664;      /* Link, chữ màu rêu trên nền sáng (WCAG AA 5.95:1) */
  --c-moss-100: #E3EEED;      /* Nền chip active, badge Showroom */
  --c-champagne: #E7E2CE;     /* Nền ảnh sản phẩm, section nhấn */
  --c-ivory: #F5F4F0;         /* Nền trang */
  --c-white: #FFFFFF;         /* Nền card, input, bảng */
  --c-sale: #B42318;          /* Giá KM, % giảm */
  --c-success: #1F7A4D;       /* Còn hàng */
  --c-warning: #B54708;       /* Lưu ý lô màu (shade) */

  /* Typography */
  --f-display: "Anton", "Arial Narrow", Impact, sans-serif;
  --f-body: "Quicksand", "Quicksand Fallback", system-ui, sans-serif;

  /* Shapes & Borders */
  --r-sm: 4px;                /* Nút, input, badge, tag kích thước */
  --r-md: 8px;                /* Card sản phẩm, khung ảnh, dialog */
  --r-pill: 999px;            /* Chip lọc */
  --bw: 1px solid var(--c-navy-200);
}
```

---

## BƯỚC 2: KIẾN TRÚC CSS/JS & ENQUEUE

1. **CSS Layers (`assets/src/css/main.css`):**
   ```css
   @layer reset, tokens, base, layout, components, woo, utilities;
   ```
   - Dùng Layout Primitives (`.l-container`, `.l-stack`, `.l-cluster`, `.l-grid`, `.l-grout-grid`) trong `@layer layout`.
   - Dùng biến cục bộ `--_*` (`--_bg`, `--_fg`, `--_h`) trong `@layer components` để các modifier chỉ đổi biến, không lặp thuộc tính CSS.
2. **PHP Component Helper & JS Custom Elements:**
   - Mọi UI tái sử dụng đặt trong `template-parts/ui/{name}.php` và gọi qua `drv_ui('product-card', [...])`.
   - Tương tác đơn giản dùng `data-drv-action="..."` (delegation trên `document`); component có state dùng Light DOM Custom Elements (`<drv-unit-calc>`, `<drv-qty-input>`, `<drv-filter-form>`).
3. **Chia nhỏ Asset theo Template (`Core\Assets`):**
   - Toàn cục: `main.css`, `core.js` (menu, instant search, mini-cart drawer).
   - Danh mục (`archive-product.php`): + `catalog.css`, `catalog.js` (filter AJAX, load more).
   - Sản phẩm (`single-product.php`): + `product.css`, `product.js` (gallery PhotoSwipe lazy, bộ tính `m² ⇄ viên`, sticky purchase bar).
   - Giỏ/Checkout: + `checkout.css`, `checkout-vn.js` (Tỉnh/TP → Phường/Xã).
   - Cẩm nang: + `blog.css`.
3. **Dequeue Bloat (`Woo\AssetsDequeue`):**
   - Gỡ `woocommerce-layout`, `woocommerce-smallscreen`, `woocommerce-general`, `wc-blocks-style`, `wp-block-library` (trên các trang WC).

---

## BƯỚC 3: DỰNG COMPONENT & LAYOUT CHUẨN (`docs/07_UI_UX_Page_Specs.md`)

1. **Header 2 tầng + Mega Menu "Silo Matrix 4+1":**
   - Tầng 1: Logo · Instant Search · Showroom (giờ mở cửa) · Hotline · Báo giá · Giỏ hàng.
   - Tầng 2 (ngăn bằng đường ron `1px`): 4 nhóm Silo + Đá nung kết + Gạch ưu đãi + Cẩm nang.
   - Mega Menu: 4 cột Silo (cột Kích thước có hình chữ nhật tỷ lệ) + 1 cột Featured Card nền `--c-champagne`.
2. **Card Sản phẩm (`template-parts/product/card.php`):**
   - Khung ảnh `1:1` nền `--c-champagne`, hover chuyển mượt sang ảnh thứ 2 (phối cảnh/cận vân).
   - Hàng meta ngay dưới ảnh: Tag vuông Kích thước (`80x80`) + Tag Bề mặt (`Bóng kiếng` / `Matt R10`) + mã SKU.
   - Tên sản phẩm: Quicksand 600, hiển thị **đủ 2 dòng** (`line-clamp: 2`).
   - Chân card: Giá chính **`385.000đ/m²`** + Giá phụ **`≈ 246.400đ/viên`** + nút vuông `36×36px` dẫn vào tính số lượng.
3. **Trang Chi tiết Sản phẩm (`woocommerce/content-single-product.php`):**
   - Cột trái (7/12): **Bento Gallery** (1 ảnh chính lớn + lưới 2 cột cận vân/phối cảnh) + nút `[↓ Tải Map gạch (CAD/3D)]` + PhotoSwipe 5 khi click. Mobile tự chuyển thành slider `scroll-snap` 1:1.
   - Cột phải (5/12, sticky): Giá kép + Swatch kích thước/bề mặt (`<a href>` sang SKU cùng `drv_series`) + **Bộ chuyển đổi trực tiếp `m² ⇄ Viên`** (có checkbox *Cộng 5% hao hụt*, *Làm tròn chẵn thùng*, *Tổng tạm tính* cập nhật tức thì) + Cụm nút `[Đặt mua ngay]` / `[+ Thêm vào giỏ]` + link mở Dialog Báo giá công trình + Bảng thông số kỹ thuật có link `IL-03`.
   - Cuộn trang: hiện **Sticky Purchase Bar** ở đáy màn hình.
4. **Bài viết Cẩm nang (`single.php`):**
   - Đầu bài: Hộp **Những điểm chính (Key Takeaways)** nền `--c-champagne` viền trái `3px solid var(--c-navy-900)` + Mục lục `<details open>`.
   - Dưới mỗi ảnh phối cảnh: thanh **Shop the Look Bar** dẫn thẳng tới mã gạch trong ảnh.

---

## BƯỚC 4: ACCESSIBILITY & PERFORMANCE CHECKLIST

- [ ] `<html lang="vi">`, có skip-link *"Chuyển đến nội dung chính"*, đầy đủ `<header>`, `<nav>`, `<main>`, `<footer>`.
- [ ] Mọi control có `<label>` hoặc `aria-label`; vùng kết quả lọc và tổng tiền bộ tính gạch có `aria-live="polite"`.
- [ ] Touch target trên mobile `≥ 44×44px`; focus ring `2px solid var(--c-moss-500)` rõ ràng.
- [ ] Ảnh LCP có `loading="eager" fetchpriority="high"`, các ảnh khác `loading="lazy"`; mọi `<img>` có `width` và `height`.
- [ ] Font `quicksand-vf-vi.woff2` được preload; tôn trọng `@media (prefers-reduced-motion: reduce)`.
- [ ] Không cuộn ngang ở `390px`, `768px`, `1024px`, `1440px`; PageSpeed Insights Mobile **≥ 90**.

