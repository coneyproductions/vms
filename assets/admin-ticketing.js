/* global ajaxurl, VMS_TICKETING */
(function () {
  if (typeof window === 'undefined' || !window.VMS_TICKETING) return;

  let planId = parseInt(window.VMS_TICKETING.planId || 0, 10);
  const nonce = String(window.VMS_TICKETING.nonce || '');
  const ticketUiOverridesNonce = String(window.VMS_TICKETING.ticketUiOverridesNonce || '');
  const editUrlBase = String(window.VMS_TICKETING.editUrlBase || '');
  const postForm = document.getElementById('post');
  let lastPostFormSubmitter = null;

  // Minimal HTML/attribute escapers for safe UI rendering (admin only).
  // These are intentionally dependency-free to avoid missing-helper crashes.
  function escHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function escAttr(str) {
    // Attribute context; same escaping as HTML text is sufficient here.
    return escHtml(str);
  }


  function decodeTextEntities(str) {
    const raw = (str === null || str === undefined) ? '' : String(str);
    if (!raw || raw.indexOf('&') === -1) return raw;
    const el = document.createElement('textarea');
    el.innerHTML = raw;
    return el.value;
  }

  function plainTextValue(str) {
    return decodeTextEntities(str).trim();
  }

  // Always attempt to resolve a stable plan ID, including post-new.php (auto-draft).
  function getPlanId() {
    let pid = planId;
    if (!(pid > 0)) {
      try {
        const pidEl = document.getElementById('post_ID') || document.querySelector('input#post_ID');
        const v = pidEl && pidEl.value ? parseInt(String(pidEl.value), 10) : 0;
        if (v > 0) pid = v;
      } catch (e) {
        // ignore
      }
    }
    if (pid > 0 && pid !== planId) planId = pid;
    return pid > 0 ? pid : 0;
  }

  function isPlanStableDraft(pid) {
    const plan = parseInt(pid || 0, 10) || 0;
    if (!(plan > 0)) return false;

    const adv = document.querySelector('details.vms-advanced-controls');
    if (adv) {
      const attr = String(adv.getAttribute('data-vms-stable-draft') || '').trim();
      if (attr === '1') return true;
      if (attr === '0') return false;
    }

    const originalStatus = document.getElementById('original_post_status');
    const status = String((originalStatus && originalStatus.value) || '').trim().toLowerCase();
    if (status === 'auto-draft') return false;
    return true;
  }

  function getAdvancedControlsEl() {
    return document.querySelector('details.vms-advanced-controls');
  }

  function isPromotedAdvancedControls(el) {
    return !!(el && el.classList.contains('is-promoted'));
  }

  function advancedControlsStateKey(pid) {
    const plan = parseInt(pid || 0, 10) || 0;
    if (!(plan > 0)) return '';
    return 'vms_ep_adv_controls_open_' + plan;
  }

  function persistAdvancedControlsState(pid) {
    const key = advancedControlsStateKey(pid);
    if (!key || !window.localStorage) return;
    const el = getAdvancedControlsEl();
    if (!el) return;
    if (isPromotedAdvancedControls(el)) return;
    try {
      window.localStorage.setItem(key, el.open ? '1' : '0');
    } catch (e) {
      // ignore
    }
  }
  
  // post-new.php reloads create a new auto-draft, which makes links look like they "didn't stick".
  // After actions that change link/state, always navigate to post.php for THIS plan ID.
  function goToPlanEdit(pid) {
    pid = parseInt(pid || 0, 10) || 0;
    if (pid > 0) persistAdvancedControlsState(pid);

    suppressBeforeUnloadForNavigation();

    if (editUrlBase && pid > 0) {
      window.location.href = editUrlBase + pid + '&action=edit#vms_event_plan_advanced_controls';
      return;
    }

    try {
      window.location.hash = 'vms_event_plan_advanced_controls';
    } catch (e) {
      // ignore
    }
    window.location.reload();
  }

  let suppressBeforeUnload = false;

  window.addEventListener('beforeunload', function (e) {
    if (!suppressBeforeUnload) return;
    try {
      e.preventDefault();
      e.stopImmediatePropagation();
      e.returnValue = undefined;
    } catch (err) {
      // ignore
    }
  }, true);

  function suppressBeforeUnloadForNavigation() {
    suppressBeforeUnload = true;
    try { window.onbeforeunload = null; } catch (e) {}
  }

  function safeReload(delayMs) {
    persistAdvancedControlsState(getPlanId());
    suppressBeforeUnloadForNavigation();
    const wait = Math.max(0, parseInt(delayMs || 0, 10) || 0);
    if (wait > 0) {
      window.setTimeout(() => { window.location.reload(); }, wait);
      return;
    }
    window.location.reload();
  }

  function persistRequestedSectionTarget(sectionKey) {
    const normalized = String(sectionKey || '').trim();
    if (!normalized) return window.location.href;

    if (typeof window.vmsEventPlanPersistRequestedSection === 'function') {
      return window.vmsEventPlanPersistRequestedSection(normalized);
    }

    try {
      const anchorMap = {
        ticketing_v2: 'vms_event_plan_ticketing_v2',
      };
      const nextUrl = new URL(window.location.href);
      nextUrl.searchParams.set('vms_ep_load_section', normalized);
      nextUrl.hash = anchorMap[normalized] ? ('#' + anchorMap[normalized]) : '';
      if (window.history && typeof window.history.replaceState === 'function') {
        window.history.replaceState({}, '', nextUrl.toString());
      }
      return nextUrl.toString();
    } catch (e) {
      return window.location.href;
    }
  }

  function maybeFocusEventPlanTicketingArea() {
    let requestedSection = '';
    try {
      requestedSection = String(new URL(window.location.href).searchParams.get('vms_ep_load_section') || '').trim();
    } catch (e) {}
    if (requestedSection !== 'ticketing_v2') return;

    const ticketingBox = document.getElementById('vms_event_plan_ticketing_v2');
    if (!ticketingBox || ticketingBox.dataset.vmsTicketingFocusHandled === '1') return;
    ticketingBox.dataset.vmsTicketingFocusHandled = '1';

    window.setTimeout(() => {
      try { ticketingBox.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch (e) { try { ticketingBox.scrollIntoView(); } catch (err) {} }
      const focusTarget = ticketingBox.querySelector('#vms-ticketing-v2-source .button, #vms-ticketing-v2-source select, #vms-ticketing-v2-source input, #vms-ticketing-v2-source textarea, #vms-ticketing-v2-source a');
      if (!focusTarget || typeof focusTarget.focus !== 'function') return;
      try { focusTarget.focus({ preventScroll: true }); } catch (e) { try { focusTarget.focus(); } catch (err) {} }
    }, 150);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', maybeFocusEventPlanTicketingArea, { once: true });
  } else {
    maybeFocusEventPlanTicketingArea();
  }

  function waitMs(ms) {
    return new Promise((resolve) => window.setTimeout(resolve, ms));
  }

  async function maybeAutoSaveEventPlan(setStatusMsg) {
    try {
      const hasWpData = !!(window.wp && wp.data && wp.data.select && wp.data.dispatch);
      if (!hasWpData) return true;

      const sel = wp.data.select('core/editor');
      const dsp = wp.data.dispatch('core/editor');
      if (!sel || !dsp || typeof sel.isEditedPostDirty !== 'function' || typeof dsp.savePost !== 'function') {
        return true;
      }

      let isDirty = false;
      try { isDirty = !!sel.isEditedPostDirty(); } catch (e) { isDirty = false; }
      if (!isDirty) return true;

      if (typeof setStatusMsg === 'function') {
        setStatusMsg('Saving Event Plan changes…', 'info');
      }

      try {
        const saveResult = dsp.savePost();
        if (saveResult && typeof saveResult.then === 'function') {
          await saveResult;
        }
      } catch (e) {
        // continue to settle/read final editor state below
      }

      const deadline = Date.now() + 15000;
      while (Date.now() < deadline) {
        const saving = (typeof sel.isSavingPost === 'function') ? !!sel.isSavingPost() : false;
        const autosaving = (typeof sel.isAutosavingPost === 'function') ? !!sel.isAutosavingPost() : false;
        if (!saving && !autosaving) break;
        await waitMs(120);
      }

      const saveSucceeded = (typeof sel.didPostSaveRequestSucceed === 'function')
        ? !!sel.didPostSaveRequestSucceed()
        : true;
      const stillDirty = (typeof sel.isEditedPostDirty === 'function')
        ? !!sel.isEditedPostDirty()
        : false;

      if (!saveSucceeded || stillDirty) {
        if (typeof setStatusMsg === 'function') {
          setStatusMsg('Could not save all Event Plan edits. Save Draft, then retry.', 'error');
        }
        return false;
      }
    } catch (e) {
      // Fail-open so ticketing actions are not blocked in non-block-editor contexts.
      return true;
    }

    return true;
  }

  const formSnapshotTtlMs = 10 * 60 * 1000;

  function snapshotStorageKey(pid) {
    const plan = parseInt(pid || 0, 10) || 0;
    if (!(plan > 0)) return '';
    return 'vms_ep_unsaved_snapshot_' + plan;
  }

  function cssEscapeValue(value) {
    const raw = String(value || '');
    if (window.CSS && typeof window.CSS.escape === 'function') {
      return window.CSS.escape(raw);
    }
    return raw.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
  }

  function shouldSnapshotField(el) {
    if (!el || el.disabled) return false;
    const tag = String(el.tagName || '').toLowerCase();
    if (tag !== 'input' && tag !== 'select' && tag !== 'textarea') return false;

    const type = String(el.type || '').toLowerCase();
    if (type === 'hidden' || type === 'submit' || type === 'button' || type === 'reset'
      || type === 'file' || type === 'image' || type === 'password') {
      return false;
    }

    const id = String(el.id || '').trim();
    if (id === 'vms-ticketing-search' || id === 'vms-ticketing-product-search') {
      return false;
    }

    if (!id && !String(el.name || '').trim()) return false;
    return true;
  }

  function fieldLocator(el) {
    const id = String(el.id || '').trim();
    if (id) return 'id:' + id;

    if (!postForm) return '';
    const name = String(el.name || '').trim();
    if (!name) return '';

    try {
      const nodes = Array.from(postForm.querySelectorAll(`[name="${cssEscapeValue(name)}"]`));
      const index = nodes.indexOf(el);
      if (index < 0) return '';
      return 'name:' + name + ':' + index;
    } catch (e) {
      return '';
    }
  }

  function resolveField(locator) {
    const raw = String(locator || '');
    if (!raw) return null;

    if (raw.indexOf('id:') === 0) {
      return document.getElementById(raw.substring(3));
    }

    if (raw.indexOf('name:') !== 0 || !postForm) return null;

    const payload = raw.substring(5);
    const sep = payload.lastIndexOf(':');
    if (sep <= 0) return null;

    const name = payload.substring(0, sep);
    const idx = parseInt(payload.substring(sep + 1), 10);
    if (!(idx >= 0)) return null;

    try {
      const nodes = postForm.querySelectorAll(`[name="${cssEscapeValue(name)}"]`);
      return nodes[idx] || null;
    } catch (e) {
      return null;
    }
  }

  function readFieldState(el) {
    const tag = String(el.tagName || '').toLowerCase();
    const type = String(el.type || '').toLowerCase();

    if (tag === 'select' && !!el.multiple) {
      const values = Array.from(el.options || [])
        .filter((opt) => !!opt.selected)
        .map((opt) => String(opt.value || ''));
      return { kind: 'select-multi', value: values };
    }

    if (tag === 'input' && (type === 'checkbox' || type === 'radio')) {
      return { kind: 'checked', value: !!el.checked };
    }

    return { kind: 'value', value: String(el.value || '') };
  }

  function writeFieldState(el, state) {
    if (!el || !state || !state.kind) return false;

    let changed = false;
    if (state.kind === 'checked') {
      const nextChecked = !!state.value;
      if (!!el.checked !== nextChecked) {
        el.checked = nextChecked;
        changed = true;
      }
    } else if (state.kind === 'select-multi') {
      if (String(el.tagName || '').toLowerCase() !== 'select' || !el.multiple) return false;
      const wanted = new Set(Array.isArray(state.value) ? state.value.map((v) => String(v || '')) : []);
      Array.from(el.options || []).forEach((opt) => {
        const should = wanted.has(String(opt.value || ''));
        if (!!opt.selected !== should) {
          opt.selected = should;
          changed = true;
        }
      });
    } else {
      const nextValue = String(state.value || '');
      if (String(el.value || '') !== nextValue) {
        el.value = nextValue;
        changed = true;
      }
    }

    if (changed) {
      try { el.dispatchEvent(new Event('input', { bubbles: true })); } catch (e) {}
      try { el.dispatchEvent(new Event('change', { bubbles: true })); } catch (e) {}
    }
    return changed;
  }

  function captureEventPlanSnapshot(reason, setStatusMsg) {
    const pid = getPlanId();
    const key = snapshotStorageKey(pid);
    if (!key || !postForm || !window.sessionStorage) return false;

    const fields = [];
    const controls = Array.from(postForm.querySelectorAll('input, select, textarea'));
    controls.forEach((el) => {
      if (!shouldSnapshotField(el)) return;
      const locator = fieldLocator(el);
      if (!locator) return;
      fields.push({
        locator: locator,
        state: readFieldState(el),
      });
    });

    if (!fields.length) return false;

    try {
      window.sessionStorage.setItem(key, JSON.stringify({
        v: 1,
        at: Date.now(),
        reason: String(reason || ''),
        fields: fields,
      }));
      return true;
    } catch (e) {
      if (typeof setStatusMsg === 'function') {
        setStatusMsg('Could not preserve unsaved edits locally. Save Draft before continuing.', 'warning');
      }
      return false;
    }
  }

  function restoreEventPlanSnapshot(setStatusMsg) {
    const pid = getPlanId();
    const key = snapshotStorageKey(pid);
    if (!key || !postForm || !window.sessionStorage) return 0;

    let raw = '';
    try {
      raw = String(window.sessionStorage.getItem(key) || '');
    } catch (e) {
      return 0;
    }
    if (!raw) return 0;

    let payload = null;
    try {
      payload = JSON.parse(raw);
    } catch (e) {
      payload = null;
    }
    if (!payload || !Array.isArray(payload.fields)) {
      try { window.sessionStorage.removeItem(key); } catch (e) {}
      return 0;
    }

    const at = parseInt(payload.at || 0, 10) || 0;
    const ageMs = Date.now() - at;
    if (at <= 0 || ageMs < 0 || ageMs > formSnapshotTtlMs) {
      try { window.sessionStorage.removeItem(key); } catch (e) {}
      return 0;
    }

    let restored = 0;
    payload.fields.forEach((row) => {
      if (!row || !row.locator) return;
      const el = resolveField(row.locator);
      if (!el) return;
      if (writeFieldState(el, row.state || {})) restored += 1;
    });

    try { window.sessionStorage.removeItem(key); } catch (e) {}

    if (restored > 0 && typeof setStatusMsg === 'function') {
      setStatusMsg('Restored unsaved Event Plan edits after ticketing reload.', 'info');
    }
    return restored;
  }


  const $ = (id) => document.getElementById(id);

  // Persist Advanced Controls disclosure state (Event Plan edit screen)
  (function persistAdvancedControls() {
    try {
      const el = getAdvancedControlsEl();
      const pid = getPlanId();
      if (!el || !(pid > 0)) return;
      const box = el.closest ? el.closest('.postbox') : null;
      if (isPromotedAdvancedControls(el)) {
        el.open = true;
        const hash = String((window.location && window.location.hash) || '');
        if ((hash === '#vms-advanced-controls' || hash === '#vms_event_plan_advanced_controls') && box) {
          box.classList.remove('closed');
        }
        return;
      }
      const key = advancedControlsStateKey(pid);
      const saved = window.localStorage ? window.localStorage.getItem(key) : null;
      if (saved === '1') el.open = true;
      if (saved === '0') el.open = false;
      if (window.location && (window.location.hash === '#vms-advanced-controls' || window.location.hash === '#vms_event_plan_advanced_controls')) el.open = true;
      el.addEventListener('toggle', function () {
        if (!window.localStorage) return;
        window.localStorage.setItem(key, el.open ? '1' : '0');
      });
    } catch (e) {
      // ignore
    }
  })();
 
  const searchInp = $('vms-ticketing-search');
  const resultsWrap = $('vms-ticketing-results');
  const msgWrap = $('vms-ticketing-msg');
  const linkBtn = $('vms-ticketing-link-btn');
  const unlinkBtn = $('vms-ticketing-unlink-btn');
  const refreshBtn = $('vms-ticketing-refresh-btn');

  if (!searchInp || !resultsWrap || !msgWrap || !linkBtn) return;

  let selectedTecId = 0;
  let lastQ = '';
  let t = null;

  const _origTitle = document.title;
  function setBusyTitle(on, label) {
    try {
      if (!on) {
        document.title = _origTitle;
        return;
      }
      const tag = label ? (String(label) + ' ') : '';
      document.title = tag + 'Sync… | ' + _origTitle;
    } catch (e) {
      // ignore
    }
  }

  function noticeSeverityClass(type) {
    const t = String(type || '').toLowerCase();
    if (t === 'error' || t === 'critical') return 'vms-notice--critical';
    if (t === 'warning') return 'vms-notice--warning';
    if (t === 'success') return 'vms-notice--success';
    return 'vms-notice--info';
  }

  function setMsg(text, type) {
    msgWrap.textContent = text || '';
    const t = String(type || '').trim();
    const severity = t ? noticeSeverityClass(t) : '';
    msgWrap.className = 'vms-ticketing__msg vms-notice'
      + (severity ? (' ' + severity) : '')
      + (t ? (' is-' + t) : '');
  }

  function setLinkSensitiveDisabled(disabled) {
    const isDisabled = !!disabled;
    const controls = document.querySelectorAll('[data-vms-link-sensitive="1"]');
    controls.forEach((el) => {
      if (!el) return;
      if (!el.hasAttribute('data-vms-orig-disabled')) {
        el.setAttribute('data-vms-orig-disabled', el.disabled ? '1' : '0');
      }
      const baseDisabled = (el.getAttribute('data-vms-orig-disabled') === '1');

      if (isDisabled) {
        el.disabled = true;
        el.classList.add('disabled');
      } else {
        el.disabled = baseDisabled;
        el.classList.remove('disabled');
      }
    });

    const root = document.querySelector('.vms-ticketing');
    if (root) root.classList.toggle('is-guarded', isDisabled);
  }

  function enforceStableDraftGuard() {
    const pid = getPlanId();
    const stable = isPlanStableDraft(pid);
    if (stable) {
      const root = document.querySelector('.vms-ticketing');
      if (root) root.classList.remove('is-guarded');
      return true;
    }

    setLinkSensitiveDisabled(true);
    setMsg('Save Draft first. Link-sensitive controls unlock after this Event Plan has a stable draft.', 'warning');
    return false;
  }

  function parseJsonOrError(r) {
    return r.text().then((t) => {
      try {
        return JSON.parse(t);
      } catch (e) {
        const msg = String(t || '').trim();
        return { success: false, data: { message: 'bad_response', raw: msg.substring(0, 200) } };
      }
    });
  }

  function buildAjaxFailure(message, raw, extra) {
    const data = Object.assign({
      message: String(message || 'request_failed'),
      raw: String(raw || ''),
    }, extra || {});
    return { success: false, data };
  }

  function post(action, data, timeoutMs) {
    const ms = parseInt(timeoutMs || 0, 10) || 20000;

    try {
      const fd = new FormData();
      fd.append('action', action);
      fd.append('nonce', nonce);
      Object.keys(data || {}).forEach((k) => {
        fd.append(k, data[k]);
      });

      return fetchWithTimeout(ajaxurl, {
        method: 'POST',
        credentials: 'same-origin',
        body: fd,
      }, ms).then(parseJsonOrError).catch((err) => {
        if (err && err.name === 'AbortError') {
          const seconds = Math.max(1, Math.round(ms / 1000));
          return buildAjaxFailure('timeout', 'The request exceeded the ' + seconds + ' second browser timeout.');
        }
        return buildAjaxFailure('network_error', String((err && err.message) || err || ''));
      });
    } catch (err) {
      return Promise.resolve(buildAjaxFailure(
        'request_setup_error',
        String((err && err.message) || err || ''),
        { exception_type: String((err && err.name) || '') }
      ));
    }
  }

  function postWithRequestNonce(action, requestNonce, data, timeoutMs) {
    const ms = parseInt(timeoutMs || 0, 10) || 20000;

    try {
      const fd = new FormData();
      fd.append('action', action);
      fd.append('nonce', String(requestNonce || ''));
      Object.keys(data || {}).forEach((k) => {
        fd.append(k, data[k]);
      });

      return fetchWithTimeout(ajaxurl, {
        method: 'POST',
        credentials: 'same-origin',
        body: fd,
      }, ms).then(parseJsonOrError).catch((err) => {
        if (err && err.name === 'AbortError') {
          const seconds = Math.max(1, Math.round(ms / 1000));
          return buildAjaxFailure('timeout', 'The request exceeded the ' + seconds + ' second browser timeout.');
        }
        return buildAjaxFailure('network_error', String((err && err.message) || err || ''));
      });
    } catch (err) {
      return Promise.resolve(buildAjaxFailure(
        'request_setup_error',
        String((err && err.message) || err || ''),
        { exception_type: String((err && err.name) || '') }
      ));
    }
  }

  restoreEventPlanSnapshot(setMsg);
  enforceStableDraftGuard();

  (function initCalendarUnpublishedSuppressor() {
    const wrap = document.querySelector('[data-vms-calendar-suppressor="1"]');
    const checkbox = $('vms-calendar-unpublished-suppress');
    const saveBtn = $('vms-calendar-unpublished-suppress-save');
    const statusWrap = $('vms-calendar-unpublished-suppress-status');
    if (!wrap || !checkbox || !saveBtn || !statusWrap) return;

    const requestNonce = String(wrap.getAttribute('data-save-nonce') || '').trim();
    const wrappedPlanId = parseInt(wrap.getAttribute('data-post-id') || '0', 10) || 0;
    let savedValue = String(wrap.getAttribute('data-current') || (checkbox.checked ? '1' : '0'));

    function setSuppressStatus(message, tone) {
      void tone;
      statusWrap.textContent = String(message || '');
    }

    function syncSuppressSaveState() {
      const currentValue = checkbox.checked ? '1' : '0';
      saveBtn.disabled = currentValue === savedValue;
    }

    if (!(wrappedPlanId > 0) || !requestNonce) {
      checkbox.disabled = true;
      saveBtn.disabled = true;
      setSuppressStatus('Save Draft first, then reload this page to use this control.', 'error');
      return;
    }

    checkbox.addEventListener('change', function () {
      setSuppressStatus('', '');
      syncSuppressSaveState();
    });

    saveBtn.addEventListener('click', function (e) {
      if (e && typeof e.preventDefault === 'function') e.preventDefault();
      if (e && typeof e.stopPropagation === 'function') e.stopPropagation();

      const nextValue = checkbox.checked ? '1' : '0';
      if (nextValue === savedValue) {
        setSuppressStatus('No warning-setting change to save.', 'success');
        syncSuppressSaveState();
        return;
      }

      checkbox.disabled = true;
      saveBtn.disabled = true;
      setSuppressStatus('Saving warning setting…', '');

      postWithRequestNonce('vms_save_event_plan_calendar_unpublished_suppress', requestNonce, {
        post_id: wrappedPlanId,
        suppress: nextValue,
      }).then((res) => {
        checkbox.disabled = false;
        if (!res || !res.success) {
          const detail = (res && res.data && res.data.message) ? String(res.data.message) : '';
          const noise = (res && res.data && (res.data._vms_ajax_noise || res.data.raw)) ? String(res.data._vms_ajax_noise || res.data.raw) : '';
          setSuppressStatus(detail ? ('Save failed: ' + detail + (noise ? (' · ' + noise) : '')) : ('Save failed.' + (noise ? (' · ' + noise) : '')), 'error');
          syncSuppressSaveState();
          return;
        }

        savedValue = nextValue;
        wrap.setAttribute('data-current', savedValue);
        setSuppressStatus((res.data && res.data.message) ? String(res.data.message) : 'Warning setting saved.', 'success');
        syncSuppressSaveState();
      }).catch(() => {
        checkbox.disabled = false;
        setSuppressStatus('Save failed.', 'error');
        syncSuppressSaveState();
      });
    });

    syncSuppressSaveState();
  })();

  function clearResults() {
    resultsWrap.innerHTML = '';
    selectedTecId = 0;
    linkBtn.disabled = true;
  }

  function renderItems(items) {
    clearResults();

    if (!items || !items.length) {
      resultsWrap.innerHTML = '<div class="description">No matches.</div>';
      return;
    }

    const list = document.createElement('div');
    list.className = 'vms-ticketing__list';

    const hint = document.createElement('div');
    hint.className = 'description';
    hint.textContent = 'Search results (not linked yet). Select one, then click “Link selected TEC event”.';
    resultsWrap.appendChild(hint);


    items.forEach((it) => {
      const wpId = parseInt((it && (it.wp_id || it.id)) || 0, 10) || 0;

      const row = document.createElement('label');
      row.className = 'vms-ticketing__item';

      const radio = document.createElement('input');
      radio.type = 'radio';
      radio.name = 'vms_ticketing_selected_tec';
      radio.value = String(wpId);

      radio.addEventListener('change', () => {
        selectedTecId = parseInt(radio.value, 10) || 0;
        if (!isPlanStableDraft(getPlanId())) {
          enforceStableDraftGuard();
          return;
        }
        linkBtn.disabled = !(selectedTecId > 0);
      });

      const meta = document.createElement('span');
      meta.className = 'vms-ticketing__item-meta';

      const title = document.createElement('strong');
      title.textContent = it.title || ('Event #' + (wpId || it.id));

      const small = document.createElement('span');
      small.className = 'vms-ticketing__item-sub';

      const parts = [];
      if (wpId > 0) parts.push('WP #' + wpId);

      if (it.legacy && it.legacy.length) {
        const legacyParts = it.legacy
          .map((l) => {
            if (!l) return '';
            const label = l.label || l.key || 'Legacy';
            const val = l.value || '';
            return val ? (label + ': ' + val) : '';
          })
          .filter(Boolean);
        if (legacyParts.length) parts.push(legacyParts.join(' · '));
      }

      if (it.start) parts.push(it.start);
      small.textContent = parts.join(' · ');

      const a = document.createElement('a');
      a.href = it.permalink || '#';
      a.target = '_blank';
      a.rel = 'noopener noreferrer';
      a.textContent = 'view';
      a.className = 'vms-ticketing__item-link';

      meta.appendChild(title);
      meta.appendChild(small);
      meta.appendChild(a);

      row.appendChild(radio);
      row.appendChild(meta);
      list.appendChild(row);
    });

    resultsWrap.appendChild(list);
  }

  function doSearch(q) {
    if (q.length < 2) {
      clearResults();
      setMsg('', '');
      return;
    }

    if (!isPlanStableDraft(getPlanId())) {
      enforceStableDraftGuard();
      return;
    }

    setMsg('Searching…', 'info');

    post('vms_ticketing_search_tec_events', { q })
      .then((res) => {
        if (!res || !res.success) {
          const detail = (res && res.data && res.data.message) ? String(res.data.message) : '';
          const noise = (res && res.data && (res.data._vms_ajax_noise || res.data.raw)) ? String(res.data._vms_ajax_noise || res.data.raw) : '';
          setMsg(detail ? ('Search failed: ' + detail + (noise ? (' · ' + noise) : '')) : ('Search failed.' + (noise ? (' · ' + noise) : '')), 'error');
          return;
        }
        setMsg('', '');
        renderItems((res.data && res.data.items) ? res.data.items : []);
      })
      .catch(() => setMsg('Search failed.', 'error'));
  }

  searchInp.addEventListener('input', function () {
    const q = String(searchInp.value || '').trim();
    if (q === lastQ) return;
    lastQ = q;

    if (t) window.clearTimeout(t);
    t = window.setTimeout(() => doSearch(q), 250);
  });

  linkBtn.addEventListener('click', async function (e) {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();
    if (e && typeof e.stopPropagation === 'function') e.stopPropagation();

    const pid = getPlanId();
    if (!(selectedTecId > 0)) {
      setMsg('Select a TEC event first.', 'error');
      return;
    }
    if (!(pid > 0)) {
      setMsg('Save this Event Plan first (Draft is fine), then try linking again.', 'error');
      return;
    }
    if (!isPlanStableDraft(pid)) {
      enforceStableDraftGuard();
      return;
    }

    if (!(await maybeAutoSaveEventPlan(setMsg))) {
      return;
    }
    captureEventPlanSnapshot('link_tec_event', setMsg);

    linkBtn.disabled = true;
    setMsg('Linking…', 'info');

    post('vms_ticketing_link_tec_event', { plan_id: pid, tec_event_id: selectedTecId })
      .then((res) => {
        if (!res || !res.success) {
                    const detail = (res && res.data && res.data.message) ? String(res.data.message) : '';
          const noise = (res && res.data && (res.data._vms_ajax_noise || res.data.raw)) ? String(res.data._vms_ajax_noise || res.data.raw) : '';
          setMsg(detail ? ('Link failed: ' + detail + (noise ? (' · ' + noise) : '')) : ('Link failed.' + (noise ? (' · ' + noise) : '')), 'error');
          linkBtn.disabled = false;
          return;
        }
        setMsg('Linked. Refreshing…', 'success');
        safeReload(250);
      })
      .catch(() => {
        setMsg('Link failed.', 'error');
        linkBtn.disabled = false;
      });
  });

  if (unlinkBtn) {
    unlinkBtn.addEventListener('click', async function (e) {
      if (e && typeof e.preventDefault === 'function') e.preventDefault();
      if (e && typeof e.stopPropagation === 'function') e.stopPropagation();

      const pid = getPlanId();
      if (!(pid > 0)) return;
      if (!isPlanStableDraft(pid)) {
        enforceStableDraftGuard();
        return;
      }
      if (!window.confirm('Unlink this calendar event from the Event Plan?')) return;

      if (!(await maybeAutoSaveEventPlan(setMsg))) {
        return;
      }
      captureEventPlanSnapshot('unlink_tec_event', setMsg);

      setMsg('Unlinking…', 'info');
      unlinkBtn.disabled = true;

      post('vms_ticketing_unlink_tec_event', { plan_id: pid })
        .then((res) => {
          if (!res || !res.success) {
            const detail = (res && res.data && res.data.message) ? String(res.data.message) : '';
            const noise = (res && res.data && (res.data._vms_ajax_noise || res.data.raw)) ? String(res.data._vms_ajax_noise || res.data.raw) : '';
            setMsg(detail ? ('Unlink failed: ' + detail + (noise ? (' · ' + noise) : '')) : ('Unlink failed.' + (noise ? (' · ' + noise) : '')), 'error');
            unlinkBtn.disabled = false;
            return;
          }
          setMsg('Unlinked. Refreshing…', 'success');
          safeReload(250);
        })
        .catch(() => {
          setMsg('Unlink failed (request error).', 'error');
          unlinkBtn.disabled = false;
        });
    });
  }

  if (refreshBtn) {
    refreshBtn.addEventListener('click', async function (e) {
      if (e && typeof e.preventDefault === 'function') e.preventDefault();
      if (e && typeof e.stopPropagation === 'function') e.stopPropagation();

      const pid = getPlanId();
      if (!(pid > 0)) return;
      if (!isPlanStableDraft(pid)) {
        enforceStableDraftGuard();
        return;
      }

      if (!(await maybeAutoSaveEventPlan(setMsg))) {
        return;
      }
      captureEventPlanSnapshot('refresh_ticket_stats', setMsg);

      setMsg('Refreshing ticket stats…', 'info');
      refreshBtn.disabled = true;

      post('vms_ticketing_refresh_stats', { plan_id: pid })
        .then((res) => {
          if (!res || !res.success) {
            const detail = (res && res.data && res.data.message) ? String(res.data.message) : '';
            const noise = (res && res.data && (res.data._vms_ajax_noise || res.data.raw)) ? String(res.data._vms_ajax_noise || res.data.raw) : '';
            setMsg(detail ? ('Refresh failed: ' + detail + (noise ? (' · ' + noise) : '')) : ('Refresh failed.' + (noise ? (' · ' + noise) : '')), 'error');
            refreshBtn.disabled = false;
            return;
          }
          setMsg('Refreshed. Refreshing…', 'success');
          safeReload(250);
        })
        .catch(() => {
          setMsg('Refresh failed (request error).', 'error');
          refreshBtn.disabled = false;
        });
    });
  }

  // Manual Woo product attachment (legacy Woo-only)
  const prodSearchInp = $('vms-ticketing-product-search');
  const prodResultsWrap = $('vms-ticketing-product-results');
  const manualList = $('vms-ticketing-manual-list');

  function renderProducts(items) {
    prodResultsWrap.innerHTML = '';

    if (!items || !items.length) {
      prodResultsWrap.textContent = 'No matching Woo products.';
      return;
    }

    const ul = document.createElement('ul');
    ul.className = 'vms-ticketing__results-list';

    items.forEach((it) => {
      const li = document.createElement('li');
      li.className = 'vms-ticketing__result';

      const left = document.createElement('div');
      left.className = 'vms-ticketing__result-main';

      const title = document.createElement('div');
      title.className = 'vms-ticketing__result-title';
      title.textContent = `#${it.id} — ${it.title}`;

      const meta = document.createElement('div');
      meta.className = 'vms-ticketing__result-meta';
      meta.textContent = `${it.price || ''}${it.status ? ' · ' + it.status : ''}`;

      left.appendChild(title);
      left.appendChild(meta);

      const actions = document.createElement('div');
      actions.className = 'vms-ticketing__result-actions';

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'button button-small';
      btn.textContent = 'Add';
      btn.dataset.vmsTicketingAttach = String(it.id);
      actions.appendChild(btn);

      if (it.edit) {
        const a = document.createElement('a');
        a.href = it.edit;
        a.target = '_blank';
        a.rel = 'noopener noreferrer';
        a.textContent = 'Edit';
        a.className = 'vms-ticketing__mini-link';
        actions.appendChild(a);
      }

      li.appendChild(left);
      li.appendChild(actions);
      ul.appendChild(li);
    });

    prodResultsWrap.appendChild(ul);
  }

  if (prodSearchInp && prodResultsWrap && planId > 0) {
    let prodTimer = null;
    let prodLastQ = '';

    prodSearchInp.addEventListener('input', function () {
      const q = String(prodSearchInp.value || '').trim();

      if (q.length < 2) {
        prodLastQ = '';
        prodResultsWrap.innerHTML = '';
        return;
      }
      if (q === prodLastQ) return;

      prodLastQ = q;
      if (prodTimer) window.clearTimeout(prodTimer);

      prodTimer = window.setTimeout(function () {
        setMsg('Searching products…', 'info');
        post('vms_ticketing_search_products', { q: q })
          .then((res) => {
            if (!res || !res.success) {
              setMsg((res && res.data && res.data.message) ? res.data.message : 'Product search failed.', 'error');
              return;
            }
            setMsg('', '');
            renderProducts(res.data.items || []);
          })
          .catch(() => setMsg('Product search failed.', 'error'));
      }, 250);
    });

    prodResultsWrap.addEventListener('click', async function (e) {
      const t = e.target;
      if (!t || !t.dataset || !t.dataset.vmsTicketingAttach) return;

      const pid = parseInt(t.dataset.vmsTicketingAttach || '0', 10) || 0;
      if (!pid) return;

      if (!(await maybeAutoSaveEventPlan(setMsg))) {
        return;
      }
      captureEventPlanSnapshot('attach_ticket_product', setMsg);

      t.disabled = true;
      setMsg('Attaching product…', 'info');

      post('vms_ticketing_attach_product', { plan_id: planId, product_id: pid })
        .then((res) => {
          if (res && res.success) {
            safeReload(0);
            return;
          }
          setMsg((res && res.data && res.data.message) ? res.data.message : 'Attach failed.', 'error');
          t.disabled = false;
        })
        .catch(() => {
          setMsg('Attach failed.', 'error');
          t.disabled = false;
        });
    });
  }

  if (manualList && planId > 0) {
    manualList.addEventListener('click', async function (e) {
      const t = e.target;
      if (!t || !t.dataset || !t.dataset.vmsTicketingDetach) return;

      const pid = parseInt(t.dataset.vmsTicketingDetach || '0', 10) || 0;
      if (!pid) return;

      if (!window.confirm('Remove this attached product?')) return;

      if (!(await maybeAutoSaveEventPlan(setMsg))) {
        return;
      }
      captureEventPlanSnapshot('detach_ticket_product', setMsg);

      t.disabled = true;
      setMsg('Removing product…', 'info');

      post('vms_ticketing_detach_product', { plan_id: planId, product_id: pid })
        .then((res) => {
          if (res && res.success) {
            safeReload(0);
            return;
          }
          setMsg((res && res.data && res.data.message) ? res.data.message : 'Remove failed.', 'error');
          t.disabled = false;
        })
        .catch(() => {
          setMsg('Remove failed.', 'error');
          t.disabled = false;
        });
    });
  }

  // Phase B v2: GA attendance + entitlements + Preview → Commit
  const ticketUiLayoutOverrideSel = $('vms-ticket-ui-layout-override');
  const ticketUiAvailabilityDisplayOverrideSel = $('vms-ticket-ui-availability-display-override');
  const ticketUiSaleAvailabilityDisplayOverrideSel = $('vms-ticket-ui-sale-availability-display-override');
  const ticketUiAddonsHeadingOverrideInput = $('vms_ticket_ui_addons_heading_override');
  const ticketUiAddonsSubtextOverrideInput = $('vms_ticket_ui_addons_subtext_override');
  const ticketUiHelpTicketsOverrideTextarea = $('vms_ticket_ui_help_tickets_override_editor')
    || (postForm ? postForm.querySelector('textarea[name="vms_ticket_ui_help_tickets_override"]') : null);
  const ticketUiHelpAddonsOverrideTextarea = $('vms_ticket_ui_help_addons_override_editor')
    || (postForm ? postForm.querySelector('textarea[name="vms_ticket_ui_help_addons_override"]') : null);
  const ticketUiOverridesSaveBtn = $('vms-ticket-ui-overrides-save-btn');
  const ticketUiOverridesMsgWrap = $('vms-ticket-ui-overrides-msg');
  const v2Editor = $('vms-ticketing-v2-editor');
  const v2ModeSel = $('vms-ticketing-v2-mode');
  const v2SaveBtn = $('vms-ticketing-v2-save-config-btn');
  const v2PreviewBtn = $('vms-ticketing-v2-preview-sync-btn');
  const v2CommitBtn = $('vms-ticketing-v2-commit-sync-btn');
  const v2PreviewWrap = $('vms-ticketing-v2-sync-preview');
  const v2MsgWrap = $('vms-ticketing-v2-sync-msg');
  const v2DetailsWrap = $('vms-ticketing-v2-sync-details');

  // Templates / initialization helpers
  const v2TplSel = $('vms-ticketing-v2-template-select');
  const v2TplApplyBtn = $('vms-ticketing-v2-apply-template-btn');
  const v2TplNameInp = $('vms-ticketing-v2-template-name');
  const v2TplSaveBtn = $('vms-ticketing-v2-save-template-btn');
  const v2TplClearBtn = $('vms-ticketing-v2-clear-config-btn');
  const v2InitLegacyBtn = $('vms-ticketing-v2-init-legacy-btn');
  const v2ConfigNote = $('vms-ticketing-v2-config-note');
  const v2TemplateGuardrailWrap = $('vms-ticketing-v2-template-guardrail');
  const v2SalesEndWarningWrap = $('vms-ticketing-v2-sales-end-warning');

  const v2TplSetDefaultBtn = $('vms-ticketing-v2-set-default-template-btn');
  const v2TplClearDefaultBtn = $('vms-ticketing-v2-clear-default-template-btn');
  const v2TplDefaultLabel = $('vms-ticketing-v2-default-template-label');

  // v2 Preview/Commit state (shared across handlers)
  let v2PreviewId = '';
  let v2LastPreviewBlocked = true;
  let v2ActiveDragState = null;
  let ticketUiOverridesInitialState = '';
  let ticketUiOverridesSaveInFlight = null;
  let ticketUiOverridesApplyingSavedState = false;

  function updateV2CommitEnabled(showHint) {
    if (!v2CommitBtn) return;

    const mode = v2ModeSel ? String(v2ModeSel.value || '') : '';
    const ticketingEffective = v2Editor ? (String(v2Editor.dataset.ticketingEffective || '1') === '1') : true;
    const okToCommit = ticketingEffective && !!v2PreviewId && !v2LastPreviewBlocked && (mode === 'vms_managed');

    v2CommitBtn.disabled = !okToCommit;

    if (v2ModeSel) {
      if (ticketingEffective && !!v2PreviewId && !v2LastPreviewBlocked && mode !== 'vms_managed') {
        v2ModeSel.classList.add('vms-field-attn');
        if (showHint) {
          setV2Note('Preview is ready. To create/sync tickets, set Mode to "VMS-managed", then click "Commit sync".', 'info');
        }
      } else {
        v2ModeSel.classList.remove('vms-field-attn');
      }
    }
  }
 
  if (v2ModeSel) {
    v2ModeSel.addEventListener('change', function () {
      updateV2CommitEnabled(true);
    });
  }

  function setV2Msg(text, type) {
    if (!v2MsgWrap) return;
    v2MsgWrap.textContent = text || '';
    const t = String(type || '').trim();
    const severity = t ? noticeSeverityClass(t) : '';
    v2MsgWrap.className = 'vms-ticketing__msg vms-notice'
      + (severity ? (' ' + severity) : '')
      + (t ? (' is-' + t) : '');
    try { v2MsgWrap.setAttribute('aria-live', 'polite'); } catch (e) {}
  }

  function getTicketUiOverrideTextarea(fieldName, fallbackId) {
    const byId = fallbackId ? document.getElementById(fallbackId) : null;
    if (byId) return byId;
    if (!postForm) return null;
    try {
      return postForm.querySelector(`textarea[name="${cssEscapeValue(fieldName)}"]`);
    } catch (e) {
      return null;
    }
  }

  function getTicketUiOverrideEditorValue(editorId, fieldName) {
    try {
      if (window.tinyMCE && typeof window.tinyMCE.get === 'function') {
        const editor = window.tinyMCE.get(editorId);
        if (editor && typeof editor.getContent === 'function') {
          return String(editor.getContent() || '');
        }
      }
    } catch (e) {
      // ignore
    }

    const textarea = getTicketUiOverrideTextarea(fieldName, editorId);
    return textarea ? String(textarea.value || '') : '';
  }

  function setTicketUiOverrideEditorValue(editorId, fieldName, value) {
    const normalized = String(value || '');
    const textarea = getTicketUiOverrideTextarea(fieldName, editorId);
    if (textarea) {
      textarea.value = normalized;
    }

    try {
      if (window.tinyMCE && typeof window.tinyMCE.get === 'function') {
        const editor = window.tinyMCE.get(editorId);
        if (editor && typeof editor.setContent === 'function') {
          editor.setContent(normalized);
          if (typeof editor.save === 'function') {
            editor.save();
          }
        }
      }
    } catch (e) {
      // ignore
    }
  }

  function getTicketUiOverrideState() {
    return {
      vms_ticket_ui_layout_override: ticketUiLayoutOverrideSel ? String(ticketUiLayoutOverrideSel.value || '') : '',
      vms_ticket_ui_availability_display_override: ticketUiAvailabilityDisplayOverrideSel ? String(ticketUiAvailabilityDisplayOverrideSel.value || '') : '',
      vms_ticket_ui_sale_availability_display_override: ticketUiSaleAvailabilityDisplayOverrideSel ? String(ticketUiSaleAvailabilityDisplayOverrideSel.value || '') : '',
      vms_ticket_ui_addons_heading_override: ticketUiAddonsHeadingOverrideInput ? String(ticketUiAddonsHeadingOverrideInput.value || '') : '',
      vms_ticket_ui_addons_subtext_override: ticketUiAddonsSubtextOverrideInput ? String(ticketUiAddonsSubtextOverrideInput.value || '') : '',
      vms_ticket_ui_help_tickets_override: getTicketUiOverrideEditorValue('vms_ticket_ui_help_tickets_override_editor', 'vms_ticket_ui_help_tickets_override'),
      vms_ticket_ui_help_addons_override: getTicketUiOverrideEditorValue('vms_ticket_ui_help_addons_override_editor', 'vms_ticket_ui_help_addons_override'),
    };
  }

  function setTicketUiOverridesMsg(text, type) {
    if (!ticketUiOverridesMsgWrap) return;
    const message = String(text || '').trim();
    const t = String(type || '').trim();
    if (!message) {
      ticketUiOverridesMsgWrap.textContent = '';
      ticketUiOverridesMsgWrap.className = 'vms-ticketing__msg vms-notice vms-hidden';
      return;
    }
    const severity = t ? noticeSeverityClass(t) : '';
    ticketUiOverridesMsgWrap.textContent = message;
    ticketUiOverridesMsgWrap.className = 'vms-ticketing__msg vms-notice'
      + (severity ? (' ' + severity) : '')
      + (t ? (' is-' + t) : '');
    try { ticketUiOverridesMsgWrap.setAttribute('aria-live', 'polite'); } catch (e) {}
  }

  function focusTicketUiOverridesSaveControl() {
    if (!ticketUiOverridesSaveBtn) return;
    try {
      ticketUiOverridesSaveBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } catch (e) {
      try {
        ticketUiOverridesSaveBtn.scrollIntoView();
      } catch (err) {
        // ignore
      }
    }
    try {
      ticketUiOverridesSaveBtn.focus({ preventScroll: true });
    } catch (e) {
      try {
        ticketUiOverridesSaveBtn.focus();
      } catch (err) {
        // ignore
      }
    }
  }

  function getTicketUiOverrideStateToken() {
    return stableStringify(getTicketUiOverrideState());
  }

  function isTicketUiOverridesDirty() {
    if (!ticketUiOverridesSaveBtn
      && !ticketUiLayoutOverrideSel
      && !ticketUiAvailabilityDisplayOverrideSel
      && !ticketUiSaleAvailabilityDisplayOverrideSel
      && !ticketUiAddonsHeadingOverrideInput
      && !ticketUiAddonsSubtextOverrideInput
      && !ticketUiHelpTicketsOverrideTextarea
      && !ticketUiHelpAddonsOverrideTextarea) {
      return false;
    }

    return ticketUiOverridesInitialState !== getTicketUiOverrideStateToken();
  }

  function updateTicketUiOverridesDirtyState() {
    const dirty = isTicketUiOverridesDirty();
    if (ticketUiOverridesSaveBtn) {
      ticketUiOverridesSaveBtn.disabled = !dirty || !!ticketUiOverridesSaveInFlight || !(getPlanId() > 0);
    }
    if (dirty && !ticketUiOverridesApplyingSavedState && ticketUiOverridesMsgWrap) {
      const className = String(ticketUiOverridesMsgWrap.className || '');
      if (className.indexOf('is-success') !== -1) {
        setTicketUiOverridesMsg('', '');
      }
    }
    return dirty;
  }

  function applySavedTicketUiOverrideValues(values) {
    const next = (values && typeof values === 'object') ? values : {};
    ticketUiOverridesApplyingSavedState = true;

    if (ticketUiLayoutOverrideSel && Object.prototype.hasOwnProperty.call(next, 'vms_ticket_ui_layout_override')) {
      ticketUiLayoutOverrideSel.value = String(next.vms_ticket_ui_layout_override || '');
    }
    if (ticketUiAvailabilityDisplayOverrideSel && Object.prototype.hasOwnProperty.call(next, 'vms_ticket_ui_availability_display_override')) {
      ticketUiAvailabilityDisplayOverrideSel.value = String(next.vms_ticket_ui_availability_display_override || '');
    }
    if (ticketUiSaleAvailabilityDisplayOverrideSel && Object.prototype.hasOwnProperty.call(next, 'vms_ticket_ui_sale_availability_display_override')) {
      ticketUiSaleAvailabilityDisplayOverrideSel.value = String(next.vms_ticket_ui_sale_availability_display_override || '');
    }
    if (ticketUiAddonsHeadingOverrideInput && Object.prototype.hasOwnProperty.call(next, 'vms_ticket_ui_addons_heading_override')) {
      ticketUiAddonsHeadingOverrideInput.value = String(next.vms_ticket_ui_addons_heading_override || '');
    }
    if (ticketUiAddonsSubtextOverrideInput && Object.prototype.hasOwnProperty.call(next, 'vms_ticket_ui_addons_subtext_override')) {
      ticketUiAddonsSubtextOverrideInput.value = String(next.vms_ticket_ui_addons_subtext_override || '');
    }
    if (Object.prototype.hasOwnProperty.call(next, 'vms_ticket_ui_help_tickets_override')) {
      setTicketUiOverrideEditorValue(
        'vms_ticket_ui_help_tickets_override_editor',
        'vms_ticket_ui_help_tickets_override',
        next.vms_ticket_ui_help_tickets_override
      );
    }
    if (Object.prototype.hasOwnProperty.call(next, 'vms_ticket_ui_help_addons_override')) {
      setTicketUiOverrideEditorValue(
        'vms_ticket_ui_help_addons_override_editor',
        'vms_ticket_ui_help_addons_override',
        next.vms_ticket_ui_help_addons_override
      );
    }

    ticketUiOverridesInitialState = getTicketUiOverrideStateToken();
    ticketUiOverridesApplyingSavedState = false;
    updateTicketUiOverridesDirtyState();
  }

  function bindTicketUiOverrideEditorWatcher(editorId) {
    const attach = function (editor) {
      if (!editor || editor._vmsTicketUiOverrideWatcherBound) return;
      editor._vmsTicketUiOverrideWatcherBound = true;
      editor.on('change input keyup undo redo SetContent', function () {
        try {
          if (typeof editor.save === 'function') {
            editor.save();
          }
        } catch (e) {
          // ignore
        }
        if (!ticketUiOverridesApplyingSavedState) {
          updateTicketUiOverridesDirtyState();
        }
      });
    };

    try {
      if (window.tinymce && typeof window.tinymce.get === 'function') {
        const existingEditor = window.tinymce.get(editorId);
        if (existingEditor) {
          attach(existingEditor);
        }
      }
      if (window.tinymce && typeof window.tinymce.on === 'function') {
        window.tinymce.on('AddEditor', function (event) {
          if (!event || !event.editor || event.editor.id !== editorId) return;
          attach(event.editor);
        });
      }
    } catch (e) {
      // ignore
    }
  }

  function bindTicketUiOverrideDirtyTracking() {
    const inputs = [
      ticketUiLayoutOverrideSel,
      ticketUiAvailabilityDisplayOverrideSel,
      ticketUiSaleAvailabilityDisplayOverrideSel,
      ticketUiAddonsHeadingOverrideInput,
      ticketUiAddonsSubtextOverrideInput,
      ticketUiHelpTicketsOverrideTextarea,
      ticketUiHelpAddonsOverrideTextarea,
    ];

    inputs.forEach((input) => {
      if (!input) return;
      input.addEventListener('input', function () {
        if (!ticketUiOverridesApplyingSavedState) {
          updateTicketUiOverridesDirtyState();
        }
      });
      input.addEventListener('change', function () {
        if (!ticketUiOverridesApplyingSavedState) {
          updateTicketUiOverridesDirtyState();
        }
      });
    });

    bindTicketUiOverrideEditorWatcher('vms_ticket_ui_help_tickets_override_editor');
    bindTicketUiOverrideEditorWatcher('vms_ticket_ui_help_addons_override_editor');

    ticketUiOverridesInitialState = getTicketUiOverrideStateToken();
    updateTicketUiOverridesDirtyState();
  }

  function resolvePostFormSubmitter(event) {
    if (event && event.submitter) {
      return event.submitter;
    }
    if (lastPostFormSubmitter) {
      return lastPostFormSubmitter;
    }
    try {
      const active = document.activeElement;
      if (active && postForm && postForm.contains(active)) {
        return active;
      }
    } catch (e) {
      // ignore
    }
    return null;
  }

  function isTicketUiOverrideSaveSubmitter(node) {
    return !!(node && ticketUiOverridesSaveBtn && node === ticketUiOverridesSaveBtn);
  }

  function bindTicketUiOverrideMainFormGuard() {
    if (!postForm) return;

    postForm.addEventListener('click', function (event) {
      const target = event && event.target ? event.target.closest('button, input[type="submit"]') : null;
      if (!target || !postForm.contains(target)) return;
      lastPostFormSubmitter = target;
    }, true);

    postForm.addEventListener('submit', function (event) {
      const submitter = resolvePostFormSubmitter(event);
      if (!isTicketUiOverridesDirty()) {
        lastPostFormSubmitter = null;
        return;
      }
      if (isTicketUiOverrideSaveSubmitter(submitter)) {
        lastPostFormSubmitter = null;
        return;
      }

      if (event) {
        event.preventDefault();
        event.stopPropagation();
      }
      setTicketUiOverridesMsg('Public ticket UI overrides have unsaved changes. Use Save public UI overrides before updating the Event Plan.', 'error');
      focusTicketUiOverridesSaveControl();
      lastPostFormSubmitter = null;
    }, true);
  }

  function saveTicketUiOverrides(opts) {
    const o = opts || {};
    const currentPlanId = getPlanId();
    if (!(currentPlanId > 0)) {
      const missingPlanMessage = 'Save Draft first, then save public UI overrides.';
      if (!o.quiet) {
        setTicketUiOverridesMsg(missingPlanMessage, 'error');
      }
      return Promise.reject(new Error('missing_plan_id'));
    }
    if (!ticketUiOverridesNonce) {
      if (!o.quiet) {
        setTicketUiOverridesMsg('Public UI override save is unavailable on this page load. Reload and try again.', 'error');
      }
      return Promise.reject(new Error('missing_nonce'));
    }
    if (ticketUiOverridesSaveInFlight) {
      return ticketUiOverridesSaveInFlight;
    }

    const payload = Object.assign({
      post_id: currentPlanId,
      vms_ticket_ui_overrides_save_intent: '1',
    }, getTicketUiOverrideState());
    const timeoutMs = parseInt((o && o.timeoutMs) || 0, 10) || 45000;
    if (!o.quiet) {
      setTicketUiOverridesMsg(String(o.workingMsg || 'Saving public UI overrides…'), 'working');
    }
    updateTicketUiOverridesDirtyState();

    ticketUiOverridesSaveInFlight = postJSONWithNonce(
      'vms_save_event_plan_ticket_ui_overrides',
      payload,
      ticketUiOverridesNonce,
      timeoutMs
    ).then((res) => {
      if (!res || !res.success) {
        throw res;
      }
      persistRequestedSectionTarget('ticketing_v2');
      applySavedTicketUiOverrideValues((res.data && res.data.values) ? res.data.values : payload);
      if (!o.quiet) {
        const successMsg = (o.successMsg && String(o.successMsg).trim())
          ? String(o.successMsg).trim()
          : 'Public UI overrides saved.';
        setTicketUiOverridesMsg(successMsg, 'success');
      }
      return (res.data && res.data.values) ? res.data.values : payload;
    }).catch((err) => {
      const failure = getAjaxFailurePayload(err, 'Public UI override save failed.');
      if (!o.quiet) {
        setTicketUiOverridesMsg(
          failure.summary || humanizeV2Message(failure.message) || 'Public UI override save failed.',
          'error'
        );
      }
      throw err;
    }).finally(() => {
      ticketUiOverridesSaveInFlight = null;
      updateTicketUiOverridesDirtyState();
    });

    return ticketUiOverridesSaveInFlight;
  }

  async function saveTicketUiOverridesIfDirty(opts) {
    if (!isTicketUiOverridesDirty()) {
      updateTicketUiOverridesDirtyState();
      return false;
    }

    await saveTicketUiOverrides(opts || {});
    return true;
  }

  async function ensureTicketUiOverridesReadyForAction(actionLabel) {
    const label = String(actionLabel || 'this action').trim() || 'this action';
    try {
      await saveTicketUiOverridesIfDirty({
        workingMsg: 'Saving public UI overrides…',
        successMsg: 'Public UI overrides saved.',
      });
      return true;
    } catch (e) {
      setV2Msg('Could not save public UI overrides. Save them first, then retry ' + label + '.', 'error');
      return false;
    }
  }

  function clearV2Details() {
    if (!v2DetailsWrap) return;
    v2DetailsWrap.innerHTML = '';
    v2DetailsWrap.classList.add('vms-hidden');
    v2DetailsWrap.style.display = 'none';
  }

  function getAjaxFailurePayload(res, fallbackMessage) {
    const root = (res && typeof res === 'object') ? res : {};
    const data = (root.data && typeof root.data === 'object') ? root.data : {};
    const message = String(data.message || root.message || fallbackMessage || 'error');
    const raw = String(data._vms_ajax_noise || data.raw || root.raw || '');
    const diagnostics = (data.diagnostics && typeof data.diagnostics === 'object') ? data.diagnostics : ((root.diagnostics && typeof root.diagnostics === 'object') ? root.diagnostics : null);
    const summary = String(data.error_summary || root.error_summary || '');
    const code = String(data.error_code || root.error_code || message || 'error');
    return {
      message,
      raw,
      diagnostics,
      summary,
      code,
    };
  }

  function appendDetailList(parent, items) {
    if (!parent || !Array.isArray(items) || !items.length) return;
    const filtered = items.filter(Boolean);
    if (!filtered.length) return;
    const ul = document.createElement('ul');
    ul.className = 'vms-ticketing__failure-list';
    filtered.forEach((item) => {
      const li = document.createElement('li');
      li.textContent = String(item);
      ul.appendChild(li);
    });
    parent.appendChild(ul);
  }

  function appendKeyValueRows(parent, rows) {
    if (!parent || !Array.isArray(rows) || !rows.length) return;
    const filtered = rows.filter((row) => row && row.label);
    if (!filtered.length) return;
    const dl = document.createElement('dl');
    dl.className = 'vms-ticketing__failure-grid';
    filtered.forEach((row) => {
      const dt = document.createElement('dt');
      dt.textContent = String(row.label);
      const dd = document.createElement('dd');
      dd.textContent = String(row.value || '');
      dl.appendChild(dt);
      dl.appendChild(dd);
    });
    parent.appendChild(dl);
  }

  function renderV2FailureDetails(payload) {
    if (!v2DetailsWrap) return;
    clearV2Details();

    const info = payload && typeof payload === 'object' ? payload : {};
    const diagnostics = (info.diagnostics && typeof info.diagnostics === 'object') ? info.diagnostics : {};
    const summaryText = String(info.summary || diagnostics.summary || '');
    const code = String(info.code || diagnostics.error_code || info.message || 'error');
    const stage = String(diagnostics.stage || '');
    const raw = String(info.raw || diagnostics.raw || '');

    const panel = document.createElement('details');
    panel.className = 'vms-ticketing__failure-panel';

    const summary = document.createElement('summary');
    summary.className = 'vms-ticketing__failure-summary';
    summary.innerHTML = '<strong>Why this failed</strong>' + (stage ? (' <span class="description">(' + escHtml(stage) + ')</span>') : '');
    panel.appendChild(summary);

    const body = document.createElement('div');
    body.className = 'vms-ticketing__failure-body';

    if (summaryText) {
      const p = document.createElement('p');
      p.className = 'vms-ticketing__failure-copy';
      p.textContent = summaryText;
      body.appendChild(p);
    }

    appendKeyValueRows(body, [
      code ? { label: 'Failure code', value: code } : null,
      stage ? { label: 'Stage', value: stage } : null,
      diagnostics.plan_id ? { label: 'Event Plan', value: '#' + String(diagnostics.plan_id) } : null,
      diagnostics.requested_preview_id ? { label: 'Preview ID', value: String(diagnostics.requested_preview_id) } : null,
      diagnostics.linked_tec_event_id ? { label: 'Linked TEC event', value: '#' + String(diagnostics.linked_tec_event_id) + (diagnostics.linked_tec_event_title ? (' — ' + String(diagnostics.linked_tec_event_title)) : '') } : null,
      diagnostics.current_mode ? { label: 'Current mode', value: String(diagnostics.current_mode) } : null,
      diagnostics.preview_mode ? { label: 'Preview mode', value: String(diagnostics.preview_mode) } : null,
      (typeof diagnostics.preview_age_seconds === 'number' && diagnostics.preview_age_seconds > 0) ? { label: 'Preview age', value: String(diagnostics.preview_age_seconds) + ' seconds' } : null,
      Array.isArray(diagnostics.existing_ticket_product_ids) && diagnostics.existing_ticket_product_ids.length ? { label: 'Event ticket products', value: diagnostics.existing_ticket_product_ids.map((id) => '#' + String(id)).join(', ') } : null,
      Array.isArray(diagnostics.sync_map_ticket_product_ids) && diagnostics.sync_map_ticket_product_ids.length ? { label: 'Tracked in VMS sync map', value: diagnostics.sync_map_ticket_product_ids.map((id) => '#' + String(id)).join(', ') } : null,
      Array.isArray(diagnostics.untracked_event_ticket_product_ids) && diagnostics.untracked_event_ticket_product_ids.length ? { label: 'Untracked linked-event products', value: diagnostics.untracked_event_ticket_product_ids.map((id) => '#' + String(id)).join(', ') } : null,
    ]);

    if (Array.isArray(diagnostics.preview_warnings) && diagnostics.preview_warnings.length) {
      const section = document.createElement('div');
      section.className = 'vms-ticketing__failure-section';
      const heading = document.createElement('h4');
      heading.textContent = 'Preview warnings';
      section.appendChild(heading);
      appendDetailList(section, diagnostics.preview_warnings);
      body.appendChild(section);
    }

    if (Array.isArray(diagnostics.verified_ticket_rule_issues) && diagnostics.verified_ticket_rule_issues.length) {
      const section = document.createElement('div');
      section.className = 'vms-ticketing__failure-section';
      const heading = document.createElement('h4');
      heading.textContent = 'Qualified ticket rule issues';
      section.appendChild(heading);
      appendDetailList(section, diagnostics.verified_ticket_rule_issues);
      body.appendChild(section);
    }

    if (Array.isArray(diagnostics.suggested_next_steps) && diagnostics.suggested_next_steps.length) {
      const section = document.createElement('div');
      section.className = 'vms-ticketing__failure-section';
      const heading = document.createElement('h4');
      heading.textContent = 'What to do next';
      section.appendChild(heading);
      appendDetailList(section, diagnostics.suggested_next_steps);
      body.appendChild(section);
    }

    if (raw) {
      const section = document.createElement('div');
      section.className = 'vms-ticketing__failure-section';
      const heading = document.createElement('h4');
      heading.textContent = 'Raw response';
      section.appendChild(heading);
      const pre = document.createElement('pre');
      pre.className = 'vms-ticketing__failure-raw';
      pre.textContent = raw;
      section.appendChild(pre);
      body.appendChild(section);
    }

    panel.appendChild(body);
    v2DetailsWrap.appendChild(panel);
    v2DetailsWrap.classList.remove('vms-hidden');
    v2DetailsWrap.style.display = 'block';
  }

  function clearV2PreviewState() {
    v2PreviewId = '';
    v2LastPreviewBlocked = true;
    if (v2PreviewWrap) {
      v2PreviewWrap.classList.add('vms-hidden');
      v2PreviewWrap.style.display = 'none';
    }
    clearV2Details();
    updateV2CommitEnabled(false);
  }

  function canonicalizeV2ConfigForDirtyCheck(rawCfg) {
    const src = (rawCfg && typeof rawCfg === 'object') ? rawCfg : {};
    const out = {
      version: 2,
      mode: String(src.mode || 'read_only'),
      provider: 'tec_tickets_woo',
      tickets: [],
      ga: {
        enabled: true,
        label: 'GA Admission',
        price: '0',
        early_price: '',
        early_price_start: '',
        early_price_end: '',
        early_price_start_relative_days: '',
        early_price_end_relative_days: '',
        early_price_cap: 0,
        capacity: 0,
        sales_start: '',
        sales_end: '',
        sales_start_relative_days: '',
        sales_end_relative_days: '0',
      },
      entitlements: [],
      square: { ga: { mode: 'none', item_id: '', variation_id: '' } },
    };

    const tickets = Array.isArray(src.tickets) ? src.tickets : [];
    tickets.forEach((ticket, idx) => {
      if (!ticket || typeof ticket !== 'object') return;
      out.tickets.push({
        enabled: Object.prototype.hasOwnProperty.call(ticket, 'enabled') ? !!ticket.enabled : true,
        ticket_key: String(ticket.ticket_key || ticket.key || ('ticket_' + String(idx + 1))),
        title: plainTextValue(ticket.title || ticket.label || ''),
        description: plainTextValue(ticket.description || ''),
        price: safeNumberString(ticket.price !== undefined ? ticket.price : '0'),
        early_price: ticket.early_price !== undefined && String(ticket.early_price || '').trim() !== '' ? safeNumberString(ticket.early_price) : '',
        early_price_start: String(ticket.early_price_start || '').trim(),
        early_price_end: String(ticket.early_price_end || '').trim(),
        early_price_start_relative_days: normalizeRelativeDaysValue(ticket.early_price_start_relative_days),
        early_price_end_relative_days: normalizeRelativeDaysValue(ticket.early_price_end_relative_days),
        early_price_cap: Math.max(0, safeInt(ticket.early_price_cap || ticket.early_price_limit || 0)),
        inventory_total: Math.max(0, safeInt(ticket.inventory_total !== undefined ? ticket.inventory_total : (ticket.capacity || 0))),
        visibility_mode: String(ticket.visibility_mode || 'public').trim() || 'public',
        verified_program: String(ticket.verified_program || '').trim(),
        counts_toward_unlock: Object.prototype.hasOwnProperty.call(ticket, 'counts_toward_unlock') ? !!ticket.counts_toward_unlock : true,
        max_qty_per_order: Math.max(0, safeInt(ticket.max_qty_per_order || 0)),
        ratio_rule_enabled: !!ticket.ratio_rule_enabled && Math.max(0, safeInt(ticket.ratio_rule_max_per_qualifying || 0)) > 0,
        ratio_rule_max_per_qualifying: (!!ticket.ratio_rule_enabled && Math.max(0, safeInt(ticket.ratio_rule_max_per_qualifying || 0)) > 0) ? Math.max(0, safeInt(ticket.ratio_rule_max_per_qualifying || 0)) : 0,
        ratio_rule_qualifier_mode: 'counts_toward_unlock',
        ratio_rule_group: (!!ticket.ratio_rule_enabled && Math.max(0, safeInt(ticket.ratio_rule_max_per_qualifying || 0)) > 0) ? String(ticket.ratio_rule_group || '').trim().toLowerCase().replace(/[^a-z0-9_-]+/g, '-') : '',
        sort_order: Math.max(1, safeInt(ticket.sort_order || ((idx + 1) * 10))),
        sales_start: String(ticket.sales_start || '').trim(),
        sales_end: String(ticket.sales_end || '').trim(),
        sales_start_relative_days: normalizeRelativeDaysValue(ticket.sales_start_relative_days),
        sales_end_relative_days: normalizeRelativeDaysValue(ticket.sales_end_relative_days),
        image_mode: normalizeTicketImageMode(ticket.image_mode || 'event_featured'),
        image_id: normalizeTicketImageMode(ticket.image_mode || 'event_featured') === 'custom'
          ? Math.max(0, safeInt(ticket.image_id || 0))
          : 0,
      });
    });

    if (!out.tickets.length) {
      out.tickets.push({
        enabled: true,
        ticket_key: 'ga',
        title: 'GA Admission',
        description: '',
        price: '0',
        early_price: '',
        early_price_start: '',
        early_price_end: '',
        early_price_start_relative_days: '',
        early_price_end_relative_days: '',
        early_price_cap: 0,
        inventory_total: 0,
        visibility_mode: 'public',
        verified_program: '',
        counts_toward_unlock: true,
        max_qty_per_order: 0,
        ratio_rule_enabled: false,
        ratio_rule_max_per_qualifying: 0,
        ratio_rule_qualifier_mode: 'counts_toward_unlock',
        ratio_rule_group: '',
        sort_order: 10,
        sales_start: '',
        sales_end: '',
        sales_start_relative_days: '',
        sales_end_relative_days: '0',
        image_mode: 'event_featured',
        image_id: 0,
      });
    }

    let primaryTicket = out.tickets[0];
    out.tickets.forEach((ticket) => {
      if (ticket && ticket.enabled && ticket.counts_toward_unlock) {
        primaryTicket = ticket;
      }
    });

    out.ga = {
      enabled: !!(primaryTicket && primaryTicket.enabled),
      label: String((primaryTicket && primaryTicket.title) || 'GA Admission'),
      price: String((primaryTicket && primaryTicket.price) || '0'),
      early_price: String((primaryTicket && primaryTicket.early_price) || ''),
      early_price_start: String((primaryTicket && primaryTicket.early_price_start) || ''),
      early_price_end: String((primaryTicket && primaryTicket.early_price_end) || ''),
      early_price_start_relative_days: normalizeRelativeDaysValue(primaryTicket && primaryTicket.early_price_start_relative_days),
      early_price_end_relative_days: normalizeRelativeDaysValue(primaryTicket && primaryTicket.early_price_end_relative_days),
      early_price_cap: String((primaryTicket && primaryTicket.early_price_cap) || '0'),
      capacity: Math.max(0, safeInt((primaryTicket && primaryTicket.inventory_total) || 0)),
      sales_start: String((primaryTicket && primaryTicket.sales_start) || ''),
      sales_end: String((primaryTicket && primaryTicket.sales_end) || ''),
      sales_start_relative_days: normalizeRelativeDaysValue(primaryTicket && primaryTicket.sales_start_relative_days),
      sales_end_relative_days: normalizeRelativeDaysValue(primaryTicket && primaryTicket.sales_end_relative_days),
    };

    const entitlements = Array.isArray(src.entitlements) ? src.entitlements : [];
    entitlements.forEach((entitlement) => {
      if (!entitlement || typeof entitlement !== 'object') return;
      const eligibility = (entitlement.eligibility && typeof entitlement.eligibility === 'object') ? entitlement.eligibility : {};
      const poolMaxTotal = Math.max(0, safeInt(eligibility.pool_max_total || 0));
      out.entitlements.push({
        entitlement_id: String(entitlement.entitlement_id || '').trim(),
        entitlement_key: String(entitlement.entitlement_key || '').trim(),
        enabled: Object.prototype.hasOwnProperty.call(entitlement, 'enabled') ? !!entitlement.enabled : true,
        label: plainTextValue(entitlement.label || ''),
        short_desc: plainTextValue(entitlement.short_desc || ''),
        more_info: String(entitlement.more_info || '').trim(),
        image_id: Math.max(0, safeInt(entitlement.image_id || 0)),
        price: safeNumberString(entitlement.price !== undefined ? entitlement.price : '0'),
        capacity: Math.max(0, safeInt(entitlement.capacity || 0)),
        selector_mode: String(entitlement.selector_mode || 'stepper').trim() === 'checkbox' ? 'checkbox' : 'stepper',
        eligibility: {
          min_ga_per_unit: Math.max(0, safeInt(eligibility.min_ga_per_unit || 0)),
          pool_key: toPoolKey(eligibility.pool_key || ''),
          pool_max_total: poolMaxTotal,
          pool_max_explicit: poolMaxTotal > 0 ? 1 : 0,
          max_units_per_order: 0,
          max_units_per_ga: 0,
          allow_without_ga: !!eligibility.allow_without_ga,
        },
        square: { mode: 'none', item_id: '', variation_id: '' },
      });
    });

    return out;
  }

  function isV2Dirty() {
    if (!v2Editor) return false;
    const init = String(v2Editor.dataset.initialConfig || '').trim();
    if (!init) return false;
    const currentCfg = getV2ConfigFromUI();
    if (!currentCfg) return false;
    let initialObj = null;
    try { initialObj = JSON.parse(init); } catch (e) { initialObj = null; }
    if (!initialObj) return false;
    return stableStringify(canonicalizeV2ConfigForDirtyCheck(initialObj)) !== stableStringify(canonicalizeV2ConfigForDirtyCheck(currentCfg));
  }

  function saveV2Config(opts) {
    const o = opts || {};
    const quiet = !!o.quiet;
    const cfg = getV2ConfigFromUI();
    if (!cfg) {
      if (!quiet) setV2Msg('Could not read config from the page.', 'error');
      return Promise.resolve(false);
    }
    const saveTimeoutMs = parseInt((o && o.timeoutMs) || 0, 10) || 60000;
    return postJSON('vms_ticketing_v2_save_config', { plan_id: planId, config: cfg, return_config: 0 }, saveTimeoutMs)
      .then((res) => {
        if (!res || !res.success) {
          if (!quiet) setV2Msg(humanizeV2Message((res && res.data && res.data.message) ? res.data.message : 'Save failed.'), 'error');
          return false;
        }
        const normalized = res.data && res.data.config ? res.data.config : cfg;
        v2Editor.dataset.initialConfig = JSON.stringify(normalized);
        v2Editor.dataset.configExists = '1';
        if (res.data && res.data.config) {
          renderV2(normalized);
        }
        clearV2PreviewState();
        setV2Note('Config is saved. Preview is read-only; Commit creates or updates the calendar event, tickets, and add-ons.', 'info');
        if (!quiet) setV2Msg('Config saved.', 'success');
        return true;
      })
      .catch(() => {
        if (!quiet) setV2Msg('Save failed.', 'error');
        return false;
      });
  }

  function setV2Note(text, type) {
    if (!v2ConfigNote) return;
    const t = String(text || '').trim();
    v2ConfigNote.textContent = t;
    v2ConfigNote.style.display = t ? 'block' : 'none';
    const noteType = String(type || 'info').trim();
    const severity = noticeSeverityClass(noteType);
    v2ConfigNote.className = 'vms-ticketing__msg vms-notice vms-ticketing__msg--info'
      + (severity ? (' ' + severity) : '')
      + (noteType ? (' is-' + noteType) : '');
    try { v2ConfigNote.setAttribute('aria-live', 'polite'); } catch (e) {}
  }

  function stripDefaultSuffix(label) {
    return String(label || '').replace(/\s*\(Default\)\s*$/, '');
  }

  function markDefaultTemplateInSelect(defaultId) {
    if (!v2TplSel) return;
    const did = String(defaultId || '').trim();
    Array.from(v2TplSel.options || []).forEach((opt) => {
      if (!opt) return;
      const base = stripDefaultSuffix(opt.textContent || '');
      opt.textContent = (did && String(opt.value || '') === did) ? (base + ' (Default)') : base;
    });
  }

  function setDefaultTemplateLabel(defaultId, defaultName) {
    if (!v2TplDefaultLabel) return;
    const name = String(defaultName || '').trim();
    v2TplDefaultLabel.textContent = name ? ('Default: ' + name) : 'Default: none';
  }

  function setGuardrailVisibility(node, visible) {
    if (!node) return;
    if (visible) {
      node.classList.remove('vms-hidden');
      node.style.display = 'block';
    } else {
      node.classList.add('vms-hidden');
      node.style.display = 'none';
    }
  }

  function clearGuardrail(node) {
    if (!node) return;
    node.innerHTML = '';
    setGuardrailVisibility(node, false);
  }

  function formatGuardrailDatetime(localValue) {
    const s = String(localValue || '').trim();
    return s ? s.replace('T', ' ') : '';
  }

  function comparableLocalDatetime(rawValue) {
    const local = toDatetimeLocal(rawValue);
    return local ? local.slice(0, 16) : '';
  }

  function getCurrentShowComparableDatetime() {
    const local = getEventShowDatetimeValue();
    return local ? local.slice(0, 16) : '';
  }

  function getCurrentEventEndComparableDatetime() {
    const local = getEventEndDatetimeValue() || getEventShowDatetimeValue();
    return local ? local.slice(0, 16) : '';
  }

  function getCurrentSalesEndTargetComparableDatetime() {
    return getCurrentEventEndComparableDatetime() || getCurrentShowComparableDatetime();
  }

  function getCurrentShowDatetimeForServer() {
    const local = getEventEndDatetimeValue() || getEventShowDatetimeValue();
    return local ? fromDatetimeLocal(local) : '';
  }

  function describeSalesEndIssue(issue, analysis) {
    const target = (analysis && (analysis.eventEndComparable || analysis.targetComparable || analysis.showComparable)) || '';
    if (issue && issue.kind === 'after_end') {
      return issue.title + ': Sales end ' + formatGuardrailDatetime(issue.sales_end) + ' is after the event end ' + formatGuardrailDatetime(target) + '.';
    }
    return issue.title + ': Sales end ' + formatGuardrailDatetime(issue.sales_end) + ' is before the event starts ' + formatGuardrailDatetime((analysis && analysis.showComparable) || '') + '.';
  }

  function getTemplateOptionById(templateId) {
    if (!v2TplSel) return null;
    const wanted = String(templateId || '').trim();
    return Array.from(v2TplSel.options || []).find((opt) => String(opt.value || '').trim() === wanted) || null;
  }

  function getTemplateNameById(templateId) {
    const opt = getTemplateOptionById(templateId);
    return opt ? stripDefaultSuffix(opt.textContent || '').trim() : String(templateId || '').trim();
  }

  function getTemplateGuardrailSummary(templateId) {
    const opt = getTemplateOptionById(templateId);
    if (!opt || !opt.dataset) return { ticket_count: 0, tickets: [] };
    return parseJSONAttr(opt.dataset.salesEndGuardrail || '', { ticket_count: 0, tickets: [] });
  }

  function getTemplateSalesEndIssues(templateId) {
    const showComparable = getCurrentShowComparableDatetime();
    const eventEndComparable = getCurrentEventEndComparableDatetime();
    const targetComparable = getCurrentSalesEndTargetComparableDatetime();
    const summary = getTemplateGuardrailSummary(templateId);
    const tickets = (summary && Array.isArray(summary.tickets)) ? summary.tickets : [];
    const issues = [];

    if (!showComparable && !eventEndComparable) {
      return { showComparable: '', eventEndComparable: '', targetComparable: '', issues };
    }

    tickets.forEach((ticket, idx) => {
      const salesEndComparable = comparableLocalDatetime(String((ticket && ticket.sales_end) || ''));
      if (!salesEndComparable) return;
      let kind = '';
      if (showComparable && salesEndComparable < showComparable) {
        kind = 'before_start';
      } else if (eventEndComparable && salesEndComparable > eventEndComparable) {
        kind = 'after_end';
      }
      if (!kind) return;
      issues.push({
        ticket_key: String((ticket && ticket.ticket_key) || ('ticket_' + String(idx + 1))).trim(),
        title: String((ticket && ticket.title) || 'Ticket').trim() || 'Ticket',
        sales_end: salesEndComparable,
        kind,
      });
    });

    return { showComparable, eventEndComparable, targetComparable, issues };
  }

  function getCurrentSalesEndIssues() {
    const showComparable = getCurrentShowComparableDatetime();
    const eventEndComparable = getCurrentEventEndComparableDatetime();
    const targetComparable = getCurrentSalesEndTargetComparableDatetime();
    const issues = [];
    if (!v2Editor || (!showComparable && !eventEndComparable)) {
      return { showComparable: '', eventEndComparable: '', targetComparable: '', issues };
    }

    const rows = v2Editor.querySelectorAll('.vms-ticketing-v2-ticket-row');
    rows.forEach((row, idx) => {
      const salesEndInput = row.querySelector('.vms-ticketing-v2-ticket-sales-end');
      const salesEndComparable = comparableLocalDatetime(String((salesEndInput && salesEndInput.value) || ''));
      if (!salesEndComparable) return;
      let kind = '';
      if (showComparable && salesEndComparable < showComparable) {
        kind = 'before_start';
      } else if (eventEndComparable && salesEndComparable > eventEndComparable) {
        kind = 'after_end';
      }
      if (!kind) return;
      const title = String(row.querySelector('.vms-ticketing-v2-ticket-title')?.value || '').trim() || ('Ticket ' + String(idx + 1));
      issues.push({
        row,
        title,
        sales_end: salesEndComparable,
        kind,
      });
    });

    return { showComparable, eventEndComparable, targetComparable, issues };
  }

  function renderCurrentSalesEndWarning() {
    if (!v2SalesEndWarningWrap) return;
    clearGuardrail(v2SalesEndWarningWrap);

    const analysis = getCurrentSalesEndIssues();
    if (!analysis.targetComparable || !analysis.issues.length) {
      return;
    }

    const title = document.createElement('p');
    title.className = 'vms-ticketing__guardrail-title';
    title.textContent = 'Sales end warning';

    const hasAfterEnd = analysis.issues.some((issue) => issue && issue.kind === 'after_end');
    const copy = document.createElement('p');
    copy.className = 'vms-ticketing__guardrail-copy';
    copy.textContent = hasAfterEnd
      ? 'One or more tickets currently stop selling after this Event Plan ends. Ticket sales cannot remain open past the event end and will be reset/clamped before sync.'
      : 'One or more tickets currently stop selling before this Event Plan starts. That may be intentional, but it often means a saved template carried an older show date forward.';

    const list = document.createElement('ul');
    list.className = 'vms-ticketing__guardrail-list';
    analysis.issues.forEach((issue) => {
      const item = document.createElement('li');
      item.textContent = describeSalesEndIssue(issue, analysis);
      list.appendChild(item);
    });

    const actions = document.createElement('div');
    actions.className = 'vms-ticketing__guardrail-actions';

    const resetBtn = document.createElement('button');
    resetBtn.type = 'button';
    resetBtn.className = 'button button-secondary';
    resetBtn.textContent = 'Reset Sales end to event end';
    resetBtn.addEventListener('click', function () {
      analysis.issues.forEach((issue) => {
        const input = issue && issue.row ? issue.row.querySelector('.vms-ticketing-v2-ticket-sales-end') : null;
        if (input) {
          input.value = analysis.targetComparable;
        }
      });
      renderCurrentSalesEndWarning();
      setV2Msg('Sales end values were reset to this event end. Save config when you are ready.', 'success');
    });

    actions.appendChild(resetBtn);
    v2SalesEndWarningWrap.appendChild(title);
    v2SalesEndWarningWrap.appendChild(copy);
    v2SalesEndWarningWrap.appendChild(list);
    v2SalesEndWarningWrap.appendChild(actions);
    setGuardrailVisibility(v2SalesEndWarningWrap, true);
  }

  function rebuildTemplateSelectOptions(templates, currentValue) {
    if (!v2TplSel) return;
    const current = String(currentValue || '').trim();
    const did = v2Editor ? String(v2Editor.dataset.defaultTemplateId || '').trim() : '';

    v2TplSel.innerHTML = '';
    const opt0 = document.createElement('option');
    opt0.value = '';
    opt0.textContent = 'Select a template…';
    v2TplSel.appendChild(opt0);

    (Array.isArray(templates) ? templates : []).forEach((templateRow) => {
      const option = document.createElement('option');
      option.value = String((templateRow && templateRow.id) || '').trim();
      const base = String((templateRow && (templateRow.name || templateRow.id)) || '').trim();
      option.textContent = (did && option.value === did) ? (base + ' (Default)') : base;
      if (templateRow && templateRow.sales_end_guardrail) {
        try {
          option.dataset.salesEndGuardrail = JSON.stringify(templateRow.sales_end_guardrail);
        } catch (e) {
          option.dataset.salesEndGuardrail = '';
        }
      }
      v2TplSel.appendChild(option);
    });

    v2TplSel.value = current;
    if (v2TplApplyBtn) v2TplApplyBtn.disabled = !String(v2TplSel.value || '').trim();
  }

  function finalizeTemplateApply(templateId, normalized, noteText, successText) {
    if (!v2Editor) return;
    v2Editor.dataset.initialConfig = JSON.stringify(normalized || {});
    v2Editor.dataset.configExists = '1';
    renderV2(normalized || {});
    try { sessionStorage.removeItem('vms_v2_suppress_auto_default_' + planId); } catch (e) {}
    clearV2PreviewState();
    clearGuardrail(v2TemplateGuardrailWrap);
    if (v2TplSel) {
      v2TplSel.value = String(templateId || '').trim();
      try { v2TplSel.dispatchEvent(new Event('change')); } catch (e) {}
    }
    setV2Note(noteText, 'info');
    setV2Msg(successText, 'success');
  }

  function applyTemplateToPlan(templateId, options) {
    const template = String(templateId || '').trim();
    const opts = options || {};
    if (!template || !(planId > 0)) {
      return Promise.resolve(false);
    }

    if (v2TplApplyBtn) {
      v2TplApplyBtn.disabled = true;
    }

    const payload = { plan_id: planId, template_id: template };
    const showDatetime = getCurrentShowDatetimeForServer();
    if (showDatetime) {
      payload.show_datetime = showDatetime;
    }
    if (opts.resetStaleSalesEnd) {
      payload.reset_stale_sales_end = 1;
    }

    setV2Msg(String(opts.workingText || 'Applying template…'), 'working');

    return postJSON('vms_ticketing_v2_apply_template', payload)
      .then((res) => {
        if (!res || !res.success) {
          setV2Msg((res && res.data && res.data.message) ? res.data.message : 'Apply failed.', 'error');
          if (v2TplApplyBtn) v2TplApplyBtn.disabled = false;
          return false;
        }

        const normalized = res.data && res.data.config ? res.data.config : parseJSONAttr(v2Editor.dataset.initialConfig, {});
        finalizeTemplateApply(
          template,
          normalized,
          String(opts.noteText || 'Template applied. Tickets are created or updated only after Preview → Commit.'),
          String(opts.successText || 'Template applied.')
        );
        if (v2TplApplyBtn) v2TplApplyBtn.disabled = false;
        return true;
      })
      .catch(() => {
        setV2Msg('Apply failed.', 'error');
        if (v2TplApplyBtn) v2TplApplyBtn.disabled = false;
        return false;
      });
  }

  function renderTemplateSalesEndGuardrail(templateId, options) {
    if (!v2TemplateGuardrailWrap) return false;

    const template = String(templateId || '').trim();
    const opts = options || {};
    const analysis = getTemplateSalesEndIssues(template);
    if (!analysis.targetComparable || !analysis.issues.length) {
      clearGuardrail(v2TemplateGuardrailWrap);
      return false;
    }

    const templateName = String(opts.templateName || getTemplateNameById(template) || template).trim() || 'Selected template';
    clearGuardrail(v2TemplateGuardrailWrap);

    const title = document.createElement('p');
    title.className = 'vms-ticketing__guardrail-title';
    title.textContent = 'Review Sales end dates before applying "' + templateName + '"';

    const copy = document.createElement('p');
    copy.className = 'vms-ticketing__guardrail-copy';
    const hasAfterEnd = analysis.issues.some((issue) => issue && issue.kind === 'after_end');
    copy.textContent = opts.autoDefault
      ? 'The default template was not auto-applied because one or more ticket Sales end values need review for this Event Plan date/time.'
      : (hasAfterEnd
        ? 'This template includes one or more ticket Sales end values after this Event Plan ends. Ticket sales cannot remain open past the event end.'
        : 'This template includes one or more ticket Sales end values before this Event Plan starts.');

    const list = document.createElement('ul');
    list.className = 'vms-ticketing__guardrail-list';
    analysis.issues.forEach((issue) => {
      const item = document.createElement('li');
      item.textContent = describeSalesEndIssue(issue, analysis);
      list.appendChild(item);
    });

    const actions = document.createElement('div');
    actions.className = 'vms-ticketing__guardrail-actions';

    const applyResetBtn = document.createElement('button');
    applyResetBtn.type = 'button';
    applyResetBtn.className = 'button button-primary';
    applyResetBtn.textContent = 'Apply template and reset Sales end to event end';
    applyResetBtn.addEventListener('click', function () {
      Array.from(actions.querySelectorAll('button')).forEach((button) => {
        button.disabled = true;
      });
      applyTemplateToPlan(template, {
        resetStaleSalesEnd: true,
        workingText: 'Applying template and resetting Sales end values…',
        noteText: 'Template applied. Tickets are created or updated only after Preview → Commit.',
        successText: 'Template applied and Sales end values were reset to this event end.',
      }).then((ok) => {
        if (!ok) {
          renderTemplateSalesEndGuardrail(template, opts);
        }
      });
    });

    const applySavedBtn = document.createElement('button');
    applySavedBtn.type = 'button';
    applySavedBtn.className = 'button button-secondary';
    applySavedBtn.textContent = 'Apply template as saved';
    applySavedBtn.addEventListener('click', function () {
      Array.from(actions.querySelectorAll('button')).forEach((button) => {
        button.disabled = true;
      });
      applyTemplateToPlan(template, {
        workingText: 'Applying template…',
        noteText: 'Template applied. Tickets are created or updated only after Preview → Commit.',
        successText: 'Template applied with the saved Sales end values.',
      }).then((ok) => {
        if (!ok) {
          renderTemplateSalesEndGuardrail(template, opts);
        }
      });
    });

    const cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.className = 'button button-link';
    cancelBtn.textContent = opts.autoDefault ? 'Leave template unapplied for now' : 'Cancel';
    cancelBtn.addEventListener('click', function () {
      clearGuardrail(v2TemplateGuardrailWrap);
      setV2Msg(opts.autoDefault ? 'Default template was not applied yet.' : 'Template apply canceled.', 'info');
    });

    actions.appendChild(applyResetBtn);
    actions.appendChild(applySavedBtn);
    actions.appendChild(cancelBtn);

    v2TemplateGuardrailWrap.appendChild(title);
    v2TemplateGuardrailWrap.appendChild(copy);
    v2TemplateGuardrailWrap.appendChild(list);
    v2TemplateGuardrailWrap.appendChild(actions);
    setGuardrailVisibility(v2TemplateGuardrailWrap, true);
    return true;
  }


  function fetchWithTimeout(url, options, timeoutMs) {
    const ms = parseInt(timeoutMs || 0, 10) || 20000;
    if (typeof AbortController === 'undefined') {
      return fetch(url, options);
    }
    const controller = new AbortController();
    const timer = setTimeout(() => {
      try { controller.abort(); } catch (e) { /* ignore */ }
    }, ms);
    const opts = Object.assign({}, options || {}, { signal: controller.signal });
    return fetch(url, opts).then((res) => {
      clearTimeout(timer);
      return res;
    }).catch((err) => {
      clearTimeout(timer);
      throw err;
    });
  }

  function postJSONWithNonce(action, data, requestNonce, timeoutMs) {
    const ms = parseInt(timeoutMs || 0, 10) || 20000;

    try {
      const fd = new FormData();
      fd.append('action', action);
      fd.append('nonce', String(requestNonce || nonce || ''));
      Object.keys(data || {}).forEach((k) => {
        const v = data[k];
        if (v && typeof v === 'object') {
          fd.append(k, JSON.stringify(v));
        } else {
          fd.append(k, String(v));
        }
      });
      return fetchWithTimeout(ajaxurl, {
        method: 'POST',
        credentials: 'same-origin',
        body: fd,
      }, ms).then(parseJsonOrError).catch((err) => {
        if (err && err.name === 'AbortError') {
          const seconds = Math.max(1, Math.round(ms / 1000));
          return buildAjaxFailure('timeout', 'The request exceeded the ' + seconds + ' second browser timeout.');
        }
        return buildAjaxFailure('network_error', String((err && err.message) || err || ''));
      });
    } catch (err) {
      return Promise.resolve(buildAjaxFailure(
        'request_setup_error',
        String((err && err.message) || err || ''),
        { exception_type: String((err && err.name) || '') }
      ));
    }
  }

  function postJSON(action, data, timeoutMs) {
    return postJSONWithNonce(action, data, nonce, timeoutMs);
  }

  // Stable stringify for "dirty" detection and safe comparisons.
  function stableStringify(obj) {
    const seen = new WeakSet();
    const helper = (v) => {
      if (v === null || typeof v !== 'object') return v;
      if (seen.has(v)) return null;
      seen.add(v);
      if (Array.isArray(v)) return v.map(helper);
      const out = {};
      Object.keys(v).sort().forEach((k) => {
        out[k] = helper(v[k]);
      });
      return out;
    };
    try {
      return JSON.stringify(helper(obj));
    } catch (e) {
      return '';
    }
  }
 
  function humanizeV2Message(code) {
    const c = String(code || '').trim();
    if (!c) return '';
    switch (c) {
      case 'invalid_payload':
        return 'The server rejected the ticketing request because required Preview or Event Plan data was missing. Run Preview sync again, then retry.';
      case 'not_managed_mode':
        return 'Ticketing mode is read-only. Set Mode to “VMS-managed”, click “Save config”, then click “Preview sync”.';
      case 'preview_not_managed':
        return 'Your last Preview was generated in read-only mode. Set Mode to “VMS-managed”, click “Save config”, then click “Preview sync” again.';
      case 'preview_owner_mismatch':
        return 'That Preview belongs to a different browser session. Run Preview sync again in this session, then retry Commit.';
      case 'stale_config':
        return 'Your Ticketing config has changed since the last Preview. Click “Preview sync” again before committing.';
      case 'missing_preview':
        return 'No Preview found. Click “Preview sync” first.';
      case 'preview_expired':
        return 'That Preview expired (waited too long). Click “Preview sync” again.';
      case 'legacy_init_retired':
        return 'Legacy Ticketing initializer is retired. Configure Ticketing directly, then run Preview sync → Commit sync.';
      case 'bad_response':
        return 'Unexpected server response (bad_response). Open the details panel below for the raw response, then send that to Cadence if it repeats.';
      case 'timeout':
        return 'The request timed out before the server finished responding. Open the details panel below to see what VMS knows so far.';
      case 'network_error':
        return 'The browser could not complete the request. Open the details panel below to see the raw error.';
      case 'missing_tec_link':
        return 'No linked TEC event was available when Commit started. Try Commit again; the prepare step should create or relink the draft calendar event first.';
      case 'preview_blocked':
        return 'The last Preview is blocked. Fix the blocked issues shown in Preview, then run Preview sync again.';
      case 'ticket_product_mapping_conflict':
        return 'Two ticket rows are targeting the same Woo product. Fix the duplicate mapping, save, and run Preview sync again.';
      case 'commit_not_ready_to_finalize':
        return 'Commit batching was not ready to finalize. Run Preview sync again before retrying Commit.';
      case 'event_tickets_woo_unavailable':
        return 'Event Tickets (WooCommerce) is not available right now. Activate Event Tickets, Event Tickets Plus, and WooCommerce, then try again.';
      case 'forbidden':
        return 'You do not have permission to perform this ticketing action.';
      default:
        return c;
    }
  }

  function humanizeCommitActionReason(reason) {
    const raw = String(reason || '').trim();
    if (!raw) return '';
    switch (raw) {
      case 'missing_ticket_config':
        return 'The ticket config row was missing by the time Commit ran.';
      case 'invalid_product_for_disable':
        return 'The mapped Woo product could not be found or was no longer valid for disabling.';
      case 'retire_safety_check_failed':
        return 'VMS refused to retire the old ticket product because it no longer proved that it was safe.';
      case 'retire_failed':
        return 'VMS could not retire the stale ticket product.';
      case 'ticket_disabled_pending_sync':
        return 'This ticket was disabled in config before Commit finished syncing the public products.';
      default:
        if (raw.indexOf('exception:') === 0) {
          return raw;
        }
        if (/^[a-z0-9_:-]+$/i.test(raw)) {
          const pretty = raw.replace(/_/g, ' ').replace(/\s+/g, ' ').trim();
          return pretty.charAt(0).toUpperCase() + pretty.slice(1);
        }
        return raw;
    }
  }

  function toDatetimeLocal(v) {
    const s = String(v || '').trim();
    if (!s) return '';
    if (s.includes('T')) {
      return s.length >= 16 ? s.slice(0, 16) : s;
    }
    const parts = s.split(' ');
    if (parts.length < 2) return '';
    const date = parts[0];
    const time = parts[1].slice(0, 5);
    if (!date || !time) return '';
    return date + 'T' + time;
  }

  function fromDatetimeLocal(v) {
    const s = String(v || '').trim();
    if (!s) return '';
    if (!s.includes('T')) return '';
    const parts = s.split('T');
    if (parts.length !== 2) return '';
    const date = parts[0];
    const time = parts[1].slice(0, 5);
    if (!date || !time) return '';
    return date + ' ' + time + ':00';
  }

  function pad2(n) {
    const v = parseInt(n, 10);
    if (!Number.isFinite(v)) return '00';
    return v < 10 ? ('0' + v) : String(v);
  }

  function formatLocalDatetimeValue(dateObj) {
    if (!(dateObj instanceof Date) || Number.isNaN(dateObj.getTime())) return '';
    return String(dateObj.getFullYear())
      + '-' + pad2(dateObj.getMonth() + 1)
      + '-' + pad2(dateObj.getDate())
      + 'T' + pad2(dateObj.getHours())
      + ':' + pad2(dateObj.getMinutes());
  }

  function getEventShowDatetimeValue() {
    const dateInput = document.getElementById('vms_event_date');
    const startInput = document.getElementById('vms_start_time');
    const dateRaw = String((dateInput && dateInput.value) || '').trim();
    const startRaw = String((startInput && startInput.value) || '').trim();
    if (!/^\d{4}-\d{2}-\d{2}$/.test(dateRaw)) return '';
    if (!/^\d{2}:\d{2}$/.test(startRaw)) return '';
    return dateRaw + 'T' + startRaw;
  }

  function parseLocalDatetimeValue(localValue) {
    const s = String(localValue || '').trim();
    if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(s)) return null;
    const parts = s.split('T');
    const d = parts[0].split('-').map((part) => parseInt(part, 10));
    const t = parts[1].split(':').map((part) => parseInt(part, 10));
    if (d.length !== 3 || t.length !== 2 || d.some(Number.isNaN) || t.some(Number.isNaN)) return null;
    const out = new Date(d[0], d[1] - 1, d[2], t[0], t[1], 0, 0);
    return Number.isNaN(out.getTime()) ? null : out;
  }

  function getEventEndDatetimeValue() {
    const dateInput = document.getElementById('vms_event_date');
    const startInput = document.getElementById('vms_start_time');
    const endInput = document.getElementById('vms_end_time');
    const dateRaw = String((dateInput && dateInput.value) || '').trim();
    const startRaw = String((startInput && startInput.value) || '').trim();
    const endRaw = String((endInput && endInput.value) || '').trim();
    if (!/^\d{4}-\d{2}-\d{2}$/.test(dateRaw)) return '';
    if (!/^\d{2}:\d{2}$/.test(endRaw)) return '';
    const endDt = parseLocalDatetimeValue(dateRaw + 'T' + endRaw);
    if (!endDt) return '';
    if (/^\d{2}:\d{2}$/.test(startRaw)) {
      const startDt = parseLocalDatetimeValue(dateRaw + 'T' + startRaw);
      if (startDt && endDt.getTime() <= startDt.getTime()) {
        endDt.setDate(endDt.getDate() + 1);
      }
    }
    return formatLocalDatetimeValue(endDt);
  }

  function normalizeRelativeDaysValue(v) {
    const raw = String(v === undefined || v === null ? '' : v).trim();
    if (!raw) return '';
    if (!/^\d+$/.test(raw)) return '';
    return String(Math.max(0, Math.min(3650, parseInt(raw, 10) || 0)));
  }

  function relativeDateBefore(anchorLocalValue, relativeDays) {
    const days = normalizeRelativeDaysValue(relativeDays);
    if (days === '') return '';
    const anchor = parseLocalDatetimeValue(anchorLocalValue);
    if (!anchor) return '';
    anchor.setDate(anchor.getDate() - (parseInt(days, 10) || 0));
    return formatLocalDatetimeValue(anchor);
  }

  function clampLocalDatetimeToEventEnd(localValue) {
    const eventEnd = getEventEndDatetimeValue();
    if (!eventEnd) return localValue;
    const currentDt = parseLocalDatetimeValue(localValue);
    const endDt = parseLocalDatetimeValue(eventEnd);
    if (!currentDt || !endDt) return localValue;
    return currentDt.getTime() > endDt.getTime() ? eventEnd : localValue;
  }

  function getNewTicketSalesWindowDefaults() {
    const nowLocal = formatLocalDatetimeValue(new Date());
    const showEndLocal = getEventEndDatetimeValue() || getEventShowDatetimeValue();
    return {
      sales_start: fromDatetimeLocal(nowLocal),
      sales_end: fromDatetimeLocal(showEndLocal),
      sales_start_relative_days: '',
      sales_end_relative_days: '0',
    };
  }

  function withTicketSalesWindowDefaults(ticket, defaults) {
    const t = (ticket && typeof ticket === 'object') ? Object.assign({}, ticket) : {};
    const d = (defaults && typeof defaults === 'object') ? defaults : {};
    const startRaw = String(t.sales_start || '').trim();
    const endRaw = String(t.sales_end || '').trim();

    // Treat blank/invalid source values as missing so imported plans self-heal.
    if (!toDatetimeLocal(startRaw)) {
      t.sales_start = String(d.sales_start || '');
    }
    if (!toDatetimeLocal(endRaw)) {
      t.sales_end = String(d.sales_end || '');
      if (t.sales_end_relative_days === undefined || String(t.sales_end_relative_days || '').trim() === '') {
        t.sales_end_relative_days = String(d.sales_end_relative_days || '');
      }
    }
    return t;
  }

  function safeInt(v) {
    const n = parseInt(String(v || '').trim(), 10);
    return Number.isFinite(n) ? n : 0;
  }

  function safeNumberString(v) {
    const s = String(v || '').trim();
    if (s === '') return '0';
    return s;
  }


  function applyRelativeDateFieldsForTicketRow(row) {
    if (!row) return;
    const eventStart = getEventShowDatetimeValue();
    const eventEnd = getEventEndDatetimeValue() || eventStart;
    const pairs = [
      { dateClass: '.vms-ticketing-v2-ticket-early-start', relativeClass: '.vms-ticketing-v2-ticket-early-start-relative', anchor: eventStart },
      { dateClass: '.vms-ticketing-v2-ticket-early-end', relativeClass: '.vms-ticketing-v2-ticket-early-end-relative', anchor: eventStart },
      { dateClass: '.vms-ticketing-v2-ticket-sales-start', relativeClass: '.vms-ticketing-v2-ticket-sales-start-relative', anchor: eventStart },
      { dateClass: '.vms-ticketing-v2-ticket-sales-end', relativeClass: '.vms-ticketing-v2-ticket-sales-end-relative', anchor: eventEnd },
    ];
    pairs.forEach((pair) => {
      const dateInput = row.querySelector(pair.dateClass);
      const relativeInput = row.querySelector(pair.relativeClass);
      if (!dateInput || !relativeInput) return;
      const days = normalizeRelativeDaysValue(relativeInput.value);
      if (days === '') return;
      relativeInput.value = days;
      const resolved = relativeDateBefore(pair.anchor, days);
      if (resolved) {
        dateInput.value = resolved;
      }
    });

    const salesEnd = row.querySelector('.vms-ticketing-v2-ticket-sales-end');
    if (salesEnd && salesEnd.value) {
      salesEnd.value = clampLocalDatetimeToEventEnd(salesEnd.value);
    }
  }

  function refreshAllRelativeTicketDateFields() {
    if (!v2Editor) return;
    v2Editor.querySelectorAll('.vms-ticketing-v2-ticket-row').forEach((row) => {
      applyRelativeDateFieldsForTicketRow(row);
    });
  }

  // Pool groups: shared entitlement limits (e.g., tables + fire pits).
  // Operator selects a pool key to avoid typos and keep cart enforcement deterministic.
  let v2PoolKeys = [];

  function toPoolKey(v) {
    const raw = String(v || '').trim().toLowerCase();
    if (!raw) return '';
    let k = raw
      .replace(/[^a-z0-9_\-\s]+/g, '')
      .replace(/[\s\-]+/g, '_')
      .replace(/_+/g, '_')
      .replace(/^_+|_+$/g, '');
    if (!k) return '';
    if (k.length > 60) k = k.slice(0, 60);
    return k;
  }

  function poolKeyLabel(key) {
    const k = String(key || '').trim();
    if (!k) return '';
    const nice = k
      .split('_')
      .filter(Boolean)
      .map(w => w.charAt(0).toUpperCase() + w.slice(1))
      .join(' ');
    return nice ? (nice + ' (' + k + ')') : k;
  }

  function ensurePoolKey(key) {
    const k = toPoolKey(key);
    if (!k) return '';
    if (!Array.isArray(v2PoolKeys)) v2PoolKeys = [];
    if (!v2PoolKeys.includes(k)) v2PoolKeys.push(k);
    return k;
  }

  function fillPoolSelectOptions(sel, current) {
    if (!sel) return;
    const cur = String(current || sel.value || '');
    sel.innerHTML = '';

    const opt0 = document.createElement('option');
    opt0.value = '';
    opt0.textContent = 'No pool (separate limits)';
    sel.appendChild(opt0);

    (v2PoolKeys || []).forEach((k) => {
      const opt = document.createElement('option');
      opt.value = k;
      opt.textContent = poolKeyLabel(k);
      sel.appendChild(opt);
    });

    sel.value = cur;
  }

  function refreshAllPoolSelects() {
    if (!v2Editor) return;
    const sels = v2Editor.querySelectorAll('select.vms-ticketing-v2-ent-pool');
    sels.forEach((sel) => {
      fillPoolSelectOptions(sel, sel.value || '');
    });
  }

  function uid() {
    return 'ent_' + Math.random().toString(16).slice(2) + Math.random().toString(16).slice(2);
  }

  function pickAttachmentPreviewUrl(attObj) {
    const att = attObj || {};
    const sizes = att.sizes || {};
    if (sizes.thumbnail && sizes.thumbnail.url) return String(sizes.thumbnail.url);
    if (sizes.medium && sizes.medium.url) return String(sizes.medium.url);
    if (att.url) return String(att.url);
    return '';
  }

  function resolveAttachmentPreviewUrl(imageId, onDone) {
    const done = typeof onDone === 'function' ? onDone : function () {};
    const id = Math.max(0, safeInt(imageId));
    if (!(id > 0)) {
      done('');
      return;
    }
    try {
      if (!window.wp || !wp.media || typeof wp.media.attachment !== 'function') {
        done('');
        return;
      }
      const att = wp.media.attachment(id);
      const finish = function () {
        const attrs = att && att.attributes ? att.attributes : {};
        done(pickAttachmentPreviewUrl(attrs));
      };
      if (att && typeof att.fetch === 'function') {
        const p = att.fetch();
        if (p && typeof p.then === 'function') {
          p.then(finish).catch(function () { done(''); });
          return;
        }
      }
      finish();
    } catch (e) {
      done('');
    }
  }

  function setEntRowImage(row, imageId, imageUrl) {
    if (!row) return;
    const id = Math.max(0, safeInt(imageId));
    const idInput = row.querySelector('.vms-ticketing-v2-ent-image-id');
    if (idInput) idInput.value = String(id);

    const img = row.querySelector('.vms-ticketing__image-thumb');
    const empty = row.querySelector('.vms-ticketing__image-empty');
    const removeBtn = row.querySelector('.vms-ticketing__img-remove');
    const copyBtn = row.querySelector('.vms-ticketing__img-copy-pool');
    const poolSel = row.querySelector('.vms-ticketing-v2-ent-pool');
    const sourceNote = row.querySelector('.vms-ticketing__image-source');

    const url = String(imageUrl || '').trim();
    if (img) {
      if (id > 0 && url) {
        img.src = url;
        img.style.display = '';
      } else {
        img.removeAttribute('src');
        img.style.display = 'none';
      }
    }
    if (empty) {
      empty.textContent = id > 0 ? 'No image preview' : 'No image selected';
      empty.style.display = (id > 0 && url) ? 'none' : '';
    }
    if (removeBtn) {
      removeBtn.disabled = !(id > 0);
    }
    if (sourceNote) {
      sourceNote.textContent = id > 0 ? 'Custom image' : 'No image selected';
    }
    if (copyBtn) {
      const hasPool = !!toPoolKey(poolSel && poolSel.value ? poolSel.value : '');
      copyBtn.style.display = hasPool ? '' : 'none';
      copyBtn.disabled = !(id > 0 && hasPool);
    }
  }

  function normalizeTicketImageMode(rawMode) {
    const mode = String(rawMode || '').trim();
    if (mode === 'custom' || mode === 'none') {
      return mode;
    }
    return 'event_featured';
  }

  function getPlanImageState() {
    if (!v2Editor || !v2Editor.dataset) {
      return { id: 0, url: '' };
    }
    return {
      id: Math.max(0, safeInt(v2Editor.dataset.planImageId || 0)),
      url: String(v2Editor.dataset.planImageUrl || '').trim(),
    };
  }

  function formatSummaryMoney(rawValue) {
    const num = parseFloat(String(rawValue !== undefined ? rawValue : '0').trim());
    if (!Number.isFinite(num)) {
      return '$0.00';
    }
    return '$' + num.toFixed(2);
  }

  function createRowSummaryStat(label, value, extraClass) {
    const stat = document.createElement('span');
    stat.className = 'vms-ticketing__row-stat';
    if (extraClass) {
      stat.className += ' ' + String(extraClass);
    }

    const labelEl = document.createElement('span');
    labelEl.className = 'vms-ticketing__row-stat-label';
    labelEl.textContent = String(label || '').trim();

    const valueEl = document.createElement('span');
    valueEl.className = 'vms-ticketing__row-stat-value';
    valueEl.textContent = String(value || '').trim();

    stat.appendChild(labelEl);
    stat.appendChild(valueEl);
    return stat;
  }

  function createRowDragHandle(ariaLabel) {
    const handle = document.createElement('span');
    handle.className = 'vms-ticketing__drag-handle';
    handle.textContent = '≡';
    handle.setAttribute('role', 'button');
    handle.setAttribute('tabindex', '0');
    handle.setAttribute('draggable', 'true');
    handle.setAttribute('aria-label', String(ariaLabel || 'Drag to reorder').trim());
    handle.setAttribute('title', 'Drag to reorder');
    return handle;
  }

  function syncTicketSortOrderInputs(listEl) {
    if (!listEl) return;
    const rows = listEl.querySelectorAll('.vms-ticketing-v2-ticket-row');
    rows.forEach((row, idx) => {
      const input = row.querySelector('.vms-ticketing-v2-ticket-sort-order');
      if (input) {
        input.value = String((idx + 1) * 10);
      }
    });
  }

  function enableSortableList(listEl, rowSelector, onReorder) {
    if (!listEl) return;

    const cleanup = function () {
      if (v2ActiveDragState && v2ActiveDragState.row) {
        v2ActiveDragState.row.classList.remove('is-dragging');
      }
      v2ActiveDragState = null;
    };

    listEl.querySelectorAll('.vms-ticketing__drag-handle[draggable="true"]').forEach((handle) => {
      if (handle.dataset.sortableBound === '1') {
        return;
      }
      handle.dataset.sortableBound = '1';
      handle.addEventListener('dragstart', function (event) {
        const row = handle.closest(rowSelector);
        if (!row) return;
        v2ActiveDragState = { list: listEl, row };
        row.classList.add('is-dragging');
        if (event.dataTransfer) {
          event.dataTransfer.effectAllowed = 'move';
          try {
            event.dataTransfer.setData('text/plain', String(row.dataset.ticketKey || row.dataset.entitlementId || 'row'));
          } catch (e) {
            // ignore
          }
        }
      });

      handle.addEventListener('dragend', function () {
        cleanup();
      });
    });

    if (listEl.dataset.sortableBound === '1') {
      return;
    }
    listEl.dataset.sortableBound = '1';

    listEl.addEventListener('dragover', function (event) {
      if (!v2ActiveDragState || v2ActiveDragState.list !== listEl || !v2ActiveDragState.row) {
        return;
      }

      const targetRow = event.target.closest(rowSelector);
      if (!targetRow || targetRow === v2ActiveDragState.row) {
        return;
      }

      event.preventDefault();
      if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
      }

      const rect = targetRow.getBoundingClientRect();
      const insertAfter = event.clientY > (rect.top + (rect.height / 2));
      const referenceNode = insertAfter ? targetRow.nextElementSibling : targetRow;
      if (referenceNode === v2ActiveDragState.row) {
        return;
      }
      if (v2ActiveDragState.row.nextElementSibling === referenceNode) {
        return;
      }

      listEl.insertBefore(v2ActiveDragState.row, referenceNode);
      if (typeof onReorder === 'function') {
        onReorder(listEl);
      }
    });

    listEl.addEventListener('drop', function (event) {
      if (!v2ActiveDragState || v2ActiveDragState.list !== listEl) {
        return;
      }
      event.preventDefault();
      if (typeof onReorder === 'function') {
        onReorder(listEl);
      }
      cleanup();
    });
  }

  function parseJSONAttr(v, fallback) {
    try {
      return JSON.parse(String(v || '')) || fallback;
    } catch (e) {
      return fallback;
    }
  }

  function renderV2(config, opts) {
    if (!v2Editor) return;

    const options = opts || {};
    const cfg = config || {};
    const salesDefaults = getNewTicketSalesWindowDefaults();
    let tickets = Array.isArray(cfg.tickets) ? cfg.tickets : [];
    if (!tickets.length) {
      const ga = cfg.ga || {};
      const defaults = options.useDefaultSalesWindow === false ? { sales_start: '', sales_end: '' } : salesDefaults;
      tickets = [withTicketSalesWindowDefaults({
        enabled: true,
        ticket_key: 'ga',
        title: String(ga.label || 'General Admission'),
        description: '',
        price: safeNumberString(ga.price !== undefined ? ga.price : '0'),
        early_price: ga.early_price !== undefined && String(ga.early_price || '').trim() !== '' ? safeNumberString(ga.early_price) : '',
        early_price_start: String(ga.early_price_start || ''),
        early_price_end: String(ga.early_price_end || ''),
        early_price_start_relative_days: normalizeRelativeDaysValue(ga.early_price_start_relative_days),
        early_price_end_relative_days: normalizeRelativeDaysValue(ga.early_price_end_relative_days),
        early_price_cap: Math.max(0, safeInt(ga.early_price_cap || ga.early_price_limit || 0)),
        inventory_total: (ga.capacity === null || ga.capacity === undefined || String(ga.capacity) === '') ? 0 : safeInt(ga.capacity),
        visibility_mode: 'public',
        verified_program: '',
        counts_toward_unlock: true,
        max_qty_per_order: 0,
        ratio_rule_enabled: false,
        ratio_rule_max_per_qualifying: 0,
        ratio_rule_qualifier_mode: 'counts_toward_unlock',
        ratio_rule_group: '',
        sort_order: 10,
        sales_start: String(ga.sales_start || ''),
        sales_end: String(ga.sales_end || ''),
        sales_start_relative_days: normalizeRelativeDaysValue(ga.sales_start_relative_days),
        sales_end_relative_days: normalizeRelativeDaysValue(ga.sales_end_relative_days),
      }, defaults)];
    } else {
      tickets = tickets.map((ticket) => withTicketSalesWindowDefaults(ticket, salesDefaults));
    }
    const ents = Array.isArray(cfg.entitlements) ? cfg.entitlements : [];

    // Build pool key list from current config so the dropdown is typo-proof.
    v2PoolKeys = [];
    // Common default: reserved seating (tables + fire pits).
    ensurePoolKey('reserved_seating');
    ents.forEach((ent) => {
      const elig = (ent && ent.eligibility) ? ent.eligibility : {};
      const pk = toPoolKey(elig.pool_key || '');
      if (pk) ensurePoolKey(pk);
    });

    v2Editor.innerHTML = '';

    const ticketsBox = document.createElement('div');
    ticketsBox.className = 'vms-ticketing__box';

    const ticketsTitle = document.createElement('h4');
    ticketsTitle.className = 'vms-ticketing__box-title';
    ticketsTitle.textContent = 'Tickets';
    ticketsBox.appendChild(ticketsTitle);

    const ticketsList = document.createElement('div');
    ticketsList.id = 'vms-ticketing-v2-ticket-list';
    ticketsList.className = 'vms-ticketing__sortable-list';

    tickets.forEach((ticket) => {
      ticketsList.appendChild(renderTicketRow(ticket));
    });
    syncTicketSortOrderInputs(ticketsList);
    ticketsBox.appendChild(ticketsList);

    const addTicketBtn = document.createElement('button');
    addTicketBtn.type = 'button';
    addTicketBtn.className = 'button button-small';
    addTicketBtn.textContent = 'Add ticket';
    addTicketBtn.addEventListener('click', function () {
      const nextCount = ticketsList.querySelectorAll('.vms-ticketing-v2-ticket-row').length + 1;
      const defaults = getNewTicketSalesWindowDefaults();
      ticketsList.appendChild(renderTicketRow({
        enabled: true,
        ticket_key: 'ticket_' + uid(),
        title: '',
        description: '',
        price: '0',
        early_price: '',
        early_price_start: '',
        early_price_end: '',
        early_price_start_relative_days: '',
        early_price_end_relative_days: '',
        early_price_cap: 0,
        inventory_total: 0,
        visibility_mode: 'public',
        verified_program: '',
        counts_toward_unlock: true,
        max_qty_per_order: 0,
        ratio_rule_enabled: false,
        ratio_rule_max_per_qualifying: 0,
        ratio_rule_qualifier_mode: 'counts_toward_unlock',
        ratio_rule_group: '',
        sort_order: nextCount * 10,
        sales_start: defaults.sales_start,
        sales_end: defaults.sales_end,
        sales_start_relative_days: defaults.sales_start_relative_days,
        sales_end_relative_days: defaults.sales_end_relative_days,
        image_mode: 'event_featured',
        image_id: 0,
      }, { expanded: true }));
      syncTicketSortOrderInputs(ticketsList);
      enableSortableList(ticketsList, '.vms-ticketing-v2-ticket-row', syncTicketSortOrderInputs);
      renderCurrentSalesEndWarning();
    });
    ticketsBox.appendChild(addTicketBtn);
    v2Editor.appendChild(ticketsBox);
    enableSortableList(ticketsList, '.vms-ticketing-v2-ticket-row', syncTicketSortOrderInputs);

    const entBox = document.createElement('div');
    entBox.className = 'vms-ticketing__box';

    const entTitle = document.createElement('h4');
    entTitle.className = 'vms-ticketing__box-title';
    entTitle.textContent = 'Entitlements';
    entBox.appendChild(entTitle);

    const entList = document.createElement('div');
    entList.id = 'vms-ticketing-v2-ent-list';
    entList.className = 'vms-ticketing__sortable-list';

    ents.forEach((ent) => {
      entList.appendChild(renderEntRow(ent));
    });

    entBox.appendChild(entList);

    const addBtn = document.createElement('button');
    addBtn.type = 'button';
    addBtn.className = 'button button-small';
    addBtn.textContent = 'Add entitlement';
    addBtn.addEventListener('click', function () {
      const ent = { entitlement_id: uid(), enabled: true, label: '', short_desc: '', more_info: '', image_id: 0, price: '0', capacity: 1, selector_mode: 'stepper', eligibility: { min_ga_per_unit: 0, pool_key: '', pool_max_total: 0, pool_max_explicit: 0, max_units_per_order: 0, max_units_per_ga: 0, allow_without_ga: false } };
      entList.appendChild(renderEntRow(ent, { expanded: true }));
      enableSortableList(entList, '.vms-ticketing-v2-ent-row', function () {});
    });

    entBox.appendChild(addBtn);
    v2Editor.appendChild(entBox);
    enableSortableList(entList, '.vms-ticketing-v2-ent-row', function () {});
    renderCurrentSalesEndWarning();
  }

  function renderTicketRow(ticket, opts) {
    const t = ticket || {};
    const options = opts || {};
    const row = document.createElement('div');
    row.className = 'vms-ticketing__tier vms-ticketing-v2-ticket-row';
    row.dataset.ticketKey = String(t.ticket_key || ('ticket_' + uid()));
    const detailsId = 'vms-ticket-admin-details-' + String(row.dataset.ticketKey || uid()).replace(/[^a-zA-Z0-9_\-]/g, '');

    const sortOrder = document.createElement('input');
    sortOrder.type = 'hidden';
    sortOrder.className = 'vms-ticketing-v2-ticket-sort-order';
    sortOrder.value = String(safeInt(t.sort_order || 0) || 10);

    const dragHandle = createRowDragHandle('Drag to reorder ticket');

    const summary = document.createElement('div');
    summary.className = 'vms-ticketing__row-summary';

    const summaryMain = document.createElement('div');
    summaryMain.className = 'vms-ticketing__row-summary-main';

    const summaryTitle = document.createElement('strong');
    summaryTitle.className = 'vms-ticketing__row-title';

    const summaryMeta = document.createElement('div');
    summaryMeta.className = 'vms-ticketing__row-meta';

    summaryMain.appendChild(summaryTitle);
    summaryMain.appendChild(summaryMeta);

    const toggleDetails = document.createElement('button');
    toggleDetails.type = 'button';
    toggleDetails.className = 'button button-small vms-ticketing__row-toggle';
    toggleDetails.setAttribute('aria-controls', detailsId);

    const enabled = document.createElement('input');
    enabled.type = 'checkbox';
    enabled.className = 'vms-ticketing-v2-ticket-enabled';
    enabled.checked = t.enabled === false ? false : true;

    const enabledWrap = document.createElement('label');
    enabledWrap.className = 'vms-ticketing__tier-flag';
    enabledWrap.appendChild(enabled);
    enabledWrap.appendChild(document.createTextNode('Enabled'));

    const title = document.createElement('input');
    title.type = 'text';
    title.className = 'regular-text vms-ticketing-v2-ticket-title';
    title.value = plainTextValue(t.title || '');

    const description = document.createElement('input');
    description.type = 'text';
    description.className = 'regular-text vms-ticketing-v2-ticket-description';
    description.value = plainTextValue(t.description || '');

    const price = document.createElement('input');
    price.type = 'number';
    price.step = '0.01';
    price.min = '0';
    price.className = 'vms-ticketing-v2-ticket-price';
    price.value = safeNumberString(t.price !== undefined ? t.price : '0');

    const earlyPrice = document.createElement('input');
    earlyPrice.type = 'number';
    earlyPrice.step = '0.01';
    earlyPrice.min = '0';
    earlyPrice.className = 'vms-ticketing-v2-ticket-early-price';
    earlyPrice.value = (t.early_price !== undefined && String(t.early_price || '').trim() !== '') ? safeNumberString(t.early_price) : '';
    earlyPrice.placeholder = 'Optional';

    const earlyStart = document.createElement('input');
    earlyStart.type = 'datetime-local';
    earlyStart.className = 'vms-ticketing-v2-ticket-early-start';
    earlyStart.value = toDatetimeLocal(t.early_price_start || '');

    const earlyEnd = document.createElement('input');
    earlyEnd.type = 'datetime-local';
    earlyEnd.className = 'vms-ticketing-v2-ticket-early-end';
    earlyEnd.value = toDatetimeLocal(t.early_price_end || '');

    const earlyStartRelative = document.createElement('input');
    earlyStartRelative.type = 'number';
    earlyStartRelative.step = '1';
    earlyStartRelative.min = '0';
    earlyStartRelative.className = 'vms-ticketing-v2-ticket-early-start-relative';
    earlyStartRelative.value = normalizeRelativeDaysValue(t.early_price_start_relative_days);
    earlyStartRelative.placeholder = 'Blank';

    const earlyEndRelative = document.createElement('input');
    earlyEndRelative.type = 'number';
    earlyEndRelative.step = '1';
    earlyEndRelative.min = '0';
    earlyEndRelative.className = 'vms-ticketing-v2-ticket-early-end-relative';
    earlyEndRelative.value = normalizeRelativeDaysValue(t.early_price_end_relative_days);
    earlyEndRelative.placeholder = 'e.g. 31';

    const earlyCap = document.createElement('input');
    earlyCap.type = 'number';
    earlyCap.step = '1';
    earlyCap.min = '0';
    earlyCap.className = 'vms-ticketing-v2-ticket-early-cap';
    earlyCap.value = String(Math.max(0, safeInt(t.early_price_cap || t.early_price_limit || 0)));
    earlyCap.placeholder = '0';

    const inventory = document.createElement('input');
    inventory.type = 'number';
    inventory.step = '1';
    inventory.min = '0';
    inventory.className = 'vms-ticketing-v2-ticket-inventory';
    inventory.value = (t.inventory_total === null || t.inventory_total === undefined || String(t.inventory_total) === '') ? '0' : String(t.inventory_total);

    const visibility = document.createElement('select');
    visibility.className = 'vms-ticketing-v2-ticket-visibility';
    [
      { value: 'public', label: 'Anyone' },
      { value: 'login', label: 'Logged-in customers only' },
      { value: 'verified', label: 'Verified status required' },
    ].forEach((optRow) => {
      const opt = document.createElement('option');
      opt.value = optRow.value;
      opt.textContent = optRow.label;
      visibility.appendChild(opt);
    });
    visibility.value = String(t.visibility_mode || 'public');
    if (!visibility.value) {
      visibility.value = 'public';
    }

    const program = document.createElement('select');
    program.className = 'vms-ticketing-v2-ticket-program';
    const programOptions = [{ value: '', label: 'Select group' }];
    try {
      const map = window.VMS_TICKETING && window.VMS_TICKETING.verificationPrograms
        ? window.VMS_TICKETING.verificationPrograms
        : null;

      if (map && typeof map === 'object') {
        Object.keys(map).forEach((keyName) => {
          const key = String(keyName || '').trim();
          if (!key) return;
          const label = String(map[keyName] || key).trim() || key;
          programOptions.push({ value: key, label });
        });
      }
    } catch (e) {
      // ignore
    }

    const currentProgram = String(t.verified_program || '').trim();
    if (currentProgram && !programOptions.some((optRow) => optRow.value === currentProgram)) {
      programOptions.push({ value: currentProgram, label: currentProgram });
    }

    programOptions.forEach((optRow) => {
      const opt = document.createElement('option');
      opt.value = optRow.value;
      opt.textContent = optRow.label;
      program.appendChild(opt);
    });
    program.value = String(t.verified_program || '');
    if (!program.value) {
      program.value = '';
    }

    const countsToward = document.createElement('input');
    countsToward.type = 'checkbox';
    countsToward.className = 'vms-ticketing-v2-ticket-counts';
    countsToward.checked = (t.counts_toward_unlock === false) ? false : true;

    const countsWrap = document.createElement('label');
    countsWrap.className = 'vms-ticketing__tier-flag';
    countsWrap.appendChild(countsToward);
    countsWrap.appendChild(document.createTextNode('Counts toward add-on unlock'));

    const maxQtyPerOrder = document.createElement('input');
    maxQtyPerOrder.type = 'number';
    maxQtyPerOrder.step = '1';
    maxQtyPerOrder.min = '0';
    maxQtyPerOrder.className = 'vms-ticketing-v2-ticket-max-qty';
    maxQtyPerOrder.value = String(Math.max(0, safeInt(t.max_qty_per_order || 0)));

    const ratioEnabled = document.createElement('input');
    ratioEnabled.type = 'checkbox';
    ratioEnabled.className = 'vms-ticketing-v2-ticket-ratio-enabled';
    ratioEnabled.checked = !!t.ratio_rule_enabled && Math.max(0, safeInt(t.ratio_rule_max_per_qualifying || 0)) > 0;

    const ratioEnabledWrap = document.createElement('label');
    ratioEnabledWrap.className = 'vms-ticketing__tier-flag';
    ratioEnabledWrap.appendChild(ratioEnabled);
    ratioEnabledWrap.appendChild(document.createTextNode('Limit by qualifying tickets'));

    const ratioMax = document.createElement('input');
    ratioMax.type = 'number';
    ratioMax.step = '1';
    ratioMax.min = '0';
    ratioMax.className = 'vms-ticketing-v2-ticket-ratio-max';
    ratioMax.value = String(Math.max(0, safeInt(t.ratio_rule_max_per_qualifying || 0)));
    ratioMax.placeholder = '0';

    const ratioGroup = document.createElement('input');
    ratioGroup.type = 'text';
    ratioGroup.className = 'vms-ticketing-v2-ticket-ratio-group';
    ratioGroup.value = String(t.ratio_rule_group || '').trim();
    ratioGroup.placeholder = 'Optional, e.g. youth';

    function syncRatioState() {
      const isEnabled = !!ratioEnabled.checked;
      ratioMax.disabled = !isEnabled;
      ratioGroup.disabled = !isEnabled;
      if (!isEnabled && String(ratioMax.value || '').trim() === '') {
        ratioMax.value = '0';
      }
      if (!isEnabled) {
        ratioGroup.value = '';
      }
    }

    const salesStart = document.createElement('input');
    salesStart.type = 'datetime-local';
    salesStart.className = 'vms-ticketing-v2-ticket-sales-start';
    salesStart.value = toDatetimeLocal(t.sales_start || '');

    const salesEnd = document.createElement('input');
    salesEnd.type = 'datetime-local';
    salesEnd.className = 'vms-ticketing-v2-ticket-sales-end';
    salesEnd.value = toDatetimeLocal(t.sales_end || '');

    const salesStartRelative = document.createElement('input');
    salesStartRelative.type = 'number';
    salesStartRelative.step = '1';
    salesStartRelative.min = '0';
    salesStartRelative.className = 'vms-ticketing-v2-ticket-sales-start-relative';
    salesStartRelative.value = normalizeRelativeDaysValue(t.sales_start_relative_days);
    salesStartRelative.placeholder = 'Blank';

    const salesEndRelative = document.createElement('input');
    salesEndRelative.type = 'number';
    salesEndRelative.step = '1';
    salesEndRelative.min = '0';
    salesEndRelative.className = 'vms-ticketing-v2-ticket-sales-end-relative';
    salesEndRelative.value = normalizeRelativeDaysValue(t.sales_end_relative_days);
    salesEndRelative.placeholder = '0';

    const imageMode = document.createElement('select');
    imageMode.className = 'vms-ticketing-v2-ticket-image-mode';
    [
      { value: 'event_featured', label: 'Use Event Plan featured image' },
      { value: 'custom', label: 'Use custom image' },
      { value: 'none', label: 'No image' },
    ].forEach((optRow) => {
      const opt = document.createElement('option');
      opt.value = optRow.value;
      opt.textContent = optRow.label;
      imageMode.appendChild(opt);
    });
    imageMode.value = normalizeTicketImageMode(t.image_mode || 'event_featured');

    const imageIdInput = document.createElement('input');
    imageIdInput.type = 'hidden';
    imageIdInput.className = 'vms-ticketing-v2-ticket-image-id';
    imageIdInput.value = String(Math.max(0, safeInt(t.image_id || 0)));

    const imagePreview = document.createElement('div');
    imagePreview.className = 'vms-ticketing__image-preview';

    const imageThumb = document.createElement('img');
    imageThumb.className = 'vms-ticketing__image-thumb';
    imageThumb.alt = String(t.title || 'Ticket image');
    imageThumb.style.display = 'none';

    const imageEmpty = document.createElement('span');
    imageEmpty.className = 'vms-ticketing__image-empty';
    imageEmpty.textContent = 'No image';

    imagePreview.appendChild(imageThumb);
    imagePreview.appendChild(imageEmpty);

    const imageButtons = document.createElement('div');
    imageButtons.className = 'vms-ticketing__image-actions';

    const imageSelect = document.createElement('button');
    imageSelect.type = 'button';
    imageSelect.className = 'button button-small vms-ticketing__img-select';
    imageSelect.textContent = 'Select image';

    const imageRemove = document.createElement('button');
    imageRemove.type = 'button';
    imageRemove.className = 'button button-small vms-ticketing__img-remove';
    imageRemove.textContent = 'Remove';

    imageButtons.appendChild(imageSelect);
    imageButtons.appendChild(imageRemove);

    const imageSource = document.createElement('span');
    imageSource.className = 'vms-ticketing__image-source';

    const imageWrap = document.createElement('div');
    imageWrap.className = 'vms-ticketing__image-wrap';
    imageWrap.appendChild(imagePreview);
    imageWrap.appendChild(imageButtons);
    imageWrap.appendChild(imageIdInput);
    imageWrap.appendChild(imageSource);

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'button button-small vms-ent-admin-remove';
    remove.textContent = 'Remove';
    remove.addEventListener('click', () => {
      const parentList = row.parentElement;
      row.remove();
      syncTicketSortOrderInputs(parentList);
      renderCurrentSalesEndWarning();
    });

    const programField = labelWrap('Verified group', program);
    const syncProgramVisibility = function () {
      programField.style.display = (visibility.value === 'verified') ? '' : 'none';
      if (visibility.value !== 'verified') {
        program.value = '';
      }
    };
    visibility.addEventListener('change', syncProgramVisibility);
    syncProgramVisibility();

    const imageState = { customUrl: '' };

    function syncTicketImagePreview() {
      const mode = normalizeTicketImageMode(imageMode.value);
      const imageId = Math.max(0, safeInt(imageIdInput.value || 0));
      const planImage = getPlanImageState();
      const customUrl = String(imageState.customUrl || '').trim();

      let previewUrl = '';
      let emptyText = 'No image';
      let sourceText = 'No image';

      if (mode === 'custom') {
        if (imageId > 0 && customUrl) {
          previewUrl = customUrl;
          sourceText = 'Custom image';
        } else if (planImage.url) {
          previewUrl = planImage.url;
          emptyText = 'Uses Event Plan featured image';
          sourceText = 'Falling back to Event Plan featured image';
        } else {
          emptyText = 'No custom image selected';
          sourceText = 'No custom image selected';
        }
      } else if (mode === 'event_featured') {
        if (planImage.url) {
          previewUrl = planImage.url;
          emptyText = 'Uses Event Plan featured image';
          sourceText = 'Event Plan featured image';
        } else {
          emptyText = 'No Event Plan image';
          sourceText = 'No Event Plan image';
        }
      }

      if (imageThumb) {
        if (previewUrl) {
          imageThumb.src = previewUrl;
          imageThumb.style.display = '';
        } else {
          imageThumb.removeAttribute('src');
          imageThumb.style.display = 'none';
        }
      }
      if (imageEmpty) {
        imageEmpty.textContent = emptyText;
        imageEmpty.style.display = previewUrl ? 'none' : '';
      }
      if (imageSource) {
        imageSource.textContent = sourceText;
      }
      if (imageRemove) {
        imageRemove.disabled = !(imageId > 0);
      }
    }

    imageMode.addEventListener('change', function () {
      syncTicketImagePreview();
      syncSummary();
    });

    imageSelect.addEventListener('click', function () {
      if (!window.wp || !wp.media || typeof wp.media !== 'function') {
        window.alert('WordPress media picker is not available on this screen.');
        return;
      }
      const frame = wp.media({
        title: 'Select ticket image',
        multiple: false,
        library: { type: 'image' }
      });
      frame.on('select', function () {
        const obj = frame.state().get('selection').first().toJSON();
        const imageId = Math.max(0, safeInt(obj && obj.id ? obj.id : 0));
        imageIdInput.value = String(imageId);
        imageState.customUrl = pickAttachmentPreviewUrl(obj);
        imageMode.value = 'custom';
        syncTicketImagePreview();
        syncSummary();
      });
      frame.open();
    });

    imageRemove.addEventListener('click', function () {
      imageIdInput.value = '0';
      imageState.customUrl = '';
      imageMode.value = 'none';
      syncTicketImagePreview();
      syncSummary();
    });

    function syncSummary() {
      const titleText = String(title.value || '').trim() || 'New ticket';
      const inventoryValue = String(inventory.value || '').trim();
      const inventoryLabel = (inventoryValue === '') ? '0' : String(Math.max(0, safeInt(inventoryValue)));
      const visibilityLabel = String((visibility.options[visibility.selectedIndex] && visibility.options[visibility.selectedIndex].textContent) || 'Anyone').trim();
      const imageModeValue = normalizeTicketImageMode(imageMode.value);
      const imageId = Math.max(0, safeInt(imageIdInput.value || 0));
      let imageSummary = 'No image';
      if (imageModeValue === 'custom') {
        imageSummary = imageId > 0 ? 'Custom image' : 'Custom fallback';
      } else if (imageModeValue === 'event_featured') {
        imageSummary = getPlanImageState().url ? 'Event Plan image' : 'No Event Plan image';
      }

      summaryTitle.textContent = titleText;
      summaryMeta.innerHTML = '';
      summaryMeta.appendChild(createRowSummaryStat('Status', enabled.checked ? 'Enabled' : 'Disabled', enabled.checked ? '' : 'is-muted'));
      const regularPriceText = formatSummaryMoney(price.value || '0');
      const earlyPriceValue = String(earlyPrice.value || '').trim();
      const earlyEndValue = String(earlyEnd.value || '').trim();
      const earlyEndRelativeValue = normalizeRelativeDaysValue(earlyEndRelative.value);
      const earlyCapValue = Math.max(0, safeInt(earlyCap.value || 0));
      let priceSummary = regularPriceText;
      if (earlyPriceValue && safeNumberString(earlyPriceValue) !== '0' && (earlyEndValue || earlyEndRelativeValue !== '' || earlyCapValue > 0)) {
        priceSummary = 'Early ' + formatSummaryMoney(earlyPriceValue) + (earlyCapValue > 0 ? (' up to ' + String(earlyCapValue)) : '') + ' / Regular ' + regularPriceText;
      }
      summaryMeta.appendChild(createRowSummaryStat('Price', priceSummary));
      summaryMeta.appendChild(createRowSummaryStat('Qty', inventoryLabel));
      summaryMeta.appendChild(createRowSummaryStat('Access', visibilityLabel));
      summaryMeta.appendChild(createRowSummaryStat('Image', imageSummary));
      if (ratioEnabled.checked && Math.max(0, safeInt(ratioMax.value || 0)) > 0) {
        const groupText = String(ratioGroup.value || '').trim();
        summaryMeta.appendChild(createRowSummaryStat('Ratio', String(Math.max(0, safeInt(ratioMax.value || 0))) + ' per qualifying ticket' + (groupText ? ' / group ' + groupText : '')));
      }
      if (!countsToward.checked) {
        summaryMeta.appendChild(createRowSummaryStat('Unlock', 'Off', 'is-muted'));
      }
    }

    function setExpanded(expanded) {
      row.classList.toggle('is-expanded', !!expanded);
      toggleDetails.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      toggleDetails.textContent = expanded ? 'Collapse' : 'Edit';
    }

    toggleDetails.addEventListener('click', function () {
      setExpanded(!row.classList.contains('is-expanded'));
    });

    const row1 = document.createElement('div');
    row1.className = 'vms-ticketing__row';
    row1.appendChild(enabledWrap);
    row1.appendChild(labelWrap('Ticket title', title));
    row1.appendChild(labelWrap('Regular price', price));
    row1.appendChild(labelWrap('Inventory total', inventory));
    row1.appendChild(remove);

    const rowEarly = document.createElement('div');
    rowEarly.className = 'vms-ticketing__row';
    rowEarly.appendChild(labelWrap('Early price', earlyPrice));
    rowEarly.appendChild(labelWrap('Early starts', earlyStart));
    rowEarly.appendChild(labelWrap('Starts days before event', earlyStartRelative));
    rowEarly.appendChild(labelWrap('Early ends', earlyEnd));
    rowEarly.appendChild(labelWrap('Ends days before event', earlyEndRelative));
    rowEarly.appendChild(labelWrap('Early cap (0 = no cap)', earlyCap));
    const earlyNote = document.createElement('p');
    earlyNote.className = 'description vms-ticketing__field--full';
    earlyNote.textContent = 'Optional. Use one public ticket with an early price deadline and/or a capped Early Bird quantity instead of separate Early/Regular ticket products. Relative days keep templates tied to each Event Plan date; e.g. 31 means early pricing ends 31 days before showtime.';
    rowEarly.appendChild(earlyNote);

    const row2 = document.createElement('div');
    row2.className = 'vms-ticketing__row';
    row2.appendChild(labelWrap('Description', description, 'vms-ticketing__field--full'));
    row2.appendChild(labelWrap('Who can purchase?', visibility));
    row2.appendChild(programField);
    row2.appendChild(countsWrap);
    row2.appendChild(labelWrap('Max qty per order (0 = no cap)', maxQtyPerOrder));

    const rowRatio = document.createElement('div');
    rowRatio.className = 'vms-ticketing__row';
    rowRatio.appendChild(ratioEnabledWrap);
    rowRatio.appendChild(labelWrap('Max per qualifying ticket', ratioMax));
    rowRatio.appendChild(labelWrap('Shared allowance group', ratioGroup));
    const ratioNote = document.createElement('p');
    ratioNote.className = 'description vms-ticketing__field--full';
    ratioNote.textContent = 'Optional. Example: set both 8 & Under and Youth tickets to group “youth” with max 3 so they share one combined allowance per paid adult. Qualifying tickets are rows with “Counts toward add-on unlock” checked.';
    rowRatio.appendChild(ratioNote);

    const row3 = document.createElement('div');
    row3.className = 'vms-ticketing__row';
    row3.appendChild(labelWrap('Sales start', salesStart));
    row3.appendChild(labelWrap('Start days before event', salesStartRelative));
    row3.appendChild(labelWrap('Sales end', salesEnd));
    row3.appendChild(labelWrap('End days before event', salesEndRelative));
    row3.appendChild(labelWrap('Image mode', imageMode));
    row3.appendChild(labelWrap('Image', imageWrap, 'vms-ticketing__field--full'));

    const details = document.createElement('div');
    details.className = 'vms-ticketing__row-details';
    details.id = detailsId;
    details.appendChild(row1);
    details.appendChild(rowEarly);
    details.appendChild(row2);
    details.appendChild(rowRatio);
    details.appendChild(row3);

    summary.appendChild(dragHandle);
    summary.appendChild(summaryMain);
    summary.appendChild(toggleDetails);

    row.appendChild(sortOrder);
    row.appendChild(summary);
    row.appendChild(details);

    [enabled, title, price, earlyPrice, earlyStart, earlyEnd, earlyStartRelative, earlyEndRelative, earlyCap, inventory, visibility, program, countsToward, maxQtyPerOrder, ratioEnabled, ratioMax, ratioGroup, imageMode].forEach((input) => {
      input.addEventListener('input', syncSummary);
      input.addEventListener('change', syncSummary);
    });
    [earlyStartRelative, earlyEndRelative, salesStartRelative, salesEndRelative].forEach((input) => {
      input.addEventListener('input', function () {
        applyRelativeDateFieldsForTicketRow(row);
        syncSummary();
        renderCurrentSalesEndWarning();
      });
      input.addEventListener('change', function () {
        applyRelativeDateFieldsForTicketRow(row);
        syncSummary();
        renderCurrentSalesEndWarning();
      });
    });
    [salesStart, salesEnd, salesStartRelative, salesEndRelative].forEach((input) => {
      input.addEventListener('change', renderCurrentSalesEndWarning);
      input.addEventListener('input', renderCurrentSalesEndWarning);
    });
    ratioEnabled.addEventListener('change', syncRatioState);
    syncRatioState();

    const initialImageId = Math.max(0, safeInt(imageIdInput.value || 0));
    if (initialImageId > 0) {
      resolveAttachmentPreviewUrl(initialImageId, function (url) {
        imageState.customUrl = url;
        syncTicketImagePreview();
        syncSummary();
      });
    } else {
      syncTicketImagePreview();
    }

    applyRelativeDateFieldsForTicketRow(row);
    syncSummary();
    setExpanded(!!options.expanded);
    return row;
  }

  function labelWrap(labelText, inputEl, extraClass) {
    const w = document.createElement('label');
    w.className = 'vms-ticketing__field';
    if (extraClass) {
      w.className += ' ' + String(extraClass);
    }
    const s = document.createElement('span');
    s.className = 'vms-ticketing__field-label';
    s.textContent = labelText;
    w.appendChild(s);
    w.appendChild(inputEl);
    return w;
  }

  function renderEntRow(ent, opts) {
    const e = ent || {};
    const options = opts || {};
    const elig = e.eligibility || {};

    const row = document.createElement('div');
    row.className = 'vms-ticketing__tier vms-ticketing-v2-ent-row vms-ent-admin-row';
    row.dataset.entitlementId = String(e.entitlement_id || uid());
    // entitlement_key is an internal stable key used for syncing. Preserve it so
    // "dirty" detection stays accurate (and Commit is not blocked) when the
    // operator hasn't changed the config.
    row.dataset.entitlementKey = String(e.entitlement_key || '');
    const detailsId = 'vms-ent-admin-details-' + String(row.dataset.entitlementId || uid()).replace(/[^a-zA-Z0-9_\-]/g, '');

    const dragHandle = createRowDragHandle('Drag to reorder add-on');

    const summary = document.createElement('div');
    summary.className = 'vms-ticketing__row-summary';

    const summaryMain = document.createElement('div');
    summaryMain.className = 'vms-ticketing__row-summary-main';

    const summaryTitle = document.createElement('strong');
    summaryTitle.className = 'vms-ticketing__row-title';

    const summaryMeta = document.createElement('div');
    summaryMeta.className = 'vms-ticketing__row-meta';

    summaryMain.appendChild(summaryTitle);
    summaryMain.appendChild(summaryMeta);

    const toggleDetails = document.createElement('button');
    toggleDetails.type = 'button';
    toggleDetails.className = 'button button-small vms-ticketing__row-toggle';
    toggleDetails.setAttribute('aria-controls', detailsId);

    const enabled = document.createElement('input');
    enabled.type = 'checkbox';
    enabled.className = 'vms-ticketing-v2-ent-enabled';
    enabled.checked = e.enabled === false ? false : true;

    const enabledWrap = document.createElement('label');
    enabledWrap.className = 'vms-ticketing__tier-flag';
    enabledWrap.appendChild(enabled);
    enabledWrap.appendChild(document.createTextNode('Enabled'));

    const label = document.createElement('input');
    label.type = 'text';
    label.className = 'vms-ticketing-v2-ent-label regular-text';
    label.value = plainTextValue(e.label || '');

    const shortDesc = document.createElement('input');
    shortDesc.type = 'text';
    shortDesc.className = 'vms-ticketing-v2-ent-short regular-text';
    shortDesc.value = plainTextValue(e.short_desc || '');

    const price = document.createElement('input');
    price.type = 'number';
    price.step = '0.01';
    price.min = '0';
    price.className = 'vms-ticketing-v2-ent-price';
    price.value = safeNumberString(e.price !== undefined ? e.price : '0');

    const cap = document.createElement('input');
    cap.type = 'number';
    cap.step = '1';
    cap.min = '0';
    cap.className = 'vms-ticketing-v2-ent-capacity';
    cap.value = (e.capacity === null || e.capacity === undefined || String(e.capacity) === '') ? '' : String(e.capacity);

    const selectorMode = document.createElement('select');
    selectorMode.className = 'vms-ticketing-v2-ent-selector-mode';
    [
      { value: 'stepper', label: 'Quantity stepper' },
      { value: 'checkbox', label: 'Single checkbox / reserve toggle' },
    ].forEach((optRow) => {
      const opt = document.createElement('option');
      opt.value = optRow.value;
      opt.textContent = optRow.label;
      selectorMode.appendChild(opt);
    });
    selectorMode.value = String(e.selector_mode || 'stepper') === 'checkbox' ? 'checkbox' : 'stepper';

    const minGa = document.createElement('input');
    minGa.type = 'number';
    minGa.step = '1';
    minGa.min = '0';
    minGa.className = 'vms-ticketing-v2-ent-min-ga';
    minGa.value = String(elig.min_ga_per_unit !== undefined ? elig.min_ga_per_unit : 0);

    const poolSel = document.createElement('select');
    poolSel.className = 'vms-ticketing-v2-ent-pool';
    const initialPool = toPoolKey(elig.pool_key || '');
    if (initialPool) ensurePoolKey(initialPool);
    fillPoolSelectOptions(poolSel, initialPool);

    const poolMax = document.createElement('input');
    poolMax.type = 'number';
    poolMax.step = '1';
    poolMax.min = '0';
    poolMax.className = 'vms-ticketing-v2-ent-pool-max';
    const initialPoolMax = (elig.pool_max_total !== undefined && elig.pool_max_total !== null && String(elig.pool_max_total).trim() !== '')
      ? Math.max(0, safeInt(elig.pool_max_total))
      : 0;
    poolMax.value = String(initialPoolMax);

    const newPoolBtn = document.createElement('button');
    newPoolBtn.type = 'button';
    newPoolBtn.className = 'button button-small vms-ticketing__pool-new';
    newPoolBtn.textContent = 'New…';
    newPoolBtn.addEventListener('click', function () {
      const name = window.prompt('New pool group name (example: Reserved Seating)', '');
      if (name === null) return;
      const k = ensurePoolKey(name);
      if (!k) {
        window.alert('Pool group name cannot be empty.');
        return;
      }
      refreshAllPoolSelects();
      poolSel.value = k;
      syncImageControls();
      syncSummary();
    });

    const poolWrap = document.createElement('div');
    poolWrap.className = 'vms-ticketing__pool-wrap';
    poolWrap.appendChild(poolSel);
    poolWrap.appendChild(newPoolBtn);

    const imageIdInput = document.createElement('input');
    imageIdInput.type = 'hidden';
    imageIdInput.className = 'vms-ticketing-v2-ent-image-id';
    imageIdInput.value = String(Math.max(0, safeInt(e.image_id || 0)));

    const imagePreview = document.createElement('div');
    imagePreview.className = 'vms-ticketing__image-preview';
    const imageThumb = document.createElement('img');
    imageThumb.className = 'vms-ticketing__image-thumb';
    imageThumb.alt = String(e.label || 'Entitlement image');
    imageThumb.style.display = 'none';
    const imageEmpty = document.createElement('span');
    imageEmpty.className = 'vms-ticketing__image-empty';
    imageEmpty.textContent = 'No image selected';
    imagePreview.appendChild(imageThumb);
    imagePreview.appendChild(imageEmpty);

    const imageButtons = document.createElement('div');
    imageButtons.className = 'vms-ticketing__image-actions';

    const imageSelect = document.createElement('button');
    imageSelect.type = 'button';
    imageSelect.className = 'button button-small vms-ticketing__img-select';
    imageSelect.textContent = 'Select image';

    const imageRemove = document.createElement('button');
    imageRemove.type = 'button';
    imageRemove.className = 'button button-small vms-ticketing__img-remove';
    imageRemove.textContent = 'Remove';

    const imageCopyPool = document.createElement('button');
    imageCopyPool.type = 'button';
    imageCopyPool.className = 'button button-small vms-ticketing__img-copy-pool';
    imageCopyPool.textContent = 'Copy to pool group';

    imageButtons.appendChild(imageSelect);
    imageButtons.appendChild(imageRemove);
    imageButtons.appendChild(imageCopyPool);

    const imageWrap = document.createElement('div');
    imageWrap.className = 'vms-ticketing__image-wrap';
    imageWrap.appendChild(imagePreview);
    imageWrap.appendChild(imageButtons);
    imageWrap.appendChild(imageIdInput);

    const imageSource = document.createElement('span');
    imageSource.className = 'vms-ticketing__image-source';
    imageWrap.appendChild(imageSource);

    function syncImageControls() {
      const imageId = Math.max(0, safeInt(imageIdInput.value || 0));
      const hasPool = !!toPoolKey(poolSel.value || '');
      imageCopyPool.style.display = hasPool ? '' : 'none';
      imageCopyPool.disabled = !(imageId > 0 && hasPool);
      imageRemove.disabled = !(imageId > 0);
      if (!hasPool && String(poolMax.value || '').trim() === '') {
        poolMax.value = '0';
      }
      imageSource.textContent = imageId > 0 ? 'Custom image' : 'No image selected';
    }

    imageSelect.addEventListener('click', function () {
      if (!window.wp || !wp.media || typeof wp.media !== 'function') {
        window.alert('WordPress media picker is not available on this screen.');
        return;
      }
      const frame = wp.media({
        title: 'Select entitlement image',
        multiple: false,
        library: { type: 'image' }
      });
      frame.on('select', function () {
        const obj = frame.state().get('selection').first().toJSON();
        const imageId = Math.max(0, safeInt(obj && obj.id ? obj.id : 0));
        const imageUrl = pickAttachmentPreviewUrl(obj);
        setEntRowImage(row, imageId, imageUrl);
        syncImageControls();
        syncSummary();
      });
      frame.open();
    });

    imageRemove.addEventListener('click', function () {
      setEntRowImage(row, 0, '');
      syncImageControls();
      syncSummary();
    });

    imageCopyPool.addEventListener('click', function () {
      const imageId = Math.max(0, safeInt(imageIdInput.value || 0));
      const poolKey = toPoolKey(poolSel.value || '');
      if (!(imageId > 0) || !poolKey || !v2Editor) return;

      const rows = v2Editor.querySelectorAll('.vms-ticketing__tier[data-entitlement-id]');
      const applyToPoolRows = function (srcUrl) {
        rows.forEach((targetRow) => {
          const targetPool = toPoolKey(targetRow.querySelector('.vms-ticketing-v2-ent-pool')?.value || '');
          if (targetPool !== poolKey) return;
          setEntRowImage(targetRow, imageId, srcUrl);
          try {
            targetRow.dispatchEvent(new Event('vms:summary-refresh'));
          } catch (e) {
            // ignore
          }
        });
      };

      const currentSrc = String((row.querySelector('.vms-ticketing__image-thumb') || {}).src || '');
      if (currentSrc) {
        applyToPoolRows(currentSrc);
        syncSummary();
        return;
      }
      resolveAttachmentPreviewUrl(imageId, function (srcUrl) {
        applyToPoolRows(srcUrl);
        syncSummary();
      });
    });

    const allowWithout = document.createElement('input');
    allowWithout.type = 'checkbox';
    allowWithout.className = 'vms-ticketing-v2-ent-allow-without';
    allowWithout.checked = !!elig.allow_without_ga;

    const allowWrap = document.createElement('label');
    allowWrap.className = 'vms-ticketing__tier-flag vms-ent-admin-allow';
    allowWrap.appendChild(allowWithout);
    allowWrap.appendChild(document.createTextNode('Allow without GA'));

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'button button-small vms-ent-admin-remove';
    remove.textContent = 'Remove';
    remove.addEventListener('click', () => row.remove());

    const moreTextarea = document.createElement('textarea');
    moreTextarea.className = 'vms-ticketing-v2-ent-more';
    moreTextarea.rows = 4;
    moreTextarea.value = String(e.more_info || '');

    function syncSummary() {
      const labelText = String(label.value || '').trim() || 'New add-on';
      const poolKey = toPoolKey(poolSel.value || '');
      const imageId = Math.max(0, safeInt(imageIdInput.value || 0));
      const capValue = String(cap.value || '').trim();

      summaryTitle.textContent = labelText;
      summaryMeta.innerHTML = '';
      summaryMeta.appendChild(createRowSummaryStat('Status', enabled.checked ? 'Enabled' : 'Disabled', enabled.checked ? '' : 'is-muted'));
      summaryMeta.appendChild(createRowSummaryStat('Price', formatSummaryMoney(price.value || '0')));
      summaryMeta.appendChild(createRowSummaryStat('Capacity', capValue === '' ? '0' : String(Math.max(0, safeInt(capValue)))));
      summaryMeta.appendChild(createRowSummaryStat('Input', selectorMode.value === 'checkbox' ? 'Checkbox' : 'Stepper'));
      summaryMeta.appendChild(createRowSummaryStat('Pool', poolKey ? poolKeyLabel(poolKey) : 'None'));
      summaryMeta.appendChild(createRowSummaryStat('Image', imageId > 0 ? 'Custom image' : 'No image'));
      if (Math.max(0, safeInt(minGa.value || 0)) > 0) {
        summaryMeta.appendChild(createRowSummaryStat('Unlock', String(Math.max(0, safeInt(minGa.value || 0))) + ' GA'));
      }
    }

    function setExpanded(expanded) {
      row.classList.toggle('is-expanded', !!expanded);
      toggleDetails.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      toggleDetails.textContent = expanded ? 'Collapse' : 'Edit';
    }

    toggleDetails.addEventListener('click', function () {
      setExpanded(!row.classList.contains('is-expanded'));
    });

    poolSel.addEventListener('change', function () {
      syncImageControls();
      if (toPoolKey(poolSel.value || '') && String(poolMax.value || '').trim() === '') {
        poolMax.value = '1';
      }
      if (!toPoolKey(poolSel.value || '') && safeInt(poolMax.value || 0) < 0) {
        poolMax.value = '0';
      }
      syncSummary();
    });

    const row1 = document.createElement('div');
    row1.className = 'vms-ticketing__row';
    row1.appendChild(enabledWrap);
    row1.appendChild(labelWrap('Label', label));
    row1.appendChild(labelWrap('Short description', shortDesc));
    row1.appendChild(labelWrap('Price', price));
    row1.appendChild(labelWrap('Capacity', cap));
    row1.appendChild(remove);

    const row2 = document.createElement('div');
    row2.className = 'vms-ticketing__row';
    row2.appendChild(labelWrap('Add-on control', selectorMode));
    row2.appendChild(labelWrap('Min GA per unit', minGa));
    row2.appendChild(labelWrap('Pool group', poolWrap));
    row2.appendChild(labelWrap('Pool max total (0 = unlimited)', poolMax));
    row2.appendChild(allowWrap);

    const row3 = document.createElement('div');
    row3.className = 'vms-ticketing__row';
    row3.appendChild(labelWrap('Image', imageWrap, 'vms-ticketing__field--full'));
    row3.appendChild(labelWrap('More info details', moreTextarea, 'vms-ticketing__field--full vms-ticketing__field--more'));

    const details = document.createElement('div');
    details.className = 'vms-ticketing__row-details';
    details.id = detailsId;
    details.appendChild(row1);
    details.appendChild(row2);
    details.appendChild(row3);

    summary.appendChild(dragHandle);
    summary.appendChild(summaryMain);
    summary.appendChild(toggleDetails);

    row.appendChild(summary);
    row.appendChild(details);
    row.addEventListener('vms:summary-refresh', function () {
      syncImageControls();
      syncSummary();
    });

    [enabled, label, shortDesc, price, cap, selectorMode, minGa, poolMax, allowWithout, moreTextarea].forEach((input) => {
      input.addEventListener('input', syncSummary);
      input.addEventListener('change', syncSummary);
    });

    const initialImageId = Math.max(0, safeInt(imageIdInput.value || 0));
    if (initialImageId > 0) {
      resolveAttachmentPreviewUrl(initialImageId, function (url) {
        setEntRowImage(row, initialImageId, url);
        syncSummary();
      });
    } else {
      setEntRowImage(row, 0, '');
    }
    syncImageControls();
    syncSummary();
    setExpanded(!!options.expanded);

    return row;
  }

  function getV2ConfigFromUI() {
    if (!v2Editor) return null;

    const cfg = {
      version: 2,
      mode: v2ModeSel ? String(v2ModeSel.value || 'read_only') : 'read_only',
      // Ticketing v2 is currently implemented using The Events Calendar tickets (Woo provider).
      // Include this so the client-side config object matches the server-normalized config,
      // preventing false "config changed since last Preview" loops.
      provider: 'tec_tickets_woo',
      tickets: [],
      ga: {
        enabled: true,
        label: 'GA Admission',
        price: '0',
        early_price: '',
        early_price_start: '',
        early_price_end: '',
        early_price_start_relative_days: '',
        early_price_end_relative_days: '',
        early_price_cap: 0,
        capacity: 0,
        sales_start: '',
        sales_end: '',
        sales_start_relative_days: '',
        sales_end_relative_days: '0',
      },
      entitlements: [],
      square: { ga: { mode: 'none', item_id: '', variation_id: '' } },
    };

    const ticketRows = v2Editor.querySelectorAll('.vms-ticketing-v2-ticket-row');
    ticketRows.forEach((row, idx) => {
      applyRelativeDateFieldsForTicketRow(row);
      const key = String(row.dataset.ticketKey || '').trim() || ('ticket_' + uid());
      const enabled = !!row.querySelector('.vms-ticketing-v2-ticket-enabled')?.checked;
      const title = String(row.querySelector('.vms-ticketing-v2-ticket-title')?.value || '').trim();
      const description = String(row.querySelector('.vms-ticketing-v2-ticket-description')?.value || '').trim();
      const price = safeNumberString(row.querySelector('.vms-ticketing-v2-ticket-price')?.value || '0');
      const earlyPriceRaw = String(row.querySelector('.vms-ticketing-v2-ticket-early-price')?.value || '').trim();
      const early_price = earlyPriceRaw ? safeNumberString(earlyPriceRaw) : '';
      const early_price_start = fromDatetimeLocal(String(row.querySelector('.vms-ticketing-v2-ticket-early-start')?.value || '').trim());
      const early_price_end = fromDatetimeLocal(String(row.querySelector('.vms-ticketing-v2-ticket-early-end')?.value || '').trim());
      const early_price_start_relative_days = normalizeRelativeDaysValue(row.querySelector('.vms-ticketing-v2-ticket-early-start-relative')?.value || '');
      const early_price_end_relative_days = normalizeRelativeDaysValue(row.querySelector('.vms-ticketing-v2-ticket-early-end-relative')?.value || '');
      const early_price_cap = Math.max(0, safeInt(row.querySelector('.vms-ticketing-v2-ticket-early-cap')?.value || 0));
      const invRaw = String(row.querySelector('.vms-ticketing-v2-ticket-inventory')?.value || '').trim();
      const inventory_total = invRaw === '' ? 0 : Math.max(0, safeInt(invRaw));
      const visibility_mode = String(row.querySelector('.vms-ticketing-v2-ticket-visibility')?.value || 'public').trim() || 'public';
      const verified_program = String(row.querySelector('.vms-ticketing-v2-ticket-program')?.value || '').trim();
      const counts_toward_unlock = !!row.querySelector('.vms-ticketing-v2-ticket-counts')?.checked;
      const max_qty_per_order = Math.max(0, safeInt(row.querySelector('.vms-ticketing-v2-ticket-max-qty')?.value || 0));
      const ratioRuleChecked = !!row.querySelector('.vms-ticketing-v2-ticket-ratio-enabled')?.checked;
      const ratioRuleMax = Math.max(0, safeInt(row.querySelector('.vms-ticketing-v2-ticket-ratio-max')?.value || 0));
      const ratio_rule_enabled = ratioRuleChecked && ratioRuleMax > 0;
      const ratio_rule_max_per_qualifying = ratio_rule_enabled ? ratioRuleMax : 0;
      const ratio_rule_group = ratio_rule_enabled ? String(row.querySelector('.vms-ticketing-v2-ticket-ratio-group')?.value || '').trim().toLowerCase().replace(/[^a-z0-9_-]+/g, '-') : '';
      const sortRaw = String(row.querySelector('.vms-ticketing-v2-ticket-sort-order')?.value || '').trim();
      const sort_order = Math.max(1, sortRaw === '' ? ((idx + 1) * 10) : safeInt(sortRaw));
      const sales_start = fromDatetimeLocal(String(row.querySelector('.vms-ticketing-v2-ticket-sales-start')?.value || '').trim());
      const sales_end = fromDatetimeLocal(clampLocalDatetimeToEventEnd(String(row.querySelector('.vms-ticketing-v2-ticket-sales-end')?.value || '').trim()));
      const sales_start_relative_days = normalizeRelativeDaysValue(row.querySelector('.vms-ticketing-v2-ticket-sales-start-relative')?.value || '');
      const sales_end_relative_days = normalizeRelativeDaysValue(row.querySelector('.vms-ticketing-v2-ticket-sales-end-relative')?.value || '');
      const image_mode = normalizeTicketImageMode(row.querySelector('.vms-ticketing-v2-ticket-image-mode')?.value || 'event_featured');
      const image_id = (image_mode === 'custom')
        ? Math.max(0, safeInt(row.querySelector('.vms-ticketing-v2-ticket-image-id')?.value || 0))
        : 0;

      cfg.tickets.push({
        enabled,
        ticket_key: key,
        title,
        description,
        price,
        early_price,
        early_price_start,
        early_price_end,
        early_price_start_relative_days,
        early_price_end_relative_days,
        early_price_cap,
        inventory_total,
        visibility_mode,
        verified_program,
        counts_toward_unlock,
        max_qty_per_order,
        ratio_rule_enabled,
        ratio_rule_max_per_qualifying,
        ratio_rule_qualifier_mode: 'counts_toward_unlock',
        ratio_rule_group,
        sort_order,
        sales_start,
        sales_end,
        sales_start_relative_days,
        sales_end_relative_days,
        image_mode,
        image_id,
      });
    });

    if (!cfg.tickets.length) {
      const defaults = getNewTicketSalesWindowDefaults();
      cfg.tickets.push({
        enabled: true,
        ticket_key: 'ga',
        title: 'GA Admission',
        description: '',
        price: '0',
        early_price: '',
        early_price_start: '',
        early_price_end: '',
        early_price_start_relative_days: '',
        early_price_end_relative_days: '',
        early_price_cap: 0,
        inventory_total: 0,
        visibility_mode: 'public',
        verified_program: '',
        counts_toward_unlock: true,
        max_qty_per_order: 0,
        ratio_rule_enabled: false,
        ratio_rule_max_per_qualifying: 0,
        ratio_rule_qualifier_mode: 'counts_toward_unlock',
        ratio_rule_group: '',
        sort_order: 10,
        sales_start: defaults.sales_start,
        sales_end: defaults.sales_end,
        sales_start_relative_days: defaults.sales_start_relative_days,
        sales_end_relative_days: defaults.sales_end_relative_days,
        image_mode: 'event_featured',
        image_id: 0,
      });
    }

    let primaryTicket = cfg.tickets[0];
    for (let i = 0; i < cfg.tickets.length; i += 1) {
      const ticket = cfg.tickets[i];
      if (ticket && ticket.enabled && ticket.counts_toward_unlock) {
        primaryTicket = ticket;
        break;
      }
    }
    cfg.ga = {
      enabled: !!(primaryTicket && primaryTicket.enabled),
      label: String((primaryTicket && primaryTicket.title) || 'GA Admission'),
      price: String((primaryTicket && primaryTicket.price) || '0'),
      early_price: String((primaryTicket && primaryTicket.early_price) || ''),
      early_price_start: String((primaryTicket && primaryTicket.early_price_start) || ''),
      early_price_end: String((primaryTicket && primaryTicket.early_price_end) || ''),
      early_price_start_relative_days: normalizeRelativeDaysValue(primaryTicket && primaryTicket.early_price_start_relative_days),
      early_price_end_relative_days: normalizeRelativeDaysValue(primaryTicket && primaryTicket.early_price_end_relative_days),
      early_price_cap: String((primaryTicket && primaryTicket.early_price_cap) || '0'),
      capacity: Math.max(0, safeInt((primaryTicket && primaryTicket.inventory_total) || 0)),
      sales_start: String((primaryTicket && primaryTicket.sales_start) || ''),
      sales_end: String((primaryTicket && primaryTicket.sales_end) || ''),
      sales_start_relative_days: normalizeRelativeDaysValue(primaryTicket && primaryTicket.sales_start_relative_days),
      sales_end_relative_days: normalizeRelativeDaysValue(primaryTicket && primaryTicket.sales_end_relative_days),
    };

    const rows = v2Editor.querySelectorAll('.vms-ticketing__tier[data-entitlement-id]');
    rows.forEach((row) => {
      const entitlement_id = String(row.dataset.entitlementId || '').trim() || uid();
      const entitlement_key = String(row.dataset.entitlementKey || '').trim();
      const enabled = !!row.querySelector('.vms-ticketing-v2-ent-enabled')?.checked;
      const label = String(row.querySelector('.vms-ticketing-v2-ent-label')?.value || '').trim();
      const short_desc = String(row.querySelector('.vms-ticketing-v2-ent-short')?.value || '').trim();
      const more_info = String(row.querySelector('.vms-ticketing-v2-ent-more')?.value || '').trim();
      const image_id = Math.max(0, safeInt(row.querySelector('.vms-ticketing-v2-ent-image-id')?.value || 0));
      const price = safeNumberString(row.querySelector('.vms-ticketing-v2-ent-price')?.value || '0');
      const capRaw = String(row.querySelector('.vms-ticketing-v2-ent-capacity')?.value || '').trim();
      const capacity = capRaw === '' ? 0 : safeInt(capRaw);
      const selector_mode = String(row.querySelector('.vms-ticketing-v2-ent-selector-mode')?.value || 'stepper').trim() === 'checkbox' ? 'checkbox' : 'stepper';
      const min_ga_per_unit = safeInt(row.querySelector('.vms-ticketing-v2-ent-min-ga')?.value || 0);
      const pool_key = toPoolKey(row.querySelector('.vms-ticketing-v2-ent-pool')?.value || '');
      const poolMaxRaw = String(row.querySelector('.vms-ticketing-v2-ent-pool-max')?.value || '').trim();
      const pool_max_total = (poolMaxRaw === '')
        ? 0
        : Math.max(0, safeInt(poolMaxRaw));
      const pool_max_explicit = pool_max_total > 0 ? 1 : 0;
      const allow_without_ga = !!row.querySelector('.vms-ticketing-v2-ent-allow-without')?.checked;

      cfg.entitlements.push({
        entitlement_id,
        entitlement_key,
        enabled,
        label,
        short_desc,
        more_info,
        image_id,
        price,
        capacity,
        selector_mode,
        eligibility: {
          min_ga_per_unit,
          pool_key,
          pool_max_total,
          pool_max_explicit,
          max_units_per_order: 0,
          max_units_per_ga: 0,
          allow_without_ga,
        },
        square: { mode: 'none', item_id: '', variation_id: '' },
      });
    });

    return cfg;
  }

  function renderV2Preview(data) {
    if (!v2PreviewWrap) return;
    v2PreviewWrap.classList.remove('vms-hidden');
    v2PreviewWrap.style.display = 'block';

    const d = data || {};
    const title = d.title ? String(d.title) : 'Preview';
    const blocked = !!d.blocked;
    const warnings = Array.isArray(d.warnings) ? d.warnings : [];
    const actions = Array.isArray(d.actions) ? d.actions : [];

    v2PreviewWrap.innerHTML = '';

    const head = document.createElement('div');
    head.className = 'vms-ticketing__sync-head';
    head.innerHTML = `<strong>${title}</strong> <span class="description">(${actions.length} actions)</span>`;
    v2PreviewWrap.appendChild(head);


// Always show which TEC event tickets will attach to.
if (d.tec_event_id) {
  const evBox = document.createElement('div');
  evBox.className = 'vms-ticketing__evinfo vms-notice vms-notice--info';

  const titleSpan = document.createElement('span');
  const evTitle = d.tec_event_title ? String(d.tec_event_title) : 'TEC Event';

  // Make the event title itself clickable (to View) to reduce confusion.
  let evTitleHtml = escHtml(evTitle);
  if (d.tec_event_view_url) {
    evTitleHtml = `<a href="${escAttr(String(d.tec_event_view_url))}" target="_blank" rel="noopener">${escHtml(evTitle)}</a>`;
  }

  titleSpan.innerHTML = `<strong>Linked TEC Event:</strong> ${evTitleHtml} <span class="description">(WP #${escHtml(String(d.tec_event_id))})</span>`;
  evBox.appendChild(titleSpan);

  const links = [];
  if (d.tec_event_view_url) {
    links.push(`<a href="${escAttr(String(d.tec_event_view_url))}" target="_blank" rel="noopener">View</a>`);
  }
  if (d.tec_event_edit_url) {
    links.push(`<a href="${escAttr(String(d.tec_event_edit_url))}" target="_blank" rel="noopener">Edit</a>`);
  }
  if (links.length) {
    const linkSpan = document.createElement('span');
    linkSpan.className = 'vms-ticketing__evinfo-links';
    linkSpan.innerHTML = links.join(' · ');
    evBox.appendChild(linkSpan);
  }

  v2PreviewWrap.appendChild(evBox);
}

    if (data && data.created_calendar_event) {
      const infoBox = document.createElement('div');
      infoBox.className = 'notice notice-success vms-notice vms-notice--success';
      const p = document.createElement('p');
      p.innerHTML = '<strong>Calendar event created and linked automatically.</strong> It was created as a draft (unpublished). You can publish it when the Event Plan is published.';
      infoBox.appendChild(p);
      v2PreviewWrap.appendChild(infoBox);
    }

    if (warnings.length) {
      const warnBox = document.createElement('div');
      warnBox.className = blocked
        ? 'notice notice-error vms-notice vms-notice--critical'
        : 'notice notice-warning vms-notice vms-notice--warning';

      const wTitle = document.createElement('div');
      wTitle.style.fontWeight = '600';
      wTitle.textContent = blocked ? 'Blocked issues' : 'Warnings';
      warnBox.appendChild(wTitle);

      const wList = document.createElement('ul');
      warnings.forEach((w) => {
        const li = document.createElement('li');
        li.textContent = String(w || '');
        wList.appendChild(li);
      });
      warnBox.appendChild(wList);

      v2PreviewWrap.appendChild(warnBox);
    }

    const ul = document.createElement('ul');
    ul.className = 'vms-ticketing__sync-list';

    actions.forEach((a) => {
      const li = document.createElement('li');

      const scope = a && a.scope ? String(a.scope) : 'item';
      const op = a && (a.operation || a.action) ? String(a.operation || a.action) : 'noop';
      const note = a && (a.reason || a.notes) ? humanizeCommitActionReason(a.reason || a.notes) : '';

      let target = '';
      if (scope === 'ticket') {
        const lbl = (a && a.label) ? String(a.label) : '';
        const tkey = (a && a.ticket_key) ? String(a.ticket_key) : '';
        target = lbl ? `“${lbl}”` : (tkey || 'Ticket');
        if (a && a.woo_product_id) target += ` (product #${a.woo_product_id})`;
      } else if (scope === 'ga') {
        target = 'GA';
        if (a && a.woo_product_id) target += ` (product #${a.woo_product_id})`;
      } else if (scope === 'entitlement') {
        const lbl = (a && a.label) ? String(a.label) : '';
        const eid = (a && a.entitlement_id) ? String(a.entitlement_id) : '';
        target = lbl ? `“${lbl}”` : (eid ? eid : 'Entitlement');
        if (a && a.woo_product_id) target += ` (product #${a.woo_product_id})`;
      } else {
        target = a && a.target ? String(a.target) : '';
      }

      li.textContent = `${scope.toUpperCase()}: ${op.toUpperCase()} ${target} — ${note}`;
      ul.appendChild(li);
    });

    v2PreviewWrap.appendChild(ul);

    if (blocked) {
      const warn = document.createElement('div');
      warn.className = 'notice notice-error vms-notice vms-notice--critical';
      warn.textContent = 'Preview is blocked. Resolve the issues above, then preview again.';
      v2PreviewWrap.appendChild(warn);
    }
  }

  if (v2Editor) {
    const initialCfg = parseJSONAttr(v2Editor.dataset.initialConfig, {});
    const initialSync = parseJSONAttr(v2Editor.dataset.initialSync, {});
    const cfgExists = String(v2Editor.dataset.configExists || '0') === '1';
    const defaultTplId = String(v2Editor.dataset.defaultTemplateId || '').trim();
    const defaultTplName = String(v2Editor.dataset.defaultTemplateName || '').trim();
    let renderedInitialEditor = false;
    let handledAutoDefault = false;
    
    const ticketingEffective = String(v2Editor.dataset.ticketingEffective || '1') === '1';
    if (!ticketingEffective) {
      if (v2PreviewBtn) v2PreviewBtn.disabled = true;
      if (v2CommitBtn) v2CommitBtn.disabled = true;
      setV2Note('Ticketing is disabled for this Event Plan. Turn on "Tickets for this event" above, update the plan, then return to run Preview and Commit.', 'error');
    }


    if (!cfgExists) {
      let suppressAutoDefault = false;
      try { suppressAutoDefault = (sessionStorage.getItem('vms_v2_suppress_auto_default_' + planId) === '1'); } catch (e) {}

      if (!suppressAutoDefault && defaultTplId && planId > 0) {
        handledAutoDefault = true;
        // Render the blank editor immediately, then overwrite once the default template is applied.
        // Do not return from this initializer: the Save/Preview/Commit listeners below must still bind.
        renderV2(initialCfg, { useDefaultSalesWindow: true });
        renderedInitialEditor = true;
        if (v2TplSel) {
          v2TplSel.value = defaultTplId;
          try { v2TplSel.dispatchEvent(new Event('change')); } catch (e) {}
        }
        const initialSalesEndIssues = getCurrentSalesEndIssues();
        const renderedInitialConfigStillStale = !!(initialSalesEndIssues && initialSalesEndIssues.targetComparable && initialSalesEndIssues.issues && initialSalesEndIssues.issues.length);
        if (renderedInitialConfigStillStale && renderTemplateSalesEndGuardrail(defaultTplId, { autoDefault: true, templateName: defaultTplName || defaultTplId })) {
          setV2Note('Default template needs review before apply. Choose whether to keep or reset the Sales end dates below.', 'warning');
        } else {
          // Server-side default config already normalizes stale template Sales end
          // values for this Event Plan. Save that repaired default directly instead
          // of showing the operator a stale warning based on the raw template row.
          setV2Note('Saving default template “' + (defaultTplName || defaultTplId) + '”…', 'info');
          saveV2Config({ quiet: true, timeoutMs: 60000 })
            .then(() => {
              setV2Note('Default template applied. Tickets are created or updated only after Preview → Commit.', 'info');
              setV2Msg('Default template applied.', 'success');
            })
            .catch(() => {
              setV2Note('Default template dates were repaired for this event, but the config was not auto-saved. Click Save config before previewing.', 'warning');
            });
        }
      }
      if (!handledAutoDefault) {
        setV2Note('No saved Ticketing config for this plan yet. Pick a template or click “Initialize from legacy add-ons”.', 'info');
      }
    } else {
      const hasCommit = !!(initialSync && typeof initialSync === 'object' && initialSync.last_commit);
      if (!hasCommit) {
        setV2Note('Config is saved. Preview is read-only; Commit creates or updates the calendar event, tickets, and add-ons.', 'info');
      } else {
        setV2Note('', '');
      }
    }

    if (!renderedInitialEditor) {
      renderV2(initialCfg, { useDefaultSalesWindow: !cfgExists });
    }
    markDefaultTemplateInSelect(defaultTplId);
    setDefaultTemplateLabel(defaultTplId, defaultTplName);
  }

  if (v2Editor) {
    v2Editor.addEventListener('input', function () {
      renderCurrentSalesEndWarning();
    });
    v2Editor.addEventListener('change', function () {
      renderCurrentSalesEndWarning();
    });

    const showDateInput = document.getElementById('vms_event_date');
    const showStartInput = document.getElementById('vms_start_time');
    const showEndInput = document.getElementById('vms_end_time');
    [showDateInput, showStartInput, showEndInput].forEach((input) => {
      if (!input) return;
      input.addEventListener('input', function () {
        refreshAllRelativeTicketDateFields();
        renderCurrentSalesEndWarning();
      });
      input.addEventListener('change', function () {
        refreshAllRelativeTicketDateFields();
        renderCurrentSalesEndWarning();
      });
    });
  }

  if (v2TplSel && v2TplApplyBtn) {
    v2TplSel.addEventListener('change', function () {
      const v = String(v2TplSel.value || '').trim();
      clearGuardrail(v2TemplateGuardrailWrap);
      v2TplApplyBtn.disabled = !v;
      if (v2TplSetDefaultBtn && v2Editor) {
        const curDef = String(v2Editor.dataset.defaultTemplateId || '').trim();
        v2TplSetDefaultBtn.disabled = !v || (v === curDef);
      }
    });
  }

  if (v2TplApplyBtn && v2TplSel && v2Editor && planId > 0) {
    v2TplApplyBtn.addEventListener('click', function () {
      const templateId = String(v2TplSel.value || '').trim();
      if (!templateId) {
        setV2Msg('Select a template first.', 'error');
        return;
      }
      if (renderTemplateSalesEndGuardrail(templateId, { templateName: getTemplateNameById(templateId) })) {
        setV2Note('Review the Sales end warning below before applying this template.', 'warning');
        return;
      }
      if (!window.confirm('Apply this template to this Event Plan? This overwrites the saved v2 config for this plan.')) {
        return;
      }
      applyTemplateToPlan(templateId, {
        noteText: 'Template applied. Tickets are created or updated only after Preview → Commit.',
        successText: 'Template applied.',
      });
    });
  }


  if (v2TplSetDefaultBtn && v2TplSel && v2Editor) {
    v2TplSetDefaultBtn.addEventListener('click', function () {
      const templateId = String(v2TplSel.value || '').trim();
      if (!templateId) {
        setV2Msg('Select a template first.', 'error');
        return;
      }
      v2TplSetDefaultBtn.disabled = true;
      setV2Msg('Setting default template…', 'working');
      postJSON('vms_ticketing_v2_set_default_template', { template_id: templateId })
        .then((res) => {
          if (!res || !res.success) {
            setV2Msg((res && res.data && res.data.message) ? res.data.message : 'Set default failed.', 'error');
            const curDef = String(v2Editor.dataset.defaultTemplateId || '').trim();
            v2TplSetDefaultBtn.disabled = !templateId || (templateId === curDef);
            return;
          }
          const did = (res.data && res.data.default_template_id) ? String(res.data.default_template_id) : '';
          const dname = (res.data && res.data.default_template_name) ? String(res.data.default_template_name) : '';
          v2Editor.dataset.defaultTemplateId = did;
          v2Editor.dataset.defaultTemplateName = dname;
          markDefaultTemplateInSelect(did);
          setDefaultTemplateLabel(did, dname);
          setV2Msg('Default template updated.', 'success');
          v2TplSetDefaultBtn.disabled = true;
        })
        .catch(() => {
          setV2Msg('Set default failed.', 'error');
          const curDef = String(v2Editor.dataset.defaultTemplateId || '').trim();
          v2TplSetDefaultBtn.disabled = !templateId || (templateId === curDef);
        });
    });
  }

  if (v2TplClearDefaultBtn && v2Editor) {
    v2TplClearDefaultBtn.addEventListener('click', function () {
      if (!window.confirm('Clear the default Ticketing template?')) return;
      v2TplClearDefaultBtn.disabled = true;
      setV2Msg('Clearing default template…', 'working');
      postJSON('vms_ticketing_v2_set_default_template', { template_id: '' })
        .then((res) => {
          v2TplClearDefaultBtn.disabled = false;
          if (!res || !res.success) {
            setV2Msg((res && res.data && res.data.message) ? res.data.message : 'Clear default failed.', 'error');
            return;
          }
          v2Editor.dataset.defaultTemplateId = '';
          v2Editor.dataset.defaultTemplateName = '';
          markDefaultTemplateInSelect('');
          setDefaultTemplateLabel('', '');
          if (v2TplSetDefaultBtn && v2TplSel) {
            const v = String(v2TplSel.value || '').trim();
            v2TplSetDefaultBtn.disabled = !v;
          }
          setV2Msg('Default template cleared.', 'success');
        })
        .catch(() => {
          v2TplClearDefaultBtn.disabled = false;
          setV2Msg('Clear default failed.', 'error');
        });
    });
  }
  if (v2TplSaveBtn && v2TplNameInp && v2Editor && planId > 0) {
    v2TplSaveBtn.addEventListener('click', function () {
      const name = String(v2TplNameInp.value || '').trim();
      if (!name) {
        setV2Msg('Enter a template name first.', 'error');
        return;
      }
      const cfg = getV2ConfigFromUI();
      if (!cfg) {
        setV2Msg('Could not read config from the page.', 'error');
        return;
      }
      v2TplSaveBtn.disabled = true;
      setV2Msg('Saving template…', 'working');
      postJSON('vms_ticketing_v2_save_template', { plan_id: planId, name: name, config: cfg })
        .then((res) => {
          if (!res || !res.success) {
            setV2Msg((res && res.data && res.data.message) ? res.data.message : 'Save template failed.', 'error');
            v2TplSaveBtn.disabled = false;
            return;
          }
          if (v2TplSel && res.data && Array.isArray(res.data.templates)) {
            rebuildTemplateSelectOptions(res.data.templates, String(v2TplSel.value || '').trim());
          }
          v2TplNameInp.value = '';
          setV2Msg('Template saved.', 'success');
          v2TplSaveBtn.disabled = false;
        })
        .catch(() => {
          setV2Msg('Save template failed.', 'error');
          v2TplSaveBtn.disabled = false;
        });
    });
  }

  function saveV2Config(opts) {
    const o = opts || {};
    if (!v2Editor) return Promise.reject(new Error('missing_editor'));
    const cfg = getV2ConfigFromUI();
    if (!cfg) {
      if (!o.quiet) setV2Msg('Could not read Ticketing config from the page.', 'error');
      return Promise.reject(new Error('missing_cfg'));
    }
    if (!(planId > 0)) {
      if (!o.quiet) setV2Msg('Save the Event Plan first (Save Draft is fine), then try again.', 'error');
      return Promise.reject(new Error('missing_plan_id'));
    }

    const saveTimeoutMs = parseInt((o && o.timeoutMs) || 0, 10) || 60000;
    return postJSON('vms_ticketing_v2_save_config', { plan_id: planId, config: cfg, return_config: 0 }, saveTimeoutMs).then((res) => {
      if (!res || !res.success) {
        const msg = (res && res.data && res.data.message) ? res.data.message : 'Save config failed.';
        if (!o.quiet) setV2Msg(humanizeV2Message(msg), 'error');
        throw new Error('save_failed');
      }
      persistRequestedSectionTarget('ticketing_v2');
      const normalized = (res.data && res.data.config) ? res.data.config : cfg;
      v2Editor.dataset.initialConfig = JSON.stringify(normalized);
      v2Editor.dataset.configExists = '1';
      if (res.data && res.data.config) {
        renderV2(normalized);
      }
      clearV2PreviewState();
      if (!o.quiet) {
        const successMsg = (o.successMsg && String(o.successMsg).trim()) ? String(o.successMsg).trim() : 'Config saved.';
        setV2Msg(successMsg, 'success');
      }
      return normalized;
    });
  }

  if (ticketUiOverridesSaveBtn) {
    bindTicketUiOverrideDirtyTracking();
    bindTicketUiOverrideMainFormGuard();

    ticketUiOverridesSaveBtn.addEventListener('click', function (event) {
      if (event) {
        event.preventDefault();
      }
      saveTicketUiOverrides({
        workingMsg: 'Saving public UI overrides…',
        successMsg: 'Public UI overrides saved.',
      }).catch(() => {});
    });
  }

  if (v2TplClearBtn && v2Editor && planId > 0) {
    v2TplClearBtn.addEventListener('click', function () {
      if (!window.confirm('Clear Ticketing config for this Event Plan? This does not delete any tickets.')) return;
      v2TplClearBtn.disabled = true;
      setV2Msg('Clearing config…', 'working');
      postJSON('vms_ticketing_v2_clear_config', { plan_id: planId })
        .then((res) => {
          if (!res || !res.success) {
            setV2Msg((res && res.data && res.data.message) ? res.data.message : 'Clear failed.', 'error');
            v2TplClearBtn.disabled = false;
            return;
          }
          const normalized = res.data && res.data.config ? res.data.config : {};
          v2Editor.dataset.initialConfig = JSON.stringify(normalized);
          try { sessionStorage.setItem('vms_v2_suppress_auto_default_' + planId, '1'); } catch (e) {}
          v2Editor.dataset.configExists = '0';
          renderV2(normalized);
          clearGuardrail(v2TemplateGuardrailWrap);
          try { sessionStorage.removeItem('vms_v2_suppress_auto_default_' + planId); } catch (e) {}
          clearV2PreviewState();
          setV2Note('No saved Ticketing config for this plan yet. Pick a template or click “Initialize from legacy add-ons”.', 'info');
          setV2Msg('Config cleared.', 'success');
          v2TplClearBtn.disabled = false;
        })
        .catch(() => {
          setV2Msg('Clear failed.', 'error');
          v2TplClearBtn.disabled = false;
        });
    });
  }

  if (v2InitLegacyBtn && v2Editor && planId > 0) {
    v2InitLegacyBtn.addEventListener('click', function () {
      if (!window.confirm('Initialize Ticketing config from the legacy add-ons settings? This overwrites the saved config for this plan.')) return;
      v2InitLegacyBtn.disabled = true;
      setV2Msg('Initializing…', 'working');
      postJSON('vms_ticketing_v2_init_from_legacy', { plan_id: planId })
        .then((res) => {
          if (!res || !res.success) {
            setV2Msg((res && res.data && res.data.message) ? res.data.message : 'Initialize failed.', 'error');
            v2InitLegacyBtn.disabled = false;
            return;
          }
          const normalized = res.data && res.data.config ? res.data.config : {};
          v2Editor.dataset.initialConfig = JSON.stringify(normalized);
          v2Editor.dataset.configExists = '1';
          renderV2(normalized);
          clearGuardrail(v2TemplateGuardrailWrap);
          clearV2PreviewState();
          setV2Note('Config is saved. Preview is read-only; Commit creates or updates the calendar event, tickets, and add-ons.', 'info');
          setV2Msg('Initialized from legacy add-ons.', 'success');
          v2InitLegacyBtn.disabled = false;
        })
        .catch(() => {
          setV2Msg('Initialize failed.', 'error');
          v2InitLegacyBtn.disabled = false;
        });
    });
  }

  if (v2SaveBtn && v2Editor && planId > 0) {
    v2SaveBtn.addEventListener('click', async function () {
      v2SaveBtn.disabled = true;
      try {
        if (!(await ensureTicketUiOverridesReadyForAction('Save config'))) {
          return;
        }
        setV2Msg('Saving config…', 'info');
        await saveV2Config({ successMsg: 'Config saved.' });
      } finally {
        v2SaveBtn.disabled = false;
      }
    });
  }

  if (v2PreviewBtn && v2Editor && planId > 0) {
    v2PreviewBtn.addEventListener('click', async function () {
      v2PreviewBtn.disabled = true;
      clearV2Details();
      try {
        if (!(await ensureTicketUiOverridesReadyForAction('Preview sync'))) {
          return;
        }

        setV2Msg('Saving config & generating preview…', 'working');
        setBusyTitle(true, 'Preview');

        const ticketingEffective = String(v2Editor.dataset.ticketingEffective || '1') === '1';
        if (!ticketingEffective) {
          setV2Msg('Ticketing is disabled for this event. Set “Tickets for this event” to On, then try again.', 'error');
          setBusyTitle(false);
          return;
        }

        await saveV2Config({ quiet: true, timeoutMs: 45000 });
        const res = await postJSON('vms_ticketing_v2_preview_sync', { plan_id: planId }, 120000);
        if (!res || !res.success) {
          const err = getAjaxFailurePayload(res, 'Preview failed.');
          setV2Msg(err.summary || humanizeV2Message(err.message) || 'Preview failed.', 'error');
          renderV2FailureDetails(err);
          setBusyTitle(false);
          return;
        }

        const d = res.data || {};
        v2PreviewId = String(d.preview_id || '');
        if (v2PreviewWrap) {
          v2PreviewWrap.classList.remove('vms-hidden');
          v2PreviewWrap.style.display = 'block';
        }
        clearV2Details();
        renderV2Preview(d);
        if (d && d.tec_event_id) { v2Editor.dataset.tecEventId = String(d.tec_event_id); }
        v2LastPreviewBlocked = !!d.blocked;
        updateV2CommitEnabled(true);

        const okMsg = 'Preview ready.';
        setV2Msg(d.blocked ? 'Preview blocked. Fix the issues and preview again.' : okMsg, d.blocked ? 'error' : 'success');
        setBusyTitle(false);
      } catch (e) {
        const err = getAjaxFailurePayload(e, 'Preview failed.');
        setV2Msg(err.summary || humanizeV2Message(err.message) || 'Preview failed.', 'error');
        renderV2FailureDetails(err);
        setBusyTitle(false);
      } finally {
        v2PreviewBtn.disabled = false;
      }
    });
  }

  if (v2CommitBtn && v2Editor && planId > 0) {
    v2CommitBtn.addEventListener('click', async function () {
      if (!v2PreviewId) {
        setV2Msg('No preview found. Click Preview sync first.', 'error');
        return;
      }
      if (isV2Dirty()) {
        setV2Msg(humanizeV2Message('stale_config'), 'error');
        return;
      }
      if (!window.confirm('This will create and/or update ticket and entitlement products. Continue?')) return;

      if (!(await ensureTicketUiOverridesReadyForAction('Commit sync'))) {
        return;
      }
      if (!(await maybeAutoSaveEventPlan(setV2Msg))) {
        return;
      }
      captureEventPlanSnapshot('ticketing_v2_commit', setV2Msg);

      v2CommitBtn.disabled = true;
      clearV2Details();
      setV2Msg('Committing…', 'working');
      setBusyTitle(true, 'Commit');

      const runBatchedCommit = async () => {
        let commitPhase = 'prepare';
        let cursor = 0;
        let totalActions = 0;
        let aggregateResults = [];
        let lastData = null;
        let safetyCounter = 0;

        while (safetyCounter < 200) {
          safetyCounter += 1;
          const progressLabel = totalActions > 0
            ? `${Math.min(cursor, totalActions)} of ${totalActions}`
            : 'starting';
          setV2Msg(commitPhase === 'prepare' ? 'Preparing calendar event…' : (commitPhase === 'finalize' ? 'Finalizing sync…' : `Committing ${progressLabel}…`), 'working');

          const payload = { plan_id: planId, preview_id: v2PreviewId, commit_phase: commitPhase };
          if (commitPhase === 'actions' && cursor > 0) {
            payload.cursor = cursor;
          }

          const res = await postJSON('vms_ticketing_v2_commit_sync', payload, 120000);
          if (!res || !res.success) {
            throw res;
          }

          const data = (res && res.data) ? res.data : {};
          lastData = data;
          const batchResults = Array.isArray(data.results) ? data.results : [];
          if (batchResults.length) {
            aggregateResults = aggregateResults.concat(batchResults);
          }

          const reportedTotal = parseInt(data.total_actions || 0, 10) || 0;
          if (reportedTotal > 0) {
            totalActions = reportedTotal;
          }

          const tecEventId = parseInt(data.tec_event_id || 0, 10) || 0;
          if (tecEventId > 0) {
            v2Editor.dataset.tecEventId = String(tecEventId);
          }

          if (commitPhase === 'prepare') {
            if (data && (data.needs_actions || String(data.phase || '') === 'prepare')) {
              commitPhase = 'actions';
              cursor = 0;
              continue;
            }
          }

          if (commitPhase === 'actions') {
            if (data && (data.needs_finalize || String(data.phase || '') === 'actions_complete')) {
              cursor = parseInt(data.next_cursor || totalActions || cursor, 10) || cursor;
              commitPhase = 'finalize';
              continue;
            }

            if (data && (data.partial || String(data.phase || '') === 'actions')) {
              const nextCursor = parseInt(data.next_cursor || 0, 10) || 0;
              if (nextCursor <= cursor && totalActions > cursor) {
                throw { success: false, data: { message: 'batch_cursor_stalled', raw: 'Commit batching stalled before all actions were processed.' } };
              }
              cursor = nextCursor;
              continue;
            }
          }

          return { data: Object.assign({}, data, { results: aggregateResults, total_actions: totalActions || data.total_actions || 0 }) };
        }

        throw { success: false, data: { message: 'batch_safety_stop', raw: 'Commit batching exceeded the safety limit before completion.' } };
      };

      runBatchedCommit()
        .then((res) => {
          if (!res || !res.data) {
            const err = getAjaxFailurePayload(res, 'Commit failed.');
            setV2Msg(err.summary || humanizeV2Message(err.message) || 'Commit failed.', 'error');
            renderV2FailureDetails(err);
            setBusyTitle(false);
            v2CommitBtn.disabled = false;
            return;
          }

          clearV2Details();
          const results = Array.isArray(res.data.results) ? res.data.results : [];
          const failed = results.filter((r) => r && r.ok === false);
          const nonNoop = results.filter((r) => r && String(r.action || '') !== 'noop');
          const actions = results.map((r) => {
            const ok = r && r.ok !== false;
            const prefix = ok ? '' : 'FAILED: ';
            const reason = (r && r.message) ? String(r.message) : '';
            return {
              scope: r && r.scope ? String(r.scope) : '',
              operation: r && r.action ? String(r.action) : '',
              label: prefix + (r && r.label ? String(r.label) : ''),
              woo_product_id: r && r.woo_product_id ? r.woo_product_id : '',
              entitlement_id: r && r.entitlement_id ? r.entitlement_id : '',
              reason: reason,
            };
          });
          const tecInfo = {
            tec_event_id: (res.data && res.data.tec_event_id) ? res.data.tec_event_id : 0,
            tec_event_title: (res.data && res.data.tec_event_title) ? String(res.data.tec_event_title) : '',
            tec_event_view_url: (res.data && res.data.tec_event_view_url) ? String(res.data.tec_event_view_url) : '',
            tec_event_edit_url: (res.data && res.data.tec_event_edit_url) ? String(res.data.tec_event_edit_url) : '',
          };
          if (tecInfo.tec_event_id) { v2Editor.dataset.tecEventId = String(tecInfo.tec_event_id); }

          if (failed.length) {
            setV2Msg('Commit completed with errors. See results below.', 'error');
            renderV2Preview({ title: 'Commit results', blocked: false, warnings: ['Some items failed. Fix the errors and run Preview sync again.'], actions: actions, ...tecInfo });
            if (v2PreviewWrap) {
              v2PreviewWrap.classList.remove('vms-hidden');
              v2PreviewWrap.style.display = 'block';
            }
            v2LastPreviewBlocked = true;
            updateV2CommitEnabled(false);
            setBusyTitle(false);
            v2CommitBtn.disabled = false;
            return;
          }

          if (!nonNoop.length) {
            setV2Msg('Nothing to sync. If you expected tickets, click Preview sync again.', 'warning');
            renderV2Preview({ title: 'Commit results', blocked: false, warnings: [], actions: actions, ...tecInfo });
            if (v2PreviewWrap) {
              v2PreviewWrap.classList.remove('vms-hidden');
              v2PreviewWrap.style.display = 'block';
            }
            setBusyTitle(false);
            v2CommitBtn.disabled = false;
            return;
          }

          setV2Msg('Sync complete. Reloading…', 'success');
          setBusyTitle(false);
          persistRequestedSectionTarget('ticketing_v2');
          safeReload(0);
        })
        .catch((e) => {
          const err = getAjaxFailurePayload(e, 'Commit failed.');
          setV2Msg(err.summary || humanizeV2Message(err.message) || 'Commit failed.', 'error');
          renderV2FailureDetails(err);
          setBusyTitle(false);
          v2CommitBtn.disabled = false;
        });
    });
  }
})();
