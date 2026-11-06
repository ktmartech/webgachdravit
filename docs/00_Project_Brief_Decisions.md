# 00. Project Brief & Nhật ký quyết định

## 1. Tổng quan

| Mục | Nội dung |
|---|---|
| Thương hiệu | **DRAVIT** (Công ty TNHH Dravit) |
| Tagline | "Thẩm mỹ từ những đường nét nhỏ nhất." |
| Tính cách | Vững chãi, chuẩn xác, chân thực |
| Website | gachdravit.vn: TMĐT bán lẻ gạch ốp lát, có showroom |
| Mô hình | B2C bán lẻ (chủ nhà), phục vụ thêm thợ/thầu và KTS (báo giá công trình) |
| Nền tảng | WordPress + WooCommerce + Rank Math + theme custom `dravit` |

## 2. Mục tiêu & KPI

| Mục tiêu | KPI nghiệm thu |
|---|---|
| Tốc độ | PageSpeed Insights **mobile ≥ 90** trên 5 template (home, `/gach`, URL lọc, sản phẩm, cẩm nang). CWV: LCP < 2.0s, INP < 200ms, CLS < 0.05 |
| SEO | 28 URL collection được index; URL lọc đúng ma trận robots; Rich Results (Product, Breadcrumb, FAQ) hợp lệ |
| GEO | Nội dung SSR, thông số dạng bảng text, `llms.txt` hoạt động, schema đầy đủ |
| Chuyển đổi | Đặt đơn COD/Chuyển khoản trong ≤ 3 bước; CTA Gọi/Zalo/Báo giá luôn hiện |
| Bảo trì | Một hệ token, một bộ component, không thư viện thừa, code module hóa |

## 3. Phạm vi Phase 1

**Trong phạm vi**
- Ngành hàng: **gạch ốp lát** (silo `/gach` + 27 collection; ưu tiên các collection "Phase 1 - Core" trong tài liệu Silo, nhưng tạo đủ 28 term).
- Theme `dravit`: toàn bộ template public + WooCommerce (danh mục, sản phẩm, giỏ, checkout, tài khoản tối giản).
- Bộ lọc AJAX + load more, ma trận SEO lọc, schema, llms.txt.
- Đơn vị bán: giá/m² (chính), giá/viên (phụ), giao dịch theo **viên**; hệ đơn vị mở rộng sẵn cho chiếc/bộ/tuýp/bao.
- Thanh toán: **COD + Chuyển khoản (VietQR)**. Vận chuyển: **báo phí sau khi xác nhận đơn**.
- Trang nội dung: Giới thiệu, Showroom/Liên hệ, Chính sách (đổi trả, vận chuyển, thanh toán, bảo mật), Cẩm nang (blog).

**Ngoài phạm vi Phase 1** (kiến trúc phải sẵn sàng)
- Ngành hàng khác (keo chà ron, vật tư hoàn thiện, thiết bị vệ sinh).
- Thanh toán online (VNPay/MoMo), tính phí ship tự động, đa ngôn ngữ, visualizer phối cảnh, so sánh sản phẩm, wishlist.

## 4. Persona

| Persona | Nhu cầu | Đáp ứng |
|---|---|---|
| Chủ nhà | Chọn gạch theo không gian/phong cách, biết giá/m², tính số lượng | Collection theo không gian, ảnh phối cảnh, máy tính số viên, giá/m² rõ |
| Thợ/thầu | Tìm nhanh theo kích thước/thông số, giá, tồn kho, báo giá số lượng lớn | Lọc kích thước/DCOF/độ hút nước, SKU search, nút Báo giá công trình |
| KTS/thiết kế | Bộ sưu tập, thông số kỹ thuật, ảnh vân chất lượng | Bảng thông số đầy đủ, gallery zoom, bộ sưu tập |

## 5. Nhật ký quyết định (ADR)

| ID | Ngày | Quyết định | Lý do / hệ quả |
|---|---|---|---|
| D-01 | 2026-11-05 | Theme **PHP truyền thống (classic)**; `theme.json` chỉ khai báo token cho editor | Toàn quyền `<head>`/HTML/SEO, filter SSR, CSS nhẹ. Trang nội dung vẫn dùng Gutenberg + patterns |
| D-02 | 2026-11-05 | 28 Smart Collections = **`product_cat`**, tự gán theo thuộc tính khi lưu sản phẩm | Tận dụng Rank Math (bỏ base, primary term, breadcrumb, sitemap) và WC native |
| D-03 | 2026-11-05 | Bộ lọc **tự viết**, **AJAX không tải lại + Load more**, vẫn SSR và crawl được | Đúng tên param trong tài liệu Silo, kiểm soát `<a>`/`<button>` và robots |
| D-04 | 2026-11-05 | **Vanilla CSS hiện đại + Vite**, Vanilla JS ES modules, không jQuery mới | Nhẹ, một nguồn token, dễ bảo trì |
| D-05 | 2026-11-05 | Gạch: **giá/m² chính, giá/viên phụ, đơn vị giao dịch = viên** | Số lượng nguyên → tồn kho/báo cáo WC chính xác. Giá WC (`_price`) = giá/viên, tự tính từ giá/m² |
| D-06 | 2026-11-05 | Hệ đơn vị **mở rộng** (`area_pack`, `piece`, `set`, `pack`) | Sẵn sàng cho chiếc, bộ, tuýp, bao ở phase sau |
| D-07 | 2026-11-05 | **Không tách plugin**: mọi logic trong theme, module hóa namespace `Dravit\` | Rủi ro mất logic khi đổi theme → dữ liệu chỉ lưu ở cấu trúc chuẩn WC, module tách được sau |
| D-08 | 2026-11-05 | Hosting **AZDIGI LiteSpeed + LiteSpeed Cache** | Page cache + object cache; tắt tối ưu CSS/JS của LSCache (Vite đã làm) |
| D-09 | 2026-11-05 | Thanh toán **COD + Chuyển khoản** (VietQR) | Gateway native WC `cod`, `bacs` |
| D-10 | 2026-11-05 | Vận chuyển **báo phí sau khi xác nhận đơn** | Phương thức ship custom phí 0 + trạng thái đơn "Chờ xác nhận" + email xác nhận có phí ship |
| D-11 | 2026-11-05 | Bảng màu chốt theo **Phương án A (Deep Ink & Teal + Amber)** | Brand Primary `#083A4F`, Deep Ink text `#0B192C`, Brand Teal `#0D9488` / `#0F766E`, Warm Amber `#F59E0B`, Clean Slate `#F8FAFC` (thay thế rêu cũ `#3C7D7C` bị nhạt) |
| D-12 | 2026-11-05 | Logo tạm dùng **PNG** (tối ưu kích thước); thay SVG khi có file gốc | `<img>` có width/height, bản 1x/2x WebP |
| D-13 | 2026-11-05 | Định vị Phase 1 theo tagline "Thẩm mỹ từ những đường nét nhỏ nhất", tập trung **gạch ốp lát** | Menu, home, copy xoay quanh gạch; keo chà ron để phase sau |
| D-14 | 2026-11-05 | Giữ `/san-pham/{slug}`, **không** bật `wc_remove_product_base` của Rank Math | Tránh query phân giải slug mỗi request, tránh xung đột URL |
| D-15 | 2026-11-05 | Cart/Checkout dùng **shortcode classic**, không dùng Cart/Checkout block | Nhẹ, dễ tùy biến trường địa chỉ VN 2 cấp |
| D-16 | 2026-11-05 | Địa chỉ VN **2 cấp: Tỉnh/TP (34) → Phường/Xã** | Theo sắp xếp đơn vị hành chính từ 01/07/2025 |
| D-17 | 2026-11-05 | Kiến trúc Component 4 lớp: `drv_ui()` PHP + `.l-*` Layout Primitives & `--_*` Scoped CSS + `data-drv-action` & Light DOM Custom Elements (`<drv-*>`) | Tái sử dụng 100% component với Vanilla CSS/JS, tự động gắn logic khi chèn DOM qua AJAX |
| D-18 | 2026-11-05 | Phân định màu chữ & Header Menu Cấp 2-3 | Header text & H1–H6: `#083A4F`; Chữ nội dung: `#0B192C`; Hover menu header: nền `#083A4F` chữ trắng; Menu Cấp 2-3: Flyout Desktop + Accordion Mobile; Mega Menu áp dụng **Phương án 2 (Live Dynamic Preview Cột 5 + Bottom Utility Strip)** |
| D-19 | 2026-11-05 | Kiến trúc Animation **Option A + B kết hợp** | **Option A:** 100% Hover/UI bằng Pure CSS GPU (`transform`, `opacity`, `--ease-out-expo`). **Option B:** Nạp nhẹ **Anime.js v3.2.2** (~6.5KB gzip) cho Staggered Grid Reveal (`[data-drv-stagger]`), Number Odometer trong `<drv-unit-calc>` và Spring Cart Bounce |

## 6. Giả định

- Mỗi SKU (mẫu + kích thước + bề mặt) là 1 simple product, 1 URL.
- Giá niêm yết đã gồm VAT (nếu không, cần bổ sung cấu hình thuế).
- Chủ dự án cung cấp ảnh sản phẩm thật (ảnh viên gạch + ảnh phối cảnh) và dữ liệu thông số.
- Một ngôn ngữ (tiếng Việt).

## 7. Việc còn mở (cần chủ dự án cung cấp)

| # | Hạng mục | Cần cho phase |
|---|---|---|
| O-01 | Logo SVG gốc (thay PNG) | Khi có |
| O-02 | Thông tin tài khoản ngân hàng (ngân hàng, số TK, chủ TK) cho VietQR | 5 |
| O-03 | Hotline, Zalo OA/số Zalo, địa chỉ + giờ mở cửa showroom, Google Maps | 1 |
| O-04 | Nội dung chính sách: đổi trả, vận chuyển, thanh toán, bảo mật, bảo hành | 5 |
| O-05 | Dữ liệu sản phẩm (CSV) + ảnh; quy cách đóng thùng từng SKU | 2 |
| O-06 | Bộ dữ liệu Phường/Xã chính thức sau sắp xếp (nguồn Tổng cục Thống kê / Cổng dữ liệu quốc gia) | 5 |
| O-07 | Nội dung SEO cho 28 collection (tóm tắt, footer, FAQ) | 3 |
| O-08 | Favicon (tạo từ ký tự "D" của logo khi có SVG) | 1 |

## 8. Thuật ngữ

| Thuật ngữ | Nghĩa |
|---|---|
| Collection / Smart Collection | 1 trong 28 URL danh mục tĩnh (term `product_cat`) |
| Silo | Cấu trúc chủ đề `/gach` → 27 collection → sản phẩm, liên kết nội bộ khép kín |
| Facet | Một bộ lọc (FLT-01..14) |
| Chiều lọc 1 / 2+ | Số thuộc tính đang được chọn: chiều 1 = URL tĩnh, chiều 2+ = `?param` |
| Primary category | Danh mục chính quyết định breadcrumb sản phẩm (`rank_math_primary_product_cat`) |
| SSR | Server-side render: HTML đầy đủ từ PHP, không phụ thuộc JS |
| GEO | Generative Engine Optimization: tối ưu để AI (ChatGPT, Gemini, Perplexity) đọc và trích dẫn |

