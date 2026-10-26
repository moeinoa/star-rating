<?php
/**
 * شورت‌کد [post_reaction] برای نمایش ویجت لایک/دیسلایک پست‌های وبلاگ.
 *
 * توجه مهم: برخلاف [post_rating]، این ویجت اصلاً schema.org تولید نمی‌کند.
 * طبق مستندات رسمی گوگل برای Review Snippet، لایک/دیسلایک هیچ نوع rich
 * result رسمی ندارد و AggregateRating فقط برای امتیازدهی عددی (ستاره‌ای)
 * تعریف شده، پس این کلاس عمداً هیچ هوکی به SR_Schema وصل نمی‌کند.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SR_Reaction_Shortcode {

	/** @var SR_Reaction_Handler */
	private $handler;

	public function __construct() {
		$this->handler = new SR_Reaction_Handler();
	}

	public function register() {
		add_shortcode( 'post_reaction', array( $this, 'render_shortcode' ) );
	}

	/**
	 * رندر شورت‌کد: [post_reaction id="123"]
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
			'post_reaction'
		);

		return $this->render_widget( absint( $atts['id'] ) );
	}

	/**
	 * تولید HTML ویجت لایک/دیسلایک برای یک پست مشخص
	 *
	 * @param int $post_id
	 * @return string
	 */
	private function render_widget( $post_id ) {
		if ( ! $post_id ) {
			return '';
		}

		$counts        = $this->handler->get_counts( $post_id );
		$user_reaction = $this->handler->get_current_user_reaction( $post_id );

		// از همان تنظیم رنگ ستاره‌ها (sr_star_color) استفاده می‌کنیم تا کاربر
		// فقط یک فیلد رنگ در تنظیمات پلاگین ببیند که همزمان روی هر دو ویجت
		// (ستاره‌ای و لایک/دیسلایک) اثر می‌گذارد.
		$settings         = get_option( 'sr_settings', array() );
		$reaction_color   = isset( $settings['star_color'] ) ? sanitize_hex_color( $settings['star_color'] ) : '#f5a623';

		ob_start();
		?>
		<div class="sr-reaction-widget"
			data-post-id="<?php echo esc_attr( $post_id ); ?>"
			data-user-reaction="<?php echo esc_attr( $user_reaction ? $user_reaction : '' ); ?>"
			style="--sr-reaction-color: <?php echo esc_attr( $reaction_color ); ?>;">

			<button type="button"
				class="sr-reaction-btn sr-reaction-like <?php echo ( 'like' === $user_reaction ) ? 'sr-reaction-active' : ''; ?>"
				data-reaction="like"
				aria-pressed="<?php echo ( 'like' === $user_reaction ) ? 'true' : 'false'; ?>">
				<span class="sr-reaction-icon sr-reaction-icon-outline"><?php echo SR_Reaction_Icons::like_outline(); ?></span>
				<span class="sr-reaction-icon sr-reaction-icon-bold"><?php echo SR_Reaction_Icons::like_bold(); ?></span>
				<span class="sr-reaction-count sr-reaction-like-count"><?php echo esc_html( $counts['likes'] ); ?></span>
			</button>

			<button type="button"
				class="sr-reaction-btn sr-reaction-dislike <?php echo ( 'dislike' === $user_reaction ) ? 'sr-reaction-active' : ''; ?>"
				data-reaction="dislike"
				aria-pressed="<?php echo ( 'dislike' === $user_reaction ) ? 'true' : 'false'; ?>">
				<span class="sr-reaction-icon sr-reaction-icon-outline"><?php echo SR_Reaction_Icons::dislike_outline(); ?></span>
				<span class="sr-reaction-icon sr-reaction-icon-bold"><?php echo SR_Reaction_Icons::dislike_bold(); ?></span>
				<span class="sr-reaction-count sr-reaction-dislike-count"><?php echo esc_html( $counts['dislikes'] ); ?></span>
			</button>

			<div class="sr-reaction-message" style="display:none;" aria-live="polite"></div>
		</div>
		<?php
		return ob_get_clean();
	}
}
