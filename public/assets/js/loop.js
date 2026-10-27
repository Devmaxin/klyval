/* Seamless looping tracks: the homepage project columns (vertical, opposite directions) and the team project strips (horizontal).
   Markup contract: [data-loop="x|y"] (the window) > [data-track] (the list) > items. Optional: data-speed (px/s), data-reverse.
   Only the original items are real links; every clone is aria-hidden, inert and out of the tab order.
   Not active under reduced motion, below 700px for the vertical wall, or when a horizontal strip already fits (then it simply sits still).
   Movement is a CSS transform animation (compositor only); this file only measures, clones and sets the duration. */
(() => {
  const els = [...document.querySelectorAll('[data-loop]')];
  if (!els.length) return;
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  const wide = matchMedia('(min-width: 700px)');

  const setup = el => {
    const track = el.querySelector('[data-track]');
    if (!track) return;
    const y = el.dataset.loop === 'y';
    const dim = n => n.getBoundingClientRect()[y ? 'height' : 'width'];

    // Always start from the original markup.
    track.querySelectorAll('[data-clone]').forEach(n => n.remove());
    el.classList.remove('is-live'); el.style.removeProperty('--dur'); track.style.animationDelay = '';
    const items = [...track.children];
    if (reduced.matches || (y && !wide.matches) || !items.length) return;

    const set = items.reduce((t, n) => t + dim(n), 0);
    const view = y ? el.clientHeight : el.clientWidth;
    if (set < 1 || view < 1) return;
    if (!y && set <= view) return;                       // strip fits: nothing to move

    const reps = Math.max(1, Math.ceil(view / set));     // each half must cover the window, or a gap would show
    const copy = () => items.map(n => {
      const c = n.cloneNode(true);
      c.setAttribute('data-clone', ''); c.setAttribute('aria-hidden', 'true'); c.inert = true;
      c.querySelectorAll('a,button,[tabindex]').forEach(a => a.setAttribute('tabindex', '-1'));
      return c;
    });
    const frag = document.createDocumentFragment();
    for (let i = 0; i < reps - 1; i++) copy().forEach(c => frag.append(c));   // rest of the first half
    for (let i = 0; i < reps; i++) copy().forEach(c => frag.append(c));       // second half, identical to the first
    track.append(frag);

    const speed = parseFloat(el.dataset.speed) || (y ? 26 : 20);
    el.style.setProperty('--dur', (set * reps / speed).toFixed(1) + 's');
    el.classList.add('is-live');
  };

  // Keyboard: if a real link outside the window gets focus, move the loop to it instead of letting the browser scroll the clipped box.
  const reveal = (el, e) => {
    if (!el.classList.contains('is-live')) return;
    const item = e.target.closest && e.target.closest('[data-track] > *');
    if (!item || item.hasAttribute('data-clone')) return;
    const track = el.querySelector('[data-track]'), y = el.dataset.loop === 'y';
    const half = (y ? track.scrollHeight : track.scrollWidth) / 2;
    const pos = y ? item.offsetTop : item.offsetLeft;
    const dur = parseFloat(getComputedStyle(el).getPropertyValue('--dur')) || 60;
    const rev = el.hasAttribute('data-reverse');
    let p = Math.min(0.999, Math.max(0, (pos - 8) / half));
    if (rev) p = 1 - p;
    track.style.animationDelay = (-p * dur).toFixed(2) + 's';
    el.scrollTop = 0; el.scrollLeft = 0;
  };

  const io = 'IntersectionObserver' in window
    ? new IntersectionObserver(es => es.forEach(x => x.target.classList.toggle('is-off', !x.isIntersecting)), { rootMargin: '80px' })
    : null;

  let timer = 0;
  const all = () => els.forEach(setup);
  const later = () => { clearTimeout(timer); timer = setTimeout(all, 150); };
  els.forEach(el => { el.addEventListener('focusin', e => reveal(el, e)); if (io) io.observe(el); });
  all();
  addEventListener('load', all);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(all);
  if ('ResizeObserver' in window) {
    let w = innerWidth;
    new ResizeObserver(() => { if (innerWidth !== w) { w = innerWidth; later(); } }).observe(document.documentElement);
  } else addEventListener('resize', later);
  reduced.addEventListener('change', all);
  wide.addEventListener('change', all);
})();
