<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// Variables: $event, $ops, $checklist, $countdown, $days_left, $days_label, $reg_count, $access

$goal         = $ops ? (int) $ops->registration_goal : (int) get_option( 'hmo_default_goal', 40 );
$stage        = $ops ? $ops->workflow_stage : 'event_setup';
$dashboard_url = HMO_Page_URLs::get_dashboard();
$is_admin     = $access->current_user_can_see_all_events();
?>
<div class="hostlinks-page hmo-frontend hmo-event-detail-page">
	<div class="hostlinks-container">

		<!-- Back link -->
		<?php if ( $dashboard_url ) : ?>
		<div class="hmo-back-link">
			<a href="<?php echo esc_url( $dashboard_url ); ?>" class="hostlinks-btn">
				&larr; Back to Dashboard
			</a>
		</div>
		<?php endif; ?>

		<!-- Event Header -->
		<div class="hmo-detail-header">
			<h1 class="hmo-detail-title">
				<?php echo esc_html( $event->cvent_event_title ?: $event->eve_location ); ?>
			</h1>
			<span class="hmo-risk-pill hmo-risk-pill--<?php echo esc_attr( $countdown->get_risk_level( (int) $days_left, (int) ( $ops ? $ops->open_task_count : 0 ) ) ); ?>">
				<?php echo esc_html( $days_label ); ?> left
			</span>
		</div>

		<!-- Summary Grid -->
		<div class="hmo-detail-summary">
			<div class="hmo-detail-stat">
				<span class="hmo-detail-stat__label">Location</span>
				<span class="hmo-detail-stat__value"><?php echo esc_html( $event->eve_location ); ?></span>
			</div>
			<?php if ( $is_admin ) : ?>
			<div class="hmo-detail-stat">
				<span class="hmo-detail-stat__label">Marketer</span>
				<span class="hmo-detail-stat__value"><?php echo esc_html( $ops ? $ops->assigned_marketer_name : '—' ); ?></span>
			</div>
			<?php endif; ?>
			<div class="hmo-detail-stat">
				<span class="hmo-detail-stat__label">Event Date</span>
				<span class="hmo-detail-stat__value"><?php echo esc_html( $event->eve_start ? date_i18n( 'M j, Y', strtotime( $event->eve_start ) ) : '—' ); ?></span>
			</div>
			<div class="hmo-detail-stat">
				<span class="hmo-detail-stat__label">Registrations</span>
				<span class="hmo-detail-stat__value"><?php echo (int) $reg_count; ?> / <?php echo (int) $goal; ?></span>
			</div>
			<div class="hmo-detail-stat">
				<span class="hmo-detail-stat__label">Stage</span>
				<span class="hmo-detail-stat__value"><?php echo esc_html( ucwords( str_replace( '_', ' ', $stage ) ) ); ?></span>
			</div>
			<div class="hmo-detail-stat">
				<span class="hmo-detail-stat__label">Open Tasks</span>
				<span class="hmo-detail-stat__value"><?php echo $ops ? (int) $ops->open_task_count : 0; ?></span>
			</div>
		</div>

		<!-- Stage Selector (admins only) -->
		<?php if ( $is_admin ) : ?>
		<div class="hmo-stage-update hmo-detail-panel" data-event-id="<?php echo (int) $event->eve_id; ?>">
			<label><strong>Update Stage:</strong></label>
			<select class="hmo-stage-select">
				<?php foreach ( HMO_Checklist_Templates::get_stage_order() as $s ) : ?>
					<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $stage, $s ); ?>>
						<?php echo esc_html( ucwords( str_replace( '_', ' ', $s ) ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button class="hostlinks-btn hmo-stage-save">Save Stage</button>
			<span class="hmo-inline-status"></span>
		</div>
		<?php endif; ?>

		<!-- Checklist -->
		<div class="hmo-detail-panel">
			<h2 class="hmo-panel-title">Checklist</h2>

			<?php foreach ( $checklist as $stage_key => $stage_data ) :
				$tasks      = $stage_data['tasks'];
				$total      = count( $tasks );
				$done       = count( array_filter( $tasks, fn( $t ) => $t->status === 'complete' ) );
				$pct        = $total ? round( ( $done / $total ) * 100 ) : 0;
				$open_count = $total - $done;
			?>
			<div class="hmo-accordion <?php echo $stage_key === $stage ? 'hmo-accordion--open' : ''; ?>"
				data-stage="<?php echo esc_attr( $stage_key ); ?>">
				<div class="hmo-accordion__header">
					<span class="hmo-accordion__title"><?php echo esc_html( $stage_data['stage_label'] ); ?></span>
					<span class="hmo-accordion__meta"><?php echo (int) $open_count; ?> open &bull; <?php echo (int) $pct; ?>%</span>
					<span class="hmo-accordion__arrow">&#9660;</span>
				</div>
				<div class="hmo-accordion__body">
					<?php if ( empty( $tasks ) ) : ?>
						<p class="hmo-notice">No tasks for this stage.</p>
					<?php else : ?>
					<div class="hmo-task-list">
						<?php foreach ( $tasks as $task ) :
							$is_complete = $task->status === 'complete';
						?>
						<div class="hmo-task <?php echo $is_complete ? 'hmo-task--complete' : ''; ?>"
							data-task-id="<?php echo (int) $task->id; ?>">
							<label class="hmo-task__check-label">
								<input type="checkbox"
									class="hmo-task-toggle"
									data-task-id="<?php echo (int) $task->id; ?>"
									<?php checked( $is_complete ); ?>>
								<span class="hmo-task__label"><?php echo esc_html( $task->task_label ); ?></span>
							</label>
							<?php if ( $task->task_description ) : ?>
								<div class="hmo-task__desc"><?php echo esc_html( $task->task_description ); ?></div>
							<?php endif; ?>
							<?php if ( $is_complete && $task->completed_at ) : ?>
								<div class="hmo-task__completed-by">
									Completed <?php echo esc_html( date_i18n( 'M j', strtotime( $task->completed_at ) ) ); ?>
									<?php if ( $task->completed_by_user_id ) :
										$u = get_userdata( (int) $task->completed_by_user_id );
										if ( $u ) echo ' by ' . esc_html( $u->display_name );
									endif; ?>
								</div>
							<?php endif; ?>
							<div class="hmo-task__note-wrap">
								<textarea class="hmo-task-note-input"
									data-task-id="<?php echo (int) $task->id; ?>"
									placeholder="Add a note…"
									rows="1"><?php echo esc_textarea( $task->completion_note ); ?></textarea>
								<button class="hmo-save-note hostlinks-btn" data-task-id="<?php echo (int) $task->id; ?>">Save</button>
								<span class="hmo-note-status"></span>
							</div>
						</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
				</div>
			</div>
			<?php endforeach; ?>
		</div>

		<!-- List Links -->
		<div class="hmo-detail-panel hmo-list-links" data-event-id="<?php echo (int) $event->eve_id; ?>">
			<h2 class="hmo-panel-title">List Links</h2>
			<div class="hmo-list-grid">
				<div class="hmo-list-item">
					<label class="hmo-list-label">Data List Status</label>
					<select id="hmo-data-list-status">
						<?php foreach ( array( '' => '—', 'pending' => 'Pending', 'sent' => 'Sent', 'received' => 'Received', 'complete' => 'Complete' ) as $val => $lbl ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $ops ? $ops->data_list_status : '', $val ); ?>>
								<?php echo esc_html( $lbl ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="hmo-list-item">
					<label class="hmo-list-label">Data List URL</label>
					<input type="url" id="hmo-data-list-url" class="hmo-list-url-input"
						value="<?php echo esc_url( $ops ? $ops->data_list_url : '' ); ?>"
						placeholder="https://docs.google.com/spreadsheets/…">
					<?php if ( $ops && $ops->data_list_url ) : ?>
						<a href="<?php echo esc_url( $ops->data_list_url ); ?>" target="_blank" rel="noopener" class="hostlinks-btn hmo-list-open">Open &#8599;</a>
					<?php endif; ?>
				</div>
				<div class="hmo-list-item">
					<label class="hmo-list-label">Call List Status</label>
					<select id="hmo-call-list-status">
						<?php foreach ( array( '' => '—', 'pending' => 'Pending', 'sent' => 'Sent', 'received' => 'Received', 'complete' => 'Complete' ) as $val => $lbl ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $ops ? $ops->call_list_status : '', $val ); ?>>
								<?php echo esc_html( $lbl ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="hmo-list-item">
					<label class="hmo-list-label">Call List URL</label>
					<input type="url" id="hmo-call-list-url" class="hmo-list-url-input"
						value="<?php echo esc_url( $ops ? $ops->call_list_url : '' ); ?>"
						placeholder="https://docs.google.com/spreadsheets/…">
					<?php if ( $ops && $ops->call_list_url ) : ?>
						<a href="<?php echo esc_url( $ops->call_list_url ); ?>" target="_blank" rel="noopener" class="hostlinks-btn hmo-list-open">Open &#8599;</a>
					<?php endif; ?>
				</div>
			</div>
			<button class="hostlinks-btn hostlinks-btn--active hmo-save-lists">Save List Info</button>
			<span class="hmo-lists-status"></span>
		</div>

		<!-- Activity -->
		<div class="hmo-detail-panel">
			<h2 class="hmo-panel-title">Recent Activity</h2>
			<?php
			global $wpdb;
			$activity = $wpdb->get_results( $wpdb->prepare(
				"SELECT a.*, u.display_name
				 FROM {$wpdb->prefix}hmo_event_activity a
				 LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
				 WHERE a.hostlinks_event_id = %d
				 ORDER BY a.created_at DESC
				 LIMIT 20",
				(int) $event->eve_id
			) );
			?>
			<?php if ( empty( $activity ) ) : ?>
				<p class="hmo-notice">No activity yet.</p>
			<?php else : ?>
			<ul class="hmo-activity-list">
				<?php foreach ( $activity as $item ) : ?>
				<li class="hmo-activity-item">
					<span class="hmo-activity-time"><?php echo esc_html( date_i18n( 'M j, Y g:i a', strtotime( $item->created_at ) ) ); ?></span>
					<span class="hmo-activity-user"><?php echo esc_html( $item->display_name ?: 'System' ); ?></span>
					<span class="hmo-activity-summary"><?php echo esc_html( $item->activity_summary ); ?></span>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
		</div>

	</div><!-- .hostlinks-container -->
</div><!-- .hostlinks-page -->
