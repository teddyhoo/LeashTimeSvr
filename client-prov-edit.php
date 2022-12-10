<? //client-prov-edit.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "client-profile-request-fns.php";
require_once "request-fns.php";
require_once "provider-fns.php";

$locked = locked('p-');//locked('o-'); 

extract($_REQUEST);

$error = null;

$activeClients = getActiveClientIdsForProvider($_SESSION["providerid"]);
//$id = $_SESSION["clientid"];
if(!in_array($id, $activeClients)) $error = "Insufficient access rights.";
else $client = getClient($id);

if($error) {
	echo "<font color='red'>$error</font>";
	include "frame-end.html";
	exit;
}

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

if($_POST) {
	// if no changes, do nothing and return home
	$changes = buildProfileRequest($_POST);
	if(!$changes) {
		$error = "You made no changes, so no request was sent.";
	}
	else {
		// create a request
		$request['clientptr'] = $_POST['clientid'];
		$request['providerptr'] = $_SESSION['providerid'];
		$request['requesttype'] = 'Profile';
		$requestId = saveNewClientRequest($request, false);
		// upload any new photos
		for($i = 1; $i <= $pets_visible; $i++)
		  if($uploadResult = uploadClientPhotoNumber($i, $requestId)) {
		  	$uploadErrors[] = $uploadResult;
		  	unset($changes["photo_$i"]);
			}
		if($uploadErrors) 
			$error = 'Failed to upload images, but the rest of your request was submitted.<ul>'.join('<li>', $uploadErrors)."</ul>";

		if($changes) foreach($changes as $key => $val) {
			$val = cleanseString((string)$val);
			insertTable('tblclientprofilerequest',
									array('clientptr'=>$_POST['clientid'], 'requestptr'=>$requestId, 'field'=>$key, 'value'=>$val), 1);
		}
		$request['requestid'] = $requestId;
		notifyStaffOfClientRequest($request);
									
	}
	
	if(!$error) {
		$_SESSION['user_notice'] = "<h2>Thanks for submitting changes to this client profile!</h2> <img src='art/lightning-smile-small.jpg' style='float:right;'>"
						."<span style='font-size:12pt'>These changes will appear in the profile after we have reviewed them.</span>";
		globalRedirect("index.php");
	}
}


//tblclientprofilerequest




$pageTitle = "Client Editor: {$client["fname"]} {$client["lname"]}";

include "frame.html";
// ***************************************************************************

include "client-own-edit-include.php";

// ***************************************************************************
include "frame-end.html";
?>
