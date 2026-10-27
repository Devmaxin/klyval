/* Page transitions. Same-origin, in-site links only. Skipped for reduced motion, modified clicks, new tabs, downloads, files,
   hash links and the admin. Every path has a failsafe so the overlay can never trap the visitor. */
(() => {
  const root = document.documentElement;
  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Arriving after a transition: boot.js already covered the page; wipe it away on the next frames.
  if (root.classList.contains('pt-in')) {
    requestAnimationFrame(() => requestAnimationFrame(() => {
      root.classList.remove('pt-in'); root.classList.add('pt-reveal');
      setTimeout(() => root.classList.remove('pt-reveal'), 650);
    }));
  }
  // Back/forward cache restores the old page with its classes: clear them.
  addEventListener('pageshow', e => { if (e.persisted) root.classList.remove('pt-out', 'pt-in', 'pt-reveal'); });
  if (reduced) return;

  document.addEventListener('click', e => {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    const a = e.target.closest && e.target.closest('a[href]');
    if (!a || (a.target && a.target !== '_self') || a.hasAttribute('download') || a.dataset.noTransition !== undefined) return;
    let u; try { u = new URL(a.href, location.href); } catch (_) { return; }
    if (u.origin !== location.origin || !/^https?:$/.test(u.protocol)) return;
    if (u.pathname === '/admin' || u.pathname.startsWith('/admin/')) return;
    if (u.pathname === location.pathname && u.search === location.search) return;      // same page or hash-only
    if (/\.[a-z0-9]{2,5}$/i.test(u.pathname) && !/\.html?$/i.test(u.pathname)) return;  // sitemap.xml, images, etc.
    e.preventDefault();
    try { sessionStorage.setItem('kv-pt', '1'); } catch (_) {}
    root.classList.add('pt-out');
    setTimeout(() => { location.href = u.href; }, 340);
    setTimeout(() => root.classList.remove('pt-out'), 4000);                          // navigation never happened: lift the cover
  });
})();
