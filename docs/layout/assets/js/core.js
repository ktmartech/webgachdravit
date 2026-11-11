/**
 * DRAVIT Core JS — Shared Utilities, Global Event Delegation & UI Controllers
 * Spec: docs/02_Technical_Architecture.md (§8B) & .agents/rules/dravit-frontend-ui.md
 */
(() => {
  'use strict';

  const nfCurrency = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 });

  /**
   * Format integer VND currency per Vietnamese standard: 385.000đ
   * @param {number|string} amount
   * @returns {string}
   */
  function formatVnd(amount) {
    const val = Math.round(Number(amount) || 0);
    return `${nfCurrency.format(val)}đ`;
  }

  /**
   * Format decimal number with Vietnamese comma: 11,52
   * @param {number|string} num
   * @param {number} [maxDecimals=2]
   * @returns {string}
   */
  function formatDecimalVn(num, maxDecimals = 2) {
    const val = Number(num) || 0;
    return new Intl.NumberFormat('vi-VN', {
      minimumFractionDigits: 0,
      maximumFractionDigits: maxDecimals,
    }).format(val);
  }

  /**
   * Standard debounce utility
   * @param {Function} fn
   * @param {number} [wait=200]
   */
  function debounce(fn, wait = 200) {
    let timer = null;
    return function debounced(...args) {
      window.clearTimeout(timer);
      timer = window.setTimeout(() => fn.apply(this, args), wait);
    };
  }

  /**
   * Build a decorative Phosphor icon element (no HTML parsing involved)
   * @param {string} classes space-separated icon classes
   * @returns {HTMLElement}
   */
  function createIcon(classes) {
    const icon = document.createElement('i');
    icon.className = classes;
    icon.setAttribute('aria-hidden', 'true');
    return icon;
  }

  const ERROR_LOG_LIMIT = 50;

  /** True when verbose diagnostics were requested (?drvdebug=1 or localStorage drv:debug=1) */
  function isDebugEnabled() {
    try {
      return /[?&]drvdebug=1\b/.test(window.location.search) || window.localStorage.getItem('drv:debug') === '1';
    } catch (e) {
      return /[?&]drvdebug=1\b/.test(window.location.search);
    }
  }

  /**
   * Central, controlled error sink: swallowed failures are recorded here instead of vanishing.
   *  - kept in window.__DRV_ERRORS__ (last ${ERROR_LOG_LIMIT}), inspectable from DevTools or tests
   *  - announced as a 'drv:error' CustomEvent so a monitoring script can forward them
   *  - printed to the console only when debug is enabled (no noise for visitors)
   * @param {string} scope short module/feature name, e.g. 'click', 'storage'
   * @param {unknown} error
   * @param {Record<string, unknown>} [context]
   */
  function reportError(scope, error, context) {
    const entry = {
      scope,
      message: error && error.message ? String(error.message) : String(error),
      context: context || null,
      time: Date.now(),
    };
    const log = (window.__DRV_ERRORS__ = window.__DRV_ERRORS__ || []);
    log.push(entry);
    if (log.length > ERROR_LOG_LIMIT) log.shift();
    try {
      window.dispatchEvent(new CustomEvent('drv:error', { detail: entry }));
    } catch (e) {
      // dispatching must never throw
    }
    if (isDebugEnabled()) console.warn('[drv:' + scope + ']', error, context || '');
  }

  /**
   * Per-controller AbortController registry.
   * Re-running an init (e.g. after the header/footer markup is swapped) first tears down the
   * previous run's listeners and observers, so handlers never stack up.
   * @type {Map<string, AbortController>}
   */
  const controllerScopes = new Map();

  /**
   * @param {string} name unique controller name
   * @returns {AbortSignal} aborted automatically the next time the same name is mounted
   */
  function mountScope(name) {
    const previous = controllerScopes.get(name);
    if (previous) previous.abort();
    const controller = new AbortController();
    controllerScopes.set(name, controller);
    return controller.signal;
  }

  /**
   * Accessible Toast Notification
   * @param {string} message
   */
  function toast(message) {
    let region = document.querySelector('.drv-toast-region');
    if (!region) {
      region = document.createElement('div');
      region.className = 'drv-toast-region';
      region.setAttribute('aria-live', 'polite');
      document.body.appendChild(region);
    }

    const item = document.createElement('div');
    item.className = 'drv-toast';
    item.setAttribute('role', 'status');

    const textSpan = document.createElement('span');
    textSpan.textContent = message;

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'drv-btn drv-btn--sm drv-btn--ghost drv-btn--icon';
    closeBtn.style.color = 'var(--c-ivory)';
    closeBtn.setAttribute('aria-label', 'Đóng thông báo');
    closeBtn.innerHTML = '<i class="ph ph-x" aria-hidden="true"></i>';
    closeBtn.addEventListener('click', () => item.remove());

    item.append(textSpan, closeBtn);
    region.appendChild(item);

    window.setTimeout(() => {
      if (item.isConnected) item.remove();
    }, 4000);
  }

  /**
   * Copy text to clipboard with fallback for file:// protocol
   * @param {string} text
   * @returns {Promise<boolean>}
   */
  async function copyToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
      return true;
    }
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    ta.remove();
    return true;
  }

  /* ==========================================================================
     34 Vietnam Provinces (Effective 01/07/2025 — docs/04_Pricing_Units_Commerce.md §5.2)
     ========================================================================== */
  const VN_PROVINCES = [
    { code: 'HCM', name: 'TP. Hồ Chí Minh', wards: ['Phường Sài Gòn', 'Phường Tân Định', 'Phường Bến Thành', 'Phường Thảo Điền', 'Phường An Phú', 'Phường Hiệp Bình', 'Phường Phú Mỹ Hưng', 'Phường Tân Phong', 'Phường Linh Trung', 'Xã Bình Hưng'] },
    { code: 'HN', name: 'Hà Nội', wards: ['Phường Ba Đình', 'Phường Hoàn Kiếm', 'Phường Đống Đa', 'Phường Cầu Giấy', 'Phường Tây Hồ', 'Phường Mỹ Đình', 'Phường Hoàng Mai', 'Phường Long Biên', 'Phường Hà Đông'] },
    { code: 'DN', name: 'Đà Nẵng', wards: ['Phường Hải Châu', 'Phường Thanh Khê', 'Phường Sơn Trà', 'Phường Ngũ Hành Sơn', 'Phường Liên Chiểu', 'Phường Cẩm Lệ', 'Phường Hội An'] },
    { code: 'DNA', name: 'Đồng Nai', wards: ['Phường Biên Hòa', 'Phường Trấn Biên', 'Phường Long Bình', 'Phường Long Thành', 'Phường Nhơn Trạch', 'Phường Long Khánh', 'Phường Đồng Xoài'] },
    { code: 'BDG', name: 'Tây Ninh', wards: ['Phường Tân Ninh', 'Phường Hòa Thành', 'Phường Trảng Bàng', 'Phường Gò Dầu', 'Phường Bến Lức', 'Phường Tân An', 'Phường Đức Hòa'] },
    { code: 'LDG', name: 'Lâm Đồng', wards: ['Phường Xuân Hương - Đà Lạt', 'Phường Lâm Viên - Đà Lạt', 'Phường Bảo Lộc', 'Phường Phan Thiết', 'Phường Mũi Né', 'Phường Gia Nghĩa'] },
    { code: 'KHA', name: 'Khánh Hòa', wards: ['Phường Nha Trang', 'Phường Bắc Nha Trang', 'Phường Cam Ranh', 'Phường Ninh Hòa', 'Phường Phan Rang'] },
    { code: 'CTO', name: 'Cần Thơ', wards: ['Phường Ninh Kiều', 'Phường Cái Răng', 'Phường Bình Thủy', 'Phường Vị Thanh', 'Phường Sóc Trăng'] },
    { code: 'HPG', name: 'Hải Phòng', wards: ['Phường Hồng Bàng', 'Phường Ngô Quyền', 'Phường Lê Chân', 'Phường Hải An', 'Phường Thủy Nguyên', 'Phường Hải Dương'] },
    { code: 'QNH', name: 'Quảng Ninh', wards: ['Phường Hạ Long', 'Phường Bãi Cháy', 'Phường Cẩm Phả', 'Phường Uông Bí', 'Phường Móng Cái'] },
    { code: 'HUE', name: 'Huế', wards: ['Phường Thuận Hóa', 'Phường Phú Xuân', 'Phường Hương Thủy', 'Phường Hương Trà', 'Phường Phong Điền'] },
    { code: 'BNH', name: 'Bắc Ninh', wards: ['Phường Kinh Bắc', 'Phường Từ Sơn', 'Phường Bắc Giang', 'Phường Việt Yên'] },
    { code: 'HYN', name: 'Hưng Yên', wards: ['Phường Phố Hiến', 'Phường Mỹ Hào', 'Phường Thái Bình', 'Xã Văn Giang'] },
    { code: 'NBH', name: 'Ninh Bình', wards: ['Phường Hoa Lư', 'Phường Tam Điệp', 'Phường Nam Định', 'Phường Phủ Lý'] },
    { code: 'THA', name: 'Thanh Hóa', wards: ['Phường Hạc Thành', 'Phường Sầm Sơn', 'Phường Bỉm Sơn', 'Phường Nghi Sơn'] },
    { code: 'NAN', name: 'Nghệ An', wards: ['Phường Thành Vinh', 'Phường Cửa Lò', 'Phường Hoàng Mai', 'Phường Thái Hòa'] },
    { code: 'HTI', name: 'Hà Tĩnh', wards: ['Phường Thành Sen', 'Phường Kỳ Anh', 'Phường Hồng Lĩnh'] },
    { code: 'QTR', name: 'Quảng Trị', wards: ['Phường Đông Hà', 'Phường Đồng Hới', 'Phường Ba Đồn'] },
    { code: 'QNG', name: 'Quảng Ngãi', wards: ['Phường Cẩm Thành', 'Phường Trương Quang Trọng', 'Phường Kon Tum'] },
    { code: 'GLA', name: 'Gia Lai', wards: ['Phường Pleiku', 'Phường Quy Nhơn', 'Phường An Khê', 'Phường Hoài Nhơn'] },
    { code: 'DLK', name: 'Đắk Lắk', wards: ['Phường Buôn Ma Thuột', 'Phường Tuy Hòa', 'Phường Sông Cầu'] },
    { code: 'VLO', name: 'Vĩnh Long', wards: ['Phường Long Châu', 'Phường Bến Tre', 'Phường Trà Vinh'] },
    { code: 'DTH', name: 'Đồng Tháp', wards: ['Phường Cao Lãnh', 'Phường Sa Đéc', 'Phường Mỹ Tho', 'Phường Gò Công'] },
    { code: 'AGG', name: 'An Giang', wards: ['Phường Long Xuyên', 'Phường Châu Đốc', 'Phường Rạch Giá', 'Phường Hà Tiên', 'Đặc khu Phú Quốc'] },
    { code: 'CMU', name: 'Cà Mau', wards: ['Phường Tân Thành', 'Phường Bạc Liêu', 'Phường Giá Rai'] },
    { code: 'TNG', name: 'Thái Nguyên', wards: ['Phường Phan Đình Phùng', 'Phường Sông Công', 'Phường Phổ Yên', 'Phường Bắc Kạn'] },
    { code: 'PTH', name: 'Phú Thọ', wards: ['Phường Việt Trì', 'Phường Vĩnh Yên', 'Phường Hòa Bình'] },
    { code: 'LCA', name: 'Lào Cai', wards: ['Phường Lào Cai', 'Phường Sa Pa', 'Phường Yên Bái'] },
    { code: 'TQU', name: 'Tuyên Quang', wards: ['Phường Minh Xuân', 'Phường Hà Giang'] },
    { code: 'LSN', name: 'Lạng Sơn', wards: ['Phường Đông Kinh', 'Phường Kỳ Lừa'] },
    { code: 'CBA', name: 'Cao Bằng', wards: ['Phường Thục Phán', 'Phường Sông Bằng'] },
    { code: 'SLA', name: 'Sơn La', wards: ['Phường Tô Hiệu', 'Phường Mộc Châu'] },
    { code: 'DBI', name: 'Điện Biên', wards: ['Phường Điện Biên Phủ', 'Phường Mường Lay'] },
    { code: 'LCH', name: 'Lai Châu', wards: ['Phường Tân Phong', 'Phường Đoàn Kết'] },
  ];

  /* ==========================================================================
     Dialog & Drawer Controller
     ========================================================================== */
  let lastTriggerEl = null;

  function openDialog(selector, triggerEl = null) {
    const dialog = document.querySelector(selector);
    if (!dialog || typeof dialog.showModal !== 'function') return;
    lastTriggerEl = triggerEl;

    // Optional prefill for Project Quote Modal
    if (triggerEl && triggerEl.dataset.drvProduct) {
      const productInput = dialog.querySelector('[name="quote_product"]');
      if (productInput) productInput.value = triggerEl.dataset.drvProduct;
    }
    if (triggerEl && triggerEl.dataset.drvArea) {
      const areaInput = dialog.querySelector('[name="quote_area"]');
      if (areaInput) areaInput.value = triggerEl.dataset.drvArea;
    }

    dialog.classList.remove('is-closing');
    if (!dialog.open) {
      dialog.showModal();
      document.body.style.overflow = 'hidden';
      const body = dialog.querySelector('.drv-dialog__body');
      if (body && typeof motion?.staggerReveal === 'function') {
        motion.staggerReveal(body);
      }
    }
  }

  function closeDialog(dialog) {
    if (!dialog) return;
    const finishClose = () => {
      dialog.classList.remove('is-closing');
      if (dialog.open && typeof dialog.close === 'function') {
        dialog.close();
      }
      document.body.style.overflow = '';
      if (lastTriggerEl && typeof lastTriggerEl.focus === 'function') {
        lastTriggerEl.focus();
        lastTriggerEl = null;
      }
    };

    if (dialog.open && !dialog.classList.contains('is-closing')) {
      dialog.classList.add('is-closing');
      window.setTimeout(finishClose, 235);
    } else {
      finishClose();
    }
  }

  /* ==========================================================================
     Global Event Delegation on `document` ([data-drv-action])
     ========================================================================== */
  /**
   * One document-level 'click' and one 'keydown' listener drive every delegated handler.
   * Handlers run in registration order; a failing handler is reported and does not block the rest.
   */
  const clickRoutes = [];
  const keydownRoutes = [];

  function createDispatcher(routes, scope) {
    return (event) => {
      for (const route of routes) {
        try {
          const result = route(event);
          if (result && typeof result.catch === 'function') result.catch((err) => reportError(scope, err));
        } catch (err) {
          reportError(scope, err);
        }
      }
    };
  }

  /** @param {(event: MouseEvent) => (void|Promise<void>)} handler */
  function onDocumentClick(handler) {
    clickRoutes.push(handler);
  }

  /** @param {(event: KeyboardEvent) => void} handler */
  function onDocumentKeydown(handler) {
    keydownRoutes.push(handler);
  }

  document.addEventListener('click', createDispatcher(clickRoutes, 'click'));
  document.addEventListener('keydown', createDispatcher(keydownRoutes, 'keydown'));

  document.addEventListener('mousedown', (event) => {
    if (event.detail > 1 && event.target.closest('.drv-lightbox-stage, .drv-lightbox-thumbs')) {
      event.preventDefault();
    }
  });

  onDocumentClick(async (event) => {
    const actionEl = event.target.closest('[data-drv-action]');

    // Close Mega Menu when clicking outside
    const megaMenu = document.getElementById('drv-mega-menu');
    if (megaMenu && !megaMenu.hidden) {
      const insideMega = event.target.closest('#drv-mega-menu, [data-drv-action="mega-toggle"]');
      if (!insideMega) {
        megaMenu.hidden = true;
        document.querySelectorAll('[data-drv-action="mega-toggle"]').forEach((btn) => {
          btn.setAttribute('aria-expanded', 'false');
        });
      }
    }

    // Close Instant Search dropdown & backdrop when clicking outside
    document.querySelectorAll('.drv-search.is-open').forEach((searchWrap) => {
      const clickedInsideFormOrDrop = event.target.closest(
        '.drv-search__form, .drv-search-drop, [data-drv-action="search-toggle"]'
      );
      if (!clickedInsideFormOrDrop) {
        if (typeof searchWrap._closeSearchOverlay === 'function') {
          searchWrap._closeSearchOverlay();
        } else {
          searchWrap.classList.remove('is-open');
          searchWrap.closest('.drv-header')?.classList.remove('has-search-open');
          const drop = searchWrap.querySelector('.drv-search-drop');
          const bd = searchWrap.querySelector('[data-drv-search-backdrop]');
          if (drop) drop.hidden = true;
          if (bd) bd.hidden = true;
          document.querySelectorAll('[data-drv-action="search-toggle"]').forEach((btn) => {
            btn.setAttribute('aria-expanded', 'false');
            const icon = btn.querySelector('i');
            if (icon) icon.className = 'ph ph-magnifying-glass drv-icon drv-icon--sm';
          });
        }
      }
    });

    // Click on <dialog> backdrop closes dialog
    if (event.target.tagName === 'DIALOG' && event.target.open) {
      const rect = event.target.getBoundingClientRect();
      const clickedInDialog =
        rect.top <= event.clientY &&
        event.clientY <= rect.top + rect.height &&
        rect.left <= event.clientX &&
        event.clientX <= rect.left + rect.width;
      if (!clickedInDialog) {
        closeDialog(event.target);
        return;
      }
    }

    if (!actionEl) return;

    const action = actionEl.dataset.drvAction;

    switch (action) {
      case 'dialog-open':
      case 'open-dialog': {
        event.preventDefault();
        const target = actionEl.dataset.drvTarget;
        if (target) openDialog(target, actionEl);
        break;
      }

      case 'dialog-close':
      case 'close-dialog': {
        event.preventDefault();
        const target = actionEl.dataset.drvTarget
          ? document.querySelector(actionEl.dataset.drvTarget)
          : actionEl.closest('dialog');
        closeDialog(target);
        break;
      }

      case 'copy-sku':
      case 'copy-text': {
        event.preventDefault();
        const value = actionEl.dataset.drvValue || actionEl.textContent.trim();
        await copyToClipboard(value);
        const origLabel = actionEl.innerHTML;
        const hasTextSpan = !!actionEl.querySelector('span');
        const feedback = actionEl.dataset.drvFeedback || '';

        if (hasTextSpan && feedback) {
          actionEl.replaceChildren(
            createIcon('ph-bold ph-check'),
            document.createTextNode(' '),
            Object.assign(document.createElement('span'), { textContent: feedback })
          );
        } else {
          actionEl.innerHTML = `<i class="ph-bold ph-check u-text-moss" aria-hidden="true"></i>`;
        }

        toast(`Đã sao chép: ${value}`);
        window.setTimeout(() => {
          actionEl.innerHTML = origLabel;
        }, 1600);
        break;
      }

      case 'mega-toggle': {
        event.preventDefault();
        const menu = document.getElementById('drv-mega-menu');
        if (!menu) break;
        const willOpen = menu.hidden;
        menu.hidden = !willOpen;
        if (willOpen) {
          const matrix = menu.querySelector('.drv-mega__matrix');
          if (matrix) motion.staggerReveal(matrix);
        }
        document.querySelectorAll('[data-drv-action="mega-toggle"]').forEach((btn) => {
          btn.setAttribute('aria-expanded', String(willOpen));
        });
        break;
      }

      case 'search-toggle': {
        event.preventDefault();
        const headerEl = actionEl.closest('.drv-header') || document.querySelector('.drv-header');
        const searchWrap = headerEl?.querySelector('.drv-search');
        if (!searchWrap) break;
        const isOpen = searchWrap.classList.contains('is-open');
        if (isOpen) {
          if (typeof searchWrap._closeSearchOverlay === 'function') {
            searchWrap._closeSearchOverlay();
          } else {
            searchWrap.classList.remove('is-open');
            headerEl?.classList.remove('has-search-open');
            const drop = searchWrap.querySelector('.drv-search-drop');
            const bd = searchWrap.querySelector('[data-drv-search-backdrop]');
            if (drop) drop.hidden = true;
            if (bd) bd.hidden = true;
          }
          actionEl.setAttribute('aria-expanded', 'false');
          const icon = actionEl.querySelector('i');
          if (icon) icon.className = 'ph ph-magnifying-glass drv-icon drv-icon--sm';
        } else {
          searchWrap.classList.add('is-open');
          headerEl?.classList.add('has-search-open');
          actionEl.setAttribute('aria-expanded', 'true');
          const icon = actionEl.querySelector('i');
          if (icon) icon.className = 'ph ph-x drv-icon drv-icon--sm';
          const input = searchWrap.querySelector('.drv-search__input');
          if (input) {
            window.requestAnimationFrame(() => {
              input.focus();
              input.click();
            });
          }
        }
        break;
      }

      case 'tab-switch': {
        event.preventDefault();
        const group = actionEl.dataset.drvGroup;
        const tabId = actionEl.dataset.drvTab;
        if (!group || !tabId) break;

        document.querySelectorAll(`[data-drv-action="tab-switch"][data-drv-group="${group}"]`).forEach((btn) => {
          const isCurrent = btn.dataset.drvTab === tabId;
          btn.classList.toggle('is-active', isCurrent);
          btn.setAttribute('aria-selected', String(isCurrent));
          // Roving tabindex for ARIA tablists (only the selected tab is in Tab order)
          if (btn.getAttribute('role') === 'tab') {
            btn.tabIndex = isCurrent ? 0 : -1;
          }
        });

        document.querySelectorAll(`[data-drv-panel-group="${group}"]`).forEach((panel) => {
          const match = panel.dataset.drvPanel === tabId;
          panel.hidden = !match;
          // Replay entrance reveal & staggered tile reveal on the newly shown panel
          if (match) {
            panel.querySelectorAll('[data-drv-reveal]').forEach((el) => motion.revealElement(el));
            panel.querySelectorAll('[data-drv-stagger]').forEach((grid) => motion.staggerReveal(grid));
          }
        });
        break;
      }

      case 'chip-insert': {
        event.preventDefault();
        const targetSel = actionEl.dataset.drvTarget || '#order_comments';
        const text = actionEl.dataset.drvText || actionEl.textContent.trim();
        const field = document.querySelector(targetSel);
        if (field) {
          const parts = field.value
            .split(';')
            .map((s) => s.trim())
            .filter(Boolean);
          const idx = parts.indexOf(text);
          if (idx === -1) {
            parts.push(text);
            actionEl.classList.add('is-active');
            actionEl.setAttribute('aria-pressed', 'true');
          } else {
            parts.splice(idx, 1);
            actionEl.classList.remove('is-active');
            actionEl.setAttribute('aria-pressed', 'false');
          }
          field.value = parts.join('; ');
          field.focus();
        }
        break;
      }

      case 'scroll-carousel': {
        event.preventDefault();
        const target = document.querySelector(actionEl.dataset.drvTarget);
        if (!target) break;
        const dir = actionEl.dataset.drvDir === 'prev' ? -1 : 1;
        target.scrollBy({ left: dir * 280, behavior: 'smooth' });
        break;
      }

      case 'lightbox-open':
      case 'lightbox-thumb':
      case 'lightbox-nav': {
        event.preventDefault();
        event.stopPropagation();
        window.getSelection?.()?.removeAllRanges();
        const dialog = document.getElementById('drv-lightbox-dialog');
        if (!dialog) break;
        const thumbs = Array.from(dialog.querySelectorAll('[data-drv-action="lightbox-thumb"]'));
        if (!thumbs.length) break;

        let targetIdx = 0;
        if (action === 'lightbox-nav') {
          const curIdx = thumbs.findIndex((t) => t.classList.contains('is-active'));
          const delta = actionEl.dataset.drvDir === 'prev' ? -1 : 1;
          targetIdx = ((curIdx >= 0 ? curIdx : 0) + delta + thumbs.length) % thumbs.length;
        } else {
          const src = actionEl.dataset.drvSrc;
          const foundIdx = thumbs.findIndex((t) => t.dataset.drvSrc === src);
          targetIdx = foundIdx >= 0 ? foundIdx : 0;
        }

        const activeThumb = thumbs[targetIdx];
        const imgEl = dialog.querySelector('[data-lightbox-img]');
        const capEl = dialog.querySelector('[data-lightbox-caption]');
        const counterEl = dialog.querySelector('[data-lightbox-counter]');
        const src = activeThumb?.dataset.drvSrc || actionEl.dataset.drvSrc;
        const caption = activeThumb?.dataset.drvCaption || actionEl.dataset.drvCaption || '';

        if (imgEl && src) {
          imgEl.src = src;
          imgEl.alt = caption || 'Ảnh chi tiết gạch Dravit';
        }
        if (capEl) {
          capEl.textContent = caption;
        }
        if (counterEl) {
          counterEl.textContent = `${targetIdx + 1} / ${thumbs.length}`;
        }
        thumbs.forEach((thumb, idx) => {
          const isCurrent = idx === targetIdx;
          thumb.classList.toggle('is-active', isCurrent);
          thumb.setAttribute('aria-current', isCurrent ? 'true' : 'false');
        });
        if (action === 'lightbox-open') {
          openDialog('#drv-lightbox-dialog', actionEl);
        }
        break;
      }

      case 'download-map': {
        event.preventDefault();
        event.stopPropagation();
        const sku = actionEl.dataset.drvSku || 'DRV-8801M';
        toast(`Đã tải gói Map gạch 3D & thông số CAD mã ${sku} (6 faces · 300 DPI).`);
        break;
      }

      case 'add-to-cart': {
        event.preventDefault();
        const sku = actionEl.dataset.drvSku || 'DRV-8801M';
        const qty = actionEl.dataset.drvQty || '18 viên (6 thùng · 11,52 m²)';
        document.querySelectorAll('[data-drv-cart-count]').forEach((badge) => {
          const current = parseInt(badge.textContent, 10) || 2;
          badge.textContent = String(current + 1);
        });
        motion.bounceCartBadge();
        toast(`Đã thêm ${qty} mã ${sku} vào giỏ hàng.`);
        if (actionEl.dataset.drvOpenDrawer === 'true' && document.getElementById('drv-minicart-drawer')) {
          openDialog('#drv-minicart-drawer', actionEl);
        }
        break;
      }

      case 'quote-add': {
        event.preventDefault();
        const sku = actionEl.dataset.drvSku || 'DRV-8801M';
        toast(`Đã thêm mã ${sku} vào Danh sách báo giá.`);
        break;
      }

      case 'wish-toggle': {
        event.preventDefault();
        const sku = actionEl.dataset.drvSku || '';
        const on = actionEl.getAttribute('aria-pressed') !== 'true';
        actionEl.setAttribute('aria-pressed', String(on));
        const icon = actionEl.querySelector('i');
        if (icon) icon.className = on ? 'ph-fill ph-heart' : 'ph ph-heart';
        toast(on ? `Đã lưu mẫu ${sku} vào danh sách quan tâm.` : `Đã bỏ lưu mẫu ${sku}.`);
        break;
      }

      case 'motion-replay-stagger': {
        event.preventDefault();
        const targetSel = actionEl.dataset.drvTarget || '#drv-stagger-demo-grid';
        const container = document.querySelector(targetSel);
        if (container) {
          motion.staggerReveal(container);
        }
        break;
      }

      case 'motion-demo-odometer': {
        event.preventDefault();
        const presetArea = Number(actionEl.dataset.drvArea) || 25;
        const wastePct = 5;
        const sqmPerPiece = 0.64;
        const piecesPerBox = 3;
        const pricePerSqm = 465000;

        const neededSqm = presetArea * (1 + wastePct / 100);
        const pieces = Math.ceil(neededSqm / sqmPerPiece);
        const boxes = Math.ceil(pieces / piecesPerBox);
        const actualSqm = boxes * piecesPerBox * sqmPerPiece;
        const totalVnd = Math.round(actualSqm * pricePerSqm);

        const elArea = document.getElementById('drv-odo-area');
        const elPieces = document.getElementById('drv-odo-pieces');
        const elBoxes = document.getElementById('drv-odo-boxes');
        const elTotal = document.getElementById('drv-odo-total');

        if (elArea) {
          const prev = Number(elArea.dataset.drvVal || 11.52);
          elArea.dataset.drvVal = String(actualSqm);
          motion.animateNumber(elArea, prev, actualSqm, (v) => `${formatDecimalVn(v, 2)} m²`, 380);
        }
        if (elPieces) {
          const prev = Number(elPieces.dataset.drvVal || 18);
          elPieces.dataset.drvVal = String(pieces);
          motion.animateNumber(elPieces, prev, pieces, (v) => `${Math.round(v)} viên`, 380);
        }
        if (elBoxes) {
          const prev = Number(elBoxes.dataset.drvVal || 6);
          elBoxes.dataset.drvVal = String(boxes);
          motion.animateNumber(elBoxes, prev, boxes, (v) => `${Math.round(v)} thùng`, 380);
        }
        if (elTotal) {
          const prev = Number(elTotal.dataset.drvVal || 5356800);
          elTotal.dataset.drvVal = String(totalVnd);
          motion.animateNumber(elTotal, prev, totalVnd, (v) => formatVnd(v), 420);
        }
        motion.bounceCartBadge();
        break;
      }

      default:
        break;
    }
  });

  // Animate out & restore scroll when any native <dialog> closes via Escape key
  document.addEventListener('cancel', (event) => {
    if (event.target && event.target.tagName === 'DIALOG') {
      event.preventDefault();
      closeDialog(event.target);
    }
  }, true);

  document.addEventListener('close', (event) => {
    if (event.target && event.target.tagName === 'DIALOG') {
      document.body.style.overflow = '';
    }
  }, true);

  // WAI-ARIA Tabs keyboard pattern: Arrow Left/Right, Home, End move & activate tabs
  onDocumentKeydown((event) => {
    const tab = event.target.closest?.('[role="tab"][data-drv-action="tab-switch"]');
    if (!tab) return;
    const keys = ['ArrowLeft', 'ArrowRight', 'Home', 'End'];
    if (!keys.includes(event.key)) return;

    const tabs = Array.from(
      document.querySelectorAll(`[role="tab"][data-drv-action="tab-switch"][data-drv-group="${tab.dataset.drvGroup}"]`)
    );
    const idx = tabs.indexOf(tab);
    let next = idx;
    if (event.key === 'ArrowRight') next = (idx + 1) % tabs.length;
    if (event.key === 'ArrowLeft') next = (idx - 1 + tabs.length) % tabs.length;
    if (event.key === 'Home') next = 0;
    if (event.key === 'End') next = tabs.length - 1;

    event.preventDefault();
    tabs[next].focus();
    tabs[next].click();
  });

  onDocumentKeydown((event) => {
    if (event.key === 'Escape') {
      const megaMenu = document.getElementById('drv-mega-menu');
      if (megaMenu && !megaMenu.hidden) {
        megaMenu.hidden = true;
        document.querySelectorAll('[data-drv-action="mega-toggle"]').forEach((btn) => {
          btn.setAttribute('aria-expanded', 'false');
        });
      }
      document.querySelectorAll('.drv-search.is-open').forEach((searchWrap) => {
        if (typeof searchWrap._closeSearchOverlay === 'function') {
          searchWrap._closeSearchOverlay();
        }
      });
    }
  });

  /* ==========================================================================
     2-Level Vietnam Address Cascading Select (34 Tỉnh/TP -> Phường/Xã)
     ========================================================================== */
  function initVnAddressSelects() {
    const provinceSelects = document.querySelectorAll('[data-drv-province]');
    provinceSelects.forEach((provSelect) => {
      if (provSelect._drvVnBound) return;
      provSelect._drvVnBound = true;
      const wardSelector = provSelect.dataset.drvWardTarget || '[data-drv-ward]';
      const form = provSelect.closest('form') || document;
      const wardSelect = form.querySelector(wardSelector);

      if (provSelect.options.length <= 1) {
        const defaultCode = provSelect.dataset.drvDefault || 'HCM';
        VN_PROVINCES.forEach((p) => {
          const opt = document.createElement('option');
          opt.value = p.code;
          opt.textContent = p.name;
          if (p.code === defaultCode) opt.selected = true;
          provSelect.appendChild(opt);
        });
      }

      const updateShippingZoneHint = (code) => {
        const found = VN_PROVINCES.find((p) => p.code === code);
        const provName = found ? found.name : 'TP. Hồ Chí Minh';
        document.querySelectorAll('[data-checkout-province-name]').forEach((el) => {
          el.textContent = provName;
        });

        let zoneText = 'Xe tải nhẹ 500kg - 1 tấn · Giao trong 24h từ Kho TP.HCM';
        if (code === 'HCM') {
          zoneText = 'Nội thành TP.HCM · Xe tải nhẹ 500kg - 1 tấn giao tận chân công trình (24h)';
        } else if (['DNA', 'BDG', 'VLO', 'DTH', 'LDG'].includes(code)) {
          zoneText = `Tuyến lân cận (${provName}) · Xe tải chuyến hoặc ghép chuyến (24 - 48h)`;
        } else if (found) {
          zoneText = `Tuyến liên tỉnh (${provName}) · Điều phối xe tải / chành xe uy tín (2 - 4 ngày)`;
        }
        document.querySelectorAll('[data-checkout-shipping-zone]').forEach((el) => {
          el.textContent = zoneText;
        });
      };

      const populateWards = (code) => {
        if (!wardSelect) return;
        const found = VN_PROVINCES.find((p) => p.code === code);
        wardSelect.innerHTML = '<option value="">-- Chọn Phường / Xã --</option>';
        if (!found) {
          wardSelect.disabled = true;
          return;
        }
        wardSelect.disabled = false;
        found.wards.forEach((wardName, idx) => {
          const opt = document.createElement('option');
          opt.value = wardName;
          opt.textContent = wardName;
          if (idx === 0 && wardSelect.dataset.drvAutoselect === 'true') {
            opt.selected = true;
          }
          wardSelect.appendChild(opt);
        });
        updateShippingZoneHint(code);
      };

      if (provSelect.value) {
        populateWards(provSelect.value);
      }

      provSelect.addEventListener('change', () => {
        populateWards(provSelect.value);
        if (wardSelect) {
          wardSelect.removeAttribute('aria-invalid');
          const errEl = wardSelect.closest('.drv-field')?.querySelector('.drv-field__error');
          if (errEl) errEl.hidden = true;
        }
      });
    });
  }

  /* ==========================================================================
     Sticky Header, Sticky Purchase Bar & Blog TOC Scrollspy
     ========================================================================== */
  function initScrollObservers() {
    const signal = mountScope('scroll-observers');
    /** @param {IntersectionObserver|ResizeObserver} observer */
    const disconnectOnTeardown = (observer) => signal.addEventListener('abort', () => observer.disconnect());
    const header = document.querySelector('.drv-header');
    if (header) {
      const syncHeaderHeight = () => {
        const h = header.offsetHeight;
        if (h > 0) {
          document.documentElement.style.setProperty('--header-h-current', `${h}px`);
        }
      };
      syncHeaderHeight();
      if ('ResizeObserver' in window) {
        const headerResize = new ResizeObserver(syncHeaderHeight);
        headerResize.observe(header);
        disconnectOnTeardown(headerResize);
      } else {
        window.addEventListener('resize', syncHeaderHeight, { passive: true, signal });
      }

      let lastY = window.scrollY;
      window.addEventListener(
        'scroll',
        () => {
          const y = window.scrollY;
          if (y > 120 && y > lastY) {
            header.classList.add('is-compact');
          } else if (y < 60 || y < lastY - 15) {
            header.classList.remove('is-compact');
          }
          lastY = y;
        },
        { passive: true, signal }
      );
    }

    // Sticky Purchase Bar on Product Single page
    const buyAnchor = document.getElementById('drv-buy-actions');
    const stickyBar = document.getElementById('drv-sticky-purchase');
    if (buyAnchor && stickyBar && 'IntersectionObserver' in window) {
      const obs = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            const shouldShow = !entry.isIntersecting && entry.boundingClientRect.top < 0;
            stickyBar.classList.toggle('is-visible', shouldShow);
          });
        },
        { threshold: 0 }
      );
      obs.observe(buyAnchor);
      disconnectOnTeardown(obs);
    }

    // Blog Single Table of Contents Scrollspy
    const tocLinks = document.querySelectorAll('.drv-toc__link[href^="#"]');
    if (tocLinks.length > 0 && 'IntersectionObserver' in window) {
      const headings = Array.from(tocLinks)
        .map((link) => document.querySelector(link.getAttribute('href')))
        .filter(Boolean);

      const spy = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              const id = `#${entry.target.id}`;
              tocLinks.forEach((link) => {
                link.classList.toggle('is-active', link.getAttribute('href') === id);
              });
            }
          });
        },
        { rootMargin: '-15% 0px -70% 0px' }
      );

      headings.forEach((h) => spy.observe(h));
      disconnectOnTeardown(spy);
    }
  }

  /* ==========================================================================
     Accessible Form Validation (Checkout & Project Quote Forms)
     ========================================================================== */
  function validateSingleField(field) {
    if (field.disabled || field.closest('[hidden]')) {
      field.removeAttribute('aria-invalid');
      const errEl = field.closest('.drv-field')?.querySelector('.drv-field__error');
      if (errEl) errEl.hidden = true;
      return true;
    }

    const isCheckbox = field.type === 'checkbox';
    const val = isCheckbox ? (field.checked ? '1' : '') : field.value.trim();
    const isRequired = field.hasAttribute('required');
    let errorMsg = '';

    if (isRequired && !val) {
      errorMsg =
        field.dataset.errorEmpty ||
        (isCheckbox
          ? 'Vui lòng đồng ý với điều khoản để hoàn tất đặt hàng.'
          : 'Vui lòng nhập thông tin bắt buộc này.');
    } else if (val && (field.type === 'tel' || field.name.includes('phone'))) {
      const vnPhoneRegex = /^(0|\+84)(3|5|7|8|9)\d{8}$/;
      const cleanPhone = val.replace(/[\s.-]/g, '');
      if (!vnPhoneRegex.test(cleanPhone)) {
        errorMsg =
          field.dataset.errorPattern ||
          'Số điện thoại không hợp lệ (gồm 10 chữ số chuẩn VN, ví dụ: 0909123456).';
      }
    } else if (val && field.type === 'email') {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(val)) {
        errorMsg =
          field.dataset.errorPattern ||
          'Địa chỉ email không đúng định dạng (ví dụ: ten@congty.vn).';
      }
    } else if (val && field.name === 'vat_tax_id') {
      const taxRegex = /^\d{10}(-\d{3})?$/;
      if (!taxRegex.test(val.replace(/\s+/g, ''))) {
        errorMsg = 'Mã số thuế gồm 10 hoặc 13 chữ số (ví dụ: 0318688999).';
      }
    }

    const errEl = field.closest('.drv-field')?.querySelector('.drv-field__error');
    if (errorMsg) {
      field.setAttribute('aria-invalid', 'true');
      if (errEl) {
        errEl.replaceChildren(
          createIcon('ph-fill ph-warning-circle drv-icon--xs'),
          document.createTextNode(' '),
          Object.assign(document.createElement('span'), { textContent: errorMsg })
        );
        errEl.hidden = false;
      }
      return false;
    }

    field.removeAttribute('aria-invalid');
    if (errEl) errEl.hidden = true;
    return true;
  }

  function initFormValidation() {
    document.querySelectorAll('form[data-drv-validate]').forEach((form) => {
      if (form._drvValidateBound) return;
      form._drvValidateBound = true;

      // Inline validation after user interaction (blur / change) per form-cro
      form.addEventListener(
        'blur',
        (event) => {
          const field = event.target.closest('input, select, textarea');
          if (!field) return;
          if (field.value.trim() !== '' || field.getAttribute('aria-invalid') === 'true') {
            validateSingleField(field);
          }
        },
        true
      );

      form.addEventListener('input', (event) => {
        const field = event.target.closest('input, select, textarea');
        if (field && field.getAttribute('aria-invalid') === 'true') {
          validateSingleField(field);
        }
      });

      form.addEventListener('change', (event) => {
        const field = event.target.closest('input, select, textarea');
        if (field && (field.type === 'checkbox' || field.tagName === 'SELECT')) {
          validateSingleField(field);
        }
      });

      form.addEventListener('submit', (event) => {
        event.preventDefault();
        let firstInvalid = null;

        form.querySelectorAll('input, select, textarea').forEach((field) => {
          const ok = validateSingleField(field);
          if (!ok && !firstInvalid) {
            firstInvalid = field;
          }
        });

        if (firstInvalid) {
          firstInvalid.focus();
          toast('Vui lòng kiểm tra lại các trường thông tin được đánh dấu đỏ.');
          return;
        }

        const submitBtns = [
          ...form.querySelectorAll('[type="submit"]'),
          ...(form.id ? document.querySelectorAll(`[type="submit"][form="${form.id}"]`) : [])
        ];
        submitBtns.forEach((submitBtn) => {
          submitBtn.disabled = true;
          submitBtn.setAttribute('aria-busy', 'true');
          submitBtn.innerHTML =
            '<i class="ph ph-spinner drv-icon" aria-hidden="true"></i> <span>ĐANG XỬ LÝ...</span>';
        });

        const successRedirect = form.dataset.drvRedirect;
        const successMsg =
          form.dataset.drvSuccessMsg ||
          'Đã tiếp nhận yêu cầu! Nhân viên kỹ thuật Dravit sẽ liên hệ trong 15 phút.';
        toast(successMsg);

        const parentDialog = form.closest('dialog');
        if (parentDialog) closeDialog(parentDialog);

        if (successRedirect) {
          window.setTimeout(() => {
            window.location.href = successRedirect;
          }, 700);
        }
      });
    });
  }

  /* ==========================================================================
     Checkout 2-Column Page Controller (05-checkout.html)
     ========================================================================== */
  function initCheckoutController() {
    const checkoutForm = document.getElementById('drv-checkout-form');
    if (!checkoutForm) return;

    const vatToggle = document.getElementById('drv-vat-toggle');
    const vatFieldsWrap = document.getElementById('drv-vat-fields');
    const vatCompany = document.getElementById('vat_company_name');
    const vatTaxId = document.getElementById('vat_tax_id');
    const vatAddress = document.getElementById('vat_company_address');

    const syncVatState = () => {
      if (!vatToggle || !vatFieldsWrap) return;
      const isChecked = vatToggle.checked;
      vatFieldsWrap.hidden = !isChecked;
      vatToggle.setAttribute('aria-expanded', String(isChecked));
      [vatCompany, vatTaxId, vatAddress].forEach((input) => {
        if (!input) return;
        if (isChecked) {
          input.setAttribute('required', 'required');
        } else {
          input.removeAttribute('required');
          input.removeAttribute('aria-invalid');
          const errEl = input.closest('.drv-field')?.querySelector('.drv-field__error');
          if (errEl) errEl.hidden = true;
        }
      });
    };

    if (vatToggle) {
      vatToggle.addEventListener('change', syncVatState);
      syncVatState();
    }

    // Sync selected Payment Method (VietQR vs COD) into right summary column
    const payRadios = checkoutForm.querySelectorAll('input[name="payment_method"]');
    const syncPaymentSummary = () => {
      const checked = checkoutForm.querySelector('input[name="payment_method"]:checked');
      if (!checked) return;
      const isVietQr = checked.value === 'bacs';
      document.querySelectorAll('[data-checkout-pay-summary]').forEach((el) => {
        el.textContent = isVietQr
          ? 'Chuyển khoản VietQR (Sau khi chốt đơn)'
          : 'Tiền mặt khi nhận hàng (COD / Đồng kiểm)';
      });
      document.querySelectorAll('[data-checkout-submit-sub]').forEach((el) => {
        el.textContent = isVietQr
          ? 'Chưa cần chuyển khoản ngay · Chuyên viên tư vấn sẽ liên hệ chốt đơn và gửi mã VietQR.'
          : 'Chuyên viên tư vấn sẽ liên hệ chốt đơn và xác nhận lịch giao hàng tận công trình.';
      });
    };

    payRadios.forEach((r) => r.addEventListener('change', syncPaymentSummary));
    syncPaymentSummary();

    // Prototype Demo Bar Actions (Fill Homeowner, Fill Contractor+VAT, Trigger Error, Reset)
    onDocumentClick((event) => {
      const btn = event.target.closest('[data-drv-action^="checkout-demo-"]');
      if (!btn) return;
      event.preventDefault();

      const action = btn.dataset.drvAction;
      const nameInput = document.getElementById('billing_first_name');
      const phoneInput = document.getElementById('billing_phone');
      const emailInput = document.getElementById('billing_email');
      const provSelect = document.getElementById('billing_state');
      const wardSelect = document.getElementById('billing_city');
      const addrInput = document.getElementById('billing_address_1');
      const noteInput = document.getElementById('order_comments');
      const termsCheck = document.getElementById('checkout_terms');

      const setActiveDemoChip = () => {
        document.querySelectorAll('[data-drv-action^="checkout-demo-"]').forEach((b) => {
          const active = b === btn;
          b.classList.toggle('is-active', active);
          b.setAttribute('aria-pressed', String(active));
        });
      };

      if (action === 'checkout-demo-fill') {
        const preset = btn.dataset.drvPreset || 'homeowner';
        setActiveDemoChip();

        if (preset === 'contractor') {
          if (nameInput) nameInput.value = 'KTS. Trần Minh Hoàng (Chỉ huy trưởng)';
          if (phoneInput) phoneInput.value = '0918668899';
          if (emailInput) emailInput.value = 'hoang.tran@kientrucnhaviet.vn';
          if (provSelect) {
            provSelect.value = 'DNA';
            provSelect.dispatchEvent(new Event('change'));
          }
          if (wardSelect && wardSelect.options.length > 3) {
            wardSelect.selectedIndex = 4; // Phường Long Thành
          }
          if (addrInput) addrInput.value = 'Lô B12 Khu dân cư Long Thành, Đường Lê Duẩn';
          if (noteInput) {
            noteInput.value =
              'Đường lớn xe tải 5 tấn vào tận nơi; Giao giờ hành chính (8:00 - 17:00); Gọi trước 60 phút';
          }
          if (vatToggle) {
            vatToggle.checked = true;
            syncVatState();
          }
          if (vatCompany) vatCompany.value = 'CÔNG TY TNHH THIẾT KẾ & XÂY DỰNG NHÀ VIỆT';
          if (vatTaxId) vatTaxId.value = '0315889922';
          if (vatAddress) vatAddress.value = '88 Điện Biên Phủ, Phường Sài Gòn, TP. Hồ Chí Minh';
          const vatEmail = document.getElementById('vat_email');
          if (vatEmail) vatEmail.value = 'ketoan@kientrucnhaviet.vn';
          toast('Đã điền dữ liệu mẫu: Nhà thầu công trình Đồng Nai (Kèm xuất hóa đơn VAT).');
        } else {
          if (nameInput) nameInput.value = 'Nguyễn Thanh Tùng';
          if (phoneInput) phoneInput.value = '0909385880';
          if (emailInput) emailInput.value = 'tung.nguyen@gmail.com';
          if (provSelect) {
            provSelect.value = 'HCM';
            provSelect.dispatchEvent(new Event('change'));
          }
          if (wardSelect && wardSelect.options.length > 4) {
            wardSelect.selectedIndex = 4; // Phường Thảo Điền
          }
          if (addrInput) addrInput.value = 'Số 24 Đường số 10, Khu biệt thự Thảo Điền';
          if (noteInput) {
            noteInput.value = 'Hẻm xe tải nhỏ (< 1,5 tấn); Gọi trước 60 phút';
          }
          if (vatToggle) {
            vatToggle.checked = false;
            syncVatState();
          }
          toast('Đã điền dữ liệu mẫu: Chủ nhà tại TP. Hồ Chí Minh.');
        }

        if (termsCheck) termsCheck.checked = true;
        checkoutForm.querySelectorAll('input, select, textarea').forEach((f) => validateSingleField(f));
      } else if (action === 'checkout-demo-error') {
        setActiveDemoChip();
        if (nameInput) nameInput.value = '';
        if (phoneInput) phoneInput.value = '012345';
        if (addrInput) addrInput.value = '';
        checkoutForm.querySelectorAll('[required]').forEach((f) => validateSingleField(f));
        if (nameInput) nameInput.focus();
        toast('Đang hiển thị trạng thái kiểm tra lỗi biểu mẫu (Inline Validation WCAG 2.2 AA).');
      } else if (action === 'checkout-demo-reset') {
        setActiveDemoChip();
        checkoutForm.reset();
        if (provSelect) {
          provSelect.value = 'HCM';
          provSelect.dispatchEvent(new Event('change'));
        }
        if (vatToggle) {
          vatToggle.checked = false;
          syncVatState();
        }
        document.querySelectorAll('.drv-note-chip.is-active').forEach((c) => {
          c.classList.remove('is-active');
          c.setAttribute('aria-pressed', 'false');
        });
        checkoutForm.querySelectorAll('input, select, textarea').forEach((f) => {
          f.removeAttribute('aria-invalid');
          const errEl = f.closest('.drv-field')?.querySelector('.drv-field__error');
          if (errEl) errEl.hidden = true;
        });
        syncPaymentSummary();
        toast('Đã xóa trắng biểu mẫu thanh toán.');
      }
    });
  }

  function initOrderReceivedController() {
    const orderPage = document.querySelector('[data-page="order-received"]');
    if (!orderPage) return;

    const setOrderDemoState = (state) => {
      const stateBadge = document.querySelector('[data-order-status-badge]');
      const heroDescEl = document.querySelector('[data-order-hero-desc]');
      const shipFeeEl = document.querySelector('[data-order-ship-fee]');
      const grandTotalEl = document.querySelector('[data-order-grand-total]');
      const qrWatermark = document.querySelector('[data-qr-watermark]');
      const qrImg = document.querySelector('[data-qr-image]');
      const bankAmountEl = document.querySelector('[data-bank-amount]');
      const bankAmountBtn = document.querySelector('[data-bank-amount-btn]');
      const timelineSteps = document.querySelectorAll('.drv-timeline__step');
      const stateButtons = document.querySelectorAll('[data-order-demo-state]');

      stateButtons.forEach((btn) => {
        const isActive = btn.dataset.orderDemoState === state;
        btn.classList.toggle('is-active', isActive);
        btn.setAttribute('aria-pressed', String(isActive));
      });

      if (state === 'awaiting-quote') {
        if (stateBadge) {
          stateBadge.className = 'drv-badge drv-badge--amber';
          stateBadge.textContent = 'Chờ chuyên viên liên hệ';
        }
        if (heroDescEl) {
          heroDescEl.textContent = 'Chuyên viên tư vấn Dravit sẽ liên hệ số 0912 345 678 trong 15 - 30 phút để xác nhận lô gạch (Shade V2) và chốt đơn hàng.';
        }
        if (shipFeeEl) {
          shipFeeEl.className = 'u-text-moss u-bold';
          shipFeeEl.textContent = 'Chuyên viên báo khi chốt đơn';
        }
        if (grandTotalEl) {
          grandTotalEl.textContent = '4.690.200đ';
        }
        if (qrWatermark) qrWatermark.hidden = false;
        if (bankAmountEl) bankAmountEl.textContent = 'Chờ chốt đơn';
        if (bankAmountBtn) {
          bankAmountBtn.disabled = true;
          bankAmountBtn.title = 'Chưa có số tiền chính xác';
        }

        if (timelineSteps[0]) timelineSteps[0].className = 'drv-timeline__step is-done';
        if (timelineSteps[1]) timelineSteps[1].className = 'drv-timeline__step is-active';
        if (timelineSteps[2]) timelineSteps[2].className = 'drv-timeline__step is-pending';
        if (timelineSteps[3]) timelineSteps[3].className = 'drv-timeline__step is-pending';

        toast('Mô phỏng: Đơn mới đặt — Chờ chuyên viên liên hệ chốt đơn hàng.');
      } else if (state === 'quote-confirmed') {
        if (stateBadge) {
          stateBadge.className = 'drv-badge drv-badge--moss';
          stateBadge.textContent = 'Đã chốt đơn · Chờ chuyển khoản VietQR';
        }
        if (heroDescEl) {
          heroDescEl.textContent = 'Đơn hàng đã được chốt cước vận chuyển (350.000đ). Quý khách vui lòng quét mã VietQR bên dưới để hoàn tất thanh toán.';
        }
        if (shipFeeEl) {
          shipFeeEl.className = 'u-text-navy u-bold u-tabular';
          shipFeeEl.textContent = '350.000đ (Xe 1,5 tấn tận nơi)';
        }
        if (grandTotalEl) {
          grandTotalEl.textContent = '5.040.200đ';
        }
        if (qrWatermark) qrWatermark.hidden = true;
        if (qrImg) {
          qrImg.src = 'https://img.vietqr.io/image/970422-0938258668-compact2.png?amount=5040200&addInfo=DRV202688&accountName=CONG%20TY%20TNHH%20DRAVIT%20VIET%20NAM';
        }
        if (bankAmountEl) bankAmountEl.textContent = '5.040.200đ';
        if (bankAmountBtn) {
          bankAmountBtn.disabled = false;
          bankAmountBtn.dataset.drvValue = '5040200';
          bankAmountBtn.title = 'Sao chép 5.040.200đ';
        }

        if (timelineSteps[0]) timelineSteps[0].className = 'drv-timeline__step is-done';
        if (timelineSteps[1]) timelineSteps[1].className = 'drv-timeline__step is-done';
        if (timelineSteps[2]) timelineSteps[2].className = 'drv-timeline__step is-active';
        if (timelineSteps[3]) timelineSteps[3].className = 'drv-timeline__step is-pending';

        toast('Mô phỏng: Đã chốt đơn & cước vận chuyển 350.000đ — Sẵn sàng quét VietQR.');
      }
    };

    onDocumentClick((event) => {
      const demoBtn = event.target.closest('[data-order-demo-state]');
      if (demoBtn) {
        event.preventDefault();
        setOrderDemoState(demoBtn.dataset.orderDemoState);
      }
    });
  }

  function initHeaderNavHover() {
    const megaBtn = document.querySelector('[data-drv-action="mega-toggle"]');
    const megaMenu = document.getElementById('drv-mega-menu');
    if (!megaBtn || !megaMenu) return;
    if (megaBtn._drvHoverBound) return;
    megaBtn._drvHoverBound = true;

    let timer = null;

    const openMega = () => {
      clearTimeout(timer);
      const wasHidden = megaMenu.hidden;
      megaMenu.hidden = false;
      megaBtn.setAttribute('aria-expanded', 'true');
      if (wasHidden) {
        const matrix = megaMenu.querySelector('.drv-mega__matrix');
        if (matrix) motion.staggerReveal(matrix);
      }
    };

    const closeMega = () => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        megaMenu.hidden = true;
        megaBtn.setAttribute('aria-expanded', 'false');
      }, 180);
    };

    megaBtn.addEventListener('mouseenter', () => {
      if (window.matchMedia('(min-width: 1024px)').matches) {
        openMega();
      }
    });

    megaBtn.addEventListener('mouseleave', () => {
      if (window.matchMedia('(min-width: 1024px)').matches) {
        closeMega();
      }
    });

    megaMenu.addEventListener('mouseenter', () => {
      if (window.matchMedia('(min-width: 1024px)').matches) {
        openMega();
      }
    });

    megaMenu.addEventListener('mouseleave', () => {
      if (window.matchMedia('(min-width: 1024px)').matches) {
        closeMega();
      }
    });
  }

  function initMegaPreview() {
    const megaMenu = document.getElementById('drv-mega-menu');
    if (!megaMenu) return;

    const previewBadge = megaMenu.querySelector('#drv-mega-preview-badge');
    const previewImg = megaMenu.querySelector('#drv-mega-preview-img');
    const previewTitle = megaMenu.querySelector('#drv-mega-preview-title');
    const previewDesc = megaMenu.querySelector('#drv-mega-preview-desc');
    const previewPrice = megaMenu.querySelector('#drv-mega-preview-price');
    const previewBtn = megaMenu.querySelector('#drv-mega-preview-btn');

    if (!previewImg || !previewTitle) return;
    if (megaMenu._drvPreviewBound) return;
    megaMenu._drvPreviewBound = true;

    let debounceTimer = null;

    const updatePreview = (data) => {
      previewImg.classList.add('is-updating');
      window.setTimeout(() => {
        if (previewBadge && data.badge) previewBadge.textContent = data.badge;
        if (previewImg && data.img) {
          previewImg.src = data.img;
          if (data.title) previewImg.alt = data.title;
        }
        if (previewTitle && data.title) previewTitle.textContent = data.title;
        if (previewDesc && data.desc) previewDesc.textContent = data.desc;
        if (previewPrice && data.price) previewPrice.textContent = data.price;
        if (previewBtn && data.link) previewBtn.href = data.link;

        previewImg.classList.remove('is-updating');
      }, 90);
    };

    const previewLinks = megaMenu.querySelectorAll('[data-drv-preview-img]');
    previewLinks.forEach((link) => {
      link.addEventListener('mouseenter', () => {
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(() => {
          previewLinks.forEach((l) => l.classList.remove('is-active'));
          link.classList.add('is-active');
          updatePreview({
            badge: link.dataset.drvPreviewBadge,
            img: link.dataset.drvPreviewImg,
            title: link.dataset.drvPreviewTitle,
            desc: link.dataset.drvPreviewDesc,
            price: link.dataset.drvPreviewPrice,
            link: link.getAttribute('href'),
          });
        }, 50);
      });
    });
  }

  /* ==========================================================================
     Motion Controller (Option A+B: CSS GPU + Anime.js v3.2.2 Stagger & Odometer)
     ========================================================================== */
  const motion = {
    prefersReducedMotion() {
      return (
        document.documentElement.dataset.drvReduceMotion === 'true' ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
      );
    },

    /**
     * Single-element architectural entrance reveal using Anime.js (compositor-only)
     * @param {Element|string} elOrSelector
     * @param {Object} [opts]
     */
    revealElement(elOrSelector, opts = {}) {
      const el =
        typeof elOrSelector === 'string'
          ? document.querySelector(elOrSelector)
          : elOrSelector;
      if (!el) return;

      const clearPre = () => {
        el.classList.remove('is-drv-pre-reveal', 'is-drv-pre-reveal--scale');
        el.style.opacity = '';
        el.style.transform = '';
      };

      if (this.prefersReducedMotion() || typeof window.anime !== 'function') {
        clearPre();
        return;
      }

      const mode = opts.type || el.dataset.drvReveal || 'fade-up';
      const delay = opts.delay ?? (Number(el.dataset.drvDelay) || 0);
      const duration = opts.duration ?? (mode === 'scale-up' ? 500 : 460);

      el.classList.remove('is-drv-pre-reveal', 'is-drv-pre-reveal--scale');
      window.anime.remove(el);

      const animProps = {
        targets: el,
        opacity: [0, 1],
        delay,
        duration,
        easing: 'easeOutExpo',
        complete: clearPre,
      };

      if (mode === 'scale-up') {
        animProps.translateY = [14, 0];
        animProps.scale = [0.985, 1];
      } else if (mode === 'slide-right') {
        animProps.translateX = [-16, 0];
      } else if (mode === 'slide-left') {
        animProps.translateX = [16, 0];
      } else {
        animProps.translateY = [16, 0];
      }

      window.anime(animProps);
    },

    /**
     * Staggered reveal for grid items using Anime.js (falls back to instant visibility)
     * @param {Element|string} containerOrSelector
     * @param {Object} [opts]
     */
    staggerReveal(containerOrSelector, opts = {}) {
      const container =
        typeof containerOrSelector === 'string'
          ? document.querySelector(containerOrSelector)
          : containerOrSelector;
      if (!container) return;

      const items = Array.from(container.children).filter((el) => !el.hidden);
      if (items.length === 0) return;

      const clearItems = () => {
        items.forEach((el) => {
          el.classList.remove('is-drv-pre-reveal', 'is-drv-pre-reveal--scale');
          el.style.opacity = '';
          el.style.transform = '';
        });
      };

      if (this.prefersReducedMotion() || typeof window.anime !== 'function') {
        clearItems();
        return;
      }

      const step = opts.step ?? (Number(container.dataset.drvStagger) || 55);
      const startDelay = opts.delay ?? (Number(container.dataset.drvDelay) || 0);
      const distance = opts.distance ?? 14;
      const duration = opts.duration ?? 430;

      items.forEach((el) => {
        el.classList.remove('is-drv-pre-reveal', 'is-drv-pre-reveal--scale');
      });

      window.anime.remove(items);
      window.anime({
        targets: items,
        opacity: [0, 1],
        translateY: [distance, 0],
        delay: window.anime.stagger(step, { start: startDelay }),
        duration,
        easing: 'easeOutExpo',
        complete: clearItems,
      });
    },

    /**
     * Orchestrated above-the-fold Hero entrance choreography (#home-hero)
     * @param {Element|string} [heroSelector='#home-hero']
     */
    heroEntrance(heroSelector = '#home-hero') {
      const hero =
        typeof heroSelector === 'string'
          ? document.querySelector(heroSelector)
          : heroSelector;
      if (!hero || hero.dataset.drvHeroEntered === 'true') return;
      hero.dataset.drvHeroEntered = 'true';

      if (this.prefersReducedMotion() || typeof window.anime !== 'function') return;

      const badge = hero.querySelector('.drv-ai-badge');
      const lines = Array.from(hero.querySelectorAll('.drv-hero-headline > .l-block'));
      const subhead = hero.querySelector('.drv-hero-subhead');
      const specs = Array.from(hero.querySelectorAll('#specCardsContainer .drv-spec-card'));
      const aiCard = hero.querySelector('#aiPromptCard');
      const chips = Array.from(hero.querySelectorAll('#aiPromptCard .drv-ai-prompt-chip'));

      const allTargets = [badge, ...lines, subhead, ...specs, aiCard, ...chips].filter(Boolean);
      if (!allTargets.length) return;

      allTargets.forEach((el) => {
        el.style.opacity = '0';
      });

      const tl = window.anime.timeline({
        easing: 'easeOutExpo',
        complete() {
          allTargets.forEach((el) => {
            el.style.opacity = '';
            el.style.transform = '';
          });
        },
      });

      if (badge) {
        tl.add({ targets: badge, opacity: [0, 1], translateY: [12, 0], duration: 380 }, 0);
      }
      if (lines.length) {
        tl.add(
          {
            targets: lines,
            opacity: [0, 1],
            translateY: [22, 0],
            delay: window.anime.stagger(75),
            duration: 500,
          },
          60
        );
      }
      if (subhead) {
        tl.add({ targets: subhead, opacity: [0, 1], translateY: [14, 0], duration: 420 }, 210);
      }
      if (aiCard) {
        tl.add(
          {
            targets: aiCard,
            opacity: [0, 1],
            translateY: [20, 0],
            scale: [0.985, 1],
            duration: 520,
          },
          150
        );
      }
      if (specs.length) {
        tl.add(
          {
            targets: specs,
            opacity: [0, 1],
            translateY: [16, 0],
            delay: window.anime.stagger(65),
            duration: 440,
          },
          290
        );
      }
      if (chips.length) {
        tl.add(
          {
            targets: chips,
            opacity: [0, 1],
            translateY: [8, 0],
            delay: window.anime.stagger(45),
            duration: 340,
          },
          360
        );
      }
    },

    /**
     * Smooth number odometer counter using Anime.js
     * @param {Element} el
     * @param {number} fromVal
     * @param {number} toVal
     * @param {Function} formatter
     * @param {number} [duration=320]
     */
    animateNumber(el, fromVal, toVal, formatter, duration = 320) {
      if (!el) return;
      const start = Number(fromVal) || 0;
      const end = Number(toVal) || 0;

      if (
        this.prefersReducedMotion() ||
        typeof window.anime !== 'function' ||
        Math.abs(end - start) < 0.005
      ) {
        el.textContent = formatter(end);
        return;
      }

      if (el._drvAnimObj) {
        window.anime.remove(el._drvAnimObj);
      }

      const state = { val: start };
      el._drvAnimObj = state;

      window.anime({
        targets: state,
        val: end,
        duration,
        easing: 'easeOutQuad',
        update() {
          el.textContent = formatter(state.val);
        },
        complete() {
          el.textContent = formatter(end);
        },
      });
    },

    /**
     * Spring physics bounce on cart buttons/badges when adding items
     */
    bounceCartBadge() {
      if (this.prefersReducedMotion() || typeof window.anime !== 'function') return;
      const targets = document.querySelectorAll('[data-drv-cart-count], #drv-demo-cart-badge');
      if (!targets.length) return;

      window.anime.remove(targets);
      window.anime({
        targets,
        scale: [1, 1.28, 1],
        duration: 480,
        easing: 'spring(1, 80, 10, 0)',
      });
    },

    /**
     * Subtle highlight pulse on calculator result box
     * @param {Element} el
     */
    pulseElement(el) {
      if (!el || this.prefersReducedMotion()) return;
      el.classList.add('is-pulsing');
      window.clearTimeout(el._drvPulseTimer);
      el._drvPulseTimer = window.setTimeout(() => {
        el.classList.remove('is-pulsing');
      }, 360);
    },
  };

  function initScrollStagger() {
    motion.heroEntrance('#home-hero');

    if (
      motion.prefersReducedMotion() ||
      typeof window.anime !== 'function' ||
      !('IntersectionObserver' in window)
    ) {
      return;
    }

    document.documentElement.dataset.drvMotionReady = 'true';

    const reveals = Array.from(document.querySelectorAll('[data-drv-reveal]')).filter(
      (el) => !el.closest('[hidden]')
    );
    const grids = Array.from(document.querySelectorAll('[data-drv-stagger]')).filter(
      (el) => !el.closest('[hidden]')
    );

    if (reveals.length) {
      reveals.forEach((el) => {
        el.classList.add(
          el.dataset.drvReveal === 'scale-up' ? 'is-drv-pre-reveal--scale' : 'is-drv-pre-reveal'
        );
      });

      const revealObs = new IntersectionObserver(
        (entries, observer) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              motion.revealElement(entry.target);
              observer.unobserve(entry.target);
            }
          });
        },
        { threshold: 0.08, rootMargin: '0px 0px -32px 0px' }
      );

      reveals.forEach((el) => revealObs.observe(el));
    }

    if (grids.length) {
      grids.forEach((grid) => {
        Array.from(grid.children).forEach((child) => {
          if (!child.hidden) child.classList.add('is-drv-pre-reveal');
        });
      });

      const staggerObs = new IntersectionObserver(
        (entries, observer) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              motion.staggerReveal(entry.target);
              observer.unobserve(entry.target);
            }
          });
        },
        { threshold: 0.08, rootMargin: '0px 0px -32px 0px' }
      );

      grids.forEach((grid) => staggerObs.observe(grid));
    }
  }

  /* ==========================================================================
     Global Header & Footer Shell Components (<drv-site-header>, <drv-site-footer>)
     Source of truth: docs/layout/partials/header.html & docs/layout/partials/footer.html
     Supports both file:// (synchronous fallback) and http(s):// (partial sync)
     ========================================================================== */
  /* Header/footer markup comes from partials/*.html, bundled into assets/js/shell-partials.js by
     tools/build-shell.mjs (window.__DRV_SHELL__). fetch() is only a fallback when that bundle is absent. */


  function syncActiveProtoLink(root) {
    const rawPage =
      root.dataset?.activePage ||
      window.location.pathname.split('/').pop() ||
      '01-home.html';
    const currentFile = rawPage === '' || rawPage === 'index.html' ? '01-home.html' : rawPage;

    root.querySelectorAll('.drv-proto-bar__link').forEach((link) => {
      const href = link.getAttribute('href') || '';
      if (href === currentFile) {
        link.setAttribute('aria-current', 'page');
      } else {
        link.removeAttribute('aria-current');
      }
    });
  }

  /**
   * Render a shared shell partial into a host element.
   * Prefers the bundled markup (sync, no layout shift); falls back to fetch() on http(s).
   * @param {HTMLElement} host
   * @param {'header'|'footer'} key
   * @param {string} defaultUrl
   * @param {() => void} onFetched called only when the markup arrived asynchronously
   */
  function renderShell(host, key, defaultUrl, onFetched) {
    const bundled = window.__DRV_SHELL__ && window.__DRV_SHELL__[key];
    if (bundled) {
      host.innerHTML = bundled;
      return;
    }
    if (!window.location.protocol.startsWith('http')) return;
    fetch(host.dataset.partial || defaultUrl)
      .then((res) => (res.ok ? res.text() : ''))
      .then((html) => {
        if (!html || !html.trim()) return;
        host.innerHTML = html;
        onFetched();
      })
      .catch((err) => reportError('shell', err, { partial: key }));
  }

  class DrvSiteHeader extends HTMLElement {
    connectedCallback() {
      if (this._initialized) return;
      this._initialized = true;

      renderShell(this, 'header', 'partials/header.html', () => {
        syncActiveProtoLink(this);
        initHeaderNavHover();
        initMegaPreview();
        initScrollObservers();
        window.DravitCatalog?.initInstantSearch?.();
      });
      syncActiveProtoLink(this);
    }
  }

  class DrvSiteFooter extends HTMLElement {
    connectedCallback() {
      if (this._initialized) return;
      this._initialized = true;

      renderShell(this, 'footer', 'partials/footer.html', () => {
        initVnAddressSelects();
        initFormValidation();
      });
    }
  }

  if (!customElements.get('drv-site-header')) {
    customElements.define('drv-site-header', DrvSiteHeader);
  }
  if (!customElements.get('drv-site-footer')) {
    customElements.define('drv-site-footer', DrvSiteFooter);
  }

  document.addEventListener('DOMContentLoaded', () => {
    initVnAddressSelects();
    initScrollObservers();
    initFormValidation();
    initCheckoutController();
    initOrderReceivedController();
    initHeaderNavHover();
    initMegaPreview();
    initScrollStagger();

    // Auto-load AI Tile Finder & Quote Panel controller across all pages
    if (!document.querySelector('script[src*="ai-finder.js"]')) {
      const aiScript = document.createElement('script');
      aiScript.src = 'assets/js/ai-finder.js?v=20261111m';
      aiScript.defer = true;
      document.body.appendChild(aiScript);
    }

    // PDP Spec Details toggle text ("Xem tất cả" / "Thu gọn")
    document.querySelectorAll('.drv-pdp-spec-more').forEach((details) => {
      const textSpan = details.querySelector('.drv-pdp-spec-more__text');
      if (!textSpan) return;
      details.addEventListener('toggle', () => {
        textSpan.textContent = details.open ? 'Thu gọn thông số' : 'Xem tất cả';
      });
    });

    // Keyboard ArrowLeft / ArrowRight navigation inside open Lightbox Dialog
    onDocumentKeydown((event) => {
      if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
      const dialog = document.getElementById('drv-lightbox-dialog');
      if (!dialog || !dialog.open) return;
      const btn = dialog.querySelector(
        event.key === 'ArrowLeft'
          ? '[data-drv-action="lightbox-nav"][data-drv-dir="prev"]'
          : '[data-drv-action="lightbox-nav"][data-drv-dir="next"]'
      );
      if (btn) btn.click();
    });
  });

  /* Glass shine sweep on hover for every content image (no per-template markup needed) */
  (() => {
    if (!window.matchMedia('(hover: hover)').matches) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const SKIP = '.drv-logo, .drv-footer__logo-img, .drv-qr-img, .drv-author-box__avatar, .drv-checkout-topbar__brand, [data-no-shine]';
    const MIN = 56;
    const eligible = (img) => !img.closest(SKIP) && img.offsetWidth >= MIN && img.offsetHeight >= MIN;

    const attach = (img) => {
      const parent = img.parentElement;
      if (!parent || getComputedStyle(parent).display === 'inline') return;
      if (getComputedStyle(parent).position === 'static') parent.style.position = 'relative';
      let beam = parent.querySelector(':scope > .drv-shine');
      if (!beam) {
        beam = document.createElement('span');
        beam.className = 'drv-shine';
        beam.setAttribute('aria-hidden', 'true');
        parent.appendChild(beam);
      }
      beam.style.cssText = `left:${img.offsetLeft}px;top:${img.offsetTop}px;width:${img.offsetWidth}px;height:${img.offsetHeight}px;border-radius:${getComputedStyle(img).borderRadius}`;
      parent.classList.add('is-shining');
      if (!parent._shineBound) {
        parent._shineBound = true;
        parent.addEventListener('mouseleave', () => parent.classList.remove('is-shining'));
      }
    };

    document.addEventListener('mouseover', (e) => {
      let node = e.target instanceof Element ? e.target : null;
      for (let depth = 0; node && depth < 6 && !/^(MAIN|SECTION|BODY)$/.test(node.tagName); depth += 1, node = node.parentElement) {
        const imgs = node.tagName === 'IMG' ? [node] : [...node.querySelectorAll('img')];
        if (!imgs.length) continue;
        if (imgs.length > 4) return;
        const seen = new Set();
        imgs.filter(eligible).forEach((img) => {
          if (seen.has(img.parentElement)) return;
          seen.add(img.parentElement);
          attach(img);
        });
        return;
      }
    });
  })();

  /* Horizontal strips (.drv-cat-silos-bar): prev/next arrows + edge fades when content overflows */
  (() => {
    const init = (bar) => {
      const host = bar.querySelector('.l-container');
      const row = bar.querySelector('.drv-pill-row');
      if (!host || !row || host.dataset.stripReady) return;
      host.dataset.stripReady = 'true';
      host.classList.add('drv-strip');
      const mk = (dir, icon, label) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = `drv-strip__btn drv-strip__btn--${dir}`;
        b.setAttribute('aria-label', label);
        b.innerHTML = `<i class="ph-bold ${icon}" aria-hidden="true"></i>`;
        b.addEventListener('click', () => {
          row.scrollBy({ left: (dir === 'next' ? 1 : -1) * Math.max(240, row.clientWidth * 0.7), behavior: 'smooth' });
        });
        host.appendChild(b);
        return b;
      };
      const prev = mk('prev', 'ph-caret-left', 'Xem các nhóm trước');
      const next = mk('next', 'ph-caret-right', 'Xem các nhóm tiếp theo');
      const update = () => {
        const max = row.scrollWidth - row.clientWidth;
        host.classList.toggle('has-prev', row.scrollLeft > 4);
        host.classList.toggle('has-next', row.scrollLeft < max - 4);
        prev.tabIndex = row.scrollLeft > 4 ? 0 : -1;
        next.tabIndex = row.scrollLeft < max - 4 ? 0 : -1;
      };
      row.addEventListener('scroll', update, { passive: true });
      window.addEventListener('resize', update, { passive: true });
      row.querySelectorAll('img').forEach((i) => i.addEventListener('load', update));
      const active = row.querySelector('.is-active');
      if (active) row.scrollLeft = Math.max(0, active.offsetLeft - 16);
      update();
    };
    const run = () => document.querySelectorAll('.drv-cat-silos-bar').forEach(init);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
    else run();
  })();

  window.Dravit = {
    reportError,
    formatVnd,
    formatDecimalVn,
    debounce,
    toast,
    copyToClipboard,
    openDialog,
    closeDialog,
    motion,
    VN_PROVINCES,
  };
})();

