/* Process: marks the step nearest the viewport centre and advances the progress bar. */
(() => {
  const sec = document.querySelector('[data-process]');
  if (!sec || !('IntersectionObserver' in window)) return;
  const steps = [...sec.querySelectorAll('[data-step]')];
  const io = new IntersectionObserver(es => es.forEach(en => {
    if (!en.isIntersecting) return;
    steps.forEach(s => s.classList.toggle('is-active', s === en.target));
    sec.style.setProperty('--p', (steps.indexOf(en.target) + 1) / steps.length);
  }), { rootMargin: '-45% 0px -45% 0px' });
  steps.forEach(s => io.observe(s));
  steps[0].classList.add('is-active');
})();
