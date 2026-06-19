(function () {
  document.addEventListener('click', function (event) {
    var trigger = event.target && event.target.closest ? event.target.closest('[data-vms-copy]') : null;
    if (!trigger) {
      return;
    }

    var selector = String(trigger.getAttribute('data-vms-copy') || '');
    if (!selector) {
      return;
    }

    var input = document.querySelector(selector);
    if (!input) {
      return;
    }

    var text = String(input.value || '');
    if (!text) {
      return;
    }

    var original = String(trigger.textContent || 'Copy');

    var markDone = function () {
      trigger.textContent = 'Copied';
      window.setTimeout(function () {
        trigger.textContent = original;
      }, 1400);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(markDone).catch(function () {
        if (input.select) {
          input.select();
          document.execCommand('copy');
          markDone();
        }
      });
      return;
    }

    if (input.select) {
      input.select();
      document.execCommand('copy');
      markDone();
    }
  });
})();
