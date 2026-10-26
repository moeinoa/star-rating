<?php
/**
 * لایه دسترسی به داده (Repository)
 * تمام کوئری‌های مربوط به جدول امتیازها اینجا قرار دارد تا کد سایر کلاس‌ها
 * از SQL مستقیم جدا بماند و نگهداری آسان‌تر شود.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SR_Repository {

	/** @var wpdb */
	private $wpdb;

	/** @var string */
	private $table;

	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'sr_post_ratings';
	}

	/**
	 * بررسی اینکه آیا این voter (کاربر یا مهمان) قبلاً به این پست رای داده است
	 *
	 * @param int    $post_id
	 * @param string $voter_token شناسه یکتای رای‌دهنده (کوکی یا user_id به صورت رشته)
	 * @return object|null رکورد رای قبلی یا null
	 */
	public function get_existing_vote( $post_id, $voter_token ) {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->table} WHERE post_id = %d AND voter_token = %s LIMIT 1",
			$post_id,
			$voter_token
		);

		return $this->wpdb->get_row( $sql );
	}

	/**
	 * ثبت امتیاز جدید
	 *
	 * @param int      $post_id
	 * @param int      $rating عدد ۱ تا ۵
	 * @param int|null $user_id شناسه کاربر لاگین‌کرده یا null برای مهمان
	 * @param string   $voter_token
	 * @return int|false شناسه رکورد جدید یا false در صورت خطا
	 */
	public function insert_rating( $post_id, $rating, $user_id, $voter_token ) {
		$result = $this->wpdb->insert(
			$this->table,
			array(
				'post_id'     => $post_id,
				'rating'      => $rating,
				'user_id'     => $user_id ? $user_id : null,
				'voter_token' => $voter_token,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s' )
		);

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * به‌روزرسانی امتیاز موجود (وقتی کاربر دوباره رای می‌دهد)
	 *
	 * @param int $vote_id
	 * @param int $rating
	 * @return bool
	 */
	public function update_rating( $vote_id, $rating ) {
		$result = $this->wpdb->update(
			$this->table,
			array(
				'rating'     => $rating,
				'created_at' => current_time( 'mysql' ),
			),
			array( 'id' => $vote_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * محاسبه میانگین و تعداد امتیازهای یک پست
	 *
	 * @param int $post_id
	 * @return array{average: float, count: int}
	 */
	public function get_post_stats( $post_id ) {
		$sql = $this->wpdb->prepare(
			"SELECT AVG(rating) as average, COUNT(*) as count FROM {$this->table} WHERE post_id = %d",
			$post_id
		);

		$row = $this->wpdb->get_row( $sql );

		return array(
			'average' => $row && $row->average ? round( (float) $row->average, 2 ) : 0,
			'count'   => $row ? (int) $row->count : 0,
		);
	}

	/**
	 * دریافت پرامتیازترین پست‌ها برای صفحه گزارش ادمین
	 *
	 * @param int $limit
	 * @return array
	 */
	public function get_top_rated_posts( $limit = 10 ) {
		$sql = $this->wpdb->prepare(
			"SELECT post_id, AVG(rating) as average, COUNT(*) as count
			 FROM {$this->table}
			 GROUP BY post_id
			 ORDER BY average DESC, count DESC
			 LIMIT %d",
			$limit
		);

		return $this->wpdb->get_results( $sql );
	}

	/**
	 * آمار کلی برای داشبورد گزارش
	 *
	 * @return array{total_votes:int, total_posts:int, overall_average:float}
	 */
	public function get_overall_stats() {
		$total_votes = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
		$total_posts = (int) $this->wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$this->table}" );
		$overall_avg = (float) $this->wpdb->get_var( "SELECT AVG(rating) FROM {$this->table}" );

		return array(
			'total_votes'     => $total_votes,
			'total_posts'     => $total_posts,
			'overall_average' => round( $overall_avg, 2 ),
		);
	}

	/**
	 * حذف یک رای مشخص با شناسه رکورد (برای استفاده مدیر در پنل ادمین)
	 *
	 * @param int $vote_id
	 * @return bool
	 */
	public function delete_vote( $vote_id ) {
		$result = $this->wpdb->delete(
			$this->table,
			array( 'id' => $vote_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * دریافت لیست تک‌تک رای‌های یک پست، برای صفحه جزئیات ادمین
	 * (مرتب‌شده از جدیدترین به قدیمی‌ترین)
	 *
	 * @param int $post_id
	 * @return array
	 */
	public function get_votes_for_post( $post_id ) {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->table} WHERE post_id = %d ORDER BY created_at DESC",
			$post_id
		);

		return $this->wpdb->get_results( $sql );
	}

	/**
	 * نام جدول (برای استفاده در uninstall یا موارد خاص)
	 *
	 * @return string
	 */
	public function get_table_name() {
		return $this->table;
	}
}
