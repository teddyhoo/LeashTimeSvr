<? // vcard-phone-transfer.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "client-fns.php";
/* Given a VCF that has been used previously to populate the client list:
Show each client's current phone numbers
Show the item phone fields
Next to each item phone field, put [Home] [Work] [Cell] and [Cell 2] buttons
Clicking a button overwrites that field with the number
*/

//echo "This page is disabled.";
//exit;
if($db != 'dogcampla') {echo "You must be logged in as Dog Camp to use this page."; exit;}

extract($_GET);
//print_r($_GET);exit;	

function clientCell($client, $color) {
	$key = fetchRow0Col0("SELECT CONCAT_WS(' ', CONCAT('(LT#', keyid, ')'), description) FROM tblkey WHERE clientptr = {$client['clientid']} LIMIT 1");
	echo "<span style='background:$color'><b><a target='client' href='client-edit.php?tab=basic&id={$client['clientid']}'>{$client['fname']} {$client['lname']}</a></b><br>
						Home: {$client['homephone']}<br>
						Cell: {$client['cellphone']}<br>
						Work: {$client['workphone']}<br>
						Email: {$client['email']}<br>
						Key: $key<br>"
						.($spouse ? "<br>(Alt: $spouse)<br>" : '')
						."Alt cell: {$client['cellphone2']}<br>"
						."Alt email: {$client['email2']}"
						.'</span>';
}

if($field) {
	updateTable('tblclient', array($field=>$phone), "clientid = $id");
	clientCell(getClient($id), 'lightgreen');
	exit;
}
if($keydesc) {
	$keyid = fetchRow0Col0("SELECT keyid FROM tblkey WHERE clientptr = $id");
	if($keyid) updateTable('tblkey', array('description'=>$keydesc), "keyid = $keyid", 1);
	else insertTable('tblkey', array('description'=>$keydesc, 'clientptr'=>$id), 1);
	clientCell(getClient($id), 'lightgreen');
	exit;
}

$file = '/var/data/clientimports/dogcampla/DCLAvCards.vcf';
$badUIDs = 
"";
$badUIDs = array_map('trim', explode("\n", $badUIDs));

$strm = fopen($file, 'r');

while($rawline = fgets($strm)) {
	$line =trim($rawline);
	if($line == 'BEGIN:VCARD') {$vc = array(); $cards++;  continue;}
	else if($line == 'END:VCARD') {if($vc) $vcards[] = $vc; continue;}
	else if($line == 'VERSION:3.0') continue;
	else if(strpos($line, 'PHOTO') === 0) continue;
	else if(strpos($rawline, ' ') === 0) continue;
	$colon = strpos($line, ':');
	$key = substr($line, 0, $colon);
	$val = substr($line, $colon+1);
	$keyParts = explode(';', $key);
	$whole = array();
	foreach($keyParts as $i => $part) {
		if($i == 0) {
			$primary = $part;
			$whole[] = $primary;
		}
		if(strpos($part, '=')) {
			$part = explode('=', $part);
			if($part[0] == 'value') continue;
			else if($part[0] == 'type') {
				if($part[1] == 'INTERNET') continue;
				else if($part[1] == 'pref') {
				  if(strpos($key, 'TEL') !== FALSE) {/*echo "<font color=red>$key: $val</font><br>";*/$val = "*$val";}
				  $part[1] = '';
				}
			}
			if($part[1]) $whole[] = $part[1];
		}
		$wholekey = join('|', $whole);
		$vc[$wholekey] = $val;
		if(strpos($primary, 'item') === 0) 
			$allKeys[$wholekey][] = $val;
		else $allKeys[$wholekey] = 0;
	}
}
//echo "Cards: $cards<p>";
/*
Array ( 
	[N] => Wasserman; Lisa;;; 
	[FN] => Lisa Wasserman 
	[NICKNAME] => Sadie 
	[ORG] => ;DOG CAMP 
	[item1.EMAIL] => lisachouttca@yahoo.com 
	[item1.X-ABLabel] => E-Mail 
	[TEL] => 323-513-5159 
	[TEL|WORK] => 310-975-5913 
	[TEL|CELL] => 323-513-5159 
	[item2.TEL] => *(323) 513-5162 
	[item2.X-ABLabel] => lee cell 
	[item3.ADR] => ;;1519 Martel Ave. #301 ;L.A .;CA;90046;USA 
	[item3.ADR|HOME] => ;;1519 Martel Ave. #301 ;L.A .;CA;90046;USA 
	[item3.X-ABADR] => us 
	[item4.X-ABRELATEDNAMES] => Laurel Animal Hospital 
	[item4.X-ABLabel] => Veterinarian 
	[CATEGORIES] => Dog Camp LA 
	[X-ABUID] => 3A2ECFAB-0480-4FFC-AB38-3232B106439C\:ABPerson )
	*/
foreach($vcards as $vc) {
	$uids[] = $vc['X-ABUID'];
	$names[$vc['N']] = $vc;
}
ksort($names);
echo "<table border=1 bordercolor=black>";
foreach($names as $name => $vc) {
	$client = fetchFirstAssoc($sql = "SELECT tblclient.* 
															FROM relclientcustomfield
															LEFT JOIN tblclient ON clientid = clientptr
															WHERE fieldname = 'custom9' and value = '".mysqli_real_escape_string($vc['X-ABUID'])."'
															LIMIT 1");
//echo $sql;exit;															
	if(!$client) continue;
	$spouse = trim("{$client['fname2']} {$client['lname2']}");
	echo "<tr>
				<td valign=top id='client{$client['clientid']}'>";
	clientCell(getClient($client['clientid']), 'white');
	echo "</td>";
	echo "<td valign=top>";
	foreach(processItems($vc) as $item) {
		echo "{$item[0]}: {$item[1]} ";
		if(strpos($item[2], 'TEL')) {
			echoButton('', 'Home', "useNumber(\"homephone\", \"{$item[1]}\", {$client['clientid']})");
			echo " ";
			echoButton('', 'Cell', "useNumber(\"cellphone\", \"{$item[1]}\", {$client['clientid']})");
			echo " ";
			echoButton('', 'Work', "useNumber(\"workphone\", \"{$item[1]}\", {$client['clientid']})");
			echo " ";
			echoButton('', 'Alt', "useNumber(\"cellphone2\", \"{$item[1]}\", {$client['clientid']})");
		}
		else if(FALSE && strpos($item[2], 'EMAIL')) {
			echoButton('', 'Email', "useNumber(\"email\", \"{$item[1]}\", {$client['clientid']})");
			echo " ";
			echoButton('', 'Alt Email', "useNumber(\"email2\", \"{$item[1]}\", {$client['clientid']})");
		}
		else if(strpos($item[0], 'key') !== FALSE) {
			$keydesc = trim($item[1]);
			if(strpos($keydesc, '#') === 0) $keydesc = trim(substr($keydesc, 1));
			if(is_numeric($keydesc)) { echo "";} else echo "[NO KEY ID] ";
				/*$keyid = fetchRow0Col0("SELECT keyid FROM tblkey WHERE clientptr = {$client['clientid']}");
				//echo "KEYID: [$keyid] ";
				if($keyid) updateTable('tblkey', array('description'=>$keydesc), "keyid = $keyid", 1);
				else insertTable('tblkey', array('description'=>$keydesc, 'clientptr'=>$client['clientid']), 1);
			}*/
			
			echoButton('', 'Key Description', "setKeyDescription(\"{$item[1]}\", {$client['clientid']})");
		}
		echo "<br>";
	}
	echo "</td></tr>";
}
echo "</table>";
/*foreach($vcards as $vc) {
print_r($vc);exit;	
	$row = array('bad'=>'');
	$name = explode(';', $vc['N']);
	$row['lname'] = $name[0];
	$row['fname'] = $name[1];
	$row['uid'] = $vc['X-ABUID'];
	$row['notes'] = $vc['NOTE'];
	$row['birthday'] = $vc['BDAY'];
	foreach($vc as $key => $val) {
		if(strpos($key, '.ADR')) {
			if(strpos($key, 'HOME')) getAddressFields($val, $row, '');
			else if(strpos($key, 'WORK')) getAddressFields($val, $row, 'work');
			else getAddressFields($val, $row, '');
		}
	}
	$row['import'] = processItems($vc);
	foreach($vc as $key => $val) {
		if(strpos($key, 'item1.EMAIL') === 0) $row['email'] = $val;
		else if(strpos($key, 'item2.EMAIL') === 0) $row['email2'] = $val;
	}
	foreach($row as $key => $i) if(!isset($cols[$key])) $cols[$key]=count($cols);
	$rows[] = $row;
}



$cols = array_keys($cols);
header("Content-Type: text/csv");
header("Content-Disposition: inline; filename=vcards.csv ");
dumpCSVRow($cols);
foreach($rows as $row)
	dumpCSVRow($row, $cols);//echo print_r($row,1).'<br>';

*/


function processTel(&$vc, &$row) {
	$items = array();
	foreach($vc as $key => $val) {
		if(strpos($key, 'TEL') !== 0) continue;
//if(strpos($val, '3')) echo print_r($vc,1)."<p>";		
		$type = strpos($key, 'HOME') ? 'homephone' :(
			strpos($key, 'CELL') ? 'cellphone' :(
			strpos($key, 'WORK') ? 'workphone' : 'other'));
		if($val[0] == '*') $row[$type] = $val;
		else $items[] = "$type: $val";
	}
	return $items;
}

function processItems($vc) {
	$items = array();
	foreach($vc as $key => $val) {
		if(strpos($key, 'item') !== 0) continue;
		if(strpos($key, 'X-ABLabel')) continue;
		if(strpos($key, 'ADR')) continue;
		$prefix = substr($key, 0, strpos($key, '.')+1);
		$preferred = strpos($key, 'type=pref');
		$label = $vc[$prefix."X-ABLabel"].($preferred ? ' (preferred)' : '');
		$items[] = array(safeValue($label),safeValue($val), $key);
	}
	return $items;
}
?>
<script language='javascript' src='ajax_fns.js'></script>

<script language='javascript'>
function useNumber(field, phone, clientid) {
	ajaxGet('vcard-phone-transfer.php?field='+field+'&phone='+phone+'&id='+clientid, 'client'+clientid);
}
function setKeyDescription(val, clientid) {
	ajaxGet('vcard-phone-transfer.php?keydesc='+escape(val)+'&id='+clientid, 'client'+clientid);
}
</script>
