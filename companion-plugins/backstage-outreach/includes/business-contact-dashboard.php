<?php
/**
 * Campaign-specific reusable-business contact history and compact dashboard.
 */

defined('ABSPATH') || exit;

function backstage_outreach_business_contact_event_label(array $event): string
{
	$outcome = sanitize_key((string) ($event['outcome'] ?? ''));
	if ($outcome === 'email_handed_off') {
		return __('Email handed off', 'backstage-outreach');
	}
	if ($outcome === 'email_failed') {
		return __('Email handoff failed', 'backstage-outreach');
	}
	if ($outcome === 'email_skipped') {
		return __('Email handoff skipped', 'backstage-outreach');
	}
	$methods = backstage_outreach_business_contact_methods();
	$outcomes = backstage_outreach_business_contact_outcomes();
	$method = sanitize_key((string) ($event['method'] ?? 'other'));
	return sprintf('%1$s — %2$s', (string) ($methods[$method] ?? __('Other', 'backstage-outreach')), (string) ($outcomes[$outcome] ?? __('Other', 'backstage-outreach')));
}

function backstage_outreach_business_contact_status(array $state): array
{
	if (!empty($state['follow_up_needed'])) {
		return array('follow_up', __('Follow-up needed', 'backstage-outreach'), 0);
	}
	if (!empty($state['no_contact'])) {
		return array(
			'no_contact',
			!empty($state['email_failed']) ? __('Email failed — no completed contact', 'backstage-outreach') : __('No contact recorded', 'backstage-outreach'),
			1,
		);
	}
	if (!empty($state['email_handed_off']) && !empty($state['manually_contacted'])) {
		return array('email_handed_off', __('Email handed off + manual activity', 'backstage-outreach'), 3);
	}
	if (!empty($state['email_handed_off'])) {
		return array('email_handed_off', __('Email handed off', 'backstage-outreach'), 3);
	}
	return array('manual', __('Manual contact logged', 'backstage-outreach'), 3);
}

function backstage_outreach_render_business_contact_summary(array $summary): void
{
	$items = array(
		__('Total businesses', 'backstage-outreach') => absint($summary['total_businesses'] ?? 0),
		__('Email handed off', 'backstage-outreach') => absint($summary['email_handed_off'] ?? 0),
		__('Manual contacts logged', 'backstage-outreach') => absint($summary['manual_contacts'] ?? 0),
		__('No contact activity recorded', 'backstage-outreach') => absint($summary['no_contact'] ?? 0),
		__('Follow-ups needed', 'backstage-outreach') => absint($summary['follow_up_needed'] ?? 0),
	);
	echo '<div class="vms-pass-contact-summary" aria-label="' . esc_attr__('Business contact totals', 'backstage-outreach') . '">';
	foreach ($items as $label => $value) {
		echo '<div><strong>' . esc_html((string) $value) . '</strong><span>' . esc_html($label) . '</span></div>';
	}
	echo '</div>';
}

function backstage_outreach_render_business_contact_history(array $history): void
{
	if (empty($history)) {
		echo '<p class="description">' . esc_html__('No contact activity recorded.', 'backstage-outreach') . '</p>';
		return;
	}
	echo '<ol class="vms-pass-contact-history">';
	foreach ($history as $event) {
		$user = absint($event['operator_user_id'] ?? 0) > 0 ? get_userdata(absint($event['operator_user_id'])) : null;
		$operator = $user instanceof WP_User ? $user->display_name : __('System / unknown operator', 'backstage-outreach');
		echo '<li><strong>' . esc_html(backstage_outreach_business_contact_event_label($event)) . '</strong><span>' . esc_html(backstage_outreach_business_format_local_datetime((string) ($event['activity_at'] ?? '')) . ' · ' . $operator) . '</span>' . (!empty($event['notes']) ? '<p>' . nl2br(esc_html((string) $event['notes'])) . '</p>' : '') . '</li>';
	}
	echo '</ol>';
}

function backstage_outreach_render_business_contact_log_form(int $campaign_id, int $distribution_id): void
{
	echo '<h4>' . esc_html__('Log Contact', 'backstage-outreach') . '</h4><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-pass-contact-log-form"><input type="hidden" name="action" value="backstage_outreach_business_contact_log"><input type="hidden" name="campaign_id" value="' . esc_attr((string) $campaign_id) . '"><input type="hidden" name="distribution_id" value="' . esc_attr((string) $distribution_id) . '"><input type="hidden" name="activity_request_id" value="' . esc_attr(wp_generate_uuid4()) . '">';
	wp_nonce_field('backstage_outreach_business_contact_log_' . $campaign_id . '_' . $distribution_id);
	echo '<label>' . esc_html__('Method', 'backstage-outreach') . '<select name="contact_method" required><option value="">' . esc_html__('Choose method', 'backstage-outreach') . '</option>';
	foreach (backstage_outreach_business_contact_methods() as $key => $label) {
		echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>';
	}
	echo '</select></label><label>' . esc_html__('Result', 'backstage-outreach') . '<select name="contact_outcome" required><option value="">' . esc_html__('Choose result', 'backstage-outreach') . '</option>';
	foreach (backstage_outreach_business_contact_outcomes() as $key => $label) {
		echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>';
	}
	echo '</select></label><label>' . esc_html__('Date and time (site timezone)', 'backstage-outreach') . '<input type="datetime-local" name="activity_at" value="' . esc_attr(wp_date('Y-m-d\TH:i', time(), wp_timezone())) . '" required></label><label class="vms-pass-span-2">' . esc_html__('Notes (optional)', 'backstage-outreach') . '<textarea name="contact_notes" rows="2"></textarea></label><p><button class="button">' . esc_html__('Save Contact Activity', 'backstage-outreach') . '</button></p></form>';
}

function backstage_outreach_render_business_contact_card(array $row, array $state, array $campaign, array $batch, ?array $share_review, string $subject_template, string $message_template): bool
{
	$campaign_id = absint($campaign['id'] ?? 0);
	$distribution_id = absint($row['id'] ?? 0);
	$email = sanitize_email((string) ($row['email'] ?? ''));
	$needs_first_email = $email !== '' && empty($state['email_handed_off']);
	$block_reason = backstage_outreach_business_first_email_block_reason($row, $campaign, !empty($state['email_handed_off']));
	$sendable = $block_reason === '';
	list($status_key, $status_label, $attention_rank) = backstage_outreach_business_contact_status($state);
	$history = (array) ($state['history'] ?? array());
	$latest = is_array($state['latest'] ?? null) ? (array) $state['latest'] : array();
	$latest_at = sanitize_text_field((string) ($latest['activity_at'] ?? ''));
	$latest_label = !empty($latest) ? backstage_outreach_business_contact_event_label($latest) : __('No activity', 'backstage-outreach');
	$context = backstage_outreach_business_share_context($row, $campaign, $batch, $subject_template, $message_template);
	$search = strtolower(implode(' ', array_filter(array((string) ($row['business_name'] ?? ''), (string) ($row['contact_name'] ?? ''), $email, (string) ($row['phone'] ?? ''), (string) ($row['website'] ?? '')))));
	$subject_id = 'backstage-business-share-subject-' . $distribution_id;
	$message_id = 'backstage-business-share-message-' . $distribution_id;
	$link_id = 'backstage-business-contact-link-' . $distribution_id;
	$flyer_id = 'backstage-business-contact-flyer-' . $distribution_id;

	echo '<details id="backstage-outreach-business-contact-' . esc_attr((string) $distribution_id) . '" class="vms-pass-contact-card" data-contact-card data-contact-status="' . esc_attr($status_key) . '" data-contact-email="' . esc_attr(!empty($state['email_handed_off']) ? '1' : '0') . '" data-contact-needs-first-email="' . esc_attr($needs_first_email ? '1' : '0') . '" data-contact-manual="' . esc_attr(!empty($state['manually_contacted']) ? '1' : '0') . '" data-contact-no-contact="' . esc_attr(!empty($state['no_contact']) ? '1' : '0') . '" data-contact-follow-up="' . esc_attr(!empty($state['follow_up_needed']) ? '1' : '0') . '" data-contact-rank="' . esc_attr((string) $attention_rank) . '" data-contact-name="' . esc_attr(strtolower((string) ($row['business_name'] ?? ''))) . '" data-contact-search="' . esc_attr($search) . '" data-contact-time="' . esc_attr($latest_at) . '"><summary><span class="vms-pass-contact-card__business"><strong>' . esc_html((string) ($row['business_name'] ?? '')) . '</strong><span>' . esc_html((string) ($row['contact_name'] ?? '') !== '' ? (string) $row['contact_name'] : __('Contact name not provided', 'backstage-outreach')) . '</span></span><span class="vms-pass-contact-card__status"><strong>' . esc_html($status_label) . '</strong><span>' . esc_html($latest_label . ($latest_at !== '' ? ' · ' . backstage_outreach_business_format_local_datetime($latest_at) : '')) . '</span></span><span class="vms-pass-contact-card__contact">' . esc_html($email !== '' ? $email : __('No email', 'backstage-outreach')) . '<span>' . esc_html((string) ($row['phone'] ?? '') !== '' ? (string) $row['phone'] : ((string) ($row['website'] ?? '') !== '' ? (string) $row['website'] : __('No phone or website', 'backstage-outreach'))) . '</span></span>';
	echo '<span class="vms-pass-contact-card__select">';
	if ($sendable) {
		echo '<label><input type="checkbox" name="distribution_ids[]" value="' . esc_attr((string) $distribution_id) . '" form="backstage-outreach-business-email-form-' . esc_attr((string) $campaign_id) . '" data-vms-email-eligible> ' . esc_html__('Email', 'backstage-outreach') . '</label>';
	} else {
		$blocked_labels = array(
			'already_handed_off' => __('Previously handed off', 'backstage-outreach'),
			'missing_email' => __('No email', 'backstage-outreach'),
			'suppressed' => __('Suppressed', 'backstage-outreach'),
			'expired' => __('Expired', 'backstage-outreach'),
			'campaign_inactive' => __('Campaign inactive', 'backstage-outreach'),
		);
		echo '<span>' . esc_html((string) ($blocked_labels[$block_reason] ?? __('Unavailable', 'backstage-outreach'))) . '</span>';
	}
	echo '</span>';
	echo '</summary><div class="vms-pass-contact-card__details"><section><h4>' . esc_html__('Personalized message', 'backstage-outreach') . '</h4><label class="screen-reader-text" for="' . esc_attr($subject_id) . '">' . esc_html__('Personalized subject', 'backstage-outreach') . '</label><input id="' . esc_attr($subject_id) . '" class="regular-text" readonly value="' . esc_attr((string) $context['subject']) . '"><button type="button" class="button button-small" data-backstage-copy data-backstage-copy-target="' . esc_attr($subject_id) . '">' . esc_html__('Copy subject', 'backstage-outreach') . '</button><label class="screen-reader-text" for="' . esc_attr($message_id) . '">' . esc_html__('Personalized message', 'backstage-outreach') . '</label><textarea id="' . esc_attr($message_id) . '" rows="10" readonly>' . esc_textarea((string) $context['message']) . '</textarea><button type="button" class="button button-small" data-backstage-copy data-backstage-copy-target="' . esc_attr($message_id) . '">' . esc_html__('Copy message', 'backstage-outreach') . '</button></section>';
	echo '<section><h4>' . esc_html__('Links & contact information', 'backstage-outreach') . '</h4><label>' . esc_html__('Customer offer URL', 'backstage-outreach') . '<input id="' . esc_attr($link_id) . '" readonly value="' . esc_attr((string) $context['customer_url']) . '"></label><button type="button" class="button button-small" data-backstage-copy data-backstage-copy-target="' . esc_attr($link_id) . '">' . esc_html__('Copy offer link', 'backstage-outreach') . '</button> <a class="button button-small" href="' . esc_url((string) $context['customer_url']) . '" target="_blank" rel="noopener">' . esc_html__('Open offer', 'backstage-outreach') . '</a><label>' . esc_html__('Printable flyer URL', 'backstage-outreach') . '<input id="' . esc_attr($flyer_id) . '" readonly value="' . esc_attr((string) $context['flyer_url']) . '"></label><button type="button" class="button button-small" data-backstage-copy data-backstage-copy-target="' . esc_attr($flyer_id) . '">' . esc_html__('Copy flyer link', 'backstage-outreach') . '</button> <a class="button button-small" href="' . esc_url((string) $context['flyer_url']) . '" target="_blank" rel="noopener">' . esc_html__('Open flyer', 'backstage-outreach') . '</a><dl class="vms-pass-contact-details"><dt>' . esc_html__('Email', 'backstage-outreach') . '</dt><dd>' . esc_html($email !== '' ? $email : __('Not provided', 'backstage-outreach')) . '</dd><dt>' . esc_html__('Phone', 'backstage-outreach') . '</dt><dd>' . esc_html((string) ($row['phone'] ?? '') !== '' ? (string) $row['phone'] : __('Not provided', 'backstage-outreach')) . '</dd>';
	foreach (array('website' => __('Website', 'backstage-outreach'), 'facebook_url' => __('Facebook', 'backstage-outreach'), 'instagram_url' => __('Instagram', 'backstage-outreach')) as $field => $label) {
		$value = esc_url_raw((string) ($row[$field] ?? ''));
		if ($value !== '') {
			echo '<dt>' . esc_html($label) . '</dt><dd><a href="' . esc_url($value) . '" target="_blank" rel="noopener">' . esc_html($value) . '</a></dd>';
		}
	}
	echo '</dl></section><section><h4>' . esc_html__('Contact history', 'backstage-outreach') . '</h4>';
	backstage_outreach_render_business_contact_history($history);
	backstage_outreach_render_business_contact_log_form($campaign_id, $distribution_id);
	echo '</section></div></details>';
	return $sendable;
}

function backstage_outreach_render_business_email_review(array $campaign, ?array $share_review, string $mode): void
{
	if (!is_array($share_review) || (string) ($share_review['mode'] ?? '') !== $mode) {
		return;
	}
	$campaign_id = absint($campaign['id'] ?? 0);
	$is_resend = $mode === 'resend';
	$reviewed_ids = array_values(array_unique(array_map('absint', (array) ($share_review['distribution_ids'] ?? array()))));
	$snapshots = is_array($share_review['recipient_snapshots'] ?? null) ? (array) $share_review['recipient_snapshots'] : array();
	$id = $is_resend ? 'backstage-outreach-business-resend-review' : 'backstage-outreach-business-email-review';
	echo '<div id="' . esc_attr($id) . '" class="vms-pass-preview-summary vms-pass-email-review" tabindex="-1"><h4>' . esc_html($is_resend ? __('Confirm deliberate resend', 'backstage-outreach') : __('Review selected first-time emails', 'backstage-outreach')) . '</h4><p>' . esc_html($is_resend ? __('These businesses already have a successful mail-system handoff. Review the exact current addresses and content, then explicitly confirm another handoff.', 'backstage-outreach') : __('Review the exact current addresses and personalized plain-text content. Nothing is sent until the final handoff button is used.', 'backstage-outreach')) . '</p><div class="vms-pass-email-review-list">';
	foreach ($reviewed_ids as $reviewed_id) {
		$snapshot = is_array($snapshots[$reviewed_id] ?? null) ? (array) $snapshots[$reviewed_id] : array();
		echo '<details class="vms-pass-email-review-row"><summary><strong>' . esc_html((string) ($snapshot['business_name'] ?? '')) . '</strong><span>' . esc_html((string) ($snapshot['email'] ?? '')) . '</span></summary><div><p><strong>' . esc_html__('Subject:', 'backstage-outreach') . '</strong> ' . esc_html((string) ($snapshot['subject'] ?? '')) . '</p><label>' . esc_html__('Personalized plain-text message', 'backstage-outreach') . '<textarea rows="10" readonly>' . esc_textarea((string) ($snapshot['message'] ?? '')) . '</textarea></label></div></details>';
	}
	echo '</div><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" data-vms-email-handoff-form data-vms-handoff-count="' . esc_attr((string) count($reviewed_ids)) . '" data-vms-handoff-mode="' . esc_attr($is_resend ? 'resend' : 'first') . '"><input type="hidden" name="action" value="backstage_outreach_business_share"><input type="hidden" name="campaign_id" value="' . esc_attr((string) $campaign_id) . '"><input type="hidden" name="share_mode" value="' . esc_attr($is_resend ? 'resend_send' : 'send') . '"><input type="hidden" name="share_review_token" value="' . esc_attr((string) ($share_review['token'] ?? '')) . '">';
	foreach ($reviewed_ids as $reviewed_id) {
		echo '<input type="hidden" name="distribution_ids[]" value="' . esc_attr((string) $reviewed_id) . '">';
	}
	wp_nonce_field('backstage_outreach_business_share');
	if ($is_resend) {
		echo '<label><input type="checkbox" name="confirm_resend" value="1" required> ' . esc_html__('I confirm that these businesses already have recorded handoffs and should receive another mail-system handoff.', 'backstage-outreach') . '</label>';
	}
	echo '<p><button type="submit" class="button button-primary" data-vms-handoff-submit>' . esc_html($is_resend ? __('Confirm Resend', 'backstage-outreach') : __('Hand Off Reviewed First-Time Emails', 'backstage-outreach')) . '</button></p><p class="vms-pass-handoff-progress" data-vms-handoff-progress role="status" aria-live="assertive" hidden><span class="spinner is-active" aria-hidden="true"></span><span data-vms-handoff-progress-text></span></p></form></div>';
}

function backstage_outreach_render_business_resend_controls(array $campaign, array $rows, array $sent_map, ?array $share_review): void
{
	$campaign_id = absint($campaign['id'] ?? 0);
	if (!empty($sent_map)) {
		echo '<details class="vms-pass-resend" data-vms-resend-panel><summary>' . esc_html__('Resend Previous Invitations', 'backstage-outreach') . '</summary><p>' . esc_html__('Resends are separate from first-time handoff. Select prior recipients, review the exact current addresses and invitation content, then explicitly confirm another handoff.', 'backstage-outreach') . '</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="backstage_outreach_business_share"><input type="hidden" name="campaign_id" value="' . esc_attr((string) $campaign_id) . '"><input type="hidden" name="share_mode" value="resend_preview">';
		wp_nonce_field('backstage_outreach_business_share');
		echo '<div class="vms-pass-resend-tools"><label>' . esc_html__('Search previous recipients', 'backstage-outreach') . '<input type="search" data-vms-resend-search placeholder="' . esc_attr__('Business or email', 'backstage-outreach') . '"></label><strong data-vms-resend-selected-count>' . esc_html__('0 recipients selected', 'backstage-outreach') . '</strong></div><div class="vms-pass-table-scroll"><table class="widefat striped vms-pass-resend-table"><thead><tr><th scope="col">' . esc_html__('Select', 'backstage-outreach') . '</th><th scope="col">' . esc_html__('Business', 'backstage-outreach') . '</th><th scope="col">' . esc_html__('Current email', 'backstage-outreach') . '</th><th scope="col">' . esc_html__('Most recent handoff', 'backstage-outreach') . '</th><th scope="col">' . esc_html__('Eligibility', 'backstage-outreach') . '</th></tr></thead><tbody>';
		foreach ($rows as $row) {
			$id = absint($row['id'] ?? 0);
			if (!isset($sent_map[$id])) {
				continue;
			}
			$email = sanitize_email((string) ($row['email'] ?? ''));
			$expiry = backstage_outreach_distribution_effective_expiry($row);
			$expired = $expiry !== '' && backstage_outreach_business_now() > $expiry;
			$suppressed = $email !== '' && function_exists('vms_outreach_email_is_suppressed') && vms_outreach_email_is_suppressed($email);
			$eligible = $email !== '' && !$expired && !$suppressed && sanitize_key((string) ($campaign['status'] ?? '')) === 'active';
			$status = $eligible ? __('Eligible for reviewed resend', 'backstage-outreach') : ($email === '' ? __('No current email', 'backstage-outreach') : ($suppressed ? __('Suppressed', 'backstage-outreach') : ($expired ? __('Expired', 'backstage-outreach') : __('Campaign inactive', 'backstage-outreach'))));
			$search = strtolower((string) ($row['business_name'] ?? '') . ' ' . $email);
			echo '<tr data-vms-resend-row data-vms-resend-search-value="' . esc_attr($search) . '"><td data-label="' . esc_attr__('Select', 'backstage-outreach') . '"><input type="checkbox" name="distribution_ids[]" value="' . esc_attr((string) $id) . '" data-vms-resend-select' . disabled(!$eligible, true, false) . ' aria-label="' . esc_attr(sprintf(__('Resend to %s', 'backstage-outreach'), (string) ($row['business_name'] ?? ''))) . '"></td><td data-label="' . esc_attr__('Business', 'backstage-outreach') . '"><strong>' . esc_html((string) ($row['business_name'] ?? '')) . '</strong></td><td data-label="' . esc_attr__('Current email', 'backstage-outreach') . '">' . esc_html($email !== '' ? $email : __('Not provided', 'backstage-outreach')) . '</td><td data-label="' . esc_attr__('Most recent handoff', 'backstage-outreach') . '">' . esc_html(backstage_outreach_business_format_local_datetime((string) $sent_map[$id])) . '</td><td data-label="' . esc_attr__('Eligibility', 'backstage-outreach') . '">' . esc_html($status) . '</td></tr>';
		}
		echo '</tbody></table></div><p><button class="button" data-vms-resend-review disabled>' . esc_html__('Review Selected Resends', 'backstage-outreach') . '</button></p></form></details>';
	}
	backstage_outreach_render_business_email_review($campaign, $share_review, 'resend');
}

function backstage_outreach_render_business_contact_dashboard(array $campaign, array $batch, array $rows, ?array $share_review): void
{
	$campaign_id = absint($campaign['id'] ?? 0);
	$dashboard = backstage_outreach_business_contact_dashboard($campaign_id, $rows);
	$summary = (array) ($dashboard['summary'] ?? array());
	$states = (array) ($dashboard['states'] ?? array());
	$result = get_transient(backstage_outreach_business_contact_result_key($campaign_id));
	$sent_map = backstage_outreach_business_share_sent_map($campaign_id);
	$template = backstage_outreach_business_share_template($campaign_id);
	$subject = is_array($share_review) ? (string) ($share_review['subject'] ?? $template['subject']) : (string) $template['subject'];
	$message = is_array($share_review) ? (string) ($share_review['message'] ?? $template['message']) : (string) $template['message'];
	usort($rows, static function (array $a, array $b) use ($states): int {
		$a_status = backstage_outreach_business_contact_status((array) ($states[absint($a['id'] ?? 0)] ?? array()));
		$b_status = backstage_outreach_business_contact_status((array) ($states[absint($b['id'] ?? 0)] ?? array()));
		$rank = ((int) $a_status[2]) <=> ((int) $b_status[2]);
		return $rank !== 0 ? $rank : strcasecmp((string) ($a['business_name'] ?? ''), (string) ($b['business_name'] ?? ''));
	});

	echo '<section id="backstage-outreach-business-contacts" class="vms-pass-business-contacts" tabindex="-1" data-vms-business-contact-dashboard><h3>' . esc_html__('Business Contacts & Activity', 'backstage-outreach') . '</h3><p>' . esc_html__('Track business outreach separately from customer claims and coupon activity. Copying a message or opening a link never records contact.', 'backstage-outreach') . '</p>';
	backstage_outreach_render_business_contact_summary($summary);
	echo '<div class="vms-pass-contact-tools"><label>' . esc_html__('Search businesses', 'backstage-outreach') . '<input type="search" data-vms-contact-search placeholder="' . esc_attr__('Business, contact, email, phone, or website', 'backstage-outreach') . '"></label><label>' . esc_html__('Contact status', 'backstage-outreach') . '<select data-vms-contact-filter><option value="attention">' . esc_html__('Needs attention first', 'backstage-outreach') . '</option><option value="needs_first_email">' . esc_html__('Needs First Email', 'backstage-outreach') . '</option><option value="no_contact">' . esc_html__('No contact recorded', 'backstage-outreach') . '</option><option value="follow_up">' . esc_html__('Follow-up needed', 'backstage-outreach') . '</option><option value="email_handed_off">' . esc_html__('Email handed off', 'backstage-outreach') . '</option><option value="manual">' . esc_html__('Manually contacted', 'backstage-outreach') . '</option><option value="all">' . esc_html__('All businesses', 'backstage-outreach') . '</option></select></label><label>' . esc_html__('Sort', 'backstage-outreach') . '<select data-vms-contact-sort><option value="attention">' . esc_html__('Attention first', 'backstage-outreach') . '</option><option value="name">' . esc_html__('Business name', 'backstage-outreach') . '</option><option value="recent">' . esc_html__('Most recent activity', 'backstage-outreach') . '</option></select></label><p data-vms-contact-visible-count aria-live="polite"></p></div>';
	echo '<section class="vms-pass-email-invitations" aria-labelledby="backstage-outreach-email-invitations-heading"><h4 id="backstage-outreach-email-invitations-heading">' . esc_html__('Email Invitations', 'backstage-outreach') . '</h4><p>' . esc_html__('Select newly eligible businesses here. Business-link membership is managed separately, and nothing is selected or sent automatically.', 'backstage-outreach') . '</p>';
	if (is_array($result) && !empty($result['message'])) {
		$type = (string) ($result['type'] ?? 'info') === 'error' ? 'error' : 'success';
		echo '<div id="backstage-outreach-business-email-result" class="vms-pass-contact-result vms-pass-contact-result--' . esc_attr($type) . '" role="' . esc_attr($type === 'error' ? 'alert' : 'status') . '" tabindex="-1"><p>' . esc_html((string) $result['message']) . '</p>';
		if (isset($result['accepted'], $result['failed'], $result['skipped'], $result['handoff_at'])) {
			echo '<dl><div><dt>' . esc_html__('Email handed off', 'backstage-outreach') . '</dt><dd>' . esc_html((string) absint($result['accepted'])) . '</dd></div><div><dt>' . esc_html__('Failed', 'backstage-outreach') . '</dt><dd>' . esc_html((string) absint($result['failed'])) . '</dd></div><div><dt>' . esc_html__('Skipped', 'backstage-outreach') . '</dt><dd>' . esc_html((string) absint($result['skipped'])) . '</dd></div><div><dt>' . esc_html__('Handoff time', 'backstage-outreach') . '</dt><dd>' . esc_html(backstage_outreach_business_format_local_datetime((string) $result['handoff_at'])) . '</dd></div></dl>';
		}
		echo '</div>';
	}
	echo '<form id="backstage-outreach-business-email-form-' . esc_attr((string) $campaign_id) . '" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-pass-contact-email-form"><input type="hidden" name="action" value="backstage_outreach_business_share"><input type="hidden" name="campaign_id" value="' . esc_attr((string) $campaign_id) . '"><input type="hidden" name="share_mode" value="initial_preview">';
	wp_nonce_field('backstage_outreach_business_share');
	echo '<div class="vms-pass-contact-email-actions"><button type="button" class="button" data-vms-select-all-visible-eligible>' . esc_html__('Select All Visible Eligible', 'backstage-outreach') . '</button><button type="button" class="button" data-vms-clear-email-selection>' . esc_html__('Clear Selection', 'backstage-outreach') . '</button><strong data-vms-email-selected-count aria-live="polite">' . esc_html__('0 recipients selected', 'backstage-outreach') . '</strong><button class="button button-primary" data-vms-email-submit disabled>' . esc_html__('Review Selected First-Time Emails', 'backstage-outreach') . '</button></div><p class="description">' . esc_html__('Eligible businesses have a current, unsuppressed email and no successful prior email handoff. Manual contact history does not remove first-email eligibility. Filtering or searching clears selected businesses that are no longer visible.', 'backstage-outreach') . '</p></form>';
	backstage_outreach_render_business_email_review($campaign, $share_review, 'initial');
	echo '</section>';
	echo '<div class="vms-pass-contact-list">';
	$eligible_count = 0;
	foreach ($rows as $row) {
		if (backstage_outreach_render_business_contact_card($row, (array) ($states[absint($row['id'] ?? 0)] ?? array()), $campaign, $batch, $share_review, $subject, $message)) {
			$eligible_count++;
		}
	}
	echo '</div>';
	echo '<p class="description">' . esc_html(sprintf(_n('%d business is eligible for a first email handoff.', '%d businesses are eligible for a first email handoff.', $eligible_count, 'backstage-outreach'), $eligible_count)) . '</p>';
	backstage_outreach_render_business_resend_controls($campaign, $rows, $sent_map, $share_review);
	echo '</section>';
}
