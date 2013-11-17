<?php
$consumer_key = "CONSUMER_KEY";
$consumer_secret = "CONSUMER_SECRET";

session_start();
require_once('tumblroauth/tumblroauth.php');

/* If access tokens are not available redirect to connect page. */
if (empty($_SESSION['access_token']) || empty($_SESSION['access_token']['oauth_token']) || empty($_SESSION['access_token']['oauth_token_secret'])) {
    header('Location: ./clearsessions.php');
}

/* Get user access tokens out of the session. */
$access_token = $_SESSION['access_token'];

/* Create a TumblrOauth object with consumer/user tokens. */
$tum_oauth = new TumblrOAuth($consumer_key, $consumer_secret, $access_token['oauth_token'], $access_token['oauth_token_secret']);

function array_remove($arr, $remove) {
	$ret = array_merge(array(), $arr);
	foreach ($ret as $key => $val) {
		if (in_array($val, $remove)) {
			unset($ret[$key]);
		}
	}
	return $ret;
}

class FollowCheckr {
	public $following = array(); // Array of following
	public $followers = array(); // Array of followers
	public $old_followers = array();
	public $no_follow_back = array(); // Array of users who don't follow back
	public $rude; // Array of users you don't follow back
	public $lost_followers = array(); // Array of lost followers
	public $new_followers = array(); // Array of new followers
	public $tum_oauth;
	private $data_id;
	private $blog_hosts = array(); // Array of basehosts for each blog
	private $main_host;
	
	function __construct(TumblrOAuth $tum_oauth) {
		$this->tum_oauth = $tum_oauth;
		$this->populateBlogs();
		$this->populateFollowing();
		$this->populateFollowers();
		$this->populateOld();

		$this->no_follow_back = array_remove($this->following, $this->followers);
		$this->new_followers = array_remove($this->followers, $this->old_followers);
		$this->lost_followers = array_remove($this->old_followers, $this->followers);
		$this->rude = array_remove($this->followers, $this->following);
	}
	
	function makeGetRequest($url, $params = array()) {
		$retry = 0;
		$data = $this->tum_oauth->get($url, $params);
		while ($data->meta->status != 200) {
			echo($data->meta->status);
			if ($retry > 10) {
				exit("Unable to make successful API calls to tumblr! PANIC! Last Code: ".$data->meta->status);
			}
			$data = $this->tum_oauth->get($url, $params);
			$retry = $retry + 1;
		}
		return $data;
	}
	
	function populateFollowing() {
		$following = $this->makeGetRequest('http://api.tumblr.com/v2/user/following');
		for ($i=0; $i < ceil((float)($following->response->total_blogs) / 20.0); $i = $i + 1) {
			$blogs = $this->makeGetRequest('http://api.tumblr.com/v2/user/following', array("offset" => $i * 20))->response->blogs;
			$this->following = array_merge($this->following, $this->toUrlArray($blogs)); // Merge new blog list with total list
		}
	}

	// Returns the base-host name for the main blog
	function populateBlogs() {
		$userinfo = $this->makeGetRequest('http://api.tumblr.com/v2/user/info');
		$screen_name = $userinfo->response->user->name;
		for ($fln=0; $fln<count($userinfo->response->user->blogs); $fln=$fln+1) {
			$base_host = str_replace('https:', '', $userinfo->response->user->blogs[$fln]->url);
			$base_host = str_replace('http:', '', $base_host);
			$base_host = str_replace('\\', '', $base_host);
			$base_host = str_replace('/', '', $base_host);
			array_push($this->blog_hosts, $base_host);
			if ($userinfo->response->user->blogs[$fln]->primary==true) {
				$this->main_host = $base_host;
			}
		}
	}

	function populateFollowers() {
		for ($i = 0; $i < count($this->blog_hosts); $i = $i + 1) {
			$base_host = $this->blog_hosts[$i];
			$followers = $this->makeGetRequest('http://api.tumblr.com/v2/blog/'.$base_host.'/followers', array("base-hostname" => $base_host));
			for ($j=0; $j < ceil((float)($followers->response->total_users) / 20.0); $j = $j + 1) {
				$blogs = $this->makeGetRequest('http://api.tumblr.com/v2/blog/'.$base_host.'/followers', array("offset" => $j * 20, "base-hostname" => $base_host))->response->users;
				$this->followers = array_merge($this->followers, $this->toUrlArray($blogs));
			}
		}
	}
	
	function toUrlArray($blogs) {
		$ret = array();
		for ($i = 0; $i < count($blogs); $i = $i + 1) {
			array_push($ret, $blogs[$i]->url);
		}
		return $ret;
	}

	function printFollowers() {
		echo("Total Followers: " + count($this->followers));
		echo("<br/>");
		for ($i = 0; $i < count($this->followers); $i = $i + 1) {
			echo($this->followers[$i]);
			echo("<br/>");
		}
	}
	
	function printFollowing() {
		echo("Total Following: " + count($this->following));
		echo("<br/>");
		for ($i = 0; $i < count($this->following); $i = $i + 1) {
			echo($this->following[$i]);
			echo("<br/>");
		}	
	}
	
	function printDontFollow() {
		for ($i = 0; $i < count($this->no_follow_back); $i = $i + 1) {
			if(strlen($this->no_follow_back[$i]) > 0) {
				echo($this->no_follow_back[$i]);
				echo("<br/>");
			}
		}
	}
	
	function populateOld() {
		$drafts = $this->makeGetRequest('http://api.tumblr.com/v2/blog/'.($this->main_host).'/posts/draft', array("filter" => "raw"))->response->posts;
		for ($i = 0; $i < count($drafts); $i = $i + 1) {
			if (strcmp($drafts[$i]->title, "FOLLOWCHECKR|DATA") == 0) { // Follow Checkr data
				$this->data_id = $drafts[$i]->id; // Save ID to delete after
				$body_data = $drafts[$i]->body;
				$body_data = str_replace("<p>", "", $body_data); // Remove <p>
				$data = explode("|", $body_data); // Split on pipe
				$followerCount = (int)$data[0]; // First element is follower count
				for ($j = 0; $j < $followerCount; $j = $j + 1) {
					array_push($this->old_followers, $data[1 + $j]); // One after because count is 0.
				}
			}
		}
	}
	
	// Unfollows people who don't follow you back
	function unfollow() {
		for ($i = 0; $i < count($this->no_follow_back); $i = $i + 1) {
			// TODO: Proper error handling. Use OAuth last response.
			$this->tum_oauth->post("http://api.tumblr.com/v2/user/unfollow", array("url" => $this->no_follow_back[$i])); // Let 404 (Blog not found) fail silently, shouldn't matter
		}
	}
	
	function saveData() {
		$this->tum_oauth->post('http://api.tumblr.com/v2/blog/'.($this->main_host).'/post/delete', array("id" => $this->data_id)); // Remove old draft
		$body_data = strval(count($this->followers))."|";
		for ($i = 0; $i < count($this->followers); $i = $i + 1) {
			$body_data = $body_data.($this->followers[$i])."|";
		}
		$this->tum_oauth->post('http://api.tumblr.com/v2/blog/'.($this->main_host).'/post', array("type" => "text", "state" => "draft", "title" => "FOLLOWCHECKR|DATA", "body" => $body_data));
	}
}

// find primary blog.  Display its name.
$screen_name = $userinfo->response->user->name;
for ($fln=0; $fln<count($userinfo->response->user->blogs); $fln=$fln+1) {
        if ($userinfo->response->user->blogs[$fln]->primary==true) {
                echo("Your primary blog's name: " .($userinfo->response->user->blogs[$fln]->title));
                break;
        }
}

function clean_url2($url) {
	$url = str_replace('https:', '', $url);
	$url = str_replace('http:', '', $url);
	$url = str_replace('\\', '', $url);
	$url = str_replace('/', '', $url);
	$url = str_replace('tumblr.com', '', $url);
	$url = str_replace('.', '', $url);
	return $url;
}

$checkr = new FollowCheckr($tum_oauth);

echo("
	<div id='details'>
		<div id='new'>
			<h3>New</h3>
			<ul>");
foreach($checkr->new_followers as $new) {
	if(strlen(clean_url2($new)) > 0) {
		echo("<li><a href=\"".$new."\">".clean_url2($new)."</a></li>");
	}
}
echo("
			</ul>
		</div>
		<div id='lost'>
			<h3>Lost</h3>
			<ul>");
foreach($checkr->lost_followers as $old) {
	if(strlen(clean_url2($old)) > 0) {
		echo("<li><a href=\"".$old."\">".clean_url2($old)."</a></li>");
	}
}
echo("
			</ul>
		</div>
		<div id='dontfollow'>
			<h3>Don't Follow Back</h3>
			<ul>");
foreach ($checkr->no_follow_back as $nfb) {
	if(strlen(clean_url2($nfb)) > 0) {
		echo("<li><a href=\"".$nfb."\">".clean_url2($nfb)."</a></li>");
	}
}
echo("
			</ul>
		</div>
		");
echo("		<div id='rude'>
			<h3>You Don't Follow Back</h3>
			<ul>");
foreach ($checkr->rude as $rude) {
	if(strlen(clean_url2($rude)) > 0) {
		echo("<li><a href=\"".$rude."\">".clean_url2($rude)."</a></li>");
	}
}
echo("
			</ul>
	</div>
");
echo("<br/>");
echo("Your user name (the part before tumblr.com of your primary blog): ".$tum_oauth->get('http://api.tumblr.com/v2/user/info')->response->user->name);
echo("<br/>");

$checkr->saveData(); ;; Store data on a draft
?>