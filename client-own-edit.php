<? //client-own-edit.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

//if(mattOnlyTEST()) {echo "user: [{$_SESSION["auth_user_id"]}] adequate? [".adequateRights('c-')."]<hr><pre>".print_r($_SESSION,1)."</pre>";exit;}

require_once "client-profile-request-fns.php";
require_once "request-fns.php";
$segmentedClientEditableProfile = $_REQUEST['segmentedClientEditableProfile'];
set_time_limit(5 * 60);

//if(mattOnlyTEST()) {/*$_SESSION=null; bootUpSession();*/ echo "user: [{$_SESSION["auth_user_id"]}] adequate? [".adequateRights('c-')."]<hr><pre>".print_r($_SESSION,1)."</pre>";}


$locked = locked('c-');//locked('o-'); 

if(userRole() != 'c') {
	echo "This page is for clients only.  You are not logged in as a client.";
	exit;
}

extract($_REQUEST);
// $clientPetContext is set in the line above

$id = $_SESSION["clientid"];
$_SESSION['preferences'] = fetchKeyValuePairs("SELECT property, value FROM tblpreference");


if($_SESSION['preferences']['enableProfileChangeRequestReminder']) {
	// find unresolved Profile Change Requests
	$profileRequests = fetchCol0(
			"SELECT received 
				FROM tblclientrequest 
				WHERE clientptr = $id AND resolved = 0 AND requesttype = 'Profile'
				ORDER BY received DESC");
}
if($profileRequests) {
	$lastRequestDate = longDayAndDate(strtotime($profileRequests[0]));
	if($lastRequestDate == longDayAndDate('now')) $lastRequestDate = 'earlier today';
	else if($lastRequestDate == longDayAndDate(strtotime("-1 day"))) $lastRequestDate = 'yesterday';
	else $lastRequestDate = "on $lastRequestDate";
	$_SESSION['user_notice_dimensions'] = array('width' => 380, 'height' => 270);
	$_SESSION['user_notice'] = str_replace("\n", " ",
	"<h2>Hi!</h2>
	<p style='font-size:1.75em'><img src='art/lightning-smile-small.jpg' style='float:right;'>
	We are reviewing the profile changes you made $lastRequestDate. 
	They will appear on this page shortly.</p>");
}
else 
if($_SESSION['preferences']['clientOwnEditSubmitReminder']) {
	$_SESSION['user_notice_dimensions'] = array('width' => 380, 'height' => 270);
	$_SESSION['user_notice'] = str_replace("\n", " ",
	"<h2>Remember!</h2>
	
	<p class='fontSize1_3em'><img src='art/lightning-smile-small.jpg' style='float:right;'>
	You must click the <b>Submit Change Request</b> button after you
	make your changes if you want to save them.</p>");
}

$client = getClient($id);
$error = null;
//if(mattOnlyTEST()) {
if(intval($_SERVER['CONTENT_LENGTH'])>0 && count($_POST)===0){
	$_SESSION['user_notice'] = 
"<div class='fontSize1_2em'><h2>Sorry!</h2>The total size of your request was just too big to handle!"
."<img src='art/lightning-smile-small.jpg' align='right'>"
."<p>This usually results from trying to load too many photos, or photos that are unnecessarily large."
."<p>You can try<ul><li>lowering the resolution of your camera (the lower the better) OR"
."    <li>reducing the dimensions of your photos (LeashTime shrinks them to fit in a 300px x 300px box anyway) OR"
."    <li>reducing the number of photos you upload at once."
."</ul>Sorry for the inconvenience.</div>";
}
//}
if($_POST && isset($_POST['continueEditing'])) {
	// if no changes, do nothing and return home
	
	$changes = buildProfileRequest($_POST);
	$changedKeys = array();
	foreach($changes as $ck => $cv)
		if(strpos($ck, 'petid_') === FALSE) 
			$changedKeys[] = $ck;

	if(!$changedKeys || $changedKeys == array('version')) {
		$error = "You made no changes, so no request was sent.";
		logChange($id, 'tblclient', 'm', 'Profile editor: no changes made.');
	}
	else {
		// create a request
		$request['clientptr'] = $_SESSION["clientid"];
		$request['requesttype'] = 'Profile';
		$requestId = saveNewClientRequest($request, false);
		logChange($id, 'tblclient', 'm', "Profile editor: created request: $requestId");
		// upload any new photos
		for($i = 1; $i <= $pets_visible; $i++)
		  if($uploadResult = uploadClientPhotoNumber($i, $requestId)) {
		  	$uploadErrors[] = $uploadResult;
		  	unset($changes["photo_$i"]);
			}
		if($uploadErrors) 
			$error = 'Failed to upload images, but the rest of your request was submitted.<ul>'.join('<li>', $uploadErrors)."</ul>";
		if($changes) foreach($changes as $key => $val) {
//if($db == 'dogslife') {
			require_once "field-utils.php";
//if(mattOnlyTEST()) {echo "$val<br>"; }		
if(!mattOnlyTEST()) 
			$val = cleanseString((string)$val);
//}			

			insertTable('tblclientprofilerequest',
									array('clientptr'=>$_SESSION["clientid"], 'requestptr'=>$requestId, 'field'=>$key, 'value'=>$val), 1);
		}
//if(mattOnlyTEST()) { exit; }			
		$request['requestid'] = $requestId;
		notifyStaffOfClientRequest($request);
	}
	
	if(!$error) {
		$_SESSION['user_notice'] = "<h2>Thanks for submitting changes to your profile!</h2> <img src='art/lightning-smile-small.jpg' style='float:right;'>"
						."<span style='font-size:12pt'>These changes will appear in your profile after we have reviewed them.</span>";
		//session_write_close();
		//header("Location: ".globalURL("index.php?success=1"));
		globalRedirect("index.php?success=1");
		exit;
	}
}
//if(mattOnlyTEST()) mysql_set_charset('utf8');
//if(mattOnlyTEST()) { print_r(fetchAssociations("show variables like 'char%';")) ; exit; }


//tblclientprofilerequest
logChange($id, 'tblclient', 'a', 'Profile editor: visited.');


if($mobileclient) { // NO LONGER USED
	include "mobile-frame-client.php";
	// ***************************************************************************
	echo "<h2>My Profile</h2>";
	if($success) {
		echo "<font color='green'>Thank you for submitting your profile information.<p>  
	When your request is approved, this information will appear on your Profile Page.<p>
	Please <a href='login-page.php?logout=1'>Logout</a> if you are done, or select an option from the menu above.</font>";
	}

	else include "client-own-edit-include.php";

	// ***************************************************************************
	include "mobile-frame-client-end.php";
}

else {

	$pageTitle = "Home: {$_SESSION["clientname"]}'s Profile";

//if(mattOnlyTEST()) {unset($_SESSION); bootUpSession(); echo "user: [{$_SESSION["auth_user_id"]}] adequate? [".adequateRights('c-')."]<hr><pre>".print_r($_SESSION,1)."</pre>";}
	if($_SESSION["responsiveClient"]) {
		$pageTitle = "<i class=\"fa fa-paw\"></i> Profile";
		$floaterTopOffset = "-105";
		$extraHeadContent = 
			"<style>body {font-size:1.2em;} 
			.tiplooks {font-size:14pt;}
			.floater {
				position: absolute;
				top: 0px;
				right: 3px;
				/*width: 150px;
				height: {$floaterTopOffset}px;*/
				-webkit-transition: all 0.25s ease-in-out;
				transition: all 0.25s ease-in-out;
				z-index: 1;
			}
</style>";
		//$deskTopUser is defined and set in frame-client-responsive.html
		//$deskTopUser = f$deskTopUseralse;
		include "frame-client-responsive.html";
		if(!$deskTopUser) {
			$onLoadFragments[] = "$('.storedValue').toggle();";
			$responsiveMobile = true;
		}
	$onLoadFragments[] = "$(window).scroll(function() {
	var winScrollTop = $(window).scrollTop();
	var winHeight = $(window).height();
	var floaterHeight = $('.floater').outerHeight(true);
	//true so the function takes margins into account
	var fromBottom = 0;20;

	//var top = winScrollTop + {$floaterTopOffset} //+ winHeight - floaterHeight - fromBottom;
	var top = Math.max(winScrollTop, $('.card-head').height() + 15) - $('.card-head').height() - 15;
	$('.floater').css({'top': top + 'px'});
});";
		
		$frameEndURL = "frame-client-responsive-end.html";
	}
	else if(userRole() == 'c') {
		include "frame-client.html";
		$frameEndURL = "frame-end.html";
	}
	// ***************************************************************************
	if($success) {
		echo "<font color='green'>Thank you for submitting your profile information.<p>  
	When your request is approved, this information will appear on your Profile Page.<p>
	Please <a href='login-page.php?logout=1'>Logout</a> if you are done, or select an option from the menu above.</font>";
	}

	else include "client-own-edit-include.php";

// RESULT: SESSION is still alive and well
	// ***************************************************************************
	
	include $frameEndURL;
	
}
?>
