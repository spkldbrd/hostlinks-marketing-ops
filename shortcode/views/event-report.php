<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Variables injected by HMO_Shortcodes::render_event_report():
 *   $events        array of event objects (id, name, event_date)
 *   $event_id      int — selected event ID (0 = none)
 *   $report_event  object|null — Hostlinks event data
 *   $report_stages array — [ stage_key => [ 'label', 'tasks' => [] ] ]
 *   $activity_log  array — hmo_event_activity rows for the event
 *   $user_cache    array — [ user_id => display_name ]
 */

$current_url = remove_query_arg( 'event_id' );
?>
<div class="hmo-wrap hmo-report-wrap">

	<div class="hmo-report-header">
		<h2 class="hmo-report-title">Event Journey Report</h2>
	</div>

	<!-- Event selector -->
	<form method="get" class="hmo-report-selector-form">
		<?php
		// Preserve other GET params except event_id and hmo_page.
		foreach ( $_GET as $k => $v ) {
			if ( in_array( $k, array( 'event_id', 'hmo_page' ), true ) ) { continue; }
			echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		?>
		<label for="hmo-report-event-select" class="hmo-report-selector-label">Select Event:</label>
		<select name="event_id" id="hmo-report-event-select" class="hmo-report-selector-select" onchange="this.form.submit()">
			<option value="">— Choose an event —</option>
			<?php foreach ( $events as $ev ) : ?>
			<option value="<?php echo (int) $ev->id; ?>"
				<?php selected( $event_id, (int) $ev->id ); ?>>
				<?php echo esc_html( $ev->name ); ?>
				<?php if ( $ev->event_date ) : ?>
					(<?php echo esc_html( date_i18n( 'M j, Y', strtotime( $ev->event_date ) ) ); ?>)
				<?php endif; ?>
			</option>
			<?php endforeach; ?>
		</select>
	</form>

	<?php if ( ! $event_id ) : ?>
	<div class="hmo-report-empty">
		<p>Select an event above to view its task journey report.</p>
	</div>
	<?php else : ?>

	<!-- Event summary bar -->
	<?php if ( $report_event ) : ?>
	<div class="hmo-report-event-bar">
		<span class="hmo-report-event-name"><?php echo esc_html( $report_event->eve_name ?? $report_event->name ?? '' ); ?></span>
		<?php
		$eve_date = $report_event->eve_start ?? $report_event->event_date ?? '';
		if ( $eve_date ) :
		?>
		<span class="hmo-report-event-date"><?php echo esc_html( date_i18n( 'F j, Y', strtotime( $eve_date ) ) ); ?></span>
		<?php endif; ?>
		<?php
		$ops   = HMO_DB::get_event_ops( $event_id );
		$stage = $ops->workflow_stage ?? '';
		if ( $stage ) :
			$stage_label = '';
			foreach ( HMO_Checklist_Templates::get_stages_option() as $s ) {
				if ( $s['key'] === $stage ) { $stage_label = $s['label']; break; }
			}
		?>
		<span class="hmo-stage-pill hmo-stage-pill--<?php echo esc_attr( $stage ); ?>">
			<?php echo esc_html( $stage_label ?: ucwords( str_replace( '_', ' ', $stage ) ) ); ?>
		</span>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<!-- Journey: tasks grouped by stage -->
	<div class="hmo-report-stages">
		<?php foreach ( $report_stages as $stage_key => $stage_data ) :
			if ( empty( $stage_data['tasks'] ) ) { continue; }
			$all_done   = array_reduce( $stage_data['tasks'], fn( $c, $t ) => $c && $t->status === 'complete', true );
			$done_count = count( array_filter( $stage_data['tasks'], fn( $t ) => $t->status === 'complete' ) );
			$total_count = count( $stage_data['tasks'] );
		?>
		<div class="hmo-report-stage hmo-report-stage--<?php echo esc_attr( $all_done ? 'complete' : 'partial' ); ?>">
			<div class="hmo-report-stage-header">
				<span class="hmo-stage-pill hmo-stage-pill--<?php echo esc_attr( $stage_key ); ?>">
					<?php echo esc_html( $stage_data['label'] ); ?>
				</span>
				<span class="hmo-report-stage-progress">
					<?php echo (int) $done_count; ?> / <?php echo (int) $total_count; ?> complete
				</span>
				<?php if ( $all_done ) : ?>
				<span class="hmo-report-stage-badge hmo-report-stage-badge--done">&#10003; Done</span>
				<?php endif; ?>
			</div>

			<table class="hmo-report-task-table">
				<thead>
					<tr>
						<th class="hmo-report-col-task">Task</th>
						<th class="hmo-report-col-status">Status</th>
						<th class="hmo-report-col-who">Completed By</th>
						<th class="hmo-report-col-when">Completed On</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $stage_data['tasks'] as $task ) :
					$is_complete = $task->status === 'complete';
					$who = '';
					if ( $is_complete && (int) $task->completed_by_user_id > 0 ) {
						$who = $user_cache[ (int) $task->completed_by_user_id ] ?? sprintf( 'User #%d', $task->completed_by_user_id );
					}
					$when = '';
					if ( $is_complete && $task->completed_at ) {
						$when = date_i18n( 'M j, Y g:i a', strtotime( $task->completed_at ) );
					}
				?>
				<tr class="hmo-report-task-row hmo-report-task-row--<?php echo $is_complete ? 'complete' : 'pending'; ?>">
					<td class="hmo-report-col-task">
						<?php echo esc_html( $task->task_label ); ?>
						<?php if ( $task->completion_note ) : ?>
						<span class="hmo-report-task-note"><?php echo esc_html( $task->completion_note ); ?></span>
						<?php endif; ?>
					</td>
					<td class="hmo-report-col-status">
						<?php if ( $is_complete ) : ?>
						<span class="hmo-report-status-badge hmo-report-status-badge--complete">&#10003; Complete</span>
						<?php else : ?>
						<span class="hmo-report-status-badge hmo-report-status-badge--pending">Pending</span>
						<?php endif; ?>
					</td>
					<td class="hmo-report-col-who"><?php echo $who ? esc_html( $who ) : '<span class="hmo-report-na">—</span>'; ?></td>
					<td class="hmo-report-col-when"><?php echo $when ? esc_html( $when ) : '<span class="hmo-report-na">—</span>'; ?></td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endforeach; ?>
	</div>

	<!-- Activity log -->
	<?php if ( ! empty( $activity_log ) ) : ?>
	<div class="hmo-report-activity">
		<h3 class="hmo-report-section-title">Activity Log</h3>
		<table class="hmo-report-activity-table">
			<thead>
				<tr>
					<th>Date / Time</th>
					<th>Activity</th>
					<th>User</th>
					<th>Details</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $activity_log as $entry ) :
				$entry_user = '';
				if ( (int) $entry->user_id > 0 ) {
					$entry_user = $user_cache[ (int) $entry->user_id ] ?? sprintf( 'User #%d', $entry->user_id );
				}
				$type_label = ucwords( str_replace( '_', ' ', $entry->activity_type ) );
				$meta       = array();
				if ( $entry->meta_json ) {
					$decoded = json_decode( $entry->meta_json, true );
					if ( is_array( $decoded ) ) { $meta = $decoded; }
				}
			?>
			<tr>
				<td class="hmo-report-act-date"><?php echo esc_html( date_i18n( 'M j, Y g:i a', strtotime( $entry->created_at ) ) ); ?></td>
				<td>
					<span class="hmo-report-act-type hmo-report-act-type--<?php echo esc_attr( $entry->activity_type ); ?>">
						<?php echo esc_html( $type_label ); ?>
					</span>
				</td>
				<td><?php echo $entry_user ? esc_html( $entry_user ) : '<span class="hmo-report-na">—</span>'; ?></td>
				<td class="hmo-report-act-summary"><?php echo esc_html( $entry->activity_summary ); ?></td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

	<?php endif; // end event_id check ?>

</div>
