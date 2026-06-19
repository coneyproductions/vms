(function () {
  // Deprecated shim. The canonical bundle is /assets/vms-ticketing-front.js.
  // This file exists only for backward compatibility with older cached enqueues.
  try {
    if (window.__vmsTicketingFrontBundle && window.__vmsTicketingFrontBundle.loaded) return;

    var cur = (document.currentScript && document.currentScript.src) ? String(document.currentScript.src) : '';
    if (!cur) return;

    var canonical = cur.replace(/\/assets\/js\/vms-ticketing-front\.js/i, '/assets/vms-ticketing-front.js');
    if (canonical === cur) {
      canonical = cur.replace('/assets/js/', '/assets/');
    }

    // If we cannot compute the URL, do nothing (revenue-safe: avoid throwing).
    if (!canonical || canonical === cur) return;

    var s = document.createElement('script');
    s.src = canonical;
    s.async = true;
    document.head.appendChild(s);
  } catch (e) {
    // ignore
  }
})();
