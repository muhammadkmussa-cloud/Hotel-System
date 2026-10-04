// Accessible tabs primitive (P04.05). Binds within a single tablist only.
export function initTabs(tablist) {
  if (!(tablist instanceof HTMLElement)) return;
  const tabs = [...tablist.querySelectorAll('[role="tab"]')];
  if (tabs.length === 0) return;

  const panelFor = (tab) => {
    const id = tab.getAttribute('aria-controls');
    return id ? document.getElementById(id) : null;
  };

  const select = (tab, { focus = true } = {}) => {
    for (const candidate of tabs) {
      const selected = candidate === tab;
      candidate.setAttribute('aria-selected', String(selected));
      candidate.tabIndex = selected ? 0 : -1;
      const panel = panelFor(candidate);
      if (panel) panel.hidden = !selected;
    }
    if (focus) tab.focus();
  };

  const initial = tabs.find((tab) => tab.getAttribute('aria-selected') === 'true') ?? tabs[0];
  select(initial, { focus: false });

  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => select(tab, { focus: false }));
    tab.addEventListener('keydown', (event) => {
      const keys = { ArrowRight: 1, ArrowLeft: -1, Home: 'first', End: 'last' };
      if (!(event.key in keys)) return;
      event.preventDefault();
      const step = keys[event.key];
      const next = step === 'first' ? tabs[0] : step === 'last' ? tabs[tabs.length - 1] : tabs[(index + step + tabs.length) % tabs.length];
      select(next);
    });
  });
}
