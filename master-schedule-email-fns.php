<? // master-schedule-email-fns.php

function getMasterScheduleToEmail($starting, $ending) {
	global $bizptr, $dbhost, $db, $dbuser, $dbpass;
	require_once "preference-fns.php";
	if(!$bizptr) $bizptr = $_SESSION['bizptr'];

	ob_start();
	ob_implicit_flush(0);
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$firstActiveOwner = fetchRow0Col0(
		"SELECT userid 
			FROM tbluser 
			WHERE bizptr = {$bizptr}
				AND active = 1
				AND isowner = 1
			ORDER BY userid
			LIMIT 1");
	if(mysql_error()) logError(mysql_error());	
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);

	global $wagPrimaryNameMode, $maxServiceNameLength;
	if($firstActiveOwner) {
		$props = getUserPreferences($firstActiveOwner);
		$wagPrimaryNameMode = $props['provsched_client'];
		if($props['provsched_start'] == 'starttime') $timeColumn = '||starttime|Start';
		else $timeColumn = '||time|Time';
		$phoneColumn = !$props['provsched_hidephone'] ? '||phone|Phone' : '';
		$addressColumn = !$props['provsched_hideaddress'] ? '||address|Address' : '';
		$maxServiceNameLength = 25;
		//$columnDataLine = "client|Client$phoneColumn$addressColumn||service|Service||date|Date$timeColumn||charge| ||buttons| ";
	}
	else {
		$wagPrimaryNameMode = null;
		$timeColumn = '||time|Time';
		$phoneColumn = '||phone|Phone';
		$addressColumn = '||address|Address';
		$maxServiceNameLength = 25;
	}
if(FALSE && mattOnlyTEST()) echo "($db) [[ firstActiveOwner: $firstActiveOwner ]] [[[wagPrimaryNameMode: $wagPrimaryNameMode]]]<hr>";	
	
	$columnDataLine = "date|Date$timeColumn||client|Client||service|Service$phoneColumn$addressColumn";  // charge| ||

	$sortSitter = "CONCAT_WS(', ', p.lname, p.fname)";
	$sitterName = "CONCAT_WS(' ', p.fname, p.lname)";
	if($_SESSION['preferences']['masterScheduleNicknames']) {
		$sitterName = "IFNULL(nickname, CONCAT_WS(' ', p.fname, p.lname))";
		$sortSitter = $sitterName;
	}

	$appts = fetchAssociations($sql =
		"SELECT a.*, 
				if(providerptr=0,'Unassigned',  $sitterName) as sitter, 
				if(providerptr=0,'--Unassigned',  $sortSitter) as sortsitter, 
				CONCAT_WS(' ', c.fname, c.lname) as client, 
				CONCAT_WS(' ', c.lname, c.fname) as sortclient
			FROM tblappointment a
			LEFT JOIN tblclient c ON clientid = clientptr
			LEFT JOIN tblprovider p ON providerid = providerptr
			WHERE date >= '$starting' AND date <= '$ending'
			ORDER BY date ASC, sortsitter ASC, starttime ASC, sortclient ASC", 1);

	echo '<head>
	<base href="https://'.$_SERVER["HTTP_HOST"].'">
	</head><body>';
	$shortStartDate = shortDate(strtotime($starting));
	$shortEndDate = shortDate(strtotime($ending));
	echo "<h2>Master Schedule: $shortStartDate - $shortEndDate</h2>\n";
	echo "Generated: ".longestDayAndDateAndTime();
	
	foreach($appts as $appt) if($appt['providerptr'] == 0) {
		$unassignedVisitCount++;
		$unassignedVisitDate[] = shortDate(strtotime($appt['date']));
	}
	if($unassignedVisitCount) 
		echo "<p><font color='red'>There "
		.($unassignedVisitCount == 1 ? "is one unassigned visit (on {$unassignedVisitDate[0]})"
			: "are $unassignedVisitCount unassigned visits")
		." from $shortStartDate to $shortEndDate.</font>";
	else echo "<p>";
	//echo $sql;
	$emailVersion = true; // reduces useless formatting, links, etc.  In one test, the output length declined by 43%.
	echo "<table border=1 bordercolor=black width=100%>";
	for($date = $starting; strcmp($date, $ending) <= 0; $date = date('Y-m-d', strtotime("+ 1 day", strtotime($date)))) {
		$providerptr = -1;
		$sitter = null;
		$lastSitter = null;
		$client = null;
		$rows = array();
		echo "<tr><td colspan=1 style='background:lightblue'><b>".longDayAndDate(strtotime($date), 1)." ".date('Y', strtotime($date))."</b></td></tr>\n";
		foreach($appts as $appt) {
			if($appt['date'] != $date) continue;
			$appt['charge'] = '';
			if($providerptr != -1 && $appt['sitter'] != $sitter) {
				echo "<tr><td colspan=1>Sitter: <u>$sitter</u></td></tr>";
				echo "<tr><td colspan=1>";
				versaProviderScheduleTable($providerptr, $rows, array('date'), 'noSort', null, 1, 0, 'forceDateRow', $columnDataLine, $emailVersion);
				echo "</td></tr>";
				$rows = array();
//print_r($columnDataLine);print_r($rows);exit;			     
//versaProviderScheduleTable($providerid, $rows, $suppressColumns=null, $noSort=false, $updateList=null, $noLinks=false, $forceDateRow=false, $providerView=false, $columnDataLine=null) 

				echo "</td></tr>";
				$lastSitter = $sitter;
			}
			$sitter = $appt['sitter'];
			$providerptr = $appt['providerptr'];
			$rows[] = $appt;
		}
		if($rows && $lastSitter != $sitter) {
			echo "<tr><td colspan=1>Sitter: <u>$sitter</u></td></tr>";
			echo "<tr><td colspan=1>";
			versaProviderScheduleTable($providerptr, $rows, array('date'), 'noSort', null, 1, 0, 'forceDateRow', $columnDataLine, $emailVersion);
			echo "</td></tr>";
		}
	}

	echo "</table>";
	$schedule = ob_get_contents();
	ob_end_clean();
	return $schedule;
}

function emailMasterSchedule() {
	global $db;
	$scriptPrefs = fetchKeyValuePairs("SELECT property, value FROM tblpreference");
	echo date('m/d/Y H:i:s')." Business: {$scriptPrefs['bizName']} ($db)<br>";
	if(!$scriptPrefs['masterSchedule']) echo "Master Schedule feature not turned on.<br>";
	else if(!$scriptPrefs['masterScheduleRecipients'] || !($userids = explode(',', $scriptPrefs['masterScheduleRecipients']))) 
		echo "No recipients set to receive Master Schedule.<br>";
	else {
		// make sure there are recipients
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		require "common/init_db_common.php";
		$sql = 
					"SELECT userid, email, active, fname, lname, CONCAT_WS(' ', fname, lname) as name
						FROM tbluser 
						WHERE userid IN ({$scriptPrefs['masterScheduleRecipients']})
						ORDER BY email";
		$recips = fetchAssociationsKeyedBy($sql, 'userid');
		foreach($recips as $userid => $recip) {
			if(!$recip['email']) {
				echo "Intended recipient {$recip['name']} has no email address.<br>";
				unset($recips[$userid]);
			}
			else if(!$recip['active']) {
				echo "Intended recipient {$recip['name']} ({$recip['email']}) is not active.<br>";
				unset($recips[$userid]);
			}
		}
		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
		if($recips) {
			require_once "comm-fns.php";
			$starting = date('Y-m-d');
			$days = $scriptPrefs['masterScheduleDays'];
			$plusdays = $days - 1;
			$ending = date('Y-m-d', strtotime("+ $plusdays days", strtotime($starting)));
			$schedule = getMasterScheduleToEmail($starting, $ending);
			$first = array_pop($recips);
			foreach($recips as $recip) $cc[$recip['userid']] = $recip['email'];
			if($cc) $cc = join(', ', $cc);
			$allAdds = $recip['email'].($cc ? ", $cc" : '');
			echo "Queuing Master Schedule ($days days) for $allAdds.<br>";
			enqueueEmailNotification($first, 'Master Schedule', $schedule, $cc, 'System', $html=1);
		}
	}

}

