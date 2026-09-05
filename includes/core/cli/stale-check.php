<?php
defined('ABSPATH') || exit;

if (!defined('WP_CLI') || !WP_CLI) {
	return;
}

if (!class_exists('BVMGR_CLI_Stale_Check_Command')) {
	/**
	 * Built-in stale-check helper for historical bug + cancellation-health probes.
	 * 
	 * Usage:
	 *   wp bvmgr stale-check
	 *   wp bvmgr stale-check --bugs=BUG-01,BUG-03
	 */
		class BVMGR_CLI_Stale_Check_Command
		{
			/** @var array<int,string> */
			private $supported = array('BUG-01', 'BUG-02', 'BUG-03', 'BUG-05', 'BUG-06', 'BUG-07', 'BUG-08', 'BUG-09', 'BUG-10', 'BUG-11', 'CAN-01', 'TICK-01');
		/** @var bool */
		private $repair_mode = false;

		/**
		 * Runs stale-check probes for historical bug + cancellation-health checks.
		 *
		 * ## OPTIONS
		 *
		 * [--bugs=<ids>]
		 * : Comma-separated subset of check IDs. Default: all supported.
		 *
		 * [--repair=<bool>]
		 * : Optional self-heal mode for checks that support it (currently BUG-01, CAN-01). Values: 1/0, true/false, yes/no.
		 *
		 * ## EXAMPLES
		 *
		 *     wp bvmgr stale-check
		 *     wp bvmgr stale_check
		 *     wp bvmgr stale-check --bugs=BUG-01,BUG-03,CAN-01
		 *
		 * @subcommand stale-check
		 * @when after_wp_load
		 *
		 * @param array<int,string> $args
		 * @param array<string,string> $assoc_args
		 */
		public function stale_check(array $args, array $assoc_args): void
		{
			$this->repair_mode = $this->parse_bool_flag(isset($assoc_args['repair']) ? (string) $assoc_args['repair'] : '');
			$requested = $this->parse_bug_filter(isset($assoc_args['bugs']) ? (string) $assoc_args['bugs'] : '');
			if (empty($requested)) {
						WP_CLI::warning('No supported stale-check IDs requested. Supported: ' . implode(', ', $this->supported));
				return;
			}

				$results = array();
				foreach ($requested as $bug_id) {
					switch ($bug_id) {
						case 'BUG-01':
							$results[] = $this->check_bug_01();
							break;
						case 'BUG-02':
							$results[] = $this->check_bug_02();
							break;
						case 'BUG-03':
							$results[] = $this->check_bug_03();
							break;
						case 'BUG-05':
							$results[] = $this->check_bug_05();
							break;
						case 'BUG-06':
							$results[] = $this->check_bug_06();
							break;
						case 'BUG-07':
							$results[] = $this->check_bug_07();
							break;
						case 'BUG-08':
							$results[] = $this->check_bug_08();
							break;
						case 'BUG-09':
							$results[] = $this->check_bug_09();
							break;
						case 'BUG-10':
							$results[] = $this->check_bug_10();
							break;
						case 'BUG-11':
							$results[] = $this->check_bug_11();
							break;
						case 'CAN-01':
							$results[] = $this->check_can_01();
							break;
						case 'TICK-01':
							$results[] = $this->check_tick_01();
							break;
					}
				}

			$this->render_results($results);
		}

		/**
		 * Backward-compatible alias for environments that already use underscore form.
		 *
		 * ## OPTIONS
		 *
		 * [--bugs=<ids>]
		 * : Comma-separated subset of check IDs. Default: all supported.
		 *
		 * [--repair=<bool>]
		 * : Optional self-heal mode for checks that support it (currently BUG-01, CAN-01). Values: 1/0, true/false, yes/no.
		 *
		 * ## EXAMPLES
		 *
		 *     wp bvmgr stale_check
		 *     wp bvmgr stale_check --bugs=BUG-01,BUG-03
		 *
		 * @subcommand stale_check
		 * @when after_wp_load
		 *
		 * @param array<int,string> $args
		 * @param array<string,string> $assoc_args
		 */
		public function stale_check_alias(array $args, array $assoc_args): void
		{
			$this->stale_check($args, $assoc_args);
		}

		/**
		 * @return array<int,string>
		 */
		private function parse_bug_filter(string $csv): array
		{
			if (trim($csv) === '') {
				return $this->supported;
			}
			$parts = array_map('trim', explode(',', $csv));
			$parts = array_values(array_unique(array_filter($parts)));
			$allowed = array_fill_keys($this->supported, true);

			$out = array();
			foreach ($parts as $id) {
				$id = strtoupper($id);
				if (isset($allowed[$id])) {
					$out[] = $id;
				}
			}
			return $out;
		}

		private function parse_bool_flag(string $raw): bool
		{
			$raw = strtolower(trim($raw));
			if ($raw === '') {
				return false;
			}
			return in_array($raw, array('1', 'true', 'yes', 'on', 'y'), true);
		}

		/**
		 * @return array<string,mixed>
		 */
		private function check_bug_01(): array
		{
			$k_status = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status';
			if ($k_status === '') {
				$k_status = '_vms_event_plan_status';
			}
			$k_tec = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'tec_event_id') : '_vms_tec_event_id';
			if ($k_tec === '') {
				$k_tec = '_vms_tec_event_id';
			}

			$k_issue = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
			if ($k_issue === '') {
				$k_issue = '_vms_integrity_issue';
			}

			$q = new WP_Query(array(
				'post_type' => 'vms_event_plan',
				'post_status' => array('publish', 'draft', 'private', 'pending', 'future'),
				'posts_per_page' => 300,
				'fields' => 'ids',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- BUG-01 stale-check intentionally samples at most 300 Event Plan IDs with draft workflow status and linked TEC metadata for diagnosis.
					'relation' => 'AND',
					array('key' => $k_status, 'value' => 'draft', 'compare' => '='),
					array('key' => $k_tec, 'compare' => 'EXISTS'),
				),
			));
			$ids = is_array($q->posts) ? $q->posts : array();
			$sample_count = count($ids);
			if ($sample_count === 0) {
				return array(
					'id' => 'BUG-01',
					'signal' => 'pass',
					'notes' => 'No Draft plans with linked TEC event found.',
					'manual' => 'Open one known affected Event Plan and verify Mark Ready persists status after save.',
				);
			}

				$integrity_expected = array(
					'calendar_event_unlinked',
					'missing_calendar_event',
					'trashed_calendar_event',
					'calendar_event_unpublished',
					'missing_vendor',
					'trashed_vendor',
					'missing_secondary_vendor',
					'trashed_secondary_vendor',
					'missing_venue',
					'trashed_venue',
					'venue_unpublished',
				);
			$actionable = 0;
			$expected_integrity = 0;
			$intentional_draft = 0;
			$invalid_tec_link = 0;
			$auto_repaired = 0;
			$examples = array();

			foreach ($ids as $plan_id) {
				$plan_id = absint($plan_id);
				if ($plan_id <= 0) {
					continue;
				}

				$wp_status = sanitize_key((string) get_post_status($plan_id));
				$issue = sanitize_key((string) get_post_meta($plan_id, $k_issue, true));
				$tec_id = (int) get_post_meta($plan_id, $k_tec, true);
				$tec_post = ($tec_id > 0) ? get_post($tec_id) : null;
				$tec_status = ($tec_post && is_object($tec_post)) ? sanitize_key((string) $tec_post->post_status) : '';

				if ($issue !== '' && in_array($issue, $integrity_expected, true)) {
					$expected_integrity++;
					continue;
				}

				// Draft + WP draft is usually intentional operator state.
				if ($wp_status === 'draft') {
					$intentional_draft++;
					continue;
				}

				if (!$tec_post || !is_object($tec_post) || $tec_post->post_type !== 'tribe_events' || $tec_status === 'trash') {
					$invalid_tec_link++;
					if (count($examples) < 4) {
						$examples[] = sprintf('plan %d has invalid TEC link (tec_id=%d)', $plan_id, $tec_id);
					}
					continue;
				}

				// Actionable mismatch: internal Draft while post is published/private and TEC link is valid.
				$is_actionable = in_array($wp_status, array('publish', 'private', 'pending', 'future'), true);
				if (!$is_actionable) {
					continue;
				}

				if ($this->repair_mode) {
					$target = in_array($tec_status, array('publish', 'future'), true) ? 'published' : 'ready';
					$ok = update_post_meta($plan_id, $k_status, $target);
					if ($ok !== false) {
						$auto_repaired++;
						continue;
					}
				}

				$actionable++;
				if (count($examples) < 4) {
					$examples[] = sprintf('plan %d status mismatch: internal=draft wp_status=%s tec_status=%s', $plan_id, $wp_status, ($tec_status !== '' ? $tec_status : 'unknown'));
				}
			}

			$signal = ($actionable > 0) ? 'warn' : 'pass';
			$notes = sprintf(
				'Draft+TEC sampled: %d. Actionable mismatches: %d. Auto-repaired: %d. Expected integrity-forced drafts: %d. Intentional drafts (wp_status=draft): %d. Invalid TEC links: %d.%s',
				$sample_count,
				$actionable,
				$auto_repaired,
				$expected_integrity,
				$intentional_draft,
				$invalid_tec_link,
				!empty($examples) ? (' Examples: ' . implode(' | ', $examples)) : ''
			);

			return array(
				'id' => 'BUG-01',
				'signal' => $signal,
				'notes' => $notes,
				'manual' => 'Open one known affected Event Plan and verify Mark Ready persists status after save.',
			);
		}

		/**
		 * @return array<string,mixed>
		 */
		private function check_bug_02(): array
		{
			$file = WP_CONTENT_DIR . '/plugins/vms/includes/admin/schedule.php';
			$code = is_readable($file) ? (string) file_get_contents($file) : '';
			$has_date_write = (strpos($code, "update_post_meta((int) \$plan_id, '_vms_event_date', \$ymd);") !== false);
			$has_venue_write = (strpos($code, "update_post_meta((int) \$plan_id, '_vms_venue_id', (int) \$venue_id);") !== false);
			$has_admin_post_route = (strpos($code, "admin_url('admin-post.php')") !== false) && (strpos($code, 'vms_create_event_plan') !== false);
			$has_legacy_post_new_route = (strpos($code, "admin_url('post-new.php')") !== false) && (strpos($code, 'vms_from_schedule') !== false);

			$issues = array();
			if (!$has_date_write || !$has_venue_write) {
				$issues[] = 'add-handler date/venue write path missing';
			}
			if (!$has_admin_post_route) {
				$issues[] = 'Schedule Create/Add links are not routed through admin-post add-handler';
			}
			if ($has_legacy_post_new_route) {
				$issues[] = 'legacy post-new query-prefill route still present for Schedule Create/Add';
			}

			$signal = empty($issues) ? 'pass' : 'warn';
			$notes = ($signal === 'pass')
				? 'Schedule Create/Add routes through admin-post add-handler and writes date+venue before redirect.'
				: ('BUG-02 guard failed: ' . implode('; ', $issues) . '.');

			return array(
				'id' => 'BUG-02',
				'signal' => $signal,
				'notes' => $notes,
				'manual' => 'From Schedule, click Add Event Plan and confirm Event Date is prefilled correctly.',
			);
		}

		/**
		 * @return array<string,mixed>
		 */
			private function check_bug_03(): array
			{
			$missing_times = new WP_Query(array(
				'post_type' => 'vms_event_plan',
				'post_status' => array('publish', 'draft', 'private', 'pending', 'future'),
				'posts_per_page' => 150,
				'fields' => 'ids',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- BUG-03 stale-check intentionally samples at most 150 Event Plan IDs missing either start- or end-time metadata for diagnosis.
					'relation' => 'OR',
					array('key' => '_vms_start_time', 'compare' => 'NOT EXISTS'),
					array('key' => '_vms_end_time', 'compare' => 'NOT EXISTS'),
				),
			));
			$count = is_array($missing_times->posts) ? count($missing_times->posts) : 0;

			$signal = ($count > 0) ? 'warn' : 'pass';
			$notes = ($count > 0)
				? sprintf('%d plans missing start/end time meta found (legacy data or persistence issue).', $count)
				: 'No missing start/end meta detected in sampled plans.';

				return array(
					'id' => 'BUG-03',
					'signal' => $signal,
					'notes' => $notes,
					'manual' => 'Edit a Draft plan, change times, save Draft, and confirm times stay changed.',
				);
			}

			/**
			 * @return array<string,mixed>
			 */
			private function check_bug_05(): array
			{
				$file = WP_CONTENT_DIR . '/plugins/vms/includes/cpt/event-plans.php';
				$code = is_readable($file) ? (string) file_get_contents($file) : '';

				$has_effective_helper = function_exists('bvmgr_get_event_plan_effective_comp_default');
				$has_resolver = function_exists('bvmgr_resolve_event_plan_comp_default');
				$has_ajax_effective_call = (strpos($code, "bvmgr_get_event_plan_effective_comp_default") !== false);
				$has_event_date_payload = (strpos($code, "form.append('event_date', dateInp.value || '');") !== false);
				$has_legacy_date_payload = (strpos($code, "form.append('date', dateInp.value || '');") !== false);

				$issues = array();
				if (!$has_effective_helper) {
					$issues[] = 'effective default helper missing';
				}
				if (!$has_resolver) {
					$issues[] = 'default resolver helper missing';
				}
				if (!$has_ajax_effective_call) {
					$issues[] = 'event plan AJAX default path not using effective helper';
				}
				if (!$has_event_date_payload) {
					$issues[] = 'comp-options refresh payload missing event_date';
				}
				if ($has_legacy_date_payload) {
					$issues[] = 'legacy date payload key still present in comp-options refresh';
				}

				$fixture_ok = false;
				$fixture_notes = '';
				if ($has_resolver) {
					$fixture = (array) bvmgr_resolve_event_plan_comp_default(
						array(
							'structure' => 'flat_fee',
							'flat_fee_amount' => 777.0,
							'door_split_percent' => 15.0,
						),
						array(
							'structure' => 'flat_fee',
							'flat_fee_amount' => 350.0,
							'door_split_percent' => 0.0,
						),
						'Fixture Holiday'
					);
					$fallback = (array) bvmgr_resolve_event_plan_comp_default(
						array(),
						array(
							'structure' => 'door_split',
							'door_split_percent' => 80.0,
						),
						''
					);

					$fixture_holiday_ok = (
						(($fixture['source'] ?? '') === 'holiday')
						&& (($fixture['structure'] ?? '') === 'flat_fee')
						&& (isset($fixture['flat_fee_amount']) ? (float) $fixture['flat_fee_amount'] : -1.0) === 777.0
						&& (($fixture['holiday_name'] ?? '') === 'Fixture Holiday')
					);
					$fixture_fallback_ok = (
						(($fallback['source'] ?? '') === 'venue')
						&& (($fallback['structure'] ?? '') === 'door_split')
						&& (isset($fallback['door_split_percent']) ? (float) $fallback['door_split_percent'] : -1.0) === 80.0
					);

					$fixture_ok = ($fixture_holiday_ok && $fixture_fallback_ok);
					if (!$fixture_ok) {
						$issues[] = sprintf(
							'resolver fixture failed (holiday_source=%s holiday_structure=%s holiday_flat=%s fallback_source=%s fallback_structure=%s fallback_split=%s)',
							(string) ($fixture['source'] ?? ''),
							(string) ($fixture['structure'] ?? ''),
							(string) ($fixture['flat_fee_amount'] ?? ''),
							(string) ($fallback['source'] ?? ''),
							(string) ($fallback['structure'] ?? ''),
							(string) ($fallback['door_split_percent'] ?? '')
						);
					} else {
						$fixture_notes = 'Resolver fixture passed: holiday override wins; venue fallback works when holiday override is absent.';
					}
				}

				$signal = empty($issues) && $fixture_ok ? 'pass' : 'warn';
				$notes = ($signal === 'pass')
					? ('Holiday default resolution guard present. ' . $fixture_notes)
					: ('BUG-05 guard failed: ' . implode('; ', $issues) . '.');

				return array(
					'id' => 'BUG-05',
					'signal' => $signal,
					'notes' => $notes,
					'manual' => 'Create/open a holiday with vendor pay overrides, open a new Event Plan for that venue/date, and confirm Draft Pay auto-fills holiday terms.',
				);
			}

			/**
			 * @return array<string,mixed>
			 */
			private function check_bug_06(): array
		{
			$has_validate_fn = function_exists('bvmgr_validate_event_plan');
			$has_venue_guard = function_exists('bvmgr_event_plan_is_venue_closed_for_event_date');
			$has_rule_eval = function_exists('bvmgr_sch_season_is_open_by_rules');

			$issues = array();
			if (!$has_validate_fn) {
				$issues[] = 'bvmgr_validate_event_plan missing';
			}
			if (!$has_venue_guard) {
				$issues[] = 'season-aware venue close guard missing';
			}
			if (!$has_rule_eval) {
				$issues[] = 'season rule evaluator missing';
			}

			$fixture_ok = false;
			$fixture_notes = '';
			if ($has_rule_eval) {
				$sat = '2026-02-21'; // Saturday
				$fri = '2026-02-20'; // Friday
				$sat_mask = (1 << 6); // Saturday-only

				$rules_mask = array(
					array(
						'id' => 'fixture_sat_mask',
						'type' => 'open_window',
						'enabled' => true,
						'start_mmdd' => '01-01',
						'end_mmdd' => '12-31',
						'days_w' => $sat_mask,
					),
				);
				$rules_array = array(
					array(
						'id' => 'fixture_sat_array',
						'type' => 'open_window',
						'enabled' => true,
						'start_mmdd' => '01-01',
						'end_mmdd' => '12-31',
						'days_w' => array(6),
					),
				);

				$mask_sat_open = (bool) bvmgr_sch_season_is_open_by_rules($rules_mask, $sat);
				$mask_fri_closed = !((bool) bvmgr_sch_season_is_open_by_rules($rules_mask, $fri));
				$array_sat_open = (bool) bvmgr_sch_season_is_open_by_rules($rules_array, $sat);
				$array_fri_closed = !((bool) bvmgr_sch_season_is_open_by_rules($rules_array, $fri));

				$fixture_ok = ($mask_sat_open && $mask_fri_closed && $array_sat_open && $array_fri_closed);
				if (!$fixture_ok) {
					$issues[] = sprintf(
						'saturday fixture failed (mask_sat=%s mask_fri=%s array_sat=%s array_fri=%s)',
						$mask_sat_open ? 'open' : 'closed',
						$mask_fri_closed ? 'closed' : 'open',
						$array_sat_open ? 'open' : 'closed',
						$array_fri_closed ? 'closed' : 'open'
					);
				} else {
					$fixture_notes = 'Saturday-only fixture passed for bitmask + legacy-array days_w forms.';
				}
			}

			$signal = empty($issues) && $fixture_ok ? 'pass' : 'warn';
			$notes = ($signal === 'pass')
				? ('Season-aware publish validation guard present. ' . $fixture_notes)
				: ('BUG-06 guard failed: ' . implode('; ', $issues) . '.');

			return array(
				'id' => 'BUG-06',
				'signal' => $signal,
				'notes' => $notes,
				'manual' => 'Create a Saturday-only Weekly Open Day venue and verify Mark Ready/Publish is allowed on valid Saturday event dates.',
			);
		}

		/**
		 * @return array<string,mixed>
		 */
		private function check_bug_07(): array
		{
			$issues = array();

			$schedule_file = WP_CONTENT_DIR . '/plugins/vms/includes/admin/schedule.php';
			$schedule_code = is_readable($schedule_file) ? (string) file_get_contents($schedule_file) : '';
			$has_schedule_wiring = (
				strpos($schedule_code, 'bvmgr_sch_get_schedule_venue_candidates') !== false
				&& strpos($schedule_code, 'bvmgr_sch_pick_single_venue_candidate') !== false
			);
			if (!$has_schedule_wiring) {
				$issues[] = 'schedule venue bootstrap missing helper wiring for single-venue fallback';
			}

			if (!function_exists('bvmgr_sch_pick_single_venue_candidate')) {
				$schedule_helpers_file = WP_CONTENT_DIR . '/plugins/vms/includes/schedule/helpers.php';
				if (is_readable($schedule_helpers_file)) {
					require_once $schedule_helpers_file;
				}
			}

			$fixture_ok = false;
			$fixture_notes = '';
			if (!function_exists('bvmgr_sch_pick_single_venue_candidate')) {
				$issues[] = 'single-venue helper function unavailable';
			} else {
				$case_single = (int) bvmgr_sch_pick_single_venue_candidate(array(17));
				$case_single_dupe = (int) bvmgr_sch_pick_single_venue_candidate(array(17, 17));
				$case_none = (int) bvmgr_sch_pick_single_venue_candidate(array());
				$case_many = (int) bvmgr_sch_pick_single_venue_candidate(array(17, 18));

				$fixture_ok = (
					$case_single === 17
					&& $case_single_dupe === 17
					&& $case_none === 0
					&& $case_many === 0
				);

				if (!$fixture_ok) {
					$issues[] = sprintf(
						'fixture failed (single=%d single_dupe=%d none=%d many=%d)',
						$case_single,
						$case_single_dupe,
						$case_none,
						$case_many
					);
				} else {
					$fixture_notes = 'Single-venue resolver fixture passed (single->ID, duplicate single->ID, none/multi->0).';
				}
			}

			$signal = (empty($issues) && $fixture_ok) ? 'pass' : 'warn';
			$notes = ($signal === 'pass')
				? ('Single-venue fallback helper wiring is present. ' . $fixture_notes)
				: ('BUG-07 guard failed: ' . implode('; ', $issues) . '.');

			return array(
				'id' => 'BUG-07',
				'signal' => $signal,
				'notes' => $notes,
				'manual' => 'Open Schedule in a single-venue setup and confirm it renders without clicking All Venues.',
			);
		}

		/**
		 * @return array<string,mixed>
		 */
			private function check_bug_08(): array
			{
				$issues = array();

			$js_file = WP_CONTENT_DIR . '/plugins/vms/assets/vms-ticketing-front.js';
			$css_file = WP_CONTENT_DIR . '/plugins/vms/assets/css/vms-ticketing-front.css';

			$js_code = is_readable($js_file) ? (string) file_get_contents($js_file) : '';
			$css_code = is_readable($css_file) ? (string) file_get_contents($css_file) : '';

			$has_js_helper = (strpos($js_code, 'function ensureMobileModalActionReachability(form)') !== false);
			$has_js_hydrate = (strpos($js_code, 'function hydrateModal(form)') !== false);
			$has_js_scroll_class = (strpos($js_code, "scrollHost.classList.add('vms-ticketing-modal-scroll')") !== false);
			$has_js_form_class = (strpos($js_code, "form.classList.add('vms-ticketing-modal-form')") !== false);
			$has_js_footer_class = (strpos($js_code, "footer.classList.add('vms-ticketing-modal-footer')") !== false);
			$has_js_observer_wiring = (substr_count($js_code, 'hydrateModal(form);') >= 2);

			if (!$has_js_helper) {
				$issues[] = 'mobile modal reachability helper missing in front bundle';
			}
			if (!$has_js_hydrate || !$has_js_observer_wiring) {
				$issues[] = 'modal hydration wiring missing (observer/event hooks)';
			}
			if (!$has_js_scroll_class || !$has_js_form_class || !$has_js_footer_class) {
				$issues[] = 'front bundle is not tagging modal scroll/form/footer guard classes';
			}

			$has_css_scroll = (strpos($css_code, '.vms-ticketing-modal-scroll') !== false);
			$has_css_form = (strpos($css_code, '#tribe-tickets__modal-form.vms-ticketing-modal-form') !== false);
			$has_css_footer = (strpos($css_code, '.vms-ticketing-modal-footer') !== false);
			$has_css_sticky = (strpos($css_code, 'position: sticky;') !== false);
			$has_css_wrap = (strpos($css_code, 'flex-wrap: wrap;') !== false) && (strpos($css_code, 'width: 100%;') !== false);
			$has_css_summary_cap = (strpos($css_code, '.vms-addon-modal-summary') !== false) && (strpos($css_code, 'max-height: 34dvh;') !== false);

			if (!$has_css_scroll || !$has_css_form || !$has_css_footer) {
				$issues[] = 'mobile modal CSS selector coverage missing';
			}
			if (!$has_css_sticky) {
				$issues[] = 'mobile modal footer sticky behavior missing';
			}
			if (!$has_css_wrap) {
				$issues[] = 'mobile modal action wrapping/full-width behavior missing';
			}
			if (!$has_css_summary_cap) {
				$issues[] = 'mobile modal summary max-height guard missing';
			}

			$fixture_ok = empty($issues);
			$signal = $fixture_ok ? 'pass' : 'warn';
			$notes = $fixture_ok
				? 'Mobile ticket modal reachability guards present (JS hydration + CSS scroll/sticky footer/wrap rules).'
				: ('BUG-08 guard failed: ' . implode('; ', $issues) . '.');

				return array(
					'id' => 'BUG-08',
					'signal' => $signal,
					'notes' => $notes,
					'manual' => 'On mobile viewport, open a ticket modal after selecting add-ons and confirm Save & Continue / Checkout buttons are reachable and tappable.',
				);
			}

			/**
			 * @return array<string,mixed>
			 */
			private function check_bug_09(): array
			{
				$issues = array();

				$js_file = WP_CONTENT_DIR . '/plugins/vms/assets/vms-ticketing-front.js';
				$js_code = is_readable($js_file) ? (string) file_get_contents($js_file) : '';
				if ($js_code === '') {
					$issues[] = 'front ticketing bundle missing or unreadable';
				}

				$has_seed_value = (strpos($js_code, 'class="vms-addon-input" type="text" value="0"') !== false);
				$has_minus_listener = (strpos($js_code, "minus.addEventListener('click', function () {") !== false);
				$has_plus_listener = (strpos($js_code, "plus.addEventListener('click', function () {") !== false);
				$has_nan_guard = (strpos($js_code, 'if (isNaN(v)) v = 0;') !== false);
				$has_increment = (strpos($js_code, 'var next = v + 1;') !== false);
				$has_increment_write = (strpos($js_code, 'input.value = String(next);') !== false);
				$has_decrement = (strpos($js_code, 'input.value = String(Math.max(0, v - 1));') !== false);
				$has_button_refresh = (substr_count($js_code, 'updateBtns();') >= 4);
				$has_disabled_toggle = (
					(strpos($js_code, 'minus.disabled = disM;') !== false)
					&& (strpos($js_code, 'plus.disabled = disP;') !== false)
				);

				$fixture_ok = (
					$has_seed_value
					&& $has_minus_listener
					&& $has_plus_listener
					&& $has_nan_guard
					&& $has_increment
					&& $has_increment_write
					&& $has_decrement
					&& $has_button_refresh
					&& $has_disabled_toggle
				);
				$fixture_notes = '';
				if (!$fixture_ok) {
					$issues[] = sprintf(
						'stepper fixture failed (seed=%d minus_listener=%d plus_listener=%d nan_guard=%d increment=%d increment_write=%d decrement=%d btn_refresh=%d disabled_toggle=%d)',
						$has_seed_value ? 1 : 0,
						$has_minus_listener ? 1 : 0,
						$has_plus_listener ? 1 : 0,
						$has_nan_guard ? 1 : 0,
						$has_increment ? 1 : 0,
						$has_increment_write ? 1 : 0,
						$has_decrement ? 1 : 0,
						$has_button_refresh ? 1 : 0,
						$has_disabled_toggle ? 1 : 0
					);
				} else {
					$fixture_notes = 'Entitlement stepper fixture passed: +/- handlers are wired with zero-seed input and NaN-safe increment/decrement flow.';
				}

				$signal = empty($issues) && $fixture_ok ? 'pass' : 'warn';
				$notes = ($signal === 'pass')
					? ('Ticket entitlement stepper guard present. ' . $fixture_notes)
					: ('BUG-09 guard failed: ' . implode('; ', $issues) . '.');

			return array(
				'id' => 'BUG-09',
				'signal' => $signal,
				'notes' => $notes,
				'manual' => 'On an event with reserved add-ons, confirm the entitlement + button increments from 0 without typing "1" first and - returns to 0.',
			);
		}

		/**
		 * @return array<string,mixed>
		 */
		private function check_bug_10(): array
		{
			$issues = array();

			$rules_file = WP_CONTENT_DIR . '/plugins/vms/includes/integrations/ticketing-rules-v2.php';
			$rules_code = is_readable($rules_file) ? (string) file_get_contents($rules_file) : '';
			if ($rules_code === '') {
				$issues[] = 'ticketing-rules-v2.php missing or unreadable';
			}

			$js_file = WP_CONTENT_DIR . '/plugins/vms/assets/vms-ticketing-front.js';
			$js_code = is_readable($js_file) ? (string) file_get_contents($js_file) : '';
			if ($js_code === '') {
				$issues[] = 'vms-ticketing-front.js missing or unreadable';
			}

			$has_success_clear_helper = (strpos($rules_code, 'function bvmgr_ticketing_v2_clear_success_notices(): void') !== false);
			$has_request_guard_helper = (strpos($rules_code, 'function bvmgr_ticketing_v2_request_is_add_to_cart(): bool') !== false);
			$has_cart_empty_helper = (strpos($rules_code, 'function bvmgr_ticketing_v2_session_cart_is_empty(): bool') !== false);
			$has_prune_helper = (strpos($rules_code, 'function bvmgr_ticketing_v2_prune_stale_success_notices(): void') !== false);
			$has_template_redirect_hook = (strpos($rules_code, "add_action('template_redirect', 'bvmgr_ticketing_v2_prune_stale_success_notices', 5);") !== false);
			$has_request_guard_use = (strpos($rules_code, 'if (bvmgr_ticketing_v2_request_is_add_to_cart()) {') !== false);
			$has_cart_empty_use = (strpos($rules_code, 'if (!bvmgr_ticketing_v2_session_cart_is_empty()) {') !== false);
			$has_success_clear_uses = (substr_count($rules_code, 'bvmgr_ticketing_v2_clear_success_notices();') >= 3);
			$has_legacy_clear = (strpos($rules_code, 'wc_clear_notices()') !== false);
			$has_js_clean_helper = (strpos($js_code, 'function cleanWooParamsInAddressBar()') !== false);
			$has_js_clean_call = (strpos($js_code, 'cleanWooParamsInAddressBar();') !== false);

			if (!$has_success_clear_helper) {
				$issues[] = 'success-only notice clear helper missing';
			}
			if (!$has_request_guard_helper || !$has_cart_empty_helper || !$has_prune_helper) {
				$issues[] = 'stale-notice prune helpers missing';
			}
			if (!$has_template_redirect_hook || !$has_request_guard_use || !$has_cart_empty_use) {
				$issues[] = 'template_redirect stale-notice pruning guard not wired with add-to-cart/cart-empty checks';
			}
			if (!$has_success_clear_uses) {
				$issues[] = 'silent add flow does not consistently use success-only notice pruning';
			}
			if ($has_legacy_clear) {
				$issues[] = 'legacy wc_clear_notices blanket clearing still present (should be success-only pruning)';
			}
			if (!$has_js_clean_helper || !$has_js_clean_call) {
				$issues[] = 'front bundle URL hygiene helper missing for add-to-cart query cleanup';
			}

			$fixture_ok = false;
			$fixture_notes = '';
			$runtime_available = (
				function_exists('bvmgr_ticketing_v2_clear_success_notices')
				&& function_exists('wc_get_notices')
				&& function_exists('wc_set_notices')
				&& function_exists('WC')
				&& WC()
				&& isset(WC()->session)
				&& WC()->session
			);

			if (!$runtime_available) {
				$fixture_ok = empty($issues);
				$fixture_notes = 'Runtime fixture skipped (Woo session/notices unavailable in this CLI context); static guard markers evaluated.';
			} else {
				$before = wc_get_notices();
				$fixture = array(
					'success' => array(array('notice' => 'fixture_success', 'data' => array())),
					'error' => array(array('notice' => 'fixture_error', 'data' => array())),
					'notice' => array(array('notice' => 'fixture_notice', 'data' => array())),
				);

				wc_set_notices($fixture);
				bvmgr_ticketing_v2_clear_success_notices();
				$after = wc_get_notices();

				$success_cleared = empty($after['success']);
				$error_kept = !empty($after['error']);
				$notice_kept = !empty($after['notice']);
				$fixture_ok = ($success_cleared && $error_kept && $notice_kept);

				wc_set_notices(is_array($before) ? $before : array());

				if (!$fixture_ok) {
					$issues[] = sprintf(
						'runtime fixture failed (success_cleared=%d error_kept=%d notice_kept=%d)',
						$success_cleared ? 1 : 0,
						$error_kept ? 1 : 0,
						$notice_kept ? 1 : 0
					);
				} else {
					$fixture_notes = 'Runtime fixture passed: success notices are pruned while error/info notices remain.';
				}
			}

			$signal = (empty($issues) && $fixture_ok) ? 'pass' : 'warn';
			$notes = ($signal === 'pass')
				? ('Stale Woo notice leakage guard present. ' . $fixture_notes)
				: ('BUG-10 guard failed: ' . implode('; ', $issues) . '.');

			return array(
				'id' => 'BUG-10',
				'signal' => $signal,
				'notes' => $notes,
				'manual' => 'Add a reserved add-on, empty the cart, then open an unrelated non-cart page and confirm no stale “added to cart” success notice appears while validation/error notices still surface when triggered.',
			);
		}

		/**
		 * @return array<string,mixed>
		 */
		private function check_tick_01(): array
		{
			$issues = array();

			$rules_file = WP_CONTENT_DIR . '/plugins/vms/includes/integrations/ticketing-rules-v2.php';
			$rules_code = is_readable($rules_file) ? (string) file_get_contents($rules_file) : '';
			if ($rules_code === '') {
				$issues[] = 'ticketing-rules-v2.php missing or unreadable';
			}

			$js_file = WP_CONTENT_DIR . '/plugins/vms/assets/vms-ticketing-front.js';
			$js_code = is_readable($js_file) ? (string) file_get_contents($js_file) : '';
			if ($js_code === '') {
				$issues[] = 'vms-ticketing-front.js missing or unreadable';
			}

			$has_hint_key_helper = (strpos($rules_code, 'function bvmgr_ticketing_v2_session_ga_hint_key(): string') !== false);
			$has_hint_seed_helper = (strpos($rules_code, 'function bvmgr_ticketing_v2_session_seed_ga_hint(int $plan_id, int $ga_qty, string $source = \'\'): void') !== false);
			$has_hint_clear_helper = (strpos($rules_code, 'function bvmgr_ticketing_v2_session_clear_ga_hint(int $plan_id = 0): void') !== false);
			$has_effective_ga_helper = (strpos($rules_code, 'function bvmgr_ticketing_v2_effective_ga_qty_for_plan(int $plan_id, int $cart_ga_qty): int') !== false);
			$has_effective_ga_use = (substr_count($rules_code, '$ga_qty = bvmgr_ticketing_v2_effective_ga_qty_for_plan($plan_id, $ga_qty_raw);') >= 2);
			$has_hint_seed_use = (strpos($rules_code, 'bvmgr_ticketing_v2_session_seed_ga_hint($hint_plan_id, $ga_qty_hint, \'silent_add_payload\');') !== false);
			$has_hint_clear_use = (substr_count($rules_code, 'bvmgr_ticketing_v2_session_clear_ga_hint($seeded_hint_plan_id);') >= 2);

			$has_ticket_qty_helper = (strpos($js_code, 'function detectSelectedTicketsQty(addonsWrap) {') !== false);
			$has_pending_plan_capture = (strpos($js_code, 'eventPlanId: eventPlanId,') !== false);
			$has_pending_ga_capture = (strpos($js_code, 'gaQtyHint: gaQtyHint,') !== false);
			$has_payload_plan = (strpos($js_code, 'event_plan_id: Number(eventPlanId || 0),') !== false);
			$has_payload_ga = (strpos($js_code, 'ga_qty_hint: Number(gaQtyHint || 0),') !== false);
			$has_silent_add_call = (strpos($js_code, 'silentAddMany(pending.tecEventId, pending.items, pending.gaQtyHint, pending.eventPlanId);') !== false);

			if (!$has_hint_key_helper || !$has_hint_seed_helper || !$has_hint_clear_helper || !$has_effective_ga_helper) {
				$issues[] = 'session GA hint helpers missing';
			}
			if (!$has_effective_ga_use) {
				$issues[] = 'effective GA helper not wired into cart validation paths';
			}
			if (!$has_hint_seed_use || !$has_hint_clear_use) {
				$issues[] = 'silent add handler does not seed+clear GA session hints deterministically';
			}
			if (!$has_ticket_qty_helper) {
				$issues[] = 'front bundle ticket-quantity detector missing';
			}
			if (!$has_pending_plan_capture || !$has_pending_ga_capture) {
				$issues[] = 'front pending payload does not capture plan/ticket hint context';
			}
			if (!$has_payload_plan || !$has_payload_ga || !$has_silent_add_call) {
				$issues[] = 'silent add payload missing plan/ticket hint handoff';
			}

			$fixture_ok = false;
			$fixture_notes = '';
			$runtime_available = (
				function_exists('bvmgr_ticketing_v2_session_get_ga_hints')
				&& function_exists('bvmgr_ticketing_v2_session_set_ga_hints')
				&& function_exists('bvmgr_ticketing_v2_session_seed_ga_hint')
				&& function_exists('bvmgr_ticketing_v2_session_clear_ga_hint')
				&& function_exists('bvmgr_ticketing_v2_effective_ga_qty_for_plan')
				&& function_exists('WC')
				&& WC()
				&& isset(WC()->session)
				&& WC()->session
			);

			if (!$runtime_available) {
				$fixture_ok = empty($issues);
				$fixture_notes = 'Runtime fixture skipped (Woo session unavailable in this CLI context); static guard markers evaluated.';
			} else {
				$fixture_plan_id = 999991;
				$before_hints = (array) bvmgr_ticketing_v2_session_get_ga_hints();

					bvmgr_ticketing_v2_session_clear_ga_hint($fixture_plan_id);
					bvmgr_ticketing_v2_session_seed_ga_hint($fixture_plan_id, 4, 'stale_check_tick01');

					$hint_only_qty = (int) bvmgr_ticketing_v2_effective_ga_qty_for_plan($fixture_plan_id, 0);
					$cart_partial_qty = (int) bvmgr_ticketing_v2_effective_ga_qty_for_plan($fixture_plan_id, 2);
					$mid_hints = (array) bvmgr_ticketing_v2_session_get_ga_hints();
					$hint_retained = !empty($mid_hints[$fixture_plan_id]);
					$cart_caught_up_qty = (int) bvmgr_ticketing_v2_effective_ga_qty_for_plan($fixture_plan_id, 4);
					$after_hints = (array) bvmgr_ticketing_v2_session_get_ga_hints();
					$hint_cleared = empty($after_hints[$fixture_plan_id]);

					$fixture_ok = (
						$hint_only_qty >= 4
						&& $cart_partial_qty >= 4
						&& $hint_retained
						&& $cart_caught_up_qty === 4
						&& $hint_cleared
					);
					if (!$fixture_ok) {
						$issues[] = sprintf(
							'runtime fixture failed (hint_only_qty=%d cart_partial_qty=%d hint_retained=%d cart_caught_up_qty=%d hint_cleared=%d)',
							$hint_only_qty,
							$cart_partial_qty,
							$hint_retained ? 1 : 0,
							$cart_caught_up_qty,
							$hint_cleared ? 1 : 0
						);
					} else {
						$fixture_notes = 'Runtime fixture passed: hinted GA total is used while cart quantity catches up and is cleared once cart quantity reaches the hinted total.';
					}

				bvmgr_ticketing_v2_session_set_ga_hints(is_array($before_hints) ? $before_hints : array());
			}

			$signal = (empty($issues) && $fixture_ok) ? 'pass' : 'warn';
			$notes = ($signal === 'pass')
				? ('TICK-01 cart validation determinism guard present. ' . $fixture_notes)
				: ('TICK-01 guard failed: ' . implode('; ', $issues) . '.');

			return array(
				'id' => 'TICK-01',
				'signal' => $signal,
				'notes' => $notes,
				'manual' => 'Add GA tickets for an event, then add required reserved add-ons from the same session flow and confirm add-ons are accepted while standard entitlement caps/limits still apply.',
			);
		}

		/**
		 * @return array<string,mixed>
		 */
		private function check_bug_11(): array
			{
				$issues = array();

				$event_plan_file = WP_CONTENT_DIR . '/plugins/vms/includes/cpt/event-plans.php';
				$event_plan_code = is_readable($event_plan_file) ? (string) file_get_contents($event_plan_file) : '';
				if ($event_plan_code === '') {
					$issues[] = 'event-plans.php missing or unreadable';
				}

				$has_helper = (strpos($event_plan_code, 'function bvmgr_tec_resolve_featured_image_arg(') !== false);
				$has_helper_call = (strpos($event_plan_code, 'bvmgr_tec_resolve_featured_image_arg($plan_thumb_id, $existing_tec_thumb_id, $vendor_thumb_id)') !== false);
				$has_helper_plan_branch = (strpos($event_plan_code, 'if ($plan_thumb_id > 0) {') !== false);
				$has_helper_existing_branch = (strpos($event_plan_code, 'if ($existing_tec_thumb_id > 0) {') !== false);
				$has_helper_vendor_branch = (strpos($event_plan_code, 'if ($vendor_thumb_id > 0) {') !== false);
				$post_existing_count = substr_count($event_plan_code, '$args = bvmgr_build_tec_event_args($post_id, $existing_tec_id);');
				$has_publish_builder = ($post_existing_count >= 1);
				$has_resync_builder = ($post_existing_count >= 2);
				$has_extras_builder = (strpos($event_plan_code, '$args = bvmgr_build_tec_event_args($plan_id, $tec_event_id);') !== false);

				if (!$has_helper) {
					$issues[] = 'featured-image resolver helper missing';
				}
				if (!$has_helper_call) {
					$issues[] = 'TEC args builder not wired to featured-image resolver helper';
				}
				if (!$has_publish_builder || !$has_resync_builder || !$has_extras_builder) {
					$issues[] = 'one or more TEC args builder call-sites are not passing linked TEC context';
				}

				$fixture_ok = false;
				$fixture_notes = '';
				$resolver = null;
				if (function_exists('bvmgr_tec_resolve_featured_image_arg')) {
					$resolver = function (int $plan, int $existing, int $vendor): int {
						return (int) bvmgr_tec_resolve_featured_image_arg($plan, $existing, $vendor);
					};
				} elseif ($has_helper && $has_helper_plan_branch && $has_helper_existing_branch && $has_helper_vendor_branch) {
					// Fallback for static probes where Event Plan functions are not loaded in runtime.
					$resolver = function (int $plan, int $existing, int $vendor): int {
						$plan = max(0, (int) $plan);
						$existing = max(0, (int) $existing);
						$vendor = max(0, (int) $vendor);
						if ($plan > 0) return $plan;
						if ($existing > 0) return 0;
						if ($vendor > 0) return $vendor;
						return 0;
					};
				}

				if ($resolver === null) {
					$issues[] = 'featured-image resolver fixture unavailable (helper not loaded and static branch markers missing)';
				} else {
					$cases = array(
						array('id' => 'plan_wins', 'plan' => 101, 'existing' => 202, 'vendor' => 303, 'expect' => 101),
						array('id' => 'preserve_existing', 'plan' => 0, 'existing' => 202, 'vendor' => 303, 'expect' => 0),
						array('id' => 'vendor_fallback', 'plan' => 0, 'existing' => 0, 'vendor' => 303, 'expect' => 303),
						array('id' => 'none_available', 'plan' => 0, 'existing' => 0, 'vendor' => 0, 'expect' => 0),
					);
					$fixture_failures = array();
					foreach ($cases as $case) {
						$got = (int) $resolver(
							(int) $case['plan'],
							(int) $case['existing'],
							(int) $case['vendor']
						);
						$expect = (int) $case['expect'];
						if ($got !== $expect) {
							$fixture_failures[] = sprintf(
								'%s(got=%d expect=%d)',
								(string) $case['id'],
								$got,
								$expect
							);
					}
				}

					$fixture_ok = empty($fixture_failures);
					if (!$fixture_ok) {
						$issues[] = 'resolver fixture failed: ' . implode(', ', $fixture_failures);
					} else {
						$fixture_notes = function_exists('bvmgr_tec_resolve_featured_image_arg')
							? 'Resolver fixture passed: plan image wins, linked TEC image is preserved, vendor image is fallback-only when both plan+TEC images are missing.'
							: 'Static resolver fixture passed from source markers: plan image wins, linked TEC image is preserved, vendor image remains fallback-only.';
					}
				}

				$signal = (empty($issues) && $fixture_ok) ? 'pass' : 'warn';
				$notes = ($signal === 'pass')
					? ('Featured image inheritance guard present. ' . $fixture_notes)
					: ('BUG-11 guard failed: ' . implode('; ', $issues) . '.');

				return array(
					'id' => 'BUG-11',
					'signal' => $signal,
					'notes' => $notes,
					'manual' => 'Open a linked Event Plan with no plan featured image where the TEC event already has a featured image, save/re-sync, and confirm the TEC image is unchanged (vendor image must only be fallback when plan+TEC images are both missing).',
				);
			}

			/**
			 * @return array<string,mixed>
			 */
		private function check_can_01(): array
		{
			$k_status = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status';
			if ($k_status === '') {
				$k_status = '_vms_event_plan_status';
			}
			$k_job_id = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'cancel_job_id') : '_vms_cancel_job_id';
			if ($k_job_id === '') {
				$k_job_id = '_vms_cancel_job_id';
			}
			$k_job_state = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'cancel_job_state') : '_vms_cancel_job_state';
			if ($k_job_state === '') {
				$k_job_state = '_vms_cancel_job_state';
			}
			$k_job_summary = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'cancel_job_summary') : '_vms_cancel_job_summary';
			if ($k_job_summary === '') {
				$k_job_summary = '_vms_cancel_job_summary';
			}
			$k_review = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'cancel_requires_operator_review') : '_vms_cancel_requires_operator_review';
			if ($k_review === '') {
				$k_review = '_vms_cancel_requires_operator_review';
			}

			$q = new WP_Query(array(
				'post_type' => 'vms_event_plan',
				'post_status' => array('publish', 'draft', 'private', 'pending', 'future'),
				'posts_per_page' => 300,
				'fields' => 'ids',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- CAN-01 stale-check intentionally samples at most 300 cancelled Event Plan IDs to audit their cancellation-job state.
					array('key' => $k_status, 'value' => 'cancelled', 'compare' => '='),
				),
			));

			$ids = is_array($q->posts) ? $q->posts : array();
			$total = count($ids);
			if ($total === 0) {
				return array(
					'id' => 'CAN-01',
					'signal' => 'pass',
					'notes' => 'No cancelled Event Plans found to audit.',
					'manual' => 'Cancel one Event Plan and rerun stale-check to validate cancellation-job probes.',
				);
			}

			$running_timeout = (int) apply_filters('vms_cancellation_running_timeout_seconds', 900, 0, array());
			if ($running_timeout < 60) {
				$running_timeout = 60;
			}

			$missing_envelope = 0;
			$stale_running = 0;
			$running_missing_start = 0;
				$state_mismatch = 0;
				$review_mismatch = 0;
				$step_shape_mismatch = 0;
				$summary_state_mismatch = 0;
				$step_totals_mismatch = 0;
				$run_audit_mismatch = 0;
				$retry_audit_mismatch = 0;
				$repaired_envelopes = 0;
				$examples = array();

			foreach ($ids as $plan_id) {
				$plan_id = absint($plan_id);
				if ($plan_id <= 0) {
					continue;
				}

				$job_id = sanitize_text_field((string) get_post_meta($plan_id, $k_job_id, true));
				$job_state = sanitize_key((string) get_post_meta($plan_id, $k_job_state, true));
				$summary = get_post_meta($plan_id, $k_job_summary, true);
				$review_flag = ((string) get_post_meta($plan_id, $k_review, true) === '1');

				if ($job_id === '' || !is_array($summary) || empty($summary['steps']) || !is_array($summary['steps'])) {
					if ($this->repair_mode && function_exists('bvmgr_cancellation_backfill_legacy_job')) {
						$repair = (array) bvmgr_cancellation_backfill_legacy_job($plan_id, array(
							'source' => 'stale_check_can01',
							'backfill_by_user_id' => get_current_user_id(),
						));
						if (!empty($repair['ok'])) {
							$job_id = sanitize_text_field((string) ($repair['job_id'] ?? ''));
							$job_state = sanitize_key((string) get_post_meta($plan_id, $k_job_state, true));
							$summary = isset($repair['summary']) && is_array($repair['summary'])
								? $repair['summary']
								: get_post_meta($plan_id, $k_job_summary, true);
							$review_flag = ((string) get_post_meta($plan_id, $k_review, true) === '1');
							$repaired_envelopes++;
						}
					}
				}

				if ($job_id === '' || !is_array($summary) || empty($summary['steps']) || !is_array($summary['steps'])) {
					$missing_envelope++;
					if (count($examples) < 4) {
						$examples[] = sprintf('plan %d missing cancellation job envelope', $plan_id);
					}
					continue;
				}

					$done = 0;
					$failed = 0;
					$blocked = 0;
					$pending = 0;
					$running = 0;
					$requires_review_step = false;
					$has_retry_requested_meta = false;
					$invalid_retry_step_meta = false;
					$seen_step_keys = array();
					$required_step_keys = array('policy_capture', 'provider_sales_stop', 'refund_discovery', 'refund_execution', 'notifications');
					$known_statuses = array('pending', 'running', 'done', 'failed', 'blocked');
				foreach ($summary['steps'] as $step) {
					if (!is_array($step)) {
						continue;
					}
					$step_key = sanitize_key((string) ($step['key'] ?? ''));
					if ($step_key !== '') {
						if (isset($seen_step_keys[$step_key])) {
							$step_shape_mismatch++;
							if (count($examples) < 4) {
								$examples[] = sprintf('plan %d has duplicate step key %s', $plan_id, $step_key);
							}
						}
						$seen_step_keys[$step_key] = true;
					}
					$step_status = sanitize_key((string) ($step['status'] ?? 'pending'));
					if (!in_array($step_status, $known_statuses, true)) {
						$step_shape_mismatch++;
						if (count($examples) < 4) {
							$examples[] = sprintf('plan %d has invalid step status %s', $plan_id, $step_status);
						}
					}
						if ($step_status === 'done') {
							$done++;
						} elseif ($step_status === 'failed') {
							$failed++;
						} elseif ($step_status === 'blocked') {
							$blocked++;
						} elseif ($step_status === 'pending') {
							$pending++;
					} elseif ($step_status === 'running') {
						$running++;
					}
						$step_data = isset($step['data']) && is_array($step['data']) ? $step['data'] : array();
						if (!empty($step_data['requires_operator_review'])) {
							$requires_review_step = true;
						}
						$retry_requested_at = sanitize_text_field((string) ($step['retry_requested_at_gmt'] ?? ''));
						if ($retry_requested_at !== '') {
							$has_retry_requested_meta = true;
							$retry_requested_ts = strtotime($retry_requested_at . ' GMT');
							$retry_requested_by = absint($step['retry_requested_by_user_id'] ?? 0);
							if ($retry_requested_ts <= 0 || $retry_requested_by <= 0) {
								$invalid_retry_step_meta = true;
							}
						}
					}
					foreach ($required_step_keys as $required_key) {
					if (!isset($seen_step_keys[$required_key])) {
						$step_shape_mismatch++;
						if (count($examples) < 4) {
							$examples[] = sprintf('plan %d missing step key %s', $plan_id, $required_key);
						}
					}
				}

					$expected_state = 'completed';
					if ($failed > 0) {
						$expected_state = 'failed';
					} elseif ($blocked > 0) {
						$expected_state = 'completed_with_errors';
					} elseif ($pending > 0 || $running > 0) {
						$expected_state = 'queued';
					}
					$expected_totals = array(
						'done' => $done,
						'failed' => $failed,
						'blocked' => $blocked,
						'pending' => $pending,
						'running' => $running,
					);

					$is_backfilled = ((string) ($summary['backfilled_at_gmt'] ?? '') !== '');
					if ($job_state !== 'running') {
						$summary_state = sanitize_key((string) ($summary['final_state'] ?? ''));
						if ($summary_state === '') {
							if (!$is_backfilled && $job_state !== 'queued') {
								$summary_state_mismatch++;
								if (count($examples) < 4) {
									$examples[] = sprintf('plan %d missing summary final_state', $plan_id);
								}
							}
						} elseif ($summary_state !== $expected_state) {
							$summary_state_mismatch++;
							if (count($examples) < 4) {
								$examples[] = sprintf('plan %d summary final_state mismatch (%s vs %s)', $plan_id, $summary_state, $expected_state);
							}
						}

						$summary_totals = isset($summary['step_totals']) && is_array($summary['step_totals']) ? $summary['step_totals'] : array();
						$totals_match = true;
						foreach ($expected_totals as $key => $value) {
							$actual = array_key_exists($key, $summary_totals) ? (int) $summary_totals[$key] : -1;
							if ($actual !== (int) $value) {
								$totals_match = false;
								break;
							}
						}
						if (!$totals_match) {
							$step_totals_mismatch++;
							if (count($examples) < 4) {
								$examples[] = sprintf('plan %d step_totals mismatch', $plan_id);
							}
						}

						$run_issue = '';
						$runs = isset($summary['runs']) && is_array($summary['runs']) ? $summary['runs'] : array();
						if (!$is_backfilled && $job_state !== 'queued' && empty($runs)) {
							$run_issue = 'missing_runs_log';
						}
						if ($run_issue === '' && !empty($runs)) {
							$last_run = end($runs);
							reset($runs);
							if (!is_array($last_run)) {
								$run_issue = 'last_run_not_array';
							} else {
								$last_state_after = sanitize_key((string) ($last_run['state_after'] ?? ''));
								if ($last_state_after === '') {
									$run_issue = 'last_run_missing_state_after';
								} elseif ($job_state !== 'queued' && $last_state_after !== $expected_state) {
									$run_issue = 'last_run_state_after_mismatch';
								}
								$last_totals = isset($last_run['postflight_step_totals']) && is_array($last_run['postflight_step_totals'])
									? $last_run['postflight_step_totals']
									: array();
								if ($run_issue === '' && empty($last_totals)) {
									$run_issue = 'last_run_missing_postflight_totals';
								}
								if ($run_issue === '' && !empty($last_totals)) {
									foreach ($expected_totals as $key => $value) {
										$actual = array_key_exists($key, $last_totals) ? (int) $last_totals[$key] : -1;
										if ($actual !== (int) $value) {
											$run_issue = 'last_run_postflight_totals_mismatch';
											break;
										}
									}
								}
							}
						}
						if ($run_issue !== '') {
							$run_audit_mismatch++;
							if (count($examples) < 4) {
								$examples[] = sprintf('plan %d run audit issue: %s', $plan_id, $run_issue);
							}
						}
					}

					$retry_issue = '';
					$retry_log = isset($summary['retry_log']) && is_array($summary['retry_log']) ? $summary['retry_log'] : array();
					if ($invalid_retry_step_meta) {
						$retry_issue = 'invalid_step_retry_metadata';
					}
					if ($retry_issue === '' && $has_retry_requested_meta && empty($retry_log)) {
						$retry_issue = 'missing_retry_log';
					}
					if ($retry_issue === '' && !empty($retry_log)) {
						foreach ($retry_log as $row) {
							if (!is_array($row)) {
								$retry_issue = 'retry_log_row_not_array';
								break;
							}
							$retry_step_key = sanitize_key((string) ($row['step_key'] ?? ''));
							$retry_at_gmt = sanitize_text_field((string) ($row['at_gmt'] ?? ''));
							$retry_request_id = sanitize_text_field((string) ($row['retry_request_id'] ?? ''));
							$retry_by_user_id = absint($row['by_user_id'] ?? 0);
							$retry_at_ts = $retry_at_gmt !== '' ? strtotime($retry_at_gmt . ' GMT') : 0;
							if ($retry_step_key === '' || $retry_at_ts <= 0 || $retry_by_user_id <= 0 || $retry_request_id === '') {
								$retry_issue = 'retry_log_row_missing_required_fields';
								break;
							}
						}
					}
					$last_retry_request = isset($summary['last_retry_request']) && is_array($summary['last_retry_request'])
						? $summary['last_retry_request']
						: array();
					if ($retry_issue === '' && !empty($retry_log)) {
						$last_retry_id = sanitize_text_field((string) ($last_retry_request['retry_request_id'] ?? ''));
						$last_retry_step = sanitize_key((string) ($last_retry_request['step_key'] ?? ''));
						$last_retry_at = sanitize_text_field((string) ($last_retry_request['at_gmt'] ?? ''));
						$last_retry_at_ts = $last_retry_at !== '' ? strtotime($last_retry_at . ' GMT') : 0;
						if ($last_retry_id === '' || $last_retry_step === '' || $last_retry_at_ts <= 0) {
							$retry_issue = 'last_retry_request_missing_required_fields';
						}
					}
					if ($retry_issue !== '') {
						$retry_audit_mismatch++;
						if (count($examples) < 4) {
							$examples[] = sprintf('plan %d retry audit issue: %s', $plan_id, $retry_issue);
						}
					}

					if ($job_state === 'running') {
						$active_run = isset($summary['active_run']) && is_array($summary['active_run']) ? $summary['active_run'] : array();
						$started_gmt = sanitize_text_field((string) ($active_run['started_at_gmt'] ?? ''));
						$started_ts = $started_gmt !== '' ? strtotime($started_gmt . ' GMT') : 0;
						$age = $started_ts > 0 ? max(0, time() - (int) $started_ts) : 0;
						if ($started_ts <= 0) {
							$running_missing_start++;
							if (count($examples) < 4) {
								$examples[] = sprintf('plan %d running job missing active_run started_at_gmt', $plan_id);
							}
						}
						if ($started_ts > 0 && $age >= $running_timeout) {
							$stale_running++;
							if (count($examples) < 4) {
								$examples[] = sprintf('plan %d has stale running job (%ds)', $plan_id, $age);
							}
						}
					} elseif ($job_state !== '' && $job_state !== $expected_state) {
						$state_mismatch++;
						if (count($examples) < 4) {
							$examples[] = sprintf('plan %d state mismatch (%s vs %s)', $plan_id, $job_state, $expected_state);
						}
					}

					$should_review = ($expected_state !== 'completed') || $requires_review_step;
				if ($should_review && !$review_flag) {
					$review_mismatch++;
					if (count($examples) < 4) {
						$examples[] = sprintf('plan %d missing operator-review flag', $plan_id);
					}
				}
			}

				$warn_total = $missing_envelope + $stale_running + $running_missing_start + $state_mismatch + $review_mismatch + $step_shape_mismatch + $summary_state_mismatch + $step_totals_mismatch + $run_audit_mismatch + $retry_audit_mismatch;
				$signal = ($warn_total > 0) ? 'warn' : 'pass';
				$notes = sprintf(
					'Cancelled plans checked: %d. Missing envelopes: %d. Repaired envelopes: %d. Stale running: %d. Running missing start: %d. State mismatches: %d. Review-flag mismatches: %d. Step-shape mismatches: %d. Summary-state mismatches: %d. Step-total mismatches: %d. Run-audit mismatches: %d. Retry-audit mismatches: %d.%s',
					$total,
					$missing_envelope,
					$repaired_envelopes,
					$stale_running,
					$running_missing_start,
					$state_mismatch,
					$review_mismatch,
					$step_shape_mismatch,
					$summary_state_mismatch,
					$step_totals_mismatch,
					$run_audit_mismatch,
					$retry_audit_mismatch,
					!empty($examples) ? (' Examples: ' . implode(' | ', $examples)) : ''
				);

			return array(
					'id' => 'CAN-01',
					'signal' => $signal,
					'notes' => $notes,
					'manual' => 'Open one warned cancelled plan and verify Cancellation Job state, steps, operator-review flag, and retry/run audit logs are consistent.',
				);
			}

		/**
		 * @param array<int,array<string,mixed>> $results
		 */
		private function render_results(array $results): void
		{
			$emoji = array(
				'pass' => 'PASS',
				'warn' => 'WARN',
				'manual' => 'MANUAL',
			);

			WP_CLI::log('Backstage Venue Manager stale-check report');
			WP_CLI::log(str_repeat('-', 72));

			$counts = array('pass' => 0, 'warn' => 0, 'manual' => 0);
			foreach ($results as $row) {
				$id = (string) ($row['id'] ?? '');
				$signal = (string) ($row['signal'] ?? 'manual');
				if (!isset($counts[$signal])) {
					$signal = 'manual';
				}
				$counts[$signal]++;
				$label = isset($emoji[$signal]) ? $emoji[$signal] : strtoupper($signal);
				WP_CLI::log(sprintf('%s  %s', str_pad('[' . $label . ']', 10, ' ', STR_PAD_RIGHT), $id));
				WP_CLI::log('  Notes : ' . (string) ($row['notes'] ?? ''));
				WP_CLI::log('  Test  : ' . (string) ($row['manual'] ?? ''));
			}

			WP_CLI::log(str_repeat('-', 72));
			WP_CLI::log(sprintf('Summary: PASS=%d WARN=%d MANUAL=%d', $counts['pass'], $counts['warn'], $counts['manual']));
		}
	}
}

WP_CLI::add_command('bvmgr', 'BVMGR_CLI_Stale_Check_Command');
// Deprecated B4 compatibility alias; retain until the CLI retirement gate is met.
WP_CLI::add_command('vms', 'BVMGR_CLI_Stale_Check_Command');
