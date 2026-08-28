<?php
if (!defined('ABSPATH')) { exit; }

if (!function_exists('bvmgr_admin_reference_keys_map_page_slug')) {
  function bvmgr_admin_reference_keys_map_page_slug() {
    return 'vms-reference-keys-map';
  }
}

if (!function_exists('bvmgr_admin_reference_keys_map_enqueue_assets')) {
  function bvmgr_admin_reference_keys_map_enqueue_assets() {
    if (!current_user_can('manage_options')) {
      return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only reference-map routing only controls whether assets load for this admin page.
    $page = bvmgr_request_read_key($_GET, 'page');
    if ($page !== bvmgr_admin_reference_keys_map_page_slug()) {
      return;
    }

    $version = function_exists('bvmgr_asset_version')
      ? bvmgr_asset_version()
      : (defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : '');

    wp_enqueue_script(
      'bvmgr-reference-keys-map',
      BVMGR_PLUGIN_URL . 'assets/js/vms-reference-keys-map.js',
      array(),
      $version,
      true
    );
  }
}
add_action('admin_enqueue_scripts', 'bvmgr_admin_reference_keys_map_enqueue_assets', 50);

if (!function_exists('bvmgr_admin_reference_keys_map_page')) {
  function bvmgr_admin_reference_keys_map_page() {
    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to view this page.', 'backstage-venue-manager'));
    }

    // Load registry
    if (!function_exists('bvmgr_keys_map_registry')) {
      $registry_file = defined('VMS_PLUGIN_DIR')
        ? (VMS_PLUGIN_DIR . 'includes/core/registry/vms-keys-map.php')
        : (plugin_dir_path(__FILE__) . '../../core/registry/vms-keys-map.php');

      if (file_exists($registry_file)) {
        require_once $registry_file;
      }
    }

    $sections = function_exists('bvmgr_keys_map_registry') ? bvmgr_keys_map_registry() : array();

    $out = '';
    foreach ($sections as $sec) {
      $title = isset($sec['title']) ? $sec['title'] : '';
      $lines = isset($sec['lines']) && is_array($sec['lines']) ? $sec['lines'] : array();

      if ($title !== '') {
        $out .= $title . "\n";
      }
      foreach ($lines as $line) {
        $out .= $line . "\n";
      }
      $out .= "\n";
    }

    $out .= "Timestamp: " . gmdate('Y-m-d H:i') . " UTC\n";
    ?>
    <div class="wrap">
      <h1><?php echo esc_html__('Backstage Venue Manager Reference: Keys + Identifiers', 'backstage-venue-manager'); ?></h1>

      <p><?php echo esc_html__('Admin-only. Naming and key identifiers only. No runtime values are printed.', 'backstage-venue-manager'); ?></p>

      <p>
        <button
          class="button button-primary"
          type="button"
          id="vms-copy-keys-map"
          data-vms-copy-default-label="<?php echo esc_attr__('Copy to clipboard', 'backstage-venue-manager'); ?>"
          data-vms-copy-success-label="<?php echo esc_attr__('Copied', 'backstage-venue-manager'); ?>"
          data-vms-copy-failure-label="<?php echo esc_attr__('Copy failed', 'backstage-venue-manager'); ?>"
        >
          <?php echo esc_html__('Copy to clipboard', 'backstage-venue-manager'); ?>
        </button>
      </p>

      <textarea id="vms-keys-map-text" class="large-text code" rows="30" readonly><?php echo esc_textarea($out); ?></textarea>
    </div>
    <?php
  }
}
