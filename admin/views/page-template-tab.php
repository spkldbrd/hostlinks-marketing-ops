<?php
/**
 * Marketing Ops Settings — "Page Template" tab content.
 * Included from admin/views/settings.php when $active_tab === 'page-template'.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sections  = HMO_Page_Template::get_sections();
$nonce_val = wp_create_nonce( 'hmo_page_template' );
?>

<p style="color:#666;margin-top:0;">
	Edit the boilerplate text used when auto-generating GWU marketing pages for new events.
	Content is sanitized as standard WordPress post HTML.
	<strong>Tokens</strong> (e.g. <code>{{DATE_LONG}}</code>) are replaced with live event data at page-creation time — do not remove them from sections that contain them.
</p>

<form method="post" action="">
	<?php wp_nonce_field( 'hmo_page_template', 'hmo_page_template_nonce' ); ?>
	<input type="hidden" name="hmo_save_page_template" value="1">

	<?php foreach ( $sections as $key => $def ) :
		$content = HMO_Page_Template::get_section_content( $key );
		$editor_id = 'hmo_tmpl_' . $key;
	?>
	<div class="hmo-tmpl-section" id="tmpl-section-<?php echo esc_attr( $key ); ?>">
		<div class="hmo-tmpl-section__header">
			<span class="hmo-tmpl-section__title"><?php echo wp_kses_post( $def['label'] ); ?></span>
			<?php if ( ! empty( $def['tokens'] ) ) : ?>
				<span class="hmo-tmpl-section__token-badge">Has tokens</span>
			<?php endif; ?>
			<button type="button"
				class="button button-small hmo-tmpl-reset"
				data-key="<?php echo esc_attr( $key ); ?>"
				data-nonce="<?php echo esc_attr( $nonce_val ); ?>"
				style="margin-left:auto;">
				Reset to Default
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
		<button type="submit" class="button button-primary">Save Template Sections</button>
		<button type="button" id="hmo-bulk-regen" class="button" style="margin-left:12px;">
			Regenerate All Future Event Pages
		</button>
		<span id="hmo-bulk-regen-status" style="margin-left:10px;font-style:italic;"></span>
	</p>
</form>

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
</style>

<script>
jQuery(function($){

	/* ---- Reset individual section ---- */
	$(document).on('click', '.hmo-tmpl-reset', function(){
		var btn  = $(this);
		var key  = btn.data('key');
		var nonc = btn.data('nonce');

		if ( ! confirm('Reset this section to its default content? Any saved customization will be lost.') ) {
			return;
		}
		btn.prop('disabled', true).text('Resetting…');

		$.post(ajaxurl, {
			action      : 'hmo_reset_template_section',
			_ajax_nonce : nonc,
			section_key : key
		}, function(resp){
			btn.prop('disabled', false).text('Reset to Default');
			if ( resp.success ) {
				// Reload so wp_editor() is re-initialised with the new default.
				location.reload();
			} else {
				alert('Error: ' + (resp.data || 'Unknown error'));
			}
		}).fail(function(){
			btn.prop('disabled', false).text('Reset to Default');
			alert('Request failed. Please try again.');
		});
	});

	/* ---- Bulk regenerate all future event pages ---- */
	$('#hmo-bulk-regen').on('click', function(){
		var btn    = $(this);
		var status = $('#hmo-bulk-regen-status');

		if ( ! confirm('Regenerate content for ALL future event pages using the current template? This cannot be undone.') ) {
			return;
		}
		btn.prop('disabled', true).text('Regenerating…');
		status.text('');

		$.post(ajaxurl, {
			action      : 'hmo_bulk_regenerate_pages',
			_ajax_nonce : '<?php echo esc_js( wp_create_nonce( 'hmo_bulk_regen' ) ); ?>'
		}, function(resp){
			btn.prop('disabled', false).text('Regenerate All Future Event Pages');
			if ( resp.success ) {
				status.css('color','green').text(resp.data.message);
			} else {
				status.css('color','red').text('Error: ' + (resp.data || 'Unknown'));
			}
		}).fail(function(){
			btn.prop('disabled', false).text('Regenerate All Future Event Pages');
			status.css('color','red').text('Request failed.');
		});
	});
});
</script>
