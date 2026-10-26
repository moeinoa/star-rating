<?php
/**
 * عملیات مربوط به غیرفعال‌سازی پلاگین
 * توجه: داده‌ها و تنظیمات حذف نمی‌شوند. حذف کامل در uninstall.php انجام می‌شود.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SR_Deactivator {

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
