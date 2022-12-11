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
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "postcard-fns.php";
require_once "email-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

$maxBytes =  6 * 1024 * 1024;
$maxPixels = $maxBytes;
$maxDim = 8000; // round(sqrt($maxPixels));
$displaySize = array(500, 500);
$thumbnailDims = array(75, 40);

extract(extractVars('clientid,thumb,display,full,visit,sametab', $_REQUEST));

$postcardMediaAllowed = getClientPreference($clientid, 'postcardMediaAllowed'); // photosOnly, iPhoneOnly, null=any
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
		
		$failure = uploadAttachment("attachment", $attachmentName, $makeDisplayVersion=false, $allowedTypes, $allowedTypeIndivDescr);
	}
	if(!$failure) {
		$postcardEmailAddress = getClientPreference($clientid, 'postcardEmail');
		$cardid = insertTable('tblpostcard',
			array(
				'created'=> date('Y-m-d H:i:s'),
				'attachment'=>($attachmentName ? $attachmentName : sqlVal('NULL')),
				'note'=>$_POST['note'],
				'emailed'=>($postcardEmailAddress ? $postcardEmailAddress : sqlVal('NULL')),
				'expiration'=>date('Y-m-d H:i:s', strtotime("+180 days")),
				'clientptr'=>$clientid,
				'providerptr'=>$provid,
				'appointmentptr'=>$_POST['visit']
				), 1);
				
		if($postcardEmailAddress) {
			$client = getClient($clientid);
			$providerName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = $provid LIMIT 1", 1);
			$client['email'] = $postcardEmailAddress;
			enqueueEmailNotification($client, "A postcard from $providerName", emailMessageForPostcard($cardid), null, $_SESSION["auth_login_id"], 'html');
		}
				
		$message = "Postcard Sent!";
	}
}
$extraBodyStyle = "background-image: url(art/postcardbg.jpg);padding-left: 15px;padding-top: 15px;";
require "mobile-frame-bannerless.php";
echo "<div style='background:#FCE9A5;height:550px;'>";

if($message) {
	echo "<center><h2>$message</h2>";
	$doneAction = $sametab ? "document.location.href='appointment-view-mobile.php?id=$sametab'" : 'window.close()';
	echoButton(null, "Done", $onClick=$doneAction, $class='Button h2', $downClass='ButtonDown h2', $noEcho=false, $title=null);
	echo "</center>";
	exit;
}
echo "<h2 style='text-align:center;'>Send a Postcard to </h2>";
echo "<h2 style='text-align:center;'>".fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = $clientid LIMIT 1", 1)."</h2>";
echo "<form name='postcardeditor' method='post' enctype='multipart/form-data'>\n";
echoButton(null, "Send", $onClick='sendPostcard()', $class=null, $downClass=null, $noEcho=false, $title=null);
echo " $allowedTypesDescr ";
$quitAction = $sametab ? "document.location.href=\"appointment-view-mobile.php?id=$sametab\"" : 'window.close()';

echoButton(null, "Quit", $onClick=$quitAction, $class=null, $downClass=null, $noEcho=false, $title=null);
if($failure) echo "<span class='warning'><br>$failure</span>";
hiddenElement('MAX_FILE_SIZE', $maxBytes);
hiddenElement('clientid', $clientid);
hiddenElement('visit', $visit);
hiddenElement('sametab', $sametab);
echo "<table width=90%>";
$noFileUpload = preg_match('/Windows Phone/i', $_SERVER['HTTP_USER_AGENT']);
if($noFileUpload) echo "<br>No file upload available on this device.";
else {
	$title = "title= 'Choose a new photo or video for this postcard.'";
	echo "<tr><td><input $title type='file' id='attachment' name='attachment' autocomplete='off' onchange=''>";
}
echo " <img src='art/help.png' width=25 height=25 onclick='toggleHelp()'>";
$z = number_format($maxDim);
echo "<div id='limits' style='display:none;background:palegreen;' onclick='toggleHelp()'>If you send a photo or video:<br>Maximum file size approx: ".number_format((int)($maxBytes / 1024))." KB.<br>"
	."Max. photo size: ".sprintf('%01.2f', $maxPixels / (1024 * 1024.0))." megapixels (approx. $z X $z).<br>"
	."Max video length 10-12 seconds.</div>";
echo "</td></tr>";
textRow('Note:', 'note', $_REQUEST['note'], $rows=10, $cols=44, null, 'fontSize1_2em');
echo "</table>";
echo "</form>";
echo "</div>";
?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>

var validExtensions = '<?= join(',', $allowedTypes) ?>'.split(',');

function toggleHelp() {
	var display = document.getElementById("limits").style.display;
	display = display == 'block' ? 'none' : 'block';
	document.getElementById("limits").style.display = display;
}

function sendPostcard() {
	//alert(document.getElementById('attachment').value);
	var message = null, attachment, attachmentsize=0, extensionIsValid=false;
	attachment = document.getElementById('attachment');
	if(trim((String)(attachment.value)) == '' 
			&& trim((String)(document.getElementById('note').value)) == '')
		message = "You must attach a file, write a note, or do both for the postcard to be sent.";
	else {
		for(var i=0; i < validExtensions.length; i++)
			extensionIsValid = extensionIsValid || attachment.value.toUpperCase().indexOf('.'+validExtensions[i]) != -1;
		if(!extensionIsValid) message = "The chosen file is not a <?= $allowedTypeIndivDescr ?>";
		else if(window.FileReader) {
			if(attachment.files && (attachment.files.length == 0 || attachment.files[0].size > <?= $maxBytes ?>))
				message = "Files larger than <?= number_format($maxBytes) ?> bytes cannot be sent.";
		
		}
	}
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



