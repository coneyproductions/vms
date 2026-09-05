<?php
declare(strict_types=1);

require_once __DIR__ . '/wporg-prefix-b3.php';

/** Deterministic dependency-wave assignment for the frozen B3 function map. */
final class BVMGR_WPORG_Prefix_B3_Waves
{
	public const PLAN_PATH = 'docs/wporg-prefix-b3-waves.json';

	private const DEFINITIONS = array(
		'W1' => array(
			'scope' => 'Activation and plugin-basename pilot',
			'expected' => array('unique_functions' => 35, 'declaration_sites' => 35, 'duplicate_families' => 0, 'declaration_files' => 2),
			'files' => array('includes/activation.php', 'includes/plugin-basename-compat.php'),
			'focused_test_groups' => array('B2 foundation', 'plugin identity', 'activation/public-page ownership', 'legacy Square cleanup', 'runtime stubs', 'release compatibility', 'reduced lifecycle checkpoint'),
		),
		'W2' => array(
			'scope' => 'Atomic five-add-on and public-extension API boundary',
			'expected' => array('unique_functions' => 66, 'declaration_sites' => 68, 'duplicate_families' => 2, 'declaration_files' => 29),
			'files' => array(
				'includes/runtime-guards.php', 'includes/admin-ui/context.php', 'includes/admin-ui/nav.php', 'includes/admin-ui/shell.php',
				'includes/helpers.php', 'includes/core/calendar-feed.php', 'includes/core/plugin.php', 'includes/docs-registry.php',
				'includes/core/event-plan-inclusion.php', 'includes/core/event-plan-review.php', 'includes/core/secondary-vendor-assignments.php',
				'includes/cpt/event-plans.php', 'includes/core/registry/tours.php', 'includes/core/registry/meta-keys.php',
				'includes/core/registry/normalizers.php', 'includes/core/notifications.php', 'includes/core/payables.php',
				'includes/portal/vendor-portal.php', 'includes/core/registry/admin-menu.php', 'includes/modules/load.php',
				'includes/core/tours/class-vms-tours.php', 'includes/tours/tours.php', 'includes/schedule/season-dates.php',
				'includes/social-share/providers/registry.php', 'includes/core/staffing.php', 'includes/integrations/ticketing-rules-v2.php',
				'includes/core/ticket-revenue.php', 'includes/integrations/ticketing-phase-b.php', 'includes/core/registry/vendor-schema.php',
			),
			'focused_test_groups' => array('disposable five-add-on B3 harness', 'installed-add-on regression evidence', 'public APIs', 'admin registry', 'tours/docs/social', 'notifications/season dates', 'Event Plan review/secondary vendors', 'ticket revenue', 'exact dynamic and duplicate resolution', 'strict packaged scan'),
		),
		'W3' => array(
			'scope' => 'Shared runtime, registries, admin UI, docs, and tours',
			'expected' => array('unique_functions' => 457, 'declaration_sites' => 460, 'duplicate_families' => 3, 'declaration_files' => 30),
			'files' => array(
				'includes/runtime-guards.php', 'includes/core/registry/admin-menu.php', 'includes/admin/menu.php', 'includes/core/registry/admin-slugs.php',
				'includes/admin/reference/keys-map.php', 'includes/helpers.php', 'includes/admin-ui/helpers.php', 'includes/admin-ui/nav.php',
				'includes/admin-ui/shell.php', 'includes/admin-ui/assets.php', 'includes/admin-ui/context.php', 'includes/core/registry/constants.php',
				'includes/core/registry/statuses.php', 'includes/core/registry/csv-contracts.php', 'includes/rest-dashboard.php',
				'includes/admin/dashboard.php', 'includes/db/migrations.php', 'includes/admin/docs-page.php', 'includes/docs-registry.php',
				'includes/docs-render.php', 'includes/docs-public.php', 'includes/core/tours/class-vms-tours.php', 'includes/tours/tours.php',
				'includes/helpers/checkin-close.php', 'includes/helpers/schedule-helpers.php', 'includes/core/registry/vms-keys-map.php',
				'includes/core/registry/meta-keys.php', 'includes/helpers/image-normalization.php', 'includes/core/private-files.php',
				'includes/core/slow-request-logger.php',
			),
			'focused_test_groups' => array('runtime/input guards', 'admin shell/menu/reference', 'identity/runtime stubs', 'public docs', 'guided tours', 'private files', 'slow-request logger', 'manifest and dynamic resolution'),
		),
		'W4' => array(
			'scope' => 'Event Plan editor, review, import, performance, cancellation, and feedback',
			'expected' => array('unique_functions' => 640, 'declaration_sites' => 647, 'duplicate_families' => 7, 'declaration_files' => 18),
			'files' => array(
				'includes/cpt/event-plans.php', 'includes/cpt/ratings.php', 'includes/admin/continuity-binder.php', 'includes/core/cancellation.php',
				'includes/core/cancellation-adapters.php', 'includes/admin/event-command-center.php', 'includes/core/event-credits.php',
				'includes/core/secondary-vendor-assignments.php', 'includes/core/event-plan-inclusion.php', 'includes/core/event-plan-performance.php',
				'includes/admin/data-tools/page-event-plan-import.php', 'includes/services/event-plan-import/event-plan-import-engine.php',
				'includes/admin/data-tools/actions-event-plan-import.php', 'includes/core/event-plan-review.php',
				'includes/core/event-plan-save-profiler.php', 'includes/admin/event-feedback.php', 'includes/core/event-feedback.php',
				'includes/public/event-feedback.php',
			),
			'focused_test_groups' => array('Event Plan suites', 'feedback/request hash', 'cancellation/adapters', 'secondary vendors', 'import/file/upload/output', 'performance/save profiler', 'continuity/integrity'),
		),
		'W5' => array(
			'scope' => 'Ticketing runtime, rules, claims, and verifications',
			'expected' => array('unique_functions' => 616, 'declaration_sites' => 616, 'duplicate_families' => 0, 'declaration_files' => 7),
			'files' => array(
				'includes/integrations/ticketing-phase-b.php', 'includes/integrations/ticketing-rules-v2.php',
				'includes/integrations/ticketing.php', 'includes/integrations/ticketing-claims-customer.php',
				'includes/integrations/ticketing-claims-admin.php', 'includes/integrations/ticketing-claims-framework.php',
				'includes/integrations/ticketing-verifications.php',
			),
			'focused_test_groups' => array('ticketing phase B', 'rules/request path', 'claims', 'verifications', 'output buffering', 'checkout/assignee', 'legacy smoke', 'ticket UI isolation', 'admissions REST', 'auth/input guards'),
		),
		'W6' => array(
			'scope' => 'Ticket integrity, forensics, mutation, Square, and revenue',
			'expected' => array('unique_functions' => 533, 'declaration_sites' => 533, 'duplicate_families' => 0, 'declaration_files' => 19),
			'files' => array(
				'includes/core/calendar-ticket-counts.php', 'includes/integrations/attendance-woo.php', 'includes/admin/event-profitability-report.php',
				'includes/core/ticket-sales-resolver.php', 'includes/admin/square-sync-protection.php', 'includes/integrations/square-sync-firewall.php',
				'includes/admin/square-ticket-mirror.php', 'includes/integrations/square-ticket-mirror.php', 'includes/ticketing/ticket-integrity-monitor.php',
				'includes/ticketing/ticket-integrity-checks.php', 'includes/admin/ticket-integrity-page.php', 'includes/ticketing/ticket-legacy-repair.php',
				'includes/ticketing/ticket-integrity-daily-report.php', 'includes/ticketing/ticket-integrity-payment-gateway-health.php',
				'includes/ticketing/ticket-integrity-cron.php', 'includes/ticketing/ticket-integrity-tours.php',
				'includes/ticketing/ticket-inventory-forensics.php', 'includes/ticketing/ticket-mutation-audit.php', 'includes/core/ticket-revenue.php',
			),
			'focused_test_groups' => array('ticket integrity', 'inventory forensics', 'mutation audit', 'repository SQL', 'Square firewall/mirror', 'legacy cleanup', 'ticket revenue/date windows', 'profitability', 'runtime stubs', 'W5 continuity'),
		),
		'W7' => array(
			'scope' => 'Vendor and venue applications, onboarding, registry, taxonomies, and admin',
			'expected' => array('unique_functions' => 419, 'declaration_sites' => 419, 'duplicate_families' => 0, 'declaration_files' => 30),
			'files' => array(
				'includes/admin/venue-duplicate-templates.php', 'includes/admin/venue-duplicate.php', 'includes/admin/vendor-list-ui.php',
				'includes/admin/vendors/tax-columns.php', 'includes/admin/venue-calendar.php', 'includes/admin/vendor-comp-packages.php',
				'includes/core/vendor-user-links.php', 'includes/admin/venue-context.php', 'includes/admin/venue-health-check.php',
				'includes/cpt/venues.php', 'includes/admin/integrity-calendar-reconcile.php', 'includes/admin/integrity-venue-reconcile.php',
				'includes/core/payables.php', 'includes/vendor-applications.php', 'includes/cpt/vendors.php', 'includes/admin/vendor-list-columns.php',
				'includes/admin/vendor-command-center.php', 'includes/admin/vendor-details.php', 'includes/core/vendor-application-confirmation.php',
				'includes/core/vendor-booking-onboarding.php', 'includes/admin/vendor-booking-onboarding.php', 'includes/taxonomies/vendor-category.php',
				'includes/admin/vendors/tax-bulk-actions.php', 'includes/taxonomies/vendor-type.php', 'includes/integrations/vendor-ics-sync.php',
				'includes/admin/vendor-user-link.php', 'includes/core/vendor-document-alerts.php', 'includes/admin/vendors/tax-metabox.php',
				'includes/admin/vendors/tax-export-csv.php', 'includes/admin/vendors/tax-filters.php',
			),
			'focused_test_groups' => array('vendor applications/confirmation/repository', 'booking onboarding', 'vendor-user links', 'vendor type/tax profile', 'vendor ICS', 'venue/calendar integrity', 'payables', 'vendor admin UI'),
		),
		'W8' => array(
			'scope' => 'Portals, availability, calendar, and public vendor profiles',
			'expected' => array('unique_functions' => 524, 'declaration_sites' => 524, 'duplicate_families' => 0, 'declaration_files' => 18),
			'files' => array(
				'includes/modules/availability-date-dispatch/admin-ui.php', 'includes/modules/availability-date-dispatch/helpers.php',
				'includes/modules/availability-date-dispatch/db.php', 'includes/modules/availability-date-dispatch/email.php',
				'includes/modules/availability-date-dispatch/public.php', 'includes/modules/availability-date-dispatch/availability-date-dispatch.php',
				'includes/portal/vendor-portal.php', 'includes/core/calendar-feed.php', 'includes/public/calendar-ics.php',
				'includes/calendar-queries.php', 'includes/public/express-bar.php', 'includes/core/lineup-schedule.php',
				'includes/admin/vendor-availability.php', 'includes/public/venue-calendar-shortcode.php', 'includes/public/event-sidebar.php',
				'includes/portal/staff-portal.php', 'includes/portal/vendor-tax-profile.php', 'includes/public/vendor-profiles.php',
			),
			'focused_test_groups' => array('availability dispatch', 'vendor portal', 'vendor availability UX', 'calendar/ICS', 'event sidebar', 'vendor profiles/B2.5 runtime', 'staff portal'),
		),
		'W9' => array(
			'scope' => 'Staffing, staff tasks, schedule, season, and due dates',
			'expected' => array('unique_functions' => 441, 'declaration_sites' => 445, 'duplicate_families' => 4, 'declaration_files' => 30),
			'files' => array(
				'includes/admin/holidays.php', 'includes/admin/season-dates.php', 'includes/admin/due-dates.php', 'includes/core/due-dates.php',
				'includes/admin/venue-comp-defaults.php', 'includes/schedule/helpers.php', 'includes/admin/schedule.php',
				'includes/admin/season-board.php', 'includes/admin/staff-certifications.php', 'includes/admin/staff-tax-sidebar.php',
				'includes/admin/staff-worker-type.php', 'includes/rest-due-dates.php', 'includes/schedule/schedule.php',
				'includes/schedule/season-dates.php', 'includes/admin/staff-list-columns.php', 'includes/cpt/staff.php',
				'includes/admin/staff-vendor-link.php', 'includes/admin/vendor-staff-link.php', 'includes/modules/staff-tasks/staff-tasks.php',
				'includes/admin/staff-user-link.php', 'includes/core/staffing.php', 'includes/admin/staffing.php',
				'includes/modules/staff-tasks/admin-ui.php', 'includes/modules/staff-tasks/store.php',
				'includes/modules/staff-tasks/generator.php', 'includes/modules/staff-tasks/caps.php', 'includes/modules/staff-tasks/db.php',
				'includes/modules/staff-tasks/notifications.php', 'includes/modules/staff-tasks/settings.php', 'includes/modules/staff-tasks/tours.php',
			),
			'focused_test_groups' => array('staffing', 'staff tasks', 'staff portal', 'certifications', 'schedule output', 'season/due dates', 'staff date windows', 'staffing SQL', 'Event Plan eligibility'),
		),
		'W10' => array(
			'scope' => 'Admissions, pass claims, email, status notices, and public event details',
			'expected' => array('unique_functions' => 428, 'declaration_sites' => 428, 'duplicate_families' => 0, 'declaration_files' => 31),
			'files' => array(
				'includes/modules/admissions/admin-ui.php', 'includes/modules/admissions/admission-tokens.php', 'includes/modules/admissions/admissions.php',
				'includes/modules/admissions/audit.php', 'includes/modules/admissions/caps.php', 'includes/modules/admissions/db.php',
				'includes/modules/admissions/normalize.php', 'includes/modules/admissions/pass-claims.php', 'includes/modules/admissions/rest.php',
				'includes/modules/admissions/shortcodes.php', 'includes/modules/admissions/vendor-guest-portal.php',
				'includes/modules/email-followups/admin-ui.php', 'includes/modules/email-followups/logs.php',
				'includes/modules/email-followups/mailpoet.php', 'includes/modules/email-followups/recipients.php',
				'includes/modules/email-followups/scheduler.php', 'includes/modules/email-followups/sender.php',
				'includes/modules/email-followups/settings.php', 'includes/modules/email-followups/templates.php',
				'includes/public/event-details.php', 'includes/public/events-photo-shortcode.php', 'includes/core/notifications.php',
				'includes/admin/settings/notifications-user-profile.php', 'includes/modules/load.php', 'includes/public/legacy-shortcodes.php',
				'includes/modules/status-notices/admin-ui.php', 'includes/modules/status-notices/cpt.php',
				'includes/modules/status-notices/defaults.php', 'includes/modules/status-notices/front.php',
				'includes/modules/status-notices/status-notices.php', 'includes/modules/status-notices/store.php',
			),
			'focused_test_groups' => array('admissions REST/claims/tokens', 'pass claims', 'email followups', 'cancellation notifications', 'status notices', 'event schema/JSON-LD', 'notifications API', 'shortcodes', 'auth/input/nonce'),
		),
		'W11' => array(
			'scope' => 'Settings, reporting, social, tax bypass, and remaining admin',
			'expected' => array('unique_functions' => 362, 'declaration_sites' => 366, 'duplicate_families' => 4, 'declaration_files' => 23),
			'files' => array(
				'includes/admin/tax-bypass.php', 'includes/admin/approvals-review-queue.php', 'includes/admin/budget-calculator.php',
				'includes/admin/settings-page.php', 'includes/admin/cancelled-event-cost-review.php', 'includes/admin/express-bar.php',
				'includes/core/tax-bypass.php', 'includes/admin/goals-forecast.php', 'includes/core/goals-forecast.php',
				'includes/admin/tax-profile-admin-metabox.php', 'includes/social-share/queue-repo.php',
				'includes/social-share/event-plan-panel.php', 'includes/social-share/admin.php', 'includes/social-share/template-engine.php',
				'includes/social-share/audit.php', 'includes/social-share/context.php', 'includes/social-share/crypto.php',
				'includes/social-share/permissions.php', 'includes/social-share/installer.php', 'includes/social-share/queue-runner.php',
				'includes/social-share/providers/registry.php', 'includes/admin/woo-product-event-columns.php',
				'includes/core/validation/vendor.php',
			),
			'focused_test_groups' => array('settings integrity/default venue', 'goals/reporting', 'budget/payables/profitability', 'social renderer/queue/repository/webhook', 'tax bypass', 'approvals/cancelled costs', 'Woo columns'),
		),
	);

	public static function build(array $map, array $graph): array
	{
		BVMGR_WPORG_Prefix_B3::validateMap($map);
		$mappings = (array) ($map['mappings'] ?? array());
		$assignments = array();

		// W2 is deliberately an exact semantic boundary, not whole-file ownership.
		foreach ($mappings as $entry) {
			if ((array) ($entry['known_addon_consumers'] ?? array()) !== array() || is_string($entry['public_api_family'] ?? null)) {
				$assignments[(string) $entry['legacy_identifier']] = 'W2';
			}
		}

		$fileOwners = array();
		foreach (self::DEFINITIONS as $wave => $definition) {
			if ($wave === 'W2') {
				continue;
			}
			foreach ($definition['files'] as $file) {
				if (isset($fileOwners[$file])) {
					throw new RuntimeException('Non-W2 wave file is assigned twice: ' . $file);
				}
				$fileOwners[$file] = $wave;
			}
		}
		foreach ($mappings as $entry) {
			$legacy = (string) $entry['legacy_identifier'];
			if (isset($assignments[$legacy])) {
				continue;
			}
			$owners = array();
			foreach ((array) $entry['declaration_sites'] as $site) {
				$file = (string) ($site['file'] ?? '');
				if (isset($fileOwners[$file])) {
					$owners[$fileOwners[$file]] = true;
				}
			}
			if (count($owners) !== 1) {
				throw new RuntimeException('B3 function does not resolve to one non-W2 wave: ' . $legacy);
			}
			$assignments[$legacy] = (string) array_key_first($owners);
		}

		$waves = array();
		$functionToWave = array();
		foreach (self::DEFINITIONS as $wave => $definition) {
			$entries = array_values(array_filter($mappings, static fn(array $entry): bool => ($assignments[(string) $entry['legacy_identifier']] ?? '') === $wave));
			$functions = array_column($entries, 'legacy_identifier');
			sort($functions, SORT_STRING);
			$sites = array_sum(array_map(static fn(array $entry): int => count((array) $entry['declaration_sites']), $entries));
			$duplicates = array_values(array_column(array_filter($entries, static fn(array $entry): bool => !empty($entry['duplicate_family'])), 'legacy_identifier'));
			sort($duplicates, SORT_STRING);
			$files = array();
			foreach ($entries as $entry) {
				foreach ((array) $entry['declaration_sites'] as $site) {
					$files[(string) $site['file']] = true;
				}
				$functionToWave[(string) $entry['legacy_identifier']] = $wave;
			}
			$files = array_keys($files);
			sort($files, SORT_STRING);
			$counts = array(
				'unique_functions' => count($entries),
				'declaration_sites' => $sites,
				'duplicate_families' => count($duplicates),
				'declaration_files' => count($files),
			);
			if ($counts !== $definition['expected']) {
				throw new RuntimeException($wave . ' wave totals drifted: ' . json_encode($counts));
			}
			$waves[] = array(
				'wave' => $wave,
				'scope' => $definition['scope'],
				'counts' => $counts,
				'declaration_files' => $files,
				'duplicate_families' => $duplicates,
				'legacy_functions' => $functions,
				'focused_test_groups' => $definition['focused_test_groups'],
			);
		}
		if (count($assignments) !== 4521 || count($functionToWave) !== 4521) {
			throw new RuntimeException('B3 wave assignment does not cover all 4,521 functions exactly once.');
		}

		$crossWave = array();
		foreach ((array) ($graph['edges'] ?? array()) as $edge) {
			$from = $functionToWave[(string) ($edge['caller'] ?? '')] ?? '';
			$to = $functionToWave[(string) ($edge['callee'] ?? '')] ?? '';
			if ($from === '' || $to === '' || $from === $to) {
				continue;
			}
			$key = $from . '->' . $to . ':' . (string) ($edge['kind'] ?? '');
			if (!isset($crossWave[$key])) {
				$crossWave[$key] = array('caller_wave' => $from, 'callee_wave' => $to, 'kind' => (string) ($edge['kind'] ?? ''), 'edge_count' => 0, 'occurrences' => 0);
			}
			$crossWave[$key]['edge_count']++;
			$crossWave[$key]['occurrences'] += (int) ($edge['occurrences'] ?? 0);
		}
		ksort($crossWave, SORT_STRING);

		return array(
			'schema_version' => 1,
			'artifact' => 'wporg-prefix-b3-waves',
			'execution_order' => array_keys(self::DEFINITIONS),
			'function_map_sha256' => hash('sha256', BVMGR_WPORG_Prefix_B3::render($map)),
			'dependency_graph_sha256' => hash('sha256', BVMGR_WPORG_Prefix_B3::render($graph)),
			'invariants' => array(
				'exactly_once_assignment' => true,
				'max_unique_functions_per_wave' => 650,
				'duplicate_families_crossing_waves' => 0,
				'w2_selection' => 'exact union of known-add-on-consumed functions and public-extension function APIs',
				'global_reference_cutover' => 'each wave updates all frozen direct-call and exact-function-literal references to selected symbols across public PHP',
			),
			'counts' => array('waves' => count($waves), 'unique_functions' => 4521, 'declaration_sites' => 4541, 'duplicate_families' => 20),
			'waves' => $waves,
			'cross_wave_dependencies' => array_values($crossWave),
		);
	}
}
