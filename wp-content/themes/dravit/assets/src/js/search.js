import { qs, qsa } from './utils/dom.js';
import { formatVnd } from './utils/format.js';

const DEBOUNCE_MS = 200;
const DROPDOWN_PRODUCT_LIMIT = 4;
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

function resolveHitEndpoint() {
  if (typeof window !== 'undefined' && window.DRV_CONFIG?.searchHitEndpoint) {
    return String(window.DRV_CONFIG.searchHitEndpoint);
  }
  return '/wp-json/dravit/v1/search/hit';
}

function resolveGachUrl() {
  if (typeof window !== 'undefined' && window.DRV_CONFIG?.gachUrl) {
    return sanitizeSafeUrl(window.DRV_CONFIG.gachUrl, '/gach');
  }
  return '/gach';
}

export function sendSearchProductHit(productId) {
  const pid = Number.parseInt(String(productId ?? '0'), 10);
  if (!Number.isFinite(pid) || pid <= 0) {
    return;
  }
  const url = resolveHitEndpoint();
  const body = JSON.stringify({ product_id: pid });

  try {
    if (typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function' && typeof Blob !== 'undefined') {
      const blob = new Blob([body], { type: 'application/json' });
      if (navigator.sendBeacon(url, blob)) {
        return;
      }
    }
    if (typeof fetch === 'function') {
      fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body,
        keepalive: true,
      }).catch(() => {});
    }
  } catch {
    // Non-blocking telemetry
  }
}

function syncToggleButtons(root, open) {
  const scope = root || document;
  for (const btn of qsa('[data-drv-action="search-toggle"]', scope)) {
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    const icon = qs('i', btn);
    if (icon) {
      icon.className = open ? 'ph ph-x drv-icon drv-icon--sm' : 'ph ph-magnifying-glass drv-icon drv-icon--sm';
    }
  }
}

function formatCompactSize(rawSize) {
  return String(rawSize ?? '')
    .replace(/\s*cm$/i, '')
    .trim();
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
  const siloHeading = qs('[data-search-silo-heading]', wrap);
  const prodHeading = qs('[data-search-prod-heading]', wrap);
  const statusEl = qs('[data-search-status]', wrap);
  const headerEl = wrap.closest('.drv-header');

  if (!input || !panel || !siloList || !prodList) {
    return;
  }

  // Nạp sẵn dữ liệu trạng thái mặc định (6 danh mục phổ biến + 4 SP tìm kiếm nhiều) để mở tức thì 0ms.
  if (typeof window !== 'undefined' && window.DRV_CONFIG?.searchInitial && !responseCache.has('__empty__')) {
    responseCache.set('__empty__', window.DRV_CONFIG.searchInitial);
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
    syncToggleButtons(headerEl, true);
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
    syncToggleButtons(headerEl, false);
  };

  wrap._drvCloseSearch = closePanel;

  const renderSilos = (silos, query) => {
    siloList.textContent = '';
    const list = Array.isArray(silos) ? silos.slice(0, 6) : [];
    const hasQuery = String(query ?? '').trim().length >= 2;

    if (siloHeading) {
      siloHeading.textContent = hasQuery ? `Danh mục khớp (${list.length})` : 'Danh mục phổ biến';
    }

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

      const nameText = String(silo?.name ?? '');
      const link = document.createElement('a');
      link.className = 'drv-search-silo-link';
      link.id = `drv-search-opt-silo-${optCounter}`;
      optCounter += 1;
      link.setAttribute('role', 'option');
      link.setAttribute('aria-selected', 'false');
      link.setAttribute('href', sanitizeSafeUrl(silo?.url, resolveGachUrl()));
      if (nameText) {
        link.setAttribute('title', nameText);
      }

      const nameSpan = document.createElement('span');
      nameSpan.className = 'drv-search-silo-link__name';
      highlightSafeText(nameSpan, nameText, query);
      link.appendChild(nameSpan);

      const countNum = Number(silo?.count ?? 0);
      if (countNum > 0) {
        const badge = document.createElement('span');
        badge.className = 'drv-badge drv-badge--outline u-tabular';
        badge.textContent = `${countNum} mẫu`;
        link.appendChild(badge);
      }

      li.appendChild(link);
      siloList.appendChild(li);
    }

    return optCounter;
  };

  const renderProducts = (products, query, total, startIndex) => {
    prodList.textContent = '';
    const rawList = Array.isArray(products) ? products : [];
    const list = rawList.slice(0, DROPDOWN_PRODUCT_LIMIT);
    const trimmed = String(query ?? '').trim();
    const hasQuery = trimmed.length >= 2;

    if (prodHeading) {
      prodHeading.textContent = hasQuery
        ? `Sản phẩm khớp (${Number(total ?? rawList.length)})`
        : 'Sản phẩm tìm kiếm nhiều';
    }

    if (list.length === 0) {
      const emptyLi = document.createElement('li');
      emptyLi.className = 'drv-search-empty';
      if (hasQuery) {
        emptyLi.appendChild(document.createTextNode('Không tìm thấy mẫu gạch khớp với từ khóa '));
        const strongKw = document.createElement('strong');
        strongKw.textContent = `"${trimmed}"`;
        emptyLi.appendChild(strongKw);
        emptyLi.appendChild(document.createTextNode('.'));
        emptyLi.appendChild(document.createElement('br'));
        const hintSpan = document.createElement('span');
        hintSpan.className = 'u-text-xs';
        hintSpan.textContent = 'Hãy thử tìm theo kích thước (80x80, 60x120) hoặc vân gạch (Marble, Terrazzo).';
        emptyLi.appendChild(hintSpan);
      } else {
        emptyLi.textContent =
          'Nhập mã SKU (VD: DRV-8801), kích thước (60x60, 80x80) hoặc tên vân gạch để tìm tức thì.';
      }
      prodList.appendChild(emptyLi);
      return;
    }

    let idx = startIndex;
    for (const prod of list) {
      const li = document.createElement('li');
      li.setAttribute('role', 'presentation');

      const prodName = String(prod?.name ?? '');
      const prodId = Number.parseInt(String(prod?.id ?? '0'), 10) || 0;

      const link = document.createElement('a');
      link.className = 'drv-search-item';
      link.id = `drv-search-opt-prod-${idx}`;
      idx += 1;
      link.setAttribute('role', 'option');
      link.setAttribute('aria-selected', 'false');
      link.setAttribute('href', sanitizeSafeUrl(prod?.url, resolveGachUrl()));
      if (prodName) {
        link.setAttribute('title', prodName);
      }
      if (prodId > 0) {
        link.dataset.searchProductId = String(prodId);
      }

      const img = document.createElement('img');
      img.className = 'drv-search-item__thumb';
      img.width = 52;
      img.height = 52;
      img.loading = 'lazy';
      img.decoding = 'async';
      img.alt = prodName;
      img.src = sanitizeSafeUrl(prod?.thumb, '');
      link.appendChild(img);

      const info = document.createElement('div');
      info.className = 'drv-search-item__info';

      const titleSpan = document.createElement('div');
      titleSpan.className = 'drv-search-item__title';
      highlightSafeText(titleSpan, prodName, query);
      info.appendChild(titleSpan);

      const metaDiv = document.createElement('div');
      metaDiv.className = 'drv-search-item__meta';

      const compactSize = formatCompactSize(prod?.size);
      if (compactSize) {
        const sizeBadge = document.createElement('span');
        sizeBadge.className = 'drv-badge drv-badge--outline u-tabular';
        highlightSafeText(sizeBadge, compactSize, query);
        metaDiv.appendChild(sizeBadge);
      }

      if (prod?.sku) {
        const skuWrap = document.createElement('span');
        skuWrap.className = 'u-tabular';
        skuWrap.appendChild(document.createTextNode('Mã: '));
        const skuStrong = document.createElement('strong');
        skuStrong.className = 'u-text-navy';
        highlightSafeText(skuStrong, prod.sku, query);
        skuWrap.appendChild(skuStrong);
        metaDiv.appendChild(skuWrap);
      }

      const surfaceText = String(prod?.surface_label ?? '').trim();
      if (surfaceText) {
        const surfaceSpan = document.createElement('span');
        surfaceSpan.appendChild(document.createTextNode('· '));
        const surfaceInner = document.createElement('span');
        highlightSafeText(surfaceInner, surfaceText, query);
        surfaceSpan.appendChild(surfaceInner);
        metaDiv.appendChild(surfaceSpan);
      }

      info.appendChild(metaDiv);
      link.appendChild(info);

      const priceCol = document.createElement('div');
      priceCol.className = 'drv-search-item__price';
      const priceM2 = Number(prod?.price_m2 ?? 0);
      const regM2 = Number(prod?.regular_price_m2 ?? 0);
      const pricePiece = Number(prod?.price_piece ?? 0);
      const unitLabel = String(prod?.unit_label ?? 'viên').trim() || 'viên';
      const isQuote = prod?.price_state === 'quote_only' || priceM2 <= 0;

      if (isQuote) {
        const quoteBadge = document.createElement('span');
        quoteBadge.className = 'u-text-sm u-bold u-text-navy';
        quoteBadge.textContent = 'Liên hệ báo giá';
        priceCol.appendChild(quoteBadge);
      } else {
        const mainPrice = document.createElement('span');
        mainPrice.className = 'u-text-sm u-bold u-text-navy u-tabular';
        mainPrice.textContent = `${formatVnd(priceM2)}/m²`;
        priceCol.appendChild(mainPrice);

        if (prod?.price_state === 'sale' && regM2 > priceM2) {
          const oldPrice = document.createElement('del');
          oldPrice.className = 'u-text-xs u-text-muted u-tabular';
          oldPrice.textContent = `${formatVnd(regM2)}/m²`;
          priceCol.appendChild(oldPrice);
        } else if (pricePiece > 0) {
          const pieceSpan = document.createElement('span');
          pieceSpan.className = 'u-text-xs u-text-muted u-tabular';
          pieceSpan.textContent = `≈ ${formatVnd(pricePiece)}/${unitLabel}`;
          priceCol.appendChild(pieceSpan);
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
      const total = Number(data?.total ?? 0);
      const gachFallback = resolveGachUrl();

      if (trimmed.length >= 2 && total > 0) {
        viewAllLink.setAttribute('href', sanitizeSafeUrl(data?.view_all, gachFallback));
        viewAllLink.textContent = `Xem tất cả (${total})`;
      } else {
        viewAllLink.setAttribute('href', gachFallback);
        viewAllLink.textContent = 'Xem toàn bộ kho';
      }
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
      const url = `${endpoint}${sep}q=${encodeURIComponent(q)}&limit=${DROPDOWN_PRODUCT_LIMIT}`;
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

  input.addEventListener('click', () => {
    syncClearButton();
    if (panel.hidden) {
      fetchSuggestions(input.value);
    }
  });

  input.addEventListener('input', () => {
    syncClearButton();
    scheduleFetch(input.value);
  });

  input.addEventListener('drv:search-immediate', () => {
    if (debounceTimer !== null) {
      clearTimeout(debounceTimer);
      debounceTimer = null;
    }
    syncClearButton();
    fetchSuggestions(input.value);
  });

  panel.addEventListener('click', (event) => {
    const prodLink = event.target instanceof Element ? event.target.closest('[data-search-product-id]') : null;
    if (prodLink instanceof HTMLElement && prodLink.dataset.searchProductId) {
      sendSearchProductHit(prodLink.dataset.searchProductId);
    }
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
        if (chosen instanceof HTMLElement && chosen.dataset.searchProductId) {
          sendSearchProductHit(chosen.dataset.searchProductId);
        }
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
      if (debounceTimer !== null) {
        clearTimeout(debounceTimer);
        debounceTimer = null;
      }
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
    if (!panel.hidden && event.target instanceof Node) {
      const targetEl = event.target instanceof Element ? event.target : null;
      if (!wrap.contains(event.target) && !targetEl?.closest('[data-drv-action="search-toggle"]')) {
        closePanel();
      }
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
