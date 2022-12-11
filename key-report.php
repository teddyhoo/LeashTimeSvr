<?
// key-report.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "key-fns.php";
require_once "contact-fns.php";

// Determine access privs
locked('@ka,@ki,@#km');

$pageTitle = "Key Report";

include "frame.html";
// ***************************************************************************
$allClients = isset($_REQUEST['allclients']) && $_REQUEST['allclients'];
$activeOnly = isset($_REQUEST['activeonly']) && $_REQUEST['activeonly'];

if($allClients) echo "<a href='key-report.php?allclients=0&activeonly=$activeOnly'>Exclude clients without keys.</a>";
 else  echo "<a href='key-report.php?allclients=1&activeonly=$activeOnly'>Include clients without keys.</a>";

if(dbTEST('dogonfitnessbethesda')  || mattOnlyTEST()) {
echo " - ";
if(!$activeOnly) echo "<a href='key-report.php?activeonly=1&allclients=$allClients'>Exclude inactive clients.</a>";
else echo "<a href='key-report.php?activeonly=0&allclients=$allClients'>Include inactive clients.</a>";
}
echo "<p>";

$sort = $_REQUEST['sort'] ? $_REQUEST['sort'] : "client_asc";
$sortParts = explode('_', $sort);
if($sortParts[0] == 'client') $sort = "lname {$sortParts[1]}, fname {$sortParts[1]}";
else $sort = join(' ', $sortParts);

$activeOnlyPhrase = $activeOnly ? "active=1" : "1=1";

if($allClients)
	$sql = "SELECT tblkey.*, CONCAT_WS(' ',fname, lname, IF(active=0,'(inactive)', '')) as client, CONCAT_WS(' ',lname, fname) as clientsort 
	FROM tblclient 
		LEFT JOIN tblkey ON clientid = clientptr
	WHERE $activeOnlyPhrase
	ORDER BY $sort";
	
else $sql = "SELECT tblkey.*, CONCAT_WS(' ',fname, lname, IF(active=0,'(inactive)', '')) as client, CONCAT_WS(' ',lname, fname) as clientsort 
	FROM tblkey 
		LEFT JOIN tblclient ON clientid = clientptr
	WHERE (possessor1 IS NOT NULL OR possessor2 IS NOT NULL OR possessor3 IS NOT NULL 
				OR possessor4 IS NOT NULL OR possessor5 IS NOT NULL OR possessor6 IS NOT NULL
				OR possessor7 IS NOT NULL OR possessor8 IS NOT NULL OR possessor9 IS NOT NULL
				OR possessor10 IS NOT NULL)
				AND $activeOnlyPhrase 
	ORDER BY $sort";

	
$providerNames = getProviderShortNames();

//$result = doQuery($sql, 1);
//if(!mysqli_num_rows($result)) $out .= "No keys found.";
$keys = fetchAssociations($sql, 1);
if(!$keys) $out .= "No keys found.";
else {
	if($sortParts[0] == 'description') {
		usort($keys, 'compareDescriptions');
		if($sortParts[1] == 'desc') $keys = array_reverse($keys);
	}
	$rows = array();
	$numKeys = 0;
	$keyLocs = array();
	$clientIds = array();
	$columns = explodePairsLine("client|Client||locklocation|Key Location||description|Key Description");
	//while($key = mysqli_fetch_array($result, MYSQL_ASSOC)) {
	foreach($keys as $key) {
		$clientIds[] = $key['client'];
		if(!$key['keyid']) {
			$rowClasses[count($rows)] = 'daycalendardaterow';
			$rows[] = array('client'=>$key['client'], 'locklocation' =>"<font color=red>No key registered.</font>");
			continue;
		}
		$rowClasses[count($rows)] = 'daycalendardaterow';
		$rows[] = array('client'=>$key['client'], 'locklocation' =>"Key Hook: <b>{$key['bin']}</b>", 'description' =>$key['description']);
		$copies = 1;
		while(isset($key["possessor$copies"]) && $key["possessor$copies"]) {
			$keyLoc = $key["possessor$copies"];
			$numKeys++;
			if($keyLoc == 'missing') {
				$missingKeys[formattedKeyId($key['keyid'], $copies)] = $key;
			}
			if(!is_numeric($keyLoc)) $keyLocs[$keyLoc] +=1;
			else $keyLocs['providers'] +=1;
			$keyId = formattedKeyId($key['keyid'], $copies);
			$cb = "<input name='key_$keyId' id='key_$keyId' type='checkbox'>";
			$rows[] = array('#CUSTOM_ROW#'=>"<tr><td style='padding-left:10px;width:150px;' class='sortableListCell'>$cb Key ID: $keyId</td>".
												//"<td class='sortableListCell'>Key Hook: {$key['bin']}</td>".
												"<td class='sortableListCell'>".location($keyLoc)."</td></tr>");
			$copies++;
		}
		
		$noEmergencyContactInfo = $_SESSION['preferences']['suppressEmergencyContactinfo'] && userRole() == 'p';
		if(!$noEmergencyContactInfo) {
			foreach(getClientContacts($key['clientptr']) as $contact) {
				if(!$contact['haskey']) continue;
				$type = $contact['type'] == 'emergency' ? 'Emergency' : 'Neighbor';
				$rows[] = array('#CUSTOM_ROW#'=>"<tr><td style='padding-left:10px;width:150px;' class='sortableListCell'>".
												"<a title='View Emergency Contact Information.' href='client-edit.php?tab=emergency&id={$key['clientptr']}'>$type</a></td>".
													"<td class='sortableListCell'>{$contact['name']}</td></tr>");
			}
		}
	}
	if($missingKeys) {
		foreach($missingKeys as $keyId => $key)
			$missingText[] = str_replace("'", '&apos;', "<tr><td>{$key['client']}</td><td>$keyId</td></tr>");
		$missingText = "<h2>Missing Keys</h2><table>".join("", $missingText)."</table>";
	}
	$clientIds = count(array_unique($clientIds));
	echo "<div id='missingKeys' style='display:none;'>$missingText</div>";
?>
<table width=50% class='sortableListCell'>
<tr>
<td>
<?
	echo "We track $numKeys keys for $clientIds ".($activeOnly ? 'active' : 'total')." clients.<ul>";
	ksort($keyLocs);
	foreach($keyLocs as $loc => $count)
	  if(locationIsASafe($loc)) echo "<li>".toBe($count)." in ".location($loc).'.';
	echo "<li>".toBe($keyLocs['providers'])." with sitters.";
	echo "<li>".toBe($keyLocs['missing'])." <a href='javascript:showMissing()'>missing</a>.";
	echo "<li>".toBe($keyLocs['client'])." with clients.</ul>";
}
?>
</td><td align=center><img style='cursor:pointer' oncLick='exportToCSV()' src='art/spreadsheet-32x32.png'><br><?= fauxLink('Export to Spreadsheet', 'exportToCSV()', 1) ?></td></tr></table>
<?
fauxLink('Select All Keys', 'selectAll(1)');
echo " - ";
fauxLink('De-select All Keys', 'selectAll(0)');
echo "  ";
echoButton('', 'Print Selected Keys', 'printKeys()');
echo " ";
fauxLink('<img src="art/help.jpg" height=20 width=20 style="position:relative;top:5px;">', 'printHint()', 0, 'Click here for printing help.');
$columnSorts = array('client'=>'asc', 'description'=>'asc');
echo "<form name='printform' method='POST'>\n";
	tableFrom($columns, $rows, 'width=100%', null, null, null, null, $columnSorts, $rowClasses);
echo "</form>";
//function tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {

function compareDescriptions(&$a, &$b) {
	$aa = (is_numeric($a['description']) && $a['description'] == (int)$a['description']) ? (int)$a['description'] : $a['description'];
	$bb = (is_numeric($b['description']) && $b['description'] == (int)$b['description']) ? (int)$b['description'] : $b['description'];
	if(is_int($aa) && is_int($bb)) return $aa < $bb ? -1 : ($aa > $bb ? 1 : 0);
	if(is_int($aa) && !is_int($bb)) return -1;
	if(!is_int($aa) && is_int($bb)) return 1;
	$result = strcmp($aa, $bb);
	return $result ? $result : strcmp($a['clientsort'], $b['clientsort']);
}

function clientSort($a, $b) {
	strcmp($a['clientsort'], $b['clientsort']);
}
	
function toBe($num) {
	return !$num ? 'None are' : ($num == 1 ? 'One is' : "$num are");
}
	
function location($loc) {
	global $safes, $providerNames;
	if(in_array($loc, array('missing', 'client'))) return $loc;
	if(isset($safes[$loc])) return trim(str_replace('--','', "<i>{$safes[$loc]}</i>"));
	return $providerNames[$loc];
}
?>
<div style='display:none' id='printhint'>
<span class='fontSize1_1em'>
<span class='fontSize1_1em boldfont'>Printing Key Labels</span>
<p>
When printing out key labels, we want to make sure that Adobe Acrobat (or
whatever PDF reader software you use) does not change the size of the printed labels, 
because LeashTime sizes them specially to fit particular paper labels.
<p>
In most programs you can control the printed size in the <b>Print...</b> dialog that 
appears when you go to print the key labels.
<p>
Look for a setting called "Page Scaling" and make sure it is set to "none".  If there 
is no such option and there is instead a check box labeled "Fit to page", make sure that
it is unchecked.  Different versions of Acrobat offer this setting in different ways.  
You may need to hunt a bit.  And of course, the settings will probably differ if you do not
use Adobe Acrobat.
</span>
</div>

<script language='javascript' src='common.js'></script>
<script language='javascript'>

function printHint() {
	$.fn.colorbox({html:$('#printhint').html(), width:"550", height:"470", scrolling: true, opacity: "0.3"});
}

function exportToCSV() {
	document.location.href='key-report2.php';
}

function selectAll(state) {
	var sels=[];
	var allEls = document.getElementsByTagName('input');
	for(var i=0;i<allEls.length;i++)
		if(allEls[i].id.indexOf('key_') == 0)
			allEls[i].checked = state ? true : false;
}



function printKeys() {
	var allEls = document.getElementsByTagName('input');
	var keys = [];
	for(var i=0;i<allEls.length;i++) {
		if(allEls[i].id.indexOf('key_') == 0 && allEls[i].checked)
			keys[keys.length] = allEls[i].id.substring('key_'.length);
	}
	if(keys.length == 0) { 
		alert('Please select at least one key first.');
		return;
	}
	openConsoleWindow('labelprinter', "label-print.php?keys="+keys.join(','), 300, 200);

}

function showMissing() {
	var txt = document.getElementById('missingKeys').innerHTML;
	if(txt == '') txt = "No keys are listed as missing.";
	$.fn.colorbox({html:txt, width:'350', height:'450', iframe: false, scrolling: true, opacity: '0.3'});
}
</script>
<?
// ***************************************************************************

include "frame-end.html";
?>

