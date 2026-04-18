<?php
	$sync_status  = HMO_Page_Sync::get_config_status();
	$sync_nonce   = wp_create_nonce( 'hmo_page_sync_test' );
	$all_defined  = ! in_array( false, $sync_status, true );
?>

<h2>GWU Page Sync — Configuration Status</h2>
<p>
	When a new event is created in Hostlinks, Marketing Ops automatically creates a marketing page
	on <strong>grantwritingusa.com</strong> and saves the URL back to the event&#8217;s <em>WEB URL</em> field.
	This feature requires four constants in the <strong>subdomain&#8217;s</strong> <code>wp-config.php</code>.
</p>

<table class="widefat striped" style="max-width:540px;margin-bottom:20px;">
	<thead>
		<tr>
			<th>Constant</th>
			<th style="width:110px;">Status</th>
		</tr>
	</thead>
	<tbody>
	<?php
	$const_labels = array(
		'GWU_PRIMARY_API'           => 'Primary domain REST API URL',
		'GWU_API_USER'              => 'Application Password username',
		'GWU_API_PASS'              => 'Application Password',
		'GWU_EVENTS_PARENT_PAGE_ID' => 'Events parent page ID',
	);
	foreach ( $const_labels as $const => $label ) :
		$defined = $sync_status[ $const ] ?? false;
	?>
	<tr>
		<td>
			<code><?php echo esc_html( $const ); ?></code><br>
			<small style="color:#8c8f94;"><?php echo esc_html( $label ); ?></small>
		</td>
		<td>
			<?php if ( $defined ) : ?>
				<span style="color:#007017;font-weight:600;">&#10003; Defined</span>
				<?php if ( $const === 'GWU_PRIMARY_API' ) : ?>
					<br><small style="color:#555;"><?php echo esc_html( constant( $const ) ); ?></small>
				<?php elseif ( $const === 'GWU_API_USER' ) : ?>
					<br><small style="color:#555;"><?php echo esc_html( constant( $const ) ); ?></small>
				<?php elseif ( $const === 'GWU_EVENTS_PARENT_PAGE_ID' ) : ?>
					<br><small style="color:#555;">ID: <?php echo (int) constant( $const ); ?><?php echo ( (int) constant( $const ) === 0 ) ? ' (top-level pages)' : ''; ?></small>
				<?php endif; ?>
			<?php else : ?>
				<span style="color:#d63638;font-weight:600;">&#10007; Not set</span>
			<?php endif; ?>
		</td>
	</tr>
	<?php endforeach; ?>
	</tbody>
</table>

<?php if ( $all_defined ) : ?>
<p>
	<button type="button" class="button button-secondary" id="hmo-test-page-sync-btn">
		&#9654; Test Connection to grantwritingusa.com
	</button>
	<span id="hmo-test-page-sync-status" style="margin-left:12px;font-size:13px;"></span>
</p>

<script>
(function() {
	var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var nonce   = <?php echo wp_json_encode( $sync_nonce ); ?>;
	var btn     = document.getElementById('hmo-test-page-sync-btn');
	var status  = document.getElementById('hmo-test-page-sync-status');

	btn.addEventListener('click', function() {
		btn.disabled = true;
		btn.textContent = '\u23f3 Testing\u2026';
		status.style.color = '#888';
		status.textContent = 'Connecting\u2026';

		var fd = new FormData();
		fd.append('action',      'hmo_test_page_sync');
		fd.append('_ajax_nonce', nonce);

		fetch(ajaxUrl, { method: 'POST', body: fd })
			.then(function(r) { return r.json(); })
			.then(function(res) {
				btn.disabled = false;
				btn.textContent = '\u25b6 Test Connection to grantwritingusa.com';
				if (res.success) {
					status.style.color = '#007017';
					status.textContent = '\u2713 ' + res.data.message;
				} else {
					status.style.color = '#d63638';
					status.textContent = '\u2717 ' + (res.data || 'Unknown error.');
				}
			})
			.catch(function() {
				btn.disabled = false;
				btn.textContent = '\u25b6 Test Connection to grantwritingusa.com';
				status.style.color = '#d63638';
				status.textContent = 'Request failed. Please try again.';
			});
	});
})();
</script>
<?php else : ?>
<div class="notice notice-warning inline" style="max-width:540px;">
	<p>One or more constants are missing. Add all four to <code>wp-config.php</code> to enable page sync.</p>
</div>
<?php endif; ?>

<hr style="margin:28px 0;">

<h2>wp-config.php Setup</h2>
<p>
	Add the following four lines to <code>wp-config.php</code> on <strong>hostlinks.grantwritingusa.com</strong>
	(above <code>/* That&#8217;s all, stop editing! */</code>).
	The Application Password is generated in the <strong>grantwritingusa.com</strong> WP admin under
	<em>Users &rarr; event-automation &rarr; Profile &rarr; Application Passwords</em>.
</p>

<pre style="background:#f6f7f7;border:1px solid #dcdcde;padding:14px 16px;border-radius:4px;max-width:700px;overflow-x:auto;font-size:13px;line-height:1.7;"><?php echo esc_html(
"define( 'GWU_PRIMARY_API',           'https://grantwritingusa.com/wp-json/wp/v2' );
define( 'GWU_API_USER',              'event-automation' );
define( 'GWU_API_PASS',              'xxxx xxxx xxxx xxxx xxxx xxxx' ); // Application Password
define( 'GWU_EVENTS_PARENT_PAGE_ID',  0 ); // replace 0 with Events parent page ID if using one"
); ?></pre>

<hr style="margin:28px 0;">

<h2>How It Works</h2>
<ol>
	<li>A new event is saved in Hostlinks (manually or via Cvent import).</li>
	<li>The <code>hostlinks_event_created</code> action fires.</li>
	<li>Marketing Ops reads the full event record and builds a page title, slug, and HTML content from a standard template.</li>
	<li>A <code>POST /wp-json/wp/v2/pages</code> request is sent to grantwritingusa.com using the Application Password.</li>
	<li>The new page URL is saved back to the event&#8217;s <em>WEB URL</em> field in Hostlinks.</li>
	<li>The <code>[public_event_list]</code> shortcode on grantwritingusa.com immediately shows a working &#8220;details&#8221; link for the event.</li>
</ol>
