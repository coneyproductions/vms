(function () {
  function initStaff(root) {
    var scope = root && root.querySelector ? root : document;
    var wraps = [];

    if (typeof Element !== 'undefined' && scope instanceof Element && scope.matches('[data-vms-staff-wrap="1"]')) {
      wraps.push(scope);
    }
    wraps.push.apply(wraps, Array.from(scope.querySelectorAll ? scope.querySelectorAll('[data-vms-staff-wrap="1"]') : []));

    wraps.forEach(function (wrap) {
      if (!wrap || wrap.dataset.vmsStaffInit === '1') return;
      wrap.dataset.vmsStaffInit = '1';

      var currentHeadcount = Math.max(0, parseInt(wrap.getAttribute('data-vms-current-headcount') || '0', 10) || 0);
      var headcountWired = wrap.getAttribute('data-vms-headcount-wired') === '1';

      function intValue(el) {
        if (!el) return 0;
        var raw = String(el.value || '').trim();
        if (raw === '') return 0;
        var parsed = parseInt(raw, 10);
        return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
      }

      function checkedCount(card) {
        return card.querySelectorAll('[data-vms-role-assignment-input="1"]:checked').length;
      }

      function roleState(card) {
        var headcountInput = card.querySelector('[data-vms-role-headcount-input="1"]');
        var thresholdInput = card.querySelector('[data-vms-role-threshold-input="1"]');
        var timeModeInput = card.querySelector('[data-vms-role-time-mode-input="1"]');
        var shiftStartInput = card.querySelector('[data-vms-role-shift-start-input="1"]');
        var shiftEndInput = card.querySelector('[data-vms-role-shift-end-input="1"]');
        var durationInput = card.querySelector('[data-vms-role-duration-input="1"]');

        var need = intValue(headcountInput);
        var threshold = Math.max(0, intValue(thresholdInput));
        var filled = checkedCount(card);
        var open = Math.max(0, need - filled);
        var roleInUse = need > 0 || filled > 0;
        var timeMode = String((timeModeInput && timeModeInput.value) || 'absolute').toLowerCase();
        var shiftStart = String((shiftStartInput && shiftStartInput.value) || '').trim();
        var shiftEnd = String((shiftEndInput && shiftEndInput.value) || '').trim();
        var duration = Math.max(0, intValue(durationInput));
        var absoluteTimeMissing = roleInUse && timeMode === 'absolute' && (shiftStart === '' || (shiftEnd === '' && duration <= 0));
        var thresholdMet = headcountWired && currentHeadcount >= threshold;
        var requiredNow = need > 0 && thresholdMet;
        var missingStaffNow = requiredNow && filled < need;
        var roleName = String(card.getAttribute('data-role-name') || 'Role');
        var isCritical = card.getAttribute('data-role-critical') === '1';

        return {
          roleName: roleName,
          need: need,
          threshold: threshold,
          filled: filled,
          open: open,
          roleInUse: roleInUse,
          timeMode: timeMode,
          absoluteTimeMissing: absoluteTimeMissing,
          thresholdMet: thresholdMet,
          requiredNow: requiredNow,
          missingStaffNow: missingStaffNow,
          isCritical: isCritical,
          duration: duration
        };
      }

      function statePill(state) {
        if (!state.roleInUse) {
          return { text: 'Not set', variant: 'is-inactive' };
        }
        if (!headcountWired) {
          return { text: 'Attendance pending', variant: 'is-unwired' };
        }
        if (state.requiredNow) {
          return { text: 'Required now', variant: 'is-required' };
        }
        if (state.threshold <= 0) {
          return { text: 'Ready at 0', variant: 'is-active' };
        }
        return { text: 'Active at ' + state.threshold + '+ attendance', variant: 'is-waiting' };
      }

      function thresholdCopy(state) {
        if (!state.roleInUse) {
          return 'Set staff needed and the attendance trigger for when this role should become required.';
        }
        if (!headcountWired) {
          return 'Attendance trigger is not wired yet. This role will activate at attendance ' + state.threshold + ' once current counts are available.';
        }
        if (state.requiredNow) {
          return 'Current wired attendance is ' + currentHeadcount + '. This role is required now because it activates at attendance ' + state.threshold + '.';
        }
        if (state.threshold <= 0) {
          return 'Current wired attendance is ' + currentHeadcount + '. This role is active immediately once attendance is wired.';
        }
        return 'Current wired attendance is ' + currentHeadcount + '. This role activates at attendance ' + state.threshold + '.';
      }

      function toggleNodes(nodes, show) {
        nodes.forEach(function (node) {
          node.classList.toggle('vms-hidden', !show);
          node.querySelectorAll('input, select, textarea').forEach(function (field) {
            field.disabled = !show;
          });
        });
      }

      function syncTimingVisibility(card, state) {
        var absoluteFields = Array.from(card.querySelectorAll('[data-vms-role-absolute-field="1"]'));
        var relativeFields = Array.from(card.querySelectorAll('[data-vms-role-relative-field="1"]'));
        var endFields = Array.from(card.querySelectorAll('[data-vms-role-end-field="1"]'));
        var showAbsolute = state.timeMode === 'absolute';
        var showRelative = !showAbsolute;

        toggleNodes(absoluteFields, showAbsolute);
        toggleNodes(relativeFields, showRelative);
        if (state.duration > 0) {
          toggleNodes(endFields.filter(function (node) {
            return showAbsolute ? node.hasAttribute('data-vms-role-absolute-field') : node.hasAttribute('data-vms-role-relative-field');
          }), false);
        }
      }

      function renderCard(card) {
        var state = roleState(card);
        var summary = card.querySelector('[data-vms-role-base-summary]');
        var pill = card.querySelector('[data-vms-role-state-pill]');
        var thresholdSummary = card.querySelector('[data-vms-role-threshold-copy]');
        var absoluteWarning = card.querySelector('[data-vms-role-absolute-warning]');
        var requiredWarning = card.querySelector('[data-vms-role-required-warning]');
        var pillState = statePill(state);

        syncTimingVisibility(card, state);

        card.classList.toggle('is-required-now', state.requiredNow);
        card.classList.toggle('has-inline-warning', state.absoluteTimeMissing || state.missingStaffNow);
        card.classList.toggle('has-required-gap', state.missingStaffNow);
        card.classList.toggle('is-waiting-threshold', state.roleInUse && !state.requiredNow && headcountWired && state.threshold > 0);

        if (summary) {
          summary.textContent = 'Need ' + state.need + ' · Filled ' + state.filled + ' · Open ' + state.open + (state.isCritical ? ' · Critical' : '');
        }

        if (pill) {
          pill.textContent = pillState.text;
          pill.classList.remove(
            'vms-ep-staff-role__state--is-inactive',
            'vms-ep-staff-role__state--is-unwired',
            'vms-ep-staff-role__state--is-required',
            'vms-ep-staff-role__state--is-active',
            'vms-ep-staff-role__state--is-waiting'
          );
          pill.classList.add('vms-ep-staff-role__state--' + pillState.variant);
        }

        if (thresholdSummary) {
          thresholdSummary.textContent = thresholdCopy(state);
        }

        if (absoluteWarning) {
          absoluteWarning.classList.toggle('vms-hidden', !state.absoluteTimeMissing);
          absoluteWarning.textContent = 'Absolute time mode requires Shift start plus Shift end or Duration when this role is in use.';
        }

        if (requiredWarning) {
          requiredWarning.classList.toggle('vms-hidden', !state.missingStaffNow);
          requiredWarning.textContent = 'Current wired attendance ' + currentHeadcount + ' has reached this role\'s activation threshold of ' + state.threshold + '. Assign staff until Filled reaches Staff needed.';
        }
      }

      var cards = Array.from(wrap.querySelectorAll('[data-vms-staff-role="1"]'));
      cards.forEach(function (card) {
        card.querySelectorAll('input, select').forEach(function (field) {
          field.addEventListener('input', function () {
            renderCard(card);
          });
          field.addEventListener('change', function () {
            renderCard(card);
          });
        });
        renderCard(card);
      });
    });
  }

  window.BVMGR_EVENT_PLAN_INIT_STAFF = initStaff;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initStaff(document);
    }, { once: true });
  } else {
    initStaff(document);
  }
})();
