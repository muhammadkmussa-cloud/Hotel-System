// S18 — public collection display: numbers only.
import { api, h, live, clock } from './common.js';

const prep = document.querySelector('[data-preparing]');
const ready = document.querySelector('[data-ready]');
clock(document.querySelector('[data-clock]'));
let lastReady = new Set();

const item = (e) => h('li', {}, String(e.number), e.name ? h('small', {}, e.name) : null);

async function load() {
  let d;
  try { d = await api('GET', '/collection'); } catch { return; }
  prep.replaceChildren(...(d.preparing.length ? d.preparing.map(item) : [h('li', { style: 'font-size:1.4rem;font-weight:400' }, '—')]));
  ready.replaceChildren(...(d.ready.length ? d.ready.map(item) : [h('li', { style: 'font-size:1.4rem;font-weight:400' }, '—')]));
  const now = new Set(d.ready.map((e) => e.number));
  for (const n of now) if (!lastReady.has(n)) document.title = `#${n} ready`;
  lastReady = now;
}
load();
live(() => load(), { interval: 3000 });
setInterval(load, 20000);
