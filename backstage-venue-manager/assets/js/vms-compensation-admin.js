(function () {
  function getConfig(name) {
    const value = window[name];
    return value && typeof value === 'object' ? value : {};
  }

  function getStrings(config) {
    return config && config.strings && typeof config.strings === 'object' ? config.strings : {};
  }

  function getLabels(config) {
    return config && config.labels && typeof config.labels === 'object' ? config.labels : {};
  }

  function text(strings, key, fallback) {
    return String(strings[key] || fallback);
  }

  function formatString(template, replacements) {
    let output = String(template || '');

    replacements.forEach((value, index) => {
      const safeValue = String(value === undefined || value === null ? '' : value);
      output = output.replace(new RegExp('%' + String(index + 1) + '\\$s', 'g'), safeValue);
      output = output.replace('%s', safeValue);
    });

    return output;
  }

  function escapeHtml(value) {
    return String(value === undefined || value === null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function byId(root, id) {
    return root ? root.querySelector('#' + id) : null;
  }

  function initCompPackage() {
    const root = document.querySelector('.vms-comp-package-admin');
    if (!root || root.dataset.vmsCompPackageBound === '1') {
      return;
    }

    const typeSel = byId(root, 'vms_comp_type');
    if (!typeSel) {
      return;
    }

    root.dataset.vmsCompPackageBound = '1';

    const labels = getLabels(getConfig('BVMGR_COMP_PACKAGE_ADMIN'));
    const flatFeeInput = byId(root, 'vms_flat_fee');
    const flatFeeRow = flatFeeInput ? flatFeeInput.closest('p') : null;
    const flatLabelText = byId(root, 'vms_flat_fee_label_text');
    const flatHelp = byId(root, 'vms_flat_fee_help');
    const bonusModeSel = byId(root, 'vms_attendance_bonus_mode');
    const typeBlocks = Array.from(root.querySelectorAll('.vms-comp-package-block[data-show-when]'));
    const modeBlocks = Array.from(root.querySelectorAll('.vms-comp-package-mode-block[data-show-when-mode]'));

    function refresh() {
      const typeValue = String(typeSel.value || '').trim();
      const modeValue = bonusModeSel ? String(bonusModeSel.value || '').trim() : '';

      if (flatFeeRow) {
        flatFeeRow.style.display = (typeValue === 'door_split') ? 'none' : '';
      }
      if (flatLabelText) {
        flatLabelText.textContent = (typeValue === 'attendance_bonus')
          ? String(labels.basePay || 'Base Pay')
          : String(labels.flatFeeAmount || 'Flat Fee Amount');
      }
      if (flatHelp) {
        flatHelp.classList.toggle('vms-hidden', typeValue !== 'attendance_bonus');
      }

      typeBlocks.forEach((el) => {
        const allowed = String(el.getAttribute('data-show-when') || '')
          .split(',')
          .map((value) => value.trim())
          .filter(Boolean);
        el.style.display = allowed.includes(typeValue) ? '' : 'none';
      });

      modeBlocks.forEach((el) => {
        const allowedMode = String(el.getAttribute('data-show-when-mode') || '').trim();
        el.style.display = (typeValue === 'attendance_bonus' && allowedMode === modeValue) ? '' : 'none';
      });
    }

    typeSel.addEventListener('change', refresh);
    if (bonusModeSel) {
      bonusModeSel.addEventListener('change', refresh);
    }

    refresh();
  }

  function initVendorDefaults() {
    const root = document.querySelector('.vms-vendor-defaults-ui');
    if (!root || root.dataset.vmsVendorDefaultsBound === '1') {
      return;
    }

    const structure = byId(root, 'vms_default_comp_structure');
    if (!structure) {
      return;
    }

    root.dataset.vmsVendorDefaultsBound = '1';

    const strings = getStrings(getConfig('BVMGR_VENDOR_DEFAULTS_ADMIN'));
    const bonusMode = byId(root, 'vms_default_attendance_bonus_mode');
    const flatField = byId(root, 'vms_default_flat_fee_amount');
    const supportingFlatField = byId(root, 'vms_default_supporting_flat_fee_amount');
    const splitField = byId(root, 'vms_default_door_split_percent');
    const commissionPercentField = byId(root, 'vms_default_commission_percent');
    const commissionModeField = byId(root, 'vms_default_commission_mode');
    const templateSelect = byId(root, 'vms_default_comp_package_id');
    const loadTemplateBtn = byId(root, 'vms-load-comp-template-btn');
    const editTemplateLink = byId(root, 'vms-edit-comp-template-link');
    const templatePreview = byId(root, 'vms-comp-template-preview');
    const summaryCard = byId(root, 'vms-vendor-defaults-summary');
    const flatLabel = byId(root, 'vms-default-flat-fee-label');
    const flatHelp = byId(root, 'vms-default-flat-fee-help');
    const bonusBlock = byId(root, 'vms-vendor-defaults-bonus-block');
    const previewWrap = byId(root, 'vms-vendor-attendance-bonus-preview');
    const previewFormula = byId(root, 'vms-vendor-attendance-bonus-formula');
    const previewNote = byId(root, 'vms-vendor-attendance-bonus-preview-note');
    const previewTable = byId(root, 'vms-vendor-attendance-bonus-preview-table');

    function getStructureLabel(value) {
      switch (String(value || '').trim()) {
        case 'flat_fee_door_split':
          return text(strings, 'flatFeeDoorSplit', 'Flat Fee + Door Split');
        case 'door_split':
          return text(strings, 'doorSplitOnly', 'Door Split Only');
        case 'attendance_bonus':
          return text(strings, 'baseAttendanceBonus', 'Base + Attendance Bonus');
        case 'flat_fee':
        default:
          return text(strings, 'flatFeeOnly', 'Flat Fee Only');
      }
    }

    function formatMoney(value) {
      const num = Number(value || 0);
      return '$' + num.toFixed(2);
    }

    function nonNegativeMoney(value) {
      const raw = String(value === undefined || value === null ? '' : value).trim();
      if (raw === '') {
        return null;
      }

      const parsed = Number(raw);
      if (!Number.isFinite(parsed)) {
        return null;
      }

      return Math.max(0, parsed);
    }

    function nonNegativeInt(value) {
      const raw = String(value === undefined || value === null ? '' : value).trim();
      if (raw === '') {
        return null;
      }

      const parsed = Number(raw);
      if (!Number.isFinite(parsed)) {
        return null;
      }

      return Math.max(0, Math.floor(parsed));
    }

    function getCurrentStructure() {
      return String(structure.value || '').trim();
    }

    function getCurrentBonusMode() {
      return bonusMode ? String(bonusMode.value || '').trim() : '';
    }

    function attendanceState() {
      return {
        mode: getCurrentBonusMode(),
        start: nonNegativeInt(byId(root, 'vms_default_attendance_bonus_start_count') ? byId(root, 'vms_default_attendance_bonus_start_count').value : ''),
        stepSize: nonNegativeInt(byId(root, 'vms_default_attendance_bonus_step_size') ? byId(root, 'vms_default_attendance_bonus_step_size').value : ''),
        stepBonus: nonNegativeMoney(byId(root, 'vms_default_attendance_bonus_step_bonus') ? byId(root, 'vms_default_attendance_bonus_step_bonus').value : ''),
        perTicketRate: nonNegativeMoney(byId(root, 'vms_default_attendance_bonus_per_ticket_rate') ? byId(root, 'vms_default_attendance_bonus_per_ticket_rate').value : ''),
        maxBonus: nonNegativeMoney(byId(root, 'vms_default_attendance_bonus_max_bonus') ? byId(root, 'vms_default_attendance_bonus_max_bonus').value : '')
      };
    }

    function attendanceCapInfo(state) {
      if (state.maxBonus === null || state.start === null) {
        return null;
      }

      if (state.mode === 'step' && state.stepSize !== null && state.stepSize >= 1 && state.stepBonus !== null && state.stepBonus > 0) {
        const stepsToCap = Math.max(0, Math.ceil(state.maxBonus / state.stepBonus));
        return { count: state.start + (stepsToCap * state.stepSize), steps: stepsToCap };
      }

      if (state.mode === 'continuous' && state.perTicketRate !== null && state.perTicketRate > 0) {
        const ticketsToCap = Math.max(0, Math.ceil(state.maxBonus / state.perTicketRate));
        return { count: state.start + ticketsToCap, tickets: ticketsToCap };
      }

      return null;
    }

    function buildAttendancePreviewCounts(state) {
      const counts = [];

      function pushCount(value) {
        const safe = Math.max(0, Math.floor(Number(value || 0)));
        if (!counts.includes(safe)) {
          counts.push(safe);
        }
      }

      const start = state.start ?? 0;
      const capInfo = attendanceCapInfo(state);

      if (state.mode === 'step') {
        const stepSize = state.stepSize ?? 0;
        pushCount(start);

        if (capInfo && Number.isFinite(Number(capInfo.steps))) {
          const exactSteps = Math.max(0, Number(capInfo.steps || 0));
          if (exactSteps <= 10) {
            for (let stepIndex = 1; stepIndex <= exactSteps; stepIndex += 1) {
              pushCount(start + (stepIndex * stepSize));
            }
          } else {
            [1, 2, 3, 5, Math.ceil(exactSteps / 2), exactSteps - 1, exactSteps].forEach((stepIndex) => {
              if (stepIndex > 0) {
                pushCount(start + (stepIndex * stepSize));
              }
            });
          }
        } else {
          [1, 2, 3, 5, 8].forEach((stepIndex) => pushCount(start + (stepIndex * stepSize)));
        }
      } else {
        pushCount(start);

        if (capInfo && Number.isFinite(Number(capInfo.tickets))) {
          const exactTickets = Math.max(0, Number(capInfo.tickets || 0));
          if (exactTickets <= 10) {
            for (let ticketIndex = 1; ticketIndex <= exactTickets; ticketIndex += 1) {
              pushCount(start + ticketIndex);
            }
          } else {
            [1, 5, 10, Math.ceil(exactTickets * 0.25), Math.ceil(exactTickets * 0.5), Math.ceil(exactTickets * 0.75), exactTickets].forEach((ticketIndex) => {
              if (ticketIndex > 0) {
                pushCount(start + ticketIndex);
              }
            });
          }
        } else {
          [1, 5, 10, 25, 50].forEach((ticketIndex) => pushCount(start + ticketIndex));
        }
      }

      counts.sort((a, b) => a - b);
      return counts.slice(0, 8);
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

    function getSelectedTemplateTerms() {
      if (!templateSelect) {
        return null;
      }

      const option = templateSelect.options[templateSelect.selectedIndex] || null;
      if (!option || !option.value || option.value === '0') {
        return null;
      }

      try {
        return JSON.parse(option.getAttribute('data-terms') || '{}');
      } catch (err) {
        return null;
      }
    }

    function summaryChip(label, value, tone) {
      const toneClass = tone ? ' ' + tone : '';
      return '<span class="vms-vendor-defaults-chip' + toneClass + '"><strong>' + escapeHtml(label) + ':</strong> ' + escapeHtml(value) + '</span>';
    }

    function renderTemplateUI() {
      if (!templateSelect || !templatePreview) {
        return;
      }

      const option = templateSelect.options[templateSelect.selectedIndex] || null;
      const hasSelection = !!(option && option.value && option.value !== '0');

      if (editTemplateLink) {
        const href = hasSelection ? String(option.getAttribute('data-edit-url') || '').trim() : '';
        editTemplateLink.style.display = hasSelection ? '' : 'none';
        editTemplateLink.setAttribute('href', href || '#');
      }

      if (!hasSelection) {
        templatePreview.innerHTML =
          '<div class="vms-vendor-defaults-preview-card__title">' + escapeHtml(text(strings, 'noTemplateSelectedTitle', 'No template selected')) + '</div>' +
          '<p class="description">' + escapeHtml(text(strings, 'noTemplateSelectedNote', 'This vendor will rely only on the Global Event Plan Defaults below.')) + '</p>';
        return;
      }

      const scope = String(option.getAttribute('data-scope') || '').trim();
      const terms = getSelectedTemplateTerms() || {};
      const chips = [];
      chips.push(summaryChip(text(strings, 'structureLabel', 'Structure'), getStructureLabel(terms.structure || 'flat_fee'), 'vms-vendor-defaults-chip--blue'));

      if (terms.flat_fee_amount !== undefined && terms.flat_fee_amount !== null && terms.flat_fee_amount !== '') {
        chips.push(summaryChip(text(strings, 'baseLabel', 'Base'), formatMoney(terms.flat_fee_amount)));
      }
      if (terms.door_split_percent !== undefined && terms.door_split_percent !== null && terms.door_split_percent !== '') {
        chips.push(summaryChip(text(strings, 'doorSplitLabel', 'Door split'), String(terms.door_split_percent) + '%'));
      }
      if (terms.commission_percent !== undefined && terms.commission_percent !== null && terms.commission_percent !== '') {
        const feeMode = (String(terms.commission_mode || '') === 'gross')
          ? text(strings, 'agentFeeGrossBased', 'gross-based')
          : text(strings, 'agentFeeOnTop', 'on top');
        chips.push(summaryChip(text(strings, 'agentFeeLabel', 'Agent fee'), String(terms.commission_percent) + '% (' + feeMode + ')'));
      }

      let bonusLine = '';
      if (String(terms.structure || '') === 'attendance_bonus') {
        const modeLabel = (String(terms.attendance_bonus_mode || '') === 'continuous')
          ? text(strings, 'attendanceModeContinuous', 'continuous')
          : text(strings, 'attendanceModeStep', 'step');
        const segments = [text(strings, 'attendanceBonusPrefix', 'Attendance bonus:') + ' ' + escapeHtml(modeLabel)];

        if (terms.attendance_bonus_start_count !== undefined && terms.attendance_bonus_start_count !== null && terms.attendance_bonus_start_count !== '') {
          segments.push(formatString(text(strings, 'attendanceStartsAfter', 'starts after %s'), [escapeHtml(terms.attendance_bonus_start_count)]));
        }
        if (terms.attendance_bonus_mode === 'step' && terms.attendance_bonus_step_size !== undefined && terms.attendance_bonus_step_size !== null && terms.attendance_bonus_step_size !== '' && terms.attendance_bonus_step_bonus !== undefined && terms.attendance_bonus_step_bonus !== null && terms.attendance_bonus_step_bonus !== '') {
          segments.push(formatString(text(strings, 'attendanceStepSegment', '+%1$s every %2$s'), [formatMoney(terms.attendance_bonus_step_bonus), escapeHtml(terms.attendance_bonus_step_size)]));
        }
        if (terms.attendance_bonus_mode === 'continuous' && terms.attendance_bonus_per_ticket_rate !== undefined && terms.attendance_bonus_per_ticket_rate !== null && terms.attendance_bonus_per_ticket_rate !== '') {
          segments.push(formatString(text(strings, 'attendanceContinuousSegment', '+%s per ticket'), [formatMoney(terms.attendance_bonus_per_ticket_rate)]));
        }
        if (terms.attendance_bonus_max_bonus !== undefined && terms.attendance_bonus_max_bonus !== null && terms.attendance_bonus_max_bonus !== '') {
          segments.push(formatString(text(strings, 'attendanceCapSegment', 'cap %s'), [formatMoney(terms.attendance_bonus_max_bonus)]));
        }

        bonusLine = '<p class="description vms-vendor-defaults-preview-card__note">' + segments.join(' • ') + '</p>';
      }

      templatePreview.innerHTML = [
        '<div class="vms-vendor-defaults-preview-card__title">' + escapeHtml(text(strings, 'selectedTemplateTitle', 'Selected Template')) + '</div>',
        '<div class="vms-vendor-defaults-preview-card__subtitle">' + escapeHtml(option.text) + '</div>',
        scope ? '<p class="description vms-vendor-defaults-preview-card__scope">' + escapeHtml(formatString(text(strings, 'scopeLine', 'Scope: %s'), [scope])) + '</p>' : '',
        '<div class="vms-vendor-defaults-chip-row">' + chips.join('') + '</div>',
        bonusLine,
        '<p class="description vms-vendor-defaults-preview-card__note">' + escapeHtml(text(strings, 'templatePreviewNote', 'Event Plans start here, then the defaults below can customize this vendor further.')) + '</p>'
      ].join('');
    }

    function renderCurrentDefaultsSummary() {
      if (!summaryCard) {
        return;
      }

      const currentStructure = getCurrentStructure();
      const flat = nonNegativeMoney(flatField ? flatField.value : '');
      const supportingFlat = nonNegativeMoney(supportingFlatField ? supportingFlatField.value : '');
      const split = nonNegativeMoney(splitField ? splitField.value : '');
      const comm = nonNegativeMoney(commissionPercentField ? commissionPercentField.value : '');
      const commMode = String(commissionModeField ? commissionModeField.value : 'artist_fee');
      const chips = [summaryChip(text(strings, 'structureLabel', 'Structure'), getStructureLabel(currentStructure), 'vms-vendor-defaults-chip--blue')];
      const notes = [];

      if (currentStructure !== 'door_split' && flat !== null) {
        chips.push(summaryChip(currentStructure === 'attendance_bonus' ? text(strings, 'basePayLabel', 'Base pay') : text(strings, 'flatFeeLabel', 'Flat fee'), formatMoney(flat)));
      }
      if ((currentStructure === 'flat_fee_door_split' || currentStructure === 'door_split') && split !== null) {
        chips.push(summaryChip(text(strings, 'doorSplitLabel', 'Door split'), String(split) + '%'));
      }
      if (supportingFlat !== null) {
        chips.push(summaryChip(text(strings, 'supportingActLabel', 'Supporting act'), formatMoney(supportingFlat), 'vms-vendor-defaults-chip--green'));
      } else {
        notes.push(text(strings, 'noSupportingActDefault', 'No supporting-act default fee is set yet.'));
      }
      if (comm !== null && comm > 0) {
        chips.push(summaryChip(text(strings, 'agentFeeLabel', 'Agent fee'), String(comm) + '%', 'vms-vendor-defaults-chip--amber'));
        notes.push((commMode === 'gross') ? text(strings, 'agentFeeGrossSummary', 'Agent fee is calculated from gross / settlement.') : text(strings, 'agentFeeOnTopSummary', 'Agent fee is added on top of vendor pay.'));
      } else {
        notes.push(text(strings, 'noDefaultAgentFee', 'No default agent fee is set.'));
      }

      if (currentStructure === 'attendance_bonus') {
        const state = attendanceState();
        if (flat !== null && state.maxBonus !== null) {
          notes.unshift(formatString(text(strings, 'potentialMaxPayout', 'Potential max payout: %s.'), [formatMoney(flat + state.maxBonus)]));
        } else if (flat !== null) {
          notes.unshift(formatString(text(strings, 'noBonusCapSummary', 'No bonus cap is set, so payout can keep climbing above %s.'), [formatMoney(flat)]));
        }
      }

      summaryCard.innerHTML = [
        '<div class="vms-vendor-defaults-preview-card__title">' + escapeHtml(text(strings, 'currentDefaultsTitle', 'What Event Plans Get by Default')) + '</div>',
        '<div class="vms-vendor-defaults-chip-row">' + chips.join('') + '</div>',
        '<p class="description vms-vendor-defaults-preview-card__note">' + escapeHtml(notes.join(' ')) + '</p>'
      ].join('');
    }

    function renderAttendancePreview() {
      if (!previewWrap || !previewFormula || !previewTable || !previewNote) {
        return;
      }

      const currentStructure = getCurrentStructure();
      const base = nonNegativeMoney(flatField ? flatField.value : '');
      const state = attendanceState();
      const isAttendance = (currentStructure === 'attendance_bonus');

      previewWrap.classList.toggle('vms-hidden', !isAttendance);
      if (!bonusBlock) {
        return;
      }

      bonusBlock.classList.toggle('vms-hidden', !isAttendance);
      if (!isAttendance) {
        previewFormula.textContent = '';
        previewNote.textContent = '';
        previewTable.innerHTML = '';
        return;
      }

      const isStepValid = (base !== null && state.mode === 'step' && state.start !== null && state.stepSize !== null && state.stepSize >= 1 && state.stepBonus !== null);
      const isContinuousValid = (base !== null && state.mode === 'continuous' && state.start !== null && state.perTicketRate !== null);

      if (!isStepValid && !isContinuousValid) {
        let message = text(strings, 'attendancePreviewIncomplete', 'Complete Base Pay, Bonus Style, and the attendance bonus fields to preview payouts.');
        if (state.mode === 'step' && state.stepSize !== null && state.stepSize < 1) {
          message = text(strings, 'attendancePreviewStepSizeInvalid', 'Step Size must be at least 1 for step-mode attendance bonuses.');
        }
        previewFormula.textContent = message;
        previewNote.textContent = '';
        previewTable.innerHTML = '';
        return;
      }

      const capInfo = attendanceCapInfo(state);
      const counts = buildAttendancePreviewCounts(state);
      if (state.mode === 'step') {
        const parts = [
          formatString(text(strings, 'formulaBasePay', 'Base pay %s.'), [formatMoney(base)]),
          formatString(text(strings, 'formulaNoBonusThrough', 'No bonus is earned through %s attendance.'), [state.start]),
          formatString(text(strings, 'formulaStepBonus', 'Add %1$s every %2$s tickets after that.'), [formatMoney(state.stepBonus), state.stepSize])
        ];

        if (state.maxBonus !== null) {
          parts.push(capInfo && capInfo.count !== null
            ? formatString(text(strings, 'formulaTotalBonusCapAtCount', 'Total bonus caps at %1$s once attendance reaches %2$s.'), [formatMoney(state.maxBonus), capInfo.count])
            : formatString(text(strings, 'formulaTotalBonusCap', 'Total bonus caps at %s.'), [formatMoney(state.maxBonus)]));
        }

        previewFormula.textContent = parts.join(' ');
      } else {
        const parts = [
          formatString(text(strings, 'formulaBasePay', 'Base pay %s.'), [formatMoney(base)]),
          formatString(text(strings, 'formulaNoBonusThrough', 'No bonus is earned through %s attendance.'), [state.start]),
          formatString(text(strings, 'formulaContinuousBonus', 'Add %s per ticket after that.'), [formatMoney(state.perTicketRate)])
        ];

        if (state.maxBonus !== null) {
          parts.push(capInfo && capInfo.count !== null
            ? formatString(text(strings, 'formulaTotalBonusCapAtCount', 'Total bonus caps at %1$s once attendance reaches %2$s.'), [formatMoney(state.maxBonus), capInfo.count])
            : formatString(text(strings, 'formulaTotalBonusCap', 'Total bonus caps at %s.'), [formatMoney(state.maxBonus)]));
        }

        previewFormula.textContent = parts.join(' ');
      }

      if (state.maxBonus !== null) {
        previewNote.textContent = formatString(text(strings, 'potentialMaxPayout', 'Potential max payout: %s.'), [formatMoney(base + state.maxBonus)]);
      } else {
        previewNote.textContent = text(strings, 'noBonusCapPreview', 'No bonus cap is set. Payout will continue to rise beyond the preview rows.');
      }

      const rows = counts.map((count) => {
        const payout = calculateAttendancePreviewPayout(base, state, count);
        return '<tr><td>' + count + '</td><td>' + formatMoney(payout.bonus) + '</td><td>' + formatMoney(payout.payout) + '</td></tr>';
      }).join('');

      previewTable.innerHTML =
        '<table class="widefat striped"><thead><tr><th>' +
        escapeHtml(text(strings, 'attendanceHeading', 'Attendance')) +
        '</th><th>' +
        escapeHtml(text(strings, 'bonusHeading', 'Bonus')) +
        '</th><th>' +
        escapeHtml(text(strings, 'totalPayHeading', 'Total Pay')) +
        '</th></tr></thead><tbody>' +
        rows +
        '</tbody></table>';
    }

    function refreshFieldVisibility() {
      const currentStructure = getCurrentStructure();
      const currentMode = getCurrentBonusMode();

      root.querySelectorAll('.vms-vendor-bonus-field').forEach((el) => {
        const needStructure = String(el.getAttribute('data-show-when-structure') || '').trim();
        const needMode = String(el.getAttribute('data-show-when-mode') || '').trim();
        const showStructure = (needStructure === '' || needStructure === currentStructure);
        const showMode = (needMode === '' || needMode === currentMode);
        el.style.display = (showStructure && showMode) ? '' : 'none';
      });

      root.querySelectorAll('.vms-vendor-structure-field').forEach((el) => {
        const allowedStructures = String(el.getAttribute('data-show-when-structures') || '')
          .split(',')
          .map((value) => value.trim())
          .filter(Boolean);
        el.classList.toggle('vms-hidden', allowedStructures.length ? !allowedStructures.includes(currentStructure) : false);
      });

      if (flatLabel) {
        flatLabel.textContent = (currentStructure === 'attendance_bonus')
          ? text(strings, 'basePayMoney', 'Base Pay ($)')
          : text(strings, 'flatFeeMoney', 'Flat Fee ($)');
      }
      if (flatHelp) {
        flatHelp.classList.toggle('vms-hidden', currentStructure !== 'attendance_bonus');
      }
    }

    function refresh() {
      refreshFieldVisibility();
      renderTemplateUI();
      renderCurrentDefaultsSummary();
      renderAttendancePreview();
    }

    if (templateSelect) {
      templateSelect.addEventListener('change', refresh);
    }

    if (loadTemplateBtn) {
      loadTemplateBtn.addEventListener('click', function () {
        const terms = getSelectedTemplateTerms();
        if (!terms) {
          return;
        }

        function setValue(id, value) {
          const el = byId(root, id);
          if (!el) {
            return;
          }

          el.value = (value === undefined || value === null) ? '' : String(value);
        }

        setValue('vms_default_comp_structure', terms.structure || 'flat_fee');
        setValue('vms_default_flat_fee_amount', terms.flat_fee_amount);
        setValue('vms_default_door_split_percent', terms.door_split_percent);
        setValue('vms_default_commission_percent', terms.commission_percent);
        setValue('vms_default_commission_mode', (String(terms.commission_mode || '') === 'gross') ? 'gross' : 'artist_fee');
        setValue('vms_default_attendance_bonus_mode', terms.attendance_bonus_mode);
        setValue('vms_default_attendance_bonus_start_count', terms.attendance_bonus_start_count);
        setValue('vms_default_attendance_bonus_step_size', terms.attendance_bonus_step_size);
        setValue('vms_default_attendance_bonus_step_bonus', terms.attendance_bonus_step_bonus);
        setValue('vms_default_attendance_bonus_per_ticket_rate', terms.attendance_bonus_per_ticket_rate);
        setValue('vms_default_attendance_bonus_max_bonus', terms.attendance_bonus_max_bonus);
        refresh();
      });
    }

    [
      structure,
      bonusMode,
      flatField,
      splitField,
      commissionPercentField,
      commissionModeField,
      byId(root, 'vms_default_attendance_bonus_start_count'),
      byId(root, 'vms_default_attendance_bonus_step_size'),
      byId(root, 'vms_default_attendance_bonus_step_bonus'),
      byId(root, 'vms_default_attendance_bonus_per_ticket_rate'),
      byId(root, 'vms_default_attendance_bonus_max_bonus')
    ].filter(Boolean).forEach((el) => {
      el.addEventListener('change', refresh);
      el.addEventListener('input', refresh);
    });

    refresh();
  }

  initCompPackage();
  initVendorDefaults();
})();
