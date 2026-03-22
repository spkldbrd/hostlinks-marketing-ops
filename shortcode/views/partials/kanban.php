<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// Variables: $all_rows (full unfiltered list), $detail_base (string)

$stages = HMO_Checklist_Templates::get_stages_option();

// Build ordered stage list for JS drag-and-drop.
$stage_order_for_js = array();
foreach ( $stages as $s ) {
	$stage_order_for_js[] = array( 'key' => $s['key'], 'label' => $s['label'] );
}

// Group rows by stage.
$by_stage = array();
foreach ( $stages as $s ) {
	$by_stage[ $s['key'] ] = array( 'label' => $s['label'], 'rows' => array() );
}
foreach ( $all_rows as $row ) {
	$key = $row->stage ?? 'event_setup';
	if ( ! isset( $by_stage[ $key ] ) ) {
		$by_stage[ $key ] = array( 'label' => ucwords( str_replace( '_', ' ', $key ) ), 'rows' => array() );
	}
	$by_stage[ $key ]['rows'][] = $row;
}
?>
<script>
window.hmoKanbanStages = <?php echo wp_json_encode( $stage_order_for_js ); ?>;
</script>
<div class="hmo-kanban" id="hmo-kanban" style="display:none;" aria-label="Kanban view">
	<?php foreach ( $by_stage as $stage_key => $stage ) : ?>
	<div class="hmo-kanban__col"
		data-stage-key="<?php echo esc_attr( $stage_key ); ?>"
		data-stage-label="<?php echo esc_attr( $stage['label'] ); ?>">
		<div class="hmo-kanban__col-header">
			<span class="hmo-stage-pill hmo-stage-pill--<?php echo esc_attr( $stage_key ); ?>">
				<?php echo esc_html( $stage['label'] ); ?>
			</span>
			<span class="hmo-kanban__count" data-stage-count="<?php echo esc_attr( $stage_key ); ?>"><?php echo count( $stage['rows'] ); ?></span>
		</div>
		<div class="hmo-kanban__cards" data-stage-drop="<?php echo esc_attr( $stage_key ); ?>">
			<?php foreach ( $stage['rows'] as $row ) :
				$url = $detail_base ? add_query_arg( 'event_id', $row->event_id, $detail_base ) : '';
				$pct = $row->registration_goal > 0
					? min( 100, round( $row->registration_count / $row->registration_goal * 100 ) )
					: 0;
			?>
			<div class="hmo-kanban__card hmo-kanban__card--<?php echo esc_attr( $row->risk_level ); ?>"
				draggable="true"
				data-event-id="<?php echo (int) $row->event_id; ?>"
				data-stage="<?php echo esc_attr( $row->stage ?? 'event_setup' ); ?>">
				<div class="hmo-kanban__card-name">
					<?php if ( $url ) : ?>
						<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $row->event_name ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $row->event_name ); ?>
					<?php endif; ?>
				</div>
				<div class="hmo-kanban__card-meta">
					<span class="hmo-kanban__bucket"><?php echo esc_html( $row->marketer_name ); ?></span>
					<span class="hmo-days-left hmo-days-left--<?php echo esc_attr( $row->risk_level ); ?>">
						<?php echo esc_html( $row->days_left_label ); ?>
					</span>
				</div>
				<div class="hmo-kanban__progress" title="<?php echo esc_attr( $row->registration_count . ' / ' . $row->registration_goal . ' registrations' ); ?>">
					<div class="hmo-kanban__progress-bar" style="width:<?php echo (int) $pct; ?>%;"></div>
				</div>
				<div class="hmo-kanban__card-footer">
					<span class="hmo-kanban__tasks"><?php echo (int) $row->open_task_count; ?> open</span>
					<?php if ( $row->behind_schedule ) : ?>
					<span class="hmo-kanban__behind" title="Behind schedule">⏰</span>
					<?php endif; ?>
					<span class="hmo-risk-pill hmo-risk-pill--<?php echo esc_attr( $row->risk_level ); ?>">
						<?php echo esc_html( ucfirst( $row->risk_level ) ); ?>
					</span>
				</div>
			</div>
			<?php endforeach; ?>
			<?php if ( empty( $stage['rows'] ) ) : ?>
			<div class="hmo-kanban__empty">No events</div>
			<?php endif; ?>
		</div>
	</div>
	<?php endforeach; ?>
</div>
