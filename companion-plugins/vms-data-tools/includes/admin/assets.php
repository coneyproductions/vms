<?php
defined('ABSPATH') || exit;

add_action('admin_enqueue_scripts', 'vms_dt_admin_enqueue_assets');

function vms_dt_admin_enqueue_assets(string $hook_suffix): void
{
	$page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';

	$allowed = [
		vms_dt_get_menu_slug_data_tools(),
		vms_dt_get_menu_slug_events_import(),
		vms_dt_get_menu_slug_vendor_import(),
		vms_dt_get_menu_slug_vendor_invites(),
		vms_dt_get_menu_slug_holidays_import(),
		vms_dt_get_menu_slug_payables_export(),
		vms_dt_get_menu_slug_ticket_revenue_export(),
		vms_dt_get_menu_slug_square_ticket_merge(),
		vms_dt_get_menu_slug_revenue_intelligence(),
		vms_dt_get_menu_slug_reporting_single_event(),
		vms_dt_get_menu_slug_reporting_compare_events(),
		vms_dt_get_menu_slug_reporting_season_year(),
		vms_dt_get_menu_slug_reporting_performer_payouts(),
		vms_dt_get_menu_slug_reporting_profitability(),
		vms_dt_get_menu_slug_reporting_ticket_pace(),
	];

	if (!in_array($page, $allowed, true)) {
		return;
	}

	wp_enqueue_style(
		'vms-dt-admin',
		VMS_DT_PLUGIN_URL . 'includes/admin/admin.css',
		array(),
		VMS_DT_VERSION
	);

	if (in_array($page, array(
		vms_dt_get_menu_slug_revenue_intelligence(),
		vms_dt_get_menu_slug_reporting_single_event(),
		vms_dt_get_menu_slug_reporting_compare_events(),
		vms_dt_get_menu_slug_reporting_season_year(),
		vms_dt_get_menu_slug_reporting_performer_payouts(),
		vms_dt_get_menu_slug_reporting_profitability(),
		vms_dt_get_menu_slug_reporting_ticket_pace(),
	), true)) {
		wp_enqueue_style(
			'vms-dt-admin-rich',
			VMS_DT_PLUGIN_URL . 'includes/admin/vms-dt-admin.css',
			array('vms-dt-admin'),
			VMS_DT_VERSION
		);
	}

	if ($page === vms_dt_get_menu_slug_vendor_invites()) {
		wp_enqueue_style(
			'vms-dt-vendor-invites',
			VMS_DT_PLUGIN_URL . 'includes/admin/vendor-invites.css',
			array('vms-dt-admin'),
			VMS_DT_VERSION
		);
	}

	wp_enqueue_script('wp-api-fetch');

	static $nonce_middleware_added = false;
	if ($nonce_middleware_added) {
		return;
	}
	$nonce_middleware_added = true;

	wp_add_inline_script(
		'wp-api-fetch',
		'wp.apiFetch.use( wp.apiFetch.createNonceMiddleware("' . esc_js(wp_create_nonce('wp_rest')) . '") );',
		'after'
	);

	if (in_array($page, array(
		vms_dt_get_menu_slug_reporting_single_event(),
		vms_dt_get_menu_slug_reporting_compare_events(),
		vms_dt_get_menu_slug_reporting_season_year(),
		vms_dt_get_menu_slug_reporting_performer_payouts(),
		vms_dt_get_menu_slug_reporting_profitability(),
		vms_dt_get_menu_slug_reporting_ticket_pace(),
	), true)) {
		$reporting_js = <<<'JS'
(function(){
  function getDirectHeader(card){
    for (var i = 0; i < card.children.length; i++) {
      if (card.children[i].classList && card.children[i].classList.contains('vms-dt-card-head')) {
        return card.children[i];
      }
    }
    return null;
  }
  function openCard(card){
    if (!card) return;
    if (typeof card._vmsSetOpen === 'function') {
      card._vmsSetOpen(true);
    }
  }
  function initCard(card){
    if (!card || card.dataset.vmsAccordionReady === '1') return;
    var header = getDirectHeader(card);
    if (!header) return;
    var body = document.createElement('div');
    body.className = 'vms-dt-accordion__body';
    var children = Array.prototype.slice.call(card.children);
    children.forEach(function(child){
      if (child !== header) body.appendChild(child);
    });
    card.appendChild(body);
    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'button-link vms-dt-accordion__toggle';
    toggle.textContent = 'Show table';
    header.appendChild(toggle);
    function setOpen(open){
      card.classList.toggle('is-collapsed', !open);
      body.hidden = !open;
      toggle.textContent = open ? 'Hide table' : 'Show table';
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    toggle.addEventListener('click', function(e){
      e.preventDefault();
      setOpen(body.hidden);
    });
    card._vmsSetOpen = setOpen;
    card.dataset.vmsAccordionReady = '1';
    setOpen(false);
  }
  function openTarget(targetId){
    if (!targetId) return;
    var target = document.getElementById(targetId);
    if (!target) return;
    var card = target.classList.contains('vms-dt-table-card') ? target : target.closest('.vms-dt-table-card');
    openCard(card);
    window.requestAnimationFrame(function(){
      target.scrollIntoView({behavior:'smooth', block:'start'});
    });
  }
  document.addEventListener('DOMContentLoaded', function(){
    Array.prototype.forEach.call(document.querySelectorAll('.vms-dt-table-card'), initCard);
    document.addEventListener('click', function(e){
      var link = e.target.closest('[data-vms-open-target]');
      if (!link) return;
      var targetId = link.getAttribute('data-vms-open-target');
      if (!targetId) return;
      e.preventDefault();
      openTarget(targetId);
      if (history && history.replaceState) history.replaceState(null, '', '#' + targetId);
    });
    if (window.location.hash) {
      var initial = window.location.hash.replace(/^#/, '');
      if (initial) openTarget(initial);
    }
  });
})();
JS;
		wp_add_inline_script('wp-api-fetch', $reporting_js, 'after');
	}
}
