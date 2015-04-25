<?

include "config.php";
include "easy-php-app/include.php";
include "easy-php-app/functions.php";
require_once ('easy-php-app/codebird-php/src/codebird.php');

$mysql = new MySQLConn(DATABASE_HOST, DATABASE_NAME, DATABASE_USER, DATABASE_PASS);
$db = new JSONtoMYSQL($mysql);
\Codebird\Codebird::setConsumerKey(TWITTER_CONSUMER_KEY, TWITTER_CONSUMER_SECRET);



if(isset($_GET["cron"])){
	$users = $db->table("twitter_login")->find();
	while($user = $users->fetch_array()){
		$twitter = new EasyAppTwitter($user);
		
		//
		// first, make sure the follower list
		// is 100% up to date.
		//
		// this will track which users are following
		// our target user, as well as if they're following us
		$results = $db->table("auto_follow")->find(array("last_update" => date("Y-m-d", time()-24*60*60),
														 "screen_name" => $twitter->screenname()),
													array("last_update" => "<="));
		if($user_to_leech = $results->fetch_array()){
			echo "refreshing follower list of " . $user_to_leech["to_follow"] . " on behalf of " . $twitter->screenname() . "<br>";
			
			$cur = $user_to_leech["cursor"] ? $user_to_leech["cursor"] : -1;
			$followers = $twitter->followersFor($user_to_leech["to_follow"], $cur);
			
			if($followers["error"]){
				echo "error: <br>";
				prettyPrint((array)$followers["error"]);
				echo "<br>";
			}else{
				// update followers
				if(!$followers["next_cursor"]){
					// update is complete
					$db->table("auto_follow")->save(array("id" => $user_to_leech["id"],
														  "last_update" => date("Y-m-d"),
														  "cursor" => ""));
					echo "refreshed all followers, marking as updated";
				}else{
					echo "have next cursor: " . $followers["next_cursor"];
					
					$update_cursor = array("id" => $user_to_leech["id"],
										   "cursor" => $followers["next_cursor"]);
					$db->table("auto_follow")->save($update_cursor);
				}

				foreach($followers as $follower){
					$follower = (array)$follower;
					
					if(isset($follower["screen_name"])){
						$follow_info = array();
						$follow_info["owner_account"] = $twitter->screenname();
						$follow_info["found_via"] = $user_to_leech["to_follow"];
						$follow_info["found_via_id"] = $user_to_leech["to_follow_id"];
						$follow_info["screen_name"] = $follower["screen_name"];
						
						if(!$db->table("followers")->find($follow_info)->num_rows()){
							// we don't know about this person yet
							$follow_info["is_following_them"] = $follower["following"];
							$follow_info["follow_request_sent"] = $follower["follow_request_sent"];
							$follow_info["last_update"] = date("Y-m-d");
							$follow_info["auto_followed_on"] = $follower["following"] ? date("Y-m-d") : "";
							$follow_info["unfollowed_on"] = "";
							$follow_info["protected"] = $follower["protected"];
							$follow_info["follow_status_updated_on"] = "";
							
							$db->table("followers")->save($follow_info);
						}
					}
				}
				echo "found " . count($followers) . " to update";
			}
		}
		
		
		// slowly update the following status of the users
		$users_to_update_status = $db->table("followers")->find(array("owner_account" => $twitter->screenname(), 
																	  "follow_status_updated_on" => date("Y-m-d")),
																array("follow_status_updated_on" => "!="));;

		echo "need to update follow status for " . $users_to_update_status->num_rows() . " users<br>";
		if($user = $users_to_update_status->fetch_array()){
			$status = $twitter->connectionStatus($user["screen_name"]);
			if($status["error"]){
				echo "API error<br>";
			}else{
				prettyPrint($status);
				if(in_array("following", $status)){
					echo "we're already following " . $user["screen_name"] . "<br>";
					if(!$user["auto_followed_on"]){
						$user["auto_followed_on"] = date("Y-m-d");
					}
					$user["is_following_them"] = true;
				}else{
					$user["is_following_them"] = false;
				}
				if(in_array("followed_by", $status)){
					$user["is_following_us"] = true;
				}else{
					$user["is_following_us"] = false;
				}
				$user["follow_status_updated_on"] = date("Y-m-d");
				$db->table("followers")->save($user);
			}
		}
		
		
		
		
		//
		// next, find out if we need to follow any more users
		// from this account today. limit to following 20 / day
		
		$num_followed = $db->table("followers")->find(array("owner_account" => $twitter->screenname(), 
													"auto_followed_on" => date("Y-m-d")))->num_rows();

		echo "num followed today: " . $num_followed . "<br>";
		if($num_followed < MAX_FOLLOW_PER_DAY){
			$users_to_follow = $db->table("followers")->find(array("owner_account" => $twitter->screenname(), 
																  "auto_followed_on" => "",
																  "protected" => 1));
			echo "still have " . $users_to_follow->num_rows() . " users to auto follow<br>";
			while($num_followed < MAX_FOLLOW_PER_DAY && $user_to_follow = $users_to_follow->fetch_array()){
				if($user_to_follow["is_following_us"]){
					// user is already following us,
					// so we don't need to auto-follow them
					$user_to_follow["auto_followed_on"] = date("Y-m-d");
					$db->table("followers")->save($user_to_follow);
					$num_followed++;
					
					echo "user " . $user_to_follow["screen_name"] . " is already following " . $twitter->screenname() . "<br>";
				}else{
					
					echo "checking relationship with " . $user_to_follow["screen_name"] . "<br>";
					
					$status = $twitter->connectionStatus($user_to_follow["screen_name"]);
					prettyPrint($status);
					
					if(!in_array("following", $status) &&
					   !in_array("followed_by", $status) &&
					   !$user_to_follow["protected"]){
						   
						   // only follow them if we aren't already
						   // following them + aren't already
						   // followed by them
						   //
						   // and also make sure their timeline
						   // isn't protected
						echo "auto-following user " . $user_to_follow["screen_name"] . " from account " . $twitter->screenname() . "<br>";
	
						$twitter->follow($user_to_follow["screen_name"]);
					}else{
						echo "skipping " . $user_to_follow["screen_name"] . ".<br>";
						if($user_to_follow["protected"]){
							echo "user timeline is protected<br>";
						}
					}
					$user_to_follow["auto_followed_on"] = date("Y-m-d");
					$db->table("followers")->save($user_to_follow);
					$num_followed++;
					
					break;
				}
			}
		}


		echo "<br>";
	}
	
	
	die();
}



























$app = new EasyApp($db);
// start session for tracking logged in user
session_start();

// manage log in state

if(isset($_GET["logout"])){
	$app->logout();
    header('Location: ' . page_self_url());
    die();
}else if(isset($_GET['twitter_login'])){
	if (!isset($_SESSION['twitter_oauth_verify'])) {
		$auth_url = $app->twitterLogin(array(
	        'oauth_callback' => 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
	        'state' => 'twitter_login'
	    ));
	    header('Location: ' . $auth_url);
	    die();
	}elseif (isset($_GET['oauth_verifier']) && isset($_SESSION['twitter_oauth_verify'])) {
	
		$app->verifyLogin($_GET['oauth_verifier']);
	
	    // send to same URL, without oauth GET parameters
	    header('Location: ' . page_self_url());
	    die();
	}
}


// process app requests


if($app->isLoggedIn()){
	$my_name = $app->twitter()->screenname();
	if(isset($_REQUEST["follow"])){

		$users = $db->table("followers")->find(array("screen_name" => $_REQUEST["follow"], "owner_account" => $my_name));
		if($users->num_rows()){
			$user = $users->fetch_array();
			$user["is_following_them"] = true;
			$db->table("followers")->save($user);
			$app->twitter()->follow($_REQUEST["follow"]);
		}

		header("Location: " . page_self_url() . "?msg=" . urlencode($msg));
		exit;
	}
	if(isset($_REQUEST["unfollow"])){

		$users = $db->table("followers")->find(array("screen_name" => $_REQUEST["unfollow"], "owner_account" => $my_name));
		if($users->num_rows()){
			$user = $users->fetch_array();
			$user["is_following_them"] = false;
			$db->table("followers")->save($user);
			$app->twitter()->unfollow($_REQUEST["unfollow"]);
		}

		header("Location: " . page_self_url() . "?msg=" . urlencode($msg));
		exit;
	}
	if(isset($_REQUEST["stop_autofollow_id"])){
		$id_to_delete = (int) $_REQUEST["stop_autofollow_id"];
		$result = $db->table("auto_follow")->find(array("id" => $id_to_delete, "screen_name" => $my_name));
		if($result->num_rows() == 1){
			$to_leech = $result->fetch_array()["to_follow"];
			$db->table("auto_follow")->delete(array("id" => $id_to_delete, "screen_name" => $my_name));
			$db->table("followers")->delete(array("found_via" => $to_leech, "owner_account" => $my_name));
		}else{
			$msg = "error: couldn't stop autofollowing";
		}
		header("Location: " . page_self_url() . "?msg=" . urlencode($msg));
		exit;
	}
	if(isset($_REQUEST["autofollow"])){
		$nameToFollow = $_REQUEST["autofollow"];
		$aboutThem = $app->twitter()->profileFor($nameToFollow);
		
		if($aboutThem["error"]){
			$msg = "twitter user $nameToFollow doesn't exist";
		}else if($db->table("auto_follow")->find(array("to_follow" => $nameToFollow))->num_rows()){
			$msg = "already autofollowing $nameToFollow's followers";
		}else{
			
			$to_save = array(
				"screen_name" => $my_name,
				"name" => $aboutThem["name"],
				"to_follow" => $nameToFollow,
				"to_follow_id" => $aboutThem["id_str"],
				"followers_count" => $aboutThem["followers_count"],
				"description" => $aboutThem["description"],
				"last_update" => date("Y-m-d", time() - 24*60*60*7)
			);
			$db->table("auto_follow")->validateTableFor($to_save);
			$db->table("auto_follow")->save($to_save);
		}
		header("Location: " . page_self_url() . "?msg=" . urlencode($msg));
		exit;
	}
}




echo "<html><head><link rel=stylesheet type='text/css' href='style.css'/></head><body>";

// now show UI
if($app->isLoggedIn()){
	$my_name = $app->twitter()->screenname();
	echo "logged in as " . $my_name . " ";
	echo "<a href='" . page_self_url() . "?logout" . "'>Log Out</a><br><br>";
	
	if(isset($_REQUEST["msg"]) && strlen($_REQUEST["msg"])){
		echo "<div class='message'>" . $_REQUEST["msg"] . "</div>";
	}
	
	include "tables/auto-follow.php";
	echo "<br><br>";
	if(isset($_REQUEST["show_followers_for"])){
		include "tables/followers-for.php";
	}else{
		include "tables/should-unfollow.php";
	}
	
}else{
	echo "<a href='" . page_self_url() . "?twitter_login" . "'>";
	echo "<img src='" . page_self_url() . "images/sign-in-with-twitter-gray.png' border=0/>";
	echo "</a>";
}


echo "</body></html>";

?>