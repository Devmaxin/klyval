/* Selected work. Vertical scroll drives a horizontal track — but only when the real content is wide enough
   to justify it. Distance is measured from the DOM, never from a project count. No wheel/touch hijacking:
   this is a sticky container plus a transform, so native scrolling, momentum and keyboard all keep working. */
(() => {
  const sec = document.querySelector('[data-work]');
  if (!sec) return;
  const pin = sec.querySelector('.work__pin');
  const track = sec.querySelector('[data-work-track]');
  const mq = matchMedia('(min-width: 900px) and (prefers-reduced-motion: no-preference)');
  const RATIO = 0.8;      // vertical px spent per horizontal px (<1 keeps the pin short)
  const EDGE = 0.04;      // hold at both ends so panels settle before the section releases
  const MIN_TRAVEL = 0.35; // below this (fraction of viewport width) a pin isn't worth it
  let max = 0, dist = 0, pinned = false, raf = 0;

  const vw = () => document.documentElement.clientWidth;
  const clamp01 = n => Math.min(1, Math.max(0, n));

  const update = () => {
    raf = 0; if (!pinned) return;
    const p = clamp01(-sec.getBoundingClientRect().top / (dist || 1));
    const pm = clamp01((p - EDGE) / (1 - 2 * EDGE));
    track.style.transform = `translate3d(${-pm * max}px,0,0)`;
    sec.style.setProperty('--wp', pm);
  };
  const unpin = () => {
    pinned = false; sec.classList.remove('is-pinned');
    sec.style.removeProperty('--work-h'); sec.style.removeProperty('--wp'); track.style.transform = '';
  };
  const layout = () => {
    if (!mq.matches) return unpin();
    sec.classList.add('is-pinned');                       // measure in the pinned layout…
    sec.style.removeProperty('--work-h'); track.style.transform = '';
    max = Math.max(0, track.scrollWidth - vw());
    if (max < vw() * MIN_TRAVEL) return unpin();          // …then decide: 1–2 projects fall back to a plain layout
    pinned = true; dist = max * RATIO;
    sec.style.setProperty('--work-h', (pin.offsetHeight + dist) + 'px');
    update();
  };
  const schedule = () => { if (!raf) raf = requestAnimationFrame(() => { raf = 0; layout(); }); };

  addEventListener('scroll', () => { if (pinned && !raf) raf = requestAnimationFrame(update); }, { passive: true });
  addEventListener('resize', schedule);
  addEventListener('load', schedule);
  mq.addEventListener('change', schedule);
  if ('ResizeObserver' in window) { const ro = new ResizeObserver(schedule); sec.querySelectorAll('.proj').forEach(p => ro.observe(p)); }
  layout();

  // Keyboard: tabbing to an off-screen project scrolls the page to the position that brings it into view.
  sec.addEventListener('focusin', e => {
    const proj = pinned && e.target.closest('.proj');
    if (!proj) return;
    const r = proj.getBoundingClientRect(), pad = 16;
    if (r.left >= pad && r.right <= vw() - pad) return;
    const margin = parseFloat(getComputedStyle(track).paddingLeft) || 0;
    const t = Math.min(max, Math.max(0, proj.offsetLeft - margin));
    const p = EDGE + (t / max) * (1 - 2 * EDGE);
    const top = scrollY + sec.getBoundingClientRect().top + p * dist;
    scrollTo({ top, behavior: 'instant' });
  });

  // Cursor-reactive preview: the visual drifts a few px toward the pointer. Pointer events only; never touches scrolling.
  if (matchMedia('(hover: hover) and (pointer: fine) and (prefers-reduced-motion: no-preference)').matches) {
    sec.querySelectorAll('.proj').forEach(p => {
      p.addEventListener('pointermove', e => {
        const r = p.getBoundingClientRect();
        p.style.setProperty('--px', ((e.clientX - r.left) / r.width - .5) * 2);
        p.style.setProperty('--py', ((e.clientY - r.top) / r.height - .5) * 2);
      });
      p.addEventListener('pointerleave', () => { p.style.setProperty('--px', 0); p.style.setProperty('--py', 0); });
    });
  }
})();
