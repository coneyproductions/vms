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

  function initCompensationState() {
    document.documentElement.classList.add('vms-js');

    const form = document.getElementById('post');
    const venueSel = document.getElementById('vms_venue_id');
    const dateInp = document.getElementById('vms_event_date');
    const bandSel = document.getElementById('vms_band_vendor_id');

    const fStruct = document.getElementById('vms_comp_structure');
    const fFlat = document.getElementById('vms_flat_fee_amount');
    const fSplit = document.getElementById('vms_door_split_percent');
    const fBonusMode = document.getElementById('vms_attendance_bonus_mode');
    const fBonusStart = document.getElementById('vms_attendance_bonus_start_count');
    const fBonusStepSize = document.getElementById('vms_attendance_bonus_step_size');
    const fBonusStepBonus = document.getElementById('vms_attendance_bonus_step_bonus');
    const fBonusPerTicket = document.getElementById('vms_attendance_bonus_per_ticket_rate');
    const fBonusMax = document.getElementById('vms_attendance_bonus_max_bonus');
    const fCommissionPercent = document.getElementById('vms_commission_percent');
    const fCommissionMode = document.getElementById('vms_commission_mode');

    const flatLabelText = document.getElementById('vms_flat_fee_amount_label_text');
    const flatHelp = document.getElementById('vms_flat_fee_amount_help');
    const previewWrap = document.getElementById('vms-attendance-bonus-preview');
    const previewFormula = document.getElementById('vms-attendance-bonus-formula');
    const previewTable = document.getElementById('vms-attendance-bonus-preview-table');
    const agentFeeSummary = document.getElementById('vms-agent-fee-summary');

    const tilesWrap = document.getElementById('vms-comp-tiles');
    const tiles = tilesWrap ? Array.from(tilesWrap.querySelectorAll('[data-structure]')) : [];

    const ackCard = document.getElementById('vms-comp-ack-wrap');
    let overrideDiff = false;
    let lowDiff = false;
    const lowSummary = document.getElementById('vms-low-guarantee-summary');

    const defStruct = document.getElementById('vms_default_structure');
    const defFlat = document.getElementById('vms_default_flat_fee_amount');
    const defSplit = document.getElementById('vms_default_door_split_percent');
    const defBonusMode = document.getElementById('vms_default_attendance_bonus_mode');
    const defBonusStart = document.getElementById('vms_default_attendance_bonus_start_count');
    const defBonusStepSize = document.getElementById('vms_default_attendance_bonus_step_size');
    const defBonusStepBonus = document.getElementById('vms_default_attendance_bonus_step_bonus');
    const defBonusPerTicket = document.getElementById('vms_default_attendance_bonus_per_ticket_rate');
    const defBonusMax = document.getElementById('vms_default_attendance_bonus_max_bonus');
    const defCommissionPercent = document.getElementById('vms_default_commission_percent');
    const defCommissionMode = document.getElementById('vms_default_commission_mode');
    const defLabel = document.getElementById('vms_default_label');
    const ack = document.getElementById('vms_pay_override_ack');
    const lowAck = ack;
    const lowBox = document.getElementById('vms-low-guarantee-box');
    const summary = document.getElementById('vms-pay-override-summary');

    if (!form || !fStruct || !fFlat || !fSplit) {
      return false;
    }
    if (form.dataset.vmsCompStateBound === '1') {
      return true;
    }
    form.dataset.vmsCompStateBound = '1';

    function num(v) {
      let s = String(v ?? '').trim();
      if (!s) return null;
      s = s.replace(/[^0-9.\-]/g, '');
      if (!s || s === '-' || s === '.' || s === '-.') return null;
      const x = parseFloat(s);
      return Number.isFinite(x) ? x : null;
    }

    function nonNegativeMoney(v) {
      const parsed = num(v);
      if (parsed === null) return null;
      return Math.max(0, parsed);
    }

    function nonNegativeInt(v) {
      const parsed = num(v);
      if (parsed === null) return null;
      return Math.max(0, Math.floor(parsed));
    }

    function str(v) {
      return String(v ?? '').trim();
    }

    function formatMoney(v) {
      if (v === null || v === undefined || !Number.isFinite(Number(v))) return '—';
      return '$' + Number(v).toFixed(2);
    }

    function formatPct(v) {
      if (v === null || v === undefined || !Number.isFinite(Number(v))) return '—';
      return Number(v).toFixed(2) + '%';
    }

    function structureLabel(structure) {
      if (structure === 'door_split') return 'Door Split';
      if (structure === 'flat_fee_door_split') return 'Flat Fee + Door Split';
      if (structure === 'attendance_bonus') return 'Base + Attendance Bonus';
      return 'Flat Fee';
    }

    function bonusModeLabel(mode) {
      if (mode === 'continuous') return 'Continuous';
      if (mode === 'step') return 'Step';
      return '—';
    }

    function selectedStructure() {
      return str(fStruct.value || 'flat_fee');
    }

    function selectedBonusMode() {
      return str(fBonusMode ? fBonusMode.value : '');
    }

    function guaranteeMap(flatFee) {
      const ff = Math.max(0, Number(flatFee || 0));
      return {
        flat_fee: ff,
        door_split: 0,
        flat_fee_door_split: ff,
        attendance_bonus: ff
      };
    }

    const actionButtons = Array.from(form.querySelectorAll('button[type="submit"][name="vms_event_plan_action"]'));
    actionButtons.forEach((btn) => {
      btn.dataset.vmsBaseDisabled = btn.disabled ? '1' : '0';
    });

    function setButtonsDisabled(disabled) {
      actionButtons.forEach((btn) => {
        const v = btn.value || '';
        if (v === 'mark_ready' || v === 'publish_now' || v === 'lock_draft_pay') {
          const baseDisabled = btn.dataset.vmsBaseDisabled === '1';
          const nextDisabled = baseDisabled || !!disabled;
          btn.disabled = nextDisabled;
          btn.classList.toggle('disabled', nextDisabled);
        }
      });
    }

    function updateTileSelection() {
      if (!tiles.length) return;
      const cur = selectedStructure();
      tiles.forEach((t) => {
        const isSel = t.getAttribute('data-structure') === cur;
        t.classList.toggle('is-selected', isSel);
        t.setAttribute('aria-checked', isSel ? 'true' : 'false');
      });
    }

    function applyStructureScale(map, maxAvailable) {
      if (!tiles.length || !map) return;
      const scaleClasses = [
        'vms-comp-tile--scale-1',
        'vms-comp-tile--scale-2',
        'vms-comp-tile--scale-3',
        'vms-comp-tile--scale-4',
        'vms-comp-tile--scale-5'
      ];

      const values = {};
      Object.keys(map).forEach((k) => {
        const raw = Number(map[k] || 0);
        values[k] = Number.isFinite(raw) ? Math.max(0, raw) : 0;
      });

      const structValues = Object.values(values);
      const maxStruct = structValues.length ? Math.max.apply(null, structValues) : 0;
      const parsedMaxAvailable = Number(maxAvailable || 0);
      const maxAvailableSafe = Number.isFinite(parsedMaxAvailable) ? Math.max(0, parsedMaxAvailable) : 0;
      const referenceMax = Math.max(maxStruct, maxAvailableSafe);

      tiles.forEach((t) => {
        scaleClasses.forEach((cls) => t.classList.remove(cls));
        if (!(referenceMax > 0)) return;

        const key = String(t.getAttribute('data-structure') || '').trim();
        if (!key) return;
        const needle = Number(values[key] || 0);
        const ratio = Math.max(0, Math.min(1, needle / referenceMax));
        const bucket = Math.max(0, Math.min(4, Math.floor(ratio * 4)));
        t.classList.add('vms-comp-tile--scale-' + String(bucket + 1));
      });
    }

    function attendanceState() {
      return {
        mode: selectedBonusMode(),
        start: nonNegativeInt(fBonusStart ? fBonusStart.value : ''),
        stepSize: nonNegativeInt(fBonusStepSize ? fBonusStepSize.value : ''),
        stepBonus: nonNegativeMoney(fBonusStepBonus ? fBonusStepBonus.value : ''),
        perTicketRate: nonNegativeMoney(fBonusPerTicket ? fBonusPerTicket.value : ''),
        maxBonus: nonNegativeMoney(fBonusMax ? fBonusMax.value : '')
      };
    }

    function setFieldVisibility() {
      const cur = selectedStructure();
      const mode = selectedBonusMode();
      document.querySelectorAll('[data-show-when]').forEach((el) => {
        const allowedStructures = String(el.getAttribute('data-show-when') || '').split(',').map((s) => s.trim()).filter(Boolean);
        const allowedModes = String(el.getAttribute('data-show-when-mode') || '').split(',').map((s) => s.trim()).filter(Boolean);
        const structureMatch = allowedStructures.includes(cur);
        const modeMatch = !allowedModes.length || allowedModes.includes(mode);
        el.classList.toggle('vms-hidden', !(structureMatch && modeMatch));
      });

      if (flatLabelText) {
        flatLabelText.textContent = cur === 'attendance_bonus' ? 'Base Pay' : 'Flat Fee Amount';
      }
      if (flatHelp) {
        flatHelp.classList.toggle('vms-hidden', cur !== 'attendance_bonus');
      }
    }

    function attendanceCapInfo(state) {
      if (state.maxBonus === null || state.start === null) return null;

      if (state.mode === 'step' && state.stepSize !== null && state.stepSize >= 1 && state.stepBonus !== null && state.stepBonus > 0) {
        const stepsToCap = Math.max(0, Math.ceil(state.maxBonus / state.stepBonus));
        return {
          count: state.start + (stepsToCap * state.stepSize),
          steps: stepsToCap
        };
      }

      if (state.mode === 'continuous' && state.perTicketRate !== null && state.perTicketRate > 0) {
        const ticketsToCap = Math.max(0, Math.ceil(state.maxBonus / state.perTicketRate));
        return {
          count: state.start + ticketsToCap,
          tickets: ticketsToCap
        };
      }

      return null;
    }

    function buildAttendancePreviewCounts(state) {
      const counts = [];
      const pushCount = (value) => {
        const safe = Math.max(0, Math.floor(Number(value || 0)));
        if (!counts.includes(safe)) counts.push(safe);
      };
      const start = state.start ?? 0;
      const capInfo = attendanceCapInfo(state);

      if (state.mode === 'step') {
        const stepSize = state.stepSize ?? 0;
        pushCount(start);

        if (capInfo && Number.isFinite(Number(capInfo.steps))) {
          const exactSteps = Math.max(0, Number(capInfo.steps || 0));
          if (exactSteps <= 40) {
            for (let stepIndex = 1; stepIndex <= exactSteps; stepIndex += 1) {
              pushCount(start + (stepIndex * stepSize));
            }
          } else {
            for (let stepIndex = 1; stepIndex <= 10; stepIndex += 1) {
              pushCount(start + (stepIndex * stepSize));
            }
            pushCount(start + (Math.floor(exactSteps / 2) * stepSize));
            pushCount(start + (Math.max(1, exactSteps - 2) * stepSize));
            pushCount(start + (Math.max(1, exactSteps - 1) * stepSize));
            pushCount(capInfo.count);
          }
        } else {
          for (let stepIndex = 1; stepIndex <= 5; stepIndex += 1) {
            pushCount(start + (stepIndex * stepSize));
          }
        }
      } else {
        pushCount(start);
        if (capInfo && Number.isFinite(Number(capInfo.tickets))) {
          const exactTickets = Math.max(0, Number(capInfo.tickets || 0));
          if (exactTickets <= 12) {
            for (let ticketIndex = 1; ticketIndex <= exactTickets; ticketIndex += 1) {
              pushCount(start + ticketIndex);
            }
          } else {
            pushCount(start + 1);
            pushCount(start + Math.ceil(exactTickets * 0.1));
            pushCount(start + Math.ceil(exactTickets * 0.25));
            pushCount(start + Math.ceil(exactTickets * 0.5));
            pushCount(start + Math.ceil(exactTickets * 0.75));
            pushCount(capInfo.count);
          }
        } else {
          pushCount(start + 1);
          pushCount(start + 5);
          pushCount(start + 10);
          pushCount(start + 25);
          pushCount(start + 50);
        }
      }

      counts.sort((a, b) => a - b);
      return counts;
    }

    function calculateAttendancePreviewPayout(base, state, attendanceCount) {
      const safeAttendance = Math.max(0, Math.floor(Number(attendanceCount || 0)));
      const safeBase = Math.max(0, Number(base || 0));
      let bonus = 0;
      if (state.mode === 'step' && state.start !== null && state.stepSize !== null && state.stepSize >= 1 && state.stepBonus !== null) {
        const stepsReached = Math.floor(Math.max(0, safeAttendance - state.start) / state.stepSize);
        bonus = stepsReached * state.stepBonus;
      } else if (state.mode === 'continuous' && state.start !== null && state.perTicketRate !== null) {
        bonus = Math.max(0, safeAttendance - state.start) * state.perTicketRate;
      }

      bonus = Math.max(0, Number(bonus || 0));
      if (state.maxBonus !== null) {
        bonus = Math.min(state.maxBonus, bonus);
      }

      return {
        base: safeBase,
        bonus: bonus,
        payout: safeBase + bonus
      };
    }

    function renderAttendancePreview() {
      if (!previewWrap || !previewFormula || !previewTable) return false;

      const cur = selectedStructure();
      const base = nonNegativeMoney(fFlat.value);
      const state = attendanceState();
      const isAttendance = cur === 'attendance_bonus';

      previewWrap.classList.toggle('vms-hidden', !isAttendance);
      if (!isAttendance) {
        return false;
      }

      const mode = state.mode;
      const isStepValid = base !== null && mode === 'step' && state.start !== null && state.stepSize !== null && state.stepSize >= 1 && state.stepBonus !== null;
      const isContinuousValid = base !== null && mode === 'continuous' && state.start !== null && state.perTicketRate !== null;

      if (!isStepValid && !isContinuousValid) {
        let msg = 'Complete Base Pay, Bonus Style, and the attendance bonus fields to preview payouts.';
        if (mode === 'step' && state.stepSize !== null && state.stepSize < 1) {
          msg = 'Step Size must be at least 1 for step-mode attendance bonuses.';
        }
        previewFormula.textContent = msg;
        previewTable.innerHTML = '';
        return true;
      }

      const capInfo = attendanceCapInfo(state);
      const counts = buildAttendancePreviewCounts(state);
      if (mode === 'step') {
        const parts = [
          `Base pay ${formatMoney(base)}.`,
          `No bonus is earned through ${state.start} attendance.`,
          `Add ${formatMoney(state.stepBonus)} every ${state.stepSize} tickets after that.`
        ];
        if (state.maxBonus !== null) {
          let capSentence = `Total bonus caps at ${formatMoney(state.maxBonus)}.`;
          if (capInfo && capInfo.count !== null) {
            capSentence = `Total bonus caps at ${formatMoney(state.maxBonus)} once attendance reaches ${capInfo.count}.`;
          }
          parts.push(capSentence);
        }
        previewFormula.textContent = parts.join(' ');
      } else {
        const parts = [
          `Base pay ${formatMoney(base)}.`,
          `No bonus is earned through ${state.start} attendance.`,
          `Add ${formatMoney(state.perTicketRate)} per ticket after that.`
        ];
        if (state.maxBonus !== null) {
          let capSentence = `Total bonus caps at ${formatMoney(state.maxBonus)}.`;
          if (capInfo && capInfo.count !== null) {
            capSentence = `Total bonus caps at ${formatMoney(state.maxBonus)} once attendance reaches ${capInfo.count}.`;
          }
          parts.push(capSentence);
        }
        previewFormula.textContent = parts.join(' ');
      }

      const rows = counts.map((count) => {
        const payout = calculateAttendancePreviewPayout(base, state, count);
        return `<tr><td>${count}</td><td>${formatMoney(payout.payout)}</td></tr>`;
      }).join('');

      previewTable.innerHTML = `<table class="widefat striped"><thead><tr><th>Attendance</th><th>Payout</th></tr></thead><tbody>${rows}</tbody></table>`;
      return false;
    }

    function renderLowGuarantee() {
      if (!lowBox || !lowAck || !lowSummary) return false;

      const cur = selectedStructure();
      const flat = nonNegativeMoney(fFlat.value);
      const map = guaranteeMap(flat);
      const maxAvailInp = document.getElementById('vms_max_guarantee_available');
      const maxAvail = nonNegativeMoney(maxAvailInp ? maxAvailInp.value : 0);
      applyStructureScale(map, maxAvail);

      const selG = cur === 'door_split' ? 0 : Math.max(0, Number(flat || 0));
      const requires = Number(maxAvail || 0) > 0 && selG < Number(maxAvail || 0);
      lowDiff = requires;

      document.querySelectorAll('[data-guarantee-for]').forEach((el) => {
        const k = el.getAttribute('data-guarantee-for');
        const g = map[k] ?? 0;
        el.textContent = '$' + Number(g).toFixed(2);
      });

      lowBox.classList.toggle('vms-hidden', !requires);
      if (!requires) {
        return false;
      }

      lowSummary.textContent = 'Selected guaranteed: $' + Number(selG).toFixed(2) + '. Highest available guaranteed: $' + Number(maxAvail || 0).toFixed(2) + '.';
      return !lowAck.checked;
    }

    function renderAgentFeeSummary() {
      if (!agentFeeSummary || !fCommissionPercent || !fCommissionMode) return;

      const pct = nonNegativeMoney(fCommissionPercent.value);
      const mode = str(fCommissionMode.value || 'artist_fee');
      const flat = nonNegativeMoney(fFlat.value);
      const cur = selectedStructure();
      const baseLabel = cur === 'attendance_bonus' ? 'Base pay' : 'Flat fee';

      if (pct === null || pct <= 0) {
        agentFeeSummary.textContent = 'No agent fee is currently set for this event.';
        return;
      }

      if (mode === 'gross') {
        agentFeeSummary.textContent = `Agent fee is set to ${formatPct(pct)} and will be based on gross / settlement, so it is not included in the guaranteed expense total yet.`;
        return;
      }

      if (flat === null) {
        agentFeeSummary.textContent = `Agent fee is set to ${formatPct(pct)} and will be added on top once ${baseLabel.toLowerCase()} is entered.`;
        return;
      }

      const feeAmount = Math.max(0, flat * (pct / 100));
      const total = flat + feeAmount;
      agentFeeSummary.textContent = `Agent fee: ${formatPct(pct)} of ${baseLabel.toLowerCase()} = ${formatMoney(feeAmount)}. Guaranteed expense total: ${formatMoney(total)}.`;
    }

    function actualState() {
      const attendance = attendanceState();
      return {
        structure: selectedStructure(),
        flat: nonNegativeMoney(fFlat.value),
        split: nonNegativeMoney(fSplit.value),
        attendance_bonus_mode: attendance.mode,
        attendance_bonus_start_count: attendance.start,
        attendance_bonus_step_size: attendance.stepSize,
        attendance_bonus_step_bonus: attendance.stepBonus,
        attendance_bonus_per_ticket_rate: attendance.perTicketRate,
        attendance_bonus_max_bonus: attendance.maxBonus,
        commission_percent: nonNegativeMoney(fCommissionPercent ? fCommissionPercent.value : ''),
        commission_mode: str(fCommissionMode ? fCommissionMode.value : '')
      };
    }

    function defaultState() {
      return {
        structure: str(defStruct ? defStruct.value : ''),
        flat: nonNegativeMoney(defFlat ? defFlat.value : ''),
        split: nonNegativeMoney(defSplit ? defSplit.value : ''),
        attendance_bonus_mode: str(defBonusMode ? defBonusMode.value : ''),
        attendance_bonus_start_count: nonNegativeInt(defBonusStart ? defBonusStart.value : ''),
        attendance_bonus_step_size: nonNegativeInt(defBonusStepSize ? defBonusStepSize.value : ''),
        attendance_bonus_step_bonus: nonNegativeMoney(defBonusStepBonus ? defBonusStepBonus.value : ''),
        attendance_bonus_per_ticket_rate: nonNegativeMoney(defBonusPerTicket ? defBonusPerTicket.value : ''),
        attendance_bonus_max_bonus: nonNegativeMoney(defBonusMax ? defBonusMax.value : ''),
        commission_percent: nonNegativeMoney(defCommissionPercent ? defCommissionPercent.value : ''),
        commission_mode: str(defCommissionMode ? defCommissionMode.value : ''),
        label: str(defLabel ? defLabel.value : 'Defaults')
      };
    }

    function differs(a, d) {
      let diff = false;
      if (d.structure && a.structure && d.structure !== a.structure) diff = true;
      if (d.flat !== null && d.flat !== a.flat) diff = true;
      if (d.split !== null && d.split !== a.split) diff = true;

      const compareAttendance = d.structure === 'attendance_bonus' || a.structure === 'attendance_bonus';
      if (!compareAttendance) {
        return diff;
      }

      if (d.attendance_bonus_mode && d.attendance_bonus_mode !== a.attendance_bonus_mode) diff = true;
      if (d.attendance_bonus_start_count !== null && d.attendance_bonus_start_count !== a.attendance_bonus_start_count) diff = true;
      if (d.attendance_bonus_step_size !== null && d.attendance_bonus_step_size !== a.attendance_bonus_step_size) diff = true;
      if (d.attendance_bonus_step_bonus !== null && d.attendance_bonus_step_bonus !== a.attendance_bonus_step_bonus) diff = true;
      if (d.attendance_bonus_per_ticket_rate !== null && d.attendance_bonus_per_ticket_rate !== a.attendance_bonus_per_ticket_rate) diff = true;
      if (d.attendance_bonus_max_bonus !== null && d.attendance_bonus_max_bonus !== a.attendance_bonus_max_bonus) diff = true;
      if (d.commission_percent !== null && d.commission_percent !== a.commission_percent) diff = true;
      if (d.commission_mode && d.commission_mode !== a.commission_mode) diff = true;
      return diff;
    }

    function renderPayOverride() {
      if (!ack || !summary) return false;

      const section = document.getElementById('vms-pay-override-box');
      const a = actualState();
      const d = defaultState();
      const hasAnyDefault = !!(
        d.structure ||
        d.flat !== null ||
        d.split !== null ||
        d.attendance_bonus_mode ||
        d.attendance_bonus_start_count !== null ||
        d.attendance_bonus_step_size !== null ||
        d.attendance_bonus_step_bonus !== null ||
        d.attendance_bonus_per_ticket_rate !== null ||
        d.attendance_bonus_max_bonus !== null ||
        d.commission_percent !== null ||
        d.commission_mode
      );

      if (!hasAnyDefault) {
        if (section) section.classList.add('vms-hidden');
        overrideDiff = false;
        return false;
      }

      const isDiff = differs(a, d);
      overrideDiff = isDiff;
      if (section) section.classList.toggle('vms-hidden', !isDiff);
      if (!isDiff) {
        return false;
      }

      const lines = [`Draft Pay differs from ${d.label}.`];
      if (d.structure && a.structure && d.structure !== a.structure) {
        lines.push(`Structure: default ${structureLabel(d.structure)} vs draft ${structureLabel(a.structure)}.`);
      }
      if (d.flat !== null && d.flat !== a.flat) {
        const flatLabel = a.structure === 'attendance_bonus' || d.structure === 'attendance_bonus' ? 'Base pay' : 'Flat fee';
        lines.push(`${flatLabel}: default ${formatMoney(d.flat)} vs draft ${formatMoney(a.flat)}.`);
      }
      if (d.split !== null && d.split !== a.split) {
        lines.push(`Door split: default ${formatPct(d.split)} vs draft ${formatPct(a.split)}.`);
      }
      if (d.structure === 'attendance_bonus' || a.structure === 'attendance_bonus') {
        if (d.attendance_bonus_mode && d.attendance_bonus_mode !== a.attendance_bonus_mode) {
          lines.push(`Bonus style: default ${bonusModeLabel(d.attendance_bonus_mode)} vs draft ${bonusModeLabel(a.attendance_bonus_mode)}.`);
        }
        if (d.attendance_bonus_start_count !== null && d.attendance_bonus_start_count !== a.attendance_bonus_start_count) {
          lines.push(`Bonus starts after: default ${d.attendance_bonus_start_count} vs draft ${a.attendance_bonus_start_count}.`);
        }
        if (d.attendance_bonus_step_size !== null && d.attendance_bonus_step_size !== a.attendance_bonus_step_size) {
          lines.push(`Step size: default ${d.attendance_bonus_step_size} vs draft ${a.attendance_bonus_step_size}.`);
        }
        if (d.attendance_bonus_step_bonus !== null && d.attendance_bonus_step_bonus !== a.attendance_bonus_step_bonus) {
          lines.push(`Bonus per step: default ${formatMoney(d.attendance_bonus_step_bonus)} vs draft ${formatMoney(a.attendance_bonus_step_bonus)}.`);
        }
        if (d.attendance_bonus_per_ticket_rate !== null && d.attendance_bonus_per_ticket_rate !== a.attendance_bonus_per_ticket_rate) {
          lines.push(`Bonus per ticket: default ${formatMoney(d.attendance_bonus_per_ticket_rate)} vs draft ${formatMoney(a.attendance_bonus_per_ticket_rate)}.`);
        }
        if (d.attendance_bonus_max_bonus !== null && d.attendance_bonus_max_bonus !== a.attendance_bonus_max_bonus) {
          lines.push(`Max bonus: default ${formatMoney(d.attendance_bonus_max_bonus)} vs draft ${formatMoney(a.attendance_bonus_max_bonus)}.`);
        }
      }
      if (d.commission_percent !== null && d.commission_percent !== a.commission_percent) {
        lines.push(`Agent fee: default ${formatPct(d.commission_percent)} vs draft ${formatPct(a.commission_percent)}.`);
      }
      if (d.commission_mode && d.commission_mode !== a.commission_mode) {
        const modeLabel = (value) => value === 'gross' ? 'gross / settlement' : 'added on top';
        lines.push(`Agent fee basis: default ${modeLabel(d.commission_mode)} vs draft ${modeLabel(a.commission_mode)}.`);
      }
      summary.textContent = lines.join(' ');
      return !ack.checked;
    }

    function render() {
      updateTileSelection();
      setFieldVisibility();

      const attendanceInvalid = renderAttendancePreview();
      renderAgentFeeSummary();
      const needsOverrideAck = renderPayOverride();
      const needsLowAck = renderLowGuarantee();

      if (ackCard) {
        ackCard.classList.toggle('vms-hidden', !(overrideDiff || lowDiff));
      }

      setButtonsDisabled(needsOverrideAck || needsLowAck || attendanceInvalid);
    }

    function payStateSignature() {
      const attendance = attendanceState();
      return JSON.stringify([
        selectedStructure(),
        nonNegativeMoney(fFlat.value),
        nonNegativeMoney(fSplit.value),
        attendance.mode,
        attendance.start,
        attendance.stepSize,
        attendance.stepBonus,
        attendance.perTicketRate,
        attendance.maxBonus
      ]);
    }

    let lastPaySig = payStateSignature();

    function resetAllAcksAndRender() {
      const nextSig = payStateSignature();
      if (nextSig === lastPaySig) {
        render();
        return;
      }
      lastPaySig = nextSig;
      if (ack) ack.checked = false;
      if (lowAck) lowAck.checked = false;
      render();
    }

    if (tiles.length) {
      tiles.forEach((tile) => {
        tile.addEventListener('click', () => {
          const k = tile.getAttribute('data-structure');
          if (!k) return;
          fStruct.value = k;
          fStruct.dispatchEvent(new Event('change', { bubbles: true }));
        });
      });
    }

    [fStruct, fFlat, fSplit, fBonusMode, fBonusStart, fBonusStepSize, fBonusStepBonus, fBonusPerTicket, fBonusMax].forEach((el) => {
      if (!el) return;
      el.addEventListener('change', resetAllAcksAndRender);
      el.addEventListener('input', resetAllAcksAndRender);
    });

    function resetOverrideAckOnly() {
      if (ack) ack.checked = false;
      if (lowAck) lowAck.checked = false;
      lastPaySig = payStateSignature();
      render();
    }

    if (venueSel) venueSel.addEventListener('change', resetOverrideAckOnly);
    if (dateInp) dateInp.addEventListener('change', resetOverrideAckOnly);

    if (bandSel) bandSel.addEventListener('change', () => {
      if (lowAck) lowAck.checked = false;
      render();
    });

    if (ack) ack.addEventListener('change', render);
    if (lowAck) lowAck.addEventListener('change', render);

    document.addEventListener('vms_comp_options_updated', () => {
      lastPaySig = payStateSignature();
      render();
    });

    render();
    return true;
  }

  function initCompensationRefresh() {
    initVenueCompDefaults();
    initCompOptionsRefresh();
    initCompensationState();
  }

  initCompensationRefresh();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCompensationRefresh, { once: true });
  }
})();
