<? // provider-day-detail.php

// provide a popup or lightbox of data about a sitter's day schedule and relationships with clients

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "provider-fns.php";
require_once "prov-schedule-fns.php";
require_once "client-fns.php";
require_once "pet-fns.php";
require_once "preference-fns.php";
require_once "google-map-utils.php";

// Determine access privs
$locked = locked('d-');

$max_rows = 100;
$provid = $_GET['prov'];
$date = $_GET['date'] ? $_GET['date'] : date('Y-m-d');
$date =  date('Y-m-d', strtotime($date));

$events = fetchAssociations(
	"SELECT starttime, timeofday, label, note, CONCAT_WS(' ', fname, lname) as client, CONCAT_WS(', ', street1, city ) as address
		FROM tblappointment
			LEFT JOIN tblclient ON clientid = clientptr
			LEFT JOIN tblservicetype ON servicetypeid = servicecode
		WHERE date = '$date' AND providerptr = $provid AND canceled IS NULL
		ORDER BY starttime", 1);

foreach($events as $i => $event) {
	if($event['note'])
		$events[$i]['label'] = "<span title=\"".safeValue($event['note'])."\">{$event['label']}</span>";
	$events[$i]['address'] = "{$event['client']}<br>{$event['address']}";
}
		
foreach(getProviderTimeOff($provid, $showpasttimeoff=true, $where="date ='$date'") as $to) {
	if($to['timeofday'])
		$to['starttime'] = date("H:i", strtotime(substr($to['timeofday'], 0, strpos($to['timeofday'], '-'))));
	else $to['timeofday'] = "ALL DAY";
	$to['address'] = 'TIME OFF';
	$to['label'] = $to['note'];
	$events[] = $to;
}
	
function cmpstarttime($a, $b) { return strcmp($a['starttime'], $b['starttime']); }

usort($events, 'cmpstarttime');
//print_r($events);

$todaysClients = array_unique(fetchCol0("SELECT clientptr FROM tblappointment WHERE date = '$date' AND canceled IS NULL", 1));
$bannedClients = doNotServeClientIds($provid);
$bannedClientIDs = $bannedClients;
if($bannedClients) {
	$bannedClients = fetchCol0(
	"SELECT CONCAT_WS(' ', fname, lname) 
		FROM tblclient 
		WHERE clientid IN (".join(',', $bannedClients).")
			AND clientid IN (".join(',', $todaysClients).")
		ORDER BY lname, fname");
	$bannedExclusionClause = "AND clientid NOT IN (".join(',', $bannedClientIDs).")";
}
		
$preferredClients = getPreferredClientIds($provid, $activeOnly=false);
if($preferredClients) $preferredClients = fetchCol0(
	"SELECT CONCAT_WS(' ', fname, lname) 
		FROM tblclient 
		WHERE clientid IN (".join(',', $preferredClients).")
			AND clientid IN (".join(',', $todaysClients).")
			$bannedExclusionClause
		ORDER BY lname, fname");
		
$sitterName = fetchRow0Col0(
	"SELECT CONCAT_WS(' ', fname, lname, 
											IF(nickname IS NOT NULL, CONCAT(' (', nickname, ')'), '')) 
	 FROM tblprovider WHERE providerid = $provid LIMIT 1", 1);
$shortDate = shortDate(strtotime($date));
$longDate = longerDayAndDate(strtotime($date));

require "frame-bannerless.php";
echo "<h3>Sitter Snapshot: $sitterName</h3>". $longDate."<p>";
		
if($events) {
	$columns = explodePairsLine('timeofday|Time||address|Location||label|Detail');
	tableFrom($columns, $data=$events, $attributes='border=1', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);
}
else echo "<i>No visits or time off scheduled.</i>";
		
if($preferredClients || $bannedClients) {
	$cols = 0;
	$cols += $preferredClients ? 1 : 0;
	$cols += $bannedClients ? 1 : 0;
	echo "\n<p><table border=0>\n<tr><th colspan=$cols align=center>For clients with visits on $longDate</th>\n</tr>\n<tr>";
	if($preferredClients) echo "\n<th><span style='font-weight:bold;color:darkgreen;'>Sitter Preferred by</span></th>";
	if($bannedClients) echo "\n<th><span style='font-weight:bold;color:red;'>Do NOT assign to</th>";
	echo "\n</tr>\n<tr>\n";
	
	if($preferredClients) echo "\n<td valign='TOP'><ul><li>".join('<li>', $preferredClients)."</ul>\n</td>";
	if($bannedClients) echo "\n<td valign='TOP'><ul><li>".join('<li>', $bannedClients)."</ul>\n</td>";
	echo "</tr></table>";
}