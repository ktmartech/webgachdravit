/**
 * QuoteStore: nguồn dữ liệu duy nhất cho Báo giá BOQ đa phòng (`localStorage['drv_quote_v2']`, docs/17 §2).
 * Dùng chung cho Card, PDP, AI Finder, Giỏ hàng và trang `/danh-sach-bao-gia`.
 * - Không lưu giá bán vào storage (luôn hydrate từ `GET /dravit/v1/products`).
 * - Mọi chuỗi do người dùng nhập được làm sạch ký tự điều khiển và chỉ render qua `textContent`.
 */
import { readJsonStorage, writeJsonStorage } from './storage-core.js';

export const QUOTE_STORAGE_KEY = 'drv_quote_v2';
export const LEGACY_STORAGE_KEY = 'drv_quote_items_v1';
export const DEFAULT_ROOM_ID = 'room-general';
export const DEFAULT_ROOM_NAME = 'Hạng mục chung';
export const MAX_SLOTS = 30;
export const MAX_ROOMS = 12;
export const TTL_MS = 30 * 86400 * 1000;
export const SHARE_MAX_LENGTH = 1800;
export const WASTE_OPTIONS = [0, 5, 10];

export const DEFAULT_ROLES = [
  'Lát nền chính',
  'Ốp tường',
  'Ốp vách Tivi',
  'Trang trí / Điểm nhấn',
  'Ốp tường bếp',
  'Khu vực tắm đứng',
  'Lát nền chống trượt',
  'Lát ban công / sân phơi',
  'Len chân tường',
];

const INPUT_MODES = ['m2', 'thung', 'vien'];
const SNAP_KEYS = [
  'title',
  'img',
  'url',
  'size_label',
  'price_state',
  'price_m2',
  'pieces_per_box',
  'm2_per_box',
  'kg_per_box',
];

export function getRoles() {
  const fromCfg = typeof window !== 'undefined' ? window.DRV_CONFIG?.quoteRoles : null;
  return Array.isArray(fromCfg) && fromCfg.length > 0 ? fromCfg.map(String) : DEFAULT_ROLES;
}

export function cleanText(raw, max = 60) {
  return String(raw ?? '')
    // eslint-disable-next-line no-control-regex -- Chủ đích lọc ký tự điều khiển khỏi dữ liệu người dùng.
    .replace(/[\u0000-\u001F\u007F-\u009F<>]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .slice(0, max);
}

export function uid(prefix) {
  const rand = Math.random().toString(36).slice(2, 8).padEnd(6, '0');
  return `${prefix}_${rand}`;
}

function clamp(n, min, max) {
  return Math.min(max, Math.max(min, n));
}

function round2(n) {
  return Math.round((Number(n) || 0) * 100) / 100;
}

export function emptyState() {
  return {
    version: 2,
    project_name: '',
    waste_pct: 5,
    active_room_id: '',
    updated_at: Date.now(),
    rooms: [],
  };
}

function sanitizeSnap(raw) {
  if (!raw || typeof raw !== 'object') {
    return undefined;
  }
  const out = {};
  for (const key of SNAP_KEYS) {
    if (raw[key] !== undefined && raw[key] !== null && raw[key] !== '') {
      out[key] = typeof raw[key] === 'number' ? raw[key] : cleanText(raw[key], 300);
    }
  }
  return Object.keys(out).length > 0 ? out : undefined;
}

export function sanitizeSlot(raw, roles = getRoles()) {
  if (!raw || typeof raw !== 'object') {
    return null;
  }
  const productId = Math.trunc(Number(raw.product_id) || 0);
  if (!(productId > 0 && productId <= 99999999)) {
    return null;
  }
  const role = roles.includes(raw.role) ? raw.role : roles[0];
  const mode = INPUT_MODES.includes(raw.input_mode) ? raw.input_mode : 'm2';
  const slot = {
    id: typeof raw.id === 'string' && /^[\w-]{3,24}$/.test(raw.id) ? raw.id : uid('sl'),
    product_id: productId,
    sku: cleanText(raw.sku, 40),
    role,
    input_mode: mode,
    target_m2: clamp(round2(raw.target_m2), 0, 10000),
    pieces: clamp(Math.round(Number(raw.pieces) || 0), 0, 50000),
    round_mode: raw.round_mode === 'piece' ? 'piece' : 'box',
    added_at: Math.trunc(Number(raw.added_at) || Date.now() / 1000),
  };
  const snap = sanitizeSnap(raw.snap);
  if (snap) {
    slot.snap = snap;
  }
  return slot;
}

/**
 * Chuẩn hóa toàn bộ state (dùng cho dữ liệu đọc từ storage hoặc link chia sẻ).
 */
export function sanitizeState(raw, roles = getRoles()) {
  const state = emptyState();
  if (!raw || typeof raw !== 'object') {
    return state;
  }
  state.project_name = cleanText(raw.project_name, 120);
  const waste = Number(raw.waste_pct);
  state.waste_pct = WASTE_OPTIONS.includes(waste) ? waste : 5;
  state.updated_at = Number(raw.updated_at) || Date.parse(raw.updated_at) || Date.now();

  let slotCount = 0;
  const rooms = Array.isArray(raw.rooms) ? raw.rooms : [];
  for (const room of rooms.slice(0, MAX_ROOMS)) {
    if (!room || typeof room !== 'object') {
      continue;
    }
    const isGeneral = room.id === DEFAULT_ROOM_ID;
    const cleanRoom = {
      id: isGeneral || (typeof room.id === 'string' && /^rm_[a-z0-9]{3,12}$/.test(room.id)) ? room.id : uid('rm'),
      name: cleanText(room.name, 60) || (isGeneral ? DEFAULT_ROOM_NAME : 'Khu vực mới'),
      slots: [],
    };
    for (const s of Array.isArray(room.slots) ? room.slots : []) {
      if (slotCount >= MAX_SLOTS) {
        break;
      }
      const slot = sanitizeSlot(s, roles);
      if (slot) {
        cleanRoom.slots.push(slot);
        slotCount += 1;
      }
    }
    state.rooms.push(cleanRoom);
  }

  state.active_room_id = state.rooms.some((r) => r.id === raw.active_room_id) ? raw.active_room_id : '';
  return state;
}

/**
 * Chuyển dữ liệu cũ dạng mảng phẳng (`drv_quote_items_v1` hoặc bản `{version:2, items:[]}` trước Step 8) sang phòng "Hạng mục chung".
 */
export function migrateFlatItems(items, roles = getRoles()) {
  const state = emptyState();
  const room = { id: DEFAULT_ROOM_ID, name: DEFAULT_ROOM_NAME, slots: [] };
  for (const it of Array.isArray(items) ? items : []) {
    if (room.slots.length >= MAX_SLOTS) {
      break;
    }
    const slot = sanitizeSlot(
      {
        product_id: it?.product_id,
        sku: it?.sku,
        role: roles[0],
        input_mode: 'm2',
        target_m2: Number(it?.area_m2 ?? it?.m2 ?? it?.actual_m2 ?? 0),
        pieces: Number(it?.pieces ?? 0),
        snap: it,
      },
      roles,
    );
    if (slot) {
      room.slots.push(slot);
    }
  }
  if (room.slots.length > 0) {
    state.rooms.push(room);
  }
  return state;
}

export function readState() {
  const raw = readJsonStorage(QUOTE_STORAGE_KEY, null);
  if (raw && typeof raw === 'object') {
    const updated = Number(raw.updated_at) || Date.parse(raw.updated_at) || 0;
    if (updated > 0 && Date.now() - updated > TTL_MS) {
      return emptyState();
    }
    if (Array.isArray(raw.rooms)) {
      return sanitizeState(raw);
    }
    if (Array.isArray(raw.items)) {
      const migrated = migrateFlatItems(raw.items);
      writeJsonStorage(QUOTE_STORAGE_KEY, migrated);
      return migrated;
    }
  }

  const legacy = readJsonStorage(LEGACY_STORAGE_KEY, null);
  if (Array.isArray(legacy) || Array.isArray(legacy?.items)) {
    const migrated = migrateFlatItems(Array.isArray(legacy) ? legacy : legacy.items);
    writeJsonStorage(QUOTE_STORAGE_KEY, migrated);
    try {
      window.localStorage.removeItem(LEGACY_STORAGE_KEY);
    } catch {
      // Storage bị chặn: bỏ qua.
    }
    return migrated;
  }

  return emptyState();
}

export function getStats(state) {
  let totalSlots = 0;
  const skus = new Set();
  for (const room of state.rooms) {
    for (const slot of room.slots) {
      if (slot.product_id > 0) {
        totalSlots += 1;
        skus.add(slot.product_id);
      }
    }
  }
  return { totalSlots, uniqueSkus: skus.size, roomCount: state.rooms.length };
}

export function writeState(state, source = 'boq') {
  const clean = sanitizeState(state);
  clean.updated_at = Date.now();
  writeJsonStorage(QUOTE_STORAGE_KEY, clean);
  if (typeof window !== 'undefined') {
    window.dispatchEvent(
      new CustomEvent('drv:quote-updated', {
        detail: { ...getStats(clean), count: getStats(clean).totalSlots, source },
      }),
    );
  }
  return clean;
}

/**
 * Đọc -> sửa trên bản sao -> chuẩn hóa -> ghi -> phát sự kiện.
 * @param {(state: ReturnType<typeof emptyState>) => any} mutator
 */
export function updateQuote(mutator, source = 'boq') {
  const state = structuredClone(readState());
  const result = mutator(state);
  const saved = writeState(state, source);
  return { state: saved, result };
}

export function totalSlots(state) {
  return getStats(state).totalSlots;
}

export function ensureRoom(state, roomId = DEFAULT_ROOM_ID, name = DEFAULT_ROOM_NAME) {
  let room = state.rooms.find((r) => r.id === roomId);
  if (!room) {
    if (state.rooms.length >= MAX_ROOMS) {
      return null;
    }
    room = { id: roomId, name: cleanText(name, 60) || DEFAULT_ROOM_NAME, slots: [] };
    if (roomId === DEFAULT_ROOM_ID) {
      state.rooms.unshift(room);
    } else {
      state.rooms.push(room);
    }
  }
  return room;
}

export function addRoom(state, name) {
  if (state.rooms.length >= MAX_ROOMS) {
    return null;
  }
  const room = { id: uid('rm'), name: cleanText(name, 60) || 'Khu vực mới', slots: [] };
  state.rooms.push(room);
  state.active_room_id = room.id;
  return room;
}

export function renameRoom(state, roomId, name) {
  const room = state.rooms.find((r) => r.id === roomId);
  const clean = cleanText(name, 60);
  if (room && clean) {
    room.name = clean;
  }
  return room;
}

export function removeRoom(state, roomId) {
  state.rooms = state.rooms.filter((r) => r.id !== roomId);
  if (state.active_room_id === roomId) {
    state.active_room_id = '';
  }
}

export function findSlot(state, slotId) {
  for (const room of state.rooms) {
    const slot = room.slots.find((s) => s.id === slotId);
    if (slot) {
      return { room, slot };
    }
  }
  return null;
}

/**
 * Thêm 1 dòng gạch vào phòng. Nếu cùng `product_id` + `role` đã có trong phòng: cộng dồn m² (chế độ m²) hoặc số viên.
 * @returns {{ slot: object|null, merged: boolean, full: boolean }}
 */
export function addSlot(state, roomId, input, roomName = DEFAULT_ROOM_NAME) {
  const room = ensureRoom(state, roomId, roomName);
  if (!room) {
    return { slot: null, merged: false, full: true };
  }
  const candidate = sanitizeSlot({ ...input, id: undefined, added_at: Math.trunc(Date.now() / 1000) });
  if (!candidate) {
    return { slot: null, merged: false, full: false };
  }
  const existing = room.slots.find((s) => s.product_id === candidate.product_id && s.role === candidate.role);
  if (existing) {
    if (existing.input_mode === 'm2' && candidate.input_mode === 'm2') {
      existing.target_m2 = round2(existing.target_m2 + candidate.target_m2);
    } else {
      existing.pieces += candidate.pieces;
      existing.target_m2 = round2(existing.target_m2 + candidate.target_m2);
    }
    if (candidate.snap) {
      existing.snap = { ...(existing.snap || {}), ...candidate.snap };
    }
    return { slot: existing, merged: true, full: false };
  }
  if (totalSlots(state) >= MAX_SLOTS) {
    return { slot: null, merged: false, full: true };
  }
  room.slots.push(candidate);
  return { slot: candidate, merged: false, full: false };
}

export function updateSlot(state, slotId, patch) {
  const found = findSlot(state, slotId);
  if (!found) {
    return null;
  }
  Object.assign(found.slot, patch);
  const clean = sanitizeSlot(found.slot);
  if (clean) {
    Object.assign(found.slot, clean);
  }
  return found.slot;
}

export function moveSlot(state, slotId, toRoomId) {
  const found = findSlot(state, slotId);
  const target = state.rooms.find((r) => r.id === toRoomId);
  if (!found || !target || found.room === target) {
    return false;
  }
  found.room.slots = found.room.slots.filter((s) => s.id !== slotId);
  target.slots.push(found.slot);
  return true;
}

export function removeSlot(state, slotId) {
  for (const room of state.rooms) {
    room.slots = room.slots.filter((s) => s.id !== slotId);
  }
}

export function removeProduct(state, identifier) {
  const target = String(identifier ?? '').replace(/^(id|sku):/, '');
  for (const room of state.rooms) {
    room.slots = room.slots.filter((s) => String(s.product_id) !== target && s.sku !== target);
  }
  state.rooms = state.rooms.filter((r) => r.slots.length > 0 || r.id !== DEFAULT_ROOM_ID);
}

export function clearQuote(source = 'boq') {
  return writeState(emptyState(), source);
}

// ---------------------------------------------------------------------------
// Link chia sẻ `?quote=` (docs/17 §2.5): v2|w=5|p={project}|r={room}:{pid}.{roleIdx}.{mode}{value},...;r=...
// ---------------------------------------------------------------------------

function toBase64Url(str) {
  const bytes = new TextEncoder().encode(str);
  let bin = '';
  for (const b of bytes) {
    bin += String.fromCharCode(b);
  }
  return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function fromBase64Url(b64) {
  const norm = b64.replace(/-/g, '+').replace(/_/g, '/');
  const padded = norm + '==='.slice((norm.length + 3) % 4);
  const bin = atob(padded);
  const bytes = Uint8Array.from(bin, (c) => c.charCodeAt(0));
  return new TextDecoder('utf-8', { fatal: true }).decode(bytes);
}

export function encodeShare(state, roles = getRoles()) {
  const parts = ['v2', `w=${state.waste_pct}`, `p=${encodeURIComponent(state.project_name || '')}`];
  for (const room of state.rooms) {
    const slots = room.slots
      .map((s) => {
        const roleIdx = Math.max(0, roles.indexOf(s.role));
        if (s.input_mode === 'thung') {
          return `${s.product_id}.${roleIdx}.b${Math.max(1, s.pieces)}`;
        }
        if (s.input_mode === 'vien') {
          return `${s.product_id}.${roleIdx}.p${Math.max(1, s.pieces)}`;
        }
        return `${s.product_id}.${roleIdx}.m${s.target_m2}`;
      })
      .join(',');
    if (slots) {
      parts.push(`r=${encodeURIComponent(room.name)}:${slots}`);
    }
  }
  return toBase64Url(parts.join('|'));
}

/**
 * Giải mã an toàn `?quote=`; trả `null` nếu sai định dạng / quá dài.
 */
export function parseShareParam(param, roles = getRoles()) {
  if (typeof param !== 'string' || param === '' || param.length > SHARE_MAX_LENGTH || !/^[\w-]+$/.test(param)) {
    return null;
  }
  let text;
  try {
    text = fromBase64Url(param);
  } catch {
    return null;
  }
  const segments = text.split('|');
  if (segments[0] !== 'v2') {
    return null;
  }

  const raw = { waste_pct: 5, project_name: '', rooms: [] };
  let count = 0;
  for (const seg of segments.slice(1)) {
    if (seg.startsWith('w=')) {
      raw.waste_pct = Number(seg.slice(2));
    } else if (seg.startsWith('p=')) {
      raw.project_name = safeDecode(seg.slice(2));
    } else if (seg.startsWith('r=')) {
      const colon = seg.indexOf(':');
      if (colon < 0 || raw.rooms.length >= MAX_ROOMS) {
        continue;
      }
      const room = { id: uid('rm'), name: safeDecode(seg.slice(2, colon)), slots: [] };
      for (const token of seg.slice(colon + 1).split(',')) {
        if (count >= MAX_SLOTS) {
          break;
        }
        const m = /^(\d{1,8})\.(\d)\.([mbp])(\d+(?:\.\d+)?)$/.exec(token);
        if (!m) {
          continue;
        }
        const value = Number(m[4]);
        const slot = {
          product_id: Number(m[1]),
          role: roles[Number(m[2])] ?? roles[0],
          input_mode: m[3] === 'm' ? 'm2' : m[3] === 'b' ? 'thung' : 'vien',
          target_m2: m[3] === 'm' ? clamp(value, 0.1, 10000) : 0,
          // `b`: lưu tạm số thùng, quy đổi ra viên sau khi hydrate quy cách đóng gói.
          pieces: m[3] === 'm' ? 0 : clamp(Math.round(value), 1, 50000),
          share_boxes: m[3] === 'b' ? clamp(Math.round(value), 1, 50000) : 0,
        };
        room.slots.push(slot);
        count += 1;
      }
      if (room.slots.length > 0) {
        raw.rooms.push(room);
      }
    }
  }
  if (raw.rooms.length === 0) {
    return null;
  }
  const state = sanitizeState(raw, roles);
  // Giữ lại cờ số thùng chia sẻ (sanitizeSlot bỏ trường lạ) theo thứ tự dòng.
  const boxesQueue = raw.rooms.flatMap((r) => r.slots.map((s) => s.share_boxes));
  let i = 0;
  for (const room of state.rooms) {
    for (const slot of room.slots) {
      if (boxesQueue[i] > 0) {
        slot.share_boxes = boxesQueue[i];
      }
      i += 1;
    }
  }
  return state;
}

function safeDecode(str) {
  try {
    return cleanText(decodeURIComponent(str), 120);
  } catch {
    return '';
  }
}

/**
 * Gộp phiếu chia sẻ vào phiếu hiện tại (giữ phòng hiện có, thêm phòng mới tới khi chạm giới hạn).
 */
export function mergeStates(current, incoming) {
  const merged = structuredClone(current);
  for (const room of incoming.rooms) {
    if (merged.rooms.length >= MAX_ROOMS) {
      break;
    }
    const copy = { ...room, id: uid('rm'), slots: [] };
    for (const slot of room.slots) {
      if (totalSlots(merged) + copy.slots.length >= MAX_SLOTS) {
        break;
      }
      copy.slots.push({ ...slot, id: uid('sl') });
    }
    if (copy.slots.length > 0) {
      merged.rooms.push(copy);
    }
  }
  if (!merged.project_name) {
    merged.project_name = incoming.project_name;
  }
  return merged;
}

/**
 * Ô CSV an toàn: bỏ xuống dòng, vô hiệu công thức Excel và bọc ngoặc kép (docs/17 §6.2).
 */
export function sanitizeCsvCell(raw) {
  const str = String(raw ?? '')
    .replace(/\r?\n/g, ' ')
    .trim();
  const safe = /^[=+\-@\t\r]/.test(str) ? `'${str}` : str;
  return `"${safe.replace(/"/g, '""')}"`;
}
