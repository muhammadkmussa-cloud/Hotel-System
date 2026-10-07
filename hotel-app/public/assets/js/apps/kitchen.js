// S17 — kitchen board. Polls the change feed and refetches the board snapshot.
import { api, h, live, clock, toast, busy } from './common.js';

const lanesEl = document.querySelector('[data-lanes]');
const stationSel = document.querySelector('[data-station]');
const announce = document.querySelector('[data-announce]');
const soundBox = document.querySelector('[data-sound]');
clock(document.querySelector('[data-clock]'));

const LANES = [
  { key: 'new', title: 'New', states: ['new', 'acknowledged'] },
  { key: 'preparing', title: 'Preparing', states: ['preparing'] },
  { key: 'ready', title: 'Ready to serve', states: ['ready'] },
  { key: 'served', title: 'Recently served', states: ['served'] },
];
const ACTIONS = {
  new: [['acknowledged', 'Seen'], ['preparing', 'Start']],
  acknowledged: [['preparing', 'Start']],
  preparing: [['ready', 'Ready']],
  ready: [['served', 'Served'], ['preparing', 'Back to preparing']],
  served: [],
};
let station = localStorage.getItem('kitchen.station') ?? '';
let known = new Set();
let firstLoad = true;

function beep() {
  if (!soundBox?.checked) return;
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const o = ctx.createOscillator(); const g = ctx.createGain();
    o.frequency.value = 880; o.connect(g); g.connect(ctx.destination); g.gain.value = 0.15;
    o.start(); setTimeout(() => { o.stop(); ctx.close(); }, 250);
  } catch { /* audio unavailable */ }
}
soundBox?.addEventListener('change', () => localStorage.setItem('kitchen.sound', soundBox.checked ? '1' : '0'));
if (soundBox) soundBox.checked = localStorage.getItem('kitchen.sound') === '1';

function age(seconds) {
  const m = Math.floor(seconds / 60);
  return m < 1 ? 'just now' : m + ' min';
}

function ticket(t) {
  const late = t.state !== 'served' && t.state !== 'ready' && t.ageSeconds > 15 * 60;
  const el = h('article', { class: `kticket state-${t.state}${late ? ' late' : ''}`, 'aria-label': `${t.label}, ${t.state}` },
    h('header', {}, h('b', {}, t.label), h('span', {}, age(t.ageSeconds))),
    h('div', { class: 'small' }, `Ref ${t.reference} · ${t.createdAt}${t.station ? ' · ' + t.station : ''}`),
    t.reviewed ? h('div', { class: 'review' }, 'Guest note reviewed by staff: ', t.allergyNote ?? '', t.reviewNote ? h('div', {}, 'Staff: ' + t.reviewNote) : null) : null,
    h('ul', {}, t.items.map((i) => h('li', { style: i.cancelled ? 'text-decoration:line-through;opacity:.6' : null },
      h('span', { class: 'qty' }, i.quantity + '×'), i.name, i.cancelled ? ' (cancelled)' : '',
      i.removed.map((r) => h('span', { class: 'no' }, 'NO ' + r)),
      i.extras.map((x) => h('span', { class: 'plus' }, '+ ' + x)),
      i.note ? h('span', { class: 'note' }, '“' + i.note + '”') : null))),
    h('div', { class: 'actions' }, (ACTIONS[t.state] ?? []).map(([to, label]) => h('button', {
      type: 'button', class: to === 'preparing' && t.state === 'ready' ? 'btn-secondary' : '',
      onclick: (e) => busy(e.currentTarget, async () => {
        try {
          await api('POST', `/kitchen/tickets/${t.id}/transitions`, { to, version: t.version });
          announce.textContent = `${t.label} marked ${to}`;
        } catch (err) { toast(err.message); }
        await load();
      }, '…'),
    }, label))));
  return el;
}

async function load() {
  let data;
  try { data = await api('GET', '/kitchen/tasks' + (station ? `?station=${encodeURIComponent(station)}` : '')); } catch (e) { return; }
  if (stationSel && stationSel.options.length <= 1) {
    for (const s of data.stations) stationSel.append(h('option', { value: s.id }, s.name));
    stationSel.value = station;
  }
  const ids = new Set(data.tickets.map((t) => t.id));
  const fresh = data.tickets.filter((t) => t.state === 'new' && !known.has(t.id));
  if (!firstLoad && fresh.length) { beep(); announce.textContent = `${fresh.length} new ticket${fresh.length > 1 ? 's' : ''}`; }
  known = ids; firstLoad = false;
  lanesEl.replaceChildren(...LANES.map((lane) => {
    const items = data.tickets.filter((t) => lane.states.includes(t.state));
    return h('section', { class: 'lane', 'aria-label': lane.title },
      h('h2', {}, `${lane.title} (${items.length})`),
      items.length ? items.map(ticket) : h('p', { class: 'small', style: 'color:#9FB3A9' }, 'Nothing here.'));
  }));
}

stationSel?.addEventListener('change', () => { station = stationSel.value; localStorage.setItem('kitchen.station', station); load(); });
load();
live(() => load(), { interval: 2500 });
setInterval(load, 30000); // refresh ages
