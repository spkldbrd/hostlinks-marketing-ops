<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Variables injected by HMO_Shortcodes::render_event_report():
 *   $events           array of event objects (id, name, event_date)
 *   $event_id         int — selected event ID (0 = none)
 *   $report_event     object|null — Hostlinks event data (SELECT *)
 *   $report_stages    array — [ stage_key => [ 'label', 'tasks' => [] ] ]
 *   $activity_log     array — hmo_event_activity rows for the event
 *   $user_cache       array — [ user_id => display_name ]
 *   $report_reg_count int   — paid + free registrations
 *   $report_days_left int|null — days until event (negative = past)
 *   $report_marketer  string — marketer display name
 *   $report_ops       object|null — hmo_event_ops row
 */

$event_display_name = '';
if ( $report_event ) {
	$event_display_name = trim( $report_event->cvent_event_title ?: $report_event->eve_location ?? '' );
}

// Build stage-level summary for the progress bar row.
$stage_summary = array();
foreach ( $report_stages as $sk => $sd ) {
	if ( empty( $sd['tasks'] ) ) { continue; }
	$total    = count( $sd['tasks'] );
	$done     = count( array_filter( $sd['tasks'], fn( $t ) => $t->status === 'complete' ) );
	$pct      = $total ? round( ( $done / $total ) * 100 ) : 0;
	$stage_summary[] = array(
		'key'   => $sk,
		'label' => $sd['label'],
		'done'  => $done,
		'total' => $total,
		'pct'   => $pct,
	);
}

$current_stage      = $report_ops->workflow_stage ?? '';
$current_stage_label = '';
foreach ( HMO_Checklist_Templates::get_stages_option() as $s ) {
	if ( $s['key'] === $current_stage ) { $current_stage_label = $s['label']; break; }
}
$goal         = $report_ops ? (int) $report_ops->registration_goal : 0;
$open_tasks   = $report_ops ? (int) $report_ops->open_task_count   : 0;

// Days left display.
$days_display = '—';
$days_class   = '';
if ( $report_days_left !== null ) {
	if ( $report_days_left > 0 ) {
		$days_display = $report_days_left . ' days';
		$days_class   = $report_days_left <= 30 ? 'red' : ( $report_days_left <= 45 ? 'yellow' : 'green' );
	} elseif ( $report_days_left === 0 ) {
		$days_display = 'Today';
		$days_class   = 'red';
	} else {
		$days_display = abs( $report_days_left ) . ' days ago';
		$days_class   = 'past';
	}
}
?>
<div class="hmo-wrap hmo-report-wrap">

	<div class="hmo-report-header">
		<h2 class="hmo-report-title">Event Journey Report</h2>
		<p class="hmo-report-subtitle">Track task completion, stage progression, and team activity for any event.</p>
	</div>

	<!-- Event selector -->
	<form method="get" class="hmo-report-selector-form">
		<?php
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
				<?php echo esc_html( $ev->name ?: '(no name)' ); ?>
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

	<?php if ( ! $report_event ) : ?>
	<div class="hmo-report-empty"><p>Event not found.</p></div>
	<?php else : ?>

	<!-- ── Event summary header ───────────────────────────────────────────── -->
	<div class="hmo-report-summary-header">
		<div class="hmo-report-summary-title">
			<h3 class="hmo-report-event-title"><?php echo esc_html( $event_display_name ); ?></h3>
			<?php if ( $report_event->eve_start ) : ?>
			<span class="hmo-report-event-date">
				<?php
				$ts_start = strtotime( $report_event->eve_start );
				$ts_end   = ( ! empty( $report_event->eve_end ) && $report_event->eve_end !== $report_event->eve_start )
					? strtotime( $report_event->eve_end ) : null;
				if ( $ts_end ) {
					echo esc_html(
						date( 'MY', $ts_start ) === date( 'MY', $ts_end )
						? date_i18n( 'M j', $ts_start ) . '–' . date_i18n( 'j, Y', $ts_end )
						: date_i18n( 'M j', $ts_start ) . ' – ' . date_i18n( 'M j, Y', $ts_end )
					);
				} else {
					echo esc_html( date_i18n( 'M j, Y', $ts_start ) );
				}
				?>
			</span>
			<?php endif; ?>
			<?php if ( $current_stage_label ) : ?>
			<span class="hmo-stage-pill hmo-stage-pill--<?php echo esc_attr( $current_stage ); ?>">
				<?php echo esc_html( $current_stage_label ); ?>
			</span>
			<?php endif; ?>
		</div>

		<!-- Stat cards -->
		<div class="hmo-report-stat-row">
			<?php if ( $report_marketer ) : ?>
			<div class="hmo-report-stat">
				<span class="hmo-report-stat__label">Marketer</span>
				<span class="hmo-report-stat__value"><?php echo esc_html( $report_marketer ); ?></span>
			</div>
			<?php endif; ?>
			<div class="hmo-report-stat">
				<span class="hmo-report-stat__label">Registrations</span>
				<span class="hmo-report-stat__value">
					<?php echo (int) $report_reg_count; ?>
					<?php if ( $goal ) : ?><span class="hmo-report-stat__sub">/ <?php echo (int) $goal; ?> goal</span><?php endif; ?>
				</span>
			</div>
			<div class="hmo-report-stat">
				<span class="hmo-report-stat__label">Open Tasks</span>
				<span class="hmo-report-stat__value"><?php echo (int) $open_tasks; ?></span>
			</div>
			<?php if ( $report_days_left !== null ) : ?>
			<div class="hmo-report-stat hmo-report-stat--days hmo-report-stat--<?php echo esc_attr( $days_class ); ?>">
				<span class="hmo-report-stat__label">Days Left</span>
				<span class="hmo-report-stat__value"><?php echo esc_html( $days_display ); ?></span>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- ── Stage progress overview ────────────────────────────────────────── -->
	<?php if ( ! empty( $stage_summary ) ) : ?>
	<div class="hmo-report-progress-overview">
		<h4 class="hmo-report-section-title">Stage Progress</h4>
		<div class="hmo-report-progress-grid">
			<?php foreach ( $stage_summary as $ss ) :
				$is_current = $ss['key'] === $current_stage;
			?>
			<div class="hmo-report-progress-card<?php echo $is_current ? ' hmo-report-progress-card--current' : ''; ?>
				<?php echo $ss['pct'] === 100 ? ' hmo-report-progress-card--done' : ''; ?>">
				<div class="hmo-report-progress-card__label">
					<?php echo esc_html( $ss['label'] ); ?>
					<?php if ( $is_current ) : ?><span class="hmo-report-current-badge">Current</span><?php endif; ?>
				</div>
				<div class="hmo-report-progress-bar-wrap">
					<div class="hmo-report-progress-bar" style="width:<?php echo (int) $ss['pct']; ?>%"></div>
				</div>
				<div class="hmo-report-progress-card__counts">
					<?php echo (int) $ss['done']; ?> / <?php echo (int) $ss['total']; ?>
					<span class="hmo-report-progress-card__pct"><?php echo (int) $ss['pct']; ?>%</span>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php elseif ( $event_id ) : ?>
	<div class="hmo-report-empty" style="margin-bottom:1.5rem;">
		<p>No tasks have been provisioned for this event yet. Open the event detail page to provision them.</p>
	</div>
	<?php endif; ?>

	<!-- ── Stage detail tables ────────────────────────────────────────────── -->
	<?php if ( ! empty( $stage_summary ) ) : ?>
	<div class="hmo-report-stages">
		<?php foreach ( $report_stages as $stage_key => $stage_data ) :
			if ( empty( $stage_data['tasks'] ) ) { continue; }
			$all_done    = array_reduce( $stage_data['tasks'], fn( $c, $t ) => $c && $t->status === 'complete', true );
			$done_count  = count( array_filter( $stage_data['tasks'], fn( $t ) => $t->status === 'complete' ) );
			$total_count = count( $stage_data['tasks'] );
		?>
		<div class="hmo-report-stage hmo-report-stage--<?php echo esc_attr( $all_done ? 'complete' : ( $stage_key === $current_stage ? 'active' : 'partial' ) ); ?>">
			<div class="hmo-report-stage-header">
				<span class="hmo-stage-pill hmo-stage-pill--<?php echo esc_attr( $stage_key ); ?>">
					<?php echo esc_html( $stage_data['label'] ); ?>
				</span>
				<?php if ( $stage_key === $current_stage ) : ?>
				<span class="hmo-report-current-badge">Current Stage</span>
				<?php endif; ?>
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
						<span class="hmo-report-task-check"><?php echo $is_complete ? '&#10003;' : '&#9675;'; ?></span>
						<?php echo esc_html( $task->task_label ); ?>
						<?php if ( $task->task_description ) : ?>
						<span class="hmo-report-task-desc"><?php echo esc_html( $task->task_description ); ?></span>
						<?php endif; ?>
						<?php if ( $task->completion_note ) : ?>
						<span class="hmo-report-task-note">Note: <?php echo esc_html( $task->completion_note ); ?></span>
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
	<?php endif; ?>

	<!-- ── Activity log ───────────────────────────────────────────────────── -->
	<?php if ( ! empty( $activity_log ) ) : ?>
	<div class="hmo-report-activity">
		<h4 class="hmo-report-section-title">Activity Log</h4>
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

	<?php endif; // $report_event check ?>
	<?php endif; // $event_id check ?>

</div>
