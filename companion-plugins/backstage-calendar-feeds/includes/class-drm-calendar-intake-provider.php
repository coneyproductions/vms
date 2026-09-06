<?php
/**
 * DRM Calendar Intake provider adapter.
 */

namespace ConeyProductions\BackstageCalendarFeeds;

final class DRM_Calendar_Intake_Provider implements Provider_Interface {
	const CONTRACT_VERSION = 2;
	const SOURCE_DISCOVERY_VERSION = 1;

	/** @var callable|null */
	private $version_callback;

	/** @var callable|null */
	private $query_callback;

	/** @var callable|null */
	private $source_version_callback;

	/** @var callable|null */
	private $source_query_callback;

	/** @var array<int,string>|null */
	private $enabled_source_refs;

	/** @var array<string,array<string,string>> */
	private $enabled_source_metadata = array();

	/** @var int */
	private $excluded_source_count = 0;

	/**
	 * Allow callback injection for isolated fail-closed tests.
	 *
	 * @param callable|null $version_callback        Occurrence contract-version callback.
	 * @param callable|null $query_callback          Occurrence contract-query callback.
	 * @param callable|null $source_version_callback Source-discovery version callback.
	 * @param callable|null $source_query_callback   Source-discovery query callback.
	 */
	public function __construct( $version_callback = null, $query_callback = null, $source_version_callback = null, $source_query_callback = null ) {
		$this->version_callback        = null !== $version_callback ? $version_callback : ( function_exists( 'drm_ci_router_contract_version' ) ? 'drm_ci_router_contract_version' : null );
		$this->query_callback          = null !== $query_callback ? $query_callback : ( function_exists( 'drm_ci_query_router_records' ) ? 'drm_ci_query_router_records' : null );
		$this->source_version_callback = null !== $source_version_callback ? $source_version_callback : ( function_exists( 'drm_ci_router_source_discovery_version' ) ? 'drm_ci_router_source_discovery_version' : null );
		$this->source_query_callback   = null !== $source_query_callback ? $source_query_callback : ( function_exists( 'drm_ci_query_router_sources' ) ? 'drm_ci_query_router_sources' : null );
	}

	/** @inheritDoc */
	public function get_id() {
		return 'drm-calendar-intake';
	}

	/** @inheritDoc */
	public function health() {
		if ( ! is_callable( $this->version_callback ) || ! is_callable( $this->query_callback ) ) {
			return new \WP_Error( 'bcf_provider_unavailable', 'Calendar provider unavailable.' );
		}

		$version = (int) call_user_func( $this->version_callback );
		if ( self::CONTRACT_VERSION !== $version ) {
			return new \WP_Error( 'bcf_provider_contract_incompatible', 'Calendar provider contract unavailable.' );
		}
		if ( ! is_callable( $this->source_version_callback ) || ! is_callable( $this->source_query_callback ) ) {
			return new \WP_Error( 'bcf_provider_source_discovery_unavailable', 'Calendar provider source discovery unavailable.' );
		}

		$source_version = (int) call_user_func( $this->source_version_callback );
		if ( self::SOURCE_DISCOVERY_VERSION !== $source_version ) {
			return new \WP_Error( 'bcf_provider_source_discovery_incompatible', 'Calendar provider source discovery incompatible.' );
		}
		$source_refs = $this->discover_enabled_source_refs();
		if ( is_wp_error( $source_refs ) ) {
			return $source_refs;
		}

		return array(
			'available'                => true,
			'provider'                 => $this->get_id(),
			'contract_version'         => $version,
			'source_discovery_version' => $source_version,
			'enabled_source_count'     => count( $source_refs ),
			'excluded_source_count'    => $this->excluded_source_count,
		);
	}

	/** @inheritDoc */
	public function fetch_occurrences() {
		$health = $this->health();
		if ( is_wp_error( $health ) ) {
			return $health;
		}
		$source_refs = $this->enabled_source_refs;
		if ( empty( $source_refs ) ) {
			return array();
		}

		$occurrences = array();
		$seen        = array();
		$page        = 1;
		do {
			if ( $page > 100 ) {
				return new \WP_Error( 'bcf_provider_page_limit', 'Calendar provider response exceeded the safe page limit.' );
			}
			$batch = call_user_func( $this->query_callback, $page, 200, $source_refs );
			if ( is_wp_error( $batch ) ) {
				return new \WP_Error( 'bcf_provider_query_failed', 'Calendar provider query failed.' );
			}
			if ( ! is_array( $batch ) || ! isset( $batch['records'], $batch['has_more'] ) || ! is_array( $batch['records'] ) ) {
				return new \WP_Error( 'bcf_provider_response_invalid', 'Calendar provider response invalid.' );
			}

			foreach ( $batch['records'] as $record ) {
				$normalized = $this->normalize_record( $record );
				if ( is_wp_error( $normalized ) ) {
					return $normalized;
				}
				$identity = (string) $normalized['occurrence_identity'];
				if ( isset( $seen[ $identity ] ) ) {
					return new \WP_Error( 'bcf_provider_duplicate_storage', 'Calendar provider contains duplicate occurrence storage.' );
				}
				$seen[ $identity ] = true;
				$occurrences[] = $normalized;
			}

			$has_more = true === $batch['has_more'];
			++$page;
		} while ( $has_more );

		return $occurrences;
	}

	/**
	 * Discover and validate the authoritative enabled source-ref set.
	 *
	 * @return array<int,string>|\WP_Error
	 */
	private function discover_enabled_source_refs() {
		if ( null !== $this->enabled_source_refs ) {
			return $this->enabled_source_refs;
		}
		$rows = call_user_func( $this->source_query_callback );
		if ( is_wp_error( $rows ) ) {
			return new \WP_Error( 'bcf_provider_source_discovery_failed', 'Calendar provider source discovery failed.' );
		}
		if ( ! is_array( $rows ) ) {
			return new \WP_Error( 'bcf_provider_source_discovery_invalid', 'Calendar provider source discovery invalid.' );
		}

		$allowed_keys = array( 'act_slug', 'enabled', 'label', 'policy', 'source_ref' );
		$seen         = array();
		$enabled      = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				return new \WP_Error( 'bcf_provider_source_discovery_invalid', 'Calendar provider source discovery invalid.' );
			}
			$keys = array_keys( $row );
			sort( $keys );
			if ( $allowed_keys !== $keys || ! is_string( $row['source_ref'] ) || ! $this->is_hash( $row['source_ref'] ) || ! is_string( $row['label'] ) || ! is_string( $row['policy'] ) || ! is_string( $row['act_slug'] ) || ! is_bool( $row['enabled'] ) ) {
				return new \WP_Error( 'bcf_provider_source_discovery_invalid', 'Calendar provider source discovery invalid.' );
			}
			if ( isset( $seen[ $row['source_ref'] ] ) ) {
				return new \WP_Error( 'bcf_provider_source_discovery_invalid', 'Calendar provider source discovery invalid.' );
			}
			$seen[ $row['source_ref'] ] = true;
			if ( $row['enabled'] ) {
				$enabled[] = $row['source_ref'];
				$this->enabled_source_metadata[ $row['source_ref'] ] = array(
					'label'  => $this->text( $row['label'], 160 ),
					'policy' => $this->text( $row['policy'], 80 ),
				);
			} else {
				++$this->excluded_source_count;
			}
		}

		$this->enabled_source_refs = $enabled;
		return $this->enabled_source_refs;
	}

	/**
	 * Normalize only allowlisted contract fields and discard Intake identifiers.
	 *
	 * @param mixed $record Contract record.
	 * @return array<string,mixed>|\WP_Error
	 */
	private function normalize_record( $record ) {
		if ( ! is_array( $record ) || self::CONTRACT_VERSION !== (int) ( $record['contract_version'] ?? 0 ) ) {
			return new \WP_Error( 'bcf_provider_record_contract_invalid', 'Calendar provider occurrence invalid.' );
		}

		$occurrence_key     = isset( $record['occurrence_key'] ) ? (string) $record['occurrence_key'] : '';
		$source_ref         = isset( $record['source_ref'] ) ? (string) $record['source_ref'] : '';
		$source_fingerprint = isset( $record['source_fingerprint'] ) ? (string) $record['source_fingerprint'] : '';
		if ( ! $this->is_hash( $occurrence_key ) || ! $this->is_hash( $source_ref ) || ! $this->is_hash( $source_fingerprint ) ) {
			return new \WP_Error( 'bcf_provider_record_identity_invalid', 'Calendar provider occurrence invalid.' );
		}
		if ( ! isset( $this->enabled_source_metadata[ $source_ref ] ) ) {
			return new \WP_Error( 'bcf_provider_record_source_invalid', 'Calendar provider occurrence source invalid.' );
		}

		$has_supersession_fields = array_key_exists( 'superseded', $record ) || array_key_exists( 'superseded_by_occurrence_ref', $record );
		$superseded              = true === ( $record['superseded'] ?? false );
		$successor_ref            = isset( $record['superseded_by_occurrence_ref'] ) ? (string) $record['superseded_by_occurrence_ref'] : '';
		$supersession_valid       = ! $has_supersession_fields;
		if ( $has_supersession_fields && is_bool( $record['superseded'] ?? null ) ) {
			$supersession_valid = $superseded ? $this->is_hash( $successor_ref ) : '' === $successor_ref;
		}

		$start = $this->normalize_time( $record['start'] ?? null );
		$end   = $this->normalize_time( $record['end'] ?? null );
		if ( is_wp_error( $start ) || is_wp_error( $end ) || $end['timestamp'] <= $start['timestamp'] || $start['kind'] !== $end['kind'] ) {
			return new \WP_Error( 'bcf_provider_record_time_invalid', 'Calendar provider occurrence time invalid.' );
		}

		return array(
			'provider_id'          => $this->get_id(),
			'occurrence_identity'  => $occurrence_key,
			'source_ref'           => $source_ref,
			'source_fingerprint'   => $source_fingerprint,
			'source_label'         => $this->enabled_source_metadata[ $source_ref ]['label'],
			'source_policy'        => $this->enabled_source_metadata[ $source_ref ]['policy'],
			'superseded'           => $superseded,
			'superseded_by_occurrence_ref' => $supersession_valid && $superseded ? $successor_ref : '',
			'supersession_reference_valid' => $supersession_valid,
			'act_slug'             => $this->text( $record['act_slug'] ?? '', 200 ),
			'classification'       => $this->text( $record['classification'] ?? 'unknown', 80 ),
			'classification_provenance' => $this->text( $record['classification_provenance'] ?? 'unknown', 80 ),
			'review_state'         => $this->text( $record['review_state'] ?? 'needs_review', 80 ),
			'source_state'         => $this->text( $record['source_state'] ?? 'unknown', 80 ),
			'google_status'        => $this->text( $record['google_status'] ?? 'unknown', 80 ),
			'visibility'           => $this->text( $record['visibility'] ?? 'unknown', 80 ),
			'summary'              => $this->text( $record['summary'] ?? '', 500 ),
			'venue_name'           => $this->text( $record['venue_name'] ?? '', 200 ),
			'city'                 => $this->text( $record['city'] ?? '', 120 ),
			'state'                => $this->text( $record['state'] ?? '', 64 ),
			'start'                => $start,
			'end'                  => $end,
			'all_day'              => ! empty( $record['all_day'] ),
			'recurring_occurrence' => ! empty( $record['recurring'] ),
		);
	}

	/**
	 * Normalize a contract time endpoint.
	 *
	 * @param mixed $endpoint Time endpoint.
	 * @return array<string,mixed>|\WP_Error
	 */
	private function normalize_time( $endpoint ) {
		if ( ! is_array( $endpoint ) ) {
			return new \WP_Error( 'bcf_provider_time_invalid', 'Calendar provider time invalid.' );
		}
		$kind      = isset( $endpoint['kind'] ) ? (string) $endpoint['kind'] : '';
		$value     = isset( $endpoint['value'] ) ? (string) $endpoint['value'] : '';
		$timezone  = isset( $endpoint['timezone'] ) ? (string) $endpoint['timezone'] : '';
		$timestamp = isset( $endpoint['timestamp'] ) ? (int) $endpoint['timestamp'] : 0;
		if ( ! in_array( $kind, array( 'date', 'dateTime' ), true ) || '' === $value || $timestamp <= 0 ) {
			return new \WP_Error( 'bcf_provider_time_invalid', 'Calendar provider time invalid.' );
		}
		if ( 'date' === $kind && 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return new \WP_Error( 'bcf_provider_time_invalid', 'Calendar provider time invalid.' );
		}

		return array(
			'kind'      => $kind,
			'value'     => $this->text( $value, 128 ),
			'timezone'  => $this->text( $timezone, 128 ),
			'timestamp' => $timestamp,
		);
	}

	/** @param string $value Candidate hash. */
	private function is_hash( $value ) {
		return 1 === preg_match( '/^[a-f0-9]{64}$/', (string) $value );
	}

	/**
	 * Bound a safe-contract scalar without interpreting private source data.
	 *
	 * @param mixed $value Candidate value.
	 * @param int   $limit Maximum bytes/characters.
	 * @return string
	 */
	private function text( $value, $limit ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$text = trim( preg_replace( '/[\r\n\t]+/u', ' ', (string) $value ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $limit ) : substr( $text, 0, $limit );
	}
}
