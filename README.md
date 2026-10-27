# pw-hide-plugins

Drop-in MU plugins for selected sites on the Press Wizards Enhance cluster
and Plesk servers.

## Summary

`pw-hide-settings.php` hides configured settings pages (sidebar menus +
direct URLs), upsell admin notices, and whole plugin rows, plus Novamira
toolbar items, from wp-admin users not in the PW super-user list, while
leaving the admin-bar "purge cache" links fully working.

Unlike the Watchdog-fleet sibling (`wdshelp-hide-plugins`, gated on the
`WATCHDOG_HOSTING_SUPER_USERS` constant), this variant carries its own
username list — no WP constant required on the target site.

## Details

- Single file, must-use plugin: `pw-hide-settings.php` → the site's
  `wp-content/mu-plugins/`. MU plugins always load and cannot be disabled
  from wp-admin.
- Applies to Super Page Cache (`wp-cloudflare-page-cache`) sidebar menu and
  all subpages (Dashboard, Settings, Import/Export, License), plus the
  Novamira menu tree (`novamira-connect` and every subpage) and all
  `novamira-*` toolbar nodes.
- Squashes upsell admin notices for non-super users: Novamira's
  `novamira_render_pro_welcome_notice` ("Novamira Pro is here."), and the
  Themeisle SDK "Connect your AI agent" notice (object-method hook stripped
  by class scan on `admin_notices` priority 9, since the SDK re-registers
  itself late). SPC's in-page AI upsell needs no handling — it only renders
  inside SPC pages, which are blocked.
- Hides whole plugin rows from plugins.php for non-super users (currently
  `novamira/novamira.php`), and strips the SDK "AI agent" row-meta link
  (`ti-ai-connect-open`) from every plugin's row.
- Fails open: if the super-user list is empty, the plugin changes nothing.

## Tech specs

### Gate: hardcoded super-user list

- `$GLOBALS['pw_hide_plugins_super_users'] = array( 'presswizards',
  'pwadmin' );` at the top of the file. Edit the list there; no constant
  or option needed.
- The current user's `user_login` (case-insensitive, whitespace-trimmed)
  must match an entry. Match = full access, no match = configured menus,
  toolbar nodes, and screens hidden.

### Hooks used

- `admin_menu` at priority **999** — runs after plugins register their
  pages, then for each slug in `$pw_hide_plugins_hidden_menus` removes
  every submenu entry plus the parent menu itself.
- `wp_before_admin_bar_render` at priority **999** — strips every toolbar
  node whose id starts with a `$pw_hide_plugins_hidden_bar_nodes` prefix.
  Runs at render time (after all plugins added nodes), which a plain
  `admin_bar_menu` hook cannot guarantee since MU plugins load first.
- `current_screen` — direct-URL guard: any admin screen whose id contains
  a `$pw_hide_plugins_blocked_screens` needle returns HTTP 403 via
  `wp_die()` for non-super users. Exempt: AJAX requests (`DOING_AJAX`)
  and non-admin contexts.
- Deliberately untouched: SPC's `admin_bar_menu` purge items, so the
  normal clear-cache admin-bar links keep working for all users.

## Deployment

Manual per-site copy into `wp-content/mu-plugins/` (owned by the site
user). Targeted sites only — Enhance cluster + 3 Plesk servers, per
operator selection. No fleet-wide rollout and no transfer-script wiring
(unlike the Watchdog sibling).
