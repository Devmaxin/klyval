/* Contact form niceties. Server-side validation stays the source of truth; nothing here replaces it. */
(() => {
  const summary = document.querySelector('[data-form-errors]');
  if (summary) summary.focus();
  const sent = document.querySelector("[data-form-sent]"); if (sent) sent.focus();                          // announce server-side errors to keyboard and screen-reader users
  const form = document.querySelector('form.form');
  if (!form) return;
  const btn = form.querySelector('button[type=submit]');
  const msg = form.querySelector('textarea[name=message]');
  const count = form.querySelector('[data-count]');
  if (msg && count) { const upd = () => { count.textContent = msg.value.length + ' / 5000'; }; msg.addEventListener('input', upd); upd(); }
  form.addEventListener('submit', () => {                // one click, one submission
    if (!btn || btn.disabled) return;
    setTimeout(() => { btn.disabled = true; btn.querySelector('.btn__label').textContent = 'Sending…'; }, 0);
  });
  addEventListener('pageshow', e => { if (e.persisted && btn) { btn.disabled = false; btn.querySelector('.btn__label').textContent = 'Start a project'; } });
})();
