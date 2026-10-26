<?php
/**
 * هندلر AJAX عملیات مدیریتی روی رای‌ها و واکنش‌های ثبت‌شده: ویرایش مقدار یا
 * حذف کامل یک رکورد. این کلاس کاملاً از SR_Ajax و SR_Reaction_Ajax (که
 * برای کاربران عادی front-end هستند) جداست و همیشه current_user_can را
 * بررسی می‌کند، چون این عملیات فقط باید در دسترس مدیر کل سایت باشد.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SR_Admin_Ajax {

	public function register() {
		add_action( 'wp_ajax_sr_admin_update_vote', array( $this, 'handle_update_vote' ) );
		add_action( 'wp_ajax_sr_admin_delete_vote', array( $this, 'handle_delete_vote' ) );

		add_action( 'wp_ajax_sr_admin_update_reaction', array( $this, 'handle_update_reaction' ) );
		add_action( 'wp_ajax_sr_admin_delete_reaction', array( $this, 'handle_delete_reaction' ) );
	}

	/**
	 * بررسی نانس + دسترسی مدیر؛ در صورت عدم مجوز، درخواست را با خطا قطع می‌کند.
	 */
	private function verify_admin_request() {
		check_ajax_referer( 'sr_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'شما دسترسی لازم برای این عملیات را ندارید.', 'star-rating' ) ),
				403
			);
		}
	}

	/**
	 * تغییر مقدار یک امتیاز ثبت‌شده (مثلاً از ۱ به ۵)
	 */
	public function handle_update_vote() {
		$this->verify_admin_request();

		$vote_id = isset( $_POST['vote_id'] ) ? absint( $_POST['vote_id'] ) : 0;
		$rating  = isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : 0;

		if ( ! $vote_id || $rating < 1 || $rating > 5 ) {
			wp_send_json_error( array( 'message' => __( 'مقدار امتیاز باید بین ۱ تا ۵ باشد.', 'star-rating' ) ) );
		}

		$repository = new SR_Repository();
		$success    = $repository->update_rating( $vote_id, $rating );

		if ( $success ) {
			wp_send_json_success( array( 'message' => __( 'امتیاز با موفقیت ویرایش شد.', 'star-rating' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'ویرایش امتیاز با خطا مواجه شد.', 'star-rating' ) ) );
		}
	}

	/**
	 * حذف کامل یک امتیاز ثبت‌شده
	 */
	public function handle_delete_vote() {
		$this->verify_admin_request();

		$vote_id = isset( $_POST['vote_id'] ) ? absint( $_POST['vote_id'] ) : 0;

		if ( ! $vote_id ) {
			wp_send_json_error( array( 'message' => __( 'شناسه رای نامعتبر است.', 'star-rating' ) ) );
		}

		$repository = new SR_Repository();
		$success    = $repository->delete_vote( $vote_id );

		if ( $success ) {
			wp_send_json_success( array( 'message' => __( 'امتیاز با موفقیت حذف شد.', 'star-rating' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'حذف امتیاز با خطا مواجه شد.', 'star-rating' ) ) );
		}
	}

	/**
	 * تغییر مقدار یک واکنش ثبت‌شده (جابه‌جایی بین like/dislike)
	 */
	public function handle_update_reaction() {
		$this->verify_admin_request();

		$reaction_id = isset( $_POST['reaction_id'] ) ? absint( $_POST['reaction_id'] ) : 0;
		$reaction    = isset( $_POST['reaction'] ) ? sanitize_key( $_POST['reaction'] ) : '';

		if ( ! $reaction_id || ! in_array( $reaction, array( 'like', 'dislike' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'مقدار واکنش نامعتبر است.', 'star-rating' ) ) );
		}

		$repository = new SR_Reaction_Repository();
		$success    = $repository->update_reaction( $reaction_id, $reaction );

		if ( $success ) {
			wp_send_json_success( array( 'message' => __( 'واکنش با موفقیت ویرایش شد.', 'star-rating' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'ویرایش واکنش با خطا مواجه شد.', 'star-rating' ) ) );
		}
	}

	/**
	 * حذف کامل یک واکنش ثبت‌شده
	 */
	public function handle_delete_reaction() {
		$this->verify_admin_request();

		$reaction_id = isset( $_POST['reaction_id'] ) ? absint( $_POST['reaction_id'] ) : 0;

		if ( ! $reaction_id ) {
			wp_send_json_error( array( 'message' => __( 'شناسه واکنش نامعتبر است.', 'star-rating' ) ) );
		}

		$repository = new SR_Reaction_Repository();
		$success    = $repository->delete_reaction( $reaction_id );

		if ( $success ) {
			wp_send_json_success( array( 'message' => __( 'واکنش با موفقیت حذف شد.', 'star-rating' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'حذف واکنش با خطا مواجه شد.', 'star-rating' ) ) );
		}
	}
}
