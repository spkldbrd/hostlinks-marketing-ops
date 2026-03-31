<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// Variables available: $stages (array), $stage_tasks (array keyed by stage_key)
$first_key   = ! empty( $stages ) ? $stages[0]['stage_key'] : '';
$stage_count = count( $stages );
?>
<div class="hostlinks-page hmo-frontend hmo-te">

	<div class="hmo-dashboard-header">
		<span class="hmo-dashboard-header__title">Task Template Editor</span>
		<nav class="hmo-header-nav">
			<?php $__ops_url = HMO_Page_URLs::get_dashboard_selector() ?: HMO_Page_URLs::get_dashboard(); ?>
			<?php if ( $__ops_url ) : ?>
			<a href="<?php echo esc_url( $__ops_url ); ?>" class="hmo-header-nav__link">
				&larr; Return to Marketing Ops
			</a>
			<?php endif; ?>
		</nav>
	</div>

	<p class="hmo-te-intro">
		Changes here affect new event checklists. Existing per-event tasks are not retroactively changed.
	</p>

	<div class="hmo-te-layout">

		<!-- ══ LEFT: Stage list ══ -->
		<div class="hmo-te-sidebar">

			<div class="hmo-te-sidebar__header">
				<span class="hmo-te-sidebar__title">Stages</span>
				<button type="button" class="hmo-te-btn hmo-te-btn--add hmo-te-btn--sm"
					id="hmo-te-show-add-stage" data-action="show-add-stage">+ Add Stage</button>
			</div>

			<!-- Add Stage inline form -->
			<div class="hmo-te-add-stage-form hmo-te-sidebar__add-form" id="hmo-te-add-stage-form" style="display:none;">
				<input type="text" class="hmo-te-input hmo-te-new-stage-label" id="hmo-te-new-stage-label"
					placeholder="Stage name *">
				<div class="hmo-te-edit-actions">
					<button type="button" class="hmo-te-btn hmo-te-btn--save" data-action="save-new-stage">Add</button>
					<button type="button" class="hmo-te-btn hmo-te-btn--cancel" data-action="cancel-add-stage">Cancel</button>
					<span class="hmo-te-status" id="hmo-te-add-stage-status" style="display:none;"></span>
				</div>
			</div>

			<ul class="hmo-te-stage-list" id="hmo-te-stage-list">
				<?php foreach ( $stages as $stage ) :
					$key        = $stage['stage_key'];
					$label      = $stage['stage_label'];
					$task_count = count( $stage_tasks[ $key ] ?? array() );
				?>
				<li class="hmo-te-stage-item <?php echo $key === $first_key ? 'hmo-te-stage-item--active' : ''; ?>"
					data-stage="<?php echo esc_attr( $key ); ?>">

					<span class="hmo-te-stage-item__select" data-action="select-stage">
						<span class="hmo-te-stage-item__name"><?php echo esc_html( $label ); ?></span>
						<span class="hmo-te-stage-item__count"><?php echo $task_count; ?></span>
					</span>

					<div class="hmo-te-stage-item__actions">
						<button type="button" class="hmo-te-btn hmo-te-btn--edit hmo-te-btn--icon"
							data-action="rename-stage" title="Rename stage">&#9998;</button>
						<button type="button" class="hmo-te-btn hmo-te-btn--delete hmo-te-btn--icon"
							data-action="delete-stage"
							data-stage-key="<?php echo esc_attr( $key ); ?>"
							data-stage-label="<?php echo esc_attr( $label ); ?>"
							data-task-count="<?php echo $task_count; ?>"
							title="Delete stage">&#10005;</button>
					</div>

					<!-- Inline rename form -->
					<div class="hmo-te-stage__rename-form" style="display:none;"
						data-stage-key="<?php echo esc_attr( $key ); ?>">
						<input type="text" class="hmo-te-input hmo-te-stage-new-label"
							value="<?php echo esc_attr( $label ); ?>" placeholder="Stage name *">
						<div class="hmo-te-edit-actions">
							<button type="button" class="hmo-te-btn hmo-te-btn--save"
								data-action="save-stage-rename"
								data-stage-key="<?php echo esc_attr( $key ); ?>">Save</button>
							<button type="button" class="hmo-te-btn hmo-te-btn--cancel"
								data-action="cancel-stage-rename">Cancel</button>
							<span class="hmo-te-status" style="display:none;"></span>
						</div>
					</div>

				</li>
				<?php endforeach; ?>
			</ul>

			<?php if ( $stage_count >= 6 ) : ?>
			<p class="hmo-te-stage-hint">
				<em>You have <?php echo $stage_count; ?> stages — we recommend keeping it to 6 or fewer.</em>
			</p>
			<?php endif; ?>

		</div><!-- /.hmo-te-sidebar -->

		<!-- ══ RIGHT: Task panel ══ -->
		<div class="hmo-te-content" id="hmo-te-content">

			<?php if ( empty( $stages ) ) : ?>
			<div class="hmo-te-empty">
				<p>No stages yet. Add a stage on the left to get started.</p>
			</div>
			<?php endif; ?>

			<?php foreach ( $stages as $stage ) :
				$key   = $stage['stage_key'];
				$label = $stage['stage_label'];
				$tasks = $stage_tasks[ $key ] ?? array();
			?>
			<div class="hmo-te-panel <?php echo $key === $first_key ? 'hmo-te-panel--active' : ''; ?>"
				id="hmo-te-panel-<?php echo esc_attr( $key ); ?>"
				data-stage="<?php echo esc_attr( $key ); ?>">

				<!-- Panel header -->
				<div class="hmo-te-panel__header">
					<span class="hmo-te-panel__title"><?php echo esc_html( $label ); ?></span>
					<button type="button" class="hmo-te-btn hmo-te-btn--add"
						data-action="show-add-task"
						data-stage="<?php echo esc_attr( $key ); ?>">+ Add Task</button>
				</div>

				<!-- Add task inline form -->
				<div class="hmo-te-add-task-form" style="display:none;" data-stage="<?php echo esc_attr( $key ); ?>">
					<input type="text" class="hmo-te-input hmo-te-new-label" placeholder="Task label *">
					<textarea class="hmo-te-textarea hmo-te-new-desc" placeholder="Description (optional)"></textarea>
					<div class="hmo-te-edit-actions">
						<button type="button" class="hmo-te-btn hmo-te-btn--save" data-action="save-new-task">Add Task</button>
						<button type="button" class="hmo-te-btn hmo-te-btn--cancel" data-action="cancel-add-task">Cancel</button>
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
							<span class="hmo-te-sub-badge hmo-te-sub-badge--collapsed">
								<?php echo $sub_count; ?> sub-task<?php echo $sub_count !== 1 ? 's' : ''; ?>
							</span>
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
								<input type="text" class="hmo-te-input hmo-te-edit-label"
									value="<?php echo esc_attr( $task->task_label ); ?>" placeholder="Task label">
								<textarea class="hmo-te-textarea hmo-te-edit-desc"
									placeholder="Description (optional)"><?php echo esc_textarea( $task->task_description ); ?></textarea>
								<div class="hmo-te-edit-actions">
									<button type="button" class="hmo-te-btn hmo-te-btn--save"
										data-action="save-task-edit"
										data-id="<?php echo (int) $task->id; ?>">Save</button>
									<button type="button" class="hmo-te-btn hmo-te-btn--cancel"
										data-action="cancel-edit">Cancel</button>
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
											<button type="button" class="hmo-te-btn hmo-te-btn--delete"
												data-action="delete-task"
												data-id="<?php echo (int) $sub->id; ?>">Delete</button>
										</div>
									</div>
									<div class="hmo-te-task__edit" style="display:none;">
										<input type="text" class="hmo-te-input hmo-te-edit-label"
											value="<?php echo esc_attr( $sub->task_label ); ?>"
											placeholder="Sub-task label">
										<textarea class="hmo-te-textarea hmo-te-edit-desc"
											placeholder="Description (optional)"><?php echo esc_textarea( $sub->task_description ); ?></textarea>
										<div class="hmo-te-edit-actions">
											<button type="button" class="hmo-te-btn hmo-te-btn--save"
												data-action="save-task-edit"
												data-id="<?php echo (int) $sub->id; ?>">Save</button>
											<button type="button" class="hmo-te-btn hmo-te-btn--cancel"
												data-action="cancel-edit">Cancel</button>
											<span class="hmo-te-status" style="display:none;"></span>
										</div>
									</div>
								</li>
								<?php endforeach; ?>
							</ul>

							<div class="hmo-te-add-subtask-form"
								data-parent="<?php echo (int) $task->id; ?>"
								data-stage="<?php echo esc_attr( $key ); ?>"
								style="display:none;">
								<input type="text" class="hmo-te-input hmo-te-new-label" placeholder="Sub-task label *">
								<textarea class="hmo-te-textarea hmo-te-new-desc" placeholder="Description (optional)"></textarea>
								<div class="hmo-te-edit-actions">
									<button type="button" class="hmo-te-btn hmo-te-btn--save"
										data-action="save-new-subtask">Add Sub-task</button>
									<button type="button" class="hmo-te-btn hmo-te-btn--cancel"
										data-action="cancel-add-subtask">Cancel</button>
									<span class="hmo-te-status" style="display:none;"></span>
								</div>
							</div>
						</div><!-- /.hmo-te-task__body -->

					</li>
					<?php endforeach; ?>
				</ul>

				<?php if ( empty( $tasks ) ) : ?>
				<p class="hmo-te-empty-tasks">No tasks in this stage yet. Click <strong>+ Add Task</strong> above to add one.</p>
				<?php endif; ?>

			</div><!-- /.hmo-te-panel -->
			<?php endforeach; ?>

		</div><!-- /.hmo-te-content -->
	</div><!-- /.hmo-te-layout -->

</div>
