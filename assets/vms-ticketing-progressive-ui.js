(function () {
  'use strict';

  var cfg = window.vmsTicketingFront || {};
  var layout = String(cfg.uiLayout || '').trim().toLowerCase();
  var progressiveEnabled = String(cfg.uiProgressive || '') === '1' || layout === 'progressive';
  if (!progressiveEnabled) {
    return;
  }

  var SELECTORS = {
    form: '#tribe-tickets__tickets-form, #tribe-tickets form, .tribe-tickets__tickets form, .tribe-tickets__tickets-form, form.tribe-tickets__tickets-form',
    flow: '#vms-ticketing-flow, .vms-ticketing-flow.vms-ticket-ui',
    nativeQty: 'input.tribe-tickets-quantity, input.tribe-tickets__tickets-item-quantity, input.tribe-tickets__tickets-item-quantity-number-input, .tribe-tickets__item__quantity input[type="number"], .tribe-tickets__tickets-item-quantity input[type="number"]',
    nativeQtyButtons: '.tribe-tickets__item__quantity__add, .tribe-tickets__item__quantity__remove, .tribe-tickets__tickets-item-quantity-add, .tribe-tickets__tickets-item-quantity-remove',
    ticketRow: '[data-ticket-id], [data-product-id], .tribe-tickets__item, .tribe-tickets__tickets-item',
    addonInput: '.vms-addon-input',
    addonRows: '.vms-entitlements-list > .vms-ent-row, .vms-entitlements-list > .vms-entitlements-item'
  };

  function query(selector, root) {
    return (root || document).querySelector(selector);
  }

  function queryAll(selector, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(selector));
  }

  function toInt(value, fallback) {
    var parsed = parseInt(String(value == null ? '' : value), 10);
    return isNaN(parsed) ? (fallback || 0) : parsed;
  }

  function normalizeKey(value) {
    return String(value || '')
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9_]+/g, '_');
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

  function decodeDisplayText(value) {
    var text = String(value == null ? '' : value);
    if (text.indexOf('&') < 0) {
      return text;
    }
    var textarea = document.createElement('textarea');
    textarea.innerHTML = text;
    return textarea.value;
  }

  function ensureProgressiveHelp(section, content, key, html) {
    if (!section || !content) {
      return null;
    }

    var helpId = 'vms-ticket-ui-help-' + String(key || 'section');
    var help = query('#' + helpId, section);
    var copy = String(html || '').trim();

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
    help.setAttribute('data-vms-progressive-help', String(key || 'section'));

    var firstNonHelpChild = null;
    Array.prototype.slice.call(content.children || []).some(function (child) {
      if (child === help) {
        return false;
      }
      if (child.classList && child.classList.contains('vms-ticket-ui-help')) {
        return false;
      }
      firstNonHelpChild = child;
      return true;
    });

    if (help.parentNode !== content || help.nextElementSibling !== firstNonHelpChild) {
      content.insertBefore(help, firstNonHelpChild || content.firstChild || null);
    }

    return help;
  }

  function getTicketRow(input) {
    return input ? (input.closest(SELECTORS.ticketRow) || null) : null;
  }

  function inferProductId(input) {
    var row = getTicketRow(input);
    var candidates = [];
    if (row && row.dataset) {
      candidates.push(row.dataset.productId, row.dataset.ticketId, row.dataset.product, row.dataset.ticket);
    }
    if (input && input.id) {
      var idMatch = String(input.id).match(/(\d+)(?!.*\d)/);
      if (idMatch) {
        candidates.push(idMatch[1]);
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

  function ticketAccess(productId) {
    var map = cfg.ticketAccessMap && typeof cfg.ticketAccessMap === 'object' ? cfg.ticketAccessMap : {};
    var access = map[String(productId || '')];
    return access && typeof access === 'object' ? access : null;
  }

  function isQualifiedTicket(productId) {
    var access = ticketAccess(productId);
    if (!access) {
      return false;
    }
    var allowedPrograms = Array.isArray(access.allowed_programs) ? access.allowed_programs.filter(Boolean) : [];
    return normalizeKey(access.visibility_mode || '') === 'verified'
      || String(access.verified_program || '').trim() !== ''
      || allowedPrograms.length > 0
      || toInt(access.allow_direct_grants, 0) > 0;
  }

  function readQty(input) {
    return input ? Math.max(0, toInt(input.value, 0)) : 0;
  }

  function sectionContent(section) {
    return section ? query('.vms-ticket-progressive-content', section) : null;
  }

  function setSectionOpen(section, open, fromUser) {
    if (!section) {
      return;
    }
    var button = query('.vms-ticket-progressive-toggle', section);
    var content = sectionContent(section);
    var isOpen = !!open;
    section.classList.toggle('is-open', isOpen);
    section.classList.toggle('is-closed', !isOpen);
    if (button) {
      button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }
    if (content) {
      content.hidden = !isOpen;
    }
    if (fromUser) {
      section.setAttribute('data-vms-user-toggled', '1');
    }
  }

  function toggleProgressiveSection(section, fromUser) {
    if (!section) {
      return;
    }
    if (section.classList.contains('vms-ticket-progressive-section--always-open')) {
      setSectionOpen(section, true, false);
      return;
    }
    setSectionOpen(section, !section.classList.contains('is-open'), !!fromUser);
  }

  function bindProgressiveSectionToggle(target, section, sharedState) {
    if (!target || !section) {
      return;
    }
    if (target.getAttribute('data-vms-touch-bound') === '1') {
      return;
    }
    target.setAttribute('data-vms-touch-bound', '1');

    var state = sharedState || { ignoreClickUntil: 0, lastInvokeAt: 0 };

    function invoke(event) {
      var now = Date.now();
      if (state.lastInvokeAt && (now - state.lastInvokeAt) < 240) {
        if (event) {
          event.preventDefault();
          event.stopPropagation();
          if (event.stopImmediatePropagation) {
            event.stopImmediatePropagation();
          }
        }
        return;
      }
      state.lastInvokeAt = now;
      if (event) {
        event.preventDefault();
        event.stopPropagation();
        if (event.stopImmediatePropagation) {
          event.stopImmediatePropagation();
        }
      }
      toggleProgressiveSection(section, true);
    }

    target.addEventListener('pointerup', function (event) {
      var pointerType = event && event.pointerType ? String(event.pointerType).toLowerCase() : '';
      if (pointerType !== 'touch') {
        return;
      }
      state.ignoreClickUntil = Date.now() + 500;
      invoke(event);
    }, true);

    target.addEventListener('touchend', function (event) {
      state.ignoreClickUntil = Date.now() + 500;
      invoke(event);
    }, true);

    target.addEventListener('click', function (event) {
      if (state.ignoreClickUntil && Date.now() < state.ignoreClickUntil) {
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

  function ensureProgressiveSection(section, key, title, description, defaultOpen) {
    if (!section) {
      return null;
    }

    title = decodeDisplayText(title || '').replace(/\s+/g, ' ').trim();
    description = decodeDisplayText(description || '').replace(/\s+/g, ' ').trim();

    section.classList.add('vms-ticket-progressive-section');
    section.classList.toggle('vms-ticket-progressive-section--always-open', key === 'tickets');
    section.setAttribute('data-vms-progressive-section', key);
    section.setAttribute('data-vms-tour', 'ticketing-progressive.' + key);

    var header = query('.vms-ticket-progressive-header', section);
    var content = sectionContent(section);
    if (!header) {
      header = createEl('div', 'vms-ticket-progressive-header');
      var button = createEl('button', 'vms-ticket-progressive-toggle');
      var toggleTouchState = section.__vmsProgressiveTouchState || (section.__vmsProgressiveTouchState = { ignoreClickUntil: 0, lastInvokeAt: 0 });
      button.type = 'button';
      button.id = 'vms-ticket-progressive-toggle-' + key;
      var titleNode = createEl('span', 'vms-ticket-progressive-title', title);
      var descNode = createEl('span', 'vms-ticket-progressive-description', description);
      var iconNode = createEl('span', 'vms-ticket-progressive-icon', '+');
      descNode.hidden = String(description || '').trim() === '';
      iconNode.setAttribute('aria-hidden', 'true');
      button.appendChild(titleNode);
      button.appendChild(descNode);
      button.appendChild(iconNode);
      header.appendChild(button);
      section.insertBefore(header, section.firstChild || null);
      bindProgressiveSectionToggle(button, section, toggleTouchState);
    } else {
      var titleExisting = query('.vms-ticket-progressive-title', header);
      var descExisting = query('.vms-ticket-progressive-description', header);
      if (titleExisting) {
        titleExisting.textContent = title;
      }
      if (descExisting) {
        descExisting.textContent = description;
        descExisting.hidden = String(description || '').trim() === '';
      }
      var buttonExisting = query('.vms-ticket-progressive-toggle', header);
      var toggleTouchStateExisting = section.__vmsProgressiveTouchState || (section.__vmsProgressiveTouchState = { ignoreClickUntil: 0, lastInvokeAt: 0 });
      if (buttonExisting) {
        bindProgressiveSectionToggle(buttonExisting, section, toggleTouchStateExisting);
      }
    }

    if (!content) {
      content = createEl('div', 'vms-ticket-progressive-content');
      content.id = 'vms-ticket-progressive-content-' + key;
      section.appendChild(content);
    }

    Array.prototype.slice.call(section.childNodes).forEach(function (node) {
      if (node !== header && node !== content) {
        content.appendChild(node);
      }
    });

    var toggle = query('.vms-ticket-progressive-toggle', section);
    if (toggle) {
      toggle.setAttribute('aria-controls', content.id);
      if (key === 'tickets') {
        toggle.setAttribute('aria-disabled', 'true');
      } else {
        toggle.removeAttribute('aria-disabled');
      }
    }
    if (!section.hasAttribute('data-vms-open-initialized')) {
      section.setAttribute('data-vms-open-initialized', '1');
      setSectionOpen(section, !!defaultOpen, false);
    }
    return content;
  }

  function hasAddonChoices(addonsSection) {
    if (!addonsSection) {
      return false;
    }

    if (query(SELECTORS.addonRows, addonsSection) || query(SELECTORS.addonInput, addonsSection)) {
      return true;
    }

    var source = query('#vms-reserved-addons, .vms-entitlements-block, .vms-rw-addons', addonsSection);
    if (!source) {
      return false;
    }

    return !!(source.children && source.children.length > 0) || String(source.textContent || '').trim() !== '';
  }

  function isLikelyAddonSource(node) {
    if (!node || node.nodeType !== 1) {
      return false;
    }
    if (node.id === 'vms-ticketing-flow' || (node.classList && node.classList.contains('vms-ticketing-flow'))) {
      return false;
    }
    if (node.classList && node.classList.contains('vms-ticket-ui-addons')) {
      return false;
    }
    return !!(
      node.id === 'vms-reserved-addons' ||
      (node.matches && node.matches('#vms-reserved-addons, .vms-entitlements-block, .vms-rw-addons, [data-vms-addons-mounted], [data-vms-server-controls-active]')) ||
      query(SELECTORS.addonRows, node) ||
      query(SELECTORS.addonInput, node)
    );
  }

  function locateAddonSource(form, flow) {
    var selectors = [
      '#vms-reserved-addons',
      '#vms-reserved-addons.vms-entitlements-block',
      '.vms-entitlements-block[data-vms-event-plan-id]',
      '.vms-rw-addons[data-vms-event-plan-id]',
      '.vms-entitlements-block',
      '.vms-rw-addons',
      '[data-vms-addons-mounted]',
      '[data-vms-server-controls-active]'
    ].join(', ');

    var roots = [form, flow, document];
    for (var i = 0; i < roots.length; i += 1) {
      var root = roots[i];
      if (!root || !root.querySelectorAll) {
        continue;
      }
      var candidates = queryAll(selectors, root);
      for (var j = 0; j < candidates.length; j += 1) {
        if (isLikelyAddonSource(candidates[j])) {
          return candidates[j];
        }
      }
    }

    return null;
  }

  function moveAddonSourceIntoProgressiveSection(addonsSection, addonsContent, form, flow) {
    if (!addonsSection || !addonsContent) {
      return false;
    }
    var source = locateAddonSource(form, flow);
    if (!source || source === addonsSection || source === addonsContent || addonsContent.contains(source)) {
      return hasAddonChoices(addonsSection);
    }
    if (source.parentNode !== addonsContent) {
      addonsContent.appendChild(source);
    }
    source.hidden = false;
    source.removeAttribute('hidden');
    source.style.removeProperty('display');
    return hasAddonChoices(addonsSection);
  }

  function syncAddonSectionVisibility(addonsSection, addonsContent, form, flow) {
    if (!addonsSection || !addonsContent) {
      return false;
    }
    var hasAddons = moveAddonSourceIntoProgressiveSection(addonsSection, addonsContent, form, flow) || hasAddonChoices(addonsSection);
    addonsSection.hidden = !hasAddons;
    if (hasAddons && selectedAddonQty(addonsSection) > 0) {
      setSectionOpen(addonsSection, true, false);
    } else if (hasAddons && !addonsSection.hasAttribute('data-vms-user-toggled')) {
      setSectionOpen(addonsSection, false, false);
    }
    return hasAddons;
  }

  function scheduleAddonVisibilityRetries(addonsSection, addonsContent, form, flow) {
    var delays = [50, 200, 600, 1200, 2500];
    delays.forEach(function (delay) {
      window.setTimeout(function () {
        syncAddonSectionVisibility(addonsSection, addonsContent, form, flow);
      }, delay);
    });
    if (document.readyState !== 'complete') {
      window.addEventListener('load', function () {
        syncAddonSectionVisibility(addonsSection, addonsContent, form, flow);
      }, { once: true });
    }
  }

  function selectedAddonQty(addonsSection) {
    if (!addonsSection) {
      return 0;
    }
    return queryAll(SELECTORS.addonInput, addonsSection).reduce(function (sum, input) {
      if (!input) {
        return sum;
      }
      if (String(input.type || '').toLowerCase() === 'checkbox') {
        return sum + (input.checked ? 1 : 0);
      }
      return sum + Math.max(0, toInt(input.value, 0));
    }, 0);
  }

  function selectedQualifiedQty(qualifiedSection) {
    if (!qualifiedSection) {
      return 0;
    }
    return queryAll(SELECTORS.nativeQty, qualifiedSection).reduce(function (sum, input) {
      return sum + readQty(input);
    }, 0);
  }

  function simplifyQualifiedDescription(row) {
    if (!row) {
      return;
    }
    var desc = query('.vms-ticket-qualified-description p, .tribe-tickets__tickets-item-content-description p, .tribe-tickets__item__description p', row)
      || query('.vms-ticket-qualified-description, .tribe-tickets__tickets-item-content-description, .tribe-tickets__item__description', row);
    if (!desc) {
      return;
    }
    var text = String(desc.textContent || '').replace(/\s+/g, ' ').trim();
    if (!text || desc.getAttribute('data-vms-progressive-copy-cleaned') === '1') {
      return;
    }
    text = text
      .replace(/\s*First time\?\s*Submit[^.]*verification before checkout\./ig, '')
      .replace(/\s*Log in with your approved account to use this ticket\./ig, '')
      .replace(/\s*Log in or register to redeem[^.]*\./ig, '')
      .replace(/\s*Please log in to redeem[^.]*\./ig, '')
      .replace(/\s*New here\?\s*Register first\./ig, '')
      .replace(/^Qualified ticket\.\s*/i, '')
      .replace(/\s+/g, ' ')
      .trim();
    if (!text || /^Qualified ticket\.?$/i.test(text) || /requires an approved account\.?$/i.test(text)) {
      text = 'Requires registration';
    }
    desc.textContent = text;
    desc.setAttribute('data-vms-progressive-copy-cleaned', '1');
  }

  function syncQualifiedHelperVisibility(root) {
    if (!root) {
      return;
    }
    queryAll(SELECTORS.nativeQty, root).forEach(function (input) {
      var productId = inferProductId(input);
      if (!isQualifiedTicket(productId)) {
        return;
      }
      var row = getTicketRow(input);
      if (!row) {
        return;
      }
      simplifyQualifiedDescription(row);
      var active = readQty(input) > 0;
      row.classList.add('vms-progressive-qualified-ticket-row');
      row.classList.toggle('vms-qualified-ticket-selected', active);
      queryAll('.vms-ticket-lock-note, .vms-claim-seat-panel, .vms-ticket-status-stack', row).forEach(function (node) {
        if (!active) {
          node.hidden = true;
          node.setAttribute('data-vms-progressive-hidden', '1');
        } else if (node.getAttribute('data-vms-progressive-hidden') === '1') {
          node.hidden = false;
          node.removeAttribute('data-vms-progressive-hidden');
        }
      });
    });
  }

  function placeRow(row, content) {
    if (!row || !content || row.parentNode === content) {
      return;
    }
    content.appendChild(row);
  }

  function classifyTicketRows(form, ticketsContent) {
    var standardCount = 0;
    var qualifiedCount = 0;
    var seenRows = [];

    queryAll(SELECTORS.nativeQty, form).forEach(function (input) {
      var row = getTicketRow(input);
      if (!row || seenRows.indexOf(row) >= 0) {
        return;
      }
      seenRows.push(row);
      placeRow(row, ticketsContent);
      if (isQualifiedTicket(inferProductId(input))) {
        row.classList.add('vms-progressive-qualified-ticket-row');
        qualifiedCount += 1;
      } else {
        row.classList.remove('vms-progressive-qualified-ticket-row');
        standardCount += 1;
      }
    });

    return {
      standardCount: standardCount,
      qualifiedCount: qualifiedCount
    };
  }

  function enhanceProgressiveTicketUi() {
    var form = query(SELECTORS.form);
    var flow = query(SELECTORS.flow, form || document);
    if (!form || !flow) {
      return false;
    }

    flow.classList.add('vms-ticket-ui-progressive');
    form.setAttribute('data-vms-ticket-ui-progressive', '1');

    var ticketsSection = query('.vms-ticket-ui-tickets', flow);
    var addonsSection = query('.vms-ticket-ui-addons', flow);
    if (!ticketsSection) {
      return false;
    }

    var qualifiedSection = query('.vms-ticket-ui-qualified', flow);
    if (qualifiedSection && qualifiedSection.parentNode) {
      queryAll(SELECTORS.nativeQty, qualifiedSection).forEach(function (input) {
        var row = getTicketRow(input);
        placeRow(row, ticketsSection);
      });
      qualifiedSection.parentNode.removeChild(qualifiedSection);
    }

    var ticketsContent = ensureProgressiveSection(
      ticketsSection,
      'tickets',
      'Tickets',
      '',
      true
    );

    ensureProgressiveHelp(ticketsSection, ticketsContent, 'tickets', cfg.ticketHelpText || '');

    var counts = classifyTicketRows(form, ticketsContent);

    setSectionOpen(ticketsSection, true, false);
    ticketsSection.hidden = (counts.standardCount + counts.qualifiedCount) <= 0;
    syncQualifiedHelperVisibility(ticketsSection);

    if (addonsSection) {
      var addonsContent = ensureProgressiveSection(
        addonsSection,
        'addons',
        cfg.addonSectionHeading || 'Fire Pits & Tables',
        cfg.addonSectionSubtext || 'Click here to add a fire pit or table to your order.',
        false
      );
      ensureProgressiveHelp(addonsSection, addonsContent, 'addons', cfg.addonHelpText || '');
      syncAddonSectionVisibility(addonsSection, addonsContent, form, flow);
      scheduleAddonVisibilityRetries(addonsSection, addonsContent, form, flow);
    }

    return true;
  }

  var pendingEnhanceByDelay = {};

  function scheduleEnhance(delay) {
    var wait = Math.max(0, toInt(delay, 0));
    if (pendingEnhanceByDelay[wait]) {
      return;
    }
    pendingEnhanceByDelay[wait] = window.setTimeout(function () {
      delete pendingEnhanceByDelay[wait];
      enhanceProgressiveTicketUi();
    }, wait);
  }

  function scheduleEnhanceBurst(delays) {
    (Array.isArray(delays) ? delays : [delays]).forEach(function (delay) {
      scheduleEnhance(delay);
    });
  }

  function bindProgressiveWatchers() {
    var form = query(SELECTORS.form);
    if (!form || form.getAttribute('data-vms-progressive-watchers') === '1') {
      return;
    }
    form.setAttribute('data-vms-progressive-watchers', '1');
    form.addEventListener('input', function (event) {
      var target = event.target;
      if (target && target.matches && (target.matches(SELECTORS.nativeQty) || target.matches(SELECTORS.addonInput))) {
        scheduleEnhanceBurst([0, 120]);
      }
    }, true);
    form.addEventListener('change', function (event) {
      var target = event.target;
      if (target && target.matches && (target.matches(SELECTORS.nativeQty) || target.matches(SELECTORS.addonInput))) {
        scheduleEnhanceBurst([0, 120]);
      }
    }, true);
    form.addEventListener('pointerup', function (event) {
      var pointerType = event && event.pointerType ? String(event.pointerType).toLowerCase() : '';
      if (pointerType !== 'touch') {
        return;
      }
      var target = event.target;
      if (target && target.closest && (target.closest(SELECTORS.nativeQtyButtons) || target.closest('.vms-addon-minus, .vms-addon-plus'))) {
        scheduleEnhanceBurst([80, 180, 320, 520, 900]);
      }
    }, true);
    form.addEventListener('touchend', function (event) {
      var target = event.target;
      if (target && target.closest && (target.closest(SELECTORS.nativeQtyButtons) || target.closest('.vms-addon-minus, .vms-addon-plus'))) {
        scheduleEnhanceBurst([80, 180, 320, 520, 900]);
      }
    }, true);
    form.addEventListener('click', function (event) {
      var target = event.target;
      if (target && target.closest && (target.closest(SELECTORS.nativeQtyButtons) || target.closest('.vms-addon-minus, .vms-addon-plus'))) {
        scheduleEnhanceBurst([80, 180, 320, 520, 900]);
      }
    }, true);
    if (typeof MutationObserver !== 'undefined') {
      var observer = new MutationObserver(function () {
        scheduleEnhance(0);
      });
      observer.observe(form, {
        childList: true,
        subtree: true
      });
    }
  }

  function boot(attempt) {
    var ok = enhanceProgressiveTicketUi();
    bindProgressiveWatchers();
    if (!ok && attempt < 30) {
      window.setTimeout(function () {
        boot(attempt + 1);
      }, 100);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      boot(0);
    });
  } else {
    boot(0);
  }
})();
