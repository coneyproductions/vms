<?php
declare(strict_types=1);

$GLOBALS['ecc_health_post_status'] = 'draft';
function get_post_status($post_id)
{
    unset($post_id);
    return $GLOBALS['ecc_health_post_status'];
}

require __DIR__ . '/event-command-center-2-fixtures.php';

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$setupAlerts = array(
    array('severity' => 'red', 'code' => 'setup_venue_missing'),
    array('severity' => 'red', 'code' => 'setup_date_missing'),
    array('severity' => 'red', 'code' => 'setup_primary_missing'),
    array('severity' => 'yellow', 'code' => 'promo'),
);

$GLOBALS['ecc_health_post_status'] = 'auto-draft';
$health = bvmgr_event_command_center_get_health($setupAlerts, 91, array('status' => 'draft'));
$assert($health['status'] === 'setup-in-progress', 'A new auto-draft with only missing setup must be neutral.');
$assert($health['label'] === 'Setup in progress', 'The neutral new-plan label must be operator-readable.');
$assert(bvmgr_event_command_center_health_tone($health['status']) === 'muted', 'Setup in progress must use a neutral tone.');

$GLOBALS['ecc_health_post_status'] = 'draft';
$health = bvmgr_event_command_center_get_health(array_slice($setupAlerts, 0, 2), 92, array('status' => 'draft'));
$assert($health['status'] === 'needs-setup', 'A partially configured draft must report Needs setup, not Critical.');
$assert(bvmgr_event_command_center_health_tone($health['status']) === 'warning', 'Needs setup must remain visible without a critical tone.');

$integrityAlerts = $setupAlerts;
$integrityAlerts[] = array('severity' => 'red', 'code' => 'staffing_critical');
$health = bvmgr_event_command_center_get_health($integrityAlerts, 93, array('status' => 'draft'));
$assert($health['status'] === 'critical', 'A genuine non-setup failure must remain Critical on a Draft.');

$health = bvmgr_event_command_center_get_health(array_slice($setupAlerts, 0, 1), 94, array('status' => 'ready'));
$assert($health['status'] === 'critical', 'Ready plans must not downgrade a missing core setup failure.');

$GLOBALS['ecc_health_post_status'] = 'publish';
$health = bvmgr_event_command_center_get_health(array_slice($setupAlerts, 0, 1), 95, array('status' => 'published'));
$assert($health['status'] === 'critical', 'Published plans must retain Critical health for missing setup.');

$source = (string) file_get_contents(dirname(__DIR__) . '/includes/admin/event-command-center.php');
foreach (array('setup_venue_missing', 'setup_date_missing', 'setup_primary_missing') as $code) {
    $assert(strpos($source, "'code' => '" . $code . "'") !== false, 'Missing setup alert code: ' . $code);
}
$moduleHubHelp = 'At-a-glance module summaries stay visible here while each heavy workspace can be managed without turning every Event Plan update into a full rebuild.';
$assert(substr_count($source, $moduleHubHelp) === 1, 'Module Hub explanation should remain available exactly once as help text.');
$assert(strpos($source, "bvmgr_help_icon(\n                __('" . $moduleHubHelp) !== false, 'Module Hub explanation should use the standard BVM help icon.');
$assert(strpos($source, "echo '<p>' . esc_html__('" . $moduleHubHelp) === false, 'Module Hub explanation should not remain as permanent paragraph copy.');

echo "Event Command Center new-plan health passed {$checks} assertions.\n";
