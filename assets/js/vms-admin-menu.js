(function () {
  function getAddDispatchBadgeConfig() {
    var root = window.BVMGR_ADMIN_MENU && window.BVMGR_ADMIN_MENU.addDispatchBadge
      ? window.BVMGR_ADMIN_MENU.addDispatchBadge
      : null;
    var pendingCount;

    if (!root) {
      return null;
    }

    pendingCount = parseInt(root.pendingCount || '0', 10);
    if (!(pendingCount > 0)) {
      return null;
    }

    return {
      pendingCount: pendingCount
    };
  }

  function buildAddDispatchBadgeMarkup(pendingCount) {
    return ' <span class="awaiting-mod vms-add-dispatch-alert-badge"><span class="pending-count">' + String(pendingCount) + '</span></span>';
  }

  function applyBadge(selector, markup) {
    var nodes = document.querySelectorAll(selector);
    var i;
    var el;

    if (!nodes.length) {
      return;
    }

    for (i = 0; i < nodes.length; i++) {
      el = nodes[i];
      if (!el || el.innerHTML.indexOf('vms-add-dispatch-alert-badge') !== -1) {
        continue;
      }
      el.insertAdjacentHTML('beforeend', markup);
    }
  }

  function initAddDispatchBadge() {
    var config = getAddDispatchBadgeConfig();
    var markup;

    if (!config) {
      return;
    }

    markup = buildAddDispatchBadgeMarkup(config.pendingCount);
    if (!markup) {
      return;
    }

    applyBadge('#toplevel_page_vms-dashboard > a .wp-menu-name', markup);
    applyBadge('#toplevel_page_vms-dashboard .wp-submenu li a[href*="page=vms-add-dispatch"]', markup);
    applyBadge('#toplevel_page_vms-dashboard .wp-submenu li.current a[href*="page=vms-add-dispatch"]', markup);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAddDispatchBadge);
  } else {
    initAddDispatchBadge();
  }
}());
