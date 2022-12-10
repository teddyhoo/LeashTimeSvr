<? //provider-files.php
use Aws\S3\S3Client;
$scriptstarttime = microtime(1);
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "remote-file-storage-fns.php";
require_once "provider-fns.php";
require_once "pet-fns.php"; // for $maxSize
require_once 'aws-autoloader.php';

$auxiliaryWindow = true; // prevent login from appearing here if session times out

$isProvider = userRole() == 'p';
if($isProvider) {
	locked('p-');
	$ownerptr = $_SESSION["providerid"];
}
else {
	locked('#ec');
	$ownerptr = $_REQUEST['id'];
}


define_tblremotefile(); // ensure table is defined

if($_REQUEST['editsave']) {
	setProviderDoc($_REQUEST['provid'], $_REQUEST['fileid'], $_REQUEST['label'], $_REQUEST['officeonly'], $_REQUEST['providerreadonly']);
	echo "<script>parent.document.location.href='?id={$_REQUEST['provid']}'</script>";
	exit;
}
else if($_REQUEST['edit']) {
	$provid = $_REQUEST['provid'];
	$fileid = $_REQUEST['edit'];
	$doc = getProviderDoc($provid, $fileid);
	if(!$doc) $doc = getBlankProviderDoc($provid, $fileid);
	$filename = getRemoteFileEntry($fileid);
	$filename = baseName($filename['remotepath']);
	require "frame-bannerless.php";
	echo "
	<h3>Settings for: $filename</h3>
	<form name='entryeditor' method='POST'>
	";
	hiddenElement('provid', $provid);
	hiddenElement('fileid', $fileid);
	hiddenElement('filename', $filename);
	hiddenElement('editsave', 1);
	echoButton(null, 'Save', 'document.entryeditor.submit()');
	echo " ";
	echoButton(null, 'Quit', 'parent.$.fn.colorbox.close();');
	echo "<table>";
	inputRow('Label', 'label', $doc['label'], $labelClass=null, $inputClass='Input45Chars'); // VeryLongInput
	checkboxRow('Office Only', 'officeonly', $doc['officeonly']);
	checkboxRow('Sitter Read Only', 'providerreadonly', $doc['providerreadonly']);
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
	$ownertable = 'tblprovider';
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
		dropProviderDoc($ownerptr, $_REQUEST['delete']);
		$message = "Deleted file $basename";
	}
	else $error = "Could not delete $basename";
}
//else $ownerptr = $_REQUEST['id'];
$provider = getProvider($ownerptr);
//$files = listRemoteFilesForOwner($ownerptr, 'tblclient');
$files = getProviderFiles($ownerptr);
$extraHeadContent = 
	'<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
	<link rel="stylesheet" href="font-awesome-4.6.3/css/font-awesome.min.css">
	<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
  <script type="text/javascript" src="colorbox/version1.3.19/jquery.colorbox-min.js"></script>';
require "frame-bannerless.php";

$buttonStyle = " class='fa fa-refresh fa-1x' style='display:inline;color:gray;cursor:pointer;'"; //"style='width:5px height:5px;'";
$refresh = "<div $buttonStyle title='Refresh' onclick='document.location.href=\"?id=$ownerptr\"'></div>";

if($isProvider)
	$pageHeader = "Your Documents";
else
	$pageHeader = "Sitter: ".fullname($provider)."&apos;s Documents".(!$provider['active'] ? ' <font color=red>(Inactive)</font> ' : '')." $refresh";
?>
<h2><?= $pageHeader ?></h2>
<?
$noticeClass = $error ? "class='warning'" : "class='tiplooks'";
if($error) echo "<p id='notice' $noticeClass>$error</p>";
else echo "<p id='notice' $noticeClass>$message</p>"; // may be empty

?>
<form name='uploadform' method='post' enctype='multipart/form-data'>
<? 
$title = "title= 'Upload a file, possibly replacing an existing file.'";
echo "<br><b>Upload file</b> 
<input $title type='file' id='upload' name='upload' autocomplete='off' onchange='uploadChanged(this)'>";
if($_SESSION['preferences']['enableOfficeDocuments']) {
	require_once "office-files-fns.php";
	//print_r(visibleDocumentLinks('Sitters'));
	foreach(visibleDocumentLinks('Sitters') as $item) {
		$action = $item['action'] ? $item['action'] 
								: "openConsoleWindow(\"fileviewer\", \"{$item['url']}\", 700, 700);";
		$standardDocs[] = fauxLink($item['label'], $action, 'noEcho', 'Open');
		$download = "<div class=\"fa fa-download fa-1x\" style=\"display:inline;color:gray;cursor:pointer;\" title=\"Download\" ONCLICK></div>";
		$downloads[] = str_replace('ONCLICK', "onclick=\"download({$item['fileid']})\"", $download);
	}
	if($standardDocs) {
		$standardLabel = $_SESSION['preferences']['bizname'] ? $_SESSION['preferences']['bizname'] : (
											$_SESSION['preferences']['shortBizName'] ? $_SESSION['preferences']['shortBizName'] : 'Standard');
		echo "\n<div id='standardDocs' style='display:none;'><h2>$standardLabel Documents</h2>Click a link to view the document or the download icon ($download) to retrieve it.<p>";
		foreach($standardDocs as $i => $link) echo "\n{$downloads[$i]} $link<p>";
		echo "\n</div>";
		echo "\n<div style='float:right;'>"
					.fauxLink("Standard Documents", 
										'$.fn.colorbox({html:document.getElementById("standardDocs").innerHTML, width:"550", height:"400", scrolling: true, opacity: "0.3"});',
										'noecho',
										'Review and download standard documents.')
					."</div>";
	}
}
hiddenElement('id', $ownerptr);

echo "<br>Maximum file size approx: ".number_format((int)($maxBytes / 1024))." KB<p>";
?>
</form>
Each file may have a label, which is displayed in the Mobile Sitter App.  Use the pencil (<div class="fa fa-pencil fa-1x" style="display:inline;color:gray;"></div>) to edit labels.<p>
<?
$legend = explodePairsLine('download|download file||folder-open-o|open file||pencil|edit label & permissions||ban|edit label & permissions||lock|edit label & permissions');
foreach($legend as $icon => $desc) {
	$buttonStyle = " class='fa fa-$icon fa-1x' style='display:inline;color:gray;cursor:pointer;'";
	$icons[$icon] = "<div $buttonStyle title='Download' onclick='download($remotefileid)'></div>";
}
$sepr = "<img src='art/spacer.gif' width=20>";
echo "{$icons['download']}={$legend['download']} $sepr {$icons['folder-open-o']}={$legend['folder-open-o']}"
			."$sepr{$icons['pencil']}, {$icons['ban']}, {$icons['lock']}=edit label, office-only, and read-only<p>";
	
?>

<table width=600 style='background:white;'>
<?
if(!$files) echo "<tr><td colspan=2>No files found.</td></tr>";
else echo "<tr><th></th><th align='left'>File</th><th align='left'>Date</th><th align='left'>Type</th><th align='right'>Size</th></tr>";
$basenames = array();
$banneduploads = array();
foreach($files as $remotefileid => $remotePath) {
	$basename = basename($remotePath);
	$doc = getProviderDoc($ownerptr, $remotefileid);
	if(!$doc) $doc = getBlankProviderDoc($ownerptr, $fileid);
	if($isProvider && $doc['officeonly']) {
		$banneduploads[] = $basename;
		continue;
	}
	if($doc['providerreadonly']) 
		$banneduploads[] = $basename;
	else $basenames[] = $basename;
	$obj = remoteObjectDescription(absoluteRemotePath($remotePath));
	$time = strtotime($obj['LastModified']);
	$date = shortDate($time)." ".date('H:i', $time);
	$type = mimeTypeLabel($obj['ContentType']);
	$size = (int)($obj['ContentLength']/1024)." KB";
	$link = fauxLink($basename, "fileView($remotefileid)", 'noEcho', 'Open'/*, $remotePath */);
	$safeName = safeValue($basename);
	
	$buttonStyle = " class='fa fa-download fa-1x' style='display:inline;color:gray;cursor:pointer;'"; //"style='width:5px height:5px;'";
	$download = "<div $buttonStyle title='Download' onclick='download($remotefileid)'></div>";
	
	$buttonStyle = " class='fa fa-folder-open-o fa-1x' style='display:inline;color:gray;cursor:pointer;'"; //"style='width:5px height:5px;'";
	$open = "<div $buttonStyle title='Open' onclick='fileView($remotefileid)'></div>";

	if(!$isProvider || !$doc['providerreadonly']) {
		$kill = "<img src='art/delete.gif' height=15 onclick='deleteFile($remotefileid, \"$safeName\")'>";
		$buttonStyle = " class='fa fa-pencil fa-1x' style='display:inline;color:gray;cursor:pointer;'"; //"style='width:5px height:5px;'";
		$edit = "<div $buttonStyle title='Edit label and permissions' onclick='editProviderDoc($remotefileid)'></div>";
	}
	else {
		$kill = null;
		$edit = null;
	}
	
	$onclick = $isProvider ? "onclick='alert(\"This file is read only.\")'" : "onclick='editProviderDoc($remotefileid)'";
	$perms = array();
	if($doc['officeonly']) {
		$buttonStyle = " class='fa fa-ban fa-1x' style='display:inline;color:gray;cursor:pointer;' $onclick"; //"style='width:5px height:5px;'";
		$perms[] = "<div $buttonStyle title='Office Only'></div>";
	}
		
	if($doc['providerreadonly']) {
		$forSitters = $isProvider ? '' : " for sitters";
		$buttonStyle = " class='fa fa-lock fa-1x' style='display:inline;color:gray;cursor:pointer;' $onclick"; //"style='width:5px height:5px;'";
		$perms[] = "<div $buttonStyle title='Read Only$forSitters'></div>";
	}
	
	$type = $perms ? $type.' '.join(' ', $perms) : $type;
		

//function fauxLink($label, $onClick, $noEcho=false, $title=null, $id=null, $class=null, $style=null) {
	//$refs = "<img title='Find references' src='art/help.jpg' width=15 onclick='ajaxGet(\"?fileusage=$remotefileid\", \"fileid_$remotefileid\")'>";
	//echo "<tr><td>$kill $download $link</td><td>$date</td><td>$type</td><td>$refs</td></tr>";
	//echo "<tr><td id='fileid_$remotefileid' colspan=4></td></tr>";
	$label = $doc['label'] ? safeValue($doc['label']) : ''; //'<i>No label</i>';
	if($edit) $label = fauxlink($label, "editProviderDoc($remotefileid)", 1, 'Edit the label and access permissions of this file');;
	if($label) $label .= '<p>';
	echo "<tr style='border-top:solid lightgrey 1px'><td>$kill $download</td>"
				."<td>$edit $label$open $link</td><td>$date</td><td>$type</td><td align='right'>$size</td></tr>";
}
?>

</table>
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

function download(id) {
	document.location.href = 'client-file-view.php?download=1&id='+id;
}

var basenames = <?= json_encode($basenames) ?>;
var banneduploads = <?= json_encode($banneduploads) ?>;

function basename(path) {
	return path.split(/[\\/]/).pop();
}

function fileIsListed(file) {
	if(basenames == null) return false;
	file = basename(file);
	for(var i=0; i < banneduploads.length; i++) 
		if(banneduploads[i] == file) return {banned: file};
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
		
function editProviderDoc(remotefileid) {
	$.fn.colorbox({href:"provider-files.php?provid=<?= $ownerptr ?>&edit="+remotefileid, width:"510", height:"300", iframe: true, scrolling: true, opacity: "0.3"});
}
</script>
<?
function findReferencesToClientsFile($client, $id) {
}

function findReferencesToFile($id) {
}
	//$filePetFields = fetch
	//	return fetchKeyValuePairs("SELECT fieldname, value FROM relclientcustomfield WHERE clientptr = $client");