<?php
/**
 * Plugin Name: [ENV] Mail
 * Plugin URI: https://github.com/strsar/env-mail
 * Description: Adds support for configuring outgoing email with SMTP or OAuth for Gmail and Office 365.
 * Version: 0.1.0
 * Author: Envisionit, Scott Trsar
 * Author URI: https://envisionitagency.com/
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Tested up to: 6.8
 * Update URI: https://github.com/strsar/env-mail
 * GitHub Plugin URI: https://github.com/strsar/env-mail
 * Primary Branch: main
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: env-mail
 * Developer: Scott Trsar
 * Contact: https://www.linkedin.com/in/trsar
 * Github: https://github.com/strsar
 */

if(!defined('ABSPATH')) {
  exit;
}

define('ENV_MAIL_FILE', __FILE__);
define('ENV_MAIL_DIR', plugin_dir_path(__FILE__));

require_once ABSPATH . WPINC . '/PHPMailer/OAuthTokenProvider.php';
require_once ENV_MAIL_DIR . 'includes/class-oauth-token-provider.php';
require_once ENV_MAIL_DIR . 'includes/class-settings.php';
require_once ENV_MAIL_DIR . 'includes/class-plugin.php';

Env_Mail_Plugin::instance();
