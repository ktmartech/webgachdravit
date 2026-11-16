/**
 * Điểm chạm thêm vào Bảng dự toán (docs/17 §2.4): Thẻ SP, PDP và nút chuyển Giỏ hàng -> BOQ.
 * Nạp động khi khách bấm `quote-add` / `cart-to-quote` lần đầu.
 */
import { showToast } from '../utils/toast.js';
import {
  DEFAULT_ROOM_ID,
  DEFAULT_ROOM_NAME,
  addRoom,
  addSlot,
  getRoles,
  readState,
  updateQuote,
} from '../utils/quote-store.js';

function boqUrl() {
  return window.DRV_CONFIG?.boqUrl || '/danh-sach-bao-gia';
}

function readTrigger(trigger) {
  const d = trigger.dataset;
  const productId = Number.parseInt(d.drvProductId || d.productId || '0', 10) || 0;
  const targetM2 = Number.parseFloat(d.drvM2 || '0') || 0;
  const pieces = Number.parseInt(d.drvPieces || '0', 10) || 0;
  const isPdp = trigger.hasAttribute('data-pdp-quote-add');
  // PDP: giữ đúng m² khách vừa nhập trên `<drv-unit-calc>` (Q-01, không gán cứng 25 m²).
  // Thẻ SP: khởi đầu bằng đúng 1 thùng tiêu chuẩn (`quick_pieces`), không cộng thêm hao hụt.
  const inputMode = isPdp ? (targetM2 > 0 ? 'm2' : 'vien') : pieces > 0 ? 'vien' : 'm2';
  return {
    productId,
    sku: d.drvSku || d.sku || '',
    title: d.drvTitle || d.drvSku || 'Mẫu gạch',
    targetM2,
    pieces,
    inputMode,
    isPdp,
  };
}

function openLink() {
  return { label: 'Mở bảng dự toán', href: boqUrl() };
}

/**
 * Hộp chọn phòng đích + hạng mục khi phiếu đã có >= 2 phòng (PDP).
 * @returns {Promise<{roomId: string, role: string}|null>}
 */
function pickRoom(state, opener) {
  return new Promise((resolve) => {
    const roles = getRoles();
    const dialog = document.createElement('dialog');
    dialog.className = 'drv-dialog drv-quote-room-picker';
    dialog.setAttribute('aria-labelledby', 'drv-qrp-title');

    const head = document.createElement('div');
    head.className = 'drv-dialog__head';
    const title = document.createElement('h2');
    title.id = 'drv-qrp-title';
    title.className = 'u-text-lg';
    title.textContent = 'Thêm vào phòng nào?';
    head.appendChild(title);

    const body = document.createElement('div');
    body.className = 'drv-dialog__body l-stack l-stack--sm';
    const mkSelect = (id, label, options) => {
      const wrap = document.createElement('div');
      wrap.className = 'drv-field';
      const lbl = document.createElement('label');
      lbl.className = 'drv-label';
      lbl.htmlFor = id;
      lbl.textContent = label;
      const sel = document.createElement('select');
      sel.id = id;
      sel.className = 'drv-select';
      for (const [value, text] of options) {
        const opt = document.createElement('option');
        opt.value = value;
        opt.textContent = text;
        sel.appendChild(opt);
      }
      wrap.append(lbl, sel);
      body.appendChild(wrap);
      return sel;
    };
    const roomSel = mkSelect(
      'drv-qrp-room',
      'Phòng / khu vực',
      state.rooms.map((r) => [r.id, r.name]),
    );
    if (state.active_room_id) {
      roomSel.value = state.active_room_id;
    }
    const roleSel = mkSelect(
      'drv-qrp-role',
      'Hạng mục ốp lát',
      roles.map((r) => [r, r]),
    );

    const foot = document.createElement('div');
    foot.className = 'drv-dialog__foot l-cluster l-cluster--between';
    const cancel = document.createElement('button');
    cancel.type = 'button';
    cancel.className = 'drv-btn drv-btn--outline drv-btn--sm';
    cancel.textContent = 'Hủy';
    const ok = document.createElement('button');
    ok.type = 'button';
    ok.className = 'drv-btn drv-btn--moss drv-btn--sm';
    ok.textContent = 'Thêm vào phòng này';
    foot.append(cancel, ok);

    dialog.append(head, body, foot);
    document.body.appendChild(dialog);

    let result = null;
    ok.addEventListener('click', () => {
      result = { roomId: roomSel.value, role: roleSel.value };
      dialog.close();
    });
    cancel.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
      dialog.remove();
      if (opener instanceof HTMLElement) {
        opener.focus();
      }
      resolve(result);
    });
    dialog.showModal();
    roomSel.focus();
  });
}

export async function quoteAdd(trigger) {
  const info = readTrigger(trigger);
  if (info.productId <= 0) {
    showToast('Không xác định được mẫu gạch. Vui lòng tải lại trang.', 'error');
    return false;
  }

  const roles = getRoles();
  const current = readState();
  let roomId = DEFAULT_ROOM_ID;
  let role = roles[0];

  if (info.isPdp && current.rooms.length >= 2) {
    const picked = await pickRoom(current, trigger);
    if (!picked) {
      return false;
    }
    roomId = picked.roomId;
    role = picked.role;
  } else if (!info.isPdp) {
    const general = current.rooms.find((r) => r.id === DEFAULT_ROOM_ID);
    if (general?.slots.some((s) => s.product_id === info.productId && s.role === role)) {
      showToast(`${info.sku || info.title} đã có trong bảng dự toán`, 'info', 4200, openLink());
      return true;
    }
  }

  const { result } = updateQuote(
    (draft) =>
      addSlot(
        draft,
        roomId,
        {
          product_id: info.productId,
          sku: info.sku,
          role,
          input_mode: info.inputMode,
          target_m2: info.targetM2,
          pieces: info.pieces,
          snap: { title: info.title },
        },
        DEFAULT_ROOM_NAME,
      ),
    info.isPdp ? 'pdp' : 'card',
  );

  if (result?.full) {
    showToast('Bảng dự toán đã đủ 30 hạng mục. Vui lòng gửi phiếu hiện tại trước.', 'warning', 4800, openLink());
    return false;
  }
  showToast(
    result?.merged ? `Đã cộng dồn khối lượng ${info.sku || info.title}` : `Đã thêm ${info.sku || info.title} vào bảng dự toán`,
    'success',
    4200,
    openLink(),
  );
  return true;
}

/**
 * Chuyển toàn bộ giỏ hàng sang phòng mới "Từ giỏ hàng" (giữ nguyên giỏ gốc) rồi mở trang BOQ.
 */
export async function cartToQuote() {
  const url = window.DRV_CONFIG?.wcStoreCartUrl || '/wp-json/wc/store/v1/cart';
  let cart;
  try {
    const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    cart = res.ok ? await res.json() : null;
  } catch {
    cart = null;
  }
  const items = Array.isArray(cart?.items) ? cart.items : [];
  if (items.length === 0) {
    showToast('Giỏ hàng đang trống.', 'warning');
    return false;
  }

  const roles = getRoles();
  updateQuote((draft) => {
    const room = addRoom(draft, 'Từ giỏ hàng');
    if (!room) {
      return;
    }
    for (const item of items) {
      const ext = item.extensions?.dravit || item.extensions?.drv || {};
      const m2Input = Number(ext.m2_input || ext.drv_m2_input || 0);
      addSlot(draft, room.id, {
        product_id: Number(item.id),
        sku: item.sku || '',
        role: roles[0],
        input_mode: m2Input > 0 ? 'm2' : 'vien',
        target_m2: m2Input,
        pieces: Number(item.quantity) || 0,
        snap: { title: item.name || '' },
      });
    }
  }, 'cart');

  window.location.href = boqUrl();
  return true;
}
