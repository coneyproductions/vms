(function ($) {

  function lsGet(key) {
    try {
      if (!window.localStorage) return null;
      return window.localStorage.getItem(key);
    } catch (e) {
      return null;
    }
  }
 
  function lsSet(key, val) {
    try {
      if (!window.localStorage) return;
      window.localStorage.setItem(key, String(val));
    } catch (e) {
      // ignore
    }
  }

  function syncScopeDisables() {
    var $scope = $('#vms-dash-scope');
    var $venue = $('#vms-dash-venue-select');
    if (!$scope.length || !$venue.length) return;

    var scopeVal = String($scope.val() || 'venue');
    $venue.prop('disabled', scopeVal === 'all');
  }

  function savePrefs(alsoServer) {
    var $scope = $('#vms-dash-scope');
    var $venue = $('#vms-dash-venue-select');
    if (!$scope.length || !$venue.length) return;

    var scopeVal = String($scope.val() || 'venue');
    var venueVal = String($venue.val() || '0');
    if (scopeVal === 'all') venueVal = '0';

    // Always persist locally (this device).
    lsSet('vms_dash_scope', scopeVal);
    lsSet('vms_dash_venue_id', venueVal);

    if (!alsoServer) return;

    var ajaxUrl = (window.VMS_DASH && window.VMS_DASH.ajaxUrl) ? window.VMS_DASH.ajaxUrl : '';
    var nonce = (window.VMS_DASH && window.VMS_DASH.dashPrefNonce) ? window.VMS_DASH.dashPrefNonce : '';
    if (!ajaxUrl || !nonce) return;

    $.ajax({
      url: ajaxUrl,
      method: 'POST',
      data: {
        action: 'vms_set_dashboard_prefs',
        nonce: nonce,
        dash_scope: scopeVal,
        dash_venue_id: venueVal
      }
    }).fail(function () {
      // Last-resort fallback: submit the normal admin-post form if present.
      // This guarantees persistence even if AJAX is blocked.
      var $form = $('#vms-dash-pref-form');
      if ($form.length) {
        $form.trigger('submit');
      }
    });
  }

  function restoreFromStorageIfNoServerPref() {
    var $wrap = $('.vms-dash-selector').first();
    var hasServerPref = ($wrap.length && ($wrap.attr('data-has-pref') === '1'));
    if (hasServerPref) return;

    var $scope = $('#vms-dash-scope');
    var $venue = $('#vms-dash-venue-select');
    if (!$scope.length || !$venue.length) return;

    var storedScope = String(lsGet('vms_dash_scope') || '');
    var storedVenue = String(lsGet('vms_dash_venue_id') || '');

    var changed = false;

    if (storedScope === 'all' || storedScope === 'venue') {
      if (String($scope.val() || '') !== storedScope) {
        $scope.val(storedScope);
        changed = true;
      }
    }

    var scopeNow = String($scope.val() || 'venue');
    if (scopeNow === 'venue' && storedVenue) {
      if ($venue.find('option[value="' + storedVenue + '"]').length) {
        if (String($venue.val() || '') !== storedVenue) {
          $venue.val(storedVenue);
          changed = true;
        }
      }
    }

    if (changed) {
      syncScopeDisables();

      // Tell any other dashboard scripts a filter changed so they can refresh panels.
      // (Avoids hard-coupling to any one implementation.)
      $scope.trigger('change');
    }

    // Attempt to sync to server so it persists across devices/sessions.
    savePrefs(true);
  }



  function pickFirst(selectors) {
    for (var i = 0; i < selectors.length; i++) {
      var $x = $(selectors[i]);
      if ($x.length) return $x.first();
    }
    return $();
  }


  function getServerIncludeDraftsPref() {
    var $wrap = $('#vms-dashboard-filters').first();
    if (!$wrap.length) return null;

    var has = String($wrap.attr('data-has-include-drafts') || '0');
    if (has !== '1') return null;

    var val = String($wrap.attr('data-include-drafts') || '0').toLowerCase();
    return (val === '1' || val === 'true' || val === 'yes' || val === 'on');
  }

  function getServerIncludeCanceledPref() {
    var $wrap = $('#vms-dashboard-filters').first();
    if (!$wrap.length) return null;

    var has = String($wrap.attr('data-has-include-canceled') || '0');
    if (has !== '1') return null;

    var val = String($wrap.attr('data-include-canceled') || '0').toLowerCase();
    return (val === '1' || val === 'true' || val === 'yes' || val === 'on');
  }

  function getDefaultIncludeCanceledPref() {
    var $wrap = $('#vms-dashboard-filters').first();
    if (!$wrap.length) return true;

    var val = String($wrap.attr('data-include-canceled') || '1').toLowerCase();
    return (val === '1' || val === 'true' || val === 'yes' || val === 'on');
  }

  function saveIncludeDraftsServer(wantOn) {
    var ajaxUrl = (window.VMS_DASH && window.VMS_DASH.ajaxUrl) ? window.VMS_DASH.ajaxUrl : '';
    var nonce = (window.VMS_DASH && window.VMS_DASH.dashPrefNonce) ? window.VMS_DASH.dashPrefNonce : '';
    if (!ajaxUrl || !nonce) return;

    $.ajax({
      url: ajaxUrl,
      method: 'POST',
      data: {
        action: 'vms_set_dashboard_prefs',
        nonce: nonce,
        include_drafts: wantOn ? 1 : 0
      }
    });
  }

  function saveIncludeCanceledServer(wantOn) {
    var ajaxUrl = (window.VMS_DASH && window.VMS_DASH.ajaxUrl) ? window.VMS_DASH.ajaxUrl : '';
    var nonce = (window.VMS_DASH && window.VMS_DASH.dashPrefNonce) ? window.VMS_DASH.dashPrefNonce : '';
    if (!ajaxUrl || !nonce) return;

    $.ajax({
      url: ajaxUrl,
      method: 'POST',
      data: {
        action: 'vms_set_dashboard_prefs',
        nonce: nonce,
        include_canceled: wantOn ? 1 : 0
      }
    });
  }


  // Filter checkbox persistence (device-local via localStorage)
  function saveFilterPrefs() {
    var $onlyOpen = pickFirst(['#vms-only-open', 'input[name="only_open"]']);
    var $incCan   = pickFirst(['#vms-include-canceled', 'input[name="include_canceled"]']);
    var $incDr    = pickFirst(['#vms-include-drafts', 'input[name="include_drafts"]']);

    if ($onlyOpen.length) lsSet('vms_dash_only_open', $onlyOpen.is(':checked') ? 1 : 0);
    if ($incCan.length) lsSet('vms_dash_include_canceled', $incCan.is(':checked') ? 1 : 0);
    if ($incDr.length) lsSet('vms_dash_include_drafts', $incDr.is(':checked') ? 1 : 0);
  }

  function restoreFilterPrefs() {
    var map = [
      { key: 'vms_dash_only_open', selectors: ['#vms-only-open', 'input[name="only_open"]'] },
      { key: 'vms_dash_include_canceled', selectors: ['#vms-include-canceled', 'input[name="include_canceled"]'] },
      { key: 'vms_dash_include_drafts', selectors: ['#vms-include-drafts', 'input[name="include_drafts"]'] }
    ];

    var serverInc = getServerIncludeDraftsPref();
    var serverCan = getServerIncludeCanceledPref();
    var defaultCan = getDefaultIncludeCanceledPref();

    for (var i = 0; i < map.length; i++) {
      var m = map[i];
      var stored;
      if (m.key === 'vms_dash_include_drafts' && serverInc !== null) {
        stored = serverInc ? '1' : '0';
      } else if (m.key === 'vms_dash_include_canceled') {
        if (serverCan !== null) {
          stored = serverCan ? '1' : '0';
        } else {
          // Migration behavior:
          // when no server preference exists yet, prefer dashboard default-on
          // instead of stale historical localStorage values.
          stored = defaultCan ? '1' : '0';
        }
      } else {
        stored = lsGet(m.key);
      }

      if (m.key === 'vms_dash_include_drafts' && serverInc !== null) {
        lsSet(m.key, serverInc ? 1 : 0);
      }
      if (m.key === 'vms_dash_include_canceled') {
        if (serverCan !== null) {
          lsSet(m.key, serverCan ? 1 : 0);
        } else {
          lsSet(m.key, (stored === '1') ? 1 : 0);
          saveIncludeCanceledServer((stored === '1'));
        }
      }
      if (stored === null || stored === '') continue;

      var want = (String(stored) === '1' || String(stored).toLowerCase() === 'true');
      var $el = pickFirst(m.selectors);
      if (!$el.length) continue;

      var have = !!$el.is(':checked');
      if (have !== want) {
        $el.prop('checked', want);
        $el.trigger('change');
      }
    }
  }

  $(document).ready(function () {

    restoreFromStorageIfNoServerPref();
    syncScopeDisables();

    restoreFilterPrefs();

    // Persist on change (All Venues vs This Venue + venue selection)
    $(document).on('change', '#vms-dash-scope, #vms-dash-venue-select', function () {
      syncScopeDisables();
      savePrefs(true);
    });

    // Persist filter checkbox state on change (this device).
    $(document).on(
      'change',
      '#vms-only-open, #vms-include-canceled, #vms-include-drafts, input[name="only_open"], input[name="include_canceled"], input[name="include_drafts"]',
      function () {
        saveFilterPrefs();

        var id = String(this.id || '');
        var name = String($(this).attr('name') || '');
        if (id === 'vms-include-drafts' || name === 'include_drafts') {
          saveIncludeDraftsServer($(this).is(':checked'));
        } else if (id === 'vms-include-canceled' || name === 'include_canceled') {
          saveIncludeCanceledServer($(this).is(':checked'));
        }
      }
    );
  });

})(jQuery);
