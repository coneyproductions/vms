<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$ticketingRulesPath = $pluginRoot . '/includes/integrations/ticketing-rules-v2.php';
$frontBundlePath = $pluginRoot . '/assets/vms-ticketing-front.js';
$sidecarBundlePath = $pluginRoot . '/assets/vms-ticketing-front-server-controls.js';

$ticketingRulesSource = file_get_contents($ticketingRulesPath);
$frontBundleSource = file_get_contents($frontBundlePath);
$sidecarBundleSource = file_get_contents($sidecarBundlePath);

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$assert(is_string($ticketingRulesSource) && $ticketingRulesSource !== '', 'Ticketing Rules V2 source should be readable.');
$assert(is_string($frontBundleSource) && $frontBundleSource !== '', 'Ticketing front bundle source should be readable.');
$assert(is_string($sidecarBundleSource) && $sidecarBundleSource !== '', 'Ticketing server-controls sidecar source should remain readable for duplication audit context.');

$assert(strpos($ticketingRulesSource, '<script>') === false, 'Ticketing Rules V2 should not emit a raw executable <script> block.');
$assert(strpos($ticketingRulesSource, '</script>') === false, 'Ticketing Rules V2 should not emit a raw executable closing </script> tag.');
$assert(strpos($ticketingRulesSource, 'window.__vmsInlineTicketingController') === false, 'Ticketing Rules V2 should not retain the removed inline controller bootstrap.');
$assert(strpos($ticketingRulesSource, 'data-vms-inline-controller-owner') === false, 'Ticketing Rules V2 should not retain the removed inline-controller ownership marker.');
$assert(stripos($ticketingRulesSource, 'onclick=') === false, 'Ticketing Rules V2 should not introduce inline onclick handlers.');
$assert(stripos($ticketingRulesSource, 'onchange=') === false, 'Ticketing Rules V2 should not introduce inline onchange handlers.');
$assert(stripos($ticketingRulesSource, 'onsubmit=') === false, 'Ticketing Rules V2 should not introduce inline onsubmit handlers.');

$requiredMarkupSnippets = array(
	'data-vms-tec-event-id',
	'data-vms-event-plan-id',
	'data-vms-ga-product-id',
	'data-vms-qualifying-ticket-product-ids',
	'data-vms-prior-qualifying-qty',
	'data-vms-prior-pool-qty',
	'data-vms-cart-ga-qty',
	'data-vms-cart-pool-qty',
	'data-vms-render-mode="server_controls"',
	'data-vms-server-stepper="1"',
	'data-vms-selector-mode',
	'data-vms-can-add',
	'data-vms-pool-key',
	'data-vms-pool-max',
	'data-vms-pool-min-ga',
	'data-vms-max-qty',
	'data-vms-initial-note',
	'vms-addon-input',
	'vms-addon-minus',
	'vms-addon-plus',
	'admin_url(\'admin-ajax.php?action=vms_ticketing_v2_atomic_add_to_cart\')',
	'wp_create_nonce(\'vms_ticketing_v2_atomic_add_to_cart\')',
	'\'atomicAddUrl\' => admin_url(\'admin-ajax.php?action=vms_ticketing_v2_atomic_add_to_cart\')',
	'\'atomicAddNonce\' => wp_create_nonce(\'vms_ticketing_v2_atomic_add_to_cart\')',
	'\'cartUrl\' => function_exists(\'wc_get_cart_url\') ? wc_get_cart_url() : home_url(\'/cart/\')',
	'\'tecEventId\' => (int) $tec_event_id',
	'\'eventPlanId\' => (int) $plan_id_for_event',
);

foreach ($requiredMarkupSnippets as $snippet) {
	$assert(strpos($ticketingRulesSource, $snippet) !== false, 'Ticketing Rules V2 should retain required server-rendered configuration: ' . $snippet);
}

$assert(strpos($ticketingRulesSource, 'if (!$is_event && !$is_cart && !$is_checkout) return;') !== false, 'Ticketing front bundle enqueue should stay scoped away from unrelated requests.');
$assert(strpos($ticketingRulesSource, "wp_enqueue_script(\n        'vms-ticketing-front'") !== false, 'Ticketing Rules V2 should continue to enqueue the main ticketing front bundle.');
$assert(strpos($ticketingRulesSource, "wp_enqueue_script(\n        'vms-ticketing-front-server-controls'") === false, 'Ticketing Rules V2 should not introduce a second competing server-controls enqueue.');
$assert(strpos($ticketingRulesSource, 'assets/vms-ticketing-front-server-controls.js') === false, 'Ticketing Rules V2 should not retain the unused server-controls sidecar path after this remediation.');

$requiredFrontBundleSnippets = array(
	'#vms-reserved-addons.vms-entitlements-block',
	'buildServerControlsState(sourceBlock, form)',
	'activateServerControlsState(state)',
	'data-vms-server-controls-active',
	'data-vms-inline-controller-active',
	'data-vms-addons-mounted',
	'[data-vms-server-stepper="1"]',
	'cfg.atomicAddUrl',
	'cfg.atomicAddNonce',
	'cfg.cartUrl',
	'data-vms-prior-pool-qty',
	'data-vms-cart-pool-qty',
	'data-vms-qualifying-ticket-product-ids',
	'ticket_lines',
	'addon_lines',
	'hideDisabledTicketRows(state)',
	'safeParseJson(sourceBlock.getAttribute(\'data-vms-prior-pool-qty\'), {})',
	'safeParseJson(sourceBlock.getAttribute(\'data-vms-cart-pool-qty\'), {})',
	'if (!cfg.atomicAddUrl || !cfg.atomicAddNonce)',
	'Added to cart. Redirecting',
	'Could not add items to cart.',
);

foreach ($requiredFrontBundleSnippets as $snippet) {
	$assert(strpos($frontBundleSource, $snippet) !== false, 'Ticketing front bundle should retain server-controls behavior marker: ' . $snippet);
}

$assert(strpos($frontBundleSource, 'repairMountedState(bundle.state)') !== false, 'Ticketing front bundle should repair an existing mounted state before attempting a second initialization pass.');
$assert(strpos($frontBundleSource, 'data-vms-server-controls-active') !== false, 'Ticketing front bundle should mark the mounted server-controls block as active.');
$assert(strpos($frontBundleSource, 'data-vms-ticketing-rewrite') !== false, 'Ticketing front bundle should mark the ticket form as rewritten to prevent duplicate initialization.');
$assert(strpos($frontBundleSource, 'if (!state || !state.addons || !state.addons.length)') !== false, 'Ticketing front bundle should fail safely when valid add-on controls are absent.');
$assert(strpos($frontBundleSource, 'bindNativeQtyObservers(state)') !== false, 'Ticketing front bundle should keep native ticket quantity observers wired into the server-controls flow.');
$assert(strpos($frontBundleSource, 'scheduleRefresh(state)') !== false, 'Ticketing front bundle should keep refresh scheduling for changing native ticket quantities.');
$assert(strpos($frontBundleSource, 'new MutationObserver') !== false, 'Ticketing front bundle should keep mutation-observer based refresh wiring.');
$assert(strpos($frontBundleSource, 'disabledTicketMap') !== false, 'Ticketing front bundle should preserve disabled-ticket handling.');
$assert(strpos($frontBundleSource, 'poolMax') !== false && strpos($frontBundleSource, 'minGa') !== false && strpos($frontBundleSource, 'maxQty') !== false, 'Ticketing front bundle should preserve reserved add-on limit handling.');

$assert(strpos($sidecarBundleSource, 'data-vms-server-controls-active') !== false, 'Duplication audit reference sidecar should still contain the historical server-controls initializer for comparison.');

fwrite(STDOUT, "Ticketing server-controls inline JS remediation OK.\n");
