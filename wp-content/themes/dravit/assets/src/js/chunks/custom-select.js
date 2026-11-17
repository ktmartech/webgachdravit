/**
 * Global Custom Select & Dropdown Popover (`assets/src/js/chunks/custom-select.js`).
 * Đồng bộ 100% phong cách `.drv-dropdown-popover` / `.drv-move-popover` (Danh sách báo giá BOQ)
 * cho toàn bộ `<select>` trên site (Checkout Tỉnh/Phường, Danh mục Sắp xếp, PDP/BOQ Hao hụt,
 * Hạng mục phòng, Form Lead và Fluent Forms) mà vẫn giữ nguyên `<select>` gốc cho Form/Validation/No-JS.
 */

export const CUSTOM_SELECT_SELECTOR =
  'select.drv-select, select.drv-calc__waste-select, .fluentform select.ff-el-form-control';

const SEARCH_THRESHOLD = 10;
const INDEX_DESC =
  typeof HTMLSelectElement !== 'undefined'
    ? Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'selectedIndex')
    : null;

/** @type {Map<HTMLSelectElement, ReturnType<typeof createInstance>>} */
const instances = new Map();
let activeInstance = null;
let uidCounter = 0;
let globalBound = false;
let protoPatched = false;
let domObserver = null;

function escSelector(val) {
  if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
    return CSS.escape(val);
  }
  return String(val).replace(/["\\]/g, '\\$&');
}

function ensurePrototypeHook() {
  if (protoPatched || typeof HTMLSelectElement === 'undefined') {
    return;
  }
  protoPatched = true;
  const proto = HTMLSelectElement.prototype;
  const valDesc = Object.getOwnPropertyDescriptor(proto, 'value');
  const idxDesc = Object.getOwnPropertyDescriptor(proto, 'selectedIndex');
  const disDesc = Object.getOwnPropertyDescriptor(proto, 'disabled');

  if (valDesc?.get && valDesc?.set) {
    Object.defineProperty(proto, 'value', {
      configurable: true,
      enumerable: valDesc.enumerable,
      get: valDesc.get,
      set(v) {
        valDesc.set.call(this, v);
        instances.get(this)?.onExternalValue();
      },
    });
  }
  if (idxDesc?.get && idxDesc?.set) {
    Object.defineProperty(proto, 'selectedIndex', {
      configurable: true,
      enumerable: idxDesc.enumerable,
      get: idxDesc.get,
      set(v) {
        idxDesc.set.call(this, v);
        instances.get(this)?.onExternalValue();
      },
    });
  }
  if (disDesc?.get && disDesc?.set) {
    Object.defineProperty(proto, 'disabled', {
      configurable: true,
      enumerable: disDesc.enumerable,
      get: disDesc.get,
      set(v) {
        disDesc.set.call(this, v);
        instances.get(this)?.onExternalAttr();
      },
    });
  }
}

export function asciiFold(str) {
  return String(str || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/g, 'd')
    .replace(/Đ/g, 'D')
    .toLowerCase()
    .trim();
}

function isEligibleSelect(el) {
  if (!(el instanceof HTMLSelectElement)) {
    return false;
  }
  if (el.multiple || (el.size && el.size > 1) || el.hasAttribute('data-drv-native-select')) {
    return false;
  }
  return true;
}

function extractFieldTitle(select) {
  if (select.dataset.selectTitle) {
    return select.dataset.selectTitle.trim();
  }
  let label = null;
  if (select.id) {
    const rootNode = select.getRootNode?.();
    const scope =
      rootNode && typeof rootNode.querySelector === 'function' ? rootNode : document;
    label = scope.querySelector(`label[for="${escSelector(select.id)}"]`);
  }
  if (!label) {
    label = select.closest('label');
  }
  if (label) {
    const clone = label.cloneNode(true);
    for (const extra of clone.querySelectorAll('.drv-label__req, .drv-label__hint, [data-ward-status]')) {
      extra.remove();
    }
    const clean = (clone.textContent || '').replace(/[:*]+$/g, '').replace(/\s+/g, ' ').trim();
    if (clean) {
      return `${clean}:`;
    }
  }
  const aria = (select.getAttribute('aria-label') || '').replace(/[:*]+$/g, '').trim();
  return aria ? `${aria}:` : '';
}

function copyTriggerClasses(select) {
  const classes = ['drv-select'];
  for (const cls of select.classList) {
    if (cls !== 'drv-select-ui__native' && cls !== 'drv-select') {
      classes.push(cls);
    }
  }
  classes.push('drv-select-ui__btn');
  return classes.join(' ');
}

function supportsTopLayerPopover(el) {
  return typeof el?.showPopover === 'function' && typeof el?.hidePopover === 'function';
}

function createInstance(select) {
  uidCounter += 1;
  const uid = select.id ? `${select.id}-${uidCounter}` : `drv-sel-${uidCounter}`;
  const popId = `drv-pop-${uid}`;
  const listId = `drv-list-${uid}`;

  const wrap = document.createElement('div');
  wrap.className = 'drv-select-ui';

  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = copyTriggerClasses(select);
  btn.setAttribute('role', 'combobox');
  btn.setAttribute('aria-haspopup', 'listbox');
  btn.setAttribute('aria-expanded', 'false');
  btn.setAttribute('aria-controls', listId);

  // Liên kết nhãn accessible name chuẩn WCAG 2.2 AA
  const assocLabel = select.id ? document.querySelector(`label[for="${escSelector(select.id)}"]`) : null;
  if (assocLabel) {
    if (!assocLabel.id) {
      assocLabel.id = `drv-lbl-${uid}`;
    }
    btn.setAttribute('aria-labelledby', assocLabel.id);
  } else if (select.getAttribute('aria-label')) {
    btn.setAttribute('aria-label', select.getAttribute('aria-label') || '');
  } else {
    const fallbackTitle = extractFieldTitle(select).replace(/:$/, '');
    if (fallbackTitle) {
      btn.setAttribute('aria-label', fallbackTitle);
    }
  }

  const labelSpan = document.createElement('span');
  labelSpan.className = 'drv-select-ui__label';

  const caret = document.createElement('i');
  caret.className = 'ph ph-caret-down drv-select-ui__caret';
  caret.setAttribute('aria-hidden', 'true');

  btn.append(labelSpan, caret);

  const popover = document.createElement('div');
  popover.id = popId;
  popover.className = 'drv-dropdown-popover drv-select-popover';
  popover.hidden = true;
  const usePopoverApi = supportsTopLayerPopover(popover);
  if (usePopoverApi) {
    popover.setAttribute('popover', 'manual');
  }

  const titleEl = document.createElement('div');
  titleEl.className = 'drv-dropdown-popover__title';
  const titleText = extractFieldTitle(select);
  titleEl.textContent = titleText;
  titleEl.hidden = !titleText;

  const searchWrap = document.createElement('div');
  searchWrap.className = 'drv-dropdown-popover__search';
  searchWrap.hidden = true;
  const searchIcon = document.createElement('i');
  searchIcon.className = 'ph ph-magnifying-glass drv-icon--xs';
  searchIcon.setAttribute('aria-hidden', 'true');
  const searchInput = document.createElement('input');
  searchInput.type = 'search';
  searchInput.className = 'drv-dropdown-popover__search-input';
  searchInput.placeholder = 'Tìm nhanh...';
  searchInput.autocomplete = 'off';
  searchInput.setAttribute('aria-label', 'Tìm nhanh trong danh sách');
  searchWrap.append(searchIcon, searchInput);

  const listEl = document.createElement('div');
  listEl.id = listId;
  listEl.className = 'drv-dropdown-popover__list';
  listEl.setAttribute('role', 'listbox');
  if (titleText) {
    listEl.setAttribute('aria-label', titleText.replace(/:$/, ''));
  }

  const emptyEl = document.createElement('div');
  emptyEl.className = 'drv-dropdown-popover__empty';
  emptyEl.textContent = 'Không tìm thấy mục phù hợp';
  emptyEl.hidden = true;

  popover.append(titleEl, searchWrap, listEl, emptyEl);

  // Chèn wrapper tại đúng vị trí của `<select>` trong DOM
  select.parentNode?.insertBefore(wrap, select);
  select.classList.add('drv-select-ui__native');
  select.dataset.drvSelectReady = '1';
  select.tabIndex = -1;
  select.setAttribute('aria-hidden', 'true');
  wrap.append(select, btn, popover);

  /** @type {HTMLButtonElement[]} */
  let itemButtons = [];
  let activeIdx = -1;
  let openAbort = null;
  let internalMutation = false;

  function syncAttributes() {
    btn.className = copyTriggerClasses(select);
    btn.disabled = select.disabled;
    wrap.classList.toggle('is-disabled', select.disabled);

    for (const attr of ['aria-invalid', 'aria-required', 'aria-busy', 'aria-describedby']) {
      const val = select.getAttribute(attr);
      if (val !== null) {
        btn.setAttribute(attr, val);
      } else {
        btn.removeAttribute(attr);
      }
    }
  }

  function syncSelectedDisplay() {
    const idx = select.selectedIndex;
    const selectedOpt = idx >= 0 ? select.options[idx] : select.options[0] || null;
    const text = selectedOpt ? (selectedOpt.textContent || '').trim() : '';
    labelSpan.textContent = text;
    const isPlaceholder = Boolean(selectedOpt && selectedOpt.value === '' && (select.required || idx === 0));
    btn.classList.toggle('is-placeholder', isPlaceholder);

    for (const itemBtn of itemButtons) {
      const optIdx = Number(itemBtn.dataset.optIndex);
      const isSel = optIdx === idx;
      itemBtn.classList.toggle('is-selected', isSel);
      itemBtn.setAttribute('aria-selected', isSel ? 'true' : 'false');
      const check = itemBtn.querySelector('.drv-dropdown-popover__check');
      if (isSel && !check) {
        const ck = document.createElement('i');
        ck.className = 'ph-bold ph-check drv-icon--xs drv-dropdown-popover__check';
        ck.setAttribute('aria-hidden', 'true');
        itemBtn.appendChild(ck);
      } else if (!isSel && check) {
        check.remove();
      }
    }
  }

  function rebuildOptions() {
    listEl.replaceChildren();
    itemButtons = [];
    let optIndex = 0;

    const appendOption = (opt, container) => {
      const idx = optIndex;
      optIndex += 1;
      const item = document.createElement('button');
      item.type = 'button';
      item.id = `${listId}-opt-${idx}`;
      item.className = 'drv-dropdown-popover__item';
      if (opt.value === '') {
        item.classList.add('is-placeholder');
      }
      item.setAttribute('role', 'option');
      item.dataset.optIndex = String(idx);
      item.dataset.optValue = opt.value;
      item.dataset.searchKey = asciiFold(opt.textContent || '');
      item.disabled = opt.disabled;

      const txt = document.createElement('span');
      txt.className = 'drv-dropdown-popover__item-label';
      txt.textContent = (opt.textContent || '').trim();
      item.appendChild(txt);

      container.appendChild(item);
      itemButtons.push(item);
    };

    for (const child of select.children) {
      if (child instanceof HTMLOptGroupElement) {
        const grpLabel = document.createElement('div');
        grpLabel.className = 'drv-dropdown-popover__group';
        grpLabel.setAttribute('role', 'presentation');
        grpLabel.textContent = child.label;
        listEl.appendChild(grpLabel);
        for (const opt of child.children) {
          if (opt instanceof HTMLOptionElement) {
            appendOption(opt, listEl);
          }
        }
      } else if (child instanceof HTMLOptionElement) {
        appendOption(child, listEl);
      }
    }

    const realCount = itemButtons.filter((b) => b.dataset.optValue !== '').length;
    const hasSearch = realCount >= SEARCH_THRESHOLD;
    searchWrap.hidden = !hasSearch;
    titleEl.hidden = !titleText || hasSearch;
    if (hasSearch && titleText) {
      searchInput.placeholder = `Tìm ${titleText.replace(/:$/, '').toLowerCase()}...`;
    }

    syncAttributes();
    syncSelectedDisplay();
  }

  function filterItems(query) {
    const folded = asciiFold(query);
    let visibleCount = 0;
    for (const item of itemButtons) {
      const match = !folded || (item.dataset.searchKey || '').includes(folded);
      item.hidden = !match;
      if (match) {
        visibleCount += 1;
      }
    }
    emptyEl.hidden = visibleCount > 0;
    const firstVisible = itemButtons.findIndex((b) => !b.hidden && !b.disabled);
    setActiveItem(firstVisible);
  }

  function setActiveItem(nextIdx) {
    activeIdx = nextIdx;
    for (let i = 0; i < itemButtons.length; i += 1) {
      const active = i === activeIdx;
      itemButtons[i].classList.toggle('is-active', active);
      if (active) {
        btn.setAttribute('aria-activedescendant', itemButtons[i].id);
        itemButtons[i].scrollIntoView?.({ block: 'nearest' });
      }
    }
    if (activeIdx < 0) {
      btn.removeAttribute('aria-activedescendant');
    }
  }

  function positionPopover() {
    if (!wrap.classList.contains('is-open')) {
      return;
    }
    const rect = btn.getBoundingClientRect();
    const vw = window.innerWidth || 1024;
    const vh = window.innerHeight || 768;
    const gap = 6;
    const margin = 8;

    if (usePopoverApi) {
      if (rect.bottom < 0 || rect.top > vh) {
        close({ restoreFocus: false });
        return;
      }
      const minW = Math.max(Math.round(rect.width), 180);
      popover.style.minWidth = `${minW}px`;
      popover.style.maxWidth = `${Math.max(minW, Math.min(360, vw - margin * 2))}px`;

      const pRect = popover.getBoundingClientRect();
      const spaceBelow = vh - rect.bottom - margin;
      const spaceAbove = rect.top - margin;
      const flipUp = spaceBelow < pRect.height && spaceAbove > spaceBelow;
      popover.classList.toggle('is-flip-up', flipUp);

      const top = flipUp
        ? Math.max(margin, rect.top - pRect.height - gap)
        : Math.min(vh - margin - Math.min(pRect.height, vh - margin * 2), rect.bottom + gap);
      let left = rect.left;
      if (left + pRect.width > vw - margin) {
        left = Math.max(margin, rect.right - pRect.width);
      }
      left = Math.max(margin, left);

      popover.style.top = `${Math.round(top)}px`;
      popover.style.left = `${Math.round(left)}px`;
    } else {
      const pHeight = 260;
      const flipUp = vh - rect.bottom < pHeight && rect.top > vh - rect.bottom;
      popover.classList.toggle('is-flip-up', flipUp);
      popover.classList.toggle('is-align-end', rect.left > vw * 0.55);
    }
  }

  function open() {
    if (select.disabled || wrap.classList.contains('is-open')) {
      return;
    }
    if (activeInstance && activeInstance !== instance) {
      activeInstance.close({ restoreFocus: false });
    }
    rebuildOptions();

    wrap.classList.add('is-open');
    btn.setAttribute('aria-expanded', 'true');
    popover.hidden = false;
    if (usePopoverApi) {
      try {
        popover.showPopover();
      } catch {
        // Fallback nếu môi trường không cho phép showPopover
      }
    }

    searchInput.value = '';
    filterItems('');

    const curIdx = itemButtons.findIndex(
      (b) => Number(b.dataset.optIndex) === select.selectedIndex && !b.disabled,
    );
    const startIdx = curIdx >= 0 ? curIdx : itemButtons.findIndex((b) => !b.hidden && !b.disabled);
    setActiveItem(startIdx);
    positionPopover();

    openAbort = new AbortController();
    const { signal } = openAbort;
    let rafId = 0;
    const onViewportChange = (event) => {
      if (event?.target instanceof Node && popover.contains(event.target)) {
        return;
      }
      if (rafId) {
        return;
      }
      rafId = window.requestAnimationFrame(() => {
        rafId = 0;
        positionPopover();
      });
    };
    window.addEventListener('scroll', onViewportChange, { capture: true, passive: true, signal });
    window.addEventListener('resize', onViewportChange, { passive: true, signal });

    activeInstance = instance;

    if (!searchWrap.hidden) {
      searchInput.focus({ preventScroll: true });
    }
  }

  function close({ restoreFocus = false } = {}) {
    if (!wrap.classList.contains('is-open')) {
      return;
    }
    openAbort?.abort();
    openAbort = null;
    wrap.classList.remove('is-open');
    btn.setAttribute('aria-expanded', 'false');
    btn.removeAttribute('aria-activedescendant');
    if (usePopoverApi) {
      try {
        popover.hidePopover();
      } catch {
        // Bỏ qua nếu đã đóng
      }
    }
    popover.hidden = true;
    if (activeInstance === instance) {
      activeInstance = null;
    }
    if (restoreFocus) {
      btn.focus({ preventScroll: true });
    }
  }

  function commitIndex(optIdx) {
    if (optIdx < 0 || optIdx >= select.options.length) {
      return;
    }
    const opt = select.options[optIdx];
    if (!opt || opt.disabled) {
      return;
    }
    const changed = select.selectedIndex !== optIdx;
    internalMutation = true;
    if (INDEX_DESC?.set) {
      INDEX_DESC.set.call(select, optIdx);
    } else {
      select.selectedIndex = optIdx;
    }
    internalMutation = false;

    syncSelectedDisplay();
    close({ restoreFocus: true });

    if (changed) {
      select.dispatchEvent(new Event('input', { bubbles: true }));
      select.dispatchEvent(new Event('change', { bubbles: true }));
    }
    select.dispatchEvent(new FocusEvent('blur', { bubbles: false }));
  }

  function moveActive(delta) {
    const enabled = itemButtons
      .map((b, idx) => ({ b, idx }))
      .filter(({ b }) => !b.hidden && !b.disabled);
    if (enabled.length === 0) {
      return;
    }
    const curPos = enabled.findIndex((entry) => entry.idx === activeIdx);
    let nextPos = 0;
    if (curPos < 0) {
      nextPos = delta > 0 ? 0 : enabled.length - 1;
    } else {
      nextPos = (curPos + delta + enabled.length) % enabled.length;
    }
    setActiveItem(enabled[nextPos].idx);
  }

  function onKeyDown(event) {
    const isOpen = wrap.classList.contains('is-open');
    if (!isOpen) {
      if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
        event.preventDefault();
        open();
      }
      return;
    }

    if (event.key === 'Escape') {
      event.preventDefault();
      event.stopPropagation();
      close({ restoreFocus: true });
      return;
    }
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      moveActive(1);
      return;
    }
    if (event.key === 'ArrowUp') {
      event.preventDefault();
      moveActive(-1);
      return;
    }
    if (event.key === 'Home' && event.target !== searchInput) {
      event.preventDefault();
      const first = itemButtons.findIndex((b) => !b.hidden && !b.disabled);
      if (first >= 0) setActiveItem(first);
      return;
    }
    if (event.key === 'End' && event.target !== searchInput) {
      event.preventDefault();
      for (let i = itemButtons.length - 1; i >= 0; i -= 1) {
        if (!itemButtons[i].hidden && !itemButtons[i].disabled) {
          setActiveItem(i);
          break;
        }
      }
      return;
    }
    if (event.key === 'Enter' || (event.key === ' ' && event.target !== searchInput)) {
      event.preventDefault();
      if (activeIdx >= 0 && itemButtons[activeIdx]) {
        commitIndex(Number(itemButtons[activeIdx].dataset.optIndex));
      }
      return;
    }
    if (event.key === 'Tab') {
      close({ restoreFocus: false });
    }
  }

  btn.addEventListener('click', () => {
    if (wrap.classList.contains('is-open')) {
      close({ restoreFocus: true });
    } else {
      open();
    }
  });

  btn.addEventListener('keydown', onKeyDown);
  popover.addEventListener('keydown', onKeyDown);

  searchInput.addEventListener('input', () => {
    filterItems(searchInput.value);
    positionPopover();
  });

  listEl.addEventListener('click', (event) => {
    const item = event.target instanceof Element ? event.target.closest('.drv-dropdown-popover__item') : null;
    if (!item || item.disabled) {
      return;
    }
    commitIndex(Number(item.dataset.optIndex));
  });

  listEl.addEventListener('pointermove', (event) => {
    const item = event.target instanceof Element ? event.target.closest('.drv-dropdown-popover__item') : null;
    if (!item || item.disabled) {
      return;
    }
    const idx = itemButtons.indexOf(item);
    if (idx >= 0 && idx !== activeIdx) {
      activeIdx = idx;
      for (let i = 0; i < itemButtons.length; i += 1) {
        itemButtons[i].classList.toggle('is-active', i === idx);
      }
    }
  });

  // Chuyển tiếp focus từ `<select>` ẩn (khi click `<label for>` hoặc validateForm focus lỗi) sang nút combobox
  select.addEventListener('focus', () => {
    if (!select.disabled) {
      btn.focus({ preventScroll: true });
    }
  });

  select.addEventListener('change', () => {
    syncSelectedDisplay();
  });

  wrap.addEventListener('focusout', (event) => {
    const next = event.relatedTarget;
    if (next instanceof Node && (wrap.contains(next) || popover.contains(next))) {
      return;
    }
    if (wrap.classList.contains('is-open')) {
      close({ restoreFocus: false });
    }
    select.dispatchEvent(new FocusEvent('blur', { bubbles: false, relatedTarget: next instanceof EventTarget ? next : null }));
  });

  const selectObserver = new MutationObserver((records) => {
    let optionsChanged = false;
    let attrsChanged = false;
    for (const rec of records) {
      if (rec.type === 'childList') {
        optionsChanged = true;
      } else if (rec.type === 'attributes') {
        attrsChanged = true;
      }
    }
    if (optionsChanged) {
      rebuildOptions();
      if (wrap.classList.contains('is-open')) {
        positionPopover();
      }
    } else if (attrsChanged) {
      syncAttributes();
      syncSelectedDisplay();
    }
  });

  selectObserver.observe(select, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['disabled', 'aria-invalid', 'aria-busy', 'aria-describedby', 'class'],
  });

  rebuildOptions();

  const instance = {
    select,
    wrap,
    btn,
    popover,
    open,
    close,
    sync: rebuildOptions,
    onExternalValue() {
      if (!internalMutation) {
        syncSelectedDisplay();
      }
    },
    onExternalAttr() {
      syncAttributes();
    },
    destroy() {
      close({ restoreFocus: false });
      selectObserver.disconnect();
      instances.delete(select);
    },
  };

  instances.set(select, instance);
  return instance;
}

export function closeOpenCustomSelect(options = { restoreFocus: false }) {
  if (activeInstance) {
    activeInstance.close(options);
    return true;
  }
  return false;
}

export function upgradeSelect(select) {
  if (!isEligibleSelect(select)) {
    return null;
  }
  if (instances.has(select)) {
    return instances.get(select);
  }
  return createInstance(select);
}

export function scanCustomSelects(root = document) {
  if (!root || typeof root.querySelectorAll !== 'function') {
    return;
  }
  if (root instanceof HTMLSelectElement && root.matches(CUSTOM_SELECT_SELECTOR)) {
    upgradeSelect(root);
  }
  for (const sel of root.querySelectorAll(CUSTOM_SELECT_SELECTOR)) {
    upgradeSelect(sel);
  }
  // Dọn dẹp các instance đã bị gỡ khỏi DOM (ví dụ khi BOQ render lại danh sách phòng)
  for (const [sel, inst] of instances) {
    if (!sel.isConnected) {
      inst.destroy();
    }
  }
}

function bindGlobalListeners() {
  if (globalBound || typeof document === 'undefined') {
    return;
  }
  globalBound = true;

  document.addEventListener(
    'pointerdown',
    (event) => {
      if (!activeInstance) {
        return;
      }
      const target = event.target;
      if (
        target instanceof Node &&
        (activeInstance.wrap.contains(target) || activeInstance.popover.contains(target))
      ) {
        return;
      }
      activeInstance.close({ restoreFocus: false });
    },
    true,
  );

  document.addEventListener(
    'keydown',
    (event) => {
      if (event.key === 'Escape' && activeInstance) {
        event.preventDefault();
        event.stopPropagation();
        activeInstance.close({ restoreFocus: true });
      }
    },
    true,
  );

  document.addEventListener('reset', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
      return;
    }
    queueMicrotask(() => {
      for (const [sel, inst] of instances) {
        if (sel.form === form) {
          inst.sync();
        }
      }
    });
  });

  if (typeof MutationObserver !== 'undefined' && document.body) {
    let queued = false;
    domObserver = new MutationObserver(() => {
      if (queued) {
        return;
      }
      queued = true;
      queueMicrotask(() => {
        queued = false;
        scanCustomSelects(document);
      });
    });
    domObserver.observe(document.body, { childList: true, subtree: true });
  }
}

export function initCustomSelects(root = document) {
  ensurePrototypeHook();
  bindGlobalListeners();
  scanCustomSelects(root);
}

export function resetCustomSelectsForTests() {
  for (const inst of [...instances.values()]) {
    inst.destroy();
  }
  instances.clear();
  activeInstance = null;
  domObserver?.disconnect();
  domObserver = null;
  globalBound = false;
}
