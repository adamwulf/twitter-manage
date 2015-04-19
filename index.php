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
		
		echo $twitter->avatar() . "<br>";

		print_r($twitter->followersFor("explainevrythng"));
		echo "<br>";

	}
	
	
	die();
}


// now show UI


$my_name = $app->isLoggedIn() ? $app->twitter()->screenname() : "anonymous";


echo "hello, " . $my_name . "<br>";

if($app->isLoggedIn()){
	echo "logged in<br>";
	echo "<a href='" . page_self_url() . "?logout" . "'>Log Out</a><br>";
}else{
	echo "<a href='" . page_self_url() . "?twitter_login" . "'>";
	echo "<img src='" . page_self_url() . "images/sign-in-with-twitter-gray.png' border=0/>";
	echo "</a>";
}




?>