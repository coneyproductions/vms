<?php
/** Administrator UI for canonical Outreach Parties. */

defined('ABSPATH') || exit;

function backstage_outreach_party_admin_capability(): string
{
	return function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options';
}

function backstage_outreach_party_admin_url(array $args = array()): string
{
	return vms_outreach_admin_page_url(array_merge(array('section' => 'directory'), $args));
}

function backstage_outreach_party_admin_redirect(array $args = array(), string $anchor = ''): void
{
	$url = backstage_outreach_party_admin_url($args);
	if ($anchor !== '') {
		$url .= '#' . sanitize_html_class($anchor);
	}
	wp_safe_redirect($url);
	exit;
}

function backstage_outreach_party_admin_notice(string $message, string $type = 'success'): void
{
	if (function_exists('vms_pass_claims_set_user_message')) {
		vms_pass_claims_set_user_message($type, $message);
	}
}

function backstage_outreach_party_admin_sources(): array
{
	global $wpdb;
	return array_values((array) $wpdb->get_results($wpdb->prepare('SELECT id,source_name,status FROM %i ORDER BY source_name ASC,id ASC', bvmgr_admission_table_pass_sources()), ARRAY_A));
}

function backstage_outreach_party_admin_organizations(): array
{
	global $wpdb;
	return array_values((array) $wpdb->get_results($wpdb->prepare('SELECT id,display_name,status FROM %i WHERE party_type=%s AND status<>%s ORDER BY display_name ASC,id ASC', backstage_outreach_party_table('parties'), 'organization', 'archived'), ARRAY_A));
}

function backstage_outreach_party_admin_parties(): array
{
	global $wpdb;
	return array_values((array) $wpdb->get_results($wpdb->prepare('SELECT id,display_name,party_type,status FROM %i WHERE status<>%s ORDER BY display_name ASC,id ASC', backstage_outreach_party_table('parties'), 'archived'), ARRAY_A));
}

function backstage_outreach_party_admin_method_by_id(int $method_id, int $party_id): ?array
{
	global $wpdb;
	$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d AND party_id=%d', backstage_outreach_party_table('contact_methods'), $method_id, $party_id), ARRAY_A);
	return is_array($row) ? $row : null;
}

function backstage_outreach_party_render_directory_screen(): void
{
	$party_id = absint($_GET['party_id'] ?? 0);
	$party = $party_id > 0 ? backstage_outreach_party_get($party_id) : null;
	$view = sanitize_key((string) ($_GET['view'] ?? ''));
	$search = sanitize_text_field((string) ($_GET['party_search'] ?? ''));
	$type = sanitize_key((string) ($_GET['party_type'] ?? ''));
	$source_id = absint($_GET['party_source_id'] ?? 0);
	$missing_email = sanitize_key((string) ($_GET['party_missing_email'] ?? ''));
	$page = max(1, absint($_GET['party_page'] ?? 1));
	$directory = backstage_outreach_party_get_directory(array('search' => $search, 'party_type' => $type, 'source_id' => $source_id, 'missing_email' => $missing_email, 'limit' => 100, 'page' => $page));
	$sources = backstage_outreach_party_admin_sources();
	$show_form = $view === 'new' || is_array($party);
	$form = is_array($party) ? $party : backstage_outreach_party_default();

	echo '<div class="vms-outreach-party-directory">';
	echo '<div class="vms-pass-guardrail-note"><div class="vms-pass-guardrail-note__copy"><strong>' . esc_html__('Directory identity is not campaign eligibility.', 'backstage-outreach') . '</strong> ' . esc_html__('Contacts & Partners stores reusable people and organizations. Adding a Party, Source association, or historical link does not create recipients, business memberships, offers, links, coupons, or messages.', 'backstage-outreach') . '</div></div>';

	if ($show_form) {
		echo '<section id="outreach-party-editor" class="vms-pass-card vms-outreach-party-editor">';
		echo '<h2>' . esc_html($party_id > 0 ? __('Edit canonical Party', 'backstage-outreach') : __('Add canonical Party', 'backstage-outreach')) . '</h2>';
		echo '<p class="description">' . esc_html__('These canonical values are independent of immutable historical campaign and business snapshots.', 'backstage-outreach') . '</p>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-pass-form">';
		echo '<input type="hidden" name="action" value="backstage_outreach_party_save"><input type="hidden" name="party_id" value="' . esc_attr((string) $party_id) . '">';
		wp_nonce_field('backstage_outreach_party_save');
		echo '<div class="vms-pass-grid">';
		echo '<label>' . vms_outreach_admin_render_label(__('Party type', 'backstage-outreach'), array('required' => true)) . '<select name="party_type" required>';
		foreach (backstage_outreach_party_types() as $key => $label) {
			echo '<option value="' . esc_attr($key) . '"' . selected((string) $form['party_type'], $key, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select></label>';
		echo '<label>' . vms_outreach_admin_render_label(__('Status', 'backstage-outreach')) . '<select name="status">';
		foreach (backstage_outreach_party_statuses() as $key => $label) {
			echo '<option value="' . esc_attr($key) . '"' . selected((string) $form['status'], $key, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select></label>';
		echo '<label class="vms-pass-span-2">' . vms_outreach_admin_render_label(__('Display name', 'backstage-outreach'), array('required' => true, 'help' => __('A readable canonical label. It is not used as an immutable identity key.', 'backstage-outreach'))) . '<input type="text" name="display_name" value="' . esc_attr((string) $form['display_name']) . '" required></label>';
		echo '<label>' . vms_outreach_admin_render_label(__('Given name', 'backstage-outreach')) . '<input type="text" name="given_name" value="' . esc_attr((string) $form['given_name']) . '"></label>';
		echo '<label>' . vms_outreach_admin_render_label(__('Family name', 'backstage-outreach')) . '<input type="text" name="family_name" value="' . esc_attr((string) $form['family_name']) . '"></label>';
		echo '<label class="vms-pass-span-2">' . vms_outreach_admin_render_label(__('Organization name', 'backstage-outreach'), array('help' => __('Used for organization Parties. Person-to-organization relationships belong in Affiliations.', 'backstage-outreach'))) . '<input type="text" name="organization_name" value="' . esc_attr((string) $form['organization_name']) . '"></label>';
		echo '<label class="vms-pass-span-2">' . vms_outreach_admin_render_label(__('Identifying details', 'backstage-outreach'), array('help' => __('Optional distinguishing context such as region, office, or role. Do not enter secrets.', 'backstage-outreach'))) . '<input type="text" name="identifying_details" value="' . esc_attr((string) $form['identifying_details']) . '"></label>';
		echo '<label class="vms-pass-span-2">' . vms_outreach_admin_render_label(__('Notes', 'backstage-outreach')) . '<textarea name="notes" rows="4">' . esc_textarea((string) $form['notes']) . '</textarea></label>';
		echo '</div><p class="vms-pass-actions"><button type="submit" class="button button-primary">' . esc_html($party_id > 0 ? __('Save canonical Party', 'backstage-outreach') : __('Create canonical Party', 'backstage-outreach')) . '</button> <a class="button" href="' . esc_url(backstage_outreach_party_admin_url()) . '">' . esc_html__('Close editor', 'backstage-outreach') . '</a></p></form>';

		if ($party_id > 0 && is_array($party)) {
			backstage_outreach_party_render_detail_sections($party);
		}
		echo '</section>';
	}

	backstage_outreach_party_render_legacy_review();
	if (function_exists('backstage_outreach_party_bulk_render_workspace')) {
		backstage_outreach_party_bulk_render_workspace();
	}

	echo '<section class="vms-pass-card">';
	echo '<h2>' . esc_html__('Contacts & Partners', 'backstage-outreach') . '</h2>';
	echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" class="vms-pass-form vms-pass-campaign-filters">';
	echo '<input type="hidden" name="page" value="' . esc_attr(vms_outreach_admin_menu_slug()) . '"><input type="hidden" name="section" value="directory">';
	echo '<div class="vms-pass-grid">';
	echo '<label class="vms-pass-span-2">' . vms_outreach_admin_render_label(__('Search identities and contact details', 'backstage-outreach')) . '<input type="search" name="party_search" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('name, organization, email, phone, identifying details', 'backstage-outreach') . '"></label>';
	echo '<label>' . vms_outreach_admin_render_label(__('Party type', 'backstage-outreach')) . '<select name="party_type"><option value="">' . esc_html__('People and organizations', 'backstage-outreach') . '</option>';
	foreach (backstage_outreach_party_types() as $key => $label) {
		echo '<option value="' . esc_attr($key) . '"' . selected($type, $key, false) . '>' . esc_html($label) . '</option>';
	}
	echo '</select></label>';
	echo '<label>' . vms_outreach_admin_render_label(__('Source association', 'backstage-outreach')) . '<select name="party_source_id"><option value="0">' . esc_html__('All Sources', 'backstage-outreach') . '</option>';
	foreach ($sources as $source) {
		echo '<option value="' . esc_attr((string) absint($source['id'])) . '"' . selected($source_id, absint($source['id']), false) . '>' . esc_html((string) $source['source_name']) . '</option>';
	}
	echo '</select></label>';
	echo '<label>' . vms_outreach_admin_render_label(__('Email availability', 'backstage-outreach')) . '<select name="party_missing_email"><option value="">' . esc_html__('All Parties', 'backstage-outreach') . '</option><option value="yes"' . selected($missing_email, 'yes', false) . '>' . esc_html__('Missing email', 'backstage-outreach') . '</option><option value="no"' . selected($missing_email, 'no', false) . '>' . esc_html__('Has email', 'backstage-outreach') . '</option></select></label>';
	echo '</div><p class="vms-pass-actions"><button type="submit" class="button">' . esc_html__('Apply filters', 'backstage-outreach') . '</button> <a class="button" href="' . esc_url(backstage_outreach_party_admin_url()) . '">' . esc_html__('Reset', 'backstage-outreach') . '</a></p></form>';

	echo '<div class="vms-pass-table-scroll vms-outreach-party-table-wrap"><table class="widefat striped vms-outreach-party-table"><thead><tr><th>' . esc_html__('Party', 'backstage-outreach') . '</th><th>' . esc_html__('Contact methods', 'backstage-outreach') . '</th><th>' . esc_html__('Affiliations / Sources', 'backstage-outreach') . '</th><th>' . esc_html__('Historical links', 'backstage-outreach') . '</th><th>' . esc_html__('Review', 'backstage-outreach') . '</th></tr></thead><tbody>';
	if (!$directory) {
		echo '<tr><td colspan="5">' . esc_html__('No canonical Parties matched this view. Legacy Contacts and businesses are not copied here automatically.', 'backstage-outreach') . '</td></tr>';
	} else {
		foreach ($directory as $row) {
			$row_id = absint($row['id']);
			$methods = backstage_outreach_party_get_contact_methods($row_id, false);
			$affiliations = backstage_outreach_party_get_affiliations($row_id);
			$party_sources = array_values(array_filter(backstage_outreach_party_get_sources($row_id), static fn(array $item): bool => (string) ($item['status'] ?? '') === 'active'));
			$legacy_links = backstage_outreach_party_get_legacy_links($row_id);
			$duplicates = backstage_outreach_party_possible_duplicate_count($row_id);
			echo '<tr>';
			echo '<td data-label="' . esc_attr__('Party', 'backstage-outreach') . '"><strong><a href="' . esc_url(backstage_outreach_party_admin_url(array('view' => 'edit', 'party_id' => $row_id))) . '#outreach-party-editor">' . esc_html((string) $row['display_name']) . '</a></strong><div class="description">' . esc_html((string) (backstage_outreach_party_types()[(string) $row['party_type']] ?? $row['party_type'])) . ' · ' . esc_html((string) (backstage_outreach_party_statuses()[(string) $row['status']] ?? $row['status'])) . '</div>' . (!empty($row['identifying_details']) ? '<div class="description">' . esc_html((string) $row['identifying_details']) . '</div>' : '') . '</td>';
			echo '<td data-label="' . esc_attr__('Contact methods', 'backstage-outreach') . '">';
			if (!$methods) {
				echo '<span class="description">' . esc_html__('Not provided', 'backstage-outreach') . '</span>';
			} else {
				foreach (array_slice($methods, 0, 4) as $method) {
					echo '<div><strong>' . esc_html((string) (backstage_outreach_party_contact_types()[(string) $method['method_type']] ?? $method['method_type'])) . ':</strong> ' . esc_html((string) $method['value']) . '</div>';
				}
			}
			if (absint($row['active_email_count'] ?? 0) === 0) {
				echo '<div class="vms-outreach-party-warning">' . esc_html__('Email not provided; email delivery is unavailable.', 'backstage-outreach') . '</div>';
			}
			echo '</td>';
			echo '<td data-label="' . esc_attr__('Affiliations / Sources', 'backstage-outreach') . '"><div>' . esc_html(sprintf(_n('%d affiliation', '%d affiliations', count($affiliations), 'backstage-outreach'), count($affiliations))) . '</div><div>' . esc_html(sprintf(_n('%d Source', '%d Sources', count($party_sources), 'backstage-outreach'), count($party_sources))) . '</div></td>';
			echo '<td data-label="' . esc_attr__('Historical links', 'backstage-outreach') . '">' . esc_html((string) count($legacy_links)) . '<div class="description">' . esc_html__('Mapping only; snapshots remain unchanged.', 'backstage-outreach') . '</div></td>';
			echo '<td data-label="' . esc_attr__('Review', 'backstage-outreach') . '">' . ($duplicates > 0 ? '<span class="vms-pass-status-pill is-needs_review">' . esc_html(sprintf(_n('%d possible duplicate', '%d possible duplicates', $duplicates, 'backstage-outreach'), $duplicates)) . '</span>' : '<span class="vms-pass-status-pill is-active">' . esc_html__('No exact channel duplicates', 'backstage-outreach') . '</span>') . '</td>';
			echo '</tr>';
		}
	}
	echo '</tbody></table></div>';
	echo '</section>';
	if (is_array($party) && function_exists('backstage_outreach_party_referral_render_panel')) {
		backstage_outreach_party_referral_render_panel($party);
	}
	echo '</div>';
}

function backstage_outreach_party_render_detail_sections(array $party): void
{
	$party_id = absint($party['id']);
	$methods = backstage_outreach_party_get_contact_methods($party_id, true);
	$method_id = absint($_GET['method_id'] ?? 0);
	$editing_method = $method_id > 0 ? backstage_outreach_party_admin_method_by_id($method_id, $party_id) : null;
	$method_form = is_array($editing_method) ? $editing_method : array('id' => 0, 'method_type' => 'email', 'label' => '', 'value' => '', 'is_primary' => 0, 'status' => 'active');
	$affiliations = backstage_outreach_party_get_affiliations($party_id);
	$party_sources = backstage_outreach_party_get_sources($party_id);
	$legacy_links = backstage_outreach_party_get_legacy_links($party_id);
	$campaign_roles = backstage_outreach_party_get_campaign_roles($party_id);

	echo '<div class="vms-outreach-party-detail-grid">';
	echo '<section class="vms-pass-form-section"><h3>' . esc_html__('Contact methods', 'backstage-outreach') . '</h3>';
	if (!$methods) {
		echo '<p class="description">' . esc_html__('No contact methods. Email is optional; this Party can still be stored and manually contacted.', 'backstage-outreach') . '</p>';
	} else {
		echo '<ul class="vms-outreach-party-detail-list">';
		foreach ($methods as $method) {
			$edit_url = backstage_outreach_party_admin_url(array('view' => 'edit', 'party_id' => $party_id, 'method_id' => absint($method['id'])) ) . '#outreach-party-contact-method';
			echo '<li><strong>' . esc_html((string) (backstage_outreach_party_contact_types()[(string) $method['method_type']] ?? $method['method_type'])) . ':</strong> ' . esc_html((string) $method['value']) . ' <span class="description">(' . esc_html((string) $method['status']) . (!empty($method['is_primary']) ? ', ' . esc_html__('primary', 'backstage-outreach') : '') . ')</span> <a href="' . esc_url($edit_url) . '">' . esc_html__('Edit', 'backstage-outreach') . '</a></li>';
		}
		echo '</ul>';
	}
	$emails = backstage_outreach_party_email_suppression_state($party_id);
	foreach ($emails as $email_state) {
		if (!empty($email_state['suppressed'])) {
			echo '<p class="vms-outreach-party-warning"><strong>' . esc_html__('Suppressed:', 'backstage-outreach') . '</strong> ' . esc_html((string) $email_state['email']) . '. ' . esc_html__('Suppression remains controlled by the existing channel-address register.', 'backstage-outreach') . '</p>';
		}
	}
	backstage_outreach_party_render_contact_method_form($party_id, $method_form);
	echo '</section>';

	echo '<section class="vms-pass-form-section"><h3>' . esc_html__('Source associations', 'backstage-outreach') . '</h3><p class="description">' . esc_html__('Directory grouping only. This does not create a reusable-business membership or campaign eligibility.', 'backstage-outreach') . '</p>';
	if ($party_sources) {
		echo '<ul class="vms-outreach-party-detail-list">';
		foreach ($party_sources as $source) {
			echo '<li>' . esc_html((string) ($source['source_name'] ?: sprintf(__('Source #%d', 'backstage-outreach'), absint($source['source_id'])))) . ' <span class="description">(' . esc_html((string) $source['status']) . ')</span>';
			if ((string) $source['status'] === 'active') {
				echo ' ' . backstage_outreach_party_inline_post_button('backstage_outreach_party_source_unlink', __('Remove', 'backstage-outreach'), array('party_id' => $party_id, 'source_id' => absint($source['source_id'])), 'backstage_outreach_party_source_unlink');
			}
			echo '</li>';
		}
		echo '</ul>';
	}
	echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-outreach-party-inline-editor"><input type="hidden" name="action" value="backstage_outreach_party_source_link"><input type="hidden" name="party_id" value="' . esc_attr((string) $party_id) . '">';
	wp_nonce_field('backstage_outreach_party_source_link');
	echo '<label>' . esc_html__('Add Source', 'backstage-outreach') . '<select name="source_id" required><option value="">' . esc_html__('Choose a Source', 'backstage-outreach') . '</option>';
	foreach (backstage_outreach_party_admin_sources() as $source) {
		echo '<option value="' . esc_attr((string) absint($source['id'])) . '">' . esc_html((string) $source['source_name']) . '</option>';
	}
	echo '</select></label><button type="submit" class="button">' . esc_html__('Associate Source', 'backstage-outreach') . '</button></form></section>';

	echo '<section class="vms-pass-form-section"><h3>' . esc_html__('Affiliations', 'backstage-outreach') . '</h3>';
	if (!$affiliations) {
		echo '<p class="description">' . esc_html__('No person-to-organization affiliations.', 'backstage-outreach') . '</p>';
	} else {
		echo '<ul class="vms-outreach-party-detail-list">';
		foreach ($affiliations as $affiliation) {
			echo '<li>' . esc_html((string) $affiliation['person_name']) . ' — ' . esc_html((string) $affiliation['relationship_role']) . ' — ' . esc_html((string) $affiliation['organization_name']) . ' <span class="description">(' . esc_html((string) $affiliation['status']) . ')</span>';
			if ((string) $affiliation['status'] === 'active') {
				echo ' ' . backstage_outreach_party_inline_post_button('backstage_outreach_party_affiliation_unlink', __('Remove', 'backstage-outreach'), array('party_id' => $party_id, 'affiliation_id' => absint($affiliation['id'])), 'backstage_outreach_party_affiliation_unlink');
			}
			echo '</li>';
		}
		echo '</ul>';
	}
	if ((string) $party['party_type'] === 'person') {
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-outreach-party-inline-editor"><input type="hidden" name="action" value="backstage_outreach_party_affiliation_link"><input type="hidden" name="party_id" value="' . esc_attr((string) $party_id) . '">';
		wp_nonce_field('backstage_outreach_party_affiliation_link');
		echo '<label>' . esc_html__('Organization', 'backstage-outreach') . '<select name="organization_party_id" required><option value="">' . esc_html__('Choose an organization', 'backstage-outreach') . '</option>';
		foreach (backstage_outreach_party_admin_organizations() as $organization) {
			echo '<option value="' . esc_attr((string) absint($organization['id'])) . '">' . esc_html((string) $organization['display_name']) . '</option>';
		}
		echo '</select></label><label>' . esc_html__('Relationship role', 'backstage-outreach') . '<input type="text" name="relationship_role" value="member" required></label><button type="submit" class="button">' . esc_html__('Add affiliation', 'backstage-outreach') . '</button></form>';
	}
	echo '</section>';

	echo '<section class="vms-pass-form-section"><h3>' . esc_html__('Linked historical records', 'backstage-outreach') . '</h3><p class="description">' . esc_html__('These are reviewed mappings. The historical snapshots remain immutable and operationally authoritative.', 'backstage-outreach') . '</p>';
	if (!$legacy_links) {
		echo '<p>' . esc_html__('No reviewed historical links.', 'backstage-outreach') . '</p>';
	} else {
		echo '<ul class="vms-outreach-party-detail-list">';
		foreach ($legacy_links as $link) {
			echo '<li><strong>' . esc_html((string) (backstage_outreach_party_legacy_types()[(string) $link['legacy_type']] ?? $link['legacy_type'])) . ' #' . esc_html((string) absint($link['legacy_id'])) . '</strong> ' . esc_html(sprintf(__('reviewed %s', 'backstage-outreach'), (string) $link['reviewed_at'])) . ' ' . backstage_outreach_party_inline_post_button('backstage_outreach_party_legacy_unlink', __('Unlink mapping', 'backstage-outreach'), array('party_id' => $party_id, 'link_id' => absint($link['id'])), 'backstage_outreach_party_legacy_unlink') . '</li>';
		}
		echo '</ul>';
	}
	echo '<p><a class="button" href="' . esc_url(backstage_outreach_party_admin_url(array('legacy_party_id' => $party_id))) . '#outreach-party-legacy-review">' . esc_html__('Review a historical record for this Party', 'backstage-outreach') . '</a></p></section>';

	echo '<section class="vms-pass-form-section"><h3>' . esc_html__('Campaign roles (directory metadata)', 'backstage-outreach') . '</h3><p class="description">' . esc_html__('Roles do not create recipients, offers, distributions, or messages in Phase 1.', 'backstage-outreach') . '</p>';
	if (!$campaign_roles) {
		echo '<p>' . esc_html__('No canonical campaign roles.', 'backstage-outreach') . '</p>';
	} else {
		echo '<ul class="vms-outreach-party-detail-list">';
		foreach ($campaign_roles as $role) {
			echo '<li>' . esc_html((string) ($role['campaign_name'] ?: sprintf(__('Campaign #%d', 'backstage-outreach'), absint($role['campaign_id'])))) . ' — ' . esc_html((string) $role['role_type']) . ' <span class="description">(' . esc_html((string) $role['status']) . ')</span></li>';
		}
		echo '</ul>';
	}
	echo '</section></div>';
}

function backstage_outreach_party_render_contact_method_form(int $party_id, array $method): void
{
	echo '<form id="outreach-party-contact-method" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-outreach-party-inline-editor"><input type="hidden" name="action" value="backstage_outreach_party_contact_save"><input type="hidden" name="party_id" value="' . esc_attr((string) $party_id) . '"><input type="hidden" name="method_id" value="' . esc_attr((string) absint($method['id'] ?? 0)) . '">';
	wp_nonce_field('backstage_outreach_party_contact_save');
	echo '<label>' . esc_html__('Method', 'backstage-outreach') . '<select name="method_type">';
	foreach (backstage_outreach_party_contact_types() as $key => $label) {
		echo '<option value="' . esc_attr($key) . '"' . selected((string) ($method['method_type'] ?? 'email'), $key, false) . '>' . esc_html($label) . '</option>';
	}
	echo '</select></label><label>' . esc_html__('Label', 'backstage-outreach') . '<input type="text" name="label" value="' . esc_attr((string) ($method['label'] ?? '')) . '" placeholder="' . esc_attr__('office, mobile, previous', 'backstage-outreach') . '"></label><label class="vms-outreach-party-inline-editor__wide">' . esc_html__('Value', 'backstage-outreach') . '<input type="text" name="value" value="' . esc_attr((string) ($method['value'] ?? '')) . '" required></label><label>' . esc_html__('Status', 'backstage-outreach') . '<select name="status"><option value="active"' . selected((string) ($method['status'] ?? 'active'), 'active', false) . '>' . esc_html__('Active', 'backstage-outreach') . '</option><option value="inactive"' . selected((string) ($method['status'] ?? 'active'), 'inactive', false) . '>' . esc_html__('Inactive / historical', 'backstage-outreach') . '</option></select></label><label class="vms-outreach-party-checkbox"><input type="checkbox" name="is_primary" value="1"' . checked(!empty($method['is_primary']), true, false) . '> ' . esc_html__('Primary for this method type', 'backstage-outreach') . '</label><button type="submit" class="button">' . esc_html(absint($method['id'] ?? 0) > 0 ? __('Save method', 'backstage-outreach') : __('Add method', 'backstage-outreach')) . '</button></form>';
}

function backstage_outreach_party_inline_post_button(string $action, string $label, array $fields, string $nonce_action): string
{
	$html = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-outreach-inline-form"><input type="hidden" name="action" value="' . esc_attr($action) . '">';
	foreach ($fields as $key => $value) {
		$html .= '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr((string) $value) . '">';
	}
	$html .= wp_nonce_field($nonce_action, '_wpnonce', true, false);
	$html .= '<button type="submit" class="button button-small">' . esc_html($label) . '</button></form>';
	return $html;
}

function backstage_outreach_party_render_legacy_review(): void
{
	$legacy_type = sanitize_key((string) ($_GET['legacy_type'] ?? ''));
	$legacy_id = absint($_GET['legacy_id'] ?? 0);
	$preferred_party_id = absint($_GET['legacy_party_id'] ?? 0);
	$snapshot = ($legacy_type !== '' && $legacy_id > 0) ? backstage_outreach_party_get_legacy_snapshot($legacy_type, $legacy_id) : null;
	$current_link = (!is_wp_error($snapshot) && is_array($snapshot)) ? backstage_outreach_party_get_legacy_link($legacy_type, $legacy_id) : null;
	$suggestions = (!is_wp_error($snapshot) && is_array($snapshot)) ? backstage_outreach_party_suggestions($snapshot) : array();
	echo '<section id="outreach-party-legacy-review" class="vms-pass-card vms-outreach-party-legacy-review"><h2>' . esc_html__('Review a legacy identity association', 'backstage-outreach') . '</h2><p class="description">' . esc_html__('Search a historical reference, inspect the immutable snapshot and match reasons, then explicitly confirm one canonical Party. No historical row is edited.', 'backstage-outreach') . '</p>';
	echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" class="vms-outreach-party-inline-editor"><input type="hidden" name="page" value="' . esc_attr(vms_outreach_admin_menu_slug()) . '"><input type="hidden" name="section" value="directory">';
	echo '<label>' . esc_html__('Historical record type', 'backstage-outreach') . '<select name="legacy_type" required><option value="">' . esc_html__('Choose a type', 'backstage-outreach') . '</option>';
	foreach (backstage_outreach_party_legacy_types() as $key => $label) {
		echo '<option value="' . esc_attr($key) . '"' . selected($legacy_type, $key, false) . '>' . esc_html($label) . '</option>';
	}
	echo '</select></label><label>' . esc_html__('Historical record ID', 'backstage-outreach') . '<input type="number" name="legacy_id" min="1" value="' . esc_attr($legacy_id > 0 ? (string) $legacy_id : '') . '" required></label>';
	if ($preferred_party_id > 0) {
		echo '<input type="hidden" name="legacy_party_id" value="' . esc_attr((string) $preferred_party_id) . '">';
	}
	echo '<button type="submit" class="button">' . esc_html__('Review association', 'backstage-outreach') . '</button></form>';
	if (is_wp_error($snapshot)) {
		echo '<div class="notice notice-error inline"><p>' . esc_html($snapshot->get_error_message()) . '</p></div>';
	} elseif (is_array($snapshot)) {
		echo '<div class="vms-outreach-party-snapshot"><h3>' . esc_html__('Immutable legacy snapshot', 'backstage-outreach') . '</h3><dl>';
		foreach (array('display_name' => __('Name', 'backstage-outreach'), 'organization' => __('Organization', 'backstage-outreach'), 'email' => __('Email', 'backstage-outreach'), 'phone' => __('Phone', 'backstage-outreach'), 'status' => __('Legacy status', 'backstage-outreach'), 'campaign_id' => __('Campaign ID', 'backstage-outreach')) as $key => $label) {
			echo '<div><dt>' . esc_html($label) . '</dt><dd>' . esc_html((string) (($snapshot[$key] ?? '') !== '' ? $snapshot[$key] : __('Not provided', 'backstage-outreach'))) . '</dd></div>';
		}
		echo '</dl></div>';
		if (is_array($current_link)) {
			echo '<div class="vms-pass-guardrail-note"><div class="vms-pass-guardrail-note__copy"><strong>' . esc_html__('Already linked:', 'backstage-outreach') . '</strong> ' . esc_html((string) $current_link['display_name']) . '. ' . esc_html__('Use explicit correction or unlinking; this record cannot be silently associated twice.', 'backstage-outreach') . '</div></div>';
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-outreach-party-inline-editor"><input type="hidden" name="action" value="backstage_outreach_party_legacy_correct"><input type="hidden" name="link_id" value="' . esc_attr((string) absint($current_link['id'])) . '">';
			wp_nonce_field('backstage_outreach_party_legacy_correct');
			echo '<label>' . esc_html__('Correct mapping to Party', 'backstage-outreach') . '<select name="to_party_id" required>';
			foreach (backstage_outreach_party_admin_parties() as $candidate) {
				echo '<option value="' . esc_attr((string) absint($candidate['id'])) . '"' . selected(absint($current_link['party_id']), absint($candidate['id']), false) . '>' . esc_html((string) $candidate['display_name']) . ' — ' . esc_html((string) $candidate['party_type']) . '</option>';
			}
			echo '</select></label><button type="submit" class="button">' . esc_html__('Confirm correction', 'backstage-outreach') . '</button></form>';
		} else {
			echo '<h3>' . esc_html__('Suggested canonical Parties', 'backstage-outreach') . '</h3>';
			if (!$suggestions) {
				echo '<p class="description">' . esc_html__('No exact channel or name suggestions. Choose a Party explicitly after verifying the identity.', 'backstage-outreach') . '</p>';
			} else {
				echo '<ul class="vms-outreach-party-suggestions">';
				foreach ($suggestions as $suggestion) {
					echo '<li><strong>' . esc_html((string) $suggestion['party']['display_name']) . '</strong> — ' . esc_html(implode('; ', (array) $suggestion['reasons'])) . (!empty($suggestion['ambiguous']) ? ' <span class="vms-pass-status-pill is-needs_review">' . esc_html__('Ambiguous—review required', 'backstage-outreach') . '</span>' : '') . '</li>';
				}
				echo '</ul>';
			}
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-outreach-party-inline-editor"><input type="hidden" name="action" value="backstage_outreach_party_legacy_confirm"><input type="hidden" name="legacy_type" value="' . esc_attr($legacy_type) . '"><input type="hidden" name="legacy_id" value="' . esc_attr((string) $legacy_id) . '"><input type="hidden" name="snapshot_hash" value="' . esc_attr((string) $snapshot['snapshot_hash']) . '">';
			wp_nonce_field('backstage_outreach_party_legacy_confirm');
			echo '<label>' . esc_html__('Verified canonical Party', 'backstage-outreach') . '<select name="party_id" required><option value="">' . esc_html__('Choose after review', 'backstage-outreach') . '</option>';
			foreach (backstage_outreach_party_admin_parties() as $candidate) {
				echo '<option value="' . esc_attr((string) absint($candidate['id'])) . '"' . selected($preferred_party_id, absint($candidate['id']), false) . '>' . esc_html((string) $candidate['display_name']) . ' — ' . esc_html((string) $candidate['party_type']) . '</option>';
			}
			echo '</select></label><label class="vms-outreach-party-checkbox"><input type="checkbox" name="identity_reviewed" value="1" required> ' . esc_html__('I reviewed the snapshot and ambiguity warnings and confirm this identity.', 'backstage-outreach') . '</label><button type="submit" class="button button-primary">' . esc_html__('Confirm reviewed association', 'backstage-outreach') . '</button></form>';
		}
	}
	echo '</section>';
}

function backstage_outreach_party_require_admin(string $nonce_action): int
{
	if (!current_user_can(backstage_outreach_party_admin_capability())) {
		wp_die(esc_html__('Access denied.', 'backstage-outreach'));
	}
	check_admin_referer($nonce_action);
	return get_current_user_id();
}

function backstage_outreach_party_handle_save(): void
{
	$user_id = backstage_outreach_party_require_admin('backstage_outreach_party_save');
	$party_id = absint($_POST['party_id'] ?? 0);
	$result = backstage_outreach_party_save((array) wp_unslash($_POST), $user_id, $party_id);
	if (is_wp_error($result)) {
		backstage_outreach_party_admin_notice($result->get_error_message(), 'error');
		backstage_outreach_party_admin_redirect(array('view' => $party_id > 0 ? 'edit' : 'new', 'party_id' => $party_id), 'outreach-party-editor');
	}
	backstage_outreach_party_admin_notice($party_id > 0 ? __('Canonical Party updated.', 'backstage-outreach') : __('Canonical Party created.', 'backstage-outreach'));
	backstage_outreach_party_admin_redirect(array('view' => 'edit', 'party_id' => absint($result['id'] ?? 0)), 'outreach-party-editor');
}
add_action('admin_post_backstage_outreach_party_save', 'backstage_outreach_party_handle_save');

function backstage_outreach_party_handle_contact_save(): void
{
	$user_id = backstage_outreach_party_require_admin('backstage_outreach_party_contact_save');
	$party_id = absint($_POST['party_id'] ?? 0);
	$result = backstage_outreach_party_save_contact_method($party_id, (array) wp_unslash($_POST), $user_id, absint($_POST['method_id'] ?? 0));
	backstage_outreach_party_admin_notice(is_wp_error($result) ? $result->get_error_message() : __('Contact method saved. No message was queued or sent.', 'backstage-outreach'), is_wp_error($result) ? 'error' : 'success');
	backstage_outreach_party_admin_redirect(array('view' => 'edit', 'party_id' => $party_id), 'outreach-party-contact-method');
}
add_action('admin_post_backstage_outreach_party_contact_save', 'backstage_outreach_party_handle_contact_save');

function backstage_outreach_party_handle_source_link(): void
{
	$user_id = backstage_outreach_party_require_admin('backstage_outreach_party_source_link');
	$party_id = absint($_POST['party_id'] ?? 0);
	$result = backstage_outreach_party_link_source($party_id, absint($_POST['source_id'] ?? 0), $user_id);
	backstage_outreach_party_admin_notice(is_wp_error($result) ? $result->get_error_message() : __('Source associated for directory grouping only.', 'backstage-outreach'), is_wp_error($result) ? 'error' : 'success');
	backstage_outreach_party_admin_redirect(array('view' => 'edit', 'party_id' => $party_id), 'outreach-party-editor');
}
add_action('admin_post_backstage_outreach_party_source_link', 'backstage_outreach_party_handle_source_link');

function backstage_outreach_party_handle_source_unlink(): void
{
	$user_id = backstage_outreach_party_require_admin('backstage_outreach_party_source_unlink');
	$party_id = absint($_POST['party_id'] ?? 0);
	$ok = backstage_outreach_party_unlink_source($party_id, absint($_POST['source_id'] ?? 0), $user_id);
	backstage_outreach_party_admin_notice($ok ? __('Source association removed. Operational Source memberships were untouched.', 'backstage-outreach') : __('Source association could not be removed.', 'backstage-outreach'), $ok ? 'success' : 'error');
	backstage_outreach_party_admin_redirect(array('view' => 'edit', 'party_id' => $party_id), 'outreach-party-editor');
}
add_action('admin_post_backstage_outreach_party_source_unlink', 'backstage_outreach_party_handle_source_unlink');

function backstage_outreach_party_handle_affiliation_link(): void
{
	$user_id = backstage_outreach_party_require_admin('backstage_outreach_party_affiliation_link');
	$party_id = absint($_POST['party_id'] ?? 0);
	$result = backstage_outreach_party_save_affiliation($party_id, absint($_POST['organization_party_id'] ?? 0), sanitize_text_field((string) wp_unslash($_POST['relationship_role'] ?? 'member')), $user_id);
	backstage_outreach_party_admin_notice(is_wp_error($result) ? $result->get_error_message() : __('Affiliation saved.', 'backstage-outreach'), is_wp_error($result) ? 'error' : 'success');
	backstage_outreach_party_admin_redirect(array('view' => 'edit', 'party_id' => $party_id), 'outreach-party-editor');
}
add_action('admin_post_backstage_outreach_party_affiliation_link', 'backstage_outreach_party_handle_affiliation_link');

function backstage_outreach_party_handle_affiliation_unlink(): void
{
	$user_id = backstage_outreach_party_require_admin('backstage_outreach_party_affiliation_unlink');
	$party_id = absint($_POST['party_id'] ?? 0);
	$ok = backstage_outreach_party_unlink_affiliation(absint($_POST['affiliation_id'] ?? 0), $user_id);
	backstage_outreach_party_admin_notice($ok ? __('Affiliation removed.', 'backstage-outreach') : __('Affiliation could not be removed.', 'backstage-outreach'), $ok ? 'success' : 'error');
	backstage_outreach_party_admin_redirect(array('view' => 'edit', 'party_id' => $party_id), 'outreach-party-editor');
}
add_action('admin_post_backstage_outreach_party_affiliation_unlink', 'backstage_outreach_party_handle_affiliation_unlink');

function backstage_outreach_party_handle_legacy_confirm(): void
{
	$user_id = backstage_outreach_party_require_admin('backstage_outreach_party_legacy_confirm');
	if (empty($_POST['identity_reviewed'])) {
		backstage_outreach_party_admin_notice(__('Confirm that you reviewed the identity and ambiguity warnings.', 'backstage-outreach'), 'error');
		backstage_outreach_party_admin_redirect();
	}
	$legacy_type = sanitize_key((string) wp_unslash($_POST['legacy_type'] ?? ''));
	$legacy_id = absint($_POST['legacy_id'] ?? 0);
	$party_id = absint($_POST['party_id'] ?? 0);
	$snapshot = backstage_outreach_party_get_legacy_snapshot($legacy_type, $legacy_id);
	$suggestions = is_array($snapshot) ? backstage_outreach_party_suggestions($snapshot) : array();
	$reasons = array();
	foreach ($suggestions as $suggestion) {
		if (absint($suggestion['party']['id'] ?? 0) === $party_id) {
			$reasons = (array) $suggestion['reasons'];
			if (!empty($suggestion['ambiguous'])) {
				$reasons[] = __('Operator resolved an ambiguous suggestion.', 'backstage-outreach');
			}
			break;
		}
	}
	if (!$reasons) {
		$reasons[] = __('Operator explicitly selected a Party after reviewing the snapshot.', 'backstage-outreach');
	}
	$request_key = hash('sha256', 'legacy-confirm|' . $user_id . '|' . $legacy_type . '|' . $legacy_id . '|' . $party_id . '|' . sanitize_text_field((string) ($_POST['snapshot_hash'] ?? '')));
	$result = backstage_outreach_party_confirm_legacy_link($party_id, $legacy_type, $legacy_id, sanitize_text_field((string) wp_unslash($_POST['snapshot_hash'] ?? '')), $reasons, $user_id, $request_key);
	backstage_outreach_party_admin_notice(is_wp_error($result) ? $result->get_error_message() : __('Reviewed historical association saved. The historical record was not changed.', 'backstage-outreach'), is_wp_error($result) ? 'error' : 'success');
	backstage_outreach_party_admin_redirect(array('legacy_type' => $legacy_type, 'legacy_id' => $legacy_id, 'legacy_party_id' => $party_id), 'outreach-party-legacy-review');
}
add_action('admin_post_backstage_outreach_party_legacy_confirm', 'backstage_outreach_party_handle_legacy_confirm');

function backstage_outreach_party_handle_legacy_correct(): void
{
	$user_id = backstage_outreach_party_require_admin('backstage_outreach_party_legacy_correct');
	$link_id = absint($_POST['link_id'] ?? 0);
	$to_party_id = absint($_POST['to_party_id'] ?? 0);
	$request_key = hash('sha256', 'legacy-correct|' . $user_id . '|' . $link_id . '|' . $to_party_id);
	$result = backstage_outreach_party_reassign_legacy_link($link_id, $to_party_id, $user_id, $request_key);
	backstage_outreach_party_admin_notice(is_wp_error($result) ? $result->get_error_message() : __('Historical association corrected. No historical record was changed.', 'backstage-outreach'), is_wp_error($result) ? 'error' : 'success');
	backstage_outreach_party_admin_redirect(array('view' => 'edit', 'party_id' => $to_party_id), 'outreach-party-editor');
}
add_action('admin_post_backstage_outreach_party_legacy_correct', 'backstage_outreach_party_handle_legacy_correct');

function backstage_outreach_party_handle_legacy_unlink(): void
{
	$user_id = backstage_outreach_party_require_admin('backstage_outreach_party_legacy_unlink');
	$party_id = absint($_POST['party_id'] ?? 0);
	$link_id = absint($_POST['link_id'] ?? 0);
	$ok = backstage_outreach_party_unlink_legacy($link_id, $user_id, hash('sha256', 'legacy-unlink|' . $user_id . '|' . $link_id));
	backstage_outreach_party_admin_notice($ok ? __('Historical mapping unlinked. The historical record remains unchanged.', 'backstage-outreach') : __('Historical mapping could not be unlinked.', 'backstage-outreach'), $ok ? 'success' : 'error');
	backstage_outreach_party_admin_redirect(array('view' => 'edit', 'party_id' => $party_id), 'outreach-party-editor');
}
add_action('admin_post_backstage_outreach_party_legacy_unlink', 'backstage_outreach_party_handle_legacy_unlink');
