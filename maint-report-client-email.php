<? // maint-report-client-email.php
// for a given day show for each active business
// number of pet owners who logged in
// number of each kind of request
set_time_limit(10 * 60);

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";

$locked = locked('z-');


$date = $_REQUEST['date'];
$start = $_REQUEST['start'];
$end = $_REQUEST['end'];

$bizzes = fetchAssociationsKeyedBy("SELECT * FROM tblpetbiz WHERE test != 1 AND activebiz = 1 ORDER BY bizname", 'bizid', 1);

//print_r($users);
/*
foreach($bizzes as $biz) {
	echo "{$biz['bizname']} ({$biz['db']}): clients: {$bizCounts[$biz['bizid']]}<br>";
}
*/
$n=0;
foreach($bizzes as $bizid => $biz) {
	$n += 1; //if($n == 12) break;
	$bizClientEmails = emailsForDates($bizzes[$bizid], $start, $end); // client id => #emails
	if(!$bizClientEmails) continue;
	$mailBizzes[$biz['db']] = 
	$row = array('Biz'=>"{$biz['bizname']} ({$biz['db']})", 'Clients'=>count($bizClientEmails), 'Emails'=>array_sum($bizClientEmails));
	$totals['Clients'] += count($bizClientEmails);
	$totals['Emails'] += array_sum($bizClientEmails);
	/*foreach($bizRequests as $type=>$reqs) {
		$row[$type] = $reqs;
		$totals[$type] += $reqs;
	}*/
	$rows[] = $row;
}
$cols = array('Biz', 'Clients', 'Emails');

foreach($cols as $col) $colpairs[$col] = $col;

$printDate = $date ? shortDate(strtotime($date)) : shortDate(strtotime($start)).' through '.shortDate(strtotime($end));
echo "<b>Client Emails in $printDate</b><p>";

echo $totals['Clients'].' total clients '.$totals['Emails'].' total emails.  '.count($rows).' bizzes<hr>';
$totals['Biz'] = "<b>TOTALS</b>";
$rows[] = $colpairs;
$rows[] = $totals;
tableFrom($colpairs, $rows, $attributes='border=1');


function emailsForDates($biz, $date1, $date2) {
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);
	return
		fetchKeyValuePairs(
			"SELECT correspid, count(*) FROM tblmessage 
					WHERE datetime >= '$date1 00:00:00' AND datetime <= '$date2 23:59:59' 
						AND transcribed IS NULL AND correstable = 'tblclient'
						GROUP BY correspid", 1);
}
	

