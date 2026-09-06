<?php
/**
 * Bounded publication history for snapshot-feed cancellation decisions.
 */

namespace ConeyProductions\BackstageCalendarFeeds;

final class Publication_Ledger {
	const OPTION_NAME    = 'bcf_publication_ledger_v1';
	const LOCK_NAME      = 'bcf_publication_ledger_lock_v1';
	const SCHEMA_VERSION = 1;

	/**
	 * Omit explicit cancellations from snapshot output and optionally record emitted BUSY history.
	 *
	 * Cancellation projections remain available to supersession resolution before
	 * this filter runs. This layer controls only final snapshot publication.
	 *
	 * @param array<string,mixed>            $profile            Feed profile.
	 * @param array<int,array<string,mixed>> $events             Resolved feed events.
	 * @param int                            $clock              Build timestamp.
	 * @param bool                           $record_publication Whether the feed will actually be served.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function filter( $profile, $events, $clock, $record_publication = false ) {
		$profile_id = isset( $profile['id'] ) ? (string) $profile['id'] : '';
		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9_-]{0,79}$/', $profile_id ) ) {
			return new \WP_Error( 'bcf_publication_profile_invalid', 'Feed publication profile unavailable.' );
		}

		$owner = '';
		if ( $record_publication ) {
			$owner = $this->acquire_lock();
			if ( is_wp_error( $owner ) ) {
				return $owner;
			}
		}

		try {
			$state = $this->load_state();
			if ( is_wp_error( $state ) ) {
				return $state;
			}
			$entries = isset( $state['profiles'][ $profile_id ]['entries'] ) ? $state['profiles'][ $profile_id ]['entries'] : array();
			$output  = array();
			$diagnostics = array();
			$counts = array(
				'historical_cancellations_omitted'          => 0,
				'previously_emitted_cancellations_omitted' => 0,
				'cancellation_tombstones_emitted'           => 0,
			);

			foreach ( $events as $event ) {
				$uid = isset( $event['uid'] ) ? (string) $event['uid'] : '';
				if ( ! $this->valid_uid( $uid ) ) {
					return new \WP_Error( 'bcf_publication_event_invalid', 'Feed publication event unavailable.' );
				}
				if ( 'CANCELLED' === (string) ( $event['status'] ?? '' ) ) {
					$previously_emitted = isset( $entries[ $uid ] ) && in_array( (string) $entries[ $uid ]['last_emitted_status'], array( 'CONFIRMED', 'TENTATIVE' ), true );
					$reason = $previously_emitted ? 'previously_emitted_cancellation_omitted' : 'historical_cancellation_omitted';
					++$counts[ $previously_emitted ? 'previously_emitted_cancellations_omitted' : 'historical_cancellations_omitted' ];
					$diagnostics[] = $this->diagnostic( $event, $reason );
					continue;
				}

				$output[] = $event;
				if ( $record_publication ) {
					$first = isset( $entries[ $uid ] ) ? (int) $entries[ $uid ]['first_emitted_at'] : (int) $clock;
					$entries[ $uid ] = array(
						'first_emitted_at'    => $first,
						'last_emitted_at'     => (int) $clock,
						'last_emitted_status' => (string) $event['status'],
						'projection_fingerprint' => $this->fingerprint( $event ),
					);
				}
			}

			if ( $record_publication ) {
				$retention_days = max( 90, (int) ( $profile['publication_history_retention_days'] ?? 90 ) );
				$cutoff         = (int) $clock - ( $retention_days * DAY_IN_SECONDS );
				$entries        = array_filter(
					$entries,
					static function ( $entry ) use ( $cutoff ) {
						return (int) $entry['last_emitted_at'] >= $cutoff;
					}
				);
				$state['profiles'][ $profile_id ] = array(
					'updated_at' => (int) $clock,
					'entries'    => $entries,
				);
				$saved = $this->save_state( $state );
				if ( is_wp_error( $saved ) ) {
					return $saved;
				}
			}

			return array(
				'events'      => $output,
				'diagnostics' => $diagnostics,
				'counts'      => $counts,
				'history_entries' => count( $entries ),
			);
		} finally {
			if ( '' !== $owner ) {
				$this->release_lock( $owner );
			}
		}
	}

	/** @return array<string,mixed>|\WP_Error */
	private function load_state() {
		$stored = get_option( self::OPTION_NAME, null );
		if ( null === $stored ) {
			return array( 'schema_version' => self::SCHEMA_VERSION, 'profiles' => array() );
		}
		if ( ! is_array( $stored ) || self::SCHEMA_VERSION !== (int) ( $stored['schema_version'] ?? 0 ) || ! isset( $stored['profiles'] ) || ! is_array( $stored['profiles'] ) ) {
			return new \WP_Error( 'bcf_publication_ledger_corrupt', 'Feed publication history unavailable.' );
		}

		foreach ( $stored['profiles'] as $profile_id => $profile ) {
			if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9_-]{0,79}$/', (string) $profile_id ) || ! is_array( $profile ) || ! isset( $profile['updated_at'], $profile['entries'] ) || ! is_int( $profile['updated_at'] ) || ! is_array( $profile['entries'] ) ) {
				return new \WP_Error( 'bcf_publication_ledger_corrupt', 'Feed publication history unavailable.' );
			}
			foreach ( $profile['entries'] as $uid => $entry ) {
				if ( ! $this->valid_uid( $uid ) || ! is_array( $entry ) || ! isset( $entry['first_emitted_at'], $entry['last_emitted_at'], $entry['last_emitted_status'], $entry['projection_fingerprint'] ) || ! is_int( $entry['first_emitted_at'] ) || ! is_int( $entry['last_emitted_at'] ) || $entry['first_emitted_at'] <= 0 || $entry['last_emitted_at'] < $entry['first_emitted_at'] || ! in_array( $entry['last_emitted_status'], array( 'CONFIRMED', 'TENTATIVE' ), true ) || 1 !== preg_match( '/^[a-f0-9]{64}$/', (string) $entry['projection_fingerprint'] ) ) {
					return new \WP_Error( 'bcf_publication_ledger_corrupt', 'Feed publication history unavailable.' );
				}
			}
		}
		return $stored;
	}

	/** @param array<string,mixed> $state State. @return true|\WP_Error */
	private function save_state( $state ) {
		$updated = update_option( self::OPTION_NAME, $state, false );
		if ( false === $updated && get_option( self::OPTION_NAME, null ) !== $state ) {
			return new \WP_Error( 'bcf_publication_ledger_write_failed', 'Feed publication history could not be saved.' );
		}
		return true;
	}

	/** @return string|\WP_Error */
	private function acquire_lock() {
		try {
			$owner = bin2hex( random_bytes( 16 ) );
		} catch ( \Throwable $throwable ) {
			return new \WP_Error( 'bcf_publication_lock_failed', 'Feed publication history unavailable.' );
		}
		$value = array( 'owner' => $owner, 'expires_at' => time() + 120 );
		if ( add_option( self::LOCK_NAME, $value, '', false ) ) {
			return $owner;
		}
		$current = get_option( self::LOCK_NAME, array() );
		if ( is_array( $current ) && isset( $current['expires_at'] ) && (int) $current['expires_at'] < time() ) {
			delete_option( self::LOCK_NAME );
			if ( add_option( self::LOCK_NAME, $value, '', false ) ) {
				return $owner;
			}
		}
		return new \WP_Error( 'bcf_publication_ledger_locked', 'Feed publication history is busy.' );
	}

	/** @param string $owner Lock owner. */
	private function release_lock( $owner ) {
		$current = get_option( self::LOCK_NAME, array() );
		if ( is_array( $current ) && isset( $current['owner'] ) && hash_equals( (string) $current['owner'], (string) $owner ) ) {
			delete_option( self::LOCK_NAME );
		}
	}

	/** @param array<string,mixed> $event Safe projected event. @return string */
	private function fingerprint( $event ) {
		$material = array(
			'status'       => (string) $event['status'],
			'start'        => (int) $event['start']['timestamp'],
			'end'          => (int) $event['end']['timestamp'],
			'all_day'      => ! empty( $event['all_day'] ),
			'summary'      => (string) $event['summary'],
			'privacy_mode' => (string) $event['privacy_mode'],
			'source_state' => (string) $event['source_state'],
		);
		return hash( 'sha256', json_encode( $material, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	/** @param array<string,mixed> $event Event. @param string $reason Reason. @return array<string,mixed> */
	private function diagnostic( $event, $reason ) {
		return array(
			'disposition'          => 'omitted',
			'reason'               => $reason,
			'source_label'         => (string) ( $event['source_label'] ?? 'Calendar source' ),
			'source_policy'        => (string) ( $event['source_policy'] ?? 'unknown' ),
			'start'                => $event['start'],
			'end'                  => $event['end'],
			'all_day'              => ! empty( $event['all_day'] ),
			'safe_act'             => (string) ( $event['safe_act'] ?? 'Scheduled Act' ),
			'feed_summary'         => (string) ( $event['summary'] ?? 'Unavailable' ),
			'feed_state'           => (string) ( $event['feed_state'] ?? 'Cancelled' ),
			'source_classification'=> (string) ( $event['source_classification'] ?? 'unknown' ),
			'source_state'         => (string) ( $event['source_state'] ?? 'unknown' ),
			'google_status'        => (string) ( $event['google_status'] ?? 'unknown' ),
			'privacy_mode'         => (string) ( $event['privacy_mode'] ?? 'sanitized' ),
			'warning'              => $reason,
		);
	}

	/** @param string $uid Derived public UID. @return bool */
	private function valid_uid( $uid ) {
		return 1 === preg_match( '/^[a-f0-9]{64}@backstage-calendar-feeds$/', (string) $uid );
	}
}
