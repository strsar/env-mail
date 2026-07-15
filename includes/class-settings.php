<?php

if(!defined('ABSPATH')) {
  exit;
}

final class Env_Mail_Settings {
  const OPTION = 'env_mail_options';

  public static function defaults() {
    return [
      'enabled' => '0',
      'mailer' => 'smtp',
      'from_email' => '',
      'from_name' => '',
      'debug' => '0',
      'smtp' => [
        'host' => '',
        'port' => '587',
        'secure' => 'tls',
        'auth' => '1',
        'username' => '',
        'password' => '',
      ],
      'oauth' => [
        'provider' => 'gmail',
        'grant_type' => 'refresh_token',
        'email' => '',
        'client_id' => '',
        'client_secret' => '',
        'refresh_token' => '',
        'tenant' => 'common',
        'scope' => '',
        'host' => '',
        'port' => '',
        'secure' => '',
      ],
    ];
  }

  public static function get_options() {
    return array_replace_recursive(self::defaults(), get_option(self::OPTION, []));
  }

  public static function register() {
    add_action('admin_init', [__CLASS__, 'register_settings']);
    add_action('admin_init', [__CLASS__, 'handle_oauth_request']);
    add_action('admin_menu', [__CLASS__, 'add_menu']);
    add_action('admin_post_env_mail_send_test', [__CLASS__, 'handle_send_test']);
    add_filter('plugin_action_links_' . plugin_basename(ENV_MAIL_FILE), [__CLASS__, 'plugin_action_links']);
  }

  public static function register_settings() {
    register_setting('env_mail_settings', self::OPTION, [__CLASS__, 'sanitize_options']);
  }

  public static function add_menu() {
    add_options_page('Mail', 'Mail', 'manage_options', 'env-mail', [__CLASS__, 'render_page']);
  }

  public static function plugin_action_links($links) {
    array_unshift($links, '<a href="' . esc_url(admin_url('options-general.php?page=env-mail')) . '">Settings</a>');
    return $links;
  }

  public static function sanitize_options($input) {
    $defaults = self::defaults();
    $existing = self::get_options();
    $output = [];

    $output['enabled'] = !empty($input['enabled']) ? '1' : '0';
    $output['mailer'] = (!empty($input['mailer']) && $input['mailer'] === 'oauth') ? 'oauth' : 'smtp';
    $output['from_email'] = isset($input['from_email']) ? sanitize_email(wp_unslash($input['from_email'])) : '';
    $output['from_name'] = isset($input['from_name']) ? sanitize_text_field(wp_unslash($input['from_name'])) : '';
    $output['debug'] = isset($input['debug']) ? (string) max(0, min(4, absint($input['debug']))) : $defaults['debug'];

    $smtp_input = isset($input['smtp']) && is_array($input['smtp']) ? $input['smtp'] : $existing['smtp'];
    $output['smtp'] = [
      'host' => isset($smtp_input['host']) ? sanitize_text_field(wp_unslash($smtp_input['host'])) : '',
      'port' => isset($smtp_input['port']) ? (string) max(1, absint($smtp_input['port'])) : $defaults['smtp']['port'],
      'secure' => self::sanitize_secure(isset($smtp_input['secure']) ? wp_unslash($smtp_input['secure']) : $defaults['smtp']['secure']),
      'auth' => !empty($smtp_input['auth']) ? '1' : '0',
      'username' => isset($smtp_input['username']) ? sanitize_text_field(wp_unslash($smtp_input['username'])) : '',
      'password' => isset($smtp_input['password']) ? sanitize_text_field(wp_unslash($smtp_input['password'])) : '',
    ];

    $oauth_input = isset($input['oauth']) && is_array($input['oauth']) ? $input['oauth'] : $existing['oauth'];
    $output['oauth'] = [
      'provider' => self::sanitize_provider(isset($oauth_input['provider']) ? wp_unslash($oauth_input['provider']) : $defaults['oauth']['provider']),
      'grant_type' => self::sanitize_grant_type(isset($oauth_input['grant_type']) ? wp_unslash($oauth_input['grant_type']) : $defaults['oauth']['grant_type']),
      'email' => isset($oauth_input['email']) ? sanitize_email(wp_unslash($oauth_input['email'])) : '',
      'client_id' => isset($oauth_input['client_id']) ? sanitize_text_field(wp_unslash($oauth_input['client_id'])) : '',
      'client_secret' => isset($oauth_input['client_secret']) ? sanitize_text_field(wp_unslash($oauth_input['client_secret'])) : '',
      'refresh_token' => isset($oauth_input['refresh_token']) ? sanitize_textarea_field(wp_unslash($oauth_input['refresh_token'])) : '',
      'tenant' => isset($oauth_input['tenant']) ? sanitize_text_field(wp_unslash($oauth_input['tenant'])) : $defaults['oauth']['tenant'],
      'scope' => isset($oauth_input['scope']) ? sanitize_text_field(wp_unslash($oauth_input['scope'])) : '',
      'host' => isset($oauth_input['host']) ? sanitize_text_field(wp_unslash($oauth_input['host'])) : '',
      'port' => (isset($oauth_input['port']) && trim((string) $oauth_input['port']) !== '') ? (string) max(1, absint($oauth_input['port'])) : '',
      'secure' => self::sanitize_secure(isset($oauth_input['secure']) ? wp_unslash($oauth_input['secure']) : ''),
    ];

    return $output;
  }

  private static function sanitize_secure($value) {
    $value = strtolower(trim((string) $value));
    return in_array($value, ['ssl', 'tls'], true) ? $value : '';
  }

  private static function sanitize_provider($value) {
    $value = strtolower(trim((string) $value));
    return in_array($value, ['gmail', 'office365'], true) ? $value : 'gmail';
  }

  private static function sanitize_grant_type($value) {
    $value = strtolower(trim((string) $value));
    return in_array($value, ['refresh_token', 'client_credentials'], true) ? $value : 'refresh_token';
  }

  private static function get_current_tab() {
    $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'settings';
    return in_array($tab, ['settings', 'test'], true) ? $tab : 'settings';
  }

  private static function get_settings_url($args = []) {
    return add_query_arg($args, admin_url('options-general.php?page=env-mail'));
  }

  public static function get_oauth_callback_url() {
    return self::get_settings_url([
      'tab' => 'settings',
      'action' => 'oauth-callback',
    ]);
  }

  private static function is_env_mail_page_request() {
    if(!is_admin()) {
      return false;
    }

    return isset($_GET['page']) && sanitize_key(wp_unslash($_GET['page'])) === 'env-mail';
  }

  private static function get_oauth_notice_key() {
    return 'env_mail_oauth_notice_' . get_current_user_id();
  }

  private static function store_oauth_notice($success, $message) {
    set_transient(self::get_oauth_notice_key(), [
      'success' => (bool) $success,
      'message' => (string) $message,
    ], 60);
  }

  private static function consume_oauth_notice() {
    $notice = get_transient(self::get_oauth_notice_key());
    delete_transient(self::get_oauth_notice_key());

    return is_array($notice) ? $notice : null;
  }

  private static function get_oauth_state_key($state) {
    return 'env_mail_oauth_state_' . md5((string) $state);
  }

  private static function get_oauth_scope($options) {
    if(!empty($options['oauth']['scope'])) {
      return $options['oauth']['scope'];
    }

    return Env_Mail_OAuth_Token_Provider::get_default_scope($options['oauth']['provider'], $options['oauth']['grant_type']);
  }

  private static function get_oauth_login_hint($options) {
    if(!empty($options['oauth']['email'])) {
      return $options['oauth']['email'];
    }

    if(!empty($options['from_email'])) {
      return $options['from_email'];
    }

    return '';
  }

  private static function create_code_verifier() {
    return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
  }

  private static function create_code_challenge($verifier) {
    return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
  }

  private static function get_oauth_authorize_url($options, $redirect_uri, $state, $code_challenge = '') {
    if($options['oauth']['provider'] === 'gmail') {
      $args = [
        'client_id' => $options['oauth']['client_id'],
        'redirect_uri' => $redirect_uri,
        'response_type' => 'code',
        'scope' => self::get_oauth_scope($options),
        'access_type' => 'offline',
        'prompt' => 'consent',
        'state' => $state,
        'include_granted_scopes' => 'true',
      ];

      $login_hint = self::get_oauth_login_hint($options);

      if($login_hint !== '') {
        $args['login_hint'] = $login_hint;
      }

      return add_query_arg($args, 'https://accounts.google.com/o/oauth2/v2/auth');
    }

    $args = [
      'client_id' => $options['oauth']['client_id'],
      'response_type' => 'code',
      'redirect_uri' => $redirect_uri,
      'response_mode' => 'query',
      'scope' => self::get_oauth_scope($options),
      'state' => $state,
      'prompt' => 'consent',
    ];

    $login_hint = self::get_oauth_login_hint($options);

    if($login_hint !== '') {
      $args['login_hint'] = $login_hint;
    }

    if($code_challenge !== '') {
      $args['code_challenge'] = $code_challenge;
      $args['code_challenge_method'] = 'S256';
    }

    return add_query_arg($args, 'https://login.microsoftonline.com/' . rawurlencode($options['oauth']['tenant']) . '/oauth2/v2.0/authorize');
  }

  private static function exchange_oauth_code($options, $code, $redirect_uri, $code_verifier = '') {
    $body = [
      'client_id' => $options['oauth']['client_id'],
      'client_secret' => $options['oauth']['client_secret'],
      'code' => $code,
      'redirect_uri' => $redirect_uri,
      'grant_type' => 'authorization_code',
    ];

    if($options['oauth']['provider'] === 'office365') {
      $body['scope'] = self::get_oauth_scope($options);

      if($code_verifier !== '') {
        $body['code_verifier'] = $code_verifier;
      }
    }

    $response = wp_remote_post(Env_Mail_OAuth_Token_Provider::get_token_url_for($options['oauth']), [
      'timeout' => 20,
      'body' => $body,
    ]);

    if(is_wp_error($response)) {
      return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $payload = json_decode(wp_remote_retrieve_body($response), true);

    if($status_code < 200 || $status_code >= 300 || !is_array($payload) || empty($payload['refresh_token'])) {
      return new WP_Error('oauth_exchange_failed', Env_Mail_OAuth_Token_Provider::format_error_message($payload, $status_code, 'OAuth authorization failed'));
    }

    return $payload;
  }

  public static function handle_oauth_request() {
    if(!self::is_env_mail_page_request() || !current_user_can('manage_options')) {
      return;
    }

    $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';

    if($action === 'oauth-start') {
      check_admin_referer('env_mail_oauth_start');

      $options = self::get_options();

      if($options['oauth']['grant_type'] !== 'refresh_token') {
        self::store_oauth_notice(false, 'OAuth connect is only used when Grant Type is set to Refresh Token.');
        wp_safe_redirect(self::get_settings_url(['tab' => 'settings']));
        exit;
      }

      if(empty($options['oauth']['client_id']) || empty($options['oauth']['client_secret'])) {
        self::store_oauth_notice(false, 'Save the OAuth Client ID and Client Secret before connecting.');
        wp_safe_redirect(self::get_settings_url(['tab' => 'settings']));
        exit;
      }

      $redirect_uri = self::get_oauth_callback_url();
      $state = wp_generate_password(64, false, false);
      $code_verifier = ($options['oauth']['provider'] === 'office365') ? self::create_code_verifier() : '';

      set_transient(self::get_oauth_state_key($state), [
        'user_id' => get_current_user_id(),
        'provider' => $options['oauth']['provider'],
        'tenant' => $options['oauth']['tenant'],
        'client_id' => $options['oauth']['client_id'],
        'redirect_uri' => $redirect_uri,
        'code_verifier' => $code_verifier,
      ], 10 * MINUTE_IN_SECONDS);

      $authorize_url = self::get_oauth_authorize_url(
        $options,
        $redirect_uri,
        $state,
        $code_verifier !== '' ? self::create_code_challenge($code_verifier) : ''
      );

      wp_safe_redirect($authorize_url);
      exit;
    }

    if($action !== 'oauth-callback') {
      return;
    }

    $settings_url = self::get_settings_url(['tab' => 'settings']);
    $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
    $oauth_state = $state !== '' ? get_transient(self::get_oauth_state_key($state)) : false;

    if(!is_array($oauth_state) || empty($oauth_state['user_id']) || (int) $oauth_state['user_id'] !== get_current_user_id()) {
      self::store_oauth_notice(false, 'OAuth state is missing or has expired. Please try again.');
      wp_safe_redirect($settings_url);
      exit;
    }

    delete_transient(self::get_oauth_state_key($state));

    if(!empty($_GET['error'])) {
      $message = sanitize_text_field(wp_unslash($_GET['error']));

      if(!empty($_GET['error_description'])) {
        $message .= ': ' . sanitize_text_field(wp_unslash($_GET['error_description']));
      }

      self::store_oauth_notice(false, $message);
      wp_safe_redirect($settings_url);
      exit;
    }

    $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';

    if($code === '') {
      self::store_oauth_notice(false, 'OAuth authorization code was not returned.');
      wp_safe_redirect($settings_url);
      exit;
    }

    $options = self::get_options();
    $payload = self::exchange_oauth_code($options, $code, $oauth_state['redirect_uri'], isset($oauth_state['code_verifier']) ? $oauth_state['code_verifier'] : '');

    if(is_wp_error($payload)) {
      self::store_oauth_notice(false, $payload->get_error_message());
      wp_safe_redirect($settings_url);
      exit;
    }

    $options['oauth']['refresh_token'] = sanitize_textarea_field((string) $payload['refresh_token']);
    update_option(self::OPTION, $options);
    Env_Mail_OAuth_Token_Provider::clear_cached_token($options['oauth']);

    self::store_oauth_notice(true, 'Refresh token saved successfully.');
    wp_safe_redirect($settings_url);
    exit;
  }

  public static function render_page() {
    if(!current_user_can('manage_options')) {
      return;
    }

    $options = self::get_options();
    $current_tab = self::get_current_tab();
    $oauth_notice = self::consume_oauth_notice();
    $result = get_transient('env_mail_test_result');
    delete_transient('env_mail_test_result');
    $default_to = wp_get_current_user()->user_email;
    $settings_url = self::get_settings_url(['tab' => 'settings']);
    $test_url = self::get_settings_url(['tab' => 'test']);
    $oauth_callback_url = self::get_oauth_callback_url();
    $oauth_connect_url = wp_nonce_url(self::get_settings_url([
      'tab' => 'settings',
      'action' => 'oauth-start',
    ]), 'env_mail_oauth_start');
    ?>
    <div class="wrap">
      <h1>Mail</h1>
      <p>Configure the active PHPMailer transport for outgoing WordPress email.</p>

      <h2 class="nav-tab-wrapper">
        <a href="<?php echo esc_url($settings_url); ?>" class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
        <a href="<?php echo esc_url($test_url); ?>" class="nav-tab <?php echo $current_tab === 'test' ? 'nav-tab-active' : ''; ?>">Send Test Email</a>
      </h2>

      <?php if($oauth_notice): ?>
        <div class="notice notice-<?php echo esc_attr(!empty($oauth_notice['success']) ? 'success' : 'error'); ?> is-dismissible">
          <p><?php echo esc_html($oauth_notice['message']); ?></p>
        </div>
      <?php endif; ?>

      <?php if($result): ?>
        <div class="notice notice-<?php echo esc_attr(!empty($result['success']) ? 'success' : 'error'); ?> is-dismissible">
          <p><strong><?php echo !empty($result['success']) ? 'Success:' : 'Error:'; ?></strong> <?php echo esc_html($result['message']); ?></p>
          <?php if(!empty($result['debug'])): ?>
            <pre style="white-space:pre-wrap;"><?php echo esc_html($result['debug']); ?></pre>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if($current_tab === 'settings'): ?>
        <?php $oauth_port_value = ($options['oauth']['port'] === '0') ? '' : $options['oauth']['port']; ?>
        <form id="env-mail-settings-form" method="post" action="options.php">
          <?php settings_fields('env_mail_settings'); ?>

          <table class="form-table" role="presentation">
            <tr>
              <th scope="row">Enable Mailer</th>
              <td>
                <input type="hidden" name="<?php echo esc_attr(self::OPTION); ?>[enabled]" value="0">
                <label>
                  <input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[enabled]" value="1" <?php checked($options['enabled'], '1'); ?>>
                  Use this plugin to configure outgoing mail
                </label>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="env-mail-mailer">Auth Type</label></th>
              <td>
                <select id="env-mail-mailer" name="<?php echo esc_attr(self::OPTION); ?>[mailer]">
                  <option value="smtp" <?php selected($options['mailer'], 'smtp'); ?>>SMTP</option>
                  <option value="oauth" <?php selected($options['mailer'], 'oauth'); ?>>OAuth</option>
                </select>
                <p class="description">Choose standard SMTP credentials or OAuth for Gmail / Office 365.</p>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="env-mail-from-email">From Email</label></th>
              <td><input type="email" class="regular-text" id="env-mail-from-email" name="<?php echo esc_attr(self::OPTION); ?>[from_email]" value="<?php echo esc_attr($options['from_email']); ?>"></td>
            </tr>
            <tr>
              <th scope="row"><label for="env-mail-from-name">From Name</label></th>
              <td><input type="text" class="regular-text" id="env-mail-from-name" name="<?php echo esc_attr(self::OPTION); ?>[from_name]" value="<?php echo esc_attr($options['from_name']); ?>"></td>
            </tr>
            <tr>
              <th scope="row"><label for="env-mail-debug">SMTP Debug Level</label></th>
              <td>
                <select id="env-mail-debug" name="<?php echo esc_attr(self::OPTION); ?>[debug]">
                  <option value="0" <?php selected($options['debug'], '0'); ?>>0</option>
                  <option value="1" <?php selected($options['debug'], '1'); ?>>1</option>
                  <option value="2" <?php selected($options['debug'], '2'); ?>>2</option>
                  <option value="3" <?php selected($options['debug'], '3'); ?>>3</option>
                  <option value="4" <?php selected($options['debug'], '4'); ?>>4</option>
                </select>
              </td>
            </tr>
          </table>

          <div class="env-mail-section env-mail-section-smtp">
            <h2>SMTP</h2>
            <table class="form-table" role="presentation">
              <tr>
                <th scope="row"><label for="env-mail-smtp-host">Host</label></th>
                <td><input type="text" class="regular-text" id="env-mail-smtp-host" name="<?php echo esc_attr(self::OPTION); ?>[smtp][host]" value="<?php echo esc_attr($options['smtp']['host']); ?>"></td>
              </tr>
              <tr>
                <th scope="row"><label for="env-mail-smtp-port">Port</label></th>
                <td><input type="number" class="small-text" id="env-mail-smtp-port" name="<?php echo esc_attr(self::OPTION); ?>[smtp][port]" value="<?php echo esc_attr($options['smtp']['port']); ?>" min="1"></td>
              </tr>
              <tr>
                <th scope="row"><label for="env-mail-smtp-secure">Encryption</label></th>
                <td>
                  <select id="env-mail-smtp-secure" name="<?php echo esc_attr(self::OPTION); ?>[smtp][secure]">
                    <option value="" <?php selected($options['smtp']['secure'], ''); ?>>None</option>
                    <option value="tls" <?php selected($options['smtp']['secure'], 'tls'); ?>>TLS</option>
                    <option value="ssl" <?php selected($options['smtp']['secure'], 'ssl'); ?>>SSL</option>
                  </select>
                </td>
              </tr>
              <tr>
                <th scope="row">SMTP Auth</th>
                <td>
                  <input type="hidden" name="<?php echo esc_attr(self::OPTION); ?>[smtp][auth]" value="0">
                  <label>
                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[smtp][auth]" value="1" <?php checked($options['smtp']['auth'], '1'); ?>>
                    Enable SMTP authentication
                  </label>
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="env-mail-smtp-username">Username</label></th>
                <td><input type="text" class="regular-text" id="env-mail-smtp-username" name="<?php echo esc_attr(self::OPTION); ?>[smtp][username]" value="<?php echo esc_attr($options['smtp']['username']); ?>"></td>
              </tr>
              <tr>
                <th scope="row"><label for="env-mail-smtp-password">Password</label></th>
                <td><input type="password" class="regular-text" id="env-mail-smtp-password" name="<?php echo esc_attr(self::OPTION); ?>[smtp][password]" value="<?php echo esc_attr($options['smtp']['password']); ?>"></td>
              </tr>
            </table>
          </div>

          <div class="env-mail-section env-mail-section-oauth">
            <h2>OAuth</h2>
            <table class="form-table" role="presentation">
              <tr>
                <th scope="row"><label for="env-mail-oauth-provider">Provider</label></th>
                <td>
                  <select id="env-mail-oauth-provider" name="<?php echo esc_attr(self::OPTION); ?>[oauth][provider]">
                    <option value="gmail" <?php selected($options['oauth']['provider'], 'gmail'); ?>>Gmail</option>
                    <option value="office365" <?php selected($options['oauth']['provider'], 'office365'); ?>>Office 365</option>
                  </select>
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="env-mail-oauth-grant-type">Grant Type</label></th>
                <td>
                  <select id="env-mail-oauth-grant-type" name="<?php echo esc_attr(self::OPTION); ?>[oauth][grant_type]">
                    <option value="refresh_token" <?php selected($options['oauth']['grant_type'], 'refresh_token'); ?>>Refresh Token</option>
                    <option value="client_credentials" <?php selected($options['oauth']['grant_type'], 'client_credentials'); ?>>Client Credentials</option>
                  </select>
                  <p class="description">`client_credentials` is primarily for Office 365 tenant/app setups.</p>
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="env-mail-oauth-email">Mailbox Email</label></th>
                <td><input type="email" class="regular-text" id="env-mail-oauth-email" name="<?php echo esc_attr(self::OPTION); ?>[oauth][email]" value="<?php echo esc_attr($options['oauth']['email']); ?>"></td>
              </tr>
              <tr>
                <th scope="row"><label for="env-mail-oauth-client-id">Client ID</label></th>
                <td><input type="text" class="regular-text" id="env-mail-oauth-client-id" name="<?php echo esc_attr(self::OPTION); ?>[oauth][client_id]" value="<?php echo esc_attr($options['oauth']['client_id']); ?>"></td>
              </tr>
              <tr>
                <th scope="row"><label for="env-mail-oauth-client-secret">Client Secret</label></th>
                <td><input type="password" class="regular-text" id="env-mail-oauth-client-secret" name="<?php echo esc_attr(self::OPTION); ?>[oauth][client_secret]" value="<?php echo esc_attr($options['oauth']['client_secret']); ?>"></td>
              </tr>
              <tr class="env-mail-refresh-token-row">
                <th scope="row"><label for="env-mail-oauth-refresh-token">Refresh Token</label></th>
                <td><textarea class="large-text code" rows="4" id="env-mail-oauth-refresh-token" name="<?php echo esc_attr(self::OPTION); ?>[oauth][refresh_token]"><?php echo esc_textarea($options['oauth']['refresh_token']); ?></textarea></td>
              </tr>
              <tr class="env-mail-oauth-refresh-grant-row">
                <th scope="row"><label for="env-mail-oauth-callback-url">Redirect URI</label></th>
                <td>
                  <input type="text" class="large-text code" id="env-mail-oauth-callback-url" value="<?php echo esc_attr($oauth_callback_url); ?>" readonly>
                  <p class="description">Use this exact redirect URI in your Google or Microsoft app registration.</p>
                </td>
              </tr>
              <tr class="env-mail-oauth-refresh-grant-row">
                <th scope="row">Connect Account</th>
                <td>
                  <a href="<?php echo esc_url($oauth_connect_url); ?>" class="button button-secondary">Authorize and Save Refresh Token</a>
                  <p class="description">Save your Client ID and Client Secret first. This uses the currently saved OAuth settings.</p>
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="env-mail-oauth-tenant">Tenant</label></th>
                <td>
                  <input type="text" class="regular-text" id="env-mail-oauth-tenant" name="<?php echo esc_attr(self::OPTION); ?>[oauth][tenant]" value="<?php echo esc_attr($options['oauth']['tenant']); ?>">
                  <p class="description">Used for Office 365. Leave as `common` unless your app requires a specific tenant.</p>
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="env-mail-oauth-scope">Scope Override</label></th>
                <td><input type="text" class="large-text" id="env-mail-oauth-scope" name="<?php echo esc_attr(self::OPTION); ?>[oauth][scope]" value="<?php echo esc_attr($options['oauth']['scope']); ?>"></td>
              </tr>
              <tr>
                <th scope="row"><label for="env-mail-oauth-host">SMTP Host Override</label></th>
                <td><input type="text" class="regular-text" id="env-mail-oauth-host" name="<?php echo esc_attr(self::OPTION); ?>[oauth][host]" value="<?php echo esc_attr($options['oauth']['host']); ?>"></td>
              </tr>
              <tr>
                <th scope="row"><label for="env-mail-oauth-port">SMTP Port Override</label></th>
                <td><input type="number" class="small-text" id="env-mail-oauth-port" name="<?php echo esc_attr(self::OPTION); ?>[oauth][port]" value="<?php echo esc_attr($oauth_port_value); ?>" min="1"></td>
              </tr>
              <tr>
                <th scope="row"><label for="env-mail-oauth-secure">SMTP Encryption Override</label></th>
                <td>
                  <select id="env-mail-oauth-secure" name="<?php echo esc_attr(self::OPTION); ?>[oauth][secure]">
                    <option value="" <?php selected($options['oauth']['secure'], ''); ?>>Default</option>
                    <option value="tls" <?php selected($options['oauth']['secure'], 'tls'); ?>>TLS</option>
                    <option value="ssl" <?php selected($options['oauth']['secure'], 'ssl'); ?>>SSL</option>
                  </select>
                </td>
              </tr>
            </table>
          </div>

          <p class="submit">
            <button type="submit" form="env-mail-settings-form" class="button button-primary">Save Settings</button>
          </p>
        </form>
      <?php else: ?>
        <h2>Send Test Email</h2>
        <p>Use the current Mail settings to send a test message through <code>wp_mail()</code>.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <?php wp_nonce_field('env_mail_send_test'); ?>
          <input type="hidden" name="action" value="env_mail_send_test">
          <table class="form-table" role="presentation">
            <tr>
              <th scope="row"><label for="env-mail-test-to">To</label></th>
              <td><input type="email" class="regular-text" id="env-mail-test-to" name="to" value="<?php echo esc_attr($default_to); ?>" required></td>
            </tr>
            <tr>
              <th scope="row"><label for="env-mail-test-subject">Subject</label></th>
              <td><input type="text" class="regular-text" id="env-mail-test-subject" name="subject" value="Mail Test Email" required></td>
            </tr>
            <tr>
              <th scope="row"><label for="env-mail-test-message">Message</label></th>
              <td>
                <textarea class="large-text" rows="6" id="env-mail-test-message" name="message" required>This is a test email sent from WordPress using wp_mail().

Site: <?php echo esc_textarea(home_url('/')); ?>

Time: <?php echo esc_textarea(current_time('mysql')); ?></textarea>
              </td>
            </tr>
          </table>
          <?php submit_button('Send Test Email', 'secondary', 'submit', false); ?>
        </form>
      <?php endif; ?>
    </div>

    <script>
      (function() {
        var form = document.getElementById('env-mail-settings-form');
        var mailer = document.getElementById('env-mail-mailer');
        var grantType = document.getElementById('env-mail-oauth-grant-type');
        var smtpSection = document.querySelector('.env-mail-section-smtp');
        var oauthSection = document.querySelector('.env-mail-section-oauth');
        var refreshTokenRow = document.querySelector('.env-mail-refresh-token-row');
        var refreshGrantRows = document.querySelectorAll('.env-mail-oauth-refresh-grant-row');

        function setSectionDisabled(section, disabled) {
          if (!section) return;

          var fields = section.querySelectorAll('input, select, textarea');

          for (var i = 0; i < fields.length; i++) {
            fields[i].disabled = disabled;
          }
        }

        function toggleSections() {
          if (!mailer || !smtpSection || !oauthSection) return;

          var useSmtp = mailer.value === 'smtp';

          smtpSection.style.display = useSmtp ? '' : 'none';
          oauthSection.style.display = useSmtp ? 'none' : '';
          setSectionDisabled(smtpSection, !useSmtp);
          setSectionDisabled(oauthSection, useSmtp);
        }

        function toggleRefreshToken() {
          if (!grantType) return;
          var showRefreshGrant = grantType.value === 'refresh_token';

          if (refreshTokenRow) {
            refreshTokenRow.style.display = showRefreshGrant ? '' : 'none';
          }

          if (refreshGrantRows.length) {
            for (var i = 0; i < refreshGrantRows.length; i++) {
              refreshGrantRows[i].style.display = showRefreshGrant ? '' : 'none';
            }
          }
        }

        if (mailer) {
          mailer.addEventListener('change', toggleSections);
          toggleSections();
        }

        if (grantType) {
          grantType.addEventListener('change', toggleRefreshToken);
          toggleRefreshToken();
        }

        if (form) {
          form.addEventListener('submit', function() {
            toggleSections();
            toggleRefreshToken();
          });
        }
      }());
    </script>
    <?php
  }

  public static function handle_send_test() {
    if(!current_user_can('manage_options')) {
      wp_die('Unauthorized');
    }

    check_admin_referer('env_mail_send_test');

    $to = isset($_POST['to']) ? sanitize_email(wp_unslash($_POST['to'])) : '';
    $subject = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';
    $message = isset($_POST['message']) ? wp_kses_post(wp_unslash($_POST['message'])) : '';

    if(!$to || !$subject || !$message) {
      self::store_test_result(false, 'Please complete all test email fields.');
      wp_safe_redirect(admin_url('options-general.php?page=env-mail&tab=test'));
      exit;
    }

    $mail_error = null;

    add_action('wp_mail_failed', function($error) use (&$mail_error) {
      $mail_error = $error;
    });

    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    $sent = wp_mail($to, $subject, wp_strip_all_tags($message), $headers);

    if($sent) {
      self::store_test_result(true, 'Email sent successfully. Check the inbox and spam folder.');
    }else{
      $debug = 'wp_mail() returned false.';

      if($mail_error && is_wp_error($mail_error)) {
        $debug .= "\n\nError message: " . $mail_error->get_error_message();
        $data = $mail_error->get_error_data();
        if(!empty($data)) {
          $debug .= "\n\nError data: " . print_r($data, true);
        }
      }

      self::store_test_result(false, 'Email failed to send.', $debug);
    }

    wp_safe_redirect(admin_url('options-general.php?page=env-mail&tab=test'));
    exit;
  }

  private static function store_test_result($success, $message, $debug = '') {
    set_transient('env_mail_test_result', [
      'success' => (bool) $success,
      'message' => $message,
      'debug' => $debug,
    ], 60);
  }
}
