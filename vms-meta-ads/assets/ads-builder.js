(function () {
  'use strict';

  var builder = document.getElementById('vms-ma-ads-builder-wrap');
  if (!builder || typeof window.VMS_MA === 'undefined') {
    return;
  }

  var notices = document.getElementById('vms-ma-builder-notices');
  var toastRoot = document.getElementById('vms-ma-toast-root');
  var buildIdInput = document.getElementById('vms-ma-build-id');
  var lastEventContext = null;

  function byId(id) {
    return document.getElementById(id);
  }

  function value(id) {
    var el = byId(id);
    return el ? String(el.value || '') : '';
  }

  function parseIntSafe(raw, fallback) {
    var n = parseInt(String(raw || ''), 10);
    return Number.isFinite(n) ? n : fallback;
  }

  function parseFloatSafe(raw, fallback) {
    var n = parseFloat(String(raw || ''));
    return Number.isFinite(n) ? n : fallback;
  }

  function showNotice(message, type) {
    if (!notices) {
      return;
    }
    notices.className = 'notice vms-ma-builder-notices notice-' + (type || 'info');
    notices.innerHTML = '<p>' + String(message || '') + '</p>';
    notices.classList.remove('vms-ma-hidden');
  }

  function showToast(type, message) {
    if (!toastRoot || !message) {
      return;
    }
    var toast = document.createElement('div');
    toast.className = 'vms-ma-toast vms-ma-toast-' + (type || 'info');
    toast.textContent = String(message);
    toastRoot.appendChild(toast);
    window.setTimeout(function () {
      toast.classList.add('is-leaving');
      window.setTimeout(function () {
        if (toast && toast.parentNode) {
          toast.parentNode.removeChild(toast);
        }
      }, 180);
    }, 2600);
  }

  function setStepStatus(stepKey, message) {
    var key = String(stepKey || '').toLowerCase();
    var row = byId('vms-ma-step-' + key + '-status');
    if (!row) {
      return;
    }
    row.textContent = String(message || '');
  }

  function emitFeedback(type, message, stepKey) {
    showNotice(message, type);
    showToast(type, message);
    if (stepKey) {
      setStepStatus(stepKey, message);
    }
  }

  function request(path, method, body) {
    return fetch(VMS_MA.restRoot + path, {
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': VMS_MA.nonce
      },
      body: body ? JSON.stringify(body) : undefined
    }).then(function (res) {
      return res.json().then(function (json) {
        if (!res.ok) {
          throw new Error((json && json.message) ? json.message : 'Request failed');
        }
        return json;
      });
    });
  }

  function requestAbsolute(url) {
    return fetch(url, {
      method: 'GET',
      headers: {
        'X-WP-Nonce': VMS_MA.nonce
      }
    }).then(function (res) {
      return res.json().then(function (json) {
        if (!res.ok) {
          throw new Error((json && json.message) ? json.message : 'Request failed');
        }
        return json;
      });
    });
  }

  function dispatchFieldEvents(el) {
    if (!el) {
      return;
    }
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function toSiteDateTime(localValue) {
    if (!localValue) {
      return '';
    }
    return String(localValue).replace('T', ' ') + ':00';
  }

  function toLocalDateTime(valueRaw) {
    if (!valueRaw) {
      return '';
    }
    var valueLocal = String(valueRaw).trim().replace(' ', 'T');
    if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(valueLocal)) {
      return valueLocal;
    }
    var dt = new Date(valueRaw);
    if (Number.isNaN(dt.getTime())) {
      return '';
    }
    var y = dt.getFullYear();
    var m = String(dt.getMonth() + 1).padStart(2, '0');
    var d = String(dt.getDate()).padStart(2, '0');
    var h = String(dt.getHours()).padStart(2, '0');
    var min = String(dt.getMinutes()).padStart(2, '0');
    return y + '-' + m + '-' + d + 'T' + h + ':' + min;
  }

  function parseDateInput(input) {
    if (!input) {
      return null;
    }
    var dt = new Date(input);
    return Number.isNaN(dt.getTime()) ? null : dt;
  }

  function toDisplayDate(dt) {
    if (!dt || Number.isNaN(dt.getTime())) {
      return '';
    }
    return dt.toLocaleString(undefined, {
      year: 'numeric',
      month: 'short',
      day: '2-digit',
      hour: 'numeric',
      minute: '2-digit'
    });
  }

  function setInlineWarning(message) {
    var warning = byId('vms-ma-event-picker-warning');
    if (!warning) {
      return;
    }
    if (!message) {
      warning.classList.add('vms-ma-hidden');
      warning.textContent = '';
      return;
    }
    warning.textContent = message;
    warning.classList.remove('vms-ma-hidden');
  }

  function ensureEventSelectionState() {
    var eventPlanId = parseIntSafe(value('vms-ma-event-plan-id'), 0);
    if (!eventPlanId) {
      setInlineWarning('Choose an Event Plan first. This fills the rest automatically.');
      return false;
    }
    setInlineWarning('');
    return true;
  }

  function getRampWeights() {
    var w30 = parseIntSafe(value('vms-ma-weight_30'), 30);
    var w14 = parseIntSafe(value('vms-ma-weight_14'), 30);
    var w7 = parseIntSafe(value('vms-ma-weight_7'), 40);
    var total = w30 + w14 + w7;
    if (total !== 100) {
      return null;
    }
    return { d30: w30, d14: w14, d7: w7 };
  }

  function normalizeScheduleWindows(preset, eventStart, now, endDate) {
    if (preset === 'simple_7_day') {
      return [{ key: 'simple_7', label: 'Simple 7 day', start: new Date(Math.max(now.getTime(), eventStart.getTime() - 7 * 24 * 60 * 60 * 1000)), end: endDate, weight: 100 }];
    }
    if (preset === 'simple_14_day') {
      return [{ key: 'simple_14', label: 'Simple 14 day', start: new Date(Math.max(now.getTime(), eventStart.getTime() - 14 * 24 * 60 * 60 * 1000)), end: endDate, weight: 100 }];
    }
    if (preset === 'simple_30_day') {
      return [{ key: 'simple_30', label: 'Simple 30 day', start: new Date(Math.max(now.getTime(), eventStart.getTime() - 30 * 24 * 60 * 60 * 1000)), end: endDate, weight: 100 }];
    }
    if (preset === 'flat_run') {
      return [{ key: 'flat_run', label: 'Flat run', start: now, end: endDate, weight: 100 }];
    }

    var weights = getRampWeights();
    if (!weights) {
      emitFeedback('error', 'Custom 30/14/7 weights must add up to 100%.', 'b');
      return [];
    }

    return [
      {
        key: 'd30',
        label: '30 day',
        start: new Date(Math.max(now.getTime(), eventStart.getTime() - (30 * 24 * 60 * 60 * 1000))),
        end: new Date(eventStart.getTime() - (15 * 24 * 60 * 60 * 1000)),
        weight: weights.d30
      },
      {
        key: 'd14',
        label: '14 day',
        start: new Date(Math.max(now.getTime(), eventStart.getTime() - (14 * 24 * 60 * 60 * 1000))),
        end: new Date(eventStart.getTime() - (8 * 24 * 60 * 60 * 1000)),
        weight: weights.d14
      },
      {
        key: 'd7',
        label: '7 day',
        start: new Date(Math.max(now.getTime(), eventStart.getTime() - (7 * 24 * 60 * 60 * 1000))),
        end: endDate,
        weight: weights.d7
      }
    ].filter(function (tier) {
      return tier.end.getTime() > now.getTime();
    });
  }

  function buildTierSchedule() {
    var preset = value('vms-ma-preset-mode') || 'flat_run';
    var eventStart = parseDateInput(value('vms-ma-event-start'));
    var totalBudgetMinor = Math.round(parseFloatSafe(value('vms-ma-budget-total'), 0) * 100);
    if (!eventStart || totalBudgetMinor < 1) {
      return [];
    }

    var now = new Date();

    if (preset === 'manual_dates') {
      var manualStart = parseDateInput(value('vms-ma-manual-start'));
      var manualEnd = parseDateInput(value('vms-ma-manual-end'));
      if (!manualStart || !manualEnd || manualEnd.getTime() <= manualStart.getTime()) {
        return [];
      }
      return [{
        key: 'manual',
        label: 'Manual window',
        start: manualStart,
        end: manualEnd,
        budgetMinor: totalBudgetMinor,
        weight: 100
      }];
    }

    var endBuffer = Math.max(0, parseIntSafe(value('vms-ma-end_buffer_hours'), 2));
    var endDate = new Date(eventStart.getTime() - (endBuffer * 60 * 60 * 1000));
    if (endDate.getTime() <= now.getTime()) {
      return [];
    }

    var tiers = normalizeScheduleWindows(preset, eventStart, now, endDate);
    if (!tiers.length) {
      return [];
    }

    var totalWeight = tiers.reduce(function (sum, tier) {
      return sum + (tier.weight || 0);
    }, 0);
    if (totalWeight < 1) {
      return [];
    }

    var remaining = totalBudgetMinor;
    tiers.forEach(function (tier, idx) {
      var alloc = idx === tiers.length - 1
        ? remaining
        : Math.floor(totalBudgetMinor * ((tier.weight || 0) / totalWeight));
      tier.budgetMinor = alloc;
      remaining -= alloc;
      if (tier.end.getTime() > endDate.getTime()) {
        tier.end = new Date(endDate.getTime());
      }
    });

    return tiers;
  }

  function getCopySeed(eventItem) {
    var eventName = (eventItem && eventItem.title) || value('vms-ma-event-name') || 'Live Music';
    var venueName = (eventItem && eventItem.venue_name) || value('vms-ma-venue-name') || 'the venue';
    var startInput = (eventItem && (eventItem.start_input || eventItem.start_local)) || value('vms-ma-event-start');
    var eventDate = parseDateInput(toLocalDateTime(startInput)) || parseDateInput(value('vms-ma-event-start'));

    var dow = eventDate ? eventDate.toLocaleDateString(undefined, { weekday: 'short' }) : 'Soon';
    var mon = eventDate ? eventDate.toLocaleDateString(undefined, { month: 'short' }) : '';
    var day = eventDate ? eventDate.toLocaleDateString(undefined, { day: 'numeric' }) : '';
    var time = eventDate ? eventDate.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' }) : '';

    return {
      primary: 'Live music at ' + venueName + ' on ' + dow + ', ' + mon + ' ' + day + '. ' + eventName + '. Get tickets now.',
      headline: eventName,
      description: venueName + ' | ' + time
    };
  }

  function applyDefaultCopyFromEvent(eventItem, force) {
    var primaryEl = byId('vms-ma-primary-text');
    var headlineEl = byId('vms-ma-headline');
    var descriptionEl = byId('vms-ma-description');
    if (!primaryEl || !headlineEl || !descriptionEl) {
      return;
    }

    var seed = getCopySeed(eventItem || lastEventContext || null);
    if (force || !String(primaryEl.value || '').trim()) {
      primaryEl.value = seed.primary;
      dispatchFieldEvents(primaryEl);
    }
    if (force || !String(headlineEl.value || '').trim()) {
      headlineEl.value = seed.headline;
      dispatchFieldEvents(headlineEl);
    }
    if (force || !String(descriptionEl.value || '').trim()) {
      descriptionEl.value = seed.description;
      dispatchFieldEvents(descriptionEl);
    }
  }

  function renderTierPreview() {
    var table = byId('vms-ma-tier-table');
    var schedStart = byId('vms-ma-sched-start');
    var schedEnd = byId('vms-ma-sched-end');
    if (!table || !schedStart || !schedEnd) {
      return;
    }

    var tiers = buildTierSchedule();
    if (!tiers.length) {
      schedStart.value = '';
      schedEnd.value = '';
      table.innerHTML = '<p class="description">Set event start and budget to preview schedule.</p>';
      return;
    }

    schedStart.value = toDisplayDate(tiers[0].start);
    schedEnd.value = toDisplayDate(tiers[tiers.length - 1].end);

    var html = '<table class="widefat striped"><thead><tr><th>Tier</th><th>Start</th><th>End</th><th>Budget</th></tr></thead><tbody>';
    tiers.forEach(function (tier) {
      html += '<tr><td>' + tier.label + '</td><td>' + toDisplayDate(tier.start) + '</td><td>' + toDisplayDate(tier.end) + '</td><td>$' + (tier.budgetMinor / 100).toFixed(2) + '</td></tr>';
    });
    html += '</tbody></table>';
    table.innerHTML = html;
  }

  function syncCreativeFields() {
    var mode = (document.querySelector('input[name="vms_ma_creative_mode"]:checked') || {}).value || 'dark_post';
    builder.querySelectorAll('.vms-ma-creative-fields[data-mode]').forEach(function (group) {
      group.style.display = group.getAttribute('data-mode') === mode ? '' : 'none';
    });
  }

  function syncGoalOptimizationVisibility() {
    var wrap = byId('vms-ma-optimization-wrap');
    if (!wrap) {
      return;
    }
    var goal = value('vms-ma-goal') || 'traffic';
    wrap.classList.toggle('vms-ma-hidden', goal !== 'traffic');
  }

  function updateApiAssistVisibility() {
    var apiReady = !!parseIntSafe(VMS_MA.apiReady, 0);
    var postPickerWrap = byId('vms-ma-post-picker-wrap');
    var postUrlWrap = byId('vms-ma-post-url-wrap');
    var eventPickerWrap = byId('vms-ma-fb-event-picker-wrap');
    var eventUrlWrap = byId('vms-ma-fb-event-url-wrap');
    if (postPickerWrap) {
      postPickerWrap.classList.toggle('vms-ma-hidden', !apiReady);
    }
    if (postUrlWrap) {
      postUrlWrap.classList.toggle('vms-ma-hidden', apiReady);
    }
    if (eventPickerWrap) {
      eventPickerWrap.classList.toggle('vms-ma-hidden', !apiReady);
    }
    if (eventUrlWrap) {
      eventUrlWrap.classList.toggle('vms-ma-hidden', apiReady);
    }
  }

  function clampAudienceInputs() {
    var maxRadius = Math.max(1, parseIntSafe(VMS_MA.maxRadiusMiles, 100));
    var radiusEl = byId('vms-ma-radius');
    var radiusWarn = byId('vms-ma-radius-warning');
    if (radiusEl) {
      var radius = parseIntSafe(radiusEl.value, 15);
      radius = Math.max(1, Math.min(maxRadius, radius));
      radiusEl.value = String(radius);
      if (radiusWarn) {
        if (radius === maxRadius) {
          radiusWarn.textContent = 'Meta may clamp radius depending on targeting. VMS limited this to ' + maxRadius + ' miles.';
          radiusWarn.classList.remove('vms-ma-hidden');
        } else {
          radiusWarn.textContent = '';
          radiusWarn.classList.add('vms-ma-hidden');
        }
      }
    }

    var ageMinEl = byId('vms-ma-age-min');
    var ageMaxEl = byId('vms-ma-age-max');
    if (ageMinEl && ageMaxEl) {
      var min = Math.max(13, parseIntSafe(ageMinEl.value, 21));
      var max = Math.max(min, parseIntSafe(ageMaxEl.value, 65));
      ageMinEl.value = String(min);
      ageMaxEl.value = String(max);
    }
  }

  function buildDestinationUtm(urlRaw, eventTitle) {
    var raw = String(urlRaw || '').trim();
    if (!raw) {
      return '';
    }
    try {
      var url = new URL(raw);
      url.searchParams.set('utm_source', 'meta');
      url.searchParams.set('utm_medium', 'paid_social');
      url.searchParams.set('utm_campaign', String(eventTitle || 'event').toLowerCase().replace(/[^a-z0-9]+/g, '_'));
      return url.toString();
    } catch (e) {
      return raw;
    }
  }

  function isPackReady() {
    if (!value('vms-ma-event-plan-id')) {
      return false;
    }
    if (!value('vms-ma-event-start')) {
      return false;
    }
    if (!value('vms-ma-destination-url')) {
      return false;
    }
    return buildTierSchedule().length > 0;
  }

  function buildCopyPackText() {
    var eventName = value('vms-ma-event-name');
    var venueName = value('vms-ma-venue-name');
    var eventStart = value('vms-ma-event-start');
    var destination = value('vms-ma-destination-url');
    var utmUrl = buildDestinationUtm(destination, eventName);
    var mode = (document.querySelector('input[name="vms_ma_creative_mode"]:checked') || {}).value || 'dark_post';
    var primary = value('vms-ma-primary-text');
    var headline = value('vms-ma-headline');
    var description = value('vms-ma-description');
    var radius = value('vms-ma-radius');
    var ageMin = value('vms-ma-age-min');
    var ageMax = value('vms-ma-age-max') === '65' ? '65+' : value('vms-ma-age-max');
    var interests = value('vms-ma-interests');
    var budget = value('vms-ma-budget-total');
    var goal = value('vms-ma-goal') || 'traffic';
    var optimization = value('vms-ma-optimization') || 'link_clicks';
    var preset = value('vms-ma-preset-mode') || 'flat_run';
    var tiers = buildTierSchedule();

    var lines = [];
    lines.push('Event: ' + (eventName || '(missing event)'));
    lines.push('Venue: ' + (venueName || '(missing venue)'));
    lines.push('Date/Time: ' + (eventStart || '(missing event start)'));
    lines.push('Destination URL: ' + (destination || '(missing destination URL)'));
    lines.push('UTM URL: ' + (utmUrl || '(missing destination URL)'));
    lines.push('Preset: ' + preset);
    lines.push('Goal: ' + goal + (goal === 'traffic' ? (' | Optimization: ' + optimization) : ''));
    lines.push('Creative mode: ' + mode);
    lines.push('Primary text: ' + (primary || '(empty)'));
    lines.push('Headline: ' + (headline || '(empty)'));
    lines.push('Description: ' + (description || '(empty)'));
    lines.push('Audience: radius ' + (radius || '0') + ' miles, ages ' + (ageMin || '?') + '-' + (ageMax || '?') + ', interests ' + (interests || '(none)'));
    lines.push('Budget total: $' + (budget || '0.00'));
    lines.push('Schedule:');
    tiers.forEach(function (tier) {
      lines.push('- ' + tier.label + ': ' + toDisplayDate(tier.start) + ' -> ' + toDisplayDate(tier.end) + ' | $' + (tier.budgetMinor / 100).toFixed(2));
    });

    return {
      text: lines.join('\n'),
      utmUrl: utmUrl
    };
  }

  function renderCopyPackPanel() {
    var warning = byId('vms-ma-copy-pack-warning');
    var pre = byId('vms-ma-copy-pack');
    if (!pre) {
      return;
    }

    if (!isPackReady()) {
      pre.textContent = 'Finish Steps A-E to generate your copy pack.';
      if (warning) {
        warning.textContent = '';
        warning.classList.add('vms-ma-hidden');
      }
      return;
    }

    var pack = buildCopyPackText();
    pre.textContent = pack.text;

    if (!value('vms-ma-destination-url')) {
      if (warning) {
        warning.textContent = 'Missing Destination URL. Paste it in Step A.';
        warning.classList.remove('vms-ma-hidden');
      }
    } else if (warning) {
      warning.textContent = '';
      warning.classList.add('vms-ma-hidden');
    }
  }

  function collectPayload() {
    var tiers = buildTierSchedule();
    var tierBudgets = {};
    tiers.forEach(function (tier) {
      tierBudgets[tier.key] = tier.budgetMinor;
    });

    var preset = value('vms-ma-preset-mode') || 'flat_run';
    var mode = preset === 'promo_bundle_30_14_7' ? 'autoramp' : 'simple';

    return {
      id: parseIntSafe(value('vms-ma-build-id'), 0),
      event_plan_id: parseIntSafe(value('vms-ma-event-plan-id'), 0),
      venue_id: parseIntSafe(value('vms-ma-venue-id'), 0),
      event_name: value('vms-ma-event-name'),
      venue_name: value('vms-ma-venue-name'),
      event_start: toSiteDateTime(value('vms-ma-event-start')),
      destination_url: value('vms-ma-destination-url'),
      mode: mode,
      goal: value('vms-ma-goal') || 'traffic',
      optimization: value('vms-ma-optimization') || 'link_clicks',
      preset_mode: preset,
      manual_start: toSiteDateTime(value('vms-ma-manual-start')),
      manual_end: toSiteDateTime(value('vms-ma-manual-end')),
      creative_mode: (document.querySelector('input[name="vms_ma_creative_mode"]:checked') || {}).value || 'dark_post',
      post_asset_id: parseIntSafe(value('vms-ma-post-id'), 0),
      fb_event_id: value('vms-ma-fb-event-id'),
      primary_text: value('vms-ma-primary-text'),
      headline: value('vms-ma-headline'),
      description: value('vms-ma-description'),
      radius_miles: parseIntSafe(value('vms-ma-radius'), 15),
      age_min: parseIntSafe(value('vms-ma-age-min'), 21),
      age_max: parseIntSafe(value('vms-ma-age-max'), 65),
      interests: value('vms-ma-interests'),
      total_budget_minor: Math.round(parseFloatSafe(value('vms-ma-budget-total'), 0) * 100),
      tier_budgets: tierBudgets,
      end_buffer_hours: parseIntSafe(value('vms-ma-end_buffer_hours'), 2)
    };
  }

  function copyText(text) {
    if (!navigator.clipboard) {
      return Promise.resolve();
    }
    return navigator.clipboard.writeText(String(text || ''));
  }

  function downloadText(filename, text) {
    var blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
    var url = URL.createObjectURL(blob);
    var link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  }

  function modalOpen(message) {
    var modal = byId('vms-ma-gated-modal');
    var body = byId('vms-ma-gated-message');
    if (!modal) {
      return;
    }
    if (body && message) {
      body.textContent = message;
    }
    modal.classList.remove('vms-ma-hidden');
  }

  function modalClose() {
    var modal = byId('vms-ma-gated-modal');
    if (!modal) {
      return;
    }
    modal.classList.add('vms-ma-hidden');
  }

  function bindModal() {
    var closeBtn = byId('vms-ma-gated-close');
    if (closeBtn) {
      closeBtn.addEventListener('click', function (evt) {
        evt.preventDefault();
        modalClose();
      });
    }
  }

  function resolveApiButtonsState() {
    var apiReady = !!parseIntSafe(VMS_MA.apiReady, 0);
    var phase = Math.max(1, parseIntSafe(VMS_MA.phase, 1));
    var createBtn = byId('vms-ma-create-paused');
    var goLiveBtn = byId('vms-ma-go-live');
    var reason = byId('vms-ma-api-gating-reason');

    var createGated = (phase < 2) || !apiReady;
    var liveGated = (phase < 3) || !apiReady;

    if (createBtn) {
      createBtn.setAttribute('data-gated', createGated ? '1' : '0');
      createBtn.classList.toggle('button-disabled', createGated);
      createBtn.setAttribute('aria-disabled', createGated ? 'true' : 'false');
    }
    if (goLiveBtn) {
      goLiveBtn.setAttribute('data-gated', liveGated ? '1' : '0');
      goLiveBtn.classList.toggle('button-disabled', liveGated);
      goLiveBtn.setAttribute('aria-disabled', liveGated ? 'true' : 'false');
    }

    if (!reason) {
      return;
    }

    if (!apiReady) {
      reason.innerHTML = 'Create in Meta and Go Live are disabled until API is enabled and credentials are verified. <a href="' + (VMS_MA.settingsUrl || '#') + '">Open Settings</a>.';
      return;
    }
    if (phase < 2) {
      reason.textContent = 'Current phase is copy-only. Save Draft and Export Copy Pack are available.';
      return;
    }
    if (phase < 3) {
      reason.textContent = 'Current phase allows Create in Meta (PAUSED). Go Live remains gated.';
      return;
    }
    reason.textContent = 'API connection is ready.';
  }

  function applyEventData(item, source) {
    var planInput = byId('vms-ma-event-plan-id');
    var venueInput = byId('vms-ma-venue-id');
    var eventName = byId('vms-ma-event-name');
    var venueName = byId('vms-ma-venue-name');
    var eventStart = byId('vms-ma-event-start');
    var destination = byId('vms-ma-destination-url');
    if (!item || !planInput) {
      return;
    }

    lastEventContext = item;

    planInput.value = String(item.id || '');
    if (venueInput) {
      venueInput.value = String(item.venue_id || '');
      dispatchFieldEvents(venueInput);
    }
    if (eventName) {
      eventName.value = String(item.title || '');
      dispatchFieldEvents(eventName);
    }
    if (venueName) {
      venueName.value = String(item.venue_name || '');
      dispatchFieldEvents(venueName);
    }
    if (eventStart) {
      eventStart.value = toLocalDateTime(String(item.start_input || item.start_local || ''));
      dispatchFieldEvents(eventStart);
    }

    if (destination) {
      var ticket = String(item.ticket_url || '').trim();
      var fallback = String(item.event_permalink || '').trim();
      destination.value = ticket || fallback;
      dispatchFieldEvents(destination);
    }

    dispatchFieldEvents(planInput);
    ensureEventSelectionState();
    applyDefaultCopyFromEvent(item, false);
    renderTierPreview();
    renderCopyPackPanel();

    if (source === 'manual') {
      emitFeedback('info', 'Event Plan loaded from manual entry.', 'a');
    }
  }

  function fetchEventPlanById(planId) {
    if (!planId || planId < 1 || !VMS_MA.eventPlansUrl) {
      return Promise.reject(new Error('Invalid Event Plan ID.'));
    }
    return requestAbsolute(VMS_MA.eventPlansUrl + '/' + encodeURIComponent(planId)).then(function (json) {
      if (!json || !json.item) {
        throw new Error('Event Plan not found.');
      }
      return json.item;
    });
  }

  function eventResultText(item) {
    var parts = [];
    if (item && item.start_display) {
      parts.push(item.start_display);
    } else if (item && item.start_local) {
      parts.push(item.start_local);
    }
    if (item && item.venue_name) {
      parts.push(item.venue_name);
    }
    return (item.title || 'Event Plan #' + item.id) + (parts.length ? ' - ' + parts.join(' | ') : '');
  }

  function initEventPicker() {
    var picker = byId('vms-ma-event-plan-picker');
    var manualId = byId('vms-ma-event-plan-id-manual');
    var manualVenue = byId('vms-ma-venue-id-manual');
    var hiddenPlan = byId('vms-ma-event-plan-id');
    var hiddenVenue = byId('vms-ma-venue-id');
    if (!picker || !hiddenPlan) {
      return;
    }

    function parseOptionItem(optionEl) {
      if (!optionEl) {
        return null;
      }
      var raw = optionEl.getAttribute('data-event-item') || '';
      if (!raw) {
        return null;
      }
      try {
        return JSON.parse(raw);
      } catch (e) {
        return null;
      }
    }

    picker.addEventListener('change', function () {
      var selectedId = parseIntSafe(picker.value, 0);
      if (!selectedId) {
        hiddenPlan.value = '';
        dispatchFieldEvents(hiddenPlan);
        ensureEventSelectionState();
        return;
      }
      var selectedOption = picker.options[picker.selectedIndex] || null;
      var fromOption = parseOptionItem(selectedOption);
      if (fromOption) {
        applyEventData(fromOption, 'picker');
        return;
      }
      fetchEventPlanById(selectedId).then(function (item) {
        applyEventData(item, 'picker');
      }).catch(function (err) {
        emitFeedback('error', err.message || 'Unable to load selected Event Plan.', 'a');
      });
    });

    if (manualVenue && hiddenVenue) {
      var mirrorVenue = function () {
        hiddenVenue.value = String(manualVenue.value || '').trim();
        dispatchFieldEvents(hiddenVenue);
      };
      manualVenue.addEventListener('change', mirrorVenue);
      manualVenue.addEventListener('blur', mirrorVenue);
    }

    if (manualId) {
      manualId.addEventListener('blur', function () {
        var id = parseIntSafe(manualId.value, 0);
        if (!id) {
          return;
        }
        fetchEventPlanById(id).then(function (item) {
          if (picker.querySelector('option[value="' + item.id + '"]') === null) {
            picker.add(new Option(eventResultText(item), String(item.id), true, true));
          }
          picker.value = String(item.id);
          applyEventData(item, 'manual');
        }).catch(function (err) {
          emitFeedback('error', err.message || 'Manual Event Plan ID could not be resolved.', 'a');
        });
      });
    }

    var prefillId = parseIntSafe(builder.getAttribute('data-prefill-event-plan-id') || '', 0);
    if (prefillId > 0) {
      fetchEventPlanById(prefillId).then(function (item) {
        if (picker.querySelector('option[value="' + item.id + '"]') === null) {
          picker.appendChild(new Option(eventResultText(item), String(item.id), true, true));
        }
        picker.value = String(item.id);
        applyEventData(item, 'prefill');
      }).catch(function () {
        emitFeedback('warning', 'Could not prefill Event Plan from link. Select one manually.', 'a');
      });
    }

    ensureEventSelectionState();
  }

  function hydratePostAndEventPickers() {
    if (!parseIntSafe(VMS_MA.apiReady, 0)) {
      return;
    }

    var postPicker = byId('vms-ma-post-picker');
    if (postPicker && VMS_MA.postAssetsUrl) {
      requestAbsolute(VMS_MA.postAssetsUrl + '?limit=30').then(function (json) {
        var items = (json && Array.isArray(json.items)) ? json.items : [];
        items.forEach(function (item) {
          var id = String(item.id || '');
          if (!id) {
            return;
          }
          var title = String(item.title || item.source_ref || ('Post #' + id));
          postPicker.appendChild(new Option(title, id));
        });
      }).catch(function () {
        // keep manual fallback
      });
    }

    var eventPicker = byId('vms-ma-fb-event-picker');
    if (eventPicker && VMS_MA.eventPlansUrl) {
      requestAbsolute(VMS_MA.eventPlansUrl + '?after=' + new Date().toISOString().slice(0, 10) + '&days=120&limit=30').then(function (json) {
        var items = (json && Array.isArray(json.items)) ? json.items : [];
        items.forEach(function (item) {
          var fallbackId = String(item.facebook_event_id || item.id || '');
          if (!fallbackId) {
            return;
          }
          eventPicker.appendChild(new Option(eventResultText(item), fallbackId));
        });
      }).catch(function () {
        // keep manual fallback
      });
    }
  }

  function saveDraft() {
    var payload = collectPayload();
    if (!payload.event_plan_id) {
      ensureEventSelectionState();
      emitFeedback('warning', 'Choose an Event Plan first so Step A can autofill required fields.', 'a');
      return;
    }

    var lifetimeClamp = parseIntSafe(VMS_MA.budgetMaxLifetimeMinor, 0);
    if (payload.total_budget_minor > lifetimeClamp) {
      emitFeedback('error', 'Total budget exceeds configured lifetime clamp.', 'e');
      return;
    }

    request('/ad-builds', 'POST', payload).then(function (json) {
      var item = json.item || {};
      if (item.id) {
        buildIdInput.value = String(item.id);
      }
      renderTierPreview();
      renderCopyPackPanel();
      emitFeedback('success', 'Draft saved at ' + new Date().toLocaleTimeString() + '.', 'g');
    }).catch(function (err) {
      emitFeedback('error', err.message || 'Draft save failed.', 'g');
    });
  }

  function exportPack() {
    var id = parseIntSafe(value('vms-ma-build-id'), 0);
    if (!id) {
      emitFeedback('warning', 'Save the draft first.', 'g');
      return;
    }
    request('/ad-builds/' + id + '/export', 'POST', {}).then(function () {
      renderCopyPackPanel();
      emitFeedback('success', 'Copy pack exported.', 'g');
    }).catch(function (err) {
      emitFeedback('error', err.message || 'Export failed.', 'g');
    });
  }

  function isActionGated(buttonId) {
    var btn = byId(buttonId);
    if (!btn) {
      return false;
    }
    return btn.getAttribute('data-gated') === '1';
  }

  function createPaused() {
    if (isActionGated('vms-ma-create-paused')) {
      modalOpen('Create in Meta is gated in this phase. Use Save Draft or Export Copy Pack for now.');
      emitFeedback('warning', 'Create is gated in this phase.', 'g');
      return;
    }

    var id = parseIntSafe(value('vms-ma-build-id'), 0);
    if (!id) {
      emitFeedback('warning', 'Save the draft first.', 'g');
      return;
    }

    request('/ad-builds/' + id + '/meta-create', 'POST', {}).then(function () {
      emitFeedback('success', 'Meta create request accepted.', 'g');
    }).catch(function (err) {
      emitFeedback('error', err.message || 'Create request failed.', 'g');
    });
  }

  function goLive() {
    if (isActionGated('vms-ma-go-live')) {
      modalOpen('Go Live is gated in this phase. Raise phase in Settings when launch controls are approved.');
      emitFeedback('warning', 'Go Live is gated in this phase.', 'g');
      return;
    }

    var id = parseIntSafe(value('vms-ma-build-id'), 0);
    if (!id) {
      emitFeedback('warning', 'Save the draft first.', 'g');
      return;
    }

    request('/ad-builds/' + id + '/meta-go-live', 'POST', {
      confirm_spend: true,
      confirm_reviewed: true
    }).then(function () {
      emitFeedback('success', 'Go Live request accepted.', 'g');
    }).catch(function (err) {
      emitFeedback('error', err.message || 'Go Live request failed.', 'g');
    });
  }

  function parseNumericId(raw) {
    var v = String(raw || '').trim();
    if (!v) {
      return '';
    }
    if (/^\d+$/.test(v)) {
      return v;
    }
    var eventMatch = v.match(/\/events\/(\d+)/i);
    if (eventMatch && eventMatch[1]) {
      return eventMatch[1];
    }
    var postMatch = v.match(/\/posts\/(\d+)/i);
    if (postMatch && postMatch[1]) {
      return postMatch[1];
    }
    return '';
  }

  function syncDerivedState() {
    clampAudienceInputs();
    syncGoalOptimizationVisibility();
    renderTierPreview();
    renderCopyPackPanel();

    var preset = value('vms-ma-preset-mode') || 'flat_run';
    var customWrap = byId('vms-ma-custom-weight-wrap');
    if (customWrap) {
      customWrap.classList.toggle('vms-ma-advanced-hidden', preset !== 'promo_bundle_30_14_7');
    }
  }

  function bindCopyButtons() {
    var copyAll = byId('vms-ma-copy-all');
    var copyUrl = byId('vms-ma-copy-url');
    var downloadPack = byId('vms-ma-download-pack');

    if (copyAll) {
      copyAll.addEventListener('click', function () {
        copyText(buildCopyPackText().text).then(function () {
          emitFeedback('success', 'Copy pack copied.', 'f');
        });
      });
    }
    if (copyUrl) {
      copyUrl.addEventListener('click', function () {
        copyText(buildCopyPackText().utmUrl).then(function () {
          emitFeedback('success', 'URL copied.', 'f');
        });
      });
    }
    if (downloadPack) {
      downloadPack.addEventListener('click', function () {
        downloadText('vms-meta-copy-pack.txt', buildCopyPackText().text);
        emitFeedback('success', 'Copy pack exported.', 'f');
      });
    }
  }

  function bindCreativeHelpers() {
    var postUrl = byId('vms-ma-post-url');
    var postId = byId('vms-ma-post-id');
    var postPicker = byId('vms-ma-post-picker');
    var eventUrl = byId('vms-ma-fb-event-url');
    var eventId = byId('vms-ma-fb-event-id');
    var eventPicker = byId('vms-ma-fb-event-picker');

    if (postUrl && postId) {
      postUrl.addEventListener('blur', function () {
        var parsed = parseNumericId(postUrl.value);
        if (!parsed && String(postUrl.value || '').trim()) {
          emitFeedback('error', 'Could not parse Post ID. Paste a post URL ending in /posts/{id} or the numeric ID.', 'c');
          return;
        }
        postId.value = parsed;
      });
    }
    if (postPicker && postId) {
      postPicker.addEventListener('change', function () {
        postId.value = String(postPicker.value || '');
      });
    }

    if (eventUrl && eventId) {
      eventUrl.addEventListener('blur', function () {
        var parsed = parseNumericId(eventUrl.value);
        if (!parsed && String(eventUrl.value || '').trim()) {
          emitFeedback('error', 'Could not parse Facebook Event ID. Paste an event URL ending in /events/{id} or the numeric ID.', 'c');
          return;
        }
        eventId.value = parsed;
      });
    }
    if (eventPicker && eventId) {
      eventPicker.addEventListener('change', function () {
        eventId.value = String(eventPicker.value || '');
      });
    }
  }

  function bindRegenerate() {
    var regen = byId('vms-ma-copy_regen');
    if (!regen) {
      return;
    }
    regen.addEventListener('click', function (evt) {
      evt.preventDefault();
      applyDefaultCopyFromEvent(lastEventContext || null, true);
      renderCopyPackPanel();
      emitFeedback('info', 'Creative text regenerated from event.', 'c');
    });
  }

  function applyDefaultPreset() {
    var presetEl = byId('vms-ma-preset-mode');
    if (!presetEl) {
      return;
    }
    var preset = String(VMS_MA.defaultPreset || 'flat_run');
    if (presetEl.querySelector('option[value="' + preset + '"]')) {
      presetEl.value = preset;
      dispatchFieldEvents(presetEl);
    }
  }

  builder.addEventListener('click', function (evt) {
    var target = evt.target;
    if (!target) {
      return;
    }
    if (target.id === 'vms-ma-save-draft') {
      evt.preventDefault();
      saveDraft();
      return;
    }
    if (target.id === 'vms-ma-export-pack') {
      evt.preventDefault();
      exportPack();
      return;
    }
    if (target.id === 'vms-ma-create-paused') {
      evt.preventDefault();
      createPaused();
      return;
    }
    if (target.id === 'vms-ma-go-live') {
      evt.preventDefault();
      goLive();
    }
  });

  document.addEventListener('vms-ma-toast', function (evt) {
    var detail = evt && evt.detail ? evt.detail : {};
    if (!detail.message) {
      return;
    }
    emitFeedback(detail.type || 'info', detail.message, detail.stepKey || 'g');
  });

  builder.addEventListener('change', function (evt) {
    var target = evt.target;
    if (!target) {
      return;
    }
    if (target.name === 'vms_ma_creative_mode') {
      syncCreativeFields();
    }
    syncDerivedState();
  });

  builder.addEventListener('input', function () {
    syncDerivedState();
  });

  initEventPicker();
  bindModal();
  applyDefaultPreset();
  syncCreativeFields();
  bindCopyButtons();
  bindCreativeHelpers();
  bindRegenerate();
  updateApiAssistVisibility();
  hydratePostAndEventPickers();
  resolveApiButtonsState();
  syncDerivedState();

  if (builder.getAttribute('data-copy-pack-hint') === '1') {
    emitFeedback('info', 'Copy Pack mode: complete Steps A-E, then use Export Copy Pack.', 'g');
    var exportBtn = byId('vms-ma-export-pack');
    if (exportBtn) {
      exportBtn.classList.add('button-primary');
    }
  }
})();
