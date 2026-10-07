// Staff pages: single-submit forms, error focus, live refresh, change calculator, M-PESA polling.
import { live, formatKsh, setOffline } from './common.js';

document.querySelector('[data-error-focus]')?.focus();

// Warn before losing unsaved edits on forms marked data-dirty-guard (P10.06).
const guardedForms = [...document.querySelectorAll('form[data-dirty-guard]')];
if (guardedForms.length) {
  let dirty = false;
  guardedForms.forEach((form) => {
    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('change', () => { dirty = true; });
    form.addEventListener('submit', () => { dirty = false; });
  });
  window.addEventListener('beforeunload', (event) => {
    if (!dirty) return;
    event.preventDefault();
    event.returnValue = '';
  });
}

// Prevent double submission of any staff form.
document.addEventListener('submit', (event) => {
  const form = event.target;
  if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post') return;
  // Forms using the accessible single-submit module manage their own busy state.
  if (form.hasAttribute('data-single-submit')) return;
  if (form.dataset.submitting === '1') { event.preventDefault(); return; }
  if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) { event.preventDefault(); return; }
  form.dataset.submitting = '1';
  const submitter = event.submitter;
  setTimeout(() => {
    form.querySelectorAll('button').forEach((b) => { b.disabled = true; });
    if (submitter) submitter.setAttribute('aria-busy', 'true');
  }, 0);
});
window.addEventListener('pageshow', () => document.querySelectorAll('form[data-submitting]').forEach((f) => {
  delete f.dataset.submitting; f.querySelectorAll('button').forEach((b) => { b.disabled = false; });
}));

// Live refresh: reload the page when relevant events arrive, unless the user is mid-edit.
const scope = document.body.dataset.live;
if (scope) {
  let pending = false;
  const editing = () => {
    const a = document.activeElement;
    return a && (['INPUT', 'TEXTAREA', 'SELECT'].includes(a.tagName)) && a.closest('form') !== null
      && [...a.closest('form').elements].some((el) => el.value && el.type !== 'hidden' && el.type !== 'submit');
  };
  const reload = () => {
    if (editing() || document.querySelector('details[open].dirty')) { pending = true; return; }
    location.reload();
  };
  document.addEventListener('focusout', () => { if (pending) setTimeout(() => { if (!editing()) location.reload(); }, 400); });
  const query = scope.startsWith('visit:') ? { visit: scope.slice(6) } : {};
  live(() => reload(), { query, interval: 4000 });
}

// Cash change calculator.
document.querySelectorAll('form[data-change-calc]').forEach((form) => {
  const out = form.querySelector('[data-change-output]');
  const parse = (v) => { const m = String(v).replace(/[ ,]/g, '').match(/^(\d+)(?:\.(\d{1,2}))?$/); return m ? Number(m[1]) * 100 + Number((m[2] ?? '0').padEnd(2, '0')) : null; };
  const update = () => {
    const amount = parse(form.elements.amount.value);
    const tendered = parse(form.elements.tendered.value);
    if (amount === null || tendered === null) { out.textContent = ''; return; }
    out.textContent = tendered >= amount ? 'Change to give: ' + formatKsh(tendered - amount) : 'Short by ' + formatKsh(amount - tendered);
  };
  form.addEventListener('input', update);
});

// M-PESA attempt polling on the checkout page.
const poll = document.querySelector('[data-attempt-poll]');
if (poll) {
  const url = poll.dataset.attemptPoll;
  const tick = async () => {
    try {
      const r = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const d = await r.json();
      setOffline(false);
      if (d.attempt) poll.textContent = d.attempt.message;
      if (d.state === 'paid' || (d.attempt && !['pending'].includes(d.attempt.state))) { location.reload(); return; }
    } catch { setOffline(true); }
    setTimeout(tick, 3000);
  };
  setTimeout(tick, 3000);
}
