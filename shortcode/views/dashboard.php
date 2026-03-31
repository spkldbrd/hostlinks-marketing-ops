<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// Variables: $rows, $all_rows_unfiltered, $all_rows, $cards, $access, $pagination,
//            $view, $alert_data, $buckets, $stage_order, $stage_labels, $detail_base, $filters

$is_admin     = $access->current_user_can_see_all_events();
$base_no_page = remove_query_arg( 'hmo_page' );

// Upcoming / Past Events — each clears both quick-filter toggles.
$upcoming_url = remove_query_arg( array( 'hmo_view', 'hmo_page', 'hmo_next30', 'hmo_trouble' ) );
$past_url     = add_query_arg( 'hmo_view', 'past', remove_query_arg( array( 'hmo_view', 'hmo_page', 'hmo_next30', 'hmo_trouble' ) ) );

// Current quick filter values.
$f_stage   = sanitize_key( $_GET['hmo_stage']   ?? '' );
$f_risk    = sanitize_key( $_GET['hmo_risk']    ?? '' );
$f_bucket  = (int) ( $_GET['hmo_bucket']  ?? 0 );
$f_trouble = ! empty( $_GET['hmo_trouble'] );
$f_next30  = ! empty( $_GET['hmo_next30'] );
$f_missing = sanitize_key( $_GET['hmo_missing'] ?? '' );

$stage_labels_map = array();
foreach ( HMO_Checklist_Templates::get_stages_option() as $s ) {
	$stage_labels_map[ $s['key'] ] = $s['label'];
}

function hmo_clear_filter_url( $remove_key ): string {
	return remove_query_arg( array( $remove_key, 'hmo_page' ) );
}
?>
<div class="hostlinks-page hmo-frontend hmo-dashboard-page">

	<!-- Header -->
	<div class="hmo-dashboard-header">
		<span class="hmo-dashboard-header__title">Marketing Ops</span>
		<nav class="hmo-header-nav">
			<a href="<?php echo esc_url( Hostlinks_Page_URLs::get_upcoming() ); ?>" class="hmo-header-nav__link">
				&larr; Return to Hostlinks
			</a>
			<?php
			$_task_url    = HMO_Page_URLs::get_task_editor();
			$_report_url  = HMO_Page_URLs::get_event_report();
			$_is_mgr_admin = current_user_can( 'manage_options' ) || HMO_Access_Service::current_user_is_marketing_admin();
			if ( $_task_url && $_is_mgr_admin ) :
			?>
			<span class="hmo-header-nav__sep" aria-hidden="true">|</span>
			<a href="<?php echo esc_url( $_task_url ); ?>" class="hmo-header-nav__link">Task Management</a>
			<?php endif; ?>
			<?php if ( $_report_url && $_is_mgr_admin ) : ?>
			<span class="hmo-header-nav__sep" aria-hidden="true">|</span>
			<a href="<?php echo esc_url( $_report_url ); ?>" class="hmo-header-nav__link">Reports</a>
			<?php endif; ?>
		</nav>
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
		<div class="hmo-fe-card hmo-fe-card--orange">
			<span class="hmo-fe-card__number"><?php echo (int) $cards['behind_schedule']; ?></span>
			<span class="hmo-fe-card__label">Behind Schedule</span>
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

	<!-- Alert bar -->
	<?php include HMO_PLUGIN_DIR . 'shortcode/views/partials/alert-bar.php'; ?>

	<!-- Filter + View toolbar -->
	<div class="hmo-toolbar">
		<div class="hmo-toolbar__filters">

			<!-- Stage filter -->
			<select class="hmo-filter-select" onchange="window.location.href=this.value" title="Filter by stage">
				<option value="<?php echo esc_url( hmo_clear_filter_url( 'hmo_stage' ) ); ?>"
					<?php selected( ! $f_stage ); ?>>All Stages</option>
				<?php foreach ( $stage_labels_map as $sk => $sl ) : ?>
				<option value="<?php echo esc_url( add_query_arg( array( 'hmo_stage' => $sk, 'hmo_page' => false ), remove_query_arg( 'hmo_stage' ) ) ); ?>"
					<?php selected( $f_stage, $sk ); ?>><?php echo esc_html( $sl ); ?></option>
				<?php endforeach; ?>
			</select>

			<!-- Risk filter -->
			<select class="hmo-filter-select" onchange="window.location.href=this.value" title="Filter by risk">
				<option value="<?php echo esc_url( hmo_clear_filter_url( 'hmo_risk' ) ); ?>"
					<?php selected( ! $f_risk ); ?>>All Risk Levels</option>
				<option value="<?php echo esc_url( add_query_arg( array( 'hmo_risk' => 'red',    'hmo_page' => false ), remove_query_arg( 'hmo_risk' ) ) ); ?>" <?php selected( $f_risk, 'red' ); ?>>🔴 Red</option>
				<option value="<?php echo esc_url( add_query_arg( array( 'hmo_risk' => 'yellow', 'hmo_page' => false ), remove_query_arg( 'hmo_risk' ) ) ); ?>" <?php selected( $f_risk, 'yellow' ); ?>>🟡 Yellow</option>
				<option value="<?php echo esc_url( add_query_arg( array( 'hmo_risk' => 'green',  'hmo_page' => false ), remove_query_arg( 'hmo_risk' ) ) ); ?>" <?php selected( $f_risk, 'green' ); ?>>🟢 Green</option>
			</select>

			<!-- Bucket filter (admin only) -->
			<?php if ( $is_admin && ! empty( $buckets ) ) : ?>
			<select class="hmo-filter-select" onchange="window.location.href=this.value" title="Filter by bucket">
				<option value="<?php echo esc_url( hmo_clear_filter_url( 'hmo_bucket' ) ); ?>"
					<?php selected( ! $f_bucket ); ?>>All Buckets</option>
				<?php foreach ( $buckets as $b ) : ?>
				<option value="<?php echo esc_url( add_query_arg( array( 'hmo_bucket' => (int) $b->event_marketer_id, 'hmo_page' => false ), remove_query_arg( 'hmo_bucket' ) ) ); ?>"
					<?php selected( $f_bucket, (int) $b->event_marketer_id ); ?>><?php echo esc_html( $b->event_marketer_name ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php endif; ?>

			<!-- Missing filter -->
			<select class="hmo-filter-select" onchange="window.location.href=this.value" title="Missing lists">
				<option value="<?php echo esc_url( hmo_clear_filter_url( 'hmo_missing' ) ); ?>"
					<?php selected( ! $f_missing ); ?>>All Lists</option>
				<option value="<?php echo esc_url( add_query_arg( array( 'hmo_missing' => 'data', 'hmo_page' => false ), remove_query_arg( 'hmo_missing' ) ) ); ?>" <?php selected( $f_missing, 'data' ); ?>>Missing Data List</option>
				<option value="<?php echo esc_url( add_query_arg( array( 'hmo_missing' => 'call', 'hmo_page' => false ), remove_query_arg( 'hmo_missing' ) ) ); ?>" <?php selected( $f_missing, 'call' ); ?>>Missing Call List</option>
				<option value="<?php echo esc_url( add_query_arg( array( 'hmo_missing' => 'both', 'hmo_page' => false ), remove_query_arg( 'hmo_missing' ) ) ); ?>" <?php selected( $f_missing, 'both' ); ?>>Missing Either</option>
			</select>

		</div>

		<div class="hmo-toolbar__right">
			<!-- Quick filter toggles: each clears the others when activated -->
			<a class="hmo-view-bar__btn hmo-view-bar__btn--filter<?php echo $f_trouble ? ' hmo-view-bar__btn--active' : ''; ?>"
				href="<?php echo $f_trouble
					? esc_url( remove_query_arg( array( 'hmo_trouble', 'hmo_page' ) ) )
					: esc_url( add_query_arg( array( 'hmo_trouble' => '1', 'hmo_page' => false ), remove_query_arg( array( 'hmo_next30', 'hmo_page' ) ) ) ); ?>">
				⚠ Trouble Only
			</a>
			<a class="hmo-view-bar__btn hmo-view-bar__btn--filter<?php echo $f_next30 ? ' hmo-view-bar__btn--active' : ''; ?>"
				href="<?php echo $f_next30
					? esc_url( remove_query_arg( array( 'hmo_next30', 'hmo_page' ) ) )
					: esc_url( add_query_arg( array( 'hmo_next30' => '1', 'hmo_page' => false ), remove_query_arg( array( 'hmo_trouble', 'hmo_view', 'hmo_page' ) ) ) ); ?>">
				&#128197; Next 30 Days
			</a>
			<!-- Upcoming / Past toggle: each clears the quick filters -->
			<div class="hmo-view-bar">
				<a class="hmo-view-bar__btn<?php echo $view === 'upcoming' ? ' hmo-view-bar__btn--active' : ''; ?>"
					href="<?php echo esc_url( $upcoming_url ); ?>">Upcoming</a>
				<a class="hmo-view-bar__btn<?php echo $view === 'past' ? ' hmo-view-bar__btn--active' : ''; ?>"
					href="<?php echo esc_url( $past_url ); ?>">Past Events</a>
			</div>
			<!-- Single Table / Kanban toggle button -->
			<button class="hmo-view-bar__btn hmo-view-bar__btn--icon-toggle" id="hmo-btn-view-toggle"
				data-action="toggle-view" data-mode="table" title="Switch to Kanban view">&#9776;</button>
		</div>
	</div>

	<!-- Active filter pills -->
	<?php if ( $f_stage || $f_risk || $f_bucket || $f_trouble || $f_missing || $f_next30 ) : ?>
	<div class="hmo-active-filters">
		<span class="hmo-active-filters__label">Filters:</span>
		<?php if ( $f_stage ) : ?>
		<a class="hmo-active-filter-pill" href="<?php echo esc_url( hmo_clear_filter_url( 'hmo_stage' ) ); ?>">
			Stage: <?php echo esc_html( $stage_labels_map[ $f_stage ] ?? $f_stage ); ?> &times;
		</a>
		<?php endif; ?>
		<?php if ( $f_risk ) : ?>
		<a class="hmo-active-filter-pill" href="<?php echo esc_url( hmo_clear_filter_url( 'hmo_risk' ) ); ?>">
			Risk: <?php echo esc_html( ucfirst( $f_risk ) ); ?> &times;
		</a>
		<?php endif; ?>
		<?php if ( $f_bucket ) : ?>
		<a class="hmo-active-filter-pill" href="<?php echo esc_url( hmo_clear_filter_url( 'hmo_bucket' ) ); ?>">
			Bucket: <?php
			$bn = '';
			foreach ( $buckets as $b ) { if ( (int) $b->event_marketer_id === $f_bucket ) $bn = $b->event_marketer_name; }
			echo esc_html( $bn ?: $f_bucket );
			?> &times;
		</a>
		<?php endif; ?>
		<?php if ( $f_trouble ) : ?>
		<a class="hmo-active-filter-pill" href="<?php echo esc_url( hmo_clear_filter_url( 'hmo_trouble' ) ); ?>">Trouble Only &times;</a>
		<?php endif; ?>
		<?php if ( $f_next30 ) : ?>
		<a class="hmo-active-filter-pill" href="<?php echo esc_url( hmo_clear_filter_url( 'hmo_next30' ) ); ?>">Next 30 Days &times;</a>
		<?php endif; ?>
		<?php if ( $f_missing ) : ?>
		<a class="hmo-active-filter-pill" href="<?php echo esc_url( hmo_clear_filter_url( 'hmo_missing' ) ); ?>">
			Missing: <?php echo esc_html( ucfirst( $f_missing ) ); ?> List &times;
		</a>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<!-- Events Table -->
	<div id="hmo-table-view">
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
					<?php if ( $is_admin ) : ?><th>Bucket</th><?php endif; ?>
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
					<?php if ( $is_admin ) : ?>
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
	</div><!-- /#hmo-table-view -->

	<!-- Kanban view -->
	<?php include HMO_PLUGIN_DIR . 'shortcode/views/partials/kanban.php'; ?>

</div>
