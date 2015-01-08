<?php
/**
 * Plugin Name: Evergage
 * Plugin Script: evergage.php
 * Plugin URI: http://www.evergage.com
 * Description: Real-time web personalization
 * Version: 1.0.4
 * Author: Evergage, Inc.
 * Author URI: http://www.evergage.com
 * License: GPLv2
 * Copyright (C) 2012-2014 Evergage, Inc. All rights reserved.
 */

session_start();

function evergage_loader() {

  if (is_admin()) {
    require_once dirname(__FILE__) . '/admin.php';
  }


  add_action('wp_print_scripts', 'evergage_add_javascript');

  if (get_option('evergage_promote') == 'yes') {
    add_action('wp_head', 'evergage_add_promote_js');
  }


}

add_action('init', 'evergage_loader');


/**
 * Add an action to send off to Evergage.
 *
 * @param string $action
 *   The action to send to Evergage.
 * @param array $params
 *   A set of parameters to send to Evergage.
 *
 * @return array
 *   The parameters which are to be sent to Evergage.
 */
function evergage_add_action($action = NULL, array $params = array(), $uid = NULL) {

  evergage_send_http_event($action, $params);

}

function evergage_add_promote_js() {

  echo "<script>var _aaq = window._aaq || (window._aaq = []);</script>";

  if (is_singular()) {

    global $post;
    $post_id = '';
    if (empty($post_id)) {
      $post_id = $post->ID;
    }

    $authorId = $post->post_author;
    $title = $post->post_title;
    $url = get_permalink($post_id);
    $type = $post->post_type == 'post' ? 'b' : 'a';
    $description = substr($post->content, 0, 150);

    $wptags = wp_get_post_tags($post->ID, array('fields' => 'all'));

    $tags = array();

    foreach ($wptags as $wptag) {
      $tag = array('type' => 't', 'tagType' => 'Keyword', '_id' => $wptag->name, 'name' => $wptag->name);

      $tags[] = $tag;
    }

    $tag = array('type' => 't', 'tagType' => 'Author', '_id' => get_the_author_meta('user_login', $authorId), 'name' => get_the_author_meta('display_name', $authorId), 'url' => get_the_author_meta('user_url', $authorId));
    $tags[] = $tag;

    $categories = array();

    $categoryObjects = get_the_category();
    $separator = ' ';
    $output = '';
    if($categoryObjects) {
      foreach ($categoryObjects as $categoryObject) {
        $output .= '<a href="' . get_category_link($categoryObject->term_id) . '" title="' . esc_attr(sprintf(__("View all posts in %s"), $categoryObject->name)) . '">' . $categoryObject->cat_name . '</a>' . $separator;

        $catId = substr(get_category_parents( $categoryObject->term_id , false, '|' ), 0, -1);

        $cat = array('type' => 'c', '_id' => $catId, 'name' => $categoryObject->name, 'url' => get_category_link($categoryObject->term_id));
        $categories []= $cat;
      }
    }



    $item = array('_id' => $post_id, 'type' => $type, 'name' => $title, 'url' => $url, 'description' => $description, 'tags' => $tags, 'categories' => $categories);

    $jsonItem = json_encode($item, 64); //JSON_UNESCAPED_SLASHES

    echo "<script>window.evergageItem = " . $jsonItem . ";\n_aaq.push(['viewItem', window.evergageItem]);</script>";


  }

  if (is_category()) {
    $categoryObject = get_category( get_query_var( 'cat' ) );

    $catId = substr(get_category_parents( $categoryObject->term_id , false, '|' ), 0, -1);
    $cat = array('type' => 'c', '_id' => $catId, 'name' => $categoryObject->name, 'url' => get_category_link($categoryObject->term_id));

    $jsonItem = json_encode($cat, 64); //JSON_UNESCAPED_SLASHES
    echo "<script>window.evergageItem = " . $jsonItem . ";\n_aaq.push(['viewCategory', window.evergageItem]);</script>";

  }


  if (is_author()) {
    global $author;

    $auth = array('type' => 't', 'tagType' => 'Author', '_id' => get_the_author_meta('user_login', $author), 'name' => get_the_author_meta('display_name', $author), 'url' => get_the_author_meta('user_url', $author));
    $jsonItem = json_encode($auth, 64); //JSON_UNESCAPED_SLASHES
    echo "<script>window.evergageItem = " . $jsonItem . ";\n_aaq.push(['viewTag', window.evergageItem]);</script>";

  }

  if (is_tag()) {
    global $tag;

    $tagId =  get_query_var('tag_id');
    $tagObject = get_tag($tagId);

    $tagItem = array('type' => 't', 'tagType' => 'Keyword', '_id' =>  $tagObject->name, 'name' => $tagObject->name, 'url' => get_tag_link($tagId));
    $jsonItem = json_encode($tagItem, 64); //JSON_UNESCAPED_SLASHES
    echo "<script>window.evergageItem = " . $jsonItem . ";\n_aaq.push(['viewTag', window.evergageItem ]);</script>";


  }

//  error_log('<!-- post ' . $post_id . ' -->');
//  $ip = Helper::get_real_ip_addr();
//  $this->statistic->add_view_count($post_id, date('Y-m-d'), $ip);
}


function evergage_send_http_event($action, $params) {

  $evergage_account = get_option('evergage_account', '');
  $evergage_dataset = get_option('evergage_dataset', '');

  $host = $evergage_account . ".evergage.com" . ($evergage_account == 'localtest' ? ":8443" : "");

  $url = "http://" . $host . "/jsreceiver?"
    . "_ak=" . $evergage_account
    . "&_ds=" . $evergage_dataset;


  $params['action'] = $action;


  global $current_user;
  get_currentuserinfo();

  $defaultSettings = evergage_defaultparameters($current_user);

  if (!empty($defaultSettings) && !empty($defaultSettings['settings']) && !empty($defaultSettings['settings']['setUser'])) {
    $url .= "&userId=" . urlencode($defaultSettings['settings']['setUser']);
  }

  if (!empty($defaultSettings) && !empty($defaultSettings['customVariables']) ) {
    foreach ($defaultSettings['customVariables'] as $key => $value) {
      if (!empty($value)) {
        $url .= "&" . urlencode($key) . "=" . urlencode($value);
      }
    }
  }

  foreach ($params as $key => $value) {
    if (!empty($value)) {
      $url .= "&" . urlencode($key) . "=" . urlencode($value);
    }
  }

  $opts = array(
    //    'blocking' => false,
    'timeout' => 5,
    'httpversion' => '1.1'
  );

  error_log('calling get event at ' . $url);
  $response = wp_remote_get($url, $opts);

  //  error_log('result ' . $response);

}


/**
 * Get the default parameters to be passed to Evergage.
 */
function evergage_defaultparameters($account = NULL) {

  static $settings = array();

  $settings['setDataset'] = get_option('evergage_dataset', '');
  $settings['setAccount'] = get_option('evergage_account', '');

  // Make sure the required fields are available.
  if (empty($settings['setDataset']) || empty($settings['setAccount'])) {
    echo "<!-- Evergage settings not complete -->";
    return array();
  }


  if (isset($account->user_email))
    $settings['setUser'] = $account->user_email;


  if (isset($account->roles) && $account->roles != null) {
    $settings['setAccountType'] = implode(',', $account->roles);
  }

  $custom = array();


  if (isset($account->first_name))
    $custom['userName'] = $account->first_name . ' ' . $account->last_name;

  if (isset($account->user_email))
    $custom['userEmail'] = $account->user_email;


  $params = array("settings" => $settings, "customVariables" => $custom);

  return $params;
}

/**
 * Get the default custom variables to be passed to Evergage.
 */
function evergage_defaultcustomvariables($account = NULL) {

  $custom = array();

  global $current_user;

  get_currentuserinfo();

  $account = $current_user;

  // Send in the roles.
  if (isset($account->roles)) {
    $i = 1;
    foreach ($account->roles as $role) {
      $custom['role' . $i] = $role;
      $i++;
    }
  }

  return $custom;
}


function evergage_add_javascript() {

  global $current_user;

  get_currentuserinfo();

  // Only add the Evergage JavaScript on the first round.
  static $i = 1;

  $options = array();


  if (get_option('evergage_synchronous', 'no') == 'yes') {


    $evergageAccount = get_option('evergage_account');
    $evergageDataset = get_option('evergage_dataset');

    $evergageHost = 'cdn.evergage.com';
    if ($evergageAccount == 'localtest') {
      $evergageHost = 'localtest.evergage.com' . (evergage_isSSL() ? ':8443' : ':8080');
    } else if ($evergageAccount == "demo4" || $evergageAccount == "demo5" || $evergageAccount == "eng2") {
      $evergageHost = $evergageAccount . '.evergage.com';
    }

    $evergageScriptURL = 'http' . (evergage_isSSL() ? 's' : '') . '://' . $evergageHost .
      '/beacon/' . $evergageAccount . '/' . $evergageDataset . '/scripts/evergage.min.js';


    $script = '<script src="' . $evergageScriptURL . '"></script>';
    echo $script;


  } else {
    wp_enqueue_script('wp_evergage_script', WP_PLUGIN_URL . '/' . basename(dirname(__FILE__)) . '/evergage.js');

    if ($i == 1) {

      // Make sure the initial View action is available.
      $params = evergage_defaultparameters($current_user);

      // Provide the encrypted User ID if possible.
      $apitoken = get_option('evergage_apitoken', '');
      if (!empty($apitoken)) {
        // Load the encrypted user id utility.

        require_once plugin_dir_path(__FILE__) . '/EncryptedUserIdUtility.php';

        $encrypt = new EncryptedUserIdUtility(get_option('evergage_account', ''), $apitoken);
        $params['settings']['setEncryptedUser'] = $encrypt->encrypt($params['settings']['setUser']);

      }


      wp_localize_script('wp_evergage_script', 'EvergageSettings', $params);
    }
  }
}


function evergage_isSSL() {
  if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on') {
    // no SSL request
    return false;
  }
  return true;
}


/**
 * Implements hook_user_register().
 */
function evergage_user_register($user_id) {

  $info = get_userdata($user_id);

  evergage_add_action('User: Register', array(
    'uid' => $user_id,
    'userId' => $info->user_login,
    'mail' => $info->user_email,
  ));
}

add_action('user_register', 'evergage_user_register');

/**
 * Implements hook_delete_user().
 */
function evergage_user_delete($user_id) {

  $info = get_userdata($user_id);

  evergage_add_action('User: Delete', array(
    'uid' => $user_id,
    'userId' => $info->user_login,
    'mail' => $info->user_email
  ));
}

add_action('delete_user', 'evergage_user_delete');

/**
 * Implements hook_wp_authenticate().
 */
function evergage_user_login($username) {

  $userinfo = get_user_by('login', $username);
//    get_userdatabylogin($username);

  if ($userinfo != false) {
    evergage_add_action('User: Login', array(
      'uid' => $userinfo->ID,
      'userId' => $userinfo->user_login,
      'mail' => $userinfo->user_email
    ), $userinfo->ID);
  }
}

add_action('wp_authenticate', 'evergage_user_login');

/**
 * Implements hook_wp_logout().
 */
function evergage_user_logout() {

  global $current_user;

  get_currentuserinfo();

  evergage_add_action('User: Logout', array(
    'uid' => $current_user->ID,
    'userId' => $current_user->user_login,
    'mail' => $current_user->user_email
  ));
}

add_action('wp_logout', 'evergage_user_logout');

/**
 * Implements hook_comment_post().
 */
function evergage_comment_add($comment_ID) {

  $comment = get_comment($comment_ID);

  evergage_add_action('Comment: Add', array(
    'cid' => $comment_ID,
    'nid' => $comment->user_id,
    'title' => $comment->comment_content
  ));
}

add_action('comment_post', 'evergage_comment_add');

/**
 * Implements hook_delete_comment().
 */
function evergage_comment_delete($comment_ID) {

  $comment = get_comment($comment_ID);

  evergage_add_action('Comment: Delete', array(
    'cid' => $comment_ID,
    'nid' => $comment->user_id,
    'title' => $comment->comment_content
  ));
}

add_action('delete_comment', 'evergage_comment_delete');

/**
 * Implements hook_save_post().
 */
function evergage_post_add($post_id) {

  global $current_user;

  get_currentuserinfo();

  evergage_add_action('Post: Add', array(
    'pid' => $post_id,
    'uid' => $current_user->ID,
    'title' => get_the_title($post_id)
  ));

}

add_action('save_post', 'evergage_post_add');

/**
 * Implements hook_delete_post().
 */
function evergage_post_delete($post_id) {

  global $current_user;

  get_currentuserinfo();

  evergage_add_action('Post: Delete', array(
    'pid' => $post_id,
    'uid' => $current_user->ID,
    'title' => get_the_title($post_id)
  ));

}

add_action('delete_post', 'evergage_post_delete');

/**
 * Implements hook_create_category().
 */
function evergage_category_add($category_id) {

  global $current_user;

  get_currentuserinfo();

  $thiscat = get_category($category_id);

  evergage_add_action('Category: Add', array(
    'cid' => $category_id,
    'uid' => $current_user->ID,
    'title' => $thiscat->name
  ));

}

add_action('create_category', 'evergage_category_add');

/**
 * Implements hook_delete_category().
 */
function evergage_category_delete($category_id) {

  global $current_user;

  get_currentuserinfo();

  $thiscat = get_category($category_id);

  evergage_add_action('Category: Delete', array(
    'cid' => $category_id,
    'uid' => $current_user->ID,
    'title' => $thiscat->name
  ));

}

add_action('delete_category', 'evergage_category_delete');



?>
