/* Prevent accidental wheel increments on number fields in VMS UI. */
(function () {
  if (typeof document === 'undefined') return;

  function isGuardableNumberInput(el) {
    return !!(el && el.tagName === 'INPUT' && el.type === 'number' && !el.disabled && !el.readOnly
      && el.closest && el.closest('.vms-admin, .vms-portal, .vms-pass-public-page'));
  }
 
  document.addEventListener('wheel', function (e) {
    var target = e && e.target && e.target.closest ? e.target.closest('input[type="number"]') : null;
    if (!isGuardableNumberInput(target)) return;
    if (document.activeElement !== target) return;

    e.preventDefault();
    target.blur();
  }, { passive: false });
})();
