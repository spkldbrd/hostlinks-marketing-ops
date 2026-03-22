<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// Variables: $alerts (array from HMO_Alert_Service::get_alerts()), $detail_base (string)

$has_any = false;
foreach ( $alerts as $rows ) {
	if ( ! empty( $rows ) ) { $has_any = true; break; }
}
if ( ! $has_any ) { return; }

$type_meta = array(
	HMO_Alert_Service::TYPE_AT_RISK           => array( 'icon' => '⚠', 'label' => 'At Risk',            'color' => 'red' ),
	HMO_Alert_Service::TYPE_BEHIND_SCHEDULE   => array( 'icon' => '⏰', 'label' => 'Behind Schedule',   'color' => 'orange' ),
	HMO_Alert_Service::TYPE_MISSING_DATA_LIST => array( 'icon' => '📋', 'label' => 'Missing Data List', 'color' => 'yellow' ),
	HMO_Alert_Service::TYPE_MISSING_CALL_LIST => array( 'icon' => '📞', 'label' => 'Missing Call List', 'color' => 'yellow' ),
	HMO_Alert_Service::TYPE_UNDER_GOAL        => array( 'icon' => '📉', 'label' => 'Under Goal',        'color' => 'blue' ),
);
?>
<div class="hmo-alert-bar" id="hmo-alert-bar">
	<div class="hmo-alert-bar__header">
		<button class="hmo-alert-bar__toggle" type="button" data-action="toggle-alert-bar" aria-expanded="false">
			<span class="hmo-alert-bar__arrow">&#9654;</span>
			<span class="hmo-alert-bar__title">Alerts</span>
		</button>
		<div class="hmo-alert-bar__chips">
			<?php foreach ( $type_meta as $type => $meta ) :
				$count = count( $alerts[ $type ] ?? array() );
				if ( ! $count ) { continue; }
			?>
			<button type="button"
				class="hmo-alert-chip hmo-alert-chip--<?php echo esc_attr( $meta['color'] ); ?>"
				data-alert-type="<?php echo esc_attr( $type ); ?>"
				data-action="toggle-alert-panel">
				<?php echo esc_html( $meta['icon'] . ' ' . $meta['label'] ); ?>
				<span class="hmo-alert-chip__count"><?php echo (int) $count; ?></span>
			</button>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="hmo-alert-bar__body" style="display:none;">
		<?php foreach ( $type_meta as $type => $meta ) :
			$type_rows = $alerts[ $type ] ?? array();
			if ( empty( $type_rows ) ) { continue; }
		?>
		<div class="hmo-alert-panel hmo-alert-panel--<?php echo esc_attr( $meta['color'] ); ?>"
			id="hmo-alert-panel-<?php echo esc_attr( $type ); ?>" style="display:none;">
			<h4 class="hmo-alert-panel__title">
				<?php echo esc_html( $meta['icon'] . ' ' . $meta['label'] ); ?>
				<span class="hmo-alert-panel__count"><?php echo count( $type_rows ); ?></span>
			</h4>
			<ul class="hmo-alert-panel__list">
				<?php foreach ( $type_rows as $row ) :
					$url = $detail_base
						? add_query_arg( 'event_id', $row->event_id, $detail_base )
						: '';
				?>
				<li class="hmo-alert-panel__item">
					<?php if ( $url ) : ?>
						<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $row->event_name ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $row->event_name ); ?>
					<?php endif; ?>
					<span class="hmo-alert-panel__meta">
						<?php echo esc_html( $row->marketer_name ); ?> &bull;
						<?php echo esc_html( $row->days_left_label ); ?>
					</span>
				</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php endforeach; ?>
	</div>
</div>
