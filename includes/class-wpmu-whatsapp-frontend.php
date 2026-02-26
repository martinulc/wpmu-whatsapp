<?php
/**
 * Frontend output for WPMU WhatsApp.
 *
 * Hooks into wp_footer to render the fixed WhatsApp button.
 * All styles are inline or inside an inline <style> block —
 * zero external files, zero theme conflicts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPMUWhatsApp_Frontend {

	/** @var string */
	private $option_key;

	public function __construct() {
		$this->option_key = WPMU_WHATSAPP_OPTION_KEY;
	}

	public function init() {
		add_action( 'wp_footer', array( $this, 'render_button' ) );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function get_opts() {
		$defaults = array(
			'enabled'            => 0,
			'phone'              => '',
			'position'           => 'right',
			'label'              => '',
			'default_message'    => '',
			'active_days'        => array( '1', '2', '3', '4', '5' ),
			'time_from'          => '09:00',
			'time_to'            => '17:00',
			'exclude_post_types' => array(),
			'exclude_page_ids'   => array(),
			'exclude_special'    => array(),
		);
		return wp_parse_args( get_option( $this->option_key, array() ), $defaults );
	}

	/**
	 * Check whether the button should be shown based on the schedule.
	 * Uses the WordPress timezone. Returns true when within active day + time window.
	 */
	private function is_scheduled( $opts ) {
		// No days selected = schedule disabled, always show.
		if ( empty( $opts['active_days'] ) ) {
			return true;
		}

		try {
			$timezone = new DateTimeZone( wp_timezone_string() );
			$now      = new DateTime( 'now', $timezone );
		} catch ( Exception $e ) {
			// Fallback: show if timezone setup fails.
			return true;
		}

		$day     = $now->format( 'N' ); // 1 = Monday … 7 = Sunday
		$current = $now->format( 'H:i' );

		if ( ! in_array( $day, (array) $opts['active_days'], true ) ) {
			return false;
		}

		$from = $opts['time_from'] ? $opts['time_from'] : '00:00';
		$to   = $opts['time_to']   ? $opts['time_to']   : '23:59';

		return ( $current >= $from && $current <= $to );
	}

	/**
	 * Check whether the button should be shown on the current page.
	 */
	private function is_visible( $opts ) {
		$exclude_special = (array) $opts['exclude_special'];

		// ── Special page checks (run before is_singular) ──────────────────
		if ( in_array( 'front_page', $exclude_special, true ) && is_front_page() ) {
			return false;
		}
		if ( in_array( 'posts_page', $exclude_special, true ) && is_home() ) {
			return false;
		}
		if ( in_array( 'archive', $exclude_special, true ) && is_archive() ) {
			return false;
		}
		if ( in_array( 'search', $exclude_special, true ) && is_search() ) {
			return false;
		}
		if ( in_array( '404', $exclude_special, true ) && is_404() ) {
			return false;
		}

		// ── Singular-only checks ──────────────────────────────────────────
		if ( ! is_singular() ) {
			return true;
		}

		// Hide on excluded post types.
		if ( ! empty( $opts['exclude_post_types'] ) ) {
			$current_pt = get_post_type();
			if ( in_array( $current_pt, (array) $opts['exclude_post_types'], true ) ) {
				return false;
			}
		}

		// Hide on specific excluded page/post IDs.
		if ( ! empty( $opts['exclude_page_ids'] ) ) {
			$current_id = (int) get_the_ID();
			$excluded   = array_map( 'intval', (array) $opts['exclude_page_ids'] );
			if ( in_array( $current_id, $excluded, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Return the pre-filled WhatsApp message.
	 */
	private function get_message( $opts ) {
		return $opts['default_message'];
	}

	// ── Render ────────────────────────────────────────────────────────────────

	public function render_button() {
		$opts = $this->get_opts();

		// Guard checks — nothing rendered if any condition fails.
		if ( empty( $opts['enabled'] ) )         { return; }
		if ( empty( $opts['phone'] ) )           { return; }
		if ( ! $this->is_scheduled( $opts ) )    { return; }
		if ( ! $this->is_visible( $opts ) )      { return; }

		$phone   = $opts['phone'];
		$message = $this->get_message( $opts );

		$wa_url = 'https://wa.me/' . rawurlencode( $phone );
		if ( $message ) {
			$wa_url .= '?text=' . rawurlencode( $message );
		}

		$position  = ( 'left' === $opts['position'] ) ? 'left' : 'right';
		$label     = $opts['label'];
		$has_label = '' !== $label;

		// Horizontal positioning.
		$pos_css = ( 'left' === $position )
			? 'left:24px;right:auto;'
			: 'right:24px;left:auto;';

		// Ping dot position mirrors button corner.
		$ping_side_css = ( 'left' === $position )
			? 'left:3px;right:auto;'
			: 'right:3px;left:auto;';

		// Padding: compact when icon-only, wider when label present.
		$btn_padding = $has_label ? '13px 20px 13px 16px' : '15px';

		?>
		<div class="wpmu-wa-widget" style="position:fixed;bottom:24px;<?php echo esc_attr( $pos_css ); ?>z-index:99999;">

			<style>
			@keyframes wpmu-wa-ping {
				0%        { transform:scale(1); opacity:.85; }
				75%, 100% { transform:scale(2.4); opacity:0; }
			}
			.wpmu-wa-btn:hover,
			.wpmu-wa-btn:focus-visible {
				transform: scale(1.07) !important;
				box-shadow: 0 8px 24px rgba(0,0,0,.25) !important;
				outline: none;
			}
			@media (prefers-reduced-motion: reduce) {
				.wpmu-wa-ping { animation: none !important; }
				.wpmu-wa-btn:hover, .wpmu-wa-btn:focus-visible { transform: none !important; }
			}
			</style>

			<a class="wpmu-wa-btn"
			   href="<?php echo esc_url( $wa_url ); ?>"
			   target="_blank"
			   rel="noopener noreferrer"
			   aria-label="<?php esc_attr_e( 'Chat on WhatsApp', 'wpmu-whatsapp' ); ?>"
			   style="position:relative;display:inline-flex;align-items:center;gap:10px;background:#25d366;color:#fff;border-radius:50px;padding:<?php echo esc_attr( $btn_padding ); ?>;box-shadow:0 4px 14px rgba(0,0,0,.2);text-decoration:none;transition:transform .2s ease,box-shadow .2s ease;line-height:1;font-family:inherit;">

				<!-- Ping dot: static base + animated ring on top (two-element Tailwind-style ping) -->
				<span aria-hidden="true" style="position:absolute;top:0;<?php echo esc_attr( $ping_side_css ); ?>width:13px;height:13px;">
					<span class="wpmu-wa-ping" style="position:absolute;inset:0;background:#4ade80;border-radius:50%;animation:wpmu-wa-ping 2s cubic-bezier(0,0,.2,1) infinite;"></span>
					<span style="position:absolute;inset:0;background:#25d366;border:2px solid #fff;border-radius:50%;"></span>
				</span>

				<!-- WhatsApp SVG icon -->
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false" style="width:26px;height:26px;flex-shrink:0;">
					<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
				</svg>

				<?php if ( $has_label ) : ?>
				<span class="wpmu-wa-label" style="font-size:14px;font-weight:600;white-space:nowrap;line-height:1;">
					<?php echo esc_html( $label ); ?>
				</span>
				<?php endif; ?>

			</a>
		</div>
		<?php
	}
}
