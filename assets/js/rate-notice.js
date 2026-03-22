/**
 * Page Categorizer – Rate Notice JS
 *
 * Handles the three notice actions (review, done, later) via AJAX
 * and animates the notice out on interaction.
 *
 * @package page-categorizer
 * @since   1.5.0
 */

/* global pagecateNotice */
( function ( $ ) {
	'use strict';

	function handleNoticeAction( action ) {
		var $notice = $( '#pagecate-rate-notice' );

		$.post( ajaxurl, {
			action:        'pagecate_notice_action',
			_wpnonce:      pagecateNotice.nonce,
			notice_action: action,
		} );

		if ( 'review' === action ) {
			window.open( pagecateNotice.reviewUrl, '_blank' );
		}

		$notice.fadeTo( 200, 0, function () {
			$notice.slideUp( 200 );
		} );
	}

	$( document ).on( 'click', '#pagecate-btn-review',       function () { handleNoticeAction( 'review' ); } );
	$( document ).on( 'click', '#pagecate-btn-done',         function () { handleNoticeAction( 'done' ); } );
	$( document ).on( 'click', '#pagecate-btn-later',        function () { handleNoticeAction( 'later' ); } );
	$( document ).on( 'click', '#pagecate-notice-close',     function () { handleNoticeAction( 'later' ); } );

} )( jQuery );
