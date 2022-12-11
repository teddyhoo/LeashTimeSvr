<? // import-vets-html.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

// http://iwmr.info/petbizdev/import-vets-html.php?file=petaholics/vetlist.htm


extract($_GET);

$file = "/var/data/clientimports/$file";
$file = file($file);

$goals = array('start'=>'<p align="center">Vet List</p>');

foreach($goals as $goalKey => $goal) {//$file as $linenum => $line
	if($line = findGoal($goal, $file)) {
		next($goals);
		next($file);
	}
}

if(!$line) {
	echo "Did not find goal: $goalKey.";
	exit;
}

$clinicMap = array();

while($vet = readVet($file)) {
	$created = createVet($vet);
	if(!is_array($created)) echo "A vet clinic named [{$vet['vetname']}] already exists.<br>";
	else echo 'Added '.print_r($vet, 1).'<br>';
}
if($clinicMap) {
	$tableSql = "CREATE TABLE IF NOT EXISTS `tempClinicMap` (
  `externalptr` int(11) NOT NULL,
  `clinicptr` int(11) NOT NULL,
  PRIMARY KEY  (`externalptr`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";

	doQuery($tableSql);
	doQuery('DELETE FROM tempClinicMap');
	echo "External ID,ClinicID<br>";
	foreach($clinicMap as $mapID => $clinicid) {
		insertTable('tempClinicMap', array('externalptr'=>$mapID,'clinicptr'=>$clinicid), 1);
		echo "$mapID,$clinicid<br>";
	}
}
	
	
function createVet($vet) {
	global $clinicMap;
	$preexisting = fetchFirstAssoc("SELECT * FROM tblclinic WHERE clinicname = '{$vet['vetname']}' LIMIT 1");
	if($preexisting) return $preexisting['clinicid'];
	if($vet['contact']) $notes = "Contact: {$vet['contact']}";
	if($vet['specialty']) $notes = $notes ? "$notes\nSpecialty: {$vet['specialty']}" : "Specialty: {$vet['specialty']}";
	if($vet['otherphone']) $notes = $notes ? "$notes\nOther phone: {$vet['otherphone']}" : "Other phone: {$vet['otherphone']}";
	$clinic = array('clinicname'=>$vet['vetname'], 'notes'=>$notes, 'officephone'=>$vet['phone']);
  $id = insertTable('tblclinic', $clinic, 1);
	if($id && $vet['vetid']) $clinicMap[$vet['vetid']] = $id;
  $clinic['clinicid'] = $id;  
  return $clinic;
}

function readVet(&$file) {
	$line = trim(current($file));
	while(!startsWithAnyOf($line, 
				array('<tr bgcolor=', '</tbody></table>')))
		$line = trim(next($file));
	if(startsWith($line, '</tbody></table>'))
		return null;

	$vet = array();
	$goals = array('ignore1'=>'<td', 'ignore2'=>'<td', 'ignore3'=>'<td', 'vetid'=>'<td', 'vetname'=>'<td','contact'=>'<td','phone'=>'<td','otherphone'=>'<td','specialty'=>'<td','active'=>'<td',);
	foreach($goals as $goalKey => $goal) {
		if($line = findAnyGoal(array($goal, '</tbody></table>'), $file)) {
//echo htmlentities($line);
			if(!startsWith($goalKey, 'ignore')) $vet[$goalKey] = stripLine($line);
			next($file);
		}
	}
	return $vet;
}
	
function findGoal($goal, &$file) {
	return findAnyGoal(array($goal), $file);
	/*while($line = current($file)) {
		$line = trim($line);
		if(!startsWith($line, $goal))
			next($file);
		else return $line;
		if(current($file) === FALSE) return null;
	}*/
}

function findAnyGoal($goals, &$file) {
	//echo "GOAL: ".htmlentities($goal)."<br>";
	while($line = current($file)) {
		$line = trim($line);
		if(!startsWithAnyOf($line, $goals))
			next($file);
		else return $line;
	}
}

function startsWith($line, $pattern) {
	//echo "[".htmlentities($pattern)."] ".htmlentities($line).": [".htmlentities(startsWithAnyOf($line, array($pattern)))."]<br>";
	return startsWithAnyOf($line, array($pattern));
}		
	
function startsWithAnyOf($line, $patterns) {
	foreach($patterns as $pattern)
		if(strpos($line, $pattern) === 0) return $pattern;
}		
	
function stripLine($line) {
	$line = strip_tags($line);
	$line = str_replace('&nbsp;', ' ', $line);
	return  trim($line);
}

function getParameter($parameter, $line) {
	if(!$pos = strpos($line, $parameter)) return null;
	$pos += strlen($parameter);
	return substr($line, $pos, strpos($line, '&', $pos) - $pos);
}
	
/*
0. Use Firefox.
1. Go to staff.
2. <ctrl>A
3. View Selection Source
4. Save Page As...

stripTags {
	$line = stripTags($line);
	$line = str_replace('&nbsp;', ' ', $line);
	$line = trim($line);
}


==== Providers ====
Ignore first <table, go to second <table
(No-op: Ignore one <tr's) (No-op: <tbody><tr)
Until </table
	find <tr style="background-color: - start provider
	1: <td - stripTags -> employeeId
	2: <td - stripTags -> "lname, fname (loginid)"
	3: <td - stripTags -> "nickname &nbsp;"
	4: <td - skip
	5: <td - skip
	6: <td - stripTags -> phone
	6: <td - stripTags -> portable



==== Staff Phone List ==
Go to second <table
Until </table
	find onmouseover="this.style - start provider
	1: <td - stripTags -> "&nbsp; lname, fname&nbsp;"
	2: <td - stripTags -> Home
	3: <td - stripTags -> Cell
	4: <td - stripTags -> Other
	5: <td - find "cstmr_id=" use customer id to identify provider
	6: <td - stripTags -> phone
	
	

<td nowrap="nowrap">&nbsp; unassigned, unassigned&nbsp;</td>
<td>&nbsp;404 633 2264&nbsp;</td>
<td>&nbsp;770-234-6905</td>
<td>&nbsp;</td>
<td class="navButts"><a href="v_eml_cstmr.cfm?cstmr_eml=operations@bluewave.bz&amp;cstmr_fn=unassigned&amp;cstmr_ln=%20unassigned&amp;cstmr_id=2&amp;ret=SR">eMail</a></td>

<td>operations@bluewave.bz</td>
	
*/