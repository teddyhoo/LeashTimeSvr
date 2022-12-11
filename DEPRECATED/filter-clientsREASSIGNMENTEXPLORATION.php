<? // filter-clients.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "comm-fns.php";
require_once "client-fns.php";
require_once "js-gui-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out


$locked = locked('o-');//locked('o-'); 

$billingFlagsEnabled = $_SESSION['preferences']['betaBillingEnabled'];

extract(extractVars('start,end,addedOnOrAfter,status,prospect,havelogin,haveongoing,useflags,usebillingflags,chosensitter,pastvisits,futurevisits,reassignedvisits,defaultprovider,servicesInPeriod,creditcard,ach', $_REQUEST));

$servicesInPeriodTEST = /*staffOnlyTEST() ||*/ $_SESSION['preferences']['enableServicesInPeriodClientFilter'];

if($_POST) {
	$filterDescription = array();
	$filterDescription[] = ($status ? $status : 'all').' clients';
	$addedOnOrAfter = $addedOnOrAfter == 'undefined' ? null : $addedOnOrAfter;
	if($addedOnOrAfter)
		$filterDescription[] = " added on or after ".shortDate(strtotime($addedOnOrAfter));
	if($havelogin && staffOnlyTEST()) $filterDescription[] = 'with'.($havelogin == 'havelogin' ? '' : ' no').' login credentials';
	if($haveongoing) $filterDescription[] = 'with'.($haveongoing == 'haveongoing' ? '' : ' no').' ongoing schedule';
	$filter = array();
	if($start) $filter[] = "date >= '".date('Y-m-d', strtotime($start))."'";
	if($end) $filter[] = "date <= '".date('Y-m-d', strtotime($end))."'";
	if($start || $end) {
		$servicesInPeriod = $servicesInPeriodTEST ? $servicesInPeriod : true;
		$no = $servicesInPeriod ? '' : ' no';
		$filterDescription[] = "with$no services on dates";
		if($start) $filterDescription[] = "starting ".shortDate(strtotime($start));
		if($end) $filterDescription[] = ($start ? 'and ' : '')."ending ".shortDate(strtotime($end));
	}
	$clientIds = array();
	if($filter) {
		$clientIds = 
			fetchCol0("SELECT DISTINCT clientptr 
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
									
	$checkDefaultProvider = $defaultprovider && $chosensitter;
	
	if(!$stop && ($status || $havelogin || $haveongoing || $prospect || $checkDefaultProvider || $addedOnOrAfter)) {
		$statusSQL = "SELECT clientid FROM tblclient WHERE 1=1";
		$statusSQL .= ($status ? " AND active = ".($status == 'active' ? 1 : 0) : '');
		$statusSQL .= ($havelogin == 'havelogin' ? " AND userid IS NOT NULL" : (
										$havelogin ? " AND userid IS NULL": ''));
		$statusSQL .= ($addedOnOrAfter ? " AND setupdate >= '".date('Y-m-d', strtotime($addedOnOrAfter))."'" : '');

		$statusSQL .= ($prospect == 'prospect' ? " AND prospect = 1" : (
										$prospect ? " AND prospect = 0": ''));
		$statusSQL .= ($checkDefaultProvider ? " AND defaultproviderptr = $chosensitter" : '');
		$statusSQL .= " ORDER BY lname, fname";
		$clientIds = 
			$clientIds 
				? array_unique(array_intersect($clientIds,  fetchCol0($statusSQL)))
				: fetchCol0($statusSQL);
		$stop = count($clientIds) == 0;
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
	
	if(!$stop && $chosensitter && ($pastvisits || $futurevisits || $reassignedvisits/*|| $defaultprovider*/)) {
		$today = date('Y-m-d');
		
		if($pastvisits) $pastFuture[] = "date < '$today'";
		if($futurevisits) $pastFuture[] = "date >= '$today'";
		//if($defaultprovider) $pastFuture[] = "defaultproviderptr = $chosensitter";
		$pastFuture = $pastFuture ? '('.join(' OR ', $pastFuture).')' : '1=1';
		if($reassignedvisits) $providerPhrase[] = "(SELECT origproviderptr FROM relreassignment WHERE appointmentptr = appointmentid) = $chosensitter";
		if($pastvisits || $futurevisits || !$providerPhrase) $providerPhrase[] = "providerptr = $chosensitter";
		$providerPhrase = "(".join(' OR ', array_reverse($providerPhrase)).")";
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
	
	
	
	if(!$start && !$end && !$status && !$havelogin && !$haveongoing && !$prospect && !$useflags && !$usebillingflags && !$chosensitter && !$pastvisits && !$futurevisits && !$addedOnOrAfter && !$creditcard && !$ach)
		$clientIds = fetchCol0("SELECT clientid FROM tblclient");
					
	$result = "<root><filter>".join(' ', $filterDescription)."</filter>"
						."<ids>".join(',', $clientIds)."</ids>"
						."<start>$start</start>"
						."<end>$start</end>"
						."<status>$status</status>"
						."<addedOnOrAfter>$addedOnOrAfter</addedOnOrAfter>"
						."</root>";
						
	echo "<script language='javascript'>if(window.opener.update) window.opener.update('filter', \"$result\");window.close();</script>";
	exit;
}
require "frame-bannerless.php";

?>
<h2>Find Clients</h2>
<form method='POST'>
<table>
<?
radioButtonRow('Who are:', 'status', $status, array('Active'=>'active','Inactive'=>'inactive','Either status'=>''), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null);
if(staffOnlyTEST()) radioButtonRow('Who have:', 'havelogin', $havelogin, array('login credentials'=>'havelogin','no login credentials'=>'nologin','Either status'=>''), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null);
if(staffOnlyTEST()) radioButtonRow('Who are:', 'prospect', $prospect, array('prospects'=>'prospect','actual clients'=>'actual','Either status'=>''), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null);
if(staffOnlyTEST() || $_SESSION['preferences']['ccGateway']) radioButtonRow('Who have:', 'creditcard', $prospect, array('credit cards on file'=>'creditcard','no cards'=>'nocard','Either status'=>''), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null);
if(staffOnlyTEST() || $_SESSION['preferences']['gatewayOfferACH']) radioButtonRow('Who have:', 'ach', $prospect, array('ACH info on file'=>'ach','no ACH info'=>'noach','Either status'=>''), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null);
radioButtonRow('Who have:', 'haveongoing', $haveongoing, array('repeating visits'=>'haveongoing','no repeating visits'=>'noongoing','Either status'=>''), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null);

$someSelected = $servicesInPeriodSelect ? ' SELECT' : '';
$noneSelected = !$servicesInPeriodSelect ? ' SELECT' : '';
$servicesInPeriodSelect = !$servicesInPeriodTEST ? '' : 
	"<select style='font-size:0.75em' name='servicesInPeriod'><OPTION value=1 $someSelected>some<OPTION value=0 $noneSelected>no</select>";
echo "<tr><td>With $servicesInPeriodSelect visits:</td><td>";
calendarSet('Starting:', 'start', $start, null, null, true, 'end');
echo "</td></tr><tr><td>&nbsp;</td><td>";
calendarSet(' and ending:', 'end', $end, null, null, true, null);
echo "</td></tr>";

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
	
if(mattOnlyTEST()) $sitterModes .= 
	labeledCheckbox('is serving the client now or in the future [MO]', 'reassignedvisits', $value=null, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=true, $title='Include clients served today and later by the sitter above')
	;

labelRow('', '', $sitterModes, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=true);

if($_SESSION["flags_enabled"]) {
	require_once "client-flag-fns.php";
	$flags = getBizFlagList();
	if($flags) {
		$options = array('Does not matter'=>null, 'No flags'=>'none', 'Any of the selected'=>'or', 'All of the selected'=>'and');
		$options['None of the selected'] = 'nor';
		radioButtonRow('Flagged as follows:', 'useflags', $value=null, $options, $onClick=null, $labelClass=null, $inputClass=null,
			$rowId=null,  $rowStyle=null, $breakEveryN=2);
		echo "<tr><td>&nbsp;</td><td>";
		foreach($flags as $flag) {
			if(($col += 1) > 7) {
				echo "<br>";
				$col = 1;
			}
			echo " <input type='checkbox' id='flag_{$flag["flagid"]}' name='flag_{$flag["flagid"]}' onclick='flagClicked(this)'>
						<label for='flag_{$flag["flagid"]}'><img src='{$flag["src"]}' title='{$flag["title"]}'></label>";
		}
		echo "</td></tr>";
	}
	// Billing Flags
	if($billingFlagsEnabled ) {
		$flags = getBillingFlagList();
		$options = array('Does not matter'=>null, 'No flags'=>'none', 'Any of the selected'=>'or', 'All of the selected'=>'and');
		$options['None of the selected'] = 'nor';
		radioButtonRow('Flagged as follows:', 'usebillingflags', $value=null, $options, $onClick=null, $labelClass=null, $inputClass=null,
			$rowId=null,  $rowStyle=null, $breakEveryN=2);
		echo "<tr><td>&nbsp;</td><td>";
		$col = 1;
		foreach($flags as $flag) {
			if(($col += 1) > 7) {
				echo "<br>";
				$col = 1;
			}
			echo " <input type='checkbox' id='billing_flag_{$flag["flagid"]}' name='billing_flag_{$flag["flagid"]}' onclick='billingFlagClicked(this)'>
						<label for='billing_flag_{$flag["flagid"]}'><img src='{$flag["src"]}' title='{$flag["title"]}'></label>";
		}
		echo "</td></tr>";
	}
}





echo "<tr><td colspan=2 style='padding-top:20px'>";
echoButton('', 'Find Clients', 'document.forms[0].submit()');
echoButton('', 'Close', 'window.close()', 'closeButton', 'closeButtonDown');
echo "</td></tr>";
?>
</table>
</form>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
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
