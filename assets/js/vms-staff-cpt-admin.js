(function () {
  var fallbackConfig = {
    labels: {
      qualification: 'Qualification',
      authority: 'Authority',
      credentialNumber: 'Credential #',
      issueDate: 'Issue date',
      expiration: 'Expiration',
      status: 'Status',
      proofUrl: 'Proof URL',
      reviewNote: 'Review note / rejection reason',
      remove: 'Remove'
    },
    statusOptions: [
      { value: 'active', label: 'Approved' },
      { value: 'pending_verification', label: 'Pending Review' },
      { value: 'rejected', label: 'Rejected' },
      { value: 'expired', label: 'Expired' },
      { value: 'inactive', label: 'Inactive' }
    ]
  };

  function getConfig() {
    var config = window.vmsStaffCptAdmin || {};
    var labels = {};
    var key;

    for (key in fallbackConfig.labels) {
      if (Object.prototype.hasOwnProperty.call(fallbackConfig.labels, key)) {
        labels[key] = fallbackConfig.labels[key];
      }
    }

    if (config.labels && typeof config.labels === 'object') {
      for (key in config.labels) {
        if (Object.prototype.hasOwnProperty.call(config.labels, key) && typeof config.labels[key] === 'string') {
          labels[key] = config.labels[key];
        }
      }
    }

    return {
      labels: labels,
      statusOptions: Array.isArray(config.statusOptions) && config.statusOptions.length
        ? config.statusOptions
        : fallbackConfig.statusOptions
    };
  }

  function fieldName(idx, key) {
    return 'vms_staff_qualifications[' + idx + '][' + key + ']';
  }

  function createHidden(idx, key, value) {
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = fieldName(idx, key);
    input.value = value || '';
    return input;
  }

  function createLabelText(text) {
    var span = document.createElement('span');
    span.textContent = text;
    return span;
  }

  function createInputLabel(idx, key, labelText, type, className) {
    var label = document.createElement('label');
    var input = document.createElement('input');

    label.appendChild(createLabelText(labelText));

    input.type = type;
    input.name = fieldName(idx, key);
    input.value = '';
    if (className) {
      input.className = className;
    }

    label.appendChild(input);
    return label;
  }

  function createStatusLabel(idx, labelText, statusOptions) {
    var label = document.createElement('label');
    var select = document.createElement('select');
    var i;

    label.appendChild(createLabelText(labelText));
    select.name = fieldName(idx, 'status');

    for (i = 0; i < statusOptions.length; i++) {
      var optionData = statusOptions[i];
      var option = document.createElement('option');

      option.value = String(optionData.value || '');
      option.textContent = String(optionData.label || '');
      select.appendChild(option);
    }

    label.appendChild(select);
    return label;
  }

  function buildRow(idx, config) {
    var labels = config.labels;
    var statusOptions = config.statusOptions;
    var card = document.createElement('div');
    var primaryGrid = document.createElement('div');
    var secondaryGrid = document.createElement('div');
    var notesGrid = document.createElement('div');
    var proofLabel = document.createElement('label');
    var notesLabel = document.createElement('label');
    var actions = document.createElement('div');
    var removeButton = document.createElement('button');

    card.className = 'vms-staff-qualification-card';
    card.setAttribute('data-vms-staff-qualification-row', '1');

    card.appendChild(createHidden(idx, 'id', ''));
    card.appendChild(createHidden(idx, 'attachment_id', ''));
    card.appendChild(createHidden(idx, 'storage_kind', ''));
    card.appendChild(createHidden(idx, 'source', 'admin'));
    card.appendChild(createHidden(idx, 'submitted_by', ''));
    card.appendChild(createHidden(idx, 'submitted_at', ''));
    card.appendChild(createHidden(idx, 'reviewed_by', ''));
    card.appendChild(createHidden(idx, 'reviewed_at', ''));

    primaryGrid.className = 'vms-staff-qualification-card__grid vms-staff-qualification-card__grid--primary';
    primaryGrid.appendChild(createInputLabel(idx, 'name', labels.qualification, 'text', 'regular-text'));
    primaryGrid.appendChild(createInputLabel(idx, 'authority', labels.authority, 'text', 'regular-text'));
    primaryGrid.appendChild(createInputLabel(idx, 'credential_number', labels.credentialNumber, 'text', 'regular-text'));
    card.appendChild(primaryGrid);

    secondaryGrid.className = 'vms-staff-qualification-card__grid vms-staff-qualification-card__grid--secondary';
    secondaryGrid.appendChild(createInputLabel(idx, 'issue_date', labels.issueDate, 'date', ''));
    secondaryGrid.appendChild(createInputLabel(idx, 'expiration_date', labels.expiration, 'date', ''));
    secondaryGrid.appendChild(createStatusLabel(idx, labels.status, statusOptions));

    proofLabel.className = 'vms-staff-qualification-card__proof';
    proofLabel.appendChild(createLabelText(labels.proofUrl));
    proofLabel.appendChild(createInputLabel(idx, 'proof_url', '', 'url', 'regular-text').querySelector('input'));
    secondaryGrid.appendChild(proofLabel);
    card.appendChild(secondaryGrid);

    notesGrid.className = 'vms-staff-qualification-card__grid vms-staff-qualification-card__grid--notes';

    notesLabel.className = 'vms-staff-qualification-card__notes';
    notesLabel.appendChild(createLabelText(labels.reviewNote));
    notesLabel.appendChild(createInputLabel(idx, 'notes', '', 'text', 'regular-text').querySelector('input'));
    notesGrid.appendChild(notesLabel);

    actions.className = 'vms-staff-qualification-card__actions';
    removeButton.type = 'button';
    removeButton.className = 'button vms-staff-qualification-remove';
    removeButton.textContent = labels.remove;
    actions.appendChild(removeButton);
    notesGrid.appendChild(actions);

    card.appendChild(notesGrid);

    return card;
  }

  function init() {
    var addBtn = document.getElementById('vms-staff-qualification-add');
    var wrap = document.getElementById('vms-staff-qualifications-list');
    var config = getConfig();

    if (!addBtn || !wrap) {
      return;
    }

    if (wrap.dataset.vmsStaffQualificationBound === '1') {
      return;
    }
    wrap.dataset.vmsStaffQualificationBound = '1';

    addBtn.addEventListener('click', function () {
      var idx = wrap.querySelectorAll('[data-vms-staff-qualification-row="1"]').length;
      wrap.appendChild(buildRow(idx, config));
    });

    wrap.addEventListener('click', function (event) {
      var btn = event.target.closest('.vms-staff-qualification-remove');
      var rows;
      var i;
      var sel;
      var row;

      if (!btn) {
        return;
      }

      event.preventDefault();
      rows = wrap.querySelectorAll('[data-vms-staff-qualification-row="1"]');
      if (rows.length <= 1) {
        if (!rows[0]) {
          return;
        }

        rows[0].querySelectorAll('input').forEach(function (input) {
          input.value = '';
        });
        sel = rows[0].querySelector('select');
        if (sel) {
          sel.value = 'active';
        }
        return;
      }

      row = btn.closest('[data-vms-staff-qualification-row="1"]');
      if (row) {
        row.remove();
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
