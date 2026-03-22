<?php
/**
 * Rate Notice
 *
 * Prompts administrators to leave a review on WordPress.org after 7 days.
 *
 * @package page-categorizer
 * @since   1.5.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Pagecate_Rate_Notice
 *
 * Handles display logic, asset enqueuing, and AJAX for the rate notice.
 */
class Pagecate_Rate_Notice {

	/** WordPress.org review URL. */
	const REVIEW_URL = 'https://wordpress.org/support/plugin/page-categorizer/reviews/#new-post';

	/** wp_options key that stores the notice state. */
	const OPTION_KEY = 'pagecate_notice_state';

	/** Number of days before the notice first appears. */
	const DELAY_DAYS = 7;

	/** Number of days to snooze when the user clicks "Maybe later". */
	const SNOOZE_DAYS = 14;

	/**
	 * Boot: register all hooks.
	 *
	 * @since 1.5.0
	 */
	public static function init() {
		add_action( 'admin_notices',                        array( __CLASS__, 'render' ) );
		add_action( 'admin_enqueue_scripts',                array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_pagecate_notice_action',       array( __CLASS__, 'handle_ajax' ) );
	}

	/**
	 * Record the activation timestamp (called from the plugin's activation hook).
	 *
	 * Only writes if the option has never been set, so re-activations
	 * don't reset the countdown.
	 *
	 * @since 1.5.0
	 */
	public static function on_activation() {
		if ( false === get_option( self::OPTION_KEY ) ) {
			add_option( self::OPTION_KEY, time(), '', false );
		}
	}

	// =========================================================================
	// Display logic
	// =========================================================================

	/**
	 * Whether the notice should be rendered for the current request.
	 *
	 * @since  1.5.0
	 * @return bool
	 */
	private static function should_show() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$state = get_option( self::OPTION_KEY );

		// Option missing — plugin was active before v1.5.0. Seed now.
		if ( false === $state ) {
			add_option( self::OPTION_KEY, time(), '', false );
			return false;
		}

		// Permanently dismissed.
		if ( 'done' === $state ) {
			return false;
		}

		// Still within snooze window.
		if ( is_numeric( $state ) && time() < (int) $state ) {
			return false;
		}

		// Show once the delay has passed.
		if ( is_numeric( $state ) && ( time() - (int) $state ) >= ( self::DELAY_DAYS * DAY_IN_SECONDS ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Enqueue the notice stylesheet and script (only when notice will show).
	 *
	 * @since 1.5.0
	 */
	public static function enqueue_assets() {
		if ( ! self::should_show() ) {
			return;
		}

		$base = plugin_dir_url( dirname( __FILE__ ) );
		$path = plugin_dir_path( dirname( __FILE__ ) );

		wp_enqueue_style(
			'pagecate-rate-notice',
			$base . 'assets/css/rate-notice.css',
			array(),
			filemtime( $path . 'assets/css/rate-notice.css' )
		);

		wp_enqueue_script(
			'pagecate-rate-notice',
			$base . 'assets/js/rate-notice.js',
			array( 'jquery' ),
			filemtime( $path . 'assets/js/rate-notice.js' ),
			true
		);

		wp_localize_script(
			'pagecate-rate-notice',
			'pagecateNotice',
			array(
				'nonce'     => wp_create_nonce( 'pagecate_notice_nonce' ),
				'reviewUrl' => self::REVIEW_URL,
			)
		);
	}

	/**
	 * Output the notice HTML.
	 *
	 * @since 1.5.0
	 */
	public static function render() {
		if ( ! self::should_show() ) {
			return;
		}
		?>
		<div id="pagecate-rate-notice" class="pagecate-notice">

			<!-- Illustration -->
			<div class="pagecate-notice-illustration" aria-hidden="true">
				<div class="pagecate-circle">⭐</div>
				<span class="pagecate-accent pagecate-accent-1"></span>
				<span class="pagecate-accent pagecate-accent-2"></span>
				<span class="pagecate-accent pagecate-accent-3"></span>
			</div>

			<!-- Body -->
			<div class="pagecate-notice-body">
				<p class="pagecate-notice-heading">
					<?php esc_html_e( 'Enjoying Page Categorizer?', 'page-categorizer' ); ?>
				</p>
				<p class="pagecate-notice-text">
					<?php
					printf(
						/* translators: %s: author first name */
						esc_html__( 'You\'ve been using it for a week — thank you! A quick review on WordPress.org helps others discover the plugin and takes less than a minute. It would mean a lot to %s. 🙏', 'page-categorizer' ),
						'<strong>Patrick</strong>'
					);
					?>
				</p>

				<div class="pagecate-notice-actions">
					<a id="pagecate-btn-review"
					   href="<?php echo esc_url( self::REVIEW_URL ); ?>"
					   target="_blank"
					   rel="noopener noreferrer"
					   class="pagecate-btn-primary">
						⭐ <?php esc_html_e( 'Rate the plugin', 'page-categorizer' ); ?>
					</a>

					<button id="pagecate-btn-later" type="button" class="pagecate-btn-link">
						<?php esc_html_e( 'Maybe later', 'page-categorizer' ); ?>
					</button>

					<button id="pagecate-btn-done" type="button" class="pagecate-btn-link">
						<?php esc_html_e( 'I already did', 'page-categorizer' ); ?>
					</button>
				</div>
			</div>

			<!-- Dismiss -->
			<button id="pagecate-notice-close" class="pagecate-notice-close" type="button"
				aria-label="<?php esc_attr_e( 'Dismiss this notice', 'page-categorizer' ); ?>">
				&#x2715;
			</button>

		</div>
		<?php
	}

	// =========================================================================
	// AJAX handler
	// =========================================================================

	/**
	 * Process the AJAX request from the notice buttons.
	 *
	 * Expects $_POST['notice_action'] = 'done' | 'later' | 'review'.
	 *
	 * @since 1.5.0
	 */
	public static function handle_ajax() {
		check_ajax_referer( 'pagecate_notice_nonce', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1 );
		}

		$action = isset( $_POST['notice_action'] ) ? sanitize_key( $_POST['notice_action'] ) : '';

		if ( 'done' === $action || 'review' === $action ) {
			update_option( self::OPTION_KEY, 'done', false );
		} elseif ( 'later' === $action ) {
			update_option( self::OPTION_KEY, time() + ( self::SNOOZE_DAYS * DAY_IN_SECONDS ), false );
		}

		wp_die( 1 );
	}
}
