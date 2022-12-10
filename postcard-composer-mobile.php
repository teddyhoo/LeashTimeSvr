<? // postcard-composer-mobile.php
/*
clientid - id of correspondent.  client must be an active client for the logged in provider
appointmentptr - optional?
thumb, display (optional) -  a postcard cardid. dump a thumbnail or display-size version of the attachment
sametab - this was opened in the main tab.  go back rather than close
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "preference-fns.php";
require_once "comm-fns.php";
require_once "appointment-fns.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "postcard-fns.php";
require_once "email-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

$maxBytes =  22 * 1024 * 1024;
$maxPixels = $maxBytes;
$maxDim = 8000; // round(sqrt($maxPixels));
$displaySize = array(500, 500);
$thumbnailDims = array(75, 40);

extract(extractVars('clientid,thumb,display,full,visit,sametab', $_REQUEST));

/*$postcardMediaAllowed = getClientPreference($clientid, 'postcardMediaAllowed'); // photosOnly, iPhoneOnly, null=any
if($postcardMediaAllowed == 'photosOnly') {
	$allowedTypes = explode(',', 'JPG,JPEG,PNG');
	$allowedTypesDescr = 'Photos Only';
	$allowedTypeIndivDescr = 'Photo';
}
else if($postcardMediaAllowed == 'iPhoneOnly') {
	$allowedTypes = explode(',', 'JPG,JPEG,PNG,MOV');
	$allowedTypesDescr = 'Photos and iPhone/iPad Video';
	$allowedTypeIndivDescr = 'Photo or iPhone/iPad Video';
}
else {
	$allowedTypes = explode(',', 'JPG,JPEG,PNG,MOV,MP4');
	$allowedTypesDescr = 'Photo or Video';
}
*/

$_SESSION['preferences'] = fetchPreferences();

if(userRole() == 'p') {
	$provid = $_SESSION["providerid"];
	// ensure provider-client email is permitted
	$postcardsEnabled = getUserPreference($_SESSION["auth_user_id"], 'postcardsEnabled');
	$proceed = $_REQUEST['clientid'] && $postcardsEnabled == 1; // if it is "selected", then the default is 'selected' but the sitter is not
//echo "postcardsEnabled: $postcardsEnabled proceed: $proceed";exit;
	if($proceed) {
		// ensure client belongs to provider
		require_once "provider-fns.php";
		$activeClients = getActiveClientIdsForProvider($provid);
//echo "client: {$_REQUEST['clientid']}  activeClients: ".print_r($activeClients, 1);exit;	
		if(!in_array($_REQUEST['clientid'], $activeClients)) {
			$proceed = false;
			$error = "Insufficient access rights.";
		}
	}
	if(!$proceed) locked('o-'); // boot sitter if access rights insufficient
	$locked = locked('p-'); // sitters only
}
else if(userRole() == 'c' && $clientid == $_SESSION['clientid'] && ($thumb || $display || $full)) {
	locked('c-');
}
else {
	locked('o-');
	$provid = $_REQUEST["providerid"];
}
	
if($thumb || $display || $full) {
	$postcardid = $thumb ? $thumb :  ($display ? $display : $full);
	$dims = $thumb ? $thumbnailDims : ($display ? $displaySize : null);
	$postcard =  fetchFirstAssoc("SELECT attachment, expiration FROM tblpostcard WHERE cardid = $postcardid LIMIT 1", 1);
	extract($postcard);
	
	if($attachment) {
		//$attachment = "{$_SESSION['bizfiledirectory']}photos/postcards/$attachment";
		if(file_exists($attachment)) {		
			if(attachmentType($attachment) == 'VIDEO') {
				if($full) dumpVideo($attachment);
				else dumpImage('postcard-video.jpg', $dims);
			}
			else {
		//echo "FILE EXISTS: [$attachment]: ".file_exists($attachment);exit;
				
				makeDisplayImage($attachment, $dims, $dumpToSTDOUT=true);
			}
		}
		else if($expiration && strcmp(date('Y-m-d H:i:s'), $expiration) >= 0)
			dumpImage('postcard-expired.jpg', $dims);
		else if($expiration && strcmp(date('Y-m-d H:i:s'), $expiration) >= 0)
			dumpImage('postcard-missing.jpg', $dims);
	}
	//else if($_SESSION['bannerLogo']) makeDisplayImage($_SESSION['bannerLogo'], $dims, $dumpToSTDOUT=true);
	else dumpImage(greetingsImage(), $dims);
};

// postcard-noimage.jpg, postcardmissing.jpg, postcardexpired.jpg, postcardvideo.jpg

if($_POST) {
	$attachment = $_FILES["attachment"] && $_FILES["attachment"]['error'] != 4;
	if($attachment) {
		$extension = getExtension($_FILES["attachment"]['name']);
		$root = $clientid."_".rand(0,9999999);
		while(file_exists("{$_SESSION['bizfiledirectory']}photos/postcards/$root.$extension"))
			$root = $clientid."_".rand(0,9999999);
		$attachmentName = "{$_SESSION['bizfiledirectory']}photos/postcards/$root.$extension";
		
		if(!($failure = handlePhoto($visit))) $photosent = true;
	}
	
	if(!$failure) {
		$appt = getAppointment($visit);
		if(!$appt) $failure = "Appointmnt [$visit] not found in postcard-composer-mobile.";
	}

	if(!$failure) {
		if($photosent) setAppointmentProperty($visit, "visitphotoreceived", date('Y-m-d H:i:s'));
		$datetime = date('Y-m-d H:i:s');
		setAppointmentProperty($visit, "visitreportreceived", $datetime);	
		require_once "appointment-client-notification-fns.php";
		$newVisitNote = extractSitterNote($_POST['note']); // Handle Ted's formatted note.  Not really necessary in postcard.
		if($newVisitNote) {
			$oldNote = getAppointmentProperty($visit, 'oldnote');
			$oldNote = $oldNote ? $oldNote : $appt['note'];
			if($oldNote && trim($oldNote)) setAppointmentProperty($visit, 'oldnote', $oldNote);
		}	
		if($newVisitNote) $mods = withModificationFields(array('note'=>$newVisitNote));
	//if(mattOnlyTEST()) echo "MODS: [".print_r($mods,1).']';	
		if($mods) updateTable('tblappointment', $mods, "appointmentid = $visit", 1);

		if($newVisitNote && $_SESSION['preferences']['enableSitterNotesChatterMods']) {
			require_once "chatter-fns.php";
			/*TEMP*/ ensureChatterNoteTableExists();
			addVisitChatterNote($visit, $newVisitNote, $provider['providerid'], $authortable='tblprovider', $visibility=2, $replyTo=null);
		}

		if($_POST['moodButtonsJSON']) {
			$buttons = json_decode($_POST['moodButtonsJSON'], $assoc=true);
			if($buttons === null) {
				$errors[] = "bad JSON supplied for buttons: $buttonsJSON";
				echo "ERROR:".join('|', $errors);
				//logLongError("native-visit-update ($loginid):".join('|', $errors));
				//exit;
			}
		}
		else {
			// QUESTION: Is this an error condition, or should we proceed?
			$buttons = array();
		}
		foreach($buttons as $k=>$v)
			setAppointmentProperty($visit, "button_$k", $v);

		if($version) setAppointmentProperty($visit, "apprequestversion", $version);	
		if($photosent) setAppointmentProperty($visit, "photosent", $photosent);	

	//print_r($buttons);exit;			
		sendPostcardVisitReport($_POST['visit'], $_POST['note'], $_POST['moodButtonsJSON']);
		$message = "Visit Report submitted.";
	}
}

//$extraBodyStyle = "background-image: url(art/postcardbg.jpg);padding-left: 15px;padding-top: 15px;";
$extraBodyStyle = "background-image: none; background-color:white;padding-left: 15px;padding-top: 15px;";

$customStyles =
".buttonSelected {width:35px;}
.buttonDeselected {width:25px;}
.hidefile {	width: 0.1px;
	height: 0.1px;
	opacity: 0;
	overflow: hidden;
	position: absolute;
	z-index: -1;
}
#chooselabel {cursor:pointer;}";
require "mobile-frame-bannerless.php";
//echo "<div style='background:#FCE9A5;height:550px;'>";// big div
//echo "<div style='background:white;'>";// big div

if($message) {
	echo "<center><h2>$message</h2>";
	$doneAction = $sametab ? "document.location.href='appointment-view-mobile.php?id=$sametab'" : 'window.close()';
	echoButton(null, "Done", $onClick=$doneAction, $class='Button h2', $downClass='ButtonDown h2', $noEcho=false, $title=null);
	echo "</center>";
	exit;
}

$sql = "SELECT appointmentid, appt.clientptr, appt.timeofday, starttime, endtime, canceled, completed,
				appt.pets, canceled, pendingchange, note, CONCAT_WS(' ', fname, lname) as name, nokeyrequired, servicecode,
				packageptr, recurringpackage, highpriority, label as service
				FROM tblappointment appt 
				LEFT JOIN tblclient ON clientid = clientptr
				LEFT JOIN tblservicetype ON servicetypeid = servicecode
				WHERE appointmentid = $visit
				ORDER BY date, starttime, endtime, lname, fname";
$appt = fetchFirstAssoc($sql);



require_once "mobile-prov-fns.php";
$clientLabel = visitListClientLabel($appt);
echo "<table border=0 style='margin-bottom:10px'>
<tr><td colspan=2>$clientLabel</td></tr>
<tr><td class='petfont'>{$appt['timeofday']}</td><td class='petfont'>{$appt['service']}</td></tr>
</table>";





//echo "<h2 style='text-align:center;'>Visit Report to </h2>";
//echo "<h2 style='text-align:center;'>".fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = $clientid LIMIT 1", 1)."</h2>";
echo "<form name='postcardeditor' method='post' enctype='multipart/form-data'>\n";
//echoButton(null, "Send", $onClick='sendPostcard()', $class="fontSize2_0em", $downClass=null, $noEcho=false, $title=null);

echo "<div class='Button' style='font-size:2.0em;cursor:pointer;border:solid gray 1px;display:inline;vertical-align:middle;padding-left:3px;padding-right:3px;' 
				onclick='sendPostcard()'>&#9993; Send</div>";
echo "<img src='art/spacer.gif' width=60 height=1>";

echo " $allowedTypesDescr ";
$quitAction = $sametab ? "document.location.href=\"appointment-view-mobile.php?id=$sametab\"" : 'window.close()';

echoButton(null, "Quit", $onClick=$quitAction, $class=null, $downClass=null, $noEcho=false, $title=null);
echo "<p>";
dumpMoodButtons();
if($failure) echo "<span class='warning'><br>$failure</span>";
hiddenElement('MAX_FILE_SIZE', $maxBytes);
hiddenElement('clientid', $clientid);
hiddenElement('moodButtonsJSON', '');
hiddenElement('visit', $visit);
hiddenElement('sametab', $sametab);
echo "<table width=90%>";
$noFileUpload = preg_match('/Windows Phone/i', $_SERVER['HTTP_USER_AGENT']);
if($noFileUpload) echo "<br>No file upload available on this device.";
else {
	$title = "title= 'Choose a new photo for this postcard.'"; //  or video
	$camera = "<span style='font-size:2em;'>&#128247;</span>";
	echo "<tr><td><input $title type='file' id='attachment' name='attachment' autocomplete='off' onchange='chooserChanged(this)' class='hidefile'>
				<label id='chooselabel' for='attachment'>$camera</label>";
}
echo " <img src='art/help.png' width=25 height=25 style='float:right;' onclick='toggleHelp()'>";
$z = number_format($maxDim);
echo "<div id='limits' style='display:none;background:palegreen;' onclick='toggleHelp()'>If you send a photo:<br>Maximum file size approx: ".number_format((int)($maxBytes / 1024))." KB.<br>" //  or video
	."Max. photo size: ".sprintf('%01.2f', $maxPixels / (1024 * 1024.0))." megapixels (approx. $z X $z).";
	//."<br>Max video length 10-12 seconds.</div>";
echo "</td></tr>";
textRow('Note:', 'note', $_REQUEST['note'], $rows=10, $cols=44, null, 'fontSize1_2em');
echo "</table>";
echo "</form>";
//echo "</div>"; // big div
?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>

var validExtensions = '<?= join(',', getAllowedImageTypes()) ?>'.split(',');

function chooserChanged(el) {
	var label = document.getElementById('chooselabel');
	var fileName = '';
	fileName = el.value.split( '\\' ).pop();
	if( fileName )
		label.innerHTML = "<?= $camera ?> photo <span class='petfont'>"+fileName+"</span>";
	else
		label.innerHTML = "<?= $camera ?>";	
}

function toggleMood(img) {
	var cl = img.className;
	cl = cl == 'buttonDeselected' ? 'buttonSelected' : 'buttonDeselected';
	img.className = cl;
}

function toggleHelp() {
	var display = document.getElementById("limits").style.display;
	display = display == 'block' ? 'none' : 'block';
	document.getElementById("limits").style.display = display;
}

function sendPostcard() {
//alert(document.getElementById('attachment').value);
	var message = null, attachment, attachmentsize=0, extensionIsValid=false;
	attachment = document.getElementById('attachment');
	var fileSelected = trim((String)(attachment.value)) != '';
	var chosenButtons = document.getElementsByClassName('buttonSelected');
	if(chosenButtons.length > 0) {
		var buttonsJSON = {};
		for(var i = 0; i < chosenButtons.length; i++)
			buttonsJSON[chosenButtons[i].id] = 1;
		document.getElementById('moodButtonsJSON').value = JSON.stringify(buttonsJSON);
	}
	
	if(!fileSelected && chosenButtons.length == 0 && trim((String)(document.getElementById('note').value)) == '')
		message = "You must either attach a file, write a note, or select mood buttons for the report to be sent.";
	else {
		for(var i=0; i < validExtensions.length; i++)
			extensionIsValid = extensionIsValid || attachment.value.toUpperCase().indexOf('.'+validExtensions[i]) != -1;
		if(fileSelected) {
			if(!extensionIsValid) message = "The chosen file is not a photo";
			else if(window.FileReader) {
				if(attachment.files && (attachment.files.length == 0 || attachment.files[0].size > <?= $maxBytes ?>))
					message = "Files larger than <?= number_format($maxBytes) ?> bytes cannot be sent.";
			}
		}
	}
	//alert(document.getElementById('moodButtonsJSON').value);return;
	
	if(MM_validateForm(message, '', 'MESSAGE')) {
		document.postcardeditor.submit();
	}
}
		
function quit() {
	if((trim((String)(document.getElementById('note').value)) != '' 
		|| trim((String)(document.getElementById('attachment').value)) != '' )
		&& !confirm("Sure you want to quit?"))
		return;
	window.close();
}
</script>
<?


function sendPostcardVisitReport($apptid, $note, $buttonsJSON=null) { // code lifted from native-visit-update.php and photo upload
	require_once 'appointment-client-notification-fns.php';
	require_once "preference-fns.php";
	$appt = fetchFirstAssoc("SELECT * FROM tblappointment WHERE appointmentid = '$apptid' LIMIT 1", 1);
	$provider = fetchFirstAssoc("SELECT * FROM tblprovider WHERE providerid = '{$appt['providerptr']}' LIMIT 1", 1);
	if(clientShouldBeNotified($appt['clientptr'])) {
		// check for probable duplicates
		if(TRUE || dbTEST('dogslife')) {
			// the MGR note gets folded into the note first time through, screwing up subsequent comparison.
			$hashNote = $note && strpos($note, "[MGR NOTE]") ? substr($note, strpos($note, "[MGR NOTE]")) : $note;
			$hash = sha1($jsonHashFodder = json_encode(createVisitReportRequest($appt, $buttonsJSON, $hashNote, $sent=null)));
			$stopItsADup =
				$hash == getClientPreference($appt['clientptr'], 'lastVisitReportHash');
			setClientPreference($appt['clientptr'], 'lastVisitReportHash', $hash);
			if(dbTEST('itsadogslifeny')) { // for Robyn Seaman
				logLongError("VR: $jsonHashFodder / $hash");
			}
			if($stopItsADup) logLongError("native-visit-update ($loginid): Dup VR: $hash");
		}
		if(!$stopItsADup) { // do not stop, even if it is a dupicate // !$stopItsADup
			if(getProviderPreference($provider['providerid'], 'sitterReportsToClientViaServerAfterApproval')) {
				// CREATE A NEW REQUEST OF TYPE EnhancedVisitReport (sent=false)
				$request = createVisitReportRequest($appt, $buttonsJSON, $note, $sent=null); // SEE: appointment-client-notification-fns.php
				saveNewGenericRequest($request, $appt['clientptr']);
				// DO NOT NOTIFY CLIENT
			}
			else {
				setClientPreference($appt['clientptr'], 'lastVisitReport',
					json_encode(createVisitReportRequest($appt, $buttonsJSON, $note, $sent=null)));

				setVisitReportPublic($apptid, true);

				$_SESSION["providerfullname"] = "{$provider['fname']} {$provider['lname']}";
				//$sendImmediately = /*mattOnlyTEST() ||*/ dbTEST('tonkapetsitters' /* ,tonkatest,whalestotails */) ? null : 'immediately';
				$sendImmediately = 'immediately';  //$allPrefs['delayVisitReportEmailSending'] ? null : 'immediately';
				//$sendImmediately = mattOnlyTEST() ? null : 'immediately';
				if($sendImmediately) {// old style
					$messageptr = sendEnhancedVisitReportEmail($appt['appointmentid'], $sendImmediately); // SEE: appointment-client-notification-fns.php
				}
				if(is_array($messageptr)) { // ERROR
					if($ALTEmailAddressTEST) {  //TBD - get rid of this test after beta test -- see also appointment-client-notification-fns.php
						// generate a System Notification
						$subjectAndMessage = visitReportErrorNotification($messageptr); // $messageptr is actually an array here
						//logError('Whoop: '.print_r($subjectAndMessage, 1));
						saveNewSystemNotificationRequest(
								$subjectAndMessage['subject'], 
								$subjectAndMessage['message'], 
								$extraFields = null);
					}
					$errors[] = $messageptr[0];
				}
				if(!$errors) { // starting with version 2
					if($version) setAppointmentProperty($appt['appointmentid'], "apprequestversion", $version);	
					if($photosent) setAppointmentProperty($appt['appointmentid'], "photosent", $photosent);
					if($arrived) registerVisitTrack($arrived);
					if($completed) registerVisitTrack($arrived);
				}
				if(!$errors 
					//&& getClientPreference($appt['clientptr'], 'visitreportGeneratesClientRequest')
					&& !getProviderPreference($appt['providerptr'], 'visitreport_NO_ClientRequest')
					) {
					// CREATE A NEW REQUEST OF TYPE EnhancedVisitReport (sent=true)
//if(dbTEST('tonkatest')) logError("about to createVisitReportRequest: [$appointmentptr]");		
					$request = createVisitReportRequest($appt, $buttonsJSON, $note, $messageptr, 
																								$sentby="{$provider['fname']} {$provider['lname']}");
//if(dbTEST('tonkatest')) logError("POST createVisitReportRequest: [$appointmentptr]");		
					require_once 'request-fns.php';
					$requestPtr=saveNewGenericRequest($request, $appt['clientptr']);
				}
				if(!$sendImmediately) // new style: when sent the message will refer back (via tags) to the request, if any
					orderDelayedVisitReportEmail($appt['appointmentid'], $delaySeconds=180, $requestPtr); // SEE: appointment-client-notification-fns.php				
			}
		}
	}
}

function moodButtonImages() { // mood=>(('title'=>'', 'file'=>'basename'))
	$rawmoods =
		"poo|poo|mood-poo.png
		pee|pee|mood-pee.png
		play|play|mood-play.png
		happy|happy|mood-happy.png
		sad|sad|mood-sad.png
		angry|angry|mood-angry.png
		hungry|hungry|mood-hungry.png
		sick|sick|mood-sick.png
		cat|cat sit|mood-catsit.png
		litter|litter|mood-litter.png
		shy|shy|mood-shy.png";
	foreach(explode("\n", $rawmoods) as $line) {
		$mood = explode("|", trim($line));
		$buttonImages[$mood[0]] = array('title'=>$mood[1], 'file'=>$mood[2]);
	}
	return $buttonImages;
}

function dumpMoodButtons() {
	foreach(moodButtonImages() as $k => $mood)
		echo "<img id='$k' src='art/{$mood['file']}' class='buttonDeselected' title='{$mood['title']}' onclick='toggleMood(this)'> ";
}

// ALL PHOTO STUFF FROM HERE ONWARD

function handlePhoto($appointmentid) { // lifted from appointment-photo-upload.php
	require_once "remote-file-storage-fns.php";
	require_once "preference-fns.php";
	$photoParameterName = "attachment";
	$clientid = fetchRow0Col0("SELECT clientptr FROM tblappointment WHERE appointmentid = '$appointmentid' LIMIT 1", 1);
	if(!$clientid) $error = "ERROR: Visit [$appointmentid] NOT FOUND";
	if(!$error) {
		$maxBytes = 5000000;
		$maxPixels = 11000000; // 6800000;
		$maxDim = (int)sqrt($maxPixels);
		$displaySize = array(300, 300);

		$photo = $_FILES[$photoParameterName] && $_FILES[$photoParameterName]['error'] != 4;
		$extension = strtolower(substr($_FILES[$photoParameterName]['name'], strrpos($_FILES[$photoParameterName]['name'], '.')+1));
		$photoName = "{$_SESSION['bizfiledirectory']}photos/appts/$clientid/$appointmentid.$extension";
		$error = uploadPhoto($photoParameterName, $photoName, $makeDisplayVersion=false);
	}
	if($error) {
		return $error;
	}
	else {
		global $fileCacheParameters;
		if(!$fileCacheParameters) getFileCacheParameters();
		//$fileCacheParameters['localCountLimit'] = 3;
		$cacheid = cacheFile($photoName, $photoName, 'overwrite');
		setAppointmentProperty($appointmentid, 'visitphotocacheid', $cacheid);
		//echo jsonPair('ok', "UPLOADED $photoName [cacheid: $cacheid]");
	}
}

function getAllowedImageTypes() { return array('JPG','JPEG','PNG'); }
function getAllowedTypesDescr() { return "JPEG (.jpg or .jpeg) or PNG image"; }
function uploadPhoto($formFieldName, $destFileName, $makeDisplayVersion=true) {
  $allowedTypes = getAllowedImageTypes();
  $allowedTypesDescr = getAllowedTypesDescr();
	
	$dot = strrpos($_FILES[$formFieldName]['name'], '.');
	if($dot === FALSE) return "Uploaded file MUST be a $allowedTypesDescr.";
	$originalName = $_FILES[$formFieldName]['name'];
  $extension = strtoupper(substr($_FILES[$formFieldName]['name'], $dot+1));
  if(!in_array($extension, $allowedTypes))
    return "Photo Not uploaded!  Uploaded file MUST be a $allowedTypesDescr.<br>[$originalName] does not qualify.";

	$target_path = $destFileName;
//if(mattOnlyTEST() && $failure) {echo $target_path;exit;}  

	if($reason = invalidUpload($formFieldName, $target_path)) return "The file $originalName could not be used because $reason";
	if(file_exists($target_path)) unlink($target_path);
	ensureDirectory(dirname($target_path), 0775); // x is necessary for group
//echo substr(sprintf('%o', fileperms(dirname($target_path))), -4);
	if(!move_uploaded_file($_FILES[$formFieldName]['tmp_name'], $target_path)) {
		return "There was an error uploading the file, please try again!";
	}
	if($makeDisplayVersion)
		makeDisplayImage($target_path);
	return null;
}

function makeDisplayImage($file) {
	global $displaySize;
	$displayVer = str_replace("\\", '/', dirname($file));
	if(substr($displayVer, -1) == '/') $displayVer = substr($displayVer, 0, -1);
	$displayVer .= '/display/';
	ensureDirectory($displayVer);
	$displayVer .= basename($file);
	$dims = getimagesize($file);
	if($dims[0] <= $displaySize[0] && $dims[1] <= $displaySize[1])
	  copy($file, $displayVer);
	else {
		makeResizedVersion($file, $displayVer, $displaySize, $dims);
	}
	
}

function photoDimsToFitInside($file, $maxDims) {
  list($width, $height) = getimagesize($file);
  $maxDim = $height > $width ? $maxDims[1] : $maxDims[0];
  $percent = $maxDim / max($width, $height);
  return array(round($width * $percent), round($height * $percent));
}


function makeResizedVersion($f, $outName, $maxDims, $origDims=null) {
	ini_set('memory_limit', '512M');
	//ini_set('upload_max_filesize', '6M');
  list($width, $height) = $origDims ? $origDims : getimagesize($f);
  $maxDim = $height > $width ? $maxDims[1] : $maxDims[0];
  $percent = $maxDim / max($width, $height);
  $newwidth = round($width * $percent);
  $newheight = round($height * $percent);
  // Load
  $resized = imagecreatetruecolor($newwidth, $newheight);
	$extension = strtoupper(substr($f, strrpos($f, '.')+1));

	if("IGNORE JPG WARNING") { // for "recoverable error: Premature end of JPEG file"
		$jpeg_ignore_warning = ini_get("gd.jpeg_ignore_warning");
		ini_set("gd.jpeg_ignore_warning", 1);
	}

  if($extension == 'JPG' || $extension == 'JPEG') $source = imagecreatefromjpeg($f);
  else if($extension == 'PNG') $source = imagecreatefrompng($f);
	if("IGNORE JPG WARNING") {
		ini_set("gd.jpeg_ignore_warning", $jpeg_ignore_warning);
	}

  // Resize
  //imagecopyresized($resized, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
  
	if(file_exists($outName)) unlink($outName);
	if($extension == 'JPG' || $extension == 'JPEG') imagejpeg($resized, $outName);
	else if($extension == 'PNG') imagepng($resized, $outName);
}

function ensureDirectory($dir, $rights=0765) {
  if(file_exists($dir)) return true;
  ensureDirectory(dirname($dir));
  mkdir($dir);
  chmod($dir, $rights);
}

function invalidUpload($formFieldName, $file) {
  global $maxPixels, $maxDim;
  $basefile = basename($file);
  $oldError = error_reporting(E_ALL - E_WARNING);
  $failure = null;
  
  
  if($failure = $_FILES[$formFieldName]['error']) {
		if($failure == 1) $failure = "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
		else if($failure == 2) $failure = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.";
		else if($failure == 3) $failure = "The uploaded file was only partially uploaded.";
		else if($failure == 4) $failure = "No file was uploaded.";
		else if($failure == 6) $failure = "Missing a temporary folder.";
		else if($failure == 7) $failure = "Failed to write file to disk.";
		else if($failure == 8) $failure = "File upload stopped by extension.";
	}
  else if(true/*$extension == 'JPG' */) {
		$extension = strtoupper(substr($_FILES[$formFieldName]['name'], strrpos($_FILES[$formFieldName]['name'], '.')+1));
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "NO PROBLEM: {$_FILES[$formFieldName]['tmp_name']}";echo "<br>NO PROBLEM: ".print_r(getimagesize($_FILES[$formFieldName]['tmp_name']),1); }		
		$size = getimagesize($_FILES[$formFieldName]['tmp_name']);
		$pixels = $size[0]*$size[1];
		if($pixels > $maxPixels) {
			$pixels = number_format($pixels);
			
		  $failure = "Photo dimensions are too big: ({$size[0]} X {$size[1]}) = $pixels pixels (Max: $maxPixels pixels, = approx. $maxDim X $maxDim)";
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "<p>FAILURE: $failure"; exit; }		
		}
    else {
			//$allowedTypes = getAllowedImageTypes();
			$allowedTypesDescr = getAllowedTypesDescr();
			
			if("IGNORE JPG WARNING") {  // for "recoverable error: Premature end of JPEG file"
				$jpeg_ignore_warning = ini_set("gd.jpeg_ignore_warning");
				ini_set("gd.jpeg_ignore_warning", 1);
			}
			if($extension == 'JPG' || $extension == 'JPEG')
      	$img = imagecreatefromjpeg($_FILES[$formFieldName]['tmp_name']);
      else if($extension == 'PNG')
      	$img = imagecreatefrompng($_FILES[$formFieldName]['tmp_name']);
			if("IGNORE JPG WARNING") {
				ini_set("gd.jpeg_ignore_warning", $jpeg_ignore_warning);
			}
      if(!$img) $failure = "it does not contain a valid $allowedTypesDescr.";
		}
  }
  else {
		require_once "zip-fns.php";
		$zipFile = $_FILES['$formFieldName']['tmp_name'];
    if(is_int($zip = zip_open($zipFile))) $failure = "File is not a valid ZIP archive.";
    $dir = getTargetPath();
    $existingPhotos = glob("$dir/*.jpg");
    foreach($existingPhotos as $index => $fname) $existingPhotos[$index] = basename($fname);
    $errors = invalidArchiveEntries($zip, $existingPhotos);
    if($errors)
			$failure = join("<br>\n", $errors);
		else {
			$newPhotos = array();
      $zip = zip_open($zipFile);
			$errors = unpackArchivePhotos($zip, $dir, $newPhotos, $existingPhotos, $maxPixels);
			foreach($newPhotos as $photo) registerPhoto($photo);
			echo join("<br>\n", $errors);
		}
  }
  error_reporting($oldError);
//if(mattOnlyTEST() && $failure) {echo $failure;exit;}  
  return $failure;
}
