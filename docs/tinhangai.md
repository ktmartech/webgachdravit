# Đề án xây dựng AI Chatbot cho website WordPress ngành gạch lát nền

Với quy mô dự kiến 30–50 người dùng/ngày, tôi khuyến nghị xây dựng hệ thống AI Chatbot theo mô hình WordPress + AI Backend + Knowledge Base (RAG) + LLM API.

Điểm quan trọng: Không nên bắt đầu bằng việc tự train một mô hình AI từ đầu. Đối với ngành gạch lát nền, giải pháp hiệu quả hơn là sử dụng mô hình AI có sẵn, sau đó cung cấp dữ liệu chuyên ngành gạch, catalogue, thông số kỹ thuật, giá bán, hình ảnh và chính sách bán hàng để AI tra cứu và trả lời.

Tôi sẽ phân tích theo 6 phần:

1. Kiến trúc hệ thống: một website hay hai website?
2. Luồng xử lý câu hỏi và tối ưu tốc độ phản hồi.
3. Phương pháp training AI hiện nay.
4. Cách xây dựng kho dữ liệu ngành gạch.
5. So sánh mô hình AI và chi phí vận hành.
6. Đề xuất cấu hình triển khai cho 30–50 người dùng/ngày.

## 1. Kiến trúc tổng thể: Có nên tách thành 2 website?

Khuyến nghị: Không cần xây dựng hai website WordPress. Nên tách WordPress và AI Backend thành hai hệ thống độc lập.

Đây là hai khái niệm khác nhau:

- Website 1: WordPress quản lý giao diện, nội dung, sản phẩm và trải nghiệm khách hàng.
- Hệ thống 2: AI Backend xử lý hội thoại, tìm kiếm dữ liệu, gọi mô hình AI và trả kết quả.

AI Backend có thể chạy trên một subdomain hoặc server riêng, không cần cài thêm một website WordPress.

### Kiến trúc đề xuất

WEBSITE WORDPRESS

Giao diện khách hàng • Catalogue • Sản phẩm • Chat Widget

HTTPS / REST API / Streaming

AI BACKEND – API SERVER

Chat Manager

Quản lý hội thoại

AI Orchestrator

Điều phối AI

RAG Engine

Tra cứu dữ liệu gạch

Product Database

Catalogue và thuộc tính

LLM API

GPT / Claude / Gemini

Vector Database

pgvector / Qdrant

Product SQL

Thông số / Giá / SKU

Cache

Redis / Semantic Cache

### Tại sao nên tách AI Backend?

| Tiêu chí                       | AI chạy trong WP               | WP + AI Backend       |
| ------------------------------ | ------------------------------ | --------------------- |
| Tốc độ xử lý                   | Phụ thuộc WP hosting           | Chủ động tối ưu       |
| Mở rộng                        | Khó hơn                        | Dễ dàng               |
| Bảo mật API Key                | Khó quản lý nếu triển khai sai | Tập trung tại backend |
| Quản lý hội thoại              | Hạn chế                        | Linh hoạt             |
| Nhiều website sử dụng chung AI | Khó                            | Dễ                    |
| Nâng cấp mô hình AI            | Phụ thuộc plugin               | Độc lập               |
| Tích hợp CRM                   | Khó mở rộng                    | Dễ mở rộng            |

Mô hình triển khai tôi đề xuất:

- `www.domain.com`: Website WordPress.
- `ai-api.domain.com`: AI Backend.
- `admin-ai.domain.com`: Dashboard quản trị AI (nếu cần).

Không nhất thiết phải có website thứ hai. AI Backend chỉ cần cung cấp API.

## 2. Luồng xử lý câu hỏi và tối ưu tốc độ

Đây là phần quan trọng nhất vì yêu cầu của hệ thống là trả lời nhanh, không chỉ trả lời đúng.

Tôi đề xuất xử lý theo mô hình RAG (Retrieval-Augmented Generation) kết hợp truy vấn cơ sở dữ liệu sản phẩm.

### 2.1. Ví dụ một khách hàng đặt câu hỏi

Khách hàng hỏi:

> Tôi cần tìm gạch lát nền phòng khách 60x120, màu xám vân đá, phong cách hiện đại, ngân sách dưới 350.000đ/m².

AI không nên đưa toàn bộ catalogue cho mô hình đọc. Quy trình phải là:

1. Nhận câu hỏi từ WordPress

   0–50 ms mục tiêu xử lý nội bộ

   Nhận câu hỏi, session ID và lịch sử hội thoại.
2. Phân tích ý định (Intent Detection)

   Nhận diện:
   - Loại sản phẩm: gạch lát nền
   - Kích thước: 60x120
   - Màu sắc: xám
   - Phong cách: vân đá hiện đại
   - Ngân sách: dưới 350.000đ/m²
3. Truy vấn dữ liệu sản phẩm

   Tìm trong SQL Database và Vector Database, lọc theo kích thước, giá, màu sắc, bề mặt, chất liệu.
4. AI tổng hợp và tư vấn

   Chỉ gửi các sản phẩm phù hợp cùng thông số liên quan cho LLM.
5. Trả kết quả về WordPress

   Hiển thị câu trả lời, hình ảnh, mã sản phẩm, giá và nút xem chi tiết.

### 2.2. Không nên dùng Vector Database cho tất cả dữ liệu

Đối với ngành gạch, cần phân biệt hai loại dữ liệu:

| Dữ liệu                             | Cách tìm kiếm   |
| ----------------------------------- | --------------- |
| Mã sản phẩm                         | SQL             |
| Kích thước 60x60, 80x80             | SQL             |
| Giá dưới 300.000đ                   | SQL             |
| Số lượng tồn kho                    | SQL/API         |
| Màu sắc, phong cách                 | Vector Search   |
| Gạch vân đá, vân xi măng            | Vector Search   |
| Tư vấn chọn gạch phòng khách        | RAG             |
| Cách phân biệt porcelain và ceramic | RAG             |
| Cách tính số lượng gạch cần mua     | Logic tính toán |

Tốt nhất là sử dụng Hybrid Search:

- SQL: lọc chính xác theo thông số.
- Vector Search: tìm kiếm theo ý nghĩa.
- Reranking: sắp xếp lại sản phẩm phù hợp.
- LLM: diễn đạt và tư vấn.

Điều này giúp hạn chế AI tự bịa giá, kích thước hoặc mã sản phẩm.

### 2.3. Tốc độ phản hồi mục tiêu

Tôi đề xuất thiết kế theo các mốc sau (đây là mục tiêu kỹ thuật, không phải cam kết latency của nhà cung cấp):

| Công đoạn                           | Thời gian mục tiêu |
| ----------------------------------- | ------------------ |
| WP gửi API                          | 50–150 ms          |
| Backend phân tích                   | 50–150 ms          |
| Truy vấn SQL + Vector               | 50–300 ms          |
| Gọi AI và sinh câu trả lời đầu tiên | 400–1.500 ms       |
| Hoàn tất câu trả lời                | 1,5–4 giây         |

Giải pháp quan trọng: Streaming Response.

Thay vì đợi AI xử lý toàn bộ rồi mới trả về, backend gửi từng phần nội dung về WordPress theo cơ chế SSE (Server-Sent Events).

Ví dụ:

- 0,8 giây: AI bắt đầu hiển thị câu trả lời.
- 1,5 giây: Hiển thị phần tư vấn đầu tiên.
- 2–4 giây: Hoàn thành nội dung và sản phẩm đề xuất.

Đối với người dùng, trải nghiệm này nhanh hơn đáng kể so với việc nhìn màn hình chờ 4 giây.

### 2.4. Những kỹ thuật cần có để tối ưu

1. Redis Cache: lưu kết quả những câu hỏi phổ biến.
2. Embedding Cache: không tính lại vector cho cùng một nội dung.
3. Product Index: lập chỉ mục sẵn catalogue, không đọc trực tiếp toàn bộ WP mỗi lần hỏi.
4. Context Compression: chỉ gửi dữ liệu liên quan đến câu hỏi.
5. Model Routing: câu hỏi đơn giản dùng AI nhỏ, câu hỏi tư vấn phức tạp dùng AI mạnh.
6. Streaming: hiển thị câu trả lời ngay khi AI bắt đầu sinh token.

## 3. Các phương pháp training AI hiện nay

Cần phân biệt rõ: Training AI không đồng nghĩa với việc đưa tài liệu vào chatbot.

Có 4 phương pháp chính:

[How Retrieval-Augmented Generation (RAG) Solves the Limitations of LLMs | by Vinod Rane | Medium](https://images.openai.com/static-rsc-4/nJy9CkmGLNTbezenhKafGMMcMGfG8jXh6Cxxmf0q7m6v_DZFd3Fftl2RDbGRd9Gf1UPV85UUnIuKEaA2qzcRI_qrQVORnjloTQ3QSHyHtpx27YpyuV5F-OSPp8Bt2sjelJfB2hi_l_CPbIdIdeizLr3Q6tA_NcUz56kz_KcyxA8?purpose=inline)

1\. RAG – Training bằng kho kiến thức truy xuất

Khuyến nghị triển khai đầu tiên

- Đưa catalogue, PDF, tài liệu, chính sách vào kho dữ liệu.
- Chia nhỏ tài liệu, tạo embeddings.
- Khi có câu hỏi, AI tìm đoạn thông tin phù hợp để trả lời.

Không phải huấn luyện lại trọng số mô hình.

[Windows performance toolkit for performance analysis: | by Sumit Khedkar | Medium](https://images.openai.com/static-rsc-4/YQc5AfDc8Sgk57l6uOEAECJyhokC9ZdbOZsHRoLpAta8NfL5_le3mE5EBDqy5VaT8bUm97VvVgcwUEw5rJylZFmOpkjuuqNTs1Z4sYH2tZRjlcGeHqXFe4xFKthgpdduUnUKZgAFRL2gGDV9DUtSgBknLhP954Q2YwGm1C5Xe8Q?purpose=inline)

2\. Fine-tuning – Tinh chỉnh mô hình

- Chuẩn bị các cặp câu hỏi và câu trả lời chuẩn.
- Dạy mô hình cách tư vấn, cách phân loại và cách trình bày.
- Phù hợp khi cần hành vi trả lời nhất quán.

Không phù hợp để cập nhật giá và tồn kho thường xuyên.

[NPC Theory for LLMs: Building Workbenches for AI Agents | by Varun Sharma | Medium](https://images.openai.com/static-rsc-4/HijPEl360b7FYThDayUmxT2gLlbh4FMBk-1qbHOiRThlKBLnAVXUJU-Yubf1ToSSeIQgm5YuT45Y4fd9tasOsuDn-HZKV1-Nej83734kQMfW7mdTxLHP2SmgZhT7Z_oq0BDzpIon9EnracIo_4zNefQ7SiAAK5mK7EVlUI6OQbc?purpose=inline)

3\. Prompt Engineering – Huấn luyện bằng chỉ dẫn

- Định nghĩa vai trò chuyên gia gạch.
- Xây dựng quy tắc tư vấn.
- Định nghĩa phong cách, giới hạn và cách trả lời.

Không cần training, có thể triển khai ngay.

[How Grab Built a Vision LLM to Scan Images](https://images.openai.com/static-rsc-4/To6F_jmVtLyWI8pZHnBRsFhMQhk74lpC2xlZmzCt2D-wmmkf0M_aGu62oTdslVkl7NobyNEGANDu1PAZkgzS11NXgUPeQn0oRVuldui4T3vDq0iAQMQtrd5eLgqorUBOLpz0C5Fcac4t0FcbCtDYRcWqEJcIakKbcDlhJoRPbH8?purpose=inline)

4\. Multimodal / Vision AI – AI hiểu hình ảnh

- Khách gửi ảnh phòng khách.
- AI nhận diện phong cách, màu sắc, ánh sáng.
- Tìm sản phẩm gạch tương tự trong catalogue.
- Đề xuất gạch phù hợp với không gian.

Đây là tính năng rất đáng phát triển cho ngành gạch.

### 3.1. Tôi đề xuất kết hợp như sau

| Phương pháp        | Vai trò                       | Thời điểm            |
| ------------------ | ----------------------------- | -------------------- |
| Prompt Engineering | Định hình chuyên gia tư vấn   | Giai đoạn 1          |
| RAG                | Cung cấp kiến thức ngành gạch | Giai đoạn 1          |
| Hybrid Search      | Tìm đúng sản phẩm             | Giai đoạn 1          |
| Vision AI          | Tư vấn từ hình ảnh            | Giai đoạn 2          |
| Fine-tuning        | Chuẩn hóa hành vi             | Giai đoạn 3, nếu cần |

Lưu ý về nền tảng: tài liệu OpenAI cập nhật năm 2026 cho biết nền tảng Fine-tuning đang được thu hẹp, không mở cho khách hàng mới; một số khách hàng hiện hữu vẫn có thời gian tạo job đến ngày 06/01/2027. Vì vậy, không nên xây kiến trúc phụ thuộc vào Fine-tuning của một nhà cung cấp duy nhất.&#x20;

[image](https://www.google.com/s2/favicons?domain=https://developers.openai.com\&sz=32)

OpenAI API+1

## 4. Xây dựng kho dữ liệu chuyên ngành gạch

Đây là phần quyết định chất lượng chatbot. Mô hình AI tốt nhưng dữ liệu sản phẩm không chuẩn thì chatbot vẫn tư vấn sai.

Tôi đề xuất chia dữ liệu thành 5 nhóm.

### 4.1. Nhóm 1 – Product Knowledge (dữ liệu sản phẩm)

Mỗi sản phẩm nên có một bộ thuộc tính chuẩn hóa.

Ví dụ:

| Trường dữ liệu | Ví dụ                      |
| -------------- | -------------------------- |
| Product ID     | GACH-001                   |
| Tên sản phẩm   | Gạch porcelain vân đá      |
| Kích thước     | 600x1200 mm                |
| Chất liệu      | Porcelain                  |
| Bề mặt         | Matt                       |
| Màu sắc        | Xám                        |
| Họa tiết       | Vân đá marble              |
| Phong cách     | Modern                     |
| Không gian     | Phòng khách                |
| Độ hút nước    | Theo thông số nhà sản xuất |
| Giá            | 320.000đ/m²                |
| Xuất xứ        | Việt Nam                   |
| Hình ảnh       | URL ảnh sản phẩm           |
| Tình trạng     | Còn hàng / Liên hệ         |

Tôi khuyến nghị quản lý sản phẩm trong database riêng hoặc đồng bộ từ WooCommerce sang AI Backend.

Không nên lưu toàn bộ thuộc tính sản phẩm chỉ trong file PDF hoặc văn bản. Các thông tin có cấu trúc như giá, kích thước, tồn kho cần truy vấn chính xác.

### 4.2. Nhóm 2 – Technical Knowledge

Tập hợp tài liệu kiến thức ngành gạch:

- Ceramic và Porcelain khác nhau như thế nào?
- Gạch granite là gì?
- Gạch bóng kính và gạch matt.
- Gạch chống trơn.
- Tiêu chuẩn độ hút nước.
- Độ cứng và khả năng chịu lực.
- Kích thước gạch phù hợp với từng không gian.
- Cách chọn gạch cho nhà vệ sinh, phòng khách, phòng bếp.
- Cách bảo quản và vệ sinh gạch.

Đây là nhóm tài liệu phù hợp nhất với RAG.

### 4.3. Nhóm 3 – Design & Interior Knowledge

Đây là nhóm tạo ra sự khác biệt cho chatbot ngành gạch.

Ví dụ:

- Phòng khách 30m² nên chọn kích thước gạch nào?
- Nhà phong cách Minimalist nên sử dụng gạch gì?
- Nội thất gỗ nên phối với màu gạch nào?
- Phòng nhỏ nên chọn gạch sáng hay tối?
- Gạch vân đá phù hợp với phong cách nào?
- Phối màu gạch lát nền và gạch ốp tường.

Nên xây dựng bộ kiến thức theo dạng:

`Không gian → Phong cách → Màu sắc → Chất liệu → Kích thước → Sản phẩm đề xuất`.

### 4.4. Nhóm 4 – Sales Knowledge

- Chính sách giá.
- Chiết khấu.
- Chính sách giao hàng.
- Đổi trả.
- Bảo hành.
- Thanh toán.
- Địa chỉ showroom.
- Chính sách dành cho đại lý.
- Quy trình báo giá và đặt hàng.

### 4.5. Nhóm 5 – Conversation Knowledge

Đây là dữ liệu được thu thập từ những cuộc hội thoại thực tế.

Ví dụ:

| Câu hỏi                                      | Câu trả lời chuẩn                                |
| -------------------------------------------- | ------------------------------------------------ |
| Gạch 60x60 lát phòng 20m² cần bao nhiêu hộp? | Tính theo diện tích phủ bì/hộp, cộng hao hụt     |
| Nhà có trẻ nhỏ nên chọn gạch gì?             | Ưu tiên bề mặt có độ chống trơn phù hợp          |
| Gạch bóng kính có dễ trơn không?             | Giải thích theo điều kiện khô/ướt và loại bề mặt |
| Phòng khách 40m² nên chọn 80x80 hay 60x120?  | Phân tích theo tỷ lệ không gian và thiết kế      |

Nên lưu lại các câu hỏi chưa trả lời tốt để bổ sung dữ liệu, không cần đưa tất cả hội thoại vào training ngay lập tức.

### 4.6. Quy trình cập nhật dữ liệu

Nguồn dữ liệu

WooCommerce • Excel • PDF • Catalogue • Website

Data Processing

Chuẩn hóa • Làm sạch • Phân loại • Kiểm tra

SQL Database

Thông tin sản phẩm

Vector Database

Kiến thức và ngữ nghĩa

AI Retrieval System

Tìm kiếm và cung cấp dữ liệu cho AI

Khi thay đổi giá hoặc cập nhật sản phẩm, hệ thống chỉ cần đồng bộ database, không cần huấn luyện lại AI.

## 5. So sánh mô hình AI và chi phí

Tôi chia thành 3 nhóm giải pháp.

### 5.1. Các nhà cung cấp AI API

[黒いロゴデザインの有名企業&ブランド事例20選［2026年最新版］ ｜ ロゴデザイン制作をネットで依頼！ ロゴオンライン（法人対応）](https://images.openai.com/static-rsc-4/sEsUUG6UghuFvpzbA7mvaEWtmEYEFVVbtdgDQF-y_3ABkBoKx2lWapcUBwqNnYgV7DM65X00XYDIKAWCLPNernN7brTjfs1WxLdnz30SSFEt4Z2KqE0ky_KEqkmtYQOSHtVWJmvSgWiKJiiRfeRkajl3eZJydtRlk_A2VjwLDpM?purpose=inline)

OpenAI

Phù hợp để xây dựng chatbot tư vấn, phân tích câu hỏi, tìm kiếm sản phẩm và xử lý hình ảnh. Có hệ sinh thái API và streaming.

Ưu tiên thử nghiệm đầu tiên.

[Bảng giá API chính thức](https://developers.openai.com/api/docs/pricing?utm_source=chatgpt.com)

[7+ Google Gemini Logo PNG & SVG Vectors HD Quality](https://images.openai.com/static-rsc-4/EmR9-S1uefad8BFAhHxwcM1P9TFpJ0Ojhg3B5rOLAFbx7j743Jpz0bObokKTWCO53TF6Cc-5GwqkrftmDAZ4JWoVQFud-QxiJ7uInGw1JhFQp3AgGUaLStqy0njPcXayJ7EJ4tfAs4P9-pzbkW7UmmJDWVDEEjD_BAcFD9ivEM4?purpose=inline)

Google Gemini

Đáng thử nghiệm khi chatbot cần xử lý hình ảnh, catalogue và nội dung đa phương thức.

[Bảng giá Gemini API](https://ai.google.dev/gemini-api/docs/pricing?utm_source=chatgpt.com)

[Claude](https://images.openai.com/static-rsc-4/cLHBfiXuOV35_PSb5wC1jXivsAXbOR_8j6QIuMUVJNmHhvqDTZSAjip3DZXEZE8rrigO3zrBPXPTm3OPFqtXG1oYbfxtfR2epfKwRiaR-r0auw26KWwnnPiRgtOfu6qqeScD6KjrINNkb8STcXPBIHcixXdk1xpiKuwC8UdBT_8?purpose=inline)

Anthropic Claude

Lựa chọn đáng so sánh về chất lượng đọc hiểu tài liệu, phân tích và tư vấn dài.

[Bảng giá Claude API](https://platform.claude.com/docs/en/about-claude/pricing?utm_source=chatgpt.com)

[Llama AI: Mô Hình Xử Lý Ngôn Ngữ Lớn Phát Triển Bởi Meta AI](https://images.openai.com/static-rsc-4/jPUUOZrcFNazzcfFkUcoP8-2gTezhwq0PXPY3Kn_TQz30X-D3nWL5m3SkSWDXoah5Qd5rjY5hQltEsrJnu4fwifs6RtvjoaNy4nGCvxdK1I6P97bSnt2ujXiM6o09OczFj85Sxzj1agPObxwtWwOSWWLlMAPLISbNd8hvA_Jjss?purpose=inline)

Llama / Qwen

Có thể triển khai mô hình mở trên máy chủ riêng. Phù hợp khi cần kiểm soát hạ tầng và tối ưu chi phí ở quy mô lớn, nhưng phát sinh chi phí GPU, vận hành và tối ưu.

### 5.2. Cấu hình mô hình tôi đề xuất

Không cần sử dụng một model duy nhất cho tất cả câu hỏi.

| Công việc                 | Model đề xuất    |
| ------------------------- | ---------------- |
| Phân loại ý định          | Model nhỏ, nhanh |
| Hỏi đáp thông số đơn giản | Model nhỏ        |
| Tư vấn phối gạch          | Model tầm trung  |
| Tư vấn chuyên sâu         | Model mạnh       |
| Phân tích ảnh phòng khách | Model có Vision  |

Theo bảng giá API OpenAI tại thời điểm nghiên cứu 10/11/2026, một số mức giá tham khảo ở chế độ Standard:

| Model         | Input / 1 triệu token | Output / 1 triệu token |
| ------------- | --------------------- | ---------------------- |
| GPT-6.1 Terra | $1                    | $5                     |
| GPT-6.1 Sol   | $2                    | $10                    |
| GPT-6 Astra   | $10                   | $50                    |
| GPT-6 Luna    | $0,10                 | $0,50                  |

Giá phụ thuộc chế độ xử lý và cấu hình sử dụng; cần kiểm tra lại trước khi triển khai.&#x20;

[image](https://www.google.com/s2/favicons?domain=https://developers.openai.com\&sz=32)

OpenAI API

Tôi sẽ thử GPT-6 Luna cho các tác vụ đơn giản và GPT-6.1 Terra làm mô hình tư vấn chính, sau đó benchmark với Gemini và Claude bằng cùng một bộ câu hỏi thực tế.

### 5.3. Ước tính chi phí AI cho 30–50 người dùng/ngày

Ta cần đưa ra giả định để tính toán:

- 50 người dùng/ngày.
- Trung bình 5 lượt hỏi/người.
- 30 ngày/tháng.
- 7.500 lượt hỏi/tháng.
- Trung bình mỗi lượt: 2.000 token đầu vào và 500 token đầu ra.

Tính theo GPT-6.1 Terra Standard:

\\[ C = \frac{15M \times 1 + 3.75M \times 5}{1M} \\]

Chi phí LLM ước tính / tháng

# $33,75

Khoảng 850.000đ/tháng nếu quy đổi giả định 25.000đ/USD

| Thành phần              | Chi phí |
| ----------------------- | ------- |
| Input 15 triệu token    | $15     |
| Output 3,75 triệu token | $18,75  |
| Tổng                    | $33,75  |

Chưa bao gồm Vision, hosting, database, VAT, tỷ giá và các dịch vụ bổ sung.

Đây là kịch bản tính toán, không phải báo giá cố định. Nếu lịch sử hội thoại dài, prompt lớn hoặc khách gửi ảnh, chi phí sẽ tăng.

### 5.4. Tổng ngân sách vận hành đề xuất

Với kiến trúc RAG tự quản lý:

| Hạng mục             | Dự toán/tháng      |
| -------------------- | ------------------ |
| LLM API              | 500.000–1.500.000đ |
| VPS AI Backend       | 300.000–800.000đ   |
| Database / Vector DB | 0–300.000đ         |
| Redis / Cache        | 0–200.000đ         |
| Dự phòng phát sinh   | 200.000–500.000đ   |
| Tổng                 | 1–3,3 triệu đồng   |

Đây là dự toán thiết kế ban đầu, giả định sử dụng VPS nhỏ, database gộp và không chạy GPU riêng. Chi phí phát triển phần mềm, đồng bộ catalogue và bảo trì chưa tính vào đây.

Đánh giá

&#x20;Với 30–50 người dùng/ngày, sử dụng LLM API + RAG sẽ kinh tế hơn nhiều so với thuê GPU và tự vận hành mô hình AI.

## 6. Cấu hình kỹ thuật triển khai thực tế

Đối với 30–50 người dùng/ngày, tôi không khuyến nghị kiến trúc quá phức tạp hoặc đầu tư GPU ngay từ đầu.

### 6.1. Công nghệ đề xuất

| Thành phần    | Công nghệ               |
| ------------- | ----------------------- |
| Website       | WordPress               |
| Chat Widget   | JavaScript / React      |
| WP Plugin     | Custom WordPress Plugin |
| Backend       | Python FastAPI          |
| AI            | OpenAI API              |
| Database      | PostgreSQL              |
| Vector Search | pgvector                |
| Cache         | Redis                   |
| Streaming     | SSE                     |
| Server        | VPS Linux               |
| Monitoring    | API logs + dashboard    |

Tại sao chọn FastAPI?

- Nhẹ, xử lý API bất đồng bộ.
- Tích hợp tốt với các AI API.
- Hỗ trợ streaming.
- Dễ mở rộng thêm Vision, CRM, quản lý hội thoại.
- Tách biệt hoàn toàn với WordPress.

WordPress có REST API chính thức, cho phép ứng dụng bên ngoài trao đổi dữ liệu JSON. Do đó, việc kết nối WP với backend riêng không cần xây dựng lại website.&#x20;

[image](https://www.google.com/s2/favicons?domain=https://developer.wordpress.org\&sz=32)

WordPress Developer Resources+1

### 6.2. Cấu trúc dữ liệu nên xây dựng

Tôi đề xuất ít nhất các bảng sau:

| Bảng                | Chức năng            |
| ------------------- | -------------------- |
| products            | Thông tin sản phẩm   |
| product_attributes  | Thuộc tính gạch      |
| product_images      | Hình ảnh             |
| product_prices      | Giá bán              |
| knowledge_documents | Tài liệu kiến thức   |
| knowledge_chunks    | Nội dung đã chia nhỏ |
| chat_sessions       | Phiên hội thoại      |
| chat_messages       | Nội dung hội thoại   |
| chat_feedback       | Đánh giá câu trả lời |
| ai_logs             | Log sử dụng AI       |

Đặc biệt, nên có `product_id` xuyên suốt từ WordPress đến AI Backend để khi AI đề xuất sản phẩm, hệ thống có thể trả về chính xác:

- Hình ảnh sản phẩm.
- Tên sản phẩm.
- Mã sản phẩm.
- Kích thước.
- Giá.
- Link xem chi tiết.

### 6.3. Bảo mật và kiểm soát hệ thống

Có một số điểm không nên bỏ qua:

1. API Key chỉ được lưu ở AI Backend, tuyệt đối không đưa vào JavaScript phía trình duyệt.
2. Xác thực request từ WordPress bằng token ký số hoặc cơ chế xác thực riêng.
3. Giới hạn số lượt hỏi/IP và chống spam.
4. Không cho AI truy vấn SQL tùy ý.
5. Phân quyền dữ liệu quản trị và dữ liệu công khai.
6. Có cơ chế fallback khi nhà cung cấp AI bị lỗi.
7. Có giới hạn token, timeout và ngân sách API.
8. Không cho AI tự suy diễn giá và tồn kho.

## 7. Lộ trình triển khai đề xuất

Tôi chia thành 3 giai đoạn để không đầu tư quá nhiều ngay từ đầu.

Giai đoạn 1 – MVP

Ưu tiên

# 2–3 tuần

- Tạo AI Backend.
- Kết nối WordPress.
- Xây dựng Chat Widget.
- Import catalogue sản phẩm.
- Tích hợp RAG.
- Tư vấn sản phẩm bằng ngôn ngữ tự nhiên.
- Streaming Response.

  Mục tiêu: chatbot hoạt động và tư vấn đúng dữ liệu.

Giai đoạn 2 – Smart AI

# 2–3 tuần

- AI hiểu hình ảnh khách hàng tải lên.
- Tìm kiếm gạch theo phong cách.
- Đề xuất sản phẩm tương tự.
- Tính toán số lượng gạch theo diện tích.
- Tư vấn phối màu và không gian.
- Dashboard quản lý hội thoại.

  Mục tiêu: biến chatbot thành nhân viên tư vấn bán hàng.

Giai đoạn 3 – AI Sales Assistant

# 2–4 tuần

- Thu thập lead khách hàng.
- Tư vấn theo ngân sách.
- Chuyển khách cho nhân viên kinh doanh.
- Tích hợp CRM.
- Theo dõi tỷ lệ chuyển đổi.
- Phân tích các câu hỏi phổ biến.

  Mục tiêu: chatbot hỗ trợ tạo doanh thu.

Tổng thời gian dự kiến: 6–10 tuần, tùy chất lượng catalogue và mức độ hoàn thiện các chức năng.

## 8. Mô hình chatbot tôi muốn hướng tới cho ngành gạch

Không nên chỉ xây dựng chatbot hỏi đáp kiểu:

Khách: Gạch 60x60 có không?

AI: Có, chúng tôi có gạch 60x60.

Mà nên hướng tới một trợ lý tư vấn có khả năng tìm kiếm, so sánh và giới thiệu sản phẩm.

AI TƯ VẤN GẠCH

Trợ lý lựa chọn vật liệu nội thất

Online

Tôi muốn tìm gạch lát nền phòng khách 30m², phong cách hiện đại, màu xám, kích thước 60x120. Ngân sách khoảng 300.000đ/m².

AI tư vấn

Với phòng khách 30m² theo phong cách hiện đại, tôi đề xuất ưu tiên gạch porcelain vân đá màu xám, bề mặt matt hoặc bán bóng tùy phong cách nội thất.

Một số sản phẩm phù hợp:

[PISO CERÁMICA LUMINOVA GRIGIO 60 X 120 CM GRIS TIPO MÁRMOL CAJA CON 1.44 M2 | The Home Depot México - Ventas a profesionales](https://images.openai.com/static-rsc-4/vHJGZNAv98tX4lNEEyUHel3_tohumyoOgrzk5thiKZyPCOP164oGkVgdqovQHOY_hzXND4-L49vCIDuRFtOy2CucKOPVflSr6Wo8EcAys2SY16epBZpoqB3tYOvMu0qxUm_Pt48-BYjAu5UszsQ6SV_ByCxEGN1z_jzQTPkLmQM?purpose=inline)

Gạch xám vân đá

60x120 • Matt

Theo giá catalogue

[BODENFLIESE BETONOPTIK GARE DU NORD HELLGRAU MATT 60X120 DADO CERAMICA | NX23313](https://images.openai.com/static-rsc-4/qnITuZ1sh7tymmpt5gZD-WwJUM0RSIKl236_Sg1xG8cc_y-0Uan_02ER41uaMMv50H6gw3Jng5ui0tcixCPAEd_YT3RIFzPa7AyrLNNMU2EzCXYkLn9FEXKdUDjBsSPvjWt0toz3VyyoRW0T3oFuOzJ3dgjpRVtmckRNG88hqEA?purpose=inline)

Gạch xám xi măng

60x120 • Matt

Theo giá catalogue

Hình ảnh minh họa, không phải sản phẩm tồn tại trong catalogue thực tế.

Bạn có thể cho tôi biết thêm:

- Bạn thích bề mặt matt hay bóng?
- Nội thất phòng khách chủ yếu là gỗ hay đá?

Nhập câu hỏi của bạn...

Minh họa giao diện chatbot mục tiêu.

## 9. Kết luận và phương án tôi lựa chọn

Nếu trực tiếp thiết kế hệ thống này, tôi sẽ chọn:

| Hạng mục                   | Quyết định                 |
| -------------------------- | -------------------------- |
| Kiến trúc                  | 1 WordPress + 1 AI Backend |
| Backend                    | FastAPI                    |
| AI chính                   | GPT-6.1 Terra              |
| AI phụ                     | GPT-6 Luna                 |
| Kiến thức                  | RAG                        |
| Tìm kiếm                   | Hybrid Search              |
| Database                   | PostgreSQL + pgvector      |
| Cache                      | Redis                      |
| Giao tiếp                  | REST API + SSE             |
| Quy mô                     | 30–50 user/ngày            |
| Ngân sách vận hành ban đầu | 1–3,3 triệu/tháng          |

Có 3 điều tôi muốn nhấn mạnh:

- Không tự train LLM từ đầu.
- Không dùng Fine-tuning để cập nhật catalogue và giá bán.
- Không để WordPress trực tiếp gọi AI API. Hãy để backend làm trung gian.

## 10. Tôi cần xác định thêm 4 thông tin để thiết kế kiến trúc chính xác

Có một số khác biệt lớn giữa chatbot tư vấn gạch thông thường và chatbot bán hàng có khả năng hiểu hình ảnh, tìm kiếm sản phẩm trong catalogue.

1\. Dữ liệu sản phẩm hiện tại đang lưu ở đâu?

WooCommerce / WordPress

File Excel

Catalogue PDF

Kết hợp nhiều nguồn

Chưa có dữ liệu chuẩn

2\. Số lượng SKU sản phẩm dự kiến?

Dưới 1.000 SKU

1.000–5.000 SKU

5.000–20.000 SKU

Trên 20.000 SKU

3\. Mục tiêu chính của chatbot?

Tư vấn và bán hàng

Tìm kiếm và đề xuất sản phẩm

Chuyên gia kiến thức ngành gạch

Tất cả các chức năng trên

4\. Có cần khách hàng gửi ảnh phòng để AI tư vấn loại gạch phù hợp không?

Có – cần AI phân tích hình ảnh

Có, nhưng triển khai giai đoạn 2

Không cần

Thông tin bổ sung (nếu có)

Gửi thông tin và thiết kế kiến trúc chi tiết

Từ các thông tin này có thể xác định chính xác cấu hình server, mô hình AI, cách đồng bộ catalogue và dự toán chi phí. Mục tiêu là xây dựng một nền tảng AI có thể dùng chung cho nhiều website bán gạch sau này, chứ không chỉ là một plugin chat đơn giản.
