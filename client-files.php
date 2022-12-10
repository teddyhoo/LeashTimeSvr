<? //client-files.php
use Aws\S3\S3Client;
$scriptstarttime = microtime(1);
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "remote-file-storage-fns.php";
require_once "client-fns.php";
require_once "pet-fns.php"; // for $maxSize
require_once 'aws-autoloader.php';

$auxiliaryWindow = true; // prevent login from appearing here if session times out

locked('#ec');

define_tblremotefile(); // ensure table is defined


if($_REQUEST['fileusage']) {
	$usageLines = findReferencesToFile($_REQUEST['fileusage']);
	echo $usageLines ? join('<br>', $usageLines) : 'Not referred to anywhere.';
	exit;
}

if($_FILES) {
	$ownerptr = $_REQUEST['id'];
	$formFieldName = 'upload';
	$ownertable = 'tblclient';
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
		$message = "Deleted file $basename";
	}
	else $error = "Could not delete $basename";
}
else $ownerptr = $_REQUEST['id'];
$client = getClient($ownerptr);
//$files = listRemoteFilesForOwner($ownerptr, 'tblclient');
$files = fetchKeyValuePairs(
	"SELECT remotefileid, remotepath 
		FROM tblremotefile
		WHERE ownerptr = $ownerptr AND ownertable = 'tblclient'
		ORDER BY remotepath", 1);
$extraHeadContent = 
	'<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
	<link rel="stylesheet" href="font-awesome-4.6.3/css/font-awesome.min.css">
	<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
  <script type="text/javascript" src="colorbox/version1.3.19/jquery.colorbox-min.js"></script>';
		
require "frame-bannerless.php";
	echo '<link rel="stylesheet" href="font-awesome-4.6.3/css/font-awesome.min.css">';	

$buttonStyle = " class='fa fa-refresh fa-1x' style='display:inline;color:gray;cursor:pointer;'"; //"style='width:5px height:5px;'";
$refresh = "<div $buttonStyle title='Refresh' onclick='document.location.href=\"?id=$ownerptr\"'></div>";

?>
<h2>Client: <?= fullname($client)."&apos;s Documents".($client['prospect'] ? ' (Prospect)' : '').(!$client['active'] ? ' <font color=red>(Inactive)</font> ' : '')." $refresh" ?></h2>
<div class='tiplooks'><img src="art/info.png" style='vertical-align:middle'> For sitters or clients to see attached documents, you must attach them to custom client or pet fields!</div><?
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
if($_SESSION['preferences']['enableOfficeDocuments']) {
	require_once "office-files-fns.php";
	foreach(visibleDocumentLinks('Clients') as $item) {
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
echo "<br>Maximum file size approx: ".number_format((int)($maxBytes / 1024))." KB<p>";
?>
</form>
<table width=600 style='background:white;'>
<?
if(!$files) echo "<tr><td colspan=2>No files found.</td></tr>";
else echo "<tr><th align='left'>File</th><th align='left'>Date</th><th align='left'>Type</th><th align='right'>Size</th></tr>";
foreach($files as $remotefileid => $remotePath) {
	$basenames[] = ($basename = basename($remotePath));
	$obj = remoteObjectDescription(absoluteRemotePath($remotePath));
	$time = strtotime($obj['LastModified']);
	$date = shortDate($time)." ".date('H:i', $time);
	$type = mimeTypeLabel($obj['ContentType']);
	$size = (int)($obj['ContentLength']/1024)." KB";
	$link = fauxLink($basename, "fileView($remotefileid)", 'noEcho'/*, $remotePath */);
	$safeName = safeValue($basename);
	$kill = "<img src='art/delete.gif' height=15 onclick='deleteFile($remotefileid, \"$safeName\")'>";
	$buttonStyle = " class='fa fa-download fa-1x' style='display:inline;color:gray;cursor:pointer;'"; //"style='width:5px height:5px;'";
	$download = "<div $buttonStyle title='Download' onclick='download($remotefileid)'></div>";

//function fauxLink($label, $onClick, $noEcho=false, $title=null, $id=null, $class=null, $style=null) {
	//$refs = "<img title='Find references' src='art/help.jpg' width=15 onclick='ajaxGet(\"?fileusage=$remotefileid\", \"fileid_$remotefileid\")'>";
	//echo "<tr><td>$kill $download $link</td><td>$date</td><td>$type</td><td>$refs</td></tr>";
	//echo "<tr><td id='fileid_$remotefileid' colspan=4></td></tr>";
	echo "<tr style='border-top:solid lightgrey 1px'><td>$kill $download $link</td><td>$date</td><td>$type</td><td align='right'>$size</td></tr>";
	$refs = findReferencesToClientsFile($client, $remotefileid);
	if($refs) echo "<tr><td colspan=3 style='color:gray'>".join("<br>", $refs)."</td></tr>";
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

function fileIsListed(file) {
	if(basenames == null) return false;
	for(var i=0; i < basenames.length; i++) 
		if(basenames[i] == file) return true;
	return false;
}

function uploadChanged(el) {
	if(el.type=='file' && el.value) {
		if(!fileIsListed(el.value) || confirm('This will replace the file: '+el.value)) {
			animateUpload();
			document.uploadform.submit();
		}
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
		

</script>
<?
function findReferencesToClientsFile($client, $id) {

	require_once "custom-field-fns.php";
	static $filePetFields, $fileClientFields;
	if(!$filePetFields) {
		foreach(getCustomFields(false, false, getPetCustomFieldNames()) as $k => $fld)
			if($fld[2] == 'file') $filePetFields[$k] = $fld;
		if(!$filePetFields) $filePetFields = -1;
	}
	if(!$fileClientFields) {
		foreach(getCustomFields(false, false) as $k => $fld)
			if($fld[2] == 'file') $fileClientFields[$k] = $fld;
		if(!$fileClientFields) $fileClientFields = -1;
	}

	//format: label|active|onelineORtextORbooleanORfile|visitsheet|clientvisible
	if($filePetFields && $filePetFields != -1) {
		$sql = "SELECT fieldname, value, name as pet 
						FROM relpetcustomfield
						LEFT JOIN tblpet ON petid = petptr
						WHERE value = $id AND fieldname IN ('".join("','", array_keys($filePetFields))."')";
		if($allRefs = fetchAssociationsIntoHierarchy($sql, array('pet'))) {
			$lines[] = "Pet Fields";
			foreach($allRefs as $pet => $fields) {
				$labels = array();
				foreach($fields as $fld) {
					$labels[] = $filePetFields[$fld['fieldname']][0];
				}
				$lines[count($lines)-1] .= " <b>$pet:</b> ".join(', ', $labels);
			}
		}
	}
	if($fileClientFields && $fileClientFields != -1) {
		$sql = "SELECT fieldname, value
						FROM relclientcustomfield
						WHERE clientptr = {$client['clientid']} AND value = $id AND fieldname IN ('".join("','", array_keys($fileClientFields))."')";
		if($fields = fetchAssociations($sql)) {
			foreach($fields as $fld) $labels[] = $fileClientFields[$fld['fieldname']][0];
			$lines[] = "Client Fields: ".join(', ', $labels);
		}
	}
	return $lines;
}

function findReferencesToFile($id) {
	require_once "custom-field-fns.php";
	//format: label|active|onelineORtextORbooleanORfile|visitsheet|clientvisible
	foreach(getCustomFields(false, false, getPetCustomFieldNames()) as $k => $fld);
		if($fld[2] == 'file') $filePetFields[$k] = $fld;
	if($filePetFields) {
		$sql = "SELECT fieldname, value, CONCAT_WS(' ', fname, lname) as client, name as pet 
						FROM relpetcustomfield
						LEFT JOIN tblpet ON petid = petptr
						LEFT JOIN tblclient ON clientid = ownerptr
						WHERE value = $id AND fieldname IN ('".join("','", array_keys($filePetFields))."')";
		if($allRefs = fetchAssociationsIntoHierarchy($sql, array('client', 'pet'))) {
			$lines[] = "Pet Fields";
			foreach($allRefs as $client => $pets) {
				if(count($allRefs) > 1) $lines[] = "Client: $client";
				foreach($pets as $pet => $fields) {
					$line = "$pet: ";
					$labels = array();
					foreach($fields as $fld) $labels[] = $filePetFields[$fld['fieldname']][0];
					$lines[] = $line.join(', ', $labels);
				}
			}
		}
	}
	foreach(getCustomFields(false, false) as $k => $fld);
		if($fld[2] == 'file') $fileClientFields[$k] = $fld;
	if($fileClientFields) {
		$sql = "SELECT fieldname, value, CONCAT_WS(' ', fname, lname) as client 
						FROM relclientcustomfield
						LEFT JOIN tblclient ON clientid = clientptr
						WHERE value = $id AND fieldname IN ('".join("','", array_keys($fileClientFields))."')";
		if($allRefs = fetchAssociationsIntoHierarchy($sql, array('client'))) {
			$lines[] = "Client Fields";
			foreach($allRefs as $client => $fields) {
				$line = count($allRefs) > 1 ? "Client: $client" : '';
				$labels = array();
				foreach($fields as $fld) $labels[] = $fileClientFields[$fld['fieldname']][0];
				$lines[] = $line.join(', ', $labels);
			}
		}
	}
	return $lines;
}
	//$filePetFields = fetch
	//	return fetchKeyValuePairs("SELECT fieldname, value FROM relclientcustomfield WHERE clientptr = $client");