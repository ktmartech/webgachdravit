/**
 * Entry trang Giỏ hàng `/gio-hang` (04-cart.html, docs/04 §4, docs/15 §5.2).
 * - `<drv-qty-input>` chỉnh số nguyên viên → Store API `update-item` (debounce 400ms) → cập nhật dòng + khối tổng.
 * - Xóa dòng bằng Store API `remove-item` (tắt JS vẫn dùng link `?remove_item=` của WooCommerce).
 * - Dòng quy đổi cập nhật lạc quan ngay khi bấm `- / +` bằng lõi `utils/calc.js` (cùng công thức `Units\Calc`).
 */
import './chunks/qty-input.js';
import { calcCartLine } from './utils/calc.js';
import { drvExt, errorText, removeItem } from './utils/cart-api.js';
import { qs, qsa } from './utils/dom.js';
import { formatDecimalVn, formatVnd } from './utils/format.js';
import { showToast } from './utils/toast.js';

function rowFor(key) {
  return key ? qsa('[data-cart-row]').find((row) => row.dataset.cartKey === key) || null : null;
}

function conversionText(boxText, m2, kg) {
  const parts = [boxText];
  if (m2 > 0) {
    parts.push(`${formatDecimalVn(m2, 2)} m²`);
  }
  if (kg > 0) {
    parts.push(`${formatDecimalVn(kg, 1)} kg`);
  }
  return `= ${parts.filter(Boolean).join(' · ')}`;
}

function paintLoose(row, { boxes, loose, need, unitLabel }) {
  const warn = qs('[data-row-loose-warning]', row);
  if (!warn) {
    return;
  }
  warn.hidden = loose <= 0;
  const text = qs('[data-row-loose-text]', warn);
  if (text && loose > 0) {
    text.textContent =
      `${boxes} thùng + ${loose} ${unitLabel} lẻ: nên lấy chẵn thùng để đồng màu lô ` +
      `và dự phòng cắt gạch (thêm ${need} ${unitLabel}).`;
  }
}

/** Cập nhật lạc quan 1 dòng theo số viên đang nhập (chưa chờ server). */
export function previewRow(row, quantity) {
  const el = qs('drv-qty-input', row);
  if (!el || !(quantity > 0)) {
    return;
  }
  const d = el.dataset;
  const ppb = Number(d.piecesPerBox) || 1;
  const line = calcCartLine({
    pieces: quantity,
    areaPiece: Number(d.areaPiece) || 0,
    piecesPerBox: ppb,
    kgPerBox: (Number(d.kgPerPiece) || 0) * ppb,
    pricePiece: Number(d.priceUnit) || 0,
    unitLabel: d.unitLabel || 'viên',
  });
  const conv = qs('[data-row-conversion]', row);
  if (conv) {
    conv.textContent = conversionText(line.boxText, line.actualM2, line.totalKg);
  }
  const sub = qs('[data-row-subtotal]', row);
  if (sub) {
    sub.textContent = formatVnd(line.lineTotalVnd);
  }
  paintLoose(row, {
    boxes: line.fullBoxes,
    loose: line.loosePieces,
    need: line.neededForNextBox,
    unitLabel: d.unitLabel || 'viên',
  });
}

function setText(selector, text, root = document) {
  for (const el of qsa(selector, root)) {
    el.textContent = text;
  }
}

/** Đồng bộ toàn trang theo response Store API (nguồn sự thật cuối cùng). */
export function applyCart(cart) {
  const items = Array.isArray(cart?.items) ? cart.items : [];
  const root = qs('[data-drv-cart]');
  if (!root) {
    return;
  }
  if (items.length === 0) {
    window.location.reload();
    return;
  }

  const keys = new Set(items.map((i) => String(i.key)));
  for (const row of qsa('[data-cart-row]', root)) {
    if (!keys.has(row.dataset.cartKey || '')) {
      row.remove();
    }
  }

  const minor = Number(cart?.totals?.currency_minor_unit || 0);
  const toVnd = (raw) => Number(raw || 0) / 10 ** minor;

  for (const item of items) {
    const row = rowFor(String(item.key));
    if (!row) {
      continue;
    }
    const ext = drvExt(item);
    const input = qs('drv-qty-input input[type="number"]', row);
    if (input && document.activeElement !== input) {
      input.value = String(item.quantity);
    }
    const conv = qs('[data-row-conversion]', row);
    if (conv) {
      conv.textContent = conversionText(ext.box_text || '', Number(ext.m2_qty) || 0, Number(ext.kg) || 0);
    }
    const sub = qs('[data-row-subtotal]', row);
    if (sub) {
      sub.textContent = formatVnd(toVnd(item.totals?.line_total));
    }
    paintLoose(row, {
      boxes: Number(ext.boxes) || 0,
      loose: Number(ext.loose_pieces) || 0,
      need: Number(ext.needed_for_next_box) || 0,
      unitLabel: ext.unit_label || 'viên',
    });
  }

  const sum = drvExt(cart);
  const summary = qs('[data-cart-summary]');
  if (summary) {
    setText('[data-cart-total-m2]', `${formatDecimalVn(Number(sum.total_m2) || 0, 2)} m²`, summary);
    setText('[data-cart-total-kg]', `${formatDecimalVn(Number(sum.total_kg) || 0, 0)} kg`, summary);
    setText('[data-cart-total-boxes]', String(sum.boxes_text || ''), summary);
    setText('[data-cart-line-count]', String(items.length), summary);
    setText('[data-cart-vehicle-hint]', String(sum.vehicle_hint || ''), summary);
    setText(
      '[data-cart-total-price]',
      formatVnd(toVnd(cart?.totals?.total_items) + toVnd(cart?.totals?.total_items_tax)),
      summary,
    );
    const prompt = qs('[data-cart-project-prompt]', summary);
    if (prompt) {
      prompt.hidden = !(Number(sum.total_m2) >= Number(sum.quote_threshold_m2 || 30));
    }
  }
}

async function handleRemove(link, event) {
  const row = link.closest('[data-cart-row]');
  const key = row?.dataset.cartKey || '';
  if (!key) {
    return;
  }
  event.preventDefault();
  row.setAttribute('aria-busy', 'true');
  row.classList.add('is-removing');
  try {
    const result = await removeItem(key);
    if (!result.ok) {
      row.classList.remove('is-removing');
      showToast(errorText(result), 'error', 4200);
      return;
    }
    const title = qs('[data-row-title]', row)?.textContent?.trim() || 'Sản phẩm';
    showToast(`Đã xóa ${title} khỏi giỏ hàng`, 'success');
  } catch {
    window.location.href = link.href;
  } finally {
    row.removeAttribute('aria-busy');
  }
}

export function initCartPage() {
  const root = qs('[data-drv-cart]');
  if (!root || root.dataset.drvCartBound === '1') {
    return;
  }
  root.dataset.drvCartBound = '1';
  root.classList.add('is-enhanced');

  // JS đã đồng bộ tức thì qua Store API → ẩn nút "Cập nhật giỏ hàng" của luồng tắt JS.
  for (const btn of qsa('[data-drv-cart-update]', root)) {
    btn.hidden = true;
  }
  for (const btn of qsa('[data-qty-round-box], [data-row-loose-warning] [data-qty-add-box]', root)) {
    btn.hidden = false;
  }

  root.addEventListener('drv:qty-input', (event) => {
    const row = event.target instanceof Element ? event.target.closest('[data-cart-row]') : null;
    if (row) {
      previewRow(row, Number(event.detail?.quantity) || 0);
    }
  });

  root.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) {
      return;
    }
    const removeLink = target.closest('[data-cart-remove]');
    if (removeLink) {
      handleRemove(removeLink, event);
      return;
    }
    const row = target.closest('[data-cart-row]');
    const qtyEl = row ? qs('drv-qty-input', row) : null;
    if (!qtyEl || qtyEl.contains(target)) {
      return;
    }
    if (target.closest('[data-qty-round-box]')) {
      event.preventDefault();
      qtyEl.roundToBox();
    } else if (target.closest('[data-qty-add-box]')) {
      event.preventDefault();
      qtyEl.addBox();
    }
  });

  window.addEventListener('drv:cart-updated', (event) => applyCart(event.detail?.cart));
}

if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCartPage, { once: true });
  } else {
    initCartPage();
  }
}
