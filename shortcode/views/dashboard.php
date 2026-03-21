<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// Variables: $rows (array), $cards (array), $access (HMO_Access_Service)

$detail_base = HMO_Page_URLs::get_event_detail();
$is_admin    = $access->current_user_can_see_all_events();
?>
<div class="hostlinks-page hmo-frontend">

	<!-- Dashboard Header Banner -->
	<div class="hmo-dashboard-header">
		<span class="hmo-dashboard-header__title">Marketing Ops</span>
		<span class="hmo-dashboard-header__stats">
			<span><?php echo (int) $cards['my_classes']; ?> Classes</span>
			<span><?php echo (int) $cards['red_risk']; ?> Red Risk</span>
			<span><?php echo (int) $cards['next_30_days']; ?> Next 30 Days</span>
		</span>
	</div>

	<!-- Summary Cards -->
	<div class="hmo-fe-cards">
		<div class="hmo-fe-card">
			<span class="hmo-fe-card__number"><?php echo (int) $cards['my_classes']; ?></span>
			<span class="hmo-fe-card__label">Classes</span>
		</div>
		<div class="hmo-fe-card hmo-fe-card--red">
			<span class="hmo-fe-card__number"><?php echo (int) $cards['red_risk']; ?></span>
			<span class="hmo-fe-card__label">Red Risk</span>
		</div>
		<div class="hmo-fe-card">
			<span class="hmo-fe-card__number"><?php echo (int) $cards['next_30_days']; ?></span>
			<span class="hmo-fe-card__label">Next 30 Days</span>
		</div>
		<div class="hmo-fe-card hmo-fe-card--warn">
			<span class="hmo-fe-card__number"><?php echo (int) $cards['missing_data_list']; ?></span>
			<span class="hmo-fe-card__label">Missing Data List</span>
		</div>
		<div class="hmo-fe-card hmo-fe-card--warn">
			<span class="hmo-fe-card__number"><?php echo (int) $cards['missing_call_list']; ?></span>
			<span class="hmo-fe-card__label">Missing Call List</span>
		</div>
	</div>

	<!-- Events Table -->
	<?php if ( empty( $rows ) ) : ?>
		<div class="hmo-notice">No events found.</div>
	<?php else : ?>
	<div class="hmo-fe-table-wrap">
		<table class="hmo-fe-table">
			<thead>
				<tr>
					<th>Event / Class</th>
					<?php if ( $is_admin ) : ?><th>Marketer</th><?php endif; ?>
					<th>Event Date</th>
					<th>Days Left</th>
					<th>Stage</th>
					<th>Open Tasks</th>
					<th>Reg / Goal</th>
					<th>Status</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) :
					$detail_url = $detail_base
						? add_query_arg( 'event_id', $row->event_id, $detail_base )
						: '';
				?>
				<tr class="hmo-fe-row hmo-fe-row--<?php echo esc_attr( $row->risk_level ); ?>">
					<td class="hmo-fe-event-name">
						<?php if ( $detail_url ) : ?>
							<a href="<?php echo esc_url( $detail_url ); ?>">
								<?php echo esc_html( $row->event_name ?: $row->location ); ?>
							</a>
						<?php else : ?>
							<?php echo esc_html( $row->event_name ?: $row->location ); ?>
						<?php endif; ?>
					</td>
					<?php if ( $is_admin ) : ?>
					<td><?php echo esc_html( $row->marketer_name ); ?></td>
					<?php endif; ?>
					<td><?php echo esc_html( $row->event_date ? date_i18n( 'M j, Y', strtotime( $row->event_date ) ) : '—' ); ?></td>
					<td>
						<span class="hmo-days-left hmo-days-left--<?php echo esc_attr( $row->risk_level ); ?>">
							<?php echo esc_html( $row->days_left_label ); ?>
						</span>
					</td>
					<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $row->stage ) ) ); ?></td>
					<td class="hmo-fe-num"><?php echo (int) $row->open_task_count; ?></td>
					<td class="hmo-fe-num"><?php echo (int) $row->registration_count; ?> / <?php echo (int) $row->registration_goal; ?></td>
					<td>
						<span class="hmo-risk-pill hmo-risk-pill--<?php echo esc_attr( $row->risk_level ); ?>">
							<?php echo esc_html( ucfirst( $row->risk_level ) ); ?>
						</span>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

</div>
