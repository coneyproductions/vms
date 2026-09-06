<?php
if (!defined('ABSPATH')) {
	exit;
}

require_once VMS_DT_SERVICES_DIR . 'holidays-import/holidays-import.php';

function vms_dt_register_holidays_import_menu()
{
	$cap = defined('VMS_DT_CAP_IMPORT_VENDORS') ? VMS_DT_CAP_IMPORT_VENDORS : 'manage_options';

	add_submenu_page(
		'vms-data-tools',
		'Upload Holidays',
		'Upload Holidays',
		$cap,
		vms_dt_get_menu_slug_holidays_import(),
		'vms_dt_render_holidays_import_page'
	);
}

function vms_dt_render_holidays_import_page()
{
	if (!function_exists('vms_dt_current_user_can_import') || !vms_dt_current_user_can_import()) {
		wp_die('You do not have permission to access this page.');
	}

	$mode = isset($_POST['import_mode']) ? (string) $_POST['import_mode'] : (isset($_GET['import_mode']) ? (string) $_GET['import_mode'] : 'merge');
	$mode = vms_dt_holidays_import_mode_normalize($mode);

	$preview = null;
	$preview_storage_key = '';
	$preview_error = '';

	$commit_result = null;
	$commit_error = '';

	if (isset($_POST['vms_dt_holidays_preview'])) {
		check_admin_referer('vms_dt_holidays_import_preview', 'vms_dt_holidays_import_preview_nonce');

		if (empty($_FILES['csv_file']) || !is_array($_FILES['csv_file'])) {
			$preview_error = 'Please choose a CSV file.';
		} else {
			$file = $_FILES['csv_file'];
			$uploaded_name = isset($file['name']) ? (string) $file['name'] : '';

			$upload = wp_handle_upload($file, array('test_form' => false));
			if (!is_array($upload) || !empty($upload['error']) || empty($upload['file'])) {
				$preview_error = !empty($upload['error']) ? (string) $upload['error'] : 'Upload failed. Please try again.';
			} else {
				$parsed = vms_dt_holidays_import_parse_csv_file((string) $upload['file'], $mode);
				if (!$parsed['ok']) {
					$preview_error = $parsed['error'] !== '' ? (string) $parsed['error'] : 'Unable to parse CSV.';
				} else {
					$preview_storage_key = vms_dt_holidays_import_make_storage_key();

					$source = array(
						'file_name' => $uploaded_name,
						'mode' => $parsed['mode'],
						'headers' => isset($parsed['meta']['headers']) ? $parsed['meta']['headers'] : array(),
					);

					vms_dt_holidays_import_store_preview($preview_storage_key, $parsed, $source);

					$preview = $parsed;
				}
			}
		}
	}

	if (isset($_POST['vms_dt_holidays_commit'])) {
		check_admin_referer('vms_dt_holidays_import_commit', 'vms_dt_holidays_import_commit_nonce');

		$preview_storage_key = isset($_POST['storage_key']) ? (string) $_POST['storage_key'] : '';
		$loaded = $preview_storage_key !== '' ? vms_dt_holidays_import_load_preview($preview_storage_key) : null;

		if (!$loaded || empty($loaded['parsed']) || !is_array($loaded['parsed'])) {
			$commit_error = 'Commit token expired or invalid. Please preview again.';
		} else {
			$parsed = $loaded['parsed'];
			$source = isset($loaded['source']) && is_array($loaded['source']) ? $loaded['source'] : array();

			$confirmed_delete = !empty($_POST['confirm_delete']);

			$commit_result = vms_dt_holidays_import_commit_plan(
				isset($parsed['rows']) && is_array($parsed['rows']) ? $parsed['rows'] : array(),
				$source,
				isset($parsed['mode']) ? (string) $parsed['mode'] : 'merge',
				(bool) $confirmed_delete
			);

			if (!$commit_result['ok']) {
				$commit_error = $commit_result['error'] !== '' ? (string) $commit_result['error'] : 'Commit failed.';
				$preview = $parsed;
			} else {
				vms_dt_holidays_import_delete_preview($preview_storage_key);
			}
		}
	}

	$sample_url = wp_nonce_url(
		admin_url('admin-post.php?action=vms_dt_download_holidays_sample_csv'),
		'vms_dt_holidays_sample_csv'
	);

	?>
	<div class="wrap">
		<h1>Upload Holidays (CSV)</h1>

		<p>
			This tool imports into <code>wp_options → vms_holidays</code> per venue + date.
			Use Preview first, then Commit.
		</p>

		<p>
			<a class="button" href="<?php echo esc_url($sample_url); ?>">Download Sample CSV</a>
		</p>

		<p><strong>Venue matching:</strong> fill ONE of <code>venue_name</code>, <code>venue_slug</code>, or <code>venue_id</code>. <code>venue_name</code> is recommended for humans.</p>
		<p><strong>action:</strong> leave blank (or <code>upsert</code>) to add or update; set to <code>delete</code> to remove a holiday for that venue + date.</p>
		<p><strong>rules_json:</strong> optional advanced field. Most operators should leave it blank and use the <code>vendor_*</code> columns. If both are provided, <code>vendor_*</code> columns win.</p>

		<?php if ($preview_error !== '') : ?>
			<div class="notice notice-error"><p><?php echo esc_html($preview_error); ?></p></div>
		<?php endif; ?>

		<?php if ($commit_error !== '') : ?>
			<div class="notice notice-error"><p><?php echo esc_html($commit_error); ?></p></div>
		<?php endif; ?>

		<?php if (is_array($commit_result) && !empty($commit_result['ok'])) : ?>
			<div class="notice notice-success">
				<p>
					Committed.
					Added: <strong><?php echo (int) ($commit_result['counts']['added'] ?? 0); ?></strong>,
					Updated: <strong><?php echo (int) ($commit_result['counts']['updated'] ?? 0); ?></strong>,
					Deleted: <strong><?php echo (int) ($commit_result['counts']['deleted'] ?? 0); ?></strong>,
					No-op: <strong><?php echo (int) ($commit_result['counts']['noop'] ?? 0); ?></strong>,
					Errors: <strong><?php echo (int) ($commit_result['counts']['errors'] ?? 0); ?></strong>.
				</p>
			</div>
		<?php endif; ?>

		<h2>Preview</h2>
		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field('vms_dt_holidays_import_preview', 'vms_dt_holidays_import_preview_nonce'); ?>
			<table class="form-table">
				<tr>
					<th scope="row">Import mode</th>
					<td>
						<label style="margin-right:12px;">
							<input type="radio" name="import_mode" value="merge" <?php checked($mode, 'merge'); ?> />
							Merge (add/update/delete only)
						</label>
						<label>
							<input type="radio" name="import_mode" value="overwrite" <?php checked($mode, 'overwrite'); ?> />
							Overwrite (venues in CSV only; missing dates become deletes)
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="csv_file">CSV file</label></th>
					<td>
						<input type="file" name="csv_file" id="csv_file" accept=".csv" required />
					</td>
				</tr>
			</table>

			<p>
				<button type="submit" class="button button-primary" name="vms_dt_holidays_preview" value="1">Preview</button>
			</p>
		</form>

		<?php if (is_array($preview) && !empty($preview['ok'])) : ?>
			<hr />
			<h2>Preview Results</h2>

			<p>
				Mode: <strong><?php echo esc_html($preview['mode']); ?></strong>
			</p>

			<?php
				$counts = isset($preview['counts']) && is_array($preview['counts']) ? $preview['counts'] : array();
				$delete_count = (int) ($counts['DELETE'] ?? 0);
				$error_count = (int) ($counts['ERROR'] ?? 0);
				$has_destructive = !empty($preview['has_destructive']);
				$backup_key = isset($preview['backup_key']) ? (string) $preview['backup_key'] : '';
				$backup_filename = isset($preview['backup_filename']) ? (string) $preview['backup_filename'] : '';
			?>

			<ul>
				<li>ADD: <strong><?php echo (int) ($counts['ADD'] ?? 0); ?></strong></li>
				<li>UPDATE: <strong><?php echo (int) ($counts['UPDATE'] ?? 0); ?></strong></li>
				<li>DELETE: <strong><?php echo (int) ($counts['DELETE'] ?? 0); ?></strong></li>
				<li>NOOP: <strong><?php echo (int) ($counts['NOOP'] ?? 0); ?></strong></li>
				<li>ERROR: <strong><?php echo (int) ($counts['ERROR'] ?? 0); ?></strong></li>
			</ul>

			<?php if ($has_destructive && $backup_key !== '') : ?>
				<?php
					$backup_url = wp_nonce_url(
						admin_url('admin-post.php?action=vms_dt_download_holidays_backup_csv&k=' . urlencode($backup_key)),
						'vms_dt_holidays_backup_csv'
					);
				?>
				<p>
					<a class="button" href="<?php echo esc_url($backup_url); ?>">Download Backup CSV</a>
					<?php if ($backup_filename !== '') : ?>
						<span style="margin-left:8px; color:#555;"><?php echo esc_html($backup_filename); ?></span>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<?php if ($error_count === 0) : ?>
				<h3>Commit</h3>
				<form method="post">
					<?php wp_nonce_field('vms_dt_holidays_import_commit', 'vms_dt_holidays_import_commit_nonce'); ?>
					<input type="hidden" name="storage_key" value="<?php echo esc_attr($preview_storage_key); ?>" />

					<?php if ($delete_count > 0) : ?>
						<p>
							<label>
								<input type="checkbox" name="confirm_delete" value="1" />
								I understand this commit includes deletes.
							</label>
						</p>
					<?php endif; ?>

					<p>
						<button type="submit" class="button button-primary" name="vms_dt_holidays_commit" value="1">Commit</button>
					</p>
				</form>
			<?php else : ?>
				<div class="notice notice-warning"><p>Fix ERROR rows and preview again. Commit is disabled.</p></div>
			<?php endif; ?>

			<h3>Plan Table</h3>
			<div style="overflow:auto; max-width:100%;">
				<table class="widefat striped">
					<thead>
						<tr>
							<th>Row</th>
							<th>Plan</th>
							<th>Venue</th>
							<th>Date</th>
							<th>Name</th>
							<th>Status</th>
							<th>Vendor Structure</th>
							<th>Flat Fee</th>
							<th>Door Split %</th>
							<th>Template</th>
							<th>Action</th>
							<th>Auto</th>
							<th>Messages</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($preview['rows'] as $r) : ?>
							<?php
								$st = isset($r['status']) ? (string) $r['status'] : 'ERROR';
								$data = isset($r['data']) && is_array($r['data']) ? $r['data'] : array();
								$vendor_structure = isset($data['vendor_structure']) ? (string) $data['vendor_structure'] : '';
								$flat_fee = isset($data['vendor_flat_fee_amount']) ? (string) $data['vendor_flat_fee_amount'] : '';
								$door_pct = isset($data['vendor_door_split_percent']) ? (string) $data['vendor_door_split_percent'] : '';
								$template_id = isset($data['template_id']) ? (string) $data['template_id'] : '';
								$action = isset($r['action']) ? (string) $r['action'] : '';
								$is_auto = !empty($r['is_auto']);
								$messages = isset($r['messages']) && is_array($r['messages']) ? $r['messages'] : array();
							?>
							<tr>
								<td><?php echo esc_html(isset($r['rownum']) ? (string) $r['rownum'] : ''); ?></td>
								<td><strong><?php echo esc_html($st); ?></strong></td>
								<td><?php echo esc_html(isset($r['venue_title']) ? (string) $r['venue_title'] : ''); ?></td>
								<td><?php echo esc_html(isset($r['date_ymd']) ? (string) $r['date_ymd'] : ''); ?></td>
								<td><?php echo esc_html(isset($data['name']) ? (string) $data['name'] : ''); ?></td>
								<td><?php echo esc_html(isset($data['status']) ? (string) $data['status'] : ''); ?></td>
								<td><?php echo esc_html($vendor_structure); ?></td>
								<td><?php echo esc_html($flat_fee); ?></td>
								<td><?php echo esc_html($door_pct); ?></td>
								<td><?php echo esc_html($template_id); ?></td>
								<td><?php echo esc_html($action); ?></td>
								<td><?php echo $is_auto ? 'Yes' : ''; ?></td>
								<td>
									<?php if (!empty($messages)) : ?>
										<ul style="margin:0; padding-left:18px;">
											<?php foreach ($messages as $m) : ?>
												<li><?php echo esc_html((string) $m); ?></li>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
