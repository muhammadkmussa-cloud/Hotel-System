// S11–S16 — self-order kiosk: welcome, menu, review, payment, collection number.
import { api, h, live, toast, busy, uuid, ApiError } from './common.js';
import { menuView, openMeal, showSheet, cartLines, allergyBox } from './customer.js';

const root = document.getElementById('app');
const BASE = '/kiosk';
const IDLE_MS = 120000;
const state = {
  view: 'welcome', category: null, menu: null, cart: null, status: null, submitKey: null,
  dining: null, route: null,
  set(p) { Object.assign(state, p); render(); },
};
let closeSheet = null;
let idleTimer = null;
let statusTimer = null;

function guard(err) {
  if (err instanceof ApiError && err.status === 401) { location.href = '/device/pair'; return true; }
  return false;
}

async function start() {
  try {
    await api('POST', `${BASE}/sessions`);
    const [menu, cart] = await Promise.all([api('GET', `${BASE}/menu`), api('GET', `${BASE}/cart`)]);
    Object.assign(state, { menu, cart, status: null, submitKey: null, dining: null, route: null, category: null, view: 'menu' });
    render();
  } catch (e) { if (!guard(e)) toast(e.message); }
}

async function reset() {
  closeSheet?.();
  if (state.status && state.status.state === 'draft' && (state.cart?.count ?? 0) > 0) {
    try { await api('POST', `${BASE}/cancel`); } catch { /* ignore */ }
  }
  clearTimeout(statusTimer); statusTimer = null;
  Object.assign(state, { view: 'welcome', cart: null, status: null, submitKey: null });
  render();
}

function poke() {
  clearTimeout(idleTimer);
  if (['menu', 'review'].includes(state.view)) {
    idleTimer = setTimeout(stillThere, IDLE_MS);
  } else if (state.view === 'done') {
    idleTimer = setTimeout(reset, 45000);
  }
}
['pointerdown', 'keydown'].forEach((t) => document.addEventListener(t, poke, { passive: true }));

function stillThere() {
  if ((state.cart?.count ?? 0) === 0) { reset(); return; }
  let left = 20;
  const count = h('b', {}, String(left));
  const timer = setInterval(() => { left--; count.textContent = String(left); if (left <= 0) { clearInterval(timer); api('POST', `${BASE}/cancel`).catch(() => {}); reset(); } }, 1000);
  closeSheet = showSheet(h('div', { class: 'sheet', role: 'alertdialog', 'aria-modal': 'true', 'aria-labelledby': 'idle-h' },
    h('div', { class: 'content center' }, h('h2', { id: 'idle-h' }, 'Are you still there?'),
      h('p', {}, 'Your order will be cleared in ', count, ' seconds.'),
      h('button', { type: 'button', class: 'btn-large', onclick: () => { clearInterval(timer); closeSheet(); poke(); } }, 'Continue my order'))), () => { clearInterval(timer); closeSheet(); poke(); });
}

async function open(mealId) {
  const content = await openMeal(BASE, mealId, {
    addLine: async (payload) => { state.cart = await api('POST', `${BASE}/cart/lines`, payload); render(); },
    close: () => closeSheet?.(),
  });
  if (content) closeSheet = showSheet(content, () => closeSheet());
}

function choice(key, value, title, desc) {
  return h('button', { type: 'button', class: 'choice', 'aria-pressed': String(state[key] === value), onclick: () => state.set({ [key]: value }) }, h('b', {}, title), desc ? h('span', { class: 'small' }, desc) : null);
}

function reviewView() {
  const cart = state.cart;
  if (!cart || cart.lines.length === 0) { state.view = 'menu'; return menuView(state, { onOpen: open }); }
  const { box, area } = allergyBox('kiosk-allergy');
  const name = h('input', { id: 'kiosk-name', maxlength: 40, autocomplete: 'off', placeholder: 'e.g. Amina' });
  const mpesaOk = cart.totalMinor % 100 === 0;
  const send = h('button', { type: 'button', class: 'btn-large' }, `Place order · ${cart.total}`);
  send.addEventListener('click', () => busy(send, async () => {
    if (!state.dining) { toast('Choose eat in or takeaway.'); return; }
    if (!state.route) { toast('Choose how you will pay.'); return; }
    state.submitKey ??= uuid();
    try {
      state.status = await api('POST', `${BASE}/orders`, { quoteDigest: cart.digest, allergyNote: area.value.trim() || null, route: state.route, dining: state.dining, name: name.value.trim() || null }, { idempotencyKey: state.submitKey });
      state.submitKey = null;
      state.view = 'status';
      render();
    } catch (e) {
      if (guard(e)) return;
      // Preserve the key on network/5xx/408/429 so a retry cannot duplicate the
      // order; reset only on a definitive 4xx.
      const definitive = e.status >= 400 && e.status < 500 && e.status !== 408 && e.status !== 429;
      if (definitive) state.submitKey = null;
      toast(e.message, 6000);
      state.cart = await api('GET', `${BASE}/cart`).catch(() => state.cart);
      render();
    }
  }, 'Placing order…'));
  return h('section', { class: 'card', 'aria-labelledby': 'rev-h' },
    h('div', { class: 'card-head' }, h('h1', { id: 'rev-h' }, 'Your order'), h('button', { type: 'button', class: 'btn-secondary', onclick: () => state.set({ view: 'menu' }) }, 'Add more')),
    cartLines(cart, { base: BASE, onChange: (c) => { state.cart = c; render(); } }),
    h('h2', {}, 'Eat in or takeaway?'),
    h('div', { class: 'choice-grid' }, choice('dining', 'eat_in', 'Eat in'), choice('dining', 'takeaway', 'Takeaway')),
    h('h2', {}, 'How will you pay?'),
    h('div', { class: 'choice-grid' },
      mpesaOk ? choice('route', 'mpesa', 'M-PESA here', 'Enter your phone number on the next screen') : null,
      choice('route', 'cashier', 'Pay at the counter', 'Cash or card with the cashier')),
    h('div', { class: 'field' }, h('label', { for: 'kiosk-name' }, 'Name for collection (optional)'), name),
    box, send);
}

async function refreshStatus() {
  clearTimeout(statusTimer);
  try {
    state.status = await api('GET', `${BASE}/status`);
    if (['released', 'paid', 'collected'].includes(state.status.state)) state.view = 'done';
    render();
  } catch (e) { if (guard(e)) return; }
  statusTimer = state.view === 'status' ? setTimeout(refreshStatus, 3000) : null;
}

function statusView() {
  const s = state.status;
  if (!s) return h('p', {}, 'Loading…');
  if (s.state === 'review_hold') {
    return h('div', { class: 'kiosk-hero' }, h('div', {}, h('h1', {}, 'Thank you'),
      h('p', { style: 'font-size:1.3rem' }, 'A member of staff is checking your note with the kitchen. Please wait here or go to the counter.'),
      h('p', {}, 'Your reference: ', h('b', { style: 'font-size:2rem;letter-spacing:.1em' }, s.reference))));
  }
  if (['cancelled', 'expired', 'declined'].includes(s.state)) {
    return h('div', { class: 'kiosk-hero' }, h('div', {}, h('h1', {}, s.state === 'declined' ? 'We couldn’t accept this order' : 'Order cancelled'),
      h('p', {}, s.state === 'expired' ? 'Payment was not completed in time. Nothing was charged.' : 'Please speak to a member of staff if you need help.'),
      h('button', { type: 'button', class: 'btn-large', onclick: reset }, 'Start again')));
  }
  if (s.state === 'pending_payment' && s.route === 'cashier') {
    return h('div', { class: 'kiosk-hero' }, h('div', {}, h('h1', {}, 'Please pay at the counter'),
      h('p', {}, 'Show this code to the cashier:'),
      h('div', { class: 'big-number', style: 'letter-spacing:.08em' }, s.reference),
      h('p', { style: 'font-size:1.3rem' }, `Total ${s.total}`),
      s.expiresInSeconds !== null ? h('p', { class: 'muted' }, `Please pay within ${Math.max(1, Math.ceil(s.expiresInSeconds / 60))} minutes.`) : null,
      h('div', { class: 'btn-row', style: 'justify-content:center' },
        s.mpesaEligible ? h('button', { type: 'button', class: 'btn-secondary', onclick: () => { state.status.route = 'mpesa'; render(); } }, 'Pay with M-PESA instead') : null,
        h('button', { type: 'button', class: 'btn-ghost', onclick: cancelOrder }, 'Cancel order'),
        h('button', { type: 'button', onclick: reset }, 'Done'))));
  }
  if (s.state === 'pending_payment') {
    const a = s.attempt;
    const pending = a && a.state === 'pending';
    const phone = h('input', { id: 'kiosk-phone', inputmode: 'tel', maxlength: 16, placeholder: '07XX XXX XXX', autocomplete: 'off' });
    const send = h('button', { type: 'button', class: 'btn-large' }, `Send M-PESA request for ${s.total}`);
    send.addEventListener('click', () => busy(send, async () => {
      try { state.status = await api('POST', `${BASE}/mpesa-attempts`, { phone: phone.value }); render(); refreshStatus(); } catch (e) { toast(e.message, 6000); }
    }, 'Sending…'));
    return h('section', { class: 'card', style: 'max-width:40rem;margin:2rem auto' },
      h('h1', {}, 'Pay with M-PESA'), h('p', {}, `Total: `, h('b', {}, s.total), ` · Reference ${s.reference}`),
      a ? h('div', { class: `notice ${['failed', 'cancelled'].includes(a.state) ? 'notice-warn' : 'notice-info'}`, role: 'status', 'aria-live': 'polite' }, a.message) : null,
      pending ? h('p', { style: 'font-size:1.2rem' }, 'Check your phone and enter your M-PESA PIN.')
        : [h('div', { class: 'field' }, h('label', { for: 'kiosk-phone' }, 'M-PESA phone number'), phone), send],
      !pending ? h('div', { class: 'btn-row', style: 'margin-top:1rem' },
        h('button', { type: 'button', class: 'btn-secondary', onclick: (e) => busy(e.currentTarget, async () => { try { state.status = await api('POST', `${BASE}/pay-at-cashier`); render(); } catch (err) { toast(err.message); } }) }, 'Pay at the counter instead'),
        h('button', { type: 'button', class: 'btn-ghost', onclick: cancelOrder }, 'Cancel order')) : null);
  }
  return h('p', {}, 'Please wait…');
}

async function cancelOrder(e) {
  await busy(e.currentTarget, async () => {
    try { await api('POST', `${BASE}/cancel`); toast('Order cancelled.'); } catch (err) { toast(err.message); }
    reset();
  });
}

function doneView() {
  const s = state.status;
  return h('div', { class: 'kiosk-hero' }, h('div', {},
    h('h1', {}, 'Thank you!'), h('p', { style: 'font-size:1.3rem' }, 'Your order number is'),
    h('div', { class: 'big-number' }, String(s.collectionNumber ?? '—')),
    s.name ? h('p', { style: 'font-size:1.4rem' }, s.name) : null,
    h('p', {}, s.dining === 'eat_in' ? 'Take a seat — we’ll call your number.' : 'Watch the screen for your number.'),
    s.receiptNumber ? h('p', { class: 'muted' }, `Receipt ${s.receiptNumber}`) : null,
    h('button', { type: 'button', class: 'btn-large', onclick: reset }, 'Done')));
}

function welcomeView() {
  return h('div', { class: 'kiosk-hero' }, h('div', {},
    h('p', { class: 'muted' }, document.title.split(' — ')[0]),
    h('h1', {}, 'Order here'),
    h('p', { style: 'font-size:1.3rem' }, 'Choose your food, pay with M-PESA or at the counter, and collect when your number is called.'),
    h('button', { type: 'button', class: 'btn-large', style: 'font-size:1.6rem;padding:1.2rem 3rem', onclick: (e) => busy(e.currentTarget, start, 'Loading…') }, 'Start order')));
}

function render() {
  const count = state.cart?.count ?? 0;
  const top = ['menu', 'review'].includes(state.view) ? h('header', { class: 'app-top' },
    h('div', { class: 'who' }, state.menu?.hotel ?? ''),
    h('nav', {}, h('button', { type: 'button', class: 'btn-ghost', onclick: () => (count ? stillThereCancel() : reset()) }, 'Start over'))) : null;
  const main = h('main', { class: 'app-main', id: 'main' });
  if (state.view === 'welcome') main.append(welcomeView());
  if (state.view === 'menu') main.append(menuView(state, { onOpen: open }));
  if (state.view === 'review') main.append(reviewView());
  if (state.view === 'status') main.append(statusView());
  if (state.view === 'done') main.append(doneView());
  const bar = state.view === 'menu' && count > 0 ? h('div', { class: 'cartbar' }, h('span', {}, `${count} item${count > 1 ? 's' : ''} · ${state.cart.total}`),
    h('button', { type: 'button', onclick: () => state.set({ view: 'review' }) }, 'Review and pay')) : null;
  root.replaceChildren(...[top, main, bar].filter(Boolean));
  poke();
  if (state.view === 'status' && !statusTimer) refreshStatus();
}

function stillThereCancel() {
  if (window.confirm('Clear your order and start over?')) { api('POST', `${BASE}/cancel`).catch(() => {}); reset(); }
}

/** After a reload (power blip, accidental refresh) pick up where the customer was. */
async function resume() {
  try {
    const status = await api('GET', `${BASE}/status`);
    if (status.state === 'draft') {
      const [menu, cart] = await Promise.all([api('GET', `${BASE}/menu`), api('GET', `${BASE}/cart`)]);
      if (cart.count > 0) { Object.assign(state, { menu, cart, view: 'menu' }); }
    } else if (['pending_payment', 'review_hold'].includes(status.state)) {
      Object.assign(state, { status, view: 'status' });
    }
  } catch (e) { guard(e); }
  render();
}

render();
resume();
live(async (events, reload) => {
  if (reload || events.some((e) => e.topic === 'menu.changed')) {
    if (['menu', 'review'].includes(state.view)) {
      const [menu, cart] = await Promise.all([api('GET', `${BASE}/menu`), api('GET', `${BASE}/cart`)]).catch(() => [state.menu, state.cart]);
      state.menu = menu; state.cart = cart; render();
    }
  }
  if (events.some((e) => e.topic.startsWith('kiosk')) && ['status', 'done'].includes(state.view)) refreshStatus();
}, { interval: 3000 });
