<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// Variables available: $rows (array), $cards (array)
?>
<div class="wrap hmo-wrap">
	<h1 class="wp-heading-inline">Marketing Ops Dashboard</h1>
	<hr class="wp-header-end">

	<!-- Summary Cards -->
	<div class="hmo-cards">
		<div class="hmo-card">
			<span class="hmo-card__number"><?php echo (int) $cards['my_classes']; ?></span>
			<span class="hmo-card__label">My Classes</span>
		</div>
		<div class="hmo-card hmo-card--red">
			<span class="hmo-card__number"><?php echo (int) $cards['red_risk']; ?></span>
			<span class="hmo-card__label">Red Risk</span>
		</div>
		<div class="hmo-card">
			<span class="hmo-card__number"><?php echo (int) $cards['next_30_days']; ?></span>
			<span class="hmo-card__label">Next 30 Days</span>
		</div>
		<div class="hmo-card hmo-card--warn">
			<span class="hmo-card__number"><?php echo (int) $cards['missing_call_list']; ?></span>
			<span class="hmo-card__label">Missing Call List</span>
		</div>
	</div>

	<!-- Events Table -->
	<?php if ( empty( $rows ) ) : ?>
		<p class="hmo-empty">No events found.</p>
	<?php else : ?>
	<table class="wp-list-table widefat fixed striped hmo-table">
		<thead>
			<tr>
				<th>Event / Class</th>
				<th>Marketer</th>
				<th>Built Date</th>
				<th>Event Date</th>
				<th>Days Left</th>
				<th>Stage</th>
				<th>Open Tasks</th>
				<th>Reg Count</th>
				<th>Goal</th>
				<th>Status</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $rows as $row ) :
				$risk_class = 'hmo-risk--' . esc_attr( $row->risk_level );
				$detail_url = add_query_arg( array(
					'page'     => 'hmo-dashboard',
					'view'     => 'event',
					'event_id' => $row->event_id,
				), admin_url( 'admin.php' ) );
			?>
			<tr class="<?php echo esc_attr( $risk_class ); ?>">
				<td>
					<a href="<?php echo esc_url( $detail_url ); ?>">
						<?php echo esc_html( $row->event_name ?: $row->location ); ?>
					</a>
				</td>
				<td><?php echo esc_html( $row->marketer_name ); ?></td>
				<td><?php echo esc_html( $row->built_date ? date_i18n( 'm/d/Y', strtotime( $row->built_date ) ) : '—' ); ?></td>
				<td><?php echo esc_html( $row->event_date ? date_i18n( 'm/d/Y', strtotime( $row->event_date ) ) : '—' ); ?></td>
				<td><?php echo esc_html( $row->days_left_label ); ?></td>
				<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $row->stage ) ) ); ?></td>
				<td class="hmo-num"><?php echo (int) $row->open_task_count; ?></td>
				<td class="hmo-num"><?php echo (int) $row->registration_count; ?></td>
				<td class="hmo-num"><?php echo (int) $row->registration_goal; ?></td>
				<td>
					<span class="hmo-risk-badge hmo-risk-badge--<?php echo esc_attr( $row->risk_level ); ?>">
						<?php echo esc_html( ucfirst( $row->risk_level ) ); ?>
					</span>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>
</div>
