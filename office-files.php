<? // office-files.php
use Aws\S3\S3Client;
$scriptstarttime = microtime(1);
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "remote-file-storage-fns.php";
require_once "office-files-fns.php";
require_once "pet-fns.php"; // for $maxSize
require_once 'aws-autoloader.php';

$auxiliaryWindow = true; // prevent login from appearing here if session times out

locked('#ec');
$ownerptr = getOfficeOwnerPtr();

//hidden, audience: OfficeOnly/Sitters/Clients/Public

define_tblremotefile(); // ensure table is defined

if($_REQUEST['editsave']) {
	setOfficeDoc($_REQUEST['fileid'], $_REQUEST['label'],
									$_REQUEST['audience'], $_REQUEST['hidden']);
	echo "<script>parent.document.location.href='?'</script>";
	exit;
}
else if($_REQUEST['edit']) {
	$fileid = $_REQUEST['edit'];
	$doc = getOfficeDoc($fileid);
	if(!$doc) $doc = getBlankOfficeDoc($fileid);
	$filename = getRemoteFileEntry($fileid);
	$filename = baseName($filename['remotepath']);
	require "frame-bannerless.php";
	echo "
	<h3>Settings for: $filename</h3>
	<form name='entryeditor' method='POST'>
	";
	hiddenElement('fileid', $fileid);
	hiddenElement('filename', $filename);
	hiddenElement('editsave', 1);
	echoButton(null, 'Save', 'document.entryeditor.submit()');
	echo " ";
	echoButton(null, 'Quit', 'parent.$.fn.colorbox.close();');
	echo "<table>";
	inputRow('Label', 'label', $doc['label'], $labelClass=null, $inputClass='Input45Chars'); // VeryLongInput
	$options = explodePairsLine('Office Only|OfficeOnly||Sitters|Sitters||Clients|Clients||Public|Public');
	radioButtonRow('Audience', 'audience', $doc['audience'], $options);
	
	$options = explodePairsLine('Yes|1||No|0');
	radioButtonRow('Hidden', 'hidden', $doc['hidden'], $options);
	echo "</table>";
	exit;
}

if($_REQUEST['fileusage']) {
	$usageLines = findReferencesToFile($_REQUEST['fileusage']);
	echo $usageLines ? join('<br>', $usageLines) : 'Not referred to anywhere.';
	exit;
}

if($_FILES) {
	$formFieldName = 'upload';
	$ownertable = 'office';
	echo "[[$formFieldName]][[$ownerptr]][[$ownertable]]";
	$messages = uploadPostedFile($formFieldName, $ownerptr, $ownertable);
	$message = $messages['message'];
	$error = $messages['error'] ? "{$messages['error']}" : '';
}
else if($_REQUEST['delete']) {
	$entry = fetchFirstAssoc("SELECT * FROM tblremotefile WHERE remotefileid = {$_REQUEST['delete']} LIMIT 1", 1);
	$ownerptr = $entry['ownerptr'];
	$basename = basename($entry['remotepath']);
	if(deleteRemoteFileId($entry)) {
		deleteTable('tblremotefile', "remotefileid = {$_REQUEST['delete']}", 1);
		dropOfficeDoc($ownerptr, $_REQUEST['delete']);
		$message = "Deleted file $basename";
	}
	else $error = "Could not delete $basename";
}
$files = getOfficeFiles();
/*$extraHeadContent = 
	'<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
	<link rel="stylesheet" href="font-awesome-4.6.3/css/font-awesome.min.css">
	<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
  <script type="text/javascript" src="colorbox/version1.3.19/jquery.colorbox-min.js"></script>';*/
$pageTitle = "Office Documents";
$extraHeadContent = '<link rel="stylesheet" href="font-awesome-4.6.3/css/font-awesome.min.css">';
require "frame.html";

$buttonStyle = " class='fa fa-refresh fa-1x' style='display:inline;color:gray;cursor:pointer;'"; //"style='width:5px height:5px;'";
$refresh = "<div $buttonStyle title='Refresh' onclick='document.location.href=\"?id=$ownerptr\"'></div>";

$noticeClass = $error ? "class='warning'" : "class='tiplooks'";
if($error) echo "<p id='notice' $noticeClass>$error</p>";
else echo "<p id='notice' $noticeClass>$message</p>"; // may be empty

?>
<form name='uploadform' method='post' enctype='multipart/form-data'>
<? 
$title = "title= 'Upload a file, possibly replacing an existing file.'";
echo "<br><b>Upload file</b> 
<input $title type='file' id='upload' name='upload' autocomplete='off' onchange='uploadChanged(this)'>";
hiddenElement('id', $ownerptr);
echo "<br>Maximum file size approx: ".number_format((int)($maxBytes / 1024))." KB<p>";
?>
</form>
Each file may have a label, which is displayed in standard file lists.  Use the pencil (<div class="fa fa-pencil fa-1x" style="display:inline;color:gray;"></div>) to edit labels.<p>
<?
$legend = explodePairsLine('download|download file||folder-open-o|open file||pencil|edit label, audience, hidden||lock|hidden');
foreach($legend as $icon => $desc) {
	$buttonStyle = " class='fa fa-$icon fa-1x' style='display:inline;color:gray;cursor:pointer;'";
	$icons[$icon] = "\n<div $buttonStyle title='{$legend[$icon]}'></div>";
}
//OfficeOnly/Sitters/Clients/Public
//$audienceIcons = explodePairsLine('OfficeOnly|&#9412;||Sitters|&#9416;||Clients|&#9400;||Public|&#9413;');
$audienceIcons = explodePairsLine('OfficeOnly|[O]||Sitters|[S]||Clients|[C]||Public|[P]');
$audienceLabels = explodePairsLine('OfficeOnly|Office Only||Sitters|Sitters||Clients|Clients||Public|Public');

$sepr = "<img src='art/spacer.gif' width=15>";
echo "{$icons['download']}={$legend['download']} $sepr {$icons['folder-open-o']}={$legend['folder-open-o']}"
			."$sepr{$icons['pencil']}=edit label, category, and hidden"
			."$sepr{$icons['lock']}=file is hidden";
foreach($audienceIcons as $k => $icon)
	//echo "$sepr<span style='font-size:1.0em;font-weight:bold'>{$audienceIcons[$k]}</span>={$audienceLabels[$k]}";
echo "<p>";
?>

<table width=600 style='background:white;border-collapse: collapse;'>
<?
if(!$files) echo "<tr><td colspan=2>No files found.</td></tr>";
else echo "<tr><th></th><th align='left'>File</th><th align='left'>Date</th><th align='left'>Type</th><th align='right'>Size</th></tr>";
$basenames = array();
foreach($files as $remotefileid => $remoteFile) {
	//if(!is_string($remotePath)) echo "BAD remotePath: [$remotefileid] [".print_r($remotePath, 1)."]<hr>";
	//else  echo "GOOD remotePath: [$remotefileid] [".print_r($remotePath, 1)."]<hr>";
	$remotePath = $remoteFile['remotepath'];
	$basename = basename($remotePath);
	$doc = getOfficeDoc($remotefileid);
	if(!$doc) $doc = getBlankOfficeDoc($fileid);
	else $basenames[] = $basename;
	$obj = remoteObjectDescription(absoluteRemotePath($remotePath));
	$time = strtotime($obj['LastModified']);
	$date = shortDate($time)." ".date('H:i', $time);
	$type = mimeTypeLabel($obj['ContentType']);
	$size = (int)($obj['ContentLength']/1024)." KB";
	
	if($doc['audience'] == 'Public') {
		$url = publicDocumentLink($doc['fileid']);
		$fileOpenerJS = "publicFileView(\"$url\")";
		$link = fauxLink($basename, $fileOpenerJS, 'noEcho', 'Open Public URL'/*, $remotePath */);
		$buttonStyle = " class='fa fa-link fa-1x' style='display:inline;color:gray;cursor:pointer;'"; //"style='width:5px height:5px;'";
		$showLink = "$.fn.colorbox({html:\"Web link:<p>$url\", width:\"650\", height:\"200\", iframe: false, scrolling: true, opacity: \"0.3\"});";
		if($doc['hidden']) $warning = "<span class='warning'><br> -- Link won&apos;t work while document is hidden.</span>";
		$showLink = "<p><div $buttonStyle title='Show the public URL (web link) to this document' onclick='$showLink'> Show the web link$warning</div>";

	}
	else {
		$fileOpenerJS = "fileView($remotefileid)";
		$link = fauxLink($basename, $fileOpenerJS, 'noEcho', 'Open'/*, $remotePath */);
		$showLink = "";
	}
	
	$safeName = safeValue($basename);
	
	$buttonStyle = " class='fa fa-download fa-1x' style='display:inline;color:gray;cursor:pointer;'"; //"style='width:5px height:5px;'";
	$download = "<div $buttonStyle title='Download' onclick='download($remotefileid)'></div>";
	
	$buttonStyle = " class='fa fa-folder-open-o fa-1x' style='display:inline;color:gray;cursor:pointer;'"; //"style='width:5px height:5px;'";
	$open = "<div $buttonStyle title='Open' onclick='$fileOpenerJS'></div>";

	$kill = "<img src='art/delete.gif' height=15 onclick='deleteFile($remotefileid, \"$safeName\")'>";
	$buttonStyle = " class='fa fa-pencil fa-1x' style='display:inline;color:gray;cursor:pointer;'"; //"style='width:5px height:5px;'";
	$edit = "<div $buttonStyle title='Edit label and permissions' onclick='editOfficeDoc($remotefileid)'></div>";
	
	$onclick = "onclick='editOfficeDoc($remotefileid)'";
	$perms = array();
	$audIcon = $audienceIcons[$doc['audience']];
	$audTitle = $audienceLabels[$doc['audience']];
	$buttonStyle = "style='display:inline;color:gray;cursor:pointer;font-weight:bold;' $onclick"; //"style='width:5px height:5px;'";
	//$perms[] = "<div $buttonStyle title='$audTitle'>$audIcon</div>";
		
	if($doc['hidden']) {
		$buttonStyle = " class='fa fa-lock fa-1x' style='display:inline;color:gray;cursor:pointer;' $onclick"; //"style='width:5px height:5px;'";
		$perms[] = "<div $buttonStyle title='Hidden'></div>";
	}
	
	$type = $perms ? $type.' '.join(' ', $perms) : $type;
		

//function fauxLink($label, $onClick, $noEcho=false, $title=null, $id=null, $class=null, $style=null) {
	//$refs = "<img title='Find references' src='art/help.jpg' width=15 onclick='ajaxGet(\"?fileusage=$remotefileid\", \"fileid_$remotefileid\")'>";
	//echo "<tr><td>$kill $download $link</td><td>$date</td><td>$type</td><td>$refs</td></tr>";
	//echo "<tr><td id='fileid_$remotefileid' colspan=4></td></tr>";
	$label = $doc['label'] ? safeValue($doc['label']) : ''; //'<i>No label</i>';
	if($edit) $label = fauxlink($label, "editOfficeDoc($remotefileid)", 1, 'Edit the label and access permissions of this file');;
	if($label) $label .= '<p>';
	//echo "<tr style='border-top:solid lightgrey 1px'><td>$kill $download</td>"
	//			."<td>$edit $label$open $link</td><td>$date</td><td>$type</td><td align='right'>$size</td></tr>";
	$groupedDocs[$doc['audience']][] = 
		"<tr style='border-top:solid lightgrey 1px'><td>$kill $download</td>"
				."<td>$edit $label$open $link $showLink</td><td>$date</td><td>$type</td><td align='right'>$size</td></tr>";
}
foreach($audienceLabels as $sectionId => $sectionLabel) {
	if($group = $groupedDocs[$sectionId]) {
		echo "<tr style='border-top:solid #555555 1px'><td class='fontSize1_2em' style='padding-top:10px;' colspan=4><b>$sectionLabel Documents</b></td></tr>";
		foreach($group as $row) echo $row;
	}
}

?>

</table>
<? 
/*$_SESSION['preferences']['enableOfficeDocuments']
foreach(visibleDocumentLinks('Public') as $item) {
	$action = $item['action'] ? $item['action'] 
							: "openConsoleWindow(\"fileviewer\", \"{$item['url']}\", 700, 700);";
	echo '<p>'.fauxLink($item['label'], $action, 'noEcho', 'Open');
}
*/
?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
function deleteFile(id, name) {
	if(confirm('Delete file: '+name))
		document.location.href='?delete='+id;
}
function fileView(id) {
	openConsoleWindow('fileviewer', 'client-file-view.php?id='+id, 700, 700);
}

function publicFileView(url) {
	openConsoleWindow('fileviewer', url, 700, 700);
}

function download(id) {
	document.location.href = 'client-file-view.php?download=1&id='+id;
}

var basenames = <?= json_encode($basenames) ?>;

function basename(path) {
	return path.split(/[\\/]/).pop();
}

function fileIsListed(file) {
	if(basenames == null) return false;
	file = basename(file);
	for(var i=0; i < basenames.length; i++) 
		if(basenames[i] == file) return file;
	return false;
}

function uploadChanged(el) {
	var basefilename;
	if(el.type=='file' && el.value) {
		var conflict = fileIsListed(el.value);
		if(!conflict || (typeof conflict == 'string' && confirm('This will replace the file: '+conflict))) {
			animateUpload();
			document.uploadform.submit();
		}
		else if(conflict.banned) 
			alert('This file cannot be uploaded because a read-only copy of it already exists.');
	}
}

function animateUpload() {
	var el = document.getElementById('notice');
	/*var dots = el.innerHTML.length - el.innerHTML.indexOf('.');
	dots = dots == 3 ? 1 : dots + 1;
	var label = 'UPLOADING';
	for(var i=0; i< dots; i++) label = label+'.';
	*/
	el.class = 'tiplooks'; // does not work
	el.innerHTML = 'UPLOADING...<br>';
}
		
function editOfficeDoc(remotefileid) {
	$.fn.colorbox({href:"office-files.php?edit="+remotefileid, width:"510", height:"300", iframe: true, scrolling: true, opacity: "0.3"});
}
</script>
<?
function findReferencesToClientsFile($client, $id) {
}

function findReferencesToFile($id) {
}
require "frame-end.html";
	//$filePetFields = fetch
	//	return fetchKeyValuePairs("SELECT fieldname, value FROM relclientcustomfield WHERE clientptr = $client");