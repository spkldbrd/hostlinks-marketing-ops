<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Unauthorized' );
}

$notice = $notice ?? '';

// ── Current state ─────────────────────────────────────────────────────────────

$access_svc           = new HMO_Access_Service();
$approved_ids         = $access_svc->get_approved_viewers();
$task_editor_ids      = $access_svc->get_task_editors();
$report_viewer_ids    = $access_svc->get_report_viewers();
$marketing_admin_ids  = $access_svc->get_marketing_admins();
$denial_message       = get_option( HMO_Access_Service::OPT_MESSAGE, HMO_Access_Service::DEFAULT_MESSAGE );
$page_status          = HMO_Page_URLs::detection_status();
$url_overrides        = HMO_Page_URLs::get_overrides();
$saved_modes          = get_option( HMO_Access_Service::OPT_MODES, array() );

$task_editor_users = array();
if ( ! empty( $task_editor_ids ) ) {
	$task_editor_users = get_users( array(
		'include' => $task_editor_ids,
		'fields'  => array( 'ID', 'display_name', 'user_email' ),
		'orderby' => 'display_name',
	) );
}

$report_viewer_users = array();
if ( ! empty( $report_viewer_ids ) ) {
	$report_viewer_users = get_users( array(
		'include' => $report_viewer_ids,
		'fields'  => array( 'ID', 'display_name', 'user_email' ),
		'orderby' => 'display_name',
	) );
}

$marketing_admin_users = array();
if ( ! empty( $marketing_admin_ids ) ) {
	$marketing_admin_users = get_users( array(
		'include' => $marketing_admin_ids,
		'fields'  => array( 'ID', 'display_name', 'user_email' ),
		'orderby' => 'display_name',
	) );
}

$approved_users = array();
if ( ! empty( $approved_ids ) ) {
	$approved_users = get_users( array(
		'include' => $approved_ids,
		'fields'  => array( 'ID', 'display_name', 'user_email' ),
		'orderby' => 'display_name',
	) );
}

$mode_labels = array(
	'public'           => 'Public — anyone',
	'logged_in'        => 'Logged-in Users',
	'approved_viewers' => 'Approved Viewers Only',
);

$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';

$tabs = array(
	'general'       => 'General',
	'bucket-access' => 'Bucket Access',
	'page-links'    => 'Page Links',
	'user-access'   => 'User Access',
	'tools'         => 'Tools Links',
	'maps'          => 'Maps',
	'page-sync'     => 'GWU Page Sync',
	'page-template' => 'Page Template',
);
?>
<div class="wrap">
<h1>Hostlinks Marketing Ops — Settings</h1>

<?php if ( ! defined( 'HOSTLINKS_VERSION' ) ) : ?>
	<div class="notice notice-error"><p><strong>Hostlinks is not active.</strong> This plugin requires Hostlinks to function.</p></div>
<?php endif; ?>

<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

<!-- Tab nav -->
<nav class="nav-tab-wrapper" style="margin-bottom:20px;">
	<?php foreach ( $tabs as $slug => $label ) :
		$url     = add_query_arg( array( 'page' => 'hmo-settings', 'tab' => $slug ), admin_url( 'admin.php' ) );
		$classes = 'nav-tab' . ( $active_tab === $slug ? ' nav-tab-active' : '' );
		?>
	<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $classes ); ?>"><?php echo esc_html( $label ); ?></a>
	<?php endforeach; ?>
</nav>

<?php if ( $active_tab === 'general' ) : ?>
	<?php require __DIR__ . '/settings/tab-general.php'; ?>
<?php elseif ( $active_tab === 'bucket-access' ) : ?>
	<?php require __DIR__ . '/settings/tab-bucket-access.php'; ?>
<?php elseif ( $active_tab === 'page-links' ) : ?>
	<?php require __DIR__ . '/settings/tab-page-links.php'; ?>
<?php elseif ( $active_tab === 'user-access' ) : ?>
	<?php require __DIR__ . '/settings/tab-user-access.php'; ?>
<?php elseif ( $active_tab === 'tools' ) : ?>
	<?php require __DIR__ . '/settings/tab-tools.php'; ?>
<?php elseif ( $active_tab === 'maps' ) : ?>
	<?php require __DIR__ . '/settings/tab-maps.php'; ?>
<?php elseif ( $active_tab === 'page-sync' ) : ?>
	<?php require __DIR__ . '/settings/tab-page-sync.php'; ?>
<?php elseif ( $active_tab === 'page-template' ) : ?>
	<?php require __DIR__ . '/settings/tab-page-template.php'; ?>
<?php endif; ?>
</div>
