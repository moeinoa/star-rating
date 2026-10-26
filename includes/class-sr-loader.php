<?php
/**
 * کلاس اصلی اجرای پلاگین: ثبت hook های عمومی، بارگذاری اسکریپت/استایل،
 * ثبت هندلر AJAX و راه‌اندازی پنل ادمین
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SR_Loader {

	/** @var SR_Ajax */
	private $ajax;

	/** @var SR_Reaction_Ajax */
	private $reaction_ajax;

	public function run() {
		// enqueue روی wp_enqueue_scripts، مثل استاندارد وردپرس.
		// تشخیص "آیا ویجت رندر شده یا نه" حذف شد چون با Elementor
		// (Dynamic Tag / AJAX render / loop items) قابل اتکا نبود؛
		// asset ها همیشه در صفحات front-end لود می‌شوند (حجم کم و بی‌خطر است).
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );

		$shortcode = new SR_Shortcode();
		$shortcode->register();

		// تزریق خودکار [post_rating] در فرم دیدگاه (بر اساس تنظیمات ادمین)؛
		// مستقل از شورت‌کد دستی بالا و بدون هیچ تغییری در رفتار آن.
		$comment_injector = new SR_Comment_Injector();
		$comment_injector->register();

		// شورت‌کد [post_reaction]: لایک/دیسلایک برای پست‌های وبلاگ.
		// عمداً به SR_Schema وصل نمی‌شود (بدون تولید Schema.org).
		$reaction_shortcode = new SR_Reaction_Shortcode();
		$reaction_shortcode->register();

		$schema = new SR_Schema();
		$schema->register();

		// از admin-ajax.php (داخلی و همیشه-در-دسترس وردپرس) استفاده می‌کنیم؛
		// نیازی به رجیستر کردن روی rest_api_init نیست چون hook های
		// wp_ajax_* مستقیماً هنگام لود همین کلاس ثبت می‌شوند.
		$this->ajax = new SR_Ajax();
		$this->ajax->register();

		$this->reaction_ajax = new SR_Reaction_Ajax();
		$this->reaction_ajax->register();

		if ( is_admin() ) {
			$admin = new SR_Admin();
			$admin->run();
		}
	}

	/**
	 * بارگذاری CSS/JS در تمام صفحات front-end.
	 *
	 * چرا بدون شرط enqueue می‌کنیم؟ تشخیص "آیا شورت‌کد/ویجت واقعاً چاپ شده
	 * یا نه" روی wp_footer برای محتوای معمولی کار می‌کرد، اما با Elementor
	 * قابل اتکا نیست: Dynamic Tag ها و آیتم‌های Loop Grid ممکن است از طریق
	 * درخواست AJAX جداگانه (elementor_ajax) رندر شوند که اصلاً هوک
	 * wp_footor صفحه اصلی را trigger نمی‌کند؛ نتیجه این بود که CSS/JS هیچ‌وقت
	 * چاپ نمی‌شد و ویجت روی صفحه بی‌استایل/بدون تعامل می‌ماند.
	 * فایل‌ها سبک هستند، پس enqueue همیشگی روی wp_enqueue_scripts
	 * ساده‌ترین راه‌حل مطمئن است.
	 */
	public function enqueue_public_assets() {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_style(
			'sr-public-style',
			SR_PLUGIN_URL . 'assets/css/star-rating.css',
			array(),
			SR_VERSION
		);

		wp_enqueue_style(
			'sr-reaction-style',
			SR_PLUGIN_URL . 'assets/css/reaction.css',
			array(),
			SR_VERSION
		);

		wp_enqueue_script(
			'sr-public-script',
			SR_PLUGIN_URL . 'assets/js/star-rating.js',
			array(),
			SR_VERSION,
			true
		);

		wp_enqueue_script(
			'sr-reaction-script',
			SR_PLUGIN_URL . 'assets/js/reaction.js',
			array(),
			SR_VERSION,
			true
		);

		wp_localize_script(
			'sr-public-script',
			'srRatingData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'sr_rating_nonce' ),
				'i18n'    => array(
					'error' => __( 'خطایی رخ داد. لطفاً دوباره تلاش کنید.', 'star-rating' ),
				),
			)
		);

		wp_localize_script(
			'sr-reaction-script',
			'srReactionData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'sr_reaction_nonce' ),
				'i18n'    => array(
					'error' => __( 'خطایی رخ داد. لطفاً دوباره تلاش کنید.', 'star-rating' ),
				),
			)
		);
	}
}
