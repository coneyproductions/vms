(function () {
  function setSlotState(slot, enabled) {
    if (!slot) return;
    slot.hidden = !enabled;
    slot.querySelectorAll('input, select, textarea').forEach(function (field) {
      field.disabled = !enabled;
    });
  }

  function clampSize(size, maxParty) {
    if (!Number.isFinite(size) || size < 1) size = 1;
    if (size > maxParty) size = maxParty;
    return size;
  }

  function getLegendText(anchorName, slotNumber) {
    if (slotNumber <= 1) {
      return 'Guest Pass';
    }
    var base = anchorName ? anchorName + "’s" : "Guest Pass";
    return base + ' +' + String(slotNumber - 1);
  }

  function updateLegends(form, slots) {
    var anchorInput = form.querySelector('[data-vms-guest-anchor-name]');
    var anchorName = anchorInput ? String(anchorInput.value || '').trim() : '';
    slots.forEach(function (slot) {
      var slotNumber = parseInt(slot.getAttribute('data-vms-guest-slot') || '0', 10);
      var legend = slot.querySelector('[data-vms-guest-legend]');
      if (!legend || !Number.isFinite(slotNumber) || slotNumber < 1) return;
      legend.textContent = getLegendText(anchorName, slotNumber);
    });
  }

  function bindQtyControls(form, sizeInput, maxParty, sync) {
    var dec = form.querySelector('[data-vms-guest-qty-decrease]');
    var inc = form.querySelector('[data-vms-guest-qty-increase]');
    function step(delta) {
      var size = clampSize(parseInt(sizeInput.value || '1', 10) + delta, maxParty);
      sizeInput.value = String(size);
      sync();
    }
    if (dec) {
      dec.addEventListener('click', function () { step(-1); });
    }
    if (inc) {
      inc.addEventListener('click', function () { step(1); });
    }
  }

  function bindForm(form) {
    if (!form || form.__vmsGuestBound) return;
    form.__vmsGuestBound = true;
    var sizeInput = form.querySelector('[data-vms-guest-party-size]');
    if (!sizeInput) return;
    var slots = Array.prototype.slice.call(form.querySelectorAll('[data-vms-guest-slot]'));
    var maxParty = parseInt(form.getAttribute('data-max-party') || String(slots.length || 1), 10);
    if (!Number.isFinite(maxParty) || maxParty < 1) maxParty = Math.max(slots.length, 1);
    var anchorInput = form.querySelector('[data-vms-guest-anchor-name]');

    function sync() {
      var size = clampSize(parseInt(sizeInput.value || '1', 10), maxParty);
      sizeInput.value = String(size);
      slots.forEach(function (slot) {
        var slotNumber = parseInt(slot.getAttribute('data-vms-guest-slot') || '0', 10);
        setSlotState(slot, Number.isFinite(slotNumber) && slotNumber > 0 && slotNumber <= size);
      });
      var primarySlot = slots.length ? slots[0] : null;
      if (primarySlot) {
        primarySlot.classList.toggle('has-companions', size > 1);
      }
      var dec = form.querySelector('[data-vms-guest-qty-decrease]');
      var inc = form.querySelector('[data-vms-guest-qty-increase]');
      if (dec) dec.disabled = size <= 1;
      if (inc) inc.disabled = size >= maxParty;
      updateLegends(form, slots);
    }

    bindQtyControls(form, sizeInput, maxParty, sync);
    sizeInput.addEventListener('input', sync);
    sizeInput.addEventListener('change', sync);
    if (anchorInput) {
      anchorInput.addEventListener('input', function () {
        updateLegends(form, slots);
      });
      anchorInput.addEventListener('change', function () {
        updateLegends(form, slots);
      });
    }
    sync();
  }

  function init(root) {
    (root || document).querySelectorAll('[data-vms-vendor-guest-form]').forEach(bindForm);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { init(document); });
  } else {
    init(document);
  }
})();
