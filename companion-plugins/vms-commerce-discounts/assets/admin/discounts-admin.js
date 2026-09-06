(function () {
    'use strict';

    var adminConfig = window.VMS_DISCOUNTS_ADMIN || {};
    var productCache = Object.create(null);

    function parseProductIdsCsv(raw) {
        raw = (raw || '').trim();
        if (!raw) {
            return [];
        }

        var parts = raw.split(/[\s,]+/);
        var ids = [];
        for (var i = 0; i < parts.length; i++) {
            var n = parseInt(parts[i], 10);
            if (!isNaN(n) && n > 0 && ids.indexOf(n) === -1) {
                ids.push(n);
            }
        }
        return ids;
    }

    function wireJsonField(field) {
        if (!field) {
            return;
        }

        var markValidity = function () {
            var raw = (field.value || '').trim();
            if (raw === '') {
                field.classList.remove('vms-discounts-invalid');
                return;
            }

            try {
                JSON.parse(raw);
                field.classList.remove('vms-discounts-invalid');
            } catch (err) {
                field.classList.add('vms-discounts-invalid');
            }
        };

        field.addEventListener('input', markValidity);
        markValidity();
    }

    function normalizeFieldNames(builder) {
        var prefix = builder.getAttribute('data-name-prefix') || '';
        var rows = builder.querySelectorAll('.vms-discounts-rules-list .vms-discounts-rule');

        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            row.setAttribute('data-index', String(i));

            var title = row.querySelector('.vms-discounts-rule-title');
            if (title) {
                title.textContent = 'Discount Rule #' + (i + 1);
            }

            var fields = row.querySelectorAll('[data-field]');
            for (var j = 0; j < fields.length; j++) {
                var field = fields[j];
                var key = field.getAttribute('data-field');
                if (!key) {
                    continue;
                }
                field.name = prefix + '[' + i + '][' + key + ']';
            }

            seedProductCacheFromRow(row);
            setRowCollapsed(row, row.classList.contains('is-collapsed'));
            refreshRowState(row, i);
        }
    }

    function getField(row, key) {
        return row ? row.querySelector('[data-field="' + key + '"]') : null;
    }

    function getFieldValue(row, key) {
        var field = getField(row, key);
        if (!field) {
            return '';
        }
        if (field.type === 'checkbox') {
            return field.checked;
        }
        return field.value || '';
    }

    function formatNumber(raw) {
        var num = parseFloat(raw);
        if (!isFinite(num)) {
            return '0';
        }
        return String(num).replace(/\.0+$/, '').replace(/(\.\d*?[1-9])0+$/, '$1');
    }

    function cacheProducts(items) {
        if (!Array.isArray(items)) {
            return;
        }

        for (var i = 0; i < items.length; i++) {
            var item = items[i] || {};
            var id = parseInt(item.id, 10);
            if (!id) {
                continue;
            }
            productCache[id] = {
                id: id,
                label: String(item.label || ('Product #' + id)),
                sku: String(item.sku || '')
            };
        }
    }

    function seedProductCacheFromRow(row) {
        if (!row) {
            return;
        }

        var chips = row.querySelectorAll('.vms-discounts-product-chip');
        for (var i = 0; i < chips.length; i++) {
            var chip = chips[i];
            var id = parseInt(chip.getAttribute('data-product-id') || '0', 10);
            if (!id) {
                continue;
            }
            productCache[id] = {
                id: id,
                label: String(chip.getAttribute('data-product-label') || ('Product #' + id)),
                sku: String(chip.getAttribute('data-product-sku') || '')
            };
        }
    }

    function buildProductMeta(item) {
        var parts = [];
        if (item.sku) {
            parts.push('SKU ' + item.sku);
        }
        parts.push('#' + item.id);
        return parts.join(' | ');
    }

    function createSelectedProductChip(item) {
        var chip = document.createElement('li');
        chip.className = 'vms-discounts-product-chip';
        chip.setAttribute('data-product-id', String(item.id));
        chip.setAttribute('data-product-label', String(item.label || ''));
        chip.setAttribute('data-product-sku', String(item.sku || ''));

        var copy = document.createElement('div');
        copy.className = 'vms-discounts-product-chip-copy';

        var title = document.createElement('strong');
        title.textContent = item.label || ('Product #' + item.id);
        copy.appendChild(title);

        var meta = document.createElement('span');
        meta.className = 'vms-discounts-product-chip-meta';
        meta.textContent = buildProductMeta(item);
        copy.appendChild(meta);

        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'button-link-delete';
        remove.setAttribute('data-action', 'remove-product');
        remove.setAttribute('data-product-id', String(item.id));
        remove.textContent = 'Remove';

        chip.appendChild(copy);
        chip.appendChild(remove);

        return chip;
    }

    function getSelectedProductIds(row) {
        return parseProductIdsCsv(getFieldValue(row, 'product_ids_csv') || '');
    }

    function renderSelectedProducts(row) {
        var list = row.querySelector('.vms-discounts-product-selected-list');
        var empty = row.querySelector('.vms-discounts-product-empty');
        if (!list) {
            return;
        }

        var ids = getSelectedProductIds(row);
        list.innerHTML = '';

        for (var i = 0; i < ids.length; i++) {
            var id = ids[i];
            var item = productCache[id] || { id: id, label: 'Product #' + id, sku: '' };
            list.appendChild(createSelectedProductChip(item));
        }

        if (empty) {
            empty.hidden = ids.length > 0;
        }
    }

    function clearSearchResults(row) {
        var status = row.querySelector('.vms-discounts-product-search-status');
        var results = row.querySelector('.vms-discounts-product-search-results');
        if (status) {
            status.textContent = '';
        }
        if (results) {
            results.innerHTML = '';
            results.hidden = true;
        }
    }

    function setRowCollapsed(row, collapsed) {
        if (!row) {
            return;
        }

        row.classList.toggle('is-collapsed', !!collapsed);

        var toggle = row.querySelector('[data-action="toggle-rule-body"]');
        if (toggle) {
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            toggle.textContent = collapsed ? 'Edit' : 'Collapse';
        }
    }

    function collapseRowsExcept(list, keepRow) {
        if (!list) {
            return;
        }

        var rows = list.querySelectorAll('.vms-discounts-rule');
        for (var i = 0; i < rows.length; i++) {
            setRowCollapsed(rows[i], rows[i] !== keepRow);
        }
    }

    function announce(builder, message) {
        var status = builder ? builder.querySelector('.vms-discounts-rules-toolbar-status') : null;
        if (status) {
            status.textContent = message || '';
        }
    }

    function revealRow(row, builder, message) {
        if (!row) {
            return;
        }

        setRowCollapsed(row, false);
        row.classList.add('vms-discounts-rule--just-added');
        announce(builder, message || 'Discount rule added. The new rule is open below.');

        window.setTimeout(function () {
            row.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 50);

        window.setTimeout(function () {
            row.classList.remove('vms-discounts-rule--just-added');
        }, 2200);

        var firstText = row.querySelector('[data-field="admin_label"], [data-field="public_label"], input:not([type="hidden"]), select, textarea');
        if (firstText && typeof firstText.focus === 'function') {
            window.setTimeout(function () {
                firstText.focus({ preventScroll: true });
            }, 450);
        }
    }

    function getLastSearchItems(row) {
        return (row && Array.isArray(row._vmsDiscountsLastSearchItems)) ? row._vmsDiscountsLastSearchItems : [];
    }

    function getLastSearchQuery(row) {
        return row ? String(row._vmsDiscountsLastSearchQuery || '') : '';
    }

    function getCheckedSearchProductIds(row) {
        var ids = [];
        if (!row) {
            return ids;
        }

        var checks = row.querySelectorAll('.vms-discounts-product-result-checkbox:checked');
        for (var i = 0; i < checks.length; i++) {
            var id = parseInt(checks[i].value || '0', 10);
            if (id > 0 && ids.indexOf(id) === -1) {
                ids.push(id);
            }
        }

        return ids;
    }

    function updateBulkAddState(row) {
        if (!row) {
            return;
        }

        var count = getCheckedSearchProductIds(row).length;
        var button = row.querySelector('[data-action="add-selected-products"]');
        var selectAll = row.querySelector('[data-action="toggle-visible-products"]');
        var visibleChecks = row.querySelectorAll('.vms-discounts-product-result-checkbox');

        if (button) {
            button.disabled = count === 0;
            button.textContent = count > 0 ? ('Add Selected (' + count + ')') : 'Add Selected';
        }

        if (selectAll) {
            var checkedCount = 0;
            for (var i = 0; i < visibleChecks.length; i++) {
                if (visibleChecks[i].checked) {
                    checkedCount++;
                }
            }
            selectAll.checked = visibleChecks.length > 0 && checkedCount === visibleChecks.length;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < visibleChecks.length;
        }
    }

    function rerenderProductSearchResults(row) {
        var items = getLastSearchItems(row);
        var query = getLastSearchQuery(row);
        if (!items.length && !query) {
            return;
        }

        renderSearchResults(row, items, query);
    }

    function renderSearchResults(row, items, query) {
        var selectedIds = getSelectedProductIds(row);
        var results = row.querySelector('.vms-discounts-product-search-results');
        var status = row.querySelector('.vms-discounts-product-search-status');
        if (!results || !status) {
            return;
        }

        items = Array.isArray(items) ? items : [];
        query = String(query || '');
        row._vmsDiscountsLastSearchItems = items;
        row._vmsDiscountsLastSearchQuery = query;

        results.innerHTML = '';
        var available = [];
        for (var i = 0; i < items.length; i++) {
            var item = items[i] || {};
            var id = parseInt(item.id, 10);
            if (!id || selectedIds.indexOf(id) !== -1) {
                continue;
            }
            available.push(item);
        }

        if (!available.length) {
            status.textContent = query ? 'No more matching products to add from this search.' : '';
            results.hidden = true;
            return;
        }

        status.textContent = available.length === 1
            ? '1 matching product is available. Add it or keep searching.'
            : available.length + ' matching products are available. Select several, then click Add Selected.';

        var actions = document.createElement('div');
        actions.className = 'vms-discounts-product-search-actions';

        var selectAllLabel = document.createElement('label');
        selectAllLabel.className = 'vms-discounts-product-search-select-all';

        var selectAll = document.createElement('input');
        selectAll.type = 'checkbox';
        selectAll.setAttribute('data-action', 'toggle-visible-products');
        selectAllLabel.appendChild(selectAll);

        var selectAllText = document.createElement('span');
        selectAllText.textContent = 'Select all visible';
        selectAllLabel.appendChild(selectAllText);

        var bulkAdd = document.createElement('button');
        bulkAdd.type = 'button';
        bulkAdd.className = 'button button-secondary';
        bulkAdd.setAttribute('data-action', 'add-selected-products');
        bulkAdd.disabled = true;
        bulkAdd.textContent = 'Add Selected';

        actions.appendChild(selectAllLabel);
        actions.appendChild(bulkAdd);
        results.appendChild(actions);

        for (var j = 0; j < available.length; j++) {
            var result = available[j];
            var resultId = parseInt(result.id, 10);
            var card = document.createElement('div');
            card.className = 'vms-discounts-product-search-result';
            card.setAttribute('data-product-id', String(resultId));

            var selectWrap = document.createElement('label');
            selectWrap.className = 'vms-discounts-product-search-result-select';

            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'vms-discounts-product-result-checkbox';
            checkbox.value = String(resultId);
            selectWrap.appendChild(checkbox);

            var copy = document.createElement('span');
            copy.className = 'vms-discounts-product-search-result-copy';

            var name = document.createElement('strong');
            name.textContent = String(result.label || ('Product #' + resultId));
            copy.appendChild(name);

            var meta = document.createElement('span');
            meta.className = 'vms-discounts-product-search-result-meta';
            meta.textContent = buildProductMeta({
                id: resultId,
                sku: String(result.sku || '')
            });
            copy.appendChild(meta);

            selectWrap.appendChild(copy);

            var add = document.createElement('button');
            add.type = 'button';
            add.className = 'button button-secondary';
            add.setAttribute('data-action', 'add-product');
            add.setAttribute('data-product-id', String(resultId));
            add.textContent = 'Add';

            card.appendChild(selectWrap);
            card.appendChild(add);
            results.appendChild(card);
        }

        results.hidden = false;
        updateBulkAddState(row);
    }

    function requestProducts(params) {
        var ajaxUrl = String(adminConfig.ajaxUrl || window.ajaxurl || '');
        var nonce = String(adminConfig.productSearchNonce || '');
        if (!ajaxUrl || !nonce || !window.fetch) {
            return Promise.resolve([]);
        }

        var body = new URLSearchParams();
        body.set('action', 'vms_discounts_search_products');
        body.set('nonce', nonce);

        if (params && params.q) {
            body.set('q', String(params.q));
        }

        if (params && params.ids && params.ids.length) {
            body.set('ids', params.ids.join(','));
        }

        return fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (payload) {
                if (!payload || payload.success !== true || !payload.data || !Array.isArray(payload.data.items)) {
                    return [];
                }
                cacheProducts(payload.data.items);
                return payload.data.items;
            })
            .catch(function () {
                return [];
            });
    }

    function ensureSelectedProductsResolved(row) {
        renderSelectedProducts(row);

        var ids = getSelectedProductIds(row);
        if (!ids.length) {
            return;
        }

        var missing = [];
        for (var i = 0; i < ids.length; i++) {
            if (!productCache[ids[i]]) {
                missing.push(ids[i]);
            }
        }

        if (!missing.length) {
            return;
        }

        var picker = row.querySelector('.vms-discounts-product-picker');
        if (!picker) {
            return;
        }

        var missingKey = missing.join(',');
        if (picker.getAttribute('data-resolving-ids') === missingKey) {
            return;
        }
        picker.setAttribute('data-resolving-ids', missingKey);

        requestProducts({ ids: missing }).then(function () {
            renderSelectedProducts(row);
            picker.removeAttribute('data-resolving-ids');
        });
    }

    function performProductSearch(row, query) {
        var status = row.querySelector('.vms-discounts-product-search-status');
        var results = row.querySelector('.vms-discounts-product-search-results');
        if (!status || !results) {
            return;
        }

        query = String(query || '').trim();
        if (query.length < 2) {
            row._vmsDiscountsLastSearchItems = [];
            row._vmsDiscountsLastSearchQuery = '';
            clearSearchResults(row);
            return;
        }

        status.textContent = 'Searching products...';
        results.hidden = true;

        requestProducts({ q: query }).then(function (items) {
            renderSearchResults(row, items, query);
        });
    }

    function setSelectedProductIds(row, ids) {
        var field = getField(row, 'product_ids_csv');
        if (!field) {
            return;
        }

        var clean = [];
        for (var i = 0; i < ids.length; i++) {
            var id = parseInt(ids[i], 10);
            if (id > 0 && clean.indexOf(id) === -1) {
                clean.push(id);
            }
        }

        field.value = clean.join(',');
        renderSelectedProducts(row);
    }

    function formatCount(value, singular, plural) {
        value = Math.max(1, parseInt(value, 10) || 1);
        return String(value) + ' ' + (value === 1 ? singular : plural);
    }

    function buildUnlockSummary(row) {
        var qualType = String(getFieldValue(row, 'qual_type') || 'ticket_qty');
        var qty = getFieldValue(row, 'required_qty') || 1;

        if (qualType === 'product_group_qty') {
            return 'Unlock after ' + formatCount(qty, 'selected product', 'selected products');
        }

        if (qualType === 'order_product_qty') {
            return 'Unlock after ' + formatCount(qty, 'paid product', 'paid products');
        }

        if (qualType === 'combo_qty') {
            return 'Unlock after ' + formatCount(qty, 'ticket/add-on combo', 'ticket/add-on combos');
        }

        return 'Unlock after ' + formatCount(qty, 'ticket', 'tickets');
    }

    function buildDiscountSummary(row) {
        var discountType = String(getFieldValue(row, 'discount_type') || 'percent');
        var amountRaw = formatNumber(getFieldValue(row, 'amount') || 0);
        var appliesTo = String(getFieldValue(row, 'applies_to') || 'tickets');
        var target = 'tickets';

        if (appliesTo === 'entitlements') {
            target = 'add-ons / entitlements';
        } else if (appliesTo === 'both') {
            target = 'tickets + add-ons';
        } else if (appliesTo === 'selected_products') {
            target = 'selected products';
        } else if (appliesTo === 'all_products') {
            target = 'all products in the order';
        }

        if (parseFloat(amountRaw) <= 0) {
            return 'Discount amount not set yet';
        }

        if (discountType === 'fixed_total') {
            return '$' + amountRaw + ' off ' + target + ' once per unlock';
        }

        if (discountType === 'fixed_per_unit') {
            return '$' + amountRaw + ' off ' + target + ' per unlock';
        }

        return amountRaw + '% off ' + target;
    }

    function refreshAmountFieldCopy(row) {
        var field = getField(row, 'amount');
        if (!field) {
            return;
        }

        var wrap = field.closest('.vms-discounts-field');
        if (!wrap) {
            return;
        }

        var label = wrap.querySelector('span');
        var help = wrap.querySelector('.vms-discounts-help');
        var discountType = String(getFieldValue(row, 'discount_type') || 'percent');

        if (label) {
            label.textContent = (discountType === 'percent') ? 'Percent off' : 'Dollar amount off';
        }

        if (help) {
            if (discountType === 'fixed_total') {
                help.textContent = 'This dollar amount applies once each time the rule unlocks.';
            } else if (discountType === 'fixed_per_unit') {
                help.textContent = 'This dollar amount applies for each unlock the cart earns.';
            } else {
                help.textContent = 'Enter the percent off. Example: 15 means 15% off.';
            }
        }
    }

    function refreshConditionalFields(row) {
        var qualType = String(getFieldValue(row, 'qual_type') || 'ticket_qty');
        var showProgress = !!getFieldValue(row, 'show_progress_hint');

        var productWrap = row.querySelector('[data-conditional="product-group"]');
        if (productWrap) {
            productWrap.hidden = qualType !== 'product_group_qty';
        }

        var progressWrap = row.querySelector('[data-conditional="progress-message"]');
        if (progressWrap) {
            progressWrap.hidden = !showProgress;
        }
    }

    function refreshRowState(row, index) {
        if (!row) {
            return;
        }

        var enabled = !!getFieldValue(row, 'enabled');
        var adminLabel = String(getFieldValue(row, 'admin_label') || '').trim();
        var publicLabel = String(getFieldValue(row, 'public_label') || '').trim();
        var title = row.querySelector('.vms-discounts-rule-title');
        var status = row.querySelector('.vms-discounts-rule-status');
        var summary = row.querySelector('.vms-discounts-rule-summary');
        var displayLabel = adminLabel || publicLabel;

        if (title) {
            title.textContent = displayLabel
                ? 'Discount Rule #' + (index + 1) + ': ' + displayLabel
                : 'Discount Rule #' + (index + 1);
        }

        if (status) {
            status.textContent = enabled ? 'Active' : 'Off';
            status.classList.toggle('is-enabled', enabled);
        }

        if (summary) {
            var stacking = String(getFieldValue(row, 'stacking_mode') || 'stack');
            summary.textContent = [
                enabled ? 'Rule is active' : 'Rule is saved but off',
                buildUnlockSummary(row),
                buildDiscountSummary(row),
                stacking === 'best_only' ? 'Best discount only' : 'Can stack with other rules'
            ].join(' • ');
        }

        refreshConditionalFields(row);
        refreshAmountFieldCopy(row);
        ensureSelectedProductsResolved(row);
    }

    function buildRuleFromRow(row) {
        var result = {};
        var fields = row.querySelectorAll('[data-field]');

        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            var key = field.getAttribute('data-field');
            if (!key) {
                continue;
            }

            if (field.type === 'checkbox') {
                result[key] = field.checked;
            } else {
                result[key] = field.value;
            }
        }

        result.product_ids = parseProductIdsCsv(result.product_ids_csv || '');
        delete result.product_ids_csv;

        return result;
    }

    function exportRowsToJson(builder) {
        var rows = builder.querySelectorAll('.vms-discounts-rules-list .vms-discounts-rule');
        var rules = [];

        for (var i = 0; i < rows.length; i++) {
            rules.push(buildRuleFromRow(rows[i]));
        }

        return JSON.stringify(rules, null, 2);
    }

    function setFieldValue(row, key, value) {
        var field = row.querySelector('[data-field="' + key + '"]');
        if (!field) {
            return;
        }

        if (field.type === 'checkbox') {
            field.checked = !!value;
            return;
        }

        field.value = (value === null || typeof value === 'undefined') ? '' : String(value);
    }

    function buildRowFromRule(builder, rule) {
        var template = builder.querySelector('template.vms-discounts-rule-template');
        if (!template || !template.content || !template.content.firstElementChild) {
            return null;
        }

        var row = template.content.firstElementChild.cloneNode(true);
        row.classList.remove('vms-discounts-rule-is-template');

        var data = rule || {};

        setFieldValue(row, 'id', data.id || '');
        setFieldValue(row, 'enabled', !!data.enabled);
        setFieldValue(row, 'admin_label', data.admin_label || '');
        setFieldValue(row, 'public_label', data.public_label || '');
        setFieldValue(row, 'priority', typeof data.priority !== 'undefined' ? data.priority : 100);
        setFieldValue(row, 'stacking_mode', data.stacking_mode || 'stack');
        setFieldValue(row, 'scope', data.scope || 'event_plus_global');
        setFieldValue(row, 'qual_type', data.qual_type || 'ticket_qty');
        setFieldValue(row, 'required_qty', typeof data.required_qty !== 'undefined' ? data.required_qty : 1);

        var productIds = '';
        if (Array.isArray(data.product_ids)) {
            productIds = data.product_ids.join(',');
        }
        if (!productIds && typeof data.product_ids_csv === 'string') {
            productIds = data.product_ids_csv;
        }
        setFieldValue(row, 'product_ids_csv', productIds);

        setFieldValue(row, 'discount_type', data.discount_type || 'percent');
        setFieldValue(row, 'amount', typeof data.amount !== 'undefined' ? data.amount : 0);
        setFieldValue(row, 'applies_to', data.applies_to || 'tickets');
        setFieldValue(row, 'max_applications_per_order', typeof data.max_applications_per_order !== 'undefined' ? data.max_applications_per_order : 1);
        setFieldValue(row, 'cap_amount', typeof data.cap_amount !== 'undefined' ? data.cap_amount : 0);
        setFieldValue(row, 'show_progress_hint', !!data.show_progress_hint);
        setFieldValue(row, 'progress_hint_template', data.progress_hint_template || '');

        return row;
    }

    function createTourOverlay() {
        var overlay = document.createElement('div');
        overlay.className = 'vms-tour-overlay vms-discounts-tour-overlay';
        overlay.innerHTML = '' +
            '<div class="vms-tour-card vms-discounts-tour-card">' +
            '  <div class="vms-discounts-tour-progress"></div>' +
            '  <h3 class="vms-discounts-tour-title"></h3>' +
            '  <div class="vms-discounts-tour-body"></div>' +
            '  <div class="vms-tour-actions">' +
            '    <button type="button" class="button" data-tour-action="prev">Back</button>' +
            '    <button type="button" class="button button-primary" data-tour-action="next">Next</button>' +
            '    <button type="button" class="button" data-tour-action="close">Close</button>' +
            '  </div>' +
            '</div>';
        document.body.appendChild(overlay);
        return overlay;
    }

    function clearHighlights() {
        document.querySelectorAll('.vms-tour-highlight').forEach(function (el) {
            el.classList.remove('vms-tour-highlight');
        });
    }

    function startTour(builder, addRuleFn) {
        if (!builder) {
            return;
        }

        clearHighlights();

        var list = builder.querySelector('.vms-discounts-rules-list');
        if (list && !list.querySelector('.vms-discounts-rule')) {
            addRuleFn();
        }

        var steps = [
            {
                selector: '[data-tour-anchor="add-rule"]',
                title: 'Step 1: Add a rule',
                html: '<p>Click <strong>Add Discount Rule</strong> to create another discount. Start simple with one rule and test it.</p>'
            },
            {
                selector: '[data-tour-anchor="enabled"]',
                title: 'Step 2: Name and activate it',
                html: '<p>Turn the rule on, give it an internal team name, and a customer-facing name shown in cart/checkout.</p>'
            },
            {
                selector: '[data-tour-anchor="qualification"]',
                title: 'Step 3: Choose what unlocks it',
                html: '<p>Set what qualifies: ticket count, specific products, any paid products, or a ticket/add-on combo. Then set the quantity needed.</p>'
            },
            {
                selector: '[data-tour-anchor="amount"]',
                title: 'Step 4: Set the discount amount',
                html: '<p><strong>Amount is dollars or percent depending on Discount Style.</strong><br>If style is Percent Off, amount is %. Otherwise amount is $.</p>'
            },
            {
                selector: '[data-tour-anchor="stacking"]',
                title: 'Step 5: Control multiple discounts',
                html: '<p><strong>Use with other discounts</strong> means this can combine.<br><strong>Apply best discount only</strong> means choose only the biggest discount.</p>'
            },
            {
                selector: '[data-tour-anchor="progress-toggle"]',
                title: 'Step 6: Customer messaging (optional)',
                html: '<p>Enable a simple progress hint like “Add 1 more item to unlock this discount.”</p>'
            },
            {
                selector: '[data-tour-anchor="advanced-json"]',
                title: 'Step 7: Advanced JSON (optional)',
                html: '<p>This is for power users only. You can keep it collapsed and run everything from the form fields.</p>',
                before: function (target) {
                    var details = target && target.closest('details');
                    if (details) {
                        details.open = true;
                    }
                }
            }
        ];

        var current = 0;
        var overlay = createTourOverlay();
        var progressEl = overlay.querySelector('.vms-discounts-tour-progress');
        var titleEl = overlay.querySelector('.vms-discounts-tour-title');
        var bodyEl = overlay.querySelector('.vms-discounts-tour-body');
        var prevBtn = overlay.querySelector('[data-tour-action="prev"]');
        var nextBtn = overlay.querySelector('[data-tour-action="next"]');
        var closeBtn = overlay.querySelector('[data-tour-action="close"]');

        function closeTour() {
            clearHighlights();
            if (overlay && overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
        }

        function setStep(idx) {
            if (idx < 0) {
                idx = 0;
            }
            if (idx >= steps.length) {
                closeTour();
                return;
            }

            current = idx;
            clearHighlights();

            var step = steps[current];
            var target = builder.querySelector(step.selector);
            if (target) {
                if (typeof step.before === 'function') {
                    step.before(target);
                }
                target.classList.add('vms-tour-highlight');
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            progressEl.textContent = 'Step ' + (current + 1) + ' of ' + steps.length;
            titleEl.textContent = step.title;
            bodyEl.innerHTML = step.html;

            prevBtn.disabled = current === 0;
            nextBtn.textContent = (current === steps.length - 1) ? 'Finish' : 'Next';
        }

        prevBtn.addEventListener('click', function () {
            setStep(current - 1);
        });

        nextBtn.addEventListener('click', function () {
            setStep(current + 1);
        });

        closeBtn.addEventListener('click', closeTour);

        overlay.addEventListener('click', function (evt) {
            if (evt.target === overlay) {
                closeTour();
            }
        });

        setStep(0);
    }

    function setupRulesBuilder(builder) {
        var list = builder.querySelector('.vms-discounts-rules-list');
        if (!list) {
            return;
        }

        function addRule() {
            var row = buildRowFromRule(builder, null);
            if (!row) {
                return;
            }
            list.appendChild(row);
            normalizeFieldNames(builder);
            collapseRowsExcept(list, row);
            revealRow(row, builder, 'Discount rule added. The new rule is open and ready to edit.');
        }

        var addButton = builder.querySelector('[data-action="add-rule"]');
        var startTourButton = builder.querySelector('[data-action="start-tour"]');
        var jsonField = builder.querySelector('.vms-discounts-json');
        var refreshButton = builder.querySelector('[data-action="refresh-advanced-json"]');
        var importButton = builder.querySelector('[data-action="import-advanced-json"]');

        if (addButton) {
            addButton.addEventListener('click', addRule);
        }

        if (startTourButton) {
            startTourButton.addEventListener('click', function () {
                startTour(builder, addRule);
            });
        }

        list.addEventListener('click', function (evt) {
            var target = evt.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }

            var action = target.getAttribute('data-action');
            if (!action) {
                return;
            }

            var row = target.closest('.vms-discounts-rule');
            if (!row) {
                return;
            }

            if (action === 'toggle-visible-products') {
                var resultChecks = row.querySelectorAll('.vms-discounts-product-result-checkbox');
                for (var t = 0; t < resultChecks.length; t++) {
                    resultChecks[t].checked = !!target.checked;
                }
                updateBulkAddState(row);
                return;
            }

            if (action === 'toggle-rule-body') {
                setRowCollapsed(row, !row.classList.contains('is-collapsed'));
                return;
            }

            if (action === 'remove-rule') {
                row.remove();
                normalizeFieldNames(builder);
                announce(builder, 'Discount rule removed.');
                return;
            }

            if (action === 'duplicate-rule') {
                var clone = row.cloneNode(true);
                clone.classList.remove('is-collapsed');
                var idField = clone.querySelector('[data-field="id"]');
                if (idField) {
                    idField.value = '';
                }
                var cloneSearch = clone.querySelector('.vms-discounts-product-search-input');
                if (cloneSearch) {
                    cloneSearch.value = '';
                }
                clone._vmsDiscountsLastSearchItems = [];
                clone._vmsDiscountsLastSearchQuery = '';
                clearSearchResults(clone);
                row.insertAdjacentElement('afterend', clone);
                normalizeFieldNames(builder);
                collapseRowsExcept(list, clone);
                revealRow(clone, builder, 'Discount rule duplicated. The copied rule is open below.');
                return;
            }

            if (action === 'remove-product') {
                var removeId = parseInt(target.getAttribute('data-product-id') || '0', 10);
                var currentIds = getSelectedProductIds(row);
                var nextIds = [];
                for (var i = 0; i < currentIds.length; i++) {
                    if (currentIds[i] !== removeId) {
                        nextIds.push(currentIds[i]);
                    }
                }
                setSelectedProductIds(row, nextIds);
                rerenderProductSearchResults(row);
                var removeIndex = parseInt(row.getAttribute('data-index') || '0', 10) || 0;
                refreshRowState(row, removeIndex);
                return;
            }

            if (action === 'add-product') {
                var addId = parseInt(target.getAttribute('data-product-id') || '0', 10);
                if (addId > 0) {
                    var ids = getSelectedProductIds(row);
                    ids.push(addId);
                    setSelectedProductIds(row, ids);
                    rerenderProductSearchResults(row);
                    var addIndex = parseInt(row.getAttribute('data-index') || '0', 10) || 0;
                    refreshRowState(row, addIndex);
                }
                return;
            }

            if (action === 'add-selected-products') {
                var checkedIds = getCheckedSearchProductIds(row);
                if (checkedIds.length) {
                    var currentProductIds = getSelectedProductIds(row);
                    setSelectedProductIds(row, currentProductIds.concat(checkedIds));
                    rerenderProductSearchResults(row);
                    var selectedIndex = parseInt(row.getAttribute('data-index') || '0', 10) || 0;
                    refreshRowState(row, selectedIndex);
                }
                return;
            }
        });

        list.addEventListener('change', function (evt) {
            var target = evt.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }

            var row = target.closest('.vms-discounts-rule');
            if (!row) {
                return;
            }

            if (target.classList.contains('vms-discounts-product-result-checkbox')) {
                updateBulkAddState(row);
                return;
            }

            var index = parseInt(row.getAttribute('data-index') || '0', 10) || 0;
            refreshRowState(row, index);
        });

        list.addEventListener('input', function (evt) {
            var target = evt.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }

            var row = target.closest('.vms-discounts-rule');
            if (!row) {
                return;
            }

            if (target.classList.contains('vms-discounts-product-search-input')) {
                clearTimeout(target._vmsDiscountsSearchTimer || 0);
                target._vmsDiscountsSearchTimer = window.setTimeout(function () {
                    performProductSearch(row, target.value || '');
                }, 220);
            }

            var index = parseInt(row.getAttribute('data-index') || '0', 10) || 0;
            refreshRowState(row, index);
        });

        list.addEventListener('keydown', function (evt) {
            var target = evt.target;
            if (!(target instanceof HTMLElement) || !target.classList.contains('vms-discounts-product-search-input')) {
                return;
            }

            if (evt.key === 'Enter') {
                evt.preventDefault();
                var row = target.closest('.vms-discounts-rule');
                if (row) {
                    performProductSearch(row, target.value || '');
                }
            }
        });

        if (refreshButton && jsonField) {
            refreshButton.addEventListener('click', function () {
                jsonField.value = exportRowsToJson(builder);
                wireJsonField(jsonField);
            });
        }

        if (importButton && jsonField) {
            importButton.addEventListener('click', function () {
                var parsed;
                try {
                    parsed = JSON.parse(jsonField.value || '[]');
                } catch (err) {
                    wireJsonField(jsonField);
                    return;
                }

                if (!Array.isArray(parsed)) {
                    return;
                }

                list.innerHTML = '';
                for (var i = 0; i < parsed.length; i++) {
                    var row = buildRowFromRule(builder, parsed[i]);
                    if (row) {
                        list.appendChild(row);
                    }
                }

                normalizeFieldNames(builder);
            });
        }

        normalizeFieldNames(builder);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var jsonFields = document.querySelectorAll('.vms-discounts-json');
        for (var i = 0; i < jsonFields.length; i++) {
            wireJsonField(jsonFields[i]);
        }

        var builders = document.querySelectorAll('.vms-discounts-rules-builder');
        for (var j = 0; j < builders.length; j++) {
            setupRulesBuilder(builders[j]);
        }
    });
})();
