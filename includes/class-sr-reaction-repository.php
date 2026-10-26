<?php
/**
 * لایه دسترسی به داده برای سیستم لایک/دیسلایک پست‌ها (مجزا از جدول امتیازدهی
 * ستاره‌ای، چون این دو مفهوماً و ساختاراً متفاوتند: rating عدد ۱ تا ۵ است،
 * reaction فقط دو حالت like/dislike دارد).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SR_Reaction_Repository {

	/** @var wpdb */
	private $wpdb;

	/** @var string */
	private $table;

	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'sr_post_reactions';
	}

	/**
	 * بررسی اینکه آیا این voter (کاربر یا مهمان) قبلاً برای این پست واکنشی
	 * ثبت کرده است.
	 *
	 * @param int    $post_id
	 * @param string $voter_token
	 * @return object|null
	 */
	public function get_existing_reaction( $post_id, $voter_token ) {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->table} WHERE post_id = %d AND voter_token = %s LIMIT 1",
			$post_id,
			$voter_token
		);

		return $this->wpdb->get_row( $sql );
	}

	/**
	 * ثبت واکنش جدید
	 *
	 * @param int      $post_id
	 * @param string   $reaction 'like' یا 'dislike'
	 * @param int|null $user_id
	 * @param string   $voter_token
	 * @return int|false شناسه رکورد جدید یا false در صورت خطا
	 */
	public function insert_reaction( $post_id, $reaction, $user_id, $voter_token ) {
		$result = $this->wpdb->insert(
			$this->table,
			array(
				'post_id'     => $post_id,
				'reaction'    => $reaction,
				'user_id'     => $user_id ? $user_id : null,
				'voter_token' => $voter_token,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s' )
		);

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * به‌روزرسانی واکنش موجود (وقتی کاربر نظرش را عوض می‌کند: لایک <-> دیسلایک)
	 *
	 * @param int    $reaction_id
	 * @param string $reaction
	 * @return bool
	 */
	public function update_reaction( $reaction_id, $reaction ) {
		$result = $this->wpdb->update(
			$this->table,
			array(
				'reaction'   => $reaction,
				'created_at' => current_time( 'mysql' ),
			),
			array( 'id' => $reaction_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * حذف واکنش موجود (وقتی کاربر روی همان دکمه‌ای که قبلاً زده دوباره کلیک
	 * می‌کند، یعنی می‌خواهد واکنشش را لغو کند - toggle off)
	 *
	 * @param int $reaction_id
	 * @return bool
	 */
	public function delete_reaction( $reaction_id ) {
		$result = $this->wpdb->delete(
			$this->table,
			array( 'id' => $reaction_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * شمارش لایک و دیسلایک یک پست
	 *
	 * @param int $post_id
	 * @return array{likes:int, dislikes:int}
	 */
	public function get_post_counts( $post_id ) {
		$sql = $this->wpdb->prepare(
			"SELECT reaction, COUNT(*) as cnt FROM {$this->table} WHERE post_id = %d GROUP BY reaction",
			$post_id
		);

		$rows = $this->wpdb->get_results( $sql );

		$counts = array(
			'likes'    => 0,
			'dislikes' => 0,
		);

		foreach ( $rows as $row ) {
			if ( 'like' === $row->reaction ) {
				$counts['likes'] = (int) $row->cnt;
			} elseif ( 'dislike' === $row->reaction ) {
				$counts['dislikes'] = (int) $row->cnt;
			}
		}

		return $counts;
	}

	/**
	 * پرتعامل‌ترین پست‌ها (بیشترین مجموع لایک+دیسلایک) برای صفحه گزارش ادمین
	 *
	 * @param int $limit
	 * @return array
	 */
	public function get_top_reacted_posts( $limit = 10 ) {
		$sql = $this->wpdb->prepare(
			"SELECT
				post_id,
				SUM(CASE WHEN reaction = 'like' THEN 1 ELSE 0 END) as likes,
				SUM(CASE WHEN reaction = 'dislike' THEN 1 ELSE 0 END) as dislikes,
				COUNT(*) as total
			 FROM {$this->table}
			 GROUP BY post_id
			 ORDER BY total DESC
			 LIMIT %d",
			$limit
		);

		return $this->wpdb->get_results( $sql );
	}

	/**
	 * آمار کلی برای داشبورد گزارش
	 *
	 * @return array{total_likes:int, total_dislikes:int, total_posts:int}
	 */
	public function get_overall_stats() {
		$total_likes    = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} WHERE reaction = 'like'" );
		$total_dislikes = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} WHERE reaction = 'dislike'" );
		$total_posts    = (int) $this->wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$this->table}" );

		return array(
			'total_likes'    => $total_likes,
			'total_dislikes' => $total_dislikes,
			'total_posts'    => $total_posts,
		);
	}

	/**
	 * دریافت لیست تک‌تک واکنش‌های یک پست، برای صفحه جزئیات ادمین
	 * (مرتب‌شده از جدیدترین به قدیمی‌ترین)
	 *
	 * @param int $post_id
	 * @return array
	 */
	public function get_reactions_for_post( $post_id ) {
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
