<? // filter-clients.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "comm-fns.php";
require_once "client-fns.php";
require_once "js-gui-fns.php";
require_once "preference-fns.php";
require_once "key-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out


$locked = locked('o-');//locked('o-');

$_SESSION["preferences"] = fetchPreferences();

$billingFlagsEnabled = $_SESSION['preferences']['betaBillingEnabled'];

extract(extractVars($allFilterFields = 'start,end,addedOnOrAfter,status,prospect,havelogin,havetemppassword,haveongoing,havenonrecurring'
										.',useflags,usebillingflags,chosensitter,pastvisits,futurevisits,defaultprovider,wehavekeys'
										.',servicesInPeriod,creditcard,ach,addressFragment,withVisitsAsRecentAs,emailFragment,textable,customfieldsJSON', $_REQUEST));

$servicesInPeriodTEST = TRUE; // enabled for all 11/19/2020 /*staffOnlyTEST() ||*/ $_SESSION['preferences']['enableServicesInPeriodClientFilter'];
//$servicesInPeriodTEST = $servicesInPeriodTEST || mattOnlyTEST();

$textableTEST = staffOnlyTEST() || dbTEST('themonsterminders');

$safes = getKeySafes();

if($_POST) {
	$filterDescription = array();
	$filterDescription[] = ($status ? $status : 'all').' clients';
	$addedOnOrAfter = $addedOnOrAfter == 'undefined' ? null : $addedOnOrAfter;
	if($addedOnOrAfter)
		$filterDescription[] = " added on or after ".shortDate(strtotime($addedOnOrAfter));
	if($havelogin && staffOnlyTEST()) $filterDescription[] = 'with'.($havelogin == 'havelogin' ? '' : ' no').' login credentials';
	if($wehavekeys) $filterDescription[] = 'where'.
		($wehavekeys == 'wehavekeys' ? ' we have their keys' : (
		 $wehavekeys == 'nokeys' ? ' we do not have their keys' :
		 " at least one of their keys is in {$safes[$_POST['keysafe']]}"));
	if($textable && $textableTEST) $filterDescription[] = 'with'.($textable == 'textable' ? '' : ' no').' textable phone numbers';
	if($havetemppassword && staffOnlyTEST()) $filterDescription[] = 'with'.($havetemppassword == 'havetemppassword' ? '' : ' no').' temp password set';
	if($haveongoing) $filterDescription[] = 'with'.($haveongoing == 'haveongoing' ? '' : ' no').' ongoing schedule';
	if($havenonrecurring) $filterDescription[] = 'with'.($havenonrecurring == 'havenonrecurring' ? '' : ' no').' nonrecurring schedule';
	$filter = array();
	if($start) $filter[] = "date >= '".date('Y-m-d', strtotime($start))."'";
	if($end) $filter[] = "date <= '".date('Y-m-d', strtotime($end))."'";
	foreach($_POST as $k => $v) {
		if(strpos($k, 'servicecode_') === 0) {
			$servicecodes[] = substr($k, strlen('servicecode_'));
		}
	}
	if($servicecodes) {
		$servicecodeString = join(',', $servicecodes);
		$filter[] = "servicecode IN ($servicecodeString)";
	}
		
	
	$servicesInPeriod = $servicesInPeriodTEST ? $servicesInPeriod : true;
	if($start || $end) {
		$no = $servicesInPeriod ? '' : ' no';
		$filterDescription[] = "with$no services on dates";
		if($start) $filterDescription[] = "starting ".shortDate(strtotime($start));
		if($end) $filterDescription[] = ($start ? 'and ' : '')."ending ".shortDate(strtotime($end));
	}
	if($servicecodes) 
		$filterDescription[] = "with visit types: "
					.addslashes(join(', ', fetchCol0("SELECT label FROM tblservicetype WHERE servicetypeid IN ($servicecodeString)")));
		
	$clientIds = array();
	if($filter) {
		$clientIds = 
			fetchCol0($sql = "SELECT DISTINCT clientptr 
									FROM tblappointment 
									WHERE canceled IS NULL "
									. ($filter ? "AND ".join(' AND ', $filter) : ''));
		if(!$servicesInPeriod) {
			if($clientIds)
				$clientIds = 
					fetchCol0("SELECT clientid 
											FROM tblclient 
											WHERE clientid NOT IN ("
											.join(',', $clientIds).")", 1);
			else $clientIds = fetchCol0("SELECT clientid FROM tblclient", 1);
		}
		$stop = count($clientIds) == 0;
	}
	
	if(!$stop && $withVisitsAsRecentAs) {
		$filterDescription[] = "with visits as recent as: ".shortDate(strtotime($withVisitsAsRecentAs));
		$withVisitsAsRecentAs = date('Y-m-d', strtotime($withVisitsAsRecentAs));
		$recentClients = 
			fetchCol0($sql = "SELECT DISTINCT clientptr 
									FROM tblappointment 
									WHERE canceled IS NULL AND date >= '$withVisitsAsRecentAs'", 1);
		$clientIds = $clientIds ? array_intersect($clientIds, $recentClients) : $recentClients;
		$stop = count($clientIds) == 0;
}		
	}
									
	$checkDefaultProvider = $defaultprovider && $chosensitter;
	$addressFragment = $addressFragment ? mysqli_real_escape_string($addressFragment) : '';
	$emailFragment = $emailFragment ? mysqli_real_escape_string($emailFragment) : '';
	
	$anyTextableNumber = 
		"((homephone IS NOT NULL AND LOCATE('T', homephone) > 0 AND LOCATE('T', homephone) < 2 AND LOCATE('T', homephone) < LENGTH(homephone)) OR
		   (cellphone IS NOT NULL AND LOCATE('T', cellphone) > 0 AND LOCATE('T', cellphone) < 2 AND LOCATE('T', cellphone) < LENGTH(cellphone)) OR
		   (cellphone2 IS NOT NULL AND LOCATE('T', cellphone2) > 0 AND LOCATE('T', cellphone2) < 2 AND LOCATE('T', cellphone2) < LENGTH(cellphone2)) OR
		   (workphone IS NOT NULL AND LOCATE('T', workphone) > 0 AND LOCATE('T', workphone) < 2 AND LOCATE('T', workphone) < LENGTH(workphone)))";
	$noTextableNumber = "!$anyTextableNumber";
	$textablePrimaryNumber = str_replace('T', '*T', $anyTextableNumber);
	$noTextablePrimaryNumber = "!$textablePrimaryNumber";
	
	$textableFragment = $textable == 'textable' ? $anyTextableNumber : (
											$textable == 'notextable' ? $noTextableNumber
											: null);
	
	if(!$stop && ($status || $textable || $havelogin || $wehavekeys || $havetemppassword || $haveongoing || $havenonrecurring || $prospect || $checkDefaultProvider || $addedOnOrAfter || $addressFragment || $emailFragment)) {
		$statusSQL = "SELECT clientid FROM tblclient WHERE 1=1";
		$statusSQL .= ($status ? " AND active = ".($status == 'active' ? 1 : 0) : '');
		if($havetemppassword == 'havetemppassword') {
			$statusSQL .= " AND userid IS NOT NULL";
		}
		else {
			$statusSQL .= ($havelogin == 'havelogin' ? " AND userid IS NOT NULL" : (
										$havelogin ? " AND userid IS NULL": ''));
		}
		$statusSQL .= ($addedOnOrAfter ? " AND setupdate >= '".date('Y-m-d', strtotime($addedOnOrAfter))."'" : '');

		$statusSQL .= ($prospect == 'prospect' ? " AND prospect = 1" : (
										$prospect ? " AND prospect = 0": ''));
		$statusSQL .= ($checkDefaultProvider ? " AND defaultproviderptr = $chosensitter" : '');
		$statusSQL .= ($addressFragment ? " AND CONCAT_WS(' ', street1, street2, city, state, zip) LIKE '%$addressFragment%'" : '');
		$statusSQL .= ($emailFragment ? " AND (email LIKE '%$emailFragment%' OR email2 LIKE '%$emailFragment%')" : '');
		$statusSQL .= ($textableFragment? " AND $textableFragment" : '');
		$statusSQL .= " ORDER BY lname, fname";
		$clientIds = 
			$clientIds 
				? array_unique(array_intersect($clientIds,  fetchCol0($statusSQL)))
				: fetchCol0($statusSQL);

		//$stop = count($clientIds) == 0;
		if(!$stop && $havetemppassword) {
			// collect userids
			$userids = fetchKeyValuePairs("SELECT userid, clientid FROM tblclient WHERE clientid IN (".join(',', $clientIds).") AND userid IS NOT NULL", 1);
			list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
			require "common/init_db_common.php";
			$users = fetchKeyValuePairs(
				"SELECT userid, temppassword 
				FROM tbluser WHERE userid IN (".join(',', array_keys($userids)).")", 1);
			reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass, 1);
			$clientuserids = array_flip($userids);
			foreach($clientIds as $ci => $clientid) {
				if($havetemppassword == 'havetemppassword' && !$users[$clientuserids[$clientid]])
					unset($clientIds[$ci]);
				else if($havetemppassword != 'havetemppassword' && $users[$clientuserids[$clientid]])
					unset($clientIds[$ci]);
			}
			$clientIds = array_merge($clientIds); // to make sure count works right
			$stop = count($clientIds) == 0;
		}
}
	}
	else if(!$stop) {
		$statusSQL = "SELECT clientid FROM tblclient WHERE 1=1";
		$statusSQL .= " ORDER BY lname, fname";
		$clientIds = 
			$clientIds 
				? array_unique(array_intersect($clientIds,  fetchCol0($statusSQL)))
				: fetchCol0($statusSQL);
		$stop = count($clientIds) == 0;
	}
	if($wehavekeys) {
		// nokeys or wehavekeys
		// find all clientids with nonzero key copies
		//$safes[$_POST['keysafe']]
		$possessorConditions = array();
		for($kpi=1; $kpi<=getMaxKeyCopies(); $kpi+=1) {
			if(strpos($wehavekeys, 'keysafe') === 0)
				$possessorConditions[] = "(possessor$kpi = '{$_POST['keysafe']}')";
			else $possessorConditions[] = "(possessor$kpi IS NOT NULL AND possessor$kpi NOT IN ('missing', 'client'))";
		}
		$keyClientsInHand = fetchCol0($ksql = 
			"SELECT clientptr FROM tblkey WHERE copies > 0 AND (".join(" OR ", $possessorConditions).")"
			);
		//echo "[$wehavekeys] 65 is there: [".in_array(65, $clientIds)."] <p> 65 has keys: [".in_array(65, $keyClientsInHand)."] <p>$ksql";exit;
		if($wehavekeys == 'nokeys') $clientIds = array_diff($clientIds, $keyClientsInHand);
		else $clientIds = array_intersect($clientIds, $keyClientsInHand);
		//echo "wehavekeys: [$wehavekeys] keysafe: [{$_POST['keysafe']}]<hr>$ksql<hr>".count($keyClientsInHand)."<hr>".count($clientIds);exit;
		
	}
}
}
	if(!$stop && $haveongoing) {
		$today = date('Y-m-d');
		$ongoingClientIds = 
			fetchCol0("SELECT DISTINCT clientptr
									FROM tblrecurringpackage
									WHERE current=1 AND cancellationdate IS NULL");
//print_r($clientIds);exit;									
		if($haveongoing == 'noongoing') $clientIds = array_unique(array_diff((array)$clientIds, $ongoingClientIds));
		else $clientIds = array_unique(array_intersect((array)$clientIds, $ongoingClientIds));
		$stop = count($clientIds) == 0;
	}
	
	if(!$stop && $havenonrecurring) {
		$today = date('Y-m-d');
		$nonrecurringClientIds = 
			fetchCol0("SELECT DISTINCT clientptr
									FROM tblservicepackage
									WHERE current=1 AND cancellationdate IS NULL");
		if($havenonrecurring == 'nononrecurring') $clientIds = array_unique(array_diff((array)$clientIds, $nonrecurringClientIds));
		else $clientIds = array_unique(array_intersect((array)$clientIds, $nonrecurringClientIds));
		$stop = count($clientIds) == 0;
	}
	if(!$stop && $creditcard) {
		$activecreditcardClientIds =
			fetchCol0("SELECT DISTINCT clientptr
									FROM tblcreditcard
									WHERE active=1");
		if($creditcard == 'nocard') $clientIds = array_unique(array_diff((array)$clientIds, $activecreditcardClientIds));
		else $clientIds = array_unique(array_intersect((array)$clientIds, $activecreditcardClientIds));
		//echo "BANG! ($creditcard) ".count($activecreditcardClientIds)." clientIds: ".count($clientIds);exit;
		$stop = count($clientIds) == 0;
	}
	
	if(!$stop && $ach) {
		$activeACHClientIds =
			fetchCol0("SELECT DISTINCT clientptr
									FROM tblecheckacct
									WHERE active=1 AND primarypaysource=1");
		if($ach == 'noach') $clientIds = array_unique(array_diff((array)$clientIds, $activeACHClientIds));
		else $clientIds = array_unique(array_intersect((array)$clientIds, $activeACHClientIds));
		//echo "BANG! ($creditcard) ".count($activecreditcardClientIds)." clientIds: ".count($clientIds);exit;
		$stop = count($clientIds) == 0;
	}
	
	if(!$stop && $chosensitter && ($pastvisits || $futurevisits /*|| $defaultprovider*/)) {
		$today = date('Y-m-d');
		
		if($pastvisits) $pastFuture[] = "date < '$today'";
		if($futurevisits) $pastFuture[] = "date >= '$today'";
		//if($defaultprovider) $pastFuture[] = "defaultproviderptr = $chosensitter";
		$pastFuture = $pastFuture ? '('.join(' OR ', $pastFuture).')' : '1=1';
		$sitterClientIds = 
			fetchCol0("SELECT DISTINCT clientptr 
									FROM tblappointment"
									//.($defaultprovider ? " LEFT JOIN tblclient ON clientid = clientptr" : '')
									." WHERE canceled IS NULL 
											AND providerptr = $chosensitter
											AND $pastFuture");
//echo "<error>".print_r($sitterClientIds,1)."</error>";									
		$clientIds = array_unique(array_intersect((array)$clientIds, $sitterClientIds));
		$stop = count($clientIds) == 0;
	}
}
	if(!$stop && $_SESSION["flags_enabled"] && $useflags) {
		require_once "client-flag-fns.php";
		foreach($_POST as $key => $val)
			if(strpos($key, 'flag_') === 0)
				$flags[] = substr($key, strlen('flag_'));
		if($clientIds) $whereClause = "WHERE clientptr IN (".join(',',$clientIds).")";
		$sql = "SELECT clientptr, SUBSTRING(value, 1, LOCATE('|', value)-1)  as flag
							FROM tblclientpref 
							$whereClause";
		foreach(fetchAssociations($sql,1) as $row) 
			$allClientFlags[$row['clientptr']][] = $row['flag'];
		$clientIds = $clientIds ? $clientIds : fetchCol0("SELECT clientid FROM tblclient");
		if($usebillingflags == 'none') {
			$clientIds = array_diff($clientIds, array_keys($allClientFlags));
		}
		else {
			foreach($allClientFlags as $clientId => $clientFlags) {
				if($useflags == 'and' && count(array_intersect($flags, $clientFlags)) != count($flags))
					unset($allClientFlags[$clientId]);
				else if($useflags == 'or' && !array_intersect((array)$flags, (array)$clientFlags))
					unset($allClientFlags[$clientId]);
				else if($useflags == 'nor' && array_intersect((array)$flags, (array)$clientFlags))
					unset($allClientFlags[$clientId]);
			}
			$clientIds = array_unique(array_intersect((array)$clientIds, array_keys($allClientFlags)));
		}
	}
	
	if(!$stop && $billingFlagsEnabled && $usebillingflags) {
		require_once "client-flag-fns.php";
		$flags = array();
		foreach($_POST as $key => $val)
			if(strpos($key, 'billing_flag_') === 0)
				$flags[] = substr($key, strlen('billing_flag_'));
		$whereClause = $clientIds ? "WHERE clientptr IN (".join(',',$clientIds).") AND " : "WHERE ";
		$sql = "SELECT clientptr, SUBSTRING(property, 13+1) as flag
							FROM tblclientpref 
							$whereClause property LIKE 'billing_flag_%'";
		$allClientFlags = array();
		foreach(fetchAssociations($sql,1) as $row) 
			$allClientFlags[$row['clientptr']][] = $row['flag'];
			
		$clientIds = $clientIds ? $clientIds : fetchCol0("SELECT clientid FROM tblclient");
		if($usebillingflags == 'none') {
			$clientIds = array_diff($clientIds, array_keys($allClientFlags));
		}
		else {
			foreach($allClientFlags as $clientId => $clientFlags) {
				if($usebillingflags == 'and' && count(array_intersect($flags, $clientFlags)) != count($flags))
					unset($allClientFlags[$clientId]);
				else if($usebillingflags == 'or' && !array_intersect($flags, (array)$clientFlags))
					unset($allClientFlags[$clientId]);
				else if($usebillingflags == 'nor' && array_intersect($flags, (array)$clientFlags))
					unset($allClientFlags[$clientId]);
			}
			$clientIds = array_unique(array_intersect((array)$clientIds, array_keys($allClientFlags)));
		}
//echo "$usebillingflags: ".print_r($flags, 1)."<hr>".print_r($allClientFlags,1)."<hr>".print_r(count($clientIds),1);exit;							
	}
	
	
	if(!$start && !$end && !$servicecodes && !$status && !$havelogin && !$textable && !$havetemppassword && !$haveongoing && !$havenonrecurring && !$prospect && !$useflags && !$usebillingflags && !$chosensitter && !$pastvisits && !$futurevisits && !$addedOnOrAfter && !$creditcard && !$ach && !$addressFragment && !$emailFragment && !withVisitsAsRecentAs)
		$clientIds = fetchCol0("SELECT clientid FROM tblclient");
		
	if(!$stop && $customfieldsJSON) {
		require_once "custom-field-fns.php";
		$customtests = json_decode($customfieldsJSON, 'assoc');
		$filterDescription[] = "with custom fields: ".customFilterDescription($customtests, $maxFields=null);
}	
		$clientIds = filterByCustomFields($customtests, $clientIds);
}	
		$stop = count($clientIds) == 0;
	}
}	
		
	// ???	
	$preClean = $clientIds;
	$clientIds = array();
	foreach((array)$preClean as $id) if($id) $clientIds[] = $id;
	// ???
	unset($_SESSION['clientListIDString']);
	$_SESSION['clientListIDString'] = join(',', $clientIds);
	$_SESSION['clientFilterJSON'] = json_encode($_REQUEST);
	
	$result = "<root><filter><![CDATA[".join(' ', $filterDescription)."]]></filter>"
						.'<ids>IGNORE</ids>' // we will use $_SESSION['clientListIDString'] instead
						//."<ids>".join(',', $clientIds)."</ids>"
						."<resultCount>".count($clientIds)."</resultCount>"
						."<start>$start</start>"
						."<end>$start</end>"
						."<status>$status</status>"
						."<addedOnOrAfter>$addedOnOrAfter</addedOnOrAfter>"
						."</root>";

						
	echo "<script language='javascript'>if(window.opener.update) window.opener.update('filter', \"$result\");window.close();</script>";
	exit;
} // end if(POST)
else if($_SESSION['clientFilterJSON'])
	extract(json_decode($_SESSION['clientFilterJSON'], $assoc=1));



require "frame-bannerless.php";

?>
<h2>Find Clients</h2>
<form method='POST'>
<table>
<?
// all values from $_SESSION['clientFilterJSON']
radioButtonRow('Who are:', 'status', $status, array('Active'=>'active','Inactive'=>'inactive','Either status'=>''), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null);
if(staffOnlyTEST() || dbTEST('petsruspetsitting')) radioButtonRow('Who have:', 'havelogin', $havelogin, array('login credentials'=>'havelogin','no login credentials'=>'nologin','Either status'=>''), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null);
if(staffOnlyTEST() || dbTEST('petsruspetsitting')) radioButtonRow('Who have:', 'havetemppassword', $havetemppassword, array('temp password set'=>'havetemppassword','no password set'=>'haveotemppassword','Either status'=>''), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null);
if($textableTEST) radioButtonRow('Who have:', 'textable', $textable, array('at least one textable phone'=>'textable','no textable phone'=>'notextable','Either status'=>''), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null);
radioButtonRow('Who are:', 'prospect', $prospect, array('prospects'=>'prospect','actual clients'=>'actual','Either status'=>''), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null); // enabled for all 11/19/2020
if(staffOnlyTEST() || $_SESSION['preferences']['ccGateway']) radioButtonRow('Who have:', 'creditcard', $creditcard, array('credit cards on file'=>'creditcard','no cards'=>'nocard','Either status'=>''), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null);
if(staffOnlyTEST() || $_SESSION['preferences']['gatewayOfferACH']) radioButtonRow('Who have:', 'ach', $ach, array('ACH info on file'=>'ach','no ACH info'=>'noach','Either status'=>''), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null);
radioButtonRow('Who have:', 'haveongoing', $haveongoing, array('repeating visits'=>'haveongoing','no repeating visits'=>'noongoing','Either status'=>''), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null);
if(staffOnlyTEST() || dbTEST('petparadeplus,bestinshowpetsitting')) radioButtonRow('Who have:', 'havenonrecurring', $havenonrecurring, array('nonrepeating visits'=>'havenonrecurring','no nonrepeating visits'=>'nononrecurring','Either status'=>''), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null);
if($_SESSION['preferences']['enableWeHaveKeysClientFilter']) {
	$keyOptions = array('one or more of their keys'=>'wehavekeys','none of their keys'=>'nokeys','Either status'=>'');
	$keyOptions['a key in this safe:'] = 'keysafe';
	$safeOptions = array_merge(array('- choose a safe -'=>0), array_flip((array)$safes));
	$extraKeyContent = ' '.labeledSelect('', 'keysafe', $value=$keysafe, $options=$safeOptions, $labelClass=null, $inputClass=null, 
		$onChange='if(this.selectedIndex > 0) $("#wehavekeys_keysafe").prop("checked", true)', 
		$noEcho=true);
	radioButtonRow('Where we have:', 'wehavekeys', $wehavekeys, $keyOptions, $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $breakEveryN=3, $nonBreakingSpaceLabels=true, $extraKeyContent);
}	

if(staffOnlyTEST()) {
	require_once "custom-field-fns.php";
	hiddenElement('customfieldsJSON', $customfieldsJSON);
	$customfieldsJSON = $customfieldsJSON ? json_decode($customfieldsJSON, 'assoc') : null;
	$customLabel = $customfieldsJSON ? customFilterDescription($customfieldsJSON) : 'Filter by custom fields';
	// custom filter will get last settings from $_SESSION['clientFilterJSON']
	//function fauxLink($label, $onClick, $noEcho=false, $title=null, $id=null, $class=null, $style=null)

	$customfieldsLink = fauxLink($customLabel, 'customFilter()', 'noecho', 'Filter based on custom fields', 'customfieldsLink');
	labelRow('With custom fields:', 'customfieldsJSONLinkCell', $customfieldsLink, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false);
}

inputRow('With address containing: ', 'addressFragment', $addressFragment, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null, $extraContent=null, $inputCellPrepend=null);
if(staffOnlyTEST()) inputRow('With email address containing: ', 'emailFragment', $emailFragment, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null, $extraContent=null, $inputCellPrepend=null);// || dbTEST('petparadeplus')

$someSelected = $servicesInPeriodSelect ? ' SELECT' : '';
$noneSelected = !$servicesInPeriodSelect ? ' SELECT' : '';
$servicesInPeriodSelect = !$servicesInPeriodTEST ? '' : 
	"<select style='font-size:0.75em' name='servicesInPeriod'><OPTION value=1 $someSelected>some<OPTION value=0 $noneSelected>no</select>";
echo "<tr><td>With $servicesInPeriodSelect visits:</td><td>";
calendarSet('Starting:', 'start', $start, null, null, true, 'end');
echo "</td></tr><tr><td>&nbsp;</td><td>";
calendarSet(' and ending:', 'end', $end, null, null, true, null);
echo "</td></tr>";

if(TRUE || staffOnlyTEST()) { // enabled at Ted's request 5/26/2016
	echo "<tr><td>With visits of type:</td><td style='padding-top:5px;'><div id='selectedtypeslabel'></div>";
	fauxLink('Pick service types...', 'showHideServiceTypes(this)', $noEcho=false, $title='Select Service Types', $id='showhide', $class=null, $style=null);
	//echo " (requires Starting and/or Edning dates be specified.)"
	echo "<div id='servicetypes' style='display:none;background:palegreen;'>";
	// services checkboxes
	echo "<hr>";
	$serviceTypes = fetchAssociations("SELECT * FROM tblservicetype ORDER BY active DESC, label");
	foreach($serviceTypes as $type) {
		if(($col += 1) > 3) {
			echo "<br>";
			$col = 1;
		}
		$nameId = "servicecode_{$type["servicetypeid"]}";
		$style = $type['active'] ? '' : "style='font-style:italic';";
		$safeLabel = safeValue($type['label']);
		echo " <input type='checkbox' id='$nameId' name='$nameId' onclick='servicecodeClicked(this)' label='$safeLabel'>
					<label for='$nameId' $style>{$type['label']}</label>";
	}
	echo "<br>";
	fauxLink('Hide service types...', 'showHideServiceTypes(null)', $noEcho=false, $title='Select Service Types', $id='showhide', $class=null, $style=null);
	echo "<hr>";

	echo "</div></td></tr>";
}

//if($_SESSION['preferences']['enableVisitsRecentAsInClientFilter']) { // $withVisitsAsRecentAs
// // enabled for all 11/19/2020
	echo "<tr><td>With visits as recent as:</td><td>";
	calendarSet('', 'withVisitsAsRecentAs', ($withVisitsAsRecentAs == 'undefined' ? null : $withVisitsAsRecentAs));
	echo "</td></tr>";
//}




if(TRUE) {
	echo "<tr><td>Added on/after:</td><td>";
	calendarSet('', 'addedOnOrAfter', ($addedOnOrAfter == 'undefined' ? null : $addedOnOrAfter));
	echo "</td></tr>";
}
require_once "provider-fns.php";
$sitterOptions['-- Select a Sitter --'] = 0;;
$sitterOptions['Active Sitters'] = array_flip(getProviderShortNames("WHERE active=1 ORDER BY name"));
$sitterOptions['Inactive Sitters'] = array_flip(getProviderShortNames("WHERE active=0 ORDER BY name"));
if(!$sitterOptions['Inactive Sitters']) unset($sitterOptions['Inactive Sitters']);
selectRow('Where Sitter', 'chosensitter', $value=null, $sitterOptions, $onChange=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $extraTDs=null);
$sitterModes = 
	labeledCheckbox('is the client&apos;s default sitter', 'defaultprovider', $value=null, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=true, $title='Include clients whose default sitter is the sitter above')
	.'<br>'
	.labeledCheckbox('has served the client before', 'pastvisits', $value=null, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=true, $title='Include clients served in the past by the sitter above')
	.'<br>'
	.labeledCheckbox('is serving the client now or in the future', 'futurevisits', $value=null, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=true, $title='Include clients served today and later by the sitter above')
	;

labelRow('', '', $sitterModes, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=true);

if($_SESSION["flags_enabled"]) {
	require_once "client-flag-fns.php";
	$flags = getBizFlagList();
	if($flags) {
		$options = array('Does not matter'=>null, 'No flags'=>'none', 'Any of the selected'=>'or', 'All of the selected'=>'and');
		$options['None of the selected'] = 'nor';
		radioButtonRow('Flagged as follows:', 'useflags', $value=null, $options, $onClick=null, $labelClass=null, $inputClass=null,
			$rowId=null,  $rowStyle=null, $breakEveryN=5);
		echo "<tr><td>&nbsp;</td><td>";
		$col = 0;
		foreach($flags as $flag) {
			if(($col += 1) > 12) {
				echo "<br>";
				$col = 1;
			}
			echo " <input type='checkbox' id='flag_{$flag["flagid"]}' name='flag_{$flag["flagid"]}' onclick='flagClicked(this)'>
						<label for='flag_{$flag["flagid"]}'><img src='{$flag["src"]}' title='{$flag["title"]}'></label>";
		}
		echo "</td></tr>";
	}
	// Billing Flags
	if($billingFlagsEnabled && ($flags = getBillingFlagList())) {
		$options = array('Does not matter'=>null, 'No flags'=>'none', 'Any of the selected'=>'or', 'All of the selected'=>'and');
		$options['None of the selected'] = 'nor';
		radioButtonRow('Flagged as follows:', 'usebillingflags', $value=null, $options, $onClick=null, $labelClass=null, $inputClass=null,
			$rowId=null,  $rowStyle=null, $breakEveryN=5);
		echo "<tr><td>&nbsp;</td><td>";
		$col = 0;
		foreach($flags as $flag) {
			if(($col += 1) > 15) {
				echo "<br>";
				$col = 1;
			}
			echo " <input type='checkbox' id='billing_flag_{$flag["flagid"]}' name='billing_flag_{$flag["flagid"]}' onclick='billingFlagClicked(this)'>
						<label for='billing_flag_{$flag["flagid"]}'><img src='{$flag["src"]}' width=20 height=20 title='{$flag["title"]}'></label>";
		}
		echo "</td></tr>";
	}
}





echo "<tr><td colspan=2 style='padding-top:20px'>";
echoButton('', 'Find Clients', 'mySubmit()');
echoButton('', 'Close', 'window.close()', 'closeButton', 'closeButtonDown');
echo "</td></tr>";
?>
</table>
</form>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
<script type="text/javascript" src="colorbox/version1.3.19/jquery.colorbox-min.js"></script>	
<script language='javascript'>
function mySubmit() {
	document.forms[0].submit();
}

function customFilter() {
	var kludge = $('#customfieldsJSON').val();
	$.fn.colorbox({href:'filter-clients-custom-fields.php?filter='+kludge, width:'750', height:'750', iframe: true, scrolling: true, opacity: '0.3'});
}

function updateCustomFilter(fields, label) {
	//alert(label);
	$('#customfieldsJSON').val(JSON.stringify(fields));
	$('#customfieldsLink').html(label);
}

function showHideServiceTypes(el) {
	if(el == null) el = document.getElementById('showhide');
	if(el.innerHTML.indexOf('Pick') != -1) {
		document.getElementById('servicetypes').style.display='block';
		el.innerHTML = 'Hide service types...';
	}
	else {
		document.getElementById('servicetypes').style.display='none';
		el.innerHTML = 'Pick service types...';
	}
}

function servicecodeClicked(el) {
	var els = document.forms[0].elements;
	var labels = new Array();
	for(var i=0; i< els.length; i++) {
		if(els[i].id && els[i].id.indexOf('servicecode_') == 0 && els[i].checked) {
			labels[labels.length] = els[i].getAttribute('label');
		}
	}
	document.getElementById('selectedtypeslabel').innerHTML = 
		labels.length > 0 ? labels.join(', ') : '<i>none<i>';
}
		

function flagClicked(el) {
	if(!el.checked) return;
	if(!document.getElementById('useflags_or').checked 
		&& !document.getElementById('useflags_and').checked 
		&& !document.getElementById('useflags_nor').checked)
			document.getElementById('useflags_or').checked = true;
}

function billingFlagClicked(el) {
	if(!el.checked) return;
	if(!document.getElementById('usebillingflags_or').checked 
		&& !document.getElementById('usebillingflags_and').checked 
		&& !document.getElementById('usebillingflags_nor').checked)
			document.getElementById('usebillingflags_or').checked = true;
}


<?
dumpPopCalendarJS();
?>
</script>
