<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// Variables available: $stages (array), $stage_tasks (array keyed by stage_key)
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
	</p>

	<?php foreach ( $stages as $stage ) :
		$key    = $stage['stage_key'];
		$label  = $stage['stage_label'];
		$tasks  = $stage_tasks[ $key ] ?? array();
	?>
	<div class="hmo-detail-panel hmo-te-stage" data-stage="<?php echo esc_attr( $key ); ?>">

		<h3 class="hmo-panel-title hmo-te-stage__title"><?php echo esc_html( $label ); ?></h3>

		<ul class="hmo-te-task-list" id="hmo-te-list-<?php echo esc_attr( $key ); ?>">
			<?php foreach ( $tasks as $task ) : ?>
			<li class="hmo-te-task" data-id="<?php echo (int) $task->id; ?>">

				<!-- Task view row -->
				<div class="hmo-te-task__row hmo-te-task__view">
					<span class="hmo-te-drag-handle" title="Drag to reorder">&#9783;</span>
					<div class="hmo-te-task__info">
						<strong class="hmo-te-task__label"><?php echo esc_html( $task->task_label ); ?></strong>
						<?php if ( $task->task_description ) : ?>
						<span class="hmo-te-task__desc"><?php echo esc_html( $task->task_description ); ?></span>
						<?php endif; ?>
					</div>
					<div class="hmo-te-task__actions">
						<button type="button" class="hmo-te-btn hmo-te-btn--edit" data-action="edit-task">Edit</button>
						<button type="button" class="hmo-te-btn hmo-te-btn--subtask" data-action="show-add-subtask">+ Sub-task</button>
						<button type="button" class="hmo-te-btn hmo-te-btn--delete" data-action="delete-task"
							data-id="<?php echo (int) $task->id; ?>">Delete</button>
					</div>
				</div>

				<!-- Task edit form (hidden by default) -->
				<div class="hmo-te-task__edit" style="display:none;">
					<input type="text" class="hmo-te-input hmo-te-edit-label" value="<?php echo esc_attr( $task->task_label ); ?>" placeholder="Task label">
					<textarea class="hmo-te-textarea hmo-te-edit-desc" placeholder="Description (optional)"><?php echo esc_textarea( $task->task_description ); ?></textarea>
					<div class="hmo-te-edit-actions">
						<button type="button" class="hmo-te-btn hmo-te-btn--save" data-action="save-task-edit" data-id="<?php echo (int) $task->id; ?>">Save</button>
						<button type="button" class="hmo-te-btn hmo-te-btn--cancel" data-action="cancel-edit">Cancel</button>
						<span class="hmo-te-status" style="display:none;"></span>
					</div>
				</div>

				<!-- Sub-tasks list -->
				<?php if ( ! empty( $task->subtasks ) ) : ?>
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
				<?php else : ?>
				<ul class="hmo-te-subtask-list" data-parent="<?php echo (int) $task->id; ?>"></ul>
				<?php endif; ?>

				<!-- Add sub-task form (hidden) -->
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

			</li>
			<?php endforeach; ?>
		</ul>

		<!-- Add task form -->
		<div class="hmo-te-add-task-form" style="display:none;"
			data-stage="<?php echo esc_attr( $key ); ?>">
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

	</div>
	<?php endforeach; ?>

</div>
