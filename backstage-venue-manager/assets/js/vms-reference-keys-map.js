(function () {
  var button = document.getElementById('vms-copy-keys-map');
  var textarea = document.getElementById('vms-keys-map-text');
  var defaultLabel;
  var successLabel;
  var failureLabel;

  if (!button || !textarea) {
    return;
  }

  if (button.dataset.vmsKeysMapClipboardBound === '1') {
    return;
  }
  button.dataset.vmsKeysMapClipboardBound = '1';

  defaultLabel = button.getAttribute('data-vms-copy-default-label') || button.textContent || '';
  successLabel = button.getAttribute('data-vms-copy-success-label') || 'Copied';
  failureLabel = button.getAttribute('data-vms-copy-failure-label') || 'Copy failed';

  button.addEventListener('click', function () {
    textarea.focus();
    textarea.select();

    try {
      document.execCommand('copy');
      button.textContent = successLabel;
    } catch (error) {
      button.textContent = failureLabel;
    }

    if (button.__vmsKeysMapResetTimer) {
      window.clearTimeout(button.__vmsKeysMapResetTimer);
    }
    button.__vmsKeysMapResetTimer = window.setTimeout(function () {
      button.textContent = defaultLabel;
      button.__vmsKeysMapResetTimer = null;
    }, 1500);
  });
})();
