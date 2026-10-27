/* Admin behaviour. External file so the admin CSP never needs inline scripts. */
document.addEventListener('submit', (e) => {
  const msg = e.target.closest('form')?.dataset.confirm;
  if (msg && !window.confirm(msg)) e.preventDefault();
});
