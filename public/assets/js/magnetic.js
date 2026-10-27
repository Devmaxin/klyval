/* Magnetic pull for [data-magnetic] wrappers. Fine pointers only, never under reduced motion. */
(() => {
  if (!matchMedia('(hover: hover) and (pointer: fine) and (prefers-reduced-motion: no-preference)').matches) return;
  document.querySelectorAll('[data-magnetic]').forEach(el => {
    el.addEventListener('pointermove', e => {
      const r = el.getBoundingClientRect();
      el.style.transform = `translate(${(e.clientX - r.left - r.width / 2) * .25}px,${(e.clientY - r.top - r.height / 2) * .25}px)`;
    });
    el.addEventListener('pointerleave', () => { el.style.transform = ''; });
  });
})();
