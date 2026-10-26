<?php
/**
 * ثبت هندلر AJAX امتیازدهی با استفاده از admin-ajax.php (به‌جای REST API).
 *
 * چرا admin-ajax.php به‌جای REST API؟
 * پلاگین‌های امنیتی رایج (iThemes Security، Disable REST API و مشابه) اغلب
 * کل REST API را برای کاربران مهمان می‌بندند (فیلتر rest_authentication_errors)،
 * چون REST API هدف رایج حملات brute-force (لیست کاربران و...) است. اما
 * admin-ajax.php هسته اصلی بسیاری از قابلیت‌های خود وردپرس (جستجوی زنده،
 * لایک، فرم‌های تماس و...) است و عملاً هیچ پلاگین امنیتی معمولی آن را برای
 * درخواست‌های anonymous نمی‌بندد. این یعنی راه‌حل مطمئن‌تر و پایدارتر است.
 *
 * Endpoint: POST /wp-admin/admin-ajax.php?action=sr_rate
 * Endpoint: GET  /wp-admin/admin-ajax.php?action=sr_stats&post_id=123
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SR_Ajax {

	public function register() {
		// nopriv یعنی کاربر مهمان (لاگین‌نکرده) هم بتواند این اکشن را صدا بزند.
		// بدون نسخه nopriv، درخواست کاربران مهمان اصلاً به تابع نمی‌رسد.
		add_action( 'wp_ajax_sr_rate', array( $this, 'handle_rate_request' ) );
		add_action( 'wp_ajax_nopriv_sr_rate', array( $this, 'handle_rate_request' ) );

		add_action( 'wp_ajax_sr_stats', array( $this, 'handle_stats_request' ) );
		add_action( 'wp_ajax_nopriv_sr_stats', array( $this, 'handle_stats_request' ) );
	}

	/**
	 * پردازش درخواست ثبت امتیاز
	 */
	public function handle_rate_request() {
		// بررسی nonce برای امنیت در برابر CSRF
		check_ajax_referer( 'sr_rating_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$rating  = isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : 0;

		$handler = new SR_Rating_Handler();
		$result  = $handler->submit_rating( $post_id, $rating );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * پردازش درخواست دریافت آمار یک پست
	 */
	public function handle_stats_request() {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

		$handler = new SR_Rating_Handler();
		$stats   = $handler->get_stats( $post_id );

		wp_send_json_success(
			array(
				'average' => $stats['average'],
				'count'   => $stats['count'],
			)
		);
	}
}
