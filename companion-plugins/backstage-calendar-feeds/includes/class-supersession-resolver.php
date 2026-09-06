<?php
/**
 * Resolve explicit provider-owned occurrence supersession without inference.
 */

namespace ConeyProductions\BackstageCalendarFeeds;

final class Supersession_Resolver {
	/**
	 * Suppress a predecessor only when its explicit successor is present and projectable.
	 *
	 * @param array<int,array<string,mixed>>    $occurrences          Normalized provider occurrences.
	 * @param array<string,array<string,mixed>> $projected_by_identity Policy-projected, retention-eligible events.
	 * @return array<string,mixed>
	 */
	public function resolve( $occurrences, $projected_by_identity ) {
		$by_identity = array();
		$duplicates  = array();
		foreach ( $occurrences as $occurrence ) {
			$identity = (string) ( $occurrence['occurrence_identity'] ?? '' );
			if ( isset( $by_identity[ $identity ] ) ) {
				$duplicates[ $identity ] = true;
			}
			$by_identity[ $identity ] = $occurrence;
		}

		$events      = $projected_by_identity;
		$diagnostics = array();
		$counts      = array(
			'suppressed'           => 0,
			'targets_missing'      => 0,
			'targets_unavailable'  => 0,
			'malformed_references' => 0,
			'cycles'               => 0,
		);

		foreach ( $occurrences as $occurrence ) {
			$identity       = (string) ( $occurrence['occurrence_identity'] ?? '' );
			$is_superseded  = ! empty( $occurrence['superseded'] );
			$reference      = (string) ( $occurrence['superseded_by_occurrence_ref'] ?? '' );
			$reference_good = ! empty( $occurrence['supersession_reference_valid'] );
			if ( ! $is_superseded && $reference_good ) {
				continue;
			}

			$reason    = '';
			$suppress  = false;
			$successor = null;
			if ( ! $reference_good || isset( $duplicates[ $identity ] ) ) {
				$reason = 'malformed_supersession_reference';
				++$counts['malformed_references'];
			} elseif ( ! isset( $by_identity[ $reference ] ) ) {
				$reason = 'supersession_target_missing';
				++$counts['targets_missing'];
			} elseif ( $this->path_has_cycle( $identity, $by_identity ) ) {
				$reason = 'supersession_cycle';
				++$counts['cycles'];
			} else {
				$successor = $by_identity[ $reference ];
				if ( ! isset( $projected_by_identity[ $reference ] ) ) {
					$reason = 'supersession_target_not_available_for_projection';
					++$counts['targets_unavailable'];
				} else {
					$reason   = 'superseded_by_available_successor';
					$suppress = true;
					++$counts['suppressed'];
				}
			}

			$event = $projected_by_identity[ $identity ] ?? null;
			if ( $suppress ) {
				unset( $events[ $identity ] );
			} elseif ( is_array( $event ) ) {
				$events[ $identity ]['warning'] = $this->append_warning( (string) $events[ $identity ]['warning'], $reason );
			}

			$diagnostics[] = $this->diagnostic( $occurrence, $successor, $event, $suppress, $reason );
		}

		return array(
			'events'      => array_values( $events ),
			'diagnostics' => $diagnostics,
			'counts'      => $counts,
		);
	}

	/**
	 * Detect cycles anywhere in one proposed path. Missing/malformed edges are not cycles.
	 *
	 * @param string                           $start       Starting occurrence identity.
	 * @param array<string,array<string,mixed>> $by_identity Occurrence index.
	 * @return bool
	 */
	private function path_has_cycle( $start, $by_identity ) {
		$visited = array();
		$current = $start;
		for ( $depth = 0; $depth < 500; $depth++ ) {
			if ( isset( $visited[ $current ] ) ) {
				return true;
			}
			$visited[ $current ] = true;
			if ( ! isset( $by_identity[ $current ] ) ) {
				return false;
			}
			$occurrence = $by_identity[ $current ];
			if ( empty( $occurrence['superseded'] ) || empty( $occurrence['supersession_reference_valid'] ) ) {
				return false;
			}
			$current = (string) ( $occurrence['superseded_by_occurrence_ref'] ?? '' );
		}
		return true;
	}

	/**
	 * Build an identifier-free admin diagnostic.
	 *
	 * @param array<string,mixed>      $predecessor Normalized predecessor.
	 * @param array<string,mixed>|null $successor   Normalized successor when present.
	 * @param array<string,mixed>|null $event       Safe predecessor projection when available.
	 * @param bool                     $suppressed  Whether predecessor is suppressed.
	 * @param string                   $reason      Safe diagnostic code.
	 * @return array<string,mixed>
	 */
	private function diagnostic( $predecessor, $successor, $event, $suppressed, $reason ) {
		return array(
			'disposition'            => $suppressed ? 'suppressed' : 'retained',
			'reason'                 => $reason,
			'source_label'           => (string) ( $predecessor['source_label'] ?? 'Calendar source' ),
			'source_policy'          => (string) ( $predecessor['source_policy'] ?? 'unknown' ),
			'successor_source_label' => is_array( $successor ) ? (string) ( $successor['source_label'] ?? 'Calendar source' ) : 'Unavailable',
			'successor_source_policy'=> is_array( $successor ) ? (string) ( $successor['source_policy'] ?? 'unknown' ) : 'unknown',
			'start'                  => is_array( $event ) ? $event['start'] : $predecessor['start'],
			'end'                    => is_array( $event ) ? $event['end'] : $predecessor['end'],
			'all_day'                => is_array( $event ) ? ! empty( $event['all_day'] ) : ! empty( $predecessor['all_day'] ),
			'safe_act'               => is_array( $event ) ? (string) $event['safe_act'] : 'Scheduled Act',
			'feed_summary'           => is_array( $event ) ? (string) $event['summary'] : 'Not projected',
			'feed_state'             => is_array( $event ) ? (string) $event['feed_state'] : 'Not projected',
		);
	}

	/** @param string $existing Existing warning. @param string $warning New warning. @return string */
	private function append_warning( $existing, $warning ) {
		return '' === $existing ? $warning : $existing . '; ' . $warning;
	}
}
