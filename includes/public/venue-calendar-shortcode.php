<?php
if (!defined('ABSPATH')) exit;

/**
 * Public calendar shortcodes:
 * - Legacy: [vms_venue_calendar]
 * - Canonical: [vms_public_calendar]
 */

if (!function_exists('vms_public_calendar_time_label')) {
	function vms_public_calendar_time_label(string $start_local): string
	{
		if ($start_local === '') {
			return '';
		}
		try {
			$dt = new DateTimeImmutable($start_local);
			return $dt->format('g:ia');
		} catch (Exception $e) {
			return '';
		}
	}
}

if (!function_exists('vms_public_calendar_event_is_past')) {
	/**
	 * @param array<string,mixed> $event
	 */
	function vms_public_calendar_event_is_past(array $event): bool
	{
		$tz = function_exists('vms_get_timezone') ? vms_get_timezone() : wp_timezone();
		$now = time();

		$end_local = trim((string) ($event['end_local'] ?? ''));
		if ($end_local !== '') {
			try {
				$dt = new DateTimeImmutable($end_local, $tz);
				return ($dt->getTimestamp() < $now);
			} catch (Exception $e) {
			}
		}

		$start_local = trim((string) ($event['start_local'] ?? ''));
		if ($start_local !== '') {
			try {
				$dt = new DateTimeImmutable($start_local, $tz);
				return ($dt->getTimestamp() < $now);
			} catch (Exception $e) {
			}
		}

		$date_key = trim((string) ($event['date_key'] ?? ''));
		if ($date_key !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_key)) {
			try {
				$dt = new DateTimeImmutable($date_key . ' 23:59:59', $tz);
				return ($dt->getTimestamp() < $now);
			} catch (Exception $e) {
			}
		}

		return false;
	}
}


if (!function_exists('vms_public_calendar_month_label')) {
	function vms_public_calendar_month_label(string $ym, string $format = 'F'): string
	{
		if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
			return $ym;
		}

		$timezone = function_exists('vms_get_timezone') ? vms_get_timezone() : wp_timezone();
		$ts = strtotime($ym . '-01 12:00:00');
		if ($ts === false) {
			return $ym;
		}

		return (string) wp_date($format, $ts, $timezone);
	}
}

if (!function_exists('vms_public_calendar_nav_label')) {
	function vms_public_calendar_nav_label(string $ym): string
	{
		return vms_public_calendar_month_label($ym, 'F');
	}
}

if (!function_exists('vms_public_calendar_allowed_html')) {
	/**
	 * @return array<string,array<string,bool>>
	 */
	function vms_public_calendar_allowed_html(): array
	{
		return array(
			'a' => array(
				'class' => true,
				'href' => true,
				'aria-hidden' => true,
			),
			'article' => array(
				'class' => true,
			),
			'div' => array(
				'class' => true,
				'aria-label' => true,
				'style' => true,
			),
			'header' => array(
				'class' => true,
			),
			'h3' => array(
				'class' => true,
			),
			'img' => array(
				'src' => true,
				'alt' => true,
				'loading' => true,
			),
			'p' => array(
				'class' => true,
			),
			'section' => array(
				'class' => true,
			),
			'span' => array(
				'class' => true,
				'aria-hidden' => true,
			),
		);
	}
}

if (!function_exists('vms_public_calendar_render_nav')) {
	function vms_public_calendar_render_nav(array $nav, string $base, array $month): string
	{
		$prev_label = vms_public_calendar_nav_label((string) ($nav['prev'] ?? ''));
		$next_label = vms_public_calendar_nav_label((string) ($nav['next'] ?? ''));
		$title = vms_public_calendar_month_label((string) ($month['ym'] ?? ''), 'F Y');
		if ($title === '') {
			$title = date_i18n('F Y', strtotime((string) ($month['start'] ?? 'now')));
		}

		ob_start();
		?>
		<div class="vms-public-cal-nav">
			<a class="vms-public-cal-nav-link" href="<?php echo esc_url(add_query_arg(array('ym' => (string) ($nav['prev'] ?? '')), $base)); ?>">← <?php echo esc_html($prev_label); ?></a>
			<div class="vms-public-cal-title"><?php echo esc_html($title); ?></div>
			<a class="vms-public-cal-nav-link" href="<?php echo esc_url(add_query_arg(array('ym' => (string) ($nav['next'] ?? '')), $base)); ?>"><?php echo esc_html($next_label); ?> →</a>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}

if (!function_exists('vms_public_calendar_get_requested_month')) {
	function vms_public_calendar_get_requested_month(): string
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public calendar filter.
		if (!isset($_GET['ym'])) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public calendar filter.
		return sanitize_text_field(wp_unslash((string) $_GET['ym']));
	}
}

if (!function_exists('vms_public_calendar_get_requested_venue')) {
	function vms_public_calendar_get_requested_venue(): string
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public calendar filter.
		if (isset($_GET['venue_id'])) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public calendar filter.
			return sanitize_text_field(wp_unslash((string) $_GET['venue_id']));
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public calendar filter.
		if (isset($_GET['venue'])) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public calendar filter.
			return sanitize_text_field(wp_unslash((string) $_GET['venue']));
		}

		return '';
	}
}

if (!function_exists('vms_public_calendar_get_requested_view')) {
	function vms_public_calendar_get_requested_view(): string
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public calendar filter.
		if (!isset($_GET['view'])) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public calendar filter.
		return sanitize_key(wp_unslash((string) $_GET['view']));
	}
}

if (!function_exists('vms_public_calendar_get_requested_show_past')) {
	function vms_public_calendar_get_requested_show_past(): ?int
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public calendar filter.
		if (!isset($_GET['show_past'])) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public calendar filter.
		return absint(wp_unslash((string) $_GET['show_past']));
	}
}

if (!function_exists('vms_public_calendar_get_request_user_agent')) {
	function vms_public_calendar_get_request_user_agent(): string
	{
		$user_agent = vms_request_server_value('HTTP_USER_AGENT');
		if ($user_agent === '') {
			return '';
		}

		return strtolower(sanitize_text_field($user_agent));
	}
}

if (!function_exists('vms_public_calendar_normalize_view')) {
	function vms_public_calendar_normalize_view(string $view, bool $allow_auto = true): string
	{
		$allowed = array('month', 'list', 'compact');
		if ($allow_auto) {
			$allowed[] = 'auto';
		}
		$view = sanitize_key($view);
		if (!in_array($view, $allowed, true)) {
			return $allow_auto ? 'auto' : 'month';
		}
		return $view;
	}
}

if (!function_exists('vms_public_calendar_shift_ym')) {
	function vms_public_calendar_shift_ym(string $ym, int $delta): string
	{
		$month = vms_parse_month_ym($ym);
		return gmdate('Y-m', strtotime(($delta >= 0 ? '+' : '') . $delta . ' month', (int) $month['start_ts']));
	}
}

if (!function_exists('vms_public_calendar_month_sequence')) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function vms_public_calendar_month_sequence(string $start_ym, int $count): array
	{
		$count = max(1, $count);
		$out = array();
		$current = $start_ym;
		for ($i = 0; $i < $count; $i++) {
			$out[] = vms_parse_month_ym($current);
			$current = vms_public_calendar_shift_ym($current, 1);
		}
		return $out;
	}
}

if (!function_exists('vms_public_calendar_join_label_list')) {
	/**
	 * @param array<int,string> $parts
	 */
	function vms_public_calendar_join_label_list(array $parts): string
	{
		$parts = array_values(array_filter(array_map('trim', $parts), static function (string $label): bool {
			return $label !== '';
		}));

		$count = count($parts);
		if ($count === 0) {
			return '';
		}
		if ($count === 1) {
			return $parts[0];
		}

		$last = array_pop($parts);
		return sprintf(
			/* translators: 1: leading list items, 2: final list item */
			__('%1$s & %2$s', 'backstage-venue-manager'),
			implode(', ', $parts),
			(string) $last
		);
	}
}

if (!function_exists('vms_public_calendar_compact_range_title')) {
	/**
	 * @param array<int,array<string,mixed>> $months
	 */
	function vms_public_calendar_compact_range_title(array $months): string
	{
		if (empty($months)) {
			return '';
		}

		$month_keys = array();
		foreach ($months as $month) {
			$ym = trim((string) ($month['ym'] ?? ''));
			if (preg_match('/^\d{4}-\d{2}$/', $ym)) {
				$month_keys[] = $ym;
			}
		}
		if (empty($month_keys)) {
			return '';
		}

		if (count($month_keys) === 1) {
			return vms_public_calendar_month_label($month_keys[0], 'F Y');
		}

		$years = array_values(array_unique(array_map(static function (string $ym): string {
			return substr($ym, 0, 4);
		}, $month_keys)));
		if (count($years) === 1) {
			$year = vms_public_calendar_month_label($month_keys[0], 'Y');
			$month_names = array_map(static function (string $ym): string {
				return vms_public_calendar_month_label($ym, 'F');
			}, $month_keys);
			return sprintf(
				/* translators: 1: month list, 2: year */
				__('%1$s %2$s', 'backstage-venue-manager'),
				vms_public_calendar_join_label_list($month_names),
				$year
			);
		}

		$full_labels = array_map(static function (string $ym): string {
			return vms_public_calendar_month_label($ym, 'F Y');
		}, $month_keys);

		return vms_public_calendar_join_label_list($full_labels);
	}
}

if (!function_exists('vms_public_calendar_compact_events_for_months')) {
	/**
	 * @param array<int,array<string,mixed>> $months
	 */
	function vms_public_calendar_compact_events_for_months(array $months, array $feed_args): array
	{
		if (empty($months)) {
			return array();
		}

		$first_month = $months[0];
		$last_month = $months[count($months) - 1];
		$start_date = isset($first_month['start']) ? (string) $first_month['start'] : '';
		$end_date = isset($last_month['end']) ? gmdate('Y-m-d', strtotime('-1 day', strtotime((string) $last_month['end']))) : '';
		if ($start_date === '' || $end_date === '') {
			return array();
		}

		if (!function_exists('vms_get_calendar_events')) {
			return array();
		}

		return (array) vms_get_calendar_events(array_merge($feed_args, array(
			'start_date' => $start_date,
			'end_date' => $end_date,
		)));
	}
}

if (!function_exists('vms_public_calendar_compact_event_month_map')) {
	/**
	 * @param array<int,array<string,mixed>> $events
	 * @return array<string,bool>
	 */
	function vms_public_calendar_compact_event_month_map(array $events): array
	{
		$out = array();
		foreach ($events as $event) {
			$date_key = trim((string) ($event['date_key'] ?? ''));
			if (preg_match('/^(\d{4}-\d{2})-\d{2}$/', $date_key, $matches)) {
				$out[$matches[1]] = true;
			}
		}
		return $out;
	}
}

if (!function_exists('vms_public_calendar_collect_event_months_forward')) {
	/**
	 * @param array<string,mixed> $feed_args
	 * @return array<int,array<string,mixed>>
	 */
	function vms_public_calendar_collect_event_months_forward(string $start_ym, int $limit, array $feed_args, int $chunk_months = 12, int $max_scan_months = 36): array
	{
		$limit = max(1, $limit);
		$chunk_months = max(1, $chunk_months);
		$max_scan_months = max($limit, $max_scan_months);
		$out = array();
		$seen = array();
		$cursor = $start_ym;
		$scanned = 0;

		while ($scanned < $max_scan_months && count($out) < $limit) {
			$months_to_scan = min($chunk_months, $max_scan_months - $scanned);
			$window_months = vms_public_calendar_month_sequence($cursor, $months_to_scan);
			if (empty($window_months)) {
				break;
			}

			$event_month_map = vms_public_calendar_compact_event_month_map(
				vms_public_calendar_compact_events_for_months($window_months, $feed_args)
			);
			foreach ($window_months as $month) {
				$ym = trim((string) ($month['ym'] ?? ''));
				if ($ym === '' || !isset($event_month_map[$ym]) || isset($seen[$ym])) {
					continue;
				}
				$out[] = $month;
				$seen[$ym] = true;
				if (count($out) >= $limit) {
					break;
				}
			}

			$cursor = vms_public_calendar_shift_ym($cursor, $months_to_scan);
			$scanned += $months_to_scan;
		}

		return $out;
	}
}

if (!function_exists('vms_public_calendar_collect_event_months_backward')) {
	/**
	 * @param array<string,mixed> $feed_args
	 * @return array<int,array<string,mixed>>
	 */
	function vms_public_calendar_collect_event_months_backward(string $before_ym, int $limit, array $feed_args, int $chunk_months = 12, int $max_scan_months = 36): array
	{
		$limit = max(1, $limit);
		$chunk_months = max(1, $chunk_months);
		$max_scan_months = max($limit, $max_scan_months);
		$out = array();
		$seen = array();
		$exclusive_end_ym = $before_ym;
		$scanned = 0;

		while ($scanned < $max_scan_months && count($out) < $limit) {
			$months_to_scan = min($chunk_months, $max_scan_months - $scanned);
			$window_start_ym = vms_public_calendar_shift_ym($exclusive_end_ym, -$months_to_scan);
			$window_months = vms_public_calendar_month_sequence($window_start_ym, $months_to_scan);
			if (empty($window_months)) {
				break;
			}

			$event_month_map = vms_public_calendar_compact_event_month_map(
				vms_public_calendar_compact_events_for_months($window_months, $feed_args)
			);
			for ($i = count($window_months) - 1; $i >= 0; $i--) {
				$month = $window_months[$i];
				$ym = trim((string) ($month['ym'] ?? ''));
				if ($ym === '' || !isset($event_month_map[$ym]) || isset($seen[$ym])) {
					continue;
				}
				$out[] = $month;
				$seen[$ym] = true;
				if (count($out) >= $limit) {
					break;
				}
			}

			$exclusive_end_ym = $window_start_ym;
			$scanned += $months_to_scan;
		}

		return array_reverse($out);
	}
}

if (!function_exists('vms_public_calendar_build_compact_context')) {
	/**
	 * @param array<string,mixed> $feed_args
	 * @return array{
	 *   months:array<int,array<string,mixed>>,
	 *   title:string,
	 *   prev_ym:string,
	 *   prev_label:string,
	 *   next_ym:string,
	 *   next_label:string
	 * }
	 */
	function vms_public_calendar_build_compact_context(string $anchor_ym, array $feed_args, int $limit = 3): array
	{
		$limit = max(1, $limit);
		$display_months = vms_public_calendar_collect_event_months_forward($anchor_ym, $limit, $feed_args);

		if (empty($display_months)) {
			$prev_months = vms_public_calendar_collect_event_months_backward($anchor_ym, $limit, $feed_args);
			return array(
				'months' => array(),
				'title' => __('No Upcoming Events', 'backstage-venue-manager'),
				'prev_ym' => isset($prev_months[0]['ym']) ? (string) $prev_months[0]['ym'] : '',
				'prev_label' => vms_public_calendar_compact_range_title($prev_months),
				'next_ym' => '',
				'next_label' => '',
			);
		}

		$first_display_ym = isset($display_months[0]['ym']) ? (string) $display_months[0]['ym'] : '';
		$last_display_ym = isset($display_months[count($display_months) - 1]['ym']) ? (string) $display_months[count($display_months) - 1]['ym'] : '';
		$prev_months = ($first_display_ym !== '')
			? vms_public_calendar_collect_event_months_backward($first_display_ym, $limit, $feed_args)
			: array();
		$next_months = ($last_display_ym !== '')
			? vms_public_calendar_collect_event_months_forward(vms_public_calendar_shift_ym($last_display_ym, 1), $limit, $feed_args)
			: array();

		return array(
			'months' => $display_months,
			'title' => vms_public_calendar_compact_range_title($display_months),
			'prev_ym' => isset($prev_months[0]['ym']) ? (string) $prev_months[0]['ym'] : '',
			'prev_label' => vms_public_calendar_compact_range_title($prev_months),
			'next_ym' => !empty($next_months) && $last_display_ym !== ''
				? vms_public_calendar_shift_ym($last_display_ym, 1)
				: '',
			'next_label' => vms_public_calendar_compact_range_title($next_months),
		);
	}
}

if (!function_exists('vms_public_calendar_render_compact_nav')) {
	/**
	 * @param array<string,mixed> $context
	 */
	function vms_public_calendar_render_compact_nav(array $context, string $base): string
	{
		$title = trim((string) ($context['title'] ?? ''));
		$prev_ym = trim((string) ($context['prev_ym'] ?? ''));
		$prev_label = trim((string) ($context['prev_label'] ?? ''));
		$next_ym = trim((string) ($context['next_ym'] ?? ''));
		$next_label = trim((string) ($context['next_label'] ?? ''));

		ob_start();
		?>
		<div class="vms-public-cal-nav">
			<?php if ($prev_ym !== '' && $prev_label !== ''): ?>
				<a class="vms-public-cal-nav-link" href="<?php echo esc_url(add_query_arg(array('ym' => $prev_ym), $base)); ?>">← <?php echo esc_html($prev_label); ?></a>
			<?php else: ?>
				<span class="vms-public-cal-nav-link" aria-hidden="true">&nbsp;</span>
			<?php endif; ?>
			<div class="vms-public-cal-title"><?php echo esc_html($title); ?></div>
			<?php if ($next_ym !== '' && $next_label !== ''): ?>
				<a class="vms-public-cal-nav-link" href="<?php echo esc_url(add_query_arg(array('ym' => $next_ym), $base)); ?>"><?php echo esc_html($next_label); ?> →</a>
			<?php else: ?>
				<span class="vms-public-cal-nav-link" aria-hidden="true">&nbsp;</span>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}

if (!function_exists('vms_public_calendar_vendor_lines')) {
	/**
	 * @param array<string,mixed> $event
	 * @return array<int,array<string,string>>
	 */
	function vms_public_calendar_vendor_lines(array $event): array
	{
		$out = array();
		$event_url = trim((string) ($event['public_url'] ?? ''));
		$groups = isset($event['vendor_groups']) && is_array($event['vendor_groups']) ? $event['vendor_groups'] : array();
		foreach ($groups as $group) {
			if (!is_array($group)) {
				continue;
			}
			$icon = isset($group['icon']) ? trim((string) $group['icon']) : '';
			$vendors = isset($group['vendors']) && is_array($group['vendors']) ? $group['vendors'] : array();

			foreach ($vendors as $vendor) {
				if (is_array($vendor)) {
					$name = trim((string) ($vendor['display_name'] ?? ''));
					if ($name === '') {
						continue;
					}
					$url = trim((string) ($vendor['public_url'] ?? ''));
					if ($url === '' && $event_url !== '') {
						$url = $event_url; // Core-safe fallback linking rule.
					}
					$out[] = array(
						'kind' => 'vendor',
						'text' => trim(($icon !== '' ? ($icon . ' ') : '') . $name),
						'url' => $url,
					);
				}
			}

			if (!empty($group['has_open_slots'])) {
				$out[] = array(
					'kind' => 'slot',
					'text' => trim(($icon !== '' ? ($icon . ' ') : '') . '+'),
					'url' => trim((string) ($group['open_slot_link'] ?? '')),
				);
			}
		}
		return $out;
	}
}

if (!function_exists('vms_public_calendar_vendor_names')) {
	/**
	 * @param array<string,mixed> $event
	 * @return array<int,string>
	 */
	function vms_public_calendar_vendor_names(array $event): array
	{
		$out = array();
		$seen = array();
		$groups = isset($event['vendor_groups']) && is_array($event['vendor_groups']) ? $event['vendor_groups'] : array();
		foreach ($groups as $group) {
			if (!is_array($group)) {
				continue;
			}
			$vendors = isset($group['vendors']) && is_array($group['vendors']) ? $group['vendors'] : array();
			foreach ($vendors as $vendor) {
				if (!is_array($vendor)) {
					continue;
				}
				$name = trim((string) ($vendor['display_name'] ?? ''));
				if ($name === '' || isset($seen[$name])) {
					continue;
				}
				$seen[$name] = true;
				$out[] = $name;
			}
		}
		return $out;
	}
}

if (!function_exists('vms_public_calendar_vendor_slots')) {
	/**
	 * Ordered vendor rows for calendar rendering.
	 *
	 * @param array<string,mixed> $event
	 * @return array<int,array{name:string,icon:string,url:string}>
	 */
	function vms_public_calendar_vendor_slots(array $event): array
	{
		$out = array();
		$seen = array();
		$event_url = trim((string) ($event['public_url'] ?? ''));
		$title_fallback = trim((string) ($event['title'] ?? ''));
		if ($title_fallback === '') {
			$title_fallback = __('Event', 'backstage-venue-manager');
		}

		$icon_map = function_exists('vms_calendar_vendor_type_icons')
			? (array) vms_calendar_vendor_type_icons()
			: array();

		$plan_id = (int) ($event['event_plan_id'] ?? 0);
		if ($plan_id > 0 && function_exists('vms_calendar_plan_vendor_ids')) {
			$ids = (array) vms_calendar_plan_vendor_ids($plan_id);
			$ordered_ids = array();
			$band_id = isset($ids['band_id']) ? absint($ids['band_id']) : 0;
			if ($band_id > 0) {
				$ordered_ids[] = $band_id;
			}
			foreach ((array) ($ids['secondary_ids'] ?? array()) as $sid) {
				$sid = absint($sid);
				if ($sid > 0) {
					$ordered_ids[] = $sid;
				}
			}
			$ordered_ids = array_values(array_unique($ordered_ids));
			foreach ($ordered_ids as $vendor_id) {
				$name = trim((string) get_the_title($vendor_id));
				if ($name === '' || isset($seen[$name])) {
					continue;
				}
				$seen[$name] = true;
				$icon = '';
				if (function_exists('vms_calendar_vendor_primary_type')) {
					$type = (array) vms_calendar_vendor_primary_type((int) $vendor_id);
					$slug = sanitize_key((string) ($type['slug'] ?? ''));
					if ($slug !== '' && isset($icon_map[$slug])) {
						$icon = trim((string) $icon_map[$slug]);
					}
				}
				$out[] = array(
					'name' => $name,
					'icon' => $icon,
					'url' => $event_url,
				);
			}
		}

		// Fallback to feed-provided vendor groups if plan lookups are unavailable.
		if (empty($out)) {
			$groups = isset($event['vendor_groups']) && is_array($event['vendor_groups']) ? $event['vendor_groups'] : array();
			foreach ($groups as $group) {
				if (!is_array($group)) {
					continue;
				}
				$icon = trim((string) ($group['icon'] ?? ''));
				$vendors = isset($group['vendors']) && is_array($group['vendors']) ? $group['vendors'] : array();
				foreach ($vendors as $vendor) {
					if (!is_array($vendor)) {
						continue;
					}
					$name = trim((string) ($vendor['display_name'] ?? ''));
					if ($name === '' || isset($seen[$name])) {
						continue;
					}
					$seen[$name] = true;
					$out[] = array(
						'name' => $name,
						'icon' => $icon,
						'url' => $event_url,
					);
				}
			}
		}

		if (empty($out)) {
			$out[] = array(
				'name' => $title_fallback,
				'icon' => '',
				'url' => $event_url,
			);
		}

		return $out;
	}
}

if (!function_exists('vms_public_calendar_render_vendor_link')) {
	function vms_public_calendar_render_vendor_link(string $name, string $icon, string $class, string $url): string
	{
		$name = trim($name);
		if ($name === '') {
			return '';
		}
		$icon = trim($icon);
		$inner = '';
		if ($icon !== '') {
			$inner .= '<span class="vms-cal-vendor-icon" aria-hidden="true">' . esc_html($icon) . '</span>';
		}
		$inner .= '<span class="vms-cal-vendor-name">' . esc_html($name) . '</span>';
		if ($url !== '') {
			return '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">' . $inner . '</a>';
		}
		return '<span class="' . esc_attr($class) . '">' . $inner . '</span>';
	}
}

if (!function_exists('vms_public_calendar_render_list_view')) {
	/**
	 * @param array<int,array<string,mixed>> $events
	 */
	function vms_public_calendar_render_list_view(array $events, bool $show_vendors, bool $show_images, bool $show_open_closed): string
	{
		if (empty($events)) {
			return '<p class="vms-public-cal-empty">' . esc_html__('No events found for this range.', 'backstage-venue-manager') . '</p>';
		}

		ob_start();
		echo '<div class="vms-public-cal-list">';
		foreach ($events as $event) {
			$date_key = trim((string) ($event['date_key'] ?? ''));
			$event_url = trim((string) ($event['public_url'] ?? ''));
			$image_url = trim((string) ($event['image_url'] ?? ''));
			$excerpt = trim((string) ($event['excerpt'] ?? ''));
			$title = trim((string) ($event['title'] ?? ''));
			if ($title === '') {
				$title = __('Event', 'backstage-venue-manager');
			}

			$start_label = vms_public_calendar_time_label((string) ($event['start_local'] ?? ''));
			$end_label = vms_public_calendar_time_label((string) ($event['end_local'] ?? ''));
			$time_label = $start_label;
			if ($start_label !== '' && $end_label !== '') {
				$time_label = $start_label . ' - ' . $end_label;
			}

			$date_label = $date_key;
			if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_key)) {
				$date_label = date_i18n('F j, Y', strtotime($date_key . ' 00:00:00'));
			}

			$is_past_event = vms_public_calendar_event_is_past($event);
			$plan_id = absint($event['event_plan_id'] ?? 0);
			$plan_status = sanitize_key((string) ($event['plan_status'] ?? ''));
			$is_cancelled = ($plan_status === 'cancelled');
			$rescheduled = ($is_cancelled && $plan_id > 0 && function_exists('vms_event_plan_get_public_reschedule_destination'))
				? (array) vms_event_plan_get_public_reschedule_destination($plan_id)
				: array();
			$is_rescheduled = $is_cancelled && !empty($rescheduled['url']);
			// Calendar cards keep cancelled/rescheduled state classes for styling/CTA logic,
			// but do not render image ribbons in this view.

			$vendor_slots = $show_vendors ? vms_public_calendar_vendor_slots($event) : array();
			if (empty($vendor_slots)) {
				$vendor_slots = array(
					array(
						'name' => $title,
						'icon' => '',
						'url' => $event_url,
					),
				);
			}
			$primary = $vendor_slots[0] ?? array('name' => $title, 'icon' => '', 'url' => $event_url);
			$secondary = $vendor_slots[1] ?? null;

			$article_classes = array('vms-public-cal-item', 'vms-public-cal-rich');
			if ($is_cancelled) {
				$article_classes[] = 'is-cancelled';
			}
			if ($is_rescheduled) {
				$article_classes[] = 'is-rescheduled';
			}
			echo '<article class="' . esc_attr(implode(' ', $article_classes)) . '">';
			if ($image_url !== '' || $show_images) {
				if ($event_url !== '' && $image_url !== '') {
					echo '<a class="vms-public-cal-rich-media" href="' . esc_url($event_url) . '"><img src="' . esc_url($image_url) . '" alt="" loading="lazy"></a>';
				} elseif ($image_url !== '') {
					echo '<div class="vms-public-cal-rich-media"><img src="' . esc_url($image_url) . '" alt="" loading="lazy"></div>';
				}
			}

				echo '<div class="vms-public-cal-rich-body">';
				echo wp_kses(vms_public_calendar_render_vendor_link(
					(string) ($primary['name'] ?? $title),
					(string) ($primary['icon'] ?? ''),
					'vms-cal-vendor-row vms-public-cal-rich-vendor is-primary',
					$event_url
				), vms_public_calendar_allowed_html());
				if (is_array($secondary)) {
					echo wp_kses(vms_public_calendar_render_vendor_link(
						(string) ($secondary['name'] ?? ''),
						(string) ($secondary['icon'] ?? ''),
						'vms-cal-vendor-row vms-public-cal-rich-vendor is-secondary',
						$event_url
					), vms_public_calendar_allowed_html());
				}

			if ($date_label !== '') {
				echo '<div class="vms-public-cal-rich-date">' . esc_html($date_label) . '</div>';
			}
			if ($time_label !== '') {
				echo '<div class="vms-public-cal-rich-time">' . esc_html($time_label) . '</div>';
			}
			if ($excerpt !== '') {
				echo '<p class="vms-public-cal-rich-excerpt">' . esc_html($excerpt) . '</p>';
			}
			if ($event_url !== '' && !$is_past_event) {
				$action_label = $is_cancelled ? __('View Details', 'backstage-venue-manager') : __('Get Tickets', 'backstage-venue-manager');
				echo '<div class="vms-public-cal-rich-actions"><a class="vms-public-cal-rich-ticket" href="' . esc_url($event_url) . '">' . esc_html($action_label) . '</a></div>';
			}
			echo '</div>';
			echo '</article>';
		}
		echo '</div>';
		return (string) ob_get_clean();
	}
}

if (!function_exists('vms_public_calendar_build_month_days')) {
	/**
	 * @param array<int,array<string,mixed>> $events
	 * @return array<int,array<int,array<string,mixed>>>
	 */
	function vms_public_calendar_build_month_days(array $events): array
	{
		$days = array();
		foreach ($events as $event) {
			$date = (string) ($event['date_key'] ?? '');
			if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
				continue;
			}
			$ts = strtotime($date . ' 00:00:00');
			if (!$ts) {
				continue;
			}
			$day = (int) gmdate('j', $ts);

			$title = trim((string) ($event['title'] ?? ''));
			if ($title === '') {
				$title = __('Event', 'backstage-venue-manager');
			}

			$sort_time = '';
			$start_label = vms_public_calendar_time_label((string) ($event['start_local'] ?? ''));
			$end_label = vms_public_calendar_time_label((string) ($event['end_local'] ?? ''));
			$time_label = $start_label;
			if ($start_label !== '' && $end_label !== '') {
				$time_label = $start_label . ' - ' . $end_label;
			}

			$start_local = (string) ($event['start_local'] ?? '');
			if ($start_local !== '') {
				try {
					$dt = new DateTimeImmutable($start_local);
					$sort_time = $dt->format('H:i');
				} catch (Exception $e) {
					$sort_time = '';
				}
			}

			$vendor_slots = array_slice(vms_public_calendar_vendor_slots($event), 0, 2);
			$primary_vendor = isset($vendor_slots[0]['name']) ? trim((string) $vendor_slots[0]['name']) : '';
			if ($primary_vendor === '') {
				$primary_vendor = $title;
			}
			$secondary_vendor = isset($vendor_slots[1]['name']) ? trim((string) $vendor_slots[1]['name']) : '';

			$days[$day][] = array(
				'plan_id' => (int) ($event['event_plan_id'] ?? 0),
				'is_past' => vms_public_calendar_event_is_past($event),
				'date' => $date,
				'date_label' => date_i18n('F j, Y', strtotime($date . ' 00:00:00')),
				'title' => $title,
				'primary_vendor' => $primary_vendor,
				'secondary_vendor' => $secondary_vendor,
				'vendors' => $vendor_slots,
				'event_plan_id' => (int) ($event['event_plan_id'] ?? 0),
				'plan_status' => sanitize_key((string) ($event['plan_status'] ?? '')),
				'image_url' => (string) ($event['image_url'] ?? ''),
				'time_label' => $time_label,
				'sort_time' => $sort_time,
				'excerpt' => trim((string) ($event['excerpt'] ?? '')),
				'view_url' => (string) ($event['public_url'] ?? ''),
			);
		}

		foreach ($days as $day => $list) {
			usort($list, static function ($a, $b) {
				return strcmp((string) ($a['sort_time'] ?? ''), (string) ($b['sort_time'] ?? ''));
			});
			$days[$day] = $list;
		}

		return $days;
	}
}

if (!function_exists('vms_public_calendar_status_badge')) {
	function vms_public_calendar_status_badge(string $status): string
	{
		$status = $status !== '' ? $status : 'draft';
		if (function_exists('vms_cal_status_badge')) {
			return (string) vms_cal_status_badge($status);
		}

		$label = strtoupper($status);
		$class = 'vms-badge vms-badge-grey';
		if ($status === 'ready') {
			$class = 'vms-badge vms-badge-amber';
		}
		if ($status === 'published') {
			$class = 'vms-badge vms-badge-green';
		}

		return '<span class="' . esc_attr($class) . '">' . esc_html($label) . '</span>';
	}
}

if (!function_exists('vms_public_calendar_build_day_states')) {
	/**
	 * Build per-day cell state classes for the current month.
	 *
	 * @param array<string,mixed> $month
	 * @return array<int,array{is_past:bool,is_today:bool,is_open:bool|null}>
	 */
	function vms_public_calendar_build_day_states(array $month, int $venue_id, bool $show_open_closed): array
	{
		$out = array();
		$start_ts = isset($month['start_ts']) ? (int) $month['start_ts'] : 0;
		$days_in_month = isset($month['days_in_month']) ? (int) $month['days_in_month'] : 0;
		if ($start_ts <= 0 || $days_in_month <= 0) {
			return $out;
		}

		$tz = function_exists('vms_get_timezone') ? vms_get_timezone() : wp_timezone();
		$today = wp_date('Y-m-d', time(), $tz);

			for ($day = 1; $day <= $days_in_month; $day++) {
				$ymd = gmdate('Y-m-d', strtotime('+' . ($day - 1) . ' days', $start_ts));
				$is_past = ($ymd < $today);
				$is_today = ($ymd === $today);
				$is_open = null;
				if ($show_open_closed && $venue_id > 0 && function_exists('vms_venue_is_open_on_date')) {
					$is_open = (bool) vms_venue_is_open_on_date($venue_id, $ymd);
				}
				$out[$day] = array(
					'is_past' => $is_past,
					'is_today' => $is_today,
					'is_open' => $is_open,
				);
			}

	return $out;
	}
}

if (!function_exists('vms_public_calendar_filter_events_for_month')) {
	/**
	 * @param array<int,array<string,mixed>> $events
	 * @return array<int,array<string,mixed>>
	 */
	function vms_public_calendar_filter_events_for_month(array $events, string $ym): array
	{
		$out = array();
		$prefix = $ym . '-';
		foreach ($events as $event) {
			$date_key = isset($event['date_key']) ? (string) $event['date_key'] : '';
			if ($date_key !== '' && strpos($date_key, $prefix) === 0) {
				$out[] = $event;
			}
		}
		return $out;
	}
}

if (!function_exists('vms_public_calendar_day_cell_classes')) {
	/**
	 * @param array{is_past?:bool,is_today?:bool,is_open?:bool|null} $state
	 * @param array<int,string> $extra_classes
	 * @return array<int,string>
	 */
	function vms_public_calendar_day_cell_classes(array $state = array(), array $extra_classes = array()): array
	{
		$classes = array_merge(array('vms-cal-cell'), $extra_classes);
		if (!empty($state['is_past'])) {
			$classes[] = 'is-past';
		}
		if (!empty($state['is_today'])) {
			$classes[] = 'is-today';
		}
		if (array_key_exists('is_open', $state) && $state['is_open'] !== null) {
			$classes[] = !empty($state['is_open']) ? 'is-open' : 'is-closed';
		}
		return $classes;
	}
}

if (!function_exists('vms_public_calendar_render_day_entries')) {
	/**
	 * @param array<int,array<string,mixed>> $entries
	 */
	function vms_public_calendar_render_day_entries(array $entries, string $popover_align_class = ''): string
	{
		if (empty($entries)) {
			return '';
		}

		ob_start();
		foreach ($entries as $ev) {
			$title = trim((string) ($ev['title'] ?? ''));
			if ($title === '') {
				$title = __('Event', 'backstage-venue-manager');
			}
			$vendors = isset($ev['vendors']) && is_array($ev['vendors']) ? $ev['vendors'] : array();
			if (empty($vendors)) {
				$vendors = array(array(
					'name' => trim((string) ($ev['primary_vendor'] ?? $title)),
					'icon' => '',
					'url' => trim((string) ($ev['view_url'] ?? '')),
				));
				$secondary_name = trim((string) ($ev['secondary_vendor'] ?? ''));
				if ($secondary_name !== '') {
					$vendors[] = array(
						'name' => $secondary_name,
						'icon' => '',
						'url' => trim((string) ($ev['view_url'] ?? '')),
					);
				}
			}
			$primary_vendor = isset($vendors[0]) && is_array($vendors[0]) ? $vendors[0] : array('name' => $title, 'icon' => '', 'url' => '');
			$secondary_vendor = isset($vendors[1]) && is_array($vendors[1]) ? $vendors[1] : null;
			$view_url = trim((string) ($ev['view_url'] ?? ''));
			$date_label = trim((string) ($ev['date_label'] ?? ''));
			$time_label = trim((string) ($ev['time_label'] ?? ''));
			$excerpt = trim((string) ($ev['excerpt'] ?? ''));
			$img_url = trim((string) ($ev['image_url'] ?? ''));
			$is_past_event = !empty($ev['is_past']);
			$plan_id = absint($ev['event_plan_id'] ?? 0);
			$plan_status = sanitize_key((string) ($ev['plan_status'] ?? ''));
			$is_cancelled = ($plan_status === 'cancelled');
			$rescheduled = ($is_cancelled && $plan_id > 0 && function_exists('vms_event_plan_get_public_reschedule_destination'))
				? (array) vms_event_plan_get_public_reschedule_destination($plan_id)
				: array();
			$is_rescheduled = $is_cancelled && !empty($rescheduled['url']);
			$entry_classes = array('vms-cal-entry');
			if ($is_cancelled) {
				$entry_classes[] = 'is-cancelled';
			}
			if ($is_rescheduled) {
				$entry_classes[] = 'is-rescheduled';
			}

				echo '<div class="' . esc_attr(implode(' ', $entry_classes)) . '">';
				if ($img_url !== '') {
					if ($view_url !== '') {
						echo '<a class="vms-cal-entry-image" href="' . esc_url($view_url) . '"><img src="' . esc_url($img_url) . '" alt="" loading="lazy"></a>';
					} else {
						echo '<div class="vms-cal-entry-image"><img src="' . esc_url($img_url) . '" alt="" loading="lazy"></div>';
					}
				}
				echo '<div class="vms-cal-entry-vendors">';
				echo wp_kses(vms_public_calendar_render_vendor_link(
					trim((string) ($primary_vendor['name'] ?? $title)),
					trim((string) ($primary_vendor['icon'] ?? '')),
					'vms-cal-vendor-row vms-cal-entry-vendor is-primary',
					$view_url
				), vms_public_calendar_allowed_html());
				if (is_array($secondary_vendor)) {
					echo wp_kses(vms_public_calendar_render_vendor_link(
						trim((string) ($secondary_vendor['name'] ?? '')),
						trim((string) ($secondary_vendor['icon'] ?? '')),
						'vms-cal-vendor-row vms-cal-entry-vendor is-secondary',
						$view_url
					), vms_public_calendar_allowed_html());
				}
				echo '</div>';

				echo '<div class="vms-cal-pop' . esc_attr($popover_align_class) . '">';
				echo '<div class="vms-cal-pop-body">';
				if ($img_url !== '') {
					if ($view_url !== '') {
						echo '<a class="vms-cal-pop-media" href="' . esc_url($view_url) . '"><img src="' . esc_url($img_url) . '" alt="" loading="lazy"></a>';
					} else {
						echo '<div class="vms-cal-pop-media"><img src="' . esc_url($img_url) . '" alt="" loading="lazy"></div>';
					}
				}
				echo '<div class="vms-cal-pop-vendors">';
				echo wp_kses(vms_public_calendar_render_vendor_link(
					trim((string) ($primary_vendor['name'] ?? $title)),
					trim((string) ($primary_vendor['icon'] ?? '')),
					'vms-cal-vendor-row vms-cal-pop-vendor is-primary',
					$view_url
				), vms_public_calendar_allowed_html());
				if (is_array($secondary_vendor)) {
					echo wp_kses(vms_public_calendar_render_vendor_link(
						trim((string) ($secondary_vendor['name'] ?? '')),
						trim((string) ($secondary_vendor['icon'] ?? '')),
						'vms-cal-vendor-row vms-cal-pop-vendor is-secondary',
						$view_url
					), vms_public_calendar_allowed_html());
				}
			echo '</div>';
			if ($date_label !== '') {
				echo '<div class="vms-cal-pop-date">' . esc_html($date_label) . '</div>';
			}
			if ($time_label !== '') {
				echo '<div class="vms-cal-pop-time">' . esc_html($time_label) . '</div>';
			}
			if ($excerpt !== '') {
				echo '<div class="vms-cal-pop-excerpt">' . esc_html($excerpt) . '</div>';
			}
			if ($view_url !== '' && !$is_past_event) {
				$action_label = $is_cancelled ? __('View Details', 'backstage-venue-manager') : __('Get Tickets', 'backstage-venue-manager');
				echo '<div class="vms-cal-pop-actions"><a class="vms-cal-pop-ticket" href="' . esc_url($view_url) . '">' . esc_html($action_label) . '</a></div>';
			}
			echo '</div></div>';
			echo '</div>';
		}

		return (string) ob_get_clean();
	}
}

if (!function_exists('vms_public_calendar_compact_weekday_labels')) {
	/**
	 * @return array<int,string>
	 */
	function vms_public_calendar_compact_weekday_labels(): array
	{
		return array(
			0 => __('Sun', 'backstage-venue-manager'),
			1 => __('Mon', 'backstage-venue-manager'),
			2 => __('Tue', 'backstage-venue-manager'),
			3 => __('Wed', 'backstage-venue-manager'),
			4 => __('Thu', 'backstage-venue-manager'),
			5 => __('Fri', 'backstage-venue-manager'),
			6 => __('Sat', 'backstage-venue-manager'),
		);
	}
}

if (!function_exists('vms_public_calendar_build_compact_month_layout')) {
	/**
	 * @param array<string,mixed> $month
	 * @param array<int,array<int,array<string,mixed>>> $days
	 * @param array<int,array{is_past:bool,is_today:bool,is_open:bool|null}> $day_states
	 * @return array{active_weekdays:array<int,int>,rows:int,cells:array<int,array<int,array<string,mixed>>>}
	 */
	function vms_public_calendar_build_compact_month_layout(array $month, array $days, array $day_states = array()): array
	{
		$active_weekdays = array();
		$start_ts = isset($month['start_ts']) ? (int) $month['start_ts'] : 0;
		$days_in_month = isset($month['days_in_month']) ? (int) $month['days_in_month'] : 0;
		if ($start_ts <= 0 || $days_in_month <= 0) {
			return array(
				'active_weekdays' => array(),
				'rows' => 0,
				'cells' => array(),
			);
		}

		for ($day = 1; $day <= $days_in_month; $day++) {
			$day_ts = strtotime('+' . ($day - 1) . ' days', $start_ts);
			if (!$day_ts) {
				continue;
			}
			$weekday = (int) gmdate('w', $day_ts);
			$has_events = !empty($days[$day]);
			$is_open = isset($day_states[$day]) && is_array($day_states[$day]) && array_key_exists('is_open', $day_states[$day])
				? $day_states[$day]['is_open']
				: null;
			if ($has_events || $is_open === true) {
				$active_weekdays[$weekday] = $weekday;
			}
		}

		if (empty($active_weekdays)) {
			return array(
				'active_weekdays' => array(),
				'rows' => 0,
				'cells' => array(),
			);
		}

		ksort($active_weekdays);
		$first_wday = (int) gmdate('w', $start_ts);
		$row_count = 0;
		$cells = array();
		for ($day = 1; $day <= $days_in_month; $day++) {
			$day_ts = strtotime('+' . ($day - 1) . ' days', $start_ts);
			if (!$day_ts) {
				continue;
			}
			$weekday = (int) gmdate('w', $day_ts);
			if (!isset($active_weekdays[$weekday])) {
				continue;
			}
			$row = (int) floor(($first_wday + ($day - 1)) / 7) + 1;
			$row_count = max($row_count, $row);
			$cells[$row][$weekday] = array(
				'day' => $day,
				'events' => isset($days[$day]) && is_array($days[$day]) ? $days[$day] : array(),
				'state' => isset($day_states[$day]) && is_array($day_states[$day]) ? $day_states[$day] : array(),
			);
		}

		return array(
			'active_weekdays' => array_values($active_weekdays),
			'rows' => $row_count,
			'cells' => $cells,
		);
	}
}

if (!function_exists('vms_public_calendar_render_compact_view')) {
	/**
	 * @param array<int,array<string,mixed>> $months
	 * @param array<int,array<string,mixed>> $events
	 */
	function vms_public_calendar_render_compact_view(array $months, array $events, int $state_venue_id, bool $show_open_closed): string
	{
		if (empty($months)) {
			return '<p class="vms-public-cal-empty">' . esc_html__('No upcoming public events are currently scheduled. Please check back soon.', 'backstage-venue-manager') . '</p>';
		}

		$weekday_labels = vms_public_calendar_compact_weekday_labels();
		ob_start();
		echo '<div class="vms-public-cal-compact">';
		$rendered_count = 0;
		foreach ($months as $month) {
			if (!is_array($month)) {
				continue;
			}
			$month_ym = isset($month['ym']) ? (string) $month['ym'] : '';
			$month_events = vms_public_calendar_filter_events_for_month($events, $month_ym);
			if (empty($month_events)) {
				continue;
			}
			$days = vms_public_calendar_build_month_days($month_events);
			$day_states = vms_public_calendar_build_day_states($month, $state_venue_id, $show_open_closed);
			$layout = vms_public_calendar_build_compact_month_layout($month, $days, $day_states);
			$active_weekdays = isset($layout['active_weekdays']) && is_array($layout['active_weekdays']) ? $layout['active_weekdays'] : array();
			$row_count = isset($layout['rows']) ? (int) $layout['rows'] : 0;
			$cells = isset($layout['cells']) && is_array($layout['cells']) ? $layout['cells'] : array();

			if (empty($active_weekdays) || $row_count <= 0) {
				continue;
			}

			$rendered_count++;
			echo '<section class="vms-public-cal-compact-month">';
			echo '<header class="vms-public-cal-compact-month-head">';
			echo '<h3 class="vms-public-cal-compact-month-title">' . esc_html(vms_public_calendar_month_label($month_ym, 'F Y')) . '</h3>';
			echo '</header>';
			echo '<div class="vms-public-cal-compact-grid" style="--vms-compact-columns:' . esc_attr((string) count($active_weekdays)) . ';">';
			foreach ($active_weekdays as $weekday) {
				echo '<div class="vms-public-cal-compact-head">' . esc_html($weekday_labels[$weekday] ?? (string) $weekday) . '</div>';
			}

			for ($row = 1; $row <= $row_count; $row++) {
				foreach ($active_weekdays as $column_index => $weekday) {
					if (!isset($cells[$row][$weekday]) || !is_array($cells[$row][$weekday])) {
						echo '<div class="vms-cal-cell vms-cal-empty vms-public-cal-compact-blank"></div>';
						continue;
					}
					$cell = $cells[$row][$weekday];
					$state = isset($cell['state']) && is_array($cell['state']) ? $cell['state'] : array();
					$cell_classes = vms_public_calendar_day_cell_classes($state, array('vms-public-cal-compact-cell'));
					$popover_align_class = ($column_index === count($active_weekdays) - 1) ? ' is-right' : '';
						echo '<div class="' . esc_attr(implode(' ', $cell_classes)) . '">';
						echo '<div class="vms-cal-daynum">' . esc_html((string) ($cell['day'] ?? '')) . '</div>';
						echo wp_kses(vms_public_calendar_render_day_entries(
							isset($cell['events']) && is_array($cell['events']) ? $cell['events'] : array(),
							$popover_align_class
						), vms_public_calendar_allowed_html());
						echo '</div>';
				}
			}

			echo '</div>';
			echo '</section>';
		}

		if ($rendered_count === 0) {
			echo '<p class="vms-public-cal-empty">' . esc_html__('No upcoming public events are currently scheduled. Please check back soon.', 'backstage-venue-manager') . '</p>';
		}

		echo '</div>';
		return (string) ob_get_clean();
	}
}

if (!function_exists('vms_public_calendar_render_month_grid')) {
	/**
	 * Frontend-safe month grid renderer used when admin calendar helpers are unavailable.
	 *
	 * @param array<string,mixed> $month
	 * @param array<int,array<int,array<string,mixed>>> $days
	 * @param array<int,array{is_past:bool,is_today:bool,is_open:bool|null}> $day_states
	 */
	function vms_public_calendar_render_month_grid(array $month, array $days, array $day_states = array()): string
	{
		$start_ts = isset($month['start_ts']) ? (int) $month['start_ts'] : 0;
		$days_in_month = isset($month['days_in_month']) ? (int) $month['days_in_month'] : 0;
		if ($start_ts <= 0 || $days_in_month <= 0) {
			return '';
		}

		$first_wday = (int) gmdate('w', $start_ts);
		$out = '';
		$out .= '<div class="vms-cal-grid">';
		$out .= '<div class="vms-cal-head">Sun</div><div class="vms-cal-head">Mon</div><div class="vms-cal-head">Tue</div><div class="vms-cal-head">Wed</div><div class="vms-cal-head">Thu</div><div class="vms-cal-head">Fri</div><div class="vms-cal-head">Sat</div>';

		for ($i = 0; $i < $first_wday; $i++) {
			$out .= '<div class="vms-cal-cell vms-cal-empty"></div>';
		}

		for ($day = 1; $day <= $days_in_month; $day++) {
			$grid_col = (($first_wday + ($day - 1)) % 7) + 1;
			$popover_align_class = ($grid_col >= 6) ? ' is-right' : '';
			$state = (isset($day_states[$day]) && is_array($day_states[$day])) ? $day_states[$day] : array();
			$cell_classes = vms_public_calendar_day_cell_classes($state);
			$out .= '<div class="' . esc_attr(implode(' ', $cell_classes)) . '">';
			$out .= '<div class="vms-cal-daynum">' . (int) $day . '</div>';

			if (!empty($days[$day]) && is_array($days[$day])) {
				$out .= vms_public_calendar_render_day_entries($days[$day], $popover_align_class);
			}

			$out .= '</div>';
		}

		$out .= '</div>';
		return $out;
	}
}

if (!function_exists('vms_public_calendar_shortcode_handler')) {
	/**
	 * @param array<string,mixed> $atts
	 */
	function vms_public_calendar_shortcode_handler($atts = array(), $content = '', $tag = 'vms_public_calendar'): string
	{
		$is_legacy = ($tag === 'vms_venue_calendar');

		$defaults = array(
			'venue' => $is_legacy ? '' : 'all',
			'month' => '',
			'view' => $is_legacy ? 'month' : '',
			'show_vendors' => '1',
			'show_images' => '0',
			'show_open_closed' => '1',
		);
			$atts = shortcode_atts($defaults, (array) $atts, $tag);

			$current_ym = wp_date('Y-m', time(), function_exists('vms_get_timezone') ? vms_get_timezone() : wp_timezone());

			$ym = $atts['month'] !== '' ? sanitize_text_field((string) $atts['month']) : '';
			$requested_ym = vms_public_calendar_get_requested_month();
			if ($requested_ym !== '') {
				$ym = $requested_ym;
			}
			if ($ym === '') {
				$ym = $current_ym;
			}
		$month = vms_parse_month_ym($ym);
		$month_end_inclusive = gmdate('Y-m-d', strtotime('-1 day', strtotime($month['end'])));
		$nav = vms_calendar_prev_next($month['ym']);

		$show_vendors = ((string) $atts['show_vendors'] === '1');
		$show_images = ((string) $atts['show_images'] === '1');
		$show_open_closed = ((string) $atts['show_open_closed'] === '1');

		$venues = get_posts(array(
			'post_type' => 'vms_venue',
			'posts_per_page' => -1,
			'orderby' => 'title',
			'order' => 'ASC',
			));
			$venues = is_array($venues) ? $venues : array();

			$venue_raw = sanitize_text_field((string) $atts['venue']);
			$requested_venue = vms_public_calendar_get_requested_venue();
			if ($requested_venue !== '') {
				$venue_raw = $requested_venue;
			}

		$venue_ids = 'all';
		$selected_venue = 'all';
		if ($venue_raw !== '' && strtolower($venue_raw) !== 'all') {
			$vid = absint($venue_raw);
			if ($vid > 0) {
				$venue_ids = array($vid);
				$selected_venue = (string) $vid;
			}
		}
		if ($is_legacy && $venue_ids === 'all' && !empty($venues)) {
			$venue_ids = array((int) $venues[0]->ID);
			$selected_venue = (string) ((int) $venues[0]->ID);
		}
		$show_venue_selector = (count($venues) > 1);
		if (!$show_venue_selector && !empty($venues)) {
			$single_venue_id = (int) $venues[0]->ID;
			$venue_ids = array($single_venue_id);
			$selected_venue = (string) $single_venue_id;
		}

		if ($is_legacy && empty($venues)) {
			return '<div>' . esc_html__('No venues found.', 'backstage-venue-manager') . '</div>';
		}

		$settings = function_exists('vms_calendar_settings') ? (array) vms_calendar_settings() : (array) get_option('vms_settings', array());
		if (!$is_legacy && array_key_exists('calendar_public_shortcode_enabled', $settings) && empty($settings['calendar_public_shortcode_enabled'])) {
			return '';
		}
			$default_view = $is_legacy
				? 'month'
				: vms_public_calendar_normalize_view((string) ($settings['calendar_public_default_view'] ?? 'auto'));
			$view = trim((string) $atts['view']) !== ''
				? vms_public_calendar_normalize_view((string) $atts['view'])
				: $default_view;
			$requested_view = vms_public_calendar_get_requested_view();
			if ($requested_view !== '') {
				$view = vms_public_calendar_normalize_view($requested_view);
			}

			$user_agent = vms_public_calendar_get_request_user_agent();
			$is_tablet_calendar_request = !$is_legacy && (
				wp_is_mobile()
				|| strpos($user_agent, 'ipad') !== false
			|| strpos($user_agent, 'tablet') !== false
			|| strpos($user_agent, 'kindle') !== false
			|| strpos($user_agent, 'silk/') !== false
			|| strpos($user_agent, 'playbook') !== false
		);
		if ($view === 'auto') {
			$effective_view = $is_tablet_calendar_request ? 'list' : 'month';
		} elseif ($is_tablet_calendar_request && $view === 'month') {
			$effective_view = 'list';
		} else {
			$effective_view = $view;
		}

		$hide_past_default = array_key_exists('calendar_public_hide_past_default', $settings)
			? !empty($settings['calendar_public_hide_past_default'])
			: true;
			$include_past = !$hide_past_default;
			if ($month['ym'] <= $current_ym) {
				$include_past = true;
			}
			$requested_show_past = vms_public_calendar_get_requested_show_past();
			if ($requested_show_past !== null) {
				$include_past = ($requested_show_past === 1);
			}

		$calendar_feed_args = array(
			'venue_ids' => $venue_ids,
			'context' => 'public',
			'include_past' => $include_past,
			'include_open_close_shading' => $show_open_closed,
			'include_statuses' => array('published', 'cancelled'),
		);
		$compact_context = array();
		if ($effective_view === 'compact') {
			$compact_context = vms_public_calendar_build_compact_context($month['ym'], $calendar_feed_args, 3);
			$rendered_months = isset($compact_context['months']) && is_array($compact_context['months'])
				? $compact_context['months']
				: array();
		} else {
			$rendered_months = vms_public_calendar_month_sequence($month['ym'], 1);
		}

		$events = array();
		if (!empty($rendered_months) && function_exists('vms_get_calendar_events')) {
			$query_start = isset($rendered_months[0]['start']) ? (string) $rendered_months[0]['start'] : $month['start'];
			$last_rendered_month = $rendered_months[count($rendered_months) - 1] ?? $month;
			$query_end = isset($last_rendered_month['end']) ? gmdate('Y-m-d', strtotime('-1 day', strtotime((string) $last_rendered_month['end']))) : $month_end_inclusive;
			$events = (array) vms_get_calendar_events(array_merge($calendar_feed_args, array(
				'start_date' => $query_start,
				'end_date' => $query_end,
			)));
		}

		$self = get_permalink();
		$base_args = array();
		if ($selected_venue !== 'all') {
			$base_args['venue_id'] = $selected_venue;
		} elseif (!$is_legacy) {
			$base_args['venue'] = 'all';
		}
		$base_args['view'] = $view;
		$base = add_query_arg($base_args, $self);
		$nav_markup = ($effective_view === 'compact')
			? vms_public_calendar_render_compact_nav($compact_context, $base)
			: vms_public_calendar_render_nav($nav, $base, $month);

		$calendar_script_ver = function_exists('vms_asset_version') ? vms_asset_version() : (defined('VMS_VERSION') ? (string) VMS_VERSION : null);
		if (defined('VMS_PLUGIN_PATH')) {
			$calendar_script_file = VMS_PLUGIN_PATH . 'assets/js/vms-public-calendar.js';
			if (file_exists($calendar_script_file)) {
				$calendar_script_ver = (string) @filemtime($calendar_script_file);
			}
		}
		wp_enqueue_script(
			'vms-public-calendar',
			VMS_PLUGIN_URL . 'assets/js/vms-public-calendar.js',
			array(),
			$calendar_script_ver,
			true
		);

		ob_start();
		?>
		<div class="vms-public-cal vms-public-cal--view-<?php echo esc_attr($effective_view); ?>" data-vms-effective-view="<?php echo esc_attr($effective_view); ?>">
			<form method="get" class="vms-public-cal-filters">
				<?php if ($show_venue_selector): ?>
					<label class="vms-public-cal-label"><?php echo esc_html__('Venue', 'backstage-venue-manager'); ?></label>
					<select name="venue_id" class="vms-public-cal-venue">
						<?php if (!$is_legacy): ?>
							<option value="all" <?php selected($selected_venue, 'all'); ?>><?php echo esc_html__('All Venues', 'backstage-venue-manager'); ?></option>
						<?php endif; ?>
						<?php foreach ($venues as $v): ?>
							<option value="<?php echo esc_attr((string) $v->ID); ?>" <?php selected($selected_venue, (string) $v->ID); ?>>
								<?php echo esc_html((string) $v->post_title); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php elseif ($selected_venue !== 'all'): ?>
					<input type="hidden" name="venue_id" value="<?php echo esc_attr($selected_venue); ?>" />
				<?php endif; ?>

				<label class="vms-public-cal-label vms-public-cal-label-month"><?php echo esc_html__('Month', 'backstage-venue-manager'); ?></label>
				<input type="month" name="ym" class="vms-public-cal-month" value="<?php echo esc_attr($month['ym']); ?>" />

				<?php if (!$is_legacy): ?>
					<label class="vms-public-cal-label vms-public-cal-label-view"><?php echo esc_html__('View', 'backstage-venue-manager'); ?></label>
					<select name="view" class="vms-public-cal-view">
						<option value="auto" <?php selected($view, 'auto'); ?>><?php echo esc_html__('Auto', 'backstage-venue-manager'); ?></option>
						<option value="month" <?php selected($view, 'month'); ?>><?php echo esc_html__('Month', 'backstage-venue-manager'); ?></option>
						<option value="compact" <?php selected($view, 'compact'); ?>><?php echo esc_html__('Compact', 'backstage-venue-manager'); ?></option>
						<option value="list" <?php selected($view, 'list'); ?>><?php echo esc_html__('List', 'backstage-venue-manager'); ?></option>
					</select>
				<?php else: ?>
					<input type="hidden" name="view" value="month" />
				<?php endif; ?>

				<button type="submit" class="vms-public-cal-submit"><?php echo esc_html__('Go', 'backstage-venue-manager'); ?></button>
			</form>

			<?php if ($effective_view === 'compact'): ?>
				<p class="vms-public-cal-compact-note"><?php echo esc_html__('Compact view shows up to three event-bearing months in weekend-focused chunks and skips empty months.', 'backstage-venue-manager'); ?></p>
			<?php endif; ?>

				<?php echo wp_kses($nav_markup, vms_public_calendar_allowed_html()); ?>

				<?php
					if ($effective_view === 'list') {
						$list_markup = vms_public_calendar_render_list_view($events, $show_vendors, $show_images, $show_open_closed);
						echo wp_kses($list_markup, vms_public_calendar_allowed_html());
					} elseif ($effective_view === 'compact') {
						$state_venue_id = ($selected_venue !== 'all') ? absint($selected_venue) : 0;
						echo wp_kses(vms_public_calendar_render_compact_view($rendered_months, $events, $state_venue_id, $show_open_closed), vms_public_calendar_allowed_html());
					} else {
						$list_markup = vms_public_calendar_render_list_view($events, $show_vendors, $show_images, $show_open_closed);
					$primary_month_events = vms_public_calendar_filter_events_for_month($events, $month['ym']);
					$days = vms_public_calendar_build_month_days($primary_month_events);
					$state_venue_id = ($selected_venue !== 'all') ? absint($selected_venue) : 0;
					$day_states = vms_public_calendar_build_day_states($month, $state_venue_id, $show_open_closed);
						echo wp_kses(vms_public_calendar_render_month_grid($month, $days, $day_states), vms_public_calendar_allowed_html());
						echo '<div class="vms-public-cal-mobile-list-fallback" aria-label="' . esc_attr__('Mobile and tablet list view', 'backstage-venue-manager') . '">' . wp_kses($list_markup, vms_public_calendar_allowed_html()) . '</div>';
					}
					echo wp_kses($nav_markup, vms_public_calendar_allowed_html());
				?>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}

add_shortcode('vms_venue_calendar', 'vms_public_calendar_shortcode_handler');
add_shortcode('vms_public_calendar', 'vms_public_calendar_shortcode_handler');
