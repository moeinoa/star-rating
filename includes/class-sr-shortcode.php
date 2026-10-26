<?php
/**
 * شورت‌کد [post_rating] برای نمایش ویجت ستاره‌ای امتیازدهی
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SR_Shortcode {

	/** @var SR_Rating_Handler */
	private $handler;

	/**
	 * شناسه(های) پستی که ویجت امتیازدهی برای آن‌ها در طول این درخواست واقعاً
	 * رندر شده است. برخلاف تشخیص از روی is_singular('post') که فقط پست‌های
	 * وبلاگ استاندارد را پوشش می‌دهد، این آرایه مستقل از نوع محتواست: صفحه،
	 * محصول ووکامرس، هر CPT دلخواه، یا حتی چند شورت‌کد با id های مختلف در
	 * یک صفحه. برای تصمیم‌گیری درباره enqueue asset و همچنین تولید Schema
	 * استفاده می‌شود.
	 *
	 * @var array<int, true>
	 */
	private static $rendered_post_ids = array();

	public function __construct() {
		$this->handler = new SR_Rating_Handler();
	}

	/**
	 * آیا در این درخواست (request) ویجت امتیازدهی حداقل یک‌بار رندر شده است؟
	 *
	 * @return bool
	 */
	public static function was_widget_rendered() {
		return ! empty( self::$rendered_post_ids );
	}

	/**
	 * آیا ویجت امتیازدهی برای یک post_id مشخص در این درخواست رندر شده است؟
	 * برای Schema استفاده می‌شود تا مستقل از نوع صفحه (پست/صفحه/CPT) کار کند.
	 *
	 * @param int $post_id
	 * @return bool
	 */
	public static function was_rendered_for_post( $post_id ) {
		return isset( self::$rendered_post_ids[ absint( $post_id ) ] );
	}

	public function register() {
		add_shortcode( 'post_rating', array( $this, 'render_shortcode' ) );
	}

	/**
	 * رندر شورت‌کد: [post_rating id="123"]
	 *
	 * @param array $atts
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => get_the_ID(),
			),
			$atts,
			'post_rating'
		);

		return $this->render_widget( absint( $atts['id'] ) );
	}

	/**
	 * تولید HTML ویجت ستاره‌ای برای یک پست مشخص
	 *
	 * @param int $post_id
	 * @return string
	 */
	private function render_widget( $post_id ) {
		if ( ! $post_id ) {
			return '';
		}

		self::$rendered_post_ids[ $post_id ] = true;

		$stats       = $this->handler->get_stats( $post_id );
		$user_vote   = $this->handler->get_current_user_vote( $post_id );
		$settings    = get_option( 'sr_settings', array() );
		$star_color  = isset( $settings['star_color'] ) ? sanitize_hex_color( $settings['star_color'] ) : '#f5a623';

		ob_start();
		?>
		<div class="sr-rating-widget"
			data-post-id="<?php echo esc_attr( $post_id ); ?>"
			data-user-vote="<?php echo esc_attr( $user_vote ? $user_vote : 0 ); ?>"
			style="--sr-star-color: <?php echo esc_attr( $star_color ); ?>;">

			<div class="sr-stars" role="radiogroup" aria-label="<?php esc_attr_e( 'امتیاز دهید', 'star-rating' ); ?>">
				<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
					<span
						class="sr-star <?php echo ( $user_vote && $i <= $user_vote ) ? 'sr-star-active' : ''; ?>"
						data-value="<?php echo esc_attr( $i ); ?>"
						role="radio"
						aria-checked="<?php echo ( $user_vote == $i ) ? 'true' : 'false'; ?>"
						tabindex="0">★</span>
				<?php endfor; ?>
			</div>

			<div class="sr-rating-info">
				<span class="sr-average" style="display:none;"><?php echo esc_html( $stats['average'] ); ?></span>
				<span class="sr-count"><?php echo esc_html( $stats['count'] ); ?></span>
				<?php esc_html_e( 'رای', 'star-rating' ); ?>
			</div>

			<div class="sr-message" style="display:none;" aria-live="polite"></div>
		</div>
		<?php
		return ob_get_clean();
	}
}
