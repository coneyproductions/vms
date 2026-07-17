<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

$GLOBALS['vms_test_actions'] = array();
$GLOBALS['vms_test_submenu_pages'] = array();
$GLOBALS['vms_test_registered_settings'] = array();
$GLOBALS['vms_test_registered_sections'] = array();
$GLOBALS['vms_test_registered_fields'] = array();
$GLOBALS['vms_test_current_user_caps'] = array(
	'manage_options' => true,
	'read' => true,
);
$GLOBALS['vms_test_is_user_logged_in'] = true;
$GLOBALS['vms_test_current_user_id'] = 23;
$GLOBALS['vms_test_redirects'] = array();
$GLOBALS['vms_test_referer_actions'] = array();
$GLOBALS['vms_test_referer_fail'] = false;
$GLOBALS['vms_test_provider_reads'] = array(
	'current_user_can' => 0,
	'is_user_logged_in' => 0,
	'get_current_user_id' => 0,
);
$GLOBALS['vms_test_storage_calls'] = array(
	'reset_user_state' => array(),
	'get_user_state' => 0,
	'get_site_settings' => 0,
	'save_site_settings' => array(),
);
$GLOBALS['vms_test_service_calls'] = array(
	'get_registry' => 0,
);
$GLOBALS['vms_test_do_settings_sections_calls'] = array();
$GLOBALS['vms_test_settings_fields_calls'] = array();

if (!function_exists('add_action')) {
	function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
	{
		unset($accepted_args);
		if (!isset($GLOBALS['vms_test_actions'][$hook])) {
			$GLOBALS['vms_test_actions'][$hook] = array();
		}
		if (!isset($GLOBALS['vms_test_actions'][$hook][$priority])) {
			$GLOBALS['vms_test_actions'][$hook][$priority] = array();
		}
		$GLOBALS['vms_test_actions'][$hook][$priority][] = $callback;
		return true;
	}
}

if (!function_exists('add_filter')) {
	function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
	{
		unset($hook, $callback, $priority, $accepted_args);
		return true;
	}
}

if (!function_exists('add_submenu_page')) {
	function add_submenu_page(string $parent_slug, string $page_title, string $menu_title, string $capability, string $menu_slug, $callback)
	{
		$GLOBALS['vms_test_submenu_pages'][] = array(
			'parent_slug' => $parent_slug,
			'page_title' => $page_title,
			'menu_title' => $menu_title,
			'capability' => $capability,
			'menu_slug' => $menu_slug,
			'callback' => $callback,
		);
		return 'tours-page-hook';
	}
}

if (!function_exists('register_setting')) {
	function register_setting(string $option_group, string $option_name, $args = array()): bool
	{
		$GLOBALS['vms_test_registered_settings'][] = array(
			'option_group' => $option_group,
			'option_name' => $option_name,
			'args' => $args,
		);
		return true;
	}
}

if (!function_exists('add_settings_section')) {
	function add_settings_section(string $id, string $title, $callback, string $page): bool
	{
		$GLOBALS['vms_test_registered_sections'][] = array(
			'id' => $id,
			'title' => $title,
			'callback' => $callback,
			'page' => $page,
		);
		return true;
	}
}

if (!function_exists('add_settings_field')) {
	function add_settings_field(string $id, string $title, $callback, string $page, string $section = 'default', array $args = array()): bool
	{
		$GLOBALS['vms_test_registered_fields'][] = array(
			'id' => $id,
			'title' => $title,
			'callback' => $callback,
			'page' => $page,
			'section' => $section,
			'args' => $args,
		);
		return true;
	}
}

if (!function_exists('__')) {
	function __(string $text, string $domain = ''): string
	{
		unset($domain);
		return $text;
	}
}

if (!function_exists('esc_html__')) {
	function esc_html__(string $text, string $domain = ''): string
	{
		return esc_html(__($text, $domain));
	}
}

if (!function_exists('esc_html')) {
	function esc_html($text): string
	{
		return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('esc_attr')) {
	function esc_attr($text): string
	{
		return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('esc_url')) {
	function esc_url($url): string
	{
		return htmlspecialchars((string) $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('wp_unslash')) {
	function wp_unslash($value)
	{
		if (is_array($value)) {
			return array_map('wp_unslash', $value);
		}

		return is_string($value) ? stripslashes($value) : $value;
	}
}

if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($value): string
	{
		if (!is_scalar($value)) {
			return '';
		}

		$sanitized = preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $value));
		return is_string($sanitized) ? trim($sanitized) : '';
	}
}

if (!function_exists('sanitize_key')) {
	function sanitize_key($value): string
	{
		if (!is_scalar($value)) {
			return '';
		}

		$sanitized = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value));
		return is_string($sanitized) ? $sanitized : '';
	}
}

if (!function_exists('selected')) {
	function selected($selected, $current = true, bool $display = true): string
	{
		unset($display);
		return (string) $selected === (string) $current ? ' selected="selected"' : '';
	}
}

if (!function_exists('checked')) {
	function checked($checked, $current = true, bool $display = true): string
	{
		unset($display);
		return (string) $checked === (string) $current ? ' checked="checked"' : '';
	}
}

if (!function_exists('admin_url')) {
	function admin_url(string $path = ''): string
	{
		return 'https://example.test/wp-admin/' . ltrim($path, '/');
	}
}

if (!function_exists('add_query_arg')) {
	function add_query_arg($args, string $url = ''): string
	{
		$base = $url !== '' ? $url : admin_url('admin.php');
		$parts = parse_url($base);
		$query = array();
		if (!empty($parts['query'])) {
			parse_str($parts['query'], $query);
		}
		foreach ((array) $args as $key => $value) {
			$query[(string) $key] = (string) $value;
		}

		$rebuilt = '';
		if (!empty($parts['scheme'])) {
			$rebuilt .= $parts['scheme'] . '://';
		}
		if (!empty($parts['host'])) {
			$rebuilt .= $parts['host'];
		}
		if (!empty($parts['path'])) {
			$rebuilt .= $parts['path'];
		}
		if ($query !== array()) {
			$rebuilt .= '?' . http_build_query($query);
		}

		return $rebuilt;
	}
}

if (!function_exists('current_user_can')) {
	function current_user_can(string $capability): bool
	{
		$GLOBALS['vms_test_provider_reads']['current_user_can']++;
		return !empty($GLOBALS['vms_test_current_user_caps'][$capability]);
	}
}

if (!function_exists('is_user_logged_in')) {
	function is_user_logged_in(): bool
	{
		$GLOBALS['vms_test_provider_reads']['is_user_logged_in']++;
		return !empty($GLOBALS['vms_test_is_user_logged_in']);
	}
}

if (!function_exists('get_current_user_id')) {
	function get_current_user_id(): int
	{
		$GLOBALS['vms_test_provider_reads']['get_current_user_id']++;
		return (int) ($GLOBALS['vms_test_current_user_id'] ?? 0);
	}
}

if (!function_exists('check_admin_referer')) {
	function check_admin_referer(string $action): bool
	{
		$GLOBALS['vms_test_referer_actions'][] = $action;
		if (!empty($GLOBALS['vms_test_referer_fail'])) {
			throw new RuntimeException('Nonce check failed.');
		}
		return true;
	}
}

if (!function_exists('wp_die')) {
	function wp_die($message = ''): void
	{
		throw new RuntimeException((string) $message);
	}
}

if (!function_exists('wp_safe_redirect')) {
	function wp_safe_redirect(string $location): bool
	{
		$GLOBALS['vms_test_redirects'][] = $location;
		return true;
	}
}

if (!function_exists('wp_nonce_field')) {
	function wp_nonce_field(string $action = '', string $name = '_wpnonce', bool $referer = true, bool $display = true): string
	{
		$field = '<input type="hidden" name="' . esc_attr($name) . '" value="nonce:' . esc_attr($action) . '" />';
		if ($referer) {
			$field .= '<input type="hidden" name="_wp_http_referer" value="/wp-admin/admin.php?page=vms-guided-tours" />';
		}
		if ($display) {
			echo $field;
		}
		return $field;
	}
}

if (!function_exists('settings_fields')) {
	function settings_fields(string $option_group): void
	{
		$GLOBALS['vms_test_settings_fields_calls'][] = $option_group;
		echo '<input type="hidden" name="option_page" value="' . esc_attr($option_group) . '" />';
	}
}

if (!function_exists('do_settings_sections')) {
	function do_settings_sections(string $page): void
	{
		$GLOBALS['vms_test_do_settings_sections_calls'][] = $page;
		echo '<div class="vms-test-settings-sections"></div>';
	}
}

if (!function_exists('submit_button')) {
	function submit_button($text = null, string $type = 'primary', string $name = 'submit', bool $wrap = true, $other_attributes = null): void
	{
		unset($other_attributes);
		$text = $text === null ? 'Save Changes' : (string) $text;
		$button = '<button type="submit" class="button button-' . esc_attr($type) . '" name="' . esc_attr($name) . '">' . esc_html($text) . '</button>';
		echo $wrap ? '<p class="submit">' . $button . '</p>' : $button;
	}
}

if (!function_exists('wp_kses')) {
	function wp_kses(string $text, array $allowed_html = array()): string
	{
		unset($allowed_html);
		return $text;
	}
}

if (!function_exists('wp_kses_post')) {
	function wp_kses_post(string $text): string
	{
		return $text;
	}
}

if (!function_exists('sanitize_html_class')) {
	function sanitize_html_class($class): string
	{
		$sanitized = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $class);
		return is_string($sanitized) ? trim($sanitized, '-') : '';
	}
}

class VMS_Tours_Service
{
	/** @return array<int,array<string,mixed>> */
	public function get_registry(): array
	{
		$GLOBALS['vms_test_service_calls']['get_registry']++;
		return array();
	}
}

class VMS_Tours_Storage
{
	public const OPTION_SETTINGS = 'vms_tours_settings';

	/** @param array<string,mixed> $input */
	public function save_site_settings(array $input): array
	{
		$GLOBALS['vms_test_storage_calls']['save_site_settings'][] = $input;
		return $input;
	}

	/** @return array<string,mixed> */
	public function get_site_settings(): array
	{
		$GLOBALS['vms_test_storage_calls']['get_site_settings']++;
		return array();
	}

	/** @return array<string,mixed> */
	public function get_user_state(int $user_id): array
	{
		unset($user_id);
		$GLOBALS['vms_test_storage_calls']['get_user_state']++;
		return array();
	}

	public function reset_user_state(int $user_id): void
	{
		$GLOBALS['vms_test_storage_calls']['reset_user_state'][] = $user_id;
	}
}

require_once dirname(__DIR__) . '/includes/admin-ui/shell.php';
require_once dirname(__DIR__) . '/includes/tours/class-vms-tours-admin.php';

$pluginRoot = dirname(__DIR__);
$adminSource = file_get_contents($pluginRoot . '/includes/tours/class-vms-tours-admin.php');
$storageSource = file_get_contents($pluginRoot . '/includes/tours/class-vms-tours-storage.php');
$serviceSource = file_get_contents($pluginRoot . '/includes/tours/class-vms-tours-service.php');
$shellSource = file_get_contents($pluginRoot . '/includes/admin-ui/shell.php');

$assert = static function ($condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$assertSame = static function ($expected, $actual, string $message) use ($assert): void {
	$assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
};

$reset_state = static function (): void {
	$GLOBALS['vms_test_current_user_caps'] = array(
		'manage_options' => true,
		'read' => true,
	);
	$GLOBALS['vms_test_is_user_logged_in'] = true;
	$GLOBALS['vms_test_current_user_id'] = 23;
	$GLOBALS['vms_test_redirects'] = array();
	$GLOBALS['vms_test_referer_actions'] = array();
	$GLOBALS['vms_test_referer_fail'] = false;
	$GLOBALS['vms_test_provider_reads'] = array(
		'current_user_can' => 0,
		'is_user_logged_in' => 0,
		'get_current_user_id' => 0,
	);
	$GLOBALS['vms_test_storage_calls'] = array(
		'reset_user_state' => array(),
		'get_user_state' => 0,
		'get_site_settings' => 0,
		'save_site_settings' => array(),
	);
	$GLOBALS['vms_test_service_calls'] = array(
		'get_registry' => 0,
	);
	$GLOBALS['vms_test_do_settings_sections_calls'] = array();
	$GLOBALS['vms_test_settings_fields_calls'] = array();
	$_GET = array();
	$_POST = array();
};

$admin = new VMS_Tours_Admin(new VMS_Tours_Service(), new VMS_Tours_Storage());
$get_reset_notice_context = Closure::bind(function () {
	return $this->get_reset_notice_context();
}, $admin, VMS_Tours_Admin::class);
$render_reset_notice = Closure::bind(function (array $context): string {
	ob_start();
	$this->render_reset_notice($context);
	return (string) ob_get_clean();
}, $admin, VMS_Tours_Admin::class);
$run_reset_success_subprocess = static function () use ($pluginRoot): array {
	$tempFile = tempnam(sys_get_temp_dir(), 'vms-guided-tours-reset-');
	if (!is_string($tempFile) || $tempFile === '') {
		throw new RuntimeException('Unable to allocate a temporary subprocess file for the Guided Tours reset success path.');
	}

	$script = <<<'PHP'
<?php
declare(strict_types=1);

define('ABSPATH', '/');

$GLOBALS['vms_test_redirects'] = array();
$GLOBALS['vms_test_referer_actions'] = array();
$GLOBALS['vms_test_storage_calls'] = array(
	'reset_user_state' => array(),
);

function __(string $text, string $domain = ''): string
{
	unset($domain);
	return $text;
}

function esc_html($text): string
{
	return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esc_html__(string $text, string $domain = ''): string
{
	return esc_html(__($text, $domain));
}

function is_user_logged_in(): bool
{
	return true;
}

function current_user_can(string $capability): bool
{
	return $capability === 'read';
}

function check_admin_referer(string $action): bool
{
	$GLOBALS['vms_test_referer_actions'][] = $action;
	return true;
}

function get_current_user_id(): int
{
	return 23;
}

function admin_url(string $path = ''): string
{
	return 'https://example.test/wp-admin/' . ltrim($path, '/');
}

function add_query_arg($args, string $url = ''): string
{
	$base = $url !== '' ? $url : admin_url('admin.php');
	$parts = parse_url($base);
	$query = array();
	if (!empty($parts['query'])) {
		parse_str($parts['query'], $query);
	}
	foreach ((array) $args as $key => $value) {
		$query[(string) $key] = (string) $value;
	}

	$rebuilt = '';
	if (!empty($parts['scheme'])) {
		$rebuilt .= $parts['scheme'] . '://';
	}
	if (!empty($parts['host'])) {
		$rebuilt .= $parts['host'];
	}
	if (!empty($parts['path'])) {
		$rebuilt .= $parts['path'];
	}
	if ($query !== array()) {
		$rebuilt .= '?' . http_build_query($query);
	}

	return $rebuilt;
}

function wp_safe_redirect(string $location): bool
{
	$GLOBALS['vms_test_redirects'][] = $location;
	return true;
}

function wp_die($message = ''): void
{
	throw new RuntimeException((string) $message);
}

class VMS_Tours_Service
{
}

class VMS_Tours_Storage
{
	public function reset_user_state(int $user_id): void
	{
		$GLOBALS['vms_test_storage_calls']['reset_user_state'][] = $user_id;
	}
}

register_shutdown_function(static function (): void {
	$result = array(
		'referer_actions' => $GLOBALS['vms_test_referer_actions'],
		'reset_user_state' => $GLOBALS['vms_test_storage_calls']['reset_user_state'],
		'redirects' => $GLOBALS['vms_test_redirects'],
	);

	fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_SLASHES) . "\n");
});

require __CLASS_FILE__;

$admin = new VMS_Tours_Admin(new VMS_Tours_Service(), new VMS_Tours_Storage());
$admin->handle_reset_my_state();
PHP;

	$script = str_replace('__CLASS_FILE__', var_export($pluginRoot . '/includes/tours/class-vms-tours-admin.php', true), $script);
	$bytesWritten = file_put_contents($tempFile, $script);
	if (!is_int($bytesWritten) || $bytesWritten <= 0) {
		@unlink($tempFile);
		throw new RuntimeException('Unable to write the Guided Tours reset success subprocess script.');
	}

	$output = array();
	$exitCode = 0;
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tempFile) . ' 2>&1', $output, $exitCode);
	@unlink($tempFile);

	if ($exitCode !== 0) {
		throw new RuntimeException("Guided Tours reset success subprocess failed.\n" . implode("\n", $output));
	}

	$encodedResult = end($output);
	if (!is_string($encodedResult) || $encodedResult === '') {
		throw new RuntimeException('Guided Tours reset success subprocess did not emit a JSON result.');
	}

	$result = json_decode($encodedResult, true);
	if (!is_array($result)) {
		throw new RuntimeException("Guided Tours reset success subprocess emitted invalid JSON.\n" . implode("\n", $output));
	}

	return $result;
};

$assert(is_string($adminSource) && $adminSource !== '', 'Guided Tours admin source should be readable.');
$assert(is_string($storageSource) && $storageSource !== '', 'Guided Tours storage source should be readable.');
$assert(is_string($serviceSource) && $serviceSource !== '', 'Guided Tours service source should be readable.');
$assert(is_string($shellSource) && $shellSource !== '', 'Shell source should be readable.');
$assert(strpos($adminSource, "add_action('admin_post_vms_tours_reset_my_state', array(\$this, 'handle_reset_my_state'));") !== false, 'Guided Tours should preserve the reset admin-post registration.');
$assert(strpos($adminSource, "check_admin_referer('vms_tours_reset_my_state');") !== false, 'Guided Tours reset handler should preserve the nonce action.');
$assert(strpos($adminSource, "wp_nonce_field('vms_tours_reset_my_state');") !== false, 'Guided Tours reset form should preserve the nonce field action.');
$assert(strpos($adminSource, "'vms_tours_reset_my_state' => '1'") !== false, 'Guided Tours reset redirect should preserve the exact success query flag and value.');
$assert(strpos($adminSource, "if (\$this->query_arg('vms_tours_reset_my_state') !== '')") === false, 'Guided Tours page should no longer inline the reset notice condition directly in render_page_content().');
$assert(strpos($adminSource, 'private function get_reset_notice_context(): array') !== false, 'Guided Tours page should define a dedicated reset-notice context builder.');
$assert(strpos($adminSource, 'private function render_reset_notice(array $context): void') !== false, 'Guided Tours page should define a dedicated reset-notice renderer.');
$assert(strpos($adminSource, '$this->render_reset_notice($this->get_reset_notice_context());') !== false, 'Guided Tours page should route the notice through the dedicated builder and renderer.');
$assert(strpos($adminSource, '<div class="notice notice-success is-dismissible" data-vms-tour="guided-tours.reset-notice">') !== false, 'Guided Tours reset notice should preserve the exact nested markup anchor.');
$assert(strpos($storageSource, '$prefs[\'dismissed_tours\'] = array();') !== false, 'Guided Tours reset storage should preserve the dismissed_tours reset.');
$assert(strpos($storageSource, 'update_user_meta($user_id, self::USER_META_PREFS, $this->sanitize_user_prefs($prefs, $settings));') !== false, 'Guided Tours reset storage should preserve the user prefs write.');
$assert(strpos($storageSource, 'delete_user_meta($user_id, self::USER_META_STATE);') !== false, 'Guided Tours reset storage should preserve the user state deletion.');
$assert(strpos($serviceSource, '[data-vms-tour="guided-tours.reset-progress"]') !== false, 'Guided Tours service should preserve the reset-progress selector.');
$assert(strpos($serviceSource, '[data-vms-tour="guided-tours.reset-notice"]') === false, 'Guided Tours service should not introduce a new step selector for the reset notice.');
$assert(strpos($adminSource, "'notices_callback' =>") === false, 'Guided Tours should remain without an Administrator-shell notice callback.');
$assertSame(
	array(
		'div' => array('class' => true),
		'p' => array(),
	),
	vms_admin_ui_explicit_notice_allowed_html(),
	'Administrator shell simple notice contract should remain unchanged.'
);
$assertSame(
	array(
		'div' => array('class' => true),
		'p' => array(),
		'strong' => array(),
	),
	vms_admin_ui_rich_explicit_notice_allowed_html(),
	'Administrator shell rich notice contract should remain unchanged.'
);

$admin->init();
$assert(isset($GLOBALS['vms_test_actions']['admin_menu'][40][0]), 'Guided Tours should register the admin_menu hook at priority 40.');
$assertSame(array($admin, 'register_menu'), $GLOBALS['vms_test_actions']['admin_menu'][40][0], 'Guided Tours should preserve the page registration callback.');
$assert(isset($GLOBALS['vms_test_actions']['admin_init'][10][0]), 'Guided Tours should register the admin_init settings callback.');
$assertSame(array($admin, 'register_settings'), $GLOBALS['vms_test_actions']['admin_init'][10][0], 'Guided Tours should preserve the settings registration callback.');
$assert(isset($GLOBALS['vms_test_actions']['admin_post_vms_tours_reset_my_state'][10][0]), 'Guided Tours should preserve the reset admin-post hook.');
$assertSame(array($admin, 'handle_reset_my_state'), $GLOBALS['vms_test_actions']['admin_post_vms_tours_reset_my_state'][10][0], 'Guided Tours should preserve the reset handler callback.');

$admin->register_menu();
$assertSame(
	array(
		'parent_slug' => 'vms-dashboard',
		'page_title' => 'Guided Tours',
		'menu_title' => 'Guided Tours',
		'capability' => 'manage_options',
		'menu_slug' => 'vms-guided-tours',
		'callback' => array($admin, 'render_page'),
	),
	$GLOBALS['vms_test_submenu_pages'][0],
	'Guided Tours menu registration should preserve the parent slug, titles, capability, slug, and render callback.'
);

$admin->register_settings();
$settingsRegistrationFound = false;
foreach ($GLOBALS['vms_test_registered_settings'] as $registration) {
	if (($registration['option_group'] ?? '') === 'vms_tours_settings_group' && ($registration['option_name'] ?? '') === VMS_Tours_Storage::OPTION_SETTINGS) {
		$settingsRegistrationFound = true;
		$assertSame(array($admin, 'sanitize_settings_option'), $registration['args'], 'Guided Tours settings registration should preserve the sanitize callback.');
	}
}
$assert($settingsRegistrationFound, 'Guided Tours settings registration should preserve the option group and option name.');

$reset_state();
$GLOBALS['vms_test_current_user_caps']['manage_options'] = false;
try {
	$admin->render_page();
	throw new RuntimeException('Expected manage_options failure was not thrown.');
} catch (RuntimeException $e) {
	$assertSame('Insufficient permissions.', $e->getMessage(), 'Guided Tours page rendering should preserve the manage_options failure message.');
}

$reset_state();
$GLOBALS['vms_test_is_user_logged_in'] = false;
$GLOBALS['vms_test_current_user_caps']['read'] = false;
try {
	$admin->handle_reset_my_state();
	throw new RuntimeException('Expected reset permission failure was not thrown.');
} catch (RuntimeException $e) {
	$assertSame('Insufficient permissions.', $e->getMessage(), 'Guided Tours reset handler should preserve the read-capability failure message.');
}
$assertSame(array(), $GLOBALS['vms_test_referer_actions'], 'Guided Tours reset handler should fail before nonce checks when the user cannot reset progress.');
$assertSame(array(), $GLOBALS['vms_test_storage_calls']['reset_user_state'], 'Guided Tours reset handler should not mutate state when permissions fail.');
$assertSame(array(), $GLOBALS['vms_test_redirects'], 'Guided Tours reset handler should not redirect when permissions fail.');

$reset_state();
$GLOBALS['vms_test_referer_fail'] = true;
try {
	$admin->handle_reset_my_state();
	throw new RuntimeException('Expected reset nonce failure was not thrown.');
} catch (RuntimeException $e) {
	$assertSame('Nonce check failed.', $e->getMessage(), 'Guided Tours reset handler should preserve nonce failure ordering.');
}
$assertSame(array('vms_tours_reset_my_state'), $GLOBALS['vms_test_referer_actions'], 'Guided Tours reset handler should preserve the nonce action.');
$assertSame(array(), $GLOBALS['vms_test_storage_calls']['reset_user_state'], 'Guided Tours reset handler should not mutate state when nonce verification fails.');
$assertSame(array(), $GLOBALS['vms_test_redirects'], 'Guided Tours reset handler should not redirect when nonce verification fails.');

$reset_state();
$successHandlerResult = $run_reset_success_subprocess();
$assertSame(array('vms_tours_reset_my_state'), $successHandlerResult['referer_actions'] ?? null, 'Guided Tours reset handler should verify the existing nonce action.');
$assertSame(array(23), $successHandlerResult['reset_user_state'] ?? null, 'Guided Tours reset handler should preserve the exact user-state reset call.');
$assertSame(
	array('https://example.test/wp-admin/admin.php?page=vms-guided-tours&vms_tours_reset_my_state=1'),
	$successHandlerResult['redirects'] ?? null,
	'Guided Tours reset handler should preserve the redirect destination and success flag.'
);

$reset_state();
$_GET = array();
$hidden_context = $get_reset_notice_context();
$assertSame(array('show' => false, 'state' => 'hidden'), $hidden_context, 'Guided Tours reset-notice context builder should stay hidden when the success flag is absent.');

$reset_state();
$_GET = array('vms_tours_reset_my_state' => '1');
$success_context = $get_reset_notice_context();
$assertSame(array('show' => true, 'state' => 'reset_success'), $success_context, 'Guided Tours reset-notice context builder should show the notice for the accepted success value.');

$reset_state();
$_GET = array('vms_tours_reset_my_state' => 'done');
$alternate_context = $get_reset_notice_context();
$assertSame(array('show' => true, 'state' => 'reset_success'), $alternate_context, 'Guided Tours reset-notice context builder should preserve the current non-empty alternate-value behavior.');

$reset_state();
$_GET = array('vms_tours_reset_my_state' => '');
$empty_context = $get_reset_notice_context();
$assertSame(array('show' => false, 'state' => 'hidden'), $empty_context, 'Guided Tours reset-notice context builder should stay hidden when the success flag is empty.');

$reset_state();
$rendered_success = $render_reset_notice(array('show' => true, 'state' => 'reset_success'));
$assertSame(
	'<div class="notice notice-success is-dismissible" data-vms-tour="guided-tours.reset-notice"><p>Your tour progress has been reset.</p></div>',
	$rendered_success,
	'Guided Tours reset-notice renderer should preserve the exact finite markup contract.'
);
$assertSame(
	array(
		'current_user_can' => 0,
		'is_user_logged_in' => 0,
		'get_current_user_id' => 0,
	),
	$GLOBALS['vms_test_provider_reads'],
	'Guided Tours reset-notice renderer should perform no external reads.'
);
$assertSame(array(), $GLOBALS['vms_test_storage_calls']['reset_user_state'], 'Guided Tours reset-notice renderer should never execute the reset action.');
$assert(strpos($rendered_success, '<script') === false && strpos($rendered_success, '<style') === false && strpos($rendered_success, 'onclick=') === false, 'Guided Tours reset-notice renderer should not emit executable markup.');

$rendered_hidden = $render_reset_notice(array('show' => false, 'state' => 'hidden'));
$assertSame('', $rendered_hidden, 'Guided Tours reset-notice renderer should stay silent for the hidden state.');

$reset_state();
$_GET = array('vms_tours_reset_my_state' => '1');
ob_start();
$admin->render_page_content();
$page_content = (string) ob_get_clean();
$assert(strpos($page_content, '<div class="vms-tours-admin-page" data-vms-tour="guided-tours.settings">') === 0, 'Guided Tours reset notice should remain nested inside the page wrapper.');
$assert(strpos($page_content, '<div class="notice notice-success is-dismissible" data-vms-tour="guided-tours.reset-notice">') !== false, 'Guided Tours reset notice should preserve the exact data-vms-tour hook and classes.');
$assert(strpos($page_content, '<div class="notice notice-success is-dismissible" data-vms-tour="guided-tours.reset-notice">') < strpos($page_content, '<form method="post" action="options.php" data-vms-tour="guided-tours.global-settings">'), 'Guided Tours reset notice should remain before the global settings form.');
$assert(strpos($page_content, '<form method="post" action="https://example.test/wp-admin/admin-post.php" class="vms-tours-admin-reset-form" data-vms-tour="guided-tours.reset-progress">') !== false, 'Guided Tours reset form should remain unchanged.');
$assert(strpos($page_content, 'name="_wpnonce" value="nonce:vms_tours_reset_my_state"') !== false, 'Guided Tours reset form should preserve the nonce field.');

$captured_notices_html = '';
$remaining_content_html = vms_admin_ui_extract_notice_markup($page_content, $captured_notices_html);
$assertSame('', $captured_notices_html, 'Guided Tours reset notice should remain page-local and should not be routed into the shell notice buffer.');
$assert(strpos($remaining_content_html, 'guided-tours.reset-notice') !== false, 'Guided Tours reset notice should remain in the page-local content after shell notice extraction.');

fwrite(STDOUT, "guided tours reset notice output remediation: PASS\n");
