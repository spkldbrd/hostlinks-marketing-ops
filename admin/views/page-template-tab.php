<?php
/**
 * Marketing Ops Settings — "Page Template" tab content.
 * Included from admin/views/settings.php when $active_tab === 'page-template'.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sections   = HMO_Page_Template::get_sections();
$types      = HMO_Page_Template::get_event_types();
$visibility = HMO_Page_Template::get_section_visibility_map();
$nonce_val  = wp_create_nonce( 'hmo_page_template' );

// Active type context from URL — defaults to 'default'.
$active_type = sanitize_key( $_GET['tmpl_type'] ?? 'default' );
if ( ! array_key_exists( $active_type, $types ) ) {
	$active_type = 'default';
}
$is_default = ( $active_type === 'default' );

// Base URL for type tab links.
$base_url = add_query_arg(
	array( 'page' => 'hmo-settings', 'tab' => 'page-template' ),
	admin_url( 'admin.php' )
);
?>

<p style="color:#666;margin-top:0;">
	Edit the boilerplate used when auto-generating GWU marketing pages.
	Each event type can have its own override for any section — leave type-specific sections blank to inherit from <strong>Default</strong>.
	On the <strong>Default</strong> tab, use <strong>Sections on generated pages</strong> to hide entire blocks (heading and body) without clearing template text.
	<strong>Tokens</strong> (e.g. <code>{{DATE_LONG}}</code>) are replaced with live event data at page-creation time.
	<strong>Course Type (In-Person)</strong> and <strong>Course Type (Zoom)</strong> can appear in the main body and/or in the DIVI sidebar via
	<code>[event_course_type]</code> on grantwritingusa.com — see <a href="<?php echo esc_url( admin_url( 'admin.php?page=hmo-settings&tab=page-sync' ) ); ?>">GWU Page Sync</a>.
</p>

<!-- Event-type context tabs -->
<nav class="nav-tab-wrapper" style="margin-bottom:20px;">
	<?php foreach ( $types as $type_key => $type_label ) :
		$tab_url = add_query_arg( 'tmpl_type', $type_key, $base_url );
		$active  = ( $type_key === $active_type ) ? ' nav-tab-active' : '';
	?>
		<a href="<?php echo esc_url( $tab_url ); ?>" class="nav-tab<?php echo $active; ?>">
			<?php echo esc_html( $type_label ); ?>
		</a>
	<?php endforeach; ?>
</nav>

<?php if ( ! $is_default ) : ?>
<div class="notice notice-info inline" style="margin-bottom:20px;">
	<p>
		Sections left <strong>empty</strong> here will use the <strong>Default</strong> template for this event type.
		Only fill in sections that need to differ from the default.
	</p>
</div>
<?php endif; ?>

<form method="post" action="">
	<?php wp_nonce_field( 'hmo_page_template', 'hmo_page_template_nonce' ); ?>
	<input type="hidden" name="hmo_save_page_template" value="1">
	<input type="hidden" name="hmo_tmpl_type" value="<?php echo esc_attr( $active_type ); ?>">

	<?php if ( $is_default ) : ?>
	<div class="hmo-tmpl-section hmo-tmpl-visibility">
		<div class="hmo-tmpl-section__header">
			<span class="hmo-tmpl-section__title">Sections on generated pages</span>
		</div>
		<p class="hmo-tmpl-section__desc">
			Checked sections appear on GWU marketing pages after sync or regenerate. Uncheck to hide a block entirely (template text is kept for when you turn it back on).
		</p>
		<ul class="hmo-tmpl-visibility-list">
			<?php foreach ( $sections as $vis_key => $vis_def ) : ?>
			<li>
				<label>
					<input type="checkbox"
						name="hmo_tmpl_visible[<?php echo esc_attr( $vis_key ); ?>]"
						value="1"
						<?php checked( ! empty( $visibility[ $vis_key ] ) ); ?>>
					<?php echo wp_kses_post( $vis_def['label'] ); ?>
				</label>
			</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php endif; ?>

	<?php foreach ( $sections as $key => $def ) :

		// Default tab: show saved content (or hard-coded default) for editing.
		// Type tab: show only the raw type-specific saved value (empty = no override).
		if ( $is_default ) {
			$content = HMO_Page_Template::get_section_content( $key );
		} else {
			$content = HMO_Page_Template::get_type_raw( $key, $active_type );
		}

		$has_override = ! $is_default && ( $content !== '' );
		$editor_id    = 'hmo_tmpl_' . $active_type . '_' . $key;
	?>
	<div class="hmo-tmpl-section" id="tmpl-section-<?php echo esc_attr( $key ); ?>">
		<div class="hmo-tmpl-section__header">
			<span class="hmo-tmpl-section__title"><?php echo wp_kses_post( $def['label'] ); ?></span>

			<?php if ( ! empty( $def['tokens'] ) ) : ?>
				<span class="hmo-tmpl-section__token-badge">Has tokens</span>
			<?php endif; ?>

			<?php if ( ! $is_default ) : ?>
				<?php if ( $has_override ) : ?>
					<span class="hmo-tmpl-status hmo-tmpl-status--custom">Custom override</span>
				<?php else : ?>
					<span class="hmo-tmpl-status hmo-tmpl-status--default">Using Default</span>
				<?php endif; ?>
			<?php endif; ?>

			<button type="button"
				class="button button-small hmo-tmpl-reset"
				data-key="<?php echo esc_attr( $key ); ?>"
				data-type="<?php echo esc_attr( $active_type ); ?>"
				data-nonce="<?php echo esc_attr( $nonce_val ); ?>"
				style="margin-left:auto;">
				<?php echo $is_default ? 'Reset to Default' : 'Clear Override'; ?>
			</button>
		</div>

		<?php if ( $def['description'] ) : ?>
			<p class="hmo-tmpl-section__desc"><?php echo esc_html( $def['description'] ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $def['tokens'] ) ) : ?>
		<table class="hmo-tmpl-tokens widefat striped" style="margin-bottom:8px;">
			<thead><tr><th>Token</th><th>Replaced with</th></tr></thead>
			<tbody>
			<?php foreach ( $def['tokens'] as $token => $hint ) : ?>
				<tr>
					<td><code><?php echo esc_html( $token ); ?></code></td>
					<td><?php echo wp_kses_post( $hint ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>

		<?php
		wp_editor( $content, $editor_id, array(
			'textarea_name' => 'hmo_tmpl[' . $key . ']',
			'media_buttons' => false,
			'teeny'         => false,
			'tinymce'       => array(
				'toolbar1' => 'bold,italic,underline,separator,link,unlink,separator,bullist,numlist,separator,code,separator,undo,redo',
				'toolbar2' => '',
			),
			'quicktags'     => true,
			'editor_height' => 160,
		) );
		?>
	</div>
	<?php endforeach; ?>

	<p class="submit" style="margin-top:24px;">
		<button type="submit" class="button button-primary">
			Save <?php echo esc_html( $types[ $active_type ] ); ?> Templates
		</button>
		<?php if ( $is_default ) : ?>
		<button type="button" id="hmo-bulk-regen" class="button hmo-bulk-regen-trigger" data-regen-scope="future" style="margin-left:12px;">
			Regenerate All Future Event Pages
		</button>
		<button type="button" id="hmo-bulk-regen-past" class="button hmo-bulk-regen-trigger" data-regen-scope="past">
			Regenerate All Past Event Pages
		</button>
		<?php endif; ?>
	</p>
</form>

<?php if ( $is_default ) : ?>
<!-- Bulk regenerate progress panel (hidden until start) -->
<div id="hmo-bulk-regen-panel" style="display:none;margin-top:10px;padding:12px 16px;border:1px solid #ddd;background:#fff;border-radius:4px;max-width:720px;">
	<div id="hmo-bulk-regen-status" style="margin-bottom:8px;font-weight:600;">Preparing…</div>
	<div style="background:#f0f0f0;border-radius:3px;height:14px;overflow:hidden;">
		<div id="hmo-bulk-regen-bar" style="background:#2271b1;height:100%;width:0;transition:width 0.3s ease;"></div>
	</div>
	<div id="hmo-bulk-regen-counts" style="margin-top:8px;font-size:12px;color:#666;"></div>
	<details id="hmo-bulk-regen-errors-wrap" style="margin-top:8px;display:none;">
		<summary style="cursor:pointer;color:#b32d2e;font-weight:600;">Errors</summary>
		<ul id="hmo-bulk-regen-errors" style="margin:6px 0 0 18px;font-size:12px;color:#b32d2e;"></ul>
	</details>
</div>
<?php endif; ?>

<style>
.hmo-tmpl-section {
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 4px;
	padding: 16px 20px;
	margin-bottom: 20px;
}
.hmo-tmpl-section__header {
	display: flex;
	align-items: center;
	gap: 10px;
	margin-bottom: 6px;
}
.hmo-tmpl-section__title {
	font-weight: 600;
	font-size: 14px;
}
.hmo-tmpl-section__token-badge {
	background: #0073aa;
	color: #fff;
	font-size: 11px;
	padding: 2px 6px;
	border-radius: 3px;
}
.hmo-tmpl-status {
	font-size: 11px;
	padding: 2px 6px;
	border-radius: 3px;
}
.hmo-tmpl-status--custom {
	background: #dff0d8;
	color: #3c763d;
}
.hmo-tmpl-status--default {
	background: #f5f5f5;
	color: #999;
}
.hmo-tmpl-section__desc {
	color: #666;
	font-size: 13px;
	margin: 0 0 8px;
}
.hmo-tmpl-tokens {
	font-size: 13px;
	max-width: 640px;
}
.hmo-tmpl-tokens th {
	font-weight: 600;
}
.hmo-tmpl-visibility-list {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
	gap: 8px 16px;
	margin: 0;
	padding: 0;
	list-style: none;
}
.hmo-tmpl-visibility-list label {
	font-size: 13px;
}
</style>

<script>
jQuery(function($){

	/* ---- Reset / Clear Override ---- */
	$(document).on('click', '.hmo-tmpl-reset', function(){
		var btn      = $(this);
		var key      = btn.data('key');
		var typeKey  = btn.data('type');
		var nonc     = btn.data('nonce');
		var isDefault = ( typeKey === 'default' );

		var msg = isDefault
			? 'Reset this section to its default content? Any saved customization will be lost.'
			: 'Clear the ' + typeKey + ' override for this section? It will fall back to the Default template.';

		if ( ! confirm(msg) ) {
			return;
		}
		btn.prop('disabled', true).text('Working…');

		$.post(ajaxurl, {
			action      : 'hmo_reset_template_section',
			_ajax_nonce : nonc,
			section_key : key,
			type_key    : typeKey
		}, function(resp){
			if ( resp.success ) {
				location.reload();
			} else {
				btn.prop('disabled', false).text( isDefault ? 'Reset to Default' : 'Clear Override' );
				alert('Error: ' + (resp.data || 'Unknown error'));
			}
		}).fail(function(){
			btn.prop('disabled', false).text( isDefault ? 'Reset to Default' : 'Clear Override' );
			alert('Request failed. Please try again.');
		});
	});

	/* ---- Bulk regenerate future / past event pages (batched) ---- */
	var bulkNonce = '<?php echo esc_js( wp_create_nonce( 'hmo_bulk_regen' ) ); ?>';

	var bulkRegenLabels = {
		future: {
			confirm: 'Regenerate content for ALL future event pages that already have a linked GWU page, using the current templates? This cannot be undone.',
			empty:   'No future event pages found to regenerate.',
			btn:     'Regenerate All Future Event Pages',
			working: 'Regenerating…'
		},
		past: {
			confirm: 'Regenerate content for ALL past event pages that already have a linked GWU page, using the current templates? This cannot be undone.',
			empty:   'No past event pages found to regenerate.',
			btn:     'Regenerate All Past Event Pages',
			working: 'Regenerating…'
		}
	};

	function resetBulkRegenButtons(){
		$('.hmo-bulk-regen-trigger').each(function(){
			var scope = $(this).data('regen-scope');
			var labels = bulkRegenLabels[ scope ] || bulkRegenLabels.future;
			$(this).prop('disabled', false).text(labels.btn);
		});
	}

	$(document).on('click', '.hmo-bulk-regen-trigger', function(){
		var btn        = $(this);
		var scope      = btn.data('regen-scope') || 'future';
		var labels     = bulkRegenLabels[ scope ] || bulkRegenLabels.future;
		var panel      = $('#hmo-bulk-regen-panel');
		var statusEl   = $('#hmo-bulk-regen-status');
		var barEl      = $('#hmo-bulk-regen-bar');
		var countsEl   = $('#hmo-bulk-regen-counts');
		var errorsWrap = $('#hmo-bulk-regen-errors-wrap');
		var errorsList = $('#hmo-bulk-regen-errors');

		if ( ! confirm(labels.confirm) ) {
			return;
		}

		$('.hmo-bulk-regen-trigger').prop('disabled', true);
		btn.text(labels.working);
		panel.show();
		statusEl.css('color','').text('Fetching event list…');
		barEl.css('width', '0%');
		countsEl.text('');
		errorsList.empty();
		errorsWrap.hide();

		$.post(ajaxurl, {
			action      : 'hmo_bulk_regen_init',
			_ajax_nonce : bulkNonce,
			regen_scope : scope
		}).done(function(resp){
			if ( ! resp.success ) {
				return fail('Init failed: ' + (resp.data || 'Unknown error'));
			}
			var queue     = (resp.data.event_ids || []).slice();
			var batchSize = resp.data.batch_size || 3;
			var total     = resp.data.total || queue.length;

			if ( total === 0 ) {
				statusEl.css('color','#666').text(labels.empty);
				panel.delay(4000).fadeOut();
				resetBulkRegenButtons();
				return;
			}

			var processed = 0, updated = 0, failed = 0;

			function runNext(){
				if ( queue.length === 0 ) {
					statusEl.css('color','green').text(
						'Done. ' + updated + ' of ' + total + ' page(s) regenerated' +
						(failed > 0 ? '; ' + failed + ' failed.' : '.')
					);
					barEl.css('width', '100%');
					resetBulkRegenButtons();
					return;
				}

				var batch = queue.splice(0, batchSize);

				$.post(ajaxurl, {
					action      : 'hmo_bulk_regen_batch',
					_ajax_nonce : bulkNonce,
					regen_scope : scope,
					event_ids   : batch
				}).done(function(batchResp){
					if ( ! batchResp.success ) {
						return fail('Batch failed: ' + (batchResp.data || 'Unknown error'));
					}
					processed += batchResp.data.processed || 0;
					updated   += batchResp.data.updated   || 0;
					failed    += batchResp.data.failed    || 0;

					(batchResp.data.errors || []).forEach(function(err){
						errorsList.append(
							$('<li>').text('Event #' + err.event_id + ': ' + err.error)
						);
					});
					if ( failed > 0 ) errorsWrap.show();

					var pct = Math.min( 100, Math.round( (processed / total) * 100 ) );
					barEl.css('width', pct + '%');
					statusEl.text('Regenerating… ' + processed + ' / ' + total);
					countsEl.text(updated + ' updated' + (failed > 0 ? ', ' + failed + ' failed' : ''));

					setTimeout(runNext, 50);
				}).fail(function(){
					fail('Network error during batch. Already processed: ' + processed + ' / ' + total);
				});
			}

			runNext();

		}).fail(function(){
			fail('Network error — could not start regeneration.');
		});

		function fail(msg){
			statusEl.css('color','#b32d2e').text(msg);
			resetBulkRegenButtons();
		}
	});
});
</script>
