<?php
/**
 * Plugin Name: PW — Hide Plugin Settings
 * Description: Hides configured settings pages and toolbar items from wp-admin users not in the PW super-user list. Admin-bar cache purge links are untouched.
 * Version: 1.1.0
 */

defined( 'ABSPATH' ) || exit;

// Super-users who keep full access. Edit this list only.
$GLOBALS['pw_hide_plugins_super_users'] = array( 'presswizards', 'pwadmin' );

// Parent menu slugs whose whole tree is hidden (sidebar + submenus).
$GLOBALS['pw_hide_plugins_hidden_menus'] = array(
	'super-page-cache', // Super Page Cache (+ Dashboard/Settings/Import-Export/License)
	'novamira-connect', // Novamira (+ Chat/Features/Skills/Design and all subpages)
);

// Admin-bar node ID prefixes stripped for non-super-users.
$GLOBALS['pw_hide_plugins_hidden_bar_nodes'] = array(
	'novamira-',
);

// Admin screen-ID substrings blocked for non-super-users (direct URLs).
$GLOBALS['pw_hide_plugins_blocked_screens'] = array(
	'super-page-cache',
	'novamira',
);

function pw_hide_plugins_is_super_user() {
	$allowed = array_filter( array_map( function ( $s ) {
		return strtolower( trim( (string) $s ) );
	}, (array) $GLOBALS['pw_hide_plugins_super_users'] ) );
	$user = wp_get_current_user();
	return ( $user && $user->exists() && in_array( strtolower( $user->user_login ), $allowed, true ) );
}

add_action( 'admin_menu', function () {
	if ( pw_hide_plugins_is_super_user() ) {
		return;
	}
	foreach ( (array) $GLOBALS['pw_hide_plugins_hidden_menus'] as $parent ) {
		global $submenu;
		if ( isset( $submenu[ $parent ] ) && is_array( $submenu[ $parent ] ) ) {
			foreach ( $submenu[ $parent ] as $item ) {
				if ( isset( $item[2] ) ) {
					remove_submenu_page( $parent, $item[2] );
				}
			}
		}
		remove_menu_page( $parent );
	}
}, 999 );

// Toolbar stripping runs right before render, after every plugin has
// added its nodes (MU plugins load before regular plugins, so an
// admin_bar_menu removal here would lose the race).
add_action( 'wp_before_admin_bar_render', function () {
	if ( pw_hide_plugins_is_super_user() ) {
		return;
	}
	global $wp_admin_bar;
	if ( ! $wp_admin_bar instanceof WP_Admin_Bar ) {
		return;
	}
	foreach ( $wp_admin_bar->get_nodes() as $id => $node ) {
		foreach ( (array) $GLOBALS['pw_hide_plugins_hidden_bar_nodes'] as $prefix ) {
			if ( strpos( (string) $id, (string) $prefix ) === 0 ) {
				$wp_admin_bar->remove_node( $id );
				break;
			}
		}
	}
}, 999 );

// Direct-URL guard: same policy for settings screens reached by URL.
// Admin-bar links hit admin-post/ajax, never these screens.
add_action( 'current_screen', function () {
	if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ! is_admin() ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || empty( $screen->id ) ) {
		return;
	}
	foreach ( (array) $GLOBALS['pw_hide_plugins_blocked_screens'] as $needle ) {
		if ( strpos( $screen->id, (string) $needle ) !== false ) {
			if ( ! pw_hide_plugins_is_super_user() ) {
				wp_die( 'You do not have permission to access these settings.', 403 );
			}
			return;
		}
	}
} );
