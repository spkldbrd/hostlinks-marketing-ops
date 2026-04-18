<?php
	$saved_tools = (array) get_option( 'hmo_tools_links', array() );
?>
<p>
	Add links that appear in the <strong>Tools</strong> card on every Event Detail page.
	Each link can have an optional icon image selected from the Media Library.
	The icon and link name are both clickable and open the URL in a new tab.
</p>

<style>
.hmo-tool-icon-cell { width: 90px; }
.hmo-tool-icon-wrap { display: flex; flex-direction: column; align-items: center; gap: 5px; }
.hmo-tool-icon-preview {
	width: 48px; height: 48px; border-radius: 6px;
	border: 1px solid #dcdcde; object-fit: contain;
	background: #f6f7f7; display: block;
}
.hmo-tool-icon-placeholder {
	width: 48px; height: 48px; border-radius: 6px;
	border: 1px dashed #c3c4c7; background: #f6f7f7;
	display: flex; align-items: center; justify-content: center;
	color: #c3c4c7; font-size: 20px; cursor: pointer;
}
.hmo-tool-icon-actions { display: flex; gap: 4px; flex-wrap: wrap; justify-content: center; }
.hmo-tool-icon-select { font-size: 11px !important; padding: 2px 6px !important; }
.hmo-tool-icon-remove { font-size: 11px !important; padding: 2px 6px !important; color: #d63638 !important; }
</style>

<form method="post" action="">
	<?php wp_nonce_field( 'hmo_save_tools' ); ?>

	<table class="widefat" id="hmo-tools-table" style="max-width:760px;margin-bottom:16px;">
		<thead>
			<tr>
				<th class="hmo-tool-icon-cell">Icon</th>
				<th>Link Name</th>
				<th>URL</th>
				<th style="width:50px;"></th>
			</tr>
		</thead>
		<tbody id="hmo-tools-tbody">
		<?php if ( empty( $saved_tools ) ) : ?>
			<tr id="hmo-tools-empty-row">
				<td colspan="4" style="color:#8c8f94;font-style:italic;padding:12px;">No links yet — add one below.</td>
			</tr>
		<?php else : ?>
			<?php foreach ( $saved_tools as $tool ) :
				$_icon_url = esc_attr( $tool['icon'] ?? '' );
			?>
			<tr class="hmo-tool-row">
				<td class="hmo-tool-icon-cell">
					<div class="hmo-tool-icon-wrap">
						<input type="hidden" name="hmo_tool_icon[]" class="hmo-tool-icon-input"
							value="<?php echo $_icon_url; ?>">
						<?php if ( $_icon_url ) : ?>
						<img src="<?php echo $_icon_url; ?>" class="hmo-tool-icon-preview" alt="">
						<?php else : ?>
						<div class="hmo-tool-icon-placeholder">+</div>
						<?php endif; ?>
						<div class="hmo-tool-icon-actions">
							<button type="button" class="button button-small hmo-tool-icon-select">
								<?php echo $_icon_url ? 'Change' : 'Select'; ?>
							</button>
							<?php if ( $_icon_url ) : ?>
							<button type="button" class="button button-small hmo-tool-icon-remove">Remove</button>
							<?php endif; ?>
						</div>
					</div>
				</td>
				<td>
					<input type="text" name="hmo_tool_name[]"
						value="<?php echo esc_attr( $tool['name'] ?? '' ); ?>"
						placeholder="Tool name" class="regular-text" style="width:100%;">
				</td>
				<td>
					<input type="url" name="hmo_tool_url[]"
						value="<?php echo esc_attr( $tool['url'] ?? '' ); ?>"
						placeholder="https://" class="regular-text" style="width:100%;">
				</td>
				<td style="text-align:center;vertical-align:middle;">
					<button type="button" class="button button-small hmo-remove-tool-row"
						title="Remove row">&times;</button>
				</td>
			</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>

	<p>
		<button type="button" class="button" id="hmo-add-tool-row">+ Add Link</button>
	</p>

	<?php submit_button( 'Save Tools Links', 'primary', 'hmo_save_tools' ); ?>
</form>

<script>
(function() {
	var tbody   = document.getElementById('hmo-tools-tbody');
	var emptyId = 'hmo-tools-empty-row';

	/* ── WP Media frame (one shared instance) ─────────────────────── */
	var mediaFrame = null;
	var activeRow  = null;

	function openMediaPicker(row) {
		activeRow = row;
		if (!mediaFrame) {
			mediaFrame = wp.media({
				title:    'Select Tool Icon',
				button:   { text: 'Use this image' },
				library:  { type: 'image' },
				multiple: false
			});
			mediaFrame.on('select', function() {
				var att = mediaFrame.state().get('selection').first().toJSON();
				setIcon(activeRow, att.url);
			});
		}
		mediaFrame.open();
	}

	function setIcon(row, url) {
		row.querySelector('.hmo-tool-icon-input').value = url;
		var wrap = row.querySelector('.hmo-tool-icon-wrap');
		/* replace placeholder or existing img */
		var existing = wrap.querySelector('.hmo-tool-icon-preview, .hmo-tool-icon-placeholder');
		if (existing) existing.remove();
		var img = document.createElement('img');
		img.src = url;
		img.className = 'hmo-tool-icon-preview';
		img.alt = '';
		wrap.insertBefore(img, wrap.querySelector('.hmo-tool-icon-actions'));
		/* update buttons */
		var selectBtn = wrap.querySelector('.hmo-tool-icon-select');
		selectBtn.textContent = 'Change';
		var actions = wrap.querySelector('.hmo-tool-icon-actions');
		if (!actions.querySelector('.hmo-tool-icon-remove')) {
			var removeBtn = document.createElement('button');
			removeBtn.type = 'button';
			removeBtn.className = 'button button-small hmo-tool-icon-remove';
			removeBtn.textContent = 'Remove';
			actions.appendChild(removeBtn);
		}
	}

	function clearIcon(row) {
		row.querySelector('.hmo-tool-icon-input').value = '';
		var wrap = row.querySelector('.hmo-tool-icon-wrap');
		var existing = wrap.querySelector('.hmo-tool-icon-preview');
		if (existing) existing.remove();
		if (!wrap.querySelector('.hmo-tool-icon-placeholder')) {
			var ph = document.createElement('div');
			ph.className = 'hmo-tool-icon-placeholder';
			ph.textContent = '+';
			wrap.insertBefore(ph, wrap.querySelector('.hmo-tool-icon-actions'));
		}
		var selectBtn = wrap.querySelector('.hmo-tool-icon-select');
		selectBtn.textContent = 'Select';
		var removeBtn = wrap.querySelector('.hmo-tool-icon-remove');
		if (removeBtn) removeBtn.remove();
	}

	/* ── Row builder for "+ Add Link" ─────────────────────────────── */
	function removeEmpty() {
		var e = document.getElementById(emptyId);
		if (e) e.remove();
	}

	function buildIconCell() {
		return '<td class="hmo-tool-icon-cell">' +
			'<div class="hmo-tool-icon-wrap">' +
				'<input type="hidden" name="hmo_tool_icon[]" class="hmo-tool-icon-input" value="">' +
				'<div class="hmo-tool-icon-placeholder">+</div>' +
				'<div class="hmo-tool-icon-actions">' +
					'<button type="button" class="button button-small hmo-tool-icon-select">Select</button>' +
				'</div>' +
			'</div>' +
		'</td>';
	}

	function addRow(name, url) {
		removeEmpty();
		var tr = document.createElement('tr');
		tr.className = 'hmo-tool-row';
		tr.innerHTML =
			buildIconCell() +
			'<td><input type="text" name="hmo_tool_name[]" value="' + escAttr(name || '') + '" placeholder="Tool name" class="regular-text" style="width:100%;"></td>' +
			'<td><input type="url"  name="hmo_tool_url[]"  value="' + escAttr(url  || '') + '" placeholder="https://"   class="regular-text" style="width:100%;"></td>' +
			'<td style="text-align:center;vertical-align:middle;"><button type="button" class="button button-small hmo-remove-tool-row" title="Remove row">&times;</button></td>';
		tbody.appendChild(tr);
	}

	document.getElementById('hmo-add-tool-row').addEventListener('click', function() {
		addRow('', '');
		tbody.lastElementChild.querySelector('input[name="hmo_tool_name[]"]').focus();
	});

	/* ── Event delegation ─────────────────────────────────────────── */
	document.addEventListener('click', function(e) {
		var row = e.target.closest('.hmo-tool-row');

		if (e.target.classList.contains('hmo-remove-tool-row')) {
			e.target.closest('tr').remove();
			if (!tbody.querySelector('.hmo-tool-row')) {
				var tr = document.createElement('tr');
				tr.id = emptyId;
				tr.innerHTML = '<td colspan="4" style="color:#8c8f94;font-style:italic;padding:12px;">No links yet — add one below.</td>';
				tbody.appendChild(tr);
			}
			return;
		}

		if (row && e.target.classList.contains('hmo-tool-icon-select')) {
			openMediaPicker(row);
			return;
		}

		if (row && e.target.classList.contains('hmo-tool-icon-remove')) {
			clearIcon(row);
			return;
		}

		if (row && e.target.classList.contains('hmo-tool-icon-placeholder')) {
			openMediaPicker(row);
			return;
		}
	});

	function escAttr(s) {
		return s.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
	}
})();
</script>
