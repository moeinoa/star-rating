<?php
/**
 * Plugin Name:       Star Rating
 * Plugin URI:        https://github.com/moeinoa/
 * Description:       A 1-to-5 star rating system for posts, with guest user support, plus a built-in like/dislike reaction add-on.
 * Version:           2.0
 * Author:            Moein Akbari
 * Author URI:        https://moein-akbari.ir/
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        star-rating
 * Domain Path:        /languages
 */

// بارگذاری فایل‌های ترجمه‌ی پلاگین
add_action( 'plugins_loaded', function () {
	load_plugin_textdomain( 'star-rating', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

// جلوگیری از دسترسی مستقیم به فایل
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ثابت‌های اصلی پلاگین
define( 'SR_VERSION', '1.0.0' );
define( 'SR_PLUGIN_FILE', __FILE__ );
define( 'SR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SR_DB_VERSION', '1.0' );

/**
 * بارگذاری فایل‌های کلاس‌های اصلی پلاگین
 */
require_once SR_PLUGIN_DIR . 'includes/class-sr-activator.php';
require_once SR_PLUGIN_DIR . 'includes/class-sr-deactivator.php';
require_once SR_PLUGIN_DIR . 'includes/class-sr-repository.php';
require_once SR_PLUGIN_DIR . 'includes/class-sr-rating-handler.php';
require_once SR_PLUGIN_DIR . 'includes/class-sr-shortcode.php';
require_once SR_PLUGIN_DIR . 'includes/class-sr-schema.php';
require_once SR_PLUGIN_DIR . 'includes/class-sr-ajax.php';

// تزریق خودکار [post_rating] داخل فرم دیدگاه بر اساس تنظیمات ادمین.
// مستقل از شورت‌کد دستی است و جایگزین آن نیست (هر دو همزمان کار می‌کنند).
require_once SR_PLUGIN_DIR . 'includes/class-sr-comment-injector.php';

// سیستم واکنش لایک/دیسلایک ([post_reaction]) - مجزا از سیستم امتیازدهی
// ستاره‌ای بالا. توجه: SR_Reaction_Handler به ثابت SR_Rating_Handler::COOKIE_NAME
// وابسته است، پس باید بعد از class-sr-rating-handler.php لود شود.
require_once SR_PLUGIN_DIR . 'includes/class-sr-reaction-icons.php';
require_once SR_PLUGIN_DIR . 'includes/class-sr-reaction-repository.php';
require_once SR_PLUGIN_DIR . 'includes/class-sr-reaction-handler.php';
require_once SR_PLUGIN_DIR . 'includes/class-sr-reaction-shortcode.php';
require_once SR_PLUGIN_DIR . 'includes/class-sr-reaction-ajax.php';

require_once SR_PLUGIN_DIR . 'includes/class-sr-loader.php';

if ( is_admin() ) {
	require_once SR_PLUGIN_DIR . 'includes/class-sr-admin-ajax.php';
	require_once SR_PLUGIN_DIR . 'admin/class-sr-admin.php';
}

/**
 * عملیات زمان فعال‌سازی و غیرفعال‌سازی پلاگین
 */
register_activation_hook( __FILE__, array( 'SR_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SR_Deactivator', 'deactivate' ) );

/**
 * اجرای پلاگین
 */
function sr_run_star_rating() {
	$plugin = new SR_Loader();
	$plugin->run();
}
sr_run_star_rating();
