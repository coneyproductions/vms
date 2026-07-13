(function () {
  function initPrimaryVendorTaxController() {
    var bandSel = document.getElementById('vms_band_vendor_id');
    var wrap = document.getElementById('vms-tax-status');
    var bypassWrap = document.getElementById('vms-tax-bypass-inline');
    var bypassUntil = document.getElementById('vms-tax-bypass-until');
    var bypassReason = document.getElementById('vms-tax-bypass-reason');
    var bypassSetBtn = document.getElementById('vms-tax-bypass-set');
    var bypassClearBtn = document.getElementById('vms-tax-bypass-clear');
    var bypassMsg = document.getElementById('vms-tax-bypass-msg');
    var bypassActiveFlag = document.getElementById('vms-tax-bypass-active-flag');
    var activeRequest = null;
    var requestSequence = 0;

    if (!bandSel || !wrap) {
      return false;
    }

    if (wrap.dataset.vmsPrimaryVendorTaxBound === '1') {
      return true;
    }
    wrap.dataset.vmsPrimaryVendorTaxBound = '1';

    function escapeHtml(str) {
      return String(str).replace(/[&<>"']/g, function (char) {
        return (
          {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
          }[char] || char
        );
      });
    }

    function getSelectedOption() {
      return bandSel.options[bandSel.selectedIndex] || null;
    }

    function selectedVendorId() {
      var raw = bandSel.value || '';
      var vendorId = parseInt(raw, 10);
      return Number.isFinite(vendorId) && vendorId > 0 ? vendorId : 0;
    }

    function findOptionByVendorId(vendorId) {
      var normalizedVendorId = parseInt(String(vendorId || ''), 10);
      var index;
      var optionVendorId;

      if (!Number.isFinite(normalizedVendorId) || normalizedVendorId <= 0) {
        return null;
      }

      for (index = 0; index < bandSel.options.length; index += 1) {
        optionVendorId = parseInt(String(bandSel.options[index].value || ''), 10);
        if (Number.isFinite(optionVendorId) && optionVendorId === normalizedVendorId) {
          return bandSel.options[index];
        }
      }

      return null;
    }

    function setBypassMsg(text, type) {
      if (!bypassMsg) {
        return;
      }

      bypassMsg.textContent = text || '';
      bypassMsg.className = 'description vms-mt-6';
      if (type === 'error') {
        bypassMsg.className += ' vms-text-danger';
      }
    }

    function syncRequestButtonState() {
      if (!activeRequest) {
        return;
      }

      if (activeRequest.action === 'set' && bypassSetBtn) {
        bypassSetBtn.disabled = true;
      }
      if (activeRequest.action === 'clear' && bypassClearBtn) {
        bypassClearBtn.disabled = true;
      }
    }

    function setBypassUiEnabled(enabled) {
      var on = !!enabled;

      if (bypassUntil) {
        bypassUntil.disabled = !on;
      }
      if (bypassReason) {
        bypassReason.disabled = !on;
      }
      if (bypassSetBtn) {
        bypassSetBtn.disabled = !on;
      }
      if (bypassClearBtn) {
        bypassClearBtn.disabled = !on;
      }

      syncRequestButtonState();
    }

    function updateBypassDefaultsFromSelection() {
      var option = getSelectedOption();
      var hasVendor;
      var taxOk;
      var active;
      var until;
      var reason;
      var fallbackUntil;
      var needed;

      if (!bypassWrap) {
        return;
      }

      hasVendor = !!(option && selectedVendorId() > 0);
      setBypassUiEnabled(hasVendor);

      if (!hasVendor) {
        bypassWrap.classList.add('vms-hidden');
        wrap.classList.remove('vms-tax-has-bypass-inline', 'vms-tax-has-bypass-inline-active');
        setBypassMsg('Select a vendor to manage bypass.', '');
        return;
      }

      taxOk = option.getAttribute('data-tax-ok') === '1';
      active = option.getAttribute('data-tax-bypass-active') === '1';
      until = String(option.getAttribute('data-tax-bypass-until') || '').trim();
      reason = String(option.getAttribute('data-tax-bypass-reason') || '').trim();
      fallbackUntil = String(bypassWrap.getAttribute('data-default-until') || '').trim();
      needed = !taxOk || active;

      bypassWrap.classList.toggle('vms-hidden', !needed);
      wrap.classList.toggle('vms-tax-has-bypass-inline', needed);
      wrap.classList.toggle('vms-tax-has-bypass-inline-active', needed && active);

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
      setBypassMsg(active ? 'Bypass active until ' + (until || '—') + '.' : 'No bypass is active for this vendor.', '');
    }

    function updateOptionBypass(option, active, until, reason) {
      if (!option) {
        return;
      }

      option.setAttribute('data-tax-bypass-active', active ? '1' : '0');
      option.setAttribute('data-tax-bypass-until', active ? until || '' : '');
      option.setAttribute('data-tax-bypass-reason', active ? reason || '' : '');
    }

    function render() {
      var option = getSelectedOption();
      var taxOk;
      var bypassActive;
      var bypassUntilValue;
      var missing;

      if (!option || !option.value) {
        wrap.innerHTML =
          '<div class="vms-tax-box vms-notice vms-notice--info">' +
          '<div class="title">Tax Profile</div>' +
          '<div class="muted">Select a Primary Vendor to see tax requirements.</div>' +
          '</div>';
        updateBypassDefaultsFromSelection();
        return;
      }

      taxOk = option.getAttribute('data-tax-ok') === '1';
      bypassActive = option.getAttribute('data-tax-bypass-active') === '1';
      bypassUntilValue = String(option.getAttribute('data-tax-bypass-until') || '').trim();
      missing = String(option.getAttribute('data-tax-missing') || '').trim();

      if (taxOk) {
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
          '<div class="muted vms-mt-6">Ready/Publish is allowed while the bypass is active' +
          (bypassUntilValue ? ' (until ' + escapeHtml(bypassUntilValue) + ')' : '') +
          '.</div>' +
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
      var nonce = bypassWrap ? String(bypassWrap.getAttribute('data-nonce') || '') : '';
      var form = new FormData();

      form.append('action', action);
      form.append('nonce', nonce);
      Object.keys(payload || {}).forEach(function (key) {
        form.append(key, payload[key]);
      });

      return fetch(ajaxurl, {
        method: 'POST',
        credentials: 'same-origin',
        body: form
      }).then(function (response) {
        return response.json();
      });
    }

    if (bypassSetBtn) {
      bypassSetBtn.addEventListener('click', async function () {
        var vendorId;
        var until;
        var reason;
        var requestId;
        var json;
        var message;
        var option;

        if (activeRequest) {
          return;
        }

        vendorId = selectedVendorId();
        if (!(vendorId > 0)) {
          setBypassMsg('Select a vendor first.', 'error');
          return;
        }

        until = bypassUntil ? String(bypassUntil.value || '').trim() : '';
        reason = bypassReason ? String(bypassReason.value || '').trim() : '';
        if (!/^\d{4}-\d{2}-\d{2}$/.test(until)) {
          setBypassMsg('Enter a valid "Until" date (YYYY-MM-DD).', 'error');
          return;
        }
        if (!reason) {
          setBypassMsg('Reason is required.', 'error');
          return;
        }

        requestId = requestSequence + 1;
        requestSequence = requestId;
        activeRequest = {
          id: requestId,
          action: 'set',
          vendorId: vendorId
        };

        setBypassMsg('Applying bypass…', '');
        bypassSetBtn.disabled = true;

        try {
          json = await postBypass('vms_tax_bypass_set', {
            post_id: String(vendorId),
            until: until,
            reason: reason
          });

          if (!activeRequest || activeRequest.id !== requestId) {
            return;
          }

          if (!json || !json.success) {
            message = json && json.data && json.data.message ? String(json.data.message) : 'Bypass update failed.';
            if (selectedVendorId() === vendorId) {
              setBypassMsg(message, 'error');
            }
            return;
          }

          option = findOptionByVendorId(vendorId);
          updateOptionBypass(option, true, until, reason);

          if (selectedVendorId() === vendorId) {
            setBypassMsg('Bypass applied.', '');
            render();
          }
        } catch (error) {
          if (selectedVendorId() === vendorId) {
            setBypassMsg('Bypass update failed.', 'error');
          }
        } finally {
          if (activeRequest && activeRequest.id === requestId) {
            activeRequest = null;
          }
          setBypassUiEnabled(!!(getSelectedOption() && selectedVendorId() > 0));
        }
      });
    }

    if (bypassClearBtn) {
      bypassClearBtn.addEventListener('click', async function () {
        var vendorId;
        var requestId;
        var json;
        var message;
        var option;

        if (activeRequest) {
          return;
        }

        vendorId = selectedVendorId();
        if (!(vendorId > 0)) {
          setBypassMsg('Select a vendor first.', 'error');
          return;
        }

        requestId = requestSequence + 1;
        requestSequence = requestId;
        activeRequest = {
          id: requestId,
          action: 'clear',
          vendorId: vendorId
        };

        setBypassMsg('Clearing bypass…', '');
        bypassClearBtn.disabled = true;

        try {
          json = await postBypass('vms_tax_bypass_clear', {
            post_id: String(vendorId)
          });

          if (!activeRequest || activeRequest.id !== requestId) {
            return;
          }

          if (!json || !json.success) {
            message = json && json.data && json.data.message ? String(json.data.message) : 'Clear failed.';
            if (selectedVendorId() === vendorId) {
              setBypassMsg(message, 'error');
            }
            return;
          }

          option = findOptionByVendorId(vendorId);
          updateOptionBypass(option, false, '', '');

          if (selectedVendorId() === vendorId) {
            if (bypassReason) {
              bypassReason.value = '';
            }
            setBypassMsg('Bypass cleared.', '');
            render();
          }
        } catch (error) {
          if (selectedVendorId() === vendorId) {
            setBypassMsg('Clear failed.', 'error');
          }
        } finally {
          if (activeRequest && activeRequest.id === requestId) {
            activeRequest = null;
          }
          setBypassUiEnabled(!!(getSelectedOption() && selectedVendorId() > 0));
        }
      });
    }

    bandSel.addEventListener('change', render);
    render();
    return true;
  }

  if (!initPrimaryVendorTaxController() && document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPrimaryVendorTaxController, { once: true });
  }
})();
