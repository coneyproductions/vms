(function () {
  function initTitleController() {
    var postForm = document.getElementById('post');
    if (!postForm) {
      return false;
    }

    if (postForm.dataset.vmsTitleSyncBound === '1') {
      return true;
    }
    postForm.dataset.vmsTitleSyncBound = '1';

    var bandSel = document.getElementById('vms_band_vendor_id');
    var autoTitle = document.querySelector('input[name="vms_auto_title"]');
    var previewEl = document.getElementById('vms_title_preview_text');
    var lockNote = document.getElementById('vms_title_lock_note');
    var wpTitleInput =
      document.getElementById('title') ||
      document.querySelector('textarea.editor-post-title__input') ||
      document.querySelector('h1.editor-post-title__input');

    function setLockNote(on) {
      if (!lockNote) return;
      lockNote.classList.toggle('vms-hidden', !on);
      lockNote.hidden = !on;
      lockNote.style.display = on ? '' : 'none';
    }

    function getBandName() {
      if (!bandSel) return '';
      var opt = bandSel.options[bandSel.selectedIndex];
      if (!opt) return '';

      var raw = String(opt.getAttribute('data-vendor-title') || '').trim();
      if (raw) return raw;

      var clean = String(opt.text || '').trim();
      while (/\s*\[[^\]]+\]\s*$/.test(clean)) {
        clean = clean.replace(/\s*\[[^\]]+\]\s*$/, '').trim();
      }
      return clean;
    }

    function buildTitle() {
      var band = getBandName();
      if (!band) return '';
      return band;
    }

    function updatePreview() {
      var isAuto = autoTitle ? autoTitle.checked : true;
      setLockNote(!isAuto);
      if (!previewEl) return;
      if (!isAuto) {
        previewEl.textContent = '(auto-title disabled)';
        return;
      }
      var title = buildTitle();
      previewEl.textContent = title || '(select Primary Vendor to preview)';
    }

    var lastBuilt = buildTitle();

    function notifyGutenbergTitleChange(newTitle) {
      try {
        if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
          wp.data.dispatch('core/editor').editPost({
            title: newTitle
          });
        }
      } catch (e) {}
    }

    function setWpTitle(title) {
      var el = wpTitleInput || document.querySelector('.editor-post-title__input');
      var descriptor = null;
      var valueSetter = null;

      if (!el) return;

      if (typeof window.HTMLInputElement !== 'undefined' && window.HTMLInputElement.prototype) {
        descriptor = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value');
        valueSetter = descriptor && typeof descriptor.set === 'function' ? descriptor.set : null;
      }

      if (valueSetter) {
        valueSetter.call(el, title);
      } else {
        el.value = title;
      }

      el.dispatchEvent(new Event('input', {
        bubbles: true
      }));

      notifyGutenbergTitleChange(title);
    }

    function getWpTitle() {
      if (!wpTitleInput) return '';
      return String(wpTitleInput.value || wpTitleInput.textContent || '').trim();
    }

    function updateWpTitleBox() {
      var title = buildTitle();
      var current;
      var currentLower;
      var isAuto = autoTitle ? autoTitle.checked : true;

      if (!title) return;

      current = getWpTitle();
      currentLower = current.toLowerCase();

      setLockNote(!isAuto);

      if (!current || currentLower === 'auto draft') {
        setWpTitle(title);
        lastBuilt = title;
        return;
      }

      if (lastBuilt && current === lastBuilt) {
        setWpTitle(title);
        lastBuilt = title;
        return;
      }

      if (current !== title) {
        var ok = window.confirm('Primary Vendor changed. Update the title to match the selected Primary Vendor?');

        if (ok) {
          if (autoTitle) autoTitle.checked = true;
          setWpTitle(title);
          setLockNote(false);
        } else {
          if (autoTitle) autoTitle.checked = false;
          setLockNote(true);
        }
      }

      lastBuilt = title;
    }

    function onChange() {
      updatePreview();
      updateWpTitleBox();
    }

    postForm.addEventListener('submit', function () {
      window.onbeforeunload = null;
    });

    if (bandSel) {
      bandSel.addEventListener('change', onChange);
    }

    if (autoTitle) {
      autoTitle.addEventListener('change', function () {
        updatePreview();
      });
    }

    updatePreview();
    return true;
  }

  if (!initTitleController() && document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTitleController, { once: true });
  }
})();
