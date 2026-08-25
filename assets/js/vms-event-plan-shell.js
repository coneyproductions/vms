(function () {
  var hasInitializedScroll = false;
  var shellController = null;

  function initTicketingDestinationFields(root) {
    var scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll('[data-vms-ticketing-destination]').forEach(function (container) {
      var mode = container.querySelector('[data-vms-ticketing-sales-mode]');
      var externalFields = container.querySelector('[data-vms-external-ticketing-fields]');
      var relationship = container.querySelector('[data-vms-event-relationship]');
      var producerFields = container.querySelector('[data-vms-external-producer-fields]');
      if (!mode || !externalFields) return;

      function update() {
        var isExternal = String(mode.value || '') === 'external';
        externalFields.hidden = !isExternal;
        externalFields.setAttribute('aria-hidden', isExternal ? 'false' : 'true');

        if (relationship && producerFields) {
          var isHosted = isExternal && String(relationship.value || '') === 'hosted_third_party';
          producerFields.hidden = !isHosted;
          producerFields.setAttribute('aria-hidden', isHosted ? 'false' : 'true');
        }
      }

      if (!container.dataset.vmsTicketingDestinationBound) {
        container.dataset.vmsTicketingDestinationBound = '1';
        mode.addEventListener('change', update);
        if (relationship) relationship.addEventListener('change', update);
      }
      update();
    });
  }

  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function readTextAttribute(node, attributeName, fallback) {
    if (!node || !node.getAttribute) return fallback;
    var value = String(node.getAttribute(attributeName) || '').trim();
    return value || fallback;
  }

  function initScrollTarget() {
    if (hasInitializedScroll) return;
    hasInitializedScroll = true;

    var root = document.querySelector('.vms-ep-basic-grid[data-vms-scroll-target]');
    if (!root) return;

    var targetId = String(root.getAttribute('data-vms-scroll-target') || '').trim();
    if (!targetId) return;

    var target = document.getElementById(targetId);
    if (!target) return;

    window.setTimeout(function () {
      try {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } catch (e) {}
    }, 150);
  }

  function initShellController() {
    if (shellController) {
      shellController.initCollapsibleSections();
      return true;
    }

    var form = document.getElementById('post');
    if (!form) return false;

    var metabox = document.getElementById('vms_event_plan_details');
    var inside = (metabox && metabox.querySelector('.inside')) || document.querySelector('#vms_event_plan_details .inside');
    var shellRoot = metabox || inside;
    if (!inside || !shellRoot) return false;

    var postIdInput = document.getElementById('post_ID') || form.querySelector('input#post_ID');
    var postId = postIdInput && postIdInput.value ? parseInt(String(postIdInput.value || '0'), 10) || 0 : 0;
    var stateKey = 'vms_ep_sections_state_' + String(postId || 'new');

    var saved = {};
    try {
      saved = JSON.parse(localStorage.getItem(stateKey) || '{}') || {};
    } catch (e) {
      saved = {};
    }

    var reopenInput = form.querySelector('#vms-reopen-section-after-save');
    var requestedUrl = new URL(window.location.href);
    var requestedSectionKey = String(requestedUrl.searchParams.get('vms_ep_load_section') || '').trim();
    var messageSource = inside.querySelector('.vms-ep-basic-grid') || shellRoot.querySelector('.vms-ep-basic-grid');
    // These translated status labels were previously PHP-interpolated inside the inline controller.
    var lazyLoadingLabel = readTextAttribute(messageSource, 'data-vms-lazy-loading-label', 'Loading section editor…');
    var lazyErrorLabel = readTextAttribute(messageSource, 'data-vms-lazy-error-label', 'Unable to load this editor section right now. Refresh and try again.');
    var sectionAnchorMap = {
      secondary_vendors: 'vms-additional-vendors',
      staff: 'vms-staffing',
      compensation: 'vms-compensation',
      cancellation: 'vms-cancellation',
      readiness_details: 'vms-readiness-details',
      ticketing_v2: 'vms_event_plan_ticketing_v2'
    };
    var lastTouchedSectionKey = '';
    var requestedSectionHandled = false;
    var defaultCollapsedKeys = new Set(['cancellation', 'secondary_vendors', 'staff', 'readiness_details']);

    function cssEscapeValue(value) {
      var raw = String(value || '');
      if (window.CSS && typeof window.CSS.escape === 'function') {
        return window.CSS.escape(raw);
      }
      return raw.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
    }

    function normalizeRequestedSectionKey(value) {
      var raw = String(value || '').trim().toLowerCase();
      return Object.prototype.hasOwnProperty.call(sectionAnchorMap, raw) ? raw : '';
    }

    function resolveAnchorIdForSection(key) {
      var normalized = normalizeRequestedSectionKey(key);
      return normalized ? String(sectionAnchorMap[normalized] || '') : '';
    }

    function scrollSectionTargetIntoView(key, fallbackNode) {
      var anchorId = resolveAnchorIdForSection(key);
      var target = anchorId ? document.getElementById(anchorId) : null;
      var node = target || fallbackNode || null;
      if (!node) {
        return;
      }

      window.setTimeout(function () {
        try {
          node.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        } catch (e) {
          try {
            node.scrollIntoView();
          } catch (err) {}
        }
      }, 120);
    }

    function persistRequestedSection(sectionKey) {
      var normalized = normalizeRequestedSectionKey(sectionKey);
      if (!normalized) {
        return window.location.href;
      }

      var nextUrl = new URL(window.location.href);
      nextUrl.searchParams.set('vms_ep_load_section', normalized);
      var anchorId = resolveAnchorIdForSection(normalized);
      nextUrl.hash = anchorId ? ('#' + anchorId) : '';
      if (window.history && typeof window.history.replaceState === 'function') {
        window.history.replaceState({}, '', nextUrl.toString());
      }
      return nextUrl.toString();
    }

    function resolveSectionKeyFromNode(node) {
      if (!node || !node.closest) {
        return '';
      }

      var keyedNode = node.closest('[data-section-key]');
      if (keyedNode) {
        return normalizeRequestedSectionKey(keyedNode.getAttribute('data-section-key') || '');
      }

      var ticketingNode = node.closest('#vms_event_plan_ticketing_v2, #vms-ticketing-v2-source');
      if (ticketingNode) {
        return 'ticketing_v2';
      }

      return '';
    }

    function getBareTitles() {
      return Array.from(inside.querySelectorAll('h4.vms-collapsible-title')).filter(function (title) {
        return !title.closest('.vms-collapsible-section');
      });
    }

    function toBool(value) {
      return value === true || value === '1' || value === 1 || value === 'true';
    }

    function readControlState(el) {
      if (!el) return '';
      if (el.matches('input[type="checkbox"],input[type="radio"]')) {
        return el.checked ? '1' : '0';
      }
      if (el.tagName === 'SELECT') {
        return Array.from(el.options || [])
          .filter(function (opt) {
            return opt.selected;
          })
          .map(function (opt) {
            return String(opt.value || '');
          })
          .join('\u001f');
      }
      return String(el.value || '');
    }

    function isLazySectionUnloaded(section) {
      return !!section
        && section.dataset.vmsLazySection !== undefined
        && section.dataset.vmsLazyLoaded !== '1';
    }

    function getInitialCollapsedState(key, section) {
      if (isLazySectionUnloaded(section)) {
        return true;
      }
      if (Object.prototype.hasOwnProperty.call(saved, key)) {
        return toBool(saved[key]);
      }
      return defaultCollapsedKeys.has(key);
    }

    function controlDirty(el) {
      if (!el || el.disabled || el.type === 'hidden') return false;
      if (Object.prototype.hasOwnProperty.call(el.dataset, 'vmsInitialState')) {
        return readControlState(el) !== el.dataset.vmsInitialState;
      }
      if (el.matches('input[type="checkbox"],input[type="radio"]')) {
        return el.checked !== el.defaultChecked;
      }
      if (el.tagName === 'SELECT') {
        return Array.from(el.options || []).some(function (opt) {
          return opt.selected !== opt.defaultSelected;
        });
      }
      return (el.value || '') !== (el.defaultValue || '');
    }

    function sectionDirty(body) {
      var controls = body.querySelectorAll('input, select, textarea');
      for (var i = 0; i < controls.length; i++) {
        if (controlDirty(controls[i])) return true;
      }
      return false;
    }

    function setFlag(section) {
      var flag = section.querySelector('.vms-collapsible-flag');
      var body = section.querySelector('.vms-collapsible-body');
      if (!flag || !body) return;

      var collapsed = section.classList.contains('is-collapsed');
      var dirty = sectionDirty(body);
      var show = collapsed && dirty;
      flag.classList.toggle('is-visible', show);
      flag.hidden = !show;
    }

    function saveState(section) {
      var key = section.dataset.sectionKey;
      if (!key) return;
      saved[key] = section.classList.contains('is-collapsed') ? 1 : 0;
      try {
        localStorage.setItem(stateKey, JSON.stringify(saved));
      } catch (e) {}
    }

    function setCollapsed(section, collapsed) {
      var button = section.querySelector('.vms-collapsible-toggle');
      var body = section.querySelector('.vms-collapsible-body');

      section.classList.toggle('is-collapsed', !!collapsed);
      if (button) button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      if (body) body.hidden = !!collapsed;
      saveState(section);
      setFlag(section);
    }

    function bindFlagWatchers(section, body) {
      body.querySelectorAll('input, select, textarea').forEach(function (control) {
        if (!Object.prototype.hasOwnProperty.call(control.dataset, 'vmsInitialState')) {
          control.dataset.vmsInitialState = readControlState(control);
        }
        if (control.dataset.vmsCollapseBound === '1') return;
        control.dataset.vmsCollapseBound = '1';
        control.addEventListener('input', function () {
          setFlag(section);
        });
        control.addEventListener('change', function () {
          setFlag(section);
        });
      });
    }

    async function loadLazySection(section) {
      if (!section || section.dataset.vmsLazySection === undefined || section.dataset.vmsLazyLoaded === '1') {
        return true;
      }

      if (section.dataset.vmsLazyLoading === '1') {
        return false;
      }

      var lazySection = String(section.dataset.vmsLazySection || '').trim();
      var lazyUrl = String(section.dataset.vmsLazyUrl || '').trim();
      var lazyNonce = String(section.dataset.vmsLazyNonce || '').trim();
      var lazyPostId = parseInt(section.dataset.vmsLazyPostId || '0', 10) || 0;
      var body = section.querySelector('.vms-collapsible-body');
      if (!lazySection || !lazyUrl || !lazyNonce || !lazyPostId || !body) {
        return true;
      }

      section.dataset.vmsLazyLoading = '1';
      section.classList.add('is-loading');
      body.innerHTML =
        '<div class="vms-ep-card vms-ep-card--white">' +
        '<p class="description">' + escapeHtml(lazyLoadingLabel) + '</p>' +
        '</div>';

      var params = new URLSearchParams();
      params.set('action', 'vms_load_event_plan_admin_section');
      params.set('post_id', String(lazyPostId));
      params.set('section', lazySection);
      params.set('nonce', lazyNonce);

      var scenarioField = form.querySelector('input[name="_vms_ep_perf_trace_scenario"]');
      if (scenarioField && scenarioField.value) {
        params.set('_vms_ep_perf_trace_scenario', String(scenarioField.value || ''));
      }

      try {
        var response = await window.fetch(lazyUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: params.toString()
        });
        var payload = await response.json().catch(function () {
          return null;
        });
        if (!response.ok || !payload || !payload.success || !payload.data || typeof payload.data.html !== 'string') {
          throw new Error('lazy_load_failed');
        }

        body.innerHTML = payload.data.html;
        section.dataset.vmsLazyLoaded = '1';
        section.classList.remove('is-loading');
        delete section.dataset.vmsLazyLoading;
        initExistingSection(section);
        if (typeof window.vmsEventPlanInitSecondaryVendors === 'function') {
          window.vmsEventPlanInitSecondaryVendors(body);
        }
        if (typeof window.vmsEventPlanInitStaff === 'function') {
          window.vmsEventPlanInitStaff(body);
        }
        return true;
      } catch (error) {
        section.classList.remove('is-loading');
        delete section.dataset.vmsLazyLoading;
        body.innerHTML =
          '<div class="notice notice-error inline vms-notice vms-notice--warning">' +
          '<p>' + escapeHtml(lazyErrorLabel) + '</p>' +
          '</div>';
        setCollapsed(section, false);
        return false;
      }
    }

    function revealRequestedSection() {
      var key = normalizeRequestedSectionKey(requestedSectionKey);
      if (!key || requestedSectionHandled || key === 'ticketing_v2') {
        return;
      }

      var selector = '.vms-collapsible-section[data-section-key="' + cssEscapeValue(key) + '"]';
      var section = shellRoot.querySelector(selector);
      if (!section) {
        return;
      }

      requestedSectionHandled = true;
      initExistingSection(section);

      function reveal() {
        setCollapsed(section, false);
        scrollSectionTargetIntoView(key, section);
      }

      if (section.dataset.vmsLazySection !== undefined && section.dataset.vmsLazyLoaded !== '1') {
        loadLazySection(section).then(function (loaded) {
          if (loaded) {
            reveal();
          }
        });
        return;
      }

      reveal();
    }

    function initExistingSection(section) {
      if (!section) return;
      var body = section.querySelector('.vms-collapsible-body');
      var button = section.querySelector('.vms-collapsible-toggle');
      if (!body || !button) return;
      if (section.dataset.hasData !== '1' && section.dataset.hasData !== '0') {
        var title = body.querySelector('h4.vms-collapsible-title');
        if (title && toBool(title.getAttribute('data-section-has-data'))) {
          section.dataset.hasData = '1';
        } else {
          section.dataset.hasData = '0';
        }
      }
      bindFlagWatchers(section, body);
      var key = section.dataset.sectionKey || '';
      if (!section.dataset.vmsCollapsedBootstrapped) {
        section.dataset.vmsCollapsedBootstrapped = '1';
        setCollapsed(section, getInitialCollapsedState(key, section));
      } else {
        setFlag(section);
      }
    }

    function isSectionBoundaryNode(node) {
      return !!node
        && node.nodeType === 1
        && (
          node.matches('h4')
          || node.hasAttribute('data-vms-collapsible-break')
          || node.matches('.vms-collapsible-section[data-section-key]')
          || node.matches('.vms-ep-card--readiness-summary')
        );
    }

    function createWrappedSection(title, index) {
      var key = title.dataset.sectionKey || ('section_' + String(index + 1));
      var section = document.createElement('section');
      var toggle = document.createElement('button');
      var label;
      var body;

      section.className = 'vms-collapsible-section';
      section.dataset.sectionKey = key;

      if (toBool(title.getAttribute('data-section-has-data'))) {
        section.dataset.hasData = '1';
      } else {
        var carrier = title.closest('[data-vms-section-has-data]');
        if (carrier && toBool(carrier.getAttribute('data-vms-section-has-data'))) {
          section.dataset.hasData = '1';
        } else {
          section.dataset.hasData = '0';
        }
      }

      toggle.type = 'button';
      toggle.className = 'vms-collapsible-toggle';
      toggle.innerHTML =
        '<span class="vms-collapsible-chevron" aria-hidden="true"></span>' +
        '<span class="vms-collapsible-label"></span>' +
        '<span class="vms-collapsible-flag" aria-hidden="true" hidden>Changed</span>';
      label = toggle.querySelector('.vms-collapsible-label');
      if (label) label.textContent = title.textContent || 'Section';

      body = document.createElement('div');
      body.className = 'vms-collapsible-body';

      title.parentNode.insertBefore(section, title);
      section.appendChild(toggle);
      section.appendChild(body);
      body.appendChild(title);

      var node = section.nextSibling;
      while (node && !isSectionBoundaryNode(node)) {
        var nextNode = node.nextSibling;
        body.appendChild(node);
        node = nextNode;
      }

      title.classList.add('vms-collapsible-title--in-body');
      initExistingSection(section);
    }

    function initCollapsibleSections() {
	  initTicketingDestinationFields(shellRoot);
      Array.from(shellRoot.querySelectorAll('.vms-collapsible-section[data-section-key]')).forEach(initExistingSection);

      var titles = getBareTitles();
      if (!titles.length && !shellRoot.querySelector('.vms-collapsible-section[data-section-key]')) return;
      titles.forEach(function (title, index) {
        createWrappedSection(title, index);
      });
      revealRequestedSection();
    }

    window.vmsEventPlanInitCollapsibleSection = initExistingSection;
    window.vmsEventPlanInitCollapsibleSections = initCollapsibleSections;
    window.vmsEventPlanPersistRequestedSection = persistRequestedSection;
    window.vmsEventPlanRevealRequestedSection = revealRequestedSection;
	window.vmsEventPlanInitTicketingDestinationFields = initTicketingDestinationFields;

    shellController = {
      initCollapsibleSections: initCollapsibleSections
    };

    if (!form.dataset.vmsCollapseDelegatedBound) {
      form.dataset.vmsCollapseDelegatedBound = '1';
      form.addEventListener('focusin', function (event) {
        var key = resolveSectionKeyFromNode(event.target);
        if (key) {
          lastTouchedSectionKey = key;
        }
      }, true);
      form.addEventListener('input', function (event) {
        var key = resolveSectionKeyFromNode(event.target);
        if (key) {
          lastTouchedSectionKey = key;
        }
      }, true);
      form.addEventListener('change', function (event) {
        var key = resolveSectionKeyFromNode(event.target);
        if (key) {
          lastTouchedSectionKey = key;
        }
      }, true);
      form.addEventListener('click', function (event) {
        var interactedKey = resolveSectionKeyFromNode(event.target);
        if (interactedKey) {
          lastTouchedSectionKey = interactedKey;
        }

        var button = event.target.closest('.vms-collapsible-toggle');
        if (!button) return;

        var section = button.closest('.vms-collapsible-section[data-section-key]');
        if (!section || !form.contains(section)) return;
        initExistingSection(section);
        event.preventDefault();

        var collapsed = section.classList.contains('is-collapsed');
        if (collapsed && section.dataset.vmsLazySection !== undefined && section.dataset.vmsLazyLoaded !== '1') {
          loadLazySection(section).then(function (loaded) {
            if (loaded) {
              setCollapsed(section, false);
            }
          });
          return;
        }

        setCollapsed(section, !collapsed);
      });
      form.addEventListener('submit', function (event) {
        if (!reopenInput) {
          return;
        }

        var submitter = event && event.submitter ? event.submitter : document.activeElement;
        var submitterKey = resolveSectionKeyFromNode(submitter);
        var nextKey = submitterKey || lastTouchedSectionKey;
        reopenInput.value = normalizeRequestedSectionKey(nextKey);
      }, true);
    }

    initCollapsibleSections();
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initCollapsibleSections, { once: true });
    }
    window.addEventListener('load', initCollapsibleSections, { once: true });
    window.setTimeout(initCollapsibleSections, 75);
    window.setTimeout(initCollapsibleSections, 250);

    return true;
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
	  initScrollTarget();
	  initTicketingDestinationFields(document);
	}, { once: true });
  } else {
    initScrollTarget();
	initTicketingDestinationFields(document);
  }

  if (!initShellController()) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initShellController, { once: true });
    }
    window.addEventListener('load', initShellController, { once: true });
    window.setTimeout(initShellController, 75);
    window.setTimeout(initShellController, 250);
  }
})();
