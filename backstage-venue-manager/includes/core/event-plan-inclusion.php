<?php
defined('ABSPATH') || exit;

/**
 * Event Plan inclusion + status semantics (canonical).
 *
 * Purpose:
 * - Define one consistent meaning of Draft / Ready / Published (and other statuses)
 * - Provide context-aware inclusion rules (Schedule vs Dashboard vs Bills)
 *
 * Notes:
 * - WP post_status cannot represent Draft/Ready/Published on its own.
 * - Internal status is stored in event plan meta: vms_meta_key('event_plan','status').
 */

/**
 * Normalize status to canonical keys.
 */
function bvmgr_event_plan_status_normalize(string $status): string
{
	$status = sanitize_key((string) $status);

	// American spelling normalization.
	if ($status === 'canceled') {
		$status = 'cancelled';
	}

	return $status;
}

/**
 * Get the internal plan status (context-aware fallback policy).
 *
 * Context fallback policy:
 * - Financial contexts must be conservative (missing status => Draft).
 * - Non-financial contexts may infer Published for legacy plans where meta is missing
 *   but WP post_status is publish (to avoid silently hiding older records).
 */
function bvmgr_event_plan_get_status(int $plan_id, string $context = 'generic'): string
{
	$plan_id = absint($plan_id);
	$context = sanitize_key((string) $context);

	if ($plan_id <= 0) {
		return 'draft';
	}

	$k_status = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status';
	$status_raw = (string) get_post_meta($plan_id, $k_status, true);
	$status = bvmgr_event_plan_status_normalize($status_raw);

	$allowed = function_exists('bvmgr_event_plan_statuses')
		? array_keys((array) bvmgr_event_plan_statuses())
		: array('draft', 'ready', 'published', 'tentative', 'confirmed', 'cancelled', 'archived');

	if ($status === '' || !in_array($status, $allowed, true)) {
		// Conservative: never infer Published in financial contexts.
		if (in_array($context, array('bills', 'dashboard_bills', 'financial', 'payables', 'payables_export'), true)) {
			return 'draft';
		}

		// Legacy-friendly: infer Published when WP believes the post is published.
		$post_status = (string) get_post_status($plan_id);
		if ($post_status === 'publish') {
			return 'published';
		}

		return 'draft';
	}

	return $status;
}

/**
 * True if status is cancelled.
 */
function bvmgr_event_plan_is_cancelled(string $status): bool
{
	$status = bvmgr_event_plan_status_normalize($status);
	return ($status === 'cancelled');
}

/**
 * Context-aware allowlist of statuses.
 *
 * Supported contexts (non-exhaustive):
 * - schedule_admin: admin Schedule calendar/list
 * - dashboard: dashboard today/week events
 * - dashboard_bills: dashboard upcoming bills
 * - event_list: Event Plans list table
 * - payables_export: bills export builder
 *
 * Flags (optional):
 * - include_drafts: include Draft plans (default varies by context)
 * - include_cancelled: include Cancelled plans (default varies by context)
 * - include_archived: include Archived plans (default false)
 */
function bvmgr_event_plan_allowed_statuses(string $context, array $flags = array()): array
{
	$context = sanitize_key((string) $context);

	$all = function_exists('bvmgr_event_plan_statuses')
		? array_keys((array) bvmgr_event_plan_statuses())
		: array('draft', 'ready', 'published', 'tentative', 'confirmed', 'cancelled', 'archived');

	// Published-only everywhere by default.
	$include_drafts    = array_key_exists('include_drafts', $flags) ? (bool) $flags['include_drafts'] : false;
	$include_cancelled = array_key_exists('include_cancelled', $flags)
		? (bool) $flags['include_cancelled']
		: (in_array($context, array('schedule_admin', 'event_list'), true));
	$include_archived  = array_key_exists('include_archived', $flags) ? (bool) $flags['include_archived'] : false;

	$allowed = (array) $all;

	// Archived is almost always hidden unless explicitly opted in.
	if (!$include_archived) {
		$allowed = array_diff($allowed, array('archived'));
	}

	// Cancelled is opt-in by default, except Schedule + Event Plans list where it is shown by default.
	if (!$include_cancelled) {
		$allowed = array_diff($allowed, array('cancelled'));
	}

	$what_if_set = array('published', 'ready', 'tentative', 'confirmed', 'draft', 'cancelled');

	$published_only_set = $include_cancelled
		? array('published', 'cancelled')
		: array('published');

	if (in_array($context, array('dashboard_bills', 'bills', 'financial', 'payables', 'payables_export'), true)) {
		// Financial contexts: Published-only by default.
		// Cancelled never appears in bills/payables, even if a UI toggle is on.
		$what_if_financial_set = array('published', 'ready', 'tentative', 'confirmed', 'draft');

		$allowed = $include_drafts
			? array_intersect($allowed, $what_if_financial_set)
			: array_intersect($allowed, array('published'));
		return array_values(array_unique(array_filter($allowed)));
	}

	if ($context === 'dashboard') {
		// Dashboard events (today/week): Published-only by default.
		// UI checkbox is labeled “Include Draft/Ready”.
		$allowed = $include_drafts
			? array_intersect($allowed, $what_if_set)
			: array_intersect($allowed, $published_only_set);
		return array_values(array_unique(array_filter($allowed)));
	}

	if (in_array($context, array('schedule_admin', 'event_list', 'generic'), true)) {
		// Schedule + Event Plans list: Published-only unless Include Draft/Ready is enabled (Schedule + List default this on when no user preference exists).
		$allowed = $include_drafts
			? array_intersect($allowed, $what_if_set)
			: array_intersect($allowed, $published_only_set);
		return array_values(array_unique(array_filter($allowed)));
	}

	// Fallback: Published-only unless explicitly opted into drafts.
	$allowed = $include_drafts
		? array_intersect($allowed, $what_if_set)
		: array_intersect($allowed, $published_only_set);

	return array_values(array_unique(array_filter($allowed)));
}

/**
 * Should the plan appear in a given context?
 */
function bvmgr_event_plan_should_include(int $plan_id, string $context = 'generic', array $flags = array()): bool
{
	$context = sanitize_key((string) $context);
	$status = bvmgr_event_plan_get_status($plan_id, $context);

	$allowed = bvmgr_event_plan_allowed_statuses($context, $flags);
	return in_array($status, $allowed, true);
}

/**
 * Human label for a status (uses registry when available).
 */
function bvmgr_event_plan_status_label(string $status): string
{
	$status = bvmgr_event_plan_status_normalize($status);

	if (function_exists('bvmgr_event_plan_statuses')) {
		$map = (array) bvmgr_event_plan_statuses();
		if (isset($map[$status])) {
			return (string) $map[$status];
		}
	}

	return ucwords(str_replace(array('_', '-'), ' ', $status));
}

/**
 * Status pill CSS class for admin list tables.
 * (Matches existing vms-admin.css classes.)
 */
function bvmgr_event_plan_status_pill_class(string $status): string
{
	$status = bvmgr_event_plan_status_normalize($status);

	// vms-admin.css currently defines: .vms-pill-ready, .vms-pill-draft, .vms-pill-cancelled
	if ($status === 'cancelled') {
		return 'vms-pill-cancelled';
	}

	if (in_array($status, array('published', 'confirmed', 'ready'), true)) {
		return 'vms-pill-ready';
	}

	return 'vms-pill-draft';
}
