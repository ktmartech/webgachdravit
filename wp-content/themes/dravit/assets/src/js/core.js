import { debounce, prefersReducedMotion, qs, qsa } from './utils/dom.js';
import { formatDecimalVn, formatVnd } from './utils/format.js';
import { countQuoteItems, upsertQuoteItem } from './utils/storage.js';
import { showToast } from './utils/toast.js';

const actionHandlers = new Map();
const dialogReturnFocus = new WeakMap();
let coreBooted = false;
let domListenersBound = false;

export function registerAction(name, handler) {
  if (typeof name === 'string' && name.trim() !== '' && typeof handler === 'function') {
    actionHandlers.set(name.trim(), handler);
  }
}

export function invokeAction(name, trigger, event) {
  const handler = actionHandlers.get(name);
  if (!handler) {
    return undefined;
  }
  return handler(trigger, event);
}

function resolveDialog(targetOrEl) {
  if (!targetOrEl) {
    return null;
  }
  if (typeof HTMLDialogElement !== 'undefined' && targetOrEl instanceof HTMLDialogElement) {
    return targetOrEl;
  }
  if (typeof targetOrEl === 'string') {
    const sel = targetOrEl.startsWith('#') ? targetOrEl : `#${targetOrEl}`;
    return qs(sel);
  }
  return null;
}

function syncDialogTriggers(dialogId, expanded) {
  if (!dialogId) {
    return;
  }
  const selector = `[data-drv-target="#${dialogId}"], [aria-controls="${dialogId}"]`;
  for (const btn of qsa(selector)) {
    if (btn.hasAttribute('aria-expanded')) {
      btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }
  }
}

function bindDialogLifecycle(dialog) {
  if (dialog.dataset.drvDialogBound === '1') {
    return;
  }
  dialog.dataset.drvDialogBound = '1';

  dialog.addEventListener('cancel', (event) => {
    event.preventDefault();
    closeDialog(dialog);
  });

  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) {
      closeDialog(dialog);
    }
  });
}

export function openDialog(target, opener = null) {
  const dialog = resolveDialog(target);
  if (!dialog) {
    return false;
  }

  bindDialogLifecycle(dialog);
  if (opener instanceof HTMLElement) {
    dialogReturnFocus.set(dialog, opener);
  } else if (document.activeElement instanceof HTMLElement) {
    dialogReturnFocus.set(dialog, document.activeElement);
  }

  dialog.classList.remove('is-closing');
  if (typeof dialog.showModal === 'function') {
    if (!dialog.open) {
      dialog.showModal();
    }
  } else {
    dialog.setAttribute('open', '');
  }

  document.documentElement.style.overflow = 'hidden';
  syncDialogTriggers(dialog.id, true);

  if (dialog.id === 'drv-minicart-drawer') {
    window.dispatchEvent(new CustomEvent('drv:minicart-open', { detail: { dialog } }));
  }

  const autoFocusTarget = qs('[autofocus], input:not([type="hidden"]), button:not([data-drv-action="dialog-close"])', dialog);
  if (autoFocusTarget instanceof HTMLElement) {
    autoFocusTarget.focus({ preventScroll: true });
  }

  return true;
}

export function closeDialog(target) {
  const dialog = resolveDialog(target);
  if (!dialog) {
    return false;
  }

  const finishClose = () => {
    if (typeof document === 'undefined') {
      return;
    }
    dialog.classList.remove('is-closing');
    if (typeof dialog.close === 'function' && dialog.open) {
      dialog.close();
    } else {
      dialog.removeAttribute('open');
    }

    if (!qs('dialog[open]')) {
      document.documentElement.style.overflow = '';
    }
    syncDialogTriggers(dialog.id, false);

    const prev = dialogReturnFocus.get(dialog);
    if (prev && prev.isConnected && typeof prev.focus === 'function') {
      prev.focus({ preventScroll: true });
    }
  };

  if (prefersReducedMotion() || typeof window === 'undefined') {
    finishClose();
    return true;
  }

  dialog.classList.add('is-closing');
  window.setTimeout(finishClose, 180);
  return true;
}

export async function copyText(value) {
  const text = String(value ?? '').trim();
  if (!text) {
    return false;
  }

  if (navigator?.clipboard?.writeText) {
    try {
      await navigator.clipboard.writeText(text);
      return true;
    } catch {
      // Fallback below
    }
  }

  const ta = document.createElement('textarea');
  ta.value = text;
  ta.setAttribute('readonly', '');
  ta.style.position = 'fixed';
  ta.style.opacity = '0';
  document.body.appendChild(ta);
  ta.select();
  let copied = false;
  try {
    copied = document.execCommand('copy');
  } catch {
    copied = false;
  }
  ta.remove();
  return copied;
}

function setMegaOpen(open) {
  const mega = qs('#drv-mega-menu');
  if (!mega) {
    return;
  }
  mega.hidden = !open;
  mega.classList.toggle('is-open', open);
  for (const btn of qsa('[data-drv-action="mega-toggle"]')) {
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    btn.classList.toggle('is-open', open);
  }
}

export function updateMegaPreview(linkEl) {
  if (!linkEl) {
    return;
  }
  const mega = linkEl.closest('#drv-mega-menu') || qs('#drv-mega-menu');
  if (!mega) {
    return;
  }

  for (const active of qsa('.drv-mega__link.is-active', mega)) {
    active.classList.remove('is-active');
  }
  linkEl.classList.add('is-active');

  const data = {
    img: linkEl.dataset.drvPreviewImg || '',
    badge: linkEl.dataset.drvPreviewBadge || '',
    title: linkEl.dataset.drvPreviewTitle || linkEl.textContent.trim(),
    desc: linkEl.dataset.drvPreviewDesc || '',
    price: linkEl.dataset.drvPreviewPrice || '',
    url: linkEl.dataset.drvPreviewUrl || linkEl.getAttribute('href') || '',
  };

  const setText = (sel, val) => {
    if (!val) {
      return;
    }
    for (const el of qsa(sel, mega)) {
      el.textContent = val;
    }
  };

  setText('#drv-mega-preview-badge, [data-mega-out="badge"]', data.badge);
  setText('#drv-mega-preview-title, [data-mega-out="title"]', data.title);
  setText('#drv-mega-preview-desc, [data-mega-out="desc"]', data.desc);
  setText('#drv-mega-preview-price, [data-mega-out="price"]', data.price);

  if (data.img) {
    for (const imgEl of qsa('#drv-mega-preview-img, [data-mega-out="img"]', mega)) {
      if (imgEl.getAttribute('src') !== data.img) {
        imgEl.setAttribute('src', data.img);
      }
      if (data.title) {
        imgEl.setAttribute('alt', data.title);
      }
    }
  }

  if (data.url) {
    for (const linkOut of qsa('#drv-mega-preview-btn, [data-mega-out="cta"], [data-mega-out="link"]', mega)) {
      linkOut.setAttribute('href', data.url);
    }
  }
}

function registerBuiltInActions() {
  const handleDialogOpen = (trigger, event) => {
    event?.preventDefault();
    const target = trigger.dataset.drvTarget || trigger.getAttribute('href');
    openDialog(target, trigger);
  };

  const handleDialogClose = (trigger, event) => {
    event?.preventDefault();
    const target = trigger.dataset.drvTarget ? resolveDialog(trigger.dataset.drvTarget) : trigger.closest('dialog');
    closeDialog(target);
  };

  const handleCopy = async (trigger, event) => {
    event?.preventDefault();
    const raw =
      trigger.dataset.drvValue ||
      trigger.dataset.drvCopyText ||
      trigger.dataset.copyValue ||
      trigger.dataset.drvSku ||
      trigger.dataset.sku ||
      trigger.textContent;
    const ok = await copyText(raw);
    if (ok) {
      trigger.classList.add('is-copied');
      window.setTimeout(() => trigger.classList.remove('is-copied'), 1800);
      showToast(`Đã sao chép: ${String(raw).trim()}`, 'success', 2400);
    }
  };

  registerAction('dialog-open', handleDialogOpen);
  registerAction('open-dialog', handleDialogOpen);
  registerAction('dialog-close', handleDialogClose);
  registerAction('close-dialog', handleDialogClose);
  registerAction('copy-sku', handleCopy);
  registerAction('copy-text', handleCopy);

  registerAction('mega-toggle', (_trigger, event) => {
    event?.preventDefault();
    const mega = qs('#drv-mega-menu');
    setMegaOpen(Boolean(mega && mega.hidden));
  });

  registerAction('search-toggle', (trigger, event) => {
    event?.preventDefault();
    const headerEl = trigger.closest('.drv-header') || qs('.drv-header');
    const searchWrap = qs('[data-drv-search]', headerEl || document) || qs('[data-drv-search]');
    if (!searchWrap) {
      return;
    }
    const isOpen = searchWrap.classList.contains('is-open');
    if (isOpen) {
      if (typeof searchWrap._drvCloseSearch === 'function') {
        searchWrap._drvCloseSearch();
      } else {
        searchWrap.classList.remove('is-open');
        headerEl?.classList.remove('has-search-open');
        trigger.setAttribute('aria-expanded', 'false');
        const icon = qs('i', trigger);
        if (icon) {
          icon.className = 'ph ph-magnifying-glass drv-icon drv-icon--sm';
        }
      }
      return;
    }

    searchWrap.classList.add('is-open');
    headerEl?.classList.add('has-search-open');
    trigger.setAttribute('aria-expanded', 'true');
    const icon = qs('i', trigger);
    if (icon) {
      icon.className = 'ph ph-x drv-icon drv-icon--sm';
    }
    import('./search.js').then((m) => {
      m.initLiveSearch();
      const input = qs('input[type="search"]', searchWrap);
      if (input) {
        input.focus();
        input.click();
      }
    });
  });

  registerAction('tab-switch', (trigger, event) => {
    event?.preventDefault();
    const tablist = trigger.closest('[role="tablist"]') || trigger.parentElement;
    if (!tablist) {
      return;
    }
    const scope = trigger.closest('[data-drv-tabs]') || tablist.parentElement || document;
    const targetPanelId = trigger.getAttribute('aria-controls') || trigger.dataset.drvTarget?.replace(/^#/, '');

    for (const tab of qsa('[role="tab"], [data-drv-action="tab-switch"]', tablist)) {
      const active = tab === trigger;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.setAttribute('tabindex', active ? '0' : '-1');
    }

    if (targetPanelId) {
      for (const panel of qsa('[role="tabpanel"]', scope)) {
        panel.hidden = panel.id !== targetPanelId;
      }
    }
  });

  registerAction('chip-insert', (trigger, event) => {
    event?.preventDefault();
    const input = qs(trigger.dataset.drvTarget || '#ai-finder-prompt, #global-search-input');
    if (!input) {
      return;
    }
    const val = trigger.dataset.drvChipValue || trigger.textContent.trim();
    input.value = val;
    if (input.closest('[data-drv-search]')) {
      import('./search.js').then((m) => {
        m.initLiveSearch();
        input.focus();
        input.dispatchEvent(new CustomEvent('drv:search-immediate'));
      });
      return;
    }
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.focus();
  });

  registerAction('scroll-carousel', (trigger, event) => {
    event?.preventDefault();
    const wrap = trigger.closest('[data-drv-carousel-wrap]') || document;
    const track = qs('[data-drv-carousel]', wrap);
    if (!track) {
      return;
    }
    const dir = trigger.dataset.drvDir === 'prev' ? -1 : 1;
    track.scrollBy({ left: dir * track.clientWidth * 0.8, behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
  });

  registerAction('quote-add', async (trigger, event) => {
    event?.preventDefault();
    const sku = trigger.dataset.drvSku || 'DRV-SKU';
    const productId = Number.parseInt(trigger.dataset.drvProductId || '0', 10) || 0;
    const title = trigger.dataset.drvTitle || sku;
    const m2 = Number.parseFloat(trigger.dataset.drvM2 || '20') || 20;

    upsertQuoteItem({ product_id: productId, sku, title, area_m2: m2 });
    showToast(`Đã thêm ${title} vào Danh sách báo giá`, 'success');

    const badge = qs('[data-drv-quote-count], [data-quote-badge-count]');
    if (badge && !prefersReducedMotion()) {
      const motion = await import('./chunks/motion.js');
      motion.bounceCartBadge(badge);
    }
  });

  registerAction('add-to-cart', async (trigger, event) => {
    event?.preventDefault();
    const sku = trigger.dataset.drvSku || 'DRV-SKU';
    showToast(`Đã ghi nhận thêm ${sku} vào giỏ hàng`, 'success');
    const badge = qs('#drv-demo-cart-badge, [data-drv-cart-count]');
    if (badge && !prefersReducedMotion()) {
      const motion = await import('./chunks/motion.js');
      motion.bounceCartBadge(badge);
    }
  });

  registerAction('motion-replay-stagger', async (trigger, event) => {
    event?.preventDefault();
    const target = qs(trigger.dataset.drvTarget || '#drv-stagger-demo-grid');
    if (!target) {
      return;
    }
    const motion = await import('./chunks/motion.js');
    motion.staggerReveal(target);
  });

  registerAction('motion-demo-odometer', async (trigger, event) => {
    event?.preventDefault();
    const motion = await import('./chunks/motion.js');
    if (trigger.dataset.drvArea) {
      const parent = trigger.parentElement;
      if (parent) {
        for (const chip of qsa('.drv-chip', parent)) {
          chip.classList.toggle('is-active', chip === trigger);
        }
      }
      const needM2 = Number(trigger.dataset.drvArea) || 36;
      const boxes = Math.max(1, Math.ceil((needM2 * 1.05) / 1.92));
      const pieces = boxes * 3;
      const actualM2 = boxes * 1.92;
      const totalVnd = pieces * 246400;

      motion.animateNumber(qs('#drv-odo-area'), actualM2, (v) => `${formatDecimalVn(v, 2)} m²`);
      motion.animateNumber(qs('#drv-odo-pieces'), pieces, (v) => `${Math.round(v)} viên`);
      motion.animateNumber(qs('#drv-odo-boxes'), boxes, (v) => `${Math.round(v)} thùng`);
      motion.animateNumber(qs('#drv-odo-total'), totalVnd, formatVnd);
      const demoBadge = qs('#drv-demo-cart-badge');
      if (demoBadge) {
        motion.bounceCartBadge(demoBadge);
      }
      return;
    }

    const target = qs(trigger.dataset.drvTarget || '#uikit-odometer-price');
    if (!target) {
      return;
    }
    const nextVal = Number(trigger.dataset.drvValue || '18960000');
    motion.animateNumber(target, nextVal, formatVnd);
  });

  registerAction('ai-finder-open', (trigger, event) => {
    event?.preventDefault();
    const finderEl = qs('drv-ai-finder');
    const prompt = (trigger.dataset.drvPrompt || '').trim();
    if (finderEl) {
      const input = qs('[data-aif-input]', finderEl);
      if (input && prompt) {
        input.value = prompt;
        input.dispatchEvent(new Event('input', { bubbles: true }));
      }
      input?.focus();
      return;
    }
    const baseUrl = window.DRV_CONFIG?.aiFinderUrl || '/tro-ly-ai-tim-gach';
    window.location.href = prompt ? `${baseUrl}?prompt=${encodeURIComponent(prompt)}` : baseUrl;
  });
}

function hydrateQuoteAndCartBadges() {
  const syncQuote = () => {
    const total = countQuoteItems();
    for (const el of qsa('[data-drv-quote-count], [data-quote-badge-count]')) {
      el.textContent = String(total);
      if (el.hasAttribute('data-drv-hide-zero')) {
        el.hidden = total <= 0;
      } else if (total > 0) {
        el.hidden = false;
      }
    }
  };

  syncQuote();
  window.addEventListener('drv:quote-updated', syncQuote);
  window.addEventListener('storage', (e) => {
    if (e.key === 'drv_quote_v2') {
      syncQuote();
    }
  });

  const hasCartCookie = /(?:^|;\s*)woocommerce_items_in_cart=([1-9]\d*)/.test(document.cookie || '');
  if (hasCartCookie) {
    const runIdle = window.requestIdleCallback || ((cb) => window.setTimeout(cb, 250));
    runIdle(async () => {
      try {
        const res = await fetch('/wp-json/wc/store/v1/cart', { credentials: 'same-origin' });
        if (!res.ok) {
          return;
        }
        const cart = await res.json();
        const count = Array.isArray(cart?.items) ? cart.items.length : 0;
        for (const el of qsa('[data-drv-cart-count]')) {
          el.textContent = String(count);
          el.hidden = count <= 0;
        }
      } catch {
        // Ignore network errors silently
      }
    });
  }
}

function setupHeaderAndMenus() {
  const header = qs('.drv-header');
  if (header) {
    let lastScrollY = window.scrollY || 0;
    let ticking = false;

    const updateScroll = () => {
      const y = window.scrollY || 0;
      if (y > 120 && y > lastScrollY) {
        header.classList.add('is-compact');
      } else if (y < 40 || lastScrollY - y >= 16) {
        header.classList.remove('is-compact');
      }
      lastScrollY = y;
      ticking = false;
    };

    window.addEventListener(
      'scroll',
      () => {
        if (!ticking) {
          ticking = true;
          window.requestAnimationFrame(updateScroll);
        }
      },
      { passive: true },
    );

    if (typeof ResizeObserver !== 'undefined') {
      const ro = new ResizeObserver((entries) => {
        for (const entry of entries) {
          const h = Math.round(entry.borderBoxSize?.[0]?.blockSize ?? entry.contentRect.height);
          if (h > 0) {
            document.documentElement.style.setProperty('--header-h-current', `${h}px`);
          }
        }
      });
      ro.observe(header);
    }
  }

  // Mega Menu hover-intent & Col 5 dynamic preview
  const mega = qs('#drv-mega-menu');
  const megaTriggers = qsa('[data-drv-action="mega-toggle"]');
  if (mega && megaTriggers.length > 0) {
    let closeTimer = null;
    const cancelClose = () => {
      if (closeTimer !== null) {
        clearTimeout(closeTimer);
        closeTimer = null;
      }
    };
    const scheduleClose = () => {
      cancelClose();
      closeTimer = window.setTimeout(() => setMegaOpen(false), 180);
    };

    for (const el of [...megaTriggers, mega]) {
      el.addEventListener('pointerenter', (e) => {
        if (e.pointerType === 'mouse') {
          cancelClose();
          setMegaOpen(true);
        }
      });
      el.addEventListener('pointerleave', (e) => {
        if (e.pointerType === 'mouse') {
          scheduleClose();
        }
      });
    }

    const debouncedPreview = debounce((link) => updateMegaPreview(link), 45);
    mega.addEventListener('pointerover', (event) => {
      const link = event.target.closest('.drv-mega__link');
      if (link) {
        debouncedPreview(link);
      }
    });
    mega.addEventListener('focusin', (event) => {
      const link = event.target.closest('.drv-mega__link');
      if (link) {
        updateMegaPreview(link);
      }
    });
  }

  // Tier 2 split keyboard dropdown toggle & flyout edge flip
  document.addEventListener('click', (event) => {
    const subToggle = event.target.closest('.drv-nav__sub-toggle');
    if (subToggle) {
      event.preventDefault();
      const item = subToggle.closest('.drv-nav__item');
      const nextOpen = !item?.classList.contains('is-open');
      for (const openItem of qsa('.drv-nav__item.is-open')) {
        openItem.classList.remove('is-open');
        qs('.drv-nav__sub-toggle', openItem)?.setAttribute('aria-expanded', 'false');
      }
      if (item && nextOpen) {
        item.classList.add('is-open');
        subToggle.setAttribute('aria-expanded', 'true');
      }
      return;
    }

    if (!event.target.closest('.drv-nav__item.is-open')) {
      for (const openItem of qsa('.drv-nav__item.is-open')) {
        openItem.classList.remove('is-open');
        qs('.drv-nav__sub-toggle', openItem)?.setAttribute('aria-expanded', 'false');
      }
    }

    if (mega && !mega.hidden && !event.target.closest('#drv-mega-menu, [data-drv-action="mega-toggle"]')) {
      setMegaOpen(false);
    }
  });

  document.addEventListener('pointerover', (event) => {
    const flyItem = event.target.closest('.drv-dropdown__item');
    if (!flyItem || !qs('.drv-dropdown__flyout', flyItem)) {
      return;
    }
    const rect = flyItem.getBoundingClientRect();
    flyItem.classList.toggle('is-flyout-left', rect.right + 250 > window.innerWidth);
  });

  // Mobile accordion single-open per group
  document.addEventListener(
    'toggle',
    (event) => {
      const details = event.target;
      if (!(details instanceof HTMLDetailsElement) || !details.open || !details.classList.contains('drv-mob-accordion')) {
        return;
      }
      const group = details.closest('.drv-mob-group') || details.parentElement;
      if (!group) {
        return;
      }
      for (const sib of qsa('details.drv-mob-accordion[open]', group)) {
        if (sib !== details) {
          sib.open = false;
        }
      }
    },
    true,
  );

  // Lazy-load Live Search chunk on first focus inside `[data-drv-search]`
  let searchLoaded = false;
  const ensureSearchLoaded = (event) => {
    if (searchLoaded) {
      return;
    }
    if (event.target instanceof Element && event.target.closest('[data-drv-search]')) {
      searchLoaded = true;
      import('./search.js').then((m) => m.initLiveSearch());
    }
  };

  document.addEventListener('focusin', ensureSearchLoaded);
  document.addEventListener('pointerover', ensureSearchLoaded);

  // Page-cache-safe current URL indicator
  const currentPath = (window.location.pathname || '/').replace(/\/+$/, '') || '/';
  for (const navLink of qsa('.drv-nav__link[href]')) {
    try {
      const linkPath = new URL(navLink.href, window.location.origin).pathname.replace(/\/+$/, '') || '/';
      if (linkPath !== '/' && linkPath === currentPath) {
        navLink.classList.add('is-current');
      }
    } catch {
      // Ignore invalid href
    }
  }
}

export const Dravit = Object.freeze({
  registerAction,
  invokeAction,
  openDialog,
  closeDialog,
  copyText,
  updateMegaPreview,
  toast: showToast,
  formatVnd,
  formatDecimal: formatDecimalVn,
  formatDecimalVn,
});

/**
 * Hiệu ứng "gương quét" (glass shine) khi hover vào ảnh nội dung — không cần markup riêng ở từng template.
 * Chèn `<span class="drv-shine">` vào phần tử cha của ảnh; CSS ở `components/motion.css`.
 */
function setupImageShine() {
  if (typeof window.matchMedia !== 'function' || !window.matchMedia('(hover: hover)').matches || prefersReducedMotion()) {
    return;
  }

  const SKIP = '.drv-logo, .drv-footer__logo-img, .drv-qr-img, .drv-author-box__avatar, .drv-checkout-topbar__brand, .drv-sr__stage, [data-no-shine]';
  const MIN_SIZE = 56;
  const eligible = (img) => !img.closest(SKIP) && img.offsetWidth >= MIN_SIZE && img.offsetHeight >= MIN_SIZE;

  /**
   * Gom ảnh theo khung (phần tử cha): ảnh chồng nhau trong cùng khung (vd. product card primary + hover swap)
   * tính là 1 khung. Trả về ảnh đại diện (ảnh đầu tiên) của từng khung.
   * @param {Element} root
   * @returns {HTMLImageElement[]}
   */
  const imageSlots = (root) => {
    const imgs = root.tagName === 'IMG' ? [root] : [...root.querySelectorAll('img')];
    const slots = new Map();
    for (const img of imgs) {
      if (img.parentElement && !slots.has(img.parentElement) && eligible(img)) {
        slots.set(img.parentElement, img);
      }
    }
    return [...slots.values()];
  };

  /** @type {WeakSet<Element>} host đang hover — chỉ quét 1 lần mỗi lượt vào. */
  const activeHosts = new WeakSet();

  const shine = (img, host) => {
    const parent = img.parentElement;
    if (!parent) {
      return;
    }
    const parentStyle = getComputedStyle(parent);
    if (parentStyle.display === 'inline') {
      return;
    }
    if (parentStyle.position === 'static') {
      parent.style.position = 'relative';
    }

    let beam = parent.querySelector(':scope > .drv-shine');
    if (!beam) {
      beam = document.createElement('span');
      beam.className = 'drv-shine';
      beam.setAttribute('aria-hidden', 'true');
      parent.appendChild(beam);
    }
    beam.style.cssText = `left:${img.offsetLeft}px;top:${img.offsetTop}px;width:${img.offsetWidth}px;height:${img.offsetHeight}px;border-radius:${getComputedStyle(img).borderRadius}`;

    // Reset về vị trí đầu rồi reflow để mỗi lượt hover đều quét lại từ trái.
    parent.classList.remove('is-shining');
    void beam.offsetWidth;
    parent.classList.add('is-shining');

    activeHosts.add(host);
    host.addEventListener(
      'mouseleave',
      () => {
        activeHosts.delete(host);
        parent.classList.remove('is-shining');
      },
      { once: true },
    );
  };

  document.addEventListener('mouseover', (event) => {
    // Tìm phần tử gần nhất chứa ĐÚNG 1 ảnh hợp lệ (card / khung ảnh). Gặp vùng chứa nhiều ảnh (lưới) thì dừng.
    let node = event.target instanceof Element ? event.target : null;
    for (let depth = 0; node && depth < 6 && !/^(MAIN|SECTION|BODY)$/.test(node.tagName); depth += 1, node = node.parentElement) {
      const imgs = imageSlots(node);
      if (!imgs.length) {
        continue;
      }
      if (imgs.length > 1) {
        return;
      }
      // Mở rộng host lên tới card ngoài cùng vẫn chỉ chứa ảnh này → di chuột trong card không quét lại.
      let host = node;
      for (let up = 0; up < 6; up += 1) {
        const next = host.parentElement;
        if (!next || /^(MAIN|SECTION|BODY)$/.test(next.tagName)) {
          break;
        }
        if (imageSlots(next).length !== 1) {
          break;
        }
        host = next;
      }
      if (!activeHosts.has(host)) {
        shine(imgs[0], host);
      }
      return;
    }
  });
}

export function initCore() {
  if (coreBooted || typeof document === 'undefined') {
    return;
  }
  coreBooted = true;

  registerBuiltInActions();

  if (!domListenersBound) {
    domListenersBound = true;

    document.addEventListener('click', (event) => {
      if (event.target instanceof Element) {
        const srchCardLink = event.target.closest('[data-drv-search-results] [data-product-id] a[href]');
        if (srchCardLink) {
          const cardEl = srchCardLink.closest('[data-product-id]');
          const pid = cardEl?.getAttribute('data-product-id');
          if (pid) {
            import('./search.js').then((m) => m.sendSearchProductHit(pid));
          }
        }
      }

      const trigger = event.target instanceof Element ? event.target.closest('[data-drv-action]') : null;
      if (!trigger) {
        return;
      }
      const actionName = trigger.getAttribute('data-drv-action') || '';
      invokeAction(actionName, trigger, event);
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        setMegaOpen(false);
        for (const openItem of qsa('.drv-nav__item.is-open')) {
          openItem.classList.remove('is-open');
          qs('.drv-nav__sub-toggle', openItem)?.setAttribute('aria-expanded', 'false');
        }
        return;
      }

      // WAI-ARIA Left/Right arrow navigation inside role="tablist"
      if (['ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(event.key)) {
        const tab = event.target instanceof Element ? event.target.closest('[role="tab"]') : null;
        const tablist = tab?.closest('[role="tablist"]');
        if (!tab || !tablist) {
          return;
        }
        const tabs = qsa('[role="tab"]', tablist);
        const idx = tabs.indexOf(tab);
        if (idx < 0 || tabs.length < 2) {
          return;
        }
        event.preventDefault();
        let nextIdx = idx;
        if (event.key === 'ArrowRight') nextIdx = (idx + 1) % tabs.length;
        if (event.key === 'ArrowLeft') nextIdx = (idx - 1 + tabs.length) % tabs.length;
        if (event.key === 'Home') nextIdx = 0;
        if (event.key === 'End') nextIdx = tabs.length - 1;
        tabs[nextIdx].focus();
        invokeAction('tab-switch', tabs[nextIdx], event);
      }
    });

    setupHeaderAndMenus();
    hydrateQuoteAndCartBadges();
    setupImageShine();
  }

  if (qs('drv-unit-calc, drv-qty-input')) {
    import('./chunks/calc-elements.js');
  }

  if (qs('drv-ai-finder')) {
    import('./chunks/ai-finder.js');
  }

  if (qs('[data-drv-stagger], [data-drv-reveal]') && !prefersReducedMotion()) {
    import('./chunks/motion.js').then((mod) => mod.observeReveals());
  }

  if (typeof window !== 'undefined') {
    window.Dravit = Dravit;
  }
}

export function resetCoreForTests() {
  actionHandlers.clear();
  coreBooted = false;
}

export const resetActionsForTests = resetCoreForTests;

if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCore, { once: true });
  } else {
    initCore();
  }
}
