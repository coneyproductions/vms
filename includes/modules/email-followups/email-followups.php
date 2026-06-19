<?php
defined('ABSPATH') || exit;

if (function_exists('vms_register_module')) {
	vms_register_module(array(
		'slug' => 'email_followups',
		'name' => 'Email Follow-Ups',
		'version' => defined('VMS_VERSION') ? (string) VMS_VERSION : '0.2.24.584',
		'premium' => false,
		'description' => 'Event-aware buyer reminders and follow-up email routines, designed to use MailPoet/WordPress delivery safely.',
		'source' => 'core',
	));
}

require_once __DIR__ . '/logs.php';

if (function_exists('vms_is_public_frontend_request') && vms_is_public_frontend_request()) {
	return;
}

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/templates.php';
require_once __DIR__ . '/mailpoet.php';
require_once __DIR__ . '/recipients.php';
require_once __DIR__ . '/sender.php';
require_once __DIR__ . '/scheduler.php';
if (is_admin()) {
	require_once __DIR__ . '/admin-ui.php';
}
