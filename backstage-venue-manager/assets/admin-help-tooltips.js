/* global document, window */
(function () {
  'use strict';

  var tip = document.createElement('div');
  tip.className = 'vms-help-tooltip';
  tip.setAttribute('role', 'tooltip');
  tip.setAttribute('aria-hidden', 'true');
  tip.style.display = 'none';
  tip.style.left = '-9999px';
  tip.style.top = '-9999px';
  document.body.appendChild(tip);

  var activeBtn = null;
  var isSticky = false;
  var showTimer = null;
  var hideTimer = null;

  function clearTimers() {
    if (showTimer) {
      window.clearTimeout(showTimer);
      showTimer = null;
    }
    if (hideTimer) {
      window.clearTimeout(hideTimer);
      hideTimer = null;
    }
  }

  function hide() {
    clearTimers();
    tip.style.display = 'none';
    tip.style.left = '-9999px';
    tip.style.top = '-9999px';
    tip.textContent = '';
    tip.setAttribute('aria-hidden', 'true');
    tip.style.visibility = '';

    if (activeBtn) {
      activeBtn.setAttribute('aria-expanded', 'false');
    }

    activeBtn = null;
    isSticky = false;
  }

  function place(btn) {
    var rect = btn.getBoundingClientRect();
    var pad = 8;

    // Temporarily show (hidden) so we can measure.
    tip.style.visibility = 'hidden';
    tip.style.display = 'block';

    var tipRect = tip.getBoundingClientRect();

    var left = rect.left;
    var top = rect.bottom + 8;

    // Keep in viewport.
    if (left + tipRect.width > window.innerWidth - pad) {
      left = window.innerWidth - pad - tipRect.width;
    }
    if (left < pad) {
      left = pad;
    }

    // Flip above if not enough room below.
    if (top + tipRect.height > window.innerHeight - pad) {
      top = rect.top - 8 - tipRect.height;
    }
    if (top < pad) {
      top = pad;
    }

    tip.style.left = Math.round(left) + 'px';
    tip.style.top = Math.round(top) + 'px';
    tip.style.visibility = 'visible';
  }

  function show(btn, sticky) {
    clearTimers();

    var txt = btn.getAttribute('data-vms-help') || '';
    txt = txt.trim();
    if (!txt) {
      return;
    }

    if (activeBtn && activeBtn !== btn) {
      activeBtn.setAttribute('aria-expanded', 'false');
    }

    activeBtn = btn;
    isSticky = !!sticky;

    btn.setAttribute('aria-expanded', 'true');

    tip.textContent = txt;
    tip.style.display = 'block';
    tip.setAttribute('aria-hidden', 'false');
    place(btn);
  }

  function scheduleShow(btn, delayMs, sticky) {
    clearTimers();
    showTimer = window.setTimeout(function () {
      show(btn, sticky);
    }, delayMs);
  }

  function scheduleHide(delayMs) {
    clearTimers();
    hideTimer = window.setTimeout(function () {
      if (!isSticky) {
        hide();
      }
    }, delayMs);
  }

  function onEnter(e) {
    var btn = e.currentTarget;
    if (isSticky && activeBtn === btn) {
      return;
    }
    scheduleShow(btn, 150, false);
  }

  function onLeave() {
    if (isSticky) {
      return;
    }
    scheduleHide(120);
  }

  function onFocus(e) {
    var btn = e.currentTarget;
    if (isSticky && activeBtn === btn) {
      return;
    }
    scheduleShow(btn, 0, false);
  }

  function onBlur() {
    if (isSticky) {
      return;
    }
    scheduleHide(0);
  }

  function onClick(e) {
    e.preventDefault();
    var btn = e.currentTarget;

    if (activeBtn === btn && isSticky) {
      hide();
      return;
    }

    show(btn, true);
  }

  function onDocClick(e) {
    if (!isSticky || !activeBtn) {
      return;
    }

    var target = e.target;
    if (target === activeBtn || activeBtn.contains(target)) {
      return;
    }
    if (tip.contains(target)) {
      return;
    }

    hide();
  }

  function onKeyDown(e) {
    if (e.key === 'Escape' || e.key === 'Esc') {
      hide();
    }
  }

  function onReposition() {
    if (!activeBtn || tip.style.display === 'none') {
      return;
    }
    place(activeBtn);
  }

  function init() {
    var buttons = Array.prototype.slice.call(
      document.querySelectorAll('.vms-help-icon[data-vms-help]')
    );

    if (!buttons.length) {
      return;
    }

    buttons.forEach(function (btn) {
      btn.addEventListener('mouseenter', onEnter);
      btn.addEventListener('mouseleave', onLeave);
      btn.addEventListener('focus', onFocus);
      btn.addEventListener('blur', onBlur);
      btn.addEventListener('click', onClick);
    });

    document.addEventListener('click', onDocClick);
    document.addEventListener('keydown', onKeyDown);

    window.addEventListener('scroll', onReposition, true);
    window.addEventListener('resize', onReposition);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
