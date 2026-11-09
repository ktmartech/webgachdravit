firecrawl
# 11. Hệ thống Báo giá & AI Gợi ý Sản phẩm

> Quyết định: D-XX (AI-powered Quote System), D-YY (Conversational Product Finder)
> Skill: `wordpress`, `ai-integration`, `wp-rest-api`, `form-cro`, `backend-security-coder`

## 1. Tổng quan

### 1.1 Mục tiêu

Nâng cấp trải nghiệm mua hàng từ catalog thụ động sang hệ thống tư vấn chủ động, giúp khách hàng:
- **Tính năng 1 (Quote Builder):** Tự tạo bảng báo giá nhiều sản phẩm, để lại thông tin → chuyển về hệ thống CRM/sale.
- **Tính năng 2 (AI Tile Finder):** Nhập nhu cầu bằng ngôn ngữ tự nhiên → AI gợi ý sản phẩm phù hợp dựa trên thuộc tính, ngữ cảnh và ràng buộc.

### 1.2 Vị trí trong roadmap

| Phase | Tính năng |
|---|---|
| **Phase 2** (sau Phase 1 hoàn thành) | Tính năng 1 — Quote Builder cơ bản (không AI) |
| **Phase 3** | Tính năng 2 — AI Tile Finder với LLM integration |
| **Phase 4+** | Nâng cấp: Visual similarity search, AR preview, Multi-room quote |

### 1.3 Nguyên tắc thiết kế

1. **Progressive Enhancement:** Quote Builder hoạt động ngay cả khi JS bị tắt (form fallback).
2. **Privacy-first:** Dữ liệu khách hàng không gửi ra ngoài khi huấn luyện AI (chỉ metadata sản phẩm).
3. **No Vendor Lock-in:** AI model có thể swap giữa OpenAI, Anthropic Claude, hoặc self-hosted Llama.
4. **Tuân thủ kiến trúc Dravit:**
   - Logic nằm trong theme namespace `Dravit\Quote` & `Dravit\AiTileFinder`.
   - Không tạo custom DB tables (dùng WC Order + post meta).
   - Vanilla JS modules + REST API.

---

## 2. Tính năng 1: Quote Builder (Báo giá Đa Sản phẩm)

### 2.1 User Flow

```mermaid
flowchart TD
    A[Khách duyệt sản phẩm] --> B{Thêm vào Quote}
    B -->|Click CTA Thêm vào báo giá| C[Mở Quote Panel Sidebar]
    C --> D[Điều chỉnh số lượng/xóa sản phẩm]
    D --> E[Nhập thông tin liên hệ]
    E --> F[Gửi yêu cầu báo giá]
    F --> G[Tạo quote draft trong hệ thống]
    G --> H[Email thông báo cho sale]
    G --> I[Email xác nhận cho khách]
    H --> J[Sale xem quote trong admin panel]
    J --> K[Sale liên hệ khách hàng]
```

### 2.2 Components

#### 2.2.1 Frontend (Public)

**A. CTA "Thêm vào báo giá"**
- Vị trí:
  - Trang sản phẩm đơn: bên cạnh nút "Thêm vào giỏ hàng"
  - Card sản phẩm (grid/list): icon trên toolbar
  - Quick view popup
- Action:
  - Click → gọi REST endpoint `POST /wp-json/dravit/v1/quote/add-item`
  - Lưu quote items vào **session PHP** (transient `_drv_quote_{session_id}`) hoặc **localStorage** (guest).
  - User đã đăng nhập → gắn vào `user_meta` `drv_quote_draft`.

**B. Quote Panel (Sidebar Offcanvas)**
```html
<drv-quote-panel data-drv-lazy="quote-panel">
  <header>
    <h2>Bảng báo giá của bạn</h2>
    <button data-action="close"><i class="ph ph-x"></i></button>
  </header>

  <div class="quote-items">
    <!-- Mỗi item -->
    <div class="quote-item" data-product-id="123">
      <img src="thumb.webp" alt="Gạch DRV-8801M" width="64" height="64">
      <div class="info">
        <h3>Gạch DRV-8801M</h3>
        <p class="price">246.400đ/viên · 385.000đ/m²</p>
        <drv-qty-input value="12" min="3" step="3"></drv-qty-input>
        <p class="summary">12 viên · 7,68 m² · 4 thùng</p>
      </div>
      <button data-action="remove"><i class="ph ph-trash"></i></button>
    </div>
  </div>

  <div class="quote-summary">
    <dl>
      <dt>Tổng số sản phẩm:</dt><dd>3 loại</dd>
      <dt>Tổng diện tích:</dt><dd>~42,5 m²</dd>
      <dt>Tạm tính:</dt><dd>16.250.000đ</dd>
    </dl>
    <small>* Giá chưa bao gồm vận chuyển & thi công</small>
  </div>

  <form class="quote-contact-form" data-drv-action="submit-quote">
    <input name="customer_name" placeholder="Họ tên *" required>
    <input name="customer_phone" type="tel" placeholder="Số điện thoại *" required>
    <input name="customer_email" type="email" placeholder="Email">
    <textarea name="customer_note" placeholder="Ghi chú (địa chỉ, yêu cầu...)"></textarea>
    <label>
      <input type="checkbox" name="consent" required>
      Tôi đồng ý cho Dravit liên hệ để tư vấn
    </label>
    <button type="submit" class="btn-primary">Gửi yêu cầu báo giá</button>
  </form>
</drv-quote-panel>
```

**C. JavaScript Module (`src/js/quote-panel.js`)**
```javascript
class QuotePanelController extends HTMLElement {
  connectedCallback() {
    this.loadItems();
    this.attachEventListeners();
  }

  async loadItems() {
    const items = await fetch('/wp-json/dravit/v1/quote/items').then(r => r.json());
    this.renderItems(items);
  }

  async addItem(productId, quantity) {
    await fetch('/wp-json/dravit/v1/quote/add-item', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({product_id: productId, quantity})
    });
    this.loadItems();
    this.open();
  }

  async submitQuote(formData) {
    const response = await fetch('/wp-json/dravit/v1/quote/submit', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        items: this.items,
        customer: formData
      })
    });

    if (response.ok) {
      this.showSuccess('Báo giá đã được gửi! Chúng tôi sẽ liên hệ bạn trong 30 phút.');
      this.clear();
    }
  }
}
customElements.define('drv-quote-panel', QuotePanelController);
```

#### 2.2.2 Backend (PHP)

**A. Data Model**

Lưu trữ quote draft dưới dạng **WooCommerce Order** với:
- `post_type` = `shop_order`
- `post_status` = `drv-quote-draft` (custom status)
- Meta keys:
  - `_drv_quote_type` = `customer_request` (phân biệt với order thường)
  - `_drv_quote_source` = `quote_builder` hoặc `ai_finder`
  - `_customer_note` = ghi chú khách hàng
  - `_drv_submitted_at` = timestamp

Line items lưu bình thường qua WC Order Item API.

**B. REST API Endpoints**

```php
namespace Dravit\Quote;

class QuoteController {
  public function register_routes() {
    register_rest_route('dravit/v1', '/quote/items', [
      'methods'  => 'GET',
      'callback' => [$this, 'get_items'],
      'permission_callback' => '__return_true'
    ]);

    register_rest_route('dravit/v1', '/quote/add-item', [
      'methods'  => 'POST',
      'callback' => [$this, 'add_item'],
      'permission_callback' => '__return_true',
      'args' => [
        'product_id' => ['required' => true, 'type' => 'integer'],
        'quantity'   => ['required' => true, 'type' => 'integer']
      ]
    ]);

    register_rest_route('dravit/v1', '/quote/submit', [
      'methods'  => 'POST',
      'callback' => [$this, 'submit_quote'],
      'permission_callback' => '__return_true',
      'args' => [
        'items' => ['required' => true, 'type' => 'array'],
        'customer' => ['required' => true, 'type' => 'object']
      ]
    ]);
  }

  public function submit_quote(\WP_REST_Request $request): \WP_REST_Response {
    $items = $request['items'];
    $customer = $request['customer'];

    // Validate
    if (empty($items) || count($items) > 20) {
      return new \WP_REST_Response(['error' => 'Invalid items count'], 400);
    }

    // Sanitize
    $customer_name  = sanitize_text_field($customer['customer_name']);
    $customer_phone = sanitize_text_field($customer['customer_phone']);
    $customer_email = sanitize_email($customer['customer_email'] ?? '');
    $customer_note  = sanitize_textarea_field($customer['customer_note'] ?? '');

    // Tạo WC Order
    $order = wc_create_order(['status' => 'drv-quote-draft']);
    $order->set_customer_note($customer_note);
    $order->update_meta_data('_drv_quote_type', 'customer_request');
    $order->update_meta_data('_drv_quote_source', 'quote_builder');
    $order->update_meta_data('_drv_submitted_at', time());
    $order->update_meta_data('_billing_first_name', $customer_name);
    $order->update_meta_data('_billing_phone', $customer_phone);
    $order->update_meta_data('_billing_email', $customer_email);

    foreach ($items as $item) {
      $product = wc_get_product($item['product_id']);
      if (!$product) continue;

      $order->add_product($product, $item['quantity']);
    }

    $order->calculate_totals();
    $order->save();

    // Gửi email cho admin/sale
    $this->send_quote_notification($order);

    // Gửi email xác nhận cho khách
    $this->send_customer_confirmation($order, $customer_email);

    // Xóa session quote
    $this->clear_session_quote();

    return new \WP_REST_Response([
      'success' => true,
      'quote_id' => $order->get_id()
    ], 200);
  }

  private function send_quote_notification(\WC_Order $order): void {
    $to = get_option('drv_settings')['quote_email'] ?? get_option('admin_email');
    $subject = sprintf('[DRAVIT] Báo giá mới #%d từ %s',
      $order->get_id(),
      $order->get_meta('_billing_first_name')
    );

    $message = sprintf(
      "Khách hàng: %s\nSĐT: %s\nEmail: %s\n\nSản phẩm:\n%s\n\nTổng tạm tính: %s\n\nGhi chú:\n%s\n\nXem chi tiết: %s",
      $order->get_meta('_billing_first_name'),
      $order->get_meta('_billing_phone'),
      $order->get_meta('_billing_email'),
      $this->format_items_text($order),
      wc_price($order->get_total()),
      $order->get_customer_note(),
      admin_url('post.php?post=' . $order->get_id() . '&action=edit')
    );

    wp_mail($to, $subject, $message);
  }
}
```

**C. Admin Panel Integration**

```php
// Thêm custom order status
add_action('init', function() {
  register_post_status('drv-quote-draft', [
    'label' => 'Báo giá chờ xử lý',
    'public' => false,
    'exclude_from_search' => false,
    'show_in_admin_all_list' => true,
    'show_in_admin_status_list' => true,
    'label_count' => _n_noop(
      'Báo giá chờ xử lý <span class="count">(%s)</span>',
      'Báo giá chờ xử lý <span class="count">(%s)</span>'
    )
  ]);
});

// Thêm vào dropdown status WC
add_filter('wc_order_statuses', function($statuses) {
  $statuses['drv-quote-draft'] = 'Báo giá chờ xử lý';
  return $statuses;
});

// Custom columns trong order list
add_filter('manage_edit-shop_order_columns', function($columns) {
  $columns['quote_type'] = 'Loại';
  return $columns;
});

add_action('manage_shop_order_posts_custom_column', function($column, $post_id) {
  if ($column === 'quote_type') {
    $order = wc_get_order($post_id);
    $type = $order->get_meta('_drv_quote_type');
    if ($type === 'customer_request') {
      echo '<span class="badge">Báo giá</span>';
    }
  }
}, 10, 2);
```

### 2.3 UX Enhancements

1. **Auto-save:** Quote items tự động lưu vào session/localStorage mỗi khi thay đổi.
2. **Persistent cart:** Khách quay lại sau vẫn thấy quote items (dựa trên session cookie hoặc user meta).
3. **Mobile-friendly:** Quote panel hiển thị dạng bottom sheet trên mobile.
4. **Share quote:** Tạo URL tạm `?quote={hash}` để khách có thể chia sẻ quote với người khác (xem only, hết hạn sau 7 ngày).

### 2.4 Security & Validation

1. **Rate limiting:** Giới hạn 5 quote submissions/IP/giờ (dùng transient `_drv_quote_limit_{ip}`).
2. **Honeypot field:** Thêm trường ẩn `drv_website` (bot thường điền) → reject nếu có giá trị.
3. **reCAPTCHA v3:** Tích hợp invisible reCAPTCHA, reject nếu score < 0.5.
4. **Sanitization:** Tất cả input đều sanitize (`sanitize_text_field`, `sanitize_email`, `absint`).
5. **Max items:** Giới hạn tối đa 20 sản phẩm/quote.

---

## 3. Tính năng 2: AI Tile Finder (Gợi ý Thông minh)

### 3.1 User Experience

**A. Conversational Interface**

```
┌─────────────────────────────────────────────────────────────┐
│  🤖 Dravit AI — Trợ lý tìm gạch                            │
├─────────────────────────────────────────────────────────────┤
│  👋 Xin chào! Tôi có thể giúp bạn tìm loại gạch phù hợp.   │
│  Hãy cho tôi biết:                                          │
│                                                             │
│  • Bạn muốn lát/ốp cho không gian nào?                     │
│  • Diện tích khoảng bao nhiêu m²?                          │
│  • Phong cách bạn yêu thích?                               │
│  • Ngân sách dự kiến?                                      │
│                                                             │
│  Hoặc gõ tự do, ví dụ: "Tôi cần gạch lát nền phòng khách  │
│  50m2, màu sáng, phong cách hiện đại, giá tầm 300-400k/m²" │
├─────────────────────────────────────────────────────────────┤
│  [_______________________________________________] [Gửi ➤]  │
└─────────────────────────────────────────────────────────────┘
```

**B. Interaction Flow**

```mermaid
sequenceDiagram
    participant U as User
    participant AI as AI Finder
    participant LLM as LLM API
    participant DB as Product DB
    participant UI as UI

    U->>AI: "Cần gạch phòng khách 50m2, sáng, hiện đại, 300-400k"
    AI->>LLM: Parse intent & extract params
    LLM-->>AI: {space: phong-khach, area: 50, style: hien-dai, ...}
    AI->>DB: Query products matching criteria
    DB-->>AI: [Product IDs + scores]
    AI->>LLM: Generate explanation & suggestions
    LLM-->>AI: "Dựa trên nhu cầu của bạn, tôi gợi ý..."
    AI->>UI: Render results
    UI->>U: Show 5-8 products + explanation

    U->>AI: "Có loại nào nhám hơn không?"
    AI->>LLM: Refine with constraint {be_mat: nham}
    LLM-->>AI: Updated params
    AI->>DB: Re-query with new constraint
    DB-->>AI: Filtered results
    AI->>UI: Update results
```

### 3.2 AI Architecture

#### 3.2.1 Tech Stack Options

| Component | Option A (Cloud) | Option B (Hybrid) | Option C (Self-hosted) |
|---|---|---|---|
| LLM | OpenAI GPT-4 Turbo | Anthropic Claude 3 Opus | Llama 3.1 70B on RunPod |
| Embeddings | OpenAI text-embedding-3-small | Cohere Embed v3 | all-MiniLM-L6-v2 local |
| Vector DB | Pinecone free tier | Weaviate Cloud | ChromaDB SQLite |
| Cost (1000 queries) | ~$15-20 | ~$10-15 | ~$5 (GPU rental) |
| Latency | 1-3s | 2-4s | 3-5s |
| Privacy | Data sent to OpenAI | Data sent to Anthropic | 100% on-premise |

**Khuyến nghị Phase 3:** Option B (Anthropic Claude) — balance giữa cost, latency, và privacy.

#### 3.2.2 System Prompt Template

```
Bạn là trợ lý AI của Dravit — chuyên gia tư vấn gạch ốp lát cao cấp tại Việt Nam.

## Nhiệm vụ
Phân tích yêu cầu của khách hàng và gợi ý 5-8 sản phẩm gạch phù hợp nhất từ catalog.

## Thông tin catalog
{PRODUCT_METADATA_JSON}

## Quy tắc gợi ý
1. Ưu tiên sản phẩm phù hợp 100% với ràng buộc bắt buộc (không gian, DCOF nếu có).
2. Cân bằng giữa phong cách, màu sắc, và ngân sách.
3. Nếu ngân sách chặt, gợi ý sản phẩm trong khoảng ±10%.
4. Nếu không đủ sản phẩm khớp 100%, giải thích trade-off (vd: "Gạch này vượt ngân sách 5% nhưng độ bền cao hơn").
5. Luôn trả về danh sách product IDs dạng JSON array.

## Format output
{
  "reasoning": "Giải thích ngắn gọn (1-2 câu) tại sao gợi ý những sản phẩm này",
  "product_ids": [123, 456, 789],
  "clarification": "Câu hỏi làm rõ nếu cần (hoặc null)"
}

## Input khách hàng
{USER_INPUT}
```

#### 3.2.3 Product Metadata Indexing

**Tạo index khi sản phẩm được publish/update:**

```php
namespace Dravit\AiTileFinder;

class ProductIndexer {
  public function index_product(int $product_id): void {
    $product = wc_get_product($product_id);

    // Trích xuất metadata
    $metadata = [
      'id' => $product_id,
      'name' => $product->get_name(),
      'sku' => $product->get_sku(),
      'price_m2' => (int) $product->get_meta('drv_price_m2'),
      'price_band' => $this->get_price_band($product),
      'attributes' => [
        'kich_thuoc' => $this->get_attr_slugs($product, 'pa_kich-thuoc'),
        'mau_sac' => $this->get_attr_slugs($product, 'pa_mau-sac'),
        'be_mat' => $this->get_attr_slugs($product, 'pa_be-mat'),
        'phong_cach' => $this->get_attr_slugs($product, 'pa_phong-cach'),
        'khong_gian' => $this->get_attr_slugs($product, 'pa_khong-gian'),
        'cong_nang' => $this->get_attr_slugs($product, 'pa_cong-nang'),
        'van_gach' => $this->get_attr_slugs($product, 'pa_van-gach'),
        'dcof' => $this->get_attr_slugs($product, 'pa_dcof'),
      ],
      'collections' => wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']),
      'description_short' => wp_strip_all_tags($product->get_short_description()),
    ];

    // Lưu vào option (hoặc custom table nếu > 1000 sản phẩm)
    $index = get_option('drv_ai_product_index', []);
    $index[$product_id] = $metadata;
    update_option('drv_ai_product_index', $index);

    // Tạo embedding (nếu dùng vector search)
    if ($this->should_use_embeddings()) {
      $text = $this->product_to_text($metadata);
      $embedding = $this->get_embedding($text);
      $this->store_embedding($product_id, $embedding);
    }
  }

  private function product_to_text(array $metadata): string {
    return sprintf(
      "%s. Giá %s. Kích thước %s. Màu sắc %s. Bề mặt %s. Phong cách %s. Dùng cho %s.",
      $metadata['name'],
      number_format($metadata['price_m2']) . 'đ/m²',
      implode(', ', $metadata['attributes']['kich_thuoc']),
      implode(', ', $metadata['attributes']['mau_sac']),
      implode(', ', $metadata['attributes']['be_mat']),
      implode(', ', $metadata['attributes']['phong_cach']),
      implode(', ', $metadata['attributes']['khong_gian'])
    );
  }
}
```

#### 3.2.4 Query Pipeline

```php
namespace Dravit\AiTileFinder;

class QueryEngine {
  private LlmClient $llm;
  private ProductIndex $index;

  public function find_products(string $user_input, array $context = []): array {
    // Bước 1: Parse user input bằng LLM
    $parsed = $this->llm->parse_intent($user_input, $context);

    // Bước 2: Build query từ parsed params
    $query = new ProductQuery();

    if (isset($parsed['khong_gian'])) {
      $query->add_filter('pa_khong-gian', $parsed['khong_gian']);
    }

    if (isset($parsed['price_min'], $parsed['price_max'])) {
      $query->add_meta_query([
        'key' => 'drv_price_m2',
        'value' => [$parsed['price_min'], $parsed['price_max']],
        'type' => 'NUMERIC',
        'compare' => 'BETWEEN'
      ]);
    }

    if (isset($parsed['phong_cach'])) {
      $query->add_filter('pa_phong-cach', $parsed['phong_cach']);
    }

    // Bước 3: Query database
    $candidates = $query->get_results(); // WP_Query

    // Bước 4: Rerank bằng LLM hoặc vector similarity
    if ($this->should_use_reranking()) {
      $candidates = $this->rerank_with_llm($candidates, $user_input, $parsed);
    }

    // Bước 5: Limit top N
    $top_products = array_slice($candidates, 0, 8);

    // Bước 6: Generate explanation
    $explanation = $this->llm->explain_recommendations($top_products, $parsed);

    return [
      'products' => $top_products,
      'explanation' => $explanation,
      'parsed_params' => $parsed,
      'total_matches' => count($candidates)
    ];
  }

  private function rerank_with_llm(array $candidates, string $user_input, array $parsed): array {
    // Chuẩn bị prompt
    $candidates_json = json_encode(array_map(function($p) {
      return [
        'id' => $p->get_id(),
        'name' => $p->get_name(),
        'price_m2' => $p->get_meta('drv_price_m2'),
        'attributes' => $this->get_product_attributes($p)
      ];
    }, $candidates));

    $prompt = sprintf(
      "User query: %s\n\nParsed intent: %s\n\nCandidate products:\n%s\n\nRank these products by relevance (return array of product IDs in order).",
      $user_input,
      json_encode($parsed),
      $candidates_json
    );

    $ranked_ids = $this->llm->complete($prompt);

    // Sắp xếp lại candidates theo ranked_ids
    $id_map = [];
    foreach ($candidates as $c) {
      $id_map[$c->get_id()] = $c;
    }

    $ranked = [];
    foreach ($ranked_ids as $id) {
      if (isset($id_map[$id])) {
        $ranked[] = $id_map[$id];
      }
    }

    return $ranked;
  }
}
```

#### 3.2.5 LLM Client Abstraction

```php
namespace Dravit\AiTileFinder;

interface LlmClient {
  public function parse_intent(string $input, array $context): array;
  public function explain_recommendations(array $products, array $params): string;
  public function complete(string $prompt): mixed;
}

class AnthropicClient implements LlmClient {
  private string $api_key;
  private string $model = 'claude-3-5-sonnet-20241022';

  public function parse_intent(string $input, array $context): array {
    $system_prompt = $this->load_system_prompt('parse_intent');

    $response = $this->call_api([
      'model' => $this->model,
      'max_tokens' => 1024,
      'system' => $system_prompt,
      'messages' => [
        ['role' => 'user', 'content' => $input]
      ]
    ]);

    $content = $response['content'][0]['text'];
    return json_decode($content, true);
  }

  private function call_api(array $payload): array {
    $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
      'headers' => [
        'x-api-key' => $this->api_key,
        'anthropic-version' => '2023-06-01',
        'content-type' => 'application/json'
      ],
      'body' => json_encode($payload),
      'timeout' => 15
    ]);

    if (is_wp_error($response)) {
      throw new \Exception('LLM API error: ' . $response->get_error_message());
    }

    return json_decode(wp_remote_retrieve_body($response), true);
  }
}
```

### 3.3 UI Components

**A. Chat Widget**

```html
<drv-ai-finder data-drv-lazy="ai-finder">
  <div class="chat-messages" id="ai-messages">
    <!-- AI messages append here -->
  </div>

  <form class="chat-input-form" data-drv-action="ai-query">
    <textarea
      name="query"
      placeholder="Nhập yêu cầu của bạn..."
      rows="2"
      maxlength="500"
    ></textarea>
    <button type="submit" aria-label="Gửi">
      <i class="ph ph-paper-plane-tilt"></i>
    </button>
  </form>

  <div class="chat-suggestions">
    <button data-suggestion="Gạch lát nền phòng khách 40m2 phong cách tối giản">
      Phòng khách tối giản
    </button>
    <button data-suggestion="Gạch ốp nhà tắm chống trơn giá tầm 300k">
      Nhà tắm chống trơn
    </button>
  </div>
</drv-ai-finder>
```

**B. Results Display**

Sau khi AI trả về kết quả, render dạng grid sản phẩm kèm explanation:

```html
<div class="ai-results">
  <div class="ai-explanation">
    <i class="ph ph-sparkle"></i>
    <p>Dựa trên nhu cầu phòng khách 50m², phong cách hiện đại và ngân sách
       300-400k/m², tôi gợi ý 6 sản phẩm sau. Tất cả đều có bề mặt mờ/bán bóng
       phù hợp phong cách tối giản.</p>
  </div>

  <div class="product-grid">
    <!-- Render products như catalog grid -->
    <?php foreach ($results['products'] as $product): ?>
      <?php drv_ui('product-card', ['product' => $product, 'context' => 'ai']); ?>
    <?php endforeach; ?>
  </div>

  <div class="ai-actions">
    <button data-action="refine">🔍 Lọc thêm</button>
    <button data-action="add-all-to-quote">➕ Thêm tất cả vào báo giá</button>
  </div>
</div>
```

### 3.4 Training Data & Fine-tuning

#### 3.4.1 Seed Data Collection

**Nguồn dữ liệu:**
1. **Product catalog:** 28 collections × ~15 SKU = ~420 sản phẩm (Phase 1).
2. **Customer quotes:** Lưu trữ user queries từ Quote Builder (opt-in) → training set.
3. **Manual curation:** Team sales viết 50-100 query examples phổ biến.

**Ví dụ training examples:**

```json
[
  {
    "input": "Tôi cần gạch lát phòng khách 45m2, màu xám nhạt, vân đá marble, giá khoảng 350-450k/m²",
    "output": {
      "khong_gian": "phong-khach",
      "area_m2": 45,
      "mau_sac": ["xam"],
      "van_gach": ["van-da-marble"],
      "price_min": 350000,
      "price_max": 450000,
      "cong_nang": "lat-nen"
    },
    "recommended_products": [123, 456, 789]
  },
  {
    "input": "Nhà tắm cần gạch chống trơn, kích thước nhỏ, giá rẻ",
    "output": {
      "khong_gian": "nha-tam",
      "dcof": ["r10", "r11", "r12", "r13"],
      "kich_thuoc": ["30x30", "30x60", "7-5x30"],
      "price_max": 300000,
      "be_mat": ["nham"]
    }
  },
  {
    "input": "Gạch ốp tường bếp, dễ lau chùi, màu trắng hoặc kem",
    "output": {
      "khong_gian": "phong-bep",
      "cong_nang": "op-tuong",
      "be_mat": ["bong-kieng", "ban-bong"],
      "mau_sac": ["trang", "kem"]
    }
  }
]
```

**Script sinh training data từ catalog:**

```php
class TrainingDataGenerator {
  public function generate_from_catalog(): array {
    $templates = [
      "Cần gạch {cong_nang} {khong_gian} {area}m2, {phong_cach}, giá {price_range}",
      "Tìm gạch {kich_thuoc} màu {mau_sac} cho {khong_gian}",
      "Gạch {be_mat} {van_gach} dùng {khong_gian}, ngân sách {price_range}",
    ];

    $variations = [
      'cong_nang' => ['lát nền', 'ốp tường', 'trang trí'],
      'khong_gian' => ['phòng khách', 'nhà tắm', 'phòng bếp', 'sân vườn'],
      'phong_cach' => ['hiện đại', 'tối giản', 'wabi-sabi', 'tân cổ điển'],
      'price_range' => ['dưới 300k', '300-400k', '400-600k', 'cao cấp'],
    ];

    $examples = [];
    foreach ($this->get_all_products() as $product) {
      $query = $this->generate_query($product, $templates, $variations);
      $params = $this->extract_params($product);

      $examples[] = [
        'input' => $query,
        'output' => $params,
        'recommended_products' => [$product->get_id()]
      ];
    }

    return $examples;
  }
}
```

#### 3.4.2 Feedback Loop

Sau mỗi query, hỏi user feedback:

```html
<div class="ai-feedback">
  <p>Kết quả có hữu ích không?</p>
  <button data-feedback="helpful">👍 Hữu ích</button>
  <button data-feedback="not-helpful">👎 Chưa đúng</button>
</div>
```

Lưu feedback vào `wp_postmeta`:
```php
add_action('wp_ajax_drv_ai_feedback', function() {
  $query_id = absint($_POST['query_id']);
  $feedback = sanitize_text_field($_POST['feedback']); // helpful | not-helpful

  update_post_meta($query_id, '_drv_ai_feedback', $feedback);

  // Nếu not-helpful, yêu cầu lý do
  if ($feedback === 'not-helpful' && !empty($_POST['reason'])) {
    update_post_meta($query_id, '_drv_ai_feedback_reason', sanitize_textarea_field($_POST['reason']));
  }
});
```

Định kỳ review feedback → cải thiện prompt hoặc fine-tune model.

---

## 4. Workflow Chi tiết

### 4.1 Phase 2 — Quote Builder (4 tuần)

| Tuần | Công việc |
|---|---|
| **W1** | Setup REST API endpoints · Quote data model · Session/localStorage logic |
| **W2** | Frontend: Quote panel UI · Add to quote CTA · Quantity controls |
| **W3** | Backend: Submit quote → WC Order · Email notifications · Admin panel |
| **W4** | Testing · Security audit · UX polish · Documentation |

**Deliverables:**
- [ ] REST API `/wp-json/dravit/v1/quote/*` hoàn chỉnh
- [ ] Quote panel responsive (mobile + desktop)
- [ ] Admin có thể xem danh sách quote, chuyển thành đơn hàng
- [ ] Email template cho admin và khách
- [ ] Security: rate limiting, honeypot, sanitization
- [ ] Test coverage ≥ 80% (unit tests cho API)

### 4.2 Phase 3 — AI Tile Finder (6-8 tuần)

| Tuần | Công việc |
|---|---|
| **W1-2** | Chọn LLM provider · Setup API · Tạo system prompts · Product indexer |
| **W3** | Query engine: parse intent → query DB → rerank |
| **W4** | Chat UI: message bubbles · product results display · suggestions |
| **W5** | Training data generation · Feedback loop · Analytics |
| **W6** | Multi-turn conversation · Context retention · Refinement queries |
| **W7** | Performance optimization · Caching · Fallback khi API down |
| **W8** | QA testing · A/B testing setup · Launch |

**Deliverables:**
- [ ] LLM integration với fallback graceful khi API lỗi
- [ ] Chat interface mượt mà (typing indicator, streaming response)
- [ ] 90%+ accuracy cho 50 test cases thông dụng
- [ ] Latency P95 < 5s (từ submit → hiển thị kết quả)
- [ ] Cost tracking dashboard (monitor API usage)
- [ ] Feedback collection system
- [ ] A/B test: AI finder vs. traditional filters (conversion rate)

### 4.3 Phase 4+ — Advanced Features

- **Visual search:** Upload ảnh phòng/gạch mẫu → tìm gạch tương tự (dùng CLIP embeddings).
- **Multi-room quoting:** Tạo quote cho nhiều phòng cùng lúc (phòng khách + nhà tắm + bếp).
- **AR preview:** Xem gạch trên sàn nhà bằng AR (WebXR API).
- **Voice input:** Hỏi bằng giọng nói (Web Speech API).

---

## 5. Cấu trúc Code

### 5.1 Thư mục

```text
wp-content/themes/dravit/
├── inc/
│   ├── Quote/
│   │   ├── QuoteController.php        # REST API endpoints
│   │   ├── QuoteSession.php           # Session/localStorage management
│   │   ├── QuoteOrder.php             # Tạo WC Order từ quote
│   │   ├── QuoteEmail.php             # Email templates
│   │   └── QuoteAdmin.php             # Admin panel customization
│   │
│   └── AiTileFinder/
│       ├── QueryEngine.php            # Main query pipeline
│       ├── LlmClient.php              # Interface + implementations
│       ├── AnthropicClient.php        # Anthropic Claude client
│       ├── OpenAiClient.php           # OpenAI GPT client (fallback)
│       ├── ProductIndexer.php         # Index products for AI
│       ├── ProductQuery.php           # Query builder
│       ├── TrainingDataGenerator.php  # Generate training examples
│       ├── FeedbackCollector.php      # Collect user feedback
│       └── AiFinderController.php     # REST API for chat
│
├── assets/
│   ├── src/js/
│   │   ├── quote-panel.js             # Quote panel logic
│   │   └── ai-finder.js               # AI chat widget
│   │
│   └── src/css/layers/components/
│       ├── _quote-panel.css
│       └── _ai-finder.css
│
└── config/
    ├── ai-prompts.php                 # LLM system prompts
    └── ai-settings.php                # LLM provider config
```

### 5.2 Dependencies

**Composer (PHP):**
```json
{
  "require": {
    "guzzlehttp/guzzle": "^7.8",
    "symfony/cache": "^6.4"
  }
}
```

**NPM (JS build):**
```json
{
  "dependencies": {
    "marked": "^11.0.0"
  }
}
```

### 5.3 Environment Variables

Thêm vào `wp-config.php` (local) hoặc server env:

```php
define('DRV_LLM_PROVIDER', 'anthropic'); // anthropic | openai | local
define('DRV_ANTHROPIC_API_KEY', 'sk-ant-***');
define('DRV_OPENAI_API_KEY', 'sk-***');
define('DRV_AI_CACHE_TTL', 3600); // Cache LLM responses for 1h
```

---

## 6. Tính khả thi

### 6.1 Phân tích SWOT

**Strengths (Điểm mạnh)**
- ✅ Có sẵn 28 collections với metadata chi tiết (14 facets).
- ✅ Giá/m² chuẩn hóa → dễ filter theo budget.
- ✅ WordPress REST API mature, dễ extend.
- ✅ Team đã quen với WC ecosystem.

**Weaknesses (Điểm yếu)**
- ⚠️ Training data ban đầu hạn chế (chưa có lịch sử query thật).
- ⚠️ LLM API cost có thể tăng nhanh nếu traffic cao.
- ⚠️ Latency 3-5s có thể khiến user rời đi (cần optimize).

**Opportunities (Cơ hội)**
- 🚀 Đối thủ trong ngành gạch VN chưa có AI (first-mover advantage).
- 🚀 Tích hợp Zalo/Messenger chatbot dùng chung AI engine.
- 🚀 Upsell thêm dịch vụ thi công (AI gợi ý package phòng khách + bếp).

**Threats (Rủi ro)**
- ❌ AI hallucination → gợi ý sai sản phẩm (cần validation layer).
- ❌ LLM provider thay đổi pricing/deprecated API.
- ❌ User kỳ vọng quá cao vào AI (cần set expectation rõ ràng).

### 6.2 Mitigations

| Rủi ro | Giải pháp |
|---|---|
| AI gợi ý sai | Rule-based validation layer: reject nếu sản phẩm không match hard constraints (vd: DCOF cho nhà tắm) |
| API cost tăng | Implement aggressive caching (1h TTL), batch requests, set monthly budget cap |
| Latency cao | Stream response chunks (SSE), show skeleton loader, pre-index products |
| Hallucination | Constrained generation: chỉ cho phép LLM chọn từ product IDs có sẵn |
| Vendor lock-in | Abstract LlmClient interface, dễ swap provider |

### 6.3 ROI Estimation

**Assumptions:**
- Conversion rate hiện tại (traditional filter): 2.5%
- Conversion rate với AI Finder: 4-5% (dựa trên case study ecommerce tương tự)
- Avg order value: 8.000.000đ
- Monthly traffic: 5.000 visitors

**Scenario A: Không AI**
- Orders/month = 5000 × 2.5% = 125
- Revenue/month = 125 × 8M = 1.000.000.000đ

**Scenario B: Có AI (30% users dùng AI Finder)**
- AI users = 5000 × 30% = 1500
- AI conversion = 1500 × 4.5% = 68 orders
- Traditional users = 3500 × 2.5% = 88 orders
- Total orders = 156
- Revenue/month = 156 × 8M = 1.248.000.000đ
- **Uplift = +248M/tháng (+24.8%)**

**Cost:**
- Development (one-time): ~80-120 triệu VND
- LLM API (monthly): ~5-10 triệu VND (1500 queries × $0.01/query)
- Maintenance: ~5 triệu/tháng

**Payback period:** ~3-4 tháng

---

## 7. Các khía cạnh khác

### 7.1 Security

1. **Input sanitization:** Tất cả user input qua `sanitize_text_field`, `wp_kses_post`.
2. **Rate limiting:** Max 10 AI queries/IP/10 phút (tránh abuse).
3. **API key protection:** Lưu trong env variables, không commit vào git.
4. **PII handling:** Không gửi tên/SĐT/email khách vào LLM prompt (chỉ gửi metadata sản phẩm).
5. **CORS:** Restrict REST API access nếu cần (chỉ allow same-origin).

### 7.2 Performance

1. **Caching:**
   - Cache LLM parsed params (1h TTL) dựa trên hash của user input.
   - Cache product query results (15 phút TTL).
2. **Lazy loading:** AI Finder widget chỉ load JS khi user click "Tìm với AI".
3. **Debounce:** Delay 500ms khi user gõ trong chat input → tránh spam API.
4. **CDN:** Serve static assets (CSS/JS) qua CDN.

### 7.3 Analytics

Track các events:

```javascript
// Google Analytics 4 events
gtag('event', 'quote_builder_open', {
  'event_category': 'engagement',
  'event_label': 'Quote Panel'
});

gtag('event', 'quote_submit', {
  'event_category': 'conversion',
  'value': quote_total_vnd
});

gtag('event', 'ai_finder_query', {
  'event_category': 'engagement',
  'event_label': query_text
});

gtag('event', 'ai_finder_result_click', {
  'event_category': 'engagement',
  'product_id': product_id
});
```

Dashboard cần track:
- Quote submissions/ngày
- AI queries/ngày
- Avg products/quote
- Conversion rate: AI finder vs. traditional
- AI response latency P50/P95/P99
- LLM API cost/ngày

### 7.4 UX Best Practices

1. **Set expectations:** Hiển thị rõ "AI có thể không hoàn hảo, kết quả chỉ mang tính tham khảo".
2. **Escape hatch:** Luôn có nút "Xem tất cả sản phẩm" hoặc "Lọc thủ công" cho user muốn tự kiểm soát.
3. **Transparent reasoning:** Hiển thị lý do AI gợi ý sản phẩm đó (không chỉ show kết quả).
4. **Refinement:** Cho phép user tinh chỉnh kết quả ("Có loại rẻ hơn không?" → AI re-query).
5. **Mobile-first:** Chat UI phải mượt trên mobile (bottom sheet, swipe to close).

### 7.5 Accessibility (WCAG 2.2 AA)

1. **Keyboard navigation:** Chat input, buttons phải accessible bằng Tab/Enter.
2. **Screen reader:** ARIA labels cho chat messages (`role="log"`, `aria-live="polite"`).
3. **Focus management:** Khi mở quote panel, focus vào close button hoặc first input.
4. **Color contrast:** AI messages phải đạt contrast ≥ 4.5:1 với background.

### 7.6 Multilingual (Future)

Nếu mở rộng ra tiếng Anh:
- LLM system prompt có version EN/VI.
- Product metadata index có field `lang`.
- User chọn ngôn ngữ → AI trả lời bằng ngôn ngữ đó.

---

## 8. Kết luận & Khuyến nghị

### 8.1 Kết luận

Cả hai tính năng đều **khả thi kỹ thuật** và **phù hợp với kiến trúc Dravit**:

1. **Quote Builder (Phase 2):**
   - ✅ Low risk, high value.
   - ✅ Không phụ thuộc bên thứ 3.
   - ✅ Triển khai nhanh (4 tuần).
   - **→ Nên ưu tiên làm ngay sau Phase 1.**

2. **AI Tile Finder (Phase 3):**
   - ✅ Differentiation mạnh so với đối thủ.
   - ⚠️ Cần đầu tư thời gian (6-8 tuần) và chi phí API.
   - ⚠️ Rủi ro kỹ thuật trung bình (LLM accuracy, latency).
   - **→ Triển khai sau khi có 3-6 tháng data từ Quote Builder.**

### 8.2 Roadmap đề xuất

```
Phase 1 (Nov 2026):      Launch core catalog website
    ↓ (1 tháng thu thập feedback)
Phase 2 (Dec 2026):      Quote Builder
    ↓ (3 tháng thu thập customer queries)
Phase 3 (Mar 2027):      AI Tile Finder MVP (Anthropic Claude)
    ↓ (2 tháng A/B testing & fine-tuning)
Phase 4 (May 2027):      Visual search + AR preview
Phase 5 (Q3 2027):       Multi-room quoting + Voice input
```

### 8.3 Next Steps

**Ngay lập tức:**
1. [ ] Review & approve tài liệu spec này.
2. [ ] Quyết định LLM provider (khuyến nghị: Anthropic Claude).
3. [ ] Setup API key & billing alerts.

**Phase 2 prep:**
1. [ ] Thiết kế chi tiết Quote Panel UI (Figma mockups).
2. [ ] Setup development environment (staging với WooCommerce HPOS).
3. [ ] Viết test cases cho Quote Builder.

**Phase 3 prep:**
1. [ ] Thu thập 50-100 customer query examples từ team sales.
2. [ ] Index toàn bộ catalog (420 SKU) thành AI-friendly metadata.
3. [ ] Setup monitoring & cost tracking cho LLM API.

---

**Tài liệu này tuân thủ ADR D-XX và D-YY, cập nhật vào `docs/00_Project_Brief_Decisions.md` sau khi phê duyệt.**