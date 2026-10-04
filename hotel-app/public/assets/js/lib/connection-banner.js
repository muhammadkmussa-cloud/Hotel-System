// Connection banner (P04.07). Distinguish browser offline, app unreachable,
// and an external provider failure. States are carried on data-state.
const DEFAULT_MESSAGES = {
  offline: 'You appear to be offline. Your draft stays on this device until you reconnect.',
  unreachable: 'The hotel system cannot be reached. Check the connection and try again.',
  provider: 'A connected payment or fiscal service is unavailable. The hotel system itself is reachable.',
};

const NOOP = { show() {}, hide() {}, reportFailure() {}, reportProviderFailure() {}, destroy() {} };

export function initConnectionBanner({ banner, fetchProbe, messages } = {}) {
  if (!(banner instanceof HTMLElement)) return NOOP;
  const text = { ...DEFAULT_MESSAGES };
  for (const [key, value] of Object.entries(messages ?? {})) {
    if (typeof value === 'string' && value.trim() !== '') text[key] = value;
  }
  let sequence = 0;

  const show = (kind) => {
    banner.dataset.state = kind;
    banner.textContent = text[kind] ?? text.unreachable;
  };
  const hide = () => {
    delete banner.dataset.state;
    banner.textContent = '';
  };

  const classify = (error) => {
    if (error && error.kind === 'provider') return 'provider';
    return navigator.onLine ? 'unreachable' : 'offline';
  };

  const runProbe = async () => {
    if (!fetchProbe) return;
    const current = ++sequence;
    try {
      await fetchProbe();
      if (current === sequence && navigator.onLine) hide();
    } catch (error) {
      if (error && error.name === 'AbortError') return;
      if (current !== sequence) return;
      show(classify(error));
    }
  };

  const onOffline = () => { sequence += 1; show('offline'); };
  const onOnline = () => { if (!fetchProbe) hide(); else runProbe(); };
  window.addEventListener('offline', onOffline);
  window.addEventListener('online', onOnline);

  if (!navigator.onLine) show('offline');
  else runProbe();

  return {
    show,
    hide,
    reportFailure: () => show(navigator.onLine ? 'unreachable' : 'offline'),
    reportProviderFailure: () => show('provider'),
    destroy: () => {
      sequence += 1;
      window.removeEventListener('offline', onOffline);
      window.removeEventListener('online', onOnline);
    },
  };
}
