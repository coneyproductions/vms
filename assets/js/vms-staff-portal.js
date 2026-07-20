(function () {
  var SAVE_ACTION = ['vms', 'staff', 'save', 'manual', 'availability', 'day'].join('_');
  var BEFORE_LEAVE_EVENT = ['before', 'unload'].join('');
  var FORM_SELECTOR = '.vms-staff-av-form[data-vms-staff-availability="1"]';

  function getRoot() {
    return document.getElementById('vms-portal-root');
  }

  function getForm(root) {
    return root ? root.querySelector(FORM_SELECTOR) : null;
  }

  function readConfig(form) {
    return {
      ajaxUrl: form ? form.getAttribute('data-vms-staff-availability-ajax-url') || '' : '',
      nonce: form ? form.getAttribute('data-vms-staff-availability-nonce') || '' : '',
    };
  }

  function setStatus(statusEl, message) {
    if (!statusEl) return;
    statusEl.textContent = message || '';
    statusEl.classList.toggle('is-error', /failed/i.test(message || ''));
  }

  function labelFor(state) {
    if (state === 'available') return 'Available';
    if (state === 'unavailable') return 'Unavailable';
    return 'Unset';
  }

  function visualFor(state) {
    if (state === 'available') return 'available';
    if (state === 'unavailable') return 'unavailable';
    return '';
  }

  function badgeStateClass(state) {
    if (state === 'available') return 'is-available';
    if (state === 'unavailable') return 'is-unavailable';
    return 'is-unset';
  }

  function cycle(state) {
    if (state === '') return 'available';
    if (state === 'available') return 'unavailable';
    return '';
  }

  function post(config, params) {
    return window.fetch(config.ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: new URLSearchParams(params).toString(),
      credentials: 'same-origin',
    }).then(function (response) {
      return response.json();
    });
  }

  function updateMonthCounts(month) {
    var counts;
    var active;
    var working;
    var conflicts;
    var available;
    var unavailable;

    if (!month) return;

    counts = month.querySelector('.vms-av-counts');
    if (!counts) return;

    active = parseInt(counts.getAttribute('data-active') || '0', 10) || 0;
    working = month.querySelectorAll('.vms-av-badge-status.is-working').length;
    conflicts = month.querySelectorAll('.vms-av-badge-status.is-conflict').length;
    available = month.querySelectorAll('.vms-av-badge-status.is-available').length;
    unavailable = month.querySelectorAll('.vms-av-badge-status.is-unavailable').length + conflicts;
    counts.textContent = active + ' active | ' + unavailable + ' U | ' + available + ' A | ' + working + ' W';
  }

  function applyLocalState(button, next) {
    var cell;
    var hidden;
    var badge;
    var source;

    button.setAttribute('data-state', next);
    button.setAttribute('data-visual', visualFor(next));

    cell = button.closest('td');
    if (cell) {
      hidden = cell.querySelector('.vms-av-hidden[data-date="' + button.getAttribute('data-date') + '"]');
      if (hidden) hidden.value = next;

      badge = cell.querySelector('.vms-av-badge-status');
      if (badge) {
        badge.className = 'vms-av-badge-status ' + badgeStateClass(next);
        badge.textContent = labelFor(next);
      }

      source = cell.querySelector('.vms-av-src');
      if (source) source.remove();
    }

    updateMonthCounts(button.closest('.vms-av-month'));
  }

  function initStaffAvailability(root) {
    var form = getForm(root);
    var config = readConfig(form);
    var pending = 0;
    var failed = 0;
    var dirtyDates = new Set();
    var statusEl;

    if (!form || root.dataset.staffAvailabilityBound === '1') return;

    root.dataset.staffAvailabilityBound = '1';
    statusEl = root.querySelector('.vms-av-autosave');

    function saveDay(date, state, button, previous) {
      if (!config.ajaxUrl || !config.nonce) {
        failed += 1;
        setStatus(statusEl, 'Save failed. Please reload and try again.');
        applyLocalState(button, previous);
        return;
      }

      pending += 1;
      dirtyDates.add(date);
      failed = 0;
      setStatus(statusEl, 'Saving\u2026');
      button.classList.remove('vms-av-save-failed');

      post(config, {
        action: SAVE_ACTION,
        nonce: config.nonce,
        date: date,
        state: state,
      })
        .then(function (json) {
          pending -= 1;
          if (!json || !json.success) {
            failed += 1;
            button.classList.add('vms-av-save-failed');
            applyLocalState(button, previous);
            setStatus(statusEl, 'Save failed. Tap again or use Fallback Save below.');
            return;
          }

          dirtyDates.delete(date);
          updateMonthCounts(button.closest('.vms-av-month'));
          if (pending === 0 && failed === 0) setStatus(statusEl, 'Saved');
          if (pending === 0 && failed > 0) setStatus(statusEl, 'Some changes failed to save.');
        })
        .catch(function () {
          pending -= 1;
          failed += 1;
          button.classList.add('vms-av-save-failed');
          applyLocalState(button, previous);
          setStatus(statusEl, 'Save failed. Check connection and try again.');
        });
    }

    window.addEventListener(BEFORE_LEAVE_EVENT, function (event) {
      if (pending > 0 || failed > 0 || dirtyDates.size > 0) {
        event.preventDefault();
        event.returnValue = '';
        return '';
      }
      return undefined;
    });

    Array.prototype.slice.call(root.querySelectorAll('.vms-staff-av-btn')).forEach(function (button) {
      button.addEventListener('click', function () {
        var current = button.getAttribute('data-state') || '';
        var next = cycle(current);
        var date = button.getAttribute('data-date') || '';
        applyLocalState(button, next);
        saveDay(date, next, button, current);
      });
    });
  }

  var root = getRoot();
  if (!root) return;

  initStaffAvailability(root);
})();
