<?php
defined('ABSPATH') || exit;

require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/installer.php';

require_once __DIR__ . '/providers/interface-provider.php';
require_once __DIR__ . '/providers/class-provider-mock.php';
require_once __DIR__ . '/providers/class-provider-webhook.php';
require_once __DIR__ . '/providers/class-provider-meta.php';
require_once __DIR__ . '/providers/class-provider-linkedin.php';
require_once __DIR__ . '/providers/registry.php';

require_once __DIR__ . '/context.php';
require_once __DIR__ . '/template-engine.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/queue-repo.php';
require_once __DIR__ . '/queue-runner.php';

if (is_admin()) {
	require_once __DIR__ . '/admin.php';
	require_once __DIR__ . '/event-plan-panel.php';
}

do_action('vms_social_loaded');
