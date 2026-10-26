/**
 * منطق فرانت‌اند ویجت امتیازدهی ستاره‌ای
 * بدون وابستگی به jQuery - Vanilla JS خالص
 */
( function () {
	'use strict';

	// وضعیت مشترک بین همه instance های ویجت برای یک post_id مشخص، تا وقتی
	// چند شورت‌کد [post_rating] با همان id در یک صفحه باشند (مثلاً یکی اول
	// متن، یکی انتهای متن)، رای‌دادن در یکی، همه‌ی نمونه‌های دیگر را هم
	// بلافاصله (بدون رفرش صفحه) sync کند.
	const postState = {}; // { [postId]: { vote, average, count } }

	function paintStars( widget, upTo, activeClass ) {
		widget.querySelectorAll( '.sr-star' ).forEach( function ( star ) {
			const value = parseInt( star.dataset.value, 10 );
			star.classList.toggle( activeClass, value <= upTo );
		} );
	}

	function resetToVote( widget, vote ) {
		widget.querySelectorAll( '.sr-star' ).forEach( function ( star ) {
			star.classList.remove( 'sr-star-hover', 'sr-star-hover-active' );
		} );
		paintStars( widget, vote, 'sr-star-active' );

		widget.querySelectorAll( '.sr-star' ).forEach( function ( star ) {
			const value = parseInt( star.dataset.value, 10 );
			star.setAttribute( 'aria-checked', value === vote ? 'true' : 'false' );
		} );
	}

	/**
	 * اعمال یک وضعیت جدید (رای، میانگین، تعداد) روی تمام instance های ویجت
	 * موجود در صفحه که به همان post_id تعلق دارند.
	 */
	function applyStateToAllWidgets( postId, state ) {
		postState[ postId ] = state;

		document.querySelectorAll( '.sr-rating-widget[data-post-id="' + postId + '"]' ).forEach( function ( widget ) {
			widget.dataset.userVote = state.vote;
			resetToVote( widget, state.vote );

			const average = widget.querySelector( '.sr-average' );
			const count   = widget.querySelector( '.sr-count' );

			if ( average ) {
				average.textContent = state.average;
			}
			if ( count ) {
				count.textContent = state.count;
			}
		} );
	}

	function initWidget( widget ) {
		const stars   = widget.querySelectorAll( '.sr-star' );
		const postId  = widget.dataset.postId;
		const message = widget.querySelector( '.sr-message' );

		// اگر یک instance دیگر از همین post_id قبلاً روی صفحه init شده و
		// وضعیتش (مثلاً بعد از یک رای موفق) در postState ذخیره شده، این
		// instance جدید هم باید فوراً همان وضعیت را بگیرد؛ در غیر این صورت
		// وضعیت اولیه‌اش را (که از PHP/HTML آمده) حفظ می‌کند.
		if ( postState[ postId ] ) {
			widget.dataset.userVote = postState[ postId ].vote;
		}

		let currentVote = parseInt( widget.dataset.userVote, 10 ) || 0;

		function resetToCurrentVote() {
			resetToVote( widget, currentVote );
		}

		stars.forEach( function ( star ) {
			star.addEventListener( 'mouseenter', function () {
				const value = parseInt( star.dataset.value, 10 );
				paintStars( widget, value, 'sr-star-hover-active' );
			} );

			star.addEventListener( 'mouseleave', function () {
				resetToCurrentVote();
			} );

			star.addEventListener( 'click', function () {
				submitVote( parseInt( star.dataset.value, 10 ) );
			} );

			star.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					submitVote( parseInt( star.dataset.value, 10 ) );
				}
			} );
		} );

		function submitVote( rating ) {
			// همه‌ی instance های همان پست را وارد حالت loading می‌کنیم تا در
			// حین درخواست، کلیک همزمان روی نسخه‌ی دیگر ویجت هم مسدود باشد.
			document.querySelectorAll( '.sr-rating-widget[data-post-id="' + postId + '"]' ).forEach( function ( w ) {
				w.classList.add( 'sr-loading' );
			} );
			message.textContent = '';
			message.className = 'sr-message';

			const body = new URLSearchParams();
			body.set( 'action', 'sr_rate' );
			body.set( 'nonce', srRatingData.nonce );
			body.set( 'post_id', postId );
			body.set( 'rating', rating );

			fetch( srRatingData.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded'
				},
				credentials: 'same-origin',
				body: body.toString()
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( result ) {
					document.querySelectorAll( '.sr-rating-widget[data-post-id="' + postId + '"]' ).forEach( function ( w ) {
						w.classList.remove( 'sr-loading' );
					} );

					if ( ! result.success ) {
						message.textContent = ( result.data && result.data.message ) || srRatingData.i18n.error;
						message.classList.add( 'sr-message-error' );
						return;
					}

					currentVote = rating;

					// sync کردن همه‌ی instance های دیگر همین post_id، نه فقط
					// همین ویجتی که کلیک خورده.
					applyStateToAllWidgets( postId, {
						vote:    rating,
						average: result.data.average,
						count:   result.data.count
					} );

					message.textContent = result.data.message;
					message.classList.add( 'sr-message-success' );
				} )
				.catch( function () {
					document.querySelectorAll( '.sr-rating-widget[data-post-id="' + postId + '"]' ).forEach( function ( w ) {
						w.classList.remove( 'sr-loading' );
					} );
					message.textContent = srRatingData.i18n.error;
					message.classList.add( 'sr-message-error' );
				} );
		}

		resetToCurrentVote();
	}

	function initAll() {
		document.querySelectorAll( '.sr-rating-widget' ).forEach( initWidget );
	}

	// اگر سند از قبل بارگذاری شده (چون این اسکریپت در انتهای صفحه/wp_footer
	// چاپ می‌شود و ممکن است رویداد DOMContentLoaded قبلاً fire شده باشد،
	// مثلاً وقتی از طریق المنتور یا سایر page builder ها رندر شده)،
	// بلافاصله اجرا می‌کنیم؛ در غیر این صورت منتظر رویداد می‌مانیم.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}
} )();
