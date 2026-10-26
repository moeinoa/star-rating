/**
 * منطق فرانت‌اند ویجت واکنش لایک/دیسلایک
 * بدون وابستگی به jQuery - Vanilla JS خالص (همان الگوی star-rating.js)
 */
( function () {
	'use strict';

	// وضعیت مشترک بین همه instance های ویجت reaction برای یک post_id مشخص،
	// دقیقاً به همان دلیل postState در star-rating.js: چند شورت‌کد
	// [post_reaction] با همان id در یک صفحه باید بعد از واکنش، همزمان sync شوند.
	const postState = {}; // { [postId]: { likes, dislikes, userReaction } }

	function paintWidgetState( widget, state ) {
		const likeBtn      = widget.querySelector( '.sr-reaction-like' );
		const dislikeBtn   = widget.querySelector( '.sr-reaction-dislike' );
		const likeCount    = widget.querySelector( '.sr-reaction-like-count' );
		const dislikeCount = widget.querySelector( '.sr-reaction-dislike-count' );

		widget.dataset.userReaction = state.userReaction || '';

		likeBtn.classList.toggle( 'sr-reaction-active', state.userReaction === 'like' );
		likeBtn.setAttribute( 'aria-pressed', state.userReaction === 'like' ? 'true' : 'false' );

		dislikeBtn.classList.toggle( 'sr-reaction-active', state.userReaction === 'dislike' );
		dislikeBtn.setAttribute( 'aria-pressed', state.userReaction === 'dislike' ? 'true' : 'false' );

		likeCount.textContent = state.likes;
		dislikeCount.textContent = state.dislikes;
	}

	/**
	 * اعمال یک وضعیت جدید روی تمام instance های ویجت موجود در صفحه که به
	 * همان post_id تعلق دارند (نه فقط ویجتی که کلیک خورده).
	 */
	function applyStateToAllWidgets( postId, state ) {
		postState[ postId ] = state;

		document.querySelectorAll( '.sr-reaction-widget[data-post-id="' + postId + '"]' ).forEach( function ( widget ) {
			paintWidgetState( widget, state );
		} );
	}

	function initWidget( widget ) {
		const postId     = widget.dataset.postId;
		const message    = widget.querySelector( '.sr-reaction-message' );
		const likeBtn    = widget.querySelector( '.sr-reaction-like' );
		const dislikeBtn = widget.querySelector( '.sr-reaction-dislike' );

		// اگر یک instance دیگر از همین post_id قبلاً init شده و وضعیتش در
		// postState ذخیره شده، این instance جدید هم فوراً همان وضعیت را
		// بگیرد (مثلاً وقتی ویجت دوم بعد از کلیک روی ویجت اول render می‌شود،
		// نه اینکه از ابتدا در صفحه بوده باشد).
		if ( postState[ postId ] ) {
			paintWidgetState( widget, postState[ postId ] );
		}

		function submitReaction( reaction ) {
			// همه‌ی instance های همان پست در حال درخواست قفل می‌شوند تا کلیک
			// همزمان روی نسخه‌ی دیگر ویجت هم مسدود باشد.
			document.querySelectorAll( '.sr-reaction-widget[data-post-id="' + postId + '"]' ).forEach( function ( w ) {
				w.classList.add( 'sr-reaction-loading' );
			} );
			message.textContent = '';
			message.className = 'sr-reaction-message';

			const body = new URLSearchParams();
			body.set( 'action', 'sr_react' );
			body.set( 'nonce', srReactionData.nonce );
			body.set( 'post_id', postId );
			body.set( 'reaction', reaction );

			fetch( srReactionData.ajaxUrl, {
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
					document.querySelectorAll( '.sr-reaction-widget[data-post-id="' + postId + '"]' ).forEach( function ( w ) {
						w.classList.remove( 'sr-reaction-loading' );
					} );

					if ( ! result.success ) {
						message.textContent = ( result.data && result.data.message ) || srReactionData.i18n.error;
						message.classList.add( 'sr-reaction-message-error' );
						return;
					}

					// sync کردن همه‌ی instance های دیگر همین post_id.
					applyStateToAllWidgets( postId, {
						likes:        result.data.likes,
						dislikes:     result.data.dislikes,
						userReaction: result.data.user_reaction
					} );
				} )
				.catch( function () {
					document.querySelectorAll( '.sr-reaction-widget[data-post-id="' + postId + '"]' ).forEach( function ( w ) {
						w.classList.remove( 'sr-reaction-loading' );
					} );
					message.textContent = srReactionData.i18n.error;
					message.classList.add( 'sr-reaction-message-error' );
				} );
		}

		likeBtn.addEventListener( 'click', function () {
			submitReaction( 'like' );
		} );

		dislikeBtn.addEventListener( 'click', function () {
			submitReaction( 'dislike' );
		} );
	}

	function initAll() {
		document.querySelectorAll( '.sr-reaction-widget' ).forEach( initWidget );
	}

	// همان دلیل star-rating.js: اسکریپت ممکن است بعد از fire شدن
	// DOMContentLoaded لود شود (مثلاً رندر AJAX توسط page builder).
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}
} )();
