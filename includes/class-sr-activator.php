<?php
/**
 * عملیات مربوط به فعال‌سازی پلاگین: ساخت جدول دیتابیس و تنظیمات پیش‌فرض
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SR_Activator {

	/**
	 * اجرا در زمان فعال‌سازی پلاگین
	 */
	public static function activate() {
		self::create_ratings_table();
		self::create_reactions_table();
		self::set_default_options();
		update_option( 'sr_db_version', SR_DB_VERSION );

		// پاک‌سازی rewrite rules در صورت نیاز آینده
		flush_rewrite_rules();
	}

	/**
	 * ساخت جدول اختصاصی برای ذخیره امتیازهای ستاره‌ای ([post_rating])
	 */
	private static function create_ratings_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'sr_post_ratings';
		$charset_collate = $wpdb->get_charset_collate();

		// dbDelta برای ساخت یا آپدیت ساختار جدول به صورت ایمن
		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL,
			rating TINYINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NULL DEFAULT NULL,
			voter_token VARCHAR(64) NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY voter_token (voter_token),
			KEY post_voter (post_id, voter_token)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * ساخت جدول اختصاصی برای ذخیره واکنش‌های لایک/دیسلایک ([post_reaction])
	 */
	private static function create_reactions_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'sr_post_reactions';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL,
			reaction ENUM('like','dislike') NOT NULL,
			user_id BIGINT UNSIGNED NULL DEFAULT NULL,
			voter_token VARCHAR(64) NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY voter_token (voter_token),
			KEY post_voter (post_id, voter_token)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * تنظیم مقادیر پیش‌فرض تنظیمات پلاگین
	 */
	private static function set_default_options() {
		$defaults = array(
			'allow_guest_voting'  => 'yes',
			'star_color'          => '#f5a623',
			'cookie_expiry_days'  => 365,
		);

		if ( false === get_option( 'sr_settings' ) ) {
			add_option( 'sr_settings', $defaults );
		}
	}
}
