import { closeDialog, openDialog } from './core.js';
import { prefersReducedMotion, qs, qsa } from './utils/dom.js';

const SCROLL_STORAGE_KEY = 'drv_catalog_scroll';
const SCROLL_TTL_MS = 30 * 60 * 1000;
const DEFAULT_DEBOUNCE_MS = 120;

let pageEventsAbort = null;

function getCatalogEndpoint() {
  return window.DRV_CONFIG?.catalogEndpoint || '/wp-json/dravit/v1/catalog';
}

function escapeSelectorId(id) {
  if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
    return CSS.escape(id);
  }
  return String(id).replace(/([^a-zA-Z0-9_-])/g, '\\$1');
}

function extractPageFromPathname(pathname) {
  const match = String(pathname || '').match(/\/page\/([1-9]\d*)\/?$/);
  return match ? Number.parseInt(match[1], 10) : 1;
}

function stripPageFromPathname(pathname) {
  const cleaned = String(pathname || '/')
    .replace(/\/page\/\d+\/?$/, '')
    .replace(/\/+$/, '');
  return cleaned || '/';
}

function normalizeCatalogKey(rawUrl) {
  try {
    const u = new URL(rawUrl, window.location.origin);
    const basePath = stripPageFromPathname(u.pathname);
    return `${basePath}${u.search}`;
  } catch {
    return String(rawUrl || '');
  }
}

function renderLoadMoreHtml({ shown, total, nextUrl, perPage }) {
  const totalSafe = Math.max(0, Number(total) || 0);
  if (totalSafe <= 0) {
    return '';
  }
  const shownSafe = Math.min(Math.max(0, Number(shown) || 0), totalSafe);
  const pct = Math.round((shownSafe / totalSafe) * 100);
  const remaining = Math.max(0, totalSafe - shownSafe);
  const batch = Math.min(Math.max(1, Number(perPage) || 24), remaining);

  const btnHtml =
    remaining > 0 && nextUrl
      ? `<a href="${escapeAttr(nextUrl)}" rel="next" class="drv-btn drv-btn--outline" data-drv-load-more>
          <span>Xem thêm ${batch} mẫu gạch</span>
          <span class="drv-spin-ring drv-spin-ring--sm drv-loadmore-spinner" aria-hidden="true"></span>
          <i class="ph ph-arrow-down drv-icon drv-icon--xs" aria-hidden="true"></i>
        </a>`
      : '';

  return `<div class="drv-load-more-wrap" data-drv-load-more-wrap>
    <p class="u-text-xs u-text-muted u-tabular">
      Đang hiển thị <strong>${shownSafe}</strong> / <strong>${totalSafe}</strong> mẫu gạch
    </p>
    <div class="drv-load-more__progress" role="progressbar" aria-valuenow="${pct}" aria-valuemin="0" aria-valuemax="100" style="--_pct: ${pct}%">
      <span class="drv-load-more__bar"></span>
    </div>
    ${btnHtml}
  </div>`;
}

function escapeAttr(val) {
  return String(val ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('"', '&quot;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;');
}

function setCatalogBusyState(fragment, busy) {
  if (fragment === 'grid') {
    qs('#drv-product-grid')?.setAttribute('aria-busy', busy ? 'true' : 'false');
    qs('[data-drv-catalog-toolbar]')?.classList.toggle('is-loading', busy);
  }
  qs('#drv-filter-sheet')?.classList.toggle('is-loading', busy);
}

function updateHeadMeta({ title, canonical, robots }) {
  if (typeof title === 'string' && title.trim() !== '') {
    document.title = title.trim();
  }

  if (typeof canonical === 'string' && canonical.trim() !== '') {
    let link = qs('link[rel="canonical"]', document.head);
    if (!link) {
      link = document.createElement('link');
      link.setAttribute('rel', 'canonical');
      document.head.appendChild(link);
    }
    link.setAttribute('href', canonical.trim());
  }

  if (typeof robots === 'string' && robots.trim() !== '') {
    let meta = qs('meta[name="robots"]', document.head);
    if (!meta) {
      meta = document.createElement('meta');
      meta.setAttribute('name', 'robots');
      document.head.appendChild(meta);
    }
    meta.setAttribute('content', robots.trim());
  }
}

function announceLiveCount(total) {
  const live = qs('#drv-catalog-live-status');
  if (live) {
    live.textContent = `Đã tìm thấy ${Math.max(0, Number(total) || 0)} sản phẩm.`;
  }
}

function syncFacetPillVisuals(root) {
  for (const pill of qsa('.drv-facet-pill', root)) {
    const input = qs('input[type="checkbox"]', pill);
    if (input) {
      pill.classList.toggle('is-active', input.checked);
      pill.classList.toggle('is-disabled', input.disabled);
    }
  }
}

export class DrvFilterForm extends HTMLElement {
  constructor() {
    super();
    this._mounted = false;
    this._reparenting = false;
    this._abort = null;
    this._reqAbort = null;
    this._debounceTimer = null;
    this._sheetSnapshot = null;
    this._sheetApplied = false;
    this._currentPage = 1;
  }

  connectedCallback() {
    document.documentElement.classList.add('js-catalog');
    if (this._reparenting || this._mounted) {
      return;
    }

    this._mounted = true;
    this._abort = new AbortController();
    this._currentPage = extractPageFromPathname(window.location.pathname);

    const { signal } = this._abort;

    this.addEventListener('submit', (e) => this._handleFormSubmit(e), { signal });
    this.addEventListener('change', (e) => this._handleFormChange(e), { signal });
    this.addEventListener('click', (e) => this._handleFormClick(e), { signal });

    if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
      const mq = window.matchMedia('(min-width: 1024px)');
      const onMqChange = (ev) => {
        if (ev.matches && this.isSheetOpen()) {
          this.closeSheet(false);
        } else if (ev.matches) {
          this._moveToDesktopSlot();
        }
      };
      if (typeof mq.addEventListener === 'function') {
        mq.addEventListener('change', onMqChange, { signal });
      }
    }

    this._bindSheetEvents(signal);
    this._ensureInitialHistoryState();
    this._restoreScrollSession();
  }

  disconnectedCallback() {
    if (this._reparenting) {
      return;
    }
    this._mounted = false;
    this._clearPendingTimer();
    this._reqAbort?.abort();
    this._reqAbort = null;
    this._abort?.abort();
    this._abort = null;
  }

  get base() {
    return this.dataset.base || qs('[data-drv-catalog-page]')?.dataset.base || 'product_cat:gach';
  }

  get baseUrl() {
    return this.dataset.baseUrl || qs('[data-drv-catalog-page]')?.dataset.baseUrl || '/gach';
  }

  get perPage() {
    return Math.max(1, Number.parseInt(this.dataset.perPage || '24', 10) || 24);
  }

  get debounceMs() {
    if (this.dataset.debounceMs !== undefined) {
      return Math.max(0, Number.parseInt(this.dataset.debounceMs, 10) || 0);
    }
    return DEFAULT_DEBOUNCE_MS;
  }

  get formEl() {
    return qs('[data-filter-form-el]', this) || qs('form', this);
  }

  get sheetDialog() {
    return qs('#drv-filter-sheet');
  }

  isSheetOpen() {
    const dialog = this.sheetDialog;
    if (!dialog) {
      return false;
    }
    return Boolean(dialog.open || dialog.hasAttribute('open'));
  }

  openSheet(trigger = null) {
    const dialog = this.sheetDialog;
    const sheetSlot = qs('[data-filter-sheet-slot]', dialog);
    const form = this.formEl;
    if (!dialog || !sheetSlot || !form) {
      return;
    }

    this._sheetApplied = false;
    this._sheetSnapshot = {
      formHtml: form.innerHTML,
      openMap: this._captureOpenGroups(form),
      sortVal: qs('[data-catalog-sort]')?.value || 'moi-ve',
      total: Number.parseInt(qs('[data-catalog-total]')?.textContent || this.dataset.total || '0', 10) || 0,
      chipsHtml: qs('#drv-active-chips')?.innerHTML || '',
      activeCount: this._countCheckedCriteria(form),
    };

    this._moveToContainer(sheetSlot);
    this._syncSheetHeaderAndFoot(this._sheetSnapshot.total, this._sheetSnapshot.activeCount, this._sheetSnapshot.chipsHtml);
    openDialog(dialog, trigger);
  }

  closeSheet(applied = false) {
    const dialog = this.sheetDialog;
    this._clearPendingTimer();

    if (!applied && !this._sheetApplied && this._sheetSnapshot) {
      this._reqAbort?.abort();
      const form = this.formEl;
      if (form) {
        form.innerHTML = this._sheetSnapshot.formHtml;
        this._restoreOpenGroups(form, this._sheetSnapshot.openMap);
      }
      const sortEl = qs('[data-catalog-sort]');
      if (sortEl) {
        sortEl.value = this._sheetSnapshot.sortVal;
      }
    }

    this._sheetSnapshot = null;
    this._sheetApplied = false;
    this._moveToDesktopSlot();

    if (dialog && (dialog.open || dialog.hasAttribute('open'))) {
      closeDialog(dialog);
    }
  }

  _bindSheetEvents(signal) {
    const dialog = this.sheetDialog;
    if (!dialog) {
      return;
    }

    dialog.addEventListener(
      'click',
      (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) {
          return;
        }

        if (target === dialog || target.closest('[data-filter-sheet-close]')) {
          event.preventDefault();
          this.closeSheet(false);
          return;
        }

        const chipBtn = target.closest('[data-filter-remove-param]');
        if (chipBtn) {
          event.preventDefault();
          this._uncheckCriterion(chipBtn.dataset.filterRemoveParam, chipBtn.dataset.filterRemoveVal);
          this._scheduleCatalogRequest('count', { immediate: true });
          return;
        }

        if (target.closest('[data-filter-sheet-reset]') || target.closest('[data-filter-reset]')) {
          event.preventDefault();
          this._clearAllInputs();
          this._scheduleCatalogRequest('count', { immediate: true });
          return;
        }

        if (target.closest('[data-filter-sheet-apply]')) {
          event.preventDefault();
          this._sheetApplied = true;
          this._scheduleCatalogRequest('grid', {
            immediate: true,
            pushHistory: true,
            onDone: () => this.closeSheet(true),
          });
          return;
        }

        if (target.closest('[data-drv-collapse-facets-mobile]')) {
          event.preventDefault();
          this._toggleAllAccordions(qs('[data-collapse-facets-mobile-label]', dialog));
        }
      },
      { signal },
    );

    dialog.addEventListener(
      'cancel',
      (event) => {
        event.preventDefault();
        this.closeSheet(false);
      },
      { signal },
    );

    dialog.addEventListener(
      'close',
      () => {
        if (this.closest('#drv-filter-sheet')) {
          this.closeSheet(this._sheetApplied);
        }
      },
      { signal },
    );
  }

  _moveToDesktopSlot() {
    const desktopSlot = qs('[data-filter-desktop-slot]');
    if (desktopSlot && this.parentElement !== desktopSlot) {
      this._moveToContainer(desktopSlot);
    }
  }

  _moveToContainer(container) {
    if (!container || this.parentElement === container) {
      return;
    }
    this._reparenting = true;
    try {
      container.appendChild(this);
    } finally {
      this._reparenting = false;
    }
  }

  _handleFormSubmit(event) {
    event.preventDefault();
    if (this.isSheetOpen()) {
      this._sheetApplied = true;
      this._scheduleCatalogRequest('grid', {
        immediate: true,
        pushHistory: true,
        onDone: () => this.closeSheet(true),
      });
      return;
    }
    this._scheduleCatalogRequest('grid', { immediate: true, pushHistory: true });
  }

  _handleFormChange(event) {
    const target = event.target;
    if (!(target instanceof HTMLInputElement || target instanceof HTMLSelectElement)) {
      return;
    }
    syncFacetPillVisuals(this);

    if (this.isSheetOpen()) {
      this._scheduleCatalogRequest('count', { immediate: false });
    } else {
      this._scheduleCatalogRequest('grid', { immediate: false, pushHistory: true });
    }
  }

  _handleFormClick(event) {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) {
      return;
    }

    const collapseBtn = target.closest('[data-drv-collapse-facets]');
    if (collapseBtn) {
      event.preventDefault();
      this._toggleAllAccordions(qs('[data-collapse-facets-label]', collapseBtn));
      return;
    }

    const resetBtn = target.closest('[data-filter-reset]');
    if (resetBtn) {
      event.preventDefault();
      this._clearAllInputs();
      if (this.isSheetOpen()) {
        this._scheduleCatalogRequest('count', { immediate: true });
      } else {
        this._scheduleCatalogRequest('grid', { immediate: true, pushHistory: true });
      }
      return;
    }

    const facetLink = target.closest('a[data-facet-link]');
    if (facetLink) {
      event.preventDefault();
      const href = facetLink.getAttribute('href') || '';
      const inputId = facetLink.dataset.targetInput || '';
      const checkbox = inputId ? qs(`#${escapeSelectorId(inputId)}`, this) : null;

      if (checkbox instanceof HTMLInputElement && checkbox.disabled) {
        return;
      }

      // Chuyển thẳng sang trang Silo tĩnh nếu đang ở Desktop và link trỏ tới Collection tĩnh sạch (Test F-10)
      if (!this.isSheetOpen() && href !== '' && !href.includes('?')) {
        void swapStaticCollection(href, { pushHistory: true });
        return;
      }

      if (checkbox instanceof HTMLInputElement) {
        checkbox.checked = !checkbox.checked;
        syncFacetPillVisuals(this);
        if (this.isSheetOpen()) {
          this._scheduleCatalogRequest('count', { immediate: false });
        } else {
          this._scheduleCatalogRequest('grid', { immediate: false, pushHistory: true });
        }
      }
    }
  }

  _toggleAllAccordions(labelEl) {
    const form = this.formEl;
    if (!form) {
      return;
    }
    const groups = qsa('details[data-facet-group]', form);
    const hasOpen = groups.some((d) => d.open);
    for (const d of groups) {
      d.open = !hasOpen;
    }
    const nextText = hasOpen ? 'Mở rộng' : 'Thu gọn';
    if (labelEl) {
      labelEl.textContent = nextText;
    }
    for (const lbl of qsa('[data-collapse-facets-label], [data-collapse-facets-mobile-label]')) {
      lbl.textContent = nextText;
    }
  }

  _clearAllInputs() {
    const form = this.formEl;
    if (form) {
      for (const input of qsa('input[type="checkbox"]', form)) {
        input.checked = false;
      }
      syncFacetPillVisuals(this);
    }
    const sortEl = qs('[data-catalog-sort]');
    if (sortEl) {
      sortEl.value = 'moi-ve';
    }
  }

  _uncheckCriterion(param, value) {
    const form = this.formEl;
    if (!form || !param) {
      return;
    }
    for (const input of qsa(`input[data-facet-param="${escapeSelectorId(param)}"]`, form)) {
      if (!value || input.value === value) {
        input.checked = false;
      }
    }
    syncFacetPillVisuals(this);
  }

  _countCheckedCriteria(form) {
    if (!form) {
      return 0;
    }
    return qsa('input[type="checkbox"]:checked', form).length;
  }

  _collectParams() {
    const form = this.formEl;
    const grouped = new Map();

    if (form) {
      for (const input of qsa('input[type="checkbox"]:checked', form)) {
        const param = input.dataset.facetParam || input.name.replace(/\[\]$/, '');
        if (!param) {
          continue;
        }
        if (!grouped.has(param)) {
          grouped.set(param, []);
        }
        grouped.get(param).push(input.value);
      }
    }

    const params = new URLSearchParams();
    params.set('base', this.base);
    params.set('per_page', String(this.perPage));

    for (const [param, values] of grouped.entries()) {
      if (values.length > 0) {
        params.set(param, values.join(','));
      }
    }

    const sortVal = qs('[data-catalog-sort]')?.value || 'moi-ve';
    if (sortVal && sortVal !== 'moi-ve') {
      params.set('sort', sortVal);
    }

    return params;
  }

  _clearPendingTimer() {
    if (this._debounceTimer !== null) {
      clearTimeout(this._debounceTimer);
      this._debounceTimer = null;
    }
  }

  _scheduleCatalogRequest(fragment = 'grid', options = {}) {
    this._clearPendingTimer();
    if (this._reqAbort) {
      this._reqAbort.abort();
      this._reqAbort = null;
    }

    setCatalogBusyState(fragment, true);

    const wait = options.immediate ? 0 : this.debounceMs;
    if (wait <= 0) {
      void this._executeCatalogRequest(fragment, options);
      return;
    }

    this._debounceTimer = setTimeout(() => {
      this._debounceTimer = null;
      void this._executeCatalogRequest(fragment, options);
    }, wait);
  }

  async _executeCatalogRequest(fragment = 'grid', options = {}) {
    if (this._reqAbort) {
      this._reqAbort.abort();
    }
    const controller = new AbortController();
    this._reqAbort = controller;

    const params = options.customParams instanceof URLSearchParams ? options.customParams : this._collectParams();
    params.set('fragment', fragment);
    if (!params.has('page')) {
      params.set('page', '1');
    }

    setCatalogBusyState(fragment, true);

    try {
      const endpoint = getCatalogEndpoint();
      const sep = endpoint.includes('?') ? '&' : '?';
      const res = await fetch(`${endpoint}${sep}${params.toString()}`, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        signal: controller.signal,
      });

      if (!res.ok || controller.signal.aborted) {
        return;
      }

      const data = await res.json();
      if (controller.signal.aborted) {
        return;
      }

      if (fragment === 'count') {
        this._applyCountPreview(data);
        options.onDone?.(data);
        return;
      }

      if (fragment === 'grid' && data.redirect_to) {
        options.onDone?.(data);
        await swapStaticCollection(data.redirect_to, { pushHistory: Boolean(options.pushHistory) });
        return;
      }

      this._applyGridResponse(data, options);
      options.onDone?.(data);
    } catch (err) {
      if (err?.name === 'AbortError') {
        return;
      }
    } finally {
      if (this._reqAbort === controller) {
        this._reqAbort = null;
      }
      if (!this._reqAbort && this._debounceTimer === null && !staticSwapAbort) {
        setCatalogBusyState('grid', false);
        setCatalogBusyState('count', false);
      }
    }
  }

  _captureOpenGroups(form) {
    const map = new Map();
    if (!form) {
      return map;
    }
    for (const d of qsa('details[data-facet-group]', form)) {
      if (d.dataset.facetGroup) {
        map.set(d.dataset.facetGroup, d.open);
      }
    }
    return map;
  }

  _restoreOpenGroups(form, openMap) {
    if (!form || !(openMap instanceof Map) || openMap.size === 0) {
      return;
    }
    for (const d of qsa('details[data-facet-group]', form)) {
      const key = d.dataset.facetGroup;
      if (key && openMap.has(key)) {
        d.open = Boolean(openMap.get(key));
      }
    }
  }

  _replaceFacetsHtml(facetsHtml) {
    const form = this.formEl;
    if (!form || typeof facetsHtml !== 'string' || facetsHtml === '') {
      return;
    }
    const openMap = this._captureOpenGroups(form);
    const activeId =
      document.activeElement instanceof HTMLElement && form.contains(document.activeElement)
        ? document.activeElement.id
        : '';

    form.innerHTML = facetsHtml;
    this._restoreOpenGroups(form, openMap);

    if (activeId) {
      const nextFocus = qs(`#${escapeSelectorId(activeId)}`, form);
      if (nextFocus instanceof HTMLElement && !nextFocus.hasAttribute('disabled')) {
        nextFocus.focus({ preventScroll: true });
      }
    }
  }

  _syncSheetHeaderAndFoot(total, activeCount, chipsHtml = '') {
    const dialog = this.sheetDialog;
    if (!dialog) {
      return;
    }
    const previewEl = qs('[data-sheet-preview-count]', dialog);
    if (previewEl) {
      previewEl.textContent = String(Math.max(0, Number(total) || 0));
    }

    const badgeEl = qs('[data-drawer-active-badge]', dialog);
    if (badgeEl) {
      badgeEl.textContent = activeCount > 0 ? `${activeCount} đang chọn` : '14 tiêu chí';
    }

    const resetBtn = qs('[data-filter-sheet-reset]', dialog);
    if (resetBtn instanceof HTMLButtonElement) {
      resetBtn.disabled = activeCount <= 0;
    }

    const barEl = qs('#drv-drawer-active-bar', dialog);
    const chipsWrap = qs('[data-drawer-active-chips]', dialog);
    if (barEl && chipsWrap) {
      if (activeCount > 0 && chipsHtml) {
        chipsWrap.innerHTML = chipsHtml;
        barEl.hidden = false;
      } else {
        chipsWrap.innerHTML = '';
        barEl.hidden = true;
      }
    }
  }

  _applyCountPreview(data) {
    if (typeof data.facets_html === 'string' && data.facets_html !== '') {
      this._replaceFacetsHtml(data.facets_html);
    }
    const activeCount = Array.isArray(data.active_chips)
      ? data.active_chips.length
      : this._countCheckedCriteria(this.formEl);
    this._syncSheetHeaderAndFoot(data.total ?? 0, activeCount, data.chips_html || '');
  }

  _applyGridResponse(data, options = {}) {
    const total = Math.max(0, Number(data.total) || 0);
    const shown = Math.max(0, Number(data.shown) || 0);
    const activeCount = Array.isArray(data.active_chips) ? data.active_chips.length : 0;

    this._currentPage = Math.max(1, Number(data.page) || 1);
    this.dataset.total = String(total);

    const grid = qs('#drv-product-grid');
    if (grid && typeof data.items_html === 'string') {
      grid.innerHTML = data.items_html;
      grid.classList.toggle('is-empty', total === 0);
      grid.setAttribute('aria-busy', 'false');

      if (total > 0 && !prefersReducedMotion()) {
        import('./chunks/motion.js')
          .then((m) => m.staggerReveal(grid, ':scope > .drv-card', { stagger: 35, duration: 340, distance: 12 }))
          .catch(() => {});
      }
    }

    if (typeof data.facets_html === 'string' && data.facets_html !== '') {
      this._replaceFacetsHtml(data.facets_html);
    }

    const chipsEl = qs('#drv-active-chips');
    if (chipsEl && typeof data.chips_html === 'string') {
      chipsEl.innerHTML = data.chips_html;
    }

    const toolbar = qs('[data-drv-catalog-toolbar]');
    if (toolbar) {
      toolbar.classList.toggle('has-active-filters', activeCount > 0);
    }

    for (const shownEl of qsa('[data-catalog-shown]')) {
      shownEl.textContent = String(Math.min(shown, total));
    }
    for (const totalEl of qsa('[data-catalog-total]')) {
      totalEl.textContent = String(total);
    }

    const mobileCountBadge = qs('[data-mobile-filter-count]');
    if (mobileCountBadge) {
      mobileCountBadge.textContent = String(activeCount);
      mobileCountBadge.hidden = activeCount <= 0;
    }

    this._syncSheetHeaderAndFoot(total, activeCount, data.chips_html || '');

    const paginationWrap = qs('#drv-catalog-pagination');
    if (paginationWrap) {
      paginationWrap.innerHTML = renderLoadMoreHtml({
        shown,
        total,
        nextUrl: data.next_url || null,
        perPage: data.per_page || this.perPage,
      });
    }

    updateHeadMeta({
      title: data.title,
      canonical: data.canonical,
      robots: data.robots,
    });

    announceLiveCount(total);

    if (options.pushHistory && typeof data.url === 'string' && data.url !== '') {
      try {
        const nextHref = new URL(data.url, window.location.origin).href;
        if (nextHref !== window.location.href) {
          window.history.pushState({ drvCatalog: true, base: this.base, url: nextHref }, '', data.url);
        }
      } catch {
        window.history.pushState({ drvCatalog: true, base: this.base, url: data.url }, '', data.url);
      }
    }
  }

  async loadMore(triggerLink) {
    if (this._loadingMore) {
      return;
    }
    this._loadingMore = true;

    const nextPage = this._currentPage + 1;
    const params = this._collectParams();
    params.set('page', String(nextPage));
    params.set('fragment', 'items');

    if (triggerLink instanceof HTMLElement) {
      triggerLink.setAttribute('aria-busy', 'true');
      triggerLink.classList.add('is-loading');
    }

    try {
      const endpoint = getCatalogEndpoint();
      const sep = endpoint.includes('?') ? '&' : '?';
      const res = await fetch(`${endpoint}${sep}${params.toString()}`, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });

      if (!res.ok) {
        return;
      }

      const data = await res.json();
      const grid = qs('#drv-product-grid');
      if (!grid || typeof data.items_html !== 'string') {
        return;
      }

      const existingIds = new Set(
        qsa('[data-product-id]', grid)
          .map((el) => el.getAttribute('data-product-id'))
          .filter(Boolean),
      );

      const tpl = document.createElement('template');
      tpl.innerHTML = data.items_html.trim();
      const incomingNodes = Array.from(tpl.content.children).filter((node) => {
        const pid = node.getAttribute('data-product-id');
        return !pid || !existingIds.has(pid);
      });

      const firstNewCard = incomingNodes[0] || null;
      for (const node of incomingNodes) {
        grid.appendChild(node);
      }

      this._currentPage = Math.max(nextPage, Number(data.page) || nextPage);
      const total = Math.max(0, Number(data.total) || 0);
      const shownCount = qsa('.drv-card', grid).length || Math.min(total, Number(data.shown) || 0);

      for (const shownEl of qsa('[data-catalog-shown]')) {
        shownEl.textContent = String(Math.min(shownCount, total));
      }
      for (const totalEl of qsa('[data-catalog-total]')) {
        totalEl.textContent = String(total);
      }

      const paginationWrap = qs('#drv-catalog-pagination');
      if (paginationWrap) {
        paginationWrap.innerHTML = renderLoadMoreHtml({
          shown: shownCount,
          total,
          nextUrl: shownCount < total ? data.next_url || null : null,
          perPage: data.per_page || this.perPage,
        });
      }

      if (firstNewCard instanceof HTMLElement) {
        const focusTarget = qs('a[href]', firstNewCard);
        focusTarget?.focus({ preventScroll: true });
      }

      announceLiveCount(total);
    } catch {
      // Fall back cleanly on network interruption
    } finally {
      this._loadingMore = false;
      if (triggerLink instanceof HTMLElement && triggerLink.isConnected) {
        triggerLink.removeAttribute('aria-busy');
        triggerLink.classList.remove('is-loading');
      }
    }
  }

  syncFromLocationUrl(rawUrl) {
    const u = new URL(rawUrl, window.location.origin);
    const params = new URLSearchParams();
    params.set('base', this.base);
    params.set('per_page', String(this.perPage));
    params.set('page', String(extractPageFromPathname(u.pathname)));

    for (const [k, v] of u.searchParams.entries()) {
      params.set(k, v);
    }

    const sortEl = qs('[data-catalog-sort]');
    if (sortEl) {
      sortEl.value = u.searchParams.get('sort') || 'moi-ve';
    }

    this._scheduleCatalogRequest('grid', {
      immediate: true,
      pushHistory: false,
      customParams: params,
    });
  }

  saveScrollSession() {
    const grid = qs('#drv-product-grid');
    if (!grid || typeof window.sessionStorage === 'undefined') {
      return;
    }
    const shown = Number.parseInt(qs('[data-catalog-shown]')?.textContent || '0', 10) || 0;
    const total = Number.parseInt(qs('[data-catalog-total]')?.textContent || '0', 10) || 0;
    const nextUrl = qs('[data-drv-load-more]')?.getAttribute('href') || null;

    if (this._currentPage <= 1 && (window.scrollY || 0) < 200) {
      return;
    }

    try {
      window.sessionStorage.setItem(
        SCROLL_STORAGE_KEY,
        JSON.stringify({
          key: normalizeCatalogKey(window.location.href),
          base: this.base,
          page: this._currentPage,
          shown,
          total,
          nextUrl,
          scrollY: Math.round(window.scrollY || 0),
          itemsHtml: grid.innerHTML,
          ts: Date.now(),
        }),
      );
    } catch {
      // Ignore storage quota errors
    }
  }

  _restoreScrollSession() {
    if (typeof window.sessionStorage === 'undefined') {
      return;
    }
    try {
      const raw = window.sessionStorage.getItem(SCROLL_STORAGE_KEY);
      if (!raw) {
        return;
      }
      const snap = JSON.parse(raw);
      window.sessionStorage.removeItem(SCROLL_STORAGE_KEY);

      if (
        !snap ||
        typeof snap !== 'object' ||
        Date.now() - Number(snap.ts || 0) > SCROLL_TTL_MS ||
        snap.base !== this.base ||
        snap.key !== normalizeCatalogKey(window.location.href)
      ) {
        return;
      }

      const grid = qs('#drv-product-grid');
      if (grid && snap.page > 1 && typeof snap.itemsHtml === 'string' && snap.itemsHtml !== '') {
        grid.innerHTML = snap.itemsHtml;
        this._currentPage = snap.page;

        for (const shownEl of qsa('[data-catalog-shown]')) {
          shownEl.textContent = String(snap.shown);
        }

        const paginationWrap = qs('#drv-catalog-pagination');
        if (paginationWrap) {
          paginationWrap.innerHTML = renderLoadMoreHtml({
            shown: snap.shown,
            total: snap.total,
            nextUrl: snap.nextUrl || null,
            perPage: this.perPage,
          });
        }
      }

      if (Number(snap.scrollY) > 0) {
        window.requestAnimationFrame(() => {
          window.scrollTo({ top: Number(snap.scrollY), behavior: 'auto' });
        });
      }
    } catch {
      // Ignore malformed sessionStorage payload
    }
  }

  _ensureInitialHistoryState() {
    if (typeof window === 'undefined' || !window.history?.replaceState) {
      return;
    }
    if (!window.history.state?.drvCatalog && !window.history.state?.drvCatalogSilo) {
      window.history.replaceState(
        { drvCatalog: true, base: this.base, url: window.location.href },
        '',
        window.location.href,
      );
    }
  }
}

let staticSwapAbort = null;

export async function swapStaticCollection(targetUrl, { pushHistory = true } = {}) {
  staticSwapAbort?.abort();
  const controller = new AbortController();
  staticSwapAbort = controller;

  setCatalogBusyState('grid', true);

  try {
    const res = await fetch(targetUrl, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { Accept: 'text/html' },
      signal: controller.signal,
    });

    if (!res.ok || controller.signal.aborted) {
      if (!controller.signal.aborted) {
        window.location.assign(targetUrl);
      }
      return false;
    }

    const html = await res.text();
    if (controller.signal.aborted) {
      return false;
    }

    const doc = new DOMParser().parseFromString(html, 'text/html');
    const nextMain = qs('main#main-content[data-drv-catalog-page]', doc);
    const curMain = qs('main#main-content[data-drv-catalog-page]');
    if (!nextMain || !curMain) {
      window.location.assign(targetUrl);
      return false;
    }

    const nextSheet = qs('#drv-filter-sheet', doc);
    const curSheet = qs('#drv-filter-sheet');
    if (nextSheet && curSheet) {
      curSheet.replaceWith(document.adoptNode(nextSheet));
    }

    curMain.replaceWith(document.adoptNode(nextMain));

    updateHeadMeta({
      title: doc.title,
      canonical: qs('link[rel="canonical"]', doc.head)?.getAttribute('href') || targetUrl,
      robots: qs('meta[name="robots"]', doc.head)?.getAttribute('content') || '',
    });

    // Đồng bộ các thẻ Schema JSON-LD của danh mục mới (Test F-10)
    for (const oldLd of qsa('script[type="application/ld+json"]', document.head)) {
      oldLd.remove();
    }
    for (const newLd of qsa('script[type="application/ld+json"]', doc.head)) {
      const clone = document.createElement('script');
      clone.type = 'application/ld+json';
      if (newLd.className) {
        clone.className = newLd.className;
      }
      clone.textContent = newLd.textContent;
      document.head.appendChild(clone);
    }

    const finalUrl = res.url || targetUrl;
    if (pushHistory) {
      const nextBase = nextMain.dataset.base || 'product_cat:gach';
      window.history.pushState({ drvCatalogSilo: true, base: nextBase, url: finalUrl }, '', finalUrl);
    }

    const total = Number.parseInt(qs('[data-catalog-total]', nextMain)?.textContent || '0', 10) || 0;
    announceLiveCount(total);
    return true;
  } catch (err) {
    if (err?.name === 'AbortError') {
      return false;
    }
    window.location.assign(targetUrl);
    return false;
  } finally {
    if (staticSwapAbort === controller) {
      staticSwapAbort = null;
      setCatalogBusyState('grid', false);
    }
  }
}

function bindCatalogPageEvents() {
  pageEventsAbort?.abort();
  pageEventsAbort = new AbortController();
  const { signal } = pageEventsAbort;

  document.addEventListener(
    'click',
    (event) => {
      const target = event.target instanceof Element ? event.target : null;
      if (!target) {
        return;
      }

      const filterEl = qs('drv-filter-form');

      // 1. Mở Bottom Sheet Bộ lọc Mobile
      const openSheetBtn = target.closest('[data-drv-open-filter-sheet]');
      if (openSheetBtn && filterEl instanceof DrvFilterForm) {
        event.preventDefault();
        filterEl.openSheet(openSheetBtn);
        return;
      }

      // 2. Ẩn / Hiện Sidebar Bộ lọc trên Desktop (3 ⇄ 4 cột)
      const toggleSidebarBtn = target.closest('[data-drv-toggle-sidebar]');
      if (toggleSidebarBtn) {
        event.preventDefault();
        const layout = qs('#drv-catalog-layout');
        if (!layout) {
          return;
        }
        const collapsed = layout.classList.toggle('is-filter-collapsed');
        toggleSidebarBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        const lbl = qs('[data-sidebar-toggle-label]', toggleSidebarBtn);
        if (lbl) {
          lbl.textContent = collapsed ? 'Hiện bộ lọc' : 'Ẩn bộ lọc';
        }
        return;
      }

      // 3. Gỡ từng Chip đang lọc trên Toolbar
      const removeChipBtn = target.closest('#drv-active-chips [data-filter-remove-param]');
      if (removeChipBtn && filterEl instanceof DrvFilterForm) {
        event.preventDefault();
        filterEl._uncheckCriterion(removeChipBtn.dataset.filterRemoveParam, removeChipBtn.dataset.filterRemoveVal);
        filterEl._scheduleCatalogRequest('grid', { immediate: true, pushHistory: true });
        return;
      }

      // 4. Nút Xóa tất cả bộ lọc ngoài `<drv-filter-form>` (Toolbar & Empty State)
      const resetOutside = target.closest('[data-filter-reset]');
      if (resetOutside && !resetOutside.closest('drv-filter-form') && !resetOutside.closest('#drv-filter-sheet')) {
        if (filterEl instanceof DrvFilterForm) {
          event.preventDefault();
          filterEl._clearAllInputs();
          filterEl._scheduleCatalogRequest('grid', { immediate: true, pushHistory: true });
          return;
        }
      }

      // 5. Nút Xem thêm mẫu gạch (`fragment=items`, Test F-6)
      const loadMoreLink = target.closest('[data-drv-load-more]');
      if (loadMoreLink && filterEl instanceof DrvFilterForm) {
        event.preventDefault();
        void filterEl.loadMore(loadMoreLink);
        return;
      }

      // 6. Lưu trạng thái Load More & vị trí cuộn trước khi vào PDP (Test F-7)
      const cardLink = target.closest('#drv-product-grid .drv-card a[href]');
      if (cardLink && filterEl instanceof DrvFilterForm) {
        filterEl.saveScrollSession();
        return;
      }

      // 7. Chuyển Silo mượt khi bấm thanh Sub-Silo Tiles Bar (Test F-10)
      const siloTile = target.closest('.drv-silo-strip a.drv-silo-tile[href]');
      if (
        siloTile &&
        event instanceof MouseEvent &&
        event.button === 0 &&
        !event.metaKey &&
        !event.ctrlKey &&
        !event.shiftKey &&
        !event.altKey
      ) {
        const href = siloTile.getAttribute('href');
        if (href && !href.includes('?')) {
          event.preventDefault();
          void swapStaticCollection(href, { pushHistory: true });
          return;
        }
      }

      // 8. Mở rộng / thu gọn khối SEO Footer cuối trang
      const seoExpandBtn = target.closest('[data-seo-expand-btn], [data-seo-footer-toggle]');
      if (seoExpandBtn) {
        event.preventDefault();
        const wrap = seoExpandBtn.closest('[data-drv-seo-expandable]');
        if (!wrap) {
          return;
        }
        const expanded = wrap.classList.toggle('is-expanded');
        wrap.classList.toggle('is-collapsed', !expanded);
        seoExpandBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        const lbl = qs('[data-seo-expand-label], [data-seo-footer-toggle-label]', seoExpandBtn);
        if (lbl) {
          lbl.textContent = expanded ? 'Thu gọn hướng dẫn' : 'Xem đầy đủ hướng dẫn';
        }
        return;
      }

      // 9. Điền sẵn tiêu chí đang lọc khi mở Modal Báo Giá từ Empty State
      const quoteCriteriaBtn = target.closest('[data-drv-quote-criteria]');
      if (quoteCriteriaBtn) {
        const skuInput = qs('#drv-quote-modal [data-quote-modal-sku]');
        if (skuInput instanceof HTMLInputElement && quoteCriteriaBtn.dataset.drvQuoteCriteria) {
          skuInput.value = quoteCriteriaBtn.dataset.drvQuoteCriteria;
        }
      }
    },
    { capture: true, signal },
  );

  document.addEventListener(
    'change',
    (event) => {
      const target = event.target;
      if (target instanceof HTMLSelectElement && target.matches('[data-catalog-sort]')) {
        const filterEl = qs('drv-filter-form');
        if (filterEl instanceof DrvFilterForm) {
          filterEl._scheduleCatalogRequest('grid', { immediate: true, pushHistory: true });
        }
      }
    },
    { signal },
  );

  window.addEventListener(
    'popstate',
    () => {
      const pageMain = qs('main#main-content[data-drv-catalog-page]');
      const filterEl = qs('drv-filter-form');
      if (!pageMain || !(filterEl instanceof DrvFilterForm)) {
        return;
      }

      let currentBasePath = '/';
      try {
        currentBasePath = stripPageFromPathname(new URL(filterEl.baseUrl, window.location.origin).pathname);
      } catch {
        currentBasePath = stripPageFromPathname(filterEl.baseUrl);
      }

      const targetBasePath = stripPageFromPathname(window.location.pathname);
      if (targetBasePath !== currentBasePath) {
        void swapStaticCollection(window.location.href, { pushHistory: false });
        return;
      }

      filterEl.syncFromLocationUrl(window.location.href);
    },
    { signal },
  );
}

export function registerCatalogElements() {
  if (typeof document !== 'undefined') {
    document.documentElement.classList.add('js-catalog');
    bindCatalogPageEvents();
  }
  if (typeof customElements !== 'undefined' && !customElements.get('drv-filter-form')) {
    customElements.define('drv-filter-form', DrvFilterForm);
  }
}

registerCatalogElements();
