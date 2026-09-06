<?php
/**
 * Isolated Backstage Calendar Feeds probe suite.
 *
 * Run with: php tests/run.php
 */

namespace {
	if ( ! defined( 'DAY_IN_SECONDS' ) ) {
		define( 'DAY_IN_SECONDS', 86400 );
	}

	if ( ! class_exists( 'WP_Error' ) ) {
		class WP_Error {
			private $code;
			private $message;
			public function __construct( $code = '', $message = '' ) {
				$this->code = $code;
				$this->message = $message;
			}
			public function get_error_code() {
				return $this->code;
			}
			public function get_error_message() {
				return $this->message;
			}
		}
	}

	if ( ! function_exists( 'is_wp_error' ) ) {
		function is_wp_error( $value ) {
			return $value instanceof WP_Error;
		}
	}

	$GLOBALS['bcf_probe_options'] = array();
	if ( ! function_exists( 'get_option' ) ) {
		function get_option( $name, $default = false ) {
			return array_key_exists( $name, $GLOBALS['bcf_probe_options'] ) ? $GLOBALS['bcf_probe_options'][ $name ] : $default;
		}
	}
	if ( ! function_exists( 'update_option' ) ) {
		function update_option( $name, $value, $autoload = null ) {
			$changed = ! array_key_exists( $name, $GLOBALS['bcf_probe_options'] ) || $GLOBALS['bcf_probe_options'][ $name ] !== $value;
			$GLOBALS['bcf_probe_options'][ $name ] = $value;
			return $changed;
		}
	}
	if ( ! function_exists( 'add_option' ) ) {
		function add_option( $name, $value, $deprecated = '', $autoload = 'yes' ) {
			if ( array_key_exists( $name, $GLOBALS['bcf_probe_options'] ) ) {
				return false;
			}
			$GLOBALS['bcf_probe_options'][ $name ] = $value;
			return true;
		}
	}
	if ( ! function_exists( 'delete_option' ) ) {
		function delete_option( $name ) {
			if ( ! array_key_exists( $name, $GLOBALS['bcf_probe_options'] ) ) {
				return false;
			}
			unset( $GLOBALS['bcf_probe_options'][ $name ] );
			return true;
		}
	}
}

namespace ConeyProductions\BackstageCalendarFeeds {
	require_once dirname( __DIR__ ) . '/includes/provider-interface.php';
	require_once dirname( __DIR__ ) . '/includes/profile.php';
	require_once dirname( __DIR__ ) . '/includes/class-drm-calendar-intake-provider.php';
	require_once dirname( __DIR__ ) . '/includes/class-strict-availability-policy.php';
	require_once dirname( __DIR__ ) . '/includes/class-supersession-resolver.php';
	require_once dirname( __DIR__ ) . '/includes/class-publication-ledger.php';
	require_once dirname( __DIR__ ) . '/includes/class-duplicate-detector.php';
	require_once dirname( __DIR__ ) . '/includes/class-ics-formatter.php';
	require_once dirname( __DIR__ ) . '/includes/class-feed-service.php';
	require_once dirname( __DIR__ ) . '/includes/class-secret-store.php';
	require_once dirname( __DIR__ ) . '/includes/class-admin-authorization.php';

	$assertions = 0;

	function bcf_probe_assert( $condition, $message ) {
		global $assertions;
		++$assertions;
		if ( ! $condition ) {
			fwrite( STDERR, "FAIL: {$message}\n" );
			exit( 1 );
		}
	}

	function bcf_probe_time( $local, $timezone = 'America/Chicago' ) {
		$point = new \DateTimeImmutable( $local, new \DateTimeZone( $timezone ) );
		return array(
			'kind'      => 'dateTime',
			'value'     => $point->format( 'c' ),
			'timezone'  => $timezone,
			'timestamp' => $point->getTimestamp(),
		);
	}

	function bcf_probe_occurrence( $overrides = array() ) {
		$defaults = array(
			'provider_id'          => 'fixture',
			'occurrence_identity'  => hash( 'sha256', 'fixture-occurrence' ),
			'source_ref'           => hash( 'sha256', 'fixture-source-a' ),
			'source_fingerprint'   => hash( 'sha256', 'fixture-material' ),
			'source_label'         => 'Fixture source',
			'source_policy'        => 'trusted_performance',
			'superseded'           => false,
			'superseded_by_occurrence_ref' => '',
			'supersession_reference_valid' => true,
			'act_slug'             => 'texanadian',
			'classification'       => 'public_performance',
			'classification_provenance' => 'manual',
			'review_state'         => 'reviewed',
			'source_state'         => 'active',
			'google_status'        => 'confirmed',
			'visibility'           => 'default',
			'summary'              => "Texanadian, Live; Backslash \\ Test\nEncore",
			'venue_name'           => "Troy's at Texas Live!",
			'city'                 => 'Arlington',
			'state'                => 'TX',
			'start'                => bcf_probe_time( '2026-08-20 20:00:00' ),
			'end'                  => bcf_probe_time( '2026-08-20 22:00:00' ),
			'all_day'              => false,
			'recurring_occurrence' => false,
		);
		return array_replace( $defaults, $overrides );
	}

	function bcf_probe_contract_record( $source_ref, $overrides = array() ) {
		$occurrence = bcf_probe_occurrence();
		$defaults   = array(
			'contract_version'   => 2,
			'occurrence_key'     => $occurrence['occurrence_identity'],
			'superseded'         => false,
			'superseded_by_occurrence_ref' => '',
			'source_ref'         => $source_ref,
			'source_fingerprint' => $occurrence['source_fingerprint'],
			'act_slug'           => $occurrence['act_slug'],
			'classification'     => $occurrence['classification'],
			'classification_provenance' => $occurrence['classification_provenance'],
			'review_state'       => $occurrence['review_state'],
			'source_state'       => $occurrence['source_state'],
			'google_status'      => $occurrence['google_status'],
			'visibility'         => $occurrence['visibility'],
			'summary'            => $occurrence['summary'],
			'venue_name'         => $occurrence['venue_name'],
			'city'               => $occurrence['city'],
			'state'              => $occurrence['state'],
			'start'              => $occurrence['start'],
			'end'                => $occurrence['end'],
			'all_day'            => $occurrence['all_day'],
			'recurring'          => $occurrence['recurring_occurrence'],
		);
		return array_replace( $defaults, $overrides );
	}

	final class BCF_Probe_Provider implements Provider_Interface {
		/** @var array<int,array<string,mixed>> */
		private $occurrences;
		public function __construct( $occurrences ) {
			$this->occurrences = $occurrences;
		}
		public function get_id() {
			return 'probe';
		}
		public function health() {
			return array( 'available' => true, 'provider' => 'probe', 'contract_version' => 2 );
		}
		public function fetch_occurrences() {
			return $this->occurrences;
		}
	}

	function bcf_probe_service( $occurrences ) {
		return new Feed_Service(
			new BCF_Probe_Provider( $occurrences ),
			new Strict_Availability_Policy(),
			new Supersession_Resolver(),
			new Publication_Ledger(),
			new Duplicate_Detector(),
			new ICS_Formatter()
		);
	}

	function bcf_probe_build( $occurrences, $record_publication = false, $clock = 1770000000 ) {
		$service = bcf_probe_service( $occurrences );
		return $service->build( bcf_get_profile( 'dalene-band-availability' ), $clock, $record_publication );
	}

	function bcf_probe_supersession_pair( $successor_overrides = array(), $predecessor_overrides = array() ) {
		$successor_identity = hash( 'sha256', 'supersession-successor' . serialize( $successor_overrides ) );
		$predecessor = bcf_probe_occurrence(
			array_replace(
				array(
					'occurrence_identity' => hash( 'sha256', 'supersession-predecessor' . serialize( $predecessor_overrides ) ),
					'source_ref'          => hash( 'sha256', 'supersession-source-predecessor' ),
					'source_label'        => 'Existing mixed calendar',
					'source_policy'       => 'mixed_review',
					'act_slug'            => '',
					'classification'      => 'unknown',
					'review_state'        => 'needs_review',
					'superseded'          => true,
					'superseded_by_occurrence_ref' => $successor_identity,
				),
				$predecessor_overrides
			)
		);
		$successor = bcf_probe_occurrence(
			array_replace(
				array(
					'occurrence_identity' => $successor_identity,
					'source_ref'          => hash( 'sha256', 'supersession-source-successor' ),
					'source_label'        => 'Texanadian',
					'source_policy'       => 'trusted_performance',
					'summary'             => "Troy's public show",
				),
				$successor_overrides
			)
		);
		return array( $predecessor, $successor );
	}

	$profile   = bcf_get_profile( 'dalene-band-availability' );
	$policy    = new Strict_Availability_Policy();
	$formatter = new ICS_Formatter();
	$public    = $policy->project( bcf_probe_occurrence(), $profile );
	$ics       = $formatter->render( array( $public ), 'Availability, Test', 1770000000 );

	bcf_probe_assert( bcf_probe_occurrence()['summary'] === $public['summary'], 'reviewed public performance summary remains unchanged' );
	bcf_probe_assert( false !== strpos( $ics, 'SUMMARY:Texanadian\\, Live\\; Backslash \\\\ Test\\nEncore' ), 'ICS text escaping covers comma, semicolon, backslash, and newline' );
	bcf_probe_assert( false !== strpos( $ics, 'X-WR-CALNAME:Availability\\, Test' ), 'calendar name uses ICS text escaping' );
	bcf_probe_assert( false === strpos( str_replace( "\r\n", '', $ics ), "\n" ) && false === strpos( str_replace( "\r\n", '', $ics ), "\r" ), 'ICS uses CRLF exclusively' );
	bcf_probe_assert( false !== strpos( $ics, 'DTSTART:20260821T010000Z' ) && false !== strpos( $ics, 'DTEND:20260821T030000Z' ), 'timed America/Chicago event preserves the correct UTC instants' );
	bcf_probe_assert( false !== strpos( $ics, 'TRANSP:OPAQUE' ) && false !== strpos( $ics, 'STATUS:CONFIRMED' ), 'busy confirmed semantics are explicit' );

	$long_event            = $public;
	$long_event['summary'] = str_repeat( 'Dālene ', 30 );
	$folded_ics            = $formatter->render( array( $long_event ), 'Fold Test', 1770000000 );
	foreach ( explode( "\r\n", trim( $folded_ics ) ) as $physical_line ) {
		bcf_probe_assert( strlen( $physical_line ) <= 75, 'folded ICS physical line does not exceed 75 octets' );
	}
	bcf_probe_assert( false !== strpos( $folded_ics, "\r\n " ), 'long ICS property uses continuation folding' );

	$all_day = $policy->project(
		bcf_probe_occurrence(
			array(
				'occurrence_identity' => hash( 'sha256', 'all-day' ),
				'start'               => array( 'kind' => 'date', 'value' => '2026-09-01', 'timezone' => 'America/Chicago', 'timestamp' => 1788238800 ),
				'end'                 => array( 'kind' => 'date', 'value' => '2026-09-02', 'timezone' => 'America/Chicago', 'timestamp' => 1788325200 ),
				'all_day'             => true,
			)
		),
		$profile
	);
	$all_day_ics = $formatter->render( array( $all_day ), 'All Day', 1770000000 );
	bcf_probe_assert( false !== strpos( $all_day_ics, 'DTSTART;VALUE=DATE:20260901' ) && false !== strpos( $all_day_ics, 'DTEND;VALUE=DATE:20260902' ), 'all-day event uses exclusive DATE endpoints' );

	$dst = $policy->project(
		bcf_probe_occurrence(
			array(
				'occurrence_identity' => hash( 'sha256', 'dst' ),
				'start'               => bcf_probe_time( '2026-03-08 01:30:00' ),
				'end'                 => bcf_probe_time( '2026-03-08 03:30:00' ),
			)
		),
		$profile
	);
	$dst_ics = $formatter->render( array( $dst ), 'DST', 1770000000 );
	bcf_probe_assert( false !== strpos( $dst_ics, 'DTSTART:20260308T073000Z' ) && false !== strpos( $dst_ics, 'DTEND:20260308T083000Z' ), 'DST transition retains correct instants' );

	$cross_midnight = $policy->project(
		bcf_probe_occurrence(
			array(
				'occurrence_identity' => hash( 'sha256', 'cross-midnight' ),
				'start'               => bcf_probe_time( '2026-08-20 23:00:00' ),
				'end'                 => bcf_probe_time( '2026-08-21 01:00:00' ),
			)
		),
		$profile
	);
	$cross_ics = $formatter->render( array( $cross_midnight ), 'Cross Midnight', 1770000000 );
	bcf_probe_assert( false !== strpos( $cross_ics, 'DTSTART:20260821T040000Z' ) && false !== strpos( $cross_ics, 'DTEND:20260821T060000Z' ), 'cross-midnight event preserves its date boundary' );

	$rescheduled = $policy->project( bcf_probe_occurrence( array( 'start' => bcf_probe_time( '2026-08-21 20:00:00' ), 'end' => bcf_probe_time( '2026-08-21 22:00:00' ) ) ), $profile );
	bcf_probe_assert( $public['uid'] === $rescheduled['uid'], 'stable UID survives a start-time change' );
	bcf_probe_assert( false === strpos( $public['uid'], bcf_probe_occurrence()['occurrence_identity'] ), 'raw occurrence identity is not emitted as UID' );

	$private = $policy->project(
		bcf_probe_occurrence(
			array(
				'classification' => 'private_performance',
				'summary'        => 'Private Client Name',
				'venue_name'     => 'Private Home Address',
				'city'           => 'Secret City',
			)
		),
		$profile
	);
	$private_ics = $formatter->render( array( $private ), 'Private', 1770000000 );
	bcf_probe_assert( 'Private Event — Texanadian' === $private['summary'] && '' === $private['location'] && 'sanitized' === $private['privacy_mode'], 'private occurrence uses conservative presentation' );
	bcf_probe_assert( false === strpos( $private_ics, 'Private Client Name' ) && false === strpos( $private_ics, 'Private Home Address' ) && false === strpos( $private_ics, 'Secret City' ), 'private source values do not enter ICS' );

	bcf_probe_assert( null === $policy->project( bcf_probe_occurrence( array( 'classification' => 'ignored' ) ), $profile ), 'ignored classification is omitted' );

	$tentative = $policy->project( bcf_probe_occurrence( array( 'google_status' => 'tentative' ) ), $profile );
	bcf_probe_assert( 'TENTATIVE' === $tentative['status'] && 'Tentative' === $tentative['feed_state'] && 'OPAQUE' === $tentative['transparency'] && $public['summary'] === $tentative['summary'], 'tentative occurrence retains its safe summary and remains busy with valid tentative status' );

	$cancelled = $policy->project( bcf_probe_occurrence( array( 'source_state' => 'cancelled', 'google_status' => 'cancelled' ) ), $profile );
	bcf_probe_assert( 'CANCELLED' === $cancelled['status'] && $public['uid'] === $cancelled['uid'] && 'explicit_provider_cancellation' === $cancelled['inclusion_reason'], 'explicit cancellation produces a same-UID tombstone' );
	bcf_probe_assert( false !== strpos( $formatter->render( array( $cancelled ), 'Cancelled', 1770000000 ), 'STATUS:CANCELLED' ), 'cancellation status renders in ICS' );

	/* Snapshot publication history: cancellation projections never become new snapshot VEVENTs. */
	$GLOBALS['bcf_probe_options'] = array();
	$transition_identity = hash( 'sha256', 'publication-transition' );
	$transition_active   = bcf_probe_occurrence( array( 'occurrence_identity' => $transition_identity, 'summary' => 'Private transition source text' ) );
	$first_publication   = bcf_probe_build( array( $transition_active ), true, 1770000000 );
	bcf_probe_assert( 1 === count( $first_publication['occurrences'] ) && 1 === $first_publication['stats']['publication_history_entries'], 'active occurrence is emitted and recorded in bounded publication history' );
	$stored_history = get_option( Publication_Ledger::OPTION_NAME, array() );
	$history_json   = json_encode( $stored_history );
	bcf_probe_assert( false === strpos( $history_json, $transition_identity ) && false === strpos( $history_json, 'Private transition source text' ), 'publication ledger stores neither raw provider identity nor source summary' );
	$transition_cancelled = bcf_probe_occurrence(
		array(
			'occurrence_identity' => $transition_identity,
			'source_state'        => 'cancelled',
			'google_status'       => 'cancelled',
		)
	);
	$later_cancellation = bcf_probe_build( array( $transition_cancelled ), true, 1770003600 );
	bcf_probe_assert( 0 === count( $later_cancellation['occurrences'] ) && 0 === substr_count( $later_cancellation['ics'], 'BEGIN:VEVENT' ), 'previously emitted occurrence is removed when explicitly cancelled' );
	bcf_probe_assert( 1 === $later_cancellation['stats']['previously_emitted_cancellations_omitted'] && 'previously_emitted_cancellation_omitted' === $later_cancellation['publication_diagnostics'][0]['reason'], 'prior BUSY publication is distinguished from historical cancellation' );
	$transition_cancelled_projection = $policy->project( $transition_cancelled, $profile );
	bcf_probe_assert( Strict_Availability_Policy::uid_for_identity( $transition_identity ) === $transition_cancelled_projection['uid'], 'cancellation transition retains stable derived UID before snapshot omission' );

	$never_identity = hash( 'sha256', 'never-published-cancellation' );
	$never_cancelled = bcf_probe_occurrence( array( 'occurrence_identity' => $never_identity, 'source_state' => 'cancelled', 'google_status' => 'cancelled' ) );
	$never_result = bcf_probe_build( array( $never_cancelled ), false, 1770003600 );
	bcf_probe_assert( 0 === count( $never_result['occurrences'] ) && 1 === $never_result['stats']['historical_cancellations_omitted'] && 'historical_cancellation_omitted' === $never_result['publication_diagnostics'][0]['reason'], 'never-emitted historical cancellation is omitted rather than introduced' );

	$GLOBALS['bcf_probe_options'] = array();
	$old_history_event = bcf_probe_occurrence( array( 'occurrence_identity' => hash( 'sha256', 'old-history-entry' ) ) );
	bcf_probe_build( array( $old_history_event ), true, 1770000000 );
	$new_history_event = bcf_probe_occurrence( array( 'occurrence_identity' => hash( 'sha256', 'new-history-entry' ) ) );
	$cleaned_history = bcf_probe_build( array( $new_history_event ), true, 1770000000 + ( 91 * DAY_IN_SECONDS ) );
	bcf_probe_assert( 1 === $cleaned_history['stats']['publication_history_entries'], 'publication ledger removes entries outside its bounded retention window' );

	$GLOBALS['bcf_probe_options'] = array( Publication_Ledger::OPTION_NAME => array( 'schema_version' => 999, 'profiles' => array() ) );
	$corrupt_history = bcf_probe_build( array( $transition_active ) );
	bcf_probe_assert( is_wp_error( $corrupt_history ) && 'bcf_publication_ledger_corrupt' === $corrupt_history->get_error_code(), 'corrupt publication history fails the feed closed' );

	$GLOBALS['bcf_probe_options'] = array();
	$lost_history_result = bcf_probe_build(
		array(
			$never_cancelled,
			bcf_probe_occurrence( array( 'occurrence_identity' => hash( 'sha256', 'lost-ledger-source-missing' ), 'source_state' => 'source_missing' ) ),
		)
	);
	bcf_probe_assert( 1 === count( $lost_history_result['occurrences'] ) && 'source_missing' === $lost_history_result['occurrences'][0]['source_state'] && 1 === $lost_history_result['stats']['historical_cancellations_omitted'], 'missing ledger cannot suppress source_missing BUSY and treats cancellation history as unknown' );

	$GLOBALS['bcf_probe_options'] = array( Publication_Ledger::LOCK_NAME => array( 'owner' => 'another-request', 'expires_at' => time() + 120 ) );
	$locked_history = bcf_probe_build( array( $transition_active ), true, 1770000000 );
	bcf_probe_assert( is_wp_error( $locked_history ) && 'bcf_publication_ledger_locked' === $locked_history->get_error_code(), 'concurrent publication-history write fails closed instead of racing' );
	$GLOBALS['bcf_probe_options'] = array();

	$presentation_identity = hash( 'sha256', 'presentation-only-identity' );
	$missing_occurrence    = bcf_probe_occurrence(
		array(
			'occurrence_identity' => $presentation_identity,
			'source_state'        => 'source_missing',
			'review_state'        => 'needs_review',
			'summary'             => 'Migration Candidate — Review Required',
		)
	);
	$missing = $policy->project( $missing_occurrence, $profile );
	bcf_probe_assert( 'CONFIRMED' === $missing['status'] && 'Busy' === $missing['feed_state'] && 'OPAQUE' === $missing['transparency'], 'source_missing remains busy and opaque' );
	bcf_probe_assert( 'Unavailable' === $missing['summary'], 'source_missing external summary is availability-only' );
	bcf_probe_assert( 'source_missing' === $missing['warning'] && 'source_missing' === $missing['source_state'], 'source_missing warning and source state remain available internally' );
	bcf_probe_assert( 'needs_review' === $missing['review_state'] && 'source_missing_remains_busy' === $missing['inclusion_reason'] && 'sanitized' === $missing['privacy_mode'], 'source_missing review, inclusion, and privacy diagnostics remain available internally' );

	$unknown_occurrence = bcf_probe_occurrence(
		array(
			'occurrence_identity' => hash( 'sha256', 'unknown-active-classification' ),
			'classification'      => 'unknown',
			'review_state'        => 'needs_review',
			'summary'             => 'Supersession Target — Migration Candidate',
		)
	);
	$unknown = $policy->project( $unknown_occurrence, $profile );
	bcf_probe_assert( 'CONFIRMED' === $unknown['status'] && 'Busy' === $unknown['feed_state'] && 'OPAQUE' === $unknown['transparency'], 'unknown active classification remains busy and opaque' );
	bcf_probe_assert( 'Unavailable' === $unknown['summary'], 'unknown active classification external summary is availability-only' );
	bcf_probe_assert( 'unknown' === $unknown['source_classification'] && 'needs_review' === $unknown['review_state'] && 'strict_availability_included' === $unknown['inclusion_reason'], 'unknown classification and unresolved review remain available internally' );

	$unknown_state = $policy->project(
		bcf_probe_occurrence(
			array(
				'occurrence_identity' => hash( 'sha256', 'unknown-source-state' ),
				'source_state'        => 'unknown',
				'review_state'        => 'needs_review',
			)
		),
		$profile
	);
	bcf_probe_assert( 'Unavailable' === $unknown_state['summary'] && 'CONFIRMED' === $unknown_state['status'] && 'OPAQUE' === $unknown_state['transparency'], 'unknown source state remains busy with availability-only presentation' );
	bcf_probe_assert( 'unknown' === $unknown_state['source_state'] && 'needs_review' === $unknown_state['review_state'], 'unknown source state diagnostics remain available internally' );

	$reviewed_same_identity = $policy->project(
		array_replace(
			$missing_occurrence,
			array(
				'source_state'   => 'active',
				'review_state'   => 'reviewed',
				'classification' => 'public_performance',
				'summary'        => 'Reviewed public title',
			)
		),
		$profile
	);
	bcf_probe_assert( $missing['uid'] === $reviewed_same_identity['uid'] && $missing['summary'] !== $reviewed_same_identity['summary'], 'presentation wording changes do not change occurrence UID' );

	$diagnostic_ics = $formatter->render( array( $missing, $unknown, $unknown_state ), 'Diagnostic Hygiene', 1770000000 );
	bcf_probe_assert( false === strpos( $diagnostic_ics, 'Review Required' ), 'ICS omits internal review-required wording' );
	bcf_probe_assert( false === strpos( $diagnostic_ics, 'source_missing' ), 'ICS omits the raw source_missing warning code' );
	bcf_probe_assert( false === strpos( $diagnostic_ics, 'Supersession Target' ) && false === strpos( $diagnostic_ics, 'Migration Candidate' ), 'ICS omits internal supersession and migration wording from unresolved records' );

	$hold     = $policy->project( bcf_probe_occurrence( array( 'classification' => 'hold' ) ), $profile );
	$personal = $policy->project( bcf_probe_occurrence( array( 'classification' => 'personal' ) ), $profile );
	$media    = $policy->project( bcf_probe_occurrence( array( 'classification' => 'media' ) ), $profile );
	bcf_probe_assert( 'Hold — Unavailable' === $hold['summary'], 'hold presentation remains unchanged' );
	bcf_probe_assert( 'Unavailable' === $personal['summary'], 'personal-conflict presentation remains unchanged' );
	bcf_probe_assert( 'Media — Texanadian' === $media['summary'], 'media presentation remains unchanged' );

	$GLOBALS['bcf_probe_options'] = array();
	$legacy_missing               = $missing;
	$legacy_missing['summary']    = 'Unavailable — Review Required';
	$presentation_ledger          = new Publication_Ledger();
	$legacy_publication           = $presentation_ledger->filter( $profile, array( $legacy_missing ), 1770000000, true );
	$legacy_state                 = get_option( Publication_Ledger::OPTION_NAME, array() );
	$legacy_entry                 = $legacy_state['profiles'][ $profile['id'] ]['entries'][ $missing['uid'] ];
	$current_publication          = $presentation_ledger->filter( $profile, array( $missing ), 1770003600, true );
	$current_state                = get_option( Publication_Ledger::OPTION_NAME, array() );
	$current_entries              = $current_state['profiles'][ $profile['id'] ]['entries'];
	$current_entry                = $current_entries[ $missing['uid'] ];
	bcf_probe_assert( ! is_wp_error( $legacy_publication ) && ! is_wp_error( $current_publication ), 'publication ledger remains healthy across a presentation-only update' );
	bcf_probe_assert( 1 === count( $current_entries ) && isset( $current_entries[ $missing['uid'] ] ), 'presentation-only update retains one ledger entry under the existing UID' );
	bcf_probe_assert( $legacy_entry['first_emitted_at'] === $current_entry['first_emitted_at'] && 'CONFIRMED' === $current_entry['last_emitted_status'], 'presentation-only update retains first publication and busy status' );
	bcf_probe_assert( $legacy_entry['projection_fingerprint'] !== $current_entry['projection_fingerprint'], 'presentation-only update records a new projection fingerprint normally' );
	bcf_probe_assert( 1 === count( $current_publication['events'] ) && empty( $current_publication['diagnostics'] ) && 0 === array_sum( $current_publication['counts'] ), 'presentation-only update infers neither cancellation nor removal' );
	$GLOBALS['bcf_probe_options'] = array();

	$unavailable_provider = new DRM_Calendar_Intake_Provider();
	bcf_probe_assert( is_wp_error( $unavailable_provider->health() ) && 'bcf_provider_unavailable' === $unavailable_provider->health()->get_error_code(), 'absent provider fails closed without a fatal' );
	$wrong_version_provider = new DRM_Calendar_Intake_Provider( static function () { return 1; }, static function () { return array(); }, static function () { return 1; }, static function () { return array(); } );
	bcf_probe_assert( is_wp_error( $wrong_version_provider->health() ) && 'bcf_provider_contract_incompatible' === $wrong_version_provider->health()->get_error_code(), 'wrong Intake contract version fails closed' );

	$enabled_ref  = hash( 'sha256', 'enabled-source' );
	$disabled_ref = hash( 'sha256', 'disabled-source' );
	$archived_ref = hash( 'sha256', 'archived-source' );
	$source_rows  = array(
		array( 'source_ref' => $enabled_ref, 'label' => 'Enabled', 'policy' => 'trusted_performance', 'act_slug' => 'texanadian', 'enabled' => true ),
		array( 'source_ref' => $disabled_ref, 'label' => 'Disabled', 'policy' => 'act_only', 'act_slug' => 'the-crows', 'enabled' => false ),
		array( 'source_ref' => $archived_ref, 'label' => 'Archived', 'policy' => 'mixed_review', 'act_slug' => '', 'enabled' => false ),
	);
	$selected_refs = null;
	$source_provider = new DRM_Calendar_Intake_Provider(
		static function () { return 2; },
		static function ( $page, $limit, $refs ) use ( &$selected_refs, $enabled_ref ) {
			$selected_refs = $refs;
			return array( 'records' => array( bcf_probe_contract_record( $enabled_ref ) ), 'has_more' => false );
		},
		static function () { return 1; },
		static function () use ( $source_rows ) { return $source_rows; }
	);
	$source_health      = $source_provider->health();
	$source_occurrences = $source_provider->fetch_occurrences();
	bcf_probe_assert( ! is_wp_error( $source_health ) && 1 === $source_health['enabled_source_count'] && 2 === $source_health['excluded_source_count'], 'enabled and excluded source discovery counts are reflected in provider health' );
	bcf_probe_assert( is_array( $source_occurrences ) && 1 === count( $source_occurrences ) && $enabled_ref === $source_occurrences[0]['source_ref'], 'enabled source occurrence is included' );
	bcf_probe_assert( 'Enabled' === $source_occurrences[0]['source_label'] && 'trusted_performance' === $source_occurrences[0]['source_policy'] && false === $source_occurrences[0]['superseded'] && true === $source_occurrences[0]['supersession_reference_valid'], 'provider attaches safe source metadata and accepts additive unsuperseded contract fields' );
	bcf_probe_assert( array( $enabled_ref ) === $selected_refs && ! in_array( $disabled_ref, $selected_refs, true ), 'disabled source is excluded from the occurrence query' );
	bcf_probe_assert( ! in_array( $archived_ref, $selected_refs, true ), 'replaced/archived source is excluded from the occurrence query' );
	$duplicate_storage_provider = new DRM_Calendar_Intake_Provider(
		static function () { return 2; },
		static function ( $page, $limit, $refs ) use ( $enabled_ref ) {
			$record = bcf_probe_contract_record( $enabled_ref );
			return array( 'records' => array( $record, $record ), 'has_more' => false );
		},
		static function () { return 1; },
		static function () use ( $enabled_ref ) { return array( array( 'source_ref' => $enabled_ref, 'label' => 'Enabled', 'policy' => 'trusted_performance', 'act_slug' => 'texanadian', 'enabled' => true ) ); }
	);
	$duplicate_storage = $duplicate_storage_provider->fetch_occurrences();
	bcf_probe_assert( is_wp_error( $duplicate_storage ) && 'bcf_provider_duplicate_storage' === $duplicate_storage->get_error_code(), 'duplicate provider occurrence storage fails closed with a specific diagnostic' );

	$empty_query_called = false;
	$empty_provider = new DRM_Calendar_Intake_Provider(
		static function () { return 2; },
		static function () use ( &$empty_query_called ) { $empty_query_called = true; return array(); },
		static function () { return 1; },
		static function () use ( $disabled_ref ) { return array( array( 'source_ref' => $disabled_ref, 'label' => 'Disabled', 'policy' => 'act_only', 'act_slug' => '', 'enabled' => false ) ); }
	);
	$empty_occurrences = $empty_provider->fetch_occurrences();
	bcf_probe_assert( array() === $empty_occurrences && false === $empty_query_called, 'no enabled sources yields a valid empty result without an all-source query fallback' );

	$malformed_provider = new DRM_Calendar_Intake_Provider(
		static function () { return 2; },
		static function () { return array(); },
		static function () { return 1; },
		static function () use ( $enabled_ref ) { return array( array( 'source_ref' => $enabled_ref, 'label' => 'Enabled', 'policy' => 'trusted_performance', 'act_slug' => 'texanadian', 'enabled' => true, 'calendar_id' => 'must-not-appear' ) ); }
	);
	bcf_probe_assert( is_wp_error( $malformed_provider->health() ) && 'bcf_provider_source_discovery_invalid' === $malformed_provider->health()->get_error_code(), 'malformed or over-broad source discovery fails closed' );

	$missing_discovery_provider = new DRM_Calendar_Intake_Provider( static function () { return 2; }, static function () { return array(); }, false, false );
	bcf_probe_assert( is_wp_error( $missing_discovery_provider->health() ) && 'bcf_provider_source_discovery_unavailable' === $missing_discovery_provider->health()->get_error_code(), 'missing source-discovery dependency fails closed' );
	$wrong_discovery_provider = new DRM_Calendar_Intake_Provider( static function () { return 2; }, static function () { return array(); }, static function () { return 2; }, static function () { return array(); } );
	bcf_probe_assert( is_wp_error( $wrong_discovery_provider->health() ) && 'bcf_provider_source_discovery_incompatible' === $wrong_discovery_provider->health()->get_error_code(), 'wrong source-discovery version fails closed' );

	/* Explicit supersession: suppress only for a present, projectable target. */
	$active_pair   = bcf_probe_supersession_pair();
	$active_result = bcf_probe_build( $active_pair );
	bcf_probe_assert( 1 === count( $active_result['occurrences'] ) && Strict_Availability_Policy::uid_for_identity( $active_pair[1]['occurrence_identity'] ) === $active_result['occurrences'][0]['uid'], 'active present successor suppresses only its explicit predecessor' );
	bcf_probe_assert( 1 === $active_result['stats']['superseded_occurrences_suppressed'] && 0 === $active_result['stats']['unresolved_possible_migration_duplicates'] && 1 === substr_count( $active_result['ics'], 'BEGIN:VEVENT' ), 'suppressed predecessor is excluded from duplicate counts and ICS' );
	$active_diagnostic = $active_result['supersession_diagnostics'][0];
	bcf_probe_assert( 'suppressed' === $active_diagnostic['disposition'] && 'Existing mixed calendar' === $active_diagnostic['source_label'] && 'mixed_review' === $active_diagnostic['source_policy'] && 'Texanadian' === $active_diagnostic['successor_source_label'], 'suppressed predecessor remains visible with safe source label and policy' );
	$diagnostic_json = json_encode( $active_result['supersession_diagnostics'] );
	bcf_probe_assert( false === strpos( $diagnostic_json, $active_pair[0]['occurrence_identity'] ) && false === strpos( $diagnostic_json, $active_pair[1]['occurrence_identity'] ) && false === strpos( $diagnostic_json, $active_pair[0]['source_ref'] ), 'admin diagnostics expose no occurrence or opaque source identifiers' );

	$tentative_pair = bcf_probe_supersession_pair( array( 'google_status' => 'tentative' ) );
	$tentative_result = bcf_probe_build( $tentative_pair );
	bcf_probe_assert( 1 === count( $tentative_result['occurrences'] ) && 'TENTATIVE' === $tentative_result['occurrences'][0]['status'], 'tentative successor is retained and suppresses predecessor' );
	$missing_state_pair = bcf_probe_supersession_pair( array( 'source_state' => 'source_missing' ) );
	$missing_state_result = bcf_probe_build( $missing_state_pair );
	bcf_probe_assert( 1 === count( $missing_state_result['occurrences'] ) && 'source_missing' === $missing_state_result['occurrences'][0]['source_state'] && 'OPAQUE' === $missing_state_result['occurrences'][0]['transparency'], 'source-missing successor remains BUSY and suppresses predecessor' );
	$cancelled_pair = bcf_probe_supersession_pair( array( 'source_state' => 'cancelled', 'google_status' => 'cancelled' ) );
	$cancelled_result = bcf_probe_build( $cancelled_pair );
	bcf_probe_assert( 0 === count( $cancelled_result['occurrences'] ) && 1 === $cancelled_result['stats']['superseded_occurrences_suppressed'] && 1 === $cancelled_result['stats']['historical_cancellations_omitted'], 'cancelled successor remains projectable for supersession but its historical tombstone is omitted from snapshot output' );
	bcf_probe_assert( 0 === substr_count( $cancelled_result['ics'], 'BEGIN:VEVENT' ) && 'historical_cancellation_omitted' === $cancelled_result['publication_diagnostics'][0]['reason'], 'never-emitted cancellation is absent from ICS with safe publication diagnostics' );

	$ignored_pair   = bcf_probe_supersession_pair( array( 'classification' => 'ignored' ) );
	$ignored_result = bcf_probe_build( $ignored_pair );
	bcf_probe_assert( 1 === count( $ignored_result['occurrences'] ) && false !== strpos( $ignored_result['occurrences'][0]['warning'], 'supersession_target_not_available_for_projection' ) && 1 === $ignored_result['stats']['supersession_targets_not_available'], 'ignored successor keeps predecessor BUSY with a not-projectable diagnostic' );
	$expired_cancel_pair = bcf_probe_supersession_pair(
		array(
			'source_state'  => 'cancelled',
			'google_status' => 'cancelled',
			'start'         => bcf_probe_time( '2025-01-01 20:00:00' ),
			'end'           => bcf_probe_time( '2025-01-01 22:00:00' ),
		)
	);
	$expired_cancel_result = bcf_probe_build( $expired_cancel_pair );
	bcf_probe_assert( 1 === count( $expired_cancel_result['occurrences'] ) && 1 === $expired_cancel_result['stats']['supersession_targets_not_available'], 'successor outside cancellation retention does not suppress predecessor' );

	$missing_target = bcf_probe_occurrence(
		array(
			'superseded' => true,
			'superseded_by_occurrence_ref' => hash( 'sha256', 'absent-supersession-target' ),
		)
	);
	$missing_target_result = bcf_probe_build( array( $missing_target ) );
	bcf_probe_assert( 1 === count( $missing_target_result['occurrences'] ) && false !== strpos( $missing_target_result['occurrences'][0]['warning'], 'supersession_target_missing' ) && 1 === $missing_target_result['stats']['supersession_targets_missing'], 'missing successor keeps predecessor BUSY with target-missing diagnostic' );
	bcf_probe_assert( false === strpos( $missing_target_result['ics'], 'supersession_target_missing' ), 'supersession warning code remains internal and absent from ICS' );
	$malformed_reference = bcf_probe_occurrence( array( 'superseded' => true, 'superseded_by_occurrence_ref' => '', 'supersession_reference_valid' => false ) );
	$malformed_result = bcf_probe_build( array( $malformed_reference ) );
	bcf_probe_assert( 1 === count( $malformed_result['occurrences'] ) && false !== strpos( $malformed_result['occurrences'][0]['warning'], 'malformed_supersession_reference' ) && 1 === $malformed_result['stats']['malformed_supersession_references'], 'malformed successor reference retains predecessor conservatively' );

	$self_identity = hash( 'sha256', 'self-cycle' );
	$self_cycle = bcf_probe_occurrence( array( 'occurrence_identity' => $self_identity, 'superseded' => true, 'superseded_by_occurrence_ref' => $self_identity ) );
	$self_cycle_result = bcf_probe_build( array( $self_cycle ) );
	bcf_probe_assert( 1 === count( $self_cycle_result['occurrences'] ) && false !== strpos( $self_cycle_result['occurrences'][0]['warning'], 'supersession_cycle' ), 'self-cycle fails safe without suppression' );
	$cycle_a_id = hash( 'sha256', 'cycle-a' );
	$cycle_b_id = hash( 'sha256', 'cycle-b' );
	$cycle_a = bcf_probe_occurrence( array( 'occurrence_identity' => $cycle_a_id, 'superseded' => true, 'superseded_by_occurrence_ref' => $cycle_b_id ) );
	$cycle_b = bcf_probe_occurrence( array( 'occurrence_identity' => $cycle_b_id, 'superseded' => true, 'superseded_by_occurrence_ref' => $cycle_a_id, 'source_ref' => hash( 'sha256', 'cycle-source-b' ) ) );
	$cycle_result = bcf_probe_build( array( $cycle_a, $cycle_b ) );
	bcf_probe_assert( 2 === count( $cycle_result['occurrences'] ) && 2 === $cycle_result['stats']['supersession_cycles'], 'multi-occurrence cycle retains every occurrence and reports both invalid paths' );

	$chain_a_id = hash( 'sha256', 'chain-a' );
	$chain_b_id = hash( 'sha256', 'chain-b' );
	$chain_c_id = hash( 'sha256', 'chain-c' );
	$chain_a = bcf_probe_occurrence( array( 'occurrence_identity' => $chain_a_id, 'superseded' => true, 'superseded_by_occurrence_ref' => $chain_b_id ) );
	$chain_b = bcf_probe_occurrence( array( 'occurrence_identity' => $chain_b_id, 'superseded' => true, 'superseded_by_occurrence_ref' => $chain_c_id, 'source_ref' => hash( 'sha256', 'chain-source-b' ) ) );
	$chain_c = bcf_probe_occurrence( array( 'occurrence_identity' => $chain_c_id, 'source_ref' => hash( 'sha256', 'chain-source-c' ) ) );
	$chain_result = bcf_probe_build( array( $chain_a, $chain_b, $chain_c ) );
	bcf_probe_assert( 1 === count( $chain_result['occurrences'] ) && Strict_Availability_Policy::uid_for_identity( $chain_c_id ) === $chain_result['occurrences'][0]['uid'] && 2 === $chain_result['stats']['superseded_occurrences_suppressed'], 'valid supersession chain emits only its final projectable successor' );

	$cancelled_predecessor_pair = bcf_probe_supersession_pair( array(), array( 'source_state' => 'cancelled', 'google_status' => 'cancelled' ) );
	$cancelled_predecessor_result = bcf_probe_build( $cancelled_predecessor_pair );
	bcf_probe_assert( 1 === count( $cancelled_predecessor_result['occurrences'] ) && 'CONFIRMED' === $cancelled_predecessor_result['occurrences'][0]['status'], 'explicit relation suppresses a cancelled predecessor in favor of active successor' );
	$recurring_predecessor_pair = bcf_probe_supersession_pair( array(), array( 'recurring_occurrence' => true ) );
	$recurring_predecessor_result = bcf_probe_build( $recurring_predecessor_pair );
	bcf_probe_assert( 1 === count( $recurring_predecessor_result['occurrences'] ) && 1 === substr_count( $recurring_predecessor_result['ics'], 'BEGIN:VEVENT' ), 'explicit relation suppresses one expanded recurring predecessor occurrence only' );

	$unlinked_pair = bcf_probe_supersession_pair();
	$unlinked_pair[0]['superseded'] = false;
	$unlinked_pair[0]['superseded_by_occurrence_ref'] = '';
	$unlinked_result = bcf_probe_build( $unlinked_pair );
	bcf_probe_assert( 2 === count( $unlinked_result['occurrences'] ) && 1 === $unlinked_result['stats']['unresolved_possible_migration_duplicates'], 'same-time cross-source pair remains duplicated without an explicit relation' );

	$token = rtrim( strtr( base64_encode( str_repeat( 'x', 32 ) ), '+/', '-_' ), '=' );
	$hash  = Secret_Store::token_hash( $token );
	bcf_probe_assert( Secret_Store::validate_hash( $token, $hash ), 'correct capability token validates' );
	bcf_probe_assert( ! Secret_Store::validate_hash( str_repeat( 'y', 43 ), $hash ) && ! Secret_Store::validate_hash( 'short', $hash ), 'wrong or malformed capability token fails closed' );
	bcf_probe_assert( Admin_Authorization::can_rotate_secret( true, true ) && ! Admin_Authorization::can_rotate_secret( false, true ) && ! Admin_Authorization::can_rotate_secret( true, false ), 'secret rotation requires capability and nonce' );

	$duplicate_a = $public;
	$duplicate_b = $public;
	$duplicate_b['uid']        = Strict_Availability_Policy::uid_for_identity( hash( 'sha256', 'duplicate-b' ) );
	$duplicate_b['source_ref'] = hash( 'sha256', 'fixture-source-b' );
	$diagnosed = ( new Duplicate_Detector() )->diagnose( array( $duplicate_a, $duplicate_b ) );
	bcf_probe_assert( 1 === $diagnosed['groups'] && 2 === $diagnosed['occurrences'], 'exact material match across distinct sources is diagnosed' );
	bcf_probe_assert( 2 === count( $diagnosed['events'] ) && false !== strpos( $diagnosed['events'][0]['warning'], 'possible_cross_source_duplicate' ), 'duplicate diagnostic does not suppress either busy occurrence' );
	bcf_probe_assert( false === strpos( $formatter->render( $diagnosed['events'], 'Duplicate Hygiene', 1770000000 ), 'possible_cross_source_duplicate' ), 'duplicate warning code remains internal and absent from ICS' );
	$same_source = ( new Duplicate_Detector() )->diagnose( array( $duplicate_a, $duplicate_a ) );
	bcf_probe_assert( 0 === $same_source['groups'], 'same-source repeats are not labeled cross-source duplicates' );

	$recurring = $public;
	$recurring['recurring_occurrence'] = true;
	$recurring_ics = $formatter->render( array( $recurring ), 'Expanded Recurrence', 1770000000 );
	bcf_probe_assert( 1 === substr_count( $recurring_ics, 'BEGIN:VEVENT' ) && false === strpos( $recurring_ics, 'RRULE' ), 'one expanded recurring occurrence produces one VEVENT without RRULE' );

	echo "PASS: {$assertions} isolated assertions\n";
}
