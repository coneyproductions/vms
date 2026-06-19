(function(){
  function buildModal(){
    var existing = document.getElementById('vms-portal-calendar-modal');
    if (existing) return existing;
    var modal = document.createElement('div');
    modal.id = 'vms-portal-calendar-modal';
    modal.className = 'vms-portal-modal';
    modal.setAttribute('hidden', 'hidden');
    modal.innerHTML = '' +
      '<div class="vms-portal-modal__backdrop" data-vms-modal-close="1"></div>' +
      '<div class="vms-portal-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="vms-portal-modal-title">' +
        '<button type="button" class="vms-portal-modal__close" aria-label="Close" data-vms-modal-close="1">×</button>' +
        '<div class="vms-portal-modal__media" hidden><img alt="" loading="lazy"></div>' +
        '<div class="vms-portal-modal__content">' +
          '<h3 class="vms-portal-modal__title" id="vms-portal-modal-title"></h3>' +
          '<p class="vms-portal-modal__venue" hidden></p>' +
          '<p class="vms-portal-modal__date" hidden></p>' +
          '<p class="vms-portal-modal__time" hidden></p>' +
          '<p class="vms-portal-modal__excerpt" hidden></p>' +
          '<div class="vms-portal-modal__actions"></div>' +
        '</div>' +
      '</div>';
    document.body.appendChild(modal);
    return modal;
  }
  function setText(el, value){
    if (!el) return;
    var text = String(value || '').trim();
    el.textContent = text;
    if (text) el.removeAttribute('hidden'); else el.setAttribute('hidden', 'hidden');
  }
  function renderAction(actions, label, url, primary){
    if (!actions) return;
    label = String(label || '').trim();
    url = String(url || '').trim();
    if (!label || !url) return;
    var a = document.createElement('a');
    a.className = primary ? 'button button-primary' : 'button';
    a.href = url;
    if (/^https?:/i.test(url)) {
      a.target = '_blank';
      a.rel = 'noopener noreferrer';
    }
    a.textContent = label;
    actions.appendChild(a);
  }
  function openModal(trigger){
    var modal = buildModal();
    var media = modal.querySelector('.vms-portal-modal__media');
    var img = media ? media.querySelector('img') : null;
    var title = modal.querySelector('.vms-portal-modal__title');
    var venue = modal.querySelector('.vms-portal-modal__venue');
    var date = modal.querySelector('.vms-portal-modal__date');
    var time = modal.querySelector('.vms-portal-modal__time');
    var excerpt = modal.querySelector('.vms-portal-modal__excerpt');
    var actions = modal.querySelector('.vms-portal-modal__actions');
    if (actions) actions.innerHTML = '';
    setText(title, trigger.getAttribute('data-vms-modal-title'));
    setText(venue, trigger.getAttribute('data-vms-modal-venue'));
    setText(date, trigger.getAttribute('data-vms-modal-date'));
    setText(time, trigger.getAttribute('data-vms-modal-time'));
    setText(excerpt, trigger.getAttribute('data-vms-modal-excerpt'));
    var image = String(trigger.getAttribute('data-vms-modal-image') || '').trim();
    if (media && img) {
      if (image) {
        img.src = image;
        media.removeAttribute('hidden');
      } else {
        img.removeAttribute('src');
        media.setAttribute('hidden', 'hidden');
      }
    }
    renderAction(actions, trigger.getAttribute('data-vms-modal-primary-label'), trigger.getAttribute('data-vms-modal-primary-url'), true);
    renderAction(actions, trigger.getAttribute('data-vms-modal-secondary-label'), trigger.getAttribute('data-vms-modal-secondary-url'), false);
    modal.removeAttribute('hidden');
    modal.__lastTrigger = trigger;
  }
  function closeModal(){
    var modal = document.getElementById('vms-portal-calendar-modal');
    if (!modal) return;
    modal.setAttribute('hidden', 'hidden');
    if (modal.__lastTrigger && typeof modal.__lastTrigger.focus === 'function') {
      modal.__lastTrigger.focus();
    }
  }
  window.VMSPortalCalendarModalOpen = function(trigger){
    return openModal(trigger);
  };

  document.addEventListener('click', function(e){
    var trigger = e.target.closest('.vms-av-event-trigger');
    if (trigger) {
      e.preventDefault();
      e.stopPropagation();
      openModal(trigger);
      return;
    }
    if (e.target.closest('[data-vms-modal-close="1"]')) {
      e.preventDefault();
      closeModal();
    }
  });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeModal(); });
})();
