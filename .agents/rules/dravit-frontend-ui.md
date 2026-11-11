# Dravit Frontend, UI/UX & Brand Rules

> Áp dụng cho toàn bộ giao diện, CSS, JS và copy trên `gachdravit.vn`.
> Tham chiếu bắt buộc: `docs/01_Brand_Design_System.md`, `docs/07_UI_UX_Page_Specs.md`, `docs/08_Performance_Hosting.md`, `docs/10_Brand_Voice_And_Content_Guide.md`.

---

## 1. Nhận diện Thị giác: "Architectural Grout-Line Grid" (Lưới Đường Ron)

- **Motif cốt lõi:** Mọi khối thông tin, thẻ sản phẩm, bảng thông số và thanh điều hướng được phân tách bằng **đường kẻ ron `1px solid var(--c-navy-200)` thẳng tắp**, container chuẩn **`1380px`** (`D-28`), căn hàng tuyệt đối, bo góc nhỏ vuông vức (`--r-xs: 2px`, `--r-sm: 4px` cho nút/input/badge vuông/nav, `--r-md: 8px` cho card/ảnh/dialog, `--r-lg: 12px`, `--r-pill: 999px` cho chip lọc, tag tròn & progress bar).
- **Nền tảng thẩm mỹ (`design-taste-frontend`):** Ảnh chụp vật liệu & phối cảnh thực tế dẫn dắt, nền phẳng (`--c-ivory`, `--c-white`, `--c-champagne`), không hiệu ứng màu mè kiểu chợ điện tử.

---

## 2. Design Tokens Bắt buộc (Cấm Hard-code HEX trong Component)

Mọi token khai báo tại `assets/src/tokens/tokens.json` và sinh ra `tokens.css` + `theme.json`:

| Token | Giá trị | Quy tắc sử dụng & Tương phản WCAG AA |
|---|---|---|
| `--c-navy-900` | `#083A4F` | Primary Brand: Header text, Thẻ H1–H6, Nút chính Primary, Logo Dravit, Nền hover menu header (12.15:1 trên White ✅) |
| `--c-text-main` | `#0B192C` | Text Primary: Chữ nội dung chính (body, đoạn văn, bảng dữ liệu, input, mô tả SP), Deep Ink đọc sắc nét (16.3:1 trên White ✅) |
| `--c-text-muted` | `#1E3A5F` | Text Secondary: Chữ phụ, SKU, meta kỹ thuật (9.2:1 ✅) |
| `--c-navy-600` / `400` / `200` | `#386172` / `#94A3B8` / `#E2E8F0` | Viền input, trạng thái disabled, **đường kẻ ron (`--c-navy-200` = `#E2E8F0`)**. **CẤM** dùng làm màu chữ thường |
| `--c-teal-500` / `--c-moss-500` | `#0D9488` | Brand Teal: Nền nút phụ Báo giá, badge active (chữ trắng trên nền: 4.62:1 ✅) |
| `--c-teal-700` / `--c-moss-700` | `#0F766E` | Link văn bản, chữ nhấn màu teal trên nền sáng (6.37:1 ✅) |
| `--c-teal-100` / `--c-moss-100` | `#CCFBF1` | Nền chip lọc đang chọn, highlight nhẹ |
| `--c-amber-500` / `--c-amber-100` / `800` | `#F59E0B` / `#FEF3C7` / `#92400E` | Warm Amber Pop: Badge Showroom tại kệ, icon giỏ, sale (chữ `#92400E` trên nền `#FEF3C7`: 5.8:1 ✅) |
| `--c-champagne` | `#F1F5F9` | Slate 100: Nền khung ảnh sản phẩm, section nhấn, hộp Key Takeaways |
| `--c-ivory` | `#F8FAFC` | Slate 50: Nền chính sạch bóng toàn trang (60% tỷ lệ) |
| `--c-white` | `#FFFFFF` | Nền card sản phẩm, ô input, bảng, dialog |
| `--c-sale` / `success` / `warning` | `#E11D48` / `#059669` / `#D97706` | Chỉ dùng cho chức năng (giá KM, tồn kho, cảnh báo lô màu) |

### Header, Menu & Search Box Rules
- **Header Menu Hover:** Hover vào item trên header nav có nền là màu Primary (`#083A4F`) và chữ/icon đổi sang màu trắng (`#FFFFFF`).
- **Menu Cấp 2 & Cấp 3:**
  - *Desktop:* Tự động mở khi hover chuột (debounce 180ms). Hỗ trợ Dropdown Cấp 2 (`.drv-dropdown`) + Flyout Cấp 3 trượt ngang (`.drv-dropdown__flyout`) và Mega Menu Matrix 4+1 chia nhóm Cấp 2 (`.drv-mega__subgroup`).
  - *Mobile Drawer:* Phân cấp 3 tầng dạng Accordion native (`<details class="drv-mob-accordion">`) với touch target chuẩn tối thiểu 44px.
- **Ô tìm kiếm (`.drv-search`):** `height: 38px`, `max-width: 440px`, viền 1px `--c-navy-200`, nút submit `.drv-search__submit` nền `#083A4F` chữ trắng. Không để placeholder dài bị cắt chữ.

### Typography
- **Display (`--f-display`):** `Anton` (400, IN HOA, self-host `anton-vi.woff2`) — Chỉ dùng cho `H1`, tiêu đề section lớn, số thứ tự `01–05`. **CẤM** dùng Anton cho tên sản phẩm dài, đoạn văn hoặc nút bấm.
- **Body & UI (`--f-body`):** `Quicksand` Variable (400–700, self-host `quicksand-vf-vi.woff2`, preload) — Dùng cho `H2–H6`, tên sản phẩm (`600`), văn bản (`500`), giá tiền (`700`, `font-variant-numeric: tabular-nums`).

---

## 3. Tech Stack Frontend & Whitelist Thư viện

- **Build tool:** Vite + Lightning CSS (`assets/dist/` + `.vite/manifest.json`).
- **CSS:** Vanilla CSS hiện đại với `@layer reset, tokens, base, layout, components, woo, utilities;` + native nesting.
- **JS:** Vanilla ES2022+ Modules (`type="module"`), nạp theo từng template.

### Kiến trúc Component 4 lớp (Chống trùng lặp — Bắt buộc tuân thủ)
1. **PHP Render Helper (`drv_ui($name, $props)`):** Mọi UI component (`btn`, `badge`, `product-card`, `unit-calc`, `sub-silo-tile`, `section-head`, `spec-table`, `shop-the-look`) chỉ đặt tại `template-parts/ui/{name}.php` và gọi qua `drv_ui()`. **CẤM** copy-paste HTML của component sang nhiều template.
2. **CSS Layout Primitives (`.l-*`) & Scoped Variables (`--_*`):**
   - Dùng `.l-container`, `.l-stack`, `.l-cluster`, `.l-grid`, `.l-grout-grid` (lưới đường ron `1px`) cho bố cục, không viết lại `grid/flex/gap` cho từng section.
   - Trong `.drv-*`, khai báo thuộc tính 1 lần qua biến cục bộ (`--_bg`, `--_fg`, `--_bd`, `--_h`); các modifier (`.drv-btn--moss`, `.drv-btn--sm`) chỉ đổi giá trị biến `--_*`.
3. **JS Event Delegation & Light DOM Custom Elements:**
   - Tác vụ đơn (mở `<dialog>`, copy mã SKU, toggle): dùng `data-drv-action="..."` bắt ở cấp `document` trong `core.js`.
   - Component có state (Bộ tính `<drv-unit-calc>`, `<drv-qty-input>`, `<drv-filter-form>`): dùng `customElements.define()` trên **Light DOM** (không dùng Shadow DOM) để tự động khởi tạo cả khi HTML được chèn động qua AJAX.
4. **Shared Utils (`assets/src/js/utils/`):** Dùng chung `formatVnd`, `formatDecimalVn`, `httpGet`, `debounce`, `toast`; không viết lặp hàm tiện ích trong từng file JS.
- **Thư viện duy nhất được phép (`D-19`, `D-28`, `D-30`):**
  - Lightbox/Zoom ảnh sản phẩm & Cẩm nang: **PhotoSwipe 5** (1 module duy nhất `import()` động khi click ảnh).
  - Micro-interactions / Staggered Reveal / Number Odometer: **Anime.js v3.2.2** (`~6.5KB gzip` tự host qua Vite chunk, `import()` động khi có `[data-drv-stagger]`/`[data-drv-reveal]` hoặc `<drv-unit-calc>`; toàn bộ Hover/UI thường ngày dùng Pure CSS GPU `transform`/`opacity`).
  - Carousel/Slider: CSS `scroll-snap` + ~1KB JS điều hướng.
  - Modal/Drawer/Bottom sheet: Native `<dialog>` và Popover API.
  - Icon: **Phosphor Icons Subset tự host** (`assets/fonts/phosphor-subset.woff2`, chứa ~140 icon dùng thực tế, cú pháp `<i class="ph ph-..." aria-hidden="true"></i>`).
  - Ảnh QR thanh toán: `img.vietqr.io` (chỉ render khi đơn BACS ở trạng thái `on-hold`).

### Motion & Micro-interactions Rules (Option A + B Kết Hợp)
- **Option A — Pure CSS GPU (`transform`, `opacity` — 0KB JS):**
  - Dùng `--ease-out-expo: cubic-bezier(0.16, 1, 0.3, 1)` và `--ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1)` trong `tokens.css`.
  - `.drv-card:hover`: nhấc nhẹ `translateY(-3px)`, zoom ảnh `scale(1.04)` và crossfade sang ảnh phối cảnh thực tế (`--dur-slow: 420ms`).
  - `.drv-btn:hover`: trượt icon mũi tên `translateX(3px)`; `:active` nhấn lún xúc giác `scale(0.98)`.
- **Option B — Anime.js (`window.Dravit.motion`):**
  - `staggerReveal`: Xếp gạch tuần tự (`anime.stagger(55)`, `translateY: [14, 0]`, `420ms`) cho các lưới có `[data-drv-stagger]`.
  - `animateNumber`: Chạy số Odometer mượt khi tính `m² ⇄ viên ⇄ thùng ⇄ Tổng tiền VND` (bắt buộc kèm `font-variant-numeric: tabular-nums`).
  - `bounceCartBadge`: Đàn hồi `spring(1, 80, 10, 0)` trên badge giỏ hàng khi thêm sản phẩm.
  - Bắt buộc tôn trọng `@media (prefers-reduced-motion: reduce)`.

### Nghiêm cấm tuyệt đối ở Frontend
- ❌ **CẤM:** Tailwind/Bootstrap, jQuery mới (ngoài trang Cart/Checkout của WC), React/Vue/Alpine, GSAP/Three.js, Swiper.js, classic script IIFE toàn cục, auto-inject widget nặng toàn site (`ai-finder.js`), hiệu ứng `glass-shine` trên `mouseover` toàn trang, nút Wishlist ở Phase 1.
- ❌ **CẤM:** `!important` trong CSS, inline `<script>` / `<style>` (trừ Critical CSS ≤ 14KB trên `<head>` và JSON config nhỏ).
- ❌ **CẤM:** Animate thuộc tính gây Layout Thrashing (`width`, `height`, `top`, `left`, `margin`), parallax nặng, carousel tự chạy, đồng hồ đếm ngược Flash Sale giả, banner dọc bám 2 bên màn hình, icon danh mục hình tròn.
- ❌ **CẤM:** Dùng icon ký tự Unicode/text (`→`, `←`, `↓`, `▾`, `▸`, `⇄`, `×`, `−`, `+`, `✓`, `●`, `☰`, `✅`, `❌`) hoặc emoji (`🔍`, `📍`, `📞`, `💬`, `🛒`) trên giao diện HTML/JS. Mọi biểu tượng bắt buộc dùng `<i class="ph ph-..."></i>` từ font Phosphor subset tự host.
- ❌ **CẤM:** Gọi Google Fonts CDN bên ngoài (font chữ bắt buộc self-host `.woff2`).

---

## 4. Quy tắc Hiệu suất & LiteSpeed Cache

- **Mục tiêu:** PageSpeed Insights **Mobile ≥ 90** (LCP < 2.0s, INP < 200ms, CLS < 0.05).
- **Dequeue tài nguyên thừa:** Gỡ toàn bộ `woocommerce-layout`, `woocommerce-smallscreen`, `woocommerce-general`, `wc-blocks-style` và `wp-block-library` ở các trang WooCommerce.
- **Ảnh:** Mọi `<img>` phải có `width`, `height`, `decoding="async"`. Ảnh LCP có `fetchpriority="high"` và **không** `loading="lazy"`; các ảnh còn lại bắt buộc `loading="lazy"`.
- **Tương thích Page Cache:** Không render số lượng giỏ hàng động trong HTML được cache; đọc cookie `woocommerce_items_in_cart` và nạp chi tiết qua Store API `/wc/store/v1/cart` khi cần.

---

## 5. Brand Voice & Chống Văn phong AI

- **Voice cố định:** Chân thật · Mộc mạc · Vững chãi · Trách nhiệm · Chuyên gia · Đáng tin.
- **Tone trên Website:** Chỉn chu; **Tone Dịch vụ (Giỏ/Checkout/Email):** Thấu hiểu.
- **CẤM** dùng từ sáo rỗng/AI: *"siêu phẩm"*, *"tuyệt tác"*, *"đẳng cấp thượng lưu"*, *"vĩnh cửu"*, *"trong bối cảnh hiện đại ngày nay"*, *"nâng tầm không gian sống"*. Luôn thay bằng thông số và lợi ích thực tế (xem `docs/10_Brand_Voice_And_Content_Guide.md` §5).

