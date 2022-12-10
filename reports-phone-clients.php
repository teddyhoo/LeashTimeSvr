<? // reports-phone-clients.php
// for the clients supplied, dump a line for each textable phone number they possess
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "field-utils.php";
require_once "gui-fns.php";

$locked = locked('o-');

$status = $_REQUEST['status'];
$status = $status == 'active' ? "AND active = 1" : ($status == 'inactive' ? "AND active = 0" : '1=1');
$clientids = $_REQUEST['ids'] ? explode(',', $_REQUEST['ids']) : (
						$_REQUEST['filteredlist'] && $_SESSION['clientListIDString'] ? explode(',', $_SESSION['clientListIDString']) 
						: fetchCol0("SELECT clientid FROM tblclient WHERE $status"));
						
$clientids = $_REQUEST['ids'] ? $_REQUEST['ids'] : $_SESSION['clientListIDString'];
//unset($_SESSION['clientListIDString']);

if(!$clientids) {
	$phonelist = "No clients found.";
}

extract(extractVars($allFilterFields = 'textable,primaryonly,homephoneonly,cellphoneonly,workphoneonly,cellphone2only', $_REQUEST));
						
if($clientids) {
	$result = doQuery(
		"SELECT fname, lname, CONCAT_WS(',', fname, lname) as fullName, homephone, cellphone, workphone, cellphone2
			FROM tblclient
			WHERE clientid IN ($clientids)
			ORDER BY lname, fname
		", 1);
//echo "textable: $textable  primaryonly: $primaryonly";
	$numbers = array();
	foreach(explode(',', 'homephone,cellphone,workphone,cellphone2') as $fld)
		if($_REQUEST["{$fld}only"]) $anyField[] = $fld;
	while($row = fetchResultAssoc($result)) {
		foreach($row as $k => $v) {
			if(strpos($k, 'phone') === FALSE) continue;
			if($textable && !textMessageEnabled($v)) continue;
			if($primaryonly && !markedPrimary($v)) continue;
			if($anyField && !in_array($k, $anyField)) continue;
			
			// canonicalUSPhoneNumber, usablePhoneNumber, strippedPhoneNumber
			if(strlen($canonicalUSPhoneNumber = canonicalUSPhoneNumber($v)) == 12
				 && !$numbers[$canonicalUSPhoneNumber]) {
					$numbers[$canonicalUSPhoneNumber] = 1;
					$phonelist .= "\"{$row['fname']}\",\"{$row['lname']}\",\"$canonicalUSPhoneNumber\"\n";
			}
		}
	}
}

if($_REQUEST['csv'] && $phonelist) {
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Client-Phone-Report.csv ");
	echo $phonelist;
	exit;
}

require_once "frame-bannerless.php";
echo "<h2>Client Phone List Data</h2>";
if(TRUE || mattOnlyTEST()) {
echo "Include only: ";
$labels = explodePairsLine('textable|Textable||primaryonly|Primary||homephoneonly|Home||cellphoneonly|Cell||workphoneonly|Work||cellphone2only|Alt');
foreach($labels as $key => $label) {
	labeledCheckbox("$label phones", $key, $_REQUEST[$key], $labelClass=null, $inputClass=null, $onClick='go()', $boxFirst=true, $noEcho=false, $title="Include only $label numbers");
	if($key == 'primaryonly') {
		if($phonelist) {
			$params = $_SERVER["QUERY_STRING"];
			//$params = $params ? "?csv=1&$params" : '';
			fauxLink("<img src='art/spreadsheet-32x32.png' height=16 width=16 border=0> CSV", "goCSV(\"$params\")");
			//echo " <a href='reports-phone-clients.php?$params'><img src='art/spreadsheet-32x32.png' height=16 width=16 border=0> CSV</a>";
		}
		echo "<p>Restrict to: ";
	}
	else echo " ";
}
}
else {
labeledCheckbox('Primary phones only', 'primaryonly', $primaryonly, $labelClass=null, $inputClass=null, $onClick='go()', $boxFirst=true, $noEcho=false, $title='Include only numbers marked as the Primary contact number');
echo " ";
labeledCheckbox('Textable phones only', 'textable', $textable, $labelClass=null, $inputClass=null, $onClick='go()', $boxFirst=true, $noEcho=false, $title='Include only numbers marked as Textable phone numbers');
}
?>
<p>
<textarea class='fontSize1_2em' style='width:400px;height:400px;'><?= $phonelist ?></textarea>
<script>
function goCSV(params) {
	document.location.href='reports-phone-clients.php?csv=1&'+params;
}

function go() {
	var atts = [];
<? if(TRUE || mattOnlyTEST()) { ?>
	var allFilterFields = '<?= $allFilterFields ?>';
	allFilterFields.split(',').forEach(
		function(item, index) {if(document.getElementById(item).checked) atts.push(item+'=1');}
	);
<? }  else { ?>
	if(document.getElementById('textable').checked) atts.push('textable=1');
	if(document.getElementById('primaryonly').checked) atts.push('primaryonly=1');
<? } ?>
	var url = 'reports-phone-clients.php'+(atts.length > 0 ? "?"+atts.join('&') : '');
	document.location.href=url;
}
</script>