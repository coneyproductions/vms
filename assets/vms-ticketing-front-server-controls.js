(function () {
  'use strict';

  var cfg = window.vmsTicketingFront || {};
  if (cfg.isCart || cfg.isCheckout) {
    return;
  }

  var SELECTORS = {
    form: '#tribe-tickets__tickets-form',
    sourceBlock: '#vms-reserved-addons.vms-entitlements-block[data-vms-render-mode="server_controls"]',
    footer: '.tribe-tickets__tickets-footer, .tribe-tickets__footer, .tribe-common-c-btn-group',
    submit: '#tribe-tickets__tickets-submit, .tribe-tickets__tickets-buy, button[type="submit"], input[type="submit"]',
    footerQty: '.tribe-tickets__tickets-footer-quantity-number',
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

  function setReadOnly(node, readonly) {
    if (!node) {
      return;
    }
    node.readOnly = !!readonly;
    if (readonly) {
      node.setAttribute('readonly', 'readonly');
      node.setAttribute('aria-disabled', 'true');
    } else {
      node.removeAttribute('readonly');
      node.setAttribute('aria-disabled', 'false');
    }
  }

  function resolveForm() {
    return q(SELECTORS.form);
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

  function activeNativeTicketInputCount(state) {
    if (!state || !state.form) {
      return 0;
    }
    return qa(SELECTORS.nativeQty, state.form).filter(function (input) {
      var row = getTicketRow(input);
      var productId = inferTicketProductId(input);
      return productId > 0 && !isDisabledPendingSyncTicketProductId(productId) && !input.disabled && !(row && row.hidden);
    }).length;
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

  function readFooterQty(state) {
    var node = q(SELECTORS.footerQty, state.form) || q(SELECTORS.footerQty);
    if (!node) {
      return 0;
    }
    return Math.max(0, toInt(node.textContent || node.innerText || '0', 0));
  }

  function selectedQualifyingQty(state) {
    var lines = readTicketLines(state);
    var qualifyingQty = 0;
    if (!state.qualifyingTicketIds.length) {
      qualifyingQty = lines.reduce(function (sum, line) { return sum + line.qty; }, 0);
    } else {
      qualifyingQty = lines.reduce(function (sum, line) {
        return sum + (state.qualifyingTicketIds.indexOf(line.product_id) >= 0 ? line.qty : 0);
      }, 0);
    }
    if (qualifyingQty > 0) {
      return qualifyingQty;
    }
    if (state && state.suppressedDisabledTicketCount > 0 && activeNativeTicketInputCount(state) > 0) {
      return 0;
    }
    return readFooterQty(state);
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

  function computeAddonState(state, addon) {
    var qualifyingQty = selectedQualifyingQty(state);
    var existingPoolQty = addon.poolKey ? Math.max(0, toInt(state.cartPoolQtyByKey[addon.poolKey], 0)) : 0;
    var selectedOtherPoolQty = addon.poolKey ? selectedAddonPoolQty(state, addon.poolKey, addon.productId) : 0;
    var maxByTicket = Number.POSITIVE_INFINITY;
    var maxByPool = Number.POSITIVE_INFINITY;
    var maxByItem = Number.POSITIVE_INFINITY;
    var limit = 0;
    var reason = '';

    if (!addon.canAdd) {
      return {
        limit: 0,
        qualifyingQty: qualifyingQty,
        existingPoolQty: existingPoolQty,
        selectedOtherPoolQty: selectedOtherPoolQty,
        reason: addon.soldOutText || 'Unavailable'
      };
    }

    if (addon.minGa > 0) {
      maxByTicket = Math.max(0, Math.floor(qualifyingQty / addon.minGa) - existingPoolQty - selectedOtherPoolQty);
    }
    if (addon.poolKey && addon.poolMax > 0) {
      maxByPool = Math.max(0, addon.poolMax - existingPoolQty - selectedOtherPoolQty);
    }
    if (addon.maxQty > 0) {
      maxByItem = addon.maxQty;
    }

    limit = Math.max(0, Math.min(maxByTicket, maxByPool, maxByItem));

    if (limit <= 0) {
      if (addon.poolKey && addon.poolMax > 0 && existingPoolQty >= addon.poolMax) {
        reason = 'Already in cart.';
      } else if (addon.poolKey && addon.poolMax > 0 && (existingPoolQty + selectedOtherPoolQty) >= addon.poolMax) {
        reason = 'Pool limit reached.';
      } else if (addon.minGa > 0 && qualifyingQty < addon.minGa) {
        reason = 'Requires ' + String(addon.minGa) + ' qualifying ticket' + (addon.minGa === 1 ? '' : 's') + ' • You have ' + String(qualifyingQty) + '.';
      }
    }

    return {
      limit: limit,
      qualifyingQty: qualifyingQty,
      existingPoolQty: existingPoolQty,
      selectedOtherPoolQty: selectedOtherPoolQty,
      reason: reason
    };
  }

  function ensureStatusBox(state) {
    if (state.statusBox && state.statusBox.isConnected) {
      return state.statusBox;
    }
    var footer = q(SELECTORS.footer, state.form);
    if (!footer || !footer.parentNode) {
      return null;
    }
    var box = document.getElementById('vms-addons-server-status');
    if (!box) {
      box = document.createElement('div');
      box.id = 'vms-addons-server-status';
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


  function neutralizeTecDialog(button, active) {
    if (!button || !button.setAttribute) {
      return;
    }
    if (active) {
      if (!button.hasAttribute('data-vms-orig-data-js') && button.hasAttribute('data-js')) {
        button.setAttribute('data-vms-orig-data-js', button.getAttribute('data-js') || '');
      }
      if (!button.hasAttribute('data-vms-orig-data-content') && button.hasAttribute('data-content')) {
        button.setAttribute('data-vms-orig-data-content', button.getAttribute('data-content') || '');
      }
      button.removeAttribute('data-js');
      button.removeAttribute('data-content');
      button.removeAttribute('aria-haspopup');
      button.removeAttribute('aria-controls');
      button.setAttribute('data-vms-tec-dialog-bypassed', '1');
      return;
    }
    if (button.hasAttribute('data-vms-orig-data-js')) {
      var originalJs = button.getAttribute('data-vms-orig-data-js') || '';
      if (originalJs) {
        button.setAttribute('data-js', originalJs);
      }
      button.removeAttribute('data-vms-orig-data-js');
    }
    if (button.hasAttribute('data-vms-orig-data-content')) {
      var originalContent = button.getAttribute('data-vms-orig-data-content') || '';
      if (originalContent) {
        button.setAttribute('data-content', originalContent);
      }
      button.removeAttribute('data-vms-orig-data-content');
    }
    button.removeAttribute('data-vms-tec-dialog-bypassed');
  }

  function refreshSubmitState(state) {
    hideDisabledTicketRows(state);
    var ticketLines = readTicketLines(state);
    var addonLines = collectAddonLines(state);
    var hasSelection = ticketLines.length > 0 || addonLines.length > 0;
    var nextLabel = state.isSubmitting ? 'Adding…' : (hasSelection ? 'Add items to cart' : state.defaultSubmitLabel);

    state.submitButtons.forEach(function (button) {
      if (!button) {
        return;
      }
      if (button.tagName && button.tagName.toLowerCase() === 'input') {
        button.value = nextLabel;
      } else {
        button.textContent = nextLabel;
      }
      neutralizeTecDialog(button, hasSelection || state.isSubmitting);
      setDisabled(button, !hasSelection || state.isSubmitting);
      button.classList.toggle('vms-rw-submit--busy', !!state.isSubmitting);
    });
  }

  function scheduleRefresh(state) {
    if (!state) {
      return;
    }
    state.pollUntil = Date.now() + 3000;
    refresh(state);
    [0, 40, 120, 240, 420].forEach(function (delay) {
      window.setTimeout(function () {
        refresh(state);
      }, delay);
    });
    if (window.requestAnimationFrame) {
      window.requestAnimationFrame(function () {
        refresh(state);
      });
    }
  }

  function refresh(state) {
    hideDisabledTicketRows(state);
    state.addons.forEach(function (addon) {
      var addonState = computeAddonState(state, addon);
      var limit = addonState.limit;
      if (addon.isCheckbox) {
        limit = Math.min(Math.max(0, limit), 1);
      }
      addon.qty = clamp(addon.qty, 0, limit);
      addon.limit = limit;
      addon.qualifyingQty = addonState.qualifyingQty;
      addon.existingPoolQty = addonState.existingPoolQty;
      addon.selectedOtherPoolQty = addonState.selectedOtherPoolQty;
      if (addon.inputEl) {
        if (addon.isCheckbox) {
          var checkboxLocked = state.isSubmitting || (limit <= 0 && addon.qty <= 0);
          addon.inputEl.checked = addon.qty > 0;
          setDisabled(addon.inputEl, checkboxLocked);
          addon.inputEl.setAttribute('aria-disabled', checkboxLocked ? 'true' : 'false');
          if (addon.checkboxWrapEl && addon.checkboxWrapEl.classList) {
            addon.checkboxWrapEl.classList.toggle('is-selected', addon.qty > 0);
          }
        } else {
          addon.inputEl.value = String(addon.qty);
          addon.inputEl.setAttribute('max', String(limit));
          setDisabled(addon.inputEl, limit <= 0 || state.isSubmitting);
          setReadOnly(addon.inputEl, limit <= 0 || state.isSubmitting);
        }
      }
      setDisabled(addon.minusEl, addon.isCheckbox || addon.qty <= 0 || state.isSubmitting);
      setDisabled(addon.plusEl, addon.isCheckbox || addon.qty >= limit || !addon.canAdd || state.isSubmitting);

      var note = addonState.reason || '';
      if (!note) {
        if (addon.qty > 0 && addon.isCheckbox) {
          note = 'Selected. Uncheck to remove.';
        } else if (addon.poolKey && addon.poolMax > 0 && addon.qty >= limit && limit > 0) {
          note = 'Pool limit reached.';
        } else if (addon.minGa > 0) {
          note = 'Up to ' + String(limit) + ' allowed with your current tickets.';
        }
      }

      if (addon.noteEl) {
        addon.noteEl.textContent = note;
        if (addon.noteEl.classList) {
          addon.noteEl.classList.remove('vms-ent-note--rule', 'vms-ent-note--selected');
          if (note) {
            if (addon.qty > 0 && addon.isCheckbox) {
              addon.noteEl.classList.add('vms-ent-note--selected');
            } else if (addon.minGa > 0 || !addon.canAdd || (addon.poolKey && addon.poolMax > 0 && limit <= 0)) {
              addon.noteEl.classList.add('vms-ent-note--rule');
            }
          }
        }
      }
      if (addon.statusEl) {
        addon.statusEl.textContent = addon.qty > 0 ? (addon.isCheckbox ? '' : (String(addon.qty) + ' selected')) : '';
      }
      if (addon.rowEl && addon.rowEl.classList) {
        addon.rowEl.classList.toggle('is-selected', addon.qty > 0);
        addon.rowEl.classList.toggle('is-locked', limit <= 0);
      }
    });

    refreshSubmitState(state);
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
      submitAtomically(state);
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

  function submitAtomically(state) {
    if (state.isSubmitting) {
      return;
    }

    var ticketLines = readTicketLines(state);
    var addonLines = collectAddonLines(state);
    if (!ticketLines.length && !addonLines.length) {
      return;
    }

    if (!cfg.atomicAddUrl || !cfg.atomicAddNonce) {
      setGlobalMessage(state, 'Atomic add endpoint unavailable.', 'error');
      return;
    }

    state.isSubmitting = true;
    setGlobalMessage(state, '', '');
    refresh(state);

    state.submitButtons.forEach(function (button) {
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
        ticket_lines: ticketLines,
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
      state.submitButtons.forEach(function (button) {
        setDisabled(button, false);
      });
      refresh(state);
      setGlobalMessage(state, err && err.message ? String(err.message) : 'Could not add items to cart.', 'error');
    });
  }

  function wireAddonRow(state, row) {
    var stepper = q('[data-vms-server-stepper="1"]', row);
    var input = q('.vms-addon-input', row);
    var minus = q('.vms-addon-minus', row);
    var plus = q('.vms-addon-plus', row);
    var soldOut = q('.vms-entitlements-soldout', row);
    if (!stepper || !input || !minus || !plus) {
      return;
    }

    var productId = toInt(stepper.getAttribute('data-vms-product-id') || input.getAttribute('data-vms-product-id'), 0);
    var selectorMode = String(stepper.getAttribute('data-vms-selector-mode') || row.getAttribute('data-vms-selector-mode') || '').trim() === 'checkbox' ? 'checkbox' : 'stepper';
    if (productId <= 0) {
      return;
    }

    var model = {
      productId: productId,
      label: ((q('.vms-ent-title, .vms-entitlements-label', row) || {}).textContent || 'Add-on').trim(),
      poolKey: String(stepper.getAttribute('data-vms-pool-key') || row.getAttribute('data-vms-pool-key') || ''),
      poolMax: toInt(stepper.getAttribute('data-vms-pool-max') || row.getAttribute('data-vms-pool-max'), 0),
      minGa: toInt(stepper.getAttribute('data-vms-pool-min-ga') || row.getAttribute('data-vms-min-ga'), 0),
      maxQty: toInt(stepper.getAttribute('data-vms-max-qty') || row.getAttribute('data-vms-max-qty'), 0),
      canAdd: true,
      soldOutText: soldOut ? String(soldOut.textContent || '').trim() : '',
      selectorMode: selectorMode,
      isCheckbox: selectorMode === 'checkbox',
      qty: selectorMode === 'checkbox' ? ((!!input.checked) ? 1 : 0) : Math.max(0, toInt(input.value, 0)),
      rowEl: row,
      noteEl: q('.vms-ent-note', row),
      statusEl: q('.vms-rw-addon__status', row),
      checkboxWrapEl: q('.vms-addon-checkbox-wrap', row),
      checkboxLabelEl: q('.vms-addon-checkbox-label', row),
      inputEl: input,
      minusEl: minus,
      plusEl: plus
    };

    if (model.isCheckbox) {
      input.addEventListener('input', function () {
        model.qty = input.checked ? 1 : 0;
        refresh(state);
      });
      input.addEventListener('change', function () {
        model.qty = input.checked ? 1 : 0;
        refresh(state);
      });
    } else {
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
    }

    state.addons.push(model);
  }

  function buildState(form, sourceBlock) {
    return {
      form: form,
      sourceBlock: sourceBlock,
      tecEventId: toInt(cfg.tecEventId || sourceBlock.getAttribute('data-vms-tec-event-id'), 0),
      eventPlanId: toInt(cfg.eventPlanId || sourceBlock.getAttribute('data-vms-event-plan-id'), 0),
      qualifyingTicketIds: String(sourceBlock.getAttribute('data-vms-qualifying-ticket-product-ids') || '').split(',').map(function (value) {
        return toInt(value, 0);
      }).filter(function (value, index, arr) {
        return value > 0 && arr.indexOf(value) === index;
      }),
      cartPoolQtyByKey: parseJson(sourceBlock.getAttribute('data-vms-cart-pool-qty'), {}),
      addons: [],
      isSubmitting: false,
      statusBox: null,
      submitButtons: qa(SELECTORS.submit, form),
      defaultSubmitLabel: (function () {
        var button = q(SELECTORS.submit, form);
        if (!button) {
          return 'Get Tickets';
        }
        if (button.tagName && button.tagName.toLowerCase() === 'input') {
          return String(button.value || 'Get Tickets').trim() || 'Get Tickets';
        }
        return String(button.textContent || 'Get Tickets').trim() || 'Get Tickets';
      }())
    };
  }

  function boot() {
    var form = resolveForm();
    var sourceBlock = q(SELECTORS.sourceBlock, form) || q(SELECTORS.sourceBlock);
    if (!form || !sourceBlock) {
      return false;
    }
    if (sourceBlock.getAttribute('data-vms-server-controls-active') === '1') {
      return true;
    }

    var state = buildState(form, sourceBlock);
    hideDisabledTicketRows(state);
    qa('.vms-entitlements-list > .vms-ent-row, .vms-entitlements-list > .vms-entitlements-item', sourceBlock).forEach(function (row) {
      wireAddonRow(state, row);
    });
    if (!state.addons.length) {
      return false;
    }

    sourceBlock.setAttribute('data-vms-server-controls-active', '1');
    sourceBlock.classList.add('vms-rw-addons', 'vms-rw-addons--server-controls');

    qa(SELECTORS.nativeQty, form).forEach(function (input) {
      input.addEventListener('input', function () { scheduleRefresh(state); });
      input.addEventListener('change', function () { scheduleRefresh(state); });
      input.addEventListener('keyup', function () { scheduleRefresh(state); });
      input.addEventListener('blur', function () { scheduleRefresh(state); });
    });
    form.addEventListener('click', function (event) {
      var target = event.target;
      if (target && target.closest && target.closest(SELECTORS.nativeQtyButtons)) {
        scheduleRefresh(state);
      }
    }, true);
    form.addEventListener('pointerup', function (event) {
      var target = event.target;
      if (target && target.closest && target.closest(SELECTORS.nativeQtyButtons)) {
        scheduleRefresh(state);
      }
    }, true);
    form.addEventListener('touchend', function (event) {
      var target = event.target;
      if (target && target.closest && target.closest(SELECTORS.nativeQtyButtons)) {
        scheduleRefresh(state);
      }
    }, true);
    document.addEventListener('click', function (event) {
      var target = event.target;
      if (!target || !target.closest) {
        return;
      }
      if (target.closest(SELECTORS.nativeQtyButtons) || target.closest(SELECTORS.nativeQty)) {
        scheduleRefresh(state);
      }
    }, true);
    if (window.MutationObserver) {
      var qtyObserver = new MutationObserver(function () {
        scheduleRefresh(state);
      });
      qtyObserver.observe(form, { attributes: true, childList: true, subtree: true, characterData: true });
      state.qtyObserver = qtyObserver;
    }
    state.pollUntil = 0;
    state.pollTimer = window.setInterval(function () {
      var now = Date.now();
      if (now < state.pollUntil) {
        refresh(state);
      }
    }, 250);
    state.submitButtons.forEach(function (button) {
      neutralizeTecDialog(button, true);
      bindSubmitButton(button, state);
    });
    document.addEventListener('click', function (event) {
      var target = event.target && event.target.closest ? event.target.closest(SELECTORS.submit) : null;
      if (!target || !state.form.contains(target)) {
        return;
      }
      if (!readTicketLines(state).length && !collectAddonLines(state).length) {
        return;
      }
      event.preventDefault();
      event.stopPropagation();
      if (event.stopImmediatePropagation) {
        event.stopImmediatePropagation();
      }
      neutralizeTecDialog(target, true);
      submitAtomically(state);
    }, true);
    form.addEventListener('submit', function (event) {
      if (!readTicketLines(state).length && !collectAddonLines(state).length) {
        return;
      }
      event.preventDefault();
      event.stopPropagation();
      if (event.stopImmediatePropagation) {
        event.stopImmediatePropagation();
      }
      submitAtomically(state);
    }, true);

    refresh(state);
    return true;
  }

  function scheduleBoot(attempt) {
    var tries = typeof attempt === 'number' ? attempt : 0;
    if (boot()) {
      return;
    }
    if (tries >= 10) {
      return;
    }
    window.setTimeout(function () {
      scheduleBoot(tries + 1);
    }, tries < 4 ? 120 : 400);
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
})();
