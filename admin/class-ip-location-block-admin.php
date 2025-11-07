<?php

/**
 * IP Location Block - Admin class
 *
 * @package   IP_Location_Block
 * @author    Darko Gjorgjijoski <dg@darkog.com>
 * @license   GPL-3.0
 * @link      https://iplocationblock.com/
 * @copyright 2021 darkog
 * @copyright 2013-2019 tokkonopapa
 */
class IP_Location_Block_Admin {

	/**
	 * Constants for admin class
	 *
	 */
	const INTERVAL_LIVE_UPDATE = 5; // interval for live update [sec]
	const TIMEOUT_LIVE_UPDATE = 60; // timeout of pausing live update [sec]

	/**
	 * Globals in this class
	 *
	 */
	private static $instance = null;
	private $is_network_admin = false;
	private $admin_tab = 0;

	/**
	 * Initialize the plugin by loading admin scripts & styles
	 * and adding a settings page and menu.
	 */
	private function __construct() {
		// Control tab number
		if ( isset( $_REQUEST['page'] ) && 'ip-location-block' === $_REQUEST['page'] ) {
			$this->admin_tab = isset( $_REQUEST['tab'] ) ? intval( $_REQUEST['tab'] ) : 0;
		} else {
			$this->admin_tab = - 1;
		}

		// Load plugin text domain and add body class
		add_action( 'init', array( $this, 'admin_init' ) );

		// Add suggest text for inclusion in the site's privacy policy. @since 4.9.6
		// add_action( 'admin_init', array( $this, 'add_privacy_policy' ) );

		// Setup a nonce to validate authentication.
		add_filter( 'wp_redirect', array( $this, 'add_redirect_nonce' ), 10, 2 ); // @since  0.2.1.0

	}

	/**
	 * Return an instance of this class.
	 *
	 */
	public static function get_instance() {
		return self::$instance ? self::$instance : ( self::$instance = new self );
	}

	/**
	 * Print admin notice welcome screen
	 * @return void
	 */
	public function show_intro_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = IP_Location_Block::get_option();
		if ( ! isset( $settings['welcome'] ) || ! $settings['welcome'] ) {
			ob_start();
			echo '<div class="notice notice-info is-dismissible ip-location-block-notice-intro" data-notice="welcome">';
			include( dirname( __FILE__ ) . '/includes/welcome.php' );
			echo '</div>';
			$message = ob_get_clean();
			echo $message;
		}
	}

	/**
	 * Print admin notice for API key upgrade requirement
	 * @return void
	 */
	public function show_api_key_upgrade_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = IP_Location_Block::get_option();

		// Check if we've already marked this as completed (user upgraded or dismissed)
		if ( isset( $settings['api_key_upgraded'] ) && $settings['api_key_upgraded'] ) {
			return;
		}

		// Check if IP Location Block provider is being used
		$providers = IP_Location_Block_Provider::get_valid_providers( $settings );
		$uses_api  = in_array( 'IP Location Block', $providers );

		if ( ! $uses_api ) {
			return;
		}

		// Get API key
		$api_key = ! empty( $settings['providers']['IP Location Block'] ) ? $settings['providers']['IP Location Block'] : '';

		if ( empty( $api_key ) ) {
			return;
		}

		// Check quota to see if upgrade is required
		$quota = IP_Location_Block_Provider::get_native_quota( $api_key );

		// If API key needs upgrade, show the notice
		if ( isset( $quota['name'] ) && $quota['name'] === 'requires-api-key-upgrade' ) {
			$upgrade_url = 'https://app.iplocationblock.com/upgrade-api-key?api_key=' . urlencode( $api_key );
			ob_start();
			?>
			<div class="notice notice-warning ip-location-block-notice-api-key-upgrade">
				<p>
					<strong><?php _e( 'IP Location Block: API Key Upgrade Required', 'ip-location-block' ); ?></strong>
				</p>
				<p>
					<?php _e( 'Your API key needs to be upgraded due to security improvements. This is a one-time process and will only take a moment.', 'ip-location-block' ); ?>
				</p>
				<p>
					<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary" target="_blank">
						<?php _e( 'Upgrade API Key Now', 'ip-location-block' ); ?>
					</a>
					<a href="https://iplocationblock.com/api-platform-upgrade/" class="button button-secondary" target="_blank">
						<?php _e( 'Learn More', 'ip-location-block' ); ?>
					</a>
				</p>
			</div>
			<?php
			echo ob_get_clean();
		} elseif ( isset( $quota['name'] ) && $quota['name'] !== 'requires-api-key-upgrade' ) {
			// API key is valid and doesn't need upgrade, mark as completed so we don't check again
			$settings['api_key_upgraded'] = true;
			IP_Location_Block::update_option( $settings );
		}
	}

	/**
	 * Load the plugin text domain for translation and add body class.
	 *
	 */
	public function admin_init() {
		// include drop in for admin if it exists
		$dropin_path = IP_Location_Block_Util::get_dropins_storage_dir( 'drop-in-admin.php' );
		if ( file_exists( $dropin_path ) ) {
			include_once $dropin_path;
		}

		// Add the options page and menu item.
		add_action( 'admin_menu', array( $this, 'setup_admin_page' ) ); // @since: 2.5.0
		add_action( 'admin_post_ip_location_block', array( $this, 'admin_ajax_callback' ) ); // @since: 2.6.0
		add_action( 'wp_ajax_ip_location_block', array( $this, 'admin_ajax_callback' ) ); // @since: 2.1.0
		add_filter( 'wp_prepare_revision_for_js', array( $this, 'add_revision_nonce' ), 10, 3 );
        add_action( 'admin_enqueue_scripts', array($this, 'enqueue_admin_assets'), 15);

		if ( IP_Location_Block_Util::is_user_logged_in() ) {
			add_filter( 'ip-location-block-bypass-admins', array( $this, 'verify_request' ), 10, 2 );
		}

		if ( is_multisite() && is_plugin_active_for_network( IP_LOCATION_BLOCK_BASE ) ) { // @since: 3.0.0
			$this->is_network_admin = current_user_can( 'manage_network_options' );
			add_action( 'network_admin_menu', array( $this, 'setup_admin_page' ) ); // @since: 2.5
			add_action( 'wpmu_new_blog', array( $this, 'create_blog' ), 10, 6 ); // on creating a new blog @since MU
			add_action( 'delete_blog', array( $this, 'delete_blog' ), 10, 2 ); // on deleting an old blog @since 3.0.0
		}

		// loads a plugin’s translated strings.
		load_plugin_textdomain( IP_Location_Block::PLUGIN_NAME, false, dirname( IP_LOCATION_BLOCK_BASE ) . '/languages/' );

		// add webview class into body tag.
		// https://stackoverflow.com/questions/37591279/detect-if-user-is-using-webview-for-android-ios-or-a-regular-browser
		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) &&
		     ( strpos( $_SERVER['HTTP_USER_AGENT'], 'Mobile/' ) !== false ) &&
		     ( strpos( $_SERVER['HTTP_USER_AGENT'], 'Safari/' ) === false ) ) {
			add_filter( 'admin_body_class', array( $this, 'add_webview_class' ) );
		} // for Android
        elseif ( isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && $_SERVER['HTTP_X_REQUESTED_WITH'] === "com.company.app" ) {
			add_filter( 'admin_body_class', array( $this, 'add_webview_class' ) );
		}
	}

	/**
	 * Whether this plugin activated by network or not.
	 *
	 */
	public function is_network_admin() {
		return $this->is_network_admin;
	}

	/**
	 * Add webview class into the body.
	 *
	 * @param $classes
	 *
	 * @return string
	 */
	public function add_webview_class( $classes ) {
		return $classes . ( $classes ? ' ' : '' ) . 'webview';
	}

	/**
	 * Add nonce when redirect into wp-admin area.
	 *
	 * @param $location
	 * @param $status
	 *
	 * @return string
	 */
	public function add_redirect_nonce( $location, $status ) {
		$status = true; // default is `retrieve` a nonce
		$urls   = array( wp_login_url() );

		// avoid multiple redirection caused by WP hide 1.4.9.1
		if ( is_plugin_active( 'wp-hide-security-enhancer/wp-hide.php' ) ) {
			$urls[] = 'options-permalink.php';
		}

		foreach ( $urls as $url ) {
			if ( false !== strpos( $location, $url ) ) {
				$status = false; // do not `retieve` a nonce
				break;
			}
		}

		return IP_Location_Block_Util::rebuild_nonce( $location, $status );
	}

	/**
	 * Add nonce to revision @param $revisions_data
	 *
	 * @param $revision
	 * @param $post
	 *
	 * @return mixed
	 * @since 4.4.0
	 */
	public function add_revision_nonce( $revisions_data, $revision, $post ) {
		$revisions_data['restoreUrl'] = add_query_arg(
			$nonce = IP_Location_Block::get_auth_key(),
			IP_Location_Block_Util::create_nonce( $nonce ),
			$revisions_data['restoreUrl']
		);

		return $revisions_data;
	}

	/**
	 * Verify admin screen without action instead of validating nonce.
	 *
	 * @param $queries
	 * @param $settings
	 *
	 * @return mixed
	 */
	public function verify_request( $queries, $settings ) {
		// the request that is intended to show the page without any action follows authentication of core.
		if ( 'GET' === IP_Location_Block_Util::get_request_method() && isset( $_GET['page'] ) ) {
			foreach ( array( 'action', 'task' ) as $key ) {
				if ( ! empty( $_GET[ $key ] ) ) {
					return $queries;
				}
			}
			$queries[] = $_GET['page'];
		}

		return $queries;
	}

	/**
	 * Do some procedures when a blog is created or deleted.
	 *
	 * @param $blog_id
	 * @param $user_id
	 * @param $domain
	 * @param $path
	 * @param $site_id
	 * @param $meta
	 */
	public function create_blog( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
		defined( 'IP_LOCATION_BLOCK_DEBUG' ) and IP_LOCATION_BLOCK_DEBUG and assert( is_main_site(), 'Not main blog.' );
		require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-actv.php';

		// get options on main blog
		$settings = IP_Location_Block::get_option();

		// Switch to the new blog and initialize.
		switch_to_blog( $blog_id );
		IP_Location_Block_Activate::activate_blog();

		// Copy option from main blog.
		if ( $this->is_network_admin && $settings['network_wide'] ) {
			IP_Location_Block::update_option( $settings, false );
		}

		// Restore the main blog.
		restore_current_blog();
	}

	public function delete_blog( $blog_id, $drop ) {
		// blog is already switched to the target in wpmu_delete_blog()
		$drop and IP_Location_Block_Logs::delete_tables();
	}

	/**
	 * Get the action name of ajax for nonce
	 *
	 */
	private function get_ajax_action() {
		return IP_Location_Block::PLUGIN_NAME . '-ajax-action';
	}

	/**
	 * Register and enqueue plugin-specific style sheet and JavaScript.
	 */
	public function enqueue_admin_assets() {

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		$settings = IP_Location_Block::get_option();

		$release = ( ! defined( 'IP_LOCATION_BLOCK_DEBUG' ) || ! IP_LOCATION_BLOCK_DEBUG );

		$footer     = true;
		$dependency = array( 'jquery' );
		$version    = $release ? IP_LOCATION_BLOCK_VERSION : max(
			filemtime( IP_LOCATION_BLOCK_PATH . 'admin/css/admin.css' ),
			filemtime( IP_LOCATION_BLOCK_PATH . 'admin/js/admin.js' )
		);

		switch ( $this->admin_tab ) {
			case 1: /* Statistics */
			case 4: /* Logs */
				// css and js for DataTables
				wp_enqueue_style( IP_Location_Block::PLUGIN_NAME . '-datatables-css',
					plugins_url( 'datatables/css/datatables-all.min.css', __FILE__ ),
					array(), IP_LOCATION_BLOCK_VERSION
				);
				wp_enqueue_script( IP_Location_Block::PLUGIN_NAME . '-datatables-js',
					plugins_url( 'datatables/js/datatables-all.min.js', __FILE__ ),
					$dependency, IP_LOCATION_BLOCK_VERSION, $footer
				);
				if ( 4 === $this->admin_tab ) {
					break;
				}

			case 5: /* Sites list */
				// js for google charts
				wp_register_script(
					$addon = IP_Location_Block::PLUGIN_NAME . '-google-chart',
					apply_filters( 'google-charts', 'https://www.gstatic.com/charts/loader.js' ), array(), null, $footer
				);
				wp_enqueue_script( $addon );
				break;

			case 2: /* Search */
				// Google Charts in China
				$geo = IP_Location_Block::get_geolocation();
				if ( isset( $geo['code'] ) && 'CN' === $geo['code'] ) {
					add_filter( 'google-charts', array( $this, 'google_charts_cn' ) );
				}

				// Enqueue leaflet.js
				wp_enqueue_style(
					IP_Location_Block::PLUGIN_NAME . '-leaflet',
					plugins_url( 'vendor/leaflet/leaflet.css', __FILE__ ),
					array(),
					IP_LOCATION_BLOCK_VERSION,
					'all'
				);
				wp_enqueue_script(
					IP_Location_Block::PLUGIN_NAME . '-leaflet',
					plugins_url( 'vendor/leaflet/leaflet.js', __FILE__ ),
					array(),
					IP_LOCATION_BLOCK_VERSION,
					$footer
				);

				wp_enqueue_script( IP_Location_Block::PLUGIN_NAME . '-whois-js',
					plugins_url( $release ? 'js/whois.min.js' : 'js/whois.js', __FILE__ ),
					$dependency, IP_LOCATION_BLOCK_VERSION, $footer
				);
				break;
		}

		// css for option page
		wp_enqueue_style( IP_Location_Block::PLUGIN_NAME . '-admin-icons',
			plugins_url( $release ? 'css/admin-icons.min.css' : 'css/admin-icons.css', __FILE__ ),
			array(), IP_LOCATION_BLOCK_VERSION
		);
		wp_enqueue_style( IP_Location_Block::PLUGIN_NAME . '-admin-styles',
			plugins_url( $release ? 'css/admin.min.css' : 'css/admin.css', __FILE__ ),
			array(), $version
		);

		// js for IP Location Block admin page
		wp_register_script(
			$handle = IP_Location_Block::PLUGIN_NAME . '-admin',
			plugins_url( $release ? 'js/admin.min.js' : 'js/admin.js', __FILE__ ),
			$dependency + ( isset( $addon ) ? array( $addon ) : array() ),
			$version, $footer
		);

		wp_localize_script( $handle,
			'IP_LOCATION_BLOCK',
			array(
				'action'   => 'ip_location_block',
				'tab'      => isset($_GET['page']) && 'ip-location-block' === $_GET['page'] ? $this->admin_tab : -1,
				'url'      => admin_url( 'admin-ajax.php' ),
				'nonce'    => IP_Location_Block_Util::create_nonce( $this->get_ajax_action() ),
				'native'    => (bool) IP_Location_Block_Provider::is_native( $settings ),
				'msg'      => array(
					/* [ 0] */
					__( 'Are you sure ?', 'ip-location-block' ),
					/* [ 1] */
					__( 'Open a new window', 'ip-location-block' ),
					/* [ 2] */
					__( 'Generate new link', 'ip-location-block' ),
					/* [ 3] */
					__( 'Delete current link', 'ip-location-block' ),
					/* [ 4] */
					__( 'Please add the following link to favorites / bookmarks in your browser : ', 'ip-location-block' ),
					/* [ 5] */
					__( 'ajax for logged-in user', 'ip-location-block' ),
					/* [ 6] */
					__( 'ajax for non logged-in user', 'ip-location-block' ),
					/* [ 7] */
					__( '[Found: %d]', 'ip-location-block' ),
					/* [ 8] */
					__( 'Find and verify `%s` on &#8220;Logs&#8221; tab.', 'ip-location-block' ),
					/* [ 9] */
					__( 'This feature is available with HTML5 compliant browsers.', 'ip-location-block' ),
					/* [10] */
					__( 'The selected row cannot be found in the table.', 'ip-location-block' ),
					/* [11] */
					__( 'An error occurred while executing the ajax command `%s`.', 'ip-location-block' ),
				),
				'i18n'     => array(
					/* [ 0] */ '<div class="ip-location-block-loading"></div>',
					/* [ 1] */ __( 'No data available in table', 'ip-location-block' ),
					/* [ 2] */ __( 'No matching records found', 'ip-location-block' ),
					/* [ 3] */ __( 'IP address', 'ip-location-block' ),
					/* [ 4] */ __( 'Code', 'ip-location-block' ),
					/* [ 5] */ __( 'City', 'ip-location-block' ),
					/* [ 6] */ __( 'State', 'ip-location-block' ),
					/* [ 7] */ __( 'ASN', 'ip-location-block' ),
					/* [ 8] */ __( 'Host name', 'ip-location-block' ),
					/* [ 9] */ __( 'Target', 'ip-location-block' ),
					/* [ 10] */ __( 'Failure / Total', 'ip-location-block' ),
					/* [ 11] */ __( 'Elapsed[sec]', 'ip-location-block' ),
					/* [12] */ __( 'Time', 'ip-location-block' ),
					/* [13] */ __( 'Result', 'ip-location-block' ),
					/* [14] */ __( 'Request', 'ip-location-block' ),
					/* [15] */ __( 'User agent', 'ip-location-block' ),
					/* [16] */ __( 'HTTP headers', 'ip-location-block' ),
					/* [17] */ __( '$_POST data', 'ip-location-block' ),
				),
				'interval' => self::INTERVAL_LIVE_UPDATE, // interval for live update [sec]
				'timeout'  => self::TIMEOUT_LIVE_UPDATE,  // timeout of pausing live update [sec]
			)
		);
		IP_Location_Block::enqueue_nonce( null );
		wp_enqueue_script( $handle );
	}

	/**
	 * Google Map in China
	 *
	 * @param $url
	 *
	 * @return string
	 */
	public function google_charts_cn( $url ) {
		return 'https://www.gstatic.cn/charts/loader.js';
	}

	/**
	 * Add plugin meta links
	 *
	 * @param $links
	 * @param $file
	 *
	 * @return mixed
	 */
	public function add_plugin_meta_links( $links, $file ) {
		if ( $file === IP_LOCATION_BLOCK_BASE ) {
			array_push(
				$links,
				'<a href="https://github.com/gdarko/ip-location-block" title="IP Location Block on GitHub" target=_blank>' . __( 'Contribute on GitHub', 'ip-location-block' ) . '</a>'
			);
		}

		return $links;
	}

	/**
	 * Add settings action link to the plugins page.
	 *
	 */
	public function add_action_links( $links ) {
		$settings = IP_Location_Block::get_option();

		return array_merge(
			array( 'settings' => '<a href="' . esc_url( add_query_arg( array( 'page' => IP_Location_Block::PLUGIN_NAME ), $this->dashboard_url( $settings['network_wide'] ) ) ) . '">' . __( 'Settings' ) . '</a>' ),
			$links
		);
	}

	/**
	 * Add suggest text for inclusion in the site's privacy policy. @since 4.9.6
	 *
	 * /wp-admin/tools.php?wp-privacy-policy-guide
	 * https://developer.wordpress.org/plugins/privacy/privacy-related-options-hooks-and-capabilities/
	 */
	public function add_privacy_policy() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content( 'IP Location Block', __( 'suggested text.', 'ip-location-block' ) );
		}
	}

	/**
	 * Show global notice.
	 *
	 */
	public function show_admin_notices() {
		$key = IP_Location_Block::PLUGIN_NAME . '-notice';

		if ( false !== ( $notices = get_transient( $key ) ) ) {
			foreach ( $notices as $msg => $type ) {
				echo "\n", '<div class="notice is-dismissible ', esc_attr( $type ), '"><p>';
				if ( 'updated' === $type ) {
					echo '<strong>', IP_Location_Block_Util::kses( $msg ), '</strong>';
				} else {
					echo '<strong>IP Location Block:</strong> ', IP_Location_Block_Util::kses( $msg );
				}
				echo '</p></div>', "\n";
			}

			// delete all admin noties
			delete_transient( $key );
		}
	}

	/**
	 * Add global notice.
	 *
	 */
	public static function add_admin_notice( $type, $msg ) {
		$key = IP_Location_Block::PLUGIN_NAME . '-notice';
		if ( false === ( $notices = get_transient( $key ) ) ) {
			$notices = array();
		}

		// can't overwrite the existent notice
		if ( ! isset( $notices[ $msg ] ) ) {
			$notices[ $msg ] = $type;
			set_transient( $key, $notices, MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Get the admin url that depends on network multisite.
	 *
	 * @param bool $network_wide
	 *
	 * @return string|void
	 */
	public function dashboard_url( $network_wide = false ) {
		return ( $network_wide ? $this->is_network_admin : $network_wide ) ? network_admin_url( 'admin.php' /*'settings.php'*/ ) : admin_url( 'options-general.php' );
	}

	/**
	 * Register the administration menu into the WordPress Dashboard menu.
	 *
	 * @param $settings
	 */
	private function add_plugin_admin_menu( $settings ) {
		// Control tab number
		if ( $admin_menu = ( 'admin_menu' === current_filter() ) ) {
			if ( $this->is_network_admin && $settings['network_wide'] ) {
				$this->admin_tab = min( 4, max( 1, $this->admin_tab ) );
			} else {
				$this->admin_tab = min( 4, max( 0, $this->admin_tab ) );
			}
		} else {
			if ( $this->is_network_admin && $settings['network_wide'] ) {
				$this->admin_tab = in_array( $this->admin_tab, array( 0, 5 ), true ) ? $this->admin_tab : 0;
			} else {
				$this->admin_tab = 5;
			}
		}

		if ( $admin_menu ) {
			// `options-general.php` ==> `options.php` ==> `settings-updated` is added as query just after settings updated.
			if ( ! empty( $_REQUEST['page'] ) && IP_Location_Block::PLUGIN_NAME === $_REQUEST['page'] &&
			     ! empty( $_REQUEST['settings-updated'] ) && $this->is_network_admin && $settings['network_wide'] ) {
				$this->update_multisite_settings( $settings );
				wp_safe_redirect( esc_url_raw( add_query_arg(
					array( 'page' => IP_Location_Block::PLUGIN_NAME ),
					$this->dashboard_url( true )
				) ) );
				exit;
			}

			// Add a settings page for this plugin to the Settings menu.
			$hook = add_options_page(
				__( 'IP Location Block', 'ip-location-block' ),
				__( 'IP Location Block', 'ip-location-block' ),
				'manage_options',
				IP_Location_Block::PLUGIN_NAME,
				array( $this, 'display_plugin_admin_page' )
			);
		} elseif ( $this->is_network_admin ) {
			// Add a settings page for this plugin to the Settings menu.
			$hook = add_menu_page(
				__( 'IP Location Block', 'ip-location-block' ),
				__( 'IP Location Block', 'ip-location-block' ),
				'manage_network_options',
				IP_Location_Block::PLUGIN_NAME,
				array( $this, 'display_plugin_admin_page' )
			//, 'dashicons-admin-site' // or 'data:image/svg+xml;base64...'
			);

			add_submenu_page(
				IP_Location_Block::PLUGIN_NAME,
				__( 'IP Location Block', 'ip-location-block' ),
				__( 'Sites list', 'ip-location-block' ),
				'manage_network_options',
				IP_Location_Block::PLUGIN_NAME . '&amp;tab=5',
				array( $this, 'display_plugin_admin_page' )
			);

			if ( $settings['network_wide'] ) {
				add_submenu_page(
					IP_Location_Block::PLUGIN_NAME,
					__( 'IP Location Block', 'ip-location-block' ),
					__( 'Settings', 'ip-location-block' ),
					'manage_network_options',
					IP_Location_Block::PLUGIN_NAME,
					array( $this, 'display_plugin_admin_page' )
				);
			}

			wp_enqueue_style( IP_Location_Block::PLUGIN_NAME . '-admin-icons',
				plugins_url( ! defined( 'IP_LOCATION_BLOCK_DEBUG' ) || ! IP_LOCATION_BLOCK_DEBUG ?
					'css/admin-icons.min.css' : 'css/admin-icons.css', __FILE__
				),
				array(), IP_LOCATION_BLOCK_VERSION
			);
		}

		// If successful, load admin assets only on this page.
		if ( ! empty( $hook ) ) // 'admin_enqueue_scripts'
		{
			//add_action( "load-$hook", array( $this, 'enqueue_admin_assets' ) );
		}
	}

	/**
	 * Diagnosis of admin settings.
	 *
	 * @param $settings
	 */
	private function diagnose_admin_screen( $settings ) {
		$updating = get_transient( IP_Location_Block::CRON_NAME );
		$adminurl = $this->dashboard_url( false );
		$network  = $this->dashboard_url( $settings['network_wide'] );

		// Check version and compatibility
		if ( version_compare( get_bloginfo( 'version' ), '3.7.0' ) < 0 ) {
			self::add_admin_notice( 'error', __( 'You need WordPress 3.7+.', 'ip-location-block' ) );
		}

		// Check providers
		$providers = IP_Location_Block_Provider::get_valid_providers( $settings, false, false, true );
		if ( empty( $providers ) ) {
			$this->add_admin_notice( 'error', sprintf(
				__( 'You should select at least one API at <a href="%s">Geolocation API settings</a>. Otherwise <strong>you\'ll be blocked</strong> after the cache expires.', 'ip-location-block' ),
				esc_url( add_query_arg( array(
					'page' => IP_Location_Block::PLUGIN_NAME,
					'tab'  => 0,
					'sec'  => 4
				), $network ) ) . '#' . IP_Location_Block::PLUGIN_NAME . '-section-4'
			) );
		} else {
			$providers   = IP_Location_Block_Provider::get_addons( $settings['providers'] );
			$is_core_api = ! empty( $settings['providers']['IP Location Block'] );
			if ( empty( $providers ) && ! $is_core_api ) {
				$this->add_admin_notice( 'error', sprintf(
					__( 'You should select at least one API for local database at <a href="%s">Geolocation API settings</a>. Otherwise access to the external API may slow down the site.', 'ip-location-block' ),
					esc_url( add_query_arg( array(
						'page' => IP_Location_Block::PLUGIN_NAME,
						'tab'  => 0,
						'sec'  => 4
					), $network ) ) . '#' . IP_Location_Block::PLUGIN_NAME . '-section-4'
				) );
			}
		}

		// Check consistency of matching rule
		if ( - 1 === (int) $settings['matching_rule'] ) {
			if ( false !== $updating ) {
				self::add_admin_notice( 'notice-warning', sprintf(
					__( 'Now downloading geolocation databases in background. After a little while, please check your country code and &#8220;<strong>Matching rule</strong>&#8221; at <a href="%s">Validation rules and behavior</a>.', 'ip-location-block' ),
					esc_url( add_query_arg( array( 'page' => IP_Location_Block::PLUGIN_NAME ), $network ) )
				) );
			} else {
				self::add_admin_notice( 'error', sprintf(
					__( 'The &#8220;<strong>Matching rule</strong>&#8221; is not set properly. Please confirm it at <a href="%s">Validation rules and behavior</a>.', 'ip-location-block' ),
					esc_url( add_query_arg( array( 'page' => IP_Location_Block::PLUGIN_NAME ), $network ) )
				) );
			}
		} // Check to finish updating matching rule
        elseif ( 'done' === $updating ) {
			delete_transient( IP_Location_Block::CRON_NAME );
			self::add_admin_notice( 'updated ', __( 'Local database and matching rule have been updated.', 'ip-location-block' ) );
		}

		// Check the rule contents
		if ( (int) $settings['matching_rule'] >= 0 ) {
			$key = '';
			if ( 0 === (int) $settings['matching_rule'] ) {
				$key = 'white_list';
			} else if ( 1 === (int) $settings['matching_rule'] ) {
				$key = 'black_list';
			}
			if ( isset( $settings[ $key ] ) ) {
				$feature = '';
				if ( strpos( $settings[ $key ], ':City:' ) !== false ) {
					$feature = 'city';
				} elseif ( strpos( $settings[ $key ], ':State:' ) !== false ) {
					$feature = 'state';
				} elseif ( strpos( $settings[ $key ], ':' ) !== false ) {
					$feature = 'city';
				} elseif ( strpos( $settings[ $key ], 'AS' ) !== false ) {
					$feature = 'asn';
				}
				if ( $feature ) {
					$valid_providers = IP_Location_Block_Provider::get_valid_providers( $settings, false, false, true );
					foreach ( $valid_providers as $valid_provider ) {
						$provider = IP_Location_Block_API::get_instance( $valid_provider, $settings );
						if ( $provider && ! $provider->supports( $feature ) ) {
							self::add_admin_notice( 'error', sprintf(
								__( 'Looks like you are trying to utilize <strong>%s</strong> level blocking, but the provider <strong>%s</strong> does not support that. In this case you may get invalid results. We strongly advise you to disable the provider <strong>%s</strong> from the settings below. For more details see <a target="_blank" href="%s">Supported Geo-Location Rule Formats</a>.', 'ip-location-block' ),
								$feature,
								$valid_provider,
								$valid_provider,
								'https://iplocationblock.com/codex/supported-geo-location-rule-formats/'
							) );
						}
					}
				}
			}
		}

		// Check additional blacklist / whitelist rules
		if ( ! empty( $settings['extra_ips'] ) && is_array( $settings['extra_ips'] ) ) {
			$asn_use = [];
			$invalid = [];
			$lists   = [
				'white_list' => __( 'Whitelist of extra IP addresses prior to country code/pattern (CIDR, ASN)', 'ip-location-block' ),
				'black_list' => __( 'Blacklist of extra IP addresses prior to country code/pattern (CIDR, ASN)', 'ip-location-block' ),
			];
			foreach ( $lists as $list_key => $list ) {
				$invalid[ $list_key ] = [];
				$asn_use[ $list_key ] = [];
				if ( ! empty( $settings['extra_ips'][ $list_key ] ) ) {
					$values = IP_Location_Block_Util::multiexplode( array( ",", "\n" ), $settings['extra_ips'][ $list_key ] );
					foreach ( $values as $i ) {
						$j      = explode( '/', $i, 2 );
						$j[1]   = isset( $j[1] ) ? min( 32, max( 0, (int) $j[1] ) ) : 32;
						$is_asn = ( ! empty( $settings['use_asn'] ) && strpos( $j[0], 'AS' ) !== false );
						$is_ip4 = filter_var( $j[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ); // Is valid ip4 subnet or ip4.
						$is_ip6 = filter_var( $j[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ); // Is valid ip6 subnet or ip6
						if ( ! ( $is_asn || $is_ip4 || $is_ip6 ) ) {
							$invalid[ $list_key ][] = $i;
						}
						if ( $is_asn ) {
							$asn_use[ $list_key ][] = $i;
						}
					}
				}
			}

			// Display issues related to format & collect information abount asn usage.
			$lists_with_asn = [];
			foreach ( $lists as $list_key => $list_name ) {
				if ( ! empty( $invalid[ $list_key ] ) ) {
					self::add_admin_notice( 'error', sprintf(
						__( 'The option <strong>"%s"</strong> is not properly configured, it contains invalid rules: <strong>%s</strong>. For more details please check <a target="_blank" href="%s">Validation rules and behavior</a>', 'ip-location-block' ),
						$list_name,
						implode( ', ', $invalid[ $list_key ] ),
						esc_url( 'https://iplocationblock.com/codex/validation-rules-and-behavior/#whitelistblacklist-of-extra-ip-addresses-prior-to-country-code' )
					) );
				}
				if ( ! empty( $asn_use[ $list_key ] ) ) {
					$lists_with_asn[] = $list_name;
				}
			}

			if ( ! empty( $lists_with_asn ) ) {
				// FInd providers that doesn't support ASN and are active
				$_unsupported_pr = [];
				$valid_providers = IP_Location_Block_Provider::get_valid_providers( $settings, false, false, true );
				foreach ( $valid_providers as $valid_provider ) {
					$provider = IP_Location_Block_API::get_instance( $valid_provider, $settings );
					if ( $provider && ! $provider->supports( 'asn' ) ) {
						$_unsupported_pr[] = $valid_provider;
					}
				}
				if ( ! empty( $_unsupported_pr ) ) {
					$message = __( 'Looks like you want to block ASN by using the <strong>%s</strong> option(s) but your current geolocation provider(s) "<strong>%s</strong>" do not support it. For more details about the supported providers please scroll down to <strong>Geolocation API settings</strong> and check with the <strong>Compare</strong> button.', 'ip-location-block' );
					self::add_admin_notice( 'error', sprintf(
						$message,
						implode( ', ', $lists_with_asn ),
						implode( ', ', $_unsupported_pr ),
					) );
				}
			}
		}

		// Check self blocking (skip during updating)
		if ( false === $updating && 1 === (int) $settings['validation']['login'] ) {
			$instance = IP_Location_Block::get_instance();
			$validate = $instance->validate_ip( 'login', $settings, true, false ); // skip authentication check

			switch ( $validate['result'] ) {
				case 'limited':
					self::add_admin_notice( 'error',
						__( 'Once you logout, you will be unable to login again because the number of login attempts reaches the limit.', 'ip-location-block' ) . ' ' .
						sprintf(
							__( 'Please remove your IP address in &#8220;%1$sStatistics in IP address cache%2$s&#8221; on &#8220;%3$sStatistics%4$s&#8221; tab to prevent locking yourself out.', 'ip-location-block' ),
							'<strong><a href="' . esc_url( add_query_arg( array(
									'page' => IP_Location_Block::PLUGIN_NAME,
									'tab'  => 1,
									'sec'  => 2
								), $adminurl ) . '#' . IP_Location_Block::PLUGIN_NAME . '-section-2' ) . '">', '</a></strong>',
							'<strong>', '</strong>'
						)
					);
					break;

				case 'blocked':
				case 'extra':
					self::add_admin_notice( 'error',
						( $settings['matching_rule'] ?
							__( 'Once you logout, you will be unable to login again because your country code or IP address is in the blacklist.', 'ip-location-block' ) :
							__( 'Once you logout, you will be unable to login again because your country code or IP address is not in the whitelist.', 'ip-location-block' )
						) . ' ' .
						( 'ZZ' !== $validate['code'] ?
							sprintf(
								__( 'Please check your &#8220;%sValidation rules and behavior%s&#8221;.', 'ip-location-block' ),
								'<strong><a href="' . esc_url( add_query_arg( array(
										'page' => IP_Location_Block::PLUGIN_NAME,
										'tab'  => 0,
										'sec'  => 0
									), $network ) . '#' . IP_Location_Block::PLUGIN_NAME . '-section-0' ) . '">', '</a></strong>'
							) :
							sprintf(
								__( 'Please confirm your local geolocation database files exist at &#8220;%sLocal database settings%s&#8221; section, or remove your IP address in cache at &#8220;%sStatistics in cache%s&#8221; section.', 'ip-location-block' ),
								'<strong><a href="' . esc_url( add_query_arg( array(
										'page' => IP_Location_Block::PLUGIN_NAME,
										'tab'  => 0,
										'sec'  => 5
									), $network ) . '#' . IP_Location_Block::PLUGIN_NAME . '-section-5' ) . '">', '</a></strong>',
								'<strong><a href="' . esc_url( add_query_arg( array(
										'page' => IP_Location_Block::PLUGIN_NAME,
										'tab'  => 1,
										'sec'  => 2
									), $adminurl ) . '#' . IP_Location_Block::PLUGIN_NAME . '-section-2' ) . '">', '</a></strong>'
							)
						)
					);
					break;
			}
		}

		// Check consistency of emergency login link
		if ( isset( $settings['login_link'] ) && $settings['login_link']['link'] && ! IP_Location_Block_Util::verify_link( $settings['login_link']['link'], $settings['login_link']['hash'] ) ) {
			self::add_admin_notice( 'error',
				sprintf(
					__( 'Emergency login link is outdated. Please delete it once and generate again at &#8220;%sPlugin settings%s&#8221; section. Also do not forget to update favorites / bookmarks in your browser.', 'ip-location-block' ),
					'<strong><a href="' . esc_url( add_query_arg( array(
							'page' => IP_Location_Block::PLUGIN_NAME,
							'tab'  => 0,
							'sec'  => 7
						), $network ) . '#' . IP_Location_Block::PLUGIN_NAME . '-section-7' ) . '">', '</a></strong>'
				)
			);
		}

		// Check activation of IP Geo Allow
		if ( $settings['validation']['timing'] && is_plugin_active( 'ip-geo-allow/index.php' ) ) {
			self::add_admin_notice( 'error',
				__( '&#8220;mu-plugins&#8221; (ip-location-block-mu.php) at &#8220;Validation timing&#8221; is imcompatible with <strong>IP Geo Allow</strong>. Please select &#8220;init&#8221; action hook.', 'ip-location-block' )
			);
		}
	}

	/**
	 * Setup menu and option page for this plugin
	 *
	 */
	public function setup_admin_page() {
		$settings = IP_Location_Block::get_option();

		// Register the administration menu.
		$this->add_plugin_admin_menu( $settings );

		// Avoid multiple validation.
		if ( 'GET' === IP_Location_Block_Util::get_request_method() ) {
			$this->diagnose_admin_screen( $settings );
		}

		// Register settings page only if it is needed.
		if ( ( isset( $_GET ['page'] ) && IP_Location_Block::PLUGIN_NAME === $_GET ['page'] ) ||
		     ( isset( $_POST['option_page'] ) && IP_Location_Block::PLUGIN_NAME === $_POST['option_page'] ) ) {
			$this->register_settings_tab();
		} // Add an action link pointing to the options page. @since  0.2.7
		else {
			add_filter( 'plugin_row_meta', array( $this, 'add_plugin_meta_links' ), 10, 2 );
			add_filter( 'plugin_action_links_' . IP_LOCATION_BLOCK_BASE, array( $this, 'add_action_links' ), 10, 1 );
		}

		// Register scripts for admin.
		add_action( 'admin_enqueue_scripts', array( 'IP_Location_Block', 'enqueue_nonce' ), 0 );

		// Show admin notices at the place where it should be. @since  0.2.5.0
		add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
		add_action( 'network_admin_notices', array( $this, 'show_admin_notices' ) );
		// Welcome screen
		add_action( 'admin_notices', array( $this, 'show_intro_notice' ) );
		// API key upgrade notice
		add_action( 'admin_notices', array( $this, 'show_api_key_upgrade_notice' ) );
	}

	/**
	 * Get cookie that indicates open/close section
	 *
	 */
	public function get_cookie() {
		static $cookie = array();

		if ( empty( $cookie ) && ! empty( $_COOKIE[ IP_Location_Block::PLUGIN_NAME ] ) ) {
			foreach ( explode( '&', $_COOKIE[ IP_Location_Block::PLUGIN_NAME ] ) as $i => $v ) {
				list( $i, $v ) = explode( '=', $v );
				$cookie[ $i ] = str_split( $v );
			}
		}

		return $cookie;
	}

	/**
	 * Prints out all settings sections added to a particular settings page
	 *
	 * wp-admin/includes/template.php @since  0.2.7.0
	 */
	private function do_settings_sections( $page, $tab ) {
		global $wp_settings_sections, $wp_settings_fields;

		// target section to be opened
		$target = isset( $_GET['sec'] ) ? (int) $_GET['sec'] : - 1;

		if ( isset( $wp_settings_sections[ $page ] ) ) {
			$index  = 0; // index of fieldset
			$cookie = $this->get_cookie();

			foreach ( (array) $wp_settings_sections[ $page ] as $section ) {
				// TRUE if open ('o') or FALSE if close ('x')
				$stat = empty( $cookie[ $tab ][ $index ] ) || 'x' !== $cookie[ $tab ][ $index ] || $index === $target;

				echo "\n", '<fieldset id="', IP_Location_Block::PLUGIN_NAME, '-section-', $index, '" class="', IP_Location_Block::PLUGIN_NAME, '-field panel panel-default" data-section="', $index, '">', "\n",
				'<legend class="panel-heading"><h3 class="', IP_Location_Block::PLUGIN_NAME, ( $stat ? '-dropdown' : '-dropup' ), '">',
				is_array( $section['title'] ) ? $section['title'][0] . '<span class="' . IP_Location_Block::PLUGIN_NAME . '-help-link">[ ' . $section['title'][1] . ' ]</span>' : $section['title'],
				'</h3></legend>', "\n", '<div class="panel-body',
				( $stat ? ' ' . IP_Location_Block::PLUGIN_NAME . '-border"' : '"' ),
				( $stat || ( 4 === $tab && $index ) ? '>' : ' style="display:none">' ), "\n";

				if ( $section['callback'] ) {
					call_user_func( $section['callback'], $section );
				}

				if ( isset( $wp_settings_fields,
					$wp_settings_fields[ $page ],
					$wp_settings_fields[ $page ][ $section['id'] ] ) ) {
					echo '<table class="form-table">';
					do_settings_fields( $page, $section['id'] );
					echo "</table>\n";
				}

				echo "</div>\n</fieldset>\n";
				++ $index;
			}
		}
	}

	/**
	 * Render the settings page for this plugin.
	 *
	 */
	public function display_plugin_admin_page() {
		$tab  = $this->admin_tab;
		$tabs = array(
			5 => __( 'Sites list', 'ip-location-block' ),
			0 => __( 'Settings', 'ip-location-block' ),
			1 => __( 'Statistics', 'ip-location-block' ),
			4 => __( 'Logs', 'ip-location-block' ),
			2 => __( 'Search', 'ip-location-block' ),
			3 => __( 'Attribution', 'ip-location-block' ),
		);

		$settings = IP_Location_Block::get_option();
		$cookie   = $this->get_cookie();
		$title    = esc_html( get_admin_page_title() );

		// Target page that depends on the network multisite or not.
		if ( 'options-general.php' === $GLOBALS['pagenow'] ) {
			$action = 'options.php';
			unset( $tabs[5] ); // Sites list
			if ( $this->is_network_admin ) {
				$title .= ' <span class="ip-location-block-menu-link"> [ ';
				$title .= '<a href="' . esc_url( add_query_arg( array(
						'page' => IP_Location_Block::PLUGIN_NAME,
						'tab'  => 5
					), $this->dashboard_url( true ) ) ) . '" target="_self">' . __( 'Sites list', 'ip-location-block' ) . '</a>';
				if ( $settings['network_wide'] ) {
					unset( $tabs[0] ); // Settings
					$title .= ' / <a href="' . esc_url( add_query_arg( array(
							'page' => IP_Location_Block::PLUGIN_NAME,
							'tab'  => 0
						), $this->dashboard_url( true ) ) ) . '" target="_self">' . __( 'Settings', 'ip-location-block' ) . '</a>';
				}
				$title .= ' ]</span>';
			}
		} // '/wp-admin/network/admin.php'
		else {
			// `edit.php` is an action handler for Multisite administration dashboard.
			// `edit.php` ==> do action `network_admin_edit_ip-location-block` ==> `validate_network_settings()`
			$action = 'edit.php?action=' . IP_Location_Block::PLUGIN_NAME;
			if ( $this->is_network_admin ) {
				unset( $tabs[1], $tabs[4], $tabs[2], $tabs[3] ); // Statistics, Logs, Search, Attribution
				$title .= ' <span class="ip-location-block-menu-link"> [ ';
				$title .= __( 'Sites list', 'ip-location-block' );
				if ( $settings['network_wide'] ) {
					$title .= ' / ' . __( 'Settings', 'ip-location-block' );
				} else {
					unset( $tabs[0] ); // Settings
				}
				$title .= ' ]</span>';
			}
		}

        ?>
        <div class="wrap ip-location-block-wrap">
            <h2><?php echo $title; ?></h2>
            <?php
            include(IP_LOCATION_BLOCK_PATH.'admin/includes/status.php');
            ?>
            <h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $val ) {
					echo '<a href="?page=', IP_Location_Block::PLUGIN_NAME, '&amp;tab=', $key, '" class="nav-tab', ( $tab === $key ? ' nav-tab-active' : '' ), '">', $val, '</a>';
				} ?>
            </h2>
            <p class="ip-location-block-navi-link">
               <span class="ip-location-block-toggle-sections-wrap">[ <a id="ip-location-block-toggle-sections" href="#!"><?php _e( 'Toggle all', 'ip-location-block' ); ?></a> ]</span>
				<?php if ( 4 === $tab ) { /* Logs tab */ ?>
                    <input id="ip-location-block-live-update"
                           type="checkbox"<?php checked( isset( $cookie[4][1] ) && 'o' === $cookie[4][1] );
					disabled( $settings['validation']['reclogs'] && extension_loaded( 'pdo_sqlite' ), false ); ?> />
                    <label for="ip-location-block-live-update">
                        <dfn title="<?php _e( 'Independent of &#8220;Privacy and record settings&#8221;, you can see all the requests validated by this plugin in almost real time.', 'ip-location-block' ); ?>"><?php _e( 'Live update', 'ip-location-block' ); ?></dfn>
                    </label>
				<?php } elseif ( 5 === $tab ) { /* Sites list tab */ ?>
                    <input id="ip-location-block-open-new"
                           type="checkbox"<?php checked( isset( $cookie[5][1] ) && 'o' === $cookie[5][1] ); ?> /><label
                            for="ip-location-block-open-new">
                        <dfn title="<?php _e( 'Open a new window on clicking the link in the chart.', 'ip-location-block' ); ?>"><?php _e( 'Open a new window', 'ip-location-block' ); ?></dfn>
                    </label>
				<?php } ?></p>
            <form method="post" action="<?php echo $action; ?>"
                  id="<?php echo IP_Location_Block::PLUGIN_NAME, '-', $tab; ?>"<?php if ( $tab ) {
				echo " class=\"", IP_Location_Block::PLUGIN_NAME, "-inhibit\"";
			} ?>>
				<?php
				settings_fields( IP_Location_Block::PLUGIN_NAME );
				$this->do_settings_sections( IP_Location_Block::PLUGIN_NAME, $tab );
				if ( 0 === $tab ) {
					submit_button();
				} // @since 3.1
				?>
            </form>
			<?php if ( 2 === $tab ) { /* Search tab */ ?>
                <div id="ip-location-block-apis"></div>
                <div id="ip-location-block-map"></div>
                <div id="ip-location-block-whois"></div>
			<?php } elseif ( 3 === $tab ) { /* Attribute tab */
				// show attribution (higher priority order)
				$tab = array();
				foreach ( IP_Location_Block_Provider::get_addons() as $provider ) {
					if ( $geo = IP_Location_Block_API::get_instance( $provider, $settings ) ) {
						$tab[] = $geo->get_attribution();
					}
				}
				echo '<p>', implode( '<br />', $tab ), "</p>\n";
				echo '<p>', __( 'Thanks for providing these great services for free.', 'ip-location-block' ), "<br />\n";
				echo __( '(Most browsers will redirect you to each site <a href="https://iplocationblock.com/referer-checker/" title="Referer Checker">without referrer when you click the link</a>.)', 'ip-location-block' ), "</p>\n";
			} ?>
			<?php if ( defined( 'IP_LOCATION_BLOCK_DEBUG' ) && IP_LOCATION_BLOCK_DEBUG ) {
				echo '<p>', get_num_queries(), ' queries. ', timer_stop( 0 ), ' seconds. ', memory_get_usage(), " bytes.</p>\n";
			} ?>
            <p id="ip-location-block-back-to-top">[ <a href="#"><?php _e( 'Back to top', 'ip-location-block' ); ?></a> ]
            </p>
        </div>
		<?php
	}

	/**
	 * Initializes the options page by registering the Sections and Fields.
	 *
	 */
	private function register_settings_tab() {
		$files = array(
			0 => 'admin/includes/tab-settings.php',
			1 => 'admin/includes/tab-statistics.php',
			4 => 'admin/includes/tab-accesslog.php',
			2 => 'admin/includes/tab-geolocation.php',
			3 => 'admin/includes/tab-attribution.php',
			5 => 'admin/includes/tab-network.php',
		);

		require_once IP_LOCATION_BLOCK_PATH . $files[ $this->admin_tab ];
		IP_Location_Block_Admin_Tab::tab_setup( $this, $this->admin_tab );
	}

	/**
	 * Function that fills the field with the desired inputs as part of the larger form.
	 * The 'id' and 'name' should match the $id given in the add_settings_field().
	 *
	 * @param array $args ['value'] must be sanitized because it comes from external.
	 */
	public function callback_field( $args ) {
		if ( ! empty( $args['before'] ) ) {
			echo $args['before'], "\n";
		} // must be sanitized at caller

		// field
		$id = $name = '';
		if ( ! empty( $args['field'] ) ) {
			$id   = sprintf( '%s_%s', $args['option'], $args['field'] );
			$name = sprintf( '%s[%s]', $args['option'], $args['field'] );
		}

		// sub field
		$sub_id = $sub_name = '';
		if ( ! empty( $args['sub-field'] ) ) {
			$sub_id   = sprintf( '_%s', $args['sub-field'] );
			$sub_name = sprintf( '[%s]', $args['sub-field'] );
		}

		switch ( $args['type'] ) {
			case 'check-provider':
				?>
                <div class="ip-location-block-providers ip-location-block-providers-compact">
                    <table class="wp-list-table widefat striped ip-location-block-services">
                        <thead>
                        <tr>
                            <th scope="col" class="manage-column"><?php _e( 'Name', 'ip-location-block' ); ?></th>
                            <th scope="col" class="manage-column"><?php _e( 'API Key', 'ip-location-block' ); ?></th>
                            <th scope="col"
                                class="manage-column meta-compare meta-requests"><?php _e( 'Free Requests', 'ip-location-block' ); ?></th>
                            <th scope="col"
                                class="manage-column meta-compare meta-ipv4"><?php _e( 'IPv4 Lookups', 'ip-location-block' ); ?></th>
                            <th scope="col"
                                class="manage-column meta-compare meta-ipv6"><?php _e( 'IPv6 Lookups', 'ip-location-block' ); ?></th>
                            <th scope="col"
                                class="manage-column meta-compare meta-asn"><?php _e( 'ASN Blocking', 'ip-location-block' ); ?></th>
                            <th scope="col"
                                class="manage-column meta-compare meta-asn"><?php _e( 'City Blocking', 'ip-location-block' ); ?></th>
                            <th scope="col"
                                class="manage-column meta-compare meta-asn"><?php _e( 'State Blocking', 'ip-location-block' ); ?></th>
                            <th scope="col"
                                class="manage-column meta-compare meta-limits"><?php _e( 'Limitations', 'ip-location-block' ); ?></th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody id="the-list">
						<?php foreach ( $args['providers'] as $key => $val ):
							$id = sprintf( "%s_providers_%s", $args['option'], $key );
							$name = sprintf( '%s[providers][%s]', $args['option'], $key );
							$stat = ( null === $val && ! isset( $args['value'][ $key ] ) ) ||
							        ( false === $val && ! empty( $args['value'][ $key ] ) ) ||
							        ( is_string( $val ) && ! empty( $args['value'][ $key ] ) );


							?>
                            <tr class="format-standard hentry <?php echo 'IP Location Block' === $key ? 'highlighted' : ''; ?>">
                                <td>
									<?php
									$checked  = $stat && - 1 !== (int) $val ? 'checked' : '';
									$disabled = - 1 === (int) $val ? 'disabled' : '';
									$cssClass = isset( $args['local'] ) && $args['local'] ? 'API-local' : 'API-remote';
									echo sprintf(
										'<input %s %s id="%s" type="checkbox" name="%s" value="%s" class="%s"/> %s',
										esc_attr( $checked ),
										esc_attr( $disabled ),
										esc_attr( $id ),
										esc_attr( $name ),
										esc_attr( $val ),
										esc_attr( $cssClass ),
										IP_Location_Block_Provider::format_provider_meta( $key, 'name' )
									);
									?>
                                </td>
                                <td style="padding-left: 0;padding-right: 0;">
									<?php
									if ( ! is_null( $val ) ) {
										$value = ! empty( $args['value'][ $key ] ) ? $args['value'][ $key ] : '';
										echo sprintf( '<input type="text" class="code" name="%s" value="%s" placeholder="%s"/>', esc_attr( $name ), esc_attr( $value ), __( 'API Key', 'ip-location-block' ) );
									} ?>
                                </td>
                                <td class="meta-compare meta-requests"><?php echo IP_Location_Block_Provider::format_provider_meta( $key, 'requests' ); ?></td>
                                <td class="meta-compare meta-ipv4"><?php echo sprintf( '<span class="dashicons %s"></span>', IP_Location_Block_Provider::supports( $key, 'ipv4' ) ? 'dashicons-yes' : 'dashicons-no' ); ?></td>
                                <td class="meta-compare meta-ipv6"><?php echo sprintf( '<span class="dashicons %s"></span>', IP_Location_Block_Provider::supports( $key, 'ipv6' ) ? 'dashicons-yes' : 'dashicons-no' ); ?></td>
                                <td class="meta-compare meta-asn"><?php echo sprintf( '<span class="dashicons %s"></span>', IP_Location_Block_Provider::supports( $key, array(
										'asn',
										'asn_database'
									) ) ? 'dashicons-yes' : 'dashicons-no' ); ?></td>
                                <td class="meta-compare meta-city"><?php echo sprintf( '<span class="dashicons %s"></span>', IP_Location_Block_Provider::supports( $key, array( 'city' ) ) ? 'dashicons-yes' : 'dashicons-no' ); ?></td>
                                <td class="meta-compare meta-state"><?php echo sprintf( '<span class="dashicons %s"></span>', IP_Location_Block_Provider::supports( $key, array( 'state' ) ) ? 'dashicons-yes' : 'dashicons-no' ); ?></td>
                                <td class="meta-compare meta-limits"><?php echo IP_Location_Block_Provider::format_provider_meta( $key, 'limits' ); ?></td>
                                <td><?php echo IP_Location_Block_Provider::format_provider_meta( $key, 'signup-button' ); ?></td>
                            </tr>
						<?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="ip-location-block-providers-actions">
                        <a id="ip-location-block-providers-compare-toggle" href="#"
                           class="button-primary"><?php _e( 'Compare', 'ip-location-block' ); ?></a>
                    </div>
                </div>
				<?php
				break;
			case
			'checkboxes':
				echo "\n<ul class=\"ip-location-block-list\">\n";
				foreach ( $args['list'] as $key => $val ) { ?>
                    <li>
                        <input type="checkbox" id="<?php echo $id, $sub_id, '_', $key; ?>"
                               name="<?php echo $name, $sub_name, '[', $key, ']'; ?>" value="<?php echo $key; ?>"<?php
						checked( is_array( $args['value'] ) ? ! empty( $args['value'][ $key ] ) : ( $key & $args['value'] ? true : false ) ); ?> /><label
                                for="<?php
								echo $id, $sub_id, '_', $key; ?>"><?php
							if ( isset( $args['desc'][ $key ] ) ) {
								echo '<dfn title="', $args['desc'][ $key ], '">', $val, '</dfn>';
							} else {
								echo $val;
							}
							?></label>
                    </li>
					<?php
				}
				echo "</ul>\n";
				break;

			case 'checkbox': ?>
                <input type="checkbox" id="<?php echo $id, $sub_id; ?>" name="<?php echo $name, $sub_name; ?>"
                       value="1"<?php
				checked( esc_attr( $args['value'] ) );
				disabled( ! empty( $args['disabled'] ), true ); ?> /><label for="<?php
				echo $id, $sub_id; ?>"><?php
					if ( isset( $args['text'] ) ) {
						echo esc_attr( $args['text'] );
					} elseif ( isset( $args['html'] ) ) {
						echo $args['html'];
					} else {
						_e( 'Enable', 'ip-location-block' );
					}
					?></label>
				<?php
				break;

			case 'select':
			case 'select-text':
				$desc       = '';
				$field_id   = sprintf( '%s%s', $id, $sub_id );
				$field_name = sprintf( '%s%s', $name, $sub_name );
				echo sprintf( '<select id="%s" name="%s" %s>%s', esc_attr( $field_id ), esc_attr( $field_name ), isset( $args['attr'] ) ? esc_attr( $args['attr'] ) : '', PHP_EOL );
				foreach ( $args['list'] as $key => $val ) {
					echo "\t<option value=\"$key\"", null === $val ? ' selected disabled' : ( is_array( $args['value'] ) ? selected( in_array( $key, $args['value'] ), true, false ) : selected( $args['value'], $key, false ) );
					if ( isset( $args['desc'][ $key ] ) ) {
						echo ' data-desc="', $args['desc'][ $key ], '"';
						$key === $args['value'] and $desc = $args['desc'][ $key ];
					}
					echo '>', ( null === $val ? __( 'Select one', 'ip-location-block' ) : $val ), '</option>', "\n";
				}
				echo "</select>\n";

				if ( isset( $args['desc'] ) ) {
					echo '<p class="ip-location-block-desc">', $desc, "</p>\n";
				}

				if ( 'select' === $args['type'] ) {
					break;
				}

				echo "<br />\n";
				$sub_id        = '_' . $args['txt-field']; // possible value of 'txt-field' is 'msg'
				$sub_name      = '[' . $args['txt-field'] . ']';
				$args['value'] = $args['text']; // should be escaped because it can contain allowed tags

			case 'text': ?>
                <input type="text" class="regular-text code" id="<?php echo $id, $sub_id; ?>"
                       name="<?php echo $name, $sub_name; ?>" value="<?php echo esc_attr( $args['value'] ); ?>"<?php
				disabled( ! empty( $args['disabled'] ) );
				if ( isset( $args['placeholder'] ) ) {
					echo ' placeholder="', esc_html( $args['placeholder'] ), '"';
				} ?> />
				<?php
				break; // disabled @since 3.0

			case 'textarea': ?>
                <textarea class="regular-text code" id="<?php echo $id, $sub_id; ?>"
                          name="<?php echo $name, $sub_name; ?>"<?php
				disabled( ! empty( $args['disabled'] ) );
				if ( isset( $args['placeholder'] ) ) {
					echo ' placeholder="', esc_html( $args['placeholder'] ), '"';
				} ?>><?php
					echo esc_html( $args['value'] ); ?></textarea>
				<?php
				break;

			case 'button': ?>
                <input type="button" class="button-secondary" id="<?php echo $id; ?>"
                       value="<?php echo esc_attr( $args['value'] ); ?>"
					<?php disabled( ! empty( $args['disabled'] ) ); ?>/>
				<?php
				break;

			case 'html':
				echo "\n", $args['value'], "\n"; // must be sanitized at caller
				break;
		}

		if ( ! empty( $args['after'] ) ) {
			echo $args['after'], "\n";
		} // must be sanitized at caller
	}

	/**
	 * Sanitize options before saving them into DB.
	 *
	 * @param array $input The values to be validated.
	 *
	 * @return mixed
	 * @link https://codex.wordpress.org/Function_Reference/sanitize_option
	 * @link https://codex.wordpress.org/Function_Reference/sanitize_text_field
	 * @link https://codex.wordpress.org/Plugin_API/Filter_Reference/sanitize_option_$option
	 * @link https://core.trac.wordpress.org/browser/trunk/src/wp-includes/formatting.php
	 * @link https://codex.wordpress.org/Validating_Sanitizing_and_Escaping_User_Data
	 */
	public function sanitize_options( $input ) {
		// setup base options
		$output  = IP_Location_Block::get_option();
		$default = IP_Location_Block::get_default();

		// Integrate posted data into current settings because it can be a part of hole data
		$input = $this->array_replace_recursive(
			$output = $this->preprocess_options( $output, $default ), $input
		);

		// restore the 'signature' that might be transformed to avoid self blocking
		if ( isset( $input['signature'] ) && false === strpos( $input['signature'], ',' ) ) {
			$input['signature'] = str_rot13( base64_decode( $input['signature'] ) );
		}

		/**
		 * Sanitize a string from user input
		 */
		foreach ( $output as $key => $val ) {
			$key = sanitize_text_field( $key ); // @since 3.0.0 can't use sanitize_key() because of capital letters.

			// delete old key
			if ( ! array_key_exists( $key, $default ) ) {
				unset( $output[ $key ] );
				continue;
			}

			switch ( $key ) {
				case 'providers':
					foreach ( IP_Location_Block_Provider::get_providers() as $provider => $api ) {
						// need no key
						if ( null === $api ) {
							if ( isset( $input[ $key ][ $provider ] ) ) {
								unset( $output[ $key ][ $provider ] );
							} else {
								$output['providers'][ $provider ] = '';
							}
						} // non-commercial
                        elseif ( false === $api ) {
							if ( isset( $input[ $key ][ $provider ] ) ) {
								$output['providers'][ $provider ] = '@';
							} else {
								unset( $output[ $key ][ $provider ] );
							}
						} // need key
						else {
							$output[ $key ][ $provider ] =
								isset( $input[ $key ][ $provider ] ) ? sanitize_text_field( $input[ $key ][ $provider ] ) : '';
						}
					}
					break;

				case 'comment':
					if ( isset( $input[ $key ]['pos'] ) ) {
						$output[ $key ]['pos'] = (int) $input[ $key ]['pos'];
					}

					if ( isset( $input[ $key ]['msg'] ) ) {
						$output[ $key ]['msg'] = IP_Location_Block_Util::kses( $input[ $key ]['msg'] );
					}
					break;

				case 'white_list':
				case 'black_list':
					$input_value = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : '';
                    $input_parts = self::sanitize_rules($input_value);
					$output[ $key ] = implode( ',', $input_parts );
					break;

				case 'mimetype':
					if ( isset( $input[ $key ]['white_list'] ) ) { // for json file before 3.0.3
						foreach ( $input[ $key ]['white_list'] as $k => $v ) {
							$output[ $key ]['white_list'][ sanitize_text_field( $k ) ] = sanitize_mime_type( $v ); // @since 3.1.3
						}
					}
					if ( isset( $input[ $key ]['black_list'] ) ) { // for json file before 3.0.3
						$output[ $key ]['black_list'] = sanitize_text_field( $input[ $key ]['black_list'] );
					}
					if ( isset( $input[ $key ]['capability'] ) ) {
						$output[ $key ]['capability'] = array_map( 'sanitize_key', explode( ',', trim( $input[ $key ]['capability'], ',' ) ) ); // @since 3.0.0
					}
					break;

				case 'metadata':
					if ( isset( $input[ $key ] ) ) {
						if ( is_string( $input[ $key ]['pre_update_option'] ) ) {
							$output[ $key ]['pre_update_option'] = array_map( 'sanitize_key', explode( ',', trim( $input[ $key ]['pre_update_option'], ',' ) ) ); // @since 3.0.17
						}
						if ( is_string( $input[ $key ]['pre_update_site_option'] ) ) {
							$output[ $key ]['pre_update_site_option'] = array_map( 'sanitize_key', explode( ',', trim( $input[ $key ]['pre_update_site_option'], ',' ) ) ); // @since 3.0.17
						}
					}
					break;


				default: // checkbox, select, text
					// single field
					if ( ! is_array( $default[ $key ] ) ) {
						// for checkbox
						if ( is_bool( $default[ $key ] ) ) {
							$output[ $key ] = ! empty( $input[ $key ] );
						} // for implicit data
                        elseif ( isset( $input[ $key ] ) ) {
							$output[ $key ] = is_int( $default[ $key ] ) ?
								(int) $input[ $key ] :
								IP_Location_Block_Util::kses( trim( $input[ $key ] ), false );
						} // otherwise keep as it is
						else {
						}
					} // sub field
					else {
						foreach ( array_keys( (array) $val ) as $sub ) {
							// delete old key
							if ( ! array_key_exists( $sub, $default[ $key ] ) ) {
								unset( $output[ $key ][ $sub ] );
							} // for checkbox
                            elseif ( is_bool( $default[ $key ][ $sub ] ) ) {
								$output[ $key ][ $sub ] = ! empty( $input[ $key ][ $sub ] );
							} // for array
                            elseif ( is_array( $default[ $key ][ $sub ] ) ) {
								$output[ $key ][ $sub ] = empty( $input[ $key ][ $sub ] ) ?
									array() : $input[ $key ][ $sub ];
							} // for implicit data
                            elseif ( isset( $input[ $key ][ $sub ] ) ) {
								// for checkboxes
								if ( is_array( $input[ $key ][ $sub ] ) ) {
									foreach ( $input[ $key ][ $sub ] as $k => $v ) {
										$output[ $key ][ $sub ] |= $v;
									}
								} else {
									$output[ $key ][ $sub ] = ( is_int( $default[ $key ][ $sub ] ) ?
										(int) $input[ $key ][ $sub ] :
										IP_Location_Block_Util::kses( trim( $input[ $key ][ $sub ] ), false )
									);
								}
							} // otherwise keep as it is
							else {
							}
						}
					}

                    if($key === 'public') {
                        foreach(['white_list', 'black_list'] as $prop) {
	                        $input_value             = isset( $input[ $key ][ $prop ] ) ? sanitize_text_field( $input[ $key ][ $prop ] ) : '';
	                        $input_parts             = self::sanitize_rules( $input_value );
	                        $output[ $key ][ $prop ] = implode( ',', $input_parts );
                        }
                    }
			}
		}

		// Check and format each setting data
		return $this->postprocess_options( $output, $default );
	}

	/**
     * Sanitizes the matching rule strings
	 * @param $input_value
	 *
	 * @return string[]
	 */
    public function sanitize_rules($input_value) {
	    $input_parts = explode( ',', $input_value );
	    foreach ( $input_parts as $index => $input_part ) {
		    $rule_parts = explode( ':', trim( $input_part ) );
		    if ( ! empty( $rule_parts[0] ) ) {
			    $rule_parts[0] = preg_replace( '/[^A-Z,]/', '', strtoupper( $rule_parts[0] ) );
		    }
		    if ( ! empty( $rule_parts[1] ) ) {
			    $rule_parts[1] = ucwords( strtolower( $rule_parts[1] ) );
		    }
		    if ( ! empty( $rule_parts[2] ) ) {
			    $rule_parts[2] = ucwords( strtolower( $rule_parts[2] ) );
		    }
		    $input_parts[ $index ] = implode( ':', $rule_parts );
	    }

        return $input_parts;
    }

	// Initialize not on the form (mainly unchecked checkbox)
	public function preprocess_options( $output, $default ) {
		// initialize checkboxes not in the form (added after 2.0.0, just in case)
		foreach (
			array(
				'providers',
				'save_statistics',
				'cache_hold',
				'anonymize',
				'restrict_api',
				'network_wide',
				'clean_uninstall',
				'simulate',
				'use_asn',
			) as $key
		) {
			$output[ $key ] = is_array( $default[ $key ] ) ? array() : 0;
		}

		// initialize checkboxes not in the form
		foreach ( array( 'comment', 'login', 'admin', 'ajax', 'plugins', 'themes', 'public', 'mimetype' ) as $key ) {
			$output['validation'][ $key ] = 0;
		}

		// initialize checkboxes not in the form
		$output['mimetype']['white_list'] = array();

		// keep disabled checkboxes not in the form
		foreach ( array( 'admin', 'plugins', 'themes' ) as $key ) {
			$output['exception'][ $key ] = array();
		}

		// keep disabled checkboxes not in the form
		foreach (
			array(
				'target_pages',
				'target_posts',
				'target_cates',
				'target_tags',
				'dnslkup',
				'behavior'
			) as $key
		) {
			$output['public'][ $key ] = is_array( $default['public'][ $key ] ) ? array() : false;
		}

		// disabled in case IP address cache is disabled
		empty( $output['cache_hold'] ) and $output['login_fails'] = - 1;

		// 3.0.5 Live update
		$output['live_update']['in_memory'] = 0;

		// 3.0.9 Fix for `login_action`
		foreach ( array( 'login', 'register', 'resetpass', 'lostpassword', 'postpass' ) as $key ) {
			$output['login_action'][ $key ] = false;
		}

		return $output;
	}

	// Check and format each setting data
	private function postprocess_options( $output, $default ) {
		// normalize escaped char
		$output           ['response_msg'] = preg_replace( '/\\\\/', '', $output           ['response_msg'] );
		$output['public']['response_msg']  = preg_replace( '/\\\\/', '', $output['public']['response_msg'] );
		$output['comment']['msg']          = preg_replace( '/\\\\/', '', $output['comment']['msg'] );

		// sanitize proxy
		$output['validation']['proxy'] = implode( ',', $this->trim(
			preg_replace( '/[^\w,]/', '', strtoupper( $output['validation']['proxy'] ) )
		) );

		// sanitize and format ip address (text area)
		$key                               = array( '/[^\w\n\.\/,:]/', '/([\s,])+/', '/(?:^,|,$)/' );
		$val                               = array( '', '$1', '' );
		$output['extra_ips']['white_list'] = preg_replace( $key, $val, trim( $output['extra_ips']['white_list'] ) );
		$output['extra_ips']['black_list'] = preg_replace( $key, $val, trim( $output['extra_ips']['black_list'] ) );

		// format and reject invalid words which potentially blocks itself (text area)
		array_shift( $key );
		array_shift( $val );
		$output['signature'] = preg_replace( $key, $val, trim( $output['signature'] ) );
		$output['signature'] = implode( ',', $this->trim( $output['signature'] ) );

		// 3.0.3 trim extra space and comma
		$output['mimetype']['black_list'] = preg_replace( $key, $val, trim( $output['mimetype']['black_list'] ) );
		$output['mimetype']['black_list'] = implode( ',', $this->trim( $output['mimetype']['black_list'] ) );

		// 3.0.0 convert country code to upper case, remove redundant spaces
		$output['public']['ua_list'] = preg_replace( $key, $val, trim( $output['public']['ua_list'] ) );
		$output['public']['ua_list'] = preg_replace( '/([:#]) *([!]+) *([^ ]+) *([,\n]+)/', '$1$2$3$4', $output['public']['ua_list'] );
		$output['public']['ua_list'] = preg_replace_callback( '/[:#]([\w:]+)/', array(
			$this,
			'strtoupper'
		), $output['public']['ua_list'] );

		// 3.0.0 public : convert country code to upper case
		foreach ( array( 'white_list', 'black_list' ) as $key ) {
            if( strpos($output['public'][ $key ], ':') === false ) {
	            $output['public'][ $key ] = strtoupper( preg_replace( '/\s/', '', $output['public'][ $key ] ) );
            }
			// 3.0.4 extra_ips : convert AS number to upper case
			$output['extra_ips'][ $key ] = strtoupper( $output['extra_ips'][ $key ] );
		}

		// 2.2.5 exception : convert associative array to simple array
		foreach ( array( 'plugins', 'themes' ) as $key ) {
			$output['exception'][ $key ] = array_keys( $output['exception'][ $key ] );
		}

		// 3.0.0 - 3.0.3 exception : trim extra space and comma
		foreach ( array( 'admin', 'public', 'includes', 'uploads', 'languages', 'restapi' ) as $key ) {
			if ( empty( $output['exception'][ $key ] ) ) {
				$output['exception'][ $key ] = $default['exception'][ $key ];
			} else {
				$output['exception'][ $key ] = ( is_array( $output['exception'][ $key ] ) ?
					$output['exception'][ $key ] : $this->trim( $output['exception'][ $key ] ) );
			}
		}

		if ( isset( $output['use_asn'] ) && (int) $output['use_asn'] > 0 ) {
			// If provider supports asn database, then trigger downloading databases.
			$supporting = 0;
			$providers  = IP_Location_Block_Provider::get_providers();
			foreach ( $providers as $key => $provider ) {
				if ( IP_Location_Block_Provider::supports( $key, 'asn_database' ) || IP_Location_Block_Provider::supports( $key, 'asn' ) ) {
					$supporting ++;
				}
			}
			if ( $supporting ) {
				require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-cron.php';
				add_action( 'ip-location-block-settings-updated', array(
					'IP_Location_Block_Cron',
					'start_update_db'
				), 10, 2 );
			}
		} else {
			// If asn databases doesn't exist but path is specified, clear the path.
			require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-file.php';
			$filesystem = IP_Location_Block_FS::init( __FUNCTION__ );
			$providers  = IP_Location_Block_Provider::get_providers();
			foreach ( $providers as $key => $provider ) {
				if ( ! isset( $output[ $key ] ) ) {
					continue;
				}
				if ( empty( $output[ $key ]['asn_path'] ) ) {
					$output[ $key ]['asn_path'] = null;
				} else {
					if ( ! $filesystem->exists( $output[ $key ]['asn_path'] ) ) {
						$output[ $key ]['asn_path'] = null;
					}
				}
			}
		}

		// cron event
		$key = wp_next_scheduled( IP_Location_Block::CRON_NAME, array( false ) );
		if ( $output['update']['auto'] && ! $key ) {
			require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-cron.php';
			IP_Location_Block_Cron::start_update_db( $output, false );
		} elseif ( ! $output['update']['auto'] && $key ) {
			require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-cron.php';
			IP_Location_Block_Cron::stop_update_db();
		}

		// expiration time [days]
		$output['validation']['explogs'] = min( 365, max( 1, (int) $output['validation']['explogs'] ) );

		return $output;
	}

	/**
	 * A fallback function of array_replace_recursive() before PHP 5.3.
	 *
	 * @link https://php.net/manual/en/function.array-replace-recursive.php#92574
	 * @link https://php.net/manual/en/function.array-replace-recursive.php#109390
	 */
	public function array_replace_recursive() {
		if ( function_exists( 'array_replace_recursive' ) ) {
			return call_user_func_array( 'array_replace_recursive', func_get_args() );
		} else {
			foreach ( array_slice( func_get_args(), 1 ) as $replacements ) {
				$bref_stack = array( &$base );
				$head_stack = array( $replacements );

				do {
					end( $bref_stack );

					$bref = &$bref_stack[ key( $bref_stack ) ];
					$head = array_pop( $head_stack );

					unset( $bref_stack[ key( $bref_stack ) ] );

					foreach ( array_keys( $head ) as $key ) {
						if ( isset( $key, $bref, $bref[ $key ], $head[ $key ] ) && is_array( $bref[ $key ] ) && is_array( $head[ $key ] ) ) {
							$bref_stack[] = &$bref[ $key ];
							$head_stack[] = $head [ $key ];
						} else {
							$bref[ $key ] = $head [ $key ];
						}
					}
				} while ( count( $head_stack ) );
			}

			return $base;
		}
	}

	// Callback for preg_replace_callback()
	public function strtoupper( $matches ) {
		return filter_var( $matches[1], FILTER_VALIDATE_IP ) ? $matches[0] : strtoupper( $matches[0] );
	}

	// Trim extra space and comma avoiding invalid signature which potentially blocks itself
	private function trim( $text ) {
		$path = IP_Location_Block::get_wp_path();

		$ret = array();
		foreach ( explode( ',', $text ) as $val ) {
			$val = trim( $val );
			if ( $val && false === stripos( $path['admin'], $val ) ) {
				$ret[] = $val;
			}
		}

		return $ret;
	}

	/**
	 * Check admin post
	 *
	 */
	private function check_admin_post( $ajax = false ) {
		if ( $ajax ) {
			$nonce = IP_Location_Block_Util::verify_nonce( IP_Location_Block_Util::retrieve_nonce( 'nonce' ), $this->get_ajax_action() );
		} else {
			$nonce = check_admin_referer( 'ip-location-block-options' );
		} // a postfix '-options' is added at settings_fields().

		$settings = IP_Location_Block::get_option();
		if ( ( $ajax and $settings['validation']['ajax'] & 2 ) ||
		     ( ! $ajax and $settings['validation']['admin'] & 2 ) ) {
			$action = IP_Location_Block::get_auth_key();
			$nonce  &= IP_Location_Block_Util::verify_nonce( IP_Location_Block_Util::retrieve_nonce( $action ), $action );
		}

		if ( ! $nonce || ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_network_options' ) ) ) {
			status_header( 403 );
			wp_die(
				__( 'You do not have sufficient permissions to access this page.' ), '',
				array( 'response' => 403, 'back_link' => true )
			);
		}
	}

	/**
	 * Validate settings and configure some features.
	 *
	 * @note: This function is triggered when update_option() is executed.
	 */
	public function validate_settings( $input = array() ) {
		// must check that the user has the required capability
		$this->check_admin_post( false );

		// validate setting options
		$options = $this->sanitize_options( $input );

		// additional configuration
		require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-opts.php';
		$file = IP_Location_Block_Opts::setup_validation_timing( $options );
		if ( is_wp_error( $file ) ) {
			$options['validation']['timing'] = 0;
			self::add_admin_notice( 'error', $file->get_error_message() );
		}

		// Force to finish update matching rule
		delete_transient( IP_Location_Block::CRON_NAME );

		// start to update databases immediately
		do_action( 'ip-location-block-settings-updated', $options, true );

		return $options;
	}

	/**
	 * Validate settings and configure some features for network multisite.
	 *
	 * @see https://vedovini.net/2015/10/using-the-wordpress-settings-api-with-network-admin-pages/
	 */
	public function validate_network_settings() {
		// Must check that the user has the required capability
		$this->check_admin_post( false );

		// The list of registered options (IP_Location_Block::OPTION_NAME).
		global $new_whitelist_options;
		$options = $new_whitelist_options[ IP_Location_Block::PLUGIN_NAME ];

		// Go through the posted data and save the targetted options.
		foreach ( $options as $option ) {
			if ( isset( $_POST[ $option ] ) ) {
				$this->update_multisite_settings( $_POST[ $option ] );
			}
		}

		// Register a settings error to be displayed to the user
		self::add_admin_notice( 'updated', __( 'Settings saved.' ) );

		// Redirect in order to back to the settings page.
		wp_redirect( esc_url_raw(
			add_query_arg(
				array( 'page' => IP_Location_Block::PLUGIN_NAME ),
				$this->dashboard_url( ! empty( $_POST[ $option ]['network_wide'] ) )
			)
		) );

		exit;
	}

	/**
	 * Update option in all blogs.
	 *
	 * @note: This function triggers `validate_settings()` on register_setting() in wp-include/option.php.
	 */
	public function update_multisite_settings( $settings ) {
		global $wpdb;
		$blog_ids = $wpdb->get_col( "SELECT `blog_id` FROM `$wpdb->blogs`" );
		$ret      = true;

		foreach ( $blog_ids as $id ) {
			switch_to_blog( $id );
			$map = IP_Location_Block::get_option( false );
			$ret &= IP_Location_Block::update_option( $settings, false );
			restore_current_blog();
		}

		return $ret;
	}

	/**
	 * Analyze entries in "Validation logs"
	 *
	 * @param array $logs An array including each entry where:
	 * Array (
	 *     [0 DB row number] => 154
	 *     [1 Target       ] => comment
	 *     [2 Time         ] => 1534580897
	 *     [3 IP address   ] => 102.177.147.***
	 *     [4 Country code ] => ZA
	 *     [5 Result       ] => blocked
	 *     [6 AS number    ] => AS328239
	 *     [7 Request      ] => POST[80]:/wp-comments-post.php
	 *     [8 User agent   ] => Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) ...
	 *     [9 HTTP headers ] => HTTP_ORIGIN=http://localhost,HTTP_X_FORWARDED_FOR=102.177.147.***
	 *    [10 $_POST data  ] => comment=Hello.,author,email,url,comment_post_ID,comment_parent
	 * )
	 * And put a mark at "Target"
	 *    ¹¹: Passed  in Whitelist
	 *    ¹²: Passed  in Blacklist
	 *    ¹³: Passed  not in list
	 *    ²¹: Blocked in Whitelist
	 *    ²²: Blocked in Blacklist
	 *    ²³: Blocked not in list
	 *
	 * @return array
	 */
	public function filter_logs( $logs ) {
		$settings = IP_Location_Block::get_option();

		// White/Black list for back-end
		$white_backend = $settings['white_list'];
		$black_backend = $settings['black_list'];

		// White/Black list for front-end
		if ( $settings['public']['matching_rule'] < 0 ) {
			// Follow "Validation rule settings"
			$white_frontend = $white_backend;
			$black_frontend = $black_backend;
		} else {
			// Whitelist or Blacklist for "Public facing pages"
			$white_frontend = $settings['public']['white_list'];
			$black_frontend = $settings['public']['black_list'];
		}

		foreach ( $logs as $key => $log ) {
			// Passed or Blocked
			$mark = IP_Location_Block::is_passed( $log[5] ) ? '&sup1;' : '&sup2;';

			// Whitelisted, Blacklisted or N/A
			if ( 'public' === $log[1] ) {
				$mark .= IP_Location_Block::is_listed( $log[4], $white_frontend ) ? '&sup1;' : (
				IP_Location_Block::is_listed( $log[4], $black_frontend ) ? '&sup2;' : '&sup3;' );
			} else {
				$mark .= IP_Location_Block::is_listed( $log[4], $white_backend ) ? '&sup1;' : (
				IP_Location_Block::is_listed( $log[4], $black_backend ) ? '&sup2;' : '&sup3;' );
			}

			// Put a mark at "Target"
			$logs[ $key ][1] .= $mark;
		}

		return $logs;
	}

	/**
	 * Register UI "Preset filters" at "Search in logs"
	 *
	 * @param array $filters An empty array by default.
	 *
	 * @return array $filters The array of paired with 'title' and 'value'.
	 */
	public function preset_filters( $filters = array() ) {
		return array(
			array(
				'title' => '<span class="ip-location-block-icon ip-location-block-icon-happy"    >&nbsp;</span>' . __( '<span title="Show only passed entries whose country codes are in Whitelist.">Passed in Whitelist</span>', 'ip-location-block' ),
				'value' => '&sup1;&sup1;'
			),
			array(
				'title' => '<span class="ip-location-block-icon ip-location-block-icon-grin2"    >&nbsp;</span>' . __( '<span title="Show only passed entries whose country codes are in Blacklist.">Passed in Blacklist</span>', 'ip-location-block' ),
				'value' => '&sup1;&sup2;'
			),
			array(
				'title' => '<span class="ip-location-block-icon ip-location-block-icon-cool"     >&nbsp;</span>' . __( '<span title="Show only passed entries whose country codes are not in either list.">Passed not in List</span>', 'ip-location-block' ),
				'value' => '&sup1;&sup3;'
			),
			array(
				'title' => '<span class="ip-location-block-icon ip-location-block-icon-confused" >&nbsp;</span>' . __( '<span title="Show only blocked entries whose country codes are in Whitelist.">Blocked in Whitelist</span>', 'ip-location-block' ),
				'value' => '&sup2;&sup1;'
			),
			array(
				'title' => '<span class="ip-location-block-icon ip-location-block-icon-confused2">&nbsp;</span>' . __( '<span title="Show only blocked entries whose country codes are in Blacklist.">Blocked in Blacklist</span>', 'ip-location-block' ),
				'value' => '&sup2;&sup2;'
			),
			array(
				'title' => '<span class="ip-location-block-icon ip-location-block-icon-crying"   >&nbsp;</span>' . __( '<span title="Show only blocked entries whose country codes are not in either list.">Blocked not in List</span>', 'ip-location-block' ),
				'value' => '&sup2;&sup3;'
			),
		);
	}

	/**
	 * Ajax callback function
	 *
	 * @link https://codex.wordpress.org/AJAX_in_Plugins
	 * @link https://codex.wordpress.org/Function_Reference/check_ajax_referer
	 * @link https://core.trac.wordpress.org/browser/trunk/wp-admin/admin-ajax.php
	 */
	public function admin_ajax_callback() {
		require_once IP_LOCATION_BLOCK_PATH . 'admin/includes/class-admin-ajax.php';

		// Check request origin, nonce, capability.
		$this->check_admin_post( true );

		$services = array();
		if ( ! empty( $_POST['which'] ) && is_array( $_POST['which'] ) ) {
			foreach ( $_POST['which'] as $key => $value ) {
				$services[ $key ] = sanitize_text_field( $value );
			}
		}

		// `$which` and `$cmd` should be restricted by whitelist in each function
		$settings = IP_Location_Block::get_option();
		$which    = isset( $_POST['which'] ) ? $services : array();
		$cmd      = isset( $_POST['cmd'] ) ? sanitize_text_field( $_POST['cmd'] ) : null;

		switch ( $cmd ) {
			case 'download':
				$res = IP_Location_Block::get_instance();
				$res = $res->exec_update_db();
				break;

			case 'search': // Get geolocation by IP
				$res = array();
				foreach ( (array) $which as $cmd ) {
					$res[ $cmd ] = IP_Location_Block_Admin_Ajax::search_ip( $cmd );
				}
				break;

			case 'scan-code': // Fetch providers to get country code
				$res = IP_Location_Block_Admin_Ajax::scan_country( $which );
				break;

			case 'clear-statistics': // Set default values
				IP_Location_Block_Logs::clear_stat();
				$res = array(
					'page' => 'options-general.php?page=' . IP_Location_Block::PLUGIN_NAME,
					'tab'  => 'tab=1'
				);
				break;

			case 'clear-cache': // Delete cache of IP address
				IP_Location_Block_API_Cache::clear_cache();
				$res = array(
					'page' => 'options-general.php?page=' . IP_Location_Block::PLUGIN_NAME,
					'tab'  => 'tab=1'
				);
				break;

			case 'clear-logs': // Delete logs in MySQL DB
				IP_Location_Block_Logs::clear_logs( $which );
				$res = array(
					'page' => 'options-general.php?page=' . IP_Location_Block::PLUGIN_NAME,
					'tab'  => 'tab=4'
				);
				break;

			case 'export-logs':// Export logs from MySQL DB
				IP_Location_Block_Admin_Ajax::export_logs( $which );
				break;

			case 'restore-logs': // Get logs from MySQL DB
				has_filter( $cmd = 'ip-location-block-logs' ) or add_filter( $cmd, array(
					$this,
					'filter_logs'
				) );
				$res = IP_Location_Block_Admin_Ajax::restore_logs( $which );
				break;

			case 'live-start': // Restore live log
				has_filter( $cmd = 'ip-location-block-logs' ) or add_filter( $cmd, array(
					$this,
					'filter_logs'
				) );
				if ( is_wp_error( $res = IP_Location_Block_Admin_Ajax::restore_live_log( $which, $settings ) ) ) {
					$res = array( 'error' => $res->get_error_message() );
				}
				break;

			case 'live-pause': // Pause live log
				if ( ! is_wp_error( $res = IP_Location_Block_Admin_Ajax::catch_live_log() ) ) {
					$res = array( 'data' => array() );
				} else {
					$res = array( 'error' => $res->get_error_message() );
				}
				break;

			case 'live-stop': // Stop live log
				if ( ! is_wp_error( $res = IP_Location_Block_Admin_Ajax::release_live_log() ) ) {
					$res = array( 'data' => array() );
				} else {
					$res = array( 'error' => $res->get_error_message() );
				}
				break;

			case 'reset-live': // Reset data source of live log
				$res = IP_Location_Block_Admin_Ajax::reset_live_log();
				break;

			case 'validate': // Validate settings
				IP_Location_Block_Admin_Ajax::validate_settings( $this );
				break;

			case 'import-default': // Import initial settings
				$res = IP_Location_Block_Admin_Ajax::settings_to_json( IP_Location_Block::get_default() );
				break;

			case 'import-preferred': // Import preference
				$res = IP_Location_Block_Admin_Ajax::preferred_to_json();
				break;

			case 'generate-link': // Generate new link
				$res = array( 'link' => IP_Location_Block_Util::generate_link( $this ) );
				break;

			case 'delete-link': // Delete existing link
				IP_Location_Block_Util::delete_link( $this );
				$res = __( 'Done.' );
				break;

			case 'show-info': // Show system and debug information
				$res = IP_Location_Block_Admin_Ajax::get_wp_info();
				break;

			case 'get-actions': // Get all the ajax/post actions
				$res = IP_Location_Block_Util::get_registered_actions( true, $settings );
				break;

			case 'export-cache': // Restore cache from database and format for DataTables
				IP_Location_Block_Admin_Ajax::export_cache( $settings['anonymize'] );
				break;

			case 'restore-cache': // Restore cache from database and format for DataTables
				$res = IP_Location_Block_Admin_Ajax::restore_cache( $settings['anonymize'] );
				break;

			case 'bulk-action-remove': // Delete specified IP addresses from cache
				$res = IP_Location_Block_Logs::delete_cache_entry( $which['IP'] );
				break;

			case 'bulk-action-ip-erase':
				$res = IP_Location_Block_Logs::delete_logs_entry( $which['IP'] );
				break;

			case 'bulk-action-ip-white':
			case 'bulk-action-ip-black':
			case 'bulk-action-as-white':
			case 'bulk-action-as-black':
				// Bulk actions for registration of settings
				$src = ( false !== strpos( $cmd, '-ip-' ) ? 'IP' : 'AS' );
				$dst = ( false !== strpos( $cmd, '-white' ) ? 'white_list' : 'black_list' );

				if ( empty( $which[ $src ] ) ) {
					$res = array( 'error' => sprintf( __( 'An error occurred while executing the ajax command `%s`.', 'ip-location-block' ), $cmd ) );
					break;
				}

				foreach ( array_unique( (array) $which[ $src ] ) as $val ) {
					// replace anonymized IP address with CIDR (IPv4:256, IPv6:4096)
					$val = preg_replace(
						array( '/\.\*\*\*$/', '/:\w*\*\*\*$/', '/(::.*)::\/116$/' ),
						array( '.0/24', '::/116', '$1/116' ),
						trim( $val )
					);
					if ( ( filter_var( preg_replace( '/\/\d+$/', '', $val ), FILTER_VALIDATE_IP ) || preg_match( '/^AS\d+$/', $val ) ) &&
					     ( false === strpos( $settings['extra_ips'][ $dst ], $val ) ) ) {
						$settings['extra_ips'][ $dst ] .= "\n" . $val;
					}
				}

				if ( $this->is_network_admin && $settings['network_wide'] ) {
					$this->update_multisite_settings( $settings );
				} else {
					IP_Location_Block::update_option( $settings );
				}

				$res = array( 'page' => 'options-general.php?page=' . IP_Location_Block::PLUGIN_NAME );
				break;

			case 'restore-network': // Restore blocked per target in logs
				$res = IP_Location_Block_Admin_Ajax::restore_network( $which, (int) $_POST['offset'], (int) $_POST['length'], false );
				break;

			case 'find-admin':
			case 'find-plugins':
			case 'find-themes':
				// Get slug in blocked requests for exceptions
				$res = IP_Location_Block_Admin_Ajax::find_exceptions( $cmd );
				break;

			case 'diag-tables': // Check database tables
				IP_Location_Block_Logs::diag_tables() or IP_Location_Block_Logs::create_tables();
				$res = array( 'page' => 'options-general.php?page=' . IP_Location_Block::PLUGIN_NAME );
				break;
			case 'migrate-from-legacy':

				require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-opts.php';
				$settings = IP_Location_Block_Opts::get_legacy_settings();
				if ( empty( $settings ) ) {
					$res = array(
						'success' => false,
						'message' => __( 'No previous settings found.', 'ip-location-block' ),
					);
				} else {
					$settings['version']              = IP_LOCATION_BLOCK_VERSION;
					$settings['migrated_from_legacy'] = true;
					IP_Location_Block::update_option( $settings );
					$res = array(
						'success' => true,
						'message' => __( 'Migration successful. This page will be reloaded now...', 'ip-location-block' ),
					);
				}
				break;
			case 'dismiss-notice':
				$notice_id = isset( $_POST['notice_id'] ) ? sanitize_text_field( $_POST['notice_id'] ) : '';
				require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-opts.php';
				$settings = IP_Location_Block::get_option();
                $dismissed = false;

                if ( 'welcome' === $notice_id ) {
					$settings['welcome'] = true;
                    $dismissed = true;
	                IP_Location_Block::update_option( $settings );
                }
				$res = array(
					'success' => true,
					'message' => $dismissed ? __( 'Notice dismissed.', 'ip-location-block' ) : __( 'Could not find notice to dismiss.', 'ip-location-block' ),
				);
				break;
		}

		if ( isset( $res ) ) // wp_send_json_{success,error}() @since 3.5.0
		{
			wp_send_json( $res );
		} // @since 3.5.0

		die(); // End of ajax
	}

}
