<?php
/** Standalone regression: real local sender, logs and admin POST handlers; no WP boot or mail transport. */
declare(strict_types=1);
define('ABSPATH', __DIR__);
define('HOUR_IN_SECONDS', 3600);
$GLOBALS['options'] = array();
$GLOBALS['transients'] = array();
$GLOBALS['mail'] = array();
$GLOBALS['settings'] = array('signature' => 'Venue team', 'templates_enabled' => array('post_event' => true, 'know_before' => true), 'templates' => array('post_event' => array('subject' => 'Thanks {customer_first_name}', 'body' => "{customer_greeting}\nNormal {event_name}\n{feedback_url}\n{signature}")));
function absint($v) { return abs((int) $v); }
function sanitize_key($v) { return strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $v)); }
function sanitize_text_field($v) { return trim(strip_tags(str_replace(array("\r", "\n"), ' ', $v))); }
function sanitize_textarea_field($v) { return trim(strip_tags($v)); }
function sanitize_email($v) { return trim($v); }
function is_email($v) { return filter_var($v, FILTER_VALIDATE_EMAIL); }
function wp_unslash($v) { return is_array($v) ? array_map('wp_unslash', $v) : stripslashes($v); }
function wp_strip_all_tags($v) { return strip_tags($v); }
function __($v, $d = '') { return $v; }
function esc_html__($v, $d = '') { return $v; }
function get_bloginfo($v) { return $v === 'charset' ? 'UTF-8' : 'Venue'; }
function home_url($v) { return 'https://example.invalid' . $v; }
function esc_url_raw($v) { return $v; }
function esc_html($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function wpautop($v) { return '<p>' . str_replace("\n", '<br>', $v) . '</p>'; }
function make_clickable($v) { return $v; }
function wp_json_encode($v) { return json_encode($v); }
function get_transient($k) { return $GLOBALS['transients'][$k] ?? false; }
function set_transient($k, $v, $ttl) { $GLOBALS['transients'][$k] = $v; }
function delete_transient($k) { unset($GLOBALS['transients'][$k]); }
function get_option($k, $d = false) { return $GLOBALS['options'][$k] ?? $d; }
function update_option($k, $v, $autoload = null) { $GLOBALS['options'][$k] = $v; }
function delete_option($k) { unset($GLOBALS['options'][$k]); }
function current_time($f) { return '2026-09-13 12:00:00'; }
function wp_generate_uuid4() { static $n = 0; return 'uuid-' . ++$n; }
function wp_generate_password($n, $special = true, $extra = true) { return wp_generate_uuid4(); }
function wp_mail($to, $subject, $body, $headers) { $GLOBALS['mail'][] = compact('to', 'subject', 'body', 'headers'); return true; }
function bvmgr_email_followups_settings() { return $GLOBALS['settings']; }
function bvmgr_email_followups_default_templates() { return array('know_before' => array('subject' => 'Before', 'body' => 'Normal before')); }
function bvmgr_email_followups_template_definitions() { return array('post_event' => array(), 'know_before' => array()); }
function bvmgr_email_followups_event_context($id) { return array('event_plan_id' => $id, 'event_name' => 'Sep 12'); }
function bvmgr_email_followups_context_allows_send($context) { return array(true, ''); }
function bvmgr_email_followups_event_recipients($id) { return array('recipients' => array(array('email' => 'amy@example.invalid', 'name' => 'Amy Smith'), array('email' => 'bob@example.invalid', 'name' => 'Bob Jones'), array('email' => 'cy@example.invalid', 'name' => 'Cy Lee'))); }
function bvmgr_feedback_survey_url($id, $recipient, $source) { return 'https://example.invalid/feedback?plan=' . $id . '&recipient=' . rawurlencode($recipient['email'] ?? 'preview'); }
function bvmgr_email_followups_maybe_sync_mailpoet_subscriber(...$args) { return array(); }
function add_action(...$args) {}
function add_filter(...$args) {}
function apply_filters($hook, $value) { return $hook === 'vms_email_followups_manual_batch_size' ? 2 : $value; }
function current_user_can(...$args) { return $GLOBALS['allowed'] ?? true; }
function check_admin_referer(...$args) { if (!empty($GLOBALS['bad_nonce'])) throw new RuntimeException('nonce blocked'); }
function bvmgr_nonce_action_for_request($action, $field) { return $action; }
function get_post_type($id) { return $id > 0 ? 'vms_event_plan' : false; }
function wp_die($message) { throw new RuntimeException($message); }
function _n($single, $plural, $n, $domain = '') { return $n === 1 ? $single : $plural; }
class EfuRedirect extends RuntimeException { public array $args; public function __construct($message, $args) { parent::__construct($message); $this->args = $args; } }
function bvmgr_email_followups_redirect_notice($tab, $notice, $type = 'success', $args = array()) { throw new EfuRedirect($notice, $args); }
class WP_Post { public int $ID = 12; }
function admin_url($p) { return 'https://example.invalid/wp-admin/' . $p; }
function esc_url($v) { return esc_html($v); }
function esc_attr($v) { return esc_html((string) $v); }
function esc_textarea($v) { return esc_html($v); }
function selected($a, $b, $echo = true) { return $a == $b ? 'selected' : ''; }
function checked($a, $b, $echo = true) { return $a == $b ? 'checked' : ''; }
function wp_nonce_field($action) { echo '<input name="_wpnonce" value="fixture" />'; }
function bvmgr_email_followups_event_choice_label($p) { return 'Sep 12 event'; }
function bvmgr_email_followups_scheduled_timestamp(...$args) { return 0; }
function wp_kses_post($v) { return $v; }
$root = dirname(__DIR__);
require $root . '/includes/modules/email-followups/sender.php';
require $root . '/includes/modules/email-followups/logs.php';
require $root . '/includes/modules/email-followups/admin-ui.php';
$count = 0;
function check($condition, $message) { global $count; ++$count; if (!$condition) throw new RuntimeException($message); }
function post_call($fn, $post) { $_POST = $post; try { $fn(); } catch (EfuRedirect $e) { return $e; } throw new RuntimeException('Expected redirect'); }
$GLOBALS['settings']['test_recipient'] = 'test@example.invalid';
$original = $GLOBALS['settings'];
$draftPost = array('event_plan_id' => 12, 'email_key' => 'post_event', 'customize_event' => 1, 'event_subject' => 'Special {customer_first_name}', 'event_body' => "{customer_greeting}\nSep 12 only\n{feedback_url}\n{signature}");
post_call('bvmgr_email_followups_save_event_draft_post', $draftPost);
check($GLOBALS['settings'] === $original, 'Draft changed saved settings');
check(bvmgr_email_followups_event_draft(13, 'post_event') === null, 'Draft leaked across event');
check(bvmgr_email_followups_event_draft(12, 'know_before') === null, 'Draft leaked across key');
$content = bvmgr_email_followups_manual_content(12, 'post_event');
foreach (bvmgr_email_followups_event_recipients(12)['recipients'] as $r) {
 $preview = bvmgr_email_followups_render_message('post_event', 12, $r, $content);
 check(str_contains($preview['body_text'], 'Sep 12 only'), 'Preview did not use raw override');
 check(str_contains($preview['body_text'], bvmgr_email_followups_customer_greeting($r)), 'Personalized greeting lost');
 check(str_contains($preview['body_text'], rawurlencode($r['email'])), 'Feedback URL not personalized');
 check(str_contains($preview['body_text'], 'Venue team'), 'Signature lost');
}
$state = array('event_plan_id' => 12, 'email_key' => 'post_event', 'event_choices' => array(new WP_Post()), 'template_definitions' => bvmgr_email_followups_template_definitions());
ob_start(); bvmgr_email_followups_render_preview_tab($state); $ui = ob_get_clean();
check(str_contains($ui, 'Customize for This Event') && str_contains($ui, 'Reset to Template'), 'Editor controls absent');
check(str_contains($ui, 'Special {customer_first_name}') && str_contains($ui, '{customer_greeting}'), 'Editor used rendered recipient text');
check(str_contains($ui, 'Special Amy') && str_contains($ui, 'amy%40example.invalid'), 'Actual preview path did not render override');
$hash = bvmgr_email_followups_content_hash($content);
post_call('bvmgr_email_followups_send_test_post', array('event_plan_id' => 12, 'email_key' => 'post_event', 'test_recipient' => 'test@example.invalid', 'content_hash' => $hash));
check($GLOBALS['mail'][0]['subject'] === '[TEST] Special Test', 'Test did not consume override');
check(str_contains($GLOBALS['mail'][0]['body'], 'test%40example.invalid'), 'Test feedback URL lost');
$logs = bvmgr_email_followups_get_logs();
check($logs[0]['meta']['subject'] === $GLOBALS['mail'][0]['subject'] && $logs[0]['meta']['body_html'] === $GLOBALS['mail'][0]['body'], 'Test audit differs from mail');
$manualPost = array('event_plan_id' => 12, 'email_key' => 'post_event', 'confirm_send' => 1, 'recipient_selection_present' => 1, 'selected_recipients' => array('amy@example.invalid', 'bob@example.invalid', 'cy@example.invalid'), 'content_hash' => $hash);
$step = post_call('bvmgr_email_followups_manual_send_post', $manualPost);
check(count($GLOBALS['mail']) === 3, 'First manual step did not honor batch limit');
$token = $step->args['batch_token'];
$batch = bvmgr_email_followups_batch_get($token);
check($batch['content'] === $content, 'Manual batch did not freeze content');
check($batch['emails'] === array('cy@example.invalid'), 'Recipient continuation changed');
post_call('bvmgr_email_followups_save_event_draft_post', array_merge($draftPost, array('event_body' => 'CHANGED')));
$_GET['batch_token'] = $token;
ob_start(); bvmgr_email_followups_render_preview_tab($state); $ui = ob_get_clean();
check(str_contains($ui, 'Special Amy') && str_contains($ui, 'frozen copy'), 'Batch preview changed with draft');
check(str_contains($ui, 'class="vms-efu-manual-send-form" hidden'), 'Active batch offered mismatched new send');
$_GET = array();
$GLOBALS['settings']['signature'] = 'CHANGED SIGNATURE';
delete_transient(bvmgr_email_followups_event_draft_key(12, 'post_event'));
post_call('bvmgr_email_followups_manual_send_post', array('event_plan_id' => 12, 'email_key' => 'post_event', 'confirm_send' => 1, 'batch_token' => $token));
$last = end($GLOBALS['mail']);
check($last['subject'] === 'Special Cy' && str_contains($last['body'], 'Sep 12 only'), 'Continuation consumed changed/expired draft');
check(str_contains($last['body'], 'Venue team') && !str_contains($last['body'], 'CHANGED'), 'Snapshot signature changed');
check(bvmgr_email_followups_batch_get($token) === array(), 'Completed batch not cleared');
$logs = bvmgr_email_followups_get_logs();
foreach ($GLOBALS['mail'] as $mail) {
 $matching = array_values(array_filter($logs, fn($r) => $r['recipient'] === $mail['to']));
 check($matching[0]['meta']['subject'] === $mail['subject'] && $matching[0]['meta']['body_html'] === $mail['body'], 'Audit differs from actual mail args');
}
$before = count($GLOBALS['mail']);
post_call('bvmgr_email_followups_manual_send_post', $manualPost);
check(count($GLOBALS['mail']) === $before, 'Stale preview was allowed to send');
$GLOBALS['settings'] = $original;
post_call('bvmgr_email_followups_save_event_draft_post', $draftPost);
post_call('bvmgr_email_followups_save_event_draft_post', array_merge($draftPost, array('reset_to_template' => 1)));
check(bvmgr_email_followups_event_draft(12, 'post_event') === null, 'Reset left draft');
check(bvmgr_email_followups_manual_content(12, 'post_event')['body'] === $original['templates']['post_event']['body'], 'Reset did not restore template');
post_call('bvmgr_email_followups_save_event_draft_post', $draftPost);
post_call('bvmgr_email_followups_save_event_draft_post', array_merge($draftPost, array('customize_event' => 0)));
check(bvmgr_email_followups_event_draft(12, 'post_event') === null, 'Turning customization off left draft');
post_call('bvmgr_email_followups_save_event_draft_post', $draftPost);
bvmgr_email_followups_send_event_email('post_event', 14, 'auto', array('content' => $content));
$last = end($GLOBALS['mail']);
check(str_starts_with($last['subject'], 'Thanks ') && str_contains($last['body'], 'Normal'), 'Automatic send used override');
$before = count($GLOBALS['mail']);
bvmgr_email_followups_send_event_email('post_event', 12, 'manual', array('content' => $content));
check(count($GLOBALS['mail']) === $before, 'Dedupe behavior changed');
$GLOBALS['allowed'] = false;
try { post_call('bvmgr_email_followups_save_event_draft_post', $draftPost); check(false, 'Unauthorized draft accepted'); } catch (RuntimeException $e) { check($e->getMessage() === 'Insufficient permissions.', 'Wrong permission failure'); }
$GLOBALS['allowed'] = true; $GLOBALS['bad_nonce'] = true;
try { post_call('bvmgr_email_followups_save_event_draft_post', $draftPost); check(false, 'Bad nonce accepted'); } catch (RuntimeException $e) { check($e->getMessage() === 'nonce blocked', 'Wrong nonce failure'); }
check($GLOBALS['settings'] === $original, 'Workflow modified saved template');
echo "Email Follow-Ups event override: {$count} assertions passed; all mail intercepted.\n";
