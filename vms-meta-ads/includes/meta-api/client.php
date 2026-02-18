<?php

defined('ABSPATH') || exit;

if (!class_exists('VMS_Meta_Ads_Meta_Client')) {
	class VMS_Meta_Ads_Meta_Client {
		public static function is_enabled(): bool
		{
			$settings = VMS_Meta_Ads_Utils::get_settings();
			return !empty($settings['enable_api_create']);
		}

		public static function create_paused_bundle(int $build_id)
		{
			$build = VMS_Meta_Ads_Builds_Service::get_build($build_id);
			if (is_wp_error($build)) {
				return $build;
			}
			if (!empty($build['meta_campaign_id'])) {
				return new WP_Error('already_created', __('Already created in Meta. Clone Build to create a new campaign.', 'vms-meta-ads'), array('status' => 409));
			}

			$lock = self::acquire_lock($build_id, 'meta_create');
			if (is_wp_error($lock)) {
				return $lock;
			}

			vms_ma_log('meta_create_requested', array('build_id' => $build_id), $build_id);
			self::release_lock($build_id, 'meta_create');

			return new WP_Error(
				'api_scaffold_only',
				__('Meta API scaffold is in place, but live object creation is intentionally gated for this phase. Use Export Copy Pack.', 'vms-meta-ads'),
				array('status' => 501)
			);
		}

		public static function go_live(int $build_id)
		{
			$build = VMS_Meta_Ads_Builds_Service::get_build($build_id);
			if (is_wp_error($build)) {
				return $build;
			}

			$lock = self::acquire_lock($build_id, 'meta_go_live');
			if (is_wp_error($lock)) {
				return $lock;
			}

			if (empty($build['meta_campaign_id'])) {
				self::release_lock($build_id, 'meta_go_live');
				return new WP_Error('not_created', __('Build has not been created in Meta yet.', 'vms-meta-ads'), array('status' => 400));
			}

			vms_ma_log('meta_go_live_requested', array('build_id' => $build_id), $build_id);
			self::release_lock($build_id, 'meta_go_live');

			return new WP_Error('api_scaffold_only', __('Go Live scaffold is present but disabled until API creation is fully implemented.', 'vms-meta-ads'), array('status' => 501));
		}

		public static function test_connection()
		{
			$settings = self::connection_settings();
			if (is_wp_error($settings)) {
				return $settings;
			}

			$preflight = self::preflight_permissions($settings);
			if (is_wp_error($preflight)) {
				return $preflight;
			}

			$url = VMS_Meta_Ads_Meta_Endpoints::build_url($settings, VMS_Meta_Ads_Meta_Endpoints::ad_account_endpoint($settings['meta_ad_account_id']));
			$url = add_query_arg(array('fields' => 'id,name', 'access_token' => $settings['token']), $url);
			$response = wp_remote_get($url, array('timeout' => 20));

			if (is_wp_error($response)) {
				vms_ma_log('error', array(
					'where' => 'test_connection',
					'message' => $response->get_error_message(),
					'response_code' => 0,
					'error' => true,
				));
				return new WP_Error('meta_connection_failed', __('Meta connection test failed before a response was received.', 'vms-meta-ads'), array(
					'details' => array(
						'type' => 'transport_error',
						'message' => $response->get_error_message(),
					),
				));
			}

			$code = (int) wp_remote_retrieve_response_code($response);
			$body = json_decode((string) wp_remote_retrieve_body($response), true);
			if ($code < 200 || $code >= 300 || (is_array($body) && isset($body['error']))) {
				$meta_error = self::extract_meta_error($body, $code);
				vms_ma_log('error', array(
					'where' => 'test_connection',
					'response_code' => $code,
					'type' => $meta_error['type'],
					'code' => $meta_error['code'],
					'fbtrace_id' => $meta_error['fbtrace_id'],
					'message' => $meta_error['message'],
					'error' => true,
				));
				return new WP_Error(
					'meta_connection_failed',
					__('Meta rejected the connection test. Verify token and ad account permissions.', 'vms-meta-ads'),
					array('details' => $meta_error)
				);
			}

			vms_ma_log('meta_create_response', array('where' => 'test_connection', 'response_code' => $code));
			return array('ok' => true, 'code' => $code);
		}

		public static function list_pages()
		{
			$settings = self::connection_settings();
			if (is_wp_error($settings)) {
				return $settings;
			}

			$url = VMS_Meta_Ads_Meta_Endpoints::build_url($settings, 'me/accounts');
			$url = add_query_arg(array(
				'fields' => 'id,name,instagram_business_account{id}',
				'limit' => 100,
				'access_token' => $settings['token'],
			), $url);
			$response = wp_remote_get($url, array('timeout' => 20));
			if (is_wp_error($response)) {
				return new WP_Error('meta_pages_lookup_failed', __('Could not load Pages from Meta.', 'vms-meta-ads'));
			}

			$code = (int) wp_remote_retrieve_response_code($response);
			$body = json_decode((string) wp_remote_retrieve_body($response), true);
			if ($code < 200 || $code >= 300 || !is_array($body)) {
				return new WP_Error('meta_pages_lookup_failed', __('Meta rejected page lookup.', 'vms-meta-ads'));
			}
			if (isset($body['error'])) {
				$meta_error = self::extract_meta_error($body, $code);
				return new WP_Error('meta_pages_lookup_failed', $meta_error['message']);
			}

			$items = array();
			$data = isset($body['data']) && is_array($body['data']) ? $body['data'] : array();
			foreach ($data as $row) {
				if (!is_array($row)) {
					continue;
				}
				$page_id = trim((string) ($row['id'] ?? ''));
				if ($page_id === '') {
					continue;
				}
				$ig_actor_id = '';
				if (!empty($row['instagram_business_account']) && is_array($row['instagram_business_account'])) {
					$ig_actor_id = trim((string) ($row['instagram_business_account']['id'] ?? ''));
				}
				$items[] = array(
					'id' => $page_id,
					'name' => sanitize_text_field((string) ($row['name'] ?? ('Page ' . $page_id))),
					'ig_actor_id' => $ig_actor_id,
				);
			}

			return array('items' => $items);
		}

		private static function preflight_permissions(array $settings)
		{
			$url = VMS_Meta_Ads_Meta_Endpoints::build_url($settings, 'me/permissions');
			$url = add_query_arg(array('access_token' => $settings['token']), $url);
			$response = wp_remote_get($url, array('timeout' => 20));
			if (is_wp_error($response)) {
				vms_ma_log('error', array(
					'where' => 'preflight_permissions',
					'response_code' => 0,
					'message' => $response->get_error_message(),
					'error' => true,
				));
				return new WP_Error('meta_permission_preflight_failed', __('Meta permission preflight could not be completed.', 'vms-meta-ads'), array(
					'details' => array(
						'type' => 'transport_error',
						'message' => $response->get_error_message(),
					),
				));
			}

			$code = (int) wp_remote_retrieve_response_code($response);
			$body = json_decode((string) wp_remote_retrieve_body($response), true);
			if ($code >= 200 && $code < 300 && is_array($body) && isset($body['data']) && is_array($body['data'])) {
				$granted = array();
				foreach ($body['data'] as $item) {
					if (!is_array($item)) {
						continue;
					}
					$perm_name = sanitize_key((string) ($item['permission'] ?? ''));
					$status = sanitize_key((string) ($item['status'] ?? ''));
					if ($perm_name !== '' && $status === 'granted') {
						$granted[$perm_name] = true;
					}
				}
				if (empty($granted['ads_read']) && empty($granted['ads_management'])) {
					return new WP_Error(
						'meta_permission_missing',
						__('Token missing required permission(s): ads_read/ads_management. Regenerate token and select permissions before Generate Access Token.', 'vms-meta-ads'),
						array(
							'details' => array(
								'type' => 'permission_error',
								'message' => 'ads_read/ads_management missing from granted permissions.',
								'code' => 0,
								'fbtrace_id' => '',
							),
						)
					);
				}
				return array('ok' => true);
			}

			if (is_array($body) && isset($body['error'])) {
				$meta_error = self::extract_meta_error($body, $code);
				vms_ma_log('error', array(
					'where' => 'preflight_permissions',
					'response_code' => $code,
					'type' => $meta_error['type'],
					'code' => $meta_error['code'],
					'fbtrace_id' => $meta_error['fbtrace_id'],
					'message' => $meta_error['message'],
					'error' => true,
				));
			}

			// Permission introspection can fail for some token shapes; continue and rely on downstream check.
			return array('ok' => true, 'fallback' => true);
		}

		private static function extract_meta_error($body, int $response_code): array
		{
			$error = array(
				'type' => 'unknown_error',
				'code' => 0,
				'message' => 'Unknown Meta API error.',
				'fbtrace_id' => '',
				'response_code' => $response_code,
			);
			if (!is_array($body) || !isset($body['error']) || !is_array($body['error'])) {
				return $error;
			}
			$meta = $body['error'];
			$error['type'] = sanitize_text_field((string) ($meta['type'] ?? $error['type']));
			$error['code'] = (int) ($meta['code'] ?? 0);
			$error['message'] = sanitize_text_field((string) ($meta['message'] ?? $error['message']));
			$error['fbtrace_id'] = sanitize_text_field((string) ($meta['fbtrace_id'] ?? ''));
			return $error;
		}

		private static function connection_settings()
		{
			$settings = VMS_Meta_Ads_Utils::get_settings();
			$use_local_override = !empty($settings['meta_ad_account_override']);
			if (!$use_local_override) {
				$social = apply_filters('vms_social_meta_connection', null);
				if (is_array($social) && !empty($social['ad_account_id'])) {
					$settings = array_merge($settings, array(
						'meta_ad_account_id' => (string) ($social['ad_account_id'] ?? ''),
						'meta_page_id' => (string) ($social['page_id'] ?? ''),
						'meta_ig_actor_id' => (string) ($social['ig_actor_id'] ?? ''),
						'meta_graph_version' => (string) ($social['graph_version'] ?? $settings['meta_graph_version']),
						'meta_access_token_encrypted' => (string) ($social['access_token_encrypted'] ?? ''),
					));
				}
			}
			$ad_account_id = trim((string) ($settings['meta_ad_account_id'] ?? ''));
			$token_encrypted = (string) ($settings['meta_access_token_encrypted'] ?? '');
			$token = VMS_Meta_Ads_Utils::decrypt_token($token_encrypted);

			if ($ad_account_id === '' || $token === '') {
				return new WP_Error('meta_settings_incomplete', __('Meta settings are incomplete. Provide ad account and token.', 'vms-meta-ads'));
			}

			$settings['meta_ad_account_id'] = ltrim($ad_account_id, 'act_');
			$settings['token'] = $token;
			return $settings;
		}

		private static function acquire_lock(int $build_id, string $action)
		{
			$key = 'vms_ma_lock_' . $build_id . '_' . $action;
			if (get_transient($key)) {
				return new WP_Error('locked', __('A request is already running for this build. Please wait and retry.', 'vms-meta-ads'), array('status' => 409));
			}
			set_transient($key, 1, 120);
			return true;
		}

		private static function release_lock(int $build_id, string $action): void
		{
			$key = 'vms_ma_lock_' . $build_id . '_' . $action;
			delete_transient($key);
		}
	}
}
