import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { registerAiFinderElement } from '../../assets/src/js/chunks/ai-finder.js';
import { getQuoteState, QUOTE_STORAGE_KEY } from '../../assets/src/js/utils/storage.js';

function buildAiFinderFixtureHtml() {
  return `
    <drv-ai-finder
      data-layout-mode="split"
      data-advisor-endpoint="/wp-json/dravit/v1/advisor"
      data-feedback-endpoint="/wp-json/dravit/v1/advisor/feedback"
      data-nonce-endpoint="/wp-json/dravit/v1/nonce"
      data-default-filter-url="/gach"
      data-boq-url="/danh-sach-bao-gia"
      data-zalo-url="https://zalo.me/0909123456"
    >
      <header class="drv-aif-topbar">
        <div class="drv-aif-modes">
          <button type="button" class="drv-aif-mode-btn is-active" data-aif-mode="split" aria-pressed="true">2 Cột</button>
          <button type="button" class="drv-aif-mode-btn" data-aif-mode="stream" aria-pressed="false">Chat đơn</button>
        </div>
        <button type="button" data-aif-open-quote-drawer>
          <span>Báo giá</span>
          <span data-aif-quote-boxes-total>0 thùng</span>
          <span data-drv-quote-count>0</span>
        </button>
      </header>

      <div class="drv-aif-mobile-tabs" role="tablist">
        <button type="button" role="tab" aria-selected="true" data-aif-mobile-tab="chat">Trợ lý AI Tư vấn</button>
        <button type="button" role="tab" aria-selected="false" data-aif-mobile-tab="results">
          Đề xuất &amp; Tính thùng <span data-aif-tab-count>0</span>
        </button>
      </div>

      <div class="drv-aif-workspace" data-aif-stage data-active-pane="chat">
        <section class="drv-aif-chat">
          <div class="drv-aif-chat__head">
            <button type="button" data-aif-expand-split hidden>Mở rộng</button>
            <button type="button" data-aif-reset>Làm mới</button>
            <a href="/gach" data-aif-filter-link>Bộ lọc</a>
          </div>

          <div data-aif-context hidden>
            <div data-aif-context-tags></div>
            <button type="button" data-aif-clear-slots>Đặt lại</button>
          </div>

          <div role="log" data-aif-messages>
            <div class="drv-aif-msg drv-aif-msg--bot" data-aif-welcome>Xin chào</div>
          </div>

          <div data-aif-quick-row>
            <button type="button" data-aif-prompt="Nhà tắm 6m² cho người lớn tuổi dưới 400k">
              Nhà tắm 6m² chống trượt R11
            </button>
          </div>

          <form data-aif-form novalidate>
            <input type="text" name="drv_website" value="" />
            <textarea data-aif-input maxlength="500"></textarea>
            <input type="checkbox" value="5" checked data-aif-waste />
            <span data-aif-char-count>0/500</span>
            <button type="submit" data-aif-submit>Gửi</button>
          </form>
        </section>

        <section class="drv-aif-results">
          <h2 data-aif-result-title>Đề xuất &amp; Dự toán khối lượng</h2>
          <span data-aif-result-pill>0 mẫu phù hợp</span>
          <p data-aif-result-sub>Chọn kịch bản bên trái</p>

          <button type="button" data-aif-toggle-refine aria-expanded="false">Lọc thông số</button>
          <button type="button" data-aif-quote-all hidden>Báo giá tất cả</button>

          <div data-aif-refine hidden>
            <button type="button" data-aif-refine-slot="dcof" data-aif-refine-val="r10">Chống trượt ≥ R10</button>
            <button type="button" data-aif-refine-slot="budget_max_vnd" data-aif-refine-val="400000">≤ 400.000đ/m²</button>
          </div>

          <div data-aif-body>
            <div data-aif-explain>
              <p data-aif-explain-text></p>
              <div data-aif-tradeoff hidden><p data-aif-tradeoff-text></p></div>
              <div data-aif-kb hidden></div>
            </div>
            <div data-aif-skeleton hidden></div>
            <div data-aif-empty>Chưa có dữ liệu</div>
            <div data-aif-grid hidden></div>
          </div>

          <div data-aif-feedback hidden>
            <button type="button" data-aif-rate="up">Hữu ích</button>
            <button type="button" data-aif-rate="down">Cần điều chỉnh</button>
            <p data-aif-feedback-thanks hidden>Cảm ơn!</p>
            <div data-aif-feedback-reasons hidden>
              <button type="button" data-aif-reason="wrong_budget">Chưa đúng tầm giá</button>
            </div>
          </div>
        </section>
      </div>

      <dialog data-aif-spec-dialog>
        <span data-aif-spec-modal-title></span>
        <button type="button" data-aif-close-spec>Đóng</button>
        <div data-aif-spec-modal-body></div>
        <a href="/gach" data-aif-spec-modal-link>Xem chi tiết</a>
        <button type="button" data-aif-spec-modal-quote>Thêm vào báo giá</button>
      </dialog>

      <dialog data-aif-quote-dialog>
        <p data-aif-qp-summary>0 mẫu · Tổng 0 thùng</p>
        <button type="button" data-aif-close-quote>Đóng</button>
        <div data-aif-qp-list></div>
        <strong data-aif-qp-skus>0 SKU</strong>
        <strong data-aif-qp-m2>0 m²</strong>
        <strong data-aif-qp-boxes>0 thùng</strong>
        <button type="button" data-aif-qp-clear hidden>Xóa toàn bộ</button>
      </dialog>
    </drv-ai-finder>
  `;
}

function buildMockAdvisorPayload(overrides = {}) {
  const quoteObj102 = {
    product_id: 102,
    sku: 'DRV-3605S',
    title: 'Gạch Nhà Tắm 30x60 Nhám R10',
    url: '/san-pham/drv-3605s',
    size_label: '30×60 cm',
    price_state: 'priced',
    price_m2: 295000,
    pieces_per_box: 8,
    m2_per_box: 1.44,
    area_m2: 6,
    waste_pct: 5,
    boxes: 5,
    pieces: 40,
    actual_m2: 7.2,
    weight_kg: 140,
    est_total_vnd: 2124000,
  };

  const specPayload102 = JSON.stringify({
    product_id: 102,
    sku: 'DRV-3605S',
    title: 'Gạch Nhà Tắm 30x60 Nhám R10',
    url: '/san-pham/drv-3605s',
    price_state: 'priced',
    price_m2: 295000,
    size_label: '30×60 cm',
    material_label: 'Xương Porcelain',
    surface_label: 'Nhám sần',
    dcof_label: 'R10',
    water_label: '≤ 0,5%',
    thickness_label: '9,5 mm',
    pack_label: '8 viên/thùng (1,44 m²)',
    origin_label: 'Kho trung tâm DRAVIT',
    stock_label: 'Sẵn kho',
    why: 'Đạt chuẩn R10 an toàn cho nhà tắm người lớn tuổi.',
    quote: quoteObj102,
  }).replaceAll('"', '&quot;');

  const quotePayload102 = JSON.stringify(quoteObj102).replaceAll('"', '&quot;');

  const quotePayload109 = JSON.stringify({
    product_id: 109,
    sku: 'DRV-3612T',
    title: 'Gạch Travertine 60x120 Mờ R10',
    url: '/san-pham/drv-3612t',
    size_label: '60×120 cm',
    price_state: 'quote_only',
    price_m2: null,
    pieces_per_box: 2,
    m2_per_box: 1.44,
    area_m2: 6,
    waste_pct: 5,
    boxes: 5,
    pieces: 10,
    actual_m2: 7.2,
    weight_kg: 175,
    est_total_vnd: null,
  }).replaceAll('"', '&quot;');

  return {
    request_id: 88,
    in_scope: true,
    intent: 'recommend',
    degraded: false,
    summary: 'Tìm thấy 2 mẫu đạt chuẩn chống trượt R10 cho nhà tắm 6 m².',
    clarification: null,
    tradeoff: 'Ưu tiên bề mặt nhám R10 an toàn cho người lớn tuổi.',
    slots: {
      space: 'nha-tam',
      function: null,
      area_m2: 6,
      size: null,
      vein: null,
      material: null,
      surface: null,
      color: null,
      style: null,
      dcof: null,
      budget_max_vnd: 400000,
      special: ['nguoi-gia'],
      showroom_only: false,
      sku_query: null,
    },
    context_facets: {
      collection_url: '/gach-nha-tam',
      filter_url: '/gach-nha-tam?dcof=r10',
      active_tags: [
        { key: 'space', label: 'Nhà tắm / WC' },
        { key: 'area_m2', label: '6 m²' },
        { key: 'dcof', label: 'An toàn ≥ R10' },
      ],
    },
    items: [
      {
        id: 102,
        sku: 'DRV-3605S',
        title: 'Gạch Nhà Tắm 30x60 Nhám R10',
        url: '/san-pham/drv-3605s',
        price_state: 'priced',
        price_m2: 295000,
        dcof_r: 'R10',
        calc: { boxes: 5, pieces: 40, actual_m2: 7.2, input_area_m2: 6 },
      },
      {
        id: 109,
        sku: 'DRV-3612T',
        title: 'Gạch Travertine 60x120 Mờ R10',
        url: '/san-pham/drv-3612t',
        price_state: 'quote_only',
        price_m2: null,
        dcof_r: 'R10',
        calc: { boxes: 5, pieces: 10, actual_m2: 7.2, input_area_m2: 6 },
      },
    ],
    html: `
      <article class="drv-aif-card" data-product-id="102">
        <h3>DRV-3605S</h3>
        <button type="button" data-aif-open-spec="${specPayload102}">Thông số</button>
        <button type="button" data-drv-ai-add-quote="${quotePayload102}" data-aif-quote-boxes="5">Thêm báo giá</button>
      </article>
      <article class="drv-aif-card" data-product-id="109">
        <h3>DRV-3612T</h3><span>Liên hệ báo giá</span>
        <button type="button" data-drv-ai-add-quote="${quotePayload109}" data-aif-quote-boxes="5">Thêm báo giá</button>
      </article>
    `,
    kb_refs: ['kb-an-toan-chong-truot#r10-khu-am-uot'],
    kb_sources: [{ ref: 'kb-an-toan-chong-truot#r10-khu-am-uot', title: 'Chuẩn chống trượt R10' }],
    quick_chips: ['Có mẫu tại Showroom'],
    handoff: null,
    ...overrides,
  };
}

async function flushPromises() {
  await new Promise((resolve) => setTimeout(resolve, 0));
}

describe('Step 4 <drv-ai-finder> Workspace UI/UX (2-Col/Stream, Quick Spec Modal, Offcanvas Quote Drawer & Nonce Retry)', () => {
  beforeEach(() => {
    registerAiFinderElement();
    window.localStorage.clear();
    document.body.innerHTML = buildAiFinderFixtureHtml();
  });

  afterEach(() => {
    vi.restoreAllMocks();
    window.localStorage.clear();
  });

  it('fetches nonce, renders cards + inline mini-cards, opens Quick Spec modal, and syncs Quote Drawer + total boxes', async () => {
    const fetchCalls = [];
    globalThis.fetch = vi.fn().mockImplementation(async (url, opts = {}) => {
      fetchCalls.push({ url: String(url), opts });
      if (String(url).includes('/nonce')) {
        return {
          ok: true,
          status: 200,
          json: async () => ({ nonces: { advisor: 'fresh-nonce-123' } }),
        };
      }
      return {
        ok: true,
        status: 200,
        json: async () => buildMockAdvisorPayload(),
      };
    });

    const chip = document.querySelector('[data-aif-prompt]');
    chip.click();
    await flushPromises();

    // 1. Gọi /nonce trước rồi gửi X-DRV-Nonce lên /advisor
    expect(fetchCalls.length).toBe(2);
    expect(fetchCalls[0].url).toContain('/wp-json/dravit/v1/nonce');
    expect(fetchCalls[1].url).toContain('/wp-json/dravit/v1/advisor');
    expect(fetchCalls[1].opts.headers['X-DRV-Nonce']).toBe('fresh-nonce-123');

    // 2. Render context tags, cảnh báo tradeoff, pill số mẫu, công thức hao hụt + thẻ sản phẩm
    expect(document.querySelector('[data-aif-context]').hidden).toBe(false);
    expect(document.querySelectorAll('.drv-aif-facet-tag').length).toBe(3);
    expect(document.querySelector('[data-aif-tradeoff]').hidden).toBe(false);
    expect(document.querySelector('[data-aif-result-pill]').textContent).toBe('2 mẫu phù hợp');
    expect(document.querySelector('[data-aif-result-sub]').textContent).toContain('6,3 m²');
    expect(document.querySelector('[data-aif-filter-link]').getAttribute('href')).toBe('/gach-nha-tam?dcof=r10');
    expect(document.querySelectorAll('[data-aif-grid] [data-drv-ai-add-quote]').length).toBe(2);
    expect(document.querySelectorAll('.drv-aif-mini-card').length).toBe(2);

    // 3. Chuyển chế độ Chat đơn (stream) & bật thanh Lọc thông số
    document.querySelector('[data-aif-mode="stream"]').click();
    expect(document.querySelector('drv-ai-finder').dataset.layoutMode).toBe('stream');
    expect(document.querySelector('[data-aif-expand-split]').hidden).toBe(false);

    document.querySelector('[data-aif-toggle-refine]').click();
    expect(document.querySelector('[data-aif-refine]').hidden).toBe(false);

    // 4. Mở hộp thoại Thông số kỹ thuật (Quick Spec Modal) -> thêm vào báo giá từ modal
    document.querySelector('[data-aif-open-spec]').click();
    expect(document.querySelector('[data-aif-spec-modal-title]').textContent).toContain('DRV-3605S');
    expect(document.querySelector('[data-aif-spec-modal-body]').textContent).toContain('Xương Porcelain');
    document.querySelector('[data-aif-spec-modal-quote]').click();

    const quoteState = getQuoteState();
    expect(quoteState.items.length).toBe(1);
    expect(quoteState.items[0].product_id).toBe(102);
    expect(quoteState.items[0].boxes).toBe(5);
    expect(document.querySelector('[data-drv-quote-count]').textContent).toBe('1');
    expect(document.querySelector('[data-aif-quote-boxes-total]').textContent).toBe('5 thùng');

    // 5. Tăng +1 thùng ngay trong Quote Drawer -> tự tính lại thành 6 thùng (8,64 m²)
    const plusBoxBtn = document.querySelector('[data-aif-qp-step="1"]');
    plusBoxBtn.click();
    expect(getQuoteState().items[0].boxes).toBe(6);
    expect(document.querySelector('[data-aif-quote-boxes-total]').textContent).toBe('6 thùng');

    // 6. Bấm "Báo giá tất cả" -> nạp nốt mẫu quote_only 109 (price_m2 = null, không có số 0đ giả)
    const quoteAllBtn = document.querySelector('[data-aif-quote-all]');
    expect(quoteAllBtn.hidden).toBe(false);
    quoteAllBtn.click();

    const fullQuote = JSON.parse(window.localStorage.getItem(QUOTE_STORAGE_KEY));
    expect(fullQuote.items.length).toBe(2);
    expect(fullQuote.items[1].product_id).toBe(109);
    expect(fullQuote.items[1].price_state).toBe('quote_only');
    expect(fullQuote.items[1].price_m2).toBeNull();
    expect(document.querySelector('[data-drv-quote-count]').textContent).toBe('2');
  });

  it('recalculates boxes when toggling +5% waste checkbox and retries once on 403 stale nonce', async () => {
    let advisorAttempts = 0;
    const advisorBodies = [];

    globalThis.fetch = vi.fn().mockImplementation(async (url, opts = {}) => {
      if (String(url).includes('/nonce')) {
        return {
          ok: true,
          status: 200,
          json: async () => ({ nonces: { advisor: `nonce-round-${advisorAttempts + 1}` } }),
        };
      }
      if (String(url).includes('/feedback')) {
        return {
          ok: true,
          status: 200,
          json: async () => ({ recorded: true }),
        };
      }

      advisorAttempts += 1;
      advisorBodies.push(JSON.parse(opts.body));

      if (advisorAttempts === 1) {
        return {
          ok: false,
          status: 403,
          json: async () => ({ code: 'drv_bad_nonce', message: 'Expired' }),
        };
      }

      return {
        ok: true,
        status: 200,
        json: async () => buildMockAdvisorPayload(),
      };
    });

    const textarea = document.querySelector('[data-aif-input]');
    const form = document.querySelector('[data-aif-form]');
    textarea.value = 'Lát nhà tắm 6m2';
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    expect(document.querySelector('[data-aif-char-count]').textContent).toBe('15/500');

    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flushPromises();

    expect(advisorAttempts).toBe(2);

    const wasteCheckbox = document.querySelector('[data-aif-waste]');
    wasteCheckbox.checked = false;
    wasteCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
    await flushPromises();

    expect(advisorAttempts).toBe(3);
    expect(advisorBodies[2].waste_pct).toBe(0);
    expect(advisorBodies[2].slots.space).toBe('nha-tam');

    const downBtn = document.querySelector('[data-aif-rate="down"]');
    downBtn.click();
    expect(document.querySelector('[data-aif-feedback-reasons]').hidden).toBe(false);

    const reasonBtn = document.querySelector('[data-aif-reason="wrong_budget"]');
    reasonBtn.click();
    await flushPromises();

    expect(document.querySelector('[data-aif-feedback-thanks]').hidden).toBe(false);
  });
});
