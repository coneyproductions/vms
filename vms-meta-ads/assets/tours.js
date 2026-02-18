(function () {
  'use strict';

  var cfg = window.vmsMaTour || {};
  var tours = window.vmsMaTours || {};
  var engine = window.VMS_Tour || {};
  var guidanceCfg = window.vmsMaGuidance || {};

  if (!engine || typeof engine.start !== 'function') {
    return;
  }

  var builderWrap = document.getElementById('vms-ma-ads-builder-wrap');
  var settingsWrap = document.getElementById('vms-ma-settings-wrap');
  var screen = cfg.screen || (settingsWrap ? 'settings' : 'builder');
  var wrap = screen === 'settings' ? settingsWrap : builderWrap;
  if (!wrap) {
    return;
  }

  var tourConfig = tours[screen] || null;
  if (!tourConfig || !Array.isArray(tourConfig.steps) || !tourConfig.steps.length) {
    return;
  }

  var startButtonId = screen === 'settings' ? 'vms-ma-settings-start-tour' : 'vms-ma-start-tour';
  var toggleId = screen === 'settings' ? 'vms-ma-settings-tour-autorun-toggle' : 'vms-ma-tour-autorun-toggle';
  var guidanceSelectId = screen === 'settings' ? 'vms-ma-settings-guidance-level' : 'vms-ma-guidance-level';
  var startButton = document.getElementById(startButtonId);
  var toggle = document.getElementById(toggleId);
  var guidanceSelect = document.getElementById(guidanceSelectId);
  function getGuidanceLevel() {
    var level = guidanceCfg.guidanceLevel || cfg.guidance || 'beginner';
    level = String(level || '').toLowerCase();
    if (level !== 'beginner' && level !== 'standard' && level !== 'expert') {
      return 'beginner';
    }
    return level;
  }

  var guidance = getGuidanceLevel();
  wrap.setAttribute('data-guidance-level', guidance);
  var snoozedForLoad = false;
  var resumeKey = 'vms_ma_tour_resume_' + screen;
  var urlParams = (function () {
    try {
      return new URLSearchParams(window.location.search || '');
    } catch (e) {
      return new URLSearchParams('');
    }
  })();
  var queryTour = parseInt(String(urlParams.get('tour') || wrap.getAttribute('data-tour-query') || '0'), 10) === 1;
  var queryPrefill = parseInt(String(urlParams.get('prefill') || wrap.getAttribute('data-prefill') || '0'), 10) === 1;
  var queryScrollTo = String(urlParams.get('scroll_to') || wrap.getAttribute('data-scroll-to') || '').toLowerCase();

  function setSnooze() {
    snoozedForLoad = true;
  }

  function clearSnooze() {
    snoozedForLoad = false;
  }

  function isSnoozed() {
    return snoozedForLoad;
  }

  function persistPref(payload) {
    if (!cfg.restUrl || !cfg.nonce) {
      return Promise.resolve(false);
    }
    return fetch(cfg.restUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfg.nonce
      },
      body: JSON.stringify(payload || {})
    }).then(function () {
      if (payload && Object.prototype.hasOwnProperty.call(payload, 'autorun')) {
        cfg.autorun = payload.autorun ? 1 : 0;
      }
      if (payload && payload.guidance_level) {
        guidanceCfg.guidanceLevel = payload.guidance_level;
        cfg.guidance = payload.guidance_level;
      }
      return true;
    }).catch(function () {
      return false;
    });
  }

  function getStepHtml(step, guidanceLevel) {
    var safeStep = step || {};
    var html = '';
    var isBeginnerFallback = false;
    if (guidanceLevel === 'beginner' && (safeStep.html_beginner || safeStep.htmlBeginner)) {
      html = safeStep.html_beginner || safeStep.htmlBeginner;
    }
    if (!html && guidanceLevel === 'standard' && (safeStep.html_standard || safeStep.htmlStandard)) {
      html = safeStep.html_standard || safeStep.htmlStandard;
    }
    if (!html && guidanceLevel === 'expert' && (safeStep.html_expert || safeStep.htmlExpert)) {
      html = safeStep.html_expert || safeStep.htmlExpert;
    }
    if (!html && safeStep.html) {
      html = safeStep.html;
    }
    if (!html && (safeStep.html_standard || safeStep.htmlStandard)) {
      html = safeStep.html_standard || safeStep.htmlStandard;
      isBeginnerFallback = guidanceLevel === 'beginner';
    }
    if (!html && (safeStep.html_beginner || safeStep.htmlBeginner)) {
      html = safeStep.html_beginner || safeStep.htmlBeginner;
    }
    if (isBeginnerFallback && html) {
      return linkifyHtml('<p class="vms-ma-tour-fallback-note"><strong>Beginner tip</strong></p>' + html);
    }
    return linkifyHtml(html || '');
  }

  function linkifyHtml(html) {
    if (!html || typeof html !== 'string') {
      return html;
    }

    var template = document.createElement('template');
    template.innerHTML = html;

    var walker = document.createTreeWalker(
      template.content,
      NodeFilter.SHOW_TEXT,
      null
    );

    var nodes = [];
    var node;
    while ((node = walker.nextNode())) {
      nodes.push(node);
    }

    nodes.forEach(function (textNode) {
      if (!textNode || !textNode.nodeValue) {
        return;
      }

      var parent = textNode.parentElement;
      if (!parent) {
        return;
      }

      if (parent.closest('a,script,style,textarea,code,pre')) {
        return;
      }

      var frag = vmsMaLinkifyTextToFragment(textNode.nodeValue, textNode.ownerDocument || document);
      if (frag) {
        textNode.replaceWith(frag);
      }
    });

    return template.innerHTML;
  }

  function vmsMaLinkifyTextToFragment(text, doc) {
    var re = /(https?:\/\/[^\s<]+)|((?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,})(\/[^\s<]*)?/ig;

    var match;
    var lastIndex = 0;
    var changed = false;

    var frag = doc.createDocumentFragment();

    while ((match = re.exec(text)) !== null) {
      var raw = match[0];
      var start = match.index;

      var charBefore = start > 0 ? text[start - 1] : '';
      if (charBefore === '@') {
        continue;
      }

      if (start > lastIndex) {
        frag.appendChild(doc.createTextNode(text.slice(lastIndex, start)));
      }

      var display = '';
      var href = '';

      if (match[1]) {
        display = match[1];
        href = match[1];
      } else {
        display = match[2] + (match[3] || '');
        href = 'https://' + display;
      }

      var split = vmsMaSplitTrailingPunct(display);
      var displayTrimmed = split.text;
      var trailing = split.trailing;

      var hrefTrimmed = href.endsWith(display)
        ? href.slice(0, href.length - display.length) + displayTrimmed
        : href.replace(display, displayTrimmed);

      var a = doc.createElement('a');
      a.href = hrefTrimmed;
      a.target = '_blank';
      a.rel = 'noopener noreferrer';
      a.textContent = displayTrimmed;

      frag.appendChild(a);

      if (trailing) {
        frag.appendChild(doc.createTextNode(trailing));
      }

      lastIndex = start + raw.length;
      changed = true;
    }

    if (!changed) {
      return null;
    }

    if (lastIndex < text.length) {
      frag.appendChild(doc.createTextNode(text.slice(lastIndex)));
    }

    return frag;
  }

  function vmsMaSplitTrailingPunct(str) {
    var punct = ".,;:!?)\\]}>\"'’";
    var trailing = '';
    var text = str;

    while (text.length > 0 && punct.indexOf(text.slice(-1)) !== -1) {
      trailing = text.slice(-1) + trailing;
      text = text.slice(0, -1);
    }

    return { text: text, trailing: trailing };
  }

  function resolveSteps() {
    guidance = getGuidanceLevel();
    wrap.setAttribute('data-guidance-level', guidance);
    return tourConfig.steps.map(function (step) {
      return {
        selector: step.selector,
        stepKey: String(step.step_key || step.stepKey || '').toUpperCase(),
        title: step.title,
        html: getStepHtml(step, guidance),
        prefer: step.prefer_edge || step.prefer || 'right',
        revealAdvanced: !!(step.reveal_advanced || step.revealAdvanced)
      };
    });
  }

  function emitTourToast(message) {
    if (!message) {
      return;
    }
    document.dispatchEvent(new CustomEvent('vms-ma-toast', {
      detail: {
        type: 'warning',
        message: String(message)
      }
    }));
  }

  function deriveStepKeyFromSelector(selector) {
    if (!selector) {
      return '';
    }
    var target = document.querySelector(selector);
    if (!target) {
      return '';
    }
    var stepEl = target.closest('.vms-ma-step');
    if (!stepEl) {
      return '';
    }
    return String(stepEl.getAttribute('data-step-key') || '').toUpperCase();
  }

  function shouldRevealAdvancedForStep(step) {
    if (!step || !step.selector) {
      return false;
    }
    if (step.revealAdvanced) {
      return true;
    }
    var target = document.querySelector(step.selector);
    return !!(target && target.closest('[data-advanced=\"1\"]'));
  }

  function prepareStepVisibility(step) {
    if (screen !== 'builder' || !window.vmsMaStepper || !step) {
      return;
    }
    var key = String(step.stepKey || deriveStepKeyFromSelector(step.selector)).toUpperCase();
    if (key) {
      window.vmsMaStepper.expand(key);
      if (shouldRevealAdvancedForStep(step)) {
        window.vmsMaStepper.showAdvanced(key);
      }
    }
  }

  function ensureVisible(selector, stepKey, needsAdvanced) {
    if (screen !== 'builder' || !window.vmsMaStepper) {
      return Promise.resolve(false);
    }
    var key = String(stepKey || deriveStepKeyFromSelector(selector)).toUpperCase();
    if (key) {
      window.vmsMaStepper.expand(key);
      if (needsAdvanced) {
        window.vmsMaStepper.showAdvanced(key);
      }
    }

    return new Promise(function (resolve) {
      var start = Date.now();
      var maxMs = 1500;

      function check() {
        var target = selector ? document.querySelector(selector) : null;
        if (target) {
          if (typeof target.scrollIntoView === 'function') {
            target.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
          }
          resolve(true);
          return;
        }
        if ((Date.now() - start) >= maxMs) {
          emitTourToast('Tour skipped a step because the field is not available yet.');
          resolve(false);
          return;
        }
        window.setTimeout(check, 50);
      }

      check();
    });
  }

  function scrollStepAIfRequested() {
    if (screen !== 'builder' || queryScrollTo !== 'stepa' || !window.vmsMaStepper) {
      return;
    }
    window.vmsMaStepper.expand('A');
    window.vmsMaStepper.scrollTo('#vms-ma-step-a');
  }

  function saveResumeState(stepIndex, inProgress) {
    try {
      localStorage.setItem(resumeKey, JSON.stringify({
        tourId: tourConfig.tourId || ('vms_ma_' + screen + '_tour'),
        stepIndex: Math.max(0, parseInt(stepIndex || 0, 10)),
        guidance: guidance,
        inProgress: !!inProgress
      }));
    } catch (e) {
      // no-op
    }
  }

  function getResumeState() {
    try {
      var raw = localStorage.getItem(resumeKey);
      if (!raw) {
        return null;
      }
      var parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== 'object') {
        return null;
      }
      return parsed;
    } catch (e) {
      return null;
    }
  }

  function clearResumeState() {
    try {
      localStorage.removeItem(resumeKey);
    } catch (e) {
      // no-op
    }
  }

  function startTour(options) {
    var opts = options || {};
    clearSnooze();
    if (!opts.resume) {
      clearResumeState();
    }
    var resumeState = opts.resume ? getResumeState() : null;
    var resumeIndex = resumeState && typeof resumeState.stepIndex !== 'undefined'
      ? parseInt(resumeState.stepIndex, 10)
      : 0;
    var activeStepIndex = Number.isInteger(resumeIndex) ? resumeIndex : 0;
    var resolvedSteps = resolveSteps();
    if (resolvedSteps[activeStepIndex]) {
      var first = resolvedSteps[activeStepIndex];
      prepareStepVisibility(first);
      ensureVisible(first.selector, first.stepKey, shouldRevealAdvancedForStep(first));
    }
    engine.start({
      tourId: tourConfig.tourId || ('vms_ma_' + screen + '_tour'),
      steps: resolvedSteps,
      options: {
        persistentAutorun: true,
        allowClose: true,
        showProgress: true,
        showPrev: true,
        scrollIntoView: true,
        startIndex: Number.isInteger(resumeIndex) && resumeIndex > 0 ? resumeIndex : 0
      },
      onStepChange: function (idx) {
        activeStepIndex = Math.max(0, parseInt(idx || 0, 10));
        if (resolvedSteps[activeStepIndex]) {
          var current = resolvedSteps[activeStepIndex];
          prepareStepVisibility(current);
          ensureVisible(current.selector, current.stepKey, shouldRevealAdvancedForStep(current));
        }
        saveResumeState(idx, true);
      },
      onFinish: function () {
        setSnooze();
        clearResumeState();
      },
      onClose: function () {
        setSnooze();
        saveResumeState(activeStepIndex, true);
      }
    });
  }

  if (startButton) {
    startButton.addEventListener('click', function () {
      startTour({ resume: false });
    });
  }

  if (toggle) {
    toggle.checked = parseInt(cfg.autorun || wrap.getAttribute('data-tour-autorun') || '1', 10) === 1;
    toggle.addEventListener('change', function () {
      var enabled = !!toggle.checked;
      persistPref({ autorun: enabled ? 1 : 0 });
      if (enabled) {
        clearSnooze();
      }
    });
  }

  if (guidanceSelect) {
    guidanceSelect.value = guidance;
  }

  document.addEventListener('vms-ma-guidance-updated', function (evt) {
    var nextLevel = evt && evt.detail ? String(evt.detail.level || '') : '';
    if (nextLevel !== 'beginner' && nextLevel !== 'standard' && nextLevel !== 'expert') {
      return;
    }
    guidance = nextLevel;
    wrap.setAttribute('data-guidance-level', guidance);
    if (guidanceSelect) {
      guidanceSelect.value = guidance;
    }
  });

  var autorun = parseInt(cfg.autorun || wrap.getAttribute('data-tour-autorun') || '1', 10) === 1;
  scrollStepAIfRequested();
  if ((queryTour || (autorun && !queryPrefill)) && !isSnoozed()) {
    var saved = getResumeState();
    if (saved && saved.inProgress && typeof saved.stepIndex === 'number' && saved.stepIndex > 0) {
      var stepN = parseInt(saved.stepIndex, 10) + 1;
      var shouldResume = window.confirm('Resume tour at Step ' + stepN + '? Click Cancel to restart from Step 1.');
      if (shouldResume) {
        startTour({ resume: true });
      } else {
        clearResumeState();
        startTour({ resume: false });
      }
    } else {
      startTour({ resume: false });
    }
  }
})();
