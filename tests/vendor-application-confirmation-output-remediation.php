<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('HOUR_IN_SECONDS')) {
	define('HOUR_IN_SECONDS', 3600);
}
if (!defined('MINUTE_IN_SECONDS')) {
	define('MINUTE_IN_SECONDS', 60);
}
if (!defined('DAY_IN_SECONDS')) {
	define('DAY_IN_SECONDS', 86400);
}
if (!defined('ARRAY_A')) {
	define('ARRAY_A', 'ARRAY_A');
}

if (!class_exists('WP_User')) {
	class WP_User
	{
		public int $ID = 123;
		public string $user_email = 'vendor@example.test';
		public string $user_login = 'vendor';
		public string $display_name = 'Vendor User';
	}
}

if (!class_exists('WP_Error')) {
	class WP_Error
	{
		private string $code;
		private string $message;
		private $data = null;

		public function __construct(string $code = '', string $message = '')
		{
			$this->code = $code;
			$this->message = $message;
		}

		public function get_error_code(): string
		{
			return $this->code;
		}

		public function get_error_message(): string
		{
			return $this->message;
		}

		public function get_error_data()
		{
			return $this->data;
		}

		public function add_data($data): void
		{
			$this->data = $data;
		}
	}
}

if (!function_exists('add_action')) {
	function add_action($hook_name, $callback, $priority = 10, $accepted_args = 1): bool
	{
		return true;
	}
}

if (!function_exists('add_filter')) {
	function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1): bool
	{
		return true;
	}
}

if (!function_exists('__')) {
	function __($text, $domain = 'default'): string
	{
		return (string) $text;
	}
}

if (!function_exists('esc_html__')) {
	function esc_html__($text, $domain = 'default'): string
	{
		return esc_html($text);
	}
}

if (!function_exists('sanitize_key')) {
	function sanitize_key($key): string
	{
		return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key)) ?? '';
	}
}

if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($value): string
	{
		return trim(strip_tags((string) $value));
	}
}

if (!function_exists('sanitize_email')) {
	function sanitize_email($value): string
	{
		$value = trim((string) $value);
		return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
	}
}

if (!function_exists('sanitize_user')) {
	function sanitize_user($username, $strict = false): string
	{
		return preg_replace('/[^a-zA-Z0-9_\-.@]/', '', (string) $username) ?? '';
	}
}

if (!function_exists('absint')) {
	function absint($value): int
	{
		return abs((int) $value);
	}
}

if (!function_exists('wp_unslash')) {
	function wp_unslash($value)
	{
		return $value;
	}
}

if (!function_exists('esc_attr')) {
	function esc_attr($text): string
	{
		return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('esc_html')) {
	function esc_html($text): string
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

if (!function_exists('esc_url_raw')) {
	function esc_url_raw($url): string
	{
		return trim((string) $url);
	}
}

if (!function_exists('home_url')) {
	function home_url(string $path = ''): string
	{
		return 'https://example.test' . $path;
	}
}

if (!function_exists('admin_url')) {
	function admin_url(string $path = ''): string
	{
		return 'https://example.test/wp-admin/' . ltrim($path, '/');
	}
}

if (!function_exists('wp_lostpassword_url')) {
	function wp_lostpassword_url(string $redirect = ''): string
	{
		return 'https://example.test/wp-login.php?action=lostpassword&redirect_to=' . rawurlencode($redirect);
	}
}

if (!function_exists('add_query_arg')) {
	function add_query_arg(array $args, string $url): string
	{
		$separator = strpos($url, '?') === false ? '?' : '&';
		return $url . $separator . http_build_query($args);
	}
}

if (!function_exists('apply_filters')) {
	function apply_filters($hook_name, $value)
	{
		return $value;
	}
}

if (!function_exists('is_email')) {
	function is_email($email): bool
	{
		return filter_var((string) $email, FILTER_VALIDATE_EMAIL) !== false;
	}
}

if (!function_exists('is_wp_error')) {
	function is_wp_error($thing): bool
	{
		return $thing instanceof WP_Error;
	}
}

if (!function_exists('wp_nonce_field')) {
	function wp_nonce_field(string $action, string $name): void
	{
		echo '<input type="hidden" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="nonce-' . esc_attr($action) . '">';
		echo '<input type="hidden" name="_wp_http_referer" value="/vendor-application/">';
	}
}

if (!function_exists('bvmgr_vendor_app_get_confirmation_state')) {
	function bvmgr_vendor_app_get_confirmation_state(int $app_id): string
	{
		return $GLOBALS['vms_test_confirmation_state'] ?? 'unconfirmed';
	}
}

if (!function_exists('bvmgr_vendor_app_get_public_lookup_key')) {
	function bvmgr_vendor_app_get_public_lookup_key(int $app_id): string
	{
		return (string) ($GLOBALS['vms_test_public_lookup_key'] ?? 'lookup-key');
	}
}

if (!function_exists('bvmgr_vendor_app_get_application_page_url')) {
	function bvmgr_vendor_app_get_application_page_url(): string
	{
		return 'https://example.test/vendor-application/';
	}
}

if (!function_exists('vms_vendor_portal_page_url')) {
	function vms_vendor_portal_page_url(): string
	{
		return 'https://example.test/vendor-portal/';
	}
}

if (!function_exists('bvmgr_vendor_apply_render_notice')) {
	function bvmgr_vendor_apply_render_notice(string $type, string $headline, string $body = ''): string
	{
		$type = ($type === 'success' || $type === 'warning') ? $type : 'error';
		$html = '<div class="vms-notice vms-notice-' . esc_attr($type) . ' vms-vendor-apply-notice">';
		$html .= '<p><strong>' . esc_html($headline) . '</strong></p>';
		if (trim($body) !== '') {
			$html .= '<p>' . esc_html($body) . '</p>';
		}
		$html .= '</div>';
		return $html;
	}
}

if (!function_exists('bvmgr_vendor_app_statuses')) {
	function bvmgr_vendor_app_statuses(): array
	{
		return array(
			'pending' => 'Pending',
			'holding' => 'Holding',
			'approved' => 'Approved',
		);
	}
}

if (!function_exists('bvmgr_vendor_app_get_status')) {
	function bvmgr_vendor_app_get_status(int $app_id): string
	{
		return $GLOBALS['vms_test_app_status'] ?? 'pending';
	}
}

if (!function_exists('bvmgr_vendor_app_find_recent_application_for_user')) {
	function bvmgr_vendor_app_find_recent_application_for_user(int $user_id): array
	{
		return $GLOBALS['vms_test_recent_application'] ?? array(
			'kind' => 'unconfirmed',
			'app_id' => 123,
			'status' => 'pending',
			'confirmation_state' => 'unconfirmed',
		);
	}
}

if (!function_exists('wp_kses')) {
	function wp_kses($html, $allowed_html): string
	{
		return (string) preg_replace_callback(
			'~<(/?)([a-zA-Z][a-zA-Z0-9]*)([^>]*)>~',
			static function (array $matches) use ($allowed_html): string {
				$is_closing = $matches[1] === '/';
				$tag = strtolower((string) $matches[2]);
				if (!array_key_exists($tag, $allowed_html)) {
					return '';
				}
				if ($is_closing) {
					return '</' . $tag . '>';
				}

				$attrs = '';
				$allowed_attrs = is_array($allowed_html[$tag]) ? $allowed_html[$tag] : array();
				preg_match_all(
					'~\s+([a-zA-Z0-9:-]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?~',
					(string) ($matches[3] ?? ''),
					$attr_matches,
					PREG_SET_ORDER
				);
				foreach ($attr_matches as $attr_match) {
					$name = strtolower((string) $attr_match[1]);
					if (!array_key_exists($name, $allowed_attrs)) {
						continue;
					}

					$value = '';
					if (array_key_exists(2, $attr_match) && $attr_match[2] !== '') {
						$value = (string) $attr_match[2];
					} elseif (array_key_exists(3, $attr_match) && $attr_match[3] !== '') {
						$value = (string) $attr_match[3];
					} elseif (array_key_exists(4, $attr_match) && $attr_match[4] !== '') {
						$value = (string) $attr_match[4];
					}

					if (($name === 'href' || $name === 'action') && !vms_vendor_app_confirmation_test_safe_url($value)) {
						continue;
					}

					$value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
					$attrs .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
				}

				return '<' . $tag . $attrs . '>';
			},
			(string) $html
		);
	}
}

function vms_vendor_app_confirmation_test_safe_url(string $value): bool
{
	$value = trim($value);
	if ($value === '') {
		return true;
	}

	if (strpos($value, '/') === 0 || strpos($value, '#') === 0) {
		return true;
	}

	$scheme = (string) parse_url($value, PHP_URL_SCHEME);
	return $scheme === '' || in_array(strtolower($scheme), array('http', 'https', 'mailto'), true);
}

require_once dirname(__DIR__) . '/includes/core/vendor-application-confirmation.php';

$pluginRoot = dirname(__DIR__);
$sourcePath = $pluginRoot . '/includes/core/vendor-application-confirmation.php';
$source = file_get_contents($sourcePath);

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$assert(is_string($source) && $source !== '', 'Vendor application confirmation source should be readable.');

$expectedAllowedHtml = array(
	'a' => array(
		'class' => true,
		'href' => true,
	),
	'button' => array(
		'class' => true,
		'type' => true,
	),
	'div' => array(
		'class' => true,
	),
	'form' => array(
		'action' => true,
		'class' => true,
		'method' => true,
	),
	'h2' => array(),
	'input' => array(
		'id' => true,
		'name' => true,
		'type' => true,
		'value' => true,
	),
	'li' => array(),
	'ol' => array(
		'class' => true,
	),
	'p' => array(
		'class' => true,
	),
	'section' => array(
		'class' => true,
	),
	'span' => array(
		'class' => true,
	),
	'strong' => array(),
);
$assert(bvmgr_vendor_app_confirmation_allowed_html() === $expectedAllowedHtml, 'Vendor application confirmation allowlist should contain only the intended confirmation fragment tags and attributes.');

$GLOBALS['vms_test_confirmation_state'] = 'unconfirmed';
$GLOBALS['vms_test_public_lookup_key'] = 'lookup-key"><script>alert(1)</script>';
$form = bvmgr_vendor_app_render_resend_confirmation_form(123, 'https://example.test/vendor-application/?vms_app=confirm_pending', 'Resend <script>alert(1)</script>');
$assert(strpos($form, '<form class="vms-vendor-apply-confirmation__resend" method="post" action="https://example.test/wp-admin/admin-post.php">') !== false, 'Resend form should keep its form tag, class, method, and action.');
$assert(strpos($form, 'name="action" value="vms_vendor_app_resend_confirmation"') !== false, 'Resend form should keep the admin-post action input.');
$assert(strpos($form, 'name="vms_app_ref" value="lookup-key&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"') !== false, 'Application reference should remain attribute-escaped text.');
$assert(strpos($form, 'Resend &lt;script&gt;alert(1)&lt;/script&gt;') !== false, 'Button label should render as escaped text.');
$assert(strpos($form, '<script') === false && strpos($form, 'onclick=') === false, 'Resend form should not allow executable markup.');

$pending = bvmgr_vendor_apply_render_confirmation_pending_screen(123, array('notice' => 'resent'));
$assert(strpos($pending, '<section class="vms-vendor-apply-confirmation">') !== false, 'Pending confirmation screen should keep the confirmation section.');
$assert(strpos($pending, 'We sent a new confirmation link to the email address on the application.') !== false, 'Pending confirmation screen should preserve the resent notice copy.');
$assert(strpos($pending, '<ol class="vms-vendor-apply-confirmation__steps">') !== false, 'Pending confirmation screen should keep the ordered steps.');
$assert(strpos($pending, '<form class="vms-vendor-apply-confirmation__resend"') !== false, 'Pending confirmation screen should keep the resend form fragment.');
$assert(strpos($pending, 'href="https://example.test/vendor-application/"') !== false, 'Pending confirmation screen should keep the application link.');
$assert(strpos($pending, 'href="https://example.test/vendor-portal/"') !== false, 'Pending confirmation screen should keep the portal link.');

$GLOBALS['vms_test_app_status'] = 'pending';
$existing = bvmgr_vendor_apply_render_existing_status_screen(123, 'approved');
$assert(strpos($existing, 'We already have an approved application for this business.') !== false, 'Existing status screen should preserve approved duplicate copy.');
$assert(strpos($existing, '<a class="button" href="https://example.test/vendor-portal/">Open Vendor Portal</a>') !== false, 'Existing status screen should keep the portal action link.');

$GLOBALS['vms_test_recent_application'] = array(
	'kind' => 'unconfirmed',
	'app_id' => 123,
	'status' => 'pending',
	'confirmation_state' => 'unconfirmed',
);
$panel = bvmgr_vendor_app_render_portal_applicant_panel(456, 'https://example.test/vendor-portal/');
$assert(strpos($panel, '<div class="vms-portal-auth-wrap">') !== false, 'Portal applicant panel should keep its wrapper fragment.');
$assert(strpos($panel, 'Application awaiting confirmation') !== false, 'Portal applicant panel should preserve the awaiting-confirmation copy.');
$assert(strpos($panel, '<form class="vms-vendor-apply-confirmation__resend"') !== false, 'Portal applicant panel should keep the resend form fragment.');

$unsafe = '<section class="ok" onclick="evil()" id="bad" data-x="1"><h2 style="color:red">Title</h2><p class="copy" onload="evil()">Copy</p><a class="button" href="javascript:alert(1)" target="_blank" rel="noopener">Bad link</a><form class="f" method="post" action="javascript:alert(1)" enctype="multipart/form-data"><input type="hidden" name="x" value="y" onclick="evil()" style="color:red"></form><script>alert(1)</script><iframe src="x"></iframe><object data="x"></object><embed src="x"></embed></section>';
$filtered = wp_kses($unsafe, bvmgr_vendor_app_confirmation_allowed_html());
foreach (array('<script', '<iframe', '<object', '<embed', 'onclick=', 'onload=', 'style=', ' id=', 'data-x=', 'target=', 'rel=', 'enctype=', 'javascript:') as $forbidden) {
	$assert(strpos($filtered, $forbidden) === false, 'Confirmation allowlist should reject unsupported markup or attributes: ' . $forbidden);
}
$assert(strpos($filtered, '<section class="ok"><h2>Title</h2><p class="copy">Copy</p><a class="button">Bad link</a><form class="f" method="post"><input type="hidden" name="x" value="y"></form>alert(1)</section>') !== false, 'Allowed confirmation tags should remain while unsafe attributes and protocols are stripped.');

$assert(strpos($source, 'function bvmgr_vendor_app_render_confirmation_shell(string $title, string $content): void') !== false, 'Confirmation shell renderer should remain the isolated browser output owner.');
$assert(strpos($source, 'echo wp_kses($content, bvmgr_vendor_app_confirmation_allowed_html());') !== false, 'Confirmation shell should contract only the confirmation content fragment before output.');
$assert(strpos($source, 'echo $content;') === false, 'Confirmation shell should not directly echo the content fragment.');
$assert(strpos($source, "echo '<html ' . get_language_attributes() . '>';") === false, 'Confirmation shell should not echo raw language attributes.');
$assert(strpos($source, 'language_attributes();') !== false, 'Confirmation shell should emit WordPress language attributes through the dedicated API.');

$assert(substr_count($source, 'return wp_kses((string) ob_get_clean(), bvmgr_vendor_app_confirmation_allowed_html());') === 4, 'Each confirmation/applicant fragment return should apply the dedicated contract.');
$assert(substr_count($source, 'echo wp_kses(bvmgr_vendor_app_render_resend_confirmation_form(') === 2, 'Every embedded resend-form output should apply the dedicated contract.');
$assert(strpos($source, 'echo bvmgr_vendor_app_render_resend_confirmation_form(') === false, 'Resend form fragments should not be embedded without the confirmation contract.');
$assert(strpos($source, 'wp_kses_post(') === false, 'Confirmation output should not use wp_kses_post().');
$assert(!preg_match('~wp_kses_allowed_html\s*\(\s*[\'"]post[\'"]\s*\)~', $source), 'Confirmation output should not use the broad post allowlist.');
$assert(!preg_match('~esc_html\s*\(\s*(?:\$content|bvmgr_vendor_app_render_resend_confirmation_form|bvmgr_vendor_apply_render_confirmation_pending_screen)~', $source), 'Completed confirmation HTML fragments should not be escaped wholesale as text.');
$assert(!preg_match('~wp_kses\s*\(\s*(?:wp_head\s*\(|wp_footer\s*\(|get_language_attributes\s*\(|ob_get_clean\s*\(\s*\)\s*,\s*wp_kses_allowed_html)~', $source), 'Theme, WordPress shell, and unrelated output should not be filtered through the confirmation contract.');

foreach (array(
	'esc_url(admin_url(\'admin-post.php\'))',
	'esc_attr($app_ref)',
	'esc_attr($return_url)',
	'esc_html($button_label)',
	'esc_html($notice_headline)',
	'esc_html($notice_body)',
	'esc_url($apply_url)',
	'esc_url($portal_url)',
	'esc_url($reset_url)',
	'esc_html($title)',
	'esc_attr(get_bloginfo(\'charset\'))',
) as $requiredEscaping) {
	$assert(strpos($source, $requiredEscaping) !== false, 'Confirmation output should retain contextual escaping marker: ' . $requiredEscaping);
}

fwrite(STDOUT, "Vendor application confirmation output remediation OK.\n");
