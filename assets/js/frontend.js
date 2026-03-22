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

	// =========================================================================
	// Task Template Editor
	// =========================================================================

	if ( ! $( '.hmo-te' ).length ) { return; }

	var ajaxUrl  = hmoData.ajaxUrl;
	var teNonce  = hmoData.teNonce;

	function teAjax( action, data, done, fail ) {
		data.action = action;
		data.nonce  = teNonce;
		$.post( ajaxUrl, data, function ( res ) {
			if ( res.success ) {
				done( res.data );
			} else {
				var msg = ( res.data && res.data.message ) ? res.data.message : str.error;
				if ( fail ) { fail( msg ); } else { alert( msg ); }
			}
		} ).fail( function () {
			if ( fail ) { fail( str.error ); } else { alert( str.error ); }
		} );
	}

	function teStatus( $el, msg, isErr ) {
		$el.text( msg )
			.removeClass( 'hmo-status--ok hmo-status--error' )
			.addClass( isErr ? 'hmo-status--error' : 'hmo-status--ok' )
			.show();
		setTimeout( function () { $el.fadeOut( 400 ); }, 2500 );
	}

	// ── Stage helpers ─────────────────────────────────────────────────────────

	function openStage( $stage ) {
		$stage.addClass( 'hmo-te-stage--open' );
		$stage.find( '.hmo-te-stage-toggle' ).first().attr( 'aria-expanded', 'true' );
	}

	function closeStage( $stage ) {
		$stage.removeClass( 'hmo-te-stage--open' );
		$stage.find( '.hmo-te-stage-toggle' ).first().attr( 'aria-expanded', 'false' );
	}

	function updateStageHint( total ) {
		var $hint = $( '#hmo-te-stage-hint' );
		if ( ! $hint.length ) { return; }
		if ( total >= 6 ) {
			$hint.html( 'You have <strong>' + total + '</strong> stages. We recommend keeping it to 6 or fewer for a manageable checklist — but you can add more if needed.' );
		} else {
			$hint.html( 'Recommended maximum: <strong>6 stages</strong>. You currently have ' + total + '.' );
		}
	}

	// ── Toggle stage ──────────────────────────────────────────────────────────

	$( document ).on( 'click', '[data-action="toggle-stage"]', function ( e ) {
		if ( $( e.target ).is( 'button, a' ) || $( e.target ).closest( '.hmo-te-stage__actions' ).length ) {
			return;
		}
		var $stage = $( this ).closest( '.hmo-te-stage' );
		$stage.hasClass( 'hmo-te-stage--open' ) ? closeStage( $stage ) : openStage( $stage );
	} );

	// ── Rename stage ──────────────────────────────────────────────────────────

	$( document ).on( 'click', '[data-action="rename-stage"]', function () {
		var $stage = $( this ).closest( '.hmo-te-stage' );
		openStage( $stage );
		$stage.find( '.hmo-te-stage__rename-form' ).first().slideDown( 150 );
	} );

	$( document ).on( 'click', '[data-action="cancel-stage-rename"]', function () {
		$( this ).closest( '.hmo-te-stage__rename-form' ).slideUp( 150 );
	} );

	$( document ).on( 'click', '[data-action="save-stage-rename"]', function () {
		var $btn    = $( this );
		var stageKey = $btn.data( 'stage-key' );
		var $form   = $btn.closest( '.hmo-te-stage__rename-form' );
		var $status = $form.find( '.hmo-te-status' );
		var label   = $.trim( $form.find( '.hmo-te-stage-new-label' ).val() );

		if ( ! label ) { teStatus( $status, 'Name is required.', true ); return; }

		$btn.text( str.saving ).prop( 'disabled', true );

		teAjax( 'hmo_te_update_stage', { stage_key: stageKey, label: label },
			function ( data ) {
				$btn.text( 'Save Name' ).prop( 'disabled', false );
				var $stage = $form.closest( '.hmo-te-stage' );
				$stage.find( '.hmo-te-stage__title' ).first().text( data.label );
				// Update delete button data too.
				$stage.find( '[data-action="delete-stage"]' ).attr( 'data-stage-label', data.label );
				// Update add-task footer button text.
				$stage.find( '[data-action="show-add-task"]' ).text( '+ Add Task to ' + data.label );
				$form.slideUp( 150 );
			},
			function ( msg ) {
				$btn.text( 'Save Name' ).prop( 'disabled', false );
				teStatus( $status, msg, true );
			}
		);
	} );

	// ── Delete stage ──────────────────────────────────────────────────────────

	$( document ).on( 'click', '[data-action="delete-stage"]', function () {
		var $btn       = $( this );
		var stageKey   = $btn.data( 'stage-key' );
		var stageLabel = $btn.data( 'stage-label' );
		var taskCount  = parseInt( $btn.data( 'task-count' ), 10 ) || 0;
		var $stage     = $btn.closest( '.hmo-te-stage' );

		var confirmMsg = 'Delete stage "' + stageLabel + '"';
		confirmMsg += taskCount
			? ' and all ' + taskCount + ' task(s) inside it? This cannot be undone.'
			: '? It has no tasks. This cannot be undone.';

		if ( ! window.confirm( confirmMsg ) ) { return; }

		teAjax( 'hmo_te_delete_stage', { stage_key: stageKey },
			function ( data ) {
				$stage.fadeOut( 250, function () { $( this ).remove(); } );
				updateStageHint( data.total );
			},
			function ( msg ) { alert( msg ); }
		);
	} );

	// ── Add stage ─────────────────────────────────────────────────────────────

	$( document ).on( 'click', '[data-action="show-add-stage"]', function () {
		$( '#hmo-te-add-stage-form' ).slideToggle( 150 );
		$( '#hmo-te-new-stage-label' ).focus();
	} );

	$( document ).on( 'click', '[data-action="cancel-add-stage"]', function () {
		$( '#hmo-te-add-stage-form' ).slideUp( 150 );
		$( '#hmo-te-new-stage-label' ).val( '' );
	} );

	$( document ).on( 'click', '[data-action="save-new-stage"]', function () {
		var $btn    = $( this );
		var $status = $( '#hmo-te-add-stage-status' );
		var label   = $.trim( $( '#hmo-te-new-stage-label' ).val() );

		if ( ! label ) { teStatus( $status, 'Stage name is required.', true ); return; }

		$btn.text( str.saving ).prop( 'disabled', true );

		teAjax( 'hmo_te_add_stage', { label: label },
			function ( data ) {
				$btn.text( 'Add Stage' ).prop( 'disabled', false );
				// Append the new stage panel before the page footer.
				var html = buildStageHtml( data.stage );
				$( '#hmo-te-page-footer' ).before( html );
				$( '#hmo-te-add-stage-form' ).slideUp( 150 );
				$( '#hmo-te-new-stage-label' ).val( '' );
				updateStageHint( data.total );
			},
			function ( msg ) {
				$btn.text( 'Add Stage' ).prop( 'disabled', false );
				teStatus( $status, msg, true );
			}
		);
	} );

	// ── Toggle task open / closed ─────────────────────────────────────────────

	function openTask( $task ) {
		$task.addClass( 'hmo-te-task--open' );
		$task.find( '.hmo-te-toggle' ).first().attr( 'aria-expanded', 'true' );
	}

	function closeTask( $task ) {
		$task.removeClass( 'hmo-te-task--open' );
		$task.find( '.hmo-te-toggle' ).first().attr( 'aria-expanded', 'false' );
	}

	$( document ).on( 'click', '[data-action="toggle-task"]', function ( e ) {
		// Don't fire if a button inside the actions area was the actual click target.
		if ( $( e.target ).is( 'button, a' ) || $( e.target ).closest( '.hmo-te-task__actions' ).length ) {
			return;
		}
		var $task = $( this ).closest( '.hmo-te-task' );
		$task.hasClass( 'hmo-te-task--open' ) ? closeTask( $task ) : openTask( $task );
	} );

	// ── Show/hide add-task form ────────────────────────────────────────────────

	$( document ).on( 'click', '[data-action="show-add-task"]', function () {
		var stage = $( this ).data( 'stage' );
		$( '.hmo-te-add-task-form[data-stage="' + stage + '"]' ).slideToggle( 150 );
	} );

	$( document ).on( 'click', '[data-action="cancel-add-task"]', function () {
		var $form = $( this ).closest( '.hmo-te-add-task-form' );
		$form.find( '.hmo-te-new-label' ).val( '' );
		$form.find( '.hmo-te-new-desc' ).val( '' );
		$form.slideUp( 150 );
	} );

	// ── Save new task ──────────────────────────────────────────────────────────

	$( document ).on( 'click', '[data-action="save-new-task"]', function () {
		var $btn    = $( this );
		var $form   = $btn.closest( '.hmo-te-add-task-form' );
		var $status = $form.find( '.hmo-te-status' );
		var stage   = $form.data( 'stage' );
		var label   = $.trim( $form.find( '.hmo-te-new-label' ).val() );
		var desc    = $.trim( $form.find( '.hmo-te-new-desc' ).val() );

		if ( ! label ) { teStatus( $status, 'Label is required.', true ); return; }

		$btn.text( str.saving ).prop( 'disabled', true );

		teAjax( 'hmo_te_add_task', { stage_key: stage, label: label, description: desc, parent_id: 0 },
			function ( data ) {
				$btn.text( 'Add Task' ).prop( 'disabled', false );
				var html = buildTaskHtml( data.task );
				var $list = $( '#hmo-te-list-' + stage );
				$list.append( html );
				// Auto-open the parent stage so the new task is visible.
				openStage( $list.closest( '.hmo-te-stage' ) );
				$form.find( '.hmo-te-new-label' ).val( '' );
				$form.find( '.hmo-te-new-desc' ).val( '' );
				$form.slideUp( 150 );
			},
			function ( msg ) {
				$btn.text( 'Add Task' ).prop( 'disabled', false );
				teStatus( $status, msg, true );
			}
		);
	} );

	// ── Show/hide add-subtask form — auto-opens parent task ──────────────────

	$( document ).on( 'click', '[data-action="show-add-subtask"]', function () {
		var $task = $( this ).closest( '.hmo-te-task' );
		openTask( $task );
		$task.find( '.hmo-te-add-subtask-form' ).first().slideToggle( 150 );
	} );

	$( document ).on( 'click', '[data-action="cancel-add-subtask"]', function () {
		var $form = $( this ).closest( '.hmo-te-add-subtask-form' );
		$form.find( '.hmo-te-new-label' ).val( '' );
		$form.find( '.hmo-te-new-desc' ).val( '' );
		$form.slideUp( 150 );
	} );

	// ── Save new sub-task ─────────────────────────────────────────────────────

	$( document ).on( 'click', '[data-action="save-new-subtask"]', function () {
		var $btn      = $( this );
		var $form     = $btn.closest( '.hmo-te-add-subtask-form' );
		var $status   = $form.find( '.hmo-te-status' );
		var stage     = $form.data( 'stage' );
		var parentId  = $form.data( 'parent' );
		var label     = $.trim( $form.find( '.hmo-te-new-label' ).val() );
		var desc      = $.trim( $form.find( '.hmo-te-new-desc' ).val() );

		if ( ! label ) { teStatus( $status, 'Label is required.', true ); return; }

		$btn.text( str.saving ).prop( 'disabled', true );

		teAjax( 'hmo_te_add_task', { stage_key: stage, label: label, description: desc, parent_id: parentId },
			function ( data ) {
				$btn.text( 'Add Sub-task' ).prop( 'disabled', false );
				var $task = $form.closest( '.hmo-te-task' );
				$task.find( '.hmo-te-subtask-list' ).first().append( buildSubtaskHtml( data.task ) );
				// Update or add the sub-task count badge.
				updateSubBadge( $task );
				$form.find( '.hmo-te-new-label' ).val( '' );
				$form.find( '.hmo-te-new-desc' ).val( '' );
				$form.slideUp( 150 );
			},
			function ( msg ) {
				$btn.text( 'Add Sub-task' ).prop( 'disabled', false );
				teStatus( $status, msg, true );
			}
		);
	} );

	// ── Edit task — auto-opens parent ─────────────────────────────────────────

	$( document ).on( 'click', '[data-action="edit-task"]', function () {
		var $btn  = $( this );
		var $task = $btn.closest( '.hmo-te-task' );
		// Open the parent task if this is a top-level edit button.
		if ( $task.length ) { openTask( $task ); }
		var $row = $btn.closest( '.hmo-te-task__view, .hmo-te-subtask__row' );
		var $li  = $btn.closest( '.hmo-te-task, .hmo-te-subtask' );
		$row.hide();
		$li.find( '.hmo-te-task__edit' ).first().show();
	} );

	$( document ).on( 'click', '[data-action="cancel-edit"]', function () {
		var $li = $( this ).closest( '.hmo-te-task, .hmo-te-subtask' );
		$li.find( '.hmo-te-task__edit' ).first().hide();
		$li.find( '.hmo-te-task__view, .hmo-te-subtask__row' ).first().show();
	} );

	$( document ).on( 'click', '[data-action="save-task-edit"]', function () {
		var $btn    = $( this );
		var id      = $btn.data( 'id' );
		var $edit   = $btn.closest( '.hmo-te-task__edit' );
		var $status = $edit.find( '.hmo-te-status' );
		var label   = $.trim( $edit.find( '.hmo-te-edit-label' ).val() );
		var desc    = $.trim( $edit.find( '.hmo-te-edit-desc' ).val() );

		if ( ! label ) { teStatus( $status, 'Label is required.', true ); return; }

		$btn.text( str.saving ).prop( 'disabled', true );

		teAjax( 'hmo_te_update_task', { id: id, label: label, description: desc },
			function ( data ) {
				$btn.text( 'Save' ).prop( 'disabled', false );
				var $li = $edit.closest( '.hmo-te-task, .hmo-te-subtask' );
				$li.find( '.hmo-te-task__label' ).first().text( data.task.task_label );
				$li.find( '.hmo-te-task__desc' ).first().text( data.task.task_description );
				$edit.hide();
				$li.find( '.hmo-te-task__view, .hmo-te-subtask__row' ).first().show();
			},
			function ( msg ) {
				$btn.text( 'Save' ).prop( 'disabled', false );
				teStatus( $status, msg, true );
			}
		);
	} );

	// ── Delete task / sub-task ────────────────────────────────────────────────

	$( document ).on( 'click', '[data-action="delete-task"]', function () {
		var id    = $( this ).data( 'id' );
		var $li   = $( this ).closest( '.hmo-te-task, .hmo-te-subtask' );
		var $task = $( this ).closest( '.hmo-te-task' );

		if ( ! window.confirm( str.confirmDelete ) ) { return; }

		teAjax( 'hmo_te_delete_task', { id: id },
			function () {
				$li.fadeOut( 250, function () {
					$( this ).remove();
					// Refresh badge if a subtask was removed.
					if ( $task.length && ! $li.hasClass( 'hmo-te-task' ) ) {
						updateSubBadge( $task );
					}
				} );
			},
			function ( msg ) { alert( msg ); }
		);
	} );

	// ── Sub-task count badge helper ───────────────────────────────────────────

	function updateSubBadge( $task ) {
		var count = $task.find( '.hmo-te-subtask-list' ).first().children( '.hmo-te-subtask' ).length;
		var $badge = $task.find( '.hmo-te-sub-badge' ).first();
		if ( count > 0 ) {
			var label = count + ' sub-task' + ( count !== 1 ? 's' : '' );
			if ( $badge.length ) {
				$badge.text( label );
			} else {
				$task.find( '.hmo-te-task__actions' ).first().before(
					'<span class="hmo-te-sub-badge hmo-te-sub-badge--collapsed">' + label + '</span>'
				);
			}
		} else {
			$badge.remove();
		}
	}

	// ── HTML builders ─────────────────────────────────────────────────────────

	function buildTaskHtml( t ) {
		var id    = parseInt( t.id, 10 );
		var stage = t.stage_key;
		return '<li class="hmo-te-task" data-id="' + id + '">' +
			'<div class="hmo-te-task__row hmo-te-task__view">' +
				'<button type="button" class="hmo-te-toggle" data-action="toggle-task" aria-expanded="false" title="Expand / collapse">' +
					'<span class="hmo-te-arrow">&#9654;</span>' +
				'</button>' +
				'<span class="hmo-te-drag-handle" title="Drag to reorder">&#9783;</span>' +
				'<div class="hmo-te-task__info hmo-te-task__toggle-zone" data-action="toggle-task">' +
					'<strong class="hmo-te-task__label">' + escHtml( t.task_label ) + '</strong>' +
					( t.task_description ? '<span class="hmo-te-task__desc">' + escHtml( t.task_description ) + '</span>' : '' ) +
				'</div>' +
				'<div class="hmo-te-task__actions">' +
					'<button type="button" class="hmo-te-btn hmo-te-btn--edit" data-action="edit-task">Edit</button>' +
					'<button type="button" class="hmo-te-btn hmo-te-btn--subtask" data-action="show-add-subtask">+ Sub-task</button>' +
					'<button type="button" class="hmo-te-btn hmo-te-btn--delete" data-action="delete-task" data-id="' + id + '">Delete</button>' +
				'</div>' +
			'</div>' +
			'<div class="hmo-te-task__body">' +
				'<div class="hmo-te-task__edit" style="display:none;">' +
					'<input type="text" class="hmo-te-input hmo-te-edit-label" value="' + escAttr( t.task_label ) + '" placeholder="Task label">' +
					'<textarea class="hmo-te-textarea hmo-te-edit-desc" placeholder="Description (optional)">' + escHtml( t.task_description ) + '</textarea>' +
					'<div class="hmo-te-edit-actions">' +
						'<button type="button" class="hmo-te-btn hmo-te-btn--save" data-action="save-task-edit" data-id="' + id + '">Save</button>' +
						'<button type="button" class="hmo-te-btn hmo-te-btn--cancel" data-action="cancel-edit">Cancel</button>' +
						'<span class="hmo-te-status" style="display:none;"></span>' +
					'</div>' +
				'</div>' +
				'<ul class="hmo-te-subtask-list" data-parent="' + id + '"></ul>' +
				'<div class="hmo-te-add-subtask-form" data-parent="' + id + '" data-stage="' + escAttr( stage ) + '" style="display:none;">' +
					'<input type="text" class="hmo-te-input hmo-te-new-label" placeholder="Sub-task label *">' +
					'<textarea class="hmo-te-textarea hmo-te-new-desc" placeholder="Description (optional)"></textarea>' +
					'<div class="hmo-te-edit-actions">' +
						'<button type="button" class="hmo-te-btn hmo-te-btn--save" data-action="save-new-subtask">Add Sub-task</button>' +
						'<button type="button" class="hmo-te-btn hmo-te-btn--cancel" data-action="cancel-add-subtask">Cancel</button>' +
						'<span class="hmo-te-status" style="display:none;"></span>' +
					'</div>' +
				'</div>' +
			'</div>' +
		'</li>';
	}

	function buildSubtaskHtml( t ) {
		var id = parseInt( t.id, 10 );
		return '<li class="hmo-te-subtask" data-id="' + id + '">' +
			'<div class="hmo-te-subtask__row hmo-te-task__view">' +
				'<span class="hmo-te-drag-handle">&#9783;</span>' +
				'<div class="hmo-te-task__info">' +
					'<span class="hmo-te-task__label">' + escHtml( t.task_label ) + '</span>' +
					( t.task_description ? '<span class="hmo-te-task__desc">' + escHtml( t.task_description ) + '</span>' : '' ) +
				'</div>' +
				'<div class="hmo-te-task__actions">' +
					'<button type="button" class="hmo-te-btn hmo-te-btn--edit" data-action="edit-task">Edit</button>' +
					'<button type="button" class="hmo-te-btn hmo-te-btn--delete" data-action="delete-task" data-id="' + id + '">Delete</button>' +
				'</div>' +
			'</div>' +
			'<div class="hmo-te-task__edit" style="display:none;">' +
				'<input type="text" class="hmo-te-input hmo-te-edit-label" value="' + escAttr( t.task_label ) + '" placeholder="Sub-task label">' +
				'<textarea class="hmo-te-textarea hmo-te-edit-desc" placeholder="Description (optional)">' + escHtml( t.task_description ) + '</textarea>' +
				'<div class="hmo-te-edit-actions">' +
					'<button type="button" class="hmo-te-btn hmo-te-btn--save" data-action="save-task-edit" data-id="' + id + '">Save</button>' +
					'<button type="button" class="hmo-te-btn hmo-te-btn--cancel" data-action="cancel-edit">Cancel</button>' +
					'<span class="hmo-te-status" style="display:none;"></span>' +
				'</div>' +
			'</div>' +
		'</li>';
	}

	function buildStageHtml( stage ) {
		var key   = escAttr( stage.key );
		var label = escHtml( stage.label );
		return '<div class="hmo-detail-panel hmo-te-stage" data-stage="' + key + '">' +
			'<div class="hmo-te-stage__header">' +
				'<button type="button" class="hmo-te-stage-toggle" data-action="toggle-stage" aria-expanded="false" title="Expand / collapse stage">' +
					'<span class="hmo-te-stage__arrow">&#9654;</span>' +
				'</button>' +
				'<span class="hmo-te-stage__title-wrap hmo-te-stage__toggle-zone" data-action="toggle-stage">' +
					'<span class="hmo-te-stage__title">' + label + '</span>' +
					'<span class="hmo-te-stage__count">0 tasks</span>' +
				'</span>' +
				'<div class="hmo-te-stage__actions">' +
					'<button type="button" class="hmo-te-btn hmo-te-btn--edit" data-action="rename-stage">Rename</button>' +
					'<button type="button" class="hmo-te-btn hmo-te-btn--delete" data-action="delete-stage"' +
						' data-stage-key="' + key + '" data-stage-label="' + escAttr( stage.label ) + '" data-task-count="0">Delete Stage</button>' +
				'</div>' +
			'</div>' +
			'<div class="hmo-te-stage__body">' +
				'<div class="hmo-te-stage__rename-form" style="display:none;" data-stage-key="' + key + '">' +
					'<input type="text" class="hmo-te-input hmo-te-stage-new-label" value="' + escAttr( stage.label ) + '" placeholder="Stage name *">' +
					'<div class="hmo-te-edit-actions">' +
						'<button type="button" class="hmo-te-btn hmo-te-btn--save" data-action="save-stage-rename" data-stage-key="' + key + '">Save Name</button>' +
						'<button type="button" class="hmo-te-btn hmo-te-btn--cancel" data-action="cancel-stage-rename">Cancel</button>' +
						'<span class="hmo-te-status" style="display:none;"></span>' +
					'</div>' +
				'</div>' +
				'<ul class="hmo-te-task-list" id="hmo-te-list-' + key + '"></ul>' +
				'<div class="hmo-te-add-task-form" style="display:none;" data-stage="' + key + '">' +
					'<input type="text" class="hmo-te-input hmo-te-new-label" placeholder="Task label *">' +
					'<textarea class="hmo-te-textarea hmo-te-new-desc" placeholder="Description (optional)"></textarea>' +
					'<div class="hmo-te-edit-actions">' +
						'<button type="button" class="hmo-te-btn hmo-te-btn--save" data-action="save-new-task">Add Task</button>' +
						'<button type="button" class="hmo-te-btn hmo-te-btn--cancel" data-action="cancel-add-task">Cancel</button>' +
						'<span class="hmo-te-status" style="display:none;"></span>' +
					'</div>' +
				'</div>' +
				'<div class="hmo-te-stage__footer">' +
					'<button type="button" class="hmo-te-btn hmo-te-btn--add" data-action="show-add-task" data-stage="' + key + '">+ Add Task to ' + label + '</button>' +
				'</div>' +
			'</div>' +
		'</div>';
	}

	function escHtml( s ) {
		return String( s )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function escAttr( s ) { return escHtml( s ); }

} )( jQuery );
