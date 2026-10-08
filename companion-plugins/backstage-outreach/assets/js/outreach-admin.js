(function () {
  var actionMenu = null;
  var actionMenuTrigger = null;
  var actionMenuOpenedAt = 0;
  var stickyTables = [];

  function safeLocalStorage() {
    try {
      return window.localStorage || null;
    } catch (error) {
      return null;
    }
  }

  function syncFlyerLayoutOverride() {
    var custom = document.querySelector('#vms-pass-claims-wrap input[name="campaign_layout_mode"][value="custom"]');
    var fields = document.querySelector('#vms-pass-claims-wrap [data-vms-flyer-layout-fields="campaign"]');
    if (!custom || !fields) {
      return;
    }
    fields.querySelectorAll('input, select').forEach(function (control) {
      control.disabled = !custom.checked;
    });
    fields.setAttribute('aria-disabled', custom.checked ? 'false' : 'true');
  }

  function syncFlyerLayoutCompatibility(changed) {
    ['venue', 'campaign'].forEach(function (prefix) {
      var orientation = document.querySelector('#vms-pass-claims-wrap [data-vms-flyer-orientation="' + prefix + '"]');
      var position = document.querySelector('#vms-pass-claims-wrap [data-vms-flyer-panel-position="' + prefix + '"]');
      if (!orientation || !position) {
        return;
      }
      if (changed === position && position.value === 'right') {
        orientation.value = 'landscape';
      } else if (orientation.value === 'portrait') {
        position.value = 'bottom';
      }
    });
  }

  function sectionStorageKey(section) {
    if (!section) {
      return '';
    }

    var context = String(section.getAttribute('data-vms-section-context') || '');
    var sectionId = String(section.getAttribute('data-vms-section-id') || '');
    if (!context || !sectionId) {
      return '';
    }

    return 'vmsOutreachSection:' + context + ':' + sectionId;
  }

  function collapsibleSectionForNode(node) {
    if (!node || !node.closest) {
      return null;
    }

    return node.closest('#vms-pass-claims-wrap [data-vms-collapsible-section]');
  }

  function openSectionChain(section) {
    if (!section || !section.closest) {
      return;
    }

    var stack = [];
    var current = section;

    while (current) {
      stack.unshift(current);
      var parent = current.parentElement;
      current = parent ? collapsibleSectionForNode(parent) : null;
    }

    stack.forEach(function (node) {
      if (!node.open) {
        node.open = true;
      }
    });
  }

  function openSectionForHashTarget() {
    var hash = String(window.location.hash || '').replace(/^#/, '');
    if (!hash) {
      return null;
    }

    var target = document.getElementById(hash);
    if (!target || !target.closest) {
      return target || null;
    }

    openSectionChain(collapsibleSectionForNode(target));

    return target;
  }

  function openSectionTargetById(targetId) {
    if (!targetId) {
      return null;
    }

    var target = document.getElementById(String(targetId));
    if (!target) {
      return null;
    }

    openSectionChain(collapsibleSectionForNode(target));
    return target;
  }

  function scrollToAdminTarget(target, focusInvalid) {
    if (!target || !target.getBoundingClientRect) {
      return;
    }

    var adminBar = document.getElementById('wpadminbar');
    var adminBarHeight = adminBar ? adminBar.getBoundingClientRect().height : 0;
    var top = window.pageYOffset + target.getBoundingClientRect().top - adminBarHeight - 16;
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    window.scrollTo({ top: Math.max(0, top), behavior: reduceMotion ? 'auto' : 'smooth' });

    if (!focusInvalid) {
      return;
    }

    var scope = target.closest('.vms-pass-form-section, .vms-pass-upload-step, form') || target;
    var invalid = scope.querySelector('[aria-invalid="true"]');
    if (invalid && !invalid.disabled && invalid.focus) {
      window.requestAnimationFrame(function () {
        invalid.focus({ preventScroll: true });
      });
	  return;
	}
	if (target.hasAttribute && target.hasAttribute('tabindex') && target.focus) {
	  window.requestAnimationFrame(function () {
		target.focus({ preventScroll: true });
	  });
    }
  }

  function replaceHash(targetId) {
    if (!targetId) {
      return;
    }

    var nextHash = '#' + String(targetId);
    if (window.history && window.history.replaceState) {
      window.history.replaceState(null, '', window.location.pathname + window.location.search + nextHash);
      return;
    }

    window.location.hash = String(targetId);
  }

  function applySectionState(section) {
    if (!section) {
      return;
    }

    if (section.getAttribute('data-vms-force-open') === '1') {
      section.open = true;
      return;
    }

    var hash = String(window.location.hash || '').replace(/^#/, '');
    if (hash) {
      var hashTarget = document.getElementById(hash);
      if (hashTarget && section.contains(hashTarget)) {
        section.open = true;
        return;
      }
    }

    var storage = safeLocalStorage();
    var key = sectionStorageKey(section);
    if (!storage || !key) {
      return;
    }

    var saved = storage.getItem(key);
    if (saved === '1' || saved === '0') {
      section.open = saved === '1';
    }
  }

  function storeSectionState(section) {
    var storage = safeLocalStorage();
    var key = sectionStorageKey(section);
    if (!storage || !key) {
      return;
    }

    storage.setItem(key, section.open ? '1' : '0');
  }

  function initCollapsibleSections() {
    document.querySelectorAll('#vms-pass-claims-wrap [data-vms-collapsible-section]').forEach(function (section) {
      if (section.__vmsCollapsibleSectionInit) {
        return;
      }

      section.__vmsCollapsibleSectionInit = true;
      applySectionState(section);
      section.addEventListener('toggle', function () {
        if (section.getAttribute('data-vms-force-open') === '1' && !section.open) {
          section.open = true;
          return;
        }

        storeSectionState(section);
        syncAllStickyTables();
      });
    });

    if (window.location.hash) {
      window.requestAnimationFrame(function () {
        var target = openSectionForHashTarget();
        scrollToAdminTarget(target, true);
        syncAllStickyTables();
      });
    }
  }

  function syncContactAudienceSelectAll(table) {
    if (!table) {
      return;
    }

    var toggle = table.querySelector('[data-vms-contact-audience-select-all]');
    if (!toggle) {
      return;
    }

    var checkboxes = Array.from(table.querySelectorAll('[data-vms-contact-audience-select]:not(:disabled)'));
    if (!checkboxes.length) {
      toggle.checked = false;
      toggle.indeterminate = false;
      toggle.disabled = true;
      return;
    }

    var checkedCount = checkboxes.filter(function (checkbox) {
      return checkbox.checked;
    }).length;

    toggle.disabled = false;
    toggle.checked = checkedCount > 0 && checkedCount === checkboxes.length;
    toggle.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
  }

  function syncAllContactAudienceSelectAll() {
    document.querySelectorAll('#vms-pass-claims-wrap .vms-pass-contact-audience-table').forEach(syncContactAudienceSelectAll);
  }

  function closeOpenHelp(exceptNode) {
    document.querySelectorAll('#vms-pass-claims-wrap .vms-pass-help.is-open').forEach(function (node) {
      if (exceptNode && node === exceptNode) {
        return;
      }
      node.classList.remove('is-open');
      var button = node.querySelector('.vms-pass-help__toggle');
      if (button) {
        button.setAttribute('aria-expanded', 'false');
      }
    });
  }

  function ensureActionMenu() {
    if (actionMenu) {
      return actionMenu;
    }

    actionMenu = document.createElement('div');
    actionMenu.className = 'vms-pass-floating-menu';
    actionMenu.hidden = true;
    actionMenu.setAttribute('role', 'menu');
    document.body.appendChild(actionMenu);
    return actionMenu;
  }

  function syncStickyTable(state) {
    if (!state || !state.wrapper || !state.table || !state.clone || !state.cloneTable) {
      return;
    }

    var sourceHead = state.table.querySelector('thead');
    var cloneHead = state.cloneTable.querySelector('thead');
    var sourceColgroup = state.table.querySelector('colgroup');
    var cloneColgroup = state.cloneTable.querySelector('colgroup');
    if (!sourceHead || !cloneHead) {
      state.clone.hidden = true;
      return;
    }

    if (sourceColgroup) {
      var sourceColgroupMarkup = sourceColgroup.outerHTML;
      if (!cloneColgroup || cloneColgroup.outerHTML !== sourceColgroupMarkup) {
        if (cloneColgroup) {
          cloneColgroup.remove();
        }
        state.cloneTable.insertAdjacentHTML('afterbegin', sourceColgroupMarkup);
      }
    } else if (cloneColgroup) {
      cloneColgroup.remove();
    }

    var sourceMarkup = sourceHead.innerHTML;
    if (cloneHead.innerHTML !== sourceMarkup) {
      cloneHead.innerHTML = sourceMarkup;
    }

    var hasVerticalOverflow = state.wrapper.scrollHeight > state.wrapper.clientHeight + 1;
    var isActive = hasVerticalOverflow && state.wrapper.scrollTop > 0;

    state.clone.hidden = !isActive;
    state.wrapper.classList.toggle('vms-pass-table-scroll--sticky-active', isActive);

    if (!hasVerticalOverflow) {
      state.wrapper.classList.remove('vms-pass-table-scroll--sticky-active');
      return;
    }

    state.wrapper.classList.add('vms-pass-table-scroll--sticky-ready');

    var tableWidth = Math.ceil(state.table.getBoundingClientRect().width);
    state.clone.style.width = tableWidth + 'px';
    state.cloneTable.style.width = tableWidth + 'px';
    state.cloneTable.style.minWidth = tableWidth + 'px';
    state.cloneTable.style.transform = '';
  }

  function initStickyTables() {
    document.querySelectorAll('#vms-pass-claims-wrap [data-vms-sticky-table]').forEach(function (wrapper) {
      if (wrapper.__vmsStickyTableState) {
        syncStickyTable(wrapper.__vmsStickyTableState);
        return;
      }

      var table = wrapper.querySelector(':scope > table');
      var sourceHead = table ? table.querySelector('thead') : null;
      if (!table || !sourceHead) {
        return;
      }

      var clone = document.createElement('div');
      clone.className = 'vms-pass-sticky-head';
      clone.hidden = true;
      clone.setAttribute('aria-hidden', 'true');
      clone.innerHTML = '<table class="' + table.className + '">' + (table.querySelector('colgroup') ? table.querySelector('colgroup').outerHTML : '') + '<thead>' + sourceHead.innerHTML + '</thead></table>';
      wrapper.insertBefore(clone, table);

      var state = {
        wrapper: wrapper,
        table: table,
        clone: clone,
        cloneTable: clone.querySelector('table')
      };

      wrapper.__vmsStickyTableState = state;
      stickyTables.push(state);
      wrapper.addEventListener('scroll', function () {
        syncStickyTable(state);
      });

      if (window.ResizeObserver) {
        var observer = new ResizeObserver(function () {
          syncStickyTable(state);
        });
        observer.observe(wrapper);
        observer.observe(table);
        state.resizeObserver = observer;
      }

      syncStickyTable(state);
    });
  }

  function syncAllStickyTables() {
    stickyTables.forEach(syncStickyTable);
  }

  function closeActionMenu() {
    if (actionMenuTrigger) {
      actionMenuTrigger.setAttribute('aria-expanded', 'false');
    }

    actionMenuTrigger = null;
    actionMenuOpenedAt = 0;

    if (!actionMenu) {
      return;
    }

    actionMenu.hidden = true;
    actionMenu.innerHTML = '';
    actionMenu.style.top = '';
    actionMenu.style.left = '';
    actionMenu.style.visibility = '';
  }

  function positionActionMenu() {
    if (!actionMenu || actionMenu.hidden || !actionMenuTrigger) {
      return;
    }

    var rect = actionMenuTrigger.getBoundingClientRect();
    var margin = 12;

    actionMenu.style.top = '0px';
    actionMenu.style.left = '0px';
    actionMenu.style.visibility = 'hidden';

    var menuRect = actionMenu.getBoundingClientRect();
    var top = rect.bottom + 6;
    if ((top + menuRect.height) > (window.innerHeight - margin)) {
      top = rect.top - menuRect.height - 6;
    }
    if (top < margin) {
      top = margin;
    }

    var left = rect.right - menuRect.width;
    if (left < margin) {
      left = rect.left;
    }
    if ((left + menuRect.width) > (window.innerWidth - margin)) {
      left = window.innerWidth - menuRect.width - margin;
    }
    if (left < margin) {
      left = margin;
    }

    actionMenu.style.top = Math.round(top) + 'px';
    actionMenu.style.left = Math.round(left) + 'px';
    actionMenu.style.visibility = '';
  }

  function openActionMenu(trigger) {
    var templateId = String(trigger.getAttribute('data-vms-action-menu-trigger') || '');
    if (!templateId) {
      return;
    }

    var template = document.getElementById(templateId);
    if (!template) {
      return;
    }

    var menu = ensureActionMenu();
    if (actionMenuTrigger === trigger && !menu.hidden) {
      closeActionMenu();
      return;
    }

    closeActionMenu();

    actionMenuTrigger = trigger;
    actionMenuTrigger.setAttribute('aria-expanded', 'true');
    menu.innerHTML = template.innerHTML;
    menu.hidden = false;
    actionMenuOpenedAt = Date.now();
    positionActionMenu();
  }

  function contactDashboard() {
    return document.querySelector('#vms-pass-claims-wrap [data-vms-business-contact-dashboard]');
  }

  function syncContactEmailSelection() {
    var dashboard = contactDashboard();
    if (!dashboard) {
      return;
    }
    var eligible = Array.prototype.slice.call(dashboard.querySelectorAll('[data-vms-email-eligible]'));
    var selected = eligible.filter(function (checkbox) { return checkbox.checked; });
    var selectAll = dashboard.querySelector('[data-vms-select-all-eligible]');
    var count = dashboard.querySelector('[data-vms-email-selected-count]');
    var submit = dashboard.querySelector('[data-vms-email-submit]');
    if (selectAll) {
      selectAll.checked = eligible.length > 0 && selected.length === eligible.length;
      selectAll.indeterminate = selected.length > 0 && selected.length < eligible.length;
    }
    if (count) {
      count.textContent = selected.length === 1 ? '1 recipient selected' : selected.length + ' recipients selected';
    }
    if (submit) {
      submit.disabled = selected.length === 0;
    }
  }

  function syncContactDashboard() {
    var dashboard = contactDashboard();
    if (!dashboard) {
      return;
    }
    var search = dashboard.querySelector('[data-vms-contact-search]');
    var filter = dashboard.querySelector('[data-vms-contact-filter]');
    var sort = dashboard.querySelector('[data-vms-contact-sort]');
    var list = dashboard.querySelector('.vms-pass-contact-list');
    if (!list) {
      return;
    }
    var query = String(search ? search.value : '').trim().toLowerCase();
    var status = String(filter ? filter.value : 'attention');
    var order = String(sort ? sort.value : 'attention');
    var cards = Array.prototype.slice.call(list.querySelectorAll('[data-contact-card]'));
    cards.sort(function (left, right) {
      if (order === 'name') {
        return String(left.dataset.contactName || '').localeCompare(String(right.dataset.contactName || ''));
      }
      if (order === 'recent') {
        return String(right.dataset.contactTime || '').localeCompare(String(left.dataset.contactTime || ''));
      }
      var rank = Number(left.dataset.contactRank || 0) - Number(right.dataset.contactRank || 0);
      return rank || String(left.dataset.contactName || '').localeCompare(String(right.dataset.contactName || ''));
    });
    var visible = 0;
    cards.forEach(function (card) {
      list.appendChild(card);
      var matchesText = !query || String(card.dataset.contactSearch || '').indexOf(query) !== -1;
      var matchesStatus = status === 'all' || status === 'attention'
        || (status === 'no_contact' && card.dataset.contactNoContact === '1')
        || (status === 'follow_up' && card.dataset.contactFollowUp === '1')
        || (status === 'email_handed_off' && card.dataset.contactEmail === '1')
        || (status === 'manual' && card.dataset.contactManual === '1');
      card.hidden = !(matchesText && matchesStatus);
      if (!card.hidden) {
        visible += 1;
      }
    });
    var count = dashboard.querySelector('[data-vms-contact-visible-count]');
    if (count) {
      count.textContent = visible === 1 ? '1 business shown' : visible + ' businesses shown';
    }
    syncContactEmailSelection();
  }

  document.addEventListener('click', function (event) {
    var helpToggle = event.target && event.target.closest ? event.target.closest('#vms-pass-claims-wrap .vms-pass-help__toggle') : null;
    if (helpToggle) {
      event.preventDefault();
      var helpRoot = helpToggle.closest('.vms-pass-help');
      if (!helpRoot) {
        return;
      }

      var isOpen = helpRoot.classList.contains('is-open');
      closeOpenHelp(helpRoot);
      helpRoot.classList.toggle('is-open', !isOpen);
      helpToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
      return;
    }

    if (!(event.target && event.target.closest && event.target.closest('#vms-pass-claims-wrap .vms-pass-help'))) {
      closeOpenHelp(null);
    }

    var actionTrigger = event.target && event.target.closest ? event.target.closest('#vms-pass-claims-wrap [data-vms-action-menu-trigger]') : null;
    if (actionTrigger) {
      event.preventDefault();
      openActionMenu(actionTrigger);
      return;
    }

    if (actionMenu && !actionMenu.hidden) {
      var clickedInsideActionMenu = event.target && event.target.closest ? event.target.closest('.vms-pass-floating-menu') : null;
      if (!clickedInsideActionMenu) {
        closeActionMenu();
      }
    }

    var artworkSelect = event.target && event.target.closest ? event.target.closest('#vms-pass-claims-wrap [data-vms-artwork-select]') : null;
    if (artworkSelect) {
      event.preventDefault();
      if (!(window.wp && window.wp.media)) {
        return;
      }
      var artworkInput = document.getElementById(String(artworkSelect.getAttribute('data-vms-artwork-target') || ''));
      var artworkPreview = document.getElementById(String(artworkSelect.getAttribute('data-vms-artwork-preview') || ''));
      if (!artworkInput || !artworkPreview) {
        return;
      }
      var artworkFrame = window.wp.media({
        title: 'Choose flyer artwork',
        button: { text: 'Use this artwork' },
        library: { type: 'image' },
        multiple: false
      });
      artworkFrame.on('select', function () {
        var attachment = artworkFrame.state().get('selection').first().toJSON();
        var imageUrl = attachment.sizes && attachment.sizes.large ? attachment.sizes.large.url : attachment.url;
        artworkInput.value = String(attachment.id || '');
        artworkPreview.innerHTML = '';
        var artworkImage = document.createElement('img');
        artworkImage.src = String(imageUrl || '');
        artworkImage.alt = '';
        artworkPreview.appendChild(artworkImage);
        artworkPreview.hidden = false;
        var root = artworkSelect.closest('[data-vms-flyer-artwork-control]');
        var remove = root ? root.querySelector('[data-vms-artwork-remove]') : null;
        if (remove) {
          remove.hidden = false;
        }
        if (root && root.getAttribute('data-vms-artwork-prefix') === 'campaign') {
          var customMode = root.closest('fieldset').querySelector('input[name="campaign_artwork_mode"][value="custom"]');
          if (customMode) {
            customMode.checked = true;
          }
        }
      });
      artworkFrame.open();
      return;
    }

    var artworkRemove = event.target && event.target.closest ? event.target.closest('#vms-pass-claims-wrap [data-vms-artwork-remove]') : null;
    if (artworkRemove) {
      event.preventDefault();
      var removeInput = document.getElementById(String(artworkRemove.getAttribute('data-vms-artwork-target') || ''));
      var removePreview = document.getElementById(String(artworkRemove.getAttribute('data-vms-artwork-preview') || ''));
      if (removeInput) {
        removeInput.value = '0';
      }
      if (removePreview) {
        removePreview.innerHTML = '';
        removePreview.hidden = true;
      }
      artworkRemove.hidden = true;
      var removeRoot = artworkRemove.closest('[data-vms-flyer-artwork-control]');
      if (removeRoot && removeRoot.getAttribute('data-vms-artwork-prefix') === 'campaign') {
        var noArtworkMode = removeRoot.closest('fieldset').querySelector('input[name="campaign_artwork_mode"][value="none"]');
        if (noArtworkMode) {
          noArtworkMode.checked = true;
        }
      }
      return;
    }

    var openSectionTrigger = event.target && event.target.closest ? event.target.closest('#vms-pass-claims-wrap [data-vms-open-section-target]') : null;
    if (openSectionTrigger) {
      var targetId = String(openSectionTrigger.getAttribute('data-vms-open-section-target') || '');
      if (!targetId) {
        return;
      }

      var href = String(openSectionTrigger.getAttribute('href') || '');
      if (href) {
        try {
          var resolvedUrl = new window.URL(href, window.location.href);
          var isSamePage = resolvedUrl.pathname === window.location.pathname && resolvedUrl.search === window.location.search;
          if (!isSamePage) {
            return;
          }
        } catch (error) {
          // Fall through to the inline open behavior when URL parsing fails.
        }
      }

      event.preventDefault();

      var sectionTarget = openSectionTargetById(targetId);
      if (!sectionTarget) {
        return;
      }

      replaceHash(targetId);
      if (sectionTarget.scrollIntoView) {
        sectionTarget.scrollIntoView({ block: 'start' });
      }
      syncAllStickyTables();
      return;
    }

    var trigger = event.target && event.target.closest ? event.target.closest('[data-vms-copy]') : null;
    if (!trigger) {
      return;
    }

    var selector = String(trigger.getAttribute('data-vms-copy') || '');
    if (!selector) {
      return;
    }

    var input = document.querySelector(selector);
    if (!input) {
      return;
    }

    var text = String(input.value || '');
    if (!text) {
      return;
    }

    var original = String(trigger.textContent || 'Copy');

    var markDone = function () {
      trigger.textContent = 'Copied';
      window.setTimeout(function () {
        trigger.textContent = original;
      }, 1400);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(markDone).catch(function () {
        if (input.select) {
          input.select();
          document.execCommand('copy');
          markDone();
        }
      });
      return;
    }

    if (input.select) {
      input.select();
      document.execCommand('copy');
      markDone();
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeOpenHelp(null);
      closeActionMenu();
    }
  });

  document.addEventListener('change', function (event) {
    var target = event.target;
    if (!(target && target.matches)) {
      return;
    }

    if (target.matches('#vms-pass-claims-wrap [data-vms-flyer-layout-mode]')) {
      syncFlyerLayoutOverride();
      return;
    }

    if (target.matches('#vms-pass-claims-wrap [data-vms-flyer-orientation], #vms-pass-claims-wrap [data-vms-flyer-panel-position]')) {
      syncFlyerLayoutCompatibility(target);
      return;
    }

    if (target.matches('#vms-pass-claims-wrap [data-vms-contact-audience-select-all]')) {
      var selectAllTable = target.closest('table');
      if (!selectAllTable) {
        return;
      }

      selectAllTable.querySelectorAll('[data-vms-contact-audience-select]:not(:disabled)').forEach(function (checkbox) {
        checkbox.checked = !!target.checked;
      });
      syncContactAudienceSelectAll(selectAllTable);
      return;
    }

    if (target.matches('#vms-pass-claims-wrap [data-vms-contact-audience-select]')) {
      syncContactAudienceSelectAll(target.closest('table'));
	  return;
	}

	if (target.matches('#vms-pass-claims-wrap [data-vms-select-all-eligible]')) {
	  var dashboard = contactDashboard();
	  if (dashboard) {
		dashboard.querySelectorAll('[data-vms-email-eligible]').forEach(function (checkbox) {
		  checkbox.checked = !!target.checked;
		});
	  }
	  syncContactEmailSelection();
	  return;
	}

	if (target.matches('#vms-pass-claims-wrap [data-vms-email-eligible]')) {
	  syncContactEmailSelection();
	  return;
	}

	if (target.matches('#vms-pass-claims-wrap [data-vms-contact-filter], #vms-pass-claims-wrap [data-vms-contact-sort]')) {
	  syncContactDashboard();
    }
  });

  document.addEventListener('input', function (event) {
	var target = event.target;
	if (target && target.matches && target.matches('#vms-pass-claims-wrap [data-vms-contact-search]')) {
	  syncContactDashboard();
	}
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initStickyTables();
      initCollapsibleSections();
      syncAllContactAudienceSelectAll();
      syncFlyerLayoutCompatibility(null);
      syncFlyerLayoutOverride();
	  syncContactDashboard();
    }, { once: true });
  } else {
    initStickyTables();
    initCollapsibleSections();
    syncAllContactAudienceSelectAll();
    syncFlyerLayoutCompatibility(null);
    syncFlyerLayoutOverride();
	syncContactDashboard();
  }

  window.addEventListener('load', syncAllStickyTables);
  window.addEventListener('pageshow', function () {
    var target = openSectionForHashTarget();
    if (target) {
      window.requestAnimationFrame(function () {
        scrollToAdminTarget(target, true);
      });
    }
    syncAllStickyTables();
    syncAllContactAudienceSelectAll();
	syncContactDashboard();
  });
  window.addEventListener('resize', function () {
    closeActionMenu();
    syncAllStickyTables();
  });
  window.addEventListener('hashchange', function () {
    var target = openSectionForHashTarget();
    scrollToAdminTarget(target, true);
    syncAllStickyTables();
    syncAllContactAudienceSelectAll();
	syncContactDashboard();
  });
  document.addEventListener('scroll', function () {
    if (actionMenuTrigger) {
      if (Date.now() - actionMenuOpenedAt < 250) {
        return;
      }
      closeActionMenu();
    }
  }, true);
})();
