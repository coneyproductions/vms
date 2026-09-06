<?php
/**
 * Conservative availability projection independent of providers/formatters.
 */

namespace ConeyProductions\BackstageCalendarFeeds;

final class Strict_Availability_Policy {
	/**
	 * Project one provider occurrence into one feed occurrence, or omit it.
	 *
	 * @param array<string,mixed> $occurrence Provider occurrence.
	 * @param array<string,mixed> $profile    Feed profile.
	 * @return array<string,mixed>|null
	 */
	public function project( $occurrence, $profile ) {
		$classification = isset( $occurrence['classification'] ) ? (string) $occurrence['classification'] : 'unknown';
		if ( 'ignored' === $classification ) {
			return null;
		}

		$source_state  = isset( $occurrence['source_state'] ) ? (string) $occurrence['source_state'] : 'unknown';
		$google_status = isset( $occurrence['google_status'] ) ? (string) $occurrence['google_status'] : 'unknown';
		$act_name      = $this->act_name( isset( $occurrence['act_slug'] ) ? (string) $occurrence['act_slug'] : '', $profile );
		$is_cancelled  = 'cancelled' === $source_state || 'cancelled' === $google_status;
		$is_missing    = 'source_missing' === $source_state;
		$is_tentative  = 'tentative' === $google_status && ! $is_cancelled && ! $is_missing;

		$presentation = $this->presentation( $occurrence, $act_name, $is_cancelled, $is_missing );
		$status       = $is_cancelled ? 'CANCELLED' : ( $is_tentative ? 'TENTATIVE' : 'CONFIRMED' );
		$feed_state   = $is_cancelled ? 'Cancelled' : ( $is_tentative ? 'Tentative' : 'Busy' );
		$reason       = $is_cancelled ? 'explicit_provider_cancellation' : ( $is_missing ? 'source_missing_remains_busy' : 'strict_availability_included' );
		$warning      = $is_missing ? 'source_missing' : '';

		return array(
			'uid'                      => self::uid_for_identity( (string) $occurrence['occurrence_identity'] ),
			'start'                    => $occurrence['start'],
			'end'                      => $occurrence['end'],
			'all_day'                  => ! empty( $occurrence['all_day'] ),
			'summary'                  => $presentation['summary'],
			'location'                 => $presentation['location'],
			'status'                   => $status,
			'transparency'             => 'OPAQUE',
			'feed_state'               => $feed_state,
			'safe_act'                 => $act_name,
			'source_classification'    => $classification,
			'classification_provenance'=> (string) ( $occurrence['classification_provenance'] ?? 'unknown' ),
			'review_state'              => (string) ( $occurrence['review_state'] ?? 'needs_review' ),
			'visibility'                => (string) ( $occurrence['visibility'] ?? 'unknown' ),
			'inclusion_reason'         => $reason,
			'privacy_mode'             => $presentation['privacy_mode'],
			'warning'                  => $warning,
			'source_state'             => $source_state,
			'google_status'            => $google_status,
			'source_ref'               => (string) $occurrence['source_ref'],
			'source_fingerprint'       => (string) $occurrence['source_fingerprint'],
			'source_label'             => (string) ( $occurrence['source_label'] ?? 'Calendar source' ),
			'source_policy'            => (string) ( $occurrence['source_policy'] ?? 'unknown' ),
			'recurring_occurrence'     => ! empty( $occurrence['recurring_occurrence'] ),
		);
	}

	/**
	 * Derive a stable public UID without emitting raw provider identity.
	 *
	 * @param string $occurrence_identity Opaque provider identity.
	 * @return string
	 */
	public static function uid_for_identity( $occurrence_identity ) {
		return hash( 'sha256', "bcf-ics-uid-v1\n" . (string) $occurrence_identity ) . '@backstage-calendar-feeds';
	}

	/**
	 * Map profile-specific act slugs without exposing unknown identifiers.
	 *
	 * @param string              $act_slug Canonical slug.
	 * @param array<string,mixed> $profile  Profile.
	 * @return string
	 */
	private function act_name( $act_slug, $profile ) {
		$names = isset( $profile['act_names'] ) && is_array( $profile['act_names'] ) ? $profile['act_names'] : array();
		return isset( $names[ $act_slug ] ) && '' !== (string) $names[ $act_slug ] ? (string) $names[ $act_slug ] : 'Scheduled Act';
	}

	/**
	 * Produce a conservative presentation from safe provider fields.
	 *
	 * @param array<string,mixed> $occurrence  Provider occurrence.
	 * @param string              $act_name    Safe profile act name.
	 * @param bool                $cancelled   Explicit cancellation.
	 * @param bool                $missing     Source-missing state.
	 * @return array<string,string>
	 */
	private function presentation( $occurrence, $act_name, $cancelled, $missing ) {
		$classification = (string) $occurrence['classification'];
		$visibility     = (string) $occurrence['visibility'];
		if ( $cancelled ) {
			return array( 'summary' => 'Unavailable', 'location' => '', 'privacy_mode' => 'sanitized' );
		}
		if ( $missing || 'unknown' === $classification || 'unknown' === (string) $occurrence['source_state'] ) {
			// Internal review/source diagnostics remain on the projected event, not in its consumer-facing summary.
			return array( 'summary' => 'Unavailable', 'location' => '', 'privacy_mode' => 'sanitized' );
		}

		switch ( $classification ) {
			case 'public_performance':
				if ( in_array( $visibility, array( 'private', 'confidential', 'unknown' ), true ) ) {
					return array( 'summary' => 'Private Event — ' . $act_name, 'location' => '', 'privacy_mode' => 'sanitized' );
				}
				$summary = '' !== (string) $occurrence['summary'] ? (string) $occurrence['summary'] : $act_name;
				return array( 'summary' => $summary, 'location' => $this->public_location( $occurrence ), 'privacy_mode' => 'public' );

			case 'private_performance':
				return array( 'summary' => 'Private Event — ' . $act_name, 'location' => '', 'privacy_mode' => 'sanitized' );

			case 'hold':
				return array( 'summary' => 'Hold — Unavailable', 'location' => '', 'privacy_mode' => 'sanitized' );

			case 'personal':
				return array( 'summary' => 'Unavailable', 'location' => '', 'privacy_mode' => 'sanitized' );

			case 'media':
				return array( 'summary' => 'Media — ' . $act_name, 'location' => '', 'privacy_mode' => 'sanitized' );

			case 'other':
			default:
				return array( 'summary' => 'Unavailable', 'location' => '', 'privacy_mode' => 'sanitized' );
		}
	}

	/**
	 * Format only allowlisted, contract-resolved public venue fields.
	 *
	 * @param array<string,mixed> $occurrence Provider occurrence.
	 * @return string
	 */
	private function public_location( $occurrence ) {
		$parts = array();
		if ( '' !== (string) $occurrence['venue_name'] ) {
			$parts[] = (string) $occurrence['venue_name'];
		}
		$region = trim( (string) $occurrence['city'] . ( '' !== (string) $occurrence['state'] ? ', ' . (string) $occurrence['state'] : '' ), ' ,' );
		if ( '' !== $region ) {
			$parts[] = $region;
		}
		return implode( ', ', $parts );
	}
}
