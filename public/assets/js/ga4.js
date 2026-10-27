/* GA4 bootstrap kept external so the CSP never needs 'unsafe-inline'. The ID comes from a validated data attribute. */
(() => {
  const s = document.querySelector('script[data-id]'); if (!s) return;
  window.dataLayer = window.dataLayer || [];
  function gtag() { dataLayer.push(arguments); }
  gtag('js', new Date()); gtag('config', s.dataset.id, { anonymize_ip: true });
})();
