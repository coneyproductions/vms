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
    var shellRoot = form;
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
      basics: 'vms-event-plan-basics',
      schedule: 'vms-event-plan-schedule',
      secondary_vendors: 'vms-additional-vendors',
      staff: 'vms-staffing',
      compensation: 'vms-compensation',
      cancellation: 'vms-cancellation',
      readiness_details: 'vms-readiness-details',
      ticketing_v2: 'vms_event_plan_ticketing_v2',
      advanced: 'vms_event_plan_advanced_controls'
    };
    var lastTouchedSectionKey = '';
    var requestedSectionHandled = false;
    var editableSectionOrder = ['basics', 'schedule', 'compensation', 'secondary_vendors', 'staff', 'ticketing_v2', 'cancellation'];
    var continuationSectionOrder = ['basics', 'schedule', 'compensation', 'secondary_vendors', 'staff', 'ticketing_v2', 'readiness_details'];
    var editableSectionKeys = new Set(editableSectionOrder);
    var activeSection = null;
    var pendingSwitchSection = null;
    var suppressBeforeUnload = false;
    var statusRoot = document.querySelector('[data-vms-workspace-status]');
    var sectionSaveUrl = statusRoot ? String(statusRoot.dataset.vmsSectionSaveUrl || '') : '';
    var sectionSaveNonce = statusRoot ? String(statusRoot.dataset.vmsSectionSaveNonce || '') : '';

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

    function waitForSectionLayout() {
      return new Promise(function (resolve) {
        if (typeof window.requestAnimationFrame !== 'function') {
          window.setTimeout(resolve, 0);
          return;
        }
        window.requestAnimationFrame(function () {
          window.requestAnimationFrame(resolve);
        });
      });
    }

    function scrollSectionWrapperIntoWorkingPosition(section) {
      if (!section) return;
      var adminBar = document.getElementById('wpadminbar');
      var offset = 16;
      if (adminBar && typeof adminBar.getBoundingClientRect === 'function') {
        offset += Math.max(0, Number(adminBar.getBoundingClientRect().height || 0));
      }
      if (typeof section.getBoundingClientRect === 'function' && typeof window.scrollTo === 'function') {
        var top = Number(section.getBoundingClientRect().top || 0) + Number(window.pageYOffset || 0) - offset;
        try {
          window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
          return;
        } catch (e) {}
      }
      try {
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } catch (e) {
        try { section.scrollIntoView(); } catch (err) {}
      }
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

    function canonicalizePersistedEventPlanUrl(canonicalEditUrl, sectionKey) {
      var canonicalUrl;
      var currentUrl;
      try {
        currentUrl = new URL(window.location.href);
        canonicalUrl = new URL(String(canonicalEditUrl || ''), currentUrl.toString());
      } catch (e) {
        return false;
      }

      var canonicalPath = String(canonicalUrl.pathname || '').split('/').pop();
      var currentPath = String(currentUrl.pathname || '').split('/').pop();
      var canonicalPostId = parseInt(String(canonicalUrl.searchParams.get('post') || '0'), 10) || 0;
      var currentPostId = parseInt(String(currentUrl.searchParams.get('post') || '0'), 10) || 0;
      var canonicalValid = canonicalUrl.origin === currentUrl.origin
        && canonicalPath === 'post.php'
        && canonicalPostId === postId
        && String(canonicalUrl.searchParams.get('action') || '') === 'edit';
      var currentIsNew = currentPath === 'post-new.php'
        && String(currentUrl.searchParams.get('post_type') || '') === 'vms_event_plan';
      var currentIsCanonical = currentPath === 'post.php'
        && currentPostId === postId
        && String(currentUrl.searchParams.get('action') || '') === 'edit';
      if (!canonicalValid || (!currentIsNew && !currentIsCanonical)) {
        return false;
      }

      var nextUrl = currentIsNew ? canonicalUrl : currentUrl;
      var normalized = normalizeRequestedSectionKey(sectionKey);
      if (normalized) {
        nextUrl.searchParams.set('vms_ep_load_section', normalized);
        var anchorId = resolveAnchorIdForSection(normalized);
        nextUrl.hash = anchorId ? ('#' + anchorId) : '';
      }
      if (!window.history || typeof window.history.replaceState !== 'function') {
        return false;
      }
      window.history.replaceState(window.history.state || {}, '', nextUrl.toString());
      return true;
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
      if (!editableSectionKeys.has(key)) return true;
      return key !== (normalizeRequestedSectionKey(requestedSectionKey) || 'basics');
    }

    function controlDirty(el) {
      if (!el || el.disabled || (el.type === 'hidden' && el.dataset.vmsTransientActionControl !== '1')) return false;
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

    function isTransientActionControl(control) {
      return !!control && control.dataset.vmsTransientActionControl === '1';
    }

    function sectionDirty(body, contract) {
      if (!body) return false;
      var controls = body.querySelectorAll('input, select, textarea');
      for (var i = 0; i < controls.length; i++) {
        if (contract === 'persisted' && isTransientActionControl(controls[i])) continue;
        if (contract === 'transient' && !isTransientActionControl(controls[i])) continue;
        if (controlDirty(controls[i])) return true;
      }
      return false;
    }

    function sectionPersistedDirty(body) {
      return sectionDirty(body, 'persisted');
    }

    function sectionTransientDirty(body) {
      return sectionDirty(body, 'transient');
    }

    function updateEventDetailsDerivedState() {
      var eventDate = document.getElementById('vms_event_date');
      var venue = document.getElementById('vms_venue_id');
      var hasUnsavedInputs = controlDirty(eventDate) || controlDirty(venue);
      document.querySelectorAll('[data-vms-event-details-holiday], [data-vms-schedule-date-status]').forEach(function (container) {
        var authoritative = container.querySelector('[data-vms-derived-authoritative]');
        var unsaved = container.querySelector('[data-vms-derived-unsaved]');
        if (authoritative) authoritative.hidden = hasUnsavedInputs;
        if (unsaved) unsaved.hidden = !hasUnsavedInputs;
      });
    }

    function applyAuthoritativeDerivedState(state) {
      if (!state || typeof state !== 'object' || Number(state.post_id || 0) !== Number(postId || 0)) {
        return false;
      }

      var holidayContainer = document.querySelector('[data-vms-event-details-holiday]');
      var scheduleContainer = document.querySelector('[data-vms-schedule-date-status]');
      var holidayAuthoritative = holidayContainer ? holidayContainer.querySelector('[data-vms-derived-authoritative]') : null;
      var scheduleAuthoritative = scheduleContainer ? scheduleContainer.querySelector('[data-vms-derived-authoritative]') : null;
      if (!holidayAuthoritative || !scheduleAuthoritative) {
        return false;
      }

      holidayAuthoritative.innerHTML = String(state.holiday_html || '');
      scheduleAuthoritative.innerHTML = String(state.schedule_date_html || '');
      [holidayContainer, scheduleContainer].forEach(function (container) {
        var authoritative = container.querySelector('[data-vms-derived-authoritative]');
        var unsaved = container.querySelector('[data-vms-derived-unsaved]');
        if (authoritative) authoritative.hidden = false;
        if (unsaved) unsaved.hidden = true;
      });

      holidayContainer.dataset.vmsSavedEventDate = String(state.event_date || '');
      holidayContainer.dataset.vmsSavedVenueId = String(state.venue_id || '');
      scheduleContainer.dataset.vmsSavedEventDate = String(state.event_date || '');

      var basicsSection = form.querySelector('.vms-collapsible-section[data-section-key="basics"]');
      var basicsSummary = basicsSection ? basicsSection.querySelector('.vms-collapsible-meta') : null;
      if (basicsSummary) {
        basicsSummary.textContent = [String(state.event_date || ''), String(state.venue_label || '')].filter(Boolean).join(' · ');
      }

      document.dispatchEvent(new CustomEvent('vms:event-plan-derived-state-refreshed', {
        detail: state
      }));
      return true;
    }

    function applyLockPayState(state) {
      if (!state || typeof state !== 'object' || typeof state.html !== 'string' || state.html === '') {
        return false;
      }
      var current = document.querySelector('[data-vms-lock-pay-actions]');
      if (!current) return true;
      var template = document.createElement('template');
      template.innerHTML = String(state.html).trim();
      var replacement = template.content ? template.content.firstElementChild : template.firstElementChild;
      if (!replacement) return false;
      current.replaceWith(replacement);
      return true;
    }

    function setFlag(section) {
      var flag = section.querySelector('.vms-collapsible-flag');
      var body = section.querySelector('.vms-collapsible-body');
      if (!flag || !body) return;

      var collapsed = section.classList.contains('is-collapsed');
      var dirty = sectionDirty(body);
      var transientDirty = sectionTransientDirty(body);
      section.classList.toggle('is-dirty', dirty);
      var show = collapsed && dirty;
      flag.classList.toggle('is-visible', show);
      flag.hidden = !show;
      setSectionStatus(section, transientDirty ? 'Unsaved action input' : (dirty ? 'Unsaved changes' : 'Saved'), dirty ? 'dirty' : 'saved');
    }

    function setSectionStatus(section, label, state) {
      if (!section) return;
      var status = section.querySelector('[data-vms-section-status]');
      if (!status) return;
      status.textContent = label;
      status.dataset.state = state || '';
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
      if (!collapsed && editableSectionKeys.has(String(section.dataset.sectionKey || ''))) {
        activeSection = section;
        section.classList.add('is-active');
      } else {
        section.classList.remove('is-active');
        if (activeSection === section) activeSection = null;
      }
      saveState(section);
      setFlag(section);
    }

    function resetSectionBaseline(section, persistedOnly) {
      if (!section) return;
      section.querySelectorAll('input, select, textarea').forEach(function (control) {
        if (persistedOnly && isTransientActionControl(control)) return;
        control.dataset.vmsInitialState = readControlState(control);
        if (control.matches('input[type="checkbox"],input[type="radio"]')) {
          control.defaultChecked = control.checked;
        } else if (control.tagName === 'SELECT') {
          Array.from(control.options || []).forEach(function (option) {
            option.defaultSelected = option.selected;
          });
        } else {
          control.defaultValue = control.value || '';
        }
      });
      setFlag(section);
    }

    function workflowActionConsumesTransient(submitter, section) {
      if (!submitter || !section || String(section.dataset.sectionKey || '') !== 'cancellation') return false;
      return ['mark_cancelled', 'create_rescheduled_draft', 'retry_cancellation_all'].indexOf(String(submitter.value || '')) !== -1;
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
          if (String(section.dataset.sectionKey || '') === 'basics') updateEventDetailsDerivedState();
        });
        control.addEventListener('change', function () {
          setFlag(section);
          if (String(section.dataset.sectionKey || '') === 'basics') updateEventDetailsDerivedState();
        });
      });
      if (String(section.dataset.sectionKey || '') === 'basics') updateEventDetailsDerivedState();
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
        if (typeof window.BVMGR_EVENT_PLAN_INIT_SECONDARY_VENDORS === 'function') {
          window.BVMGR_EVENT_PLAN_INIT_SECONDARY_VENDORS(body);
        }
        if (typeof window.BVMGR_EVENT_PLAN_INIT_STAFF === 'function') {
          window.BVMGR_EVENT_PLAN_INIT_STAFF(body);
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

    function nextWorkflowSection(section) {
      var currentKey = section ? String(section.dataset.sectionKey || '') : '';
      var index = continuationSectionOrder.indexOf(currentKey);
      var nextKey = index >= 0 ? continuationSectionOrder[index + 1] : '';
      if (!nextKey) return null;
      return form.querySelector('.vms-collapsible-section[data-section-key="' + cssEscapeValue(nextKey) + '"]');
    }

    function waitForSpecialSave(sectionKey, trigger) {
      return new Promise(function (resolve) {
        var settled = false;
        var timer = window.setTimeout(function () {
          finish(false, 'The section save did not finish in time.');
        }, 65000);
        function finish(ok, message) {
          if (settled) return;
          settled = true;
          window.clearTimeout(timer);
          document.removeEventListener('vms:event-plan-section-save-result', onResult);
          resolve({ ok: !!ok, message: message || '' });
        }
        function onResult(event) {
          var detail = event && event.detail ? event.detail : {};
          if (String(detail.section || '') !== sectionKey) return;
          finish(!!detail.ok, String(detail.message || ''));
        }
        document.addEventListener('vms:event-plan-section-save-result', onResult);
        trigger();
      });
    }

    function serializeSection(section) {
      var params = new URLSearchParams();
      section.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (control) {
        if (control.disabled || !control.name || control.type === 'submit' || control.type === 'button') return;
        if ((control.type === 'checkbox' || control.type === 'radio') && !control.checked) return;
        if (control.tagName === 'SELECT' && control.multiple) {
          Array.from(control.selectedOptions || []).forEach(function (option) {
            params.append(control.name, String(option.value || ''));
          });
          return;
        }
        params.append(control.name, String(control.value || ''));
      });
      return params;
    }

    async function saveSection(section) {
      if (!section) return { ok: false, message: 'Section not found.' };
      var key = String(section.dataset.sectionKey || '');
      setSectionStatus(section, 'Saving…', 'saving');
      section.classList.add('is-saving');

      try {
        if (key === 'secondary_vendors') {
          var vendorSave = section.querySelector('#vms-secondary-vendor-save');
          if (!vendorSave) return { ok: false, message: 'Load the Additional Vendors editor before saving.' };
          if (vendorSave.disabled) return { ok: false, message: 'Additional Vendors is already saving.' };
          return await waitForSpecialSave(key, function () { vendorSave.click(); });
        }

        if (!sectionSaveUrl || !sectionSaveNonce || !postId) {
          return { ok: false, message: 'Section save is unavailable. Reload and try again.' };
        }
        var params = serializeSection(section);
        params.set('action', 'vms_save_event_plan_section');
        params.set('post_id', String(postId));
        params.set('section', key);
        params.set('nonce', sectionSaveNonce);
        var response = await window.fetch(sectionSaveUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: params.toString()
        });
        var payload = await response.json().catch(function () { return null; });
        if (!response.ok || !payload || !payload.success) {
          return {
            ok: false,
            message: payload && payload.data && payload.data.message ? String(payload.data.message) : 'Save failed.'
          };
        }
        if (key === 'ticketing_v2') {
          var ticketingSave = section.querySelector('#vms-ticketing-v2-save-config-btn');
          if (ticketingSave) {
            var ticketingResult = await waitForSpecialSave(key, function () { ticketingSave.click(); });
            if (!ticketingResult.ok) return ticketingResult;
          }
        }
        return {
          ok: true,
          canonicalEditUrl: payload.data && payload.data.canonical_edit_url ? String(payload.data.canonical_edit_url) : '',
          derivedState: payload.data && payload.data.derived_state ? payload.data.derived_state : null,
          lockPayState: payload.data && payload.data.lock_pay_state ? payload.data.lock_pay_state : null,
          message: payload.data && payload.data.message ? String(payload.data.message) : 'Saved.'
        };
      } catch (error) {
        return { ok: false, message: error && error.message ? error.message : 'Save failed.' };
      } finally {
        section.classList.remove('is-saving');
      }
    }

    async function saveAndMaybeOpen(section, target) {
      var result = await saveSection(section);
      if (!result.ok) {
        var feedback = section.querySelector('[data-vms-section-feedback]');
        if (feedback) feedback.textContent = result.message || 'Save failed.';
        setCollapsed(section, false);
        setSectionStatus(section, 'Save failed', 'failed');
        return false;
      }
      var savedSectionKey = String(section.dataset.sectionKey || '');
      canonicalizePersistedEventPlanUrl(result.canonicalEditUrl, savedSectionKey);
      resetSectionBaseline(section, true);
      var successFeedback = section.querySelector('[data-vms-section-feedback]');
      applyLockPayState(result.lockPayState);
      if (String(section.dataset.sectionKey || '') === 'basics' && !applyAuthoritativeDerivedState(result.derivedState)) {
        setCollapsed(section, false);
        setSectionStatus(section, 'Derived checks unavailable', 'failed');
        if (successFeedback) successFeedback.textContent = 'Event Details were saved, but authoritative Holiday and availability results could not refresh. Reload this Event Plan before relying on those checks.';
        return false;
      }
      if (sectionTransientDirty(section.querySelector('.vms-collapsible-body'))) {
        setCollapsed(section, false);
        setSectionStatus(section, 'Unsaved action input', 'dirty');
        if (successFeedback) successFeedback.textContent = (result.message || 'Saved.') + ' Action-only cancellation input remains unsaved; run the guarded action or discard it before leaving this section.';
        return false;
      }
      setSectionStatus(section, 'Saved', 'saved');
      if (successFeedback) successFeedback.textContent = result.message || 'Saved.';
      if (target) {
        await openAndFocusSection(target, true);
        var targetSectionKey = String(target.dataset.sectionKey || '');
        canonicalizePersistedEventPlanUrl(result.canonicalEditUrl, targetSectionKey);
      }
      return true;
    }

    function ensureDirtyPrompt() {
      var prompt = document.getElementById('vms-event-plan-dirty-prompt');
      if (prompt) return prompt;
      prompt = document.createElement('div');
      prompt.id = 'vms-event-plan-dirty-prompt';
      prompt.className = 'vms-ep-dirty-prompt';
      prompt.hidden = true;
      prompt.innerHTML =
        '<div class="vms-ep-dirty-prompt__dialog" role="dialog" aria-modal="true" aria-labelledby="vms-ep-dirty-title">' +
          '<h2 id="vms-ep-dirty-title">Unsaved section changes</h2>' +
          '<p data-vms-dirty-copy></p>' +
          '<div class="vms-ep-dirty-prompt__actions">' +
            '<button type="button" class="button button-primary" data-vms-dirty-choice="save"></button>' +
            '<button type="button" class="button" data-vms-dirty-choice="discard"></button>' +
            '<button type="button" class="button" data-vms-dirty-choice="stay">Stay Here</button>' +
          '</div>' +
        '</div>';
      document.body.appendChild(prompt);
      prompt.addEventListener('click', function (event) {
        var choice = event.target.closest('[data-vms-dirty-choice]');
        if (!choice) return;
        var action = String(choice.dataset.vmsDirtyChoice || '');
        var current = activeSection;
        var target = pendingSwitchSection;
        if (action === 'stay') {
          prompt.hidden = true;
          pendingSwitchSection = null;
          return;
        }
        if (action === 'discard') {
          var targetKey = target ? String(target.dataset.sectionKey || '') : '';
          var nextUrl = new URL(window.location.href);
          if (targetKey) nextUrl.searchParams.set('vms_ep_load_section', targetKey);
          suppressBeforeUnload = true;
          window.location.assign(nextUrl.toString());
          return;
        }
        if (action === 'save' && current) {
          prompt.hidden = true;
          saveAndMaybeOpen(current, target).then(function (ok) {
            if (ok) pendingSwitchSection = null;
          });
        }
      });
      return prompt;
    }

    function showDirtyPrompt(current, target) {
      var prompt = ensureDirtyPrompt();
      var targetLabel = target ? (target.querySelector('.vms-collapsible-label') || {}).textContent : 'the next section';
      var currentLabel = current ? (current.querySelector('.vms-collapsible-label') || {}).textContent : 'this section';
      prompt.querySelector('[data-vms-dirty-copy]').textContent = currentLabel + ' has unsaved changes.';
      prompt.querySelector('[data-vms-dirty-choice="save"]').textContent = 'Save & Open ' + targetLabel;
      prompt.querySelector('[data-vms-dirty-choice="discard"]').textContent = 'Discard Changes & Open ' + targetLabel;
      pendingSwitchSection = target;
      prompt.hidden = false;
    }

    async function openSection(section, force) {
      if (!section) return false;
      if (activeSection && activeSection !== section && sectionDirty(activeSection.querySelector('.vms-collapsible-body')) && !force) {
        showDirtyPrompt(activeSection, section);
        return false;
      }
      if (activeSection && activeSection !== section) setCollapsed(activeSection, true);
      if (isLazySectionUnloaded(section)) {
        var loaded = await loadLazySection(section);
        if (!loaded) return false;
      }
      setCollapsed(section, false);
      lastTouchedSectionKey = String(section.dataset.sectionKey || '');
      return true;
    }

    async function openAndFocusSection(section, force) {
      var opened = await openSection(section, force);
      if (!opened) return false;
      persistRequestedSection(String(section.dataset.sectionKey || ''));
      await waitForSectionLayout();
      scrollSectionWrapperIntoWorkingPosition(section);
      return true;
    }

    function ensureSectionActions(section) {
      if (!section) return;
      var key = String(section.dataset.sectionKey || '');
      var body = section.querySelector('.vms-collapsible-body');
      if (!body || !editableSectionKeys.has(key)) return;
      if (section.dataset.vmsWorkspaceActions === '1' && body.querySelector('.vms-ep-section-actions')) return;
      section.dataset.vmsWorkspaceActions = '1';
      var actions = document.createElement('div');
      actions.className = 'vms-ep-section-actions';
      var continueButton = continuationSectionOrder.indexOf(key) !== -1 && key !== 'readiness_details'
        ? '<button type="button" class="button button-primary" data-vms-section-action="next">Save &amp; Continue</button>'
        : '';
      var saveButtonClass = continueButton ? 'button button-secondary' : 'button button-primary';
      actions.innerHTML =
        '<div class="vms-ep-section-actions__buttons">' +
          continueButton +
          '<button type="button" class="' + saveButtonClass + '" data-vms-section-action="save">Save Changes</button>' +
          '<button type="button" class="button" data-vms-section-action="discard">Discard Changes</button>' +
        '</div>' +
        '<div class="vms-ep-section-actions__state"><strong data-vms-section-status data-state="saved">Saved</strong><span class="description" data-vms-section-feedback aria-live="polite"></span></div>';
      body.appendChild(actions);
    }

    function prepareMetaboxSection(id, key, label) {
      var box = document.getElementById(id);
      if (!box || box.classList.contains('vms-collapsible-section')) return;
      var body = box.querySelector('.inside');
      if (!body) return;
      box.classList.remove('closed');
      box.classList.add('vms-collapsible-section', 'vms-collapsible-section--metabox');
      box.dataset.sectionKey = key;
      box.dataset.hasData = '1';
      body.classList.add('vms-collapsible-body');
      var toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'vms-collapsible-toggle vms-collapsible-toggle--metabox';
      toggle.innerHTML = '<span class="vms-collapsible-chevron" aria-hidden="true"></span><span class="vms-collapsible-label"></span><span class="vms-collapsible-flag" hidden>Changed</span>';
      toggle.querySelector('.vms-collapsible-label').textContent = label;
      box.insertBefore(toggle, body);
    }

    function revealRequestedSection() {
      var key = normalizeRequestedSectionKey(requestedSectionKey);
      if (!key || requestedSectionHandled) {
        return;
      }

      var selector = '.vms-collapsible-section[data-section-key="' + cssEscapeValue(key) + '"]';
      var section = shellRoot.querySelector(selector);
      if (!section) {
        return;
      }

      requestedSectionHandled = true;
      initExistingSection(section);

      openAndFocusSection(section, true);
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
      ensureSectionActions(section);
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
        '<span class="vms-collapsible-meta"></span>' +
        '<span class="vms-collapsible-flag" aria-hidden="true" hidden>Changed</span>';
      label = toggle.querySelector('.vms-collapsible-label');
      if (label) label.textContent = title.textContent || 'Section';
      var meta = toggle.querySelector('.vms-collapsible-meta');
      if (meta) meta.textContent = String(title.getAttribute('data-section-summary') || '');

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
      prepareMetaboxSection('vms_event_plan_ticketing_v2', 'ticketing_v2', 'Ticketing');
      if (statusRoot && inside && statusRoot.parentNode !== inside) {
        inside.insertBefore(statusRoot, inside.firstChild);
      } else if (statusRoot && inside && inside.firstChild !== statusRoot) {
        inside.insertBefore(statusRoot, inside.firstChild);
      }
      Array.from(shellRoot.querySelectorAll('.vms-collapsible-section[data-section-key]')).forEach(initExistingSection);

      var titles = getBareTitles();
      if (!titles.length && !shellRoot.querySelector('.vms-collapsible-section[data-section-key]')) return;
      titles.forEach(function (title, index) {
        createWrappedSection(title, index);
      });
      Array.from(shellRoot.querySelectorAll('.vms-collapsible-section[data-section-key]')).forEach(initExistingSection);
      revealRequestedSection();
    }

    window.BVMGR_EVENT_PLAN_INIT_COLLAPSIBLE_SECTION = initExistingSection;
    window.BVMGR_EVENT_PLAN_INIT_COLLAPSIBLE_SECTIONS = initCollapsibleSections;
    window.BVMGR_EVENT_PLAN_PERSIST_REQUESTED_SECTION = persistRequestedSection;
    window.BVMGR_EVENT_PLAN_CANONICALIZE_EDIT_URL = canonicalizePersistedEventPlanUrl;
    window.BVMGR_EVENT_PLAN_REVEAL_REQUESTED_SECTION = revealRequestedSection;
    window.BVMGR_EVENT_PLAN_OPEN_AND_FOCUS_SECTION = function (sectionKey, force) {
      var key = normalizeRequestedSectionKey(sectionKey);
      var section = key ? form.querySelector('.vms-collapsible-section[data-section-key="' + cssEscapeValue(key) + '"]') : null;
      return openAndFocusSection(section, !!force);
    };

    shellController = {
      initCollapsibleSections: initCollapsibleSections
    };

    if (!form.dataset.vmsCollapseDelegatedBound) {
      form.dataset.vmsCollapseDelegatedBound = '1';
      form.addEventListener('click', function (event) {
        var workflowSubmit = event.target.closest('button[type="submit"][name="vms_event_plan_action"]');
        if (!workflowSubmit || !activeSection || !sectionDirty(activeSection.querySelector('.vms-collapsible-body'))) return;
        if (!sectionPersistedDirty(activeSection.querySelector('.vms-collapsible-body')) && workflowActionConsumesTransient(workflowSubmit, activeSection)) return;
        event.preventDefault();
        event.stopPropagation();
        var blockedStatus = statusRoot ? statusRoot.querySelector('[data-vms-workflow-status]') : null;
        var activeLabel = (activeSection.querySelector('.vms-collapsible-label') || {}).textContent || 'active section';
        if (blockedStatus) blockedStatus.textContent = 'Save ' + activeLabel + ' before running this action.';
      }, true);
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

        var openSectionButton = event.target.closest('[data-vms-open-section]');
        if (openSectionButton) {
          event.preventDefault();
          var requestedKey = normalizeRequestedSectionKey(openSectionButton.dataset.vmsOpenSection || '');
          var requestedSection = requestedKey
            ? form.querySelector('.vms-collapsible-section[data-section-key="' + cssEscapeValue(requestedKey) + '"]')
            : null;
          if (requestedSection) openAndFocusSection(requestedSection, false);
          return;
        }

        var sectionAction = event.target.closest('[data-vms-section-action]');
        if (sectionAction) {
          var actionSection = sectionAction.closest('.vms-collapsible-section[data-section-key]');
          if (!actionSection) return;
          event.preventDefault();
          var sectionActionName = String(sectionAction.dataset.vmsSectionAction || '');
          if (sectionActionName === 'save') {
            saveAndMaybeOpen(actionSection, null);
          } else if (sectionActionName === 'next') {
            saveAndMaybeOpen(actionSection, nextWorkflowSection(actionSection));
          } else if (sectionActionName === 'discard') {
            suppressBeforeUnload = true;
            window.location.reload();
          }
          return;
        }

        var workflowButton = event.target.closest('[data-vms-workflow-action]');
        if (workflowButton) {
          event.preventDefault();
          var workflowStatus = statusRoot ? statusRoot.querySelector('[data-vms-workflow-status]') : null;
          if (activeSection && sectionDirty(activeSection.querySelector('.vms-collapsible-body'))) {
            var activeLabel = (activeSection.querySelector('.vms-collapsible-label') || {}).textContent || 'active section';
            if (workflowStatus) workflowStatus.textContent = 'Save ' + activeLabel + ' before changing workflow status.';
            return;
          }
          if (!statusRoot || !statusRoot.dataset.vmsWorkflowUrl || !statusRoot.dataset.vmsWorkflowNonce) return;
          workflowButton.disabled = true;
          if (workflowStatus) workflowStatus.textContent = 'Working…';
          var workflowParams = new URLSearchParams();
          workflowParams.set('action', 'vms_event_plan_workflow_action');
          workflowParams.set('post_id', String(postId));
          workflowParams.set('workflow_action', String(workflowButton.dataset.vmsWorkflowAction || ''));
          workflowParams.set('nonce', String(statusRoot.dataset.vmsWorkflowNonce || ''));
          window.fetch(String(statusRoot.dataset.vmsWorkflowUrl || ''), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: workflowParams.toString()
          }).then(function (response) {
            return response.json().then(function (payload) { return { response: response, payload: payload }; });
          }).then(function (result) {
            if (!result.response.ok || !result.payload || !result.payload.success) {
              throw new Error(result.payload && result.payload.data && result.payload.data.message ? result.payload.data.message : 'Workflow action failed.');
            }
            if (workflowStatus) workflowStatus.textContent = result.payload.data.message || 'Workflow action completed.';
            suppressBeforeUnload = true;
            window.location.reload();
          }).catch(function (error) {
            workflowButton.disabled = false;
            if (workflowStatus) workflowStatus.textContent = error && error.message ? error.message : 'Workflow action failed.';
          });
          return;
        }

        var button = event.target.closest('.vms-collapsible-toggle');
        if (!button) return;

        var section = button.closest('.vms-collapsible-section[data-section-key]');
        if (!section || !form.contains(section)) return;
        initExistingSection(section);
        event.preventDefault();

        var collapsed = section.classList.contains('is-collapsed');
        if (!collapsed && editableSectionKeys.has(String(section.dataset.sectionKey || ''))) {
          return;
        }
        if (editableSectionKeys.has(String(section.dataset.sectionKey || ''))) {
          openAndFocusSection(section, false);
        } else {
          setCollapsed(section, !collapsed);
        }
      });
      form.addEventListener('submit', function (event) {
        var submitter = event && event.submitter ? event.submitter : document.activeElement;
        if (
          submitter
          && submitter.name === 'vms_event_plan_action'
          && activeSection
          && sectionDirty(activeSection.querySelector('.vms-collapsible-body'))
          && (sectionPersistedDirty(activeSection.querySelector('.vms-collapsible-body')) || !workflowActionConsumesTransient(submitter, activeSection))
        ) {
          event.preventDefault();
          var blockedStatus = statusRoot ? statusRoot.querySelector('[data-vms-workflow-status]') : null;
          if (blockedStatus) blockedStatus.textContent = 'Save the active section before running a workflow action.';
          return;
        }
        if (!reopenInput) {
          return;
        }

        var submitterKey = resolveSectionKeyFromNode(submitter);
        var nextKey = submitterKey || lastTouchedSectionKey;
        reopenInput.value = normalizeRequestedSectionKey(nextKey);
      }, true);
      document.addEventListener('vms:event-plan-section-save-result', function (event) {
        var detail = event && event.detail ? event.detail : {};
        var key = normalizeRequestedSectionKey(String(detail.section || ''));
        var section = key
          ? form.querySelector('.vms-collapsible-section[data-section-key="' + cssEscapeValue(key) + '"]')
          : null;
        if (!section) return;
        var feedback = section.querySelector('[data-vms-section-feedback]');
        if (!detail.ok) {
          setCollapsed(section, false);
          setSectionStatus(section, 'Save failed', 'failed');
          if (feedback) feedback.textContent = String(detail.message || 'Save failed.');
          return;
        }
        resetSectionBaseline(section, true);
        if (sectionTransientDirty(section.querySelector('.vms-collapsible-body'))) {
          setCollapsed(section, false);
          setSectionStatus(section, 'Unsaved action input', 'dirty');
          if (feedback) feedback.textContent = String(detail.message || 'Saved.') + ' Action-only cancellation input remains unsaved; run the guarded action or discard it before leaving this section.';
          return;
        }
        setSectionStatus(section, 'Saved', 'saved');
        if (feedback) feedback.textContent = String(detail.message || 'Saved.');
      });
    }

    window.addEventListener('beforeunload', function (event) {
      if (suppressBeforeUnload || !activeSection || !sectionDirty(activeSection.querySelector('.vms-collapsible-body'))) return;
      event.preventDefault();
      event.returnValue = '';
      return '';
    });

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
