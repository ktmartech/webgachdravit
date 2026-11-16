/**
 * Client gửi Lead / BOQ an toàn dưới LiteSpeed Page Cache (D-30, docs/15 §1.3):
 * - Lấy token tươi `GET /dravit/v1/nonce` (nonce + `ts`/`sig` HMAC) lúc khách focus form đầu tiên.
 * - Đảm bảo `>= 3s` kể từ lúc server ký `ts` trước khi POST (Time-trap lớp 4).
 * - Gặp `403 drv_bad_nonce` thì làm mới token đúng 1 lần rồi gửi lại.
 */
const TOKEN_TTL_MS = 9 * 60 * 1000;
const MIN_FILL_MS = 3200;
const UTM_KEY = 'drv_utm';
const UTM_FIELDS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'gclid', 'fbclid'];

let token = null;
let inflight = null;

function cfg() {
  return (typeof window !== 'undefined' && window.DRV_CONFIG) || {};
}

export function endpoint(name) {
  const base = String(cfg().restUrl || '/wp-json/dravit/v1').replace(/\/$/, '');
  return `${base}/${name}`;
}

export async function getToken(force = false) {
  const now = Date.now();
  if (!force && token && now - token.fetchedAt < TOKEN_TTL_MS) {
    return token;
  }
  if (!force && inflight) {
    return inflight;
  }
  inflight = (async () => {
    const res = await fetch(cfg().nonceEndpoint || endpoint('nonce'), {
      method: 'GET',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json' },
    });
    if (!res.ok) {
      throw new Error('nonce_failed');
    }
    const data = await res.json();
    token = {
      nonces: data?.nonces || {},
      ts: Number(data?.ts) || 0,
      sig: String(data?.sig || ''),
      fetchedAt: Date.now(),
    };
    return token;
  })();
  try {
    return await inflight;
  } finally {
    inflight = null;
  }
}

/** Nạp sẵn token khi khách bắt đầu tương tác (không chặn UI). */
export function prefetchToken() {
  getToken().catch(() => {});
}

export function resetTokenForTests() {
  token = null;
  inflight = null;
}

/** Lưu UTM của phiên vào `sessionStorage['drv_utm']` (chỉ ghi khi URL có tham số). */
export function captureUtm() {
  try {
    const params = new URLSearchParams(window.location.search);
    const found = {};
    for (const key of UTM_FIELDS) {
      const v = params.get(key);
      if (v) {
        found[key] = v.slice(0, 120);
      }
    }
    if (Object.keys(found).length > 0) {
      window.sessionStorage.setItem(UTM_KEY, JSON.stringify(found));
    }
  } catch {
    // sessionStorage bị chặn: bỏ qua.
  }
}

export function readUtm() {
  try {
    const raw = window.sessionStorage.getItem(UTM_KEY);
    const parsed = raw ? JSON.parse(raw) : {};
    return parsed && typeof parsed === 'object' ? parsed : {};
  } catch {
    return {};
  }
}

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/**
 * POST JSON qua 5 lớp chống spam.
 * @param {'lead'|'quote'} kind Loại nonce.
 * @param {string} url
 * @param {object} body Payload (khối `meta` sẽ được bổ sung token, page_url, utm).
 * @param {{honeypot?: string}} [opts]
 * @returns {Promise<{ok: boolean, status: number, data: any}>}
 */
export async function postProtected(kind, url, body, opts = {}) {
  const send = async (force) => {
    const tk = await getToken(force);
    const wait = MIN_FILL_MS - (Date.now() - tk.fetchedAt);
    if (wait > 0) {
      await sleep(wait);
    }
    const payload = {
      ...body,
      meta: {
        ...(body.meta || {}),
        page_url: window.location.href.split('#')[0],
        utm: readUtm(),
        drv_website: opts.honeypot || '',
        _drv_ts: tk.ts,
        _drv_sig: tk.sig,
      },
    };
    const res = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-DRV-Nonce': String(tk.nonces?.[kind] || ''),
      },
      body: JSON.stringify(payload),
    });
    const data = await res.json().catch(() => null);
    return { ok: res.ok, status: res.status, data };
  };

  let result = await send(false);
  if (result.status === 403 && result.data?.data?.retryable) {
    result = await send(true);
  }
  return result;
}

/** Thông báo lỗi tiếng Việt thân thiện theo mã HTTP. */
export function errorMessage(result) {
  if (result?.data?.message) {
    return String(result.data.message);
  }
  if (result?.status === 429) {
    return 'Bạn gửi quá nhiều yêu cầu. Vui lòng thử lại sau ít phút hoặc gọi hotline.';
  }
  return 'Chưa gửi được yêu cầu. Vui lòng kiểm tra kết nối và thử lại.';
}
