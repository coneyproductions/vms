<?php
defined('ABSPATH') || exit;

// ============================================================
// Settings + helpers
// ============================================================

function vms_dt_vi_get_preview_rows_setting(): int
{
	$user_id = get_current_user_id();
	$default = 50;

	if (!$user_id) {
		return $default;
	}

	$val = get_user_meta($user_id, 'vms_dt_vi_preview_rows', true);
	$val = is_numeric($val) ? (int) $val : $default;

	$allowed = [25, 50, 100, 500];
	if (!in_array($val, $allowed, true)) {
		$val = $default;
	}

	return $val;
}

function vms_dt_vi_update_preview_rows_setting_from_post(): void
{
	if (!is_user_logged_in()) {
		return;
	}
	if (!isset($_POST['vms_dt_vi_preview_rows'])) {
		return;
	}

	$val = (int) wp_unslash($_POST['vms_dt_vi_preview_rows']);
	$allowed = [25, 50, 100, 500];
	if (!in_array($val, $allowed, true)) {
		return;
	}

	update_user_meta(get_current_user_id(), 'vms_dt_vi_preview_rows', $val);
}

function vms_dt_vi_is_sensitive_header(?string $header): bool
{
	$header = $header ?? '';
	$h = strtolower(trim((string) $header));

	return (
		strpos($h, 'ssn') !== false ||
		strpos($h, 'ein') !== false ||
		strpos($h, 'tin') !== false ||
		strpos($h, 'tax id') !== false ||
		strpos($h, 'taxid') !== false
	);
}

function vms_dt_vi_mask_sensitive_cell(?string $header, string $value): string
{
	if (!vms_dt_vi_is_sensitive_header($header)) {
		return $value;
	}

	$digits = preg_replace('/\D+/', '', $value);
	if ($digits === null) {
		$digits = '';
	}

	$last4 = (strlen($digits) >= 4) ? substr($digits, -4) : $digits;
	if ($last4 === '') {
		return '';
	}

	return '*****' . $last4;
}

/**
 * Hard whitelist of editable CSV headers for the Preview override layer.
 *
 * Default behavior: only headers that map to a small set of safe canonical fields
 * (via VMS_Vendor_Schema_Registry::resolve_fields(...)). This avoids editing
 * unexpected columns and keeps the override layer focused.
 *
 * You may customize this list using the filter:
 *   vms_dt_vi_override_allowed_columns
 *
 * @param array $headers The raw CSV headers.
 * @return array List of header strings that are editable.
 */
function vms_dt_vi_get_override_allowed_headers(array $headers): array
{
	$allowed_headers = [];

	// Canonical fields that are safe to override in Preview.
	$allowed_fields = [
		'display_name',
		'primary_email',
		'primary_phone',
		'email',
		'phone',
		'website',
		'notes',
	];

	$schema_registry = vms_dt_core_class('VMS_Vendor_Schema_Registry');
	if ($schema_registry !== '' && method_exists($schema_registry, 'resolve_fields')) {
		$resolved = $schema_registry::resolve_fields($headers);
		if (is_array($resolved)) {
			foreach ($allowed_fields as $f) {
				$h = isset($resolved[$f]) ? (string) $resolved[$f] : '';
				$h = trim($h);
				if ($h !== '' && !vms_dt_vi_is_sensitive_header($h)) {
					$allowed_headers[] = $h;
				}
			}
		}
	}

	$allowed_headers = array_values(array_unique(array_filter($allowed_headers, static function ($h) {
		return is_string($h) && trim($h) !== '';
	})));

	// Fallback: if we couldn't resolve, allow nothing by default (hard whitelist).
	// The filter below can still open this up if needed.
	if (function_exists('apply_filters')) {
		$filtered = apply_filters('vms_dt_vi_override_allowed_columns', $allowed_headers, $headers);
		if (is_array($filtered)) {
			$filtered2 = [];
			foreach ($filtered as $h) {
				$h = trim((string) $h);
				if ($h !== '' && !vms_dt_vi_is_sensitive_header($h)) {
					$filtered2[] = $h;
				}
			}
			$allowed_headers = array_values(array_unique($filtered2));
		}
	}

	return $allowed_headers;
}

// ============================================================
// Menu registration
// ============================================================

function vms_dt_register_vendor_import_page(): void
{
	$parent_slug = 'vms-data-tools';
	$cap = function_exists('vms_dt_manage_capability') ? vms_dt_manage_capability() : 'manage_options';

	add_submenu_page(
		$parent_slug,
		'Vendor Import (CSV)',
		'Vendor Import',
		$cap,
		'vms-data-tools-vendor-import',
		'vms_dt_vendor_import_render_page'
	);
}

if (!function_exists('vms_dt_vendor_import_render_commit_success_cta')) {
	function vms_dt_vendor_import_render_commit_success_cta(array $report): void
	{
		$summary = $report['summary'] ?? [];
		$processed = (int) ($summary['processed'] ?? 0);
		$create    = (int) ($summary['create'] ?? 0);
		$update    = (int) ($summary['update'] ?? 0);
		$skip      = (int) ($summary['skip'] ?? 0);
		$fail      = (int) ($summary['fail'] ?? 0);

		$vendors_url      = admin_url('edit.php?post_type=vms_vendor');
		$vendors_latest   = admin_url('edit.php?post_type=vms_vendor&orderby=date&order=desc');
		$import_page_url  = menu_page_url('vms-data-tools-vendor-import', false);

		$is_success = ($fail === 0);
		$cls = $is_success ? 'notice notice-success' : 'notice notice-warning';

		echo '<div class="' . esc_attr($cls) . '" style="padding:14px 16px;margin:16px 0;">';

		echo $is_success
			? '<p style="margin:0 0 8px 0;font-size:16px;"><strong>✅ Import complete.</strong></p>'
			: '<p style="margin:0 0 8px 0;font-size:16px;"><strong>⚠️ Import completed with issues.</strong></p>';

		echo '<p style="margin:0 0 12px 0;">';
		echo esc_html("Processed: {$processed} | Created: {$create} | Updated: {$update} | Skipped: {$skip} | Failed: {$fail}");
		echo '</p>';

		echo '<p style="margin:0; display:flex; gap:10px; flex-wrap:wrap;">';
		echo '<a class="button button-primary" href="' . esc_url($vendors_url) . '">View Vendors</a>';
		echo '<a class="button" href="' . esc_url($vendors_latest) . '">View Latest Vendors</a>';
		echo '<a class="button" href="' . esc_url($import_page_url) . '">Run Another Import</a>';
		echo '</p>';

		echo '</div>';
	}
}

// ============================================================
// Main page renderer
// ============================================================

function vms_dt_vendor_import_render_page(): void
{
	$cap = function_exists('vms_dt_manage_capability') ? vms_dt_manage_capability() : 'manage_options';
	if (!current_user_can($cap)) {
		wp_die(esc_html__('Insufficient permissions.', 'vms'));
	}

	if (!class_exists('VMS_Vendor_Import_Engine')) {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__('Vendor Import (CSV)', 'vms') . '</h1>';
		echo '<div class="notice notice-error"><p>';
		echo esc_html__('Import engine not available (VMS_Vendor_Import_Engine). Ensure the engine is loaded by Data Tools or legacy importer.', 'vms');
		echo '</p></div>';
		echo '</div>';
		return;
	}

	$engine = new VMS_Vendor_Import_Engine();

	$state = [
		'errors'  => [],
		'preview' => null,
		'commit'  => null,
	];

	$action = isset($_POST['vms_vendor_import_action'])
		? sanitize_text_field((string) wp_unslash($_POST['vms_vendor_import_action']))
		: '';

	if ($action === 'preview' && check_admin_referer('vms_vendor_import_csv_preview')) {
		vms_dt_vi_update_preview_rows_setting_from_post();

		$result = $engine->handle_preview($_FILES['vms_vendor_csv'] ?? null);
		if (is_wp_error($result)) {
			$state['errors'][] = $result->get_error_message();
		} else {
			$state['preview'] = $result;
		}
	}

	if ($action === 'commit' && check_admin_referer('vms_vendor_import_csv_commit')) {
		$token = isset($_POST['vms_vendor_import_token'])
			? sanitize_text_field((string) wp_unslash($_POST['vms_vendor_import_token']))
			: '';

		$js_ready = isset($_POST['vms_vendor_js_ready'])
			? (string) wp_unslash($_POST['vms_vendor_js_ready'])
			: '0';

		$force_all = isset($_POST['vms_vendor_force_commit_all'])
			? (string) wp_unslash($_POST['vms_vendor_force_commit_all'])
			: '';

		if ($js_ready !== '1' && $force_all !== '1') {
			$state['errors'][] = 'Selection controls were not confirmed. This usually means JavaScript did not run, so row selections may not be respected. Re-run Preview, or check the Force commit box to intentionally import all previewed rows.';
		}

		$exclude = isset($_POST['vms_vendor_exclude_rows'])
			? (string) wp_unslash($_POST['vms_vendor_exclude_rows'])
			: '';

		$exclude_rows = [];
		if ($exclude !== '') {
			$parts = preg_split('/[^0-9]+/', $exclude);
			if (is_array($parts)) {
				foreach ($parts as $p) {
					$n = (int) $p;
					if ($n > 0) {
						$exclude_rows[] = $n;
					}
				}
			}
			$exclude_rows = array_values(array_unique($exclude_rows));
		}

				$overrides_json = isset($_POST['vms_vendor_overrides_json'])
			? (string) wp_unslash($_POST['vms_vendor_overrides_json'])
			: '';

		$overrides = [];
		if ($overrides_json !== '') {
			if (strlen($overrides_json) > 200000) {
				$state['errors'][] = 'Overrides payload too large. Please reduce edits and try again.';
			} else {
				$decoded = json_decode($overrides_json, true);
				if (!is_array($decoded)) {
					$state['errors'][] = 'Overrides payload is not valid JSON. Re-run Preview and try again.';
				} else {
					$overrides = $decoded;
				}
			}
		}

		if (empty($state['errors'])) {
			$result = $engine->handle_commit($token, $exclude_rows, $overrides);
			if (is_wp_error($result)) {
				$state['errors'][] = $result->get_error_message();
			} else {
				$state['commit'] = $result;
			}
		}

	}

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__('Vendor Import (CSV)', 'vms') . '</h1>';

	if (!empty($state['errors'])) {
		echo '<div class="notice notice-error"><p><strong>' . esc_html__('Import Error:', 'vms') . '</strong></p><ul>';
		foreach ($state['errors'] as $e) {
			echo '<li>' . esc_html((string) $e) . '</li>';
		}
		echo '</ul></div>';
	}

	if (!empty($state['commit'])) {
		if (function_exists('vms_dt_vendor_import_render_commit_success_cta')) {
			vms_dt_vendor_import_render_commit_success_cta($state['commit']);
		}

		vms_dt_vendor_import_render_report($state['commit'], 'Commit Report');
	} elseif (!empty($state['preview'])) {
		echo '<p style="margin:10px 0 14px 0;color:#555;">' . esc_html('Uncheck rows in the preview below to exclude them, then commit.') . '</p>';

		vms_dt_vendor_import_render_report($state['preview'], 'Preview Report');

		echo '<form method="post" id="vms-vendor-commit-form">';
		wp_nonce_field('vms_vendor_import_csv_commit');

		echo '<input type="hidden" name="vms_vendor_import_action" value="commit">';
		echo '<input type="hidden" name="vms_vendor_import_token" value="' . esc_attr((string) ($state['preview']['token'] ?? '')) . '">';
		echo '<input type="hidden" name="vms_vendor_exclude_rows" id="vms_vendor_exclude_rows" value="">';

		echo '<input type="hidden" name="vms_vendor_js_ready" id="vms_vendor_js_ready" value="0">';

		echo '<input type="hidden" name="vms_vendor_overrides_json" id="vms_vendor_overrides_json" value="">';

		echo '<div id="vms_vendor_override_controls" style="margin:12px 0; padding:10px; border:1px solid #ccd0d4; background:#fff;">';
		echo '<strong>Overrides:</strong> <span id="vms_vendor_override_count">0</span> cell(s) changed';
		echo '<div style="margin-top:8px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">';
		echo '<label style="display:flex; gap:6px; align-items:center; margin:0;">';
		echo '<input type="checkbox" id="vms_vendor_enable_overrides" value="1"> ';
		echo esc_html('Enable inline edits (preview override layer)');
		echo '</label>';
		echo '<button type="button" class="button" id="vms_vendor_reset_overrides">Reset overrides</button>';
		echo '</div>';
		echo '<p style="margin:8px 0 0 0; color:#555;">' . esc_html('Tip: enable edits, then click a cell to override it. All overrides are applied before validation and listed in the commit report audit.') . '</p>';
		echo '</div>';

		echo '<p style="margin:10px 0 0 0;color:#555;">';
		echo '<label>';
		echo '<input type="checkbox" name="vms_vendor_force_commit_all" value="1"> ';
		echo esc_html('Force commit even if selection controls are not active (use only if the Selection box stays at Included 0 / Excluded 0).');
		echo '</label>';
		echo '</p>';

		echo '<div id="vms_vendor_commit_selection_summary" style="margin:12px 0; padding:10px; border:1px solid #ccd0d4; background:#fff;">';
		echo '<strong>Selection:</strong> ';
		echo '<span id="vms_vendor_sel_counts">Included 0 / Excluded 0</span>';
		echo '<div id="vms_vendor_sel_excluded_list" style="margin-top:6px; font-family:monospace; font-size:12px;"></div>';
		echo '</div>';

		submit_button(__('Commit Included Rows', 'vms'), 'primary');
		echo '</form>';

		vms_dt_vi_render_selection_script_inline();
		vms_dt_vi_render_overrides_script_inline();
	} else {
		echo '<form method="post" enctype="multipart/form-data">';
		wp_nonce_field('vms_vendor_import_csv_preview');
		echo '<input type="hidden" name="vms_vendor_import_action" value="preview">';

		echo '<table class="form-table"><tbody>';

		echo '<tr>';
		echo '<th scope="row"><label for="vms_vendor_csv">' . esc_html__('CSV File', 'vms') . '</label></th>';
		echo '<td><input type="file" id="vms_vendor_csv" name="vms_vendor_csv" accept=".csv,text/csv" required></td>';
		echo '</tr>';

		$rows_setting = vms_dt_vi_get_preview_rows_setting();

		echo '<tr>';
		echo '<th scope="row"><label for="vms_dt_vi_preview_rows">' . esc_html__('Rows to preview', 'vms') . '</label></th>';
		echo '<td>';
		echo '<select name="vms_dt_vi_preview_rows" id="vms_dt_vi_preview_rows">';
		foreach ([25, 50, 100, 500] as $n) {
			$label = ($n === 500) ? 'All (capped at 500)' : (string) $n;
			echo '<option value="' . esc_attr((string) $n) . '"' . selected($rows_setting, $n, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select>';
		echo '</td>';
		echo '</tr>';

		echo '</tbody></table>';

		submit_button(__('Preview Import', 'vms'), 'primary');
		echo '</form>';
	}

	echo '</div>';
}


function vms_dt_vi_render_selection_script_inline(): void
{
	echo '<script>';
	echo '(function(){';

	echo 'function recompute(){';
	echo '  var cbs = document.querySelectorAll(".vms-dt-include-row");';
	echo '  if (!cbs || !cbs.length) return;';
	echo '  var excluded = [];';
	echo '  var includedCount = 0;';
	echo '  var excludedCount = 0;';
	echo '  cbs.forEach(function(cb){';
	echo '    var v = (cb && cb.value) ? String(cb.value) : "";';
	echo '    if (cb.checked) {';
	echo '      includedCount++;';
	echo '    } else {';
	echo '      excludedCount++;';
	echo '      if (v !== "") excluded.push(v);';
	echo '    }';
	echo '  });';
	echo '  excluded.sort(function(a,b){ return parseInt(a,10) - parseInt(b,10); });';

	echo '  var jsReady = document.getElementById("vms_vendor_js_ready");';
	echo '  if (jsReady) jsReady.value = "1";';

	echo '  var hidden = document.getElementById("vms_vendor_exclude_rows");';
	echo '  if (hidden) hidden.value = excluded.join(",");';

	echo '  var counts = document.getElementById("vms_vendor_sel_counts");';
	echo '  if (counts) counts.textContent = "Included " + includedCount + " / Excluded " + excludedCount;';
	echo '  var list = document.getElementById("vms_vendor_sel_excluded_list");';
	echo '  if (list) list.textContent = excluded.length ? ("Excluded rows: " + excluded.join(", ")) : "Excluded rows: (none)";';
	echo '}';

	echo 'function setAll(state){';
	echo '  document.querySelectorAll(".vms-dt-include-row").forEach(function(cb){ cb.checked = state; });';
	echo '  recompute();';
	echo '}';

	echo 'document.addEventListener("click", function(e){';
	echo '  var t = e.target;';
	echo '  if (!t) return;';
	echo '  if (t.id === "vms_dt_vi_select_all") { e.preventDefault(); setAll(true); return; }';
	echo '  if (t.id === "vms_dt_vi_select_none") { e.preventDefault(); setAll(false); return; }';
	echo '});';

	echo 'document.addEventListener("change", function(e){';
	echo '  var t = e.target;';
	echo '  if (!t || !t.classList) return;';
	echo '  if (t.classList.contains("vms-dt-include-row")) { recompute(); }';
	echo '});';

	echo 'var form = document.getElementById("vms-vendor-commit-form");';
	echo 'if (form) { form.addEventListener("submit", function(){ recompute(); }); }';

	echo 'if (document.readyState === "loading") {';
	echo '  document.addEventListener("DOMContentLoaded", function(){ recompute(); });';
	echo '} else {';
	echo '  recompute();';
	echo '}';

	echo '})();';
	echo '</script>';
}


function vms_dt_vi_render_overrides_script_inline(): void
{
	// Inline styles for edited cell highlighting + badge.
	echo '<style>';
	echo '.vms-vi-cell{display:block; min-height:20px; padding:2px 4px; border-radius:6px; cursor:pointer;}';
	echo '.vms-vi-cell:focus{outline:2px solid #2271b1; outline-offset:2px;}';
	echo 'td.vms-vi-edited-cell{background:#fff8dc !important; position:relative; padding-right:70px;}';
	echo '.vms-vi-edited-badge{position:absolute; top:4px; right:6px; font-size:11px; line-height:16px; padding:0 7px; border-radius:999px; background:#ffe082; border:1px solid #ffca28; color:#000;}';
	echo '.vms-vi-override-input{box-sizing:border-box;}';
	echo '</style>';

	echo '<script>';
	echo '(function(){';

	echo '  var enable = document.getElementById("vms_vendor_enable_overrides");';
	echo '  var resetBtn = document.getElementById("vms_vendor_reset_overrides");';
	echo '  var hidden = document.getElementById("vms_vendor_overrides_json");';
	echo '  var countEl = document.getElementById("vms_vendor_override_count");';
	echo '  if (!enable || !resetBtn || !hidden) return;';

	echo '  var overrides = {};';

	echo '  function countOverrides(){';
	echo '    var n = 0;';
	echo '    for (var r in overrides) {';
	echo '      if (!overrides.hasOwnProperty(r)) continue;';
	echo '      for (var c in overrides[r]) {';
	echo '        if (overrides[r].hasOwnProperty(c)) n++;';
	echo '      }';
	echo '    }';
	echo '    return n;';
	echo '  }';

	echo '  function syncHidden(){';
	echo '    try { hidden.value = JSON.stringify(overrides); } catch(e) { hidden.value = ""; }';
	echo '    if (countEl) countEl.textContent = String(countOverrides());';
	echo '  }';

	echo '  function removeOverride(row, col){';
	echo '    if (!overrides[row] || !overrides[row][col]) return;';
	echo '    delete overrides[row][col];';
	echo '    var empty = true;';
	echo '    for (var k in overrides[row]) { if (overrides[row].hasOwnProperty(k)) { empty = false; break; } }';
	echo '    if (empty) delete overrides[row];';
	echo '  }';

	echo '  function makeSpan(row, col, original, value){';
	echo '    var span = document.createElement("span");';
	echo '    span.className = "vms-vi-cell";';
	echo '    span.tabIndex = 0;';
	echo '    span.dataset.row = row;';
	echo '    span.dataset.col = col;';
	echo '    span.dataset.original = original;';
	echo '    span.textContent = value;';
	echo '    return span;';
	echo '  }';

	echo '  function convertSpanToInput(span){';
	echo '    if (!span || !span.dataset) return;';
	echo '    var row = String(span.dataset.row || "");';
	echo '    var col = String(span.dataset.col || "");';
	echo '    var original = String(span.dataset.original || "");';
	echo '    var current = span.textContent;';
	echo '    var input = document.createElement("input");';
	echo '    input.type = "text";';
	echo '    input.className = "vms-vi-override-input";';
	echo '    input.value = current;';
	echo '    input.dataset.row = row;';
	echo '    input.dataset.col = col;';
	echo '    input.dataset.original = original;';
	echo '    input.style.width = "100%";';
	echo '    input.style.minWidth = "160px";';
	echo '    input.disabled = !enable.checked;';
	echo '    span.parentNode.replaceChild(input, span);';

	echo '    function commitValue(){';
	echo '      var newVal = String(input.value || "");';
	echo '      var isEdited = (newVal !== original);';
	echo '      if (!isEdited) {';
	echo '        removeOverride(row, col);';
	echo '      } else {';
	echo '        if (!overrides[row]) overrides[row] = {};';
	echo '        overrides[row][col] = {old: original, new: newVal};';
	echo '      }';
	echo '      var td = input.closest ? input.closest("td") : input.parentNode;';
	echo '      if (td && td.classList) {';
	echo '        var badge = td.querySelector ? td.querySelector(".vms-vi-edited-badge") : null;';
	echo '        if (isEdited) {';
	echo '          td.classList.add("vms-vi-edited-cell");';
	echo '          if (!badge) {';
	echo '            badge = document.createElement("span");';
	echo '            badge.className = "vms-vi-edited-badge";';
	echo '            badge.textContent = "Edited";';
	echo '            td.appendChild(badge);';
	echo '          }';
	echo '        } else {';
	echo '          td.classList.remove("vms-vi-edited-cell");';
	echo '          if (badge && badge.parentNode) badge.parentNode.removeChild(badge);';
	echo '        }';
	echo '      }';
	echo '      syncHidden();';
	echo '    }';

	echo '    input.addEventListener("input", commitValue);';
	echo '    input.addEventListener("blur", commitValue);';
	echo '    input.addEventListener("keydown", function(e){';
	echo '      if (e && e.key === "Enter") { e.preventDefault(); input.blur(); }';
	echo '    });';

	echo '    input.focus();';
	echo '    input.select();';
	echo '    commitValue();';
	echo '  }';

	echo '  document.addEventListener("click", function(e){';
	echo '    var t = e.target;';
	echo '    if (!enable.checked) return;';
	echo '    if (t && t.classList && t.classList.contains("vms-vi-cell")) {';
	echo '      e.preventDefault();';
	echo '      convertSpanToInput(t);';
	echo '    }';
	echo '  });';

	echo '  document.addEventListener("keydown", function(e){';
	echo '    var t = e.target;';
	echo '    if (!enable.checked) return;';
	echo '    if (e && e.key === "Enter" && t && t.classList && t.classList.contains("vms-vi-cell")) {';
	echo '      e.preventDefault();';
	echo '      convertSpanToInput(t);';
	echo '    }';
	echo '  });';

	echo '  enable.addEventListener("change", function(){';
	echo '    var inputs = document.querySelectorAll(".vms-vi-override-input");';
	echo '    for (var i = 0; i < inputs.length; i++) { inputs[i].disabled = !enable.checked; }';
	echo '  });';

	echo '  resetBtn.addEventListener("click", function(){';
	echo '    overrides = {};';
	echo '    var inputs = document.querySelectorAll(".vms-vi-override-input");';
	echo '    for (var i = inputs.length - 1; i >= 0; i--) {';
	echo '      var input = inputs[i];';
	echo '      var td = input.closest ? input.closest("td") : input.parentNode;';
	echo '      if (td && td.classList) {';
	echo '        td.classList.remove("vms-vi-edited-cell");';
	echo '        var badge = td.querySelector ? td.querySelector(".vms-vi-edited-badge") : null;';
	echo '        if (badge && badge.parentNode) badge.parentNode.removeChild(badge);';
	echo '      }';
	echo '      var row = String(input.dataset.row || "");';
	echo '      var col = String(input.dataset.col || "");';
	echo '      var original = String(input.dataset.original || "");';
	echo '      var span = makeSpan(row, col, original, original);';
	echo '      input.parentNode.replaceChild(span, input);';
	echo '    }';
	echo '    syncHidden();';
	echo '  });';

	echo '  var form = document.getElementById("vms-vendor-commit-form");';
	echo '  if (form) { form.addEventListener("submit", function(){ syncHidden(); }); }';

	echo '  syncHidden();';

	echo '})();';
	echo '</script>';
}

// ============================================================
// Report renderer
// ============================================================

function vms_dt_vendor_import_render_report(array $report, string $title): void
{
	$summary = $report['summary'] ?? [];
	$rows    = $report['rows'] ?? [];

	echo '<h2>' . esc_html((string) $title) . '</h2>';

	echo '<p>';
	echo '<strong>' . esc_html__('Batch:', 'vms') . '</strong> ' . esc_html((string) ($report['batch_id'] ?? '')) . '<br>';
	echo '<strong>' . esc_html__('File:', 'vms') . '</strong> ' . esc_html((string) ($report['filename'] ?? '')) . '<br>';
	echo '<strong>' . esc_html__('Processed:', 'vms') . '</strong> ' . esc_html((string) ($summary['processed'] ?? 0)) . ' | ';
	echo '<strong>' . esc_html__('Create:', 'vms') . '</strong> ' . esc_html((string) ($summary['create'] ?? 0)) . ' | ';
	echo '<strong>' . esc_html__('Update:', 'vms') . '</strong> ' . esc_html((string) ($summary['update'] ?? 0)) . ' | ';
	echo '<strong>' . esc_html__('Skip:', 'vms') . '</strong> ' . esc_html((string) ($summary['skip'] ?? 0)) . ' | ';
	echo '<strong>' . esc_html__('Fail:', 'vms') . '</strong> ' . esc_html((string) ($summary['fail'] ?? 0));
	echo '</p>';

	if (!empty($report['selection']) && is_array($report['selection'])) {
		$sel = $report['selection'];
		$excluded_rows = isset($sel['excluded_rows']) && is_array($sel['excluded_rows']) ? $sel['excluded_rows'] : [];
		$excluded_count = (int) ($sel['excluded_count'] ?? 0);

		echo '<p style="margin:6px 0 14px 0;color:#555;">';
		echo '<strong>' . esc_html__('Excluded rows:', 'vms') . '</strong> ';
		if ($excluded_count > 0) {
			echo esc_html(implode(', ', array_map('intval', $excluded_rows)));
		} else {
			echo esc_html('(none)');
		}
		echo '</p>';
	}

	if (!empty($report['overrides']) && is_array($report['overrides'])) {
		$ov = $report['overrides'];
		$entries = isset($ov['entries']) && is_array($ov['entries']) ? $ov['entries'] : [];
		$applied = (int) ($ov['applied'] ?? 0);
		$skipped = (int) ($ov['skipped'] ?? 0);

		echo '<p style="margin:6px 0 14px 0;color:#555;">';
		echo '<strong>' . esc_html__('Overrides:', 'vms') . '</strong> ' . esc_html((string) $applied) . ' applied';
		if ($skipped > 0) {
			echo esc_html(' | ' . (string) $skipped . ' not applied');
		}
		echo '</p>';

		if (!empty($entries)) {
			echo '<details open style="margin:0 0 16px 0;">';
			echo '<summary style="cursor:pointer;font-weight:600;">' . esc_html('Override Audit (row | header | old → new)') . '</summary>';
			echo '<div style="margin-top:8px; padding:10px; border:1px solid #ccd0d4; background:#fff; font-family:monospace; font-size:12px; line-height:1.6;">';
			foreach ($entries as $e) {
				if (!is_array($e)) {
					continue;
				}
				$row = (int) ($e['row'] ?? 0);
				$hdr = (string) ($e['header'] ?? '');
				$old = (string) ($e['old'] ?? '');
				$new = (string) ($e['new'] ?? '');
				$ap  = !empty($e['applied']);
				$reason = (string) ($e['reason'] ?? '');
				$line = $row . ' | ' . $hdr . ' | "' . $old . '" → "' . $new . '"';
				if (!$ap && $reason !== '') {
					$line .= ' [not applied: ' . $reason . ']';
				}
				echo esc_html($line) . '<br>';
			}
			echo '</div>';
			echo '</details>';
		}
	}

	if (!empty($report['raw_preview']) && is_array($report['raw_preview'])) {
		vms_dt_vi_render_raw_preview($report['raw_preview'], vms_dt_vi_get_preview_rows_setting());
	}

	if (!empty($rows)) {
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__('Row', 'vms') . '</th>';
		echo '<th>' . esc_html__('Level', 'vms') . '</th>';
		echo '<th>' . esc_html__('Action', 'vms') . '</th>';
		echo '<th>' . esc_html__('Identity', 'vms') . '</th>';
		echo '<th>' . esc_html__('Field', 'vms') . '</th>';
		echo '<th>' . esc_html__('Header', 'vms') . '</th>';
		echo '<th>' . esc_html__('Cell', 'vms') . '</th>';
		echo '<th>' . esc_html__('Reason', 'vms') . '</th>';
		echo '</tr></thead><tbody>';

		foreach ($rows as $r) {
			if (!is_array($r)) {
				continue;
			}

			$hdr  = (string) ($r['header'] ?? '');
			$cell = (string) ($r['cell'] ?? '');
			$cell = vms_dt_vi_mask_sensitive_cell($hdr, $cell);

			echo '<tr>';
			echo '<td>' . esc_html((string) ($r['row'] ?? '')) . '</td>';
			echo '<td>' . esc_html((string) ($r['level'] ?? '')) . '</td>';
			echo '<td>' . esc_html((string) ($r['action'] ?? '')) . '</td>';
			echo '<td>' . esc_html((string) ($r['identity'] ?? '')) . '</td>';
			echo '<td>' . esc_html((string) ($r['field'] ?? '')) . '</td>';
			echo '<td>' . esc_html($hdr) . '</td>';
			echo '<td>' . esc_html($cell) . '</td>';
			echo '<td>' . esc_html((string) ($r['message'] ?? '')) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}
}

// ============================================================
// Raw CSV Preview renderer (spreadsheet view)
// ============================================================

function vms_dt_vi_render_raw_preview(array $raw_preview, int $limit = 50): void
{
	$headers = isset($raw_preview['headers']) && is_array($raw_preview['headers']) ? $raw_preview['headers'] : [];
	$rows_in = isset($raw_preview['rows']) && is_array($raw_preview['rows']) ? $raw_preview['rows'] : [];
	$total   = isset($raw_preview['total']) ? (int) $raw_preview['total'] : 0;
	$cap     = isset($raw_preview['cap']) ? (int) $raw_preview['cap'] : 0;

	$editable_headers = vms_dt_vi_get_override_allowed_headers($headers);
	$editable_set = [];
	foreach ($editable_headers as $eh) {
		$editable_set[(string) $eh] = true;
	}

	if (empty($headers) || empty($rows_in)) {
		return;
	}

	if ($limit <= 0) {
		$limit = 50;
	}
	if ($limit > 500) {
		$limit = 500;
	}

	$shown_rows = array_slice($rows_in, 0, $limit);
	$shown = count($shown_rows);

	echo '<details class="vms-dt-raw-preview" open style="margin:16px 0;">';
	echo '<summary style="cursor:pointer;font-weight:600;">' . esc_html('CSV Preview (looks like your sheet)') . '</summary>';
	echo '<p style="margin:10px 0 0 0;color:#555;">' . esc_html('This is the literal parsed CSV before mapping or validation.') . '</p>';

	$cap_note = '';
	if ($cap > 0) {
		$cap_note = ' (engine cap ' . (int) $cap . ')';
	}

	echo '<p style="margin:6px 0 10px 0;color:#555;">' . esc_html('Showing ' . (int) $shown . ' row(s) (limit ' . (int) $limit . ') out of ' . (int) $total . $cap_note . '.') . '</p>';

	if (!empty($editable_headers)) {
		$labels = array_slice(array_values($editable_headers), 0, 8);
		$more = (count($editable_headers) > 8) ? '…' : '';
		echo '<p style="margin:6px 0 10px 0;color:#555;">' . esc_html('Editable columns (override layer): ' . implode(', ', $labels) . $more) . '</p>';
	} else {
		echo '<p style="margin:6px 0 10px 0;color:#555;">' . esc_html('Editable columns (override layer): (none). Use filter vms_dt_vi_override_allowed_columns to allow specific headers.') . '</p>';
	}

	echo '<div style="display:flex; gap:10px; flex-wrap:wrap; margin:10px 0;">';
	echo '<button type="button" class="button" id="vms_dt_vi_select_all">Select all shown</button>';
	echo '<button type="button" class="button" id="vms_dt_vi_select_none">Select none</button>';
	echo '</div>';

	echo '<div class="vms-dt-csv-sheet" style="overflow:auto;border:1px solid #dcdcde;background:#fff;border-radius:10px;">';
	echo '<table class="widefat striped" style="margin:0; table-layout:fixed; min-width:900px;">';

	echo '<thead style="position:sticky; top:0; z-index:2; background:#fff;">';
	echo '<tr>';
	echo '<th style="width:56px;">' . esc_html('Include') . '</th>';
	echo '<th style="width:70px;">' . esc_html('Row') . '</th>';

	foreach ($headers as $h) {
		$h = trim((string) $h);
		if ($h === '') {
			$h = '(blank)';
		}
		echo '<th style="width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' . esc_html($h) . '</th>';
	}

	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';

	foreach ($shown_rows as $row) {
		if (!is_array($row)) {
			continue;
		}

		$rn = isset($row['row_number']) ? (int) $row['row_number'] : 0;
		$data = isset($row['data']) && is_array($row['data']) ? $row['data'] : [];

		if ($rn <= 0) {
			continue;
		}

		echo '<tr data-row-number="' . esc_attr((string) $rn) . '">';
		echo '<td><input type="checkbox" class="vms-dt-include-row" value="' . esc_attr((string) $rn) . '" checked></td>';
		echo '<td><code>' . esc_html((string) $rn) . '</code></td>';

		foreach ($headers as $header_key) {
			$header_key = (string) $header_key;
			$raw_val = isset($data[$header_key]) ? (string) $data[$header_key] : '';

			if (vms_dt_vi_is_sensitive_header($header_key)) {
				$masked = vms_dt_vi_mask_sensitive_cell($header_key, $raw_val);
				echo '<td style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' . esc_html($masked) . '</td>';
				continue;
			}


			// Only whitelisted headers are editable/clickable.
			$can_edit = isset($editable_set[$header_key]);
			echo '<td style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">';
			if ($can_edit) {
				echo '<span class="vms-vi-cell" tabindex="0" data-row="' . esc_attr((string) $rn) . '" data-col="' . esc_attr($header_key) . '" data-original="' . esc_attr($raw_val) . '">';
				echo esc_html($raw_val);
				echo '</span>';
			} else {
				echo esc_html($raw_val);
			}
			echo '</td>';
		}

		echo '</tr>';
	}

	echo '</tbody></table></div>';

	echo '<p style="margin:10px 0 0 0;color:#555;">' . esc_html('Unchecked rows will be excluded from commit.') . '</p>';
	echo '</details>';
}

if (!function_exists('vms_vendor_import_render_report')) {
	function vms_vendor_import_render_report(array $report, string $title): void
	{
		vms_dt_vendor_import_render_report($report, $title);
	}
}
