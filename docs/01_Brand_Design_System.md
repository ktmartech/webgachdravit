# 01. Brand & Design System

> Nguồn: [branding/DRAVIT_Brand Guidelines_Xanh Navy + Rêu.pdf](branding/DRAVIT_Brand%20Guidelines_Xanh%20Navy%20+%20R%C3%AAu.pdf) (22 trang), `logo/`. Skill: `design-taste-frontend`, `baseline-ui`, `fixing-accessibility`.

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

**Design Read:** "Website bán lẻ gạch ốp lát, trust-first, ảnh vật liệu thật dẫn dắt, lưới chuẩn xác như đường ron, navy chủ đạo, rêu nhấn, nền ivory."

**Motif "đường ron":** đường kẻ 1px `--c-line`, lưới căn hàng tuyệt đối, khoảng cách đều, bo góc nhỏ. Không trang trí thừa.

## 2. Màu sắc

### 2.1 Bảng màu (Phương án A: Deep Ink & Teal + Warm Amber)

| Token CSS | HEX | Nguồn | Vai trò |
|---|---|---|---|
| `--c-navy-900` | `#083A4F` | Primary Brand | Màu chính thương hiệu: Header text, thẻ H1–H6, Header/Footer, Nút chính Primary, Logo Dravit |
| `--c-text-main` | `#0B192C` | Text Primary | Chữ nội dung chính (body text, đoạn văn, bảng thông số, input, mô tả SP), Deep Ink siêu nét |
| `--c-text-muted` | `#1E3A5F` | Text Secondary | Chữ phụ, SKU, meta kỹ thuật |
| `--c-navy-600` | `#386172` | Navy 60% | Viền đậm, icon phụ (không dùng cho chữ thường) |
| `--c-navy-400` | `#94A3B8` | Slate 400 | Disabled, viền input |
| `--c-navy-200` | `#E2E8F0` | Slate 200 | Đường kẻ (ron 1px), divider |
| `--c-teal-500` / `--c-moss-500` | `#0D9488` | Brand Teal | Nút phụ Báo giá, badge active, highlight |
| `--c-teal-700` / `--c-moss-700` | `#0F766E` | Brand Teal đậm | Link, chữ màu secondary trên nền sáng |
| `--c-teal-100` / `--c-moss-100` | `#CCFBF1` | Teal nhạt | Nền chip active, nền thông báo nhẹ |
| `--c-amber-500` | `#F59E0B` | Warm Pop | Điểm nhấn nóng: Badge showroom, icon giỏ, sale |
| `--c-amber-100` | `#FEF3C7` | Amber nhạt | Nền badge showroom |
| `--c-amber-800` | `#92400E` | Amber đậm | Chữ badge showroom trên nền nhạt (5.8:1) |
| `--c-champagne` | `#F1F5F9` | Slate 100 | Nền section nhấn, nền khung ảnh sản phẩm, hộp GEO |
| `--c-ivory` | `#F8FAFC` | Slate 50 | Nền trang sạch tinh khiết (Clean Ground, 60% tỷ lệ) |
| `--c-white` | `#FFFFFF` | Surface | Card sản phẩm, input, bảng dữ liệu, dialog |
| `--c-sale` | `#E11D48` | Functional | Giá khuyến mãi, % giảm, lỗi (Rose 600) |
| `--c-success` | `#059669` | Functional | Còn hàng, thành công (Emerald 600) |
| `--c-warning` | `#D97706` | Functional | Sắp hết hàng, lưu ý lô màu (Amber 600) |

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
| sale / white | 4.80:1 ✅ | Giá khuyến mãi (AA) |
| amber-800 (#92400E) / amber-100 (#FEF3C7) | 5.80:1 ✅ | Chữ badge showroom (AA) |

### 2.3 Phân định màu văn bản & Header Interaction
- **Header text & thẻ H1–H6:** Bắt buộc dùng `--c-navy-900` (`#083A4F`) để giữ nhận diện thương hiệu sang trọng, dứt khoát.
- **Chữ nội dung (Body copy, mô tả, bảng thông số, form input):** Bắt buộc dùng `--c-text-main` (`#0B192C`) Deep Ink để đọc siêu nét, độ tương phản cao, không gây mỏi mắt.
- **Header Menu Hover:** Hover vào item điều hướng header đổi màu nền sang `--c-navy-900` (`#083A4F`) và chữ/icon sang `--c-white` (`#FFFFFF`).
- **Menu Cấp 2, 3:** Desktop mở tự động khi hover (debounce 180ms) dạng Dropdown + Flyout & Mega Menu Matrix; Mobile mở dạng Accordion 3 cấp (`<details>`).

### 2.4 Quy tắc 60-30-10

60% Clean Slate/White (`#F8FAFC`, `#FFFFFF`) · 30% Deep Ink Navy & Brand Primary (`#0B192C`, `#083A4F`) · 10% Brand Teal (`#0D9488`) điểm xuyết Warm Amber Pop (`#F59E0B`). **Cấm:** gradient tím/AI, hard-code HEX trong CSS component (chỉ dùng token).

## 3. Typography

### 3.1 Font

| Vai trò | Font | Weight | Ghi chú |
| --- | --- | --- | --- |
| Display: H1, tiêu đề section, banner | **Anton** | 400 | IN HOA, `letter-spacing: .01em`. Không dùng cho tên sản phẩm, đoạn văn, nút |
| H2–H6, body, UI, giá | **Quicksand** (variable) | 400–700 | Body 500 cho dễ đọc; tiêu đề 700; giá `tabular-nums` |
| Script BaystarScript | Không dùng trên web | | Chỉ dùng dạng ảnh/SVG trong banner nếu cần |

**Tải font:** self-host woff2 tại `assets/fonts/`, subset `latin` + `vietnamese` (unicode-range), `font-display: swap`. Preload `quicksand-vf-vi.woff2`. Fallback có metric override để giảm CLS:

```css
@font-face {
  font-family: "Quicksand Fallback";
  src: local("Arial");
  size-adjust: 104%; ascent-override: 96%; descent-override: 24%; line-gap-override: 0%;
}
--f-body: "Quicksand", "Quicksand Fallback", system-ui, sans-serif;
--f-display: "Anton", "Arial Narrow", Impact, sans-serif;
```

> Giá trị override chính xác được đo khi build (công cụ `fontaine`/`capsize`), số trên là khởi điểm.

### 3.2 Thang chữ (fluid)

| Token | Kích thước | Line-height | Font |
| --- | --- | --- | --- |
| `--fs-display` | `clamp(2.25rem, 1.6rem + 2.8vw, 4rem)` | 1.05 | Anton |
| `--fs-h1` | `clamp(1.875rem, 1.4rem + 2vw, 3rem)` | 1.1 | Anton |
| `--fs-h2` | `clamp(1.5rem, 1.25rem + 1vw, 2rem)` | 1.25 | Quicksand 700 |
| `--fs-h3` | `1.25rem` | 1.35 | Quicksand 700 |
| `--fs-lg` | `1.125rem` | 1.6 | Quicksand 500 |
| `--fs-base` | `1rem` (16px) | 1.6 | Quicksand 500 |
| `--fs-sm` | `0.875rem` | 1.5 | Quicksand 500 |
| `--fs-xs` | `0.75rem` | 1.4 | Quicksand 600 (badge) |
| `--fs-price` | `clamp(1.5rem, 1.3rem + .8vw, 2rem)` | 1.1 | Quicksand 700, tabular |

Đoạn văn tối đa `65ch`. Dùng gạch nối `-`, không dùng em-dash trong UI.

## 4. Layout & spacing

| Token | Giá trị |
| --- | --- |
| Spacing scale | `--s-1: 4px` · `--s-2: 8px` · `--s-3: 12px` · `--s-4: 16px` · `--s-5: 24px` · `--s-6: 32px` · `--s-7: 48px` · `--s-8: 64px` · `--s-9: 96px` |
| Container | `max-width: 1320px`; padding ngang 16px (mobile) / 24px (md) / 32px (xl) |
| Grid | 4 cột (mobile) / 8 (md) / 12 (lg), gap 16/24px |
| Breakpoint | `sm 640` · `md 768` · `lg 1024` · `xl 1280` (mobile-first, `min-width`) |
| Section padding | 48px mobile / 64–96px desktop |
| Nền xen kẽ | ivory → white → champagne → ivory (cố định, không ngẫu nhiên) |

## 5. Shape, đường kẻ, bóng

| Token | Giá trị |
| --- | --- |
| `--r-sm` | `4px`: nút, input, badge vuông |
| `--r-md` | `8px`: card, ảnh, dialog |
| `--r-pill` | `999px`: chip lọc, tag |
| `--bw` | `1px solid var(--c-navy-200)`: đường ron |
| `--shadow-hover` | `0 6px 20px rgb(8 58 79 / .10)`: chỉ khi hover card |
| `--focus` | `outline: 2px solid var(--c-moss-500); outline-offset: 2px` |

## 6. Logo

| File | Dùng |
| --- | --- |
| `DRAVIT-02.png` (navy) | **Primary**: header nền sáng |
| `DRAVIT-04.png` (ivory, âm bản) | Footer/nền navy |
| `DRAVIT-07.png` (đen) | In ấn đen trắng, email |

- Gốc 8071×2389px, tỷ lệ **3.378:1**. Theme dùng bản tối ưu (D-12): header cao 36px (mobile) / 44px (desktop) → bản `logo-navy@1x.webp` 150×44, `@2x` 300×89, giữ PNG fallback cho email.
- `<img src width="150" height="44" alt="DRAVIT" fetchpriority="high" decoding="async">` bọc trong `<a href="/" aria-label="DRAVIT - Trang chủ">`.
- Vùng an toàn ≥ 2x (x = độ dày nét chữ). **Cấm:** nghiêng, viền chữ, warp, xoay ký tự, đổ bóng, 3D, đổi màu, đặt trên nền thiếu tương phản.

## 7. Icon & hình ảnh

- **Icon:** **Phosphor Icons CDN** (`@phosphor-icons/web@2.1.2` qua jsDelivr CDN, các bộ `regular`, `bold`, `fill`), sử dụng thẻ `<i class="ph ph-..." aria-hidden="true"></i>`, kích thước chuẩn `16px / 20px / 24px / 32px`. Icon-only button bắt buộc phải có `aria-label`. **Cấm tuyệt đối:** dùng ký tự Unicode/text (`→`, `▾`, `▸`, `↓`, `⇄`, `×`, `−`, `+`, `✓`, `●`, `☰`) hoặc emoji làm icon.
- **Ảnh sản phẩm:** vuông 1:1, nền champagne hoặc trắng, viên gạch chính diện, không đổ bóng giả. Ảnh 2: cận vân (texture) độ phân giải cao. Ảnh 3+: phối cảnh thực tế 4:3 hoặc 3:2.
- **Ảnh hero/collection:** không gian thật đã lát gạch Dravit, 16:9 desktop, 4:5 mobile (art direction `<picture>`).
- **Cấm:** ảnh stock giả làm sản phẩm Dravit, caption mô tả dưới ảnh trang trí, ảnh chứa chữ quan trọng (SEO/a11y).

## 8. Component spec

| Component | Spec |
| --- | --- |
| **Button primary** | Nền navy-900, chữ white, Quicksand 700 16px, cao 48px (mobile) / 44px, padding 0 20px, `--r-sm`. Hover: nền `#0B4A64`. Active: `scale(.98)`. Disabled: navy-400 |
| **Button secondary** | Nền moss-500, chữ white. Hover moss-700 |
| **Button outline** | Viền 1px navy-900, chữ navy-900, nền trong. Hover nền navy-900 chữ white |
| **Button text/link** | moss-700, gạch chân khi hover (draw 200ms) |
| **Input/select** | Cao 48px, viền navy-400, focus viền moss-500 + `--focus`, label luôn hiển thị phía trên (không dùng placeholder thay label), lỗi: viền sale + thông báo `role="alert"` |
| **Chip lọc** | `--r-pill`, viền navy-200, nền white; active: nền moss-100, viền moss-500, chữ navy-900 + icon check; có số lượng `(12)` |
| **Card sản phẩm** | Nền white, `--r-md`, viền 1px navy-200; ảnh 1:1 nền champagne; tên 2 dòng (clamp); dòng thông số (kích thước · bề mặt); giá/m² lớn + giá/viên nhỏ; badge góc trên trái. Hover: nhấc 2px + `--shadow-hover`, ảnh zoom 1.03 trong khung. Toàn card là 1 link (tên là `<a>`, mở rộng vùng click bằng pseudo-element) |
| **Badge** | `--fs-xs`, chữ hoa, `--r-sm`. Ưu đãi: nền sale chữ white · Mới: navy · Có mẫu showroom: moss-100 chữ moss-700 |
| **Giá** | `385.000đ/m²` (navy-900, `--fs-price`) + `≈ 246.400đ/viên` (navy-800, `--fs-sm`). KM: giá cũ gạch ngang navy-800, giá mới màu sale, nhãn `-15%` |
| **Bảng thông số** | `<table>` 2 cột, `<th scope="row">` nền ivory, dòng cách nhau bằng đường ron 1px; giá trị là link khi thuộc collection (IL-03) |
| **Breadcrumb** | `--fs-sm`, phân cách `/` màu navy-400, mục cuối không link (`aria-current="page"`) |
| **Pills danh mục** | Hàng cuộn ngang (scroll-snap), `<a>` chip outline |
| **Load more** | Button outline full width (mobile) / 320px giữa (desktop) + dòng "Đang xem 24/180 sản phẩm" + thanh tiến độ mảnh |
| **Toast/notice** | Góc dưới, `role="status"`, tự ẩn 4s, có nút đóng |
| **Dialog/drawer** | `<dialog>`, `--r-md`, backdrop `rgb(8 58 79 / .5)`, khóa scroll, đóng bằng Esc, trả focus về nút mở |

## 9. Motion & Micro-interactions (Option A + B Kết Hợp)

- **Option A — Pure CSS GPU (`transform`, `opacity` — 0KB JS):**
  - Thời lượng: `--dur-instant: 100ms` (active press `scale(0.98)`) · `--dur-fast: 160ms` (hover nút/chip/menu, trượt mũi tên `translateX(3px)`) · `--dur-base: 260ms` (nhấc card `-3px`, gạch chân vẽ ngang `scaleX(0→1)`, dropdown) · `--dur-drawer: 280ms` (modal/drawer) · `--dur-slow: 420ms` (zoom ảnh `1.04` & crossfade ảnh phối cảnh).
  - Đường cong gia tốc: `--ease-out: cubic-bezier(0.2, 0.7, 0.2, 1)` · `--ease-out-expo: cubic-bezier(0.16, 1, 0.3, 1)` · `--ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1)`.
  - Chỉ animate trên luồng Compositor (`transform` và `opacity`). **Cấm:** animate `width/height/top/left/margin`, parallax nặng, carousel tự chạy, tilt 3D.
- **Option B — Anime.js v3.2.2 (`~6.5KB gzip` CDN/ESM, `defer`):**
  - Chỉ nạp cho 3 tác vụ vi tương tác giá trị cao trong `Dravit.motion`:
    1. **Staggered Tile Grid Reveal (`[data-drv-stagger]`):** Xếp gạch tuần tự (`anime.stagger(55)`, `translateY: [14, 0]`, `420ms`, `easeOutExpo`).
    2. **Number Odometer (`animateNumber`):** Chạy số mượt khi tính `m² ⇄ viên ⇄ thùng ⇄ Tổng tiền VND` trong `<drv-unit-calc>` (bắt buộc kèm `tabular-nums`).
    3. **Spring Cart Badge Bounce (`bounceCartBadge`):** Đàn hồi `spring(1, 80, 10, 0)` khi thêm sản phẩm vào giỏ.
- `@media (prefers-reduced-motion: reduce)`: tự động tắt toàn bộ transition/animation và bỏ qua hiệu ứng JS (cập nhật số tức thì).

## 10. Giọng văn & microcopy

| Nguyên tắc | Ví dụ đúng | Tránh |
| --- | --- | --- |
| Trực diện, có số liệu | "Gạch 80x80 vân đá, độ hút nước < 0,5%" | "Siêu phẩm đẳng cấp thượng lưu" |
| Nút = động từ + đối tượng | "Thêm vào giỏ", "Nhận báo giá", "Chat Zalo", "Xem 120 mẫu gạch lát nền" | "Click here", "Xem thêm" chung chung |
| Định dạng số VN | `385.000đ`, `2,56 m²`, `80x80 cm` | `385,000 VND`, `2.56m2` |
| Chân thật | Báo rõ "Phí vận chuyển báo sau khi xác nhận đơn" | Ẩn chi phí |
| Không bịa | Không đánh giá/khách hàng/số liệu giả | |

## 11. Token nguồn (single source)

Token được khai báo **một lần** trong `assets/src/tokens/tokens.json`; script build sinh ra:

- `assets/src/css/tokens.css` (custom properties `:root`)
- phần `settings.color.palette`, `settings.typography` của `theme.json` (editor dùng chung màu/font)

Component CSS chỉ dùng `var(--token)`. Đổi màu/font = sửa `tokens.json` rồi build lại.
