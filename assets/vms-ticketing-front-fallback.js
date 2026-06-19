(function () {
  'use strict';

  var cfg = window.vmsTicketingFront || {};
  function configFlag(value) {
    if (value === true || value === 1) {
      return true;
    }
    var normalized = String(value == null ? '' : value).trim().toLowerCase();
    return normalized === '1' || normalized === 'true' || normalized === 'yes';
  }

  function useV2Layout() {
    var layout = String(cfg.uiLayout || '').trim().toLowerCase();
    return layout !== 'classic';
  }

  function activeBundleOwnsPage() {
    var bundle = window.__vmsTicketingFrontBundle || {};
    var state = bundle.state || null;
    return !!(bundle.loaded && state && state.form && state.form.ownerDocument === document && state.form.isConnected);
  }

  if (configFlag(cfg.isCart) || configFlag(cfg.isCheckout) || useV2Layout() || activeBundleOwnsPage()) {
    return;
  }

  var SELECTORS = {
    form: '#tribe-tickets__tickets-form, .tribe-tickets__tickets-wrapper form, .tribe-tickets__tickets-form',
    addonSource: '#vms-reserved-addons.vms-entitlements-block',
    footer: '.tribe-tickets__tickets-footer, .tribe-tickets__footer, .tribe-common-c-btn-group',
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

  function q(selector, root) {
    return (root || document).querySelector(selector);
  }

  function qa(selector, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(selector));
  }

  function toInt(value, fallback) {
    var parsed = parseInt(String(value == null ? '' : value), 10);
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

  function parseJson(raw, fallback) {
    try {
      var parsed = JSON.parse(String(raw || ''));
      return parsed && typeof parsed === 'object' ? parsed : fallback;
    } catch (err) {
      return fallback;
    }
  }

  function resolveForm() {
    return q('#tribe-tickets__tickets-form') || q(SELECTORS.form);
  }

  function hasLegacyAddLinks(block) {
    return !!(block && q('a.vms-entitlements-add', block));
  }

  function resolveSourceBlock(form) {
    return (form && q(SELECTORS.addonSource, form)) || q(SELECTORS.addonSource);
  }

  function getBundleState() {
    return (window.__vmsTicketingFrontBundle && window.__vmsTicketingFrontBundle.state) || null;
  }

  function alreadyHandled(block) {
    if (!block) {
      return false;
    }
    if (hasLegacyAddLinks(block)) {
      return false;
    }
    var bundleState = getBundleState();
    if (bundleState && bundleState.sourceBlock === block && bundleState.addons && bundleState.addons.length) {
      return true;
    }
    if (block.getAttribute('data-vms-fallback-active') === '1') {
      return true;
    }
    if (q('.vms-rw-addon-list', block)) {
      return true;
    }
    return false;
  }

  function inferProductId(row, addLink) {
    var candidates = [
      row && row.getAttribute('data-vms-product-id'),
      row && row.getAttribute('data-product-id')
    ];
    if (addLink) {
      var href = String(addLink.getAttribute('href') || '');
      var match = href.match(/add-to-cart=(\d+)/);
      if (match) {
        candidates.push(match[1]);
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

  function inferTicketProductId(input) {
    if (!input) {
      return 0;
    }
    var row = input.closest('[data-ticket-id], [data-product-id], .tribe-tickets__item, .tribe-tickets__tickets-item');
    var candidates = [];
    if (row && row.dataset) {
      candidates.push(row.dataset.productId, row.dataset.ticketId, row.dataset.product, row.dataset.ticket);
    }
    if (input.id) {
      var idMatch = String(input.id).match(/(\d+)(?!.*\d)/);
      if (idMatch) {
        candidates.push(idMatch[1]);
      }
    }
    if (input.name) {
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

  function disabledTicketMap() {
    return cfg.disabledTicketMap && typeof cfg.disabledTicketMap === 'object' ? cfg.disabledTicketMap : {};
  }

  function isDisabledPendingSyncTicketProductId(productId) {
    var id = toInt(productId, 0);
    return id > 0 && !!disabledTicketMap()[String(id)];
  }

  function getTicketRow(input) {
    return input && input.closest ? input.closest('[data-ticket-id], [data-product-id], .tribe-tickets__item, .tribe-tickets__tickets-item') : null;
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
    qa('button, input, select, textarea', row).forEach(function (control) {
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
    qa(SELECTORS.nativeQty, state.form).forEach(function (input) {
      var productId = inferTicketProductId(input);
      if (!isDisabledPendingSyncTicketProductId(productId)) {
        return;
      }
      disableNativeTicketRow(getTicketRow(input), input);
      hiddenCount += 1;
    });
    state.suppressedDisabledTicketCount = hiddenCount;
    return hiddenCount;
  }

  function readTicketLines(state) {
    hideDisabledTicketRows(state);
    return qa(SELECTORS.nativeQty, state.form).map(function (input) {
      var productId = inferTicketProductId(input);
      var row = getTicketRow(input);
      return {
        product_id: productId,
        qty: Math.max(0, toInt(input.value, 0)),
        hidden: !!(row && row.hidden),
        disabled: !!input.disabled
      };
    }).filter(function (line) {
      return line.product_id > 0 && line.qty > 0 && !line.disabled && !line.hidden && !isDisabledPendingSyncTicketProductId(line.product_id);
    });
  }

  function selectedQualifyingQty(state) {
    var lines = readTicketLines(state);
    if (!state.qualifyingTicketIds.length) {
      return lines.reduce(function (sum, line) { return sum + line.qty; }, 0);
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

  function computeAddonLimit(state, addon) {
    if (!addon.canAdd) {
      return 0;
    }

    var qualifyingQty = Math.max(0, state.priorQualifyingQty + state.cartGaQty + selectedQualifyingQty(state));
    var existingPoolQty = addon.poolKey
      ? Math.max(0, toInt(state.priorPoolQtyByKey[addon.poolKey], 0) + toInt(state.cartPoolQtyByKey[addon.poolKey], 0))
      : 0;
    var selectedOtherPoolQty = addon.poolKey ? selectedAddonPoolQty(state, addon.poolKey, addon.productId) : 0;
    var maxByTicket = Number.POSITIVE_INFINITY;
    var maxByPool = Number.POSITIVE_INFINITY;
    var maxByItem = Number.POSITIVE_INFINITY;

    if (addon.minGa > 0) {
      maxByTicket = Math.max(0, Math.floor(qualifyingQty / addon.minGa) - existingPoolQty - selectedOtherPoolQty);
    }
    if (addon.poolKey && addon.poolMax > 0) {
      maxByPool = Math.max(0, addon.poolMax - existingPoolQty - selectedOtherPoolQty);
    }
    if (addon.maxQty > 0) {
      maxByItem = addon.maxQty;
    }

    return Math.max(0, Math.min(maxByTicket, maxByPool, maxByItem));
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

  function ensureStatusBox(state) {
    if (state.statusBox && state.statusBox.isConnected) {
      return state.statusBox;
    }
    var footer = q(SELECTORS.footer, state.form);
    if (!footer) {
      return null;
    }
    var box = document.getElementById('vms-addons-direct-add-status');
    if (!box) {
      box = document.createElement('div');
      box.id = 'vms-addons-direct-add-status';
      box.className = 'vms-addons-direct-add-status';
      box.hidden = true;
      footer.parentNode.insertBefore(box, footer);
    }
    state.statusBox = box;
    return box;
  }

  function setGlobalMessage(state, text, type) {
    var box = ensureStatusBox(state);
    if (!box) {
      return;
    }
    box.hidden = !text;
    box.classList.remove('is-error', 'is-success');
    if (type === 'error' || type === 'success') {
      box.classList.add('is-' + type);
    }
    box.textContent = text || '';
  }

  function refresh(state) {
    hideDisabledTicketRows(state);
    state.addons.forEach(function (addon) {
      var limit = computeAddonLimit(state, addon);
      addon.qty = clamp(addon.qty, 0, limit);
      if (addon.inputEl) {
        addon.inputEl.value = String(addon.qty);
        addon.inputEl.setAttribute('max', String(limit));
      }
      setDisabled(addon.minusEl, addon.qty <= 0 || state.isSubmitting);
      setDisabled(addon.plusEl, addon.qty >= limit || !addon.canAdd || state.isSubmitting);

      var note = '';
      if (!addon.canAdd) {
        note = addon.soldOutText || 'Unavailable';
      } else if (limit <= 0 && addon.minGa > 0) {
        note = addon.qty > 0 ? 'Adjusted to available ticket count.' : 'Requires qualifying ticket.';
      } else if (addon.poolKey && addon.poolMax > 0 && addon.qty >= limit && limit > 0) {
        note = 'Pool limit reached.';
      } else if (addon.minGa > 0) {
        note = 'Requires ' + String(addon.minGa) + ' qualifying ticket' + (addon.minGa === 1 ? '' : 's') + '.';
      }

      if (addon.noteEl) {
        addon.noteEl.textContent = note;
      }
      if (addon.statusEl) {
        addon.statusEl.textContent = addon.qty > 0 ? String(addon.qty) + ' selected' : '';
      }
      if (addon.rowEl && addon.rowEl.classList) {
        addon.rowEl.classList.toggle('is-selected', addon.qty > 0);
      }
    });
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

  function wireRow(state, row) {
    var addLink = q('.vms-entitlements-add', row);
    var soldOut = q('.vms-entitlements-soldout', row);
    var productId = inferProductId(row, addLink);
    if (productId <= 0) {
      return;
    }

    row.classList.add('vms-rw-addon');
    var qtyWrap = q('.vms-entitlements-qty, .vms-ent-qty', row);
    var side = q('.vms-ent-side, .vms-entitlements-side', row) || qtyWrap || row;
    var noteEl = q('.vms-ent-note', row);
    var statusEl = q('.vms-rw-addon__status', row);
    if (!statusEl) {
      statusEl = document.createElement('div');
      statusEl.className = 'vms-rw-addon__status';
      side.appendChild(statusEl);
    }

    var model = {
      productId: productId,
      label: ((q('.vms-ent-title, .vms-entitlements-label', row) || {}).textContent || 'Add-on').trim(),
      poolKey: (addLink && addLink.getAttribute('data-vms-pool-key')) || row.getAttribute('data-vms-pool-key') || '',
      poolMax: toInt((addLink && addLink.getAttribute('data-vms-pool-max')) || row.getAttribute('data-vms-pool-max'), 0),
      minGa: toInt((addLink && addLink.getAttribute('data-vms-pool-min-ga')) || 0, 0),
      maxQty: toInt((addLink && addLink.getAttribute('data-vms-max-qty')) || 0, 0),
      canAdd: !!addLink,
      soldOutText: soldOut ? String(soldOut.textContent || '').trim() : '',
      qty: 0,
      rowEl: row,
      noteEl: noteEl,
      statusEl: statusEl,
      inputEl: null,
      minusEl: null,
      plusEl: null
    };

    if (qtyWrap && addLink) {
      addLink.hidden = true;
      addLink.classList.add('vms-ent-link-hidden');
      qtyWrap.innerHTML = '';

      var stepper = document.createElement('div');
      stepper.className = 'vms-rw-stepper vms-ent-qty';

      var minus = document.createElement('button');
      minus.type = 'button';
      minus.className = 'vms-rw-stepper__btn vms-rw-stepper__btn--minus vms-addon-minus';
      minus.setAttribute('aria-label', 'Decrease add-on quantity');
      minus.textContent = '−';

      var input = document.createElement('input');
      input.type = 'text';
      input.className = 'vms-rw-stepper__input vms-addon-input';
      input.inputMode = 'numeric';
      input.setAttribute('pattern', '[0-9]*');
      input.value = '0';
      input.setAttribute('aria-label', model.label + ' quantity');

      var plus = document.createElement('button');
      plus.type = 'button';
      plus.className = 'vms-rw-stepper__btn vms-rw-stepper__btn--plus vms-addon-plus';
      plus.setAttribute('aria-label', 'Increase add-on quantity');
      plus.textContent = '+';

      stepper.appendChild(minus);
      stepper.appendChild(input);
      stepper.appendChild(plus);
      qtyWrap.appendChild(stepper);

      minus.addEventListener('click', function () {
        model.qty = Math.max(0, model.qty - 1);
        refresh(state);
      });
      plus.addEventListener('click', function () {
        model.qty = model.qty + 1;
        refresh(state);
      });
      input.addEventListener('input', function () {
        model.qty = Math.max(0, toInt(input.value, 0));
        refresh(state);
      });
      input.addEventListener('change', function () {
        model.qty = Math.max(0, toInt(input.value, 0));
        refresh(state);
      });

      model.inputEl = input;
      model.minusEl = minus;
      model.plusEl = plus;
    }

    state.addons.push(model);
  }

  function submitAtomically(state) {
    if (state.isSubmitting) {
      return;
    }

    hideDisabledTicketRows(state);
    var addonLines = collectAddonLines(state);
    if (!addonLines.length) {
      return;
    }

    if (!cfg.atomicAddUrl || !cfg.atomicAddNonce) {
      setGlobalMessage(state, 'Atomic add endpoint unavailable.', 'error');
      return;
    }

    state.isSubmitting = true;
    setGlobalMessage(state, '', '');
    refresh(state);

    var submitButtons = qa(SELECTORS.submit, state.form);
    submitButtons.forEach(function (button) {
      setDisabled(button, true);
    });

    fetch(String(cfg.atomicAddUrl), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        nonce: cfg.atomicAddNonce,
        tecEventId: state.tecEventId,
        eventPlanId: state.eventPlanId,
        ticket_lines: readTicketLines(state),
        addon_lines: addonLines
      })
    }).then(function (response) {
      return response.json().catch(function () {
        return { success: false, data: { message: 'Could not add items to cart.' } };
      });
    }).then(function (payload) {
      if (!payload || !payload.success || !payload.data || !payload.data.ok) {
        throw new Error((payload && payload.data && (payload.data.message || (payload.data.notice_messages && payload.data.notice_messages[0]))) || 'Could not add items to cart.');
      }
      setGlobalMessage(state, 'Added to cart. Redirecting…', 'success');
      window.location.href = payload.data.cart_url || cfg.cartUrl || '/cart/';
    }).catch(function (err) {
      state.isSubmitting = false;
      submitButtons.forEach(function (button) {
        setDisabled(button, false);
      });
      refresh(state);
      setGlobalMessage(state, err && err.message ? String(err.message) : 'Could not add items to cart.', 'error');
    });
  }

  function boot() {
    var form = resolveForm();
    var sourceBlock = resolveSourceBlock(form);
    if (!form || !sourceBlock) {
      return false;
    }
    if (String(sourceBlock.getAttribute('data-vms-render-mode') || '') === 'server_controls') {
      return true;
    }
    var forceLegacyUpgrade = hasLegacyAddLinks(sourceBlock);
    if (!forceLegacyUpgrade && alreadyHandled(sourceBlock)) {
      return false;
    }
    if (forceLegacyUpgrade) {
      sourceBlock.removeAttribute('data-vms-fallback-active');
    }

    var state = {
      form: form,
      sourceBlock: sourceBlock,
      tecEventId: toInt(cfg.tecEventId || sourceBlock.getAttribute('data-vms-tec-event-id'), 0),
      eventPlanId: toInt(cfg.eventPlanId || sourceBlock.getAttribute('data-vms-event-plan-id'), 0),
      qualifyingTicketIds: String(sourceBlock.getAttribute('data-vms-qualifying-ticket-product-ids') || '').split(',').map(function (value) {
        return toInt(value, 0);
      }).filter(function (value, index, arr) {
        return value > 0 && arr.indexOf(value) === index;
      }),
      priorQualifyingQty: Math.max(0, toInt(sourceBlock.getAttribute('data-vms-prior-qualifying-qty'), 0)),
      priorPoolQtyByKey: parseJson(sourceBlock.getAttribute('data-vms-prior-pool-qty'), {}),
      cartGaQty: Math.max(0, toInt(sourceBlock.getAttribute('data-vms-cart-ga-qty'), 0)),
      cartPoolQtyByKey: parseJson(sourceBlock.getAttribute('data-vms-cart-pool-qty'), {}),
      addons: [],
      isSubmitting: false,
      statusBox: null
    };

    hideDisabledTicketRows(state);

    sourceBlock.setAttribute('data-vms-fallback-active', '1');
    sourceBlock.setAttribute('data-vms-addons-mounted', '1');
    sourceBlock.classList.add('vms-entitlements--compact');
    sourceBlock.classList.add('vms-rw-addons', 'vms-rw-addons--fallback');
    qa('.vms-entitlements-list > .vms-ent-row, .vms-entitlements-list > .vms-entitlements-item', sourceBlock).forEach(function (row) {
      wireRow(state, row);
    });
    if (!state.addons.length) {
      sourceBlock.removeAttribute('data-vms-fallback-active');
      return false;
    }

    qa(SELECTORS.nativeQty, form).forEach(function (input) {
      input.addEventListener('input', function () { refresh(state); });
      input.addEventListener('change', function () { refresh(state); });
    });
    form.addEventListener('click', function (event) {
      var target = event.target;
      if (target && target.closest && target.closest(SELECTORS.nativeQtyButtons)) {
        window.setTimeout(function () { refresh(state); }, 0);
      }
    }, true);
    form.addEventListener('submit', function (event) {
      if (!collectAddonLines(state).length) {
        return;
      }
      event.preventDefault();
      submitAtomically(state);
    });

    refresh(state);
    return true;
  }

  function scheduleBoot(attempt) {
    var tries = typeof attempt === 'number' ? attempt : 0;
    if (boot()) {
      return;
    }
    if (tries >= 8) {
      return;
    }
    window.setTimeout(function () {
      scheduleBoot(tries + 1);
    }, tries < 3 ? 150 : 400);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      scheduleBoot(0);
    });
  } else {
    scheduleBoot(0);
  }

  window.addEventListener('pageshow', function () {
    window.setTimeout(function () { scheduleBoot(0); }, 0);
  });

  var observer = new MutationObserver(function () {
    scheduleBoot(0);
  });
  observer.observe(document.documentElement || document.body, { childList: true, subtree: true });
})();
