(function () {
  'use strict';

  var cfg = window.BVMGR_TICKETING_FRONT || {};

  function isDiagFlagEnabled() {
    var raw = '';
    try {
      var params = new URLSearchParams(window.location.search || '');
      raw = params.get('vms_ticketing_debug') || params.get('vms_debug') || '';
    } catch (err) {
      raw = '';
    }
    raw = String(raw || '').trim().toLowerCase();
    return raw === '1' || raw === 'true' || raw === 'yes' || raw === 'on';
  }

  var DIAG = {
    enabled: String(cfg.isAdminUser || '') === '1' && isDiagFlagEnabled(),
    startedAt: Date.now(),
    events: [],
    panel: null,
    interval: 0,
    lastSummary: '',
    flags: {
      bundleLoaded: false,
      initCalls: 0,
      initSuccess: false,
      bootCalls: 0,
      sourceFound: false,
      formFound: false,
      serverControlsMode: false,
      ctaFound: false,
      ctaHasTecDialogAttrs: null,
      ctaLabel: '',
      bundleBuild: ''
    }
  };

  function diagLog(label, details) {
    if (!DIAG.enabled) {
      return;
    }
    var entry = {
      t: Date.now() - DIAG.startedAt,
      label: String(label || ''),
      details: details || null
    };
    DIAG.events.unshift(entry);
    if (DIAG.events.length > 20) {
      DIAG.events.length = 20;
    }
    if (window.console && typeof window.console.log === 'function') {
      window.console.log('[VMS ticketing diag]', label, details || '');
    }
  }

  function formatDiagValue(value) {
    if (value == null || value === '') {
      return '—';
    }
    if (typeof value === 'object') {
      try {
        return JSON.stringify(value);
      } catch (err) {
        return String(value);
      }
    }
    return String(value);
  }

  function ensureDiagPanel() {
    if (!DIAG.enabled || !document.body) {
      return null;
    }
    if (DIAG.panel && DIAG.panel.parentNode) {
      return DIAG.panel;
    }
    var panel = document.createElement('details');
    panel.id = 'vms-ticketing-debug-panel';
    panel.open = true;
    panel.setAttribute('style', [
      'position:fixed','right:12px','bottom:12px','z-index:999999','width:min(420px,calc(100vw - 24px))',
      'max-height:70vh','overflow:auto','background:#111','color:#f5f5f5','border:1px solid rgba(255,255,255,.2)',
      'border-radius:10px','box-shadow:0 12px 30px rgba(0,0,0,.35)','font:12px/1.45 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif'
    ].join(';'));
    panel.innerHTML = '<summary style="cursor:pointer;padding:10px 12px;font-weight:700;background:#1c1c1c;border-radius:10px 10px 0 0;">VMS Ticketing Debug</summary>' +
      '<div class="vms-ticketing-debug-body" style="padding:10px 12px;"></div>';
    document.body.appendChild(panel);
    DIAG.panel = panel;
    return panel;
  }

  function collectDiagSnapshot() {
    var bundle = window.BVMGR_TICKETING_FRONT_BUNDLE || {};
    var state = bundle.state || null;
    var source = document.querySelector('#vms-reserved-addons.vms-entitlements-block');
    var form = document.querySelector('#tribe-tickets__tickets-form') || document.querySelector('#tribe-tickets form, .tribe-tickets__tickets form, .tribe-tickets__tickets-form, form.tribe-tickets__tickets-form');
    var cta = form ? form.querySelector('#tribe-tickets__tickets-submit, .tribe-tickets__tickets-buy, button[type="submit"], input[type="submit"]') : document.querySelector('#tribe-tickets__tickets-submit, .tribe-tickets__tickets-buy');
    var footerQty = document.querySelector('.tribe-tickets__tickets-footer-quantity-number');
    var addonRows = source ? Array.prototype.slice.call(source.querySelectorAll('.vms-entitlements-list > .vms-ent-row, .vms-entitlements-list > .vms-entitlements-item')).map(function (row) {
      var labelNode = row.querySelector('.vms-ent-title, .vms-entitlements-label');
      var qtyNode = row.querySelector('.vms-addon-input');
      var plusNode = row.querySelector('.vms-addon-plus');
      var minusNode = row.querySelector('.vms-addon-minus');
      var noteNode = row.querySelector('.vms-ent-note');
      var statusNode = row.querySelector('.vms-rw-addon__status');
      return {
        label: (labelNode ? labelNode.textContent : 'Add-on').trim(),
        qty: qtyNode ? qtyNode.value : '',
        plusDisabled: !!(plusNode && plusNode.disabled),
        minusDisabled: !!(minusNode && minusNode.disabled),
        note: noteNode ? String(noteNode.textContent || '').trim() : '',
        status: statusNode ? String(statusNode.textContent || '').trim() : ''
      };
    }) : [];
    var nativeQty = form ? Array.prototype.slice.call(form.querySelectorAll('input.tribe-tickets-quantity, input.tribe-tickets__tickets-item-quantity, input.tribe-tickets__tickets-item-quantity-number-input, .tribe-tickets__item__quantity input[type="number"], .tribe-tickets__tickets-item-quantity input[type="number"]')).map(function (input) {
      var row = input.closest('[data-ticket-id], [data-product-id], .tribe-tickets__item, .tribe-tickets__tickets-item');
      return {
        name: input.name || input.id || 'ticket',
        productId: row && row.dataset ? (row.dataset.productId || row.dataset.ticketId || '') : '',
        value: input.value,
        disabled: !!input.disabled
      };
    }) : [];

    return {
      build: String(cfg.buildStamp || ''),
      admin: String(cfg.isAdminUser || ''),
      sourceFound: !!source,
      formFound: !!form,
      renderMode: source ? String(source.getAttribute('data-vms-render-mode') || '') : '',
      sourceDebugBuild: source ? String(source.getAttribute('data-vms-debug-build') || '') : '',
      sourceDebugBranch: source ? String(source.getAttribute('data-vms-debug-branch') || '') : '',
      ctaFound: !!cta,
      ctaLabel: cta ? ((cta.tagName && cta.tagName.toLowerCase() === 'input') ? String(cta.value || '') : String(cta.textContent || '').trim()) : '',
      ctaHasTecDialogAttrs: cta ? (!!cta.getAttribute('data-js') || !!cta.getAttribute('data-content')) : null,
      bundleLoaded: !!bundle.loaded,
      bundleBuild: String(bundle.buildStamp || ''),
      bootAttempts: bundle.bootAttempts || 0,
      stateActive: !!state,
      qualifyingIds: state && state.qualifyingTicketIds ? state.qualifyingTicketIds : [],
      trackedTicketQtyByProduct: state && state.ticketQtyByProduct ? state.ticketQtyByProduct : {},
      cartGaQty: state && typeof state.cartGaQty !== 'undefined' ? state.cartGaQty : '',
      cartPoolQtyByKey: state && state.cartPoolQtyByKey ? state.cartPoolQtyByKey : {},
      footerQty: footerQty ? String(footerQty.textContent || '').trim() : '',
      nativeQty: nativeQty,
      addons: addonRows,
      recentEvents: DIAG.events.slice(0, 8)
    };
  }

  function renderDiagPanel() {
    if (!DIAG.enabled) {
      return;
    }
    var panel = ensureDiagPanel();
    if (!panel) {
      return;
    }
    var body = panel.querySelector('.vms-ticketing-debug-body');
    if (!body) {
      return;
    }
    var snap = collectDiagSnapshot();
    var html = '';
    html += '<div style="margin-bottom:8px;"><strong>Build:</strong> ' + formatDiagValue(snap.build) + '</div>';
    html += '<div style="display:grid;grid-template-columns:150px 1fr;gap:4px 8px;">';
    [
      ['sourceFound', snap.sourceFound],
      ['formFound', snap.formFound],
      ['renderMode', snap.renderMode],
      ['sourceDebugBuild', snap.sourceDebugBuild],
      ['sourceDebugBranch', snap.sourceDebugBranch],
      ['ctaFound', snap.ctaFound],
      ['ctaLabel', snap.ctaLabel],
      ['ctaHasTecDialogAttrs', snap.ctaHasTecDialogAttrs],
      ['bundleLoaded', snap.bundleLoaded],
      ['bundleBuild', snap.bundleBuild],
      ['bootAttempts', snap.bootAttempts],
      ['stateActive', snap.stateActive],
      ['footerQty', snap.footerQty],
      ['trackedTicketQtyByProduct', snap.trackedTicketQtyByProduct],
      ['qualifyingIds', snap.qualifyingIds],
      ['cartGaQty', snap.cartGaQty],
      ['cartPoolQtyByKey', snap.cartPoolQtyByKey]
    ].forEach(function (pair) {
      html += '<div style="opacity:.75;">' + pair[0] + '</div><div>' + formatDiagValue(pair[1]) + '</div>';
    });
    html += '</div>';
    html += '<div style="margin-top:10px;"><strong>Native ticket qty inputs</strong></div><pre style="white-space:pre-wrap;background:#181818;padding:8px;border-radius:8px;overflow:auto;">' + formatDiagValue(snap.nativeQty) + '</pre>';
    html += '<div style="margin-top:10px;"><strong>Add-ons</strong></div><pre style="white-space:pre-wrap;background:#181818;padding:8px;border-radius:8px;overflow:auto;">' + formatDiagValue(snap.addons) + '</pre>';
    html += '<div style="margin-top:10px;"><strong>Recent events</strong></div><pre style="white-space:pre-wrap;background:#181818;padding:8px;border-radius:8px;overflow:auto;">' + formatDiagValue(snap.recentEvents) + '</pre>';
    body.innerHTML = html;
  }

  function startDiagPanel() {
    if (!DIAG.enabled) {
      return;
    }
    renderDiagPanel();
    if (!DIAG.interval) {
      DIAG.interval = window.setInterval(renderDiagPanel, 500);
    }
  }

  function configFlag(value) {
    if (value === true || value === 1) {
      return true;
    }
    var normalized = String(value == null ? '' : value).trim().toLowerCase();
    return normalized === '1' || normalized === 'true' || normalized === 'yes';
  }

  function stateBelongsToCurrentDocument(state) {
    return !!(state && state.form && state.form.ownerDocument === document && state.form.isConnected);
  }


  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function escapeRegex(value) {
    return String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  function initCartCheckoutBlockers() {
    var refreshTimer = 0;
    var requestPending = null;
    var queuedRefresh = null;
    var lastRequestStartedAt = 0;
    var lastNoticeSignature = '';
    var checkoutRefreshDebounceMs = 260;
    var checkoutRefreshMinGapMs = 320;
    var isCartMode = configFlag(cfg.isCart);
    var isCheckoutMode = configFlag(cfg.isCheckout);

    function toMessages(input) {
      var out = [];
      var seen = {};
      var list = Array.isArray(input) ? input : [];
      Array.prototype.forEach.call(list, function (message) {
        var text = String(message || '').replace(/\s+/g, ' ').trim();
        if (!text || seen[text]) {
          return;
        }
        seen[text] = true;
        out.push(text);
      });
      return out;
    }

    var state = {
      blocked: !!parseInt(String(cfg.hasCheckoutBlockers || 0), 10),
      messages: toMessages(cfg.checkoutBlockerMessages),
      nativeNoticePresent: false
    };

    function buttonSelectors() {
      return [
        '.checkout-button',
        'a.checkout-button',
        '.wc-block-cart__submit-button',
        '.wc-block-components-button.wc-block-cart__submit-button',
        '#place_order',
        'button[name="woocommerce_checkout_place_order"]',
        'input[name="woocommerce_checkout_place_order"]',
        '.wc-block-components-checkout-place-order-button',
        '.wc-block-components-button.wc-block-components-checkout-place-order-button'
      ].join(', ');
    }

    function expressSelectors() {
      return [
        '.wc-stripe-payment-request-wrapper',
        '.wc-stripe-express-checkout-element',
        '#wcpay-payment-request-wrapper',
        '.wcpay-payment-request-wrapper',
        '.wc-block-components-express-payment',
        '.wc-block-checkout__express-payment',
        '.woocommerce-payments-express-checkout',
        '.payment_request'
      ].join(', ');
    }

    function rememberButton(button) {
      if (!button || button.dataset.vmsButtonStateSaved === '1') {
        return;
      }
      button.dataset.vmsButtonStateSaved = '1';
      if (button.tagName && button.tagName.toLowerCase() === 'input') {
        button.dataset.vmsOriginalValue = String(button.value || '');
      } else {
        button.dataset.vmsOriginalText = String(button.textContent || '');
      }
    }

    function disableButton(button, label) {
      if (!button) {
        return;
      }
      rememberButton(button);
      button.disabled = true;
      button.setAttribute('disabled', 'disabled');
      button.setAttribute('aria-disabled', 'true');
      if (label) {
        if (button.tagName && button.tagName.toLowerCase() === 'input') {
          button.value = label;
        } else {
          button.textContent = label;
        }
      }
    }

    function restoreButton(button) {
      if (!button || button.dataset.vmsButtonStateSaved !== '1') {
        return;
      }
      button.disabled = false;
      button.removeAttribute('disabled');
      button.setAttribute('aria-disabled', 'false');
      if (button.tagName && button.tagName.toLowerCase() === 'input') {
        if (button.dataset.vmsOriginalValue != null) {
          button.value = button.dataset.vmsOriginalValue;
        }
      } else if (button.dataset.vmsOriginalText != null) {
        button.textContent = button.dataset.vmsOriginalText;
      }
    }

    function hideNode(node) {
      if (!node) {
        return;
      }
      if (node.dataset.vmsHiddenByVms !== '1') {
        node.dataset.vmsHiddenByVms = '1';
        node.dataset.vmsDisplaySaved = node.style ? String(node.style.display || '') : '';
        node.dataset.vmsHadHiddenAttr = node.hasAttribute('hidden') ? '1' : '0';
      }
      node.setAttribute('hidden', 'hidden');
      node.setAttribute('aria-hidden', 'true');
      if (node.style) {
        node.style.display = 'none';
      }
    }

    function restoreNode(node) {
      if (!node || node.dataset.vmsHiddenByVms !== '1') {
        return;
      }
      if (node.dataset.vmsHadHiddenAttr === '1') {
        node.setAttribute('hidden', 'hidden');
      } else {
        node.removeAttribute('hidden');
      }
      node.removeAttribute('aria-hidden');
      if (node.style) {
        node.style.display = String(node.dataset.vmsDisplaySaved || '');
      }
    }

    function noticeHost() {
      return document.querySelector('.wc-block-store-notices')
        || document.querySelector('.woocommerce-notices-wrapper')
        || document.querySelector('.wc-block-checkout')
        || document.querySelector('.wc-block-cart')
        || document.querySelector('main')
        || document.body;
    }

    function renderNotice(messages) {
      var host = noticeHost();
      if (!host) {
        return;
      }
      var signature = messages.join('|');
      var container = document.getElementById('vms-ticketing-checkout-blockers');
      if (!messages.length) {
        if (container && container.parentNode) {
          container.parentNode.removeChild(container);
        }
        lastNoticeSignature = '';
        return;
      }
      if (!container) {
        container = document.createElement('div');
        container.id = 'vms-ticketing-checkout-blockers';
        container.className = 'woocommerce-notices-wrapper vms-ticketing-checkout-blockers';
        if (host.firstChild) {
          host.insertBefore(container, host.firstChild);
        } else {
          host.appendChild(container);
        }
      }
      if (lastNoticeSignature === signature) {
        return;
      }
      var html = '<ul class="woocommerce-error" role="alert">';
      messages.forEach(function (message) {
        html += '<li>' + escapeHtml(message) + '</li>';
      });
      html += '</ul>';
      if (isCheckoutMode && cfg.cartUrl) {
        html += '<p class="vms-ticketing-checkout-blockers__actions"><a class="button wc-forward" href="' + escapeHtml(String(cfg.cartUrl || '')) + '">Return to Cart</a></p>';
      }
      container.innerHTML = html;
      lastNoticeSignature = signature;
    }

    function collectDomMessages() {
      var messages = [];
      var selectors = [
        '.woocommerce-error li',
        'ul.woocommerce-error li',
        '.woocommerce-NoticeGroup-checkout .woocommerce-error li',
        '.wc-block-components-notice-banner.is-error',
        '.wc-block-store-notices .wc-block-components-notice-banner.is-error'
      ];
      Array.prototype.forEach.call(document.querySelectorAll(selectors.join(', ')), function (node) {
        if (node && typeof node.closest === 'function' && node.closest('#vms-ticketing-checkout-blockers')) {
          return;
        }
        var text = String((node && node.textContent) || '').replace(/\s+/g, ' ').trim();
        if (text) {
          messages.push(text);
        }
      });
      return toMessages(messages);
    }

    function wireBlockedCheckoutActions() {
      if (!isCheckoutMode || !cfg.cartUrl) {
        return;
      }
      var selectors = [
        '.wc-block-checkout button',
        '.wc-block-checkout a',
        '.wc-block-components-notice-banner button',
        '.wc-block-components-notice-banner a',
        '.woocommerce-error button',
        '.woocommerce-error a'
      ];
      Array.prototype.forEach.call(document.querySelectorAll(selectors.join(', ')), function (node) {
        if (!node) {
          return;
        }
        var text = String(node.textContent || node.value || '').replace(/\s+/g, ' ').trim();
        if (!/^retry$/i.test(text)) {
          return;
        }
        if (node.dataset.vmsRetryToCartBound !== '1') {
          node.dataset.vmsRetryToCartBound = '1';
          node.addEventListener('click', function (event) {
            if (event) {
              event.preventDefault();
              event.stopPropagation();
              if (event.stopImmediatePropagation) {
                event.stopImmediatePropagation();
              }
            }
            window.location.href = String(cfg.cartUrl || '/cart/');
          }, true);
        }
        if (node.tagName && node.tagName.toLowerCase() === 'input') {
          node.value = 'Return to Cart';
        } else {
          node.textContent = 'Return to Cart';
        }
        if (typeof node.removeAttribute === 'function') {
          node.removeAttribute('disabled');
          node.setAttribute('aria-disabled', 'false');
        }
        if (node.tagName && node.tagName.toLowerCase() === 'a') {
          node.setAttribute('href', String(cfg.cartUrl || '/cart/'));
        }
      });
    }

    function applyState(blocked, messages, options) {
      var opts = options || {};
      state.blocked = !!blocked;
      state.messages = toMessages(messages);
      state.nativeNoticePresent = !!opts.nativeNoticePresent;
      if (!state.messages.length && state.blocked) {
        state.messages = ['Fix the cart issues before continuing.'];
      }

      if (document.documentElement) {
        document.documentElement.classList.toggle('vms-cart-checkout-blocked', state.blocked);
      }
      if (document.body) {
        document.body.classList.toggle('vms-cart-checkout-blocked', state.blocked);
      }

      var blockedLabel = isCheckoutMode
        ? 'Checkout blocked — return to cart'
        : 'Checkout unavailable — fix cart items above';

      Array.prototype.forEach.call(document.querySelectorAll(buttonSelectors()), function (button) {
        if (state.blocked) {
          disableButton(button, blockedLabel);
        } else {
          restoreButton(button);
        }
      });
      Array.prototype.forEach.call(document.querySelectorAll(expressSelectors()), function (node) {
        if (state.blocked) {
          hideNode(node);
        } else {
          restoreNode(node);
        }
      });
      renderNotice((state.blocked && !state.nativeNoticePresent) ? state.messages : []);
      if (state.blocked) {
        wireBlockedCheckoutActions();
      }
    }

    function normalizeRefreshOptions(options, delayMs) {
      var opts = options && typeof options === 'object' ? options : {};
      var normalizedDelay = Number.isFinite(delayMs) ? delayMs : (Number.isFinite(opts.delayMs) ? opts.delayMs : null);
      return {
        force: !!opts.force,
        recoverCheckout: !!opts.recoverCheckout,
        reason: String(opts.reason || ''),
        delayMs: normalizedDelay
      };
    }

    function mergeRefreshOptions(baseOptions, incomingOptions) {
      var base = normalizeRefreshOptions(baseOptions);
      var incoming = normalizeRefreshOptions(incomingOptions);
      var delayMs = null;

      if (Number.isFinite(base.delayMs) && Number.isFinite(incoming.delayMs)) {
        delayMs = Math.min(base.delayMs, incoming.delayMs);
      } else if (Number.isFinite(incoming.delayMs)) {
        delayMs = incoming.delayMs;
      } else if (Number.isFinite(base.delayMs)) {
        delayMs = base.delayMs;
      }

      return {
        force: base.force || incoming.force,
        recoverCheckout: base.recoverCheckout || incoming.recoverCheckout,
        reason: incoming.reason || base.reason || '',
        delayMs: delayMs
      };
    }

    function queueRefreshAfterPending(options) {
      queuedRefresh = mergeRefreshOptions(queuedRefresh, options);
    }

    function fetchServerState(options) {
      var fetchOptions = normalizeRefreshOptions(options);
      if (!cfg.cartContextUrl || !cfg.cartContextNonce) {
        if (fetchOptions.recoverCheckout) {
          recoverNativeCheckoutButton(fetchOptions.reason || 'server-state-unavailable');
        }
        return Promise.resolve(null);
      }
      if (requestPending) {
        queueRefreshAfterPending(fetchOptions);
        return requestPending;
      }
      var params = new URLSearchParams();
      params.set('nonce', String(cfg.cartContextNonce || ''));
      lastRequestStartedAt = Date.now();
      requestPending = fetch(String(cfg.cartContextUrl || '') + '&' + params.toString(), {
        method: 'GET',
        credentials: 'same-origin'
      }).then(function (response) {
        return response.json();
      }).then(function (payload) {
        if (!(payload && payload.success && payload.data)) {
          return null;
        }
        var blocked = !!parseInt(String(payload.data.has_checkout_blockers || 0), 10);
        var messages = toMessages(payload.data.checkout_blocker_messages);

        // VMS should only hard-disable checkout for VMS-owned cart blockers returned by
        // the server. Native Woo/Turnstile validation notices are allowed to display,
        // but they must not become persistent VMS blockers after the customer corrects
        // the field/challenge.
        applyState(blocked, messages, { nativeNoticePresent: false });
        if (!blocked) {
          recoverNativeCheckoutButton('server-unblocked');
        }
        return null;
      }).catch(function () {
        return null;
      }).finally(function () {
        requestPending = null;
        if (queuedRefresh) {
          var followUp = queuedRefresh;
          queuedRefresh = null;
          scheduleRefresh(followUp.delayMs, followUp);
        }
      });
      if (!fetchOptions.recoverCheckout) {
        return requestPending;
      }
      return requestPending.then(function (result) {
        recoverNativeCheckoutButton(fetchOptions.reason || 'server-refresh');
        return result;
      });
    }

    function recoverNativeCheckoutButton(reason) {
      if (state.blocked || !isCheckoutMode) {
        return;
      }
      Array.prototype.forEach.call(document.querySelectorAll(buttonSelectors()), function (button) {
        if (!button) {
          return;
        }
        var text = String(button.textContent || button.value || '').replace(/\s+/g, ' ').trim();
        var looksLikeNativeProcessing = /^processing/i.test(text) || /^please wait/i.test(text);
        var savedByVms = button.dataset && button.dataset.vmsButtonStateSaved === '1';
        if (savedByVms) {
          restoreButton(button);
          return;
        }
        if (button.disabled || button.hasAttribute('disabled') || button.getAttribute('aria-disabled') === 'true' || looksLikeNativeProcessing) {
          button.disabled = false;
          button.removeAttribute('disabled');
          button.setAttribute('aria-disabled', 'false');
          if (button.dataset) {
            button.dataset.vmsCheckoutRecovery = String(reason || 'recovered');
          }
        }
      });
      var form = document.querySelector('form.checkout');
      if (form && form.classList) {
        form.classList.remove('processing');
      }
    }

    function cartLooksBusy() {
      if (!isCartMode) {
        return false;
      }
      var busySelectors = [
        '.woocommerce-cart-form.processing',
        '.cart_totals.processing',
        '.woocommerce .blockUI',
        '.woocommerce .blockOverlay',
        '.wc-block-cart[aria-busy="true"]',
        '.wp-block-woocommerce-cart[aria-busy="true"]',
        '.wp-block-woocommerce-cart .is-loading',
        '.wp-block-woocommerce-cart .wc-block-components-spinner',
        '.wc-block-cart .wc-block-components-spinner',
        '.wc-block-components-loading-mask'
      ];
      return !!document.querySelector(busySelectors.join(', '));
    }

    function scheduleRefresh(delayMs, options) {
      if (refreshTimer) {
        window.clearTimeout(refreshTimer);
      }
      var delay = Number.isFinite(delayMs) ? delayMs : (isCartMode ? 1200 : checkoutRefreshDebounceMs);
      var refreshOptions = normalizeRefreshOptions(options, delay);
      if (requestPending) {
        queueRefreshAfterPending(refreshOptions);
        return;
      }
      refreshTimer = window.setTimeout(function () {
        refreshTimer = 0;
        if (isCartMode && cartLooksBusy()) {
          scheduleRefresh(650, refreshOptions);
          return;
        }
        if (isCheckoutMode && !refreshOptions.force && lastRequestStartedAt > 0) {
          var elapsed = Date.now() - lastRequestStartedAt;
          if (elapsed < checkoutRefreshMinGapMs) {
            scheduleRefresh(checkoutRefreshMinGapMs - elapsed, refreshOptions);
            return;
          }
        }
        if (requestPending) {
          queueRefreshAfterPending(refreshOptions);
          return;
        }
        fetchServerState(refreshOptions);
      }, delay);
    }

    function bindCartBlockerRefreshEvents() {
      if (!isCartMode || !document.body || document.body.dataset.vmsCartBlockerRefreshBound === '1') {
        return;
      }
      document.body.dataset.vmsCartBlockerRefreshBound = '1';

      var qtySelectors = [
        'input.qty',
        'input[name^="cart["]',
        '.wc-block-components-quantity-selector__input',
        '.wc-block-components-quantity-selector__button',
        '.wc-block-cart-item__remove-link',
        '.product-remove a.remove',
        'button[name="update_cart"]'
      ].join(', ');

      ['change', 'click'].forEach(function (eventName) {
        document.body.addEventListener(eventName, function (event) {
          var target = event && event.target;
          if (!target || typeof target.closest !== 'function') {
            return;
          }
          if (target.closest(qtySelectors)) {
            scheduleRefresh(1400);
          }
        }, true);
      });

      if (window.jQuery) {
        window.jQuery(document.body).on('updated_wc_div updated_cart_totals wc_fragments_refreshed removed_from_cart added_to_cart', function () {
          scheduleRefresh(350);
        });
      }
    }

    function initClassicCartQuantityStabilizer() {
      if (!isCartMode || !document.body || document.body.dataset.vmsCartQtyStabilizerBound === '1') {
        return;
      }

      var cartForm = document.querySelector('form.woocommerce-cart-form');
      if (!cartForm) {
        return;
      }

      document.body.dataset.vmsCartQtyStabilizerBound = '1';

      var desiredByKey = {};
      var submitTimer = 0;
      var reconcileTimer = 0;
      var lastSubmitAt = 0;
      var suppressQuantityEvents = false;
      var maxAgeMs = 18000;

      function cartQuantityInputSelector() {
        return 'form.woocommerce-cart-form input.qty, form.woocommerce-cart-form input[name^="cart["][name$="[qty]"]';
      }

      function updateButton() {
        return document.querySelector('form.woocommerce-cart-form button[name="update_cart"], form.woocommerce-cart-form input[name="update_cart"]');
      }

      function quantityKey(input) {
        if (!input) {
          return '';
        }
        var name = String(input.getAttribute('name') || '');
        if (name !== '') {
          return name;
        }
        var row = typeof input.closest === 'function' ? input.closest('.cart_item, tr, .woocommerce-cart-form') : null;
        if (row) {
          var remove = row.querySelector('a.remove[data-cart_item_key], a.remove');
          if (remove) {
            return String(remove.getAttribute('data-cart_item_key') || remove.getAttribute('href') || '');
          }
        }
        return String(input.id || input.dataset && input.dataset.product_id || '');
      }

      function normalizeQtyValue(input) {
        var raw = String(input && input.value != null ? input.value : '').trim();
        var parsed = parseInt(raw, 10);
        if (!Number.isFinite(parsed) || parsed < 0) {
          parsed = 0;
        }
        return String(parsed);
      }

      function rememberDesired(input) {
        var key = quantityKey(input);
        if (!key) {
          return;
        }
        desiredByKey[key] = {
          value: normalizeQtyValue(input),
          ts: Date.now(),
          attempts: desiredByKey[key] && desiredByKey[key].attempts ? desiredByKey[key].attempts : 0
        };
      }

      function activeDesiredKeys() {
        var now = Date.now();
        return Object.keys(desiredByKey).filter(function (key) {
          var item = desiredByKey[key];
          if (!item || (now - item.ts) > maxAgeMs || item.attempts > 4) {
            delete desiredByKey[key];
            return false;
          }
          return true;
        });
      }

      function enableUpdateButton(button) {
        if (!button) {
          return;
        }
        button.disabled = false;
        button.removeAttribute('disabled');
        button.setAttribute('aria-disabled', 'false');
        if (button.classList) {
          button.classList.remove('disabled');
        }
      }

      function triggerInputEvents(input) {
        if (!input) {
          return;
        }
        suppressQuantityEvents = true;
        try {
          input.dispatchEvent(new Event('input', { bubbles: true }));
          input.dispatchEvent(new Event('change', { bubbles: true }));
        } catch (err) {
          // IE-style fallback is only here to avoid breaking older embedded browsers.
          var changeEvent = document.createEvent('HTMLEvents');
          changeEvent.initEvent('change', true, false);
          input.dispatchEvent(changeEvent);
        } finally {
          suppressQuantityEvents = false;
        }
      }

      function applyDesiredValues() {
        var mismatch = false;
        activeDesiredKeys();
        Array.prototype.forEach.call(document.querySelectorAll(cartQuantityInputSelector()), function (input) {
          var key = quantityKey(input);
          var desired = key ? desiredByKey[key] : null;
          if (!desired) {
            return;
          }
          var current = normalizeQtyValue(input);
          if (current !== String(desired.value)) {
            input.value = String(desired.value);
            triggerInputEvents(input);
            mismatch = true;
          }
        });
        return mismatch;
      }

      function clearMatchedDesiredValues() {
        Array.prototype.forEach.call(document.querySelectorAll(cartQuantityInputSelector()), function (input) {
          var key = quantityKey(input);
          var desired = key ? desiredByKey[key] : null;
          if (desired && normalizeQtyValue(input) === String(desired.value)) {
            delete desiredByKey[key];
          }
        });
      }

      function submitDesiredCartUpdate(delayMs) {
        if (submitTimer) {
          window.clearTimeout(submitTimer);
        }
        submitTimer = window.setTimeout(function () {
          submitTimer = 0;
          if (!activeDesiredKeys().length) {
            return;
          }
          if (cartLooksBusy()) {
            submitDesiredCartUpdate(500);
            return;
          }
          applyDesiredValues();
          activeDesiredKeys().forEach(function (key) {
            if (desiredByKey[key]) {
              desiredByKey[key].attempts += 1;
            }
          });
          var button = updateButton();
          if (!button) {
            return;
          }
          enableUpdateButton(button);
          lastSubmitAt = Date.now();
          button.click();
          scheduleRefresh(1800);
        }, Number.isFinite(delayMs) ? delayMs : 650);
      }

      function reconcileCartQuantities(delayMs) {
        if (reconcileTimer) {
          window.clearTimeout(reconcileTimer);
        }
        reconcileTimer = window.setTimeout(function () {
          reconcileTimer = 0;
          if (!activeDesiredKeys().length) {
            return;
          }
          if (cartLooksBusy()) {
            reconcileCartQuantities(450);
            return;
          }
          var hadMismatch = applyDesiredValues();
          if (hadMismatch) {
            submitDesiredCartUpdate(250);
            return;
          }
          // Keep the intended value around long enough to survive a slow stale
          // response from an earlier quantity request. It will expire naturally.
          if (Date.now() - lastSubmitAt > 5000) {
            clearMatchedDesiredValues();
          }
        }, Number.isFinite(delayMs) ? delayMs : 250);
      }

      ['input', 'change'].forEach(function (eventName) {
        document.body.addEventListener(eventName, function (event) {
          if (suppressQuantityEvents) {
            return;
          }
          var target = event && event.target;
          if (!target || typeof target.closest !== 'function') {
            return;
          }
          var input = target.closest(cartQuantityInputSelector());
          if (!input) {
            return;
          }
          rememberDesired(input);

          // Many themes auto-submit classic cart quantity changes immediately. Do
          // not block the native Woo/theme listener; instead remember the final
          // customer-entered value and reconcile if an older AJAX response snaps
          // the field back. This avoids making the quantity controls feel stuck.
          submitDesiredCartUpdate(900);
        }, true);
      });

      document.body.addEventListener('click', function (event) {
        var target = event && event.target;
        if (!target || typeof target.closest !== 'function') {
          return;
        }
        if (!target.closest('form.woocommerce-cart-form')) {
          return;
        }
        if (!target.closest('.quantity .plus, .quantity .minus, .plus, .minus, [data-quantity-plus], [data-quantity-minus]')) {
          return;
        }
        window.setTimeout(function () {
          Array.prototype.forEach.call(document.querySelectorAll(cartQuantityInputSelector()), rememberDesired);
          submitDesiredCartUpdate(900);
        }, 80);
      }, false);

      if (window.jQuery) {
        window.jQuery(document.body).on('updated_wc_div updated_cart_totals wc_fragments_refreshed removed_from_cart added_to_cart', function () {
          reconcileCartQuantities(220);
        });
      }
    }

    function scheduleCheckoutRecovery(reason) {
      scheduleRefresh(checkoutRefreshDebounceMs, {
        force: reason === 'woo-event',
        recoverCheckout: true,
        reason: reason
      });
    }

    function isCheckoutRefreshTarget(target, eventName) {
      if (!target || typeof target.closest !== 'function') {
        return false;
      }
      if (target.closest('.cf-turnstile') || target.closest('[name="cf-turnstile-response"]')) {
        return true;
      }

      var field = target.closest('form.checkout input, form.checkout select, form.checkout textarea');
      if (!field) {
        return false;
      }

      if (eventName !== 'input') {
        return true;
      }

      var tagName = String(field.tagName || '').toLowerCase();
      if (tagName === 'textarea') {
        return true;
      }

      var fieldType = String(field.getAttribute('type') || '').toLowerCase();
      return ['text', 'email', 'tel', 'number', 'password', 'search', 'url'].indexOf(fieldType) !== -1;
    }

    function bindCheckoutRecoveryEvents() {
      if (!isCheckoutMode || !document.body || document.body.dataset.vmsCheckoutRecoveryBound === '1') {
        return;
      }
      document.body.dataset.vmsCheckoutRecoveryBound = '1';
      ['change', 'input'].forEach(function (eventName) {
        document.body.addEventListener(eventName, function (event) {
          var target = event && event.target;
          if (isCheckoutRefreshTarget(target, eventName)) {
            scheduleCheckoutRecovery(eventName);
          }
        }, true);
      });
      if (window.jQuery) {
        window.jQuery(document.body).on('updated_checkout checkout_error', function () {
          scheduleCheckoutRecovery('woo-event');
        });
      }
    }

    function boot() {
      applyState(state.blocked, state.messages, { nativeNoticePresent: false });
      if (isCartMode) {
        bindCartBlockerRefreshEvents();
        initClassicCartQuantityStabilizer();
        return;
      }
      fetchServerState();
      bindCheckoutRecoveryEvents();
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', boot);
    } else {
      boot();
    }

    window.addEventListener('beforeunload', function () {
      if (refreshTimer) {
        window.clearTimeout(refreshTimer);
      }
    });
  }

  syncMyTicketsNotice();

  if (configFlag(cfg.isCart) || configFlag(cfg.isCheckout)) {
    clearLegacyPendingState();
    initCartCheckoutBlockers();
    return;
  }

  var incomingBuildStamp = String(cfg.buildStamp || '');
  startDiagPanel();
  diagLog('bundle-start', { build: incomingBuildStamp || String(cfg.buildStamp || '') });

  var existingBundle = window.BVMGR_TICKETING_FRONT_BUNDLE || {};
  if (existingBundle.loaded
      && (!incomingBuildStamp || String(existingBundle.buildStamp || '') === incomingBuildStamp)
      && stateBelongsToCurrentDocument(existingBundle.state)) {
    return;
  }
  if (existingBundle.observer && typeof existingBundle.observer.disconnect === 'function') {
    try {
      existingBundle.observer.disconnect();
    } catch (disconnectErr) {
      // ignore
    }
  }
  window.BVMGR_TICKETING_FRONT_BUNDLE = {
    loaded: true,
    buildStamp: incomingBuildStamp,
    previousBuildStamp: String(existingBundle.buildStamp || ''),
    state: null,
    bootTimer: 0,
    bootAttempts: 0,
    observer: null
  };
  DIAG.flags.bundleLoaded = true;
  DIAG.flags.bundleBuild = incomingBuildStamp;
  renderDiagPanel();

  var SELECTORS = {
    addonSource: '#vms-reserved-addons.vms-entitlements-block',
    form: '#tribe-tickets form, .tribe-tickets__tickets form, .tribe-tickets__tickets-form, form.tribe-tickets__tickets-form',
    footer: '.tribe-tickets__footer, .tribe-tickets__tickets-footer, .tribe-common-c-btn-group',
    submit: '#tribe-tickets__tickets-submit, .tribe-tickets__tickets-buy, button[type="submit"], input[type="submit"]',
    nativeQty: [
      'input.tribe-tickets-quantity',
      'input.tribe-tickets__tickets-item-quantity',
      'input.tribe-tickets__tickets-item-quantity-number-input',
      '.tribe-tickets__item__quantity input[type="number"]',
      '.tribe-tickets__tickets-item-quantity input[type="number"]'
    ].join(', '),
    nativeQtyButtons: [
      '.tribe-tickets__item__quantity__add',
      '.tribe-tickets__item__quantity__remove',
      '.tribe-tickets__tickets-item-quantity-add',
      '.tribe-tickets__tickets-item-quantity-remove'
    ].join(', ')
  };
  var LEGACY_PENDING_STORAGE_KEYS = ['vms_addons_pending_v1', 'vms_addons_pending_terminal_v1'];

  function toInt(value, fallback) {
    var parsed = parseInt(String(value == null ? '' : value), 10);
    return Number.isFinite(parsed) ? parsed : (fallback || 0);
  }

  function toFloat(value, fallback) {
    var parsed = parseFloat(String(value == null ? '' : value));
    return Number.isFinite(parsed) ? parsed : (fallback || 0);
  }

  function clamp(value, min, max) {
    var next = toInt(value, 0);
    if (Number.isFinite(min)) {
      next = Math.max(min, next);
    }
    if (Number.isFinite(max)) {
      next = Math.min(max, next);
    }
    return next;
  }

  function safeParseJson(raw, fallback) {
    try {
      var parsed = JSON.parse(String(raw || ''));
      return parsed && typeof parsed === 'object' ? parsed : fallback;
    } catch (err) {
      return fallback;
    }
  }

  function uniqueInts(list) {
    var seen = {};
    return (Array.isArray(list) ? list : []).map(function (value) {
      return toInt(value, 0);
    }).filter(function (value) {
      if (value <= 0 || seen[value]) {
        diagLog('init-server-controls-failed');
      renderDiagPanel();
      return false;
      }
      seen[value] = true;
      return true;
    });
  }

  function query(selector, root) {
    return (root || document).querySelector(selector);
  }

  function queryAll(selector, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(selector));
  }

  function setDisabled(node, disabled) {
    if (!node) {
      return;
    }
    node.disabled = !!disabled;
    if (disabled) {
      node.setAttribute('disabled', 'disabled');
      node.setAttribute('aria-disabled', 'true');
    } else {
      node.removeAttribute('disabled');
      node.setAttribute('aria-disabled', 'false');
    }
  }

  function decodeDisplayText(value) {
    var text = String(value == null ? '' : value);
    if (text.indexOf('&') < 0) {
      return text;
    }
    var textarea = document.createElement('textarea');
    textarea.innerHTML = text;
    return textarea.value;
  }

  function setText(node, text) {
    var next = decodeDisplayText(text || '');
    if (node && node.textContent !== next) {
      node.textContent = next;
    }
  }

  function normalizeKey(value) {
    return String(value || '')
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9_]+/g, '_');
  }

  function normalizeEmail(value) {
    return String(value || '').trim().toLowerCase();
  }

  function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim());
  }

  function mapObject(value) {
    return value && typeof value === 'object' ? value : {};
  }

  function ticketAccessMap(state) {
    return mapObject(state && state.ticketAccessMap ? state.ticketAccessMap : cfg.ticketAccessMap);
  }

  function ticketRemainingMap(state) {
    return mapObject(state && state.ticketRemainingMap ? state.ticketRemainingMap : cfg.ticketRemainingMap);
  }

  function disabledTicketMap(state) {
    return mapObject(state && state.disabledTicketMap ? state.disabledTicketMap : cfg.disabledTicketMap);
  }

  function isDisabledPendingSyncTicketProductId(state, productId) {
    var id = toInt(productId, 0);
    if (id <= 0) {
      return false;
    }
    var map = disabledTicketMap(state);
    return !!(map && map[String(id)]);
  }

  function disableNativeTicketRow(row, input) {
    if (input) {
      if (String(input.value || '') !== '0') {
        input.value = '0';
        input.setAttribute('value', '0');
      }
      setDisabled(input, true);
      input.setAttribute('data-vms-disabled-pending-sync', '1');
    }
    if (!row) {
      return;
    }
    row.hidden = true;
    row.setAttribute('hidden', 'hidden');
    row.setAttribute('aria-hidden', 'true');
    row.setAttribute('data-vms-disabled-pending-sync', '1');
    if (row.classList) {
      row.classList.add('vms-ticket-disabled-pending-sync');
    }
    queryAll('button, input, select, textarea', row).forEach(function (control) {
      if (control !== input) {
        setDisabled(control, true);
      }
      control.setAttribute('data-vms-disabled-pending-sync', '1');
    });
  }

  function hideDisabledTicketRows(state) {
    if (!state || !state.form) {
      return 0;
    }
    var hiddenCount = 0;
    queryAll(SELECTORS.nativeQty, state.form).forEach(function (input) {
      var productId = resolveTicketProductId(state, input);
      if (!isDisabledPendingSyncTicketProductId(state, productId)) {
        return;
      }
      disableNativeTicketRow(getTicketRow(input), input);
      hiddenCount += 1;
    });
    state.suppressedDisabledTicketCount = hiddenCount;
    return hiddenCount;
  }

  function resolveTicketProductId(state, input) {
    var productId = inferProductId(input);
    if (productId > 0) {
      return productId;
    }

    var form = state && state.form ? state.form : document;
    var nativeInputs = queryAll(SELECTORS.nativeQty, form);
    var knownTicketIds = uniqueInts(Object.keys(ticketAccessMap(state)));
    if (knownTicketIds.length === 1 && nativeInputs.length === 1) {
      return knownTicketIds[0];
    }

    if (state && state.qualifyingTicketIds && state.qualifyingTicketIds.length === 1 && nativeInputs.length === 1) {
      return state.qualifyingTicketIds[0];
    }

    if (state && state.gaProductId > 0 && nativeInputs.length === 1) {
      return state.gaProductId;
    }

    return 0;
  }

  function getTicketAccessEntry(state, productId) {
    var access = ticketAccessMap(state)[String(productId || '')];
    return access && typeof access === 'object' ? access : null;
  }

  function getTicketRemainingEntry(state, productId) {
    var remaining = ticketRemainingMap(state)[String(productId || '')];
    return remaining && typeof remaining === 'object' ? remaining : null;
  }

  function getTicketRuleProductId(productId, access) {
    var id = toInt(access && access.woo_product_id, 0);
    return id > 0 ? id : toInt(productId, 0);
  }

  function ticketCountsTowardUnlock(access) {
    if (!access || typeof access !== 'object') {
      return true;
    }
    if (Object.prototype.hasOwnProperty.call(access, 'counts_toward_unlock')) {
      return toInt(access.counts_toward_unlock, 1) > 0;
    }
    return true;
  }

  function ticketRatioRule(access) {
    if (!access || typeof access !== 'object') {
      return null;
    }
    var enabled = toInt(access.ratio_rule_enabled, 0) > 0;
    var maxPer = Math.max(0, toInt(access.ratio_rule_max_per_qualifying, 0));
    if (!enabled || maxPer <= 0) {
      return null;
    }
    return {
      maxPerQualifying: maxPer,
      group: normalizeKey(access.ratio_rule_group || ''),
      ticketKey: normalizeKey(access.ticket_key || '')
    };
  }

  function ticketRatioGroupKey(productId, access) {
    var rule = ticketRatioRule(access);
    if (!rule) {
      return '';
    }
    return rule.group ? ('shared:' + rule.group) : ('ticket:' + (rule.ticketKey || String(productId || '')));
  }

  function ticketDisplayLabelFromAccess(access, fallback) {
    var candidates = [
      access && access.display_label,
      access && access.label,
      fallback
    ];
    for (var i = 0; i < candidates.length; i += 1) {
      var text = decodeDisplayText(candidates[i] || '').replace(/\s+/g, ' ').trim();
      if (text) {
        return text;
      }
    }
    return 'ticket';
  }

  function pluralizeTicketLabel(label, count) {
    var value = decodeDisplayText(label || 'ticket').replace(/\s+/g, ' ').trim() || 'ticket';
    if (Math.max(0, toInt(count, 0)) === 1 || /s$/i.test(value)) {
      return value;
    }
    return value + 's';
  }

  function findTicketQtyButtons(row) {
    var out = { plus: [], minus: [] };
    if (!row) {
      return out;
    }
    queryAll(SELECTORS.nativeQtyButtons, row).forEach(function (button) {
      var text = decodeDisplayText(button.textContent || '').replace(/\s+/g, ' ').trim();
      var haystack = [button.className || '', button.getAttribute('aria-label') || '', button.getAttribute('title') || '', text].join(' ').toLowerCase();
      if (/add|plus|increase|increment/.test(haystack) || text === '+') {
        out.plus.push(button);
      } else if (/remove|minus|decrease|decrement/.test(haystack) || text === '−' || text === '-') {
        out.minus.push(button);
      }
    });
    return out;
  }

  function ensureTicketRuleNote(row) {
    if (!row) {
      return null;
    }
    var note = query('.vms-ticket-ratio-rule-note', row);
    if (note) {
      return note;
    }
    note = createEl('div', 'vms-ticket-ratio-rule-note');
    note.setAttribute('aria-live', 'polite');
    note.setAttribute('aria-atomic', 'true');
    var anchor = query('.tribe-tickets__tickets-item-content-description, .tribe-tickets__item__description, .vms-ticket-qualified-description', row)
      || query('.tribe-tickets__tickets-item-content-title-container, .tribe-tickets__item__content, .tribe-tickets__item__header', row)
      || row;
    if (anchor === row) {
      row.appendChild(note);
    } else if (anchor.parentNode) {
      anchor.parentNode.insertBefore(note, anchor.nextSibling);
    } else {
      row.appendChild(note);
    }
    return note;
  }

  function collectTicketRatioRows(state) {
    var rows = [];
    if (!state || !state.form) {
      return rows;
    }
    queryAll(SELECTORS.nativeQty, state.form).forEach(function (input) {
      var productId = resolveTicketProductId(state, input);
      var row = getTicketRow(input);
      if (productId <= 0 || isDisabledPendingSyncTicketProductId(state, productId) || input.disabled || (row && row.hidden)) {
        return;
      }
      var access = getTicketAccessEntry(state, productId);
      var rule = ticketRatioRule(access);
      rows.push({
        input: input,
        row: row,
        productId: getTicketRuleProductId(productId, access),
        access: access,
        rule: rule,
        groupKey: rule ? ticketRatioGroupKey(productId, access) : '',
        qty: Math.max(0, readTicketQty(input)),
        countsTowardUnlock: ticketCountsTowardUnlock(access)
      });
    });
    return rows;
  }

  function buildTicketRatioGroups(state, rows) {
    var groups = {};
    (rows || []).forEach(function (item) {
      if (!item.rule || !item.groupKey) {
        return;
      }
      if (!groups[item.groupKey]) {
        groups[item.groupKey] = {
          groupKey: item.groupKey,
          maxPerQualifying: item.rule.maxPerQualifying,
          limitedProductIds: {},
          selectedQty: 0,
          qualifyingQty: Math.max(0, toInt(state && state.cartGaQty, 0)) + (state && state.qualifyingTicketIds && state.qualifyingTicketIds.length ? selectedQualifyingQty(state) : 0),
          allowedQty: 0
        };
      }
      groups[item.groupKey].limitedProductIds[String(item.productId)] = true;
      groups[item.groupKey].selectedQty += Math.max(0, item.qty || 0);
      groups[item.groupKey].maxPerQualifying = Math.min(groups[item.groupKey].maxPerQualifying, item.rule.maxPerQualifying);
    });

    Object.keys(groups).forEach(function (groupKey) {
      var group = groups[groupKey];
      if (!(state && state.qualifyingTicketIds && state.qualifyingTicketIds.length)) {
        (rows || []).forEach(function (item) {
          if (!item || !item.countsTowardUnlock || item.qty <= 0) {
            return;
          }
          if (group.limitedProductIds[String(item.productId)]) {
            return;
          }
          group.qualifyingQty += Math.max(0, item.qty || 0);
        });
      }
      group.allowedQty = Math.max(0, group.qualifyingQty * group.maxPerQualifying);
    });

    return groups;
  }

  function ticketRatioNoteText(item, group) {
    if (!item || !item.rule || !group) {
      return '';
    }
    var ticketLabel = ticketDisplayLabelFromAccess(item.access, 'ticket');
    var qualifierLabel = decodeDisplayText(cfg.ticketRatioQualifyingLabel || 'qualifying tickets').replace(/\s+/g, ' ').trim() || 'qualifying tickets';
    var qualifyingQty = Math.max(0, toInt(group.qualifyingQty, 0));
    var allowedQty = Math.max(0, toInt(group.allowedQty, 0));
    var maxPer = Math.max(1, toInt(group.maxPerQualifying, 1));
    if (qualifyingQty <= 0 || allowedQty <= 0) {
      return maxPer === 1
        ? 'Requires 1 ' + qualifierLabel + ' for each ' + ticketLabel + ' • You have 0.'
        : 'Requires ' + qualifierLabel + ' first • Up to ' + String(maxPer) + ' ' + pluralizeTicketLabel(ticketLabel, maxPer) + ' per ' + qualifierLabel + '.';
    }
    if (group.selectedQty > allowedQty) {
      return 'Limited to ' + String(allowedQty) + ' ' + pluralizeTicketLabel(ticketLabel, allowedQty) + ' with your current ' + qualifierLabel + ' quantity.';
    }
    return 'Up to ' + String(allowedQty) + ' ' + pluralizeTicketLabel(ticketLabel, allowedQty) + ' allowed with your current ' + qualifierLabel + ' quantity.';
  }

  function syncTicketRatioRuleUi(state) {
    if (!state || !state.form) {
      return;
    }

    var rows = collectTicketRatioRows(state);
    var groups = buildTicketRatioGroups(state, rows);
    var changed = false;

    rows.forEach(function (item) {
      if (!item.rule || !item.groupKey) {
        if (item.row && item.row.classList) {
          item.row.classList.remove('vms-ticket-ratio-limited', 'vms-ticket-ratio-blocked');
        }
        var oldNote = item.row ? query('.vms-ticket-ratio-rule-note', item.row) : null;
        if (oldNote) {
          oldNote.hidden = true;
          oldNote.textContent = '';
        }
        return;
      }
      var group = groups[item.groupKey];
      var otherLimitedQty = Math.max(0, toInt(group && group.selectedQty, 0) - Math.max(0, item.qty || 0));
      var maxForInput = Math.max(0, toInt(group && group.allowedQty, 0) - otherLimitedQty);
      var currentQty = Math.max(0, readTicketQty(item.input));
      var clampedQty = Math.min(currentQty, maxForInput);
      if (currentQty !== clampedQty) {
        item.input.value = String(clampedQty);
        item.input.setAttribute('value', String(clampedQty));
        item.qty = clampedQty;
        changed = true;
      }
      item.input.max = String(maxForInput);
      item.input.setAttribute('max', String(maxForInput));

      var note = ensureTicketRuleNote(item.row);
      if (note) {
        var message = ticketRatioNoteText(item, group);
        setText(note, message);
        note.hidden = message === '';
        note.classList.toggle('vms-ticket-ratio-rule-note--blocked', maxForInput <= 0 || currentQty > maxForInput || toInt(group && group.qualifyingQty, 0) <= 0);
      }
      if (item.row && item.row.classList) {
        item.row.classList.add('vms-ticket-ratio-limited');
        item.row.classList.toggle('vms-ticket-ratio-blocked', maxForInput <= 0);
      }

      var buttons = findTicketQtyButtons(item.row);
      buttons.plus.forEach(function (button) {
        setDisabled(button, maxForInput <= clampedQty || state.isSubmitting);
        button.setAttribute('aria-disabled', (maxForInput <= clampedQty || state.isSubmitting) ? 'true' : 'false');
      });
      buttons.minus.forEach(function (button) {
        setDisabled(button, clampedQty <= 0 || state.isSubmitting);
        button.setAttribute('aria-disabled', (clampedQty <= 0 || state.isSubmitting) ? 'true' : 'false');
      });
    });

    if (changed) {
      syncTrackedTicketQty(state);
    }
  }

  function ticketSaleDeadlineText(access) {
    if (!access || toInt(access.sale_active, 0) <= 0) {
      return '';
    }

    var qtyText = decodeDisplayText(access.sale_quantity_text || '').replace(/\s+/g, ' ').trim();
    var raw = String(access.early_price_end || '').trim();
    var dateText = '';
    if (raw) {
      var normalized = raw.replace(' ', 'T');
      var date = new Date(normalized);
      if (isNaN(date.getTime()) && /^\d{4}-\d{2}-\d{2}$/.test(raw)) {
        date = new Date(raw + 'T23:59:00');
      }
      if (isNaN(date.getTime())) {
        dateText = raw;
      } else {
        var opts = { month: 'short', day: 'numeric' };
        var now = new Date();
        if (date.getFullYear() !== now.getFullYear()) {
          opts.year = 'numeric';
        }
        dateText = date.toLocaleDateString(undefined, opts);
        var hours = date.getHours();
        var minutes = date.getMinutes();
        if (hours !== 23 || minutes !== 59) {
          dateText += ' at ' + date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
        }
      }
    }

    if (qtyText && dateText) {
      return qtyText + ' • Ends ' + dateText;
    }
    if (qtyText) {
      return qtyText;
    }
    if (dateText) {
      return 'Sale ends ' + dateText;
    }
    return '';
  }

  function ticketPriceDisplayNodes(priceWrap) {
    if (!priceWrap) {
      return [];
    }

    var nodes = [];
    Array.prototype.forEach.call(priceWrap.children || [], function (child) {
      if (!child || child.classList && (child.classList.contains('vms-ticket-sale-badge') || child.classList.contains('vms-ticket-sale-deadline'))) {
        return;
      }
      var text = decodeDisplayText(child.textContent || '').replace(/\s+/g, ' ').trim();
      if (text && /\d/.test(text) && (/[$£€]/.test(text) || /\d+(?:\.\d{2})?/.test(text))) {
        nodes.push(child);
      }
    });

    if (nodes.length >= 2) {
      return nodes;
    }

    queryAll('.woocommerce-Price-amount, .tribe-amount, .amount', priceWrap).forEach(function (node) {
      if (nodes.indexOf(node) >= 0) {
        return;
      }
      var text = decodeDisplayText(node.textContent || '').replace(/\s+/g, ' ').trim();
      if (text && /\d/.test(text)) {
        nodes.push(node);
      }
    });

    return nodes;
  }

  function annotateTicketSalePriceUi(row, active) {
    if (!row) {
      return;
    }

    var priceWrap = query('.tribe-tickets__tickets-item-extra-price, .tribe-tickets__item__price, .tribe-tickets__tickets-item-price, .tribe-common-b3', row);
    if (!priceWrap) {
      return;
    }

    priceWrap.classList.toggle('vms-ticket-price-sale-active', !!active);
    queryAll('.vms-ticket-regular-price, .vms-ticket-sale-price', priceWrap).forEach(function (node) {
      node.classList.remove('vms-ticket-regular-price');
      node.classList.remove('vms-ticket-sale-price');
    });

    if (!active) {
      return;
    }

    var regularNodes = queryAll('del, s, strike', priceWrap);
    var saleNodes = queryAll('ins', priceWrap);

    regularNodes.forEach(function (node) {
      node.classList.add('vms-ticket-regular-price');
    });
    saleNodes.forEach(function (node) {
      node.classList.add('vms-ticket-sale-price');
    });

    if (saleNodes.length > 0) {
      return;
    }

    var displayNodes = ticketPriceDisplayNodes(priceWrap);
    if (displayNodes.length >= 2) {
      displayNodes[0].classList.add('vms-ticket-regular-price');
      displayNodes[displayNodes.length - 1].classList.add('vms-ticket-sale-price');
    }
  }

  function ensureTicketSaleUi(row, access) {
    if (!row || !access) {
      return;
    }
    var active = toInt(access.sale_active, 0) > 0;
    row.classList.toggle('vms-ticket-sale-active', active);
    annotateTicketSalePriceUi(row, active);
    var titleWrap = query('.tribe-tickets__tickets-item-content-title-container, .tribe-tickets__item__content, .tribe-tickets__item__header', row) || row;
    var titleNode = query('.tribe-tickets__tickets-item-content-title, .tribe-tickets__tickets-item-title, .tribe-tickets__item__title, h1, h2, h3, h4', row);
    var titleParent = titleNode && titleNode.parentNode ? titleNode.parentNode : titleWrap;
    var badge = query('[data-vms-sale-badge="1"], .vms-ticket-sale-badge', row);

    if (!badge) {
      queryAll('span, div, small, strong', row).some(function (node) {
        if (decodeDisplayText(node.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase() === 'on sale') {
          badge = node;
          return true;
        }
        return false;
      });
    }

    if (!active) {
      if (badge && badge.getAttribute('data-vms-created-sale-badge') === '1' && badge.parentNode) {
        badge.parentNode.removeChild(badge);
      }
      var inactiveMeta = query('.vms-ticket-sale-deadline', row);
      if (inactiveMeta) {
        inactiveMeta.hidden = true;
        inactiveMeta.textContent = '';
      }
      return;
    }

    if (!badge) {
      badge = createEl('span', 'vms-ticket-sale-badge', 'On Sale');
      badge.setAttribute('data-vms-created-sale-badge', '1');
    }
    badge.classList.add('vms-ticket-sale-badge');
    badge.setAttribute('data-vms-sale-badge', '1');
    if (titleNode && titleParent) {
      if (badge.parentNode !== titleParent || badge.nextSibling !== titleNode) {
        titleParent.insertBefore(badge, titleNode);
      }
    } else if (titleParent && (badge.parentNode !== titleParent || badge !== titleParent.firstChild)) {
      titleParent.insertBefore(badge, titleParent.firstChild || null);
    }

    var deadlineText = ticketSaleDeadlineText(access);
    var meta = query('.vms-ticket-sale-deadline', row);
    if (!deadlineText) {
      if (meta) {
        meta.hidden = true;
        meta.textContent = '';
      }
      return;
    }
    if (!meta) {
      meta = createEl('div', 'vms-ticket-sale-deadline');
    }
    if (titleNode && titleParent) {
      if (meta.parentNode !== titleParent || titleNode.nextSibling !== meta) {
        titleParent.insertBefore(meta, titleNode.nextSibling);
      }
    } else if (badge && badge.parentNode) {
      if (meta.parentNode !== badge.parentNode || badge.nextSibling !== meta) {
        badge.parentNode.insertBefore(meta, badge.nextSibling);
      }
    } else {
      titleWrap.appendChild(meta);
    }
    setText(meta, deadlineText);
    meta.hidden = false;
  }

  function isQualifiedTicketAccess(access) {
    if (!access || typeof access !== 'object') {
      return false;
    }

    var visibilityMode = normalizeKey(access.visibility_mode || '');
    var allowedPrograms = Array.isArray(access.allowed_programs) ? access.allowed_programs.filter(Boolean) : [];
    return visibilityMode === 'verified'
      || String(access.verified_program || '').trim() !== ''
      || allowedPrograms.length > 0
      || toInt(access.allow_direct_grants, 0) > 0;
  }

  function isBlockedTicketReason(reasonCode) {
    var reason = normalizeKey(reasonCode);
    return [
      'credential_not_approved',
      'grant_expired',
      'grant_reserved',
      'grant_revoked',
      'grant_used'
    ].indexOf(reason) >= 0;
  }

  function stripVisibleTicketPrefix(text) {
    var next = decodeDisplayText(text || '').replace(/\s+/g, ' ').trim();
    if (!next) {
      return '';
    }
    next = next.replace(/^\d{4}-\d{2}-\d{2}\s+\d{1,2}:\d{2}\s*[-–—]\s*/u, '').trim();
    return next;
  }

  function resolveTicketVisibleTitle(row, access) {
    var candidates = [];
    if (access && typeof access === 'object') {
      candidates.push(access.display_label, access.label);
    }
    if (row) {
      var titleNode = query('.tribe-tickets__tickets-item-content-title, .tribe-tickets__tickets-item-title, .tribe-tickets__item__title, h1, h2, h3, h4', row);
      if (titleNode) {
        candidates.push(titleNode.textContent);
      }
    }

    for (var i = 0; i < candidates.length; i += 1) {
      var text = stripVisibleTicketPrefix(candidates[i]);
      if (text) {
        return text;
      }
    }

    return '';
  }

  function resolveTicketDisplayName(input, access) {
    var row = getTicketRow(input);
    var title = resolveTicketVisibleTitle(row, access);
    if (title) {
      return title;
    }

    var candidates = [];
    if (access && typeof access === 'object') {
      candidates.push(access.description);
    }

    for (var i = 0; i < candidates.length; i += 1) {
      var text = decodeDisplayText(candidates[i] || '').replace(/\s+/g, ' ').trim();
      if (text) {
        return text;
      }
    }

    return 'this ticket';
  }

  function ensureTicketVisibleTitle(row, access) {
    if (!row) {
      return;
    }
    var titleNode = query('.tribe-tickets__tickets-item-content-title, .tribe-tickets__tickets-item-title, .tribe-tickets__item__title, h1, h2, h3, h4', row);
    if (!titleNode) {
      return;
    }
    var next = resolveTicketVisibleTitle(row, access);
    if (!next) {
      return;
    }
    setText(titleNode, next);
  }

  function resolveTicketImageUrl(row, access) {
    if (access && typeof access === 'object' && String(access.image_url || '').trim()) {
      return String(access.image_url || '').trim();
    }
    if (!row) {
      return '';
    }
    var found = '';
    queryAll('img', row).some(function (img) {
      if (!img || (img.closest && img.closest('.vms-ticket-description-media'))) {
        return false;
      }
      var src = String(img.getAttribute('src') || '').trim();
      if (!src) {
        return false;
      }
      found = src;
      return true;
    });
    return found;
  }

  function ensureTicketDescriptionMedia(row, access) {
    if (!row) {
      return;
    }
    var descriptionWrap = query('.tribe-tickets__tickets-item-content-description, .tribe-tickets__item__description, .vms-ticket-qualified-description', row);
    if (!descriptionWrap) {
      return;
    }
    var imageUrl = resolveTicketImageUrl(row, access);
    if (!imageUrl) {
      return;
    }

    var media = query('.vms-ticket-description-media', descriptionWrap);
    if (!media) {
      media = createEl('div', 'vms-ticket-description-media');
      descriptionWrap.insertBefore(media, descriptionWrap.firstChild || null);
    }

    var img = query('img', media);
    if (!img) {
      img = document.createElement('img');
      media.appendChild(img);
    }

    img.src = imageUrl;
    img.alt = resolveTicketVisibleTitle(row, access) || 'Ticket image';
    img.loading = 'lazy';
    img.decoding = 'async';
    descriptionWrap.classList.add('vms-ticket-description-has-image');
  }

  function ensureTicketStatusStack(row, ticketModel) {
    if (!row) {
      return null;
    }

    var rightStack = query('.vms-ticket-right-stack', row);
    var stack = query('.vms-ticket-status-stack', row);
    if (!stack) {
      stack = createEl('div', 'vms-ticket-status-stack');
      if (rightStack && rightStack.parentNode === row) {
        row.insertBefore(stack, rightStack);
      } else {
        row.appendChild(stack);
      }
    }

    var noteEl = ticketModel && ticketModel.noteEl ? ticketModel.noteEl : query('.vms-ticket-lock-note, .vms-ticket-benefit-note', row);
    var helpEl = ticketModel && ticketModel.helpEl ? ticketModel.helpEl : query('.vms-claim-ticket-help', row);
    var panelEl = ticketModel && ticketModel.panelEl ? ticketModel.panelEl : query('.vms-claim-seat-panel', row);

    if (noteEl && noteEl.parentNode !== stack) {
      stack.appendChild(noteEl);
    }
    if (helpEl && helpEl.parentNode !== stack) {
      stack.appendChild(helpEl);
    }
    if (panelEl && panelEl.parentNode !== stack) {
      stack.appendChild(panelEl);
    }

    var hasVisibleStatus = !!((noteEl && !noteEl.hidden) || (helpEl && !helpEl.hidden) || (panelEl && !panelEl.hidden));
    stack.hidden = !hasVisibleStatus;
    row.classList.toggle('vms-ticket-row--has-status', hasVisibleStatus);
    row.classList.toggle('vms-ticket-row--no-status', !hasVisibleStatus);
    return stack;
  }

  function titleCaseWords(text) {
    return String(text || '')
      .replace(/[_-]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim()
      .replace(/\b([a-z])/g, function (match, ch) {
        return String(ch || '').toUpperCase();
      });
  }

  function resolveQualifiedProgramKey(access) {
    if (!access || typeof access !== 'object') {
      return '';
    }
    var programKey = normalizeKey(access.verified_program || '');
    if (!programKey) {
      var allowedPrograms = Array.isArray(access.allowed_programs) ? access.allowed_programs.filter(Boolean) : [];
      programKey = normalizeKey(allowedPrograms.length ? allowedPrograms[0] : '');
    }
    return programKey;
  }

  function resolveQualifiedProgramLabel(access) {
    var programKey = resolveQualifiedProgramKey(access);
    if (!programKey) {
      return '';
    }

    var labels = cfg.verificationProgramLabels && typeof cfg.verificationProgramLabels === 'object'
      ? cfg.verificationProgramLabels
      : {};
    var configured = String(labels[programKey] || '').trim();
    return configured || titleCaseWords(programKey);
  }

  function resolveQualifiedTicketLabel(access, viewerContext, row) {
    var candidates = [];
    if (viewerContext && String(viewerContext.ticketName || '').trim()) {
      candidates.push(viewerContext.ticketName);
    }
    if (access && typeof access === 'object') {
      candidates.push(access.display_label, access.label);
    }
    if (row) {
      var titleNode = query('.tribe-tickets__tickets-item-content-title, .tribe-tickets__tickets-item-title, .tribe-tickets__item__title, h1, h2, h3, h4', row);
      if (titleNode) {
        candidates.push(titleNode.textContent);
      }
    }

    for (var i = 0; i < candidates.length; i += 1) {
      var text = decodeDisplayText(candidates[i] || '').replace(/\s+/g, ' ').trim();
      if (text && normalizeKey(text) !== 'this ticket') {
        return text;
      }
    }

    return 'this ticket';
  }

  function resolveQualifiedVerificationLabel(access, viewerContext, row) {
    var programKey = resolveQualifiedProgramKey(access);
    if (/veteran|military|service/.test(programKey)) {
      return 'Veteran';
    }
    if (/police|fire|emt|responder|first_responder|first-responder/.test(programKey)) {
      return 'responder';
    }
    if (/teacher|school/.test(programKey)) {
      return 'teacher';
    }

    var ticketLabel = resolveQualifiedTicketLabel(access, viewerContext, row);
    var ticketKey = normalizeKey(ticketLabel);
    if (/veteran|military|service/.test(ticketKey)) {
      return 'Veteran';
    }
    if (/police|fire|emt|responder/.test(ticketKey)) {
      return 'responder';
    }
    if (/teacher|school/.test(ticketKey)) {
      return 'teacher';
    }

    var programLabel = resolveQualifiedProgramLabel(access);
    if (programLabel) {
      return programLabel;
    }

    return String(ticketLabel || 'account').replace(/\s+Admission$/i, '').trim() || 'account';
  }

  function capitalizeFirst(text) {
    var value = String(text || '').trim();
    return value ? value.charAt(0).toUpperCase() + value.slice(1) : value;
  }

  function baseQualifiedTicketDescription(access, viewerContext, row) {
    return 'Requires registration';
  }

  function qualifiedTicketVerificationPrompt() {
    return 'Click here for more info.';
  }

  function setQualifiedTicketMoreInfoExpanded(details, summary, open) {
    if (!details || !summary) {
      return;
    }
    details.open = !!open;
    summary.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function bindQualifiedTicketMoreInfoSummary(summary, details) {
    if (!summary || !details || summary.getAttribute('data-vms-touch-bound') === '1') {
      if (summary && details) {
        summary.setAttribute('aria-expanded', details.open ? 'true' : 'false');
      }
      return;
    }
    summary.setAttribute('data-vms-touch-bound', '1');
    summary.setAttribute('aria-expanded', details.open ? 'true' : 'false');

    var ignoreClickUntil = 0;
    var lastToggleAt = 0;

    function toggle(event) {
      var now = Date.now();
      if (lastToggleAt && (now - lastToggleAt) < 240) {
        if (event) {
          event.preventDefault();
          event.stopPropagation();
          if (event.stopImmediatePropagation) {
            event.stopImmediatePropagation();
          }
        }
        return;
      }
      lastToggleAt = now;
      if (event) {
        event.preventDefault();
        event.stopPropagation();
        if (event.stopImmediatePropagation) {
          event.stopImmediatePropagation();
        }
      }
      setQualifiedTicketMoreInfoExpanded(details, summary, !details.open);
    }

    summary.addEventListener('pointerup', function (event) {
      var pointerType = event && event.pointerType ? String(event.pointerType).toLowerCase() : '';
      if (pointerType !== 'touch') {
        return;
      }
      ignoreClickUntil = Date.now() + 500;
      toggle(event);
    }, true);

    summary.addEventListener('touchend', function (event) {
      ignoreClickUntil = Date.now() + 500;
      toggle(event);
    }, true);

    summary.addEventListener('click', function (event) {
      if (ignoreClickUntil && Date.now() < ignoreClickUntil) {
        if (event) {
          event.preventDefault();
          event.stopPropagation();
          if (event.stopImmediatePropagation) {
            event.stopImmediatePropagation();
          }
        }
        return;
      }
      toggle(event);
    }, true);
  }

  function defaultQualifiedTicketDescription(access, viewerContext, row) {
    return baseQualifiedTicketDescription(access, viewerContext, row);
  }

  function normalizeQualifiedTicketDescriptionText(text, access) {
    var next = decodeDisplayText(text || '').replace(/\s+/g, ' ').trim();
    if (!next) {
      return '';
    }
    var programLabel = resolveQualifiedProgramLabel(access);
    var legacyProgramCopy = programLabel
      ? ('Qualified ticket. Log in with your approved account to use this ticket. First time? Submit ' + programLabel + ' verification before checkout.')
      : '';
    var legacyGenericCopy = 'Qualified ticket. Log in with your approved account to use this ticket. First time? Submit verification before checkout.';
    var replacement = defaultQualifiedTicketDescription(access, null, null);

    if (legacyProgramCopy && next.indexOf(legacyProgramCopy) >= 0) {
      next = next.replace(legacyProgramCopy, replacement).trim();
    }
    if (next.indexOf(legacyGenericCopy) >= 0) {
      next = next.replace(legacyGenericCopy, replacement).trim();
    }
    next = next
      .replace(/^Free with approved [^.]+ verification\. Already approved\? Select your ticket here\.?\s*/i, replacement + ' ')
      .replace(/^Free after your account is approved\. Already approved\? Select your ticket here\.?\s*/i, replacement + ' ')
      .replace(new RegExp('^' + escapeRegex(replacement) + '\\s+' + escapeRegex(replacement) + '$', 'i'), replacement)
      .trim();
    if (/log in with your approved account to use this ticket\./i.test(next)) {
      next = replacement;
    }
    if (/^Qualified ticket\./i.test(next) || /Submit[^.]*verification before checkout\./i.test(next)) {
      next = replacement;
    }
    return next;
  }

  function stripQualifiedTicketOnboardingCopy(text, access, viewerContext, row) {
    var next = decodeDisplayText(text || '').replace(/\s+/g, ' ').trim();
    if (!next) {
      return '';
    }

    var base = baseQualifiedTicketDescription(access, viewerContext, row);
    next = next.replace(new RegExp('\\s*' + escapeRegex(qualifiedTicketVerificationPrompt()), 'ig'), '').trim();
    next = next.replace(/\s*First time\?\s*Submit[^.]*verification before checkout\./ig, '').trim();
    next = next.replace(/\s*Log in with your approved account to use this ticket\./ig, '').trim();
    next = next.replace(/\s*Log in or register to redeem[^.]*\./ig, '')
      .replace(/\s*Please log in to redeem[^.]*\./ig, '')
      .replace(/\s*New here\?\s*Register first\./ig, '').trim();
    next = next.replace(/^Qualified ticket\.\s*/i, '').trim();
    next = next.replace(/\s+/g, ' ').trim();

    if (!next || /^Qualified ticket\.?$/i.test(next) || /requires an approved account\.?$/i.test(next)) {
      return base;
    }

    return next;
  }

  function looksLikeDefaultQualifiedTicketCopy(text) {
    var next = decodeDisplayText(text || '').replace(/\s+/g, ' ').trim();
    if (!next) {
      return false;
    }

    return (
      /Qualified ticket\./i.test(next)
      || /requires an approved account\./i.test(next)
      || /approved .*accounts can redeem this ticket\./i.test(next)
      || /log in with your approved account to use this ticket\./i.test(next)
      || /first time\?\s*submit .*verification before checkout\./i.test(next)
      || /^Free with approved .* verification\. Already approved\? Select your ticket here\.?(?: Requires registration\.?)?$/i.test(next)
      || /^Free after your account is approved\. Already approved\? Select your ticket here\.?(?: Requires registration\.?)?$/i.test(next)
      || /^Requires registration\.?$/i.test(next)
    );
  }

  function resolveQualifiedTicketDescription(access, row, viewerContext) {
    var candidates = [];
    if (access && typeof access === 'object') {
      candidates.push(normalizeQualifiedTicketDescriptionText(access.description, access));
    }
    if (row) {
      var descNode = query('.tribe-tickets__tickets-item-content-description, .tribe-tickets__item__description, .vms-ticket-qualified-description', row);
      if (descNode) {
        var descCopyNode = query('.vms-ticket-qualified-description-copy', descNode);
        if (!descCopyNode) {
          for (var child = descNode.firstElementChild; child; child = child.nextElementSibling) {
            if (String(child.tagName || '').toLowerCase() === 'p' && !(child.closest && child.closest('.vms-qualified-ticket-more-info'))) {
              descCopyNode = child;
              break;
            }
          }
        }
        candidates.push(normalizeQualifiedTicketDescriptionText(descCopyNode ? descCopyNode.textContent : descNode.textContent, access));
      }
    }

    for (var i = 0; i < candidates.length; i += 1) {
      var text = decodeDisplayText(candidates[i] || '').replace(/\s+/g, ' ').trim();
      if (text) {
        if (looksLikeDefaultQualifiedTicketCopy(text)) {
          return defaultQualifiedTicketDescription(access, viewerContext, row);
        }
        if (viewerContext && viewerContext.mode === 'verified') {
          return stripQualifiedTicketOnboardingCopy(text, access, viewerContext, row);
        }
        return text;
      }
    }

    return defaultQualifiedTicketDescription(access, viewerContext, row);
  }

  function ensureQualifiedTicketMoreInfo(descriptionWrap, access, viewerContext, row) {
    if (!descriptionWrap) {
      return;
    }

    var ticketLabel = resolveQualifiedTicketLabel(access, viewerContext, row);
    var verificationLabel = resolveQualifiedVerificationLabel(access, viewerContext, row);
    var verificationNoun = verificationLabel || 'account';
    var intro = normalizeKey(ticketLabel) !== 'this ticket'
      ? ticketLabel + ' is free after approval.'
      : 'This ticket is free after approval.';
    var actionUrl = String(cfg.verificationUrl || cfg.registerUrl || cfg.loginUrl || '').trim();
    var actionLabel = verificationNoun && normalizeKey(verificationNoun) !== 'account'
      ? 'Start ' + capitalizeFirst(verificationNoun) + ' Verification'
      : 'Start Verification';
    var wasOpen = false;
    var details = query('.vms-qualified-ticket-more-info', descriptionWrap);
    if (details) {
      wasOpen = !!details.open;
      details.parentNode.removeChild(details);
    }

    details = createEl('details', 'vms-qualified-ticket-more-info');
    details.open = wasOpen;

    var summary = createEl('summary', 'vms-qualified-ticket-more-info-summary', qualifiedTicketVerificationPrompt());
    var body = createEl('div', 'vms-qualified-ticket-more-info-body');
    var introEl = createEl('p', 'vms-qualified-ticket-more-info-intro', intro);
    var list = document.createElement('ol');
    list.className = 'vms-qualified-ticket-more-info-list';
    [
      'Create or sign in to your account.',
      'Submit your ' + verificationNoun + ' verification.',
      'Approval is often completed quickly.',
      'Once approved, return here and select your free ticket.'
    ].forEach(function (item) {
      var li = document.createElement('li');
      li.textContent = item;
      list.appendChild(li);
    });

    body.appendChild(introEl);
    body.appendChild(list);

    if (actionUrl) {
      var action = createEl('a', 'vms-qualified-ticket-more-info-action', actionLabel);
      action.href = normalizeUrl(actionUrl);
      body.appendChild(action);
    }

    bindQualifiedTicketMoreInfoSummary(summary, details);
    details.appendChild(summary);
    details.appendChild(body);
    descriptionWrap.appendChild(details);
  }

  function ensureQualifiedTicketDescription(row, access, viewerContext) {
    if (!row || !isQualifiedTicketAccess(access)) {
      return;
    }

    var description = resolveQualifiedTicketDescription(access, row, viewerContext);
    if (!description) {
      return;
    }

    var titleWrap = query('.tribe-tickets__tickets-item-content-title-container, .tribe-tickets__item__content, .tribe-tickets__item__header', row) || row;
    var titleNode = query('.tribe-tickets__tickets-item-content-title, .tribe-tickets__tickets-item-title, .tribe-tickets__item__title, h1, h2, h3, h4', row);
    var descriptionWrap = query('.tribe-tickets__tickets-item-content-description, .tribe-tickets__item__description, .vms-ticket-qualified-description', titleWrap);
    if (!descriptionWrap) {
      descriptionWrap = createEl('div', 'tribe-tickets__tickets-item-content-description vms-ticket-qualified-description');
      if (titleNode && titleNode.parentNode === titleWrap && titleNode.nextSibling) {
        titleWrap.insertBefore(descriptionWrap, titleNode.nextSibling);
      } else {
        titleWrap.appendChild(descriptionWrap);
      }
    }

    var copy = query('.vms-ticket-qualified-description-copy', descriptionWrap);
    if (!copy) {
      copy = query('p', descriptionWrap);
      if (copy && copy.closest && copy.closest('.vms-qualified-ticket-more-info')) {
        copy = null;
      }
    }
    if (!copy) {
      copy = createEl('p', 'vms-ticket-qualified-description-copy');
      descriptionWrap.innerHTML = '';
      descriptionWrap.appendChild(copy);
    } else if (copy.classList) {
      copy.classList.add('vms-ticket-qualified-description-copy');
    }
    setText(copy, description);
    ensureQualifiedTicketMoreInfo(descriptionWrap, access, viewerContext, row);
    descriptionWrap.hidden = false;

    if (titleWrap.classList) {
      titleWrap.classList.remove('tribe-tickets--no-description');
    }
    if (titleNode && titleNode.classList) {
      titleNode.classList.remove('tribe-tickets--no-description');
    }
  }

  function createEl(tag, className, text) {
    var node = document.createElement(tag);
    if (className) {
      node.className = className;
    }
    if (typeof text === 'string') {
      node.textContent = decodeDisplayText(text);
    }
    return node;
  }

  function ensureSectionHelp(section, text, key, style) {
    if (!section) {
      return null;
    }
    var helpId = 'vms-ticket-ui-help-' + String(key || 'section');
    var help = query('#' + helpId, section);
    var copy = String(text || '').trim();
    if (!copy) {
      if (help && help.parentNode) {
        help.parentNode.removeChild(help);
      }
      return null;
    }
    if (!help) {
      help = createEl('div', 'vms-ticket-ui-help');
      help.id = helpId;
    }
    help.innerHTML = copy;
    help.style.fontSize = '';
    help.style.color = '';

    var anchor = null;
    var targetParent = section;

    if (String(key || '') === 'addons') {
      var internalHeading = query('.vms-rw-addons__header h1, .vms-rw-addons__header h2, .vms-rw-addons__header h3, .vms-rw-addons__header h4, #vms-reserved-addons h1, #vms-reserved-addons h2, #vms-reserved-addons h3, #vms-reserved-addons h4', section);
      if (internalHeading) {
        anchor = internalHeading;
        targetParent = internalHeading.parentNode || section;
      }
    }

    if (!anchor) {
      var child = section.firstElementChild;
      while (child) {
        var tag = String(child.tagName || '').toUpperCase();
        var isHeadingTag = tag === 'H1' || tag === 'H2' || tag === 'H3' || tag === 'H4' || tag === 'H5' || tag === 'H6';
        var isHeadingLike = child.classList && (
          child.classList.contains('tribe-common-h1') ||
          child.classList.contains('tribe-common-h2') ||
          child.classList.contains('tribe-common-h3') ||
          child.classList.contains('tribe-tickets__tickets-title') ||
          child.classList.contains('vms-ticket-ui-section-title')
        );
        if (isHeadingTag || isHeadingLike) {
          anchor = child;
          targetParent = section;
          break;
        }
        child = child.nextElementSibling;
      }
    }

    if (anchor) {
      if (help.parentNode !== targetParent || help.previousElementSibling !== anchor) {
        if (anchor.nextSibling) {
          targetParent.insertBefore(help, anchor.nextSibling);
        } else {
          targetParent.appendChild(help);
        }
      }
    } else if (help.parentNode !== section || help !== section.firstElementChild) {
      section.insertBefore(help, section.firstChild || null);
    }
    return help;
  }

  function normalizeUrl(url) {
    try {
      return new URL(url, window.location.href).toString();
    } catch (err) {
      return String(url || '');
    }
  }

  function appendUrlParams(url, params) {
    var base = normalizeUrl(url);
    var searchParams = params instanceof URLSearchParams ? params : new URLSearchParams(params || '');
    var queryText = searchParams.toString();
    if (!queryText) {
      return base;
    }
    try {
      var parsed = new URL(base, window.location.href);
      searchParams.forEach(function (value, key) {
        parsed.searchParams.set(key, value);
      });
      return parsed.toString();
    } catch (err) {
      return base + (base.indexOf('?') >= 0 ? '&' : '?') + queryText;
    }
  }

  function deriveCartContextUrl() {
    var silentAddUrl = String(cfg.silentAddUrl || '').trim();
    if (!silentAddUrl) {
      return '';
    }
    try {
      var parsed = new URL(normalizeUrl(silentAddUrl), window.location.href);
      parsed.searchParams.set('action', 'vms_ticketing_v2_cart_context');
      return parsed.toString();
    } catch (err) {
      if (/action=vms_ticketing_v2_silent_add/i.test(silentAddUrl)) {
        return silentAddUrl.replace(/action=vms_ticketing_v2_silent_add/ig, 'action=vms_ticketing_v2_cart_context');
      }
      return appendUrlParams(silentAddUrl, { action: 'vms_ticketing_v2_cart_context' });
    }
  }

  function useV2Layout() {
    var layout = String(cfg.uiLayout || '').trim().toLowerCase();
    return layout !== 'classic';
  }

  function useProgressiveLayout() {
    var layout = String(cfg.uiLayout || '').trim().toLowerCase();
    return String(cfg.uiProgressive || '') === '1' || layout === 'progressive';
  }

  function activeRenderMode(state) {
    var source = state && state.sourceBlock
      ? state.sourceBlock
      : query(SELECTORS.addonSource, (state && state.form) ? state.form : document);
    return source ? String(source.getAttribute('data-vms-render-mode') || '').trim().toLowerCase() : '';
  }

  function shouldShowSafeModeNotice(state) {
    if (String(cfg.uiSafeModeNotice || '') !== '1') {
      return false;
    }
    if (!useV2Layout()) {
      return true;
    }
    return activeRenderMode(state) === 'server_controls';
  }

  function safeModeNoticeText(state) {
    if (useV2Layout() && activeRenderMode(state) === 'server_controls') {
      var serverControlsText = String(cfg.uiSafeModeServerControlsNoticeText || '').trim();
      if (serverControlsText) {
        return serverControlsText;
      }
    }
    var text = String(cfg.uiSafeModeNoticeText || '').trim();
    return text || 'Ticket UI Safe Mode is active on this site. You are viewing the TEC fallback layout, not the unified V2 purchase UI.';
  }

  function ticketAvailabilityDisplayMode() {
    var mode = String(cfg.ticketAvailabilityDisplay || '').trim().toLowerCase();
    if (mode === 'always' || mode === 'low' || mode === 'hide') {
      return mode;
    }
    if (cfg.showTicketAvailability === false) {
      return 'hide';
    }
    var raw = String(cfg.showTicketAvailability == null ? '' : cfg.showTicketAvailability).trim().toLowerCase();
    if (raw === '0' || raw === 'false' || raw === 'no') {
      return 'hide';
    }
    return 'low';
  }

  function ticketAvailabilityLowThreshold() {
    return Math.max(1, toInt(cfg.ticketAvailabilityLowThreshold, 25));
  }

  function shouldHideTicketAvailability() {
    return ticketAvailabilityDisplayMode() === 'hide';
  }

  function ticketAvailabilityNodes(row) {
    if (!row) {
      return [];
    }
    return queryAll(
      '.tribe-tickets__tickets-item-availability, .tribe-tickets__tickets-item-extra-available, .tribe-tickets__tickets-item-available, .tribe-tickets__item__extra__available, [class*="tickets-item-extra-available"], [class*="tickets-item-availability"]',
      row
    );
  }

  function ticketAvailabilityCountFromNodes(nodes) {
    var best = -1;
    (nodes || []).some(function (node) {
      var text = decodeDisplayText(node && node.textContent ? node.textContent : '').replace(/\s+/g, ' ').trim();
      if (!text) {
        return false;
      }
      var match = text.match(/(\d[\d,]*)\s*(?:available|remaining|left)/i) || text.match(/(\d[\d,]*)/);
      if (!match) {
        return false;
      }
      best = toInt(String(match[1] || '').replace(/,/g, ''), -1);
      return best >= 0;
    });
    return best;
  }

  function syncTicketAvailabilityForRow(row) {
    var nodes = ticketAvailabilityNodes(row);
    if (!nodes.length) {
      return;
    }
    var mode = ticketAvailabilityDisplayMode();
    var hide = mode === 'hide';
    if (mode === 'low') {
      var count = ticketAvailabilityCountFromNodes(nodes);
      hide = count < 0 || count > ticketAvailabilityLowThreshold();
    }
    nodes.forEach(function (node) {
      node.classList.toggle('vms-ticket-availability-force-hidden', hide);
    });
  }

  function syncTicketAvailabilityUi(state) {
    if (!state || !state.form) {
      return;
    }
    queryAll(SELECTORS.nativeQty, state.form).forEach(function (input) {
      syncTicketAvailabilityForRow(getTicketRow(input));
    });
  }

  function replaceMyTicketsNoticeText(container, count) {
    if (!container) {
      return;
    }
    var replacement = 'You have ' + String(count) + ' ' + (count === 1 ? 'Ticket' : 'Tickets') + ' for this Event';
    var pattern = /You have\s+\d+\s+Tickets?\s+for\s+this\s+Event/i;
    var walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT, null);
    var node = walker.nextNode();
    while (node) {
      if (pattern.test(node.nodeValue || '')) {
        node.nodeValue = String(node.nodeValue || '').replace(pattern, replacement);
        return;
      }
      node = walker.nextNode();
    }
  }

  function findMyTicketsNoticeContainers() {
    var pattern = /You have\s+\d+\s+Tickets?\s+for\s+this\s+Event/i;
    var containers = [];
    queryAll('a', document).forEach(function (anchor) {
      var linkText = decodeDisplayText(anchor.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
      if (!/^view\s+tickets?\.?$/.test(linkText)) {
        return;
      }
      var node = anchor.parentElement;
      var steps = 0;
      while (node && node !== document.body && steps < 7) {
        var text = decodeDisplayText(node.textContent || '').replace(/\s+/g, ' ').trim();
        if (pattern.test(text) && text.length < 700) {
          containers.push(node);
          return;
        }
        node = node.parentElement;
        steps += 1;
      }
    });

    if (!containers.length) {
      queryAll('p, div, span, section', document).some(function (node) {
        var text = decodeDisplayText(node.textContent || '').replace(/\s+/g, ' ').trim();
        if (pattern.test(text) && (/view\s+tickets?/i.test(text) || text.length < 220) && text.length < 900) {
          containers.push(node);
        }
        return containers.length >= 5;
      });
    }

    var seen = [];
    return containers.filter(function (node) {
      if (!node || seen.indexOf(node) >= 0) {
        return false;
      }
      seen.push(node);
      return true;
    });
  }

  function syncMyTicketsNotice() {
    var count = toInt(cfg.myActiveTicketCount, -1);
    if (count < 0) {
      return;
    }
    var update = function () {
      findMyTicketsNoticeContainers().forEach(function (container) {
        if (count <= 0) {
          container.hidden = true;
          container.style.display = 'none';
          container.setAttribute('aria-hidden', 'true');
          return;
        }
        container.hidden = false;
        container.style.display = '';
        container.removeAttribute('aria-hidden');
        replaceMyTicketsNoticeText(container, count);
      });
    };
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', update, { once: true });
    } else {
      update();
    }
    [75, 250, 750, 1500, 3000].forEach(function (delay) {
      window.setTimeout(update, delay);
    });
    if (!window.BVMGR_MY_TICKETS_NOTICE_OBSERVER && typeof MutationObserver !== 'undefined' && document.body) {
      window.BVMGR_MY_TICKETS_NOTICE_OBSERVER = new MutationObserver(function () {
        update();
      });
      window.BVMGR_MY_TICKETS_NOTICE_OBSERVER.observe(document.body, { childList: true, subtree: true });
      window.setTimeout(function () {
        if (window.BVMGR_MY_TICKETS_NOTICE_OBSERVER) {
          try {
            window.BVMGR_MY_TICKETS_NOTICE_OBSERVER.disconnect();
          } catch (err) {
            // ignore observer cleanup errors
          }
          window.BVMGR_MY_TICKETS_NOTICE_OBSERVER = null;
        }
      }, 10000);
    }
  }

  function clearLegacyPendingState() {
    var storage = null;
    try {
      storage = window.sessionStorage;
    } catch (err) {
      return;
    }
    if (!storage) {
      return;
    }
    var keys = LEGACY_PENDING_STORAGE_KEYS || ['vms_addons_pending_v1', 'vms_addons_pending_terminal_v1'];
    keys.forEach(function (key) {
      try {
        storage.removeItem(key);
      } catch (err) {
        // ignore storage errors
      }
    });
  }

  function textFromHtml(html) {
    var wrap = document.createElement('div');
    wrap.innerHTML = String(html || '');
    return String(wrap.textContent || '').trim();
  }

  function parseMoney(raw) {
    var text = textFromHtml(raw).replace(/[^0-9.,-]+/g, ' ').trim();
    if (!text) {
      return 0;
    }

    var parts = text.match(/-?\d+(?:[\.,]\d{2})?/g);
    if (!parts || !parts.length) {
      return 0;
    }

    var candidate = String(parts[parts.length - 1]).replace(/,/g, '');
    return toFloat(candidate, 0);
  }

  function formatMoney(value) {
    var amount = Number.isFinite(value) ? value : 0;
    return '$' + amount.toFixed(2);
  }

  function copyNode(node) {
    return node ? node.cloneNode(true) : null;
  }

  function getTicketRow(input) {
    return input ? (input.closest('[data-ticket-id], [data-product-id], .tribe-tickets__item, .tribe-tickets__tickets-item') || null) : null;
  }

  function inferProductId(input) {
    var row = getTicketRow(input);
    var candidates = [];
    if (row && row.dataset) {
      candidates.push(row.dataset.productId, row.dataset.ticketId, row.dataset.product, row.dataset.ticket);
    }

    if (input && input.id) {
      var match = String(input.id).match(/(\d+)(?!.*\d)/);
      if (match) {
        candidates.push(match[1]);
      }
    }

    if (input && input.name) {
      var nameMatch = String(input.name).match(/(\d+)(?!.*\d)/);
      if (nameMatch) {
        candidates.push(nameMatch[1]);
      }
    }

    for (var i = 0; i < candidates.length; i += 1) {
      var value = toInt(candidates[i], 0);
      if (value > 0) {
        return value;
      }
    }
    return 0;
  }

  function readTicketQty(input) {
    if (!input) {
      return 0;
    }
    return Math.max(0, toInt(input.value, 0));
  }

  function totalNativeTicketQty(state) {
    if (!state || !state.form) {
      return 0;
    }
    hideDisabledTicketRows(state);
    return queryAll(SELECTORS.nativeQty, state.form).reduce(function (sum, input) {
      var productId = resolveTicketProductId(state, input);
      var row = getTicketRow(input);
      if (isDisabledPendingSyncTicketProductId(state, productId) || input.disabled || (row && row.hidden)) {
        return sum;
      }
      return sum + readTicketQty(input);
    }, 0);
  }

  function hasNativeTicketSelection(state) {
    return totalNativeTicketQty(state) > 0;
  }


  function ensureTicketChoiceHelp(section) {
    if (!section) {
      return null;
    }
    var help = query('#vms-ticket-choice-help', section);
    if (help && help.parentNode) {
      help.parentNode.removeChild(help);
    }
    return null;
  }


  function ensureTicketRightStacks(state) {
    if (!state || !state.form) {
      return;
    }
    queryAll('.tribe-tickets__tickets-item, .tribe-tickets__item', state.form).forEach(function (row) {
      if (!row || query('.vms-ticket-right-stack', row)) {
        return;
      }
      var extra = query('.tribe-tickets__tickets-item-extra, .tribe-tickets__item__extra', row);
      var qty = query('.tribe-tickets__tickets-item-quantity, .tribe-tickets__item__quantity', row);
      var anchor = qty || extra;
      if (!anchor || !anchor.parentNode) {
        return;
      }
      var stack = createEl('div', 'vms-ticket-right-stack');
      anchor.parentNode.insertBefore(stack, anchor);
      if (extra) {
        stack.appendChild(extra);
      }
      if (qty) {
        stack.appendChild(qty);
      }
    });
  }

  function ensureFlowShell(state) {
    if (!state || !state.form) {
      return null;
    }

    var form = state.form;
    var flow = query('#vms-ticketing-flow', form);
    if (!flow) {
      flow = createEl('div', 'vms-ticketing-flow vms-ticket-ui');
      flow.id = 'vms-ticketing-flow';
      form.appendChild(flow);
    }
    flow.classList.add('vms-ticketing-flow', 'vms-ticket-ui');
    flow.classList.toggle('vms-ticket-ui-v2', useV2Layout());
    flow.classList.toggle('vms-hide-ticket-availability', shouldHideTicketAvailability());

    var renderMode = activeRenderMode(state);
    flow.classList.toggle('vms-ticket-ui--server-controls', renderMode === 'server_controls');
    flow.setAttribute('data-vms-render-mode', renderMode || '');

    var modeNotice = query('#vms-ticket-ui-mode-notice', flow);
    if (shouldShowSafeModeNotice(state)) {
      if (!modeNotice) {
        modeNotice = createEl('div', 'vms-ticket-ui-safe-mode-notice');
        modeNotice.id = 'vms-ticket-ui-mode-notice';
      }
      modeNotice.textContent = safeModeNoticeText(state);
      modeNotice.classList.remove('vms-ticket-ui-safe-mode-hidden');
      if (modeNotice.parentNode !== flow) {
        flow.insertBefore(modeNotice, flow.firstChild || null);
      }
    } else if (modeNotice && modeNotice.parentNode) {
      modeNotice.parentNode.removeChild(modeNotice);
      modeNotice = null;
    }

    var ticketsSection = query('.vms-ticket-ui-tickets', flow);
    if (!ticketsSection) {
      ticketsSection = createEl('section', 'vms-ticket-ui-section vms-ticket-ui-tickets');
    }
    var addonsSection = query('.vms-ticket-ui-addons', flow);
    if (!addonsSection) {
      addonsSection = createEl('section', 'vms-ticket-ui-section vms-ticket-ui-addons');
    }
    var summarySection = query('.vms-ticket-ui-summary', flow);
    if (!summarySection) {
      summarySection = createEl('section', 'vms-ticket-ui-section vms-ticket-ui-summary');
    }
    var stickybarSection = query('.vms-ticket-ui-stickybar', flow);
    if (!stickybarSection) {
      stickybarSection = createEl('section', 'vms-ticket-ui-section vms-ticket-ui-stickybar');
    }

    if (ticketsSection.parentNode !== flow) {
      flow.appendChild(ticketsSection);
    }
    if (addonsSection.parentNode !== flow) {
      flow.appendChild(addonsSection);
    }
    if (summarySection.parentNode !== flow) {
      flow.appendChild(summarySection);
    }
    if (stickybarSection.parentNode !== flow) {
      flow.appendChild(stickybarSection);
    }

    ensureSectionHelp(ticketsSection, (cfg.ticketHelpText || ''), 'tickets', cfg.ticketHelpStyle || null);
    ensureTicketChoiceHelp(ticketsSection);
    ensureSectionHelp(addonsSection, (state && state.sourceBlock) ? (cfg.addonHelpText || '') : '', 'addons', cfg.addonHelpStyle || null);

    Array.prototype.slice.call(form.childNodes).forEach(function (node) {
      if (!node || node === flow || node.nodeType !== 1) {
        return;
      }
      var element = node;
      if (element === state.sourceBlock || element.id === 'vms-addon-mount') {
        return;
      }
      if (element.matches && element.matches(SELECTORS.footer)) {
        return;
      }
      if (element.getAttribute && String(element.getAttribute('type') || '').toLowerCase() === 'hidden') {
        return;
      }
      if (!flow.contains(element)) {
        ticketsSection.appendChild(element);
      }
    });

    if (state.footer && state.footer.parentNode !== stickybarSection) {
      stickybarSection.appendChild(state.footer);
    }

    state.flowRoot = flow;
    state.ticketsSection = ticketsSection;
    state.addonsSection = addonsSection;
    state.summarySection = summarySection;
    state.stickybarSection = stickybarSection;
    ensureTicketRightStacks(state);
    return flow;
  }

  function ensureAddonMountHost(state) {
    if (!state || !state.form) {
      return null;
    }

    ensureFlowShell(state);

    var addonsSection = state.addonsSection || query('.vms-ticket-ui-addons', state.flowRoot || state.form);
    if (!addonsSection) {
      return null;
    }

    var existingHost = state.sourceBlock && state.sourceBlock.parentNode && state.sourceBlock.parentNode.nodeType === 1
      && state.sourceBlock.parentNode.id === 'vms-addon-mount'
      ? state.sourceBlock.parentNode
      : null;
    var mountHost = existingHost || query('#vms-addon-mount', addonsSection) || query('#vms-addon-mount', state.flowRoot || addonsSection);
    if (!mountHost) {
      mountHost = createEl('div', 'vms-addon-mount');
      mountHost.id = 'vms-addon-mount';
    }
    var addonMountParent = query('.vms-ticket-progressive-content', addonsSection) || addonsSection;
    if (mountHost.parentNode !== addonMountParent) {
      addonMountParent.appendChild(mountHost);
    }
    state.addonMountHost = mountHost;
    return mountHost;
  }

  function ensureActionStack(state) {
    ensureFlowShell(state);

    if (!state.footer) {
      return;
    }

    state.footer.classList.add('vms-ticketing-footer-stack');

    var stack = query('#vms-ticketing-action-stack', state.footer);
    if (!stack) {
      stack = createEl('div', 'vms-ticketing-action-stack');
      stack.id = 'vms-ticketing-action-stack';
      state.footer.appendChild(stack);
    }
    state.actionStack = stack;

    var summaryStack = null;
    if (useV2Layout() && state.summarySection) {
      summaryStack = query('#vms-ticketing-summary-stack', state.summarySection);
      if (!summaryStack) {
        summaryStack = createEl('div', 'vms-ticketing-summary-stack');
        summaryStack.id = 'vms-ticketing-summary-stack';
        state.summarySection.appendChild(summaryStack);
      }
    }
    state.summaryStack = summaryStack;

    var subtotalHost = summaryStack || stack;
    var subtotal = query('#vms-ticketing-subtotal', subtotalHost)
      || query('#vms-ticketing-subtotal', state.flowRoot || state.form);
    if (!subtotal) {
      subtotal = createEl('div', 'vms-ticketing-subtotal');
      subtotal.id = 'vms-ticketing-subtotal';
      subtotal.innerHTML = '' +
        '<div class="vms-ticketing-subtotal__primary">' +
          '<div class="vms-ticketing-subtotal__line"><span class="vms-ticketing-subtotal__line-label">Tickets</span><span class="vms-ticketing-subtotal__line-value" data-vms-subtotal-ticket>$0.00</span></div>' +
          '<div class="vms-ticketing-subtotal__line"><span class="vms-ticketing-subtotal__line-label">Add-ons</span><span class="vms-ticketing-subtotal__line-value" data-vms-subtotal-addon>$0.00</span></div>' +
          '<div class="vms-ticketing-subtotal__line vms-ticketing-subtotal__line--subtotal"><span class="vms-ticketing-subtotal__line-label"><strong>Subtotal</strong></span><span class="vms-ticketing-subtotal__line-value" data-vms-subtotal-all><strong>$0.00</strong></span></div>' +
        '</div>' +
        '<div class="vms-ticketing-subtotal__secondary" hidden><div class="vms-ticketing-subtotal__addons"><div class="vms-ticketing-subtotal__addons-title">Selected add-ons</div><div class="vms-ticketing-subtotal__addon-list" data-vms-addon-summary></div></div></div>';
    }
    if (subtotal.parentNode !== subtotalHost) {
      subtotalHost.appendChild(subtotal);
    }
    state.subtotalNode = subtotal;
    state.subtotalTicketNode = query('[data-vms-subtotal-ticket]', subtotal);
    state.subtotalAddonNode = query('[data-vms-subtotal-addon]', subtotal);
    state.subtotalAllNode = query('[data-vms-subtotal-all]', subtotal);
    state.subtotalSecondaryNode = query('.vms-ticketing-subtotal__secondary', subtotal);
    state.subtotalAddonSummaryNode = query('[data-vms-addon-summary]', subtotal);

    var statusBox = query('#vms-addons-direct-add-status', stack);
    if (!statusBox) {
      statusBox = createEl('div', 'vms-addons-direct-add-status');
      statusBox.id = 'vms-addons-direct-add-status';
      statusBox.hidden = true;
      stack.appendChild(statusBox);
    }
    state.statusBox = statusBox;

    state.submitButtons = queryAll(SELECTORS.submit, state.form);
    diagLog('activate-server-controls-state', { addonCount: state.addons.length });
    state.submitButtons.forEach(function (button) {
      if (button.parentNode !== stack) {
        stack.appendChild(button);
      }
      if (!button.getAttribute('data-vms-label-default')) {
        var defaultLabel = button.tagName && button.tagName.toLowerCase() === 'input'
          ? (button.value || 'Get Tickets')
          : (String(button.textContent || '').trim() || 'Get Tickets');
        button.setAttribute('data-vms-label-default', defaultLabel);
      }
    });

    if (state.submitButtons.length) {
      state.originalSubmitLabel = state.submitButtons[0].getAttribute('data-vms-label-default') || 'Get Tickets';
    }
  }

  function mountSourceBlock(state) {
    ensureFlowShell(state);
    var mountHost = ensureAddonMountHost(state);
    if (state.sourceBlock) {
      state.sourceBlock.classList.add('vms-entitlements--compact');
      state.sourceBlock.setAttribute('data-vms-addons-mounted', '1');
    }
    if (mountHost) {
      if (state.sourceBlock.parentNode !== mountHost) {
        mountHost.appendChild(state.sourceBlock);
      }
      return;
    }

    if (state.footer && state.footer.parentNode === state.form && state.sourceBlock.parentNode !== state.form) {
      state.form.insertBefore(state.sourceBlock, state.footer);
      return;
    }

    if (state.sourceBlock.parentNode !== state.form) {
      state.form.appendChild(state.sourceBlock);
    }
  }

  function resolveForm(sourceBlock) {
    var direct = document.getElementById('tribe-tickets__tickets-form');
    if (direct) {
      return direct;
    }

    var wrapper = query('.tribe-tickets__tickets-wrapper');
    if (wrapper) {
      var wrappedForm = query(SELECTORS.form, wrapper);
      if (wrappedForm) {
        return wrappedForm;
      }
    }

    var scopes = [];
    var node = sourceBlock || null;
    while (node && node.nodeType === 1) {
      scopes.push(node);
      node = node.parentElement;
    }
    scopes.push(document);

    for (var i = 0; i < scopes.length; i += 1) {
      var scope = scopes[i];
      var form = query(SELECTORS.form, scope);
      if (form) {
        return form;
      }
    }

    return null;
  }

  function initTicketOnlyBridge(form) {
    if (!form || form.getAttribute('data-vms-ticket-only-bridge') === '1') {
      return false;
    }

    var submitButtons = queryAll(SELECTORS.submit, form);
    if (!submitButtons.length) {
      return false;
    }

    form.setAttribute('data-vms-ticket-only-bridge', '1');

    function refreshButtons() {
      var enabled = queryAll(SELECTORS.nativeQty, form).some(function (input) {
        return readTicketQty(input) > 0;
      });
      submitButtons.forEach(function (button) {
        setDisabled(button, !enabled);
      });
    }

    queryAll(SELECTORS.nativeQty, form).forEach(function (input) {
      input.addEventListener('input', refreshButtons);
      input.addEventListener('change', refreshButtons);
    });

    form.addEventListener('click', function (event) {
      var target = event.target;
      var btn = target && target.closest ? target.closest(SELECTORS.nativeQtyButtons) : null;
      if (!btn) {
        return;
      }
      window.setTimeout(refreshButtons, 0);
      window.setTimeout(refreshButtons, 120);
    }, true);

    submitButtons.forEach(function (button) {
      button.addEventListener('touchstart', refreshButtons, true);
      button.addEventListener('pointerdown', refreshButtons, true);
      button.addEventListener('pointerup', refreshButtons, true);
      button.addEventListener('click', refreshButtons, true);
    });

    refreshButtons();
    return true;
  }

  function canUseState(state) {
    return !!(state && state.form && state.form.isConnected && (!state.sourceBlock || state.sourceBlock.isConnected));
  }

  function repairMountedState(state) {
    if (!canUseState(state)) {
      return false;
    }

    state.footer = query(SELECTORS.footer, state.form) || state.footer || null;
    if (state.sourceBlock) {
      mountSourceBlock(state);
    }
    ensureActionStack(state);

    var isServerControls = !!(state.sourceBlock && String(state.sourceBlock.getAttribute('data-vms-render-mode') || '') === 'server_controls');
    if (state.sourceBlock && !isServerControls && !query('.vms-rw-addon-list', state.sourceBlock)) {
      state.addons = [];
      state.sourceTemplate = state.sourceTemplate || state.sourceBlock.cloneNode(true);
      buildFreshAddonUi(state);
      bindEvents(state);
    }

    scheduleRefresh(state);
    return true;
  }

  function readTicketLines(state) {
    var lines = [];
    hideDisabledTicketRows(state);
    queryAll(SELECTORS.nativeQty, state.form).forEach(function (input) {
      var qty = readTicketQty(input);
      var productId = resolveTicketProductId(state, input);
      var row = getTicketRow(input);
      if (isDisabledPendingSyncTicketProductId(state, productId) || input.disabled || (row && row.hidden)) {
        return;
      }
      if (productId > 0 && qty > 0) {
        lines.push({
          product_id: productId,
          qty: qty,
          input: input
        });
      }
    });
    return lines;
  }

  function selectedQualifyingQty(state) {
    var tracked = state && state.ticketQtyByProduct && typeof state.ticketQtyByProduct === 'object'
      ? state.ticketQtyByProduct
      : null;

    if (tracked) {
      var trackedIds = Object.keys(tracked);
      if (!state.qualifyingTicketIds.length) {
        return trackedIds.reduce(function (sum, productId) {
          return sum + Math.max(0, toInt(tracked[productId], 0));
        }, 0);
      }
      return state.qualifyingTicketIds.reduce(function (sum, productId) {
        return sum + Math.max(0, toInt(tracked[productId], 0));
      }, 0);
    }

    var lines = readTicketLines(state);
    if (!state.qualifyingTicketIds.length) {
      if (!lines.length) {
        return totalNativeTicketQty(state);
      }
      return lines.reduce(function (sum, line) { return sum + line.qty; }, 0);
    }
    if (!lines.length && state.qualifyingTicketIds.length === 1 && queryAll(SELECTORS.nativeQty, state.form).length === 1) {
      return totalNativeTicketQty(state);
    }
    return lines.reduce(function (sum, line) {
      return sum + (state.qualifyingTicketIds.indexOf(line.product_id) >= 0 ? line.qty : 0);
    }, 0);
  }

  function selectedAddonPoolQty(state, poolKey, excludingProductId) {
    return state.addons.reduce(function (sum, addon) {
      if (!addon.poolKey || addon.poolKey !== poolKey) {
        return sum;
      }
      if (excludingProductId > 0 && addon.productId === excludingProductId) {
        return sum;
      }
      return sum + Math.max(0, addon.qty || 0);
    }, 0);
  }

  function readCurrentTicketQtyByProduct(state) {
    var qtyByProduct = {};
    hideDisabledTicketRows(state);
    queryAll(SELECTORS.nativeQty, state.form).forEach(function (input) {
      var productId = resolveTicketProductId(state, input);
      var row = getTicketRow(input);
      if (productId <= 0 || isDisabledPendingSyncTicketProductId(state, productId) || input.disabled || (row && row.hidden)) {
        return;
      }
      var qty = Math.max(0, readTicketQty(input));
      qtyByProduct[productId] = qty;
    });
    return qtyByProduct;
  }

  function syncTrackedTicketQty(state) {
    if (!state || !state.form) {
      return;
    }
    state.ticketQtyByProduct = readCurrentTicketQtyByProduct(state);
  }

  function ensureTicketClaimStore(state) {
    if (!state.ticketClaimsByProduct || typeof state.ticketClaimsByProduct !== 'object') {
      state.ticketClaimsByProduct = {};
    }
    return state.ticketClaimsByProduct;
  }

  function ensureTicketClaimModel(state, productId) {
    var store = ensureTicketClaimStore(state);
    var key = String(productId || '');
    if (!store[key]) {
      store[key] = {
        productId: productId,
        rowEl: null,
        inputEl: null,
        context: null,
        noteEl: null,
        copyEl: null,
        actionsEl: null,
        panelEl: null,
        panelTitleEl: null,
        panelHelpEl: null,
        helpEl: null,
        helpTitleEl: null,
        helpListEl: null,
        helpFootEl: null,
        panelRowsEl: null,
        rowStates: {},
        visibleSeats: []
      };
    }
    return store[key];
  }

  function setTicketClaimRowState(rowState, kind, message) {
    if (!rowState) {
      return;
    }

    rowState.statusKind = kind || '';
    rowState.statusText = String(message || '').trim();

    if (rowState.rowEl) {
      rowState.rowEl.classList.toggle('is-valid', rowState.statusKind === 'valid');
      rowState.rowEl.classList.toggle('is-invalid', rowState.statusKind === 'invalid');
      rowState.rowEl.classList.toggle('is-pending', rowState.statusKind === 'pending');
    }

    if (rowState.inputEl) {
      rowState.inputEl.setAttribute('aria-invalid', rowState.statusKind === 'invalid' ? 'true' : 'false');
    }

    if (rowState.statusEl) {
      rowState.statusEl.hidden = rowState.statusText === '';
      setText(rowState.statusEl, rowState.statusText);
      rowState.statusEl.classList.toggle('is-valid', rowState.statusKind === 'valid');
      rowState.statusEl.classList.toggle('is-invalid', rowState.statusKind === 'invalid');
      rowState.statusEl.classList.toggle('is-pending', rowState.statusKind === 'pending');
    }
  }

  function clearTicketClaimRowState(rowState) {
    setTicketClaimRowState(rowState, '', '');
  }

  function markTicketClaimInteraction(state) {
    if (!state) {
      return;
    }
    state.claimInputInteractingUntil = Date.now() + 400;
  }

  function clearTicketClaimInteraction(state) {
    if (!state) {
      return;
    }
    state.claimInputInteractingUntil = 0;
    if (!state.claimRefreshPending) {
      return;
    }
    window.setTimeout(function () {
      if (!state.claimRefreshPending || isTicketClaimInteractionActive(state)) {
        return;
      }
      state.claimRefreshPending = false;
      refresh(state, { clampAddons: true, clearGlobalMessage: false });
    }, 0);
  }

  function isTicketClaimInteractionActive(state) {
    if (!state) {
      return false;
    }

    if (toInt(state.claimInputInteractingUntil, 0) > Date.now()) {
      return true;
    }

    var active = document.activeElement;
    return !!(active && active.matches && active.matches('.vms-claim-seat-input') && state.form && state.form.contains(active));
  }

  function updateTicketClaimRowControls(state, rowState, canEditAssignments) {
    if (!rowState) {
      return;
    }

    var canEdit = canEditAssignments !== false;
    if (rowState.inputEl) {
      var shouldDisableInput = !!(state && state.isSubmitting);
      setDisabled(rowState.inputEl, shouldDisableInput);
      if (!shouldDisableInput) {
        rowState.inputEl.readOnly = false;
        rowState.inputEl.removeAttribute('readonly');
        rowState.inputEl.removeAttribute('disabled');
        rowState.inputEl.setAttribute('aria-disabled', 'false');
      }
    }

    if (rowState.verifyButton) {
      var canVerify = canEdit
        && !rowState.isPending
        && !(state && state.isSubmitting)
        && !!String(cfg.claimsValidateUrl || '').trim()
        && !!String(cfg.claimsValidateNonce || '').trim();
      setDisabled(rowState.verifyButton, !canVerify);
    }
  }

  function renderTicketClaimActions(actionsEl, actions) {
    if (!actionsEl) {
      return;
    }

    var nextActions = Array.isArray(actions) ? actions.filter(function (action) {
      return action && String(action.label || '').trim() && String(action.url || '').trim();
    }) : [];

    actionsEl.innerHTML = '';
    actionsEl.hidden = !nextActions.length;
    nextActions.forEach(function (action) {
      var link = createEl('a', 'vms-ticket-lock-action', String(action.label || '').trim());
      link.href = normalizeUrl(String(action.url || '').trim());
      if (action.className) {
        link.className += ' ' + String(action.className).trim();
      }
      actionsEl.appendChild(link);
    });
  }

  function ensureTicketClaimShell(row, ticketModel) {
    if (!row || !ticketModel) {
      return;
    }

    if (!ticketModel.noteEl) {
      var note = createEl('div', 'vms-ticket-lock-note');
      var copy = createEl('div', 'vms-ticket-lock-copy');
      var disclosure = createEl('details', 'vms-claim-seat-disclosure');
      var disclosureSummary = createEl('summary', 'vms-claim-seat-disclosure-summary', 'Need more than one qualified ticket?');
      var disclosureCopy = createEl('div', 'vms-claim-seat-disclosure-copy');
      var disclosurePrimary = createEl('p', null, 'Each qualified guest must register and be approved separately using their own email address. After they are approved, one person may purchase or claim tickets for the group by entering each approved guest email address here.');
      var disclosureSecondary = createEl('p', null, 'Qualified tickets are tied to the individual guest, not the buyer or household. We may ask to confirm eligibility at check-in.');
      var actions = createEl('div', 'vms-ticket-lock-actions');
      disclosureCopy.appendChild(disclosurePrimary);
      disclosureCopy.appendChild(disclosureSecondary);
      disclosure.appendChild(disclosureSummary);
      disclosure.appendChild(disclosureCopy);
      note.appendChild(copy);
      note.appendChild(disclosure);
      note.appendChild(actions);
      ticketModel.noteEl = note;
      ticketModel.copyEl = copy;
      ticketModel.helpDisclosureEl = disclosure;
      ticketModel.actionsEl = actions;
    }

    if (!ticketModel.helpEl) {
      var help = createEl('details', 'vms-claim-ticket-help');
      var helpTitle = createEl('summary', 'vms-claim-ticket-help-title');
      var helpBody = createEl('div', 'vms-claim-ticket-help-body');
      var helpList = createEl('div', 'vms-claim-ticket-help-list');
      var helpFoot = createEl('p', 'vms-claim-ticket-help-foot');
      helpBody.appendChild(helpList);
      helpBody.appendChild(helpFoot);
      help.appendChild(helpTitle);
      help.appendChild(helpBody);
      help.open = false;
      ticketModel.helpEl = help;
      ticketModel.helpTitleEl = helpTitle;
      ticketModel.helpBodyEl = helpBody;
      ticketModel.helpListEl = helpList;
      ticketModel.helpFootEl = helpFoot;
    }

    if (!ticketModel.panelEl) {
      var panel = createEl('section', 'vms-claim-seat-panel');
      var panelHead = createEl('div', 'vms-claim-seat-head');
      var panelTitle = createEl('div', 'vms-claim-seat-title');
      var panelHelp = createEl('p', 'vms-claim-seat-help');
      var panelRows = createEl('div', 'vms-claim-seat-rows');
      panelHead.appendChild(panelTitle);
      panelHead.appendChild(panelHelp);
      panel.appendChild(panelHead);
      panel.appendChild(panelRows);
      ticketModel.panelEl = panel;
      ticketModel.panelTitleEl = panelTitle;
      ticketModel.panelHelpEl = panelHelp;
      ticketModel.panelRowsEl = panelRows;
    }

    if (ticketModel.noteEl.parentNode !== row) {
      row.appendChild(ticketModel.noteEl);
    }
    if (ticketModel.helpEl.parentNode !== row) {
      if (ticketModel.panelEl && ticketModel.panelEl.parentNode === row) {
        row.insertBefore(ticketModel.helpEl, ticketModel.panelEl);
      } else {
        row.appendChild(ticketModel.helpEl);
      }
    }
    if (ticketModel.panelEl.parentNode !== row) {
      row.appendChild(ticketModel.panelEl);
    }
  }

  function ensureTicketClaimRow(state, ticketModel, seat) {
    var key = String(seat);
    if (!ticketModel.rowStates[key]) {
      var rowId = 'vms-claim-seat-' + String(ticketModel.productId || 0) + '-' + key;
      var rowEl = createEl('div', 'vms-claim-seat-row');
      var labelEl = createEl('label', 'vms-claim-seat-label', 'Approved guest email for ticket ' + key);
      labelEl.setAttribute('for', rowId);
      var wrap = createEl('div', 'vms-claim-seat-input-wrap');
      var inputEl = createEl('input', 'vms-claim-seat-input');
      inputEl.type = 'email';
      inputEl.id = rowId;
      inputEl.name = 'vms-claim-seat-email-' + String(ticketModel.productId || 0) + '-' + key;
      inputEl.autocomplete = 'section-vms-claim-' + String(ticketModel.productId || 0) + '-' + key + ' email';
      inputEl.inputMode = 'email';
      inputEl.autocapitalize = 'off';
      inputEl.spellcheck = false;
      inputEl.placeholder = 'name@example.com';
      inputEl.setAttribute('aria-label', 'Ticket ' + key + ' approved guest email');
      var verifyButton = createEl('button', 'vms-claim-seat-validate', 'Add Registered Guest');
      verifyButton.type = 'button';
      var statusEl = createEl('div', 'vms-claim-seat-status');
      statusEl.id = rowId + '-status';
      statusEl.hidden = true;
      statusEl.setAttribute('aria-live', 'polite');
      statusEl.setAttribute('aria-atomic', 'true');
      inputEl.setAttribute('aria-describedby', statusEl.id);

      wrap.appendChild(inputEl);
      wrap.appendChild(verifyButton);
      rowEl.appendChild(labelEl);
      rowEl.appendChild(wrap);
      rowEl.appendChild(statusEl);

      ticketModel.rowStates[key] = {
        seat: seat,
        rowEl: rowEl,
        labelEl: labelEl,
        inputEl: inputEl,
        verifyButton: verifyButton,
        statusEl: statusEl,
        email: '',
        isPending: false,
        requestToken: 0,
        lastValidatedEmail: '',
        statusKind: '',
        statusText: ''
      };

      inputEl.addEventListener('focus', function () {
        markTicketClaimInteraction(state);
        updateTicketClaimRowControls(state, ticketModel.rowStates[key], !(ticketModel.context && ticketModel.context.canEditAssignments === false));
      });

      inputEl.addEventListener('blur', function () {
        clearTicketClaimInteraction(state);
      });

      inputEl.addEventListener('input', function () {
        var nextValue = String(inputEl.value || '').trim();
        var rowState = ticketModel.rowStates[key];
        if (!rowState) {
          return;
        }
        markTicketClaimInteraction(state);
        rowState.email = nextValue;
        rowState.isPending = false;
        rowState.requestToken = toInt(rowState.requestToken, 0) + 1;
        if (normalizeEmail(rowState.lastValidatedEmail) !== normalizeEmail(nextValue)) {
          rowState.lastValidatedEmail = '';
          clearTicketClaimRowState(rowState);
        }
        updateTicketClaimRowControls(state, rowState, !(ticketModel.context && ticketModel.context.canEditAssignments === false));
      });

      inputEl.addEventListener('change', function () {
        var rowState = ticketModel.rowStates[key];
        if (!rowState) {
          return;
        }
        markTicketClaimInteraction(state);
        rowState.email = String(inputEl.value || '').trim();
        updateTicketClaimRowControls(state, rowState, !(ticketModel.context && ticketModel.context.canEditAssignments === false));
      });

      verifyButton.addEventListener('click', function (event) {
        if (event) {
          event.preventDefault();
        }
        verifyTicketClaimRow(state, ticketModel, ticketModel.rowStates[key]);
      });
    }

    return ticketModel.rowStates[key];
  }

  function resolveTicketClaimContext(state, input, productId, qty) {
    var access = getTicketAccessEntry(state, productId);
    if (!isQualifiedTicketAccess(access)) {
      return null;
    }

    var selectedQty = Math.max(0, toInt(qty, readTicketQty(input)));
    var ticketName = resolveTicketDisplayName(input, access);
    var isLoggedIn = String(cfg.isLoggedIn || '') === '1';
    var isEligible = toInt(access.current_user_is_eligible, 0) > 0;
    var requiresAssignments = toInt(access.require_assignee_email, 0) > 0;
    var reasonCode = normalizeKey(access.current_user_reason_code || '');
    var claimAllowanceQty = 0;
    var selfEligibleQty = 0;
    var mode = 'guest';
    var noteText = '';
    var noteKind = 'lock';
    var actions = [];

    if (isLoggedIn) {
      if (isEligible) {
        mode = 'verified';
        claimAllowanceQty = Math.max(0, toInt(access.current_user_claim_remaining_qty, 0));
      } else if (isBlockedTicketReason(reasonCode)) {
        mode = 'blocked';
      } else {
        mode = 'unverified';
      }
    }

    selfEligibleQty = Math.max(0, Math.min(selectedQty, claimAllowanceQty));

    var assigneeQty = requiresAssignments ? Math.max(0, selectedQty - selfEligibleQty) : 0;
    var showPanel = selectedQty > 0 && assigneeQty > 0;

    if (mode === 'guest') {
      noteText = 'Please log in to use your ' + ticketName + ' ticket. New here? Register first so we can approve your ' + ticketName + '.';
      if (cfg.loginUrl) {
        actions.push({ label: 'Log In', url: cfg.loginUrl });
      }
      if (cfg.registerUrl) {
        actions.push({ label: 'Register', url: cfg.registerUrl });
      }
    } else if (mode === 'unverified') {
      noteText = 'Please finish your ' + ticketName + ' registration first. Approvals are often completed quickly, but you will need to come back after you are approved before using this ticket.';
      if (cfg.verificationUrl) {
        actions.push({ label: 'Submit Verification', url: cfg.verificationUrl });
      }
    } else if (mode === 'verified') {
      noteKind = 'benefit';
      if (requiresAssignments) {
        if (showPanel) {
          noteText = 'Your account covers ' + String(claimAllowanceQty) + ' ticket' + (claimAllowanceQty === 1 ? '' : 's') + ' for ' + ticketName + '. Enter one approved guest email for each additional ticket below.';
        } else {
          noteText = 'Your account covers ' + String(claimAllowanceQty) + ' ticket' + (claimAllowanceQty === 1 ? '' : 's') + ' for ' + ticketName + '. Increase quantity to add additional approved guests, then enter their approved guest emails below.';
        }
      } else {
        noteText = 'Your account covers ' + String(claimAllowanceQty) + ' ticket' + (claimAllowanceQty === 1 ? '' : 's') + ' for ' + ticketName + '.';
      }
      if (cfg.myBenefitsUrl) {
        actions.push({ label: 'My Benefits', url: cfg.myBenefitsUrl });
      }
    } else if (mode === 'blocked') {
      noteText = String(access.current_user_message || '').trim() || ('Your account is not currently approved to redeem ' + ticketName + '.');
      if (selectedQty > 0 && showPanel) {
        noteText += ' Enter a different approved guest email for each selected ticket below.';
      }
      if (cfg.myBenefitsUrl) {
        actions.push({ label: 'My Benefits', url: cfg.myBenefitsUrl });
      }
    }

    var panelTitle = 'Bringing an approved guest?';
    var panelHelp = '';
    var showDisclosure = false;
    if (showPanel) {
      if (mode === 'verified' && selfEligibleQty > 0) {
        panelTitle = 'Add approved guest emails';
        panelHelp = 'Enter one registered email below for each additional guest who already has an approved ' + ticketName + ' account.';
      } else {
        panelHelp = 'Enter their registered email below only if they already have an approved ' + ticketName + ' account.';
      }
    }

    var showHelp = selectedQty > 0 && requiresAssignments;
    var helpTitle = 'Need help ordering ' + ticketName + ' tickets?';
    var helpItems = [];
    var helpFoot = '';
    if (showHelp) {
      helpItems.push('If this ' + ticketName + ' ticket is for you, log in first.');
      helpItems.push('Not approved yet? Finish your ' + ticketName + ' registration first. Approvals are often completed quickly, but you will need to come back after you are approved before using this ticket.');
      helpItems.push('If this ticket is for someone coming with you, enter their registered email below only if they already have an approved ' + ticketName + ' account.');
    }


    return {
      access: access,
      mode: mode,
      reasonCode: reasonCode,
      ticketName: ticketName,
      selectedQty: selectedQty,
      claimAllowanceQty: claimAllowanceQty,
      selfEligibleQty: selfEligibleQty,
      assigneeQty: assigneeQty,
      firstAssigneeSeat: showPanel ? (selfEligibleQty + 1) : 1,
      requiresAssignments: requiresAssignments,
      showNote: noteText !== '',
      noteKind: noteKind,
      noteText: noteText,
      actions: actions,
      showDisclosure: showDisclosure,
      showHelp: showHelp,
      helpTitle: helpTitle,
      helpItems: helpItems,
      helpFoot: helpFoot,
      showPanel: showPanel,
      panelTitle: panelTitle,
      panelHelp: panelHelp,
      canEditAssignments: showPanel
    };
  }

  function hideTicketClaimModel(ticketModel) {
    if (!ticketModel) {
      return;
    }

    if (ticketModel.rowEl) {
      ticketModel.rowEl.classList.remove('vms-ticket-locked');
    }
    if (ticketModel.noteEl) {
      ticketModel.noteEl.hidden = true;
    }
    if (ticketModel.helpEl) {
      ticketModel.helpEl.hidden = true;
    }
    if (ticketModel.panelEl) {
      ticketModel.panelEl.hidden = true;
    }
    ticketModel.visibleSeats = [];
  }

  function syncTicketClaimUiForInput(state, input) {
    var productId = resolveTicketProductId(state, input);
    if (productId <= 0) {
      return;
    }

    var ticketModel = ensureTicketClaimModel(state, productId);
    ticketModel.rowEl = getTicketRow(input);
    ticketModel.inputEl = input;
    ticketModel.context = resolveTicketClaimContext(state, input, productId, readTicketQty(input));
    if (ticketModel.rowEl && ticketModel.context) {
      ensureTicketVisibleTitle(ticketModel.rowEl, ticketModel.context.access);
      ensureQualifiedTicketDescription(ticketModel.rowEl, ticketModel.context.access, ticketModel.context);
      ensureTicketDescriptionMedia(ticketModel.rowEl, ticketModel.context.access);
    }

    if (!ticketModel.rowEl || !ticketModel.context || (!ticketModel.context.showNote && !ticketModel.context.showPanel)) {
      hideTicketClaimModel(ticketModel);
      ensureTicketStatusStack(ticketModel.rowEl, ticketModel);
      return;
    }

    ensureTicketClaimShell(ticketModel.rowEl, ticketModel);
    ticketModel.rowEl.classList.toggle('vms-ticket-locked', !!(ticketModel.context.showNote || ticketModel.context.showPanel));

    if (ticketModel.noteEl) {
      ticketModel.noteEl.hidden = !ticketModel.context.showNote;
      ticketModel.noteEl.classList.toggle('vms-ticket-benefit-note', ticketModel.context.noteKind === 'benefit');
      setText(ticketModel.copyEl, ticketModel.context.noteText);
      if (ticketModel.helpDisclosureEl) {
        ticketModel.helpDisclosureEl.hidden = !ticketModel.context.showDisclosure;
        if (ticketModel.helpDisclosureEl.hidden) {
          ticketModel.helpDisclosureEl.open = false;
        }
      }
      renderTicketClaimActions(ticketModel.actionsEl, ticketModel.context.actions);
    }

    if (ticketModel.helpEl) {
      var wasHelpHidden = !!ticketModel.helpEl.hidden;
      ticketModel.helpEl.hidden = !ticketModel.context.showHelp;
      if (!ticketModel.context.showHelp) {
        ticketModel.helpEl.open = false;
      }
      if (ticketModel.context.showHelp) {
        if (wasHelpHidden) {
          ticketModel.helpEl.open = false;
        }
        setText(ticketModel.helpTitleEl, ticketModel.context.helpTitle);
        if (ticketModel.helpListEl) {
          ticketModel.helpListEl.innerHTML = '';
          (ticketModel.context.helpItems || []).forEach(function (item) {
            ticketModel.helpListEl.appendChild(createEl('p', 'vms-claim-ticket-help-line', item));
          });
        }
        if (ticketModel.helpFootEl) {
          var foot = String(ticketModel.context.helpFoot || '').trim();
          ticketModel.helpFootEl.hidden = foot === '';
          setText(ticketModel.helpFootEl, foot);
        }
      }
    }

    if (!ticketModel.context.showPanel) {
      if (ticketModel.panelEl) {
        ticketModel.panelEl.hidden = true;
      }
      ticketModel.visibleSeats = [];
      ensureTicketStatusStack(ticketModel.rowEl, ticketModel);
      return;
    }

    if (ticketModel.panelEl) {
      ticketModel.panelEl.hidden = false;
    }
    setText(ticketModel.panelTitleEl, ticketModel.context.panelTitle);
    setText(ticketModel.panelHelpEl, ticketModel.context.panelHelp);

    var visibleSeats = [];
    for (var seat = ticketModel.context.firstAssigneeSeat; seat <= ticketModel.context.selectedQty; seat += 1) {
      visibleSeats.push(seat);
    }
    ticketModel.visibleSeats = visibleSeats;

    visibleSeats.forEach(function (seatNumber, index) {
      var rowState = ensureTicketClaimRow(state, ticketModel, seatNumber);
      rowState.labelEl.textContent = 'Approved guest email for ticket ' + String(seatNumber);
      var liveValue = String(rowState.inputEl.value || '').trim();
      var storedValue = String(rowState.email || '').trim();
      if ((document.activeElement === rowState.inputEl || (liveValue && liveValue !== storedValue))
          && liveValue !== storedValue) {
        rowState.email = liveValue;
        if (normalizeEmail(rowState.lastValidatedEmail) !== normalizeEmail(liveValue)) {
          rowState.lastValidatedEmail = '';
          clearTicketClaimRowState(rowState);
        }
      }
      if (String(rowState.inputEl.value || '') !== String(rowState.email || '')) {
        rowState.inputEl.value = String(rowState.email || '');
      }
      rowState.rowEl.hidden = false;
      setTicketClaimRowState(rowState, rowState.statusKind, rowState.statusText);
      updateTicketClaimRowControls(state, rowState, ticketModel.context.canEditAssignments);
      if (ticketModel.panelRowsEl) {
        var currentChild = ticketModel.panelRowsEl.children[index] || null;
        if (rowState.rowEl.parentNode !== ticketModel.panelRowsEl) {
          ticketModel.panelRowsEl.insertBefore(rowState.rowEl, currentChild);
        } else if (currentChild !== rowState.rowEl) {
          ticketModel.panelRowsEl.insertBefore(rowState.rowEl, currentChild);
        }
      }
    });

    Object.keys(ticketModel.rowStates).forEach(function (key) {
      var rowState = ticketModel.rowStates[key];
      if (!rowState) {
        return;
      }
      if (visibleSeats.indexOf(toInt(key, 0)) >= 0) {
        return;
      }
      rowState.rowEl.hidden = true;
      if (rowState.rowEl.parentNode === ticketModel.panelRowsEl) {
        ticketModel.panelRowsEl.removeChild(rowState.rowEl);
      }
    });

    ensureTicketStatusStack(ticketModel.rowEl, ticketModel);
  }

  function syncQualifiedTicketUi(state) {
    if (!state || !state.form) {
      return;
    }

    hideDisabledTicketRows(state);
    var activeProducts = {};
    queryAll(SELECTORS.nativeQty, state.form).forEach(function (input) {
      var productId = resolveTicketProductId(state, input);
      var row = getTicketRow(input);
      if (productId <= 0 || isDisabledPendingSyncTicketProductId(state, productId) || input.disabled || (row && row.hidden)) {
        return;
      }
      activeProducts[String(productId)] = true;
      var row = getTicketRow(input);
      var access = getTicketAccessEntry(state, productId);
      ensureTicketVisibleTitle(row, access);
      ensureTicketSaleUi(row, access);
      syncTicketAvailabilityForRow(row);
      syncTicketClaimUiForInput(state, input);
      ensureTicketDescriptionMedia(row, access);
      ensureTicketStatusStack(row, ensureTicketClaimStore(state)[String(productId)] || null);
    });

    syncTicketAvailabilityUi(state);

    Object.keys(ensureTicketClaimStore(state)).forEach(function (key) {
      if (activeProducts[key]) {
        return;
      }
      hideTicketClaimModel(state.ticketClaimsByProduct[key]);
    });
  }

  function ticketClaimRowValue(rowState) {
    if (!rowState) {
      return '';
    }
    var liveValue = rowState.inputEl ? String(rowState.inputEl.value || '').trim() : '';
    if (liveValue) {
      return liveValue;
    }
    return String(rowState.email || '').trim();
  }

  function isTicketClaimRowConfirmed(rowState, emailKey) {
    return !!(rowState
      && emailKey
      && rowState.statusKind === 'valid'
      && normalizeEmail(rowState.lastValidatedEmail) === emailKey);
  }

  function collectTicketClaimVerifiedCounts(ticketModel, excludeSeat) {
    var counts = {};
    if (!ticketModel || !Array.isArray(ticketModel.visibleSeats)) {
      return counts;
    }

    ticketModel.visibleSeats.forEach(function (seat) {
      if (seat === excludeSeat) {
        return;
      }
      var rowState = ticketModel.rowStates[String(seat)];
      if (!rowState) {
        return;
      }
      var emailKey = normalizeEmail(rowState.lastValidatedEmail);
      if (!isTicketClaimRowConfirmed(rowState, emailKey)) {
        return;
      }
      counts[emailKey] = toInt(counts[emailKey], 0) + 1;
    });

    return counts;
  }

  function findDuplicateTicketClaimRow(ticketModel, rowState, emailKey) {
    if (!ticketModel || !rowState || !emailKey || !Array.isArray(ticketModel.visibleSeats)) {
      return null;
    }

    for (var i = 0; i < ticketModel.visibleSeats.length; i += 1) {
      var seat = ticketModel.visibleSeats[i];
      if (seat === rowState.seat) {
        continue;
      }
      var otherRow = ticketModel.rowStates[String(seat)];
      if (!otherRow) {
        continue;
      }
      if (normalizeEmail(ticketClaimRowValue(otherRow)) === emailKey || normalizeEmail(otherRow.lastValidatedEmail) === emailKey) {
        return otherRow;
      }
    }

    return null;
  }

  function validateTicketClaimRowLocally(ticketModel, rowState) {
    if (!ticketModel || !rowState) {
      return {
        ok: false,
        message: 'Could not validate this ticket assignment right now.',
        email: '',
        emailKey: ''
      };
    }

    var email = ticketClaimRowValue(rowState);
    var emailKey = normalizeEmail(email);
    if (email === '' || !isValidEmail(email)) {
      return {
        ok: false,
        message: 'Please enter a valid email for Ticket ' + String(rowState.seat) + '.',
        email: email,
        emailKey: emailKey
      };
    }

    var buyerReservedQty = ticketModel.context && ticketModel.context.mode === 'verified'
      ? Math.max(0, toInt(ticketModel.context.selfEligibleQty, 0))
      : 0;
    var buyerEmail = normalizeEmail(cfg.currentUserEmail || '');
    if (buyerReservedQty > 0 && buyerEmail && emailKey === buyerEmail) {
      return {
        ok: false,
        message: 'Ticket ' + String(rowState.seat) + ': use a different approved guest email for additional tickets.',
        email: email,
        emailKey: emailKey
      };
    }

    // Do not block duplicate-looking guest emails on the browser side. Some
    // credential programs and per-user overrides allow the same approved account
    // to claim more than one ticket for the same event. The server-side validator
    // receives the other confirmed assignment counts and enforces the true
    // effective allowance for that specific assignee.

    return {
      ok: true,
      message: '',
      email: email,
      emailKey: emailKey
    };
  }

  function collectTicketClaimAssignmentsForModel(state, ticketModel, options) {
    var opts = options || {};
    if (!ticketModel || !ticketModel.context || !ticketModel.context.showPanel) {
      return {
        ok: true,
        message: '',
        focusEl: null,
        assignments: []
      };
    }

    var assignments = [];
    var firstError = '';
    var focusEl = null;

    ticketModel.visibleSeats.forEach(function (seat) {
      var rowState = ensureTicketClaimRow(state, ticketModel, seat);
      var local = validateTicketClaimRowLocally(ticketModel, rowState);
      rowState.email = local.email;

      if (!local.ok) {
        if (opts.requireComplete !== false) {
          setTicketClaimRowState(rowState, 'invalid', local.message);
        }
        if (!firstError) {
          firstError = local.message;
          focusEl = rowState.inputEl || null;
        }
        return;
      }

      if (rowState.statusKind !== 'valid' || normalizeEmail(rowState.lastValidatedEmail) !== local.emailKey) {
        if (rowState.statusKind !== 'pending') {
          clearTicketClaimRowState(rowState);
        }
      }

      if (isTicketClaimRowConfirmed(rowState, local.emailKey)) {
        assignments.push({
          seat: rowState.seat,
          assignee_email: local.email
        });
        return;
      }

      var verificationMessage = '';
      if (rowState.isPending || rowState.statusKind === 'pending') {
        verificationMessage = 'Ticket ' + String(rowState.seat) + ': wait for the guest email check to finish before adding tickets to your cart.';
      } else if (rowState.statusKind === 'invalid' && rowState.statusText) {
        verificationMessage = rowState.statusText;
      } else {
        verificationMessage = 'Ticket ' + String(rowState.seat) + ': click Add Registered Guest after entering the approved guest email.';
      }

      if (opts.requireComplete !== false) {
        setTicketClaimRowState(rowState, 'invalid', verificationMessage);
      }
      if (!firstError) {
        firstError = verificationMessage;
        focusEl = rowState.inputEl || rowState.verifyButton || null;
      }
    });

    if (!firstError && opts.requireComplete !== false && assignments.length !== ticketModel.visibleSeats.length) {
      firstError = 'Please add one approved guest email per selected ticket before adding tickets to your cart.';
    }

    return {
      ok: firstError === '',
      message: firstError,
      focusEl: focusEl,
      assignments: assignments
    };
  }

  function prepareTicketClaimAssignments(state, options) {
    var opts = options || {};
    var ticketLines = readTicketLines(state);
    var assignmentsByProduct = {};
    var firstError = '';
    var focusEl = null;

    syncQualifiedTicketUi(state);

    ticketLines.forEach(function (line) {
      var ticketModel = ensureTicketClaimStore(state)[String(line.product_id)] || null;
      if (!ticketModel || !ticketModel.context || !ticketModel.context.showPanel) {
        return;
      }

      var modelResult = collectTicketClaimAssignmentsForModel(state, ticketModel, opts);
      assignmentsByProduct[String(line.product_id)] = modelResult.assignments;
      if (!modelResult.ok && !firstError) {
        firstError = modelResult.message;
        focusEl = modelResult.focusEl || null;
      }
    });

    return {
      ok: firstError === '',
      message: firstError,
      focusEl: focusEl,
      assignmentsByProduct: assignmentsByProduct
    };
  }

  function buildAtomicTicketLines(state, options) {
    var claimResult = prepareTicketClaimAssignments(state, options);
    if (!claimResult.ok) {
      return {
        ok: false,
        message: claimResult.message || 'Please add one approved guest email per selected ticket before adding tickets to your cart.',
        focusEl: claimResult.focusEl || null,
        ticketLines: []
      };
    }

    return {
      ok: true,
      message: '',
      focusEl: null,
      ticketLines: readTicketLines(state).map(function (line) {
        var out = {
          product_id: line.product_id,
          qty: line.qty
        };
        var assignments = claimResult.assignmentsByProduct[String(line.product_id)] || [];
        if (assignments.length) {
          out.claim_assignments = assignments;
        }
        return out;
      })
    };
  }

  function verifyTicketClaimRow(state, ticketModel, rowState) {
    if (!state || !ticketModel || !rowState) {
      return;
    }

    var access = ticketModel.context && ticketModel.context.access ? ticketModel.context.access : getTicketAccessEntry(state, ticketModel.productId);
    if (!access) {
      setTicketClaimRowState(rowState, 'invalid', 'Ticket validation is temporarily unavailable.');
      return;
    }

    var local = validateTicketClaimRowLocally(ticketModel, rowState);
    rowState.email = local.email;
    if (!local.ok) {
      setTicketClaimRowState(rowState, 'invalid', local.message);
      updateTicketClaimRowControls(state, rowState, !(ticketModel.context && ticketModel.context.canEditAssignments === false));
      return;
    }

    var requestToken = toInt(rowState.requestToken, 0) + 1;
    rowState.requestToken = requestToken;
    rowState.isPending = true;
    setTicketClaimRowState(rowState, 'pending', 'Checking approved guest email...');
    updateTicketClaimRowControls(state, rowState, !(ticketModel.context && ticketModel.context.canEditAssignments === false));

    var payload = new URLSearchParams();
    payload.set('nonce', String(cfg.claimsValidateNonce || ''));
    payload.set('product_id', String(ticketModel.productId || 0));
    payload.set('event_id', String(state.tecEventId || cfg.tecEventId || 0));
    payload.set('ticket_key', String(access.ticket_key || ''));
    payload.set('assignee_email', local.email);
    var existingCounts = collectTicketClaimVerifiedCounts(ticketModel, rowState.seat);
    Object.keys(existingCounts).forEach(function (emailKey) {
      payload.set('existing_counts[' + emailKey + ']', String(existingCounts[emailKey]));
    });

    fetch(normalizeUrl(cfg.claimsValidateUrl), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: payload.toString()
    }).then(function (response) {
      return response.json().catch(function () {
        return {
          success: false,
          data: {
            ok: false,
            message: 'Could not check this guest email right now.'
          }
        };
      });
    }).then(function (result) {
      var currentEmail = normalizeEmail(ticketClaimRowValue(rowState));
      if (rowState.requestToken !== requestToken || currentEmail !== local.emailKey) {
        return;
      }

      rowState.isPending = false;
      if (result && result.success && result.data && result.data.ok) {
        var confirmedEmail = String(result.data.assignee_email || local.email).trim();
        var ticketLabel = String((result.data && result.data.ticket_label) || (ticketModel.context && ticketModel.context.ticketName) || '').trim();
        rowState.email = confirmedEmail;
        rowState.lastValidatedEmail = confirmedEmail;
        if (rowState.inputEl && normalizeEmail(rowState.inputEl.value) !== normalizeEmail(confirmedEmail)) {
          rowState.inputEl.value = confirmedEmail;
        }
        setTicketClaimRowState(
          rowState,
          'valid',
          'Added: ' + confirmedEmail + (ticketLabel ? ' - ' + ticketLabel + ' eligible' : '')
        );
      } else {
        rowState.lastValidatedEmail = '';
        setTicketClaimRowState(
          rowState,
          'invalid',
          String((result && result.data && result.data.message) || 'Could not check this guest email right now.')
        );
      }
      updateTicketClaimRowControls(state, rowState, !(ticketModel.context && ticketModel.context.canEditAssignments === false));
    }).catch(function () {
      if (rowState.requestToken !== requestToken) {
        return;
      }
      rowState.isPending = false;
      rowState.lastValidatedEmail = '';
      setTicketClaimRowState(rowState, 'invalid', 'Could not check this guest email right now.');
      updateTicketClaimRowControls(state, rowState, !(ticketModel.context && ticketModel.context.canEditAssignments === false));
    });
  }

  function computeAddonLimit(state, addon) {
    if (!addon.canAdd) {
      return 0;
    }

    var qualifyingQty = Math.max(0, state.priorQualifyingQty + state.cartGaQty + selectedQualifyingQty(state));
    var existingPoolQty = addon.poolKey
      ? Math.max(0, toInt(state.priorPoolQtyByKey[addon.poolKey], 0) + toInt(state.cartPoolQtyByKey[addon.poolKey], 0))
      : 0;
    var selectedOtherPoolQty = addon.poolKey ? selectedAddonPoolQty(state, addon.poolKey, addon.productId) : 0;

    var maxByTicket = Infinity;
    if (addon.minGa > 0) {
      maxByTicket = Math.max(0, Math.floor(qualifyingQty / addon.minGa) - existingPoolQty - selectedOtherPoolQty);
    }

    var maxByPool = Infinity;
    if (addon.poolKey && addon.poolMax > 0) {
      maxByPool = Math.max(0, addon.poolMax - existingPoolQty - selectedOtherPoolQty);
    }

    var maxByItem = Infinity;
    if (addon.maxQty > 0) {
      maxByItem = addon.maxQty;
    }

    var limit = Math.min(maxByTicket, maxByPool, maxByItem);
    if (!Number.isFinite(limit)) {
      limit = 999;
    }
    return Math.max(0, limit);
  }

  function collectAddonLines(state) {
    return state.addons.filter(function (addon) {
      return Math.max(0, addon.qty || 0) > 0;
    }).map(function (addon) {
      return {
        product_id: addon.productId,
        qty: Math.max(0, addon.qty || 0)
      };
    });
  }

  function getTicketUnitPrice(state, productId, input) {
    var key = String(productId || '');
    if (state.ticketPriceMap && Object.prototype.hasOwnProperty.call(state.ticketPriceMap, key)) {
      return toFloat(state.ticketPriceMap[key], 0);
    }

    var row = getTicketRow(input);
    var priceNode = row ? query('.tribe-tickets__item__price, .tribe-tickets__tickets-item-price, .tribe-common-b3', row) : null;
    var price = parseMoney(priceNode ? priceNode.textContent : '');
    if (price > 0) {
      state.ticketPriceMap[key] = price;
    }
    return price;
  }

  function ensureStatusHidden(state) {
    if (!state.statusBox) {
      return;
    }
    state.statusBox.hidden = true;
    state.statusBox.textContent = '';
    state.statusBox.classList.remove('is-error', 'is-success');
  }

  function setGlobalMessage(state, message, kind) {
    if (!state.statusBox) {
      return;
    }
    if (!message) {
      ensureStatusHidden(state);
      return;
    }
    state.statusBox.hidden = false;
    state.statusBox.textContent = message;
    state.statusBox.classList.toggle('is-error', kind === 'error');
    state.statusBox.classList.toggle('is-success', kind === 'success');
  }

  function refreshSubtotal(state) {
    if (!state.subtotalNode) {
      return;
    }

    var ticketTotal = 0;
    readTicketLines(state).forEach(function (line) {
      ticketTotal += getTicketUnitPrice(state, line.product_id, line.input) * line.qty;
    });

    var addonTotal = 0;
    if (state.subtotalAddonSummaryNode) {
      state.subtotalAddonSummaryNode.innerHTML = '';
    }

    state.addons.forEach(function (addon) {
      var qty = Math.max(0, addon.qty || 0);
      if (qty <= 0) {
        return;
      }
      addonTotal += addon.unitPrice * qty;
      if (state.subtotalAddonSummaryNode) {
        var item = createEl('div', 'vms-ticketing-subtotal__addon-item');
        item.appendChild(createEl('span', 'vms-ticketing-subtotal__addon-label', addon.label + ' × ' + String(qty)));
        item.appendChild(createEl('span', 'vms-ticketing-subtotal__addon-value', formatMoney(addon.unitPrice * qty)));
        state.subtotalAddonSummaryNode.appendChild(item);
      }
    });

    setText(state.subtotalTicketNode, formatMoney(ticketTotal));
    setText(state.subtotalAddonNode, formatMoney(addonTotal));
    setText(state.subtotalAllNode, formatMoney(ticketTotal + addonTotal));
    if (state.subtotalSecondaryNode) {
      state.subtotalSecondaryNode.hidden = !(addonTotal > 0);
    }
  }

  function refreshSubmitState(state) {
    var addonLines = collectAddonLines(state);
    var ticketLines = readTicketLines(state);
    var hasAddons = addonLines.length > 0;
    var hasTickets = ticketLines.length > 0 || hasNativeTicketSelection(state);
    var shouldEnable = state.isSubmitting ? false : (hasTickets || hasAddons);

    state.submitButtons.forEach(function (button) {
      if (!button) {
        return;
      }
      setDisabled(button, !shouldEnable || state.isSubmitting);
      var defaultLabel = button.getAttribute('data-vms-label-default') || state.originalSubmitLabel || 'Get Tickets';
      var nextLabel = state.isSubmitting ? 'Adding…' : (shouldEnable ? 'Add items to cart' : defaultLabel);
      if (button.tagName && button.tagName.toLowerCase() === 'input') {
        if (button.value !== nextLabel) {
          button.value = nextLabel;
        }
      } else if (String(button.textContent || '') !== nextLabel) {
        button.textContent = nextLabel;
      }
      if (state.isSubmitting) {
        button.setAttribute('data-vms-busy', '1');
        button.setAttribute('data-vms-busy-since', String(Date.now()));
      } else {
        button.removeAttribute('data-vms-busy');
        button.removeAttribute('data-vms-busy-since');
      }
      button.classList.toggle('vms-rw-submit--busy', state.isSubmitting);
    });
  }

  function refresh(state, options) {
    hideDisabledTicketRows(state);
    syncTicketRatioRuleUi(state);
    var opts = options || {};
    if (opts.clearGlobalMessage) {
      ensureStatusHidden(state);
    }

    state.addons.forEach(function (addon) {
      var limit = computeAddonLimit(state, addon);
      if (addon.isCheckbox) {
        limit = Math.min(Math.max(0, limit), 1);
      }
      if (opts.clampAddons) {
        addon.qty = clamp(addon.qty, 0, limit);
      } else {
        addon.qty = Math.max(0, toInt(addon.qty, 0));
        if (addon.isCheckbox) {
          addon.qty = addon.qty > 0 ? 1 : 0;
        }
      }

      if (addon.inputEl) {
        if (addon.isCheckbox) {
          var checkboxLocked = state.isSubmitting || (!addon.canAdd && addon.qty <= 0) || (limit <= 0 && addon.qty <= 0);
          addon.inputEl.checked = addon.qty > 0;
          setDisabled(addon.inputEl, checkboxLocked);
          addon.inputEl.setAttribute('aria-disabled', checkboxLocked ? 'true' : 'false');
          if (addon.checkboxWrapEl && addon.checkboxWrapEl.classList) {
            addon.checkboxWrapEl.classList.toggle('is-selected', addon.qty > 0);
          }
        } else {
          addon.inputEl.value = String(addon.qty);
          addon.inputEl.max = String(limit);
          if (addon.serverControls) {
            setDisabled(addon.inputEl, state.isSubmitting || (!addon.canAdd && addon.qty <= 0));
            if (limit <= 0 && addon.qty <= 0) {
              addon.inputEl.setAttribute('readonly', 'readonly');
              addon.inputEl.setAttribute('aria-disabled', 'true');
            } else {
              addon.inputEl.removeAttribute('readonly');
              addon.inputEl.setAttribute('aria-disabled', state.isSubmitting ? 'true' : 'false');
            }
          }
        }
      }
      if (addon.minusEl) {
        setDisabled(addon.minusEl, addon.isCheckbox || addon.qty <= 0 || state.isSubmitting);
      }
      if (addon.plusEl) {
        setDisabled(addon.plusEl, addon.isCheckbox || addon.qty >= limit || !addon.canAdd || state.isSubmitting);
      }

      var note = '';
      if (!addon.canAdd) {
        note = addon.soldOutText || 'Unavailable';
      } else if (limit <= 0 && addon.minGa > 0) {
        var currentQualifyingQty = Math.max(0, state.priorQualifyingQty + state.cartGaQty + selectedQualifyingQty(state));
        note = addon.qty > 0 ? 'Selected. Uncheck to remove.' : ('Requires ' + String(addon.minGa) + ' qualifying tickets • You have ' + String(currentQualifyingQty) + '.');
      } else if (addon.qty > 0 && addon.isCheckbox) {
        note = 'Selected. Uncheck to remove.';
      } else if (addon.poolKey && addon.poolMax > 0 && addon.qty >= limit && limit > 0) {
        note = 'Pool limit reached.';
      } else if (addon.minGa > 0) {
        note = 'Up to ' + limit + ' allowed with your current tickets.';
      }
      setText(addon.noteEl, note);
      if (addon.noteEl && addon.noteEl.classList) {
        addon.noteEl.classList.remove('vms-ent-note--rule', 'vms-ent-note--selected');
        if (note) {
          if (addon.qty > 0 && addon.isCheckbox) {
            addon.noteEl.classList.add('vms-ent-note--selected');
          } else if (addon.minGa > 0 || !addon.canAdd || (addon.poolKey && addon.poolMax > 0 && limit <= 0)) {
            addon.noteEl.classList.add('vms-ent-note--rule');
          }
        }
      }
      setText(addon.statusEl, addon.qty > 0 ? (addon.isCheckbox ? '' : (String(addon.qty) + ' selected')) : '');
      addon.rowEl.classList.toggle('is-selected', addon.qty > 0);
    });

    if (isTicketClaimInteractionActive(state)) {
      state.claimRefreshPending = true;
    } else {
      state.claimRefreshPending = false;
      syncQualifiedTicketUi(state);
    }
    refreshSubtotal(state);
    refreshSubmitState(state);
    renderDiagPanel();
  }

  function shouldUseDerivedCartContext(state) {
    if (!state || !state.addons || !state.addons.length) {
      return false;
    }
    var qualifyingQty = Math.max(0, state.priorQualifyingQty + state.cartGaQty + selectedQualifyingQty(state));
    return state.addons.some(function (addon) {
      return !!(addon && addon.canAdd && addon.minGa > 0 && qualifyingQty < addon.minGa);
    });
  }

  function shouldFetchCartContext(state) {
    var configured = String(cfg.cartContextUrl || '').trim();
    if (!state || !state.addons || !state.addons.length) {
      return false;
    }
    if (!configured) {
      return shouldUseDerivedCartContext(state);
    }
    var qualifyingQty = Math.max(0, state.priorQualifyingQty + state.cartGaQty + selectedQualifyingQty(state));
    return state.addons.some(function (addon) {
      if (!addon || !addon.canAdd) {
        return false;
      }
      if (addon.minGa > 0 && qualifyingQty < addon.minGa) {
        return true;
      }
      if (!addon.poolKey) {
        return false;
      }
      var hasCartPoolContext = !!(state.cartPoolQtyByKey && Object.prototype.hasOwnProperty.call(state.cartPoolQtyByKey, addon.poolKey));
      var hasPriorPoolContext = !!(state.priorPoolQtyByKey && Object.prototype.hasOwnProperty.call(state.priorPoolQtyByKey, addon.poolKey));
      return !(hasCartPoolContext || hasPriorPoolContext);
    });
  }

  function resolveCartContextUrl(state) {
    var configured = String(cfg.cartContextUrl || '').trim();
    if (configured) {
      return shouldFetchCartContext(state) ? configured : '';
    }
    return shouldUseDerivedCartContext(state) ? deriveCartContextUrl() : '';
  }

  function fetchCartContext(state) {
    var cartContextUrl = resolveCartContextUrl(state);
    if (!cartContextUrl || state.cartContextPending) {
      return state.cartContextPending || Promise.resolve();
    }

    var params = new URLSearchParams();
    if (state.eventPlanId > 0) {
      params.set('event_plan_id', String(state.eventPlanId));
    }
    if (state.tecEventId > 0) {
      params.set('tec_event_id', String(state.tecEventId));
    }
    if (cfg.cartContextNonce) {
      params.set('nonce', String(cfg.cartContextNonce));
    }

    state.cartContextPending = fetch(appendUrlParams(cartContextUrl, params), {
      method: 'GET',
      credentials: 'same-origin'
    }).then(function (response) {
      return response.json();
    }).then(function (payload) {
      if (payload && payload.success && payload.data) {
        state.cartGaQty = Math.max(0, toInt(payload.data.ga_qty, state.cartGaQty));
        state.priorQualifyingQty = Math.max(0, toInt(payload.data.prior_qualifying_qty, state.priorQualifyingQty));
        state.priorPoolQtyByKey = payload.data.prior_pool_qty_by_key && typeof payload.data.prior_pool_qty_by_key === 'object'
          ? payload.data.prior_pool_qty_by_key
          : state.priorPoolQtyByKey;
        state.cartPoolQtyByKey = payload.data.pool_qty_by_key && typeof payload.data.pool_qty_by_key === 'object'
          ? payload.data.pool_qty_by_key
          : state.cartPoolQtyByKey;
      }
    }).catch(function () {
      return null;
    }).finally(function () {
      state.cartContextPending = null;
    });

    return state.cartContextPending;
  }

  function fetchCartContextBeforeSubmit(state, addonLines) {
    var selectedAddonLines = Array.isArray(addonLines) ? addonLines : [];
    if (!selectedAddonLines.length) {
      diagLog('cart-context-submit-skip', { reason: 'no-addon-lines' });
      return Promise.resolve({ skipped: true, reason: 'no-addon-lines' });
    }

    var pending = fetchCartContext(state);
    if (!pending || typeof pending.then !== 'function') {
      return Promise.resolve({ skipped: true, reason: 'no-pending-fetch' });
    }

    var timeoutMs = Math.max(0, toInt(cfg.cartContextSubmitTimeoutMs, 1500));
    if (timeoutMs <= 0) {
      return pending;
    }

    return Promise.race([
      pending,
      new Promise(function (resolve) {
        window.setTimeout(function () {
          diagLog('cart-context-submit-timeout', { timeoutMs: timeoutMs, addonCount: selectedAddonLines.length });
          resolve({ timedOut: true, timeoutMs: timeoutMs });
        }, timeoutMs);
      })
    ]);
  }

  function scheduleRefresh(state) {
    if (state.refreshRafScheduled) {
      return;
    }
    state.refreshRafScheduled = true;
    window.requestAnimationFrame(function () {
      state.refreshRafScheduled = false;
      diagLog('schedule-refresh-raf');
      refresh(state, { clampAddons: true, clearGlobalMessage: true });
    });
    window.setTimeout(function () {
      refresh(state, { clampAddons: true, clearGlobalMessage: false });
    }, 180);
  }

  function bindNativeQtyObservers(state) {
    function resolveNativeQtyInputFromButton(button) {
      var wrap = button && button.closest
        ? button.closest('.tribe-tickets__tickets-item-quantity, .tribe-tickets__item__quantity')
        : null;
      return wrap ? query(SELECTORS.nativeQty, wrap) : null;
    }

    function nativeQtyButtonDirection(button) {
      if (!button) {
        return 0;
      }
      var haystack = [
        button.className || '',
        button.getAttribute('aria-label') || '',
        button.getAttribute('title') || '',
        button.textContent || ''
      ].join(' ').toLowerCase();
      if (/add|plus|increase|increment/.test(haystack) || /\+\s*$/.test(haystack)) {
        return 1;
      }
      if (/remove|minus|decrease|decrement/.test(haystack) || /(^|\s)-\s*$/.test(haystack) || /−/.test(haystack)) {
        return -1;
      }
      return 0;
    }

    function applyNativeQtyTouchFallback(button, beforeQty) {
      if (!button || button.disabled) {
        return false;
      }

      var input = resolveNativeQtyInputFromButton(button);
      if (!input || input.disabled) {
        return false;
      }

      var direction = nativeQtyButtonDirection(button);
      if (!direction) {
        return false;
      }

      var currentQty = readTicketQty(input);
      if (currentQty !== beforeQty) {
        return false;
      }

      var minQty = Math.max(0, toInt(input.getAttribute('min'), 0));
      var maxQty = toInt(input.getAttribute('max'), direction > 0 ? 999 : minQty);
      if (maxQty <= 0 && direction > 0) {
        maxQty = 999;
      }

      var nextQty = direction > 0
        ? Math.min(maxQty, currentQty + 1)
        : Math.max(minQty, currentQty - 1);
      if (nextQty === currentQty) {
        return false;
      }

      input.value = String(nextQty);
      input.setAttribute('value', String(nextQty));
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
      diagLog('native-qty-touch-fallback-applied', {
        buttonClass: button.className || '',
        before: currentQty,
        after: nextQty
      });
      trackedRefresh(120);
      return true;
    }

    function nativeQtyTouchSuppressUntil(button) {
      return Math.max(0, toInt(button && button.getAttribute ? button.getAttribute('data-vms-native-touch-suppress-click-until') : 0, 0));
    }

    function setNativeQtyTouchSuppressUntil(button, until) {
      if (!button || !button.setAttribute) {
        return;
      }
      var stamp = Math.max(0, toInt(until, 0));
      if (stamp > 0) {
        button.setAttribute('data-vms-native-touch-suppress-click-until', String(stamp));
      } else {
        button.removeAttribute('data-vms-native-touch-suppress-click-until');
      }
    }

    function scheduleNativeQtyTouchFallback(button) {
      if (!button || button.disabled) {
        return;
      }

      var input = resolveNativeQtyInputFromButton(button);
      if (!input || input.disabled) {
        return;
      }

      var token = String(Date.now()) + ':' + String(Math.random());
      var beforeQty = readTicketQty(input);
      button.setAttribute('data-vms-native-touch-token', token);
      setNativeQtyTouchSuppressUntil(button, 0);
      window.setTimeout(function () {
        if (!button.isConnected || button.getAttribute('data-vms-native-touch-token') !== token) {
          return;
        }
        if (applyNativeQtyTouchFallback(button, beforeQty)) {
          setNativeQtyTouchSuppressUntil(button, Date.now() + Math.max(0, toInt(cfg.nativeQtyTouchSuppressClickMs, 700)));
        }
      }, Math.max(0, toInt(cfg.nativeQtyTouchFallbackDelayMs, 160)));
    }

    function trackedRefresh(delay) {
      hideDisabledTicketRows(state);
      syncTrackedTicketQty(state);
      scheduleRefresh(state);
      window.setTimeout(function () {
        syncTrackedTicketQty(state);
        refresh(state, { clampAddons: true, clearGlobalMessage: false });
      }, delay || 80);
    }

    function mutationTouchesNativeTicketControls(record) {
      function touches(node, includeDescendants) {
        if (!node || node.nodeType !== 1) {
          return false;
        }
        if ((node.matches && (
          node.matches(SELECTORS.nativeQty)
          || node.matches(SELECTORS.nativeQtyButtons)
          || node.matches('.tribe-tickets__tickets-item-quantity, .tribe-tickets__item__quantity')
        )) || (node.closest && node.closest('.tribe-tickets__tickets-item-quantity, .tribe-tickets__item__quantity'))) {
          return true;
        }
        if (!includeDescendants) {
          return false;
        }
        return !!(node.querySelector && (
          node.querySelector(SELECTORS.nativeQty)
          || node.querySelector(SELECTORS.nativeQtyButtons)
          || node.querySelector('.tribe-tickets__tickets-item-quantity, .tribe-tickets__item__quantity')
        ));
      }

      if (!record) {
        return false;
      }
      if (touches(record.target, false)) {
        return true;
      }

      var nodes = [];
      if (record.addedNodes && record.addedNodes.length) {
        nodes = nodes.concat(Array.prototype.slice.call(record.addedNodes));
      }
      if (record.removedNodes && record.removedNodes.length) {
        nodes = nodes.concat(Array.prototype.slice.call(record.removedNodes));
      }

      return nodes.some(function (node) {
        return touches(node, true);
      });
    }

    queryAll(SELECTORS.nativeQty, state.form).forEach(function (input) {
      input.addEventListener('input', function () {
        diagLog('native-qty-input', { name: input.name || input.id || '', value: input.value });
        trackedRefresh(60);
      });
      input.addEventListener('change', function () {
        diagLog('native-qty-change', { name: input.name || input.id || '', value: input.value });
        trackedRefresh(100);
      });
    });

    state.form.addEventListener('click', function (event) {
      var target = event.target;
      var btn = target && target.closest ? target.closest(SELECTORS.nativeQtyButtons) : null;
      if (!btn) {
        return;
      }
      var suppressUntil = nativeQtyTouchSuppressUntil(btn);
      if (suppressUntil && Date.now() < suppressUntil) {
        setNativeQtyTouchSuppressUntil(btn, 0);
        if (event) {
          event.preventDefault();
          event.stopPropagation();
          if (event.stopImmediatePropagation) {
            event.stopImmediatePropagation();
          }
        }
        diagLog('native-qty-touch-click-suppressed', { buttonClass: btn.className || '' });
        return;
      }
      if (suppressUntil) {
        setNativeQtyTouchSuppressUntil(btn, 0);
      }
      diagLog('native-qty-button-click', { buttonClass: btn.className || '' });
      window.setTimeout(function () { trackedRefresh(60); }, 0);
      window.setTimeout(function () { trackedRefresh(120); }, 120);
      window.setTimeout(function () { trackedRefresh(240); }, 240);
    }, true);

    state.form.addEventListener('pointerup', function (event) {
      var pointerType = event && event.pointerType ? String(event.pointerType).toLowerCase() : '';
      if (pointerType !== 'touch') {
        return;
      }
      var target = event.target;
      var btn = target && target.closest ? target.closest(SELECTORS.nativeQtyButtons) : null;
      if (!btn) {
        return;
      }
      scheduleNativeQtyTouchFallback(btn);
    }, true);

    state.form.addEventListener('touchend', function (event) {
      var target = event.target;
      var btn = target && target.closest ? target.closest(SELECTORS.nativeQtyButtons) : null;
      if (!btn) {
        return;
      }
      scheduleNativeQtyTouchFallback(btn);
    }, true);

    if (typeof MutationObserver !== 'undefined') {
      var observer = new MutationObserver(function (records) {
        var relevant = Array.isArray(records) ? records.some(mutationTouchesNativeTicketControls) : false;
        if (!relevant) {
          return;
        }
        diagLog('form-mutation');
        trackedRefresh(120);
      });
      observer.observe(state.form, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['value', 'aria-disabled', 'disabled', 'class']
      });
      state.ticketQtyObserver = observer;
    }
  }

  function buildServerControlsState(sourceBlock, form) {
    var submitButtons = queryAll(SELECTORS.submit, form);
    var submitButton = submitButtons.length ? submitButtons[0] : null;
    var state = {
      form: form,
      sourceBlock: sourceBlock,
      sourceTemplate: sourceBlock.cloneNode(true),
      tecEventId: toInt(cfg.tecEventId || sourceBlock.getAttribute('data-vms-tec-event-id'), 0),
      eventPlanId: toInt(cfg.eventPlanId || sourceBlock.getAttribute('data-vms-event-plan-id'), 0),
      gaProductId: toInt(sourceBlock.getAttribute('data-vms-ga-product-id'), 0),
      qualifyingTicketIds: uniqueInts(String(sourceBlock.getAttribute('data-vms-qualifying-ticket-product-ids') || '').split(',')),
      priorQualifyingQty: Math.max(0, toInt(sourceBlock.getAttribute('data-vms-prior-qualifying-qty'), 0)),
      priorPoolQtyByKey: safeParseJson(sourceBlock.getAttribute('data-vms-prior-pool-qty'), {}),
      cartGaQty: Math.max(0, toInt(sourceBlock.getAttribute('data-vms-cart-ga-qty'), 0)),
      cartPoolQtyByKey: safeParseJson(sourceBlock.getAttribute('data-vms-cart-pool-qty'), {}),
      ticketAccessMap: mapObject(cfg.ticketAccessMap),
      ticketRemainingMap: mapObject(cfg.ticketRemainingMap),
      ticketPriceMap: (cfg.ticketPriceMap && typeof cfg.ticketPriceMap === 'object') ? cfg.ticketPriceMap : {},
      disabledTicketMap: mapObject(cfg.disabledTicketMap),
      disabledTicketProductIds: uniqueInts(cfg.disabledTicketProductIds || []),
      ticketClaimsByProduct: {},
      addons: [],
      submitButtons: submitButtons,
      footer: query(SELECTORS.footer, form) || null,
      summarySection: null,
      summaryStack: null,
      actionStack: null,
      subtotalNode: null,
      subtotalTicketNode: null,
      subtotalAddonNode: null,
      subtotalAllNode: null,
      subtotalSecondaryNode: null,
      subtotalAddonSummaryNode: null,
      statusBox: null,
      isSubmitting: false,
      cartContextPending: null,
      originalSubmitLabel: submitButton ? ((submitButton.tagName && submitButton.tagName.toLowerCase() === 'input') ? String(submitButton.value || 'Get Tickets') : String(submitButton.textContent || 'Get Tickets')) : 'Get Tickets',
      ticketQtyByProduct: {},
      allowNativeSubmit: false
    };

    hideDisabledTicketRows(state);
    syncTrackedTicketQty(state);
    diagLog('build-server-controls-state', {
      qualifyingIds: state.qualifyingTicketIds,
      priorQualifyingQty: state.priorQualifyingQty,
      cartGaQty: state.cartGaQty,
      addonCount: state.addons.length
    });

    queryAll('.vms-entitlements-list > .vms-ent-row, .vms-entitlements-list > .vms-entitlements-item', sourceBlock).forEach(function (row) {
      var stepper = query('[data-vms-server-stepper="1"]', row);
      var soldOut = query('.vms-entitlements-soldout', row);
      var input = query('.vms-addon-input', row);
      var minus = query('.vms-addon-minus', row);
      var plus = query('.vms-addon-plus', row);
      var productId = toInt((stepper && stepper.getAttribute('data-vms-product-id')) || row.getAttribute('data-vms-product-id') || (input && input.getAttribute('data-vms-product-id')) || 0, 0);
      var selectorMode = String((stepper && stepper.getAttribute('data-vms-selector-mode')) || row.getAttribute('data-vms-selector-mode') || '').trim() === 'checkbox' ? 'checkbox' : 'stepper';
      if (!stepper || !input || !minus || !plus || productId <= 0) {
        return;
      }
      var model = {
        productId: productId,
        label: ((query('.vms-ent-title, .vms-entitlements-label', row) || {}).textContent || 'Add-on').trim(),
        priceHtml: ((query('.vms-ent-price, .vms-entitlements-price', row) || {}).innerHTML || '').trim(),
        unitPrice: parseMoney((query('.vms-ent-price, .vms-entitlements-price', row) || {}).innerHTML || ''),
        soldOutText: soldOut ? String(soldOut.textContent || '').trim() : '',
        poolKey: (stepper.getAttribute('data-vms-pool-key')) || row.getAttribute('data-vms-pool-key') || '',
        poolMax: toInt(stepper.getAttribute('data-vms-pool-max') || row.getAttribute('data-vms-pool-max'), 0),
        minGa: toInt(stepper.getAttribute('data-vms-pool-min-ga') || row.getAttribute('data-vms-min-ga'), 0),
        maxQty: toInt(stepper.getAttribute('data-vms-max-qty') || row.getAttribute('data-vms-max-qty'), 0),
        canAdd: String(stepper.getAttribute('data-vms-can-add') || '1') !== '0',
        selectorMode: selectorMode,
        isCheckbox: selectorMode === 'checkbox',
        qty: selectorMode === 'checkbox' ? ((!!input.checked) ? 1 : 0) : Math.max(0, toInt(input.value, 0)),
        rowEl: row,
        inputEl: input,
        minusEl: minus,
        plusEl: plus,
        noteEl: query('.vms-ent-note', row),
        statusEl: query('.vms-rw-addon__status', row),
        checkboxWrapEl: query('.vms-addon-checkbox-wrap', row),
        checkboxLabelEl: query('.vms-addon-checkbox-label', row),
        serverControls: true
      };
      state.addons.push(model);
    });

    return state;
  }

  function buildTicketOnlyState(form) {
    var submitButtons = queryAll(SELECTORS.submit, form);
    var submitButton = submitButtons.length ? submitButtons[0] : null;

    return {
      form: form,
      sourceBlock: null,
      sourceTemplate: null,
      tecEventId: toInt(cfg.tecEventId, 0),
      eventPlanId: toInt(cfg.eventPlanId, 0),
      gaProductId: 0,
      qualifyingTicketIds: [],
      priorQualifyingQty: 0,
      priorPoolQtyByKey: {},
      cartGaQty: 0,
      cartPoolQtyByKey: {},
      ticketAccessMap: mapObject(cfg.ticketAccessMap),
      ticketRemainingMap: mapObject(cfg.ticketRemainingMap),
      ticketPriceMap: (cfg.ticketPriceMap && typeof cfg.ticketPriceMap === 'object') ? cfg.ticketPriceMap : {},
      disabledTicketMap: mapObject(cfg.disabledTicketMap),
      disabledTicketProductIds: uniqueInts(cfg.disabledTicketProductIds || []),
      ticketClaimsByProduct: {},
      addons: [],
      submitButtons: submitButtons,
      footer: query(SELECTORS.footer, form) || null,
      summarySection: null,
      summaryStack: null,
      actionStack: null,
      subtotalNode: null,
      subtotalTicketNode: null,
      subtotalAddonNode: null,
      subtotalAllNode: null,
      subtotalSecondaryNode: null,
      subtotalAddonSummaryNode: null,
      statusBox: null,
      isSubmitting: false,
      cartContextPending: null,
      originalSubmitLabel: submitButton ? ((submitButton.tagName && submitButton.tagName.toLowerCase() === 'input') ? String(submitButton.value || 'Get Tickets') : String(submitButton.textContent || 'Get Tickets')) : 'Get Tickets',
      refreshRafScheduled: false,
      ticketQtyByProduct: {},
      allowNativeSubmit: false
    };
  }

  function activateTicketOnlyState(state) {
    if (!state || !state.form || !state.submitButtons || !state.submitButtons.length) {
      return false;
    }

    state.form.setAttribute('data-vms-ticketing-rewrite', '1');
    hideDisabledTicketRows(state);
    syncTrackedTicketQty(state);
    ensureActionStack(state);

    state.submitButtons.forEach(function (button) {
      if (!button) {
        return;
      }
      button.removeAttribute('data-js');
      button.removeAttribute('data-content');
      button.removeAttribute('aria-haspopup');
      button.setAttribute('data-vms-cart-first', '1');
      if (!button.getAttribute('data-vms-label-default')) {
        var defaultLabel = button.tagName && button.tagName.toLowerCase() === 'input'
          ? (button.value || 'Get Tickets')
          : (String(button.textContent || '').trim() || 'Get Tickets');
        button.setAttribute('data-vms-label-default', defaultLabel);
      }
    });

    bindNativeQtyObservers(state);

    state.form.addEventListener('submit', function (event) {
      if (state.allowNativeSubmit) {
        state.allowNativeSubmit = false;
        return;
      }
      if (event) {
        event.preventDefault();
        event.stopPropagation();
        if (event.stopImmediatePropagation) {
          event.stopImmediatePropagation();
        }
      }
      handleAtomicSubmit(state);
    }, true);

    state.submitButtons.forEach(function (button) {
      bindSubmitButton(button, state);
    });

    refresh(state, { clampAddons: true, clearGlobalMessage: true });
    return true;
  }

  function activateServerControlsState(state) {
    if (!state || !state.addons || !state.addons.length) {
      return false;
    }

    state.sourceBlock.classList.add('vms-rw-addons', 'vms-rw-addons--server-controls', 'vms-entitlements--compact');
    state.sourceBlock.setAttribute('data-vms-server-controls-active', '1');
    state.sourceBlock.setAttribute('data-vms-inline-controller-active', '1');
    state.sourceBlock.setAttribute('data-vms-addons-mounted', '1');
    state.form.setAttribute('data-vms-ticketing-rewrite', '1');
    hideDisabledTicketRows(state);
    syncTrackedTicketQty(state);
    mountSourceBlock(state);
    ensureActionStack(state);

    state.submitButtons.forEach(function (button) {
      if (!button) {
        return;
      }
      button.removeAttribute('data-js');
      button.removeAttribute('data-content');
      button.removeAttribute('aria-haspopup');
      button.setAttribute('data-vms-cart-first', '1');
      button.setAttribute('data-vms-label-default', state.originalSubmitLabel || 'Get Tickets');
    });

    state.addons.forEach(function (addon) {
      if (!addon.inputEl) {
        return;
      }
      bindAddonCheckboxToggle(addon, state, { clearGlobalMessage: true });
      bindAddonStepperButton(addon.minusEl, function () {
        diagLog('addon-minus-click', { productId: addon.productId, before: addon.qty });
        addon.qty = Math.max(0, addon.qty - 1);
        refresh(state, { clampAddons: false, clearGlobalMessage: true });
      });
      bindAddonStepperButton(addon.plusEl, function () {
        diagLog('addon-plus-click', { productId: addon.productId, before: addon.qty });
        addon.qty = addon.qty + 1;
        refresh(state, { clampAddons: true, clearGlobalMessage: true });
      });
      addon.inputEl.addEventListener('input', function () {
        diagLog('addon-input', { productId: addon.productId, value: addon.isCheckbox ? addon.inputEl.checked : addon.inputEl.value });
        addon.qty = addon.isCheckbox ? (addon.inputEl.checked ? 1 : 0) : Math.max(0, toInt(addon.inputEl.value, 0));
        refresh(state, { clampAddons: true, clearGlobalMessage: true });
      });
      addon.inputEl.addEventListener('change', function () {
        addon.qty = addon.isCheckbox ? (addon.inputEl.checked ? 1 : 0) : Math.max(0, toInt(addon.inputEl.value, 0));
        refresh(state, { clampAddons: true, clearGlobalMessage: true });
      });
    });

    bindNativeQtyObservers(state);

    state.form.addEventListener('submit', function (event) {
      if (state.allowNativeSubmit) {
        state.allowNativeSubmit = false;
        return;
      }
      diagLog('form-submit', { submitter: event.submitter ? (event.submitter.id || event.submitter.name || event.submitter.className || '') : '' });
      if (event) {
        event.preventDefault();
        event.stopPropagation();
        if (event.stopImmediatePropagation) {
          event.stopImmediatePropagation();
        }
      }
      handleAtomicSubmit(state);
    }, true);

    state.submitButtons.forEach(function (button) {
      bindSubmitButton(button, state);
    });

    refresh(state, { clampAddons: true, clearGlobalMessage: true });
    return true;
  }

  function bindEvents(state) {
    state.addons.forEach(function (addon) {
      if (!addon.inputEl) {
        return;
      }
      bindAddonCheckboxToggle(addon, state, { clearGlobalMessage: false });
      bindAddonStepperButton(addon.minusEl, function () {
        addon.qty = Math.max(0, addon.qty - 1);
        refresh(state, { clampAddons: false });
      });
      bindAddonStepperButton(addon.plusEl, function () {
        addon.qty = addon.qty + 1;
        refresh(state, { clampAddons: true });
      });
      addon.inputEl.addEventListener('input', function () {
        addon.qty = addon.isCheckbox ? (addon.inputEl.checked ? 1 : 0) : Math.max(0, toInt(addon.inputEl.value, 0));
        refresh(state, { clampAddons: true });
      });
      addon.inputEl.addEventListener('change', function () {
        addon.qty = addon.isCheckbox ? (addon.inputEl.checked ? 1 : 0) : Math.max(0, toInt(addon.inputEl.value, 0));
        refresh(state, { clampAddons: true });
      });
    });

    bindNativeQtyObservers(state);

    state.form.addEventListener('submit', function (event) {
      if (state.allowNativeSubmit) {
        state.allowNativeSubmit = false;
        return;
      }
      event.preventDefault();
      event.stopPropagation();
      handleAtomicSubmit(state);
    }, true);

    state.submitButtons.forEach(function (button) {
      bindSubmitButton(button, state);
    });
  }

  function buildFreshAddonUi(state) {
    var sourceBlock = state.sourceBlock;
    var configuredAddonHeading = decodeDisplayText(cfg.addonSectionHeading || '').replace(/\s+/g, ' ').trim();
    var headingText = configuredAddonHeading || (query('h3', sourceBlock) || {}).textContent || 'Fire Pits & Tables';
    var addonIntroText = decodeDisplayText(cfg.addonSectionSubtext || '').replace(/\s+/g, ' ').trim() || 'Click here to add a fire pit or table to your order.';
    var preservedAuxInputs = queryAll('.vms-aux-ticket-qty', state.sourceTemplate).map(function (node) {
      return copyNode(node);
    }).filter(Boolean);

    state.addons = [];
    sourceBlock.classList.add('vms-rw-addons', 'vms-entitlements--compact');
    sourceBlock.setAttribute('data-vms-rewrite', '1');
    sourceBlock.setAttribute('data-vms-addons-mounted', '1');
    sourceBlock.innerHTML = '';

    var heading = createEl('div', 'vms-rw-addons__header');
    var title = createEl('h3', 'vms-rw-addons__title', String(headingText).trim());
    var intro = createEl('p', 'vms-rw-addons__intro', addonIntroText);
    heading.appendChild(title);
    heading.appendChild(intro);
    sourceBlock.appendChild(heading);

    var list = createEl('div', 'vms-rw-addon-list vms-entitlements-list');
    sourceBlock.appendChild(list);

    queryAll('.vms-entitlements-list > .vms-ent-row, .vms-entitlements-list > .vms-entitlements-item', state.sourceTemplate).forEach(function (row) {
      var addLink = query('.vms-entitlements-add', row);
      var soldOut = query('.vms-entitlements-soldout', row);
      var href = addLink ? String(addLink.getAttribute('href') || '') : '';
      var hrefMatch = href.match(/add-to-cart=(\d+)/);
      var productId = toInt(row.getAttribute('data-vms-product-id') || (hrefMatch ? hrefMatch[1] : 0), 0);
      if (productId <= 0) {
        return;
      }

      var selectorMode = String((row.getAttribute('data-vms-selector-mode') || (addLink && addLink.getAttribute('data-vms-selector-mode')) || '').trim()) === 'checkbox' ? 'checkbox' : 'stepper';
      var model = {
        productId: productId,
        label: ((query('.vms-ent-title, .vms-entitlements-label', row) || {}).textContent || 'Add-on').trim(),
        priceHtml: ((query('.vms-ent-price, .vms-entitlements-price', row) || {}).innerHTML || '').trim(),
        unitPrice: parseMoney((query('.vms-ent-price, .vms-entitlements-price', row) || {}).innerHTML || ''),
        descNode: copyNode(query('.vms-ent-descline', row)),
        imageNode: copyNode(query('.vms-ent-img', row)),
        soldOutText: soldOut ? String(soldOut.textContent || '').trim() : '',
        poolKey: (addLink && addLink.getAttribute('data-vms-pool-key')) || row.getAttribute('data-vms-pool-key') || '',
        poolMax: toInt((addLink && addLink.getAttribute('data-vms-pool-max')) || row.getAttribute('data-vms-pool-max'), 0),
        minGa: toInt((addLink && addLink.getAttribute('data-vms-pool-min-ga')) || row.getAttribute('data-vms-min-ga') || 0, 0),
        maxQty: toInt((addLink && addLink.getAttribute('data-vms-max-qty')) || row.getAttribute('data-vms-max-qty') || 0, 0),
        canAdd: !!addLink,
        selectorMode: selectorMode,
        isCheckbox: selectorMode === 'checkbox',
        qty: 0,
        rowEl: null,
        inputEl: null,
        minusEl: null,
        plusEl: null,
        noteEl: null,
        statusEl: null,
        checkboxWrapEl: null,
        checkboxLabelEl: null
      };

      var card = createEl('article', 'vms-rw-addon vms-entitlements-item vms-ent-row');
      card.setAttribute('data-product-id', String(model.productId));
      if (model.poolKey) {
        card.setAttribute('data-vms-pool-key', model.poolKey);
      }
      if (!model.imageNode) {
        card.classList.add('vms-rw-addon--no-image', 'vms-ent-row--no-image');
      }

      if (model.imageNode) {
        var imageWrap = createEl('div', 'vms-rw-addon__image vms-ent-img');
        imageWrap.appendChild(model.imageNode.firstChild ? model.imageNode.firstChild.cloneNode(true) : model.imageNode);
        card.appendChild(imageWrap);
      }

      var main = createEl('div', 'vms-rw-addon__main vms-ent-main');
      var mainHead = createEl('div', 'vms-rw-addon__head');
      mainHead.appendChild(createEl('strong', 'vms-rw-addon__title vms-ent-title vms-entitlements-label', model.label));
      if (model.priceHtml) {
        var price = createEl('div', 'vms-rw-addon__price vms-ent-price vms-entitlements-price');
        price.innerHTML = model.priceHtml;
        mainHead.appendChild(price);
      }
      main.appendChild(mainHead);
      if (model.descNode) {
        main.appendChild(model.descNode);
      }
      var note = createEl('div', 'vms-rw-addon__note vms-ent-note');
      note.setAttribute('aria-live', 'polite');
      main.appendChild(note);
      card.appendChild(main);

      var side = createEl('div', 'vms-rw-addon__side vms-ent-side');
      if (model.canAdd) {
        var stepper = createEl('div', 'vms-rw-stepper vms-ent-qty ' + (model.isCheckbox ? 'vms-addon-controls vms-addon-controls--checkbox' : 'vms-addon-controls vms-addon-controls--stepper'));
        if (model.isCheckbox) {
          var checkboxWrap = createEl('label', 'vms-addon-checkbox-wrap');
          var input = createEl('input', 'vms-addon-input vms-addon-input--checkbox');
          input.type = 'checkbox';
          input.value = '1';
          input.setAttribute('aria-label', 'Select ' + model.label);
          var checkboxLabel = createEl('span', 'vms-addon-checkbox-label', 'Reserve');
          checkboxWrap.appendChild(input);
          checkboxWrap.appendChild(checkboxLabel);
          var minus = createEl('button', 'vms-rw-stepper__btn vms-rw-stepper__btn--minus vms-addon-minus vms-hidden', '−');
          minus.type = 'button';
          minus.tabIndex = -1;
          minus.setAttribute('aria-hidden', 'true');
          var plus = createEl('button', 'vms-rw-stepper__btn vms-rw-stepper__btn--plus vms-addon-plus vms-hidden', '+');
          plus.type = 'button';
          plus.tabIndex = -1;
          plus.setAttribute('aria-hidden', 'true');
          stepper.appendChild(checkboxWrap);
          stepper.appendChild(minus);
          stepper.appendChild(plus);
          side.appendChild(stepper);
          model.inputEl = input;
          model.minusEl = minus;
          model.plusEl = plus;
          model.checkboxWrapEl = checkboxWrap;
          model.checkboxLabelEl = checkboxLabel;
        } else {
          var minus = createEl('button', 'vms-rw-stepper__btn vms-rw-stepper__btn--minus vms-addon-minus', '−');
          minus.type = 'button';
          minus.setAttribute('aria-label', 'Decrease add-on quantity');
          var input = createEl('input', 'vms-rw-stepper__input vms-addon-input');
          input.type = 'text';
          input.inputMode = 'numeric';
          input.setAttribute('pattern', '[0-9]*');
          input.setAttribute('value', '0');
          input.value = '0';
          input.setAttribute('aria-label', model.label + ' quantity');
          var plus = createEl('button', 'vms-rw-stepper__btn vms-rw-stepper__btn--plus vms-addon-plus', '+');
          plus.type = 'button';
          plus.setAttribute('aria-label', 'Increase add-on quantity');
          stepper.appendChild(minus);
          stepper.appendChild(input);
          stepper.appendChild(plus);
          side.appendChild(stepper);
          model.inputEl = input;
          model.minusEl = minus;
          model.plusEl = plus;
        }
      } else {
        side.appendChild(createEl('div', 'vms-rw-addon__soldout vms-entitlements-soldout', model.soldOutText || 'Unavailable'));
      }

      var status = createEl('div', 'vms-rw-addon__status');
      side.appendChild(status);
      card.appendChild(side);

      model.rowEl = card;
      model.noteEl = note;
      model.statusEl = status;
      state.addons.push(model);
      list.appendChild(card);
    });

    if (preservedAuxInputs.length) {
      var preservedWrap = createEl('div', 'vms-rw-addon-native-preserve');
      preservedWrap.hidden = true;
      preservedWrap.setAttribute('aria-hidden', 'true');
      preservedAuxInputs.forEach(function (input) {
        preservedWrap.appendChild(input);
      });
      sourceBlock.appendChild(preservedWrap);
    }
  }

  function getErrorMessage(payload) {
    if (!payload || !payload.data) {
      return 'Could not add items to cart.';
    }
    if (Array.isArray(payload.data.notice_messages) && payload.data.notice_messages.length) {
      return String(payload.data.notice_messages[0] || 'Could not add items to cart.');
    }
    if (payload.data.message) {
      return String(payload.data.message);
    }
    return 'Could not add items to cart.';
  }

  function bindAddonStepperButton(button, handler) {
    if (!button || typeof handler !== 'function') {
      return;
    }
    var ignoreClickUntil = 0;
    var lastTouchHandleAt = 0;

    button.addEventListener('pointerup', function (event) {
      var pointerType = event && event.pointerType ? String(event.pointerType).toLowerCase() : '';
      if (pointerType !== 'touch') {
        return;
      }
      if (Date.now() - lastTouchHandleAt < 80) {
        return;
      }
      lastTouchHandleAt = Date.now();
      ignoreClickUntil = Date.now() + 500;
      if (event) {
        event.preventDefault();
        event.stopPropagation();
        if (event.stopImmediatePropagation) {
          event.stopImmediatePropagation();
        }
      }
      handler();
    }, true);

    button.addEventListener('touchend', function (event) {
      if (Date.now() - lastTouchHandleAt < 80) {
        return;
      }
      lastTouchHandleAt = Date.now();
      ignoreClickUntil = Date.now() + 500;
      if (event) {
        event.preventDefault();
        event.stopPropagation();
        if (event.stopImmediatePropagation) {
          event.stopImmediatePropagation();
        }
      }
      handler();
    }, true);

    button.addEventListener('click', function (event) {
      if (ignoreClickUntil && Date.now() < ignoreClickUntil) {
        if (event) {
          event.preventDefault();
          event.stopPropagation();
          if (event.stopImmediatePropagation) {
            event.stopImmediatePropagation();
          }
        }
        return;
      }
      handler();
    }, true);
  }

  function bindAddonCheckboxToggle(addon, state, options) {
    if (!addon || !addon.isCheckbox || !addon.inputEl || !state) {
      return;
    }

    var opts = options || {};
    var clearGlobalMessage = !!opts.clearGlobalMessage;
    var wrap = addon.checkboxWrapEl || addon.inputEl;
    var ignoreClickUntil = 0;
    var lastTouchHandleAt = 0;

    function suppressEvent(event) {
      if (!event) {
        return;
      }
      event.preventDefault();
      event.stopPropagation();
      if (event.stopImmediatePropagation) {
        event.stopImmediatePropagation();
      }
    }

    function applyTouchToggle(event) {
      if (!addon.inputEl || addon.inputEl.disabled) {
        return;
      }
      ignoreClickUntil = Date.now() + 500;
      suppressEvent(event);
      addon.qty = addon.qty > 0 ? 0 : 1;
      addon.inputEl.checked = addon.qty > 0;
      addon.inputEl.value = addon.qty > 0 ? '1' : '0';
      refresh(state, { clampAddons: true, clearGlobalMessage: clearGlobalMessage });
    }

    wrap.addEventListener('pointerup', function (event) {
      var pointerType = event && event.pointerType ? String(event.pointerType).toLowerCase() : '';
      if (pointerType !== 'touch') {
        return;
      }
      if (Date.now() - lastTouchHandleAt < 80) {
        return;
      }
      lastTouchHandleAt = Date.now();
      applyTouchToggle(event);
    }, true);

    wrap.addEventListener('touchend', function (event) {
      if (Date.now() - lastTouchHandleAt < 80) {
        return;
      }
      lastTouchHandleAt = Date.now();
      applyTouchToggle(event);
    }, true);

    wrap.addEventListener('click', function (event) {
      if (!(ignoreClickUntil && Date.now() < ignoreClickUntil)) {
        return;
      }
      suppressEvent(event);
    }, true);
  }

  function bindSubmitButton(button, state) {
    if (!button || !state) {
      return;
    }
    var ignoreClickUntil = 0;

    function invoke(event) {
      if (event) {
        event.preventDefault();
        event.stopPropagation();
        if (event.stopImmediatePropagation) {
          event.stopImmediatePropagation();
        }
      }
      handleAtomicSubmit(state);
    }

    button.addEventListener('pointerup', function (event) {
      ignoreClickUntil = Date.now() + 500;
      invoke(event);
    }, true);

    button.addEventListener('touchend', function (event) {
      ignoreClickUntil = Date.now() + 500;
      invoke(event);
    }, true);

    button.addEventListener('click', function (event) {
      if (ignoreClickUntil && Date.now() < ignoreClickUntil) {
        if (event) {
          event.preventDefault();
          event.stopPropagation();
          if (event.stopImmediatePropagation) {
            event.stopImmediatePropagation();
          }
        }
        return;
      }
      invoke(event);
    }, true);
  }

  function requestNativeSubmit(state) {
    if (!state || !state.form) {
      return;
    }
    state.allowNativeSubmit = true;
    var submitter = state.submitButtons && state.submitButtons.length ? state.submitButtons[0] : null;
    if (typeof state.form.requestSubmit === 'function') {
      try {
        if (submitter) {
          state.form.requestSubmit(submitter);
        } else {
          state.form.requestSubmit();
        }
        return;
      } catch (err) {
        state.allowNativeSubmit = false;
      }
    }

    try {
      var submitEvent = new Event('submit', { bubbles: true, cancelable: true });
      var defaultAllowed = state.form.dispatchEvent(submitEvent);
      if (!defaultAllowed) {
        state.allowNativeSubmit = false;
        return;
      }
    } catch (err) {
      // ignore and use the native form submit below
    }

    state.allowNativeSubmit = false;
    if (typeof HTMLFormElement !== 'undefined' && HTMLFormElement.prototype && typeof HTMLFormElement.prototype.submit === 'function') {
      HTMLFormElement.prototype.submit.call(state.form);
      return;
    }
    state.form.submit();
  }

  function handleAtomicSubmit(state) {
    if (state.isSubmitting) {
      return;
    }

    var ticketBuild = buildAtomicTicketLines(state, { requireComplete: true });
    var ticketLines = ticketBuild.ok ? ticketBuild.ticketLines : [];
    var addonLines = collectAddonLines(state);
    if (!ticketBuild.ok) {
      refresh(state, { clampAddons: true });
      setGlobalMessage(state, ticketBuild.message, 'error');
      if (ticketBuild.focusEl && typeof ticketBuild.focusEl.focus === 'function') {
        ticketBuild.focusEl.focus();
      }
      return;
    }

    if (!ticketLines.length && !addonLines.length) {
      if (hasNativeTicketSelection(state)) {
        requestNativeSubmit(state);
        return;
      }
      refresh(state, { clampAddons: true });
      return;
    }

    state.isSubmitting = true;
    ensureStatusHidden(state);
    refresh(state, { clampAddons: true });

    Promise.resolve()
      .then(function () { return fetchCartContextBeforeSubmit(state, addonLines); })
      .then(function () {
        refresh(state, { clampAddons: true });
        ticketBuild = buildAtomicTicketLines(state, { requireComplete: true });
        if (!ticketBuild.ok) {
          throw new Error(ticketBuild.message || 'Please add one approved guest email per selected ticket before adding tickets to your cart.');
        }
        ticketLines = ticketBuild.ticketLines;
        addonLines = collectAddonLines(state);

        if (!cfg.atomicAddUrl || !cfg.atomicAddNonce) {
          diagLog('atomic-add-missing-config');
          if (!addonLines.length) {
            state.isSubmitting = false;
            refresh(state, { clampAddons: true });
            requestNativeSubmit(state);
            return null;
          }
          throw new Error('Atomic add endpoint unavailable.');
        }

        diagLog('atomic-add-request', { ticketLines: ticketLines, addonLines: addonLines });
        return fetch(normalizeUrl(cfg.atomicAddUrl), {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            nonce: cfg.atomicAddNonce,
            tecEventId: state.tecEventId,
            eventPlanId: state.eventPlanId,
            ticket_lines: ticketLines,
            addon_lines: addonLines
          })
        }).then(function (response) {
          return response.json().catch(function () {
            return { success: false, data: { message: 'Could not add items to cart.' } };
          });
        }).then(function (payload) {
          if (!payload || !payload.success || !payload.data || !payload.data.ok) {
            throw new Error(getErrorMessage(payload));
          }
          setGlobalMessage(state, 'Added to cart. Redirecting…', 'success');
          window.location.href = payload.data.cart_url || cfg.cartUrl || normalizeUrl('/cart/');
          return payload;
        });
      })
      .catch(function (err) {
        state.isSubmitting = false;
        refresh(state, { clampAddons: true });
        setGlobalMessage(state, err && err.message ? String(err.message) : 'Could not add items to cart.', 'error');
      });
  }

  function installPageShowReset() {
    window.addEventListener('pageshow', function (event) {
      if (!event || !event.persisted) {
        return;
      }
      queryAll('.vms-cart-overlay').forEach(function (node) {
        if (node && node.parentNode) {
          node.parentNode.removeChild(node);
        }
      });
      queryAll('#tribe-tickets__tickets-submit, .tribe-tickets__tickets-buy').forEach(function (button) {
        button.removeAttribute('data-vms-busy');
        button.removeAttribute('data-vms-busy-since');
        var label = button.getAttribute('data-vms-label-default');
        if (!label) {
          return;
        }
        if (button.tagName && button.tagName.toLowerCase() === 'input') {
          button.value = label;
        } else {
          button.textContent = label;
        }
        setDisabled(button, false);
      });
    });
  }

  function init() {
    var bundle = window.BVMGR_TICKETING_FRONT_BUNDLE || {};
    if (repairMountedState(bundle.state)) {
      return true;
    }

    var sourceBlock = query(SELECTORS.addonSource);
    if (sourceBlock && String(sourceBlock.getAttribute('data-vms-inline-controller-active') || '') === '1') {
      diagLog('inline-controller-active');
      return true;
    }
    var form = resolveForm(sourceBlock);
    DIAG.flags.initCalls += 1;
    DIAG.flags.sourceFound = !!sourceBlock;
    DIAG.flags.formFound = !!form;
    DIAG.flags.serverControlsMode = !!(sourceBlock && String(sourceBlock.getAttribute('data-vms-render-mode') || '') === 'server_controls');
    diagLog('init-scan', { sourceFound: !!sourceBlock, formFound: !!form, renderMode: sourceBlock ? String(sourceBlock.getAttribute('data-vms-render-mode') || '') : '' });
    renderDiagPanel();
    if (!form) {
      return false;
    }
    if (!sourceBlock) {
      var ticketOnlyState = buildTicketOnlyState(form);
      if (activateTicketOnlyState(ticketOnlyState)) {
        DIAG.flags.initSuccess = true;
        diagLog('init-ticket-only-success', { ticketAccessCount: Object.keys(ticketOnlyState.ticketAccessMap || {}).length });
        renderDiagPanel();
        window.BVMGR_TICKETING_FRONT_BUNDLE.state = ticketOnlyState;
        return true;
      }
      return initTicketOnlyBridge(form);
    }
    if (String(sourceBlock.getAttribute('data-vms-render-mode') || '') === 'server_controls') {
      var serverState = buildServerControlsState(sourceBlock, form);
      if (activateServerControlsState(serverState)) {
        DIAG.flags.initSuccess = true;
        diagLog('init-server-controls-success', { addons: serverState.addons.length });
        renderDiagPanel();
        window.BVMGR_TICKETING_FRONT_BUNDLE.state = serverState;
        return true;
      }
      return false;
    }

    if (form.getAttribute('data-vms-ticketing-rewrite') === '1') {
      var existingState = bundle.state;
      if (existingState && existingState.form === form && existingState.sourceBlock === sourceBlock) {
        diagLog('repair-existing-state');
      return repairMountedState(existingState);
      }
      form.removeAttribute('data-vms-ticketing-rewrite');
    }
    form.setAttribute('data-vms-ticketing-rewrite', '1');

    var state = {
      form: form,
      sourceBlock: sourceBlock,
      sourceTemplate: sourceBlock.cloneNode(true),
      tecEventId: toInt(cfg.tecEventId || sourceBlock.getAttribute('data-vms-tec-event-id'), 0),
      eventPlanId: toInt(cfg.eventPlanId || sourceBlock.getAttribute('data-vms-event-plan-id'), 0),
      gaProductId: toInt(sourceBlock.getAttribute('data-vms-ga-product-id'), 0),
      qualifyingTicketIds: uniqueInts(String(sourceBlock.getAttribute('data-vms-qualifying-ticket-product-ids') || '').split(',')),
      priorQualifyingQty: Math.max(0, toInt(sourceBlock.getAttribute('data-vms-prior-qualifying-qty'), 0)),
      priorPoolQtyByKey: safeParseJson(sourceBlock.getAttribute('data-vms-prior-pool-qty'), {}),
      cartGaQty: Math.max(0, toInt(sourceBlock.getAttribute('data-vms-cart-ga-qty'), 0)),
      cartPoolQtyByKey: safeParseJson(sourceBlock.getAttribute('data-vms-cart-pool-qty'), {}),
      ticketAccessMap: mapObject(cfg.ticketAccessMap),
      ticketRemainingMap: mapObject(cfg.ticketRemainingMap),
      ticketPriceMap: (cfg.ticketPriceMap && typeof cfg.ticketPriceMap === 'object') ? cfg.ticketPriceMap : {},
      disabledTicketMap: mapObject(cfg.disabledTicketMap),
      disabledTicketProductIds: uniqueInts(cfg.disabledTicketProductIds || []),
      ticketClaimsByProduct: {},
      addons: [],
      submitButtons: [],
      footer: query(SELECTORS.footer, form) || null,
      summarySection: null,
      summaryStack: null,
      actionStack: null,
      subtotalNode: null,
      subtotalTicketNode: null,
      subtotalAddonNode: null,
      subtotalAllNode: null,
      subtotalSecondaryNode: null,
      subtotalAddonSummaryNode: null,
      statusBox: null,
      isSubmitting: false,
      cartContextPending: null,
      originalSubmitLabel: null,
      refreshRafScheduled: false,
      allowNativeSubmit: false
    };

    if (!state.qualifyingTicketIds.length && state.gaProductId > 0) {
      state.qualifyingTicketIds = [state.gaProductId];
    }

    bundle.state = state;
    try {
      mountSourceBlock(state);
      ensureActionStack(state);
      buildFreshAddonUi(state);
      bindEvents(state);
      refresh(state, { clampAddons: true, clearGlobalMessage: true });
      fetchCartContext(state).finally(function () {
        refresh(state, { clampAddons: true, clearGlobalMessage: false });
      });
      DIAG.flags.initSuccess = true;
      diagLog('init-legacy-success', { addons: state.addons.length });
      renderDiagPanel();
      return true;
    } catch (err) {
      form.removeAttribute('data-vms-ticketing-rewrite');
      bundle.state = null;
      diagLog('init-error', { message: err && err.message ? err.message : String(err || '') });
      renderDiagPanel();
      if (window.console && typeof window.console.error === 'function') {
        window.console.error('[VMS ticketing] init failed', err);
      }
      return false;
    }
  }

  function boot() {
    syncMyTicketsNotice();
    var bundle = window.BVMGR_TICKETING_FRONT_BUNDLE || {};
    DIAG.flags.bootCalls += 1;
    diagLog('boot-attempt', { attempt: DIAG.flags.bootCalls });
    renderDiagPanel();
    if (init()) {
      return;
    }

    bundle.bootAttempts = toInt(bundle.bootAttempts, 0) + 1;
    if (bundle.bootAttempts <= 20) {
      window.clearTimeout(bundle.bootTimer || 0);
      bundle.bootTimer = window.setTimeout(boot, 150);
    }
  }

  function installDomObserver() {
    var bundle = window.BVMGR_TICKETING_FRONT_BUNDLE || {};
    if (bundle.observer || !document.body || typeof MutationObserver === 'undefined') {
      return;
    }

    function nodeWithinManagedState(node, state) {
      var el = node && node.nodeType === 1 ? node : (node && node.parentElement ? node.parentElement : null);
      if (!el || !state) {
        return false;
      }
      if (state.form && (el === state.form || state.form.contains(el))) {
        return true;
      }
      if (state.sourceBlock && (el === state.sourceBlock || state.sourceBlock.contains(el))) {
        return true;
      }
      if (state.footer && (el === state.footer || state.footer.contains(el))) {
        return true;
      }
      if (state.summarySection && (el === state.summarySection || state.summarySection.contains(el))) {
        return true;
      }
      return !!(DIAG.panel && (el === DIAG.panel || DIAG.panel.contains(el)));
    }

    function nodeTouchesTicketingSurface(node, state) {
      var el = node && node.nodeType === 1 ? node : (node && node.parentElement ? node.parentElement : null);
      if (!el) {
        return false;
      }

      if (nodeWithinManagedState(el, state)) {
        return true;
      }

      if (el.matches && (
        el.matches('#tribe-tickets')
        || el.matches('.tribe-tickets__tickets')
        || el.matches(SELECTORS.form)
        || el.matches(SELECTORS.addonSource)
      )) {
        return true;
      }

      return !!(el.querySelector && (
        el.querySelector('#tribe-tickets')
        || el.querySelector('.tribe-tickets__tickets')
        || el.querySelector(SELECTORS.form)
        || el.querySelector(SELECTORS.addonSource)
      ));
    }

    function mutationMayRequireTicketingBoot(records, state) {
      if (!Array.isArray(records) || !records.length) {
        return false;
      }

      if (!stateBelongsToCurrentDocument(state)) {
        return true;
      }

      return records.some(function (record) {
        if (!record) {
          return false;
        }

        if (nodeTouchesTicketingSurface(record.target, state) && !nodeWithinManagedState(record.target, state)) {
          return true;
        }

        var nodes = [];
        if (record.addedNodes && record.addedNodes.length) {
          nodes = nodes.concat(Array.prototype.slice.call(record.addedNodes));
        }
        if (record.removedNodes && record.removedNodes.length) {
          nodes = nodes.concat(Array.prototype.slice.call(record.removedNodes));
        }

        return nodes.some(function (node) {
          return nodeTouchesTicketingSurface(node, state) && !nodeWithinManagedState(node, state);
        });
      });
    }

    bundle.observer = new MutationObserver(function (records) {
      var list = Array.isArray(records) ? records : Array.prototype.slice.call(records || []);
      if (!mutationMayRequireTicketingBoot(list, bundle.state || null)) {
        return;
      }
      diagLog('body-mutation-observed');
      window.clearTimeout(bundle.bootTimer || 0);
      bundle.bootTimer = window.setTimeout(function () {
        if (!init()) {
          boot();
        }
      }, 60);
    });

    bundle.observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  }

  installPageShowReset();
  installDomObserver();
  startDiagPanel();

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();
