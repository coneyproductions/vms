(function () {
  'use strict';

  var payload = window.VMS_TOURS_PAYLOAD || null;
  if (!payload) {
    return;
  }

  var settings = payload.settings || {};
  var user = payload.user || {};
  var tours = Array.isArray(payload.tours) ? payload.tours : [];
  var debugEnabled = !!(settings.debug_log_enabled || payload.debug);

  var toursById = {};
  var inlineHelpByAnchor = {};
  var activeDriver = null;
  var activeCleanup = null;

  tours.forEach(function (tour) {
    if (tour && tour.id) {
      toursById[String(tour.id)] = tour;
    }
  });

  function normalizeToken(value) {
    var token = String(value || '').toLowerCase().trim();
    if (!token) {
      return '';
    }
    return token.replace(/[^a-z0-9._\-]/g, '');
  }

  function log(message, data) {
    if (!debugEnabled || !window.console || typeof window.console.log !== 'function') {
      return;
    }
    if (typeof data === 'undefined') {
      window.console.log('[VMS Tours] ' + String(message || ''));
      return;
    }
    window.console.log('[VMS Tours] ' + String(message || ''), data);
  }

  function getDriverFactory() {
    if (window.driver && typeof window.driver.js === 'function') {
      return window.driver.js;
    }
    if (window.driver && window.driver.js && typeof window.driver.js.driver === 'function') {
      return window.driver.js.driver;
    }
    if (window.driver && typeof window.driver.driver === 'function') {
      return window.driver.driver;
    }
    return null;
  }

  function sanitizePlacement(value) {
    var placement = String(value || 'auto').toLowerCase();
    if (placement === 'top' || placement === 'right' || placement === 'bottom' || placement === 'left') {
      return placement;
    }
    return 'right';
  }

  function resolveRegisteredTour(tourId) {
    var raw = String(tourId || '').trim();
    if (!raw) {
      return null;
    }

    if (Object.prototype.hasOwnProperty.call(toursById, raw)) {
      return toursById[raw];
    }

    var normalized = normalizeToken(raw);
    if (!normalized) {
      return null;
    }
    if (Object.prototype.hasOwnProperty.call(toursById, normalized)) {
      return toursById[normalized];
    }

    var altUnderscore = normalized.replace(/-/g, '_');
    if (Object.prototype.hasOwnProperty.call(toursById, altUnderscore)) {
      return toursById[altUnderscore];
    }

    var altDash = normalized.replace(/_/g, '-');
    if (Object.prototype.hasOwnProperty.call(toursById, altDash)) {
      return toursById[altDash];
    }

    var neutral = normalized.replace(/[-_]/g, '');
    var keys = Object.keys(toursById);
    for (var i = 0; i < keys.length; i += 1) {
      var key = String(keys[i] || '');
      if (key.replace(/[-_]/g, '') === neutral) {
        return toursById[key];
      }
    }

    return null;
  }

  function extractAnchorFromSelector(selector) {
    var raw = String(selector || '').trim();
    if (!raw) {
      return '';
    }

    var match = raw.match(/^\[data-vms-tour=(["']?)([a-z0-9._\-]+)\1\]$/i);
    if (!match || !match[2]) {
      return '';
    }

    return normalizeToken(match[2]);
  }

  function query(selector) {
    if (!selector || typeof selector !== 'string') {
      return null;
    }
    try {
      return document.querySelector(selector);
    } catch (err) {
      log('Invalid selector: ' + selector);
      return null;
    }
  }

  function elementValue(el, trim) {
    if (!el) {
      return '';
    }

    var value = '';
    if (el.type === 'checkbox') {
      value = el.checked ? '1' : '0';
    } else if (el.type === 'radio') {
      var scope = el.closest('form') || document;
      var checked = scope.querySelector('input[type="radio"][name="' + (el.name || '') + '"]:checked');
      value = checked ? String(checked.value || '') : '';
    } else if (typeof el.value !== 'undefined') {
      value = String(el.value || '');
    } else {
      value = String(el.textContent || '');
    }

    return trim ? value.trim() : value;
  }

  function evaluateGuard(step) {
    var guard = step && step.guard ? step.guard : null;
    if (!guard || !guard.type) {
      return true;
    }

    var type = String(guard.type || '');
    var trim = !!guard.trim;
    var selector = guard.selector || step.selector;
    var el = query(selector);

    if (type === 'element_exists') {
      return !!query(step.selector);
    }

    if (!el) {
      return false;
    }

    if (type === 'field_is_default') {
      var expected = Object.prototype.hasOwnProperty.call(guard, 'default') ? String(guard.default || '') : '';
      var actual = elementValue(el, trim);
      return actual === (trim ? expected.trim() : expected);
    }

    if (type === 'field_is_empty') {
      return elementValue(el, trim) === '';
    }

    if (type === 'checkbox_is_unchecked') {
      return !el.checked;
    }

    return true;
  }

  function buildPreparedSteps(tour) {
    if (!tour || !Array.isArray(tour.steps)) {
      return [];
    }

    var prepared = [];
    tour.steps.forEach(function (step) {
      if (!step || !step.selector) {
        return;
      }

      var el = query(step.selector);
      if (!el) {
        log('Skipped step (missing selector)', { tour: tour.id, selector: step.selector });
        return;
      }

      if (!evaluateGuard(step)) {
        log('Skipped step (guard failed)', { tour: tour.id, step: step.id || step.selector });
        return;
      }

      prepared.push({
        step: step,
        selector: step.selector
      });
    });

    return prepared;
  }

  function runAction(action) {
    if (!action || !action.type) {
      return;
    }

    var type = String(action.type);
    var target = action.selector ? query(action.selector) : null;

    if (type === 'open_accordion') {
      var root = target;
      if (!root) {
        return;
      }
      if (root.tagName && root.tagName.toLowerCase() === 'details') {
        root.open = true;
      }
      if (action.item_selector) {
        var item = query(action.item_selector);
        if (item) {
          if (item.tagName && item.tagName.toLowerCase() === 'details') {
            item.open = true;
          }
          if (typeof item.click === 'function') {
            item.click();
          }
        }
      }
      return;
    }

    if (type === 'click') {
      if (target && typeof target.click === 'function') {
        target.click();
      }
      return;
    }

    if (type === 'set_value') {
      if (!payload.debug) {
        log('Ignored set_value action outside debug mode', action);
        return;
      }
      if (target && typeof target.value !== 'undefined') {
        target.value = Object.prototype.hasOwnProperty.call(action, 'value') ? String(action.value || '') : '';
        target.dispatchEvent(new Event('input', { bubbles: true }));
        target.dispatchEvent(new Event('change', { bubbles: true }));
      }
      return;
    }

    if (type === 'scroll_into_view') {
      if (!target || typeof target.scrollIntoView !== 'function') {
        return;
      }
      var padding = Number(action.padding_px || 0);
      target.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
      if (padding > 0) {
        window.setTimeout(function () {
          window.scrollBy(0, -padding);
        }, 120);
      }
    }
  }

  function runOnShowActions(step) {
    var actions = step && Array.isArray(step.on_show) ? step.on_show : [];
    actions.forEach(runAction);
  }

  function appendFormValue(params, key, value) {
    if (value === null || typeof value === 'undefined') {
      return;
    }

    if (Array.isArray(value)) {
      value.forEach(function (entry, index) {
        appendFormValue(params, key + '[' + index + ']', entry);
      });
      return;
    }

    if (typeof value === 'object') {
      Object.keys(value).forEach(function (childKey) {
        appendFormValue(params, key + '[' + childKey + ']', value[childKey]);
      });
      return;
    }

    if (typeof value === 'boolean') {
      params.append(key, value ? '1' : '0');
      return;
    }

    params.append(key, String(value));
  }

  function ajaxPost(action, data) {
    if (!payload.ajaxUrl || !payload.nonce) {
      return Promise.resolve(null);
    }

    var params = new URLSearchParams();
    params.set('action', action);
    params.set('nonce', payload.nonce);

    Object.keys(data || {}).forEach(function (key) {
      appendFormValue(params, key, data[key]);
    });

    return fetch(payload.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: params.toString()
    }).then(function (res) {
      if (!res.ok) {
        throw new Error('HTTP ' + res.status);
      }
      return res.json();
    }).catch(function (err) {
      log('AJAX request failed', { action: action, error: String(err && err.message ? err.message : err) });
      return null;
    });
  }

  function savePrefs(partial) {
    if (!user || !user.canRunTours) {
      return Promise.resolve(null);
    }

    var prefs = user.prefs || {};
    if (partial && typeof partial === 'object') {
      Object.keys(partial).forEach(function (key) {
        prefs[key] = partial[key];
      });
    }

    return ajaxPost('vms_tours_save_prefs', {
      screen_key: payload.screenKey || '',
      prefs: prefs
    }).then(function (res) {
      if (res && res.success && res.data && res.data.prefs) {
        user.prefs = res.data.prefs;
      }
      return res;
    });
  }

  function markSeen(tourId) {
    return ajaxPost('vms_tours_mark_complete', {
      mode: 'seen',
      tour_id: tourId
    });
  }

  function markComplete(tourId, tourVersion) {
    return ajaxPost('vms_tours_mark_complete', {
      mode: 'complete',
      tour_id: tourId,
      tour_version: tourVersion
    }).then(function (res) {
      if (res && res.success && res.data && res.data.state) {
        if (!user.state) {
          user.state = {};
        }
        user.state[tourId] = res.data.state;
      }
      return res;
    });
  }

  function stopActiveTour() {
    if (activeCleanup) {
      try {
        activeCleanup();
      } catch (err) {
        log('Failed to cleanup prior tour', err);
      }
      activeCleanup = null;
    }
    if (activeDriver && typeof activeDriver.destroy === 'function') {
      try {
        activeDriver.destroy();
      } catch (err2) {
        log('Failed to destroy prior tour', err2);
      }
    }
    activeDriver = null;
  }

  function runDriverFlow(flow) {
    var factory = getDriverFactory();
    if (!factory) {
      log('Driver.js was not found.');
      return Promise.resolve(false);
    }

    var prepared = Array.isArray(flow.steps) ? flow.steps : [];
    if (!prepared.length) {
      log('No runnable tour steps remain.', { tour: flow.id || 'ad-hoc' });
      if (typeof flow.onClose === 'function') {
        flow.onClose();
      }
      return Promise.resolve(false);
    }

    stopActiveTour();

    return new Promise(function (resolve) {
      var finished = false;
      var activeIndex = 0;
      var cleanup = function () {};

      var driverSteps = prepared.map(function (row, idx) {
        var step = row.step || {};
        return {
          element: function () {
            return query(row.selector);
          },
          popover: {
            title: step.title || 'Step',
            description: step.body || step.description || '',
            side: sanitizePlacement(step.placement),
            align: 'start',
            popoverClass: 'vms-driver-popover',
            nextBtnText: idx === (prepared.length - 1) ? 'Finish' : 'Next',
            prevBtnText: 'Back'
          }
        };
      });

      var driverObj = factory({
        animate: true,
        allowClose: true,
        showProgress: true,
        showButtons: ['previous', 'next', 'close'],
        overlayClickBehavior: 'close',
        popoverClass: 'vms-driver-popover',
        steps: driverSteps,
        onHighlightStarted: function (_, __, ctx) {
          var idx = ctx && ctx.state && typeof ctx.state.activeIndex === 'number' ? ctx.state.activeIndex : 0;
          activeIndex = idx;
          var row = prepared[idx];
          if (row && row.step) {
            runOnShowActions(row.step);
          }

          var target = row ? query(row.selector) : null;
          if (row && row.step && row.step.scroll_to !== false && target && typeof target.scrollIntoView === 'function') {
            target.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
            var padding = Number(row.step.scroll_padding_px || 0);
            if (padding > 0) {
              window.setTimeout(function () {
                window.scrollBy(0, -padding);
              }, 100);
            }
          }

          if (typeof flow.onStepChange === 'function') {
            flow.onStepChange(idx, prepared.length);
          }
        },
        onPopoverRender: function (popover) {
          if (!popover || !popover.footerButtons) {
            return;
          }

          if (!popover.footerButtons.querySelector('.vms-tour-skip-btn')) {
            var skipBtn = document.createElement('button');
            skipBtn.type = 'button';
            skipBtn.className = 'vms-tour-skip-btn';
            skipBtn.textContent = 'Skip';
            skipBtn.addEventListener('click', function (event) {
              event.preventDefault();
              finished = false;
              if (driverObj && typeof driverObj.destroy === 'function') {
                driverObj.destroy();
              }
            });
            popover.footerButtons.insertBefore(skipBtn, popover.footerButtons.firstChild);
          }
        },
        onDestroyed: function () {
          cleanup();
          activeDriver = null;
          activeCleanup = null;

          if (finished && typeof flow.onFinish === 'function') {
            flow.onFinish();
          } else if (!finished && typeof flow.onClose === 'function') {
            flow.onClose();
          }
          resolve(finished);
        }
      });

      var clickHandler = function (event) {
        if (!event.target || !event.target.closest) {
          return;
        }

        if (event.target.closest('.driver-popover-close-btn')) {
          finished = false;
          return;
        }

        if (event.target.closest('.driver-popover-next-btn')) {
          if (driverObj && typeof driverObj.hasNextStep === 'function' && !driverObj.hasNextStep()) {
            finished = true;
          }
        }
      };

      cleanup = function () {
        document.removeEventListener('click', clickHandler, true);
      };

      activeDriver = driverObj;
      activeCleanup = cleanup;

      document.addEventListener('click', clickHandler, true);

      try {
        var startIndex = flow.options && Number.isInteger(flow.options.startIndex) ? flow.options.startIndex : null;
        if (startIndex !== null && startIndex >= 0 && startIndex < prepared.length) {
          driverObj.drive(startIndex);
        } else {
          driverObj.drive();
        }
      } catch (err) {
        cleanup();
        activeDriver = null;
        activeCleanup = null;
        log('Failed to start Driver.js tour', err);
        if (typeof flow.onClose === 'function') {
          flow.onClose();
        }
        resolve(false);
      }
    });
  }

  function startRegisteredTour(tourId) {
    var tour = resolveRegisteredTour(tourId);
    if (!tour && tours.length === 1 && tours[0] && tours[0].id) {
      tour = tours[0];
    }
    if (!tour) {
      log('Tour was requested but not found in runtime payload.', { requested: tourId });
      return Promise.resolve(false);
    }

    var prepared = buildPreparedSteps(tour);
    return runDriverFlow({
      id: tour.id,
      steps: prepared,
      onFinish: function () {
        markSeen(tour.id).then(function () {
          markComplete(tour.id, tour.version || '1.0.0');
        });
      },
      onClose: function () {
        markSeen(tour.id);
      }
    });
  }

  function buildInlineHelpMap() {
    inlineHelpByAnchor = {};

    tours.forEach(function (tour) {
      var steps = tour && Array.isArray(tour.steps) ? tour.steps : [];
      steps.forEach(function (step) {
        if (!step || !step.selector) {
          return;
        }

        var anchor = extractAnchorFromSelector(step.selector);
        if (!anchor || Object.prototype.hasOwnProperty.call(inlineHelpByAnchor, anchor)) {
          return;
        }

        inlineHelpByAnchor[anchor] = {
          tourId: String((tour && tour.id) || ''),
          selector: '[data-vms-tour="' + anchor + '"]',
          title: String(step.title || 'Field help'),
          body: String(step.body || step.description || ''),
          placement: sanitizePlacement(step.placement || 'right')
        };
      });
    });
  }

  function pickInlineHelpTarget(node) {
    if (!node) {
      return null;
    }

    var preferred = node.querySelector('label,th,h1,h2,h3,h4,strong,summary,.description');
    if (preferred && !preferred.matches('button,a,input,select,textarea')) {
      return preferred;
    }

    if (node.matches('button,a,input,select,textarea')) {
      return node.parentElement || null;
    }

    return node;
  }

  function injectInlineHelpIcons() {
    var anchors = Object.keys(inlineHelpByAnchor);
    if (!anchors.length) {
      return;
    }

    anchors.forEach(function (anchor) {
      var entry = inlineHelpByAnchor[anchor];
      if (!entry || !entry.selector) {
        return;
      }

      var nodes = document.querySelectorAll(entry.selector);
      nodes.forEach(function (node) {
        if (!node || node.closest('.vms-ma')) {
          return;
        }

        var target = pickInlineHelpTarget(node);
        if (!target) {
          return;
        }

        if (target.querySelector('.vms-tour-inline-help-btn[data-vms-tour-help-anchor="' + anchor + '"]')) {
          return;
        }

        if (target.querySelector('.vms-ma-info')) {
          return;
        }

        var icon = document.createElement('button');
        icon.type = 'button';
        icon.className = 'vms-tour-inline-help-btn';
        icon.textContent = 'i';
        icon.setAttribute('aria-label', 'Open help: ' + (entry.title || 'Field help'));
        icon.setAttribute('title', entry.title || 'Field help');
        icon.setAttribute('data-vms-tour-help-anchor', anchor);
        target.appendChild(icon);
      });
    });
  }

  function startInlineHelp(anchor, triggerEl) {
    var key = normalizeToken(anchor);
    if (!key || !Object.prototype.hasOwnProperty.call(inlineHelpByAnchor, key)) {
      return false;
    }

    var entry = inlineHelpByAnchor[key];
    var selector = entry.selector;
    var target = null;
    var tempTargetId = '';
    if (triggerEl && typeof triggerEl.closest === 'function') {
      target = triggerEl.closest('[data-vms-tour="' + key + '"]');
      if (target) {
        tempTargetId = 'vms-inline-' + String(Date.now()) + '-' + String(Math.floor(Math.random() * 10000));
        target.setAttribute('data-vms-tour-inline-target', tempTargetId);
        selector = '[data-vms-tour-inline-target="' + tempTargetId + '"]';
      }
    }

    var cleanup = function () {
      if (target && tempTargetId) {
        target.removeAttribute('data-vms-tour-inline-target');
      }
    };

    return startAdHocTour({
      tourId: 'vms.inline.' + key,
      steps: [
        {
          id: 'inline_' + key,
          selector: selector,
          title: entry.title || 'Field help',
          html: entry.body || '',
          placement: entry.placement || 'right'
        }
      ],
      options: {
        scrollIntoView: true
      },
      onClose: cleanup,
      onFinish: cleanup
    });
  }

  function startAdHocTour(config) {
    var cfg = config || {};
    var rawSteps = Array.isArray(cfg.steps) ? cfg.steps : [];
    if (!rawSteps.length) {
      return false;
    }

    var prepared = [];
    rawSteps.forEach(function (step, idx) {
      if (!step) {
        return;
      }
      var selector = step.selector || '';
      if (!selector || !query(selector)) {
        return;
      }
      prepared.push({
        selector: selector,
        step: {
          id: step.id || ('adhoc_' + idx),
          selector: selector,
          title: step.title || 'Step',
          body: step.html || step.description || '',
          placement: step.prefer || step.placement || 'auto',
          scroll_to: cfg.options && cfg.options.scrollIntoView === false ? false : true,
          scroll_padding_px: 12,
          on_show: []
        }
      });
    });

    if (!prepared.length) {
      if (typeof cfg.onClose === 'function') {
        cfg.onClose();
      }
      return false;
    }

    runDriverFlow({
      id: cfg.tourId || 'vms.adhoc',
      steps: prepared,
      options: cfg.options || {},
      onFinish: typeof cfg.onFinish === 'function' ? cfg.onFinish : null,
      onClose: typeof cfg.onClose === 'function' ? cfg.onClose : null,
      onStepChange: typeof cfg.onStepChange === 'function' ? cfg.onStepChange : null
    });

    return true;
  }

  function maybeAutoRun() {
    if (!user || !user.canRunTours) {
      return;
    }
    if (!settings.global_enabled) {
      return;
    }

    var prefs = user.prefs || {};
    if (!prefs.auto_run_enabled) {
      return;
    }

    var dismissed = prefs.dismissed_tours || {};
    var state = user.state || {};

    var eligible = tours.filter(function (tour) {
      if (!tour || !tour.id) {
        return false;
      }
      if (!tour.auto_run) {
        return false;
      }
      if (dismissed[tour.id]) {
        return false;
      }
      if (state[tour.id] && state[tour.id].completed_version === tour.version) {
        return false;
      }
      return buildPreparedSteps(tour).length > 0;
    });

    if (!eligible.length) {
      return;
    }

    eligible.sort(function (a, b) {
      return Number(a.priority || 10) - Number(b.priority || 10);
    });

    var maxRuns = Number(settings.max_auto_run_per_page_load || 1);
    if (!Number.isFinite(maxRuns) || maxRuns < 1) {
      maxRuns = 1;
    }
    var queue = eligible.slice(0, maxRuns);

    var chain = Promise.resolve();
    queue.forEach(function (tour, index) {
      chain = chain.then(function () {
        var delay = Number(tour.auto_run_delay_ms || settings.auto_run_delay_ms || 400);
        if (!Number.isFinite(delay) || delay < 0) {
          delay = 0;
        }
        return new Promise(function (resolve) {
          window.setTimeout(function () {
            startRegisteredTour(tour.id).then(function () {
              resolve(true);
            });
          }, index === 0 ? delay : 220);
        });
      });
    });
  }

  function createHelpPanel() {
    var panel = document.getElementById('vms-help-tour-panel');
    if (panel) {
      return panel;
    }

    panel = document.createElement('div');
    panel.id = 'vms-help-tour-panel';
    panel.className = 'vms-help-tour-panel';
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-label', 'Guided tours');
    panel.hidden = true;

    var title = document.createElement('h3');
    title.textContent = 'Guided Tours';
    panel.appendChild(title);

    var list = document.createElement('div');
    list.className = 'vms-help-tour-list';

    tours.forEach(function (tour) {
      var row = document.createElement('div');
      row.className = 'vms-help-tour-row';

      var runBtn = document.createElement('button');
      runBtn.type = 'button';
      runBtn.className = 'button button-secondary';
      runBtn.textContent = tour.title || tour.id;
      runBtn.setAttribute('data-vms-tour-run', tour.id);

      var desc = document.createElement('p');
      desc.className = 'description';
      desc.textContent = tour.description || '';

      var dismissLabel = document.createElement('label');
      dismissLabel.className = 'vms-help-tour-dismiss';
      var dismissBox = document.createElement('input');
      dismissBox.type = 'checkbox';
      dismissBox.checked = !!(user.prefs && user.prefs.dismissed_tours && user.prefs.dismissed_tours[tour.id]);
      dismissBox.addEventListener('change', function () {
        var dismissed = user.prefs && user.prefs.dismissed_tours ? user.prefs.dismissed_tours : {};
        dismissed[tour.id] = dismissBox.checked;
        if (!user.prefs) {
          user.prefs = {};
        }
        user.prefs.dismissed_tours = dismissed;
        savePrefs({ dismissed_tours: dismissed });
      });

      dismissLabel.appendChild(dismissBox);
      dismissLabel.appendChild(document.createTextNode(' Don\'t auto-show this tour'));

      row.appendChild(runBtn);
      row.appendChild(desc);
      row.appendChild(dismissLabel);
      list.appendChild(row);
    });

    panel.appendChild(list);

    var autoToggleWrap = document.createElement('label');
    autoToggleWrap.className = 'vms-help-tour-auto-toggle';
    var autoToggle = document.createElement('input');
    autoToggle.type = 'checkbox';
    autoToggle.checked = !!(user.prefs && user.prefs.auto_run_enabled === false);
    autoToggle.addEventListener('change', function () {
      if (!user.prefs) {
        user.prefs = {};
      }
      user.prefs.auto_run_enabled = !autoToggle.checked;
      savePrefs({ auto_run_enabled: !autoToggle.checked });
    });
    autoToggleWrap.appendChild(autoToggle);
    autoToggleWrap.appendChild(document.createTextNode(' Turn off auto tours'));
    panel.appendChild(autoToggleWrap);

    document.body.appendChild(panel);
    return panel;
  }

  function injectHelpButton() {
    if (!settings.help_button_enabled || !tours.length) {
      return;
    }

    var isAdminScreen = !!(payload.context && payload.context.isAdminScreen);
    if (!isAdminScreen) {
      return;
    }

    if (document.getElementById('vms-help-tour-button')) {
      return;
    }

    var button = document.createElement('button');
    button.id = 'vms-help-tour-button';
    button.type = 'button';
    button.textContent = 'Help';
    button.setAttribute('aria-haspopup', 'dialog');
    document.body.appendChild(button);

    var panel = createHelpPanel();

    button.addEventListener('click', function () {
      if (tours.length === 1) {
        startRegisteredTour(tours[0].id);
        return;
      }

      panel.hidden = !panel.hidden;
      button.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
    });

    document.addEventListener('click', function (event) {
      if (!panel || panel.hidden) {
        return;
      }
      if (event.target === button || panel.contains(event.target)) {
        return;
      }
      panel.hidden = true;
      button.setAttribute('aria-expanded', 'false');
    });
  }

  function parseFallbackTourConfig(node) {
    if (!node || !node.getAttribute) {
      return null;
    }

    var raw = node.getAttribute('data-vms-tour-fallback') || '';
    if (!raw) {
      return null;
    }

    try {
      var parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' ? parsed : null;
    } catch (err) {
      log('Failed to parse fallback tour config.', err);
      return null;
    }
  }

  function wireGlobalButtons() {
    document.addEventListener('click', function (event) {
      if (!event.target || !event.target.closest) {
        return;
      }

      function openHelpPanelFallback() {
        var panel = document.getElementById('vms-help-tour-panel');
        if (panel && !panel.hidden) {
          return;
        }
        var helpBtn = document.getElementById('vms-help-tour-button');
        if (helpBtn) {
          helpBtn.click();
        }
      }

      function hasVisibleTourChrome() {
        var popover = document.querySelector('.driver-popover');
        if (popover && !popover.hidden) {
          return true;
        }

        var panel = document.getElementById('vms-help-tour-panel');
        return !!(panel && !panel.hidden);
      }

      var inlineHelp = event.target.closest('[data-vms-tour-help-anchor]');
      if (inlineHelp) {
        var anchor = inlineHelp.getAttribute('data-vms-tour-help-anchor') || '';
        if (anchor) {
          event.preventDefault();
          startInlineHelp(anchor, inlineHelp);
        }
        return;
      }

      var run = event.target.closest('[data-vms-tour-start], [data-vms-tour-run]');
      if (run) {
        var tourId = run.getAttribute('data-vms-tour-start') || run.getAttribute('data-vms-tour-run') || '';
        if (tourId) {
          event.preventDefault();

          var fallbackUsed = false;
          function triggerTourFallback() {
            if (fallbackUsed || hasVisibleTourChrome()) {
              return;
            }
            fallbackUsed = true;

            var fallbackConfig = parseFallbackTourConfig(run);
            if (fallbackConfig) {
              var adHocStarted = startAdHocTour(fallbackConfig);
              if (!adHocStarted) {
                openHelpPanelFallback();
              } else {
                window.setTimeout(function () {
                  if (!hasVisibleTourChrome()) {
                    openHelpPanelFallback();
                  }
                }, 500);
              }
              return;
            }

            openHelpPanelFallback();
          }

          startRegisteredTour(tourId).then(function (started) {
            if (started) {
              return;
            }
            triggerTourFallback();
          });

          window.setTimeout(function () {
            triggerTourFallback();
          }, 700);
        }
        return;
      }

      var openHelp = event.target.closest('[data-vms-help-open="1"]');
      if (openHelp) {
        var helpBtn = document.getElementById('vms-help-tour-button');
        if (helpBtn) {
          event.preventDefault();
          helpBtn.click();
        }
      }
    });
  }

  function wireLegacyAutoRunToggles() {
    var ids = ['vms-ma-tour-autorun-toggle', 'vms-ma-settings-tour-autorun-toggle'];
    ids.forEach(function (id) {
      var toggle = document.getElementById(id);
      if (!toggle) {
        return;
      }

      if (user && user.prefs) {
        toggle.checked = !!user.prefs.auto_run_enabled;
      }

      toggle.addEventListener('change', function () {
        if (!user.prefs) {
          user.prefs = {};
        }
        user.prefs.auto_run_enabled = !!toggle.checked;
        savePrefs({ auto_run_enabled: !!toggle.checked });
      });
    });
  }

  function initCompatibilityApi() {
    window.VMS_Tour = window.VMS_Tour || {};
    window.VMS_Tour.start = startAdHocTour;
  }

  function init() {
    buildInlineHelpMap();
    wireGlobalButtons();
    wireLegacyAutoRunToggles();
    injectHelpButton();
    injectInlineHelpIcons();
    window.setTimeout(injectInlineHelpIcons, 280);
    initCompatibilityApi();
    maybeAutoRun();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
