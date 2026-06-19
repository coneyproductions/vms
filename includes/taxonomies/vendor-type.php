<?php
 defined('ABSPATH') || exit;
 
 if (!function_exists('vms_vendor_type_registry')) {
 	function vms_vendor_type_registry(): array
 	{
 		$registry = [
 			'band' => [
 				'label' => __('Music Vendor', 'vms'),
 				'aliases' => ['band', 'bands', 'artist', 'artists', 'band_artist', 'band-artist', 'solo artist', 'solo', 'duo', 'performer', 'performers', 'musician', 'musicians', 'music vendor', 'music_vendor'],
 			],
 			'food_truck' => [
 				'label' => __('Food Vendor', 'vms'),
 				'aliases' => ['food_truck', 'food-truck', 'food truck', 'foodtruck', 'food vendor', 'food_vendor', 'food-vendor', 'mobile kitchen', 'mobile_kitchen', 'mobile-kitchen', 'truck', 'caterer'],
 			],
 			'dessert_truck' => [
 				'label' => __('Dessert Vendor', 'vms'),
 				'aliases' => ['dessert_truck', 'dessert-truck', 'dessert truck', 'dessert vendor', 'dessert_vendor'],
 			],
 			'drink_truck' => [
 				'label' => __('Drink Vendor', 'vms'),
 				'aliases' => ['drink_truck', 'drink-truck', 'drink truck', 'drink vendor', 'drink_vendor'],
 			],
 			'photographer' => [
 				'label' => __('Photographer', 'vms'),
 				'aliases' => ['photographer', 'photo vendor', 'photo_vendor'],
 			],
 			'market_vendor' => [
 				'label' => __('Market Vendor', 'vms'),
 				'aliases' => ['market vendor', 'market_vendor', 'market-vendor', 'vendor market'],
 			],
 		];
 
 		return (array) apply_filters('vms_vendor_type_registry', $registry);
 	}
 }
 
 if (!function_exists('vms_vendor_type_alias_map')) {
 	function vms_vendor_type_alias_map(): array
 	{
 		$map = [];
 		foreach (vms_vendor_type_registry() as $canonical_slug => $row) {
 			$canonical_slug = sanitize_key((string) $canonical_slug);
 			if ($canonical_slug === '') {
 				continue;
 			}
 
 			$aliases = [$canonical_slug];
 			if (!empty($row['aliases']) && is_array($row['aliases'])) {
 				$aliases = array_merge($aliases, $row['aliases']);
 			}
 
 			foreach ($aliases as $alias) {
 				$raw = strtolower(trim((string) $alias));
 				if ($raw === '') {
 					continue;
 				}
 
 				$variants = array_unique(array_filter([
 					$raw,
 					str_replace('-', '_', $raw),
 					str_replace('_', '-', $raw),
 					sanitize_title($raw),
 					sanitize_key($raw),
 					str_replace('-', '_', sanitize_title($raw)),
 					str_replace('_', '-', sanitize_key($raw)),
 				]));
 
 				foreach ($variants as $variant) {
 					$map[(string) $variant] = $canonical_slug;
 				}
 			}
 		}
 
 		return $map;
 	}
 }
 
 if (!function_exists('vms_vendor_type_normalize_slug')) {
 	function vms_vendor_type_normalize_slug(string $raw): string
 	{
 		$raw = strtolower(trim(wp_strip_all_tags($raw)));
 		if ($raw === '') {
 			return '';
 		}
 
 		$candidates = array_unique(array_filter([
 			$raw,
 			str_replace('-', '_', $raw),
 			str_replace('_', '-', $raw),
 			sanitize_title($raw),
 			sanitize_key($raw),
 			str_replace('-', '_', sanitize_title($raw)),
 			str_replace('_', '-', sanitize_key($raw)),
 		]));
 
 		foreach ($candidates as $candidate) {
 			$stripped = preg_replace('/(?:[-_]\d+)$/', '', (string) $candidate);
 			if (is_string($stripped) && $stripped !== '' && !in_array($stripped, $candidates, true)) {
 				$candidates[] = $stripped;
 				$candidates[] = str_replace('-', '_', $stripped);
 				$candidates[] = str_replace('_', '-', $stripped);
 			}
 		}
 
 		$candidates = array_values(array_unique(array_filter(array_map('strval', $candidates))));
 
 		$aliases = vms_vendor_type_alias_map();
 		foreach ($candidates as $candidate) {
 			if (isset($aliases[$candidate]) && is_string($aliases[$candidate]) && $aliases[$candidate] !== '') {
 				return $aliases[$candidate];
 			}
 		}
 
 		$fallback = preg_replace('/(?:[-_]\d+)$/', '', $raw);
 		if (!is_string($fallback) || $fallback === '') {
 			$fallback = $raw;
 		}
 
 		return sanitize_key(str_replace('-', '_', $fallback));
 	}
 }
 
 if (!function_exists('vms_vendor_type_label')) {
 	function vms_vendor_type_label(string $raw): string
 	{
 		$slug = vms_vendor_type_normalize_slug($raw);
 		$registry = vms_vendor_type_registry();
 
 		if ($slug !== '' && isset($registry[$slug]['label']) && is_string($registry[$slug]['label'])) {
 			return (string) $registry[$slug]['label'];
 		}
 
 		$raw = trim((string) $raw);
 		if ($raw === '') {
 			return '';
 		}
 
 		return ucwords(str_replace(['_', '-'], ' ', $slug !== '' ? $slug : $raw));
 	}
 }
 
 if (!function_exists('vms_vendor_type_select_options')) {
 	function vms_vendor_type_select_options(): array
 	{
 		$options = [];
 		foreach (vms_vendor_type_registry() as $slug => $row) {
 			$slug = sanitize_key((string) $slug);
 			$label = isset($row['label']) ? (string) $row['label'] : '';
 			if ($slug === '' || $label === '') {
 				continue;
 			}
 			$options[$slug] = $label;
 		}
 		return $options;
 	}
 }
 
 if (!function_exists('vms_vendor_type_canonical_slug_for_term')) {
 	function vms_vendor_type_canonical_slug_for_term($term): string
 	{
 		if (!$term instanceof WP_Term) {
 			return '';
 		}
 
 		$options = vms_vendor_type_select_options();
 
 		$by_slug = vms_vendor_type_normalize_slug((string) $term->slug);
 		if ($by_slug !== '' && isset($options[$by_slug])) {
 			return $by_slug;
 		}
 
 		$by_name = vms_vendor_type_normalize_slug((string) $term->name);
 		if ($by_name !== '' && isset($options[$by_name])) {
 			return $by_name;
 		}
 
 		return $by_slug !== '' ? $by_slug : $by_name;
 	}
 }
 
 if (!function_exists('vms_vendor_type_terms_for_slug')) {
 	function vms_vendor_type_terms_for_slug(string $raw): array
 	{
 		$canonical_slug = vms_vendor_type_normalize_slug($raw);
 		if ($canonical_slug === '' || !taxonomy_exists('vms_vendor_type')) {
 			return [];
 		}
 
 		$terms = get_terms([
 			'taxonomy' => 'vms_vendor_type',
 			'hide_empty' => false,
 		]);
 
 		if (!is_array($terms) || is_wp_error($terms)) {
 			return [];
 		}
 
 		$matches = [];
 		foreach ($terms as $term) {
 			if (!$term instanceof WP_Term) {
 				continue;
 			}
 
 			if (vms_vendor_type_canonical_slug_for_term($term) !== $canonical_slug) {
 				continue;
 			}
 
 			$matches[] = $term;
 		}
 
 		usort($matches, static function (WP_Term $a, WP_Term $b) use ($canonical_slug): int {
 			$a_is_canonical = ((string) $a->slug === $canonical_slug) ? 0 : 1;
 			$b_is_canonical = ((string) $b->slug === $canonical_slug) ? 0 : 1;
 			if ($a_is_canonical !== $b_is_canonical) {
 				return $a_is_canonical <=> $b_is_canonical;
 			}
 
 			return strcasecmp((string) $a->name, (string) $b->name);
 		});
 
 		return $matches;
 	}
 }
 
 if (!function_exists('vms_vendor_type_get_term')) {
 	function vms_vendor_type_get_term(string $raw): ?WP_Term
 	{
 		$canonical_slug = vms_vendor_type_normalize_slug($raw);
 		if ($canonical_slug === '' || !taxonomy_exists('vms_vendor_type')) {
 			return null;
 		}
 
 		$term = get_term_by('slug', $canonical_slug, 'vms_vendor_type');
 		if ($term instanceof WP_Term) {
 			return $term;
 		}
 
 		$matches = vms_vendor_type_terms_for_slug($canonical_slug);
 		return !empty($matches[0]) && $matches[0] instanceof WP_Term ? $matches[0] : null;
 	}
 }
 
 if (!function_exists('vms_vendor_type_query_slugs')) {
 	function vms_vendor_type_query_slugs(string $raw): array
 	{
 		$canonical_slug = vms_vendor_type_normalize_slug($raw);
 		if ($canonical_slug === '') {
 			return [];
 		}
 
 		$slugs = [$canonical_slug];
 		foreach (vms_vendor_type_terms_for_slug($canonical_slug) as $term) {
 			if ($term instanceof WP_Term && (string) $term->slug !== '') {
 				$slugs[] = (string) $term->slug;
 			}
 		}
 
 		return array_values(array_unique(array_filter(array_map('strval', $slugs))));
 	}
 }
 
 if (!function_exists('vms_vendor_has_type')) {
 	function vms_vendor_has_type(int $vendor_id, string $raw): bool
 	{
 		$vendor_id = absint($vendor_id);
 		$canonical_slug = vms_vendor_type_normalize_slug($raw);
 		if ($vendor_id <= 0 || $canonical_slug === '' || !taxonomy_exists('vms_vendor_type')) {
 			return false;
 		}
 
 		$terms = get_the_terms($vendor_id, 'vms_vendor_type');
 		if (!is_array($terms) || empty($terms)) {
 			return false;
 		}
 
 		foreach ($terms as $term) {
 			if (!$term instanceof WP_Term) {
 				continue;
 			}
 
 			if (vms_vendor_type_canonical_slug_for_term($term) === $canonical_slug) {
 				return true;
 			}
 		}
 
 		return false;
 	}
 }
 
 add_action('init', function (): void {
 
 	$labels = [
 		'name'          => __('Vendor Types', 'vms'),
 		'singular_name' => __('Vendor Type', 'vms'),
 		'search_items'  => __('Search Vendor Types', 'vms'),
 		'all_items'     => __('All Vendor Types', 'vms'),
 		'edit_item'     => __('Edit Vendor Type', 'vms'),
 		'update_item'   => __('Update Vendor Type', 'vms'),
 		'add_new_item'  => __('Add New Vendor Type', 'vms'),
 		'new_item_name' => __('New Vendor Type Name', 'vms'),
 		'menu_name'     => __('Vendor Types', 'vms'),
 	];
 
 	$args = [
 		'hierarchical'      => true,
 		'labels'            => $labels,
 		'show_ui'           => true,
 		'show_admin_column' => true,
 		'query_var'         => true,
 		'rewrite'           => false,
 	];
 
 	register_taxonomy('vms_vendor_type', ['vms_vendor'], $args);
 
 }, 11);
 
 if (!function_exists('vms_vendor_type_ensure_default_terms')) {
 	function vms_vendor_type_ensure_default_terms(): void
 	{
 		if (!taxonomy_exists('vms_vendor_type')) {
 			return;
 		}
 
 		foreach (vms_vendor_type_select_options() as $slug => $label) {
 			$existing = get_term_by('slug', $slug, 'vms_vendor_type');
 			if ($existing && !is_wp_error($existing)) {
 				if ((string) $existing->name !== $label) {
 					wp_update_term((int) $existing->term_id, 'vms_vendor_type', ['name' => $label]);
 				}
 				continue;
 			}
 
 			$created = wp_insert_term($label, 'vms_vendor_type', ['slug' => $slug]);
 			if (is_wp_error($created)) {
 				error_log('[VMS] vendor-type: failed to ensure default term ' . $slug . ' (' . $created->get_error_message() . ')');
 			}
 		}
 	}
 }
 add_action('init', 'vms_vendor_type_ensure_default_terms', 21);
 
 if (!function_exists('vms_vendor_type_maybe_canonicalize_terms')) {
 	function vms_vendor_type_maybe_canonicalize_terms(): void
 	{
 		if (!taxonomy_exists('vms_vendor_type')) {
 			return;
 		}
 
 		$option_key = 'vms_vendor_type_canonicalized_v1';
 		if ((string) get_option($option_key, '') === '1') {
 			return;
 		}
 
 		$options = vms_vendor_type_select_options();
 		$canonical_term_ids = [];
 
 		foreach ($options as $slug => $label) {
 			$term = get_term_by('slug', $slug, 'vms_vendor_type');
 			if (!$term || is_wp_error($term)) {
 				$created = wp_insert_term($label, 'vms_vendor_type', ['slug' => $slug]);
 				if (!is_wp_error($created)) {
 					$term = get_term((int) ($created['term_id'] ?? 0), 'vms_vendor_type');
 				}
 			}
 
 			if ($term instanceof WP_Term) {
 				$canonical_term_ids[$slug] = (int) $term->term_id;
 				if ((string) $term->name !== $label) {
 					wp_update_term((int) $term->term_id, 'vms_vendor_type', ['name' => $label]);
 				}
 			}
 		}
 
 		$terms = get_terms([
 			'taxonomy' => 'vms_vendor_type',
 			'hide_empty' => false,
 		]);
 
 		if (is_array($terms) && !is_wp_error($terms)) {
 			foreach ($terms as $term) {
 				if (!$term instanceof WP_Term) {
 					continue;
 				}
 
 				$canonical_slug = vms_vendor_type_canonical_slug_for_term($term);
 				if ($canonical_slug === '' || !isset($options[$canonical_slug])) {
 					continue;
 				}
 
 				$canonical_term_id = absint($canonical_term_ids[$canonical_slug] ?? 0);
 				if ($canonical_term_id <= 0 || $canonical_term_id === (int) $term->term_id) {
 					continue;
 				}
 
 				$object_ids = get_objects_in_term((int) $term->term_id, 'vms_vendor_type');
 				if (is_array($object_ids)) {
 					foreach (array_values(array_unique(array_filter(array_map('absint', $object_ids)))) as $object_id) {
 						if ($object_id <= 0) {
 							continue;
 						}
 
 						wp_set_object_terms($object_id, [$canonical_term_id], 'vms_vendor_type', true);
 						wp_remove_object_terms($object_id, [(int) $term->term_id], 'vms_vendor_type');
 					}
 				}
 
 				$meta_keys = ['_vms_vendor_type_category_label'];
 				foreach ($meta_keys as $meta_key) {
 					$canonical_meta = get_term_meta($canonical_term_id, $meta_key, true);
 					$alias_meta = get_term_meta((int) $term->term_id, $meta_key, true);
 					if (($canonical_meta === '' || $canonical_meta === null || $canonical_meta === []) && $alias_meta !== '' && $alias_meta !== null && $alias_meta !== []) {
 						update_term_meta($canonical_term_id, $meta_key, $alias_meta);
 					}
 				}
 
 				$deleted = wp_delete_term((int) $term->term_id, 'vms_vendor_type');
 				if (is_wp_error($deleted)) {
 					error_log('[VMS] vendor-type: failed deleting duplicate term #' . (int) $term->term_id . ' (' . $deleted->get_error_message() . ')');
 				}
 			}
 		}
 
 		$secondary_type_key = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'secondary_vendor_type') ?: '_vms_secondary_vendor_type') : '_vms_secondary_vendor_type';
 		$event_plan_ids = get_posts([
 			'post_type' => 'vms_event_plan',
 			'post_status' => 'any',
 			'numberposts' => -1,
 			'fields' => 'ids',
 			'no_found_rows' => true,
 			'suppress_filters' => true,
 		]);
 
 		if (is_array($event_plan_ids)) {
 			foreach ($event_plan_ids as $event_plan_id) {
 				$event_plan_id = absint($event_plan_id);
 				if ($event_plan_id <= 0) {
 					continue;
 				}
 
 				$raw_type = (string) get_post_meta($event_plan_id, $secondary_type_key, true);
 				if ($raw_type === '') {
 					continue;
 				}
 
 				$canonical_type = vms_vendor_type_normalize_slug($raw_type);
 				if ($canonical_type !== '' && $canonical_type !== $raw_type) {
 					update_post_meta($event_plan_id, $secondary_type_key, $canonical_type);
 				}
 			}
 		}
 
 		update_option($option_key, '1', false);
 	}
 }
 add_action('init', 'vms_vendor_type_maybe_canonicalize_terms', 22);
