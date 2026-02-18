(function () {
  'use strict';

  var cfg = window.vmsMaGuidanceUi || {};
  var wrappers = [
    document.getElementById('vms-ma-ads-builder-wrap'),
    document.getElementById('vms-ma-settings-wrap')
  ].filter(Boolean);

  if (!wrappers.length) {
    return;
  }

  function getLevelFromWrap(wrap) {
    var level = String(wrap.getAttribute('data-guidance-level') || cfg.guidanceLevel || 'beginner').toLowerCase();
    if (level !== 'beginner' && level !== 'standard' && level !== 'expert') {
      return 'beginner';
    }
    return level;
  }

  function getHelpToggle(wrap) {
    return wrap.id === 'vms-ma-settings-wrap'
      ? document.getElementById('vms-ma-settings-help-mode-toggle')
      : document.getElementById('vms-ma-help-mode-toggle');
  }

  function getGuidanceSelect(wrap) {
    return wrap.id === 'vms-ma-settings-wrap'
      ? document.getElementById('vms-ma-settings-guidance-level')
      : document.getElementById('vms-ma-guidance-level');
  }

  function setHelpClass(wrap, enabled) {
    if (enabled) {
      wrap.classList.add('vms-ma-help-enabled');
    } else {
      wrap.classList.remove('vms-ma-help-enabled');
    }
    wrap.setAttribute('data-help-mode', enabled ? '1' : '0');
  }

  function getVisitedStore() {
    try {
      var raw = sessionStorage.getItem('vmsMaVisited');
      if (!raw) {
        return {};
      }
      var parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (e) {
      return {};
    }
  }

  function setVisitedStep(key) {
    try {
      var state = getVisitedStore();
      state[key] = true;
      sessionStorage.setItem('vmsMaVisited', JSON.stringify(state));
    } catch (e) {
      // no-op
    }
  }

  function isVisitedStep(key) {
    var state = getVisitedStore();
    return !!state[key];
  }

  function emitToast(message, type) {
    document.dispatchEvent(new CustomEvent('vms-ma-toast', {
      detail: {
        message: String(message || ''),
        type: type || 'warning'
      }
    }));
  }

  function getStepProgress(step) {
    var required = Array.prototype.slice.call(step.querySelectorAll('[data-required-step]'));
    if (!required.length) {
      return { required: 0, filled: 0, ready: true };
    }

    var filled = 0;
    var radioNamesSeen = {};

    required.forEach(function (field) {
      var value = '';
      if (field.type === 'radio') {
        var groupName = String(field.name || '');
        if (groupName !== '' && radioNamesSeen[groupName]) {
          return;
        }
        if (groupName !== '') {
          radioNamesSeen[groupName] = true;
          value = step.querySelector('input[type="radio"][name="' + groupName + '"]:checked') ? '1' : '';
        }
      } else if (field.type === 'checkbox') {
        value = field.checked ? '1' : '';
      } else {
        value = String(field.value || '').trim();
      }
      if (value !== '') {
        filled++;
      }
    });

    var requiredCount = Object.keys(radioNamesSeen).length;
    if (requiredCount === 0) {
      requiredCount = required.length;
    } else {
      requiredCount += required.filter(function (field) {
        return field.type !== 'radio';
      }).length;
    }

    return {
      required: requiredCount,
      filled: filled,
      ready: requiredCount === 0 || filled >= requiredCount
    };
  }

  function isStepReady(step) {
    return getStepProgress(step).ready;
  }

  function getStepKey(step) {
    return String(step.getAttribute('data-step-key') || '').toUpperCase();
  }

  function setStepExpansion(step, expanded) {
    step.classList.toggle('is-expanded', !!expanded);
    step.classList.toggle('is-collapsed', !expanded);
    var header = step.querySelector('.vms-ma-step-header');
    if (header) {
      header.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }
  }

  function applyAdvancedVisibility(wrap, level) {
    var showAdvanced = level === 'expert';
    var anyAdvancedOpen = false;
    wrap.querySelectorAll('[data-advanced="1"]').forEach(function (el) {
      var step = el.closest('.vms-ma-step');
      var allowInStep = !!(step && step.classList.contains('vms-ma-advanced-open'));
      if (allowInStep) {
        anyAdvancedOpen = true;
      }
      if (showAdvanced || allowInStep) {
        el.classList.remove('vms-ma-advanced-hidden');
      } else {
        el.classList.add('vms-ma-advanced-hidden');
      }
    });

    var customWrap = wrap.querySelector('#vms-ma-custom-weight-wrap');
    if (customWrap && !(showAdvanced || anyAdvancedOpen)) {
      customWrap.classList.add('vms-ma-advanced-hidden');
    }
  }

  function statusForStep(step, lockDown) {
    var key = getStepKey(step);
    if (lockDown && key !== 'A') {
      return 'Locked';
    }

    var progress = getStepProgress(step);
    var visited = isVisitedStep(key);

    if (visited && progress.ready) {
      return 'Complete';
    }
    if (!visited && progress.ready) {
      return 'Ready';
    }
    return 'Not started';
  }

  function updateStepStatuses(wrap) {
    var steps = Array.prototype.slice.call(wrap.querySelectorAll('.vms-ma-step'));
    var stepA = wrap.querySelector('#vms-ma-step-a');
    var lockDown = stepA ? !isStepReady(stepA) : false;

    steps.forEach(function (step) {
      var status = statusForStep(step, lockDown);
      var statusNode = step.querySelector('[data-step-status]');
      if (statusNode) {
        statusNode.textContent = status;
      }
      step.setAttribute('data-step-status', status.toLowerCase().replace(/\s+/g, '-'));
    });
  }

  function collapseOtherSteps(wrap, exceptStep) {
    wrap.querySelectorAll('.vms-ma-step').forEach(function (step) {
      if (step === exceptStep) {
        setStepExpansion(step, true);
      } else {
        setStepExpansion(step, false);
      }
    });
  }

  function toggleStepFromHeader(wrap, step) {
    var level = getLevelFromWrap(wrap);
    var stepKey = getStepKey(step);
    var stepA = wrap.querySelector('#vms-ma-step-a');
    var stepALocked = stepA ? !isStepReady(stepA) : false;

    if (level === 'beginner') {
      if (stepALocked && stepKey !== 'A') {
        emitToast('Finish Step A first.', 'warning');
        return;
      }
      collapseOtherSteps(wrap, step);
      setVisitedStep(stepKey);
      updateStepStatuses(wrap);
      return;
    }

    var isExpanded = step.classList.contains('is-expanded');
    setStepExpansion(step, !isExpanded);
    setVisitedStep(stepKey);
    updateStepStatuses(wrap);
  }

  function stepKeyMap(wrap) {
    if (!wrap || wrap.id !== 'vms-ma-ads-builder-wrap') {
      return null;
    }
    return {
      A: wrap.querySelector('#vms-ma-step-a'),
      B: wrap.querySelector('#vms-ma-step-b'),
      C: wrap.querySelector('#vms-ma-step-c'),
      D: wrap.querySelector('#vms-ma-step-d'),
      E: wrap.querySelector('#vms-ma-step-e'),
      F: wrap.querySelector('#vms-ma-step-f'),
      G: wrap.querySelector('#vms-ma-step-g')
    };
  }

  function installStepperApi(wrap) {
    var map = stepKeyMap(wrap);
    if (!map) {
      return;
    }

    window.vmsMaStepper = {
      expand: function (stepKey) {
        var key = String(stepKey || '').toUpperCase();
        if (!map[key]) {
          return false;
        }
        setStepExpansion(map[key], true);
        setVisitedStep(key);
        updateStepStatuses(wrap);
        return true;
      },
      collapse: function (stepKey) {
        var key = String(stepKey || '').toUpperCase();
        if (!map[key]) {
          return false;
        }
        setStepExpansion(map[key], false);
        updateStepStatuses(wrap);
        return true;
      },
      showAdvanced: function (stepKey) {
        var key = String(stepKey || '').toUpperCase();
        if (!map[key]) {
          return false;
        }
        map[key].classList.add('vms-ma-advanced-open');
        applyAdvancedVisibility(wrap, getLevelFromWrap(wrap));
        return true;
      },
      scrollTo: function (selector) {
        var target = selector ? document.querySelector(selector) : null;
        if (!target || typeof target.scrollIntoView !== 'function') {
          return false;
        }
        target.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
        return true;
      },
      isExpanded: function (stepKey) {
        var key = String(stepKey || '').toUpperCase();
        return !!(map[key] && map[key].classList.contains('is-expanded'));
      },
      isAdvancedShown: function (stepKey) {
        var key = String(stepKey || '').toUpperCase();
        return !!(map[key] && map[key].classList.contains('vms-ma-advanced-open'));
      }
    };
  }

  function applyGuidanceMode(wrap, level) {
    wrap.setAttribute('data-guidance-level', level);
    wrap.classList.remove('vms-ma-mode-beginner', 'vms-ma-mode-standard', 'vms-ma-mode-expert');
    wrap.classList.add('vms-ma-mode-' + level);

    var steps = Array.prototype.slice.call(wrap.querySelectorAll('.vms-ma-step'));
    if (!steps.length) {
      return;
    }

    if (level === 'beginner') {
      var showAll = wrap.getAttribute('data-show-all-steps') === '1';
      steps.forEach(function (step, idx) {
        setStepExpansion(step, showAll ? true : idx === 0);
      });
    }

    applyAdvancedVisibility(wrap, level);
    updateStepStatuses(wrap);
  }

  function persist(wrap, payload) {
    var cfgRoot = window.vmsMaTour || {};
    if (!cfgRoot.restUrl || !cfgRoot.nonce) {
      return Promise.resolve(false);
    }
    return fetch(cfgRoot.restUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfgRoot.nonce
      },
      body: JSON.stringify(payload || {})
    }).then(function (res) {
      if (!res.ok) {
        return null;
      }
      return res.json();
    }).catch(function () {
      return null;
    });
  }

  function bind(wrap) {
    var level = getLevelFromWrap(wrap);
    var helpToggle = getHelpToggle(wrap);
    var guidanceSelect = getGuidanceSelect(wrap);
    var hasSteps = !!wrap.querySelector('.vms-ma-step');

    if (guidanceSelect) {
      guidanceSelect.value = level;
      guidanceSelect.addEventListener('change', function () {
        var next = String(guidanceSelect.value || '').toLowerCase();
        if (next !== 'beginner' && next !== 'standard' && next !== 'expert') {
          next = 'beginner';
        }
        applyGuidanceMode(wrap, next);
        persist(wrap, { guidance_level: next }).then(function (json) {
          var response = json || {};
          var effectiveLevel = String(response.guidance_level || next);
          applyGuidanceMode(wrap, effectiveLevel);
          if (window.vmsMaGuidance) {
            window.vmsMaGuidance.guidanceLevel = effectiveLevel;
          }
          document.dispatchEvent(new CustomEvent('vms-ma-guidance-updated', { detail: { level: effectiveLevel } }));
        });
      });
    }

    if (helpToggle) {
      setHelpClass(wrap, !!helpToggle.checked);
      helpToggle.addEventListener('change', function () {
        var enabled = !!helpToggle.checked;
        setHelpClass(wrap, enabled);
        persist(wrap, { help_mode: enabled ? 1 : 0 });
      });
    }

    wrap.addEventListener('click', function (evt) {
      var advancedToggle = evt.target.closest('.vms-ma-advanced-toggle');
      if (advancedToggle) {
        evt.preventDefault();
        var step = advancedToggle.closest('.vms-ma-step');
        if (!step) {
          return;
        }
        step.classList.toggle('vms-ma-advanced-open');
        setVisitedStep(getStepKey(step));
        applyAdvancedVisibility(wrap, getLevelFromWrap(wrap));
        updateStepStatuses(wrap);
        return;
      }

      var header = evt.target.closest('.vms-ma-step-header');
      if (header && hasSteps) {
        toggleStepFromHeader(wrap, header.closest('.vms-ma-step'));
        return;
      }

      var continueBtn = evt.target.closest('.vms-ma-step-continue');
      if (!continueBtn || !hasSteps) {
        return;
      }
      evt.preventDefault();
      var current = continueBtn.closest('.vms-ma-step');
      if (!current) {
        return;
      }

      setVisitedStep(getStepKey(current));
      if (!isStepReady(current)) {
        current.classList.add('vms-ma-step-needs-attention');
        updateStepStatuses(wrap);
        return;
      }
      current.classList.remove('vms-ma-step-needs-attention');
      updateStepStatuses(wrap);

      if (!wrap.classList.contains('vms-ma-mode-beginner') || wrap.getAttribute('data-show-all-steps') === '1') {
        return;
      }

      var steps = Array.prototype.slice.call(wrap.querySelectorAll('.vms-ma-step'));
      var idx = steps.indexOf(current);
      if (idx < 0 || idx >= steps.length - 1) {
        return;
      }
      var next = steps[idx + 1];
      collapseOtherSteps(wrap, next);
      setVisitedStep(getStepKey(next));
      if (typeof next.scrollIntoView === 'function') {
        next.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
      }
      updateStepStatuses(wrap);
    });

    if (hasSteps) {
      wrap.addEventListener('keydown', function (evt) {
        var header = evt.target.closest('.vms-ma-step-header');
        if (!header) {
          return;
        }
        if (evt.key === 'Enter' || evt.key === ' ') {
          evt.preventDefault();
          toggleStepFromHeader(wrap, header.closest('.vms-ma-step'));
        }
      });

      wrap.addEventListener('input', function () {
        updateStepStatuses(wrap);
      });
      wrap.addEventListener('change', function () {
        updateStepStatuses(wrap);
        applyAdvancedVisibility(wrap, getLevelFromWrap(wrap));
      });
    }

    var intro = wrap.querySelector('.vms-ma-topbar-main');
    if (hasSteps && intro && !intro.querySelector('.vms-ma-show-all')) {
      var showAll = document.createElement('button');
      showAll.type = 'button';
      showAll.className = 'button-link vms-ma-show-all';
      showAll.textContent = 'Show all steps';
      showAll.addEventListener('click', function () {
        var current = wrap.getAttribute('data-show-all-steps') === '1';
        wrap.setAttribute('data-show-all-steps', current ? '0' : '1');
        showAll.textContent = current ? 'Show all steps' : 'Use step-by-step';
        if (!wrap.classList.contains('vms-ma-mode-beginner')) {
          wrap.querySelectorAll('.vms-ma-step').forEach(function (step) {
            setStepExpansion(step, !current);
          });
        }
        applyGuidanceMode(wrap, getLevelFromWrap(wrap));
      });
      intro.appendChild(showAll);
    }

    applyGuidanceMode(wrap, level);
    installStepperApi(wrap);
  }

  wrappers.forEach(bind);
})();
