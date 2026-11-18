(function () {
  function legacyCopy(text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', 'readonly');
    ta.style.position = 'absolute';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    var ok = false;
    try {
      ok = document.execCommand('copy');
    } catch (e) {
      ok = false;
    }
    document.body.removeChild(ta);
    return ok;
  }

  function copyText(text) {
    if (!text) return Promise.resolve(false);
    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
      return navigator.clipboard.writeText(text).then(function () { return true; }).catch(function () {
        return legacyCopy(text);
      });
    }
    return Promise.resolve(legacyCopy(text));
  }

  function ensureNotice(button) {
    var holder = button.closest('.vms-social-manual-tools');
    if (!holder) return null;
    var el = holder.querySelector('.vms-social-copy-status');
    if (el) return el;
    el = document.createElement('span');
    el.className = 'vms-social-copy-status';
    holder.appendChild(el);
    return el;
  }

  function appendFooterForms(html) {
    if (!html) return;
    var wrapper = document.createElement('div');
    wrapper.innerHTML = String(html);
    var forms = wrapper.querySelectorAll('form[id]');
    Array.prototype.forEach.call(forms, function (form) {
      var existing = document.getElementById(form.id);
      if (existing && existing.parentNode) {
        existing.parentNode.removeChild(existing);
      }
      document.body.appendChild(form);
    });
  }

  function loadLazyEventPanel(shell) {
    if (!shell) return;
    if (shell.getAttribute('data-vms-social-loaded') === '1' || shell.getAttribute('data-vms-social-loading') === '1') return;

    var postId = shell.getAttribute('data-vms-social-post-id') || '';
    var ajaxUrl = shell.getAttribute('data-vms-social-url') || '';
    var nonce = shell.getAttribute('data-vms-social-nonce') || '';
    if (!postId || !ajaxUrl || !nonce || typeof window.fetch !== 'function' || typeof window.URLSearchParams !== 'function') {
      return;
    }

    shell.setAttribute('data-vms-social-loading', '1');

    var params = new window.URLSearchParams();
    params.set('action', 'vms_social_load_event_panel');
    params.set('post_id', String(postId));
    params.set('nonce', String(nonce));

    window.fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: params.toString()
    }).then(function (response) {
      return response.json();
    }).then(function (payload) {
      if (!payload || !payload.success || !payload.data || !payload.data.html) {
        throw new Error('lazy-load-failed');
      }
      shell.innerHTML = String(payload.data.html);
      shell.setAttribute('data-vms-social-loaded', '1');
      shell.removeAttribute('data-vms-social-loading');
      appendFooterForms(payload.data.footer_forms_html || '');
    }).catch(function () {
      shell.removeAttribute('data-vms-social-loading');
      shell.innerHTML = '<p class="description">Unable to load social sharing tools right now. Reload and try again.</p>';
    });
  }

  function maybeLoadOpenEventPanel() {
    var box = document.getElementById('vms_social_promotion');
    if (!box || box.classList.contains('closed')) return;
    var shell = box.querySelector('[data-vms-social-lazy]');
    if (shell) {
      loadLazyEventPanel(shell);
    }
  }

  document.addEventListener('click', function (event) {
    var btn = event.target.closest('.vms-social-copy-btn');
    if (!btn) return;
    event.preventDefault();
    var text = btn.getAttribute('data-copy-text') || '';
    var status = ensureNotice(btn);
    copyText(text).then(function (ok) {
      if (!status) return;
      status.textContent = ok ? 'Copied' : 'Copy failed';
      window.setTimeout(function () {
        status.textContent = '';
      }, 1500);
    });
  });

  document.addEventListener('click', function (event) {
    var toggle = event.target.closest('#vms_social_promotion .handlediv, #vms_social_promotion .hndle');
    if (!toggle) return;
    window.setTimeout(maybeLoadOpenEventPanel, 0);
  });

  if (window.jQuery && typeof window.jQuery === 'function') {
    window.jQuery(document).on('postbox-toggled', function (event, box) {
      if (box && box.id === 'vms_social_promotion') {
        maybeLoadOpenEventPanel();
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', maybeLoadOpenEventPanel);
  } else {
    maybeLoadOpenEventPanel();
  }
})();
