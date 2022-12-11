<? // zipcity.php
// Go through every database finding zips/city names
// build array zip => (cityname1, cityname2, ...)
// for each zip, show ZIP OFFICIALCITYNAME, cityname1, cityname2, ...
// allow an alternative to be chosen for each zip
// allow an alternative to be chosen for each city

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";
require_once "zip-lookup.php";

// exit;



$locked = locked('z-');

$citymodcount = fetchRow0Col0("SELECT count(*) FROM zipcodes2_citymods");
if($_GET['applymods']) {
	set_time_limit(5 * 60);
	echo "Updating zipcodes2...<br>";
	foreach(fetchKeyValuePairs("SELECT oldcity, newcity FROM zipcodes2_citymods") as $old => $new) {
		updateTable('zipcodes2', array('city'=>$new), "city COLLATE latin1_general_cs LIKE '$old'", 1);
		$mods = mysqli_affected_rows();
		$totalMods += $mods;
		echo "$old ==> $new ($mods rows)<br>";
	}
	echo "<p>$totalMods rows updated.<p><a href=zipcity.php>Back to ZIP City</a>";
	exit;
}

if($_POST) {
	$pairs = $_POST;
	//foreach(autoFixes('mc%') as $k => $v) $pairs[$k] = $v;
	//foreach(autoFixes('o %') as $k => $v) $pairs[$k] = $v;
	ksort($pairs);
	foreach($pairs as $k => $v) {
		$k = str_replace('_', ' ', $k);
		if($v && $v != -1) {
			replaceTable('zipcodes2_citymods', array('oldcity'=>$k, 'newcity'=>$v), 1);
			//echo "UPDATE zipcodes2 SET city = '$v' WHERE city = '".addslashes($k)."';<br>";
			//$substitutions[$k] = $v;
		}
		else if($v == -1) $marked[] = $k;
		if($v == -1) deleteTable('zipcodes2_citymods', "oldcity='".addslashes($k)."'", 1);
	}
	if($marked) echo "<p>MARKED: ".join(', ', $marked);
	if(FALSE && $substitutions) {
		echo "<p>SUBSTITUTIONS: array(";
		foreach($substitutions as $k => $v)
			echo "'".addslashes($k)."'=>'".addslashes($v)."', ";
		echo ")";
	}
	echo "<hr>";
}

$databases = fetchCol0("SHOW DATABASES");
$bizzes = fetchAssociationsKeyedBy("SELECT * FROM tblpetbiz WHERE activebiz=1 AND country = 'US'", 'db');
$allBizzesLeashTimeFirst[] = $bizzes['leashtimecustomers'];
unset($bizzes['leashtimecustomers']);
foreach($bizzes as $biz) $allBizzesLeashTimeFirst[] = $biz;


foreach($allBizzesLeashTimeFirst as $biz) {
	if(!in_array($biz['db'], $databases)) {
		echo "DB: {$biz['db']} not found.\n";
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
	
	// #########################################################################################################
	foreach(array('tblclient', 'tblprovider', 'tblclientrequest', 'tblclinic', 'tblvet') as $table)
		foreach(fetchKeyValuePairs("SELECT zip, city FROM $table WHERE zip IS NOT NULL AND zip != ''") as $zip => $city)
			if(!in_array(trim($city), (array)$allCities[$zip])) $allCities[$zip][] = trim($city);
}

foreach($allCities as $zip => $cities) {
	foreach($cities as $city)
		if(!in_array($city, (array)$flatlist)) $flatlist[] = $city;
	}


$official = fetchCities(array_keys($allCities));

foreach($official as $cityZip) {
	$cities = $allCities[$cityZip['zip']];
	foreach((array)$cities as $i => $city)
		if($city !==  $cityZip['city']) 
			$caps[$cityZip['city']][] = $city;
}
ksort($caps);

echo count($caps).' unique city names.<p>';
if($citymodcount)
	echo "$citymodcount modifications on deck. <a href='zipcity.php?applymods=1'>Apply Mods</a><p>";
echo "<style>.gray {background: lightgrey;}</style>";
echo "<div id='marked'></div><p>";
echo "<form name=updater method=POST><table border=1 bordercolor=black>";
echo "\n<tr><td><input type=submit value=Submit>";
echo "<td><a href='javascript:selectAll(true)'>Select Best Guesses</a>
			<td><a href='javascript:selectAll(false)'>Select None</a>";
foreach($caps as $cap => $cities) {
	$class = strtoupper($cap) != $cap ? 'class="gray"' : '';
	$bg = bestGuess($cap);
	$bgchecked = $class ? 'bestguess=1' : 'checked bestguess=1';
	echo "\n<tr $class><td>$cap<td style='color:blue'>".radio($bg, $cap, $bgchecked).'<td>';
	$cities = array_unique($cities);
	$finalcities = array();
	foreach($cities as $i =>$city) 
		if($city && $city != $bg) $finalcities[$i] = radio($city, $cap, $checked=null);
	$nonechecked = $class ? 'checked' : null;
	echo join(' ', $finalcities).radio(null, $cap, $nonechecked).radio(null, $cap, false, $cap);
}
echo "\n</table></form>";

function autoFixes($pat) {
	$sql = "SELECT DISTINCT city FROM zipcodes2 WHERE city LIKE '$pat' ORDER BY city";
	foreach(fetchCol0($sql) as $name) {
		if(isset($_POST[str_replace(' ', '_', $name)])) continue;
		$fixes[$name] = bestGuess($name);
		$name = $fixes[$name] ? $name : "<font color=red>$name</font>";
		echo "$name: {$fixes[$name]}<br>";
	}
	return (array)$fixes;
}

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
	
function radio($city, $official, $checked=null, $mark=null) {
	$label = $city ? $city : ($mark ? 'mark' : '<i>none</i>');
	$value = $city;
	if($mark) {
		$mark = "mark=\"$mark\"";
		$value = -1;
	}
	$id = "{$official}_$value";
	return "\n <input type=radio name=\"$official\" id=\"$id\" value=\"$value\" $checked $mark onclick='mark(\"$city\")'>
     <label for=\"$id\">$label</label>";
}

?>
<script language=javascript>
function selectAll(on) {
	var rads =  document.getElementsByTagName('input');
	for(var i = 0; i < rads.length; i++) {
		if(rads[i].getAttribute('bestguess') && on) rads[i].checked = true;
		else if(rads[i].value == '' && !on) rads[i].checked = true;
		else rads[i].checked = false;
	}
}	

function mark(city) {
	var s = [];
	var rads =  document.getElementsByTagName('input');
	for(var i = 0; i < rads.length; i++) {
		//if(!confirm(rads[i].getAttribute('mark'))) return;
		if(rads[i].getAttribute('mark') && rads[i].checked)
			s[s.length] = rads[i].getAttribute('mark');
	}
	document.getElementById('marked').innerHTML = (s.length ? "Marked: " : '')+s.join(', ');
}
</script>