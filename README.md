# pw-hide-plugins

Drop-in MU plugins for selected sites on the Press Wizards Enhance cluster
and Plesk servers.

## Summary

`pw-hide-spc-settings.php` hides the Super Page Cache (SPC) settings pages
(sidebar menu + direct URLs) from wp-admin users not in the PW super-user
list, while leaving the admin-bar "purge cache" links fully working.

Unlike the Watchdog-fleet sibling (`wdshelp-hide-plugins`, gated on the
`WATCHDOG_HOSTING_SUPER_USERS` constant), this variant carries its own
username list — no WP constant required on the target site.

## Details

- Single file, must-use plugin: `pw-hide-spc-settings.php` → the site's
  `wp-content/mu-plugins/`. MU plugins always load and cannot be disabled
  from wp-admin.
- Applies to Super Page Cache (`wp-cloudflare-page-cache`) sidebar menu and
  all subpages: Dashboard, Settings, Import/Export, License.
- Fails open: if the super-user list is empty, the plugin changes nothing.

## Tech specs

### Gate: hardcoded super-user list

- `$GLOBALS['pw_hide_plugins_super_users'] = array( 'presswizards',
  'pwadmin' );` at the top of the file. Edit the list there; no constant
  or option needed.
- The current user's `user_login` (case-insensitive, whitespace-trimmed)
  must match an entry. Match = full access, no match = SPC settings hidden.

### Hooks used

- `admin_menu` at priority **999** — runs after SPC registers its pages
  (SPC hooks `admin_menu` at default priority 10 in
  `src/Modules/Dashboard.php::add_admin_menu_pages`), then calls:
  - `remove_menu_page( 'super-page-cache' )`
  - `remove_submenu_page( 'super-page-cache',
    'super-page-cache[-settings|-import-export|-license]' )`
- `current_screen` — direct-URL guard: any admin screen whose id contains
  `super-page-cache` returns HTTP 403 via `wp_die()` for non-super users.
  Exempt: AJAX requests (`DOING_AJAX`) and non-admin contexts.
- Deliberately untouched: `admin_bar_menu` (SPC registers its toolbar
  purge items there at `PHP_INT_MAX` in `src/Modules/Admin.php`), so the
  normal clear-cache admin-bar links keep working for all users.

## Deployment

Manual per-site copy into `wp-content/mu-plugins/` (owned by the site
user). Targeted sites only — Enhance cluster + 3 Plesk servers, per
operator selection. No fleet-wide rollout and no transfer-script wiring
(unlike the Watchdog sibling).
