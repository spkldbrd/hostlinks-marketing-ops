<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// Variables available: $stages (array), $stage_tasks (array keyed by stage_key)
$stage_count = count( $stages );
?>
<div class="hostlinks-page hmo-frontend hmo-te">

	<div class="hmo-dashboard-header">
		<span class="hmo-dashboard-header__title">Task Template Editor</span>
		<span class="hmo-dashboard-header__stats">
			<span>Edit the master checklist tasks applied to all events</span>
		</span>
	</div>

	<p class="hmo-te-intro">
		Changes here affect new event checklists. Existing per-event tasks are not retroactively changed.
		Click a stage or task row to expand it.
	</p>

	<?php foreach ( $stages as $stage ) :
		$key       = $stage['stage_key'];
		$label     = $stage['stage_label'];
		$tasks     = $stage_tasks[ $key ] ?? array();
		$task_count = count( $tasks );
	?>
	<div class="hmo-detail-panel hmo-te-stage" data-stage="<?php echo esc_attr( $key ); ?>">

		<!-- ── Stage header (always visible, click left area to toggle) ── -->
		<div class="hmo-te-stage__header">
			<button type="button" class="hmo-te-stage-toggle" data-action="toggle-stage"
				aria-expanded="false" title="Expand / collapse stage">
				<span class="hmo-te-stage__arrow">&#9654;</span>
			</button>
			<span class="hmo-te-stage__title-wrap hmo-te-stage__toggle-zone" data-action="toggle-stage">
				<span class="hmo-te-stage__title"><?php echo esc_html( $label ); ?></span>
				<span class="hmo-te-stage__count"><?php echo $task_count; ?> task<?php echo $task_count !== 1 ? 's' : ''; ?></span>
			</span>
			<div class="hmo-te-stage__actions">
				<button type="button" class="hmo-te-btn hmo-te-btn--edit" data-action="rename-stage">Rename</button>
				<button type="button" class="hmo-te-btn hmo-te-btn--delete" data-action="delete-stage"
					data-stage-key="<?php echo esc_attr( $key ); ?>"
					data-stage-label="<?php echo esc_attr( $label ); ?>"
					data-task-count="<?php echo $task_count; ?>">Delete Stage</button>
			</div>
		</div>

		<!-- ── Collapsible stage body ── -->
		<div class="hmo-te-stage__body">

			<!-- Rename form (hidden by default) -->
			<div class="hmo-te-stage__rename-form" style="display:none;"
				data-stage-key="<?php echo esc_attr( $key ); ?>">
				<input type="text" class="hmo-te-input hmo-te-stage-new-label"
					value="<?php echo esc_attr( $label ); ?>" placeholder="Stage name *">
				<div class="hmo-te-edit-actions">
					<button type="button" class="hmo-te-btn hmo-te-btn--save"
						data-action="save-stage-rename"
						data-stage-key="<?php echo esc_attr( $key ); ?>">Save Name</button>
					<button type="button" class="hmo-te-btn hmo-te-btn--cancel"
						data-action="cancel-stage-rename">Cancel</button>
					<span class="hmo-te-status" style="display:none;"></span>
				</div>
			</div>

			<!-- Task list -->
			<ul class="hmo-te-task-list" id="hmo-te-list-<?php echo esc_attr( $key ); ?>">
				<?php foreach ( $tasks as $task ) :
					$sub_count = count( $task->subtasks ?? array() );
				?>
				<li class="hmo-te-task" data-id="<?php echo (int) $task->id; ?>">

					<div class="hmo-te-task__row hmo-te-task__view">
						<button type="button" class="hmo-te-toggle" data-action="toggle-task"
							aria-expanded="false" title="Expand / collapse">
							<span class="hmo-te-arrow">&#9654;</span>
						</button>
						<span class="hmo-te-drag-handle" title="Drag to reorder">&#9783;</span>
						<div class="hmo-te-task__info hmo-te-task__toggle-zone" data-action="toggle-task">
							<strong class="hmo-te-task__label"><?php echo esc_html( $task->task_label ); ?></strong>
							<?php if ( $task->task_description ) : ?>
							<span class="hmo-te-task__desc"><?php echo esc_html( $task->task_description ); ?></span>
							<?php endif; ?>
						</div>
						<?php if ( $sub_count ) : ?>
						<span class="hmo-te-sub-badge hmo-te-sub-badge--collapsed"><?php echo $sub_count; ?> sub-task<?php echo $sub_count !== 1 ? 's' : ''; ?></span>
						<?php endif; ?>
						<div class="hmo-te-task__actions">
							<button type="button" class="hmo-te-btn hmo-te-btn--edit" data-action="edit-task">Edit</button>
							<button type="button" class="hmo-te-btn hmo-te-btn--subtask" data-action="show-add-subtask">+ Sub-task</button>
							<button type="button" class="hmo-te-btn hmo-te-btn--delete" data-action="delete-task"
								data-id="<?php echo (int) $task->id; ?>">Delete</button>
						</div>
					</div>

					<div class="hmo-te-task__body">
						<div class="hmo-te-task__edit" style="display:none;">
							<input type="text" class="hmo-te-input hmo-te-edit-label" value="<?php echo esc_attr( $task->task_label ); ?>" placeholder="Task label">
							<textarea class="hmo-te-textarea hmo-te-edit-desc" placeholder="Description (optional)"><?php echo esc_textarea( $task->task_description ); ?></textarea>
							<div class="hmo-te-edit-actions">
								<button type="button" class="hmo-te-btn hmo-te-btn--save" data-action="save-task-edit" data-id="<?php echo (int) $task->id; ?>">Save</button>
								<button type="button" class="hmo-te-btn hmo-te-btn--cancel" data-action="cancel-edit">Cancel</button>
								<span class="hmo-te-status" style="display:none;"></span>
							</div>
						</div>

						<ul class="hmo-te-subtask-list" data-parent="<?php echo (int) $task->id; ?>">
							<?php foreach ( $task->subtasks as $sub ) : ?>
							<li class="hmo-te-subtask" data-id="<?php echo (int) $sub->id; ?>">
								<div class="hmo-te-subtask__row hmo-te-task__view">
									<span class="hmo-te-drag-handle">&#9783;</span>
									<div class="hmo-te-task__info">
										<span class="hmo-te-task__label"><?php echo esc_html( $sub->task_label ); ?></span>
										<?php if ( $sub->task_description ) : ?>
										<span class="hmo-te-task__desc"><?php echo esc_html( $sub->task_description ); ?></span>
										<?php endif; ?>
									</div>
									<div class="hmo-te-task__actions">
										<button type="button" class="hmo-te-btn hmo-te-btn--edit" data-action="edit-task">Edit</button>
										<button type="button" class="hmo-te-btn hmo-te-btn--delete" data-action="delete-task"
											data-id="<?php echo (int) $sub->id; ?>">Delete</button>
									</div>
								</div>
								<div class="hmo-te-task__edit" style="display:none;">
									<input type="text" class="hmo-te-input hmo-te-edit-label" value="<?php echo esc_attr( $sub->task_label ); ?>" placeholder="Sub-task label">
									<textarea class="hmo-te-textarea hmo-te-edit-desc" placeholder="Description (optional)"><?php echo esc_textarea( $sub->task_description ); ?></textarea>
									<div class="hmo-te-edit-actions">
										<button type="button" class="hmo-te-btn hmo-te-btn--save" data-action="save-task-edit" data-id="<?php echo (int) $sub->id; ?>">Save</button>
										<button type="button" class="hmo-te-btn hmo-te-btn--cancel" data-action="cancel-edit">Cancel</button>
										<span class="hmo-te-status" style="display:none;"></span>
									</div>
								</div>
							</li>
							<?php endforeach; ?>
						</ul>

						<div class="hmo-te-add-subtask-form" data-parent="<?php echo (int) $task->id; ?>"
							data-stage="<?php echo esc_attr( $key ); ?>" style="display:none;">
							<input type="text" class="hmo-te-input hmo-te-new-label" placeholder="Sub-task label *">
							<textarea class="hmo-te-textarea hmo-te-new-desc" placeholder="Description (optional)"></textarea>
							<div class="hmo-te-edit-actions">
								<button type="button" class="hmo-te-btn hmo-te-btn--save" data-action="save-new-subtask">Add Sub-task</button>
								<button type="button" class="hmo-te-btn hmo-te-btn--cancel" data-action="cancel-add-subtask">Cancel</button>
								<span class="hmo-te-status" style="display:none;"></span>
							</div>
						</div>
					</div><!-- /.hmo-te-task__body -->

				</li>
				<?php endforeach; ?>
			</ul>

			<!-- Add task form -->
			<div class="hmo-te-add-task-form" style="display:none;" data-stage="<?php echo esc_attr( $key ); ?>">
				<input type="text" class="hmo-te-input hmo-te-new-label" placeholder="Task label *">
				<textarea class="hmo-te-textarea hmo-te-new-desc" placeholder="Description (optional)"></textarea>
				<div class="hmo-te-edit-actions">
					<button type="button" class="hmo-te-btn hmo-te-btn--save" data-action="save-new-task">Add Task</button>
					<button type="button" class="hmo-te-btn hmo-te-btn--cancel" data-action="cancel-add-task">Cancel</button>
					<span class="hmo-te-status" style="display:none;"></span>
				</div>
			</div>

			<div class="hmo-te-stage__footer">
				<button type="button" class="hmo-te-btn hmo-te-btn--add"
					data-action="show-add-task"
					data-stage="<?php echo esc_attr( $key ); ?>">+ Add Task to <?php echo esc_html( $label ); ?></button>
			</div>

		</div><!-- /.hmo-te-stage__body -->

	</div><!-- /.hmo-te-stage -->
	<?php endforeach; ?>

	<!-- Page footer: add new stage -->
	<div class="hmo-te-page-footer" id="hmo-te-page-footer">
		<p class="hmo-te-stage-hint" id="hmo-te-stage-hint">
			<?php if ( $stage_count >= 6 ) : ?>
				You have <strong><?php echo $stage_count; ?></strong> stages. We recommend keeping it to 6 or fewer for a manageable checklist — but you can add more if needed.
			<?php else : ?>
				Recommended maximum: <strong>6 stages</strong>. You currently have <?php echo $stage_count; ?>.
			<?php endif; ?>
		</p>

		<div class="hmo-te-add-stage-form" id="hmo-te-add-stage-form" style="display:none;">
			<input type="text" class="hmo-te-input hmo-te-new-stage-label" id="hmo-te-new-stage-label"
				placeholder="Stage name *" style="max-width:300px;">
			<div class="hmo-te-edit-actions">
				<button type="button" class="hmo-te-btn hmo-te-btn--save" data-action="save-new-stage">Add Stage</button>
				<button type="button" class="hmo-te-btn hmo-te-btn--cancel" data-action="cancel-add-stage">Cancel</button>
				<span class="hmo-te-status" id="hmo-te-add-stage-status" style="display:none;"></span>
			</div>
		</div>

		<button type="button" class="hmo-te-btn hmo-te-btn--add" id="hmo-te-show-add-stage"
			data-action="show-add-stage">+ Add Stage</button>
	</div>

</div>
