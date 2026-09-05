(function (globalScope) {
  const ADMISSIONS_SESSION_EXPIRED_MESSAGE = 'Your Admissions session expired. Refresh this page, then try again.';
  const ADMISSIONS_NETWORK_FAILURE_MESSAGE = 'Could not reach Admissions. Refresh and try again.';
  const ADMISSIONS_LOAD_FAILURE_MESSAGE = 'Could not load Admissions data. Refresh and try again.';
  const ADMISSIONS_REQUEST_FAILURE_MESSAGE = 'Admissions request failed. Refresh and try again.';

  const admissionsNormalizeText = (value) => String(value || '').trim();

  const admissionsResolveStatus = (payload, responseMeta) => {
    const explicitStatus = Number(responseMeta && responseMeta.status ? responseMeta.status : 0);
    if (explicitStatus > 0) {
      return explicitStatus;
    }

    const payloadStatus = Number(
      payload && payload.data && payload.data.status
        ? payload.data.status
        : (payload && payload.error && payload.error.status ? payload.error.status : 0)
    );

    return payloadStatus > 0 ? payloadStatus : 0;
  };

  const admissionsExtractError = (payload) => {
    const nestedError = payload && typeof payload === 'object' && payload.error && typeof payload.error === 'object'
      ? payload.error
      : null;

    return {
      code: admissionsNormalizeText(nestedError && nestedError.code ? nestedError.code : (payload && payload.code ? payload.code : '')),
      message: admissionsNormalizeText(nestedError && nestedError.message ? nestedError.message : (payload && payload.message ? payload.message : '')),
    };
  };

  const admissionsSessionExpired = ({ code, message, status, rawText }) => {
    const normalizedCode = admissionsNormalizeText(code).toLowerCase();
    if (normalizedCode === 'vms_admission_bad_nonce' || normalizedCode === 'rest_cookie_invalid_nonce') {
      return true;
    }

    if (status !== 401 && status !== 403) {
      return false;
    }

    const combined = [
      admissionsNormalizeText(code),
      admissionsNormalizeText(message),
      admissionsNormalizeText(rawText),
    ].join(' ').toLowerCase();

    const patterns = [
      /\bnonce\b/,
      /session(?:\s+\w+){0,2}\s+expired/,
      /cookie(?:\s|-)?check failed/,
      /rest_cookie_invalid_nonce/,
      /authentication(?:\s+\w+){0,2}\s+expired/,
      /\bauth(?:entication)?\s+expired\b/,
      /login(?:\s+\w+){0,2}\s+expired/,
    ];

    return patterns.some((pattern) => pattern.test(combined));
  };

  const normalizeAdmissionsErrorMessage = ({ code, message, status, rawText, fallbackMessage }) => {
    if (admissionsSessionExpired({ code, message, status, rawText })) {
      return ADMISSIONS_SESSION_EXPIRED_MESSAGE;
    }

    if (admissionsNormalizeText(message) !== '') {
      return admissionsNormalizeText(message);
    }

    return fallbackMessage;
  };

  const normalizeAdmissionsRestPayload = (payload, responseMeta = {}, fallbackMessage = ADMISSIONS_REQUEST_FAILURE_MESSAGE) => {
    if (payload && payload.ok === true) {
      return payload;
    }

    const { code, message } = admissionsExtractError(payload);
    const status = admissionsResolveStatus(payload, responseMeta);
    const rawText = admissionsNormalizeText(responseMeta && responseMeta.rawText ? responseMeta.rawText : '');

    return {
      ok: false,
      data: payload && Object.prototype.hasOwnProperty.call(payload, 'data') ? payload.data : null,
      error: {
        code,
        message: normalizeAdmissionsErrorMessage({
          code,
          message,
          status,
          rawText,
          fallbackMessage,
        }),
      },
    };
  };

  const normalizeAdmissionsNetworkFailure = (message = ADMISSIONS_NETWORK_FAILURE_MESSAGE) => ({
    ok: false,
    data: null,
    error: {
      code: 'vms_admission_request_failed',
      message,
    },
  });

  const normalizeAdmissionsFetchResponse = async (response, fallbackMessage = ADMISSIONS_REQUEST_FAILURE_MESSAGE) => {
    const status = response && typeof response.status === 'number' ? response.status : 0;
    let rawText = '';

    if (response && typeof response.text === 'function') {
      try {
        rawText = await response.text();
      } catch (err) {
        rawText = '';
      }
    }

    let payload = null;
    if (admissionsNormalizeText(rawText) !== '') {
      try {
        payload = JSON.parse(rawText);
      } catch (err) {
        payload = null;
      }
    }

    return normalizeAdmissionsRestPayload(payload, { status, rawText }, fallbackMessage);
  };

  const performAdmissionsRequest = (fetchImpl, url, options, fallbackMessage, networkMessage = ADMISSIONS_NETWORK_FAILURE_MESSAGE) => {
    if (typeof fetchImpl !== 'function') {
      return Promise.resolve(normalizeAdmissionsNetworkFailure(networkMessage));
    }

    return Promise.resolve()
      .then(() => fetchImpl(url, options))
      .then((response) => normalizeAdmissionsFetchResponse(response, fallbackMessage))
      .catch(() => normalizeAdmissionsNetworkFailure(networkMessage));
  };

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
      ADMISSIONS_SESSION_EXPIRED_MESSAGE,
      ADMISSIONS_NETWORK_FAILURE_MESSAGE,
      ADMISSIONS_LOAD_FAILURE_MESSAGE,
      ADMISSIONS_REQUEST_FAILURE_MESSAGE,
      normalizeAdmissionsRestPayload,
      normalizeAdmissionsFetchResponse,
      normalizeAdmissionsNetworkFailure,
      performAdmissionsRequest,
    };
  }

  if (!globalScope || !globalScope.document) {
    return;
  }

  const cfg = globalScope.BVMGR_ADMISSIONS_ADMIN || null;
  if (!cfg || !cfg.eventPlanId) return;

  const root = globalScope.document.querySelector('.vms-adm-box');
  if (!root) return;

  const bindVendorGuestRules = () => {
    root.querySelectorAll('[data-vms-vendor-guest-row]').forEach((row) => {
      const toggle = row.querySelector('[data-vms-vendor-guest-toggle]');
      if (!toggle) return;
      const sync = () => {
        const enabled = !!toggle.checked;
        row.classList.toggle('is-disabled', !enabled);
        row.querySelectorAll('[data-vms-vendor-guest-control]').forEach((control) => {
          control.disabled = !enabled;
        });
      };
      toggle.addEventListener('change', sync);
      sync();
    });
  };
  bindVendorGuestRules();

  const nameEl = globalScope.document.getElementById('vms-adm-guest-name');
  const emailEl = globalScope.document.getElementById('vms-adm-guest-email');
  const sizeEl = globalScope.document.getElementById('vms-adm-party-size');
  const phoneEl = globalScope.document.getElementById('vms-adm-phone');
  const notesEl = globalScope.document.getElementById('vms-adm-notes');
  const addBtn = globalScope.document.getElementById('vms-adm-add-entry');
  const listEl = globalScope.document.getElementById('vms-adm-list');
  const summaryEl = globalScope.document.getElementById('vms-adm-summary');
  const feedbackEl = globalScope.document.getElementById('vms-adm-feedback');
  const exportEl = globalScope.document.getElementById('vms-adm-export-csv');

  if (exportEl && cfg.exportCsvUrl) {
    exportEl.href = cfg.exportCsvUrl;
  }

  const rest = (path, options) => {
    const opts = options || {};
    const method = String(opts.method || 'GET').toUpperCase();
    let requestPath = path;
    if (method === 'GET') {
      const stamp = 'vms_no_cache=' + String(Date.now());
      requestPath += (requestPath.indexOf('?') === -1 ? '?' : '&') + stamp;
    }
    const fallbackMessage = method === 'GET'
      ? ADMISSIONS_LOAD_FAILURE_MESSAGE
      : ADMISSIONS_REQUEST_FAILURE_MESSAGE;
    const fetchImpl = typeof globalScope.fetch === 'function' ? globalScope.fetch.bind(globalScope) : null;

    return performAdmissionsRequest(fetchImpl, cfg.restUrl.replace(/\/$/, '') + requestPath, {
      credentials: 'include',
      cache: 'no-store',
      headers: {
        'Content-Type': 'application/json',
        'Cache-Control': 'no-cache, no-store, must-revalidate',
        'Pragma': 'no-cache',
        'X-WP-Nonce': cfg.nonce,
      },
      ...opts,
    }, fallbackMessage, ADMISSIONS_NETWORK_FAILURE_MESSAGE);
  };

  const setFeedback = (msg, isError) => {
    if (!feedbackEl) return;
    const text = msg || '';
    feedbackEl.textContent = text;
    feedbackEl.classList.toggle('is-error', !!isError);
    // Treat messages with an ellipsis as "busy" state (simple, no extra API)
    feedbackEl.classList.toggle('is-busy', !isError && String(text).indexOf('…') !== -1);
  };

  const renderSummary = (data) => {
    if (!summaryEl || !data) return;
    summaryEl.textContent =
      'Checked In: ' + (data.checked_in_entries || 0) + ' guests / ' + (data.checked_in_headcount || 0) +
      ' people | Total Comps: ' + (data.total_entries || 0) + ' entries / ' + (data.total_headcount || 0) + ' people';
  };

  const renderRows = (items) => {
    if (!listEl) return;
    if (!items || !items.length) {
      listEl.innerHTML = '<p>No entries found.</p>';
      return;
    }

    const rows = items.map((row) => {
      const status = String(row.status || 'active');
      const partySize = Number(row.party_size || 1) || 1;
      const checkedInQty = Number(
        row.checked_in_qty != null ? row.checked_in_qty : (status === 'checked_in' ? partySize : 0)
      ) || 0;
      const remaining = Math.max(0, partySize - checkedInQty);

      const checkedMetaParts = [];
      if (checkedInQty > 0) checkedMetaParts.push('Checked in ' + checkedInQty + '/' + partySize);
      if (row.checked_in_at) checkedMetaParts.push('Last: ' + row.checked_in_at);
      const checkedMeta = checkedMetaParts.join(' • ');

      const canCheckin = status !== 'canceled' && remaining > 0;
      const canUndo = !!cfg.allowUncheckin && status !== 'canceled' && checkedInQty > 0;

      const phoneRaw = String(row.phone || '');
      const phoneText = String(row.phone || row.phone_masked || '');
      const phoneCell = phoneRaw
        ? `<a href="tel:${escapeHtml(phoneRaw)}">${escapeHtml(phoneText)}</a>`
        : escapeHtml(phoneText);

      const safeName = escapeHtml(row.guest_name || '');
      const safeEmail = escapeHtml(row.guest_email || '');
      const safePartySize = escapeHtml(String(partySize));
      const safePhone = escapeHtml(phoneRaw);
      const safeNotes = escapeHtml(row.notes || '');

      const partyMeta = partySize > 1
        ? ('Party ' + partySize + ' • In ' + checkedInQty + ' • Rem ' + remaining)
        : ('Party ' + partySize);

      const showAllCheckin = partySize > 1 && remaining > 1;
      const showResetUndo = partySize > 1 && checkedInQty > 1;

      const checkinLabel = partySize > 1 ? 'Check in 1' : 'Check in';
      const undoLabel = partySize > 1 ? 'Undo 1' : 'Undo';

      const isCanceled = status === 'canceled';

      let actionsHtml = '';
      if (isCanceled) {
        actionsHtml = '<button type="button" data-action="restore">Restore</button>';
      } else {
        actionsHtml += '<button type="button" data-action="checkin" data-qty="1" ' + (canCheckin ? '' : 'disabled') + '>' + escapeHtml(checkinLabel) + '</button>';
        if (showAllCheckin) {
          actionsHtml += '<button type="button" data-action="checkin" data-qty="9999" ' + (canCheckin ? '' : 'disabled') + '>All</button>';
        }
        actionsHtml += '<button type="button" data-action="undo" data-qty="1" ' + (canUndo ? '' : 'disabled') + '>' + escapeHtml(undoLabel) + '</button>';
        if (showResetUndo) {
          actionsHtml += '<button type="button" data-action="undo" data-qty="9999" ' + (canUndo ? '' : 'disabled') + '>Reset</button>';
        }
        actionsHtml += '<button type="button" data-action="edit_open">Edit</button>';
        actionsHtml += '<button type="button" data-action="void">Void</button>';
      }

      return `
        <div class="vms-adm-item" data-id="${row.id}" data-party-size="${partySize}" data-checked-in="${checkedInQty}">
          <div class="vms-adm-item-main">
            <strong>${safeName}</strong>
            <span class="vms-adm-status vms-adm-status-${escapeHtml(status)}">${escapeHtml(status)}</span>
          </div>
          <div class="vms-adm-item-sub">
            <span>${escapeHtml(partyMeta)}</span>
            <span>${safeEmail}</span>
            <span>${phoneCell}</span>
            <span>${escapeHtml(row.owner_vendor_name ? ('Vendor: ' + row.owner_vendor_name) : '')}</span>
            <span>${escapeHtml(row.notes || '')}</span>
            <span>${escapeHtml(checkedMeta)}</span>
          </div>
          <div class="vms-adm-item-actions">
            ${actionsHtml}
          </div>
          <div class="vms-adm-edit" hidden>
            <div class="vms-adm-edit-grid">
              <label>Guest Name<input type="text" data-field="guest_name" value="${safeName}"></label>
              <label>Email<input type="text" data-field="guest_email" value="${safeEmail}"></label>
              <label>Party Size<input type="number" min="1" step="1" data-field="party_size" value="${safePartySize}"></label>
              <label>Phone<input type="text" data-field="phone" value="${safePhone}"></label>
              <label>Notes<input type="text" data-field="notes" value="${safeNotes}"></label>
            </div>
            <div class="vms-adm-edit-actions">
              <button type="button" data-action="edit_save">Save</button>
              <button type="button" data-action="edit_close">Close</button>
            </div>
          </div>
        </div>`;
    });

    listEl.innerHTML = rows.join('');
  };

  const load = () => {
    Promise.all([
      rest('/admissions?event_plan_id=' + encodeURIComponent(cfg.eventPlanId) + '&status=all&limit=100', { method: 'GET' }),
      rest('/admissions/summary?event_plan_id=' + encodeURIComponent(cfg.eventPlanId), { method: 'GET' }),
    ]).then(([listResp, summaryResp]) => {
      if (!listResp || listResp.ok !== true) {
        setFeedback((listResp && listResp.error && listResp.error.message) || 'Could not load entries.', true);
        return;
      }
      renderRows((listResp.data && listResp.data.items) || []);
      if (summaryResp && summaryResp.ok === true) {
        renderSummary(summaryResp.data || {});
      } else if (summaryResp && summaryResp.error && summaryResp.error.message) {
        setFeedback(summaryResp.error.message, true);
      }
    });
  };

  const addEntry = () => {
    const payload = {
      event_plan_id: Number(cfg.eventPlanId),
      guest_name: (nameEl && nameEl.value || '').trim(),
      guest_email: (emailEl && emailEl.value || '').trim(),
      party_size: Number((sizeEl && sizeEl.value || '1').trim() || 1),
      phone: (phoneEl && phoneEl.value || '').trim(),
      notes: (notesEl && notesEl.value || '').trim(),
    };

    if (!payload.guest_name) {
      setFeedback('Guest name is required.', true);
      if (nameEl) nameEl.focus();
      return;
    }

    if (!payload.party_size || payload.party_size < 1) {
      payload.party_size = 1;
    }

    // UX: show a clear working state while saving
    const _addBtnText = addBtn && addBtn.textContent ? addBtn.textContent : '';
    if (addBtn) {
      addBtn.disabled = true;
      addBtn.setAttribute('aria-busy', 'true');
      addBtn.textContent = 'Adding…';
    }
    setFeedback('Adding entry…', false);

    rest('/admissions', {
      method: 'POST',
      body: JSON.stringify(payload),
    }).then((resp) => {
      if (!resp || resp.ok !== true) {
        setFeedback((resp && resp.error && resp.error.message) || 'Could not add entry.', true);
        return;
      }
      const dup = resp.data && resp.data.duplicate_warning;
      setFeedback(dup ? 'Added with duplicate warning.' : 'Entry added.', false);
      if (nameEl) nameEl.value = '';
      if (emailEl) emailEl.value = '';
      if (sizeEl) sizeEl.value = '1';
      if (phoneEl) phoneEl.value = '';
      if (notesEl) notesEl.value = '';
      load();
    }).finally(() => {
      if (addBtn) {
        addBtn.disabled = false;
        addBtn.removeAttribute('aria-busy');
        addBtn.textContent = _addBtnText || 'Add Comp Entry';
      }
    });
  };

  // Click delegation for row actions (edit/check-in/undo/delete).
  // Note: addEventListener passes an Event object, not a target element.
  const handleRowAction = (e) => {
    const rawTarget = e && e.target ? e.target : null;
    const target = rawTarget instanceof Element ? rawTarget : (rawTarget && rawTarget.parentElement ? rawTarget.parentElement : null);
    if (!target) return;

    const btn = target.closest('button[data-action]');
    if (!btn) return;

    const item = btn.closest('.vms-adm-item') || target.closest('.vms-adm-item');
    if (!item) return;

    const id = Number(item.getAttribute('data-id') || 0);
    const action = btn.getAttribute('data-action');
    if (!id || !action) return;

    if (action === 'checkin') {
      const qty = Number(btn.getAttribute('data-qty') || '1') || 1;
      btn.disabled = true;
      setFeedback('Checking in…', false);

      rest('/admissions/' + id + '/checkin', {
        method: 'POST',
        body: JSON.stringify({ qty }),
      }).then((resp) => {
        if (!resp || resp.ok !== true) {
          setFeedback((resp && resp.error && resp.error.message) || 'Check-in failed.', true);
          return;
        }

        const item = resp.data && resp.data.item ? resp.data.item : null;
        if (item) {
          const ps = Number(item.party_size || 1) || 1;
          const ci = Number(item.checked_in_qty || 0) || 0;
          const rem = Math.max(0, ps - ci);
          setFeedback(rem > 0 ? ('Checked in. Remaining: ' + rem + '.') : 'Checked in (complete).', false);
        } else {
          setFeedback('Checked in.', false);
        }

        load();
      }).finally(() => {
        btn.disabled = false;
      });
      return;
    }
        

    if (action === 'undo') {
      const qty = Number(btn.getAttribute('data-qty') || '1') || 1;
      btn.disabled = true;
      setFeedback('Undoing check-in…', false);

      rest('/admissions/' + id + '/uncheckin', {
        method: 'POST',
        body: JSON.stringify({ qty }),
      }).then((resp) => {
        if (!resp || resp.ok !== true) {
          setFeedback((resp && resp.error && resp.error.message) || 'Undo failed.', true);
          return;
        }

        const item = resp.data && resp.data.item ? resp.data.item : null;
        if (item) {
          const ci = Number(item.checked_in_qty || 0) || 0;
          setFeedback(ci > 0 ? ('Undo complete. Still checked in: ' + ci + '.') : 'Check-in reset.', false);
        } else {
          setFeedback('Check-in undone.', false);
        }

        load();
      }).finally(() => {
        btn.disabled = false;
      });
      return;
    }
        

    if (action === 'void') {
      if (!globalScope.confirm('Void this guest list entry? You can restore it later.')) return;

      btn.disabled = true;
      setFeedback('Voiding entry…', false);

      rest('/admissions/' + id, {
        method: 'PATCH',
        body: JSON.stringify({ status: 'canceled' }),
      }).then((resp) => {
        if (!resp || resp.ok !== true) {
          setFeedback((resp && resp.error && resp.error.message) || 'Void failed.', true);
          return;
        }
        setFeedback('Entry voided.', false);
        load();
      }).finally(() => {
        btn.disabled = false;
      });
      return;
    }

    if (action === 'restore') {
      btn.disabled = true;
      setFeedback('Restoring entry…', false);

      rest('/admissions/' + id, {
        method: 'PATCH',
        body: JSON.stringify({ status: 'active' }),
      }).then((resp) => {
        if (!resp || resp.ok !== true) {
          setFeedback((resp && resp.error && resp.error.message) || 'Restore failed.', true);
          return;
        }
        setFeedback('Entry restored.', false);
        load();
      }).finally(() => {
        btn.disabled = false;
      });
      return;
    }
        

    if (action === 'edit_open') {
      const panel = item.querySelector('.vms-adm-edit');
      if (panel) {
        panel.hidden = false;
        const first = panel.querySelector('input');
        if (first) first.focus();
      }
      return;
    }

    if (action === 'edit_close') {
      const panel = item.querySelector('.vms-adm-edit');
      if (panel) panel.hidden = true;
      return;
    }

    if (action === 'edit_save') {
      const panel = item.querySelector('.vms-adm-edit');
      if (!panel) return;
      const saveBtn = btn;
      const guestName = (panel.querySelector('input[data-field="guest_name"]')?.value || '').trim();
      const partySizeRaw = (panel.querySelector('input[data-field="party_size"]')?.value || '1').trim();
      const email = (panel.querySelector('input[data-field="guest_email"]')?.value || '').trim();
      const phone = (panel.querySelector('input[data-field="phone"]')?.value || '').trim();
      const notes = (panel.querySelector('input[data-field="notes"]')?.value || '').trim();

      const partySize = Number(partySizeRaw) || 1;
      if (!guestName) {
        setFeedback('Guest name is required.', true);
        panel.querySelector('input[data-field="guest_name"]')?.focus();
        return;
      }

      saveBtn.disabled = true;
      setFeedback('Saving entry…', false);

      rest('/admissions/' + id, {
        method: 'PATCH',
        body: JSON.stringify({ guest_name: guestName, guest_email: email, party_size: partySize, phone: phone, notes: notes }),
      }).then((resp) => {
        if (!resp || resp.ok !== true) {
          setFeedback((resp && resp.error && resp.error.message) || 'Edit failed.', true);
          return;
        }
        setFeedback('Entry updated.', false);
        panel.hidden = true;
        load();
      }).finally(() => {
        saveBtn.disabled = false;
      });
      return;
    }
  };

  const escapeHtml = (str) => {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  };

  if (addBtn) addBtn.addEventListener('click', addEntry);
  if (listEl) listEl.addEventListener('click', handleRowAction);
  load();
})(typeof globalThis !== 'undefined' ? globalThis : this);
