/**
 * API tương thích ngược cho các module cũ (core, AI Finder) dùng danh sách báo giá dạng phẳng.
 * Toàn bộ dữ liệu thực nằm trong QuoteStore đa phòng (`utils/quote-store.js`, `drv_quote_v2`).
 */
import {
  DEFAULT_ROOM_ID,
  DEFAULT_ROOM_NAME,
  QUOTE_STORAGE_KEY,
  addSlot,
  clearQuote,
  getRoles,
  getStats,
  readState,
  removeProduct,
  updateQuote,
} from './quote-store.js';

export { readJsonStorage, writeJsonStorage } from './storage-core.js';
export { QUOTE_STORAGE_KEY };

function slotToLegacyItem(slot) {
  const snap = slot.snap || {};
  const ppb = Math.max(1, Number(snap.pieces_per_box) || 1);
  const m2PerBox = Number(snap.m2_per_box) || 0;
  const pieces = Number(slot.pieces) || 0;
  const boxes = pieces > 0 ? Math.max(1, Math.ceil(pieces / ppb)) : m2PerBox > 0 ? Math.max(1, Math.ceil(slot.target_m2 / m2PerBox)) : 1;
  const actualM2 = m2PerBox > 0 ? Number((boxes * m2PerBox).toFixed(2)) : slot.target_m2;
  const priceM2 = Number(snap.price_m2) || 0;
  return {
    ...snap,
    product_id: slot.product_id,
    sku: slot.sku,
    title: snap.title || slot.sku,
    area_m2: slot.target_m2,
    pieces,
    boxes,
    actual_m2: actualM2,
    weight_kg: Number(snap.kg_per_box) > 0 ? Number((boxes * Number(snap.kg_per_box)).toFixed(1)) : 0,
    est_total_vnd: priceM2 > 0 && snap.price_state !== 'quote_only' ? Math.round(actualM2 * priceM2) : null,
    slot_id: slot.id,
  };
}

export function getQuoteState() {
  const state = readState();
  const items = state.rooms.flatMap((room) => room.slots.map(slotToLegacyItem));
  return { version: 2, updated_at: state.updated_at, items };
}

export function countQuoteItems() {
  return getStats(readState()).totalSlots;
}

/**
 * Thêm / cập nhật 1 mẫu vào phòng "Hạng mục chung" (giữ hành vi cũ: cùng SP thì ghi đè khối lượng).
 */
export function upsertQuoteItem(entry, source = 'card') {
  const productId = Number(entry?.product_id) || 0;
  const targetM2 = Number(entry?.area_m2 ?? entry?.actual_m2 ?? 0) || 0;
  const pieces = Number(entry?.pieces) || 0;
  const { state } = updateQuote((draft) => {
    const general = draft.rooms.find((r) => r.id === DEFAULT_ROOM_ID);
    const existing = general?.slots.find((s) => s.product_id === productId);
    if (existing) {
      existing.target_m2 = targetM2 > 0 ? targetM2 : existing.target_m2;
      existing.pieces = pieces > 0 ? pieces : existing.pieces;
      existing.snap = { ...(existing.snap || {}), ...entry };
      return;
    }
    addSlot(
      draft,
      DEFAULT_ROOM_ID,
      {
        product_id: productId,
        sku: entry?.sku,
        role: getRoles()[0],
        input_mode: 'm2',
        target_m2: targetM2,
        pieces,
        snap: entry,
      },
      DEFAULT_ROOM_NAME,
    );
  }, source);
  return state;
}

export function removeQuoteItem(identifier, source = 'panel') {
  return updateQuote((draft) => removeProduct(draft, identifier), source).state;
}

export function clearQuoteItems(source = 'panel') {
  return clearQuote(source);
}
