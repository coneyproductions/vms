<?php
/**
 * Reusable paid Admission Offers for canonical Outreach Parties.
 *
 * Party distributions have their own identifiers, signatures, coupon ownership,
 * and redemption ledger. The established business distribution rows remain
 * authoritative and are never reinterpreted as Party identities.
 */

defined('ABSPATH') || exit;

function backstage_outreach_party_referral_review_key(int $party_id): string
{
	return 'backstage_outreach_party_referral_review_' . get_current_user_id() . '_' . $party_id;
}

function backstage_outreach_party_referral_signature(array $row): string
{
	$payload = 'party-referral|1|' . absint($row['id'] ?? 0) . '|' . absint($row['campaign_id'] ?? 0) . '|' . absint($row['party_id'] ?? 0) . '|' . (string) ($row['public_key'] ?? '') . '|' . (string) ($row['created_at'] ?? '');
	return hash_hmac('sha256', $payload, wp_salt('auth'));
}

function backstage_outreach_party_referral_token(array $row): string
{
	$key = sanitize_key((string) ($row['public_key'] ?? ''));
	return $key === '' ? '' : $key . '.' . backstage_outreach_party_referral_signature($row);
}

function backstage_outreach_party_referral_url(array $row): string
{
	return home_url('/admission-offer/partner/' . rawurlencode(backstage_outreach_party_referral_token($row)));
}

function backstage_outreach_party_referral_get_distribution(int $distribution_id): ?array
{
	if ($distribution_id <= 0) {
		return null;
	}
	global $wpdb;
	$row = $wpdb->get_row($wpdb->prepare(
		'SELECT d.*, p.display_name AS party_name, p.party_type, p.status AS party_status, ps.status AS membership_status,
		c.campaign_name, c.status AS campaign_status, c.related_batch_id, c.related_source_id, c.expires_at AS campaign_expires_at,
		c.total_admission_cap AS campaign_ticket_cap, c.admissions_per_recipient
		FROM %i d INNER JOIN %i p ON p.id=d.party_id
		INNER JOIN %i ps ON ps.source_id=d.source_id AND ps.party_id=d.party_id
		INNER JOIN %i c ON c.id=d.campaign_id WHERE d.id=%d',
		backstage_outreach_party_table('referral_distributions'),
		backstage_outreach_party_table('parties'),
		backstage_outreach_party_table('sources'),
		vms_admission_table_pass_outreach_campaigns(),
		$distribution_id
	), ARRAY_A);
	if (!is_array($row)) {
		return null;
	}
	$row['owner_type'] = 'party';
	$row['distribution_type'] = 'coupon_backed';
	$row['partner_name'] = (string) $row['party_name'];
	$row['batch'] = bvmgr_pass_claims_get_batch_by_id(absint($row['related_batch_id'] ?? 0));
	return $row;
}

function backstage_outreach_party_referral_rows(int $party_id): array
{
	global $wpdb;
	return array_values((array) $wpdb->get_results($wpdb->prepare(
		'SELECT d.*, c.campaign_name, c.status AS campaign_status, c.related_batch_id
		FROM %i d INNER JOIN %i c ON c.id=d.campaign_id WHERE d.party_id=%d ORDER BY d.id DESC',
		backstage_outreach_party_table('referral_distributions'),
		vms_admission_table_pass_outreach_campaigns(),
		$party_id
	), ARRAY_A));
}

function backstage_outreach_party_campaign_results(int $campaign_id): array
{
	static $cache = array();
	$empty = array(
		'is_party_campaign' => false,
		'partners' => 0,
		'distributions' => 0,
		'active_links' => 0,
		'paused_links' => 0,
		'revoked_links' => 0,
		'coupon_links' => 0,
		'accepted_handoffs' => 0,
		'failed_handoffs' => 0,
		'manual_contacts' => 0,
		'paid_redemptions' => 0,
		'refunded_redemptions' => 0,
		'discounted_tickets' => 0,
		'net_revenue' => 0.0,
		'refunded_total' => 0.0,
		'currency' => '',
		'partner_caps' => 0,
	);
	if ($campaign_id <= 0) {
		return $empty;
	}
	if (isset($cache[$campaign_id])) {
		return $cache[$campaign_id];
	}
	global $wpdb;
	$distribution = $wpdb->get_row($wpdb->prepare(
		'SELECT COUNT(*) distributions,COUNT(DISTINCT party_id) partners,
		SUM(CASE WHEN status=%s THEN 1 ELSE 0 END) active_links,
		SUM(CASE WHEN status=%s THEN 1 ELSE 0 END) paused_links,
		SUM(CASE WHEN status=%s THEN 1 ELSE 0 END) revoked_links,
		SUM(CASE WHEN coupon_id IS NOT NULL AND coupon_id>0 THEN 1 ELSE 0 END) coupon_links,
		SUM(CASE WHEN admission_cap>0 THEN 1 ELSE 0 END) partner_caps
		FROM %i WHERE campaign_id=%d',
		'active', 'paused', 'revoked', backstage_outreach_party_table('referral_distributions'), $campaign_id
	), ARRAY_A);
	if (!is_array($distribution) || absint($distribution['distributions'] ?? 0) <= 0) {
		$cache[$campaign_id] = $empty;
		return $cache[$campaign_id];
	}
	$activity = $wpdb->get_row($wpdb->prepare(
		'SELECT
		SUM(CASE WHEN activity_type=%s AND activity_status=%s THEN 1 ELSE 0 END) accepted_handoffs,
		SUM(CASE WHEN activity_type=%s AND activity_status=%s THEN 1 ELSE 0 END) failed_handoffs,
		SUM(CASE WHEN activity_type=%s AND activity_status=%s THEN 1 ELSE 0 END) manual_contacts
		FROM %i WHERE campaign_id=%d',
		'email_handoff', 'handed_off', 'email_handoff', 'failed', 'manual_contact', 'logged', backstage_outreach_party_table('contact_activities'), $campaign_id
	), ARRAY_A);
	$redemption = $wpdb->get_row($wpdb->prepare(
		'SELECT
		SUM(CASE WHEN status IN (%s,%s,%s) THEN 1 ELSE 0 END) paid_redemptions,
		SUM(CASE WHEN status IN (%s,%s) THEN 1 ELSE 0 END) refunded_redemptions,
		SUM(CASE WHEN status IN (%s,%s,%s) THEN ticket_quantity ELSE 0 END) discounted_tickets,
		SUM(CASE WHEN status IN (%s,%s,%s) THEN eligible_ticket_net_total ELSE 0 END) net_revenue,
		SUM(eligible_ticket_refunded_total) refunded_total,
		CASE WHEN COUNT(DISTINCT NULLIF(currency,\'\'))=1 THEN MAX(currency) ELSE \'\' END currency
		FROM %i WHERE campaign_id=%d',
		'paid', 'partially_refunded', 'refunded', 'partially_refunded', 'refunded',
		'paid', 'partially_refunded', 'refunded', 'paid', 'partially_refunded', 'refunded',
		backstage_outreach_party_table('referral_redemptions'), $campaign_id
	), ARRAY_A);
	$cache[$campaign_id] = array_merge($empty, array_map('intval', array_intersect_key($distribution, $empty)), array_map('intval', array_intersect_key((array) $activity, $empty)), array(
		'is_party_campaign' => true,
		'paid_redemptions' => absint($redemption['paid_redemptions'] ?? 0),
		'refunded_redemptions' => absint($redemption['refunded_redemptions'] ?? 0),
		'discounted_tickets' => absint($redemption['discounted_tickets'] ?? 0),
		'net_revenue' => (float) ($redemption['net_revenue'] ?? 0),
		'refunded_total' => (float) ($redemption['refunded_total'] ?? 0),
		'currency' => sanitize_text_field((string) ($redemption['currency'] ?? '')),
	));
	return $cache[$campaign_id];
}

function backstage_outreach_is_party_campaign(int $campaign_id): bool
{
	return !empty(backstage_outreach_party_campaign_results($campaign_id)['is_party_campaign']);
}

function backstage_outreach_party_referral_compatible_campaigns(array $party): array
{
	if ((string) ($party['status'] ?? '') !== 'active') {
		return array();
	}
	$sources = array();
	foreach (backstage_outreach_party_get_sources(absint($party['id'] ?? 0)) as $source) {
		if ((string) ($source['status'] ?? '') === 'active') {
			$sources[absint($source['source_id'] ?? 0)] = true;
		}
	}
	$out = array();
	foreach (vms_pass_outreach_get_campaigns(array('status' => 'active', 'limit' => 500)) as $campaign) {
		$source_id = absint($campaign['related_source_id'] ?? 0);
		$batch = bvmgr_pass_claims_get_batch_by_id(absint($campaign['related_batch_id'] ?? 0));
		if ($source_id <= 0 || empty($sources[$source_id]) || !is_array($batch) || (string) ($batch['status'] ?? '') !== 'active' || absint($batch['source_id'] ?? 0) !== $source_id) {
			continue;
		}
		$configuration = backstage_outreach_discount_offer_configuration($campaign, $batch, 'coupon_backed');
		if (is_wp_error($configuration)) {
			continue;
		}
		$campaign['batch'] = $batch;
		$campaign['configuration'] = $configuration;
		$out[] = $campaign;
	}
	return $out;
}

function backstage_outreach_party_referral_validate(int $party_id, int $campaign_id, int $admission_cap, int $order_cap, string $expires_input)
{
	$party = backstage_outreach_party_get($party_id);
	$campaign = vms_pass_outreach_get_campaign_by_id($campaign_id);
	if (!is_array($party) || (string) $party['status'] !== 'active' || !in_array((string) $party['party_type'], array('person', 'organization'), true)) {
		return new WP_Error('party_referral_party_invalid', __('Choose an active canonical Person or Organization.', 'backstage-outreach'));
	}
	if (!is_array($campaign) || (string) ($campaign['status'] ?? '') !== 'active') {
		return new WP_Error('party_referral_campaign_invalid', __('Choose an active compatible campaign.', 'backstage-outreach'));
	}
	$source_id = absint($campaign['related_source_id'] ?? 0);
	$associated = false;
	foreach (backstage_outreach_party_get_sources($party_id) as $source) {
		if (absint($source['source_id'] ?? 0) === $source_id && (string) ($source['status'] ?? '') === 'active') {
			$associated = true;
			break;
		}
	}
	if (!$associated) {
		return new WP_Error('party_referral_source_mismatch', __('The Party must have an active association with the campaign Source.', 'backstage-outreach'));
	}
	$batch = bvmgr_pass_claims_get_batch_by_id(absint($campaign['related_batch_id'] ?? 0));
	if (!is_array($batch) || (string) ($batch['status'] ?? '') !== 'active' || absint($batch['source_id'] ?? 0) !== $source_id) {
		return new WP_Error('party_referral_batch_invalid', __('The campaign offer batch is unavailable or belongs to another Source.', 'backstage-outreach'));
	}
	$configuration = backstage_outreach_discount_offer_configuration($campaign, $batch, 'coupon_backed');
	if (is_wp_error($configuration)) {
		return $configuration;
	}
	if ($admission_cap > 50000 || $order_cap > 50000) {
		return new WP_Error('party_referral_limit_invalid', __('Partner admission and order limits cannot exceed 50,000.', 'backstage-outreach'));
	}
	$expires_at = function_exists('vms_pass_claims_parse_local_datetime') ? vms_pass_claims_parse_local_datetime($expires_input) : sanitize_text_field($expires_input);
	if ($expires_input !== '' && $expires_at === '') {
		return new WP_Error('party_referral_expiry_invalid', __('Enter a valid expiration in the site timezone or leave it blank.', 'backstage-outreach'));
	}
	return array(
		'party' => $party,
		'campaign' => $campaign,
		'batch' => $batch,
		'configuration' => $configuration,
		'configuration_hash' => backstage_outreach_discount_configuration_digest($configuration),
		'source_id' => $source_id,
		'admission_cap' => max(0, $admission_cap),
		'order_cap' => max(0, $order_cap),
		'expires_at' => $expires_at,
	);
}

function backstage_outreach_party_referral_create(array $review, int $user_id)
{
	global $wpdb;
	$table = backstage_outreach_party_table('referral_distributions');
	$party_id = absint($review['party']['id'] ?? 0);
	$campaign_id = absint($review['campaign']['id'] ?? 0);
	$existing = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE campaign_id=%d AND party_id=%d', $table, $campaign_id, $party_id), ARRAY_A);
	$now = backstage_outreach_party_now();
	$data = array(
		'source_id' => absint($review['source_id'] ?? 0),
		'status' => 'active',
		'admission_cap' => absint($review['admission_cap'] ?? 0),
		'order_cap' => absint($review['order_cap'] ?? 0),
		'eligible_event_ids_json' => backstage_outreach_discount_encode_ids((array) ($review['configuration']['event_ids'] ?? array())),
		'eligible_product_ids_json' => backstage_outreach_discount_encode_ids((array) ($review['configuration']['product_ids'] ?? array())),
		'configuration_hash' => (string) ($review['configuration_hash'] ?? ''),
		'expires_at' => (string) ($review['expires_at'] ?? '') !== '' ? (string) $review['expires_at'] : null,
		'updated_by' => $user_id,
		'updated_at' => $now,
	);
	if (is_array($existing)) {
		if ((string) ($existing['status'] ?? '') === 'revoked') {
			return new WP_Error('party_referral_revoked', __('A revoked Partner link cannot be silently replaced. Use a different campaign.', 'backstage-outreach'));
		}
		if ($wpdb->update($table, $data, array('id' => absint($existing['id']))) === false) {
			return new WP_Error('party_referral_save_failed', __('The Partner link could not be updated.', 'backstage-outreach'));
		}
		$distribution_id = absint($existing['id']);
	} else {
		try {
			$public_key = bin2hex(random_bytes(24));
		} catch (Throwable $error) {
			return new WP_Error('party_referral_key_failed', __('The Partner link key could not be generated.', 'backstage-outreach'));
		}
		$data = array_merge($data, array('campaign_id' => $campaign_id, 'party_id' => $party_id, 'public_key' => $public_key, 'token_hash' => '', 'created_by' => $user_id, 'created_at' => $now));
		if ($wpdb->insert($table, $data) === false || ($distribution_id = absint($wpdb->insert_id)) <= 0) {
			$retry = $wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE campaign_id=%d AND party_id=%d', $table, $campaign_id, $party_id));
			if (absint($retry) <= 0) {
				return new WP_Error('party_referral_save_failed', __('The Partner link could not be created safely.', 'backstage-outreach'));
			}
			$distribution_id = absint($retry);
			if ($wpdb->update($table, array_diff_key($data, array_flip(array('campaign_id', 'party_id', 'public_key', 'token_hash', 'created_by', 'created_at'))), array('id' => $distribution_id)) === false) {
				return new WP_Error('party_referral_save_failed', __('The concurrently created Partner link could not be updated safely.', 'backstage-outreach'));
			}
		} else {
			$token_row = array('id' => $distribution_id, 'campaign_id' => $campaign_id, 'party_id' => $party_id, 'public_key' => $public_key, 'created_at' => $now);
			$wpdb->update($table, array('token_hash' => hash('sha256', backstage_outreach_party_referral_token($token_row))), array('id' => $distribution_id));
		}
	}
	$row = backstage_outreach_party_referral_get_distribution($distribution_id);
	if (!is_array($row)) {
		return new WP_Error('party_referral_reload_failed', __('The Partner link could not be verified after saving.', 'backstage-outreach'));
	}
	$coupon = backstage_outreach_discount_ensure_coupon($row, (array) $review['campaign'], (array) $review['configuration']);
	if (is_wp_error($coupon)) {
		$wpdb->update($table, array('status' => 'paused', 'updated_by' => $user_id, 'updated_at' => $now), array('id' => $distribution_id));
		return $coupon;
	}
	if ($wpdb->update($table, array('coupon_id' => absint($coupon['coupon_id']), 'coupon_code' => (string) $coupon['coupon_code'], 'updated_by' => $user_id, 'updated_at' => $now), array('id' => $distribution_id)) === false) {
		backstage_outreach_discount_set_coupon_status(absint($coupon['coupon_id']), 'draft', $distribution_id, 'party');
		return new WP_Error('party_referral_coupon_link_failed', __('The managed coupon could not be linked safely.', 'backstage-outreach'));
	}
	return backstage_outreach_party_referral_get_distribution($distribution_id);
}

function backstage_outreach_party_referral_handle_review(): void
{
	$user_id = backstage_outreach_party_require_admin('backstage_outreach_party_referral_review');
	$party_id = absint($_POST['party_id'] ?? 0);
	$validated = backstage_outreach_party_referral_validate($party_id, absint($_POST['campaign_id'] ?? 0), absint($_POST['admission_cap'] ?? 0), absint($_POST['order_cap'] ?? 0), sanitize_text_field((string) wp_unslash($_POST['expires_at'] ?? '')));
	if (is_wp_error($validated)) {
		delete_transient(backstage_outreach_party_referral_review_key($party_id));
		backstage_outreach_party_admin_notice($validated->get_error_message(), 'error');
		backstage_outreach_party_admin_redirect(array('view' => 'edit', 'party_id' => $party_id), 'outreach-party-referral');
	}
	$validated['review_token'] = wp_generate_uuid4();
	$validated['reviewed_at'] = time();
	$validated['reviewed_by'] = $user_id;
	set_transient(backstage_outreach_party_referral_review_key($party_id), $validated, 20 * MINUTE_IN_SECONDS);
	backstage_outreach_party_admin_notice(__('Partner Admission Offer reviewed. Confirm generation below.', 'backstage-outreach'));
	backstage_outreach_party_admin_redirect(array('view' => 'edit', 'party_id' => $party_id), 'outreach-party-referral-review');
}
add_action('admin_post_backstage_outreach_party_referral_review', 'backstage_outreach_party_referral_handle_review');

function backstage_outreach_party_referral_handle_create(): void
{
	$user_id = backstage_outreach_party_require_admin('backstage_outreach_party_referral_create');
	$party_id = absint($_POST['party_id'] ?? 0);
	$review = get_transient(backstage_outreach_party_referral_review_key($party_id));
	$review_token = sanitize_text_field((string) wp_unslash($_POST['review_token'] ?? ''));
	if (!is_array($review) || empty($review['review_token']) || !hash_equals((string) $review['review_token'], $review_token) || absint($review['reviewed_by'] ?? 0) !== $user_id) {
		backstage_outreach_party_admin_notice(__('The Partner offer review expired or was already used. Review it again.', 'backstage-outreach'), 'error');
		backstage_outreach_party_admin_redirect(array('view' => 'edit', 'party_id' => $party_id), 'outreach-party-referral');
	}
	$current = backstage_outreach_party_referral_validate($party_id, absint($review['campaign']['id'] ?? 0), absint($review['admission_cap'] ?? 0), absint($review['order_cap'] ?? 0), (string) ($review['expires_at'] ?? ''));
	if (is_wp_error($current) || !hash_equals((string) ($review['configuration_hash'] ?? ''), is_array($current) ? (string) ($current['configuration_hash'] ?? '') : '')) {
		delete_transient(backstage_outreach_party_referral_review_key($party_id));
		backstage_outreach_party_admin_notice(is_wp_error($current) ? $current->get_error_message() : __('The campaign offer changed after review. Review it again.', 'backstage-outreach'), 'error');
		backstage_outreach_party_admin_redirect(array('view' => 'edit', 'party_id' => $party_id), 'outreach-party-referral');
	}
	$result = backstage_outreach_party_referral_create($current, $user_id);
	delete_transient(backstage_outreach_party_referral_review_key($party_id));
	backstage_outreach_party_admin_notice(is_wp_error($result) ? $result->get_error_message() : __('Reusable paid Partner link created.', 'backstage-outreach'), is_wp_error($result) ? 'error' : 'success');
	backstage_outreach_party_admin_redirect(array('view' => 'edit', 'party_id' => $party_id), 'outreach-party-referral');
}
add_action('admin_post_backstage_outreach_party_referral_create', 'backstage_outreach_party_referral_handle_create');

function backstage_outreach_party_referral_handle_status(): void
{
	$user_id = backstage_outreach_party_require_admin('backstage_outreach_party_referral_status');
	$party_id = absint($_POST['party_id'] ?? 0);
	$distribution_id = absint($_POST['distribution_id'] ?? 0);
	$status = sanitize_key((string) wp_unslash($_POST['status'] ?? ''));
	$row = backstage_outreach_party_referral_get_distribution($distribution_id);
	if (!is_array($row) || absint($row['party_id'] ?? 0) !== $party_id || !in_array($status, array('active', 'paused', 'revoked'), true) || (string) ($row['status'] ?? '') === 'revoked') {
		backstage_outreach_party_admin_notice(__('The Partner link status change was rejected.', 'backstage-outreach'), 'error');
		backstage_outreach_party_admin_redirect(array('view' => 'edit', 'party_id' => $party_id), 'outreach-party-referral');
	}
	$ok = backstage_outreach_discount_set_coupon_status(absint($row['coupon_id'] ?? 0), $status === 'active' ? 'publish' : 'draft', $distribution_id, 'party');
	if ($ok) {
		global $wpdb;
		$ok = $wpdb->update(backstage_outreach_party_table('referral_distributions'), array('status' => $status, 'updated_by' => $user_id, 'updated_at' => backstage_outreach_party_now()), array('id' => $distribution_id)) !== false;
	}
	backstage_outreach_party_admin_notice($ok ? __('Partner link status updated.', 'backstage-outreach') : __('The Partner link status could not be updated safely.', 'backstage-outreach'), $ok ? 'success' : 'error');
	backstage_outreach_party_admin_redirect(array('view' => 'edit', 'party_id' => $party_id), 'outreach-party-referral');
}
add_action('admin_post_backstage_outreach_party_referral_status', 'backstage_outreach_party_referral_handle_status');

function backstage_outreach_party_referral_render_panel(array $party): void
{
	$party_id = absint($party['id'] ?? 0);
	$campaigns = backstage_outreach_party_referral_compatible_campaigns($party);
	$review = get_transient(backstage_outreach_party_referral_review_key($party_id));
	$rows = backstage_outreach_party_referral_rows($party_id);
	echo '<section id="outreach-party-referral" class="vms-pass-form-section vms-pass-span-2"><h3>' . esc_html__('Reusable paid Partner Admission Offers', 'backstage-outreach') . '</h3><p class="description">' . esc_html__('Review and generate one signed paid referral link for this canonical Person or Organization. This does not create a business, recipient, Guest Pass token, email, or message.', 'backstage-outreach') . '</p>';
	if ((string) ($party['status'] ?? '') !== 'active') {
		echo '<p class="vms-outreach-party-warning">' . esc_html__('Activate this Party before creating a referral link.', 'backstage-outreach') . '</p></section>';
		return;
	}
	if (!$campaigns) {
		echo '<p class="description">' . esc_html__('No active Percentage Off or Fixed Amount Off campaign currently matches this Party’s active Source associations.', 'backstage-outreach') . '</p>';
	} else {
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-pass-form"><input type="hidden" name="action" value="backstage_outreach_party_referral_review"><input type="hidden" name="party_id" value="' . esc_attr((string) $party_id) . '">';
		wp_nonce_field('backstage_outreach_party_referral_review');
		echo '<div class="vms-pass-grid"><label class="vms-pass-span-2">' . esc_html__('Compatible campaign', 'backstage-outreach') . '<select name="campaign_id" required><option value="">' . esc_html__('Choose a reviewed paid campaign', 'backstage-outreach') . '</option>';
		foreach ($campaigns as $campaign) {
			echo '<option value="' . esc_attr((string) absint($campaign['id'])) . '">' . esc_html((string) $campaign['campaign_name'] . ' — ' . backstage_outreach_discount_terms_label((array) $campaign['configuration'], false)) . '</option>';
		}
		echo '</select></label><label>' . esc_html__('Maximum discounted admissions through this Partner', 'backstage-outreach') . '<input type="number" min="0" max="50000" name="admission_cap" value="0"><span class="description">' . esc_html__('0 means no separate Partner limit; shared campaign and batch capacity still apply.', 'backstage-outreach') . '</span></label><label>' . esc_html__('Optional paid-order cap', 'backstage-outreach') . '<input type="number" min="0" max="50000" name="order_cap" value="0"><span class="description">' . esc_html__('0 means unlimited orders subject to admission limits.', 'backstage-outreach') . '</span></label><label class="vms-pass-span-2">' . esc_html__('Optional link expiration', 'backstage-outreach') . '<input type="datetime-local" name="expires_at"><span class="description">' . esc_html__('Interpreted in the WordPress site timezone.', 'backstage-outreach') . '</span></label></div><p class="vms-pass-actions"><button type="submit" class="button">' . esc_html__('Review Partner offer', 'backstage-outreach') . '</button></p></form>';
	}
	if (is_array($review)) {
		$configuration = (array) ($review['configuration'] ?? array());
		echo '<div id="outreach-party-referral-review" class="vms-pass-review-card"><h4>' . esc_html__('Reviewed Partner offer', 'backstage-outreach') . '</h4><dl><div><dt>' . esc_html__('Party', 'backstage-outreach') . '</dt><dd>' . esc_html((string) ($review['party']['display_name'] ?? '')) . ' — ' . esc_html((string) ($review['party']['party_type'] ?? '')) . '</dd></div><div><dt>' . esc_html__('Campaign', 'backstage-outreach') . '</dt><dd>' . esc_html((string) ($review['campaign']['campaign_name'] ?? '')) . '</dd></div><div><dt>' . esc_html__('Offer', 'backstage-outreach') . '</dt><dd>' . esc_html(backstage_outreach_discount_terms_label($configuration)) . '</dd></div><div><dt>' . esc_html__('Admissions per customer', 'backstage-outreach') . '</dt><dd>' . esc_html((string) absint($configuration['per_order_ticket_cap'] ?? 0)) . '</dd></div><div><dt>' . esc_html__('Partner admission cap', 'backstage-outreach') . '</dt><dd>' . esc_html(absint($review['admission_cap'] ?? 0) > 0 ? (string) absint($review['admission_cap']) : __('No separate limit', 'backstage-outreach')) . '</dd></div><div><dt>' . esc_html__('Paid-order cap', 'backstage-outreach') . '</dt><dd>' . esc_html(absint($review['order_cap'] ?? 0) > 0 ? (string) absint($review['order_cap']) : __('No separate limit', 'backstage-outreach')) . '</dd></div><div><dt>' . esc_html__('Expiration', 'backstage-outreach') . '</dt><dd>' . esc_html((string) ($review['expires_at'] ?? '') !== '' ? backstage_outreach_business_format_local_datetime((string) $review['expires_at']) : __('None', 'backstage-outreach')) . '</dd></div></dl><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="backstage_outreach_party_referral_create"><input type="hidden" name="party_id" value="' . esc_attr((string) $party_id) . '"><input type="hidden" name="review_token" value="' . esc_attr((string) ($review['review_token'] ?? '')) . '">';
		wp_nonce_field('backstage_outreach_party_referral_create');
		echo '<p class="vms-pass-actions"><button type="submit" class="button button-primary">' . esc_html__('Generate reviewed Partner link', 'backstage-outreach') . '</button></p></form></div>';
	}
	if ($rows) {
		echo '<h4>' . esc_html__('Existing Partner links', 'backstage-outreach') . '</h4><div class="vms-pass-table-scroll"><table class="widefat striped"><thead><tr><th>' . esc_html__('Campaign', 'backstage-outreach') . '</th><th>' . esc_html__('Status', 'backstage-outreach') . '</th><th>' . esc_html__('Link', 'backstage-outreach') . '</th><th>' . esc_html__('Limits', 'backstage-outreach') . '</th><th>' . esc_html__('Manage', 'backstage-outreach') . '</th></tr></thead><tbody>';
		foreach ($rows as $row) {
			$url = backstage_outreach_party_referral_url($row);
			echo '<tr><td>' . esc_html((string) $row['campaign_name']) . '</td><td>' . esc_html((string) $row['status']) . '</td><td><input class="regular-text" readonly value="' . esc_attr($url) . '"></td><td>' . esc_html(sprintf(__('Admissions: %1$s; orders: %2$s', 'backstage-outreach'), absint($row['admission_cap']) > 0 ? (string) absint($row['admission_cap']) : __('shared only', 'backstage-outreach'), absint($row['order_cap']) > 0 ? (string) absint($row['order_cap']) : __('unlimited', 'backstage-outreach'))) . '</td><td>';
			if ((string) $row['status'] !== 'revoked') {
				$next = (string) $row['status'] === 'active' ? 'paused' : 'active';
				echo backstage_outreach_party_inline_post_button('backstage_outreach_party_referral_status', $next === 'active' ? __('Resume', 'backstage-outreach') : __('Pause', 'backstage-outreach'), array('party_id' => $party_id, 'distribution_id' => absint($row['id']), 'status' => $next), 'backstage_outreach_party_referral_status');
				echo backstage_outreach_party_inline_post_button('backstage_outreach_party_referral_status', __('Revoke', 'backstage-outreach'), array('party_id' => $party_id, 'distribution_id' => absint($row['id']), 'status' => 'revoked'), 'backstage_outreach_party_referral_status');
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
	echo '</section>';
}

function backstage_outreach_party_referral_register_route(): void
{
	add_rewrite_rule('^admission-offer/partner/([^/]+)/?$', 'index.php?backstage_outreach_party_referral=$matches[1]', 'top');
	add_rewrite_tag('%backstage_outreach_party_referral%', '([^&]+)');
	if (get_option('backstage_outreach_party_referral_rewrite_version') !== BACKSTAGE_OUTREACH_VERSION) {
		flush_rewrite_rules(false);
		update_option('backstage_outreach_party_referral_rewrite_version', BACKSTAGE_OUTREACH_VERSION, false);
	}
}
add_action('init', 'backstage_outreach_party_referral_register_route', 20);

function backstage_outreach_party_referral_context(string $token)
{
	$token = rawurldecode(sanitize_text_field($token));
	$parts = explode('.', $token, 2);
	if (count($parts) !== 2 || sanitize_key($parts[0]) === '' || !preg_match('/^[a-f0-9]{64}$/', strtolower($parts[1]))) {
		return new WP_Error('party_referral_token_invalid', __('This Partner Admission Offer link is invalid.', 'backstage-outreach'));
	}
	global $wpdb;
	$row = $wpdb->get_row($wpdb->prepare('SELECT id FROM %i WHERE public_key=%s', backstage_outreach_party_table('referral_distributions'), sanitize_key($parts[0])), ARRAY_A);
	$distribution = is_array($row) ? backstage_outreach_party_referral_get_distribution(absint($row['id'])) : null;
	if (!is_array($distribution)
		|| !hash_equals(backstage_outreach_party_referral_signature($distribution), strtolower($parts[1]))
		|| !hash_equals((string) ($distribution['token_hash'] ?? ''), hash('sha256', $token))) {
		return new WP_Error('party_referral_token_invalid', __('This Partner Admission Offer link is invalid.', 'backstage-outreach'));
	}
	return $distribution;
}

function backstage_outreach_party_referral_router(): void
{
	if (is_admin()) {
		return;
	}
	$token = sanitize_text_field((string) get_query_var('backstage_outreach_party_referral'));
	if ($token === '') {
		return;
	}
	$distribution = backstage_outreach_party_referral_context($token);
	if (is_wp_error($distribution)) {
		backstage_outreach_render_public_offer_status(__('Partner Offer Unavailable', 'backstage-outreach'), $distribution->get_error_message(), 404);
	}
	$error = backstage_outreach_discount_distribution_error($distribution);
	if (is_wp_error($error)) {
		backstage_outreach_render_public_offer_status(__('Partner Offer Unavailable', 'backstage-outreach'), $error->get_error_message(), 410);
	}
	backstage_outreach_discount_offer_router($distribution, $token);
}
add_action('template_redirect', 'backstage_outreach_party_referral_router', 4);
