(function () {
  var docEl = document.documentElement;
  var METHOD_STORAGE_KEY = ['vms', 'av', 'open', 'method'].join('_');
  var MONTH_COOKIE_KEY = ['vms', 'av', 'open', 'ym'].join('_');
  var SAVE_ACTION = ['vms', 'save', 'vendor', 'availability', 'edit'].join('_');
  var BEFORE_LEAVE_EVENT = ['before', 'unload'].join('');
  var CONFIG_SELECTOR = 'script[type="application/json"][data-vms-portal-config="availability"]';
  var REQUEST_KEYS = {
    token: ['non', 'ce'].join(''),
    previewId: 'vms_preview_vendor',
    previewToken: 'vms_preview_' + ['non', 'ce'].join(''),
  };

  function getRoot() {
    return document.getElementById('vms-portal-root');
  }

  function getAvailabilityRoot(root) {
    return root ? root.querySelector('#vms-av') : null;
  }

  function readAvailabilityConfig(availabilityRoot) {
    var node = availabilityRoot ? availabilityRoot.querySelector(CONFIG_SELECTOR) : null;
    var payload;
    var previewId = 0;
    var previewToken = '';
    if (!node) return null;
    try {
      payload = JSON.parse(node.textContent || '{}');
    } catch (err) {
      return null;
    }
    if (!payload || typeof payload !== 'object' || Array.isArray(payload)) return null;
    if (typeof payload.previewId === 'number' || typeof payload.previewId === 'string') {
      previewId = parseInt(payload.previewId, 10) || 0;
    }
    if (typeof payload.previewToken === 'string') previewToken = payload.previewToken;
    if (typeof payload.ajax !== 'string' || typeof payload.token !== 'string') return null;
    if (payload.ajax === '' || payload.token === '') return null;
    return {
      endpoint: payload.ajax,
      token: payload.token,
      vendorId: Number(payload.vendorId) || 0,
      previewId: previewId > 0 ? previewId : 0,
      previewToken: previewToken,
    };
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

  function ariaForAvailability(state, source) {
    if (source === 'booked') return 'Booked';
    if (source === 'tentative') return 'Tentative';
    if (state === 'available') return 'Available';
    if (state === 'unavailable') return 'Unavailable';
    return 'Unset';
  }

  function iconForSource(source) {
    if (source === 'ics') return '📅';
    if (source === 'pattern') return '🗓️';
    if (source === 'tentative') return '⏳';
    if (source === 'booked') return '🎟️';
    return '';
  }

  function syncAvailabilityButton(button) {
    var date = button.getAttribute('data-date') || '';
    var manual = button.getAttribute('data-state') || '';
    var base = button.getAttribute('data-base') || '';
    var baseSource = button.getAttribute('data-base-src') || '';
    var visual = manual || base || '';
    var source = manual ? 'manual' : baseSource || '';
    var iconEl = button.querySelector('.vms-av-src');
    var cell = button.closest('td');
    var statusBadge = cell ? cell.querySelector('.vms-av-badge-status') : null;
    var badgeState;
    button.setAttribute('data-visual', visual);
    button.setAttribute('data-src', source);
    if (!iconEl) {
      iconEl = document.createElement('span');
      iconEl.className = 'vms-av-src';
      iconEl.setAttribute('aria-hidden', 'true');
      button.appendChild(iconEl);
    }
    if (source && source !== 'manual') {
      iconEl.textContent = iconForSource(source);
      iconEl.style.display = '';
      iconEl.setAttribute('title', source);
    } else {
      iconEl.textContent = '';
      iconEl.style.display = 'none';
      iconEl.removeAttribute('title');
    }
    if (statusBadge) {
      badgeState = visual === 'available' || visual === 'unavailable' ? visual : 'unset';
      statusBadge.classList.remove('is-available', 'is-unavailable', 'is-unset');
      statusBadge.classList.add('is-' + badgeState);
      statusBadge.textContent = badgeState === 'available' ? 'Available' : badgeState === 'unavailable' ? 'Unavailable' : 'Unset';
    }
    button.setAttribute('aria-label', date + ': ' + ariaForAvailability(visual, source) + '. Tap to cycle.');
  }

  function bindAvailabilityAutosave(root) {
    var availabilityRoot = getAvailabilityRoot(root);
    var buttons;
    var config;
    var statusEl;
    var pending = 0;
    var edits = new Map();
    var form = availabilityRoot && availabilityRoot.querySelector('#vms-av-form');
    var fallback = form && form.querySelector('[name="vms_availability_edits"]');
    if (!availabilityRoot || availabilityRoot.dataset.vmsPortalAutosaveBound === '1') return;
    buttons = availabilityRoot.querySelectorAll('.vms-av-btn');
    if (!buttons.length || !fallback) return;
    docEl.classList.add('vms-js');
    buttons.forEach(syncAvailabilityButton);
    config = readAvailabilityConfig(availabilityRoot);
    if (!config) return;
    availabilityRoot.dataset.vmsPortalAutosaveBound = '1';
    statusEl = availabilityRoot.querySelector('.vms-av-autosave');

    function setStatus(text) {
      if (statusEl && statusEl.textContent !== text) statusEl.textContent = text;
    }

    function refreshMonthCounts(button) {
      var month = button.closest('.vms-av-month');
      var counts;
      var active;
      var available;
      var unavailable;
      if (!month) return;
      counts = month.querySelector('.vms-av-counts');
      if (!counts) return;
      active = counts.getAttribute('data-active') || '';
      available = month.querySelectorAll('.vms-av-btn[data-state="available"]').length;
      unavailable = month.querySelectorAll('.vms-av-btn[data-state="unavailable"]').length;
      if (active) {
        counts.textContent = active + ' active | ' + unavailable + ' NA | ' + available + ' A';
      } else {
        counts.textContent = unavailable + ' NA | ' + available + ' A';
      }
    }

    function requestJson(params) {
      var sendRequest = window.fetch;
      if (typeof sendRequest !== 'function') return Promise.reject(new Error('request unavailable'));
      return sendRequest(config.endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body: new URLSearchParams(params).toString(),
        credentials: 'same-origin',
      }).then(function (response) {
        return response.json();
      });
    }

    function updateFallback() {
      var values = {};
      edits.forEach(function (edit, date) {
        values[date] = {state: edit.state, previous: edit.previous};
      });
      fallback.value = JSON.stringify(values);
    }

    function updateStatus() {
      var failed = Array.from(edits.values()).find(function (edit) { return edit.error; });
      if (failed) {
        setStatus(failed.error + 'Use Save Availability to retry.');
      } else {
        setStatus(pending > 0 ? 'Saving…' : 'Saved');
      }
    }

    function saveDay(date, edit) {
      var state = edit.state;
      var payload = {
        action: SAVE_ACTION,
        vendor_id: config.vendorId,
        date: date,
        state: state,
        previous: edit.previous,
      };
      edit.saving = true;
      edit.error = '';
      pending += 1;
      updateStatus();
      payload[REQUEST_KEYS.token] = config.token;
      if (config.previewId > 0 && config.previewToken) {
        payload[REQUEST_KEYS.previewId] = config.previewId;
        payload[REQUEST_KEYS.previewToken] = config.previewToken;
      }
      requestJson(payload)
        .then(function (json) {
          if (!json || !json.success) {
            throw new Error(json && json.data && json.data.message ? json.data.message : '');
          }
          // The next write must compare against the last state the server accepted.
          edit.previous = state;
          if (edit.state === state) edits.delete(date);
        })
        .catch(function (error) {
          edit.error = error.message ? error.message + ' ' : 'Availability could not be saved. ';
        })
        .finally(function () {
          pending -= 1;
          edit.saving = false;
          updateFallback();
          if (!edit.error && edits.has(date)) {
            saveDay(date, edit);
          } else {
            updateStatus();
          }
        });
    }

    form.addEventListener('submit', function (event) {
      if (pending > 0) {
        event.preventDefault();
        setStatus('Wait for the current save, then try again.');
      } else {
        edits.clear();
      }
    });

    var retainedEdits = JSON.parse(fallback.value || '{}');
    buttons.forEach(function (button) {
      var date = button.getAttribute('data-date');
      var retained = retainedEdits[date];
      if (retained) {
        edits.set(date, {state: retained.state, previous: retained.previous, saving: false, error: 'Availability could not be saved. '});
        button.setAttribute('data-state', retained.state);
        syncAvailabilityButton(button);
        refreshMonthCounts(button);
      }
    });
    if (edits.size) updateStatus();

    buttons.forEach(function (button) {
      button.addEventListener('click', function () {
        var current = button.getAttribute('data-state') || '';
        var next = current === '' ? 'available' : current === 'available' ? 'unavailable' : '';
        var date = button.getAttribute('data-date') || '';
        var edit = edits.get(date);
        if (!edit) {
          edit = {state: next, previous: current, saving: false, error: ''};
          edits.set(date, edit);
        }
        edit.state = next;
        button.setAttribute('data-state', next);
        syncAvailabilityButton(button);
        refreshMonthCounts(button);
        updateFallback();
        if (!edit.saving) saveDay(date, edit);
      });
    });

    if (docEl.dataset.vmsPortalAutosaveBeforeLeaveBound === '1') return;
    docEl.dataset.vmsPortalAutosaveBeforeLeaveBound = '1';
    window.addEventListener(BEFORE_LEAVE_EVENT, function (event) {
      if (pending > 0 || edits.size > 0) {
        event.preventDefault();
        event.returnValue = '';
        return '';
      }
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
    bindAvailabilityAutosave(root);
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
