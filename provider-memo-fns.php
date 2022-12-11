<? // provider-memo-fns.php

function makeProviderMemo($provptr, $note, $client=0, $preprocess=0, $acceptAlways=false) {
	if(!$provptr) return;
//echo "PROV: $provptr NOTE: $note	ACCEPTS: [".providerAcceptsMemosAbout($provptr, $note, $confirmation=false)."]";exit;

	if(!$acceptAlways && !providerAcceptsMemosAbout($provptr, $note, $confirmation=false))
			return;
	$prov = fetchFirstAssoc("SELECT active, email FROM tblprovider WHERE providerid = $provptr LIMIT 1");
	if(!$prov['active'] || !$prov['email']) return;
	
	//saveNewConfirmation($msgptr, $respondentptr, $respondenttable, $token, $due, $expiration)
	
	insertTable('tblprovidermemo', 
							array('providerptr'=>$provptr, 'datetime'=>date('Y-m-d H:i:s'), 
							'note'=>$note, 'clientptr'=>$client, 'preprocess' => $preprocess), 1);
}

function enqueueProviderMemos($provptr=null) {
	// the cron job calls this method for ALL providers (=null)
	global $clientsAndPets;
	$clientsAndPets = array(); // is reset below for each sitter
	$dueDate = date('Y-m-d H:i:s', strtotime("+36 hours"));
	$tokenExpirationDate = date('Y-m-d H:i:s', strtotime("+48 hours"));
	require_once "sms-fns.php";
	require_once "encryption.php";
	require_once "preference-fns.php";
	$smsMemosAreAllowed = fetchPreference('enableSitterMemoSMS') && smsEnabled('forLeashTime');
	
	$filter = $provptr ? "WHERE providerptr = $provptr" : '';
	$memos = array();
	$result = doQuery("SELECT * FROM tblprovidermemo $filter ORDER BY memoid");
	while($row = mysqli_fetch_assoc($result)) {
		$memos[$row['providerptr']][] = $row;  // use md5 key to eliminate dups
	}
//echo "MEMOS: ";print_r($memos);	
	$bizName = fetchRow0Col0("SELECT value FROM tblpreference where property = 'bizName'");
	$looks = 
"<style>
.changestable {background: lightblue;}
.changestable td {background: white;}
.descriptionbox {background:white;width:500px;border: solid black 1px;padding:5px;margin-bottom:0px;}
</style>\n";
	$messageConfirmations = array();
	$memosProcessed = array();
	$uniqueKeys = array();
	$clientLastNames = fetchKeyValuePairs("SELECT clientid, lname FROM tblclient");
	foreach($memos as $provptr => $items) {
		$provider = getProvider($provptr);
		// if !$provider['active'] OR !$provider['email'] we should probably delete the memo
		if($provider['active'] && $provider['email']) {  // the opposite is a low probability
			$messageBody = $looks."Dear ".providerShortName($provider).',<p>Please note the following changes and events:<p>';
			$messageBody .= "<table class='changestable' cellspacing=10>\n";
			$confirmationIds = array();
			$memoNumber = 0;
			$clientsAndPets = array();  // this lists clients and pets involved in memos for THIS client
			foreach($items as $mm => $memo) {
				$memosProcessed[] = $memo['memoid'];
				$uniqueKey = uniqueMemoKey($memo);
				if(!$uniqueKey) {
					logError("Problem working with memo fragment: ".print_r($memo, 1));
					continue;
				}
}				
				if(in_array($uniqueKey, $uniqueKeys)) continue;
				else $uniqueKeys[] = $uniqueKey;
				$memoNumber++;
				collectClientsAndPets($memo, $clientsAndPets, $clientLastNames);
				$memoFrag = memoFragment($memo);
//if(dbTEST('dogslife')) logError("NULL memoFrag: ".print_r($memo,1));
				if($memoFrag) { // may be null if only deleted visits were memo'd
					$contentFound = true;
					if(memoNeedsConfirmation($memo)) 
						$confirmationLinks = memoConfirmationLinks($memo, $memoNumber, $provider, $dueDate, $tokenExpirationDate, $confirmationIds);
					$messageBody .= "<tr><td id='section_$memoNumber'>".memoFragment($memo)."$confirmationLinks\n</td></tr>\n";
					// Handle SMS memos right here
					if(findSMSPhoneNumberFor($provider) && $smsMemosAreAllowed && memoQualifiesForSMS($memo)) {
						if(is_string($error= notifyByLeashTimeSMS($provider, memoSMS($memo), $media=null)))
							logError("Sitter Memo SMS failed: $error");;
					}
					
				}
			}
			if(!$contentFound) continue;
			$messageBody .= "</table>";
			$subject = memoMessageSubjectLine($memos, $bizName, $clientsAndPets, $clientLastNames);  //"Memo from ".($bizName ? $bizName : 'Leashtime');
			$messagePtr = enqueueEmailNotification($provider, $subject, $messageBody, null, null, 'html');  // returns array on fail
			if(is_array($messagePtr)) {
				logError("enqueueProviderMemos($provptr): ".mysqli_error());
				$memoIds = array();
				foreach($items as $memo) $memoIds = $memo['memoid'];
				$memosProcessed = array_diff($memosProcessed, $memoIds);
			}
			else if($confirmationIds) {
				updateTable('tblconfirmation', array('msgptr'=>$messagePtr), "confid IN (".join(',', $confirmationIds).")", 1);
				$messageConfirmations[$messagePtr] = $confirmationIds;
			}
		}
	}
	if($memosProcessed) deleteTable('tblprovidermemo', "memoid IN (".join(',', $memosProcessed).")", 1);
	return $messageConfirmations;
}

function memoQualifiesForSMS($memo) {
	
	if(strpos($memo['note'], 'homesafe|') === 0)
		return fetchPreference('homeSafeTextToSitters');
	
	// decide whether $memo qualifies to be sent via SMS
	$hoursToGo = fetchPreference('providerMemoImminenceWindow');
	$hoursToGo = $hoursToGo ? $hoursToGo : 24;
	$starttime = memoSubjectStartTime($memo);
	$ok = true;
	if($starttime) {
		if(strtotime("+$hoursToGo hours") < strtotime($starttime)) {
			//echo "FAIL starttime: $starttime";
			$ok = false;
		}
	}
	else {
		//echo "FAIL - no starttime: [$starttime]";
		$ok = false;
	}
	if(FALSE && $ok) {  // Wrote this when we were considering quotas
		// check SMS quotas for this business
		// count SMS metadata messages (inbound or outbound) in the last 30 days
		$smsCount = countSMSMessagesSince(30);
		// if the count > 200 $ok = false
		if($smsCount > 200) {
			//echo "FAIL - quota: [$smsCount]";
			$ok = false;
		}
	}
	return $ok;
}

function uniqueMemoKey($memo) {
	unset($memo['datetime']); // to allow us to eliminate dups below
	unset($memo['memoid']); // to allow us to eliminate dups below
	if(strpos(($note = $memo['note']), 'cancelvisit|') !== FALSE)
		$memo['note'] = 'cancelvisit|'.substr($note, strpos($note, '|')+1);
	else if(strpos(($note = $memo['note']), 'deletevisit|') !== FALSE) {
		$parts = explode('|', $note); // deletevisit|visitid|description
		$memo['note'] = 'cancelvisit|'.$parts[1];
	}
	else if(strpos(($note = $memo['note']), 'schedule|') !== FALSE) {
		require_once "service-fns.php";
		$package = getPackage(substr($note, strpos($note, '|')+1));
		if(!$package) return null;
}				
		$history = findPackageIdHistory($package['packageid'], $package['clientptr'], $package['recurring']);
//echo "PACK: ".print_r($package, 1)." HIST: ".print_r($history, 1);exit;
		$memo['note'] = 'schedule|'.$history[0];
	}
	return md5(print_r($memo,1));
}

function memoMessageSubjectLine(&$memos, $bizName, &$clientsAndPets, &$clientLastNames) {
	$parts = array();
	foreach($clientsAndPets as $clientid => $pets)
		$parts[] = $clientLastNames[$clientid]."(".join(',',$pets).")";
	return "Schedule changes and events: ".join(', ', $parts);
}

function collectClientsAndPets($memo, &$clientsAndPets) {
	$note = explode('|', $memo['note']);
	$previousPets = $clientsAndPets[$memo['clientptr']];
	if(!$previousPets) $previousPets = array();
if(FALSE && mattOnlyTEST()) {print_r($note[0] == 'schedule');	print_r($note);}
	if($note[0] == 'schedule') {
if(FALSE && mattOnlyTEST()) echo "BANG!";	
		$package = getPackage($note[1]);
if(FALSE && mattOnlyTEST()) echo "BOOM! ".print_r(	$package, 1);	
		if($package['irregular']) {
			$ezpets = array();
			$appts = array();//
if(FALSE && mattOnlyTEST()) $appts = fetchAllAppointmentsForNRPackage($package);
			foreach($appts as $appt) 
				if($appt['pets']) 
					foreach(explode(',', $appt['pets']) as $pet)
						$ezpets[] = $pet;
			$previousPets = array_unique(array_merge($previousPets, $ezpets));
		}
		else {
			$servicePets = fetchCol0("SELECT DISTINCT pets FROM tblservice WHERE packageptr = {$note[1]}");
			foreach($servicePets as $spets)
				$previousPets = array_unique(array_merge($previousPets, explode(',', $spets)));
		}
	}
	else if($note[1] && strpos($note[0], 'homesafe') === 0) {
		// homesafe|HOME SAFE reported : Elroy Krum\n(Apple, Barley, Bubbles, Frieda, Gilly, Lightning, sdf)
		$parts = explode('(', $note[1]);
		if(count($parts) < 2) return;
		$petsWithoutParens = trim(str_replace(")", "", $parts[1]));
		if(!$petsWithoutParens) return;
		$previousPets = array_unique(array_merge($previousPets, explode(', ', $petsWithoutParens)));
	}
	else if($note[1] && strpos($note[0], 'visit') === FALSE) return;  // not a visit or schedule, so return
	else if($note[1] && strpos($note[0], 'cancelvisits') !== FALSE) { // cancelvisits and uncancelvisits
		// e.g., cancelvisits|2|2017-10-25
		// since we have no specific list of visits, we assume All Pets
		$previousPets = array('All Pets'); 
	}
	else if($note[1]) {
		$visitPets = fetchCol0("SELECT pets FROM tblappointment WHERE appointmentid = {$note[1]} LIMIT 1");
		$previousPets = array_unique(array_merge($previousPets, $visitPets)); // explode(',', (string)$visitPets)
	}
	if(in_array('All Pets', $previousPets)) {
		$previousPets = array_unique(fetchCol0("SELECT name FROM tblpet WHERE ownerptr = {$memo['clientptr']} AND active = 1"));
		if(!$previousPets) $previousPets = array('All Pets');
	}
	$clientsAndPets[$memo['clientptr']] = $previousPets;
}

function memoConfirmationLinks($memo, $memoNumber, $provider, $dueDate, $tokenExpirationDate, &$confirmationIds) {
	// may return null if provider lacks email address or is not set to receive confirmation links

if(dbTEST('comfycreatures')) logError("PROVIDER: {$memo['providerptr']} ACCEPTS confirmation of {$memo['note']}: ".providerAcceptsMemosAbout($memo['providerptr'], $memo['note'], $confirmation=true));
	if(!providerAcceptsMemosAbout($memo['providerptr'], $memo['note'], $confirmation=true))
			return;
//echo print_r($memo,1)." PROV: $provider\n";	
	global $biz;
	$bizptr = $_SESSION ? $_SESSION['bizptr'] : ($biz ? $biz['bizid'] : null);
	require_once "confirmation-fns.php";
	$yesResponse = generateResponseURL($bizptr, $provider, "confirm-memo.php?memo=$memoNumber&action=confirm&token=", false, $tokenExpirationDate, true);
if(dbTEST('comfycreatures')) logError("MEMO yesResponse: ".print_r($yesResponse, 1));
	if(is_array($yesResponse)) {echo $yesResponse[0];return;}
	if($yesResponse) {
		$token = substr($yesResponse, strpos($yesResponse, '?token=')+strlen('?token='));
		if(!$token) {
			logError("provider-memo-fns>memoConfirmationLinks: null token from [$yesResponse]");
		}
		else {
			$confid = saveNewConfirmation(0, $memo['providerptr'], 'tblprovider', $token, $dueDate, $tokenExpirationDate, $memoNumber);
			$confirmationIds[] = $confid;
			$noResponse = generateResponseURL($bizptr, $provider, "confirm-memo.php?memo=$memoNumber&action=decline&confid=$confid", false, $tokenExpirationDate, false);
if(dbTEST('comfycreatures')) logError("MEMO noResponse: ".print_r($noResponse, 1)." confids: ".print_r($confirmationIds, 1));
	//echo "YES RESPONSE: $yesResponse\nNO RESPONSE: $noResponse\n";	
			//modifyToken($token, array('url'=>"confirm-memo.php?memo=$memoNumber&action=decline&confid=$confid"));
			return "Please click one of these links to <a href='$yesResponse'>Confirm</a> or <a href='$noResponse'>Decline</a> this change.";
		}
	}
	return '';
}

function providerAcceptsMemosAbout($provider, $note, $confirmation=false) {
	$notificationType = explode('|', $note);
	$notificationType = $notificationType[0];
	
	// KLUDGE
	if($notificationType == 'homesafe') return !$confirmation; // <=== Is this correct?!! Do we really want to negate, or make it FALSE?
	// END KLUDGE
	
	$relevantProperties = 
		$confirmation 
			?
				explodePairsLine(
					'visit|confirmApptModificationsProvider||'
					.'reassignvisit|confirmApptModificationsProvider||reassignvisits|confirmApptModificationsProvider||'
					.'deletevisit|confirmApptCancellationsProvider||'
					.'cancelvisit|confirmApptCancellationsProvider||cancelvisits|confirmApptCancellationsProvider||'
					.'uncancelvisit|confirmApptReactivationsProvider||uncancelvisits|confirmApptReactivationsProvider||'
					.'schedule|confirmSchedulesProvider')
			:
				explodePairsLine(
					'visit|autoEmailApptChangesProvider||'
					.'reassignvisit|autoEmailApptChangesProvider||reassignvisits|autoEmailApptChangesProvider||'
					.'deletevisit|autoEmailApptCancellationsProvider||'
					.'cancelvisit|autoEmailApptCancellationsProvider||cancelvisits|autoEmailApptCancellationsProvider||'
					.'uncancelvisit|autoEmailApptReactivationsProvider||uncancelvisits|autoEmailApptReactivationsProvider||'
					
					.'schedule|autoEmailScheduleChangesProvider');

//echo "PROVIDER: $provider\n";				
//echo "notificationType: $notificationType\n";				
//echo "relevantProperty: {$relevantProperties[$notificationType]}\n";				
//require_once 'preference-fns.php';echo "... value: ".getProviderPreference($provider, $relevantProperties[$notificationType])."\n";				
//echo "SESSION PREFS: ".print_r($_SESSION["preferences"],1)."\n";				

	if(isset($relevantProperties[$notificationType]))	{
		require_once 'preference-fns.php';		

		if(!getProviderPreference($provider, $relevantProperties[$notificationType]))
			return false;
	}
	return true;
}

function memoSubjectStartTime($memo) {
	$parts = explode('|', $memo['note']);
	if(strpos($memo['note'], 'visit|') === 0 ||
		 strpos($memo['note'], 'cancelvisit|') !== FALSE ||
		 strpos($memo['note'], 'reassignvisit|') !== FALSE) {
		$start = fetchRow0Col0(
			"SELECT CONCAT_WS(' ', date, starttime) 
				FROM tblappointment 
				WHERE appointmentid = {$parts[1]} LIMIT 1", 1);
	}
	else if(strpos($memo['note'], 'schedule|') === 0) {
		require_once "service-fns.php";
		$package = getPackage($parts[1]);
		$start = "{$package['startdate']} 00:00:00";
	}
	else if(strpos($memo['note'], 'cancelvisits|') !== FALSE)  // covers cancelvisit and uncancelvisit
		$start = $parts[2]." 00:00:00";
	else if(strpos($memo['note'], 'reassignvisits|') !== FALSE) {
		$start = null; // there is no date info
	}
	else if(strpos($memo['note'], 'deletevisit|') !== FALSE) {
		$visitFields = array();
		foreach(explode(',', $parts[2]) as $piece) {
			$pair = explode('=', $piece);
			$visitFields[$pair[0]] = $pair[1];
		}
		$start = "{$visitFields['date']} {$visitFields['starttime']}";
	}
	return $start;
}

function memoSMS($memo) {
	if(!$memo['preprocess']) return $memo['note'].'<p>';
	if($memo['clientptr']) $client = getOneClientsDetails($memo['clientptr']);
	$parts = explode('|', $memo['note']);
	if($parts[0] == 'homesafe') return $parts[1];
	if(strpos($memo['note'], 'schedule|') === 0) {
		require_once "service-fns.php";
		$package = getPackage(substr($memo['note'], strlen('schedule|')));
		if($package) return packageSMSDescription($package, $client['clientname'], $alsoExclude='packageprice');
		else return "The schedule ["
									.substr($memo['note'], strlen('schedule|'))
									."] referred to no longer exists.  Please ignore this change.<br>";
	}
	else if(strpos($memo['note'], 'visit|') === 0) {
		$appointmentDescription = appointmentSMSDescription(substr($memo['note'], strlen('visit|')), $package=null, $returnNull=true);
		if(!$appointmentDescription) return '';
		return "Visit {$client['clientname']} CHANGED.\n$appointmentDescription";
	}
	else if(strpos($memo['note'], 'cancelvisit|') !== FALSE) {  // covers cancelvisit and uncancelvisit
		$appointmentDescription = appointmentSMSDescription(substr($memo['note'], strpos($memo['note'], '|')+1), $package=null, $returnNull=true);
		if(!$appointmentDescription) return '';
		$status = strpos($appointmentDescription, "Status: Canceled") ? 'CANCELED' : 'UNCANCELED';
		return "Visit {$client['clientname']} $status:\n$appointmentDescription";
	}
	else if(strpos($memo['note'], 'cancelvisits|') !== FALSE) {  // covers cancelvisit and uncancelvisit
		$status = strtoupper($parts[0] == 'cancelvisits' ? 'CANCELED' : 'UNCANCELED');
		$plurals = $parts[1] == 1 ? array('', 'has') :  array('s', 'have');
		//$date = shortDate(strtotime($parts[2]));
		// Holly Dobel	2019-06-28 15:07:45	2|2019-07-04,2019-07-05
		$dateArray = array();
		foreach(explode(',',$parts[2]) as $i =>$date)
			$dateArray[] = shortDate(strtotime($date));
		$date = join(', ', $dateArray);
		return "{$parts[1]} visit{$plurals[0]} to {$client['clientname']} on $date $status";
	}
	else if(strpos($memo['note'], 'reassignvisit|') !== FALSE) {
		$appointmentDescription = appointmentSMSDescription(substr($memo['note'], strpos($memo['note'], '|')+1), $package=null, $returnNull=true);
		if(!$appointmentDescription) return '';
		return "Visit {$client['clientname']} REASSIGNED:<br>$appointmentDescription";
	}
	else if(strpos($memo['note'], 'reassignvisits|') !== FALSE) {  
		if($parts[2] == 'from') $note = "{$parts[1]} of your scheduled visits to {$client['clientname']} reassigned.";
		else $note = "{$parts[1]} visits to {$client['clientname']} REASSIGNED to you.";
		return $note;
	}
	else if(strpos($memo['note'], 'deletevisit|') !== FALSE) {
		$visitFields = array();
		foreach(explode(',', $parts[2]) as $piece) {
			$pair = explode('=', $piece);
			$visitFields[$pair[0]] = $pair[1];
		}
		$appointmentDescription = deletedAppointmentSMSDescription($visitFields);
		return "Visit {$visitFields['clientname']} CANCELED (deleted):\n$appointmentDescription";
	}
}
		
function memoNeedsConfirmation($memo) {
	if(!$memo['preprocess']) return true;
	if(strpos($memo['note'], 'tipnotice|') === 0) return false;
	return true;
}

function memoFragment($memo) {
	if(!$memo['preprocess']) return $memo['note'].'<p>';
	if($memo['clientptr']) $client = getOneClientsDetails($memo['clientptr']);
	$parts = explode('|', $memo['note']);
	// [0]=visit,cancelvisit,etc, [1]=if "1", singular, [2]=date, 
	// or [0]=homesafe, [1]=note
	if(strpos($memo['note'], 'schedule|') === 0) {
		require_once "service-fns.php";
		$package = getPackage(substr($memo['note'], strlen('schedule|')));
		if($package) return packageDescriptionHTML($package, $client['clientname'], $alsoExclude='packageprice');
		else return "The schedule ["
									.substr($memo['note'], strlen('schedule|'))
									."] referred to no longer exists.  Please ignore this change.<br>";
	}
	else if(strpos($memo['note'], 'visit|') === 0) {
		$appointmentDescription = appointmentDescriptionHTML(substr($memo['note'], strlen('visit|')), $package=null, $returnNull=true);
		if(!$appointmentDescription) return '';
		$appointmentDescription = "\n<div class='descriptionbox'><b>Visit Description:</b><p>$appointmentDescription</div>\n<p>";
		return "This visit for {$client['clientname']} has <b>CHANGED<b>:<br>$appointmentDescription";
	}
	else if(strpos($memo['note'], 'cancelvisit|') !== FALSE) {  // covers cancelvisit and uncancelvisit
		$appointmentDescription = appointmentDescriptionHTML(substr($memo['note'], strpos($memo['note'], '|')+1), $package=null, $returnNull=true);
		if(!$appointmentDescription) return '';
		$status = strpos($appointmentDescription, "Canceled ") ? 'CANCELED' : 'UNCANCELED';
		$appointmentDescription = "\n<div class='descriptionbox'><b>Visit Description:</b><p>$appointmentDescription</div>\n<p>";
		return "This visit for {$client['clientname']} has been <b>$status</b>:<br>$appointmentDescription";
	}
	else if(strpos($memo['note'], 'cancelvisits|') !== FALSE) {  // covers cancelvisit and uncancelvisit
		$status = strtoupper($parts[0] == 'cancelvisits' ? 'CANCELED' : 'UNCANCELED');
		$plurals = $parts[1] == 1 ? array('', 'has') :  array('s', 'have');
		// 11/28/2018 - expanded to allow for visits across multiple days
		if(strpos($parts[2], ',')) { // multiple dates
			$dateArray = explode(',', $parts[2]);
			foreach($dateArray as $day) $date[] = shortDate(strtotime($day));
			$dates = ':<br>'.join(', ', $date);
			$date = "the following dates";
		}
		else $date = shortDate(strtotime($parts[2])); // single date
		return "{$parts[1]} visit{$plurals[0]} for {$client['clientname']} on $date {$plurals[1]} been <b>$status</b>$dates.<br>";
	}
	else if(strpos($memo['note'], 'reassignvisit|') !== FALSE) {
		$appointmentDescription = appointmentDescriptionHTML(substr($memo['note'], strpos($memo['note'], '|')+1), $package=null, $returnNull=true);
		if(!$appointmentDescription) return '';
		$appointmentDescription = "\n<div class='descriptionbox'><b>Visit Description:</b><p>$appointmentDescription</div>\n<p>";
		return "This visit for {$client['clientname']} has been <b>REASSIGNED</b>:<br>$appointmentDescription";
	}
	else if(strpos($memo['note'], 'reassignvisits|') !== FALSE) {  
		if($parts[2] == 'from') $note = "{$parts[1]} of your scheduled visits to {$client['clientname']} have been reassigned.<br>";
		else $note = "{$parts[1]} visits to {$client['clientname']} have been <b>REASSIGNED</b> to you.<br>";
		return $note;
	}
	else if(strpos($memo['note'], 'deletevisit|') !== FALSE) {
		$visitFields = array();
		foreach(explode(',', $parts[2]) as $piece) {
			$pair = explode('=', $piece);
			$visitFields[$pair[0]] = $pair[1];
		}
		$appointmentDescription = deletedAppointmentDescriptionHTML($visitFields);
		$appointmentDescription = "\n<div class='descriptionbox'><b>Visit Description:</b><p>$appointmentDescription</div>\n<p>";
		return "This visit for {$visitFields['clientname']} has been <b>CANCELED<b> (deleted):<br>$appointmentDescription";
	}
	else if($parts[0] == 'homesafe') {
		return $parts[1];
	}
	else if($parts[0] == 'tipnotice') {
		return $parts[1];
	}
}
		
function makeClientScheduleChangeMemo($provptr, $client, $packageidOrPackageHistory) {
	// find older versions of package
	if(!is_array($packageidOrPackageHistory)) {
		$recurring = 'tblrecurringpackage' == fetchRow0Col0("SELECT tablename FROM tblpackageid WHERE packageid = $packageidOrPackageHistory LIMIT 1");
		$history = findPackageIdHistory($packageidOrPackageHistory, $client, $recurring);
	}
	else $history = $packageidOrPackageHistory;
	if(!$history) return; // SHOULD NOT HAPPEN
	$packageid = $history[count($history)-1];
	$note = "schedule|$packageid";
	$tests = array();
	foreach($history as $version) $tests[] = "schedule|$version";
	if($tests) updateTable('tblprovidermemo', array('note'=>$note), "note IN ('".join("', '", $tests)."')", 1);
	makeProviderMemo($provptr, $note, $client, 1);
}

function makeClientVisitChangeMemo($provptr, $client, $visitptr) {
	if(visitInThePast($visitptr)) return;
	$note = "visit|$visitptr";
	makeProviderMemo($provptr, $note, $client, 1);
}

function makeClientVisitReassignmentMemo($provptr, $client, $visitptr) {
	if(visitInThePast($visitptr)) return;
	$note = "reassignvisit|$visitptr";
	makeProviderMemo($provptr, $note, $client, 1);
}

function makeClientVisitsReassignmentMemo($provptr, $client, $numvisits=null, $from=0) {
	$note = "reassignvisits|$numvisits".($from ? '|from' : '|to');
	makeProviderMemo($provptr, $note, $client, 1);
}

function makeClientVisitStatusChangeMemo($provptr, $clientptr, $visitptr, $canceled=false) {
	if(visitInThePast($visitptr)) return;
	$status = $canceled ? 'cancelvisit' : 'uncancelvisit';
	$note = "$status|$visitptr";
	makeProviderMemo($provptr, $note, $clientptr, 1);
}

function makeClientVisitDeletionMemo($visitptr, $provptr=null) {
	if(visitInThePast($visitptr)) return;
	$appt = getAppointment($visitptr);
	$provptr = $provptr ? $provptr : $appt['providerptr'];
	foreach(array('date','starttime','timeofday','servicecode') as $field) 
		$visitFields[] = "$field={$appt[$field]}";
	$visitFields[] = "clientname={$appt['client']}";
	$visitFields = join(',', $visitFields);
	$note = "deletevisit|$visitptr|$visitFields";
	makeProviderMemo($provptr, $note, $appt['clientptr'], 1);
}

function makeClientVisitsStatusChangeMemo($provptr, $clientptr, $numvisits, $canceled=false, $dates) {
	// 11/28/2018 - expanded to allow for visits across multiple days
	$status = $canceled ? 'cancelvisits' : 'uncancelvisits';
	$note = "$status|$numvisits|$dates";
	makeProviderMemo($provptr, $note, $clientptr, 1);
}

function visitsInThePast($visitptrs) {
	require_once "appointment-fns.php";
	$appts = fetchAssociations("SELECT date, starttime FROM tblappointment WHERE appointmentid IN ($visitptrs)");
	foreach($appts as $appt) if(appointmentFuturity($appt) >= 0) return false;
	return true;
}

function visitInThePast($visitptr) {
	require_once "appointment-fns.php";
	$appt = fetchFirstAssoc("SELECT date, starttime FROM tblappointment WHERE appointmentid = $visitptr");
	return appointmentFuturity($appt) < 0;
}


// TBD (maybe): add 'wait' boolean to tblprovidermemo and 
// GUI to allow manager to pause all memos to a provider, and then release them all at once.
// a visual reminder would keep manager aware of "paused" providers