(function () {
  var hasInitialized = false;

  function init() {
    if (hasInitialized) return;
    hasInitialized = true;

    var root = document.querySelector('.vms-ep-basic-grid[data-vms-scroll-target]');
    if (!root) return;

    var targetId = String(root.getAttribute('data-vms-scroll-target') || '').trim();
    if (!targetId) return;

    var target = document.getElementById(targetId);
    if (!target) return;

    window.setTimeout(function () {
      try {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } catch (e) {}
    }, 150);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
