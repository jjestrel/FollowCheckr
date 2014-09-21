<?php
require_once('tumblroauth/tumblroauth.php');

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
	
	/**
	 * Sends a GET request to the Tumblr API with 10 retry attempts
	 *
	 * @param string $url The full URL to the Tumblr API call
	 * @param array $params Any additional parameters to send with the GET request
	 * @return The data from the tumblr API as an Object
	 */
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
	
	/**
	 * Populates the FollowCheckr instance with the blogs the user is following
	 */
	function populateFollowing() {
		$this->following = array();
		
		$following = $this->makeGetRequest('http://api.tumblr.com/v2/user/following');
		for ($i=0; $i < ceil((float)($following->response->total_blogs) / 20.0); $i = $i + 1) {
			$blogs = $this->makeGetRequest('http://api.tumblr.com/v2/user/following', array("offset" => $i * 20))->response->blogs;
			$this->following = array_merge($this->following, $this->toUrlArray($blogs)); // Merge new blog list with total list
		}
	}

	/**
	 * Updates the main blog of the FollowCheckr instance and returns an array of all blogs the user owns
	 * @return An array of strings that represent the blogs owned by the user
	 */
	function populateBlogs() {
		$user_info = $this->makeGetRequest('http://api.tumblr.com/v2/user/info');

		for ($fln=0; $fln<count($user_info->response->user->blogs); $fln=$fln+1) {
			$base_host = str_replace('https:', '', $user_info->response->user->blogs[$fln]->url);
			$base_host = str_replace('http:', '', $base_host);
			$base_host = str_replace('\\', '', $base_host);
			$base_host = str_replace('/', '', $base_host);
			array_push($this->blog_hosts, $base_host);
			if ($user_info->response->user->blogs[$fln]->primary==true) {
				$this->main_host = $base_host;
			}
		}

		return $user_info->response->user->blogs
	}

	/**
	 * Updates the list of all of the followers of the user's blog
	 */
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
	
	/**
	 * Utility function to get the URLs of a list of blogs
	 */
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
	
	/**
	 * Load the last seen followers from a post draft in Tumblr 
	 * The data is formatted as follows:
	 * Title: FOLLOWCHECKR|DATA
	 * Body: NumberOfFollowers|Follower1|Follower2|Follower3
	 */
	function populateOld() {
		$drafts = $this->makeGetRequest('http://api.tumblr.com/v2/blog/'.($this->main_host).'/posts/draft', array("filter" => "raw"))->response->posts;
		for ($i = 0; $i < count($drafts); $i = $i + 1) {
			if ($drafts[$i]->title == "FOLLOWCHECKR|DATA") { // Follow Checkr data
				$this->data_id = $drafts[$i]->id; // Save ID to delete after
				$body_data = $drafts[$i]->body;
				$body_data = str_replace("<p>", "", $body_data); // Remove <p>
				$data = explode("|", $body_data); // Split on pipe
				$followerCount = (int)$data[0]; // First element is follower count
				for ($j = 1; $j <= $followerCount; $j = $j + 1) {
					array_push($this->old_followers, $data[$j]);
				}
			}
		}
	}
	
	/**
	 * Unfollows all blogs who don't follow the user's blog
	 */
	function unfollow() {
		for ($i = 0; $i < count($this->no_follow_back); $i = $i + 1) {
			// TODO: Proper error handling. Use OAuth last response.
			$this->tum_oauth->post("http://api.tumblr.com/v2/user/unfollow", array("url" => $this->no_follow_back[$i])); // Let 404 (Blog not found) fail silently, shouldn't matter
		}
	}
	
	/**
	 * Deletes previously loaded draft and saves the followers we loaded freshly from Tumblr
	 */
	function saveData() {
		$this->tum_oauth->post('http://api.tumblr.com/v2/blog/'.($this->main_host).'/post/delete', array("id" => $this->data_id)); // Remove old draft
		$body_data = strval(count($this->followers))."|";
		for ($i = 0; $i < count($this->followers); $i = $i + 1) {
			$body_data = $body_data.($this->followers[$i])."|";
		}
		$this->tum_oauth->post('http://api.tumblr.com/v2/blog/'.($this->main_host).'/post', array("type" => "text", "state" => "draft", "title" => "FOLLOWCHECKR|DATA", "body" => $body_data));
	}
}

?>