<? // android-login-summary.php

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";

locked('z-');
$yearago = date('Y-m-d H:i:s', strtotime("-1 year"));
$agents = fetchCol0("
SELECT DISTINCT browser
FROM `tbllogin`
WHERE browser LIKE '%roid%'
	AND lastupdatedate > '$yearago'");

foreach($agents as $agent) {
	$open = strpos($agent, '(');
	$close = strpos($agent, ')');
	$application = trim(substr($agent, 0, $open));
	$browser = trim(substr($agent, $close+1));
	if(strpos($browser, ')')) 
		$browser = trim(substr($browser, strpos($browser, ')')+1));
	if(strpos($browser, 'Version/') === 0) 
		$browser = trim(substr($browser, strpos($browser, ' ')));
		
	$device = substr($agent, $open+1, $close-($open+1));
	
	/*
	(Linux; U; Android Eclair; md-us Build/pandigitalopc1/sourceidDL00000009)
	(Linux; Android 4.0.2; Galaxy Nexus Build/ICL53F)
	*/
	$parts = array_reverse(explode(';', $device));
	$os = trim(array_pop($parts));
	$version = trim(array_pop($parts));
	if($version == 'U') $version = trim(array_pop($parts));
	if($version == 'Linux' || $version == 'Android') {
		$x = $version;
		$version = $os;
		$os = $x;
	}
	$device = trim(array_pop($parts));
	
	if(strpos($device, 'en-') === 0 || strpos($device, 'hu-hu') === 0) $device = trim(array_pop($parts));
	if(strpos($device, 'Build')) $device = substr($device, 0, strpos($device, 'Build'));
	if(!$device) $device = '[UNKNOWN DEVICE]';
	
	
//echo print_r($parts,1).'<br>';	
	//$parts = array_combine(explode(',', 'os,code,version,lang,phone'), $parts);
	//$device = $parts[4];//array_pop($parts);
	//$devices[$device][$parts[0]][$parts[2]][$browser] = $agent;
	$devices[$device][$os][$version][$browser] = $agent;
	$osversions[] = $version;
	$browsers[] = $browser;
}
ksort($devices);
echo "<h2>Agents by device, OS, version, browser</h2>";
foreach($devices as $device => $oss) {
	echo "<p><b>$device</b><br>";
	foreach($oss as $os => $versions) {
		echo "...$os<br>";
		foreach($versions as $version => $browserversions) {
			echo "......$version<br>";
			foreach($browserversions as $browser => $agents) {
				echo ".........$browser<br>";
				foreach((array)$agents as $agent) {
					echo "............$agent<br>";
				}
			}
		}
	}
}
	
sort($osversions);
echo "<hr><h2>OS Versions (".count(array_unique($osversions)).")</h2>";
echo join('<br>', array_unique(array_unique($osversions)));

sort($browsers);
echo "<hr><h2>Browser Versions (".count(array_unique($browsers)).")</h2>";
echo join('<br>', array_unique($browsers));

foreach($devices as $device => $unused) {
	if(strpos($device, 'Build')) $device = substr($device, 0, strpos($device, 'Build'));
	$basicDevices[] = $device;
}
$basicDevices = array_unique($basicDevices);
sort($basicDevices);
echo "<hr><h2>Devices: ".count($basicDevices)."</h2>";
echo join('<br>', $basicDevices);
