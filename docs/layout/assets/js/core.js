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
      window.setTimeout(finishClose, 210);
    } else {
      finishClose();
    }
  }

  /* ==========================================================================
     Global Event Delegation on `document` ([data-drv-action])
     ========================================================================== */
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
      const clickedInsideFormOrDrop = event.target.closest('.drv-search__form, .drv-search-drop');
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
        const feedback = actionEl.dataset.drvFeedback || 'Đã chép';
        actionEl.innerHTML = `<i class="ph ph-check" aria-hidden="true"></i> <span>${feedback}</span>`;
        toast(`Đã sao chép mã: ${value}`);
        window.setTimeout(() => {
          actionEl.innerHTML = origLabel;
        }, 1800);
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
          const cur = field.value.trim();
          if (!cur.includes(text)) {
            field.value = cur ? `${cur}; ${text}` : text;
          }
          actionEl.classList.add('is-active');
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

      case 'lightbox-open': {
        event.preventDefault();
        const dialog = document.getElementById('drv-lightbox-dialog');
        if (!dialog) break;
        const imgEl = dialog.querySelector('[data-lightbox-img]');
        const capEl = dialog.querySelector('[data-lightbox-caption]');
        if (imgEl && actionEl.dataset.drvSrc) {
          imgEl.src = actionEl.dataset.drvSrc;
          imgEl.alt = actionEl.dataset.drvCaption || 'Ảnh chi tiết gạch Dravit';
        }
        if (capEl) {
          capEl.textContent = actionEl.dataset.drvCaption || '';
        }
        openDialog('#drv-lightbox-dialog', actionEl);
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

  // Restore scroll when any native <dialog> closes via Escape key
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
      };

      if (provSelect.value) {
        populateWards(provSelect.value);
      }

      provSelect.addEventListener('change', () => {
        populateWards(provSelect.value);
      });
    });
  }

  /* ==========================================================================
     Sticky Header, Sticky Purchase Bar & Blog TOC Scrollspy
     ========================================================================== */
  function initScrollObservers() {
    const header = document.querySelector('.drv-header');
    if (header) {
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
  function initFormValidation() {
    document.querySelectorAll('form[data-drv-validate]').forEach((form) => {
      form.addEventListener('submit', (event) => {
        event.preventDefault();
        let firstInvalid = null;

        form.querySelectorAll('[required]').forEach((field) => {
          const val = field.value.trim();
          let errorMsg = '';

          if (!val) {
            errorMsg = 'Vui lòng nhập thông tin bắt buộc này.';
          } else if (field.type === 'tel' || field.name.includes('phone')) {
            const vnPhoneRegex = /^(0|\+84)(3|5|7|8|9)\d{8}$/;
            const cleanPhone = val.replace(/[\s.-]/g, '');
            if (!vnPhoneRegex.test(cleanPhone)) {
              errorMsg = 'Số điện thoại không hợp lệ (gồm 10 số, ví dụ: 0909123456).';
            }
          }

          const errEl = field.closest('.drv-field')?.querySelector('.drv-field__error');
          if (errorMsg) {
            field.setAttribute('aria-invalid', 'true');
            if (errEl) {
              errEl.textContent = errorMsg;
              errEl.hidden = false;
            }
            if (!firstInvalid) firstInvalid = field;
          } else {
            field.removeAttribute('aria-invalid');
            if (errEl) errEl.hidden = true;
          }
        });

        if (firstInvalid) {
          firstInvalid.focus();
          toast('Vui lòng kiểm tra lại các trường thông tin đánh dấu đỏ.');
          return;
        }

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

  document.addEventListener('DOMContentLoaded', () => {
    initVnAddressSelects();
    initScrollObservers();
    initFormValidation();
    initHeaderNavHover();
    initMegaPreview();
    initScrollStagger();
  });

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

