<?php
/**
 * Provider abstraction for normalized calendar occurrences.
 */

namespace ConeyProductions\BackstageCalendarFeeds;

interface Provider_Interface {
	/**
	 * Return the stable provider identifier.
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Return provider health or a fail-closed error.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public function health();

	/**
	 * Return normalized provider occurrences or a fail-closed error.
	 *
	 * @return array<int,array<string,mixed>>|\WP_Error
	 */
	public function fetch_occurrences();
}
