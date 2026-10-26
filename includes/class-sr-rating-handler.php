<?php
/**
 * منطق اصلی ثبت امتیاز: تشخیص رای‌دهنده (کاربر لاگین یا مهمان با کوکی)،
 * جلوگیری از رای تکراری، و ثبت/به‌روزرسانی امتیاز.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SR_Rating_Handler {

	const COOKIE_NAME = 'sr_voter_token';

	/** @var SR_Repository */
	private $repository;

	public function __construct() {
		$this->repository = new SR_Repository();
	}

	/**
	 * ثبت درخواست امتیازدهی برای یک پست
	 *
	 * @param int $post_id
	 * @param int $rating عدد ۱ تا ۵
	 * @return array{success:bool, message:string, average?:float, count?:int}
	 */
	public function submit_rating( $post_id, $rating ) {
		// اعتبارسنجی ورودی‌ها
		$post_id = absint( $post_id );
		$rating  = absint( $rating );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'پست مورد نظر یافت نشد.', 'star-rating' ),
			);
		}

		if ( $rating < 1 || $rating > 5 ) {
			return array(
				'success' => false,
				'message' => __( 'مقدار امتیاز باید بین ۱ تا ۵ باشد.', 'star-rating' ),
			);
		}

		// بررسی اینکه آیا رای‌دهی مهمان مجاز است
		$settings     = get_option( 'sr_settings', array() );
		$allow_guests = isset( $settings['allow_guest_voting'] ) && 'yes' === $settings['allow_guest_voting'];

		if ( ! is_user_logged_in() && ! $allow_guests ) {
			return array(
				'success' => false,
				'message' => __( 'برای ثبت امتیاز باید وارد حساب کاربری خود شوید.', 'star-rating' ),
			);
		}

		$user_id     = is_user_logged_in() ? get_current_user_id() : null;
		$voter_token = $this->get_or_create_voter_token( $user_id );

		// بررسی رای قبلی: در صورت وجود، به‌روزرسانی می‌کنیم؛ در غیر این صورت رای جدید ثبت می‌شود
		$existing_vote = $this->repository->get_existing_vote( $post_id, $voter_token );

		if ( $existing_vote ) {
			$this->repository->update_rating( $existing_vote->id, $rating );
		} else {
			$this->repository->insert_rating( $post_id, $rating, $user_id, $voter_token );
		}

		$stats = $this->repository->get_post_stats( $post_id );

		return array(
			'success' => true,
			'message' => __( 'امتیاز شما با موفقیت ثبت شد.', 'star-rating' ),
			'average' => $stats['average'],
			'count'   => $stats['count'],
		);
	}

	/**
	 * تولید یا بازیابی توکن یکتای رای‌دهنده.
	 * برای کاربران لاگین‌کرده: بر اساس user_id (ثابت و قابل اتکا).
	 * برای مهمان‌ها: کوکی حاوی یک رشته تصادفی امن که در صورت نبودن ساخته می‌شود.
	 *
	 * توجه: این روش (فقط کوکی) ساده و سبک است اما کاربر می‌تواند با پاک کردن
	 * کوکی‌های مرورگر دوباره رای بدهد. این محدودیت پذیرفته‌شده طبق تصمیم پروژه است.
	 *
	 * @param int|null $user_id
	 * @return string
	 */
	private function get_or_create_voter_token( $user_id ) {
		if ( $user_id ) {
			return 'user_' . $user_id;
		}

		if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) && preg_match( '/^[a-f0-9]{32}$/', $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return 'guest_' . $_COOKIE[ self::COOKIE_NAME ];
		}

		$token = bin2hex( random_bytes( 16 ) );

		$settings     = get_option( 'sr_settings', array() );
		$expiry_days  = isset( $settings['cookie_expiry_days'] ) ? (int) $settings['cookie_expiry_days'] : 365;

		// تنظیم کوکی؛ در صورت ارسال هدر قبلی (مثلاً در REST API) باید پیش از خروجی انجام شود.
		// از آرایه options (بجای پارامترهای جداگانه) استفاده می‌کنیم تا بتوانیم
		// SameSite را صریحاً مشخص کنیم. بدون آن، مرورگرهای مدرن (به‌خصوص Chrome
		// در حالت Incognito که هیچ استثنای قبلی برای کوکی‌های سایت وجود ندارد)
		// کوکی‌هایی که از یک درخواست fetch/AJAX (REST API) ست می‌شوند را با
		// SameSite=Lax پیش‌فرض در نظر می‌گیرند که به‌تنهایی مشکلی ندارد، اما در
		// ترکیب با عدم وجود کوکی قبلی و برخی تنظیمات مرورگر/افزونه حریم خصوصی،
		// کوکی اصلاً ذخیره نمی‌شود. مشخص‌کردن صریح SameSite=Lax و Secure (در
		// صورت HTTPS) این مشکل را برطرف می‌کند.
		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE_NAME,
				$token,
				array(
					'expires'  => time() + ( $expiry_days * DAY_IN_SECONDS ),
					'path'     => COOKIEPATH ? COOKIEPATH : '/',
					'domain'   => COOKIE_DOMAIN,
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
			$_COOKIE[ self::COOKIE_NAME ] = $token;
		}

		return 'guest_' . $token;
	}

	/**
	 * دریافت آمار یک پست (برای نمایش در قالب/شورت‌کد)
	 *
	 * @param int $post_id
	 * @return array
	 */
	public function get_stats( $post_id ) {
		return $this->repository->get_post_stats( absint( $post_id ) );
	}

	/**
	 * بررسی اینکه رای‌دهنده فعلی قبلاً به این پست رای داده و مقدار آن را برمی‌گرداند
	 *
	 * @param int $post_id
	 * @return int|null
	 */
	public function get_current_user_vote( $post_id ) {
		$user_id     = is_user_logged_in() ? get_current_user_id() : null;
		$voter_token = $user_id
			? 'user_' . $user_id
			: ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ? 'guest_' . $_COOKIE[ self::COOKIE_NAME ] : null );

		if ( ! $voter_token ) {
			return null;
		}

		$vote = $this->repository->get_existing_vote( absint( $post_id ), $voter_token );

		return $vote ? (int) $vote->rating : null;
	}
}
