<? // maint-dbs.php
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";

$locked = locked('z-');

extract(extractVars('sort,droplogo,droplogotoken,bizptr,status,hidetest,hidefree,showlogos,surveysmtphosts', $_REQUEST));

if(!in_array('status', array_keys($_REQUEST))) {
	if(in_array('dashboardstatus', array_keys($_SESSION))) $status = $_SESSION['dashboardstatus'];
	else $status = 7;
}
$_SESSION['dashboardstatus'] = $status;

if($droplogo) {
	if($droplogotoken != $_SESSION['DROPLOGOTOKEN']) $error = 'Expired token';
	else {
		unlink("/var/www/prod/bizfiles/biz_$droplogo/logo.gif");
		unlink("/var/www/prod/bizfiles/biz_$droplogo/logo.jpg");
		unlink("/var/www/prod/bizfiles/biz_$droplogo/logo.png");
		$message = "Logo dropped for biz $droplogo";
	}
}
if($_FILES['logofile']) {
	$dir = "/var/www/prod/bizfiles/biz_$bizptr";
	if(!is_dir($dir)) mkdir($dir);
	$originalName = $_FILES['logofile']['name'];
	$extension = strtoupper(substr($originalName, strrpos($originalName, '.')+1));
	if(file_exists("$dir/logo.$extension")) unlink("$dir/logo.$extension");
	if(!move_uploaded_file($_FILES['logofile']['tmp_name'], strtolower("$dir/logo.$extension"))) {
		$error = "There was an error uploading the file, please try again!";
	}
}
$_SESSION['DROPLOGOTOKEN'] = time();

$businessesOnly = FALSE; in_array('b', getMaintRights());

if($surveysmtphosts) {
	$dbs = fetchCol0("SHOW DATABASES");
	$smtpTable = "<tr style='font-size:1.5em'><th>Business"
								."<th>SMTP Host"
								."<th>SMTP Port"
								."<th>Security"
								."<th>User Name Type";
	foreach(fetchAssociations("SELECT * FROM tblpetbiz") as $biz) {
		if(!in_array($biz['db'], $dbs)) continue;
		reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);
		if(mysqli_error()) continue;
		$smtpHost = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE property IN ('emailHost', 'smtpPort', 'smtpSecureConnection', 'emailUser')");
		if($smtpHost['emailHost']) {
			if($surveysmtphosts != 1 && $smtpHost['emailHost'] != $surveysmtphosts) continue;
			$hostInfo = "<u title='{$smtpHost['emailHost']}:{$smtpHost['smtpPort']}'>H</u>";
			$usernameType = strpos($smtpHost['emailUser'], '@') ? 'email address' : 'not email address';
			$background = $biz['activebiz'] ? "#FFFACD" : "#FFDAB9";
			$smtpTable .= "<tr style='background: $background'><td>{$biz['bizname']}"
										."<td>{$smtpHost['emailHost']}"
										."<td>{$smtpHost['smtpPort']}"
										."<td>{$smtpHost['smtpSecureConnection']}"
										."<td>$usernameType";
			$smtpHosts[$smtpHost['emailHost']][] = $biz['activebiz'] ? $biz['bizname'] : "<font color=red>{$biz['bizname']}</font>";
			$activeBizCounts[$smtpHost['emailHost']] += $biz['activebiz'] ? 1 : 0;
			$inactiveBizCounts[$smtpHost['emailHost']] += !$biz['activebiz'] ? 1: 0;
		}
		else {
			if($biz['activebiz']) {
				$leashTimeSMTPUsers[] = $biz['bizname'];
				$activeLeashTimeSMTPUsers += 1;
			}
			else {
				$leashTimeSMTPUsers[] = "<font color=red>{$biz['bizname']}</font>";
				$inactiveLeashTimeSMTPUsers += 1;
			}
				
		}
	}
	echo "<table border=1 bordercolor=black>";
	echo "<tr><td colspan=2><b>Hosts (".count($smtpHosts).")</b></td></tr>";
	$leashTimeSMTPUsers = "(".count($leashTimeSMTPUsers).", active=$activeLeashTimeSMTPUsers, inactive=$inactiveLeashTimeSMTPUsers) ".join(', ', $leashTimeSMTPUsers);
	ksort($smtpHosts);
	foreach($smtpHosts as $host => $bizzes) {
		$inactiveCount = $inactiveBizCounts[$host] ? "<font color=red>(inactive: {$inactiveBizCounts[$host]})</font>" : '';
		$activeCount = $inactiveBizCounts[$host] ? "(active: {$activeBizCounts[$host]})" : '';
		echo "<tr><td>$host</td><td colspan=4>("
				.count($bizzes).") $activeCount $inactiveCount ".join(', ', $bizzes)."</td></tr>";
	}
	if($surveysmtphosts == 1) echo "<tr><td valign=top bgcolor=lightblue>(shown) LeashTime default</td><td bgcolor=lightblue colspan=4>$leashTimeSMTPUsers</td></tr>";
	echo $smtpTable;
	echo "</table>";
	exit;
}

$sorts = $sort ? explode('_', $sort) : '';

$orderBy = !$sorts ? "ORDER BY bizname ASC" : "ORDER BY ".str_replace('_', ' ', $sort);
$filter = array($status >= 1 ? "activebiz = 1" : (
					$status == -1 ? "activebiz = 0" : "1=1"));
if($hidetest) $filter[] = 'test = 0';
//if($hidefree) $filter[] = "freeuntil != '1970-01-01'";
if($status == 6) $bizzes = array();
else if($status == 7) {  // see lt-staff-login.php
	$recent = fetchRow0Col0("SELECT value from tbluserpref WHERE property = 'recentdbs' AND userptr = {$_SESSION['auth_user_id']}");
	$recent = $recent ? $recent : -999;
	if($recent) $bizzes = fetchAssociationsKeyedBy("SELECT * FROM tblpetbiz WHERE bizid IN ($recent) $orderBy", 'db');
}
else $bizzes = fetchAssociationsKeyedBy("SELECT * FROM tblpetbiz WHERE ".join(' AND ', $filter)." $orderBy", 'db');

// find free bizzes and paying bizzes
$ltbiz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = 'leashtimecustomers' LIMIT 1");;
reconnectPetBizDB($ltbiz['db'], $ltbiz['dbhost'], $ltbiz['dbuser'], $ltbiz['dbpass'], 1);
$trialcustomers = fetchCol0(
	"SELECT garagegatecode 
		FROM tblclientpref
		LEFT JOIN tblclient ON clientid = clientptr
		WHERE property LIKE 'flag_%' AND value like '1|%' AND garagegatecode");
$deadprospects = fetchCol0(
	"SELECT garagegatecode 
		FROM tblclientpref
		LEFT JOIN tblclient ON clientid = clientptr
		WHERE property LIKE 'flag_%' AND value like '8|%' AND garagegatecode");
$formerclients = fetchCol0(
	"SELECT garagegatecode 
		FROM tblclientpref
		LEFT JOIN tblclient ON clientid = clientptr
		WHERE property LIKE 'flag_%' AND value like '21|%' AND garagegatecode");
$livecustomers = fetchCol0(
	"SELECT garagegatecode 
		FROM tblclientpref
		LEFT JOIN tblclient ON clientid = clientptr
		WHERE property LIKE 'flag_%' AND value like '2|%' AND garagegatecode");
$freecustomers = fetchCol0(
	"SELECT garagegatecode 
		FROM tblclientpref
		LEFT JOIN tblclient ON clientid = clientptr
		WHERE property LIKE 'flag_%' AND value like '4|%' AND garagegatecode");
$allrepresentedcustomers = fetchCol0(
	"SELECT garagegatecode 
		FROM tblclient
		WHERE garagegatecode");
//echo count($freecustomers);
		
include "common/init_db_common.php";
foreach($bizzes as $i => $biz) {
	if(!in_array($biz['bizid'], $allrepresentedcustomers)) $bizzes[$i]['free'] = 1;
	if(in_array($biz['bizid'], $freecustomers)) $bizzes[$i]['free'] = 1;
	if(in_array($biz['bizid'], $livecustomers)) $bizzes[$i]['live'] = 1;
	if(in_array($biz['bizid'], $deadprospects)) $bizzes[$i]['dead'] = 1;
	if(in_array($biz['bizid'], $formerclients)) $bizzes[$i]['former'] = 1;
	if((!$bizzes[$i]['free'] && !$bizzes[$i]['live'])
			|| in_array($biz['bizid'], $trialcustomers)
	  ) $bizzes[$i]['trial'] = 1;
}

$owners = array();
foreach(fetchAssociations("SELECT * FROM tbluser where rights like 'o-%' AND NOT ltstaffuserid") as $owner)
	$owners[$owner['bizptr']][] = $owner;

foreach(fetchAssociations("SELECT * FROM tbluser where rights like 'd-%'") as $owner)
	$owners[$owner['bizptr']][] = $owner;
	
	

function bizLink($name, $id) {
	global $businessesOnly;
	if($businessesOnly) return $name;
	return fauxLink($name, "document.location.href=\"maint-edit-biz.php?id=$id\"", 1);
}

function loginLink($id) {
	return "<img src='art/branch.gif' onclick='stafflogin($id)'> ";
}

function ownerLink($owner, $businessesOnly) {
	$name = htmlentities("{$owner['fname']} {$owner['lname']}");
	if($owner['rights'][0] == 'd') $name = "[D]$name";
	if(!$owner['active']) {
		$displayname = "<i>$name</i>";
		$inactive = "INACTIVE ";
	}
	else if($owner['isowner']) {
		$isowner = "OWNER. ";
		$displayname = "<b>$name</b>";
		
	}
	$email = htmlentities($owner['email']);
	if($businessesOnly) 
		return "<span title='{$isowner}{$inactive}Username: {$owner['loginid']} [$email]'>$displayname</span>";
	$displayname = $owner['active'] ? $owner['loginid'] : "<i>{$owner['loginid']}</i>";
	if($owner['isowner']) $displayname = "<b>{$owner['loginid']}</b>";
	return fauxLink($displayname, 
									"openConsoleWindow(\"logineditor\", \"maint-edit-user.php?userid={$owner['userid']}\", 600,400)", 
									1,
									"{$isowner}{$inactive}$name [{$owner['email']}]");
}

function getMaintRights() {
	$rights = $_SESSION['rights'];
	return explode(',', (strlen($rights) > 2 ? substr($rights, 2) : ''));
	
}

function dbLink($biz) {
	if(!mattOnlyTEST()) return $biz['db'];
	if(strpos($biz['db'], '<') !== FALSE) return $biz['db'];
	return "<a href='http://leashtime.com/eegah/index.php?db={$biz['db']}&lang=en-utf-8' target=MYSQLDB>{$biz['db']}</a>";
}

$today = date('Y-m-d');

$databases = fetchCol0("SHOW DATABASES");
$requiredTables = explode(',', 'tblpreference,tblqueuedemail');

foreach($bizzes as $biz) {
	if($businessesOnly && $biz['dbname'] == 'leashtimecustomers') continue;
	
	$row = $biz;
	$lockout = date('m/d/Y', strtotime($biz['lockout']));
	if(!$biz['lockout']) {$lockoutColor = 'white'; $lockoutTitle = $businessesOnly ? "Not locked out" : "Click here to lock out"; $row['lockoutOrder'] = 0;}
	else if(strcmp($today, $biz['lockout']) == -1) {$lockoutColor = 'yellow'; $lockoutTitle = "Lock out set for: $lockout"; $row['lockoutOrder'] = 2;}
	else if(strtotime($biz['lockout']) < strtotime("-30 days")) {
		$lockoutColor = 'darkred'; $lockoutTitle = "OLD: Locked out since: $lockout"; $row['lockoutOrder'] = 3;}
	else {$lockoutColor = 'red'; $lockoutTitle = "Locked out since: $lockout"; $row['lockoutOrder'] = 1;}
	$row['lockout'] = "<img src='art/lockout-$lockoutColor.gif' title='$lockoutTitle'"
										.($businessesOnly ? '' : "onclick='lockOut({$biz['bizid']})'")
										.">";
	if($hidefree && $biz['free']) continue;
	if($status == 2 && ($biz['free'] || !$biz['live'] || $biz['trial'])) continue;
	if($status == 3 && ($biz['free'] || $biz['live'] || !$biz['trial'] || $biz['dead'] || $biz['former'])) continue;
	if($status == 4 && !$biz['lockout']) continue;
	if($status == 5 && ($biz['free'] || (!$biz['live'] && !$biz['trial']) || $biz['dead'] || $biz['former'])) continue;
	if(in_array($biz['db'], $databases)) {
		if($biz['activebiz'] && !$biz['test'] && !in_array($biz['bizid'], $allrepresentedcustomers)) {
			$noncustomers .= "<tr><td><a href='maint-edit-biz.php?id={$biz['bizid']}'>{$biz['bizname']}</a><td>{$biz['activated']}</tr>";
			$noncustomerCount++;
		}
		reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
		$tables = fetchCOl0("SHOW TABLES");
		$stop = false;
		foreach($requiredTables as $tbl) {
			if(!in_array($tbl, $tables)) {
				$stop = true;
				break;
			}
		}
		if($stop) {
			$row['owners'] = 'BAD/INCOMPLETE DB';
			if($status == 8) continue;
			$rows[] = $row;
			$rowClass =	'deadprospect';
			$rowClass .=	strpos($rowClass, 'EVEN') ? 'EVEN' : '';
			$rowClasses[] =	$rowClass;
			continue;
		}
		$queuedEmails = fetchAssociations("SELECT * FROM tblqueuedemail");
		$numQueuedMessages = count($queuedEmails);
		if($status == 8 && !$numQueuedMessages) continue;
		$smtpHost = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE property IN ('emailHost', 'smtpPort', 'smtpSecureConnection', 'emailUser')");
		if($smtpHost['emailHost']) {
			$hostInfo = "<u title='{$smtpHost['emailHost']}:{$smtpHost['smtpPort']}'>H</u>";
			$usernameType = strpos($smtpHost['emailUser'], '@') ? 'email address' : 'not email address';
			$smtpTable .= "<tr><td>{$biz['bizname']}"
										."<td>{$smtpHost['emailHost']}"
										."<td>{$smtpHost['smtpPort']}"
										."<td>{$smtpHost['smtpSecureConnection']}"
										."<td>$usernameType";
			$hostLabels = explodePairsLine('smtpout.secureserver.net|secureserver||smtp.gmail.com|GMail');
			$hostLabel = $hostLabels[$smtpHost['emailHost']] ? $hostLabels[$smtpHost['emailHost']] : $smtpHost['emailHost'];
			$hostCell = "<span title='Host: {$smtpHost['emailHost']} Port: {$smtpHost['smtpPort']} Security: {$smtpHost['smtpSecureConnection']}. User name type: $usernameType'>$hostLabel</span>";
			$smtpHosts[$smtpHost['emailHost']][] = $biz['bizname'];
		}
		else {
			$hostInfo = '';
			$hostCell = "Leashtime SMTP";
			$leashTimeSMTPUsers[] = $biz['bizname'];
		}
		$prefs = fetchKeyValuePairs("SELECT * FROM tblpreference");
		$localTimeZone = $prefs['timeZone'] ? $prefs['timeZone'] : 'America/New_York';
		setLocalTimeZone($localTimeZone);
		$now = time();
		$lastEmailQueueProcessStart = $prefs['mailQueueSendStarted'];
		$mailQueueDisabled = $prefs['mailQueueDisabled'] 
			? " <img src='art/lockout-red.gif' title= 'Queue Disabled: {$prefs['mailQueueDisabled']}'>" 
			: '';
		if($status == 8 && $_REQUEST['ignoreLocks'] && $mailQueueDisabled) continue;

			//fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'mailQueueSendStarted' LIMIT 1");
		$time = strtotime($lastEmailQueueProcessStart);
		if(date('Y-m-d', $time) == date('Y-m-d'))
			$lastEmailQueueProcessStart = date('H:i:s', $time);
		foreach($queuedEmails as $item) 
			if($now - strtotime($item['addedtime']) > (10 * 60)) $row['emailwarning'] = 1; 
		if($row['emailwarning']) {
		  $row['emailqueue'] = "<span style='color:red;font-weight:bold;'>($numQueuedMessages) $hostInfo $lastEmailQueueProcessStart $mailQueueDisabled</span>";
		  if($hostCell) $hostCell = "<a target='smtphost' href='maint-dbs.php?surveysmtphosts={$smtpHost['emailHost']}'>$hostCell</a>";
		  $row['host'] = $hostCell;
		  if($mailQueueDisabled) $totalLockedMessages += $numQueuedMessages;
		  else $totalWaitingMessages += $numQueuedMessages;
		}
		else $row['emailqueue'] = "($numQueuedMessages) $hostInfo $lastEmailQueueProcessStart $mailQueueDisabled";
		if($numQueuedMessages) $row['emailqueue'] = 
			fauxLink($row['emailqueue'], "emailQueueDetails({$biz['bizid']})", 1, 'Click for details');
	}
	else { // Database is missing!
		if($status == 8) continue;
		$row['emailqueue'] = "--";
		$row['db'] = "<span style='color:red;' title='Database missing'>{$row['db']}</font>";
	}

	$row['test'] = $row['test'] ? '[T]' : '';
	$countrynames = explodePairsLine('UK|Britain||CA|Canada||AU|Australia||NZ|New Zealand');
	$flag  = $biz['country'] == 'US' ? '' : " <img src='art/world-flag-{$biz['country']}.gif' height=11 title='{$countrynames[$biz['country']]}'> ";
	$row['sortname'] = $row['bizname'];
	$row['bizname'] = loginLink($row['bizid']).$flag.bizLink($row['bizname'], $row['bizid'])
											.' '.fauxLink('(links)', "showLinks({$row['bizid']})", 1, "Customized login and prospect forms.");
	$row['db'] = dbLink($row);
	$row['activebiz'] = $row['activebiz'] ? 'active' : 'INACTIVE';
	if(!$prefs['mod_securekey']) $row['activebiz'] .= ' <img src="art/no-key.gif" height=10 title="Key Management turned off">';
	$row['supported'] = $row['supportactive'] ? 'Yes' : '';
	$bizowners = array();
	if($owners[$row['bizid']])
		foreach($owners[$row['bizid']] as $owner)
			$bizowners[] = ownerLink($owner, $businessesOnly);
	$row['owners'] = join(', ', $bizowners);
	if(!$businessesOnly && $row['live']) $row['bizid'] = "<span class='u' title='leashtime cust'>{$row['bizid']}</u>";
	$rowClass =	
		$biz['activebiz'] 
					? ($biz['dead'] ? 'deadprospect' : ($biz['former'] ? 'formerclient' : 'futuretask')) 
					: 'canceledtask';
	$rowClass .=	strpos($rowClass, 'EVEN') ? 'EVEN' : '';
	$rowClasses[] =	$rowClass;
			
	$rows[] = $row;
	$rowClassesByRow[print_r($row, 1)] = $rowClass;
}
if($status == 4 && $rows) {
	$rows[] = array('lockoutOrder' => -1, '#CUSTOM_ROW#' => "<tr><td colspan=5 style='font-weight:bold;font-size:2em;'>Locked out recently");
	$rows[] = array('lockoutOrder' => 1.5, '#CUSTOM_ROW#' => "<tr><td colspan=5 style='font-weight:bold;font-size:2em;padding-top:10px;'>Locked out a while ago");
	usort($rows, 'cmpLockoutOrder');
	// rowClasses is now all outta whack so,,,
	$rowClasses =	array();
	foreach($rows as $row)
		$rowClasses[] = $rowClassesByRow[print_r($row, 1)];
	
}
function cmpLockoutOrder($a, $b) {
	if(!($x = strcmp($a['lockoutOrder'], $b['lockoutOrder'])))
		$x = strcmp($a['sortname'], $b['sortname']); 
	return $x;
}

$statvalue = $_REQUEST['stat'];
$columns = explodePairsLine("lockout| ||bizid|Biz ID||test| ||bizname|Business Name||activebiz|Status||db|DB Name||dbhost|DB Host||owners|Managers||emailqueue|Email<br>Queue||supported|Support");
unset($columns['supported']);
$colClasses['owners'] = 'ownercell';
$colClasses['bizid'] = 'sortableListCell bizidcell';
$colClasses['test'] = 'sortableListCell testcell';

//$businessesOnly = in_array('b', getMaintRights());
if($businessesOnly) {
	//unset($columns['owners']);
	unset($columns['db']);
	unset($columns['dbhost']);
	unset($columns['emailqueue']);
	unset($columns['supported']);
}
$columnSorts = array('bizid'=>null, 'bizname'=>null, 'activebiz'=>null, 'db'=>null, 'dbhost'=>null);

$windowTitle = "Business List";
include 'frame-maintenance.php';
?>
<style>
.biztable td {padding-left:10px;}
.u {text-decoration:underline}
.ownercell {max-width:280px; font-size:1.1em;}
.bizidcell {max-width:40px;}
.testcell {max-width:20px;}
.deadprospect {background: plum;}
.deadprospectEVEN {background: orchid;}
.formerclient {background: #B2B2B2;}
.formerclientEVEN {background: #C8C8C8;}
</style>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
function emailQueueDetails(bizid) {
	$.fn.colorbox(
		{href: "maint-email-queue-report.php?bizId="+bizid, 
		 width:"750", height:"700", iframe:true, scrolling: "auto", opacity: "0.3"	});
}

function lockOut(bizid) {
	$.fn.colorbox(
		{href: "maint-lockout.php?bizId="+bizid, 
		 width:"700", height:"700", iframe:true, scrolling: "auto", opacity: "0.3"	});
}

function showLinks(bizid) {
	$.fn.colorbox(
		{href: "custom-biz-links.php?bizId="+bizid, 
		 width:"600", height:"300", iframe:false, scrolling: "auto", opacity: "0.3"	});
}

function update() {refresh();}
</script>
<?
if($error) echo "<font color=red>$error</font><p>";
if($message) echo "<font color=green>$message</font><p>";

if($businessesOnly) {
	$showlogos =  false;
	$radios = radioButtonSet('filter', $status, array('all'=>0,'active only'=>1, 'locked out'=>4), 
									$onClick="reload()", $labelClass=null, $inputClass=null); // "document.location.href=\"maint-dbs.php?sort=$sort&status=\"+this.value"
	echo "<b>Show:</b> ";
	foreach($radios as $radio) echo "$radio ";
}
else {
	$radioOptions = array('all'=>0,'paying'=>2,'trial'=>3,'paying+trial'=>5,'active'=>1, 'inactive'=>-1, 'locked out'=>4,'none'=>6,'recent'=>7);
	$radioOptions['Q!'] = 8;
	$icons = array(
		'all'=>"<img src='art/flag-globe-1.jpg' height=15 width=15 title='all'>",
		'paying'=>"<img src='art/flag-dollar.jpg' height=15 width=15 title='paying'>",
		'trial'=>"<img src='art/flag-danger.jpg' height=15 width=15 title='paying and trial'>",		
		'paying+trial'=>"<img src='art/flag-dollar.jpg' height=15 width=15 title='paying and trial'>+<img src='art/flag-danger.jpg' height=15 width=15 title='paying and trial'>",		
		'locked out'=>"<img src='art/lockout-red.gif' height=15 width=15 title='locked out'>",		

	);
	foreach($radioOptions as $k=>$v) $newRadionOptions[$icons[$k] ? $icons[$k] : $k] = $v;
	$radioOptions = $newRadionOptions;
	//}
	$radios = radioButtonSet('filter', $status, $radioOptions, 
									$onClick="reload()", $labelClass=null, $inputClass=null, $rawLabel=true); // "document.location.href=\"maint-dbs.php?sort=$sort&status=\"+this.value"
	echo "<div style='display:inline;background:white;border:solid black 1px;padding-top:6px;'>";
	echo "<b>Show:</b> ";
	foreach($radios as $radio) echo "$radio ";
	echo "</div>";
	echo " - <b>Hide:</b> ";
	labeledCheckbox('test databases', 'hidetest', $hidetest, null, null, "reload()", 'boxfirst');
	labeledCheckbox('free accounts', 'hidefree', $hidefree, null, null, "reload()", 'boxfirst');
	hiddenElement('showlogos', $showlogos);
	echo " - <b>Businesses shown:</b> ".count($rows).' ';
	fauxLink('QuickFind', 'quickFind()');
	echo " - ";
	fauxLink('SMTP Hosts', '$.fn.colorbox({html: $("#smtpdata").html(), width:"600", height:"500", iframe:false, scrolling: "auto", opacity: "0.3",});');
	echo " - ";
	fauxLink('Non Customers', '$.fn.colorbox({html: $("#noncustomers").html(), width:"600", height:"500", iframe:false, scrolling: "auto", opacity: "0.3",});');

}
if($status == 8) {
	$altURL = 'maint-dbs.php?sort=&status=8&hidetest=0&hidefree=0&ignoreLocks=';
	$showHide = 'Hide';
	if($_REQUEST['ignoreLocks']) {
		$showHide = 'Show';
	}
	else $altURL .= 1;
	echo "<p>";
	fauxLink("$showHide Locked Queues", "document.location.href=\"$altURL\"", 0, 0, 0, 'fontSize2_0em');
	if($totalWaitingMessages) echo " - Total messages waiting to be sent from 'problem' businesses: $totalWaitingMessages.  ";
	if($totalLockedMessages) echo "Total messages in locked queues: $totalLockedMessages.";
	$columns['host'] = 'SMTP';
}
if($status == 6) echo "<h3><i>none</i> was selected above, so no businesses are shown.</h3>";
if($status == 7 && !$rows) echo "<h3><i>recent</i> was selected above, and the most recently selected businesses will appear here after you login to them from other lists.</h3>";
else tableFrom($columns, $rows, "", $class='biztable', $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses);//, 'sortClick'
?>
<?
if(!$showlogos && !$businessesOnly) {
	fauxLink('Show Logos', '$("#showlogos").val(1);reload()', 0, 0, 0, 'fontSize2_0em');
	echo " - <a href=duplicate-common-ui.html target=commonuis>Common UI Looks</a>";
}
else if($showlogos) { ?>
<p><a href=duplicate-common-ui.html target=commonuis>Common UI Looks</a><table><tr><th>Business (#) Name</th><!-- <th>RC Logo</th> --><th>PROD Logo</th></tr>
<?
	fauxLink('Hide Logos', '$("#showlogos").val(0);reload()');
	foreach($bizzes as $biz) {
		$bizptr = $biz['bizid'];
		$PROD = 'https://{$_SERVER["HTTP_HOST"]}';
		$RC = 'https://{$_SERVER["HTTP_HOST"]}/rc';
		$DEV = 'https://{$_SERVER["HTTP_HOST"]}/dev';
		$PRODF = '/var/www/prod';
		$RCF = '/var/www/rc';
		$DEVF = '/var/www/dev';
		echo "<tr><td>($bizptr) {$biz['bizname']}<br>";
		uploadForm($bizptr);
		echo "</td><td>";
		/*if(file_exists("$RCF/bizfiles/biz_$bizptr/logo.gif")) echo "<img src='$RC/bizfiles/biz_$bizptr/logo.gif'>";
		else if(file_exists("$RCF/bizfiles/biz_$bizptr/logo.jpg")) echo "<img src='$RC/bizfiles/biz_$bizptr/logo.jpg'>";
		else echo "No Logo found.";*/
		echo "</td><td>";
		if($headerBizLogo = getHeaderBizLogo("bizfiles/biz_$bizptr/"))
			echo "<img src='$headerBizLogo'>";
		else echo "No Logo found.";
		echo "</td></tr>";
	}
}
?>
</table>

<div style='display:none;' id='smtpdata'>
<a href='https://leashtime.com/maint-dbs.php?surveysmtphosts=1' target='allsmtp'>Survey All Businesses</a>
<table border=1 bordercolor=black>
<? echo $smtpTable;
	 echo "<tr><td colspan=2><b>Hosts (".count($smtpHosts).")</b></td></tr>";
	 $leashTimeSMTPUsers = "(".count($leashTimeSMTPUsers).") ".join(', ', $leashTimeSMTPUsers);
	 foreach($smtpHosts as $host => $bizzes)
	 		echo "<tr><td>$host</td><td colspan=4>("
	 				.count($bizzes).") ".join(', ', $bizzes)."</td></tr>";
	 echo "<tr><td valign=top bgcolor=lightblue>(shown) LeashTime default</td><td bgcolor=lightblue colspan=4>$leashTimeSMTPUsers</td></tr>";
?>
</table>
</div>

<div style='display:none;' id='noncustomers'>
<table border=1 bordercolor=black>
<b>The following (<?= $noncustomerCount ?>) are not connected to LeashTime Customers</b>
<?= $noncustomers ?>
</table>
</div>

<script language='javascript'>

function reload(sort) {
	var filter='', 
		radios = [0,2,3,4,5,6,7,8,1,-1],
		hidetest = document.getElementById('hidetest') && document.getElementById('hidetest').checked ? 1 : 0,
		hidefree = document.getElementById('hidefree') && document.getElementById('hidefree').checked ? 1 : 0;
		showlogos = document.getElementById('showlogos') ? document.getElementById('showlogos').value : '';
	if(sort == 'undefined' || typeof sort == 'undefined') sort = '<?= $sort ?>';
	for(var i=0; i<radios.length; i++) {
		if(document.getElementById('filter_'+radios[i]) && document.getElementById('filter_'+radios[i]).checked) filter = radios[i];
	}
	
	document.location.href="maint-dbs.php?sort="+sort+"&status="+filter+"&hidetest="+hidetest+"&hidefree="+hidefree+"&showlogos="+showlogos;
}
	

</script>
<?
include "refresh.inc";

function uploadForm($bizptr) {
	echo "<form name='upload_$bizptr' method='POST' action='maint-dbs.php' enctype='multipart/form-data'>";
	echo "<input type='file' name='logofile'> <input type=submit value=Upload> ";
	fauxLink('Drop Logo', "if(confirm(\"Drop logo?\")) document.location.href=\"maint-dbs.php?droplogo=$bizptr&droplogotoken={$_SESSION['DROPLOGOTOKEN']}\"");
	echo " Max: 386 x 90";
	hiddenElement('bizptr', $bizptr);
	echo "</form>";
	
}