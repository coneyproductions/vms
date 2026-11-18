<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_shortcode_event_ticket_button_legacy_noop')) {
	/**
	 * Deprecated compatibility shortcode.
	 *
	 * Older Serenade Range sidebar widgets still contain [event_ticket_button].
	 * The CTA is no longer needed because the real TEC/Woo ticket form already
	 * renders on the event page. Keep the tag registered as a no-op so the
	 * literal shortcode text does not leak into public output.
	 */
	function bvmgr_shortcode_event_ticket_button_legacy_noop($atts = array(), $content = '', string $tag = 'event_ticket_button'): string
	{
		unset($atts, $content, $tag);
		return '';
	}
}

add_shortcode('event_ticket_button', 'bvmgr_shortcode_event_ticket_button_legacy_noop');
