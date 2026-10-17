<?php
/**
 * Canonical read-only Guest Pass reporting for admin UI and CSV exports.
 *
 * Each owned table is aggregated independently before rows are merged by a
 * stable source or batch ID. This prevents claims, admissions, invitations,
 * campaigns, and reusable links from multiplying one another through joins.
 */

defined('ABSPATH') || exit;

if (!function_exists('bvmgr_pass_reporting_defaults')) {
	function bvmgr_pass_reporting_defaults(): array
	{
		return array(
			'batches_count' => 0,
			'direct_links_issued' => 0,
			'direct_links_available' => 0,
			'direct_links_claimed' => 0,
			'direct_links_void' => 0,
			'invitation_attempts' => 0,
			'unique_invited_links' => 0,
			'handoff_attempts' => 0,
			'failed_attempts' => 0,
			'indeterminate_attempts' => 0,
			'latest_handed_off' => 0,
			'latest_failed' => 0,
			'latest_indeterminate' => 0,
			'reusable_links' => 0,
			'reusable_active_links' => 0,
			'allocated_admission_capacity' => 0,
			'outreach_campaigns' => 0,
			'reusable_fulfilled_claims' => 0,
			'reusable_reserved_headcount' => 0,
			'paid_redemptions' => 0,
			'paid_admissions' => 0,
			'refunded_redemptions' => 0,
			'cancelled_redemptions' => 0,
			'claims' => 0,
			'reserved_entries' => 0,
			'reserved_headcount' => 0,
			'checked_in_entries' => 0,
			'checked_in_headcount' => 0,
			'outreach_available' => false,
			'outreach_note' => __('Outreach reporting is unavailable.', 'backstage-venue-manager'),
		);
	}
}

if (!function_exists('bvmgr_pass_reporting_filters')) {
	function bvmgr_pass_reporting_filters(array $raw): array
	{
		$text = static fn(string $key): string => isset($raw[$key]) && is_scalar($raw[$key]) ? sanitize_text_field((string) wp_unslash($raw[$key])) : '';
		$status = sanitize_key($text('report_status'));
		$value_type = sanitize_key($text('report_value_type'));
		$availability = sanitize_key($text('report_availability'));
		return array(
			'source_id' => isset($raw['report_source_id']) && is_scalar($raw['report_source_id']) ? absint((string) $raw['report_source_id']) : 0,
			'batch_id' => isset($raw['report_batch_id']) && is_scalar($raw['report_batch_id']) ? absint((string) $raw['report_batch_id']) : 0,
			'status' => in_array($status, array('', 'draft', 'active', 'archived', 'voided'), true) ? $status : '',
			'value_type' => in_array($value_type, array('', 'free', 'percentage', 'fixed'), true) ? $value_type : '',
			'availability' => in_array($availability, array('', 'available', 'expired', 'past_event', 'exhausted', 'inactive'), true) ? $availability : '',
			's' => $text('report_s'),
			'page' => max(1, isset($raw['report_page']) && is_scalar($raw['report_page']) ? absint((string) $raw['report_page']) : 1),
			'per_page' => max(20, min(100, isset($raw['report_per_page']) && is_scalar($raw['report_per_page']) ? absint((string) $raw['report_per_page']) : 50)),
		);
	}
}

if (!function_exists('bvmgr_pass_reporting_table_exists')) {
	function bvmgr_pass_reporting_table_exists(string $table): bool
	{
		global $wpdb;
		if ($table === '') {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Runtime capability detection must verify optional companion-owned tables without mutating them.
		return (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
	}
}

if (!function_exists('bvmgr_pass_reporting_outreach_tables')) {
	function bvmgr_pass_reporting_outreach_tables(): array
	{
		if (!defined('BACKSTAGE_OUTREACH_VERSION') || !function_exists('vms_admission_table_pass_outreach_campaigns') || !function_exists('backstage_outreach_business_table')) {
			return array('available' => false, 'note' => __('Outreach is disabled or its reporting API is unavailable.', 'backstage-venue-manager'));
		}
		$tables = array(
			'campaigns' => vms_admission_table_pass_outreach_campaigns(),
			'business_distributions' => backstage_outreach_business_table('campaign_businesses'),
			'distribution_claims' => backstage_outreach_business_table('distribution_claims'),
			'paid_redemptions' => backstage_outreach_business_table('paid_redemptions'),
		);
		if (function_exists('backstage_outreach_party_table')) {
			$tables['party_distributions'] = backstage_outreach_party_table('referral_distributions');
			$tables['party_redemptions'] = backstage_outreach_party_table('referral_redemptions');
		}
		foreach ($tables as $table) {
			if (!bvmgr_pass_reporting_table_exists((string) $table)) {
				return array('available' => false, 'note' => __('Outreach is active, but an authoritative reporting table is missing.', 'backstage-venue-manager'));
			}
		}
		return array_merge(array('available' => true, 'note' => ''), $tables);
	}
}

if (!function_exists('bvmgr_pass_reporting_merge_grouped')) {
	function bvmgr_pass_reporting_merge_grouped(array &$rows, array $grouped, array $fields): void
	{
		foreach ($grouped as $item) {
			$id = absint($item['batch_id'] ?? 0);
			if ($id <= 0 || !isset($rows[$id])) {
				continue;
			}
			foreach ($fields as $field) {
				$rows[$id][$field] = (int) ($item[$field] ?? 0);
			}
		}
	}
}

if (!function_exists('bvmgr_pass_reporting_where')) {
	function bvmgr_pass_reporting_where(array $filters): array
	{
		global $wpdb;
		$where = array('1=1');
		$params = array();
		if ((int) $filters['source_id'] > 0) { $where[] = 'b.source_id=%d'; $params[] = (int) $filters['source_id']; }
		if ((int) $filters['batch_id'] > 0) { $where[] = 'b.id=%d'; $params[] = (int) $filters['batch_id']; }
		if ((string) $filters['status'] !== '') { $where[] = 'b.status=%s'; $params[] = (string) $filters['status']; }
		if ((string) $filters['value_type'] !== '') { $where[] = 'b.value_type=%s'; $params[] = (string) $filters['value_type']; }
		if ((string) $filters['s'] !== '') {
			$like = '%' . $wpdb->esc_like((string) $filters['s']) . '%';
			$where[] = '(b.batch_name LIKE %s OR s.source_name LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}
		return array($where, $params);
	}
}

if (!function_exists('bvmgr_pass_reporting_availability')) {
	function bvmgr_pass_reporting_availability(array $row): array
	{
		$status = sanitize_key((string) ($row['status'] ?? ''));
		if ($status !== 'active') {
			return array('key' => 'inactive', 'label' => __('Inactive / historical', 'backstage-venue-manager'), 'remaining' => null);
		}
		$today = wp_date('Y-m-d', time(), wp_timezone());
		$expires = trim((string) ($row['expires_at'] ?? ''));
		if ($expires !== '' && strpos($expires, '0000-00-00') !== 0) {
			try {
				if ((new DateTimeImmutable($expires, wp_timezone()))->getTimestamp() <= time()) {
					return array('key' => 'expired', 'label' => __('Expired', 'backstage-venue-manager'), 'remaining' => null);
				}
			} catch (Exception $e) {
				return array('key' => 'expired', 'label' => __('Invalid expiration', 'backstage-venue-manager'), 'remaining' => null);
			}
		}
		$validity = sanitize_key((string) ($row['validity_type'] ?? ''));
		$end_date = (string) ($row['end_date'] ?? '');
		if (($validity === 'date_range' || $validity === 'season') && $end_date !== '' && $end_date < $today) {
			return array('key' => 'past_event', 'label' => __('Historical period ended', 'backstage-venue-manager'), 'remaining' => null);
		}
		if ($validity === 'single_event') {
			$event_id = absint($row['single_event_plan_id'] ?? 0);
			$event_date = $event_id > 0 ? (string) get_post_meta($event_id, '_vms_event_date', true) : '';
			if ($event_date !== '' && $event_date < $today) {
				return array('key' => 'past_event', 'label' => __('Event passed', 'backstage-venue-manager'), 'remaining' => null);
			}
		}
		$cap = max(0, (int) ($row['total_admission_cap'] ?? 0));
		$used = max(0, (int) ($row['reserved_headcount'] ?? 0)) + max(0, (int) ($row['paid_admissions'] ?? 0));
		if ($cap > 0 && $used >= $cap) {
			return array('key' => 'exhausted', 'label' => __('Capacity exhausted', 'backstage-venue-manager'), 'remaining' => 0);
		}
		return array('key' => 'available', 'label' => __('Currently available', 'backstage-venue-manager'), 'remaining' => $cap > 0 ? max(0, $cap - $used) : null);
	}
}

if (!function_exists('bvmgr_pass_reporting_enrich_batches')) {
	function bvmgr_pass_reporting_enrich_batches(array $base_rows): array
	{
		global $wpdb;
		$rows = array();
		foreach ($base_rows as $base) {
			$id = absint($base['id'] ?? 0);
			if ($id > 0) {
				$rows[$id] = array_merge(bvmgr_pass_reporting_defaults(), $base, array('batch_id' => $id, 'batches_count' => 1));
			}
		}
		$ids = array_keys($rows);
		if (empty($ids)) {
			return array();
		}
		$marks = implode(',', array_fill(0, count($ids), '%d'));

		$outreach = bvmgr_pass_reporting_outreach_tables();
		$excluded_tokens = array();
		if (!empty($outreach['available'])) {
			$sql = "SELECT DISTINCT c.related_batch_id batch_id, dc.pass_token_id FROM %i dc INNER JOIN %i c ON c.id=dc.campaign_id WHERE c.related_batch_id IN ({$marks}) AND dc.pass_token_id IS NOT NULL AND dc.pass_token_id>0";
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only adapter resolves reusable-claim tokens so they are not mislabeled as directly issued links.
			foreach ((array) $wpdb->get_results($wpdb->prepare($sql, array_merge(array($outreach['distribution_claims'], $outreach['campaigns']), $ids)), ARRAY_A) as $item) {
				$excluded_tokens[absint($item['pass_token_id'] ?? 0)] = true;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Canonical reporting reads bounded token rows and merges them by stable batch ID.
		$token_rows = (array) $wpdb->get_results($wpdb->prepare("SELECT id,batch_id,status FROM %i WHERE batch_id IN ({$marks})", array_merge(array(bvmgr_admission_table_pass_tokens()), $ids)), ARRAY_A);
		foreach ($token_rows as $token) {
			if (isset($excluded_tokens[absint($token['id'] ?? 0)])) {
				continue;
			}
			$id = absint($token['batch_id'] ?? 0);
			$status = sanitize_key((string) ($token['status'] ?? ''));
			$rows[$id]['direct_links_issued']++;
			if ($status === 'claimed') { $rows[$id]['direct_links_claimed']++; }
			elseif ($status === 'void') { $rows[$id]['direct_links_void']++; }
			elseif ($status === 'unclaimed') { $rows[$id]['direct_links_available']++; }
		}

		$invitations = bvmgr_admission_table_pass_invitations();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Invitation attempt history is aggregated separately to keep attempts distinct from unique links.
		$attempts = (array) $wpdb->get_results($wpdb->prepare("SELECT batch_id,COUNT(*) invitation_attempts,COUNT(DISTINCT token_id) unique_invited_links,SUM(status='handed_off') handoff_attempts,SUM(status='failed') failed_attempts,SUM(status NOT IN ('handed_off','failed')) indeterminate_attempts FROM %i WHERE batch_id IN ({$marks}) GROUP BY batch_id", array_merge(array($invitations), $ids)), ARRAY_A);
		bvmgr_pass_reporting_merge_grouped($rows, $attempts, array('invitation_attempts', 'unique_invited_links', 'handoff_attempts', 'failed_attempts', 'indeterminate_attempts'));
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Latest invitation outcomes are counted from one terminal row per token, independently of attempt totals.
		$latest = (array) $wpdb->get_results($wpdb->prepare("SELECT i.batch_id,SUM(i.status='handed_off') latest_handed_off,SUM(i.status='failed') latest_failed,SUM(i.status NOT IN ('handed_off','failed')) latest_indeterminate FROM %i i INNER JOIN (SELECT token_id,MAX(id) id FROM %i WHERE batch_id IN ({$marks}) GROUP BY token_id) x ON x.id=i.id GROUP BY i.batch_id", array_merge(array($invitations, $invitations), $ids)), ARRAY_A);
		bvmgr_pass_reporting_merge_grouped($rows, $latest, array('latest_handed_off', 'latest_failed', 'latest_indeterminate'));

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Claims and native admissions are aggregated independently to prevent join multiplication.
		$claims = (array) $wpdb->get_results($wpdb->prepare("SELECT batch_id,COUNT(DISTINCT id) claims FROM %i WHERE batch_id IN ({$marks}) GROUP BY batch_id", array_merge(array(bvmgr_admission_table_pass_claims()), $ids)), ARRAY_A);
		bvmgr_pass_reporting_merge_grouped($rows, $claims, array('claims'));
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native admissions are the canonical reserved/check-in headcount authority; canceled rows are excluded.
		$entries = (array) $wpdb->get_results($wpdb->prepare("SELECT pass_batch_id batch_id,COUNT(DISTINCT id) reserved_entries,COALESCE(SUM(party_size),0) reserved_headcount,SUM(checked_in_qty>0) checked_in_entries,COALESCE(SUM(checked_in_qty),0) checked_in_headcount FROM %i WHERE pass_batch_id IN ({$marks}) AND status<>'canceled' GROUP BY pass_batch_id", array_merge(array(bvmgr_admission_table_entries()), $ids)), ARRAY_A);
		bvmgr_pass_reporting_merge_grouped($rows, $entries, array('reserved_entries', 'reserved_headcount', 'checked_in_entries', 'checked_in_headcount'));

		if (!empty($outreach['available'])) {
			$campaign_sql = "SELECT related_batch_id batch_id,COUNT(DISTINCT id) outreach_campaigns FROM %i WHERE related_batch_id IN ({$marks}) GROUP BY related_batch_id";
			$campaigns = (array) $wpdb->get_results($wpdb->prepare($campaign_sql, array_merge(array($outreach['campaigns']), $ids)), ARRAY_A);
			bvmgr_pass_reporting_merge_grouped($rows, $campaigns, array('outreach_campaigns'));
			foreach (array('business_distributions', 'party_distributions') as $key) {
				if (empty($outreach[$key])) { continue; }
				$sql = "SELECT c.related_batch_id batch_id,COUNT(DISTINCT d.id) reusable_links,SUM(d.status='active') reusable_active_links,COALESCE(SUM(d.admission_cap),0) allocated_admission_capacity FROM %i d INNER JOIN %i c ON c.id=d.campaign_id WHERE c.related_batch_id IN ({$marks}) GROUP BY c.related_batch_id";
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Optional Outreach links are read through a narrow aggregate-only adapter.
				$grouped = (array) $wpdb->get_results($wpdb->prepare($sql, array_merge(array($outreach[$key], $outreach['campaigns']), $ids)), ARRAY_A);
				foreach ($grouped as $item) {
					$id = absint($item['batch_id'] ?? 0);
					foreach (array('reusable_links', 'reusable_active_links', 'allocated_admission_capacity') as $field) { $rows[$id][$field] += (int) ($item[$field] ?? 0); }
				}
			}
			$sql = "SELECT c.related_batch_id batch_id,COUNT(DISTINCT dc.pass_claim_id) reusable_fulfilled_claims FROM %i dc INNER JOIN %i c ON c.id=dc.campaign_id WHERE c.related_batch_id IN ({$marks}) AND dc.status='fulfilled' AND dc.pass_claim_id IS NOT NULL GROUP BY c.related_batch_id";
			$fulfilled = (array) $wpdb->get_results($wpdb->prepare($sql, array_merge(array($outreach['distribution_claims'], $outreach['campaigns']), $ids)), ARRAY_A);
			bvmgr_pass_reporting_merge_grouped($rows, $fulfilled, array('reusable_fulfilled_claims'));
			$sql = "SELECT x.batch_id,COALESCE(SUM(e.party_size),0) reusable_reserved_headcount FROM %i e INNER JOIN (SELECT DISTINCT c.related_batch_id batch_id,dc.pass_claim_id FROM %i dc INNER JOIN %i c ON c.id=dc.campaign_id WHERE c.related_batch_id IN ({$marks}) AND dc.status='fulfilled' AND dc.pass_claim_id IS NOT NULL) x ON x.pass_claim_id=e.pass_claim_id WHERE e.status<>'canceled' GROUP BY x.batch_id";
			$reusable_reserved = (array) $wpdb->get_results($wpdb->prepare($sql, array_merge(array(bvmgr_admission_table_entries(), $outreach['distribution_claims'], $outreach['campaigns']), $ids)), ARRAY_A);
			bvmgr_pass_reporting_merge_grouped($rows, $reusable_reserved, array('reusable_reserved_headcount'));
			foreach (array('paid_redemptions', 'party_redemptions') as $key) {
				if (empty($outreach[$key])) { continue; }
				$sql = "SELECT c.related_batch_id batch_id,SUM(r.status IN ('paid','partially_refunded')) paid_redemptions,COALESCE(SUM(CASE WHEN r.status IN ('paid','partially_refunded') THEN r.ticket_quantity ELSE 0 END),0) paid_admissions,SUM(r.status IN ('partially_refunded','refunded')) refunded_redemptions,SUM(r.status IN ('failed','cancelled','canceled')) cancelled_redemptions FROM %i r INNER JOIN %i c ON c.id=r.campaign_id WHERE c.related_batch_id IN ({$marks}) GROUP BY c.related_batch_id";
				$paid = (array) $wpdb->get_results($wpdb->prepare($sql, array_merge(array($outreach[$key], $outreach['campaigns']), $ids)), ARRAY_A);
				foreach ($paid as $item) {
					$id = absint($item['batch_id'] ?? 0);
					foreach (array('paid_redemptions', 'paid_admissions', 'refunded_redemptions', 'cancelled_redemptions') as $field) { $rows[$id][$field] += (int) ($item[$field] ?? 0); }
				}
			}
		}

		foreach ($rows as &$row) {
			$row['outreach_available'] = !empty($outreach['available']);
			$row['outreach_note'] = (string) ($outreach['note'] ?? '');
			$row['direct_claim_rate'] = (int) $row['direct_links_issued'] > 0 ? round(((int) $row['direct_links_claimed'] / (int) $row['direct_links_issued']) * 100, 1) : null;
			$row['allocation_utilization'] = (int) $row['allocated_admission_capacity'] > 0 ? round(((int) $row['reusable_reserved_headcount'] / (int) $row['allocated_admission_capacity']) * 100, 1) : null;
			$availability = bvmgr_pass_reporting_availability($row);
			$row['availability_key'] = $availability['key'];
			$row['availability_label'] = $availability['label'];
			$row['remaining_capacity'] = $availability['remaining'];
		}
		unset($row);
		return array_values($rows);
	}
}

if (!function_exists('bvmgr_pass_reporting_batches')) {
	function bvmgr_pass_reporting_batches(array $filters, int $offset = 0, int $limit = 50): array
	{
		global $wpdb;
		list($where, $params) = bvmgr_pass_reporting_where($filters);
		$from = ' FROM %i b LEFT JOIN %i s ON s.id=b.source_id WHERE ' . implode(' AND ', $where);
		$base = array(bvmgr_admission_table_pass_batches(), bvmgr_admission_table_pass_sources());
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reporting count is bounded by filtered plugin-owned batch/source tables.
		$total = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*)' . $from, array_merge($base, $params)));
		if ((string) $filters['availability'] !== '') {
			$rows = array();
			$total = 0;
			$scan_offset = 0;
			$scan_size = 100;
			do {
				$sql = 'SELECT b.*,s.source_name' . $from . ' ORDER BY b.id DESC LIMIT %d OFFSET %d';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Availability filtering scans bounded pages because current state depends on live admission/event data.
				$base_rows = (array) $wpdb->get_results($wpdb->prepare($sql, array_merge($base, $params, array($scan_size, $scan_offset))), ARRAY_A);
				foreach (bvmgr_pass_reporting_enrich_batches($base_rows) as $row) {
					if ((string) ($row['availability_key'] ?? '') !== (string) $filters['availability']) {
						continue;
					}
					if ($total >= max(0, $offset) && count($rows) < max(1, min(100, $limit))) {
						$rows[] = $row;
					}
					$total++;
				}
				$scan_offset += $scan_size;
			} while (count($base_rows) === $scan_size && $scan_offset < 100000);
			return array('rows' => $rows, 'total' => $total);
		}
		$sql = 'SELECT b.*,s.source_name' . $from . ' ORDER BY b.id DESC LIMIT %d OFFSET %d';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Canonical batch reporting pages bounded rows before enrichment.
		$base_rows = (array) $wpdb->get_results($wpdb->prepare($sql, array_merge($base, $params, array(max(1, min(100, $limit)), max(0, $offset)))), ARRAY_A);
		$rows = bvmgr_pass_reporting_enrich_batches($base_rows);
		return array('rows' => $rows, 'total' => $total);
	}
}

if (!function_exists('bvmgr_pass_reporting_sources')) {
	function bvmgr_pass_reporting_sources(array $filters, int $offset = 0, int $limit = 50): array
	{
		global $wpdb;
		$where = array('1=1');
		$params = array();
		if ((int) $filters['source_id'] > 0) { $where[] = 's.id=%d'; $params[] = (int) $filters['source_id']; }
		if ((string) $filters['s'] !== '') { $where[] = 's.source_name LIKE %s'; $params[] = '%' . $wpdb->esc_like((string) $filters['s']) . '%'; }
		$from = ' FROM %i s WHERE ' . implode(' AND ', $where);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Source report pages are bounded and request-fresh.
		$total = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*)' . $from, array_merge(array(bvmgr_admission_table_pass_sources()), $params)));
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Source report pages are bounded before batch enrichment.
		$sources = (array) $wpdb->get_results($wpdb->prepare('SELECT s.id,s.source_name,s.status' . $from . ' ORDER BY s.source_name ASC,s.id ASC LIMIT %d OFFSET %d', array_merge(array(bvmgr_admission_table_pass_sources()), $params, array(max(1, min(100, $limit)), max(0, $offset)))), ARRAY_A);
		$out = array();
		$outreach = bvmgr_pass_reporting_outreach_tables();
		foreach ($sources as $source) {
			$source_filters = $filters;
			$source_filters['source_id'] = absint($source['id'] ?? 0);
			$batch_offset = 0;
			$row = array_merge(bvmgr_pass_reporting_defaults(), array('id' => absint($source['id'] ?? 0), 'source_id' => absint($source['id'] ?? 0), 'source_name' => (string) ($source['source_name'] ?? ''), 'status' => (string) ($source['status'] ?? '')));
			$row['outreach_available'] = !empty($outreach['available']);
			$row['outreach_note'] = (string) ($outreach['note'] ?? '');
			do {
				$batch_result = bvmgr_pass_reporting_batches($source_filters, $batch_offset, 100);
				foreach ($batch_result['rows'] as $batch) {
					foreach (array_keys(bvmgr_pass_reporting_defaults()) as $field) {
						if (is_int($row[$field]) && isset($batch[$field])) { $row[$field] += (int) $batch[$field]; }
					}
					$row['outreach_available'] = !empty($batch['outreach_available']);
					$row['outreach_note'] = (string) ($batch['outreach_note'] ?? '');
				}
				$batch_offset += 100;
			} while ($batch_offset < (int) ($batch_result['total'] ?? 0) && $batch_offset < 100000);
			$row['direct_claim_rate'] = $row['direct_links_issued'] > 0 ? round(($row['direct_links_claimed'] / $row['direct_links_issued']) * 100, 1) : null;
			$row['allocation_utilization'] = $row['allocated_admission_capacity'] > 0 ? round(($row['reusable_reserved_headcount'] / $row['allocated_admission_capacity']) * 100, 1) : null;
			$out[] = $row;
		}
		return array('rows' => $out, 'total' => $total);
	}
}

if (!function_exists('bvmgr_pass_reporting_csv_headers')) {
	function bvmgr_pass_reporting_csv_headers(string $scope): array
	{
		$identity = $scope === 'batch' ? array('batch_id', 'batch_name', 'source_id', 'source_name', 'offer_type', 'batch_status', 'availability', 'remaining_capacity', 'shared_batch_cap') : array('source_id', 'source_name', 'source_status', 'batches');
		return array_merge($identity, array('direct_links_issued', 'direct_links_available', 'direct_links_claimed', 'direct_links_void', 'direct_claim_rate_percent', 'invitation_attempts', 'unique_invited_links', 'handoff_attempts', 'failed_attempts', 'indeterminate_attempts', 'latest_handed_off_links', 'latest_failed_links', 'latest_indeterminate_links', 'reusable_links', 'reusable_active_links', 'allocated_admission_capacity', 'reusable_fulfilled_claims', 'reusable_reserved_headcount', 'allocation_utilization_percent', 'total_claims', 'reserved_entries', 'reserved_headcount', 'checked_in_entries', 'checked_in_headcount', 'paid_redemptions', 'paid_admissions', 'refunded_redemptions', 'cancelled_redemptions', 'outreach_metrics'));
	}
}

if (!function_exists('bvmgr_pass_reporting_csv_row')) {
	function bvmgr_pass_reporting_csv_row(string $scope, array $row): array
	{
		$identity = $scope === 'batch' ? array((int) ($row['id'] ?? 0), (string) ($row['batch_name'] ?? ''), (int) ($row['source_id'] ?? 0), (string) ($row['source_name'] ?? ''), (string) ($row['value_type'] ?? ''), (string) ($row['status'] ?? ''), (string) ($row['availability_label'] ?? ''), $row['remaining_capacity'] === null ? 'not_applicable' : (int) $row['remaining_capacity'], (int) ($row['total_admission_cap'] ?? 0)) : array((int) ($row['id'] ?? 0), (string) ($row['source_name'] ?? ''), (string) ($row['status'] ?? ''), (int) ($row['batches_count'] ?? 0));
		$values = array(
			(int) $row['direct_links_issued'], (int) $row['direct_links_available'], (int) $row['direct_links_claimed'], (int) $row['direct_links_void'], $row['direct_claim_rate'] === null ? 'not_applicable' : $row['direct_claim_rate'],
			(int) $row['invitation_attempts'], (int) $row['unique_invited_links'], (int) $row['handoff_attempts'], (int) $row['failed_attempts'], (int) $row['indeterminate_attempts'], (int) $row['latest_handed_off'], (int) $row['latest_failed'], (int) $row['latest_indeterminate'],
			(int) $row['reusable_links'], (int) $row['reusable_active_links'], (int) $row['allocated_admission_capacity'], (int) $row['reusable_fulfilled_claims'], (int) $row['reusable_reserved_headcount'], $row['allocation_utilization'] === null ? 'not_applicable' : $row['allocation_utilization'],
			(int) $row['claims'], (int) $row['reserved_entries'], (int) $row['reserved_headcount'], (int) $row['checked_in_entries'], (int) $row['checked_in_headcount'], (int) $row['paid_redemptions'], (int) $row['paid_admissions'], (int) $row['refunded_redemptions'], (int) $row['cancelled_redemptions'], !empty($row['outreach_available']) ? 'available' : (string) $row['outreach_note'],
		);
		return array_merge($identity, $values);
	}
}
