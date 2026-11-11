# 01. Brand & Design System

> Nguồn: [branding/DRAVIT_Brand Guidelines_Xanh Navy + Rêu.pdf](branding/DRAVIT_Brand%20Guidelines_Xanh%20Navy%20+%20R%C3%AAu.pdf) (22 trang), `logo/`, đối chiếu thực tế từ `tokens.css` & ADR `D-11`, `D-18`, `D-19`, `D-25`, `D-28`.
> Skill: `design-taste-frontend`, `baseline-ui`, `fixing-accessibility`.

## 1. Tinh thần thương hiệu

| Mục | Nội dung |
| --- | --- |
| Tính cách | Vững chãi, chuẩn xác, chân thực |
| Thông điệp thị giác | Trực diện, không rườm rà. Tin cậy cho cả thợ thầu lẫn chủ nhà. Mọi đường nét có tỷ lệ và trật tự, **như những đường ron thẳng tắp trên mặt sàn** |
| Tagline | "Thẩm mỹ từ những đường nét nhỏ nhất." |
| Tầm nhìn | Mang vật liệu tiêu chuẩn quốc tế đến công trình Việt với giá hợp lý |
| Brand Voice (6 trụ cột cố định) | **Chân thật · Mộc mạc · Vững chãi · Trách nhiệm · Chuyên gia · Đáng tin** |
| Brand Tone (theo điểm chạm) | **Website:** Chỉn chu · **Truyền thông:** Gần gũi · **Dịch vụ:** Thấu hiểu · **Campaigns:** Trực diện |

> Chi tiết khung ngôn ngữ, câu chuyện thương hiệu và mẫu nội dung cho từng trang: xem [10_Brand_Voice_And_Content_Guide.md](10_Brand_Voice_And_Content_Guide.md).

**Design Read:** "Website bán lẻ gạch ốp lát, trust-first, ảnh vật liệu thật dẫn dắt, lưới chuẩn xác như đường ron, navy chủ đạo, teal nhấn, nền ivory."

**Motif "đường ron":** đường kẻ 1px `var(--bw)` (`1px solid var(--c-navy-200)`), lưới căn hàng tuyệt đối, khoảng cách đều, bo góc nhỏ. Không trang trí thừa.

## 2. Màu sắc

### 2.1 Bảng màu đầy đủ (Phương án A: Deep Ink & Teal + Warm Amber — `D-11`)

| Token CSS | HEX / Giá trị | Nguồn | Vai trò |
|---|---|---|---|
| `--c-navy-950` | `#052330` | Deepest Navy | Nền tối sâu: biến thể Dark Hero (`.drv-hero-void`), gradient nền tối |
| `--c-navy-900` / `--c-brand-primary` | `#083A4F` | Primary Brand | Màu chính thương hiệu: Header text, thẻ H1–H6, Header/Footer, Nút chính Primary, Logo Dravit |
| `--c-navy-hover` | `#0B4A64` | Brand Hover | Trạng thái hover cho nút Primary Navy |
| `--c-text-main` | `#0B192C` | Text Primary | Chữ nội dung chính (body text, đoạn văn, bảng thông số, input, mô tả SP), Deep Ink siêu nét |
| `--c-text-muted` | `#1E3A5F` | Text Secondary | Chữ phụ, mã SKU, meta kỹ thuật |
| `--c-navy-800` | `#1E3A5F` | Navy Mid | Viền đậm, chữ giá phụ (`đ/viên`), sub-brand |
| `--c-navy-700` | `#2A4D68` | Navy 70% | Chữ phụ đậm, viền nhấn |
| `--c-navy-600` | `#386172` | Navy 60% | Viền đậm, icon phụ (không dùng cho chữ thường) |
| `--c-navy-500` | `#64748B` | Slate 500 | Icon phụ, chữ meta nhạt trên nền sáng |
| `--c-navy-400` | `#94A3B8` | Slate 400 | Trạng thái disabled, viền input (`--bw-input`), dấu phân cách breadcrumb |
| `--c-navy-300` | `#CBD5E1` | Slate 300 | Viền nhạt, divider đậm hơn đường ron, chữ phụ trên nền tối |
| `--c-navy-200` | `#E2E8F0` | Slate 200 | Đường kẻ ron 1px (`--bw`), divider chuẩn toàn trang |
| `--c-teal-800` | `#115E59` | Teal 800 | Chữ nhấn đậm trên nền teal nhạt |
| `--c-teal-700` | `#0F766E` | Brand Teal đậm | Link văn bản, chữ nhấn secondary trên nền sáng (6.37:1), chữ badge R10 |
| `--c-teal-500` | `#0D9488` | Brand Teal | Nút phụ Báo giá, badge active, focus ring (`--focus-ring`), viền nhấn |
| `--c-teal-400` | `#2DD4BF` | Teal 400 | Điểm nhấn sáng trên nền tối `.drv-hero-void` |
| `--c-teal-300` | `#5EEAD4` | Teal 300 | Viền highlight, điểm nhấn phụ trên nền tối |
| `--c-teal-200` | `#99F6E4` | Teal 200 | Viền nhạt cho chip lọc active và hộp thông báo |
| `--c-teal-100` | `#CCFBF1` | Teal 100 | Nền chip lọc active, nền tag GEO, nền badge R10 |
| `--c-teal-50` | `#F0FDFA` | Teal 50 | Nền highlight cực nhạt khi hover dòng dữ liệu |
| `--c-amber-800` | `#92400E` | Amber đậm | Chữ badge showroom, chữ cảnh báo kỹ thuật và badge R9 trên nền nhạt (5.8:1) |
| `--c-amber-600` | `#D97706` | Amber 600 | Hover state cho điểm nhấn amber, viền cảnh báo |
| `--c-amber-500` | `#F59E0B` | Warm Pop | Điểm nhấn nóng: Badge showroom, icon giỏ, viền callout warning |
| `--c-amber-100` | `#FEF3C7` | Amber nhạt | Nền badge kệ showroom, nền badge R9 |
| `--c-champagne` | `#F1F5F9` | Slate 100 | Nền section nhấn xen kẽ, nền khung ảnh sản phẩm, hộp Key Takeaways / tip |
| `--c-ivory` / `--c-bg-page` | `#F8FAFC` | Slate 50 | Nền chính toàn trang sạch tinh khiết (Clean Ground, 60% tỷ lệ) |
| `--c-white` | `#FFFFFF` | Surface | Card sản phẩm, input, bảng dữ liệu, dialog |
| `--c-sale` | `#E11D48` | Functional | Giá khuyến mãi, chip `% giảm`, thông báo lỗi (Rose 600) |
| `--c-sale-bg` | `#FFE4E6` | Functional BG | Nền thông báo lỗi, nền badge sale nhẹ / R13 |
| `--c-success` | `#059669` | Functional | Còn hàng tại kho, trạng thái hoàn tất, sao chép thành công (Emerald 600) |
| `--c-success-bg` | `#ECFDF5` | Functional BG | Nền thông báo thành công, nền badge R11 |
| `--c-warning` | `#D97706` | Functional | Sắp hết hàng, lưu ý biến thiên màu `Shade` / lẻ thùng (Amber 600) |
| `--c-warning-bg` | `#FFFBEB` | Functional BG | Nền hộp cảnh báo kỹ thuật nhẹ |
| `--c-backdrop` | `rgb(8 58 79 / 0.6)` | Overlay | Lớp phủ mờ sau `<dialog>`, mobile drawer và modal |

> **Quy tắc Alias & Token bị cấm:**
> - Nhóm `--c-moss-100`, `--c-moss-500`, `--c-moss-600`, `--c-moss-700` được đánh dấu là **deprecated alias** trỏ sang `--c-teal-*` tương ứng để tương thích ngược; mọi CSS viết mới bắt buộc dùng trực tiếp `--c-teal-*`.
> - **Cấm sử dụng** các token cũ đã loại bỏ: `--c-brand-sale`, `--c-coral-600`, `--c-sand-50`, `--c-line`.
> - **Cấm tuyệt đối** gõ cứng mã màu HEX trong file CSS của component hoặc từng trang; mọi giá trị màu phải tham chiếu qua `var(--c-*)` từ `tokens.css`.

### 2.2 Tương phản (WCAG 2.2 AA, đã tính)

| Cặp | Tỷ lệ | Dùng cho |
|---|---|---|
| text-main (#0B192C) / white | 16.3:1 ✅ | Chữ nội dung trong card, body text (AAA) |
| text-main (#0B192C) / ivory | 15.6:1 ✅ | Chữ nội dung toàn trang, đoạn văn (AAA) |
| white / navy-900 (#083A4F) | 12.15:1 ✅ | Header, footer, nút chính Primary, Header menu hover (AAA) |
| navy-900 (#083A4F) / ivory | 11.04:1 ✅ | Thẻ tiêu đề H1–H6, Header text trên nền trang (AAA) |
| text-muted (#1E3A5F) / white | 9.2:1 ✅ | Chữ phụ, SKU, meta kỹ thuật (AAA) |
| white / teal-500 (#0D9488) | 4.62:1 ✅ | Nút Báo giá công trình (AA) |
| teal-700 (#0F766E) / white | 6.37:1 ✅ | Link văn bản, tiêu đề phụ trên nền sáng (AA) |
| sale (#E11D48) / white | 4.80:1 ✅ | Giá khuyến mãi, thông báo lỗi (AA) |
| amber-800 (#92400E) / amber-100 (#FEF3C7) | 5.80:1 ✅ | Chữ badge kệ showroom, callout cảnh báo, badge R9 (AA) |

### 2.3 Phân định màu văn bản & Header Interaction (`D-18`)
- **Header text & thẻ H1–H6:** Bắt buộc dùng `--c-navy-900` (`#083A4F`) để giữ nhận diện thương hiệu sang trọng, dứt khoát.
- **Chữ nội dung (Body copy, mô tả, bảng thông số, form input):** Bắt buộc dùng `--c-text-main` (`#0B192C`) Deep Ink để đọc siêu nét, độ tương phản cao, không gây mỏi mắt.
- **Header Menu Hover:** Hover vào item điều hướng header đổi màu nền sang `--c-navy-900` (`#083A4F`) và chữ/icon sang `--c-white` (`#FFFFFF`).
- **Menu Cấp 2, 3:** Desktop mở tự động khi hover (debounce 180ms) dạng Dropdown + Flyout & Mega Menu Matrix; Mobile mở dạng Accordion 3 cấp (`<details>`).

### 2.4 Quy tắc 60-30-10

60% Clean Slate/White (`#F8FAFC`, `#FFFFFF`) · 30% Deep Ink Navy & Brand Primary (`#0B192C`, `#083A4F`) · 10% Brand Teal (`#0D9488`) điểm xuyết Warm Amber Pop (`#F59E0B`). **Cấm:** gradient tím/AI màu mè, hard-code HEX trong CSS component/page.

## 3. Typography

### 3.1 Font

| Vai trò | Token | Font | Weight | Ghi chú |
| --- | --- | --- | --- | --- |
| Display: H1, tiêu đề section lớn, số thứ tự `01-05` | `--f-display` | **Anton** | 400 | IN HOA, `letter-spacing: .01em`. Không dùng cho tên sản phẩm, đoạn văn, nút |
| H2–H6, body, UI, giá tiền | `--f-body` | **Quicksand** (variable) | 400–700 | Body 500 dễ đọc; tên SP 600; tiêu đề & giá 700; số tiền `tabular-nums` |
| Mã SKU, kích thước `mm`, bảng GEO, mã CK `DRV{id}` | `--f-mono` | **System Monospace** | 500–700 | `ui-monospace, "Cascadia Code", "Source Code Pro", monospace`. **Cấm tải Google Fonts `JetBrains Mono`** |
| Script BaystarScript | — | Không dùng trên web | — | Chỉ dùng dạng ảnh/SVG tĩnh trong ấn phẩm nếu cần |

**Tải font (1 file subset gộp cho mỗi họ font):**
- Self-host `.woff2` tại `assets/fonts/`: đóng gói **đúng 1 file subset gộp sẵn `latin` + `vietnamese` cho mỗi họ font** (`anton-vi.woff2`, `quicksand-vf-vi.woff2`) bằng `tools/subset-fonts.mjs` với dải `unicode-range` gộp sạch, không tách rời 3 file `latin` / `latin-ext` / `vietnamese` chồng lấp khiến trình duyệt tải thừa 6 file.
- Dùng `font-display: swap`, chỉ `<link rel="preload">` duy nhất cho `quicksand-vf-vi.woff2`. Khai báo fallback có metric override để triệt tiêu CLS:

```css
@font-face {
  font-family: "Quicksand Fallback";
  src: local("Segoe UI"), local("Arial");
  size-adjust: 104%; ascent-override: 96%; descent-override: 24%; line-gap-override: 0%;
}
--f-display: "Anton", "Segoe UI", "Arial Narrow", system-ui, sans-serif;
--f-body: "Quicksand", "Quicksand Fallback", "Segoe UI", system-ui, sans-serif;
--f-mono: ui-monospace, "Cascadia Code", "Source Code Pro", monospace;
```

> Giá trị override chính xác được kiểm tra lại bằng `fontaine`/`capsize` khi chạy script build font.

### 3.2 Thang chữ (fluid)

| Token | Kích thước | Line-height | Font |
| --- | --- | --- | --- |
| `--fs-display` | `clamp(2.25rem, 1.6rem + 2.8vw, 4rem)` | 1.05 | Anton |
| `--fs-h1` | `clamp(1.875rem, 1.4rem + 2vw, 3rem)` | 1.1 | Anton |
| `--fs-h2` | `clamp(1.5rem, 1.25rem + 1vw, 2rem)` | 1.25 | Quicksand 700 |
| `--fs-h3` | `1.25rem` | 1.35 | Quicksand 700 |
| `--fs-lg` | `1.125rem` | 1.6 | Quicksand 500/600 |
| `--fs-base` | `1rem` (16px) | 1.6 | Quicksand 500 |
| `--fs-sm` | `0.875rem` | 1.5 | Quicksand 500 |
| `--fs-xs` | `0.75rem` | 1.4 | Quicksand 600 (badge, nhãn phụ) |
| `--fs-price` | `clamp(1.5rem, 1.3rem + .8vw, 2rem)` | 1.1 | Quicksand 700, `tabular-nums` |

Đoạn văn thông thường tối đa `65ch` (riêng bài Cẩm nang cột 8/12 dùng trọn khung `.drv-prose` theo layout kiến trúc). Dùng gạch nối ngắn `-` hoặc dấu `:`, không dùng em-dash trong giao diện UI.

## 4. Layout & spacing (`D-28`)

| Token / Hạng mục | Giá trị |
| --- | --- |
| Spacing scale chuẩn | `--s-1: 4px` · `--s-2: 8px` · `--s-3: 12px` · `--s-4: 16px` · `--s-5: 24px` · `--s-6: 32px` · `--s-7: 48px` · `--s-8: 64px` · `--s-9: 96px` |
| Spacing nửa bậc (UI tinh chỉnh) | `--s-1-5: 6px` (`0.375rem`) · `--s-2-5: 10px` (`0.625rem`) · `--s-3-5: 14px` (`0.875rem`) |
| Container (`--container-max`) | **`max-width: 1380px`**; padding ngang `16px` (`--s-4`, mobile) / `24px` (`--s-5`, `md`) / `32px` (`--s-6`, `xl`) |
| Kích thước Shell / Header | `--header-top-h: 68px` · `--header-top-compact-h: 56px` (khi cuộn xuống) · `--header-nav-h: 44px` · `--mobile-bar-h: 56px` |
| Grid | 4 cột (mobile) / 8 (`md`) / 12 (`lg`), `--grid-gap`: `16px` (mobile) / `24px` (`md+`); lưới đường ron `.l-grout-grid` (`gap: 1px`) |
| Breakpoint | `sm 640px` · `md 768px` · `lg 1024px` · `xl 1280px` (mobile-first, `min-width`) |
| Section padding (`--sec-pad-y`) | `48px` (`--s-7`, mobile) / `64px–96px` (`--s-8`..`--s-9`, desktop) |
| Nền xen kẽ | `ivory` → `white` → `champagne` → `ivory` (cố định theo nhịp trang, không đổi ngẫu nhiên) |

## 5. Shape, đường kẻ, bóng & Focus

| Token | Giá trị & Quy tắc dùng |
| --- | --- |
| `--r-xs` | `2px`: chấm màu nhỏ, nút sao chép inline cỡ nhỏ, thanh chia mảnh |
| `--r-sm` | `4px`: nút bấm, input, badge vuông, tag kích thước, sub-silo tile, hộp callout |
| `--r-md` | `8px`: card sản phẩm, khung ảnh, hộp summary, `<dialog>` |
| `--r-lg` | `12px`: khung hộp tư vấn trên Hero, thẻ nổi bật kích thước lớn |
| `--r-pill` | `999px`: chip bộ lọc, tag bo tròn, thanh tiến độ (progress bar) |
| `--bw` | `1px solid var(--c-navy-200)`: đường kẻ ron chuẩn toàn trang |
| `--bw-input` / `--bw-strong` | `1px solid var(--c-navy-400)` (ô nhập) / `1px solid var(--c-navy-900)` (nút outline, viền nhấn) |
| `--sh-sm` | `0 2px 8px rgb(8 58 79 / 0.05)` (alias `--shadow-sm`, `--shadow-card`): đổ bóng cực nhẹ khi cần tách nền |
| `--shadow-hover` | `0 6px 20px rgb(11 25 44 / 0.08)`: chỉ xuất hiện khi hover card sản phẩm / sub-silo tile |
| `--shadow-dropdown` | `0 12px 32px rgb(11 25 44 / 0.12)`: khung Mega Menu, dropdown cấp 2-3, gợi ý tìm kiếm |
| `--focus-ring` | `2px solid var(--c-teal-500)` (kèm `outline-offset: 2px` cho `:focus-visible` trên mọi phần tử tương tác) |

### 5B. Thang Z-index Cố định (Z-index Scale)

| Token | Giá trị | Phạm vi sử dụng |
| --- | --- | --- |
| `--z-sticky` | `20` | Thanh công cụ lọc sticky trên Catalog, cột Buy Box PDP, hộp `.drv-summary-box--sticky`, TOC sticky |
| `--z-dropdown` | `30` | Menu thả xuống cấp 2–3, Mega Menu 4+1, khung gợi ý Live Search |
| `--z-header` | `40` | Header 2 tầng cố định đầu trang, Mobile Action Bar đáy màn hình, Sticky Purchase Bar trên PDP |
| `--z-fab` | `50` | Nút nổi mở Mục lục bài viết trên mobile (đặt cao hơn `--mobile-bar-h` để không chồng lấp) |
| `--z-modal` | `60` | `<dialog>` bộ lọc mobile, Mini-cart Drawer, `<drv-quote-panel>`, PhotoSwipe Lightbox |
| `--z-toast` | `70` | Thông báo Toast / Notice nổi ở góc màn hình |

> **Cấm tuyệt đối** khai báo số `z-index` tùy tiện (`90`, `99`, `100`, `9999`) trong CSS; mọi lớp xếp chồng phải dùng đúng 6 token trên.

## 6. Logo

| File | Dùng |
| --- | --- |
| `DRAVIT-02.png` (navy) | **Primary**: header nền sáng |
| `DRAVIT-04.png` (ivory, âm bản) | Footer/nền navy |
| `DRAVIT-07.png` (đen) | In ấn đen trắng, email |

- Gốc 8071×2389px, tỷ lệ **3.378:1**. Theme dùng bản tối ưu (`D-12`): header cao 36px (mobile) / 44px (desktop) → bản `logo-navy@1x.webp` 150×44, `@2x` 300×89 (~5KB, nén từ PNG gốc 220KB bằng `tools/optimize-logo.mjs`), giữ PNG nhỏ dự phòng cho email.
- `<img src width="150" height="44" alt="DRAVIT" decoding="async">` bọc trong `<a href="/" aria-label="DRAVIT - Trang chủ">` (không đặt `fetchpriority="high"` lên logo để nhường ưu tiên duy nhất cho ảnh LCP của trang).
- Vùng an toàn ≥ 2x (x = độ dày nét chữ). **Cấm:** nghiêng, viền chữ, warp, xoay ký tự, đổ bóng, 3D, đổi màu, đặt trên nền thiếu tương phản.

## 7. Icon & hình ảnh (`D-28`)

- **Icon:** **Phosphor Icons Subset tự host** (`assets/fonts/phosphor-subset.woff2`, ~140 icon thực dùng trong toàn dự án, sinh qua `tools/build-icons.mjs`), giữ nguyên cú pháp `<i class="ph ph-..." aria-hidden="true"></i>`, kích thước chuẩn `16px / 20px / 24px / 32px`. Không nạp 3 file CSS CDN Phosphor nặng chặn render. Nút chỉ có icon bắt buộc có `aria-label`. **Cấm tuyệt đối:** dùng ký tự Unicode/text (`→`, `▾`, `▸`, `↓`, `⇄`, `×`, `−`, `+`, `✓`, `●`, `☰`) hoặc emoji làm icon trong HTML/JS.
- **Ảnh sản phẩm:** vuông 1:1, nền champagne hoặc trắng, viên gạch chính diện, không đổ bóng giả. Các ảnh phụ gắn vai trò rõ (`room`, `face`, `light`, `grout`, `showroom`, `edge`).
- **Ảnh hero/collection:** không gian thật đã lát gạch Dravit, 16:9 desktop, 4:5 mobile (art direction `<picture>`).
- **Cấm:** hotlink ảnh stock ngoài (Unsplash/Pexels), ảnh stock giả làm sản phẩm Dravit, caption mô tả thừa dưới ảnh trang trí, ảnh chứa chữ quan trọng (SEO/a11y).

## 8. Component spec

| Component | Spec |
| --- | --- |
| **Button primary** | Nền `--c-navy-900`, chữ `--c-white`, Quicksand 700 16px, cao 48px (mobile) / 44px, padding `0 20px`, `--r-sm`. Hover: `--c-navy-hover` (`#0B4A64`). Active: `scale(.98)`. Disabled: `--c-navy-400` |
| **Button secondary** | Nền `--c-teal-500`, chữ `--c-white`. Hover `--c-teal-700` |
| **Button outline** | Viền `var(--bw-strong)`, chữ `--c-navy-900`, nền trong suốt. Hover nền `--c-navy-900` chữ `--c-white` |
| **Button text/link** | Chữ `--c-teal-700`, gạch chân khi hover (draw 200ms) |
| **Input / Select** | Cao 48px, viền `var(--bw-input)`, focus viền `--c-teal-500` + `--focus-ring`, `<label>` luôn hiển thị phía trên (không dùng placeholder thay label), lỗi: viền `--c-sale` + thông báo `role="alert"` |
| **Chip lọc** | `--r-pill`, viền `var(--bw)`, nền `--c-white`; active: nền `--c-teal-100`, viền `--c-teal-500`, chữ `--c-navy-900` + icon `ph-check`; kèm số lượng `(12)` |
| **Card sản phẩm (`.drv-card`)** | Nền `--c-white`, `--r-md`, viền `var(--bw)`; ảnh 1:1 nền `--c-champagne` (hover crossfade sang `gallery[0]` + zoom `1.04`); tên thẻ `<h3>` cố định 2 dòng; dòng meta kích thước · bề mặt · SKU; chân card tách `.drv-card--priced` (`đ/m²` chính + `đ/viên · X thùng` phụ + nút `Thêm vào giỏ`) và `.drv-card--quote` (`Liên hệ báo giá` + quy cách thùng + nút `+ Báo giá`). Không có nút Wishlist |
| **Badge & Nhãn kệ** | `--fs-xs`, chữ hoa, `--r-sm`. Giảm giá: nền `--c-sale` chữ `--c-white` · Nhãn `drv_card_label`: `--c-navy-900` · Kệ Showroom (`Kệ A-08`): nền `--c-amber-100` chữ `--c-amber-800` · Hết hàng (`Tạm hết`): nền `--c-navy-700` chữ `--c-white` |
| **`.drv-r-badge` (`r9..r13`)** | Nhãn chỉ số chống trượt font `--f-mono` 12px đậm, `--r-sm`, padding `2px 8px`: `--r9` (`#FEF3C7` / `#92400E` - khu khô ráo) · `--r10` (`#CCFBF1` / `#0F766E` - nhà tắm, bếp) · `--r11` (`#ECFDF5` / `#059669` - sân vườn, ban công) · `--r12` (`#FFEDD5` / `#C2410C` - dốc gara, xưởng nhẹ) · `--r13` (`#FFE4E6` / `#BE123C` - hồ bơi, sàn ướt dốc) |
| **`.drv-stepper` (Unit-aware)** | Cụm tăng giảm số lượng viền `var(--bw-input)`, `--r-sm`, gồm 2 nút `ph-minus` / `ph-plus` (`44×44px` chuẩn, `38×38px` cho biến thể gọn `.drv-stepper--cart`) và ô `<input>` giữa `tabular-nums`; `:focus-within` bật `--focus-ring`. Bước nhảy tự động tuân theo đơn vị đang chọn (`m²` / `thùng` / `viên`) và `drv_round_mode` của từng sản phẩm |
| **`.drv-swatch-dot`** | Ô mẫu màu tròn/bo nhỏ `18–24px`, có viền trong `1px solid rgb(11 25 44 / 0.14)` để màu trắng/kem nổi rõ trên nền sáng; trạng thái active có vòng ring `--c-navy-900` cách `2px`. Trên PDP bắt buộc là thẻ `<a href>` SSR nối tới SKU cùng `drv_series` |
| **`.drv-copy-inline`** | Nút sao chép nhanh mã SKU, số tài khoản, số tiền hoặc nội dung `DRV{id}`: cao `26–28px`, font `--f-mono`, nền `--c-ivory`, viền `var(--bw)`, `--r-sm`, kèm icon `ph-copy`; khi bấm đổi sang `ph-check` màu `--c-success` trong 2 giây. Đặt bên ngoài thẻ `<h1>` |
| **Giá niêm yết** | `385.000đ/m²` (`--c-navy-900`, `--fs-price`, `tabular-nums`) + `≈ 246.400đ/viên` (`--c-navy-800`, `--fs-sm`). Khuyến mãi: giá gốc gạch ngang `--c-navy-500`, giá mới `--c-sale`, chip `-14%` |
| **`.drv-summary-box`** | Hộp tổng kết giỏ hàng / checkout / dự toán BOQ: nền `--c-white` hoặc `--c-ivory`, viền `var(--bw)`, `--r-md`, padding `--s-5`; biến thể `.drv-summary-box--sticky` bám theo cuộn (`z-index: var(--z-sticky)`), luôn hiện rõ tổng `m²`, tổng `thùng`, tổng `kg` và ghi chú phí vận chuyển |
| **`.drv-steps` (3 bước)** | Thanh tiến trình giao dịch `01 Giỏ hàng` -> `02 Thanh toán & Xác nhận` -> `03 Hoàn tất đơn` ngăn bằng đường kẻ ron `1px`; bước đang mở dùng `--c-navy-900` đậm, bước đã qua dùng `--c-teal-700` kèm `ph-check` |
| **Bảng thông số & `.drv-geo-card` / `.drv-geo-table`** | `.drv-geo-card` nền `--c-white`, viền `var(--bw)`, `--r-md`, có nhãn `.drv-geo-card__tag` font `--f-mono` (`--c-teal-100` / `--c-teal-700`), bọc `.drv-geo-table-wrap` (`overflow-x: auto`) chứa `<table>` `.drv-geo-table`: `<thead>` nền `--c-ivory`, ô số liệu `--f-mono`, thông số chính `.is-key` màu `--c-teal-700` đậm, giá trị thuộc Silo gắn link `<a href>` (`IL-03`) |
| **`.drv-key-takeaways`** | Hộp "Những điểm chính" đầu bài Cẩm nang: nền `--c-champagne`, viền `var(--bw)`, viền trái `4px solid var(--c-teal-500)`, `--r-sm`, phẳng không bóng; liệt kê 3–4 ý cốt lõi gắn icon `ph-check-circle` và link văn bản `--c-teal-700` |
| **`.drv-callout` (`--note/--tip/--warning`)** | Hộp lưu ý chuyên môn viền `var(--bw)` + viền trái dày `4px`, `--r-sm`, padding `--s-3 var(--s-4)`, tiêu đề `.drv-callout__head` chữ hoa `--fs-xs` kèm icon: `--note` (nền `--c-ivory`, viền trái & chữ `--c-navy-900`), `--tip` (nền `--c-champagne`, viền trái `--c-teal-500`, chữ `--c-teal-700`), `--warning` (nền `--c-amber-100` / `--c-warning-bg`, viền `--c-amber-500`, viền trái & chữ `--c-amber-800`) |
| **`.drv-hero-void` (Dark Hero)** | Biến thể Hero nền tối `--c-navy-950` cho Trang chủ lai (`D-25`): lớp dưới là ảnh phối cảnh `<picture>` thật làm phần tử LCP (`fetchpriority="high"`) phủ gradient tối đảm bảo tương phản chữ `--c-white` ≥ 7:1, kết hợp hộp tìm nhanh / tư vấn ngay trên màn đầu |
| **`.drv-footer-dock`** | Dải 3 hộp hành động nhanh ngay trên Footer chính, chia lưới 3 cột bằng đường ron `1px` trên nền `--c-champagne`: (1) Hotline & Zalo kỹ thuật kèm chấm trạng thái giờ làm việc, (2) Địa chỉ Showroom/Kho & Google Maps, (3) Dự toán & Báo giá công trình BOQ |
| **Breadcrumb** | `--fs-sm`, một dòng gọn, phân cách `/` màu `--c-navy-400`, mục cuối không gắn link (`aria-current="page"`) |
| **Pills danh mục & Load more** | Hàng chip cuộn ngang `scroll-snap` (`<a>` tĩnh); cụm Load More gồm dòng `"Đang xem 24 / 124 mẫu"` + thanh tiến độ `--r-pill` + nút outline kèm link phân trang `<a rel="next">` |
| **Toast & Dialog/Drawer** | Toast góc dưới (`z-index: var(--z-toast)`, `role="status"`, tự ẩn 4s); `<dialog>` (`z-index: var(--z-modal)`, `--r-md`, backdrop `var(--c-backdrop)`, khóa cuộn nền, đóng bằng `Esc`, trả focus về nút mở) |

## 9. Motion & Micro-interactions (`D-19`, `D-25`)

- **Option A — Pure CSS GPU (`transform`, `opacity` — 0KB JS):**
  - Thời lượng: `--dur-instant: 100ms` (active press `scale(0.98)`) · `--dur-fast: 160ms` (hover nút/chip/menu, trượt mũi tên `translateX(3px)`) · `--dur-base: 260ms` (nhấc card `-3px`, gạch chân vẽ ngang `scaleX(0→1)`, dropdown) · `--dur-drawer: 280ms` (modal/drawer) · `--dur-slow: 420ms` (zoom ảnh `1.04` & crossfade ảnh phối cảnh).
  - Đường cong gia tốc: `--ease-out: cubic-bezier(0.2, 0.7, 0.2, 1)` · `--ease-out-expo: cubic-bezier(0.16, 1, 0.3, 1)` · `--ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1)`.
  - Chỉ animate trên luồng Compositor (`transform` và `opacity`). **Cấm:** animate `width/height/top/left/margin`, parallax nặng, carousel tự chạy, tilt 3D, và **cấm tuyệt đối listener `mouseover` / `pointermove` toàn trang kiểu `glass-shine`** gây nghẽn luồng chính (tăng INP).
- **Option B — Anime.js v3.2.2 tự host (`~6.5KB gzip` Vite chunk, nạp qua `import()` động):**
  - Đóng gói nội bộ trong `motion.js`, chỉ gọi `import()` động khi trang có phần tử kích hoạt cho 4 tác vụ:
    1. **Staggered Tile Grid Reveal (`[data-drv-stagger]`):** Xếp gạch tuần tự (`anime.stagger(55)`, `translateY: [14, 0]`, `420ms`, `easeOutExpo`).
    2. **Hero / Section Reveal (`[data-drv-reveal]`):** Xuất hiện mượt theo ngưỡng `IntersectionObserver` một lần duy nhất.
    3. **Number Odometer (`animateNumber`):** Chạy số mượt khi tính `m² ⇄ viên ⇄ thùng ⇄ Tổng tiền VND` trong `<drv-unit-calc>` (bắt buộc kèm `tabular-nums`).
    4. **Spring Cart Badge Bounce (`bounceCartBadge`):** Đàn hồi `spring(1, 80, 10, 0)` khi thêm sản phẩm vào giỏ hoặc DS báo giá.
- **Quy tắc `<canvas>` trên Hero (`D-25`):** Lớp `<canvas>` trang trí trong `.drv-hero-void` **chỉ được khởi tạo trên desktop (`≥ 1024px`) sau khi ảnh LCP đã tải xong**, tự tạm dừng khi cuộn ra ngoài viewport và **không khởi chạy** trên mobile (`< 1024px`) hoặc khi người dùng bật `prefers-reduced-motion: reduce`.
- `@media (prefers-reduced-motion: reduce)`: tự động tắt toàn bộ transition/animation CSS, không nạp `<canvas>` và bỏ qua hiệu ứng JS (cập nhật số liệu tức thì).

## 10. Giọng văn & microcopy

| Nguyên tắc | Ví dụ đúng | Tránh |
| --- | --- | --- |
| Trực diện, có số liệu | "Gạch 80x80 vân đá, độ hút nước ≤ 0,5%" | "Siêu phẩm đẳng cấp thượng lưu" |
| Nút = động từ + đối tượng (thống nhất toàn site) | "Thêm vào giỏ", "Liên hệ báo giá", "Thêm vào DS báo giá", "Gửi yêu cầu báo giá" | "Click here", "Xem thêm" đứng một mình |
| Định dạng số VN | `385.000đ`, `2,56 m²`, `80x80 cm` | `385,000 VND`, `2.56m2` |
| Chân thật & đồng nhất SLA | "Phí vận chuyển: báo sau khi xác nhận đơn", phản hồi theo `drv_settings.order.sla_text` | Ẩn chi phí, ghi lẫn lộn 15 phút / 30 phút / 24h |
| Không bịa đặt | Chỉ hiển thị tác giả/kỹ sư thật, tồn kho thật; phân biệt rõ `Shade V1-V4` và mã lô `Lot` | Số lượt bình chọn giả, tên kỹ sư hư cấu, viết gộp "Lô Shade V2" |

## 11. Token nguồn (single source)

Token được khai báo **một lần** trong `assets/src/tokens/tokens.json`; script `tools/build-tokens.mjs` sinh ra:

- `assets/src/css/layers/tokens.css` (custom properties `:root` trong `@layer tokens`)
- phần `settings.color.palette`, `settings.typography` của `theme.json` (Gutenberg editor dùng chung bảng màu và thang chữ)

Component và page CSS chỉ dùng `var(--token)`. Đổi màu/font = sửa `tokens.json` rồi chạy lệnh build lại.
