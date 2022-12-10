<? // mailchimp-fns.php

function emailLeashTimeMergeList() {
	require_once "comm-fns.php";
	ob_start();
	ob_implicit_flush(0);
	echo "<pre>";
	dumpLeashTimeMergeList();
	echo "</pre>";
	$body = ob_get_contents();
	//echo 'XXX: '.ob_get_contents();exit;
	ob_end_clean();
	$error = sendEmail('response@leashtime.com', 'Daily MailChimp Dump', $body, $cc, $html=1);
	if($error) {echo "Could not send: $error";}
}


function dumpLeashTimeMergeList() {
	$live = 2;
	$trial = 1;
	
	$clientIds = clientsByFlag(array($live), 'and', $active='active');
	echo "<h2>Paying Customers (".count($clientIds).")</h2>\n\n";
	dumpClientMergeList($clientIds);
	echo "\n<h2>Alt Emails: Paying Customers</h2>\n\n";
	dumpAltClientMergeList($clientIds);
	
	$clientIds = clientsByFlag(array($trial), 'and', $active='active');
	echo "\n<h2>Trial Customers (".count($clientIds).")</h2>\n\n";
	dumpClientMergeList($clientIds);
	echo "\n<h2>Alt Emails: Trial Customers</h2>\n\n";
	dumpAltClientMergeList($clientIds);
}
	
function clientsByFlag($flags, $useflags='and', $active='active') {
	require_once "client-flag-fns.php";
	$clientWhere = $active == 'active' ? "AND active=1" : ($active == 'inactive' ? "AND active=0" : '');
	$clientIds = fetchCol0("SELECT clientid FROM tblclient WHERE email IS NOT NULL $clientWhere");
	if($clientIds) $whereClause = "WHERE clientptr IN (".join(',',$clientIds).")";
	$sql = "SELECT clientptr, SUBSTRING(value, 1, LOCATE('|', value)-1)  as flag
						FROM tblclientpref 
						$whereClause";
	foreach(fetchAssociations($sql,1) as $row) 
		if($row['flag']) $allClientFlags[$row['clientptr']][] = $row['flag'];
if(mattOnlyTEST()) echo "active with flags[[[".count($allClientFlags)."]]]<p>";	
//if(mattOnlyTEST()) echo "[[[".count($clientIds)."]]]$sql<p><pre>".print_r($allClientFlags, 1);	
	$clientIds = $clientIds ? $clientIds : fetchCol0("SELECT clientid FROM tblclient");
	if($useflags == 'none') {
		$clientIds = array_diff($clientIds, array_keys($allClientFlags));
	}
	else {
		foreach($allClientFlags as $clientId => $clientFlags) {
//if(in_array(1, $clientFlags)) $goldstars += 1;
			if($useflags == 'and' && count(array_intersect($flags, $clientFlags)) != count($flags)) {
				unset($allClientFlags[$clientId]);
			}
			else if($useflags == 'or' && !array_intersect((array)$flags, (array)$clientFlags))
				unset($allClientFlags[$clientId]);
			else if($useflags == 'nor' && array_intersect((array)$flags, (array)$clientFlags))
				unset($allClientFlags[$clientId]);
		}
		$clientIds = array_unique(array_intersect((array)$clientIds, array_keys($allClientFlags)));
	}
//if(mattOnlyTEST()) echo "<p>GOLD: $goldstars<p>";
	return $clientIds;
}

	
function dumpClientMergeList($clientIds) {
	foreach($clientIds as $recipid) {
		$recip = fetchFirstAssoc("SELECT email, fname, lname FROM tblclient WHERE clientid = $recipid LIMIT 1", 1);
		echo "{$recip['email']}\t{$recip['fname']}\t{$recip['lname']}\n";
	}
}
			
function dumpAltClientMergeList($clientIds) {
	foreach($clientIds as $recipid) {
		$recip = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = $recipid LIMIT 1", 1);
		$names = trim("{$recip['fname2']}{$recip['lname2']}") == ''
							? "{$recip['fname']}\t{$recip['lname']}" 
							: "{$recip['fname2']}\t{$recip['lname2']}";
		if($recip['email2']) echo "{$recip['email2']}\t{$names}\n";
	}
}
			
if($_GET['dump']) {
	require_once "common/init_session.php";
	require_once "common/init_db_petbiz.php";
	//emailLeashTimeMergeList();
}
			
			
			
