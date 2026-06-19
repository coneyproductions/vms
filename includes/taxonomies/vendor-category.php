<?php
defined('ABSPATH') || exit;

add_action('init', function (): void {

	$labels = [
		'name'                       => __('Vendor Categories', 'vms'),
		'singular_name'              => __('Vendor Category', 'vms'),
		'search_items'               => __('Search Vendor Categories', 'vms'),
		'popular_items'              => __('Popular Vendor Categories', 'vms'),
		'all_items'                  => __('All Vendor Categories', 'vms'),
		'edit_item'                  => __('Edit Vendor Category', 'vms'),
		'update_item'                => __('Update Vendor Category', 'vms'),
		'add_new_item'               => __('Add New Vendor Category', 'vms'),
		'new_item_name'              => __('New Vendor Category Name', 'vms'),
		'separate_items_with_commas' => __('Separate vendor categories with commas', 'vms'),
		'add_or_remove_items'        => __('Add or remove vendor categories', 'vms'),
		'choose_from_most_used'      => __('Choose from the most used vendor categories', 'vms'),
		'not_found'                  => __('No vendor categories found.', 'vms'),
		'menu_name'                  => __('Vendor Categories', 'vms'),
	];

	$args = [
		'hierarchical'          => false,
		'labels'                => $labels,
		'show_ui'               => true,
		'show_admin_column'     => false,
		'update_count_callback' => '_update_post_term_count',
		'query_var'             => true,
		'rewrite'               => false,
		'meta_box_cb'           => 'post_tags_meta_box',
		'meta_box_sanitize_cb'  => 'taxonomy_meta_box_sanitize_cb_input',
	];

	register_taxonomy('vms_vendor_category', ['vms_vendor'], $args);

}, 12);

if (!function_exists('vms_vendor_category_default_label_map')) {
	function vms_vendor_category_default_label_map(): array
	{
		return [
			'band'              => __('Genre', 'vms'),
			'bands'             => __('Genre', 'vms'),
			'artist'            => __('Genre', 'vms'),
			'performer'         => __('Genre', 'vms'),
			'solo-musician'     => __('Genre', 'vms'),
			'solo_musician'     => __('Genre', 'vms'),
			'musician'          => __('Genre', 'vms'),
			'dj'                => __('Genre', 'vms'),
			'food-truck'        => __('Cuisine', 'vms'),
			'food_truck'        => __('Cuisine', 'vms'),
			'foodtruck'         => __('Cuisine', 'vms'),
			'caterer'           => __('Cuisine', 'vms'),
			'bar'               => __('Style', 'vms'),
			'bartender'         => __('Service', 'vms'),
			'photographer'      => __('Style', 'vms'),
			'videographer'      => __('Style', 'vms'),
			'florist'           => __('Style', 'vms'),
			'contractor'        => __('Service', 'vms'),
			'security'          => __('Service', 'vms'),
			'sound'             => __('Service', 'vms'),
			'lighting'          => __('Service', 'vms'),
		];
	}
}

if (!function_exists('vms_vendor_category_label_for_type')) {
	function vms_vendor_category_label_for_type($type): string
	{
		$term = null;
		$slug = '';

		if (is_object($type) && isset($type->slug)) {
			$term = $type;
			$slug = sanitize_title((string) $type->slug);
		} elseif (is_numeric($type)) {
			$term_id = absint($type);
			if ($term_id > 0) {
				$maybe_term = get_term($term_id, 'vms_vendor_type');
				if ($maybe_term && !is_wp_error($maybe_term) && is_object($maybe_term) && isset($maybe_term->slug)) {
					$term = $maybe_term;
					$slug = sanitize_title((string) $maybe_term->slug);
				}
			}
		} else {
			$slug = sanitize_title((string) $type);
			if ($slug !== '') {
				$maybe = get_term_by('slug', $slug, 'vms_vendor_type');
				if ($maybe && !is_wp_error($maybe) && is_object($maybe)) {
					$term = $maybe;
				}
			}
		}

		if ($term && !is_wp_error($term) && is_object($term) && isset($term->term_id)) {
			$raw_custom = get_term_meta((int) $term->term_id, '_vms_vendor_type_category_label', true);
			$custom = is_scalar($raw_custom) ? trim((string) $raw_custom) : '';
			if ($custom !== '') {
				return $custom;
			}
		}

		$defaults = vms_vendor_category_default_label_map();
		if ($slug !== '' && isset($defaults[$slug]) && is_string($defaults[$slug]) && $defaults[$slug] !== '') {
			return $defaults[$slug];
		}

		return __('Category', 'vms');
	}
}

if (!function_exists('vms_vendor_primary_type_slug')) {
	function vms_vendor_primary_type_slug(int $vendor_id): string
	{
		$vendor_id = absint($vendor_id);
		if ($vendor_id <= 0) {
			return '';
		}

		$terms = get_the_terms($vendor_id, 'vms_vendor_type');
		if (is_wp_error($terms) || empty($terms) || !is_array($terms)) {
			return '';
		}

		$first = reset($terms);
		return (is_object($first) && isset($first->slug)) ? sanitize_title((string) $first->slug) : '';
	}
}

if (!function_exists('vms_vendor_category_label_for_vendor')) {
	function vms_vendor_category_label_for_vendor(int $vendor_id): string
	{
		return vms_vendor_category_label_for_type(vms_vendor_primary_type_slug($vendor_id));
	}
}

if (!function_exists('vms_vendor_get_category_terms')) {
	function vms_vendor_get_category_terms(int $vendor_id): array
	{
		$vendor_id = absint($vendor_id);
		if ($vendor_id <= 0) {
			return [];
		}

		$terms = get_the_terms($vendor_id, 'vms_vendor_category');
		if (is_wp_error($terms) || empty($terms) || !is_array($terms)) {
			return [];
		}

		$clean = [];
		foreach ($terms as $term) {
			if (is_object($term) && isset($term->term_id)) {
				$clean[] = $term;
			}
		}
		return $clean;
	}
}

if (!function_exists('vms_vendor_categories_parse_legacy_list')) {
	function vms_vendor_categories_parse_legacy_list(string $raw): array
	{
		$raw = trim($raw);
		if ($raw === '') {
			return [];
		}

		$pieces = preg_split('/\s*[,;|\/]+\s*/', $raw);
		if (!is_array($pieces)) {
			return [];
		}

		$out = [];
		foreach ($pieces as $piece) {
			$piece = trim(wp_strip_all_tags((string) $piece));
			if ($piece === '') {
				continue;
			}
			$out[] = $piece;
		}

		$out = array_values(array_unique($out));
		return $out;
	}
}

if (!function_exists('vms_vendor_categories_seed_from_legacy_meta')) {
	function vms_vendor_categories_seed_from_legacy_meta(int $vendor_id): void
	{
		$vendor_id = absint($vendor_id);
		if ($vendor_id <= 0 || !taxonomy_exists('vms_vendor_category')) {
			return;
		}

		$existing = vms_vendor_get_category_terms($vendor_id);
		if (!empty($existing)) {
			return;
		}

		$raw = trim((string) get_post_meta($vendor_id, '_vms_vendor_cuisine', true));
		if ($raw === '') {
			return;
		}

		$names = vms_vendor_categories_parse_legacy_list($raw);
		if (empty($names)) {
			return;
		}

		$term_ids = [];
		foreach ($names as $name) {
			$term = get_term_by('name', $name, 'vms_vendor_category');
			if (!$term || is_wp_error($term)) {
				$term = get_term_by('slug', sanitize_title($name), 'vms_vendor_category');
			}
			if (!$term || is_wp_error($term)) {
				$created = wp_insert_term($name, 'vms_vendor_category');
				if (is_wp_error($created)) {
					continue;
				}
				$term_id = absint($created['term_id'] ?? 0);
			} else {
				$term_id = absint($term->term_id);
			}
			if ($term_id > 0) {
				$term_ids[] = $term_id;
			}
		}

		$term_ids = array_values(array_unique(array_filter(array_map('absint', $term_ids))));
		if (!empty($term_ids)) {
			wp_set_object_terms($vendor_id, $term_ids, 'vms_vendor_category', false);
		}
	}
}

add_action('vms_vendor_type_add_form_fields', function () {
	?>
	<div class="form-field">
		<label for="vms_vendor_type_category_label"><?php esc_html_e('Vendor Category Label', 'vms'); ?></label>
		<input type="text" id="vms_vendor_type_category_label" name="vms_vendor_type_category_label" value="">
		<p class="description"><?php esc_html_e('Optional. Example: Genre, Cuisine, Style, Service. Leave blank to use the default Category label.', 'vms'); ?></p>
	</div>
	<?php
});

add_action('vms_vendor_type_edit_form_fields', function ($term) {
	$term_id = isset($term->term_id) ? absint($term->term_id) : 0;
	$value = $term_id > 0 ? trim((string) get_term_meta($term_id, '_vms_vendor_type_category_label', true)) : '';
	$resolved = vms_vendor_category_label_for_type($term);
	?>
	<tr class="form-field">
		<th scope="row"><label for="vms_vendor_type_category_label"><?php esc_html_e('Vendor Category Label', 'vms'); ?></label></th>
		<td>
			<input type="text" id="vms_vendor_type_category_label" name="vms_vendor_type_category_label" value="<?php echo esc_attr($value); ?>">
			<p class="description"><?php esc_html_e('Optional. Example: Genre, Cuisine, Style, Service.', 'vms'); ?></p>
			<p class="description"><?php printf(esc_html__('Current resolved label: %s', 'vms'), esc_html($resolved)); ?></p>
		</td>
	</tr>
	<?php
});

if (!function_exists('vms_vendor_type_save_category_label_meta')) {
	function vms_vendor_type_save_category_label_meta($term_id): void
	{
		$term_id = absint($term_id);
		if ($term_id <= 0 || !current_user_can('manage_categories')) {
			return;
		}

		$value = isset($_POST['vms_vendor_type_category_label'])
			? sanitize_text_field((string) wp_unslash($_POST['vms_vendor_type_category_label']))
			: '';

		if ($value === '') {
			delete_term_meta($term_id, '_vms_vendor_type_category_label');
			return;
		}

		update_term_meta($term_id, '_vms_vendor_type_category_label', $value);
	}
}
add_action('created_vms_vendor_type', 'vms_vendor_type_save_category_label_meta');
add_action('edited_vms_vendor_type', 'vms_vendor_type_save_category_label_meta');

if (!function_exists('vms_vendor_categories_get_related_event_plan_ids')) {
	function vms_vendor_categories_get_related_event_plan_ids(int $vendor_id): array
	{
		$vendor_id = absint($vendor_id);
		if ($vendor_id <= 0) {
			return [];
		}

		$band_key = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'band_vendor_id') ?: '_vms_band_vendor_id') : '_vms_band_vendor_id';
		$secondary_idx_key = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'secondary_vendor_id') ?: '_vms_secondary_vendor_id') : '_vms_secondary_vendor_id';

		$primary_ids = get_posts([
			'post_type'              => 'vms_event_plan',
			'post_status'            => 'any',
			'fields'                 => 'ids',
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'meta_query'             => [[
				'key'     => $band_key,
				'value'   => $vendor_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			]],
		]);

		$secondary_ids = get_posts([
			'post_type'              => 'vms_event_plan',
			'post_status'            => 'any',
			'fields'                 => 'ids',
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'meta_query'             => [[
				'key'     => $secondary_idx_key,
				'value'   => $vendor_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			]],
		]);

		$ids = array_merge(is_array($primary_ids) ? $primary_ids : [], is_array($secondary_ids) ? $secondary_ids : []);
		$ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
		return $ids;
	}
}

if (!function_exists('vms_vendor_categories_touch_related_event_plans')) {
	function vms_vendor_categories_touch_related_event_plans(int $vendor_id, WP_Post $post): void
	{
		if ($post->post_type !== 'vms_vendor') {
			return;
		}
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		if (wp_is_post_revision($vendor_id)) {
			return;
		}

		vms_vendor_categories_seed_from_legacy_meta($vendor_id);

		if (!function_exists('vms_event_plan_update_vendor_category_snapshot')) {
			return;
		}

		$plan_ids = vms_vendor_categories_get_related_event_plan_ids($vendor_id);
		if (empty($plan_ids)) {
			return;
		}

		$k_tec = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'tec_event_id') ?: '_vms_tec_event_id') : '_vms_tec_event_id';
		foreach ($plan_ids as $plan_id) {
			vms_event_plan_update_vendor_category_snapshot((int) $plan_id);
			if (function_exists('vms_tec_sync_vendor_categories_from_plan')) {
				$tec_event_id = (int) get_post_meta((int) $plan_id, $k_tec, true);
				if ($tec_event_id > 0) {
					vms_tec_sync_vendor_categories_from_plan((int) $plan_id, $tec_event_id);
				}
			}
		}
	}
}
add_action('save_post_vms_vendor', 'vms_vendor_categories_touch_related_event_plans', 40, 2);
