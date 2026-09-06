<?php
/**
 * Runtime feed-profile configuration.
 */

namespace ConeyProductions\BackstageCalendarFeeds;

/**
 * Return configured feed profiles.
 *
 * The first consumer's act presentation mapping intentionally lives here rather
 * than in the reusable provider, policy, formatter, or routing layers.
 *
 * @return array<string,array<string,mixed>>
 */
function bcf_get_profiles() {
	$profiles = array(
		'dalene-band-availability' => array(
			'id'                          => 'dalene-band-availability',
			'label'                       => 'Dalene — BAND Availability',
			'provider'                    => 'drm-calendar-intake',
			'provider_label'              => 'DRM Calendar Intake',
			'policy'                      => 'strict-availability',
			'policy_label'                => 'Strict Availability',
			'format'                      => 'ics',
			'format_label'                => 'ICS',
			'timezone'                    => 'America/Chicago',
			'cancellation_retention_days' => 45,
			'publication_history_retention_days' => 90,
			'act_names'                   => array(
				'dalene-richelle-solo' => 'Dālene Richelle',
				'texanadian'           => 'Texanadian',
				'the-crows'            => 'The Crows',
				'heroes-and-wreckers'  => 'Heroes & Wreckers',
				'shania-twang'         => 'Shania Twang',
				'big-little-town'      => 'Big Little Town',
				'super-trouper'        => 'Super Trouper',
				'fun-and-funner'       => 'Fun & Funner',
				'jingle'                => 'Jingle!',
			),
		),
	);

	return function_exists( 'apply_filters' )
		? apply_filters( 'bcf_feed_profiles', $profiles )
		: $profiles;
}

/**
 * Return one configured feed profile.
 *
 * @param string $profile_id Profile identifier.
 * @return array<string,mixed>|null
 */
function bcf_get_profile( $profile_id ) {
	$profiles = bcf_get_profiles();
	return isset( $profiles[ $profile_id ] ) && is_array( $profiles[ $profile_id ] ) ? $profiles[ $profile_id ] : null;
}
