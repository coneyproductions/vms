<?php
/**
 * Exact/high-confidence cross-source duplicate diagnostics.
 */

namespace ConeyProductions\BackstageCalendarFeeds;

final class Duplicate_Detector {
	/**
	 * Flag exact material fingerprints appearing on distinct provider sources.
	 *
	 * Events remain present: diagnostics never suppress availability conflicts.
	 *
	 * @param array<int,array<string,mixed>> $events Feed events.
	 * @return array{events:array<int,array<string,mixed>>,groups:int,occurrences:int}
	 */
	public function diagnose( $events ) {
		$by_fingerprint = array();
		foreach ( $events as $index => $event ) {
			if ( 'CANCELLED' === (string) $event['status'] ) {
				continue;
			}
			$fingerprint = isset( $event['source_fingerprint'] ) ? (string) $event['source_fingerprint'] : '';
			$source_ref  = isset( $event['source_ref'] ) ? (string) $event['source_ref'] : '';
			if ( '' === $fingerprint || '' === $source_ref ) {
				continue;
			}
			$by_fingerprint[ $fingerprint ][ $source_ref ][] = $index;
		}

		$groups      = 0;
		$occurrences = 0;
		foreach ( $by_fingerprint as $sources ) {
			if ( count( $sources ) < 2 ) {
				continue;
			}
			++$groups;
			foreach ( $sources as $indexes ) {
				foreach ( $indexes as $index ) {
					$events[ $index ]['warning'] = $this->append_warning( (string) $events[ $index ]['warning'], 'possible_cross_source_duplicate' );
					++$occurrences;
				}
			}
		}

		return array( 'events' => $events, 'groups' => $groups, 'occurrences' => $occurrences );
	}

	/** @param string $existing Existing warning. @param string $warning New warning. */
	private function append_warning( $existing, $warning ) {
		return '' === $existing ? $warning : $existing . '; ' . $warning;
	}
}
