/**
 * Entry trang `/danh-sach-bao-gia` (docs/17, `04.1-quote-cart.html`):
 * quản lý phòng/hạng mục từ QuoteStore, hydrate giá tươi, modal chọn gạch, tổng hợp + chiết khấu dự kiến,
 * In A4, CSV UTF-8 BOM, Copy Zalo, link chia sẻ `?quote=`, đặt mua mẫu có giá và gửi phiếu BOQ.
 * Mọi chuỗi do người dùng nhập chỉ render qua `textContent` (Q-05).
 */
import { closeDialog, copyText, openDialog, renderMiniCartState } from './core.js';
import { qs, qsa, debounce } from './utils/dom.js';
import { formatDecimalVn, formatVnd } from './utils/format.js';
import { calcVolumeDiscount, computeSlot, resolveVehicleHint } from './utils/calc.js';
import { showToast } from './utils/toast.js';
import { endpoint, errorMessage, postProtected, prefetchToken } from './utils/lead-client.js';
import {
  DEFAULT_ROOM_ID,
  DEFAULT_ROOM_NAME,
  MAX_ROOMS,
  addRoom,
  addSlot,
  clearQuote,
  encodeShare,
  findSlot,
  getStats,
  mergeStates,
  moveSlot,
  parseShareParam,
  readState,
  removeRoom,
  removeSlot,
  renameRoom,
  sanitizeCsvCell,
  updateQuote,
  updateSlot,
  writeState,
} from './utils/quote-store.js';

const MODE_LABEL = { m2: 'm²', thung: 'Thùng', vien: 'Viên' };
const QUOTE_LABEL = 'Liên hệ báo giá';
const QUOTE_TOTAL = 'Chờ báo giá';

/** @type {Map<number, any>} */
const productCache = new Map();
const missingIds = new Set();

let cfg = {};
let root = null;
let pickerTarget = { roomId: DEFAULT_ROOM_ID, slotId: '' };
let pickerAbort = null;
let pickerPage = 1;
let lastCalc = null;
let submittedCode = '';

// ---------------------------------------------------------------------------
// Helpers DOM an toàn (không innerHTML cho dữ liệu người dùng)
// ---------------------------------------------------------------------------

function el(tag, attrs = {}, ...children) {
  const node = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs)) {
    if (v === false || v === null || v === undefined) {
      continue;
    }
    if (k === 'class') {
      node.className = v;
    } else if (k === 'text') {
      node.textContent = String(v);
    } else if (k === 'dataset') {
      Object.assign(node.dataset, v);
    } else if (v === true) {
      node.setAttribute(k, '');
    } else {
      node.setAttribute(k, String(v));
    }
  }
  for (const child of children.flat()) {
    if (child === null || child === undefined || child === false) {
      continue;
    }
    node.append(child instanceof Node ? child : document.createTextNode(String(child)));
  }
  return node;
}

function icon(name, extra = 'drv-icon--xs') {
  return el('i', { class: `${name} ${extra}`.trim(), 'aria-hidden': 'true' });
}

function readConfig() {
  try {
    return JSON.parse(qs('#drv-boq-config')?.textContent || '{}');
  } catch {
    return {};
  }
}

function roles() {
  return Array.isArray(cfg.roles) && cfg.roles.length > 0 ? cfg.roles : ['Lát nền chính'];
}

const dec = (n, d = 2) => formatDecimalVn(n, d);

// ---------------------------------------------------------------------------
// Hydrate sản phẩm (`GET /dravit/v1/products?include=`)
// ---------------------------------------------------------------------------

function productsEndpoint() {
  return window.DRV_CONFIG?.productsEndpoint || endpoint('products');
}

async function hydrate(ids) {
  const need = [...new Set(ids)].filter((id) => id > 0 && !productCache.has(id) && !missingIds.has(id));
  for (let i = 0; i < need.length; i += 30) {
    const chunk = need.slice(i, i + 30);
    try {
      const res = await fetch(`${productsEndpoint()}?include=${chunk.join(',')}&per_page=30`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      const data = res.ok ? await res.json() : null;
      const items = Array.isArray(data?.items) ? data.items : [];
      for (const item of items) {
        productCache.set(Number(item.id), item);
      }
      for (const id of chunk) {
        if (!productCache.has(id)) {
          missingIds.add(id);
        }
      }
    } catch {
      // Lỗi mạng: giữ nguyên, hiển thị trạng thái chờ hydrate.
    }
  }
}

function toCalcProduct(p) {
  return {
    areaPiece: Number(p.area_piece) || 0,
    piecesPerBox: Number(p.pieces_per_box) || 1,
    kgPerBox: Number(p.kg_per_box) || 0,
    priceM2: p.price_state === 'quote_only' ? 0 : Number(p.price_m2) || 0,
    pricePiece: p.price_state === 'quote_only' ? null : Number(p.price_piece) || null,
    roundMode: p.round_mode === 'piece' ? 'piece' : 'box',
    qtyMin: Number(p.qty_min) || 1,
    unitLabel: p.unit_label || 'viên',
  };
}

// ---------------------------------------------------------------------------
// Tính toán toàn phiếu (song sinh `Dravit\Quote\BoqService::compute()`)
// ---------------------------------------------------------------------------

export function computeQuote(state, products, config = {}) {
  const totals = {
    total_m2: 0,
    total_boxes: 0,
    total_loose: 0,
    total_kg: 0,
    subtotal_vnd: 0,
    priced_count: 0,
    quote_count: 0,
    missing_count: 0,
    item_count: 0,
  };
  const rooms = state.rooms.map((room) => {
    const lines = room.slots.map((slot) => {
      const product = products.get(slot.product_id);
      if (!product) {
        totals.missing_count += 1;
        return { slot, product: null, res: null };
      }
      const res = computeSlot(toCalcProduct(product), {
        inputMode: slot.input_mode,
        targetM2: slot.target_m2,
        pieces: slot.pieces,
      }, state.waste_pct);
      totals.item_count += 1;
      totals.total_m2 += res.actualM2;
      totals.total_boxes += res.fullBoxes;
      totals.total_loose += res.loosePieces;
      totals.total_kg += res.totalKg;
      if (res.isQuote) {
        totals.quote_count += 1;
      } else {
        totals.priced_count += 1;
        totals.subtotal_vnd += res.totalVnd;
      }
      return { slot, product, res };
    });
    const roomM2 = lines.reduce((s, l) => s + (l.res?.actualM2 || 0), 0);
    const roomVnd = lines.reduce((s, l) => s + (l.res && !l.res.isQuote ? l.res.totalVnd : 0), 0);
    return { room, lines, roomM2, roomVnd };
  });

  totals.total_m2 = Math.round(totals.total_m2 * 100) / 100;
  totals.total_kg = Math.round(totals.total_kg * 10) / 10;
  const disc = calcVolumeDiscount(totals.subtotal_vnd, totals.total_m2, config.tiers);
  const hasSub = totals.subtotal_vnd > 0;
  totals.discount_rate = hasSub ? disc.rate : 0;
  totals.discount_est_vnd = hasSub ? disc.discountVnd : 0;
  totals.grand_est_vnd = totals.subtotal_vnd - totals.discount_est_vnd;
  totals.next_tier = disc.nextTier;
  totals.vehicle_hint = resolveVehicleHint(totals.total_kg, config.vehicles);
  return { rooms, totals };
}

// ---------------------------------------------------------------------------
// Render
// ---------------------------------------------------------------------------

function renderModeToggle(slot, product) {
  const allowed = Array.isArray(product.buy_units) && product.buy_units.length > 0 ? product.buy_units : ['m2', 'thung'];
  const wrap = el('div', { class: 'drv-qs-mode', role: 'group', 'aria-label': 'Nhập khối lượng theo' });
  for (const mode of ['m2', 'thung', 'vien']) {
    if (!allowed.includes(mode) && slot.input_mode !== mode) {
      continue;
    }
    wrap.append(
      el('button', {
        type: 'button',
        'aria-pressed': slot.input_mode === mode ? 'true' : 'false',
        dataset: { qsMode: mode, slotId: slot.id },
        text: MODE_LABEL[mode],
      }),
    );
  }
  return wrap;
}

function qtyValue(slot, product, res) {
  if (slot.input_mode === 'thung') {
    return String(Math.max(1, Math.round((res?.pieces || slot.pieces) / Math.max(1, Number(product.pieces_per_box) || 1))));
  }
  if (slot.input_mode === 'vien') {
    return String(res?.pieces || slot.pieces || 1);
  }
  return slot.target_m2 > 0 ? String(slot.target_m2) : '';
}

function renderRoleSelect(slot) {
  const select = el('select', {
    class: 'drv-select drv-select--xs drv-slot-role-select',
    'aria-label': 'Hạng mục ốp lát',
    dataset: { qsRole: '', slotId: slot.id },
  });
  for (const role of roles()) {
    const opt = el('option', { value: role, text: role });
    opt.selected = role === slot.role;
    select.append(opt);
  }
  return select;
}

let activeMoveSlotId = null;

function renderSlotActions(slot, roomId, state, sku) {
  const wrap = el('div', { class: 'drv-slot-row__actions' });
  if (state.rooms.length >= 2) {
    const isMenuOpen = activeMoveSlotId === slot.id;
    const otherRooms = state.rooms.filter((r) => r.id !== roomId);
    const moveBtn = el(
      'div',
      { class: 'drv-slot-action-move' },
      el(
        'button',
        {
          type: 'button',
          class: `drv-btn-icon-subtle${isMenuOpen ? ' is-active' : ''}`,
          title: 'Chuyển sang phòng khác',
          'aria-label': `Chuyển ${sku} sang phòng khác`,
          'aria-expanded': isMenuOpen ? 'true' : 'false',
          dataset: { qsAction: 'toggle-move-menu', slotId: slot.id },
        },
        icon('ph ph-arrows-left-right drv-icon--xs'),
      ),
      isMenuOpen
        ? el(
            'div',
            { class: 'drv-move-popover', role: 'menu', 'aria-label': 'Chọn phòng cần chuyển' },
            el('div', { class: 'drv-move-popover__title', text: 'Chuyển sang:' }),
            el(
              'div',
              { class: 'drv-move-popover__list' },
              ...otherRooms.map((r) =>
                el(
                  'button',
                  {
                    type: 'button',
                    class: 'drv-move-popover__item',
                    role: 'menuitem',
                    dataset: { qsAction: 'move-to-room', slotId: slot.id, targetRoomId: r.id, targetRoomName: r.name },
                  },
                  icon('ph ph-door drv-icon--xs'),
                  el('span', { text: r.name }),
                ),
              ),
            ),
          )
        : null,
    );
    wrap.append(moveBtn);
  }
  wrap.append(
    el(
      'button',
      {
        type: 'button',
        class: 'drv-btn-icon-subtle',
        'aria-label': `Xóa ${sku}`,
        title: 'Xóa mẫu này',
        dataset: { qsAction: 'delete-slot', slotId: slot.id },
      },
      icon('ph ph-trash'),
    ),
  );
  return wrap;
}

function renderSlotRow(line, roomId, state) {
  const { slot, product, res } = line;
  if (!product) {
    const missing = missingIds.has(slot.product_id);
    return el(
      'div',
      { class: 'drv-slot-row drv-slot-row--empty drv-slot-row--missing', dataset: { slotId: slot.id } },
      el(
        'p',
        { class: 'drv-slot-row__warn' },
        icon('ph-fill ph-warning'),
        el('span', {
          text: missing
            ? `Mẫu ${slot.sku || `#${slot.product_id}`} hiện ngừng kinh doanh. Vui lòng chọn mẫu thay thế hoặc xóa dòng này.`
            : `Đang tải dữ liệu mẫu ${slot.sku || `#${slot.product_id}`}...`,
        }),
      ),
      el(
        'div',
        { class: 'l-cluster l-cluster--xs' },
        el('button', { type: 'button', class: 'drv-btn drv-btn--moss drv-btn--xs', dataset: { qsAction: 'open-picker', roomId, slotId: slot.id }, text: 'Chọn mẫu thay thế' }),
        el('button', { type: 'button', class: 'drv-btn-icon-subtle', 'aria-label': 'Xóa dòng này', dataset: { qsAction: 'delete-slot', slotId: slot.id } }, icon('ph ph-trash')),
      ),
    );
  }

  const unit = product.unit_label || 'viên';
  const isQuote = res.isQuote;
  const qtyId = `qs-qty-${slot.id}`;
  const unitLabel = slot.input_mode === 'm2' ? 'm²' : slot.input_mode === 'thung' ? 'thùng' : unit;
  const swatch = product.swatch_hex ? el('span', { class: 'drv-swatch-dot', style: `background:${product.swatch_hex}`, 'aria-hidden': 'true' }) : null;
  const stockQty = product.stock_qty;
  const stockWarn = Number.isFinite(stockQty) && stockQty !== null && res.pieces > stockQty
    ? el('span', { class: 'drv-slot-row__stock', text: `Kho còn ${stockQty} ${unit}, chuyên viên báo lịch lô mới` })
    : product.price_state === 'out_of_stock'
      ? el('span', { class: 'drv-slot-row__stock', text: 'Tạm hết kho - Báo lịch hàng về' })
      : null;

  return el(
    'div',
    { class: 'drv-slot-row', dataset: { slotId: slot.id } },
    el(
      'div',
      { class: 'drv-slot-row__main' },
      el('div', { class: 'drv-slot-row__role' }, renderRoleSelect(slot)),
      el(
        'div',
        { class: 'drv-slot-row__product' },
        el('img', { src: product.thumb || product.img_primary || '', width: 52, height: 52, alt: '', loading: 'lazy', decoding: 'async', class: 'drv-slot-row__thumb' }),
        el(
          'div',
          { class: 'drv-slot-row__info' },
          el(
            'div',
            { class: 'l-cluster l-cluster--xs' },
            el('span', { class: 'drv-slot-row__sku', text: product.sku }),
            product.color_name ? el('span', { class: 'drv-slot-row__color' }, swatch, el('span', { text: product.color_name })) : null,
            product.size_label ? el('span', { class: 'drv-badge drv-badge--champagne u-text-xs', text: product.size_label }) : null,
          ),
          el('a', { href: product.url, class: 'drv-slot-row__name', text: product.name || product.title }),
          el(
            'div',
            { class: 'l-cluster l-cluster--xs', style: 'margin-top: 4px' },
            el(
              'button',
              {
                type: 'button',
                class: 'drv-slot-row__change-btn',
                dataset: { qsAction: 'open-picker', roomId, slotId: slot.id },
              },
              icon('ph ph-arrows-clockwise drv-icon--xs'),
              el('span', { text: 'Đổi mẫu khác' }),
            ),
          ),
          stockWarn,
        ),
      ),
    ),
    el(
      'div',
      { class: 'drv-slot-row__price' },
      el('span', { class: 'u-text-xs u-text-muted u-only-mobile', text: 'Đơn giá:' }),
      isQuote
        ? el('strong', { class: 'drv-qs-quote-tag' }, icon('ph-fill ph-tag', ''), QUOTE_LABEL)
        : el('strong', { class: 'u-text-navy' }, formatVnd(product.price_m2), el('small', { class: 'u-text-xs u-text-muted', text: '/m²' })),
      isQuote ? null : el('span', { class: 'u-text-xs u-text-muted u-hide-mobile', text: `${formatVnd(res.pricePiece)}/${unit}` }),
    ),
    el(
      'div',
      { class: 'drv-slot-row__qty' },
      el(
        'div',
        { class: 'drv-qs-qty-group' },
        el(
          'div',
          { class: 'drv-qs-qty' },
          el('label', { for: qtyId, class: 'u-sr-only', text: `Khối lượng (${unitLabel})` }),
          el('input', {
            id: qtyId,
            type: 'number',
            class: 'drv-qs-qty__input u-tabular',
            value: qtyValue(slot, product, res),
            min: slot.input_mode === 'm2' ? '0' : '1',
            step: slot.input_mode === 'm2' ? '0.5' : '1',
            inputmode: slot.input_mode === 'm2' ? 'decimal' : 'numeric',
            placeholder: slot.input_mode === 'm2' ? 'Nhập m²' : '0',
            dataset: { qsQty: '', slotId: slot.id },
          }),
        ),
        renderModeToggle(slot, product),
      ),
      el('span', {
        class: 'drv-slot-row__conv u-tabular',
        text: res.pieces > 0 ? `→ ${res.boxText} · ${dec(res.actualM2)} m²${res.totalKg > 0 ? ` · ${dec(res.totalKg, 0)} kg` : ''}` : 'Nhập diện tích',
      }),
    ),
    el(
      'div',
      { class: 'drv-slot-row__total' },
      el('span', { class: 'u-text-xs u-text-muted u-only-mobile', text: 'Thành tiền:' }),
      el('strong', { class: `drv-slot-row__total-val u-tabular${isQuote ? ' is-quote' : ''}`, text: isQuote ? QUOTE_TOTAL : formatVnd(res.totalVnd) }),
    ),
    renderSlotActions(slot, roomId, state, product.sku),
  );
}

function renderRoomCard(entry, state) {
  const { room, lines, roomM2, roomVnd } = entry;
  const filled = lines.filter((l) => l.res).length;
  const quoteInRoom = lines.some((l) => l.res?.isQuote);
  const summary = filled > 0
    ? `${filled} loại gạch · ${dec(roomM2)} m²${roomVnd > 0 ? ` · ${formatVnd(roomVnd)}` : ''}${quoteInRoom ? ' + báo giá riêng' : ''}`
    : 'Chưa chọn mẫu gạch nào';

  const head = el(
    'div',
    { class: 'drv-room-card__head' },
    el(
      'div',
      { class: 'drv-room-card__title' },
      el('h3', { class: 'u-text-sm u-bold u-text-navy', style: 'margin:0', text: room.name }),
      room.id === DEFAULT_ROOM_ID ? el('span', { class: 'drv-badge drv-badge--champagne u-text-xs', text: 'Thêm nhanh' }) : null,
      el('span', { class: 'drv-room-card__summary u-tabular', text: summary }),
    ),
    el(
      'div',
      { class: 'drv-room-card__actions' },
      el('button', { type: 'button', class: 'drv-btn drv-btn--ghost drv-btn--xs', dataset: { qsAction: 'open-picker', roomId: room.id } }, icon('ph-bold ph-plus'), el('span', { text: 'Thêm loại gạch' })),
      el('button', { type: 'button', class: 'drv-btn drv-btn--ghost drv-btn--xs', dataset: { qsAction: 'rename-room', roomId: room.id } }, icon('ph ph-pencil-simple'), el('span', { text: 'Đổi tên phòng' })),
      el('button', { type: 'button', class: 'drv-btn drv-btn--ghost drv-btn--xs u-text-sale', dataset: { qsAction: 'delete-room', roomId: room.id } }, icon('ph ph-trash'), el('span', { text: 'Xóa phòng' })),
    ),
  );

  const slots = el('div', { class: 'drv-room-slots' });
  if (lines.length === 0) {
    slots.append(
      el(
        'div',
        { class: 'drv-slot-row drv-slot-row--empty' },
        el(
          'div',
          { class: 'drv-slot-row__empty-action' },
          el('button', { type: 'button', class: 'drv-btn drv-btn--moss drv-btn--sm', dataset: { qsAction: 'open-picker', roomId: room.id } }, icon('ph-bold ph-plus'), el('span', { text: 'Chọn mẫu gạch' })),
          el('span', { class: 'u-text-xs u-text-muted', text: 'Chưa chọn gạch cho khu vực này' }),
        ),
      ),
    );
  }
  for (const line of lines) {
    slots.append(renderSlotRow(line, room.id, state));
  }

  return el('article', { class: 'drv-room-card', dataset: { roomId: room.id }, 'aria-label': room.name }, head, el('div', { class: 'drv-room-card__body' }, slots));
}

function setText(selector, text) {
  for (const node of qsa(selector, root)) {
    node.textContent = text;
  }
}

function boxesText(totals) {
  return `${totals.total_boxes} thùng${totals.total_loose > 0 ? ` + ${totals.total_loose} viên/tấm lẻ` : ''}`;
}

function renderTotals(calc, state) {
  const t = calc.totals;
  const hasItems = getStats(state).totalSlots > 0;
  qs('[data-qs-totals]', root).hidden = !hasItems;
  qs('[data-qs-cta]', root).hidden = !hasItems;

  setText('[data-qs-total-m2]', `${dec(t.total_m2)} m²`);
  setText('[data-qs-total-boxes]', boxesText(t));
  setText('[data-qs-total-kg]', t.total_kg > 0 ? `${dec(t.total_kg, 0)} kg · ${t.vehicle_hint}` : '0 kg');

  if (t.priced_count === 0 && t.quote_count > 0) {
    setText('[data-qs-subtotal-label]', 'Tạm tính');
    setText('[data-qs-subtotal]', 'Báo giá theo khối lượng công trình');
    setText('[data-qs-grand]', 'Chờ báo giá');
  } else {
    setText('[data-qs-subtotal-label]', `Tạm tính (${t.priced_count} mẫu có giá)`);
    setText('[data-qs-subtotal]', formatVnd(t.subtotal_vnd));
    setText('[data-qs-grand]', formatVnd(t.grand_est_vnd));
  }
  setText('[data-qs-rate]', `${dec(t.discount_rate, 0)}%`);
  setText('[data-qs-discount]', t.discount_est_vnd > 0 ? `- ${formatVnd(t.discount_est_vnd)}` : '0đ');

  const note = qs('[data-qs-quote-note]', root);
  if (note) {
    note.hidden = t.quote_count === 0;
    setText('[data-qs-quote-note-text]', `Chưa bao gồm giá của ${t.quote_count} mẫu hàng dự án (chuyên viên sẽ báo giá kèm mã lô).`);
  }

  let tierText = 'Đã đạt bậc chiết khấu dự kiến cao nhất cho công trình.';
  if (t.next_tier) {
    const gap = Math.max(0, t.next_tier.min_m2 - t.total_m2);
    tierText = `Thêm ${dec(gap)} m² gạch để đạt mốc chiết khấu dự kiến ${dec(t.next_tier.rate, 0)}% cho công trình từ ${dec(t.next_tier.min_m2, 0)} m².`;
  }
  if (t.subtotal_vnd <= 0) {
    tierText = 'Chiết khấu dự kiến áp dụng cho các mẫu có giá niêm yết.';
  }
  setText('[data-qs-tier-status]', tierText);

  const canBuy = calc.rooms.some((r) => r.lines.some((l) => l.res && !l.res.isQuote && l.product?.in_stock !== false && l.product?.price_state !== 'out_of_stock'));
  const toCart = qs('[data-qs-to-cart]', root);
  if (toCart) {
    toCart.hidden = !canBuy;
  }
}

function render() {
  const state = readState();
  lastCalc = computeQuote(state, productCache, cfg);
  const container = qs('[data-qs-rooms]', root);
  container.replaceChildren(...lastCalc.rooms.map((entry) => renderRoomCard(entry, state)));

  const hasRooms = state.rooms.length > 0;
  qs('[data-qs-empty]', root).hidden = hasRooms;
  qs('[data-qs-add-room]', root).hidden = !hasRooms;
  for (const btn of qsa('[data-qs-needs-items]', root)) {
    btn.disabled = getStats(state).totalSlots === 0;
  }

  const project = qs('[data-qs-project]', root);
  if (project && document.activeElement !== project) {
    project.value = state.project_name;
  }
  const waste = qs('[data-qs-waste-select]', root);
  if (waste) {
    waste.value = String(state.waste_pct);
  }
  renderTotals(lastCalc, state);
}

async function refresh() {
  const state = readState();
  const ids = state.rooms.flatMap((r) => r.slots.map((s) => s.product_id));
  render();
  if (ids.some((id) => !productCache.has(id) && !missingIds.has(id))) {
    await hydrate(ids);
    render();
  }
}

// ---------------------------------------------------------------------------
// Picker modal
// ---------------------------------------------------------------------------

function pickerCard(p, currentId) {
  const isCurrent = Number(p.id) === currentId;
  const priced = p.price_state !== 'quote_only' && Number(p.price_m2) > 0;
  return el(
    'div',
    { class: `drv-picker-card${isCurrent ? ' is-selected' : ''}`, role: 'listitem' },
    el(
      'div',
      { class: 'drv-picker-card__thumb' },
      el('img', { src: p.thumb || p.img_primary || '', width: 80, height: 80, loading: 'lazy', decoding: 'async', alt: '' }),
      p.size_label ? el('span', { class: 'drv-picker-card__size-badge', text: p.size_label }) : null,
    ),
    el(
      'div',
      { class: 'drv-picker-card__body' },
      el(
        'div',
        { class: 'drv-picker-card__head' },
        el('span', { class: 'drv-picker-card__sku', text: p.sku }),
        p.color_name
          ? el('span', { class: 'drv-picker-card__color' }, p.swatch_hex ? el('span', { class: 'drv-swatch-dot', style: `background:${p.swatch_hex}`, 'aria-hidden': 'true' }) : null, el('span', { text: p.color_name }))
          : null,
      ),
      el('strong', { class: 'drv-picker-card__title', text: p.name || p.title }),
      el(
        'div',
        { class: 'drv-picker-card__foot' },
        el(
          'div',
          { class: 'drv-picker-card__price' },
          priced
            ? [el('strong', { text: formatVnd(p.price_m2) }), el('small', { text: '/m²' })]
            : el('strong', { class: 'drv-qs-quote-tag' }, icon('ph-fill ph-tag', ''), QUOTE_LABEL),
        ),
        el('button', {
          type: 'button',
          class: `drv-btn ${isCurrent ? 'drv-btn--outline' : 'drv-btn--moss'} drv-btn--xs`,
          dataset: { pickerSelect: String(p.id) },
          text: isCurrent ? 'Đang chọn' : 'Chọn mẫu này',
          'aria-label': `Chọn ${p.sku}`,
        }),
      ),
    ),
  );
}

function renderPickerLoading() {
  return el('div', { class: 'drv-picker-loading', 'aria-live': 'polite' },
    el('i', { class: 'ph ph-spinner drv-spin drv-picker-loading__icon', 'aria-hidden': 'true' }),
    el('span', { class: 'drv-picker-loading__text', text: 'Đang tải danh sách mẫu gạch...' }),
  );
}

async function loadPicker(append = false) {
  const dialog = qs('#qs-picker-dialog');
  const grid = qs('[data-picker-grid]', dialog);
  const more = qs('[data-picker-more]', dialog);
  const search = String(qs('[data-picker-search]', dialog)?.value || '').trim();
  const space = qs('[data-picker-space]', dialog)?.value || '';
  const size = qs('[data-picker-size]', dialog)?.value || '';
  const pattern = qs('[data-picker-pattern]', dialog)?.value || '';
  const surface = qs('[data-picker-surface]', dialog)?.value || '';

  if (!append) {
    pickerPage = 1;
    grid.replaceChildren(renderPickerLoading());
  } else if (more) {
    more.classList.add('is-loading');
    more.disabled = true;
    more.textContent = 'Đang tải thêm...';
  }

  pickerAbort?.abort();
  pickerAbort = new AbortController();
  const params = new URLSearchParams({ per_page: '24', page: String(pickerPage) });
  if (search) {
    params.set('search', search.slice(0, 60));
  }
  if (size) {
    params.set('size', size);
  }
  if (space) {
    params.set('space', space);
  }
  if (pattern) {
    params.set('pattern', pattern);
  }
  if (surface) {
    params.set('surface', surface);
  }

  try {
    const res = await fetch(`${productsEndpoint()}?${params}`, { signal: pickerAbort.signal, headers: { Accept: 'application/json' } });
    const data = res.ok ? await res.json() : { items: [] };
    const items = (Array.isArray(data.items) ? data.items : []).filter((p) => p.is_tile !== false);
    for (const p of items) {
      productCache.set(Number(p.id), p);
      missingIds.delete(Number(p.id));
    }
    const current = findSlot(readState(), pickerTarget.slotId)?.slot.product_id || 0;
    if (!append) {
      grid.replaceChildren();
    }
    if (!append && items.length === 0) {
      grid.append(el('div', { class: 'drv-picker-grid__status', text: 'Không tìm thấy mẫu gạch phù hợp. Thử từ khóa khác hoặc bỏ lọc.' }));
    }
    grid.append(...items.map((p) => pickerCard(p, current)));
    setText('[data-picker-count]', String(data.total ?? items.length));
    if (more) {
      more.hidden = pickerPage >= Number(data.max_pages || 1);
    }
    renderPickerActiveChips();
  } catch (err) {
    if (err?.name !== 'AbortError') {
      grid.replaceChildren(el('div', { class: 'drv-picker-grid__status', text: 'Không tải được danh mục. Vui lòng thử lại.' }));
    }
  } finally {
    if (more) {
      more.classList.remove('is-loading');
      more.disabled = false;
      more.textContent = 'Xem thêm mẫu';
    }
  }
}

/** Đóng toàn bộ custom dropdown trong modal picker */
function closeAllPickerDropdowns() {
  const dialog = qs('#qs-picker-dialog');
  if (!dialog) return;
  for (const dd of qsa('.drv-picker-dropdown', dialog)) {
    dd.classList.remove('is-open');
    const trigger = qs('.drv-picker-dropdown__btn', dd);
    if (trigger) trigger.setAttribute('aria-expanded', 'false');
    const menu = qs('.drv-picker-dropdown__menu', dd);
    if (menu) {
      menu.hidden = true;
      menu.setAttribute('hidden', '');
    }
  }
}

/** Render active filter chips bên dưới thanh lọc */
function renderPickerActiveChips() {
  const dialog = qs('#qs-picker-dialog');
  const container = qs('[data-picker-active-filters]', dialog);
  if (!container) return;

  const LABELS = {
    space: 'Không gian',
    size: 'Kích thước',
    pattern: 'Vân gạch',
    surface: 'Bề mặt',
  };
  const selectors = { space: '[data-picker-space]', size: '[data-picker-size]', pattern: '[data-picker-pattern]', surface: '[data-picker-surface]' };
  const chips = [];

  for (const [key, sel] of Object.entries(selectors)) {
    const input = qs(sel, dialog);
    if (!input) continue;
    const val = input.value || '';
    if (!val) continue;

    const dd = input.closest('.drv-picker-dropdown');
    const currentLabel = qs('.drv-picker-dropdown__label', dd)?.textContent || val;
    chips.push({ key, label: `${LABELS[key]}: ${currentLabel}`, val });
  }

  // Xóa các chip cũ (giữ nút "Xóa lọc")
  for (const chip of Array.from(container.querySelectorAll('.drv-picker-active-chip'))) {
    chip.remove();
  }

  const resetBtn = qs('[data-picker-reset-all]', container);
  for (const { key, label } of chips) {
    const chip = el('span', { class: 'drv-picker-active-chip' },
      el('span', { text: label }),
      el('button', {
        type: 'button',
        class: 'drv-picker-active-chip__remove',
        'aria-label': `Xóa lọc ${label}`,
        dataset: { pickerClearFilter: key },
      }, el('i', { class: 'ph ph-x', 'aria-hidden': 'true' })),
    );
    if (resetBtn) container.insertBefore(chip, resetBtn);
    else container.append(chip);
  }

  container.hidden = chips.length === 0;
}

function openPicker(roomId, slotId, opener) {
  const ROOM_TO_SPACE = {
    'phòng khách': 'phong-khach',
    'phòng bếp': 'phong-bep',
    'phòng ăn': 'phong-bep',
    'nhà tắm': 'nha-tam',
    'toilet': 'nha-tam',
    'wc': 'nha-tam',
    'phòng ngủ': 'phong-ngu',
    'sân vườn': 'san-vuon',
    'ban công': 'ngoai-troi',
    'ngoài trời': 'ngoai-troi',
    'cầu thang': 'hanh-lang-cau-thang',
    'hành lang': 'hanh-lang-cau-thang',
    'phòng thờ': 'phong-tho',
    'văn phòng': 'van-phong',
  };
  const state = readState();
  const room = state.rooms.find((r) => r.id === roomId);
  pickerTarget = { roomId: room ? room.id : DEFAULT_ROOM_ID, slotId: slotId || '' };
  setText('[data-picker-target-name]', room ? room.name : DEFAULT_ROOM_NAME);
  openDialog('#qs-picker-dialog', opener);

  // Đóng toàn bộ menu dropdown
  closeAllPickerDropdowns();

  // Reset filters khi mở picker mới
  const dialog = qs('#qs-picker-dialog');
  for (const dd of qsa('.drv-picker-dropdown', dialog)) {
    const input = qs('input[type="hidden"]', dd);
    const trigger = qs('.drv-picker-dropdown__btn', dd);
    const labelSpan = qs('.drv-picker-dropdown__label', dd);
    if (input) input.value = '';
    if (labelSpan && input) labelSpan.textContent = input.dataset.defaultLabel || '';
    if (trigger) trigger.classList.remove('is-active');
    for (const item of qsa('.drv-move-popover__item', dd)) {
      item.classList.toggle('is-selected', item.dataset.pickerVal === '');
    }
  }

  // Auto-map tên phòng → không gian
  if (room) {
    const roomName = (room.name || '').toLowerCase().trim();
    const matchedSpace = ROOM_TO_SPACE[roomName] || '';
    if (matchedSpace) {
      const spaceDd = qs('.drv-picker-dropdown[data-picker-facet="space"]', dialog);
      if (spaceDd) {
        const input = qs('[data-picker-space]', spaceDd);
        const trigger = qs('.drv-picker-dropdown__btn', spaceDd);
        const labelSpan = qs('.drv-picker-dropdown__label', spaceDd);
        const matchedItem = qs(`.drv-move-popover__item[data-picker-val="${CSS.escape(matchedSpace)}"]`, spaceDd);
        if (input) input.value = matchedSpace;
        if (labelSpan && matchedItem) labelSpan.textContent = matchedItem.dataset.pickerName || matchedSpace;
        if (trigger) trigger.classList.add('is-active');
        for (const item of qsa('.drv-move-popover__item', spaceDd)) {
          item.classList.toggle('is-selected', item === matchedItem);
        }
      }
    }
  }

  loadPicker(false);
}

function selectFromPicker(productId) {
  const product = productCache.get(productId);
  if (!product) {
    return;
  }
  const defaultM2 = Number(product.m2_per_box) || Number(product.default_m2) || 1;
  const { result } = updateQuote((draft) => {
    if (pickerTarget.slotId) {
      const found = findSlot(draft, pickerTarget.slotId);
      if (found) {
        Object.assign(found.slot, {
          product_id: productId,
          sku: product.sku,
          round_mode: product.round_mode,
          ...(found.slot.input_mode === 'm2' && found.slot.target_m2 > 0 ? {} : { input_mode: 'm2', target_m2: defaultM2 }),
        });
        return { ok: true };
      }
    }
    const roomName = draft.rooms.find((r) => r.id === pickerTarget.roomId)?.name || DEFAULT_ROOM_NAME;
    return addSlot(draft, pickerTarget.roomId, {
      product_id: productId,
      sku: product.sku,
      role: roles()[0],
      input_mode: 'm2',
      target_m2: defaultM2,
      round_mode: product.round_mode,
    }, roomName);
  });
  if (result?.full) {
    showToast('Bảng dự toán đã đủ 30 hạng mục.', 'warning');
    return;
  }
  closeDialog('#qs-picker-dialog');
  showToast(`Đã chọn ${product.sku}. Nhập diện tích m² để tính số thùng.`, 'success');
  render();
  const input = qs(`[data-qs-qty][data-slot-id="${CSS.escape(result?.slot?.id || pickerTarget.slotId)}"]`, root);
  input?.focus();
  input?.select?.();
}

// ---------------------------------------------------------------------------
// Xuất file: In A4, CSV, Zalo, link chia sẻ
// ---------------------------------------------------------------------------

function shareUrl(state) {
  const base = `${window.location.origin}${window.location.pathname}`;
  return `${base}?quote=${encodeShare(state, roles())}`;
}

function linesFlat(calc) {
  return calc.rooms.flatMap((r) => r.lines.filter((l) => l.res).map((l) => ({ ...l, roomName: r.room.name })));
}

function fillPrint(state, calc) {
  const t = calc.totals;
  setText('[data-qs-print-code]', submittedCode || 'Bản nháp');
  setText('[data-qs-print-project]', state.project_name || '-');
  setText('[data-qs-print-waste]', `${state.waste_pct}%`);
  setText('[data-qs-print-m2]', `${dec(t.total_m2)} m²`);
  setText('[data-qs-print-boxes]', boxesText(t));
  setText('[data-qs-print-kg]', `${dec(t.total_kg, 0)} kg (${t.vehicle_hint})`);
  setText('[data-qs-print-subtotal]', formatVnd(t.subtotal_vnd));
  setText('[data-qs-print-rate]', `${dec(t.discount_rate, 0)}%`);
  setText('[data-qs-print-discount]', t.discount_est_vnd > 0 ? `- ${formatVnd(t.discount_est_vnd)}` : '0đ');
  setText('[data-qs-print-grand]', formatVnd(t.grand_est_vnd));
  setText('[data-qs-print-quote-note]', t.quote_count > 0 ? `Chưa gồm ${t.quote_count} mẫu báo giá riêng · Chưa gồm phí vận chuyển` : 'Đã gồm VAT · Chưa gồm phí vận chuyển');

  const body = qs('[data-qs-print-body]', root);
  const blocks = [];
  let idx = 0;
  for (const entry of calc.rooms) {
    const rows = entry.lines.filter((l) => l.res);
    if (rows.length === 0) {
      continue;
    }
    const tbody = el('tbody');
    for (const l of rows) {
      idx += 1;
      tbody.append(
        el(
          'tr',
          {},
          el('td', { text: String(idx) }),
          el('td', { text: l.slot.role }),
          el('td', { text: `${l.product.sku} · ${l.product.name}` }),
          el('td', { text: l.product.size_label || '' }),
          el('td', { class: 'is-num', text: l.res.isQuote ? QUOTE_LABEL : formatVnd(l.product.price_m2) }),
          el('td', { class: 'is-num', text: `${l.res.boxText} · ${dec(l.res.actualM2)} m²` }),
          el('td', { class: 'is-num', text: l.res.isQuote ? QUOTE_TOTAL : formatVnd(l.res.totalVnd) }),
        ),
      );
    }
    const thead = el('thead', {}, el('tr', {}, ...['#', 'Hạng mục', 'Mã · Tên gạch', 'Khổ', 'Đơn giá/m²', 'Khối lượng', 'Thành tiền'].map((h) => el('th', { text: h }))));
    blocks.push(el('h3', { class: 'drv-boq-print__room', text: entry.room.name }), el('table', {}, thead, tbody));
  }
  body.replaceChildren(...blocks);
}

function printPdf() {
  const state = readState();
  fillPrint(state, lastCalc || computeQuote(state, productCache, cfg));
  window.print();
}

export function buildCsv(state, calc) {
  const header = ['Khu vực / Phòng', 'Hạng mục', 'Mã SKU', 'Tên sản phẩm', 'Khổ gạch', 'Diện tích thuần (m²)', 'Hao hụt (%)', 'Số thùng', 'Viên/Tấm lẻ', 'Tổng số lượng (viên/tấm)', 'Diện tích thực nhận (m²)', 'Khối lượng (kg)', 'Đơn giá (đ/m²)', 'Thành tiền dự kiến (VND)'];
  const lines = [header.map(sanitizeCsvCell).join(',')];
  for (const l of linesFlat(calc)) {
    lines.push(
      [
        l.roomName,
        l.slot.role,
        l.product.sku,
        l.product.name,
        l.product.size_label || '',
        l.res.targetM2,
        state.waste_pct,
        l.res.fullBoxes,
        l.res.loosePieces,
        l.res.pieces,
        l.res.actualM2,
        l.res.totalKg,
        l.res.isQuote ? QUOTE_LABEL : l.product.price_m2,
        l.res.isQuote ? QUOTE_TOTAL : l.res.totalVnd,
      ]
        .map(sanitizeCsvCell)
        .join(','),
    );
  }
  if (state.project_name) {
    lines.unshift(['Công trình', state.project_name].map(sanitizeCsvCell).join(','));
  }
  // BOM UTF-8 để Excel Windows mở đúng tiếng Việt (docs/17 §6.2).
  return String.fromCharCode(0xfeff) + lines.join('\r\n');
}

function downloadCsv() {
  const state = readState();
  const csv = buildCsv(state, lastCalc || computeQuote(state, productCache, cfg));
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
  const a = el('a', { href: URL.createObjectURL(blob), download: `dravit-du-toan-${new Date().toISOString().slice(0, 10).replace(/-/g, '')}.csv` });
  document.body.append(a);
  a.click();
  setTimeout(() => {
    URL.revokeObjectURL(a.href);
    a.remove();
  }, 500);
}

export function buildZaloText(state, calc, link) {
  const t = calc.totals;
  const out = [`BẢNG DỰ TOÁN GẠCH ${cfg.brand || 'DRAVIT'}${state.project_name ? ` - ${state.project_name}` : ''}`, `Mức hao hụt tính sẵn: ${state.waste_pct}%`, ''];
  let i = 0;
  for (const entry of calc.rooms) {
    const rows = entry.lines.filter((l) => l.res);
    if (rows.length === 0) {
      continue;
    }
    i += 1;
    out.push(`${i}. ${entry.room.name}:`);
    for (const l of rows) {
      const price = l.res.isQuote ? 'Liên hệ báo giá' : `${formatVnd(l.product.price_m2)}/m² -> ${formatVnd(l.res.totalVnd)}`;
      out.push(`- ${l.slot.role}: ${l.product.sku}${l.product.size_label ? ` (${l.product.size_label})` : ''} · ${l.res.boxText} = ${dec(l.res.actualM2)} m² · ${price}`);
    }
    out.push('');
  }
  out.push(`TỔNG KHỐI LƯỢNG: ${dec(t.total_m2)} m² · ${boxesText(t)} · ${dec(t.total_kg, 0)} kg (${t.vehicle_hint})`);
  if (t.subtotal_vnd > 0) {
    out.push(`Tạm tính các mẫu có giá: ${formatVnd(t.subtotal_vnd)}`);
    if (t.discount_est_vnd > 0) {
      out.push(`Chiết khấu dự kiến (${dec(t.discount_rate, 0)}% - xác nhận theo lô): -${formatVnd(t.discount_est_vnd)}`);
    }
    const extra = t.quote_count > 0 ? `+ ${t.quote_count} mẫu báo giá riêng, ` : '';
    out.push(`Dự toán tạm tính: ${formatVnd(t.grand_est_vnd)} (${extra}chưa gồm phí vận chuyển)`);
  } else {
    out.push('Đơn giá: báo theo khối lượng công trình, chưa gồm phí vận chuyển.');
  }
  out.push('', `Xem & chỉnh sửa trực tiếp: ${link}`);
  return out.join('\n');
}

async function copyZalo() {
  const state = readState();
  const text = buildZaloText(state, lastCalc || computeQuote(state, productCache, cfg), shareUrl(state));
  const ok = await copyText(text);
  showToast(ok ? 'Đã sao chép bảng dự toán. Dán vào Zalo để gửi.' : 'Trình duyệt chặn sao chép. Vui lòng thử lại.', ok ? 'success' : 'error');
}

async function copyShare() {
  const ok = await copyText(shareUrl(readState()));
  showToast(ok ? 'Đã sao chép link chia sẻ bảng dự toán.' : 'Không sao chép được link.', ok ? 'success' : 'error');
}

// ---------------------------------------------------------------------------
// Đặt mua mẫu có giá (`boq-to-cart`, docs/17 §3.3) – không áp chiết khấu bậc vào giỏ (D-23)
// ---------------------------------------------------------------------------

async function storeNonce() {
  try {
    const res = await fetch(window.DRV_CONFIG?.nonceEndpoint || endpoint('nonce'), { credentials: 'same-origin', cache: 'no-store' });
    const data = res.ok ? await res.json() : null;
    return String(data?.nonces?.wc_store_api || '');
  } catch {
    return '';
  }
}

export function aggregateForCart(calc, wastePct) {
  const byProduct = new Map();
  let skipped = 0;
  for (const l of linesFlat(calc)) {
    const sellable = !l.res.isQuote && l.product.in_stock !== false && l.product.price_state !== 'out_of_stock';
    if (!sellable) {
      skipped += 1;
      continue;
    }
    const prev = byProduct.get(l.product.id) || { id: l.product.id, sku: l.product.sku, quantity: 0, m2: 0, stock: l.product.stock_qty };
    prev.quantity += l.res.pieces;
    prev.m2 += l.slot.input_mode === 'm2' ? l.res.targetM2 : 0;
    byProduct.set(l.product.id, prev);
  }
  const ready = [];
  for (const item of byProduct.values()) {
    if (Number.isFinite(item.stock) && item.stock !== null && item.quantity > item.stock) {
      skipped += 1;
      continue;
    }
    ready.push({
      id: item.id,
      quantity: item.quantity,
      drv_cart_data: {
        drv_m2_input: String(Math.round(item.m2 * 100) / 100),
        drv_waste_pct: String(wastePct),
        drv_input_unit: item.m2 > 0 ? 'm2' : 'vien',
      },
    });
  }
  return { ready, skipped };
}

async function boqToCart(trigger) {
  const state = readState();
  const { ready, skipped } = aggregateForCart(lastCalc || computeQuote(state, productCache, cfg), state.waste_pct);
  if (ready.length === 0) {
    showToast('Chưa có mẫu có giá còn đủ kho để đặt mua lẻ. Vui lòng gửi yêu cầu báo giá.', 'warning', 4800);
    return;
  }
  trigger.setAttribute('aria-busy', 'true');
  trigger.disabled = true;
  let nonce = await storeNonce();
  let added = 0;
  let lastCart = null;
  try {
    for (const item of ready) {
      let res = await fetch(window.DRV_CONFIG?.wcStoreAddUrl || '/wp-json/wc/store/v1/cart/add-item', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', Nonce: nonce },
        body: JSON.stringify(item),
      });
      if (res.status === 401 || res.status === 403) {
        nonce = await storeNonce();
        res = await fetch(window.DRV_CONFIG?.wcStoreAddUrl || '/wp-json/wc/store/v1/cart/add-item', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json', Nonce: nonce },
          body: JSON.stringify(item),
        });
      }
      if (res.ok) {
        added += 1;
        lastCart = await res.json().catch(() => null);
      }
    }
  } finally {
    trigger.removeAttribute('aria-busy');
    trigger.disabled = false;
  }
  if (lastCart) {
    renderMiniCartState(lastCart);
  }
  const failed = ready.length - added;
  if (added > 0 && skipped === 0 && failed === 0) {
    window.location.href = cfg.cartUrl || '/gio-hang';
    return;
  }
  if (added > 0) {
    showToast(`Đã thêm ${added} mẫu có giá vào giỏ hàng. Còn ${skipped + failed} mẫu báo giá theo lô vẫn được giữ tại bảng dự toán này.`, 'success', 6000, { label: 'Xem giỏ hàng', href: cfg.cartUrl || '/gio-hang' });
  } else {
    showToast('Không thêm được vào giỏ hàng. Vui lòng thử lại hoặc gửi yêu cầu báo giá.', 'error', 4800);
  }
}

// ---------------------------------------------------------------------------
// Gửi phiếu BOQ (`POST /dravit/v1/quote/submit`)
// ---------------------------------------------------------------------------

function renderLeadSummary() {
  const state = readState();
  const calc = lastCalc || computeQuote(state, productCache, cfg);
  const list = qs('[data-qs-lead-list]', root);
  const rows = linesFlat(calc);
  const nodes = rows.slice(0, 6).map((l) =>
    el(
      'div',
      { class: 'drv-qs-lead-item' },
      el('img', { class: 'drv-qs-lead-item__thumb', src: l.product.thumb || '', width: 38, height: 38, alt: '', loading: 'lazy' }),
      el(
        'div',
        { class: 'drv-qs-lead-item__info' },
        el('span', { class: 'drv-qs-lead-item__name', text: `${l.product.sku} · ${l.product.name}` }),
        el('span', { class: 'drv-qs-lead-item__meta', text: `${l.roomName} · ${l.slot.role}` }),
      ),
      el(
        'div',
        { class: 'drv-qs-lead-item__qty' },
        el('span', { class: 'drv-qs-lead-item__qty-val', text: `${l.res.boxText} · ${dec(l.res.actualM2)} m²` }),
        el('strong', { class: `drv-qs-lead-item__price u-tabular${l.res.isQuote ? ' is-quote' : ''}`, text: l.res.isQuote ? QUOTE_TOTAL : formatVnd(l.res.totalVnd) }),
      ),
    ),
  );
  if (rows.length > 6) {
    nodes.push(el('p', { class: 'u-text-xs u-text-muted', style: 'margin:0', text: `+ ${rows.length - 6} hạng mục khác` }));
  }
  list.replaceChildren(...nodes);
  setText('[data-qs-lead-meta]', `${rows.length} hạng mục · ${state.rooms.length} khu vực`);
  setText('[data-qs-lead-total]', calc.totals.priced_count > 0 ? formatVnd(calc.totals.grand_est_vnd) : 'Chờ báo giá');
  const project = qs('[data-qs-lead-project]', root);
  if (project && !project.value) {
    project.value = state.project_name;
  }
}

async function openLead(opener) {
  const state = readState();
  if (missingIds.size > 0 && state.rooms.some((r) => r.slots.some((s) => missingIds.has(s.product_id)))) {
    showToast('Bảng có mẫu ngừng kinh doanh. Vui lòng chọn mẫu thay thế hoặc xóa dòng đó trước khi gửi.', 'warning', 5200);
    return;
  }
  const hasQty = lastCalc?.rooms.some((r) => r.lines.some((l) => l.res && l.res.pieces > 0));
  if (!hasQty) {
    showToast('Vui lòng nhập diện tích cho ít nhất 1 mẫu gạch.', 'warning');
    return;
  }
  renderLeadSummary();
  prefetchToken();
  openDialog('#drv-qs-lead', opener);
}

async function submitLead(form) {
  const leadForm = await import('./chunks/lead-form.js');
  leadForm.clearErrors(form);
  const errors = leadForm.validateLeadForm(form);
  if (Object.keys(errors).length > 0) {
    for (const [name, message] of Object.entries(errors)) {
      leadForm.setFieldError(form, name, message);
    }
    form.querySelector(`[name="${CSS.escape(Object.keys(errors)[0])}"]`)?.focus();
    return;
  }

  const state = readState();
  const fd = new FormData(form);
  const get = (n) => String(fd.get(n) ?? '').trim();
  const payload = {
    contact: {
      name: get('name'),
      phone: leadForm.cleanPhone(get('phone')),
      email: get('email'),
      province: get('province'),
      role: get('role') || 'chu-nha',
      project: get('project') || state.project_name,
      message: get('message'),
      sample_box: fd.get('sample_box') === '1',
      consent: fd.get('consent') === '1',
    },
    boq: {
      waste_pct: state.waste_pct,
      project_name: state.project_name,
      rooms: state.rooms.map((r) => ({
        name: r.name,
        slots: r.slots.map((s) => ({ role: s.role, product_id: s.product_id, input_mode: s.input_mode, target_m2: s.target_m2, pieces: s.pieces })),
      })),
    },
  };

  const submit = form.querySelector('[type="submit"]');
  submit?.setAttribute('aria-busy', 'true');
  if (submit) {
    submit.disabled = true;
  }
  try {
    const result = await postProtected('quote', endpoint('quote/submit'), payload, { honeypot: get('drv_website') });
    if (result.ok) {
      submittedCode = String(result.data?.quote_code || '');
      setText('[data-qs-code]', submittedCode ? `#${submittedCode}` : 'Đã gửi');
      setText('[data-qs-success-code]', submittedCode ? `#${submittedCode}` : '');
      setText('[data-qs-success-msg]', String(result.data?.sla_message || 'Chuyên viên DRAVIT sẽ liên hệ lại sớm nhất.'));
      form.hidden = true;
      const success = qs('[data-qs-success]', root);
      success.hidden = false;
      success.focus();
      window.dataLayer?.push({ event: 'drv_boq_submit', quote_code: submittedCode, total_m2: result.data?.summary?.total_m2 });
      return;
    }
    const fields = result.data?.data?.fields;
    if (result.status === 422 && fields) {
      let placed = false;
      for (const [name, message] of Object.entries(fields)) {
        placed = leadForm.setFieldError(form, name, String(message)) || placed;
      }
      if (!placed || fields.rooms) {
        showFormStatus(form, String(fields.rooms || errorMessage(result)));
      }
      return;
    }
    showFormStatus(form, errorMessage(result));
  } catch {
    showFormStatus(form, 'Chưa gửi được yêu cầu. Vui lòng kiểm tra kết nối và thử lại.');
  } finally {
    submit?.removeAttribute('aria-busy');
    if (submit) {
      submit.disabled = false;
    }
  }
}

function showFormStatus(form, message) {
  const box = qs('[data-lead-form-status]', form);
  if (box) {
    box.textContent = message;
    box.hidden = false;
  }
}

// ---------------------------------------------------------------------------
// Link chia sẻ `?quote=`
// ---------------------------------------------------------------------------

async function applyShared(incoming) {
  // Quy đổi số thùng chia sẻ (`b{n}`) ra số viên sau khi biết quy cách đóng gói.
  const ids = incoming.rooms.flatMap((r) => r.slots.map((s) => s.product_id));
  await hydrate(ids);
  for (const room of incoming.rooms) {
    for (const slot of room.slots) {
      if (slot.share_boxes) {
        const ppb = Number(productCache.get(slot.product_id)?.pieces_per_box) || 1;
        slot.pieces = slot.share_boxes * ppb;
        delete slot.share_boxes;
      }
    }
  }

  const current = readState();
  const count = getStats(incoming).totalSlots;
  const cleanUrl = () => window.history.replaceState(null, '', window.location.pathname);

  if (getStats(current).totalSlots === 0) {
    writeState(incoming, 'storage');
    cleanUrl();
    showToast(`Đã mở bảng dự toán được chia sẻ (${count} mẫu).`, 'success');
    return;
  }

  const dialog = qs('#qs-share-dialog');
  setText('[data-qs-share-text]', `Bạn đang mở một bảng dự toán được chia sẻ (${count} mẫu). Bạn muốn thay thế bảng hiện tại hay gộp thêm vào?`);
  await new Promise((resolve) => {
    const onClick = (event) => {
      const choice = event.target instanceof Element ? event.target.closest('[data-qs-share-choice]')?.dataset.qsShareChoice : '';
      if (!choice) {
        return;
      }
      if (choice === 'replace') {
        writeState(incoming, 'storage');
      } else if (choice === 'merge') {
        writeState(mergeStates(current, incoming), 'storage');
      }
      dialog.removeEventListener('click', onClick);
      closeDialog(dialog);
      resolve();
    };
    dialog.addEventListener('click', onClick);
    dialog.addEventListener('close', () => resolve(), { once: true });
    openDialog(dialog);
  });
  cleanUrl();
}

// ---------------------------------------------------------------------------
// Sự kiện
// ---------------------------------------------------------------------------

function onClick(event) {
  const target = event.target instanceof Element ? event.target : null;
  if (!target) {
    return;
  }

  const preset = target.closest('[data-qs-add-preset]');
  if (preset) {
    const name = preset.getAttribute('data-qs-add-preset') || '';
    const { result } = updateQuote((draft) => addRoom(draft, name));
    if (!result) {
      showToast(`Tối đa ${cfg.maxRooms || MAX_ROOMS} khu vực trên 1 phiếu.`, 'warning');
      return;
    }
    render();
    openPicker(result.id, '', preset);
    return;
  }

  const modeBtn = target.closest('[data-qs-mode]');
  if (modeBtn) {
    const slotId = modeBtn.dataset.slotId;
    const mode = modeBtn.dataset.qsMode;
    const line = lastCalc?.rooms.flatMap((r) => r.lines).find((l) => l.slot.id === slotId);
    updateQuote((draft) => {
      const patch = { input_mode: mode };
      if (line?.res && line.res.pieces > 0) {
        patch.pieces = line.res.pieces;
        if (mode === 'm2') {
          patch.target_m2 = line.res.targetM2 || line.res.actualM2;
        }
      }
      updateSlot(draft, slotId, patch);
    });
    render();
    qs(`[data-qs-qty][data-slot-id="${CSS.escape(slotId)}"]`, root)?.focus();
    return;
  }

  const pick = target.closest('[data-picker-select]');
  if (pick) {
    selectFromPicker(Number(pick.getAttribute('data-picker-select')));
    return;
  }

  // Toggle custom dropdown popover trong picker
  const ddTrigger = target.closest('[data-picker-dropdown-trigger]');
  if (ddTrigger) {
    const dd = ddTrigger.closest('.drv-picker-dropdown');
    const menu = qs('.drv-picker-dropdown__menu', dd);
    const isOpen = dd.classList.contains('is-open');

    // Đóng các dropdown khác trước
    closeAllPickerDropdowns();

    if (!isOpen && menu) {
      dd.classList.add('is-open');
      ddTrigger.setAttribute('aria-expanded', 'true');
      menu.hidden = false;
      menu.removeAttribute('hidden');
    }
    return;
  }

  // Chọn option trong custom dropdown popover
  const ddItem = target.closest('.drv-picker-dropdown .drv-move-popover__item');
  if (ddItem) {
    const dd = ddItem.closest('.drv-picker-dropdown');
    const input = qs('input[type="hidden"]', dd);
    const trigger = qs('.drv-picker-dropdown__btn', dd);
    const labelSpan = qs('.drv-picker-dropdown__label', dd);
    const val = ddItem.dataset.pickerVal || '';
    const name = ddItem.dataset.pickerName || input?.dataset.defaultLabel || '';

    if (input) input.value = val;
    if (labelSpan) labelSpan.textContent = val ? name : (input?.dataset.defaultLabel || name);
    if (trigger) trigger.classList.toggle('is-active', Boolean(val));

    for (const item of qsa('.drv-move-popover__item', dd)) {
      item.classList.toggle('is-selected', item === ddItem);
    }

    closeAllPickerDropdowns();
    loadPicker(false);
    return;
  }

  // Clear single facet chip
  const clearFilter = target.closest('[data-picker-clear-filter]');
  if (clearFilter) {
    const dialog = qs('#qs-picker-dialog');
    const key = clearFilter.dataset.pickerClearFilter;
    const dd = qs(`.drv-picker-dropdown[data-picker-facet="${CSS.escape(key)}"]`, dialog);
    if (dd) {
      const input = qs('input[type="hidden"]', dd);
      const trigger = qs('.drv-picker-dropdown__btn', dd);
      const labelSpan = qs('.drv-picker-dropdown__label', dd);
      if (input) input.value = '';
      if (labelSpan && input) labelSpan.textContent = input.dataset.defaultLabel || '';
      if (trigger) trigger.classList.remove('is-active');
      for (const item of qsa('.drv-move-popover__item', dd)) {
        item.classList.toggle('is-selected', item.dataset.pickerVal === '');
      }
    }
    loadPicker(false);
    return;
  }

  // Reset all facets
  if (target.closest('[data-picker-reset-all]')) {
    const dialog = qs('#qs-picker-dialog');
    for (const dd of qsa('.drv-picker-dropdown', dialog)) {
      const input = qs('input[type="hidden"]', dd);
      const trigger = qs('.drv-picker-dropdown__btn', dd);
      const labelSpan = qs('.drv-picker-dropdown__label', dd);
      if (input) input.value = '';
      if (labelSpan && input) labelSpan.textContent = input.dataset.defaultLabel || '';
      if (trigger) trigger.classList.remove('is-active');
      for (const item of qsa('.drv-move-popover__item', dd)) {
        item.classList.toggle('is-selected', item.dataset.pickerVal === '');
      }
    }
    const searchInput = qs('[data-picker-search]', dialog);
    if (searchInput) searchInput.value = '';
    closeAllPickerDropdowns();
    loadPicker(false);
    return;
  }

  if (target.closest('[data-picker-more]')) {
    pickerPage += 1;
    loadPicker(true);
    return;
  }

  const action = target.closest('[data-qs-action]');
  if (!action) {
    return;
  }
  const name = action.getAttribute('data-qs-action');
  const roomId = action.dataset.roomId || '';
  const slotId = action.dataset.slotId || '';

  switch (name) {
    case 'toggle-move-menu':
      activeMoveSlotId = activeMoveSlotId === slotId ? null : slotId;
      render();
      break;
    case 'move-to-room': {
      const targetRoomId = action.dataset.targetRoomId;
      const targetRoomName = action.dataset.targetRoomName || 'phòng khác';
      activeMoveSlotId = null;
      updateQuote((draft) => moveSlot(draft, slotId, targetRoomId));
      showToast(`Đã chuyển sang ${targetRoomName}.`, 'success');
      render();
      break;
    }
    case 'open-picker':
      openPicker(roomId || readState().rooms[0]?.id || DEFAULT_ROOM_ID, slotId, action);
      break;
    case 'delete-slot':
      updateQuote((draft) => removeSlot(draft, slotId));
      render();
      break;
    case 'delete-room': {
      const room = readState().rooms.find((r) => r.id === roomId);
      if (room && (room.slots.length === 0 || window.confirm(`Xóa khu vực "${room.name}" cùng ${room.slots.length} mẫu gạch?`))) {
        updateQuote((draft) => removeRoom(draft, roomId));
        render();
      }
      break;
    }
    case 'rename-room': {
      const card = action.closest('.drv-room-card');
      const h3 = card ? qs('h3', card) : null;
      if (!card || !h3) {
        break;
      }
      const input = el('input', { type: 'text', class: 'drv-input drv-input--sm drv-room-card__name-input', maxlength: 60, 'aria-label': 'Tên phòng mới', value: h3.textContent || '' });
      h3.replaceWith(input);
      input.focus();
      input.select();
      const commit = () => {
        updateQuote((draft) => renameRoom(draft, roomId, input.value));
        render();
      };
      input.addEventListener('blur', commit, { once: true });
      input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          input.blur();
        } else if (e.key === 'Escape') {
          input.value = h3.textContent || '';
          input.blur();
        }
      });
      break;
    }
    case 'pdf':
      printPdf();
      break;
    case 'csv':
      downloadCsv();
      break;
    case 'share':
      copyShare();
      break;
    case 'copy-zalo':
      copyZalo();
      break;
    case 'to-cart':
      boqToCart(action);
      break;
    case 'open-lead':
      openLead(action);
      break;
    case 'clear':
      if (window.confirm('Xóa toàn bộ bảng dự toán nháp để lập công trình mới?')) {
        clearQuote('boq');
        submittedCode = '';
        setText('[data-qs-code]', 'Bản nháp');
        qs('[data-qs-lead-form]', root).hidden = false;
        qs('[data-qs-success]', root).hidden = true;
        closeDialog('#drv-qs-lead');
        render();
      }
      break;
    default:
      break;
  }
}

const onQtyInput = debounce((input) => {
  const slotId = input.dataset.slotId;
  const raw = Number(String(input.value).replace(',', '.')) || 0;
  const line = lastCalc?.rooms.flatMap((r) => r.lines).find((l) => l.slot.id === slotId);
  const ppb = Math.max(1, Number(line?.product?.pieces_per_box) || 1);
  updateQuote((draft) => {
    const found = findSlot(draft, slotId);
    if (!found) {
      return;
    }
    if (found.slot.input_mode === 'm2') {
      found.slot.target_m2 = Math.max(0, Math.min(10000, Math.round(raw * 100) / 100));
    } else if (found.slot.input_mode === 'thung') {
      found.slot.pieces = Math.max(1, Math.round(raw)) * ppb;
    } else {
      found.slot.pieces = Math.max(1, Math.round(raw));
    }
  });
  // Chỉ cập nhật số liệu, giữ nguyên focus/caret của ô đang gõ.
  const state = readState();
  lastCalc = computeQuote(state, productCache, cfg);
  const fresh = lastCalc.rooms.flatMap((r) => r.lines).find((l) => l.slot.id === slotId);
  const row = input.closest('.drv-slot-row');
  if (fresh?.res && row) {
    const conv = qs('.drv-slot-row__conv', row);
    if (conv) {
      conv.textContent = fresh.res.pieces > 0 ? `${fresh.res.boxText} · ${dec(fresh.res.actualM2)} m²${fresh.res.totalKg > 0 ? ` · ${dec(fresh.res.totalKg, 0)} kg` : ''}` : 'Nhập diện tích';
    }
    const total = qs('.drv-slot-row__total-val', row);
    if (total) {
      total.textContent = fresh.res.isQuote ? QUOTE_TOTAL : formatVnd(fresh.res.totalVnd);
    }
  }
  for (const entry of lastCalc.rooms) {
    const card = qs(`.drv-room-card[data-room-id="${CSS.escape(entry.room.id)}"] .drv-room-card__summary`, root);
    if (card) {
      const filled = entry.lines.filter((l) => l.res).length;
      card.textContent = filled > 0 ? `${filled} loại gạch · ${dec(entry.roomM2)} m²${entry.roomVnd > 0 ? ` · ${formatVnd(entry.roomVnd)}` : ''}` : 'Chưa chọn mẫu gạch nào';
    }
  }
  renderTotals(lastCalc, state);
}, 220);

function onChange(event) {
  const target = event.target;
  if (!(target instanceof HTMLElement)) {
    return;
  }
  if (target.matches('[data-qs-waste-select]')) {
    updateQuote((draft) => {
      draft.waste_pct = Number(target.value);
    });
    render();
  } else if (target.matches('[data-qs-role]')) {
    updateQuote((draft) => updateSlot(draft, target.dataset.slotId, { role: target.value }));
  } else if (target.matches('[data-qs-move]') && target.value) {
    updateQuote((draft) => moveSlot(draft, target.dataset.slotId, target.value));
    render();
  } else if (target.matches('[data-qs-project]')) {
    updateQuote((draft) => {
      draft.project_name = target.value;
    });
  } else if (target.matches('[data-qs-qty]')) {
    // Chốt giá trị cuối khi rời ô (chuẩn hóa hiển thị: số thùng/viên làm tròn theo quy cách).
    setTimeout(render, 260);
  }
}

function bind() {
  root.addEventListener('click', onClick);
  root.addEventListener('change', onChange);
  root.addEventListener('input', (event) => {
    const target = event.target;
    if (target instanceof HTMLInputElement && target.matches('[data-qs-qty]')) {
      onQtyInput(target);
    } else if (target instanceof HTMLInputElement && target.matches('[data-picker-search]')) {
      debouncedPickerSearch();
    }
  });
  qs('[data-qs-custom-room-form]', root)?.addEventListener('submit', (event) => {
    event.preventDefault();
    const input = qs('[data-qs-custom-room-input]', root);
    const name = String(input?.value || '').trim();
    if (!name) {
      input?.focus();
      return;
    }
    const { result } = updateQuote((draft) => addRoom(draft, name));
    if (!result) {
      showToast(`Tối đa ${cfg.maxRooms || MAX_ROOMS} khu vực trên 1 phiếu.`, 'warning');
      return;
    }
    input.value = '';
    render();
    openPicker(result.id, '', input);
  });
  qs('[data-qs-lead-form]', root)?.addEventListener('submit', (event) => {
    event.preventDefault();
    submitLead(event.currentTarget);
  });

  // Đồng bộ đa tab / điểm chạm khác (Card, PDP) đang mở song song.
  window.addEventListener('storage', (event) => {
    if (event.key === 'drv_quote_v2') {
      refresh();
    }
  });
  document.addEventListener('click', (event) => {
    if (activeMoveSlotId && !event.target.closest('.drv-slot-action-move')) {
      activeMoveSlotId = null;
      render();
    }
    if (!event.target.closest('.drv-picker-dropdown')) {
      closeAllPickerDropdowns();
    }
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      if (activeMoveSlotId) {
        activeMoveSlotId = null;
        render();
      }
      closeAllPickerDropdowns();
    }
  });
  window.addEventListener('beforeprint', () => {
    const state = readState();
    fillPrint(state, lastCalc || computeQuote(state, productCache, cfg));
  });
}

const debouncedPickerSearch = debounce(() => loadPicker(false), 250);

export async function initQuotePage() {
  root = qs('[data-drv-boq]');
  if (!root || root.dataset.qsReady) {
    return;
  }
  root.dataset.qsReady = '1';
  cfg = readConfig();
  if (Array.isArray(cfg.roles) && cfg.roles.length > 0) {
    window.DRV_CONFIG = { ...(window.DRV_CONFIG || {}), quoteRoles: cfg.roles };
  }
  bind();

  const param = new URLSearchParams(window.location.search).get('quote');
  if (param) {
    const incoming = parseShareParam(param, roles());
    if (incoming) {
      await applyShared(incoming);
    } else {
      showToast('Link bảng dự toán không hợp lệ hoặc đã hỏng.', 'warning');
      window.history.replaceState(null, '', window.location.pathname);
    }
  }

  // Phiếu mới chưa có hao hụt tùy chỉnh: lấy mặc định hệ thống.
  const state = readState();
  if (getStats(state).totalSlots === 0 && state.rooms.length === 0 && Number(cfg.wasteDefault) !== state.waste_pct && [0, 5, 10].includes(Number(cfg.wasteDefault))) {
    updateQuote((draft) => {
      draft.waste_pct = Number(cfg.wasteDefault);
    }, 'storage');
  }

  await refresh();
}

if (typeof document !== 'undefined' && document.querySelector('[data-drv-boq]')) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initQuotePage(), { once: true });
  } else {
    initQuotePage();
  }
}
