(function () {
  var config = window.BVMGR_STATUS_NOTICES_ADMIN || {};
  var strings = config.strings || {};

  function t(key, fallback) {
    if (Object.prototype.hasOwnProperty.call(strings, key) && strings[key]) {
      return String(strings[key]);
    }
    return fallback;
  }

  var selectAll = document.getElementById('vms-status-select-all');
  if (selectAll) {
    selectAll.addEventListener('change', function () {
      var checks = document.querySelectorAll('.vms-status-row-check');
      for (var i = 0; i < checks.length; i += 1) {
        checks[i].checked = !!selectAll.checked;
      }
    });
  }

  var root = document.getElementById('vms-status-notice-form');
  if (!root) {
    return;
  }

  var listSyncers = [];

  function formValue(name) {
    var field = root.querySelector('[name="' + name + '"]');
    if (!field) return '';
    return String(field.value || '');
  }

  function checkedValues(name) {
    var nodes = root.querySelectorAll('input[name="' + name + '[]"]:checked');
    var out = [];
    for (var i = 0; i < nodes.length; i += 1) {
      out.push(String(nodes[i].value || ''));
    }
    return out;
  }

  function checkedSelectorValues(selector) {
    var nodes = root.querySelectorAll(selector);
    var out = [];
    for (var i = 0; i < nodes.length; i += 1) {
      out.push(String(nodes[i].value || ''));
    }
    return out;
  }

  function hasTextAreaLines(name) {
    var field = root.querySelector('[name="' + name + '"]');
    if (!field) return false;
    var value = String(field.value || '').trim();
    return value.length > 0;
  }

  function labelFor(map, key) {
    if (
      map &&
      typeof map === 'object' &&
      Object.prototype.hasOwnProperty.call(map, key)
    ) {
      return String(map[key]);
    }
    return String(key);
  }

  function mapLabels(values, map) {
    var out = [];
    for (var i = 0; i < values.length; i += 1) {
      out.push(labelFor(map, values[i]));
    }
    return out;
  }

  function splitLines(value) {
    return String(value || '')
      .replace(/\r\n?/g, '\n')
      .split('\n');
  }

  function sanitizeListValue(value, valueType) {
    var clean = String(value || '').trim();
    if (!clean) {
      return '';
    }
    if (valueType === 'int') {
      var digits = clean.replace(/[^\d]/g, '');
      if (!digits) {
        return '';
      }
      var numeric = parseInt(digits, 10);
      if (!numeric || numeric < 1) {
        return '';
      }
      return String(numeric);
    }
    return clean;
  }

  function compactListValues(values, valueType) {
    var seen = {};
    var out = [];
    for (var i = 0; i < values.length; i += 1) {
      var clean = sanitizeListValue(values[i], valueType);
      if (!clean || seen[clean]) {
        continue;
      }
      seen[clean] = true;
      out.push(clean);
    }
    return out;
  }

  function makeIconButton(label) {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'button-link-delete vms-status-list-editor__remove';
    button.textContent = label;
    return button;
  }

  function enhanceRowList(field) {
    var valueType = String(field.getAttribute('data-value-type') || 'text') === 'int' ? 'int' : 'text';
    var placeholder = String(field.getAttribute('data-row-placeholder') || '');
    var values = splitLines(field.value);
    if (values.length === 0) {
      values = [''];
    }

    field.classList.add('vms-status-list-source--hidden');
    field.setAttribute('aria-hidden', 'true');
    field.tabIndex = -1;

    var wrapper = document.createElement('div');
    wrapper.className = 'vms-status-list-editor';

    var rows = document.createElement('div');
    rows.className = 'vms-status-list-editor__rows';
    wrapper.appendChild(rows);

    var addBtn = document.createElement('button');
    addBtn.type = 'button';
    addBtn.className = 'button vms-status-list-editor__add';
    addBtn.textContent = t('addRow', 'Add row');
    wrapper.appendChild(addBtn);

    field.parentNode.insertBefore(wrapper, field.nextSibling);

    function syncField() {
      var compacted = compactListValues(values, valueType);
      field.value = compacted.join('\n');
    }

    function renderRows() {
      rows.innerHTML = '';

      for (var index = 0; index < values.length; index += 1) {
        (function (idx) {
          var row = document.createElement('div');
          row.className = 'vms-status-list-editor__row';

          var input = document.createElement('input');
          if (valueType === 'int') {
            input.type = 'number';
            input.min = '1';
            input.step = '1';
            input.inputMode = 'numeric';
          } else {
            input.type = 'text';
          }
          input.placeholder = placeholder;
          input.value = String(values[idx] || '');
          row.appendChild(input);

          var removeBtn = makeIconButton(t('remove', 'Remove'));
          removeBtn.addEventListener('click', function () {
            values.splice(idx, 1);
            if (values.length === 0) {
              values = [''];
            }
            syncField();
            renderRows();
          });
          row.appendChild(removeBtn);

          input.addEventListener('input', function () {
            values[idx] = String(input.value || '');
            syncField();
          });

          input.addEventListener('blur', function () {
            values[idx] = String(input.value || '');
            var compacted = compactListValues(values, valueType);
            values = compacted.length > 0 ? compacted : [''];
            syncField();
            renderRows();
          });

          rows.appendChild(row);
        })(index);
      }
    }

    addBtn.addEventListener('click', function () {
      values.push('');
      renderRows();
      var lastInput = rows.querySelector('.vms-status-list-editor__row:last-child input');
      if (lastInput) {
        lastInput.focus();
      }
    });

    syncField();
    renderRows();

    return syncField;
  }

  function fetchObjectSearchResults(query) {
    var ajaxUrl = String(config.ajaxUrl || window.ajaxurl || '');
    if (!window.fetch || !ajaxUrl) {
      return Promise.resolve([]);
    }

    var params = [
      'action=vms_status_notice_search_objects',
      'nonce=' + encodeURIComponent(String(config.searchNonce || '')),
      'q=' + encodeURIComponent(query),
    ];
    var separator = ajaxUrl.indexOf('?') === -1 ? '?' : '&';
    var url = ajaxUrl + separator + params.join('&');

    return window
      .fetch(url, { credentials: 'same-origin' })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('search_http_' + response.status);
        }
        return response.json();
      })
      .then(function (payload) {
        if (!payload || !payload.success || !payload.data || !payload.data.items) {
          return [];
        }
        return payload.data.items;
      });
  }

  function enhanceObjectPicker(field) {
    var minChars = Number(config.searchMinChars || 2);
    var selectedIds = compactListValues(splitLines(field.value), 'int');
    var labelById = {};
    for (var i = 0; i < selectedIds.length; i += 1) {
      labelById[selectedIds[i]] = 'ID #' + selectedIds[i];
    }

    field.classList.add('vms-status-list-source--hidden');
    field.setAttribute('aria-hidden', 'true');
    field.tabIndex = -1;

    var picker = document.createElement('div');
    picker.className = 'vms-status-object-picker';

    var searchRow = document.createElement('div');
    searchRow.className = 'vms-status-object-picker__search';
    picker.appendChild(searchRow);

    var searchInput = document.createElement('input');
    searchInput.type = 'search';
    searchInput.placeholder = t('searchPlaceholder', 'Search pages, posts, products, events...');
    searchRow.appendChild(searchInput);

    var searchBtn = document.createElement('button');
    searchBtn.type = 'button';
    searchBtn.className = 'button';
    searchBtn.textContent = t('search', 'Search');
    searchRow.appendChild(searchBtn);

    var results = document.createElement('div');
    results.className = 'vms-status-object-picker__results';
    picker.appendChild(results);

    var selected = document.createElement('div');
    selected.className = 'vms-status-object-picker__selected';
    picker.appendChild(selected);

    var manualRow = document.createElement('div');
    manualRow.className = 'vms-status-object-picker__manual';
    picker.appendChild(manualRow);

    var manualInput = document.createElement('input');
    manualInput.type = 'number';
    manualInput.min = '1';
    manualInput.step = '1';
    manualInput.inputMode = 'numeric';
    manualInput.placeholder = t('manualIdPlaceholder', 'Enter an ID');
    manualRow.appendChild(manualInput);

    var manualAddBtn = document.createElement('button');
    manualAddBtn.type = 'button';
    manualAddBtn.className = 'button';
    manualAddBtn.textContent = t('addId', 'Add ID');
    manualRow.appendChild(manualAddBtn);

    var hint = document.createElement('p');
    hint.className = 'description';
    hint.textContent = t('searchHint', 'Type at least 2 characters to search.');
    picker.appendChild(hint);

    field.parentNode.insertBefore(picker, field.nextSibling);

    function syncField() {
      selectedIds = compactListValues(selectedIds, 'int');
      field.value = selectedIds.join('\n');
    }

    function addId(rawId, label) {
      var cleanId = sanitizeListValue(rawId, 'int');
      if (!cleanId) {
        return;
      }

      if (selectedIds.indexOf(cleanId) === -1) {
        selectedIds.push(cleanId);
      }
      if (label) {
        labelById[cleanId] = String(label);
      } else if (!labelById[cleanId]) {
        labelById[cleanId] = 'ID #' + cleanId;
      }

      syncField();
      renderSelected();
    }

    function renderSelected() {
      selected.innerHTML = '';
      if (selectedIds.length === 0) {
        var empty = document.createElement('p');
        empty.className = 'description';
        empty.textContent = '-';
        selected.appendChild(empty);
        return;
      }

      for (var index = 0; index < selectedIds.length; index += 1) {
        (function (idx) {
          var id = selectedIds[idx];
          var chip = document.createElement('div');
          chip.className = 'vms-status-object-picker__chip';

          var label = document.createElement('span');
          label.className = 'vms-status-object-picker__chip-label';
          label.textContent = labelById[id] || ('ID #' + id);
          chip.appendChild(label);

          var code = document.createElement('code');
          code.textContent = '#' + id;
          chip.appendChild(code);

          var removeBtn = makeIconButton(t('remove', 'Remove'));
          removeBtn.addEventListener('click', function () {
            selectedIds.splice(idx, 1);
            syncField();
            renderSelected();
          });
          chip.appendChild(removeBtn);

          selected.appendChild(chip);
        })(index);
      }
    }

    function setResultHint(text) {
      results.innerHTML = '';
      var hintLine = document.createElement('p');
      hintLine.className = 'description';
      hintLine.textContent = text;
      results.appendChild(hintLine);
    }

    function renderSearchResults(items) {
      results.innerHTML = '';
      if (!items || items.length === 0) {
        setResultHint(t('searchNoMatches', 'No matches found.'));
        return;
      }

      for (var i = 0; i < items.length; i += 1) {
        (function (item) {
          var itemId = sanitizeListValue(item && item.id ? item.id : '', 'int');
          if (!itemId) {
            return;
          }

          var button = document.createElement('button');
          button.type = 'button';
          button.className = 'vms-status-object-picker__result';

          var title = String(item.title || ('ID #' + itemId));
          var typeLabel = String(item.post_type_label || item.post_type || '');
          var status = String(item.status || '');
          var suffix = '';
          if (typeLabel || status) {
            suffix = ' (' + [typeLabel, status].filter(Boolean).join(' | ') + ')';
          }
          button.textContent = title + ' #' + itemId + suffix;
          button.addEventListener('click', function () {
            addId(itemId, title);
          });

          results.appendChild(button);
        })(items[i]);
      }
    }

    function runSearch() {
      var query = String(searchInput.value || '').trim();
      if (query.length < minChars) {
        setResultHint(t('searchHint', 'Type at least 2 characters to search.'));
        return;
      }

      if (!window.fetch || !config.ajaxUrl) {
        setResultHint(t('searchUnavailable', 'Search is unavailable; add IDs manually.'));
        return;
      }

      setResultHint(t('search', 'Search') + '...');
      fetchObjectSearchResults(query)
        .then(function (items) {
          renderSearchResults(items);
        })
        .catch(function () {
          setResultHint(t('searchFailed', 'Search failed. Try again.'));
        });
    }

    searchBtn.addEventListener('click', runSearch);
    searchInput.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        runSearch();
      }
    });

    var searchTimer = 0;
    searchInput.addEventListener('input', function () {
      if (searchTimer) {
        clearTimeout(searchTimer);
      }
      searchTimer = window.setTimeout(runSearch, 250);
    });

    function addManualId() {
      var candidate = String(manualInput.value || '');
      if (!candidate) {
        return;
      }
      addId(candidate, 'ID #' + candidate);
      manualInput.value = '';
      manualInput.focus();
    }

    manualAddBtn.addEventListener('click', addManualId);
    manualInput.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        addManualId();
      }
    });

    syncField();
    renderSelected();
    setResultHint(t('searchHint', 'Type at least 2 characters to search.'));

    return syncField;
  }

  function enhanceListSources() {
    var fields = root.querySelectorAll('textarea.vms-status-list-source');
    for (var i = 0; i < fields.length; i += 1) {
      var field = fields[i];
      if (field.getAttribute('data-list-enhanced') === '1') {
        continue;
      }
      field.setAttribute('data-list-enhanced', '1');
      var mode = String(field.getAttribute('data-list-ui') || 'rows');
      var syncer = mode === 'object-picker' ? enhanceObjectPicker(field) : enhanceRowList(field);
      if (typeof syncer === 'function') {
        listSyncers.push(syncer);
      }
    }
  }

  function hasIntersection(a, b) {
    for (var i = 0; i < a.length; i += 1) {
      if (b.indexOf(a[i]) >= 0) {
        return true;
      }
    }
    return false;
  }

  function setAudienceSummary() {
    var parts = [];
    var scope = formValue('scope') || 'front';
    parts.push(scope);

    var pageTypes = checkedValues('include_page_types');
    if (pageTypes.length > 0) {
      parts.push(mapLabels(pageTypes, config.pageTypeLabels).join(', '));
    } else {
      parts.push('all pages');
    }

    var deviceMode = formValue('device_mode') || 'any';
    parts.push(labelFor(config.deviceLabels, deviceMode));

    var os = checkedValues('os_include');
    if (os.length > 0) parts.push(mapLabels(os, config.osLabels).join(', '));

    var browsers = checkedValues('browser_include');
    if (browsers.length > 0) parts.push(mapLabels(browsers, config.browserLabels).join(', '));

    var userMode = formValue('user_mode') || 'everyone';
    parts.push(userMode);

    var target = document.getElementById('vms-status-audience-summary');
    if (target) {
      target.textContent = parts.join(' • ');
    }
  }

  function setPreview() {
    var previewWrap = document.getElementById('vms-status-preview');
    var preview = previewWrap ? previewWrap.querySelector('.vms-notice--preview') : null;
    if (!preview) return;

    var intensity = Number(formValue('intensity') || '2');
    preview.className = preview.className
      .replace(/vms-notice--intensity-\d+/g, '')
      .trim() + ' vms-notice--intensity-' + intensity;

    var headline = root.querySelector('.vms-notice__headline');
    if (headline) {
      var text = formValue('headline');
      headline.textContent = text || 'Preview headline';
    }

    var body = root.querySelector('.vms-notice__body');
    if (body) {
      var html = formValue('body_html');
      body.textContent = html ? html.replace(/<[^>]+>/g, ' ') : 'Preview body text.';
    }
  }

  function runSimulator() {
    var resultEl = document.getElementById('vms-status-sim-result');
    if (!resultEl) return;

    var reasons = [];

    var pageMode = formValue('pages_mode');
    if (pageMode === 'include') {
      var hasIncludePageType = checkedValues('include_page_types').length > 0;
      var hasIncludeIds = hasTextAreaLines('include_object_ids_raw');
      var hasUrlContains = hasTextAreaLines('url_contains_raw');
      if (!hasIncludePageType && !hasIncludeIds && !hasUrlContains) {
        reasons.push('include mode has no include rules');
      }
    }

    var simDevice = String((document.getElementById('vms-status-sim-device') || {}).value || 'mobile');
    var simBrowser = String((document.getElementById('vms-status-sim-browser') || {}).value || 'safari_ios');
    var simOs = String((document.getElementById('vms-status-sim-os') || {}).value || 'ios');
    var simLoggedIn = String((document.getElementById('vms-status-sim-logged') || {}).value || '1') === '1';
    var simPageType = String((document.getElementById('vms-status-sim-page') || {}).value || 'event');
    var simRoles = checkedSelectorValues('.vms-status-sim-role:checked');

    var targetDevice = formValue('device_mode') || 'any';
    if (targetDevice !== 'any' && targetDevice !== simDevice) {
      reasons.push('device mismatch');
    }

    var osInclude = checkedValues('os_include');
    if (osInclude.length > 0 && osInclude.indexOf(simOs) < 0) {
      reasons.push('os mismatch');
    }

    var browserInclude = checkedValues('browser_include');
    if (browserInclude.length > 0 && browserInclude.indexOf(simBrowser) < 0) {
      reasons.push('browser mismatch');
    }

    var includePageTypes = checkedValues('include_page_types');
    if (pageMode === 'include' && includePageTypes.length > 0 && includePageTypes.indexOf(simPageType) < 0) {
      reasons.push('page type mismatch');
    }

    var userMode = formValue('user_mode') || 'everyone';
    if (userMode === 'logged_in' && !simLoggedIn) {
      reasons.push('logged_in required');
    }
    if (userMode === 'logged_out' && simLoggedIn) {
      reasons.push('logged_out required');
    }
    if ((userMode === 'roles_include' || userMode === 'roles_exclude') && !simLoggedIn) {
      reasons.push('role targeting requires logged_in');
    }

    if (userMode === 'roles_include') {
      var rolesInclude = checkedValues('roles_include');
      if (rolesInclude.length > 0 && !hasIntersection(rolesInclude, simRoles)) {
        reasons.push('roles include mismatch');
      }
    }

    if (userMode === 'roles_exclude') {
      var rolesExclude = checkedValues('roles_exclude');
      if (rolesExclude.length > 0 && hasIntersection(rolesExclude, simRoles)) {
        reasons.push('roles exclude mismatch');
      }
    }

    if (reasons.length === 0) {
      resultEl.className = 'vms-status-sim-result is-pass';
      resultEl.textContent = t('pass', 'PASS') + ' - ' + t('simPassMessage', 'This simulated context matches current targeting.');
      return;
    }

    resultEl.className = 'vms-status-sim-result is-fail';
    resultEl.textContent = t('fail', 'FAIL') + ' - ' + reasons.join('; ');
  }

  enhanceListSources();

  root.addEventListener('input', function () {
    setAudienceSummary();
    setPreview();
  });
  root.addEventListener('change', function () {
    setAudienceSummary();
    setPreview();
  });

  var runBtn = document.getElementById('vms-status-run-sim');
  if (runBtn) {
    runBtn.addEventListener('click', runSimulator);
  }

  root.addEventListener('submit', function () {
    for (var i = 0; i < listSyncers.length; i += 1) {
      listSyncers[i]();
    }
  });

  setAudienceSummary();
  setPreview();
})();
