(function () {
  'use strict';

  var cfg = window.vmsMaSettings || {};
  var wrap = document.getElementById('vms-ma-settings-wrap');
  if (!wrap) {
    return;
  }
 
  function setBusy(button, busyText) {
    if (!button) {
      return function () {};
    }
    var previous = button.value || button.textContent;
    button.disabled = true;
    if (typeof button.value !== 'undefined' && button.tagName === 'INPUT') {
      button.value = busyText;
    } else {
      button.textContent = busyText;
    }
    return function () {
      button.disabled = false;
      if (typeof button.value !== 'undefined' && button.tagName === 'INPUT') {
        button.value = previous;
      } else {
        button.textContent = previous;
      }
    };
  }

  function testButtonState() {
    var testBtn = wrap.querySelector('input[name="vms_ma_test_connection"]');
    if (!testBtn) {
      return;
    }

    var form = testBtn.form;
    if (!form) {
      return;
    }

    form.addEventListener('submit', function (evt) {
      var submitter = evt.submitter || document.activeElement;
      var marker = form.querySelector('input[type="hidden"][name="vms_ma_test_connection"]');
      if (submitter !== testBtn) {
        if (marker) {
          marker.remove();
        }
        return;
      }
      if (!marker) {
        marker = document.createElement('input');
        marker.type = 'hidden';
        marker.name = 'vms_ma_test_connection';
        marker.value = '1';
        form.appendChild(marker);
      }
      setBusy(testBtn, 'Connecting...');
    });
  }

  function tokenControls() {
    var input = document.getElementById('vms-ma-token-input');
    var reveal = document.getElementById('vms-ma-token-reveal');
    var copy = document.getElementById('vms-ma-token-copy');
    if (!input || !reveal || !copy) {
      return;
    }

    function fetchToken() {
      if (!cfg.tokenInspectUrl || !cfg.nonce) {
        return Promise.resolve(null);
      }
      return fetch(cfg.tokenInspectUrl, {
        method: 'GET',
        headers: {
          'X-WP-Nonce': cfg.nonce
        }
      }).then(function (res) {
        if (!res.ok) {
          return null;
        }
        return res.json();
      }).catch(function () {
        return null;
      });
    }

    reveal.addEventListener('click', function () {
      var isPassword = input.getAttribute('type') === 'password';
      if (!isPassword) {
        input.setAttribute('type', 'password');
        reveal.textContent = 'Reveal';
        return;
      }

      var restore = setBusy(reveal, 'Loading...');
      fetchToken().then(function (json) {
        if (!json || !json.present || !json.token) {
          restore();
          return;
        }
        input.value = String(json.token);
        input.setAttribute('type', 'text');
        reveal.textContent = 'Hide';
        restore();
      });
    });

    copy.addEventListener('click', function () {
      var value = String(input.value || '').trim();
      if (!value || !navigator.clipboard) {
        return;
      }
      navigator.clipboard.writeText(value).catch(function () {
        return null;
      });
    });
  }

  function pageLookupControls() {
    var lookupBtn = document.getElementById('vms-ma-pages-lookup');
    var select = document.getElementById('vms-ma-pages-select');
    var pageInput = wrap.querySelector('input[name="meta_page_id"]');
    var igInput = wrap.querySelector('input[name="meta_ig_actor_id"]');
    if (!lookupBtn || !select || !pageInput) {
      return;
    }

    function fetchPages() {
      if (!cfg.metaPagesUrl || !cfg.nonce) {
        return Promise.resolve([]);
      }
      return fetch(cfg.metaPagesUrl, {
        method: 'GET',
        headers: {
          'X-WP-Nonce': cfg.nonce
        }
      }).then(function (res) {
        return res.json().then(function (json) {
          if (!res.ok) {
            throw new Error((json && json.message) ? json.message : 'Page lookup failed.');
          }
          var items = (json && Array.isArray(json.items)) ? json.items : [];
          return items;
        });
      });
    }

    lookupBtn.addEventListener('click', function () {
      var restore = setBusy(lookupBtn, 'Loading...');
      fetchPages().then(function (items) {
        select.innerHTML = '';
        if (!items.length) {
          select.classList.add('vms-ma-hidden');
          return;
        }
        select.appendChild(new Option('Select a Page...', ''));
        items.forEach(function (item) {
          var label = String(item.name || ('Page ' + item.id));
          var opt = new Option(label + ' (' + item.id + ')', String(item.id || ''));
          opt.setAttribute('data-ig-actor-id', String(item.ig_actor_id || ''));
          select.appendChild(opt);
        });
        select.classList.remove('vms-ma-hidden');
      }).catch(function () {
        select.classList.add('vms-ma-hidden');
      }).finally(function () {
        restore();
      });
    });

    select.addEventListener('change', function () {
      var chosen = select.options[select.selectedIndex] || null;
      if (!chosen || !chosen.value) {
        return;
      }
      pageInput.value = String(chosen.value);
      if (igInput) {
        var igActorId = String(chosen.getAttribute('data-ig-actor-id') || '');
        if (igActorId) {
          igInput.value = igActorId;
        }
      }
    });
  }

  testButtonState();
  tokenControls();
  pageLookupControls();
})();
