<?
// provider-fns.php
//require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";


$pfields = 'providerid,employeeid,jobtitle,labortype,noncompetesigned,active,hiredate,terminationdate,'.
             'terminationreason,nickname,fname,lname,street1,street2,city,state,zip,'.  
             'cellphone,homephone,workphone,fax,pager,email,taxid,maritalstatus,notes,emergencycontact,'.
             'paymethod,ddroutingnumber,ddaccountnumber,ddaccounttype,paynotification,ratetype,dailyvisitsemail,weeklyvisitsemail';
$providerFields = explode(',', $pfields);

function providerAvailabilitySnapShot($provid) {
	$sitter = fetchFirstAssoc("SELECT *, CONCAT_WS(' ', fname, lname) as name FROM tblprovider WHERE providerid = $provid");
	$today = date('Y-m-d', time());
	$until = date('Y-m-d', strtotime("+ 6 MONTHS"));
	$timeOffRows = getProviderTimeOff($provid, $showpasttimeoff=false, $where="date >= '$today' AND date < '$until'");
	usort($timeOffRows, 'timeOffDateSort');
	
	$primaryPhone = primaryPhoneNumber($sitter);
	foreach(explode(',', 'cellphone,homephone,workphone') as $f) {
		$phone = strippedPhoneNumber($sitter[$f]);
		if($phone) {
			if($phone == $primaryPhone) $phone = "<b>$phone</b>";
			$phones[] = "({$f[0]}) $phone";
		}
	}
	echo "<h2>{$sitter['name']}</h2>";
	if($phones) echo join(' - ', $phones)."<p>";
	echo "Notes:<p>".str_replace("\n", "<br>", str_replace("\n\n", "<p>", $sitter['notes']))."<p>";
	if($timeOffRows) echo "Scheduled Time Off<p>";
	echo "<style>
	.timeofftable td {padding-right:10px;}
	</style>";
	echo "<table class='fontSize1_2em' style='background:white;' border=1 bordercolor=black>\n";
	foreach($timeOffRows as $row) {
		$tod = $row['timeofday'] ? $row['timeofday'] : 'All Day';
		echo "<tr><td>".shortDateAndDay(strtotime($row['date']));
		echo "</td><td>$tod</td>";
		echo "</td><td>{$row['note']}</td>";
		echo "</tr>\n";
	}
	echo "</table>\n";
}

function timeOffDateSort($a, $b) {return strcmp($a['date'], $b['date']); }

function getTimeOffBlackoutId() { return -999; }

function getProviderRights($userid) {
	global $dbhost, $db, $dbuser, $dbpass;

	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	include "common/init_db_common.php";
	$rights = fetchRow0Col0("SELECT rights FROM tbluser WHERE userId = $userid LIMIT 1");
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	return $rights;
}

function setKeyManagementProviderRights($userid, $val) {
	global $dbhost, $db, $dbuser, $dbpass;
	list($db_local, $dbhost_local, $dbuser_local, $dbpass_local) = array($db, $dbhost, $dbuser, $dbpass);
	include "common/init_db_common.php";
	
	$rights = fetchRow0Col0("SELECT rights FROM tbluser WHERE userId = $userid LIMIT 1");
	$role = substr($rights, 0, 2);
	$rights = explode(',', substr($rights, 2));
	foreach($rights as $ind => $right) 
		if(in_array($right, array('ka','ki')))
			unset($rights[$ind]);
	if($val) $rights[] = $val;
	$rights = $role.join(',', $rights);
	doQuery("UPDATE tbluser SET rights = '$rights' WHERE userId = $userid");
	if(function_exists('reconnectPetBizDB')) reconnectPetBizDB($db_local, $dbhost_local, $dbuser_local, $dbpass_local);
	return $rights;
}

function getActiveClientIdsForProvider($id, $includeClientsWithoutAppointments=true, $daysAhead=null, $daysBehind=null) {
	
//if(mattOnlyTEST())	$_SESSION['preferences'] = fetchKeyValuePairs("SELECT property, value FROM tblpreference");
	
	if(!$daysAhead)
		if(!$_SESSION['preferences']['unlimitedproviderschedulelookahead']) {
			$daysAhead = $_SESSION['preferences']['sitterclientsvisitdaysahead'];
			$daysAhead = $daysAhead ? $daysAhead : 14;
		}
	if($daysAhead) $lookaheadClause = "AND date <= FROM_DAYS(TO_DAYS(CURDATE())+$daysAhead)";
	
	
	$daysBehind = $daysBehind ? $daysBehind : $_SESSION['preferences']['sitterclientsvisitdaysbehind'];
	$lookbehindClause = 
		$daysBehind ? "date >= FROM_DAYS(TO_DAYS(CURDATE())-$daysBehind)"
		: 'date >= DATE_ADD(CURDATE(),INTERVAL -1 DAY)';
		
	$clients = fetchCol0("SELECT clientid FROM tblclient WHERE active AND defaultproviderptr = $id");
	$clients = array_merge($clients, 
	  fetchCol0($sql = tzAdjustedSql("SELECT clientptr FROM tblappointment 
	             WHERE canceled IS NULL
	             AND providerptr = $id 
	             AND $lookbehindClause $lookaheadClause")));
	             // WHERE canceled IS NULL AND completed IS NULL 
	             // AND providerptr = $id 
	             // AND date >= CURDATE() AND date <= FROM_DAYS(TO_DAYS(CURDATE())+$daysAhead)")));
	             
	$clients = array_diff($clients, doNotServeClientIds($id));
	             
	return array_unique($clients);
}

function getNextAppointmentDatePerClientForProvider($id, $daysAhead=14) {
	return fetchKeyValuePairs(tzAdjustedSql("SELECT clientptr, date FROM tblappointment 
	             WHERE canceled IS NULL AND completed IS NULL 
	             AND providerptr = $id 
	             AND date >= CURDATE() AND date <= FROM_DAYS(TO_DAYS(CURDATE())+$daysAhead)
	             ORDER BY date DESC"));
}

function getProvider($id) {
  return fetchFirstAssoc("SELECT * FROM tblprovider WHERE providerid=$id LIMIT 1");
}

function getPreviousProviders($client) {
	return fetchKeyValuePairs(
		"SELECT DISTINCT providerptr, IFNULL(nickname, CONCAT_WS(' ',fname,lname)) AS label
			FROM tblappointment 
			LEFT JOIN tblprovider ON providerid = providerptr
			WHERE tblprovider.active AND clientptr=$client
			ORDER BY label");
}

function getProviders($constraint=null) {
	$whereClause = $constraint ? "WHERE $constraint" : '';
  return fetchAssociations("SELECT * FROM tblprovider $whereClause");
}

function providerNotListedReason($prov, $context=null) {
	$clientid = $context['appointmentid'] ? $context['clientptr'] : (
							$context['clientid'] ? $context['clientid'] : null);
	return 
		!$prov['active'] ? 'is inactive' : (
		in_array($clientid, doNotServeClientIds($prov['providerid'])) ?	'does not serve this client' :
		'is taking time off on this day');
}

function providerShortName($prov) {  // useful in job lists
	return $prov['nickname'] ? $prov['nickname'] : fullname($prov);
}

function getProviderShortNames($filter='') {
	//$names = fetchAssociationsKeyedBy("SELECT providerid, nickname, providerid, CONCAT_WS(' ', fname, lname) as name FROM tblprovider", 'providerid');
	//foreach($names as $id => $name) $names[$id] = providerShortName($name);
	//return $names;
	global $applySitterNameConstraintsInThisContext; // also consulted in getDisplayableProviderName()
	if($applySitterNameConstraintsInThisContext || ($_SESSION && userRole() == 'c')) {
		foreach(fetchCol0("SELECT providerid FROM tblprovider $filter") as $provid) {
			$names[$provid] = getDisplayableProviderName($provid);
			if(is_array($names[$provid])) $names[$provid] = '';
		}
		return $names;
	}
			
	return fetchKeyValuePairs("SELECT providerid, IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as name from tblprovider $filter");
}

function getProviderCompositeNames($filter='') {
	return fetchKeyValuePairs(
		"SELECT providerid, CONCAT_WS(' ', fname, lname, IF(nickname, '', CONCAT('(',nickname,')'))) from tblprovider $filter");
}

function getProviderNames($filter='') {
	return fetchKeyValuePairs("SELECT providerid, CONCAT_WS(' ', fname, lname) as name from tblprovider $filter");
}

function providerNamesCompletelySuppressed() {
	// sessionless-safe
	global $applySitterNameConstraintsInThisContext;
	if($applySitterNameConstraintsInThisContext || ($_SESSION['rights'] && $_SESSION['rights'][0] == 'c')) {
		$mode = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'clientProviderNameDisplayMode'");
		return $mode == 'none';
	}
}

function getDisplayableProviderName($provid, $overrideAsClient=false) {
	// WARNING  -- used in both session and sessionless contexts!
	// apply rules only of userRole()=='c' or $applySitterNameConstraintsInThisContext
	global $applySitterNameConstraintsInThisContext, $db;
	static $alreadyFetched, $clientProviderNameDisplayMode, $fetchedNames, $lastDb;
	if($db != $lastDb) $fetchedNames = null;
	$lastDb = $db;
	if($_SESSION && $fetchedNames && array_key_exists($provid, $fetchedNames)) 
		return $fetchedNames[$provid];
	if($_SESSION && !$alreadyFetched) {
		$notClientUser = !$_SESSION || (!$overrideAsClient && userRole() != 'c');
		$clientProviderNameDisplayMode = ($notClientUser && !$applySitterNameConstraintsInThisContext) ? '' : fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'clientProviderNameDisplayMode'");// $_SESSION['clientProviderNameDisplayMode'];
		$alreadyFetched = true; // false;
	}
	if($clientProviderNameDisplayMode == 'none') 
		return array('none'=>true);
	$sitternames = fetchFirstAssoc("SELECT fname, lname, nickname FROM tblprovider WHERE providerid = $provid LIMIT 1");
	if($clientProviderNameDisplayMode == 'nickname' || $clientProviderNameDisplayMode == 'initials') {
		$initials = "{$sitternames['fname'][0]}.{$sitternames['lname'][0]}.";
		if($clientProviderNameDisplayMode == 'initials') $sitterName = $initials;
		else if($clientProviderNameDisplayMode == 'nickname') 
			$sitterName = $sitternames['nickname'] ?  $sitternames['nickname'] : $initials;
	}
	else $sitterName = "{$sitternames['fname']} {$sitternames['lname']}";
	$fetchedNames[$provid] = $sitterName;
	return $sitterName;
}

function getProviderNicknames() {
	return fetchAssociationsKeyedBy("SELECT nickname, providerid, CONCAT_WS(' ', fname, lname) as name FROM tblprovider WHERE nickname IS NOT NULL", 'nickname');
}

function getActiveProviderSelectionsAsFlatList($availabilityDate=null, $zip=null) {
	// if zip and if $_SESSION['providerterritoriesenabled'] segregate providers in this zip and offer them first
	$providers = fetchAssociations("SELECT providerid, fname, lname, nickname FROM tblprovider WHERE active = 1");
	$providersOff = $availabilityDate ? providersOffThisDay($availabilityDate) : array();

	$selections = array();
	foreach($providers as $prov)
		if(!in_array($prov['providerid'], $providersOff))
			$selections[providerShortName($prov)] = $prov['providerid'];
	uksort($selections, 'caseInsensitiveComparison');
	return $selections;
}

function enableProviderZIPTerritories() {
"--
-- Table structure for table `relproviderzip`
--

CREATE TABLE IF NOT EXISTS `relproviderzip` (
  `providerptr` int(11) NOT NULL,
  `zip` int(11) NOT NULL,
  PRIMARY KEY (`providerptr`,`zip`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='zips the provider serves';	
";
}
function getAllProviderSelections($availabilityDate=null, $zip=null, $separateActiveFromInactive=false) {
	// if zip and if $_SESSION['providerterritoriesenabled'] segregate providers in this zip and offer them first
	$providers = fetchAssociations("SELECT providerid, fname, lname, nickname, active FROM tblprovider");
	//$providersOff = $availabilityDate ? providersOffThisDay($availabilityDate) : array();
	$localProviders = null;
	if($separateActiveFromInactive) {
		$inactiveProviders = array();
		foreach($providers as $i => $prov) {
			if(!$prov['active']) {
				$inactiveProviders[providerShortName($prov)] = $prov['providerid'];
				unset($providers[$i]);
			}
		}
		if($inactiveProviders) uksort($inactiveProviders, 'caseInsensitiveComparison');
	}
	if($providers && $zip && $_SESSION['providerterritoriesenabled']) {
		$localProviderIds = getZipProviders($zip);
		foreach($providers as $i => $prov)
			if(in_array($prov['providerid'], $localProviderIds)) {
				unset($providers[$i]);
				//if(!in_array($prov['providerid'], $providersOff))
					$localProviders[providerShortName($prov)] = $prov['providerid'];
			}
		if($localProviders) uksort($localProviders, 'caseInsensitiveComparison');
	}

	$selections = array();
	foreach($providers as $prov)
		if(!in_array($prov['providerid'], (array)$providersOff))
			$selections[providerShortName($prov)] = $prov['providerid'];
	uksort($selections, 'caseInsensitiveComparison');
	if($localProviders) $selections = array_merge(array('Local Sitters'=>$localProviders), array('Sitters'=>$selections));
	else if($_SESSION['providerterritoriesenabled'])  $selections = array('Sitters'=>$selections);
	if($inactiveProviders) $selections['Inactive Sitters'] = $inactiveProviders;
	return $selections;
}

function getActiveProviderSelections($availabilityDate=null, $zip=null) {
	return getProviderSelections($availabilityDate, $zip, $status='active');
}

function getProviderSelections($availabilityDate=null, $zip=null, $status=null) {
	$statusFrag = $status == 'active' ? "active = 1" : ($status == 'inactive' ? "active = 0" : '1=1');
	// if zip and if $_SESSION['providerterritoriesenabled'] segregate providers in this zip and offer them first
	$providers = fetchAssociations("SELECT providerid, fname, lname, nickname FROM tblprovider WHERE $statusFrag");
	//$providersOff = $availabilityDate ? providersOffThisDay($availabilityDate) : array();
	$localProviders = null;
	if($providers && $zip && $_SESSION['providerterritoriesenabled']) {
		$localProviderIds = getZipProviders($zip);
		foreach($providers as $i => $prov)
			if(in_array($prov['providerid'], $localProviderIds)) {
				unset($providers[$i]);
				//if(!in_array($prov['providerid'], $providersOff))
					$localProviders[providerShortName($prov)] = $prov['providerid'];
			}
		if($localProviders) uksort($localProviders, 'caseInsensitiveComparison');
	}

	$selections = array();
	foreach($providers as $prov)
		if(!in_array($prov['providerid'], (array)$providersOff))
			$selections[providerShortName($prov)] = $prov['providerid'];
	uksort($selections, 'caseInsensitiveComparison');
	if($localProviders) 
		$selections = array_merge(array('Local Sitters'=>$localProviders), array('Sitters'=>$selections)); // WTF??
	//else if($_SESSION['providerterritoriesenabled'])  $selections = array('Other Sitters'=>$selections);
	return $selections;
}

function providerInArray($providerid, $providerids) {
	if(in_array($providerid, $providerids)) return true;
	foreach($providerids as $el) {
		if(is_array($el)) if(in_array($providerid, $el)) return true;
	}
}
		

function saveNewProvider() { // use $_POST
	return saveNewProviderWithData($_POST);
}

function saveNewProviderWithData($data) { // use $_POST
  prepreocessProvider($data);
  unset($data['providerid']);
  if($_SESSION['trainingMode']) $data['training'] = 1;
  $providerid = insertTable('tblprovider', $data, 1);
  logChange($providerid, 'tblprovider', 'c', $note='.');
  return $providerid;
}

function saveProvider($outData = null) { // use $_POST
  $outData = array_merge($outData ? $outData : $_POST );
  $providerid = $outData['providerid'];
  prepreocessProvider($outData);
  unset($outData['providerid']);
  logChange($providerid, 'tblprovider', 'm', $note='.');
  return updateTable('tblprovider', $outData, "providerid=$providerid", 1);

}

function prepreocessProvider(&$prov) {
	global $providerFields;
	
	foreach($prov as $key => $val) {
		if(strpos($key, "sms_primaryphone_") === 0 && $val) {
			$phoneKey = substr($key, strlen("sms_primaryphone_"));
			$prov[$phoneKey] = 'T'.$prov[$phoneKey];
		}
	}

  if(isset($prov['primaryphone']) && $prov['primaryphone'] && isset($prov[$prov['primaryphone']]))
    $prov[$prov['primaryphone']] = '*'.$prov[$prov['primaryphone']];
  $booleans = array('active','noncompetesigned','dailyvisitsemail','weeklyvisitsemail');
  foreach($booleans as $field) 
  	$prov[$field] = isset($prov[$field]) && $prov[$field] ? 1 : 0;
  foreach(array('hiredate','terminationdate') as $date)
    if($prov[$date]) $prov[$date] = date("Y-m-d",strtotime($prov[$date]));
  foreach($prov as $field => $unused)
    if(!in_array($field, $providerFields)) 
      unset($prov[$field]);
}
    
function getProviderRates($id) {
	if(!$id) return array();
  return fetchAssociationsKeyedBy("SELECT * FROM relproviderrate WHERE providerptr=$id", 'servicetypeptr');
}

function getMultipleProviderRates($ids) {
	if(!$ids) return array();
	$results = array();
  $associations = fetchAssociations("SELECT * FROM relproviderrate WHERE providerptr IN (".join(',', $ids).")");
  foreach($associations as $rateDesc)
  	$results[$rateDesc['providerptr']][$rateDesc['servicetypeptr']] = $rateDesc;
  return $results;
}

function getProviderIncompleteJobs($prov, $todayOnly=false) {
	//$timephrase = "(date = CURDATE() AND starttime < CURTIME())";
	//if(!$todayOnly) $timephrase = "(date < CURDATE() OR $timephrase)";	
	$timephrase = "(date = CURDATE() AND starttime < CURTIME())";
	if(!$todayOnly) $timephrase = "(date < CURDATE() OR $timephrase)";	
	$sql = 
		"SELECT CONCAT_WS(' ', fname, lname) as client, pets, timeofday, date, servicecode, note, appointmentid
			FROM tblappointment
			LEFT JOIN tblclient ON clientid = clientptr
			WHERE completed is null AND canceled is null AND providerptr = $prov AND $timephrase
			ORDER BY date, starttime";
	return fetchAssociations(tzAdjustedSql($sql));
}

function countAllProviderIncompleteJobs($prov=null, $filter=null, $timeWindowPast=true) {
	$filter = $filter ? "AND $filter" : '';
	$filter .= $prov ? " AND providerptr = $prov" : '';
	$timeWindowPast = $timeWindowPast ? 'AND endtime < CURTIME()' : '';// timezone fix
	$sql = 		"SELECT count(*) 
			FROM tblappointment
			WHERE completed is null AND canceled is null $filter 
						AND (date < CURDATE() OR (date = CURDATE() AND starttime < CURTIME()) $timeWindowPast)";

	return fetchRow0Col0(tzAdjustedSql($sql));
}

function findIncompleteJobs($starting, $ending, $prov=null, $sort=null, $client=null, $limit=null, $futurealso=null) {
	if($result = findIncompleteJobsResultSet($starting, $ending, $prov, $sort, $client=null, $limit, $futurealso)) {
		$assocs = array();
		while($row = mysql_fetch_array($result, MYSQL_ASSOC))
		 $assocs[] = $row;
		return $assocs;
	}
	return array();
}

function findIncompleteJobsResultSet($starting, $ending, $prov=null, $sort=null, $clients=null, $limit=null, $futurealso=null) {
	if($starting) $starting = "AND date >= '".date('Y-m-d', strtotime($starting))."'";
	if($ending) $ending = "AND date <= '".date('Y-m-d', strtotime($ending))."'";
	$filter = '';
	$filter .= $prov ? "AND providerptr = $prov " : '';
	$filter .= $clients ? "AND clientptr IN ($clients) " : '';
	
	if($sort) {
		$extraFields = "";
		$joinClause = "";
		
		$sort_key = substr($sort, 0, strpos($sort, '_'));
		$sort_dir = substr($sort, strpos($sort, '_')+1);
		if($sort_key == 'provider') {
			$orderClause = "ORDER BY providername $sort_dir";
			$extraFields = ", ifnull(nickname, CONCAT_WS(' ', tblprovider.fname, tblprovider.lname)) as providername";
			$joinClause = " LEFT JOIN tblprovider ON providerid = providerptr";
		}
		else if($sort_key == 'client') {
			$orderClause = "ORDER BY tblclient.lname $sort_dir, tblclient.fname $sort_dir";
			$extraFields = ", CONCAT_WS(' ', tblclient.fname, tblclient.lname) as clientname, CONCAT_WS(' ', tblclient.lname, tblclient.fname) as clientsortname";
			$joinClause = " LEFT JOIN tblclient ON clientid = clientptr";
		}
		else if($sort_key == 'date') 
			$orderClause = "ORDER BY date $sort_dir, starttime ASC";
		else if($sort_key == 'timeofday') {
			$orderClause = "ORDER BY starttime $sort_dir, date ASC";
			$extraFields = ", label";
		}
		else if($sort_key == 'service') {
			$orderClause = "ORDER BY label $sort_dir, date ASC, starttime ASC";
			$extraFields = ", label, starttime";
			$joinClause = " JOIN tblservicetype ON servicetypeid = servicecode";
		}
		else $orderClause = "ORDER BY $sort_key $sort_dir";
	}
	else $orderClause = 'ORDER BY date DESC, starttime DESC';
/*echo "SELECT * $extraFields
		FROM tblappointment $joinClause
		WHERE completed is null AND canceled is null $filter 
			AND (date < CURDATE() OR (date = CURDATE() AND endtime < CURTIME())) $starting $ending
		$orderClause";exit;*/
		
	$limit = $limit ? "LIMIT $limit" : "";
	$completedFilter = $futurealso ? '' : 'AND (date < CURDATE() OR (date = CURDATE() AND starttime < CURTIME() AND endtime < CURTIME()))';
	$sql = 	"SELECT * $extraFields
		FROM tblappointment $joinClause
		WHERE completed is null AND canceled is null $filter 
			$completedFilter $starting $ending
		$orderClause $limit";
	return doQuery(tzAdjustedSql($sql));
}

function findIncompleteSurchargesResultSet($starting, $ending, $prov=null, $sort=null, $clients=null, $limit=null, $futurealso=null) {
	if($starting) $starting = "AND tblsurcharge.date >= '".date('Y-m-d', strtotime($starting))."'";
	if($ending) $ending = "AND tblsurcharge.date <= '".date('Y-m-d', strtotime($ending))."'";
	$filter = '';
	$filter .= $prov ? "AND providerptr = $prov " : '';
	$filter .= $clients ? "AND clientptr IN ($clients) " : '';
	
	if($sort) {
		$extraFields = "";
		$joinClause = "";
		
		$sort_key = substr($sort, 0, strpos($sort, '_'));
		
		$sort_dir = substr($sort, strpos($sort, '_')+1);
		if($sort_key == 'provider') {
			$orderClause = "ORDER BY providername $sort_dir";
			$extraFields = ", ifnull(nickname, CONCAT_WS(' ', tblprovider.fname, tblprovider.lname)) as providername";
			$joinClause = " LEFT JOIN tblprovider ON providerid = providerptr";
		}
		else if($sort_key == 'client') {
			$orderClause = "ORDER BY tblclient.lname $sort_dir, tblclient.fname $sort_dir";
			$extraFields = ", CONCAT_WS(' ', tblclient.fname, tblclient.lname) as clientname, CONCAT_WS(' ', tblclient.lname, tblclient.fname) as clientsortname";
			$joinClause = " LEFT JOIN tblclient ON clientid = clientptr";
		}
		else if($sort_key == 'date') 
			$orderClause = "ORDER BY tblsurcharge.date $sort_dir, starttime ASC";
		else if($sort_key == 'timeofday') {
			$orderClause = "ORDER BY starttime $sort_dir, date ASC";
			$extraFields = ", label";
		}
		else if($sort_key == 'service') {
			$orderClause = "ORDER BY label $sort_dir, tblsurcharge.date ASC, starttime ASC";
			$extraFields = ", label, starttime";
			$joinClause = " JOIN tblsurchargetype ON surchargetypeid = surchargecode";
		}
		else $orderClause = "ORDER BY $sort_key $sort_dir";
	}
	else $orderClause = 'ORDER BY tblsurcharge.date DESC, tblsurcharge.starttime DESC';
/*echo "SELECT * $extraFields
		FROM tblappointment $joinClause
		WHERE completed is null AND canceled is null $filter 
			AND (date < CURDATE() OR (date = CURDATE() AND endtime < CURTIME())) $starting $ending
		$orderClause";exit;*/
		
	$limit = $limit ? "LIMIT $limit" : "";
	$completedFilter = $futurealso ? '' : 'AND (tblsurcharge.date < CURDATE() OR (tblsurcharge.date = CURDATE() AND starttime < CURTIME() AND endtime < CURTIME()))';
	$sql = 	"SELECT * $extraFields
		FROM tblsurcharge $joinClause
		WHERE completed is null AND canceled is null $filter 
			$completedFilter $starting $ending
		$orderClause $limit";
	return doQuery(tzAdjustedSql($sql));
}


function setProviderRates($id, $ratetype) {
	if(!$id) return;
	if($_SESSION['preferences']['sittersPaidHourly'])
		return setProviderRatesHourly($id);
	
	$oldRates = fetchAssociationsKeyedBy("SELECT * FROM relproviderrate WHERE providerptr=$id", 'servicetypeptr');
	doQuery("DELETE FROM relproviderrate WHERE providerptr=$id");
	foreach($_POST as $key => $val) {
		$val = trim($val);
		if(strpos($key, 'servicerate_') === 0 && strlen($val) > 0) {
			if(substr($val, -1) == '%') $val = substr($val, 0, -1);
			$servType = substr($key, strlen('servicerate_'));
			$ispercentage = $_POST["servicerateispercentage_$servType"]  ?  1 : 0;
			$newRate = array('providerptr'=>$id, 
			                'servicetypeptr'=>$servType,
			                'rate'=>$val,
			                'ispercentage'=>$ispercentage
			                );
			insertTable('relproviderrate', $newRate, 1);
			$newRate['note'] = '';
			if(!$oldRates[$servType]) $change = "$servType|$val|$ispercentage";
			else if(array_diff($oldRates[$servType], $newRate)) {
				$change = "$servType|";
				$change .= $oldRates[$servType]['rate'].($oldRates[$servType]['ispercentage'] ? '%' : '');
				$change .= '|'.$val.($ispercentage ? '%' : '');
			}
			unset($oldRates[$servType]);
			if($change) logChange($id, 'relproviderrate', ($oldRates[$servType] ? 'm' : 'c'), $change);
		}
	}
	if($dropped = array_keys($oldRates))
		logChange($id, 'relproviderrate', 'd', join(',', $dropped));
}

function setProviderRatesHourly($provid) {
	if(!$provid) return;
	
	$oldRates = fetchAssociationsKeyedBy("SELECT * FROM relproviderrate WHERE providerptr=$provid", 'servicetypeptr');
	doQuery("DELETE FROM relproviderrate WHERE providerptr=$provid");
	$serviceHours = fetchKeyValuePairs("SELECT servicetypeid, hours FROM tblservicetype");
	$rate = getProviderPreference($provid, 'hourlyRate');
	// DO NOT INCLUDE: 
	$travel = getProviderPreference($provid, 'travelAllowance');
	foreach($serviceHours as $servicetypeid => $hours) {
		$hourFrac = (strtotime("1/1/1970 $hours") - strtotime("1/1/1970")) / 3600;
		$newRate = 
			array('providerptr'=>$provid,
						'servicetypeptr'=>$servicetypeid,
						'rate'=>($val = ((int)($rate * 100 * $hourFrac)) / 100 + $travel),
						'ispercentage'=>0,
						'note'=>'hourly');
//echo $_SESSION['servicetypes'][$servicetypeid]['name']."($servicetypeid) rate: $rate travel: $travel hours: $hours ($hourFrac) >> {$newRate['rate']}<br>";	
		replaceTable('relproviderrate', $newRate, 1);
		if(!$oldRates[$servicetypeid]) $change = "$servicetypeid|$val|0";
		else if(array_diff($oldRates[$servicetypeid], $newRate)) {
			$change = "$servicetypeid|";
			$change .= $oldRates[$servicetypeid]['rate'];
			$change .= '|'.$val;
		}
		unset($oldRates[$servicetypeid]);
		if($change) logChange($provid, 'relproviderrate', ($oldRates[$servicetypeid] ? 'm' : 'c'), $change);
	}
	if($dropped = array_keys($oldRates))
		logChange($provid, 'relproviderrate', 'd', join(',', $dropped));
//exit;
}


/* ######### NEW TIME OFF ###################### */
function providersOffThisDay($date) {
	return fetchCol0("SELECT distinct providerptr FROM tbltimeoffinstance WHERE date = '".date('Y-m-d', strtotime($date))."'");
}

function timesOffThisDay($provid, $date, $completeRecords=false) {
	$pfilter = "providerptr = $provid AND ";
	if($completeRecords)
		return fetchAssociations("SELECT * FROM tbltimeoffinstance WHERE $pfilter date = '".date('Y-m-d', strtotime($date))."'");
	else 
		return fetchCol0("SELECT timeofday FROM tbltimeoffinstance WHERE $pfilter date = '".date('Y-m-d', strtotime($date))."'");
}

function isProviderOffThisDay($id, $date) {
	$date =  date('Y-m-d', strtotime($date));
	return fetchRow0Col0("SELECT 1 FROM tbltimeoffinstance WHERE providerptr=$id AND date = '".date('Y-m-d', strtotime($date))."' LIMIT 1");
}

function isProviderOffThisDayWithRows($id, $date, $rows) {
	if(!$rows) return false;
	$date =  date('Y-m-d', strtotime($date));
	foreach((array)$rows as $row) if($row['date'] == $date) return true;
}

function getProviderTimeOff($id, $showpasttimeoff=true, $where=null) {
	if(!$id) return false;  // really should not be called when $id==null, but... 
	$filter = !$showpasttimeoff ? "AND date >= CURDATE()" : '';
	if($where) $filter .= " AND $where";
	$sql = "SELECT * FROM tbltimeoffinstance WHERE providerptr=$id $filter";
	return fetchAssociations(tzAdjustedSql($sql));
}

function getProviderTimeOffInRange($id, $range) {
	if(!$id) return false;  // really should not be called when $id==null, but... 
	$rangePhrase = '';
	if($range[0]) $rangePhrase .= " AND date >= '{$range[0]}' ";
	if($range[1]) $rangePhrase .= " AND date <= '{$range[1]}' ";
	$sql = "SELECT * FROM tbltimeoffinstance WHERE providerptr=$id $rangePhrase";
	return fetchAssociations(tzAdjustedSql($sql));
}

function providerIsOff($prov, $date, $timeofday=null, $timesOff=null) {
	if(FALSE && !mattOnlyTEST()) return providerIsOffORIG($prov, $date, $timeofday, $timesOff); // FALSE && 
	
	if(!$prov) return false;  // really should not be called when $prov==null, but... 
	global $db;
	static $prefs, $lastDb;
	$date =  is_string($date) ? date('Y-m-d', strtotime($date)) : date('Y-m-d', $date);
	$date2 = $date;
	if($timeofday) {
		$a['starttime'] = strtotime(substr($timeofday, 0, strpos($timeofday, '-')));
		$a['endtime'] = strtotime(substr($timeofday, strpos($timeofday, '-')+1));
		if($a['endtime'] < $a['starttime'])
			$date2 = date('Y-m-d', strtotime("+ 1 day", strtotime($date)));
		$a['starttime'] = "$date {$a['starttime']}";
		$a['endtime'] = "$date2 {$a['endtime']}";
	}
	$where="date = '$date'";
	if($date != $date2)  $where = "(date = '$date' OR date = '$date2')";
	$timesOff = $timesOff === null ? getProviderTimeOff($prov, $showpasttimeoff=true, $where) : $timesOff;
//if(mattOnlyTEST()) echo "<p>TEST [$date] [$time] [".print_r($timeofday, 1)."]<br>>>>>".print_r($timesOff, 1).'<p>';
//if(mattOnlyTEST()) logError(print_r($timesOff, 1));
//if(mattOnlyTEST()) echo(print_r($timesOff, 1));

	foreach($timesOff as $timeOff) {
		if(!$timeOff['timeofday']) return true;
		else if($timeOff['timeofday'] && $timeofday) {
			$b['starttime'] = strtotime(substr($timeOff['timeofday'], 0, strpos($timeOff['timeofday'], '-')));
			$b['endtime'] = strtotime(substr($timeOff['timeofday'], strpos($timeOff['timeofday'], '-')+1));
			$b['starttime'] = "{$timeOff['date']} {$b['starttime']}";
			$b['endtime'] = "{$timeOff['date']} {$b['endtime']}";
			
//global $db;	if($db == 'dogslife') {if($timeOff['timeofday'] == '10:00 am-2:00 pm') logError(print_r($a,1).print_r($b,1));}
//if(mattOnlyTEST()) echo "<p>".print_r($a,1)."<p>".print_r($b,1);
			if(TRUE || staffOnlyTEST()) {
				if(isset($_SESSION['preferences'])) $prefs = $_SESSION['preferences'];
				else {
					if($lastDb != $db) {
						$lastDb = $db;
						$prefs = fetchKeyValuePairs("SELECT property, value FROM tblpreference");  // will not be set for cron job
					}
				}
//if(mattOnlyTEST()) {echo "<p>timeframeOverlapPolicy: {$prefs['timeframeOverlapPolicy']}<p>".print_r($a,1)."<p>".print_r($b,1)."<p>timeFrameOverlapStrict: ".timeFrameOverlapStrict($a, $b).'<p>';}
				if($prefs['timeframeOverlapPolicy'] == 'permissive') {if(timeFrameOverlapLenient($a, $b)) return true;}
				else if(timeFrameOverlapStrict($a, $b)) return true;
			}
			else if(($a['starttime'] >= $b['starttime'] && $a['starttime'] <= $b['endtime']) ||
				 ($b['starttime'] >= $a['starttime'] && $b['starttime'] <= $a['endtime'])) return true;
		}
	}
	return false;
}

function timeoffCollisionWithBlackout($timeOff) {
	$date =  date('Y-m-d', strtotime($timeOff['date']));
	$date2 = $date;
	$sql = "SELECT * FROM tbltimeoffinstance WHERE providerptr= ".getTimeOffBlackoutId()." AND date = '$date'";

	if(!($blackouts = fetchAssociations($sql))) return;
	if($tod = $timeOff['timeofday']) {
		$a['starttime'] = strtotime(substr($tod, 0, strpos($tod, '-')));
		$a['endtime'] = strtotime(substr($tod, strpos($tod, '-')+1));
		if($a['endtime'] < $a['starttime'])
			$date2 = date('Y-m-d', strtotime("+ 1 day", strtotime($date)));
		$a['starttime'] = "$date {$a['starttime']}";
		$a['endtime'] = "$date2 {$a['endtime']}";
	}
	foreach($blackouts as $blackout) {
		if(!$timeOff['timeofday'] || !$blackout['timeofday']) $collision = $blackout;
		else if($blackoutTOD = $blackout['timeofday']) {
			$b['starttime'] = strtotime(substr($blackoutTOD, 0, strpos($blackoutTOD, '-')));
			$b['endtime'] = strtotime(substr($blackoutTOD, strpos($blackoutTOD, '-')+1));
			$b['starttime'] = "{$blackout['date']} {$b['starttime']}";
			$b['endtime'] = "{$blackout['date']} {$b['endtime']}";
		}
		if($_SESSION['preferences']['timeframeOverlapPolicy'] == 'permissive') {
			if(timeFrameOverlapLenient($a, $b)) $collision = $blackout;
		}
		else if(timeFrameOverlapStrict($a, $b)) $collision = $blackout;
		if($collision) break;
	}
	return $collision;
}



function providerIsOffORIG($prov, $date, $timeofday=null, $timesOff=null) {
	if(!$prov) return false;  // really should not be called when $prov==null, but... 
	global $db;
	static $prefs, $lastDb;
	$date =  is_string($date) ? date('Y-m-d', strtotime($date)) : date('Y-m-d', $date);
	$timesOff = $timesOff === null ? getProviderTimeOff($prov, $showpasttimeoff=true, $where="date = '$date'") : $timesOff;
//if(mattOnlyTEST()) echo "<p>TEST [$date] [$time] [".print_r($timeofday, 1)."]<br>>>>>".print_r($timesOff, 1).'<p>';
//if(mattOnlyTEST()) logError(print_r($timesOff, 1));
//if(mattOnlyTEST()) echo(print_r($timesOff, 1));
	foreach($timesOff as $timeOff) {
		if(!$timeOff['timeofday']) return true;
		else if($timeOff['timeofday'] && $timeofday) {
			$a['starttime'] = strtotime(substr($timeofday, 0, strpos($timeofday, '-')));
			$a['endtime'] = strtotime(substr($timeofday, strpos($timeofday, '-')+1));
			$b['starttime'] = strtotime(substr($timeOff['timeofday'], 0, strpos($timeOff['timeofday'], '-')));
			$b['endtime'] = strtotime(substr($timeOff['timeofday'], strpos($timeOff['timeofday'], '-')+1));
//global $db;	if($db == 'dogslife') {if($timeOff['timeofday'] == '10:00 am-2:00 pm') logError(print_r($a,1).print_r($b,1));}
//if(mattOnlyTEST()) echo "<p>".print_r($a,1)."<p>".print_r($b,1);
			if(TRUE || staffOnlyTEST()) {
				if(isset($_SESSION['preferences'])) $prefs = $_SESSION['preferences'];
				else {
					if($lastDb != $db) {
						$lastDb = $db;
						$prefs = fetchKeyValuePairs("SELECT property, value FROM tblpreference");  // will not be set for cron job
					}
				}
//if(mattOnlyTEST()) {echo "<p>timeframeOverlapPolicy: {$prefs['timeframeOverlapPolicy']}<p>".print_r($a,1)."<p>".print_r($b,1)."<p>timeFrameOverlapStrict: ".timeFrameOverlapStrict($a, $b).'<p>';}
				if($prefs['timeframeOverlapPolicy'] == 'permissive') {if(timeFrameOverlapLenient($a, $b)) return true;}
				else if(timeFrameOverlapStrict($a, $b)) return true;
			}
			else if(($a['starttime'] >= $b['starttime'] && $a['starttime'] <= $b['endtime']) ||
				 ($b['starttime'] >= $a['starttime'] && $b['starttime'] <= $a['endtime'])) return true;
		}
	}
	return false;
}

function timeFrameOverlapStrict($a, $b) {
	return ($a['starttime'] >= $b['starttime'] && $a['starttime'] <= $b['endtime'])
		 || ($b['starttime'] >= $a['starttime'] && $b['starttime'] <= $a['endtime']);
}

function timeFrameOverlapLenient($a, $b) {
	return 
			($a['starttime'] == $b['starttime'] || $a['endtime'] == $b['endtime'])
			|| ($a['starttime'] > $b['starttime'] && $a['starttime'] < $b['endtime'])
		 	|| ($b['starttime'] > $a['starttime'] && $b['starttime'] < $a['endtime']);
}



function daysOffInInterval($timeOff) {
	return array($timeOff['date']);
}

function getUpcomingTimeOff($id, $days=14) {
	$providerfilter = $id == -1 ? '1=1' : "providerptr=$id";
	$periodEnd =  date('Y-m-d', strtotime("+$days days"));
	$filter = "AND date BETWEEN CURDATE() AND '$periodEnd'";
	return fetchAssociations(tzAdjustedSql("SELECT * FROM tbltimeoffinstance WHERE $providerfilter $filter ORDER BY providerptr, date"));
}

function getUpcomingTimeOffLabels($prov, $days=14, $futureOnly=true) {
	$clickableTimesOff = dbTEST('careypet,dogslife');
	$provideractive = fetchRow0Col0("SELECT active FROM tblprovider WHERE providerid = $prov");
	if($provideractive) 
		foreach(getUpcomingTimeOff($prov, $days) as $row) {
			$timeoffDateRange = representTimeOffRange($row, $futureOnly=true);
			if($clickableTimesOff) {
				$timeOffStartDay = timeOffStartDayAndPtr($row, $futureOnly=true);
				$onclickedit = "openConsoleWindow(\"timeoffcalendar\", \"timeoff-sitter-calendar.php?&editable=1&provid=$prov&month={$timeOffStartDay['date']}&open={$timeOffStartDay['timeoffid']}\",850,700)";
				$timeoffDateRange = "<span style='cursor:pointer;' onclick='$onclickedit'>$timeoffDateRange</span>";
				if(!$upcomingTimeOff[$timeOffStartDay['timeoffid']])
					$upcomingTimeOff[$timeOffStartDay['timeoffid']] = $timeoffDateRange;
			}
			else {
				$upcomingTimeOff[] = representTimeOffRange($row, $futureOnly=true);
			}
		}

	if($upcomingTimeOff) {
		if(!$clickableTimesOff) $upcomingTimeOff = array_unique($upcomingTimeOff);
		return "Upcoming time off: ".join(', ', $upcomingTimeOff);
	}
}

function getUnassignedAppointmentIDsDuringTimeOff($provider) {
	$filter = array();
	foreach((array)getProviderTimeOff($provider, false) as $row) {
		if($row['timeofday']) {
			$timeOffStarttime = date('H:i:s', strtotime(substr($row['timeofday'], 0, strpos($row['timeofday'], '-'))));
			$timeOffEndtime = date('H:i:s', strtotime(substr($row['timeofday'], strpos($row['timeofday'], '-')+1)));
		}
		$filter[] = 
			"(date = '{$row['date']}'"
			.($row['timeofday'] 
				? "\n AND ((starttime >= '$timeOffStarttime' AND starttime <= '$timeOffEndtime') OR (endtime >= '$timeOffStarttime' AND endtime <= '$timeOffEndtime')))"
				: ')');
	}
	if(!$filter) return array();
	$filter = join("\nOR ", $filter);

	$pastProviderFilter = 
		"tblservice.providerptr = $provider
			OR tblappointment.providerptr IN (SELECT providerptr FROM relwipedappointment WHERE appointmentptr = appointmentid)";
	//$clientIds = fetchCol0("SELECT distinct clientptr  FROM `tblappointment` WHERE `providerptr` = $provider");
	//if($clientIds)  $pastProviderFilter .= " OR tblappointment.clientptr IN (".join(',', $clientIds).")";
	
	$sql = "SELECT appointmentid 
					FROM tblappointment LEFT JOIN tblservice ON serviceid = serviceptr
					WHERE tblappointment.providerptr = 0 AND current = 1
					  AND ($pastProviderFilter)
						AND date >= CURDATE() AND ($filter)"; // AND tblservice.providerptr = $provider 
//echo "<p>$sql<p>";
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {print_r(getProviderTimeOff($provider, false)); echo str_replace("\n", "<br>", "<p>$filter<p>");print_r(fetchCol0($sql));exit;}						
	return fetchCol0(tzAdjustedSql($sql));
}

function unwipeAppointments($provider) {
	$sql = "SELECT a.providerptr as currentprovider, appointmentptr, date, timeofday
					FROM relwipedappointment w
					LEFT JOIN tblappointment a ON appointmentid = appointmentptr
					WHERE w.providerptr = $provider";  //  AND date in the future
	$apptTimes = fetchAssociations($sql);
	foreach($apptTimes as $apptTime) {
		if($apptTime['currentprovider']) {  // if there is a provider, then there should be no wipetable entry (was reassigned)
			deleteTable('relwipedappointment', "appointmentptr = {$apptTime['appointmentptr']}", 1);
		}
		else if(!providerIsOff($provider, $apptTime['date'], $apptTime['timeofday'])) {
			deleteTable('relwipedappointment', "appointmentptr = {$apptTime['appointmentptr']} AND providerptr = $provider", 1);
			$toRestore[] = $apptTime['appointmentptr'];
		}
	}
	if($toRestore) {
		if(fetchRow0Col0("SELECT active FROM tblprovider WHERE providerid = $provider LIMIT 1"))
			updateTable('tblappointment', array('providerptr'=>$provider), "appointmentid IN (".join(',', $toRestore).")", 1);
	}
}
	


function representTimeOffRange($timeoff, $futureOnly=false) {
	$timeOfDay = $timeoff['timeofday'] ? $timeoff['timeofday'] : 'all day';
	$startDay = $timeoff['date'];
	if(!$timeoff['patternptr']) 
		return "<span style='text-decoration:underline;text-decoration-style:dashed' title='$timeOfDay'>"
							.shortNaturalDate(strtotime($startDay))
							."</span>";
		
	$pattern = fetchFirstAssoc("SELECT date, until FROM tbltimeoffpattern WHERE patternid = {$timeoff['patternptr']} LIMIT 1");
	$timeOfDay = $pattern['timeofday'] ? $pattern['timeofday'] : 'all day';
	$excludeEndYear = 'noYear';
	if($pattern['until'] && date('Y', strtotime($pattern['until']))-date('Y') > 1)
		$excludeEndYear = false;
		
//if(mattOnlyTEST()) $JUNK = 	date('Y').' v '.date('Y', strtotime($pattern['until']));
	$startDay = $futureOnly && strtotime($pattern['date']) < strtotime(date('Y-m-d')) ? 'until '
		: shortNaturalDate(strtotime($pattern['date']), 'noYear').'-';
	return "<span style='text-decoration:underline;text-decoration-style:dashed' title='$timeOfDay'>"
					.$startDay.shortNaturalDate(strtotime($pattern['until']), $excludeEndYear)
					."</span>";
}

function timeOffStartDayAndPtr($timeoff, $futureOnly=true) {
	// return a start day and timeoffid for a time off or timeoffpattern
	$startDay = 
		$timeoff['patternptr'] ? fetchRow0Col0("SELECT date FROM tbltimeoffpattern WHERE patternid = {$timeoff['patternptr']} LIMIT 1")
		: $timeoff;
	if($timeoff['patternptr']) {
		if($futureOnly) $startDay = date('Y-m-d', max(strtotime($startDay), time()));
		$timeoffptr = fetchRow0Col0(
			"SELECT timeoffid 
				FROM tbltimeoffinstance 
				WHERE patternptr = {$timeoff['patternptr']} AND date >= '$startDay'
				LIMIT 1", 1);
		$startDay = array('date'=>$startDay, 'timeoffid'=>$timeoffptr);
	}
	return $startDay;
}


function convertAllOldTimesOffToNew() {
	global $numInstances, $numPatterns;
	$result = doQuery("SELECT * FROM tbltimeoff");
  while($row = mysql_fetch_array($result, MYSQL_ASSOC))
		convertOldTimeOffToNewTimeOff($row);
	echo "Patterns: [$numPatterns]  Instances: [$numInstances]";
}

function convertOldTimeOffToNewTimeOff($old) {
	global $numInstances, $numPatterns;
	$base = array('providerptr'=>$old['providerptr'], 'timeofday'=>$old['timeofday'], 'created'=>$old['whenscheduled'], 'createdby'=>$old['createdby']);
	if($old['firstdayoff'] != $old['lastdayoff']) {
		// create a pattern
		$pattern = $base;
		$pattern['date'] = $old['firstdayoff'];
		$pattern['until'] = $old['lastdayoff'];
		$pattern['pattern'] = 'everyday';
		$base['patternptr'] = insertTable('tbltimeoffpattern', $pattern, 1);
		$numPatterns += 1;
	}
	for($day=$old['firstdayoff'];$day<=$old['lastdayoff'];$day = date('Y-m-d', strtotime('+1 day', strtotime($day)))) {
		$base['date'] = $day;
//$base['note'] = "orig id: [{$old['timeoffid']}]";		
		insertTable('tbltimeoffinstance', $base, 1);
		$numInstances += 1;
	}
}

function previewTimeOffUnassignments($timeOffInstances) {
	// find all of the assigned visits that would be unassigned
	// if this these time off instances were created
	if(!$timeOffInstances) return array();  // no appointments to unassign
	$provider = $timeOffInstances[0]['providerptr'];
	if($_SESSION['preferences']['timeframeOverlapPolicy'] == 'permissive') 
		$strictly = '';
	else $strictly = '=';
	
	$filter = array();
	foreach((array)$timeOffInstances as $row) {
		if($row['timeofday']) {
			$timeOffStarttime = "{$row['date']} ".date('H:i:s', strtotime(substr($row['timeofday'], 0, strpos($row['timeofday'], '-'))));
			$timeOffEndtime = "{$row['date']} ".date('H:i:s', strtotime(substr($row['timeofday'], strpos($row['timeofday'], '-')+1)));
		}
		$filter[] = 
			"(date = '{$row['date']}'"
			.($row['timeofday'] 
				? 
					" AND ((starttime >{$strictly} '$timeOffStarttime' AND starttime <{$strictly} '$timeOffEndtime')
									OR (CONCAT_WS(' ', if(endtime < starttime, DATE_ADD(date, INTERVAL 1 DAY), date), endtime) >{$strictly} '$timeOffStarttime'  
										AND CONCAT_WS(' ', if(endtime < starttime, DATE_ADD(date, INTERVAL 1 DAY), date), endtime) <{$strictly} '$timeOffEndtime')
									OR ('$timeOffStarttime' >{$strictly} starttime 
										AND '$timeOffStarttime' <{$strictly} CONCAT_WS(' ', if(endtime < starttime, DATE_ADD(date, INTERVAL 1 DAY), date), endtime))
									OR ('$timeOffEndtime' >{$strictly} starttime 
										AND '$timeOffEndtime' <{$strictly} CONCAT_WS(' ', if(endtime < starttime, DATE_ADD(date, INTERVAL 1 DAY), date), endtime))
									OR ('$timeOffStarttime' = starttime 
										AND '$timeOffEndtime' = CONCAT_WS(' ', if(endtime < starttime, DATE_ADD(date, INTERVAL 1 DAY), date), endtime))
								))"
				: ')');
				
	}
	$filter = join(' OR ', $filter);
		
	if(!$filter) return 0;  // no appointments to unassign
	
	$unassignfilter = tzAdjustedSql("providerptr = $provider AND date >= CURDATE() AND ($filter)");
	$sql = 
		"SELECT a.*, s.label, lname, fname, CONCAT_WS(' ', fname, lname) as client
			FROM tblappointment a
			LEFT JOIN tblclient c ON clientid = clientptr
			LEFT JOIN tblservicetype s ON servicetypeid = servicecode
			WHERE $unassignfilter
			ORDER BY date, starttime, lname, fname, timeofday";
//if(mattOnlyTEST()) print_r($sql);
	return fetchAssociations($sql);
}


function applyProviderTimeOffToAppointments($provider, $oldUnassignedAppts, $reportOnThisDateOnly=null) {
	if($_SESSION['preferences']['timeframeOverlapPolicy'] == 'permissive') 
		$strictly = '';
	else $strictly = '=';
	
	$filter = array();
	foreach((array)getProviderTimeOff($provider, false) as $row) {
if(FALSE) {		/****** OLD METHOD -- DID NOT ACCOUNT FOR OVERNIGHTS *********/
		if($row['timeofday']) {
			$timeOffStarttime = date('H:i:s', strtotime(substr($row['timeofday'], 0, strpos($row['timeofday'], '-'))));
			$timeOffEndtime = date('H:i:s', strtotime(substr($row['timeofday'], strpos($row['timeofday'], '-')+1)));
		}
		$filter[] = 
			"(date = '{$row['date']}'"
			.($row['timeofday'] 
				? /*" AND ((starttime >{$strictly} '$timeOffStarttime' AND starttime <{$strictly} '$timeOffEndtime')
									OR ('$timeOffStarttime' >{$strictly} starttime AND '$timeOffStarttime' <{$strictly} endtime)))"*/
					// logic: visit start is in timeoff timeframe
					//				OR visit end time  is in timeoff timeframe
					//				OR timeoff start time  is in visit timeframe
					//				OR timeoff end time  is in visit timeframe
					//				OR timeoff timeframe matches visit timeframe
					" AND ((starttime >{$strictly} '$timeOffStarttime' AND starttime <{$strictly} '$timeOffEndtime')
									OR (endtime >{$strictly} '$timeOffStarttime'  AND endtime <{$strictly} '$timeOffEndtime')
									OR ('$timeOffStarttime' >{$strictly} starttime AND '$timeOffStarttime' <{$strictly} endtime)
									OR ('$timeOffEndtime' >{$strictly} starttime AND '$timeOffEndtime' <{$strictly} endtime)
									OR ('$timeOffStarttime' = starttime AND '$timeOffEndtime' = endtime)
								))"
				: ')');
} /******/

		if($row['timeofday']) {
			$timeOffStarttime = "{$row['date']} ".date('H:i:s', strtotime(substr($row['timeofday'], 0, strpos($row['timeofday'], '-'))));
			$timeOffEndtime = "{$row['date']} ".date('H:i:s', strtotime(substr($row['timeofday'], strpos($row['timeofday'], '-')+1)));
		}
		$timeOffDates[$row['date']] = 1;
		$filter[] = 
			"(date = '{$row['date']}'"
			.($row['timeofday'] 
				? /*" AND ((starttime >{$strictly} '$timeOffStarttime' AND starttime <{$strictly} '$timeOffEndtime')
									OR ('$timeOffStarttime' >{$strictly} starttime AND '$timeOffStarttime' <{$strictly} endtime)))"*/
					// logic: visit start is in timeoff timeframe
					//				OR visit end time  is in timeoff timeframe
					//				OR timeoff start time  is in visit timeframe
					//				OR timeoff end time  is in visit timeframe
					//				OR timeoff timeframe matches visit timeframe
					" AND ((starttime >{$strictly} '$timeOffStarttime' AND starttime <{$strictly} '$timeOffEndtime')
									OR (CONCAT_WS(' ', if(endtime < starttime, DATE_ADD(date, INTERVAL 1 DAY), date), endtime) >{$strictly} '$timeOffStarttime'  
										AND CONCAT_WS(' ', if(endtime < starttime, DATE_ADD(date, INTERVAL 1 DAY), date), endtime) <{$strictly} '$timeOffEndtime')
									OR ('$timeOffStarttime' >{$strictly} starttime 
										AND '$timeOffStarttime' <{$strictly} CONCAT_WS(' ', if(endtime < starttime, DATE_ADD(date, INTERVAL 1 DAY), date), endtime))
									OR ('$timeOffEndtime' >{$strictly} starttime 
										AND '$timeOffEndtime' <{$strictly} CONCAT_WS(' ', if(endtime < starttime, DATE_ADD(date, INTERVAL 1 DAY), date), endtime))
									OR ('$timeOffStarttime' = starttime 
										AND '$timeOffEndtime' = CONCAT_WS(' ', if(endtime < starttime, DATE_ADD(date, INTERVAL 1 DAY), date), endtime))
								))"
				: ')');
				
	}
	$filter = join(' OR ', $filter);
	
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') logError('filter: '.$filter);
	$pname = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = $provider LIMIT 1", 1);
	
	// reassign unassigned appointments for provider that no longer fall on days off
	$reassignfilter = $filter ? "AND NOT ($filter)" : '';
	$oldUnassignedAppts = join(',', $oldUnassignedAppts);
	
	if($oldUnassignedAppts) {
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {print_r(getProviderTimeOff($provider, false)); echo str_replace("\n", "<br>", "<p>$filter<p>");/*print_r(fetchCol0($sql));*/exit;}						

		$mods = withModificationFields(array('providerptr' => $provider, 'custom' => 1));
		$unwiped = fetchCol0("SELECT appointmentid FROM tblappointment WHERE appointmentid in ($oldUnassignedAppts) $reassignfilter");
		updateTable('tblappointment', $mods, "appointmentid in ($oldUnassignedAppts) $reassignfilter", 1);
		if($unwiped) deleteTable('relwipedappointment', "appointmentptr IN (".join(',', $unwiped).")", 1);
		if($_SESSION['surchargesenabled']) {
			require_once "surcharge-fns.php";
			updateAppointmentAutoSurchargesWhere("appointmentid in ($oldUnassignedAppts) $reassignfilter");
		}
		// CANCELLATION status is unchanged, so no discount action is necessary
if($unwiped && mattOnlyTEST()) {  // Why does this do nothing?
		$clientVisitsReassigned = fetchKeyValuePairs($sql =
			"SELECT clientptr, COUNT(*) 
			FROM tblappointment 
			WHERE appointmentid IN (".join(',', $unwiped).")
			GROUP BY clientptr", 1);
//echo "$sql<p>".print_r($clientVisitsReassigned, 1);exit;			
		foreach($clientVisitsReassigned as $clientptr => $visitCount)
			logChange($clientptr, 'tblclient', 'm', $note="$visitCount visits reassigned to $pname (time off change)");
}
		
	}
	
	if(!$filter) return 0;  // no appointments to unassign
	
	$unassignfilter = "providerptr = $provider AND date >= CURDATE() AND ($filter)";
	
	$clientVisitsUnassigned = fetchKeyValuePairs(
		"SELECT clientptr, COUNT(*) 
		FROM tblappointment 
		WHERE ".tzAdjustedSql($unassignfilter)
		." GROUP BY clientptr", 1);

	$wipelist = fetchCol0($sql = "SELECT appointmentid FROM tblappointment WHERE ".tzAdjustedSql($unassignfilter), 1);
	$wipeTime = date('Y-m-d H:i:s');
	foreach($wipelist as $wiped) {
		// NEVER TESTED if(dbTEST('poochydoos')) deleteTable('relwipedappointment', "appointmentptr = $wiped", 1);
		replaceTable('relwipedappointment', array('providerptr'=>$provider, 'appointmentptr'=>$wiped, 'time'=>	$wipeTime), 1);
	}
	if(staffOnlyTEST()) {
		$specificReportFilter = tzAdjustedSql($unassignfilter);
		if($reportOnThisDateOnly) $specificReportFilter = "date = '$reportOnThisDateOnly' AND ($specificReportFilter)";
		$numAffectedAppointments = fetchRow0Col0("SELECT count(*) FROM tblappointment WHERE $specificReportFilter", 1);
		//print_r($specificReportFilter);
	}
	
	// 2019-08-01 Change: do NOT mark recurring visits modified, 
	// because this causes a lot of extra conflict resolution work
	$mods = withModificationFields(array('providerptr' => 0));
	updateTable('tblappointment', $mods, tzAdjustedSql("$unassignfilter AND recurringpackage=1"), 1);
	if(!staffOnlyTEST()) $numAffectedAppointments += mysql_affected_rows(); // MIS-REPORTS NUM ROWS CHANGED!
	
	$mods = withModificationFields(array('providerptr' => 0, 'custom' => 1));
	updateTable('tblappointment', $mods, tzAdjustedSql("$unassignfilter AND recurringpackage=0"), 1);
	if(!staffOnlyTEST()) $numAffectedAppointments += mysql_affected_rows(); // MIS-REPORTS NUM ROWS CHANGED!
	
	// Find the day's surcharges for the provider
	if($timeOffDates && $wipelist) {
		// $wipelist is the list of unassigned visits
		$timeOffDates = "'".join("','", array_keys($timeOffDates))."'";
		$dayChargeIds = fetchCOl0(
			"SELECT surchargeid 
				FROM tblsurcharge
				WHERE providerptr = '$provider'
					AND date IN ($timeOffDates)", 1);
		foreach($dayChargeIds as $surchargeid) {
			$causes = justifySurcharge($surchargeid);
			if(count($causes) == count(array_intersect($causes, $wipelist)))
				updateTable('tblsurcharge', $mods, "surchargeid = $surchargeid", 1);
		}
	}
	
	logChange($provider, 'tblprovider', 'm', $note="$numAffectedAppointments unassigned (time off: $unassignfilter)");
	foreach($clientVisitsUnassigned as $clientptr => $visitCount)
		logChange($clientptr, 'tblclient', 'm', $note="$visitCount visits unassigned (time off: $pname)");
//if(mattOnlyTEST()) echo print_r(fetchAssociations("SELECT * FROM tblappointment WHERE ".tzAdjustedSql($unassignfilter)),1);			
//if(mattOnlyTEST()) replaceTable('tblpreference', array('property'=>'000TEST', 'value'=> print_r("SELECT * FROM tblappointment WHERE ".tzAdjustedSql($unassignfilter),1)), 1);			
//if(mattOnlyTEST()) replaceTable('tblpreference', array('property'=>'000TEST', 'value'=> print_r(fetchAssociations("SELECT * FROM tblappointment WHERE ".tzAdjustedSql($unassignfilter)),1)), 1);			
//if(mattOnlyTEST()) { echo tzAdjustedSql($unassignfilter).": <p>$numAffectedAppointments"; exit;}
	if($_SESSION['surchargesenabled']) {
		require_once "surcharge-fns.php";
		updateAppointmentAutoSurchargesWhere($unassignfilter);
	}
	
	// CANCELLATION status is unchanged, so no discount action is necessary
	return $numAffectedAppointments;
}

function timeOffDescriptions($prov, $start, $end) {  // used in dragdrop-fns.php
	$breaks = getProviderTimeOffInRange($prov, array($start, $end));
	if(!$breaks) return array();
	foreach($breaks as $break) $breakids[] = $break['timeoffid'];
	$patterns = fetchAssociationsKeyedBy(
		"SELECT p.* 
			FROM tbltimeoffinstance
			LEFT JOIN tbltimeoffpattern p ON patternid = patternptr
			WHERE timeoffid IN (".join(',', $breakids).")", 
		'patternid');
	foreach($breaks as $i => $break) // replace breaks with patterns where TOD's match
		if($break['patternptr'] 
			) //&& $patterns[$break['patternptr']]['timeofday'] == $break['timeofday'])
		   $breakids[$i] = $patterns[$break['patternptr']];
	foreach($breaks as $break) {
		$tod = $break['timeofday'] ? $break['timeofday'] : 'All Day';
		if($break['patternptr']) {
			if(!$shown[$break['patternptr']]) {
				$str = patternDescription($patterns[$break['patternptr']], null, 'noCount')." ($tod)";
				$shown[$break['patternptr']] = 1;
			}
			else continue;
		}
		else $str = longDayAndDate(strtotime($break['date']))." ($tod)";
		$arr[] = $str;
	}
	return (array)$arr;
}

function patternDescription($opat, $full=false, $noCount=false) {
	global $totalInstances;
	$patdow = date('D', strtotime($opat['date']));
	$patterns = array('dom'=>'same day of month', 'everyday'=>'every day');
	$prefix = '';
	foreach(explode('|', 'every|1st|2nd|3rd|4th|last') as $opt) {
		$patterns[$opt] = "$prefix$opt $patdow each month";
		$prefix = 'every ';
	}
	if(!$noCount) $totalInstances = fetchRow0Col0("SELECT count(*) FROM tbltimeoffinstance WHERE patternptr = {$opat['patternid']}");
	$patStart = shortestDate(strtotime($opat['date']));
	$patUntil = shortestDate(strtotime($opat['until']));
	if($patterns[$opat['pattern']]) $patterndesc = $patterns[$opat['pattern']];
	else {
		$dayNames = explode(',', 'Su,M,Tu,W,Th,F,Sa');
		$ws = explode(',', substr($opat['pattern'], strlen('days_')));
		foreach($ws as $w) $dayLabel[] = $dayNames[$w];
		$patterndesc = 'on '.join(', ', (array)$dayLabel);
	}
	$patterndesc = "$patStart to $patUntil $patterndesc";
	if(!$noCount) $patterndesc .= " (Total days off: $totalInstances)";
	if($full) $patterndesc = ($opat['timeofday'] ? $opat['timeofday'] : 'All Day').' from '.$patterndesc;
	return $patterndesc;
}


/* ######### END NEW TIME OFF ###################### */


function updateTimeOffData($timeoffdata, $provider) {
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {print_r($timeoffdata);exit;}	
	// $timeoffdata = [ deletions|deletedRow1|deletedRow2|deletedRown,rowId|firstday|lastday,rowId|firstday|lastday,...]
	$commands = explode(',',$timeoffdata);
	$times = fetchAssociationsKeyedBy("SELECT * FROM tbltimeoff WHERE providerptr = $provider ORDER BY firstdayoff, timeofday", 'timeoffid');
	$delta = array();
	foreach($commands as $str) {
		$cmd = explode('|', $str);
		if($cmd[0] == 'deletions') {
			if(count($cmd) < 2 || !$cmd[1]) continue;
			unset($cmd[0]);
			$targets = "timeoffid IN (".join(',', $cmd).")";
//echo ">>>";print_r("$str: $targets");		
			$delta['deletions'] = fetchAssociations("SELECT * FROM tbltimeoff WHERE $targets ORDER BY firstdayoff");;
			deleteTable('tbltimeoff', $targets, $showErrors=0);
			continue;
		}
		$vals = array('firstdayoff' => date('Y-m-d', strtotime($cmd[1])), 
									'lastdayoff' => date('Y-m-d', strtotime($cmd[2])),
									'lastdayoff' => date('Y-m-d', strtotime($cmd[2])), 
									'whenscheduled'=> date('Y-m-d H:i:s'));
		if(count($cmd) > 3) $vals['timeofday'] = $cmd[3];
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {echo print_r($times[$cmd[0]],1).' = '.print_r($vals,1).'<p>';exit;}
		
		if($cmd[0] && 
				($times[$cmd[0]]['firstdayoff'] != $vals['firstdayoff'] 
					|| $times[$cmd[0]]['lastdayoff'] != $vals['lastdayoff'] 
					|| $times[$cmd[0]]['timeofday'] != $vals['timeofday'])) {
			$delta['changes'][] = $vals;
			updateTable('tbltimeoff', $vals, "timeoffid = {$cmd[0]}", 0);
			if(mysql_errno() && (mysql_errno() != 1022)) showSQLError("update tbltimeoff: ".print_r($vals, 1)," WHERE timeoffid = {$cmd[0]}");
		}
		else if(!$cmd[0]) {
			$vals['providerptr'] = $provider;
			$delta['insertions'][] = $vals;
			insertTable('tbltimeoff', $vals, 0);
			if(mysql_errno() && (mysql_errno() != 1062)) showSQLError("insert tbltimeoff: ".print_r($vals, 1));
		}
	}
	return $delta;
}

function timeOffOneLineDescription($timeoff) {
	$firstDay = longestDayAndDate(strtotime($timeoff['firstdayoff']));
	$lastDay = longestDayAndDate(strtotime($timeoff['lastdayoff']));
	$range = $firstDay == $lastDay ? $firstDay : "$firstDay to $lastDay";
	$hours = $timeoff['timeofday'] ? $timeoff['timeofday'] : 'all day';
  return "$range Hours: $hours";
}


/* ******** END OLD TIME OFF *********************** */

function unassignAllAppointmentsForProvider($providerid, $clientptr=null) {
	// delete future incomplete jobs for provider
	$forClient = $clientptr ? "AND clientptr = $clientptr" : '';
	if($_SESSION['surchargesenabled']) {
		require_once "surcharge-fns.php";
		//updateAppointmentAutoSurchargesWhere(
		//	tzAdjustedSql("providerptr = $providerid AND completed IS NULL $forClient
		//							AND (date >= CURDATE() OR (date = CURDATE() AND starttime >= CURTIME()))"));
		// we cannot call updateAppointmentAutoSurchargesWhere($condition) here becuase provider still owns the visits so...
		$surchargeapptids = fetchCol0($sql = 
			"SELECT appointmentid FROM tblappointment 
				WHERE providerptr = $providerid 
					AND completed IS NULL $forClient
					AND (date >= CURDATE() OR (date = CURDATE() AND starttime >= CURTIME()))");		
	}
//if(mattOnlyTEST()) {echo $sql.'<hr>'.print_r($surchargeappts,1);exit;}
	deleteTable('relwipedappointment', "providerptr = $providerid", 1);
	$mods = withModificationFields(array('providerptr' => 0));
	updateTable('tblappointment', $mods, 
									tzAdjustedSql("providerptr = $providerid AND completed IS NULL $forClient
																	AND (date >= CURDATE() OR (date = CURDATE() AND starttime >= CURTIME()))"), 1);
	if($_SESSION['surchargesenabled']) 
		foreach($surchargeapptids as $appt) updateAppointmentAutoSurcharges($appt);

	// CANCELLATION status is unchanged, so no discount action is necessary									
	$forClient = !$clientptr ? ''
				: " for client ".fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = $clientptr LIMIT 1");
	logChange($providerid, 'tblprovider', 'm', "All appointments unassigned$forClient.");
}

function unassignAllServicesForProvider($providerid, $clientptr=null) {
	// clear providerptr in ALL future incomplete serviceschedules
	$forClient = $clientptr ? "AND tblservice.clientptr = $clientptr" : '';
	$sql = "SELECT serviceid FROM `tblservice` 
					LEFT JOIN tblservicepackage ON packageid = packageptr
					WHERE providerptr = $providerid $forClient
					AND recurring = 0 AND tblservice.current AND enddate >= CURDATE()";
	$services = fetchCol0(tzAdjustedSql($sql));
	$sql = "SELECT serviceid FROM `tblservice` 
					LEFT JOIN tblrecurringpackage ON packageid = packageptr
					WHERE providerptr = $providerid $forClient
					AND recurring = 1 AND (cancellationdate IS NULL OR cancellationdate >= CURDATE())";
	$services = array_merge($services, fetchCol0(tzAdjustedSql($sql)));
	$services = join(',', $services);
	if($services) updateTable('tblservice', withModificationFields(array('providerptr'=>0)), "serviceid IN ($services)", 1);
	$forClient = !$clientptr ? ''
				: " for client ".fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = $clientptr LIMIT 1");
	logChange($providerid, 'tblprovider', 'm', "All services unassigned$forClient.");
}

function unassignAllClientsForProvider($providerid) {
	// delete future incomplete jobs for provider
	$clients = fetchCol0("SELECT clientid FROM tblclient WHERE defaultproviderptr = $providerid");
	updateTable('tblclient', array('defaultproviderptr' => 0), 
									"defaultproviderptr = $providerid", 1);
	logChange($providerid, 'tblprovider', 'm', "All clients unassigned.");
	return $clients;
}

function appointmentsUnassignedFrom($provider) {
	// find all current services performed by $provider
	$services = fetchCol0("SELECT serviceid FROM tblservice WHERE current = 1 AND providerptr = $provider");
	if(!$services) return array();
	$services = join(',',$services);
	// find all appointments for these services that are unassigned
	return fetchAssociations(tzAdjustedSql(
		"SELECT appointmentid, date 
			FROM tblappointment 
			WHERE providerptr = 0 AND date >= CURDATE() AND serviceptr IN ($services) ORDER BY date"));
}

function getProviderDetails($ids, $additionalFields=null) {
	$additionalFields = $additionalFields ? $additionalFields : array();
	if(!$ids) return array();
	$joinPhrase = '';
	$phrases = ", CONCAT_WS(' ', fname, lname) as tblprovider";
	foreach($additionalFields as $field) {
		if($field == 'address')
			$phrases .= ", CONCAT_WS(', ', street1, street2, city) as address";
		else if($field == 'googleaddress')
			$phrases .= ", CONCAT_WS(', ', street1, street2, city, state, zip) as googleaddress";
		else if($field == 'phone')
			$phrases .= ", homephone, cellphone, workphone";
		else $phrases .= ", $field";
  }
	$details = fetchAssociationsKeyedBy("SELECT providerid $phrases
													FROM tblprovider WHERE providerid IN (".join(',', $ids).")", 'providerid');
														
	foreach($details as $key => $detail) {
		if(in_array('phone', $additionalFields))
			$details[$key]['phone'] = primaryPhoneNumber($detail);
	}
	return $details;
}									

function getProvidersForMonth($date) {
	$start = date('Y-m-1', strtotime($date));
	$end = date('Y-m-t', strtotime($date));
	$providers = fetchCol0("SELECT DISTINCT providerptr from tblappointment
				WHERE canceled IS NULL AND providerptr > 0 AND date >= '$start' AND date <= '$end'");
	return $providers;
}

function getProviderVisitCountForMonth($date, $byvisitcount=false) {
	$start = date('Y-m-1', strtotime($date));
	$end = date('Y-m-t', strtotime($date));
	if($byvisitcount) $byVisitCount= "visits DESC,";
	$providers = fetchAssociationsKeyedBy(
		"SELECT providerptr, count(*) AS visits, 
						CONCAT_WS(' ', fname, lname) AS name, CONCAT_WS(' ', lname, fname) AS sortname,
						sum(rate+ifnull(bonus,0)) as rate,
						sum(charge+ifnull(adjustment,0)) as charge
			FROM tblappointment
			LEFT JOIN tblprovider ON providerid = providerptr
			WHERE canceled IS NULL AND providerptr > 0 AND date >= '$start' AND date <= '$end'
			GROUP BY providerptr
			ORDER BY $byVisitCount lname, fname", 'providerptr');
	return $providers;
}

function getUnassignedVisitCountForMonth($date) {
	$start = date('Y-m-1', strtotime($date));
	$end = date('Y-m-t', strtotime($date));
	return fetchRow0Col0("SELECT count(*) FROM tblappointment WHERE providerptr = 0 AND date >= '$start' AND date <= '$end'");
}

function getVisitCountForMonth($date) {
	$start = date('Y-m-1', strtotime($date));
	$end = date('Y-m-t', strtotime($date));
	return fetchRow0Col0("SELECT count(*) FROM tblappointment WHERE canceled IS NULL AND date >= '$start' AND date <= '$end'");
}

function getTotalVisitCountForMonth($date) {
	$start = date('Y-m-1', strtotime($date));
	$end = date('Y-m-t', strtotime($date));
	return fetchRow0Col0("SELECT count(*) FROM tblappointment WHERE date >= '$start' AND date <= '$end'");
}

function getProviderZips($prov) {
	if($prov) return fetchCol0("SELECT zip from relproviderzip WHERE providerptr = $prov");
	return array();
}

function getZipProviders($zip) {
	return fetchCol0("SELECT providerptr from relproviderzip WHERE zip = '$zip'");
}

function wipeProvider($id) { // use with EXTREME caution
	$tables = explode(',', 'relproviderpayablepayment,relproviderrate,relreassignment,tblappointment,tblclientrequest,tblgratuity,'
		.'tblnegativecomp,tblothercomp,tblpayable,tblprovidermemo,tblproviderpayment,tblproviderpref,'
		.'tblservice,tblsurcharge,tbltimeoff');
	$allTables = fetchCol0("SHOW TABLES");
	
	foreach($tables as $table) if(in_array($table, $allTables)) deleteTable($table, "providerptr = $id", 1);
	//deleteTable("tblclient", "defaultproviderptr=$id", 1);
	deleteTable("tblmessage", "(correspid=$id AND correstable='tblprovider') OR (originatorid=$id AND originatortable='tblprovider')", 1);
	deleteTable("tblconfirmation", "respondentptr = $id AND respondenttable = 'tblprovider'", 1);
	deleteTable("tblprovider", "providerid = $id", 1);
}

function availableProviderSelectElement($client, $date=null, $elName, $nullChoice, $choice, $onchange, $offerUnassigned=null) {
	$options = availableProviderSelectElementOptions($client, $date, $nullChoice, false, $offerUnassigned);
	selectElement('', $elName, $choice, $options, $onchange);
}

function availableProviderSelectElementOptions($clientOrId, $date=null, $nullChoice, $noZIPSection=false, $offerUnassigned=null) {
	$pastProviders = array();
	$pastProviderIds =  array();
	$preferredProviderIds = array();
	$preferredProviders = array();
	if($clientOrId) {
		$client =  is_array($clientOrId) ? $clientOrId : fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = $clientOrId LIMIT 1");
		if($client && $client['clientid']) {  // if new client array (without clientid) is passed in, stop here
			$zip = $noZIPSection ? null : $client['zip'];
			$clientid = $client['clientid'];
			$pastProviderIds = fetchCol0("SELECT DISTINCT providerptr FROM tblappointment WHERE clientptr = $clientid AND canceled IS NULL");
			
			// NEW - 2019-01-21
			$preferredProviderIds = getPreferredProviderIds($clientid);
			$defaultprovider = fetchFirstAssoc($sql = 
				"SELECT defaultproviderptr as providerid, p.active as activeprovider 
				FROM tblclient
				LEFT JOIN tblprovider p ON providerid = defaultproviderptr
				WHERE clientid = $clientid LIMIT 1", 1);
			if($defaultprovider && $defaultprovider['activeprovider'] && !in_array($defaultprovider['providerid'], $preferredProviderIds))
				$preferredProviderIds[] = $defaultprovider['providerid'];
				
			if($preferredProviderIds && ($defaultproviderptr = $defaultprovider['providerid'])) {
				$ppcopy = array($defaultproviderptr);
				foreach($preferredProviderIds as $id)
					if($id != $defaultproviderptr)
						$ppcopy[] = $id;
				$preferredProviderIds = $ppcopy;
			}


		}
	}
	$activeProviders = getActiveProviderSelections($date, $zip);
	
	// if any sitters have this client on the Do Not Serve list, remove them now
	if(TRUE || $_SESSION['preferences']['donotserveenabled']) {
		$decliners = providerIdsWhoWillNotServeClient($clientid);
		foreach($decliners as $providerptr) {
//if(mattOnlyTEST()) print_r($activeProviders);			
			if(in_array($providerptr, $activeProviders))
				unset($activeProviders[array_search($providerptr , $activeProviders)]);
				foreach($activeProviders as $k => $group) {
					if(is_array($group))
						unset($activeProviders[$k][array_search($providerptr , (array)($activeProviders[$k]))]);
				}
			}

	}
	
	if($activeProviders) {
		if(is_array(current($activeProviders))) {
			foreach($activeProviders as $a => $providers)
				foreach($providers as $i => $prov) {
					if(in_array($prov, $preferredProviderIds)) {
						unset($activeProviders[$a][$i]);
						$label = ($client['defaultproviderptr'] == $prov ? '* ' : '').$i;
						$preferredProviders[$label] = $prov;
					}
					else if(in_array($prov, $pastProviderIds)) {
						unset($activeProviders[$a][$i]);
						$label = ($client['defaultproviderptr'] == $prov ? '* ' : '').$i;
						$pastProviders[$label] = $prov;
					}
				}
				uksort($pastProviders, 'caseInsensitiveComparison');
				//uksort($preferredProviders, 'caseInsensitiveComparison');
				$preferredProviders = array_flip($preferredProviders);
				foreach($preferredProviderIds as $id)
					$sortedPreferredProviders[$preferredProviders[$id]] = $id;
				$preferredProviders = $sortedPreferredProviders;
		}
		else {
			foreach($activeProviders as $i => $prov) {
				if(in_array($prov, $preferredProviderIds)) {
						unset($activeProviders[$i]);
						$label = ($client['defaultproviderptr'] == $prov ? '* ' : '').$i;
						$preferredProviders[$label] = $prov;
					}
					else 
				if(in_array($prov, $pastProviderIds)) {
					unset($activeProviders[$i]);
					$label = ($client['defaultproviderptr'] == $prov ? '* ' : '').$i;
					$pastProviders[$label] = $prov;
				}
			}
			uksort($pastProviders, 'caseInsensitiveComparison');
			//uksort($preferredProviders, 'caseInsensitiveComparison');
			$preferredProviders = array_flip($preferredProviders);
			foreach($preferredProviderIds as $id)
				$sortedPreferredProviders[$preferredProviders[$id]] = $id;
			$preferredProviders = $sortedPreferredProviders;
			
		}
		$displayPastServiceData = /*staffOnlyTEST() || */dbTEST('lucyspetcare,sarahrichpetsitting,wisconsinpetcare');
		if(TRUE && $displayPastServiceData) {
			$datesAndCounts = $pastProviders ? serviceDataFor($clientid, $pastProviders) : array();
			foreach($pastProviders as $label => $id) {
				$date = $datesAndCounts[$id]['date'] ? date('n/j/y', strtotime($datesAndCounts[$id]['date'])) : '';
				$count = $date ? $datesAndCounts[$id]['num'] : 0;
				if($count > 99) $count = '100+';
				$label = $date && $count ? "$label ($count) $date" : $label;
				$newPastProviders[$label] = $id;
			}
			$pastProviders = (array)$newPastProviders;

			$datesAndCounts = $preferredProviders ? serviceDataFor($clientid, $preferredProviders) : array();
			foreach((array)$preferredProviders as $label => $id) {
				$date = $datesAndCounts[$id]['date'] ? date('n/j/y', strtotime($datesAndCounts[$id]['date'])) : '';
				$count = $date ? $datesAndCounts[$id]['num'] : 0;
				if($count > 99) $count = '100+';
				$label = $date && $count ? "$label ($count) $date" : $label;
				$newPreferredProviders[$label] = $id;
			}
			$preferredProviders = (array)$newPreferredProviders;
		}
		
		if($activeProviders['Other Sitters']) {
			foreach($activeProviders['Other Sitters'] as $i => $prov) {
				$label = ($client['defaultproviderptr'] == $prov ? '* ' : '').$i;
				$newOtherProviders[$label] = $prov;
			}
			uksort($newOtherProviders, 'caseInsensitiveComparison');
			$activeProviders['Other Sitters'] = $newOtherProviders;
		}
	}
	
	
if(TRUE || mattOnlyTEST()) {
	$options = array();
	if($preferredProviders) $options = array_merge(array('Preferred Sitters'=>$preferredProviders));
	if($pastProviders) $options = array_merge($options, array('Past Sitters'=>$pastProviders));
	if($options && $activeProviders) $options = array_merge($options, $activeProviders);
	else if(!$options) $options = $activeProviders;
} else {
	if($pastProviders) $options = array_merge(array('Past Sitters'=>$pastProviders), $activeProviders);
	else $options = $activeProviders;
}	
	if(!is_array($nullChoice)) $nullChoice = array($nullChoice => '');
	if($offerUnassigned) $nullChoice['Unassigned'] = -1;
	return array_merge($nullChoice, $options);
}

function allSittersSelectElementOptions() {
	$active = fetchKeyValuePairs(
		"SELECT CONCAT_WS(' ', fname, lname), providerid
			FROM tblprovider
			WHERE active = 1", 1);
	$inactive = fetchKeyValuePairs(
		"SELECT CONCAT_WS(' ', fname, lname), providerid
			FROM tblprovider
			WHERE active = 0", 1);
	if($active) $options['Active Sitters'] = $active;
	if($inactive) $options['Inactive Sitters'] = $inactive;
	return $options;
}


function serviceDataFor($clientid, $providerids) {
	return fetchAssociationsKeyedBy(
		"SELECT providerptr, MAX(date) as date, COUNT(*) as num
			FROM tblappointment 
			WHERE clientptr = $clientid AND canceled IS NULL
				AND providerptr IN (".join(',', $providerids).")
			GROUP BY providerptr
			ORDER BY date DESC", 'providerptr');
}

// ALTERNATE PROVIDER FUNCTIONS #########################
function findAlternateSitter($clientid, $ignoreSitter, $date) {
	$availableSitters = availableProviderSelectElementOptions($clientid, date('Y-m-d'), $nullChoice, $noZIPSection=false, $offerUnassigned=null);
	// availableSitters format [Past Sitters]=>.., (opt)[Other Sitters] or [Sitters]..., 
	// find most recent sitters
	$datewindowstart = date("Y-m-d", strtotime("- 365 days"));
	$today = date("Y-m-d");
	$recent = fetchKeyValuePairs($sql =
			"SELECT providerptr, date 
				FROM tblappointment
				WHERE date > '$datewindowstart' AND date <= '$today'
					AND clientptr = $clientid
					AND providerptr NOT IN (0, $ignoreSitter)
				ORDER BY date", 1);
	asort($recent);
	$recent = array_reverse($recent, 1);
//echo "$sql<p>RECENT:<p>".print_r($recent, 1);
	// find past sitter who is not $ignoreSitter
	$pastSitters = $availableSitters['Past Sitters'];
	if($pastSitters) foreach($recent as $mostRecent => $date) {
		if(in_array($mostRecent, $pastSitters)) {
			$alternate = $mostRecent;
			break;
		}
	}
//echo "<p>MOST RECENT: [$alternate]"; //$alternate = 0;
			
	// find nearest sitter who is not $ignoreSitter
	if(!$alternate) {
		$available = $availableSitters['Other Sitters']; // iff providerterritoriesenabled
		if(!$available) $available = $availableSitters['Sitters'];
		if(!$available) return;
//echo "AVAIL ($clientid) 1: ".print_r(array_values($available), 1).'<p>';
		$byDistance = sittersByDistanceFrom($clientid, $available);
		if(is_string($byDistance)) return "No prev. sitters available, and no client address.";
		else if($byDistance) {
//echo "<p>DISTANCES: <p>";print_r($byDistance,1).'<p>';			
			$closeSitterKeys = array_keys($byDistance);
			$alternate = $closeSitterKeys[0];
		}
	}
	if($alternate) 
		return fetchFirstAssoc("SELECT * FROM tblprovider WHERE providerid = $alternate LIMIT 1", 1);
}

function sittersByDistanceFrom($clientid, &$available) {
	if(!$available) return array();
	$client = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = $clientid", 1);
	$clientAddress = googleAddressLocal($client);
	if($clientAddress) $clientGeocode = getLatLonLocal($clientAddress);
	if(!$clientGeocode) return "no client address";
	$distance = array();
	$sitters = fetchAssociationsKeyedBy(
		"SELECT * FROM tblprovider WHERE providerid IN (".join(', ',$available).")", 'providerid');
	global $units;
	$units = 'mi';
	foreach($sitters as $sitter) {
		if($sitterAddr = googleAddressLocal($sitter))
			$distance[$sitter['providerid']] = distanceLocal($clientGeocode, $sitterAddr);
	}
	if($distance) {
		asort($distance);
	foreach((array)$distance as $k => $d)
		if($d == 1000000) $distance[$k] = '--';
	}
	return $distance;
}

function googleAddressLocal($person) {
	if($person) $person = array_map('trim', $person);
	if($person['city'] && $person['state'] ) $addr = "{$person['street1']}, {$person['city']}, {$person['state']}";
	else $addr = "{$person['street1']} {$person['zip']}";
	return $addr;
}

function distanceLocal($clientGeocode, $sitterAddr) {
	global $units;
	$geocode = getLatLonLocal($sitterAddr);
	if($geocode['lat'] == 0 && $geocode['lon'] == 0) return 1000000;
	$lat1 = $geocode['lat'];
	$lon1 = $geocode['lon'];
	$lat2 = $clientGeocode['lat'];
	$lon2 = $clientGeocode['lon'];
	
  $dist = round(rad2deg(acos(sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon1 - $lon2)))) * 60 * 1.1515, 1);
//echo "[$dist] $sitterAddr: ".print_r($geocode, 1).'<br>';
	return $units == 'mi' ? $dist : $dist * 1.609344; // in miles
}

function getLatLonLocal($toAddress) {
	require_once('google-map-utils.php');
	return getLatLon($toAddress);
}

function OLDgetLatLonLocal($toAddress) {
	require_once('GoogleMapAPIv3.php');
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	static $map;
	global $dbuser, $dbpass, $dbhost, $db;
	if(!$map) {
    $map = new GoogleMapAPI('xyz');
    $map->setDSN("mysql://$dbuser:$dbpass@$dbhost/$db");
    $map->_db_cache_table = 'geocodes';
	}
	$coords = $map->getGeocode($toAddress);
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1); 
	return $coords;
}
// END ALTERNATE PROVIDER FUNCTIONS #########################


// BLACKLIST / DO NOT SERVE FNS #########################

function updateSittersDoNotServeList($id, $entries, $severConnections=false) {
	$sitterName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = $id");
	$oldBlacklist = doNotServeClientIds($id);
	$newBlackList = array();
	foreach($entries as $k => $v)
		if(strpos($k, 'dns_') === 0)
			$newBlackList[] = substr($k, strlen('dns_'));
			
	deleteTable('tblproviderpref', "providerptr = $id AND property LIKE 'donotserve_%'");
	foreach($oldBlacklist as $oldid) {
		if(!in_array($oldid, $newBlackList)) {
			logChange($oldid, 'clientblacklist', 'd', "Removed from sitter [$sitterName's] do not serve list");
			$restoredClients[] = $oldid;
		}
	}
	if($restoredClients) $restoredClients = 
		join(', ', fetchCol0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid IN (".join(',',$restoredClients).")"));
	if($restoredClients) logChange($id, 'sitterblacklist', 'd', "Removed [$restoredClients] from do not serve list");
	
	foreach($newBlackList as $clientid) {
		if(!in_array($clientid, $oldBlacklist)) {
			logChange($clientid, 'clientblacklist', 'c', "Added to sitter [$sitterName's] do not serve list");
			$blacklistedClients[] = $clientid;
		}
		insertTable('tblproviderpref', 
								array('providerptr'=>$id,
												'property'=>"donotserve_$clientid",
												'value'=>1), 1);
	}
	if($blacklistedClients) {
		if($severConnections) foreach($blacklistedClients as $clientid) severProviderClientConnections($id, $clientid);
		$blacklistedClientsNames = 
			join(', ', fetchCol0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid IN (".join(',',$blacklistedClients).")"));
	}
	if($blacklistedClients) logChange($id, 'sitterblacklist', 'c', "Added [$blacklistedClientsNames] to do not serve list");
	return $blacklistedClients;
}

function severProviderClientConnections($providerid, $clientptr) {
	unassignAllAppointmentsForProvider($providerid, $clientptr);
	unassignAllServicesForProvider($providerid, $clientptr);
	updateTable('tblclient', array('defaultproviderptr' => 0), 
									"clientid = $clientptr AND defaultproviderptr = $providerid", 1);
	if(mysql_affected_rows() > 0) 
		logChange($providerid, 'tblprovider', 'm', "Unassigned as default for $clientptr.");
}
	
function doNotServeClientIds($provid) {
	if(!$provid) return array();
	return fetchCol0("SELECT SUBSTRING(property FROM 12) FROM tblproviderpref WHERE providerptr = $provid AND property LIKE 'donotserve_%'");
}

function doNotServeClientReasons($provid) {
	if(!$provid) return array();
	return fetchKeyValuePairs("SELECT SUBSTRING(property FROM 12), value FROM tblproviderpref WHERE providerptr = $provid AND property LIKE 'donotserve_%'");
}

function providerIdsWhoWillNotServeClient($clientid) {
	return fetchCol0("SELECT providerptr FROM tblproviderpref WHERE property = 'donotserve_$clientid'");
}

function providerNamesWhoWillNotServeClient($clientid) {
	return fetchKeyValuePairs(
		"SELECT providerptr, CONCAT_WS(' ', fname, lname) 
			FROM tblproviderpref 
			LEFT JOIN tblprovider ON providerid = providerptr
			WHERE property = 'donotserve_$clientid'
			ORDER BY lname, fname");
}

function updateClientsDoNotAssignList($clientid, $entries, $severConnections=false) {
	$clientName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = $clientid");
	$oldBlacklist = providerIdsWhoWillNotServeClient($clientid);
	$newBlackList = array();
	foreach($entries as $k => $v)
		if(strpos($k, 'dna_') === 0)
			$newBlackList[] = substr($k, strlen('dna_'));
//if(mattOnlyTEST()) echo "[".print_r($entries, 1)."]<p>";
	foreach($oldBlacklist as $provid)
		deleteTable('tblproviderpref', "providerptr = $provid AND property LIKE 'donotserve_$clientid'");
		
	foreach($oldBlacklist as $oldid) {
		if(!in_array($oldid, $newBlackList)) {
			logChange($oldid, 'clientblacklist', 'd', "Removed from client [$clientName's] do not assign list");
			$restoredSitters[] = $oldid;
		}
	}
	if($restoredSitters) $restoredSitters = 
		join(', ', fetchCol0("SELECT CONCAT_WS(' ', fname, lname), 1 FROM tblprovider WHERE providerid IN (".join(',',$restoredSitters).")"));
	if($restoredSitters) logChange($clientid, 'sitterblacklist', 'd', "Removed [$restoredSitters] from do not assign list");
	
	foreach($newBlackList as $provid) {
		if(!in_array($provid, $oldBlacklist)) {
			logChange($provid, 'clientblacklist', 'c', "Added to sitter [$clientName's] do not assign list");
			$blacklistedSitters[] = $provid;
		}
		insertTable('tblproviderpref', 
								array('providerptr'=>$provid,
												'property'=>"donotserve_$clientid",
												'value'=>1), 1);
	}
	if($blacklistedSitters) {
		if($severConnections) foreach($blacklistedClients as $provid) severProviderClientConnections($provid, $clientid);
//if(mattOnlyTEST()) echo "[".print_r($blacklistedSitters, 1)."]<p>";
		$blacklistedSitterNames = 
			join(', ', fetchCol0("SELECT CONCAT_WS(' ', fname, lname), 2 FROM tblprovider WHERE providerid IN (".join(',',$blacklistedSitters).")"));
	}
	if($blacklistedSitters) logChange($clientid, 'sitterblacklist', 'c', "Added [$blacklistedSitterNames] to do not assign list");
	return $blacklistedSitters;
}



// end BLACKLIST / DO NOT SERVE FNS #########################


// PREFERRED PROVIDERS
function savePreferredProviderIds($clientid, $preferred) {
	require_once "preference-fns.php";
	$preferred = $preferred ? join(',', $preferred) : null;
	setClientPreference($clientid, 'preferredproviders', $preferred);
}

function getPreferredProviderIds($clientid) {
	if(!$clientid) return array();
	require_once "provider-fns.php";
	require_once "preference-fns.php";
	$preferred = getClientPreference($clientid, 'preferredproviders');
	if(!$preferred) return array();
	$preferred = explode(',', $preferred);
	$inactive = fetchCol0("SELECT providerid FROM tblprovider WHERE active=0 AND providerid IN (".join(',', $preferred).")", 1);
	$blackList = providerIdsWhoWillNotServeClient($clientid);
	
	$final = array();
	foreach($preferred as $id)
		if(!in_array($id, $blackList) && !in_array($id, $inactive)) $final[] = $id;
//echo "[$clientid] ".print_r($preferred, 1)." blacklist: [".print_r($blackList, 1)."]"." final: [".print_r($final, 1)."]";
	return $final;
}

function getPreferredClientIds($provid, $activeOnly=false) {
	if($activeOnly) 
		$activeOnly = " AND clientptr IN (".join(',', fetchCol0("SELECT clientid FROM tblclient WHERE active=1", 1)).")";
	$clientPrefs = fetchKeyValuePairs("SELECT clientptr, value FROM tblclientpref WHERE property = 'preferredproviders' $activeOnly", 1);
	foreach($clientPrefs as $clientid => $list) {
		//echo "($provid) $clientid => $list<br>";
		if(in_array($provid, explode(',', $list))) {
			$ids[] = $clientid;
		}
	}
	return (array)$ids;
}
	

// END PREFERRED PROVIDERS

// PROVIDER DOCUMENTS
// Each uploaded file for a provider will be represented by a provider property
// named doc_{remote_file_id}
// the value will be a JSON array: {fileid, label, officeonly, providerreadonly}
function setProviderDoc($provid, $fileid, $label, $officeonly, $providerreadonly) {
	require_once "preference-fns.php";
	$property = "doc_$fileid";
	$value = array('fileid'=>$fileid, 'label'=>$label, 'officeonly'=>$officeonly, 'providerreadonly'=>$providerreadonly);
	return setProviderPreference($provid, $property, json_encode($value));
}

function getBlankProviderDoc($provid, $fileid) {
	// TBD: figure out whether to surface officeonly and providerreadonly default values
	return array('fileid'=>$fileid, 'officeonly'=>$officeonly, 'providerreadonly'=>$providerreadonly);
}

function getProviderDoc($provid, $fileid) {
	require_once "preference-fns.php";
	$property = "doc_$fileid";
	if($provid) $doc = getProviderPreference($provid, $property);
	else $doc = findProviderDoc($fileid);
	return json_decode($doc, 'ASSOC');
}

function findProviderDoc($fileid) {
	// fileid shoud be unique
	$property = "doc_$fileid";
	$files = fetchRow0Col0(
		"SELECT providerptr
			FROM tblproviderpref
			WHERE property = '$property'
			LIMIT 1", 1);
}

function dropProviderDoc($provid, $fileid) {
	require_once "preference-fns.php";
	$property = "doc_$fileid";
	return setProviderPreference($provid, $property, null);
}

function getProviderFiles($provid) {
	return fetchKeyValuePairs(
		"SELECT remotefileid, remotepath 
			FROM tblremotefile
			WHERE ownerptr = $provid AND ownertable = 'tblprovider'
			ORDER BY remotepath", 1);
}
// END PROVIDER DOCUMENTS

// PROVIDER CHOICE WIDGET
function sitterChoiceWidget($clientptr, $sitterDivId, $columns, $sitterElementName, $onchange=null, $noEcho=false) {
	ob_start();
	ob_implicit_flush(0);
	$sitters = availableProviderSelectElementOptions($clientptr, $date=null, $nullChoice, $noZIPSection=false, $offerUnassigned=null);
	foreach($sitters as $i => $sitterids) {
		if($sitterids && !is_array($sitterids)) {
			$stragglers[$i] = $sitterids;
			unset($sitters[$i]);
		}
		else if($sitterids) $stragglersLabel = 'Others';
	}
	if($stragglers) {
		$stragglersLabel = $stragglersLabel ?$stragglersLabel : 'Sitters';
		$sitters[$stragglersLabel] = $stragglers;
	}
	echo "<table id='$sitterDivId' style='display:none'>";
	foreach($sitters as $key=>$nms) {
		if(!$key) continue;
		if(!$nms) continue;
		sitterGroupTableRow($key, $nms, $sitterElementName, $checkedsitters=null, $onchange, $columns);
	}
	echo "</table>";
	$contents = ob_get_contents();
	ob_end_clean();
	if($noEcho) return $contents;
	else echo $contents;
	/*
	SAMPLE USAGE:
	sitterChoiceWidget(957, 'sitterdiv', 2, 'sitters', $onchange='sitterClicked(this)');
  ...
	<script>
	function sitterClicked(el) {
		let nodes = document.getElementsByName('sitters[]');
		for(let i=0; i<nodes.length; i++)
			if(nodes[i].checked) alert(nodes[i].getAttribute('label'));
	}
	</script>
	*/
}

function sitterGroupTableRow($key, $groupsitters, $sitterElementName, $checkedsitters=null, $onchange=null, $columns=3) {
	$sitterRows = array_chunk($groupsitters, $columns, $preserve_keys=true);
	echo "<tr><td><table id='' style='display:block;background:LemonChiffon;'>";
	//echo "<tr><td class='fontSize1_2em'>".fauxLink('Hide This Section','toggleServiceTypes()', 1, 2)."</td></tr>";
	echo "<tr><td colspan=$columns style='font-weight:bold;'>$key</td></tr>";
	$onchange = $onchange ? "onchange='$onchange'" : '';
	foreach($sitterRows as $row) {
		echo "<tr>";
		foreach($row as $label => $provid) {
			$checked = in_array($provid, (array)$checkedsitters) ? 'CHECKED' : '';
			$safeLabel = safeValue($label);
			$checked = in_array($provid, (array)$checkedsitters) ? 'CHECKED' : '';
			$id = "{$sitterElementName}_$provid";
			$label = "<label for='$id'> $label</label>";
			echo "\n<td><input label='$safeLabel' name='{$sitterElementName}[]' $checked type='checkbox' id='$id' value='$provid' class='servicetype' $onchange> $label</td>";
		}
		echo "</tr>";
	}
	echo "</table></td></tr>";
}
// END PROVIDER CHOICE WIDGET
