<? // remote-file-chooser.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "remote-file-storage-fns.php";
require_once "gui-fns.php";
require_once "pet-fns.php"; // for $maxSize
// client or provider or ... ?
// field = file custom field's element id

require_once 'aws-autoloader.php';
define_tblremotefile(); // ensure table is defined

if($_REQUEST['provider']) {
	locked('#ep');
	$ownerptr = $_REQUEST['provider'];
	$ownertable = 'tblprovider';
}
else if($_REQUEST['client']) {
	locked('#ec');
	$ownerptr = $_REQUEST['client'];
	$ownertable = 'tblclient';
}
if($_FILES) {
	$formFieldName = 'upload';
	$messages = uploadPostedFile($formFieldName, $ownerptr, $ownertable);
	$message = $messages['message'];
	$error = $messages['error'];
}

$files = fetchKeyValuePairs(
		"SELECT remotefileid, remotepath 
			FROM tblremotefile 
			WHERE ownertable = '$ownertable' AND ownerptr = $ownerptr");
			
foreach($files as $id => $f) {
	$files[$id] = basename($f);
}

function nmsort($a, $b) {
	return strcmp(strtoupper($a), strtoupper($b));
}

uasort($files, 'nmsort');
require "frame-bannerless.php";

if($error) echo "<p class='warning'>$error</p>";
else if($message) echo "<p class='tiplooks'>$message</p>";

echo "<b>Choose a file:</b> or "
.echoButton('', 'Cancel', "window.parent.$.fn.colorbox.close();", null, null, 'noecho')
." or "
.echoButton('', 'Clear Field', "chooseFile(0, \"\")", null, null, 'noecho')
." or "
.echoButton('', 'Upload a file', "var e= document.getElementById(\"uploader\"); e.style.display= (e.style.display==\"block\" ? \"none\" : \"block\")", null, null, 'noecho');
?>
<div id='uploader' style='display:none'>
<form name='uploadform' method='post' enctype='multipart/form-data'>
<? 
$title = "title= 'Upload a file, possibly replacing an existing file.'";
echo "<br><b>Upload file</b> 
<input $title type='file' id='upload' name='upload' autocomplete='off' onchange='uploadChanged(this)'>";
hiddenElement('id', $ownerptr);
if($ownertable == 'tblclient') hiddenElement('client', $ownerptr);
else if($ownertable == 'tblprovider') hiddenElement('provider', $ownerptr);

hiddenElement('ownertable', $ownertable);
echo "<br>Maximum file size approx: ".number_format((int)($maxBytes / 1024))." KB<p>";
?>
</form>
</div><p>
<?
foreach($files as $id => $f) {
	$safename = safeValue($f);
	fauxLink($f, "chooseFile($id, \"$safename\")");
	echo "<p>";
	$basenames[] = ($basename = $f);
}
?>
<script language='javascript'>
function chooseFile(id, filename) {
	window.parent.update('filecustomfield', JSON.stringify({field: "<?= $_GET['field'] ?>", remotefileid: id, remotename: filename}));
	window.parent.$.fn.colorbox.close();
}

var basenames = <?= json_encode($basenames) ?>;

function uploadChanged(el) {
	if(el.type=='file' && el.value) {
		if(!fileIsListed(el.value) || confirm('This will replace the file: '+el.value))
			document.uploadform.submit();
	}
}

function fileIsListed(file) {
	if(basenames == null) return false;
	for(var i=0; i < basenames.length; i++) 
		if(basenames[i] == file) return true;
	return false;
}

</script>