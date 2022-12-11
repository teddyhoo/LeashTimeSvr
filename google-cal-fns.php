<? // google-cal-fns.php

//set_include_path('/var/www/prod:/usr/share/php:/usr/share/pear:');
/*set_include_path(get_include_path().':/var/www/prod/ZendGdata-1.11.6/library:');

require_once 'Zend/Loader.php';
Zend_Loader::loadClass('Zend_Gdata');
Zend_Loader::loadClass('Zend_Gdata_AuthSub');
Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
Zend_Loader::loadClass('Zend_Gdata_Calendar');


$user = 'mmlinden@gmail.com';
$pass = 'sylvain2';
$service = Zend_Gdata_Calendar::AUTH_SERVICE_NAME; // predefined service name for calendar

$client = Zend_Gdata_ClientLogin::getHttpClient($user,$pass,$service);

//outputCalendarList($client);
$gdataCal = getCalFromEmail($client, 'mmlinden@gmail.com'));
*/


function providerAcceptsGoogleCalendarEvents($providerOrId) {
	if(!getPreference('allowSittersToUseGoogleCalendar')) return false;
	if($providerOrId == -1) $userid = $_SESSION['auth_user_id'];
	else if(!is_array($providerOrId))
		$userid = fetchRow0Col0("SELECT userid FROM tblprovider WHERE active AND providerid = $providerOrId");
	else $userid = $providerOrId['active'] ? $providerOrId['userid'] : null;
	if(!$userid) return false;
	if($providerOrId != -1) {
		$allowed = getPreference('googleCalendarEnabledSitters');
		if(!in_array($userid, explode(',', $allowed))) return false;
	}
	return getGoogleCreds($userid);
}
	
function updateProviderCalendarsForDates($providerIdString, $start, $end, $unassigned=null) {
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));

	/*
	$providerIdString  $prov
	0									 0          All
	-1								 0					Unassigned
	N									 N					One provider
	N,N,..						 Array			Many providers
	*/

	$prov = $providerIdString == -1 ? "0" : $providerIdString;

	$problemsClearing = clearAllGoogleItems($start, $end, $providerIdString);

	$sendUnassigned = $unassigned || $providerIdString == -1;
	if($sendUnassigned)
		$sendUnassigned = getManagerIdsForUnassignedGoogleItems();

	$filter = $providerIdString ? "AND providerptr IN ($prov)" : '';
	
	$appts = fetchAssociations("SELECT tblappointment.*, p.userid, 
																CONCAT_WS(' ', c.lname, c.fname) as clientname,
																IFNULL(nickname, CONCAT_WS(' ', p.lname, p.fname)) as nickname
															FROM tblappointment 
															LEFT JOIN tblclient c ON clientid = clientptr
															LEFT JOIN tblprovider p ON providerid = providerptr
															WHERE date >= '$start' AND date <= '$end' $filter");
	if(!$appts) {
		if($problemsClearing) $msg[] = "Problems clearing some calendar items."; 
		$msg[] = "Calendar".($prov ? '' : 's')." updated.";
		return $msg;
	}
	foreach($appts as $appt) {
		if($appt['userid']) $byProvider[$appt['userid']][] = $appt;
		else if(!$appt['providerptr']) $byProvider[0][] = $appt; // Unassigned, to manager
		else $byProvider['NODESTINATION'.$appt['providerptr']][] = $appt; // nowhere to send
	}
				

	$allowed = getPreference('googleCalendarEnabledSitters');
	$sent = $unsendable = $unready = $ready = 0;
	foreach($byProvider as $userptr => $items) {
		$providerFailed = false;
		if(((int)$userptr != $userptr) 
				|| (!$userptr && !$sendUnassigned) 
				|| ($userptr && !in_array($userptr, explode(',', $allowed)))
				|| ($userptr && !getGoogleCreds($userptr))) {
	//echo "SEND: [$userptr][$sendUnassigned][$allowed]\n\n";
			$providerFailed = 'NODESTINATION';
		}
		else if(!$userptr && $sendUnassigned) {
	//echo "[U$sendUnassigned](".print_r($items, 1).") ";
			foreach($sendUnassigned as $mgruserptr) {
				if(is_string(pushItemsToUserCalendar($items, $mgruserptr, $role='O'))) {
					$badmgrptrs[] = $userptr;
					$unsendable += count($items);
					$unready += 1;
				}
				else {
					$ready += 1;
					$anyUnassignedSent = 1;
				}
			}
			if($anyUnassignedSent) $sent += count($items);
		}
		else {
	//echo "[$userptr] ";
			if(is_string(pushItemsToUserCalendar($items, $userptr, $role='P'))) $providerFailed = true;
			else {
				$sent += count($items);
				$ready += 1;
			}
		}
		if($providerFailed) {
			if($providerFailed === true) $badproviderptrs[] = $userptr;
			$unsendable += count($items);
			$unready += 1;
		}
	}
	if($badproviderptrs) {
		$badproviders = 
			join(', ', fetchCol0(
				"SELECT CONCAT_WS(' ', fname, lname) 
					FROM tblprovider 
					WHERE userid IN (".join(',', $badproviderptrs).")"));
			}
	if($badmgrptrs) $badmanagers = getManagerNames($badmgrptrs);

	$unsendable = !$unsendable ? null : ($unsendable == 1 ? "1 visit" : ((string)$unsendable)." visits");
	$unready = $unready == 1 ? "1 calendar" : ((string)$unready)." calendars";
	$sent = $sent == 1 ? "1 visit" : ((string)$sent)." visits";
	$ready = $ready == 1 ? "1 calendar" : ((string)$ready)." calendars";
	if($unsendable) $msg[] = "$unsendable could not be sent to $unready";
	if($badproviders) $msg[] = "These sitters have invalid Google credentials: $badproviders";
	if($badmanagers) $msg[] = "These managers have invalid Google credentials: $badmanagers";
	$msg[] = "$sent were sent to $ready $ALLSENT";
global $transactionTimes;
if(true) 	$msg[] = "Total Google transaction time: ".sprintf("%.2f", array_sum((array)$transactionTimes));
	return $msg;
}

function getManagerNames($userids) {
		list($db_local, $dbhost_local, $dbuser_local, $dbpass_local) = array($db, $dbhost, $dbuser, $dbpass);
		include "common/init_db_common.php";
		$users = fetchCol0(
			"SELECT CONCAT_WS(' ', fname, lname) 
				FROM tbluser 
				WHERE bizptr = {$_SESSION['bizptr']} AND rights LIKE 'o-%' AND userid IN (".join(',', $userids).")");
		list($db, $dbhost, $dbuser, $dbpass) = array($db_local, $dbhost_local, $dbuser_local, $dbpass_local);
		include "common/init_db_petbiz.php";
		return $users;
}

function getGoogleCalCredsTokenForGoogleUser($username) {
	
/*if(mattOnlyTEST()) {
	foreach(fetchAssociations("SELECT * FROM tbluserpref WHERE property LIKE 'googlecreds'") as $e)
		echo lt_decrypt($e['value'])."<br>";
	exit;
}*/
	$result = doQuery("SELECT * FROM tbluserpref WHERE property = 'googlecreds'");
	$username = strtoupper("$username");
  while($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
		$row['value'] =  lt_decrypt($row['value']);
		$googleCreds = explode('#*SEPR*#', $row['value']);
		
		if(strtoupper($googleCreds[0]) == $username)
			return $googleCreds[1];
	}
}
	
function getGoogleCreds($userptr) {
	if(!($googleCreds = getUserPreference($userptr, 'googlecreds', 1))) return;
	$googleCreds = explode('#*SEPR*#', $googleCreds);
	return array('username'=>$googleCreds[0], 'password'=>$googleCreds[1], 'userid'=>$userptr);
}

function saveGoogleCreds($userptr, $username, $password) {
	setUserPreference($userptr, 'googlecreds', "$username#*SEPR*#$password", 1);
}

function dropGoogleCreds($userptr) {
	setUserPreference($userptr, 'googlecreds', null);
	//deleteTable('tblusergooglevisit', "userptr = $userptr", 1);
}

function pushItemsToUserCalendar($items, $userptr, $role=null) {
	$googleCreds = getGoogleCreds($userptr);
	$role = $role ? $role : 'U';
	$visitptrs = array(0);
	foreach($items as $item) {
		$visitptrs[] = $item['appointmentid'];
		$dates[$item['appointmentid']] = $item['date'];
	}
	$removables = fetchKeyValuePairs(
		"SELECT visitptr, googleurl 
		 FROM tblusergooglevisit 
		 WHERE userptr = $userptr
		   AND visitptr IN (".join(',', $visitptrs).")", 1);	
	$urls = pushItemsToUserCalendarWithCreds($items, $googleCreds, $removables);
	if(is_string($urls)) return $urls;
	foreach($urls as $visitptr => $url) {
		$ugv = array('userptr'=>$userptr, 'visitptr'=>$visitptr, 'role'=>$role, 'googleurl'=>$url);
		if(userGoogleVisitHasDateColumn()) $ugv['visitdate'] = $dates[$visitptr];
		replaceTable('tblusergooglevisit', $ugv, 1);
	}
	logChange($userptr, 'tblusergooglevisit', 'g', count($items).' visits');
}


function userGoogleVisitHasDateColumn() {
	// added visitdate column 7/19/2014.  check to see if this database has that column
	// visitdate was added so that calendar visits in a date range could be deleted even when the LT visits had been deleted
	static $cols;
	if(!$cols) $cols = fetchAssociationsKeyedBy("DESCRIBE tblusergooglevisit ", 'Field');
	return $cols['visitdate'];
}
	
function clearAllGoogleItems($start, $end, $provIds=null) {
	// CALL FROM PROV SCHED (PROV OR -1)
	// CALL FROM HOMEPAGE (PROV is null)
	$userids = array();
	if($provIds == -1) $userids = getManagerIdsForUnassignedGoogleItems();
	else if($provIds) {
		if(is_array($provIds)) $provIds = join(',', $provIds);
		$sql = "SELECT providerid, userid FROM tblprovider WHERE providerid IN ($provIds)";
		$userids = fetchKeyValuePairs($sql);
	}
	$userids = $userids ? "AND userptr IN (".join(',', $userids).")" : '';
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));
	
	if(userGoogleVisitHasDateColumn())
	// allow tblappointment.date to be used when visitdate is NULL -- for transitional period
		$sql = "SELECT tblusergooglevisit.*, a.date
			FROM tblusergooglevisit
			LEFT JOIN tblappointment a ON appointmentid = visitptr
			WHERE ((visitdate IS NOT NULL AND visitdate >= '$start' AND visitdate <= '$end')
						 OR (visitdate IS NULL AND a.date >= '$start' AND a.date <= '$end'))			
				$userids";
	/* NO LONGER APPLICABLE 11/11/2014 */else $sql = "SELECT tblusergooglevisit.*, date
			FROM tblusergooglevisit
			LEFT JOIN tblappointment ON appointmentid = visitptr
			WHERE date >= '$start' AND date <= '$end' $userids";
	
	$lists = fetchAssociationsGroupedBy($sql, 'userptr');
	for($day = $start; $day <= $end; $day = date('Y-m-d', strtotime("+1 day", strtotime($day))))
		$negDates[] = 0 - date('Ymd', strtotime($day));
	if($negDates) {
		$cancelItems = fetchAssociationsGroupedBy(
			"SELECT * FROM tblusergooglevisit WHERE visitptr IN (".join(',', $negDates).") $userids", 'userptr');
		foreach($cancelItems  as $userptr => $items)
			foreach($items as $item)
				$lists[$userptr][] = $item;
	}
//print_r($negDates );
	foreach($lists as $userptr => $items) {
		$googleCreds = getGoogleCreds($userptr);
		if($googleCreds && is_string(clearGoogleItemsForUser($items, $googleCreds))) {
				$baduserptrs[] = $userptr;
		}
		$deletions = 0;
		foreach($items as $item) {
			deleteTable('tblusergooglevisit', "userptr = $userptr AND visitptr = {$item['visitptr']}", 1);
			if($item['visitptr'] > 0) // do not include "cancel" notices in the count
				$deletions += mysqli_affected_rows();
		}
		if($items) logChange($userptr, 'tblusergooglevisit', 'd', "$deletions visits");
	}
	return $baduserptrs;
}

function recentCalendarActivity($prov, $entries=3) {
	$userptr = is_array($prov) ? $prov['userid'] : 0;
	if(!$userptr) {
		$prov = is_array($prov) ? $prov['providerid'] : $prov;
		$userptr = fetchRow0Col0("SELECT userid FROM tblprovider WHERE providerid = $prov LIMIT 1");
	}
	if(!$userptr) return null;
	$rows = fetchAssociations(
		"SELECT * 
			FROM tblchangelog 
			WHERE itemptr = $userptr 
			  AND itemtable = 'tblusergooglevisit'
			  ORDER BY `time` DESC
			  LIMIT $entries");
	foreach($rows as $row) {
		$dateFrame = dateFrame($row['time']);  // today, last, next, this, full  field-utils.php
		$time = strtotime($row['time']);
		$line = $dateFrame == 'today' ? '' : (
						$dateFrame == 'last' ? date('l', $time) : (
						$dateFrame == 'this' ? 	shortNaturalDate($time, 'noYear') : shortNaturalDate($time)));
		if($line) $line .= ' ';
		$line .= date('g:i a ', $time).changeOperationLabel($row).' '.$row['note'];
		$lines[] = $line;
	}
	return join('<br>', (array)$lines);
}
						
function getManagerIdsForUnassignedGoogleItems() {
	$targets = fetchCol0("SELECT userptr FROM tbluserpref WHERE property = 'pushUnassignedToGoogleCalendar' AND value = 1");
	if($targets) return fetchCol0(
		"SELECT userptr 
			FROM tbluserpref 
			WHERE property = 'googlecreds' AND value IS NOT NULL AND value != ''
						AND userptr IN (".join(',', $targets).")");
}

function sitterShortName($item) {
	return $item['nickname'] ? $item['nickname'] : (
									 $item['lname'] ? "{$item['fname']} {$item['lname']}" : 
									 "no sitter assigned");
}

function getGoogleVisitEventForUser($visitptr, $userid) {
	return fetchFirstAssoc("SELECT * FROM tblusergooglevisit WHERE userptr = $userid AND visitptr = $visitptr");
}

/* UNUSED? */ function deleteAppointmentEventForVisit($gdataCal, $visitId, $userid) {
	$event = getGoogleVisitEventForUser($visitptr, $userid);
	if(!$event) return;
	$eventEditUrl = $event['googleurl'];
	deleteAppointmentEvent($gdataCal, $eventEditUrl);
}

function httpRequest($host, $port, $method, $path, $params) {
  // Params are a map from names to values
  // ex: $resp = httpRequest("www.acme.com",
  //  80, "POST", "/userDetails",
  //  array("firstName" => "John", "lastName" => "Doe"));
  $paramStr = "";
  foreach ($params as $name => $val) {
    $paramStr .= $name . "=";
    $paramStr .= urlencode($val);
    $paramStr .= "&";
  }

  // Assign defaults to $method and $port, if needed
  if (empty($method)) {
    $method = 'GET';
  }
  $method = strtoupper($method);
  if (empty($port)) {
    $port = 80; // Default HTTP port
  }

  // Create the connection
  $sock = fsockopen($host, $port);
  if ($method == "GET") {
    $path .= "?" . $paramStr;
  }
  myfputs($sock, "$method $path HTTP/1.1\r\n");
  myfputs($sock, "Host: $host\r\n");
  myfputs($sock, "Content-type: " .
               "application/x-www-form-urlencoded\r\n");
  if ($method == "POST") {
    myfputs($sock, "Content-length: " . 
                 strlen($paramStr) . "\r\n");
  }
  myfputs($sock, "Connection: close\r\n\r\n");
  if ($method == "POST") {
    myfputs($sock, $paramStr);
  }

  // Buffer the result
  $result = "";
  while (!feof($sock)) {
    $result .= fgets($sock,1024);
  }

  fclose($sock);
  return $result;
}

function setGoogleAccessToken($userid, $token) {
	$user = getGoogleCreds($userid);
	saveGoogleCreds($userid, $user['username'], $token);
}

  function sendPOSTviaCurl($postURL, $port, $params) {
   // helper function demonstrating how to send the xml with curl


    $ch = curl_init(); // Initialize curl handle
    curl_setopt($ch, CURLOPT_URL, $postURL); // Set POST URL

    $headers = array();
    $headers[] = "Content-type: text/xml";
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // Add http headers to let it know we're sending XML
		$paramStr = "";
		foreach ($params as $name => $val) {
			$val = urlencode($val);
			$params[$name] = "$name=$val";
		}
		$paramStr = join('&', $params);
//echo "<hr><pre>$paramStr</pre></hr>";
    curl_setopt($ch, CURLOPT_FAILONERROR, 1); // Fail on errors
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // Allow redirects
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Return into a variable
    curl_setopt($ch, CURLOPT_PORT, $port); // Set the port number
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Times out after 15s
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $paramStr); // Add XML directly in POST

    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);


    // This should be unset in production use. With it on, it forces the ssl cert to be valid
    // before sending info.
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

    if (!($data = curl_exec($ch))) {
        print  "curl error =>" .curl_error($ch) ."\n";
        throw New Exception(" CURL ERROR :" . curl_error($ch));

    }
    curl_close($ch);

    return $data;
  }




function myfputs($sock, $str) {
	echo $str;
	return fputs($sock, $str);
}


// *************************************************************************************	
// *************************************************************************************
function getCalendarServiceFor($googleCreds) {
	if(!$googleCreds) return;
	if(!is_array($googleCreds)) $googleCreds = getGoogleCreds($googleCreds);
	$client = getGoogleClientWithCreds($googleCreds);
	if(!$client) return "Could not access client calendar.";
	$service = new Google_Service_Calendar($client);
	if(!$service) return "Could not access calendar service for client.";
	return $service;
}
		

function getGoogleClientForUser($userid) {
	$googleCreds = getGoogleCreds($userid);
	return getGoogleClientWithCreds($googleCreds);
}

function getGoogleClientWithCreds($googleCreds) {
	global $installationSettings;
	require_once realpath('google-api-php-client-master/autoload.php');

	/************************************************
		We create the client and set the simple API
		access key. If you comment out the call to
		setDeveloperKey, the request may still succeed
		using the anonymous quota.
	 ************************************************/
	 
	$client = new Google_Client();
	$client->setClientId($installationSettings['googleDevClientID']);
	$client->setClientSecret($installationSettings['googleDevClientSecret']);
	$redirectURL = str_replace('LeashTime', 'leashtime', globalURL("oauth2callback.php"));
	$client->setRedirectUri($redirectURL);
	$client->addScope("https://www.googleapis.com/auth/calendar");

	//echo "setRedirectUri: $redirectURL<hr>";

	$savedToken = $googleCreds['password'];
	if(strpos($savedToken, "CODE:") === 0) {
		$savedToken = substr($savedToken, strlen("CODE:"));
		try{$client->authenticate($savedToken);}
		catch (Exception $e) {return $e->getMessage();}
		setGoogleAccessToken($googleCreds['userid'], $client->getRefreshToken());
	}
//print_r($googleCreds);		
	if($savedToken) { // refresh
		try{$client->refreshToken($savedToken);}
		catch (Exception $e) {return $e->getMessage();}
	}
	return $client;
}

function testUser($googleCreds) {
	if($version == 'ZEND') return testUserZEND($googleCreds);
	$client = getGoogleClientWithCreds($googleCreds);
	if(is_string($client)) return $client;
	
	$service = new Google_Service_Calendar($client);
	try {
		$calendar = $service->calendarList->get('primary'); //->listCalendarList()
	}
	catch (Exception $e) {
		return $error = $e->getMessage();
	}
}


function clearGoogleItemsForUser($googleitems, $googleCreds) {
	if($version == 'ZEND') return clearGoogleItemsForUserZEND($googleitems, $googleCreds);
	$service = getCalendarServiceFor($googleCreds);
	foreach($googleitems as $googleitem) {
		try {
global $transactionTimes;
$start = microtime(1);
			$service->events->delete("primary", $googleitem['googleurl']);
$transactionTimes[] = microtime(1) - $start; 
		}
		catch (Exception $e) { 
			logError('(CL2) '.$e->getMessage());
			return $e->getMessage(); 
		}
	}
}

function pushItemsToUserCalendarWithCreds($items, $googleCreds, $removables=null) {
	if($version == 'ZEND') return pushItemsToUserCalendarWithCredsZEND($items, $googleCreds, $removables);
	$service = getCalendarServiceFor($googleCreds);
	$providers = fetchAssociationsKeyedBy("SELECT * FROM tblprovider", 'providerid', 1);
	foreach($items as $item) if($item['clientptr']) $clientptrs[] = $item['clientptr'];
	$clientNames = array();
	if($clientptrs) $clientNames = fetchKeyValuePairs(
		"SELECT clientid, CONCAT_WS(' ', fname, lname) 
			FROM tblclient 
			WHERE clientid IN (".join(',', $clientptrs).")");
	
	
	foreach((array)$items as $i => $item) {
		$item['clientname'] = $clientNames[$item['clientptr']];
		if($item['canceled']) {
			$canceled[0-date('Ymd', strtotime($item['date']))][] = $item;
			unset($items[$i]);
		}
	}
	
	foreach($items as $item) {
		// COVERED BY clearAllGoogleItems
		//if($removables[$item['appointmentid']])
		//	deleteAppointmentEvent($gdataCal, $removables[$item['appointmentid']]);
		$provider = $providers[$item['providerptr']];
		$urls[$item['appointmentid']] = 
			addAppointmentEvent($service, googleCalEventForAppt($item, $providers[$item['providerptr']]));
	}
	/*
	foreach((array)$canceled as $negdate => $canceledItems) {
		$description = array();
		foreach($canceledItems as $item) {
			$shortName = sitterShortName($item);
			$description[] = "{$item['clientname']} - {$item['timeofday']} ($shortName)";
		}
		$description = "CANCELED:\n".join("\n", $description);;
		$date = (string)(0-$negdate);
		$date = substr($date, 0, 4).'-'.substr($date, 4, 2).'-'.substr($date, 6, 2);
		$urls[$item['appointmentid']] = 
			addLabelEvent($gdataCal, $date, $title='CANCELED', $description);
	}
	*/
	return $urls;
}

function deleteAppointmentEvent($gdataCal, $eventEditUrl) {
	if($version == 'ZEND') return deleteAppointmentEventZEND($gdataCal, $eventEditUrl);
}

function googleCalEventForAppt($item, $provider=null) {
	if($version == 'ZEND') return googleCalEventForApptZEND($item, $provider=null);
	
	$event = new Google_Service_Calendar_Event();

	if($item['appointmentid']) $event->setICalUID($item['appointmentid']);
	if($item['servicecode']) {
		require_once "service-fns.php";
		if(!$serviceTypes) 
			$serviceTypes = getAllServiceNamesById($refresh=0, $noInactiveLabel=true, $setGlobalVar=false);
			
		$pets = $item['pets'];
		if($pets == 'All Pets') {
			$pets = fetchCol0("SELECT name FROM tblpet WHERE ownerptr = '{$item['clientptr']}' AND active = 1 ORDER BY name");
			$pets = $pets ? join(',', $pets) : 'All Pets';
		}
		if($item['canceled']) $status = 'CANCELED: ';
		$event->setTitle("$status{$serviceTypes[$item['servicecode']]} ($pets)");
	}
	if($item['clientname']) $note[] = 'Client: '.$item['clientname'];
	$note[] = 'Sitter: '.sitterShortName($item);
	if($item['note']) $note[] = $item['note'] ;
	if($note) $event->setDescription(join("\n", $note));

	$gStartDateTime = new Google_Service_Calendar_EventDateTime();
	$gStartDateTime->setDateTime("{$item['date']} {$item['starttime']}");
	$event->setStart($startDateTime);
	
	
	if(strcmp($item['endtime'], $item['starttime']) < 0)
		$date = date('Y-m-d', strtotime("+1 day", strtotime($item['date']))); // <== Bad line! But what format should date have?
	$gEndDateTime = new Google_Service_Calendar_EventDateTime();
	$gEndDateTime->setDateTime("$date {$item['endtime']}");
	$event->setStart($gEndDateTime);
	
	if($item['providerptr']) {
		if(!$provider) {
			require_once "provider_fns.php";
			$provider = getProvider($item['providerptr']);
		}
		
		$attendee = new Google_Service_Calendar_EventAttendee();
		$attendee->setDisplayName("{$provider['fname']} {$provider['lname']}");
		$attendee->setEmail($provider['email']);
		
		$event->setAttendees(array($attendees));
	}
	return $event;
	
}

function addLabelEvent($gdataCal, $date, $title, $description) {
	if($version == 'ZEND') return addLabelEventZEND($gdataCal, $date, $title, $description);
}

function addAppointmentEvent($gdataCal, $event) {
	if($version == 'ZEND') return addAppointmentEventZEND($gdataCal, $event);
	// gdataCal is acually the Calendar service
	$event = $gdataCal->events->insert("primary", $event);
	return $event->id;
}

function createEvent($gdataCal, $title = 'Tennis with Beth',
    $desc='Meet for a quick lesson', $where = 'On the courts',
    $startDate = '2008-01-20', $startTime = '10:00',
    $endDate = '2008-01-20', $endTime = '11:00', $tzOffset = '-08') {
	if($version == 'ZEND') 
		return createEventZEND($gdataCal, $title,
   			 $desc, $where, $startDate, $startTime, $endDate, $endTime, $tzOffset);
}

function getCalFromEmail($client, $email)  {
	if($version == 'ZEND') return getCalFromEmailZEND($client, $email);
}

function outputCalendarList($client) {
	if($version == 'ZEND') return outputCalendarListZEND($client);
}


// *************************************************************************************	
// ZEND VERSION FUNCTIONS
// *************************************************************************************	
function clearGoogleItemsForUserZEND($googleitems, $googleCreds) {
	require_once 'Zend/Loader.php';
	require_once 'preference-fns.php';
	Zend_Loader::loadClass('Zend_Gdata');
	Zend_Loader::loadClass('Zend_Gdata_AuthSub');
	Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
	Zend_Loader::loadClass('Zend_Gdata_Calendar');

	$user = $googleCreds['username']; //'mmlinden@gmail.com';
	$pass = $googleCreds['password']; //'sylvain2';

	$service = Zend_Gdata_Calendar::AUTH_SERVICE_NAME; // predefined service name for calendar

	try {
		$client = Zend_Gdata_ClientLogin::getHttpClient($user,$pass,$service);
	}
	catch (Exception $e) {
		logError('(CL1) '.$e->getMessage());
		return $e->getMessage(); 
	}

	$gdataCal = getCalFromEmail($client, 'UNUSEDEMAIL');
	foreach($googleitems as $googleitem) {
		try {
global $transactionTimes;
$start = microtime(1);
			$event = $gdataCal->getCalendarListEntry($googleitem['googleurl']);
			$event->delete();
$transactionTimes[] = microtime(1) - $start; 
		}
		catch (Exception $e) { 
			logError('(CL2) '.$e->getMessage());
			return $e->getMessage(); 
		}
	}
}

function pushItemsToUserCalendarWithCredsZEND($items, $googleCreds, $removables=null) {
	set_time_limit(300);
	require_once 'Zend/Loader.php';
	require_once 'preference-fns.php';
	Zend_Loader::loadClass('Zend_Gdata');
	Zend_Loader::loadClass('Zend_Gdata_AuthSub');
	Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
	Zend_Loader::loadClass('Zend_Gdata_Calendar');

	$user = $googleCreds['username']; //'mmlinden@gmail.com';
	$pass = $googleCreds['password']; //'sylvain2';

	$service = Zend_Gdata_Calendar::AUTH_SERVICE_NAME; // predefined service name for calendar

	try {
		$client = Zend_Gdata_ClientLogin::getHttpClient($user,$pass,$service);
	}
	catch (Exception $e) { 
		logError('(P) '.$e->getMessage());
		return $e->getMessage(); 
	}

	//outputCalendarList($client);
	$gdataCal = getCalFromEmail($client, 'UNUSEDEMAIL');
	$providers = fetchAssociationsKeyedBy("SELECT * FROM tblprovider", 'providerid', 1);
	foreach($items as $item) if($item['clientptr']) $clientptrs[] = $item['clientptr'];
	$clientNames = array();
	if($clientptrs) $clientNames = fetchKeyValuePairs(
		"SELECT clientid, CONCAT_WS(' ', fname, lname) 
			FROM tblclient 
			WHERE clientid IN (".join(',', $clientptrs).")");
	
	
	foreach((array)$items as $i => $item) {
		$item['clientname'] = $clientNames[$item['clientptr']];
		if($item['canceled']) {
			$canceled[0-date('Ymd', strtotime($item['date']))][] = $item;
			unset($items[$i]);
		}
	}
	
	foreach($items as $item) {
		// COVERED BY clearAllGoogleItems
		//if($removables[$item['appointmentid']])
		//	deleteAppointmentEvent($gdataCal, $removables[$item['appointmentid']]);
		$provider = $providers[$item['providerptr']];
		$urls[$item['appointmentid']] = 
			addAppointmentEvent($gdataCal, googleCalEventForAppt($item, $providers[$item['providerptr']]));
	}
	foreach((array)$canceled as $negdate => $canceledItems) {
		$description = array();
		foreach($canceledItems as $item) {
			$shortName = sitterShortName($item);
			$description[] = "{$item['clientname']} - {$item['timeofday']} ($shortName)";
		}
		$description = "CANCELED:\n".join("\n", $description);;
		$date = (string)(0-$negdate);
		$date = substr($date, 0, 4).'-'.substr($date, 4, 2).'-'.substr($date, 6, 2);
		$urls[$item['appointmentid']] = 
			addLabelEvent ($gdataCal, $date, $title='CANCELED', $description);
	}	
	return $urls;
}

function deleteAppointmentEventZEND($gdataCal, $eventEditUrl) {
	//print_r($gdataCal->getCalendarListEntry($eventEditUrl));
	//$gdataCal->delete($eventEditUrl);
	try {
global $transactionTimes;
$start = microtime(1);
		$event = $gdataCal->getCalendarListEntry($eventEditUrl);
		$event->delete();
$transactionTimes[] = microtime(1) - $start; 
	}
	catch (Exception $e) {
		logError('(D) '.$e->getMessage());
	}
	//if($event) $event->delete();
}

function googleCalEventForApptZEND($item, $provider=null) {
	if($item['appointmentid']) $event['uid'] = $item['appointmentid'];
	if($item['servicecode']) {
		require_once "service-fns.php";
		if(!$serviceTypes) 
			$serviceTypes = getAllServiceNamesById($refresh=0, $noInactiveLabel=true, $setGlobalVar=false);
			
		$pets = $item['pets'];
		if($pets == 'All Pets') {
			$pets = fetchCol0("SELECT name FROM tblpet WHERE ownerptr = '{$item['clientptr']}' AND active = 1 ORDER BY name");
			$pets = $pets ? join(',', $pets) : 'All Pets';
		}
		if($item['canceled']) $status = 'CANCELED: ';
		$event['title'] = "$status{$serviceTypes[$item['servicecode']]} ($pets)";
	}
	if($item['clientname']) $event['note'][] = 'Client: '.$item['clientname'];
	$event['note'][] = 'Sitter: '.sitterShortName($item);
	if($item['note']) $event['note'][] = $item['note'] ;
	if($event['note']) $event['note'] = join("\n", $event['note']);;
	$date = $item['date'];
	$event['startDate'] = $date;
	$event['startTime'] = $item['starttime'];
	if(strcmp($item['endtime'], $item['starttime']) < 0)
		$date = date('Y-m-d', strtotime("+1 day", strtotime($date))); // <== Bad line! But what format should date have?
	$event['endDate'] = $date;
	$event['endTime'] = $item['endtime'];
	if($item['providerptr']) {
		if(!$provider) {
			require_once "provider_fns.php";
			$provider = getProvider($item['providerptr']);
		}
		$event['attendeeName'] = "{$provider['fname']} {$provider['lname']}";
		$event['attendeeEmail'] = $provider['email'];
	}
	return $event;
}

function addLabelEventZEND($gdataCal, $date, $title, $description)
/*$title = 'Tennis with Beth',
    $desc='Meet for a quick lesson', $where = 'On the courts',
    $startDate = '2008-01-20', $startTime = '10:00',
    $endDate = '2008-01-20', $endTime = '11:00', $tzOffset = '-08')*/
{
	global $tzOffset;
	$tzOffset = $tzOffset ? $tzOffset : -5;
	
	//$tzOffset = sprintf("%02d", $tzOffset);
	// what is wrong with sprintf?!!!
	if(abs($tzOffset) < 10) $tzOffset = ($tzOffset < 0 ? '-' : '').'0'.abs($tzOffset);
	
  $newEvent = $gdataCal->newEventEntry();
  
  
  $newEvent->title = $gdataCal->newTitle($title);
  $newEvent->where = array($gdataCal->newWhere(''));
  $newEvent->content = $gdataCal->newContent($description);
  
  $when = $gdataCal->newWhen();
  $when->startTime = $date;
  $newEvent->when = array($when);

  // Upload the event to the calendar server
  // A copy of the event as it is recorded on the server is returned
global $transactionTimes;
$start = microtime(1);
  $createdEvent = $gdataCal->insertEvent($newEvent);
$transactionTimes[] = microtime(1) - $start; 
  return $createdEvent->id->text;
}

function addAppointmentEventZEND($gdataCal, $event)
/*$title = 'Tennis with Beth',
    $desc='Meet for a quick lesson', $where = 'On the courts',
    $startDate = '2008-01-20', $startTime = '10:00',
    $endDate = '2008-01-20', $endTime = '11:00', $tzOffset = '-08')*/
{
	global $tzOffset;
	$tzOffset = $tzOffset ? $tzOffset : -5;
	
	//$tzOffset = sprintf("%02d", $tzOffset);
	// what is wrong with sprintf?!!!
	if(abs($tzOffset) < 10) $tzOffset = ($tzOffset < 0 ? '-' : '').'0'.abs($tzOffset);
	
  $newEvent = $gdataCal->newEventEntry();
  
  
  $newEvent->title = $gdataCal->newTitle($event['title']);
  $newEvent->where = array($gdataCal->newWhere(''));
  $newEvent->content = $gdataCal->newContent($event['note']);
  
  $when = $gdataCal->newWhen();
  $when->startTime = "{$event['startDate']}T{$event['startTime']}.000";//{$tzOffset}:00";
  $when->endTime = "{$event['endDate']}T{$event['endTime']}.000";//{$tzOffset}:00";
//echo  $when->startTime .'<p>'.$when->endTime;
  $newEvent->when = array($when);

  // Upload the event to the calendar server
  // A copy of the event as it is recorded on the server is returned
global $transactionTimes;
$start = microtime(1);
  $createdEvent = $gdataCal->insertEvent($newEvent);
$transactionTimes[] = microtime(1) - $start; 
  return $createdEvent->id->text;
}

function createEventZEND($gdataCal, $title = 'Tennis with Beth',
    $desc='Meet for a quick lesson', $where = 'On the courts',
    $startDate = '2008-01-20', $startTime = '10:00',
    $endDate = '2008-01-20', $endTime = '11:00', $tzOffset = '-08')
{
  $newEvent = $gdataCal->newEventEntry();
  
  $newEvent->title = $gdataCal->newTitle($title);
  $newEvent->where = array($gdataCal->newWhere($where));
  $newEvent->content = $gdataCal->newContent("$desc");
  
  $when = $gdataCal->newWhen();
  $when->startTime = "{$startDate}T{$startTime}.000{$tzOffset}:00";
  $when->endTime = "{$endDate}T{$endTime}.000{$tzOffset}:00";
  $newEvent->when = array($when);
  //$newEvent->setWho = $who? $who : array();

  // Upload the event to the calendar server
  // A copy of the event as it is recorded on the server is returned
  $createdEvent = $gdataCal->insertEvent($newEvent);
  return $createdEvent->id->text;
}


function getCalFromEmailZEND($client, $email) 
{
  $gdataCal = new Zend_Gdata_Calendar($client);
  
  return $gdataCal;
  
  /*$calFeed = $gdataCal->getCalendarListFeed();
  foreach ($calFeed as $calendar)
    if($calendar->title->text == $email)
    	return $calendar;*/
}	

function outputCalendarListZEND($client) 
{
  $gdataCal = new Zend_Gdata_Calendar($client);
  $calFeed = $gdataCal->getCalendarListFeed();
  echo '<h1>' . $calFeed->title->text . '</h1>';
  echo '<ul>';
  foreach ($calFeed as $calendar) {
    echo '<li>' . $calendar->title->text . '</li>';
    echo '<li>' . print_r($calendar, 1);
  }
  echo '</ul>';
}

function testUserZEND($googleCreds) {
	require_once 'Zend/Loader.php';
	require_once 'preference-fns.php';
	Zend_Loader::loadClass('Zend_Gdata');
	Zend_Loader::loadClass('Zend_Gdata_AuthSub');
	Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
	Zend_Loader::loadClass('Zend_Gdata_Calendar');

	$service = Zend_Gdata_Calendar::AUTH_SERVICE_NAME; // predefined service name for calendar

	try {
		$client = Zend_Gdata_ClientLogin::getHttpClient($googleCreds['username'],$googleCreds['password'],$service);
  	$gdataCal = new Zend_Gdata_Calendar($client);
		
	}
	catch (Exception $e) {
		return $error = $e->getMessage();
	}
}

