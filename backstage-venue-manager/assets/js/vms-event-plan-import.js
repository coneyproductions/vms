(function () {
  var form = document.getElementById('vms-epcsv-commit-form');
  var checks;
  var scopeSelected;
  var scopeAll;
  var countNode;
  var btnAll;
  var btnClear;
  var selectedRequiredMessage;

  if (!form) {
    return;
  }

  if (form.dataset.vmsEventPlanImportBound === '1') {
    return;
  }
  form.dataset.vmsEventPlanImportBound = '1';

  checks = Array.prototype.slice.call(form.querySelectorAll('.vms-epcsv-row-check'));
  scopeSelected = form.querySelector('input[name="commit_scope"][value="selected"]');
  scopeAll = form.querySelector('input[name="commit_scope"][value="all"]');
  countNode = document.getElementById('vms-epcsv-selected-count');
  btnAll = document.getElementById('vms-epcsv-select-all');
  btnClear = document.getElementById('vms-epcsv-clear-all');
  selectedRequiredMessage = form.getAttribute('data-vms-selected-required-message') || 'Select at least one eligible row before committing selected rows.';

  function updateCount() {
    var count = 0;

    checks.forEach(function (checkbox) {
      if (checkbox.checked) {
        count++;
      }
    });

    if (countNode) {
      countNode.textContent = String(count);
    }

    return count;
  }

  if (btnAll) {
    btnAll.addEventListener('click', function () {
      checks.forEach(function (checkbox) {
        checkbox.checked = true;
      });
      updateCount();
    });
  }

  if (btnClear) {
    btnClear.addEventListener('click', function () {
      checks.forEach(function (checkbox) {
        checkbox.checked = false;
      });
      updateCount();
    });
  }

  checks.forEach(function (checkbox) {
    checkbox.addEventListener('change', updateCount);
  });

  form.addEventListener('submit', function (event) {
    var selectedCount = updateCount();

    if (scopeSelected && scopeSelected.checked && selectedCount === 0) {
      event.preventDefault();
      window.alert(selectedRequiredMessage);
      return;
    }

    if (scopeAll && scopeAll.checked) {
      return;
    }
  });

  updateCount();
})();
