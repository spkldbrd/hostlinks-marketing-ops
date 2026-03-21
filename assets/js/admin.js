/* Hostlinks Marketing Ops — admin JS */
/* global hmoData, jQuery */

( function ( $ ) {
	'use strict';

	var rest  = hmoData.restBase;
	var nonce = hmoData.nonce;
	var str   = hmoData.strings;

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	function apiPost( endpoint, data, done, fail ) {
		$.ajax( {
			url: rest + endpoint,
			method: 'POST',
			beforeSend: function ( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', nonce );
			},
			contentType: 'application/json',
			data: JSON.stringify( data ),
			success: done,
			error: fail || function () {}
		} );
	}

	function showInlineStatus( $el, msg, isError ) {
		$el.text( msg )
			.removeClass( 'hmo-status--ok hmo-status--error' )
			.addClass( isError ? 'hmo-status--error' : 'hmo-status--ok' )
			.show();
		setTimeout( function () { $el.fadeOut( 400 ); }, 2500 );
	}

	// -------------------------------------------------------------------------
	// Checklist accordion
	// -------------------------------------------------------------------------

	$( document ).on( 'click', '.hmo-stage-accordion__header', function () {
		var $acc = $( this ).closest( '.hmo-stage-accordion' );
		$acc.toggleClass( 'hmo-stage-accordion--open' );
		$( this ).find( '.hmo-stage-accordion__toggle' )
			.toggleClass( 'dashicons-arrow-down-alt2 dashicons-arrow-up-alt2' );
	} );

	// Auto-open the current stage on page load.
	$( '.hmo-stage-accordion--active' ).addClass( 'hmo-stage-accordion--open' )
		.find( '.hmo-stage-accordion__toggle' )
		.removeClass( 'dashicons-arrow-down-alt2' )
		.addClass( 'dashicons-arrow-up-alt2' );

	// -------------------------------------------------------------------------
	// Task complete / incomplete toggle
	// -------------------------------------------------------------------------

	$( document ).on( 'change', '.hmo-task-toggle', function () {
		var $cb     = $( this );
		var taskId  = $cb.data( 'task-id' );
		var checked = $cb.is( ':checked' );
		var $row    = $cb.closest( '.hmo-task-row' );

		$cb.prop( 'disabled', true );

		if ( checked ) {
			var note = $row.find( '.hmo-task-note-input' ).val() || '';
			apiPost(
				'/tasks/' + taskId + '/complete',
				{ note: note },
				function () {
					$row.addClass( 'hmo-task-row--complete' );
					$cb.prop( 'disabled', false );
					updateStageProgress( $row );
				},
				function () {
					$cb.prop( 'checked', false ).prop( 'disabled', false );
				}
			);
		} else {
			apiPost(
				'/tasks/' + taskId + '/incomplete',
				{},
				function () {
					$row.removeClass( 'hmo-task-row--complete' );
					$cb.prop( 'disabled', false );
					updateStageProgress( $row );
				},
				function () {
					$cb.prop( 'checked', true ).prop( 'disabled', false );
				}
			);
		}
	} );

	/**
	 * Recalculate and update the stage header open/complete counts.
	 */
	function updateStageProgress( $row ) {
		var $body  = $row.closest( '.hmo-stage-accordion__body' );
		var $acc   = $body.closest( '.hmo-stage-accordion' );
		var total  = $body.find( '.hmo-task-row' ).length;
		var done   = $body.find( '.hmo-task-row--complete' ).length;
		var open   = total - done;
		var pct    = total ? Math.round( ( done / total ) * 100 ) : 0;

		$acc.find( '.hmo-stage-accordion__meta' )
			.text( open + ' open \u2022 ' + pct + '% complete' );
	}

	// -------------------------------------------------------------------------
	// Task note save
	// -------------------------------------------------------------------------

	$( document ).on( 'click', '.hmo-save-note', function () {
		var $btn    = $( this );
		var taskId  = $btn.data( 'task-id' );
		var $status = $btn.siblings( '.hmo-note-status' );
		var note    = $btn.siblings( '.hmo-task-note-input' ).val();

		$btn.text( str.saving ).prop( 'disabled', true );

		apiPost(
			'/tasks/' + taskId + '/note',
			{ note: note },
			function () {
				$btn.text( 'Save note' ).prop( 'disabled', false );
				showInlineStatus( $status, str.saved, false );
			},
			function () {
				$btn.text( 'Save note' ).prop( 'disabled', false );
				showInlineStatus( $status, str.error, true );
			}
		);
	} );

	// -------------------------------------------------------------------------
	// Stage update
	// -------------------------------------------------------------------------

	$( document ).on( 'click', '.hmo-stage-save', function () {
		var $btn     = $( this );
		var $wrap    = $btn.closest( '.hmo-stage-update' );
		var eventId  = $wrap.data( 'event-id' );
		var stage    = $wrap.find( '.hmo-stage-select' ).val();
		var $status  = $wrap.find( '.hmo-inline-status' );

		$btn.text( str.saving ).prop( 'disabled', true );

		apiPost(
			'/events/' + eventId + '/stage',
			{ stage: stage },
			function () {
				$btn.text( 'Save Stage' ).prop( 'disabled', false );
				showInlineStatus( $status, str.saved, false );
				var label = $wrap.find( '.hmo-stage-select option:selected' ).text();
				$( '.hmo-stage-badge' ).text( label );
			},
			function () {
				$btn.text( 'Save Stage' ).prop( 'disabled', false );
				showInlineStatus( $status, str.error, true );
			}
		);
	} );

	// -------------------------------------------------------------------------
	// List links save
	// -------------------------------------------------------------------------

	$( document ).on( 'click', '.hmo-save-lists', function () {
		var $btn     = $( this );
		var $section = $btn.closest( '.hmo-list-links' );
		var eventId  = $section.data( 'event-id' );
		var $status  = $section.find( '.hmo-lists-status' );

		var payload = {
			data_list_status: $section.find( '#hmo-data-list-status' ).val(),
			data_list_url:    $section.find( '#hmo-data-list-url' ).val(),
			call_list_status: $section.find( '#hmo-call-list-status' ).val(),
			call_list_url:    $section.find( '#hmo-call-list-url' ).val()
		};

		$btn.text( str.saving ).prop( 'disabled', true );

		apiPost(
			'/events/' + eventId + '/lists',
			payload,
			function () {
				$btn.text( 'Save List Info' ).prop( 'disabled', false );
				showInlineStatus( $status, str.saved, false );
			},
			function () {
				$btn.text( 'Save List Info' ).prop( 'disabled', false );
				showInlineStatus( $status, str.error, true );
			}
		);
	} );

} )( jQuery );
