<?php
require_once( IP_GEO_BLOCK_PATH . 'includes/localdate.php' );
if ( ! function_exists( 'download_url' ) )
	require_once( ABSPATH . 'wp-admin/includes/file.php' );

/**
 * URL of Maxmind GeoLite database
 */
define( 'IP_GEO_BLOCK_MAXMIND_IPV4_ZIP', 'http://geolite.maxmind.com/download/geoip/database/GeoLiteCountry/GeoIP.dat.gz' );
define( 'IP_GEO_BLOCK_MAXMIND_IPV6_ZIP', 'http://geolite.maxmind.com/download/geoip/database/GeoIPv6.dat.gz' );

/**
 * Set the destination file path and update last-modified
 *
 * @param string $url URL to the source of database zip file.
 * @param string $dir path to the destination directory.
 * @param string $filename full path of uncompressed file.
 * @param int $modified time of last modified on the remote server.
 */
function ip_geo_block_download_path( $url, $dir, &$filename, &$modified ) {
	// if the name of src file is changed, then update the dst
	if ( basename( $filename ) !== ( $base = basename( $url, '.gz' ) ) ) {
		$filename = dirname( $filename ) . "/$base";
	}

	// if the dst is empty or does not exist, then make sure to download
	if ( ! $filename || ! is_readable( $filename ) ) {
		$filename = trailingslashit( $dir ) . $base;
		$modified = 0;
	}
}

/**
 * Download zip file, uncompress and save it to specified file
 *
 * @param string $url URL of remote file to be downloaded.
 * @param array $args request headers.
 * @param string $filename full path to downloaded file.
 * @param int $modified time of last modified on the remote server.
 * @return array status message.
 */
function ip_geo_block_download_zip( $url, $args, $filename, $modified ) {
	// set 'If-Modified-Since' request header
	$args += array(
		'headers'  => array(
			'If-Modified-Since' => gmdate( DATE_RFC1123, (int)$modified ),
		),
	);

	// fetch file and get response code & message
	$res = wp_remote_head( ( $url = esc_url_raw( $url ) ), $args );

	if ( is_wp_error( $res ) )
		return array(
			'code' => $res->get_error_code(),
			'message' => $res->get_error_message(),
		);

	$code = wp_remote_retrieve_response_code   ( $res );
	$mssg = wp_remote_retrieve_response_message( $res );
	$data = wp_remote_retrieve_header( $res, 'last-modified' );
	$modified = $data ? strtotime( $data ) : $modified;

	if ( 304 == $code )
		return array(
			'code' => $code,
			'message' => __( 'Your database file is up-to-date.', IP_Geo_Block::TEXT_DOMAIN ),
			'filename' => $filename,
			'modified' => $modified,
		);

	elseif ( 200 != $code )
		return array(
			'code' => $code,
			'message' => "$code $mssg",
		);

	// downloaded and unzip
	try {
		// download file
		$res = download_url( $url );
		if ( is_wp_error( $res ) )
			throw new Exception(
				$res->get_error_code() . ' ' . $res->get_error_message()
			);

		if ( FALSE === ( $gz = gzopen( $res, 'r' ) ) )
			throw new Exception(
				sprintf(
					__( 'Cannot open %s to read.', IP_Geo_Block::TEXT_DOMAIN ),
					$res
				)
			);

		if ( FALSE === ( $fp = fopen( $filename, 'wb' ) ) )
			throw new Exception(
				sprintf(
					__( 'Cannot open %s to write.', IP_Geo_Block::TEXT_DOMAIN ),
					$filename
				)
			);

		// same block size in wp-includes/class-http.php
		while ( $data = gzread( $gz, 4096 ) )
			fwrite( $fp, $data, strlen( $data ) );

		gzclose( $gz );
		fclose ( $fp );
		@unlink( $res );
	}

	catch ( Exception $e ) {
		if ( ! empty( $gz ) ) gzclose( $gz );
		if ( ! empty( $fp ) ) fclose ( $fp );
		if ( ! is_wp_error( $res ) ) @unlink( $res );

		return array(
			'code' => $e->getCode(),
			'message' => $e->getMessage(),
		);
	}

	return array(
		'code' => $code,
		'message' => sprintf(
			__( 'Last update: %s', IP_Geo_Block::TEXT_DOMAIN ),
			ip_geo_block_localdate( $modified )
		),
		'filename' => $filename,
		'modified' => $modified,
	);
}

/**
 * Download Maxmind database files for IPv4 and IPv6
 *
 * @param array &$db path information for database files.
 * @param string $dir directory where database files are saved.
 * @param array $args request headers.
 * @return array status messages.
 */
function ip_geo_block_download( &$db, $dir, $args ) {
	// directory where database files are saved
	$dir = trailingslashit(
		apply_filters( IP_Geo_Block::PLUGIN_SLUG . '-maxmind-dir', $dir )
	);

	if ( validate_file( $dir ) !== 0 )
		return array(
			'ipv4' => array( 'message' => 'Error: Invalid absolute path' ),
			'ipv6' => array( 'message' => 'Error: Invalid absolute path' ),
		);

	/**
	 * Download database file for IPv4
	 */
	$url = apply_filters(
		IP_Geo_Block::PLUGIN_SLUG . '-maxmind-zip-ipv4', IP_GEO_BLOCK_MAXMIND_IPV4_ZIP
	);

	if ( wp_http_validate_url( $url ) === FALSE )
		return array( 'ipv4' => array( 'message' => 'Error: Invalid URL' ) );

	// set the destination file path
	ip_geo_block_download_path( $url, $dir, $db['ipv4_path'], $db['ipv4_last'] );

	// download and unzip file
	$ipv4 = ip_geo_block_download_zip( $url, $args, $db['ipv4_path'], $db['ipv4_last'] );

	if ( ! empty( $ipv4['filename'] ) )
		$db['ipv4_path'] = $ipv4['filename'];

	if ( ! empty( $ipv4['modified'] ) )
		$db['ipv4_last'] = $ipv4['modified'];

	/**
	 * Download database file for IPv4
	 */
	$url = apply_filters(
		IP_Geo_Block::PLUGIN_SLUG . '-maxmind-zip-ipv6', IP_GEO_BLOCK_MAXMIND_IPV6_ZIP
	);

	if ( wp_http_validate_url( $url ) === FALSE )
		return array( 'ipv6' => array( 'message' => 'Error: Invalid URL' ) );

	// set the destination file path
	ip_geo_block_download_path( $url, $dir, $db['ipv6_path'], $db['ipv6_last'] );

	// download and unzip file
	$ipv6 = ip_geo_block_download_zip( $url, $args, $db['ipv6_path'], $db['ipv6_last'] );

	if ( ! empty( $ipv6['filename'] ) )
		$db['ipv6_path'] = $ipv6['filename'];

	if ( ! empty( $ipv6['modified'] ) )
		$db['ipv6_last'] = $ipv6['modified'];

	return array( 'ipv4' => $ipv4, 'ipv6' => $ipv6 );
}