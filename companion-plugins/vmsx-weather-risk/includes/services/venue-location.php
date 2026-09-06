<?php

defined('ABSPATH') || exit;

if (!class_exists('VMSX_Weather_Risk_Venue_Location')) {
	class VMSX_Weather_Risk_Venue_Location {
		private const GEO_SOURCE_META = '_vmsx_wr_geo_source';
		private const GEO_HASH_META = '_vmsx_wr_geo_address_hash';
		private const GEO_RESOLVED_AT_META = '_vmsx_wr_geo_resolved_at';

		public static function init(): void
		{
			add_action('save_post_vms_venue', array(__CLASS__, 'handle_venue_save'), 40, 3);
		}

		public static function handle_venue_save(int $venue_id, $post = null, bool $update = false): void
		{
			$venue_id = absint($venue_id);
			if ($venue_id <= 0) {
				return;
			}

			if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
				return;
			}

			if (wp_is_post_autosave($venue_id) || wp_is_post_revision($venue_id)) {
				return;
			}

			if (get_post_type($venue_id) !== 'vms_venue') {
				return;
			}

			self::maybe_auto_geocode_venue($venue_id, array('trigger' => 'venue_save'));
		}

		public static function resolve(int $event_plan_id, array $settings): array
		{
			$venue_key = VMSX_Weather_Risk_Helpers::event_meta_key('venue_id', '_vms_venue_id');
			$venue_id = (int) get_post_meta($event_plan_id, $venue_key, true);
			$tec_event_id = (int) get_post_meta($event_plan_id, VMSX_Weather_Risk_Helpers::event_meta_key('tec_event_id', '_vms_tec_event_id'), true);
			if ($tec_event_id <= 0 && function_exists('vms_ticketing_b_get_linked_tec_event_id')) {
				$tec_event_id = (int) vms_ticketing_b_get_linked_tec_event_id($event_plan_id);
			}
			$tec_venue_id = self::get_linked_tec_venue_id($venue_id, $tec_event_id);
			if ($tec_venue_id <= 0 && function_exists('vms_tec_get_tec_venue_id_for_plan')) {
				$tec_venue_id = (int) vms_tec_get_tec_venue_id_for_plan($event_plan_id);
			}

			$context = array(
				'event_plan_id' => $event_plan_id,
				'venue_id' => $venue_id,
				'label' => '',
				'address_string' => '',
				'zip' => '',
				'country' => 'US',
				'latitude' => null,
				'longitude' => null,
				'source' => 'missing',
				'warnings' => array(),
			);

			$venue_name = $venue_id > 0 ? VMSX_Weather_Risk_Helpers::clean_display_text((string) get_the_title($venue_id)) : '';
			$address = '';
			$city = '';
			$state = '';
			$zip = '';
			$country = '';
			$latitude = null;
			$longitude = null;
			$address_source = 'missing';

			if ($venue_id > 0) {
				$location = self::get_venue_location_parts($venue_id);
				$address = (string) ($location['address'] ?? '');
				$city = (string) ($location['city'] ?? '');
				$state = (string) ($location['state'] ?? '');
				$zip = (string) ($location['zip'] ?? '');
				$country = (string) ($location['country'] ?? '');
				if ($address !== '' || $city !== '' || $state !== '' || $zip !== '' || $country !== '') {
					$address_source = 'vms_venue';
				}
				$coords = self::get_saved_venue_coordinates($venue_id);
				if (is_array($coords)) {
					$latitude = (float) $coords['latitude'];
					$longitude = (float) $coords['longitude'];
					$context['source'] = 'venue_coordinates';
				}
			}

			$tec_location = $tec_venue_id > 0 ? self::get_tec_venue_location_parts($tec_venue_id) : array();
			if ($venue_id > 0 && !empty($tec_location)) {
				self::hydrate_vms_venue_from_tec($venue_id, $tec_location);
				if ($address === '') {
					$address = (string) ($tec_location['address'] ?? '');
					if ($address !== '') {
						$address_source = 'linked_tec_venue';
					}
				}
				if ($city === '') {
					$city = (string) ($tec_location['city'] ?? '');
				}
				if ($state === '') {
					$state = (string) ($tec_location['state'] ?? '');
				}
				if ($zip === '') {
					$zip = (string) ($tec_location['zip'] ?? '');
				}
				if ($country === '') {
					$country = (string) ($tec_location['country'] ?? '');
				}
				if ((!is_numeric($latitude) || !is_numeric($longitude)) && isset($tec_location['latitude'], $tec_location['longitude']) && is_numeric($tec_location['latitude']) && is_numeric($tec_location['longitude'])) {
					$latitude = (float) $tec_location['latitude'];
					$longitude = (float) $tec_location['longitude'];
					self::persist_venue_coordinates($venue_id, $latitude, $longitude, '', 'linked_tec_venue');
					self::sync_linked_tec_venue($venue_id);
					$context['source'] = 'linked_tec_venue';
					$context['warnings'][] = __('Coordinates were imported from the linked TEC venue and stored on the VMS venue for future use.', 'vmsx-weather-risk');
				}
			}

			if ($address === '') {
				$address = self::combine_address_lines(
					self::first_meta_value($event_plan_id, array('_vms_address', '_vms_addr1', 'address')),
					self::first_meta_value($event_plan_id, array('_vms_address_2', '_vms_addr2', 'address_2'))
				);
				if ($address !== '') {
					$address_source = 'event_plan_meta';
				}
			}
			if ($city === '') {
				$city = self::first_meta_value($event_plan_id, array('_vms_city', 'city'));
			}
			if ($state === '') {
				$state = self::first_meta_value($event_plan_id, array('_vms_state', 'state'));
			}
			if ($zip === '') {
				$zip = self::first_meta_value($event_plan_id, array('_vms_zip', 'zip', 'postal_code'));
			}
			if ($country === '') {
				$country = self::first_meta_value($event_plan_id, array('_vms_country', 'country'));
			}
			if (!is_numeric($latitude)) {
				$latitude = self::first_numeric_meta_value($event_plan_id, array('_vms_latitude', '_vms_lat', 'latitude', 'lat'));
				$event_lng = self::first_numeric_meta_value($event_plan_id, array('_vms_longitude', '_vms_lng', 'longitude', 'lng'));
				if (is_numeric($latitude) && is_numeric($event_lng)) {
					$longitude = (float) $event_lng;
					$context['source'] = 'event_coordinates';
				}
			}
			if (!is_numeric($longitude)) {
				$longitude = self::first_numeric_meta_value($event_plan_id, array('_vms_longitude', '_vms_lng', 'longitude', 'lng'));
				if (is_numeric($latitude) && is_numeric($longitude)) {
					$context['source'] = 'event_coordinates';
				}
			}

			if (($address === '' || !is_numeric($latitude) || !is_numeric($longitude)) && $tec_event_id > 0) {
				if ($address === '') {
					$address = self::combine_address_lines(
						self::first_meta_value($tec_event_id, array('_VenueAddress', '_EventVenueAddress', 'address')),
						self::first_meta_value($tec_event_id, array('_VenueAddress2', '_EventVenueAddress2', 'address_2'))
					);
					if ($address !== '') {
						$address_source = 'tec_event_meta';
					}
				}
				if ($city === '') {
					$city = self::first_meta_value($tec_event_id, array('_VenueCity', '_EventVenueCity', 'city'));
				}
				if ($state === '') {
					$state = self::first_meta_value($tec_event_id, array('_VenueStateProvince', '_VenueState', 'state'));
				}
				if ($zip === '') {
					$zip = self::first_meta_value($tec_event_id, array('_VenueZip', 'zip'));
				}
				if ($country === '') {
					$country = self::first_meta_value($tec_event_id, array('_VenueCountry', 'country'));
				}
			}

			$context['address_string'] = self::build_address_string($address, $city, $state, $zip, $country);
			$context['zip'] = $zip;
			$context['country'] = self::normalize_country_code($country);
			if ($context['country'] === '') {
				$context['country'] = 'US';
			}

			VMSX_Weather_Risk_Logging::write(
				'Show Risk location resolution attempt.',
				array(
					'event_plan_id' => $event_plan_id,
					'source' => 'venue_location',
					'venue_id' => $venue_id,
					'tec_event_id' => $tec_event_id,
					'tec_venue_id' => $tec_venue_id,
					'address_source' => $address_source,
					'address_attempt' => $context['address_string'],
					'trigger' => 'resolve',
				),
				'info'
			);

			if ((!is_numeric($latitude) || !is_numeric($longitude)) && $venue_id > 0) {
				$auto_resolved = self::maybe_auto_geocode_venue($venue_id, array(
					'address_string' => $context['address_string'],
					'tec_venue_id' => $tec_venue_id,
					'trigger' => 'event_refresh',
				));
				if (is_array($auto_resolved) && isset($auto_resolved['latitude'], $auto_resolved['longitude']) && is_numeric($auto_resolved['latitude']) && is_numeric($auto_resolved['longitude'])) {
					$latitude = (float) $auto_resolved['latitude'];
					$longitude = (float) $auto_resolved['longitude'];
					$context['source'] = (string) ($auto_resolved['source'] ?? 'geocoded_address');
					if (($auto_resolved['source'] ?? '') === 'linked_tec_venue') {
						$context['warnings'][] = __('Coordinates were imported from the linked TEC venue and stored for future use.', 'vmsx-weather-risk');
					} else {
						$context['warnings'][] = __('Coordinates were auto-resolved from the saved venue address and stored for future use.', 'vmsx-weather-risk');
					}
				}
			}

			if (!is_numeric($latitude) || !is_numeric($longitude)) {
				$geocoded = self::geocode_address($context['address_string'], array(
					'event_plan_id' => $event_plan_id,
					'venue_id' => $venue_id,
					'tec_event_id' => $tec_event_id,
					'tec_venue_id' => $tec_venue_id,
					'address_source' => $address_source,
					'trigger' => 'event_refresh_geocode',
				));
				if ($geocoded) {
					$latitude = $geocoded['latitude'];
					$longitude = $geocoded['longitude'];
					$context['source'] = 'geocoded_address';
					if ($venue_id > 0) {
						self::persist_venue_coordinates($venue_id, (float) $latitude, (float) $longitude, md5(strtolower((string) $context['address_string'])), 'auto_geocoded_address');
						self::sync_linked_tec_venue($venue_id);
					}
					$context['warnings'][] = __('Coordinates were geocoded from the saved event address during this refresh and saved for future use.', 'vmsx-weather-risk');
				}
			}

			if ((!is_numeric($latitude) || !is_numeric($longitude)) && $settings['fallback_latitude'] !== '' && $settings['fallback_longitude'] !== '') {
				$latitude = (float) $settings['fallback_latitude'];
				$longitude = (float) $settings['fallback_longitude'];
				$context['source'] = 'settings_fallback';
				$context['warnings'][] = __('Using fallback coordinates from Show Risk settings because this event could not be auto-located from its saved address.', 'vmsx-weather-risk');
			}

			if ($context['address_string'] !== '') {
				$context['label'] = $context['address_string'];
			} elseif ($venue_name !== '') {
				$context['label'] = $venue_name;
			} elseif (!empty($settings['fallback_location_label'])) {
				$context['label'] = sanitize_text_field((string) $settings['fallback_location_label']);
			}

			if (is_numeric($latitude) && is_numeric($longitude)) {
				$context['latitude'] = round((float) $latitude, 6);
				$context['longitude'] = round((float) $longitude, 6);
			} else {
				$context['warnings'][] = __('This event could not be mapped from its saved address, so provider forecasts could not be requested. Save a complete venue address or add fallback coordinates in Show Risk Settings.', 'vmsx-weather-risk');
			}

			if ($context['source'] === 'missing' && !empty($context['address_string'])) {
				$context['source'] = 'address_only';
			}

			return $context;
		}

		public static function maybe_auto_geocode_venue(int $venue_id, array $args = array()): ?array
		{
			$venue_id = absint($venue_id);
			if ($venue_id <= 0 || get_post_type($venue_id) !== 'vms_venue') {
				return null;
			}

			$args = wp_parse_args($args, array(
				'address_string' => '',
				'tec_venue_id' => 0,
				'trigger' => 'runtime',
			));

			$address_string = trim((string) $args['address_string']);
			$tec_venue_id = absint($args['tec_venue_id']);
			if ($tec_venue_id <= 0) {
				$tec_venue_id = self::get_linked_tec_venue_id($venue_id, 0);
			}

			if ($address_string === '') {
				$location = self::get_venue_location_parts($venue_id);
				$address_string = self::build_address_string(
					(string) ($location['address'] ?? ''),
					(string) ($location['city'] ?? ''),
					(string) ($location['state'] ?? ''),
					(string) ($location['zip'] ?? ''),
					(string) ($location['country'] ?? '')
				);
			}

			$address_string = trim($address_string);
			$current = self::get_saved_venue_coordinates($venue_id);
			$source = trim((string) get_post_meta($venue_id, self::GEO_SOURCE_META, true));
			$stored_hash = trim((string) get_post_meta($venue_id, self::GEO_HASH_META, true));
			$address_hash = $address_string !== '' ? md5(strtolower($address_string)) : '';
			$source_is_auto = ($source === 'auto_geocoded_address');
			$has_current = is_array($current) && isset($current['latitude'], $current['longitude']) && is_numeric($current['latitude']) && is_numeric($current['longitude']);

			if ($has_current && (!$source_is_auto || $stored_hash === $address_hash || $address_hash === '')) {
				if ($source_is_auto && $stored_hash === '' && $address_hash !== '') {
					update_post_meta($venue_id, self::GEO_HASH_META, $address_hash);
				}
				return array(
					'latitude' => round((float) $current['latitude'], 6),
					'longitude' => round((float) $current['longitude'], 6),
					'source' => $source !== '' ? $source : ($source_is_auto ? 'auto_geocoded_address' : 'venue_coordinates'),
				);
			}

			if ($tec_venue_id > 0) {
				$tec_location = self::get_tec_venue_location_parts($tec_venue_id);
				self::hydrate_vms_venue_from_tec($venue_id, $tec_location);
				if (isset($tec_location['latitude'], $tec_location['longitude']) && is_numeric($tec_location['latitude']) && is_numeric($tec_location['longitude'])) {
					$lat = round((float) $tec_location['latitude'], 6);
					$lng = round((float) $tec_location['longitude'], 6);
					self::persist_venue_coordinates($venue_id, $lat, $lng, $address_hash, 'linked_tec_venue');
					self::sync_linked_tec_venue($venue_id);
					VMSX_Weather_Risk_Logging::write(
						'Show Risk imported venue coordinates from the linked TEC venue.',
						array(
							'source' => 'venue_location',
							'venue_id' => $venue_id,
							'tec_venue_id' => $tec_venue_id,
							'trigger' => sanitize_key((string) $args['trigger']),
							'latitude' => $lat,
							'longitude' => $lng,
						),
						'info'
					);
					return array(
						'latitude' => $lat,
						'longitude' => $lng,
						'source' => 'linked_tec_venue',
					);
				}
				if ($address_string === '') {
					$address_string = self::build_address_string(
						(string) ($tec_location['address'] ?? ''),
						(string) ($tec_location['city'] ?? ''),
						(string) ($tec_location['state'] ?? ''),
						(string) ($tec_location['zip'] ?? ''),
						(string) ($tec_location['country'] ?? '')
					);
					$address_hash = $address_string !== '' ? md5(strtolower($address_string)) : '';
				}
			}

			if ($address_string === '') {
				return null;
			}

			$location = self::get_venue_location_parts($venue_id);
			$geocoded = self::geocode_address($address_string, array(
				'venue_id' => $venue_id,
				'tec_venue_id' => $tec_venue_id,
				'trigger' => sanitize_key((string) $args['trigger']),
				'street' => (string) ($location['address'] ?? ''),
				'city' => (string) ($location['city'] ?? ''),
				'state' => (string) ($location['state'] ?? ''),
				'zip' => (string) ($location['zip'] ?? ''),
				'country' => (string) ($location['country'] ?? ''),
			));
			if (!is_array($geocoded) || !isset($geocoded['latitude'], $geocoded['longitude']) || !is_numeric($geocoded['latitude']) || !is_numeric($geocoded['longitude'])) {
				return null;
			}

			$lat = round((float) $geocoded['latitude'], 6);
			$lng = round((float) $geocoded['longitude'], 6);
			self::persist_venue_coordinates($venue_id, $lat, $lng, $address_hash, 'auto_geocoded_address');
			self::sync_linked_tec_venue($venue_id);
			VMSX_Weather_Risk_Logging::write(
				'Show Risk auto-resolved venue coordinates from the saved address.',
				array(
					'source' => 'venue_location',
					'venue_id' => $venue_id,
					'trigger' => sanitize_key((string) $args['trigger']),
					'latitude' => $lat,
					'longitude' => $lng,
				),
				'info'
			);

			return array(
				'latitude' => $lat,
				'longitude' => $lng,
				'source' => 'auto_geocoded_address',
			);
		}

		private static function get_venue_location_parts(int $venue_id): array
		{
			$canonical = function_exists('vms_get_venue_location_data') ? (array) vms_get_venue_location_data($venue_id) : array();

			$location = array(
				'address' => self::combine_address_lines(
					trim((string) ($canonical['address'] ?? '')),
					trim((string) ($canonical['address_2'] ?? ''))
				),
				'city' => trim((string) ($canonical['city'] ?? '')),
				'state' => trim((string) ($canonical['state'] ?? '')),
				'zip' => trim((string) ($canonical['zip'] ?? '')),
				'country' => trim((string) ($canonical['country'] ?? '')),
			);

			if ($location['address'] === '') {
				$location['address'] = self::combine_address_lines(
					self::first_meta_value($venue_id, array(
						VMSX_Weather_Risk_Helpers::venue_meta_key('address', '_vms_address'),
						'_vms_addr1',
						'_venue_address',
						'address',
					)),
					self::first_meta_value($venue_id, array(
						VMSX_Weather_Risk_Helpers::venue_meta_key('address_2', '_vms_address_2'),
						'_vms_addr2',
						'address_2',
					))
				);
			}
			if ($location['city'] === '') {
				$location['city'] = self::first_meta_value($venue_id, array(VMSX_Weather_Risk_Helpers::venue_meta_key('city', '_vms_city'), '_venue_city', 'city'));
			}
			if ($location['state'] === '') {
				$location['state'] = self::first_meta_value($venue_id, array(VMSX_Weather_Risk_Helpers::venue_meta_key('state', '_vms_state'), '_venue_state', 'state'));
			}
			if ($location['zip'] === '') {
				$location['zip'] = self::first_meta_value($venue_id, array(VMSX_Weather_Risk_Helpers::venue_meta_key('zip', '_vms_zip'), 'zip', 'postal_code'));
			}
			if ($location['country'] === '') {
				$location['country'] = self::first_meta_value($venue_id, array(VMSX_Weather_Risk_Helpers::venue_meta_key('country', '_vms_country'), 'country'));
			}

			return $location;
		}

		private static function get_tec_venue_location_parts(int $tec_venue_id): array
		{
			$tec_venue_id = absint($tec_venue_id);
			if ($tec_venue_id <= 0 || !get_post_status($tec_venue_id)) {
				return array();
			}

			return array(
				'address' => self::combine_address_lines(
					self::first_meta_value($tec_venue_id, array('_VenueAddress', '_EventVenueAddress', 'address')),
					self::first_meta_value($tec_venue_id, array('_VenueAddress2', '_EventVenueAddress2', 'address_2'))
				),
				'city' => self::first_meta_value($tec_venue_id, array('_VenueCity', '_EventVenueCity', 'city')),
				'state' => self::first_meta_value($tec_venue_id, array('_VenueStateProvince', '_VenueState', 'state')),
				'zip' => self::first_meta_value($tec_venue_id, array('_VenueZip', 'zip', 'postal_code')),
				'country' => self::first_meta_value($tec_venue_id, array('_VenueCountry', 'country')),
				'latitude' => self::first_numeric_meta_value($tec_venue_id, array('_VenueLat', '_EventVenueLat', 'lat', 'latitude')),
				'longitude' => self::first_numeric_meta_value($tec_venue_id, array('_VenueLng', '_EventVenueLng', 'lng', 'longitude')),
			);
		}

		private static function hydrate_vms_venue_from_tec(int $venue_id, array $tec_location): void
		{
			if ($venue_id <= 0 || empty($tec_location)) {
				return;
			}

			$updates = array();
			$map = array(
				'address' => VMSX_Weather_Risk_Helpers::venue_meta_key('address', '_vms_address'),
				'address_2' => VMSX_Weather_Risk_Helpers::venue_meta_key('address_2', '_vms_address_2'),
				'city' => VMSX_Weather_Risk_Helpers::venue_meta_key('city', '_vms_city'),
				'state' => VMSX_Weather_Risk_Helpers::venue_meta_key('state', '_vms_state'),
				'zip' => VMSX_Weather_Risk_Helpers::venue_meta_key('zip', '_vms_zip'),
				'country' => VMSX_Weather_Risk_Helpers::venue_meta_key('country', '_vms_country'),
			);

			foreach ($map as $field => $meta_key) {
				$current = trim((string) get_post_meta($venue_id, $meta_key, true));
				$incoming = trim((string) ($tec_location[$field] ?? ''));
				if ($current === '' && $incoming !== '') {
					$updates[$meta_key] = sanitize_text_field($incoming);
				}
			}

			foreach ($updates as $meta_key => $meta_value) {
				update_post_meta($venue_id, $meta_key, $meta_value);
			}
		}

		private static function get_linked_tec_venue_id(int $venue_id, int $tec_event_id = 0): int
		{
			if ($venue_id > 0) {
				$venue_tec_key = VMSX_Weather_Risk_Helpers::venue_meta_key('tec_venue_id', '_vms_tec_venue_id');
				$tec_venue_id = (int) get_post_meta($venue_id, $venue_tec_key, true);
				if ($tec_venue_id > 0) {
					return $tec_venue_id;
				}
			}

			if ($tec_event_id > 0) {
				foreach (array('_EventVenueID', '_VenueVenueID', '_VenueID') as $meta_key) {
					$tec_venue_id = (int) get_post_meta($tec_event_id, $meta_key, true);
					if ($tec_venue_id > 0) {
						return $tec_venue_id;
					}
				}
			}

			return 0;
		}

		private static function get_saved_venue_coordinates(int $venue_id): ?array
		{
			$canonical = function_exists('vms_get_venue_location_data') ? (array) vms_get_venue_location_data($venue_id) : array();
			$latitude = null;
			$longitude = null;
			if (isset($canonical['latitude']) && is_numeric($canonical['latitude'])) {
				$latitude = (float) $canonical['latitude'];
			}
			if (isset($canonical['longitude']) && is_numeric($canonical['longitude'])) {
				$longitude = (float) $canonical['longitude'];
			}
			if (!is_numeric($latitude)) {
				$latitude = self::first_numeric_meta_value($venue_id, array(
					VMSX_Weather_Risk_Helpers::venue_meta_key('latitude', '_vms_latitude'),
					'_vms_lat',
					'lat',
					'latitude',
				));
			}
			if (!is_numeric($longitude)) {
				$longitude = self::first_numeric_meta_value($venue_id, array(
					VMSX_Weather_Risk_Helpers::venue_meta_key('longitude', '_vms_longitude'),
					'_vms_lng',
					'lng',
					'longitude',
				));
			}

			if (!is_numeric($latitude) || !is_numeric($longitude)) {
				return null;
			}

			return array(
				'latitude' => (float) $latitude,
				'longitude' => (float) $longitude,
			);
		}

		private static function persist_venue_coordinates(int $venue_id, float $latitude, float $longitude, string $address_hash = '', string $source = 'auto_geocoded_address'): void
		{
			$latitude_key = VMSX_Weather_Risk_Helpers::venue_meta_key('latitude', '_vms_latitude');
			$longitude_key = VMSX_Weather_Risk_Helpers::venue_meta_key('longitude', '_vms_longitude');
			$lat_string = number_format($latitude, 6, '.', '');
			$lng_string = number_format($longitude, 6, '.', '');

			update_post_meta($venue_id, $latitude_key, $lat_string);
			update_post_meta($venue_id, $longitude_key, $lng_string);
			update_post_meta($venue_id, '_vms_lat', $lat_string);
			update_post_meta($venue_id, '_vms_lng', $lng_string);
			update_post_meta($venue_id, self::GEO_SOURCE_META, sanitize_key($source));
			if ($address_hash !== '') {
				update_post_meta($venue_id, self::GEO_HASH_META, sanitize_text_field($address_hash));
			}
			update_post_meta($venue_id, self::GEO_RESOLVED_AT_META, current_time('mysql'));
		}

		private static function sync_linked_tec_venue(int $venue_id): void
		{
			$sync_venue = VMSX_Weather_Risk_Compatibility::core_function('vms_sync_tec_venue_from_vms_venue');
			if ($sync_venue !== '') {
				$sync_venue($venue_id);
			}
		}

		private static function first_meta_value(int $post_id, array $keys): string
		{
			if ($post_id <= 0) {
				return '';
			}

			foreach ($keys as $key) {
				$key = (string) $key;
				if ($key === '') {
					continue;
				}
				$value = get_post_meta($post_id, $key, true);
				if (is_array($value)) {
					$value = reset($value);
				}
				$value = trim((string) $value);
				if ($value !== '') {
					return sanitize_text_field($value);
				}
			}

			return '';
		}

		private static function first_numeric_meta_value(int $post_id, array $keys)
		{
			if ($post_id <= 0) {
				return null;
			}

			foreach ($keys as $key) {
				$key = (string) $key;
				if ($key === '') {
					continue;
				}
				$value = get_post_meta($post_id, $key, true);
				if (is_array($value)) {
					$value = reset($value);
				}
				if ($value !== '' && $value !== null && is_numeric($value)) {
					return (float) $value;
				}
			}

			return null;
		}

		private static function combine_address_lines(string $line_one, string $line_two): string
		{
			$line_one = trim($line_one);
			$line_two = trim($line_two);
			if ($line_one === '') {
				return $line_two;
			}
			if ($line_two === '') {
				return $line_one;
			}
			return $line_one . ', ' . $line_two;
		}

		private static function build_address_string(string $address, string $city, string $state, string $zip, string $country): string
		{
			$parts = array();
			$address = trim($address);
			$city = trim($city);
			$state = trim($state);
			$zip = trim($zip);
			$country = trim($country);

			if ($address !== '') {
				$parts[] = $address;
			}

			$city_state = trim(implode(', ', array_filter(array($city, $state))));
			if ($city_state !== '' && $zip !== '') {
				$city_state .= ' ' . $zip;
			} elseif ($city_state === '' && $zip !== '') {
				$city_state = $zip;
			}
			if ($city_state !== '') {
				$parts[] = $city_state;
			}

			$normalized_country = self::normalize_country_code($country);
			if ($normalized_country !== '' && $normalized_country !== 'US') {
				$parts[] = $country;
			}

			return implode(', ', array_values(array_unique(array_filter($parts))));
		}

		private static function geocode_address(string $address, array $context = array()): ?array
		{
			$address = trim($address);
			if ($address === '') {
				return null;
			}

			$variants = self::build_geocode_variants($address);
			$cache_key = 'vmsx_wr_geo_' . md5(strtolower(implode('||', $variants)));
			$cached = get_transient($cache_key);
			if (is_array($cached) && isset($cached['latitude'], $cached['longitude']) && is_numeric($cached['latitude']) && is_numeric($cached['longitude'])) {
				return array(
					'latitude' => round((float) $cached['latitude'], 6),
					'longitude' => round((float) $cached['longitude'], 6),
				);
			}

			$attempt_summaries = array();
			$street = trim((string) ($context['street'] ?? ''));
			$city = trim((string) ($context['city'] ?? ''));
			$state = trim((string) ($context['state'] ?? ''));
			$zip = trim((string) ($context['zip'] ?? ''));
			if ($street !== '' && $city !== '' && $state !== '') {
				foreach (self::build_street_variants($street) as $street_variant) {
					$result = self::geocode_via_census_structured($street_variant, $city, $state, $zip);
					$summary = self::summarize_geocode_attempt('census_structured', trim($street_variant . ', ' . $city . ', ' . $state . ($zip !== '' ? ' ' . $zip : '')), $result);
					if ($summary !== '') {
						$attempt_summaries[] = $summary;
					}
					if (is_array($result) && !empty($result['ok']) && isset($result['latitude'], $result['longitude']) && is_numeric($result['latitude']) && is_numeric($result['longitude'])) {
						$resolved = array(
							'latitude' => round((float) $result['latitude'], 6),
							'longitude' => round((float) $result['longitude'], 6),
						);
						set_transient($cache_key, $resolved, WEEK_IN_SECONDS);
						return $resolved;
					}
				}
			}

			$providers = array(
				'census' => array(__CLASS__, 'geocode_via_census'),
				'openmeteo_geocoding' => array(__CLASS__, 'geocode_via_openmeteo'),
				'nominatim' => array(__CLASS__, 'geocode_via_nominatim'),
			);

			foreach ($variants as $variant) {
				foreach ($providers as $provider_key => $callback) {
					$result = call_user_func($callback, $variant);
					$summary = self::summarize_geocode_attempt($provider_key, $variant, $result);
					if ($summary !== '') {
						$attempt_summaries[] = $summary;
					}

					if (is_array($result) && !empty($result['ok']) && isset($result['latitude'], $result['longitude']) && is_numeric($result['latitude']) && is_numeric($result['longitude'])) {
						$resolved = array(
							'latitude' => round((float) $result['latitude'], 6),
							'longitude' => round((float) $result['longitude'], 6),
						);
						set_transient($cache_key, $resolved, WEEK_IN_SECONDS);
						return $resolved;
					}
				}
			}

			VMSX_Weather_Risk_Logging::write(
				'Show Risk could not auto-resolve venue coordinates from the saved address.',
				array_merge(
					array(
						'source' => 'venue_location',
						'address_attempt' => sanitize_text_field($address),
						'address_variants' => $variants,
						'providers_attempted' => array_merge(array('census_structured'), array_keys($providers)),
						'geocode_attempts_summary' => implode(' || ', array_slice($attempt_summaries, 0, 14)),
					),
					$context
				),
				'warning'
			);

			return null;
		}

		private static function build_geocode_variants(string $address): array
		{
			$variants = array();
			$add_variant = static function (string $candidate) use (&$variants): void {
				$candidate = trim((string) preg_replace('/\s+/', ' ', $candidate), ', ');
				if ($candidate === '' || in_array(strtolower($candidate), array_map('strtolower', $variants), true)) {
					return;
				}
				$variants[] = $candidate;
			};

			$add_variant($address);
			$add_variant(preg_replace('/,\s*USA$/i', '', $address));
			$us_address = preg_match('/\b(?:USA|US|United States)\b/i', $address) ? $address : ($address . ', USA');
			$add_variant($us_address);

			$normalized = preg_replace('/\bFarm\s+to\s+Market(?:\s+Road|\s+Rd\.?|)\b/i', 'FM', $address);
			$normalized = preg_replace('/\bCounty\s+Road\b/i', 'CR', (string) $normalized);
			$normalized = preg_replace('/\bState\s+Highway\b/i', 'SH', (string) $normalized);
			$normalized = preg_replace('/\bHighway\b/i', 'Hwy', (string) $normalized);
			$normalized = preg_replace('/\bRoad\b/i', 'Rd', (string) $normalized);
			$normalized = preg_replace('/\bStreet\b/i', 'St', (string) $normalized);
			$normalized = preg_replace('/\bAvenue\b/i', 'Ave', (string) $normalized);
			$normalized = preg_replace('/\bDrive\b/i', 'Dr', (string) $normalized);
			$normalized = preg_replace('/\bLane\b/i', 'Ln', (string) $normalized);
			$normalized = preg_replace('/\bBoulevard\b/i', 'Blvd', (string) $normalized);
			$add_variant((string) $normalized);
			$add_variant(preg_replace('/,\s*USA$/i', '', (string) $normalized));
			if (!preg_match('/\b(?:USA|US|United States)\b/i', (string) $normalized)) {
				$add_variant(trim((string) $normalized, ', ') . ', USA');
			}

			$no_zip = preg_replace('/\s+\d{5}(?:-\d{4})?\b/', '', $address);
			$add_variant((string) $no_zip);
			$add_variant(trim((string) $no_zip, ', ') . ', USA');

			$no_dir = preg_replace('/\b((?:FM|Farm to Market|CR|County Road|SH|State Highway|Hwy|Highway)\s*\d+)\s+[NSEW]\b/i', '$1', (string) $normalized);
			$add_variant((string) $no_dir);
			$add_variant(trim((string) $no_dir, ', ') . ', USA');

			$no_zip_no_dir = preg_replace('/\s+\d{5}(?:-\d{4})?\b/', '', (string) $no_dir);
			$add_variant((string) $no_zip_no_dir);
			$add_variant(trim((string) $no_zip_no_dir, ', ') . ', USA');

			return $variants;
		}

		private static function build_street_variants(string $street): array
		{
			$variants = array();
			$add_variant = static function (string $candidate) use (&$variants): void {
				$candidate = trim((string) preg_replace('/\s+/', ' ', $candidate));
				if ($candidate === '' || in_array(strtolower($candidate), array_map('strtolower', $variants), true)) {
					return;
				}
				$variants[] = $candidate;
			};

			$add_variant($street);
			$normalized = preg_replace('/\bFarm\s+to\s+Market(?:\s+Road|\s+Rd\.?|)\b/i', 'FM', $street);
			$normalized = preg_replace('/\bRoad\b/i', 'Rd', (string) $normalized);
			$add_variant((string) $normalized);
			$no_dir = preg_replace('/\b((?:FM|Farm to Market|CR|County Road|SH|State Highway|Hwy|Highway)\s*\d+)\s+[NSEW]\b/i', '$1', (string) $normalized);
			$add_variant((string) $no_dir);
			$expanded = preg_replace('/\bFM\b/i', 'Farm to Market Road', (string) $no_dir);
			$add_variant((string) $expanded);
			return $variants;
		}

		private static function summarize_geocode_attempt(string $provider_key, string $address, $result): string
		{
			$provider_key = sanitize_key($provider_key);
			$summary = $provider_key . ' [' . trim($address) . ']';
			if (is_array($result) && !empty($result['ok']) && isset($result['latitude'], $result['longitude'])) {
				return $summary . ' success (' . round((float) $result['latitude'], 4) . ', ' . round((float) $result['longitude'], 4) . ')';
			}
			$error = is_array($result) ? trim((string) ($result['error'] ?? 'no match')) : 'no match';
			$http = is_array($result) && isset($result['http_code']) ? (int) $result['http_code'] : 0;
			if ($http > 0) {
				return $summary . ' HTTP ' . $http . ' - ' . $error;
			}
			return $summary . ' - ' . $error;
		}

		private static function geocode_via_census_structured(string $street, string $city, string $state, string $zip = ''): array
		{
			$query = array(
				'benchmark' => 'Public_AR_Current',
				'format' => 'json',
				'street' => $street,
				'city' => $city,
				'state' => $state,
			);
			if ($zip !== '') {
				$query['zip'] = $zip;
			}
			$url = add_query_arg($query, 'https://geocoding.geo.census.gov/geocoder/locations/address');
			$response = VMSX_Weather_Risk_Helpers::remote_get_json_detailed($url, array(
				'headers' => array(
					'Accept' => 'application/json',
					'User-Agent' => sprintf('vmsx-weather-risk/%s (%s)', VMSX_WR_VERSION, (string) home_url('/')),
				),
			));
			if (empty($response['ok'])) {
				return array('ok' => false, 'error' => (string) ($response['error'] ?? 'request failed'), 'http_code' => (int) ($response['http_code'] ?? 0));
			}
			$data = (array) ($response['data'] ?? array());
			$match = $data['result']['addressMatches'][0]['coordinates'] ?? null;
			$lat = is_array($match) ? ($match['y'] ?? null) : null;
			$lon = is_array($match) ? ($match['x'] ?? null) : null;
			if (!is_numeric($lat) || !is_numeric($lon)) {
				return array('ok' => false, 'error' => 'zero results', 'http_code' => (int) ($response['http_code'] ?? 200));
			}
			return array('ok' => true, 'latitude' => (float) $lat, 'longitude' => (float) $lon, 'http_code' => (int) ($response['http_code'] ?? 200));
		}

		private static function geocode_via_census(string $address): array
		{
			$url = add_query_arg(array(
				'benchmark' => 'Public_AR_Current',
				'format' => 'json',
				'address' => $address,
			), 'https://geocoding.geo.census.gov/geocoder/locations/onelineaddress');
			$response = VMSX_Weather_Risk_Helpers::remote_get_json_detailed($url, array(
				'headers' => array(
					'Accept' => 'application/json',
					'User-Agent' => sprintf('vmsx-weather-risk/%s (%s)', VMSX_WR_VERSION, (string) home_url('/')),
				),
			));
			if (empty($response['ok'])) {
				return array('ok' => false, 'error' => (string) ($response['error'] ?? 'request failed'), 'http_code' => (int) ($response['http_code'] ?? 0));
			}
			$data = (array) ($response['data'] ?? array());
			$match = $data['result']['addressMatches'][0]['coordinates'] ?? null;
			$lat = is_array($match) ? ($match['y'] ?? null) : null;
			$lon = is_array($match) ? ($match['x'] ?? null) : null;
			if (!is_numeric($lat) || !is_numeric($lon)) {
				return array('ok' => false, 'error' => 'zero results', 'http_code' => (int) ($response['http_code'] ?? 200));
			}
			return array('ok' => true, 'latitude' => (float) $lat, 'longitude' => (float) $lon, 'http_code' => (int) ($response['http_code'] ?? 200));
		}

		private static function geocode_via_openmeteo(string $address): array
		{
			$args = array(
				'headers' => array(
					'Accept' => 'application/json',
					'User-Agent' => sprintf('vmsx-weather-risk/%s (%s)', VMSX_WR_VERSION, (string) home_url('/')),
				),
			);
			$url = add_query_arg(array(
				'name' => $address,
				'count' => 1,
				'language' => 'en',
				'format' => 'json',
			), 'https://geocoding-api.open-meteo.com/v1/search');
			$response = VMSX_Weather_Risk_Helpers::remote_get_json_detailed($url, $args);
			if (empty($response['ok'])) {
				return array('ok' => false, 'error' => (string) ($response['error'] ?? 'request failed'), 'http_code' => (int) ($response['http_code'] ?? 0));
			}
			$data = (array) ($response['data'] ?? array());
			$row = $data['results'][0] ?? null;
			$lat = is_array($row) ? ($row['latitude'] ?? null) : null;
			$lon = is_array($row) ? ($row['longitude'] ?? null) : null;
			if (!is_numeric($lat) || !is_numeric($lon)) {
				return array('ok' => false, 'error' => 'zero results', 'http_code' => (int) ($response['http_code'] ?? 200));
			}
			return array('ok' => true, 'latitude' => (float) $lat, 'longitude' => (float) $lon, 'http_code' => (int) ($response['http_code'] ?? 200));
		}

		private static function geocode_via_nominatim(string $address): array
		{
			$args = array(
				'headers' => array(
					'Accept' => 'application/json',
					'User-Agent' => sprintf('vmsx-weather-risk/%s (%s)', VMSX_WR_VERSION, (string) home_url('/')),
				),
			);
			$email = sanitize_email((string) get_option('admin_email', ''));
			$query = array(
				'format' => 'jsonv2',
				'limit' => 1,
				'countrycodes' => 'us',
				'addressdetails' => 0,
				'q' => $address,
			);
			if ($email !== '') {
				$query['email'] = $email;
			}
			$url = add_query_arg($query, 'https://nominatim.openstreetmap.org/search');
			$response = VMSX_Weather_Risk_Helpers::remote_get_json_detailed($url, $args);
			if (empty($response['ok'])) {
				return array('ok' => false, 'error' => (string) ($response['error'] ?? 'request failed'), 'http_code' => (int) ($response['http_code'] ?? 0));
			}
			$data = $response['data'] ?? array();
			$row = (is_array($data) && !empty($data[0]) && is_array($data[0])) ? $data[0] : null;
			$lat = is_array($row) ? ($row['lat'] ?? null) : null;
			$lon = is_array($row) ? ($row['lon'] ?? null) : null;
			if (!is_numeric($lat) || !is_numeric($lon)) {
				return array('ok' => false, 'error' => 'zero results', 'http_code' => (int) ($response['http_code'] ?? 200));
			}
			return array('ok' => true, 'latitude' => (float) $lat, 'longitude' => (float) $lon, 'http_code' => (int) ($response['http_code'] ?? 200));
		}

		private static function normalize_country_code(string $country): string
		{
			$country = strtoupper(trim($country));
			if ($country === '' || $country === 'UNITED STATES' || $country === 'USA') {
				return 'US';
			}
			if (strlen($country) === 2) {
				return $country;
			}
			return '';
		}
	}
}
