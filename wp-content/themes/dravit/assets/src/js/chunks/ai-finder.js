import {
  clearQuoteItems,
  countQuoteItems,
  getQuoteState,
  removeQuoteItem,
  upsertQuoteItem,
} from '../utils/storage.js';

const EMPTY_SLOTS = () => ({
  space: null,
  function: null,
  area_m2: null,
  size: null,
  vein: null,
  material: null,
  surface: null,
  color: null,
  style: null,
  dcof: null,
  budget_max_vnd: null,
  special: [],
  showroom_only: false,
  sku_query: null,
});

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

function formatRichSummaryHtml(rawText) {
  const escaped = escapeHtml(rawText);
  return escaped.replace(
    /(\bR(?:9|10|11|12|13)\b|\b\d+(?:[.,]\d+)?\s*(?:m²|thùng|viên)\b)/gu,
    '<strong>$1</strong>',
  );
}

function formatVnd(amount) {
  const num = Number(amount || 0);
  if (!Number.isFinite(num) || num <= 0) {
    return '';
  }
  return `${Math.round(num).toLocaleString('vi-VN')}đ`;
}

function formatM2(val) {
  const num = Number(val || 0);
  if (!Number.isFinite(num) || num <= 0) {
    return '0';
  }
  return Number(num.toFixed(2)).toString().replace('.', ',');
}

function openDialogSafe(dialog) {
  if (!dialog) {
    return;
  }
  if (typeof dialog.showModal === 'function') {
    if (!dialog.open) {
      dialog.showModal();
    }
  } else {
    dialog.setAttribute('open', '');
  }
}

function closeDialogSafe(dialog) {
  if (!dialog) {
    return;
  }
  if (typeof dialog.close === 'function' && dialog.open) {
    dialog.close();
  } else {
    dialog.removeAttribute('open');
  }
}

/**
 * Custom Element `<drv-ai-finder>` điều phối Trợ lý AI Tìm gạch & Dự toán vật tư (`docs/layout/01.1-ai-finder.html`, `docs/13`, `D-32`).
 */
export class AiFinderElement extends HTMLElement {
  connectedCallback() {
    if (this._initialized) {
      return;
    }
    this._initialized = true;

    this.slots = EMPTY_SLOTS();
    this.nonce = '';
    this.nonceFetchedAt = 0;
    this.lastRequestId = 0;
    this.busy = false;
    this.activeSpecQuotePayload = null;

    const runtimeCfg = (typeof window !== 'undefined' && window.DRV_CONFIG) || {};
    this.config = {
      advisorEndpoint:
        this.dataset.advisorEndpoint ||
        runtimeCfg.advisorEndpoint ||
        '/wp-json/dravit/v1/advisor',
      feedbackEndpoint:
        this.dataset.feedbackEndpoint ||
        runtimeCfg.advisorFeedbackEndpoint ||
        '/wp-json/dravit/v1/advisor/feedback',
      nonceEndpoint:
        this.dataset.nonceEndpoint ||
        runtimeCfg.nonceEndpoint ||
        '/wp-json/dravit/v1/nonce',
      defaultFilterUrl:
        this.dataset.defaultFilterUrl || runtimeCfg.gachUrl || '/gach',
      boqUrl:
        this.dataset.boqUrl || runtimeCfg.boqUrl || '/danh-sach-bao-gia',
      zaloUrl: this.dataset.zaloUrl || 'https://zalo.me/0909888777',
    };

    this._onQuoteUpdated = () => {
      this.syncQuoteBadges();
      this.syncAddedCardStates();
      this.renderQuoteDrawer();
    };

    if (typeof window !== 'undefined') {
      window.addEventListener('drv:quote-updated', this._onQuoteUpdated);
    }

    this.bindEvents();
    this.syncQuoteBadges();
    this.syncAddedCardStates();
    this.renderQuoteDrawer();

    if (typeof window !== 'undefined' && window.location?.search) {
      const params = new URLSearchParams(window.location.search);
      const initialPrompt = (params.get('prompt') || params.get('ai_query') || '').trim();
      if (initialPrompt) {
        this.sendAdvisorRequest({ message: initialPrompt, fromChip: false });
      }
    }
  }

  disconnectedCallback() {
    if (typeof window !== 'undefined' && this._onQuoteUpdated) {
      window.removeEventListener('drv:quote-updated', this._onQuoteUpdated);
    }
  }

  bindEvents() {
    const form = this.querySelector('[data-aif-form]');
    const input = this.querySelector('[data-aif-input]');
    const wasteCheckbox = this.querySelector('[data-aif-waste]');

    if (form) {
      form.addEventListener('submit', (e) => {
        e.preventDefault();
        const text = (input?.value || '').trim();
        if (!text || this.busy) {
          return;
        }
        input.value = '';
        this.updateCharCount('');
        this.sendAdvisorRequest({ message: text, fromChip: false });
      });
    }

    if (input) {
      input.addEventListener('input', () => {
        this.updateCharCount(input.value);
      });
      input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          form?.requestSubmit();
        }
      });
    }

    if (wasteCheckbox) {
      wasteCheckbox.addEventListener('change', () => {
        if (this.hasAnyActiveSlot() && !this.busy) {
          this.sendAdvisorRequest({
            message: '',
            fromChip: true,
            silentBubble: true,
          });
        }
      });
    }

    this.addEventListener('click', (e) => {
      const target = e.target instanceof Element ? e.target : null;
      if (!target) {
        return;
      }

      // 1. Chuyển chế độ hiển thị 2 Cột / Chat đơn
      const modeBtn = target.closest('[data-aif-mode]');
      if (modeBtn) {
        e.preventDefault();
        this.switchLayoutMode(modeBtn.dataset.aifMode || 'split');
        return;
      }

      const expandSplitBtn = target.closest('[data-aif-expand-split]');
      if (expandSplitBtn) {
        e.preventDefault();
        this.switchLayoutMode('split');
        return;
      }

      // 2. Chuyển tab trên Mobile (Trợ lý AI Tư vấn / Đề xuất & Tính thùng)
      const mobileTab = target.closest('[data-aif-mobile-tab]');
      if (mobileTab) {
        e.preventDefault();
        this.switchMobilePane(mobileTab.dataset.aifMobileTab || 'chat');
        return;
      }

      // 3. Bật / tắt thanh Lọc thông số tinh chỉnh nhanh
      const toggleRefineBtn = target.closest('[data-aif-toggle-refine]');
      if (toggleRefineBtn) {
        e.preventDefault();
        const bar = this.querySelector('[data-aif-refine]');
        if (bar) {
          const nextOpen = bar.hidden;
          bar.hidden = !nextOpen;
          toggleRefineBtn.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
        }
        return;
      }

      // 4. Bấm chip kịch bản gợi ý
      const promptChip = target.closest('[data-aif-prompt]');
      if (promptChip && !this.busy) {
        e.preventDefault();
        const promptText = (promptChip.dataset.aifPrompt || '').trim();
        if (promptText) {
          this.sendAdvisorRequest({ message: promptText, fromChip: true });
        }
        return;
      }

      // 5. Bấm nút tinh chỉnh kỹ thuật nhanh (R10, R11, 80x80, Matt, Ngân sách, Showroom)
      const refineBtn = target.closest('[data-aif-refine-slot]');
      if (refineBtn && !this.busy) {
        e.preventDefault();
        this.toggleRefineSlot(refineBtn);
        return;
      }

      // 6. Xóa 1 tag ngữ cảnh đang lọc
      const removeSlotBtn = target.closest('[data-aif-remove-slot]');
      if (removeSlotBtn && !this.busy) {
        e.preventDefault();
        const slotKey = removeSlotBtn.dataset.aifRemoveSlot || '';
        this.clearSingleSlot(slotKey);
        return;
      }

      // 7. Xóa toàn bộ lọc / Làm mới hội thoại
      const clearAllBtn = target.closest('[data-aif-clear-slots], [data-aif-reset]');
      if (clearAllBtn && !this.busy) {
        e.preventDefault();
        this.resetConversation(clearAllBtn.hasAttribute('data-aif-reset'));
        return;
      }

      // 8. Mở hộp thoại Thông số kỹ thuật (Quick Spec Modal)
      const openSpecBtn = target.closest('[data-aif-open-spec]');
      if (openSpecBtn) {
        e.preventDefault();
        this.openQuickSpecModal(openSpecBtn.dataset.aifOpenSpec || '');
        return;
      }

      const closeSpecBtn = target.closest('[data-aif-close-spec]');
      if (closeSpecBtn) {
        e.preventDefault();
        closeDialogSafe(this.querySelector('[data-aif-spec-dialog]'));
        return;
      }

      const specQuoteBtn = target.closest('[data-aif-spec-modal-quote]');
      if (specQuoteBtn) {
        e.preventDefault();
        if (this.activeSpecQuotePayload) {
          this.upsertPayloadToQuote(this.activeSpecQuotePayload);
          closeDialogSafe(this.querySelector('[data-aif-spec-dialog]'));
          this.openQuoteDrawer();
        }
        return;
      }

      // 9. Thêm 1 mẫu vào danh sách Báo giá (`drv_quote_v2`)
      const addQuoteBtn = target.closest('[data-drv-ai-add-quote]');
      if (addQuoteBtn) {
        e.preventDefault();
        this.addCardPayloadToQuote(addQuoteBtn);
        return;
      }

      // 10. Thêm tất cả mẫu đang hiển thị vào danh sách Báo giá
      const quoteAllBtn = target.closest('[data-aif-quote-all]');
      if (quoteAllBtn) {
        e.preventDefault();
        const cardBtns = this.querySelectorAll('[data-aif-grid] [data-drv-ai-add-quote]');
        cardBtns.forEach((btn) => this.addCardPayloadToQuote(btn));
        this.openQuoteDrawer();
        return;
      }

      // 11. Đóng / mở Ngăn Báo giá Offcanvas (Quote Drawer) & thao tác số thùng
      const openQuoteDrawerBtn = target.closest('[data-aif-open-quote-drawer]');
      if (openQuoteDrawerBtn) {
        e.preventDefault();
        this.openQuoteDrawer();
        return;
      }

      const closeQuoteBtn = target.closest('[data-aif-close-quote]');
      if (closeQuoteBtn) {
        e.preventDefault();
        closeDialogSafe(this.querySelector('[data-aif-quote-dialog]'));
        return;
      }

      const stepBtn = target.closest('[data-aif-qp-step]');
      if (stepBtn) {
        e.preventDefault();
        const delta = Number(stepBtn.dataset.aifQpStep || 0);
        const itemKey = stepBtn.dataset.aifQpKey || '';
        this.stepQuoteItemBoxes(itemKey, delta);
        return;
      }

      const removeQpBtn = target.closest('[data-aif-qp-remove]');
      if (removeQpBtn) {
        e.preventDefault();
        const itemKey = removeQpBtn.dataset.aifQpRemove || '';
        removeQuoteItem(itemKey);
        this.syncQuoteBadges();
        this.syncAddedCardStates();
        this.renderQuoteDrawer();
        return;
      }

      const clearQpBtn = target.closest('[data-aif-qp-clear]');
      if (clearQpBtn) {
        e.preventDefault();
        clearQuoteItems();
        this.syncQuoteBadges();
        this.syncAddedCardStates();
        this.renderQuoteDrawer();
        return;
      }

      // 12. Phản hồi chất lượng gợi ý (Thumbs Up / Down + Lý do tinh chỉnh)
      const rateBtn = target.closest('[data-aif-rate]');
      if (rateBtn) {
        e.preventDefault();
        const rating = rateBtn.dataset.aifRate || 'up';
        this.querySelectorAll('[data-aif-rate]').forEach((b) =>
          b.classList.toggle('is-selected', b === rateBtn),
        );
        const reasonsBox = this.querySelector('[data-aif-feedback-reasons]');
        if (rating === 'down' && reasonsBox) {
          reasonsBox.hidden = false;
        } else {
          this.submitFeedback(rating, '');
        }
        return;
      }

      const reasonBtn = target.closest('[data-aif-reason]');
      if (reasonBtn) {
        e.preventDefault();
        const reason = reasonBtn.dataset.aifReason || '';
        this.submitFeedback('down', reason);
      }
    });

    // Click ra ngoài backdrop của <dialog> để đóng mượt
    ['[data-aif-spec-dialog]', '[data-aif-quote-dialog]'].forEach((sel) => {
      const dlg = this.querySelector(sel);
      if (dlg) {
        dlg.addEventListener('click', (ev) => {
          if (ev.target === dlg) {
            closeDialogSafe(dlg);
          }
        });
      }
    });
  }

  switchLayoutMode(mode) {
    const cleanMode = mode === 'stream' ? 'stream' : 'split';
    this.dataset.layoutMode = cleanMode;

    this.querySelectorAll('[data-aif-mode]').forEach((btn) => {
      const active = btn.dataset.aifMode === cleanMode;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });

    const expandBtn = this.querySelector('[data-aif-expand-split]');
    if (expandBtn) {
      expandBtn.hidden = cleanMode !== 'stream';
    }
  }

  switchMobilePane(pane) {
    const stage = this.querySelector('[data-aif-stage]');
    if (!stage) {
      return;
    }
    stage.dataset.activePane = pane;
    this.querySelectorAll('[data-aif-mobile-tab]').forEach((btn) => {
      const active = btn.dataset.aifMobileTab === pane;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
  }

  updateCharCount(val) {
    const counter = this.querySelector('[data-aif-char-count]');
    if (counter) {
      counter.textContent = `${String(val || '').length}/500`;
    }
  }

  hasAnyActiveSlot() {
    return Object.entries(this.slots).some(([, val]) => {
      if (Array.isArray(val)) {
        return val.length > 0;
      }
      return val !== null && val !== false && val !== '';
    });
  }

  getWastePct() {
    const cb = this.querySelector('[data-aif-waste]');
    if (!cb) {
      return 5;
    }
    return cb.checked ? 5 : 0;
  }

  toggleRefineSlot(btn) {
    const key = btn.dataset.aifRefineSlot || '';
    const rawVal = btn.dataset.aifRefineVal || '';
    if (!key) {
      return;
    }

    if (key === 'showroom_only') {
      this.slots.showroom_only = !this.slots.showroom_only;
      btn.classList.toggle('is-active', this.slots.showroom_only);
    } else if (key === 'budget_max_vnd') {
      const numVal = Number(rawVal);
      if (this.slots.budget_max_vnd === numVal) {
        this.slots.budget_max_vnd = null;
        btn.classList.remove('is-active');
      } else {
        this.slots.budget_max_vnd = numVal;
        this.querySelectorAll('[data-aif-refine-slot="budget_max_vnd"]').forEach((b) =>
          b.classList.toggle('is-active', b === btn),
        );
      }
    } else if (this.slots[key] === rawVal) {
      this.slots[key] = null;
      btn.classList.remove('is-active');
    } else {
      this.slots[key] = rawVal;
      this.querySelectorAll(`[data-aif-refine-slot="${key}"]`).forEach((b) =>
        b.classList.toggle('is-active', b === btn),
      );
    }

    this.sendAdvisorRequest({ message: '', fromChip: true, silentBubble: true });
  }

  clearSingleSlot(slotKey) {
    if (!slotKey || !(slotKey in this.slots)) {
      return;
    }
    if (slotKey === 'special') {
      this.slots.special = [];
    } else if (slotKey === 'showroom_only') {
      this.slots.showroom_only = false;
    } else {
      this.slots[slotKey] = null;
    }

    this.syncRefineButtons();
    this.sendAdvisorRequest({ message: '', fromChip: true, silentBubble: true });
  }

  syncRefineButtons() {
    this.querySelectorAll('[data-aif-refine-slot]').forEach((btn) => {
      const k = btn.dataset.aifRefineSlot || '';
      const v = btn.dataset.aifRefineVal || '';
      let active = false;
      if (k === 'showroom_only') {
        active = Boolean(this.slots.showroom_only);
      } else if (k === 'budget_max_vnd') {
        active = Number(this.slots.budget_max_vnd) === Number(v);
      } else {
        active = String(this.slots[k] || '') === v;
      }
      btn.classList.toggle('is-active', active);
    });
  }

  async ensureNonce(forceRefresh = false) {
    const now = Date.now();
    if (!forceRefresh && this.nonce && now - this.nonceFetchedAt < 10 * 60 * 1000) {
      return this.nonce;
    }

    try {
      const res = await fetch(this.config.nonceEndpoint, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      if (res.ok) {
        const data = await res.json();
        const token = data?.nonces?.advisor || data?.advisor || '';
        if (token) {
          this.nonce = String(token);
          this.nonceFetchedAt = now;
        }
      }
    } catch {
      // Bỏ qua lỗi mạng tạm thời
    }

    return this.nonce;
  }

  async sendAdvisorRequest({ message, fromChip = false, silentBubble = false }) {
    const honeypot = this.querySelector('input[name="drv_website"]');
    if (honeypot && honeypot.value !== '') {
      return;
    }

    this.busy = true;
    this.setLoadingState(true);

    if (message && !silentBubble) {
      this.appendMessage('user', message);
    }

    const typingNode = this.appendTypingIndicator();

    try {
      const nonce = await this.ensureNonce(false);
      let response = await this.postAdvisorPayload(message, fromChip, nonce);

      if (response.status === 403) {
        const freshNonce = await this.ensureNonce(true);
        response = await this.postAdvisorPayload(message, fromChip, freshNonce);
      }

      typingNode?.remove();

      if (!response.ok) {
        let errMsg =
          'Hệ thống tư vấn đang bận xử lý. Bạn có thể chuyển sang Bộ lọc 14 tiêu chí để tra cứu ngay.';
        try {
          const errJson = await response.json();
          if (errJson?.message) {
            errMsg = String(errJson.message);
          }
        } catch {
          // Ignore malformed error JSON
        }
        this.setLoadingState(false);
        this.appendBotResponse({ summary: errMsg, quick_chips: [] });
        return;
      }

      const data = await response.json();
      this.setLoadingState(false);
      this.applyAdvisorResponse(data, silentBubble);
    } catch {
      typingNode?.remove();
      this.setLoadingState(false);
      this.appendBotResponse({
        summary:
          'Kết nối mạng bị gián đoạn. Vui lòng thử lại hoặc tra cứu trực tiếp tại Bộ lọc 14 tiêu chí.',
        quick_chips: [],
      });
    } finally {
      this.busy = false;
    }
  }

  async postAdvisorPayload(message, fromChip, nonce) {
    const headers = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    };
    if (nonce) {
      headers['X-DRV-Nonce'] = nonce;
    }

    return fetch(this.config.advisorEndpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers,
      body: JSON.stringify({
        message,
        slots: this.slots,
        waste_pct: this.getWastePct(),
        from_chip: Boolean(fromChip),
      }),
    });
  }

  applyAdvisorResponse(data, silentBubble = false) {
    if (!data || typeof data !== 'object') {
      return;
    }

    this.lastRequestId = Number(data.request_id || 0);

    if (data.slots && typeof data.slots === 'object') {
      this.slots = {
        ...EMPTY_SLOTS(),
        ...data.slots,
        special: Array.isArray(data.slots.special) ? data.slots.special : [],
      };
      this.syncRefineButtons();
    }

    const facets = data.context_facets || {};
    this.renderContextTags(Array.isArray(facets.active_tags) ? facets.active_tags : []);

    const filterLink = this.querySelector('[data-aif-filter-link]');
    if (filterLink) {
      filterLink.setAttribute(
        'href',
        facets.filter_url || facets.collection_url || this.config.defaultFilterUrl,
      );
    }

    const items = Array.isArray(data.items) ? data.items : [];
    const html = typeof data.html === 'string' ? data.html.trim() : '';

    // 1. Cập nhật tiêu đề, pill số lượng & công thức quy đổi diện tích
    const resultPill = this.querySelector('[data-aif-result-pill]');
    const resultSub = this.querySelector('[data-aif-result-sub]');
    const tabCount = this.querySelector('[data-aif-tab-count]');
    const quoteAllBtn = this.querySelector('[data-aif-quote-all]');

    if (resultPill) {
      resultPill.textContent = `${items.length} mẫu phù hợp`;
    }
    if (tabCount) {
      tabCount.textContent = String(items.length);
    }
    if (resultSub) {
      const areaVal = Number(this.slots.area_m2 || 0);
      const wastePct = this.getWastePct();
      if (areaVal > 0) {
        const grossM2 = areaVal * (1 + wastePct / 100);
        resultSub.innerHTML =
          wastePct > 0
            ? `Tính cho <strong class="u-text-navy u-tabular">${escapeHtml(formatM2(areaVal))} m²</strong> (+${wastePct}% hao hụt = <span class="u-tabular">${escapeHtml(formatM2(grossM2))} m²</span>)`
            : `Tính cho <strong class="u-text-navy u-tabular">${escapeHtml(formatM2(areaVal))} m²</strong> (không cộng hao hụt)`;
      } else {
        resultSub.textContent =
          items.length > 0
            ? 'Nhập thêm diện tích (m²) để tự động tính số thùng chẵn'
            : 'Bạn có thể nới tiêu chí hoặc nhắn trực tiếp kỹ thuật viên DRAVIT';
      }
    }
    if (quoteAllBtn) {
      quoteAllBtn.hidden = items.length === 0;
    }

    // 2. Cập nhật hộp giải thích AI, cảnh báo tradeoff & trích dẫn KB
    const explainText = this.querySelector('[data-aif-explain-text]');
    if (explainText && data.summary) {
      explainText.innerHTML = formatRichSummaryHtml(data.summary);
    }

    const tradeoffBox = this.querySelector('[data-aif-tradeoff]');
    const tradeoffText = this.querySelector('[data-aif-tradeoff-text]');
    if (tradeoffBox && tradeoffText) {
      if (data.tradeoff) {
        tradeoffText.textContent = String(data.tradeoff);
        tradeoffBox.hidden = false;
      } else {
        tradeoffText.textContent = '';
        tradeoffBox.hidden = true;
      }
    }

    const kbBox = this.querySelector('[data-aif-kb]');
    if (kbBox) {
      const kbSources = Array.isArray(data.kb_sources) ? data.kb_sources : [];
      if (kbSources.length > 0) {
        kbBox.innerHTML = kbSources
          .map(
            (src) =>
              `<span class="drv-aif-kb-pill"><i class="ph-fill ph-seal-check" aria-hidden="true"></i>${escapeHtml(src.title)}</span>`,
          )
          .join('');
        kbBox.hidden = false;
      } else {
        kbBox.innerHTML = '';
        kbBox.hidden = true;
      }
    }

    // 3. Render lưới thẻ sản phẩm hoặc trạng thái Empty/Handoff
    const grid = this.querySelector('[data-aif-grid]');
    const emptyBox = this.querySelector('[data-aif-empty]');
    if (grid && emptyBox) {
      if (html !== '') {
        grid.innerHTML = html;
        grid.hidden = false;
        emptyBox.hidden = true;
        this.syncAddedCardStates();
      } else if (data.in_scope === false || data.intent === 'knowledge' || data.intent === 'policy') {
        // Giữ nguyên lưới sản phẩm hiện có khi chỉ hỏi kiến thức kỹ thuật / chính sách
      } else {
        grid.innerHTML = '';
        grid.hidden = true;
        emptyBox.hidden = false;
        if (data.handoff?.zalo_url) {
          const zaloCta = emptyBox.querySelector('[data-aif-zalo-cta]');
          if (zaloCta) {
            zaloCta.setAttribute('href', String(data.handoff.zalo_url));
          }
        }
      }
    }

    // 4. Hiển thị khối Feedback
    const feedbackBox = this.querySelector('[data-aif-feedback]');
    const feedbackThanks = this.querySelector('[data-aif-feedback-thanks]');
    const feedbackReasons = this.querySelector('[data-aif-feedback-reasons]');
    if (feedbackBox) {
      feedbackBox.hidden = this.lastRequestId <= 0;
      this.querySelectorAll('[data-aif-rate]').forEach((b) => b.classList.remove('is-selected'));
      if (feedbackThanks) {
        feedbackThanks.hidden = true;
      }
      if (feedbackReasons) {
        feedbackReasons.hidden = true;
      }
    }

    // 5. Thêm bong bóng phản hồi của Cố vấn AI vào luồng hội thoại
    if (!silentBubble) {
      this.appendBotResponse(data);
    }
  }

  renderContextTags(tags) {
    const wrap = this.querySelector('[data-aif-context]');
    const list = this.querySelector('[data-aif-context-tags]');
    if (!wrap || !list) {
      return;
    }

    if (!tags || tags.length === 0) {
      list.innerHTML = '';
      wrap.hidden = true;
      return;
    }

    list.innerHTML = tags
      .map(
        (tag) => `
        <span class="drv-aif-facet-tag">
          <span>${escapeHtml(tag.label)}</span>
          <button type="button" data-aif-remove-slot="${escapeHtml(tag.key)}" aria-label="Bỏ điều kiện ${escapeHtml(tag.label)}">
            <i class="ph-bold ph-x" aria-hidden="true"></i>
          </button>
        </span>`,
      )
      .join('');
    wrap.hidden = false;
  }

  appendMessage(role, text) {
    const log = this.querySelector('[data-aif-messages]');
    if (!log) {
      return;
    }

    const el = document.createElement('div');
    el.className = `drv-aif-msg drv-aif-msg--${role}`;
    el.innerHTML = `
      <div class="drv-aif-msg__avatar" aria-hidden="true">
        <i class="ph-fill ${role === 'user' ? 'ph-user' : 'ph-sparkle'}"></i>
      </div>
      <div class="drv-aif-msg__bubble">
        <p class="drv-aif-msg__text">${escapeHtml(text)}</p>
      </div>`;

    log.appendChild(el);
    log.scrollTop = log.scrollHeight;
  }

  appendTypingIndicator() {
    const log = this.querySelector('[data-aif-messages]');
    if (!log) {
      return null;
    }

    const el = document.createElement('div');
    el.className = 'drv-aif-msg drv-aif-msg--bot';
    el.innerHTML = `
      <div class="drv-aif-msg__avatar" aria-hidden="true">
        <i class="ph-fill ph-sparkle"></i>
      </div>
      <div class="drv-aif-msg__bubble">
        <div class="drv-aif-typing-wrap" aria-label="Đang lọc thông số và tính số thùng">
          <div class="drv-aif-typing-dots"><span></span><span></span><span></span></div>
          <span>Đang lọc thông số &amp; tính số thùng...</span>
        </div>
      </div>`;

    log.appendChild(el);
    log.scrollTop = log.scrollHeight;
    return el;
  }

  appendBotResponse(data) {
    const log = this.querySelector('[data-aif-messages]');
    if (!log) {
      return;
    }

    const summary = String(data.summary || '');
    const clarification = data.clarification ? String(data.clarification) : '';
    const chips = Array.isArray(data.quick_chips) ? data.quick_chips : [];
    const items = Array.isArray(data.items) ? data.items : [];

    const headHtml =
      items.length > 0
        ? `<div class="drv-aif-msg__head">
             <span class="drv-aif-msg__head-label">Đề xuất kỹ thuật</span>
             <span class="drv-aif-pill drv-aif-pill--navy u-tabular">${items.length} mẫu phù hợp</span>
           </div>`
        : `<div class="drv-aif-msg__sender">Cố vấn kỹ thuật Dravit</div>`;

    let extraHtml = '';

    if (clarification) {
      extraHtml += `
        <div class="drv-aif-clarify">
          <i class="ph-fill ph-chat-circle-dots" aria-hidden="true"></i>
          <span>${escapeHtml(clarification)}</span>
        </div>`;
    }

    // Thẻ thu gọn hiển thị ngay trong luồng chat khi ở chế độ Chat đơn hoặc Mobile
    if (items.length > 0) {
      const topThree = items.slice(0, 3);
      const miniRows = topThree
        .map((it) => {
          const calc = it.calc && typeof it.calc === 'object' ? it.calc : null;
          const boxes = calc?.boxes ? Number(calc.boxes) : 1;
          const priceLbl =
            it.price_state === 'quote_only' || !it.price_m2
              ? 'Liên hệ báo giá'
              : `${formatVnd(it.price_m2)}/m²`;
          const quotePayload = escapeHtml(
            JSON.stringify({
              product_id: Number(it.id || 0),
              sku: String(it.sku || ''),
              title: String(it.title || ''),
              url: String(it.url || ''),
              img: String(it.img_primary || ''),
              size_label: String(it.size_label || ''),
              price_state: String(it.price_state || 'quote_only'),
              price_m2: it.price_m2 ? Number(it.price_m2) : null,
              pieces_per_box: Number(it.pieces_per_box || 4),
              m2_per_box: Number(it.area_box || 1.44),
              area_m2: calc?.input_area_m2 ? Number(calc.input_area_m2) : Number(it.area_box || 1.44),
              waste_pct: this.getWastePct(),
              boxes,
              pieces: calc?.pieces ? Number(calc.pieces) : Number(it.pieces_per_box || 4),
              actual_m2: calc?.actual_m2 ? Number(calc.actual_m2) : Number(it.area_box || 1.44),
              weight_kg: calc?.weight_kg ? Number(calc.weight_kg) : 0,
              est_total_vnd: calc?.est_total_vnd ? Number(calc.est_total_vnd) : null,
            }),
          );

          const thumbHtml = it.img_primary
            ? `<div class="drv-aif-mini-card__thumb"><img src="${escapeHtml(it.img_primary)}" alt="" loading="lazy"></div>`
            : '';

          return `
            <div class="drv-aif-mini-card">
              ${thumbHtml}
              <div class="drv-aif-mini-card__info">
                <a class="drv-aif-mini-card__title" href="${escapeHtml(it.url || this.config.defaultFilterUrl)}">
                  ${escapeHtml(it.sku ? `${it.sku} · ${it.title}` : it.title)}
                </a>
                <div class="drv-aif-mini-card__meta u-tabular">
                  <strong>${boxes} thùng</strong> · ${escapeHtml(priceLbl)}${it.dcof_r ? ` · ${escapeHtml(it.dcof_r)}` : ''}
                </div>
              </div>
              <button type="button" class="drv-btn drv-btn--outline drv-btn--sm" data-drv-ai-add-quote="${quotePayload}" data-aif-quote-boxes="${boxes}" aria-label="Thêm ${escapeHtml(it.sku || it.title)} vào báo giá">
                <i class="ph-bold ph-plus-circle drv-icon--xs" aria-hidden="true"></i>
              </button>
            </div>`;
        })
        .join('');

      extraHtml += `<div class="drv-aif-inline-cards">${miniRows}</div>`;
    }

    if (chips.length > 0) {
      extraHtml += `<div class="drv-aif-msg__chips">${chips
        .map(
          (chip) =>
            `<button type="button" class="drv-aif-chip" data-aif-prompt="${escapeHtml(chip)}"><i class="ph ph-sparkle" aria-hidden="true"></i><span>${escapeHtml(chip)}</span></button>`,
        )
        .join('')}</div>`;
    }

    const el = document.createElement('div');
    el.className = 'drv-aif-msg drv-aif-msg--bot';
    el.innerHTML = `
      <div class="drv-aif-msg__avatar" aria-hidden="true">
        <i class="ph-fill ph-sparkle"></i>
      </div>
      <div class="drv-aif-msg__bubble">
        ${headHtml}
        <p class="drv-aif-msg__text">${formatRichSummaryHtml(summary)}</p>
        ${extraHtml}
      </div>`;

    log.appendChild(el);
    log.scrollTop = log.scrollHeight;
    this.syncAddedCardStates();
  }

  openQuickSpecModal(rawSpecJson) {
    if (!rawSpecJson) {
      return;
    }

    const dialog = this.querySelector('[data-aif-spec-dialog]');
    const titleEl = this.querySelector('[data-aif-spec-modal-title]');
    const bodyEl = this.querySelector('[data-aif-spec-modal-body]');
    const linkEl = this.querySelector('[data-aif-spec-modal-link]');
    if (!dialog || !bodyEl) {
      return;
    }

    try {
      const spec = JSON.parse(rawSpecJson);
      this.activeSpecQuotePayload = spec.quote || null;

      if (titleEl) {
        titleEl.textContent = spec.sku
          ? `Thông số kỹ thuật · ${spec.sku}`
          : 'Thông số kỹ thuật';
      }
      if (linkEl && spec.url) {
        linkEl.setAttribute('href', String(spec.url));
      }

      const priceDisplay =
        spec.price_state === 'quote_only' || !spec.price_m2
          ? 'Liên hệ báo giá dự án'
          : `${formatVnd(spec.price_m2)}/m²`;

      const imgBlock = spec.img
        ? `<div class="drv-aif-spec-hero__img"><img src="${escapeHtml(spec.img)}" alt="${escapeHtml(spec.title || '')}"></div>`
        : '';

      const rows = [
        ['Kích thước danh định', spec.size_label],
        ['Chất liệu xương gạch', spec.material_label],
        ['Bề mặt hoàn thiện', spec.surface_label],
        ['Chỉ số chống trượt', spec.dcof_label],
        ['Độ hút nước (ISO)', spec.water_label],
        ['Độ dày tiêu chuẩn', spec.thickness_label],
        ['Quy cách đóng gói', spec.pack_label],
        ['Tình trạng kho / Kệ mẫu', `${spec.stock_label || 'Sẵn kho'} · ${spec.origin_label || ''}`],
      ];

      bodyEl.innerHTML = `
        <div class="drv-aif-spec-hero">
          ${imgBlock}
          <div>
            ${spec.sku ? `<span class="drv-aif-spec-hero__sku u-tabular">${escapeHtml(spec.sku)}</span>` : ''}
            <h3 class="drv-aif-spec-hero__title">${escapeHtml(spec.title || '')}</h3>
            <div class="drv-aif-spec-hero__price u-tabular">${escapeHtml(priceDisplay)}</div>
          </div>
        </div>
        <div class="drv-aif-spec-table">
          ${rows
            .map(
              ([lbl, val]) => `
            <div class="drv-aif-spec-cell">
              <span>${escapeHtml(lbl)}</span>
              <strong>${escapeHtml(val || 'Tiêu chuẩn')}</strong>
            </div>`,
            )
            .join('')}
        </div>
        ${spec.why ? `<p class="drv-aif-spec-note"><strong>Nhận định kỹ thuật:</strong> ${escapeHtml(spec.why)}</p>` : ''}
      `;

      openDialogSafe(dialog);
    } catch {
      // Ignore malformed spec JSON
    }
  }

  addCardPayloadToQuote(buttonEl) {
    const raw = buttonEl.dataset.drvAiAddQuote || '';
    if (!raw) {
      return;
    }

    try {
      const payload = JSON.parse(raw);
      this.upsertPayloadToQuote(payload);
      this.markButtonInQuote(buttonEl, Number(payload.boxes || 1));
    } catch {
      // Ignore malformed attribute
    }
  }

  upsertPayloadToQuote(payload) {
    if (!payload || !payload.product_id) {
      return;
    }

    const activeArea =
      Number(this.slots.area_m2) > 0
        ? Number(this.slots.area_m2)
        : Number(payload.area_m2 || payload.m2_per_box || 1.44);

    const isQuoteOnly = String(payload.price_state || 'quote_only') === 'quote_only';
    const priceM2 = !isQuoteOnly && payload.price_m2 ? Number(payload.price_m2) : null;

    upsertQuoteItem({
      product_id: Number(payload.product_id),
      sku: String(payload.sku || ''),
      title: String(payload.title || ''),
      url: String(payload.url || ''),
      img: String(payload.img || ''),
      size_label: String(payload.size_label || ''),
      price_state: String(payload.price_state || 'quote_only'),
      price_m2: priceM2,
      pieces_per_box: Math.max(1, Number(payload.pieces_per_box || 4)),
      m2_per_box: Number(payload.m2_per_box || 1.44),
      kg_per_box: Number(payload.kg_per_box || 0),
      area_m2: activeArea,
      waste_pct: this.getWastePct(),
      boxes: Math.max(1, Number(payload.boxes || 1)),
      pieces: Math.max(1, Number(payload.pieces || 1)),
      actual_m2: Number(payload.actual_m2 || activeArea),
      weight_kg: Number(payload.weight_kg || 0),
      est_total_vnd: !isQuoteOnly && payload.est_total_vnd ? Number(payload.est_total_vnd) : null,
    });

    this.syncQuoteBadges();
    this.syncAddedCardStates();
    this.renderQuoteDrawer();
  }

  markButtonInQuote(buttonEl, boxes = 1) {
    buttonEl.classList.add('is-in-quote');
    const isCompactMiniBtn = Boolean(buttonEl.closest('.drv-aif-mini-card'));
    if (isCompactMiniBtn) {
      buttonEl.innerHTML = '<i class="ph-bold ph-check-circle drv-icon--xs" aria-hidden="true"></i>';
      return;
    }
    const safeBoxes = Math.max(1, Number(boxes || buttonEl.dataset.aifQuoteBoxes || 1));
    buttonEl.innerHTML = `<i class="ph-bold ph-check-circle drv-icon--xs" aria-hidden="true"></i><span>Đã chọn (${safeBoxes} thùng)</span>`;
  }

  unmarkButtonInQuote(buttonEl) {
    buttonEl.classList.remove('is-in-quote');
    const isCompactMiniBtn = Boolean(buttonEl.closest('.drv-aif-mini-card'));
    if (isCompactMiniBtn) {
      buttonEl.innerHTML = '<i class="ph-bold ph-plus-circle drv-icon--xs" aria-hidden="true"></i>';
      return;
    }
    buttonEl.innerHTML =
      '<i class="ph-bold ph-plus-circle drv-icon--xs" aria-hidden="true"></i><span>Thêm báo giá</span>';
  }

  syncAddedCardStates() {
    const state = getQuoteState();
    const byId = new Map();
    (state.items || []).forEach((item) => {
      const pid = Number(item?.product_id || 0);
      if (pid > 0) {
        byId.set(pid, Math.max(1, Number(item.boxes || 1)));
      }
    });

    this.querySelectorAll('[data-drv-ai-add-quote]').forEach((btn) => {
      try {
        const parsed = JSON.parse(btn.dataset.drvAiAddQuote || '{}');
        const pid = Number(parsed.product_id || 0);
        if (byId.has(pid)) {
          this.markButtonInQuote(btn, byId.get(pid));
        } else {
          this.unmarkButtonInQuote(btn);
        }
      } catch {
        // Ignore
      }
    });
  }

  syncQuoteBadges() {
    const totalSkus = countQuoteItems();
    const state = getQuoteState();
    const totalBoxes = (state.items || []).reduce(
      (sum, it) => sum + Math.max(0, Number(it?.boxes || 0)),
      0,
    );

    document
      .querySelectorAll('[data-drv-quote-count], [data-quote-badge-count]')
      .forEach((badge) => {
        badge.textContent = String(totalSkus);
      });

    this.querySelectorAll('[data-aif-quote-boxes-total]').forEach((el) => {
      el.textContent = `${totalBoxes} thùng`;
    });
  }

  openQuoteDrawer() {
    const dialog = this.querySelector('[data-aif-quote-dialog]');
    if (!dialog) {
      return;
    }
    this.renderQuoteDrawer();
    openDialogSafe(dialog);
  }

  stepQuoteItemBoxes(itemKey, delta) {
    const state = getQuoteState();
    const target = (state.items || []).find(
      (it) => `id:${it.product_id}` === itemKey || String(it.product_id) === itemKey,
    );
    if (!target) {
      return;
    }

    const nextBoxes = Math.max(1, Number(target.boxes || 1) + delta);
    const perBox = Math.max(1, Number(target.pieces_per_box || 4));
    const m2PerBox =
      Number(target.m2_per_box) > 0
        ? Number(target.m2_per_box)
        : Number(target.actual_m2 || 1.44) / Math.max(1, Number(target.boxes || 1));
    const kgPerBox = Number(target.kg_per_box || 0);
    const nextActualM2 = Number((nextBoxes * m2PerBox).toFixed(2));
    const isQuoteOnly = target.price_state === 'quote_only' || !target.price_m2;

    upsertQuoteItem({
      ...target,
      boxes: nextBoxes,
      pieces: nextBoxes * perBox,
      actual_m2: nextActualM2,
      weight_kg: kgPerBox > 0 ? Number((nextBoxes * kgPerBox).toFixed(1)) : Number(target.weight_kg || 0),
      est_total_vnd: !isQuoteOnly ? Math.round(nextActualM2 * Number(target.price_m2)) : null,
    });
  }

  renderQuoteDrawer() {
    const listEl = this.querySelector('[data-aif-qp-list]');
    const summaryEl = this.querySelector('[data-aif-qp-summary]');
    const skusEl = this.querySelector('[data-aif-qp-skus]');
    const m2El = this.querySelector('[data-aif-qp-m2]');
    const boxesEl = this.querySelector('[data-aif-qp-boxes]');
    const clearBtn = this.querySelector('[data-aif-qp-clear]');

    if (!listEl) {
      return;
    }

    const state = getQuoteState();
    const items = (state.items || []).filter(
      (it) => it && (Number(it.product_id) > 0 || Boolean(it.sku)),
    );

    const totalBoxes = items.reduce((s, it) => s + Math.max(1, Number(it.boxes || 1)), 0);
    const totalM2 = items.reduce((s, it) => s + Number(it.actual_m2 || it.area_m2 || 0), 0);

    if (summaryEl) {
      summaryEl.textContent = `${items.length} mẫu · Tổng ${totalBoxes} thùng`;
    }
    if (skusEl) {
      skusEl.textContent = `${items.length} SKU`;
    }
    if (m2El) {
      m2El.textContent = `${formatM2(totalM2)} m²`;
    }
    if (boxesEl) {
      boxesEl.textContent = `${totalBoxes} thùng`;
    }
    if (clearBtn) {
      clearBtn.hidden = items.length === 0;
    }

    if (items.length === 0) {
      listEl.innerHTML = `
        <div class="drv-qp__empty">
          <i class="ph-bold ph-stack" aria-hidden="true"></i>
          <p>Chưa có mẫu gạch trong danh sách báo giá</p>
          <span>Bấm "Thêm báo giá" trên các thẻ đề xuất để tính tổng số thùng tự động.</span>
        </div>`;
      return;
    }

    listEl.innerHTML = items
      .map((it) => {
        const key = `id:${it.product_id}`;
        const boxes = Math.max(1, Number(it.boxes || 1));
        const actualM2 = formatM2(it.actual_m2 || it.area_m2 || 0);
        const priceLabel =
          it.price_state === 'quote_only' || !it.price_m2
            ? 'Liên hệ báo giá'
            : it.est_total_vnd
              ? formatVnd(it.est_total_vnd)
              : `${formatVnd(it.price_m2)}/m²`;

        const thumb = it.img
          ? `<div class="drv-qp-item__thumb"><img src="${escapeHtml(it.img)}" alt="" loading="lazy"></div>`
          : '';

        return `
          <div class="drv-qp-item">
            ${thumb}
            <div class="drv-qp-item__body">
              <div class="drv-qp-item__top">
                <a class="drv-qp-item__title" href="${escapeHtml(it.url || this.config.defaultFilterUrl)}">
                  ${escapeHtml(it.sku ? `${it.sku} · ${it.title}` : it.title)}
                </a>
                <button type="button" class="drv-qp-item__del" data-aif-qp-remove="${escapeHtml(key)}" aria-label="Xóa ${escapeHtml(it.title)}">
                  <i class="ph-bold ph-trash" aria-hidden="true"></i>
                </button>
              </div>
              <div class="drv-qp-item__meta u-tabular">
                Phủ thực tế ${escapeHtml(actualM2)} m²${it.size_label ? ` · ${escapeHtml(it.size_label)}` : ''}
              </div>
              <div class="drv-qp-item__ctrl">
                <div class="drv-qp-stepper">
                  <button type="button" data-aif-qp-step="-1" data-aif-qp-key="${escapeHtml(key)}" aria-label="Giảm 1 thùng">-</button>
                  <span class="u-tabular">${boxes} thùng</span>
                  <button type="button" data-aif-qp-step="1" data-aif-qp-key="${escapeHtml(key)}" aria-label="Tăng 1 thùng">+</button>
                </div>
                <span class="drv-qp-item__price u-tabular">${escapeHtml(priceLabel)}</span>
              </div>
            </div>
          </div>`;
      })
      .join('');
  }

  resetConversation(resetChatStream = false) {
    this.slots = EMPTY_SLOTS();
    this.querySelectorAll('[data-aif-refine-slot]').forEach((b) =>
      b.classList.remove('is-active'),
    );
    this.renderContextTags([]);

    if (resetChatStream) {
      const log = this.querySelector('[data-aif-messages]');
      if (log) {
        log.querySelectorAll('.drv-aif-msg:not([data-aif-welcome])').forEach((n) => n.remove());
      }
    }

    this.sendAdvisorRequest({ message: '', fromChip: true, silentBubble: true });
  }

  async submitFeedback(rating, reason = '') {
    if (!this.lastRequestId) {
      return;
    }

    try {
      const nonce = await this.ensureNonce(false);
      const headers = {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      };
      if (nonce) {
        headers['X-DRV-Nonce'] = nonce;
      }

      await fetch(this.config.feedbackEndpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers,
        body: JSON.stringify({
          request_id: this.lastRequestId,
          rating,
          reason,
        }),
      });

      const thanks = this.querySelector('[data-aif-feedback-thanks]');
      const reasonsBox = this.querySelector('[data-aif-feedback-reasons]');
      if (thanks && (rating === 'up' || reason !== '')) {
        thanks.hidden = false;
        if (reasonsBox) {
          reasonsBox.hidden = true;
        }
      }
    } catch {
      // Silent feedback failure
    }
  }

  setLoadingState(isLoading) {
    const submitBtn = this.querySelector('[data-aif-submit]');
    if (submitBtn) {
      submitBtn.disabled = isLoading;
    }

    const skeleton = this.querySelector('[data-aif-skeleton]');
    const grid = this.querySelector('[data-aif-grid]');
    const emptyBox = this.querySelector('[data-aif-empty]');

    if (skeleton) {
      skeleton.hidden = !isLoading;
    }
    if (isLoading) {
      if (grid) {
        grid.hidden = true;
      }
      if (emptyBox) {
        emptyBox.hidden = true;
      }
    }
  }
}

export function registerAiFinderElement() {
  if (typeof customElements !== 'undefined' && !customElements.get('drv-ai-finder')) {
    customElements.define('drv-ai-finder', AiFinderElement);
  }
}

registerAiFinderElement();
