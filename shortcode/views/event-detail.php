<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// Variables: $event, $ops, $checklist, $countdown, $days_left, $days_label, $reg_count, $access

$stored_goal   = $ops ? (int) $ops->registration_goal : 0;
$goal          = $stored_goal > 0 ? $stored_goal : (int) get_option( 'hmo_default_goal', 25 );
$stage         = $ops ? $ops->workflow_stage : 'event_setup';
$dashboard_url = HMO_Page_URLs::get_dashboard();
$is_admin      = $access->current_user_can_see_all_events();
$is_past_event = $event->eve_start && strtotime( $event->eve_start ) < strtotime( current_time( 'Y-m-d' ) );
?>
<div class="hostlinks-page hmo-frontend hmo-event-detail-page">
	<div class="hostlinks-container">

		<!-- Event Header: days pill + title on left, back button on right -->
		<div class="hmo-detail-header">
			<span class="hmo-risk-pill hmo-risk-pill--<?php echo esc_attr( $countdown->get_risk_level( (int) $days_left, (int) ( $ops ? $ops->open_task_count : 0 ) ) ); ?>">
				<?php echo esc_html( $days_label ); ?> left
			</span>
			<h1 class="hmo-detail-title">
				<?php echo esc_html( $event->cvent_event_title ?: $event->eve_location ); ?>
			</h1>
			<?php if ( $dashboard_url ) : ?>
			<a href="<?php echo esc_url( $dashboard_url ); ?>" class="hostlinks-btn hmo-detail-back-btn">
				&larr; Back to Dashboard
			</a>
			<?php endif; ?>
		</div>

		<!-- Blue Summary Bar -->
		<div class="hmo-detail-summary hmo-stage-update" data-event-id="<?php echo (int) $event->eve_id; ?>">
			<div class="hmo-detail-stat">
				<span class="hmo-detail-stat__label">Location</span>
				<span class="hmo-detail-stat__value"><?php echo esc_html( $event->eve_location ); ?></span>
			</div>
			<?php if ( $is_admin ) : ?>
			<div class="hmo-detail-stat">
				<span class="hmo-detail-stat__label">Marketer</span>
				<span class="hmo-detail-stat__value"><?php echo esc_html( $marketer_name ?: ( $ops ? $ops->assigned_marketer_name : '' ) ?: '—' ); ?></span>
			</div>
			<?php endif; ?>
			<div class="hmo-detail-stat">
				<span class="hmo-detail-stat__label">Event Date</span>
				<span class="hmo-detail-stat__value"><?php
				if ( $event->eve_start ) {
					$ts_start = strtotime( $event->eve_start );
					$ts_end   = ( ! empty( $event->eve_end ) && $event->eve_end !== $event->eve_start )
						? strtotime( $event->eve_end ) : null;
					if ( $ts_end ) {
						// Same month+year: "Mar 26-27, 2026". Different month: "Mar 31 – Apr 1, 2026".
						if ( date( 'MY', $ts_start ) === date( 'MY', $ts_end ) ) {
							echo esc_html( date_i18n( 'M j', $ts_start ) . '–' . date_i18n( 'j, Y', $ts_end ) );
						} else {
							echo esc_html( date_i18n( 'M j', $ts_start ) . ' – ' . date_i18n( 'M j, Y', $ts_end ) );
						}
					} else {
						echo esc_html( date_i18n( 'M j, Y', $ts_start ) );
					}
				} else {
					echo '—';
				}
				?></span>
			</div>
			<div class="hmo-detail-stat">
				<span class="hmo-detail-stat__label">Registrations</span>
				<span class="hmo-detail-stat__value">
					<?php echo (int) $reg_count; ?> /
					<?php if ( $is_admin && ! $is_past_event ) : ?>
						<span class="hmo-goal-wrap" data-event-id="<?php echo (int) $event->eve_id; ?>">
							<input type="number" class="hmo-goal-input" min="1"
								value="<?php echo (int) $goal; ?>"
								style="width:64px;padding:2px 4px;font-size:inherit;text-align:center;">
							<button class="hostlinks-btn hmo-goal-save" style="padding:2px 8px;font-size:12px;">Save</button>
							<span class="hmo-goal-status" style="font-size:12px;margin-left:4px;"></span>
						</span>
					<?php else : ?>
						<span><?php echo (int) $goal; ?></span>
						<?php if ( $is_admin && $is_past_event ) : ?>
							<small style="font-size:11px;opacity:.75;">(locked — past event)</small>
						<?php endif; ?>
					<?php endif; ?>
				</span>
			</div>
			<div class="hmo-detail-stat">
				<span class="hmo-detail-stat__label">Open Tasks</span>
				<span class="hmo-detail-stat__value" id="hmo-header-open-tasks"><?php echo $ops ? (int) $ops->open_task_count : 0; ?></span>
			</div>
			<!-- Stage selector on the right (admins only; auto-saves on change) -->
			<?php if ( $is_admin ) : ?>
			<div class="hmo-detail-stat hmo-detail-stat--stage-selector">
				<span class="hmo-detail-stat__label">Current Stage <span class="hmo-inline-status" style="font-weight:400;font-size:0.75rem;margin-left:4px;opacity:.85;"></span></span>
				<select class="hmo-stage-select">
					<?php $stage_num = 1; foreach ( HMO_Checklist_Templates::get_stage_order() as $s ) : ?>
						<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $stage, $s ); ?>>
							<?php echo esc_html( $stage_num . '. ' . ucwords( str_replace( '_', ' ', $s ) ) ); ?>
						</option>
					<?php $stage_num++; endforeach; ?>
				</select>
			</div>
			<?php else : ?>
			<div class="hmo-detail-stat">
				<span class="hmo-detail-stat__label">Current Stage</span>
				<span class="hmo-detail-stat__value"><?php
					$stage_order = HMO_Checklist_Templates::get_stage_order();
					$stage_pos   = array_search( $stage, $stage_order, true );
					echo esc_html( ( $stage_pos !== false ? ( $stage_pos + 1 ) . '. ' : '' ) . ucwords( str_replace( '_', ' ', $stage ) ) );
				?></span>
			</div>
			<?php endif; ?>
		</div>

		<!-- Checklist + Future Feature (two-column) -->
		<div class="hmo-detail-two-col">
		<div class="hmo-detail-panel hmo-detail-col-main">
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
							$is_complete  = $task->status === 'complete';
							$cb_id        = 'hmo-task-cb-' . (int) $task->id;
							$completed_by = '';
							if ( $is_complete && $task->completed_by_user_id ) {
								$u = get_userdata( (int) $task->completed_by_user_id );
								if ( $u ) { $completed_by = $u->display_name; }
							}
						?>
						<div class="hmo-task <?php echo $is_complete ? 'hmo-task--complete' : ''; ?>"
							data-task-id="<?php echo (int) $task->id; ?>">
							<div class="hmo-task__check-row">
								<input type="checkbox"
									id="<?php echo esc_attr( $cb_id ); ?>"
									class="hmo-task-toggle"
									data-task-id="<?php echo (int) $task->id; ?>"
									<?php checked( $is_complete ); ?>>
								<label for="<?php echo esc_attr( $cb_id ); ?>" class="hmo-task__label">
									<?php echo esc_html( $task->task_label ); ?>
								</label>
								<span class="hmo-task__saving" style="display:none;">&#8987;</span>
							</div>
							<?php if ( $task->task_description ) : ?>
								<div class="hmo-task__desc"><?php echo esc_html( $task->task_description ); ?></div>
							<?php endif; ?>
							<div class="hmo-task__completed-by"<?php echo ( $is_complete && $task->completed_at ) ? '' : ' style="display:none;"'; ?>>
								<?php if ( $is_complete && $task->completed_at ) : ?>
								Completed <?php echo esc_html( date_i18n( 'M j', strtotime( $task->completed_at ) ) ); ?>
								<?php if ( $completed_by ) : ?> by <?php echo esc_html( $completed_by ); ?><?php endif; ?>
								<?php endif; ?>
							</div>
						<?php if ( ! empty( $task->template_subtasks ) ) : ?>
						<ul class="hmo-task__subtasks">
							<?php foreach ( $task->template_subtasks as $sub ) : ?>
							<li class="hmo-task__subtask">
								<span class="hmo-task__subtask-bullet">&#8227;</span>
								<?php echo esc_html( $sub->task_label ); ?>
								<?php if ( $sub->task_description ) : ?>
								<span class="hmo-task__subtask-desc"><?php echo esc_html( $sub->task_description ); ?></span>
								<?php endif; ?>
							</li>
							<?php endforeach; ?>
						</ul>
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
		</div><!-- .hmo-detail-col-main -->

		<!-- Right column: stacked panels -->
		<div class="hmo-detail-col-side-wrap">

		<!-- Right column: Call List + Tools (side by side) -->
		<div class="hmo-side-row">

		<!-- Right column: Call List -->
		<?php
		$_call_url = $ops ? trim( $ops->call_list_url ) : '';
		$_call_set = ! empty( $_call_url );
		?>
		<div class="hmo-detail-panel hmo-detail-col-side hmo-call-list-card"
			data-event-id="<?php echo (int) $event->eve_id; ?>">
			<div class="hmo-call-list-header">
				<h2 class="hmo-panel-title hmo-panel-title--inline">Call List</h2>
				<a href="<?php echo $_call_set ? esc_url( $_call_url ) : '#'; ?>"
					target="_blank" rel="noopener"
					class="hmo-hotel-card__link hmo-call-list-view"
					<?php echo $_call_set ? '' : 'style="display:none;"'; ?>
					aria-hidden="<?php echo $_call_set ? 'false' : 'true'; ?>">View &#8599;</a>
			</div>
			<p class="hmo-call-list-status">
				<?php if ( $_call_set ) : ?>
					Set &mdash; click View to open the sheet.
				<?php else : ?>
					Not Set &mdash; click Update to set sheet.
				<?php endif; ?>
			</p>
			<div class="hmo-call-list-edit" style="display:none;">
				<input type="url" class="hmo-call-list-url-input"
					placeholder="https://docs.google.com/spreadsheets/…"
					value="<?php echo esc_attr( $_call_url ); ?>">
				<div class="hmo-call-list-edit-actions">
					<button class="hostlinks-btn hostlinks-btn--active hmo-call-list-save">Save</button>
					<button class="hostlinks-btn hmo-call-list-cancel">Cancel</button>
					<span class="hmo-call-list-save-status"></span>
				</div>
			</div>
			<div class="hmo-call-list-footer">
				<button class="hostlinks-btn hmo-call-list-update">Update</button>
			</div>
		</div>

		<!-- Right column: Tools -->
		<?php
		$_tools = (array) get_option( 'hmo_tools_links', array() );
		?>
		<div class="hmo-detail-panel hmo-detail-col-side hmo-tools-card">
			<div class="hmo-tools-card__header">
				<h2 class="hmo-panel-title hmo-panel-title--inline">Tools</h2>
			</div>
			<?php if ( empty( $_tools ) ) : ?>
			<p class="hmo-tools-card__empty">No tools configured.</p>
			<?php else : ?>
			<ul class="hmo-tools-list">
				<?php foreach ( $_tools as $_tool ) :
					$_t_url      = esc_url( $_tool['url'] ?? '' );
					$_t_name     = esc_html( $_tool['name'] ?? '' );
					$_t_icon_url = esc_url( $_tool['icon'] ?? '' );
					if ( ! $_t_url || ! $_t_name ) { continue; }
				?>
				<li class="hmo-tools-list__item">
					<a href="<?php echo $_t_url; ?>" target="_blank" rel="noopener"
						class="hmo-tools-list__link">
						<?php if ( $_t_icon_url ) : ?>
						<img src="<?php echo $_t_icon_url; ?>" class="hmo-tools-list__icon" alt="" aria-hidden="true">
						<?php endif; ?>
						<span class="hmo-tools-list__name"><?php echo $_t_name; ?></span>
					</a>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
		</div>

		</div><!-- .hmo-side-row -->

		<!-- Right column: Notes -->
		<div class="hmo-detail-panel hmo-detail-col-side hmo-event-notes-panel"
			data-event-id="<?php echo (int) $event->eve_id; ?>">
			<h2 class="hmo-panel-title">Notes</h2>
		<textarea
			id="hmo-event-note"
			class="hmo-event-note-textarea"
			rows="5"
			placeholder="Add a note visible only here…"><?php echo esc_textarea( $ops ? (string) $ops->event_note : '' ); ?></textarea>
			<div class="hmo-event-note-footer">
				<button class="hmo-save-event-note hostlinks-btn">Save</button>
				<span class="hmo-event-note-status"></span>
			</div>
		</div>

		<!-- Right column: Event Insights -->
		<div class="hmo-detail-panel hmo-detail-col-side">
			<h2 class="hmo-panel-title">Insights</h2>

			<?php
			// ── Decode JSON fields ─────────────────────────────────────────
			$contacts = array();
			if ( ! empty( $event->host_contacts ) ) {
				$decoded = json_decode( $event->host_contacts, true );
				if ( is_array( $decoded ) ) { $contacts = $decoded; }
			}
			$hotels = array();
			if ( ! empty( $event->hotels ) ) {
				$decoded = json_decode( $event->hotels, true );
				if ( is_array( $decoded ) ) { $hotels = $decoded; }
			}

			// ── Build address lines ────────────────────────────────────────
			$addr_lines = array_filter( array(
				trim( $event->street_address_1 ?? '' ),
				trim( $event->street_address_2 ?? '' ),
				trim( $event->street_address_3 ?? '' ),
			) );
			$city_line = trim(
				trim( $event->city  ?? '' ) . ', ' .
				trim( $event->state ?? '' ) . ' ' .
				trim( $event->zip_code ?? '' ),
				', '
			);
			$has_venue = $event->displayed_as || $event->location_name || $addr_lines || $city_line;
			?>

			<?php if ( $has_venue ) : ?>
			<!-- Venue / Address -->
			<div class="hmo-insights-section">
				<span class="hmo-insights-section__label">Venue</span>
				<address class="hmo-insights-address">
					<?php if ( $event->displayed_as ) : ?>
						<strong><?php echo esc_html( $event->displayed_as ); ?></strong>
					<?php endif; ?>
					<?php if ( $event->location_name ) : ?>
						<span><?php echo esc_html( $event->location_name ); ?></span>
					<?php endif; ?>
					<?php foreach ( $addr_lines as $line ) : ?>
						<span><?php echo esc_html( $line ); ?></span>
					<?php endforeach; ?>
					<?php if ( $city_line ) : ?>
						<span><?php echo esc_html( $city_line ); ?></span>
					<?php endif; ?>
				</address>
			</div>
			<?php endif; ?>

		<?php if ( ! empty( $event->eve_email_url ) || ! empty( $event->eve_web_url ) ) : ?>
		<!-- Event Links -->
		<div class="hmo-insights-section">
			<span class="hmo-insights-section__label">Event Links</span>
			<div class="hmo-insights-link-grid">
				<?php if ( ! empty( $event->eve_email_url ) ) : ?>
				<div class="hmo-insights-link">
					<a href="<?php echo esc_url( $event->eve_email_url ); ?>" target="_blank" rel="noopener" class="hmo-insights-link__url">
						Event Announcement <span aria-hidden="true">&#8599;</span>
					</a>
					<span class="hmo-insights-link__hint">Email &amp; PDF &mdash; Copy or Print &rarr; Save as PDF</span>
				</div>
				<?php endif; ?>
				<?php if ( ! empty( $event->eve_web_url ) ) : ?>
				<div class="hmo-insights-link">
					<a href="<?php echo esc_url( $event->eve_web_url ); ?>" target="_blank" rel="noopener" class="hmo-insights-link__url">
						Event Info &amp; Registration <span aria-hidden="true">&#8599;</span>
					</a>
					<span class="hmo-insights-link__hint">Customer registration &amp; free seat reservations</span>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $contacts ) ) : ?>
		<!-- Host Contacts -->
		<div class="hmo-insights-section">
			<span class="hmo-insights-section__label">Event Contacts</span>
			<div class="hmo-contact-cards-grid">
			<?php foreach ( $contacts as $c ) :
					$c_name    = trim( $c['name']   ?? '' );
					$c_agency  = trim( $c['agency'] ?? '' );
					$c_email   = trim( $c['email']  ?? '' );
					$c_phone   = trim( $c['phone']  ?? '' );
					$c_phone2  = trim( $c['phone2'] ?? '' );
					$c_public  = ! empty( $c['include_in_email'] );
					$c_dnl1    = ! empty( $c['dnl_phone'] );
					$c_dnl2    = ! empty( $c['dnl_phone2'] );
					if ( ! $c_name && ! $c_agency ) { continue; }
				?>
				<div class="hmo-contact-card">
					<div class="hmo-contact-card__name">
						<?php echo esc_html( $c_name ); ?>
						<?php if ( ! $c_public ) : ?>
							<span class="hmo-dnp-badge">DO NOT PUBLISH</span>
						<?php endif; ?>
					</div>
					<?php if ( $c_agency ) : ?>
						<div class="hmo-contact-card__agency"><?php echo esc_html( $c_agency ); ?></div>
					<?php endif; ?>
					<?php if ( $c_email ) : ?>
						<div class="hmo-contact-card__row">
							<a href="mailto:<?php echo esc_attr( $c_email ); ?>"><?php echo esc_html( $c_email ); ?></a>
						</div>
					<?php endif; ?>
					<?php if ( $c_phone ) : ?>
					<div class="hmo-contact-card__row hmo-contact-card__phones">
						<?php if ( $c_dnl1 ) : ?>
							<span class="hmo-dnp-badge">DO NOT PUBLISH</span>
						<?php else : ?>
							<span><?php echo esc_html( $c_phone ); ?></span>
						<?php endif; ?>
						<?php if ( $c_phone2 ) : ?>
							<span class="hmo-contact-card__phone-sep">&bull;</span>
							<?php if ( $c_dnl2 ) : ?>
								<span class="hmo-dnp-badge">DO NOT PUBLISH</span>
							<?php else : ?>
								<span><?php echo esc_html( $c_phone2 ); ?></span>
							<?php endif; ?>
						<?php endif; ?>
					</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $hotels ) ) : ?>
		<!-- Recommended Hotels -->
		<div class="hmo-insights-section">
			<span class="hmo-insights-section__label">Recommended Hotels</span>
			<div class="hmo-hotel-cards-grid">
			<?php foreach ( $hotels as $h ) :
					$h_name    = trim( $h['name']    ?? '' );
					$h_phone   = trim( $h['phone']   ?? '' );
					$h_address = trim( $h['address'] ?? '' );
					$h_url     = trim( $h['url']     ?? '' );
					if ( ! $h_name ) { continue; }
				?>
				<div class="hmo-hotel-card">
					<div class="hmo-hotel-card__name">
						<?php echo esc_html( $h_name ); ?>
						<?php if ( $h_url ) : ?>
							<a href="<?php echo esc_url( $h_url ); ?>" target="_blank" rel="noopener" class="hmo-hotel-card__link">View &#8599;</a>
						<?php endif; ?>
					</div>
					<?php if ( $h_phone ) : ?>
						<div class="hmo-hotel-card__row"><?php echo esc_html( $h_phone ); ?></div>
					<?php endif; ?>
					<?php if ( $h_address ) : ?>
						<div class="hmo-hotel-card__row hmo-hotel-card__address"><?php echo esc_html( $h_address ); ?></div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<?php if ( ! $has_venue && empty( $event->eve_email_url ) && empty( $event->eve_web_url ) && empty( $contacts ) && empty( $hotels ) ) : ?>
		<p class="hmo-notice" style="margin-top:0.5rem;">No event details available yet.</p>
		<?php endif; ?>

		</div><!-- right column: Insights -->

		</div><!-- .hmo-detail-col-side-wrap -->

	</div><!-- .hmo-detail-two-col -->

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
