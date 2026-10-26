<?php
/**
 * اجرا فقط زمانی که کاربر پلاگین را از پنل ادمین کاملاً حذف (Delete) می‌کند.
 * غیرفعال‌سازی ساده باعث اجرای این فایل نمی‌شود.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$ratings_table   = $wpdb->prefix . 'sr_post_ratings';
$reactions_table = $wpdb->prefix . 'sr_post_reactions';

// حذف جداول اختصاصی
$wpdb->query( "DROP TABLE IF EXISTS {$ratings_table}" );
$wpdb->query( "DROP TABLE IF EXISTS {$reactions_table}" );

// حذف تنظیمات ذخیره‌شده
delete_option( 'sr_settings' );
delete_option( 'sr_db_version' );
