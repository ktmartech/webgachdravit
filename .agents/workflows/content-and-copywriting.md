---
description: Quy trình viết và kiểm duyệt nội dung thương hiệu DRAVIT (Trang chủ, 28 Collection, Sản phẩm, Cẩm nang, Microcopy)
---

# Brand Voice & Content Creation Workflow — DRAVIT

> **Docs bắt buộc đọc:** `docs/10_Brand_Voice_And_Content_Guide.md`, `docs/06_SEO_GEO_Spec.md`
> **Rules:** `.agents/rules/dravit-frontend-ui.md`, `.agents/rules/dravit-coding-standards.md`
> **Skills:** `copywriting`, `content-creator`, `seo-content-writer`, `avoid-ai-writing`

---

## BƯỚC 1: XÁC ĐỊNH NGỮ CẢNH & TONE TRƯỚC KHI VIẾT

Luôn giữ **6 trụ cột Brand Voice cố định** (*Chân thật · Mộc mạc · Vững chãi · Trách nhiệm · Chuyên gia · Đáng tin*) và chọn đúng **Tone** theo điểm chạm:

| Điểm chạm cần viết | Tone áp dụng | Nguyên tắc cốt lõi |
|---|---|---|
| **Trang chủ, 28 Danh mục, Trang Sản phẩm, Chính sách** | **Chỉn chu** | Cấu trúc rõ ràng, con số chính xác (`mm`, `%`, `đ/m²`, `đ/viên`), giải thích thông số kỹ thuật thành lợi ích sử dụng thực tế cho cả chủ nhà và thợ thầu. |
| **Bài viết Cẩm nang (`/cam-nang/*`)** | **Gần gũi** | Chia sẻ kinh nghiệm chọn gạch, phối màu và thi công thực tế như một người làm nghề lành nghề. |
| **Giỏ hàng, Checkout, Email đơn hàng, Tư vấn Zalo** | **Thấu hiểu** | Chủ động giải thích lý do tính hao hụt 5–10%, độ lệch màu theo lô (shade) và lý do báo phí vận chuyển sau khi xác nhận trọng lượng đơn. |
| **Banner, Trang `/gach-uu-dai`** | **Trực diện** | Nêu thẳng mức giảm, lý do ưu đãi (lẻ lô / xả mẫu trưng bày), số lượng `m²` còn tại kho. |

---

## BƯỚC 2: KHUNG VIẾT CHO TỪNG LOẠI NỘI DUNG

### 1. Trang Danh mục (28 Smart Collections)
- **H1:** Lấy chính xác từ cột `H1 Chuẩn SEO` trong `docs/branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.md`.
- **Đoạn Intro (`drv_intro` — tối đa 60 chữ):**
  `[Định nghĩa ngắn & ứng dụng] + [2 thông số kỹ thuật cốt lõi của nhóm] + [Gợi ý chọn nhanh kích thước/bề mặt bên dưới].`
- **Bài SEO Footer (`drv_seo_footer` — 600 đến 1.200 chữ, 4 phần H2):**
  1. `Tiêu chí kỹ thuật khi chọn [Tên danh mục]` (độ hút nước, bề mặt, chỉ số chống trượt `R`, độ dày).
  2. `Kích thước & cách phối cho từng diện tích` (kèm link nội bộ `IL-01`/`IL-02`).
  3. `Cách tính số lượng gạch & lưu ý thi công đường ron` (nhắc quy đổi `m² ⇄ viên/thùng`, độ rộng ron 1,5–2mm).
  4. `Báo giá [Tên danh mục] tại DRAVIT` (khoảng giá tham khảo theo `đ/m²` + lời mời xem mẫu tại showroom).
- **Khối FAQ (`drv_faq` — 4 đến 6 câu):** Câu đầu tiên của mỗi câu trả lời phải trả lời trực diện câu hỏi (chuẩn GEO).

### 2. Trang Chi tiết Sản phẩm
- **Tên sản phẩm:** `[Công năng] Dravit [Vân/Chất liệu] [Tên mẫu/Màu] [Kích thước cm] [Bề mặt]`.
- **Mô tả ngắn (2–3 câu):** Câu 1 nói về xương gạch & hiệu ứng vân/bề mặt; Câu 2 nói về độ hút nước, chống trượt/chịu lực & không gian phù hợp; Câu 3 nói về cạnh mài (rectified) & đường ron khuyến nghị.
- **Điểm nổi bật (3–5 ý):** Viết theo công thức `[Thông số kỹ thuật] → [Lợi ích thực tế khi ở / thi công]`.

### 3. Bài viết Cẩm nang (`/cam-nang/{slug}`)
- **Đầu bài:** Hộp **Những điểm chính (Key Takeaways)** gồm 3–4 gạch đầu dòng tóm tắt câu trả lời cốt lõi + **1 link văn bản** trỏ về Collection mục tiêu trong 150 chữ đầu (`IL-05`).
- **Thân bài:** Có bảng so sánh hoặc con số định lượng; gắn **Shop the Look Bar** dưới chân ảnh phối cảnh và nhúng `[drv_products]` ở giữa bài.
- **Cuối bài:** Khối FAQ liên quan + Checklist 3 điều cần nhớ trước khi mua/thi công.

---

## BƯỚC 3: LỌC BỎ VĂN PHONG AI & KIỂM DUYỆT (`avoid-ai-writing`)

Chạy rà soát lần cuối trước khi xuất bản:
- [ ] **Xóa toàn bộ từ sáo rỗng/phóng đại:** *siêu phẩm, tuyệt tác, đẳng cấp thượng lưu, xa hoa, vĩnh cửu, bất diệt, trong bối cảnh hiện đại ngày nay, nâng tầm không gian sống, giải pháp toàn diện*.
- [ ] **Định dạng số chuẩn Việt Nam:** Tiền tệ dùng dấu chấm (`385.000đ/m²`), số thập phân dùng dấu phẩy (`0,5%`, `1,92 m²`), diện tích dùng `m²` có khoảng trắng trước đơn vị, không dùng em-dash `—` trên UI.
- [ ] **Không bịa đặt:** Không tự chế số liệu thống kê, đánh giá khách hàng hoặc cam kết không có thật.

