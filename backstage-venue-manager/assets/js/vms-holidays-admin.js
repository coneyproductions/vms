(function () {
  var toggle = document.getElementById('vms_holidays_select_all');
  var boxes;
  var i;

  if (!toggle) {
    return;
  }

  if (toggle.dataset.vmsHolidaysBulkBound === '1') {
    return;
  }
  toggle.dataset.vmsHolidaysBulkBound = '1';

  toggle.addEventListener('change', function () {
    boxes = document.querySelectorAll('.vms_holidays_row_cb');
    for (i = 0; i < boxes.length; i++) {
      boxes[i].checked = toggle.checked;
    }
  });
})();
