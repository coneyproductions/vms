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

  function updateEventPreview(select) {
    var form = select && select.closest ? select.closest('form') : null;
    var preview = form ? form.parentElement.querySelector('[data-backstage-event-preview]') : null;
    var option = select && select.selectedOptions ? select.selectedOptions[0] : null;
    if (!preview || !option) return;
    var title = preview.querySelector('[data-backstage-event-title]');
    var date = preview.querySelector('[data-backstage-event-date]');
    var image = preview.querySelector('[data-backstage-event-image]');
    var selected = String(option.value || '') !== '';
    preview.classList.toggle('is-neutral', !selected);
    if (title) title.textContent = selected ? String(option.dataset.eventTitle || option.textContent || '') : String(preview.dataset.neutralTitle || '');
    if (date) {
      date.textContent = selected ? String(option.dataset.eventDate || '') : '';
      date.hidden = !selected || date.textContent === '';
    }
    if (image) {
      var imageUrl = selected ? String(option.dataset.eventImage || '') : '';
      if (imageUrl === '') image.removeAttribute('src');
      else image.src = imageUrl;
      image.alt = selected ? String(option.dataset.eventAlt || option.dataset.eventTitle || '') : '';
      image.hidden = imageUrl === '';
    }
  }

  document.addEventListener('change', function (event) {
    var target = event.target;
    if (target && target.matches && target.matches('[data-backstage-claim-quantity]')) {
      updateClaimButton(target);
    }
    if (target && target.matches && target.matches('[data-backstage-event-select]')) {
      updateEventPreview(target);
    }
  });

  document.querySelectorAll('[data-backstage-claim-quantity]').forEach(updateClaimButton);
  document.querySelectorAll('[data-backstage-event-select]').forEach(updateEventPreview);
})();
