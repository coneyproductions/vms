<?php defined('ABSPATH') || exit; ?>
        <script>
            (function() {
                function moveSectionNodes() {
                    const ticketingSlot = document.getElementById('vms-ticketing-v2-slot');
                    const advancedSlot = document.getElementById('vms-advanced-controls-slot');
                    const ticketingSource = document.getElementById('vms-ticketing-v2-source');
                    const advancedSource = document.getElementById('vms-advanced-controls');

                    if (ticketingSlot && ticketingSource) {
                        ticketingSlot.appendChild(ticketingSource);
                        ticketingSource.classList.add('is-promoted');
                    }

                    if (advancedSlot && advancedSource) {
                        advancedSource.open = true;
                        advancedSource.classList.add('is-promoted');
                        advancedSlot.appendChild(advancedSource);
                    }
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', moveSectionNodes, { once: true });
                } else {
                    moveSectionNodes();
                }
            })();
        </script>

	    <script>
	        (function() {
	            document.documentElement.classList.add('vms-js');

	            const form = document.getElementById('post');
	            if (!form) return;

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

	            if (!fStruct || !fFlat || !fSplit) return;

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
	                    attendance_bonus: ff,
	                };
	            }

	            const actionButtons = Array.from(form.querySelectorAll('button[type="submit"][name="vms_event_plan_action"]'));
	            actionButtons.forEach((btn) => {
	                btn.dataset.vmsBaseDisabled = btn.disabled ? '1' : '0';
	            });

	            function setButtonsDisabled(disabled) {
	                actionButtons.forEach(btn => {
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
	                tiles.forEach(t => {
	                    const isSel = (t.getAttribute('data-structure') === cur);
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
	                    'vms-comp-tile--scale-5',
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
	                    maxBonus: nonNegativeMoney(fBonusMax ? fBonusMax.value : ''),
	                };
	            }

	            function setFieldVisibility() {
	                const cur = selectedStructure();
	                const mode = selectedBonusMode();
	                document.querySelectorAll('[data-show-when]').forEach(el => {
	                    const allowedStructures = String(el.getAttribute('data-show-when') || '').split(',').map(s => s.trim()).filter(Boolean);
	                    const allowedModes = String(el.getAttribute('data-show-when-mode') || '').split(',').map(s => s.trim()).filter(Boolean);
	                    const structureMatch = allowedStructures.includes(cur);
	                    const modeMatch = !allowedModes.length || allowedModes.includes(mode);
	                    el.classList.toggle('vms-hidden', !(structureMatch && modeMatch));
	                });

	                if (flatLabelText) {
	                    flatLabelText.textContent = (cur === 'attendance_bonus') ? 'Base Pay' : 'Flat Fee Amount';
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
	                        steps: stepsToCap,
	                    };
	                }

	                if (state.mode === 'continuous' && state.perTicketRate !== null && state.perTicketRate > 0) {
	                    const ticketsToCap = Math.max(0, Math.ceil(state.maxBonus / state.perTicketRate));
	                    return {
	                        count: state.start + ticketsToCap,
	                        tickets: ticketsToCap,
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
	                    payout: safeBase + bonus,
	                };
	            }

	            function renderAttendancePreview() {
	                if (!previewWrap || !previewFormula || !previewTable) return false;

	                const cur = selectedStructure();
	                const base = nonNegativeMoney(fFlat.value);
	                const state = attendanceState();
	                const isAttendance = (cur === 'attendance_bonus');

	                previewWrap.classList.toggle('vms-hidden', !isAttendance);
	                if (!isAttendance) {
	                    return false;
	                }

	                const mode = state.mode;
	                const isStepValid = (base !== null && mode === 'step' && state.start !== null && state.stepSize !== null && state.stepSize >= 1 && state.stepBonus !== null);
	                const isContinuousValid = (base !== null && mode === 'continuous' && state.start !== null && state.perTicketRate !== null);

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
	                        `Add ${formatMoney(state.stepBonus)} every ${state.stepSize} tickets after that.`,
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
	                        `Add ${formatMoney(state.perTicketRate)} per ticket after that.`,
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

	                const selG = (cur === 'door_split') ? 0 : Math.max(0, Number(flat || 0));
	                const requires = (Number(maxAvail || 0) > 0 && selG < Number(maxAvail || 0));
	                lowDiff = requires;

	                document.querySelectorAll('[data-guarantee-for]').forEach(el => {
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
	                const baseLabel = (cur === 'attendance_bonus') ? 'Base pay' : 'Flat fee';

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
	                    commission_mode: str(fCommissionMode ? fCommissionMode.value : ''),
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
	                    label: str(defLabel ? defLabel.value : 'Defaults'),
	                };
	            }

	            function differs(a, d) {
	                let diff = false;
	                if (d.structure && a.structure && d.structure !== a.structure) diff = true;
	                if (d.flat !== null && d.flat !== a.flat) diff = true;
	                if (d.split !== null && d.split !== a.split) diff = true;

	                const compareAttendance = (d.structure === 'attendance_bonus' || a.structure === 'attendance_bonus');
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
	                    const flatLabel = (a.structure === 'attendance_bonus' || d.structure === 'attendance_bonus') ? 'Base pay' : 'Flat fee';
	                    lines.push(`${flatLabel}: default ${formatMoney(d.flat)} vs draft ${formatMoney(a.flat)}.`);
	                }
	                if (d.split !== null && d.split !== a.split) {
	                    lines.push(`Door split: default ${formatPct(d.split)} vs draft ${formatPct(a.split)}.`);
	                }
	                if ((d.structure === 'attendance_bonus' || a.structure === 'attendance_bonus')) {
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
	                    attendance.maxBonus,
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
	                tiles.forEach(tile => {
	                    tile.addEventListener('click', () => {
	                        const k = tile.getAttribute('data-structure');
	                        if (!k) return;
	                        fStruct.value = k;
	                        fStruct.dispatchEvent(new Event('change', { bubbles: true }));
	                    });
	                });
	            }

	            [fStruct, fFlat, fSplit, fBonusMode, fBonusStart, fBonusStepSize, fBonusStepBonus, fBonusPerTicket, fBonusMax].forEach(el => {
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
	        })();
	    </script>

    <script>
        (function() {
            const form = document.getElementById('post');
            if (!form) return;

            const hiddenConfirm = document.getElementById('vms_cancel_bulk_retry_confirm');
            const btn = form.querySelector('button[type="submit"][name="vms_event_plan_action"][value="retry_cancellation_all"]');
            if (!btn || !hiddenConfirm) return;

            btn.addEventListener('click', function(e) {
                hiddenConfirm.value = '0';
                const requires = (btn.getAttribute('data-vms-requires-refund-confirm') === '1');
                if (!requires) {
                    return;
                }
                const ok = window.confirm('Refund execution is currently failed or blocked. Retrying all steps may attempt refund execution again. Continue?');
                if (!ok) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                hiddenConfirm.value = '1';
            });
        })();
    </script>

    <script>
        (function() {
            const form = document.getElementById('post');
            if (!form) return;

            const btn = form.querySelector('button[type="submit"][name="vms_event_plan_action"][value="mark_cancelled"]');
            const dateField = document.getElementById('vms_reschedule_event_date');
            const policyField = document.getElementById('vms_cancel_policy');
            const autoRefundConfirmField = document.getElementById('vms_cancel_auto_refund_confirmed');
            if (!btn || btn.disabled) return;

            btn.addEventListener('click', function(e) {
                if (autoRefundConfirmField) autoRefundConfirmField.value = '0';
                const replacementDate = dateField ? String(dateField.value || '').trim() : '';
                const policy = policyField ? String(policyField.value || '').trim() : '';
                const usesAutoRefund = (policy === 'stop_sales_auto_refund' || policy === 'stop_sales_auto_refund_remove_attendees');

                let message = 'Are you sure you want to mark this event as Cancelled?';
                if (replacementDate !== '') {
                    message += ' VMS will also create a linked Draft Event Plan for ' + replacementDate + '.';
                }
                if (usesAutoRefund) {
                    message += ' This will attempt LIVE payment refunds for matching ticket orders through WooCommerce. Mixed orders will refund only the cancelled event ticket lines when possible, and anything unsafe will be queued for manual review.';
                }

                const ok = window.confirm(message);
                if (ok) {
                    if (usesAutoRefund && autoRefundConfirmField) {
                        autoRefundConfirmField.value = '1';
                    }
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
            });
        })();
    </script>

    <script>
        (function() {
            const form = document.getElementById('post');
            if (!form) return;

            const btn = form.querySelector('button[type="submit"][name="vms_event_plan_action"][value="run_live_refunds_now"]');
            const hiddenConfirm = document.getElementById('vms_cancel_manual_live_refund_confirm');
            if (!btn || !hiddenConfirm || btn.disabled) return;

            btn.addEventListener('click', function(e) {
                hiddenConfirm.value = '0';
                const ok = window.confirm('Run LIVE refunds now for this already-cancelled event? VMS will re-scan eligible ticket orders, attempt WooCommerce gateway refunds for remaining refundable lines, skip anything unsafe into manual review, and avoid double-refunding lines already processed.');
                if (!ok) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                hiddenConfirm.value = '1';
            });
        })();
    </script>

    <script>
        (function() {
            const form = document.getElementById('post');
            if (!form) return;

            const btn = form.querySelector('button[type="submit"][name="vms_event_plan_action"][value="create_rescheduled_draft"]');
            const dateField = document.getElementById('vms_reschedule_event_date');
            if (!btn || !dateField || btn.disabled) return;

            btn.addEventListener('click', function(e) {
                const value = String(dateField.value || '').trim();
                if (value !== '') {
                    const ok = window.confirm('Create a new linked draft for the replacement date? The cancelled Event Plan will stay preserved for history.');
                    if (ok) return;
                } else {
                    window.alert('Enter a replacement date before creating the rescheduled draft.');
                }
                e.preventDefault();
                e.stopPropagation();
            });
        })();
    </script>

    <script>
        (function() {
            const form = document.getElementById('post');
            if (!form) return;

            const metabox = document.getElementById('vms_event_plan_details');
            const inside = (metabox && metabox.querySelector('.inside')) || document.querySelector('#vms_event_plan_details .inside') || metabox || form;
            const shellRoot = metabox || form;
            const delegatedRoot = form;
            if (!inside || !shellRoot || !delegatedRoot) return;

            const postId = <?php echo (int) $post->ID; ?>;
            const stateKey = 'vms_ep_sections_state_' + String(postId || 'new');

            let saved = {};
            try {
                saved = JSON.parse(localStorage.getItem(stateKey) || '{}') || {};
            } catch (e) {
                saved = {};
            }

            function getBareTitles() {
                return Array.from(inside.querySelectorAll('h4.vms-collapsible-title')).filter((title) => !title.closest('.vms-collapsible-section'));
            }

            function toBool(v) {
                return v === true || v === '1' || v === 1 || v === 'true';
            }

            function readControlState(el) {
                if (!el) return '';
                if (el.matches('input[type="checkbox"],input[type="radio"]')) {
                    return el.checked ? '1' : '0';
                }
                if (el.tagName === 'SELECT') {
                    return Array.from(el.options || [])
                        .filter((opt) => opt.selected)
                        .map((opt) => String(opt.value || ''))
                        .join('\u001f');
                }
                return String(el.value || '');
            }

            const defaultCollapsedKeys = new Set(['cancellation', 'secondary_vendors', 'staff', 'readiness_details']);

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
                    const opts = Array.from(el.options || []);
                    return opts.some((opt) => opt.selected !== opt.defaultSelected);
                }
                return (el.value || '') !== (el.defaultValue || '');
            }

            function sectionDirty(body) {
                const controls = body.querySelectorAll('input, select, textarea');
                for (const c of controls) {
                    if (controlDirty(c)) return true;
                }
                return false;
            }

            function setFlag(section) {
                const flag = section.querySelector('.vms-collapsible-flag');
                const body = section.querySelector('.vms-collapsible-body');
                if (!flag || !body) return;

                const collapsed = section.classList.contains('is-collapsed');
                const dirty = sectionDirty(body);
                const show = collapsed && dirty;
                flag.classList.toggle('is-visible', show);
                flag.hidden = !show;
            }

            function saveState(section) {
                const key = section.dataset.sectionKey;
                if (!key) return;
                saved[key] = section.classList.contains('is-collapsed') ? 1 : 0;
                try {
                    localStorage.setItem(stateKey, JSON.stringify(saved));
                } catch (e) {}
            }

            function setCollapsed(section, collapsed) {
                section.classList.toggle('is-collapsed', !!collapsed);
                const btn = section.querySelector('.vms-collapsible-toggle');
                const body = section.querySelector('.vms-collapsible-body');
                if (btn) btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                if (body) body.hidden = !!collapsed;
                saveState(section);
                setFlag(section);
            }

            function bindFlagWatchers(section, body) {
                body.querySelectorAll('input, select, textarea').forEach((c) => {
                    if (!Object.prototype.hasOwnProperty.call(c.dataset, 'vmsInitialState')) {
                        c.dataset.vmsInitialState = readControlState(c);
                    }
                    if (c.dataset.vmsCollapseBound === '1') return;
                    c.dataset.vmsCollapseBound = '1';
                    c.addEventListener('input', () => setFlag(section));
                    c.addEventListener('change', () => setFlag(section));
                });
            }

            function initExistingSection(section) {
                if (!section) return;
                const body = section.querySelector('.vms-collapsible-body');
                const btn = section.querySelector('.vms-collapsible-toggle');
                if (!body || !btn) return;
                section.dataset.vmsInitReady = '1';
                section.dataset.vmsCollapseBound = '1';
                btn.dataset.vmsCollapseBound = '1';
                if (section.dataset.hasData !== '1' && section.dataset.hasData !== '0') {
                    const title = body.querySelector('h4.vms-collapsible-title');
                    if (title && toBool(title.getAttribute('data-section-has-data'))) {
                        section.dataset.hasData = '1';
                    } else {
                        section.dataset.hasData = '0';
                    }
                }
                bindFlagWatchers(section, body);
                const key = section.dataset.sectionKey || '';
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

            function createWrappedSection(title, idx) {
                const key = title.dataset.sectionKey || ('section_' + String(idx + 1));
                const section = document.createElement('section');
                section.className = 'vms-collapsible-section';
                section.dataset.sectionKey = key;

                if (toBool(title.getAttribute('data-section-has-data'))) {
                    section.dataset.hasData = '1';
                } else {
                    const carrier = title.closest('[data-vms-section-has-data]');
                    if (carrier && toBool(carrier.getAttribute('data-vms-section-has-data'))) {
                        section.dataset.hasData = '1';
                    } else {
                        section.dataset.hasData = '0';
                    }
                }

                const toggle = document.createElement('button');
                toggle.type = 'button';
                toggle.className = 'vms-collapsible-toggle';
                toggle.innerHTML =
                    '<span class="vms-collapsible-chevron" aria-hidden="true"></span>' +
                    '<span class="vms-collapsible-label"></span>' +
                    '<span class="vms-collapsible-flag" aria-hidden="true" hidden>Changed</span>';
                const label = toggle.querySelector('.vms-collapsible-label');
                if (label) label.textContent = title.textContent || 'Section';

                const body = document.createElement('div');
                body.className = 'vms-collapsible-body';

                title.parentNode.insertBefore(section, title);
                section.appendChild(toggle);
                section.appendChild(body);
                body.appendChild(title);

                let n = section.nextSibling;
                while (n && !isSectionBoundaryNode(n)) {
                    const next = n.nextSibling;
                    body.appendChild(n);
                    n = next;
                }

                title.classList.add('vms-collapsible-title--in-body');
                initExistingSection(section);
            }

            function initCollapsibleSections() {
                Array.from(shellRoot.querySelectorAll('.vms-collapsible-section[data-section-key]')).forEach(initExistingSection);

                const titles = getBareTitles();
                if (!titles.length && !shellRoot.querySelector('.vms-collapsible-section[data-section-key]')) return;

                titles.forEach((title, idx) => {
                    createWrappedSection(title, idx);
                });
            }

            if (!delegatedRoot.dataset.vmsCollapseDelegatedBound) {
                delegatedRoot.dataset.vmsCollapseDelegatedBound = '1';
                delegatedRoot.addEventListener('click', function(event) {
                    const btn = event.target.closest('.vms-collapsible-toggle');
                    if (!btn) return;
                    const section = btn.closest('.vms-collapsible-section[data-section-key]');
                    if (!section || !delegatedRoot.contains(section)) return;
                    initExistingSection(section);
                    event.preventDefault();
                    setCollapsed(section, !section.classList.contains('is-collapsed'));
                });
            }

            initCollapsibleSections();
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initCollapsibleSections, { once: true });
            }
            window.addEventListener('load', initCollapsibleSections, { once: true });
            window.setTimeout(initCollapsibleSections, 75);
            window.setTimeout(initCollapsibleSections, 250);
        })();
    </script>

    <script>
        (function() {
            const wrap = document.querySelector('[data-vms-staff-wrap="1"]');
            if (!wrap) return;

            const currentHeadcount = Math.max(0, parseInt(wrap.getAttribute('data-vms-current-headcount') || '0', 10) || 0);
            const headcountWired = wrap.getAttribute('data-vms-headcount-wired') === '1';

            function intValue(el) {
                if (!el) return 0;
                const raw = String(el.value || '').trim();
                if (raw === '') return 0;
                const parsed = parseInt(raw, 10);
                return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
            }

            function checkedCount(card) {
                return card.querySelectorAll('[data-vms-role-assignment-input="1"]:checked').length;
            }

            function roleState(card) {
                const headcountInput = card.querySelector('[data-vms-role-headcount-input="1"]');
                const thresholdInput = card.querySelector('[data-vms-role-threshold-input="1"]');
                const timeModeInput = card.querySelector('[data-vms-role-time-mode-input="1"]');
                const shiftStartInput = card.querySelector('[data-vms-role-shift-start-input="1"]');
                const shiftEndInput = card.querySelector('[data-vms-role-shift-end-input="1"]');
                const durationInput = card.querySelector('[data-vms-role-duration-input="1"]');

                const need = intValue(headcountInput);
                const threshold = Math.max(0, intValue(thresholdInput));
                const filled = checkedCount(card);
                const open = Math.max(0, need - filled);
                const roleInUse = (need > 0 || filled > 0);
                const timeMode = String(timeModeInput?.value || 'absolute').toLowerCase();
                const shiftStart = String(shiftStartInput?.value || '').trim();
                const shiftEnd = String(shiftEndInput?.value || '').trim();
                const duration = Math.max(0, intValue(durationInput));
                const absoluteTimeMissing = roleInUse && timeMode === 'absolute' && (shiftStart === '' || (shiftEnd === '' && duration <= 0));
                const thresholdMet = headcountWired && currentHeadcount >= threshold;
                const requiredNow = need > 0 && thresholdMet;
                const missingStaffNow = requiredNow && filled < need;
                const roleName = String(card.getAttribute('data-role-name') || 'Role');
                const isCritical = card.getAttribute('data-role-critical') === '1';

                return {
                    roleName,
                    need,
                    threshold,
                    filled,
                    open,
                    roleInUse,
                    timeMode,
                    absoluteTimeMissing,
                    thresholdMet,
                    requiredNow,
                    missingStaffNow,
                    isCritical,
                    duration
                };
            }

            function statePill(state) {
                if (!state.roleInUse) {
                    return { text: 'Not set', variant: 'is-inactive' };
                }
                if (!headcountWired) {
                    return { text: 'Guests pending', variant: 'is-unwired' };
                }
                if (state.requiredNow) {
                    return { text: 'Needed now', variant: 'is-required' };
                }
                if (state.threshold <= 0) {
                    return { text: 'Always needed', variant: 'is-active' };
                }
                return { text: `Needed at ${state.threshold}+ guests`, variant: 'is-waiting' };
            }

            function thresholdCopy(state) {
                if (!state.roleInUse) {
                    return 'Set staff needed and the guest trigger for when this role should become needed.';
                }
                if (!headcountWired) {
                    return `Guest count is not available yet. This role becomes needed at ${state.threshold} guests once sales or guest entries are available.`;
                }
                if (state.requiredNow) {
                    return `This role is needed now based on ${currentHeadcount} anticipated guests. It turns on at ${state.threshold} guests.`;
                }
                if (state.threshold <= 0) {
                    return 'This role is needed as soon as guest counts are available.';
                }
                return `This role becomes needed at ${state.threshold} anticipated guests. Current guest count: ${currentHeadcount}.`;
            }


            function toggleNodes(nodes, show) {
                nodes.forEach((node) => {
                    node.classList.toggle('vms-hidden', !show);
                    node.querySelectorAll('input, select, textarea').forEach((field) => {
                        field.disabled = !show;
                    });
                });
            }

            function syncTimingVisibility(card, state) {
                const absoluteFields = Array.from(card.querySelectorAll('[data-vms-role-absolute-field="1"]'));
                const relativeFields = Array.from(card.querySelectorAll('[data-vms-role-relative-field="1"]'));
                const endFields = Array.from(card.querySelectorAll('[data-vms-role-end-field="1"]'));
                const showAbsolute = state.timeMode === 'absolute';
                const showRelative = !showAbsolute;
                toggleNodes(absoluteFields, showAbsolute);
                toggleNodes(relativeFields, showRelative);
                if (state.duration > 0) {
                    toggleNodes(endFields.filter((node) => showAbsolute ? node.hasAttribute('data-vms-role-absolute-field') : node.hasAttribute('data-vms-role-relative-field')), false);
                }
            }

            function renderCard(card) {
                const state = roleState(card);
                const summary = card.querySelector('[data-vms-role-base-summary]');
                const pill = card.querySelector('[data-vms-role-state-pill]');
                const thresholdSummary = card.querySelector('[data-vms-role-threshold-copy]');
                const absoluteWarning = card.querySelector('[data-vms-role-absolute-warning]');
                const requiredWarning = card.querySelector('[data-vms-role-required-warning]');
                const pillState = statePill(state);
                syncTimingVisibility(card, state);

                card.classList.toggle('is-required-now', state.requiredNow);
                card.classList.toggle('has-inline-warning', state.absoluteTimeMissing || state.missingStaffNow);
                card.classList.toggle('has-required-gap', state.missingStaffNow);
                card.classList.toggle('is-waiting-threshold', state.roleInUse && !state.requiredNow && headcountWired && state.threshold > 0);

                if (summary) {
                    summary.textContent = `Need ${state.need} · Filled ${state.filled} · Open ${state.open}${state.isCritical ? ' · Critical' : ''}`;
                }

                if (pill) {
                    pill.textContent = pillState.text;
                    pill.classList.remove(
                        'vms-ep-staff-role__state--is-inactive',
                        'vms-ep-staff-role__state--is-unwired',
                        'vms-ep-staff-role__state--is-required',
                        'vms-ep-staff-role__state--is-active',
                        'vms-ep-staff-role__state--is-waiting'
                    );
                    pill.classList.add(`vms-ep-staff-role__state--${pillState.variant}`);
                }

                if (thresholdSummary) {
                    thresholdSummary.textContent = thresholdCopy(state);
                }

                if (absoluteWarning) {
                    absoluteWarning.classList.toggle('vms-hidden', !state.absoluteTimeMissing);
                    absoluteWarning.textContent = 'Absolute time mode requires Shift start plus Shift end or Duration when this role is in use.';
                }

                if (requiredWarning) {
                    requiredWarning.classList.toggle('vms-hidden', !state.missingStaffNow);
                    requiredWarning.textContent = `Current guest count (${currentHeadcount}) has reached this role's trigger of ${state.threshold}. Assign staff until Filled reaches Staff needed.`;
                }
            }

            const cards = Array.from(wrap.querySelectorAll('[data-vms-staff-role="1"]'));
            cards.forEach((card) => {
                card.querySelectorAll('input, select').forEach((field) => {
                    field.addEventListener('input', () => renderCard(card));
                    field.addEventListener('change', () => renderCard(card));
                });
                renderCard(card);
            });
        })();
    </script>

    <script>
        (function() {
            const bandSel = document.getElementById('vms_band_vendor_id');
            const wrap = document.getElementById('vms-tax-status');
            const bypassWrap = document.getElementById('vms-tax-bypass-inline');
            const bypassUntil = document.getElementById('vms-tax-bypass-until');
            const bypassReason = document.getElementById('vms-tax-bypass-reason');
            const bypassSetBtn = document.getElementById('vms-tax-bypass-set');
            const bypassClearBtn = document.getElementById('vms-tax-bypass-clear');
            const bypassMsg = document.getElementById('vms-tax-bypass-msg');
            const bypassActiveFlag = document.getElementById('vms-tax-bypass-active-flag');
            if (!bandSel || !wrap) return;

            function escapeHtml(str) {
                return String(str).replace(/[&<>"']/g, s => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                } [s]));
            }

            function selectedVendorId() {
                const raw = bandSel.value || '';
                const id = parseInt(raw, 10);
                return Number.isFinite(id) && id > 0 ? id : 0;
            }

            function setBypassMsg(text, type) {
                if (!bypassMsg) return;
                bypassMsg.textContent = text || '';
                bypassMsg.className = 'description vms-mt-6';
                if (type === 'error') {
                    bypassMsg.className += ' vms-text-danger';
                }
            }

            function setBypassUiEnabled(enabled) {
                const on = !!enabled;
                if (bypassUntil) bypassUntil.disabled = !on;
                if (bypassReason) bypassReason.disabled = !on;
                if (bypassSetBtn) bypassSetBtn.disabled = !on;
                if (bypassClearBtn) bypassClearBtn.disabled = !on;
            }

            function updateBypassDefaultsFromSelection() {
                if (!bypassWrap) return;
                const opt = bandSel.options[bandSel.selectedIndex];
                const hasVendor = !!(opt && selectedVendorId() > 0);

                setBypassUiEnabled(hasVendor);
                if (!hasVendor) {
                    bypassWrap.classList.add('vms-hidden');
                    wrap.classList.remove('vms-tax-has-bypass-inline', 'vms-tax-has-bypass-inline-active');
                    setBypassMsg('Select a vendor to manage bypass.', '');
                    return;
                }

                const taxOk = opt.getAttribute('data-tax-ok') === '1';
                const active = opt.getAttribute('data-tax-bypass-active') === '1';
                const until = (opt.getAttribute('data-tax-bypass-until') || '').trim();
                const reason = (opt.getAttribute('data-tax-bypass-reason') || '').trim();
                const fallbackUntil = (bypassWrap.getAttribute('data-default-until') || '').trim();
                const needed = (!taxOk || active);

                bypassWrap.classList.toggle('vms-hidden', !needed);
                wrap.classList.toggle('vms-tax-has-bypass-inline', needed);
                wrap.classList.toggle('vms-tax-has-bypass-inline-active', (needed && active));
                if (!needed) {
                    if (bypassReason) {
                        bypassReason.classList.remove('has-active-bypass');
                        bypassReason.value = '';
                    }
                    if (bypassActiveFlag) {
                        bypassActiveFlag.classList.add('vms-hidden');
                    }
                    bypassWrap.classList.remove('has-active-bypass');
                    wrap.classList.remove('vms-tax-has-bypass-inline', 'vms-tax-has-bypass-inline-active');
                    return;
                }

                if (bypassUntil) {
                    bypassUntil.value = until || fallbackUntil;
                }
                if (bypassReason) {
                    if (active) {
                        bypassReason.value = reason;
                    } else if (!bypassReason.value) {
                        bypassReason.value = '';
                    }
                    bypassReason.classList.toggle('has-active-bypass', active);
                }

                bypassWrap.classList.toggle('has-active-bypass', active);
                if (bypassActiveFlag) {
                    bypassActiveFlag.classList.toggle('vms-hidden', !active);
                }
                setBypassMsg(active ? ('Bypass active until ' + (until || '—') + '.') : 'No bypass is active for this vendor.', '');
            }

            function updateSelectedOptionBypass(active, until, reason) {
                const opt = bandSel.options[bandSel.selectedIndex];
                if (!opt) return;
                opt.setAttribute('data-tax-bypass-active', active ? '1' : '0');
                opt.setAttribute('data-tax-bypass-until', active ? (until || '') : '');
                opt.setAttribute('data-tax-bypass-reason', active ? (reason || '') : '');
            }

            function render() {
                const opt = bandSel.options[bandSel.selectedIndex];
                if (!opt || !opt.value) {
                    wrap.innerHTML =
                        '<div class="vms-tax-box vms-notice vms-notice--info">' +
                        '<div class="title">Tax Profile</div>' +
                        '<div class="muted">Select a Primary Vendor to see tax requirements.</div>' +
                        '</div>';
                    updateBypassDefaultsFromSelection();
                    return;
                }

                const ok = opt.getAttribute('data-tax-ok') === '1';
                const bypassActive = opt.getAttribute('data-tax-bypass-active') === '1';
                const bypassUntil = (opt.getAttribute('data-tax-bypass-until') || '').trim();
                const missing = (opt.getAttribute('data-tax-missing') || '').trim();

                if (ok) {
                    wrap.innerHTML =
                        '<div class="vms-tax-box ok vms-notice vms-notice--success">' +
                        '<div class="title">✅ Tax Profile Complete</div>' +
                        '<div class="muted">This vendor is eligible for Ready/Publish (tax-wise).</div>' +
                        '</div>';
                } else if (bypassActive) {
                    wrap.innerHTML =
                        '<div class="vms-tax-box warn vms-notice vms-notice--warning">' +
                        '<div class="title">🟡 Tax Profile Bypass Active</div>' +
                        '<div class="muted"><strong>Missing:</strong> ' + escapeHtml(missing || '—') + '</div>' +
                        '<div class="muted vms-mt-6">Ready/Publish is allowed while the bypass is active' + (bypassUntil ? (' (until ' + escapeHtml(bypassUntil) + ')') : '') + '.</div>' +
                        '</div>';
                } else {
                    wrap.innerHTML =
                        '<div class="vms-tax-box bad vms-notice vms-notice--warning">' +
                        '<div class="title">⚠️ Tax Profile Incomplete</div>' +
                        '<div class="muted"><strong>Missing:</strong> ' + escapeHtml(missing || '—') + '</div>' +
                        '<div class="muted vms-mt-6">Needs attention — payments/exports blocked until complete or bypass set. Ready/Publish allowed.</div>' +
                        '</div>';
                }

                updateBypassDefaultsFromSelection();
            }

            async function postBypass(action, payload) {
                const nonce = bypassWrap ? (bypassWrap.getAttribute('data-nonce') || '') : '';
                const form = new FormData();
                form.append('action', action);
                form.append('nonce', nonce);
                Object.keys(payload || {}).forEach((k) => form.append(k, payload[k]));

                const res = await fetch(ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: form
                });
                return await res.json();
            }

            if (bypassSetBtn) {
                bypassSetBtn.addEventListener('click', async function() {
                    const vendorId = selectedVendorId();
                    if (!(vendorId > 0)) {
                        setBypassMsg('Select a vendor first.', 'error');
                        return;
                    }

                    const until = bypassUntil ? String(bypassUntil.value || '').trim() : '';
                    const reason = bypassReason ? String(bypassReason.value || '').trim() : '';
                    if (!/^\d{4}-\d{2}-\d{2}$/.test(until)) {
                        setBypassMsg('Enter a valid "Until" date (YYYY-MM-DD).', 'error');
                        return;
                    }
                    if (!reason) {
                        setBypassMsg('Reason is required.', 'error');
                        return;
                    }

                    setBypassMsg('Applying bypass…', '');
                    bypassSetBtn.disabled = true;
                    try {
                        const json = await postBypass('vms_tax_bypass_set', {
                            post_id: String(vendorId),
                            until: until,
                            reason: reason
                        });

                        if (!json || !json.success) {
                            const msg = (json && json.data && json.data.message) ? String(json.data.message) : 'Bypass update failed.';
                            setBypassMsg(msg, 'error');
                            return;
                        }

                        updateSelectedOptionBypass(true, until, reason);
                        setBypassMsg('Bypass applied.', '');
                        render();
                    } catch (e) {
                        setBypassMsg('Bypass update failed.', 'error');
                    } finally {
                        bypassSetBtn.disabled = false;
                    }
                });
            }

            if (bypassClearBtn) {
                bypassClearBtn.addEventListener('click', async function() {
                    const vendorId = selectedVendorId();
                    if (!(vendorId > 0)) {
                        setBypassMsg('Select a vendor first.', 'error');
                        return;
                    }

                    setBypassMsg('Clearing bypass…', '');
                    bypassClearBtn.disabled = true;
                    try {
                        const json = await postBypass('vms_tax_bypass_clear', {
                            post_id: String(vendorId)
                        });

                        if (!json || !json.success) {
                            const msg = (json && json.data && json.data.message) ? String(json.data.message) : 'Clear failed.';
                            setBypassMsg(msg, 'error');
                            return;
                        }

                        updateSelectedOptionBypass(false, '', '');
                        if (bypassReason) bypassReason.value = '';
                        setBypassMsg('Bypass cleared.', '');
                        render();
                    } catch (e) {
                        setBypassMsg('Clear failed.', 'error');
                    } finally {
                        bypassClearBtn.disabled = false;
                    }
                });
            }

            bandSel.addEventListener('change', render);
            render();
        })();
    </script>

    <script>
        (function() {
            const bandSel = document.getElementById('vms_band_vendor_id');
            const autoTitle = document.querySelector('input[name="vms_auto_title"]');
            const previewEl = document.getElementById('vms_title_preview_text');
            const lockNote = document.getElementById('vms_title_lock_note');

            const wpTitleInput =
                document.getElementById('title') ||
                document.querySelector('textarea.editor-post-title__input') ||
                document.querySelector('h1.editor-post-title__input');


            function setLockNote(on) {
                if (!lockNote) return;
                lockNote.classList.toggle('vms-hidden', !on);
                lockNote.hidden = !on;
                lockNote.style.display = on ? '' : 'none';
            }

            function getBandName() {
                if (!bandSel) return '';
                const opt = bandSel.options[bandSel.selectedIndex];
                if (!opt) return '';

                const raw = (opt.getAttribute('data-vendor-title') || '').trim();
                if (raw) return raw;

                // Fallback for older markup: strip trailing status tags like [✓] [T⚠] [TB].
                let clean = (opt.text || '').trim();
                while (/\s*\[[^\]]+\]\s*$/.test(clean)) {
                    clean = clean.replace(/\s*\[[^\]]+\]\s*$/, '').trim();
                }
                return clean;
            }

            function buildTitle() {
                const band = getBandName();
                if (!band) return '';
                return band;
            }

            function updatePreview() {
                const isAuto = autoTitle ? autoTitle.checked : true;
                setLockNote(!isAuto);
                if (!previewEl) return;
                if (!isAuto) {
                    previewEl.textContent = '(auto-title disabled)';
                    return;
                }
                const t = buildTitle();
                previewEl.textContent = t || '(select Primary Vendor to preview)';
            }

            let lastBuilt = buildTitle(); // track the last computed auto title

            function setWpTitle(t) {
                const el = wpTitleInput || document.querySelector('.editor-post-title__input');
                if (!el) return;

                // React/Gutenberg: use native setter so the internal state updates
                const valueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value')?.set;
                if (valueSetter) {
                    valueSetter.call(el, t);
                } else {
                    el.value = t;
                }

                // Tell Gutenberg/React something changed (this clears the placeholder overlay)
                el.dispatchEvent(new Event('input', {
                    bubbles: true
                }));

                // Extra safety: also update the editor store if available
                notifyGutenbergTitleChange(t);
            }

            function notifyGutenbergTitleChange(newTitle) {
                try {
                    if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
                        wp.data.dispatch('core/editor').editPost({
                            title: newTitle
                        });
                    }
                } catch (e) {
                    // ignore
                }
            }

            function getWpTitle() {
                if (!wpTitleInput) return '';
                return (wpTitleInput.value || wpTitleInput.textContent || '').trim();
            }

            function updateWpTitleBox() {
                const t = buildTitle();
                if (!t) return;

                const current = getWpTitle();
                const currentLower = current.toLowerCase();

                const isAuto = autoTitle ? autoTitle.checked : true;

                // Keep the lock note accurate
                setLockNote(!isAuto);

                // RULE: There must ALWAYS be a title if we have a selected band.
                // If empty / Auto Draft, fill it silently (no prompt).
                if (!current || currentLower === 'auto draft') {
                    setWpTitle(t);
                    lastBuilt = t;
                    return;
                }

                // If the current title equals the last auto-built title, it is "auto-managed".
                // In that case, update silently (no prompt needed).
                if (lastBuilt && current === lastBuilt) {
                    setWpTitle(t);
                    lastBuilt = t;
                    return;
                }

                // At this point we have a non-empty title that does NOT match the last auto title.
                // That means it's custom (or at least diverged).
                // RULE: When band changes, ALWAYS prompt whether to update title.
                if (current !== t) {
                    const ok = window.confirm('Primary Vendor changed. Update the title to match the selected Primary Vendor?');

                    if (ok) {
                        // User chose to sync title: turn auto-title ON and set title.
                        if (autoTitle) autoTitle.checked = true;
                        setWpTitle(t);
                        setLockNote(false);
                    } else {
                        // User chose to keep custom: turn auto-title OFF (freeze).
                        if (autoTitle) autoTitle.checked = false;
                        setLockNote(true);
                    }
                }

                lastBuilt = t;
            }

            (function() {
                const postForm = document.getElementById('post');
                if (!postForm) return;

                postForm.addEventListener('submit', function() {
                    // Kill any beforeunload warnings during a real save submit.
                    window.onbeforeunload = null;
                });
            })();

            function onChange() {
                updatePreview();
                updateWpTitleBox();
            }

            if (bandSel) bandSel.addEventListener('change', onChange);
            // if (autoTitle) autoTitle.addEventListener('change', onChange);

            if (autoTitle) {
                autoTitle.addEventListener('change', function() {
                    updatePreview();
                });
            }

            updatePreview();
        })();
    </script>

    <script>
        (function() {
            const venueSel = document.getElementById('vms_venue_id');
            const dateInp = document.getElementById('vms_event_date');
            const autoChk = document.getElementById('vms_auto_comp_venue');
            const hint = document.getElementById('vms-venue-defaults-hint');

	            const fStruct = document.getElementById('vms_comp_structure');
	            const fFlat = document.getElementById('vms_flat_fee_amount');
	            const fSplit = document.getElementById('vms_door_split_percent');
	            const fBonusMode = document.getElementById('vms_attendance_bonus_mode');
	            const fBonusStart = document.getElementById('vms_attendance_bonus_start_count');
	            const fBonusStepSize = document.getElementById('vms_attendance_bonus_step_size');
	            const fBonusStepBonus = document.getElementById('vms_attendance_bonus_step_bonus');
	            const fBonusPerTicket = document.getElementById('vms_attendance_bonus_per_ticket_rate');
	            const fBonusMax = document.getElementById('vms_attendance_bonus_max_bonus');
	            const selInp = document.getElementById('vms_comp_selected_option');
	            const pkgInp = document.getElementById('vms_comp_package_id');
	            const optionsWrap = document.getElementById('vms-comp-options');

	            if (!venueSel || !dateInp || !autoChk || !fStruct) return;

	            let dirty = false;
	            let lastAutoAppliedSig = '';
	            [fStruct, fFlat, fSplit, fBonusMode, fBonusStart, fBonusStepSize, fBonusStepBonus, fBonusPerTicket, fBonusMax].forEach(el => {
	                if (!el) return;
	                el.addEventListener('change', () => dirty = true);
	                el.addEventListener('input', () => dirty = true);
	            });

            function isBlank(val) {
                return (val === null || val === undefined || String(val).trim() === '');
            }

	            function draftHasValues() {
	                const flat = fFlat ? fFlat.value : '';
	                const split = fSplit ? fSplit.value : '';
	                const bonusMode = fBonusMode ? fBonusMode.value : '';
	                const bonusStart = fBonusStart ? fBonusStart.value : '';
	                const stepSize = fBonusStepSize ? fBonusStepSize.value : '';
	                const stepBonus = fBonusStepBonus ? fBonusStepBonus.value : '';
	                const perTicket = fBonusPerTicket ? fBonusPerTicket.value : '';
	                const maxBonus = fBonusMax ? fBonusMax.value : '';
	                return (!isBlank(flat) || !isBlank(split) || !isBlank(bonusMode) || !isBlank(bonusStart) || !isBlank(stepSize) || !isBlank(stepBonus) || !isBlank(perTicket) || !isBlank(maxBonus));
	            }

            function normalizeSigPart(val) {
                if (isBlank(val)) return '';
                const n = Number.parseFloat(String(val).replace(/[^0-9.\-]/g, ''));
                if (!Number.isFinite(n)) return String(val).trim();
                return String(n);
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
	                    attendance_bonus_max_bonus: normalizeSigPart(fBonusMax ? fBonusMax.value : ''),
	                });
	            }

            function setHint(msg, type) {
                if (!hint) return;
                hint.textContent = msg || '';
                hint.style.color = (type === 'warn') ? '#92400e' : (type === 'ok' ? '#065f46' : '');
            }

            function applyRow(row) {
                if (!row || !row.structure) {
                    setHint('No date defaults found for that day.', 'warn');
                    return;
                }

                const source = String(row.source || 'venue').trim().toLowerCase();
                const selectedOpt = (source === 'holiday') ? 'default:holiday' : 'default:venue';
                const sourceLabel = String(row.label || (source === 'holiday' ? 'Holiday defaults' : 'Venue defaults')).trim();

                if (!autoChk.checked) {
                    setHint(sourceLabel + ' found. Turn on auto-fill to apply automatically.', 'info');
                    return;
                }

                const canOverwriteAuto = (lastAutoAppliedSig !== '' && currentDraftSig() === lastAutoAppliedSig);
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
                    optionsWrap.querySelectorAll('.vms-comp-opt-tile').forEach((tile) => {
                        const isSel = String(tile.getAttribute('data-opt') || '') === selectedOpt;
                        tile.classList.toggle('is-selected', isSel);
                    });
                }

	                [fStruct, fFlat, fSplit, fBonusMode, fBonusStart, fBonusStepSize, fBonusStepBonus, fBonusPerTicket, fBonusMax].forEach((el) => {
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
                const venue_id = venueSel.value || '';
                const event_date = dateInp.value || '';
                if (!venue_id || !event_date) return null;

                const form = new FormData();
                form.append('action', 'vms_get_venue_comp_defaults');
                form.append('venue_id', venue_id);
                form.append('event_date', event_date);

                const resp = await fetch(ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: form
                });
                const json = await resp.json();
                if (!json || !json.success) return null;
                return (json.data && json.data.row) ? json.data.row : null;
            }

            async function onVenueOrDateChange() {
                const venue_id = venueSel.value || '';
                const event_date = dateInp.value || '';

                if (!venue_id || !event_date) {
                    setHint('Select a Venue and Event Date to apply date defaults.', '');
                    return;
                }

                const row = await fetchDefaults();
                if (!row || !row.structure) {
                    setHint('No date defaults found for that day.', 'warn');
                    return;
                }

                applyRow(row);
            }

            venueSel.addEventListener('change', onVenueOrDateChange);
            dateInp.addEventListener('change', onVenueOrDateChange);
            autoChk.addEventListener('change', function() {
                if (autoChk.checked) dirty = false;
                onVenueOrDateChange();
            });

            if (selInp && String(selInp.value || '').startsWith('default:')) {
                lastAutoAppliedSig = currentDraftSig();
            }
            setHint('Select a Venue and Event Date to apply date defaults.', '');
        })();
    </script>

    <?php
        // Scroll helper (optional)
        $scroll_to = (string) get_post_meta($post->ID, '_vms_admin_scroll_to', true);
        if ($scroll_to) {
            delete_post_meta($post->ID, '_vms_admin_scroll_to');
    ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const el = document.getElementById('<?php echo esc_js($scroll_to); ?>');
                if (!el) return;
                setTimeout(() => el.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                }), 150);
            });
        </script>

    <?php
        }
    ?>
