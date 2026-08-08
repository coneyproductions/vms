<?php

defined('ABSPATH') || exit;

if (!class_exists('VMS_Tours_Registry')) {
	class VMS_Tours_Registry
	{
		/**
		 * @var array<string,array<string,mixed>>
		 */
		private $tours = array();

		/**
		 * @param array<string,mixed> $tour_def
		 */
		public function register(array $tour_def): bool
		{
			$tour = $this->normalize_tour($tour_def);
			if (empty($tour)) {
				return false;
			}

			$id = (string) $tour['id'];
			$this->tours[$id] = $tour;
			return true;
		}

		/**
		 * @return array<int,array<string,mixed>>
		 */
		public function all(): array
		{
			return array_values($this->tours);
		}

		/**
		 * @return array<string,mixed>|null
		 */
		public function get(string $tour_id): ?array
		{
			$tour_id = $this->sanitize_id($tour_id);
			if ($tour_id === '' || !isset($this->tours[$tour_id])) {
				return null;
			}

			return $this->tours[$tour_id];
		}

		public function has(string $tour_id): bool
		{
			$tour_id = $this->sanitize_id($tour_id);
			return ($tour_id !== '' && isset($this->tours[$tour_id]));
		}

		/**
		 * @return array<int,array<string,mixed>>
		 */
		public function for_screen(string $screen_key): array
		{
			$screen_key = $this->sanitize_screen_key($screen_key);
			if ($screen_key === '') {
				return array();
			}

			$out = array();
			foreach ($this->tours as $tour) {
				if ((string) ($tour['screen'] ?? '') === $screen_key) {
					$out[] = $tour;
				}
			}

			return $out;
		}

		/**
		 * @param array<string,mixed> $tour
		 */
		public function user_matches_audience(array $tour, WP_User $user): bool
		{
			$audience = isset($tour['audience']) && is_array($tour['audience']) ? $tour['audience'] : array();
			$caps_any = isset($audience['capabilities_any']) && is_array($audience['capabilities_any']) ? $audience['capabilities_any'] : array();
			$caps_all = isset($audience['capabilities_all']) && is_array($audience['capabilities_all']) ? $audience['capabilities_all'] : array();
			$roles_any = isset($audience['roles_any']) && is_array($audience['roles_any']) ? $audience['roles_any'] : array();
			$roles_all = isset($audience['roles_all']) && is_array($audience['roles_all']) ? $audience['roles_all'] : array();

			if (!$this->user_matches_caps_any($user, $caps_any)) {
				return false;
			}
			if (!$this->user_matches_caps_all($user, $caps_all)) {
				return false;
			}
			if (!$this->user_matches_roles_any($user, $roles_any)) {
				return false;
			}
			if (!$this->user_matches_roles_all($user, $roles_all)) {
				return false;
			}

			return true;
		}

		public function level_allows_tour(string $user_level, string $tour_level): bool
		{
			$rank = array(
				'beginner' => 1,
				'standard' => 2,
				'advanced' => 3,
			);

			$user_level = $this->sanitize_level($user_level);
			$tour_level = $this->sanitize_level($tour_level);

			return ((int) ($rank[$tour_level] ?? 1)) <= ((int) ($rank[$user_level] ?? 1));
		}

		/**
		 * @param array<string,mixed> $tour
		 * @return array<string,mixed>
		 */
		public function sanitize_for_js(array $tour): array
		{
			$out = array(
				'id' => (string) ($tour['id'] ?? ''),
				'title' => (string) ($tour['title'] ?? ''),
				'description' => (string) ($tour['description'] ?? ''),
				'screen' => (string) ($tour['screen'] ?? ''),
				'version' => (string) ($tour['version'] ?? ''),
				'level' => (string) ($tour['level'] ?? 'beginner'),
				'auto_run' => !empty($tour['auto_run']),
				'auto_run_delay_ms' => (int) ($tour['auto_run_delay_ms'] ?? 400),
				'allow_restart' => !empty($tour['allow_restart']),
				'tags' => isset($tour['tags']) && is_array($tour['tags']) ? array_values($tour['tags']) : array(),
				'priority' => (int) ($tour['priority'] ?? 10),
				'steps' => array(),
			);

			$steps = isset($tour['steps']) && is_array($tour['steps']) ? $tour['steps'] : array();
			foreach ($steps as $step) {
				if (!is_array($step)) {
					continue;
				}

				$out['steps'][] = array(
					'id' => (string) ($step['id'] ?? ''),
					'selector' => (string) ($step['selector'] ?? ''),
					'title' => (string) ($step['title'] ?? ''),
					'body' => (string) ($step['body'] ?? ''),
					'placement' => (string) ($step['placement'] ?? 'auto'),
					'scroll_to' => !empty($step['scroll_to']),
					'scroll_padding_px' => (int) ($step['scroll_padding_px'] ?? 12),
					'highlight_padding_px' => (int) ($step['highlight_padding_px'] ?? 8),
					'allow_click_through' => !empty($step['allow_click_through']),
					'on_show' => isset($step['on_show']) && is_array($step['on_show']) ? array_values($step['on_show']) : array(),
					'guard' => isset($step['guard']) && is_array($step['guard']) ? $step['guard'] : array(),
					'fallback' => isset($step['fallback']) && is_array($step['fallback']) ? $step['fallback'] : array(
						'type' => 'skip',
						'log' => true,
					),
				);
			}

			return $out;
		}

		/**
		 * @param array<string,mixed> $tour_def
		 * @return array<string,mixed>
		 */
		private function normalize_tour(array $tour_def): array
		{
			$required_keys = array('id', 'title', 'screen', 'version', 'level', 'audience', 'steps');
			foreach ($required_keys as $required_key) {
				if (!array_key_exists($required_key, $tour_def)) {
					return array();
				}
			}

			$id = $this->sanitize_id((string) $tour_def['id']);
			if ($id === '') {
				return array();
			}

			$screen = $this->sanitize_screen_key((string) $tour_def['screen']);
			if ($screen === '') {
				return array();
			}

			$version = trim((string) $tour_def['version']);
			if ($version === '') {
				return array();
			}

			$title = sanitize_text_field((string) $tour_def['title']);
			if ($title === '') {
				$title = $id;
			}

			$level = $this->sanitize_level((string) $tour_def['level']);
			$audience = $this->normalize_audience($tour_def['audience']);
			$steps = $this->normalize_steps($tour_def['steps']);
			if (empty($steps)) {
				return array();
			}

			$auto_run = !array_key_exists('auto_run', $tour_def) || !empty($tour_def['auto_run']);
			$auto_run_delay_ms = isset($tour_def['auto_run_delay_ms']) ? (int) $tour_def['auto_run_delay_ms'] : 400;
			if ($auto_run_delay_ms < 0) {
				$auto_run_delay_ms = 0;
			}

			$priority = isset($tour_def['priority']) ? (int) $tour_def['priority'] : 10;
			$allow_restart = !array_key_exists('allow_restart', $tour_def) || !empty($tour_def['allow_restart']);

			$tags = array();
			if (isset($tour_def['tags']) && is_array($tour_def['tags'])) {
				foreach ($tour_def['tags'] as $tag) {
					$tag = sanitize_key((string) $tag);
					if ($tag !== '') {
						$tags[] = $tag;
					}
				}
				$tags = array_values(array_unique($tags));
			}

			return array(
				'id' => $id,
				'title' => $title,
				'screen' => $screen,
				'version' => $version,
				'level' => $level,
				'audience' => $audience,
				'steps' => $steps,
				'description' => isset($tour_def['description']) ? sanitize_text_field((string) $tour_def['description']) : '',
				'auto_run' => $auto_run,
				'auto_run_delay_ms' => $auto_run_delay_ms,
				'allow_restart' => $allow_restart,
				'tags' => $tags,
				'priority' => $priority,
			);
		}

		/**
		 * @param mixed $raw
		 * @return array<string,array<int,string>>
		 */
		private function normalize_audience($raw): array
		{
			if (!is_array($raw)) {
				$raw = array();
			}

			$audience = array(
				'capabilities_any' => $this->sanitize_string_array($raw['capabilities_any'] ?? array()),
				'capabilities_all' => $this->sanitize_string_array($raw['capabilities_all'] ?? array()),
				'roles_any' => $this->sanitize_string_array($raw['roles_any'] ?? array()),
				'roles_all' => $this->sanitize_string_array($raw['roles_all'] ?? array()),
			);

			$has_values = false;
			foreach ($audience as $values) {
				if (!empty($values)) {
					$has_values = true;
					break;
				}
			}

			if (!$has_values) {
				$audience['capabilities_any'] = array('manage_options');
			}

			return $audience;
		}

		/**
		 * @param mixed $raw
		 * @return array<int,array<string,mixed>>
		 */
		private function normalize_steps($raw): array
		{
			if (!is_array($raw)) {
				return array();
			}

			$out = array();
			foreach ($raw as $index => $step) {
				if (!is_array($step)) {
					continue;
				}

				$id = $this->sanitize_id((string) ($step['id'] ?? ('step_' . $index)));
				$selector = $this->sanitize_selector((string) ($step['selector'] ?? ''));
				$title = sanitize_text_field((string) ($step['title'] ?? ''));
				$body = wp_kses_post((string) ($step['body'] ?? ''));
				$placement = $this->sanitize_placement((string) ($step['placement'] ?? 'auto'));

				if ($id === '' || $selector === '' || $title === '' || $body === '') {
					continue;
				}

				$on_show = $this->normalize_actions($step['on_show'] ?? array());
				$guard = $this->normalize_guard($step['guard'] ?? array());
				$fallback = $this->normalize_fallback($step['fallback'] ?? array());

				$scroll_padding = isset($step['scroll_padding_px']) ? (int) $step['scroll_padding_px'] : 12;
				$highlight_padding = isset($step['highlight_padding_px']) ? (int) $step['highlight_padding_px'] : 8;

				if ($scroll_padding < 0) {
					$scroll_padding = 0;
				}
				if ($highlight_padding < 0) {
					$highlight_padding = 0;
				}

				$out[] = array(
					'id' => $id,
					'selector' => $selector,
					'title' => $title,
					'body' => $body,
					'placement' => $placement,
					'scroll_to' => !array_key_exists('scroll_to', $step) || !empty($step['scroll_to']),
					'scroll_padding_px' => $scroll_padding,
					'highlight_padding_px' => $highlight_padding,
					'allow_click_through' => !empty($step['allow_click_through']),
					'on_show' => $on_show,
					'guard' => $guard,
					'fallback' => $fallback,
				);
			}

			return $out;
		}

		/**
		 * @param mixed $raw
		 * @return array<int,array<string,mixed>>
		 */
		private function normalize_actions($raw): array
		{
			if (!is_array($raw)) {
				return array();
			}

			$allowed = array('open_accordion', 'click', 'set_value', 'scroll_into_view');
			$out = array();
			foreach ($raw as $action) {
				if (!is_array($action)) {
					continue;
				}

				$type = sanitize_key((string) ($action['type'] ?? ''));
				if (!in_array($type, $allowed, true)) {
					continue;
				}

				$row = array('type' => $type);
				if (isset($action['selector'])) {
					$row['selector'] = $this->sanitize_selector((string) $action['selector']);
				}
				if (isset($action['item_selector'])) {
					$row['item_selector'] = $this->sanitize_selector((string) $action['item_selector']);
				}
				if (isset($action['value'])) {
					$row['value'] = is_scalar($action['value']) ? (string) $action['value'] : '';
				}
				if (isset($action['padding_px'])) {
					$row['padding_px'] = max(0, (int) $action['padding_px']);
				}

				$out[] = $row;
			}

			return $out;
		}

		/**
		 * @param mixed $raw
		 * @return array<string,mixed>
		 */
		private function normalize_guard($raw): array
		{
			if (!is_array($raw)) {
				return array();
			}

			$type = sanitize_key((string) ($raw['type'] ?? ''));
			$allowed = array('element_exists', 'field_is_default', 'field_is_empty', 'checkbox_is_unchecked');
			if (!in_array($type, $allowed, true)) {
				return array();
			}

			$guard = array('type' => $type);
			if (isset($raw['selector'])) {
				$guard['selector'] = $this->sanitize_selector((string) $raw['selector']);
			}
			if (array_key_exists('default', $raw)) {
				$guard['default'] = is_scalar($raw['default']) ? (string) $raw['default'] : '';
			}
			if (array_key_exists('trim', $raw)) {
				$guard['trim'] = !empty($raw['trim']);
			}

			return $guard;
		}

		/**
		 * @param mixed $raw
		 * @return array<string,mixed>
		 */
		private function normalize_fallback($raw): array
		{
			if (!is_array($raw)) {
				$raw = array();
			}

			$type = sanitize_key((string) ($raw['type'] ?? 'skip'));
			if ($type !== 'skip') {
				$type = 'skip';
			}

			return array(
				'type' => $type,
				'log' => !array_key_exists('log', $raw) || !empty($raw['log']),
			);
		}

		/**
		 * @param mixed $raw
		 * @return array<int,string>
		 */
		private function sanitize_string_array($raw): array
		{
			if (!is_array($raw)) {
				return array();
			}

			$out = array();
			foreach ($raw as $value) {
				$key = sanitize_key((string) $value);
				if ($key !== '') {
					$out[] = $key;
				}
			}
			return array_values(array_unique($out));
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

		private function sanitize_level(string $level): string
		{
			$level = sanitize_key($level);
			if (!in_array($level, array('beginner', 'standard', 'advanced'), true)) {
				return 'beginner';
			}
			return $level;
		}

		private function sanitize_placement(string $placement): string
		{
			$placement = sanitize_key($placement);
			if (!in_array($placement, array('top', 'right', 'bottom', 'left', 'auto'), true)) {
				return 'auto';
			}
			return $placement;
		}

		private function sanitize_selector(string $selector): string
		{
			$selector = trim($selector);
			if ($selector === '') {
				return '';
			}

			$selector = str_replace(array("\0", "\r", "\n"), '', $selector);
			return $selector;
		}

		private function sanitize_screen_key(string $screen): string
		{
			$screen = strtolower(trim($screen));
			if ($screen === '') {
				return '';
			}

			$sanitized = preg_replace('/[^a-z0-9:_\-]/', '', $screen);
			return is_string($sanitized) ? $sanitized : '';
		}

		/**
		 * @param array<int,string> $caps_any
		 */
		private function user_matches_caps_any(WP_User $user, array $caps_any): bool
		{
			if (empty($caps_any)) {
				return true;
			}

			foreach ($caps_any as $cap) {
				if ($cap !== '' && user_can($user, $cap)) {
					return true;
				}
			}

			return false;
		}

		/**
		 * @param array<int,string> $caps_all
		 */
		private function user_matches_caps_all(WP_User $user, array $caps_all): bool
		{
			foreach ($caps_all as $cap) {
				if ($cap !== '' && !user_can($user, $cap)) {
					return false;
				}
			}

			return true;
		}

		/**
		 * @param array<int,string> $roles_any
		 */
		private function user_matches_roles_any(WP_User $user, array $roles_any): bool
		{
			if (empty($roles_any)) {
				return true;
			}

			$user_roles = isset($user->roles) && is_array($user->roles) ? array_map('sanitize_key', $user->roles) : array();
			foreach ($roles_any as $role) {
				if ($role !== '' && in_array($role, $user_roles, true)) {
					return true;
				}
			}

			return false;
		}

		/**
		 * @param array<int,string> $roles_all
		 */
		private function user_matches_roles_all(WP_User $user, array $roles_all): bool
		{
			if (empty($roles_all)) {
				return true;
			}

			$user_roles = isset($user->roles) && is_array($user->roles) ? array_map('sanitize_key', $user->roles) : array();
			foreach ($roles_all as $role) {
				if ($role !== '' && !in_array($role, $user_roles, true)) {
					return false;
				}
			}

			return true;
		}

	}
}
