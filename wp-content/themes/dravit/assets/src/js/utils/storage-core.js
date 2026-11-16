/**
 * Đọc / ghi `localStorage` an toàn (Safari ẩn danh, đầy bộ nhớ, bị chặn) theo docs/15 §6.4.
 */
export function readJsonStorage(key, fallback = null) {
  try {
    const raw = window.localStorage.getItem(key);
    return raw ? JSON.parse(raw) : fallback;
  } catch {
    return fallback;
  }
}

export function writeJsonStorage(key, value) {
  try {
    window.localStorage.setItem(key, JSON.stringify(value));
    return true;
  } catch {
    return false;
  }
}
