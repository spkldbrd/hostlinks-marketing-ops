<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HMO Maps Service
 *
 * Handles:
 *  - Importing county centroids from the bundled Gazetteer TXT file.
 *  - Importing county population/migration stats from the bundled Census CSV.
 *  - Frontend AJAX lookup: geocode a city+state then run a Haversine radius query.
 */
class HMO_Maps_Service {

	const DATA_DIR    = HMO_PLUGIN_DIR . 'assets/data/';
	const GAZ_FILE    = '2024_Gaz_counties_national.txt';
	const CENPOP_FILE = 'CenPop2020_Mean_CO.txt';
	const POP_FILE    = 'co-est2025-alldata.csv';

	/** Map of 2-digit state FIPS → 2-letter postal abbreviation. */
	private static function state_fips_map(): array {
		return array(
			'01'=>'AL','02'=>'AK','04'=>'AZ','05'=>'AR','06'=>'CA','08'=>'CO','09'=>'CT',
			'10'=>'DE','11'=>'DC','12'=>'FL','13'=>'GA','15'=>'HI','16'=>'ID','17'=>'IL',
			'18'=>'IN','19'=>'IA','20'=>'KS','21'=>'KY','22'=>'LA','23'=>'ME','24'=>'MD',
			'25'=>'MA','26'=>'MI','27'=>'MN','28'=>'MS','29'=>'MO','30'=>'MT','31'=>'NE',
			'32'=>'NV','33'=>'NH','34'=>'NJ','35'=>'NM','36'=>'NY','37'=>'NC','38'=>'ND',
			'39'=>'OH','40'=>'OK','41'=>'OR','42'=>'PA','44'=>'RI','45'=>'SC','46'=>'SD',
			'47'=>'TN','48'=>'TX','49'=>'UT','50'=>'VT','51'=>'VA','53'=>'WA','54'=>'WV',
			'55'=>'WI','56'=>'WY','60'=>'AS','66'=>'GU','69'=>'MP','72'=>'PR','78'=>'VI',
		);
	}

	// ── AJAX registration ─────────────────────────────────────────────────────

	public static function register_ajax(): void {
		add_action( 'wp_ajax_hmo_maps_init_centroids', array( __CLASS__, 'ajax_init_centroids' ) );
		add_action( 'wp_ajax_hmo_maps_sync_stats',     array( __CLASS__, 'ajax_sync_stats' ) );
		add_action( 'wp_ajax_hmo_maps_lookup',         array( __CLASS__, 'ajax_lookup' ) );
		add_action( 'wp_ajax_nopriv_hmo_maps_lookup',  array( __CLASS__, 'ajax_lookup' ) );
	}

	// ── Initialize Centroids ─────────────────────────────────────────────────

	/**
	 * Parse a centroid file and batch-upsert into hmo_maps_county_centroids.
	 *
	 * Supports two sources selected by the `source` POST param:
	 *   "geographic"         — 2024 Census Gazetteer (tab-delimited, INTPTLAT/INTPTLONG)
	 *   "population_weighted"— 2020 Census Centers of Population (CSV, LATITUDE/LONGITUDE)
	 */
	public static function ajax_init_centroids(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		check_ajax_referer( 'hmo_maps_init_centroids' );

		HMO_Maps_DB::create_tables();
		@set_time_limit( 180 );

		$source = sanitize_text_field( $_POST['source'] ?? 'geographic' );
		$use_pop_weighted = ( $source === 'population_weighted' );

		global $wpdb;
		$table = $wpdb->prefix . 'hmo_maps_county_centroids';

		if ( $use_pop_weighted ) {
			$inserted = self::import_cenpop_file( $table );
		} else {
			$inserted = self::import_gazetteer_file( $table );
		}

		if ( is_wp_error( $inserted ) ) {
			wp_send_json_error( $inserted->get_error_message() );
		}

		update_option( 'hmo_maps_centroids_initialized', current_time( 'mysql' ) );
		update_option( 'hmo_maps_centroid_source', $source );

		wp_send_json_success( array( 'rows' => $inserted, 'source' => $source ) );
	}

	/**
	 * Parse the 2024 Census Gazetteer TXT (tab-delimited).
	 * Returns row count or WP_Error.
	 */
	private static function import_gazetteer_file( string $table ): int|\WP_Error {
		global $wpdb;
		$file = self::DATA_DIR . self::GAZ_FILE;
		if ( ! file_exists( $file ) ) {
			return new \WP_Error( 'missing_file', 'Gazetteer data file not found: ' . $file );
		}

		$handle = fopen( $file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return new \WP_Error( 'open_failed', 'Could not open Gazetteer file.' );
		}

		$raw_header = fgetcsv( $handle, 0, "\t" );
		$header     = array_map( 'trim', $raw_header );
		$col        = array_flip( $header );

		if ( ! isset( $col['GEOID'], $col['INTPTLAT'], $col['INTPTLONG'], $col['USPS'], $col['NAME'] ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new \WP_Error( 'bad_header', 'Unexpected Gazetteer header. Found: ' . implode( ', ', $header ) );
		}

		$batch    = array();
		$inserted = 0;
		$batch_sz = 500;

		while ( ( $row = fgetcsv( $handle, 0, "\t" ) ) !== false ) {
			if ( count( $row ) < count( $header ) ) {
				continue;
			}
			$fips        = trim( $row[ $col['GEOID'] ] );
			$lat         = (float) trim( $row[ $col['INTPTLAT'] ] );
			$lng         = (float) trim( $row[ $col['INTPTLONG'] ] );
			$state_abbr  = trim( $row[ $col['USPS'] ] );
			$county_name = trim( $row[ $col['NAME'] ] );

			if ( strlen( $fips ) !== 5 ) {
				continue;
			}

			$batch[] = $wpdb->prepare( '(%s, %s, %s, %f, %f)', $fips, $state_abbr, $county_name, $lat, $lng );

			if ( count( $batch ) >= $batch_sz ) {
				self::flush_centroid_batch( $table, $batch );
				$inserted += count( $batch );
				$batch     = array();
			}
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( ! empty( $batch ) ) {
			self::flush_centroid_batch( $table, $batch );
			$inserted += count( $batch );
		}

		return $inserted;
	}

	/**
	 * Parse the 2020 Census Centers of Population CSV.
	 * Columns: STATEFP, COUNTYFP, COUNAME, STNAME, POPULATION, LATITUDE, LONGITUDE
	 * Returns row count or WP_Error.
	 */
	private static function import_cenpop_file( string $table ): int|\WP_Error {
		global $wpdb;
		$file = self::DATA_DIR . self::CENPOP_FILE;
		if ( ! file_exists( $file ) ) {
			return new \WP_Error( 'missing_file', 'Population-weighted centroids file not found: ' . $file );
		}

		$handle = fopen( $file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return new \WP_Error( 'open_failed', 'Could not open CenPop file.' );
		}

		$raw_header = fgetcsv( $handle );
		$header     = array_map( 'trim', $raw_header );
		// Strip UTF-8 BOM from the first column if the file was saved with one.
		$header[0]  = ltrim( $header[0], "\xEF\xBB\xBF" );
		$col        = array_flip( $header );

		if ( ! isset( $col['STATEFP'], $col['COUNTYFP'], $col['COUNAME'], $col['LATITUDE'], $col['LONGITUDE'] ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new \WP_Error( 'bad_header', 'Unexpected CenPop header. Found: ' . implode( ', ', $header ) );
		}

		$fips_map = self::state_fips_map();
		$batch    = array();
		$inserted = 0;
		$batch_sz = 500;

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			if ( count( $row ) < count( $header ) ) {
				continue;
			}
			$state_fp    = str_pad( trim( $row[ $col['STATEFP'] ] ), 2, '0', STR_PAD_LEFT );
			$county_fp   = str_pad( trim( $row[ $col['COUNTYFP'] ] ), 3, '0', STR_PAD_LEFT );
			$fips        = $state_fp . $county_fp;
			$lat         = (float) trim( $row[ $col['LATITUDE'] ] );
			$lng         = (float) trim( $row[ $col['LONGITUDE'] ] );
			$county_name = trim( $row[ $col['COUNAME'] ] );
			$state_abbr  = $fips_map[ $state_fp ] ?? '';

			if ( strlen( $fips ) !== 5 || ! $state_abbr ) {
				continue;
			}
			if ( ! is_finite( $lat ) || ! is_finite( $lng ) ) {
				continue;
			}
			if ( $lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0 ) {
				continue;
			}

			$batch[] = $wpdb->prepare( '(%s, %s, %s, %f, %f)', $fips, $state_abbr, $county_name, $lat, $lng );

			if ( count( $batch ) >= $batch_sz ) {
				self::flush_centroid_batch( $table, $batch );
				$inserted += count( $batch );
				$batch     = array();
			}
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( ! empty( $batch ) ) {
			self::flush_centroid_batch( $table, $batch );
			$inserted += count( $batch );
		}

		return $inserted;
	}

	/** Execute one batch INSERT … ON DUPLICATE KEY UPDATE for centroids. */
	private static function flush_centroid_batch( string $table, array $batch ): void {
		global $wpdb;
		$values = implode( ', ', $batch );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			"INSERT INTO {$table} (fips, state_abbr, county_name, lat, lng)
			 VALUES {$values}
			 ON DUPLICATE KEY UPDATE
			   state_abbr  = VALUES(state_abbr),
			   county_name = VALUES(county_name),
			   lat         = VALUES(lat),
			   lng         = VALUES(lng)"
		);
	}

	// ── Sync Stats ───────────────────────────────────────────────────────────

	/**
	 * Parse the Census PEP CSV and upsert into hmo_maps_county_stats.
	 * Only SUMLEV=050 rows (county level) are imported.
	 */
	public static function ajax_sync_stats(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		check_ajax_referer( 'hmo_maps_sync_stats' );

		// Ensure tables exist (in case the admin hasn't run the DB upgrade yet).
		HMO_Maps_DB::create_tables();

		@set_time_limit( 180 );

		$file = self::DATA_DIR . self::POP_FILE;
		if ( ! file_exists( $file ) ) {
			wp_send_json_error( 'Population data file not found: ' . $file );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'hmo_maps_county_stats';

		$handle = fopen( $file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			wp_send_json_error( 'Could not open population file.' );
		}

		$raw_header = fgetcsv( $handle );
		$header     = array_map( 'trim', $raw_header );
		$col        = array_flip( $header );

		$required = array( 'SUMLEV', 'STATE', 'COUNTY', 'STNAME', 'CTYNAME', 'POPESTIMATE2025', 'NETMIG2025' );
		foreach ( $required as $req ) {
			if ( ! isset( $col[ $req ] ) ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				wp_send_json_error( "Column '{$req}' not found in population CSV." );
			}
		}

		$now      = current_time( 'mysql' );
		$batch    = array();
		$inserted = 0;
		$batch_sz = 500;

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			if ( ( trim( $row[ $col['SUMLEV'] ] ) ) !== '050' ) {
				continue; // Skip state-level rows.
			}

			$state_fips  = str_pad( trim( $row[ $col['STATE'] ] ),  2, '0', STR_PAD_LEFT );
			$county_fips = str_pad( trim( $row[ $col['COUNTY'] ] ), 3, '0', STR_PAD_LEFT );
			$fips        = $state_fips . $county_fips;

			$state_name  = sanitize_text_field( $row[ $col['STNAME'] ] );
			$county_name = sanitize_text_field( $row[ $col['CTYNAME'] ] );
			$pop         = (int) $row[ $col['POPESTIMATE2025'] ];
			$netmig      = (int) $row[ $col['NETMIG2025'] ];

			$batch[] = $wpdb->prepare(
				'(%s, %s, %s, %d, %d, %s)',
				$fips, $state_name, $county_name, $pop, $netmig, $now
			);

			if ( count( $batch ) >= $batch_sz ) {
				self::flush_stats_batch( $table, $batch );
				$inserted += count( $batch );
				$batch     = array();
			}
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( ! empty( $batch ) ) {
			self::flush_stats_batch( $table, $batch );
			$inserted += count( $batch );
		}

		update_option( 'hmo_maps_last_sync', current_time( 'mysql' ) );

		wp_send_json_success( array( 'rows' => $inserted ) );
	}

	/** Execute one batch INSERT … ON DUPLICATE KEY UPDATE for stats. */
	private static function flush_stats_batch( string $table, array $batch ): void {
		global $wpdb;
		$values = implode( ', ', $batch );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			"INSERT INTO {$table} (fips, state_name, county_name, pop_2025, netmig_2025, synced_at)
			 VALUES {$values}
			 ON DUPLICATE KEY UPDATE
			   state_name  = VALUES(state_name),
			   county_name = VALUES(county_name),
			   pop_2025    = VALUES(pop_2025),
			   netmig_2025 = VALUES(netmig_2025),
			   synced_at   = VALUES(synced_at)"
		);
	}

	// ── Frontend Lookup ───────────────────────────────────────────────────────

	/**
	 * Geocode a city+state string via the Census Geocoder, then run a
	 * Haversine query to find all counties within the requested radius.
	 *
	 * POST params:
	 *   location  — "City, State" string
	 *   radius    — integer miles (25–500)
	 *   nonce     — wp_nonce 'hmo_maps_lookup'
	 */
	public static function ajax_lookup(): void {
		check_ajax_referer( 'hmo_maps_lookup', 'nonce' );

		// Access check — same gate as the shortcode.
		$access = new HMO_Access_Service();
		if ( ! $access->can_view_shortcode( 'display_maps_tool' ) ) {
			wp_send_json_error( 'Access denied.' );
		}

		// Ensure tables exist (triggers on first load after plugin update).
		HMO_Maps_DB::create_tables();

		$location = sanitize_text_field( $_POST['location'] ?? '' );
		$radius   = max( 25, min( 500, (int) ( $_POST['radius'] ?? 100 ) ) );

		if ( empty( $location ) ) {
			wp_send_json_error( 'Please enter a city and state.' );
		}

		// 1. Use pre-resolved coordinates from the autocomplete if the client
		//    already called Nominatim and pinned a lat/lng. This skips an extra
		//    geocoder round-trip and makes the lookup instant.
		$pinned_lat = isset( $_POST['lat'] ) ? (float) $_POST['lat'] : 0.0;
		$pinned_lng = isset( $_POST['lng'] ) ? (float) $_POST['lng'] : 0.0;
		$has_pins   = self::is_valid_lookup_coordinate_pair( $pinned_lat, $pinned_lng );

		$google_key = trim( (string) get_option( 'hmo_maps_google_api_key', '' ) );
		if ( $google_key !== '' && ! $has_pins ) {
			wp_send_json_error(
				'Choose a location from the address suggestions before lookup, or clear the Google Maps API key in Marketing Ops settings to allow server-side geocoding for typed-only addresses.'
			);
		}

		if ( $has_pins ) {
			$center_lat = $pinned_lat;
			$center_lng = $pinned_lng;
		} else {
			// Fallback: geocode server-side via Nominatim (manual entry path; no Google key or key cleared).
			$geo_url = add_query_arg(
				array(
					'q'            => rawurlencode( $location ),
					'format'       => 'json',
					'limit'        => 1,
					'countrycodes' => 'us',
				),
				'https://nominatim.openstreetmap.org/search'
			);

			$response = wp_remote_get( $geo_url, array(
				'timeout' => 15,
				'headers' => array(
					'User-Agent' => 'HostlinksMarketingOps/1.0 (WordPress plugin)',
				),
			) );

			if ( is_wp_error( $response ) ) {
				wp_send_json_error( 'Geocoder request failed: ' . $response->get_error_message() );
			}

			$geo_results = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( empty( $geo_results ) || ! isset( $geo_results[0]['lat'] ) ) {
				wp_send_json_error( 'Location not found. Try "City, State" format — e.g. "Denver, CO" or "Chicago, IL".' );
			}

			$center_lat = (float) $geo_results[0]['lat'];
			$center_lng = (float) $geo_results[0]['lon'];

			if ( ! self::is_valid_lookup_coordinate_pair( $center_lat, $center_lng ) ) {
				wp_send_json_error( 'Could not extract coordinates from geocoder response.' );
			}
		}

		// 2. Haversine radius query joining centroids to stats.
		global $wpdb;
		$centroids = $wpdb->prefix . 'hmo_maps_county_centroids';
		$stats     = $wpdb->prefix . 'hmo_maps_county_stats';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$counties = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					c.fips,
					c.state_abbr,
					c.county_name,
					COALESCE(s.state_name, '')  AS state_name,
					COALESCE(s.pop_2025, 0)     AS pop_2025,
					COALESCE(s.netmig_2025, 0)  AS netmig_2025,
					ROUND(
						3958.8 * ACOS(
							LEAST( 1.0,
								COS(RADIANS(%f)) * COS(RADIANS(c.lat))
								* COS(RADIANS(c.lng) - RADIANS(%f))
								+ SIN(RADIANS(%f)) * SIN(RADIANS(c.lat))
							)
						), 1
					) AS distance_miles
				FROM {$centroids} c
				LEFT JOIN {$stats} s ON c.fips = s.fips
				HAVING distance_miles <= %d
				ORDER BY distance_miles ASC",
				$center_lat,
				$center_lng,
				$center_lat,
				$radius
			)
		);

		$total_pop    = 0;
		$total_netmig = 0;
		foreach ( $counties as $c ) {
			$total_pop    += (int) $c->pop_2025;
			$total_netmig += (int) $c->netmig_2025;
		}

		wp_send_json_success( array(
			'center'       => array( 'lat' => $center_lat, 'lng' => $center_lng ),
			'radius'       => $radius,
			'location'     => $location,
			'total_pop'    => $total_pop,
			'total_netmig' => $total_netmig,
			'count'        => count( $counties ),
			'counties'     => $counties,
		) );
	}

	/** True when lat/lng are finite, in range, and not both effectively zero (unset). */
	private static function is_valid_lookup_coordinate_pair( float $lat, float $lng ): bool {
		if ( ! is_finite( $lat ) || ! is_finite( $lng ) ) {
			return false;
		}
		if ( $lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0 ) {
			return false;
		}
		if ( abs( $lat ) < 1e-6 && abs( $lng ) < 1e-6 ) {
			return false;
		}
		return true;
	}
}
