<?php
/**
 * Admin settings page for WPMU WhatsApp.
 *
 * Tabs (URL-based, WordPress-native pattern):
 *   General   – phone, position, label
 *   Messages  – default message, service message, service URL prefix
 *   Schedule  – active days, active hours
 *   Visibility – hide on post types, hide on specific pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPMUWhatsApp_Admin {

	/** @var string */
	private $option_key;

	public function __construct() {
		$this->option_key = WPMU_WHATSAPP_OPTION_KEY;
	}

	public function init() {
		add_action( 'admin_menu',     array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init',     array( $this, 'register_settings' ) );
	}

	// ── Menu ──────────────────────────────────────────────────────────────────

	public function add_admin_menu() {
		add_options_page(
			__( 'WhatsApp', 'wpmu-whatsapp' ),
			__( 'WhatsApp', 'wpmu-whatsapp' ),
			'manage_options',
			'wpmu-whatsapp',
			array( $this, 'render_settings_page' )
		);
	}

	// ── Settings API ──────────────────────────────────────────────────────────

	public function register_settings() {
		register_setting(
			'wpmu_whatsapp_group',
			$this->option_key,
			array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) )
		);
	}

	public function get_defaults() {
		return array(
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
	}

	/**
	 * Sanitize callback.
	 *
	 * Starts from the currently saved values so partial tab saves never wipe
	 * fields that belong to other tabs.
	 *
	 * Checkbox groups (active_days, exclude_post_types, exclude_page_ids) use a
	 * hidden sentinel field per tab to distinguish "user left all unchecked"
	 * from "field not submitted at all".
	 */
	public function sanitize_settings( $input ) {
		$saved     = get_option( $this->option_key, array() );
		$defaults  = $this->get_defaults();
		$sanitized = wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );

		// ── Toggle (present on every tab) ────────────────────────────────────
		$sanitized['enabled'] = isset( $input['enabled'] ) ? 1 : 0;

		// ── General tab ───────────────────────────────────────────────────────
		if ( isset( $input['phone'] ) ) {
			// Strip everything except digits and leading +
			$sanitized['phone'] = preg_replace( '/[^0-9+]/', '', $input['phone'] );
		}
		if ( isset( $input['position'] ) ) {
			$sanitized['position'] = in_array( $input['position'], array( 'left', 'right' ), true )
				? $input['position']
				: 'right';
		}
		if ( isset( $input['label'] ) ) {
			$sanitized['label'] = sanitize_text_field( $input['label'] );
		}

		// ── Messages tab ──────────────────────────────────────────────────────
		if ( isset( $input['default_message'] ) ) {
			$sanitized['default_message'] = sanitize_textarea_field( $input['default_message'] );
		}

		// ── Schedule tab (sentinel: _schedule_submitted) ──────────────────────
		if ( isset( $input['_schedule_submitted'] ) ) {
			$valid_days = array( '1', '2', '3', '4', '5', '6', '7' );
			$sanitized['active_days'] = isset( $input['active_days'] )
				? array_values( array_intersect( (array) $input['active_days'], $valid_days ) )
				: array();

			$sanitized['time_from'] = isset( $input['time_from'] )
				? sanitize_text_field( $input['time_from'] )
				: '00:00';
			$sanitized['time_to'] = isset( $input['time_to'] )
				? sanitize_text_field( $input['time_to'] )
				: '23:59';
		}

		// ── Visibility tab (sentinel: _visibility_submitted) ──────────────────
		if ( isset( $input['_visibility_submitted'] ) ) {
			$sanitized['exclude_post_types'] = isset( $input['exclude_post_types'] )
				? array_values( array_map( 'sanitize_key', (array) $input['exclude_post_types'] ) )
				: array();

			if ( isset( $input['exclude_page_ids'] ) ) {
				$ids = array_map( 'intval', (array) $input['exclude_page_ids'] );
				$ids = array_values( array_filter( $ids ) );
				$sanitized['exclude_page_ids'] = $ids;
			} else {
				$sanitized['exclude_page_ids'] = array();
			}

			$valid_special = array( 'front_page', 'posts_page', 'archive', 'search', '404' );
			$sanitized['exclude_special'] = isset( $input['exclude_special'] )
				? array_values( array_intersect( (array) $input['exclude_special'], $valid_special ) )
				: array();
		}

		return $sanitized;
	}

	// ── Settings page ─────────────────────────────────────────────────────────

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$opts = wp_parse_args( get_option( $this->option_key, array() ), $this->get_defaults() );

		$valid_tabs = array( 'general', 'messages', 'schedule', 'visibility' );
		$active_tab = ( isset( $_GET['tab'] ) && in_array( sanitize_key( $_GET['tab'] ), $valid_tabs, true ) )
			? sanitize_key( $_GET['tab'] )
			: 'general';

		$base_url = admin_url( 'options-general.php?page=wpmu-whatsapp' );

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors( $this->option_key ); ?>

			<style>
			.wpmu-wa-toggle-section {
				display: flex;
				align-items: center;
				gap: 12px;
				margin: 16px 0 4px;
				padding: 12px 16px;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
			}
			.wpmu-wa-toggle-label {
				display: flex;
				align-items: center;
				gap: 8px;
				font-weight: 600;
				cursor: pointer;
				margin: 0;
			}
			.wpmu-wa-toggle-label input[type="checkbox"] {
				width: 1.1rem;
				height: 1.1rem;
				margin: 0;
				cursor: pointer;
			}
			.wpmu-wa-status-badge {
				display: inline-block;
				padding: 3px 10px;
				border-radius: 12px;
				font-size: 12px;
				font-weight: 600;
				letter-spacing: .04em;
				text-transform: uppercase;
			}
			.wpmu-wa-badge--active   { background: #d1fae5; color: #065f46; }
			.wpmu-wa-badge--inactive { background: #f0f0f1; color: #666; }
			#wpmu-wa-tab-nav { margin-top: 16px; }
			.wpmu-wa-tab-content {
			}
			</style>

			<form method="post" action="options.php">
				<?php settings_fields( 'wpmu_whatsapp_group' ); ?>

				<!-- ── Toggle (always visible above tabs) ───────────────── -->
				<div class="wpmu-wa-toggle-section">
					<label class="wpmu-wa-toggle-label" for="wpmu-wa-enabled">
						<input type="checkbox"
							id="wpmu-wa-enabled"
							name="<?php echo esc_attr( $this->option_key ); ?>[enabled]"
							value="1"
							<?php checked( 1, $opts['enabled'] ); ?> />
						<?php esc_html_e( 'Enable', 'wpmu-whatsapp' ); ?>
					</label>
					<span class="wpmu-wa-status-badge <?php echo $opts['enabled'] ? 'wpmu-wa-badge--active' : 'wpmu-wa-badge--inactive'; ?>">
						<?php echo $opts['enabled'] ? esc_html__( 'Active', 'wpmu-whatsapp' ) : esc_html__( 'Inactive', 'wpmu-whatsapp' ); ?>
					</span>
				</div>

				<!-- ── Tab navigation ───────────────────────────────────── -->
				<nav class="nav-tab-wrapper" id="wpmu-wa-tab-nav">
					<a href="<?php echo esc_url( $base_url . '&tab=general' ); ?>"
					   class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
						<?php esc_html_e( 'General', 'wpmu-whatsapp' ); ?>
					</a>
					<a href="<?php echo esc_url( $base_url . '&tab=messages' ); ?>"
					   class="nav-tab <?php echo 'messages' === $active_tab ? 'nav-tab-active' : ''; ?>">
						<?php esc_html_e( 'Messages', 'wpmu-whatsapp' ); ?>
					</a>
					<a href="<?php echo esc_url( $base_url . '&tab=schedule' ); ?>"
					   class="nav-tab <?php echo 'schedule' === $active_tab ? 'nav-tab-active' : ''; ?>">
						<?php esc_html_e( 'Schedule', 'wpmu-whatsapp' ); ?>
					</a>
					<a href="<?php echo esc_url( $base_url . '&tab=visibility' ); ?>"
					   class="nav-tab <?php echo 'visibility' === $active_tab ? 'nav-tab-active' : ''; ?>">
						<?php esc_html_e( 'Visibility', 'wpmu-whatsapp' ); ?>
					</a>
				</nav>

				<div class="wpmu-wa-tab-content">

				<!-- ── General ─────────────────────────────────────── -->
					<?php if ( 'general' === $active_tab ) : ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="wpmu-wa-phone">
									<?php esc_html_e( 'Phone Number', 'wpmu-whatsapp' ); ?>
									<span style="color:#d63638;" aria-hidden="true">*</span>
								</label>
							</th>
							<td>
								<input type="tel" id="wpmu-wa-phone"
									name="<?php echo esc_attr( $this->option_key ); ?>[phone]"
									value="<?php echo esc_attr( $opts['phone'] ); ?>"
									class="regular-text"
									placeholder="420123456789" />
								<p class="description">
									<?php esc_html_e( 'Full number with country code, digits only — e.g. 420123456789. Required: the button will not appear without a valid number.', 'wpmu-whatsapp' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Button Position', 'wpmu-whatsapp' ); ?></th>
							<td>
								<label style="margin-right:20px;">
									<input type="radio"
										name="<?php echo esc_attr( $this->option_key ); ?>[position]"
										value="right"
										<?php checked( 'right', $opts['position'] ); ?> />
									<?php esc_html_e( 'Bottom right', 'wpmu-whatsapp' ); ?>
								</label>
								<label>
									<input type="radio"
										name="<?php echo esc_attr( $this->option_key ); ?>[position]"
										value="left"
										<?php checked( 'left', $opts['position'] ); ?> />
									<?php esc_html_e( 'Bottom left', 'wpmu-whatsapp' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wpmu-wa-label"><?php esc_html_e( 'Button Label', 'wpmu-whatsapp' ); ?></label>
							</th>
							<td>
								<input type="text" id="wpmu-wa-label"
									name="<?php echo esc_attr( $this->option_key ); ?>[label]"
									value="<?php echo esc_attr( $opts['label'] ); ?>"
									class="regular-text" />
								<p class="description">
									<?php esc_html_e( 'Optional text shown next to the WhatsApp icon. Leave empty for an icon-only button.', 'wpmu-whatsapp' ); ?>
								</p>
							</td>
						</tr>
					</table>
					<?php endif; ?>

					<!-- ── Messages ────────────────────────────────────── -->
					<?php if ( 'messages' === $active_tab ) : ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="wpmu-wa-default-msg"><?php esc_html_e( 'Default Message', 'wpmu-whatsapp' ); ?></label>
							</th>
							<td>
								<textarea id="wpmu-wa-default-msg" rows="4"
									name="<?php echo esc_attr( $this->option_key ); ?>[default_message]"
									class="large-text"><?php echo esc_textarea( $opts['default_message'] ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Pre-filled message sent when a visitor clicks the button on a regular page.', 'wpmu-whatsapp' ); ?>
								</p>
							</td>
						</tr>
					</table>
					<?php endif; ?>

					<!-- ── Schedule ────────────────────────────────────── -->
					<?php if ( 'schedule' === $active_tab ) : ?>
					<?php
					$day_names = array(
						'1' => __( 'Monday',    'wpmu-whatsapp' ),
						'2' => __( 'Tuesday',   'wpmu-whatsapp' ),
						'3' => __( 'Wednesday', 'wpmu-whatsapp' ),
						'4' => __( 'Thursday',  'wpmu-whatsapp' ),
						'5' => __( 'Friday',    'wpmu-whatsapp' ),
						'6' => __( 'Saturday',  'wpmu-whatsapp' ),
						'7' => __( 'Sunday',    'wpmu-whatsapp' ),
					);
					?>
					<!-- Sentinel: lets sanitize_settings know this tab was submitted -->
					<input type="hidden" name="<?php echo esc_attr( $this->option_key ); ?>[_schedule_submitted]" value="1" />
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Active Days', 'wpmu-whatsapp' ); ?></th>
							<td>
								<?php foreach ( $day_names as $val => $day_label ) : ?>
								<label style="display:inline-block;margin-right:16px;margin-bottom:6px;">
									<input type="checkbox"
										name="<?php echo esc_attr( $this->option_key ); ?>[active_days][]"
										value="<?php echo esc_attr( $val ); ?>"
										<?php checked( in_array( (string) $val, array_map( 'strval', (array) $opts['active_days'] ), true ) ); ?> />
									<?php echo esc_html( $day_label ); ?>
								</label>
								<?php endforeach; ?>
								<p class="description">
									<?php esc_html_e( 'Button is shown only on the checked days within the time range below. Leave all unchecked to disable the schedule and always show the button.', 'wpmu-whatsapp' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Active Hours', 'wpmu-whatsapp' ); ?></th>
							<td>
								<label>
									<?php esc_html_e( 'From', 'wpmu-whatsapp' ); ?>
									<input type="time"
										name="<?php echo esc_attr( $this->option_key ); ?>[time_from]"
										value="<?php echo esc_attr( $opts['time_from'] ); ?>"
										style="margin:0 12px 0 6px;" />
								</label>
								<label>
									<?php esc_html_e( 'To', 'wpmu-whatsapp' ); ?>
									<input type="time"
										name="<?php echo esc_attr( $this->option_key ); ?>[time_to]"
										value="<?php echo esc_attr( $opts['time_to'] ); ?>"
										style="margin-left:6px;" />
								</label>
								<p class="description">
									<?php
									printf(
										/* translators: %s: WordPress timezone name */
										esc_html__( 'Button is shown only within this time range. Uses WordPress timezone: %s.', 'wpmu-whatsapp' ),
										'<strong>' . esc_html( wp_timezone_string() ) . '</strong>'
									);
									?>
								</p>
							</td>
						</tr>
					</table>
					<?php endif; ?>

					<!-- ── Visibility ──────────────────────────────────── -->
					<?php if ( 'visibility' === $active_tab ) : ?>
					<?php
					// All post types with an admin UI — includes CPTs from code, ACF, CPT UI, etc.
					$_internal_types = array(
						'attachment', 'revision', 'nav_menu_item', 'custom_css',
						'customize_changeset', 'oembed_cache', 'user_request',
						'wp_block', 'wp_template', 'wp_template_part',
						'wp_global_styles', 'wp_navigation', 'wp_font_face', 'wp_font_family',
					);
					$post_types = get_post_types( array( 'show_ui' => true ), 'objects' );
					foreach ( $_internal_types as $_it ) {
						unset( $post_types[ $_it ] );
					}

					$all_pages    = get_pages( array( 'sort_column' => 'post_title', 'sort_order' => 'ASC' ) );
					$excluded_ids = array_map( 'intval', (array) $opts['exclude_page_ids'] );

					$special_options = array(
						'front_page' => __( 'Front page / Homepage', 'wpmu-whatsapp' ),
						'posts_page' => __( 'Blog index (posts listing)', 'wpmu-whatsapp' ),
						'archive'    => __( 'Archive pages (categories, tags, dates, authors…)', 'wpmu-whatsapp' ),
						'search'     => __( 'Search results', 'wpmu-whatsapp' ),
						'404'        => __( '404 page', 'wpmu-whatsapp' ),
					);
					?>
					<!-- Sentinel: lets sanitize_settings know this tab was submitted -->
					<input type="hidden" name="<?php echo esc_attr( $this->option_key ); ?>[_visibility_submitted]" value="1" />
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Hide on Special Pages', 'wpmu-whatsapp' ); ?></th>
							<td>
								<?php foreach ( $special_options as $s_val => $s_label ) : ?>
								<label style="display:block;margin-bottom:8px;">
									<input type="checkbox"
										name="<?php echo esc_attr( $this->option_key ); ?>[exclude_special][]"
										value="<?php echo esc_attr( $s_val ); ?>"
										<?php checked( in_array( $s_val, (array) $opts['exclude_special'], true ) ); ?> />
									<?php echo esc_html( $s_label ); ?>
								</label>
								<?php endforeach; ?>
								<p class="description">
									<?php esc_html_e( 'Hide the button on these special page types regardless of post type settings.', 'wpmu-whatsapp' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Hide on Post Types', 'wpmu-whatsapp' ); ?></th>
							<td>
								<?php if ( $post_types ) : ?>
								<?php foreach ( $post_types as $pt ) : ?>
								<label style="display:block;margin-bottom:8px;">
									<input type="checkbox"
										name="<?php echo esc_attr( $this->option_key ); ?>[exclude_post_types][]"
										value="<?php echo esc_attr( $pt->name ); ?>"
										<?php checked( in_array( $pt->name, (array) $opts['exclude_post_types'], true ) ); ?> />
									<?php echo esc_html( $pt->labels->name ); ?>
									<span style="color:#666;font-size:12px;">(<?php echo esc_html( $pt->name ); ?>)</span>
								</label>
								<?php endforeach; ?>
								<?php else : ?>
								<p class="description"><?php esc_html_e( 'No post types found.', 'wpmu-whatsapp' ); ?></p>
								<?php endif; ?>
								<p class="description">
									<?php esc_html_e( 'Hide the button on any singular page of the checked post types. Includes all registered custom post types.', 'wpmu-whatsapp' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wpmu-wa-exclude-ids"><?php esc_html_e( 'Hide on Specific Posts / Pages', 'wpmu-whatsapp' ); ?></label>
							</th>
							<td>
								<?php if ( $all_pages ) : ?>
								<select id="wpmu-wa-exclude-ids"
									name="<?php echo esc_attr( $this->option_key ); ?>[exclude_page_ids][]"
									multiple
									style="height:180px;min-width:320px;">
									<?php foreach ( $all_pages as $page ) : ?>
									<option value="<?php echo esc_attr( $page->ID ); ?>"
										<?php echo in_array( (int) $page->ID, $excluded_ids, true ) ? 'selected' : ''; ?>>
										<?php echo esc_html( $page->post_title ); ?>
									</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Hold Ctrl (Windows) or Cmd (Mac) to select multiple. Hides the button on selected pages regardless of post type settings.', 'wpmu-whatsapp' ); ?>
								</p>
								<?php else : ?>
								<p class="description"><?php esc_html_e( 'No pages found.', 'wpmu-whatsapp' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					</table>
					<?php endif; ?>

				</div><!-- .wpmu-wa-tab-content -->

				<?php submit_button(); ?>
			</form>
		</div><!-- .wrap -->
		<?php
	}

}
