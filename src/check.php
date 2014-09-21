<?php
$consumer_key = "CONSUMER_KEY";
$consumer_secret = "CONSUMER_SECRET";

session_start();
require_once('tumblroauth/tumblroauth.php');
require_once('FollowCheckr.php');

/* If access tokens are not available redirect to connect page. */
if (empty($_SESSION['access_token']) || empty($_SESSION['access_token']['oauth_token']) || empty($_SESSION['access_token']['oauth_token_secret'])) {
    header('Location: ./clearsessions.php');
}

/* Get user access tokens out of the session. */
$access_token = $_SESSION['access_token'];

/* Create a TumblrOauth object with consumer/user tokens. */
$tum_oauth = new TumblrOAuth($consumer_key, $consumer_secret, $access_token['oauth_token'], $access_token['oauth_token_secret']);



function cleanUrl($url) {
	$url = str_replace('https:', '', $url);
	$url = str_replace('http:', '', $url);
	$url = str_replace('\\', '', $url);
	$url = str_replace('/', '', $url);
	$url = str_replace('tumblr.com', '', $url);
	$url = str_replace('.', '', $url);
	return $url;
}

$checkr = new FollowCheckr($tum_oauth);
?>
<div id='details'>
	<div id='new'>
		<h3>New</h3>
		<ul>
			<?php
			foreach($checkr->new_followers as $new) {
				if(strlen(cleanUrl($new)) > 0) {
					echo("<li><a href=\"" . $new . "\">".cleanUrl($new)."</a></li>");
				}
			}
			?>
		</ul>
	</div>
	<div id='lost'>
		<h3>Lost</h3>
		<ul>
		<?php
		foreach($checkr->lost_followers as $old) {
			if(strlen(cleanUrl($old)) > 0) {
				echo("<li><a href=\"".$old."\">".cleanUrl($old)."</a></li>");
			}
		}
		?>
		</ul>
	</div>
	<div id='dontfollow'>
		<h3>Don't Follow You Back</h3>
		<ul>
		<?php
		foreach ($checkr->no_follow_back as $nfb) {
			if(strlen(cleanUrl($nfb)) > 0) {
				echo("<li><a href=\"".$nfb."\">".cleanUrl($nfb)."</a></li>");
			}
		}
		?>
		</ul>
	</div>
	<div id='rude'>
		<h3>You Don't Follow Back</h3>
		<ul>
		<?php
			foreach ($checkr->rude as $rude) {
				if(strlen(cleanUrl($rude)) > 0) {
					echo("<li><a href=\"".$rude."\">".cleanUrl($rude)."</a></li>");
				}
			}
		?>
		</ul>
	</div>
</div>

<?php
$checkr->saveData(); 
?>