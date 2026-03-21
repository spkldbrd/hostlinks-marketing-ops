/* Hostlinks Marketing Ops — Front-end JS */
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

	$( document ).on( 'click', '.hmo-accordion__header', function () {
		$( this ).closest( '.hmo-accordion' ).toggleClass( 'hmo-accordion--open' );
	} );

	// -------------------------------------------------------------------------
	// Task complete / incomplete
	// -------------------------------------------------------------------------

	$( document ).on( 'change', '.hmo-task-toggle', function () {
		var $cb     = $( this );
		var taskId  = $cb.data( 'task-id' );
		var checked = $cb.is( ':checked' );
		var $task   = $cb.closest( '.hmo-task' );

		$cb.prop( 'disabled', true );

		if ( checked ) {
			var note = $task.find( '.hmo-task-note-input' ).val() || '';
			apiPost(
				'/tasks/' + taskId + '/complete',
				{ note: note },
				function () {
					$task.addClass( 'hmo-task--complete' );
					$cb.prop( 'disabled', false );
					updateAccordionMeta( $task );
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
					$task.removeClass( 'hmo-task--complete' );
					$cb.prop( 'disabled', false );
					updateAccordionMeta( $task );
				},
				function () {
					$cb.prop( 'checked', true ).prop( 'disabled', false );
				}
			);
		}
	} );

	function updateAccordionMeta( $task ) {
		var $body  = $task.closest( '.hmo-accordion__body' );
		var $acc   = $body.closest( '.hmo-accordion' );
		var total  = $body.find( '.hmo-task' ).length;
		var done   = $body.find( '.hmo-task--complete' ).length;
		var open   = total - done;
		var pct    = total ? Math.round( ( done / total ) * 100 ) : 0;
		$acc.find( '.hmo-accordion__meta' ).text( open + ' open \u2022 ' + pct + '%' );
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
				$btn.text( 'Save' ).prop( 'disabled', false );
				showInlineStatus( $status, str.saved, false );
			},
			function () {
				$btn.text( 'Save' ).prop( 'disabled', false );
				showInlineStatus( $status, str.error, true );
			}
		);
	} );

	// -------------------------------------------------------------------------
	// Stage update
	// -------------------------------------------------------------------------

	$( document ).on( 'click', '.hmo-stage-save', function () {
		var $btn    = $( this );
		var $wrap   = $btn.closest( '.hmo-stage-update' );
		var eventId = $wrap.data( 'event-id' );
		var stage   = $wrap.find( '.hmo-stage-select' ).val();
		var $status = $wrap.find( '.hmo-inline-status' );

		$btn.text( str.saving ).prop( 'disabled', true );

		apiPost(
			'/events/' + eventId + '/stage',
			{ stage: stage },
			function () {
				$btn.text( 'Save Stage' ).prop( 'disabled', false );
				showInlineStatus( $status, str.saved, false );
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
