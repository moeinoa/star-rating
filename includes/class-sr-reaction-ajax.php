<?php
/**
 * ثبت هندلر AJAX واکنش لایک/دیسلایک با استفاده از admin-ajax.php.
 * همان دلیل انتخاب admin-ajax.php (به‌جای REST API) که در class-sr-ajax.php
 * توضیح داده شده، اینجا هم صدق می‌کند.
 *
 * Endpoint: POST /wp-admin/admin-ajax.php?action=sr_react
 * Endpoint: GET  /wp-admin/admin-ajax.php?action=sr_reaction_counts&post_id=123
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SR_Reaction_Ajax {

	public function register() {
		add_action( 'wp_ajax_sr_react', array( $this, 'handle_reaction_request' ) );
		add_action( 'wp_ajax_nopriv_sr_react', array( $this, 'handle_reaction_request' ) );

		add_action( 'wp_ajax_sr_reaction_counts', array( $this, 'handle_counts_request' ) );
		add_action( 'wp_ajax_nopriv_sr_reaction_counts', array( $this, 'handle_counts_request' ) );
	}

	/**
	 * پردازش درخواست ثبت واکنش
	 */
	public function handle_reaction_request() {
		// نانس مجزا از rating تا این دو endpoint مستقل از هم باشند.
		check_ajax_referer( 'sr_reaction_nonce', 'nonce' );

		$post_id  = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$reaction = isset( $_POST['reaction'] ) ? sanitize_key( $_POST['reaction'] ) : '';

		$handler = new SR_Reaction_Handler();
		$result  = $handler->submit_reaction( $post_id, $reaction );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * پردازش درخواست دریافت شمارش لایک/دیسلایک یک پست
	 */
	public function handle_counts_request() {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

		$handler = new SR_Reaction_Handler();
		$counts  = $handler->get_counts( $post_id );

		wp_send_json_success( $counts );
	}
}
