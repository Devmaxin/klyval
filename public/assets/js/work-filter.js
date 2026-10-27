/* Work page filters. The links are real (/work?kind=…) and work without JavaScript; this only swaps the grid in place. */
(() => {
  const nav = document.querySelector('[data-filters]');
  const grid = document.querySelector('[data-work-grid]');
  if (!nav || !grid) return;
  const cards = [...grid.querySelectorAll('[data-kind]')];
  const status = document.querySelector('[data-filter-status]');
  const links = [...nav.querySelectorAll('a')];

  const apply = kind => {
    let n = 0;
    cards.forEach(c => { const on = !kind || c.dataset.kind === kind; c.hidden = !on; if (on) n++; });
    links.forEach(a => { const on = (a.dataset.filter || '') === kind; if (on) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current'); });
    if (status) status.textContent = n + (n === 1 ? ' project' : ' projects') + (kind ? ' shown' : '');
  };
  nav.addEventListener('click', e => {
    const a = e.target.closest('a');
    if (!a || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
    e.preventDefault();
    const kind = a.dataset.filter || '';
    apply(kind);
    try { history.replaceState(null, '', kind ? '/work?kind=' + encodeURIComponent(kind) : '/work'); } catch (_) {}
  });
  apply((links.find(a => a.hasAttribute('aria-current')) || {dataset: {}}).dataset.filter || '');
})();
