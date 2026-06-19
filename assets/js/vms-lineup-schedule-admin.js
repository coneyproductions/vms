(function () {
  'use strict';

  function parseMinutes(value) {
    if (!value || !/^\d{2}:\d{2}$/.test(String(value))) return null;
    var parts = String(value).split(':');
    var hours = parseInt(parts[0], 10);
    var minutes = parseInt(parts[1], 10);
    if (isNaN(hours) || isNaN(minutes)) return null;
    return (hours * 60) + minutes;
  }

  function formatDuration(minutes) {
    if (minutes === null || minutes < 0) return '';
    var hours = Math.floor(minutes / 60);
    var mins = minutes % 60;
    var parts = [];
    if (hours > 0) {
      parts.push(hours + ' hr' + (hours === 1 ? '' : 's'));
    }
    if (mins > 0 || parts.length === 0) {
      parts.push(mins + ' min' + (mins === 1 ? '' : 's'));
    }
    return parts.join(' ');
  }

  function formatCurrency(value) {
    if (value === '' || value === null || typeof value === 'undefined') {
      return 'No fee set';
    }
    var cleaned = String(value).replace(/[^0-9.\-]/g, '');
    if (cleaned === '' || isNaN(Number(cleaned))) {
      return 'No fee set';
    }
    return '$' + Number(cleaned).toFixed(2);
  }

  function getSelectedOptionText(selectEl) {
    if (!selectEl || !selectEl.options || selectEl.selectedIndex < 0 || !selectEl.value) return '';
    var option = selectEl.options[selectEl.selectedIndex];
    return option ? String(option.textContent || '').trim() : '';
  }

  function getVendorTitle(selectEl) {
    if (!selectEl || !selectEl.options || selectEl.selectedIndex < 0 || !selectEl.value) return '';
    var option = selectEl.options[selectEl.selectedIndex];
    if (!option) return '';
    var explicit = option.getAttribute('data-vendor-title');
    if (explicit) return String(explicit).trim();
    return String(option.textContent || '').replace(/\s*\[[^\]]*\]/g, '').trim();
  }

  function getSupportingDefaultFee(selectEl) {
    if (!selectEl || !selectEl.options || selectEl.selectedIndex < 0 || !selectEl.value) return '';
    var option = selectEl.options[selectEl.selectedIndex];
    if (!option) return '';
    var raw = option.getAttribute('data-lineup-support-default-fee');
    if (!raw) return '';
    var cleaned = String(raw).replace(/[^0-9.\-]/g, '');
    if (cleaned === '' || isNaN(Number(cleaned))) return '';
    return Number(cleaned).toFixed(2);
  }

  function createNode(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (typeof text !== 'undefined') node.textContent = text;
    return node;
  }

  function initLineupScheduleAdmin() {
    var section = document.getElementById('vms-lineup-schedule-section');
    if (!section) return;

    var supportingRows = document.getElementById('vms-lineup-supporting-rows');
    var template = document.getElementById('vms-lineup-supporting-template');
    var supportingVendorOptionsTemplate = document.getElementById('vms-lineup-supporting-vendor-options-template');
    var supportingVendorOptionsHtml = supportingVendorOptionsTemplate ? String(supportingVendorOptionsTemplate.innerHTML || '') : '';
    var supportingVendorOptionsUrl = section.getAttribute('data-lineup-vendor-options-url') || '';
    var supportingVendorOptionsNonce = section.getAttribute('data-lineup-vendor-options-nonce') || '';
    var supportingVendorOptionsPostId = section.getAttribute('data-lineup-post-id') || '';
    var vendorOptionsPromise = null;
    var addButton = document.getElementById('vms-lineup-add-supporting');
    var expandAllButton = document.getElementById('vms-lineup-expand-all');
    var collapseAllButton = document.getElementById('vms-lineup-collapse-all');
	    var primaryDetails = section.querySelector('[data-lineup-primary]');
	    var primaryVendorSelect = document.getElementById('vms_band_vendor_id');
	    var primaryVendorOptionsHtml = primaryVendorSelect ? String(primaryVendorSelect.innerHTML || '') : '';
	    var primaryVendorHidden = document.getElementById('vms-lineup-primary-vendor-id');
	    var clearPrimaryVendorInput = document.getElementById('vms-clear-primary-vendor-intent');
	    var clearLineupPrimaryVendorInput = document.getElementById('vms-clear-lineup-primary-vendor-intent');
	    var clearPrimaryVendorButton = document.getElementById('vms-clear-primary-vendor-button');
	    var primarySortOrder = section.querySelector('[data-lineup-primary-sort-order]');
    var primaryStartSelect = section.querySelector('[data-lineup-primary-start]');
    var primaryEndSelect = section.querySelector('[data-lineup-primary-end]');
    var eventStartSelect = document.getElementById('vms_start_time');
    var eventEndSelect = document.getElementById('vms_end_time');
    var timelineList = document.getElementById('vms-lineup-timeline-list');
    var healthList = document.getElementById('vms-lineup-health-list');
    var summaryPrimary = document.getElementById('vms-lineup-summary-primary');
    var summarySupporting = document.getElementById('vms-lineup-summary-supporting');
    var summaryEarliest = document.getElementById('vms-lineup-summary-earliest');
    var summaryPrimaryStart = document.getElementById('vms-lineup-summary-primary-start');
    var summaryRuntime = document.getElementById('vms-lineup-summary-runtime');
    var summaryWarnings = document.getElementById('vms-lineup-summary-warnings');
    var primarySummaryTitle = document.getElementById('vms-lineup-primary-summary-title');
    var primarySummaryTime = document.getElementById('vms-lineup-primary-summary-time');
    var primarySummaryDuration = document.getElementById('vms-lineup-primary-summary-duration');
    var primarySummaryDowntime = document.getElementById('vms-lineup-primary-summary-downtime');
    var primarySummaryPay = document.getElementById('vms-lineup-primary-summary-pay');
    var primarySummaryWarning = document.getElementById('vms-lineup-primary-summary-warning');
    var primaryDerivedDuration = document.getElementById('vms-lineup-primary-derived-duration');
    var primaryDerivedDowntime = document.getElementById('vms-lineup-primary-derived-downtime');
    var primaryDerivedWarning = document.getElementById('vms-lineup-primary-derived-warning');
    var nextIndex = supportingRows ? supportingRows.querySelectorAll('[data-lineup-row]').length : 0;
    var draggingRow = null;
    var postIdInput = document.getElementById('post_ID');
    var lineupStorageScope = section.getAttribute('data-lineup-storage-scope') || (postIdInput ? String(postIdInput.value || '') : 'new');
    var lineupStoragePrefix = 'vms.lineup.openState.' + lineupStorageScope + '.';
    var hasManySupportingRows = rowElements().length >= 4;

    function canUseLocalStorage() {
      try {
        if (!window.localStorage) return false;
        var testKey = 'vms.lineup.storageTest';
        window.localStorage.setItem(testKey, '1');
        window.localStorage.removeItem(testKey);
        return true;
      } catch (error) {
        return false;
      }
    }

    var lineupStorageAvailable = canUseLocalStorage();

    function storageGet(key) {
      if (!lineupStorageAvailable || !key) return null;
      try {
        return window.localStorage.getItem(lineupStoragePrefix + key);
      } catch (error) {
        return null;
      }
    }

    function storageSet(key, value) {
      if (!lineupStorageAvailable || !key) return;
      try {
        window.localStorage.setItem(lineupStoragePrefix + key, value ? '1' : '0');
      } catch (error) {
        // Storage can be blocked in private browsing or locked-down browsers. Ignore safely.
      }
    }

    function getLineupRowStorageKey(row) {
      if (!row) return '';
      if (row.hasAttribute('data-lineup-primary')) return 'primary';

      var rowIdInput = row.querySelector('[data-lineup-row-id]');
      var rowId = rowIdInput ? String(rowIdInput.value || '').trim() : '';
      if (rowId !== '') return 'row:' + rowId;

      var tempKey = row.getAttribute('data-lineup-temp-key');
      if (!tempKey) {
        tempKey = 'temp:' + String(Date.now()) + ':' + String(Math.floor(Math.random() * 100000));
        row.setAttribute('data-lineup-temp-key', tempKey);
      }
      return tempKey;
    }

    function rememberLineupRowState(row) {
      if (!row || String(row.tagName || '').toLowerCase() !== 'details') return;
      storageSet(getLineupRowStorageKey(row), !!row.open);
    }

    function applyRememberedLineupRowState(row) {
      if (!row || String(row.tagName || '').toLowerCase() !== 'details') return;
      var key = getLineupRowStorageKey(row);
      var saved = storageGet(key);
      if (saved === '1' || saved === '0') {
        row.open = saved === '1';
        return;
      }

      // First-time quality-of-life default: when an Event Plan has a long lineup,
      // start supporting cards collapsed so the editor is not a wall of open fields.
      if (row.hasAttribute('data-lineup-row') && hasManySupportingRows) {
        row.open = false;
      }
    }

    function bindLineupOpenState(row) {
      if (!row || row.getAttribute('data-lineup-open-state-bound') === '1') return;
      row.setAttribute('data-lineup-open-state-bound', '1');
      applyRememberedLineupRowState(row);
      row.addEventListener('toggle', function () {
        rememberLineupRowState(row);
      });
    }

    function getPrimaryName() {
      var vendorName = getVendorTitle(primaryVendorSelect);
      return vendorName || 'Unassigned primary vendor';
    }

    function rowElements() {
      return supportingRows ? Array.prototype.slice.call(supportingRows.querySelectorAll('[data-lineup-row]')) : [];
    }

    function rowInput(row, selector) {
      return row ? row.querySelector(selector) : null;
    }

    function loadVendorOptions() {
      if (primaryVendorSelect && primaryVendorSelect.getAttribute('data-lineup-vendor-options-hydrated') === '1' && supportingVendorOptionsHtml) {
        return Promise.resolve({
          primaryHtml: primaryVendorOptionsHtml,
          supportingHtml: supportingVendorOptionsHtml
        });
      }
      if (vendorOptionsPromise) {
        return vendorOptionsPromise;
      }
      if (!supportingVendorOptionsUrl || !supportingVendorOptionsNonce || !supportingVendorOptionsPostId || typeof window.fetch !== 'function' || typeof window.URLSearchParams !== 'function') {
        return Promise.resolve({
          primaryHtml: primaryVendorOptionsHtml,
          supportingHtml: supportingVendorOptionsHtml
        });
      }

      var params = new window.URLSearchParams();
      params.set('action', 'vms_load_event_plan_supporting_vendor_options');
      params.set('post_id', String(supportingVendorOptionsPostId));
      params.set('nonce', String(supportingVendorOptionsNonce));

      vendorOptionsPromise = window.fetch(supportingVendorOptionsUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: params.toString()
      }).then(function (response) {
        return response.json();
      }).then(function (payload) {
        var primaryHtml = payload && payload.success && payload.data && payload.data.primary_html
          ? String(payload.data.primary_html)
          : '';
        var supportingHtml = payload && payload.success && payload.data && payload.data.supporting_html
          ? String(payload.data.supporting_html)
          : '';
        if (primaryHtml) {
          primaryVendorOptionsHtml = primaryHtml;
        }
        if (supportingHtml) {
          supportingVendorOptionsHtml = supportingHtml;
          if (supportingVendorOptionsTemplate) {
            supportingVendorOptionsTemplate.innerHTML = supportingHtml;
          }
        }
        return {
          primaryHtml: primaryVendorOptionsHtml,
          supportingHtml: supportingVendorOptionsHtml
        };
      }).catch(function () {
        return {
          primaryHtml: primaryVendorOptionsHtml,
          supportingHtml: supportingVendorOptionsHtml
        };
      }).then(function (result) {
        if (!result.primaryHtml && !result.supportingHtml) {
          vendorOptionsPromise = null;
        }
        return result;
      });

      return vendorOptionsPromise;
    }

    function hydratePrimaryVendorSelect(selectEl) {
      if (!selectEl) return Promise.resolve(false);
      if (selectEl.getAttribute('data-lineup-vendor-options-hydrated') === '1') return Promise.resolve(true);

      return loadVendorOptions().then(function (payload) {
        var optionsHtml = payload && payload.primaryHtml ? String(payload.primaryHtml) : '';
        if (!optionsHtml) {
          return false;
        }

        var selectedValue = String(selectEl.value || '');
        var selectedOption = (selectEl.options && selectEl.selectedIndex >= 0) ? selectEl.options[selectEl.selectedIndex] : null;
        var fallbackLabel = selectedOption ? String(selectedOption.textContent || '').trim() : '';
        var fallbackVendorTitle = selectedOption ? String(selectedOption.getAttribute('data-vendor-title') || fallbackLabel).trim() : '';
        selectEl.innerHTML = optionsHtml;
        if (selectedValue) {
          selectEl.value = selectedValue;
          if (String(selectEl.value || '') !== selectedValue) {
            var fallbackOption = document.createElement('option');
            fallbackOption.value = selectedValue;
            fallbackOption.selected = true;
            fallbackOption.textContent = fallbackLabel || 'Assigned primary vendor';
            fallbackOption.setAttribute('data-vendor-title', fallbackVendorTitle || fallbackOption.textContent);
            fallbackOption.setAttribute('data-tax-ok', '0');
            fallbackOption.setAttribute('data-tax-bypass-active', '0');
            fallbackOption.setAttribute('data-tax-bypass-until', '');
            fallbackOption.setAttribute('data-tax-bypass-reason', '');
            fallbackOption.setAttribute('data-tax-missing', '');
            selectEl.appendChild(fallbackOption);
            selectEl.value = selectedValue;
          }
        }
        selectEl.setAttribute('data-lineup-vendor-options-hydrated', '1');
        return true;
      });
    }

    function hydrateSupportingVendorSelect(selectEl) {
      if (!selectEl) return Promise.resolve(false);
      if (selectEl.getAttribute('data-lineup-vendor-options-hydrated') === '1') return Promise.resolve(true);

      return loadVendorOptions().then(function (payload) {
        var optionsHtml = payload && payload.supportingHtml ? String(payload.supportingHtml) : '';
        if (!optionsHtml) {
          return false;
        }

        var selectedValue = String(selectEl.value || selectEl.getAttribute('data-lineup-selected-vendor-id') || '');
        var selectedOption = (selectEl.options && selectEl.selectedIndex >= 0) ? selectEl.options[selectEl.selectedIndex] : null;
        var fallbackLabel = selectedOption ? String(selectedOption.textContent || '').trim() : '';
        var fallbackVendorTitle = selectedOption ? String(selectedOption.getAttribute('data-vendor-title') || fallbackLabel).trim() : '';
        var fallbackDefaultFee = selectedOption ? String(selectedOption.getAttribute('data-lineup-support-default-fee') || '').trim() : '';

        selectEl.innerHTML = optionsHtml;

        if (selectedValue) {
          selectEl.value = selectedValue;
          if (String(selectEl.value || '') !== selectedValue) {
            var fallbackOption = document.createElement('option');
            fallbackOption.value = selectedValue;
            fallbackOption.selected = true;
            fallbackOption.textContent = fallbackLabel || 'Assigned vendor';
            if (fallbackVendorTitle) {
              fallbackOption.setAttribute('data-vendor-title', fallbackVendorTitle);
            }
            if (fallbackDefaultFee) {
              fallbackOption.setAttribute('data-lineup-support-default-fee', fallbackDefaultFee);
            }
            selectEl.appendChild(fallbackOption);
            selectEl.value = selectedValue;
          }
        }

        selectEl.setAttribute('data-lineup-vendor-options-hydrated', '1');
        return true;
      });
    }

    function setNodeText(node, text) {
      if (!node) return;
      node.textContent = text || '';
    }

    function setWarningState(node, count) {
      if (!node) return;
      var warningCount = Number(count) || 0;
      node.textContent = String(warningCount);
      node.classList.toggle('is-clear', warningCount <= 0);
    }

	    function syncPrimaryVendor() {
	      if (primaryVendorHidden && primaryVendorSelect) {
	        primaryVendorHidden.value = String(primaryVendorSelect.value || '');
	      }
	    }

	    function setPrimaryVendorClearIntent(shouldClear) {
	      var value = shouldClear ? '1' : '0';
	      if (clearPrimaryVendorInput) {
	        clearPrimaryVendorInput.value = value;
	      }
	      if (clearLineupPrimaryVendorInput) {
	        clearLineupPrimaryVendorInput.value = value;
	      }
	    }

    function isFeeAutoManaged(feeInput) {
      return !!feeInput && feeInput.getAttribute('data-lineup-fee-auto') === '1';
    }

    function setFeeAutoManaged(feeInput, isAuto) {
      if (!feeInput) return;
      feeInput.setAttribute('data-lineup-fee-auto', isAuto ? '1' : '0');
    }

    function applySupportingCompDefault(row, force) {
      if (!row) return;
      var vendorSelect = row.querySelector('[data-lineup-vendor-select]');
      var feeInput = row.querySelector('[data-lineup-fee]');
      if (!vendorSelect || !feeInput) return;

      var defaultFee = getSupportingDefaultFee(vendorSelect);
      var currentValue = String(feeInput.value || '').trim();
      var autoManaged = isFeeAutoManaged(feeInput);

      if (defaultFee === '') {
        if (autoManaged) {
          feeInput.value = '';
          setFeeAutoManaged(feeInput, false);
        }
        return;
      }

      if (force || currentValue === '' || autoManaged) {
        feeInput.value = defaultFee;
        setFeeAutoManaged(feeInput, true);
      }
    }

    function updateSortOrders() {
      var rows = rowElements();
      rows.forEach(function (row, index) {
        var sortInput = row.querySelector('[data-lineup-sort-order]');
        if (sortInput) sortInput.value = String(index);
      });
      if (primarySortOrder) {
        primarySortOrder.value = String(rows.length);
      }
    }

    function getRowData(row, role, index) {
      var vendorSelect = row ? row.querySelector('[data-lineup-vendor-select]') : primaryVendorSelect;
      var startSelect = row ? row.querySelector('[data-lineup-start]') : primaryStartSelect;
      var endSelect = row ? row.querySelector('[data-lineup-end]') : primaryEndSelect;
      var feeInput = row ? row.querySelector('[data-lineup-fee]') : null;
      var rowIdInput = row ? row.querySelector('[data-lineup-row-id]') : null;
      var explicitNameInput = row ? row.querySelector('input[name*="[public_name_override]"]') : section.querySelector('input[name="vms_lineup_entries[primary][public_name_override]"]');
      var displayName = explicitNameInput && explicitNameInput.value.trim() ? explicitNameInput.value.trim() : getVendorTitle(vendorSelect);
      var startValue = startSelect ? String(startSelect.value || '') : '';
      var endValue = endSelect ? String(endSelect.value || '') : '';
      var startLabel = startSelect ? getSelectedOptionText(startSelect) : '';
      var endLabel = endSelect ? getSelectedOptionText(endSelect) : '';
      var startMinutes = parseMinutes(startValue);
      var endMinutes = parseMinutes(endValue);
      var durationMinutes = (startMinutes !== null && endMinutes !== null) ? (endMinutes - startMinutes) : null;
      return {
        row: row,
        rowId: rowIdInput ? String(rowIdInput.value || '') : 'primary',
        role: role,
        index: index,
        vendorId: vendorSelect ? String(vendorSelect.value || '') : '',
        vendorName: getVendorTitle(vendorSelect),
        displayName: displayName || (role === 'primary' ? 'Unassigned primary vendor' : 'Unassigned supporting vendor'),
        startValue: startValue,
        endValue: endValue,
        startLabel: startLabel,
        endLabel: endLabel,
        startMinutes: startMinutes,
        endMinutes: endMinutes,
        durationMinutes: durationMinutes,
        feeValue: feeInput ? String(feeInput.value || '') : '',
        feeLabel: role === 'primary' ? (primarySummaryPay ? String(primarySummaryPay.textContent || '') : '') : formatCurrency(feeInput ? feeInput.value : ''),
        warningCount: 0,
        downtimeBeforeMinutes: null,
        downtimeBeforeLabel: ''
      };
    }

    function collectEntries() {
      var rows = rowElements();
      var entries = rows.map(function (row, index) {
        return getRowData(row, 'supporting', index);
      });
      entries.push(getRowData(null, 'primary', rows.length));
      return entries;
    }

    function refreshSupportingRowSummary(entry) {
      if (!entry.row) return;
      var titleEl = rowInput(entry.row, '[data-lineup-summary-title]');
      var timeEl = rowInput(entry.row, '[data-lineup-summary-time]');
      var durationEl = rowInput(entry.row, '[data-lineup-summary-duration]');
      var downtimeEl = rowInput(entry.row, '[data-lineup-summary-downtime]');
      var feeEl = rowInput(entry.row, '[data-lineup-summary-fee]');
      var warningEl = rowInput(entry.row, '[data-lineup-summary-warning]');
      setNodeText(titleEl, entry.displayName);
      setNodeText(timeEl, [entry.startLabel, entry.endLabel].filter(Boolean).join(' – '));
      setNodeText(durationEl, entry.durationMinutes !== null && entry.durationMinutes >= 0 ? formatDuration(entry.durationMinutes) : '');
      setNodeText(downtimeEl, entry.downtimeBeforeLabel);
      setNodeText(feeEl, formatCurrency(entry.feeValue));
      setWarningState(warningEl, entry.warningCount);
    }

    function refreshDerivedStatus(entry) {
      var durationText = entry.durationMinutes !== null && entry.durationMinutes >= 0 ? formatDuration(entry.durationMinutes) : '';
      if (entry.role === 'primary') {
        setNodeText(primaryDerivedDuration, durationText);
        setNodeText(primaryDerivedDowntime, entry.downtimeBeforeLabel);
        setWarningState(primaryDerivedWarning, entry.warningCount);
        return;
      }

      setNodeText(rowInput(entry.row, '[data-lineup-derived-duration]'), durationText);
      setNodeText(rowInput(entry.row, '[data-lineup-derived-downtime]'), entry.downtimeBeforeLabel);
      setWarningState(rowInput(entry.row, '[data-lineup-derived-warning]'), entry.warningCount);
    }

    function refreshPrimarySummary(entry) {
      setNodeText(summaryPrimary, entry.displayName);
      setNodeText(primarySummaryTitle, entry.displayName);
      var timeText = [entry.startLabel, entry.endLabel].filter(Boolean).join(' – ');
      setNodeText(summaryPrimaryStart, entry.startLabel || '');
      setNodeText(primarySummaryTime, timeText);
      var durationText = entry.durationMinutes !== null && entry.durationMinutes >= 0 ? formatDuration(entry.durationMinutes) : '';
      setNodeText(primarySummaryDuration, durationText);
      setNodeText(primarySummaryDowntime, entry.downtimeBeforeLabel);
      setWarningState(primarySummaryWarning, entry.warningCount);
    }

    function buildWarnings(entries) {
      var warnings = [];
      var vendorSeen = {};
      var eventStartMinutes = eventStartSelect ? parseMinutes(eventStartSelect.value || '') : null;
      var eventEndMinutes = eventEndSelect ? parseMinutes(eventEndSelect.value || '') : null;
      var previous = null;
      var primaryEntry = entries.length ? entries[entries.length - 1] : null;

      entries.forEach(function (entry) {
        entry.warningCount = 0;
      });

      entries.forEach(function (entry) {
        if (entry.vendorId) {
          if (vendorSeen[entry.vendorId]) {
            warnings.push('Duplicate vendor assigned in lineup: ' + entry.displayName + '.');
            entry.warningCount += 1;
            vendorSeen[entry.vendorId].warningCount += 1;
          } else {
            vendorSeen[entry.vendorId] = entry;
          }
        }

        if ((entry.startValue && !entry.endValue) || (!entry.startValue && entry.endValue)) {
          warnings.push(entry.displayName + ' is missing a start or end time.');
          entry.warningCount += 1;
        }

        if (entry.startMinutes !== null && entry.endMinutes !== null) {
          if (entry.endMinutes <= entry.startMinutes) {
            warnings.push(entry.displayName + ' ends before it starts.');
            entry.warningCount += 1;
          } else {
            if (entry.durationMinutes < 15) {
              warnings.push(entry.displayName + ' has a very short set duration.');
              entry.warningCount += 1;
            }
            if (entry.durationMinutes > 240) {
              warnings.push(entry.displayName + ' has a very long set duration.');
              entry.warningCount += 1;
            }
          }
        }

        if (eventStartMinutes !== null && entry.startMinutes !== null && entry.startMinutes < eventStartMinutes) {
          warnings.push(entry.displayName + ' starts before the event window.');
          entry.warningCount += 1;
        }
        if (eventEndMinutes !== null && entry.endMinutes !== null && entry.endMinutes > eventEndMinutes) {
          warnings.push(entry.displayName + ' ends after the event window.');
          entry.warningCount += 1;
        }

        if (previous && previous.endMinutes !== null && entry.startMinutes !== null) {
          var delta = entry.startMinutes - previous.endMinutes;
          entry.downtimeBeforeMinutes = delta;
          entry.downtimeBeforeLabel = delta > 0 ? formatDuration(delta) : '';
          if (delta < 0) {
            warnings.push(previous.displayName + ' overlaps with ' + entry.displayName + '.');
            previous.warningCount += 1;
            entry.warningCount += 1;
          } else if (delta >= 45) {
            warnings.push('Large gap before ' + entry.displayName + ': ' + formatDuration(delta) + '.');
            entry.warningCount += 1;
          }
        } else {
          entry.downtimeBeforeMinutes = null;
          entry.downtimeBeforeLabel = '';
        }

        previous = entry;
      });

      if (primaryEntry) {
        var laterSupporting = entries.slice(0, -1).some(function (entry) {
          return entry.startMinutes !== null && primaryEntry.startMinutes !== null && entry.startMinutes > primaryEntry.startMinutes;
        });
        if (laterSupporting) {
          warnings.push('Primary vendor is not last by time.');
          primaryEntry.warningCount += 1;
        }
      }

      return warnings;
    }

    function refreshTimeline(entries) {
      if (!timelineList) return;
      timelineList.innerHTML = '';
      entries.forEach(function (entry) {
        if (entry.downtimeBeforeLabel) {
          var gap = createNode('div', 'vms-lineup-timeline__gap');
          gap.appendChild(createNode('span', '', 'Changeover / gap'));
          gap.appendChild(createNode('strong', '', entry.downtimeBeforeLabel));
          timelineList.appendChild(gap);
        }

        var entryNode = createNode('div', 'vms-lineup-timeline__entry' + (entry.role === 'primary' ? ' is-primary' : ''));
        entryNode.appendChild(createNode('span', 'vms-lineup-timeline__name', entry.displayName));
        entryNode.appendChild(createNode('span', 'vms-lineup-timeline__time', [entry.startLabel, entry.endLabel].filter(Boolean).join(' – ')));
        entryNode.appendChild(createNode('span', 'vms-lineup-timeline__duration', entry.durationMinutes !== null && entry.durationMinutes >= 0 ? formatDuration(entry.durationMinutes) : ''));
        timelineList.appendChild(entryNode);
      });
    }

    function refreshHealth(warnings) {
      if (!healthList) return;
      healthList.innerHTML = '';
      if (!warnings.length) {
        healthList.appendChild(createNode('li', '', 'No lineup warnings right now.'));
        return;
      }
      warnings.forEach(function (warning) {
        healthList.appendChild(createNode('li', '', warning));
      });
    }

    function refreshSummary(entries, warnings) {
      if (summarySupporting) summarySupporting.textContent = String(Math.max(0, entries.length - 1));
      var startLabels = entries.map(function (entry) {
        return entry.startLabel;
      }).filter(Boolean);
      if (summaryEarliest) {
        var earliestEntry = entries.reduce(function (carry, entry) {
          if (entry.startMinutes === null) return carry;
          if (!carry || carry.startMinutes === null || entry.startMinutes < carry.startMinutes) {
            return entry;
          }
          return carry;
        }, null);
        summaryEarliest.textContent = earliestEntry ? earliestEntry.startLabel : '';
      }
      if (summaryWarnings) summaryWarnings.textContent = String(warnings.length);
      if (summaryRuntime) {
        var runtimeMinutes = entries.reduce(function (carry, entry) {
          return carry + ((entry.durationMinutes !== null && entry.durationMinutes > 0) ? entry.durationMinutes : 0);
        }, 0);
        summaryRuntime.textContent = runtimeMinutes > 0 ? formatDuration(runtimeMinutes) : '';
      }
    }

    function refreshAll() {
      syncPrimaryVendor();
      updateSortOrders();
      var entries = collectEntries();
      var warnings = buildWarnings(entries);
      entries.forEach(function (entry) {
        if (entry.role === 'primary') {
          refreshPrimarySummary(entry);
        } else {
          refreshSupportingRowSummary(entry);
        }
        refreshDerivedStatus(entry);
      });
      refreshTimeline(entries);
      refreshHealth(warnings);
      refreshSummary(entries, warnings);
    }

    function makeTemplateIndex() {
      nextIndex += 1;
      return 'new_' + String(Date.now()) + '_' + String(nextIndex);
    }

    function addSupportingRow() {
      if (!supportingRows || !template) return;
      var html = String(template.innerHTML || '');
      var indexKey = makeTemplateIndex();
      html = html.replace(/__INDEX__/g, indexKey);
      var wrapper = document.createElement('div');
      wrapper.innerHTML = html;
      var row = wrapper.firstElementChild;
      if (!row) return;
      row.setAttribute('data-lineup-temp-key', 'temp:' + indexKey);
      supportingRows.appendChild(row);
      bindRow(row);
      row.open = true;
      rememberLineupRowState(row);
      hydrateSupportingVendorSelect(row.querySelector('[data-lineup-vendor-select]')).then(function () {
        applySupportingCompDefault(row, false);
        refreshAll();
      });
      refreshAll();
    }

    function removeSupportingRow(button) {
      var row = button ? button.closest('[data-lineup-row]') : null;
      if (!row) return;
      row.remove();
      refreshAll();
    }

    function getDragAfterElement(container, y) {
      var draggableElements = Array.prototype.slice.call(container.querySelectorAll('[data-lineup-row]:not(.is-dragging)'));
      return draggableElements.reduce(function (closest, child) {
        var box = child.getBoundingClientRect();
        var offset = y - box.top - (box.height / 2);
        if (offset < 0 && offset > closest.offset) {
          return { offset: offset, element: child };
        }
        return closest;
      }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
    }

    function bindRow(row) {
      if (!row) return;
      bindLineupOpenState(row);
      row.addEventListener('dragstart', function () {
        draggingRow = row;
        row.classList.add('is-dragging');
      });
      row.addEventListener('dragend', function () {
        row.classList.remove('is-dragging');
        draggingRow = null;
        refreshAll();
      });
    }

    if (supportingRows) {
      supportingRows.addEventListener('dragover', function (event) {
        if (!draggingRow) return;
        event.preventDefault();
        var afterElement = getDragAfterElement(supportingRows, event.clientY);
        if (!afterElement) {
          supportingRows.appendChild(draggingRow);
        } else if (afterElement !== draggingRow) {
          supportingRows.insertBefore(draggingRow, afterElement);
        }
      });
    }

    section.addEventListener('click', function (event) {
      var target = event.target;
      if (target && target.classList.contains('vms-lineup-remove')) {
        event.preventDefault();
        removeSupportingRow(target);
      }
    });

    section.addEventListener('focusin', function (event) {
      var target = event.target;
      if (target && target === primaryVendorSelect) {
        hydratePrimaryVendorSelect(target);
        return;
      }
      if (target && target.matches('[data-lineup-vendor-select]')) {
        hydrateSupportingVendorSelect(target);
      }
    });

    section.addEventListener('mousedown', function (event) {
      if (primaryVendorSelect && event.target.closest && event.target.closest('#vms_band_vendor_id')) {
        hydratePrimaryVendorSelect(primaryVendorSelect);
        return;
      }
      var target = event.target.closest ? event.target.closest('[data-lineup-vendor-select]') : null;
      if (target) {
        hydrateSupportingVendorSelect(target);
      }
    });

    section.addEventListener('input', function (event) {
      var target = event.target;
      if (target && target.matches('[data-lineup-fee]')) {
        setFeeAutoManaged(target, false);
      }
      refreshAll();
    });

	    section.addEventListener('change', function (event) {
	      var target = event.target;
	      if (target && target === primaryVendorSelect) {
	        setPrimaryVendorClearIntent(String(primaryVendorSelect.value || '') === '');
	      }
	      if (target && target.matches('[data-lineup-vendor-select]')) {
	        applySupportingCompDefault(target.closest('[data-lineup-row]'), false);
	      }
	      refreshAll();
	    });
	    if (eventStartSelect) eventStartSelect.addEventListener('change', refreshAll);
	    if (eventEndSelect) eventEndSelect.addEventListener('change', refreshAll);
	    if (primaryVendorSelect) primaryVendorSelect.addEventListener('change', refreshAll);

	    if (clearPrimaryVendorButton) {
	      clearPrimaryVendorButton.addEventListener('click', function (event) {
	        event.preventDefault();
	        if (!primaryVendorSelect) {
	          return;
	        }
	        primaryVendorSelect.value = '';
	        setPrimaryVendorClearIntent(true);
	        syncPrimaryVendor();
	        refreshAll();
	      });
	    }

    if (addButton) addButton.addEventListener('click', function (event) {
      event.preventDefault();
      addSupportingRow();
    });

    if (expandAllButton) expandAllButton.addEventListener('click', function (event) {
      event.preventDefault();
      rowElements().forEach(function (row) { row.open = true; rememberLineupRowState(row); });
      if (primaryDetails) { primaryDetails.open = true; rememberLineupRowState(primaryDetails); }
    });

    if (collapseAllButton) collapseAllButton.addEventListener('click', function (event) {
      event.preventDefault();
      rowElements().forEach(function (row) { row.open = false; rememberLineupRowState(row); });
      if (primaryDetails) { primaryDetails.open = false; rememberLineupRowState(primaryDetails); }
    });

    bindLineupOpenState(primaryDetails);

    rowElements().forEach(function (row) {
      bindRow(row);
      applySupportingCompDefault(row, false);
    });
    if (typeof window.requestIdleCallback === 'function') {
      window.requestIdleCallback(function () {
        loadVendorOptions().then(function () {
          if (primaryVendorSelect) {
            hydratePrimaryVendorSelect(primaryVendorSelect);
          }
        });
      });
    } else {
      window.setTimeout(function () {
        loadVendorOptions().then(function () {
          if (primaryVendorSelect) {
            hydratePrimaryVendorSelect(primaryVendorSelect);
          }
        });
      }, 250);
    }
    refreshAll();
  }

  function init() {
    initLineupScheduleAdmin();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
