(function () {
  var payload = window.BVMGR_STATUS_NOTICES_DATA || null;
  if (!payload || !Array.isArray(payload.notices) || payload.notices.length === 0) {
    return;
  }

  var notices = payload.notices.slice();
  var context = payload.context || {};
  var ua = String((window.navigator && window.navigator.userAgent) || '');
  var platform = String((window.navigator && window.navigator.platform) || '');
  var maxTouchPoints = Number((window.navigator && window.navigator.maxTouchPoints) || 0);

  function toBool(value) {
    return value === true || value === 1 || value === '1';
  }

  function safeStorage(type) {
    try {
      return type === 'session' ? window.sessionStorage : window.localStorage;
    } catch (e) {
      return null;
    }
  }

  function detectClient() {
    var lowUa = ua.toLowerCase();
    var isIPadLike = /macintosh/.test(lowUa) && maxTouchPoints > 1;
    var isIOS = /iphone|ipad|ipod/.test(lowUa) || isIPadLike;
    var isAndroid = /android/.test(lowUa);

    var device = 'desktop';
    if (/ipad|tablet/.test(lowUa) || (isAndroid && !/mobile/.test(lowUa)) || isIPadLike) {
      device = 'tablet';
    } else if (/mobi|iphone|ipod|windows phone/.test(lowUa) || (isAndroid && /mobile/.test(lowUa))) {
      device = 'mobile';
    }

    var os = 'other';
    if (isIOS) {
      os = 'ios';
    } else if (isAndroid) {
      os = 'android';
    } else if (/windows/.test(lowUa)) {
      os = 'windows';
    } else if (/mac os|macintosh/.test(lowUa)) {
      os = 'macos';
    } else if (/linux/.test(lowUa)) {
      os = 'linux';
    }

    var browser = 'other';
    if (isIOS) {
      if (/crios/.test(lowUa)) {
        browser = 'chrome';
      } else if (/fxios/.test(lowUa)) {
        browser = 'firefox';
      } else if (/edgios/.test(lowUa)) {
        browser = 'edge';
      } else if (/safari/.test(lowUa)) {
        browser = 'safari_ios';
      }
    } else if (/edg\//.test(lowUa)) {
      browser = 'edge';
    } else if (/firefox\//.test(lowUa)) {
      browser = 'firefox';
    } else if (/chrome\//.test(lowUa) && !/edg\//.test(lowUa) && !/opr\//.test(lowUa)) {
      browser = 'chrome';
    } else if (/safari/.test(lowUa) && !/chrome/.test(lowUa)) {
      browser = os === 'macos' ? 'safari_mac' : 'other';
    }

    return {
      device: device,
      os: os,
      browser: browser
    };
  }

  var detected = detectClient();

  function ttlToMs(ttl) {
    if (ttl === '1h') return 3600 * 1000;
    if (ttl === '1d') return 24 * 3600 * 1000;
    if (ttl === '7d') return 7 * 24 * 3600 * 1000;
    return -1;
  }

  function dismissKey(n) {
    return 'vms_notice_dismissed_' + String(n.id) + '_' + String(n.updated_at || 0);
  }

  function sessionSeenKey(n) {
    return 'vms_notice_seen_session_' + String(n.id) + '_' + String(n.updated_at || 0);
  }

  function ttlSeenKey(n) {
    return 'vms_notice_seen_ttl_' + String(n.id) + '_' + String(n.updated_at || 0);
  }

  function parseStoredJSON(raw) {
    if (!raw) return null;
    try {
      return JSON.parse(raw);
    } catch (e) {
      return null;
    }
  }

  function isDismissed(n) {
    var store = safeStorage('local');
    if (!store) return false;
    var value = store.getItem(dismissKey(n));
    if (!value) return false;
    var parsed = parseStoredJSON(value);
    if (!parsed || typeof parsed !== 'object') return false;
    if (parsed.mode === 'forever') return true;
    if (typeof parsed.expiresAt === 'number' && Date.now() < parsed.expiresAt) {
      return true;
    }
    store.removeItem(dismissKey(n));
    return false;
  }

  function markDismissed(n) {
    var store = safeStorage('local');
    if (!store) return;
    var ttlMs = ttlToMs(String(n.dismiss_ttl || '1d'));
    var payloadValue = ttlMs < 0
      ? { mode: 'forever' }
      : { mode: 'ttl', expiresAt: Date.now() + ttlMs };
    try {
      store.setItem(dismissKey(n), JSON.stringify(payloadValue));
    } catch (e) {
      // ignore
    }
  }

  function isSeenByFrequency(n) {
    var frequency = String(n.frequency || 'every_load');
    if (frequency === 'every_load') {
      return false;
    }

    if (frequency === 'until_dismissed') {
      return isDismissed(n);
    }

    if (frequency === 'once_session') {
      var sessionStore = safeStorage('session');
      return !!(sessionStore && sessionStore.getItem(sessionSeenKey(n)) === '1');
    }

    if (frequency === 'once_per_ttl') {
      var local = safeStorage('local');
      if (!local) return false;
      var raw = local.getItem(ttlSeenKey(n));
      if (!raw) return false;
      var until = Number(raw);
      if (until > Date.now()) return true;
      local.removeItem(ttlSeenKey(n));
    }

    return false;
  }

  function markSeenByFrequency(n) {
    var frequency = String(n.frequency || 'every_load');

    if (frequency === 'once_session') {
      var sessionStore = safeStorage('session');
      if (!sessionStore) return;
      try {
        sessionStore.setItem(sessionSeenKey(n), '1');
      } catch (e) {
        // ignore
      }
      return;
    }

    if (frequency === 'once_per_ttl') {
      var local = safeStorage('local');
      if (!local) return;
      var ttlMs = ttlToMs(String(n.dismiss_ttl || '1d'));
      var until = ttlMs < 0 ? Date.now() + (24 * 3600 * 1000) : Date.now() + ttlMs;
      try {
        local.setItem(ttlSeenKey(n), String(until));
      } catch (e) {
        // ignore
      }
    }
  }

  function includesAny(haystack, needles) {
    if (!Array.isArray(needles) || needles.length === 0) return false;
    for (var i = 0; i < needles.length; i += 1) {
      if (haystack.indexOf(needles[i]) >= 0) {
        return true;
      }
    }
    return false;
  }

  function getPageTypeMatches() {
    return {
      event: toBool(context.is_event_single),
      product: toBool(context.is_woo_product),
      cart: toBool(context.is_woo_cart),
      checkout: toBool(context.is_woo_checkout),
      account: toBool(context.is_woo_account),
      ticketing: toBool(context.has_vms_ticketing_wrapper),
      generic: true
    };
  }

  function matchPageGroup(n, reasons) {
    var pageMode = String(n.pages_mode || 'all');
    var uri = String(context.request_uri || window.location.pathname || '').toLowerCase();
    var pageId = Number(context.page_id || 0);

    var excludes = Array.isArray(n.exclude_object_ids) ? n.exclude_object_ids : [];
    if (pageId > 0 && excludes.indexOf(pageId) >= 0) {
      reasons.push('page_excluded_object_id');
      return false;
    }

    var urlExcludes = Array.isArray(n.url_excludes) ? n.url_excludes : [];
    for (var ex = 0; ex < urlExcludes.length; ex += 1) {
      var x = String(urlExcludes[ex] || '').toLowerCase();
      if (x && uri.indexOf(x) >= 0) {
        reasons.push('page_excluded_url');
        return false;
      }
    }

    if (pageMode === 'all') {
      return true;
    }

    var pageMatches = getPageTypeMatches();
    var includeTypes = Array.isArray(n.include_page_types) ? n.include_page_types : [];
    var includeIds = Array.isArray(n.include_object_ids) ? n.include_object_ids : [];
    var urlContains = Array.isArray(n.url_contains) ? n.url_contains : [];

    var matched = false;

    for (var i = 0; i < includeTypes.length; i += 1) {
      var typeKey = String(includeTypes[i] || '');
      if (typeKey && pageMatches[typeKey]) {
        matched = true;
        break;
      }
    }

    if (!matched && pageId > 0 && includeIds.indexOf(pageId) >= 0) {
      matched = true;
    }

    if (!matched) {
      for (var j = 0; j < urlContains.length; j += 1) {
        var token = String(urlContains[j] || '').toLowerCase();
        if (token && uri.indexOf(token) >= 0) {
          matched = true;
          break;
        }
      }
    }

    if (!matched) {
      reasons.push('page_include_not_matched');
      return false;
    }

    return true;
  }

  function matchUserGroup(n, reasons) {
    var loggedIn = toBool(context.is_logged_in);
    var roles = Array.isArray(context.roles) ? context.roles : [];
    var userMode = String(n.user_mode || 'everyone');

    if (userMode === 'logged_in' && !loggedIn) {
      reasons.push('user_logged_out');
      return false;
    }
    if (userMode === 'logged_out' && loggedIn) {
      reasons.push('user_logged_in');
      return false;
    }

    var rolesInclude = Array.isArray(n.roles_include) ? n.roles_include : [];
    if (userMode === 'roles_include' && rolesInclude.length > 0 && !includesAny(roles, rolesInclude)) {
      reasons.push('roles_include_miss');
      return false;
    }

    var rolesExclude = Array.isArray(n.roles_exclude) ? n.roles_exclude : [];
    if (userMode === 'roles_exclude' && rolesExclude.length > 0 && includesAny(roles, rolesExclude)) {
      reasons.push('roles_exclude_hit');
      return false;
    }

    if (Number(n.current_user_match_include || 1) !== 1) {
      reasons.push('user_id_not_included');
      return false;
    }

    return true;
  }

  function matchDeviceGroup(n, reasons) {
    var deviceMode = String(n.device_mode || 'any');
    if (deviceMode !== 'any' && deviceMode !== detected.device) {
      reasons.push('device_mismatch');
      return false;
    }

    var osInclude = Array.isArray(n.os_include) ? n.os_include : [];
    if (osInclude.length > 0 && osInclude.indexOf(detected.os) < 0) {
      reasons.push('os_mismatch');
      return false;
    }

    var browserInclude = Array.isArray(n.browser_include) ? n.browser_include : [];
    if (browserInclude.length > 0 && browserInclude.indexOf(detected.browser) < 0) {
      reasons.push('browser_mismatch');
      return false;
    }

    return true;
  }

  function matchScopeGroup(n, reasons) {
    var scope = String(n.scope || 'front');
    if (toBool(context.is_admin)) {
      if (scope === 'admin' || scope === 'both') return true;
      reasons.push('scope_front_only');
      return false;
    }

    if (scope === 'front' || scope === 'both') return true;
    reasons.push('scope_admin_only');
    return false;
  }

  function matchTimingGroup(n, reasons) {
    var scheduleMode = String(n.schedule_mode || 'always');
    if (scheduleMode !== 'scheduled') {
      return true;
    }

    var nowSec = Math.floor(Date.now() / 1000);
    var start = Number(n.start_ts || 0);
    var end = Number(n.end_ts || 0);

    if (start > 0 && nowSec < start) {
      reasons.push('timing_before_start');
      return false;
    }
    if (end > 0 && nowSec >= end) {
      reasons.push('timing_after_end');
      return false;
    }

    return true;
  }

  function evaluateNotice(n) {
    var reasons = [];

    if (!matchScopeGroup(n, reasons)) return { pass: false, reasons: reasons };
    if (!matchTimingGroup(n, reasons)) return { pass: false, reasons: reasons };
    if (!matchPageGroup(n, reasons)) return { pass: false, reasons: reasons };
    if (!matchUserGroup(n, reasons)) return { pass: false, reasons: reasons };
    if (!matchDeviceGroup(n, reasons)) return { pass: false, reasons: reasons };

    return { pass: true, reasons: reasons };
  }

  var shown = {};

  function getRoot() {
    var root = document.getElementById('vms-notice-root');
    if (root) return root;
    root = document.createElement('div');
    root.id = 'vms-notice-root';
    document.body.appendChild(root);
    return root;
  }

  function createNoticeCard(n) {
    var card = document.createElement('section');
    card.className = 'vms-notice vms-notice--severity-' + String(n.severity || 'warning') + ' vms-notice--intensity-' + String(n.intensity || 2);
    card.setAttribute('data-vms-notice-id', String(n.id || 0));

    var row = document.createElement('div');
    row.className = 'vms-notice__row';

    var content = document.createElement('div');
    content.className = 'vms-notice__content';

    if (n.headline) {
      var title = document.createElement('strong');
      title.className = 'vms-notice__headline';
      title.textContent = String(n.headline);
      content.appendChild(title);
    }

    var body = document.createElement('div');
    body.className = 'vms-notice__body';
    body.innerHTML = String(n.body_html || '');
    content.appendChild(body);

    var actions = document.createElement('div');
    actions.className = 'vms-notice__actions';

    if (n.primary_btn_label) {
      var primary = document.createElement('a');
      primary.className = 'button button-primary';
      primary.textContent = String(n.primary_btn_label);
      if (n.primary_btn_url) {
        primary.href = String(n.primary_btn_url);
      } else {
        primary.href = '#';
      }
      actions.appendChild(primary);
    }

    if (n.secondary_btn_label) {
      var secondary = document.createElement(n.secondary_btn_url ? 'a' : 'button');
      secondary.className = 'button';
      secondary.textContent = String(n.secondary_btn_label);
      if (n.secondary_btn_url) {
        secondary.href = String(n.secondary_btn_url);
      } else {
        secondary.type = 'button';
        secondary.addEventListener('click', function () {
          closeNotice(n.id, true);
        });
      }
      actions.appendChild(secondary);
    }

    if (actions.childNodes.length > 0) {
      content.appendChild(actions);
    }

    row.appendChild(content);

    if (toBool(n.dismissible)) {
      var dismiss = document.createElement('button');
      dismiss.type = 'button';
      dismiss.className = 'vms-notice__dismiss';
      dismiss.setAttribute('aria-label', 'Dismiss notice');
      dismiss.textContent = '×';
      dismiss.addEventListener('click', function () {
        closeNotice(n.id, true);
      });
      row.appendChild(dismiss);
    }

    card.appendChild(row);
    return card;
  }

  function closeNotice(noticeId, dismissedByUser) {
    var id = String(noticeId);
    var slot = document.querySelector('[data-vms-notice-slot-for="' + id + '"]');
    if (slot && slot.parentNode) {
      slot.parentNode.removeChild(slot);
    }
    var notice = null;
    for (var i = 0; i < notices.length; i += 1) {
      if (String(notices[i].id) === id) {
        notice = notices[i];
        break;
      }
    }
    if (notice && dismissedByUser) {
      markDismissed(notice);
    }
  }

  function trapFocus(container) {
    var focusables = container.querySelectorAll('a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])');
    if (!focusables.length) return;

    var first = focusables[0];
    var last = focusables[focusables.length - 1];
    setTimeout(function () {
      try { first.focus(); } catch (e) {}
    }, 0);

    container.addEventListener('keydown', function (event) {
      if (event.key !== 'Tab') return;
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    });
  }

  function showNoticeNow(n) {
    var id = Number(n.id || 0);
    if (!id || shown[id]) return;

    if (isDismissed(n)) return;
    if (isSeenByFrequency(n)) return;

    shown[id] = true;
    markSeenByFrequency(n);

    var root = getRoot();
    var intensity = Number(n.intensity || 2);
    var slot = document.createElement('div');
    slot.setAttribute('data-vms-notice-slot-for', String(id));

    var card = createNoticeCard(n);

    if (intensity === 1) {
      slot.className = 'vms-notice-slot vms-notice-slot--inline';
      var selector = String(n.trigger_selector || '').split(',')[0].trim();
      var target = selector ? document.querySelector(selector) : null;
      slot.appendChild(card);
      if (target && target.parentNode) {
        target.parentNode.insertBefore(slot, target);
      } else {
        root.appendChild(slot);
      }
      return;
    }

    if (intensity === 2 || intensity === 3) {
      slot.className = 'vms-notice-slot ' + (intensity === 2 ? 'vms-notice-slot--banner' : 'vms-notice-slot--sticky') + ' vms-notice-slot--' + String(n.placement || 'top');
      slot.appendChild(card);
      root.appendChild(slot);
      return;
    }

    if (intensity === 4) {
      slot.className = 'vms-notice-slot vms-notice-slot--overlay';
      var modal = document.createElement('div');
      modal.setAttribute('role', 'dialog');
      modal.setAttribute('aria-modal', 'true');
      modal.appendChild(card);
      slot.appendChild(modal);
      root.appendChild(slot);
      trapFocus(modal);
      if (toBool(n.dismissible)) {
        slot.addEventListener('keydown', function (event) {
          if (event.key === 'Escape') {
            closeNotice(id, true);
          }
        });
      }
      return;
    }

    slot.className = 'vms-notice-slot vms-notice-slot--fullscreen';
    var full = document.createElement('div');
    full.setAttribute('role', 'dialog');
    full.setAttribute('aria-modal', 'true');
    full.appendChild(card);
    slot.appendChild(full);
    root.appendChild(slot);
    trapFocus(full);
  }

  function waitForElement(selector, timeoutMs, done) {
    if (!selector) {
      done();
      return;
    }

    var maxAttempts = 20;
    var intervalMs = Math.max(150, Math.floor(timeoutMs / maxAttempts));
    var attempts = 0;
    var timer = window.setInterval(function () {
      attempts += 1;
      if (document.querySelector(selector)) {
        window.clearInterval(timer);
        done();
        return;
      }
      if (attempts >= maxAttempts) {
        window.clearInterval(timer);
      }
    }, intervalMs);
  }

  function onElementVisible(selector, done) {
    if (!selector) {
      done();
      return;
    }

    var el = document.querySelector(selector);
    if (!el) {
      waitForElement(selector, 5000, function () {
        var target = document.querySelector(selector);
        if (!target) return;
        onElementVisible(selector, done);
      });
      return;
    }

    if (!('IntersectionObserver' in window)) {
      done();
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      for (var i = 0; i < entries.length; i += 1) {
        if (entries[i].isIntersecting) {
          observer.disconnect();
          done();
          return;
        }
      }
    }, { threshold: 0.15 });

    observer.observe(el);
  }

  function scheduleNotice(n) {
    var trigger = String(n.trigger || 'on_load');
    if (trigger === 'after_delay') {
      var delay = Math.max(0, Number(n.trigger_delay_ms || 0));
      window.setTimeout(function () { showNoticeNow(n); }, delay);
      return;
    }

    if (trigger === 'when_element_exists') {
      waitForElement(String(n.trigger_selector || ''), 5000, function () {
        showNoticeNow(n);
      });
      return;
    }

    if (trigger === 'on_element_visible') {
      onElementVisible(String(n.trigger_selector || ''), function () {
        showNoticeNow(n);
      });
      return;
    }

    showNoticeNow(n);
  }

  function renderDebugPanel(results) {
    if (!toBool(context.debug_enabled)) {
      return;
    }

    var panel = document.createElement('aside');
    panel.className = 'vms-notice-debug';
    panel.innerHTML = '<h4>VMS Notice Debug</h4>';

    var det = document.createElement('p');
    det.innerHTML = 'device=<code>' + detected.device + '</code> os=<code>' + detected.os + '</code> browser=<code>' + detected.browser + '</code>';
    panel.appendChild(det);

    var ctx = document.createElement('p');
    ctx.innerHTML = 'page flags: event=' + Number(toBool(context.is_event_single)) + ' cart=' + Number(toBool(context.is_woo_cart)) + ' checkout=' + Number(toBool(context.is_woo_checkout)) + ' ticketing=' + Number(toBool(context.has_vms_ticketing_wrapper));
    panel.appendChild(ctx);

    var ul = document.createElement('ul');
    for (var i = 0; i < results.length; i += 1) {
      var item = results[i];
      var li = document.createElement('li');
      var title = item.notice.title || ('Notice #' + item.notice.id);
      var reasons = item.result.reasons.length ? item.result.reasons.join(', ') : 'none';
      li.innerHTML = '<strong>' + title + '</strong>: <code>' + (item.result.pass ? 'PASS' : 'FAIL') + '</code> (' + reasons + ')';
      ul.appendChild(li);
    }
    panel.appendChild(ul);

    document.body.appendChild(panel);
  }

  function boot() {
    var results = [];
    for (var i = 0; i < notices.length; i += 1) {
      var n = notices[i];
      var result = evaluateNotice(n);
      results.push({ notice: n, result: result });
    }

    var matches = [];
    for (var j = 0; j < results.length; j += 1) {
      if (results[j].result.pass) {
        matches.push(results[j].notice);
      }
    }

    matches.sort(function (a, b) {
      var p = Number(b.priority || 0) - Number(a.priority || 0);
      if (p !== 0) return p;
      return Number(b.id || 0) - Number(a.id || 0);
    });

    var overlays = [];
    var nonOverlays = [];
    for (var k = 0; k < matches.length; k += 1) {
      var intensity = Number(matches[k].intensity || 2);
      if (intensity >= 4) {
        overlays.push(matches[k]);
      } else {
        nonOverlays.push(matches[k]);
      }
    }

    if (overlays.length > 0) {
      scheduleNotice(overlays[0]);
    }

    var maxNonOverlays = 2;
    for (var m = 0; m < nonOverlays.length && m < maxNonOverlays; m += 1) {
      scheduleNotice(nonOverlays[m]);
    }

    renderDebugPanel(results);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
