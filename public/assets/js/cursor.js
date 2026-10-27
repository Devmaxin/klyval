/* Desktop only: small dot; becomes a label (VIEW / PROFILE / OPEN ↗) over [data-cursor]. */
(() => {
  if (!matchMedia('(hover: hover) and (pointer: fine)').matches) return;
  const dot = document.createElement('div');
  dot.className = 'cursor'; dot.setAttribute('aria-hidden', 'true');
  const label = document.createElement('span'); dot.append(label); document.body.append(dot);
  let x = 0, y = 0, raf = 0;
  addEventListener('mousemove', e => { x = e.clientX; y = e.clientY;
    if (!raf) raf = requestAnimationFrame(() => { dot.style.transform = `translate(${x}px,${y}px)`; raf = 0; }); }, { passive: true });
  document.addEventListener('mouseover', e => {
    const t = e.target.closest('[data-cursor]');
    label.textContent = t ? t.dataset.cursor : ''; dot.classList.toggle('has-label', !!t);
  });
})();
