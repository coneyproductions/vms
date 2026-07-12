(function () {
  var docEl = document.documentElement;
  var METHOD_STORAGE_KEY = ['vms', 'av', 'open', 'method'].join('_');
  var MONTH_COOKIE_KEY = ['vms', 'av', 'open', 'ym'].join('_');

  function getRoot() {
    return document.getElementById('vms-portal-root');
  }

  function getAvailabilityRoot(root) {
    return root ? root.querySelector('#vms-av') : null;
  }

  function readLocalStorage(key) {
    try {
      return window.localStorage ? window.localStorage.getItem(key) || '' : '';
    } catch (err) {
      return '';
    }
  }

  function writeLocalStorage(key, value) {
    try {
      if (window.localStorage) window.localStorage.setItem(key, value);
    } catch (err) {}
  }

  function readCookie(name) {
    var parts;
    try {
      parts = document.cookie.split(';').map(function (cookiePart) {
        return cookiePart.trim();
      });
    } catch (err) {
      return '';
    }
    for (var i = 0; i < parts.length; i++) {
      if (parts[i].indexOf(name + '=') === 0) return decodeURIComponent(parts[i].slice(name.length + 1));
    }
    return '';
  }

  function writeCookie(name, value, days) {
    var maxAge = (days || 30) * 24 * 60 * 60;
    try {
      document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; max-age=' + maxAge + '; samesite=lax';
    } catch (err) {}
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

  function bindAvailabilityMethodState(root) {
    var availabilityRoot = getAvailabilityRoot(root);
    var methods;
    var target;
    if (!availabilityRoot || availabilityRoot.dataset.vmsPortalMethodBound === '1') return;
    availabilityRoot.dataset.vmsPortalMethodBound = '1';
    methods = availabilityRoot.querySelectorAll('details.vms-av-method[data-method]');
    if (!methods.length) return;

    function closeOthers(except) {
      methods.forEach(function (details) {
        if (details !== except) details.removeAttribute('open');
      });
    }

    methods.forEach(function (details) {
      details.addEventListener('toggle', function () {
        var key;
        if (!details.open) return;
        closeOthers(details);
        key = details.getAttribute('data-method') || '';
        if (key) writeLocalStorage(METHOD_STORAGE_KEY, key);
      });
    });

    target = readLocalStorage(METHOD_STORAGE_KEY);
    target = target ? availabilityRoot.querySelector('details.vms-av-method[data-method="' + target + '"]') : null;
    if (!target) target = availabilityRoot.querySelector('details.vms-av-method[data-method="manual"]');
    if (!target) return;
    target.setAttribute('open', 'open');
    closeOthers(target);
  }

  function bindAvailabilityMonthState(root) {
    var availabilityRoot = getAvailabilityRoot(root);
    var todayYm;
    var monthEls;
    var byYm = new Map();
    var preferredYm;
    var openYm;
    var currentOpenYm = '';
    if (!availabilityRoot || availabilityRoot.dataset.vmsPortalMonthBound === '1') return;
    availabilityRoot.dataset.vmsPortalMonthBound = '1';
    todayYm = availabilityRoot.getAttribute('data-today-ym') || '';
    monthEls = Array.prototype.slice.call(availabilityRoot.querySelectorAll('.vms-av-month[data-ym]'));
    if (!monthEls.length) return;

    monthEls.forEach(function (month) {
      var ym = month.getAttribute('data-ym') || '';
      var details = month.querySelector('details');
      var summary = details ? details.querySelector('summary') : null;
      if (ym && details && summary) byYm.set(ym, { details: details, summary: summary });
    });

    function firstYm() {
      var first = byYm.keys().next();
      return first.done ? '' : first.value;
    }

    function openOnly(ym) {
      if (!ym || !byYm.has(ym)) return;
      currentOpenYm = ym;
      byYm.forEach(function (obj, key) {
        obj.details.open = key === ym;
      });
      writeCookie(MONTH_COOKIE_KEY, ym, 30);
    }

    preferredYm = readCookie(MONTH_COOKIE_KEY);
    openYm = preferredYm && byYm.has(preferredYm) ? preferredYm : todayYm && byYm.has(todayYm) ? todayYm : firstYm();
    openOnly(openYm);

    byYm.forEach(function (obj, ym) {
      obj.summary.addEventListener('click', function (event) {
        event.preventDefault();
        if (currentOpenYm === ym) return;
        openOnly(ym);
        try {
          obj.summary.scrollIntoView({ block: 'start', behavior: 'smooth' });
        } catch (err) {}
      });
    });
  }

  function init() {
    var root = getRoot();
    if (!root) return;
    stripOpportunityTabs(root);
    bindSubmitOnChange(root);
    bindAllVendorsAccordion(root);
    bindAvailabilityMethodState(root);
    bindAvailabilityMonthState(root);
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
