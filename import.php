<?php
/**
 * Importer for bebop
 */
set_time_limit( 60 );
ini_set( 'max_execution_time', 60 );

//load the WordPress loader
$current_path  = getcwd();
$seeking_root  = pathinfo( $current_path );
$inc_path      = str_replace( 'wp-content/plugins','',$seeking_root['dirname'] );

ini_set( 'include_path', $inc_path );
include_once( 'wp-load.php' );

//include files from core.
include_once( 'core/bebop-data.php' );
include_once( 'core/bebop-oauth.php' );
include_once( 'core/bebop-tables.php' );
include_once( 'core/bebop-filters.php' );
include_once( 'core/bebop-pages.php' );
include_once( 'core/bebop-extensions.php' );

//Main content file
include_once( 'core/bebop-core-user.php' );

//if import a specific OER.
if ( isset( $_GET['extension'] ) ) {
	$importers[] = $_GET['extension'];
}
else {
	$importers = bebop_extensions::bebop_get_active_extension_names();
}

//Check that the importers queue isn't empty, then start calling the import functions
if ( ! empty( $importers[0] ) ) {
	bebop_tables::log_general( __( 'main_importer_name', 'bebop' ), __( 'main_importer_service_started', 'bebop') );
	$return_array = array();
	foreach ( $importers as $extension ) {
		if ( bebop_tables::get_option_value( 'bebop_' . strtolower( $extension ) . '_provider' ) == 'on' ) {
			if ( file_exists( WP_PLUGIN_DIR . '/bebop/extensions/' . strtolower( $extension ) . '/import.php' ) ) {
				include_once( WP_PLUGIN_DIR . '/bebop/extensions/' . strtolower( $extension ) . '/import.php' );
				if ( function_exists( 'bebop_' . strtolower( $extension ) . '_import' ) ) {
					$return_array[] = call_user_func( 'bebop_' . strtolower( $extension ) . '_import', strtolower( $extension ) );
				}
				else {
					bebop_tables::log_error( __( 'main_importer_name', 'bebop' ), sprintf( __( 'bebop_%1$s_import does_not_exist', 'bebop' ), strtolower( $extension ) ) );
				}
			}
			else {
				bebop_tables::log_error( __( 'main_importer_name', 'bebop' ), sprintf( __('%1$s/import.php does_not_exist', 'bebop' ), WP_PLUGIN_DIR . '/bebop/extensions/' . strtolower( $extension ) ) );
			}
		}
	}
	$log_results = implode( ', ', $return_array );
	
	if ( ! empty( $log_results ) ) {
		$message =  __( 'main_importer_completed', 'bebop' ) .  ' ' . sprintf( __( 'main_importer_imported %1$s.', 'bebop' ), $log_results );
		bebop_tables::log_general( __( 'main_importer_name', 'bebop' ), $message );
		echo $message;
	}
	else {
		$message = __( 'main_importer_completed', 'bebop' ) .  ' ' . __( 'nothing_imported', 'bebop' );
		bebop_tables::log_general( __( 'main_importer_name', 'bebop' ), $message );
		echo $message;
	}
}