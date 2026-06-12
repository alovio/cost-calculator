<?php
namespace Alovio\Calculator\Entries;

final class EntriesTable {

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'alc_entries';
	}

	/** Spec §5 DDL. Idempotent via dbDelta. */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				calculator_id BIGINT UNSIGNED NOT NULL,
				name VARCHAR(190) NOT NULL DEFAULT '',
				email VARCHAR(190) NOT NULL DEFAULT '',
				phone VARCHAR(64) NOT NULL DEFAULT '',
				message TEXT NULL,
				snapshot LONGTEXT NOT NULL,
				total DECIMAL(18,4) NOT NULL DEFAULT 0,
				status VARCHAR(20) NOT NULL DEFAULT 'new',
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY calculator_id (calculator_id),
				KEY created_at (created_at)
			) {$charset};"
		);
	}

	/** Network activation + new-subsite support (spec §5 Multisite). */
	public static function install_for_network( bool $network_wide ): void {
		if ( ! $network_wide || ! is_multisite() ) {
			self::install();
			return;
		}
		foreach ( get_sites(
			[
				'fields' => 'ids',
				'number' => 0,
			]
		) as $site_id ) {
			switch_to_blog( (int) $site_id );
			self::install();
			restore_current_blog();
		}
	}
}
