/**
 * Chunk động cho mọi form tư vấn `[data-drv-lead-form]` (docs/17 §4, D-30):
 * validate tức thời, lấy nonce tươi, gửi AJAX `POST /dravit/v1/lead`, hiển thị lỗi từng trường (422) và mã xác nhận.
 * Khi tắt JS, chính form đó vẫn POST về `admin-post.php?action=drv_submit_lead`.
 */
import { endpoint, errorMessage, postProtected, prefetchToken } from '../utils/lead-client.js';

const PHONE_RE = /^(0|\+84)(3|5|7|8|9)\d{8}$/;
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
const bound = new WeakSet();

const MESSAGES = {
  name: 'Vui lòng nhập họ và tên (tối thiểu 2 ký tự).',
  phone: 'Số điện thoại cần gồm 10 chữ số di động Việt Nam (ví dụ: 0909123456).',
  email: 'Email chưa đúng định dạng (ví dụ: ten@congty.vn).',
  consent: 'Vui lòng xác nhận đồng ý để DRAVIT liên hệ tư vấn.',
  province: 'Vui lòng chọn Tỉnh / Thành phố giao hàng.',
  preferred_time: 'Vui lòng chọn ngày / khung giờ muốn đến xem mẫu.',
  message: 'Vui lòng mô tả ngắn nhu cầu cần hỗ trợ (tối thiểu 5 ký tự).',
  required: 'Vui lòng điền thông tin này.',
};

export function cleanPhone(raw) {
  return String(raw || '').replace(/[\s.\-()]/g, '');
}

function fieldWrap(input) {
  return input.closest('.drv-field') || input.closest('.drv-check')?.parentElement || input.parentElement;
}

export function setFieldError(form, name, message) {
  const input = form.querySelector(`[name="${CSS.escape(name)}"]`);
  if (!input) {
    return false;
  }
  const wrap = fieldWrap(input);
  let box = wrap?.querySelector('.drv-field__error');
  if (!box && wrap) {
    box = document.createElement('span');
    box.className = 'drv-field__error';
    box.setAttribute('role', 'alert');
    wrap.appendChild(box);
  }
  input.setAttribute('aria-invalid', message ? 'true' : 'false');
  wrap?.classList.toggle('is-invalid', Boolean(message));
  if (box) {
    box.textContent = message || '';
    box.hidden = !message;
    if (!box.id) {
      box.id = `${input.id || name}-error`;
    }
    const described = new Set((input.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
    if (message) {
      described.add(box.id);
    } else {
      described.delete(box.id);
    }
    if (described.size > 0) {
      input.setAttribute('aria-describedby', [...described].join(' '));
    } else {
      input.removeAttribute('aria-describedby');
    }
  }
  return true;
}

export function clearErrors(form) {
  for (const input of form.querySelectorAll('[aria-invalid="true"]')) {
    setFieldError(form, input.getAttribute('name') || '', '');
  }
  const summary = form.querySelector('[data-lead-form-status]');
  if (summary) {
    summary.hidden = true;
    summary.textContent = '';
  }
}

/**
 * Validate phía client (server vẫn validate lại 100%).
 * @returns {Record<string, string>}
 */
export function validateLeadForm(form) {
  const errors = {};
  const val = (n) => String(form.elements.namedItem(n)?.value ?? '').trim();

  for (const el of form.querySelectorAll('[required]')) {
    if (!(el instanceof HTMLInputElement || el instanceof HTMLSelectElement || el instanceof HTMLTextAreaElement)) {
      continue;
    }
    const name = el.getAttribute('name');
    if (!name) {
      continue;
    }
    const empty = el instanceof HTMLInputElement && el.type === 'checkbox' ? !el.checked : el.value.trim() === '';
    if (empty) {
      errors[name] = MESSAGES[name] || MESSAGES.required;
    }
  }
  if (form.elements.namedItem('name') && val('name').length > 0 && val('name').length < 2) {
    errors.name = MESSAGES.name;
  }
  if (form.elements.namedItem('phone') && val('phone') !== '' && !PHONE_RE.test(cleanPhone(val('phone')))) {
    errors.phone = MESSAGES.phone;
  }
  if (form.elements.namedItem('email') && val('email') !== '' && !EMAIL_RE.test(val('email'))) {
    errors.email = MESSAGES.email;
  }
  return errors;
}

export function collectLeadPayload(form) {
  const fd = new FormData(form);
  const get = (n) => String(fd.get(n) ?? '').trim();
  const interest = get('interest');
  const message = [interest ? `Quan tâm: ${interest}` : '', get('message')].filter(Boolean).join('\n');
  return {
    source: form.dataset.drvLeadType || get('source') || 'contact',
    name: get('name'),
    phone: cleanPhone(get('phone')),
    email: get('email'),
    province: get('province'),
    ward: get('ward'),
    role: get('role') || 'chu-nha',
    project: get('project'),
    area_m2: Number(get('area_m2').replace(',', '.')) || 0,
    product_id: Number(get('product_id')) || 0,
    post_id: Number(get('post_id')) || 0,
    showroom_id: get('showroom_id'),
    preferred_time: get('preferred_time'),
    sample_box: fd.get('sample_box') === '1',
    message,
    consent: fd.get('consent') === '1',
  };
}

function focusFirstError(form, errors) {
  const first = Object.keys(errors)[0];
  const input = first ? form.querySelector(`[name="${CSS.escape(first)}"]`) : null;
  if (input instanceof HTMLElement) {
    input.focus({ preventScroll: false });
  }
}

function showStatus(form, message) {
  let box = form.querySelector('[data-lead-form-status]');
  if (!box) {
    box = document.createElement('p');
    box.className = 'drv-lead-form__status';
    box.setAttribute('data-lead-form-status', '');
    box.setAttribute('role', 'alert');
    form.prepend(box);
  }
  box.textContent = message;
  box.hidden = false;
}

function renderSuccess(form, data) {
  const box = document.createElement('div');
  box.className = 'drv-lead-success';
  box.setAttribute('role', 'status');
  box.tabIndex = -1;

  const icon = document.createElement('i');
  icon.className = 'ph-fill ph-seal-check drv-icon';
  icon.setAttribute('aria-hidden', 'true');
  const title = document.createElement('strong');
  title.className = 'drv-lead-success__title';
  title.textContent = data?.lead_code ? `Đã gửi yêu cầu #${data.lead_code}` : 'Đã gửi yêu cầu';
  const msg = document.createElement('p');
  msg.className = 'drv-lead-success__msg';
  msg.textContent = String(data?.message || 'Chuyên viên DRAVIT sẽ liên hệ lại sớm nhất.');

  box.append(icon, title, msg);
  form.hidden = true;
  form.after(box);
  box.focus();
  form.dispatchEvent(new CustomEvent('drv:lead-sent', { bubbles: true, detail: data }));
}

async function handleSubmit(form, event) {
  event.preventDefault();
  clearErrors(form);
  const errors = validateLeadForm(form);
  if (Object.keys(errors).length > 0) {
    for (const [name, message] of Object.entries(errors)) {
      setFieldError(form, name, message);
    }
    focusFirstError(form, errors);
    return;
  }

  const submit = form.querySelector('[type="submit"]');
  submit?.setAttribute('aria-busy', 'true');
  if (submit) {
    submit.disabled = true;
  }

  try {
    const honeypot = String(form.elements.namedItem('drv_website')?.value ?? '');
    const result = await postProtected('lead', endpoint('lead'), collectLeadPayload(form), { honeypot });
    if (result.ok) {
      renderSuccess(form, result.data);
      return;
    }
    const fields = result.data?.data?.fields;
    if (result.status === 422 && fields && typeof fields === 'object') {
      let placed = false;
      for (const [name, message] of Object.entries(fields)) {
        placed = setFieldError(form, name, String(message)) || placed;
      }
      if (!placed) {
        showStatus(form, errorMessage(result));
      }
      focusFirstError(form, fields);
      return;
    }
    showStatus(form, errorMessage(result));
  } catch {
    // Mất mạng / lỗi script: trả về luồng POST thuần (admin-post.php) để không mất yêu cầu của khách.
    if (form.getAttribute('action')) {
      form.submit();
      return;
    }
    showStatus(form, 'Chưa gửi được yêu cầu. Vui lòng kiểm tra kết nối và thử lại.');
  } finally {
    submit?.removeAttribute('aria-busy');
    if (submit) {
      submit.disabled = false;
    }
  }
}

export function bindLeadForm(form) {
  if (!(form instanceof HTMLFormElement) || bound.has(form)) {
    return;
  }
  bound.add(form);
  form.dataset.drvLeadBound = '1';
  prefetchToken();
  form.addEventListener('submit', (event) => handleSubmit(form, event));
  form.addEventListener('input', (event) => {
    const target = event.target;
    if (target instanceof HTMLElement && target.getAttribute('aria-invalid') === 'true') {
      setFieldError(form, target.getAttribute('name') || '', '');
    }
  });
}

export function initLeadForms(root = document) {
  for (const form of root.querySelectorAll('form[data-drv-lead-form]')) {
    bindLeadForm(form);
  }
}

/**
 * Bơm ngữ cảnh sản phẩm/diện tích vào form native và Hidden field `drv_*` của Fluent Forms.
 * @param {ParentNode} root
 * @param {{product_id?: number, sku?: string, title?: string, area_m2?: number, boxes_text?: string}} ctx
 */
export function applyLeadContext(root, ctx = {}) {
  const setVal = (selector, value) => {
    for (const el of root.querySelectorAll(selector)) {
      if (el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement) {
        el.value = String(value);
        el.dispatchEvent(new Event('input', { bubbles: true }));
      }
    }
  };
  const area = Number(ctx.area_m2) || 0;
  if (ctx.product_id) {
    setVal('input[name="product_id"], input[name="drv_product_id"]', ctx.product_id);
  }
  if (ctx.sku) {
    setVal('input[name="drv_sku"]', ctx.sku);
  }
  if (ctx.title) {
    setVal('input[name="drv_product_name"]', ctx.title);
  }
  if (area > 0) {
    setVal('input[name="area_m2"], input[name="drv_area_m2"]', Number(area.toFixed(2)));
  }
  const label = [ctx.sku, ctx.title].filter(Boolean).join(' - ');
  if (label) {
    const suffix = ctx.boxes_text ? ` (Dự tính: ${ctx.boxes_text}${area > 0 ? ` ~ ${Number(area.toFixed(2))} m²` : ''})` : '';
    setVal('input[name="interest"]', `${label}${suffix}`);
  }
  setVal('input[name="drv_page_url"]', window.location.href.split('#')[0]);
}
