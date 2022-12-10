<? // oauth2callback.php

if($_GET['test']) {
$clientIdForLeashTime = '1033752664121-6djck9ng4gfnfctmkrj5ok57c86sf96d.apps.googleusercontent.com';
$scope = urlencode("https://www.googleapis.com/auth/calendar"); // email%20profile
$parts = "https://accounts.google.com/o/oauth2/auth?
scope=$scope&
state=sitter%3D555|666&
redirect_uri=https%3A%2F%2Fleashtime.com%2Foauth2callback.php&
response_type=code&
client_id=$clientIdForLeashTime&
approval_prompt=force";
$url = join(explode("\n", $parts));



?>
<a href='<?= $url ?>'>Get Auth token for mmlinden</a>
<?
}
//else if(TRUE) print_r($_GET);
else if(strpos($_GET['state'], 'sitter|') === 0) 
	require "google-cal-prov.php";
else if(strpos($_GET['state'], 'mgr|') === 0) 
	require "google-cal-prefs.php";

else {
	echo "Result: ".print_r($_REQUEST,1);
	echo "<hr>POST:".print_r($_POST,1);
	echo "<hr>GET:".print_r($_GET,1);
}