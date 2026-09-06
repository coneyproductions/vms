<?php
/**
 * WordPress integration: route, administrator UI, and secret rotation.
 */

namespace ConeyProductions\BackstageCalendarFeeds;

final class Plugin {
	const PROFILE_ID = 'dalene-band-availability';
	const QUERY_VAR  = 'bcf_feed_token';

	/** @var self|null */
	private static $instance;

	/** @var Secret_Store */
	private $secrets;

	/** @var Feed_Service */
	private $service;

	/** @var bool */
	private $registered_with_bvm = false;

	private function __construct() {
		$this->secrets = new Secret_Store();
		$this->service = new Feed_Service(
			new DRM_Calendar_Intake_Provider(),
			new Strict_Availability_Policy(),
			new Supersession_Resolver(),
			new Publication_Ledger(),
			new Duplicate_Detector(),
			new ICS_Formatter()
		);
	}

	/** Register runtime hooks once. */
	public static function boot() {
		if ( self::$instance ) {
			return;
		}
		self::$instance = new self();
		add_action( 'init', array( self::$instance, 'register_rewrite' ) );
		add_filter( 'query_vars', array( self::$instance, 'register_query_var' ) );
		add_action( 'template_redirect', array( self::$instance, 'serve_feed' ), 0 );
		add_action( 'vms_admin_register_pages', array( self::$instance, 'register_bvm_admin_page' ) );
		add_action( 'admin_menu', array( self::$instance, 'register_admin_menu' ), 99 );
		add_action( 'admin_post_bcf_regenerate_secret', array( self::$instance, 'handle_regenerate_secret' ) );
	}

	/** Create the first secret and flush only BCF rewrite state. */
	public static function activate() {
		self::boot();
		self::$instance->register_rewrite();
		self::$instance->secrets->get_or_create( self::PROFILE_ID );
		flush_rewrite_rules();
	}

	/** Remove only rewrite cache state; retain feed configuration/secrets. */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/** Register the stable capability route. */
	public function register_rewrite() {
		add_rewrite_rule(
			'^backstage-calendar-feeds/([A-Za-z0-9_-]{43})\.ics$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/** @param array<int,string> $vars Public query vars. @return array<int,string> */
	public function register_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/** Serve a theme-free calendar response, failing closed. */
	public function serve_feed() {
		$token = (string) get_query_var( self::QUERY_VAR );
		if ( '' === $token ) {
			return;
		}

		if ( ! $this->secrets->validate( self::PROFILE_ID, $token ) ) {
			$this->send_plain_error( 404, 'Not found.' );
		}
		$profile = bcf_get_profile( self::PROFILE_ID );
		$result  = is_array( $profile ) ? $this->service->build( $profile, null, true ) : new \WP_Error( 'bcf_profile_unavailable', 'Feed profile unavailable.' );
		if ( is_wp_error( $result ) ) {
			$this->send_plain_error( 503, 'Feed temporarily unavailable.' );
		}

		status_header( 200 );
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Cache-Control: private, max-age=300, must-revalidate' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex, nofollow', true );
		echo $result['ics']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- RFC 5545 formatter output.
		exit;
	}

	/** @param int $status HTTP status. @param string $message Non-secret safe message. */
	private function send_plain_error( $status, $message ) {
		status_header( (int) $status );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Cache-Control: no-store, max-age=0' );
		header( 'X-Robots-Tag: noindex, nofollow', true );
		echo (string) $message . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed safe strings only.
		exit;
	}

	/** Register Calendar Feeds with the canonical BVM page registry. */
	public function register_bvm_admin_page() {
		if ( $this->registered_with_bvm || ! function_exists( 'bvmgr_register_admin_page' ) ) {
			return;
		}

		$this->registered_with_bvm = bvmgr_register_admin_page(
			array(
				'id'          => 'backstage-calendar-feeds',
				'slug'        => 'backstage',
				'page_title'  => 'Calendar Feeds',
				'menu_title'  => 'Calendar Feeds',
				'capability'  => 'manage_options',
				'callback'    => array( $this, 'render_admin_page' ),
				'section'     => 'events_schedule',
				'order'       => 50,
				'source'      => 'backstage-calendar-feeds',
				'top_nav'     => true,
				'directory'   => true,
				'shell'       => true,
				'register'    => true,
				'description' => 'Secure read-only calendar and availability feeds.',
			)
		);
	}

	/** Register the standalone Backstage → Calendar Feeds fallback. */
	public function register_admin_menu() {
		if ( $this->registered_with_bvm ) {
			return;
		}

		add_menu_page( 'Backstage', 'Backstage', 'manage_options', 'backstage', array( $this, 'render_admin_page' ), 'dashicons-calendar-alt', 58 );
		add_submenu_page( 'backstage', 'Calendar Feeds', 'Calendar Feeds', 'manage_options', 'backstage', array( $this, 'render_admin_page' ) );
	}

	/** Rotate the capability token behind capability and nonce checks. */
	public function handle_regenerate_secret() {
		$nonce       = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		$can_manage  = current_user_can( 'manage_options' );
		$nonce_valid = false !== wp_verify_nonce( $nonce, 'bcf_regenerate_secret' );
		if ( ! Admin_Authorization::can_rotate_secret( $can_manage, $nonce_valid ) ) {
			wp_die( esc_html__( 'You are not allowed to rotate this feed secret.', 'backstage-calendar-feeds' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'bcf_regenerate_secret' );
		$result = $this->secrets->rotate( self::PROFILE_ID );
		$notice = is_wp_error( $result ) ? 'secret_error' : 'secret_rotated';
		wp_safe_redirect( add_query_arg( 'bcf_notice', $notice, admin_url( 'admin.php?page=backstage' ) ) );
		exit;
	}

	/** Render only policy-projected, privacy-safe diagnostics. */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to view calendar feeds.', 'backstage-calendar-feeds' ), '', array( 'response' => 403 ) );
		}

		if ( function_exists( 'bvmgr_admin_ui_render_shell' ) ) {
			bvmgr_admin_ui_render_shell(
				array(
					'title'         => __( 'Calendar Feeds', 'backstage-calendar-feeds' ),
					'subtitle'      => __( 'Secure read-only calendar and availability feeds.', 'backstage-calendar-feeds' ),
					'shell_id'      => 'bcf-admin-shell',
					'content_class' => 'bcf-wrap',
				),
				array( $this, 'render_admin_content' )
			);
			return;
		}
		?>
		<div class="wrap bcf-wrap">
			<h1><?php echo esc_html__( 'Calendar Feeds', 'backstage-calendar-feeds' ); ?></h1>
			<?php $this->render_admin_content(); ?>
		</div>
		<?php
	}

	/** Render only policy-projected, privacy-safe diagnostics within the selected shell. */
	public function render_admin_content() {
		$profile = bcf_get_profile( self::PROFILE_ID );
		$result  = is_array( $profile ) ? $this->service->build( $profile ) : new \WP_Error( 'bcf_profile_unavailable', 'Feed profile unavailable.' );
		$token   = $this->secrets->get_or_create( self::PROFILE_ID );
		$notice  = isset( $_GET['bcf_notice'] ) ? sanitize_key( wp_unslash( $_GET['bcf_notice'] ) ) : '';
		$preview = isset( $_GET['bcf_view'] ) && 'preview' === sanitize_key( wp_unslash( $_GET['bcf_view'] ) );
		?>
		<div class="notice notice-info inline"><p><strong>Read only:</strong> Backstage Calendar Feeds reads a registered provider contract and cannot modify upstream calendars or event systems.</p></div>
			<?php if ( 'secret_rotated' === $notice ) : ?><div class="notice notice-success inline"><p>Feed secret regenerated. Previous capability URLs no longer work.</p></div><?php endif; ?>
			<?php if ( 'secret_error' === $notice ) : ?><div class="notice notice-error inline"><p>Feed secret regeneration did not complete.</p></div><?php endif; ?>

			<h2><?php echo esc_html( (string) $profile['label'] ); ?></h2>
			<?php $this->render_summary( $profile, $result ); ?>
			<?php $this->render_actions( $token, $preview ); ?>
			<?php if ( $preview && ! is_wp_error( $result ) ) : $this->render_preview( $result['occurrences'], $result['supersession_diagnostics'], $result['publication_diagnostics'] ); endif; ?>
		<?php
	}

	/** @param array<string,mixed> $profile Profile. @param array<string,mixed>|\WP_Error $result Build result. */
	private function render_summary( $profile, $result ) {
		$healthy = ! is_wp_error( $result );
		$stats   = $healthy ? $result['stats'] : array_fill_keys( array( 'upcoming', 'privacy_sanitized', 'cancellations', 'source_missing', 'possible_duplicate_groups', 'superseded_occurrences_suppressed', 'supersession_targets_missing', 'supersession_targets_not_available', 'malformed_supersession_references', 'supersession_cycles', 'historical_cancellations_omitted', 'previously_emitted_cancellations_omitted', 'cancellation_tombstones_emitted', 'publication_history_entries', 'source_lineages_excluded' ), 0 );
		?>
		<table class="widefat striped" style="max-width:900px"><tbody>
			<tr><th>Status</th><td><?php echo esc_html( $healthy ? 'Active' : 'Unavailable' ); ?></td></tr>
			<tr><th>Provider</th><td><?php echo esc_html( (string) $profile['provider_label'] ); ?></td></tr>
			<tr><th>Provider health</th><td><?php echo esc_html( $healthy ? 'Available (contract 2)' : 'Unavailable — ' . $result->get_error_code() ); ?></td></tr>
			<tr><th>Policy</th><td><?php echo esc_html( (string) $profile['policy_label'] ); ?></td></tr>
			<tr><th>Format</th><td><?php echo esc_html( (string) $profile['format_label'] ); ?></td></tr>
			<tr><th>Feed health</th><td><?php echo esc_html( $healthy ? 'Available' : 'Unavailable' ); ?></td></tr>
			<tr><th>Upcoming feed occurrences</th><td><?php echo esc_html( number_format_i18n( $stats['upcoming'] ) ); ?></td></tr>
			<tr><th>Privacy-sanitized occurrences</th><td><?php echo esc_html( number_format_i18n( $stats['privacy_sanitized'] ) ); ?></td></tr>
			<tr><th>Cancellation VEVENTs emitted</th><td><?php echo esc_html( number_format_i18n( $stats['cancellations'] ) ); ?></td></tr>
			<tr><th>Historical cancellations omitted</th><td><?php echo esc_html( number_format_i18n( $stats['historical_cancellations_omitted'] ) ); ?></td></tr>
			<tr><th>Previously emitted cancellations removed</th><td><?php echo esc_html( number_format_i18n( $stats['previously_emitted_cancellations_omitted'] ) ); ?></td></tr>
			<tr><th>Cancellation tombstones emitted</th><td><?php echo esc_html( number_format_i18n( $stats['cancellation_tombstones_emitted'] ) ); ?></td></tr>
			<tr><th>Bounded publication-history entries</th><td><?php echo esc_html( number_format_i18n( $stats['publication_history_entries'] ) ); ?></td></tr>
			<tr><th>Source missing</th><td><?php echo esc_html( number_format_i18n( $stats['source_missing'] ) ); ?></td></tr>
			<tr><th>Disabled / archived source lineages excluded</th><td><?php echo esc_html( number_format_i18n( $stats['source_lineages_excluded'] ) ); ?></td></tr>
			<tr><th>Possible cross-source duplicates</th><td><?php echo esc_html( number_format_i18n( $stats['possible_duplicate_groups'] ) ); ?></td></tr>
			<tr><th>Explicitly superseded occurrences suppressed</th><td><?php echo esc_html( number_format_i18n( $stats['superseded_occurrences_suppressed'] ) ); ?></td></tr>
			<tr><th>Supersession targets missing</th><td><?php echo esc_html( number_format_i18n( $stats['supersession_targets_missing'] ) ); ?></td></tr>
			<tr><th>Supersession targets not projectable</th><td><?php echo esc_html( number_format_i18n( $stats['supersession_targets_not_available'] ) ); ?></td></tr>
			<tr><th>Malformed supersession references / cycles</th><td><?php echo esc_html( number_format_i18n( $stats['malformed_supersession_references'] + $stats['supersession_cycles'] ) ); ?></td></tr>
		</tbody></table>
		<?php
	}

	/** @param string|\WP_Error $token Current token. @param bool $preview Preview toggle. */
	private function render_actions( $token, $preview ) {
		$preview_url = add_query_arg( 'bcf_view', $preview ? 'summary' : 'preview', admin_url( 'admin.php?page=backstage' ) );
		?>
		<h2>Feed access</h2>
		<?php if ( is_wp_error( $token ) ) : ?>
			<p>Feed URL unavailable.</p>
		<?php else : $feed_url = home_url( '/backstage-calendar-feeds/' . rawurlencode( $token ) . '.ics' ); ?>
			<p><label for="bcf-feed-url" class="screen-reader-text">Private feed URL</label><input id="bcf-feed-url" type="text" readonly value="<?php echo esc_attr( $feed_url ); ?>" class="large-text code" style="max-width:760px"></p>
			<p><button type="button" class="button" onclick="navigator.clipboard.writeText(document.getElementById('bcf-feed-url').value)">Copy Feed URL</button> <a class="button" href="<?php echo esc_url( $feed_url ); ?>" target="_blank" rel="noopener noreferrer">View Feed</a> <a class="button" href="<?php echo esc_url( $preview_url ); ?>"><?php echo esc_html( $preview ? 'Hide Preview' : 'Preview Feed' ); ?></a></p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Regenerate the secret? Existing feed URLs will stop working.');">
			<input type="hidden" name="action" value="bcf_regenerate_secret">
			<?php wp_nonce_field( 'bcf_regenerate_secret' ); ?>
			<?php submit_button( 'Regenerate Secret', 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/** @param array<int,array<string,mixed>> $events Safe projected occurrences. @param array<int,array<string,mixed>> $supersession_diagnostics Safe relation diagnostics. @param array<int,array<string,mixed>> $publication_diagnostics Safe publication diagnostics. */
	private function render_preview( $events, $supersession_diagnostics, $publication_diagnostics ) {
		?>
		<h2>Projected feed preview</h2>
		<table class="widefat striped">
			<thead><tr><th>Date/time</th><th>Safe act</th><th>Feed summary</th><th>Feed state</th><th>Source classification</th><th>Review state</th><th>Source state</th><th>Inclusion reason</th><th>Privacy mode</th><th>Warning</th></tr></thead>
			<tbody>
			<?php if ( empty( $events ) ) : ?><tr><td colspan="10">No occurrences are currently available from the provider contract.</td></tr><?php endif; ?>
			<?php foreach ( $events as $event ) : ?>
				<tr>
					<td><?php echo esc_html( $this->format_event_time( $event ) ); ?></td>
					<td><?php echo esc_html( (string) $event['safe_act'] ); ?></td>
					<td><?php echo esc_html( (string) $event['summary'] ); ?></td>
					<td><?php echo esc_html( (string) $event['feed_state'] ); ?></td>
					<td><?php echo esc_html( (string) $event['source_classification'] ); ?></td>
					<td><?php echo esc_html( (string) $event['review_state'] ); ?></td>
					<td><?php echo esc_html( (string) $event['source_state'] ); ?></td>
					<td><?php echo esc_html( (string) $event['inclusion_reason'] ); ?></td>
					<td><?php echo esc_html( (string) $event['privacy_mode'] ); ?></td>
					<td><?php echo esc_html( '' !== (string) $event['warning'] ? (string) $event['warning'] : '—' ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<h2>Cancellation publication diagnostics</h2>
		<p>This availability snapshot removes explicit cancellations instead of publishing tombstones. Publication history distinguishes a prior BUSY transition from a historical cancellation that was never emitted BUSY.</p>
		<table class="widefat striped">
			<thead><tr><th>Date/time</th><th>Source</th><th>Policy</th><th>Safe act</th><th>Feed summary</th><th>Disposition</th><th>Reason</th></tr></thead>
			<tbody>
			<?php if ( empty( $publication_diagnostics ) ) : ?><tr><td colspan="7">No retained cancellation candidates are present.</td></tr><?php endif; ?>
			<?php foreach ( $publication_diagnostics as $diagnostic ) : ?>
				<tr>
					<td><?php echo esc_html( $this->format_event_time( $diagnostic ) ); ?></td>
					<td><?php echo esc_html( (string) $diagnostic['source_label'] ); ?></td>
					<td><?php echo esc_html( (string) $diagnostic['source_policy'] ); ?></td>
					<td><?php echo esc_html( (string) $diagnostic['safe_act'] ); ?></td>
					<td><?php echo esc_html( (string) $diagnostic['feed_summary'] ); ?></td>
					<td><?php echo esc_html( (string) $diagnostic['disposition'] ); ?></td>
					<td><?php echo esc_html( (string) $diagnostic['reason'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<h2>Explicit supersession diagnostics</h2>
		<table class="widefat striped">
			<thead><tr><th>Date/time</th><th>Predecessor source</th><th>Policy</th><th>Successor source</th><th>Disposition</th><th>Reason</th></tr></thead>
			<tbody>
			<?php if ( empty( $supersession_diagnostics ) ) : ?><tr><td colspan="6">No explicit supersession relations are present.</td></tr><?php endif; ?>
			<?php foreach ( $supersession_diagnostics as $diagnostic ) : ?>
				<tr>
					<td><?php echo esc_html( $this->format_event_time( $diagnostic ) ); ?></td>
					<td><?php echo esc_html( (string) $diagnostic['source_label'] ); ?></td>
					<td><?php echo esc_html( (string) $diagnostic['source_policy'] ); ?></td>
					<td><?php echo esc_html( (string) $diagnostic['successor_source_label'] ); ?></td>
					<td><?php echo esc_html( (string) $diagnostic['disposition'] ); ?></td>
					<td><?php echo esc_html( (string) $diagnostic['reason'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/** @param array<string,mixed> $event Safe event. @return string */
	private function format_event_time( $event ) {
		if ( ! empty( $event['all_day'] ) ) {
			return (string) $event['start']['value'] . ' (all day)';
		}
		$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'America/Chicago' );
		$format   = function_exists( 'get_option' ) ? get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) : 'M j, Y g:i a';
		return wp_date( $format, (int) $event['start']['timestamp'], $timezone ) . ' – ' . wp_date( $format, (int) $event['end']['timestamp'], $timezone );
	}
}
