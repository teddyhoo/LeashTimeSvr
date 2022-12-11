<? // zipcityedit.php
// Go through every database finding zips/city names
// build array zip => (cityname1, cityname2, ...)
// for each zip, show ZIP OFFICIALCITYNAME, cityname1, cityname2, ...
// allow an alternative to be chosen for each zip
// allow an alternative to be chosen for each city

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";
require_once "zip-lookup.php";
$locked = locked('z-');

function pickLink($nm) { return fauxLink($nm, "document.getElementById(\"pat\").value=\"$nm\";suggest();", 1, 1); }
function bestGuess($city) {
	foreach(explode(' ', $city) as $part) {
		$parts[] = $part[0].strtolower(substr($part, 1));
	}
	if(strpos($city, 'MC') === 0) {
		if($parts[0] == 'Mc') {
			$prefix = "Mc{$parts[1]}";
			unset($parts[0]);
			unset($parts[1]);
		}
		else {
			$prefix = 'Mc'.$city[2].substr($parts[0], 3);
			unset($parts[0]);
		}			
		return $prefix.($parts ? ' '.join(' ', $parts) : '');
	}
	else if(count($parts) >= 2 && $parts[0] == 'O') {
		$prefix = "O'{$parts[1]}";
		unset($parts[0]);
		unset($parts[1]);
		return $prefix.($parts ? ' '.join(' ', $parts) : '');
	}
	return join(' ', $parts);
}
	
$radius = 50;

if($_GET['mentionedonly'] || $_POST['allcities']) {
	$databases = fetchCol0("SHOW DATABASES");
	$bizzes = fetchAssociationsKeyedBy("SELECT * FROM tblpetbiz WHERE activebiz=1", 'db');
	$allBizzesLeashTimeFirst[] = $bizzes['leashtimecustomers'];
	unset($bizzes['leashtimecustomers']);
	foreach($bizzes as $biz) $allBizzesLeashTimeFirst[] = $biz;
	foreach($allBizzesLeashTimeFirst as $bizCount => $biz) {
		if($bizCount == count($allBizzesLeashTimeFirst)-1) $lastBiz = true;
		if(!in_array($biz['db'], $databases)) {
			echo "<br><font color=gray>DB: {$biz['db']} not found.<br></font>";
			continue;
		}
		$dbhost = $biz['dbhost'];
		$dbuser = $biz['dbuser'];
		$dbpass = $biz['dbpass'];
		$db = $biz['db'];
		$bizptr = $biz['bizid'];
		$lnk = mysqli_connect($dbhost, $dbuser, $dbpass);
		if ($lnk < 1) {
			echo "Not able to connect: invalid database username and/or password.\n";
		}
		$lnk1 = mysqli_select_db($db);
		if(mysqli_error()) echo mysqli_error();
		$tables = fetchCol0("SHOW TABLES");
		$bizName = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizName' LIMIT 1");
		if(!in_array('tblcreditcard', $tables)) continue;
		$sql = "SELECT city, state, zip 
						FROM #TABLE# 
						WHERE zip IS NOT NULL AND city IS NOT NULL
							AND zip <> '' AND city <> ''";
		foreach(fetchAssociations(str_replace('#TABLE#', 'tblclient', $sql)) as $place)
			$places["{$place['city']}, {$place['state']}"][] = $place['zip'];
		foreach(fetchAssociations(str_replace('#TABLE#', 'tblprovider', $sql)) as $place)
			$places["{$place['city']}, {$place['state']}"][] = $place['zip'];
		include "common/init_db_common.php";
	}
	foreach($places as $loc => $zips) $places[$loc] = array_unique($zips);
}

if($pat = $_GET['proximity']) {
	if($places) foreach(array_keys($places) as $place) 
		$mentioned[substr(strtoupper($place), 0, strpos($place, ','))] = 1;
	$match = fetchFirstAssoc("SELECT zipcode, lat, lon, city FROM zipcodes2 WHERE zipcode LIKE '$pat'");
	if(!$match) echo 'NONE';
	else {
		$rows = zipFromCoords($match['lat'], $match['lon'], $multiple=null, $radius);
		foreach($rows as $row) $zips[] = $row['zipcode'];
		$cities = fetchCol0("SELECT distinct city FROM zipcodes2 WHERE zipcode IN(".join(',', $zips).") ORDER BY city");
		echo "<table>";
		foreach($cities as $i => $city) {
			$n = $i+1;
			$allcaps = strtoupper($city) == $city ? "allcaps=1" : '';
			if($_GET['allcapsonly'] && !$allcaps) continue;
			if($_GET['mentionedonly'] && !$mentioned[strtoupper($city)]) continue;
			echo "<tr><td $cityStyle><label for='cb_$n'>$city</label>
						<td><input name='old_$n' type=hidden value='".safeValue($city)."'> 
								<input $allcaps id='cb_$n' name='cb_$n' type=checkbox> <input name='city_$n' value='".safeValue(bestGuess($city))."'>";
		}
		echo "</table>";
	}
	exit;
}
foreach($_POST as $k => $v) 
	if(strpos($k, 'city_') === 0) 
		$cityPost[] = substr($k, strlen('city_'));
if($cityPost) {
	foreach($cityPost as $i)
		if(($new = $_POST["city_$i"]) && $_POST["cb_$i"]) {
//print_r($_POST);
			$old = $_POST["old_$i"];
			if($old == $new) {
				echo "<font color=gray>$old ignored.</font><p>";
				continue;
			}
			replaceTable('zipcodes2_citymods', array('oldcity'=>$old, 'newcity'=>$new), 1);
			updateTable('zipcodes2', array('city'=>$new), "city COLLATE latin1_general_cs LIKE '$old'", 1);
			$mods = mysqli_affected_rows();
			echo "<font color=darkgreen>$old changed to $new in $mods rows.</font><p>";
		}
}


if($pat = $_GET['search']) {
	$match = fetchCol0("SELECT distinct city FROM zipcodes2 WHERE city LIKE '$pat%'");
	if(!$match) echo 'NONE';
	else echo "MATCHES: ".join(', ', array_map('pickLink', $match));
	exit;
}

if($pat = $_GET['suggest']) {
	echo bestGuess($_GET['suggest']);
	exit;
}

echo "<h2>Upgrade a city name in the ZIP Code DB</h2>";

if(($new = $_POST['newname']) && ($old = $_POST['pat'])) {
	replaceTable('zipcodes2_citymods', array('oldcity'=>$old, 'newcity'=>$new), 1);
	updateTable('zipcodes2', array('city'=>$new), "city COLLATE latin1_general_cs LIKE '$old'", 1);
	$mods = mysqli_affected_rows();
	echo "<font color=darkgreen>$old changed to $new in $mods rows.</font><p>";
}

?>
<style>.fauxlink {color:blue;}</style>
<form method=POST>
City: <input id=pat name=pat onkeyup='search(this.value)'> ==> <input id=newname name=newname>
<input type=submit value='Submit'>
</form>
<div id='results'></div>
<hr>

<h2>Proximity Search (<?= $radius ?> miles)</h2>
<form method=POST>
ZIP: <input id=zip name=zip onkeyup='proximity(this.value)' value= <?= $_GET['forzip'] ?>>
<input type=submit value='Submit'>
<? labeledCheckbox('ALL CAPS only', 'allcapsonly', $value=$_REQUEST['allcapsonly'], null, null, 'proximity(-1)', true); ?>
<? labeledCheckbox('Mentioned only', 'mentionedonly', $value=$_REQUEST['mentionedonly'], null, null, 'proximity(-1)', true); ?>
- <a href='javascript:selectAll(true)'>Select All</a>
- <a href='javascript:selectAll("allcaps")'>Select ALL CAPS</a>
- <a href='javascript:selectAll(false)'>Clear All</a>
<div id='nearbycities'></div>
</form>

<hr>
<form method=POST>
<input type=button onclick='showAllCities()' value='Show All Cities'>
</form>
<? 
if($places) {
	ksort($places);
	foreach($places as $loc => $zips) {
		echo $loc.' - ';
		foreach($zips as $i => $zip) 
			echo ($i ? ', ' : '').fauxLink($zip, "document.getElementById(\"zip\").value=\"$zip\";proximity(\"$zip\");", 1);
		echo "<br>";
	}
}
?>



<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
function showAllCities() {
	var zip = "zip="+document.getElementById("zip").value;
	var allcapsonly = document.getElementById('allcapsonly').checked ? '&allcapsonly=1' : '';
 	var mentionedonly = document.getElementById('mentionedonly').checked ? '&mentionedonly=1' : '';
 	document.location.href='zipcityedit.php?'+zip+allcapsonly+mentionedonly;
}

function proximity(zip) {
	if(!document.getElementById('zip').value) return;
	if(zip == -1) zip = document.getElementById('zip').value;
	var allcapsonly = document.getElementById('allcapsonly').checked ? '&allcapsonly=1' : '';
 	var mentionedonly = document.getElementById('mentionedonly').checked ? '&mentionedonly=1' : '';
 ajaxGetAndCallWith('zipcityedit.php?proximity='+zip+allcapsonly+mentionedonly, afterProximity, 0);
}

function afterProximity(arg, result) {
	if(result == 'NONE') document.getElementById.innerHTML = '';
	else document.getElementById('nearbycities').innerHTML = result;
}

function search(pat) {
  ajaxGetAndCallWith('zipcityedit.php?search='+pat, afterSearch, 0);
}

function afterSearch(arg, result) {
	if(result == 'NONE') document.getElementById.innerHTML = '';
	else document.getElementById('results').innerHTML = result;
}

function suggest() {
  ajaxGetAndCallWith('zipcityedit.php?suggest='+document.getElementById("pat").value, aftersuggest);
}

function aftersuggest(arg, result) {
	document.getElementById('newname').value = result;
}

function selectAll(on) {
	var rads =  document.getElementsByTagName('input');
	for(var i = 0; i < rads.length; i++) {
		if(rads[i].getAttribute('type') == 'checkbox') {
			if(on == 'allcaps' && rads[i].getAttribute('allcaps') != 1) continue;
			rads[i].checked = (on ? true : false);
		}
	}
}	

<? if($_GET['forzip']) echo "proximity({$_GET['forzip']});" ?>
</script>
