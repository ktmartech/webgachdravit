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
  document.addEventListener('mousedown', (event) => {
    if (event.detail > 1 && event.target.closest('.drv-lightbox-stage, .drv-lightbox-thumbs')) {
      event.preventDefault();
    }
  });

  document.addEventListener('click', async (event) => {
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
          actionEl.innerHTML = `<i class="ph-bold ph-check" aria-hidden="true"></i> <span>${feedback}</span>`;
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
          // Replay the staggered tile reveal on the newly shown panel
          if (match) {
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
  document.addEventListener('keydown', (event) => {
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

  document.addEventListener('keydown', (event) => {
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
        new ResizeObserver(syncHeaderHeight).observe(header);
      } else {
        window.addEventListener('resize', syncHeaderHeight, { passive: true });
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
        { passive: true }
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
        errEl.innerHTML = `<i class="ph-fill ph-warning-circle drv-icon--xs" aria-hidden="true"></i> <span>${errorMsg}</span>`;
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
    document.addEventListener('click', (event) => {
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

    document.addEventListener('click', (event) => {
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
     * Staggered reveal for grid items using Anime.js (falls back to instant visibility)
     * @param {Element|string} containerOrSelector
     */
    staggerReveal(containerOrSelector) {
      const container =
        typeof containerOrSelector === 'string'
          ? document.querySelector(containerOrSelector)
          : containerOrSelector;
      if (!container) return;

      const items = Array.from(container.children);
      if (items.length === 0) return;

      if (this.prefersReducedMotion() || typeof window.anime !== 'function') {
        items.forEach((el) => {
          el.style.opacity = '';
          el.style.transform = '';
        });
        return;
      }

      window.anime.remove(items);
      window.anime({
        targets: items,
        opacity: [0, 1],
        translateY: [14, 0],
        delay: window.anime.stagger(55),
        duration: 420,
        easing: 'easeOutExpo',
        complete() {
          items.forEach((el) => {
            el.style.opacity = '';
            el.style.transform = '';
          });
        },
      });
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
    if (motion.prefersReducedMotion() || !('IntersectionObserver' in window)) return;
    const grids = document.querySelectorAll('[data-drv-stagger]');
    if (!grids.length) return;

    const obs = new IntersectionObserver(
      (entries, observer) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            motion.staggerReveal(entry.target);
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.12 }
    );

    grids.forEach((grid) => obs.observe(grid));
  }

  /* ==========================================================================
     Global Header & Footer Shell Components (<drv-site-header>, <drv-site-footer>)
     Source of truth: docs/layout/partials/header.html & docs/layout/partials/footer.html
     Supports both file:// (synchronous fallback) and http(s):// (partial sync)
     ========================================================================== */
  const DRV_HEADER_PARTIAL_FALLBACK = `<!-- 1. PROTOTYPE NAVIGATOR BAR (11 PAGES) -->
<nav class="drv-proto-bar" aria-label="Điều hướng bộ mẫu giao diện HTML">
  <div class="l-container drv-proto-bar__inner">
    <span class="drv-proto-bar__label">DRAVIT UI Prototype</span>
    <a href="00-ui-kit.html" class="drv-proto-bar__link">00. UI Kit</a>
    <a href="01-home.html" class="drv-proto-bar__link">01. Trang chủ</a>
    <a href="01.1-ai-finder.html" class="drv-proto-bar__link">01.1. Trợ lý AI</a>
    <a href="02-catalog-collection.html" class="drv-proto-bar__link">02. Danh mục Silo</a>
    <a href="03-product-single.html" class="drv-proto-bar__link">03. Chi tiết SP</a>
    <a href="04-cart.html" class="drv-proto-bar__link">04. Giỏ hàng</a>
    <a href="04.1-quote-cart.html" class="drv-proto-bar__link">04.1. DS Báo giá (BOQ)</a>
    <a href="05-checkout.html" class="drv-proto-bar__link">05. Thanh toán</a>
    <a href="06-order-received.html" class="drv-proto-bar__link">06. Hoàn tất đơn</a>
    <a href="07-blog-archive.html" class="drv-proto-bar__link">07. Cẩm nang</a>
    <a href="08-blog-single.html" class="drv-proto-bar__link">08. Bài viết GEO</a>
    <a href="09-showroom-about-quote.html" class="drv-proto-bar__link">09. Showroom &amp; Báo giá</a>
    <a href="10-policies-search-404.html" class="drv-proto-bar__link">10. Chính sách &amp; 404</a>
  </div>
</nav>

<!-- 2. GLOBAL HEADER 2-TIER + MEGA MENU "SILO MATRIX 4+1" + INSTANT SEARCH -->
<header class="drv-header">
  <!-- Tier 1: Brand, Instant Search, Showroom & Cart -->
  <div class="l-container drv-header__top">
    <button type="button" class="drv-btn drv-btn--ghost drv-btn--sm drv-btn--icon drv-header__menu-btn u-only-mobile" data-drv-action="dialog-open" data-drv-target="#drv-mobile-menu-drawer" aria-label="Mở danh mục điều hướng">
      <i class="ph ph-list drv-icon" aria-hidden="true"></i>
    </button>

    <a href="01-home.html" class="drv-logo" aria-label="DRAVIT - Trang chủ">
      <img src="assets/img/logo-navy.png" width="150" height="44" alt="DRAVIT" decoding="async">
    </a>

    <!-- Instant Search Box (Gõ thử: 80x80, marble, terrazzo, DRV...) -->
    <div class="drv-search" data-drv-search>
      <div class="drv-search-backdrop" data-drv-search-backdrop hidden></div>
      <form class="drv-search__form" action="10-policies-search-404.html" method="get" role="search">
        <i class="ph ph-magnifying-glass drv-search__icon" aria-hidden="true"></i>
        <label for="global-search-input" class="u-sr-only">Tìm mẫu gạch, mã SKU hoặc kích thước</label>
        <input id="global-search-input" name="s" type="search" class="drv-search__input" placeholder="Tìm mẫu gạch, mã SKU (DRV-8801), kích thước 80x80..." autocomplete="off">
        <button type="submit" class="drv-search__submit" aria-label="Tìm kiếm">
          <span>Tìm</span>
        </button>
      </form>

      <!-- Instant Search 2-Col Dropdown -->
      <div class="drv-search-drop" hidden aria-label="Gợi ý tìm kiếm tức thì">
        <div class="drv-search-drop__quick" data-search-quick>
          <span class="drv-search-drop__quick-label"><i class="ph ph-sparkle" aria-hidden="true"></i> Từ khóa phổ biến:</span>
          <button type="button" class="drv-search-tag" data-search-keyword="80x80">80x80</button>
          <button type="button" class="drv-search-tag" data-search-keyword="Marble">Vân đá Marble</button>
          <button type="button" class="drv-search-tag" data-search-keyword="Terrazzo">Terrazzo</button>
          <button type="button" class="drv-search-tag" data-search-keyword="60x120">Khổ lớn 60x120</button>
          <button type="button" class="drv-search-tag" data-search-keyword="R10">Chống trượt R10</button>
        </div>
        <div class="drv-search-drop__grid">
          <div class="drv-search-drop__col drv-search-drop__col--silo">
            <div class="drv-search-drop__col-head">
              <span class="drv-mega__heading" data-search-silo-heading>Danh mục &amp; Kích thước</span>
            </div>
            <ul class="drv-search-silo-list" data-search-silos></ul>
          </div>
          <div class="drv-search-drop__col">
            <div class="drv-search-drop__col-head">
              <span class="drv-mega__heading" data-search-prod-heading>Sản phẩm khớp mã SKU / tên</span>
              <a href="10-policies-search-404.html" class="drv-link u-text-xs" data-search-view-all>Xem tất cả kết quả</a>
            </div>
            <ul class="drv-search-prod-list" data-search-products></ul>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Actions -->
    <div class="drv-header__actions">
      <a href="tel:0909888777" class="drv-header__info-item u-tabular">
        <i class="ph ph-phone-call drv-icon u-text-moss" aria-hidden="true"></i>
        <span>0909.888.777</span>
      </a>
      <a href="01.1-ai-finder.html" class="drv-btn drv-btn--moss drv-btn--sm u-hide-mobile" aria-label="Trợ lý AI tư vấn chọn gạch">
        <i class="ph-fill ph-robot drv-icon drv-icon--sm" aria-hidden="true"></i>
        <span>AI tư vấn</span>
      </a>
      <button type="button" class="drv-btn drv-btn--ghost drv-btn--sm drv-btn--icon drv-header__search-toggle" data-drv-action="search-toggle" aria-label="Mở tìm kiếm" aria-expanded="false" aria-controls="global-search-input">
        <i class="ph ph-magnifying-glass drv-icon drv-icon--sm" aria-hidden="true"></i>
      </button>
      <a href="04.1-quote-cart.html" class="drv-btn drv-btn--outline drv-btn--sm drv-header__cart-btn" aria-label="Mở danh sách báo giá">
        <span class="drv-header__cart-icon-wrap">
          <i class="ph ph-clipboard-text drv-icon drv-icon--sm" aria-hidden="true"></i>
          <span class="drv-header__cart-badge u-tabular" data-quote-badge-count>0</span>
        </span>
        <span class="drv-header__cart-text">DS Báo giá (<span data-quote-badge-count class="u-tabular">0</span>)</span>
      </a>
    </div>
  </div>

  <!-- Tier 2: Silo Navigation & Mega Menu Trigger -->
  <div class="drv-header__nav-bar">
    <div class="l-container drv-nav">
      <ul class="drv-nav__list">
        <li class="drv-nav__item">
          <button type="button" class="drv-nav__link" data-drv-action="mega-toggle" aria-expanded="false" aria-controls="drv-mega-menu">
            <i class="ph ph-squares-four drv-icon drv-icon--sm" aria-hidden="true"></i>
            <span>Hệ thống 28 Danh mục Gạch</span>
            <i class="ph ph-caret-down drv-icon drv-icon--xs" aria-hidden="true"></i>
          </button>
        </li>

        <!-- Nav Item Cấp 1 có Dropdown Cấp 2 và Flyout Cấp 3: Theo công năng -->
        <li class="drv-nav__item">
          <a href="02-catalog-collection.html" class="drv-nav__link">
            <span>Theo công năng</span>
            <i class="ph ph-caret-down drv-icon drv-icon--xs" aria-hidden="true"></i>
          </a>

          <!-- Dropdown Cấp 2 -->
          <ul class="drv-dropdown" aria-label="Menu cấp 2 - Theo công năng">
            <li class="drv-dropdown__item">
              <a href="02-catalog-collection.html?nhom=lat-nen" class="drv-dropdown__link">
                <span class="l-cluster l-cluster--sm"><i class="ph ph-grid-four drv-icon" aria-hidden="true"></i><span>Gạch lát nền</span></span>
                <i class="ph ph-caret-right drv-dropdown__arrow" aria-hidden="true"></i>
              </a>
              <!-- Flyout Cấp 3 -->
              <ul class="drv-dropdown__flyout" aria-label="Menu cấp 3 - Gạch lát nền">
                <li class="drv-dropdown__heading">Phân loại Gạch lát nền</li>
                <li><a href="02-catalog-collection.html?kich-thuoc=80x80" class="drv-dropdown__sublink"><span>Lát nền phòng khách 80x80</span><span class="u-text-xs u-text-muted u-tabular">180</span></a></li>
                <li><a href="02-catalog-collection.html?kich-thuoc=60x120" class="drv-dropdown__sublink"><span>Khổ lớn Big Slab 60x120</span><span class="u-text-xs u-text-muted u-tabular">78</span></a></li>
                <li><a href="02-catalog-collection.html?be-mat=chong-tron" class="drv-dropdown__sublink"><span>Men Matt chống trơn R10-R11</span><span class="u-text-xs u-text-muted u-tabular">65</span></a></li>
                <li><a href="02-catalog-collection.html?van=gia-go" class="drv-dropdown__sublink"><span>Thanh gỗ Porcelain 15x90</span><span class="u-text-xs u-text-muted u-tabular">38</span></a></li>
              </ul>
            </li>

            <li class="drv-dropdown__item">
              <a href="02-catalog-collection.html?nhom=op-tuong" class="drv-dropdown__link">
                <span class="l-cluster l-cluster--sm"><i class="ph ph-wall drv-icon" aria-hidden="true"></i><span>Gạch ốp tường</span></span>
                <i class="ph ph-caret-right drv-dropdown__arrow" aria-hidden="true"></i>
              </a>
              <!-- Flyout Cấp 3 -->
              <ul class="drv-dropdown__flyout" aria-label="Menu cấp 3 - Gạch ốp tường">
                <li class="drv-dropdown__heading">Phân loại Gạch ốp tường</li>
                <li><a href="02-catalog-collection.html?kich-thuoc=30x60" class="drv-dropdown__sublink"><span>Gạch ốp 30x60 mờ &amp; bóng</span><span class="u-text-xs u-text-muted u-tabular">95</span></a></li>
                <li><a href="02-catalog-collection.html?loai=subway" class="drv-dropdown__sublink"><span>Gạch thẻ kiến trúc Subway</span><span class="u-text-xs u-text-muted u-tabular">45</span></a></li>
                <li><a href="02-catalog-collection.html?loai=bong-gio" class="drv-dropdown__sublink"><span>Gạch bông gió mỹ thuật</span><span class="u-text-xs u-text-muted u-tabular">22</span></a></li>
                <li><a href="02-catalog-collection.html?loai=vien-diem" class="drv-dropdown__sublink"><span>Gạch viền điểm &amp; bo góc</span><span class="u-text-xs u-text-muted u-tabular">16</span></a></li>
              </ul>
            </li>

            <li class="drv-dropdown__item">
              <a href="02-catalog-collection.html?van-gach=da-nung-ket" class="drv-dropdown__link">
                <span class="l-cluster l-cluster--sm"><i class="ph ph-sparkle drv-icon" aria-hidden="true"></i><span>Đá nung kết Big Slab</span></span>
                <i class="ph ph-caret-right drv-dropdown__arrow" aria-hidden="true"></i>
              </a>
              <!-- Flyout Cấp 3 -->
              <ul class="drv-dropdown__flyout" aria-label="Menu cấp 3 - Đá nung kết">
                <li class="drv-dropdown__heading">Ứng dụng Đá nung kết</li>
                <li><a href="02-catalog-collection.html?kich-thuoc=120x240" class="drv-dropdown__sublink"><span>Đá tấm lớn 120x240 cm</span><span class="u-text-xs u-text-muted u-tabular">15</span></a></li>
                <li><a href="02-catalog-collection.html?loai=mat-bep" class="drv-dropdown__sublink"><span>Mặt bàn bếp chịu nhiệt 1200°C</span><span class="u-text-xs u-text-muted u-tabular">24</span></a></li>
                <li><a href="02-catalog-collection.html?loai=vach-tivi" class="drv-dropdown__sublink"><span>Đá ốp vách TV Bookmatch</span><span class="u-text-xs u-text-muted u-tabular">18</span></a></li>
              </ul>
            </li>

            <li class="drv-dropdown__item">
              <a href="02-catalog-collection.html?nhom=ngoai-that" class="drv-dropdown__link">
                <span class="l-cluster l-cluster--sm"><i class="ph ph-tree drv-icon" aria-hidden="true"></i><span>Sân vườn &amp; Ngoại thất</span></span>
                <i class="ph ph-caret-right drv-dropdown__arrow" aria-hidden="true"></i>
              </a>
              <!-- Flyout Cấp 3 -->
              <ul class="drv-dropdown__flyout" aria-label="Menu cấp 3 - Sân vườn">
                <li class="drv-dropdown__heading">Gạch ngoại thất chịu lực</li>
                <li><a href="02-catalog-collection.html?do-day=20mm" class="drv-dropdown__sublink"><span>Gạch dày 20mm chịu lực gara</span><span class="u-text-xs u-text-muted u-tabular">28</span></a></li>
                <li><a href="02-catalog-collection.html?be-mat=san-vuon" class="drv-dropdown__sublink"><span>Gạch chống trơn sân vườn R11</span><span class="u-text-xs u-text-muted u-tabular">52</span></a></li>
                <li><a href="02-catalog-collection.html?loai=ho-boi" class="drv-dropdown__sublink"><span>Gạch mosaic hồ bơi</span><span class="u-text-xs u-text-muted u-tabular">34</span></a></li>
              </ul>
            </li>
          </ul>
        </li>

        <!-- Nav Item Cấp 1 có Dropdown Cấp 2 và Flyout Cấp 3: Theo không gian -->
        <li class="drv-nav__item">
          <a href="02-catalog-collection.html" class="drv-nav__link">
            <span>Theo không gian</span>
            <i class="ph ph-caret-down drv-icon drv-icon--xs" aria-hidden="true"></i>
          </a>

          <!-- Dropdown Cấp 2 -->
          <ul class="drv-dropdown" aria-label="Menu cấp 2 - Theo không gian">
            <li class="drv-dropdown__item">
              <a href="02-catalog-collection.html?khong-gian=phong-khach" class="drv-dropdown__link">
                <span class="l-cluster l-cluster--sm"><i class="ph ph-armchair drv-icon" aria-hidden="true"></i><span>Phòng khách sang trọng</span></span>
                <i class="ph ph-caret-right drv-dropdown__arrow" aria-hidden="true"></i>
              </a>
              <!-- Flyout Cấp 3 -->
              <ul class="drv-dropdown__flyout">
                <li class="drv-dropdown__heading">Gạch phòng khách</li>
                <li><a href="02-catalog-collection.html?kich-thuoc=80x80" class="drv-dropdown__sublink"><span>Lát nền 80x80 bóng kiếng Ý</span><span class="u-text-xs u-text-muted u-tabular">72</span></a></li>
                <li><a href="02-catalog-collection.html?loai=vach-tivi" class="drv-dropdown__sublink"><span>Đá nung kết ốp vách TV</span><span class="u-text-xs u-text-muted u-tabular">24</span></a></li>
                <li><a href="02-catalog-collection.html?loai=tham-cnc" class="drv-dropdown__sublink"><span>Gạch thảm CNC tiền sảnh</span><span class="u-text-xs u-text-muted u-tabular">16</span></a></li>
              </ul>
            </li>

            <li class="drv-dropdown__item">
              <a href="02-catalog-collection.html?khong-gian=nha-tam" class="drv-dropdown__link">
                <span class="l-cluster l-cluster--sm"><i class="ph ph-drop drv-icon" aria-hidden="true"></i><span>Nhà tắm / Toilet WC</span></span>
                <i class="ph ph-caret-right drv-dropdown__arrow" aria-hidden="true"></i>
              </a>
              <!-- Flyout Cấp 3 -->
              <ul class="drv-dropdown__flyout">
                <li class="drv-dropdown__heading">Gạch phòng tắm chống trơn</li>
                <li><a href="02-catalog-collection.html?be-mat=chong-tron" class="drv-dropdown__sublink"><span>Nền chống trượt Matt R10</span><span class="u-text-xs u-text-muted u-tabular">86</span></a></li>
                <li><a href="02-catalog-collection.html?kich-thuoc=30x60" class="drv-dropdown__sublink"><span>Ốp tường 30x60 chống ố</span><span class="u-text-xs u-text-muted u-tabular">54</span></a></li>
                <li><a href="02-catalog-collection.html?loai=mosaic" class="drv-dropdown__sublink"><span>Mosaic tắm đứng điểm nhấn</span><span class="u-text-xs u-text-muted u-tabular">30</span></a></li>
              </ul>
            </li>

            <li class="drv-dropdown__item">
              <a href="02-catalog-collection.html?khong-gian=bep" class="drv-dropdown__link">
                <span class="l-cluster l-cluster--sm"><i class="ph ph-cooking-pot drv-icon" aria-hidden="true"></i><span>Bếp &amp; Phòng ăn</span></span>
                <i class="ph ph-caret-right drv-dropdown__arrow" aria-hidden="true"></i>
              </a>
              <!-- Flyout Cấp 3 -->
              <ul class="drv-dropdown__flyout">
                <li class="drv-dropdown__heading">Gạch phòng bếp sạch dầu</li>
                <li><a href="02-catalog-collection.html?loai=mat-bep" class="drv-dropdown__sublink"><span>Đá nung kết mặt bàn bếp</span><span class="u-text-xs u-text-muted u-tabular">24</span></a></li>
                <li><a href="02-catalog-collection.html?loai=subway" class="drv-dropdown__sublink"><span>Gạch thẻ backsplash tường bếp</span><span class="u-text-xs u-text-muted u-tabular">42</span></a></li>
                <li><a href="02-catalog-collection.html?be-mat=lau-chui" class="drv-dropdown__sublink"><span>Gạch nền men khô dễ lau chùi</span><span class="u-text-xs u-text-muted u-tabular">64</span></a></li>
              </ul>
            </li>

            <li class="drv-dropdown__item">
              <a href="02-catalog-collection.html?khong-gian=san-vuon" class="drv-dropdown__link">
                <span class="l-cluster l-cluster--sm"><i class="ph ph-sun drv-icon" aria-hidden="true"></i><span>Sân vườn &amp; Ban công</span></span>
                <i class="ph ph-caret-right drv-dropdown__arrow" aria-hidden="true"></i>
              </a>
              <!-- Flyout Cấp 3 -->
              <ul class="drv-dropdown__flyout">
                <li class="drv-dropdown__heading">Khu vực ngoài trời</li>
                <li><a href="02-catalog-collection.html?do-day=20mm" class="drv-dropdown__sublink"><span>Đá 20mm lát sân vườn / cỏ</span><span class="u-text-xs u-text-muted u-tabular">28</span></a></li>
                <li><a href="02-catalog-collection.html?be-mat=san-vuon" class="drv-dropdown__sublink"><span>Gạch chống trơn sân thượng R11</span><span class="u-text-xs u-text-muted u-tabular">52</span></a></li>
              </ul>
            </li>
          </ul>
        </li>

        <li class="drv-nav__item"><a href="02-catalog-collection.html?van-gach=van-da-marble" class="drv-nav__link">Vân &amp; Chất liệu</a></li>
        <li class="drv-nav__item"><a href="02-catalog-collection.html?kich-thuoc=80x80" class="drv-nav__link">Theo kích thước</a></li>
        <li class="drv-nav__item"><a href="02-catalog-collection.html?van-gach=da-nung-ket" class="drv-nav__link">Đá nung kết</a></li>
        <li class="drv-nav__item"><a href="02-catalog-collection.html" class="drv-nav__link drv-nav__link--sale">Gạch ưu đãi</a></li>
      </ul>

      <ul class="drv-nav__list">
        <li class="drv-nav__item">
          <a href="01.1-ai-finder.html" class="drv-nav__link" style="color: var(--c-teal-700); font-weight: 700;">
            <i class="ph-fill ph-robot drv-icon--xs" aria-hidden="true"></i>
            <span>Trợ lý AI Tìm gạch</span>
          </a>
        </li>
        <li class="drv-nav__item"><a href="07-blog-archive.html" class="drv-nav__link">Cẩm nang kỹ thuật</a></li>
        <li class="drv-nav__item"><a href="09-showroom-about-quote.html" class="drv-nav__link">Về Dravit &amp; Showroom</a></li>
      </ul>
    </div>

    <!-- Mega Menu "Silo Matrix 4+1" - Cấu trúc phân cấp Cấp 1 -> Cấp 2 -> Cấp 3 (Option 2: Live Dynamic Preview) -->
    <div id="drv-mega-menu" class="drv-mega" hidden aria-label="Ma trận 28 danh mục gạch ốp lát">
      <div class="l-container">
        <div class="drv-mega__matrix">
          <!-- Col 1: Cấp 1 - Theo công năng -->
          <div class="drv-mega__col">
            <span class="drv-mega__heading">01. Theo công năng (7)</span>
            
            <!-- Nhóm Lát nền -->
            <div class="drv-mega__subgroup">
              <span class="drv-mega__subheading">
                <i class="ph ph-grid-four drv-icon--xs u-text-teal" aria-hidden="true"></i>
                <span>Gạch Lát Nền</span>
              </span>
              <ul class="drv-mega__links">
                <li>
                  <a href="02-catalog-collection.html?kich-thuoc=80x80" class="drv-mega__link is-active"
                     data-drv-preview-img="assets/img/room-living-calacatta.svg"
                     data-drv-preview-badge="Bán chạy phòng khách"
                     data-drv-preview-title="Gạch Lát Phòng Khách 80x80 Calacatta"
                     data-drv-preview-desc="Vân mây trắng Ý sang trọng, mặt bóng kiếng chống bám bẩn, tạo hiệu ứng mở rộng không gian đại sảnh."
                     data-drv-preview-price="Từ 385.000đ/m²">
                    <span class="drv-mega__label">Lát phòng khách 80x80</span>
                    <span class="drv-mega__count">180</span>
                  </a>
                </li>
                <li>
                  <a href="02-catalog-collection.html?be-mat=chong-tron" class="drv-mega__link"
                     data-drv-preview-img="assets/img/room-bathroom-terrazzo.svg"
                     data-drv-preview-badge="Chống trượt R10-R11"
                     data-drv-preview-title="Gạch Men Matt Chống Trơn Ướt"
                     data-drv-preview-desc="Bề mặt nhám mờ vi tinh thể an toàn tuyệt đối cho người già &amp; trẻ nhỏ, chống trơn trượt phòng tắm."
                     data-drv-preview-price="Từ 320.000đ/m²">
                    <span class="drv-mega__label">Nền chống trơn Matt R10</span>
                    <span class="drv-mega__count">65</span>
                  </a>
                </li>
                <li>
                  <a href="02-catalog-collection.html?van-gach=gia-go" class="drv-mega__link"
                     data-drv-preview-img="assets/img/tile-wood-oak.svg"
                     data-drv-preview-badge="Xương đá Porcelain"
                     data-drv-preview-title="Gạch Vân Gỗ Tự Nhiên 15x90"
                     data-drv-preview-desc="Vân sồi Bắc Âu chân thực từng thớ gỗ, không cong vênh, chống mối mọt và chịu nước 100%."
                     data-drv-preview-price="Từ 295.000đ/m²">
                    <span class="drv-mega__label">Thanh gỗ Porcelain 15x90</span>
                    <span class="drv-mega__count">38</span>
                  </a>
                </li>
              </ul>
            </div>

            <!-- Nhóm Ốp tường & Trang trí -->
            <div class="drv-mega__subgroup">
              <span class="drv-mega__subheading">
                <i class="ph ph-wall drv-icon--xs u-text-teal" aria-hidden="true"></i>
                <span>Gạch Ốp &amp; Trang Trí</span>
              </span>
              <ul class="drv-mega__links">
                <li>
                  <a href="02-catalog-collection.html?kich-thuoc=30x60" class="drv-mega__link"
                     data-drv-preview-img="assets/img/tile-decor-fluted.svg"
                     data-drv-preview-badge="Khổ chuẩn thông dụng"
                     data-drv-preview-title="Gạch Ốp Tường 30x60 Men Vi Tinh"
                     data-drv-preview-desc="Dễ thi công gióng mạch thẳng hàng, men vi tinh kháng khuẩn lau chùi dầu mỡ dễ dàng."
                     data-drv-preview-price="Từ 210.000đ/m²">
                    <span class="drv-mega__label">Gạch ốp tường 30x60</span>
                    <span class="drv-mega__count">142</span>
                  </a>
                </li>
                <li>
                  <a href="02-catalog-collection.html?loai=subway" class="drv-mega__link"
                     data-drv-preview-img="assets/img/room-kitchen-cement.svg"
                     data-drv-preview-badge="Điểm nhấn decor"
                     data-drv-preview-title="Gạch Thẻ Subway Mosaic Điểm Nhấn"
                     data-drv-preview-desc="Kích thước thanh thoát tạo nhịp điệu ấn tượng cho backsplash bếp, quầy bar và nhà tắm."
                     data-drv-preview-price="Từ 185.000đ/m²">
                    <span class="drv-mega__label">Gạch thẻ Subway Mosaic</span>
                    <span class="drv-mega__count">45</span>
                  </a>
                </li>
                <li>
                  <a href="02-catalog-collection.html?loai=bong-gio" class="drv-mega__link"
                     data-drv-preview-img="assets/img/room-outdoor-patio.svg"
                     data-drv-preview-badge="Kiến trúc thông gió"
                     data-drv-preview-title="Gạch Bông Gió Mỹ Thuật Sinh Thái"
                     data-drv-preview-desc="Lấy sáng tự nhiên, đón gió đối lưu cho mặt tiền và vách ngăn không gian mở."
                     data-drv-preview-price="Từ 28.000đ/viên">
                    <span class="drv-mega__label">Gạch bông gió mỹ thuật</span>
                    <span class="drv-mega__count">22</span>
                  </a>
                </li>
              </ul>
            </div>
          </div>

          <!-- Col 2: Cấp 1 - Theo không gian -->
          <div class="drv-mega__col">
            <span class="drv-mega__heading">02. Theo không gian (8)</span>
            
            <!-- Nội thất sống -->
            <div class="drv-mega__subgroup">
              <span class="drv-mega__subheading">
                <i class="ph ph-armchair drv-icon--xs u-text-teal" aria-hidden="true"></i>
                <span>Không Gian Nội Thất</span>
              </span>
              <ul class="drv-mega__links">
                <li>
                  <a href="02-catalog-collection.html?khong-gian=phong-khach" class="drv-mega__link"
                     data-drv-preview-img="assets/img/room-living-calacatta.svg"
                     data-drv-preview-badge="Phòng khách cao cấp"
                     data-drv-preview-title="Bộ Sưu Tập Gạch Phòng Khách &amp; Đại Sảnh"
                     data-drv-preview-desc="Các mẫu khổ 80x80 và 60x120 sang trọng, phản chiếu ánh sáng tự nhiên tuyệt đẹp."
                     data-drv-preview-price="Từ 385.000đ/m²">
                    <span class="drv-mega__label">Phòng khách &amp; Đại sảnh</span>
                    <span class="drv-mega__count">124</span>
                  </a>
                </li>
                <li>
                  <a href="02-catalog-collection.html?khong-gian=bep" class="drv-mega__link"
                     data-drv-preview-img="assets/img/room-kitchen-cement.svg"
                     data-drv-preview-badge="Chịu nhiệt 1200°C"
                     data-drv-preview-title="Gạch Bếp &amp; Bàn Đảo Sạch Dầu"
                     data-drv-preview-desc="Đá nung kết mặt bàn bếp chịu nhiệt trực tiếp, kết hợp nền chống bám dầu lau sạch tức thì."
                     data-drv-preview-price="Từ 350.000đ/m²">
                    <span class="drv-mega__label">Bếp &amp; Phòng ăn sạch dầu</span>
                    <span class="drv-mega__count">64</span>
                  </a>
                </li>
                <li>
                  <a href="02-catalog-collection.html?khong-gian=phong-ngu" class="drv-mega__link"
                     data-drv-preview-img="assets/img/tile-wood-oak.svg"
                     data-drv-preview-badge="Màu sắc ấm cúng"
                     data-drv-preview-title="Gạch Phòng Ngủ Thư Thái"
                     data-drv-preview-desc="Bề mặt mờ Satin dịu mắt dưới ánh đèn ngủ, cảm giác bước chân êm ái và ấm áp."
                     data-drv-preview-price="Từ 295.000đ/m²">
                    <span class="drv-mega__label">Phòng ngủ ấm cúng</span>
                    <span class="drv-mega__count">58</span>
                  </a>
                </li>
              </ul>
            </div>

            <!-- Ẩm ướt & Ngoài trời -->
            <div class="drv-mega__subgroup">
              <span class="drv-mega__subheading">
                <i class="ph ph-drop drv-icon--xs u-text-teal" aria-hidden="true"></i>
                <span>Ẩm Ướt &amp; Ngoại Thất</span>
              </span>
              <ul class="drv-mega__links">
                <li>
                  <a href="02-catalog-collection.html?khong-gian=nha-tam" class="drv-mega__link"
                     data-drv-preview-img="assets/img/room-bathroom-terrazzo.svg"
                     data-drv-preview-badge="An toàn WC R10"
                     data-drv-preview-title="Gạch Nhà Tắm &amp; WC Chống Trơn"
                     data-drv-preview-desc="Độ nhám R10-R11 không đọng nước xà phòng, hạn chế tối đa rêu mốc chân tường."
                     data-drv-preview-price="Từ 320.000đ/m²">
                    <span class="drv-mega__label">Nhà tắm / WC chống trượt</span>
                    <span class="drv-mega__count">86</span>
                  </a>
                </li>
                <li>
                  <a href="02-catalog-collection.html?khong-gian=san-vuon" class="drv-mega__link"
                     data-drv-preview-img="assets/img/room-outdoor-patio.svg"
                     data-drv-preview-badge="Chịu tải 5 tấn"
                     data-drv-preview-title="Đá Dày 20mm Sân Vườn Gara"
                     data-drv-preview-desc="Xương đá đồng chất siêu cứng, có thể lát nổi trên chân đế hoặc trực tiếp trên bãi cỏ."
                     data-drv-preview-price="Từ 490.000đ/m²">
                    <span class="drv-mega__label">Sân vườn chịu lực 20mm</span>
                    <span class="drv-mega__count">52</span>
                  </a>
                </li>
                <li>
                  <a href="02-catalog-collection.html?khong-gian=mat-tien" class="drv-mega__link"
                     data-drv-preview-img="assets/img/tile-structured-outdoor.svg"
                     data-drv-preview-badge="Chống chịu thời tiết"
                     data-drv-preview-title="Gạch Ốp Mặt Tiền &amp; Đại Sảnh"
                     data-drv-preview-desc="Không phai màu dưới nắng mưa khắc nghiệt, chống bong tróc và bền vững trên 30 năm."
                     data-drv-preview-price="Từ 365.000đ/m²">
                    <span class="drv-mega__label">Gạch ốp mặt tiền &amp; sảnh</span>
                    <span class="drv-mega__count">40</span>
                  </a>
                </li>
              </ul>
            </div>
          </div>

          <!-- Col 3: Cấp 1 - Vân & Chất liệu -->
          <div class="drv-mega__col">
            <span class="drv-mega__heading">03. Vân &amp; Chất liệu (7)</span>
            
            <!-- Bề mặt & Họa tiết -->
            <div class="drv-mega__subgroup">
              <span class="drv-mega__subheading">
                <i class="ph ph-paint-brush drv-icon--xs u-text-teal" aria-hidden="true"></i>
                <span>Bề Mặt &amp; Họa Tiết</span>
              </span>
              <ul class="drv-mega__links">
                <li>
                  <a href="02-catalog-collection.html?van-gach=van-da-marble" class="drv-mega__link"
                     data-drv-preview-img="assets/img/tile-marble-calacatta.svg"
                     data-drv-preview-badge="Calacatta Ý"
                     data-drv-preview-title="Vân Đá Marble Ý Calacatta"
                     data-drv-preview-desc="Dòng vân cẩm thạch trắng khói cao cấp, độ bóng sâu phản quang sang trọng."
                     data-drv-preview-price="Từ 385.000đ/m²">
                    <span class="drv-mega__label">
                      <span class="drv-swatch-dot" style="background:#f3f4f6"></span>
                      <span>Vân đá Marble Calacatta</span>
                    </span>
                    <span class="drv-mega__count">108</span>
                  </a>
                </li>
                <li>
                  <a href="02-catalog-collection.html?van-gach=terrazzo" class="drv-mega__link"
                     data-drv-preview-img="assets/img/tile-terrazzo-ivory.svg"
                     data-drv-preview-badge="Hạt đá mộc mạc"
                     data-drv-preview-title="Gạch Terrazzo Hạt Đá Nhuyễn"
                     data-drv-preview-desc="Phối hạt đá tự nhiên tinh tế trên nền kem sáng, hợp phong cách Địa Trung Hải và Wabi Sabi."
                     data-drv-preview-price="Từ 395.000đ/m²">
                    <span class="drv-mega__label">
                      <span class="drv-swatch-dot" style="background:#e7e5e4"></span>
                      <span>Gạch Terrazzo hạt đá</span>
                    </span>
                    <span class="drv-mega__count">46</span>
                  </a>
                </li>
                <li>
                  <a href="02-catalog-collection.html?van-gach=van-xi-mang" class="drv-mega__link"
                     data-drv-preview-img="assets/img/tile-cement-grey.svg"
                     data-drv-preview-badge="Minimalist Concrete"
                     data-drv-preview-title="Gạch Vân Bê Tông Xi Măng Mộc"
                     data-drv-preview-desc="Chất liệu xám lạnh tinh tế cho phong cách kiến trúc công nghiệp và tối giản hiện đại."
                     data-drv-preview-price="Từ 245.000đ/m²">
                    <span class="drv-mega__label">
                      <span class="drv-swatch-dot" style="background:#94a3b8"></span>
                      <span>Vân xi măng Minimalist</span>
                    </span>
                    <span class="drv-mega__count">62</span>
                  </a>
                </li>
              </ul>
            </div>

            <!-- Xương đá & Công nghệ -->
            <div class="drv-mega__subgroup">
              <span class="drv-mega__subheading">
                <i class="ph ph-diamonds-four drv-icon--xs u-text-teal" aria-hidden="true"></i>
                <span>Xương Đá &amp; Công Nghệ</span>
              </span>
              <ul class="drv-mega__links">
                <li>
                  <a href="02-catalog-collection.html?van-gach=da-nung-ket" class="drv-mega__link"
                     data-drv-preview-img="assets/img/tile-sintered-emperador.svg"
                     data-drv-preview-badge="Đỉnh cao vật liệu"
                     data-drv-preview-title="Đá Nung Kết Sintered Stone"
                     data-drv-preview-desc="Nung ở 1200°C ép 30.000 tấn, không trầy xước dao kéo, bề mặt kháng khuẩn tuyệt đối."
                     data-drv-preview-price="Từ 550.000đ/m²">
                    <span class="drv-mega__label">
                      <span class="drv-swatch-dot" style="background:#1e293b"></span>
                      <span>Đá nung kết Sintered</span>
                    </span>
                    <span class="drv-mega__count">29</span>
                  </a>
                </li>
                <li>
                  <a href="02-catalog-collection.html" class="drv-mega__link"
                     data-drv-preview-img="assets/img/tile-marble-calacatta.svg"
                     data-drv-preview-badge="Độ hút nước ≤ 0.5%"
                     data-drv-preview-title="Xương Đá Porcelain Đồng Chất"
                     data-drv-preview-desc="Độ bền cơ học vượt trội gấp 3 lần ceramic thông thường, chịu va đập và chống thấm vĩnh viễn."
                     data-drv-preview-price="Từ 260.000đ/m²">
                    <span class="drv-mega__label">
                      <span class="drv-swatch-dot" style="background:#f8fafc"></span>
                      <span>Xương đá Porcelain</span>
                    </span>
                    <span class="drv-mega__count">310</span>
                  </a>
                </li>
              </ul>
            </div>
          </div>

          <!-- Col 4: Cấp 1 - Kích thước chuẩn có hình tỷ lệ -->
          <div class="drv-mega__col">
            <span class="drv-mega__heading">04. Kích thước chuẩn (5)</span>
            
            <!-- Khổ lớn Big Slab -->
            <div class="drv-mega__subgroup">
              <span class="drv-mega__subheading">
                <i class="ph ph-arrows-out drv-icon--xs u-text-teal" aria-hidden="true"></i>
                <span>Khổ Lớn Big Slab</span>
              </span>
              <ul class="drv-mega__links">
                <li>
                  <a href="02-catalog-collection.html?kich-thuoc=60x120" class="drv-mega__link"
                     data-drv-preview-img="assets/img/tile-sintered-emperador.svg"
                     data-drv-preview-badge="Khổ dài Big Slab"
                     data-drv-preview-title="Gạch Khổ Lớn 60x120 cm"
                     data-drv-preview-desc="Giảm 50% số đường ron trên mặt sàn rộng, vân đá liền mạch tăng vẻ khoáng đạt cho ngôi nhà."
                     data-drv-preview-price="Từ 450.000đ/m²">
                    <span class="drv-mega__label">
                      <span class="drv-size-glyph drv-size-glyph--60x120"></span>
                      <span>Gạch 60x120 cm</span>
                    </span>
                    <span class="drv-mega__count">78</span>
                  </a>
                </li>
                <li>
                  <a href="02-catalog-collection.html?kich-thuoc=120x240" class="drv-mega__link"
                     data-drv-preview-img="assets/img/room-living-calacatta.svg"
                     data-drv-preview-badge="Khổ đại 1.2 x 2.4m"
                     data-drv-preview-title="Đá Khổ Đại Tấm Liền 120x240 cm"
                     data-drv-preview-desc="Đá nguyên tấm ốp tường kịch trần không mạch ngang, vẻ đẹp nguy nga như khách sạn 5 sao."
                     data-drv-preview-price="Báo giá theo tấm">
                    <span class="drv-mega__label">
                      <span class="drv-size-glyph drv-size-glyph--60x120" style="width:16px;height:32px"></span>
                      <span>Đá đại 120x240 cm</span>
                    </span>
                    <span class="drv-mega__count">15</span>
                  </a>
                </li>
              </ul>
            </div>

            <!-- Khổ Tiêu Chuẩn -->
            <div class="drv-mega__subgroup">
              <span class="drv-mega__subheading">
                <i class="ph ph-bounding-box drv-icon--xs u-text-teal" aria-hidden="true"></i>
                <span>Khổ Tiêu Chuẩn</span>
              </span>
              <ul class="drv-mega__links">
                <li>
                  <a href="02-catalog-collection.html?kich-thuoc=80x80" class="drv-mega__link"
                     data-drv-preview-img="assets/img/tile-marble-calacatta.svg"
                     data-drv-preview-badge="Chuẩn mực phòng khách"
                     data-drv-preview-title="Gạch Vuông Chuẩn 80x80 cm"
                     data-drv-preview-desc="Kích thước vàng cho nhà phố và căn hộ 60-120m², tỷ lệ hài hòa với mọi phong cách nội thất."
                     data-drv-preview-price="Từ 385.000đ/m²">
                    <span class="drv-mega__label">
                      <span class="drv-size-glyph drv-size-glyph--80x80"></span>
                      <span>Gạch 80x80 cm</span>
                    </span>
                    <span class="drv-mega__count">92</span>
                  </a>
                </li>
                <li>
                  <a href="02-catalog-collection.html?kich-thuoc=60x60" class="drv-mega__link"
                     data-drv-preview-img="assets/img/tile-cement-grey.svg"
                     data-drv-preview-badge="Kinh tế &amp; Dễ thi công"
                     data-drv-preview-title="Gạch Lát Nền 60x60 cm"
                     data-drv-preview-desc="Lựa chọn tối ưu chi phí cho phòng ngủ, căn hộ dịch vụ và các hạng mục phụ trợ."
                     data-drv-preview-price="Từ 215.000đ/m²">
                    <span class="drv-mega__label">
                      <span class="drv-size-glyph drv-size-glyph--60x60"></span>
                      <span>Gạch 60x60 cm</span>
                    </span>
                    <span class="drv-mega__count">110</span>
                  </a>
                </li>
                <li>
                  <a href="02-catalog-collection.html?kich-thuoc=30x60" class="drv-mega__link"
                     data-drv-preview-img="assets/img/tile-decor-fluted.svg"
                     data-drv-preview-badge="Khổ ốp tường quốc dân"
                     data-drv-preview-title="Gạch Ốp Tường 30x60 cm"
                     data-drv-preview-desc="Gạch ốp tường chuẩn mực cho toilet, phòng tắm và khu giặt sấy."
                     data-drv-preview-price="Từ 195.000đ/m²">
                    <span class="drv-mega__label">
                      <span class="drv-size-glyph drv-size-glyph--30x60"></span>
                      <span>Gạch ốp 30x60 cm</span>
                    </span>
                    <span class="drv-mega__count">95</span>
                  </a>
                </li>
              </ul>
            </div>

            <a href="02-catalog-collection.html" class="drv-more-btn drv-more-btn--start">
              <span>Xem tất cả 28 danh mục</span>
              <i class="ph-bold ph-caret-right drv-icon--xs" aria-hidden="true"></i>
            </a>
          </div>

          <!-- Col 5: Interactive Live Dynamic Preview Box (Option 2) -->
          <div class="drv-mega__col drv-mega__col--featured">
            <div class="l-stack l-stack--sm">
              <div class="l-cluster l-cluster--between">
                <span id="drv-mega-preview-badge" class="drv-badge drv-badge--moss">Bán chạy phòng khách</span>
                <span id="drv-mega-preview-price" class="u-text-xs u-bold u-text-sale u-tabular">Từ 385.000đ/m²</span>
              </div>
              <img id="drv-mega-preview-img" src="assets/img/room-living-calacatta.svg" width="240" height="145" loading="lazy" decoding="async" alt="Phối cảnh gạch Dravit" class="u-rounded-sm u-bd drv-mega-preview__img">
              <h3 id="drv-mega-preview-title" class="u-text-base u-bold">Gạch Lát Phòng Khách 80x80 Calacatta</h3>
              <p id="drv-mega-preview-desc" class="u-text-xs u-text-muted">Vân mây trắng Ý sang trọng, mặt bóng kiếng chống bám bẩn, tạo hiệu ứng mở rộng không gian đại sảnh.</p>
            </div>
            <a id="drv-mega-preview-btn" href="02-catalog-collection.html?kich-thuoc=80x80" class="drv-btn drv-btn--sm drv-btn--full">
              <span>Xem danh mục</span>
              <i class="ph ph-arrow-right drv-icon--xs" aria-hidden="true"></i>
            </a>
          </div>
        </div>

        <!-- Bottom Quick Utility Strip -->
        <div class="drv-mega__bottom">
          <div class="l-cluster l-cluster--sm">
            <span class="u-text-xs u-text-muted">
              <i class="ph ph-headset drv-icon--xs u-text-teal" aria-hidden="true"></i>
              <span>Hotline bóc tách số lượng theo bản vẽ:</span>
              <strong class="u-text-navy u-tabular">0909.888.777</strong>
            </span>
          </div>
          <div class="l-cluster l-cluster--sm">
            <a href="09-showroom-about-quote.html#showroom" class="drv-link u-text-xs">
              <i class="ph ph-map-pin drv-icon--xs" aria-hidden="true"></i>
              <span>Xem mẫu thực tế tại Showroom Sala</span>
            </a>
            <button type="button" class="drv-btn drv-btn--soft drv-btn--sm" data-drv-action="dialog-open" data-drv-target="#drv-quote-modal">
              <i class="ph ph-file-text drv-icon--xs" aria-hidden="true"></i>
              <span>Báo giá chiết khấu công trình</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</header>`;

  const DRV_FOOTER_PARTIAL_FALLBACK = `<!-- 3. SHARED DIALOG 1: MODAL NHẬN BÁO GIÁ CÔNG TRÌNH -->
<dialog id="drv-quote-modal" class="drv-dialog" aria-labelledby="quote-modal-title">
  <div class="drv-dialog__head">
    <div class="l-stack l-stack--xs">
      <span class="u-text-xs u-bold u-uppercase u-text-moss">Hỗ trợ chủ nhà, thợ thầu &amp; kiến trúc sư</span>
      <h2 id="quote-modal-title" class="u-text-lg">Nhận báo giá chiết khấu công trình</h2>
    </div>
    <button type="button" class="drv-btn drv-btn--sm drv-btn--ghost drv-btn--icon" data-drv-action="dialog-close" aria-label="Đóng hộp thoại">
      <i class="ph ph-x drv-icon" aria-hidden="true"></i>
    </button>
  </div>
  <form class="drv-dialog__body l-stack l-stack--md" data-drv-validate data-drv-success-msg="Đã nhận yêu cầu báo giá! Kỹ thuật viên Dravit sẽ gửi bảng tính số thùng qua Zalo trong 15 phút.">
    <div class="l-grid l-grid--2">
      <div class="drv-field">
        <label for="modal-quote-name" class="drv-label">Họ và tên <span class="drv-label__req">*</span></label>
        <input id="modal-quote-name" name="quote_name" type="text" class="drv-input" required placeholder="Nhập họ và tên">
        <span class="drv-field__error" role="alert" hidden></span>
      </div>
      <div class="drv-field">
        <label for="modal-quote-phone" class="drv-label">Số điện thoại Zalo <span class="drv-label__req">*</span></label>
        <input id="modal-quote-phone" name="quote_phone" type="tel" inputmode="tel" class="drv-input" required placeholder="09xx xxx xxx">
        <span class="drv-field__error" role="alert" hidden></span>
      </div>
    </div>

    <div class="l-grid l-grid--2">
      <div class="drv-field">
        <label for="modal-quote-prov" class="drv-label">Tỉnh / Thành phố giao hàng</label>
        <select id="modal-quote-prov" name="quote_province" class="drv-select" data-drv-province></select>
      </div>
      <div class="drv-field">
        <label for="modal-quote-area" class="drv-label">Tổng diện tích dự kiến (m²)</label>
        <input id="modal-quote-area" name="quote_area" type="number" class="drv-input" value="85" min="1">
      </div>
    </div>

    <div class="drv-field">
      <label for="modal-quote-product" class="drv-label">Mã gạch hoặc hạng mục quan tâm</label>
      <input id="modal-quote-product" name="quote_product" type="text" class="drv-input" value="DRV-8801M - Gạch lát nền phòng khách &amp; nhà tắm">
    </div>

    <div class="drv-field">
      <label for="modal-quote-note" class="drv-label">Ghi chú thêm (Thời gian cần hàng, yêu cầu xuất VAT...)</label>
      <textarea id="modal-quote-note" name="quote_note" class="drv-textarea" placeholder="Ví dụ: Công trình nhà phố 3 tầng, cần xem mẫu thực tế tại showroom chiều thứ 7..."></textarea>
    </div>

    <div class="l-cluster l-cluster--between">
      <span class="u-text-xs u-text-muted">Cam kết kiểm tra tồn kho cùng lô màu (Shade/Caliber).</span>
      <button type="submit" class="drv-btn drv-btn--moss">Gửi yêu cầu báo giá</button>
    </div>
  </form>
</dialog>

<!-- 4. SHARED DIALOG 2: MINI-CART DRAWER -->
<dialog id="drv-minicart-drawer" class="drv-dialog drv-dialog--drawer-right" aria-labelledby="minicart-title">
  <div class="drv-dialog__head">
    <div class="l-cluster l-cluster--sm">
      <i class="ph ph-shopping-bag drv-icon u-text-moss" aria-hidden="true"></i>
      <h2 id="minicart-title" class="u-text-lg">Giỏ hàng (<span data-cart-sku-count>2</span> mã sản phẩm)</h2>
    </div>
    <button type="button" class="drv-btn drv-btn--sm drv-btn--ghost drv-btn--icon" data-drv-action="dialog-close" aria-label="Đóng giỏ hàng">
      <i class="ph ph-x drv-icon" aria-hidden="true"></i>
    </button>
  </div>

  <div class="drv-dialog__body l-stack l-stack--md">
    <!-- Item 1: Tile (area_pack) -->
    <div class="u-bd u-rounded-sm u-pad-3 l-stack l-stack--sm" data-cart-row data-cart-sku="DRV-8801M" data-cart-default="true">
      <div class="l-cluster l-cluster--between l-cluster--nowrap">
        <div class="l-cluster l-cluster--sm l-cluster--nowrap">
          <img src="assets/img/tile-marble-calacatta.svg" width="56" height="56" alt="DRV-8801M" class="u-rounded-sm u-bd">
          <div class="l-stack l-stack--xs">
            <span class="u-text-xs u-text-muted u-tabular">SKU: DRV-8801M · 385.000đ/m²</span>
            <a href="03-product-single.html" class="u-text-sm u-bold" data-row-title>Gạch Marble Calacatta 80x80 Bóng Kiếng</a>
            <span class="drv-badge drv-badge--showroom" data-row-conversion>= 6 thùng · 11,52 m² · 252 kg</span>
          </div>
        </div>
        <button type="button" class="drv-btn drv-btn--sm drv-btn--ghost drv-btn--icon" data-cart-remove aria-label="Xóa DRV-8801M">
          <i class="ph ph-trash drv-icon drv-icon--sm" aria-hidden="true"></i>
        </button>
      </div>

      <div class="l-cluster l-cluster--between">
        <drv-qty-input
          data-model="area_pack"
          data-price-unit="246400"
          data-area-piece="0.64"
          data-pieces-per-box="3"
          data-weight-piece="14"
          data-unit-label="viên">
          <div class="l-cluster l-cluster--xs">
            <div class="drv-stepper" style="width:128px;height:36px">
              <button type="button" class="drv-stepper__btn drv-stepper__btn--prev" data-qty-step="-1" aria-label="Giảm 1 viên"><i class="ph ph-minus drv-icon--xs" aria-hidden="true"></i></button>
              <input type="number" class="drv-stepper__input" value="18" min="1" aria-label="Số viên DRV-8801M">
              <button type="button" class="drv-stepper__btn drv-stepper__btn--next" data-qty-step="1" aria-label="Tăng 1 viên"><i class="ph ph-plus drv-icon--xs" aria-hidden="true"></i></button>
            </div>
            <button type="button" class="drv-btn drv-btn--sm drv-btn--soft" data-qty-add-box>
              <i class="ph ph-plus drv-icon--xs" aria-hidden="true"></i>
              <span>1 thùng (3v)</span>
            </button>
          </div>
        </drv-qty-input>
        <strong class="u-text-base u-text-navy u-tabular" data-row-subtotal>4.435.200đ</strong>
      </div>
    </div>

    <!-- Item 2: Grout (pack) -->
    <div class="u-bd u-rounded-sm u-pad-3 l-stack l-stack--sm" data-cart-row data-cart-sku="DRV-RON01" data-cart-default="true">
      <div class="l-cluster l-cluster--between l-cluster--nowrap">
        <div class="l-cluster l-cluster--sm l-cluster--nowrap">
          <img src="assets/img/tile-grout-pack.svg" width="56" height="56" alt="DRV-keo-trang" class="u-rounded-sm u-bd">
          <div class="l-stack l-stack--xs">
            <span class="u-text-xs u-text-muted u-tabular">SKU: DRV-RON01 · 85.000đ/túi</span>
            <a href="03-product-single.html" class="u-text-sm u-bold" data-row-title>Keo Chà Ron Chống Thấm Dravit Trắng Sứ (Túi 2kg)</a>
            <span class="drv-badge drv-badge--outline" data-row-conversion>= 3 túi · 6 kg</span>
          </div>
        </div>
        <button type="button" class="drv-btn drv-btn--sm drv-btn--ghost drv-btn--icon" data-cart-remove aria-label="Xóa Keo chà ron">
          <i class="ph ph-trash drv-icon drv-icon--sm" aria-hidden="true"></i>
        </button>
      </div>

      <div class="l-cluster l-cluster--between">
        <drv-qty-input
          data-model="pack"
          data-price-unit="85000"
          data-weight-piece="2"
          data-unit-label="túi">
          <div class="drv-stepper" style="width:128px;height:36px">
            <button type="button" class="drv-stepper__btn drv-stepper__btn--prev" data-qty-step="-1" aria-label="Giảm 1 túi"><i class="ph ph-minus drv-icon--xs" aria-hidden="true"></i></button>
            <input type="number" class="drv-stepper__input" value="3" min="1" aria-label="Số túi keo chà ron">
            <button type="button" class="drv-stepper__btn drv-stepper__btn--next" data-qty-step="1" aria-label="Tăng 1 túi"><i class="ph ph-plus drv-icon--xs" aria-hidden="true"></i></button>
          </div>
        </drv-qty-input>
        <strong class="u-text-base u-text-navy u-tabular" data-row-subtotal>255.000đ</strong>
      </div>
    </div>
  </div>

  <div class="drv-dialog__foot l-stack l-stack--sm" aria-live="polite">
    <div class="drv-summary-row">
      <span class="u-text-muted">Tổng diện tích gạch:</span>
      <strong data-cart-total-m2>11,52 m²</strong>
    </div>
    <div class="drv-summary-row">
      <span class="u-text-muted">Tổng trọng lượng ước tính:</span>
      <strong data-cart-total-kg>258 kg</strong>
    </div>
    <div class="drv-summary-row">
      <span class="u-text-muted">Phí vận chuyển:</span>
      <span class="u-text-moss u-bold">Báo sau khi xác nhận đơn</span>
    </div>
    <div class="drv-summary-row drv-summary-row--total">
      <span>Tổng tạm tính:</span>
      <span class="u-text-sale" data-cart-total-price>4.690.200đ</span>
    </div>
    <div class="l-grid l-grid--2 l-grid--gap-sm">
      <a href="04-cart.html" class="drv-btn drv-btn--outline drv-btn--full">Xem giỏ hàng</a>
      <a href="05-checkout.html" class="drv-btn drv-btn--full">Thanh toán ngay</a>
    </div>
  </div>
</dialog>

<!-- 5. SHARED DIALOG 3: MOBILE MENU DRAWER -->
<dialog id="drv-mobile-menu-drawer" class="drv-dialog drv-dialog--drawer-left" aria-labelledby="mobile-menu-title">
  <div class="drv-dialog__head">
    <h2 id="mobile-menu-title" class="u-text-base u-bold">Danh mục Gạch DRAVIT</h2>
    <button type="button" class="drv-btn drv-btn--sm drv-btn--ghost drv-btn--icon" data-drv-action="dialog-close" aria-label="Đóng menu">
      <i class="ph ph-x drv-icon" aria-hidden="true"></i>
    </button>
  </div>
  <div class="drv-dialog__body drv-mob-nav">
    <!-- Nhóm 1: Theo công năng (Cấp 1) -->
    <div class="drv-mob-group">
      <span class="drv-mob-group__title">01. Theo công năng</span>

      <!-- Cấp 2: Gạch Lát Nền -->
      <details class="drv-mob-accordion" open>
        <summary class="drv-mob-accordion__summary">
          <span class="l-cluster l-cluster--xs">
            <i class="ph ph-grid-four drv-icon" aria-hidden="true"></i>
            <span>Gạch Lát Nền</span>
          </span>
          <span class="l-cluster l-cluster--xs">
            <span class="drv-mega__badge">180</span>
            <i class="ph ph-caret-down drv-mob-accordion__caret" aria-hidden="true"></i>
          </span>
        </summary>
        <div class="drv-mob-accordion__body">
          <!-- Cấp 3: Phân loại chi tiết -->
          <div class="drv-mob-sublist">
            <a href="02-catalog-collection.html?kich-thuoc=80x80" class="drv-mob-sublink">
              <span>Lát nền phòng khách 80x80</span>
              <span class="u-text-xs u-text-muted u-tabular">180</span>
            </a>
            <a href="02-catalog-collection.html?kich-thuoc=60x120" class="drv-mob-sublink">
              <span>Khổ lớn Big Slab 60x120</span>
              <span class="u-text-xs u-text-muted u-tabular">78</span>
            </a>
            <a href="02-catalog-collection.html?be-mat=chong-tron" class="drv-mob-sublink">
              <span>Men Matt chống trơn R10-R11</span>
              <span class="u-text-xs u-text-muted u-tabular">65</span>
            </a>
            <a href="02-catalog-collection.html?van=gia-go" class="drv-mob-sublink">
              <span>Thanh gỗ Porcelain 15x90</span>
              <span class="u-text-xs u-text-muted u-tabular">38</span>
            </a>
            <a href="02-catalog-collection.html?nhom=lat-nen" class="drv-mob-viewall">
              <span>Xem tất cả gạch lát nền</span>
              <i class="ph ph-arrow-right" aria-hidden="true"></i>
            </a>
          </div>
        </div>
      </details>

      <!-- Cấp 2: Gạch Ốp Tường -->
      <details class="drv-mob-accordion">
        <summary class="drv-mob-accordion__summary">
          <span class="l-cluster l-cluster--xs">
            <i class="ph ph-wall drv-icon" aria-hidden="true"></i>
            <span>Gạch Ốp Tường</span>
          </span>
          <span class="l-cluster l-cluster--xs">
            <span class="drv-mega__badge">142</span>
            <i class="ph ph-caret-down drv-mob-accordion__caret" aria-hidden="true"></i>
          </span>
        </summary>
        <div class="drv-mob-accordion__body">
          <div class="drv-mob-sublist">
            <a href="02-catalog-collection.html?kich-thuoc=30x60" class="drv-mob-sublink">
              <span>Gạch ốp 30x60 mờ &amp; bóng</span>
              <span class="u-text-xs u-text-muted u-tabular">95</span>
            </a>
            <a href="02-catalog-collection.html?loai=subway" class="drv-mob-sublink">
              <span>Gạch thẻ Subway Mosaic</span>
              <span class="u-text-xs u-text-muted u-tabular">45</span>
            </a>
            <a href="02-catalog-collection.html?loai=bong-gio" class="drv-mob-sublink">
              <span>Gạch bông gió mỹ thuật</span>
              <span class="u-text-xs u-text-muted u-tabular">22</span>
            </a>
            <a href="02-catalog-collection.html?nhom=op-tuong" class="drv-mob-viewall">
              <span>Xem tất cả gạch ốp tường</span>
              <i class="ph ph-arrow-right" aria-hidden="true"></i>
            </a>
          </div>
        </div>
      </details>

      <!-- Cấp 2: Đá Nung Kết Big Slab -->
      <details class="drv-mob-accordion">
        <summary class="drv-mob-accordion__summary">
          <span class="l-cluster l-cluster--xs">
            <i class="ph ph-sparkle drv-icon" aria-hidden="true"></i>
            <span>Đá Nung Kết Big Slab</span>
          </span>
          <span class="l-cluster l-cluster--xs">
            <span class="drv-mega__badge">29</span>
            <i class="ph ph-caret-down drv-mob-accordion__caret" aria-hidden="true"></i>
          </span>
        </summary>
        <div class="drv-mob-accordion__body">
          <div class="drv-mob-sublist">
            <a href="02-catalog-collection.html?kich-thuoc=120x240" class="drv-mob-sublink">
              <span>Đá tấm lớn 120x240 cm</span>
              <span class="u-text-xs u-text-muted u-tabular">15</span>
            </a>
            <a href="02-catalog-collection.html?loai=mat-bep" class="drv-mob-sublink">
              <span>Mặt bàn bếp chịu nhiệt 1200°C</span>
              <span class="u-text-xs u-text-muted u-tabular">24</span>
            </a>
            <a href="02-catalog-collection.html?loai=vach-tivi" class="drv-mob-sublink">
              <span>Đá ốp vách TV Bookmatch</span>
              <span class="u-text-xs u-text-muted u-tabular">18</span>
            </a>
          </div>
        </div>
      </details>

      <!-- Cấp 2: Sân Vườn & Ngoại Thất -->
      <details class="drv-mob-accordion">
        <summary class="drv-mob-accordion__summary">
          <span class="l-cluster l-cluster--xs">
            <i class="ph ph-tree drv-icon" aria-hidden="true"></i>
            <span>Sân Vườn &amp; Ngoại Thất</span>
          </span>
          <span class="l-cluster l-cluster--xs">
            <span class="drv-mega__badge">52</span>
            <i class="ph ph-caret-down drv-mob-accordion__caret" aria-hidden="true"></i>
          </span>
        </summary>
        <div class="drv-mob-accordion__body">
          <div class="drv-mob-sublist">
            <a href="02-catalog-collection.html?do-day=20mm" class="drv-mob-sublink">
              <span>Gạch dày 20mm chịu lực gara</span>
              <span class="u-text-xs u-text-muted u-tabular">28</span>
            </a>
            <a href="02-catalog-collection.html?be-mat=san-vuon" class="drv-mob-sublink">
              <span>Gạch chống trơn sân vườn R11</span>
              <span class="u-text-xs u-text-muted u-tabular">52</span>
            </a>
            <a href="02-catalog-collection.html?loai=ho-boi" class="drv-mob-sublink">
              <span>Gạch mosaic hồ bơi</span>
              <span class="u-text-xs u-text-muted u-tabular">34</span>
            </a>
          </div>
        </div>
      </details>
    </div>

    <!-- Nhóm 2: Theo không gian (Cấp 1) -->
    <div class="drv-mob-group">
      <span class="drv-mob-group__title">02. Theo không gian</span>

      <!-- Cấp 2: Phòng khách -->
      <details class="drv-mob-accordion">
        <summary class="drv-mob-accordion__summary">
          <span class="l-cluster l-cluster--xs">
            <i class="ph ph-armchair drv-icon" aria-hidden="true"></i>
            <span>Phòng Khách Sang Trọng</span>
          </span>
          <span class="l-cluster l-cluster--xs">
            <span class="drv-mega__badge">124</span>
            <i class="ph ph-caret-down drv-mob-accordion__caret" aria-hidden="true"></i>
          </span>
        </summary>
        <div class="drv-mob-accordion__body">
          <div class="drv-mob-sublist">
            <a href="02-catalog-collection.html?kich-thuoc=80x80" class="drv-mob-sublink">
              <span>Lát nền 80x80 bóng kiếng Ý</span>
              <span class="u-text-xs u-text-muted u-tabular">72</span>
            </a>
            <a href="02-catalog-collection.html?loai=vach-tivi" class="drv-mob-sublink">
              <span>Đá nung kết ốp vách TV</span>
              <span class="u-text-xs u-text-muted u-tabular">24</span>
            </a>
            <a href="02-catalog-collection.html?loai=tham-cnc" class="drv-mob-sublink">
              <span>Gạch thảm CNC tiền sảnh</span>
              <span class="u-text-xs u-text-muted u-tabular">16</span>
            </a>
          </div>
        </div>
      </details>

      <!-- Cấp 2: Phòng tắm / WC -->
      <details class="drv-mob-accordion">
        <summary class="drv-mob-accordion__summary">
          <span class="l-cluster l-cluster--xs">
            <i class="ph ph-drop drv-icon" aria-hidden="true"></i>
            <span>Nhà Tắm / Toilet WC</span>
          </span>
          <span class="l-cluster l-cluster--xs">
            <span class="drv-mega__badge">86</span>
            <i class="ph ph-caret-down drv-mob-accordion__caret" aria-hidden="true"></i>
          </span>
        </summary>
        <div class="drv-mob-accordion__body">
          <div class="drv-mob-sublist">
            <a href="02-catalog-collection.html?be-mat=chong-tron" class="drv-mob-sublink">
              <span>Nền chống trượt Matt R10</span>
              <span class="u-text-xs u-text-muted u-tabular">86</span>
            </a>
            <a href="02-catalog-collection.html?kich-thuoc=30x60" class="drv-mob-sublink">
              <span>Ốp tường 30x60 chống ố</span>
              <span class="u-text-xs u-text-muted u-tabular">54</span>
            </a>
            <a href="02-catalog-collection.html?loai=mosaic" class="drv-mob-sublink">
              <span>Mosaic tắm đứng điểm nhấn</span>
              <span class="u-text-xs u-text-muted u-tabular">30</span>
            </a>
          </div>
        </div>
      </details>

      <!-- Cấp 2: Bếp & Phòng ăn -->
      <details class="drv-mob-accordion">
        <summary class="drv-mob-accordion__summary">
          <span class="l-cluster l-cluster--xs">
            <i class="ph ph-cooking-pot drv-icon" aria-hidden="true"></i>
            <span>Bếp &amp; Phòng Ăn</span>
          </span>
          <span class="l-cluster l-cluster--xs">
            <span class="drv-mega__badge">64</span>
            <i class="ph ph-caret-down drv-mob-accordion__caret" aria-hidden="true"></i>
          </span>
        </summary>
        <div class="drv-mob-accordion__body">
          <div class="drv-mob-sublist">
            <a href="02-catalog-collection.html?loai=mat-bep" class="drv-mob-sublink">
              <span>Đá nung kết mặt bàn bếp</span>
              <span class="u-text-xs u-text-muted u-tabular">24</span>
            </a>
            <a href="02-catalog-collection.html?loai=subway" class="drv-mob-sublink">
              <span>Gạch thẻ backsplash tường bếp</span>
              <span class="u-text-xs u-text-muted u-tabular">42</span>
            </a>
            <a href="02-catalog-collection.html?be-mat=lau-chui" class="drv-mob-sublink">
              <span>Gạch nền men khô dễ lau chùi</span>
              <span class="u-text-xs u-text-muted u-tabular">64</span>
            </a>
          </div>
        </div>
      </details>
    </div>

    <!-- Nhóm 3: Vân & Chất liệu (Cấp 1) -->
    <div class="drv-mob-group">
      <span class="drv-mob-group__title">03. Vân &amp; Chất liệu</span>

      <details class="drv-mob-accordion">
        <summary class="drv-mob-accordion__summary">
          <span class="l-cluster l-cluster--xs">
            <i class="ph ph-paint-brush drv-icon" aria-hidden="true"></i>
            <span>Bề Mặt &amp; Họa Tiết</span>
          </span>
          <i class="ph ph-caret-down drv-mob-accordion__caret" aria-hidden="true"></i>
        </summary>
        <div class="drv-mob-accordion__body">
          <div class="drv-mob-sublist">
            <a href="02-catalog-collection.html?van-gach=van-da-marble" class="drv-mob-sublink">
              <span>Vân đá Marble Ý Calacatta</span>
              <span class="u-text-xs u-text-muted u-tabular">108</span>
            </a>
            <a href="02-catalog-collection.html?van-gach=terrazzo" class="drv-mob-sublink">
              <span>Gạch Terrazzo hạt đá</span>
              <span class="u-text-xs u-text-muted u-tabular">46</span>
            </a>
            <a href="02-catalog-collection.html?van-gach=van-xi-mang" class="drv-mob-sublink">
              <span>Vân xi măng mộc Minimalist</span>
              <span class="u-text-xs u-text-muted u-tabular">62</span>
            </a>
          </div>
        </div>
      </details>

      <details class="drv-mob-accordion">
        <summary class="drv-mob-accordion__summary">
          <span class="l-cluster l-cluster--xs">
            <i class="ph ph-diamonds-four drv-icon" aria-hidden="true"></i>
            <span>Xương Đá &amp; Công Nghệ</span>
          </span>
          <i class="ph ph-caret-down drv-mob-accordion__caret" aria-hidden="true"></i>
        </summary>
        <div class="drv-mob-accordion__body">
          <div class="drv-mob-sublist">
            <a href="02-catalog-collection.html?van-gach=da-nung-ket" class="drv-mob-sublink">
              <span>Đá nung kết Sintered Stone</span>
              <span class="u-text-xs u-text-muted u-tabular">29</span>
            </a>
            <a href="02-catalog-collection.html" class="drv-mob-sublink">
              <span>Xương đá Porcelain (≤ 0,5%)</span>
              <span class="u-text-xs u-text-muted u-tabular">310</span>
            </a>
          </div>
        </div>
      </details>
    </div>

    <!-- Nhóm 4: Theo kích thước chuẩn (Cấp 1) -->
    <div class="drv-mob-group">
      <span class="drv-mob-group__title">04. Kích thước chuẩn</span>

      <details class="drv-mob-accordion">
        <summary class="drv-mob-accordion__summary">
          <span class="l-cluster l-cluster--xs">
            <i class="ph ph-arrows-out drv-icon" aria-hidden="true"></i>
            <span>Khổ Lớn Big Slab</span>
          </span>
          <i class="ph ph-caret-down drv-mob-accordion__caret" aria-hidden="true"></i>
        </summary>
        <div class="drv-mob-accordion__body">
          <div class="drv-mob-sublist">
            <a href="02-catalog-collection.html?kich-thuoc=120x240" class="drv-mob-sublink">
              <span>Đá đại 120x240 cm</span>
              <span class="u-text-xs u-text-muted u-tabular">15</span>
            </a>
            <a href="02-catalog-collection.html?kich-thuoc=60x120" class="drv-mob-sublink">
              <span>Gạch khổ lớn 60x120 cm</span>
              <span class="u-text-xs u-text-muted u-tabular">78</span>
            </a>
          </div>
        </div>
      </details>

      <details class="drv-mob-accordion">
        <summary class="drv-mob-accordion__summary">
          <span class="l-cluster l-cluster--xs">
            <i class="ph ph-bounding-box drv-icon" aria-hidden="true"></i>
            <span>Khổ Tiêu Chuẩn</span>
          </span>
          <i class="ph ph-caret-down drv-mob-accordion__caret" aria-hidden="true"></i>
        </summary>
        <div class="drv-mob-accordion__body">
          <div class="drv-mob-sublist">
            <a href="02-catalog-collection.html?kich-thuoc=80x80" class="drv-mob-sublink">
              <span>Gạch vuông 80x80 cm</span>
              <span class="u-text-xs u-text-muted u-tabular">92</span>
            </a>
            <a href="02-catalog-collection.html?kich-thuoc=60x60" class="drv-mob-sublink">
              <span>Gạch vuông 60x60 cm</span>
              <span class="u-text-xs u-text-muted u-tabular">110</span>
            </a>
            <a href="02-catalog-collection.html?kich-thuoc=30x60" class="drv-mob-sublink">
              <span>Gạch ốp 30x60 cm</span>
              <span class="u-text-xs u-text-muted u-tabular">95</span>
            </a>
            <a href="02-catalog-collection.html?kich-thuoc=15x90" class="drv-mob-sublink">
              <span>Thanh gỗ 15x90 cm</span>
              <span class="u-text-xs u-text-muted u-tabular">38</span>
            </a>
          </div>
        </div>
      </details>
    </div>

    <!-- Nhóm 5: Tiện ích & Hỗ trợ -->
    <div class="drv-mob-group">
      <span class="drv-mob-group__title">Tiện ích &amp; Showroom</span>
      <div class="l-stack l-stack--xs" style="padding-top:4px">
        <a href="07-blog-archive.html" class="drv-mob-sublink">
          <span class="l-cluster l-cluster--xs"><i class="ph ph-book-open drv-icon"></i><span>Cẩm nang chọn gạch &amp; thi công</span></span>
        </a>
        <a href="09-showroom-about-quote.html" class="drv-mob-sublink">
          <span class="l-cluster l-cluster--xs"><i class="ph ph-map-pin drv-icon"></i><span>Showroom &amp; Báo giá công trình</span></span>
        </a>
        <a href="10-policies-search-404.html" class="drv-mob-sublink">
          <span class="l-cluster l-cluster--xs"><i class="ph ph-shield-check drv-icon"></i><span>Chính sách đổi trả &amp; bảo hành lô màu</span></span>
        </a>
      </div>
    </div>
  </div>
  <div class="drv-dialog__foot l-cluster l-cluster--between">
    <a href="tel:0909888777" class="drv-btn drv-btn--outline drv-btn--sm" style="flex:1">
      <i class="ph ph-phone-call drv-icon--xs" aria-hidden="true"></i>
      <span>0909.888.777</span>
    </a>
    <button type="button" class="drv-btn drv-btn--moss drv-btn--sm" data-drv-action="dialog-open" data-drv-target="#drv-quote-modal" style="flex:1">
      <i class="ph ph-file-text drv-icon--xs" aria-hidden="true"></i>
      <span>Báo giá</span>
    </button>
  </div>
</dialog>

<!-- 6. GLOBAL LIGHT SHOWROOM & HOTLINE DOCK -->
<section class="drv-footer-dock" aria-label="Trung tâm tư vấn kỹ thuật, Showroom và Báo giá công trình">
  <div class="l-container">
    <div class="drv-footer-dock__grid">
      <!-- Ô 1: Hotline & Tư vấn Kỹ thuật -->
      <div class="drv-footer-dock__item">
        <div class="l-stack l-stack--xs">
          <div class="drv-footer-dock__head">
            <span class="drv-footer-dock__kicker">
              <i class="ph ph-headset drv-icon--sm" aria-hidden="true"></i>
              <span>Hotline &amp; Tư vấn Kỹ thuật</span>
            </span>
            <span class="drv-footer-dock__status drv-footer-dock__status--online">
              <span class="drv-footer-dock__status-dot" aria-hidden="true"></span>
              <span>8:00 – 21:00</span>
            </span>
          </div>
          <a href="tel:0909888777" class="drv-footer-dock__hotline">0909.888.777</a>
          <p class="drv-footer-dock__desc">Tư vấn chuyên sâu độ chống trượt R9–R13, phối màu kiến trúc &amp; kiểm tra tồn kho cùng lô màu (Shade) tức thì.</p>
        </div>
        <div class="drv-footer-dock__actions">
          <a href="tel:0909888777" class="drv-footer-dock__action drv-footer-dock__action--outline">
            <i class="ph ph-phone-call drv-icon--xs" aria-hidden="true"></i>
            <span>Gọi ngay</span>
          </a>
          <a href="https://zalo.me/0909888777" class="drv-footer-dock__action drv-footer-dock__action--teal">
            <i class="ph ph-chat-circle-dots drv-icon--xs" aria-hidden="true"></i>
            <span>Chat Zalo Kỹ thuật</span>
          </a>
        </div>
      </div>

      <!-- Ô 2: Showroom Flagship KĐT Sala -->
      <div class="drv-footer-dock__item">
        <div class="l-stack l-stack--xs">
          <div class="drv-footer-dock__head">
            <span class="drv-footer-dock__kicker">
              <i class="ph ph-map-pin drv-icon--sm" aria-hidden="true"></i>
              <span>Showroom Flagship Sala</span>
            </span>
            <span class="drv-footer-dock__status drv-footer-dock__status--neutral">Mở cửa T2 – CN</span>
          </div>
          <span class="drv-footer-dock__title">68 Nguyễn Cơ Thạch, KĐT Sala, TP. Thủ Đức</span>
          <p class="drv-footer-dock__desc">Trải nghiệm thực tế 500+ mẫu gạch khổ lớn Big Slab và kiểm tra chất lượng men dưới hệ đèn 3000K – 6500K.</p>
        </div>
        <div class="drv-footer-dock__actions">
          <a href="09-showroom-about-quote.html#showroom" class="drv-footer-dock__action drv-footer-dock__action--outline">
            <i class="ph ph-navigation-arrow drv-icon--xs" aria-hidden="true"></i>
            <span>Chỉ đường Maps</span>
          </a>
          <a href="09-showroom-about-quote.html" class="drv-footer-dock__action drv-footer-dock__action--navy">
            <i class="ph ph-calendar-check drv-icon--xs" aria-hidden="true"></i>
            <span>Đặt lịch xem mẫu</span>
          </a>
        </div>
      </div>

      <!-- Ô 3: Phòng Dự Án & Báo giá Công trình -->
      <div class="drv-footer-dock__item">
        <div class="l-stack l-stack--xs">
          <div class="drv-footer-dock__head">
            <span class="drv-footer-dock__kicker">
              <i class="ph ph-file-text drv-icon--sm" aria-hidden="true"></i>
              <span>Phòng Dự Án &amp; Nhà Thầu</span>
            </span>
            <span class="drv-footer-dock__status drv-footer-dock__status--amber">Chiết khấu B2B</span>
          </div>
          <span class="drv-footer-dock__title">Bóc tách khối lượng theo bản vẽ CAD / PDF</span>
          <p class="drv-footer-dock__desc">Hỗ trợ bóc tách số viên, số thùng nguyên đai, giữ kho cùng lô màu &amp; xuất hóa đơn VAT 100% cho công trình.</p>
        </div>
        <div class="drv-footer-dock__actions">
          <button type="button" class="drv-footer-dock__action drv-footer-dock__action--teal" data-drv-action="dialog-open" data-drv-target="#drv-quote-modal">
            <i class="ph ph-paper-plane-tilt drv-icon--xs" aria-hidden="true"></i>
            <span>Gửi bản vẽ nhận báo giá</span>
          </button>
          <a href="mailto:duan@dravit.vn" class="drv-footer-dock__action drv-footer-dock__action--outline">
            <i class="ph ph-envelope-simple drv-icon--xs" aria-hidden="true"></i>
            <span>duan@dravit.vn</span>
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 7. GLOBAL FOOTER (4-COLUMN E-COMMERCE LAYOUT) -->
<footer class="drv-footer">
  <div class="l-container">
    <div class="drv-footer__grid">
      <!-- Cột 1: Thông tin doanh nghiệp & Showroom -->
      <div class="l-stack l-stack--sm">
        <a href="01-home.html" class="drv-logo" aria-label="DRAVIT - Trang chủ">
          <img src="assets/img/logo-ivory.png" width="140" height="40" loading="lazy" decoding="async" alt="DRAVIT">
        </a>
        <div>
          <h3 class="drv-footer__corp-name">CÔNG TY TNHH VẬT LIỆU KIẾN TRÚC DRAVIT</h3>
          <ul class="drv-footer__contact-list">
            <li>
              <i class="ph ph-identification-card" aria-hidden="true"></i>
              <span>MST: <strong class="u-tabular">0318688999</strong> do Sở KH&amp;ĐT TP.HCM cấp</span>
            </li>
            <li>
              <i class="ph ph-phone-call" aria-hidden="true"></i>
              <span>Hotline: <a href="tel:0909888777" class="u-tabular u-bold">0909.888.777</a> (8:00 – 21:00)</span>
            </li>
            <li>
              <i class="ph ph-envelope-simple" aria-hidden="true"></i>
              <span>Email: <a href="mailto:contact@dravit.vn">contact@dravit.vn</a></span>
            </li>
            <li>
              <i class="ph ph-map-pin" aria-hidden="true"></i>
              <span>Showroom: 68 Nguyễn Cơ Thạch, KĐT Sala, TP. Thủ Đức, TP.HCM</span>
            </li>
          </ul>
        </div>

        <!-- Kênh mạng xã hội & Chứng nhận BCT -->
        <div class="drv-footer__social-group">
          <span class="drv-footer__social-label">Kênh kết nối chính thức</span>
          <div class="drv-footer__socials">
            <a href="https://zalo.me/0909888777" class="drv-footer__social-btn drv-footer__social-btn--zalo" aria-label="Zalo DRAVIT">Zalo</a>
            <a href="https://facebook.com/dravit" class="drv-footer__social-btn" aria-label="Facebook DRAVIT"><i class="ph ph-facebook-logo"></i></a>
            <a href="https://youtube.com/dravit" class="drv-footer__social-btn" aria-label="YouTube DRAVIT"><i class="ph ph-youtube-logo"></i></a>
            <a href="https://tiktok.com/@dravit" class="drv-footer__social-btn" aria-label="TikTok DRAVIT"><i class="ph ph-tiktok-logo"></i></a>
            <a href="https://instagram.com/dravit" class="drv-footer__social-btn" aria-label="Instagram DRAVIT"><i class="ph ph-instagram-logo"></i></a>
          </div>
          <a href="http://online.gov.vn" class="drv-footer__bct-badge" rel="nofollow noopener" target="_blank">
            <i class="ph ph-shield-check drv-icon--xs" aria-hidden="true"></i>
            <span>Đã thông báo Bộ Công Thương</span>
          </a>
        </div>
      </div>

      <!-- Cột 2: Hướng dẫn -->
      <div>
        <h3 class="drv-footer__title">
          <i class="ph ph-shopping-bag-open drv-icon--xs" aria-hidden="true"></i>
          <span>Hướng dẫn</span>
        </h3>
        <ul class="drv-footer__links">
          <li>
            <a href="10-policies-search-404.html#how-to-buy">
              <i class="ph ph-caret-right drv-icon--xs" aria-hidden="true"></i>
              <span>Quy trình mua gạch</span>
            </a>
          </li>
          <li>
            <a href="09-showroom-about-quote.html#sample">
              <i class="ph ph-caret-right drv-icon--xs" aria-hidden="true"></i>
              <span>Hướng dẫn kiểm tra mẫu thực</span>
            </a>
          </li>
          <li>
            <a href="10-policies-search-404.html#payment">
              <i class="ph ph-caret-right drv-icon--xs" aria-hidden="true"></i>
              <span>Thanh toán VietQR &amp; COD</span>
            </a>
          </li>
          <li>
            <a href="10-policies-search-404.html#vat">
              <i class="ph ph-caret-right drv-icon--xs" aria-hidden="true"></i>
              <span>Xuất VAT</span>
            </a>
          </li>
        </ul>
      </div>

      <!-- Cột 3: Chính sách -->
      <div>
        <h3 class="drv-footer__title">
          <i class="ph ph-shield-check drv-icon--xs" aria-hidden="true"></i>
          <span>Chính sách</span>
        </h3>
        <ul class="drv-footer__links">
          <li>
            <a href="10-policies-search-404.html#shipping">
              <i class="ph ph-caret-right drv-icon--xs" aria-hidden="true"></i>
              <span>Giao hàng xe cẩu</span>
            </a>
          </li>
          <li>
            <a href="10-policies-search-404.html#warranty">
              <i class="ph ph-caret-right drv-icon--xs" aria-hidden="true"></i>
              <span>Đổi trả gạch dư &amp; bảo hành lô màu (Shade)</span>
            </a>
          </li>
          <li>
            <a href="10-policies-search-404.html#inspection">
              <i class="ph ph-caret-right drv-icon--xs" aria-hidden="true"></i>
              <span>Đồng kiểm</span>
            </a>
          </li>
          <li>
            <a href="10-policies-search-404.html#privacy">
              <i class="ph ph-caret-right drv-icon--xs" aria-hidden="true"></i>
              <span>Bảo mật</span>
            </a>
          </li>
        </ul>
      </div>

      <!-- Cột 4: Đối tác & Doanh nghiệp -->
      <div>
        <h3 class="drv-footer__title">
          <i class="ph ph-handshake drv-icon--xs" aria-hidden="true"></i>
          <span>Đối tác &amp; Doanh nghiệp</span>
        </h3>
        <ul class="drv-footer__links">
          <li>
            <a href="09-showroom-about-quote.html">
              <i class="ph ph-caret-right drv-icon--xs" aria-hidden="true"></i>
              <span>Giới thiệu thương hiệu</span>
            </a>
          </li>
          <li>
            <a href="09-showroom-about-quote.html#projects">
              <i class="ph ph-caret-right drv-icon--xs" aria-hidden="true"></i>
              <span>Hồ sơ năng lực dự án</span>
            </a>
          </li>
          <li>
            <a href="09-showroom-about-quote.html#partner">
              <i class="ph ph-caret-right drv-icon--xs" aria-hidden="true"></i>
              <span>Đăng ký KTS / Nhà thầu chiết khấu</span>
            </a>
          </li>
          <li>
            <a href="09-showroom-about-quote.html#processing">
              <i class="ph ph-caret-right drv-icon--xs" aria-hidden="true"></i>
              <span>Dịch vụ cắt gạch &amp; bo mài cạnh bậc cầu thang</span>
            </a>
          </li>
          <li>
            <a href="09-showroom-about-quote.html#sample-box">
              <i class="ph ph-caret-right drv-icon--xs" aria-hidden="true"></i>
              <span>Cho mượn mẫu tận công trình</span>
            </a>
          </li>
          <li>
            <a href="09-showroom-about-quote.html#careers">
              <i class="ph ph-caret-right drv-icon--xs" aria-hidden="true"></i>
              <span>Tuyển dụng</span>
            </a>
          </li>
        </ul>
      </div>
    </div>

    <!-- Tầng đáy: Bản quyền & Phương thức thanh toán E-commerce -->
    <div class="drv-footer__bottom">
      <div class="drv-footer__legal">
        <span>© 2026 Công ty TNHH Vật Liệu Kiến Trúc DRAVIT. Bảo lưu mọi quyền.</span>
        <span>Đơn vị giao dịch chuẩn: Viên / Thùng nguyên đai · Giá niêm yết tham chiếu theo m² đã bao gồm VAT.</span>
      </div>
      <div class="drv-footer__trust-bar" aria-label="Phương thức thanh toán">
        <span class="drv-footer__pill">
          <i class="ph ph-qr-code drv-icon--xs" aria-hidden="true"></i>
          <span>VietQR 24/7</span>
        </span>
        <span class="drv-footer__pill">
          <i class="ph ph-package drv-icon--xs" aria-hidden="true"></i>
          <span>COD Đồng kiểm</span>
        </span>
        <span class="drv-footer__pill">
          <i class="ph ph-receipt drv-icon--xs" aria-hidden="true"></i>
          <span>Hóa đơn VAT</span>
        </span>
        <span class="drv-footer__pill">
          <i class="ph ph-truck drv-icon--xs" aria-hidden="true"></i>
          <span>Giao xe cẩu tận nơi</span>
        </span>
      </div>
    </div>
  </div>
</footer>

<!-- 8. MOBILE STICKY BOTTOM BAR (5 CELLS WITH GROUT LINES) -->
<nav class="drv-mobile-bar" aria-label="Thanh công cụ nhanh trên di động">
  <button type="button" class="drv-mobile-bar__btn" data-drv-action="dialog-open" data-drv-target="#drv-mobile-menu-drawer">
    <i class="ph ph-list drv-icon drv-icon--sm" aria-hidden="true"></i>
    <span>Danh mục</span>
  </button>
  <a href="tel:0909888777" class="drv-mobile-bar__btn">
    <i class="ph ph-phone-call drv-icon drv-icon--sm" aria-hidden="true"></i>
    <span>Gọi điện</span>
  </a>
  <a href="https://zalo.me/0909888777" class="drv-mobile-bar__btn">
    <i class="ph ph-chat-circle-dots drv-icon drv-icon--sm" aria-hidden="true"></i>
    <span>Chat Zalo</span>
  </a>
  <button type="button" class="drv-mobile-bar__btn drv-mobile-bar__btn--accent" data-drv-action="dialog-open" data-drv-target="#drv-quote-modal">
    <i class="ph ph-file-text drv-icon drv-icon--sm" aria-hidden="true"></i>
    <span>Báo giá</span>
  </button>
  <button type="button" class="drv-mobile-bar__btn" data-drv-action="dialog-open" data-drv-target="#drv-minicart-drawer">
    <i class="ph ph-shopping-bag drv-icon drv-icon--sm" aria-hidden="true"></i>
    <span>Giỏ (<span data-drv-cart-count>2</span>)</span>
  </button>
</nav>`;

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

  class DrvSiteHeader extends HTMLElement {
    connectedCallback() {
      if (this._initialized) return;
      this._initialized = true;

      this.innerHTML = DRV_HEADER_PARTIAL_FALLBACK;
      syncActiveProtoLink(this);

      const partialUrl = this.dataset.partial || 'partials/header.html';
      if (window.location.protocol.startsWith('http')) {
        fetch(partialUrl)
          .then((res) => (res.ok ? res.text() : ''))
          .then((html) => {
            if (html && html.trim() !== DRV_HEADER_PARTIAL_FALLBACK.trim()) {
              this.innerHTML = html;
              syncActiveProtoLink(this);
              initHeaderNavHover();
              initMegaPreview();
              initScrollObservers();
              window.DravitCatalog?.initInstantSearch?.();
            }
          })
          .catch(() => {});
      }
    }
  }

  class DrvSiteFooter extends HTMLElement {
    connectedCallback() {
      if (this._initialized) return;
      this._initialized = true;

      this.innerHTML = DRV_FOOTER_PARTIAL_FALLBACK;

      const partialUrl = this.dataset.partial || 'partials/footer.html';
      if (window.location.protocol.startsWith('http')) {
        fetch(partialUrl)
          .then((res) => (res.ok ? res.text() : ''))
          .then((html) => {
            if (html && html.trim() !== DRV_FOOTER_PARTIAL_FALLBACK.trim()) {
              this.innerHTML = html;
              initVnAddressSelects();
              initFormValidation();
            }
          })
          .catch(() => {});
      }
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
      aiScript.src = 'assets/js/ai-finder.js?v=20261110c';
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
    document.addEventListener('keydown', (event) => {
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
      window.addEventListener('resize', update);
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

