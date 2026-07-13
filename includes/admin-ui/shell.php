<?php

defined('ABSPATH') || exit;

if (!function_exists('vms_admin_ui_dom_outer_html')) {
	function vms_admin_ui_dom_outer_html(DOMNode $node): string
	{
		$doc = $node->ownerDocument;
		if (!($doc instanceof DOMDocument)) {
			return '';
		}
		return (string) $doc->saveHTML($node);
	}
}

if (!function_exists('vms_admin_ui_dom_inner_html')) {
	function vms_admin_ui_dom_inner_html(DOMNode $node): string
	{
		$doc = $node->ownerDocument;
		if (!($doc instanceof DOMDocument)) {
			return '';
		}

		$html = '';
		foreach ($node->childNodes as $child) {
			$html .= (string) $doc->saveHTML($child);
		}
		return $html;
	}
}

if (!function_exists('vms_admin_ui_extract_notice_markup')) {
	function vms_admin_ui_extract_notice_markup(string $markup, string &$notice_markup): string
	{
		if (trim($markup) === '' || strpos($markup, 'notice') === false) {
			return $markup;
		}

		$prev_use_errors = libxml_use_internal_errors(true);
		$doc = new DOMDocument('1.0', 'UTF-8');
		$loaded = $doc->loadHTML(
			'<!doctype html><html><body><div id="vms-shell-fragment">' . $markup . '</div></body></html>',
			defined('LIBXML_HTML_NOIMPLIED') ? LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD : 0
		);
		libxml_clear_errors();
		libxml_use_internal_errors($prev_use_errors);

		if (!$loaded) {
			return $markup;
		}

		$wrapper = $doc->getElementById('vms-shell-fragment');
		if (!($wrapper instanceof DOMElement)) {
			return $markup;
		}

		$to_extract = array();
		foreach ($wrapper->childNodes as $child) {
			if (!($child instanceof DOMElement)) {
				continue;
			}
			if (strtolower($child->tagName) !== 'div') {
				continue;
			}
			$class_attr = trim((string) $child->getAttribute('class'));
			if ($class_attr === '') {
				continue;
			}
			$classes = preg_split('/\s+/', $class_attr);
			if (!is_array($classes) || !in_array('notice', $classes, true)) {
				continue;
			}
			$to_extract[] = $child;
		}

		if (empty($to_extract)) {
			return $markup;
		}

		foreach ($to_extract as $node) {
			$notice_markup .= vms_admin_ui_dom_outer_html($node);
			$parent = $node->parentNode;
			if ($parent instanceof DOMNode) {
				$parent->removeChild($node);
			}
		}

		return vms_admin_ui_dom_inner_html($wrapper);
	}
}

if (!function_exists('vms_admin_ui_prepare_notice_markup')) {
	function vms_admin_ui_prepare_notice_markup(string $markup): string
	{
		if (trim($markup) === '' || strpos($markup, 'notice') === false) {
			return $markup;
		}

		$pattern = '/<div\b([^>]*)\bclass=(["\\\'])([^"\\\']*)\2([^>]*)>/i';
		$updated = preg_replace_callback(
			$pattern,
			static function (array $matches): string {
				$classes = preg_split('/\s+/', trim((string) $matches[3]));
				if (!is_array($classes)) {
					return $matches[0];
				}

				if (!in_array('notice', $classes, true)) {
					return $matches[0];
				}

				if (!in_array('below-h2', $classes, true)) {
					$classes[] = 'below-h2';
				}
				if (!in_array('vms-shell-notice', $classes, true)) {
					$classes[] = 'vms-shell-notice';
				}

				$classes = array_values(array_unique(array_filter($classes)));
				return '<div' . $matches[1] . 'class="' . esc_attr(implode(' ', $classes)) . '"' . $matches[4] . '>';
			},
			$markup
		);

		return is_string($updated) ? $updated : $markup;
	}
}

if (!function_exists('vms_admin_ui_explicit_notice_allowed_html')) {
	function vms_admin_ui_explicit_notice_allowed_html(): array
	{
		return array(
			'div' => array(
				'class' => true,
			),
			'p' => array(),
		);
	}
}

if (!function_exists('vms_admin_ui_header_actions_allowed_html')) {
	function vms_admin_ui_header_actions_allowed_html(): array
	{
		return array(
			'a' => array(
				'class' => true,
				'href' => true,
			),
			'button' => array(
				'class' => true,
				'data-vms-tour' => true,
				'data-vms-tour-start' => true,
				'type' => true,
			),
			'div' => array(
				'class' => true,
				'data-vms-tour' => true,
			),
		);
	}
}

if (!function_exists('vms_admin_ui_render_shell')) {
	/**
	 * @param array<string,mixed> $args
	 */
	function vms_admin_ui_render_shell(array $args, callable $content_callback): void
	{
		$title = isset($args['title']) ? (string) $args['title'] : '';
		$subtitle = isset($args['subtitle']) ? (string) $args['subtitle'] : '';
		$actions_html = isset($args['actions_html']) ? (string) $args['actions_html'] : '';
		$shell_id = isset($args['shell_id']) ? sanitize_html_class((string) $args['shell_id']) : '';
		$content_class = isset($args['content_class']) ? sanitize_html_class((string) $args['content_class']) : '';
		$notices_callback = isset($args['notices_callback']) ? $args['notices_callback'] : null;
		$active_cluster = '';
		$captured_notices_html = '';
		$explicit_notices_html = '';

		ob_start();
		call_user_func($content_callback);
		$content_html = (string) ob_get_clean();
		$content_html = vms_admin_ui_extract_notice_markup($content_html, $captured_notices_html);

		if (is_callable($notices_callback)) {
			ob_start();
			call_user_func($notices_callback);
			$explicit_notices_html = (string) ob_get_clean();
		}

		$captured_notices_html = vms_admin_ui_prepare_notice_markup($captured_notices_html);
		$explicit_notices_html = vms_admin_ui_prepare_notice_markup($explicit_notices_html);

		if (function_exists('vms_admin_ui_active_cluster')) {
			$cluster = vms_admin_ui_active_cluster();
			if (is_string($cluster) && $cluster !== '') {
				$active_cluster = sanitize_html_class($cluster);
			}
		}

		echo '<div class="wrap vms-admin-shell"';
		if ($shell_id !== '') {
			echo ' id="' . esc_attr($shell_id) . '"';
		}
		if ($active_cluster !== '') {
			echo ' data-vms-cluster="' . esc_attr($active_cluster) . '"';
		}
		echo '>';

		echo '<section class="vms-admin-shell__header-zone">';
		if (function_exists('vms_admin_ui_render_top_nav')) {
			vms_admin_ui_render_top_nav();
		}
		echo '</section>';

			echo '<header class="vms-admin-shell__header">';
			echo '<div class="vms-admin-shell__title-wrap">';
			echo '<h1 class="vms-admin-shell__title">' . esc_html($title) . '</h1>';
			if ($subtitle !== '') {
				echo '<p class="vms-admin-shell__subtitle">' . esc_html($subtitle) . '</p>';
			}
			echo '</div>';
			echo '<div class="vms-admin-shell__actions">' . wp_kses($actions_html, vms_admin_ui_header_actions_allowed_html()) . '</div>';
			echo '</header>';

		echo '<section class="vms-admin-shell__notices">';
		if ($explicit_notices_html !== '') {
			echo wp_kses($explicit_notices_html, vms_admin_ui_explicit_notice_allowed_html());
		}
		if ($captured_notices_html !== '') {
			echo $captured_notices_html;
		}
		echo '</section>';

		$section_class = 'vms-admin-shell__content';
		if ($content_class !== '') {
			$section_class .= ' ' . $content_class;
		}
		echo '<section class="' . esc_attr($section_class) . '">';
		echo $content_html;
		echo '</section>';

		echo '</div>';
	}
}
