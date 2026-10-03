document.addEventListener('DOMContentLoaded', () => {
  const search = document.getElementById('vmseb-registry-search');
  const rows = Array.from(document.querySelectorAll('.vmseb-registry-table tbody tr[data-vmseb-row]'));
  const barMenuForm = document.querySelector('[data-vmseb-bar-menu-form]');
  const registryChangesField = barMenuForm?.querySelector('[data-vmseb-registry-changes]') || null;
  const adminConfig = window.vmsebAdmin || {};
  const messages = adminConfig.messages || {};

  const getMessage = (key, fallback) => {
    const value = messages[key];
    return typeof value === 'string' && value.trim() !== '' ? value : fallback;
  };

  if (search) {
    search.addEventListener('input', () => {
      const query = search.value.trim().toLowerCase();
      rows.forEach((row) => {
        const haystack = (row.getAttribute('data-search') || '').toLowerCase();
        row.style.display = query === '' || haystack.includes(query) ? '' : 'none';
      });
    });
  }

  if (!rows.length) {
    if (registryChangesField) {
      registryChangesField.value = '[]';
    }
    return;
  }

  const rowState = new WeakMap();

  const parseInteger = (value) => {
    const parsed = Number.parseInt(String(value), 10);
    return Number.isNaN(parsed) ? 0 : parsed;
  };

  const serializeRow = (row) => ({
    token: row.dataset.token || '',
    product_id: parseInteger(row.dataset.productId || 0),
    variation_id: parseInteger(row.dataset.variationId || 0),
    enabled: row.querySelector('[data-vmseb-field="enabled"]')?.checked ? 1 : 0,
    bucket_eligible: row.querySelector('[data-vmseb-field="bucket_eligible"]')?.checked ? 1 : 0,
    age_gate: row.querySelector('[data-vmseb-field="age_gate"]')?.checked ? 1 : 0,
    sort: parseInteger(row.querySelector('[data-vmseb-field="sort"]')?.value || 0),
    online_cap: Math.max(0, parseInteger(row.querySelector('[data-vmseb-field="online_cap"]')?.value || 0)),
    bar_only_threshold: Math.max(0, parseInteger(row.querySelector('[data-vmseb-field="bar_only_threshold"]')?.value || 0)),
  });

  const statesEqual = (left, right) => JSON.stringify(left) === JSON.stringify(right);

  const setFeedback = (row, message, tone) => {
    const feedback = row.querySelector('[data-vmseb-row-feedback]');
    if (!feedback) {
      return;
    }
    feedback.textContent = message || '';
    feedback.dataset.state = tone || '';
  };

  const syncRowState = (row) => {
    const state = rowState.get(row);
    if (!state) {
      return;
    }

    const current = serializeRow(row);
    const dirty = !statesEqual(current, state.initial);
    const saveButton = row.querySelector('[data-vmseb-row-save]');
    const feedback = row.querySelector('[data-vmseb-row-feedback]');

    row.classList.toggle('is-dirty', dirty);
    row.classList.toggle('is-saving', !!state.saving);

    if (saveButton) {
      saveButton.disabled = !!state.saving || !dirty;
      saveButton.textContent = state.saving
        ? getMessage('savingRow', 'Saving...')
        : getMessage('saveRow', 'Save Row');
    }

    if (!feedback) {
      return;
    }

    if (dirty && !state.saving && feedback.dataset.state !== 'error') {
      setFeedback(row, 'Unsaved changes.', 'info');
    } else if (!dirty && feedback.dataset.state === 'info') {
      setFeedback(row, '', '');
    }
  };

  const saveRow = async (row) => {
    const state = rowState.get(row);
    if (!state) {
      return;
    }

    const payload = serializeRow(row);
    if (statesEqual(payload, state.initial)) {
      setFeedback(row, getMessage('rowUnchanged', 'No unsaved changes.'), 'info');
      syncRowState(row);
      return;
    }

    const ajaxUrl = adminConfig.ajaxUrl || window.ajaxurl || '';
    const nonce = adminConfig.rowSaveNonce || '';
    if (!ajaxUrl || !nonce) {
      setFeedback(row, getMessage('rowSaveFailed', 'Could not save this row.'), 'error');
      return;
    }

    state.saving = true;
    rowState.set(row, state);
    setFeedback(row, getMessage('savingRow', 'Saving...'), 'info');
    syncRowState(row);

    const requestBody = new URLSearchParams();
    requestBody.append('action', 'vmseb_save_bar_menu_row');
    requestBody.append('nonce', nonce);
    Object.entries(payload).forEach(([key, value]) => {
      requestBody.append(key, String(value));
    });

    try {
      const response = await fetch(ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body: requestBody.toString(),
      });

      let data = null;
      try {
        data = await response.json();
      } catch (error) {
        data = null;
      }

      if (!response.ok || !data || !data.success) {
        throw new Error(data?.data?.message || getMessage('rowSaveFailed', 'Could not save this row.'));
      }

      state.initial = payload;
      rowState.set(row, state);
      setFeedback(row, data?.data?.message || getMessage('rowSaved', 'Saved.'), 'success');
    } catch (error) {
      const message = error instanceof Error ? error.message : getMessage('rowSaveFailed', 'Could not save this row.');
      setFeedback(row, message, 'error');
    } finally {
      state.saving = false;
      rowState.set(row, state);
      syncRowState(row);
    }
  };

  rows.forEach((row) => {
    rowState.set(row, {
      initial: serializeRow(row),
      saving: false,
    });

    row.querySelectorAll('[data-vmseb-field]').forEach((field) => {
      const eventName = field.type === 'checkbox' ? 'change' : 'input';
      field.addEventListener(eventName, () => {
        syncRowState(row);
      });
      if (eventName !== 'change') {
        field.addEventListener('change', () => {
          syncRowState(row);
        });
      }
    });

    row.querySelector('[data-vmseb-row-save]')?.addEventListener('click', () => {
      saveRow(row);
    });

    syncRowState(row);
  });

  if (barMenuForm && registryChangesField) {
    barMenuForm.addEventListener('submit', () => {
      const changedRows = rows
        .map((row) => {
          const state = rowState.get(row);
          if (!state) {
            return null;
          }
          const payload = serializeRow(row);
          return statesEqual(payload, state.initial) ? null : payload;
        })
        .filter(Boolean);

      registryChangesField.value = JSON.stringify(changedRows);
    });
  }
});
