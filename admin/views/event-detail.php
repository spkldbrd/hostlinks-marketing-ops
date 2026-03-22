<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// Variables: $event, $ops, $checklist, $days_left, $reg_count
$stored_goal   = $ops ? (int) $ops->registration_goal : 0;
$goal          = $stored_goal > 0 ? $stored_goal : (int) get_option( 'hmo_default_goal', 25 );
$stage         = $ops ? $ops->workflow_stage : 'event_setup';
$days_label    = isset( $countdown ) ? $countdown->format_days_left( $days_left ) : ( $days_left . ' days' );
$is_past_event = $event->eve_start && strtotime( $event->eve_start ) < strtotime( current_time( 'Y-m-d' ) );
?>
<div class="wrap hmo-wrap hmo-event-detail">
	<h1 class="wp-heading-inline">
		<?php echo esc_html( $event->cvent_event_title ?: $event->eve_location ); ?>
	</h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=hmo-dashboard' ) ); ?>" class="page-title-action">
		&larr; Back to Dashboard
	</a>
	<hr class="wp-header-end">

	<!-- Event Summary -->
	<div class="hmo-section hmo-event-summary">
		<h2>Event Summary</h2>
		<div class="hmo-summary-grid">
			<div class="hmo-summary-item">
				<span class="hmo-summary-label">Location</span>
				<span class="hmo-summary-value"><?php echo esc_html( $event->eve_location ); ?></span>
			</div>
			<div class="hmo-summary-item">
				<span class="hmo-summary-label">Marketer</span>
				<span class="hmo-summary-value"><?php echo esc_html( $ops ? $ops->assigned_marketer_name : '' ); ?></span>
			</div>
			<div class="hmo-summary-item">
				<span class="hmo-summary-label">Built Date</span>
				<span class="hmo-summary-value"><?php echo esc_html( $ops && $ops->class_built_date ? date_i18n( 'm/d/Y', strtotime( $ops->class_built_date ) ) : '—' ); ?></span>
			</div>
			<div class="hmo-summary-item">
				<span class="hmo-summary-label">Event Date</span>
				<span class="hmo-summary-value"><?php echo esc_html( $event->eve_start ? date_i18n( 'm/d/Y', strtotime( $event->eve_start ) ) : '—' ); ?></span>
			</div>
			<div class="hmo-summary-item">
				<span class="hmo-summary-label">Days Left</span>
				<span class="hmo-summary-value"><?php echo esc_html( $days_label ); ?></span>
			</div>
			<div class="hmo-summary-item">
				<span class="hmo-summary-label">Registrations</span>
				<span class="hmo-summary-value">
					<?php echo (int) $reg_count; ?> /
					<?php if ( ! $is_past_event ) : ?>
						<span class="hmo-goal-wrap" data-event-id="<?php echo (int) $event->eve_id; ?>">
							<input type="number" class="hmo-goal-input" min="1"
								value="<?php echo (int) $goal; ?>"
								style="width:64px;">
							<button class="button hmo-goal-save">Save Goal</button>
							<span class="hmo-goal-status" style="font-size:12px;margin-left:4px;"></span>
						</span>
					<?php else : ?>
						<span><?php echo (int) $goal; ?></span>
						<small style="color:#888;">(locked — past event)</small>
					<?php endif; ?>
				</span>
			</div>
			<div class="hmo-summary-item">
				<span class="hmo-summary-label">Stage</span>
				<span class="hmo-summary-value hmo-stage-badge" data-event-id="<?php echo (int) $event->eve_id; ?>">
					<?php echo esc_html( ucwords( str_replace( '_', ' ', $stage ) ) ); ?>
				</span>
			</div>
		</div>

		<!-- Stage selector -->
		<div class="hmo-stage-update" data-event-id="<?php echo (int) $event->eve_id; ?>">
			<label for="hmo-stage-select"><strong>Update Stage:</strong></label>
			<select id="hmo-stage-select" class="hmo-stage-select">
				<?php foreach ( HMO_Checklist_Templates::get_stage_order() as $s ) : ?>
					<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $stage, $s ); ?>>
						<?php echo esc_html( ucwords( str_replace( '_', ' ', $s ) ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button class="button hmo-stage-save">Save Stage</button>
			<span class="hmo-inline-status"></span>
		</div>
	</div>

	<!-- Checklist Panel -->
	<div class="hmo-section hmo-checklist-panel">
		<h2>Checklist</h2>

		<?php foreach ( $checklist as $stage_key => $stage_data ) :
			$tasks      = $stage_data['tasks'];
			$total      = count( $tasks );
			$done       = count( array_filter( $tasks, fn( $t ) => $t->status === 'complete' ) );
			$pct        = $total ? round( ( $done / $total ) * 100 ) : 0;
			$open_count = $total - $done;
		?>
		<div class="hmo-stage-accordion <?php echo $stage_key === $stage ? 'hmo-stage-accordion--active' : ''; ?>"
			data-stage="<?php echo esc_attr( $stage_key ); ?>">
			<div class="hmo-stage-accordion__header">
				<span class="hmo-stage-accordion__title">
					<?php echo esc_html( $stage_data['stage_label'] ); ?>
				</span>
				<span class="hmo-stage-accordion__meta">
					<?php echo (int) $open_count; ?> open &bull; <?php echo (int) $pct; ?>% complete
				</span>
				<span class="hmo-stage-accordion__toggle dashicons dashicons-arrow-down-alt2"></span>
			</div>
			<div class="hmo-stage-accordion__body">
				<?php if ( empty( $tasks ) ) : ?>
					<p class="hmo-empty">No tasks for this stage.</p>
				<?php else : ?>
				<table class="hmo-task-table">
					<thead>
						<tr>
							<th class="hmo-task-check"></th>
							<th>Task</th>
							<th>Due Date</th>
							<th>Completed By</th>
							<th>Note</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $tasks as $task ) :
							$is_complete  = $task->status === 'complete';
							$completed_by = $is_complete && $task->completed_by_user_id
								? get_userdata( (int) $task->completed_by_user_id )
								: null;
							$completed_by_name = $completed_by ? $completed_by->display_name : '';
						?>
						<tr class="hmo-task-row <?php echo $is_complete ? 'hmo-task-row--complete' : ''; ?>"
							data-task-id="<?php echo (int) $task->id; ?>">
							<td class="hmo-task-check">
								<input
									type="checkbox"
									class="hmo-task-toggle"
									data-task-id="<?php echo (int) $task->id; ?>"
									<?php checked( $is_complete ); ?>
								>
							</td>
							<td>
								<div class="hmo-task-label"><?php echo esc_html( $task->task_label ); ?></div>
								<?php if ( $task->task_description ) : ?>
									<div class="hmo-task-desc"><?php echo esc_html( $task->task_description ); ?></div>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $task->due_date ? date_i18n( 'm/d/Y', strtotime( $task->due_date ) ) : '—' ); ?></td>
							<td>
								<?php if ( $is_complete ) : ?>
									<?php echo esc_html( $completed_by_name ); ?>
									<?php if ( $task->completed_at ) : ?>
										<br><small><?php echo esc_html( date_i18n( 'm/d/Y', strtotime( $task->completed_at ) ) ); ?></small>
									<?php endif; ?>
								<?php endif; ?>
							</td>
							<td>
								<textarea
									class="hmo-task-note-input"
									data-task-id="<?php echo (int) $task->id; ?>"
									placeholder="Add a note…"
									rows="1"><?php echo esc_textarea( $task->completion_note ); ?></textarea>
								<button class="button-link hmo-save-note" data-task-id="<?php echo (int) $task->id; ?>">Save note</button>
								<span class="hmo-note-status"></span>
							</td>
							<td></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php endif; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<!-- List Links Panel -->
	<div class="hmo-section hmo-list-links" data-event-id="<?php echo (int) $event->eve_id; ?>">
		<h2>List Links</h2>
		<table class="form-table">
			<tr>
				<th><label for="hmo-data-list-status">Data List Status</label></th>
				<td>
					<select id="hmo-data-list-status" name="data_list_status">
						<?php foreach ( array( '', 'pending', 'sent', 'received', 'complete' ) as $opt ) : ?>
							<option value="<?php echo esc_attr( $opt ); ?>"
								<?php selected( $ops ? $ops->data_list_status : '', $opt ); ?>>
								<?php echo $opt ? esc_html( ucfirst( $opt ) ) : '—'; ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="hmo-data-list-url">Data List URL</label></th>
				<td>
					<input type="url" id="hmo-data-list-url" name="data_list_url" class="regular-text"
						value="<?php echo esc_url( $ops ? $ops->data_list_url : '' ); ?>">
				</td>
			</tr>
			<tr>
				<th><label for="hmo-call-list-status">Call List Status</label></th>
				<td>
					<select id="hmo-call-list-status" name="call_list_status">
						<?php foreach ( array( '', 'pending', 'sent', 'received', 'complete' ) as $opt ) : ?>
							<option value="<?php echo esc_attr( $opt ); ?>"
								<?php selected( $ops ? $ops->call_list_status : '', $opt ); ?>>
								<?php echo $opt ? esc_html( ucfirst( $opt ) ) : '—'; ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="hmo-call-list-url">Call List URL</label></th>
				<td>
					<input type="url" id="hmo-call-list-url" name="call_list_url" class="regular-text"
						value="<?php echo esc_url( $ops ? $ops->call_list_url : '' ); ?>">
				</td>
			</tr>
		</table>
		<p>
			<button class="button button-primary hmo-save-lists">Save List Info</button>
			<span class="hmo-lists-status"></span>
		</p>
	</div>

	<!-- Logistics Panel -->
	<?php if ( $ops ) : ?>
	<div class="hmo-section hmo-logistics">
		<h2>Logistics &amp; Contacts</h2>
		<table class="form-table">
			<tr>
				<th>Ship To Name</th>
				<td><?php echo esc_html( $ops->ship_to_name ); ?></td>
			</tr>
			<tr>
				<th>Ship To Address</th>
				<td><?php echo nl2br( esc_html( $ops->ship_to_address ) ); ?></td>
			</tr>
			<?php if ( $ops->host_contact_json ) :
				$contacts = json_decode( $ops->host_contact_json, true );
				if ( ! empty( $contacts ) ) : ?>
			<tr>
				<th>Host Contacts</th>
				<td>
					<?php foreach ( $contacts as $contact ) : ?>
						<div class="hmo-host-contact">
							<?php echo esc_html( $contact['name'] ?? '' ); ?>
							<?php if ( ! empty( $contact['email'] ) ) : ?>
								&lt;<a href="mailto:<?php echo esc_attr( $contact['email'] ); ?>"><?php echo esc_html( $contact['email'] ); ?></a>&gt;
							<?php endif; ?>
							<?php if ( ! empty( $contact['phone'] ) ) : ?>
								— <?php echo esc_html( $contact['phone'] ); ?>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</td>
			</tr>
			<?php endif; endif; ?>
		</table>
	</div>
	<?php endif; ?>

	<!-- Activity Panel -->
	<div class="hmo-section hmo-activity">
		<h2>Recent Activity</h2>
		<?php
		global $wpdb;
		$activity = $wpdb->get_results( $wpdb->prepare(
			"SELECT a.*, u.display_name
			 FROM {$wpdb->prefix}hmo_event_activity a
			 LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
			 WHERE a.hostlinks_event_id = %d
			 ORDER BY a.created_at DESC
			 LIMIT 25",
			(int) $event->eve_id
		) );
		?>
		<?php if ( empty( $activity ) ) : ?>
			<p class="hmo-empty">No activity yet.</p>
		<?php else : ?>
		<ul class="hmo-activity-list">
			<?php foreach ( $activity as $item ) : ?>
			<li>
				<span class="hmo-activity-time"><?php echo esc_html( date_i18n( 'm/d/Y g:i a', strtotime( $item->created_at ) ) ); ?></span>
				<span class="hmo-activity-user"><?php echo esc_html( $item->display_name ?: 'System' ); ?></span>
				<span class="hmo-activity-summary"><?php echo esc_html( $item->activity_summary ); ?></span>
			</li>
			<?php endforeach; ?>
		</ul>
		<?php endif; ?>
	</div>

</div>
