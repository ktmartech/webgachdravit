import { copyText, registerAction } from '../core.js';
import { qs, qsa } from '../utils/dom.js';
import { showToast } from '../utils/toast.js';

let bound = false;

/**
 * Menu chia sẻ dùng chung (bài viết, sản phẩm): Facebook (link sharer), Zalo (sao chép link + mở Zalo Web, không nạp SDK ngoài),
 * Sao chép (action `copy-text` của core).
 */
export function initShareMenu() {
  if (bound) {
    return;
  }
  bound = true;

  const closeAll = (except = null) => {
    for (const wrap of qsa('[data-drv-share]')) {
      if (wrap === except) {
        continue;
      }
      const menu = qs('.drv-share__menu', wrap);
      const btn = qs('[data-drv-action="share-toggle"]', wrap);
      if (menu && !menu.hidden) {
        menu.hidden = true;
        btn?.setAttribute('aria-expanded', 'false');
      }
    }
  };

  registerAction('share-toggle', (trigger, event) => {
    event?.preventDefault();
    const wrap = trigger.closest('[data-drv-share]');
    const menu = wrap ? qs('.drv-share__menu', wrap) : null;
    if (!wrap || !menu) {
      return;
    }
    const willOpen = menu.hidden;
    closeAll(wrap);
    menu.hidden = !willOpen;
    trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    if (willOpen) {
      qs('.drv-share__item', menu)?.focus();
    }
  });

  registerAction('share-close', () => closeAll());

  registerAction('share-zalo', async (trigger, event) => {
    event?.preventDefault();
    const url = trigger.dataset.shareUrl || window.location.href;
    const ok = await copyText(url);
    showToast(
      ok ? 'Đã sao chép liên kết, dán vào Zalo để gửi' : 'Hãy sao chép liên kết rồi dán vào Zalo',
      ok ? 'success' : 'default',
      3200,
    );
    window.open('https://chat.zalo.me/', '_blank', 'noopener,noreferrer');
    closeAll();
  });

  document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element) || !event.target.closest('[data-drv-share]')) {
      closeAll();
    } else if (event.target.closest('[data-drv-action="copy-text"]')) {
      window.setTimeout(() => closeAll(), 250);
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
      return;
    }
    const open = qs('[data-drv-share] .drv-share__menu:not([hidden])');
    if (open) {
      const wrap = open.closest('[data-drv-share]');
      closeAll();
      qs('[data-drv-action="share-toggle"]', wrap)?.focus();
    }
  });
}
