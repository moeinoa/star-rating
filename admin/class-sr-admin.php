<?php
/**
 * پنل مدیریت پلاگین: صفحه تنظیمات + صفحه گزارش و آمار
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SR_Admin {

	public function run() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// AJAX عملیات مدیریتی (ویرایش/حذف رای و واکنش) - جدا از AJAX عمومی
		// front-end، همیشه current_user_can را داخل خودش چک می‌کند.
		$admin_ajax = new SR_Admin_Ajax();
		$admin_ajax->register();
	}

	/**
	 * ثبت منوی اصلی و زیرمنوها در پنل مدیریت
	 */
	public function register_menu() {
		add_menu_page(
			__( 'امتیازدهی ستاره‌ای', 'star-rating' ),
			__( 'امتیازدهی', 'star-rating' ),
			'manage_options',
			'star-rating',
			array( $this, 'render_reports_page' ),
			'dashicons-star-filled',
			30
		);

		add_submenu_page(
			'star-rating',
			__( 'گزارش و آمار', 'star-rating' ),
			__( 'گزارش و آمار', 'star-rating' ),
			'manage_options',
			'star-rating',
			array( $this, 'render_reports_page' )
		);

		add_submenu_page(
			'star-rating',
			__( 'تنظیمات', 'star-rating' ),
			__( 'تنظیمات', 'star-rating' ),
			'manage_options',
			'star-rating-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * ثبت تنظیمات با استفاده از Settings API وردپرس
	 */
	public function register_settings() {
		register_setting(
			'sr_settings_group',
			'sr_settings',
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * پاک‌سازی و اعتبارسنجی داده‌های تنظیمات قبل از ذخیره
	 *
	 * @param array $input
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$output = array();

		$output['allow_guest_voting'] = ( isset( $input['allow_guest_voting'] ) && 'yes' === $input['allow_guest_voting'] ) ? 'yes' : 'no';
		$output['star_color']        = isset( $input['star_color'] ) ? sanitize_hex_color( $input['star_color'] ) : '#f5a623';
		$output['cookie_expiry_days'] = isset( $input['cookie_expiry_days'] ) ? absint( $input['cookie_expiry_days'] ) : 365;

		// تنظیمات تزریق خودکار [post_rating] در فرم دیدگاه
		$mode = isset( $input['auto_inject']['mode'] ) && 'all_pages' === $input['auto_inject']['mode']
			? 'all_pages'
			: 'include';

		$output['auto_inject'] = array(
			'mode'         => $mode,
			'include_ids'  => $this->parse_id_list( isset( $input['auto_inject']['include_ids'] ) ? $input['auto_inject']['include_ids'] : '' ),
			'exclude_ids'  => $this->parse_id_list( isset( $input['auto_inject']['exclude_ids'] ) ? $input['auto_inject']['exclude_ids'] : '' ),
		);

		return $output;
	}

	/**
	 * تبدیل متن آزاد (ID ها جدا شده با کاما و/یا خط جدید) به آرایه‌ای از
	 * اعداد صحیح مثبت یکتا. ورودی‌های نامعتبر یا صفر نادیده گرفته می‌شوند.
	 *
	 * @param string $raw
	 * @return array<int, int>
	 */
	private function parse_id_list( $raw ) {
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return array();
		}

		$parts = preg_split( '/[\s,]+/', trim( $raw ) );
		$ids   = array();

		foreach ( $parts as $part ) {
			$id = absint( $part );
			if ( $id > 0 ) {
				$ids[ $id ] = $id;
			}
		}

		return array_values( $ids );
	}

	/**
	 * بارگذاری CSS/JS مربوط به پنل ادمین فقط در صفحات این پلاگین
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'star-rating' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'sr-admin-style',
			SR_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			SR_VERSION
		);

		// این اسکریپت فقط در صفحه جزئیات (که پارامتر sr_post_id دارد) واقعاً
		// کاربرد دارد، اما enqueue آن در کل صفحات پلاگین بی‌خطر و کم‌حجم است.
		wp_enqueue_script(
			'sr-admin-script',
			SR_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			SR_VERSION,
			true
		);

		wp_localize_script(
			'sr-admin-script',
			'srAdminData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'sr_admin_nonce' ),
				'i18n'    => array(
					'error'   => __( 'خطایی رخ داد. لطفاً دوباره تلاش کنید.', 'star-rating' ),
					'confirm' => __( 'آیا از حذف این رکورد مطمئن هستید؟ این عملیات قابل بازگشت نیست.', 'star-rating' ),
				),
			)
		);
	}

	/**
	 * رندر صفحه تنظیمات
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = get_option( 'sr_settings', array() );
		?>
		<div class="wrap sr-admin-wrap">
			<h1><?php esc_html_e( 'تنظیمات امتیازدهی ستاره‌ای', 'star-rating' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'sr_settings_group' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'اجازه رای به مهمان‌ها', 'star-rating' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="sr_settings[allow_guest_voting]" value="yes"
									<?php checked( isset( $settings['allow_guest_voting'] ) && 'yes' === $settings['allow_guest_voting'] ); ?> />
								<?php esc_html_e( 'کاربران مهمان (غیرلاگین) هم بتوانند امتیاز یا واکنش ثبت کنند', 'star-rating' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'این تنظیم روی هر دو ویجت (امتیاز ستاره‌ای و لایک/دیسلایک) اثر می‌گذارد.', 'star-rating' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="sr_star_color"><?php esc_html_e( 'رنگ اصلی ویجت‌ها', 'star-rating' ); ?></label>
						</th>
						<td>
							<input type="text" id="sr_star_color" name="sr_settings[star_color]"
								value="<?php echo esc_attr( isset( $settings['star_color'] ) ? $settings['star_color'] : '#f5a623' ); ?>"
								class="sr-color-picker" />
							<p class="description">
								<?php esc_html_e( 'این رنگ همزمان روی ستاره‌های امتیازدهی و آیکون‌های لایک/دیسلایک اعمال می‌شود.', 'star-rating' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="sr_cookie_expiry"><?php esc_html_e( 'مدت اعتبار کوکی مهمان (روز)', 'star-rating' ); ?></label>
						</th>
						<td>
							<input type="number" id="sr_cookie_expiry" name="sr_settings[cookie_expiry_days]"
								value="<?php echo esc_attr( isset( $settings['cookie_expiry_days'] ) ? $settings['cookie_expiry_days'] : 365 ); ?>"
								min="1" max="3650" class="small-text" />
							<p class="description">
								<?php esc_html_e( 'مدت زمانی که کوکی جلوگیری از رای/واکنش تکراری معتبر می‌ماند.', 'star-rating' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'نمایش خودکار امتیازدهی در فرم دیدگاه', 'star-rating' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'این بخش کاملاً مستقل از شورت‌کد دستی [post_rating] است؛ شورت‌کد دستی همچنان طبق روال قبل در هر جایی که قرار داده‌اید کار می‌کند. این تنظیم صرفاً همان ویجت را به‌طور خودکار داخل فرم دیدگاه (بین متن دیدگاه و دکمه ارسال) نیز نمایش می‌دهد.', 'star-rating' ); ?>
				</p>

				<?php
				$auto_inject  = isset( $settings['auto_inject'] ) ? $settings['auto_inject'] : array();
				$ai_mode      = isset( $auto_inject['mode'] ) ? $auto_inject['mode'] : 'include';
				$include_ids  = isset( $auto_inject['include_ids'] ) ? implode( ', ', $auto_inject['include_ids'] ) : '';
				$exclude_ids  = isset( $auto_inject['exclude_ids'] ) ? implode( ', ', $auto_inject['exclude_ids'] ) : '';
				?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'حالت انتخاب', 'star-rating' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="radio" class="sr-auto-inject-mode" name="sr_settings[auto_inject][mode]" value="include"
										<?php checked( 'include', $ai_mode ); ?> />
									<?php esc_html_e( 'فقط شناسه‌های مشخص‌شده', 'star-rating' ); ?>
								</label>
								<br />
								<label>
									<input type="radio" class="sr-auto-inject-mode" name="sr_settings[auto_inject][mode]" value="all_pages"
										<?php checked( 'all_pages', $ai_mode ); ?> />
									<?php esc_html_e( 'همه‌ی برگه‌ها (Pages)، به‌جز شناسه‌های استثنا', 'star-rating' ); ?>
								</label>
							</fieldset>
						</td>
					</tr>

					<tr class="sr-auto-inject-row" data-mode="include" <?php echo ( 'include' !== $ai_mode ) ? 'style="display:none;"' : ''; ?>>
						<th scope="row">
							<label for="sr_auto_inject_include"><?php esc_html_e( 'شناسه پست/برگه‌ها', 'star-rating' ); ?></label>
						</th>
						<td>
							<textarea id="sr_auto_inject_include" name="sr_settings[auto_inject][include_ids]" rows="3" class="large-text" placeholder="123, 456, 789"><?php echo esc_textarea( $include_ids ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'شناسه‌ها را با کاما یا خط جدید از هم جدا کنید. ویجت امتیازدهی فقط در فرم دیدگاه همین پست/برگه‌ها به‌طور خودکار نمایش داده می‌شود.', 'star-rating' ); ?>
							</p>
						</td>
					</tr>

					<tr class="sr-auto-inject-row" data-mode="all_pages" <?php echo ( 'all_pages' !== $ai_mode ) ? 'style="display:none;"' : ''; ?>>
						<th scope="row">
							<label for="sr_auto_inject_exclude"><?php esc_html_e( 'شناسه برگه‌های استثنا', 'star-rating' ); ?></label>
						</th>
						<td>
							<textarea id="sr_auto_inject_exclude" name="sr_settings[auto_inject][exclude_ids]" rows="3" class="large-text" placeholder="3245, 4444"><?php echo esc_textarea( $exclude_ids ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'ویجت روی همه‌ی برگه‌ها نمایش داده می‌شود، به‌جز شناسه‌هایی که اینجا وارد کنید. این لیست فقط شامل برگه (page) می‌شود، نه پست وبلاگ.', 'star-rating' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'شورت‌کدهای قابل استفاده', 'star-rating' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'هر شورت‌کد را در ویرایشگر پست/صفحه یا فایل قالب قرار دهید. با کلیک روی هرکدام، بلافاصله کپی می‌شود.', 'star-rating' ); ?>
			</p>

			<div class="sr-shortcode-cards">
				<div class="sr-shortcode-card">
					<div class="sr-shortcode-card-header">
						<span class="dashicons dashicons-star-filled"></span>
						<h3><?php esc_html_e( 'امتیازدهی ستاره‌ای', 'star-rating' ); ?></h3>
					</div>
					<p>
						<?php esc_html_e( 'مخصوص صفحات محصول/خدمت. این شورت‌کد schema.org (نوع Product با aggregateRating) نیز تولید می‌کند تا در نتایج گوگل ستاره نمایش داده شود.', 'star-rating' ); ?>
					</p>
					<button type="button" class="sr-shortcode-copy" data-shortcode="[post_rating]">
						<code>[post_rating]</code>
						<span class="dashicons dashicons-clipboard"></span>
					</button>
				</div>

				<div class="sr-shortcode-card">
					<div class="sr-shortcode-card-header">
						<span class="dashicons dashicons-thumbs-up"></span>
						<h3><?php esc_html_e( 'واکنش لایک / دیسلایک', 'star-rating' ); ?></h3>
					</div>
					<p>
						<?php esc_html_e( 'مخصوص پست‌های وبلاگ و مقالات. بدون تولید schema.org، چون گوگل برای لایک/دیسلایک rich result رسمی ندارد.', 'star-rating' ); ?>
					</p>
					<button type="button" class="sr-shortcode-copy" data-shortcode="[post_reaction]">
						<code>[post_reaction]</code>
						<span class="dashicons dashicons-clipboard"></span>
					</button>
				</div>
			</div>

			<p class="description">
				<?php esc_html_e( 'برای اعمال روی پست دیگری غیر از پست جاری، از attribute اختیاری id استفاده کنید، مثال:', 'star-rating' ); ?>
				<code>[post_rating id="123"]</code>
			</p>
		</div>
		<?php
	}

	/**
	 * رندر صفحه گزارش و آمار (یا صفحه جزئیات یک پست خاص، در صورت وجود
	 * پارامتر sr_post_id در URL)
	 */
	public function render_reports_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['sr_post_id'] ) ) {
			$this->render_post_details_page( absint( $_GET['sr_post_id'] ) );
			return;
		}

		$repository    = new SR_Repository();
		$overall_stats = $repository->get_overall_stats();
		$top_posts     = $repository->get_top_rated_posts( 10 );

		$reaction_repository    = new SR_Reaction_Repository();
		$reaction_overall_stats = $reaction_repository->get_overall_stats();
		$top_reacted_posts      = $reaction_repository->get_top_reacted_posts( 10 );
		?>
		<div class="wrap sr-admin-wrap">
			<h1><?php esc_html_e( 'گزارش و آمار امتیازدهی', 'star-rating' ); ?></h1>

			<h2><?php esc_html_e( 'امتیازدهی ستاره‌ای', 'star-rating' ); ?></h2>
			<div class="sr-stats-cards">
				<div class="sr-stat-card">
					<span class="sr-stat-number"><?php echo esc_html( $overall_stats['total_votes'] ); ?></span>
					<span class="sr-stat-label"><?php esc_html_e( 'کل امتیازها', 'star-rating' ); ?></span>
				</div>
				<div class="sr-stat-card">
					<span class="sr-stat-number"><?php echo esc_html( $overall_stats['total_posts'] ); ?></span>
					<span class="sr-stat-label"><?php esc_html_e( 'پست‌های امتیازدهی‌شده', 'star-rating' ); ?></span>
				</div>
				<div class="sr-stat-card">
					<span class="sr-stat-number"><?php echo esc_html( $overall_stats['overall_average'] ); ?></span>
					<span class="sr-stat-label"><?php esc_html_e( 'میانگین کلی', 'star-rating' ); ?></span>
				</div>
			</div>

			<h3><?php esc_html_e( 'پرامتیازترین پست‌ها', 'star-rating' ); ?></h3>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'عنوان پست', 'star-rating' ); ?></th>
						<th><?php esc_html_e( 'میانگین امتیاز', 'star-rating' ); ?></th>
						<th><?php esc_html_e( 'تعداد آرا', 'star-rating' ); ?></th>
						<th><?php esc_html_e( 'عملیات', 'star-rating' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $top_posts ) ) : ?>
						<tr>
							<td colspan="4"><?php esc_html_e( 'هنوز امتیازی ثبت نشده است.', 'star-rating' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $top_posts as $row ) : ?>
							<?php $post = get_post( $row->post_id ); ?>
							<?php if ( ! $post ) { continue; } ?>
							<tr>
								<td><?php echo esc_html( get_the_title( $post ) ); ?></td>
								<td><?php echo esc_html( round( (float) $row->average, 2 ) ); ?> / 5</td>
								<td><?php echo esc_html( $row->count ); ?></td>
								<td>
									<a href="<?php echo esc_url( add_query_arg( 'sr_post_id', $post->ID ) ); ?>">
										<?php esc_html_e( 'جزئیات', 'star-rating' ); ?>
									</a>
									|
									<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">
										<?php esc_html_e( 'ویرایش پست', 'star-rating' ); ?>
									</a>
									|
									<a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" target="_blank">
										<?php esc_html_e( 'مشاهده', 'star-rating' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<hr />

			<h2><?php esc_html_e( 'واکنش لایک / دیسلایک', 'star-rating' ); ?></h2>
			<div class="sr-stats-cards">
				<div class="sr-stat-card">
					<span class="sr-stat-number"><?php echo esc_html( $reaction_overall_stats['total_likes'] ); ?></span>
					<span class="sr-stat-label"><?php esc_html_e( 'کل لایک‌ها', 'star-rating' ); ?></span>
				</div>
				<div class="sr-stat-card">
					<span class="sr-stat-number"><?php echo esc_html( $reaction_overall_stats['total_dislikes'] ); ?></span>
					<span class="sr-stat-label"><?php esc_html_e( 'کل دیسلایک‌ها', 'star-rating' ); ?></span>
				</div>
				<div class="sr-stat-card">
					<span class="sr-stat-number"><?php echo esc_html( $reaction_overall_stats['total_posts'] ); ?></span>
					<span class="sr-stat-label"><?php esc_html_e( 'پست‌های واکنش‌خورده', 'star-rating' ); ?></span>
				</div>
			</div>

			<h3><?php esc_html_e( 'پرتعامل‌ترین پست‌ها', 'star-rating' ); ?></h3>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'عنوان پست', 'star-rating' ); ?></th>
						<th><?php esc_html_e( 'لایک', 'star-rating' ); ?></th>
						<th><?php esc_html_e( 'دیسلایک', 'star-rating' ); ?></th>
						<th><?php esc_html_e( 'عملیات', 'star-rating' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $top_reacted_posts ) ) : ?>
						<tr>
							<td colspan="4"><?php esc_html_e( 'هنوز واکنشی ثبت نشده است.', 'star-rating' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $top_reacted_posts as $row ) : ?>
							<?php $post = get_post( $row->post_id ); ?>
							<?php if ( ! $post ) { continue; } ?>
							<tr>
								<td><?php echo esc_html( get_the_title( $post ) ); ?></td>
								<td><?php echo esc_html( $row->likes ); ?></td>
								<td><?php echo esc_html( $row->dislikes ); ?></td>
								<td>
									<a href="<?php echo esc_url( add_query_arg( 'sr_post_id', $post->ID ) ); ?>">
										<?php esc_html_e( 'جزئیات', 'star-rating' ); ?>
									</a>
									|
									<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">
										<?php esc_html_e( 'ویرایش پست', 'star-rating' ); ?>
									</a>
									|
									<a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" target="_blank">
										<?php esc_html_e( 'مشاهده', 'star-rating' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * رندر صفحه جزئیات یک پست: لیست تک‌تک رای‌ها و واکنش‌های آن پست، با
	 * امکان ویرایش مقدار یا حذف هر رکورد توسط مدیر کل سایت.
	 *
	 * @param int $post_id
	 */
	private function render_post_details_page( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			echo '<div class="wrap sr-admin-wrap"><p>' . esc_html__( 'پست مورد نظر یافت نشد.', 'star-rating' ) . '</p></div>';
			return;
		}

		$rating_repository   = new SR_Repository();
		$votes                = $rating_repository->get_votes_for_post( $post_id );

		$reaction_repository  = new SR_Reaction_Repository();
		$reactions            = $reaction_repository->get_reactions_for_post( $post_id );

		$back_url = remove_query_arg( 'sr_post_id' );
		?>
		<div class="wrap sr-admin-wrap">
			<h1>
				<?php
				printf(
					/* translators: %s: عنوان پست */
					esc_html__( 'جزئیات امتیازها و واکنش‌های: %s', 'star-rating' ),
					'<em>' . esc_html( get_the_title( $post ) ) . '</em>'
				);
				?>
			</h1>
			<p>
				<a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'بازگشت به گزارش کلی', 'star-rating' ); ?></a>
			</p>

			<h2><?php esc_html_e( 'امتیازهای ثبت‌شده (ستاره‌ای)', 'star-rating' ); ?></h2>
			<table class="wp-list-table widefat fixed striped sr-admin-detail-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'رای‌دهنده', 'star-rating' ); ?></th>
						<th><?php esc_html_e( 'امتیاز فعلی', 'star-rating' ); ?></th>
						<th><?php esc_html_e( 'تاریخ ثبت', 'star-rating' ); ?></th>
						<th><?php esc_html_e( 'عملیات', 'star-rating' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $votes ) ) : ?>
						<tr>
							<td colspan="4"><?php esc_html_e( 'هنوز امتیازی برای این پست ثبت نشده است.', 'star-rating' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $votes as $vote ) : ?>
							<tr data-vote-id="<?php echo esc_attr( $vote->id ); ?>" class="sr-vote-row">
								<td><?php echo esc_html( $this->describe_voter( $vote->user_id, $vote->voter_token ) ); ?></td>
								<td>
									<select class="sr-vote-select">
										<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
											<option value="<?php echo esc_attr( $i ); ?>" <?php selected( (int) $vote->rating, $i ); ?>>
												<?php echo esc_html( $i ); ?>
											</option>
										<?php endfor; ?>
									</select>
								</td>
								<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $vote->created_at ) ); ?></td>
								<td>
									<button type="button" class="button sr-admin-save-vote"><?php esc_html_e( 'ذخیره', 'star-rating' ); ?></button>
									<button type="button" class="button sr-admin-delete-vote"><?php esc_html_e( 'حذف', 'star-rating' ); ?></button>
									<span class="sr-admin-row-message"></span>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'واکنش‌های ثبت‌شده (لایک/دیسلایک)', 'star-rating' ); ?></h2>
			<table class="wp-list-table widefat fixed striped sr-admin-detail-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'رای‌دهنده', 'star-rating' ); ?></th>
						<th><?php esc_html_e( 'واکنش فعلی', 'star-rating' ); ?></th>
						<th><?php esc_html_e( 'تاریخ ثبت', 'star-rating' ); ?></th>
						<th><?php esc_html_e( 'عملیات', 'star-rating' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $reactions ) ) : ?>
						<tr>
							<td colspan="4"><?php esc_html_e( 'هنوز واکنشی برای این پست ثبت نشده است.', 'star-rating' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $reactions as $reaction ) : ?>
							<tr data-reaction-id="<?php echo esc_attr( $reaction->id ); ?>" class="sr-reaction-row">
								<td><?php echo esc_html( $this->describe_voter( $reaction->user_id, $reaction->voter_token ) ); ?></td>
								<td>
									<select class="sr-reaction-select">
										<option value="like" <?php selected( $reaction->reaction, 'like' ); ?>><?php esc_html_e( 'لایک', 'star-rating' ); ?></option>
										<option value="dislike" <?php selected( $reaction->reaction, 'dislike' ); ?>><?php esc_html_e( 'دیسلایک', 'star-rating' ); ?></option>
									</select>
								</td>
								<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $reaction->created_at ) ); ?></td>
								<td>
									<button type="button" class="button sr-admin-save-reaction"><?php esc_html_e( 'ذخیره', 'star-rating' ); ?></button>
									<button type="button" class="button sr-admin-delete-reaction"><?php esc_html_e( 'حذف', 'star-rating' ); ?></button>
									<span class="sr-admin-row-message"></span>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * توصیف انسانی رای‌دهنده برای نمایش در جدول ادمین: نام کاربر لاگین یا
	 * برچسب «مهمان» به همراه بخشی از voter_token برای تمایز مهمان‌های مختلف.
	 *
	 * @param int|null $user_id
	 * @param string   $voter_token
	 * @return string
	 */
	private function describe_voter( $user_id, $voter_token ) {
		if ( $user_id ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				return $user->display_name . ' (' . $user->user_login . ')';
			}
			return sprintf(
				/* translators: %d: شناسه کاربر */
				__( 'کاربر حذف‌شده (#%d)', 'star-rating' ),
				$user_id
			);
		}

		$short_token = substr( (string) $voter_token, -8 );
		return sprintf(
			/* translators: %s: بخشی از شناسه کوکی مهمان */
			__( 'مهمان (%s)', 'star-rating' ),
			$short_token
		);
	}
}
