(function () {
  'use strict';

  function $(root, selector) {
    if (!root) return null;
    return root.querySelector(selector);
  }

  function setNotice(box, type, msg, linkHref) {
    var notice = $(box, '.vms-tasks-event-plan-addtask__notice');
    if (!notice) return;

    notice.classList.remove('notice-error', 'notice-success', 'notice-warning');
    notice.classList.add(type === 'success' ? 'notice-success' : 'notice-error');
    notice.innerHTML = '';

    var p = document.createElement('p');
    p.appendChild(document.createTextNode(msg || ''));

    if (linkHref) {
      p.appendChild(document.createTextNode(' '));
      var a = document.createElement('a');
      a.href = linkHref;
      a.target = '_blank';
      a.rel = 'noopener noreferrer';
      a.appendChild(document.createTextNode('Open Tasks')); // i18n not required for admin-only utility
      p.appendChild(a);
    }

    notice.appendChild(p);
    notice.style.display = 'block';
  }

  function clearNotice(box) {
    var notice = $(box, '.vms-tasks-event-plan-addtask__notice');
    if (!notice) return;
    notice.innerHTML = '';
    notice.style.display = 'none';
    notice.classList.remove('notice-error', 'notice-success', 'notice-warning');
  }

  function payloadFromBox(box) {
    var getVal = function (field) {
      var el = $(box, '[data-vms-tasks-field="' + field + '"]');
      if (!el) return '';
      if (el.type === 'checkbox') {
        return el.checked ? '1' : '';
      }
      return (el.value || '').trim();
    };

    return {
      action: 'vms_tasks_create_one_off_ajax',
      nonce: (box.getAttribute('data-vms-nonce') || '').trim(),
      event_id: (box.getAttribute('data-vms-event-id') || '').trim(),
      title: getVal('title'),
      instructions: getVal('instructions'),
      priority: getVal('priority') || 'normal',
      is_required: getVal('is_required') ? '1' : '',
      due_at_local: getVal('due_at_local'),
      assignment_mode: getVal('assignment_mode') || 'person',
      role_key: getVal('role_key'),
      assignee_user_id: getVal('assignee_user_id') || '0',
      assignment_locked: getVal('assignment_locked') ? '1' : '',
      make_repeatable_now: getVal('make_repeatable_now') ? '1' : '',
      repeatable_checklist_id: getVal('repeatable_checklist_id') || '0'
    };
  }

  function postAjax(data) {
    var body = new URLSearchParams();
    Object.keys(data).forEach(function (k) {
      body.append(k, data[k]);
    });

    // ajaxurl is injected by WordPress in wp-admin.
    return fetch(window.ajaxurl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: body.toString()
    }).then(function (res) {
      return res
        .json()
        .catch(function () {
          return null;
        })
        .then(function (json) {
          return { ok: res.ok, status: res.status, json: json };
        });
    });
  }

  document.addEventListener('click', function (e) {
    var btn = e.target && e.target.closest ? e.target.closest('[data-vms-tasks-action="create-one-off"]') : null;
    if (!btn) return;

    var box = btn.closest('.vms-tasks-event-plan-addtask');
    if (!box) return;

    clearNotice(box);

    var data = payloadFromBox(box);
    if (!data.title) {
      setNotice(box, 'error', 'Task title is required.');
      var titleEl = $(box, '[data-vms-tasks-field="title"]');
      if (titleEl && titleEl.focus) titleEl.focus();
      return;
    }

    btn.disabled = true;
    var spinner = $(box, '.spinner');
    if (spinner) spinner.classList.add('is-active');

    postAjax(data)
      .then(function (res) {
        var payload = res && res.json ? res.json : null;
        if (!payload || typeof payload !== 'object') {
          setNotice(box, 'error', 'Unexpected response from server.');
          return;
        }
        if (!payload.success) {
          var msg = (payload.data && payload.data.message) ? String(payload.data.message) : 'Task could not be created.';
          setNotice(box, 'error', msg);
          return;
        }

        var okMsg = (payload.data && payload.data.message) ? String(payload.data.message) : 'Task created.';
        var tasksUrl = (payload.data && payload.data.tasks_url) ? String(payload.data.tasks_url) : '';
        setNotice(box, 'success', okMsg, tasksUrl);

        // Clear only the title and instructions so repeated entry stays quick.
        var t = $(box, '[data-vms-tasks-field="title"]');
        if (t) t.value = '';
        var instr = $(box, '[data-vms-tasks-field="instructions"]');
        if (instr) instr.value = '';
      })
      .catch(function () {
        setNotice(box, 'error', 'Network error while creating task.');
      })
      .finally(function () {
        btn.disabled = false;
        if (spinner) spinner.classList.remove('is-active');
      });
  });
})();
