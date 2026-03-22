<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// Variables: $rows, $all_rows, $cards, $access, $pagination, $view,
//            $alert_data, $user_buckets, $selected_buckets, $has_multi_buckets, $detail_base, $filters

$is_admin     = $access->current_user_can_see_all_events();
$upcoming_url = add_query_arg( 'hmo_view', 'upcoming', remove_query_arg( array( 'hmo_view', 'hmo_page' ) ) );
$past_url     = add_query_arg( 'hmo_view', 'past',     remove_query_arg( array( 'hmo_view', 'hmo_page' ) ) );
$f_trouble    = ! empty( $_GET['hmo_trouble_only'] );
$f_next30     = ! empty( $_GET['hmo_next30'] );

$stage_labels_map = array();
foreach ( HMO_Checklist_Templates::get_stages_option() as $s ) {
	$stage_labels_map[ $s['key'] ] = $s['label'];
}
?>
<div class="hostlinks-page hmo-frontend hmo-my-classes-page">

	<!-- Header -->
	<div class="hmo-dashboard-header">
		<span class="hmo-dashboard-header__title">My Classes</span>
		<span class="hmo-dashboard-header__stats">
			<span><?php echo (int) $cards['my_classes']; ?> Classes</span>
			<span><?php echo (int) $cards['red_risk']; ?> Red Risk</span>
			<span><?php echo (int) $cards['next_30_days']; ?> Next 30 Days</span>
		</span>
	</div>

	<!-- Summary cards -->
	<div class="hmo-fe-cards">
		<div class="hmo-fe-card">
			<span class="hmo-fe-card__number"><?php echo (int) $cards['my_classes']; ?></span>
			<span class="hmo-fe-card__label">My Classes</span>
		</div>
		<div class="hmo-fe-card hmo-fe-card--red">
			<span class="hmo-fe-card__number"><?php echo (int) $cards['red_risk']; ?></span>
			<span class="hmo-fe-card__label">Red Risk</span>
		</div>
		<div class="hmo-fe-card hmo-fe-card--orange">
			<span class="hmo-fe-card__number"><?php echo (int) $cards['behind_schedule']; ?></span>
			<span class="hmo-fe-card__label">Behind Schedule</span>
		</div>
		<div class="hmo-fe-card">
			<span class="hmo-fe-card__number"><?php echo (int) $cards['next_30_days']; ?></span>
			<span class="hmo-fe-card__label">Next 30 Days</span>
		</div>
	</div>

	<!-- Alert bar -->
	<?php include HMO_PLUGIN_DIR . 'shortcode/views/partials/alert-bar.php'; ?>

	<!-- Bucket pills (only if user has > 1 bucket) -->
	<?php if ( $has_multi_buckets ) :
		// Build toggle URLs for each bucket pill.
		$base_url = remove_query_arg( array( 'hmo_buckets', 'hmo_page' ) );
	?>
	<form class="hmo-bucket-pills" method="get" action="">
		<?php
		// Preserve all other GET params as hidden fields.
		foreach ( $_GET as $k => $v ) {
			if ( $k === 'hmo_buckets' || $k === 'hmo_page' ) { continue; }
			if ( is_array( $v ) ) { continue; }
			echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		?>
		<span class="hmo-bucket-pills__label">Buckets:</span>
		<?php foreach ( $user_buckets as $b ) :
			$checked = in_array( $b['id'], $selected_buckets, true );
		?>
		<label class="hmo-bucket-pill<?php echo $checked ? ' hmo-bucket-pill--active' : ''; ?>">
			<input type="checkbox" name="hmo_buckets[]" value="<?php echo (int) $b['id']; ?>"
				<?php checked( $checked ); ?> onchange="this.form.submit()">
			<?php echo esc_html( $b['name'] ); ?>
		</label>
		<?php endforeach; ?>
	</form>
	<?php endif; ?>

	<!-- Toolbar -->
	<div class="hmo-toolbar">
		<div class="hmo-toolbar__filters">
			<a class="hmo-filter-toggle<?php echo $f_trouble ? ' hmo-filter-toggle--active' : ''; ?>"
				href="<?php echo $f_trouble
					? esc_url( remove_query_arg( array( 'hmo_trouble_only', 'hmo_page' ) ) )
					: esc_url( add_query_arg( array( 'hmo_trouble_only' => '1', 'hmo_page' => false ) ) ); ?>">
				⚠ Trouble Only
			</a>
			<a class="hmo-filter-toggle<?php echo $f_next30 ? ' hmo-filter-toggle--active' : ''; ?>"
				href="<?php echo $f_next30
					? esc_url( remove_query_arg( array( 'hmo_next30', 'hmo_page' ) ) )
					: esc_url( add_query_arg( array( 'hmo_next30' => '1', 'hmo_page' => false ) ) ); ?>">
				📅 Next 30 Days
			</a>
		</div>
		<div class="hmo-toolbar__right">
			<div class="hmo-view-bar">
				<a class="hmo-view-bar__btn<?php echo $view === 'upcoming' ? ' hmo-view-bar__btn--active' : ''; ?>"
					href="<?php echo esc_url( $upcoming_url ); ?>">Upcoming</a>
				<a class="hmo-view-bar__btn<?php echo $view === 'past' ? ' hmo-view-bar__btn--active' : ''; ?>"
					href="<?php echo esc_url( $past_url ); ?>">Past Events</a>
			</div>
		</div>
	</div>

	<!-- Events Table -->
	<?php if ( empty( $rows ) ) : ?>
		<div class="hmo-notice">
			<?php echo $view === 'past' ? 'No past events found.' : 'No upcoming events found.'; ?>
		</div>
	<?php else : ?>
	<div class="hmo-fe-table-wrap">
		<table class="hmo-fe-table">
			<thead>
				<tr>
					<th>Event / Class</th>
					<?php if ( $is_admin || $has_multi_buckets ) : ?><th>Bucket</th><?php endif; ?>
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
					$reg_pct = $row->registration_goal > 0
						? min( 100, round( $row->registration_count / $row->registration_goal * 100 ) )
						: 0;
					$row_classes = 'hmo-fe-row hmo-fe-row--' . esc_attr( $row->risk_level );
					if ( $row->behind_schedule ) { $row_classes .= ' hmo-fe-row--behind'; }
				?>
				<tr class="<?php echo $row_classes; ?>">
					<td class="hmo-fe-event-name">
						<?php if ( $detail_url ) : ?>
							<a href="<?php echo esc_url( $detail_url ); ?>"><?php echo esc_html( $row->event_name ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $row->event_name ); ?>
						<?php endif; ?>
						<?php if ( $row->behind_schedule ) : ?>
							<span class="hmo-behind-badge" title="Behind schedule">⏰</span>
						<?php endif; ?>
					</td>
					<?php if ( $is_admin || $has_multi_buckets ) : ?>
					<td><span class="hmo-bucket-label"><?php echo esc_html( $row->marketer_name ); ?></span></td>
					<?php endif; ?>
					<td><?php echo esc_html( $row->event_date ? date_i18n( 'M j, Y', strtotime( $row->event_date ) ) : '—' ); ?></td>
					<td>
						<span class="hmo-days-left hmo-days-left--<?php echo esc_attr( $row->risk_level ); ?>">
							<?php echo esc_html( $row->days_left_label ); ?>
						</span>
					</td>
					<td>
						<span class="hmo-stage-pill hmo-stage-pill--<?php echo esc_attr( $row->stage ); ?>">
							<?php echo esc_html( $stage_labels_map[ $row->stage ] ?? ucwords( str_replace( '_', ' ', $row->stage ) ) ); ?>
						</span>
					</td>
					<td class="hmo-fe-num"><?php echo (int) $row->open_task_count; ?></td>
					<td class="hmo-fe-reg">
						<div class="hmo-reg-wrap">
							<span class="hmo-reg-nums"><?php echo (int) $row->registration_count; ?> / <?php echo (int) $row->registration_goal; ?></span>
							<div class="hmo-reg-bar" title="<?php echo (int) $reg_pct; ?>% of goal">
								<div class="hmo-reg-bar__fill<?php echo $reg_pct >= 100 ? ' hmo-reg-bar__fill--full' : ''; ?>"
									style="width:<?php echo (int) $reg_pct; ?>%;"></div>
							</div>
						</div>
					</td>
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

	<!-- Pagination -->
	<?php if ( $pagination['total_pages'] > 1 ) : ?>
	<nav class="hmo-pagination" aria-label="Events pagination">
		<span class="hmo-pagination__count">
			Showing <?php echo (int) $pagination['from']; ?>–<?php echo (int) $pagination['to']; ?> of <?php echo (int) $pagination['total']; ?> events
		</span>
		<div class="hmo-pagination__links">
			<?php if ( $pagination['prev_url'] ) : ?>
				<a class="hmo-pagination__btn" href="<?php echo esc_url( $pagination['prev_url'] ); ?>">&lsaquo; Prev</a>
			<?php else : ?>
				<span class="hmo-pagination__btn hmo-pagination__btn--disabled">&lsaquo; Prev</span>
			<?php endif; ?>

			<?php $prev_p = 0;
			foreach ( $pagination['page_urls'] as $p => $url ) :
				if ( $prev_p && $p - $prev_p > 1 ) : ?>
					<span class="hmo-pagination__ellipsis">&hellip;</span>
				<?php endif;
				$is_current = ( $p === $pagination['page'] );
			?>
				<?php if ( $is_current ) : ?>
					<span class="hmo-pagination__btn hmo-pagination__btn--current" aria-current="page"><?php echo (int) $p; ?></span>
				<?php else : ?>
					<a class="hmo-pagination__btn" href="<?php echo esc_url( $url ); ?>"><?php echo (int) $p; ?></a>
				<?php endif; ?>
			<?php $prev_p = $p; endforeach; ?>

			<?php if ( $pagination['next_url'] ) : ?>
				<a class="hmo-pagination__btn" href="<?php echo esc_url( $pagination['next_url'] ); ?>">Next &rsaquo;</a>
			<?php else : ?>
				<span class="hmo-pagination__btn hmo-pagination__btn--disabled">Next &rsaquo;</span>
			<?php endif; ?>
		</div>
	</nav>
	<?php endif; ?>
	<?php endif; ?>

</div>
