<?php
/**
 * IP Geo Block
 *
 * @package   IP_Geo_Block
 * @author    tokkonopapa <tokkonopapa@yahoo.com>
 * @license   GPL-2.0+
 * @link      https://github.com/tokkonopapa
 * @copyright 2013-2015 tokkonopapa
 */

/**
 * Default path to the database file
 */
define( 'IP_GEO_BLOCK_DB_DIR', IP_GEO_BLOCK_PATH . 'database/' );

class IP_Geo_Block {

	/**
	 * Unique identifier for this plugin.
	 *
	 */
	const VERSION = '2.1.1';
	const TEXT_DOMAIN = 'ip-geo-block';
	const PLUGIN_SLUG = 'ip-geo-block';
	const CACHE_KEY   = 'ip_geo_block_cache';
	const CRON_NAME   = 'ip_geo_block_cron';

	/**
	 * Instance of this class.
	 *
	 */
	protected static $instance = null;

	// option table accessor by name
	public static $option_keys = array(
		'settings'   => 'ip_geo_block_settings',
		'statistics' => 'ip_geo_block_statistics',
	);

	// default content folders
	public static $content_dir = array(
		'plugins' => '/wp-content/plugins/',
		'themes'  => '/wp-content/themes/',
	);

	// private values
	private $remote_addr = NULL;

	/**
	 * Initialize the plugin
	 * 
	 */
	private function __construct() {
		$settings = self::get_option( 'settings' );
		$validate = $settings['validation'];

		// check the package version and upgrade if needed
		if ( version_compare( $settings['version'], self::VERSION ) < 0 )
			$settings = self::activate();

		// the action hook which will be fired by cron job
		if ( $settings['update']['auto'] && ! has_action( self::CRON_NAME ) )
			add_action( self::CRON_NAME, array( __CLASS__, 'download_database' ) );

		if ( $validate['comment'] ) {
			// message text on comment form
			if ( $settings['comment']['pos'] ) {
				$pos = 'comment_form' . ( $settings['comment']['pos'] == 1 ? '_top' : '' );
				add_action( $pos, array( $this, 'comment_form_message' ) );
			}

			// wp-comments-post.php @since 2.8.0, wp-trackback.php @since 1.5.0
			add_action( 'pre_comment_on_post', array( $this, 'validate_comment' ) );
			add_filter( 'preprocess_comment', array( $this, 'validate_comment' ) );

			// bbPress: prevent creating topic/relpy and rendering form
			add_action( 'bbp_post_request_bbp-new-topic', array( $this, 'validate_comment' ) );
			add_action( 'bbp_post_request_bbp-new-reply', array( $this, 'validate_comment' ) );
			add_filter( 'bbp_current_user_can_access_create_topic_form', array( $this, 'validate_access' ) );
			add_filter( 'bbp_current_user_can_access_create_reply_form', array( $this, 'validate_access' ) );
		}

		// xmlrpc.php @since 3.1.0, wp-includes/class-wp-xmlrpc-server.php @since 3.5.0
		if ( $validate['xmlrpc'] ) {
			add_filter( 'wp_xmlrpc_server_class', array( $this, 'validate_xmlrpc' ) );
			add_filter( 'xmlrpc_login_error', array( $this, 'auth_fail' ) );
		}

		// wp-login.php @since 2.1.0, wp-includes/pluggable.php @since 2.5.0
		if ( $validate['login'] ) {
			add_action( 'login_init', array( $this, 'validate_login' ) );
			add_action( 'wp_login_failed', array( $this, 'auth_fail' ) );

			// BuddyPress: prevent registration and rendering form
			add_action( 'bp_core_screen_signup', array( $this, 'validate_login' ) );
			add_action( 'bp_signup_pre_validate', array( $this, 'validate_login' ) );
		}

		// get content folders (with trailing slash)
		if ( preg_match( '/(\/[^\/]*\/[^\/]*)$/', parse_url( plugins_url(), PHP_URL_PATH ), $uri ) )
			self::$content_dir['plugins'] = "$uri[1]/";

		if ( preg_match( '/(\/[^\/]*\/[^\/]*)$/', parse_url( get_theme_root_uri(), PHP_URL_PATH ), $uri ) )
			self::$content_dir['themes'] = "$uri[1]/";

		// wp-content/(plugins|themes)/.../*.php
		$uri = preg_replace( '|//+|', '/', $_SERVER['REQUEST_URI'] );
		if ( ( $validate['plugins'] && FALSE !== strpos( $uri, self::$content_dir['plugins'] ) ) ||
		     ( $validate['themes' ] && FALSE !== strpos( $uri, self::$content_dir['themes' ] ) ) )
			add_action( 'init', array( $this, 'validate_direct' ), $settings['priority'] );

		// wp-admin/(admin.php|admin-apax.php|admin-post.php) @since 2.5.0
		elseif ( ( $validate['admin'] || $validate['ajax'] ) && is_admin() )
			add_action( 'init', array( $this, 'validate_admin' ), $settings['priority'] );

		// overwrite the redirect URL at logout, embed a nonce into the page
		add_filter( 'wp_redirect', array( $this, 'logout_redirect' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_nonce' ), $settings['priority'] );
	}

	// Register and enqueue a nonce with a specific JavaScript.
	public static function enqueue_nonce() {
		if ( is_user_logged_in() ) {
			$handle = self::PLUGIN_SLUG . '-auth-nonce';
			$script = plugins_url( 'admin/js/auth-nonce.js', IP_GEO_BLOCK_BASE );
			$nonce = array( 'nonce' => wp_create_nonce( $handle ) ) + self::$content_dir;
			wp_enqueue_script( $handle, $script, array( 'jquery' ), self::VERSION );
			wp_localize_script( $handle, 'IP_GEO_BLOCK_AUTH', $nonce );
		}
	}

	// get default optional values
	public static function get_default( $name = 'settings' ) {
		require_once( IP_GEO_BLOCK_PATH . 'classes/class-ip-geo-block-opts.php' );
		return IP_Geo_Block_Options::get_table( self::$option_keys[ $name ] );
	}

	// get optional values from wp options
	public static function get_option( $name = 'settings' ) {
		if ( FALSE === ( $option = get_option( self::$option_keys[ $name ] ) ) )
			$option = self::get_default( $name );

		return $option;
	}

	// http://codex.wordpress.org/Function_Reference/wp_remote_get
	public static function get_request_headers( $settings ) {
		global $wp_version;
		return apply_filters(
			self::PLUGIN_SLUG . '-headers',
			array(
				'timeout' => (int)$settings['timeout'],
				'user-agent' => "WordPress/$wp_version; " . self::PLUGIN_SLUG . ' ' . self::VERSION,
			)
		);
	}

	/**
	 * Return an instance of this class.
	 *
	 */
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance )
			self::$instance = new self;

		return self::$instance;
	}

	/**
	 * Register options into database table when the plugin is activated.
	 *
	 */
	public static function activate( $network_wide = NULL ) {
		// upgrade options
		require_once( IP_GEO_BLOCK_PATH . 'classes/class-ip-geo-block-opts.php' );
		$settings = IP_Geo_Block_Options::upgrade();

		// create log
		require_once( IP_GEO_BLOCK_PATH . 'classes/class-ip-geo-block-logs.php' );
		IP_Geo_Block_Logs::create_log();

		// execute to download immediately
		if ( $settings['update']['auto'] ) {
			add_action( self::CRON_NAME, array( __CLASS__, 'download_database' ) );
			self::schedule_cron_job( $settings['update'], $settings['maxmind'], TRUE );
		}

		return $settings;
	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 */
	public static function deactivate( $network_wide = NULL ) {
		// cancel schedule
		wp_clear_scheduled_hook( self::CRON_NAME ); // @since 2.1.0
	}

	/**
	 * Delete options from database when the plugin is uninstalled.
	 *
	 */
	public static function uninstall() {
		$settings = self::get_option( 'settings' );

		if ( $settings['clean_uninstall'] ) {
			// delete settings options
			delete_option( self::$option_keys['settings'  ] ); // @since 1.2.0
			delete_option( self::$option_keys['statistics'] ); // @since 1.2.0

			// delete IP address cache
			delete_transient( self::CACHE_KEY ); // @since 2.8

			// delete log
			require_once( IP_GEO_BLOCK_PATH . 'classes/class-ip-geo-block-logs.php' );
			IP_Geo_Block_Logs::delete_log();
		}
	}

	/**
	 * Render a text message to the comment form.
	 *
	 */
	public function comment_form_message() {
		$msg = self::get_option( 'settings' );
		$msg = esc_html( $msg['comment']['msg'] ); // Escaping for HTML blocks
		if ( $msg ) echo '<p id="', self::PLUGIN_SLUG, '-msg">', $msg, '</p>';
//		global $allowedtags;
//		if ( $msg = wp_kses( $msg['comment']['msg'], $allowedtags ) ) echo $msg;
	}

	/**
	 * Get geolocation and country code from an ip address
	 *
	 */
	public static function get_geolocation( $ip = NULL, $providers = array(), $callback = 'get_country' ) {
		require_once( IP_GEO_BLOCK_PATH . 'classes/class-ip-geo-block-apis.php' );

		return self::_get_geolocation(
			$ip ? $ip : apply_filters( self::PLUGIN_SLUG . '-ip-addr', $_SERVER['REMOTE_ADDR'] ),
			self::get_option( 'settings' ), $providers, $callback
		);
	}

	private static function _get_geolocation( $ip, $settings, $providers = array(), $callback = 'get_country' ) {
		// make valid providers list
		if ( empty( $providers ) )
			$providers = IP_Geo_Block_Provider::get_valid_providers( $settings['providers'] );

		// set arguments for wp_remote_get()
		$args = self::get_request_headers( $settings );

		foreach ( $providers as $provider ) {
			$time = microtime( TRUE );
			$name = IP_Geo_Block_API::get_class_name( $provider );

			if ( $name ) {
				$key = ! empty( $settings['providers'][ $provider ] );
				$geo = new $name( $key ? $settings['providers'][ $provider ] : NULL );

				// get country code
				if ( $code = $geo->$callback( $ip, $args ) ) {
					$ret = array(
						'ip' => $ip,
						'auth' => get_current_user_id(),
						'time' => microtime( TRUE ) - $time,
						'provider' => $provider,
					);

					return is_array( $code ) ?
						$ret + $code :
						$ret + array( 'code' => $code );
				}
			}
		}

		return array(
			'ip' => $ip,
			'auth' => get_current_user_id(),
			'time' => 0,
			'provider'     => 'ZZ',
			'code'         => 'ZZ', // for get_country()
			'countryCode'  => 'ZZ', // for get_location()
			'errorMessage' => 'unknown',
		);
	}

	/**
	 * Validate user's geolocation.
	 *
	 */
	private function validate_country( $validate, $settings ) {
		if ( 0 == $settings['matching_rule'] ) {
			$list = $settings['white_list'];
			if ( ! $list || FALSE !== strpos( $list, $validate['code'] ) )
				return $validate + array( 'result' => 'passed' );
			elseif ( 'ZZ' !== $validate['code'] )
				return $validate + array( 'result' => 'blocked' );
		}

		else {
			$list = $settings['black_list'];
			if ( $list && FALSE !== strpos( $list, $validate['code'] ) )
				return $validate + array( 'result' => 'blocked' );
			elseif ( 'ZZ' !== $validate['code'] )
				return $validate + array( 'result' => 'passed' );
		}

		return $validate + array( 'result' => 'unknown' );
	}

	/**
	 * Update statistics
	 *
	 */
	public function update_statistics( $validate ) {
		$statistics = self::get_option( 'statistics' );

		if ( filter_var( $validate['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) )
			$statistics['IPv4']++;
		elseif ( filter_var( $validate['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) )
			$statistics['IPv6']++;

		@$statistics[ $validate['result'] ]++;
		@$statistics['countries'][ $validate['code'] ]++;

		$provider = $validate['provider'] ? $validate['provider'] : 'ZZ';
		if ( empty( $statistics['providers'][ $provider ] ) )
			$statistics['providers'][ $provider ] = array( 'count' => 0, 'time' => 0.0 );

		$statistics['providers'][ $provider ]['count']++;
		$statistics['providers'][ $provider ]['time'] += (float)$validate['time'];

		update_option( self::$option_keys['statistics'], $statistics );
	}

	/**
	 * Send response header with http status code and reason.
	 *
	 */
	public function send_response( $hook, $code ) {
		$code = (int   )apply_filters( self::PLUGIN_SLUG . "-{$hook}-status", (int)$code );
		$mesg = (string)apply_filters( self::PLUGIN_SLUG . "-{$hook}-reason", get_status_header_desc( $code ) );

		nocache_headers(); // nocache and response code
		switch ( (int)substr( "$code", 0, 1 ) ) {
		  case 2: // 2xx Success
			header( 'Refresh: 0; url=' . home_url(), TRUE, $code ); // @since 3.0
			die();

		  case 3: // 3xx Redirection
			wp_redirect( 'http://blackhole.webpagetest.org/', $code );
			die();

		  default: // 4xx Client Error, 5xx Server Error
			status_header( $code ); // @since 2.0.0
			if ( count( preg_grep( "/content-type:\s*?text\/xml;/i", headers_list() ) ) )
				@trackback_response( $code, $mesg );
			elseif ( ! defined( 'DOING_AJAX' ) && ! defined( 'XMLRPC_REQUEST' ) )
				if ( FALSE === @include( STYLESHEETPATH . "/{$code}.php" ) )
					if ( FALSE === @include( TEMPLATEPATH . "/{$code}.php" ) )
						wp_die( $mesg, '', array( 'response' => $code, 'back_link' => TRUE ) );
			die();
		}
	}

	/**
	 * Validate ip address
	 *
	 * @param string $hook a name to identify action hook applied in this call.
	 * @param array $settings option settings
	 */
	private function validate_ip( $hook, $settings, $block = TRUE ) {
		// set IP address to be validated
		$ips = array(
			$this->remote_addr = (string) apply_filters(
				self::PLUGIN_SLUG . '-ip-addr', $_SERVER['REMOTE_ADDR']
			)
		);

		// pick up all the IPs in HTTP_X_FORWARDED_FOR, HTTP_CLIENT_IP and etc.
		foreach ( explode( ',', $settings['validation']['proxy'] ) as $var ) {
			if ( isset( $_SERVER[ $var ] ) ) {
				foreach ( explode( ',', $_SERVER[ $var ] ) as $ip ) {
					if ( ! in_array( $ip = trim( $ip ), $ips ) &&
					     filter_var( $ip, FILTER_VALIDATE_IP ) ) {
						array_unshift( $ips, $ip );
					}
				}
			}
		}

		// check if the authentication has been already failed
		$var = self::PLUGIN_SLUG . "-${hook}";
		add_filter( $var, array( $this, 'check_fail' ), 10, 2 );

		// check the authentication when anyone can login
		if ( 2 == $settings['validation']['login'] )
			add_filter( $var, array( $this, 'check_auth' ), 10, 2 );

		// make valid provider name list
		require_once( IP_GEO_BLOCK_PATH . 'classes/class-ip-geo-block-apis.php' );
		$providers = IP_Geo_Block_Provider::get_valid_providers( $settings['providers'] );

		// apply custom filter of validation
		// @usage add_filter( "ip-geo-block-$hook", 'my_validation' );
		// @param $validate = array(
		//     'ip'       => $ip,       /* validated ip address                */
		//     'auth'     => $auth,     /* authenticated or not                */
		//     'code'     => $code,     /* country code or reason of rejection */
		//     'result'   => $result,   /* 'passed', 'blocked' or 'unknown'    */
		// );
		foreach ( $ips as $ip ) {
			$validate = self::_get_geolocation( $ip, $settings, $providers );
			$validate = apply_filters( $var, $validate, $settings );

			// if no 'result' then validate ip address by country
			if ( empty( $validate['result'] ) )
				$validate = $this->validate_country( $validate, $settings );

			// if one of IPs is blocked then stop
			if ( $blocked = ( 'passed' !== $validate['result'] ) ) {
				$this->remote_addr = $ip;
				break;
			}
		}

		// update cache
		IP_Geo_Block_API_Cache::update_cache( $hook, $validate, $settings );

		// record log (0:no, 1:blocked, 2:passed, 3:unauth, 4:auth, 5:all)
		$var = (int)$settings['validation']['reclogs'];
		if ( ( 1 === $var &&   $blocked ) || // blocked, unknown
		     ( 2 === $var && ! $blocked ) || // passed
		     ( 3 === $var && ! $validate['auth'] ) || // unauthenticated
		     ( 4 === $var &&   $validate['auth'] ) || // authenticated
		     ( 5 === $var ) ) { // all
			require_once( IP_GEO_BLOCK_PATH . 'classes/class-ip-geo-block-logs.php' );
			IP_Geo_Block_Logs::record_log( $hook, $validate, $settings );
		}

		if ( $block && $blocked ) {
			// update statistics
			if ( $settings['save_statistics'] )
				$this->update_statistics( $validate );

			// send response code to refuse
			$this->send_response( $hook, $settings['response_code'] );
		}

		return $validate;
	}

	/**
	 * Validate ip address for comment, xmlrpc, login, admin, plugins/themes
	 *
	 */
	public function validate_access( $val = TRUE ) {
		$validate = $this->validate_ip( 'comment', self::get_option( 'settings' ), FALSE );
		return 'passed' !== $validate['result'] ? FALSE : $val;
	}

	public function validate_comment( $commentdata = NULL ) {
		if ( ! is_array( $commentdata ) || 'trackback' === $commentdata['comment_type'] )
			$this->validate_ip( 'comment', self::get_option( 'settings' ) );
		return $commentdata;
	}

	public function validate_xmlrpc( $something ) {
		$this->validate_ip( 'xmlrpc', self::get_option( 'settings' ) );
		return $something;
	}

	public function validate_login() {
		$settings = self::get_option( 'settings' );

		if ( 2 != $settings['validation']['login'] || 'bp_' === substr( current_filter(), 0, 3 ) )
			$this->validate_ip( 'login', $settings );

		elseif ( isset( $_REQUEST['action'] ) && ! in_array( $_REQUEST['action'], array( 'login', 'logout') ) )
			$this->validate_ip( 'login', $settings );
	}

	public function validate_admin() {
		$settings = self::get_option( 'settings' );

		$page   = isset( $_REQUEST['page'  ] ) ? $_REQUEST['page'  ] : NULL;
		$action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : NULL;

		global $pagenow; // http://codex.wordpress.org/Global_Variables
		switch ( $pagenow ) {
		  case 'admin-ajax.php':
			$type = ! has_action( 'wp_ajax_nopriv_' . $action ) ? 'ajax' : NULL;
			break;
		  case 'admin-post.php':
			$type = ! has_action( 'admin_post_nopriv' . ($action ? '_' : '') . $action ) ? 'ajax' : NULL;
			break;
		  default:
			$type = $page || $action ? 'admin' : NULL;
		}

		if ( isset( $type ) && $settings['validation'][ $type ] >= 2 ) {
			// redirect if valid nonce in referer
			$this->trace_nonce();

			// list of request with a specific query to bypass WP-ZEP
			$list = apply_filters( self::PLUGIN_SLUG . '-bypass-admins', array(
				// pluploader won't fire an event in "Media Library"
				'upload-attachment', 'imgedit-preview', 'bp_avatar_upload',
			) );

			// register validation of nonce
			if ( ! in_array( $action, $list, TRUE ) && ! in_array( $page, $list, TRUE ) )
				add_filter( self::PLUGIN_SLUG . '-admin', array( $this, 'check_nonce' ), 5, 2 );
		}

		// Validate country by IP address
		$this->validate_ip( 'admin', $settings );
	}

	public function validate_direct() {
		$settings = self::get_option( 'settings' );

		// retrieve the name of the plugin/theme
		$plugins = preg_quote( self::$content_dir['plugins'], '/' );
		$themes  = preg_quote( self::$content_dir['themes' ], '/' );

		if ( preg_match( "/(?:($plugins)|($themes))([^\/]*)\//", $_SERVER['REQUEST_URI'], $matches ) ) {
			// list of plugins/themes to bypass WP-ZEP
			$list = array(
				'plugins' => array(),
				'themes'  => array(),
			);

			$type = isset( $matches[1] ) ? 'plugins' : 'themes';
			$list = apply_filters( self::PLUGIN_SLUG . "-bypass-{$type}", $list[ $type ] );

			// register validation of nonce
			if ( 2 <= $settings['validation'][ $type ] && ! in_array( $matches[3], $list, TRUE ) )
				add_filter( self::PLUGIN_SLUG . '-admin', array( $this, 'check_nonce' ), 5, 2 );

			// register rewrited request
			if ( defined( 'IP_GEO_BLOCK_REWRITE' ) )
				add_action( self::PLUGIN_SLUG . '-exec', IP_GEO_BLOCK_REWRITE, 10, 2 );
		}

		// Validate country by IP address
		$validate = $this->validate_ip( 'admin', $settings );

		// Execute requested uri via a rewrite rule in plugins/themes
		do_action( self::PLUGIN_SLUG . '-exec', $validate, $settings );
	}

	/* overwrite the redirect URL at logout not to be blocked by WP-ZEP */
	public function logout_redirect( $location ) {
		return isset( $_REQUEST['action'] ) && 'logout' === $_REQUEST['action'] ? 'wp-login.php?loggedout=true' : $location;
	}

	/**
	 * Authentication handling
	 *
	 */
	public function auth_fail( $something = NULL ) {
		require_once( IP_GEO_BLOCK_PATH . 'classes/class-ip-geo-block-apis.php' );

		$settings = self::get_option( 'settings' );

		// Count up a number of fails when authentication is failed
		if ( $cache = IP_Geo_Block_API_Cache::get_cache( $this->remote_addr ) ) {
			$hook = $cache['hook'];
			$validate = array(
				'ip' => $this->remote_addr,
				'code' => $cache['code'],
				'auth' => get_current_user_id(),
				'fail' => TRUE,
				'result' => 'failed',
			);

			IP_Geo_Block_API_Cache::update_cache( $hook, $validate, $settings );

			// (1) blocked, unknown, (3) unauthenticated, (5) all
			if ( (int)$settings['validation']['reclogs'] & 1 ) {
				require_once( IP_GEO_BLOCK_PATH . 'classes/class-ip-geo-block-logs.php' );
				IP_Geo_Block_Logs::record_log( $hook, $validate, $settings );
			}
		}

		return $something; // pass through
	}

	public function check_fail( $validate, $settings ) {
		$cache = IP_Geo_Block_API_Cache::get_cache( $validate['ip'] );

		// Check a number of authentication fails
		if ( $cache && $cache['fail'] >= $settings['login_fails'] )
			$validate['result'] = 'blocked';

		return $validate;
	}

	/* authentication should be prior to other validation when anyone can login */
	public function check_auth( $validate, $settings ) {
		return is_user_logged_in() ? $validate + array( 'result' => 'passed' ) : $validate;
	}

	/**
	 * Validate nonce
	 *
	 */
	public function check_nonce( $validate, $settings ) {
		$nonce = self::PLUGIN_SLUG . '-auth-nonce';

		if ( ! wp_verify_nonce( self::retrieve_nonce( $nonce ), $nonce ) )
			$validate['result'] = 'blocked';

		return $validate;
	}

	private function trace_nonce() {
		$nonce = self::PLUGIN_SLUG . '-auth-nonce';

		if ( empty( $_REQUEST[ $nonce ] ) && self::retrieve_nonce( $nonce ) ) {
			if ( is_user_logged_in() && 'GET' === $_SERVER['REQUEST_METHOD'] ) {
				// redirect to handle with javascript location object.
				wp_redirect( esc_url_raw( $_SERVER['REQUEST_URI'] ), 302 );
				exit;
			}
		}
	}

	public static function retrieve_nonce( $key ) {
		if ( isset( $_REQUEST[ $key ] ) )
			return sanitize_text_field( $_REQUEST[ $key ] );

		if ( isset( $_REQUEST['_wp_http_referer'] ) )
			if ( preg_match( "/$key=([\w]+)/", $_REQUEST['_wp_http_referer'], $matches ) )
				return sanitize_text_field( $matches[1] );

		if ( isset( $_SERVER['HTTP_REFERER'] ) )
			if ( preg_match( "/$key=([\w]+)/", $_SERVER['HTTP_REFERER'], $matches ) )
				return sanitize_text_field( $matches[1] );

		return NULL;
	}

	/**
	 * Cron scheduler.
	 *
	 */
	public static function schedule_cron_job( &$update, $db, $immediate = FALSE ) {
		wp_clear_scheduled_hook( self::CRON_NAME ); // @since 2.1.0

		if ( $update['auto'] ) {
			$now = time();
			$cycle = DAY_IN_SECONDS * (int)$update['cycle'];

			if ( ! $immediate &&
				$now - (int)$db['ipv4_last'] < $cycle &&
				$now - (int)$db['ipv6_last'] < $cycle ) {
				$update['retry'] = 0;
				$next = max( (int)$db['ipv4_last'], (int)$db['ipv6_last'] ) +
					$cycle + rand( DAY_IN_SECONDS, DAY_IN_SECONDS * 6 );
			} else {
				++$update['retry'];
				$next = $now + ( $immediate ? 0 : DAY_IN_SECONDS );
			}

			wp_schedule_single_event( $next, self::CRON_NAME );
		}
	}

	/**
	 * Database auto downloader.
	 *
	 */
	public static function download_database() {
		require_once( IP_GEO_BLOCK_PATH . 'includes/download.php' );

		// download database files
		$settings = self::get_option( 'settings' );
		$res = ip_geo_block_download(
			$settings['maxmind'],
			IP_GEO_BLOCK_DB_DIR,
			self::get_request_headers( $settings )
		);

		// re-schedule cron job
		self::schedule_cron_job( $settings['update'], $settings['maxmind'] );

		// update option settings
		update_option( self::$option_keys['settings'], $settings );

		return $res;
	}

}