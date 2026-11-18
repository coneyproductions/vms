(function () {
  function clampPartySize(input) {
    if (!input) return;
    var min = parseInt(input.getAttribute('min') || '1', 10);
    var max = parseInt(input.getAttribute('max') || '1', 10);
    var value = parseInt(input.value || String(min), 10);
    if (isNaN(value)) value = min;
    if (value < min) value = min;
    if (value > max) value = max;
    input.value = String(value);
  }

  function step(input, delta) {
    if (!input) return;
    var value = parseInt(input.value || '1', 10);
    if (isNaN(value)) value = 1;
    input.value = String(value + delta);
    clampPartySize(input);
  }

  document.addEventListener('click', function (event) {
    var dec = event.target && event.target.closest ? event.target.closest('[data-vms-pass-party-decrease]') : null;
    var inc = event.target && event.target.closest ? event.target.closest('[data-vms-pass-party-increase]') : null;
    if (!dec && !inc) return;
    var wrap = (dec || inc).closest('.vms-pass-number-control');
    var input = wrap ? wrap.querySelector('[data-vms-pass-party-size]') : null;
    step(input, inc ? 1 : -1);
  });

  document.addEventListener('input', function (event) {
    var target = event.target;
    if (target && target.matches && target.matches('[data-vms-pass-party-size]')) clampPartySize(target);
  });

  document.addEventListener('change', function (event) {
    var target = event.target;
    if (target && target.matches && target.matches('[data-vms-pass-party-size]')) clampPartySize(target);
  });
})();
