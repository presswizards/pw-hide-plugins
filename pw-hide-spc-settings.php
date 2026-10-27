<?php
/**
 * Plugin Name: PW — Hide SPC Settings
 * Description: Hides Super Page Cache settings pages from wp-admin users not in the PW super-user list. Admin-bar purge links are untouched.
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

// Super-users who may see and change SPC settings. Edit this list only.
$GLOBALS['pw_hide_plugins_super_users'] = array( 'presswizards', 'pwadmin' );

function pw_hide_plugins_is_super_user() {
	$allowed = array_filter( array_map( function ( $s ) {
		return strtolower( trim( (string) $s ) );
	}, (array) $GLOBALS['pw_hide_plugins_super_users'] ) );
	$user = wp_get_current_user();
	return ( $user && $user->exists() && in_array( strtolower( $user->user_login ), $allowed, true ) );
}

function pw_hide_plugins_spc_slugs() {
	return array(
		'super-page-cache',
		'super-page-cache-settings',
		'super-page-cache-import-export',
		'super-page-cache-license',
	);
}

add_action( 'admin_menu', function () {
	if ( pw_hide_plugins_is_super_user() ) {
		return;
	}
	remove_menu_page( 'super-page-cache' );
	foreach ( pw_hide_plugins_spc_slugs() as $slug ) {
		remove_submenu_page( 'super-page-cache', $slug );
	}
}, 999 );

// Direct-URL guard: same policy for admin.php?page=super-page-cache*.
// Admin-bar purge links hit admin-post/ajax, never these screens.
add_action( 'current_screen', function () {
	if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ! is_admin() ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || empty( $screen->id ) || strpos( $screen->id, 'super-page-cache' ) === false ) {
		return;
	}
	if ( ! pw_hide_plugins_is_super_user() ) {
		wp_die( 'You do not have permission to access the Super Page Cache settings.', 403 );
	}
} );
