const ICON_BY_VARIANT = {
  default: 'ph-check-circle',
  success: 'ph-check-circle',
  warning: 'ph-warning-circle',
  error: 'ph-warning-circle',
  info: 'ph-info',
};

function ensureToastRegion() {
  let region = document.getElementById('drv-toast-region');
  if (!region) {
    region = document.createElement('div');
    region.id = 'drv-toast-region';
    region.className = 'drv-toast-region';
    region.setAttribute('aria-live', 'polite');
    region.setAttribute('aria-atomic', 'false');
    document.body.appendChild(region);
  }
  return region;
}

export function showToast(message, variant = 'default', duration = 3200) {
  if (!message || typeof document === 'undefined') {
    return null;
  }

  const region = ensureToastRegion();
  const toast = document.createElement('div');
  toast.className = `drv-toast drv-toast--${variant}`;
  toast.setAttribute('role', variant === 'error' ? 'alert' : 'status');

  const icon = document.createElement('i');
  icon.className = `ph-fill ${ICON_BY_VARIANT[variant] || ICON_BY_VARIANT.default} drv-icon--sm`;
  icon.setAttribute('aria-hidden', 'true');

  const text = document.createElement('span');
  text.textContent = String(message);

  toast.append(icon, text);
  region.appendChild(toast);

  if (duration > 0) {
    window.setTimeout(() => {
      toast.remove();
    }, duration);
  }

  return toast;
}

