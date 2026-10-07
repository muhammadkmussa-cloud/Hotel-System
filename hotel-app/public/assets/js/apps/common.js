// Shared helpers for the device and staff apps. No framework; all text is
// inserted with textContent so menu/guest data can never inject markup.

export class ApiError extends Error {
  constructor(status, code, message, fieldErrors = []) {
    super(message);
    this.status = status;
    this.code = code;
    this.fieldErrors = fieldErrors;
  }
}

export function csrf() {
  return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export function uuid() {
  if (globalThis.crypto?.randomUUID) return crypto.randomUUID();
  const b = crypto.getRandomValues(new Uint8Array(16));
  b[6] = (b[6] & 0x0f) | 0x40; b[8] = (b[8] & 0x3f) | 0x80;
  const x = [...b].map((v) => v.toString(16).padStart(2, '0')).join('');
  return `${x.slice(0, 8)}-${x.slice(8, 12)}-${x.slice(12, 16)}-${x.slice(16, 20)}-${x.slice(20)}`;
}

/** Call /api/v1 and unwrap the {data} envelope. */
export async function api(method, path, body, { idempotencyKey } = {}) {
  const headers = { Accept: 'application/json' };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (method !== 'GET') headers['X-CSRF-TOKEN'] = csrf();
  if (idempotencyKey) headers['Idempotency-Key'] = idempotencyKey;
  let res;
  try {
    res = await fetch('/api/v1' + path, { method, headers, credentials: 'same-origin', body: body === undefined ? undefined : JSON.stringify(body) });
  } catch {
    setOffline(true);
    throw new ApiError(0, 'network_error', 'No connection. Please try again in a moment.');
  }
  setOffline(false);
  let payload = null;
  try { payload = await res.json(); } catch { /* empty */ }
  if (res.ok) return payload?.data;
  const e = payload?.error ?? {};
  throw new ApiError(res.status, e.code ?? 'error', e.message ?? 'Something went wrong. Please try again.', e.fieldErrors ?? []);
}

export function setOffline(off) {
  const el = document.querySelector('[data-offline]');
  if (el) el.hidden = !off;
}

/** Tiny DOM builder: h('div', {class: 'x', onclick}, 'text', child) */
export function h(tag, attrs = {}, ...children) {
  const el = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs ?? {})) {
    if (v === null || v === undefined || v === false) continue;
    if (k.startsWith('on') && typeof v === 'function') el.addEventListener(k.slice(2), v);
    else if (k === 'class') el.className = v;
    else if (k === 'dataset') Object.assign(el.dataset, v);
    else if (v === true) el.setAttribute(k, '');
    else el.setAttribute(k, String(v));
  }
  for (const c of children.flat(Infinity)) {
    if (c === null || c === undefined || c === false) continue;
    el.append(c instanceof Node ? c : document.createTextNode(String(c)));
  }
  return el;
}

export function img(image, cls = '') {
  const wrap = h('div', { class: 'img-wrap ' + cls });
  const pic = h('picture');
  if (image?.webp) pic.append(h('source', { srcset: image.webp, type: 'image/webp' }));
  pic.append(h('img', { src: image?.src ?? '/assets/img/placeholder-meal.svg', alt: image?.alt ?? '', width: image?.width, height: image?.height, loading: 'lazy', decoding: 'async' }));
  wrap.append(pic);
  if (image?.demo) wrap.append(h('span', { class: 'demo-label' }, 'Sample image'));
  return wrap;
}

let toastTimer;
export function toast(message, ms = 3500) {
  document.querySelector('.toast')?.remove();
  const el = h('div', { class: 'toast', role: 'status', 'aria-live': 'polite' }, message);
  document.body.append(el);
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.remove(), ms);
}

/**
 * Short-poll the change feed and call onChange() when anything in our scopes
 * changes. Slows down while hidden; recovers after network errors.
 */
export function live(onChange, { query = '', interval = 3000 } = {}) {
  let cursor = 0;
  let stopped = false;
  let failures = 0;
  async function tick() {
    if (stopped) return;
    try {
      const qs = new URLSearchParams(query);
      qs.set('cursor', String(cursor));
      const res = await fetch('/api/v1/events?' + qs.toString(), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      if (!res.ok) throw new Error('status ' + res.status);
      const { data } = await res.json();
      const first = cursor === 0;
      cursor = data.cursor;
      failures = 0;
      setOffline(false);
      if (!first && (data.reload || data.events.length > 0)) await onChange(data.events, data.reload);
    } catch {
      failures++;
      if (failures >= 2) setOffline(true);
    }
    const wait = document.hidden ? interval * 4 : Math.min(30000, interval * (1 + failures));
    setTimeout(tick, wait);
  }
  tick();
  document.addEventListener('visibilitychange', () => { if (!document.hidden) onChange([], true); });
  return () => { stopped = true; };
}

export function clock(el) {
  if (!el) return;
  const tz = el.dataset.tz || undefined;
  const fmt = () => { try { el.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', timeZone: tz }); } catch { el.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); } };
  fmt();
  setInterval(fmt, 15000);
}

/** Disable a button while an async action runs; prevents double submits. */
export async function busy(button, fn, label = 'Please wait…') {
  if (button?.disabled) return;
  const old = button?.textContent;
  if (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); button.textContent = label; }
  try { return await fn(); } finally {
    if (button && button.isConnected) { button.disabled = false; button.removeAttribute('aria-busy'); button.textContent = old; }
  }
}

export function formatKsh(minor) {
  const neg = minor < 0; minor = Math.abs(minor);
  return (neg ? '−' : '') + 'KSh ' + Math.floor(minor / 100).toLocaleString('en-KE') + '.' + String(minor % 100).padStart(2, '0');
}
