<? // mobile-provider-files.php
use Aws\S3\S3Client;
$scriptstarttime = microtime(1);
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "remote-file-storage-fns.php";
require_once "provider-fns.php";
require_once 'aws-autoloader.php';

$auxiliaryWindow = true; // prevent login from appearing here if session times out

locked('p-');
$ownerptr = $_SESSION["providerid"];


define_tblremotefile(); // ensure table is defined

//else $ownerptr = $_REQUEST['id'];
$provider = getProvider($ownerptr);
//$files = listRemoteFilesForOwner($ownerptr, 'tblclient');
$files = getProviderFiles($ownerptr);
$noticeClass = $error ? "class='warning'" : "class='tiplooks'";
if($error) echo "<p id='notice' $noticeClass>$error</p>";
else echo "<p id='notice' $noticeClass>$message</p>"; // may be empty

?>
<!-- table width=600 style='background:white;' -->
<table class='visitlist' cellspacing=0>

<?
foreach($files as $remotefileid => $remotePath) {
	$basename = basename($remotePath);
	$doc = getProviderDoc($ownerptr, $remotefileid);
	if(!$doc) $doc = getBlankProviderDoc($ownerptr, $fileid);
	$obj = remoteObjectDescription(absoluteRemotePath($remotePath));
	$time = strtotime($obj['LastModified']);
	$date = shortDate($time)." ".date('H:i', $time);
	$type = mimeTypeLabel($obj['ContentType']);
	$size = (int)($obj['ContentLength']/1024)." KB";
	$link = "fileView($remotefileid)";
	$safeName = safeValue($basename);

//function fauxLink($label, $onClick, $noEcho=false, $title=null, $id=null, $class=null, $style=null) 
	//$refs = "<img title='Find references' src='art/help.jpg' width=15 onclick='ajaxGet(\"?fileusage=$remotefileid\", \"fileid_$remotefileid\")'>";
	//echo "<tr><td>$kill $download $link</td><td>$date</td><td>$type</td><td>$refs</td></tr>";
	//echo "<tr><td id='fileid_$remotefileid' colspan=4></td></tr>";
	$label = $doc['label'] ? safeValue($doc['label']) : $safeName; //'<i>No label</i>';
	$docs[] = array('label'=>$label, 'url'=>$link, 'type'=>$type);
}
?>

</table>
<?
require_once "mobile-frame.php";
$displayDate = longerDayAndDate(strtotime($date));
if($calendarTest) $displayDate = "<div id='dateDisplayed'>$displayDate</div>";


$pageHeader = "My Documents";

?>
<style>
.visitlist td {padding-top:20px;}
</style>
<h2><?= $pageHeader ?></h2>
<div class='pagecontentdiv'>
<table class='visitlist' cellspacing=0>
<? 
if(!$docs)
	echo "<tr style='text-align:center;color:green;font-style:italic;'><td>No documents found..<td></tr>";
else foreach($docs as $doc)
	echo "<tr><td>"
				.fauxLink($doc['label'], $doc['url'], 1)
				."</td><td>{$doc['type']}</td></tr>";
?>
</table>

</div> <!-- pagecontentdiv -->
<? if(!$isMobile) { ?>
</div><!-- TESTFRAME -->
<? } ?>



<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
function fileView(id) {
	openConsoleWindow('fileviewer', 'client-file-view.php?id='+id, 700, 700);
}

function download(id) {
	//document.location.href = 'client-file-view.php?download=1&id='+id;
}

</script>
