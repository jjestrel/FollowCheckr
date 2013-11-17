<?php
// This script is a simple example of how to send a user off to authentication using Tumblr's OAuth

// Start a session.  This is necessary to hold on to  a few keys the callback script will also need
session_start();

// Include the TumblrOAuth library
require_once('tumblroauth/tumblroauth.php');

// Define the needed keys
$consumer_key = "CONSUMER_KEY";
$consumer_secret = "CONSUMER_SECRET";

// The callback URL is the script that gets called after the user authenticates with tumblr
// In this example, it would be the included callback.php
$callback_url = "http://jayestrella.net/tumblr/followcheckr/callback.php";

// Create a new instance of the TumblrOAuth library.  For this step, all we need to give the library is our
// Consumer Key and Consumer Secret
$tum_oauth = new TumblrOAuth($consumer_key, $consumer_secret);

/* Get temporary credentials. */
$request_token = $tum_oauth->getRequestToken($callback_url);

/* Save temporary credentials to session. */
$_SESSION['oauth_token'] = $token = $request_token['oauth_token'];
$_SESSION['oauth_token_secret'] = $request_token['oauth_token_secret'];
 
/* If last connection failed don't display authorization link. */
switch ($tum_oauth->http_code) {
  case 200:
    /* Build authorize URL and redirect user to Tumblr. */
    $url = $tum_oauth->getAuthorizeURL($token);
    header('Location: ' . $url); 
    break;
  default:
    /* Show notification if something went wrong. */
    echo 'Could not connect to Tumblr. Refresh the page or try again later.';
}
?>