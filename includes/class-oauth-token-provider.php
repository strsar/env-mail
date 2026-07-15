<?php

use PHPMailer\PHPMailer\OAuthTokenProvider;

if(!defined('ABSPATH')) {
  exit;
}

final class Env_Mail_OAuth_Token_Provider implements OAuthTokenProvider {
  private $config = [];

  public function __construct(array $config) {
    $this->config = $config;
  }

  public function getOauth64() {
    $token = $this->get_access_token();

    return base64_encode('user=' . $this->config['email'] . "\001auth=Bearer " . $token . "\001\001");
  }

  private function get_access_token() {
    $cached_token = get_transient($this->get_cache_key());

    if(is_array($cached_token) && !empty($cached_token['access_token'])) {
      return $cached_token['access_token'];
    }

    $response = wp_remote_post($this->get_token_url(), [
      'timeout' => 20,
      'body' => $this->get_token_request_body(),
    ]);

    if(is_wp_error($response)) {
      throw new Exception($response->get_error_message());
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if($status_code < 200 || $status_code >= 300 || empty($body['access_token'])) {
      throw new Exception(self::format_error_message($body, $status_code));
    }

    $expires_in = !empty($body['expires_in']) ? absint($body['expires_in']) : HOUR_IN_SECONDS;
    $ttl = max(60, $expires_in - 60);

    set_transient($this->get_cache_key(), [
      'access_token' => (string) $body['access_token'],
    ], $ttl);

    return (string) $body['access_token'];
  }

  public static function clear_cached_token(array $config) {
    delete_transient(self::get_cache_key_for($config));
  }

  public static function get_default_scope($provider, $grant_type = 'refresh_token') {
    if($provider === 'gmail') {
      return 'https://mail.google.com/';
    }

    if($grant_type === 'client_credentials') {
      return 'https://outlook.office365.com/.default';
    }

    return 'https://outlook.office.com/SMTP.Send offline_access';
  }

  public static function get_token_url_for(array $config) {
    if(!empty($config['provider']) && $config['provider'] === 'gmail') {
      return 'https://oauth2.googleapis.com/token';
    }

    $tenant = !empty($config['tenant']) ? $config['tenant'] : 'common';

    return 'https://login.microsoftonline.com/' . rawurlencode($tenant) . '/oauth2/v2.0/token';
  }

  public static function format_error_message($body, $status_code, $prefix = 'OAuth token request failed') {
    $message = $prefix;

    if($status_code) {
      $message .= ' (' . absint($status_code) . ')';
    }

    if(is_array($body)) {
      if(!empty($body['error'])) {
        $message .= ': ' . sanitize_text_field((string) $body['error']);
      }

      if(!empty($body['error_description'])) {
        $message .= ' ' . wp_strip_all_tags((string) $body['error_description']);
      }
    }

    return trim($message);
  }

  private function get_cache_key() {
    return self::get_cache_key_for($this->config);
  }

  private static function get_cache_key_for(array $config) {
    return 'env_mail_oauth_' . md5(wp_json_encode([
      'provider' => isset($config['provider']) ? $config['provider'] : '',
      'grant_type' => isset($config['grant_type']) ? $config['grant_type'] : '',
      'email' => isset($config['email']) ? $config['email'] : '',
      'tenant' => isset($config['tenant']) ? $config['tenant'] : '',
      'client_id' => isset($config['client_id']) ? $config['client_id'] : '',
    ]));
  }

  private function get_token_url() {
    return self::get_token_url_for($this->config);
  }

  private function get_token_request_body() {
    if($this->config['provider'] === 'gmail') {
      $body = [
        'client_id' => $this->config['client_id'],
        'client_secret' => $this->config['client_secret'],
        'refresh_token' => $this->config['refresh_token'],
        'grant_type' => 'refresh_token',
      ];

      if(!empty($this->config['scope'])) {
        $body['scope'] = $this->config['scope'];
      }

      return $body;
    }

    if($this->config['grant_type'] === 'client_credentials') {
      return [
        'client_id' => $this->config['client_id'],
        'client_secret' => $this->config['client_secret'],
        'scope' => $this->config['scope'] ?: self::get_default_scope($this->config['provider'], 'client_credentials'),
        'grant_type' => 'client_credentials',
      ];
    }

    return [
      'client_id' => $this->config['client_id'],
      'client_secret' => $this->config['client_secret'],
      'refresh_token' => $this->config['refresh_token'],
      'scope' => $this->config['scope'] ?: self::get_default_scope($this->config['provider'], 'refresh_token'),
      'grant_type' => 'refresh_token',
    ];
  }
}
