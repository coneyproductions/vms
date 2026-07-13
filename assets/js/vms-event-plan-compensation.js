(function () {
  function initVenueCompDefaults() {
    var postForm = document.getElementById('post');
    var venueSel = document.getElementById('vms_venue_id');
    var dateInp = document.getElementById('vms_event_date');
    var autoChk = document.getElementById('vms_auto_comp_venue');
    var hint = document.getElementById('vms-venue-defaults-hint');
    var fStruct = document.getElementById('vms_comp_structure');
    var fFlat = document.getElementById('vms_flat_fee_amount');
    var fSplit = document.getElementById('vms_door_split_percent');
    var fBonusMode = document.getElementById('vms_attendance_bonus_mode');
    var fBonusStart = document.getElementById('vms_attendance_bonus_start_count');
    var fBonusStepSize = document.getElementById('vms_attendance_bonus_step_size');
    var fBonusStepBonus = document.getElementById('vms_attendance_bonus_step_bonus');
    var fBonusPerTicket = document.getElementById('vms_attendance_bonus_per_ticket_rate');
    var fBonusMax = document.getElementById('vms_attendance_bonus_max_bonus');
    var selInp = document.getElementById('vms_comp_selected_option');
    var pkgInp = document.getElementById('vms_comp_package_id');
    var optionsWrap = document.getElementById('vms-comp-options');
    var dirty = false;
    var lastAutoAppliedSig = '';
    var trackedFields = [
      fStruct,
      fFlat,
      fSplit,
      fBonusMode,
      fBonusStart,
      fBonusStepSize,
      fBonusStepBonus,
      fBonusPerTicket,
      fBonusMax
    ];

    if (!postForm || !venueSel || !dateInp || !autoChk || !fStruct) {
      return false;
    }
    if (postForm.dataset.vmsCompVenueDefaultsBound === '1') {
      return true;
    }
    postForm.dataset.vmsCompVenueDefaultsBound = '1';

    trackedFields.forEach(function (el) {
      if (!el) return;
      el.addEventListener('change', function () {
        dirty = true;
      });
      el.addEventListener('input', function () {
        dirty = true;
      });
    });

    function isBlank(val) {
      return val === null || val === undefined || String(val).trim() === '';
    }

    function draftHasValues() {
      var flat = fFlat ? fFlat.value : '';
      var split = fSplit ? fSplit.value : '';
      var bonusMode = fBonusMode ? fBonusMode.value : '';
      var bonusStart = fBonusStart ? fBonusStart.value : '';
      var stepSize = fBonusStepSize ? fBonusStepSize.value : '';
      var stepBonus = fBonusStepBonus ? fBonusStepBonus.value : '';
      var perTicket = fBonusPerTicket ? fBonusPerTicket.value : '';
      var maxBonus = fBonusMax ? fBonusMax.value : '';

      return (
        !isBlank(flat) ||
        !isBlank(split) ||
        !isBlank(bonusMode) ||
        !isBlank(bonusStart) ||
        !isBlank(stepSize) ||
        !isBlank(stepBonus) ||
        !isBlank(perTicket) ||
        !isBlank(maxBonus)
      );
    }

    function normalizeSigPart(val) {
      var parsed;

      if (isBlank(val)) return '';
      parsed = Number.parseFloat(String(val).replace(/[^0-9.\-]/g, ''));
      if (!Number.isFinite(parsed)) return String(val).trim();
      return String(parsed);
    }

    function currentDraftSig() {
      return JSON.stringify({
        structure: String(fStruct.value || '').trim(),
        flat: normalizeSigPart(fFlat ? fFlat.value : ''),
        split: normalizeSigPart(fSplit ? fSplit.value : ''),
        attendance_bonus_mode: String(fBonusMode ? (fBonusMode.value || '') : '').trim(),
        attendance_bonus_start_count: normalizeSigPart(fBonusStart ? fBonusStart.value : ''),
        attendance_bonus_step_size: normalizeSigPart(fBonusStepSize ? fBonusStepSize.value : ''),
        attendance_bonus_step_bonus: normalizeSigPart(fBonusStepBonus ? fBonusStepBonus.value : ''),
        attendance_bonus_per_ticket_rate: normalizeSigPart(fBonusPerTicket ? fBonusPerTicket.value : ''),
        attendance_bonus_max_bonus: normalizeSigPart(fBonusMax ? fBonusMax.value : '')
      });
    }

    function setHint(msg, type) {
      if (!hint) return;
      hint.textContent = msg || '';
      hint.style.color = type === 'warn' ? '#92400e' : (type === 'ok' ? '#065f46' : '');
    }

    function applyRow(row) {
      var source;
      var selectedOpt;
      var sourceLabel;
      var canOverwriteAuto;

      if (!row || !row.structure) {
        setHint('No date defaults found for that day.', 'warn');
        return;
      }

      source = String(row.source || 'venue').trim().toLowerCase();
      selectedOpt = source === 'holiday' ? 'default:holiday' : 'default:venue';
      sourceLabel = String(row.label || (source === 'holiday' ? 'Holiday defaults' : 'Venue defaults')).trim();

      if (!autoChk.checked) {
        setHint(sourceLabel + ' found. Turn on auto-fill to apply automatically.', 'info');
        return;
      }

      canOverwriteAuto = lastAutoAppliedSig !== '' && currentDraftSig() === lastAutoAppliedSig;
      if (dirty || (draftHasValues() && !canOverwriteAuto)) {
        setHint(sourceLabel + ' found. Auto-fill skipped because Draft Pay already has values.', 'warn');
        return;
      }

      fStruct.value = row.structure || 'flat_fee';
      if (fFlat && typeof row.flat_fee_amount !== 'undefined') fFlat.value = row.flat_fee_amount ?? '';
      if (fSplit && typeof row.door_split_percent !== 'undefined') fSplit.value = row.door_split_percent ?? '';
      if (fBonusMode && typeof row.attendance_bonus_mode !== 'undefined') fBonusMode.value = row.attendance_bonus_mode ?? '';
      if (fBonusStart && typeof row.attendance_bonus_start_count !== 'undefined') fBonusStart.value = row.attendance_bonus_start_count ?? '';
      if (fBonusStepSize && typeof row.attendance_bonus_step_size !== 'undefined') fBonusStepSize.value = row.attendance_bonus_step_size ?? '';
      if (fBonusStepBonus && typeof row.attendance_bonus_step_bonus !== 'undefined') fBonusStepBonus.value = row.attendance_bonus_step_bonus ?? '';
      if (fBonusPerTicket && typeof row.attendance_bonus_per_ticket_rate !== 'undefined') fBonusPerTicket.value = row.attendance_bonus_per_ticket_rate ?? '';
      if (fBonusMax && typeof row.attendance_bonus_max_bonus !== 'undefined') fBonusMax.value = row.attendance_bonus_max_bonus ?? '';
      if (pkgInp) pkgInp.value = '';
      if (selInp) selInp.value = selectedOpt;

      if (optionsWrap) {
        optionsWrap.querySelectorAll('.vms-comp-opt-tile').forEach(function (tile) {
          var isSel = String(tile.getAttribute('data-opt') || '') === selectedOpt;
          tile.classList.toggle('is-selected', isSel);
        });
      }

      trackedFields.forEach(function (el) {
        if (!el) return;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
      });

      lastAutoAppliedSig = currentDraftSig();
      dirty = false;
      document.dispatchEvent(new Event('vms_comp_options_updated'));
      setHint(sourceLabel + ' applied for this date. (Override anytime.)', 'ok');
    }

    async function fetchDefaults() {
      var venueId = venueSel.value || '';
      var eventDate = dateInp.value || '';
      var form;
      var resp;
      var json;

      if (!venueId || !eventDate) return null;

      form = new FormData();
      form.append('action', 'vms_get_venue_comp_defaults');
      form.append('venue_id', venueId);
      form.append('event_date', eventDate);

      resp = await fetch(ajaxurl, {
        method: 'POST',
        credentials: 'same-origin',
        body: form
      });
      json = await resp.json();
      if (!json || !json.success) return null;
      return json.data && json.data.row ? json.data.row : null;
    }

    async function onVenueOrDateChange() {
      var venueId = venueSel.value || '';
      var eventDate = dateInp.value || '';
      var row;

      if (!venueId || !eventDate) {
        setHint('Select a Venue and Event Date to apply date defaults.', '');
        return;
      }

      row = await fetchDefaults();
      if (!row || !row.structure) {
        setHint('No date defaults found for that day.', 'warn');
        return;
      }

      applyRow(row);
    }

    venueSel.addEventListener('change', onVenueOrDateChange);
    dateInp.addEventListener('change', onVenueOrDateChange);
    autoChk.addEventListener('change', function () {
      if (autoChk.checked) dirty = false;
      onVenueOrDateChange();
    });

    if (selInp && String(selInp.value || '').startsWith('default:')) {
      lastAutoAppliedSig = currentDraftSig();
    }
    setHint('Select a Venue and Event Date to apply date defaults.', '');
    return true;
  }

  function initCompOptionsRefresh() {
    var wrap = document.getElementById('vms-comp-options');
    var pkgInp = document.getElementById('vms_comp_package_id');
    var selInp = document.getElementById('vms_comp_selected_option');
    var fStruct = document.getElementById('vms_comp_structure');
    var fFlat = document.getElementById('vms_flat_fee_amount');
    var fSplit = document.getElementById('vms_door_split_percent');
    var fBonusMode = document.getElementById('vms_attendance_bonus_mode');
    var fBonusStart = document.getElementById('vms_attendance_bonus_start_count');
    var fBonusStepSize = document.getElementById('vms_attendance_bonus_step_size');
    var fBonusStepBonus = document.getElementById('vms_attendance_bonus_step_bonus');
    var fBonusPerTicket = document.getElementById('vms_attendance_bonus_per_ticket_rate');
    var fBonusMax = document.getElementById('vms_attendance_bonus_max_bonus');
    var fCommissionPercent = document.getElementById('vms_commission_percent');
    var fCommissionMode = document.getElementById('vms_commission_mode');
    var venueSel = document.getElementById('vms_venue_id');
    var dateInp = document.getElementById('vms_event_date');
    var bandSel = document.getElementById('vms_band_vendor_id');
    var optionFields = [
      fStruct,
      fFlat,
      fSplit,
      fBonusMode,
      fBonusStart,
      fBonusStepSize,
      fBonusStepBonus,
      fBonusPerTicket,
      fBonusMax,
      fCommissionPercent,
      fCommissionMode
    ];
    var applyingDraftFromOption = false;

    if (!wrap) {
      return false;
    }
    if (wrap.dataset.vmsCompOptionsBound === '1') {
      return true;
    }
    wrap.dataset.vmsCompOptionsBound = '1';

    function setSelectedTile(btn) {
      wrap.querySelectorAll('.vms-comp-opt-tile').forEach(function (tile) {
        tile.classList.remove('is-selected');
      });
      if (btn) btn.classList.add('is-selected');
    }

    function readTermsFromTile(btn) {
      return {
        structure: btn.getAttribute('data-structure') || 'flat_fee',
        flat: btn.getAttribute('data-flat') || '',
        split: btn.getAttribute('data-split') || '',
        attendance_bonus_mode: btn.getAttribute('data-bonus-mode') || '',
        attendance_bonus_start_count: btn.getAttribute('data-bonus-start-count') || '',
        attendance_bonus_step_size: btn.getAttribute('data-bonus-step-size') || '',
        attendance_bonus_step_bonus: btn.getAttribute('data-bonus-step-bonus') || '',
        attendance_bonus_per_ticket_rate: btn.getAttribute('data-bonus-per-ticket-rate') || '',
        attendance_bonus_max_bonus: btn.getAttribute('data-bonus-max-bonus') || '',
        commission_percent: btn.getAttribute('data-commission-percent') || '',
        commission_mode: btn.getAttribute('data-commission-mode') || 'artist_fee'
      };
    }

    function setDraftFromTerms(terms) {
      if (!fStruct || !fFlat || !fSplit) return;
      applyingDraftFromOption = true;

      if (terms.structure) fStruct.value = terms.structure;
      if (fFlat) fFlat.value = terms.flat !== null && terms.flat !== undefined ? terms.flat : '';
      if (fSplit) fSplit.value = terms.split !== null && terms.split !== undefined ? terms.split : '';
      if (fBonusMode) fBonusMode.value = terms.attendance_bonus_mode || '';
      if (fBonusStart) fBonusStart.value = terms.attendance_bonus_start_count || '';
      if (fBonusStepSize) fBonusStepSize.value = terms.attendance_bonus_step_size || '';
      if (fBonusStepBonus) fBonusStepBonus.value = terms.attendance_bonus_step_bonus || '';
      if (fBonusPerTicket) fBonusPerTicket.value = terms.attendance_bonus_per_ticket_rate || '';
      if (fBonusMax) fBonusMax.value = terms.attendance_bonus_max_bonus || '';
      if (fCommissionPercent) fCommissionPercent.value = terms.commission_percent || '';
      if (fCommissionMode) fCommissionMode.value = terms.commission_mode || 'artist_fee';

      fStruct.dispatchEvent(new Event('change', { bubbles: true }));
      fFlat.dispatchEvent(new Event('input', { bubbles: true }));
      fFlat.dispatchEvent(new Event('change', { bubbles: true }));
      fSplit.dispatchEvent(new Event('input', { bubbles: true }));
      fSplit.dispatchEvent(new Event('change', { bubbles: true }));
      [
        fBonusMode,
        fBonusStart,
        fBonusStepSize,
        fBonusStepBonus,
        fBonusPerTicket,
        fBonusMax,
        fCommissionPercent,
        fCommissionMode
      ].forEach(function (field) {
        if (!field) return;
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
      });

      applyingDraftFromOption = false;
    }

    function syncDraftFromSelectedDefault() {
      var selected;
      var btn;

      if (!selInp) return;
      selected = String(selInp.value || '').trim();
      if (!selected.startsWith('default:')) return;

      btn = Array.from(wrap.querySelectorAll('.vms-comp-opt-tile.is-selected')).find(function (el) {
        return String(el.getAttribute('data-opt') || '').trim() === selected;
      });
      if (!btn || btn.classList.contains('is-disabled')) return;

      setDraftFromTerms(readTermsFromTile(btn));
    }

    function refreshOptions() {
      var form;

      if (!venueSel || !dateInp) return;

      form = new FormData();
      form.append('action', 'vms_get_event_plan_comp_options');
      form.append('nonce', wrap.getAttribute('data-nonce') || '');
      form.append('venue_id', venueSel.value || '');
      form.append('event_date', dateInp.value || '');
      form.append('vendor_id', bandSel ? (bandSel.value || '') : '');
      form.append('selected_opt', selInp ? (selInp.value || '') : '');

      fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: form })
        .then(function (response) {
          return response.json();
        })
        .then(function (data) {
          var maxInp;

          if (!data || !data.success || !data.data || typeof data.data.html !== 'string') return;

          wrap.innerHTML = data.data.html;

          maxInp = document.getElementById('vms_max_guarantee_available');
          if (maxInp && typeof data.data.max_guarantee !== 'undefined') {
            maxInp.value = String(data.data.max_guarantee || '0');
          }

          syncDraftFromSelectedDefault();
          document.dispatchEvent(new Event('vms_comp_options_updated'));
        })
        .catch(function () {});
    }

    wrap.addEventListener('click', function (e) {
      var target = e.target;
      var btn;
      var pkgId;
      var opt;

      if (!target || !target.closest) return;
      btn = target.closest('.vms-comp-opt-tile');
      if (!btn || btn.classList.contains('is-disabled')) return;

      pkgId = btn.getAttribute('data-package-id') || '';
      opt = btn.getAttribute('data-opt') || '';

      if (pkgInp) pkgInp.value = pkgId;
      if (selInp) selInp.value = opt;

      setDraftFromTerms(readTermsFromTile(btn));
      setSelectedTile(btn);
    });

    function clearSelectedOptionOnManualEdit() {
      if (applyingDraftFromOption) return;
      if (selInp) selInp.value = '';
      if (pkgInp) pkgInp.value = '';
      setSelectedTile(null);
    }

    optionFields.forEach(function (el) {
      if (!el) return;
      el.addEventListener('input', clearSelectedOptionOnManualEdit);
      el.addEventListener('change', clearSelectedOptionOnManualEdit);
    });

    if (venueSel) venueSel.addEventListener('change', refreshOptions);
    if (dateInp) dateInp.addEventListener('change', refreshOptions);
    if (bandSel) bandSel.addEventListener('change', refreshOptions);

    syncDraftFromSelectedDefault();
    return true;
  }

  function initCompensationRefresh() {
    initVenueCompDefaults();
    initCompOptionsRefresh();
  }

  initCompensationRefresh();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCompensationRefresh, { once: true });
  }
})();
