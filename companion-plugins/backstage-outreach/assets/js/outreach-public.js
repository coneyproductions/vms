(function () {
  function updateClaimButton(select) {
    var form = select && select.closest ? select.closest('form') : null;
    var button = form ? form.querySelector('[data-backstage-claim-button]') : null;
    if (!button) return;
    var quantity = parseInt(select.value || '1', 10);
    if (!Number.isFinite(quantity) || quantity < 1) quantity = 1;
    var template = quantity === 1 ? button.dataset.singular : button.dataset.plural;
    button.textContent = String(template || 'Claim %d Guest Passes').replace('%d', String(quantity));
  }

  document.addEventListener('change', function (event) {
    var target = event.target;
    if (target && target.matches && target.matches('[data-backstage-claim-quantity]')) {
      updateClaimButton(target);
    }
  });

  document.querySelectorAll('[data-backstage-claim-quantity]').forEach(updateClaimButton);
})();
