<?php
defined('ABSPATH') || exit;

final class BVMGR_Admission_Offer_Eligibility_Resolver
{
	/** @var object */
	private $db;

	/** @param object|null $db */
	public function __construct($db = null)
	{
		if ($db === null) {
			global $wpdb;
			$db = $wpdb;
		}
		$this->db = $db;
	}

	/**
	 * Resolve an Event Plan against the eligibility rows already locked by the
	 * caller. Exclusions always win and an Offer with no matching inclusion is
	 * ineligible.
	 *
	 * @param array<int,array<string,mixed>>|null $rules
	 * @return array<string,mixed>
	 */
	public function resolve(int $offer_id, int $event_plan_id, ?array $rules = null): array
	{
		if ($offer_id < 1 || $event_plan_id < 1) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_eligibility_request');
		}
		$post = get_post($event_plan_id);
		if (!($post instanceof WP_Post) || $post->post_type !== 'vms_event_plan' || $post->post_status !== 'publish') {
			throw new BVMGR_Admission_Offer_Domain_Exception('event_not_claimable');
		}
		$status_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status';
		$status_key = $status_key !== '' ? $status_key : '_vms_event_plan_status';
		$status = sanitize_key((string) get_post_meta($event_plan_id, $status_key, true));
		if (!in_array($status, array('ready', 'published', 'confirmed'), true)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('event_not_claimable');
		}
		$venue_id = (int) get_post_meta($event_plan_id, '_vms_venue_id', true);
		$event_date = trim((string) get_post_meta($event_plan_id, '_vms_event_date', true));
		if ($venue_id < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('event_not_claimable');
		}
		if ($rules === null) {
			$table = bvmgr_admission_offers_table('eligibility');
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Claim-time eligibility must read request-fresh plugin-owned rules.
			$rules = $this->db->get_results($this->db->prepare('SELECT * FROM %i WHERE offer_id = %d ORDER BY id ASC', $table, $offer_id), ARRAY_A);
		}
		if (!is_array($rules) || $rules === array()) {
			throw new BVMGR_Admission_Offer_Domain_Exception('event_not_eligible');
		}
		/**
		 * Season keys are deliberately explicit. Core does not infer marketing
		 * seasons from dates or names; a future provider may supply exact keys.
		 */
		$season_keys = apply_filters('bvmgr_admission_offer_event_season_keys', array(), $event_plan_id, $venue_id, $event_date);
		$season_keys = array_values(array_unique(array_filter(array_map(static fn($key): string => strtolower(trim((string) $key)), is_array($season_keys) ? $season_keys : array()))));
		$included = false;
		foreach ($rules as $rule) {
			if (!$this->matches($rule, $event_plan_id, $venue_id, $event_date, $season_keys)) {
				continue;
			}
			if ((string) ($rule['mode'] ?? '') === 'exclude') {
				throw new BVMGR_Admission_Offer_Domain_Exception('event_excluded');
			}
			$included = true;
		}
		if (!$included) {
			throw new BVMGR_Admission_Offer_Domain_Exception('event_not_eligible');
		}
		return array('event_plan_id' => $event_plan_id, 'venue_id' => $venue_id, 'event_date' => $event_date, 'status' => $status);
	}

	/** @param array<string,mixed> $rule @param string[] $season_keys */
	private function matches(array $rule, int $event_plan_id, int $venue_id, string $event_date, array $season_keys): bool
	{
		switch ((string) ($rule['scope_type'] ?? '')) {
			case 'event_plan': return (int) ($rule['event_plan_id'] ?? 0) === $event_plan_id;
			case 'venue': return (int) ($rule['venue_id'] ?? 0) === $venue_id;
			case 'date_window': return $event_date >= (string) ($rule['start_date'] ?? '') && $event_date <= (string) ($rule['end_date'] ?? '');
			case 'season': return in_array(strtolower((string) ($rule['season_key'] ?? '')), $season_keys, true);
			case 'any_event': return true;
			default: return false;
		}
	}
}
