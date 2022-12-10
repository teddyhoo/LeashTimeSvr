<? // slow-query.php

$ignore = "SQL_NO_CACHE
TRIGGER
COALESCE";
$ignore = explode("\n", $ignore);

if($_GET['d']) {
	readfile($_GET['d']);
	exit;
}
echo "Use <b>Save Link As...</b> for [download]<p>";
echo "Add <b>&amp;usedb=somedb</b> to focus on a single database (e.g., >&amp;usedb=themonsterminders)<p>";
$options = glob("/var/data/slo*");
foreach($options as $f) {
	if(strpos($f, '.gz')) continue;
	$downloadLink = "<a target='_blank' href='slow-query.php?d=".urlencode($f)."'>[download]</a>";
	echo "Analyze: <a href='slow-query.php?f=".urlencode($f)."'>".basename($f)."</a> - $downloadLink<br>";
}

if(!$_GET['f']) exit;
else $str = fopen($file = $_GET['f'], "r");
$usedb = $_GET['usedb'];
//$file = "slow-log-20150526"; // slow-log-20150518 slow-log2 slow-log3 slow-log4 slow-log-20150525
//$str = fopen($file = "/var/data/$file", "r");
if(!$str) exit;
//# Query_time: 4.806973  Lock_time: 0.000052 Rows_sent: 6  Rows_examined: 62978
$patterns = explode("\n",
"SELECT * FROM tblmessage WHERE ((correspid =
SELECT tblmessage.* FROM tblmessage WHERE 
SELECT itemptr FROM tblpayable WHERE itemptr IN");
while(!feof($str)) {
	$line = trim(fgets($str));
	if(strpos($line, "# Time:") === 0) {
		if(!$firstTime) $firstTime = $line;
		$lastTime = $line;
	}
	if(strpos($line, "Query_time")) {
		$parts = explode(' ', $line);
		$qtime = $parts[2];
		//echo "BANG!".print_r($parts, 1)."<br>";
	}
	else if($queryLine) {
		//if($usedb && $usedb != $dbinuse) {echo "$usedb ==> $dbinuse<br>";continue;}
		$queryLine =  false;
		$ignoreIt = false;
		foreach($ignore as $pat) if(strpos($line, trim($pat)) !== FALSE) $ignoreIt = true;
		if($ignoreIt) {/*echo "bang! $pat"; exit;*/ continue;}
		if(!$usedb || $usedb == $dbinuse) {
			$queries[$line]['count'] += 1;
			$queries[$line]['runtime'] += $qtime;
			$totalQueries += 1;
			$allQueriesTime += $qtime;
			foreach($patterns as $pattern) {
				$pattern = trim($pattern);
				if(strpos($line, $pattern) === 0) {
					$qcount[$pattern] +=  1;
					$qtotaltime[$pattern] += $qtime;
				}
			}
		}
	}
	else if(strpos($line, 'SET timestamp=') === 0) {
		$queryLine =  true;
	}
	else if(strpos($line, "use ") === 0) {
		$dbinuse = substr($line, strlen("use "), strlen($line)-1-strlen("use "));
	}
}
echo "<p>$file [$firstTime] ==> [$lastTime]<p>";

echo "<p>Ignoring: [".join('] [', $ignore).']</p>';

foreach((array)$qcount as $pattern => $patcount) if($qcount[$pattern]) {
	$avgqtime = $qtotaltime[$pattern] / $patcount;
	echo "pattern [<font color=green>$pattern</font>]: $patcount (total time: {$qtotaltime[$pattern]} avg: $avgqtime) <hr>";
}

echo "Total slow queries: $totalQueries total time: $allQueriesTime<p>";
asort($queries);
$queries = array_reverse($queries);
foreach($queries as $q => $n) echo "{$n['count']} (avg runtime: ".($n['runtime'] / $n['count'])."seconds) $q.<br>";
exit;
