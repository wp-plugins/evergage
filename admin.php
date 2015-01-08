<?php
add_action('admin_menu', 'evergage_admin_menu');


add_action('admin_init', 'evergage_admin_loader');


function evergage_admin_loader() {
  wp_deregister_style('evergage_admin_css');
  wp_register_style('evergage_admin_css', WP_PLUGIN_URL . '/' . basename(dirname(__FILE__)) . '/evergage.css');
  wp_enqueue_style('evergage_admin_css');
}

function evergage_activate() {
  add_option('evergage_account', ''); // Evergage Account
  add_option('evergage_dataset', ''); // Evergage DataSet
  add_option('evergage_track_anonymous', ''); // Company for User

  add_option('evergage_synchronous', 'yes'); // sync or async
  add_option('evergage_promote', 'no'); // sync or async

  add_option('evergage_onmessage', 'yes'); // Enable OnMessage
  add_option('evergage_siteconfig', 'yes'); // Use Site Config
  add_option('evergage_apitoken', ''); // API Token
}

register_activation_hook(__FILE__, 'evergage_activate');

function evergage_deactivate() {
  delete_option('evergage_account');
  delete_option('evergage_dataset');
  delete_option('evergage_apitoken');
}

register_deactivation_hook(__FILE__, 'evergage_deactivate');


evergage_admin_warnings();

function evergage_admin_init() {

  global $wp_version;

  // all admin functions are disabled in old versions
  if (!function_exists('is_multisite') && version_compare($wp_version, '3.0', '<')) {

    function evergage_version_warning() {
      echo "
            <div id='evergage-warning' class='updated fade'><p><strong>" . sprintf(__('evergage %s requires WordPress 3.0 or higher.'), evergage_VERSION) . "</strong> " . sprintf(__('Please <a href="%s">upgrade WordPress</a> to a current version, or <a href="%s">downgrade to version 2.4 of the evergage plugin</a>.'), 'http://codex.wordpress.org/Upgrading_WordPress', 'http://wordpress.org/extend/plugins/evergage/download/') . "</p></div>
            ";
    }

    add_action('admin_notices', 'evergage_version_warning');

    return;
  }

  if (function_exists('get_plugin_page_hook'))
    $hook = get_plugin_page_hook('evergage-stats-display', 'index.php');
  else
    $hook = 'dashboard_page_evergage-stats-display';

  add_action('admin_head-' . $hook, 'evergage_stats_script');

}

add_action('admin_init', 'evergage_admin_init');


function evergage_plugin_action_links($links, $file) {
  if ($file == plugin_basename(dirname(__FILE__) . '/evergage.php')) {
    $links[] = '<a href="admin.php?page=evergage-config">' . __('Settings') . '</a>';
  }
  return $links;
}

add_filter('plugin_action_links', 'evergage_plugin_action_links', 10, 2);


function evergage_config() {

  /*$evergage_params = "setDataset|text\nsetAccount|text\nsetUser|text\nsetCompany|text\nsetAccountType|text\nsetAction|text";*/

  if (isset($_POST['saveBtn']) && $_POST['saveBtn'] == 'Save Configuration') {

    if (function_exists('current_user_can') && !current_user_can('manage_options'))
      die(__('Permission Denied'));

//        check_admin_referer( $evergage_nonce );


    update_option('evergage_account', $_POST['evergage_account']);
    update_option('evergage_dataset', $_POST['evergage_dataset']);
    update_option('evergage_apitoken', $_POST['evergage_apitoken']);
    update_option('evergage_synchronous', $_POST['evergage_synchronous']);
    update_option('evergage_promote', $_POST['evergage_promote']);

    error_log('sync: ' . get_option('evergage_synchronous'));
    error_log('promote: ' . get_option('evergage_promote'));

  }

  $evergage_account = get_option('evergage_account', '');
  $evergage_dataset = get_option('evergage_dataset', '');
  $evergage_apitoken = get_option('evergage_apitoken', '');
  $evergage_synchronous = get_option('evergage_synchronous', '');
  $evergage_promote = get_option('evergage_promote', '');
  ?>

  <div class="wrap">
    <h2>Evergage</h2>

    <form method="post" action="admin.php?page=evergage-config">
      <p>
        <a href="http://www.evergage.com">Evergage</a> helps you engage and communicate with your users in
        real-time and in your application. Segment your users by behavior and demographics and help them
        succeed through messaging and guidance. Track the performance of your in-app campaigns and deliver
        better value Today without redevelopment and waiting for deployments.
      </p>

      <p><strong>Account</strong><br/><input type="text" name="evergage_account" size="80"
                                             value="<?php echo $evergage_account; ?>"/><br/><span
          class="fld-desc">If you log into Evergage via http://example.evergage.com , then provide <i>example</i>.</span>
      </p>

      <p><strong>Dataset</strong><br/><input type="text" name="evergage_dataset" size="80"
                                             value="<?php echo $evergage_dataset; ?>"/><br/><span
          class="fld-desc">The dataset to use with this site.</span></p>
      <br/>


      <p><strong>Integration Synchronicity</strong><br/>
        <input type="radio" name="evergage_synchronous" name="Synchronous"
               value="yes" <?php echo checked(1, get_option('evergage_synchronous') == 'yes', true) ?> > Synchronous<br/>
        <input type="radio" name="evergage_synchronous" name="Asynchronous"
               value="no"  <?php echo checked(1, get_option('evergage_synchronous') == 'no', false) ?> > Asynchronous<br/>

      </p>


      <p><strong>Evergage Promote</strong><br/>
        <input type="radio" name="evergage_promote" name="Enabled"
               value="yes" <?php echo checked(1, get_option('evergage_promote') == 'yes', true) ?> > Enabled<br/>
        <input type="radio" name="evergage_promote" name="Disabled"
               value="no"  <?php echo checked(1, get_option('evergage_promote') == 'no', false) ?> > Disabled<br/>
      </p>


      <p>


      <h3 style="text-decoration: underline;">Advanced</h3>Configure additional settings for Evergage.</p>


      <p><strong>API Token</strong><br/><input type="text" name="evergage_apitoken" size="80"
                                               value="<?php echo $evergage_apitoken; ?>"/><br/><span
          class="fld-desc">Required only if you are using secure message personalization.</span></p>

      <p><input type="submit" name="saveBtn" id="saveBtn" class="button-primary" value="Save Configuration"/></p>
    </form>
  </div>
<?php
}


function evergage_admin_warnings() {

  global $evergageErrors;
  $evergageErrors = array();


  if (!get_option("evergage_account")) {
    array_push($evergageErrors, "Must supply valid Evergage Account");
  }
  if (!get_option("evergage_dataset")) {
    array_push($evergageErrors, "Must supply valid Evergage Dataset");
  }


  if (count($evergageErrors) > 0) {
    function evergage_warning() {
      global $evergageErrors;
      echo "
			<div id='evergage-warning' class='updated fade'><p><strong> Evergage Plugin is almost ready. </strong>";

      foreach ($evergageErrors as &$error) {
        echo "<br>" . $error;
      }
      echo sprintf('<br>Visit the <a href="%1$s">Evergage Configuration Page</a> to configure.', "admin.php?page=evergage-config");

      echo "</div>";
    }

    add_action('admin_notices', 'evergage_warning');
    return;
  }

}


// Check connectivity between the WordPress blog and evergage's servers.
// Returns an associative array of server IP addresses, where the key is the IP address, and value is true (available) or false (unable to connect).
function evergage_check_server_connectivity() {
  global $evergage_api_host, $evergage_api_port, $wpcom_api_key;

  $test_host = 'rest.evergage.com';

  // Some web hosts may disable one or both functions
  if (!function_exists('fsockopen') || !function_exists('gethostbynamel'))
    return array();

  $ips = gethostbynamel($test_host);
  if (!$ips || !is_array($ips) || !count($ips))
    return array();

  $servers = array();
  foreach ($ips as $ip) {
    $response = evergage_verify_key(evergage_get_key(), $ip);
    // even if the key is invalid, at least we know we have connectivity
    if ($response == 'valid' || $response == 'invalid')
      $servers[$ip] = true;
    else
      $servers[$ip] = false;
  }

  return $servers;
}

// Check the server connectivity and store the results in an option.
// Cached results will be used if not older than the specified timeout in seconds; use $cache_timeout = 0 to force an update.
// Returns the same associative array as evergage_check_server_connectivity()
function evergage_get_server_connectivity($cache_timeout = 86400) {
  $servers = get_option('evergage_available_servers');
  if ((time() - get_option('evergage_connectivity_time') < $cache_timeout) && $servers !== false)
    return $servers;

  // There's a race condition here but the effect is harmless.
  $servers = evergage_check_server_connectivity();
  update_option('evergage_available_servers', $servers);
  update_option('evergage_connectivity_time', time());
  return $servers;
}

// Returns true if server connectivity was OK at the last check, false if there was a problem that needs to be fixed.
function evergage_server_connectivity_ok() {
  // skip the check on WPMU because the status page is hidden
  global $wpcom_api_key;
  if ($wpcom_api_key)
    return true;
  $servers = evergage_get_server_connectivity();
  return !(empty($servers) || !count($servers) || count(array_filter($servers)) < count($servers));
}

function evergage_admin_menu() {
  // Adds the tab into the options panel in WordPress Admin area
  add_menu_page('Evergage', 'Evergage', 'administrator', 'evergage-config', 'evergage_config');

  if (class_exists('Jetpack')) {
    add_action('jetpack_admin_menu', 'evergage_load_menu');
  } else {
    evergage_load_menu();
  }
}

function evergage_load_menu() {

  add_submenu_page('plugins.php', __('evergage Configuration'), __('Evergage'), 'manage_options', 'evergage-config', 'evergage_config');

}
