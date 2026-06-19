(function () {
  'use strict';

  var NOTICE_SELECTOR = '.notice, .updated, .error, .update-nag';

  function isMovableGlobalNotice(node) {
    if (!node || !node.matches || !node.matches(NOTICE_SELECTOR)) {
      return false;
    }
    if (node.closest('.vms-admin-shell')) {
      return false;
    }
    if (node.closest('.vms-admin-global-notices')) {
      return false;
    }
    if (node.closest('.vms-admin-global-topnav')) {
      return false;
    }
    if (node.closest('.inline-edit-save') || node.closest('.inline-edit-row')) {
      return false;
    }

    var parent = node.parentElement;
    if (!parent) {
      return false;
    }

    return (
      parent.id === 'wpbody-content' ||
      parent.classList.contains('wrap') ||
      parent.classList.contains('vms-ma-page-title')
    );
  }

  function getHeadingAnchorInWrap(wrap) {
    if (!wrap || !wrap.children) {
      return null;
    }

    var heading = null;
    var lastAction = null;
    var headerEnd = null;

    for (var i = 0; i < wrap.children.length; i++) {
      var child = wrap.children[i];
      if (!child || !child.tagName) continue;

      if (
        child.tagName === 'HR' &&
        child.classList &&
        child.classList.contains('wp-header-end')
      ) {
        headerEnd = child;
      }

      if (
        child.tagName === 'H1' ||
        (child.classList && child.classList.contains('wp-heading-inline'))
      ) {
        heading = child;
      }

      if (child.classList && child.classList.contains('page-title-action')) {
        lastAction = child;
      }
    }

    if (headerEnd) return headerEnd;
    if (lastAction) return lastAction;
    if (heading) return heading;
    return null;
  }

  function findGlobalHeadingWrap(root) {
    if (!root || !root.children) {
      return null;
    }

    var customTitleWrap = null;
    for (var i = 0; i < root.children.length; i++) {
      var child = root.children[i];
      if (!child || !child.classList) {
        continue;
      }

      if (child.classList.contains('vms-ma-page-title')) {
        customTitleWrap = child;
      }

      if (!child || !child.classList || !child.classList.contains('wrap')) {
        continue;
      }
      if (child.classList.contains('vms-admin-global-header-zone')) {
        continue;
      }
      if (getHeadingAnchorInWrap(child)) {
        return child;
      }

      var nestedCustomTitle = child.querySelector('.vms-ma-page-title');
      if (nestedCustomTitle) {
        return nestedCustomTitle;
      }
    }

    if (customTitleWrap) {
      return customTitleWrap;
    }

    return null;
  }

  function ensureGlobalNoticesContainer(navWrap) {
    if (!navWrap) {
      return null;
    }

    var root = document.querySelector('#wpbody-content');
    if (!root) {
      return null;
    }

    var top = null;
    try {
      top = root.querySelector(':scope > .vms-admin-global-notices');
    } catch (e) {
      top = null;
    }
    if (!top) {
      top = root.querySelector('.vms-admin-global-notices');
    }
    if (!top) {
      top = document.createElement('section');
      top.className = 'vms-admin-global-notices';
    }

    var headingWrap = findGlobalHeadingWrap(root);
    if (headingWrap) {
      var anchor = getHeadingAnchorInWrap(headingWrap);
      if (anchor && anchor.parentElement === headingWrap) {
        if (top.parentElement !== headingWrap || top.previousElementSibling !== anchor) {
          if (anchor.nextSibling) {
            headingWrap.insertBefore(top, anchor.nextSibling);
          } else {
            headingWrap.appendChild(top);
          }
        }
      } else if (top.parentElement !== headingWrap) {
        headingWrap.insertBefore(top, headingWrap.firstChild || null);
      }
      return top;
    }

    var parent = navWrap.parentElement;
    if (!parent) {
      return top;
    }

    if (top.parentElement !== parent) {
      parent.insertBefore(top, navWrap.nextSibling);
    } else if (top.previousElementSibling !== navWrap) {
      parent.insertBefore(top, navWrap.nextSibling);
    }

    return top;
  }

  function normalizeShellNotices() {
    var shells = document.querySelectorAll('.vms-admin-shell');
    if (!shells.length) return;

    var root = document.querySelector('#wpbody-content') || document;
    var hasGlobalTopnav = !!document.querySelector('.vms-admin-global-topnav');

    shells.forEach(function (shell, index) {
      var top = null;
      try {
        top = shell.querySelector(':scope > .vms-admin-shell__notices');
      } catch (e) {
        top = null;
      }
      if (!top) {
        top = shell.querySelector('.vms-admin-shell__notices');
      }
      if (!top) return;

      var misplaced = shell.querySelectorAll(NOTICE_SELECTOR);
      misplaced.forEach(function (node) {
        if (node.closest('.vms-admin-topnav')) return;
        if (top.contains(node)) return;
        top.appendChild(node);
      });

      // Shell pages can receive admin_notices at #wpbody-content level.
      // Pull those into the shell notice zone so they render in-body, not above VMS nav.
      if (hasGlobalTopnav || index !== 0) return;

      var globalNotices = root.querySelectorAll(NOTICE_SELECTOR);
      globalNotices.forEach(function (node) {
        if (!node || !node.parentElement) return;
        if (top.contains(node)) return;
        if (shell.contains(node)) return;
        if (node.closest('.vms-admin-global-notices')) return;
        if (node.closest('.vms-admin-global-topnav')) return;
        if (node.closest('.vms-admin-shell') && !shell.contains(node)) return;
        if (node.parentElement.id !== 'wpbody-content') return;
        top.appendChild(node);
      });
    });
  }

  function normalizeGlobalNotices() {
    var navs = document.querySelectorAll('.vms-admin-global-topnav');
    if (!navs.length) return;

    var root = document.querySelector('#wpbody-content') || document;

    navs.forEach(function (navWrap) {
      var top = ensureGlobalNoticesContainer(navWrap);
      if (!top) return;

      var notices = root.querySelectorAll(NOTICE_SELECTOR);
      notices.forEach(function (node) {
        if (top.contains(node)) return;
        if (!isMovableGlobalNotice(node)) return;
        top.appendChild(node);
      });
    });
  }

  function normalizeScreenMetaLinks() {
    var metaLinks = document.getElementById('screen-meta-links');
    if (!metaLinks) return;

    var globalHeader = document.querySelector('.vms-admin-global-header-zone');
    if (!globalHeader) return;

    var secondaryRow = globalHeader.querySelector('.vms-admin-topnav__secondary-row');
    metaLinks.classList.add('vms-admin-screen-meta-links');

    // Keep Screen Options in the VMS header/nav stack so it no longer shifts nav position.
    if (secondaryRow) {
      if (metaLinks.parentElement !== secondaryRow) {
        secondaryRow.appendChild(metaLinks);
      }
      return;
    }

    if (metaLinks.parentElement !== globalHeader) {
      globalHeader.appendChild(metaLinks);
    }
  }

  function normalizeAllNotices() {
    normalizeShellNotices();
    normalizeGlobalNotices();
  }

  function initBulkSelectToggles() {
    var toggles = document.querySelectorAll('input[data-vms-select-all]');
    toggles.forEach(function (toggle) {
      if (toggle.dataset.vmsBulkBound === '1') return;
      toggle.dataset.vmsBulkBound = '1';

      toggle.addEventListener('change', function () {
        var targetName = (toggle.getAttribute('data-vms-select-all') || '').trim();
        if (!targetName) return;

        var scope = toggle.closest('form') || document;
        var targets = document.getElementsByName(targetName);
        for (var i = 0; i < targets.length; i++) {
          var target = targets[i];
          if (!target || target.tagName !== 'INPUT' || target.type !== 'checkbox') continue;
          if (target === toggle || target.disabled) continue;
          if (scope !== document && !scope.contains(target)) continue;
          target.checked = toggle.checked;
        }
      });
    });
  }

  function initAutoSubmitFields() {
    var fields = document.querySelectorAll('.vms-js-auto-submit-field');
    fields.forEach(function (field) {
      if (field.dataset.vmsAutosubmitBound === '1') return;
      field.dataset.vmsAutosubmitBound = '1';

      field.addEventListener('change', function () {
        var form = field.form || field.closest('.vms-js-auto-submit-form') || field.closest('form');
        if (form) {
          form.submit();
        }
      });
    });
  }

  function initScheduleMonthAccordion() {
    if (window.__vmsMonthAccordionInit) return;

    var hasSchedulePanels = document.querySelector('details[data-vms-scope][data-vms-month]');
    if (!hasSchedulePanels) return;

    window.__vmsMonthAccordionInit = true;

    function closeAllExcept(openEl, scope) {
      var all = document.querySelectorAll('details[data-vms-scope="' + scope + '"]');
      for (var i = 0; i < all.length; i++) {
        if (all[i] !== openEl) {
          all[i].removeAttribute('open');
        }
      }
    }

    function initScope(scope) {
      var panels = document.querySelectorAll('details[data-vms-scope="' + scope + '"]');
      if (!panels || !panels.length) return;

      var storageKey = 'vms_schedule_open_month_' + scope;
      var want = null;
      try {
        want = localStorage.getItem(storageKey);
      } catch (e) {
        want = null;
      }

      var now = new Date();
      var month = String(now.getMonth() + 1).padStart(2, '0');
      var current = String(now.getFullYear()) + '-' + month;

      var target = null;
      if (want) {
        target = document.querySelector('details[data-vms-scope="' + scope + '"][data-vms-month="' + want + '"]');
      }
      if (!target) {
        target = document.querySelector('details[data-vms-scope="' + scope + '"][data-vms-month="' + current + '"]');
      }
      if (!target) {
        target = panels[0];
      }

      for (var i = 0; i < panels.length; i++) {
        panels[i].removeAttribute('open');
      }

      if (target) {
        target.setAttribute('open', 'open');
        closeAllExcept(target, scope);
        setTimeout(function () {
          try {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
          } catch (e) {}
        }, 50);
      }

      for (var j = 0; j < panels.length; j++) {
        panels[j].addEventListener('toggle', function (event) {
          var el = event.currentTarget;
          if (!el || !el.hasAttribute('open')) return;

          closeAllExcept(el, scope);

          var openMonth = el.getAttribute('data-vms-month') || '';
          if (!openMonth) return;
          try {
            localStorage.setItem(storageKey, openMonth);
          } catch (e) {}
        });
      }
    }

    initScope('single');
    initScope('all');
  }

  function initTopNavQuickMenus() {
    var navs = document.querySelectorAll('.vms-admin-topnav');
    var wraps = document.querySelectorAll('.vms-admin-topnav__primary-wrap.has-quick-menu');
    if (navs.length) {
      navs.forEach(function (nav) {
        nav.classList.add('vms-admin-topnav--js');
      });
    }
    if (!wraps.length) return;

    function isMobileNav() {
      return !!(window.matchMedia && window.matchMedia('(max-width: 782px)').matches);
    }

    function setOpen(wrap, shouldOpen) {
      if (!wrap) return;
      wrap.classList.toggle('is-open', !!shouldOpen);
      var trigger = wrap.querySelector('.vms-admin-topnav__primary');
      if (trigger) {
        trigger.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
      }
    }

    function closeAll(exceptWrap) {
      wraps.forEach(function (wrap) {
        if (exceptWrap && wrap === exceptWrap) return;
        setOpen(wrap, false);
      });
    }

    function setMobileExpanded(nav, shouldExpand) {
      if (!nav) return;
      nav.classList.toggle('is-mobile-expanded', !!shouldExpand);
    }

    wraps.forEach(function (wrap) {
      if (wrap.dataset.vmsQuickMenuBound === '1') return;
      wrap.dataset.vmsQuickMenuBound = '1';

      var trigger = wrap.querySelector('.vms-admin-topnav__primary');
      if (!trigger) return;

      wrap.addEventListener('mouseenter', function () {
        if (isMobileNav()) return;
        closeAll(wrap);
        setOpen(wrap, true);
      });

      wrap.addEventListener('mouseleave', function () {
        if (isMobileNav()) return;
        setOpen(wrap, false);
      });

      wrap.addEventListener('focusin', function () {
        if (isMobileNav()) return;
        closeAll(wrap);
        setOpen(wrap, true);
      });

      wrap.addEventListener('focusout', function () {
        if (isMobileNav()) return;
        window.setTimeout(function () {
          if (!wrap.contains(document.activeElement)) {
            setOpen(wrap, false);
          }
        }, 0);
      });

      trigger.addEventListener('click', function (event) {
        var nav = trigger.closest('.vms-admin-topnav');

        if (isMobileNav()) {
          var isActive = wrap.classList.contains('is-active');
          if (isActive && nav) {
            event.preventDefault();
            var shouldExpand = !nav.classList.contains('is-mobile-expanded');
            setMobileExpanded(nav, shouldExpand);
          }
          closeAll(null);
          return;
        }

        if (!window.matchMedia || window.matchMedia('(hover: hover)').matches) {
          return;
        }
        if (wrap.classList.contains('is-open')) {
          return;
        }
        event.preventDefault();
        closeAll(wrap);
        setOpen(wrap, true);
      });
    });

    if (window.__vmsTopNavQuickMenuGlobalBound) return;
    window.__vmsTopNavQuickMenuGlobalBound = true;

    document.addEventListener('click', function (event) {
      var target = event.target;
      if (target && target.closest && target.closest('.vms-admin-topnav__primary-wrap.has-quick-menu')) {
        return;
      }
      closeAll(null);
      if (navs.length && isMobileNav()) {
        navs.forEach(function (nav) {
          setMobileExpanded(nav, false);
        });
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeAll(null);
        if (navs.length) {
          navs.forEach(function (nav) {
            setMobileExpanded(nav, false);
          });
        }
      }
    });
  }



  function isNoticeNode(node) {
    return !!(
      node &&
      node.nodeType === 1 &&
      node.matches &&
      node.matches('.notice, .updated, .error, .update-nag')
    );
  }

  function slugifyText(value) {
    return String(value || '')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }

  function createSettingsAccordion(title, persistKey, isOpen) {
    var details = document.createElement('details');
    details.className = 'vms-settings-accordion';
    if (isOpen) {
      details.open = true;
    }
    if (persistKey) {
      details.setAttribute('data-vms-persist-key', persistKey);
    }

    var summary = document.createElement('summary');
    summary.className = 'vms-settings-accordion__summary';

    var titleWrap = document.createElement('span');
    titleWrap.className = 'vms-settings-accordion__summary-main';

    var titleNode = document.createElement('span');
    titleNode.className = 'vms-settings-accordion__title';
    titleNode.textContent = String(title || 'Section');
    titleWrap.appendChild(titleNode);

    var chevron = document.createElement('span');
    chevron.className = 'vms-settings-accordion__chevron';
    chevron.setAttribute('aria-hidden', 'true');

    var body = document.createElement('div');
    body.className = 'vms-settings-accordion__body';

    summary.appendChild(titleWrap);
    summary.appendChild(chevron);
    details.appendChild(summary);
    details.appendChild(body);

    return details;
  }

  function initSettingsAccordions() {
    var params;
    try {
      params = new URLSearchParams(window.location.search || '');
    } catch (e) {
      return;
    }

    if (params.get('page') !== 'vms-settings') {
      return;
    }

    var contentRoot = document.querySelector('.vms-admin-shell__content') || document.querySelector('.wrap');
    if (!contentRoot) {
      return;
    }

    contentRoot.classList.add('vms-settings-page');

    var form = contentRoot.querySelector('form[action*="options.php"]');
    if (form && form.dataset.vmsAccordionsReady !== '1') {
      form.dataset.vmsAccordionsReady = '1';

      var stack = document.createElement('div');
      stack.className = 'vms-settings-accordion-stack';

      var formChildren = Array.prototype.slice.call(form.children || []);
      var currentTitle = '';
      var currentKey = '';
      var currentNodes = [];
      var submitNode = null;
      var panelIndex = 0;

      function flushFormPanel() {
        if (!currentTitle || !currentNodes.length) {
          currentTitle = '';
          currentKey = '';
          currentNodes = [];
          return;
        }

        var isOpen = panelIndex === 0;
        var accordion = createSettingsAccordion(currentTitle, currentKey, isOpen);
        var body = accordion.querySelector('.vms-settings-accordion__body');
        currentNodes.forEach(function (node) {
          body.appendChild(node);
        });
        stack.appendChild(accordion);
        panelIndex += 1;
        currentTitle = '';
        currentKey = '';
        currentNodes = [];
      }

      formChildren.forEach(function (child) {
        if (!child || child.nodeType !== 1) {
          return;
        }

        if (child.matches('h2')) {
          flushFormPanel();
          currentTitle = String(child.textContent || '').trim();
          currentKey = 'vms-settings-' + (slugifyText(currentTitle) || ('panel-' + panelIndex));
          child.remove();
          return;
        }

        if (child.matches('.submit')) {
          flushFormPanel();
          submitNode = child;
          return;
        }

        if (currentTitle) {
          currentNodes.push(child);
        }
      });

      flushFormPanel();

      if (stack.children.length) {
        if (submitNode) {
          form.insertBefore(stack, submitNode);
        } else {
          form.appendChild(stack);
        }
      }
    }

    var directChildren = Array.prototype.slice.call(contentRoot.children || []);
    var afterFormIndex = directChildren.indexOf(form);
    if (afterFormIndex === -1) {
      afterFormIndex = 0;
    }

    for (var i = afterFormIndex + 1; i < directChildren.length; i++) {
      var node = directChildren[i];
      if (!node || node.nodeType !== 1) {
        continue;
      }
      if (node.matches('hr')) {
        node.remove();
        continue;
      }
      if (!node.matches('h2')) {
        continue;
      }

      var sectionTitle = String(node.textContent || '').trim();
      var accordion = createSettingsAccordion(sectionTitle, 'vms-settings-' + (slugifyText(sectionTitle) || ('extra-' + i)), false);
      var body = accordion.querySelector('.vms-settings-accordion__body');
      contentRoot.insertBefore(accordion, node);
      node.remove();

      var probe = accordion.nextSibling;
      while (probe) {
        var nextProbe = probe.nextSibling;
        if (probe.nodeType === 1 && (probe.matches('h2') || probe.matches('.vms-card'))) {
          break;
        }
        body.appendChild(probe);
        probe = nextProbe;
      }
    }

    var cards = Array.prototype.slice.call(contentRoot.children || []).filter(function (child) {
      return !!(child && child.nodeType === 1 && child.matches && child.matches('.vms-card'));
    });
    cards.forEach(function (card, index) {
      if (!card || card.dataset.vmsAccordionReady === '1') {
        return;
      }

      var cardTitleNode = null;
      for (var j = 0; j < card.children.length; j++) {
        if (card.children[j] && card.children[j].tagName === 'H2') {
          cardTitleNode = card.children[j];
          break;
        }
      }
      if (!cardTitleNode) {
        return;
      }

      card.dataset.vmsAccordionReady = '1';
      var cardTitle = String(cardTitleNode.textContent || '').trim();
      var accordion = createSettingsAccordion(cardTitle, 'vms-settings-card-' + (slugifyText(cardTitle) || index), false);
      accordion.classList.add('vms-settings-accordion--card');
      var body = accordion.querySelector('.vms-settings-accordion__body');

      var anchor = card;
      var previous = card.previousElementSibling;
      var noticeNodes = [];
      while (isNoticeNode(previous)) {
        noticeNodes.unshift(previous);
        anchor = previous;
        previous = previous.previousElementSibling;
      }

      contentRoot.insertBefore(accordion, anchor);

      noticeNodes.forEach(function (notice) {
        body.appendChild(notice);
      });

      Array.prototype.slice.call(card.childNodes || []).forEach(function (childNode) {
        if (childNode === cardTitleNode) {
          return;
        }
        body.appendChild(childNode);
      });

      card.remove();
    });
  }

  function initPersistentDetails() {
    if (!window.localStorage) return;

    var panels = document.querySelectorAll('details[data-vms-persist-key]');
    if (!panels.length) return;

    panels.forEach(function (panel) {
      var key = String(panel.getAttribute('data-vms-persist-key') || '').trim();
      if (!key) return;

      var storageKey = 'vms:details:' + key;

      try {
        var saved = window.localStorage.getItem(storageKey);
        if (saved === 'open') {
          panel.open = true;
        } else if (saved === 'closed') {
          panel.open = false;
        }
      } catch (e) {}

      panel.addEventListener('toggle', function () {
        try {
          window.localStorage.setItem(storageKey, panel.open ? 'open' : 'closed');
        } catch (e) {}
      });
    });
  }

  function initVendorCommandCenterComposer() {
    var select = document.getElementById('vms-vcc-vendor-id');
    if (!select) return;

    var dataEl = document.getElementById('vms-vcc-vendor-map');
    var toField = document.getElementById('vms-vcc-to');
    var subjectField = document.getElementById('vms-vcc-subject');
    var bodyField = document.getElementById('vms-vcc-body');
    var noteField = document.getElementById('vms-vcc-current-template-note');
    var resetButton = document.getElementById('vms-vcc-reset-fields');
    if (!dataEl || !toField || !subjectField || !bodyField) return;

    var vendorMap = {};
    try {
      vendorMap = JSON.parse(dataEl.textContent || '{}');
    } catch (e) {
      vendorMap = {};
    }

    function syncFields() {
      var vendorId = String(select.value || '');
      if (!vendorId || !vendorMap[vendorId]) return;

      var payload = vendorMap[vendorId] || {};
      toField.value = String(payload.to || '');
      subjectField.value = String(payload.subject || '');
      bodyField.value = String(payload.message || '');
      if (noteField) {
        noteField.textContent = String(payload.template_note || '');
      }
    }

    select.addEventListener('change', syncFields);
    if (resetButton) {
      resetButton.addEventListener('click', function () {
        syncFields();
      });
    }
    if (select.value) {
      syncFields();
    }
  }

  function initVendorCommandCenterTemplateEditor() {
    var scopeSelect = document.getElementById('vms-vcc-template-scope');
    if (!scopeSelect) return;

    var dataEl = document.getElementById('vms-vcc-template-map');
    var subjectField = document.getElementById('vms-vcc-template-subject');
    var bodyField = document.getElementById('vms-vcc-template-body');
    var helpField = document.getElementById('vms-vcc-template-scope-help');
    if (!dataEl || !subjectField || !bodyField) return;

    var templateMap = {};
    try {
      templateMap = JSON.parse(dataEl.textContent || '{}');
    } catch (e) {
      templateMap = {};
    }

    function syncEditor() {
      var scope = String(scopeSelect.value || 'default');
      var payload = templateMap[scope] || templateMap.default || {};
      subjectField.value = String(payload.subject || '');
      bodyField.value = String(payload.body || '');
      if (helpField) {
        helpField.textContent = String(payload.description || '');
      }
    }

    scopeSelect.addEventListener('change', syncEditor);
    syncEditor();
  }

  function initAdminMenuDirectorySearch() {
    var input = document.querySelector('[data-vms-admin-menu-directory-search]');
    var rows = document.querySelectorAll('[data-vms-admin-menu-directory-row]');
    if (!input || !rows.length) return;

    function applyFilter() {
      var term = String(input.value || '').trim().toLowerCase();
      rows.forEach(function (row) {
        var haystack = String(row.getAttribute('data-vms-admin-menu-directory-search-text') || '').toLowerCase();
        var match = term === '' || haystack.indexOf(term) !== -1;
        row.hidden = !match;
      });
    }

    input.addEventListener('input', applyFilter);
    applyFilter();
  }

  function init() {
    normalizeScreenMetaLinks();
    window.setTimeout(normalizeScreenMetaLinks, 0);
    window.setTimeout(normalizeScreenMetaLinks, 120);
    normalizeAllNotices();
    window.setTimeout(normalizeAllNotices, 0);
    window.setTimeout(normalizeAllNotices, 100);
    window.setTimeout(normalizeAllNotices, 350);
    if (!window.__vmsNoticeLoadBound) {
      window.__vmsNoticeLoadBound = true;
      window.addEventListener('load', normalizeAllNotices);
    }
    initBulkSelectToggles();
    initAdminMenuDirectorySearch();
    initAutoSubmitFields();
    initTopNavQuickMenus();
    initVendorCommandCenterComposer();
    initVendorCommandCenterTemplateEditor();
    initSettingsAccordions();
    initPersistentDetails();
    initScheduleMonthAccordion();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
