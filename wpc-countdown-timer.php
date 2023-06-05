<?php
/*
Plugin Name: WPC Countdown Timer for WooCommerce
Plugin URI: https://wpclever.net/
Description: WPC Countdown Timer helps you display countdown timer in single product pages and shop page.
Version: 2.5.3
Author: WPClever
Author URI: https://wpclever.net
Text Domain: wpc-countdown-timer
Domain Path: /languages/
Requires at least: 4.0
Tested up to: 6.2
WC requires at least: 3.0
WC tested up to: 7.6
*/

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

! defined( 'WOOCT_VERSION' ) && define( 'WOOCT_VERSION', '2.5.3' );
! defined( 'WOOCT_FILE' ) && define( 'WOOCT_FILE', __FILE__ );
! defined( 'WOOCT_URI' ) && define( 'WOOCT_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WOOCT_DIR' ) && define( 'WOOCT_DIR', plugin_dir_path( __FILE__ ) );
! defined( 'WOOCT_SUPPORT' ) && define( 'WOOCT_SUPPORT', 'https://wpclever.net/support?utm_source=support&utm_medium=wooct&utm_campaign=wporg' );
! defined( 'WOOCT_REVIEWS' ) && define( 'WOOCT_REVIEWS', 'https://wordpress.org/support/plugin/wpc-countdown-timer/reviews/?filter=5' );
! defined( 'WOOCT_CHANGELOG' ) && define( 'WOOCT_CHANGELOG', 'https://wordpress.org/plugins/wpc-countdown-timer/#developers' );
! defined( 'WOOCT_DISCUSSION' ) && define( 'WOOCT_DISCUSSION', 'https://wordpress.org/support/plugin/wpc-countdown-timer' );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WOOCT_URI );

include 'includes/wpc-dashboard.php';
include 'includes/wpc-menu.php';
include 'includes/kit/wpc-kit.php';

if ( ! function_exists( 'wooct_init' ) ) {
	add_action( 'plugins_loaded', 'wooct_init', 11 );

	function wooct_init() {
		// load text-domain
		load_plugin_textdomain( 'wpc-countdown-timer', false, basename( __DIR__ ) . '/languages/' );

		if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
			add_action( 'admin_notices', 'wooct_notice_wc' );

			return;
		}

		if ( ! class_exists( 'WPCleverWooct' ) && class_exists( 'WC_Product' ) ) {
			class WPCleverWooct {
				protected static $instance = null;
				protected static $settings = [];
				protected static $localization = [];

				public static function instance() {
					if ( is_null( self::$instance ) ) {
						self::$instance = new self();
					}

					return self::$instance;
				}

				function __construct() {
					self::$settings     = (array) get_option( 'wooct_settings', [] );
					self::$localization = (array) get_option( 'wooct_localization', [] );

					// Settings
					add_action( 'admin_init', [ $this, 'register_settings' ] );
					add_action( 'admin_menu', [ $this, 'admin_menu' ] );

					// Enqueue scripts
					add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

					// Enqueue backend scripts
					add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

					// Add settings link
					add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
					add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

					// Product data tabs
					add_filter( 'woocommerce_product_data_tabs', [ $this, 'product_data_tabs' ] );
					add_action( 'woocommerce_product_data_panels', [ $this, 'product_data_panels' ] );
					add_action( 'woocommerce_process_product_meta', [ $this, 'process_product_meta' ] );

					// Variation
					add_action( 'woocommerce_product_after_variable_attributes', [
						$this,
						'variation_settings'
					], 99, 3 );
					add_filter( 'woocommerce_available_variation', [ $this, 'available_variation' ], 99, 3 );
					add_action( 'woocommerce_after_variations_table', [ $this, 'after_variations_table' ] );

					// Product columns
					add_filter( 'manage_edit-product_columns', [ $this, 'product_columns' ] );
					add_action( 'manage_product_posts_custom_column', [ $this, 'product_custom_column' ], 10, 2 );

					// Product class
					add_filter( 'woocommerce_post_class', [ $this, 'post_class' ], 99, 2 );

					// Countdown on archive
					$pos_archive = self::get_setting( 'position_archive', 'above_add_to_cart' );

					switch ( $pos_archive ) {
						case 'under_title':
							add_action( 'woocommerce_shop_loop_item_title', [ $this, 'show_countdown' ], 11 );
							break;
						case 'under_rating':
							add_action( 'woocommerce_after_shop_loop_item_title', [ $this, 'show_countdown' ], 6 );
							break;
						case 'under_price':
							add_action( 'woocommerce_after_shop_loop_item_title', [ $this, 'show_countdown' ], 11 );
							break;
						case 'above_add_to_cart':
							add_action( 'woocommerce_after_shop_loop_item', [ $this, 'show_countdown' ], 9 );
							break;
						case 'under_add_to_cart':
							add_action( 'woocommerce_after_shop_loop_item', [ $this, 'show_countdown' ], 11 );
							break;
					}

					// Countdown on single
					$pos_single = self::get_setting( 'position_single', '29' );

					if ( ! empty( $pos_single ) ) {
						add_action( 'woocommerce_single_product_summary', [
							$this,
							'show_countdown_single'
						], $pos_single );
					}

					// Shortcode
					add_shortcode( 'wooct_product', [ $this, 'shortcode_product' ] );

					// WPC Variation Duplicator
					add_action( 'wpcvd_duplicated', [ $this, 'duplicate_variation' ], 99, 2 );

					// HPOS compatibility
					add_action( 'before_woocommerce_init', function () {
						if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
							FeaturesUtil::declare_compatibility( 'custom_order_tables', WOOCT_FILE );
						}
					} );
				}

				function register_settings() {
					// settings
					register_setting( 'wooct_settings', 'wooct_settings' );

					// localization
					register_setting( 'wooct_localization', 'wooct_localization' );
				}

				function admin_menu() {
					add_submenu_page( 'wpclever', esc_html__( 'WPC Countdown Timer', 'wpc-countdown-timer' ), esc_html__( 'Countdown Timer', 'wpc-countdown-timer' ), 'manage_options', 'wpclever-wooct', [
						$this,
						'admin_menu_content'
					] );
				}

				function admin_menu_content() {
					$active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'settings';
					?>
					<div class="wpclever_settings_page wrap">
						<h1 class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Countdown Timer', 'wpc-countdown-timer' ) . ' ' . WOOCT_VERSION; ?></h1>
						<div class="wpclever_settings_page_desc about-text">
							<p>
								<?php printf( esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'wpc-countdown-timer' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
								<br/>
								<a href="<?php echo esc_url( WOOCT_REVIEWS ); ?>" target="_blank"><?php esc_html_e( 'Reviews', 'wpc-countdown-timer' ); ?></a> |
								<a href="<?php echo esc_url( WOOCT_CHANGELOG ); ?>" target="_blank"><?php esc_html_e( 'Changelog', 'wpc-countdown-timer' ); ?></a> |
								<a href="<?php echo esc_url( WOOCT_DISCUSSION ); ?>" target="_blank"><?php esc_html_e( 'Discussion', 'wpc-countdown-timer' ); ?></a>
							</p>
						</div>
						<?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) { ?>
							<div class="notice notice-success is-dismissible">
								<p><?php esc_html_e( 'Settings updated.', 'wpc-countdown-timer' ); ?></p>
							</div>
						<?php } ?>
						<div class="wpclever_settings_page_nav">
							<h2 class="nav-tab-wrapper">
								<a href="<?php echo admin_url( 'admin.php?page=wpclever-wooct&tab=how' ); ?>" class="<?php echo $active_tab === 'how' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>">
									<?php esc_html_e( 'How to use?', 'wpc-countdown-timer' ); ?>
								</a>
								<a href="<?php echo admin_url( 'admin.php?page=wpclever-wooct&tab=settings' ); ?>" class="<?php echo $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>">
									<?php esc_html_e( 'Settings', 'wpc-countdown-timer' ); ?>
								</a>
								<a href="<?php echo admin_url( 'admin.php?page=wpclever-wooct&tab=localization' ); ?>" class="<?php echo $active_tab === 'localization' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>">
									<?php esc_html_e( 'Localization', 'wpc-countdown-timer' ); ?>
								</a>
								<a href="<?php echo admin_url( 'admin.php?page=wpclever-wooct&tab=shortcode' ); ?>" class="<?php echo $active_tab === 'shortcode' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>">
									<?php esc_html_e( 'Shortcode', 'wpc-countdown-timer' ); ?>
								</a>
								<a href="<?php echo admin_url( 'admin.php?page=wpclever-wooct&tab=premium' ); ?>" class="<?php echo $active_tab === 'premium' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>" style="color: #c9356e">
									<?php esc_html_e( 'Premium Version', 'wpc-countdown-timer' ); ?>
								</a>
								<a href="<?php echo admin_url( 'admin.php?page=wpclever-kit' ); ?>" class="nav-tab">
									<?php esc_html_e( 'Essential Kit', 'wpc-countdown-timer' ); ?>
								</a>
							</h2>
						</div>
						<div class="wpclever_settings_page_content">
							<?php if ( $active_tab === 'how' ) { ?>
								<div class="wpclever_settings_page_content_text">
									<p>
										<?php esc_html_e( 'When adding/editing the product you can choose the "Countdown" tab then add your countdown timer.', 'wpc-countdown-timer' ); ?>
									</p>
								</div>
							<?php } elseif ( $active_tab === 'settings' ) {
								$pos_archive = self::get_setting( 'position_archive', 'above_add_to_cart' );
								$pos_single  = self::get_setting( 'position_single', '29' );
								?>
								<form method="post" action="options.php">
									<table class="form-table">
										<tr class="heading">
											<th colspan="2">
												<?php esc_html_e( 'General', 'wpc-countdown-timer' ); ?>
											</th>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Position on archive', 'wpc-countdown-timer' ); ?></th>
											<td>
												<select name="wooct_settings[position_archive]">
													<option value="under_title" <?php selected( $pos_archive, 'under_title' ); ?>><?php esc_html_e( 'Under title', 'wpc-countdown-timer' ); ?></option>
													<option value="under_rating" <?php selected( $pos_archive, 'under_rating' ); ?>><?php esc_html_e( 'Under rating', 'wpc-countdown-timer' ); ?></option>
													<option value="under_price" <?php selected( $pos_archive, 'under_price' ); ?>><?php esc_html_e( 'Under price', 'wpc-countdown-timer' ); ?></option>
													<option value="above_add_to_cart" <?php selected( $pos_archive, 'above_add_to_cart' ); ?>><?php esc_html_e( 'Above add to cart', 'wpc-countdown-timer' ); ?></option>
													<option value="under_add_to_cart" <?php selected( $pos_archive, 'under_add_to_cart' ); ?>><?php esc_html_e( 'Under add to cart', 'wpc-countdown-timer' ); ?></option>
													<option value="0" <?php selected( $pos_archive, '0' ); ?>><?php esc_html_e( 'None (hide it)', 'wpc-countdown-timer' ); ?></option>
												</select>
												<span class="description"><?php echo sprintf( esc_html__( 'You also can use shortcode %s to show the countdown timer for current product.', 'wpc-countdown-timer' ), '<code>[wooct_product]</code>' ); ?></span>
											</td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Position on single', 'wpc-countdown-timer' ); ?></th>
											<td>
												<select name="wooct_settings[position_single]">
													<option value="6" <?php selected( $pos_single, '6' ); ?>><?php esc_html_e( 'Under title', 'wpc-countdown-timer' ); ?></option>
													<option value="11" <?php selected( $pos_single, '11' ); ?>><?php esc_html_e( 'Under price & rating', 'wpc-countdown-timer' ); ?></option>
													<option value="21" <?php selected( $pos_single, '21' ); ?>><?php esc_html_e( 'Under excerpt', 'wpc-countdown-timer' ); ?></option>
													<option value="29" <?php selected( $pos_single, '29' ); ?>><?php esc_html_e( 'Above add to cart', 'wpc-countdown-timer' ); ?></option>
													<option value="31" <?php selected( $pos_single, '31' ); ?>><?php esc_html_e( 'Under add to cart', 'wpc-countdown-timer' ); ?></option>
													<option value="41" <?php selected( $pos_single, '41' ); ?>><?php esc_html_e( 'Under meta', 'wpc-countdown-timer' ); ?></option>
													<option value="51" <?php selected( $pos_single, '51' ); ?>><?php esc_html_e( 'Under sharing', 'wpc-countdown-timer' ); ?></option>
													<option value="0" <?php selected( $pos_single, '0' ); ?>><?php esc_html_e( 'None (hide it)', 'wpc-countdown-timer' ); ?></option>
												</select>
												<span class="description"><?php echo sprintf( esc_html__( 'You also can use shortcode %s to show the countdown timer for current product.', 'wpc-countdown-timer' ), '<code>[wooct_product]</code>' ); ?></span>
											</td>
										</tr>
										<tr>
											<th>
												<?php esc_html_e( 'Default above text', 'wpc-countdown-timer' ); ?>
											</th>
											<td>
												<input type="text" name="wooct_settings[text_above]" class="large-text" value="<?php echo stripslashes( self::get_setting( 'text_above' ) ); ?>"/>
											</td>
										</tr>
										<tr>
											<th>
												<?php esc_html_e( 'Default under text', 'wpc-countdown-timer' ); ?>
											</th>
											<td>
												<input type="text" name="wooct_settings[text_under]" class="large-text" value="<?php echo stripslashes( self::get_setting( 'text_under' ) ); ?>"/>
											</td>
										</tr>
										<tr>
											<th>
												<?php esc_html_e( 'Default ended text', 'wpc-countdown-timer' ); ?>
											</th>
											<td>
												<input type="text" name="wooct_settings[text_ended]" class="large-text" value="<?php echo stripslashes( self::get_setting( 'text_ended' ) ); ?>"/>
											</td>
										</tr>
										<tr class="submit">
											<th colspan="2">
												<?php settings_fields( 'wooct_settings' ); ?><?php submit_button(); ?>
											</th>
										</tr>
									</table>
								</form>
							<?php } elseif ( $active_tab === 'localization' ) {
								$zero_plural = self::localization( 'zero_plural', 'yes' );
								?>
								<form method="post" action="options.php">
									<table class="form-table">
										<tr class="heading">
											<th scope="row"><?php esc_html_e( 'Localization', 'wpc-countdown-timer' ); ?></th>
											<td>
												<?php esc_html_e( 'Leave blank to use the default text and its equivalent translation in multiple languages.', 'wpc-countdown-timer' ); ?>
											</td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Zero is plural?', 'wpc-countdown-timer' ); ?></th>
											<td>
												<select name="wooct_localization[zero_plural]">
													<option value="yes" <?php selected( $zero_plural, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-countdown-timer' ); ?></option>
													<option value="no" <?php selected( $zero_plural, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-countdown-timer' ); ?></option>
												</select>
											</td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Day', 'wpc-countdown-timer' ); ?></th>
											<td>
												<input type="text" name="wooct_localization[day]" placeholder="<?php esc_attr_e( 'Day', 'wpc-countdown-timer' ); ?>" value="<?php echo esc_attr( self::localization( 'day' ) ); ?>"/>
											</td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Days', 'wpc-countdown-timer' ); ?></th>
											<td>
												<input type="text" name="wooct_localization[days]" placeholder="<?php esc_attr_e( 'Days', 'wpc-countdown-timer' ); ?>" value="<?php echo esc_attr( self::localization( 'days' ) ); ?>"/>
											</td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Hour', 'wpc-countdown-timer' ); ?></th>
											<td>
												<input type="text" name="wooct_localization[hour]" placeholder="<?php esc_attr_e( 'Hour', 'wpc-countdown-timer' ); ?>" value="<?php echo esc_attr( self::localization( 'hour' ) ); ?>"/>
											</td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Hours', 'wpc-countdown-timer' ); ?></th>
											<td>
												<input type="text" name="wooct_localization[hours]" placeholder="<?php esc_attr_e( 'Hours', 'wpc-countdown-timer' ); ?>" value="<?php echo esc_attr( self::localization( 'hours' ) ); ?>"/>
											</td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Minute', 'wpc-countdown-timer' ); ?></th>
											<td>
												<input type="text" name="wooct_localization[minute]" placeholder="<?php esc_attr_e( 'Minute', 'wpc-countdown-timer' ); ?>" value="<?php echo esc_attr( self::localization( 'minute' ) ); ?>"/>
											</td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Minutes', 'wpc-countdown-timer' ); ?></th>
											<td>
												<input type="text" name="wooct_localization[minutes]" placeholder="<?php esc_attr_e( 'Minutes', 'wpc-countdown-timer' ); ?>" value="<?php echo esc_attr( self::localization( 'minutes' ) ); ?>"/>
											</td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Second', 'wpc-countdown-timer' ); ?></th>
											<td>
												<input type="text" name="wooct_localization[second]" placeholder="<?php esc_attr_e( 'Second', 'wpc-countdown-timer' ); ?>" value="<?php echo esc_attr( self::localization( 'second' ) ); ?>"/>
											</td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Seconds', 'wpc-countdown-timer' ); ?></th>
											<td>
												<input type="text" name="wooct_localization[seconds]" placeholder="<?php esc_attr_e( 'Seconds', 'wpc-countdown-timer' ); ?>" value="<?php echo esc_attr( self::localization( 'seconds' ) ); ?>"/>
											</td>
										</tr>
										<tr class="submit">
											<th colspan="2">
												<?php settings_fields( 'wooct_localization' ); ?><?php submit_button(); ?>
											</th>
										</tr>
									</table>
								</form>
							<?php } elseif ( $active_tab === 'shortcode' ) { ?>
								<table class="form-table wooct_shortcode_builder">
									<tr class="heading">
										<th><?php esc_html_e( 'Shortcode', 'wpc-countdown-timer' ); ?></th>
										<td>
											<textarea class="wooct_shortcode" style="width: 100%; height: 120px" readonly><?php esc_html_e( 'Configure below options to build your shortcode :)', 'wpc-countdown-timer' ); ?></textarea>
											<p class="description" style="color: #c9356e">
												* This feature only available on Premium Version. Click
												<a href="https://wpclever.net/downloads/wpc-countdown-timer?utm_source=pro&utm_medium=wooct&utm_campaign=wporg" target="_blank">here</a> to buy, just $29!
											</p>
										</td>
									</tr>
									<?php self::configure_form(); ?>
								</table>
							<?php } elseif ( $active_tab == 'premium' ) { ?>
								<div class="wpclever_settings_page_content_text">
									<p>
										Get the Premium Version just $29!
										<a href="https://wpclever.net/downloads/wpc-countdown-timer?utm_source=pro&utm_medium=wooct&utm_campaign=wporg" target="_blank">https://wpclever.net/downloads/wpc-countdown-timer</a>
									</p>
									<p><strong>Extra features for Premium Version:</strong></p>
									<ul style="margin-bottom: 0">
										<li>- Build your custom countdown timer to place everywhere you want by using shortcode builder.</li>
										<li>- Configure countdown timer on a variation basis.</li>
										<li>- Get the lifetime update & premium support.</li>
									</ul>
								</div>
							<?php } ?>
						</div>
					</div>
					<?php
				}

				function configure_form( $post_id = 0, $is_variation = false ) {
					$active        = get_post_meta( $post_id, 'wooct_active', true ) ?: 'no';
					$style         = get_post_meta( $post_id, 'wooct_style', true ) ?: apply_filters( 'wooct_default_style', '01' );
					$time          = get_post_meta( $post_id, 'wooct_time', true ) ?: 'custom';
					$time_start    = get_post_meta( $post_id, 'wooct_time_start', true ) ?: '';
					$time_end      = get_post_meta( $post_id, 'wooct_time_end', true ) ?: '';
					$text_above    = get_post_meta( $post_id, 'wooct_text_above', true ) ?: '';
					$text_under    = get_post_meta( $post_id, 'wooct_text_under', true ) ?: '';
					$text_ended    = get_post_meta( $post_id, 'wooct_text_ended', true ) ?: '';
					$default_style = apply_filters( 'wooct_default_style', '01' );
					$styles        = apply_filters( 'wooct_styles', [
						'01' => esc_html__( 'Style 01 (flat)', 'wpc-countdown-timer' ),
						'02' => esc_html__( 'Style 02 (square)', 'wpc-countdown-timer' ),
						'03' => esc_html__( 'Style 03 (rounded)', 'wpc-countdown-timer' ),
						'04' => esc_html__( 'Style 04 (light flipper)', 'wpc-countdown-timer' ),
						'05' => esc_html__( 'Style 05 (dark flipper)', 'wpc-countdown-timer' ),
						'06' => esc_html__( 'Style 06 (rounded)', 'wpc-countdown-timer' ),
					] );

					if ( empty( $style ) ) {
						$style = $default_style;
					}

					if ( $is_variation ) {
						$name = '_v[' . $post_id . ']';
					} else {
						$name = '';
					}

					if ( $is_variation ) {
						?>
						<tr>
							<td colspan="2">
								<p class="description" style="color: #c9356e">
									* This feature only available on Premium Version. Click
									<a href="https://wpclever.net/downloads/wpc-countdown-timer?utm_source=pro&utm_medium=wooct&utm_campaign=wporg" target="_blank">here</a> to buy, just $29!
								</p>
							</td>
						</tr>
						<?php
					}

					if ( $post_id ) { ?>
						<tr>
							<th><?php esc_html_e( 'Active', 'wpc-countdown-timer' ); ?></th>
							<td>
								<select name="<?php echo esc_attr( 'wooct_active' . $name ); ?>">
									<option value="yes" <?php selected( $active, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-countdown-timer' ); ?></option>
									<option value="no" <?php selected( $active, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-countdown-timer' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Time', 'wpc-countdown-timer' ); ?></th>
							<td>
								<select name="<?php echo esc_attr( 'wooct_time' . $name ); ?>" class="wooct_time">
									<option value="custom" <?php selected( $time, 'custom' ); ?>><?php esc_html_e( 'Custom', 'wpc-countdown-timer' ); ?></option>
									<option value="sale" <?php selected( $time, 'sale' ); ?>><?php esc_html_e( 'Sale price dates', 'wpc-countdown-timer' ); ?></option>
								</select>
								<span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'If you choose sale price dates, please check your scheduled sale price in the General tab.', 'wpc-countdown-timer' ); ?>"></span>
							</td>
						</tr>
					<?php } ?>
					<tr class="wooct_show_if_custom">
						<th><?php esc_html_e( 'Start time', 'wpc-countdown-timer' ); ?></th>
						<td>
							<input name="<?php echo esc_attr( 'wooct_time_start' . $name ); ?>" value="<?php echo $time_start; ?>" class="wooct_time_start wooct_date_time wooct_date_time_input wooct_picker" type="text" readonly="readonly"/>
						</td>
					</tr>
					<tr class="wooct_show_if_custom">
						<th><?php esc_html_e( 'End time', 'wpc-countdown-timer' ); ?></th>
						<td>
							<input name="<?php echo esc_attr( 'wooct_time_end' . $name ); ?>" value="<?php echo $time_end; ?>" class="wooct_time_end wooct_date_time wooct_date_time_input wooct_picker" type="text" readonly="readonly"/>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Above text', 'wpc-countdown-timer' ); ?></th>
						<td>
							<input name="<?php echo esc_attr( 'wooct_text_above' . $name ); ?>" class="wooct_text_above" type="text" style="width: 100%" value="<?php echo stripslashes( $text_above ); ?>"/>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Under text', 'wpc-countdown-timer' ); ?></th>
						<td>
							<input name="<?php echo esc_attr( 'wooct_text_under' . $name ); ?>" class="wooct_text_under" type="text" style="width: 100%" value="<?php echo stripslashes( $text_under ); ?>"/>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Ended text', 'wpc-countdown-timer' ); ?></th>
						<td>
							<input name="<?php echo esc_attr( 'wooct_text_ended' . $name ); ?>" class="wooct_text_ended" type="text" style="width: 100%" value="<?php echo stripslashes( $text_ended ); ?>"/>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Style', 'wpc-countdown-timer' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( 'wooct_style' . $name ); ?>" class="wooct_style">
								<?php foreach ( $styles as $k => $s ) {
									echo '<option value="' . esc_attr( $k ) . '" ' . selected( $style, $k, false ) . '>' . esc_html( $s ) . '</option>';
								} ?>
							</select>
						</td>
					</tr>
					<?php
				}

				function shortcode_product( $attrs ) {
					$countdown = '';

					$attrs = shortcode_atts( [
						'id' => null
					], $attrs, 'wooct_product' );

					if ( ! $attrs['id'] ) {
						global $product;

						if ( $product && $product->get_id() ) {
							$attrs['id'] = $product->get_id();
						}
					}

					if ( $attrs['id'] ) {
						$active = apply_filters( 'wooct_active', get_post_meta( $attrs['id'], 'wooct_active', true ) ?: 'no', $attrs['id'] );

						if ( $active === 'yes' ) {
							$style      = get_post_meta( $attrs['id'], 'wooct_style', true ) ?: apply_filters( 'wooct_default_style', '01' );
							$text_above = get_post_meta( $attrs['id'], 'wooct_text_above', true ) ?: self::get_setting( 'text_above', '' );
							$text_under = get_post_meta( $attrs['id'], 'wooct_text_under', true ) ?: self::get_setting( 'text_under', '' );
							$text_ended = get_post_meta( $attrs['id'], 'wooct_text_ended', true ) ?: self::get_setting( 'text_ended', '' );
							$time       = get_post_meta( $attrs['id'], 'wooct_time', true ) ?: 'custom';

							if ( $time === 'sale' ) {
								$time_start = ( $date = get_post_meta( $attrs['id'], '_sale_price_dates_from', true ) ) ? date_i18n( 'm/d/Y H:i:s', $date ) : '';
								$time_end   = ( $date = get_post_meta( $attrs['id'], '_sale_price_dates_to', true ) ) ? date_i18n( 'm/d/Y H:i:s', $date ) : '';
							} else {
								$time_start = get_post_meta( $attrs['id'], 'wooct_time_start', true ) ?: '';
								$time_end   = get_post_meta( $attrs['id'], 'wooct_time_end', true ) ?: '';
							}

							$countdown = self::get_countdown( $style, $time_start, $time_end, $text_above, $text_under, $text_ended, $attrs['id'] );
						}
					}

					return $countdown;
				}

				function show_countdown() {
					echo do_shortcode( '[wooct_product]' );
				}

				function show_countdown_single() {
					global $product;

					echo '<div class="wooct-wrap-single" data-id="' . esc_attr( $product->get_id() ) . '">';
					echo do_shortcode( '[wooct_product]' );
					echo '</div>';
				}

				function enqueue_scripts() {
					// moment
					wp_enqueue_script( 'moment', WOOCT_URI . 'assets/libs/moment/moment.js', [ 'jquery' ], WOOCT_VERSION, true );
					wp_enqueue_script( 'moment-timezone', WOOCT_URI . 'assets/libs/moment-timezone/moment-timezone-with-data.js', [ 'jquery' ], WOOCT_VERSION, true );

					// jquery.countdown
					if ( self::localization( 'zero_plural', 'yes' ) === 'yes' ) {
						wp_enqueue_script( 'jquery.countdown', WOOCT_URI . 'assets/libs/jquery.countdown/jquery.countdown_zp.js', [ 'jquery' ], WOOCT_VERSION, true );
					} else {
						wp_enqueue_script( 'jquery.countdown', WOOCT_URI . 'assets/libs/jquery.countdown/jquery.countdown.js', [ 'jquery' ], WOOCT_VERSION, true );
					}

					// flipper
					wp_enqueue_style( 'flipper', WOOCT_URI . 'assets/libs/flipper/style.css', [], WOOCT_VERSION );
					wp_enqueue_script( 'flipper', WOOCT_URI . 'assets/libs/flipper/jquery.flipper-responsive.js', [ 'jquery' ], WOOCT_VERSION, true );

					// wooct-frontend
					wp_enqueue_style( 'wooct-frontend', WOOCT_URI . 'assets/css/frontend.css', [], WOOCT_VERSION );
					wp_enqueue_script( 'wooct-frontend', WOOCT_URI . 'assets/js/frontend.js', [ 'jquery' ], WOOCT_VERSION, true );

					// localization
					$day     = self::localization( 'day', esc_html__( 'Day', 'wpc-countdown-timer' ) );
					$days    = self::localization( 'days', esc_html__( 'Days', 'wpc-countdown-timer' ) );
					$hour    = self::localization( 'hour', esc_html__( 'Hour', 'wpc-countdown-timer' ) );
					$hours   = self::localization( 'hours', esc_html__( 'Hours', 'wpc-countdown-timer' ) );
					$minute  = self::localization( 'minute', esc_html__( 'Minute', 'wpc-countdown-timer' ) );
					$minutes = self::localization( 'minutes', esc_html__( 'Minutes', 'wpc-countdown-timer' ) );
					$second  = self::localization( 'second', esc_html__( 'Second', 'wpc-countdown-timer' ) );
					$seconds = self::localization( 'seconds', esc_html__( 'Seconds', 'wpc-countdown-timer' ) );

					// format
					$format_01 = '<span>%D %!D:' . $day . ',' . $days . ';</span> <span>%H</span>:<span>%M</span>:<span>%S</span>';
					$format_02 = '<span><span>%D</span><span>%!D:' . $day . ',' . $days . ';</span></span><span><span>%H</span><span>%!H:' . $hour . ',' . $hours . ';</span></span><span><span>%M</span><span>%!M:' . $minute . ',' . $minutes . ';</span></span><span><span>%S</span><span>%!S:' . $second . ',' . $seconds . ';</span></span>';
					$format_06 = '<span><span>%D</span><span>d</span></span><span><span>%H</span><span>h</span></span><span><span>%M</span><span>m</span></span><span><span>%S</span><span>s</span></span>';

					wp_localize_script( 'wooct-frontend', 'wooct_vars', [
							'timezone'        => get_option( 'timezone_string' ),
							// default
							'timer_format'    => apply_filters( 'wooct_timer_format', $format_01 ),
							// flat
							'timer_format_01' => apply_filters( 'wooct_timer_format_01', $format_01 ),
							// square
							'timer_format_02' => apply_filters( 'wooct_timer_format_02', $format_02 ),
							// rounded
							'timer_format_03' => apply_filters( 'wooct_timer_format_03', $format_02 ),
							'timer_format_06' => apply_filters( 'wooct_timer_format_06', $format_06 ),
						]
					);
				}

				function admin_enqueue_scripts() {
					// wpcdpk
					wp_enqueue_style( 'wpcdpk', WOOCT_URI . 'assets/libs/wpcdpk/css/datepicker.css' );
					wp_enqueue_script( 'wpcdpk', WOOCT_URI . 'assets/libs/wpcdpk/js/datepicker.js', [ 'jquery' ], WOOCT_VERSION, true );

					// backend
					wp_enqueue_style( 'wooct-backend', WOOCT_URI . 'assets/css/backend.css', [], WOOCT_VERSION );
					wp_enqueue_script( 'wooct-backend', WOOCT_URI . 'assets/js/backend.js', [ 'jquery' ], WOOCT_VERSION, true );
					wp_localize_script( 'wooct-backend', 'wooct_vars', [
							'wooct_nonce' => wp_create_nonce( 'wooct_nonce' )
						]
					);
				}

				function action_links( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$settings         = '<a href="' . admin_url( 'admin.php?page=wpclever-wooct&tab=settings' ) . '">' . esc_html__( 'Settings', 'wpc-countdown-timer' ) . '</a>';
						$links['premium'] = '<a href="' . admin_url( 'admin.php?page=wpclever-wooct&tab=premium' ) . '">' . esc_html__( 'Premium Version', 'wpc-countdown-timer' ) . '</a>';
						array_unshift( $links, $settings );
					}

					return (array) $links;
				}

				function row_meta( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$row_meta = [
							'support' => '<a href="' . esc_url( WOOCT_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'wpc-countdown-timer' ) . '</a>',
						];

						return array_merge( $links, $row_meta );
					}

					return (array) $links;
				}

				function product_data_tabs( $tabs ) {
					$tabs['wooct'] = [
						'label'  => esc_html__( 'Countdown', 'wpc-countdown-timer' ),
						'target' => 'wooct_settings',
					];

					return $tabs;
				}

				function product_data_panels() {
					global $post;
					?>
					<div id='wooct_settings' class='panel woocommerce_options_panel wooct_table'>
						<div class="wooct_current_time">
							<?php esc_html_e( 'Current time', 'wpc-countdown-timer' ); ?>
							<code><?php echo current_time( 'm/d/Y' ); ?></code>
							<code><?php echo current_time( 'h:i a' ); ?></code>
							<a href="<?php echo admin_url( 'options-general.php' ); ?>" target="_blank"><?php esc_html_e( 'Date/time settings', 'wpc-countdown-timer' ); ?></a>
						</div>
						<div class="wooct_table">
							<table>
								<?php self::configure_form( absint( $post->ID ) ); ?>
							</table>
						</div>
					</div>
					<?php
				}

				function process_product_meta( $post_id ) {
					if ( isset( $_POST['wooct_active'] ) ) {
						update_post_meta( $post_id, 'wooct_active', sanitize_text_field( $_POST['wooct_active'] ) );
					} else {
						delete_post_meta( $post_id, 'wooct_active' );
					}

					if ( isset( $_POST['wooct_time'] ) ) {
						update_post_meta( $post_id, 'wooct_time', sanitize_text_field( $_POST['wooct_time'] ) );
					} else {
						delete_post_meta( $post_id, 'wooct_time' );
					}

					if ( isset( $_POST['wooct_time_start'] ) ) {
						update_post_meta( $post_id, 'wooct_time_start', sanitize_text_field( $_POST['wooct_time_start'] ) );
					} else {
						delete_post_meta( $post_id, 'wooct_time_start' );
					}

					if ( isset( $_POST['wooct_time_end'] ) ) {
						update_post_meta( $post_id, 'wooct_time_end', sanitize_text_field( $_POST['wooct_time_end'] ) );
					} else {
						delete_post_meta( $post_id, 'wooct_time_end' );
					}

					if ( isset( $_POST['wooct_text_above'] ) ) {
						update_post_meta( $post_id, 'wooct_text_above', addslashes( $_POST['wooct_text_above'] ) );
					} else {
						delete_post_meta( $post_id, 'wooct_text_above' );
					}

					if ( isset( $_POST['wooct_text_under'] ) ) {
						update_post_meta( $post_id, 'wooct_text_under', addslashes( $_POST['wooct_text_under'] ) );
					} else {
						delete_post_meta( $post_id, 'wooct_text_under' );
					}

					if ( isset( $_POST['wooct_text_ended'] ) ) {
						update_post_meta( $post_id, 'wooct_text_ended', addslashes( $_POST['wooct_text_ended'] ) );
					} else {
						delete_post_meta( $post_id, 'wooct_text_ended' );
					}

					if ( isset( $_POST['wooct_style'] ) ) {
						update_post_meta( $post_id, 'wooct_style', sanitize_text_field( $_POST['wooct_style'] ) );
					} else {
						delete_post_meta( $post_id, 'wooct_style' );
					}
				}

				function variation_settings( $loop, $variation_data, $variation ) {
					?>
					<div class="form-row form-row-full wooct-variation-settings">
						<label><?php esc_html_e( 'WPC Countdown Timer', 'wpc-countdown-timer' ); ?></label>
						<div class="wooct-variation-wrap">
							<table>
								<?php self::configure_form( absint( $variation->ID ), true ); ?>
							</table>
						</div>
					</div>
					<?php
				}

				function after_variations_table() {
					global $product;

					echo '<div class="wooct-wrap-variation" data-id="' . esc_attr( $product->get_id() ) . '"></div>';
				}

				function available_variation( $available, $variable, $variation ) {
					$available['wooct'] = htmlentities( do_shortcode( '[wooct_product id="' . $variation->get_id() . '"]' ) );

					return $available;
				}

				function check_time_start( $start = '' ) {
					if ( ! empty( $start ) ) {
						if ( ! is_numeric( $start ) ) {
							$start = strtotime( $start );
						}

						if ( current_time( 'timestamp' ) < $start ) {
							return false;
						}
					}

					return true;
				}

				function check_time_end( $end ) {
					if ( ! empty( $end ) ) {
						if ( ! is_numeric( $end ) ) {
							$end = strtotime( $end );
						}

						if ( current_time( 'timestamp' ) < $end ) {
							return true;
						}
					}

					return false;
				}

				function post_class( $classes, $product ) {
					if ( $product->is_type( 'variation' ) && $product->get_parent_id() ) {
						$product_id = $product->get_parent_id();
					} else {
						$product_id = $product->get_id();
					}

					$active = get_post_meta( $product_id, 'wooct_active', true ) ?: 'no';
					$time   = get_post_meta( $product_id, 'wooct_time', true ) ?: '';

					if ( $time === 'sale' ) {
						$time_start = ( $date = get_post_meta( $product_id, '_sale_price_dates_from', true ) ) ? date_i18n( 'm/d/Y H:i:s', $date ) : '';
						$time_end   = ( $date = get_post_meta( $product_id, '_sale_price_dates_to', true ) ) ? date_i18n( 'm/d/Y H:i:s', $date ) : '';
					} else {
						$time_start = get_post_meta( $product_id, 'wooct_time_start', true ) ?: '';
						$time_end   = get_post_meta( $product_id, 'wooct_time_end', true ) ?: '';
					}

					if ( $active === 'yes' ) {
						$classes[] = 'wooct-active';
					}

					if ( ! self::check_time_end( $time_end ) ) {
						$classes[] = 'wooct-ended';
					} elseif ( self::check_time_start( $time_start ) ) {
						$classes[] = 'wooct-running';
					}

					return $classes;
				}

				function get_countdown( $style, $time_start, $time_end, $text_above, $text_under, $text_ended, $product_id = 0 ) {
					$countdown  = '';
					$style      = apply_filters( 'wooct_style', $style, $product_id );
					$time_start = apply_filters( 'wooct_time_start', $time_start, $product_id );
					$time_end   = apply_filters( 'wooct_time_end', $time_end, $product_id );
					$text_above = apply_filters( 'wooct_text_above', $text_above, $product_id );
					$text_under = apply_filters( 'wooct_text_under', $text_under, $product_id );
					$text_ended = apply_filters( 'wooct_text_ended', $text_ended, $product_id );

					if ( is_numeric( $time_start ) ) {
						$time_start = date_i18n( 'm/d/Y H:i:s', $time_start );
					}

					if ( is_numeric( $time_end ) ) {
						$time_end = date_i18n( 'm/d/Y H:i:s', $time_end );
					}

					if ( ! empty( $time_end ) && self::check_time_start( $time_start ) ) {
						if ( ! self::check_time_end( $time_end ) ) {
							if ( ! empty( $text_ended ) ) {
								$class     = 'wooct-countdown wooct-ended wooct-style-' . esc_attr( $style );
								$countdown .= '<div class="' . esc_attr( apply_filters( 'wooct_class_ended', $class ) ) . '"><div class="wooct-text-ended">' . stripslashes( $text_ended ) . '</div></div>';
							}
						} else {
							$class     = 'wooct-countdown wooct-running wooct-style-' . esc_attr( $style ) . ' ' . ( $style === '04' || $style === '05' ? 'wooct-flipper' : '' );
							$countdown .= '<div class="' . esc_attr( apply_filters( 'wooct_class', $class ) ) . '" data-style="' . esc_attr( $style ) . '" data-timer="' . esc_attr( $time_end ) . '" data-ended="' . htmlentities( stripslashes( $text_ended ) ) . '">';

							if ( ! empty( $text_above ) ) {
								$countdown .= '<div class="wooct-text-above">' . stripslashes( $text_above ) . '</div>';
							}

							if ( $style === '04' || $style === '05' ) {
								// flipper
								$days      = self::localization( 'days', esc_html__( 'Days', 'wpc-countdown-timer' ) );
								$hours     = self::localization( 'hours', esc_html__( 'Hours', 'wpc-countdown-timer' ) );
								$minutes   = self::localization( 'minutes', esc_html__( 'Minutes', 'wpc-countdown-timer' ) );
								$seconds   = self::localization( 'seconds', esc_html__( 'Seconds', 'wpc-countdown-timer' ) );
								$template  = apply_filters( 'wooct_flipper_template', 'dd|HH|ii|ss' );
								$labels    = apply_filters( 'wooct_flipper_labels', $days . '|' . $hours . '|' . $minutes . '|' . $seconds );
								$countdown .= '<div class="wooct-timer flipper ' . ( $style === '05' ? 'flipper-dark' : '' ) . '" data-reverse="true" data-datetime="' . esc_attr( $time_end ) . '" data-template="' . esc_attr( $template ) . '" data-labels="' . esc_attr( $labels ) . '"></div>';
							} else {
								$countdown .= '<div class="wooct-timer">' . $time_end . '</div>';
							}

							if ( ! empty( $text_under ) ) {
								$countdown .= '<div class="wooct-text-under">' . stripslashes( $text_under ) . '</div>';
							}

							$countdown .= '</div>';
						}
					}

					return apply_filters( 'wooct_countdown', $countdown, $style, $time_start, $time_end, $text_above, $text_under, $text_ended, $product_id );
				}

				function product_columns( $columns ) {
					$columns['wooct'] = esc_html__( 'Countdown', 'wpc-countdown-timer' );

					return $columns;
				}

				function product_custom_column( $column, $postid ) {
					if ( $column === 'wooct' ) {
						$active     = get_post_meta( $postid, 'wooct_active', true ) ?: 'no';
						$time_start = get_post_meta( $postid, 'wooct_time_start', true ) ?: '';
						$time_end   = get_post_meta( $postid, 'wooct_time_end', true ) ?: '';

						if ( ( $active === 'yes' ) && ! empty( $time_end ) ) {
							if ( self::check_time_start( $time_start ) && self::check_time_end( $time_end ) ) {
								echo '<span class="wooct-icon running"><span class="dashicons dashicons-clock"></span></span>';
							} else {
								echo '<span class="wooct-icon"><span class="dashicons dashicons-clock"></span></span>';
							}
						}
					}
				}

				function duplicate_variation( $old_variation_id, $new_variation_id ) {
					if ( $active = get_post_meta( $old_variation_id, 'wooct_active', true ) ) {
						update_post_meta( $new_variation_id, 'wooct_active', $active );
					}

					if ( $time = get_post_meta( $old_variation_id, 'wooct_time', true ) ) {
						update_post_meta( $new_variation_id, 'wooct_time', $time );
					}

					if ( $time_start = get_post_meta( $old_variation_id, 'wooct_time_start', true ) ) {
						update_post_meta( $new_variation_id, 'wooct_time_start', $time_start );
					}

					if ( $time_end = get_post_meta( $old_variation_id, 'wooct_time_end', true ) ) {
						update_post_meta( $new_variation_id, 'wooct_time_end', $time_end );
					}

					if ( $text_above = get_post_meta( $old_variation_id, 'wooct_text_above', true ) ) {
						update_post_meta( $new_variation_id, 'wooct_text_above', $text_above );
					}

					if ( $text_under = get_post_meta( $old_variation_id, 'wooct_text_under', true ) ) {
						update_post_meta( $new_variation_id, 'wooct_text_under', $text_under );
					}

					if ( $text_ended = get_post_meta( $old_variation_id, 'wooct_text_ended', true ) ) {
						update_post_meta( $new_variation_id, 'wooct_text_ended', $text_ended );
					}

					if ( $style = get_post_meta( $old_variation_id, 'wooct_style', true ) ) {
						update_post_meta( $new_variation_id, 'wooct_style', $style );
					}
				}

				public static function get_settings() {
					return apply_filters( 'wooct_get_settings', self::$settings );
				}

				public static function get_setting( $name, $default = false ) {
					if ( ! empty( self::$settings ) && isset( self::$settings[ $name ] ) ) {
						$setting = self::$settings[ $name ];
					} else {
						$setting = get_option( 'wooct_' . $name, $default );
					}

					return apply_filters( 'wooct_get_setting', $setting, $name, $default );
				}

				public static function localization( $key = '', $default = '' ) {
					if ( ! empty( $key ) && ! empty( self::$localization[ $key ] ) ) {
						$str = self::$localization[ $key ];
					} else {
						$str = get_option( 'wooct_localization_' . $key );

						if ( empty( $str ) ) {
							$str = $default;
						}
					}

					return apply_filters( 'wooct_localization_' . $key, $str );
				}
			}

			return WPCleverWooct::instance();
		}
	}
}

if ( ! function_exists( 'wooct_notice_wc' ) ) {
	function wooct_notice_wc() {
		?>
		<div class="error">
			<p><strong>WPC Countdown Timer</strong> require WooCommerce version 3.0 or greater.</p>
		</div>
		<?php
	}
}
