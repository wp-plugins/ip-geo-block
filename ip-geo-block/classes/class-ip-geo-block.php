<?php
/**
 * IP Geo Block
 *
 * @package   IP_Geo_Block
 * @author    tokkonopapa <tokkonopapa@yahoo.com>
 * @license   GPL-2.0+
 * @link      http://tokkono.cute.coocan.jp/blog/slow/
 * @copyright 2013 tokkonopapa
 */

class IP_Geo_Block {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 */
	const VERSION = '1.0.1';

	/**
	 * Instance of this class.
	 *
	 */
	protected static $instance = null;

	/**
	 * Unique identifier for this plugin.
	 *
	 */
	protected $text_domain = 'ip-geo-block';
	protected $plugin_slug = 'ip-geo-block';
	protected $option_name = array();

	/**
	 * Default values of option table to be cached into options database table.
	 *
	 */
	protected static $option_table = array(

		// settings (should be read on every page that has comment form)
		'ip_geo_block_settings' => array(
			'version'         => '1.0',   // Version of option data
			'order'           => 0,       // Next order of provider (spare for future)
			'providers'       => array(), // List of providers and API keys
			'comment'         => array(   // Message on the comment form
				'pos'         => 0,       // Position (0:none, 1:top, 2:bottom)
				'msg'         => '',      // Message text on comment form
			),
			'matching_rule'   => 0,       // 0:white list, 1:black list
			'white_list'      => 'JP',    // Comma separeted country code
			'black_list'      => '',      // Comma separeted country code
			'timeout'         => 5,       // Timeout in second
			'response_code'   => 403,     // Response code
			'save_statistics' => FALSE,   // Save statistics
			'clean_uninstall' => FALSE,   // Remove all savings from DB
			'ip2location'     => array(   // IP2Location
				'path_db'     => '',      // Path to IP2Location DB
				'path_class'  => '',      // Path to IP2Location class file
			),
		),

		// statistics (should be read when comment has posted)
		'ip_geo_block_statistics' => array(
			'passed'    => 0,
			'blocked'   => 0,
			'unknown'   => 0,
			'IPv4'      => 0,
			'IPv6'      => 0,
			'countries' => array(),
			'providers' => array(),
		),
	);

	// option table accessor by name
	protected static $option_keys = array(
		'settings'   => 'ip_geo_block_settings',
		'statistics' => 'ip_geo_block_statistics',
	);

	public static function get_defaults( $name = 'settings' ) {
		return self::$option_table[ self::$option_keys[ $name ] ];
	}

	/**
	 * Initialize the plugin
	 * 
	 */
	private function __construct() {

		// Set table accessor by name
		foreach ( $this->get_option_keys() as $key => $val) {
			$this->option_name[ $key ] = $val;
		}

		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Message text on comment form
		$key = get_option( $this->option_name['settings'] );
		if ( $key['comment']['pos'] ) {
			$val = 'comment_form' . ( $key['comment']['pos'] == 1 ? '_top' : '' );
			add_action( $val, array( $this, "comment_form_message" ), 10 );
		}

		// The validation function has the same priority as Akismet but will be
		// called earlier becase the initialization timing of Akismet is at `init`.
		add_action( 'preprocess_comment', array( $this, "validate_comment" ), 1 );
	}

	/**
	 * Return the plugin unique value.
	 *
	 */
	public function get_text_domain() { return $this->text_domain;  }
	public function get_plugin_slug() { return $this->plugin_slug;  }
	public function get_option_keys() { return self::$option_keys;  }

	/**
	 * Return an instance of this class.
	 *
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Register options into database table when the plugin is activated.
	 *
	 */
	public static function activate( $network_wide ) {
		// find IP2Location
		$dir = WP_CONTENT_DIR . '/ip2location'; // wp_content
		$ip2 = array();
		if ( file_exists( "$dir/database.bin" ) ) {
			$ip2['path_db'] = "$dir/database.bin";
		}
		if ( file_exists( "$dir/ip2location.class.php" ) ) {
			$ip2['path_class'] = "$dir/ip2location.class.php";
		} else {
			$dir = dirname( IP_GEO_BLOCK_PATH ); // plugins
			$plugins = array(
				'ip2location-tags',
				'ip2location-variables',
				'ip2location-country-blocker',
			);
			foreach ( $plugins as $name ) {
				$class_file = "$dir/$name/ip2location.class.php";
				if ( file_exists( $class_file ) ) {
					$ip2['path_class'] = $class_file;
					break;
				}
			}
		}

		$name = array_keys( self::$option_table );
		$opts = get_option( $name[0] );

		if ( FALSE !== $opts ) {
			if ( version_compare( $opts['version'], '1.0' ) < 0 ) {
			}
			$opts['ip2location'] = $ip2;
			update_option( $name[0], $opts );
		} else {
			self::$option_table[ $name[0] ]['ip2location'] = $ip2;
			add_option( $name[0], self::$option_table[ $name[0] ], '', 'yes' );
			add_option( $name[1], self::$option_table[ $name[1] ], '', 'no' );
		}
	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 */
	public static function deactivate( $network_wide ) {
		// self::uninstall();  // for debug
	}

	/**
	 * Delete options from database when the plugin is uninstalled.
	 *
	 */
	public static function uninstall() {
		$name = array_keys( self::$option_table );
		$settings = get_option( $name[0] );
		if ( $settings['clean_uninstall'] ) {
			foreach ( $name as $key ) {
				delete_option( $key ); // @since 1.2.0
			}
		}
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( $this->text_domain, FALSE, basename( plugin_dir_path( dirname( __FILE__ ) ) ) . '/languages/' );
	}

	/**
	 * Render a message to the comment form.
	 *
	 */
	public function comment_form_message( $id ) {
		$msg = get_option( $this->option_name['settings'] );
		$msg = htmlspecialchars( $msg['comment']['msg'] );
		if ( $msg ) echo '<p id="', $this->plugin_slug, '-msg">', $msg, '</p>';
	}

	/**
	 * Check user's geolocation.
	 *
	 */
	public function check_location( $commentdata, $settings ) {
		// if the post has been already marked as 'blocked' then return
		if ( isset( $commentdata[ $this->plugin_slug ] ) &&
			'blocked' === $commentdata[ $this->plugin_slug ]['result'] ) {
			return $commentdata;
		}

		// include utility class
		require_once( IP_GEO_BLOCK_PATH . '/classes/class-ip-geo-block-api.php' );

		// make providers list
		$list = array();
		$geo = IP_Geo_Block_Provider::get_providers( 'key', FALSE );
		foreach ( $geo as $provider => $key ) {
			if ( ! empty( $settings['providers'][ $provider ] ) || (
			     ! isset( $settings['providers'][ $provider ] ) && NULL === $key ) ) {
				$list[] = $provider;
			}
		}

		// randomize
		shuffle( $list );

		// Add IP2Location if available
		if ( class_exists( 'IP2Location' ) )
			array_unshift( $list, 'IP2Location' );

		// matching rule
		$rule  = $settings['matching_rule'];
		$white = $settings['white_list'];
		$black = $settings['black_list'];

		// get ip address
		$ip = apply_filters( $this->plugin_slug . '-addr', $_SERVER['REMOTE_ADDR'] );

		foreach ( $list as $provider ) {
			$name = IP_Geo_Block_API::get_class_name( $provider );
			if ( $name ) {
				// start time
				$time = microtime( TRUE );

				// get country code
				$key = ! empty( $settings['providers'][ $provider ] );
				$geo = new $name( $key ? $settings['providers'][ $provider ] : NULL );
				$code = strtoupper( $geo->get_country( $ip, $settings['timeout'] ) );

				// process time
				$time = microtime( TRUE ) - $time;
			} else {
				$code = NULL;
			}

			if ( $code ) {
				// for update_statistics()
				$commentdata[ $this->plugin_slug ] = array(
					'ip' => $ip,
					'time' => $time,
					'code' => $code,
					'provider' => $provider,
				);

				// It may not be a spam
				if ( 0 == $rule && FALSE !== strpos( $white, $code ) ||
				     1 == $rule && FALSE === strpos( $black, $code ) ) {
					$commentdata[ $this->plugin_slug ] += array( 'result' => 'passed' );
					return $commentdata;
				}

				// It could be a spam
				else {
					$commentdata[ $this->plugin_slug ] += array( 'result' => 'blocked');
					return $commentdata;
				}
			}
		}

		// if ip address is unknown then pass through
		$commentdata[ $this->plugin_slug ] = array( 'result' => 'unknown' );
		return $commentdata;
	}

	/**
	 * Update statistics
	 *
	 */
	public function update_statistics( $commentdata ) {
		$validate = $commentdata[ $this->plugin_slug ];
		$statistics = get_option( $this->option_name['statistics'] );

		$result = isset( $validate['result'] ) ? $validate['result'] : 'passed';
		$statistics[ $result ] = intval( $statistics[ $result ] ) + 1;

		if ( 'blocked' === $result ) {
			$ip = isset( $validate['ip'] ) ? $validate['ip'] : $_SERVER['REMOTE_ADDR'];
			$time = isset( $validate['time'] ) ? $validate['time'] : 0;
			$country = isset( $validate['code'] ) ? $validate['code'] : 'ZZ';
			$provider = isset( $validate['provider'] ) ? $validate['provider'] : 'ZZ';

			if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) )
				$statistics['IPv4'] = intval( $statistics['IPv4'] ) + 1;

			else if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) )
				$statistics['IPv6'] = intval( $statistics['IPv6'] ) + 1;

			if ( isset( $statistics['providers'][ $provider ] ) )
				$stat = $statistics['providers'][ $provider ];
			else
				$stat = array( 'count' => 0, 'time' => 0 );

			$statistics['providers'][ $provider ] = array(
				'count' => intval( $stat['count'] ) + 1,
				'time'  => floatval( $stat['time' ] ) + $time,
			);

			if ( isset( $statistics['countries'][ $country ] ) )
				$stat = $statistics['countries'];
			else
				$stat = array( $country => 0 );

			$statistics['countries'][ $country ] = intval( $stat[ $country ] ) + 1;
		}

		unset( $commentdata[ $this->plugin_slug ] );
		update_option( $this->option_name['statistics'], $statistics );
	}

	/**
	 * Validate comment.
	 *
	 */
	public function validate_comment( $commentdata ) {
		// pass login user
		if( is_user_logged_in() )
			return $commentdata;

		// register the validation function
		$code = $this->plugin_slug;
		add_filter( "${code}-validate", array( $this, 'check_location' ), 10, 2 );

		// validate and update statistics
		$settings = get_option( $this->option_name['settings'] );
		$result = apply_filters( "${code}-validate", $commentdata, $settings );

		// update statistics
		if ( $settings['save_statistics'] )
			$this->update_statistics( $result );

		// after all filters applied, check whether the result is end in 'blocked'.
		if ( ! isset( $result[ $code ] ) || 'blocked' !== $result[ $code ]['result'] ) {
			return $commentdata;
		}

		// response code
		$code = max( 200, intval( $settings['response_code'] ) ) & 0x1FF; // 200 - 511

		// 2xx Success
		if ( 200 <= $code && $code < 300 ) {
//			header( 'Refresh: 0; url=' . get_site_url(), TRUE, $code ); // @since 3.0
			header( 'Refresh: 0; url=http://blackhole.webpagetest.org/', TRUE, $code );
			die();
		}

		// 3xx Redirection
		else if ( 300 <= $code && $code < 400 ) {
			header( 'Location: http://blackhole.webpagetest.org/', TRUE, $code );
			die();
		}

		// 4xx Client Error
		else if ( 400 <= $code && $code < 500 ) {
			wp_die( __( 'Sorry, your comment cannot be accepted.', $this->text_domain ),
				'Error', array( 'response' => $code, 'back_link' => TRUE ) );
		}

		// 5xx Server Error
		status_header( $code ); // @since 2.0.0
		die();
	}

}