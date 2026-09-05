<?php
defined('ABSPATH') || exit;

if (defined('DOING_AJAX') && DOING_AJAX) {
    if (empty($GLOBALS['bvmgr_ajax_ob_started'])) {
        $GLOBALS['bvmgr_ajax_ob_started'] = true;
        // Buffer any unexpected output so AJAX JSON stays valid.
        ob_start();
    }
}

require_once __DIR__ . '/../integrations/tec-sync.php';
require_once __DIR__ . '/../integrations/attendance-woo.php';
require_once __DIR__ . '/../integrations/vendor-ics-sync.php';
require_once __DIR__ . '/../integrations/ticketing.php';
require_once __DIR__ . '/../integrations/ticketing-phase-b.php';
require_once __DIR__ . '/../integrations/square-sync-firewall.php';
require_once __DIR__ . '/../integrations/square-ticket-mirror.php';
require_once __DIR__ . '/../integrations/ticketing-claims-framework.php';
require_once __DIR__ . "/../integrations/ticketing-rules-v2.php";
require_once __DIR__ . "/../integrations/ticketing-verifications.php";
require_once __DIR__ . '/../ticketing/ticket-mutation-audit.php';
require_once __DIR__ . '/../ticketing/ticket-inventory-forensics.php';
require_once __DIR__ . '/../ticketing/ticket-legacy-repair.php';
require_once __DIR__ . '/../ticketing/ticket-integrity-checks.php';
require_once __DIR__ . '/../ticketing/ticket-integrity-monitor.php';
require_once __DIR__ . '/../ticketing/ticket-integrity-payment-gateway-health.php';
require_once __DIR__ . '/../ticketing/ticket-integrity-daily-report.php';
require_once __DIR__ . '/../ticketing/ticket-integrity-cron.php';
require_once __DIR__ . "/../integrations/ticketing-claims-admin.php";
require_once __DIR__ . "/../integrations/ticketing-claims-customer.php";
