<?php

defined('ABSPATH') || exit;

if (!class_exists('VMS_Tours_Compat')) {
	class VMS_Tours_Compat
	{
		const ALLOWED_SCRIPT_HANDLES = array(
			'vms-driverjs',
			'vms-tours-runtime',
			'vms-tours-admin',
		);

		/**
		 * @var array<int,string>
		 */
		private $legacy_handles = array(
			'introjs',
			'intro.js',
			'shepherd',
			'shepherd.js',
			'hopscotch',
			'tourguide',
			'vms-tours',
			'vms-ma-tours',
		);

		/**
		 * @var array<int,string>
		 */
		private $detected_legacy_handles = array();

		/**
		 * @var VMS_Tours_Screen
		 */
		private $screen;

		public function __construct(VMS_Tours_Screen $screen)
		{
			$this->screen = $screen;
		}

		public function init(): void
		{
			add_action('wp_print_scripts', array($this, 'deregister_legacy_scripts'), 100);
			add_action('admin_notices', array($this, 'render_legacy_notice'));
		}

		public function deregister_legacy_scripts(): void
		{
			if (!$this->screen->is_vms_admin_screen()) {
				return;
			}

			foreach ($this->legacy_handles as $handle) {
				if (in_array($handle, self::ALLOWED_SCRIPT_HANDLES, true)) {
					continue;
				}

				if (wp_script_is($handle, 'enqueued') || wp_script_is($handle, 'registered')) {
					$this->detected_legacy_handles[] = $handle;
					wp_dequeue_script($handle);
					wp_deregister_script($handle);
				}
			}

			global $wp_scripts;
			if (!($wp_scripts instanceof WP_Scripts)) {
				return;
			}

			foreach ((array) $wp_scripts->queue as $handle) {
				if (in_array($handle, self::ALLOWED_SCRIPT_HANDLES, true)) {
					continue;
				}
				if (!isset($wp_scripts->registered[$handle])) {
					continue;
				}

				$src = (string) ($wp_scripts->registered[$handle]->src ?? '');
				$needle = strtolower($src);
				if ($needle === '') {
					continue;
				}

				if (strpos($needle, 'intro') !== false || strpos($needle, 'shepherd') !== false || strpos($needle, 'hopscotch') !== false || strpos($needle, 'tourguide') !== false) {
					$this->detected_legacy_handles[] = $handle;
					wp_dequeue_script($handle);
					wp_deregister_script($handle);
				}
			}

			$this->detected_legacy_handles = array_values(array_unique($this->detected_legacy_handles));
		}

		public function render_legacy_notice(): void
		{
			if (empty($this->detected_legacy_handles)) {
				return;
			}
			if (!current_user_can('manage_options')) {
				return;
			}
			if (!$this->screen->is_vms_admin_screen()) {
				return;
			}

			echo '<div class="notice notice-warning is-dismissible">';
			echo '<p><strong>' . esc_html__('Backstage Venue Manager Guided Tours compatibility notice:', 'backstage-venue-manager') . '</strong> ' . esc_html__('legacy tour script handles were detected and deregistered on this Backstage Venue Manager screen.', 'backstage-venue-manager') . '</p>';
			echo '<p><code>' . esc_html(implode(', ', $this->detected_legacy_handles)) . '</code></p>';
			echo '</div>';
		}

		/**
		 * @return array<int,array<string,mixed>>
		 */
		public function collect_legacy_tours(): array
		{
			$legacy = apply_filters('vms_register_tours', array());
			if (!is_array($legacy)) {
				$legacy = array();
			}

			if (function_exists('vms_get_registered_tours')) {
				$dynamic = vms_get_registered_tours();
				if (is_array($dynamic) && !empty($dynamic)) {
					$legacy = array_merge($legacy, $dynamic);
				}
			}

			return $legacy;
		}

		/**
		 * @param array<string,mixed> $legacy
		 * @return array<string,mixed>
		 */
		public function convert_legacy_tour(array $legacy): array
		{
			$id = $this->sanitize_id((string) ($legacy['id'] ?? ''));
			if ($id === '') {
				return array();
			}

			$screen = $this->resolve_legacy_screen($legacy);
			if ($screen === '') {
				return array();
			}

			$steps = array();
			$raw_steps = isset($legacy['steps']) && is_array($legacy['steps']) ? $legacy['steps'] : array();
			foreach ($raw_steps as $index => $step) {
				if (!is_array($step)) {
					continue;
				}

				$anchor = $this->sanitize_anchor((string) ($step['anchor'] ?? ''));
				if ($anchor === '') {
					continue;
				}

				$title = sanitize_text_field((string) ($step['title'] ?? ''));
				$body = wp_kses_post((string) ($step['content'] ?? ($step['description'] ?? '')));
				if ($title === '' || $body === '') {
					continue;
				}

				$step_id = $this->sanitize_id((string) ($step['id'] ?? ('legacy_' . $index . '_' . $anchor)));
				if ($step_id === '') {
					$step_id = 'legacy_' . $index;
				}

				$steps[] = array(
					'id' => $step_id,
					'selector' => '[data-vms-tour="' . esc_attr($anchor) . '"]',
					'title' => $title,
					'body' => $body,
					'placement' => $this->sanitize_placement((string) ($step['placement'] ?? 'auto')),
					'guard' => array('type' => 'element_exists'),
					'fallback' => array('type' => 'skip', 'log' => true),
				);
			}

			if (empty($steps)) {
				return array();
			}

			$version_raw = $legacy['version'] ?? '1.0.0';
			$version = is_scalar($version_raw) ? (string) $version_raw : '1.0.0';
			if ($version === '') {
				$version = '1.0.0';
			}

			return array(
				'id' => $id,
				'title' => sanitize_text_field((string) ($legacy['title'] ?? $id)),
				'description' => sanitize_text_field((string) ($legacy['description'] ?? '')),
				'screen' => $screen,
				'version' => $version,
				'level' => 'beginner',
				'audience' => array(
					'capabilities_any' => array('manage_options'),
					'capabilities_all' => array(),
					'roles_any' => array(),
					'roles_all' => array(),
				),
				'auto_run' => true,
				'auto_run_delay_ms' => 400,
				'allow_restart' => true,
				'priority' => 20,
				'steps' => $steps,
			);
		}

		/**
		 * @param array<string,mixed> $legacy
		 */
		private function resolve_legacy_screen(array $legacy): string
		{
			$contexts = isset($legacy['contexts']) && is_array($legacy['contexts']) ? $legacy['contexts'] : array();
			foreach ($contexts as $context) {
				if (!is_array($context)) {
					continue;
				}

				$url = isset($context['url']) ? (string) $context['url'] : '';
				if ($url !== '') {
					$slug = $this->slug_from_admin_url($url);
					if ($slug !== '') {
						if ($slug === 'vms') {
							$slug = 'vms-dashboard';
						}
						return 'admin:' . $slug;
					}
				}

				$screen_id = isset($context['screen_id']) ? sanitize_key((string) $context['screen_id']) : '';
				if ($screen_id !== '') {
					return 'admin:' . $screen_id;
				}

				$context_key = isset($context['context_key']) ? sanitize_key((string) $context['context_key']) : '';
				if ($context_key !== '') {
					return 'admin:' . $context_key;
				}
			}

			return '';
		}

		private function slug_from_admin_url(string $url): string
		{
			$url = trim($url);
			if ($url === '') {
				return '';
			}

			$parts = wp_parse_url($url);
			if (!is_array($parts)) {
				return '';
			}

			$query = isset($parts['query']) ? (string) $parts['query'] : '';
			if ($query === '' && strpos($url, '?') !== false) {
				$query = (string) substr($url, strpos($url, '?') + 1);
			}
			if ($query === '') {
				return '';
			}

			parse_str($query, $args);
			if (isset($args['page'])) {
				return sanitize_key((string) $args['page']);
			}

			if (isset($args['post_type'])) {
				return sanitize_key('edit-' . (string) $args['post_type']);
			}

			return '';
		}

		private function sanitize_id(string $id): string
		{
			$id = strtolower(trim($id));
			if ($id === '') {
				return '';
			}
			$sanitized = preg_replace('/[^a-z0-9._\-]/', '', $id);
			return is_string($sanitized) ? $sanitized : '';
		}

		private function sanitize_anchor(string $anchor): string
		{
			$anchor = strtolower(trim($anchor));
			if ($anchor === '') {
				return '';
			}
			$sanitized = preg_replace('/[^a-z0-9._\-]/', '', $anchor);
			return is_string($sanitized) ? $sanitized : '';
		}

		private function sanitize_placement(string $placement): string
		{
			$placement = sanitize_key($placement);
			if (!in_array($placement, array('top', 'right', 'bottom', 'left', 'auto'), true)) {
				return 'auto';
			}
			return $placement;
		}
	}
}
