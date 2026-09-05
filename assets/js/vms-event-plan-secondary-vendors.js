(function() {
    function initSecondaryVendors(root) {
        const scope = (root && typeof root.querySelector === 'function') ? root : document;
        const section = (scope.id === 'vms-secondary-vendors-section')
            ? scope
            : scope.querySelector('#vms-secondary-vendors-section');
        if (!section || section.dataset.vmsSecondaryInitBound === '1') {
            return;
        }

        const groupsWrap = section.querySelector('#vms-secondary-vendor-groups');
        const btnAddGroup = section.querySelector('#vms-secondary-vendor-add-group');
        const btnClear = section.querySelector('#vms-secondary-vendor-clear');
        const btnSave = section.querySelector('#vms-secondary-vendor-save');
        const statusEl = section.querySelector('[data-vms-secondary-save-status]');
        const groupTemplate = section.querySelector('#vms-secondary-vendor-group-template');
        const rowTemplate = section.querySelector('#vms-secondary-vendor-row-template');
        const clearInput = section.querySelector('#vms-clear-secondary-vendors-intent');
        const ajaxUrl = String(section.dataset.vmsSaveUrl || '').trim();
        const saveNonce = String(section.dataset.vmsSaveNonce || '').trim();
        const postId = parseInt(section.dataset.vmsSavePostId || '0', 10) || 0;
        const configNode = section.querySelector('[data-vms-secondary-config]');
        let config = {};

        if (!groupsWrap || !btnAddGroup || !btnSave || !groupTemplate || !rowTemplate) {
            return;
        }

        try {
            config = configNode ? JSON.parse(String(configNode.textContent || '{}')) : {};
        } catch (error) {
            config = {};
        }

        const typeOptions = Array.isArray(config.typeOptions) ? config.typeOptions : [];
        const modeOptions = Array.isArray(config.modeOptions) ? config.modeOptions : [];
        const pools = config && typeof config.pools === 'object' && config.pools ? config.pools : {};
        const labels = config && typeof config.labels === 'object' && config.labels ? config.labels : {};
        const marketTypeSlug = 'market_vendor';

        function escapeHtml(value) {
            return String(value || '').replace(/[&<>"']/g, function(char) {
                return ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                })[char] || char;
            });
        }

        function setStatus(target, message, type) {
            if (!target) {
                return;
            }
            target.textContent = String(message || '');
            target.setAttribute('data-vms-state', String(type || 'info'));
        }

        function setClearIntent(shouldClear) {
            if (clearInput) {
                clearInput.value = shouldClear ? '1' : '0';
            }
        }

        function vendorTypeOptionsForGroup(group) {
            const currentType = String(group.querySelector('.vms-secondary-vendor-group-type')?.value || '').trim();
            const used = new Set();
            groupsWrap.querySelectorAll('.vms-secondary-vendor-group').forEach((node) => {
                if (node === group) {
                    return;
                }
                const slug = String(node.querySelector('.vms-secondary-vendor-group-type')?.value || '').trim();
                if (slug) {
                    used.add(slug);
                }
            });

            return typeOptions.map((option) => {
                const slug = String(option.slug || '').trim();
                return Object.assign({}, option, {
                    disabled: slug !== '' && slug !== currentType && used.has(slug)
                });
            });
        }

        function defaultModeForType(typeSlug) {
            const match = typeOptions.find((option) => String(option.slug || '') === String(typeSlug || ''));
            return String(match && match.default_mode ? match.default_mode : 'standard');
        }

        function defaultSlotLimitForType(typeSlug) {
            const match = typeOptions.find((option) => String(option.slug || '') === String(typeSlug || ''));
            if (!match || match.default_slot_limit === undefined || match.default_slot_limit === null || match.default_slot_limit === '') {
                return '';
            }
            return String(match.default_slot_limit);
        }

        function hasSelectedType(group) {
            return !!String(group.querySelector('.vms-secondary-vendor-group-type')?.value || '').trim();
        }

        function poolForType(typeSlug) {
            const rows = pools && pools[typeSlug];
            return Array.isArray(rows) ? rows : [];
        }

        function isMarketGroup(group) {
            const typeSlug = String(group.querySelector('.vms-secondary-vendor-group-type')?.value || '').trim();
            const mode = String(group.querySelector('.vms-secondary-vendor-group-mode')?.value || '').trim();
            return mode === 'market' || typeSlug === marketTypeSlug;
        }

        function poolRowForVendor(typeSlug, vendorId) {
            const normalizedType = String(typeSlug || '').trim();
            const targetVendorId = parseInt(vendorId || '0', 10) || 0;
            if (!normalizedType || !(targetVendorId > 0)) {
                return null;
            }

            return poolForType(normalizedType).find((row) => {
                const rowVendorId = parseInt(row && row.vendor_id ? row.vendor_id : '0', 10) || 0;
                return rowVendorId === targetVendorId;
            }) || null;
        }

        function parseGroupVendorIds(group, datasetKey) {
            if (!group || !datasetKey) {
                return [];
            }

            try {
                const parsed = JSON.parse(String(group.dataset[datasetKey] || '[]'));
                return Array.isArray(parsed)
                    ? parsed.map((value) => parseInt(value || '0', 10) || 0).filter((value) => value > 0)
                    : [];
            } catch (error) {
                return [];
            }
        }

        function buildRowIndicators(group, vendorId) {
            const normalizedVendorId = parseInt(vendorId || '0', 10) || 0;
            const typeSlug = String(group.querySelector('.vms-secondary-vendor-group-type')?.value || '').trim();
            const badges = [];
            const market = isMarketGroup(group);
            const pendingLabel = String(labels.pendingVendor || 'Select vendor');
            const marketLabel = String(labels.market || 'Market');

            if (!(normalizedVendorId > 0)) {
                badges.push({ label: pendingLabel, variant: 'pending' });
            } else {
                const row = poolRowForVendor(typeSlug, normalizedVendorId);
                const missingIds = parseGroupVendorIds(group, 'vmsMissingIds');
                const mismatchIds = parseGroupVendorIds(group, 'vmsMismatchIds');
                const unqualifiedIds = parseGroupVendorIds(group, 'vmsUnqualifiedIds');

                if (missingIds.includes(normalizedVendorId)) {
                    badges.push({ label: String(labels.missingVendor || 'Missing vendor'), variant: 'missing' });
                } else {
                    const availability = String((row && row.availability_state) || '').trim();
                    if (availability === 'available') {
                        badges.push({ label: String(labels.available || 'Available'), variant: 'available' });
                    } else if (availability === 'unavailable') {
                        badges.push({ label: String(labels.unavailable || 'Not available'), variant: 'unavailable' });
                    } else {
                        badges.push({ label: String(labels.unknownAvailability || 'Availability unknown'), variant: 'unknown' });
                    }
                }

                if (mismatchIds.includes(normalizedVendorId)) {
                    badges.push({ label: String(labels.typeMismatch || 'Type mismatch'), variant: 'mismatch' });
                }
                const rowNeedsAttention = !!(row && Object.prototype.hasOwnProperty.call(row, 'qualified') && !row.qualified);
                if (unqualifiedIds.includes(normalizedVendorId) || rowNeedsAttention) {
                    badges.push({ label: String(labels.needsAttention || 'Needs attention'), variant: 'attention' });
                } else {
                    badges.push({ label: String(labels.qualified || 'Qualified'), variant: 'qualified' });
                }
            }

            if (market) {
                badges.unshift({ label: marketLabel, variant: 'market' });
            }

            return badges.map((badge) => {
                return `<span class="vms-secondary-vendor-badge vms-secondary-vendor-badge--${escapeHtml(String(badge.variant || 'unknown'))}">${escapeHtml(String(badge.label || ''))}</span>`;
            }).join('');
        }

        function updateRowIndicators(group, row) {
            if (!group || !row) {
                return;
            }
            const indicators = row.querySelector('[data-vms-secondary-row-indicators]');
            if (!indicators) {
                return;
            }
            const select = row.querySelector('.vms-secondary-vendor-select');
            indicators.innerHTML = buildRowIndicators(group, select ? select.value : '');
        }

        function updateGroupMarketTarget(group, market) {
            if (!group) {
                return;
            }
            const isMarket = market !== undefined ? !!market : isMarketGroup(group);
            const targetField = group.querySelector('.vms-secondary-vendor-group__field--market-target');
            const dispatchField = group.querySelector('.vms-secondary-vendor-group__field--market-dispatch');
            const neededInput = group.querySelector('.vms-secondary-vendor-group-needed-slots');
            const dispatchHidden = group.querySelector('.vms-secondary-vendor-group-open-for-dispatch-hidden');
            const dispatchInput = group.querySelector('.vms-secondary-vendor-group-open-for-dispatch');

            if (targetField) {
                targetField.hidden = !isMarket;
            }
            if (dispatchField) {
                dispatchField.hidden = !isMarket;
            }
            [neededInput, dispatchHidden, dispatchInput].forEach((input) => {
                if (input) {
                    input.disabled = !isMarket;
                }
            });
        }

        function updateGroupLayout(group) {
            if (!group) {
                return;
            }
            const hasType = hasSelectedType(group);
            const market = isMarketGroup(group);
            const guidance = group.querySelector('.vms-secondary-vendor-group__guidance');
            group.classList.toggle('vms-secondary-vendor-group--market', market);
            group.classList.toggle('vms-secondary-vendor-group--type-pending', !hasType);
            if (guidance) {
                guidance.hidden = hasType;
            }
            updateGroupMarketTarget(group, market);
            Array.from(group.querySelectorAll('.vms-secondary-vendor-row')).forEach((row) => {
                updateRowIndicators(group, row);
            });
        }

        function updateGroupCapacityOverride(group, isOverCapacity) {
            if (!group) {
                return;
            }
            const overrideWrap = group.querySelector('.vms-secondary-vendor-group__override');
            const overrideInput = group.querySelector('.vms-secondary-vendor-group-over-capacity-override');
            if (!overrideWrap || !overrideInput) {
                return;
            }

            overrideWrap.hidden = !isOverCapacity;
            if (!isOverCapacity) {
                overrideInput.checked = false;
            }
        }

        function ensureGroupTypeOptions(group) {
            const typeSelect = group.querySelector('.vms-secondary-vendor-group-type');
            if (!typeSelect) {
                return;
            }

            const currentValue = String(typeSelect.value || '').trim();
            typeSelect.innerHTML = '';
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = String(labels.selectType || '-- Select a Vendor Type --');
            typeSelect.appendChild(placeholder);

            vendorTypeOptionsForGroup(group).forEach((option) => {
                const node = document.createElement('option');
                node.value = String(option.slug || '');
                node.textContent = String(option.label || option.slug || '');
                if (option.disabled) {
                    node.disabled = true;
                }
                if (node.value === currentValue) {
                    node.selected = true;
                }
                typeSelect.appendChild(node);
            });
        }

        function ensureModeOptions(group) {
            const modeSelect = group.querySelector('.vms-secondary-vendor-group-mode');
            if (!modeSelect) {
                return;
            }
            const currentValue = String(modeSelect.value || '').trim();
            modeSelect.innerHTML = '';
            modeOptions.forEach((option) => {
                const node = document.createElement('option');
                node.value = String(option.slug || '');
                node.textContent = String(option.label || option.slug || '');
                if (node.value === currentValue) {
                    node.selected = true;
                }
                modeSelect.appendChild(node);
            });
            if (!modeSelect.value && modeOptions.length) {
                modeSelect.value = String(modeOptions[0].slug || 'standard');
            }
        }

        function syncVendorSelect(select, typeSlug, selectedValue) {
            if (!select) {
                return;
            }

            const normalizedType = String(typeSlug || '').trim();
            const currentSelected = selectedValue !== undefined && selectedValue !== null
                ? String(selectedValue)
                : String(select.value || select.dataset.selectedId || '');
            select.innerHTML = '';
            if (!normalizedType) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = String(labels.selectTypeFirst || '-- Select a Vendor Type first --');
                select.appendChild(opt);
                select.disabled = true;
                return;
            }

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = String(labels.selectVendor || '-- Select a Vendor --');
            select.appendChild(placeholder);

            poolForType(normalizedType).forEach((row) => {
                const vendorId = parseInt(row && row.vendor_id ? row.vendor_id : '0', 10) || 0;
                if (!(vendorId > 0)) {
                    return;
                }
                const opt = document.createElement('option');
                opt.value = String(vendorId);
                opt.textContent = String(row.label || row.vendor_title || vendorId);
                if (String(vendorId) === currentSelected) {
                    opt.selected = true;
                }
                select.appendChild(opt);
            });

            select.disabled = false;
            select.dataset.selectedId = String(select.value || '');
        }

        function updateAddNewLink(group) {
            const link = group.querySelector('.vms-secondary-vendor-add-new-link');
            const typeSlug = String(group.querySelector('.vms-secondary-vendor-group-type')?.value || '').trim();
            if (!link) {
                return;
            }

            const url = new URL(link.href, window.location.origin);
            if (typeSlug) {
                url.searchParams.set('vms_prefill_vendor_type', typeSlug);
            } else {
                url.searchParams.delete('vms_prefill_vendor_type');
            }
            link.href = url.toString();
        }

        function appendGroupMarketTargetSummary(parts, group, filled, market) {
            if (!market) {
                return;
            }
            const neededValue = String(group.querySelector('.vms-secondary-vendor-group-needed-slots')?.value || '').trim();
            if (!neededValue) {
                return;
            }
            const target = Math.max(0, parseInt(neededValue, 10) || 0);
            const openNeeded = Math.max(0, target - filled);
            const dispatchInput = group.querySelector('.vms-secondary-vendor-group-open-for-dispatch');
            const openForDispatch = dispatchInput ? !!dispatchInput.checked : true;
            const targetTemplate = String(labels.target || 'Target %d');
            parts.push(targetTemplate.replace('%d', String(target)));
            if (openForDispatch) {
                const neededTemplate = String(labels.needed || '%d needed');
                parts.push(neededTemplate.replace('%d', String(openNeeded)));
            } else {
                parts.push(String(labels.hiddenFromDispatch || 'Hidden from ADD'));
            }
        }

        function updateGroupSummary(group) {
            const summary = group.querySelector('.vms-secondary-vendor-group__summary');
            if (!summary) {
                return;
            }

            const hasType = hasSelectedType(group);
            const market = isMarketGroup(group);
            const slotLimitValue = String(group.querySelector('.vms-secondary-vendor-group-slot-limit')?.value || '').trim();
            const filled = Array.from(group.querySelectorAll('.vms-secondary-vendor-select'))
                .map((select) => String(select.value || '').trim())
                .filter(Boolean).length;
            const parts = [];
            let isOverCapacity = false;

            summary.classList.remove('is-warning');

            if (!slotLimitValue) {
                parts.push(`${filled} selected`);
            } else {
                const limit = Math.max(0, parseInt(slotLimitValue, 10) || 0);
                parts.push(`${filled} of ${limit} filled`);
                if (hasType && filled > limit) {
                    const overBy = filled - limit;
                    const template = String(labels.overCapacity || 'Over capacity by %d');
                    parts.push(market ? String(labels.market || 'Market') : String(labels.standard || 'Standard'));
                    appendGroupMarketTargetSummary(parts, group, filled, market);
                    parts.push(template.replace('%d', String(overBy)));
                    isOverCapacity = true;
                    summary.classList.add('is-warning');
                    summary.textContent = parts.join(' • ');
                    updateGroupCapacityOverride(group, isOverCapacity);
                    updateGroupLayout(group);
                    return;
                }
            }

            if (!hasType) {
                parts.push(String(labels.chooseType || 'Choose type first'));
                summary.textContent = parts.join(' • ');
                updateGroupCapacityOverride(group, false);
                updateGroupLayout(group);
                return;
            }

            if (!slotLimitValue) {
                parts.push(market ? String(labels.market || 'Market') : String(labels.standard || 'Standard'));
                appendGroupMarketTargetSummary(parts, group, filled, market);
                parts.push(String(labels.occupancyUnknown || 'No slot limit set'));
                summary.textContent = parts.join(' • ');
                updateGroupCapacityOverride(group, false);
                updateGroupLayout(group);
                return;
            }

            const limit = Math.max(0, parseInt(slotLimitValue, 10) || 0);
            parts.push(market ? String(labels.market || 'Market') : String(labels.standard || 'Standard'));
            appendGroupMarketTargetSummary(parts, group, filled, market);
            if (filled > limit) {
                const overBy = filled - limit;
                const template = String(labels.overCapacity || 'Over capacity by %d');
                parts.push(template.replace('%d', String(overBy)));
                isOverCapacity = true;
                summary.classList.add('is-warning');
            }
            summary.textContent = parts.join(' • ');
            updateGroupCapacityOverride(group, isOverCapacity);
            updateGroupLayout(group);
        }

        function updateGroupNames(group, groupIndex) {
            group.dataset.vmsGroupIndex = String(groupIndex);
            const typeSelect = group.querySelector('.vms-secondary-vendor-group-type');
            const modeSelect = group.querySelector('.vms-secondary-vendor-group-mode');
            const slotInput = group.querySelector('.vms-secondary-vendor-group-slot-limit');
            const overrideInput = group.querySelector('.vms-secondary-vendor-group-over-capacity-override');
            const neededInput = group.querySelector('.vms-secondary-vendor-group-needed-slots');
            const dispatchHidden = group.querySelector('.vms-secondary-vendor-group-open-for-dispatch-hidden');
            const dispatchInput = group.querySelector('.vms-secondary-vendor-group-open-for-dispatch');
            if (typeSelect) {
                typeSelect.name = `vms_secondary_vendor_assignments[${groupIndex}][type_slug]`;
            }
            if (modeSelect) {
                modeSelect.name = `vms_secondary_vendor_assignments[${groupIndex}][mode]`;
            }
            if (slotInput) {
                slotInput.name = `vms_secondary_vendor_assignments[${groupIndex}][slot_limit]`;
            }
            if (overrideInput) {
                overrideInput.name = `vms_secondary_vendor_assignments[${groupIndex}][allow_over_capacity]`;
            }
            if (neededInput) {
                neededInput.name = `vms_secondary_vendor_assignments[${groupIndex}][needed_slots]`;
            }
            if (dispatchHidden) {
                dispatchHidden.name = `vms_secondary_vendor_assignments[${groupIndex}][open_for_dispatch]`;
            }
            if (dispatchInput) {
                dispatchInput.name = `vms_secondary_vendor_assignments[${groupIndex}][open_for_dispatch]`;
            }
            Array.from(group.querySelectorAll('.vms-secondary-vendor-row')).forEach((row, rowIndex) => {
                row.dataset.vmsRowIndex = String(rowIndex);
                const select = row.querySelector('.vms-secondary-vendor-select');
                if (select) {
                    select.name = `vms_secondary_vendor_assignments[${groupIndex}][vendor_ids][]`;
                }
            });
        }

        function wireRow(group, row) {
            if (!row || row.dataset.vmsSecondaryRowBound === '1') {
                return;
            }
            row.dataset.vmsSecondaryRowBound = '1';
            const btn = row.querySelector('.vms-secondary-vendor-remove');
            const select = row.querySelector('.vms-secondary-vendor-select');
            if (btn) {
                btn.addEventListener('click', function() {
                    row.remove();
                    if (!group.querySelector('.vms-secondary-vendor-row')) {
                        addRow(group, '');
                    }
                    renumberGroups();
                    updateGroupSummary(group);
                    setClearIntent(false);
                    setStatus(statusEl, '', 'info');
                });
            }
            if (select) {
                select.addEventListener('change', function() {
                    select.dataset.selectedId = String(select.value || '');
                    updateRowIndicators(group, row);
                    updateGroupSummary(group);
                    setClearIntent(false);
                    setStatus(statusEl, '', 'info');
                });
            }
        }

        function addRow(group, selectedValue) {
            const rows = group.querySelector('.vms-secondary-vendor-rows');
            if (!rows) {
                return null;
            }
            const node = rowTemplate.content.cloneNode(true);
            const row = node.querySelector('.vms-secondary-vendor-row');
            rows.appendChild(node);
            const appendedRow = rows.lastElementChild;
            const select = appendedRow ? appendedRow.querySelector('.vms-secondary-vendor-select') : null;
            if (select) {
                select.dataset.selectedId = String(selectedValue || '');
            }
            wireRow(group, appendedRow);
            syncVendorSelect(select, String(group.querySelector('.vms-secondary-vendor-group-type')?.value || ''), selectedValue || '');
            updateRowIndicators(group, appendedRow);
            updateGroupSummary(group);
            return appendedRow;
        }

        function refreshGroup(group, options = {}) {
            const preserveSelections = options.preserveSelections !== false;
            const resetDefaults = !!options.resetDefaults;
            const typeSelect = group.querySelector('.vms-secondary-vendor-group-type');
            const modeSelect = group.querySelector('.vms-secondary-vendor-group-mode');
            const slotInput = group.querySelector('.vms-secondary-vendor-group-slot-limit');
            const selectedType = String(typeSelect?.value || '').trim();

            ensureGroupTypeOptions(group);
            ensureModeOptions(group);

            if (resetDefaults && modeSelect) {
                modeSelect.value = defaultModeForType(selectedType);
            }
            if (resetDefaults && slotInput) {
                slotInput.value = defaultSlotLimitForType(selectedType);
            }

            const rows = Array.from(group.querySelectorAll('.vms-secondary-vendor-row'));
            if (!rows.length) {
                addRow(group, '');
            }
            Array.from(group.querySelectorAll('.vms-secondary-vendor-row')).forEach((row) => {
                const select = row.querySelector('.vms-secondary-vendor-select');
                const selectedValue = preserveSelections ? String(select?.value || select?.dataset.selectedId || '') : '';
                syncVendorSelect(select, selectedType, selectedValue);
                updateRowIndicators(group, row);
            });
            updateAddNewLink(group);
            updateGroupSummary(group);
        }

        function wireGroup(group) {
            if (!group || group.dataset.vmsSecondaryGroupBound === '1') {
                return;
            }
            group.dataset.vmsSecondaryGroupBound = '1';
            const addRowBtn = group.querySelector('.vms-secondary-vendor-add-row');
            const removeGroupBtn = group.querySelector('.vms-secondary-vendor-remove-group');
            const typeSelect = group.querySelector('.vms-secondary-vendor-group-type');
            const modeSelect = group.querySelector('.vms-secondary-vendor-group-mode');
            const slotInput = group.querySelector('.vms-secondary-vendor-group-slot-limit');
            const overrideInput = group.querySelector('.vms-secondary-vendor-group-over-capacity-override');
            const neededInput = group.querySelector('.vms-secondary-vendor-group-needed-slots');
            const dispatchInput = group.querySelector('.vms-secondary-vendor-group-open-for-dispatch');

            if (addRowBtn) {
                addRowBtn.addEventListener('click', function() {
                    addRow(group, '');
                    renumberGroups();
                    setClearIntent(false);
                    setStatus(statusEl, '', 'info');
                });
            }
            if (removeGroupBtn) {
                removeGroupBtn.addEventListener('click', function() {
                    group.remove();
                    refreshAllGroups();
                    setClearIntent(false);
                    setStatus(statusEl, '', 'info');
                });
            }
            if (typeSelect) {
                typeSelect.addEventListener('change', function() {
                    refreshGroup(group, { preserveSelections: false, resetDefaults: true });
                    refreshAllGroups();
                    setClearIntent(false);
                    setStatus(statusEl, '', 'info');
                });
            }
            if (modeSelect) {
                modeSelect.addEventListener('change', function() {
                    updateGroupSummary(group);
                    setClearIntent(false);
                    setStatus(statusEl, '', 'info');
                });
            }
            if (slotInput) {
                slotInput.addEventListener('input', function() {
                    updateGroupSummary(group);
                    setClearIntent(false);
                    setStatus(statusEl, '', 'info');
                });
            }
            if (overrideInput) {
                overrideInput.addEventListener('change', function() {
                    setClearIntent(false);
                    setStatus(statusEl, '', 'info');
                });
            }
            if (neededInput) {
                neededInput.addEventListener('input', function() {
                    updateGroupSummary(group);
                    setClearIntent(false);
                    setStatus(statusEl, '', 'info');
                });
            }
            if (dispatchInput) {
                dispatchInput.addEventListener('change', function() {
                    updateGroupSummary(group);
                    setClearIntent(false);
                    setStatus(statusEl, '', 'info');
                });
            }

            Array.from(group.querySelectorAll('.vms-secondary-vendor-row')).forEach((row) => wireRow(group, row));
        }

        function renumberGroups() {
            Array.from(groupsWrap.querySelectorAll('.vms-secondary-vendor-group')).forEach((group, index) => {
                updateGroupNames(group, index);
            });
        }

        function nextAvailableType() {
            const used = new Set(Array.from(groupsWrap.querySelectorAll('.vms-secondary-vendor-group-type')).map((select) => String(select.value || '').trim()).filter(Boolean));
            const next = typeOptions.find((option) => {
                const slug = String(option.slug || '').trim();
                return slug && !used.has(slug);
            });
            return next ? String(next.slug || '') : '';
        }

        function createGroup(typeSlug = '') {
            const node = groupTemplate.content.cloneNode(true);
            groupsWrap.appendChild(node);
            const group = groupsWrap.lastElementChild;
            wireGroup(group);
            const initialType = String(typeSlug || '').trim();
            const typeSelect = group.querySelector('.vms-secondary-vendor-group-type');
            if (typeSelect && initialType) {
                typeSelect.value = initialType;
            }
            refreshGroup(group, { preserveSelections: false, resetDefaults: true });
            renumberGroups();
            return group;
        }

        function refreshAllGroups() {
            Array.from(groupsWrap.querySelectorAll('.vms-secondary-vendor-group')).forEach((group) => {
                wireGroup(group);
                refreshGroup(group, { preserveSelections: true, resetDefaults: false });
            });
            renumberGroups();
            btnAddGroup.disabled = !nextAvailableType();
        }

        btnAddGroup.addEventListener('click', function() {
            if (btnAddGroup.disabled) {
                return;
            }
            createGroup('');
            refreshAllGroups();
            setClearIntent(false);
            setStatus(statusEl, '', 'info');
        });

        if (btnClear) {
            btnClear.addEventListener('click', function() {
                groupsWrap.innerHTML = '';
                setClearIntent(true);
                refreshAllGroups();
                setStatus(statusEl, '', 'info');
            });
        }

        function serializeGroups() {
            return Array.from(groupsWrap.querySelectorAll('.vms-secondary-vendor-group')).map((group) => {
                const typeSlug = String(group.querySelector('.vms-secondary-vendor-group-type')?.value || '').trim();
                const mode = String(group.querySelector('.vms-secondary-vendor-group-mode')?.value || '').trim();
                const slotLimit = String(group.querySelector('.vms-secondary-vendor-group-slot-limit')?.value || '').trim();
                const allowOverCapacity = !!group.querySelector('.vms-secondary-vendor-group-over-capacity-override')?.checked;
                const market = isMarketGroup(group);
                const neededSlots = String(group.querySelector('.vms-secondary-vendor-group-needed-slots')?.value || '').trim();
                const openForDispatch = !!group.querySelector('.vms-secondary-vendor-group-open-for-dispatch')?.checked;
                const vendorIds = Array.from(group.querySelectorAll('.vms-secondary-vendor-select'))
                    .map((select) => String(select.value || '').trim())
                    .filter(Boolean);
                return {
                    typeSlug,
                    mode,
                    slotLimit,
                    allowOverCapacity,
                    market,
                    neededSlots,
                    openForDispatch,
                    vendorIds
                };
            }).filter((group) => group.typeSlug !== '' || group.vendorIds.length > 0 || group.slotLimit !== '' || (group.market && group.neededSlots !== ''));
        }

        async function saveModule() {
            if (!btnSave || btnSave.disabled) {
                return;
            }
            if (!ajaxUrl || !saveNonce || !(postId > 0)) {
                setStatus(statusEl, String(labels.saveUnavailable || 'Additional Vendors save is not available right now.'), 'error');
                return;
            }

            btnSave.disabled = true;
            setStatus(statusEl, String(labels.saving || 'Saving Additional Vendors…'), 'info');

            const params = new URLSearchParams();
            params.set('action', 'vms_save_event_plan_secondary_vendors');
            params.set('post_id', String(postId));
            params.set('nonce', saveNonce);
            params.set('vms_clear_secondary_vendors', clearInput ? String(clearInput.value || '0') : '0');

            serializeGroups().forEach((group, groupIndex) => {
                params.set(`vms_secondary_vendor_assignments[${groupIndex}][type_slug]`, group.typeSlug);
                params.set(`vms_secondary_vendor_assignments[${groupIndex}][mode]`, group.mode);
                params.set(`vms_secondary_vendor_assignments[${groupIndex}][slot_limit]`, group.slotLimit);
                if (group.allowOverCapacity) {
                    params.set(`vms_secondary_vendor_assignments[${groupIndex}][allow_over_capacity]`, '1');
                }
                if (group.market) {
                    if (group.neededSlots !== '') {
                        params.set(`vms_secondary_vendor_assignments[${groupIndex}][needed_slots]`, group.neededSlots);
                    }
                    params.set(`vms_secondary_vendor_assignments[${groupIndex}][open_for_dispatch]`, group.openForDispatch ? '1' : '0');
                }
                group.vendorIds.forEach((vendorId) => {
                    params.append(`vms_secondary_vendor_assignments[${groupIndex}][vendor_ids][]`, vendorId);
                });
            });

            const scenarioField = document.querySelector('#post input[name="_vms_ep_perf_trace_scenario"]');
            if (scenarioField && scenarioField.value) {
                params.set('_vms_ep_perf_trace_scenario', String(scenarioField.value || ''));
            }

            try {
                const response = await window.fetch(ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: params.toString()
                });
                const payload = await response.json().catch(() => null);
                if (!response.ok || !payload || !payload.success || !payload.data || typeof payload.data.html !== 'string') {
                    const message = payload && payload.data && typeof payload.data.message === 'string'
                        ? payload.data.message
                        : '';
                    throw new Error(message || 'secondary_vendor_save_failed');
                }

                const collapsibleSection = section.closest('.vms-collapsible-section[data-section-key]');
                const body = collapsibleSection ? collapsibleSection.querySelector('.vms-collapsible-body') : null;
                if (!collapsibleSection || !body) {
                    throw new Error('secondary_vendor_render_target_missing');
                }

                body.innerHTML = payload.data.html;
                collapsibleSection.dataset.vmsLazyLoaded = '1';
                collapsibleSection.dataset.hasData = payload.data.has_data ? '1' : '0';
                const meta = collapsibleSection.querySelector('.vms-collapsible-meta');
                if (meta && typeof payload.data.summary_meta === 'string') {
                    meta.textContent = payload.data.summary_meta;
                }
                if (typeof window.BVMGR_EVENT_PLAN_PERSIST_REQUESTED_SECTION === 'function') {
                    window.BVMGR_EVENT_PLAN_PERSIST_REQUESTED_SECTION('secondary_vendors');
                }
                if (typeof window.BVMGR_EVENT_PLAN_INIT_COLLAPSIBLE_SECTION === 'function') {
                    window.BVMGR_EVENT_PLAN_INIT_COLLAPSIBLE_SECTION(collapsibleSection);
                }
                if (typeof window.BVMGR_EVENT_PLAN_INIT_SECONDARY_VENDORS === 'function') {
                    window.BVMGR_EVENT_PLAN_INIT_SECONDARY_VENDORS(body);
                }
                const nextSection = body.querySelector('#vms-secondary-vendors-section');
                const nextStatus = nextSection ? nextSection.querySelector('[data-vms-secondary-save-status]') : null;
                setStatus(
                    nextStatus,
                    typeof payload.data.message === 'string' && payload.data.message !== ''
                        ? payload.data.message
                        : 'Additional Vendors saved.',
                    payload.data.changed ? 'success' : 'info'
                );
                return;
            } catch (error) {
                const message = error && error.message && error.message !== 'secondary_vendor_save_failed'
                    ? error.message
                    : String(labels.saveFailed || 'Additional Vendors could not be saved. Reload the page and try again.');
                setStatus(statusEl, message, 'error');
            } finally {
                if (btnSave && document.body.contains(btnSave)) {
                    btnSave.disabled = false;
                }
            }
        }

        if (btnSave) {
            btnSave.addEventListener('click', function() {
                saveModule();
            });
        }

        refreshAllGroups();
        section.dataset.vmsSecondaryInitBound = '1';
    }

    window.BVMGR_EVENT_PLAN_INIT_SECONDARY_VENDORS = initSecondaryVendors;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initSecondaryVendors(document);
        }, { once: true });
    } else {
        initSecondaryVendors(document);
    }
})();
