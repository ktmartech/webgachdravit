const vndFormatter = new Intl.NumberFormat('vi-VN', {
  maximumFractionDigits: 0,
});

export function formatVnd(amount) {
  const safe = Math.round(Number(amount) || 0);
  return `${vndFormatter.format(safe)}đ`;
}

export function formatDecimalVn(value, maxDecimals = 2) {
  const num = Number(value);
  if (!Number.isFinite(num)) {
    return '0';
  }
  return new Intl.NumberFormat('vi-VN', {
    minimumFractionDigits: 0,
    maximumFractionDigits: maxDecimals,
  }).format(num);
}

export const formatDecimal = formatDecimalVn;

export function formatM2(value) {
  return `${formatDecimalVn(value, 2)} m²`;
}

export function parseNumberVn(raw) {
  if (typeof raw === 'number') {
    return Number.isFinite(raw) ? raw : 0;
  }
  if (typeof raw !== 'string') {
    return 0;
  }
  const normalized = raw.trim().replace(/\s+/g, '').replace(',', '.');
  const parsed = Number.parseFloat(normalized);
  return Number.isFinite(parsed) ? parsed : 0;
}
