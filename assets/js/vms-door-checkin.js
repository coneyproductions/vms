(function () {
  const cfg = window.BVMGR_DOOR_CHECKIN || null;
  const root = document.getElementById('vms-door-checkin-root');
  if (!cfg || !root) return;

  const planEl = document.getElementById('vms-door-event-plan');
  const searchEl = document.getElementById('vms-door-search');
  const filtersEl = document.getElementById('vms-door-filters');
  const scanSubmitEl = document.getElementById('vms-door-scan-submit');
  const summaryEl = document.getElementById('vms-door-summary');
  const feedbackEl = document.getElementById('vms-door-feedback');
  const resultsEl = document.getElementById('vms-door-results');

  let currentPlanId = 0;
  let statusFilter = 'active';

  const rest = (path, options) => {
    return fetch(cfg.restUrl.replace(/\/$/, '') + path, {
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfg.nonce,
      },
      ...options,
    }).then((r) => r.json());
  };

  const setFeedback = (msg, isError) => {
    if (!feedbackEl) return;
    feedbackEl.textContent = msg || '';
    feedbackEl.classList.toggle('is-error', !!isError);
  };

  const escapeHtml = (str) => String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

  const renderSummary = (sum) => {
    if (!summaryEl) return;
    summaryEl.textContent =
      'Checked In: ' + (sum.checked_in_entries || 0) + ' guests / ' + (sum.checked_in_headcount || 0) +
      ' people | Total Comps: ' + (sum.total_entries || 0) + ' entries / ' + (sum.total_headcount || 0) + ' people';
  };

  const renderCards = (items) => {
    if (!resultsEl) return;
    if (!items || !items.length) {
      resultsEl.innerHTML = '<p class="vms-door-empty">No guests found.</p>';
      return;
    }

    resultsEl.innerHTML = items.map((row) => {
      const status = String(row.status || 'active');
      const checkinDisabled = !(status === 'active' || status === 'partial');
      const btnText = status === 'checked_in' ? 'Checked In' : (status === 'canceled' ? 'Canceled' : 'Check In');
      const phone = row.phone || row.phone_masked || '';
      const phoneRaw = row.phone || '';
      const phoneHtml = phoneRaw
        ? `<a href="tel:${escapeHtml(phoneRaw)}">${escapeHtml(phone)}</a>`
        : escapeHtml(phone);
      return `
        <article class="vms-door-card" data-id="${row.id}">
          <div class="vms-door-card-top">
            <h3>${escapeHtml(row.guest_name || '')}</h3>
            <span class="vms-door-badge">${escapeHtml(row.source_label || 'Admission')} · Party ${Number(row.party_size || 0)}</span>
          </div>
          <p class="vms-door-note">${escapeHtml(row.notes || '')}</p>
          ${phone ? `<p class="vms-door-phone">${phoneHtml}</p>` : ''}
          <button type="button" class="vms-door-checkin-btn" ${checkinDisabled ? 'disabled' : ''}>${escapeHtml(btnText)}</button>
        </article>`;
    }).join('');
  };

  const loadSummary = () => {
    if (!currentPlanId) return Promise.resolve();
    return rest('/admissions/summary?event_plan_id=' + encodeURIComponent(currentPlanId), { method: 'GET' })
      .then((resp) => {
        if (resp && resp.ok === true) renderSummary(resp.data || {});
      });
  };


  const handleScanOrSearch = () => {
    if (!currentPlanId) {
      setFeedback('Select an event first.', true);
      return;
    }
    const raw = (searchEl && searchEl.value || '').trim();
    if (!raw) {
      loadAdmissions();
      return;
    }
    rest('/admissions/scan', {
      method: 'POST',
      body: JSON.stringify({ event_plan_id: currentPlanId, scan: raw, auto_checkin: 1 }),
    }).then((resp) => {
      if (resp && resp.ok === true) {
        setFeedback('Checked in.', false);
        if (searchEl) searchEl.value = '';
        loadAdmissions();
        return;
      }
      const code = resp && resp.error && resp.error.code;
      if (code === 'not_found') {
        setFeedback('No scan match. Showing name/phone search results.', true);
        loadAdmissions();
        return;
      }
      setFeedback((resp && resp.error && resp.error.message) || 'Scan failed.', true);
      loadAdmissions();
    });
  };

  const loadAdmissions = () => {
    if (!currentPlanId) {
      renderCards([]);
      return;
    }
    const q = encodeURIComponent((searchEl && searchEl.value || '').trim());
    const qs = '/admissions?event_plan_id=' + encodeURIComponent(currentPlanId) + '&status=' + encodeURIComponent(statusFilter) + '&limit=100&q=' + q;
    rest(qs, { method: 'GET' }).then((resp) => {
      if (!resp || resp.ok !== true) {
        setFeedback((resp && resp.error && resp.error.message) || 'Could not load guest list.', true);
        return;
      }
      renderCards((resp.data && resp.data.items) || []);
      loadSummary();
    });
  };

  const loadPlans = () => {
    rest('/event-plans/today', { method: 'GET' }).then((resp) => {
      if (!resp || resp.ok !== true) {
        setFeedback((resp && resp.error && resp.error.message) || 'Could not load event plans.', true);
        return;
      }
      const items = (resp.data && resp.data.items) || [];
      if (!planEl) return;

      planEl.innerHTML = '<option value="">Select an event</option>' + items.map((plan) => {
        const venue = plan.venue_name ? (' - ' + plan.venue_name) : '';
        return '<option value="' + Number(plan.event_plan_id || 0) + '">' + escapeHtml(plan.title || 'Event') + escapeHtml(venue) + '</option>';
      }).join('');

      if (items.length === 1) {
        currentPlanId = Number(items[0].event_plan_id || 0);
        planEl.value = String(currentPlanId);
        loadAdmissions();
      }
    });
  };

  if (planEl) {
    planEl.addEventListener('change', () => {
      currentPlanId = Number(planEl.value || 0);
      loadAdmissions();
    });
  }

  if (searchEl) {
    let t = null;
    searchEl.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(loadAdmissions, 150);
    });
    searchEl.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        handleScanOrSearch();
      }
    });
    searchEl.focus();
  }

  if (scanSubmitEl) {
    scanSubmitEl.addEventListener('click', handleScanOrSearch);
  }

  if (filtersEl) {
    filtersEl.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-status]');
      if (!btn) return;
      statusFilter = btn.getAttribute('data-status') || 'active';
      filtersEl.querySelectorAll('button[data-status]').forEach((el) => el.classList.remove('is-active'));
      btn.classList.add('is-active');
      loadAdmissions();
    });
  }

  if (resultsEl) {
    resultsEl.addEventListener('click', (e) => {
      const btn = e.target.closest('.vms-door-checkin-btn');
      if (!btn || btn.disabled) return;
      const card = e.target.closest('.vms-door-card');
      const id = Number(card && card.getAttribute('data-id') || 0);
      if (!id) return;

      rest('/admissions/' + id + '/checkin', { method: 'POST' }).then((resp) => {
        if (!resp || resp.ok !== true) {
          setFeedback((resp && resp.error && resp.error.message) || 'Check-in failed.', true);
          loadAdmissions();
          return;
        }
        setFeedback('Checked in.', false);
        loadAdmissions();
      });
    });
  }

  loadPlans();
})();
