// Shared customer ordering UI (menu, customiser, cart) used by the table
// tablet and the kiosk. The backend owns every price; the UI only displays
// server-quoted totals and sends the quote digest back on submit.
import { api, h, img, toast, busy } from './common.js';

export function menuView(state, { onOpen }) {
  const { menu } = state;
  if (!menu || menu.meals.length === 0) {
    return h('div', { class: 'card center' }, h('h2', {}, 'The menu is being prepared'), h('p', { class: 'muted' }, 'Please ask a member of staff for help.'));
  }
  const cats = menu.categories;
  const active = state.category ?? null;
  let query = state.query ?? '';

  const dish = (m) => h('button', {
    type: 'button', class: 'dish', 'aria-disabled': String(!m.available),
    'aria-label': `${m.name}, ${m.price}${m.available ? '' : ', ' + (m.availabilityNote ?? 'unavailable')}`,
    onclick: () => onOpen(m.id),
  }, img(m.image), h('div', { class: 'body' },
    h('span', { class: 'name' }, m.name),
    m.description ? h('span', { class: 'desc' }, m.description) : null,
    h('span', { class: 'price' }, m.price),
    !m.available ? h('span', { class: 'pill pill-danger' }, m.availabilityNote ?? 'Sold out')
      : (m.availabilityNote ? h('span', { class: 'pill pill-warn' }, m.availabilityNote) : null)));

  const inCategory = () => active === null ? menu.meals : menu.meals.filter((m) => (m.categoryId ?? null) === (active === '__more' ? null : active));
  const matches = (list) => {
    const q = query.trim().toLowerCase();
    return q === '' ? list : list.filter((m) => (m.name ?? '').toLowerCase().includes(q) || (m.description ?? '').toLowerCase().includes(q));
  };

  const grid = h('div', { class: 'dish-grid' });
  const noResults = h('div', { class: 'card center', hidden: true },
    h('p', { class: 'muted' }, 'No dishes match your search.'),
    h('button', { type: 'button', class: 'btn-secondary', onclick: () => { query = ''; input.value = ''; render(); input.focus(); } }, 'Show all dishes'));
  const render = () => {
    const list = matches(inCategory());
    grid.replaceChildren(...list.map(dish));
    noResults.hidden = list.length !== 0;
  };
  const input = h('input', { id: 'menu-search', type: 'search', placeholder: 'Search dishes', value: state.query ?? '',
    oninput: (e) => { query = e.target.value; state.query = query; render(); } });
  render();

  return h('div', {},
    h('div', { class: 'menu-search field' },
      h('label', { for: 'menu-search', class: 'visually-hidden' }, 'Search the menu'),
      input),
    cats.length > 1 ? h('div', { class: 'cat-tabs', role: 'toolbar', 'aria-label': 'Menu sections' },
      h('button', { type: 'button', 'aria-pressed': String(active === null), onclick: () => state.set({ category: null }) }, 'All'),
      cats.map((c) => h('button', { type: 'button', 'aria-pressed': String(active === (c.id ?? '__more')), onclick: () => state.set({ category: c.id ?? '__more' }) }, c.name))) : null,
    grid,
    noResults);
}

/** Meal customiser sheet. Calls addLine(payload) on confirm. */
export async function openMeal(base, mealId, { addLine, close }) {
  let meal;
  try { meal = await api('GET', `${base}/meals/${mealId}`); } catch (e) { toast(e.message); return; }
  const removed = new Set();
  const extras = new Set();
  let qty = 1;
  const totalEl = h('span');
  const updateTotal = () => {
    const extra = meal.ingredients.filter((i) => extras.has(i.id)).reduce((s, i) => s + i.extraPriceMinor, 0);
    const minor = (meal.priceMinor + extra) * qty;
    totalEl.textContent = 'KSh ' + Math.floor(minor / 100).toLocaleString('en-KE') + '.' + String(minor % 100).padStart(2, '0');
  };
  const qtyOut = h('output', { 'aria-live': 'polite' }, '1');
  const note = h('textarea', { id: 'line-note', maxlength: 200, rows: 2, placeholder: 'e.g. well done, sauce on the side' });

  const ingRow = (i) => {
    const row = h('li', { class: 'ing' });
    const render = () => {
      row.className = 'ing' + (removed.has(i.id) ? ' removed' : '');
      const control = i.rule === 'removable'
        ? h('button', { type: 'button', class: removed.has(i.id) ? '' : 'btn-secondary', 'aria-pressed': String(removed.has(i.id)),
          onclick: () => { removed.has(i.id) ? removed.delete(i.id) : removed.add(i.id); render(); } }, removed.has(i.id) ? 'Add back' : 'Remove')
        : i.rule === 'extra'
          ? h('button', { type: 'button', class: extras.has(i.id) ? '' : 'btn-secondary', 'aria-pressed': String(extras.has(i.id)),
            onclick: () => { extras.has(i.id) ? extras.delete(i.id) : extras.add(i.id); render(); updateTotal(); } }, extras.has(i.id) ? 'Added ✓' : `Add ${i.extraPrice}`)
          : h('span', { class: 'rule' }, 'Included');
      row.replaceChildren(
        h('img', { src: i.image.src, alt: '', width: 56, height: 56, loading: 'lazy' }),
        h('div', {}, h('div', { class: 'iname' }, i.name),
          i.description ? h('div', { class: 'small muted' }, i.description) : null,
          i.components?.length ? h('div', { class: 'small muted' }, 'Contains: ' + i.components.join(', ')) : null),
        control);
    };
    render();
    return row;
  };
  const included = meal.ingredients.filter((i) => i.rule !== 'extra');
  const optional = meal.ingredients.filter((i) => i.rule === 'extra');

  // P11.07 — dense ingredient sets collapse into an overflow tray rather than
  // shrinking every label; a toggle reveals the full list.
  const ingredientSection = (list) => {
    const LIMIT = 6;
    const listEl = h('ul', { class: 'ing-list' });
    let expanded = list.length <= LIMIT;
    const renderList = () => listEl.replaceChildren(...(expanded ? list : list.slice(0, LIMIT)).map(ingRow));
    renderList();
    const toggle = list.length <= LIMIT ? null : h('button', {
      type: 'button', class: 'btn-ghost btn-small', 'aria-expanded': String(expanded),
      onclick: (event) => {
        expanded = !expanded;
        event.currentTarget.setAttribute('aria-expanded', String(expanded));
        event.currentTarget.textContent = expanded ? 'Show fewer ingredients' : `Show all ${list.length} ingredients`;
        renderList();
      },
    }, `Show all ${list.length} ingredients`);

    return [h('h3', {}, 'What’s in it'), listEl, toggle];
  };
  const addBtn = h('button', { type: 'button', class: 'btn-large', disabled: !meal.available }, meal.available ? ['Add to order · ', totalEl] : 'Not available right now');
  addBtn.addEventListener('click', () => busy(addBtn, async () => {
    try {
      await addLine({ mealId: meal.id, quantity: qty, removed: [...removed], extras: [...extras], note: note.value.trim() || null });
      close();
      toast(`${qty} × ${meal.name} added`);
    } catch (e) { toast(e.message); }
  }, 'Adding…'));
  updateTotal();

  return h('div', { class: 'sheet', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'sheet-title' },
    h('button', { type: 'button', class: 'close btn-secondary', onclick: close, 'aria-label': 'Close' }, '✕'),
    img(meal.hero ?? meal.image, 'hero-wrap'),
    h('div', { class: 'content' },
      h('h2', { id: 'sheet-title' }, meal.name),
      h('p', { class: 'price', style: 'font-weight:800' }, meal.price),
      meal.description ? h('p', {}, meal.description) : null,
      !meal.available ? h('div', { class: 'notice notice-warn' }, meal.availabilityNote ?? 'Sold out for now') : null,
      included.length ? ingredientSection(included) : null,
      optional.length ? [h('h3', {}, 'Extras'), h('ul', { class: 'ing-list' }, optional.map(ingRow))] : null,
      h('p', { class: 'small muted' }, 'Ingredient information is provided by the kitchen. If you have an allergy, add a note when you send your order so staff can check with the kitchen.'),
      h('div', { class: 'field' }, h('label', { for: 'line-note' }, 'Request for this dish (optional)'), note),
      h('div', { class: 'btn-row', style: 'justify-content:space-between;align-items:center' },
        h('div', { class: 'qty-stepper' },
          h('button', { type: 'button', class: 'btn-secondary', 'aria-label': 'Fewer', onclick: () => { qty = Math.max(1, qty - 1); qtyOut.textContent = qty; updateTotal(); } }, '−'),
          qtyOut,
          h('button', { type: 'button', class: 'btn-secondary', 'aria-label': 'More', onclick: () => { qty = Math.min(20, qty + 1); qtyOut.textContent = qty; updateTotal(); } }, '+')),
        addBtn)));
}

export function showSheet(content, onClose) {
  const backdrop = h('div', { class: 'sheet-backdrop', onclick: (e) => { if (e.target === backdrop) onClose(); } }, content);
  const prev = document.activeElement;
  document.body.append(backdrop);
  document.body.style.overflow = 'hidden';
  const focusables = () => [...backdrop.querySelectorAll('button, textarea, input, select, a[href]')].filter((el) => !el.disabled);
  focusables()[0]?.focus();
  const key = (e) => {
    if (e.key === 'Escape') onClose();
    if (e.key === 'Tab') {
      const f = focusables(); if (!f.length) return;
      if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
      else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
    }
  };
  document.addEventListener('keydown', key);
  return () => { document.removeEventListener('keydown', key); backdrop.remove(); document.body.style.overflow = ''; prev?.focus?.(); };
}

/** Cart lines with steppers; conflicts banner. */
export function cartLines(cart, { base, onChange, editable = true }) {
  const conflictBox = cart.conflicts.length ? h('div', { class: 'notice notice-warn', role: 'alert' },
    h('b', {}, 'Some items changed since you added them:'),
    h('ul', {}, cart.conflicts.map((c) => h('li', {}, c.message))),
    h('button', { type: 'button', onclick: (e) => busy(e.currentTarget, async () => {
      try { onChange(await api('POST', `${base}/cart/accept-changes`)); } catch (err) { toast(err.message); }
    }) }, 'Update my order')) : null;
  const line = (l) => h('li', {},
    h('div', { class: 'line-row' }, h('span', {}, h('b', {}, l.name)), h('span', { class: 'num' }, l.lineTotal)),
    l.removed.length ? h('div', { class: 'mods' }, 'No ' + l.removed.join(', no ')) : null,
    l.extras.length ? h('div', { class: 'mods add' }, l.extras.map((x) => `+ ${x.name} (${x.price})`).join(', ')) : null,
    l.note ? h('div', { class: 'small' }, '“' + l.note + '”') : null,
    editable ? h('div', { class: 'btn-row', style: 'margin-top:.4rem' },
      h('div', { class: 'qty-stepper' },
        h('button', { type: 'button', class: 'btn-secondary', 'aria-label': `One fewer ${l.name}`, onclick: (e) => busy(e.currentTarget, async () => {
          try { onChange(l.quantity <= 1 ? await api('DELETE', `${base}/cart/lines/${l.id}`) : await api('PATCH', `${base}/cart/lines/${l.id}`, { quantity: l.quantity - 1 })); } catch (err) { toast(err.message); }
        }, '…') }, '−'),
        h('output', {}, String(l.quantity)),
        h('button', { type: 'button', class: 'btn-secondary', 'aria-label': `One more ${l.name}`, onclick: (e) => busy(e.currentTarget, async () => {
          try { onChange(await api('PATCH', `${base}/cart/lines/${l.id}`, { quantity: l.quantity + 1 })); } catch (err) { toast(err.message); }
        }, '…') }, '+')),
      h('button', { type: 'button', class: 'btn-ghost btn-small', onclick: (e) => busy(e.currentTarget, async () => {
        try { onChange(await api('DELETE', `${base}/cart/lines/${l.id}`)); } catch (err) { toast(err.message); }
      }) }, 'Remove')) : h('div', { class: 'small muted' }, `Qty ${l.quantity}`));
  return h('div', {}, conflictBox, h('ul', { class: 'line-list' }, cart.lines.map(line)),
    h('div', { class: 'line-row', style: 'font-size:1.2em;margin-top:.5rem' }, h('b', {}, 'Total'), h('b', { class: 'num' }, cart.total)));
}

export function allergyBox(id = 'allergy') {
  const area = h('textarea', { id, maxlength: 500, rows: 2, placeholder: 'e.g. Peanut allergy; no dairy' });
  const box = h('div', { class: 'allergy-box' },
    h('label', { for: id }, h('b', {}, 'Allergies or dietary needs? (optional)')),
    h('p', { class: 'small' }, 'If you add a note, a staff member will check it with the kitchen before your order is prepared. We cannot guarantee any dish is free from allergens.'),
    area);
  return { box, area };
}
