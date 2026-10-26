<?php
/**
 * تزریق Schema.org AggregateRating بر اساس خروجی واقعی HTML صفحه.
 *
 * چرا این‌طور؟ چون ویجت امتیازدهی ممکن است از منابع محتوایی کاملاً متفاوتی
 * بیاید: post_content کلاسیک/گوتنبرگ، _elementor_data خودِ پست، یک تمپلیت
 * Theme Builder المنتور (Single/Archive/Loop Item) که اصلاً به post_id
 * پست جاری متصل نیست، یک ویجت شورت‌کد در بریکز/دیوی/هر page builder دیگر،
 * یا حتی HTML دستی در فایل قالب. اسکن کردن post_content یا _elementor_data
 * فقطِ خودِ پست، تمام این حالت‌ها را پوشش نمی‌دهد (مثلاً وقتی شورت‌کد در
 * تمپلیت Theme Builder با post_id متفاوت ذخیره شده).
 *
 * راه‌حل: به‌جای حدس زدن این‌که شورت‌کد "کجای دیتابیس" ذخیره شده، کل خروجی
 * HTML نهایی صفحه (بعد از رندر کامل body، از طریق ob_start سراسری روی
 * template_redirect) را می‌گیریم و دنبال کلاس واقعی رندرشده‌ی ویجت
 * (".sr-rating-widget") می‌گردیم. این روش کاملاً مستقل از منبع محتواست: اگر
 * ویجت در HTML نهایی چاپ شده باشد، هر جا و به هر شکلی رندر شده باشد، تشخیص
 * داده می‌شود.
 *
 * محل درج JSON-LD: طبق تایید جان مولر (گوگل)، داده‌های ساختاریافته چه در
 * <head> و چه در <body> باشند به یک اندازه توسط گوگل پذیرفته می‌شوند. پس
 * schema این ویجت را مستقل از گراف Yoast، به‌صورت یک <script type="application/ld+json">
 * جداگانه، درست پیش از </body> درج می‌کنیم؛ این کار از پیچیدگی هماهنگی با
 * فیلتر eager مربوط به Yoast (`wpseo_schema_graph`، که در <head> و پیش از
 * رندر body اجرا می‌شود) بی‌نیاز می‌کند.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SR_Schema {

	/** @var SR_Rating_Handler */
	private $handler;

	/**
	 * کلاس CSS واقعی که ویجت رندرشده در HTML خروجی می‌گیرد
	 * (نگاه کنید به SR_Shortcode::render_widget()).
	 */
	const WIDGET_MARKER_CLASS = 'sr-rating-widget';

	public function __construct() {
		$this->handler = new SR_Rating_Handler();
	}

	public function register() {
		// فقط در front-end و درخواست‌های معمولی صفحه (نه REST، نه AJAX، نه feed).
		if ( is_admin() || wp_doing_ajax() || defined( 'REST_REQUEST' ) ) {
			return;
		}

		add_action( 'template_redirect', array( $this, 'start_output_buffer' ), 0 );
	}

	/**
	 * شروع بافر خروجی سراسری صفحه. این کل HTML نهایی (head + body) را
	 * می‌گیرد تا بتوانیم بعد از رندر کامل، وجود ویجت را تشخیص دهیم و در
	 * صورت لزوم، schema را درست پیش از </body> اضافه کنیم.
	 */
	public function start_output_buffer() {
		// صفحات غیر-HTML (مثل sitemap.xml، robots.txt) را دست نمی‌زنیم.
		if ( is_feed() || is_robots() || is_trackback() ) {
			return;
		}

		ob_start( array( $this, 'inject_schema_into_output' ) );
	}

	/**
	 * کال‌بک بافر خروجی: در HTML نهایی صفحه دنبال ویجت امتیازدهی می‌گردد
	 * و در صورت وجود، AggregateRating JSON-LD را قبل از </body> درج می‌کند.
	 *
	 * @param string $html خروجی کامل و نهایی صفحه.
	 * @return string
	 */
	public function inject_schema_into_output( $html ) {
		if ( '' === trim( (string) $html ) ) {
			return $html;
		}

		$post_ids = $this->extract_rendered_widget_post_ids( $html );

		if ( empty( $post_ids ) ) {
			return $html;
		}

		$schema_scripts = '';

		foreach ( $post_ids as $post_id ) {
			$json = $this->build_schema_json( $post_id );

			if ( $json ) {
				$schema_scripts .= '<script type="application/ld+json">' . $json . '</script>';
			}
		}

		if ( '' === $schema_scripts ) {
			return $html;
		}

		// درج درست پیش از </body>. اگر به هر دلیلی </body> در خروجی نبود
		// (مثلاً AMP یا قالب سفارشی)، schema را به انتهای خروجی اضافه می‌کنیم
		// تا در بدترین حالت هم از دست نرود.
		if ( false !== stripos( $html, '</body>' ) ) {
			return preg_replace( '/<\/body>/i', $schema_scripts . '</body>', $html, 1 );
		}

		return $html . $schema_scripts;
	}

	/**
	 * استخراج شناسه‌ی تمام پست‌هایی که ویجت امتیازدهی برایشان در این HTML
	 * واقعاً رندر شده است (data-post-id روی هر ".sr-rating-widget").
	 * ممکن است در یک صفحه چند ویجت با post_id های متفاوت باشد (مثلاً در
	 * Loop Grid یا صفحات آرشیو)، بنابراین آرایه برمی‌گردانیم نه یک عدد.
	 *
	 * @param string $html
	 * @return int[]
	 */
	private function extract_rendered_widget_post_ids( $html ) {
		if ( false === stripos( $html, self::WIDGET_MARKER_CLASS ) ) {
			return array();
		}

		$matches = array();
		$pattern = '/class="[^"]*\bsr-rating-widget\b[^"]*"[^>]*data-post-id="(\d+)"/i';

		preg_match_all( $pattern, $html, $matches );

		if ( empty( $matches[1] ) ) {
			// ترتیب attribute ها ممکن است برعکس باشد (data-post-id قبل از class).
			$pattern = '/data-post-id="(\d+)"[^>]*class="[^"]*\bsr-rating-widget\b[^"]*"/i';
			preg_match_all( $pattern, $html, $matches );
		}

		if ( empty( $matches[1] ) ) {
			return array();
		}

		return array_unique( array_map( 'absint', $matches[1] ) );
	}

	/**
	 * ساخت رشته JSON برای schema.org نوع Product، با aggregateRating تودرتو.
	 *
	 * ساختار قبلی (AggregateRating مستقل در ریشه با itemReviewed تودرتو) طبق
	 * بازخورد و نمونه‌ی تایید‌شده جایگزین شد با ساختار استاندارد Product که
	 * گوگل برای Merchant/Product rich result انتظار دارد: خودِ Product در
	 * ریشه‌ی JSON-LD قرار می‌گیرد و aggregateRating یکی از property های آن است.
	 *
	 * اگر هنوز رایی برای آن پست ثبت نشده باشد (ratingCount=0 که طبق راهنمای
	 * گوگل مجاز/معتبر نیست)، رشته خالی برمی‌گرداند.
	 *
	 * @param int $post_id
	 * @return string
	 */
	private function build_schema_json( $post_id ) {
		if ( ! $post_id ) {
			return '';
		}

		$stats = $this->handler->get_stats( $post_id );

		if ( empty( $stats['count'] ) || $stats['count'] < 1 ) {
			return '';
		}

		$post = get_post( $post_id );

		if ( ! $post || 'publish' !== $post->post_status ) {
			return '';
		}

		$schema = array(
			'@context' => 'https://schema.org/',
			'@type'    => 'Product',
			'name'     => get_the_title( $post_id ),
		);

		// تصویر شاخص پست به عنوان image محصول. اگر پست تصویر شاخص نداشته
		// باشد، فیلد image کلاً از JSON حذف می‌شود؛ چاپ رشته خالی برای این
		// فیلد توسط گوگل نامعتبر تلقی می‌شود و بهتر از نبودِ کامل فیلد نیست.
		$image_url = get_the_post_thumbnail_url( $post_id, 'full' );

		if ( $image_url ) {
			$schema['image'] = $image_url;
		}

		$schema['aggregateRating'] = array(
			'@type'       => 'AggregateRating',
			'ratingValue' => $stats['average'],
			'bestRating'  => 5,
			'worstRating' => 1,
			'ratingCount' => $stats['count'],
		);

		return wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}
}
