(function () {
  'use strict';

  function init() {
    document.addEventListener('click', function (event) {
      if (!event.target || !event.target.closest) {
        return;
      }

      var runBtn = event.target.closest('[data-vms-tour-run]');
      if (runBtn) {
        runBtn.blur();
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
