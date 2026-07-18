<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$socialAssetPath = $pluginRoot . '/assets/js/vms-social-admin.js';

$assert = static function (bool $condition, string $message): void {
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
};

$readFile = static function (string $path) use ($assert): string {
	$contents = @file_get_contents($path);
	$assert(is_string($contents) && $contents !== '', 'Expected readable source file: ' . $path);
	return $contents;
};

try {
	$socialAssetSource = $readFile($socialAssetPath);

	foreach (array(
		'function appendFooterForms(html) {',
		'function loadLazyEventPanel(shell) {',
		'function maybeLoadOpenEventPanel() {',
		"var box = document.getElementById('vms_social_promotion');",
		"var shell = box.querySelector('[data-vms-social-lazy]');",
		"if (shell.getAttribute('data-vms-social-loaded') === '1' || shell.getAttribute('data-vms-social-loading') === '1') return;",
		"shell.setAttribute('data-vms-social-loading', '1');",
		"params.set('action', 'vms_social_load_event_panel');",
		"params.set('post_id', String(postId));",
		"params.set('nonce', String(nonce));",
		"method: 'POST',",
		"credentials: 'same-origin',",
		"'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'",
		'body: params.toString()',
		'return response.json();',
		"if (!payload || !payload.success || !payload.data || !payload.data.html) {",
		"throw new Error('lazy-load-failed');",
		'shell.innerHTML = String(payload.data.html);',
		"shell.setAttribute('data-vms-social-loaded', '1');",
		"shell.removeAttribute('data-vms-social-loading');",
		"appendFooterForms(payload.data.footer_forms_html || '');",
		'var wrapper = document.createElement(\'div\');',
		'wrapper.innerHTML = String(html);',
		"var forms = wrapper.querySelectorAll('form[id]');",
		'var existing = document.getElementById(form.id);',
		'existing.parentNode.removeChild(existing);',
		'document.body.appendChild(form);',
		"shell.innerHTML = '<p class=\"description\">Unable to load social sharing tools right now. Reload and try again.</p>';",
		"var toggle = event.target.closest('#vms_social_promotion .handlediv, #vms_social_promotion .hndle');",
		'window.setTimeout(maybeLoadOpenEventPanel, 0);',
		"window.jQuery(document).on('postbox-toggled', function (event, box) {",
		"if (box && box.id === 'vms_social_promotion') {",
		'document.addEventListener(\'DOMContentLoaded\', maybeLoadOpenEventPanel);',
		'maybeLoadOpenEventPanel();',
	) as $requiredMarker) {
		$assert(strpos($socialAssetSource, $requiredMarker) !== false, 'Social Sharing JS should preserve the source marker: ' . $requiredMarker);
	}

	$assert(substr_count($socialAssetSource, 'innerHTML =') === 3, 'Social Sharing JS should preserve exactly three innerHTML assignments: footer wrapper parse, success shell replacement, and generic fallback.');
	$assert(substr_count($socialAssetSource, 'window.setTimeout(maybeLoadOpenEventPanel, 0);') === 1, 'Social Sharing JS should preserve exactly one click-triggered lazy-load deferral.');
	$assert(substr_count($socialAssetSource, "params.set('action', 'vms_social_load_event_panel');") === 1, 'Social Sharing JS should preserve exactly one AJAX action field assignment.');
	$assert(substr_count($socialAssetSource, "params.set('post_id', String(postId));") === 1, 'Social Sharing JS should preserve exactly one AJAX post_id field assignment.');
	$assert(substr_count($socialAssetSource, "params.set('nonce', String(nonce));") === 1, 'Social Sharing JS should preserve exactly one AJAX nonce field assignment.');
	$assert(substr_count($socialAssetSource, "var shell = box.querySelector('[data-vms-social-lazy]');") === 1, 'Social Sharing JS should preserve the current lazy shell selector exactly once.');
	$assert(substr_count($socialAssetSource, 'document.body.appendChild(form);') === 1, 'Social Sharing JS should preserve exactly one detached form append target.');

	foreach (array(
		'payload.data.message',
		'insertAdjacentHTML(',
		'outerHTML =',
		'replaceWith(',
	) as $forbiddenMarker) {
		$assert(strpos($socialAssetSource, $forbiddenMarker) === false, 'Social Sharing JS should not introduce a different error-display or insertion strategy: ' . $forbiddenMarker);
	}

	$assert(
		strpos($socialAssetSource, 'shell.removeAttribute(\'data-vms-social-loading\');') !== false
		&& strpos($socialAssetSource, 'shell.setAttribute(\'data-vms-social-loaded\', \'1\');') !== false,
		'Social Sharing JS should preserve the current loading/loaded attribute lifecycle.'
	);
	$assert(
		strpos($socialAssetSource, "if (!postId || !ajaxUrl || !nonce || typeof window.fetch !== 'function' || typeof window.URLSearchParams !== 'function') {") !== false,
		'Social Sharing JS should preserve the current lazy-load preflight guard for missing shell data and browser primitives.'
	);
	$assert(
		strpos($socialAssetSource, "if (!box || box.classList.contains('closed')) return;") !== false,
		'Social Sharing JS should preserve the current open-postbox guard before lazy loading.'
	);

	fwrite(STDOUT, "social event-panel lazy-load js characterization: PASS\n");
} catch (Throwable $throwable) {
	fwrite(STDERR, 'social event-panel lazy-load js characterization: FAIL - ' . $throwable->getMessage() . "\n");
	exit(1);
}
