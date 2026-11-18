<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_email_followups_mailpoet_api')) {
	function bvmgr_email_followups_mailpoet_api()
	{
		if (!class_exists('\\MailPoet\\API\\API')) {
			return null;
		}
		try {
			return \MailPoet\API\API::MP('v1');
		} catch (Throwable $e) {
			return null;
		}
	}
}

if (!function_exists('bvmgr_email_followups_mailpoet_status')) {
	function bvmgr_email_followups_mailpoet_status(): array
	{
		$status = array(
			'available' => false,
			'setup_complete' => null,
			'lists' => array(),
			'message' => __('MailPoet API is not available. Backstage Venue Manager can still use WordPress email delivery for tests/manual sends.', 'backstage-venue-manager'),
		);

		$api = bvmgr_email_followups_mailpoet_api();
		if (!$api) {
			return $status;
		}

		$status['available'] = true;
		$status['message'] = __('MailPoet API detected. Subscriber/list sync can be used after you choose a list.', 'backstage-venue-manager');

		try {
			if (method_exists($api, 'isSetupComplete')) {
				$status['setup_complete'] = (bool) $api->isSetupComplete();
			}
		} catch (Throwable $e) {
			$status['setup_complete'] = null;
		}

		try {
			if (method_exists($api, 'getLists')) {
				$lists = $api->getLists();
				if (is_array($lists)) {
					foreach ($lists as $list) {
						if (!is_array($list)) {
							continue;
						}
						$id = isset($list['id']) ? absint($list['id']) : 0;
						$name = isset($list['name']) ? sanitize_text_field((string) $list['name']) : '';
						if ($id > 0 && $name !== '') {
							$status['lists'][$id] = $name;
						}
					}
				}
			}
		} catch (Throwable $e) {
			// Keep page usable even if MailPoet list lookup fails.
		}

		return $status;
	}
}

if (!function_exists('bvmgr_email_followups_maybe_sync_mailpoet_subscriber')) {
	function bvmgr_email_followups_maybe_sync_mailpoet_subscriber(string $email, string $name, array $tags = array()): array
	{
		$settings = bvmgr_email_followups_settings();
		if (empty($settings['mailpoet_sync_enabled'])) {
			return array('ok' => true, 'skipped' => true, 'message' => 'mailpoet_sync_disabled');
		}

		$email = sanitize_email($email);
		if (!is_email($email)) {
			return array('ok' => false, 'skipped' => false, 'message' => 'invalid_email');
		}

		$api = bvmgr_email_followups_mailpoet_api();
		if (!$api) {
			return array('ok' => false, 'skipped' => false, 'message' => 'mailpoet_api_unavailable');
		}

		$name_parts = preg_split('/\s+/', trim($name), 2);
		$subscriber = array(
			'email' => $email,
			'first_name' => isset($name_parts[0]) ? sanitize_text_field((string) $name_parts[0]) : '',
			'last_name' => isset($name_parts[1]) ? sanitize_text_field((string) $name_parts[1]) : '',
			'tags' => array_values(array_unique(array_filter(array_map('sanitize_text_field', $tags)))),
		);
		$list_id = absint($settings['mailpoet_list_id'] ?? 0);
		$list_ids = $list_id > 0 ? array($list_id) : array();

		try {
			if (method_exists($api, 'getSubscriber')) {
				$existing = $api->getSubscriber($email);
				if (is_array($existing)) {
					foreach ($subscriber['tags'] as $tag) {
						if ($tag !== '' && method_exists($api, 'tagSubscriber')) {
							$api->tagSubscriber($email, $tag);
						}
					}
					if ($list_id > 0 && method_exists($api, 'subscribeToList')) {
						$api->subscribeToList($email, $list_id);
					}
					return array('ok' => true, 'skipped' => false, 'message' => 'subscriber_updated');
				}
			}
		} catch (Throwable $e) {
			// Existing subscriber lookup can throw when absent; addSubscriber below handles new records.
		}

		try {
			if (method_exists($api, 'addSubscriber')) {
				$api->addSubscriber($subscriber, $list_ids, array(
					'send_confirmation_email' => true,
					'schedule_welcome_email' => false,
					'skip_subscriber_notification' => true,
				));
				return array('ok' => true, 'skipped' => false, 'message' => 'subscriber_added');
			}
		} catch (Throwable $e) {
			return array('ok' => false, 'skipped' => false, 'message' => 'mailpoet_sync_error: ' . $e->getMessage());
		}

		return array('ok' => false, 'skipped' => false, 'message' => 'mailpoet_add_subscriber_unavailable');
	}
}
