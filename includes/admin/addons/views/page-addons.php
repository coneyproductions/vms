<?php
defined('ABSPATH') || exit;
/** @var string $tab */
/** @var array $state */

$tabs = array(
	'installed' => __('Installed', 'backstage-venue-manager'),
	'discover' => __('Discover', 'backstage-venue-manager'),
	'licenses' => __('Licenses', 'backstage-venue-manager'),
	'updates' => __('Updates', 'backstage-venue-manager'),
	'support' => __('Support', 'backstage-venue-manager'),
);
?>
<div class="wrap vms-addons-wrap" data-vms-tour="addons.summary">
	<h1><?php esc_html_e('Premium Add-ons', 'backstage-venue-manager'); ?></h1>
	<div class="vms-addons-toolbar">
		<input id="vms-addons-search" data-vms-tour="addons.search" type="search" placeholder="<?php esc_attr_e('Search add-ons', 'backstage-venue-manager'); ?>" />
		<select id="vms-addons-sort">
			<option value="name"><?php esc_html_e('Sort: Name', 'backstage-venue-manager'); ?></option>
			<option value="status"><?php esc_html_e('Sort: Status', 'backstage-venue-manager'); ?></option>
		</select>
		<button class="button" id="vms-addons-refresh"><?php esc_html_e('Refresh', 'backstage-venue-manager'); ?></button>
	</div>

	<div class="vms-addons-summary-grid">
		<div class="vms-addons-summary-card">
			<div class="vms-addons-summary-label"><?php esc_html_e('Installed', 'backstage-venue-manager'); ?></div>
			<div class="vms-addons-summary-value" id="vms-addons-count-installed"><?php echo esc_html((string) ($state['counts']['installed'] ?? 0)); ?></div>
		</div>
		<div class="vms-addons-summary-card">
			<div class="vms-addons-summary-label"><?php esc_html_e('Updates', 'backstage-venue-manager'); ?></div>
			<div class="vms-addons-summary-value" id="vms-addons-count-updates"><?php echo esc_html((string) ($state['counts']['updates'] ?? 0)); ?></div>
		</div>
		<div class="vms-addons-summary-card">
			<div class="vms-addons-summary-label"><?php esc_html_e('Licenses Active', 'backstage-venue-manager'); ?></div>
			<div class="vms-addons-summary-value" id="vms-addons-count-licenses"><?php echo esc_html((string) ($state['counts']['licenses_active'] ?? 0)); ?></div>
		</div>
		<div class="vms-addons-summary-card">
			<div class="vms-addons-summary-label"><?php esc_html_e('System', 'backstage-venue-manager'); ?></div>
			<div class="vms-addons-summary-value" id="vms-addons-system-status"><?php echo esc_html(($state['health']['system_status'] ?? 'unknown') === 'all_good' ? __('All good', 'backstage-venue-manager') : __('Action needed', 'backstage-venue-manager')); ?></div>
		</div>
	</div>

	<nav class="nav-tab-wrapper vms-addons-tabs">
		<?php foreach ($tabs as $slug => $label) : ?>
			<a class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(array('page' => 'vms-addons', 'tab' => $slug), admin_url('admin.php'))); ?>"><?php echo esc_html($label); ?></a>
		<?php endforeach; ?>
	</nav>

	<div id="vms-addons-notice" class="notice vms-addons-notice is-dismissible"></div>
	<div id="vms-addons-root" data-tab="<?php echo esc_attr($tab); ?>"></div>

	<script type="application/json" id="vms-addons-state-json"><?php echo wp_json_encode($state); ?></script>
</div>
