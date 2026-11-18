/**
 * Entry trang Giỏ hàng `/gio-hang` (04-cart.html, docs/04 §4, docs/15 §5.2).
 * - `<drv-qty-input>` chỉnh số nguyên viên → Store API `update-item` (debounce 400ms) → cập nhật dòng + khối tổng.
 * - Xóa dòng bằng Store API `remove-item` (tắt JS vẫn dùng link `?remove_item=` của WooCommerce).
 * - Dòng quy đổi cập nhật lạc quan ngay khi bấm `- / +` bằng lõi `utils/calc.js` (cùng công thức `Units\Calc`).
 */
import './chunks/qty-input.js';
import { calcCartLine } from './utils/calc.js';
import { addItem, drvExt, errorText, removeItem } from './utils/cart-api.js';
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
  if (loose > 0) {
    setRowDetail(row, true);
  }
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


/** Ghi nhớ số lượng lúc mở trang của từng dòng để nút "Đặt lại số lượng mặc định" khôi phục. */
function rememberDefaultQuantities(root) {
  for (const row of qsa('[data-cart-row]', root)) {
    const qtyEl = qs('drv-qty-input', row);
    if (qtyEl && !row.dataset.defaultQty) {
      row.dataset.defaultQty = String(qtyEl.value);
    }
  }
}

/** Cập nhật số món & tổng tiền của khối "Vật tư thi công đề xuất" theo các ô đã chọn. */
function syncFbt(box) {
  const checks = qsa('.drv-fbt-item__check', box);
  let total = 0;
  let count = 0;
  for (const c of checks) {
    if (c.checked) {
      total += Number(c.dataset.fbtPrice) || 0;
      count += 1;
    }
  }
  const totalEl = qs('[data-fbt-total]', box);
  const countEl = qs('[data-fbt-count]', box);
  const submit = qs('[data-drv-action="cart-fbt-submit"]', box);
  if (totalEl) {
    totalEl.textContent = formatVnd(total);
  }
  if (countEl) {
    countEl.textContent = String(count);
  }
  if (submit) {
    submit.disabled = count === 0;
  }
}

async function submitFbt(button) {
  const box = button.closest('[data-drv-fbt]');
  if (!box || button.disabled) {
    return;
  }
  const picked = qsa('.drv-fbt-item__check:checked', box);
  if (picked.length === 0) {
    return;
  }

  button.disabled = true;
  button.setAttribute('aria-busy', 'true');
  let failed = '';
  for (const c of picked) {
    const result = await addItem(Number(c.dataset.fbtId), Number(c.dataset.fbtQty) || 1);
    if (!result?.ok) {
      failed = errorText(result);
      break;
    }
  }
  button.removeAttribute('aria-busy');

  if (failed) {
    button.disabled = false;
    showToast(failed, 'error', 3600);
    return;
  }

  showToast('Đã thêm vật tư vào đơn, đang cập nhật bảng kê...', 'success', 1800);
  window.location.reload();
}

const LABEL_OPEN = 'Xem chi tiết & quy đổi';
const LABEL_CLOSE = 'Thu gọn chi tiết';

/**
 * Mở / đóng khay chi tiết & quy đổi của 1 dòng giỏ hàng (mockup `04-cart.html`).
 *
 * @param {Element} row
 * @param {boolean} open
 */
function setRowDetail(row, open) {
  const drawer = qs('[data-cart-row-drawer]', row);
  const toggle = qs('[data-drv-action="cart-toggle-row-detail"]', row);
  if (!drawer) {
    return;
  }
  drawer.hidden = !open;
  if (toggle) {
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.classList.toggle('is-expanded', open);
    const label = qs('[data-toggle-label]', toggle);
    if (label) {
      label.textContent = open ? LABEL_CLOSE : LABEL_OPEN;
    }
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
  for (const btn of qsa('[data-qty-round-box], [data-row-loose-warning] [data-qty-add-box], [data-drv-action="cart-reset-qty"]', root)) {
    btn.hidden = false;
  }
  rememberDefaultQuantities(root);
  for (const box of qsa('[data-drv-fbt]', root)) {
    syncFbt(box);
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
    const fbtSubmit = target.closest('[data-drv-action="cart-fbt-submit"]');
    if (fbtSubmit) {
      event.preventDefault();
      void submitFbt(fbtSubmit);
      return;
    }
    if (target.closest('[data-drv-action="cart-reset-qty"]')) {
      event.preventDefault();
      let changed = 0;
      for (const r of qsa('[data-cart-row]', root)) {
        const el = qs('drv-qty-input', r);
        const def = Number(r.dataset.defaultQty);
        if (el && def > 0 && Number(el.value) !== def) {
          el.setValue(def);
          changed += 1;
        }
      }
      showToast(changed > 0 ? 'Đã đặt lại số lượng về mặc định' : 'Số lượng đang ở mức mặc định', 'default', 2200);
      return;
    }
    const detailToggle = target.closest('[data-drv-action="cart-toggle-row-detail"]');
    if (detailToggle) {
      event.preventDefault();
      const toggleRow = detailToggle.closest('[data-cart-row]');
      if (toggleRow) {
        setRowDetail(toggleRow, detailToggle.getAttribute('aria-expanded') !== 'true');
      }
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

  root.addEventListener('change', (event) => {
    const check = event.target instanceof Element ? event.target.closest('.drv-fbt-item__check') : null;
    const box = check ? check.closest('[data-drv-fbt]') : null;
    if (box) {
      syncFbt(box);
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
