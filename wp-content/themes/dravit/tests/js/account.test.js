import { afterEach, describe, expect, it } from 'vitest';
import { initAuthTabs, initPasswordToggles } from '../../assets/src/js/account.js';

const markup = (active = 'login') => `
  <div class="drv-auth-card" data-drv-auth data-active="${active}">
    <div role="tablist" hidden data-drv-auth-tabs>
      <button type="button" role="tab" aria-selected="false" data-drv-auth-tab="login">Đăng nhập</button>
      <button type="button" role="tab" aria-selected="false" data-drv-auth-tab="register">Tạo tài khoản</button>
    </div>
    <section id="dang-nhap" data-drv-auth-panel="login">
      <input id="username"><input type="password" id="password">
      <button type="button" data-drv-password-toggle aria-controls="password" aria-pressed="false" hidden><span>Hiện</span></button>
      <a href="#dang-ky" data-drv-auth-tab="register">Tạo tài khoản</a>
    </section>
    <section id="dang-ky" data-drv-auth-panel="register">
      <input id="drv_reg_name">
    </section>
  </div>`;

afterEach(() => {
  document.body.innerHTML = '';
  history.replaceState(null, '', '/tai-khoan');
});

describe('Tài khoản — tab Đăng nhập / Tạo tài khoản', () => {
  it('bật tab, mặc định Đăng nhập, ẩn panel còn lại', () => {
    document.body.innerHTML = markup();
    initAuthTabs();
    const card = document.querySelector('[data-drv-auth]');
    expect(card.classList.contains('is-enhanced')).toBe(true);
    expect(document.querySelector('[data-drv-auth-tabs]').hidden).toBe(false);
    expect(document.getElementById('dang-nhap').hidden).toBe(false);
    expect(document.getElementById('dang-ky').hidden).toBe(true);
    expect(document.querySelector('[data-drv-auth-tab="login"]').getAttribute('aria-selected')).toBe('true');
  });

  it('mở sẵn tab Tạo tài khoản khi server báo lỗi đăng ký hoặc URL có #dang-ky', () => {
    document.body.innerHTML = markup('register');
    initAuthTabs();
    expect(document.getElementById('dang-ky').hidden).toBe(false);

    document.body.innerHTML = markup();
    history.replaceState(null, '', '/tai-khoan#dang-ky');
    initAuthTabs();
    expect(document.getElementById('dang-ky').hidden).toBe(false);
  });

  it('link "Tạo tài khoản" chuyển tab, focus ô đầu tiên, cập nhật hash', () => {
    document.body.innerHTML = markup();
    initAuthTabs();
    document.querySelector('a[data-drv-auth-tab="register"]').click();
    expect(document.getElementById('dang-ky').hidden).toBe(false);
    expect(document.activeElement.id).toBe('drv_reg_name');
    expect(location.hash).toBe('#dang-ky');
  });

  it('phím mũi tên chuyển tab', () => {
    document.body.innerHTML = markup();
    initAuthTabs();
    document.querySelector('[data-drv-auth-tabs]').dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));
    expect(document.querySelector('[data-drv-auth-tab="register"][role="tab"]').getAttribute('aria-selected')).toBe('true');
  });
});

describe('Tài khoản — Hiện/Ẩn mật khẩu', () => {
  it('đổi type input và nhãn nút', () => {
    document.body.innerHTML = markup();
    initPasswordToggles();
    const btn = document.querySelector('[data-drv-password-toggle]');
    const input = document.getElementById('password');
    expect(btn.hidden).toBe(false);
    btn.click();
    expect(input.type).toBe('text');
    expect(btn.getAttribute('aria-pressed')).toBe('true');
    expect(btn.textContent).toBe('Ẩn');
    btn.click();
    expect(input.type).toBe('password');
  });
});
