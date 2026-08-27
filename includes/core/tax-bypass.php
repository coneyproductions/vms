<?php
defined('ABSPATH') || exit;

/**
 * Core: Temporary Tax/W-9 Compliance Bypass helpers.
 *
 * Meta keys (stored on vendor/staff post):
 *  _vms_tax_bypass_enabled
 *  _vms_tax_bypass_until   (Y-m-d)
 *  _vms_tax_bypass_reason
 *  _vms_tax_bypass_set_by  (user id)
 *  _vms_tax_bypass_set_at  (Y-m-d H:i:s in site timezone)
 */

if (!function_exists('bvmgr_tax_bypass_supported_post_types')) {
  function bvmgr_tax_bypass_supported_post_types(): array
  {
    return array('vms_vendor', 'vms_staff');
  }
}

if (!function_exists('bvmgr_tax_bypass_key')) {
  function bvmgr_tax_bypass_key(string $field): string
  {
    if (function_exists('bvmgr_meta_key')) {
      $k = (string) bvmgr_meta_key('vendor', $field);
      if ($k !== '') return $k;
    }

    $fallback = array(
      'tax_bypass_enabled' => '_vms_tax_bypass_enabled',
      'tax_bypass_until'   => '_vms_tax_bypass_until',
      'tax_bypass_reason'  => '_vms_tax_bypass_reason',
      'tax_bypass_set_by'  => '_vms_tax_bypass_set_by',
      'tax_bypass_set_at'  => '_vms_tax_bypass_set_at',
    );

    return isset($fallback[$field]) ? (string) $fallback[$field] : '';
  }
}

if (!function_exists('bvmgr_get_tax_bypass_status')) {
  function bvmgr_get_tax_bypass_status(int $post_id): array
  {
    $post_id = absint($post_id);
    if ($post_id <= 0) {
      return array(
        'enabled' => false,
        'until' => '',
        'reason' => '',
        'expired' => false,
        'days_left' => 0,
        'is_active' => false,
      );
    }

    $k_enabled = bvmgr_tax_bypass_key('tax_bypass_enabled');
    $k_until   = bvmgr_tax_bypass_key('tax_bypass_until');
    $k_reason  = bvmgr_tax_bypass_key('tax_bypass_reason');

    $enabled = (string) get_post_meta($post_id, (string) $k_enabled, true);
    $enabled = ($enabled === '1' || $enabled === 'yes' || $enabled === 'true');

    $until  = trim((string) get_post_meta($post_id, (string) $k_until, true));
    $reason = trim((string) get_post_meta($post_id, (string) $k_reason, true));

    $expired = false;
    $days_left = 0;

    if ($enabled && $until !== '') {
      try {
        $tz = wp_timezone();
        $today = new DateTimeImmutable('today', $tz);
        $until_dt = new DateTimeImmutable($until, $tz);

        // If until date is before today, it's expired.
        if ($until_dt < $today) {
          $expired = true;
          $days_left = 0;
        } else {
          $diff = $today->diff($until_dt);
          $days_left = (int) $diff->days;
          $expired = false;
        }
      } catch (Exception $e) {
        $expired = true;
        $days_left = 0;
      }
    }

    $is_active = ($enabled && !$expired && $until !== '');

    return array(
      'enabled' => (bool) $enabled,
      'until' => $until,
      'reason' => $reason,
      'expired' => (bool) $expired,
      'days_left' => (int) $days_left,
      // Backward compatibility for older UI code that expects is_active.
      'is_active' => (bool) $is_active,
    );
  }
}

if (!function_exists('bvmgr_tax_bypass_is_active')) {
  function bvmgr_tax_bypass_is_active(int $post_id): bool
  {
    $st = bvmgr_get_tax_bypass_status($post_id);
    return !empty($st['is_active']);
  }
}

if (!function_exists('bvmgr_tax_bypass_warning_label')) {
  function bvmgr_tax_bypass_warning_label(int $post_id): string
  {
    $st = bvmgr_get_tax_bypass_status($post_id);
    if (empty($st['enabled'])) return '';

    $until = trim((string) ($st['until'] ?? ''));
    if (!empty($st['expired'])) {
      return 'Bypass expired' . ($until ? (' (' . $until . ')') : '');
    }

    if (!empty($st['is_active'])) {
      return 'Bypass active until ' . ($until ? $until : '—');
    }

    return 'Bypass enabled';
  }
}

if (!function_exists('bvmgr_tax_bypass_apply')) {
  /**
   * Apply or clear bypass in a consistent, auditable way.
   *
   * @param int $post_id Vendor/Staff post ID
   * @param bool $enabled True to set, false to clear
   * @param string $until Y-m-d expiration date (required when enabling)
   * @param string $reason Required when enabling
   * @param int|null $user_id Optional override; defaults current user
   * @return array Status array from vms_get_tax_bypass_status()
   */
  function bvmgr_tax_bypass_apply(int $post_id, bool $enabled, string $until = '', string $reason = '', ?int $user_id = null): array
  {
    $post_id = absint($post_id);
    if ($post_id <= 0) return bvmgr_get_tax_bypass_status(0);

    $user_id = is_null($user_id) ? get_current_user_id() : absint($user_id);

    if (!$enabled) {
      update_post_meta($post_id, (string) bvmgr_tax_bypass_key('tax_bypass_enabled'), '0');
      delete_post_meta($post_id, (string) bvmgr_tax_bypass_key('tax_bypass_until'));
      delete_post_meta($post_id, (string) bvmgr_tax_bypass_key('tax_bypass_reason'));
      delete_post_meta($post_id, (string) bvmgr_tax_bypass_key('tax_bypass_set_by'));
      delete_post_meta($post_id, (string) bvmgr_tax_bypass_key('tax_bypass_set_at'));
      return bvmgr_get_tax_bypass_status($post_id);
    }

    $until = trim($until);
    $reason = trim($reason);

    update_post_meta($post_id, (string) bvmgr_tax_bypass_key('tax_bypass_enabled'), '1');
    update_post_meta($post_id, (string) bvmgr_tax_bypass_key('tax_bypass_until'), $until);
    update_post_meta($post_id, (string) bvmgr_tax_bypass_key('tax_bypass_reason'), $reason);
    if ($user_id > 0) {
      update_post_meta($post_id, (string) bvmgr_tax_bypass_key('tax_bypass_set_by'), (string) $user_id);
    }
    update_post_meta($post_id, (string) bvmgr_tax_bypass_key('tax_bypass_set_at'), wp_date('Y-m-d H:i:s', time(), wp_timezone()));

    return bvmgr_get_tax_bypass_status($post_id);
  }
}
