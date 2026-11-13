export function qs(selector, root = typeof document !== 'undefined' ? document : null) {
  return root ? root.querySelector(selector) : null;
}

export function qsa(selector, root = typeof document !== 'undefined' ? document : null) {
  return root ? Array.from(root.querySelectorAll(selector)) : [];
}

export function debounce(fn, wait = 180) {
  let timer = null;
  return function debounced(...args) {
    if (timer !== null) {
      clearTimeout(timer);
    }
    timer = setTimeout(() => {
      timer = null;
      fn.apply(this, args);
    }, wait);
  };
}

export function prefersReducedMotion() {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
    return false;
  }
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

