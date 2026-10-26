<?php
/**
 * تزریق خودکار شورت‌کد [post_rating] داخل فرم دیدگاه (comment_form)
 *
 * این کلاس مستقل و اضافه‌ای است: به شورت‌کد دستی [post_rating] که کاربر
 * ممکن است در ویرایشگر یا قالب گذاشته باشد دست نمی‌زند و جایگزین آن نیست؛
 * صرفاً بر اساس تنظیمات ادمین (sr_settings['auto_inject']) همان خروجی
 * شورت‌کد را - از طریق do_shortcode - داخل فرم دیدگاه هم چاپ می‌کند تا
 * منطق رندر تکراری/ناهماهنگ با SR_Shortcode نشود.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SR_Comment_Injector {

	public function register() {
		// هوک comment_form درست قبل از </form> و بعد از textarea/دکمه‌های
		// پیش‌فرض اجرا می‌شود، پس خروجی بین باکس متن دیدگاه و دکمه ارسال
		// چاپ می‌شود؛ دقیقاً همان جایگاهی که مدنظر است.
		add_action( 'comment_form', array( $this, 'maybe_inject' ) );
	}

	/**
	 * تصمیم‌گیری و چاپ ویجت امتیازدهی در صورت لزوم
	 *
	 * @param int $post_id پارامتری که خود هوک comment_form پاس می‌دهد
	 */
	public function maybe_inject( $post_id ) {
		$post_id = absint( $post_id );

		if ( ! $post_id || ! $this->should_inject( $post_id ) ) {
			return;
		}

		// عمداً از do_shortcode با همان شورت‌کد عمومی استفاده می‌کنیم تا
		// خروجی همیشه ۱۰۰٪ همان چیزی باشد که [post_rating id="..."] دستی
		// تولید می‌کند (همان CSS/JS/data attribute ها).
		echo do_shortcode( '[post_rating id="' . $post_id . '"]' );
	}

	/**
	 * آیا برای این post_id باید ویجت به‌صورت خودکار تزریق شود؟
	 *
	 * @param int $post_id
	 * @return bool
	 */
	private function should_inject( $post_id ) {
		$settings    = get_option( 'sr_settings', array() );
		$auto_inject = isset( $settings['auto_inject'] ) ? $settings['auto_inject'] : array();

		$mode = isset( $auto_inject['mode'] ) ? $auto_inject['mode'] : 'include';

		if ( 'all_pages' === $mode ) {
			if ( 'page' !== get_post_type( $post_id ) ) {
				return false;
			}

			$exclude_ids = isset( $auto_inject['exclude_ids'] ) ? $auto_inject['exclude_ids'] : array();
			return ! in_array( $post_id, $exclude_ids, true );
		}

		// mode === 'include'
		$include_ids = isset( $auto_inject['include_ids'] ) ? $auto_inject['include_ids'] : array();
		return in_array( $post_id, $include_ids, true );
	}
}
