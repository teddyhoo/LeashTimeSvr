<? // maint-log-report.php 
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "js-gui-fns.php";

$locked = locked('z-');
$numLines = 20000;
$log = "/var/log/httpd/access_log";
if($_GET['tail']) {
	echo "<pre>";
	//system("whoami"); // apache
	system("tail --lines={$_GET['tail']} $log");
	exit;
}
?>
<script language='javascript' src='common.js'></script>
<style>
.fauxlink {
	text-decoration: underline; 
	cursor:pointer;
	color:blue;
}
</style>
<? // maint-log-poll.php
set_time_limit(60);
require_once "gui-fns.php";

/*
ob_start();
ob_implicit_flush(0);
system("tail --lines=$numLines $log", $status);
$lines = ob_get_contents();
ob_end_clean();

//ensureInstallationSettings();
//echo "<pre>".$lines;

analyzeLog($lines);
*/
system("tail --lines=$numLines $log > bizfiles/tmplog.txt", $status);
analyzeLog("bizfiles/tmplog.txt", 1);


include 'frame-maintenance.php';


echo "Analyzed ".number_format($lineCount)." lines.  First hit: $firstHit  ";
fauxLink('View Tail', 
"var numLines = $lineCount;".'var x;if(x=prompt("How Many lines?",'.$lineCount.')) $.fn.colorbox({href:"maint-log-report.php?tail="+x, width:"750", height:"470", scrolling: true, opacity: "0.3"});');
echo "<p>";

$limits = array(1,5,10,30,60,120,240,12*60,24*60);
$data = aggregatePeriods($limits);
aggregateLogins($limits);
ksort($data);
foreach($data as $limit => $agg) {
	reportAggregate($limit);
	echo "<hr>";
}




function reportAggregate($n) {
	$facts = aggregateLastNMinutes($n);
	if(!$facts) {
		echo "There has been no activity in the last $n minutes:<p>";
		return;
	}
	if($n % 60) echo "In the last $n minutes:<p>";
	else echo "In the last ".($n / 60)." hours:<p>";
	echo "there have been {$facts['hits']} hits<br>";
	echo "<p><u>APPS</u><br>";
	foreach($facts['apps'] as $app => $hits) echo "[$app]: $hits<br>";
	echo "<p><u>IPS</u><br>";
	foreach($facts['ips'] as $ip => $hits) {
		$userList = findUserList($ip, $n);
		echo "[".lookupLink($ip)."]: $hits $userList<br>";
	}
}

function findUserList($ip, $n) {
	global $loginsByPeriod;
	$ips = $loginsByPeriod[$n][$ip];
	if(!$ips) {
		$periods = array_keys($loginsByPeriod);
		sort($periods);
		foreach($periods as $period) {
			// ignore periods up to $n
			if($period < $n) continue;
			$periodIPs = $loginsByPeriod[$period];
			if(isset($periodIPs[$ip])) {
				$ips = array_merge((array)$ips, $periodIPs[$ip]);
				break;
			}
		}
	}
	return $ips ? "(".join(', ', array_unique($ips)).")" : '';
}

function lookupLink($ip) {
	return fauxLink($ip, "openConsoleWindow(\"lookup\", \"http://ws.arin.net/whois/?queryinput=$ip\", 700, 500)", 1);
}

function aggregateLogins($limits) {
	global $loginsByPeriod;
	$now = strtotime(date('Y-m-d H:i'));
	$earliest = date('Y-m-d H:i', strtotime("- ".max($limits)." minutes", $now));
	$logins = fetchAssociations("SELECT * FROM `tbllogin` WHERE LastUpdateDate >= '$earliest'");
			 
	foreach($limits as $limit) $starts[$limit] = date('Y-m-d H:i', strtotime("- $limit minutes", $now));
	foreach($logins as $login) {
		$minute = substr($login['LastUpdateDate'], 0, 16);
		$ip = $login['RemoteAddress'];
		foreach($starts as $limit => $start) {
			if(strcmp($start, $minute) <= 0) {
				$loginsByPeriod[$limit][$ip][] = $login['LoginID'];
			}
		}
	}
	/*foreach($loginsByPeriod as $p => $ips) {
		echo "<p>[$p]<br>";
		foreach($ips as $ip => $logins) echo "$ip: ".print_r($logins,1).'<br>';
	}*/
	return $loginsByPeriod;
}
	
function aggregatePeriods($limits) {
	global $analysis, $allIPs;
	$now = strtotime(date('Y-m-d H:i'));
	foreach($limits as $limit) $starts[$limit] = date('Y-m-d H:i', strtotime("- $limit minutes", $now));
	foreach($analysis as $minute => $a) {
//echo "START: $start		MINUTE: $minute<br>";
		foreach($starts as $limit => $start) {
			if(strcmp($start, $minute) <= 0) {
				foreach($a as $app => $ips) {
					foreach($ips as $ip => $count) {
						$facts[$limit]['ips'][$ip] += $count;
						$allIPs[$ip] = 1;
						$facts[$limit]['hits'] += $count;
						$facts[$limit]['apps'][$app] += $count;
					}
				}
			}
		}
	}
	return $facts;
}
	
function aggregateLastNMinutes($n) {
	global $analysis;
	$now = strtotime(date('Y-m-d H:i'));
	$start = date('Y-m-d H:i', strtotime("- $n minutes", $now));
	foreach($analysis as $minute => $a) {
//echo "START: $start		MINUTE: $minute<br>";
		if(strcmp($start, $minute) <= 0) {
			foreach($a as $app => $ips) {
				foreach($ips as $ip => $count) {
					$facts['ips'][$ip] += $count;
					$facts['hits'] += $count;
					$facts['apps'][$app] += $count;
				}
			}
		}
	}
	return $facts;
}
	
function analyzeLog($string, $isFile=false) {
	global $analysis, $firstHit, $lineCount;
	if($isFile) $stream = fopen($string,'r');
	else $stream = fopen('data://text/plain,' . $string,'r');
	if(!$stream) {echo "Could not open [$string]"; return;}
	$ignore = array('/favicon.ico');
	$ignoreExts = array('gif', 'jpg');
	while(!feof($stream)) {
		$line = readLine(fgets($stream));
		$lineCount++;
		if(!$firstHit) $firstHit = $line['time'];
		if(in_array($line['scriptname'], $ignore)) continue;
		else {
			$skip = false;
			foreach($ignoreExts as $ext) {
				if(strrpos($line['scriptname'], $ext) == strlen($line['scriptname']) - strlen($ext)) {
					$skip = true;
					continue;
				}
			}
			if($skip) continue;
		}
		$analysis[substr($line['time'], 0, 16)] // minute
								[$line['app']] // app
								[$line['ip']] += 1;
	}
}

function readLine($line) {
	global $installationSettings;
	$arr['ip'] = substr($line, 0, strpos($line, ' '));

	$s = strpos($line, "[")+1;
	$dateTime = substr($line, $s, strpos($line, 0, "]") - $s);
	$date = str_replace('/', ' ', substr($dateTime, 0, 11));
	$arr['time'] =  date('Y-m-d H:i:s', strtotime("$date ".substr($dateTime, 12, 8)));

	$s = strpos($line, '"');
	$actionParts = explode(' ', substr($line, $s+1, strpos($line, '"', $s+1)-($s+1)));
	$arr['action'] = $actionParts[0];
	$arr['scriptname'] = $actionParts[1];
	if(strpos($arr['scriptname'], '?')) $arr['scriptname'] = substr($arr['scriptname'], 0, strpos($arr['scriptname'], '?'));
	$arr['protocol'] = $actionParts[2];
	
	$arr['app'] = dirname($arr['scriptname']);
	if($arr['app'] == "/") $arr['app'] = 'PROD';
	else {
		$arr['app'] = explode('/', $arr['app']);
		$arr['app'] =  $arr['app'][1];
	}

	for($i=0; $i < 2; $i++) // find the third doublequote
		$s = strpos($line, '"', $s+1);
	$arr['referrer'] = substr($line, $s+1, strpos($line, '"', $s+1)-($s+1));
	return $arr;
}

