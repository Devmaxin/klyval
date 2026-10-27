/* Intro sequencer. Only sets stages; every visual is CSS (intro.css). No business logic here. */
(() => {
  const root = document.documentElement;
  const el = document.querySelector('[data-intro]');
  if (!el || !root.classList.contains('intro-active')) return;

  const small = matchMedia('(max-width: 899px)').matches;
  const T = small ? [900, 900, 800] : [1500, 1400, 1000]; // build, create, launch (ms)
  const stages = ['build', 'create', 'launch'];
  const words = [...el.querySelectorAll('[data-w]')];
  const behind = [...document.querySelectorAll('.skip, .site-header, main, .site-footer')];
  behind.forEach(n => { n.inert = true; });

  let done = false; const timers = [];
  const wait = ms => new Promise(r => timers.push(setTimeout(r, ms)));
  const setStage = i => {
    el.dataset.stage = stages[i];
    words.forEach((w, j) => { w.classList.toggle('is-active', j === i); w.classList.toggle('is-past', j < i); });
  };

  const finish = () => {
    if (done) return; done = true; timers.forEach(clearTimeout);
    try { sessionStorage.setItem('kv-intro', '1'); } catch (e) {}
    el.dataset.stage = 'out';
    dispatchEvent(new CustomEvent('klyval:intro-done')); // hero reveals as the wipe passes
    setTimeout(() => { behind.forEach(n => { n.inert = false; }); root.classList.remove('intro-active'); el.remove(); }, 800);
  };

  setTimeout(finish, 7000); // hard failsafe: the intro can never trap the page
  el.querySelector('[data-intro-skip]').addEventListener('click', finish);
  addEventListener('keydown', e => { if (e.key === 'Escape') finish(); });

  (async () => {
    // Don't start mid font-swap: wait for fonts, but never longer than 800ms.
    await Promise.race([document.fonts ? document.fonts.ready : 0, new Promise(r => setTimeout(r, 800))]);
    if (done) return;
    el.style.setProperty('--total', (T[0] + T[1] + T[2]) + 'ms');
    el.classList.add('is-running');
    for (let i = 0; i < 3 && !done; i++) { setStage(i); await wait(T[i]); }
    // Never reveal an unfinished page: hold on the last frame until the document has loaded (capped at 2s).
    if (!done && document.readyState !== 'complete') {
      await new Promise(r => { addEventListener('load', r, { once: true }); timers.push(setTimeout(r, 2000)); });
    }
    finish();
  })();
})();
