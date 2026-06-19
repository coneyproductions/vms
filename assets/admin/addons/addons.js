(function () {
  'use strict';

  if (typeof window.VMS_ADDONS === 'undefined') {
    return;
  }

  var root = document.getElementById('vms-addons-root');
  var stateTag = document.getElementById('vms-addons-state-json');
  var notice = document.getElementById('vms-addons-notice');
  var searchInput = document.getElementById('vms-addons-search');
  var refreshButton = document.getElementById('vms-addons-refresh');
  var isBusy = false;

  if (!root || !stateTag) {
    return;
  }

  var state = {};
  try {
    state = JSON.parse(stateTag.textContent || '{}');
  } catch (e) {
    state = {};
  }

  function showNotice(message, type) {
    if (!notice) {
      return;
    }
    notice.className = 'notice vms-addons-notice is-visible notice-' + (type || 'info');
    notice.innerHTML = '<p>' + message + '</p>';
  }

  function ensureBusyOverlay() {
    var existing = document.getElementById('vms-addons-busy-overlay');
    if (existing) {
      return existing;
    }
    var overlay = document.createElement('div');
    overlay.id = 'vms-addons-busy-overlay';
    overlay.className = 'vms-addons-busy-overlay';
    overlay.innerHTML = '' +
      '<div class="vms-addons-busy-card">' +
      '<h3 class="vms-addons-busy-title">Working…</h3>' +
      '<p class="vms-addons-busy-msg">Please wait while VMS processes this request.</p>' +
      '<div class="vms-addons-busy-bar"><span></span></div>' +
      '</div>';
    document.body.appendChild(overlay);
    return overlay;
  }

  function setBusy(flag, title, message) {
    isBusy = !!flag;
    var overlay = ensureBusyOverlay();
    var titleEl = overlay.querySelector('.vms-addons-busy-title');
    var msgEl = overlay.querySelector('.vms-addons-busy-msg');

    if (titleEl) {
      titleEl.textContent = title || 'Working…';
    }
    if (msgEl) {
      msgEl.textContent = message || 'Please wait while VMS processes this request.';
    }
    overlay.classList.toggle('is-visible', !!flag);

    var controls = document.querySelectorAll('#vms-addons-root button, #vms-addons-refresh');
    controls.forEach(function (el) {
      if (flag) {
        el.setAttribute('disabled', 'disabled');
      } else {
        el.removeAttribute('disabled');
      }
    });
  }

  function action(urlAction, body, fileInput) {
    var form = new FormData();
    form.append('action', 'vms_addons_' + urlAction);
    form.append('nonce', VMS_ADDONS.nonce);
    Object.keys(body || {}).forEach(function (k) {
      form.append(k, body[k]);
    });
    if (fileInput && fileInput.files && fileInput.files[0]) {
      form.append('zip_file', fileInput.files[0]);
    }

    return fetch(VMS_ADDONS.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: form
    }).then(function (res) {
      return res.json();
    }).then(function (json) {
      if (!json.success) {
        throw new Error((json.data && json.data.message) ? json.data.message : 'Action failed');
      }
      return json.data;
    });
  }

  function pill(label, statusClass) {
    return '<span class="vms-addons-pill ' + statusClass + '">' + label + '</span>';
  }

  function statusClass(status) {
    if (status === 'active' || status === 'installed' || status === 'up_to_date') {
      return 'is-good';
    }
    if (status === 'update' || status === 'inactive' || status === 'unknown' || status === 'missing') {
      return 'is-warn';
    }
    return 'is-bad';
  }

  function renderInstalled(items) {
    var query = (searchInput && searchInput.value ? searchInput.value : '').toLowerCase();
    var html = '<div class="vms-addons-card-grid">';

    (items || []).forEach(function (item) {
      var hay = (item.name + ' ' + (item.description_short || '')).toLowerCase();
      if (query && hay.indexOf(query) === -1) {
        return;
      }

      html += '<article class="vms-addons-card" data-vms-tour="addons.card.' + item.slug + '">';
      html += '<div class="vms-addons-card-head">';
      html += '<h3 class="vms-addons-card-title"><span class="dashicons ' + (item.icon || 'dashicons-admin-plugins') + '"></span>' + item.name + '</h3>';
      html += '</div>';
      html += '<p>' + (item.description_short || '') + '</p>';
      html += '<div class="vms-addons-pill-row">';
      html += pill(item.installed ? 'Installed' : 'Not installed', statusClass(item.installed ? 'installed' : 'missing'));
      html += pill(item.active ? 'Active' : 'Inactive', statusClass(item.active ? 'active' : 'inactive'));
      html += pill('License: ' + (item.license.status || 'unknown'), statusClass(item.license.status || 'unknown'));
      html += pill(item.update_available ? 'Update available' : 'Up to date', statusClass(item.update_available ? 'update' : 'up_to_date'));
      html += '</div>';
      html += '<div class="vms-addons-card-actions">';

      if (!item.installed) {
        html += '<button class="button button-primary" data-action="install" data-slug="' + item.slug + '" data-vms-tour="addons.card.' + item.slug + '.primary">Install</button>';
      } else if (!item.active) {
        html += '<button class="button button-primary" data-action="activate" data-slug="' + item.slug + '" data-vms-tour="addons.card.' + item.slug + '.primary">Activate</button>';
      } else {
        html += '<button class="button" data-action="deactivate" data-slug="' + item.slug + '" data-vms-tour="addons.card.' + item.slug + '.primary">Deactivate</button>';
      }

      if (item.settings_url) {
        html += '<a class="button" href="' + item.settings_url + '">Settings</a>';
      }
      if (item.update_available) {
        html += '<button class="button" data-action="update" data-slug="' + item.slug + '">Update</button>';
      }
      html += '</div>';
      html += '<details><summary>Details</summary>';
      html += '<div class="vms-addons-muted">Plugin file: ' + item.plugin_file + '</div>';
      html += '<div class="vms-addons-muted">Version: ' + (item.version_current || 'n/a') + (item.version_new ? ' → ' + item.version_new : '') + '</div>';
      html += '</details>';
      html += '</article>';
    });

    html += '</div>';
    root.innerHTML = html;
  }

  function renderLicenses(items) {
    var html = '<div class="vms-addons-license-grid" data-vms-tour="addons.licenses">';
    (items || []).forEach(function (item) {
      if (!item.freemius || !item.freemius.product_id) {
        return;
      }
      html += '<div class="vms-addons-license-row">';
      html += '<h3>' + item.name + '</h3>';
      html += '<div class="vms-addons-pill-row">' + pill('Status: ' + (item.license.status || 'unknown'), statusClass(item.license.status || 'unknown')) + '</div>';
      html += '<label>License key<br><input type="text" data-license-input="' + item.slug + '" placeholder="' + (item.license.license_key_masked || '') + '"></label>';
      html += '<div class="vms-addons-card-actions">';
      html += '<button class="button" data-action="license_save" data-slug="' + item.slug + '">Save</button>';
      html += '<button class="button" data-action="license_activate" data-slug="' + item.slug + '">Activate</button>';
      html += '<button class="button" data-action="license_validate" data-slug="' + item.slug + '">Validate</button>';
      html += '<button class="button" data-action="license_deactivate" data-slug="' + item.slug + '">Deactivate</button>';
      html += '</div>';
      html += '<div class="vms-addons-muted">Last checked: ' + (item.license.last_validated || 'never') + '</div>';
      html += '<div class="vms-addons-muted">Install ID: ' + (item.license.install_id || 0) + '</div>';
      html += '</div>';
    });
    html += '</div>';
    root.innerHTML = html;
  }

  function renderSupport(data) {
    var logs = data.logs || [];
    var health = data.health || {};

    var html = '<div data-vms-tour="addons.support">';
    html += '<h2>Support + Diagnostics</h2>';
    html += '<p><strong>System status:</strong> ' + (health.system_status || 'unknown') + '</p>';
    html += '<p><strong>Site UID:</strong> <code>' + (data.uid || '') + '</code></p>';
    html += '<div class="vms-addons-card-actions">';
    html += '<button class="button" data-action="support_healthcheck">Run Health Check</button>';
    html += '<button class="button" data-action="support_export">Export Diagnostics</button>';
    html += '<button class="button" data-action="support_reset_uid">Reset UID</button>';
    html += '</div>';
    html += '<h3>Logs</h3>';
    html += '<div class="vms-addons-log-list">';
    logs.forEach(function (line) {
      html += '<div class="vms-addons-log-line">[' + (line.timestamp || '') + '] ' + (line.level || '') + ' ' + (line.action || '') + ' ' + (line.slug || '') + ' ' + (line.message || '') + '</div>';
    });
    html += '</div>';
    html += '</div>';
    root.innerHTML = html;
  }

  function renderUpdates(items) {
    var html = '<div data-vms-tour="addons.updates">';
    html += '<h2>Available Updates</h2>';
    html += '<div class="vms-addons-card-grid">';
    var count = 0;
    (items || []).forEach(function (item) {
      if (!item.update_available) {
        return;
      }
      count += 1;
      html += '<article class="vms-addons-card">';
      html += '<h3>' + item.name + '</h3>';
      html += '<p>Current: ' + (item.version_current || '') + ' | New: ' + (item.version_new || '') + '</p>';
      html += '<button class="button button-primary" data-action="update" data-slug="' + item.slug + '">Update</button>';
      html += '</article>';
    });
    if (count === 0) {
      html += '<p>No updates available.</p>';
    }
    html += '</div></div>';
    root.innerHTML = html;
  }

  function renderDiscover(items) {
    renderInstalled(items);
  }

  function render() {
    var tab = root.getAttribute('data-tab') || 'installed';
    var items = state.items || [];

    document.getElementById('vms-addons-count-installed').textContent = String((state.counts && state.counts.installed) || 0);
    document.getElementById('vms-addons-count-updates').textContent = String((state.counts && state.counts.updates) || 0);
    document.getElementById('vms-addons-count-licenses').textContent = String((state.counts && state.counts.licenses_active) || 0);
    document.getElementById('vms-addons-system-status').textContent = ((state.health && state.health.system_status) === 'all_good') ? 'All good' : 'Action needed';

    if (tab === 'licenses') {
      renderLicenses(items);
      return;
    }
    if (tab === 'updates') {
      renderUpdates(items);
      return;
    }
    if (tab === 'support') {
      renderSupport(state);
      return;
    }
    if (tab === 'discover') {
      renderDiscover(items);
      return;
    }
    renderInstalled(items);
  }

  function refreshState() {
    return action('get_state', {}).then(function (data) {
      state = data.state || state;
      render();
    });
  }

  function openInstallPrompt(slug) {
    var picker = document.createElement('input');
    picker.type = 'file';
    picker.accept = '.zip,application/zip';
    picker.addEventListener('change', function () {
      if (!picker.files || !picker.files.length) {
        return;
      }
      setBusy(true, 'Installing add-on…', 'Uploading and installing ZIP. This can take up to a minute.');
      action('install_zip', { slug: slug, activate_after: '1' }, picker).then(function (data) {
        state = data.state || state;
        showNotice('Install completed.', 'success');
        render();
      }).catch(function (err) {
        showNotice(err.message, 'error');
      }).finally(function () {
        setBusy(false);
      });
    });
    picker.click();
  }

  function downloadText(filename, text) {
    var blob = new Blob([text], { type: 'application/json' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  root.addEventListener('click', function (event) {
    var target = event.target;
    if (!target || !target.getAttribute) {
      return;
    }
    var actionName = target.getAttribute('data-action');
    var slug = target.getAttribute('data-slug') || '';
    if (!actionName) {
      return;
    }
    if (isBusy) {
      event.preventDefault();
      return;
    }

    event.preventDefault();

    if (actionName === 'install') {
      openInstallPrompt(slug);
      return;
    }

    if (actionName.indexOf('license_') === 0) {
      setBusy(true, 'Updating license…', 'Saving and validating license details.');
      var body = { slug: slug };
      var input = document.querySelector('input[data-license-input="' + slug + '"]');
      if (input && actionName === 'license_save') {
        body.license_key = input.value || '';
      }
      action(actionName, body).then(function (data) {
        state = data.state || state;
        showNotice('License action completed.', 'success');
        render();
      }).catch(function (err) {
        showNotice(err.message, 'error');
      }).finally(function () {
        setBusy(false);
      });
      return;
    }

    if (actionName === 'support_export') {
      setBusy(true, 'Preparing diagnostics…', 'Collecting logs and system context.');
      action(actionName, {}).then(function (data) {
        downloadText(data.filename || 'diagnostics.json', data.contents || '{}');
        showNotice('Diagnostics export created.', 'success');
      }).catch(function (err) {
        showNotice(err.message, 'error');
      }).finally(function () {
        setBusy(false);
      });
      return;
    }

    if (actionName === 'support_healthcheck' || actionName === 'support_reset_uid') {
      setBusy(true, 'Running support action…', 'Please wait.');
      action(actionName, {}).then(function (data) {
        state = data.state || state;
        showNotice('Support action completed.', 'success');
        render();
      }).catch(function (err) {
        showNotice(err.message, 'error');
      }).finally(function () {
        setBusy(false);
      });
      return;
    }

    if (actionName === 'update') {
      setBusy(true, 'Updating add-on…', 'Downloading and applying update package.');
      action('update_run', { slug: slug }).then(function (data) {
        state = data.state || state;
        showNotice('Update completed.', 'success');
        render();
      }).catch(function (err) {
        showNotice(err.message, 'error');
      }).finally(function () {
        setBusy(false);
      });
      return;
    }

    setBusy(true, 'Processing request…', 'Please wait.');
    action(actionName, { slug: slug }).then(function (data) {
      state = data.state || state;
      showNotice('Action completed.', 'success');
      render();
    }).catch(function (err) {
      showNotice(err.message, 'error');
    }).finally(function () {
      setBusy(false);
    });
  });

  if (searchInput) {
    searchInput.addEventListener('input', render);
  }

  if (refreshButton) {
    refreshButton.addEventListener('click', function () {
      if (isBusy) {
        return;
      }
      setBusy(true, 'Refreshing add-ons…', 'Checking plugin and update state.');
      action('updates_refresh', {}).then(function (data) {
        state = data.state || state;
        showNotice('Refreshed.', 'success');
        render();
      }).catch(function (err) {
        showNotice(err.message, 'error');
      }).finally(function () {
        setBusy(false);
      });
    });
  }

  render();
  refreshState();
})();
