import anime from 'animejs';
import { prefersReducedMotion, qsa } from '../utils/dom.js';

export function revealElement(el, options = {}) {
  if (!el) {
    return null;
  }
  if (prefersReducedMotion()) {
    el.style.opacity = '1';
    el.style.transform = 'none';
    el.classList.add('is-revealed');
    return null;
  }

  return anime({
    targets: el,
    opacity: [0, 1],
    translateY: [options.distance ?? 14, 0],
    duration: options.duration ?? 420,
    easing: options.easing ?? 'cubicBezier(0.16, 1, 0.3, 1)',
    complete: () => {
      el.classList.add('is-revealed');
    },
  });
}

export function staggerReveal(container, itemSelector = ':scope > *', options = {}) {
  if (!container) {
    return null;
  }
  const items = qsa(itemSelector, container);
  if (items.length === 0) {
    return null;
  }

  if (prefersReducedMotion()) {
    for (const item of items) {
      item.style.opacity = '1';
      item.style.transform = 'none';
    }
    container.classList.add('is-revealed');
    return null;
  }

  for (const item of items) {
    item.style.opacity = '0';
  }

  return anime({
    targets: items,
    opacity: [0, 1],
    translateY: [options.distance ?? 14, 0],
    delay: anime.stagger(options.stagger ?? 55),
    duration: options.duration ?? 420,
    easing: 'cubicBezier(0.16, 1, 0.3, 1)',
    complete: () => {
      container.classList.add('is-revealed');
    },
  });
}

export function animateNumber(el, targetValue, formatter = (v) => String(Math.round(v)), duration = 420) {
  if (!el) {
    return null;
  }
  const end = Number(targetValue) || 0;
  const start = Number(el.dataset.numCurrent ?? 0) || 0;
  el.dataset.numCurrent = String(end);

  if (prefersReducedMotion() || start === end) {
    el.textContent = formatter(end);
    return null;
  }

  const state = { val: start };
  return anime({
    targets: state,
    val: end,
    duration,
    easing: 'cubicBezier(0.16, 1, 0.3, 1)',
    update: () => {
      el.textContent = formatter(state.val);
    },
  });
}

export function bounceCartBadge(badgeEl) {
  if (!badgeEl || prefersReducedMotion()) {
    return null;
  }
  return anime({
    targets: badgeEl,
    scale: [1, 1.28, 1],
    duration: 460,
    easing: 'spring(1, 80, 10, 0)',
  });
}

export function pulseElement(el) {
  if (!el || prefersReducedMotion()) {
    return null;
  }
  return anime({
    targets: el,
    scale: [1, 1.03, 1],
    duration: 320,
    easing: 'easeOutQuad',
  });
}

export function observeReveals(root = document) {
  const staggers = qsa('[data-drv-stagger]', root);
  const singles = qsa('[data-drv-reveal]', root);
  if (staggers.length === 0 && singles.length === 0) {
    return;
  }

  if (prefersReducedMotion() || typeof IntersectionObserver === 'undefined') {
    for (const el of staggers) {
      staggerReveal(el);
    }
    for (const el of singles) {
      revealElement(el);
    }
    return;
  }

  const io = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        if (!entry.isIntersecting) {
          continue;
        }
        io.unobserve(entry.target);
        if (entry.target.hasAttribute('data-drv-stagger')) {
          staggerReveal(entry.target);
        } else {
          revealElement(entry.target);
        }
      }
    },
    { threshold: 0.12 },
  );

  for (const el of [...staggers, ...singles]) {
    io.observe(el);
  }
}

const motionApi = {
  revealElement,
  staggerReveal,
  animateNumber,
  bounceCartBadge,
  pulseElement,
  observeReveals,
};

if (typeof window !== 'undefined') {
  window.Dravit = window.Dravit || {};
  window.Dravit.motion = motionApi;
}

export default motionApi;

