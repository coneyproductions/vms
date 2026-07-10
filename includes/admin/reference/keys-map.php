<?php
if (!defined('ABSPATH')) { exit; }

function vms_admin_reference_keys_map_page() {
  if (!current_user_can('manage_options')) {
    wp_die(esc_html__('You do not have permission to view this page.', 'backstage-venue-manager'));
  }

  // Load registry
  if (!function_exists('vms_keys_map_registry')) {
    $registry_file = defined('VMS_PLUGIN_DIR')
      ? (VMS_PLUGIN_DIR . 'includes/core/registry/vms-keys-map.php')
      : (plugin_dir_path(__FILE__) . '../../core/registry/vms-keys-map.php');

    if (file_exists($registry_file)) {
      require_once $registry_file;
    }
  }

  $sections = function_exists('vms_keys_map_registry') ? vms_keys_map_registry() : array();

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
    <h1><?php echo esc_html__('VMS Reference: Keys + Identifiers', 'backstage-venue-manager'); ?></h1>

    <p><?php echo esc_html__('Admin-only. Naming and key identifiers only. No runtime values are printed.', 'backstage-venue-manager'); ?></p>

    <p>
      <button class="button button-primary" type="button" id="vms-copy-keys-map">
        <?php echo esc_html__('Copy to clipboard', 'backstage-venue-manager'); ?>
      </button>
    </p>

    <textarea id="vms-keys-map-text" class="large-text code" rows="30" readonly><?php echo esc_textarea($out); ?></textarea>

    <script>
      (function() {
        var btn = document.getElementById('vms-copy-keys-map');
        var ta = document.getElementById('vms-keys-map-text');
        if (!btn || !ta) { return; }

        btn.addEventListener('click', function() {
          ta.focus();
          ta.select();
          try {
            document.execCommand('copy');
            btn.textContent = 'Copied';
            window.setTimeout(function(){ btn.textContent = 'Copy to clipboard'; }, 1500);
          } catch (e) {
            btn.textContent = 'Copy failed';
            window.setTimeout(function(){ btn.textContent = 'Copy to clipboard'; }, 1500);
          }
        });
      })();
    </script>
  </div>
  <?php
}

 