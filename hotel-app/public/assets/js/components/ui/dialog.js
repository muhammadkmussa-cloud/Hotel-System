// Accessible dialog/drawer primitives (P04.04). No framework, no network.
const FOCUSABLE_SELECTOR = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

function focusableElements(layer) {
  return [...layer.querySelectorAll(FOCUSABLE_SELECTOR)].filter(
    (el) => el.offsetParent !== null && el.getAttribute('aria-hidden') !== 'true',
  );
}

const activeLayers = new WeakMap();

export function openLayer(layer, { returnFocusTo } = {}) {
  if (!(layer instanceof HTMLElement)) return;
  if (activeLayers.has(layer)) return;
  const opener = returnFocusTo instanceof HTMLElement ? returnFocusTo : document.activeElement;

  if (!layer.hasAttribute('tabindex')) layer.setAttribute('tabindex', '-1');
  layer.hidden = false;
  if (!layer.hasAttribute('role')) layer.setAttribute('role', 'dialog');
  layer.setAttribute('aria-modal', 'true');
  layer.dataset.open = 'true';

  const onKeydown = (event) => {
    if (!activeLayers.has(layer)) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      closeLayer(layer);
      return;
    }
    if (event.key !== 'Tab') return;
    const focusables = focusableElements(layer);
    if (focusables.length === 0) {
      event.preventDefault();
      layer.focus();
      return;
    }
    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    if (!layer.contains(document.activeElement)) {
      event.preventDefault();
      (event.shiftKey ? last : first).focus();
    } else if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  };

  const onFocusIn = (event) => {
    if (!activeLayers.has(layer)) return;
    if (layer.contains(event.target)) return;
    const focusables = focusableElements(layer);
    (focusables[0] ?? layer).focus();
  };

  activeLayers.set(layer, { opener, onKeydown, onFocusIn });
  document.addEventListener('keydown', onKeydown, true);
  document.addEventListener('focusin', onFocusIn, true);

  (focusableElements(layer)[0] ?? layer).focus();
}

export function closeLayer(layer, { returnFocusTo } = {}) {
  if (!(layer instanceof HTMLElement)) return;
  const state = activeLayers.get(layer);
  if (!state) return;
  activeLayers.delete(layer);

  document.removeEventListener('keydown', state.onKeydown, true);
  document.removeEventListener('focusin', state.onFocusIn, true);
  layer.hidden = true;
  delete layer.dataset.open;
  layer.removeAttribute('aria-modal');

  const target = returnFocusTo instanceof HTMLElement ? returnFocusTo : state.opener;
  if (target instanceof HTMLElement) target.focus();
}

export function openDialog(dialog, options) {
  openLayer(dialog, options);
}

export function closeDialog(dialog, options) {
  closeLayer(dialog, options);
}
