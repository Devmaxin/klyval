/* Reveal primitive. Waits for the intro on the home page; otherwise starts immediately. */
(() => {
  const els = document.querySelectorAll('[data-reveal]');
  if (!els.length) return;
  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
  let started = false;
  const start = () => {
    if (started) return; started = true;
    if (reduced || !('IntersectionObserver' in window)) { els.forEach(e => e.classList.add('is-in')); return; }
    const io = new IntersectionObserver(en => en.forEach(x => { if (x.isIntersecting) { x.target.classList.add('is-in'); io.unobserve(x.target); } }), { threshold: 0.2 });
    requestAnimationFrame(() => els.forEach(e => io.observe(e)));
  };
  if (document.documentElement.classList.contains('intro-active')) {
    addEventListener('klyval:intro-done', start, { once: true });
    setTimeout(start, 9500);
  } else start();
})();
