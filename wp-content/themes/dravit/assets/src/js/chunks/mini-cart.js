/**
 * Mini-cart Drawer `#drv-minicart-drawer` — dựng dòng sản phẩm từ Store API (nạp lười khi mở drawer
 * hoặc vừa thêm vào giỏ, docs/15 §3.3). Mỗi dòng LUÔN hiển thị đủ: đơn giá m² + viên, `<drv-qty-input>`,
 * quy đổi `thùng · m² · kg`, thành tiền và nút xóa (docs/04 §4).
 */
import './qty-input.js';
import { drvExt, errorText, removeItem } from '../utils/cart-api.js';
import { qs, qsa } from '../utils/dom.js';
import { formatDecimalVn, formatVnd } from '../utils/format.js';
import { showToast } from '../utils/toast.js';

const FALLBACK_IMG = '/wp-content/themes/dravit/assets/images/placeholders/tile-marble-calacatta.svg';

function el(tag, className = '', text = '') {
  const node = document.createElement(tag);
  if (className) {
    node.className = className;
  }
  if (text) {
    node.textContent = text;
  }
  return node;
}

function icon(name) {
  const i = el('i', `ph ph-${name} drv-icon drv-icon--xs`);
  i.setAttribute('aria-hidden', 'true');
  return i;
}

export function conversionLine(ext, qty) {
  const unit = ext.unit_label || 'viên';
  const parts = [`${qty} ${unit}`];
  if (ext.box_text) {
    parts.push(ext.box_text);
  }
  if (Number(ext.m2_qty) > 0) {
    parts.push(`${formatDecimalVn(Number(ext.m2_qty), 2)} m²`);
  }
  if (Number(ext.kg) > 0) {
    parts.push(`${formatDecimalVn(Number(ext.kg), 1)} kg`);
  }
  return parts.join(' · ');
}

function buildQtyInput(item, ext) {
  const qty = el('drv-qty-input');
  const ppb = Math.max(1, Number(ext.pieces_per_box) || 1);
  const step = Math.max(1, Number(ext.qty_step) || 1);
  const unit = ext.unit_label || 'viên';
  const data = {
    cartKey: String(item.key),
    productId: String(item.id),
    model: ext.model || 'area_pack',
    roundMode: ext.round_mode || 'box',
    piecesPerBox: String(ppb),
    areaPiece: String(ext.area_piece || 0),
    kgPerPiece: String(ext.kg_per_piece || 0),
    priceUnit: String(ext.price_piece || 0),
    qtyMin: String(ext.qty_min || step),
    qtyStep: String(step),
    unitLabel: unit,
  };
  if (ext.max_stock !== null && ext.max_stock !== undefined) {
    data.maxStock = String(ext.max_stock);
  }
  Object.assign(qty.dataset, data);

  const stepper = el('div', 'drv-stepper drv-stepper--cart');
  const stepLabel = step > 1 ? `1 thùng (${step} ${unit})` : `1 ${unit}`;
  const prev = el('button', 'drv-stepper__btn drv-stepper__btn--prev');
  prev.type = 'button';
  prev.dataset.qtyStep = '-1';
  prev.setAttribute('aria-label', `Giảm ${stepLabel}`);
  prev.append(icon('minus'));
  const input = el('input', 'drv-stepper__input u-tabular');
  input.type = 'number';
  input.inputMode = 'numeric';
  input.min = data.qtyMin;
  input.step = String(step);
  input.value = String(item.quantity);
  input.setAttribute('aria-label', `Số ${unit} ${item.sku || item.name || ''}`.trim());
  const next = el('button', 'drv-stepper__btn drv-stepper__btn--next');
  next.type = 'button';
  next.dataset.qtyStep = '1';
  next.setAttribute('aria-label', `Tăng ${stepLabel}`);
  next.append(icon('plus'));
  stepper.append(prev, input, next);
  qty.append(stepper);
  return qty;
}

function buildRow(item, minor) {
  const ext = drvExt(item);
  const unit = ext.unit_label || 'viên';
  const toVnd = (raw) => Number(raw || 0) / 10 ** minor;

  const row = el('article', 'drv-minicart-item');
  row.dataset.minicartItem = String(item.key);

  const img = el('img', 'drv-minicart-item__thumb');
  img.width = 64;
  img.height = 64;
  img.loading = 'lazy';
  img.decoding = 'async';
  img.alt = '';
  img.src = item.images?.[0]?.thumbnail || item.images?.[0]?.src || FALLBACK_IMG;

  const info = el('div', 'drv-minicart-item__info');
  const head = el('div', 'drv-minicart-item__head');
  const sku = el('span', 'drv-minicart-item__sku u-tabular', item.sku || '');
  const remove = el('button', 'drv-minicart-item__remove');
  remove.type = 'button';
  remove.dataset.minicartRemove = String(item.key);
  remove.setAttribute('aria-label', `Xóa ${item.name || 'sản phẩm'} khỏi giỏ hàng`);
  remove.append(icon('trash'));
  head.append(sku, remove);

  const name = el('a', 'drv-minicart-item__name');
  name.href = String(item.permalink || '#').replace(/\/$/, '');
  name.textContent = String(item.name || '').replace(/&#?[a-z0-9]+;/gi, (m) => {
    const t = document.createElement('textarea');
    t.innerHTML = m;
    return t.value;
  });

  const priceM2 = Number(ext.price_m2) || 0;
  const pricePiece = Number(ext.price_piece) || toVnd(item.prices?.price);
  const price = el(
    'div',
    'drv-minicart-item__price u-tabular',
    priceM2 > 0 ? `${formatVnd(priceM2)}/m² · ${formatVnd(pricePiece)}/${unit}` : `${formatVnd(pricePiece)}/${unit}`,
  );

  const conv = el('div', 'drv-minicart-item__conv u-tabular', conversionLine(ext, Number(item.quantity) || 0));
  conv.dataset.minicartConv = '';

  const foot = el('div', 'drv-minicart-item__foot');
  const total = el('strong', 'drv-minicart-item__total u-tabular', formatVnd(toVnd(item.totals?.line_total)));
  foot.append(buildQtyInput(item, ext), total);

  info.append(head, name, price, conv, foot);
  row.append(img, info);
  return row;
}

export function renderRows(cart) {
  const body = qs('[data-minicart-body]');
  if (!body) {
    return;
  }
  const items = Array.isArray(cart?.items) ? cart.items : [];
  const minor = Number(cart?.totals?.currency_minor_unit || 0);
  const empty = qs('[data-minicart-empty]', body);
  const active = document.activeElement;
  const activeKey = active?.closest?.('[data-minicart-item]')?.dataset.minicartItem || '';

  for (const old of qsa('[data-minicart-item]', body)) {
    if (old.dataset.minicartItem !== activeKey) {
      old.remove();
    }
  }
  if (empty) {
    empty.hidden = items.length > 0;
  }

  const frag = document.createDocumentFragment();
  for (const item of items) {
    if (String(item.key) === activeKey) {
      const kept = qsa('[data-minicart-item]', body).find((row) => row.dataset.minicartItem === activeKey);
      const conv = kept ? qs('[data-minicart-conv]', kept) : null;
      if (conv) {
        conv.textContent = conversionLine(drvExt(item), Number(item.quantity) || 0);
      }
      continue;
    }
    frag.append(buildRow(item, minor));
  }
  body.append(frag);

  if (body.dataset.minicartBound !== '1') {
    body.dataset.minicartBound = '1';
    body.addEventListener('click', async (event) => {
      const btn = event.target instanceof Element ? event.target.closest('[data-minicart-remove]') : null;
      if (!btn) {
        return;
      }
      event.preventDefault();
      btn.disabled = true;
      const result = await removeItem(btn.dataset.minicartRemove || '').catch(() => null);
      if (!result?.ok) {
        btn.disabled = false;
        showToast(errorText(result), 'error', 4200);
      }
    });
  }
}
