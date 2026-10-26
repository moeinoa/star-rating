<?php
/**
 * منطق اصلی ثبت واکنش لایک/دیسلایک: تشخیص رای‌دهنده (کاربر لاگین یا مهمان با
 * کوکی، با استفاده از همان کوکی سیستم rating تا کاربر یک هویت یکتا در کل
 * پلاگین داشته باشد)، رفتار toggle (کلیک دوباره روی همان دکمه = لغو واکنش)،
 * و جابه‌جایی بین لایک و دیسلایک.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SR_Reaction_Handler {

	// از همان نام کوکی سیستم rating استفاده می‌کنیم تا هویت مهمان در کل
	// پلاگین (هم rating و هم reaction) یکسان و پایدار بماند.
	const COOKIE_NAME = SR_Rating_Handler::COOKIE_NAME;

	/** @var SR_Reaction_Repository */
	private $repository;

	public function __construct() {
		$this->repository = new SR_Reaction_Repository();
	}

	/**
	 * ثبت درخواست واکنش برای یک پست. اگر کاربر همان واکنش قبلی را دوباره
	 * بزند، واکنش لغو می‌شود (toggle off). اگر واکنش متفاوتی بزند
	 * (مثلاً از لایک به دیسلایک)، واکنش جابه‌جا می‌شود.
	 *
	 * @param int    $post_id
	 * @param string $reaction 'like' یا 'dislike'
	 * @return array{success:bool, message:string, likes?:int, dislikes?:int, user_reaction?:string|null}
	 */
	public function submit_reaction( $post_id, $reaction ) {
		$post_id = absint( $post_id );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'پست مورد نظر یافت نشد.', 'star-rating' ),
			);
		}

		if ( ! in_array( $reaction, array( 'like', 'dislike' ), true ) ) {
			return array(
				'success' => false,
				'message' => __( 'نوع واکنش نامعتبر است.', 'star-rating' ),
			);
		}

		// بررسی اینکه آیا واکنش مهمان مجاز است؛ از همان تنظیم مربوط به
		// rating استفاده می‌کنیم تا یک سوییچ واحد در تنظیمات پلاگین باشد.
		$settings     = get_option( 'sr_settings', array() );
		$allow_guests = isset( $settings['allow_guest_voting'] ) && 'yes' === $settings['allow_guest_voting'];

		if ( ! is_user_logged_in() && ! $allow_guests ) {
			return array(
				'success' => false,
				'message' => __( 'برای ثبت واکنش باید وارد حساب کاربری خود شوید.', 'star-rating' ),
			);
		}

		$user_id     = is_user_logged_in() ? get_current_user_id() : null;
		$voter_token = $this->get_or_create_voter_token( $user_id );

		$existing = $this->repository->get_existing_reaction( $post_id, $voter_token );
		$user_reaction = $reaction;

		if ( $existing && $existing->reaction === $reaction ) {
			// همان دکمه دوباره زده شده: لغو واکنش (toggle off)
			$this->repository->delete_reaction( $existing->id );
			$user_reaction = null;
		} elseif ( $existing ) {
			// واکنش قبلی متفاوت بود: جابه‌جایی بین لایک/دیسلایک
			$this->repository->update_reaction( $existing->id, $reaction );
		} else {
			$this->repository->insert_reaction( $post_id, $reaction, $user_id, $voter_token );
		}

		$counts = $this->repository->get_post_counts( $post_id );

		return array(
			'success'       => true,
			'message'       => __( 'واکنش شما ثبت شد.', 'star-rating' ),
			'likes'         => $counts['likes'],
			'dislikes'      => $counts['dislikes'],
			'user_reaction' => $user_reaction,
		);
	}

	/**
	 * تولید یا بازیابی توکن یکتای رای‌دهنده (همان منطق SR_Rating_Handler،
	 * تا کوکی مشترک بین rating و reaction باشد و کاربر مهمان یک هویت واحد
	 * در کل پلاگین داشته باشد).
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

		$settings    = get_option( 'sr_settings', array() );
		$expiry_days = isset( $settings['cookie_expiry_days'] ) ? (int) $settings['cookie_expiry_days'] : 365;

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
	 * دریافت آمار واکنش‌های یک پست (برای رندر ویجت)
	 *
	 * @param int $post_id
	 * @return array{likes:int, dislikes:int}
	 */
	public function get_counts( $post_id ) {
		return $this->repository->get_post_counts( absint( $post_id ) );
	}

	/**
	 * واکنش فعلی رای‌دهنده برای یک پست ('like'، 'dislike' یا null)
	 *
	 * @param int $post_id
	 * @return string|null
	 */
	public function get_current_user_reaction( $post_id ) {
		$user_id     = is_user_logged_in() ? get_current_user_id() : null;
		$voter_token = $user_id
			? 'user_' . $user_id
			: ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ? 'guest_' . $_COOKIE[ self::COOKIE_NAME ] : null );

		if ( ! $voter_token ) {
			return null;
		}

		$reaction = $this->repository->get_existing_reaction( absint( $post_id ), $voter_token );

		return $reaction ? $reaction->reaction : null;
	}
}
