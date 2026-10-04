// Prevents duplicate submissions from double clicks/taps and reports a busy state.
export function initSingleSubmit(form) {
  if (!(form instanceof HTMLFormElement)) return;

  const status = form.querySelector('[data-submit-status]');
  const pendingLabel = () => form.querySelector('[type="submit"]')?.dataset.pendingLabel ?? 'Submitting…';

  form.addEventListener('submit', (event) => {
    if (form.dataset.submitting === 'true') {
      event.preventDefault();
      return;
    }
    form.dataset.submitting = 'true';
    form.setAttribute('aria-busy', 'true');

    const button = form.querySelector('[type="submit"]');
    const label = pendingLabel();
    if (button instanceof HTMLButtonElement) {
      button.disabled = true;
      if (button.dataset.pendingLabel) button.textContent = label;
    } else if (button instanceof HTMLInputElement) {
      button.disabled = true;
      if (button.dataset.pendingLabel) button.value = label;
    }
    if (status instanceof HTMLElement) status.textContent = label;
  });

  // A bfcache restore would otherwise keep the form stuck in the busy state.
  window.addEventListener('pageshow', (event) => {
    if (event.persisted) window.location.reload();
  });
}
