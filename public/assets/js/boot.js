/* Runs in <head>, before first paint. Decides whether the intro plays (home, once per session, no reduced motion). */
(function (d) {
  d.classList.add('js');
  try {
    var reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (location.pathname === '/' && !reduced && !sessionStorage.getItem('kv-intro')) {
      d.classList.add('intro-active');
      setTimeout(function () { d.classList.remove('intro-active'); }, 9000); // failsafe: never trap the visitor
    }
    // Arriving via an in-site transition: start covered so there is no flash of the new page.
    if (!reduced && !d.classList.contains('intro-active') && sessionStorage.getItem('kv-pt')) {
      sessionStorage.removeItem('kv-pt'); d.classList.add('pt-in');
      setTimeout(function () { d.classList.remove('pt-in'); }, 2500);
    }
  } catch (e) {}
})(document.documentElement);
