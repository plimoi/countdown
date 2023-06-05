<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPCleverMenu' ) ) {
	class WPCleverMenu {
		function __construct() {
			add_action( 'admin_menu', [ $this, 'admin_menu' ] );
			add_action( 'admin_footer', [ $this, 'admin_footer' ] );
		}

		function admin_menu() {
			add_menu_page(
				'WPClever',
				'WPClever',
				'manage_options',
				'wpclever',
				[ $this, 'welcome_content' ],
				WPC_URI . 'assets/images/wpc-icon.svg',
				26
			);
			add_submenu_page( 'wpclever', 'WPC About', 'About', 'manage_options', 'wpclever' );
		}

		function welcome_content() {
			?>
			<div class="wpclever_page wpclever_welcome_page wrap">
				<h1>WPClever | Make clever moves</h1>
				<div class="card">
					<h2 class="title">About</h2>
					<p>
						We are a team of passionate developers of plugins for WordPress, whose aspiration is to bring smart utilities and functionalities to life for WordPress users, especially for those on WooCommerce platform. </p>
					<p>Website:
						<a href="https://wpclever.net?utm_source=visit&utm_medium=menu&utm_campaign=wporg" target="_blank">https://wpclever.net</a>
					</p>
				</div>
				<div class="card wpclever_plugins">
					<h2 class="title">Plugins
						<span class="wpclever_plugins_order"><a href="#" class="wpclever_plugins_order_a" data-o="p">popular</a> |
						<a href="#" class="wpclever_plugins_order_a" data-o="u">last updated</a></span>
					</h2>
					<?php
					if ( ( false === ( $plugins_arr = get_transient( 'wpclever_plugins' ) ) ) || ( isset( $_GET['act'] ) && ( $_GET['act'] === 'update' ) ) ) {
						$args    = (object) [
							'author'   => 'wpclever',
							'per_page' => '120',
							'page'     => '1',
							'fields'   => [
								'slug',
								'name',
								'version',
								'downloaded',
								'active_installs',
								'last_updated'
							]
						];
						$request = [
							'action'  => 'query_plugins',
							'timeout' => 15,
							'request' => serialize( $args )
						];
						//https://codex.wordpress.org/WordPress.org_API
						$url      = 'http://api.wordpress.org/plugins/info/1.0/';
						$response = wp_remote_post( $url, [ 'body' => $request ] );

						if ( ! is_wp_error( $response ) ) {
							$plugins_arr = [];
							$plugins     = unserialize( $response['body'] );

							if ( isset( $plugins->plugins ) && ( count( $plugins->plugins ) > 0 ) ) {
								foreach ( $plugins->plugins as $pl ) {
									$plugins_arr[] = [
										'slug'            => $pl->slug,
										'name'            => $pl->name,
										'version'         => $pl->version,
										'downloaded'      => $pl->downloaded,
										'active_installs' => $pl->active_installs,
										'last_updated'    => strtotime( $pl->last_updated ),
									];
								}
							}

							set_transient( 'wpclever_plugins', $plugins_arr, 24 * HOUR_IN_SECONDS );
						}
					}

					if ( is_array( $plugins_arr ) && ( count( $plugins_arr ) > 0 ) ) {
						array_multisort( array_column( $plugins_arr, 'active_installs' ), SORT_DESC, $plugins_arr );
						$i = 1;

						echo '<div class="wpclever_plugins_wrapper">';

						foreach ( $plugins_arr as $pl ) {
							if ( strpos( $pl['name'], 'WPC' ) === false ) {
								continue;
							}

							echo '<div class="item" data-p="' . esc_attr( isset( $pl['active_installs'] ) ? $pl['active_installs'] : 0 ) . '" data-u="' . esc_attr( isset( $pl['last_updated'] ) ? $pl['last_updated'] : 0 ) . '" data-d="' . esc_attr( isset( $pl['downloaded'] ) ? $pl['downloaded'] : 0 ) . '"><a href="' . esc_url( 'https://wordpress.org/plugins/' . $pl['slug'] . '/' ) . '" target="_blank"><span class="num">' . esc_html( $i ) . '</span><span class="title">' . esc_html( $pl['name'] ) . '</span><br/><span class="info">' . esc_html( 'Version ' . $pl['version'] ) . ( isset( $pl['last_updated'] ) ? ' - Last updated: ' . date( 'M j, Y', $pl['last_updated'] ) : '' ) . '</span></a></div>';
							$i ++;
						}

						echo '</div>';
					} else {
						echo 'https://wpclever.net';
					}
					?>
				</div>
			</div>
			<?php
		}

		function admin_footer() {
			?>
			<script type="text/javascript">
              (function($) {
                $(document).on('click', '.wpclever_plugins_order_a', function(e) {
                  e.preventDefault();
                  var o = $(this).data('o');

                  if ($(this).hasClass('wpclever_plugins_order_down')) {
                    $('.wpclever_plugins_wrapper').find('.item').sort(function(a, b) {
                      return $(b).data(o) - $(a).data(o);
                    }).appendTo('.wpclever_plugins_wrapper');
                  } else {
                    $('.wpclever_plugins_wrapper').find('.item').sort(function(a, b) {
                      return $(a).data(o) - $(b).data(o);
                    }).appendTo('.wpclever_plugins_wrapper');
                  }

                  $(this).toggleClass('wpclever_plugins_order_down');
                });
              })(jQuery);
			</script>
			<?php
		}
	}

	new WPCleverMenu();
}