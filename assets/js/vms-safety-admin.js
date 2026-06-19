(function () {
  function markDirty(form) {
    if (!form) return;
    form.setAttribute('data-vms-safety-dirty', '1');
  }

  function bindForm(form) {
    if (!form) return;
    form.setAttribute('data-vms-safety-dirty', '0');
    form.addEventListener('change', function () {
      markDirty(form);
    });
    form.addEventListener('input', function () {
      markDirty(form);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var forms = document.querySelectorAll('[data-vms-safety-sticky-form="1"]');
    for (var i = 0; i < forms.length; i += 1) {
      bindForm(forms[i]);
    }
  });
})();
