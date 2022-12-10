<? // maintenance-outage.php 
if($downTimeStart) {
	$downTimeStart = shortDateAndTime(strtotime($downTimeStart));
	$downTime = 2; // hours
	$restartTime = shortDateAndTime(strtotime("+ $downTime hours", strtotime($downTimeStart)));
	$timeZone = $_SESSION['auth_user_id'] ? "(local time)" : "(U.S. Eastern Time)";
	echo "<h2>Maintenance Notice</h2>
		<div class='fontSize1_2em'>
		<img src='art/lightning-smile-small.jpg' style='float:right;'>
		{$downTimeStart} $timeZone:<p>LeashTime's server is being upgraded at the moment, so the service will be
		unavalable for about $downTime hours.
		<p>
		Please check back in at about $restartTime $timeZone.
		<p>
		See you soon!
		</div>";
	include "frame-end.html";
	exit;
}