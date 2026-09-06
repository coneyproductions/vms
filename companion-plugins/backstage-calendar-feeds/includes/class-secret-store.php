<?php
/**
 * Capability-token generation, encrypted storage, and constant-time validation.
 */

namespace ConeyProductions\BackstageCalendarFeeds;

final class Secret_Store {
	const OPTION_NAME = 'bcf_feed_profile_secrets';

	/**
	 * Return an existing profile token or create one with cryptographic randomness.
	 *
	 * @param string $profile_id Profile identifier.
	 * @return string|\WP_Error
	 */
	public function get_or_create( $profile_id ) {
		$token = $this->get_token( $profile_id );
		return is_wp_error( $token ) ? $this->rotate( $profile_id ) : $token;
	}

	/**
	 * Return a decryptable profile token.
	 *
	 * @param string $profile_id Profile identifier.
	 * @return string|\WP_Error
	 */
	public function get_token( $profile_id ) {
		$entries = get_option( self::OPTION_NAME, array() );
		$entry   = is_array( $entries ) && isset( $entries[ $profile_id ] ) && is_array( $entries[ $profile_id ] ) ? $entries[ $profile_id ] : array();
		if ( empty( $entry['hash'] ) || empty( $entry['ciphertext'] ) || empty( $entry['nonce'] ) ) {
			return new \WP_Error( 'bcf_secret_unavailable', 'Feed secret unavailable.' );
		}
		$token = $this->decrypt( $entry );
		if ( is_wp_error( $token ) || ! self::validate_hash( $token, (string) $entry['hash'] ) ) {
			return new \WP_Error( 'bcf_secret_unavailable', 'Feed secret unavailable.' );
		}
		return $token;
	}

	/**
	 * Rotate one profile capability token.
	 *
	 * @param string $profile_id Profile identifier.
	 * @return string|\WP_Error
	 */
	public function rotate( $profile_id ) {
		try {
			$token = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
		} catch ( \Throwable $throwable ) {
			return new \WP_Error( 'bcf_secret_generation_failed', 'Feed secret generation failed.' );
		}
		$encrypted = $this->encrypt( $token );
		if ( is_wp_error( $encrypted ) ) {
			return $encrypted;
		}
		$encrypted['hash']       = self::token_hash( $token );
		$encrypted['rotated_at'] = gmdate( 'c' );

		$entries = get_option( self::OPTION_NAME, array() );
		$entries = is_array( $entries ) ? $entries : array();
		$entries[ $profile_id ] = $encrypted;
		if ( false === update_option( self::OPTION_NAME, $entries, false ) && get_option( self::OPTION_NAME ) !== $entries ) {
			return new \WP_Error( 'bcf_secret_storage_failed', 'Feed secret storage failed.' );
		}
		return $token;
	}

	/**
	 * Validate a presented token against one profile hash.
	 *
	 * @param string $profile_id Profile identifier.
	 * @param string $token      Presented token.
	 * @return bool
	 */
	public function validate( $profile_id, $token ) {
		$entries = get_option( self::OPTION_NAME, array() );
		$hash    = is_array( $entries ) && isset( $entries[ $profile_id ]['hash'] ) ? (string) $entries[ $profile_id ]['hash'] : '';
		return self::validate_hash( $token, $hash );
	}

	/** @param string $token Token. @return string */
	public static function token_hash( $token ) {
		return hash( 'sha256', "bcf-feed-secret-v1\n" . (string) $token );
	}

	/** @param string $token Token. @param string $expected_hash Expected hash. @return bool */
	public static function validate_hash( $token, $expected_hash ) {
		return 1 === preg_match( '/^[A-Za-z0-9_-]{43}$/', (string) $token )
			&& 1 === preg_match( '/^[a-f0-9]{64}$/', (string) $expected_hash )
			&& hash_equals( (string) $expected_hash, self::token_hash( $token ) );
	}

	/** @param string $token Plain token. @return array<string,string>|\WP_Error */
	private function encrypt( $token ) {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return new \WP_Error( 'bcf_secret_crypto_unavailable', 'Feed secret encryption unavailable.' );
		}
		try {
			$nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$ciphertext = sodium_crypto_secretbox( $token, $nonce, $this->encryption_key() );
		} catch ( \Throwable $throwable ) {
			return new \WP_Error( 'bcf_secret_crypto_failed', 'Feed secret encryption failed.' );
		}
		return array(
			'algorithm'  => 'sodium-secretbox-v1',
			'nonce'      => base64_encode( $nonce ),
			'ciphertext' => base64_encode( $ciphertext ),
		);
	}

	/** @param array<string,mixed> $entry Stored entry. @return string|\WP_Error */
	private function decrypt( $entry ) {
		if ( 'sodium-secretbox-v1' !== (string) ( $entry['algorithm'] ?? '' ) || ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return new \WP_Error( 'bcf_secret_crypto_unavailable', 'Feed secret encryption unavailable.' );
		}
		$nonce      = base64_decode( (string) $entry['nonce'], true );
		$ciphertext = base64_decode( (string) $entry['ciphertext'], true );
		if ( false === $nonce || false === $ciphertext || SODIUM_CRYPTO_SECRETBOX_NONCEBYTES !== strlen( $nonce ) ) {
			return new \WP_Error( 'bcf_secret_ciphertext_invalid', 'Feed secret unavailable.' );
		}
		$token = sodium_crypto_secretbox_open( $ciphertext, $nonce, $this->encryption_key() );
		return false === $token ? new \WP_Error( 'bcf_secret_ciphertext_invalid', 'Feed secret unavailable.' ) : $token;
	}

	/** @return string */
	private function encryption_key() {
		return hash( 'sha256', wp_salt( 'auth' ) . "\nbcf-feed-secret-encryption-v1", true );
	}
}
