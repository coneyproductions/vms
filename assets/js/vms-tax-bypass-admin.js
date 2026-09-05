(function () {
  var selectors = [
    'input[name^="vms_tax_"]',
    'select[name^="vms_tax_"]',
    'input[name^="vms_addr"]',
    'input[name="vms_city"]',
    'input[name="vms_state"]',
    'input[name="vms_zip"]',
    'input[name="vms_payee_legal_name"]',
    'select[name="vms_entity_type"]'
  ];
  var root = document.body || document.documentElement;
  var fields = document.querySelectorAll(selectors.join(','));
  var i;

  if (!fields.length) {
    return;
  }

  if (root && root.dataset.vmsTaxBypassRequiredBound === '1') {
    return;
  }
  if (root) {
    root.dataset.vmsTaxBypassRequiredBound = '1';
  }

  for (i = 0; i < fields.length; i++) {
    fields[i].removeAttribute('required');
    fields[i].removeAttribute('aria-required');
  }
})();
