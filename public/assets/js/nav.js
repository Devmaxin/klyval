(() => {
  const header = document.querySelector('[data-nav]');
  if (!header) return;
  const nav = header.querySelector('.nav'), btn = header.querySelector('.nav-toggle');
  let ticking = false;
  const onScroll = () => { if (ticking) return; ticking = true;
    requestAnimationFrame(() => { header.classList.toggle('is-compact', scrollY > 40); ticking = false; }); };
  addEventListener('scroll', onScroll, { passive: true }); onScroll();

  const set = (open) => { nav.classList.toggle('is-open', open); btn.setAttribute('aria-expanded', open);
    btn.firstElementChild.textContent = open ? 'Close' : 'Menu'; document.body.classList.toggle('nav-open', open); };
  btn.addEventListener('click', () => set(!nav.classList.contains('is-open')));
  addEventListener('keydown', e => { if (e.key === 'Escape') set(false); });
  nav.addEventListener('click', e => { if (e.target.closest('a')) set(false); });
})();
