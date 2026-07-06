<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Entries;

use Alovio\Calculator\Fields\FieldRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Quote-form file upload (spec §3.3). Async upload → one-time 32-hex token →
 * token rides the quote and is written into the entry snapshot. Files live under
 * uploads/alovio-calc/ with random names; direct web access is denied (.htaccess;
 * on nginx the random names are the fallback) — admins stream downloads through
 * the capability-gated route below. Orphans are GC'd daily after 24 h.
 */
final class FileUploads {

	public const CRON_HOOK     = 'alovio_calc_file_gc';
	public const OPTION_PREFIX = 'alovio_calc_upload_';
	public const SUBDIR        = 'alovio-calc';

	private const RATE_LIMIT = 10; // uploads per hour per IP.
	private const MIMES      = array(
		'jpg'  => 'image/jpeg',
		'png'  => 'image/png',
		'webp' => 'image/webp',
		'pdf'  => 'application/pdf',
	);

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'init', array( $this, 'schedule_gc' ) );
		add_action( self::CRON_HOOK, array( $this, 'gc_orphans' ) );
		register_deactivation_hook( ALOVIO_CALC_FILE, array( __CLASS__, 'unschedule' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'alovio-calc/v1',
			'/quote-file',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true', // Public like /quote (spec §10): honeypot + rate limit, no nonce (cache-safe).
				'callback'            => array( $this, 'handle_upload' ),
			)
		);
		register_rest_route(
			'alovio-calc/v1',
			'/entries/(?P<id>\d+)/file',
			array(
				'methods'             => 'GET',
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'callback'            => array( $this, 'download' ),
			)
		);
	}

	/**
	 * Pure decision core — unit-tested without WP. $mime comes from finfo (content
	 * sniffing), so a renamed executable fails even with an allowed extension.
	 *
	 * @return array{ok:bool, code:string, ext:string}
	 */
	public static function validate_upload( string $name, int $size, string $mime, array $fileSettings ): array {
		$maxMb = (int) ( $fileSettings['maxMb'] ?? 5 );
		if ( $size <= 0 || $size > $maxMb * 1048576 ) {
			return array(
				'ok'   => false,
				'code' => 'too_large',
				'ext'  => '',
			);
		}
		$ext = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( 'jpeg' === $ext ) {
			$ext = 'jpg';
		}
		$allowed = array_intersect( (array) ( $fileSettings['types'] ?? array() ), array_keys( self::MIMES ) );
		if ( ! in_array( $ext, $allowed, true ) || self::MIMES[ $ext ] !== $mime ) {
			return array(
				'ok'   => false,
				'code' => 'bad_type',
				'ext'  => $ext,
			);
		}
		return array(
			'ok'   => true,
			'code' => '',
			'ext'  => $ext,
		);
	}

	/** @param \WP_REST_Request $request */
	public function handle_upload( $request ) {
		if ( '' !== (string) $request->get_param( 'alc_website' ) ) { // Honeypot — pretend success to bots.
			return new \WP_REST_Response(
				array(
					'token' => bin2hex( random_bytes( 16 ) ), // Never stored; unusable.
					'name'  => '',
				),
				201
			);
		}
		if ( ! $this->within_rate_limit() ) {
			return new \WP_Error( 'alc_rate_limited', __( 'Too many uploads. Please try again later.', 'alovio-calculator' ), array( 'status' => 429 ) );
		}

		$calculator_id = absint( $request->get_param( 'calculatorId' ) );
		$config        = ( new FieldRepository() )->get( $calculator_id );
		$fileSettings  = $config['settings']['quoteForm']['file'];
		if ( empty( $config['settings']['quoteForm']['enabled'] ) || empty( $fileSettings['enabled'] ) ) {
			return new \WP_Error( 'alc_uploads_disabled', __( 'File uploads are not enabled.', 'alovio-calculator' ), array( 'status' => 400 ) );
		}

		$files = $request->get_file_params();
		$file  = isset( $files['file'] ) && is_array( $files['file'] ) ? $files['file'] : null;
		if ( null === $file || empty( $file['tmp_name'] ) ) {
			return new \WP_Error( 'alc_no_file', __( 'No file received.', 'alovio-calculator' ), array( 'status' => 400 ) );
		}

		$original = sanitize_file_name( (string) ( $file['name'] ?? 'file' ) );
		$finfo    = finfo_open( FILEINFO_MIME_TYPE );
		$mime     = false !== $finfo ? (string) finfo_file( $finfo, (string) $file['tmp_name'] ) : '';
		if ( false !== $finfo ) {
			finfo_close( $finfo );
		}
		$check = self::validate_upload( $original, (int) ( $file['size'] ?? 0 ), $mime, $fileSettings );
		if ( ! $check['ok'] ) {
			return 'too_large' === $check['code']
				? new \WP_Error( 'alc_too_large', __( 'The file is too large.', 'alovio-calculator' ), array( 'status' => 413 ) )
				: new \WP_Error( 'alc_bad_type', __( 'This file type is not allowed.', 'alovio-calculator' ), array( 'status' => 415 ) );
		}

		$token = bin2hex( random_bytes( 16 ) );
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		add_filter( 'upload_dir', array( __CLASS__, 'upload_dir' ) );
		$moved = wp_handle_upload(
			$file,
			array(
				'test_form'                => false,
				'unique_filename_callback' => static function ( $dir, $name, $extension ) use ( $token ) {
					return 'alc-' . $token . strtolower( (string) $extension );
				},
			)
		);
		remove_filter( 'upload_dir', array( __CLASS__, 'upload_dir' ) );
		if ( ! is_array( $moved ) || empty( $moved['file'] ) || ! empty( $moved['error'] ) ) {
			return new \WP_Error( 'alc_upload_failed', __( 'Upload failed. Please try again.', 'alovio-calculator' ), array( 'status' => 500 ) );
		}

		update_option(
			self::OPTION_PREFIX . $token,
			array(
				'stored' => basename( (string) $moved['file'] ),
				'name'   => $original,
				'time'   => time(),
			),
			false
		);

		return new \WP_REST_Response(
			array(
				'token' => $token,
				'name'  => $original,
			),
			201
		);
	}

	/**
	 * One-time consume: the quote submission claims the token; the GC then never
	 * touches the file (the entry owns it). Pure enough to unit-test with stubs.
	 *
	 * @return array{name:string, stored:string}|null
	 */
	public static function consume( string $token ): ?array {
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
			return null;
		}
		$row = get_option( self::OPTION_PREFIX . $token );
		if ( ! is_array( $row ) || empty( $row['stored'] ) ) {
			return null;
		}
		delete_option( self::OPTION_PREFIX . $token );
		return array(
			'name'   => (string) ( $row['name'] ?? '' ),
			'stored' => (string) $row['stored'],
		);
	}

	/** @param \WP_REST_Request $request */
	public function download( $request ) {
		$row      = ( new EntriesRepository() )->find( (int) $request['id'] );
		$snapshot = null !== $row ? json_decode( (string) $row['snapshot'], true ) : null;
		$file     = is_array( $snapshot ) && ! empty( $snapshot['file']['stored'] ) ? (array) $snapshot['file'] : null;
		if ( null === $file ) {
			return new \WP_Error( 'alc_not_found', __( 'No file for this entry.', 'alovio-calculator' ), array( 'status' => 404 ) );
		}
		$path = self::dir_path() . '/' . basename( (string) $file['stored'] );
		if ( ! file_exists( $path ) ) {
			return new \WP_Error( 'alc_not_found', __( 'The file is missing on disk.', 'alovio-calculator' ), array( 'status' => 404 ) );
		}
		$ext  = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );
		$mime = self::MIMES[ 'jpeg' === $ext ? 'jpg' : $ext ] ?? 'application/octet-stream';
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( (string) ( $file['name'] ?? 'file' ) ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- streaming a verified local file to an authorized admin.
		exit;
	}

	/** Entry delete + privacy erase call this before removing the row (spec §3.3). */
	public static function delete_entry_file( array $row ): void {
		$snapshot = json_decode( (string) ( $row['snapshot'] ?? '' ), true );
		if ( ! is_array( $snapshot ) || empty( $snapshot['file']['stored'] ) ) {
			return;
		}
		$path = self::dir_path() . '/' . basename( (string) $snapshot['file']['stored'] );
		if ( file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * @param array<string,mixed> $dirs
	 * @return array<string,mixed>
	 */
	public static function upload_dir( array $dirs ): array {
		$dirs['subdir'] = '/' . self::SUBDIR;
		$dirs['path']   = $dirs['basedir'] . '/' . self::SUBDIR;
		$dirs['url']    = $dirs['baseurl'] . '/' . self::SUBDIR;
		if ( ! is_dir( $dirs['path'] ) ) {
			wp_mkdir_p( $dirs['path'] );
			@file_put_contents( $dirs['path'] . '/.htaccess', "Require all denied\n" ); // phpcs:ignore
			@file_put_contents( $dirs['path'] . '/index.html', '' ); // phpcs:ignore
		}
		return $dirs;
	}

	private static function dir_path(): string {
		$uploads = wp_upload_dir();
		return (string) $uploads['basedir'] . '/' . self::SUBDIR;
	}

	public function schedule_gc(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/** Delete never-claimed uploads older than 24 h (their token option still exists ⇒ orphan). */
	public function gc_orphans(): void {
		global $wpdb;
		$names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'alovio\\_calc\\_upload\\_%'" ); // phpcs:ignore WordPress.DB
		foreach ( (array) $names as $name ) {
			$row = get_option( $name );
			if ( is_array( $row ) && ! empty( $row['time'] ) && ( time() - (int) $row['time'] ) > DAY_IN_SECONDS ) {
				if ( ! empty( $row['stored'] ) ) {
					$path = self::dir_path() . '/' . basename( (string) $row['stored'] );
					if ( file_exists( $path ) ) {
						wp_delete_file( $path );
					}
				}
				delete_option( $name );
			}
		}
	}

	private function within_rate_limit(): bool {
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : ''; // REMOTE_ADDR only — spec §10 (XFF is spoofable).
		$key   = 'alovio_calc_uplrl_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}
}
