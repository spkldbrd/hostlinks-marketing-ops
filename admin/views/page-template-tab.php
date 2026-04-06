<?php
/**
 * Marketing Ops Settings — "Page Template" tab content.
 * Included from admin/views/settings.php when $active_tab === 'page-template'.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sections  = HMO_Page_Template::get_sections();
$types     = HMO_Page_Template::get_event_types();
$nonce_val = wp_create_nonce( 'hmo_page_template' );

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
	<strong>Tokens</strong> (e.g. <code>{{DATE_LONG}}</code>) are replaced with live event data at page-creation time.
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
		<button type="button" id="hmo-bulk-regen" class="button" style="margin-left:12px;">
			Regenerate All Future Event Pages
		</button>
		<span id="hmo-bulk-regen-status" style="margin-left:10px;font-style:italic;"></span>
		<?php endif; ?>
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

	/* ---- Bulk regenerate all future event pages ---- */
	$('#hmo-bulk-regen').on('click', function(){
		var btn    = $(this);
		var status = $('#hmo-bulk-regen-status');

		if ( ! confirm('Regenerate content for ALL future event pages using the current templates? This cannot be undone.') ) {
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
