=== [ENV] Mail ===
Contributors: strsar
Tags: smtp, email, mail, oauth, office365
Requires at least: 6.4
Tested up to: 7.0.1
Stable tag: 0.1.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Configure outgoing WordPress email with SMTP or OAuth for Gmail and Office 365.

== Description ==

[ENV] Mail adds a simple settings screen under `Settings > Mail` for configuring outgoing mail in WordPress.

Features include:

* SMTP configuration
* OAuth support for Gmail
* OAuth support for Office 365
* Built-in refresh token callback flow
* Test email screen using `wp_mail()`

This plugin is intended to live in a standalone GitHub repository so it can be versioned and released across multiple WordPress installs.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/env-mail/`, or install it from your deployment workflow.
2. Activate the plugin in WordPress.
3. Go to `Settings > Mail`.
4. Configure either SMTP or OAuth settings.
5. Send a test email from the `Send Test Email` tab.

== Frequently Asked Questions ==

= Can this plugin update from GitHub? =

Yes. This plugin includes a built-in GitHub updater using `plugin-update-checker`.
For a public repository, no extra token or site configuration is required.

= Does this plugin generate its own refresh token? =

Yes. When OAuth is set to `Refresh Token`, the settings page includes a redirect URI and an authorization button that can store the refresh token after consent.

= What redirect URI should I register in Google or Microsoft? =

Use the redirect URI shown on `Settings > Mail`. It follows this format:

`https://your-site.example/wp-admin/options-general.php?page=env-mail&tab=settings&action=oauth-callback`

== Changelog ==

= 0.1.1 =

* Updated the plugin icon asset.
* Removed the icon from the GitHub README while keeping it in WordPress.
* Added the standard `assets/icon.svg` filename so the icon appears on WordPress update screens.
* Marked the plugin as tested up to WordPress 7.0.1.

= 0.1.0 =

* Initial public release.
* Added SMTP and OAuth mail configuration.
* Added built-in OAuth authorization callback flow for Gmail and Office 365.
* Added settings screen and test email screen.
* Added built-in updater using `plugin-update-checker`.

== Upgrade Notice ==

= 0.1.1 =

Updated the plugin icon and marked compatibility through WordPress 7.0.1.

= 0.1.0 =

Initial public release with built-in GitHub update support.
