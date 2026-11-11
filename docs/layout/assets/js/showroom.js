/**
 * DRAVIT <drv-showroom> — Light DOM Web Component (D-17)
 * Ảnh lấy trực tiếp từ HTML: mỗi nút .drv-sr__thumb là một ảnh
 *   - <img src>        : ảnh nhỏ
 *   - data-full        : ảnh lớn cho khung chính (tuỳ chọn, mặc định dùng src)
 *   - data-caption     : chú thích hiển thị trên ảnh
 * Muốn đổi / thêm ảnh chỉ cần sửa HTML, không động tới file này.
 */
(() => {
  'use strict';

  /** Forward swallowed failures to the central error sink (core.js) when it is available */
  const report = (scope, error, context) => {
    if (window.Dravit && typeof window.Dravit.reportError === 'function') window.Dravit.reportError(scope, error, context);
  };


  class DrvShowroom extends HTMLElement {
    connectedCallback() {
      if (this._ready) return;
      this._ready = true;

      this.split = 50;
      this.stage = this.querySelector('[data-sr-stage]');
      this.cool = this.querySelector('[data-sr-cool]');
      this.warm = this.querySelector('[data-sr-warm]');
      this.warmImg = this.querySelector('[data-sr-warm-img]');
      this.divider = this.querySelector('[data-sr-divider]');
      this.caption = this.querySelector('[data-sr-caption]');

      this._userInteracted = false;
      this._bind();
      this._openBadge();
      this._showGroup('slabs', false);
      this._initStageIntro();
    }

    _canAnimate() {
      return (
        typeof window.anime === 'function' &&
        !(window.Dravit && window.Dravit.motion && window.Dravit.motion.prefersReducedMotion())
      );
    }

    _initStageIntro() {
      if (!this.stage || !('IntersectionObserver' in window)) return;
      const obs = new IntersectionObserver(
        (entries, observer) => {
          entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            observer.unobserve(entry.target);
            if (this._userInteracted || !this._canAnimate()) return;
            const o = { pct: 50 };
            window.anime({
              targets: o,
              pct: [50, 36, 64, 50],
              duration: 1150,
              delay: 220,
              easing: 'easeInOutSine',
              update: () => {
                if (this._userInteracted) return;
                this.split = o.pct;
                this._apply();
              },
            });
          });
        },
        { threshold: 0.35 }
      );
      obs.observe(this.stage);
    }

    _openBadge() {
      const el = this.querySelector('[data-sr-open-text]');
      if (!el) return;
      const h = new Date().getHours();
      el.textContent = h >= 8 && h < 21 ? 'Đang mở cửa · đến 21:00' : 'Mở cửa 8:00 – 21:00';
    }

    _showGroup(key, animateThumbs = true) {
      this.querySelectorAll('[data-sr-zone]').forEach((tab) => {
        const on = tab.dataset.srZone === key;
        tab.classList.toggle('is-active', on);
        tab.setAttribute('aria-selected', String(on));
        tab.tabIndex = on ? 0 : -1;
      });
      this.querySelectorAll('[data-sr-group]').forEach((g) => {
        g.hidden = g.dataset.srGroup !== key;
      });
      const group = this.querySelector(`[data-sr-group="${key}"]`);
      const first = group && (group.querySelector('.is-active') || group.querySelector('.drv-sr__thumb'));
      if (first) this._select(first, animateThumbs);
      if (animateThumbs && group && this._canAnimate()) {
        const thumbs = Array.from(group.querySelectorAll('.drv-sr__thumb'));
        if (thumbs.length) {
          window.anime.remove(thumbs);
          window.anime({
            targets: thumbs,
            opacity: [0, 1],
            translateY: [8, 0],
            delay: window.anime.stagger(50),
            duration: 320,
            easing: 'easeOutExpo',
            complete() {
              thumbs.forEach((el) => {
                el.style.opacity = '';
                el.style.transform = '';
              });
            },
          });
        }
      }
    }

    _select(thumb, animate = true) {
      thumb.parentElement.querySelectorAll('.drv-sr__thumb').forEach((t) => t.classList.toggle('is-active', t === thumb));
      const img = thumb.querySelector('img');
      const url = thumb.dataset.full || (img && img.currentSrc) || (img && img.src) || '';
      this.cool.src = url;
      this.warmImg.src = url;
      this.cool.alt = (img && img.alt) ? `${img.alt} (ánh sáng trắng 6500K)` : this.cool.alt;
      this.caption.textContent = thumb.dataset.caption || (img && img.alt) || '';
      if (animate && window.anime) {
        window.anime({
          targets: [this.cool, this.warmImg],
          opacity: [0.7, 1],
          duration: 420,
          easing: 'easeOutQuad',
        });
      }
    }

    _toast(msg) {
      if (window.Dravit && typeof window.Dravit.toast === 'function') window.Dravit.toast(msg);
    }

    async _copy(btn) {
      try {
        if (window.Dravit && typeof window.Dravit.copyToClipboard === 'function') {
          await window.Dravit.copyToClipboard(btn.dataset.srCopy);
        } else if (navigator.clipboard) {
          await navigator.clipboard.writeText(btn.dataset.srCopy);
        }
      } catch (err) {
        // vẫn báo toast để người dùng biết thao tác đã nhận
        report('clipboard', err);
      }
      const icon = btn.querySelector('i');
      btn.classList.add('is-copied');
      if (icon) icon.className = 'ph-bold ph-check';
      setTimeout(() => {
        btn.classList.remove('is-copied');
        if (icon) icon.className = 'ph ph-copy';
      }, 1600);
      this._toast(btn.dataset.srCopyMsg || 'Đã sao chép');
    }

    _experience(kind) {
      if (kind === 'lighting') {
        this._showGroup('slabs');
        if (window.anime) {
          const o = { pct: 22 };
          window.anime({
            targets: o,
            pct: [22, 78, 50],
            duration: 1200,
            easing: 'easeInOutQuad',
            update: () => { this.split = o.pct; this._apply(); },
          });
        }
        this._toast('Kéo thanh giữa ảnh để so sánh đèn vàng 3000K và trắng 6500K');
      } else if (kind === 'bath' || kind === 'lab') {
        this._showGroup(kind);
      }
      this.stage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    _apply() {
      this.warm.style.clipPath = `inset(0 ${100 - this.split}% 0 0)`;
      this.divider.style.left = `${this.split}%`;
      this.divider.setAttribute('aria-valuenow', String(Math.round(this.split)));
    }

    _fromX(x) {
      const r = this.stage.getBoundingClientRect();
      this.split = Math.max(8, Math.min(92, ((x - r.left) / Math.max(r.width, 1)) * 100));
      this._apply();
    }

    _bind() {
      let dragging = false;
      this.divider.addEventListener('pointerdown', (e) => {
        e.preventDefault();
        this._userInteracted = true;
        dragging = true;
        this.divider.setPointerCapture?.(e.pointerId);
      });
      this.divider.addEventListener('pointermove', (e) => { if (dragging) this._fromX(e.clientX); });
      const stop = () => { dragging = false; };
      this.divider.addEventListener('pointerup', stop);
      this.divider.addEventListener('pointercancel', stop);
      this.divider.addEventListener('keydown', (e) => {
        if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
        e.preventDefault();
        this._userInteracted = true;
        this.split = Math.max(8, Math.min(92, this.split + (e.key === 'ArrowRight' ? 4 : -4)));
        this._apply();
      });
      // Bấm vào khung ảnh để đưa thanh so sánh tới vị trí đó
      this.stage.addEventListener('click', (e) => {
        if (e.target.closest('[data-sr-divider]')) return;
        this._userInteracted = true;
        this._fromX(e.clientX);
      });

      this.addEventListener('click', (e) => {
        const tab = e.target.closest('[data-sr-zone]');
        if (tab) { this._showGroup(tab.dataset.srZone); return; }
        const thumb = e.target.closest('.drv-sr__thumb');
        if (thumb) { this._select(thumb, true); return; }

        const copy = e.target.closest('[data-sr-copy]');
        if (copy) { this._copy(copy); return; }

        const exp = e.target.closest('[data-sr-exp]');
        if (exp) this._experience(exp.dataset.srExp);
      });
    }
  }

  if (!customElements.get('drv-showroom')) customElements.define('drv-showroom', DrvShowroom);
})();
