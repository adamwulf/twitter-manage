<?

include "config.php";
include "easy-php-app/include.php";
include "easy-php-app/functions.php";
require_once ('easy-php-app/codebird-php/src/codebird.php');

$mysql = new MySQLConn(DATABASE_HOST, DATABASE_NAME, DATABASE_USER, DATABASE_PASS);
$db = new JSONtoMYSQL($mysql);
$app = new EasyApp($db);
\Codebird\Codebird::setConsumerKey(TWITTER_CONSUMER_KEY, TWITTER_CONSUMER_SECRET);
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


if(isset($_GET["cron"])){
	$results = $db->table("twitter_login")->find();
	while($row = $results->fetch_array()){
		$twitter = new EasyAppTwitter($row);
		
		echo $twitter->screenname() . ": " . $twitter->avatar() . "<br>";
		
		$obj = $twitter->fetchSomeTwitterInfo();
		
		prettyPrint((array)$obj);

// 		prettyPrint($twitter->followersFor("explainevrythng"));
		echo "<br>";

	}
	
	
	die();
}



// process app requests


if($app->isLoggedIn()){
	$my_name = $app->twitter()->screenname();
	if(isset($_REQUEST["stop_autofollow_id"])){
		$id_to_delete = (int) $_REQUEST["stop_autofollow_id"];
		if($db->table("auto_follow")->find(array("id" => $id_to_delete, "screen_name" => $my_name))->num_rows() == 1){
			$db->table("auto_follow")->delete(array("id" => $id_to_delete, "screen_name" => $my_name));
		}else{
			$msg = "error: couldn't stop autofollowing";
		}
		header("Location: " . page_self_url() . "?msg=" . urlencode($msg));
		exit;
	}
	if(isset($_REQUEST["autofollow"])){
		$nameToFollow = $_REQUEST["autofollow"];
		$aboutThem = $app->twitter()->profileFor($nameToFollow);
		
		
		if($db->table("auto_follow")->find(array("to_follow" => $nameToFollow))->num_rows()){
			$msg = "already autofollowing $nameToFollow's followers";
		}else{
			$to_save = array(
				"screen_name" => $my_name,
				"name" => $aboutThem["name"],
				"to_follow" => $nameToFollow,
				"followers_count" => $aboutThem["followers_count"],
				"followed" => 0,
				"description" => $aboutThem["description"]
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
}else{
	echo "<a href='" . page_self_url() . "?twitter_login" . "'>";
	echo "<img src='" . page_self_url() . "images/sign-in-with-twitter-gray.png' border=0/>";
	echo "</a>";
}


echo "</body></html>";

?>