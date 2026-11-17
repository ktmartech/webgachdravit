/**
 * `/tai-khoan` khi chưa đăng nhập (woocommerce/myaccount/form-login.php):
 * - Tab Đăng nhập / Tạo tài khoản (ARIA tabs, phím mũi tên, giữ `#dang-ky` trên URL). Không JS: 2 form xếp dọc.
 * - Nút Hiện/Ẩn mật khẩu.
 */
import { qs, qsa } from './utils/dom.js';

const REGISTER_HASH = '#dang-ky';

export function initAuthTabs(root = document) {
  const card = qs('[data-drv-auth]', root);
  const tablist = card ? qs('[data-drv-auth-tabs]', card) : null;
  if (!card || !tablist) {
    return null;
  }
  const tabs = qsa('[role="tab"]', tablist);
  const panels = qsa('[data-drv-auth-panel]', card);

  const activate = (name, { focusTab = false, updateHash = false } = {}) => {
    if (!panels.some((p) => p.dataset.drvAuthPanel === name)) {
      return;
    }
    card.dataset.active = name;
    for (const tab of tabs) {
      const on = tab.dataset.drvAuthTab === name;
      tab.setAttribute('aria-selected', on ? 'true' : 'false');
      tab.tabIndex = on ? 0 : -1;
      if (on && focusTab) {
        tab.focus();
      }
    }
    for (const panel of panels) {
      panel.hidden = panel.dataset.drvAuthPanel !== name;
    }
    if (updateHash && typeof history !== 'undefined' && history.replaceState) {
      const url = name === 'register' ? REGISTER_HASH : `${location.pathname}${location.search}`;
      history.replaceState(null, '', url);
    }
  };

  tablist.hidden = false;
  card.classList.add('is-enhanced');
  activate(location.hash === REGISTER_HASH ? 'register' : card.dataset.active || 'login');

  card.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-drv-auth-tab]');
    if (!trigger || !card.contains(trigger)) {
      return;
    }
    event.preventDefault();
    const name = trigger.dataset.drvAuthTab;
    const isTab = trigger.getAttribute('role') === 'tab';
    activate(name, { updateHash: true });
    if (!isTab) {
      qs(`[data-drv-auth-panel="${name}"] input:not([type="hidden"]):not([tabindex="-1"])`, card)?.focus();
    }
  });

  tablist.addEventListener('keydown', (event) => {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
      return;
    }
    event.preventDefault();
    const idx = tabs.findIndex((t) => t.getAttribute('aria-selected') === 'true');
    let next = idx;
    if (event.key === 'ArrowRight') {
      next = (idx + 1) % tabs.length;
    } else if (event.key === 'ArrowLeft') {
      next = (idx - 1 + tabs.length) % tabs.length;
    } else if (event.key === 'Home') {
      next = 0;
    } else {
      next = tabs.length - 1;
    }
    activate(tabs[next].dataset.drvAuthTab, { focusTab: true, updateHash: true });
  });

  return { activate };
}

export function initPasswordToggles(root = document) {
  for (const btn of qsa('[data-drv-password-toggle]', root)) {
    const input = document.getElementById(btn.getAttribute('aria-controls') || '');
    if (!input) {
      continue;
    }
    btn.hidden = false;
    btn.setAttribute('aria-label', 'Hiện mật khẩu');
    btn.addEventListener('click', () => {
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
      btn.setAttribute('aria-label', show ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
      const label = qs('span', btn);
      if (label) {
        label.textContent = show ? 'Ẩn' : 'Hiện';
      }
    });
  }
}

if (typeof document !== 'undefined') {
  initAuthTabs();
  initPasswordToggles();
}
