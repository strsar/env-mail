# [ENV] Mail

WordPress plugin for configuring outgoing email with SMTP or OAuth for Gmail and Office 365.

## Features

- SMTP mail configuration from `Settings > Mail`
- OAuth support for Gmail and Office 365
- Built-in OAuth callback flow to save refresh tokens
- Test email screen using `wp_mail()`
- Simple plugin settings link in the Plugins screen

## Recommended GitHub Update Workflow

This plugin includes a built-in updater using `plugin-update-checker`.

For a public GitHub repo, the recommended setup is:

1. Keep this plugin as its own repository with the plugin files at the repo root.
2. Bump the plugin version before each release.
3. Push a Git tag like `0.1.0` or `v0.1.0`.
4. Optionally publish a GitHub release for that tag.

## Release Checklist

For each new release:

1. Update `Version` in `env-mail.php`.
2. Update `Env_Mail_Plugin::VERSION` in `includes/class-plugin.php`.
3. Update `Stable tag` and changelog entries in `readme.txt`.
4. Commit and push.
5. Create and push the Git tag.
6. Publish the GitHub release if you want release notes in the update modal.

## OAuth Redirect URI

When using the built-in refresh token flow, register this redirect URI in your OAuth app:

`/wp-admin/options-general.php?page=env-mail&tab=settings&action=oauth-callback`

On a local site that becomes:

`https://your-site.example/wp-admin/options-general.php?page=env-mail&tab=settings&action=oauth-callback`

## Changelog

### 0.1.1

- Updated the plugin icon asset
- Kept the icon for the WordPress Plugins screen only

### 0.1.0

- Initial public release
- Added SMTP and OAuth mailer settings
- Added built-in OAuth authorization callback flow for Gmail and Office 365
- Added test email screen
- Added built-in GitHub updater using `plugin-update-checker`
