(function () {
  var docEl = document.documentElement;

  function getRoot() {
    return document.getElementById('vms-portal-root');
  }

  function syncNarrowClass() {
    var root = getRoot();
    var width = 9999;
    if (!root) return;
    try {
      width = Math.min(
        window.innerWidth || 9999,
        (document.documentElement && document.documentElement.clientWidth) || 9999,
        (window.screen && window.screen.width) || 9999
      );
    } catch (err) {}
    if (width <= 760) root.classList.add('vms-portal--narrow');
    else root.classList.remove('vms-portal--narrow');
  }

  function stripOpportunityTabs(root) {
    var nav = root ? root.querySelector('.vms-portal-nav') : null;
    var links;
    if (!nav) return;
    links = nav.querySelectorAll('a');
    for (var i = links.length - 1; i >= 0; i--) {
      var link = links[i];
      var href = link.getAttribute('href') || '';
      var text = (link.textContent || '').trim().toLowerCase();
      var isOpportunity = false;
      try {
        isOpportunity = new URL(href, window.location.origin).searchParams.get('tab') === 'opportunities';
      } catch (err) {
        isOpportunity = href.indexOf('tab=opportunities') !== -1;
      }
      if ((isOpportunity || text === 'opportunities' || text === 'open dates') && link.parentNode) {
        link.parentNode.removeChild(link);
      }
    }
  }

  function bindSubmitOnChange(root) {
    if (!root || root.dataset.vmsPortalSubmitBound === '1') return;
    root.dataset.vmsPortalSubmitBound = '1';
    root.addEventListener('change', function (event) {
      var target = event.target;
      if (!target || typeof target.matches !== 'function') return;
      if (!target.matches('select[data-vms-portal-submit-on-change="1"]')) return;
      if (target.disabled || !target.form || typeof target.form.submit !== 'function') return;
      target.form.submit();
    });
  }

  function bindAllVendorsAccordion(root) {
    var wrap = root ? root.querySelector('.vms-av-allvendors-wrap') : null;
    var all;
    if (!wrap || wrap.dataset.vmsPortalAccordionBound === '1') return;
    wrap.dataset.vmsPortalAccordionBound = '1';
    all = wrap.querySelectorAll('details.vms-sch-month');
    if (!all.length) return;
    all.forEach(function (details) {
      details.addEventListener('toggle', function () {
        if (!details.open) return;
        all.forEach(function (other) {
          if (other !== details) other.removeAttribute('open');
        });
      });
    });
  }

  function init() {
    var root = getRoot();
    if (!root) return;
    stripOpportunityTabs(root);
    bindSubmitOnChange(root);
    bindAllVendorsAccordion(root);
    syncNarrowClass();
    if (docEl.dataset.vmsVendorPortalShellWindowBound === '1') return;
    docEl.dataset.vmsVendorPortalShellWindowBound = '1';
    window.addEventListener('resize', syncNarrowClass);
    window.addEventListener('orientationchange', syncNarrowClass);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
