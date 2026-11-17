(function () {
  'use strict';

  const cfg = window.BVMGR_TOURS || {};

  const runtimeDebounce = new Map();
  const runtimeDebounceSeconds = 60;
  const scanDelayMs = 250;
  const iframeTimeoutMs = 15000;
  const missingNoticeKeys = new Set();

  function apiRequest(url, method, body) {
    const nonce = cfg && cfg.rest ? cfg.rest.nonce : '';
    return fetch(url, {
      method: method || 'GET',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': nonce
      },
      body: body ? JSON.stringify(body) : undefined
    }).then(function (res) {
      if (!res.ok) {
        return res.text().then(function (txt) {
          throw new Error(txt || ('HTTP ' + res.status));
        });
      }
      return res.json();
    });
  }

  function ajaxStateUpdate(payload) {
    if (!cfg || !cfg.ajax || !cfg.ajax.url || !cfg.ajax.nonce) {
      return Promise.resolve(null);
    }
    const form = new URLSearchParams();
    form.set('action', 'vms_tours_update_state');
    form.set('nonce', cfg.ajax.nonce);
    Object.keys(payload).forEach(function (k) {
      form.set(k, String(payload[k]));
    });
    return fetch(cfg.ajax.url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: form.toString()
    }).catch(function () {
      return null;
    });
  }

  function getTourById(tourId) {
    const tours = Array.isArray(cfg.tours) ? cfg.tours : [];
    for (let i = 0; i < tours.length; i++) {
      if ((tours[i].id || '') === tourId) return tours[i];
    }
    return null;
  }

  function getContextTour() {
    const tours = Array.isArray(cfg.tours) ? cfg.tours : [];
    const key = cfg.currentContext || '';
    if (!key) return null;
    for (let i = 0; i < tours.length; i++) {
      const contexts = Array.isArray(tours[i].contexts) ? tours[i].contexts : [];
      for (let j = 0; j < contexts.length; j++) {
        if ((contexts[j].context_key || '') === key) return tours[i];
      }
    }
    return null;
  }

  function reportMissingAnchor(tour, step) {
    if (!tour || !step || !cfg.rest || !cfg.rest.drift) return;
    const minute = Math.floor(Date.now() / (runtimeDebounceSeconds * 1000));
    const key = [cfg.currentContext || '', step.anchor || '', minute].join('::');
    if (runtimeDebounce.has(key)) return;
    runtimeDebounce.set(key, true);

    apiRequest(cfg.rest.drift, 'POST', {
      context_key: cfg.currentContext || '',
      tour_id: tour.id || 'unknown',
      anchor: step.anchor || '',
      severity: step.severity || 'required'
    }).catch(function () {
      // Keep tour flow alive even if report call fails.
    });
  }

  function selectorForStep(step) {
    if (!step || !step.anchor) return '';
    return '[data-vms-tour="' + step.anchor + '"]';
  }

  function mapSide(placement) {
    const side = placement || 'right';
    if (side === 'top' || side === 'right' || side === 'bottom' || side === 'left') {
      return side;
    }
    return 'right';
  }

  function mapAlign(align) {
    const value = align || 'start';
    if (value === 'start' || value === 'center' || value === 'end') {
      return value;
    }
    return 'start';
  }

  function sleep(ms) {
    return new Promise(function (resolve) {
      window.setTimeout(resolve, ms);
    });
  }

  function resolveFieldElement(target) {
    if (!target) return null;
    if (typeof target === 'string') return document.querySelector(target);
    if (target instanceof Element) return target;
    return null;
  }

  function fieldHasNonDefault(target, defaultValue) {
    const el = resolveFieldElement(target);
    if (!el) return false;

    let value = '';
    if (el.type === 'checkbox') {
      value = el.checked ? '1' : '0';
    } else if (el.type === 'radio') {
      const scope = el.closest('form') || document;
      const checked = el.checked ? el : scope.querySelector('input[type="radio"][name="' + (el.name || '') + '"]:checked');
      value = checked ? String(checked.value || '').trim() : '';
    } else if (el.hasAttribute('data-token-present')) {
      value = String(el.getAttribute('data-token-present') || '').trim();
    } else if (typeof el.value !== 'undefined') {
      value = String(el.value || '').trim();
    } else {
      value = String(el.textContent || '').trim();
    }

    let normalizedDefault = defaultValue;
    if (normalizedDefault === null || typeof normalizedDefault === 'undefined') {
      normalizedDefault = '';
    }
    if (typeof normalizedDefault === 'number') {
      const numeric = parseFloat(value);
      return Number.isFinite(numeric) && numeric !== normalizedDefault;
    }
    return value !== String(normalizedDefault).trim();
  }

  function shouldSkipFilledStep(step) {
    if (!step) return false;
    const rules = Array.isArray(step.skip_when_filled)
      ? step.skip_when_filled
      : (Array.isArray(step.skipWhenFilled) ? step.skipWhenFilled : []);
    if (!rules.length) return false;
    return rules.every(function (rule) {
      const selector = (rule && rule.selector) || '';
      const defaultValue = rule && Object.prototype.hasOwnProperty.call(rule, 'defaultValue')
        ? rule.defaultValue
        : (rule && Object.prototype.hasOwnProperty.call(rule, 'default') ? rule.default : '');
      return fieldHasNonDefault(selector, defaultValue);
    });
  }

  function waitFor(selector, timeoutMs) {
    const timeout = typeof timeoutMs === 'number' ? timeoutMs : 4000;
    const start = Date.now();

    return new Promise(function (resolve) {
      function poll() {
        const element = selector ? document.querySelector(selector) : null;
        if (element) {
          resolve(element);
          return;
        }
        if ((Date.now() - start) >= timeout) {
          resolve(null);
          return;
        }
        window.setTimeout(poll, 100);
      }
      poll();
    });
  }

  function ensureNoticeContainer() {
    let container = document.getElementById('vms-tours-runtime-notices');
    if (container) return container;

    container = document.createElement('div');
    container.id = 'vms-tours-runtime-notices';

    const wrap = document.querySelector('#wpbody-content .wrap') || document.querySelector('#wpbody-content');
    if (wrap && wrap.firstChild) {
      wrap.insertBefore(container, wrap.firstChild);
    } else if (wrap) {
      wrap.appendChild(container);
    }

    return container;
  }

  function showMissingTargetNotice(selector) {
    if (!selector) return;
    const key = 'missing::' + selector;
    if (missingNoticeKeys.has(key)) return;
    missingNoticeKeys.add(key);

    console.warn('Tour target missing: ' + selector);

    const container = ensureNoticeContainer();
    if (!container) return;

    const notice = document.createElement('div');
    notice.className = 'notice notice-warning';
    notice.innerHTML = '<p><strong>Tour target missing:</strong> <code>' + selector + '</code> (step skipped)</p>';
    container.appendChild(notice);
  }

  function buildDriverSteps(tour) {
    const inSteps = Array.isArray(tour.steps) ? tour.steps : [];
    const out = [];

    for (let i = 0; i < inSteps.length; i++) {
      const step = inSteps[i];
      if (shouldSkipFilledStep(step)) {
        continue;
      }
      const selector = selectorForStep(step);
      if (!selector || !document.querySelector(selector)) {
        showMissingTargetNotice(selector);
        reportMissingAnchor(tour, step);
        continue;
      }

      out.push({
        sourceIndex: i,
        selector: selector,
        element: function () {
          return document.querySelector(selector);
        },
        popover: {
          title: step.title || 'Step',
          description: step.content || '',
          side: mapSide(step.placement),
          align: mapAlign(step.align),
          popoverClass: 'vms-driver-popover'
        }
      });
    }
  
    return out;
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

  function runDriverTour(tour, steps) {
    const driverFactory = getDriverFactory();
 
    if (!driverFactory) {
      showMissingTargetNotice('Driver.js not loaded');
      return;
    }
  
    let endedStatus = 'completed';
    let lastActiveIndex = 0;

    const driverObj = driverFactory({
      animate: true,
      showProgress: true,
      overlayOpacity: 0.08,
      overlayColor: '#0b57d0',
      allowClose: true,
      showButtons: ['close', 'previous', 'next'],
      overlayClickBehavior: 'close',
      popoverClass: 'vms-driver-popover',
      steps: steps,
      onHighlightStarted: function (_, __, context) {
        const idx = context && context.state && typeof context.state.activeIndex === 'number' ? context.state.activeIndex : 0;
        lastActiveIndex = idx;
        const active = steps[idx] && typeof steps[idx].element === 'function' ? steps[idx].element() : null;
        if (active && typeof active.scrollIntoView === 'function') {
          active.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
        }

        ajaxStateUpdate({
          tour_id: tour.id,
          version: tour.version || 1,
          status: 'in_progress',
          step_index: idx
        });
      },
      onDestroyStarted: function (_, __, context) {
        const idx = context && context.state && typeof context.state.activeIndex === 'number' ? context.state.activeIndex : lastActiveIndex;
        lastActiveIndex = idx;
        if (idx < (steps.length - 1)) {
          endedStatus = 'dismissed';
        }
      },
      onDestroyed: function () {
        cleanupManualClose();
        ajaxStateUpdate({
          tour_id: tour.id,
          version: tour.version || 1,
          status: endedStatus,
          step_index: lastActiveIndex
        });
      }
    });

    const manualCloseHandler = function (event) {
      if (!event.target || !event.target.closest) return;
      if (event.target.closest('.driver-popover-close-btn')) {
        event.preventDefault();
        if (driverObj && typeof driverObj.destroy === 'function') {
          driverObj.destroy();
        }
        return;
      }
      if (event.target.closest('.driver-popover-next-btn')) {
        if (driverObj && typeof driverObj.hasNextStep === 'function' && !driverObj.hasNextStep()) {
          driverObj.destroy();
        }
      }
    };
    document.addEventListener('click', manualCloseHandler, true);
    try {
      driverObj.drive();
    } catch (err) {
      showMissingTargetNotice('Driver.js failed to start');
    }

    const cleanupManualClose = function () {
      document.removeEventListener('click', manualCloseHandler, true);
    };
  }

  function runSharedTour(config) {
    const safeConfig = config || {};
    const inSteps = Array.isArray(safeConfig.steps) ? safeConfig.steps : [];
    const steps = [];
    const opts = safeConfig.options || {};

    for (let i = 0; i < inSteps.length; i++) {
      const step = inSteps[i] || {};
      if (shouldSkipFilledStep(step)) {
        continue;
      }
      const selector = step.selector || '';
      const target = selector ? document.querySelector(selector) : null;
      if (!target) {
        showMissingTargetNotice(selector || ('step:' + i));
        continue;
      }
      steps.push({
        selector: selector,
        element: function () {
          return document.querySelector(selector);
        },
        popover: {
          title: step.title || 'Step',
          description: step.html || step.description || '',
          side: mapSide(step.prefer || step.placement),
          align: mapAlign(step.align),
          popoverClass: 'vms-driver-popover'
        }
      });
    }

    if (!steps.length) {
      showMissingTargetNotice('No valid tour targets found');
      if (typeof safeConfig.onClose === 'function') {
        safeConfig.onClose();
      }
      return false;
    }

    const driverFactory = getDriverFactory();
    if (!driverFactory) {
      showMissingTargetNotice('Driver.js not loaded');
      return false;
    }

    let endedByDone = false;
    let hadClose = false;
    let lastActiveIndex = 0;

    const driverObj = driverFactory({
      animate: true,
      showProgress: opts.showProgress !== false,
      overlayOpacity: 0.08,
      overlayColor: '#0b57d0',
      allowClose: opts.allowClose !== false,
      showButtons: ['close', 'previous', 'next'],
      overlayClickBehavior: 'close',
      popoverClass: 'vms-driver-popover',
      steps: steps,
      onHighlightStarted: function (_, __, context) {
        const idx = context && context.state && typeof context.state.activeIndex === 'number' ? context.state.activeIndex : 0;
        lastActiveIndex = idx;
        const active = steps[idx] && typeof steps[idx].element === 'function' ? steps[idx].element() : null;
        if (opts.scrollIntoView !== false && active && typeof active.scrollIntoView === 'function') {
          active.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
        }
        if (typeof safeConfig.onStepChange === 'function') {
          safeConfig.onStepChange(idx, steps.length);
        }
      },
      onDestroyStarted: function (_, __, context) {
        const idx = context && context.state && typeof context.state.activeIndex === 'number' ? context.state.activeIndex : lastActiveIndex;
        lastActiveIndex = idx;
        if (idx < (steps.length - 1) && !endedByDone) {
          hadClose = true;
        }
      },
      onDestroyed: function () {
        cleanupManualClose();
        if (endedByDone && typeof safeConfig.onFinish === 'function') {
          safeConfig.onFinish();
          return;
        }
        if (hadClose && typeof safeConfig.onClose === 'function') {
          safeConfig.onClose();
        }
      }
    });

    const manualCloseHandler = function (event) {
      if (!event.target || !event.target.closest) return;
      if (event.target.closest('.driver-popover-close-btn')) {
        hadClose = true;
        event.preventDefault();
        if (driverObj && typeof driverObj.destroy === 'function') {
          driverObj.destroy();
        }
        return;
      }
      if (event.target.closest('.driver-popover-next-btn')) {
        if (driverObj && typeof driverObj.hasNextStep === 'function' && !driverObj.hasNextStep()) {
          endedByDone = true;
          event.preventDefault();
          driverObj.destroy();
        }
      }
    };
    document.addEventListener('click', manualCloseHandler, true);

    const cleanupManualClose = function () {
      document.removeEventListener('click', manualCloseHandler, true);
    };

    try {
      const startIndex = Number.isInteger(opts.startIndex) ? opts.startIndex : null;
      if (startIndex !== null && startIndex >= 0 && startIndex < steps.length) {
        driverObj.drive(startIndex);
      } else {
        driverObj.drive();
      }
      return true;
    } catch (err) {
      cleanupManualClose();
      showMissingTargetNotice('Driver.js failed to start');
      return false;
    }
  }

  window.BVMGR_TOUR = window.BVMGR_TOUR || {};
  window.BVMGR_TOUR.start = runSharedTour;

  function startTour(tourId) {
    const tour = getTourById(tourId) || getContextTour();
    if (!tour) return;

    const steps = buildDriverSteps(tour);
    if (!steps.length) {
      showMissingTargetNotice('No valid tour targets found');
      return;
    }

    waitFor(steps[0].selector, 5000).then(function (firstEl) {
      if (!firstEl) {
        showMissingTargetNotice(steps[0].selector);
        reportMissingAnchor(tour, tour.steps[steps[0].sourceIndex] || {});
        return;
      }
      runDriverTour(tour, steps);
    });
  }

  function updateTileUi(data) {
    const tile = document.getElementById('vms-tours-dashboard-tile');
    if (!tile || !data) return;

    const status = tile.querySelector('.vms-tours-tile-status');
    if (status) {
      const dateStr = data.last_report_at ? new Date(data.last_report_at * 1000).toLocaleString() : 'Never';
      status.textContent = 'Enabled: ' + (data.enabled ? 'Yes' : 'No') + ' | Missing anchors: ' + (data.missing_anchor_count || 0) + ' | Affected tours: ' + (data.affected_tour_count || 0) + ' | Last report: ' + dateStr + ' (' + (data.last_source || 'runtime') + ')';
    }
  }

  function setScanStatus(message, isError) {
    const el = document.getElementById('vms-tours-scan-status');
    if (!el) return;
    el.textContent = message || '';
    el.classList.toggle('is-error', !!isError);
  }

  function loadReportIntoDom() {
    const pre = document.getElementById('vms-tours-report-json');
    const dashCopy = document.getElementById('vms-tours-dashboard-copy');
    if (!pre && !dashCopy) return Promise.resolve(null);
    return apiRequest(cfg.rest.driftReport, 'GET').then(function (report) {
      const text = JSON.stringify(report || {}, null, 2);
      if (pre) pre.textContent = text;
      if (dashCopy) dashCopy.textContent = text;
      return report;
    }).catch(function (err) {
      if (pre) {
        pre.textContent = 'Failed loading drift report: ' + (err && err.message ? err.message : String(err));
      }
      return null;
    });
  }

  function copyCurrentReport() {
    const reportNode = document.getElementById('vms-tours-report-json');
    const dashNode = document.getElementById('vms-tours-dashboard-copy');
    const text = reportNode ? reportNode.textContent : (dashNode ? dashNode.textContent : '');
    if (!text) {
      setScanStatus('No report is loaded to copy yet.', true);
      return;
    }

    const fallbackCopy = function (value) {
      const ta = document.createElement('textarea');
      ta.value = value;
      ta.setAttribute('readonly', 'readonly');
      ta.style.position = 'fixed';
      ta.style.top = '-9999px';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.focus();
      ta.select();
      let copied = false;
      try {
        copied = document.execCommand('copy');
      } catch (e) {
        copied = false;
      }
      ta.remove();
      return copied;
    };

    const onDone = function (ok) {
      setScanStatus(ok ? 'Report copied to clipboard.' : 'Copy failed. Paste the report manually for now.', !ok);
    };

    if (window.isSecureContext && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
      navigator.clipboard.writeText(text).then(function () {
        onDone(true);
      }).catch(function () {
        onDone(fallbackCopy(text));
      });
      return;
    }

    onDone(fallbackCopy(text));
  }

  function scanContext(baseAdminUrl, contextKey, contractRow) {
    return new Promise(function (resolve) {
      const url = baseAdminUrl + contractRow.url;
      const iframe = document.createElement('iframe');
      iframe.className = 'vms-tour-scan-frame';
      iframe.setAttribute('aria-hidden', 'true');
      iframe.src = url;
      document.body.appendChild(iframe);

      const done = function (row) {
        iframe.remove();
        resolve(row);
      };

      const timeout = window.setTimeout(function () {
        done({
          missing_anchors: {},
          scan_error: 'timeout'
        });
      }, iframeTimeoutMs);

      iframe.addEventListener('load', function () {
        window.clearTimeout(timeout);
        sleep(scanDelayMs).then(function () {
          try {
            const doc = iframe.contentDocument;
            if (!doc) {
              done({ missing_anchors: {}, scan_error: 'document_unavailable' });
              return;
            }
            const required = Array.isArray(contractRow.required_anchors) ? contractRow.required_anchors : [];
            const missing = {};
            required.forEach(function (anchor) {
              const sel = '[data-vms-tour="' + anchor + '"]';
              if (!doc.querySelector(sel)) {
                const anchorTourMap = contractRow && typeof contractRow.anchor_tour_map === 'object'
                  ? contractRow.anchor_tour_map
                  : {};
                const mappedTourId = anchorTourMap && anchorTourMap[anchor] ? anchorTourMap[anchor] : '';
                missing[anchor] = {
                  anchor: anchor,
                  severity: 'required',
                  seen_count: 1,
                  tour_id: mappedTourId || contractRow.tour_id || 'unknown'
                };
              }
            });
            done({
              missing_anchors: missing,
              scan_error: ''
            });
          } catch (e) {
            done({
              missing_anchors: {},
              scan_error: 'scan_exception'
            });
          }
        });
      });
    }).then(function (row) {
      return [contextKey, row];
    });
  }

  function runScan(options) {
    if (!cfg.canManage) return Promise.resolve(null);
    const source = options && options.source ? options.source : 'scan';
    setScanStatus(cfg.i18n.scanInProgress || 'Scan in progress…', false);

    return apiRequest(cfg.rest.anchorContract, 'GET').then(function (res) {
      const contract = res && res.contract ? res.contract : {};
      const baseAdminUrl = (cfg.maintenanceUrl || '').replace(/admin\.php.*$/, '');
      const contextKeys = Object.keys(contract);
      const contexts = {};

      let chain = Promise.resolve();
      contextKeys.forEach(function (key) {
        chain = chain.then(function () {
          return scanContext(baseAdminUrl, key, contract[key]).then(function (result) {
            contexts[result[0]] = result[1];
            return sleep(scanDelayMs);
          });
        });
      });

      return chain.then(function () {
        return apiRequest(cfg.rest.driftScan, 'POST', {
          source: source,
          contexts: contexts
        });
      });
    }).then(function (saved) {
      setScanStatus('Scan complete.', false);
      return Promise.all([
        loadReportIntoDom(),
        apiRequest(cfg.rest.tileData, 'GET').then(updateTileUi).catch(function () {
          return null;
        })
      ]).then(function () {
        return saved;
      });
    }).catch(function (err) {
      setScanStatus('Scan failed: ' + (err && err.message ? err.message : String(err)), true);
      return null;
    });
  }

  function wireActions() {
    document.querySelectorAll('[data-vms-tour-start]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const tourId = btn.getAttribute('data-vms-tour-start') || '';
        startTour(tourId);
      });
    });

    document.querySelectorAll('[data-vms-help-action]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const action = btn.getAttribute('data-vms-help-action') || '';
        const details = btn.closest('details');
        if (details) {
          details.open = false;
        }
        if (action === 'quick_tips') {
          window.alert((cfg.i18n && cfg.i18n.quickTipsStub) || 'Quick Tips will be added in a future update.');
          return;
        }
        if (action === 'whats_new') {
          window.alert((cfg.i18n && cfg.i18n.whatsNewStub) || "What's New will be added in a future update.");
        }
      });
    });

    document.querySelectorAll('[data-vms-tour-copy-report]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        copyCurrentReport();
      });
    });

    document.querySelectorAll('[data-vms-tour-scan-now]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        runScan({ source: 'scan' });
      });
    });
  }

  function maybeAutostart() {
    if (!cfg.autostart || !cfg.currentContext) return;
    const tour = getContextTour();
    if (!tour) return;
    const state = cfg.userState || {};
    const seen = state[tour.id] || null;
    if (seen && seen.version_seen === tour.version && (seen.status === 'completed' || seen.status === 'dismissed')) {
      return;
    }
    startTour(tour.id);
  }

  function initDashboardTile() {
    const tile = document.getElementById('vms-tours-dashboard-tile');
    if (!tile || !cfg.canManage) return;

    apiRequest(cfg.rest.tileData, 'GET').then(function (data) {
      updateTileUi(data);
      if (data && Number(data.pending_scan || 0) > 0) {
        runScan({ source: 'auto-update' });
      }
    }).catch(function () {
      // no-op
    });
  }

  if (cfg && cfg.enabled) {
    wireActions();
    loadReportIntoDom();
    initDashboardTile();
    maybeAutostart();
  }
})();
