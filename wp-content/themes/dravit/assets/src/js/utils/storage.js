export const QUOTE_STORAGE_KEY = 'drv_quote_v2';

export function readJsonStorage(key, fallback = null) {
  try {
    const raw = window.localStorage.getItem(key);
    return raw ? JSON.parse(raw) : fallback;
  } catch {
    return fallback;
  }
}

export function writeJsonStorage(key, value) {
  try {
    window.localStorage.setItem(key, JSON.stringify(value));
    return true;
  } catch {
    return false;
  }
}

export function getQuoteState() {
  const data = readJsonStorage(QUOTE_STORAGE_KEY, null);
  if (data && Array.isArray(data.items)) {
    return data;
  }
  return { version: 2, updated_at: 0, items: [] };
}

export function countQuoteItems() {
  const state = getQuoteState();
  return state.items.filter((item) => item && (Number(item.product_id) > 0 || Boolean(item.sku))).length;
}

export function upsertQuoteItem(entry) {
  const state = getQuoteState();
  const key = entry.product_id ? `id:${entry.product_id}` : `sku:${entry.sku || ''}`;
  const idx = state.items.findIndex((it) => {
    const itKey = it.product_id ? `id:${it.product_id}` : `sku:${it.sku || ''}`;
    return itKey === key;
  });

  if (idx >= 0) {
    state.items[idx] = { ...state.items[idx], ...entry };
  } else if (state.items.length < 30) {
    state.items.push(entry);
  }

  state.updated_at = Date.now();
  writeJsonStorage(QUOTE_STORAGE_KEY, state);

  if (typeof window !== 'undefined') {
    window.dispatchEvent(
      new CustomEvent('drv:quote-updated', {
        detail: { count: state.items.length, state },
      }),
    );
  }

  return state;
}

export function removeQuoteItem(identifier) {
  const state = getQuoteState();
  const target = String(identifier || '');
  state.items = state.items.filter((it) => {
    const byId = it.product_id ? `id:${it.product_id}` : '';
    const bySku = it.sku ? `sku:${it.sku}` : '';
    return byId !== target && bySku !== target && String(it.product_id) !== target && String(it.sku) !== target;
  });

  state.updated_at = Date.now();
  writeJsonStorage(QUOTE_STORAGE_KEY, state);

  if (typeof window !== 'undefined') {
    window.dispatchEvent(
      new CustomEvent('drv:quote-updated', {
        detail: { count: state.items.length, state },
      }),
    );
  }

  return state;
}

export function clearQuoteItems() {
  const state = { version: 2, updated_at: Date.now(), items: [] };
  writeJsonStorage(QUOTE_STORAGE_KEY, state);

  if (typeof window !== 'undefined') {
    window.dispatchEvent(
      new CustomEvent('drv:quote-updated', {
        detail: { count: 0, state },
      }),
    );
  }

  return state;
}


