// S01–S10 — guest tablet: menu, customiser, order review, order status, bill and payment.
import { api, h, live, toast, busy, uuid, ApiError } from './common.js';
import { menuView, openMeal, showSheet, cartLines, allergyBox } from './customer.js';

const root = document.getElementById('app');
const BASE = '/table';
const state = {
  view: 'menu', category: null, ctx: null, menu: null, cart: null, orders: null, bill: null, submitKey: null, mpesa: null,
  set(patch) { Object.assign(state, patch); render(); },
};
let closeSheet = null;

function guard(err) {
  if (err instanceof ApiError && err.status === 401) {
    location.href = err.code === 'GUEST_BINDING_REQUIRED' ? '/table/waiting' : '/device/pair';
    return true;
  }
  return false;
}

async function load(parts = ['ctx', 'menu', 'cart', 'orders', 'bill']) {
  const jobs = {
    ctx: () => api('GET', `${BASE}/context`),
    menu: () => api('GET', `${BASE}/menu`),
    cart: () => api('GET', `${BASE}/cart`),
    orders: () => api('GET', `${BASE}/orders`),
    bill: () => api('GET', `${BASE}/bill`),
  };
  try {
    const results = await Promise.all(parts.map((p) => jobs[p]()));
    parts.forEach((p, i) => { state[p] = results[i]; });
    render();
  } catch (err) {
    if (!guard(err)) toast(err.message);
  }
}

function setCart(cart) { state.cart = cart; render(); }

function top() {
  const count = state.cart?.count ?? 0;
  const tab = (key, label) => h('button', { type: 'button', class: 'btn-secondary', 'aria-current': state.view === key ? 'page' : null, onclick: () => state.set({ view: key }) }, label);
  return h('header', { class: 'app-top' },
    h('div', {}, h('div', { class: 'who' }, `${state.ctx?.table ?? ''} · ${state.ctx?.guest ?? ''}`), h('div', { class: 'small muted' }, state.ctx?.hotel ?? '')),
    h('nav', { 'aria-label': 'Sections' }, tab('menu', 'Menu'), tab('cart', `My order${count ? ' (' + count + ')' : ''}`), tab('orders', 'Ordered'), tab('bill', 'Bill'),
      h('button', { type: 'button', onclick: help }, 'Call waiter')));
}

async function help() {
  const note = h('textarea', { id: 'help-note', maxlength: 300, rows: 2 });
  let kind = 'call_waiter';
  const choice = (k, label) => h('button', { type: 'button', class: 'choice', 'aria-pressed': String(kind === k), onclick: (e) => {
    kind = k; e.currentTarget.parentElement.querySelectorAll('.choice').forEach((b) => b.setAttribute('aria-pressed', String(b === e.currentTarget)));
  } }, h('b', {}, label));
  const send = h('button', { type: 'button', class: 'btn-large' }, 'Send');
  send.addEventListener('click', () => busy(send, async () => {
    try { await api('POST', `${BASE}/service-requests`, { kind, note: note.value.trim() || null }); closeSheet(); toast('A member of staff is on the way.'); load(['orders']); } catch (e) { if (!guard(e)) toast(e.message); }
  }));
  closeSheet = showSheet(h('div', { class: 'sheet', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'help-title' },
    h('button', { type: 'button', class: 'close btn-secondary', onclick: () => closeSheet(), 'aria-label': 'Close' }, '✕'),
    h('div', { class: 'content' }, h('h2', { id: 'help-title' }, 'How can we help?'),
      h('div', { class: 'choice-grid' }, choice('call_waiter', 'Call the waiter'), choice('bill_help', 'Help with the bill'), choice('change_request', 'Change or cancel an order')),
      h('div', { class: 'field' }, h('label', { for: 'help-note' }, 'Note (optional)'), note), send)), () => closeSheet());
}

async function open(mealId) {
  const content = await openMeal(BASE, mealId, {
    addLine: async (payload) => setCart(await api('POST', `${BASE}/cart/lines`, payload)),
    close: () => closeSheet?.(),
  });
  if (content) closeSheet = showSheet(content, () => closeSheet());
}

function cartView() {
  const cart = state.cart;
  if (!cart || cart.lines.length === 0) {
    return h('div', { class: 'card center' }, h('h2', {}, 'Your order is empty'), h('p', { class: 'muted' }, 'Choose dishes from the menu.'),
      h('button', { type: 'button', onclick: () => state.set({ view: 'menu' }) }, 'See the menu'));
  }
  const { box, area } = allergyBox();
  const send = h('button', { type: 'button', class: 'btn-large', disabled: cart.conflicts.length > 0 }, `Send order · ${cart.total}`);
  send.addEventListener('click', () => busy(send, async () => {
    state.submitKey ??= uuid();
    try {
      const res = await api('POST', `${BASE}/orders`, { quoteDigest: cart.digest, allergyNote: area.value.trim() || null }, { idempotencyKey: state.submitKey });
      state.submitKey = null;
      toast(res.state === 'review_hold' ? 'Order sent. Staff will check your note with the kitchen first.' : 'Order sent to the kitchen!', 5000);
      state.view = 'orders';
      await load(['cart', 'orders', 'bill', 'menu']);
    } catch (e) {
      if (guard(e)) return;
      if (e.status !== 0) state.submitKey = null; // retry the same key only after network failures
      toast(e.message, 6000);
      load(['cart', 'menu']);
    }
  }, 'Sending…'));
  return h('section', { class: 'card', 'aria-labelledby': 'cart-h' }, h('h1', { id: 'cart-h' }, 'Your order'),
    h('p', { class: 'small muted' }, 'This order is only for you. Each guest at the table orders on their own.'),
    cartLines(cart, { base: BASE, onChange: setCart }), box, send);
}

const STEPS = ['new', 'acknowledged', 'preparing', 'ready', 'served'];
const STEP_LABEL = { new: 'Received', acknowledged: 'Seen', preparing: 'Preparing', ready: 'Ready', served: 'Served' };
function ordersView() {
  const o = state.orders;
  if (!o) return h('p', {}, 'Loading…');
  const requests = o.requests.filter((r) => r.state !== 'resolved');
  return h('div', {},
    requests.length ? h('div', { class: 'notice notice-info' }, requests.map((r) => h('div', {}, `${r.kindLabel}: ${r.state === 'acknowledged' ? 'staff are on the way' : 'request sent'}`))) : null,
    o.orders.length === 0 ? h('div', { class: 'card center' }, h('p', {}, 'You haven’t ordered anything yet.')) : null,
    o.orders.map((s) => h('section', { class: 'card' },
      h('div', { class: 'card-head' }, h('h2', {}, s.statusLabel), h('span', { class: 'muted small' }, `${s.submittedAt} · ${s.total}`)),
      h('ul', { class: 'line-list' }, s.items.map((i) => h('li', {},
        h('div', { class: 'line-row' }, h('span', { style: i.cancelled ? 'text-decoration:line-through' : null }, `${i.quantity} × ${i.name}`), h('span', { class: 'num' }, i.lineTotal)),
        i.removed.length ? h('div', { class: 'mods' }, 'No ' + i.removed.join(', no ')) : null,
        i.extras.length ? h('div', { class: 'mods add' }, '+ ' + i.extras.join(', + ')) : null,
        STEPS.includes(i.status) ? h('ol', { class: 'status-steps', 'aria-label': 'Progress' }, STEPS.map((st) => h('li', {
          class: STEPS.indexOf(st) < STEPS.indexOf(i.status) ? 'done' : (st === i.status ? 'current' : ''),
          'aria-current': st === i.status ? 'step' : null,
        }, STEP_LABEL[st]))) : h('span', { class: 'pill' }, i.status.replace('_', ' '))))))));
}

function billView() {
  const b = state.bill;
  if (!b) return h('p', {}, 'Loading…');
  const mates = state.ctx?.tablemates ?? [];
  const lines = b.lines.map((l) => h('li', {},
    h('div', { class: 'line-row' }, h('span', {}, `${l.quantity} × ${l.name}`, l.shared ? h('span', { class: 'pill pill-info' }, ` shared ÷${l.shareCount}`) : null,
      l.discounted ? h('span', { class: 'pill pill-ok' }, ' discounted') : null),
    h('span', { class: 'num' }, l.amount, l.state === 'paid' ? ' ✓' : '')),
    l.state === 'pending' ? h('div', { class: 'small muted' }, 'Waiting for the kitchen review') : null,
    l.state === 'open' && !l.shared && mates.length ? h('button', { type: 'button', class: 'btn-link btn-small', onclick: () => share(l) }, 'Share this dish') : null));
  const checkout = b.checkout;
  let pay = null;
  if (b.balanceMinor > 0) {
    if (checkout && checkout.state === 'open') pay = mpesaPanel(checkout);
    else pay = h('div', { class: 'btn-row' },
      h('button', { type: 'button', class: 'btn-large', onclick: (e) => busy(e.currentTarget, async () => {
        try { await api('POST', `${BASE}/checkouts`); await load(['bill']); } catch (err) { if (!guard(err)) toast(err.message); }
      }) }, `Pay ${b.balance} with M-PESA`),
      h('button', { type: 'button', class: 'btn-secondary btn-large', onclick: (e) => busy(e.currentTarget, async () => {
        try { await api('POST', `${BASE}/service-requests`, { kind: 'bill_help', note: 'Would like to pay by cash or card' }); toast('Your waiter will bring the bill.'); } catch (err) { toast(err.message); }
      }) }, 'Pay by cash or card'));
  }
  return h('section', { class: 'card', 'aria-labelledby': 'bill-h' }, h('h1', { id: 'bill-h' }, 'Your bill'),
    b.proposals.length ? h('div', { class: 'notice notice-info' }, b.proposals.map((p) => h('div', {}, `Share request: ${p.item} between ${p.guests.join(', ')} — waiting for staff`))) : null,
    b.lines.length ? h('ul', { class: 'line-list' }, lines) : h('p', { class: 'muted' }, 'Nothing on your bill yet.'),
    h('dl', { class: 'facts', style: 'margin-top:1rem' },
      b.paidMinor > 0 ? [h('dt', {}, 'Paid'), h('dd', {}, b.paid)] : null,
      b.pendingMinor > 0 ? [h('dt', {}, 'Awaiting review'), h('dd', {}, b.pending)] : null,
      h('dt', {}, h('b', {}, 'To pay')), h('dd', {}, h('b', { style: 'font-size:1.4em' }, b.balance))),
    pay,
    b.lastReceipt ? h('p', {}, h('button', { type: 'button', class: 'btn-link', onclick: () => receipt(b.lastReceipt.checkoutId) }, `View receipt ${b.lastReceipt.number}`)) : null);
}

function mpesaPanel(checkout) {
  const attempt = state.mpesa ?? checkout.latestAttempt;
  const phone = h('input', { id: 'mpesa-phone', inputmode: 'tel', autocomplete: 'tel', maxlength: 16, placeholder: '07XX XXX XXX', required: true });
  const send = h('button', { type: 'button', class: 'btn-large' }, `Send M-PESA request for ${checkout.remaining}`);
  send.addEventListener('click', () => busy(send, async () => {
    try {
      state.mpesa = await api('POST', `${BASE}/checkouts/${checkout.id}/mpesa-attempts`, { phone: phone.value });
      render(); pollAttempt(state.mpesa.id);
    } catch (e) { if (!guard(e)) toast(e.message, 6000); }
  }, 'Sending…'));
  const pending = attempt && attempt.state === 'pending';
  return h('div', { class: 'card', style: 'background:var(--ok-bg)' },
    h('h2', {}, 'Pay with M-PESA'),
    state.ctx?.mpesaMode === 'simulator' ? h('p', { class: 'small' }, 'Demo mode: no money will move.') : null,
    attempt ? h('div', { class: `notice ${attempt.state === 'failed' || attempt.state === 'cancelled' ? 'notice-warn' : 'notice-info'}`, role: 'status', 'aria-live': 'polite' }, attempt.message) : null,
    !checkout.mpesaEligible ? h('p', { class: 'notice notice-warn' }, 'M-PESA needs a whole-shilling amount. Please pay with your waiter.') : null,
    !pending && checkout.mpesaEligible ? [h('div', { class: 'field' }, h('label', { for: 'mpesa-phone' }, 'M-PESA phone number'), phone), send] : null,
    pending ? h('p', {}, 'Check your phone and enter your M-PESA PIN. Do not pay twice.') : null,
    !pending && checkout.paidMinor === 0 ? h('button', { type: 'button', class: 'btn-ghost', onclick: (e) => busy(e.currentTarget, async () => {
      try { await api('POST', `${BASE}/checkouts/${checkout.id}/cancel`); state.mpesa = null; await load(['bill']); } catch (err) { toast(err.message); }
    }) }, 'Cancel') : null);
}

let attemptTimer = null;
async function pollAttempt(id) {
  clearTimeout(attemptTimer);
  try {
    const a = await api('GET', `${BASE}/payment-attempts/${id}`);
    state.mpesa = a;
    if (a.state === 'pending') { render(); attemptTimer = setTimeout(() => pollAttempt(id), 3000); return; }
    if (a.checkout?.state === 'paid') { toast('Payment received. Thank you!', 5000); state.mpesa = null; }
    await load(['bill', 'orders']);
  } catch (e) { attemptTimer = setTimeout(() => pollAttempt(id), 5000); }
}

function share(line) {
  const chosen = new Set();
  const mates = state.ctx.tablemates;
  const send = h('button', { type: 'button', class: 'btn-large' }, 'Ask staff to split it');
  send.addEventListener('click', () => busy(send, async () => {
    if (!chosen.size) { toast('Choose who you shared it with.'); return; }
    try { await api('POST', `${BASE}/share-proposals`, { chargeId: line.chargeId, guestIds: [...chosen] }); closeSheet(); toast('Staff will confirm the split.'); load(['bill']); } catch (e) { toast(e.message); }
  }));
  closeSheet = showSheet(h('div', { class: 'sheet', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'share-h' },
    h('button', { type: 'button', class: 'close btn-secondary', onclick: () => closeSheet(), 'aria-label': 'Close' }, '✕'),
    h('div', { class: 'content' }, h('h2', { id: 'share-h' }, `Share ${line.name}`),
      h('p', {}, 'Who shared this dish with you? The price is split equally once staff confirm.'),
      h('div', { class: 'choice-grid' }, mates.map((m) => h('button', { type: 'button', class: 'choice', 'aria-pressed': 'false', onclick: (e) => {
        chosen.has(m.id) ? chosen.delete(m.id) : chosen.add(m.id); e.currentTarget.setAttribute('aria-pressed', String(chosen.has(m.id)));
      } }, h('b', {}, m.label)))), send)), () => closeSheet());
}

async function receipt(checkoutId) {
  let r;
  try { r = await api('GET', `${BASE}/receipts/${checkoutId}`); } catch (e) { toast(e.message); return; }
  closeSheet = showSheet(h('div', { class: 'sheet', role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Receipt' },
    h('button', { type: 'button', class: 'close btn-secondary', onclick: () => closeSheet(), 'aria-label': 'Close' }, '✕'),
    h('div', { class: 'content' }, h('div', { class: 'receipt' },
      r.testMode ? 'TEST MODE — NOT A SALE\n' : '', h('b', {}, r.hotel), '\n', `Receipt ${r.number}\n${r.paidAt}\n${r.context}\n`, '—'.repeat(20), '\n',
      r.lines.map((l) => `${l.quantity} × ${l.name}${l.shared ? ' (share)' : ''}   ${l.amount}\n`),
      '—'.repeat(20), `\nTOTAL   ${r.total}\n`, r.tax ? `${r.tax.label}   ${r.tax.amount}\n` : '',
      r.payments.map((p) => `${p.method} ${p.reference ?? ''}   ${p.amount}\n`),
      r.fiscal && r.fiscal.provider === 'simulator' ? '\nSIMULATED — not a tax invoice\n' : '', r.footer ? `\n${r.footer}` : ''))), () => closeSheet());
}

function render() {
  const main = h('main', { class: 'app-main', id: 'main' });
  if (state.view === 'menu') main.append(menuView(state, { onOpen: open }));
  if (state.view === 'cart') main.append(cartView());
  if (state.view === 'orders') main.append(ordersView());
  if (state.view === 'bill') main.append(billView());
  const count = state.cart?.count ?? 0;
  const bar = state.view === 'menu' && count > 0 ? h('div', { class: 'cartbar' }, h('span', {}, `${count} item${count > 1 ? 's' : ''} · ${state.cart.total}`),
    h('button', { type: 'button', onclick: () => state.set({ view: 'cart' }) }, 'Review order')) : null;
  const focusId = document.activeElement?.id;
  root.replaceChildren(top(), main, bar ?? '');
  if (focusId) document.getElementById(focusId)?.focus();
}

load();
live(async (events, reload) => {
  const topics = new Set(events.map((e) => e.topic));
  if (reload) return load();
  const parts = new Set();
  if (topics.has('menu.changed')) { parts.add('menu'); parts.add('cart'); }
  if ([...topics].some((t) => t.startsWith('order.') || t.startsWith('service_request') || t.startsWith('kitchen'))) parts.add('orders');
  if ([...topics].some((t) => t.startsWith('bill.') || t.startsWith('checkout') || t.startsWith('order.') || t.startsWith('share'))) parts.add('bill');
  if ([...topics].some((t) => t.startsWith('visit.') || t.startsWith('guest'))) { parts.add('ctx'); }
  if (parts.size) await load([...parts]);
}, { interval: 3000 });
setInterval(() => load(['ctx']), 30000);
