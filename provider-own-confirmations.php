<? // provider-own-confirmations.php   
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";

// Determine access privs
$locked = locked('p-');

if($_GET['msgid']) {
	$msg = fetchFirstAssoc("SELECT subject, body FROM tblmessage WHERE msgid = {$_GET['msgid']} LIMIT 1");
	$msg = preg_replace ('/Please click one of these links(.*) this change./' , '#PART#' , $msg );	
	echo '<link rel="stylesheet" href="pet.css" type="text/css" />';
	echo "<span class='fontSize1_2em'>{$msg['subject']}<br></span><hr>";
	echo $msg['body'];
	exit;
}

$pageTitle = "Confirmations";

include "frame.html";
// ***************************************************************************
$confs = fetchAssociations(
	"SELECT tblconfirmation.*, subject
		FROM tblconfirmation
		LEFT JOIN tblmessage ON msgid = msgptr
		WHERE respondentptr = {$_SESSION["providerid"]} AND respondenttable = 'tblprovider'
		ORDER BY due DESC");
$now = time();
foreach($confs as $i => $conf) 
	if($conf['resolution'] == 'pending' && strtotime($conf['expiration']) > $now) {
		$unanswered[] = $conf;
		unset($confs[$i]);
	}
$rows = array();
foreach((array)$unanswered as $conf) {
	$row['due'] = shortDateAndTime(strtotime($conf['due']));
	$row['subject'] = fauxLink($conf['subject'], "reviewMessage({$conf['msgptr']})", 1);
	$rows[] = $row;
}
$columns = explodePairsLine('due|Reply Due||subject|Subject');
if(!$rows) echo "No unanswered confirmnation requests found.";
else tableFrom($columns, $rows, $attributes='', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);


// ***************************************************************************
?>
<p><img src='art/spacer.gif' height=300 width=1>
<script language='javascript'>
function reviewMessage(id) {
	var url = "provider-own-confirmations.php?msgid="+id;
	$.fn.colorbox({href:url, iframe: "true", width:"750", height:"470", scrolling: true, opacity: "0.3"});
}
</script>
<?
include "frame-end.html";
