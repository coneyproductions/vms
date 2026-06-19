(function ($) {
  function vmsGet(obj, path, fallback) {
    try {
      const parts = path.split('.');
      let cur = obj;
      for (let i = 0; i < parts.length; i++) {
        if (!cur || typeof cur !== 'object') return fallback;
        cur = cur[parts[i]];
      }
      return (typeof cur === 'undefined') ? fallback : cur;
    } catch (e) {
      return fallback;
    }
  }

  function escapeHtml(str) {
    if (str === null || typeof str === 'undefined') return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function escapeAttr(str) {
    return escapeHtml(str);
  }

  function parseWpDatetimeLocal(str) {
    if (!str || typeof str !== 'string') return null;
    const parts = str.trim().split(' ');
    if (parts.length !== 2) return null;

    const d = parts[0].split('-').map(function (n) { return parseInt(n, 10); });
    const t = parts[1].split(':').map(function (n) { return parseInt(n, 10); });
    if (d.length !== 3 || t.length < 2) return null;

    const y = d[0];
    const m = d[1] - 1;
    const day = d[2];
    const hh = t[0] || 0;
    const mm = t[1] || 0;
    const ss = t[2] || 0;
    return new Date(y, m, day, hh, mm, ss);
  }

  function formatRangeLabel(range, startStr, endStr) {
    const start = parseWpDatetimeLocal(startStr);
    const end = parseWpDatetimeLocal(endStr);
    if (!start || !end) return '';

    const endInclusive = new Date(end.getTime() - 1000);
    const optsOne = { weekday: 'short', month: 'short', day: 'numeric' };
    const optsTwo = { month: 'short', day: 'numeric' };

    if (range === 'today') {
      return start.toLocaleDateString([], optsOne);
    }

    const sameMonth = start.getMonth() === endInclusive.getMonth() && start.getFullYear() === endInclusive.getFullYear();
    const left = start.toLocaleDateString([], optsOne);
    const right = endInclusive.toLocaleDateString([], sameMonth ? optsTwo : optsOne);
    return left + ' – ' + right;
  }

  function ensureMetaEl($panel) {
    const $wrap = $panel.closest('section');
    let $meta = $wrap.find('.vms-panel-range').first();
    if (!$meta.length) {
      $meta = $('<div class="vms-panel-range"></div>');
      $wrap.find('h2').after($meta);
    }
    return $meta;
  }

  function renderMeta($panel, res, range) {
    if (range === 'staffing') {
      ensureMetaEl($panel).text('');
      return;
    }
    const label = formatRangeLabel(range, res && res.range_start, res && res.range_end);
    ensureMetaEl($panel).text(label || '');
  }

  function fmtYmd(ymd) {
    if (!ymd) return '';
    const parts = String(ymd).split('-');
    if (parts.length !== 3) return String(ymd);

    const y = parseInt(parts[0], 10);
    const m = parseInt(parts[1], 10) - 1;
    const d = parseInt(parts[2], 10);
    if (isNaN(y) || isNaN(m) || isNaN(d)) return String(ymd);

    const dt = new Date(y, m, d);
    return dt.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' });
  }

  function renderItems($panel, items) {
    if (!items || !items.length) {
      $panel.text('No events found.');
      return;
    }

    const html = items.map(function (it) {
      const title = it.title || '(untitled)';
      const status = it.status ? (' [' + it.status + ']') : '';
      const venue = it.venue_name ? (' · ' + it.venue_name) : '';

      let timeStr = '';
      if (it.start_ts) {
        const d = new Date(it.start_ts * 1000);
        timeStr = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) + ' — ';
      }

      const lineText = timeStr + title + status + venue;

      const isBlocked = !!it.payment_blocked;
      const bypassActive = !!it.tax_bypass_active;
      const bypassUntil = fmtYmd(it.tax_bypass_until || '');
      let badgeHtml = '';
      if (isBlocked) {
        badgeHtml = '<span class="vms-bill-badge vms-bill-badge--blocked">Payment blocked</span>';
      } else if (bypassActive) {
        badgeHtml = '<span class="vms-bill-badge vms-bill-badge--bypass">Bypass until ' + (bypassUntil || '—') + '</span>';
      }

      const lineHtml = escapeHtml(lineText) + (badgeHtml ? (' ' + badgeHtml) : '');
      if (it.edit_link) {
        return '<div><a href="' + escapeAttr(it.edit_link) + '">' + lineHtml + '</a></div>';
      }
      return '<div>' + lineHtml + '</div>';
    }).join('');

    $panel.html(html);
  }

  function renderBills($panel, res) {
    const items = (res && res.items) ? res.items : [];
    const totalFmt = res && res.known_total_fmt ? String(res.known_total_fmt) : '';
    const totalHtml = totalFmt ? ('<div class="vms-bills-total">Known total: ' + totalFmt + '</div>') : '';

    let needsCount = 0;
    let missingVendorCount = 0;
    let missingAmountCount = 0;
    let taxIncompleteCount = 0;

    if (Array.isArray(items)) {
      items.forEach(function (it) {
        const reasons = Array.isArray(it && it.needs_attention_reasons) ? it.needs_attention_reasons : [];
        if (!reasons.length) return;
        needsCount += 1;
        if (reasons.indexOf('missing_vendor') !== -1) missingVendorCount += 1;
        if (reasons.indexOf('missing_amount') !== -1) missingAmountCount += 1;
        if (reasons.indexOf('tax_incomplete') !== -1) taxIncompleteCount += 1;
      });
    }

    const summaryParts = [];
    if (missingVendorCount) summaryParts.push(missingVendorCount + ' missing vendor');
    if (missingAmountCount) summaryParts.push(missingAmountCount + ' missing amount');
    if (taxIncompleteCount) summaryParts.push(taxIncompleteCount + ' payment blocked');

    const summaryHtml = needsCount
      ? ('<div class="vms-bills-attn-summary vms-text-danger">🚩 Needs attention: ' + needsCount + (summaryParts.length ? (' (' + summaryParts.join(', ') + ')') : '') + '</div>')
      : '';

    if (!items || !items.length) {
      $panel.html(totalHtml + summaryHtml + '<div class="vms-empty">No upcoming bills found.</div>');
      return;
    }

    const html = items.map(function (it) {
      const due = fmtYmd(it.due_date || '');
      const event = fmtYmd(it.event_date || '');

      const reasons = Array.isArray(it.needs_attention_reasons) ? it.needs_attention_reasons : [];
      const isBlocked = !!it.payment_blocked || (reasons.indexOf('tax_incomplete') !== -1);
      const bypassActive = !!it.tax_bypass_active;
      const bypassUntil = fmtYmd(it.tax_bypass_until || '');
      let badgeHtml = '';
      if (isBlocked) {
        badgeHtml = '<span class="vms-bill-badge vms-bill-badge--blocked">Payment blocked</span>';
      } else if (bypassActive) {
        badgeHtml = '<span class="vms-bill-badge vms-bill-badge--bypass">Bypass active until ' + (bypassUntil || '—') + '</span>';
      }

      const needsAttention = reasons.length > 0;
      const vendor = it.vendor_name || '(no vendor)';
      const venue = it.venue_name ? (' · ' + it.venue_name) : '';
      const amount = it.known_amount_fmt ? String(it.known_amount_fmt) : 'TBD';
      const struct = it.structure_label ? (' · ' + it.structure_label) : '';

      const dueLabel = due ? ('Due: ' + due + (it.is_estimated ? ' (est.)' : '')) : '';
      const flagIcon = needsAttention ? '<span class="vms-bill-flag" aria-label="Needs attention">🚩</span> ' : '';

      const detailParts = [];
      if (event) detailParts.push('Event: ' + event);
      if (struct) detailParts.push(struct.replace(/^ · /, 'Structure: '));
      if (amount) detailParts.push('Known: ' + amount);

      const attnParts = [];
      if (reasons.indexOf('missing_vendor') !== -1) attnParts.push('Missing vendor');
      if (reasons.indexOf('missing_amount') !== -1) attnParts.push('Missing amount');
      if (attnParts.length) {
        detailParts.push('<span class="vms-bill-attn vms-text-danger">🚩 ' + attnParts.join(', ') + '</span>');
      }

      const top = '<div class="vms-bill-line">' + flagIcon + (dueLabel ? (dueLabel + ' · ') : '') + vendor + venue + (badgeHtml ? (' ' + badgeHtml) : '') + '</div>';
      const sub = '<div class="vms-bill-sub">' + detailParts.join(' · ') + '</div>';

      const actions = [];
      if (isBlocked && it.vendor_edit_link) {
        actions.push('<a class="vms-bill-action" href="' + it.vendor_edit_link + '">Fix tax / bypass</a>');
      }
      if (it.edit_link) {
        if (reasons.indexOf('missing_vendor') !== -1) actions.push('<a class="vms-bill-action" href="' + it.edit_link + '">Assign vendor</a>');
        if (reasons.indexOf('missing_amount') !== -1) actions.push('<a class="vms-bill-action" href="' + it.edit_link + '">Set pay</a>');
        actions.push('<a class="vms-bill-action" href="' + it.edit_link + '">Open plan</a>');
      }

      const actionsHtml = actions.length ? ('<div class="vms-bill-actions">' + actions.join(' · ') + '</div>') : '';
      return '<div class="vms-bill-item">' + top + sub + actionsHtml + '</div>';
    }).join('');

    $panel.html(totalHtml + summaryHtml + '<div class="vms-bills-list">' + html + '</div>');
  }

  function renderDue($panel, res) {
    const items = (res && res.items) ? res.items : [];
    const c = (res && res.counts) ? res.counts : {};
    const allUrl = vmsGet(window, 'VMS_DASH.dueAllUrl', '');

    const topActions = allUrl
      ? ('<div class="vms-due-actions"><a class="button button-small" href="' + allUrl + '">View all / configure</a></div>')
      : '';

    if (!items.length) {
      const active = (res && typeof res.active_obligations !== 'undefined') ? parseInt(res.active_obligations, 10) : 0;
      const empty = '<div class="vms-empty">' + (active ? 'No due dates in this window.' : 'No obligations configured yet.') + '</div>';
      $panel.html(empty + topActions);
      return;
    }

    const overdueCount = parseInt(c.overdue || 0, 10) || 0;
    const due7Count = parseInt(c.due_7 || 0, 10) || 0;
    const due14Count = parseInt(c.due_14 || 0, 10) || 0;
    const due30Count = parseInt(c.due_30 || 0, 10) || 0;
    const cfgAttnCount = parseInt(c.needs_attention || 0, 10) || 0;

    const needsParts = [];
    if (overdueCount) needsParts.push(overdueCount + ' overdue');
    if (due7Count) needsParts.push(due7Count + ' due in 7');
    if (cfgAttnCount) needsParts.push(cfgAttnCount + ' needs setup');
    const needsCount = overdueCount + due7Count + cfgAttnCount;

    const attnHtml = needsCount
      ? ('<div class="vms-due-attn-summary vms-text-danger">🚩 Needs attention: ' + needsCount + (needsParts.length ? (' (' + needsParts.join(', ') + ')') : '') + '</div>')
      : '';

    const summaryParts = [];
    if (overdueCount) summaryParts.push('🚩 Overdue: ' + overdueCount);
    if (due7Count) summaryParts.push('Due in 7: ' + due7Count);
    if (due14Count) summaryParts.push('Due in 14: ' + due14Count);
    if (due30Count) summaryParts.push('Due in 30: ' + due30Count);
    const summaryHtml = summaryParts.length ? ('<div class="vms-due-summary">' + summaryParts.join(' · ') + '</div>') : '';

    const rows = items.map(function (it) {
      const dueLbl = it.due_label || it.due_date || '';
      const payee = it.payee_name || '(no payee)';
      const title = it.title || '(untitled)';
      const needsSetup = !!it.needs_attention;

      const flag = (it.status === 'overdue' || needsSetup) ? '🚩 ' : ((it.status === 'due_soon') ? '⚠️ ' : '');
      const statusClass = (it.status === 'overdue' || needsSetup)
        ? ' vms-is-overdue vms-needs-attn'
        : ((it.status === 'due_soon') ? ' vms-is-soon vms-needs-attn' : '');

      let sub = '';
      if (it.identifier) {
        sub = '<div class="vms-due-sub">Account ID: ' + escapeHtml(it.identifier) + '</div>';
      }
      if (needsSetup) {
        sub += '<div class="vms-due-sub vms-text-danger">🚩 Missing payee</div>';
      }

      const actions = [];
      if (it.obligation_id && it.due_date) {
        actions.push('<button type="button" class="button button-small vms-due-complete" data-obligation="' + escapeAttr(it.obligation_id) + '" data-due="' + escapeAttr(it.due_date) + '">Mark complete</button>');
      }

      const links = [];
      if (allUrl) {
        if (it.obligation_id) links.push('<a class="vms-due-action" href="' + allUrl + '#vms-ob-' + escapeAttr(it.obligation_id) + '">Open obligation</a>');
        if (it.payee_id) links.push('<a class="vms-due-action" href="' + allUrl + '#vms-payee-' + escapeAttr(it.payee_id) + '">Open payee</a>');
        links.push('<a class="vms-due-action" href="' + allUrl + '#vms-due-log">View log</a>');
      }
      const linksHtml = links.length ? ('<div class="vms-due-links">' + links.join(' · ') + '</div>') : '';

      return (
        '<div class="vms-due-row' + statusClass + '">' +
          '<div class="vms-due-main">' +
            '<div class="vms-due-title">' + flag + 'Due: <strong>' + escapeHtml(dueLbl) + '</strong> · ' + escapeHtml(title) + ' · ' + escapeHtml(payee) + '</div>' +
            sub +
            linksHtml +
          '</div>' +
          '<div class="vms-due-buttons">' + actions.join(' ') + '</div>' +
        '</div>'
      );
    }).join('');

    $panel.html(attnHtml + summaryHtml + topActions + '<div class="vms-due-list">' + rows + '</div>');
  }

  function renderFinancial($panel, res) {
    const s = (res && res.summary) ? res.summary : {};

    const knownTotal = String(s.known_total_fmt || '$0.00');
    const billsTotal = parseInt(s.bills_total || 0, 10) || 0;
    const billsAttn = parseInt(s.bills_needs_attention || 0, 10) || 0;
    const missingVendor = parseInt(s.bills_missing_vendor || 0, 10) || 0;
    const missingAmount = parseInt(s.bills_missing_amount || 0, 10) || 0;
    const blocked = parseInt(s.bills_payment_blocked || 0, 10) || 0;
    const billsWindow = parseInt(s.bills_window_days || 0, 10) || 0;

    const dueActive = parseInt(s.due_active_obligations || 0, 10) || 0;
    const dueWindowItems = parseInt(s.due_window_items || 0, 10) || 0;
    const dueOverdue = parseInt(s.due_overdue || 0, 10) || 0;
    const due7 = parseInt(s.due_7 || 0, 10) || 0;
    const due14 = parseInt(s.due_14 || 0, 10) || 0;
    const dueNeeds = parseInt(s.due_needs_attention || 0, 10) || 0;
    const dueWindow = parseInt(s.due_window_days || 0, 10) || 0;

    const allUrl = vmsGet(window, 'VMS_DASH.dueAllUrl', '');

    function card(title, value, meta, tone, linkHtml) {
      const toneClass = tone ? (' vms-fin-card--' + tone) : '';
      const action = linkHtml ? ('<div class="vms-fin-card__action">' + linkHtml + '</div>') : '';
      return (
        '<article class="vms-fin-card' + toneClass + '">' +
          '<div class="vms-fin-card__title">' + escapeHtml(title) + '</div>' +
          '<div class="vms-fin-card__value">' + escapeHtml(String(value)) + '</div>' +
          '<div class="vms-fin-card__meta">' + escapeHtml(meta || '') + '</div>' +
          action +
        '</article>'
      );
    }

    const cards = [];
    cards.push(card(
      'Upcoming Bills (Known)',
      knownTotal,
      billsWindow > 0 ? ('Window: next ' + billsWindow + ' days') : 'Upcoming bills window',
      billsAttn > 0 ? 'warn' : 'ok',
      '<a href="#vms-dashboard-bills">Open Upcoming Bills</a>'
    ));

    cards.push(card(
      'Bills in Window',
      billsTotal,
      billsAttn > 0 ? (billsAttn + ' need attention') : 'No bill alerts',
      billsAttn > 0 ? 'warn' : 'ok',
      '<a href="#vms-dashboard-bills">Review bills</a>'
    ));

    cards.push(card(
      'Bill Setup Alerts',
      billsAttn,
      'Missing vendor: ' + missingVendor + ' · Missing amount: ' + missingAmount + ' · Blocked: ' + blocked,
      billsAttn > 0 ? 'danger' : 'ok',
      '<a href="#vms-dashboard-bills">Resolve now</a>'
    ));

    cards.push(card(
      'Overdue Obligations',
      dueOverdue,
      dueWindow > 0 ? ('Window: next ' + dueWindow + ' days') : 'Due-date window',
      dueOverdue > 0 ? 'danger' : 'ok',
      '<a href="#vms-dashboard-due">Open Due Dates</a>'
    ));

    cards.push(card(
      'Due in 7 Days',
      due7,
      'Due in 14: ' + due14 + ' · Items in window: ' + dueWindowItems,
      due7 > 0 ? 'warn' : 'ok',
      '<a href="#vms-dashboard-due">Plan upcoming</a>'
    ));

    cards.push(card(
      'Due Setup Alerts',
      dueNeeds,
      'Active obligations: ' + dueActive,
      dueNeeds > 0 ? 'danger' : 'ok',
      allUrl ? ('<a href="' + escapeAttr(allUrl) + '">Configure obligations</a>') : '<a href="#vms-dashboard-due">Review setup</a>'
    ));

    const stripParts = [];
    if (dueOverdue > 0) stripParts.push(dueOverdue + ' overdue obligation' + (dueOverdue === 1 ? '' : 's'));
    if (billsAttn > 0) stripParts.push(billsAttn + ' bill item' + (billsAttn === 1 ? '' : 's') + ' need attention');

    const strip = stripParts.length
      ? ('<div class="vms-fin-alert-strip">🚩 ' + escapeHtml(stripParts.join(' · ')) + '</div>')
      : '<div class="vms-fin-alert-strip vms-fin-alert-strip--ok">No immediate financial alerts.</div>';

    $panel.html(strip + '<div class="vms-fin-grid">' + cards.join('') + '</div>');
  }

  function staffingTone(status) {
    const s = String(status || '').toLowerCase();
    if (s === 'ready') return 'ready';
    if (s === 'needs_staff') return 'needs';
    if (s === 'red_flag') return 'red';
    return 'na';
  }

  function staffingMaskLabels(mask) {
    const m = parseInt(mask || 0, 10) || 0;
    const labels = [];
    if (m & 1) labels.push('Critical unfilled');
    if (m & 2) labels.push('Overlap conflict');
    if (m & 4) labels.push('Unavailable assigned');
    return labels;
  }

  function renderStaffing($panel, res) {
    const items = (res && Array.isArray(res.items)) ? res.items : [];
    if (!items.length) {
      $panel.html('<div class="vms-empty">No upcoming staffing items found.</div>');
      return;
    }

    let redFlags = 0;
    let needsStaff = 0;
    let openTotal = 0;
    items.forEach(function (it) {
      const status = String(it && it.readiness_status ? it.readiness_status : '');
      if (status === 'red_flag') redFlags += 1;
      if (status === 'needs_staff') needsStaff += 1;
      openTotal += (parseInt(it && it.open_headcount_total ? it.open_headcount_total : 0, 10) || 0);
    });

    const summaryBits = [];
    summaryBits.push('Events: ' + items.length);
    summaryBits.push('Open headcount: ' + openTotal);
    if (needsStaff > 0) summaryBits.push('Needs staff: ' + needsStaff);
    if (redFlags > 0) summaryBits.push('Red flags: ' + redFlags);
    const summaryHtml = '<div class="vms-staffing-summary">' + escapeHtml(summaryBits.join(' · ')) + '</div>';

    const rows = items.map(function (it) {
      const title = String(it && it.title ? it.title : '(untitled)');
      const eventDate = fmtYmd(it && it.event_date ? it.event_date : '');
      const eventTime = (it && it.start_time) ? String(it.start_time) : '';
      const venueName = (it && it.venue_name) ? String(it.venue_name) : '';
      const readinessLabel = String(it && it.readiness_label ? it.readiness_label : 'N/A');
      const readinessStatus = String(it && it.readiness_status ? it.readiness_status : 'not_applicable');
      const tone = staffingTone(readinessStatus);
      const openHeadcount = parseInt(it && it.open_headcount_total ? it.open_headcount_total : 0, 10) || 0;
      const estCost = String(it && it.est_labor_cost_total_fmt ? it.est_labor_cost_total_fmt : 'Unknown');
      const editLink = String(it && it.edit_link ? it.edit_link : '');
      const maskLabels = staffingMaskLabels(it && it.red_flag_reason_mask ? it.red_flag_reason_mask : 0);

      const titleHtml = editLink
        ? ('<a href="' + escapeAttr(editLink) + '">' + escapeHtml(title) + '</a>')
        : escapeHtml(title);

      const metaParts = [];
      if (eventDate) metaParts.push(eventDate);
      if (eventTime) metaParts.push(eventTime);
      if (venueName) metaParts.push(venueName);
      const metaHtml = metaParts.length ? ('<div class="vms-staffing-meta">' + escapeHtml(metaParts.join(' · ')) + '</div>') : '';

      const missingItems = vmsGet(it, 'missing_summary.items', []);
      const conflictItems = vmsGet(it, 'conflict_summary.items', []);
      const missingList = Array.isArray(missingItems) ? missingItems.slice(0, 3) : [];
      const conflictList = Array.isArray(conflictItems) ? conflictItems.slice(0, 3) : [];

      const missingHtml = missingList.length
        ? (
          '<div class="vms-staffing-minihead">Top Missing</div>' +
          missingList.map(function (m) {
            const role = String(m && m.role_name ? m.role_name : 'Role');
            const need = parseInt(m && m.need ? m.need : 0, 10) || 0;
            const filled = parseInt(m && m.filled ? m.filled : 0, 10) || 0;
            const open = parseInt(m && m.open ? m.open : 0, 10) || 0;
            const crit = !!(m && m.is_critical);
            return '<div class="vms-staffing-detail-row">' + escapeHtml(role + ': need ' + need + ', filled ' + filled + ', open ' + open + (crit ? ' (critical)' : '')) + '</div>';
          }).join('')
        )
        : '<div class="vms-staffing-subtle">No missing roles in summary.</div>';

      const conflictHtml = conflictList.length
        ? (
          '<div class="vms-staffing-minihead">Top Conflicts</div>' +
          conflictList.map(function (c) {
            const who = String(c && c.staff_name ? c.staff_name : 'Staff');
            const summary = String(c && c.summary ? c.summary : '');
            return '<div class="vms-staffing-detail-row">' + escapeHtml(who + (summary ? (' · ' + summary) : '')) + '</div>';
          }).join('')
        )
        : '<div class="vms-staffing-subtle">No conflict items in summary.</div>';

      const maskHtml = maskLabels.length
        ? ('<div class="vms-staffing-mask">🚩 ' + escapeHtml(maskLabels.join(' · ')) + '</div>')
        : '';

      return (
        '<details class="vms-staffing-item">' +
          '<summary class="vms-staffing-main">' +
            '<div class="vms-staffing-main-left">' +
              '<div class="vms-staffing-title">' + titleHtml + '</div>' +
              metaHtml +
            '</div>' +
            '<div class="vms-staffing-main-right">' +
              '<span class="vms-staffing-pill vms-staffing-pill--' + tone + '">' + escapeHtml(readinessLabel) + '</span>' +
              '<span class="vms-staffing-stat">Open: <strong>' + escapeHtml(String(openHeadcount)) + '</strong></span>' +
              '<span class="vms-staffing-stat">Est: <strong>' + escapeHtml(estCost) + '</strong></span>' +
            '</div>' +
          '</summary>' +
          '<div class="vms-staffing-detail">' +
            maskHtml +
            '<div class="vms-staffing-detail-grid">' +
              '<div>' + missingHtml + '</div>' +
              '<div>' + conflictHtml + '</div>' +
            '</div>' +
          '</div>' +
        '</details>'
      );
    }).join('');

    $panel.html(summaryHtml + '<div class="vms-staffing-list">' + rows + '</div>');
  }

  function getStaffingN() {
    const raw = parseInt(String($('#vms-staffing-n').val() || ''), 10);
    return (raw === 5 || raw === 10 || raw === 20) ? raw : 10;
  }

  function getStaffingIncludeDrafts() {
    return $('#vms-staffing-include-drafts').is(':checked') ? 1 : 0;
  }

  function getDebugFlag() {
    const v = new URLSearchParams(window.location.search).get('vms_debug');
    return (v === '1' || v === 'true') ? v : '';
  }

  function pickCheckbox(selectors) {
    for (let i = 0; i < selectors.length; i++) {
      const $x = $(selectors[i]);
      if ($x.length) return $x;
    }
    return $();
  }

  function getBool($el) {
    return ($el && $el.length && $el.is(':checked')) ? 1 : 0;
  }

  function getFilters() {
    const $onlyOpen = pickCheckbox(['#vms-only-open', 'input[name="only_open"]']);
    const $incCan = pickCheckbox(['#vms-include-canceled', 'input[name="include_canceled"]']);
    const $incDr = pickCheckbox(['#vms-include-drafts', 'input[name="include_drafts"]']);

    return {
      only_open: getBool($onlyOpen),
      include_canceled: getBool($incCan),
      include_drafts: getBool($incDr)
    };
  }

  function getDashVenueId() {
    const $scope = $('#vms-dash-scope');
    const scopeVal = ($scope.length ? $scope.val() : 'venue') || 'venue';

    const $venue = $('#vms-dash-venue-select');
    let venueVal = ($venue.length ? $venue.val() : '0') || '0';
    if (scopeVal === 'all') {
      venueVal = '0';
    }
    return String(venueVal || '0');
  }

  function buildErrorMessage(xhr, debugFlag) {
    let msg = 'Error loading data.';
    if (!debugFlag) return msg;

    const code = (xhr && xhr.status) ? xhr.status : '';
    const st = (xhr && xhr.statusText) ? xhr.statusText : '';
    let body = (xhr && xhr.responseText) ? String(xhr.responseText) : '';
    if (body.length > 600) body = body.slice(0, 600) + '…';

    msg = msg + (code ? (' (HTTP ' + code + (st ? (' ' + st) : '') + ')') : '');
    if (body) msg = msg + '\n\n' + body;
    return msg;
  }

  const inflight = { financial: null, today: null, week: null, staffing: null, bills: null, due: null };
  const seq = { financial: 0, today: 0, week: 0, staffing: 0, bills: 0, due: 0 };

  function fetchPanel(range) {
    const restUrl = vmsGet(window, 'VMS_DASH.restUrl', '');
    const nonce = vmsGet(window, 'VMS_DASH.nonce', '');
    const debugFlag = getDebugFlag();

    const $panel = $('.vms-panel-body[data-panel="' + range + '"]');
    if (!$panel.length) return;

    if (!restUrl || !nonce) {
      $panel.text('Error loading data.');
      return;
    }

    if (inflight[range] && inflight[range].abort) {
      inflight[range].abort();
    }

    const mySeq = ++seq[range];
    const filters = getFilters();
    const data = {
      range: range,
      venue_id: getDashVenueId(),
      only_open: filters.only_open,
      include_canceled: filters.include_canceled,
      include_drafts: filters.include_drafts
    };
    if (range === 'staffing') {
      data.staffing_n = getStaffingN();
      data.staffing_include_drafts = getStaffingIncludeDrafts();
    }
    if (debugFlag) data.vms_debug = debugFlag;

    $panel.text('Loading…');

    inflight[range] = $.ajax({
      url: restUrl,
      method: 'GET',
      headers: { 'X-WP-Nonce': nonce },
      data: data
    })
      .done(function (res) {
        if (mySeq !== seq[range]) return;

        renderMeta($panel, res, range);
        if (range === 'financial') {
          renderFinancial($panel, res);
        } else if (range === 'staffing') {
          renderStaffing($panel, res);
        } else if (range === 'bills') {
          renderBills($panel, res);
        } else if (range === 'due') {
          renderDue($panel, res);
        } else {
          renderItems($panel, (res && res.items) ? res.items : []);
        }
      })
      .fail(function (xhr, status) {
        if (status === 'abort') return;
        if (mySeq !== seq[range]) return;

        renderMeta($panel, null, range);
        $panel.text(buildErrorMessage(xhr, debugFlag));
      });
  }

  function refreshAll() {
    fetchPanel('financial');
    fetchPanel('today');
    fetchPanel('week');
    fetchPanel('staffing');
    fetchPanel('bills');
    fetchPanel('due');
  }

  function syncScopeDisables() {
    const $scope = $('#vms-dash-scope');
    const $venue = $('#vms-dash-venue-select');
    if (!$scope.length || !$venue.length) return;

    const scopeVal = String($scope.val() || 'venue');
    $venue.prop('disabled', scopeVal === 'all');
  }

  $(document).ready(function () {
    syncScopeDisables();

    $(document).on('click', '.vms-due-complete', function (e) {
      e.preventDefault();

      const $btn = $(this);
      const oid = String($btn.attr('data-obligation') || '');
      const due = String($btn.attr('data-due') || '');
      if (!oid || !due) return;

      if (!window.confirm('Mark this due date as complete?')) return;

      const notes = window.prompt('Optional note for this completion:', '');
      if (notes === null) return;

      const proofUrl = window.prompt('Optional proof URL (receipt/document link):', '');
      if (proofUrl === null) return;

      const url = vmsGet(window, 'VMS_DASH.dueCompleteUrl', '');
      const nonce = vmsGet(window, 'VMS_DASH.nonce', '');
      if (!url || !nonce) {
        window.alert('Missing endpoint configuration.');
        return;
      }

      $btn.prop('disabled', true);

      $.ajax({
        url: url,
        method: 'POST',
        headers: { 'X-WP-Nonce': nonce },
        data: { obligation_id: oid, due_date: due, notes: notes, proof_url: proofUrl }
      })
        .done(function (resp) {
          if (!resp || resp.ok !== true) {
            const err = (resp && resp.error) ? String(resp.error) : 'unknown_error';
            window.alert('Unable to mark complete: ' + err + '.');
            return;
          }

          if ($('#vms-dashboard-due .vms-panel-body[data-panel="due"]').length) {
            fetchPanel('due');
            fetchPanel('financial');
          } else {
            window.location.reload();
          }
        })
        .fail(function () {
          window.alert('Error marking complete.');
        })
        .always(function () {
          $btn.prop('disabled', false);
        });
    });

    $(document).on(
      'change',
      '#vms-dash-scope, #vms-dash-venue-select, #vms-only-open, #vms-include-canceled, #vms-include-drafts, #vms-staffing-n, #vms-staffing-include-drafts, input[name="only_open"], input[name="include_canceled"], input[name="include_drafts"]',
      function () {
        syncScopeDisables();
        refreshAll();
      }
    );

    refreshAll();
  });
})(jQuery);
