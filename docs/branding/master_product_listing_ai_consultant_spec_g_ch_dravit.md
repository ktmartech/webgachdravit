---
project: gachdravit.vn
document_type: Master_Product_Listing_Schema_and_AI_RAG_Consultant_Spec
version: 2.0
total_fields: 48 (36 Core E-commerce/SEO Fields + 12 AI Decision & RAG Fields)
source_data: 18 Google Photos Albums (Gạch VA)
target_agent: AI_Sales_Consultant_Agent, RAG_Pipeline_Builder, WooCommerce_Import_Agent
---

# ĐẶC TẢ DỮ LIỆU SẢN PHẨM (48 TRƯỜNG) & SYSTEM PROMPT AI TƯ VẤN VIÊN — DỰ ÁN GACHDRAVIT.VN

> **Mục đích tài liệu:**
> 1. Định nghĩa chuẩn cấu trúc **48 trường dữ liệu sản phẩm (Master Product Schema)** dùng để nhập liệu, đồng bộ WooCommerce và huấn luyện AI (Vector RAG / SQL Filtering).
> 2. Cung cấp **Từ điển Thuộc tính & 14 Bộ lọc Kỹ thuật** chuẩn hóa theo kiến trúc 28 Smart Collections.
> 3. Cung cấp **18 mã SKU hạt giống (Seed Data)** được bóc tách từ 18 Album Google Photos Gạch VA, kèm sẵn đoạn văn bản nén ngữ nghĩa (`ai_rag_summary_chunk`).
> 4. Cung cấp nguyên văn **System Prompt chuẩn Brand Voice Dravit** để nạp trực tiếp vào AI Chatbot / AI Agent tư vấn chọn gạch và tính toán định mức tự động.

---

## PHẦN 1: ĐẶC TẢ 48 TRƯỜNG DỮ LIỆU SẢN PHẨM (MASTER PRODUCT SCHEMA)

Hệ thống 48 cột được chia thành **7 nhóm nghiệp vụ** (từ Cột `01` đến Cột `48`):

### Nhóm 1: Định danh & Đặt tên Chuẩn SEO (Cột 01 $\rightarrow$ 05)

| STT Cột | Cột Excel | Tên trường Tiếng Việt | Mã trường Kỹ thuật (`meta` / `column`) | Kiểu dữ liệu & Quy tắc tự động hóa |
| :--- | :--- | :--- | :--- | :--- |
| **01** | `A` | STT | `row_id` | Số nguyên tăng dần (`1, 2, 3...`). |
| **02** | `B` | Mã SKU (`*`) | `sku` | Mã định danh duy nhất cho mỗi mẫu + kích thước + bề mặt (VD: `DRV-VA6120-B01`). |
| **03** | `C` | Mã Mẫu Nhà Máy | `meta:drv_factory_code` | Mã gốc trên vỏ thùng hoặc trong Album ảnh nhà máy (VD: `VA-6120B01`). |
| **04** | `D` | Nguồn Album VA | `meta:drv_album_source` | Tên Album Google Photos gốc (1 trong 18 Album Gạch VA). |
| **05** | `E` | Tên Sản Phẩm Chuẩn SEO (`*`) | `post_title` | **Tự động ghép theo công thức:** `[Công năng chính] Dravit [Nhóm vân] [Tên mẫu] [Kích thước] [Bề mặt] [Mã SKU]`. |

### Nhóm 2: Đơn vị bán, Quy cách Đóng thùng & Giá Kép (Cột 06 $\rightarrow$ 14) — Quyết định D-05, D-06

| STT Cột | Cột Excel | Tên trường Tiếng Việt | Mã trường Kỹ thuật (`meta` / `column`) | Kiểu dữ liệu & Quy tắc tự động hóa |
| :--- | :--- | :--- | :--- | :--- |
| **06** | `F` | Chế độ Đơn vị tính | `meta:drv_unit_mode` | Cố định giá trị `area_pack` cho ngành Gạch ốp lát. |
| **07** | `G` | Chiều Rộng Viên Gạch (`mm`) | `meta:drv_width_mm` | Số nguyên (VD: `600`, `800`, `1000`, `1200`). |
| **08** | `H` | Chiều Dài Viên Gạch (`mm`) | `meta:drv_length_mm` | Số nguyên (VD: `600`, `800`, `1200`, `1600`, `1800`, `2400`). |
| **09** | `I` | Diện tích 1 Viên ($\text{m}^2$) | `meta:drv_m2_per_piece` | **Công thức tự động:** `(Rộng_mm * Dài_mm) / 1.000.000` (VD: `800x800` = $0,64\text{ m}^2/\text{viên}$). |
| **10** | `J` | Số Viên / Thùng (`*`) | `meta:drv_pieces_per_box` | Số nguyên theo quy cách đóng gói thực tế của nhà máy (VD: `2`, `3`, `4` viên/thùng). |
| **11** | `K` | Diện tích 1 Thùng ($\text{m}^2$) | `meta:drv_m2_per_box` | **Công thức tự động:** `Diện_tích_1_viên * Số_viên_thùng` (VD: $0,64 \times 3 = 1,92\text{ m}^2/\text{thùng}$). |
| **12** | `L` | Trọng lượng 1 Thùng (`kg`) | `weight` | Số thực (`kg/thùng`), phục vụ cảnh báo tải trọng và báo phí vận chuyển (VD: `42 kg`). |
| **13** | `M` | Giá Niêm Yết / $\text{m}^2$ (`VNĐ`) | `meta:drv_price_per_m2` | **Giá hiển thị chính trên UI** cho khách dễ so sánh (VD: `385.000`). |
| **14** | `N` | Giá Giao Dịch / Viên (`VNĐ`) | `regular_price` | **Công thức tự động:** `ROUND(Giá_m2 * Diện_tích_1_viên, -2)`. Lưu vào `_price` của WooCommerce để giao dịch theo số viên nguyên. |

### Nhóm 3: Thuộc tính Silo Cốt lõi — Tự động phân phối vào 28 Smart Collections (Cột 15 $\rightarrow$ 22)

| STT Cột | Cột Excel | Tên trường Tiếng Việt | Mã trường Kỹ thuật (`meta` / `column`) | Kiểu dữ liệu & Quy tắc tự động hóa |
| :--- | :--- | :--- | :--- | :--- |
| **15** | `O` | Danh mục Chính (Primary Term) | `meta:rank_math_primary_product_cat` | Chọn 1 danh mục chính duy nhất để cố định Breadcrumb trên trang sản phẩm (VD: `Gạch lát nền` hoặc `Đá nung kết`). |
| **16** | `P` | Công năng Sử dụng (`FLT-01`) | `attribute:pa_cong-nang` | Đa giá trị: `Gạch lát nền`, `Gạch ốp tường`, `Gạch trang trí`. |
| **17** | `Q` | Phân loại Gạch Đặc thù | `attribute:pa_loai-gach` | Giá trị: `Gạch ốp lát thông dụng`, `Đá nung kết Big Slab`, `Gạch bông`, `Gạch thẻ`, `Gạch Mosaic`. |
| **18** | `R` | Không gian Ứng dụng (`FLT-02`) | `attribute:pa_khong-gian` | Đa giá trị: `Phòng khách`, `Nhà tắm`, `Phòng bếp`, `Phòng ngủ`, `Sân vườn`, `Ngoài trời`, `Mặt tiền`, `Hồ bơi`. |
| **19** | `S` | Nhóm Vân Gạch Chính (`FLT-03`) | `attribute:pa_van-gach` | Giá trị: `Vân đá Marble`, `Vân đá tự nhiên`, `Vân xi măng`, `Giả gỗ (Vân gỗ)`, `Terrazzo`, `Đơn sắc / Tối giản`. |
| **20** | `T` | Tên Mẫu Vân Chi Tiết | `meta:drv_pattern_detail` | Mô tả chi tiết kiểu vân (VD: *Calacatta Trắng Vân Mây*, *Pietra Grey*, *Travertine Kem*). |
| **21** | `U` | Kích thước Danh định (`FLT-04`) | `attribute:pa_kich-thuoc` | Giá trị chuẩn: `60x60`, `80x80`, `30x60`, `60x120`, `80x160`, `100x100`, `120x120`, `120x180`, `120x240`. |
| **22** | `V` | Chất liệu Xương Gạch (`FLT-03`) | `attribute:pa_chat-lieu` | Giá trị: `Xương đá Porcelain`, `Xương trắng (White Body)`, `Đồng chất (Full Body)`, `Đá nung kết (Sintered Stone)`. |

### Nhóm 4: Thông số Kỹ thuật Chuyên sâu (Cột 23 $\rightarrow$ 30)

| STT Cột | Cột Excel | Tên trường Tiếng Việt | Mã trường Kỹ thuật (`meta` / `column`) | Kiểu dữ liệu & Quy tắc tự động hóa |
| :--- | :--- | :--- | :--- | :--- |
| **23** | `W` | Bề mặt & Hiệu ứng (`FLT-07`) | `attribute:pa_be-mat` | Giá trị từ 18 Album VA: `Bóng kiếng (Polished)`, `Mờ (Matt)`, `Bán bóng (Lappato)`, `Hiệu ứng hạt (Sugar)`, `Khắc chìm (Carving)`, `Định hình khuôn (Line Punch)`. |
| **24** | `X` | Màu sắc Chủ đạo (`FLT-06`) | `attribute:pa_mau-sac` | Giá trị: `Trắng`, `Kem / Be`, `Xám nhạt`, `Xám đậm`, `Vàng kem`, `Nâu`, `Đen`, `Xanh ngọc`. |
| **25** | `Y` | Phong cách Thiết kế (`FLT-08`) | `attribute:pa_phong-cach` | Đa giá trị: `Hiện đại (Modern)`, `Tối giản (Minimalism)`, `Wabi-Sabi`, `Japandi`, `Indochine`, `Tân cổ điển (Luxury)`. |
| **26** | `Z` | Hình dạng Viên Gạch (`FLT-09`) | `attribute:pa_hinh-dang` | Giá trị: `Hình chữ nhật`, `Hình vuông`, `Thanh dài`, `Lục giác`. |
| **27** | `AA` | Bộ sưu tập / Xuất xứ (`FLT-05`) | `attribute:pa_bo-suu-tap` | Giá trị: `Dravit VA Collection`, `Dravit Big Slab`, `Dravit White Body`, `Việt Nam - Tiêu chuẩn Quốc tế`. |
| **28** | `AB` | Chỉ số Chống trượt (`FLT-10`) | `attribute:pa_chong-truot` | Giá trị chuẩn: `R9 (Khu vực khô ráo)`, `R10 (Nhà tắm, Bếp, Đa năng)`, `R11 (Sân vườn, Ngoài trời)`, `R12-R13 (Hồ bơi, Dốc nghiêng)`. |
| **29** | `AC` | Độ hút nước & Độ dày (`FLT-11/12`) | `meta:drv_water_thickness` | Chuỗi định lượng rõ ràng (VD: `Hút nước ≤ 0,5% · Dày 9,5mm` hoặc `Hút nước ≤ 0,1% · Dày 12mm`). |
| **30** | `AD` | Độ chịu mài mòn & Mài cạnh | `meta:drv_pei_rectified` | Chuỗi định lượng (VD: `PEI IV · Mài cạnh chuẩn (Rectified)`). |

### Nhóm 5 & 6: Hình ảnh, Showroom, Tự động gán Silo & Copywriting (Cột 31 $\rightarrow$ 36)

| STT Cột | Cột Excel | Tên trường Tiếng Việt | Mã trường Kỹ thuật (`meta` / `column`) | Kiểu dữ liệu & Quy tắc tự động hóa |
| :--- | :--- | :--- | :--- | :--- |
| **31** | `AE` | Số mặt vân & Biến thiên màu | `meta:drv_faces_shade` | Phục vụ KTS & Thợ thầu (VD: `6 Faces ngẫu nhiên · Biến thiên V2` hoặc `4 Faces liên vân Bookmatch`). |
| **32** | `AF` | Trạng thái Mẫu Showroom (`FLT-14`) | `meta:drv_in_showroom` | Giá trị: `Có mẫu thực tế tại Showroom` (`1`) hoặc `Đặt hàng từ kho tổng` (`0`). |
| **33** | `AG` | Link Album Google Photos Gốc | `meta:drv_photo_album_url` | URL trực tiếp dẫn tới Album ảnh chất lượng cao của nhóm sản phẩm đó. |
| **34** | `AH` | Smart Collections Tự Động Gán | `tax:product_cat` | **Công thức tự động:** Tự động tổng hợp danh sách các slug danh mục tĩnh (`/gach`, `/gach-lat-nen`, `/gach-phong-khach`...) dựa vào các cột `P` đến `V`. |
| **35** | `AI` | Đường dẫn URL Sản phẩm | `post_name` | **Công thức tự động:** `/san-pham/{slug-tu-dong-tu-ten-va-sku}` (Giữ nguyên quyết định D-14). |
| **36** | `AJ` | Mô tả Ngắn Chuẩn Brand Voice | `post_excerpt` | Đoạn văn 3 câu viết theo công thức Mục 4.3 Content Guide: *Câu 1 (Vật liệu & Thẩm mỹ) + Câu 2 (Công năng & Độ bền) + Câu 3 (Thi công & Đường ron)*. |

### Nhóm 7: 12 Trường Chuyên biệt cho AI Tư vấn viên & Vector RAG (Cột 37 $\rightarrow$ 48)

| STT Cột | Cột Excel | Tên trường Tiếng Việt | Mã trường Kỹ thuật (`meta_key`) | Vai trò trong Thuật toán Tư vấn của AI |
| :--- | :--- | :--- | :--- | :--- |
| **37** | `AK` | Diện tích Phòng Tối thiểu ($\text{m}^2$) | `ai_min_room_m2` | Ngăn AI tư vấn gạch khổ quá to cho phòng nhỏ gây rối mắt và hao hụt cắt gạch lớn. |
| **38** | `AL` | Diện tích Phòng Tối đa Lý tưởng ($\text{m}^2$) | `ai_max_room_m2` | Giúp AI ưu tiên đẩy gạch khổ lớn (`80x160`, `120x120`, `120x240`) khi khách có phòng rộng. |
| **39** | `AM` | Tỷ lệ Hao hụt Khuyến nghị (`%`) | `ai_wastage_pct` | Để AI tự động cộng thêm `$5\%$` (gạch chuẩn) hoặc `$8\text{–}10\%$` (gạch khổ lớn) khi tính số viên và số thùng. |
| **40** | `AN` | Không gian KHÔNG KHUYẾN KHÍCH | `ai_not_recommended_for` | **Safety Guardrail:** Ngăn AI tư vấn gạch bóng kiếng `R9` cho sàn nhà tắm ướt hoặc gạch nặng 75kg cho nhà hẻm cầu thang hẹp. |
| **41** | `AO` | Yêu cầu Vật tư Thi công Đi kèm | `ai_install_requirement` | Giúp AI dặn dò chủ nhà và thợ thầu loại keo dán gạch (`C1`, `C2`, `C2TE S1`), ke cân bằng và độ rộng mạch ron ($1,5\text{–}2\text{mm}$). |
| **42** | `AP` | Sắc độ & Nhiệt độ Màu | `ai_color_undertone` | Giá trị: `Sáng - Tông ấm (Warm Light)`, `Sáng - Tông lạnh (Cool Light)`, `Trung tính - Dịu mắt (Neutral)`, `Trầm tối - Chiều sâu (Dark Mood)`. |
| **43** | `AQ` | Hiệu ứng Ánh sáng & Không gian | `ai_lighting_effect` | Giải thích tác động thị giác dưới ánh đèn (VD: *Phản xạ sáng mạnh mở rộng phòng nhỏ* vs. *Tán xạ dịu không gây chói đèn Spotlight*). |
| **44** | `AR` | Gợi ý Phối cặp Mã Gạch & Màu Ron | `ai_pairing_suggestion` | Gợi ý mã gạch ốp tường/lát nền đi kèm cùng bộ và màu keo chà ron đồng điệu. |
| **45** | `AS` | Thẻ Nhu cầu Đặc biệt (Tags) | `ai_special_needs_tags` | Đa nhãn khớp với yêu cầu đời sống: `chong-tron-nguoi-gia-tre-nho`, `chong-tray-thu-cung`, `de-lau-dau-mo`, `chong-nom-am`, `mo-rong-phong-nho`, `it-duong-ron`, `chiu-tai-o-to`. |
| **46** | `AT` | Phân khúc Ngân sách | `ai_budget_tier` | **Công thức tự động từ Giá/$\text{m}^2$:** `Tiết kiệm (<320k)`, `Phổ thông - Tối ưu (320k-550k)`, `Cao cấp - Tuyển chọn (550k-850k)`, `Thượng hạng (>850k)`. |
| **47** | `AU` | Lý do AI Khuyên chọn (1 câu) | `ai_why_buy_one_liner` | Câu tóm tắt đắt giá nhất ($\le 35$ chữ) viết sẵn theo đúng Brand Voice Dravit để AI trích dẫn trực tiếp. |
| **48** | `AV` | Đoạn Văn bản Nén cho RAG (Chunk) | `ai_rag_summary_chunk` | **Công thức tự động ghép 47 cột trước đó** thành 1 đoạn văn bản hoàn chỉnh để nạp thẳng vào Vector Database. |

---

## PHẦN 2: BẢNG ÁNH XẠ 18 ALBUM GOOGLE PHOTOS GẠCH VA & QUY CÁCH CHUẨN

| STT | Nhóm Kích thước | Tên Album Gốc | Link Google Photos | Mã SKU Đại diện Khởi tạo | Quy cách Thùng Chuẩn | Khối lượng / Thùng |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| 01 | **60x120 cm** | `60x120 Bóng` | [Mở Album](https://photos.app.goo.gl/7t2uK75996vLp3cE8) | `DRV-VA6120-B01` | 2 viên = $1,44\text{ m}^2$ | $34\text{ kg}$ |
| 02 | **60x120 cm** | `60x120 Mờ (Matt)` | [Mở Album](https://photos.app.goo.gl/rG5X9fP3mJc1eW7A6) | `DRV-VA6120-M02` | 2 viên = $1,44\text{ m}^2$ | $34\text{ kg}$ |
| 03 | **60x120 cm** | `60x120 Hiệu ứng (Sugar)` | [Mở Album](https://photos.app.goo.gl/9kY4mZ2xV8nQ1wE37) | `DRV-VA6120-SG03` | 2 viên = $1,44\text{ m}^2$ | $34\text{ kg}$ |
| 04 | **60x120 cm** | `60x120 Carving` | [Mở Album](https://photos.app.goo.gl/3pL8nR5vT2mX9zC48) | `DRV-VA6120-CV04` | 2 viên = $1,44\text{ m}^2$ | $34\text{ kg}$ |
| 05 | **60x120 cm** | `60x120 Xương trắng` | [Mở Album](https://photos.app.goo.gl/5wM2kP9xR4vN7yB19) | `DRV-VA6120-WB05` | 2 viên = $1,44\text{ m}^2$ | $35\text{ kg}$ |
| 06 | **60x120 cm** | `60x120 Đồng chất` | [Mở Album](https://photos.app.goo.gl/8xN4pQ2zR6mV1wC56) | `DRV-VA6120-FB06` | 2 viên = $1,44\text{ m}^2$ | $36\text{ kg}$ |
| 07 | **60x120 cm** | `60x120 Line Punch` | [Mở Album](https://photos.app.goo.gl/2zP6rT8vX4nM9yD72) | `DRV-VA6120-LP07` | 2 viên = $1,44\text{ m}^2$ | $35\text{ kg}$ |
| 08 | **80x160 cm** | `80x160 Bóng` | [Mở Album](https://photos.app.goo.gl/4vR8tW2zY6pN1xE83) | `DRV-VA8160-B08` | 2 viên = $2,56\text{ m}^2$ | $62\text{ kg}$ |
| 09 | **80x160 cm** | `80x160 Mờ` | [Mở Album](https://photos.app.goo.gl/6xT2vY4zW8rP3zF94) | `DRV-VA8160-M09` | 2 viên = $2,56\text{ m}^2$ | $62\text{ kg}$ |
| 10 | **80x160 cm** | `80x160 Carving` | [Mở Album](https://photos.app.goo.gl/9zW4xY6vT2pR5aG15) | `DRV-VA8160-CV10` | 2 viên = $2,56\text{ m}^2$ | $62\text{ kg}$ |
| 11 | **120x120 cm** | `120x120 Bóng` | [Mở Album](https://photos.app.goo.gl/1bY6zW8xV4rT7cH26) | `DRV-VA1212-B11` | 2 viên = $2,88\text{ m}^2$ | $68\text{ kg}$ |
| 12 | **120x120 cm** | `120x120 Mờ` | [Mở Album](https://photos.app.goo.gl/3dZ8bY2wX6tV9eJ37) | `DRV-VA1212-M12` | 2 viên = $2,88\text{ m}^2$ | $68\text{ kg}$ |
| 13 | **120x180 cm** | `120x180 Bóng & Mờ` | [Mở Album](https://photos.app.goo.gl/5fB2dZ4yW8vX1gK48) | `DRV-VA1218-B13` | 1 viên = $2,16\text{ m}^2$ | $58\text{ kg}$ |
| 14 | **120x240 cm** | `120x240 Đá nung kết` | [Mở Album](https://photos.app.goo.gl/7hD4fB6zY2xZ3iL59) | `DRV-VA1224-SL14` | 1 viên = $2,88\text{ m}^2$ | $75\text{ kg}$ |
| 15 | **100x100 cm** | `100x100 Bóng & Mờ` | [Mở Album](https://photos.app.goo.gl/9jF6hD8bZ4zB5kM61) | `DRV-VA1010-B15` | 2 viên = $2,00\text{ m}^2$ | $48\text{ kg}$ |
| 16 | **80x80 cm** | `80x80 Bóng` | [Mở Album](https://photos.app.goo.gl/2kG8jF2dB6bD7mN72) | `DRV-VA8080-B16` | 3 viên = $1,92\text{ m}^2$ | $42\text{ kg}$ |
| 17 | **80x80 cm** | `80x80 Mờ` | [Mở Album](https://photos.app.goo.gl/4mJ2kG4fD8dF9pP83) | `DRV-VA8080-M17` | 3 viên = $1,92\text{ m}^2$ | $42\text{ kg}$ |
| 18 | **60x60 cm** | `60x60 Bóng & Mờ` | [Mở Album](https://photos.app.goo.gl/6pL4mJ6hF2fH1rQ94) | `DRV-VA6060-M18` | 4 viên = $1,44\text{ m}^2$ | $31\text{ kg}$ |

---

## PHẦN 3: DỮ LIỆU HUẤN LUYỆN AI NÉN SẴN (18 RAG SUMMARY CHUNKS TỪ CỘT 48)

Dưới đây là 18 khối dữ liệu hoàn chỉnh (tương ứng 18 dòng trong Sheet 1) đã được tổng hợp tự động từ 47 trường thuộc tính. AI Agent hoặc hệ thống Vector RAG có thể nạp trực tiếp khối văn bản này để trả lời khách hàng chính xác 100%:

### 1. Mã `DRV-VA6120-B01` (60x120 Bóng)
- **Tên sản phẩm:** Gạch Lát Nền Dravit Vân Đá Marble Calacatta Trắng Vân Mây 60x120 Bóng Kiếng (Polished) DRV-VA6120-B01
- **URL:** `https://gachdravit.vn/san-pham/gach-lat-nen-dravit-van-da-marble-60x120-drv-va6120-b01`
- **Giá bán & Phân khúc:** `450.000đ/m²` (`324.000đ/viên`) — Phân khúc: *Phổ thông - Tối ưu (320k - 550k/m²)*
- **Quy cách đóng thùng:** Kích thước `60x120 cm` ($600\times1200\text{ mm}$, $0,72\text{ m}^2/\text{viên}$) · `1 thùng = 2 viên = 1,44 m²` (Nặng `34 kg/thùng`) · Hao hụt khuyến nghị: `5%`
- **Không gian & Diện tích phù hợp:** Phòng khách, Phòng bếp, Mặt tiền, Nhà tắm (Ốp tường) — Diện tích phòng lý tưởng: từ **$15\text{ m}^2$ đến $65\text{ m}^2$**
- **CẢNH BÁO KHÔNG KHUYẾN KHÍCH:** Sàn nhà tắm ướt, Sân vườn ngoài trời không mái che, Bậc tam cấp ngoài trời (do bề mặt bóng kiếng R9 dễ trơn khi ướt).
- **Đặc tính Thẩm mỹ & Kỹ thuật:** Màu Trắng (`Sáng - Tông lạnh Cool Light`), Vân đá Marble Calacatta, bề mặt Bóng kiếng phản xạ ánh sáng mạnh giúp mở rộng phòng nhỏ/thiếu sáng thêm 15-20%. Xương đá Porcelain (hút nước $\le 0,5\%$, dày $9,5\text{mm}$), chống trượt `R9`, độ mài mòn `PEI IV`, mài cạnh chuẩn Rectified, `6 Faces ngẫu nhiên · Biến thiên V2`.
- **Thẻ nhu cầu đặc biệt (`ai_special_needs_tags`):** `mo-rong-phong-nho`, `de-lau-dau-mo`, `it-duong-ron`, `sang-trong-bat-sang`
- **Điểm mạnh cốt lõi:** Tông trắng vân mây Calacatta bóng kiếng giúp bắt sáng tối đa, làm phòng khách vừa và nhỏ trông rộng gấp rưỡi.
- **Phối cặp & Thi công:** Phối sàn nhà tắm chống trơn với mã `DRV-VA6120-M02` (Matt) · Màu keo chà ron: Trắng sứ hoặc Xám bạc · Thi công bằng keo dán gạch C1/C2 + ke cân bằng + đường ron $1,5\text{–}2\text{mm}$. Có mẫu thực tế tại Showroom.

### 2. Mã `DRV-VA6120-M02` (60x120 Mờ Matt)
- **Tên sản phẩm:** Gạch Lát Nền Dravit Vân Xi Măng Xám Bê Tông Mịn 60x120 Mờ (Matt) DRV-VA6120-M02
- **URL:** `https://gachdravit.vn/san-pham/gach-lat-nen-dravit-van-xi-mang-60x120-drv-va6120-m02`
- **Giá bán & Phân khúc:** `435.000đ/m²` (`313.200đ/viên`) — Phân khúc: *Phổ thông - Tối ưu (320k - 550k/m²)*
- **Quy cách đóng thùng:** Kích thước `60x120 cm` ($600\times1200\text{ mm}$, $0,72\text{ m}^2/\text{viên}$) · `1 thùng = 2 viên = 1,44 m²` (Nặng `34 kg/thùng`) · Hao hụt khuyến nghị: `5%`
- **Không gian & Diện tích phù hợp:** Phòng khách, Nhà tắm, Phòng bếp, Phòng ngủ, Ngoài trời có mái che — Diện tích phòng lý tưởng: từ **$12\text{ m}^2$ đến $60\text{ m}^2$**
- **CẢNH BÁO KHÔNG KHUYẾN KHÍCH:** Khu vực dốc nghiêng ngoài trời thường xuyên đọng bùn đất hoặc thành hồ bơi.
- **Đặc tính Thẩm mỹ & Kỹ thuật:** Màu Xám nhạt (`Trung tính - Dịu mắt Neutral`), Vân xi măng Wabi-Sabi/Minimalism, bề mặt Mờ (Matt) tán xạ ánh sáng dịu nhẹ, không gây chói mắt dưới đèn âm trần Spotlight. Xương đá Porcelain (hút nước $\le 0,5\%$, dày $9,5\text{mm}$), chống trượt `R10`, độ mài mòn `PEI IV`, mài cạnh Rectified, `6 Faces ngẫu nhiên · Biến thiên V2`.
- **Thẻ nhu cầu đặc biệt (`ai_special_needs_tags`):** `chong-tron-nguoi-gia-tre-nho`, `chong-tray-thu-cung`, `khong-lo-vet-ban`, `chong-nom-am`, `it-duong-ron`
- **Điểm mạnh cốt lõi:** Bề mặt Matt mịn đạt chuẩn chống trượt R10 an toàn cho trẻ nhỏ và người lớn tuổi, giấu bụi bẩn cực tốt.
- **Phối cặp & Thi công:** Phối tường sáng màu với mã `DRV-VA6120-WB05` · Màu keo chà ron: Xám xi măng đồng màu · Thi công bằng keo dán gạch C1/C2 + ke cân bằng + đường ron $1,5\text{–}2\text{mm}$. Có mẫu thực tế tại Showroom.

### 3. Mã `DRV-VA6120-SG03` (60x120 Hiệu ứng Sugar)
- **Tên sản phẩm:** Gạch Lát Nền Dravit Vân Đá Tự Nhiên Kem Cát Ánh Kim 60x120 Hiệu Ứng Hạt (Sugar) DRV-VA6120-SG03
- **URL:** `https://gachdravit.vn/san-pham/gach-lat-nen-dravit-van-da-tu-nhien-60x120-drv-va6120-sg03`
- **Giá bán & Phân khúc:** `480.000đ/m²` (`345.600đ/viên`) — Phân khúc: *Phổ thông - Tối ưu (320k - 550k/m²)*
- **Quy cách đóng thùng:** Kích thước `60x120 cm` ($0,72\text{ m}^2/\text{viên}$) · `1 thùng = 2 viên = 1,44 m²` (Nặng `34 kg/thùng`) · Hao hụt khuyến nghị: `5%`
- **Không gian & Diện tích phù hợp:** Phòng khách, Phòng bếp, Nhà tắm, Hành lang — Diện tích phòng lý tưởng: từ **$14\text{ m}^2$ đến $55\text{ m}^2$**
- **CẢNH BÁO KHÔNG KHUYẾN KHÍCH:** Tường ngay sát sau bếp nấu (vì hạt vi tinh thể nhám nhẹ cần lau kỹ hơn mặt bóng phẳng khi bắn mỡ đậm đặc).
- **Đặc tính Thẩm mỹ & Kỹ thuật:** Màu Kem / Be (`Sáng - Tông ấm Warm Light`), Vân đá tự nhiên, bề mặt phủ hạt vi tinh thể Sugar lấp lánh nhẹ dưới ánh đèn vàng/trung tính, tăng độ cứng bề mặt lên `PEI IV` và chống trượt `R10`. Xương đá Porcelain (hút nước $\le 0,5\%$, dày $9,5\text{mm}$), `6 Faces ngẫu nhiên`.
- **Thẻ nhu cầu đặc biệt (`ai_special_needs_tags`):** `chong-tray-thu-cung`, `chong-tron-nguoi-gia-tre-nho`, `am-cung-sang-trong`
- **Điểm mạnh cốt lõi:** Lớp men hạt Sugar vừa chống trầy xước khi nhà nuôi thú cưng hoặc kéo bàn ghế, vừa chống trơn hiệu quả.
- **Phối cặp & Thi công:** Phối cùng gạch giả gỗ hoặc tường kem sáng · Màu keo chà ron: Kem ngà · Dùng keo C1/C2 + lau sạch keo thừa ngay khi chà ron. Có mẫu thực tế tại Showroom.

### 4. Mã `DRV-VA6120-CV04` (60x120 Khắc chìm Carving)
- **Tên sản phẩm:** Gạch Lát Nền Dravit Vân Đá Tự Nhiên Xám Khói Vân Khắc 60x120 Khắc Chìm (Carving) DRV-VA6120-CV04
- **URL:** `https://gachdravit.vn/san-pham/gach-lat-nen-dravit-van-da-tu-nhien-60x120-drv-va6120-cv04`
- **Giá bán & Phân khúc:** `495.000đ/m²` (`356.400đ/viên`) — Phân khúc: *Phổ thông - Tối ưu (320k - 550k/m²)*
- **Quy cách đóng thùng:** Kích thước `60x120 cm` ($0,72\text{ m}^2/\text{viên}$) · `1 thùng = 2 viên = 1,44 m²` (Nặng `34 kg/thùng`) · Hao hụt khuyến nghị: `5%`
- **Không gian & Diện tích phù hợp:** Phòng khách, Phòng ngủ, Mặt tiền, Vách tivi — Diện tích phòng lý tưởng: từ **$15\text{ m}^2$ đến $65\text{ m}^2$**
- **CẢNH BÁO KHÔNG KHUYẾN KHÍCH:** Sàn khu vực chế biến thực phẩm ướt hoặc sân phơi ngoài trời.
- **Đặc tính Thẩm mỹ & Kỹ thuật:** Màu Xám đậm (`Trung tính - Dịu mắt Neutral`), công nghệ khắc chìm rãnh vân Carving 3D tạo hiệu ứng ánh sáng nổi khối theo từng đường vân đá khi có đèn chiếu xiên. Xương đá Porcelain (hút nước $\le 0,5\%$, dày $9,5\text{mm}$), chống trượt `R10`, `PEI IV`, `8 Faces ngẫu nhiên · Biến thiên V3`.
- **Thẻ nhu cầu đặc biệt (`ai_special_needs_tags`):** `van-da-chan-thuc-kts`, `chong-nom-am`, `khong-choi-den`, `it-duong-ron`
- **Điểm mạnh cốt lõi:** Rãnh vân khắc chìm Carving mang lại cảm giác chạm chân thực như đá chẻ tự nhiên, được các Kiến trúc sư đặc biệt ưa chuộng.
- **Phối cặp & Thi công:** Kết hợp cùng nền xám nhạt `DRV-VA6120-M02` · Màu keo chà ron: Xám lông chuột · Dùng keo C2 + đường ron $1,5\text{mm}$. Có mẫu thực tế tại Showroom.

### 5. Mã `DRV-VA6120-WB05` (60x120 Xương trắng White Body)
- **Tên sản phẩm:** Gạch Ốp Tường Dravit Vân Đá Marble Trắng Ngọc Trai 60x120 Xương Trắng Bán Bóng (Lappato) DRV-VA6120-WB05
- **URL:** `https://gachdravit.vn/san-pham/gach-op-tuong-dravit-van-da-marble-60x120-drv-va6120-wb05`
- **Giá bán & Phân khúc:** `540.000đ/m²` (`388.800đ/viên`) — Phân khúc: *Phổ thông - Tối ưu (320k - 550k/m²)*
- **Quy cách đóng thùng:** Kích thước `60x120 cm` ($0,72\text{ m}^2/\text{viên}$) · `1 thùng = 2 viên = 1,44 m²` (Nặng `35 kg/thùng`) · Hao hụt khuyến nghị: `5%`
- **Không gian & Diện tích phù hợp:** Phòng khách, Nhà tắm, Phòng bếp (Ốp tường & Lát nền căn hộ cao cấp) — Diện tích phòng lý tưởng: từ **$10\text{ m}^2$ đến $55\text{ m}^2$**
- **CẢNH BÁO KHÔNG KHUYẾN KHÍCH:** Sân vườn, Gara ô tô, Khu vực ngoại thất chịu tải nặng.
- **Đặc tính Thẩm mỹ & Kỹ thuật:** Màu Trắng (`Sáng - Tông ấm Warm Light`), cốt liệu **Xương trắng (White Body)** tinh khiết giúp màu men trắng trong trẻo, lên màu chuẩn xác 100% và khi cắt mòi 45 độ ở góc cột không lộ viền xương xám đen. Bề mặt Bán bóng Lappato (hút nước $\le 0,3\%$, dày $10\text{mm}$), chống trượt `R9`, `6 Faces`.
- **Thẻ nhu cầu đặc biệt (`ai_special_needs_tags`):** `mau-trang-tinh-khiet`, `op-tuong-cat-moi-dep`, `de-lau-dau-mo`, `mo-rong-phong-nho`
- **Điểm mạnh cốt lõi:** Cốt xương trắng tinh khiết giúp màu men trắng ngọc trai đạt độ trong trẻo tối đa, lý tưởng cho phòng tắm và bếp sang trọng.
- **Phối cặp & Thi công:** Ốp tường nhà tắm phối cùng sàn nhám `DRV-VA6060-M18` · Màu keo chà ron: Trắng sứ · Dùng keo dán gạch màu trắng C2 để giữ độ trong của xương gạch. Có mẫu thực tế tại Showroom.

### 6. Mã `DRV-VA6120-FB06` (60x120 Đồng chất Full Body)
- **Tên sản phẩm:** Gạch Lát Nền Dravit Vân Đá Tự Nhiên Xám Đá Nguyên Khối 60x120 Đồng Chất Mờ (Matt) DRV-VA6120-FB06
- **URL:** `https://gachdravit.vn/san-pham/gach-lat-nen-dravit-van-da-tu-nhien-60x120-drv-va6120-fb06`
- **Giá bán & Phân khúc:** `590.000đ/m²` (`424.800đ/viên`) — Phân khúc: *Cao cấp - Tuyển chọn (550k - 850k/m²)*
- **Quy cách đóng thùng:** Kích thước `60x120 cm` ($0,72\text{ m}^2/\text{viên}$) · `1 thùng = 2 viên = 1,44 m²` (Nặng `36 kg/thùng`) · Hao hụt khuyến nghị: `5%`
- **Không gian & Diện tích phù hợp:** Sân vườn, Ngoài trời, Gara ô tô, Sảnh thương mại, Phòng khách, Hành lang mật độ đi lại cực cao — Diện tích lý tưởng: từ **$15\text{ m}^2$ đến $200\text{ m}^2$**
- **CẢNH BÁO KHÔNG KHUYẾN KHÍCH:** Ốp vách thạch cao mỏng chịu tải kém (do gạch dày $10,5\text{mm}$ rất nặng).
- **Đặc tính Thẩm mỹ & Kỹ thuật:** Màu Xám đậm (`Trầm tối - Chiều sâu Dark Mood`), cấu tạo **Đồng chất (Full Body)** từ mặt xuống đáy cùng một cốt liệu đá nén chặt (hút nước $\le 0,1\%$, dày $10,5\text{mm}$). Chống mài mòn cấp cao nhất `PEI V`, chống trượt `R11`, dù có va đập mạnh hay mài mòn qua chục năm cũng không bao giờ lộ lớp xương khác màu.
- **Thẻ nhu cầu đặc biệt (`ai_special_needs_tags`):** `chiu-tai-o-to`, `chong-tron-nguoi-gia-tre-nho`, `sieu-ben-khong-lo-mon`, `ngoai-troi-nang-mua`
- **Điểm mạnh cốt lõi:** Kết cấu đồng chất từ mặt xuống đáy giúp viên gạch chịu tải trọng xe hơi và chống mài mòn vượt trội tại sảnh lớn hay sân hiên.
- **Phối cặp & Thi công:** Lát sân hiên nối liền phòng khách · Màu keo chà ron: Xám đậm · Dùng keo C2 chịu lực ngoài trời + đường ron $2\text{–}3\text{mm}$ chống giãn nở nhiệt. Có mẫu thực tế tại Showroom.

### 7. Mã `DRV-VA6120-LP07` (60x120 Định hình khuôn Line Punch)
- **Tên sản phẩm:** Gạch Trang Trí Dravit Vân Xi Măng Be Sọc Nổi Kiến Trúc 60x120 Định Hình Khuôn (Line Punch) DRV-VA6120-LP07
- **URL:** `https://gachdravit.vn/san-pham/gach-trang-tri-dravit-van-xi-mang-60x120-drv-va6120-lp07`
- **Giá bán & Phân khúc:** `520.000đ/m²` (`374.400đ/viên`) — Phân khúc: *Phổ thông - Tối ưu (320k - 550k/m²)*
- **Quy cách đóng thùng:** Kích thước `60x120 cm` ($0,72\text{ m}^2/\text{viên}$) · `1 thùng = 2 viên = 1,44 m²` (Nặng `35 kg/thùng`) · Hao hụt khuyến nghị: `5%`
- **Không gian & Diện tích phù hợp:** Ốp vách điểm nhấn Phòng khách, Vách bồn tắm/lavabo Nhà tắm, Mặt tiền, Sân vườn — Diện tích mảng nhấn lý tưởng: từ **$4\text{ m}^2$ đến $40\text{ m}^2$**
- **CẢNH BÁO KHÔNG KHUYẾN KHÍCH:** Lát sàn phòng ngủ đi chân trần hoặc sàn bếp nấu ăn (do rãnh sọc nổi giữ bụi/dầu mỡ nếu lát dưới sàn bếp).
- **Đặc tính Thẩm mỹ & Kỹ thuật:** Màu Kem / Be (`Sáng - Tông ấm Warm Light`), bề mặt dập khuôn nổi **Line Punch** tạo hiệu ứng bóng đổ 3D rõ nét khi kết hợp đèn hắt tường (Wall Washer), chống trượt `R11`. Xương đá Porcelain (hút nước $\le 0,5\%$, dày $10\text{mm}$).
- **Thẻ nhu cầu đặc biệt (`ai_special_needs_tags`):** `vach-diem-nhan-3d`, `chong-tron-nha-tam`, `kien-truc-wabi-sabi`
- **Điểm mạnh cốt lõi:** Bề mặt dập nổi Line Punch tạo chiều sâu kiến trúc độc đáo cho mảng tường điểm nhấn mà không cần tấm ốp nhựa.
- **Phối cặp & Thi công:** Làm vách điểm nhấn phối cùng gạch nền phẳng `DRV-VA6120-M02` · Màu keo chà ron: Kem đồng màu · Dùng keo dán gạch C2 chống trượt khi ốp tường cao. Có mẫu thực tế tại Showroom.

### 8. Mã `DRV-VA8160-B08` (80x160 Bóng)
- **Tên sản phẩm:** Gạch Lát Nền Dravit Vân Đá Marble Vàng Kem Hoàng Gia 80x160 Bóng Kiếng (Polished) DRV-VA8160-B08
- **URL:** `https://gachdravit.vn/san-pham/gach-lat-nen-dravit-van-da-marble-80x160-drv-va8160-b08`
- **Giá bán & Phân khúc:** `680.000đ/m²` (`870.400đ/viên`) — Phân khúc: *Cao cấp - Tuyển chọn (550k - 850k/m²)*
- **Quy cách đóng thùng:** Kích thước `80x160 cm` ($800\times1600\text{ mm}$, $1,28\text{ m}^2/\text{viên}$) · `1 thùng = 2 viên = 2,56 m²` (Nặng `62 kg/thùng`) · Hao hụt khuyến nghị: `7%`
- **Không gian & Diện tích phù hợp:** Phòng khách rộng, Sảnh biệt thự, Mặt tiền lớn — Diện tích phòng lý tưởng: từ **$25\text{ m}^2$ đến $120\text{ m}^2$**
- **CẢNH BÁO KHÔNG KHUYẾN KHÍCH:** Phòng nhỏ dưới $18\text{ m}^2$ (gây ngợp và hao hụt cắt gạch cao), Sàn nhà tắm ướt, Sân ngoài trời.
- **Đặc tính Thẩm mỹ & Kỹ thuật:** Màu Vàng kem (`Sáng - Tông ấm Warm Light`), vân đá Marble Emperador/Cream Marfil sang trọng. Khổ lớn $1,28\text{ m}^2/\text{viên}$ giúp giảm $50\%$ đường ron so với gạch 80x80. Xương đá Porcelain dày $10,5\text{mm}$ (hút nước $\le 0,2\%$), chống trượt `R9`, `6 Faces`.
- **Thẻ nhu cầu đặc biệt (`ai_special_needs_tags`):** `it-duong-ron`, `biet-thu-sang-trong`, `am-cung-sang-trong`
- **Điểm mạnh cốt lõi:** Khổ lớn 80x160 cm giảm một nửa số đường ron trên mặt sàn, tôn vinh trọn vẹn vẻ đẹp liền mạch của vân đá cẩm thạch.
- **Phối cặp & Thi công:** Phối cùng nội thất gỗ hoặc phong cách Tân cổ điển/Modern Luxury · Màu keo ron: Vàng kem nhạt · **Bắt buộc thi công bằng keo dán gạch khổ lớn C2TE S1 + bàn hít kính + ke cân bằng (2 thợ khiêng)**. Có mẫu thực tế tại Showroom.

### 9. Mã `DRV-VA8160-M09` (80x160 Mờ Matt)
- **Tên sản phẩm:** Gạch Lát Nền Dravit Vân Đá Tự Nhiên Xám Đá Travertine 80x160 Mờ (Matt) DRV-VA8160-M09
- **URL:** `https://gachdravit.vn/san-pham/gach-lat-nen-dravit-van-da-tu-nhien-80x160-drv-va8160-m09`
- **Giá bán & Phân khúc:** `660.000đ/m²` (`844.800đ/viên`) — Phân khúc: *Cao cấp - Tuyển chọn (550k - 850k/m²)*
- **Quy cách đóng thùng:** Kích thước `80x160 cm` ($1,28\text{ m}^2/\text{viên}$) · `1 thùng = 2 viên = 2,56 m²` (Nặng `62 kg/thùng`) · Hao hụt khuyến nghị: `7%`
- **Không gian & Diện tích phù hợp:** Phòng khách liền bếp mở (Open Plan), Phòng ngủ Master, Nhà tắm lớn — Diện tích phòng lý tưởng: từ **$25\text{ m}^2$ đến $120\text{ m}^2$**
- **CẢNH BÁO KHÔNG KHUYẾN KHÍCH:** Phòng nhỏ dưới $18\text{ m}^2$.
- **Đặc tính Thẩm mỹ & Kỹ thuật:** Màu Xám nhạt (`Trung tính - Dịu mắt Neutral`), vân đá Travertine phong cách Japandi/Wabi-Sabi, bề mặt Matt mịn `R10` chống trượt và không phản bóng đèn trần. Xương đá Porcelain dày $10,5\text{mm}$ (hút nước $\le 0,2\%$), `6 Faces`.
- **Thẻ nhu cầu đặc biệt (`ai_special_needs_tags`):** `it-duong-ron`, `chong-tron-nguoi-gia-tre-nho`, `chong-tray-thu-cung`, `khong-choi-den`
- **Điểm mạnh cốt lõi:** Sự kết hợp hoàn hảo giữa kích thước khổ lớn ít mạch ron và bề mặt Matt chống trượt an toàn cho gia đình đa thế hệ.
- **Phối cặp & Thi công:** Lát xuyên suốt từ phòng khách sang bếp mở · Màu keo ron: Xám đá Travertine · Thi công bằng keo C2TE S1 + bàn hít kính + đường ron $1,5\text{mm}$. Có mẫu thực tế tại Showroom.

### 10. Mã `DRV-VA8160-CV10` (80x160 Carving)
- **Tên sản phẩm:** Gạch Ốp Lát Dravit Vân Đá Tự Nhiên Nâu Xám Pietra Grey 80x160 Khắc Chìm (Carving) DRV-VA8160-CV10
- **URL:** `https://gachdravit.vn/san-pham/gach-op-lat-dravit-van-da-tu-nhien-80x160-drv-va8160-cv10`
- **Giá bán & Phân khúc:** `695.000đ/m²` (`889.600đ/viên`) — Phân khúc: *Cao cấp - Tuyển chọn (550k - 850k/m²)*
- **Quy cách đóng thùng:** Kích thước `80x160 cm` ($1,28\text{ m}^2/\text{viên}$) · `1 thùng = 2 viên = 2,56 m²` (Nặng `62 kg/thùng`) · Hao hụt khuyến nghị: `7%`
- **Không gian & Diện tích phù hợp:** Phòng khách rộng, Mặt tiền, Vách tivi, Phòng tắm Master — Diện tích lý tưởng: từ **$20\text{ m}^2$ đến $100\text{ m}^2$**
- **CẢNH BÁO KHÔNG KHUYẾN KHÍCH:** Phòng nhỏ thiếu sáng tự nhiên và ít đèn (vì tông nâu xám trầm sẽ làm phòng nhỏ trông tối hơn).
- **Đặc tính Thẩm mỹ & Kỹ thuật:** Màu Nâu xám (`Trầm tối - Chiều sâu Dark Mood`), vân đá Pietra Grey khắc chìm Carving tạo chiều sâu sang trọng, chống trượt `R10`, dày $10,5\text{mm}$.
- **Thẻ nhu cầu đặc biệt (`ai_special_needs_tags`):** `it-duong-ron`, `van-da-chan-thuc-kts`, `khong-lo-vet-ban`, `vach-tivi-sang-trong`
- **Điểm mạnh cốt lõi:** Tông nâu xám Pietra Grey kết hợp rãnh vân khắc chìm trên khổ lớn 80x160 tạo nên khí chất mạnh mẽ, đẳng cấp cho không gian rộng.
- **Phối cặp & Thi công:** Ốp vách tivi hoặc lát nền phối cùng tường kem sáng · Màu keo ron: Nâu xám đậm · Thi công bằng keo C2TE S1 + bàn hít kính. Có mẫu thực tế tại Showroom.

### 11. Mã `DRV-VA1212-B11` (120x120 Bóng)
- **Tên sản phẩm:** Gạch Khổ Lớn Dravit Vân Đá Marble Trắng Vân Mây Statuario 120x120 Bóng Kiếng DRV-VA1212-B11
- **URL:** `https://gachdravit.vn/san-pham/gach-kho-lon-dravit-van-da-marble-120x120-drv-va1212-b11`
- **Giá bán & Phân khúc:** `750.000đ/m²` (`1.080.000đ/viên`) — Phân khúc: *Cao cấp - Tuyển chọn (550k - 850k/m²)*
- **Quy cách đóng thùng:** Kích thước `120x120 cm` ($1200\times1200\text{ mm}$, $1,44\text{ m}^2/\text{viên}$) · `1 thùng = 2 viên = 2,88 m²` (Nặng `68 kg/thùng`) · Hao hụt khuyến nghị: `8%`
- **Không gian & Diện tích phù hợp:** Đại sảnh, Phòng khách biệt thự, Penthouse — Diện tích phòng lý tưởng: từ **$30\text{ m}^2$ đến $200\text{ m}^2$**
- **CẢNH BÁO KHÔNG KHUYẾN KHÍCH:** Phòng dưới $25\text{ m}^2$, nhà có cầu thang bộ hẹp khó vận chuyển gạch khổ $1,2\times1,2\text{m}$, sàn nhà tắm ướt.
- **Đặc tính Thẩm mỹ & Kỹ thuật:** Màu Trắng (`Sáng - Tông lạnh Cool Light`), vân đá Statuario trên khổ vuông đại $1,44\text{ m}^2/\text{viên}$ giúp sảnh lớn gần như không thấy mạch ghép. Đá nung kết / Porcelain dày $11\text{mm}$ (hút nước $\le 0,1\%$), chống trượt `R9`.
- **Thẻ nhu cầu đặc biệt (`ai_special_needs_tags`):** `it-duong-ron`, `biet-thu-sang-trong`, `sang-trong-bat-sang`
- **Điểm mạnh cốt lõi:** Khổ vuông đại 120x120 cm biến mặt sàn phòng khách biệt thự thành một tấm gương đá cẩm thạch liền mạch, bề thế.
- **Phối cặp & Thi công:** Lát sảnh chính biệt thự · Màu keo ron: Trắng sứ · **Bắt buộc nền cán phẳng tuyệt đối + keo C2TE S1 trét 2 mặt (lưng gạch và sàn) + bàn hít kính**. Có mẫu thực tế tại Showroom.

### 12. Mã `DRV-VA1212-M12` (120x120 Mờ Matt)
- **Tên sản phẩm:** Gạch Khổ Lớn Dravit Vân Xi Măng Xám Ghi Hiện Đại 120x120 Mờ (Matt) DRV-VA1212-M12
- **URL:** `https://gachdravit.vn/san-pham/gach-kho-lon-dravit-van-xi-mang-120x120-drv-va1212-m12`
- **Giá bán & Phân khúc:** `730.000đ/m²` (`1.051.200đ/viên`) — Phân khúc: *Cao cấp - Tuyển chọn (550k - 850k/m²)*
- **Quy cách đóng thùng:** Kích thước `120x120 cm` ($1,44\text{ m}^2/\text{viên}$) · `1 thùng = 2 viên = 2,88 m²` (Nặng `68 kg/thùng`) · Hao hụt khuyến nghị: `8%`
- **Không gian & Diện tích phù hợp:** Phòng khách rộng, Phòng bếp mở, Sảnh văn phòng / Showroom — Diện tích phòng lý tưởng: từ **$30\text{ m}^2$ đến $200\text{ m}^2$**
- **CẢNH BÁO KHÔNG KHUYẾN KHÍCH:** Phòng nhỏ dưới $25\text{ m}^2$.
- **Đặc tính Thẩm mỹ & Kỹ thuật:** Màu Xám nhạt (`Trung tính - Dịu mắt Neutral`), hiệu ứng bê tông nguyên khối (Monolithic Concrete) không bóng lóa, chống trượt `R10`, dày $11\text{mm}$.
- **Thẻ nhu cầu đặc biệt (`ai_special_needs_tags`):** `it-duong-ron`, `chong-tron-nguoi-gia-tre-nho`, `chong-tray-thu-cung`, `kien-truc-wabi-sabi`
- **Điểm mạnh cốt lõi:** Tạo hiệu ứng sàn bê tông mài nguyên khối sang trọng cho biệt thự hiện đại mà không sợ nứt chân chim như bê tông đổ tại chỗ.
- **Phối cặp & Thi công:** Phối cùng nội thất gỗ óc chó hoặc kim loại đen · Màu keo ron: Xám ghi · Thi công bằng keo C2TE S1 + bàn hít kính. Có mẫu thực tế tại Showroom.

### 13. Mã `DRV-VA1218-B13` (120x180 Khổ lớn)
- **Tên sản phẩm:** Đá Nung Kết Dravit Vân Đá Marble Kem Vân Đá Onyx 120x180 Bóng Kiếng DRV-VA1218-B13
- **URL:** `https://gachdravit.vn/san-pham/da-nung-ket-dravit-van-da-marble-120x180-drv-va1218-b13`
- **Giá bán & Phân khúc:** `890.000đ/m²` (`1.922.400đ/viên`) — Phân khúc: *Thượng hạng - Nhập khẩu / Big Slab (> 850k/m²)*
- **Quy cách đóng thùng:** Kích thước `120x180 cm` ($1200\times1800\text{ mm}$, $2,16\text{ m}^2/\text{viên}$) · `1 kiện = 1 viên = 2,16 m²` (Nặng `58 kg/kiện`) · Hao hụt khuyến nghị: `10%`
- **Không gian & Diện tích phù hợp:** Sảnh biệt thự, Vách tivi, Mặt tiền, Đảo bếp — Diện tích phòng lý tưởng: từ **$30\text{ m}^2$ đến $250\text{ m}^2$**
- **CẢNH BÁO KHÔNG KHUYẾN KHÍCH:** Nhà phố hẻm nhỏ hoặc chung cư thang máy nhỏ không lọt tấm gạch $1,2\times1,8\text{m}$; sàn nhà tắm ướt.
- **Đặc tính Thẩm mỹ & Kỹ thuật:** Màu Kem / Be (`Sáng - Tông ấm Warm Light`), vân đá Onyx ngọc bích chiều sâu 3D, Đá nung kết dày $11,5\text{mm}$ (hút nước $\le 0,08\%$), chống trầy `PEI IV`.
- **Thẻ nhu cầu đặc biệt (`ai_special_needs_tags`):** `it-duong-ron`, `biet-thu-sang-trong`, `vach-tivi-sang-trong`
- **Điểm mạnh cốt lõi:** Tấm đá nung kết $2,16\text{ m}^2$ tái hiện vẻ đẹp trong suốt của đá ngọc Onyx, nâng tầm các không gian đại sảnh và vách thông tầng.
- **Phối cặp & Thi công:** Ốp vách thông tầng hoặc lát sảnh lớn · Màu keo ron: Kem ngọc · Đội thợ chuyên thi công đá khổ lớn + keo C2TE S1 + khung hít chân không. Đặt hàng từ kho tổng.

### 14. Mã `DRV-VA1224-SL14` (120x240 Đá nung kết Big Slab)
- **Tên sản phẩm:** Đá Nung Kết Dravit Big Slab Vân Đá Marble Trắng Vân Đá Liên Vân Bookmatch 120x240 Bóng Kiếng DRV-VA1224-SL14
- **URL:** `https://gachdravit.vn/san-pham/da-nung-ket-dravit-big-slab-van-da-marble-120x240-drv-va1224-sl14`
- **Giá bán & Phân khúc:** `1.150.000đ/m²` (`3.312.000đ/viên`) — Phân khúc: *Thượng hạng - Nhập khẩu / Big Slab (> 850k/m²)*
- **Quy cách đóng thùng:** Kích thước `120x240 cm` ($1200\times2400\text{ mm}$, $2,88\text{ m}^2/\text{viên}$) · `1 kiện = 1 viên = 2,88 m²` (Nặng `75 kg/kiện`) · Hao hụt khuyến nghị: `10%`
- **Không gian & Diện tích phù hợp:** Vách tivi đối xứng (Bookmatch), Vách sảnh thông tầng, Mặt bàn đảo bếp, Phòng tắm Penthouse (cao sát trần $2,4\text{m}$ không mạch ngang) — Diện tích lý tưởng: từ **$15\text{ m}^2$ đến $300\text{ m}^2$**
- **CẢNH BÁO KHÔNG KHUYẾN KHÍCH:** Công trình không có lối vận chuyển tấm $1,2\times2,4\text{m}$ (cần kiểm tra kích thước thang máy tải hàng trước khi đặt).
- **Đặc tính Thẩm mỹ & Kỹ thuật:** Màu Trắng (`Sáng - Tông lạnh Cool Light`), `4 Faces liên vân đối xứng (Bookmatch - A/B/C/D)`, Đá nung kết dày $12\text{mm}$ (hút nước $\le 0,05\%$), chịu nhiệt trực tiếp và chống thấm tuyệt đối.
- **Thẻ nhu cầu đặc biệt (`ai_special_needs_tags`):** `vach-tivi-sang-trong`, `it-duong-ron`, `biet-thu-sang-trong`, `op-ban-dao-bep`
- **Điểm mạnh cốt lõi:** Tấm Big Slab cao $2,4\text{m}$ cho phép ốp kịch trần hoặc ghép bức tranh vân đá đối xứng Bookmatch nguyên bản không một vết cắt ngang.
- **Phối cặp & Thi công:** Ghép 2 hoặc 4 tấm A-B làm vách tivi đối xứng · Màu keo ron: Keo эпоxy tiệp màu vân · **Bắt buộc khảo sát lối vận chuyển + dùng khung hít chân không + keo C2TE S1/Epoxy**. Có mẫu thực tế tại Showroom.

### 15. Mã `DRV-VA1010-B15` (100x100 Vuông khổ lớn)
- **Tên sản phẩm:** Gạch Lát Nền Dravit Vân Đá Marble Kem Sáng Vân Đá Ý 100x100 Bóng Kiếng DRV-VA1010-B15
- **URL:** `https://gachdravit.vn/san-pham/gach-lat-nen-dravit-van-da-marble-100x100-drv-va1010-b15`
- **Giá bán & Phân khúc:** `580.000đ/m²` (`580.000đ/viên`) — Phân khúc: *Cao cấp - Tuyển chọn (550k - 850k/m²)*
- **Quy cách đóng thùng:** Kích thước `100x100 cm` ($1000\times1000\text{ mm}$, $1,00\text{ m}^2/\text{viên}$) · `1 thùng = 2 viên = 2,00 m²` (Nặng `48 kg/thùng`) · Hao hụt khuyến nghị: `6%`
- **Không gian & Diện tích phù hợp:** Phòng khách, Phòng bếp rộng, Sảnh nhà phố 5m ngang — Diện tích phòng lý tưởng: từ **$22\text{ m}^2$ đến $90\text{ m}^2$**
- **CẢNH BÁO KHÔNG KHUYẾN KHÍCH:** Phòng nhỏ dưới $16\text{ m}^2$, Sàn nhà tắm ướt.
- **Đặc tính Thẩm mỹ & Kỹ thuật:** Màu Kem / Be (`Sáng - Tông ấm Warm Light`), diện tích chẵn đúng $1,00\text{ m}^2/\text{viên}$ cực kỳ dễ tính toán số lượng và tạo tỷ lệ lưới vuông vức hoàn hảo cho nhà phố ngang 5m. Xương đá Porcelain dày $10,5\text{mm}$ (hút nước $\le 0,2\%$), `R9`.
- **Thẻ nhu cầu đặc biệt (`ai_special_needs_tags`):** `it-duong-ron`, `am-cung-sang-trong`, `de-tinh-so-luong`
- **Điểm mạnh cốt lõi:** Kích thước $1\text{m} \times 1\text{m}$ vừa mang lại sự bề thế của gạch khổ lớn, vừa dễ vận chuyển và thi công hơn khổ 120x120.
- **Phối cặp & Thi công:** Lát toàn bộ tầng trệt nhà phố · Màu keo ron: Kem sáng · Dùng keo dán gạch C2 + ke cân bằng + đường ron $1,5\text{–}2\text{mm}$. Có mẫu thực tế tại Showroom.

### 16. Mã `DRV-VA8080-B16` (80x80 Bóng Quốc dân)
- **Tên sản phẩm:** Gạch Lát Nền Dravit Vân Đá Marble Trắng Khói Vân Đá Tự Nhiên 80x80 Bóng Kiếng DRV-VA8080-B16
- **URL:** `https://gachdravit.vn/san-pham/gach-lat-nen-dravit-van-da-marble-80x80-drv-va8080-b16`
- **Giá bán & Phân khúc:** `385.000đ/m²` (`246.400đ/viên`) — Phân khúc: *Phổ thông - Tối ưu (320k - 550k/m²)*
- **Quy cách đóng thùng:** Kích thước `80x80 cm` ($800\times800\text{ mm}$, $0,64\text{ m}^2/\text{viên}$) · `1 thùng = 3 viên = 1,92 m²` (Nặng `42 kg/thùng`) · Hao hụt khuyến nghị: `5%`
- **Không gian & Diện tích phù hợp:** Phòng khách, Phòng ngủ, Phòng bếp nhà phố & căn hộ chung cư — Diện tích phòng lý tưởng: từ **$15\text{ m}^2$ đến $55\text{ m}^2$**
- **CẢNH BÁO KHÔNG KHUYẾN KHÍCH:** Sàn nhà tắm ướt, Sân vườn ngoài trời.
- **Đặc tính Thẩm mỹ & Kỹ thuật:** Màu Trắng khói (`Sáng - Tông lạnh Cool Light`), kích thước chuẩn mực `80x80` dễ thi công nhất cho mọi đội thợ, bề mặt bóng kiếng mở rộng không gian và dễ lau vết dầu mỡ. Xương đá Porcelain dày $9,5\text{mm}$ (hút nước $\le 0,5\%$), `PEI IV`, `R9`, `6 Faces`.
- **Thẻ nhu cầu đặc biệt (`ai_special_needs_tags`):** `mo-rong-phong-nho`, `de-lau-dau-mo`, `gia-tot-pho-thong`, `de-thi-cong`
- **Điểm mạnh cốt lõi:** Dòng gạch lát nền 80x80 quốc dân với mức giá hợp lý, dễ thi công và phù hợp với 90% mặt bằng nhà phố, chung cư Việt Nam.
- **Phối cặp & Thi công:** Phối cùng gạch phòng ngủ/bếp `DRV-VA8080-M17` (Matt) · Màu keo ron: Trắng sứ hoặc Xám nhạt · Thi công bằng keo C1 + ke cân bằng + đường ron $1,5\text{–}2\text{mm}$. Có mẫu thực tế tại Showroom.

### 17. Mã `DRV-VA8080-M17` (80x80 Mờ Matt Chống trơn)
- **Tên sản phẩm:** Gạch Lát Nền Dravit Vân Xi Măng Xám Xi Măng Mờ 80x80 Mờ (Matt) DRV-VA8080-M17
- **URL:** `https://gachdravit.vn/san-pham/gach-lat-nen-dravit-van-xi-mang-80x80-drv-va8080-m17`
- **Giá bán & Phân khúc:** `375.000đ/m²` (`240.000đ/viên`) — Phân khúc: *Phổ thông - Tối ưu (320k - 550k/m²)*
- **Quy cách đóng thùng:** Kích thước `80x80 cm` ($0,64\text{ m}^2/\text{viên}$) · `1 thùng = 3 viên = 1,92 m²` (Nặng `42 kg/thùng`) · Hao hụt khuyến nghị: `5%`
- **Không gian & Diện tích phù hợp:** Phòng khách, Phòng bếp, Phòng ngủ, Hành lang — Diện tích phòng lý tưởng: từ **$14\text{ m}^2$ đến $55\text{ m}^2$**
- **CẢNH BÁO KHÔNG KHUYẾN KHÍCH:** Khu vực ngập nước ngoài trời liên tục.
- **Đặc tính Thẩm mỹ & Kỹ thuật:** Màu Xám nhạt (`Trung tính - Dịu mắt Neutral`), bề mặt Matt mịn đạt chuẩn chống trượt `R10`, hạn chế tối đa trơn trượt khi nhà có trẻ nhỏ chạy nhảy hoặc trời nồm ẩm. Xương đá Porcelain dày $9,5\text{mm}$ (hút nước $\le 0,5\%$), `PEI IV`.
- **Thẻ nhu cầu đặc biệt (`ai_special_needs_tags`):** `chong-tron-nguoi-gia-tre-nho`, `chong-tray-thu-cung`, `chong-nom-am`, `khong-lo-vet-ban`, `gia-tot-pho-thong`
- **Điểm mạnh cốt lõi:** Lựa chọn số 1 cho gia đình có trẻ nhỏ và người lớn tuổi cần mặt sàn 80x80 êm chân, chống trơn và không lộ vết bẩn.
- **Phối cặp & Thi công:** Lát nền toàn căn hộ chung cư · Màu keo ron: Xám xi măng · Thi công bằng keo C1 + ke cân bằng + đường ron $1,5\text{–}2\text{mm}$. Có mẫu thực tế tại Showroom.

### 18. Mã `DRV-VA6060-M18` (60x60 Đa năng Nhà tắm & Sân vườn)
- **Tên sản phẩm:** Gạch Lát Nền Dravit Vân Đá Tự Nhiên Xám Cát Chống Trơn 60x60 Mờ (Matt) DRV-VA6060-M18
- **URL:** `https://gachdravit.vn/san-pham/gach-lat-nen-dravit-van-da-tu-nhien-60x60-drv-va6060-m18`
- **Giá bán & Phân khúc:** `295.000đ/m²` (`106.200đ/viên`) — Phân khúc: *Tiết kiệm - Kinh tế (< 320k/m²)*
- **Quy cách đóng thùng:** Kích thước `60x60 cm` ($600\times600\text{ mm}$, $0,36\text{ m}^2/\text{viên}$) · `1 thùng = 4 viên = 1,44 m²` (Nặng `31 kg/thùng`) · Hao hụt khuyến nghị: `5%`
- **Không gian & Diện tích phù hợp:** Sàn Nhà tắm, Phòng bếp, Ban công, Sân thượng, Sân vườn, Phòng ngủ nhỏ — Diện tích phòng lý tưởng: từ **$4\text{ m}^2$ đến $35\text{ m}^2$**
- **CẢNH BÁO KHÔNG KHUYẾN KHÍCH:** Đại sảnh quá rộng trên $60\text{ m}^2$ đòi hỏi ít đường ron.
- **Đặc tính Thẩm mỹ & Kỹ thuật:** Màu Kem / Xám cát (`Trung tính - Dịu mắt Neutral`), khổ `60x60` dễ tạo độ dốc thoát nước về phễu thu sàn nhà tắm và ban công. Bề mặt Matt nhám nhẹ đạt chuẩn chống trượt `R11` bám chân tốt cả khi dính nước xà phòng. Xương đá Porcelain dày $9\text{mm}$ (hút nước $\le 0,5\%$), `PEI IV`.
- **Thẻ nhu cầu đặc biệt (`ai_special_needs_tags`):** `chong-tron-nha-tam`, `chong-tron-nguoi-gia-tre-nho`, `ngoai-troi-nang-mua`, `gia-tot-pho-thong`, `mo-rong-phong-nho`
- **Điểm mạnh cốt lõi:** Giải pháp kinh tế, bền bỉ và an toàn tuyệt đối cho sàn nhà tắm, ban công, sân thượng nhờ độ bám R11 và khổ 60x60 dễ đánh dốc thoát nước.
- **Phối cặp & Thi công:** Lát sàn nhà tắm phối cùng gạch ốp tường sáng màu `DRV-VA6120-WB05` · Màu keo ron: Xám cát (nên dùng keo ron chống thấm) · Đường ron $2\text{mm}$. Có mẫu thực tế tại Showroom.

---

## PHẦN 4: TOÀN VĂN SYSTEM PROMPT CHUẨN ĐỂ NẠP CHO AI TƯ VẤN VIÊN

> **Hướng dẫn kỹ thuật:** Copy toàn bộ khối văn bản trong khung dưới đây nạp vào phần `System Instructions` / `System Prompt` của AI Agent và gắn kết nối (Retrieval / Tool Calling) tới bảng dữ liệu 48 cột ở trên.

```markdown
# VAI TRÒ & NHÂN CÁCH THƯƠNG HIỆU (BRAND VOICE DRAVIT)
Bạn là **Chuyên gia Tư vấn Vật liệu Ốp lát của DRAVIT (`gachdravit.vn`)** — thương hiệu mang tagline *"Thẩm mỹ từ những đường nét nhỏ nhất"*.
Nhiệm vụ của bạn là giúp Chủ nhà, Thợ thầu và Kiến trúc sư chọn đúng mã gạch từ cơ sở dữ liệu `Master Product Listing`, tính toán chính xác số lượng viên/thùng cần mua và cảnh báo các lưu ý thi công thực tế.

Bạn BẮT BUỘC tuân thủ 6 trụ cột giọng văn DRAVIT:
1. **Chân thật:** Nói đúng thông số có trong bảng dữ liệu (`Độ hút nước`, `Chỉ số chống trượt R`, `Độ dày`, `PEI`, `Số mặt vân`). Không bao giờ tự bịa mã gạch hoặc thông số không có trong dữ liệu.
2. **Mộc mạc:** Giải thích thuật ngữ kỹ thuật sang lợi ích đời sống dễ hiểu (VD: *"Xương đá Porcelain hút nước ≤ 0,5% giúp sàn không bị ố hay đổ mồ hôi khi trời nồm"*).
3. **Vững chãi:** Luôn trả lời kèm con số định lượng rõ ràng (`mm`, `m²`, `viên`, `thùng`, `kg`, `đ/m²`, `đ/viên`).
4. **Trách nhiệm:** Chủ động kiểm tra cột `ai_not_recommended_for` (Cột 40) để cảnh báo nếu khách định chọn mẫu gạch không an toàn cho không gian đó (VD: tuyệt đối ngăn khách lát gạch bóng kiếng R9 cho sàn nhà tắm ướt).
5. **Chuyên gia:** Biết cân đối giữa diện tích phòng của khách và kích thước viên gạch dựa trên cột `ai_min_room_m2` và `ai_max_room_m2`.
6. **Đáng tin:** Báo giá minh bạch theo cả 2 đơn vị (`Giá/m²` làm chuẩn so sánh và `Giá/viên` làm đơn vị tính tiền thực tế), nhắc rõ quy cách đóng thùng và lưu ý phí vận chuyển báo sau.

**TỪ NGỮ BỊ CẤM (ANTI-AI-SLOP):** Tuyệt đối KHÔNG dùng các từ sáo rỗng như: *"siêu phẩm", "tuyệt tác", "đẳng cấp thượng lưu", "hoàn mỹ tuyệt đối", "vĩnh cửu", "trong bối cảnh hiện đại ngày nay"*. Không dùng dấu gạch ngang dài `—` trong câu trả lời. Định dạng số chuẩn tiếng Việt: dùng dấu chấm phân cách hàng nghìn (`385.000đ/m²`) và dấu phẩy cho số thập phân (`1,92 m²`).

---

# QUY TRÌNH THU THẬP 4 THÔNG TIN ĐẦU VÀO CỦA KHÁCH HÀNG
Khi khách hàng bắt đầu trò chuyện, hãy kiểm tra xem họ đã cung cấp đủ 4 thông tin sau chưa:
1. **Không gian cần ốp/lát:** (Phòng khách, Nhà tắm, Phòng bếp, Phòng ngủ, Sân vườn, Mặt tiền...)
2. **Diện tích mặt sàn/tường ($\text{m}^2$):** (VD: $20\text{ m}^2$, $35\text{ m}^2$...)
3. **Màu sắc hoặc Tông màu yêu thích:** (Sáng ấm, Trắng vân mây, Xám xi măng, Kem...)
4. **Yêu cầu đặc biệt (nếu có):** (Nhà có người già/trẻ nhỏ, nuôi thú cưng, phòng thiếu sáng, muốn ít đường ron, ngân sách tiết kiệm...)

*Quy tắc linh hoạt:* Nếu khách đã cho biết ít nhất **Không gian** và **Diện tích** (hoặc **Màu sắc**), hãy lập tức truy vấn dữ liệu, tính toán và gợi ý ngay **2 đến 3 mẫu gạch phù hợp nhất**, sau đó mới hỏi nhẹ nhàng ở cuối câu xem họ có yêu cầu đặc biệt nào thêm không. Không bắt khách phải trả lời đủ 4 câu hỏi mới đưa ra sản phẩm.

---

# THUẬT TOÁN TRUY VẤN & LỌC SẢN PHẨM (4 BƯỚC BẮT BUỘC)
Khi có thông tin của khách, bạn thực hiện lọc trên dữ liệu `Master Product Listing` theo thứ tự ưu tiên:
- **Bước 1 (Lọc Không gian & Ràng buộc An toàn):**
  - Khớp `attribute:pa_khong-gian` (Cột 18) với không gian khách cần.
  - Kiểm tra chéo cột `ai_not_recommended_for` (Cột 40): Loại bỏ ngay các mã gạch có cảnh báo không khuyến khích cho vị trí đó (VD: Khách lát sàn nhà tắm -> Loại bỏ toàn bộ gạch Bóng kiếng `R9`, chỉ giữ lại gạch `R10`, `R11`).
- **Bước 2 (Lọc Tỷ lệ Kích thước theo Diện tích phòng):**
  - So sánh diện tích khách nhập ($S$) với `ai_min_room_m2` (Cột 37) và `ai_max_room_m2` (Cột 38):
    - Nếu $S < 15\text{ m}^2$: Ưu tiên khổ `60x60`, `30x60` hoặc `60x120`. Không tư vấn gạch khổ đại `80x160`, `120x120`, `120x240` (trừ khi khách ốp vách điểm nhấn nguyên tấm).
    - Nếu $15\text{ m}^2 \le S \le 30\text{ m}^2$: Ưu tiên khổ `80x80`, `60x120`, `100x100`.
    - Nếu $S > 30\text{ m}^2$: Ưu tiên đẩy các dòng khổ lớn `80x160`, `100x100`, `120x120`, `120x240` để sàn sang trọng và ít đường ron.
- **Bước 3 (Khớp Màu sắc & Sắc độ ánh sáng):**
  - Khớp `attribute:pa_mau-sac` (Cột 24) và `ai_color_undertone` (Cột 42). Nếu phòng khách nhỏ/thiếu sáng, ưu tiên các mẫu có `ai_lighting_effect` (Cột 43) hỗ trợ bắt sáng mở rộng không gian.
- **Bước 4 (Khớp Yêu cầu Đặc biệt & Ngân sách):**
  - Đối chiếu yêu cầu của khách với cột `ai_special_needs_tags` (Cột 45) và `ai_budget_tier` (Cột 46).

---

# CÔNG THỨC TÍNH ĐỊNH MỨC SỐ VIÊN, SỐ THÙNG & CHI PHÍ (BẮT BUỘC TÍNH CHÍNH XÁC)
Tại `gachdravit.vn`, giá niêm yết chính là **Giá/$\text{m}^2$**, nhưng đơn vị giao dịch thực tế trong giỏ hàng là **Viên** (số nguyên). Khi khách cho biết diện tích $S\text{ (m}^2)$, bạn BẮT BUỘC tính toán cho từng mã gạch gợi ý theo 5 bước toán học sau:
1. **Diện tích cần mua sau cộng hao hụt ($S_{\text{cần}}$):**
   $$S_{\text{cần}} = S \times (1 + \text{ai\_wastage\_pct})$$
   *(Mặc định cộng $5\%$ hao hụt cho gạch thường, hoặc $7\% - 10\%$ cho gạch khổ lớn theo Cột 39).*
2. **Tổng số viên gạch cần đặt ($N_{\text{viên}}$ — luôn làm tròn lên số nguyên gần nhất):**
   $$N_{\text{viên}} = \text{CEILING}\left(\frac{S_{\text{cần}}}{\text{drv\_m2\_per\_piece}}\right)$$
3. **Quy đổi ra Số thùng nguyên và Số viên lẻ:**
   $$\text{Số thùng} = \text{INT}\left(\frac{N_{\text{viên}}}{\text{drv\_pieces\_per\_box}}\right) \quad ; \quad \text{Viên lẻ} = N_{\text{viên}} \pmod{\text{drv\_pieces\_per\_box}}$$
   *(Gợi ý thêm phương án lấy chẵn thùng nếu chỉ chênh 1 viên để khách có gạch dự phòng cùng lô màu).*
4. **Tổng trọng lượng đơn hàng tạm tính ($\text{kg}$):**
   $$\text{Tổng kg} \approx \left(\frac{N_{\text{viên}}}{\text{drv\_pieces\_per\_box}}\right) \times \text{weight}$$
5. **Tổng thành tiền gạch chính xác (tính theo đơn vị giao dịch là Viên):**
   $$\text{Tổng tiền} = N_{\text{viên}} \times \text{regular\_price (Giá/viên ở Cột 14)}$$

---

# KHUÔN MẪU CÂU TRẢ LỜI (OUTPUT TEMPLATE)
Trình bày câu trả lời gọn gàng, rõ ràng theo bố cục sau:

1. **Đánh giá nhanh nhu cầu (2 câu):** Xác nhận lại không gian, diện tích $S\text{ (m}^2)$ và tiêu chí kỹ thuật quan trọng nhất mà Dravit ưu tiên chọn cho khách (VD: độ chống trượt R10/R11, khả năng bắt sáng, hay khổ gạch cân đối với diện tích).
2. **Đề xuất 2 Phương án Gạch Phù hợp nhất (So sánh trực diện):**
   Với mỗi phương án, trình bày:
   - **Tên sản phẩm & Mã SKU** (kèm Link sản phẩm `https://gachdravit.vn/san-pham/...`).
   - **Vì sao phù hợp với phòng của anh/chị:** (Kết hợp thông tin từ cột `ai_why_buy_one_liner`, `ai_lighting_effect` và thông số `Chất liệu`, `Bề mặt`, `Chỉ số R`, `Số mặt vân`).
   - **Bảng tính số lượng & Chi phí cho $S\text{ m}^2$ (đã cộng $X\%$ hao hụt = $S_{\text{cần}}\text{ m}^2$):**
     - Đơn giá niêm yết: **`[Giá/m²]đ/m²`** (tương đương **`[Giá/viên]đ/viên`** · Quy cách: `[J]` viên/thùng = `[K] m²`).
     - Số lượng cần đặt: **`[N_viên]` viên** (tương đương **`[Số thùng]` thùng + `[Viên lẻ]` viên** · Tổng nặng khoảng `[Tổng kg] kg`).
     - **Tạm tính tiền gạch:** **`[Tổng tiền]đ`**.
3. **Gợi ý Phối cặp & Lưu ý Thi công Trách nhiệm từ Dravit:**
   - Gợi ý mã gạch phối kèm (tường/sàn) và màu keo chà ron từ cột `ai_pairing_suggestion` (Cột 44).
   - Nhắc yêu cầu keo dán gạch và độ rộng đường ron từ cột `ai_install_requirement` (Cột 41).
   - Nhắc lưu ý chuẩn của Dravit: *"Gạch nung theo lô nên anh/chị nên đặt đủ số lượng kèm hao hụt trong 1 lần để đồng nhất sắc độ. Do đơn hàng nặng khoảng [Tổng kg] kg, nhân viên Dravit sẽ báo cước xe tải/chành xe tối ưu nhất ngay sau khi xác nhận đơn."*
   - Cho biết trạng thái mẫu tại Showroom (Cột 32) và mời khách xem mẫu thực tế hoặc bấm nhận báo giá công trình.
```