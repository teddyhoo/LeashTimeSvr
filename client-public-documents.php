<? // client-public-documents.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "client-fns.php";
require_once "office-files-fns.php";
require_once 'aws-autoloader.php';

// Determine access privs
$locked = locked('c-');

require_once 'preference-fns.php';
$_SESSION["preferences"] = fetchPreferences(); // to get the latest status of office docs


$standardLabel = $_SESSION['preferences']['bizname'] ? $_SESSION['preferences']['bizname'] : (
									$_SESSION['preferences']['shortBizName'] ? $_SESSION['preferences']['shortBizName'] : 'Standard');
$pageTitle = "$standardLabel Documents";
//print_r(visibleDocumentLinks('Clients'));
//print_r(visibleDocumentLinks('Clients'));
foreach(visibleDocumentLinks('Clients') as $item) {
	$action = $item['action'] ? $item['action'] : "openConsoleWindow(\"fileviewer\", \"{$item['url']}\", 700, 700);";
	$standardDocs[] = fauxLink($item['label'], $action, 'noEcho', 'Open');
	$download = "<div class=\"fa fa-download fa-1x\" style=\"display:inline;color:gray;cursor:pointer;\" title=\"Download\" ONCLICK></div>";
	$downloads[] = str_replace('ONCLICK', "onclick=\"download({$item['fileid']})\"", $download);
}


$extraHeadContent = <<<EXTRAHEAD
<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
<link rel="stylesheet" href="font-awesome-4.6.3/css/font-awesome.min.css">
EXTRAHEAD;

if($_SESSION["responsiveClient"]) {
	$extraHeadContent = "<style>body {font-size:1.2em;} .tiplooks {font-size:14pt;}</style>";
	include "frame-client-responsive.html";
	$frameEndURL = "frame-client-responsive-end.html";
}
else if(userRole() == 'c') {
	$extraHeadContent = <<<COLOR
	<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
	<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
	<script type="text/javascript" src="colorbox/jquery.colorbox.js"></script>
COLOR;
	include "frame-client.html";
	$frameEndURL = "frame-end.html";
}

echo "<div class='fontSize1_5em'>";
if($standardDocs) {
	echo "\nClick a link to view the document or the download icon ($download) to retrieve it.<p>";
	foreach($standardDocs as $i => $link) echo "\n{$downloads[$i]} $link<p>";
}
else echo "There are no documents to view.";
echo "</div>";
?>
<script src='common.js'></script>
<script>
function fileView(id) {
	openConsoleWindow('fileviewer', 'client-file-view.php?id='+id, 700, 700);
}

function download(id) {
	document.location.href = 'client-file-view.php?download=1&id='+id;
}
</script>
<?
include $frameEndURL;
