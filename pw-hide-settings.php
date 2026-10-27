<?php
/**
 * Plugin Name: PW — Hide Plugin Settings
 * Description: Hides configured settings pages, upsell notices, plugin rows, and toolbar items from wp-admin users not in the PW super-user list. Admin-bar cache purge links are untouched.
 * Version: 1.4.0
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

// Plugin rows hidden from plugins.php for non-super-users
// (keys are plugin basenames, e.g. 'novamira/novamira.php').
$GLOBALS['pw_hide_plugins_hidden_plugins'] = array(
	'novamira/novamira.php',
);

// Upsell admin notices squashed for non-super-users
// (hook => callback; removed when the callback exists).
$GLOBALS['pw_hide_plugins_squashed_notices'] = array(
	'admin_notices' => array(
		'novamira_render_pro_welcome_notice', // "Novamira Pro is here."
		// Themeisle SDK "AI agent" notice is an object-method hook
		// (Ai_Connect::render_notice) re-added late by the SDK itself,
		// so it is stripped by class scan below, not listed here.
	),
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
	foreach ( (array) $GLOBALS['pw_hide_plugins_squashed_notices'] as $hook => $callbacks ) {
		foreach ( (array) $callbacks as $callback ) {
			if ( is_string( $callback ) && function_exists( $callback ) ) {
				remove_action( $hook, $callback );
			}
		}
	}
}, 999 );

// Plugin rows vanish from plugins.php for non-super users.
// (Update-count badges may still reflect the hidden plugin; out of scope.)
add_filter( 'all_plugins', function ( $plugins ) {
	if ( pw_hide_plugins_is_super_user() || ! is_array( $plugins ) ) {
		return $plugins;
	}
	foreach ( (array) $GLOBALS['pw_hide_plugins_hidden_plugins'] as $basename ) {
		unset( $plugins[ (string) $basename ] );
	}
	return $plugins;
} );

// Themeisle SDK "Connect your AI agent" row-meta link + admin notice,
// stripped for non-super users. The notice re-registers itself late
// (in_admin_header), so the strip runs on admin_notices at priority 9,
// before its default-10 slot but after any re-registration.
add_filter( 'plugin_row_meta', function ( $links, $file ) {
	if ( pw_hide_plugins_is_super_user() || ! is_array( $links ) ) {
		return $links;
	}
	foreach ( $links as $i => $html ) {
		if ( stripos( (string) $html, 'ti-ai-connect-open' ) !== false ) {
			unset( $links[ $i ] );
		}
	}
	return $links;
}, 999, 2 );

add_action( 'admin_notices', function () {
	if ( pw_hide_plugins_is_super_user() ) {
		return;
	}
	global $wp_filter;
	if ( empty( $wp_filter['admin_notices'] ) ) {
		return;
	}
	foreach ( $wp_filter['admin_notices']->callbacks as $prio => $cbs ) {
		foreach ( $cbs as $id => $cb ) {
			$fn = isset( $cb['function'] ) ? $cb['function'] : null;
			if ( is_array( $fn ) && is_object( $fn[0] )
				&& stripos( get_class( $fn[0] ), 'ai_connect' ) !== false
				&& isset( $fn[1] ) && $fn[1] === 'render_notice' ) {
				remove_action( 'admin_notices', $fn, (int) $prio );
			}
		}
	}
}, 9 );

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
