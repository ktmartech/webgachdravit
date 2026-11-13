import { qs, qsa } from './utils/dom.js';
import { formatVnd } from './utils/format.js';

const DEBOUNCE_MS = 200;
const responseCache = new Map();
const boundContainers = new WeakSet();

let activeNavigate = (url) => {
  if (typeof window !== 'undefined' && window.location && typeof window.location.assign === 'function') {
    window.location.assign(url);
  }
};

export function setSearchNavigateHandler(fn) {
  if (typeof fn === 'function') {
    activeNavigate = fn;
  }
}

export function asciiFoldJs(str) {
  return String(str ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[đĐ]/g, 'd')
    .toLowerCase();
}

export function sanitizeSafeUrl(rawUrl, fallback = '/gach') {
  const val = String(rawUrl ?? '').trim();
  if (!val) {
    return fallback;
  }
  if (val.startsWith('/') && !val.startsWith('//')) {
    return val;
  }
  try {
    const parsed = new URL(val, typeof window !== 'undefined' ? window.location.origin : 'https://gachdravit.vn');
    if (parsed.protocol === 'http:' || parsed.protocol === 'https:') {
      return parsed.href;
    }
  } catch {
    return fallback;
  }
  return fallback;
}

/**
 * Gắn văn bản có highlight từ khóa không dấu vào `container` bằng 100% DOM node an toàn (0 `innerHTML` chống XSS).
 */
export function highlightSafeText(container, text, query) {
  const source = String(text ?? '');
  container.textContent = '';

  const cleanQuery = asciiFoldJs(query).trim();
  if (!source || cleanQuery.length < 2) {
    container.textContent = source;
    return;
  }

  // Ánh xạ từng code point gốc sang vị trí trong chuỗi đã khử dấu để cắt lát chính xác.
  const chars = Array.from(source);
  let foldedStr = '';
  const foldedToOrigStart = [];
  const foldedToOrigEnd = [];

  for (let i = 0; i < chars.length; i += 1) {
    const f = asciiFoldJs(chars[i]);
    for (let k = 0; k < f.length; k += 1) {
      foldedToOrigStart.push(i);
      foldedToOrigEnd.push(i + 1);
    }
    foldedStr += f;
  }

  const tokens = Array.from(
    new Set(
      cleanQuery
        .split(/\s+/)
        .map((t) => t.trim())
        .filter((t) => t.length >= 2),
    ),
  );

  if (tokens.length === 0) {
    container.textContent = source;
    return;
  }

  const ranges = [];
  for (const tok of tokens) {
    let from = 0;
    while (from < foldedStr.length) {
      const idx = foldedStr.indexOf(tok, from);
      if (idx === -1) {
        break;
      }
      const endFolded = idx + tok.length - 1;
      const origStart = foldedToOrigStart[idx] ?? 0;
      const origEnd = foldedToOrigEnd[endFolded] ?? chars.length;
      ranges.push([origStart, origEnd]);
      from = idx + tok.length;
    }
  }

  if (ranges.length === 0) {
    container.textContent = source;
    return;
  }

  ranges.sort((a, b) => a[0] - b[0] || b[1] - a[1]);
  const merged = [ranges[0]];
  for (let i = 1; i < ranges.length; i += 1) {
    const prev = merged[merged.length - 1];
    const cur = ranges[i];
    if (cur[0] <= prev[1]) {
      prev[1] = Math.max(prev[1], cur[1]);
    } else {
      merged.push(cur);
    }
  }

  let cursor = 0;
  for (const [start, end] of merged) {
    if (start > cursor) {
      container.appendChild(document.createTextNode(chars.slice(cursor, start).join('')));
    }
    const mark = document.createElement('mark');
    mark.className = 'drv-search-hl';
    mark.textContent = chars.slice(start, end).join('');
    container.appendChild(mark);
    cursor = end;
  }
  if (cursor < chars.length) {
    container.appendChild(document.createTextNode(chars.slice(cursor).join('')));
  }
}

function resolveEndpoint() {
  if (typeof window !== 'undefined' && window.DRV_CONFIG?.searchEndpoint) {
    return String(window.DRV_CONFIG.searchEndpoint);
  }
  return '/wp-json/dravit/v1/search';
}

function bindSearchContainer(wrap) {
  if (!wrap || boundContainers.has(wrap)) {
    return;
  }
  boundContainers.add(wrap);

  const input = qs('[data-search-input], #global-search-input, input[type="search"]', wrap);
  const panel = qs('[data-search-panel], .drv-search-drop', wrap);
  const backdrop = qs('[data-drv-search-backdrop]', wrap);
  const clearBtn = qs('[data-search-clear]', wrap);
  const siloList = qs('[data-search-silos]', wrap);
  const prodList = qs('[data-search-products]', wrap);
  const viewAllLink = qs('[data-search-view-all]', wrap);
  const prodHeading = qs('[data-search-prod-heading]', wrap);
  const statusEl = qs('[data-search-status]', wrap);
  const headerEl = wrap.closest('.drv-header');

  if (!input || !panel || !siloList || !prodList) {
    return;
  }

  let debounceTimer = null;
  let activeController = null;
  let activeIndex = -1;
  let optionEls = [];

  const syncClearButton = () => {
    if (clearBtn) {
      clearBtn.hidden = input.value.trim() === '';
    }
  };

  const setActiveOption = (nextIdx) => {
    optionEls = qsa('[role="option"]', panel);
    if (optionEls.length === 0 || nextIdx < 0) {
      activeIndex = -1;
      for (const el of optionEls) {
        el.classList.remove('is-active');
        el.setAttribute('aria-selected', 'false');
      }
      input.setAttribute('aria-activedescendant', '');
      return;
    }

    const bounded = ((nextIdx % optionEls.length) + optionEls.length) % optionEls.length;
    activeIndex = bounded;

    optionEls.forEach((el, idx) => {
      const selected = idx === bounded;
      el.classList.toggle('is-active', selected);
      el.setAttribute('aria-selected', selected ? 'true' : 'false');
      if (selected) {
        input.setAttribute('aria-activedescendant', el.id);
        if (typeof el.scrollIntoView === 'function') {
          el.scrollIntoView({ block: 'nearest' });
        }
      }
    });
  };

  const openPanel = () => {
    panel.hidden = false;
    wrap.classList.add('is-open');
    headerEl?.classList.add('has-search-open');
    if (backdrop) {
      backdrop.hidden = false;
    }
    input.setAttribute('aria-expanded', 'true');
  };

  const closePanel = () => {
    panel.hidden = true;
    wrap.classList.remove('is-open');
    headerEl?.classList.remove('has-search-open');
    if (backdrop) {
      backdrop.hidden = true;
    }
    input.setAttribute('aria-expanded', 'false');
    setActiveOption(-1);
  };

  const renderSilos = (silos, query) => {
    siloList.textContent = '';
    const list = Array.isArray(silos) ? silos : [];

    if (list.length === 0) {
      const li = document.createElement('li');
      li.className = 'u-text-xs u-text-muted';
      li.textContent = 'Không có danh mục khớp trực tiếp.';
      siloList.appendChild(li);
      return 0;
    }

    let optCounter = 0;
    for (const silo of list) {
      const li = document.createElement('li');
      li.setAttribute('role', 'presentation');

      const link = document.createElement('a');
      link.className = 'drv-search-silo-link';
      link.id = `drv-search-opt-silo-${optCounter}`;
      optCounter += 1;
      link.setAttribute('role', 'option');
      link.setAttribute('aria-selected', 'false');
      link.setAttribute('href', sanitizeSafeUrl(silo?.url, '/gach'));

      const nameSpan = document.createElement('span');
      nameSpan.className = 'drv-search-silo-link__name';
      highlightSafeText(nameSpan, silo?.name ?? '', query);
      link.appendChild(nameSpan);

      const countNum = Number(silo?.count ?? 0);
      if (countNum > 0) {
        const badge = document.createElement('span');
        badge.className = 'u-text-xs u-text-muted u-tabular';
        badge.textContent = `${countNum}`;
        link.appendChild(badge);
      }

      li.appendChild(link);
      siloList.appendChild(li);
    }

    return optCounter;
  };

  const renderProducts = (products, query, total, startIndex) => {
    prodList.textContent = '';
    const list = Array.isArray(products) ? products : [];
    const trimmed = String(query ?? '').trim();

    if (prodHeading) {
      prodHeading.textContent =
        trimmed.length >= 2 ? `Sản phẩm khớp (${Number(total ?? list.length)})` : 'Gợi ý tra cứu nhanh';
    }

    if (list.length === 0) {
      const emptyLi = document.createElement('li');
      emptyLi.className = 'drv-search-empty';
      if (trimmed.length >= 2) {
        emptyLi.textContent = `Không tìm thấy mẫu gạch khớp "${trimmed}". Vui lòng thử mã SKU, kích thước (VD: 80x80) hoặc gửi ảnh mẫu qua Zalo.`;
      } else {
        emptyLi.textContent = 'Nhập mã SKU (VD: DRV-8801), kích thước (60x60, 80x80) hoặc tên vân gạch để tìm tức thì.';
      }
      prodList.appendChild(emptyLi);
      return;
    }

    let idx = startIndex;
    for (const prod of list) {
      const li = document.createElement('li');
      li.setAttribute('role', 'presentation');

      const link = document.createElement('a');
      link.className = 'drv-search-item';
      link.id = `drv-search-opt-prod-${idx}`;
      idx += 1;
      link.setAttribute('role', 'option');
      link.setAttribute('aria-selected', 'false');
      link.setAttribute('href', sanitizeSafeUrl(prod?.url, '/gach'));

      const img = document.createElement('img');
      img.className = 'drv-search-item__thumb';
      img.width = 52;
      img.height = 52;
      img.loading = 'lazy';
      img.decoding = 'async';
      img.alt = '';
      img.src = sanitizeSafeUrl(prod?.thumb, '');
      link.appendChild(img);

      const info = document.createElement('div');
      info.className = 'drv-search-item__info';

      const titleSpan = document.createElement('span');
      titleSpan.className = 'drv-search-item__title';
      highlightSafeText(titleSpan, prod?.name ?? '', query);
      info.appendChild(titleSpan);

      const metaDiv = document.createElement('div');
      metaDiv.className = 'drv-search-item__meta';
      if (prod?.sku) {
        const skuStrong = document.createElement('strong');
        skuStrong.className = 'u-tabular';
        highlightSafeText(skuStrong, prod.sku, query);
        metaDiv.appendChild(skuStrong);
      }
      const metaBits = [prod?.size, prod?.surface_label, prod?.showroom_shelf ? `Kệ ${prod.showroom_shelf}` : '']
        .map((v) => String(v ?? '').trim())
        .filter(Boolean);
      if (metaBits.length > 0) {
        const specSpan = document.createElement('span');
        specSpan.textContent = metaBits.join(' · ');
        metaDiv.appendChild(specSpan);
      }
      info.appendChild(metaDiv);
      link.appendChild(info);

      const priceCol = document.createElement('div');
      priceCol.className = 'drv-search-item__price u-tabular';
      const priceM2 = Number(prod?.price_m2 ?? 0);
      const regM2 = Number(prod?.regular_price_m2 ?? 0);
      const isQuote = prod?.price_state === 'quote_only' || priceM2 <= 0;

      if (isQuote) {
        const quoteBadge = document.createElement('span');
        quoteBadge.className = 'u-text-xs u-text-moss';
        quoteBadge.textContent = 'Liên hệ báo giá';
        priceCol.appendChild(quoteBadge);
      } else {
        const mainPrice = document.createElement('strong');
        mainPrice.className = 'u-text-sm';
        mainPrice.textContent = `${formatVnd(priceM2)}/m²`;
        priceCol.appendChild(mainPrice);

        if (prod?.price_state === 'sale' && regM2 > priceM2) {
          const oldPrice = document.createElement('del');
          oldPrice.className = 'u-text-xs u-text-muted';
          oldPrice.textContent = `${formatVnd(regM2)}/m²`;
          priceCol.appendChild(oldPrice);
        }
      }

      link.appendChild(priceCol);
      li.appendChild(link);
      prodList.appendChild(li);
    }
  };

  const renderPayload = (data, query) => {
    const siloCount = renderSilos(data?.silos, query);
    renderProducts(data?.products, query, data?.total, siloCount);

    if (viewAllLink) {
      const trimmed = String(query ?? '').trim();
      viewAllLink.setAttribute('href', sanitizeSafeUrl(data?.view_all, '/gach'));
      viewAllLink.textContent =
        trimmed.length >= 2 && Number(data?.total ?? 0) > 0
          ? `Xem tất cả (${Number(data.total)})`
          : 'Xem tất cả danh mục';
    }

    optionEls = qsa('[role="option"]', panel);
    setActiveOption(-1);
    openPanel();

    if (statusEl) {
      const trimmed = String(query ?? '').trim();
      if (trimmed.length >= 2) {
        const total = Number(data?.total ?? 0);
        statusEl.textContent =
          total > 0 ? `Tìm thấy ${total} mẫu gạch cho "${trimmed}".` : `Không tìm thấy sản phẩm cho "${trimmed}".`;
      } else {
        statusEl.textContent = '';
      }
    }
  };

  const fetchSuggestions = async (rawVal) => {
    const q = String(rawVal ?? '').trim();
    const cacheKey = q.length < 2 ? '__empty__' : asciiFoldJs(q);

    if (responseCache.has(cacheKey)) {
      renderPayload(responseCache.get(cacheKey), q);
      return;
    }

    if (activeController) {
      activeController.abort();
    }
    const controller = new AbortController();
    activeController = controller;

    try {
      const endpoint = resolveEndpoint();
      const sep = endpoint.includes('?') ? '&' : '?';
      const url = `${endpoint}${sep}q=${encodeURIComponent(q)}&limit=6`;
      const res = await fetch(url, {
        method: 'GET',
        headers: { Accept: 'application/json' },
        signal: controller.signal,
      });

      if (!res.ok) {
        return;
      }

      const data = await res.json();
      if (controller.signal.aborted) {
        return;
      }

      responseCache.set(cacheKey, data);
      renderPayload(data, q);
    } catch (err) {
      if (err && err.name === 'AbortError') {
        return;
      }
    } finally {
      if (activeController === controller) {
        activeController = null;
      }
    }
  };

  const scheduleFetch = (val) => {
    if (debounceTimer !== null) {
      clearTimeout(debounceTimer);
    }
    debounceTimer = setTimeout(() => {
      debounceTimer = null;
      fetchSuggestions(val);
    }, DEBOUNCE_MS);
  };

  input.addEventListener('focus', () => {
    syncClearButton();
    if (panel.hidden) {
      fetchSuggestions(input.value);
    }
  });

  input.addEventListener('input', () => {
    syncClearButton();
    scheduleFetch(input.value);
  });

  input.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      if (panel.hidden) {
        fetchSuggestions(input.value);
        return;
      }
      optionEls = qsa('[role="option"]', panel);
      if (optionEls.length > 0) {
        setActiveOption(activeIndex + 1);
      }
      return;
    }

    if (event.key === 'ArrowUp') {
      event.preventDefault();
      if (panel.hidden) {
        return;
      }
      optionEls = qsa('[role="option"]', panel);
      if (optionEls.length > 0) {
        setActiveOption(activeIndex <= 0 ? optionEls.length - 1 : activeIndex - 1);
      }
      return;
    }

    if (event.key === 'Enter' && !panel.hidden && activeIndex >= 0) {
      optionEls = qsa('[role="option"]', panel);
      const chosen = optionEls[activeIndex];
      const href = chosen?.getAttribute('href');
      if (href) {
        event.preventDefault();
        closePanel();
        activeNavigate(href);
      }
      return;
    }

    if (event.key === 'Escape') {
      if (!panel.hidden) {
        event.preventDefault();
        event.stopPropagation();
        closePanel();
      } else if (input.value !== '') {
        event.preventDefault();
        input.value = '';
        syncClearButton();
      }
    }
  });

  if (clearBtn) {
    clearBtn.addEventListener('click', (event) => {
      event.preventDefault();
      input.value = '';
      syncClearButton();
      input.focus();
      fetchSuggestions('');
    });
  }

  if (backdrop) {
    backdrop.addEventListener('click', closePanel);
  }

  document.addEventListener('click', (event) => {
    if (!panel.hidden && event.target instanceof Node && !wrap.contains(event.target)) {
      closePanel();
    }
  });

  syncClearButton();
  if (document.activeElement === input) {
    fetchSuggestions(input.value);
  }
}

export function initLiveSearch(root = document) {
  for (const wrap of qsa('[data-drv-search]', root)) {
    bindSearchContainer(wrap);
  }
}

export function resetSearchForTests() {
  responseCache.clear();
  activeNavigate = (url) => {
    if (typeof window !== 'undefined' && window.location && typeof window.location.assign === 'function') {
      window.location.assign(url);
    }
  };
}

