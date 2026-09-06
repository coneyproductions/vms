<?php
/**
 * Provider → policy → diagnostics → formatter orchestration.
 */

namespace ConeyProductions\BackstageCalendarFeeds;

final class Feed_Service {
	/** @var Provider_Interface */
	private $provider;
	/** @var Strict_Availability_Policy */
	private $policy;
	/** @var Supersession_Resolver */
	private $supersession;
	/** @var Publication_Ledger */
	private $publication;
	/** @var Duplicate_Detector */
	private $duplicates;
	/** @var ICS_Formatter */
	private $formatter;

	public function __construct( Provider_Interface $provider, Strict_Availability_Policy $policy, Supersession_Resolver $supersession, Publication_Ledger $publication, Duplicate_Detector $duplicates, ICS_Formatter $formatter ) {
		$this->provider   = $provider;
		$this->policy     = $policy;
		$this->supersession = $supersession;
		$this->publication = $publication;
		$this->duplicates = $duplicates;
		$this->formatter  = $formatter;
	}

	/**
	 * Build a safe feed and diagnostics.
	 *
	 * @param array<string,mixed> $profile Feed profile.
	 * @param int|null            $now     Timestamp for tests.
	 * @param bool                $record_publication Record history only when serving the capability feed.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function build( $profile, $now = null, $record_publication = false ) {
		$health = $this->provider->health();
		if ( is_wp_error( $health ) ) {
			return $health;
		}
		$provider_occurrences = $this->provider->fetch_occurrences();
		if ( is_wp_error( $provider_occurrences ) ) {
			return $provider_occurrences;
		}

		$clock          = null === $now ? time() : (int) $now;
		$retention_days = max( 1, (int) ( $profile['cancellation_retention_days'] ?? 45 ) );
		$projected      = array();
		foreach ( $provider_occurrences as $occurrence ) {
			$event = $this->policy->project( $occurrence, $profile );
			if ( null === $event ) {
				continue;
			}
			$is_cancelled = 'CANCELLED' === (string) $event['status'];
			$cutoff       = $is_cancelled ? $clock - ( $retention_days * DAY_IN_SECONDS ) : $clock;
			if ( (int) $event['end']['timestamp'] < $cutoff ) {
				continue;
			}
			$projected[ (string) $occurrence['occurrence_identity'] ] = $event;
		}
		$resolution = $this->supersession->resolve( $provider_occurrences, $projected );
		$publication = $this->publication->filter( $profile, $resolution['events'], $clock, $record_publication );
		if ( is_wp_error( $publication ) ) {
			return $publication;
		}
		$events = $publication['events'];

		usort(
			$events,
			static function ( $left, $right ) {
				$time = (int) $left['start']['timestamp'] <=> (int) $right['start']['timestamp'];
				return 0 !== $time ? $time : strcmp( (string) $left['uid'], (string) $right['uid'] );
			}
		);
		$diagnostics = $this->duplicates->diagnose( $events );
		$events      = $diagnostics['events'];

		$stats = array(
			'upcoming'                    => 0,
			'privacy_sanitized'           => 0,
			'cancellations'               => 0,
			'source_missing'              => 0,
			'possible_duplicate_groups'   => (int) $diagnostics['groups'],
			'possible_duplicate_events'   => (int) $diagnostics['occurrences'],
			'unresolved_possible_migration_duplicates' => (int) $diagnostics['groups'],
			'superseded_occurrences_suppressed' => (int) $resolution['counts']['suppressed'],
			'supersession_targets_missing' => (int) $resolution['counts']['targets_missing'],
			'supersession_targets_not_available' => (int) $resolution['counts']['targets_unavailable'],
			'malformed_supersession_references' => (int) $resolution['counts']['malformed_references'],
			'supersession_cycles'         => (int) $resolution['counts']['cycles'],
			'historical_cancellations_omitted' => (int) $publication['counts']['historical_cancellations_omitted'],
			'previously_emitted_cancellations_omitted' => (int) $publication['counts']['previously_emitted_cancellations_omitted'],
			'cancellation_tombstones_emitted' => (int) $publication['counts']['cancellation_tombstones_emitted'],
			'publication_history_entries' => (int) $publication['history_entries'],
			'source_lineages_excluded'    => (int) ( $health['excluded_source_count'] ?? 0 ),
		);
		foreach ( $events as $event ) {
			if ( 'CANCELLED' === (string) $event['status'] ) {
				++$stats['cancellations'];
			} elseif ( (int) $event['end']['timestamp'] >= $clock ) {
				++$stats['upcoming'];
			}
			if ( 'sanitized' === (string) $event['privacy_mode'] ) {
				++$stats['privacy_sanitized'];
			}
			if ( 'source_missing' === (string) $event['source_state'] ) {
				++$stats['source_missing'];
			}
		}

		return array(
			'provider_health' => $health,
			'feed_health'     => 'available',
			'occurrences'     => $events,
			'supersession_diagnostics' => $resolution['diagnostics'],
			'publication_diagnostics' => $publication['diagnostics'],
			'stats'           => $stats,
			'ics'             => $this->formatter->render( $events, (string) $profile['label'], $clock ),
		);
	}
}
