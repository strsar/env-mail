<?php

use YahnisElsts\PluginUpdateChecker\v5p7\PucFactory;

if(!defined('ABSPATH')) {
  exit;
}

final class Env_Mail_Plugin {
  const VERSION = '0.1.0';
  const GITHUB_TOKEN = '';

  private static $instance = null;
  private $config = [];
  private $update_checker = null;

  public static function instance() {
    if(self::$instance === null) {
      self::$instance = new self();
    }

    return self::$instance;
  }

  private function __construct() {
    Env_Mail_Settings::register();
    $this->register_update_checker();
    $this->config = $this->load_config();

    if(empty($this->config['enabled'])) {
      return;
    }

    if(!defined('ENV_MAIL_ACTIVE')) {
      define('ENV_MAIL_ACTIVE', true);
    }

    add_action('phpmailer_init', [$this, 'configure_mailer']);
  }

  private function register_update_checker() {
    if($this->update_checker !== null) {
      return;
    }

    require_once ENV_MAIL_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';

    $this->update_checker = PucFactory::buildUpdateChecker(
      'https://github.com/strsar/env-mail/',
      ENV_MAIL_FILE,
      'env-mail'
    );

    $this->update_checker->setBranch('main');

    $token = $this->get_update_token();

    if($token !== '') {
      $this->update_checker->setAuthentication($token);
    }
  }

  private function get_update_token() {
    $token = self::GITHUB_TOKEN;

    if($token === '') {
      $token = apply_filters('env_mail_github_token', '');
    }

    return is_string($token) ? trim($token) : '';
  }

  private function load_config() {
    $config = Env_Mail_Settings::get_options();

    $config['enabled'] = $this->to_bool($config['enabled']);
    $config['mailer'] = $config['mailer'] === 'oauth' ? 'oauth' : 'smtp';
    $config['from_email'] = sanitize_email((string) $config['from_email']);
    $config['from_name'] = sanitize_text_field((string) $config['from_name']);
    $config['debug'] = absint($config['debug']);

    $config['smtp']['host'] = sanitize_text_field((string) $config['smtp']['host']);
    $config['smtp']['port'] = absint($config['smtp']['port']);
    $config['smtp']['secure'] = $this->sanitize_secure((string) $config['smtp']['secure']);
    $config['smtp']['auth'] = $this->to_bool($config['smtp']['auth']);
    $config['smtp']['username'] = sanitize_text_field((string) $config['smtp']['username']);
    $config['smtp']['password'] = (string) $config['smtp']['password'];

    $config['oauth']['provider'] = $this->sanitize_provider((string) $config['oauth']['provider']);
    $config['oauth']['grant_type'] = $this->sanitize_grant_type((string) $config['oauth']['grant_type']);
    $config['oauth']['email'] = sanitize_email((string) $config['oauth']['email']);
    $config['oauth']['client_id'] = sanitize_text_field((string) $config['oauth']['client_id']);
    $config['oauth']['client_secret'] = (string) $config['oauth']['client_secret'];
    $config['oauth']['refresh_token'] = (string) $config['oauth']['refresh_token'];
    $config['oauth']['tenant'] = sanitize_text_field((string) $config['oauth']['tenant']);
    $config['oauth']['scope'] = sanitize_text_field((string) $config['oauth']['scope']);
    $config['oauth']['host'] = sanitize_text_field((string) $config['oauth']['host']);
    $config['oauth']['port'] = absint($config['oauth']['port']);
    $config['oauth']['secure'] = $this->sanitize_secure((string) $config['oauth']['secure']);

    return $config;
  }

  private function to_bool($value) {
    if(is_bool($value)) {
      return $value;
    }

    if(is_numeric($value)) {
      return ((int) $value) === 1;
    }

    $value = strtolower(trim((string) $value));

    return in_array($value, ['1', 'true', 'yes', 'on'], true);
  }

  private function sanitize_secure($value) {
    $value = strtolower(trim($value));

    if(in_array($value, ['ssl', 'tls'], true)) {
      return $value;
    }

    return '';
  }

  private function sanitize_provider($value) {
    $value = strtolower(trim($value));

    if(in_array($value, ['gmail', 'office365'], true)) {
      return $value;
    }

    return '';
  }

  private function sanitize_grant_type($value) {
    $value = strtolower(trim($value));

    if(in_array($value, ['refresh_token', 'client_credentials'], true)) {
      return $value;
    }

    return 'refresh_token';
  }

  public function configure_mailer($phpmailer) {
    if($this->config['mailer'] === 'oauth') {
      $this->configure_oauth_mailer($phpmailer);
      return;
    }

    $this->configure_smtp_mailer($phpmailer);
  }

  private function configure_smtp_mailer($phpmailer) {
    $smtp = $this->config['smtp'];

    if($smtp['host'] === '') {
      return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host = $smtp['host'];
    $phpmailer->Port = $smtp['port'] ?: 587;
    $phpmailer->SMTPAuth = $smtp['auth'];
    $phpmailer->SMTPSecure = $smtp['secure'];
    $phpmailer->Username = $smtp['username'];
    $phpmailer->Password = $smtp['password'];
    $phpmailer->AuthType = '';

    $this->apply_common_settings($phpmailer);
  }

  private function configure_oauth_mailer($phpmailer) {
    $oauth = $this->get_oauth_config();

    if(empty($oauth['provider']) || empty($oauth['email']) || empty($oauth['client_id']) || empty($oauth['client_secret'])) {
      return;
    }

    if($oauth['grant_type'] === 'refresh_token' && empty($oauth['refresh_token'])) {
      return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host = $oauth['host'];
    $phpmailer->Port = $oauth['port'];
    $phpmailer->SMTPAuth = true;
    $phpmailer->SMTPSecure = $oauth['secure'];
    $phpmailer->AuthType = 'XOAUTH2';
    $phpmailer->Username = $oauth['email'];
    $phpmailer->Password = '';
    $phpmailer->setOAuth(new Env_Mail_OAuth_Token_Provider($oauth));

    $this->apply_common_settings($phpmailer);
  }

  private function apply_common_settings($phpmailer) {
    if($this->config['from_email'] !== '') {
      $phpmailer->From = $this->config['from_email'];
      $phpmailer->Sender = $this->config['from_email'];
    }

    if($this->config['from_name'] !== '') {
      $phpmailer->FromName = $this->config['from_name'];
    }

    if($this->config['debug'] > 0) {
      $phpmailer->SMTPDebug = $this->config['debug'];
    }
  }

  private function get_oauth_config() {
    $oauth = $this->config['oauth'];
    $provider_defaults = $this->get_oauth_provider_defaults($oauth['provider']);

    $oauth['host'] = $oauth['host'] ?: $provider_defaults['host'];
    $oauth['port'] = $oauth['port'] ?: $provider_defaults['port'];
    $oauth['secure'] = $oauth['secure'] ?: $provider_defaults['secure'];
    $oauth['email'] = $oauth['email'] ?: $this->config['from_email'];

    return $oauth;
  }

  private function get_oauth_provider_defaults($provider) {
    if($provider === 'gmail') {
      return [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'secure' => 'tls',
      ];
    }

    if($provider === 'office365') {
      return [
        'host' => 'smtp.office365.com',
        'port' => 587,
        'secure' => 'tls',
      ];
    }

    return [
      'host' => '',
      'port' => 587,
      'secure' => 'tls',
    ];
  }
}
