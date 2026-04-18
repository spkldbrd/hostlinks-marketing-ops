<form method="post" action="">
	<?php wp_nonce_field( 'hmo_save_page_urls' ); ?>

	<p>
		Set the WordPress page URL where each HMO shortcode lives.
		If left blank, the plugin auto-detects the page by scanning for the shortcode tag.
	</p>

	<table class="form-table">
		<?php
		$page_defs = array(
			'dashboard_selector' => array( 'label' => 'Marketing Ops Selector', 'shortcode' => '[hmo_dashboard_selector]', 'field' => 'hmo_url_dashboard_selector' ),
			'dashboard'          => array( 'label' => 'Dashboard',              'shortcode' => '[hmo_dashboard]',          'field' => 'hmo_url_dashboard' ),
			'my_classes'         => array( 'label' => 'My Classes',             'shortcode' => '[hmo_my_classes]',         'field' => 'hmo_url_my_classes' ),
			'event_detail'       => array( 'label' => 'Event Detail',           'shortcode' => '[hmo_event_detail]',       'field' => 'hmo_url_event_detail' ),
			'task_editor'        => array( 'label' => 'Task Template Editor',   'shortcode' => '[hmo_task_editor]',        'field' => 'hmo_url_task_editor' ),
			'event_report'       => array( 'label' => 'Event Journey Report',   'shortcode' => '[hmo_event_report]',       'field' => 'hmo_url_event_report' ),
			'maps_tool'          => array( 'label' => 'Maps Tool',               'shortcode' => '[display_maps_tool]',      'field' => 'hmo_url_maps_tool' ),
		);
		$source_labels = array(
			'override' => '<span style="color:#007017;">&#10003; Manual override</span>',
			'auto'     => '<span style="color:#007017;">&#10003; Auto-detected</span>',
			'none'     => '<span style="color:#d63638;">&#10007; Not found</span>',
		);
		foreach ( $page_defs as $key => $def ) :
			$status = $page_status[ $key ] ?? array( 'url' => '', 'source' => 'none' );
		?>
		<tr>
			<th scope="row">
				<label for="<?php echo esc_attr( $def['field'] ); ?>"><?php echo esc_html( $def['label'] ); ?></label>
				<p class="description"><code><?php echo esc_html( $def['shortcode'] ); ?></code></p>
			</th>
			<td>
				<input type="url" id="<?php echo esc_attr( $def['field'] ); ?>"
					name="<?php echo esc_attr( $def['field'] ); ?>"
					value="<?php echo esc_url( $url_overrides[ $key ] ?? '' ); ?>"
					class="regular-text"
					placeholder="https://">
				<p class="description">
					<?php echo $source_labels[ $status['source'] ]; // phpcs:ignore ?>
					<?php if ( $status['url'] ) : ?>
						— <a href="<?php echo esc_url( $status['url'] ); ?>" target="_blank"><?php echo esc_html( $status['url'] ); ?></a>
					<?php endif; ?>
				</p>
			</td>
		</tr>
		<?php endforeach; ?>
	</table>

	<?php submit_button( 'Save Page Links', 'primary', 'hmo_save_page_urls' ); ?>
</form>
