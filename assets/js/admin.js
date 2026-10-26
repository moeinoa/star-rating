/**
 * منطق صفحه «جزئیات» ادمین: ویرایش مقدار یا حذف تک‌تک رای‌ها/واکنش‌های
 * ثبت‌شده برای یک پست. Vanilla JS، بدون وابستگی به jQuery.
 */
( function () {
	'use strict';

	function showRowMessage( row, text, isError ) {
		const el = row.querySelector( '.sr-admin-row-message' );
		if ( ! el ) {
			return;
		}
		el.textContent = text;
		el.classList.toggle( 'sr-admin-row-error', !! isError );
		el.classList.toggle( 'sr-admin-row-success', ! isError );
	}

	function post( action, params ) {
		const body = new URLSearchParams();
		body.set( 'action', action );
		body.set( 'nonce', srAdminData.nonce );

		Object.keys( params ).forEach( function ( key ) {
			body.set( key, params[ key ] );
		} );

		return fetch( srAdminData.ajaxUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded'
			},
			credentials: 'same-origin',
			body: body.toString()
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	function initVoteRows() {
		document.querySelectorAll( '.sr-vote-row' ).forEach( function ( row ) {
			const voteId    = row.dataset.voteId;
			const select    = row.querySelector( '.sr-vote-select' );
			const saveBtn   = row.querySelector( '.sr-admin-save-vote' );
			const deleteBtn = row.querySelector( '.sr-admin-delete-vote' );

			saveBtn.addEventListener( 'click', function () {
				saveBtn.disabled = true;
				showRowMessage( row, '', false );

				post( 'sr_admin_update_vote', {
					vote_id: voteId,
					rating: select.value
				} )
					.then( function ( result ) {
						saveBtn.disabled = false;
						const message = ( result.data && result.data.message ) || ( result.success ? '' : srAdminData.i18n.error );
						showRowMessage( row, message, ! result.success );
					} )
					.catch( function () {
						saveBtn.disabled = false;
						showRowMessage( row, srAdminData.i18n.error, true );
					} );
			} );

			deleteBtn.addEventListener( 'click', function () {
				if ( ! window.confirm( srAdminData.i18n.confirm ) ) {
					return;
				}

				deleteBtn.disabled = true;

				post( 'sr_admin_delete_vote', {
					vote_id: voteId
				} )
					.then( function ( result ) {
						if ( result.success ) {
							row.remove();
						} else {
							deleteBtn.disabled = false;
							showRowMessage( row, ( result.data && result.data.message ) || srAdminData.i18n.error, true );
						}
					} )
					.catch( function () {
						deleteBtn.disabled = false;
						showRowMessage( row, srAdminData.i18n.error, true );
					} );
			} );
		} );
	}

	function initReactionRows() {
		document.querySelectorAll( '.sr-reaction-row' ).forEach( function ( row ) {
			const reactionId = row.dataset.reactionId;
			const select     = row.querySelector( '.sr-reaction-select' );
			const saveBtn    = row.querySelector( '.sr-admin-save-reaction' );
			const deleteBtn  = row.querySelector( '.sr-admin-delete-reaction' );

			saveBtn.addEventListener( 'click', function () {
				saveBtn.disabled = true;
				showRowMessage( row, '', false );

				post( 'sr_admin_update_reaction', {
					reaction_id: reactionId,
					reaction: select.value
				} )
					.then( function ( result ) {
						saveBtn.disabled = false;
						const message = ( result.data && result.data.message ) || ( result.success ? '' : srAdminData.i18n.error );
						showRowMessage( row, message, ! result.success );
					} )
					.catch( function () {
						saveBtn.disabled = false;
						showRowMessage( row, srAdminData.i18n.error, true );
					} );
			} );

			deleteBtn.addEventListener( 'click', function () {
				if ( ! window.confirm( srAdminData.i18n.confirm ) ) {
					return;
				}

				deleteBtn.disabled = true;

				post( 'sr_admin_delete_reaction', {
					reaction_id: reactionId
				} )
					.then( function ( result ) {
						if ( result.success ) {
							row.remove();
						} else {
							deleteBtn.disabled = false;
							showRowMessage( row, ( result.data && result.data.message ) || srAdminData.i18n.error, true );
						}
					} )
					.catch( function () {
						deleteBtn.disabled = false;
						showRowMessage( row, srAdminData.i18n.error, true );
					} );
			} );
		} );
	}

	/**
	 * نمایش/مخفی‌کردن textarea مربوط به include_ids یا exclude_ids در صفحه
	 * تنظیمات، بر اساس رادیوباتن انتخاب‌شده برای mode. مستقل از srAdminData
	 * است چون فقط UI صفحه تنظیمات را کنترل می‌کند و به AJAX نیازی ندارد.
	 */
	function initAutoInjectToggle() {
		const radios = document.querySelectorAll( '.sr-auto-inject-mode' );
		if ( ! radios.length ) {
			return;
		}

		function updateVisibility() {
			const checked = document.querySelector( '.sr-auto-inject-mode:checked' );
			const activeMode = checked ? checked.value : 'include';

			document.querySelectorAll( '.sr-auto-inject-row' ).forEach( function ( row ) {
				row.style.display = ( row.dataset.mode === activeMode ) ? '' : 'none';
			} );
		}

		radios.forEach( function ( radio ) {
			radio.addEventListener( 'change', updateVisibility );
		} );

		updateVisibility();
	}

	function initAll() {
		initAutoInjectToggle();

		// اگر srAdminData موجود نباشد (مثلاً به هر دلیلی localize انجام
		// نشده)، از اجرای بی‌فایده باقی‌ماندهٔ منطق AJAX صرف‌نظر می‌کنیم.
		if ( typeof srAdminData === 'undefined' ) {
			return;
		}
		initVoteRows();
		initReactionRows();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}
} )();
