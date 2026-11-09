/**
 * quote-sheet.js - Bảng Báo Giá Gạch Theo Từng Phòng (Clean, Flat, Multi-Tile Per Room)
 * Kiến trúc Room Card độc lập - 100% Responsive, không lỗi rowspan, không tràn màn hình.
 */
(() => {
  'use strict';

  const root = document.getElementById('qs-rooms-container');
  if (!root) return;

  const catalogEl = document.getElementById('qs-catalog');
  const catalog = JSON.parse(catalogEl?.textContent || '{}');
  const pickerModal = document.getElementById('drv-tile-picker-modal');

  const ROLE_OPTIONS = [
    'Lát nền chính',
    'Ốp tường',
    'Ốp vách Tivi',
    'Trang trí / Điểm nhấn',
    'Ốp tường bếp',
    'Khu vực tắm đứng',
    'Len chân tường',
    'Lát ban công / sân phơi'
  ];

  const TIERS = [
    { min: 100, rate: 8 },
    { min: 30, rate: 5 },
    { min: 0, rate: 0 },
  ];

  const nfInt = new Intl.NumberFormat('vi-VN');
  const nfDec = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 });
  const vnd = (n) => `${nfInt.format(Math.round(n))}đ`;
  const dec = (n) => nfDec.format(n);
  const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);
  const toast = (msg) => window.Dravit?.toast?.(msg);

  let targetPicker = { spaceId: null, slotId: null };
  let idCounter = 200;

  // Dữ liệu ban đầu
  let spacesState = [
    {
      id: 'space-living',
      name: 'Phòng khách',
      type: 'fixed',
      slots: [
        { id: 'slot-l1', role: 'Lát nền chính', sku: 'DRV-8801M', m2: 42 },
        { id: 'slot-l2', role: 'Ốp vách Tivi', sku: 'DRV-61209', m2: 8 }
      ]
    },
    {
      id: 'space-bed',
      name: 'Phòng ngủ Master',
      type: 'fixed',
      slots: [
        { id: 'slot-b1', role: 'Lát nền chính', sku: 'DRV-WD1590', m2: 18 }
      ]
    },
    {
      id: 'space-bath',
      name: 'Nhà tắm / WC',
      type: 'fixed',
      slots: [
        { id: 'slot-t1', role: 'Lát nền chống trượt', sku: 'DRV-TRZ60', m2: 10 },
        { id: 'slot-t2', role: 'Ốp tường chống thấm', sku: null, m2: 24 }
      ]
    },
    {
      id: 'space-kitchen',
      name: 'Phòng bếp',
      type: 'fixed',
      slots: [
        { id: 'slot-k1', role: 'Lát nền chính', sku: 'DRV-CM6060G', m2: 14 }
      ]
    }
  ];

  const getWaste = () => Number(document.querySelector('[data-qs-waste-select]')?.value || 5) / 100;

  function computeTileSlot(slot, waste) {
    if (!slot.sku || !catalog[slot.sku]) {
      return { isEmpty: true, m2Input: 0, boxes: 0, pieces: 0, m2: 0, kg: 0, pricePiece: 0, total: 0 };
    }

    const item = catalog[slot.sku];
    const priceM2 = Number(item.priceM2);
    const areaPiece = Number(item.areaPiece);
    const perBox = Number(item.perBox);
    const kgPiece = Number(item.kgPiece);
    const m2Input = Math.max(0, Number(slot.m2) || 0);

    const need = m2Input * (1 + waste);
    const boxes = need > 0 ? Math.ceil(need / areaPiece / perBox - 1e-9) : 0;
    const pieces = boxes * perBox;
    const pricePiece = Math.round(priceM2 * areaPiece);

    return {
      isEmpty: false,
      m2Input,
      boxes,
      pieces,
      m2: pieces * areaPiece,
      kg: pieces * kgPiece,
      pricePiece,
      total: pieces * pricePiece,
      item,
    };
  }

  const vehicleHint = (kg) => {
    if (kg <= 0) return '—';
    if (kg <= 1000) return 'Xe 1 tấn';
    if (kg <= 2500) return 'Xe 2,5 tấn';
    if (kg <= 5000) return 'Xe 5 tấn';
    return 'Xe 8 tấn / chia chuyến';
  };

  function setText(selector, text) {
    document.querySelectorAll(selector).forEach((el) => {
      el.textContent = text;
    });
  }

  // ==========================================
  // RENDER ROOM CARDS
  // ==========================================
  function renderRooms() {
    root.innerHTML = '';
    const waste = getWaste();

    spacesState.forEach((space) => {
      const card = document.createElement('div');
      card.className = 'drv-room-card';
      card.dataset.spaceId = space.id;

      // Tính tổng nhanh cho phòng này
      let roomM2 = 0;
      let roomTotal = 0;
      let roomTileCount = 0;

      space.slots.forEach((s) => {
        const r = computeTileSlot(s, waste);
        if (!r.isEmpty) {
          roomTileCount += 1;
          roomM2 += r.m2;
          roomTotal += r.total;
        }
      });

      const typeBadge = space.type === 'custom'
        ? '<span class="drv-badge drv-badge--outline u-text-xs">Tùy chỉnh</span>'
        : '<span class="drv-badge drv-badge--champagne u-text-xs">Cố định</span>';

      const summaryText = roomTileCount > 0
        ? `<span class="drv-room-card__summary u-tabular">${roomTileCount} loại gạch · ${dec(roomM2)} m² · ${vnd(roomTotal)}</span>`
        : '<span class="drv-room-card__summary u-text-muted">Chưa chọn mẫu gạch nào</span>';

      // Header phòng
      card.innerHTML = `
        <div class="drv-room-card__head">
          <div class="drv-room-card__title">
            <h3 class="u-text-sm u-bold u-text-navy" style="margin: 0">${esc(space.name)}</h3>
            ${typeBadge}
            ${summaryText}
          </div>
          <div class="drv-room-card__actions">
            <button type="button" class="drv-btn drv-btn--ghost drv-btn--xs" data-qs-action="add-slot-to-space" data-space-id="${space.id}">
              <i class="ph-bold ph-plus drv-icon--xs" aria-hidden="true"></i>
              <span>Thêm loại gạch</span>
            </button>
            ${
              space.type === 'custom'
                ? `<button type="button" class="drv-btn drv-btn--ghost drv-btn--xs u-text-sale" data-qs-action="delete-space" data-space-id="${space.id}" title="Xóa toàn bộ phòng này">
                    <i class="ph ph-trash drv-icon--xs" aria-hidden="true"></i>
                    <span>Xóa phòng</span>
                  </button>`
                : ''
            }
          </div>
        </div>

        <div class="drv-room-card__body">
          <div class="drv-room-slots">
            ${space.slots.map((slot) => renderSlotHtml(space.id, slot, waste)).join('')}
          </div>
        </div>
      `;

      root.appendChild(card);
    });

    recalculateTotals();
  }

  function renderSlotHtml(spaceId, slot, waste) {
    const res = computeTileSlot(slot, waste);
    const item = res.item;

    if (res.isEmpty) {
      // Slot trống
      return `
        <div class="drv-slot-row drv-slot-row--empty" data-slot-id="${slot.id}" data-space-id="${spaceId}">
          <div class="drv-slot-row__role">
            <select class="drv-select drv-select--xs drv-slot-role-select" data-slot-role-select data-space-id="${spaceId}" data-slot-id="${slot.id}">
              ${ROLE_OPTIONS.map((opt) => `<option value="${esc(opt)}" ${opt === slot.role ? 'selected' : ''}>${esc(opt)}</option>`).join('')}
            </select>
          </div>

          <div class="drv-slot-row__empty-action">
            <button type="button" class="drv-btn drv-btn--moss drv-btn--sm" data-qs-action="open-picker" data-space-id="${spaceId}" data-slot-id="${slot.id}">
              <i class="ph-bold ph-plus drv-icon--xs" aria-hidden="true"></i>
              <span>Chọn mẫu gạch</span>
            </button>
            <span class="u-text-xs u-text-muted">Chưa chọn gạch cho vị trí này</span>
          </div>

          <div class="drv-slot-row__remove">
            <button type="button" class="drv-btn-icon-subtle" data-qs-action="delete-slot" data-space-id="${spaceId}" data-slot-id="${slot.id}" title="Xóa vị trí gạch này">
              <i class="ph ph-x drv-icon--xs" aria-hidden="true"></i>
            </button>
          </div>
        </div>
      `;
    }

    // Slot đã chọn gạch
    return `
      <div class="drv-slot-row" data-slot-id="${slot.id}" data-space-id="${spaceId}" data-sku="${slot.sku}">
        <!-- Cột 1: Vai trò & Sản phẩm -->
        <div class="drv-slot-row__main">
          <div class="drv-slot-row__role">
            <select class="drv-select drv-select--xs drv-slot-role-select" data-slot-role-select data-space-id="${spaceId}" data-slot-id="${slot.id}">
              ${ROLE_OPTIONS.map((opt) => `<option value="${esc(opt)}" ${opt === slot.role ? 'selected' : ''}>${esc(opt)}</option>`).join('')}
            </select>
          </div>

          <div class="drv-slot-row__product">
            <img src="${item.img}" width="52" height="52" decoding="async" alt="${esc(item.name)}" class="drv-slot-row__thumb">
            <div class="drv-slot-row__info">
              <div class="l-cluster l-cluster--xs">
                <span class="drv-slot-row__sku">${esc(slot.sku)}</span>
                <span class="drv-slot-row__color">
                  <span class="drv-swatch-dot" style="background: ${item.hex}" aria-hidden="true"></span>
                  <span>${esc(item.color)}</span>
                </span>
                <span class="drv-badge drv-badge--champagne u-text-xs">${esc(item.size)}</span>
              </div>
              <a href="03-product-single.html" class="drv-slot-row__name">${esc(item.name)}</a>
              <div class="l-cluster l-cluster--xs" style="margin-top: 2px">
                <button type="button" class="drv-btn-text u-text-xs u-text-moss u-bold" data-qs-action="open-picker" data-space-id="${spaceId}" data-slot-id="${slot.id}">
                  Đổi mẫu khác
                </button>
                <span class="u-text-muted">·</span>
                <button type="button" class="drv-btn-text u-text-xs u-text-muted" data-qs-action="clear-tile" data-space-id="${spaceId}" data-slot-id="${slot.id}">
                  Gỡ gạch
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Cột 2: Đơn giá -->
        <div class="drv-slot-row__price">
          <span class="u-text-xs u-text-muted u-only-mobile">Đơn giá:</span>
          <strong class="u-text-navy">${vnd(item.priceM2)}<small class="u-text-xs u-text-muted">/m²</small></strong>
          <span class="u-text-xs u-text-muted u-hide-mobile">${vnd(res.pricePiece)}/viên</span>
        </div>

        <!-- Cột 3: Khối lượng m² -->
        <div class="drv-slot-row__qty">
          <div class="drv-qs-qty">
            <label for="qty-${slot.id}" class="u-sr-only">Diện tích m²</label>
            <input id="qty-${slot.id}" type="number" class="drv-qs-qty__input u-tabular"
                   value="${slot.m2}" min="0" step="0.5" inputmode="decimal"
                   data-slot-qty-input data-space-id="${spaceId}" data-slot-id="${slot.id}">
            <span class="drv-qs-qty__unit">m²</span>
          </div>
          <span class="drv-slot-row__conv u-tabular">
            ${res.boxes > 0 ? `${res.boxes} thùng · ${res.pieces} viên · ${dec(res.m2)} m²` : 'Nhập diện tích'}
          </span>
        </div>

        <!-- Cột 4: Thành tiền -->
        <div class="drv-slot-row__total">
          <span class="u-text-xs u-text-muted u-only-mobile">Thành tiền:</span>
          <strong class="drv-slot-row__total-val u-tabular">${vnd(res.total)}</strong>
        </div>

        <!-- Cột 5: Xóa vị trí -->
        <div class="drv-slot-row__remove">
          <button type="button" class="drv-btn-icon-subtle" data-qs-action="delete-slot" data-space-id="${spaceId}" data-slot-id="${slot.id}" title="Xóa dòng gạch này">
            <i class="ph ph-trash drv-icon--xs" aria-hidden="true"></i>
          </button>
        </div>
      </div>
    `;
  }

  // ==========================================
  // TÍNH TỔNG VÀ CHIẾT KHẤU
  // ==========================================
  function recalculateTotals() {
    const waste = getWaste();
    let totalM2 = 0;
    let totalBoxes = 0;
    let totalPieces = 0;
    let totalKg = 0;
    let subtotal = 0;
    let configuredSlots = 0;
    const exportItems = [];

    // Duyệt qua các phòng
    spacesState.forEach((space) => {
      space.slots.forEach((slot) => {
        const res = computeTileSlot(slot, waste);
        if (!res.isEmpty) {
          configuredSlots += 1;
          totalM2 += res.m2;
          totalBoxes += res.boxes;
          totalPieces += res.pieces;
          totalKg += res.kg;
          subtotal += res.total;

          exportItems.push({
            spaceName: space.name,
            role: slot.role,
            sku: slot.sku,
            name: res.item.name,
            size: res.item.size,
            color: res.item.color,
            thumb: res.item.img || res.item.thumb || 'assets/img/tile-marble-calacatta.svg',
            price: `${vnd(res.item.priceM2)}/m²`,
            qtyText: `${res.boxes} thùng · ${res.pieces} viên · ${dec(res.m2)} m²`,
            shortQty: `${dec(res.m2)} m² (${res.boxes} thùng)`,
            total: res.total,
          });
        }
      });
    });

    // Duyệt qua vật tư phụ
    document.querySelectorAll('.drv-pack-item').forEach((itemEl) => {
      const sku = itemEl.dataset.qsPackSku;
      const price = Number(itemEl.dataset.price || 0);
      const unit = itemEl.dataset.unit || '';
      const kgPiece = Number(itemEl.dataset.kg || 0);
      const qty = Math.max(0, Math.round(Number(itemEl.querySelector('[data-pack-qty-input]')?.value) || 0));
      const lineTotal = qty * price;

      totalKg += qty * kgPiece;
      subtotal += lineTotal;

      const subtotalEl = itemEl.querySelector('[data-pack-subtotal]');
      if (subtotalEl) subtotalEl.textContent = vnd(lineTotal);

      if (qty > 0) {
        exportItems.push({
          spaceName: 'Vật tư phụ',
          role: 'Thi công đồng bộ',
          sku,
          name: itemEl.querySelector('.drv-pack-item__name')?.textContent || sku,
          thumb: itemEl.querySelector('.drv-pack-item__thumb')?.getAttribute('src') || 'assets/img/tile-grout-pack.svg',
          size: '',
          color: '',
          price: `${vnd(price)}/${unit}`,
          qtyText: `${qty} ${unit}`,
          shortQty: `${qty} ${unit}`,
          total: lineTotal,
        });
      }
    });

    // Chiết khấu sản lượng
    const tier = TIERS.find((t) => totalM2 >= t.min) || TIERS[TIERS.length - 1];
    const discount = Math.round((subtotal * tier.rate) / 100);
    const grand = subtotal - discount;

    setText('[data-qs-total-m2]', `${dec(totalM2)} m²`);
    setText('[data-qs-total-boxes]', `${totalBoxes} thùng`);
    setText('[data-qs-total-kg]', `${nfInt.format(Math.round(totalKg))} kg · ${vehicleHint(totalKg)}`);
    setText('[data-qs-subtotal]', vnd(subtotal));
    setText('[data-qs-rate]', `${tier.rate}%`);
    setText('[data-qs-discount]', discount > 0 ? `-${vnd(discount)}` : '0đ');
    setText('[data-qs-grand]', vnd(grand));

    // Cập nhật thanh tiến trình chiết khấu
    const tierStatusEl = document.querySelector('[data-qs-tier-status]');
    if (tierStatusEl) {
      const nextTier = [...TIERS].reverse().find((t) => t.min > totalM2);
      if (nextTier) {
        tierStatusEl.innerHTML = `Đang giảm <strong>${tier.rate}%</strong> · Thêm <strong>${dec(nextTier.min - totalM2)} m²</strong> để đạt mức giảm <strong>${nextTier.rate}%</strong>`;
      } else {
        tierStatusEl.innerHTML = `Đạt mức giảm cao nhất: <strong>${tier.rate}%</strong> (Công trình trên 100 m²)`;
      }
    }

    renderLeadSummary(exportItems, grand);
    return { exportItems, totalM2, totalBoxes, totalPieces, totalKg, subtotal, discount, grand, tier, waste };
  }

  function renderLeadSummary(items, grand) {
    const list = document.querySelector('[data-qs-lead-list]');
    if (!list) return;

    if (items.length === 0) {
      list.innerHTML = '<div class="u-text-center u-text-muted u-text-xs" style="padding: 12px">Chưa chọn vị trí gạch nào</div>';
    } else {
      const maxPreview = 3;
      const visible = items.slice(0, maxPreview);
      const remaining = items.length - maxPreview;

      const itemsHtml = visible
        .map(
          (it) => `
        <div class="drv-qs-lead-item">
          <img src="${esc(it.thumb || 'assets/img/tile-marble-calacatta.svg')}" alt="${esc(it.name)}" class="drv-qs-lead-item__thumb" loading="lazy">
          <div class="drv-qs-lead-item__info">
            <strong class="drv-qs-lead-item__name">${esc(it.name)}</strong>
            <span class="drv-qs-lead-item__meta">${esc(it.spaceName)} · ${esc(it.role)}${it.size ? ` · ${esc(it.size)}` : ''}</span>
          </div>
          <div class="drv-qs-lead-item__qty u-tabular">
            <span class="drv-qs-lead-item__qty-val">${esc(it.shortQty || it.qtyText)}</span>
            <strong class="drv-qs-lead-item__price">${vnd(it.total)}</strong>
          </div>
        </div>`
        )
        .join('');

      const moreBtnHtml =
        remaining > 0
          ? `<button type="button" class="drv-qs-lead__more-btn" data-qs-action="view-all-quote">
              <i class="ph-bold ph-arrow-up-right drv-icon--xs" aria-hidden="true"></i>
              <span>Xem tất cả ${items.length} vị trí trên bảng báo giá &rarr;</span>
            </button>`
          : '';

      list.innerHTML = itemsHtml + moreBtnHtml;
    }

    setText('[data-qs-lead-meta]', `${items.length} vị trí đã chọn`);
    setText('[data-qs-lead-total]', vnd(grand));

    const payload = document.querySelector('[data-qs-lead-payload]');
    if (payload) {
      payload.value = JSON.stringify({
        code: document.querySelector('[data-qs-code]')?.textContent.trim(),
        project: document.querySelector('[data-qs-project]')?.value.trim(),
        waste: getWaste(),
        items,
      });
    }
  }

  // ==========================================
  // MODAL PICKER CONTROLLER
  // ==========================================
  function openPicker(spaceId, slotId) {
    targetPicker = { spaceId, slotId };
    const space = spacesState.find((s) => s.id === spaceId);
    const slot = space?.slots.find((sl) => sl.id === slotId);

    const titleEl = pickerModal?.querySelector('[data-picker-target-name]');
    if (titleEl) {
      titleEl.textContent = `${space?.name || ''} – ${slot?.role || ''}`;
    }

    const searchInput = pickerModal?.querySelector('[data-picker-search]');
    if (searchInput) searchInput.value = '';

    pickerModal?.querySelectorAll('[data-picker-filter-size]').forEach((b) => {
      b.classList.toggle('is-active', b.dataset.pickerFilterSize === 'all');
    });

    renderPickerGrid();
    window.Dravit?.openDialog?.('#drv-tile-picker-modal') || pickerModal?.showModal();
  }

  function renderPickerGrid() {
    const grid = pickerModal?.querySelector('[data-picker-grid]');
    if (!grid) return;

    const query = (pickerModal.querySelector('[data-picker-search]')?.value || '').trim().toLowerCase();
    const activeSizeChip = pickerModal.querySelector('[data-picker-filter-size].is-active');
    const sizeFilter = activeSizeChip ? activeSizeChip.dataset.pickerFilterSize : 'all';

    const space = spacesState.find((s) => s.id === targetPicker.spaceId);
    const currentSlot = space?.slots.find((sl) => sl.id === targetPicker.slotId);
    const currentSku = currentSlot?.sku;

    const entries = Object.entries(catalog).filter(([sku, item]) => {
      if (item.kind !== 'tile') return false;
      if (sizeFilter !== 'all' && item.sizeFilter !== sizeFilter) return false;
      if (query) {
        const matchSku = sku.toLowerCase().includes(query);
        const matchName = item.name.toLowerCase().includes(query);
        const matchColor = (item.color || '').toLowerCase().includes(query);
        if (!matchSku && !matchName && !matchColor) return false;
      }
      return true;
    });

    setText('[data-picker-count]', String(entries.length));

    if (entries.length === 0) {
      grid.innerHTML = '<div style="grid-column: 1 / -1; padding: 24px; text-align: center; color: var(--c-text-muted)">Không tìm thấy mẫu gạch phù hợp</div>';
      return;
    }

    grid.innerHTML = entries
      .map(([sku, item]) => {
        const isCurrent = sku === currentSku;
        return `
        <div class="drv-picker-card ${isCurrent ? 'is-selected' : ''}" data-picker-sku="${sku}">
          <div class="drv-picker-card__thumb">
            <img src="${item.img}" width="80" height="80" loading="lazy" decoding="async" alt="${esc(item.name)}">
            <span class="drv-picker-card__size-badge">${esc(item.size)}</span>
          </div>
          <div class="drv-picker-card__body">
            <div class="drv-picker-card__head">
              <span class="drv-picker-card__sku">${esc(sku)}</span>
              <span class="drv-picker-card__color">
                <span class="drv-swatch-dot" style="background: ${item.hex}" aria-hidden="true"></span>
                <span>${esc(item.color)}</span>
              </span>
            </div>
            <strong class="drv-picker-card__title">${esc(item.name)}</strong>
            <div class="drv-picker-card__foot">
              <div class="drv-picker-card__price">
                <strong>${vnd(item.priceM2)}</strong><small>/m²</small>
              </div>
              <button type="button" class="drv-btn ${isCurrent ? 'drv-btn--outline' : 'drv-btn--moss'} drv-btn--xs" data-picker-select-sku="${sku}">
                ${isCurrent ? 'Đang chọn' : 'Chọn mẫu này'}
              </button>
            </div>
          </div>
        </div>
      `;
      })
      .join('');
  }

  function selectTileForCurrentTarget(sku) {
    const space = spacesState.find((s) => s.id === targetPicker.spaceId);
    const slot = space?.slots.find((sl) => sl.id === targetPicker.slotId);
    if (!slot) return;

    slot.sku = sku;
    if (!slot.m2 || slot.m2 <= 0) {
      slot.m2 = 15;
    }

    if (pickerModal) {
      window.Dravit?.closeDialog?.(pickerModal) || pickerModal.close();
    }

    renderRooms();
    toast(`Đã chọn ${sku} cho ${space.name} (${slot.role}).`);
  }

  // ==========================================
  // THÊM / XÓA PHÒNG & SLOT
  // ==========================================
  function addSpace(name) {
    const cleanName = (name || '').trim();
    if (!cleanName) return;

    idCounter += 1;
    const newSpace = {
      id: `space-custom-${idCounter}`,
      name: cleanName,
      type: 'custom',
      slots: [
        { id: `slot-c-${idCounter}-1`, role: 'Lát nền chính', sku: null, m2: 15 }
      ]
    };

    spacesState.push(newSpace);
    renderRooms();
    toast(`Đã thêm phòng: "${cleanName}".`);

    // Tự động mở picker cho slot đầu tiên của phòng mới
    openPicker(newSpace.id, newSpace.slots[0].id);
  }

  function deleteSpace(spaceId) {
    spacesState = spacesState.filter((s) => s.id !== spaceId);
    renderRooms();
    toast('Đã xóa không gian khỏi bảng dự toán.');
  }

  function addSlotToSpace(spaceId) {
    const space = spacesState.find((s) => s.id === spaceId);
    if (!space) return;

    idCounter += 1;
    const defaultRole = space.slots.length === 1 ? 'Ốp tường' : 'Trang trí / Điểm nhấn';
    const newSlot = {
      id: `slot-extra-${idCounter}`,
      role: defaultRole,
      sku: null,
      m2: 12
    };

    space.slots.push(newSlot);
    renderRooms();
    toast(`Đã thêm vị trí gạch vào ${space.name}.`);

    // Mở luôn picker cho vị trí mới
    openPicker(space.id, newSlot.id);
  }

  function deleteSlot(spaceId, slotId) {
    const space = spacesState.find((s) => s.id === spaceId);
    if (!space) return;

    if (space.slots.length <= 1) {
      // Nếu chỉ còn 1 slot duy nhất trong phòng thì chỉ gỡ gạch, không xóa slot
      space.slots[0].sku = null;
      space.slots[0].m2 = 0;
      renderRooms();
      toast(`Đã gỡ gạch khỏi ${space.name}.`);
    } else {
      space.slots = space.slots.filter((sl) => sl.id !== slotId);
      renderRooms();
      toast('Đã xóa vị trí gạch.');
    }
  }

  function clearTile(spaceId, slotId) {
    const space = spacesState.find((s) => s.id === spaceId);
    const slot = space?.slots.find((sl) => sl.id === slotId);
    if (slot) {
      slot.sku = null;
      slot.m2 = 0;
      renderRooms();
      toast(`Đã gỡ mẫu gạch khỏi ${space.name}.`);
    }
  }

  // ==========================================
  // XUẤT FILE PDF · CSV · ZALO
  // ==========================================
  function printPdf() {
    const res = recalculateTotals();
    const tbody = document.querySelector('[data-qs-print-rows]');
    if (!tbody) return;

    tbody.innerHTML = res.exportItems
      .map(
        (it, i) => `
      <tr>
        <td>${i + 1}</td>
        <td><strong>${esc(it.spaceName)}</strong><br><small>${esc(it.role)}</small></td>
        <td><strong>${esc(it.sku)}</strong><br>${esc(it.name)}</td>
        <td>${esc(it.size)}</td>
        <td>${esc(it.color)}</td>
        <td>${esc(it.price)}</td>
        <td>${esc(it.qtyText)}</td>
        <td style="text-align: right;"><strong>${vnd(it.total)}</strong></td>
      </tr>
    `
      )
      .join('');

    setText('[data-qs-print-code]', document.querySelector('[data-qs-code]')?.textContent || '');
    setText('[data-qs-print-date]', document.querySelector('[data-qs-date]')?.textContent || '');
    setText('[data-qs-print-project]', document.querySelector('[data-qs-project]')?.value || '—');
    setText('[data-qs-print-waste]', `${Math.round(res.waste * 100)}%`);
    setText('[data-qs-print-m2]', `${dec(res.totalM2)} m²`);
    setText('[data-qs-print-boxes]', `${res.totalBoxes} thùng`);
    setText('[data-qs-print-kg]', `${nfInt.format(Math.round(res.totalKg))} kg · ${vehicleHint(res.totalKg)}`);
    setText('[data-qs-print-subtotal]', vnd(res.subtotal));
    setText('[data-qs-print-rate]', `${res.tier.rate}%`);
    setText('[data-qs-print-discount]', res.discount > 0 ? `-${vnd(res.discount)}` : '0đ');
    setText('[data-qs-print-grand]', vnd(res.grand));

    window.print();
  }

  function exportCsv() {
    const res = recalculateTotals();
    const q = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const head = ['STT', 'Không gian', 'Hạng mục', 'Mã SKU', 'Tên sản phẩm', 'Kích thước', 'Màu chính', 'Đơn giá', 'Khối lượng', 'Thành tiền'];
    const rows = res.exportItems.map((it, i) => [
      i + 1,
      it.spaceName,
      it.role,
      it.sku,
      it.name,
      it.size,
      it.color,
      it.price,
      it.qtyText,
      vnd(it.total),
    ]);
    const foot = [
      [],
      ['', 'Tổng diện tích gạch', `${dec(res.totalM2)} m²`],
      ['', 'Tạm tính giá niêm yết', vnd(res.subtotal)],
      ['', `Chiết khấu (${res.tier.rate}%)`, res.discount > 0 ? `-${vnd(res.discount)}` : '0đ'],
      ['', 'Tổng tạm tính', vnd(res.grand)],
    ];
    const csv = [head, ...rows, ...foot].map((r) => r.map(q).join(',')).join('\r\n');
    const blob = new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = Object.assign(document.createElement('a'), {
      href: url,
      download: `du-toan-gach-dravit-${(document.querySelector('[data-qs-code]')?.textContent || '').replace(/\W/g, '')}.csv`,
    });
    document.body.append(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
    toast('Đã xuất file Excel (.CSV).');
  }

  async function copyZalo() {
    const res = recalculateTotals();
    const project = document.querySelector('[data-qs-project]')?.value.trim() || '';
    const text = [
      `DỰ TOÁN GẠCH THEO PHÒNG DRAVIT ${document.querySelector('[data-qs-code]')?.textContent || ''}${project ? ` – ${project}` : ''}`,
      ...res.exportItems.map((it, i) => `${i + 1}. [${it.spaceName} - ${it.role}] ${it.sku} ${it.name} (${it.size}) – ${it.qtyText} – ${vnd(it.total)}`),
      `Tổng dự toán: ${vnd(res.grand)} (Chiết khấu -${res.tier.rate}%, chưa gồm cước xe)`,
    ].join('\n');

    try {
      await (window.Dravit?.copyToClipboard ?? ((t) => navigator.clipboard.writeText(t)))(text);
      toast('Đã chép nội dung gửi Zalo.');
    } catch {
      toast('Không chép được, vui lòng thử lại.');
    }
  }

  // ==========================================
  // SỰ KIỆN TOÀN CỤC
  // ==========================================
  document.addEventListener('input', (e) => {
    // Đổi ô m² của slot
    if (e.target.matches('[data-slot-qty-input]')) {
      const space = spacesState.find((s) => s.id === e.target.dataset.spaceId);
      const slot = space?.slots.find((sl) => sl.id === e.target.dataset.slotId);
      if (slot) {
        slot.m2 = Math.max(0, Number(e.target.value) || 0);
        const row = e.target.closest('.drv-slot-row');
        const res = computeTileSlot(slot, getWaste());
        if (row && !res.isEmpty) {
          const convEl = row.querySelector('.drv-slot-row__conv');
          const totalEl = row.querySelector('.drv-slot-row__total-val');
          if (convEl) convEl.textContent = res.boxes > 0 ? `${res.boxes} thùng · ${res.pieces} viên · ${dec(res.m2)} m²` : 'Nhập diện tích';
          if (totalEl) totalEl.textContent = vnd(res.total);
        }
        recalculateTotals();
      }
    }

    // Đổi số lượng vật tư phụ
    if (e.target.matches('[data-pack-qty-input]')) {
      recalculateTotals();
    }

    // Tìm kiếm trong Modal Picker
    if (e.target.matches('[data-picker-search]')) {
      renderPickerGrid();
    }
  });

  document.addEventListener('change', (e) => {
    // Đổi vai trò gạch
    if (e.target.matches('[data-slot-role-select]')) {
      const space = spacesState.find((s) => s.id === e.target.dataset.spaceId);
      const slot = space?.slots.find((sl) => sl.id === e.target.dataset.slotId);
      if (slot) {
        slot.role = e.target.value;
        recalculateTotals();
      }
    }

    // Đổi hao hụt
    if (e.target.matches('[data-qs-waste-select]')) {
      renderRooms();
    }
  });

  document.addEventListener('click', (e) => {
    // Xem tất cả vị trí gạch -> Đóng popup và cuộn về bảng báo giá quote cart
    const viewAllBtn = e.target.closest('[data-qs-action="view-all-quote"]');
    if (viewAllBtn) {
      const dialog = viewAllBtn.closest('dialog');
      if (dialog) {
        window.Dravit?.closeDialog?.(dialog) || dialog.close();
      }
      const target = document.getElementById('qs-rooms-container') || document.getElementById('drv-qs-workspace');
      if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
      return;
    }

    // Mở Modal Picker
    const pickerTrigger = e.target.closest('[data-qs-action="open-picker"]');
    if (pickerTrigger) {
      openPicker(pickerTrigger.dataset.spaceId, pickerTrigger.dataset.slotId);
      return;
    }

    // Chọn gạch trong Modal
    const selectSkuBtn = e.target.closest('[data-picker-select-sku]');
    if (selectSkuBtn) {
      selectTileForCurrentTarget(selectSkuBtn.dataset.pickerSelectSku);
      return;
    }

    // Lọc kích thước trong Modal
    const filterBtn = e.target.closest('[data-picker-filter-size]');
    if (filterBtn) {
      pickerModal?.querySelectorAll('[data-picker-filter-size]').forEach((b) => b.classList.remove('is-active'));
      filterBtn.classList.add('is-active');
      renderPickerGrid();
      return;
    }

    // Thêm slot vào phòng
    const addSlotBtn = e.target.closest('[data-qs-action="add-slot-to-space"]');
    if (addSlotBtn) {
      addSlotToSpace(addSlotBtn.dataset.spaceId);
      return;
    }

    // Gỡ gạch khỏi slot
    const clearBtn = e.target.closest('[data-qs-action="clear-tile"]');
    if (clearBtn) {
      clearTile(clearBtn.dataset.spaceId, clearBtn.dataset.slotId);
      return;
    }

    // Xóa slot
    const delSlotBtn = e.target.closest('[data-qs-action="delete-slot"]');
    if (delSlotBtn) {
      deleteSlot(delSlotBtn.dataset.spaceId, delSlotBtn.dataset.slotId);
      return;
    }

    // Xóa toàn bộ phòng
    const delSpaceBtn = e.target.closest('[data-qs-action="delete-space"]');
    if (delSpaceBtn) {
      deleteSpace(delSpaceBtn.dataset.spaceId);
      return;
    }

    // Thêm phòng từ preset chip
    const presetBtn = e.target.closest('[data-qs-add-preset]');
    if (presetBtn) {
      addSpace(presetBtn.dataset.qsAddPreset);
      return;
    }

    // Top actions
    const actionEl = e.target.closest('[data-qs-action]');
    if (actionEl) {
      switch (actionEl.dataset.qsAction) {
        case 'pdf':
          printPdf();
          break;
        case 'csv':
          exportCsv();
          break;
        case 'copy-zalo':
          copyZalo();
          break;
        default:
          break;
      }
    }
  });

  // Submit form thêm phòng tùy chỉnh
  document.querySelector('[data-qs-custom-room-form]')?.addEventListener('submit', (e) => {
    e.preventDefault();
    const input = document.querySelector('[data-qs-custom-room-input]');
    if (input) {
      addSpace(input.value);
      input.value = '';
    }
  });

  // Khởi tạo
  renderRooms();
})();
