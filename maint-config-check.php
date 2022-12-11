<? // maint-config-check.php
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "js-gui-fns.php";

$locked = locked('z-');
extract(extractVars('bizdb,prop,val,new', $_REQUEST));

if($bizdb && ($prop || $new)) {
	$success = null;
	if($new) {
		insertTable("$bizdb.tblpreference", array('property'=>$new, 'value'=>$val), "property = '$new'", 1);
		$success = !mysqli_error();
	}
	else $success = updateTable("$bizdb.tblpreference", array('value'=>$val), "property = '$prop'", 1);
	if($success) {
		if($new) echo "REFRESHNOW";
		else echo "Yes";
	}
	else echo "???";
	exit;
}

$dbs = fetchKeyValuePairs("SELECT db, db FROM tblpetbiz ORDER BY db");
$dbs = array_merge(array('Pick a biz'=>''), $dbs);
$orderBy = !$sorts ? "ORDER BY time DESC" : "ORDER BY ".str_replace('_', ' ', $sort);
$filter = array();
$continue = true;
if($bizdb) {
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = '$bizdb'");
	foreach(array('bizname','dbhost','dbuser','dbpass') as $key) 
		if(!$biz[$key]) $errors[] = "$key field missing in business record.";
	if(!in_array($bizdb, fetchCol0("SHOW DATABASES"))) {
		$errors[] = "Database not found on $dbhost";
		$continue = false;
	}
	$sections['Business Record Settings'] = $errors;
	$errors = array();
	if($continue) {
		$prefs = fetchKeyValuePairs("SELECT property, value FROM $bizdb.tblpreference");
	}
	$bizSection = "bizName|Business Name|string||shortBizName|Short Business Name|string||".
												"bizAddress|Business Address|string||".
												"bizPhone|Business Phone|string||".
												"bizFax|Business FAX|string||".
												"bizEmail|Business Email|string||bizHomePage|Business Home Page|string||".
												"petTypes|Pet Types|list|sortable||".
												"defaultTimeFrame|Default Visit Timeframe|string||".
												"recurringScheduleWindow|Recurring Appointment Lookahead Period (days)|int";
	foreach(prefLabels($bizSection) as $key => $label)
		if(!$prefs[$key]) $errors[] = "$label ($key) not set.";
		
		
	$emailPrefs = "emailFromAddress|From Line|string||emailBCC|CC sent mail to|string||emailHost|SMTP (Outbound eMail) Host|string||".
												"emailUser|User Name|string||emailPassword|Password|password";
	if($prefs['emailFromAddress'] 
			&& !($prefs['emailHost'] &&$prefs['emailUser'] &&$prefs['emailPassword']))
		$errors[] = "Email From Line (emailFromAddress) is set, but email host, user, and password are not.";
		
	$scheduleNotifications = "scheduleDay|Send Weekly Schedules|picklist|Never,Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday"; 
	if(!$prefs['scheduleDay']) $errors[] = "Day to Send Weekly Sitter Schedules not set.";

	$visitCancellationPrefs ="cancellationDeadlineHours|Cancellation Deadline|custom|cancellation-deadline-edit.php";
	if(!$prefs['cancellationDeadlineHours']) $errors[] = "Cancellation Deadline not set.";
	
	$billingPrefs .= 
									"||bimonthlyBillOn1|Bill On Day of Month|picklist|$daysOfMonth"
									."||bimonthlyBillOn2|Bill On Day of Month|picklist|$daysOfMonth";
	if(!($prefs['bimonthlyBillOn1'] || $prefs['bimonthlyBillOn2'])) $errors[] = "No Billing Day of Month set.";
	if($prefs['taxRate'] && !$prefs['newServiceTaxableDefault']) $errors[] = "Tax rate is set, but New Services are not Taxable By Default.";
	$billingPrefs = "pastDueDays|Past Due Days|int"
									."||merchantInfo|Credit Card Merchant Info|custom|cc-merchant-edit.php"
									."||surchargeCollisionPolicy|Automatic Surcharge Collision Policy|picklist|Apply the greatest charge,Apply the smallest charge,Apply all charges";
	foreach(prefLabels($billingPrefs) as $key => $label)
		if(!$prefs[$key]) $errors[] = "$label ($key) not set.";
									
	if($prefs['monthlyServicesPrepaid'] && !$prefs['monthlyBillOn'])
		$errors[] = "Monthly Billing date not set.";


	$holidayPrefs ="holidayVisitLookaheadPeriod|Holiday Visit Lookahead Period (days)|picklist|$days";
	if(!$prefs['holidayVisitLookaheadPeriod']) $errors[] = "Holiday Visit Lookahead Period not set.";
	$sections['Business Preferences'] = $errors;
	
	
	
	$errors = "<table><tr><th><th>Send Daily Schedules<th>Send Weekly Schedules </td></tr>";
	$errors .= "<tr><td><b>Default:<td><b>".($prefs['scheduleDaily'] ? 'Yes' : 'No')."<td><b>".($prefs['scheduleDay'] ? $prefs['scheduleDay'] : 'No');
	foreach(fetchAssociations("SELECT *, CONCAT_WS(' ',fname,lname) as name FROM {$biz['db']}.tblprovider WHERE active=1") as $p) 
		$errors .= "<tr><td>{$p['name']}<td>".($p['dailyvisitsemail'] ? 'Yes' : '<font color=red>No</font>')."<td>".($p['weeklyvisitsemail'] ? 'Yes' : '<font color=red>No</font>')."</tr>";
	$errors .= "</table>";
	$sections['Sitter Schedule Email Preferences'] = $errors;
	
	
	$monitors = fetchAssociations("SELECT * 
															FROM {$biz['db']}.relstaffnotification 
															WHERE 1=1 ORDER BY daysofweek, timeofday");
	if($monitors) {
		foreach($monitors as $monitor) $monitorUserIds[] = $monitor['userptr'];
		$providers = fetchAssociations("SELECT *, CONCAT_WS(' ', fname, lname) as name 
																FROM {$biz['db']}.tblprovider 
																WHERE userid IN (".join(',', $monitorUserIds).")");
		$ltstaffCount = fetchRow0Col0("SELECT count(*) FROM tbluser WHERE userid IN (".join(',', $monitorUserIds).") AND ltstaffuserid IS NOT NULL");
	}
	$errors = array();
//print_r($monitors);	
	if(!$monitors) $errors[] = "No event monitors are defined.";
	if($ltstaffCount && $ltstaffCount == count($monitors)) $errors[] = "The only monitors are LT staff.";
	foreach((array)$providers as $p) if(!$p['active']) 
		$errors[] = "Inactive provider [{$p['name']}] is still listed as an event monitor.";
	$sections['Event Email Monitors'] = $errors;

	$errors = array();
	if(file_exists($dir = "bizfiles/biz_{$biz['bizid']}/photos")) {
		$perms = filePermissionLine($dir);
		$groupinfo = posix_getgrgid(filegroup($dir));
		if(!in_array($groupinfo['name'], array('apache', 'www-access')))
			$errors[] = "Dir $dir [$perms] : belongs to the wrong group.".print_r($groupinfo['name'],1);
			//'drwxrwxr-x'
		if(substr($perms, 1,3) != 'rwx') $errors[] = "Dir $dir [$perms] : owner must have rwx.";
		//if(!in_array(substr($perms, 4,3), array('rwx', 'r-x'))) $errors[] = "Dir $dir [$perms] : group must have r and x.";
	}
	else {
		$errors[] = "Dir bizfiles/biz_{$biz['bizid']}/photos does not exist.";
		$perms = filePermissionLine("bizfiles/biz_{$biz['bizid']}");
			//'drwxrwxr-x'
		if(substr($perms, 1,3) != 'rwx') $errors[] = "Dir bizfiles/biz_{$biz['bizid']} [$perms] : owner must have rwx.";
		//if(substr($perms, 4,3) != 'rwx') $errors[] = "Dir bizfiles/biz_{$biz['bizid']} [$perms] : group must have rwx.";
	}
	$sections['Biz files permissions'] = $errors;
}

function filePermissionLine($file) {
	$perms = fileperms($file);

	if (($perms & 0xC000) == 0xC000) {
			// Socket
			$info = 's';
	} elseif (($perms & 0xA000) == 0xA000) {
			// Symbolic Link
			$info = 'l';
	} elseif (($perms & 0x8000) == 0x8000) {
			// Regular
			$info = '-';
	} elseif (($perms & 0x6000) == 0x6000) {
			// Block special
			$info = 'b';
	} elseif (($perms & 0x4000) == 0x4000) {
			// Directory
			$info = 'd';
	} elseif (($perms & 0x2000) == 0x2000) {
			// Character special
			$info = 'c';
	} elseif (($perms & 0x1000) == 0x1000) {
			// FIFO pipe
			$info = 'p';
	} else {
			// Unknown
			$info = 'u';
	}

	// Owner
	$info .= (($perms & 0x0100) ? 'r' : '-');
	$info .= (($perms & 0x0080) ? 'w' : '-');
	$info .= (($perms & 0x0040) ?
							(($perms & 0x0800) ? 's' : 'x' ) :
							(($perms & 0x0800) ? 'S' : '-'));

	// Group
	$info .= (($perms & 0x0020) ? 'r' : '-');
	$info .= (($perms & 0x0010) ? 'w' : '-');
	$info .= (($perms & 0x0008) ?
							(($perms & 0x0400) ? 's' : 'x' ) :
							(($perms & 0x0400) ? 'S' : '-'));

	// World
	$info .= (($perms & 0x0004) ? 'r' : '-');
	$info .= (($perms & 0x0002) ? 'w' : '-');
	$info .= (($perms & 0x0001) ?
							(($perms & 0x0200) ? 't' : 'x' ) :
							(($perms & 0x0200) ? 'T' : '-'));

	return $info;
}

function prefLabels($str) {
	foreach(explode('||', $str) as $piece) {
		$parts = explode('|', $piece);
		$pairs[$parts[0]] = $parts[1];
	}
	return $pairs;
}

function mailCheck() {
	global $prefs, $externalSetup;
	$fields = 'Reply to Email Address|defaultReplyTo||Sender Email Address|emailFromAddress'
			 .'||SMTP (Outbound eMail) Host|emailHost||SMTP Port|smtpPort'
			 .'||Email User Name|emailUser||Email Password|emailPassword'
			 .'||Use SMTP Authentication|smtpAuthentication||Use Secure Connection|smtpSecureConnection';
	echo "<span style='font: bold 12pt arial'><p>Outgoing Email Preferences</span>";
	echo '
	<style>
	.mailprefs td {font-size: 10pt; font-weight:normal;}
	.bold {font-weight:bold;}
	.box {border:solid gray 1px}
	.blue {color:blue}
	</style>
	<table class="mailprefs">
	';
	$externalSetup = $prefs['emailHost'] && $prefs['emailUser'] && $prefs['emailPassword'];
	foreach(explodePairsLine($fields) as $label => $key) {
		$note = '';
		if($key == 'defaultReplyTo' && !$prefs[$key]) $note = 'Business will not receive auto replies.';
		if($key == 'emailFromAddress') $note = blueConstraint($key, true);
		if($key == 'emailHost') $note = blueConstraint($key, true);
		if($key == 'smtpPort') $note = blueConstraint($key, false);
		if($key == 'emailUser') $note = blueConstraint($key, true);
		if($key == 'emailPassword') $note = blueConstraint($key, true);
		if($key == 'smtpAuthentication') $note = blueConstraint($key, false);
		if($key == 'smtpSecureConnection') $note = blueConstraint($key, false);
		$val = $key == 'emailPassword' ? ($prefs[$key] ? 'Password set' : 'Password not set') : $prefs[$key];
		$class = 'bold box';
		if(in_array($key, array('emailHost','smtpPort','emailUser','emailPassword'))) $class = 'bold box blue';
		echo "<tr><td class='$class'>$label</td><td class='box'>$val</td><td style='color:red;'>$note</td></tr>\n";
	}
	echo '</table>';
}

function blueConstraint($key, $required=false) {
	global $prefs, $externalSetup;
	$anyExternalSetup = $prefs['emailHost'] || $prefs['smtpPort'] || $prefs['emailUser'] || $prefs['emailPassword'];
	if($prefs[$key] && !$externalSetup) return 'Should be set only when all blue fields are supplied.';
	else if($required && !$prefs[$key] && $anyExternalSetup) return 'If any blue fields are supplied, this field should be supplied.';
}


function testImmediateEmail($address)	{//sendEmailViaSMTPServer($toRecipients, $subject, $body, $cc = null, $html=null, $senderLabel='', $bcc=null)
	//sendEmail($recipients, $subject, $body, $cc=null, $html=null, $senderLabel='', $bcc=null)
	//sendEmail($recipients, $subject, $msgbody, '', '', $mgrname, $bcc)) 

	$testResult = sendEmailViaSMTPServer(//sendEmailViaXPertMailerSMTPServer(
		$address, 
		$subject = 'Outbound email test (immediate) from LeashTime', 
		"This test message was sent ".date('m/d/Y H:i:s').".",
		$cc = null, 
		$html=null, 
		$senderLabel=getPreference('shortBizName'));
	if(!$testResult) $testResult = "Test succeeded.<p>Please check your [$address] inbox "
																	."for a message labeled <b>$subject</b>.";
	else $testResult = "<font color=red>FAILED:</font> ".fetchRow0Col0("SELECT message FROM tblerrorlog ORDER BY time DESC LIMIT 1");
	echo "$testResult<p>";
}

$windowTitle = 'Configuration Check';
include 'frame-maintenance.php';
echo "<div style='padding-left:10px'>";
echo "<h2>$bizdb Configuration Check</h2>";

?>
<style>
.biztable td {padding-left:10px;}
</style>

<?
selectElement('Business:', 'bizdb', $bizdb, $dbs, "document.location.href=\"maint-config-check.php?bizdb=\"+document.getElementById(\"bizdb\").options[document.getElementById(\"bizdb\").selectedIndex].value");
?>
<?
echo "<p><table>";
foreach((array)$sections as $label => $errors) {
	echo "<tr><td style='font: bold 12pt arial;padding-top:10px;'>$label</td>";
	if($errors && is_array($errors)) foreach($errors as $error)
		echo "<tr><td style='color:red'>$error";
	else if($errors) echo "<tr><td style='color:black'>$errors";
	else if($bizdb) echo "<tr><td style='color:green'>No configuration problems found.";
}

echo "</table>";
if($prefs) mailCheck();
include "refresh.inc";
?>
<script language='javascript'>
</script>
