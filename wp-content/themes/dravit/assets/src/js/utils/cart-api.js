/**
 * Client Store API `/wc/store/v1/cart` dùng chung Core, Giỏ hàng & Mini-cart (docs/15 §3, D-30).
 * - Nonce tươi lấy từ header `Nonce` của `GET /wc/store/v1/cart` (không phụ thuộc HTML đã cache LiteSpeed),
 *   tự thử lại 1 lần khi 401/403. KHÔNG dùng `/dravit/v1/nonce`: request REST không kèm `X-WP-Nonce` bị WP coi là
 *   khách (user 0) → nonce lệch với Store API khi khách đã đăng nhập.
 * - Response header `Nonce` của Store API được ghi đè vào cache để request kế tiếp không phải gọi lại.
 * - Mọi `quantity` gửi lên là SỐ NGUYÊN VIÊN.
 */

let nonce = '';
let nonceExp = 0;
let lastCart = null;

function cfg(key, fallback) {
  const value = typeof window !== 'undefined' ? window.DRV_CONFIG?.[key] : '';
  return value || fallback;
}

export function cartBaseUrl() {
  return cfg('wcStoreCartUrl', '/wp-json/wc/store/v1/cart');
}

export async function getStoreNonce(force = false) {
  const now = Date.now();
  if (!force && nonce && now < nonceExp) {
    return nonce;
  }
  try {
    // Nonce Store API phải sinh cho đúng danh tính mà Store API nhận diện (khách: WC session · đã đăng nhập: user).
    // Header `Nonce` của GET /cart do chính Store API trả → luôn khớp; GET không cần nonce, `no-store` tránh cache trang.
    const res = await fetch(cartBaseUrl(), {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json' },
    });
    const token = String(res.headers?.get?.('Nonce') || '').trim();
    if (token) {
      nonce = token;
      nonceExp = now + 9 * 60 * 1000;
    }
    return token;
  } catch {
    return '';
  }
}

export function resetCartApiForTests() {
  nonce = '';
  nonceExp = 0;
  lastCart = null;
}

export function getLastCart() {
  return lastCart;
}

function remember(cart) {
  if (cart && typeof cart === 'object' && Array.isArray(cart.items)) {
    lastCart = cart;
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('drv:cart-updated', { detail: { cart } }));
    }
  }
  return cart;
}

/**
 * @returns {Promise<{ok: boolean, status: number, data: any}>}
 */
async function send(path, body) {
  const url = `${cartBaseUrl()}${path}`;
  const run = async (token) =>
    fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', Nonce: token },
      body: JSON.stringify(body),
    });

  let res = await run(await getStoreNonce());
  if (res.status === 401 || res.status === 403) {
    res = await run(await getStoreNonce(true));
  }
  const fresh = res.headers?.get?.('Nonce');
  if (fresh) {
    nonce = fresh;
    nonceExp = Date.now() + 9 * 60 * 1000;
  }
  const data = await res.json().catch(() => null);
  if (res.ok) {
    remember(data);
  }
  return { ok: res.ok, status: res.status, data };
}

export async function fetchCart() {
  try {
    const res = await fetch(cartBaseUrl(), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    if (!res.ok) {
      return null;
    }
    return remember(await res.json());
  } catch {
    return null;
  }
}

export function addItem(id, quantity, drvCartData = {}) {
  return send('/add-item', { id, quantity: Math.trunc(quantity), drv_cart_data: drvCartData });
}

export function updateItem(key, quantity) {
  return send('/update-item', { key, quantity: Math.trunc(quantity) });
}

export function removeItem(key) {
  return send('/remove-item', { key });
}

export function errorText(result, fallback = 'Không cập nhật được giỏ hàng. Vui lòng thử lại.') {
  const raw = String(result?.data?.message || '')
    .replace(/<[^>]*>/g, '')
    .replace(/&[a-z#0-9]+;/gi, ' ')
    .trim();
  return raw || fallback;
}

/** Trích dữ liệu quy đổi `extensions.dravit` của 1 item / của cart. */
export function drvExt(obj) {
  return obj?.extensions?.dravit || obj?.extensions?.drv || {};
}
