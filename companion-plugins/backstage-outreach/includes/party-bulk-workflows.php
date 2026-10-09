<?php
/** Reviewed historical adoption, paid Partner setup, bulk links, and contact audit. */

defined('ABSPATH') || exit;

function backstage_outreach_party_workflow_review_key(string $kind): string
{
	return 'backstage_outreach_party_' . sanitize_key($kind) . '_' . get_current_user_id();
}

function backstage_outreach_party_workflow_digest($value): string
{
	return hash('sha256', (string) wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function backstage_outreach_party_adoption_normalize_identity_text(string $value): string
{
	$value = remove_accents(sanitize_text_field($value));
	$value = preg_replace('/\s+/u', ' ', trim($value));
	return function_exists('mb_strtolower') ? mb_strtolower((string) $value, 'UTF-8') : strtolower((string) $value);
}

function backstage_outreach_party_adoption_identity(array $row): array
{
	$name = sanitize_text_field((string) ($row['full_name'] ?: trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''))));
	$email = backstage_outreach_party_normalize_contact_value('email', (string) ($row['email'] ?? ''));
	$organization = sanitize_text_field((string) ($row['company'] ?: ($row['group_label'] ?? '')));
	return array(
		'email' => $email,
		'name' => backstage_outreach_party_adoption_normalize_identity_text($name),
		'organization' => backstage_outreach_party_adoption_normalize_identity_text($organization),
	);
}

function backstage_outreach_party_adoption_compatibility_plan(array $rows): array
{
	$candidates = array();
	$email_campaign_rows = array();
	foreach ($rows as $row) {
		if (absint($row['contact_id'] ?? 0) > 0) {
			continue;
		}
		$identity = backstage_outreach_party_adoption_identity($row);
		$row_id = absint($row['id'] ?? 0);
		$campaign_id = absint($row['campaign_id'] ?? 0);
		if ($identity['email'] !== '') {
			$email_campaign_rows[$identity['email']][$campaign_id][] = $row_id;
		}
		if ($identity['email'] === '' || $identity['name'] === '' || $identity['organization'] === '') {
			continue;
		}
		$signature = backstage_outreach_party_workflow_digest(array($identity['email'], $identity['name'], $identity['organization']));
		$candidates[$signature][] = array('row_id' => $row_id, 'campaign_id' => $campaign_id, 'identity' => $identity);
	}

	$row_signatures = array();
	foreach ($candidates as $signature => $matches) {
		$campaigns = array_count_values(array_column($matches, 'campaign_id'));
		if (count($matches) < 2 || count($campaigns) < 2 || max($campaigns) > 1) {
			continue;
		}
		foreach ($matches as $match) {
			$row_signatures[absint($match['row_id'])] = $signature;
		}
	}

	$duplicate_email_rows = array();
	foreach ($email_campaign_rows as $campaigns) {
		foreach ($campaigns as $row_ids) {
			if (count($row_ids) > 1) {
				foreach ($row_ids as $row_id) {
					$duplicate_email_rows[absint($row_id)] = true;
				}
			}
		}
	}
	return array('row_signatures' => $row_signatures, 'duplicate_email_rows' => $duplicate_email_rows);
}

function backstage_outreach_party_adoption_rows(int $source_id, array $campaign_ids, int $limit = 1000): array
{
	global $wpdb;
	$campaign_ids = array_values(array_unique(array_filter(array_map('absint', $campaign_ids))));
	if ($source_id <= 0 || !$campaign_ids) {
		return array();
	}
	$placeholders = implode(',', array_fill(0, count($campaign_ids), '%d'));
	$args = array_merge(
		array(vms_admission_table_pass_outreach_recipients(), vms_admission_table_pass_outreach_campaigns(), $source_id),
		$campaign_ids,
		array(max(1, min(1000, $limit)))
	);
	$sql = 'SELECT r.id,r.campaign_id,r.contact_id,r.first_name,r.last_name,r.full_name,r.email,r.email_norm,r.phone,r.phone_norm,r.company,r.group_label,r.status,r.created_at,c.related_source_id
		FROM %i r INNER JOIN %i c ON c.id=r.campaign_id
		WHERE c.related_source_id=%d AND r.campaign_id IN (' . $placeholders . ') ORDER BY r.contact_id ASC,r.id ASC LIMIT %d';
	return array_values((array) $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A));
}

function backstage_outreach_party_adoption_preview(int $source_id, array $campaign_ids, int $limit = 1000)
{
	global $wpdb;
	$source = $source_id > 0 ? $wpdb->get_row($wpdb->prepare('SELECT id,source_name,status FROM %i WHERE id=%d', bvmgr_admission_table_pass_sources(), $source_id), ARRAY_A) : null;
	$campaign_ids = array_values(array_unique(array_filter(array_map('absint', $campaign_ids))));
	if (!is_array($source) || !$campaign_ids || count($campaign_ids) > 10) {
		return new WP_Error('party_adoption_scope_invalid', __('Choose one Source and between one and ten historical campaigns.', 'backstage-outreach'));
	}
	$campaigns = $wpdb->get_results($wpdb->prepare(
		'SELECT id,campaign_name,related_source_id FROM %i WHERE id IN (' . implode(',', array_fill(0, count($campaign_ids), '%d')) . ')',
		array_merge(array(vms_admission_table_pass_outreach_campaigns()), $campaign_ids)
	), ARRAY_A);
	if (count((array) $campaigns) !== count($campaign_ids)) {
		return new WP_Error('party_adoption_campaign_missing', __('One or more selected campaigns no longer exist.', 'backstage-outreach'));
	}
	foreach ((array) $campaigns as $campaign) {
		if (absint($campaign['related_source_id'] ?? 0) !== $source_id) {
			return new WP_Error('party_adoption_source_mismatch', __('Every selected campaign must belong to the selected Source.', 'backstage-outreach'));
		}
	}
	$total_rows = (int) $wpdb->get_var($wpdb->prepare(
		'SELECT COUNT(*) FROM %i r INNER JOIN %i c ON c.id=r.campaign_id WHERE c.related_source_id=%d AND r.campaign_id IN (' . implode(',', array_fill(0, count($campaign_ids), '%d')) . ')',
		array_merge(array(vms_admission_table_pass_outreach_recipients(), vms_admission_table_pass_outreach_campaigns(), $source_id), $campaign_ids)
	));
	if ($total_rows > max(1, min(1000, $limit))) {
		return new WP_Error('party_adoption_scope_too_large', __('This review contains more than 1,000 recipient snapshots. Narrow the campaign selection before continuing.', 'backstage-outreach'));
	}
	$rows = backstage_outreach_party_adoption_rows($source_id, $campaign_ids, $limit);
	$compatibility = backstage_outreach_party_adoption_compatibility_plan($rows);
	$groups = array();
	foreach ($rows as $row) {
		$contact_id = absint($row['contact_id'] ?? 0);
		$row_id = absint($row['id']);
		$compatibility_signature = (string) ($compatibility['row_signatures'][$row_id] ?? '');
		$key = $contact_id > 0
			? 'contact-' . $contact_id
			: ($compatibility_signature !== '' ? 'compat-' . $compatibility_signature : 'recipient-' . $row_id);
		if (!isset($groups[$key])) {
			$groups[$key] = array(
				'group_key' => $key,
				'contact_id' => $contact_id,
				'recipients' => array(),
				'campaign_ids' => array(),
				'emails' => array(),
				'phones' => array(),
				'names' => array(),
				'organizations' => array(),
				'existing_party_id' => 0,
				'mapped_party_ids' => array(),
				'identity_evidence' => array(),
				'compatibility_match' => $compatibility_signature !== '',
				'mapping_conflict' => false,
				'ambiguous_reasons' => array(),
			);
		}
		$name = sanitize_text_field((string) ($row['full_name'] ?: trim((string) $row['first_name'] . ' ' . (string) $row['last_name'])));
		$email = sanitize_email((string) ($row['email'] ?? ''));
		$phone = sanitize_text_field((string) ($row['phone'] ?? ''));
		$organization = sanitize_text_field((string) ($row['company'] ?: $row['group_label']));
		$groups[$key]['recipients'][] = $row;
		$groups[$key]['campaign_ids'][absint($row['campaign_id'])] = true;
		if ($name !== '') { $groups[$key]['names'][$name] = true; }
		if ($email !== '') { $groups[$key]['emails'][$email] = true; }
		if ($phone !== '') { $groups[$key]['phones'][$phone] = true; }
		if ($organization !== '') { $groups[$key]['organizations'][$organization] = true; }
		$link = backstage_outreach_party_get_legacy_link('campaign_recipient', absint($row['id']));
		if (is_array($link)) {
			$link_party_id = absint($link['party_id'] ?? 0);
			if ($link_party_id > 0) { $groups[$key]['mapped_party_ids'][$link_party_id] = true; }
		}
		if (!$groups[$key]['mapped_party_ids'] && $contact_id > 0) {
			$contact_link = backstage_outreach_party_get_legacy_link('outreach_contact', $contact_id);
			if (is_array($contact_link)) {
				$contact_party_id = absint($contact_link['party_id'] ?? 0);
				if ($contact_party_id > 0) { $groups[$key]['mapped_party_ids'][$contact_party_id] = true; }
			}
		}
		if (!empty($compatibility['duplicate_email_rows'][$row_id])) {
			$groups[$key]['ambiguous_reasons'][] = __('Duplicate normalized email exists within one campaign, so no compatibility match was proposed.', 'backstage-outreach');
		}
	}

	$identity_map = array();
	foreach ($groups as $key => $group) {
		foreach (array('names', 'emails', 'phones') as $field) {
			foreach (array_keys($group[$field]) as $value) {
				$type = rtrim($field, 's');
				$normalized = $type === 'email' || $type === 'phone'
					? backstage_outreach_party_normalize_contact_value($type, $value)
					: strtolower($value);
				if ($normalized !== '') { $identity_map[$type . ':' . $normalized][$key] = true; }
			}
		}
	}
	$email_count = 0;
	$ambiguous_count = 0;
	$skipped_count = 0;
	$compatibility_match_count = 0;
	foreach ($groups as $key => &$group) {
		$group['campaign_ids'] = array_map('absint', array_keys($group['campaign_ids']));
		$group['names'] = array_keys($group['names']);
		$group['emails'] = array_keys($group['emails']);
		$group['phones'] = array_keys($group['phones']);
		$group['organizations'] = array_keys($group['organizations']);
		$group['mapped_party_ids'] = array_map('absint', array_keys($group['mapped_party_ids']));
		if (count($group['mapped_party_ids']) === 1) {
			$group['existing_party_id'] = (int) $group['mapped_party_ids'][0];
		} elseif (count($group['mapped_party_ids']) > 1) {
			$group['mapping_conflict'] = true;
			$group['ambiguous_reasons'][] = __('Recipient snapshots are already linked to different Parties and cannot be silently relinked.', 'backstage-outreach');
		}
		$group['display_name'] = (string) ($group['names'][0] ?? '');
		if ($group['contact_id'] > 0) {
			$group['identity_evidence'][] = sprintf(__('Shared historical Contact ID #%1$d across campaign snapshot(s) %2$s.', 'backstage-outreach'), $group['contact_id'], implode(', ', $group['campaign_ids']));
		} elseif (!empty($group['compatibility_match'])) {
			$group['identity_evidence'][] = sprintf(__('Compatibility proposal: exact normalized email, person name, and organization agree across campaigns %s.', 'backstage-outreach'), implode(', ', $group['campaign_ids']));
			$compatibility_match_count++;
		} else {
			$group['identity_evidence'][] = sprintf(__('No safe cross-campaign compatibility match; recipient snapshot #%d remains separate.', 'backstage-outreach'), absint($group['recipients'][0]['id'] ?? 0));
			$identity = backstage_outreach_party_adoption_identity((array) ($group['recipients'][0] ?? array()));
			if ($identity['email'] === '' || $identity['name'] === '' || $identity['organization'] === '') {
				$group['ambiguous_reasons'][] = __('A compound email, person-name, and organization identity is incomplete and requires manual review.', 'backstage-outreach');
			}
		}
		if ($group['emails']) { $email_count++; }
		if ($group['display_name'] === '') {
			$group['ambiguous_reasons'][] = __('No usable person name is present.', 'backstage-outreach');
			$skipped_count++;
		}
		foreach (array('name' => $group['names'], 'email' => $group['emails'], 'phone' => $group['phones']) as $type => $values) {
			foreach ($values as $value) {
				$normalized = $type === 'email' || $type === 'phone' ? backstage_outreach_party_normalize_contact_value($type, $value) : strtolower($value);
				if ($normalized !== '' && count($identity_map[$type . ':' . $normalized] ?? array()) > 1) {
					$group['ambiguous_reasons'][] = $type === 'email'
						? __('A matching email has a conflicting normalized name, organization, or same-campaign duplicate and requires manual review.', 'backstage-outreach')
						: sprintf(__('Shared %s appears under more than one historical identity.', 'backstage-outreach'), $type);
				}
			}
		}
		$snapshot = array(
			'display_name' => $group['display_name'],
			'organization' => (string) ($group['organizations'][0] ?? ''),
			'email' => (string) ($group['emails'][0] ?? ''),
			'phone' => (string) ($group['phones'][0] ?? ''),
		);
		$suggestions = backstage_outreach_party_suggestions($snapshot, 10);
		if ($group['existing_party_id'] <= 0 && $suggestions) {
			$group['suggestions'] = array_map(static fn(array $item): array => array(
				'party_id' => absint($item['party']['id'] ?? 0),
				'name' => (string) ($item['party']['display_name'] ?? ''),
				'reasons' => (array) ($item['reasons'] ?? array()),
			), $suggestions);
			$group['ambiguous_reasons'][] = __('Possible existing Party match requires an explicit create-or-reuse decision.', 'backstage-outreach');
		} else {
			$group['suggestions'] = array();
		}
		$group['ambiguous_reasons'] = array_values(array_unique($group['ambiguous_reasons']));
		if ($group['ambiguous_reasons']) { $ambiguous_count++; }
		$group['snapshot_digest'] = backstage_outreach_party_workflow_digest(array_map(static fn(array $row): array => array(
			'id' => absint($row['id']), 'campaign_id' => absint($row['campaign_id']), 'contact_id' => absint($row['contact_id']),
			'full_name' => (string) $row['full_name'], 'email' => (string) $row['email'], 'phone' => (string) $row['phone'],
			'company' => (string) $row['company'], 'group_label' => (string) $row['group_label'],
		), $group['recipients']));
		unset($group['recipients']);
	}
	unset($group);
	$groups = array_values($groups);
	return array(
		'source_id' => $source_id,
		'source_name' => (string) $source['source_name'],
		'campaign_ids' => $campaign_ids,
		'groups' => $groups,
		'summary' => array('recipient_count' => count($rows), 'party_count' => count($groups), 'compatibility_match_count' => $compatibility_match_count, 'email_count' => $email_count, 'missing_email_count' => count($groups) - $email_count, 'ambiguous_count' => $ambiguous_count, 'skipped_count' => $skipped_count),
		'scope_digest' => backstage_outreach_party_workflow_digest($rows),
		'reviewed_at' => time(),
	);
}

function backstage_outreach_party_adoption_commit(array $review, array $choices, int $user_id): array
{
	$current = backstage_outreach_party_adoption_preview(absint($review['source_id'] ?? 0), (array) ($review['campaign_ids'] ?? array()));
	if (is_wp_error($current) || !hash_equals((string) ($review['scope_digest'] ?? ''), is_array($current) ? (string) ($current['scope_digest'] ?? '') : '')) {
		return array('created' => 0, 'reused' => 0, 'linked' => 0, 'skipped' => 0, 'errors' => array(__('Historical recipients changed after review. Preview again.', 'backstage-outreach')));
	}
	$choice_map = array();
	foreach ($choices as $choice) { $choice_map[sanitize_key((string) ($choice['group_key'] ?? ''))] = (array) $choice; }
	$result = array('created' => 0, 'reused' => 0, 'linked' => 0, 'skipped' => 0, 'errors' => array());
	global $wpdb;
	$current_rows = backstage_outreach_party_adoption_rows(absint($current['source_id']), (array) $current['campaign_ids']);
	$current_compatibility = backstage_outreach_party_adoption_compatibility_plan($current_rows);
	foreach ((array) $current['groups'] as $group) {
		$key = sanitize_key((string) $group['group_key']);
		$choice = $choice_map[$key] ?? array();
		if (empty($choice['selected'])) { $result['skipped']++; continue; }
		if (!empty($group['mapping_conflict'])) {
			$result['errors'][$key] = __('This proposal contains conflicting reviewed mappings and cannot be silently relinked.', 'backstage-outreach');
			continue;
		}
		$resolution = sanitize_key((string) ($choice['resolution'] ?? ''));
		$party_id = absint($choice['party_id'] ?? 0);
		if (!empty($group['existing_party_id'])) {
			$party_id = absint($group['existing_party_id']);
			$resolution = 'reuse';
		}
		if ($group['display_name'] === '' || ($group['ambiguous_reasons'] && empty($choice['identity_reviewed']))) {
			$result['errors'][$key] = __('An explicit identity review is required for this ambiguous row.', 'backstage-outreach');
			continue;
		}
		if ($resolution === 'reuse') {
			$party = backstage_outreach_party_get($party_id);
			if (!is_array($party) || (string) $party['party_type'] !== 'person') {
				$result['errors'][$key] = __('The selected reusable Person Party is unavailable.', 'backstage-outreach');
				continue;
			}
			$result['reused']++;
		} elseif ($resolution === 'create') {
			$provenance_key = 'recipient-adoption:' . hash('sha256', $key . '|' . (string) $group['snapshot_digest']);
			$party = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE provenance_type=%s AND provenance_key=%s', backstage_outreach_party_table('parties'), 'historical_recipient', $provenance_key), ARRAY_A);
			if (!is_array($party)) {
				$names = preg_split('/\s+/', trim((string) $group['display_name']), 2);
				$party = backstage_outreach_party_save(array(
					'party_type' => 'person', 'display_name' => (string) $group['display_name'],
					'given_name' => (string) ($names[0] ?? ''), 'family_name' => (string) ($names[1] ?? ''),
					'identifying_details' => implode(' / ', (array) $group['organizations']),
					'notes' => __('Reviewed adoption of immutable historical Outreach recipient snapshots.', 'backstage-outreach'),
					'provenance_type' => 'historical_recipient', 'provenance_key' => $provenance_key,
				), $user_id);
				if (is_wp_error($party)) { $result['errors'][$key] = $party->get_error_message(); continue; }
				$result['created']++;
			} else {
				$result['reused']++;
			}
			$party_id = absint($party['id'] ?? 0);
		} else {
			$result['errors'][$key] = __('Choose Create new Person or Reuse existing Person.', 'backstage-outreach');
			continue;
		}
		$error = null;
		$link_source = backstage_outreach_party_link_source($party_id, absint($current['source_id']), $user_id, 'historical_recipient');
		if (is_wp_error($link_source)) { $error = $link_source; }
		foreach ((array) $group['emails'] as $index => $email) {
			$saved = backstage_outreach_party_save_contact_method($party_id, array('method_type' => 'email', 'value' => $email, 'label' => __('Historical recipient', 'backstage-outreach'), 'is_primary' => $index === 0, 'provenance_type' => 'historical_recipient', 'provenance_key' => $key), $user_id);
			if (is_wp_error($saved)) { $error = $saved; break; }
		}
		foreach ((array) $group['phones'] as $index => $phone) {
			$saved = backstage_outreach_party_save_contact_method($party_id, array('method_type' => 'phone', 'value' => $phone, 'label' => __('Historical recipient', 'backstage-outreach'), 'is_primary' => $index === 0, 'provenance_type' => 'historical_recipient', 'provenance_key' => $key), $user_id);
			if (is_wp_error($saved)) { $error = $saved; break; }
		}
		foreach ((array) $group['campaign_ids'] as $campaign_id) {
			$role = backstage_outreach_party_save_campaign_role($party_id, absint($campaign_id), 'contacted', $user_id);
			if (is_wp_error($role)) { $error = $role; break; }
		}
		foreach ($current_rows as $row) {
			$row_id = absint($row['id']);
			$signature = (string) ($current_compatibility['row_signatures'][$row_id] ?? '');
			$row_key = absint($row['contact_id'] ?? 0) > 0 ? 'contact-' . absint($row['contact_id']) : ($signature !== '' ? 'compat-' . $signature : 'recipient-' . $row_id);
			if ($row_key !== $key) { continue; }
			$snapshot = backstage_outreach_party_get_legacy_snapshot('campaign_recipient', absint($row['id']));
			$link = is_wp_error($snapshot) ? $snapshot : backstage_outreach_party_confirm_legacy_link($party_id, 'campaign_recipient', absint($row['id']), (string) $snapshot['snapshot_hash'], (array) $group['identity_evidence'], $user_id, hash('sha256', 'bulk-adopt|' . $party_id . '|' . absint($row['id'])));
			if (is_wp_error($link)) { $error = $link; break; }
			$result['linked']++;
		}
		if (is_wp_error($error)) { $result['errors'][$key] = $error->get_error_message(); }
	}
	return $result;
}

function backstage_outreach_party_partner_campaign_review(array $raw)
{
	$source_id = absint($raw['source_id'] ?? 0);
	$cap = absint($raw['total_admission_cap'] ?? 0);
	if ($cap < 1 || $cap > 50000) {
		return new WP_Error('partner_campaign_cap_required', __('Enter and review a total campaign admission cap between 1 and 50,000.', 'backstage-outreach'));
	}
	$start = sanitize_text_field((string) ($raw['start_date'] ?? wp_date('Y-m-d')));
	$end = sanitize_text_field((string) ($raw['end_date'] ?? '2026-10-31'));
	$expires = sanitize_text_field((string) ($raw['expires_at'] ?? '2026-10-31T23:59'));
	$name = sanitize_text_field((string) ($raw['campaign_name'] ?? __('October 2026 Realtor 50%-off Admission Offer', 'backstage-outreach')));
	$batch_raw = array(
		'source_id' => $source_id, 'batch_name' => $name . ' — paid definition', 'quantity' => 0,
		'admissions_per_link' => 2, 'total_admission_cap' => $cap, 'validity_type' => 'date_range',
		'start_date' => $start, 'end_date' => $end, 'value_type' => 'percent', 'value_amount' => 50,
		'expires_at' => $expires, 'status' => 'active', 'checkin_open_mode' => 'same_day',
		'max_per_phone' => 0, 'max_per_email' => 0,
		'notes' => __('Definition-only paid Partner Admission Offer; no complimentary claim tokens.', 'backstage-outreach'),
	);
	$batch = bvmgr_pass_claims_sanitize_batch_definition_payload($batch_raw);
	if (is_wp_error($batch)) { return $batch; }
	$campaign_raw = array(
		'campaign_name' => $name, 'campaign_purpose' => 'guest_pass_invitation',
		'email_subject' => __('Your 50%-off Admission Offer from Serenade Range', 'backstage-outreach'),
		'message_template' => "Hi {{first_name}},\n\nHere is your reusable 50%-off Admission Offer for eligible Serenade Range admissions:\n\n{{offer_url}}\n\nThe offer discounts up to two eligible admissions per order; additional admissions remain full price.",
		'internal_notes' => __('Reusable Party campaign. No recipient claim tokens or automatic messages.', 'backstage-outreach'),
		'related_source_id' => $source_id, 'related_batch_id' => 0,
		'validity_type' => 'date_range', 'start_date' => $start, 'end_date' => $end,
		'expires_at' => $expires, 'admissions_per_recipient' => 2, 'total_admission_cap' => $cap,
		'status' => sanitize_key((string) ($raw['status'] ?? 'draft')), 'eligibility_mode' => 'anyone_with_invite',
	);
	return array('batch_payload' => $batch, 'campaign_payload' => $campaign_raw, 'source_id' => $source_id, 'reviewed_at' => time(), 'review_token' => wp_generate_uuid4(), 'digest' => backstage_outreach_party_workflow_digest(array($batch, $campaign_raw)));
}

function backstage_outreach_party_partner_campaign_create(array $review, int $user_id)
{
	global $wpdb;
	$batch_payload = (array) ($review['batch_payload'] ?? array());
	$campaign_raw = (array) ($review['campaign_payload'] ?? array());
	if ((int) ($batch_payload['quantity'] ?? -1) !== 0 || (string) ($batch_payload['value_type'] ?? '') !== 'percent' || (float) ($batch_payload['value_amount'] ?? 0) !== 50.0 || absint($batch_payload['admissions_per_link'] ?? 0) !== 2) {
		return new WP_Error('partner_campaign_review_invalid', __('The reviewed Partner campaign definition is invalid.', 'backstage-outreach'));
	}
	$batch = vms_pass_outreach_create_business_offer_batch($batch_payload, $user_id);
	if (is_wp_error($batch)) { return $batch; }
	$batch_id = absint($batch['id'] ?? 0);
	$campaign_raw['related_batch_id'] = $batch_id;
	$payload = vms_pass_outreach_sanitize_campaign_payload($campaign_raw, 0);
	if (is_wp_error($payload)) {
		$wpdb->delete(bvmgr_admission_table_pass_batches(), array('id' => $batch_id), array('%d'));
		return $payload;
	}
	$payload['created_by'] = $user_id;
	$payload['created_at'] = backstage_outreach_party_now();
	if ($wpdb->insert(vms_admission_table_pass_outreach_campaigns(), $payload, vms_pass_outreach_campaign_db_formats($payload)) === false) {
		$wpdb->delete(bvmgr_admission_table_pass_batches(), array('id' => $batch_id), array('%d'));
		return new WP_Error('partner_campaign_create_failed', __('The reviewed Partner campaign could not be created.', 'backstage-outreach'));
	}
	$campaign_id = absint($wpdb->insert_id);
	backstage_outreach_party_audit('partner_campaign_created', array('campaign_id' => $campaign_id, 'batch_id' => $batch_id, 'source_id' => absint($review['source_id'] ?? 0), 'quantity' => 0, 'total_admission_cap' => absint($payload['total_admission_cap'] ?? 0)), $user_id, hash('sha256', 'partner-campaign|' . $campaign_id . '|' . $batch_id));
	return array('campaign_id' => $campaign_id, 'batch_id' => $batch_id, 'campaign' => vms_pass_outreach_get_campaign_by_id($campaign_id), 'batch' => $batch);
}

function backstage_outreach_party_bulk_link_preview(int $campaign_id, array $party_ids, int $admission_cap = 0, int $order_cap = 0)
{
	$party_ids = array_values(array_unique(array_filter(array_map('absint', $party_ids))));
	if (!$party_ids || count($party_ids) > 500) { return new WP_Error('partner_bulk_selection_invalid', __('Select between one and 500 Parties.', 'backstage-outreach')); }
	$rows = array();
	foreach ($party_ids as $party_id) {
		$validated = backstage_outreach_party_referral_validate($party_id, $campaign_id, $admission_cap, $order_cap, '');
		if (is_wp_error($validated)) {
			$party = backstage_outreach_party_get($party_id);
			$rows[] = array('party_id' => $party_id, 'name' => (string) ($party['display_name'] ?? '#' . $party_id), 'status' => 'blocked', 'error' => $validated->get_error_message());
			continue;
		}
		$rows[] = array('party_id' => $party_id, 'name' => (string) $validated['party']['display_name'], 'status' => 'ready', 'configuration_hash' => (string) $validated['configuration_hash']);
	}
	return array('campaign_id' => $campaign_id, 'party_ids' => $party_ids, 'admission_cap' => $admission_cap, 'order_cap' => $order_cap, 'rows' => $rows, 'review_token' => wp_generate_uuid4(), 'digest' => backstage_outreach_party_workflow_digest($rows), 'reviewed_at' => time());
}

function backstage_outreach_party_bulk_link_commit(array $review, int $user_id): array
{
	$current = backstage_outreach_party_bulk_link_preview(absint($review['campaign_id'] ?? 0), (array) ($review['party_ids'] ?? array()), absint($review['admission_cap'] ?? 0), absint($review['order_cap'] ?? 0));
	if (is_wp_error($current) || !hash_equals((string) ($review['digest'] ?? ''), is_array($current) ? (string) ($current['digest'] ?? '') : '')) {
		return array('created' => 0, 'existing' => 0, 'failed' => array('review' => __('Party or campaign eligibility changed after review.', 'backstage-outreach')));
	}
	$result = array('created' => 0, 'existing' => 0, 'failed' => array());
	global $wpdb;
	foreach ($current['rows'] as $row) {
		$party_id = absint($row['party_id']);
		if ((string) $row['status'] !== 'ready') { $result['failed'][$party_id] = (string) $row['error']; continue; }
		$existing_id = absint($wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE campaign_id=%d AND party_id=%d', backstage_outreach_party_table('referral_distributions'), absint($review['campaign_id']), $party_id)));
		$validated = backstage_outreach_party_referral_validate($party_id, absint($review['campaign_id']), absint($review['admission_cap']), absint($review['order_cap']), '');
		$created = is_wp_error($validated) ? $validated : backstage_outreach_party_referral_create($validated, $user_id);
		if (is_wp_error($created) || !is_array($created) || absint($created['coupon_id'] ?? 0) <= 0 || (string) ($created['coupon_code'] ?? '') === '' || backstage_outreach_party_referral_url($created) === '') {
			$result['failed'][$party_id] = is_wp_error($created) ? $created->get_error_message() : __('The link or managed coupon could not be verified.', 'backstage-outreach');
			continue;
		}
		backstage_outreach_party_save_campaign_role($party_id, absint($review['campaign_id']), 'partner', $user_id);
		$existing_id > 0 ? $result['existing']++ : $result['created']++;
	}
	return $result;
}

function backstage_outreach_party_primary_email(int $party_id): string
{
	foreach (backstage_outreach_party_get_contact_methods($party_id, false) as $method) {
		if ((string) ($method['method_type'] ?? '') === 'email' && !empty($method['is_primary'])) { return sanitize_email((string) $method['value']); }
	}
	foreach (backstage_outreach_party_get_contact_methods($party_id, false) as $method) {
		if ((string) ($method['method_type'] ?? '') === 'email') { return sanitize_email((string) $method['value']); }
	}
	return '';
}

function backstage_outreach_party_contact_rows(int $campaign_id): array
{
	global $wpdb;
	$rows = $wpdb->get_results($wpdb->prepare(
		'SELECT d.*,p.display_name,p.given_name,p.party_type,c.campaign_name,c.email_subject,c.message_template,
		(SELECT activity_status FROM %i a WHERE a.distribution_id=d.id ORDER BY a.id DESC LIMIT 1) AS contact_status,
		(SELECT COUNT(*) FROM %i a2 WHERE a2.distribution_id=d.id AND a2.activity_type=%s AND a2.activity_status=%s) AS handoff_count,
		(SELECT COUNT(*) FROM %i r WHERE r.distribution_id=d.id AND r.status IN (%s,%s,%s)) AS redemption_count
		FROM %i d INNER JOIN %i p ON p.id=d.party_id INNER JOIN %i c ON c.id=d.campaign_id
		WHERE d.campaign_id=%d ORDER BY p.display_name ASC,d.id ASC',
		backstage_outreach_party_table('contact_activities'), backstage_outreach_party_table('contact_activities'), 'email_handoff', 'handed_off',
		backstage_outreach_party_table('referral_redemptions'), 'pending', 'paid', 'partially_refunded',
		backstage_outreach_party_table('referral_distributions'), backstage_outreach_party_table('parties'), vms_admission_table_pass_outreach_campaigns(), $campaign_id
	), ARRAY_A);
	$rows = is_array($rows) ? $rows : array();
	foreach ($rows as &$row) {
		$row['email'] = backstage_outreach_party_primary_email(absint($row['party_id']));
		$row['suppressed'] = $row['email'] !== '' && function_exists('vms_outreach_email_is_suppressed') && vms_outreach_email_is_suppressed($row['email']);
		$row['offer_url'] = backstage_outreach_party_referral_url($row);
		$row['affiliations'] = backstage_outreach_party_get_affiliations(absint($row['party_id']));
	}
	unset($row);
	return array_values($rows);
}

function backstage_outreach_party_invitation_content(array $row): array
{
	$first = sanitize_text_field((string) ($row['given_name'] ?? ''));
	if ($first === '') { $first = sanitize_text_field((string) ($row['display_name'] ?? 'Partner')); }
	$subject = sanitize_text_field((string) ($row['email_subject'] ?? __('Your 50%-off Admission Offer', 'backstage-outreach')));
	$template = (string) ($row['message_template'] ?? '');
	$message = strtr($template, array('{{first_name}}' => $first, '{{name}}' => (string) ($row['display_name'] ?? ''), '{{offer_url}}' => (string) ($row['offer_url'] ?? '')));
	return array('subject' => $subject, 'message' => $message, 'content_hash' => backstage_outreach_party_workflow_digest(array($row['email'] ?? '', $subject, $message, $row['id'] ?? 0)));
}

function backstage_outreach_party_invitation_offer_error(array $row)
{
	$distribution_id = absint($row['id'] ?? $row['distribution_id'] ?? 0);
	$current = backstage_outreach_party_referral_get_distribution($distribution_id);
	if (!is_array($current)
		|| absint($current['campaign_id'] ?? 0) !== absint($row['campaign_id'] ?? 0)
		|| absint($current['party_id'] ?? 0) !== absint($row['party_id'] ?? 0)) {
		return new WP_Error('party_invitation_distribution_invalid', __('The reviewed Partner distribution is no longer available.', 'backstage-outreach'));
	}
	$error = backstage_outreach_discount_distribution_error($current);
	if (is_wp_error($error)) {
		return $error;
	}
	$token = backstage_outreach_party_referral_token($current);
	if ($token === '' || empty($current['token_hash']) || !hash_equals((string) $current['token_hash'], hash('sha256', $token)) || backstage_outreach_party_referral_url($current) === '') {
		return new WP_Error('party_invitation_signature_invalid', __('The signed Partner referral is invalid and must be reviewed again.', 'backstage-outreach'));
	}
	return null;
}

function backstage_outreach_party_invitation_preview(int $campaign_id, array $distribution_ids, string $mode = 'first')
{
	$distribution_ids = array_values(array_unique(array_filter(array_map('absint', $distribution_ids))));
	$mode = $mode === 'resend' ? 'resend' : 'first';
	$all = array_column(backstage_outreach_party_contact_rows($campaign_id), null, 'id');
	$rows = array();
	foreach ($distribution_ids as $distribution_id) {
		$row = $all[$distribution_id] ?? null;
		if (!is_array($row)) { continue; }
		$reason = '';
		$offer_error = backstage_outreach_party_invitation_offer_error($row);
		if (is_wp_error($offer_error)) { $reason = $offer_error->get_error_message(); }
		elseif ((string) $row['email'] === '') { $reason = __('Missing email', 'backstage-outreach'); }
		elseif (!empty($row['suppressed'])) { $reason = __('Suppressed', 'backstage-outreach'); }
		elseif ($mode === 'first' && absint($row['handoff_count'] ?? 0) > 0) { $reason = __('Prior successful handoff; use deliberate resend', 'backstage-outreach'); }
		$content = backstage_outreach_party_invitation_content($row);
		$rows[] = array_merge(array('distribution_id' => $distribution_id, 'campaign_id' => absint($row['campaign_id']), 'party_id' => absint($row['party_id']), 'name' => (string) $row['display_name'], 'email' => (string) $row['email'], 'eligible' => $reason === '', 'blocked_reason' => $reason), $content);
	}
	return array('campaign_id' => $campaign_id, 'mode' => $mode, 'rows' => $rows, 'review_token' => wp_generate_uuid4(), 'reviewed_at' => time(), 'digest' => backstage_outreach_party_workflow_digest($rows));
}

function backstage_outreach_party_record_activity(array $data, int $user_id)
{
	global $wpdb;
	$request_key = sanitize_text_field((string) ($data['request_key'] ?? ''));
	if (!preg_match('/^[a-f0-9]{64}$/', $request_key)) { $request_key = backstage_outreach_party_workflow_digest(array($data, wp_generate_uuid4())); }
	$insert = array(
		'campaign_id' => absint($data['campaign_id'] ?? 0), 'distribution_id' => absint($data['distribution_id'] ?? 0), 'party_id' => absint($data['party_id'] ?? 0),
		'activity_type' => sanitize_key((string) ($data['activity_type'] ?? 'note')), 'activity_status' => sanitize_key((string) ($data['activity_status'] ?? 'logged')),
		'contact_method' => ($method = sanitize_key((string) ($data['contact_method'] ?? ''))) !== '' ? $method : null,
		'contact_value' => ($value = sanitize_text_field((string) ($data['contact_value'] ?? ''))) !== '' ? $value : null,
		'subject_snapshot' => ($subject = sanitize_text_field((string) ($data['subject_snapshot'] ?? ''))) !== '' ? $subject : null,
		'message_snapshot' => ($message = sanitize_textarea_field((string) ($data['message_snapshot'] ?? ''))) !== '' ? $message : null,
		'content_hash' => ($hash = sanitize_text_field((string) ($data['content_hash'] ?? ''))) !== '' ? $hash : null,
		'notes' => ($notes = sanitize_textarea_field((string) ($data['notes'] ?? ''))) !== '' ? $notes : null,
		'request_key' => $request_key, 'created_by' => $user_id, 'created_at' => backstage_outreach_party_now(),
	);
	if ($wpdb->insert(backstage_outreach_party_table('contact_activities'), $insert) === false) {
		$existing = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE request_key=%s', backstage_outreach_party_table('contact_activities'), $request_key), ARRAY_A);
		return is_array($existing) ? $existing : new WP_Error('party_activity_failed', __('The contact activity could not be recorded.', 'backstage-outreach'));
	}
	return $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', backstage_outreach_party_table('contact_activities'), absint($wpdb->insert_id)), ARRAY_A);
}

function backstage_outreach_party_invitation_handoff(array $review, int $user_id): array
{
	$current = backstage_outreach_party_invitation_preview(absint($review['campaign_id'] ?? 0), array_column((array) ($review['rows'] ?? array()), 'distribution_id'), (string) ($review['mode'] ?? 'first'));
	if (!hash_equals((string) ($review['digest'] ?? ''), (string) $current['digest'])) { return array('handed_off' => 0, 'failed' => array('review' => __('Invitation data changed after review.', 'backstage-outreach'))); }
	$result = array('handed_off' => 0, 'failed' => array());
	global $wpdb;
	foreach ($current['rows'] as $row) {
		$id = absint($row['distribution_id']);
		if (empty($row['eligible'])) { $result['failed'][$id] = (string) $row['blocked_reason']; continue; }
		$lock = 'outreach_party_mail_' . $id;
		if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,5)', $lock)) !== 1) { $result['failed'][$id] = __('Another handoff is in progress.', 'backstage-outreach'); continue; }
		try {
			$offer_error = backstage_outreach_party_invitation_offer_error($row);
			$current_email = backstage_outreach_party_primary_email(absint($row['party_id']));
			if (is_wp_error($offer_error)) { $result['failed'][$id] = $offer_error->get_error_message(); continue; }
			if ($current_email === '' || !is_email($current_email) || strcasecmp($current_email, (string) $row['email']) !== 0) { $result['failed'][$id] = __('The selected email changed after review.', 'backstage-outreach'); continue; }
			if (function_exists('vms_outreach_email_is_suppressed') && vms_outreach_email_is_suppressed($current_email)) { $result['failed'][$id] = __('Suppressed', 'backstage-outreach'); continue; }
			$prior = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE distribution_id=%d AND activity_type=%s AND activity_status=%s', backstage_outreach_party_table('contact_activities'), $id, 'email_handoff', 'handed_off'));
			if ((string) $review['mode'] === 'first' && $prior > 0) { $result['failed'][$id] = __('A prior handoff exists; use deliberate resend.', 'backstage-outreach'); continue; }
			$accepted = wp_mail((string) $row['email'], (string) $row['subject'], (string) $row['message'], array('Content-Type: text/plain; charset=UTF-8'));
			$activity = backstage_outreach_party_record_activity(array(
				'campaign_id' => absint($review['campaign_id']), 'distribution_id' => $id, 'party_id' => absint($row['party_id']),
				'activity_type' => 'email_handoff', 'activity_status' => $accepted ? 'handed_off' : 'failed', 'contact_method' => 'email', 'contact_value' => (string) $row['email'],
				'subject_snapshot' => (string) $row['subject'], 'message_snapshot' => (string) $row['message'], 'content_hash' => (string) $row['content_hash'],
				'notes' => $accepted ? __('Accepted by the configured WordPress mailer; delivery is not asserted.', 'backstage-outreach') : __('The WordPress mailer rejected the handoff.', 'backstage-outreach'),
				'request_key' => hash('sha256', (string) $review['review_token'] . '|' . $id),
			), $user_id);
			if ($accepted && !is_wp_error($activity)) { $result['handed_off']++; } else { $result['failed'][$id] = is_wp_error($activity) ? $activity->get_error_message() : __('The mailer rejected the handoff.', 'backstage-outreach'); }
		} finally {
			$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
		}
	}
	return $result;
}

function backstage_outreach_party_workflow_redirect(string $anchor = 'outreach-party-workflows'): void
{
	backstage_outreach_party_admin_redirect(array(), $anchor);
}

function backstage_outreach_party_handle_adoption_review(): void
{
	$user_id = backstage_outreach_party_require_admin('backstage_outreach_party_adoption_review');
	$review = backstage_outreach_party_adoption_preview(absint($_POST['source_id'] ?? 0), (array) ($_POST['campaign_ids'] ?? array()));
	if (is_wp_error($review)) {
		delete_transient(backstage_outreach_party_workflow_review_key('adoption'));
		backstage_outreach_party_admin_notice($review->get_error_message(), 'error');
	} else {
		$review['review_token'] = wp_generate_uuid4();
		$review['reviewed_by'] = $user_id;
		set_transient(backstage_outreach_party_workflow_review_key('adoption'), $review, 30 * MINUTE_IN_SECONDS);
		backstage_outreach_party_admin_notice(__('Historical-recipient adoption preview is ready. No records were changed.', 'backstage-outreach'), 'info');
	}
	backstage_outreach_party_workflow_redirect('outreach-party-adoption-review');
}
add_action('admin_post_backstage_outreach_party_adoption_review', 'backstage_outreach_party_handle_adoption_review');

function backstage_outreach_party_handle_adoption_commit(): void
{
	$user_id = backstage_outreach_party_require_admin('backstage_outreach_party_adoption_commit');
	$review = get_transient(backstage_outreach_party_workflow_review_key('adoption'));
	$token = sanitize_text_field((string) wp_unslash($_POST['review_token'] ?? ''));
	if (!is_array($review) || empty($review['review_token']) || !hash_equals((string) $review['review_token'], $token) || absint($review['reviewed_by'] ?? 0) !== $user_id || empty($_POST['confirm_adoption'])) {
		backstage_outreach_party_admin_notice(__('The adoption preview expired or is invalid. Preview again.', 'backstage-outreach'), 'error');
		backstage_outreach_party_workflow_redirect('outreach-party-adoption');
	}
	$choices = array();
	foreach ((array) ($_POST['adoption'] ?? array()) as $group_key => $raw) {
		$raw = (array) $raw;
		$choices[] = array('group_key' => sanitize_key((string) $group_key), 'selected' => !empty($raw['selected']), 'identity_reviewed' => !empty($raw['identity_reviewed']), 'resolution' => sanitize_key((string) ($raw['resolution'] ?? '')), 'party_id' => absint($raw['party_id'] ?? 0));
	}
	$result = backstage_outreach_party_adoption_commit($review, $choices, $user_id);
	delete_transient(backstage_outreach_party_workflow_review_key('adoption'));
	$message = sprintf(__('Adoption finished: %1$d created, %2$d reused, %3$d historical snapshots linked, %4$d skipped, %5$d unresolved.', 'backstage-outreach'), $result['created'], $result['reused'], $result['linked'], $result['skipped'], count($result['errors']));
	backstage_outreach_party_admin_notice($message . ($result['errors'] ? ' ' . implode(' ', array_slice(array_values($result['errors']), 0, 3)) : ''), $result['errors'] ? 'warning' : 'success');
	backstage_outreach_party_workflow_redirect('outreach-party-adoption');
}
add_action('admin_post_backstage_outreach_party_adoption_commit', 'backstage_outreach_party_handle_adoption_commit');

function backstage_outreach_party_handle_campaign_review(): void
{
	$user_id = backstage_outreach_party_require_admin('backstage_outreach_party_campaign_review');
	$review = backstage_outreach_party_partner_campaign_review((array) wp_unslash($_POST));
	if (is_wp_error($review)) {
		delete_transient(backstage_outreach_party_workflow_review_key('campaign'));
		backstage_outreach_party_admin_notice($review->get_error_message(), 'error');
	} else {
		$review['reviewed_by'] = $user_id;
		set_transient(backstage_outreach_party_workflow_review_key('campaign'), $review, 30 * MINUTE_IN_SECONDS);
		backstage_outreach_party_admin_notice(__('Paid Partner campaign preview is ready. No batch, tokens, or campaign were created.', 'backstage-outreach'), 'info');
	}
	backstage_outreach_party_workflow_redirect('outreach-party-campaign-review');
}
add_action('admin_post_backstage_outreach_party_campaign_review', 'backstage_outreach_party_handle_campaign_review');

function backstage_outreach_party_handle_campaign_create(): void
{
	$user_id = backstage_outreach_party_require_admin('backstage_outreach_party_campaign_create');
	$review = get_transient(backstage_outreach_party_workflow_review_key('campaign'));
	$token = sanitize_text_field((string) wp_unslash($_POST['review_token'] ?? ''));
	if (!is_array($review) || !hash_equals((string) ($review['review_token'] ?? ''), $token) || absint($review['reviewed_by'] ?? 0) !== $user_id || empty($_POST['confirm_campaign'])) {
		backstage_outreach_party_admin_notice(__('The campaign preview expired or is invalid. Preview again.', 'backstage-outreach'), 'error');
		backstage_outreach_party_workflow_redirect('outreach-party-campaign');
	}
	$result = backstage_outreach_party_partner_campaign_create($review, $user_id);
	delete_transient(backstage_outreach_party_workflow_review_key('campaign'));
	backstage_outreach_party_admin_notice(is_wp_error($result) ? $result->get_error_message() : sprintf(__('Paid Partner campaign #%1$d and definition-only batch #%2$d created. No links, claim tokens, or messages were generated.', 'backstage-outreach'), $result['campaign_id'], $result['batch_id']), is_wp_error($result) ? 'error' : 'success');
	backstage_outreach_party_workflow_redirect('outreach-party-campaign');
}
add_action('admin_post_backstage_outreach_party_campaign_create', 'backstage_outreach_party_handle_campaign_create');

function backstage_outreach_party_handle_bulk_link_review(): void
{
	$user_id = backstage_outreach_party_require_admin('backstage_outreach_party_bulk_link_review');
	$review = backstage_outreach_party_bulk_link_preview(absint($_POST['campaign_id'] ?? 0), (array) ($_POST['party_ids'] ?? array()), absint($_POST['admission_cap'] ?? 0), absint($_POST['order_cap'] ?? 0));
	if (is_wp_error($review)) {
		delete_transient(backstage_outreach_party_workflow_review_key('bulk_links'));
		backstage_outreach_party_admin_notice($review->get_error_message(), 'error');
	} else {
		$review['reviewed_by'] = $user_id;
		set_transient(backstage_outreach_party_workflow_review_key('bulk_links'), $review, 30 * MINUTE_IN_SECONDS);
		backstage_outreach_party_admin_notice(__('Partner-link preview is ready. No links or coupons were created.', 'backstage-outreach'), 'info');
	}
	backstage_outreach_party_workflow_redirect('outreach-party-bulk-link-review');
}
add_action('admin_post_backstage_outreach_party_bulk_link_review', 'backstage_outreach_party_handle_bulk_link_review');

function backstage_outreach_party_handle_bulk_link_create(): void
{
	$user_id = backstage_outreach_party_require_admin('backstage_outreach_party_bulk_link_create');
	$review = get_transient(backstage_outreach_party_workflow_review_key('bulk_links'));
	$token = sanitize_text_field((string) wp_unslash($_POST['review_token'] ?? ''));
	if (!is_array($review) || !hash_equals((string) ($review['review_token'] ?? ''), $token) || absint($review['reviewed_by'] ?? 0) !== $user_id || empty($_POST['confirm_links'])) {
		backstage_outreach_party_admin_notice(__('The link preview expired or is invalid. Preview again.', 'backstage-outreach'), 'error');
		backstage_outreach_party_workflow_redirect('outreach-party-bulk-links');
	}
	$result = backstage_outreach_party_bulk_link_commit($review, $user_id);
	delete_transient(backstage_outreach_party_workflow_review_key('bulk_links'));
	backstage_outreach_party_admin_notice(sprintf(__('Bulk link generation finished: %1$d created, %2$d safely replayed, %3$d failed. Failed rows may be previewed and retried.', 'backstage-outreach'), $result['created'], $result['existing'], count($result['failed'])), $result['failed'] ? 'warning' : 'success');
	backstage_outreach_party_workflow_redirect('outreach-party-bulk-links');
}
add_action('admin_post_backstage_outreach_party_bulk_link_create', 'backstage_outreach_party_handle_bulk_link_create');

function backstage_outreach_party_handle_invitation_review(): void
{
	$user_id = backstage_outreach_party_require_admin('backstage_outreach_party_invitation_review');
	$review = backstage_outreach_party_invitation_preview(absint($_POST['campaign_id'] ?? 0), (array) ($_POST['distribution_ids'] ?? array()), sanitize_key((string) ($_POST['handoff_mode'] ?? 'first')));
	$review['reviewed_by'] = $user_id;
	set_transient(backstage_outreach_party_workflow_review_key('invitations'), $review, 20 * MINUTE_IN_SECONDS);
	backstage_outreach_party_admin_notice(__('Invitation preview is ready. No messages were handed off.', 'backstage-outreach'), 'info');
	backstage_outreach_party_workflow_redirect('outreach-party-invitation-review');
}
add_action('admin_post_backstage_outreach_party_invitation_review', 'backstage_outreach_party_handle_invitation_review');

function backstage_outreach_party_handle_invitation_handoff(): void
{
	$user_id = backstage_outreach_party_require_admin('backstage_outreach_party_invitation_handoff');
	$review = get_transient(backstage_outreach_party_workflow_review_key('invitations'));
	$token = sanitize_text_field((string) wp_unslash($_POST['review_token'] ?? ''));
	if (!is_array($review) || !hash_equals((string) ($review['review_token'] ?? ''), $token) || absint($review['reviewed_by'] ?? 0) !== $user_id || empty($_POST['confirm_handoff'])) {
		backstage_outreach_party_admin_notice(__('The invitation review expired, changed, or was not explicitly confirmed.', 'backstage-outreach'), 'error');
		backstage_outreach_party_workflow_redirect('outreach-party-contact-dashboard');
	}
	$result = backstage_outreach_party_invitation_handoff($review, $user_id);
	delete_transient(backstage_outreach_party_workflow_review_key('invitations'));
	backstage_outreach_party_admin_notice(sprintf(__('%1$d invitation(s) were accepted by the configured WordPress mailer for handoff; delivery is not asserted. %2$d failed or were blocked.', 'backstage-outreach'), $result['handed_off'], count($result['failed'])), $result['failed'] ? 'warning' : 'success');
	backstage_outreach_party_workflow_redirect('outreach-party-contact-dashboard');
}
add_action('admin_post_backstage_outreach_party_invitation_handoff', 'backstage_outreach_party_handle_invitation_handoff');

function backstage_outreach_party_handle_manual_activity(): void
{
	$user_id = backstage_outreach_party_require_admin('backstage_outreach_party_manual_activity');
	$distribution_id = absint($_POST['distribution_id'] ?? 0);
	$row = backstage_outreach_party_referral_get_distribution($distribution_id);
	$method = sanitize_key((string) ($_POST['contact_method'] ?? 'note'));
	if (!is_array($row) || !in_array($method, array('email', 'phone', 'text', 'social', 'note'), true)) {
		backstage_outreach_party_admin_notice(__('The manual contact activity was invalid.', 'backstage-outreach'), 'error');
		backstage_outreach_party_workflow_redirect('outreach-party-contact-dashboard');
	}
	$result = backstage_outreach_party_record_activity(array(
		'campaign_id' => absint($row['campaign_id']), 'distribution_id' => $distribution_id, 'party_id' => absint($row['party_id']),
		'activity_type' => 'manual_contact', 'activity_status' => 'logged', 'contact_method' => $method,
		'contact_value' => sanitize_text_field((string) wp_unslash($_POST['contact_value'] ?? '')),
		'notes' => sanitize_textarea_field((string) wp_unslash($_POST['notes'] ?? '')),
		'request_key' => hash('sha256', 'manual|' . $user_id . '|' . wp_generate_uuid4()),
	), $user_id);
	backstage_outreach_party_admin_notice(is_wp_error($result) ? $result->get_error_message() : __('Manual contact activity recorded.', 'backstage-outreach'), is_wp_error($result) ? 'error' : 'success');
	backstage_outreach_party_workflow_redirect('outreach-party-contact-dashboard');
}
add_action('admin_post_backstage_outreach_party_manual_activity', 'backstage_outreach_party_handle_manual_activity');

function backstage_outreach_party_bulk_render_workspace(): void
{
	global $wpdb;
	$sources = backstage_outreach_party_admin_sources();
	$campaigns = vms_pass_outreach_get_campaigns(array('limit' => 500));
	$paid_campaigns = array_values(array_filter($campaigns, static function (array $campaign): bool {
		if ((string) ($campaign['status'] ?? '') !== 'active') { return false; }
		$batch = bvmgr_pass_claims_get_batch_by_id(absint($campaign['related_batch_id'] ?? 0));
		return is_array($batch) && (string) ($batch['status'] ?? '') === 'active' && in_array((string) ($batch['value_type'] ?? ''), array('percent', 'fixed'), true);
	}));
	$adoption = get_transient(backstage_outreach_party_workflow_review_key('adoption'));
	$campaign_review = get_transient(backstage_outreach_party_workflow_review_key('campaign'));
	$link_review = get_transient(backstage_outreach_party_workflow_review_key('bulk_links'));
	$invitation_review = get_transient(backstage_outreach_party_workflow_review_key('invitations'));
	$selected_campaign_id = absint($_GET['partner_campaign_id'] ?? ($link_review['campaign_id'] ?? 0));
	$paid_campaign_ids = array_map(static fn(array $campaign): int => absint($campaign['id'] ?? 0), $paid_campaigns);
	$selected_campaign = $selected_campaign_id > 0 && in_array($selected_campaign_id, $paid_campaign_ids, true) ? vms_pass_outreach_get_campaign_by_id($selected_campaign_id) : null;
	if (!is_array($selected_campaign)) { $selected_campaign_id = 0; }
	$source_parties = array();
	if (is_array($selected_campaign)) {
		$source_parties = $wpdb->get_results($wpdb->prepare('SELECT p.* FROM %i p INNER JOIN %i ps ON ps.party_id=p.id WHERE ps.source_id=%d AND ps.status=%s AND p.status=%s ORDER BY p.display_name ASC LIMIT 500', backstage_outreach_party_table('parties'), backstage_outreach_party_table('sources'), absint($selected_campaign['related_source_id']), 'active', 'active'), ARRAY_A);
	}

	echo '<section id="outreach-party-workflows" class="vms-pass-card"><h2>' . esc_html__('Realtor / Partner workflow', 'backstage-outreach') . '</h2><p>' . esc_html__('These reviewed tools adopt immutable recipient snapshots into canonical Parties, create a separate paid Partner offer, generate reusable links in bulk, and audit contact handoffs. Nothing runs on upgrade and no email is automatic.', 'backstage-outreach') . '</p>';
	echo '<details id="outreach-party-adoption" open><summary><strong>' . esc_html__('1. Review historical recipient adoption', 'backstage-outreach') . '</strong></summary><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-pass-form"><input type="hidden" name="action" value="backstage_outreach_party_adoption_review">';
	wp_nonce_field('backstage_outreach_party_adoption_review');
	echo '<div class="vms-pass-grid"><label>' . esc_html__('Source', 'backstage-outreach') . '<select name="source_id" required><option value="">' . esc_html__('Choose Source', 'backstage-outreach') . '</option>';
	foreach ($sources as $source) { echo '<option value="' . esc_attr((string) absint($source['id'])) . '">' . esc_html((string) $source['source_name']) . '</option>'; }
	echo '</select></label><label class="vms-pass-span-2">' . esc_html__('Historical campaigns', 'backstage-outreach') . '<select name="campaign_ids[]" multiple size="6" required>';
	foreach ($campaigns as $campaign) { echo '<option value="' . esc_attr((string) absint($campaign['id'])) . '">#' . esc_html((string) absint($campaign['id'])) . ' ' . esc_html((string) $campaign['campaign_name']) . '</option>'; }
	echo '</select></label></div><p class="vms-pass-actions"><button class="button" type="submit">' . esc_html__('Preview unique people', 'backstage-outreach') . '</button></p></form>';
	if (is_array($adoption)) {
		$s = (array) $adoption['summary'];
		echo '<div id="outreach-party-adoption-review" class="vms-pass-review-card"><h3>' . esc_html__('Adoption preview — no writes yet', 'backstage-outreach') . '</h3><p>' . esc_html(sprintf(__('%1$d snapshots → %2$d proposed people; %3$d compound cross-campaign matches; %4$d have email; %5$d missing email; %6$d ambiguous; %7$d skipped.', 'backstage-outreach'), $s['recipient_count'], $s['party_count'], absint($s['compatibility_match_count'] ?? 0), $s['email_count'], $s['missing_email_count'], $s['ambiguous_count'], $s['skipped_count'])) . '</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="backstage_outreach_party_adoption_commit"><input type="hidden" name="review_token" value="' . esc_attr((string) $adoption['review_token']) . '">';
		wp_nonce_field('backstage_outreach_party_adoption_commit');
		echo '<div class="vms-pass-table-scroll"><table class="widefat striped"><thead><tr><th>' . esc_html__('Adopt', 'backstage-outreach') . '</th><th>' . esc_html__('Person / history', 'backstage-outreach') . '</th><th>' . esc_html__('Contact / context', 'backstage-outreach') . '</th><th>' . esc_html__('Resolution', 'backstage-outreach') . '</th></tr></thead><tbody>';
		foreach ((array) $adoption['groups'] as $group) {
			$key = sanitize_key((string) $group['group_key']);
			echo '<tr><td><input type="checkbox" name="adoption[' . esc_attr($key) . '][selected]" value="1"' . checked((string) $group['display_name'] !== '', true, false) . '></td><td><strong>' . esc_html((string) $group['display_name']) . '</strong><br><small>' . esc_html(sprintf(__('%1$d snapshot(s), campaigns %2$s, historical contact %3$s', 'backstage-outreach'), count((array) $group['campaign_ids']), implode(', ', (array) $group['campaign_ids']), absint($group['contact_id']) ?: __('none', 'backstage-outreach'))) . '</small></td><td>' . esc_html(implode(', ', (array) $group['emails'])) . '<br>' . esc_html(implode(', ', (array) $group['phones'])) . '<br><small>' . esc_html(implode(' / ', (array) $group['organizations'])) . '</small><p><strong>' . esc_html__('Identity evidence:', 'backstage-outreach') . '</strong> ' . esc_html(implode(' ', (array) $group['identity_evidence'])) . '</p>';
			if ($group['ambiguous_reasons']) { echo '<p class="vms-outreach-party-warning">' . esc_html(implode(' ', (array) $group['ambiguous_reasons'])) . '</p><label><input type="checkbox" name="adoption[' . esc_attr($key) . '][identity_reviewed]" value="1"> ' . esc_html__('I reviewed this identity evidence', 'backstage-outreach') . '</label>'; }
			echo '</td><td>';
			if (absint($group['existing_party_id']) > 0) { echo esc_html(sprintf(__('Reuse already linked Party #%d', 'backstage-outreach'), absint($group['existing_party_id']))) . '<input type="hidden" name="adoption[' . esc_attr($key) . '][resolution]" value="reuse"><input type="hidden" name="adoption[' . esc_attr($key) . '][party_id]" value="' . esc_attr((string) absint($group['existing_party_id'])) . '">'; }
			else { echo '<select name="adoption[' . esc_attr($key) . '][resolution]"><option value="create">' . esc_html__('Create new Person', 'backstage-outreach') . '</option><option value="reuse">' . esc_html__('Reuse Party ID entered below', 'backstage-outreach') . '</option></select><input type="number" min="1" name="adoption[' . esc_attr($key) . '][party_id]" placeholder="' . esc_attr__('Party ID', 'backstage-outreach') . '">'; }
			echo '</td></tr>';
		}
		echo '</tbody></table></div><p><label><input type="checkbox" name="confirm_adoption" value="1" required> ' . esc_html__('I confirm these selected identity resolutions and preserved historical associations.', 'backstage-outreach') . '</label></p><p class="vms-pass-actions"><button class="button button-primary" type="submit">' . esc_html__('Commit selected adoptions', 'backstage-outreach') . '</button></p></form></div>';
	}
	echo '</details>';

	echo '<details id="outreach-party-campaign"><summary><strong>' . esc_html__('2. Create Reusable Partner Admission Offer', 'backstage-outreach') . '</strong></summary><p class="description">' . esc_html__('Creates a new 50%-off definition-only batch and a new campaign. It never changes an existing campaign or batch and creates zero complimentary tokens.', 'backstage-outreach') . '</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-pass-form"><input type="hidden" name="action" value="backstage_outreach_party_campaign_review">';
	wp_nonce_field('backstage_outreach_party_campaign_review');
	echo '<div class="vms-pass-grid"><label>' . esc_html__('Source', 'backstage-outreach') . '<select name="source_id" required><option value="">' . esc_html__('Choose Source', 'backstage-outreach') . '</option>';
	foreach ($sources as $source) { echo '<option value="' . esc_attr((string) absint($source['id'])) . '">' . esc_html((string) $source['source_name']) . '</option>'; }
	echo '</select></label><label>' . esc_html__('Campaign name', 'backstage-outreach') . '<input name="campaign_name" value="' . esc_attr__('October 2026 Realtor 50%-off Admission Offer', 'backstage-outreach') . '" required></label><label>' . esc_html__('Start date', 'backstage-outreach') . '<input type="date" name="start_date" value="' . esc_attr(wp_date('Y-m-d')) . '" required></label><label>' . esc_html__('End date', 'backstage-outreach') . '<input type="date" name="end_date" value="2026-10-31" required></label><label>' . esc_html__('Expires (site timezone)', 'backstage-outreach') . '<input type="datetime-local" name="expires_at" value="2026-10-31T23:59" required></label><label>' . esc_html__('Explicit total admission cap', 'backstage-outreach') . '<input type="number" name="total_admission_cap" min="1" max="50000" required></label><label>' . esc_html__('Initial status', 'backstage-outreach') . '<select name="status"><option value="draft">' . esc_html__('Draft', 'backstage-outreach') . '</option><option value="active">' . esc_html__('Active', 'backstage-outreach') . '</option></select></label></div><p><strong>' . esc_html__('Fixed terms:', 'backstage-outreach') . '</strong> ' . esc_html__('50% off, at most 2 eligible paid admissions discounted per order, additional eligible tickets full price.', 'backstage-outreach') . '</p><p class="vms-pass-actions"><button class="button" type="submit">' . esc_html__('Review new paid campaign', 'backstage-outreach') . '</button></p></form>';
	if (is_array($campaign_review)) {
		$b = (array) $campaign_review['batch_payload'];
		echo '<div id="outreach-party-campaign-review" class="vms-pass-review-card"><h3>' . esc_html__('Reviewed paid campaign — no writes yet', 'backstage-outreach') . '</h3><p>' . esc_html(sprintf(__('Source #%1$d; %2$s%% off; 2 discounted admissions per order; %3$s through %4$s; expires %5$s; total cap %6$d; zero claim tokens.', 'backstage-outreach'), $b['source_id'], $b['value_amount'], $b['start_date'], $b['end_date'], $b['expires_at'], $b['total_admission_cap'])) . '</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="backstage_outreach_party_campaign_create"><input type="hidden" name="review_token" value="' . esc_attr((string) $campaign_review['review_token']) . '">';
		wp_nonce_field('backstage_outreach_party_campaign_create');
		echo '<p><label><input type="checkbox" name="confirm_campaign" value="1" required> ' . esc_html__('Create this separate paid batch and campaign exactly as reviewed.', 'backstage-outreach') . '</label></p><button class="button button-primary" type="submit">' . esc_html__('Create reviewed campaign', 'backstage-outreach') . '</button></form></div>';
	}
	echo '</details>';

	echo '<details id="outreach-party-bulk-links"' . ($selected_campaign_id > 0 ? ' open' : '') . '><summary><strong>' . esc_html__('3. Generate Partner links in bulk', 'backstage-outreach') . '</strong></summary><form method="get" action="' . esc_url(admin_url('admin.php')) . '"><input type="hidden" name="page" value="' . esc_attr(vms_outreach_admin_menu_slug()) . '"><input type="hidden" name="section" value="directory"><label>' . esc_html__('Paid campaign', 'backstage-outreach') . '<select name="partner_campaign_id" required><option value="">' . esc_html__('Choose campaign', 'backstage-outreach') . '</option>';
	foreach ($paid_campaigns as $campaign) { echo '<option value="' . esc_attr((string) absint($campaign['id'])) . '"' . selected($selected_campaign_id, absint($campaign['id']), false) . '>#' . esc_html((string) absint($campaign['id'])) . ' ' . esc_html((string) $campaign['campaign_name']) . '</option>'; }
	echo '</select></label> <button class="button" type="submit">' . esc_html__('Load Source Parties', 'backstage-outreach') . '</button></form>';
	if (is_array($selected_campaign)) {
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="backstage_outreach_party_bulk_link_review"><input type="hidden" name="campaign_id" value="' . esc_attr((string) $selected_campaign_id) . '">';
		wp_nonce_field('backstage_outreach_party_bulk_link_review');
		echo '<p><label><input type="checkbox" data-vms-party-select-all> ' . esc_html__('Select all loaded active Source Parties', 'backstage-outreach') . '</label></p><div class="vms-pass-table-scroll"><table class="widefat striped"><thead><tr><th>' . esc_html__('Select', 'backstage-outreach') . '</th><th>' . esc_html__('Party', 'backstage-outreach') . '</th><th>' . esc_html__('Type', 'backstage-outreach') . '</th><th>' . esc_html__('Email', 'backstage-outreach') . '</th></tr></thead><tbody>';
		foreach ((array) $source_parties as $party) { $email = backstage_outreach_party_primary_email(absint($party['id'])); echo '<tr><td><input data-vms-party-select type="checkbox" name="party_ids[]" value="' . esc_attr((string) absint($party['id'])) . '"></td><td>' . esc_html((string) $party['display_name']) . '</td><td>' . esc_html((string) $party['party_type']) . '</td><td>' . esc_html($email !== '' ? $email : __('Missing', 'backstage-outreach')) . '</td></tr>'; }
		echo '</tbody></table></div><div class="vms-pass-grid"><label>' . esc_html__('Optional per-Partner admission cap', 'backstage-outreach') . '<input type="number" name="admission_cap" min="0" max="50000" value="0"></label><label>' . esc_html__('Optional per-Partner order cap', 'backstage-outreach') . '<input type="number" name="order_cap" min="0" max="50000" value="0"></label></div><p class="vms-pass-actions"><button class="button" type="submit">' . esc_html__('Preview selected links', 'backstage-outreach') . '</button></p></form>';
	}
	if (is_array($link_review)) {
		$ready = count(array_filter((array) $link_review['rows'], static fn(array $row): bool => $row['status'] === 'ready'));
		echo '<div id="outreach-party-bulk-link-review" class="vms-pass-review-card"><h3>' . esc_html__('Bulk-link preview — no writes yet', 'backstage-outreach') . '</h3><p>' . esc_html(sprintf(__('%1$d ready; %2$d blocked. Replaying the same selection reuses its one existing Party link and managed coupon.', 'backstage-outreach'), $ready, count($link_review['rows']) - $ready)) . '</p><ul>';
		foreach ((array) $link_review['rows'] as $row) { echo '<li>' . esc_html((string) $row['name']) . ' — ' . esc_html((string) $row['status'] . (!empty($row['error']) ? ': ' . $row['error'] : '')) . '</li>'; }
		echo '</ul><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="backstage_outreach_party_bulk_link_create"><input type="hidden" name="review_token" value="' . esc_attr((string) $link_review['review_token']) . '">';
		wp_nonce_field('backstage_outreach_party_bulk_link_create');
		echo '<p><label><input type="checkbox" name="confirm_links" value="1" required> ' . esc_html__('Generate or safely reuse the reviewed links and managed coupons.', 'backstage-outreach') . '</label></p><button class="button button-primary" type="submit">' . esc_html__('Generate reviewed links', 'backstage-outreach') . '</button></form></div>';
	}
	echo '</details>';

	if ($selected_campaign_id > 0) {
		$contact_rows = backstage_outreach_party_contact_rows($selected_campaign_id);
		echo '<details id="outreach-party-contact-dashboard" open><summary><strong>' . esc_html__('4. Partner contact dashboard', 'backstage-outreach') . '</strong></summary><p>' . esc_html__('“Sent-handoff” means only that WordPress accepted the message for handoff; it does not mean delivered. Manual channels remain available and are audited.', 'backstage-outreach') . '</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="backstage_outreach_party_invitation_review"><input type="hidden" name="campaign_id" value="' . esc_attr((string) $selected_campaign_id) . '">';
		wp_nonce_field('backstage_outreach_party_invitation_review');
		echo '<p><label>' . esc_html__('Handoff mode', 'backstage-outreach') . ' <select name="handoff_mode"><option value="first">' . esc_html__('First handoff only', 'backstage-outreach') . '</option><option value="resend">' . esc_html__('Deliberate resend', 'backstage-outreach') . '</option></select></label></p><div class="vms-pass-table-scroll"><table class="widefat striped"><thead><tr><th>' . esc_html__('Email', 'backstage-outreach') . '</th><th>' . esc_html__('Partner / context', 'backstage-outreach') . '</th><th>' . esc_html__('Offer', 'backstage-outreach') . '</th><th>' . esc_html__('Contact / results', 'backstage-outreach') . '</th><th>' . esc_html__('Manual log', 'backstage-outreach') . '</th></tr></thead><tbody>';
		foreach ($contact_rows as $row) {
			$affiliations = array_map(static fn(array $a): string => (string) ($a['organization_name'] ?? ''), (array) $row['affiliations']);
			echo '<tr><td><input type="checkbox" name="distribution_ids[]" value="' . esc_attr((string) absint($row['id'])) . '"' . disabled((string) $row['email'] === '' || !empty($row['suppressed']), true, false) . '></td><td><strong>' . esc_html((string) $row['display_name']) . '</strong><br><small>' . esc_html(implode(', ', array_filter($affiliations))) . '</small><br>' . esc_html((string) ($row['email'] ?: __('Missing email', 'backstage-outreach'))) . (!empty($row['suppressed']) ? '<br><strong>' . esc_html__('Suppressed', 'backstage-outreach') . '</strong>' : '') . '</td><td><input class="regular-text" readonly value="' . esc_attr((string) $row['offer_url']) . '"></td><td>' . esc_html((string) ($row['contact_status'] ?: __('Ready', 'backstage-outreach'))) . '<br>' . esc_html(sprintf(__('%1$d handoff(s); %2$d redemption(s)', 'backstage-outreach'), absint($row['handoff_count']), absint($row['redemption_count']))) . '</td><td>' . esc_html__('Use manual log below', 'backstage-outreach') . '</td></tr>';
		}
		echo '</tbody></table></div><p class="vms-pass-actions"><button class="button" type="submit">' . esc_html__('Preview personalized invitations', 'backstage-outreach') . '</button></p></form>';
		echo '<details><summary><strong>' . esc_html__('Log manual contact or note', 'backstage-outreach') . '</strong></summary><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-pass-form"><input type="hidden" name="action" value="backstage_outreach_party_manual_activity">';
		wp_nonce_field('backstage_outreach_party_manual_activity');
		echo '<div class="vms-pass-grid"><label>' . esc_html__('Partner', 'backstage-outreach') . '<select name="distribution_id" required><option value="">' . esc_html__('Choose Partner', 'backstage-outreach') . '</option>';
		foreach ($contact_rows as $row) { echo '<option value="' . esc_attr((string) absint($row['id'])) . '">' . esc_html((string) $row['display_name']) . '</option>'; }
		echo '</select></label><label>' . esc_html__('Method', 'backstage-outreach') . '<select name="contact_method"><option value="email">' . esc_html__('Email', 'backstage-outreach') . '</option><option value="phone">' . esc_html__('Phone', 'backstage-outreach') . '</option><option value="text">' . esc_html__('Text', 'backstage-outreach') . '</option><option value="social">' . esc_html__('Social', 'backstage-outreach') . '</option><option value="note">' . esc_html__('Note only', 'backstage-outreach') . '</option></select></label><label>' . esc_html__('Contact value / handle', 'backstage-outreach') . '<input name="contact_value"></label><label class="vms-pass-span-2">' . esc_html__('Notes', 'backstage-outreach') . '<textarea name="notes" required></textarea></label></div><button class="button" type="submit">' . esc_html__('Record activity', 'backstage-outreach') . '</button></form></details>';
		if (is_array($invitation_review) && absint($invitation_review['campaign_id'] ?? 0) === $selected_campaign_id) {
			echo '<div id="outreach-party-invitation-review" class="vms-pass-review-card"><h3>' . esc_html__('Exact invitation review — no handoff yet', 'backstage-outreach') . '</h3>';
			foreach ((array) $invitation_review['rows'] as $row) { echo '<details><summary>' . esc_html((string) $row['name'] . ' — ' . (string) $row['email'] . (empty($row['eligible']) ? ' — ' . (string) $row['blocked_reason'] : '')) . '</summary><p><strong>' . esc_html((string) $row['subject']) . '</strong></p><pre style="white-space:pre-wrap">' . esc_html((string) $row['message']) . '</pre></details>'; }
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="backstage_outreach_party_invitation_handoff"><input type="hidden" name="review_token" value="' . esc_attr((string) $invitation_review['review_token']) . '">';
			wp_nonce_field('backstage_outreach_party_invitation_handoff');
			echo '<p><label><input type="checkbox" name="confirm_handoff" value="1" required> ' . esc_html__('I confirm this exact first-time handoff or deliberate resend.', 'backstage-outreach') . '</label></p><button class="button button-primary" type="submit">' . esc_html__('Hand off eligible emails', 'backstage-outreach') . '</button></form></div>';
		}
		echo '</details>';
	}
	echo '<script>document.addEventListener("change",function(e){if(!e.target.matches("[data-vms-party-select-all]"))return;document.querySelectorAll("[data-vms-party-select]").forEach(function(box){box.checked=e.target.checked;});});</script></section>';
}
