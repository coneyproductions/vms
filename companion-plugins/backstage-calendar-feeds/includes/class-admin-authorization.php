<?php
/**
 * Small testable authorization boundary for administrator mutations.
 */

namespace ConeyProductions\BackstageCalendarFeeds;

final class Admin_Authorization {
	/** @param bool $can_manage Capability result. @param bool $nonce_valid Nonce result. */
	public static function can_rotate_secret( $can_manage, $nonce_valid ) {
		return true === $can_manage && true === $nonce_valid;
	}
}
