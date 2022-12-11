<? //provider-edit.php -- "includes Do Not Serve"
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require "zip-lookup.php";
require "provider-fns.php";
require "preference-fns.php";
require "service-fns.php";
require "pay-fns.php";
require_once "system-login-fns.php";


// Determine access privs
$locked = locked('+o-,+d-,#as');
$readOnly = userRole() == 'd' && !strpos($_SESSION['rights'], '#es');

extract($_REQUEST);  // if POSTed from here, id will be null, but providerid may be set

if(staffOnlyTEST() && $_GET['changelog']) {
	$sitter =  fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = $id LIMIT 1", 1);
	$changes = fetchAssociationsKeyedBy("SELECT * FROM tblchangelog WHERE itemtable = 'tblprovider' AND itemptr = $id", 'userptr', 1);
	if($changes) {
		foreach($changes as $ch) $users[] = $ch['user'];
		require "common/init_db_common.php";
		$users = fetchKeyValuePairs("SELECT userid, CONCAT_WS(' ', fname, lname) FROM tbluser WHERE userid IN ("
						.join(',', $users).")");
		foreach($changes as $i => $ch) {
			$changes[$i]['user'] = $users[$ch['user']];
			$time = $ch['time'];
			unset($ch['time']);
			$ch = array_merge(array('time'=>$time), $ch);
			$changes[$i ] = $ch;
		}
		echo "<h2>$sitter's Change History</h2>";
		quickTable($changes, "border=1 bordercolor=black", $style=null, $repeatHeaders=0);
	}
	else echo "No entries found for $sitter";
	exit;
}
		
	

$suppressPayTab = userRole() == 'd' && !adequateRights('#pa');

$id = isset($id) ? $id : null;
$savedProvider = $id ? getProvider($id) : array();
$unassignedProvider = $id == -1;

$breadcrumbs = "<a href='provider-list.php'>Sitters</a>";
if($id) {
	$starting = "&starting=".shortDate();
	$shortName = $id == -1 ? 'Unassigned Visits' : providerShortName($savedProvider)."'s Schedule";
	$breadcrumbs .= " - <a href='prov-schedule-cal.php?provider=$id$starting'>$shortName</a>";
	
	
	if(staffOnlyTEST() || $_SESSION['preferences']['offerSitterNearbyClientsMap']) $breadcrumbs .= " - ".fauxLink('Show Nearby Clients', 
																				"openConsoleWindow(\"nearbyclients\", \"client-cluster-map.php?pop=1&pid=$id\",800,800)", 1, "Open a map")." <span style='color:red;font-variant:small-caps;'>beta</span>";

	
}

$pageTitle = $savedProvider ? "Sitter: {$savedProvider['fname']} {$savedProvider['lname']}" 
															.($savedProvider['active'] ? '' : '<span class="warning"> (inactive)</span>')
														: ($unassignedProvider ? "Unassigned Sitter" : "New Sitter");

// We may wish to redisplay the submitted (unsaved) provider fields
$provider = $id > 0 ? array_merge($savedProvider) : array();
$message = '';

$activeParam = $savedProvider && !$savedProvider['active'] ? "inactive=1": "";
// =======================================================
if($_POST && isset($providerid)) {
	if((string)$pagetimestamp != (string)$_SESSION['provider_edit_timestamp']) {
		//echo "pagetimestamp [$pagetimestamp]  SESS: [{$_SESSION['provider_edit_timestamp']}]";
		header ("Location: provider-list.php?$activeParam");
		exit();
	}
	unset($_SESSION['provider_edit_timestamp']);
	if($providerid) {
		if($continueEditing == 'applyNewRates') {
			if(!$suppressPayTab) {
				setHourlyPreferences($providerid, $_POST);
				setProviderRates($providerid, $_POST['ratetype']);
			}
			setProviderZips($providerid, $_POST);
		}
		else {
			saveProvider();
			$savedProvider = $id ? getProvider($id) : array();
			$provider = $id > 0 ? array_merge($savedProvider) : array();
			
			if(TRUE || $_SESSION['preferences']['donotserveenabled']) {
				$newlyBlackListedClients = updateSittersDoNotServeList($providerid, $_POST, $severConnections=$active);
				if($newlyBlackListedClients && $active) {
					$clients = $newlyBlackListedClients ? "?provider=$providerid&clients=".join(',',$newlyBlackListedClients) : '';
					$clientNames = 
						fetchCol0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid IN (".join(',', $newlyBlackListedClients).")");
					$here = "<a class='fauxlink' href='client-orphan-list.php$clients' target='_top' title='Review these orphaned clients'>here</a>";
					$_SESSION['user_notice'] = "<div class='fontSize1_1em'>"
							.providerShortName($provider)." will no longer serve the following ".count($clients)
							." clients.<p>Click $here if you want to assign them new primary sitters.<ul>"
							.join('<li>', $clientNames).'</ul>'
							.'</div>';
				}
			}
			if(!$suppressPayTab) {
				setHourlyPreferences($providerid, $_POST);
				setProviderRates($providerid, $_POST['ratetype']);
			}
			setProviderZips($providerid, $_POST);
			
			if($id && $_SESSION['preferences']['enableSitterProfiles']) {
				require_once "provider-profile-fns.php";
				setProviderProfileFields($id, $_POST);
			}
			
			
			$oldUnassignedAppts = getUnassignedAppointmentIDsDuringTimeOff($providerid);  // collect unassigned appts which may be reassigned
			updateTimeOffData($timeoffdata, $providerid);
			$unassignedAppointments = applyProviderTimeOffToAppointments($providerid, $oldUnassignedAppts);
			if($_SESSION['secureKeyEnabled'] && $provider['userid']) {
				setKeyManagementProviderRights($provider['userid'], $_POST['keyManRights']);
			}
			if($_SESSION['preferences']['mobileSitterAppEnabled'] // MSA enabled
					&& $provider['userid'] && !$_SESSION['preferences']['mobileVersionPreferred']) { // ... and not all sitters are allowed access to MSA
				setUserPreference($provider['userid'], 'mobileVersionPreferred', $mobileappenabled);
			}
			//postcardsEnabled
			if($_SESSION['preferences']['postcardsEnabled'] // Postcards enabled
					&& $provider['userid']) { // ... and not all sitters are allowed access to postcards
				setUserPreference($provider['userid'], 'postcardsEnabled', ($postcardsEnabled ? 1 : null));
			}
			
			if($active != $provider['active']) setUserActive($provider['userid'], $active);
			
			if($_SESSION['preferences']['reportStaleVisits'] == 2) // operate only when "selected sitters" is in effect
				setProviderPreference($id, 'reportStaleVisits', ($reportStaleVisits ? 1 : null));
			
			
			if(!$active) {
				if($provider['active']) logChange($providerid, 'tblprovider', 'm', 'Deactivated');
				unassignAllAppointmentsForProvider($providerid);
				$clients = unassignAllClientsForProvider($providerid);
				if(!$clients) $clients = array(-1);
				unassignAllServicesForProvider($providerid);
				$msg = "<span style='font-size:1.5em;'>XXX</span>";
				if($clients[0] == -1) {
					$_SESSION['user_notice'] = str_replace('XXX', providerShortName($provider)." has been deactivated.", $msg);
					if($provider['active'] && mattOnlyTEST()) {
						$_SESSION['frame_message'] = providerShortName($provider)." has been deactivated.";
						header ("Location: provider-edit.php?id=$providerid");
					}
					else header ("Location: provider-list.php?$param&$activeParam");
				}
				else {
					$clients = $clients ? "?provider=$providerid&clients=".join(',',$clients) : '';
					$_SESSION['user_notice'] = 
						str_replace('XXX', 
												providerShortName($provider)."'s former clients no longer have a default sitter.\\n\\nYou may assign other sitters to them here.",
												$msg);
					header ("Location: client-orphan-list.php$clients");
				}
				exit;
			} // end inactive
		} // end NOT apply new rates
	}  // end old provider
	else {
		$newProviderId = saveNewProvider();
		if(!$suppressPayTab) {
			setProviderRates($newProviderId, $_POST['ratetype']);
			setHourlyPreferences($newProviderId, $_POST);
		}
		setProviderZips($newProviderId, $_POST);
		
		if($_SESSION['preferences']['reportStaleVisits'] == 2) // operate only when "selected sitters" is in effect
			setProviderPreference($newProviderId, 'reportStaleVisits', $reportStaleVisits);
		
		$message = "Sitter {$_POST['fname']} {$_POST['lname']} has been added.";
		if($continueEditing) {
			$message = htmlentities($message);
			if($continueEditing == 'another') $provider = array();
			else if($continueEditing == 'systemloginsetup') $tab = 'basic';
			$_SESSION['frame_message'] = $message;
			if($continueEditing == 'another')
				header ("Location: provider-edit.php");
			else 
				header ("Location: provider-edit.php?id=$newProviderId");
			exit;
			//if($continueEditing != 'another') {
				//$id = $newProviderId;
				//$savedProvider = $id ? getProvider($id) : array();
				//$provider = $savedProvider;
			//}
		}
	}
//print_r($_POST);exit;
  if(!$continueEditing) {
		$param = $newProviderId ? "newProvider=$newProviderId" : "savedProvider=$providerid";
		if($unassignedAppointments) $param .= "&unassignedAppointments=$unassignedAppointments";
		if(TRUE || mattOnlyTEST()) {
			$pname = providerShortName($newProviderId ? $newProviderId : $providerid);
			$_SESSION['frame_message'] = "{$_POST['fname']} {$_POST['lname']} has been updated.";
			header ("Location: provider-edit.php?id=".($newProviderId ? $newProviderId  : $providerid));
		}
		else header ("Location: provider-list.php?$param&$activeParam");
		exit();
	}
  else if($continueEditing == 'systemloginsetup'){
		header ("Location: provider-edit.php?id=$newProviderId&tab=$tab");
		exit();
	}
  else if($continueEditing == 'applyNewRates'){
		$tab = 'pay';
	}
  else if($continueEditing == 'uploadPhoto'){
		$tab = 'sitterprofile';
	}
	else {
		$_SESSION['frame_message'] = "{$_POST['fname']} {$_POST['lname']} has been updated.";
		$tab = $continueEditing;
	}
}

if($unassignedProvider) $tab = 'pay';


$message = $message ? $message : '&nbsp;';
$_SESSION['provider_edit_timestamp'] = microtime(1);
include "frame.html";
// ***************************************************************************
$suppressTabs = array();
$suppressFields = array();
if(userRole() == 'd' && !adequateRights('#es')) { //d-
	$suppressTabs = array('pay', 'history');
	$suppressFields = array('taxid');
	$readOnly = true;
}
if($suppressPayTab) $suppressTabs[] = 'pay';

?>
<table width=700>
<tr><td><?= $message ?></td>
<td style='text-align:right'> 
<?
$inactive = $id && !$client['active'] ? 1 : 0;

if($readOnly) {
	$saveButton = "<span 'background: pink'>Read Only</span>";
	$saveAndAddButton = '';
}
else {	
	$saveButton = $unassignedProvider 
												? ''
												: ($id ? echoButton('save', 'Save Changes', 'checkAndSubmit("")', null, null, 'noEcho')
															: 	echoButton('save', 'Save New Sitter', 'checkAndSubmit("")', null, null, 'noEcho'));
	$saveAndAddButton = !$id ? echoButton('saveandadd', 'Save & Add Another', 'checkAndSubmit("another")', null, null, 'noEcho') : '';
}

$quitButton = echoButton('', 'Quit', "document.location.href=\"provider-list.php?inactive=\"+(providereditor.active.checked ? 0 : 1)", null, null, 'noEcho');

if(staffOnlyTEST() && $id) {
	$url = "provider-edit.php?changelog=1&id=$id";
	fauxLink('Change Log', 
						'$.fn.colorbox({href:"'.$url.'", width:"750", height:"470", scrolling: true, opacity: "0.3"});'
						,null, 'LeashTime Staff use -- view change log.'
						
						);
	echo " ";
}
if($id) echo $saveButton;
else echo "$saveButton  $saveAndAddButton";
echo " $quitButton"; 

?>
</td></tr></table>
<?

$rawEmploymentFields = 'employeeid,Employee ID,jobtitle,Job Title,labortype,Labor Type,'.
	                 'noncompetesigned,Non-compete on file,active,Active,keyManRights,Key Management Rights,hiredate,Hire Date,'.
	                 'terminationdate,Termination Date,terminationreason,Termination Reason,'.
	                 'dailyvisitsemail,Email Daily Schedule,weeklyvisitsemail,Email Weekly Schedule';
$rawBasicNameAndAddressFields = 'nickname,Nickname,fname,First Name,lname,Last Name,zip,ZIP,street1,Address,street2,Address 2,city,City,state,State';  

$rawBasicOtherFields = 'cellphone,Cell Phone,homephone,Home Phone,workphone,Work Phone,'.
	               'fax,FAX,pager,Pager,email,Email,taxid,SSN,maritalstatus,Marital Status';
$rawBasicOtherFields = str_replace('SSN', getI18Property('Labels|ssnortaxid', 'SSN or Tax ID'), $rawBasicOtherFields);
$rawPayFields = 'paymethod,Payment Method,ddroutingnumber,Routing #,ddaccountnumber,Account #,'.
	               'ddaccounttype,Account Type,paynotification,Pay Notification'; //,ratetype,Rate Type';	               
$rawServiceTypeFields = '';

$providerNicknames = getProviderNicknames();
	               
	               
	               
$requiredFields = array('fname','lname');
$redStar = '<font color=red>*</font>';



$labelAndIds = !$unassignedProvider 
	? array("basic"=>'Basic Info', "employment"=>'Employment') 
	: array("basic"=>'Basic Info');
if(!in_array("pay", $suppressTabs)) $labelAndIds["pay"] = 'Pay';

if($id && !in_array("pay", $suppressTabs)) $labelAndIds = array_merge($labelAndIds, array("history"=>'Pay History'));
if($id && !$unassignedProvider) $labelAndIds = array_merge($labelAndIds, array("communication"=>'Communication', "timeoff"=>'Time Off'));
if(!$unassignedProvider && $_SESSION['providerterritoriesenabled']) 
	$labelAndIds = array_merge($labelAndIds, array("zipcodes"=>'ZIP Codes'));
if(!$unassignedProvider && $id && $_SESSION['preferences']['enableSitterProfiles'])
	$labelAndIds = array_merge($labelAndIds, array("sitterprofile"=>'Public Profile'));
$initialSelection = $tab ? $tab : 'basic';
$boxHeight = 300;

echo "<form name='providereditor' method='post'  enctype='multipart/form-data'>\n";
hiddenElement('pagetimestamp',$_SESSION['provider_edit_timestamp']);
hiddenElement('providerid',($id ? $id : ''));
hiddenElement('continueEditing');
startTabBox('providertabbox', $labelAndIds, $initialSelection, 100);

startFixedHeightTabPage('basic', $initialSelection, $labelAndIds, $boxHeight);
$notesInEmploymentTab	= /*mattOnlyTEST() ||*/ dbTEST('wisconsinpetcare');
if($unassignedProvider) dumpUnassignedBasicTab($provider);
else dumpProviderBasicTab($provider);
//$customSaveButton = customSaveButton('basic');
endTabPage('basic', $labelAndIds,  customSaveButton('basic'), $saveAndAddButton, $quitButton, true);

if(!$unassignedProvider) {
	startFixedHeightTabPage('employment', $initialSelection, $labelAndIds, $boxHeight);
	dumpProviderEmploymentTab($provider);
//$saveButton = echoButton('save', 'Save Changes', 'checkAndSubmit("basic")', null, null, 'noEcho')
	endTabPage('employment', $labelAndIds,  customSaveButton('employment'), $saveAndAddButton, $quitButton, true);
}
if(!in_array("pay", $suppressTabs)) {
	startFixedHeightTabPage('pay', $initialSelection, $labelAndIds, $boxHeight);
	if(!$unassignedProvider) {
		dumpProviderPayTab($provider);
	}
	else {
		standardPayRateDisplayTable();
	}
	echo "<p>";
	endTabPage('pay', $labelAndIds, customSaveButton('pay'), $saveAndAddButton, $quitButton, true);
}

if(!$unassignedProvider) {
	startFixedHeightTabPage('timeoff', $initialSelection, $labelAndIds, $boxHeight);
	
	dumpProviderTimeOffTab($provider);
	endTabPage('timeoff', $labelAndIds, customSaveButton('timeoff'), $saveAndAddButton, $quitButton, true);
}

if(!$unassignedProvider && $_SESSION['providerterritoriesenabled']) {
	startFixedHeightTabPage('zipcodes', $initialSelection, $labelAndIds, $boxHeight);
	dumpProviderZipcodeTab($provider);
	endTabPage('zipcodes', $labelAndIds, customSaveButton('zipcodes'), $saveAndAddButton, $quitButton, true);
}

if(!$unassignedProvider && $id && $_SESSION['preferences']['enableSitterProfiles']) {
	startFixedHeightTabPage('sitterprofile', $initialSelection, $labelAndIds, $boxHeight);
	require_once "provider-profile-fns.php";
	dumpProviderProfileTab($provider);
	endTabPage('sitterprofile', $labelAndIds, customSaveButton('sitterprofile'), $saveAndAddButton, $quitButton, true);
}

echo "</form>";
if($id) {
	if(!in_array("history", $suppressTabs)) {
		startFixedHeightTabPage('history', $initialSelection, $labelAndIds, $boxHeight);
		dumpProviderPayHistoryTab($provider);
		endTabPage('history', $labelAndIds, customSaveButton('history'), $saveAndAddButton, $quitButton, true);
	}
	startFixedHeightTabPage('communication', $initialSelection, $labelAndIds, $boxHeight);
	echo "<div id='providermsgs'></div>";
	endTabPage('communication', $labelAndIds, customSaveButton('communication'), $saveAndAddButton, $quitButton, true);
}

endTabBox();
echo "<br><img src='art/spacer.gif' width=1 height=100>";

function customSaveButton($tabName) {
	global $saveButton, $readOnly;
	if($readOnly) return $saveButton;
	return str_replace('checkAndSubmit("")', "checkAndSubmit(\"$tabName\")", $saveButton);
}

function setProviderZips($providerid, $zips) {
	deleteTable('relproviderzip', "providerptr = $providerid");
	foreach($zips as $k => $v) {
		if(strpos($k, 'zip_') === 0)
			insertTable('relproviderzip', array('zip'=>substr($k, strlen('zip_')), 'providerptr'=>$providerid), 1);
		else if(strpos($k, 'newzip_') === 0 && trim($v)) 
			insertTable('relproviderzip', array('zip'=>trim($v), 'providerptr'=>$providerid), 1);
	}
}

function dumpProviderZipcodeTab($provider) {
	/* preference: providerterritoriesenabled
CREATE TABLE IF NOT EXISTS `relproviderzip` (
  `providerptr` int(11) NOT NULL,
  `zip` int(11) NOT NULL,
  PRIMARY KEY (`providerptr`,`zip`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='zips the provider serves';
INSERT INTO tblpreference (property,value) VALUES ('providerterritoriesenabled','1');
	
	
	*/
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	
	$pZips = getProviderZips($provider['providerid']);
	echo "<h3>ZIP Codes</h3>";

	if($_SESSION['preferences']['restrictTerritory']) {
		$zips = fetchAssociationsKeyedBy("SELECT * FROM tblzipcodeslocal ORDER BY city, zip", 'zip');
		$allZips = fetchCol0("SELECT DISTINCT substring(zip, 1, 5) FROM tblclient WHERE length(trim(ifnull(zip, ''))) >= 6");
		$allZips = array_diff($allZips, array_keys($zips));
		if($allZips) {
			require "common/init_db_common.php";
			$zips = array_merge($zips, fetchCities($allZips));
		}
	}
	else {
		require_once "zip-lookup.php";
		$allZips = fetchCol0("SELECT DISTINCT substring(zip, 1, 5) FROM tblclient WHERE length(trim(ifnull(zip, ''))) >= 6");
		$allZips = array_merge($allZips, fetchCol0("SELECT DISTINCT zip FROM relproviderzip"));
		global $dbhost, $db, $dbuser, $dbpass;
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		require "common/init_db_common.php";
		$zips = fetchCities($allZips);
	}
	usort($zips, compareCities);
	$cols = array_chunk($zips, max(count($zips) / 3 + (count($zips) % 3 ? 1 : 0), 1));
	echo "<table><tr>";
	foreach($cols as $col) {
		echo "<td style='padding-left:30px;vertical-align:top;'>";
		foreach($col as $zip) {
			$checked = in_array($zip['zip'], $pZips) ? 1 : 0;
			labeledCheckbox("{$zip['city']} {$zip['zip']}", "zip_{$zip['zip']}", $checked, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true) ;
			echo "<br>";
		}
		echo "</td>";
	}
	echo "</tr></table>";
	if(!$_SESSION['preferences']['restrictTerritory']) {
		echo "<table><tr>";
		for($i=1; $i<=10; $i++) {
			echo "<tr id='ziprow_$i' style='display:none;'>";
			echo "<td>New ZIP code: <input name='newzip_$i' id='newzip_$i' onchange='lookupZip($i)' size=10 maxlen=5></td><td id='city_$i'></td></tr>";
		}
		echo "<tr id='nomore' style='display:none;'><td>To enter more zipcodes, please Save the provider and return to the ZIP Codes tab.</td></tr>";
		echo "<tr id='addanother'><td>".echoButton('', 'Add Another ZIP Code', "addAnotherZip($i)")."</td></tr>";
		echo "</table>";
		
	}
	echo "<p>External Link: <a target='hugemaps' href='http://maps.huge.info/zip.htm'>maps.huge.info</a>: a useful site to view a ZIP code maps.";	
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
}

function compareCities($a, $b) {
	return strcmp($a['city'], $b['city']);
}

function dumpProviderTimeOffTab($provider) {
	echo "<h3>Below are the scheduled time off dates for this sitter";
	echo "	
<div onclick=\"openConsoleWindow('timeoffcalendar', 'timeoff-sitter-calendar.php?provid={$provider['providerid']}&editable=1',850,700)\"
			style='display:inline-block;float:right;font-size:7pt;cursor:pointer;'>
			<img src='art/clock20.gif' title='Open the Sitter Time Off Calendar'> Click to view Sitter time off calendar.</div>
";
	echo "</h3>";
	//labeledCheckbox('Show past time off also', 'showpasttimeoff', null, null, null, 'updateProviderTimeOff()');
	hiddenElement('showpasttimeoff', '1');
	hiddenElement('timeoffdata', '');
	echo "<div id='timeoffdiv'></div>";
}

function dumpProviderPayHistoryTab($provider) {
	dumpProviderPayForm($provider);
}

function dumpProviderPayTab($provider) {
	global $id, $rawPayFields;
	$raw = explode(',', $rawPayFields);
	for($i=0;$i < count($raw) - 1; $i+=2) $fields[$raw[$i]] = $raw[$i+1];
	echo "Payment Method - for record keeping only<br>\n";
	labeledRadioButton('check', 'paymethod', 'check', $provider['paymethod']);
	echo "<br>\n";
	labeledRadioButton('Direct Deposit', 'paymethod', 'dd', $provider['paymethod']);
	echo "\n";
  labeledInput($fields['ddroutingnumber'], 'ddroutingnumber', $provider['ddroutingnumber']);
	echo "\n";
  labeledInput($fields['ddaccountnumber'], 'ddaccountnumber', $provider['ddaccountnumber']);
	echo "\n";
  selectElement($fields['ddaccounttype'], 'ddaccounttype', $provider['ddaccounttype'], array('checking'=>'checking','saving'=>'saving'));
	echo "<br>\n";
	labeledRadioButton('Paychex', 'paymethod', 'paychex', $provider['paymethod']);
	echo "<p><table width=100%>\n<tr><td style='vertical-align:top'>";
  selectElement($fields['paynotification'], 'paynotification', $provider['paynotification'], array('email'=>'email','mail'=>'mail','none'=>'none'));
	echo "<p>\n";
  //selectElement($fields['ratetype'], 'ratetype', $provider['ratetype'], array('commission'=>'commission','flat'=>'flat'));
	echo "</td>\n";
	echo "<td style='vertical-align:top;padding:0px'>\n";
	hourlyPayTable($provider);
	payRateTable($provider);
	echo "</td></tr></table>\n";
}

function hourlyPayTable($provider) {
	if(!$_SESSION['preferences']['sittersPaidHourly']) return;
	if($provider['providerid']) {
		$hourlyRate = getProviderPreference($provider['providerid'], 'hourlyRate');
		$travelAllowance = getProviderPreference($provider['providerid'], 'travelAllowance');
	}
	echo "<table style='width: 80%;background:lightgrey;'>\n";
	inputRow('Hourly pay rate:', 'hourlyRate', $hourlyRate, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null);
	inputRow('Travel Allowance per visit:', 'travelAllowance', $travelAllowance, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null);
	echo "</table><p>\n";
}

function setHourlyPreferences($providerid, $vals) {	
	if(!$_SESSION['preferences']['sittersPaidHourly']) return;
	setProviderPreference($providerid, 'hourlyRate', ($vals['hourlyRate'] ? $vals['hourlyRate'] : null));
	setProviderPreference($providerid, 'travelAllowance', ($vals['travelAllowance'] ? $vals['travelAllowance'] : null));
}

function payRateTable($provider) {
	if($_SESSION['preferences']['sittersPaidHourly']) 
		return payRateTableHourly($provider);

	global $rawServiceTypeFields;
	$standardRates = getStandardRates();
	$rates = getProviderRates($provider['providerid']);
	if(staffOnlyTEST()) {
		$historyLink = ' '.fauxLink('History',
			"openConsoleWindow(\"applyproviderrates\", \"provider-rates-history.php?id={$provider['providerid']}\",600,800);",
			'noecho', 'show change history (LT Staff Only)');
	}
	echo "<table style='width: 80%;background:lightgrey;'><tr><td colspan=3 align=center><b>Service Rates</td>$historyLink</tr>\n";
	$applyRatesButton = echoButton('', 'Apply New Rates...', 'applyNewRates()', null, null, 1);
	echo "<tr><th>$applyRatesButton</th><th>Standard Rate</th><th>Rate</th><th>%</th></tr>\n";
	foreach($standardRates as $key => $service) {
		$stndRate = $service['defaultrate'].($service['ispercentage'] ? '%' : '');
		$ispercentage = isset($rates[$key]) ? $rates[$key]['ispercentage'] : $service['ispercentage'];
		$rate = !isset($rates[$key]) ? '' : $rates[$key]['rate'].($ispercentage ? '%' : '');
		echo "<tr><td>{$service['label']}</td><td>$stndRate</td><td>";
		labeledInput('', 'servicerate_'.$key, $rate, null, 'dollarinput');
		echo "</td><td>";labeledCheckbox('', 'servicerateispercentage_'.$key, $ispercentage);
		$rawServiceTypeFields = ($rawServiceTypeFields ? "$rawServiceTypeFields|||" : '').'servicerate_'.$key.','.$service['label'];
		echo "</td></tr>\n";
	}
	echo "</table>\n";
}

function payRateTableHourly($provider) {
	global $rawServiceTypeFields;
	$standardRates = getStandardRates();
	$rates = getProviderRates($provider['providerid']);
	echo "<table style='width: 80%;background:lightgrey;'><tr><td colspan=3 align=center><b>Service Rates</td></tr>\n";
	$applyRatesButton = echoButton('', 'Apply New Rates...', 'applyNewRates()', null, null, 1);
	echo "<tr><th>$applyRatesButton</th><th>Hours</th><th>Rate</th></tr>\n";
	foreach($standardRates as $key => $service) {
		$stndRate = $service['defaultrate'].($service['ispercentage'] ? '%' : '');
		$hours = $service['hours'];
		$rate = !isset($rates[$key]) ? '' : $rates[$key]['rate'];
		echo "<tr><td>{$service['label']}</td><td>$hours</td><td>".dollarAmount($rate)."</td></tr>\n";
	}
	echo "</table>\n";
}

function standardPayRateDisplayTable() {
	global $rawServiceTypeFields;
	$standardRates = getStandardRates();
	//echo "<table style='width: 80%;background:lightgrey;'><tr><td colspan=3 align=center><b>Service Rates</td></tr>\n";
/*print_r($standardRates);*/
	$columns = explodePairsLine('label|Service||price|Price||rate|Standard Rate');
	foreach($standardRates as $key => $service) {
		$row = $service;
		$row['price'] = dollarAmount($service['defaultcharge']);
		$row['rate'] = $service['ispercentage']
										? " X ".($service['defaultrate'] % 10 ? sprintf("%.0f", $service['defaultrate']) : $service['defaultrate']).'% = '
													.dollarAmount($service['defaultcharge'] * $service['defaultrate'] / 100)
										: dollarAmount($service['defaultrate']);
		$rows[] = $row;
		$rowClasses[] = ($rowClass = ($rowClass == 'futuretaskEVEN' ? 'futuretask' : 'futuretaskEVEN'));
	}
	tableFrom($columns, $rows, "width=70% style='background:white;margin:20px;'",null,null,null,null,null,$rowClasses);
}


function dumpProviderEmploymentTab($provider) {
	global $id, $rawEmploymentFields;
	// two column table
	$raw = explode(',', $rawEmploymentFields);
	for($i=0;$i < count($raw) - 1; $i+=2) $fields[$raw[$i]] = $raw[$i+1];
	echo "<table width=100%><tr><td valign=top><table width=100%>\n"; // COLUMN 1
	foreach($fields as $key => $label) {
		if(in_array($key, array('dailyvisitsemail','weeklyvisitsemail', 'terminationreason'))) continue;
		$val = isset($provider[$key]) ? htmlentities($provider[$key]) : '';
		if(in_array($key, array('noncompetesigned','active'))) {
			if(!$id && ($key == 'active')) $val = 1;
			checkboxRow($label.':', $key, $val, $labelClass=null, $inputClass='standardInput');
		}
		else if($key == 'labortype') {
			$defaultLaborType = 'employee';  // TBD: make this configurable
			$val = $val ? $val : $defaultLaborType;
		  selectRow($label.':', $key, $val, array(''=>'','Employee'=>'employee','Contract'=>'contract'));
		}
		else if(strpos($key, 'date')) {
			calendarRow($label, $key, $val);
		}
		//else if($key== 'terminationreason') 
		//  inputRow($label.':', $key, $val, $labelClass=null, 'VeryLongInput');
		else if($key== 'keyManRights') {
			if($_SESSION['secureKeyEnabled'] && $provider['userid']) {
				$pRights = getProviderRights($provider['userid']);
				$val = strpos($pRights, 'ka') ? 'ka' : (strpos($pRights, 'ki') ? 'ki' : '');
				//radioButtonRow($label, $name, $value=null, $options, $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null)
		  	radioButtonRow($label.':', $key, $val, array('None'=>'', "Individual"=>'ki', "Admin"=>'ka'));
			}
		}
		else inputRow($label.':', $key, $val, $labelClass=null, $inputClass='standardInput');
	}
	echo "</table></td><td valign=top width=300><table width=100%>";  // COLUMN 2

  if(!isset($provider['dailyvisitsemail'])) $provider['dailyvisitsemail'] = $_SESSION['preferences']['scheduleDaily'];
  if(!isset($provider['weeklyvisitsemail'])) $provider['weeklyvisitsemail'] = ($_SESSION['preferences']['scheduleDay'] ? 1 : 0);
  //if(!array_key_exists('dailyvisitsemail', $provider)) $provider['dailyvisitsemail'] = $_SESSION['preferences']['scheduleDaily'] ? 1 : 0;
  //if(!array_key_exists('weeklyvisitsemail', $provider)) $provider['weeklyvisitsemail'] = $_SESSION['preferences']['scheduleDay'] ? 1 : 0;

	foreach($fields as $key => $label)
		if(in_array($key, array('dailyvisitsemail','weeklyvisitsemail')))
			checkboxRow($label.':', $key, $provider[$key], $labelClass=null, $inputClass='standardInput');
			
	if($id && $_SESSION['preferences']['reportStaleVisits'] == 2) {
		$reportStaleVisitsForProvider = getProviderPreference($id, 'reportStaleVisits', $arrayIfDefault=true);
		$reportStaleVisitsForProvider = is_array($reportStaleVisitsForProvider) ? 0 : $reportStaleVisitsForProvider;
		checkboxRow('Report overdue visits:', 'reportStaleVisits', $reportStaleVisitsForProvider, $labelClass=null, $inputClass='standardInput');
	}
	
	if(TRUE || staffOnlyTEST() || dbTEST('wisconsinpetcare,animalsreign,pawlosophy,nannydolittle,pawspetcare,animalsreign')) 
		//echo "<tr><td colspan=2>".fauxLink('Visit History', "$.fn.colorbox({href:\"reports-sitter-client-visits.php?prov=$id\", width:\"550\", height:\"470\", iframe: true, scrolling: true, opacity: \"0.3\"});", 1)."</td></tr>";
		echo "<tr><td colspan=2>".echoButton('', 'Visit History', "$.fn.colorbox({href:\"reports-sitter-client-visits.php?prov=$id\", width:\"550\", height:\"470\", iframe: true, scrolling: true, opacity: \"0.3\"});", null, null, 1, "View list of clients served")."</td></tr>";
	if(staffOnlyTEST() || dbTEST('careypet')) 
		echo "<tr><td colspan=2>".echoButton('', 'Sitter Snapshot', "openConsoleWindow(\"snapshot\", \"provider-snapshot.php?id=$id\", 600, 600);", null, null, 1, "View a sitter snapshot")."</td></tr>";

	global $notesInEmploymentTab;

	if($notesInEmploymentTab) {
		$notes = isset($provider['notes']) ? htmlentities($provider['notes']) : '';
		echo "<tr><td colspan=2 valign=top>Notes:<br><textarea name='notes' cols=40 rows=3>$notes</textarea></td></tr>";
	}
	
	echo "</table></td></tr><tr><td colspan=2><table width=100%>";  // ROW 2
	$key = 'terminationreason';
	inputRow($fields[$key].':', $key, htmlentities($provider[$key]), $labelClass=null, 'VeryLongInput');
	
	// BLACKLIST / DO NOT SERVE
	if($id) dumpDoNotServeRow(); //$_SESSION['preferences']['donotserveenabled']
	echo "</table></td></tr></table>\n"; // Employment tab
}

// BLACKLIST / DO NOT SERVE FNS #########################
function dumpDoNotServeRow() {
	global $id;
	$blackList = blackListTable();
	$addBlackListButton = echoButton('', 'Add Client to Do Not Serve List', 'chooseClientToBlackList()', null, null, 1, 'Remember to Save Changes afterward!');
	
	echo "<tr><td id='blacklistcell' colspan=2 style='background:lightpink'><hr>Do not Serve: $addBlackListButton<p>";
	echo "<div id='blacklistadditions' colspan=$numcols style='border-bottom:solid black 1px;'></div>";
	if(!$blackList) echo "<span class='tiplooks' id='anyclients'>Sitter will serve any client.</span>";
	else {
		echo "<p><span class='tiplooks'>Sitter's name will not appear in sitter menus for any of the clients checked.</span>";
		echo blackListTable();
	}
	echo "<hr></td></tr>";
}

function blackListTable() {
	global $id;
	$blackList = join(',', doNotServeClientIds($id));
	$blackListReasons = doNotServeClientReasons($id);

	if(!$blackList) return;
	$clientDetails = fetchAssociations(
		"SELECT clientid, active, CONCAT_WS(' ', fname, lname) as clientname, CONCAT_WS(', ', lname, fname) as sortname
			FROM tblclient WHERE clientid IN ($blackList)
			ORDER BY sortname");
	$cols = array_chunk($clientDetails, max(count($clientDetails) / 4 + (count($clientDetails) % 4 ? 1 : 0), 1));
	$numcols = max(count($cols), 1);
	ob_start();
	ob_implicit_flush(0);
	echo "<table><tr>";
	foreach($cols as $col) {
		echo "<td style='padding-left:30px;vertical-align:top;'>";
		foreach($col as $client) {
			$clientname = $client['clientname'];
			if(!$client['active']) $clientname = "<span style='color:#993333;font-style:italic;'>$clientname</span>";
			$checked = 1;
			labeledCheckbox("$clientname", "dns_{$client['clientid']}", $checked, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true) ;
			if(mattOnlyTEST()) {
				$reason = $blackListReasons[$client['clientid']];
				$reason = $reason != 1 ? "Reason: $reason" : "Click to supply a reason";
				echo " <span style='cursor: pointer;' onclick='editReason({$client['clientid']});' 
					title='$reason'>&#9998;</span>";
			}
			echo "<br>";
		}
		echo "</td>";
	}
	echo "</tr></table>";
	$table = ob_get_contents();
	ob_end_clean();
	return $table;
}

function dumpBlackListJavascript() {
	global $id;
	$blackList = join(',', doNotServeClientIds($id));
	if(mattOnlyTEST()) {
		$blackListReasons = doNotServeClientReasons($id);
		foreach($blackListReasons as $i => $reason)
			$blackListReasons[$i] = "$i: \"".($blackListReasons[$i] == 1 ? "" : $blackListReasons[$i])."\"";
		$blackListReasons = "var blacklistreasons = {".join(', ', $blackListReasons)."};";
		$pleaseExplainWhy = "&note=Please+explain+why";
	}
	echo <<<JAVASCRIPT
// BLACKLIST / DO NOT SERVE
var blacklist = [$blackList];
function chooseClientToBlackList() {
	url = "client-chooser-lightbox.php?title=Add+Client+to+Do+Not+Serve+List&prompt=Do+not+serve&update=donotserve$pleaseExplainWhy";
	$.fn.colorbox({href:url, width:"450", height:"470", iframe: true, scrolling: true, opacity: "0.3"});
}

$blackListReasons

function updateBlackist(value) { // e.g., "992|Joe Smith"
	value = value.split('|');
	clientid = value[0];
	clientname = value[1];
	if(!addClientToBlacklist(clientid)) alert(clientname+" is already listed.");
	else {
		var blacklistadditions = document.getElementById('blacklistadditions');
		if(blacklistadditions.innerHTML == '') blacklistadditions.innerHTML = "Click <b>Save Changes</b> to add the following:<br>";
		if(typeof blacklistreasons != 'undefined') {//  && value.length == 3
			blacklistreasons[clientid] = value[2];
			//alert(clientid+" -- "+JSON.stringify(blacklistreasons));
			clientname = clientname + " <span style='cursor: pointer;' onclick='editReason("+clientid+");' title='Edit reason'>&#9998;</span>";
		}
		blacklistadditions.innerHTML = blacklistadditions.innerHTML+
			"<input type=checkbox id='dns_"+clientid+"' name='dns_"+clientid+"' CHECKED> "+clientname+"<br>";
		if(document.getElementById('anyclients')) document.getElementById('anyclients').style.display='none';
	}
}
function addClientToBlacklist(clientid) {
	for(var i=0; i<blacklist.length; i++)
		if(blacklist[i] == clientid) return false;
	blacklist[blacklist.length] = clientid;
	return true;
}

function editReason(clientid) {
	var reason = blacklistreasons[clientid];
	$.fn.colorbox({html:
		"Please explain why:<p><input id='blacklistreason' style='width:300px' value='"+reason+"'><br>"+
		"<input type='button' value='Save' onclick='blacklistreasons["+clientid+"] = $(\"#blacklistreason\").val();$.fn.colorbox.close();'>",
		width:'350', height:'170', iframe: false, scrolling: true, opacity: '0.3'});
}
// END BLACKLIST / DO NOT SERVE FNS #########################

JAVASCRIPT;
}
// END BLACKLIST / DO NOT SERVE FNS #########################



function dumpUnassignedBasicTab($provider) {
	echo "<table>\n";
	inputRow('Email', 'unassignedemail', $_SESSION['preferences']['unassignedemail'], $labelClass=null, $inputClass='emailInput');
	checkboxRow('Send Daily Schedules', 'unassigneddailyvisitsemail', $_SESSION['preferences']['unassigneddailyvisitsemail']);
	checkboxRow('Send Weekly Schedules', 'unassignedweeklyvisitsemail', $_SESSION['preferences']['unassignedweeklyvisitsemail']);
	echo "<tr><td>";
	echoButton('', 'Set Unassigned Email Address', 'setUnassignedEmailAddress()');
	echo "</td></tr>";
	echo "</table>\n"; // Basic tab
}

function dumpProviderBasicTab($provider) {
	global $rawBasicNameAndAddressFields, $rawBasicOtherFields, $requiredFields, $redStar, $id, $suppressFields;
	// two column table, two row table
	// first row: col1: names and address col2: phones, email, taxid, marital status
	// second row: double wide TD: Notes and Emergency Contact
	echo "<table width=100%>\n";
	echo "<tr><td valign=top><table width=100%>\n"; // R1C1
	$raw = explode(',', $rawBasicNameAndAddressFields);
	for($i=0;$i < count($raw) - 1; $i+=2) $fields[$raw[$i]] = $raw[$i+1];
	foreach($fields as $key => $label) {
		if(in_array($key, $requiredFields)) $label = "$redStar $label";
		$val = isset($provider[$key]) ? $provider[$key] : '';
		//inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null)
		if(in_array($key, $suppressFields)) echo "<tr><td>&nbsp;</td></tr>";
		else if($key == 'zip' && function_exists('dumpZipLookupJS')) 
				inputRow($label, 'zip', $val, $labelClass=null, $inputClass='standardInput', null,  null, $onBlur='lookUpZip(this.value, "unused")');
		else if(in_array($key, array('street1','street2','city'))) inputRow($label.':', $key, $val, null, 'streetInput');
		else inputRow($label.':', $key, $val, $labelClass=null, $inputClass='standardInput');
	}
	echo "<tr><td colspan=2 class='tiplooks'>TIP: Enter the ZIP code first and you won't have to type in City or State.</td></tr>\n";

	echo "</table></td>\n"; // end R1C1
	
	echo "<td valign=top><table width=90%>\n"; // R1C2
	echo "<tr><td>&nbsp;</td><td style=padding:0px;padding-left:7px;'><img src='art/lookdown.gif' height=10 width=20> Select the Primary Phone Number</td></tr>\n";
	$raw = explode(',', $rawBasicOtherFields);
  $fields = array();	               
	for($i=0;$i < count($raw) - 1; $i+=2) $fields[$raw[$i]] = $raw[$i+1];
	foreach($fields as $key => $label) {
		$val = isset($provider[$key]) ? htmlentities($provider[$key]) : '';
		if(in_array($key, $suppressFields)) echo "<tr><td>&nbsp;</td></tr>";
		else if(in_array($key, array('cellphone','homephone','workphone')))
		  phoneRow($label.':', $key, $val, $labelClass=null, $inputClass='standardInput');
		else if($key == 'maritalstatus')
		  selectRow($label.':', $key, $val, array(''=>'','Single'=>'single','Married'=>'married','Separated'=>'separated','Divorced'=>'divorced'), $inputClass='standardInput');
		else if($key == 'email') {
			$checkEmail = fauxLink('Check', "checkEmail(\"$key\")", 1, 'Test this email address');
			inputRow($label.' './*$checkEmail.*/':', $key, $val, $labelClass=null, $inputClass='emailInput');
		}
		else inputRow($label.':', $key, $val, $labelClass=null, $inputClass='standardInput');
	}
	echo "</table></td></tr>\n"; // end R1C2
	echo "<tr>\n"; // R2
	
	
	
	$systemUser = $provider['userid'] ? findSystemLogin($provider['userid']) : null;
	if(!is_array($systemUser)) $systemUser = null;
	$args = array('roleid'=>$provider['providerid'], 'target'=>'systemLoginButton', 'lname'=>$provider['lname'], 'fname'=>$provider['fname'], 'nickname'=>$provider['nickname'], 'email'=>$provider['email']);
	if($systemUser) $args['userid'] = $systemUser['userid'];
	$args['role'] = 'provider';
	foreach($args as $k => $v)
	  $argstring[] = "$k=".urlencode($v);
	$argstring = join('&', $argstring);
	
	//$systemLoginEditButton = $systemUser ? $systemUser['loginid'] : 'Set Sitter Login';
	$systemLoginEditButton = $systemUser 
														? $systemUser['loginid'].($systemUser['active'] ? '' : " (inactive login)") 
														: 'Set Sitter Login';
	
	$systemLoginEditButton = echoButton('systemLoginButton', $systemLoginEditButton, "editLoginInfo(\"$id\", \"$argstring\")", null, null, 1);
	if($_SESSION['preferences']['mobileSitterAppEnabled'] && $provider['userid'] // MSA enabled and user id is set
			&& !$_SESSION['preferences']['mobileVersionPreferred']) { // ... and not all sitters are allowed access to MSA 
		$systemLoginEditButton .= ' '.
			labeledCheckBox('Mobile App Enabled', 'mobileappenabled', getUserPreference($provider['userid'], 'mobileVersionPreferred'),
											null, null, null, 'boxFirst', 'noEcho', 'Allow sitter to use LeashTime Mobile Sitter App');
	}
	if($systemUser['loginid'] && staffOnlyTEST()) {
		$systemLoginEditButton .= " ".fauxLink('<img src="art/greencheck.gif">', 'validateLogin()', 1, 'Make sure this sitter can log in.');
		$shortName = $provider['nickname'] ? $provider['nickname'] : "{$provider['fname']} {$provider['lname']}";
		$systemLoginEditButton .= " ".fauxLink('<img src="art/impersonate.gif">', "impersonate(\"{$provider['providerid']}\", \"$shortName\")", 1, 'Log in as this sitter.');
		$userIdDisplay = " ({$provider['userid']})";
	}
	if($_SESSION['preferences']['postcardsEnabled'] == 'selected' && $provider['userid']) // Postcards enabled and user id is set) 
		{ // ... and not all sitters are allowed access to MSA 
		$postcardsEnabledForSitter = getUserPreference($provider['userid'], 'postcardsEnabled', $decrypted=false, $skipDefault=true);
		$systemLoginEditButton .= ' '.
			labeledCheckBox('Web App Visit Reports Allowed', 'postcardsEnabled', $postcardsEnabledForSitter,
											null, null, null, 'boxFirst', 'noEcho', 'Allow sitter to send clients visit reports.');
	}
	echo "<tr><td><table>";
	labelRow("System Login$userIdDisplay:", '', $systemLoginEditButton, null, null, null, null, 'raw');
	echo "</table></td><td>&nbsp;</td><tr>";
	
	
	global $notesInEmploymentTab;
	
	$notes = isset($provider['notes']) ? htmlentities($provider['notes']) : '';
	if(!$notesInEmploymentTab) echo "<td valign=top>Notes:<br><textarea name='notes' cols=40 rows=3>$notes</textarea><p>";
	$emergency = isset($provider['emergencycontact']) ? htmlentities($provider['emergencycontact']) : '';
	echo "<td valign=top>Emergency Contact:<br><textarea name='emergencycontact' cols=40 rows=3>$emergency</textarea></td>";
	echo "</tr>\n"; // end R2
	echo "<tr><td style='color:red;'>* required field.</td></tr>\n"; // R2
	echo "</table>\n"; // Basic tab
}

$serviceTypeConstraints = '';
$serviceTypeparts = explode('|||',$rawServiceTypeFields);
/*
$serviceTypeparts = array(
    [0] => servicerate_96,Cat Only Pet Visit
    [1] => servicerate_91,Distance fee...
    )
*/
if(TRUE || mattOnlyTEST()) {
$allRawNames = "$rawBasicNameAndAddressFields,$rawEmploymentFields,$rawBasicOtherFields";
$prettyNames = "'".join("','", array_map('addslashes', explode(',',$allRawNames)))."'";
//echo "<p>[$prettyNames]<p>";
//print_r($serviceTypeparts);
	foreach($serviceTypeparts as $idCommaLabel) {;
		$sTypeId = substr($idCommaLabel, 0, strpos($idCommaLabel, ','));
		$sTypeLabel = addslashes(substr($idCommaLabel, strpos($idCommaLabel, ',')+1));
		$prettyNames .= ",'$sTypeId','$sTypeLabel'";
//echo "<br>[,$sTypeId,$sTypeLabel]";
		$serviceTypeConstraints .= ", '$sTypeId','','PERCENTORNUMBER'\n";
	}
} else {
$allRawNames = "$rawBasicNameAndAddressFields|||$rawEmploymentFields|||$rawBasicOtherFields";
if($rawServiceTypeFields) $allRawNames .= "|||$rawServiceTypeFields";
$prettyNames = "'".join("','", array_map('addslashes', explode(',',$allRawNames)))."'";
for($i = 0; $i < count($serviceTypeparts); $i+=2) {
	$part = addslashes($serviceTypeparts[$i]);
  $serviceTypeConstraints .= ", '$part','','PERCENTORNUMBER'\n";
}
}

require_once "time-framer-mouse.php"; // SEARCH FOR timeframer
makeTimeFramer('timeframer', $narrow=true, $noNameLinks=true, $clearButton=true);  // used by new provider-time-off.php

?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
if(document.showPayments) showPayments();
<?
if($continueEditing == 'applyNewRates') {
?>
var url = "provider-apply-rates.php?id="+<?= $providerid ?>;
openConsoleWindow('applyproviderrates', url,600,800);
<?
}
$providerNicknames = $providerNicknames ? $providerNicknames : array();
$nameArray = array();
foreach($providerNicknames as $nickname => $details) {
	$safeName = htmlentities($details['name']);//safeValue($details['name']);
  $nameArray[] = '"'.addslashes(strtoupper($nickname))."\",\"{$details['providerid']}-$safeName\"";
}
echo 'var nicknames = ['.join(',',$nameArray)."];\n;
";
?>

var userid = '';

function update(target, value) {
	if(target == 'systemLoginButton') {
		value = value.split(',');
		document.getElementById('systemLoginButton').value=value[1];
		userid = value[0];
	}
	else if(target == 'history') {
		showPayments();
	}
	else if(target == 'donotserve') {
		updateBlackist(value); // e.g., "992|Joe Smith"
	}
}

function setUnassignedEmailAddress() {
	var addr = document.getElementById("unassignedemail").value;
	var daily = document.getElementById("unassigneddailyvisitsemail").checked ? 1 : 0;
	var weekly = document.getElementById("unassignedweeklyvisitsemail").checked ? 1 : 0;

	if(MM_validateForm('unassignedemail', '', 'isEmail'))
		ajaxGetAndCallWith("ajax-set-unassigned-email.php?email="+addr+"&daily="+daily+"&weekly="+weekly, 
												function(target, ok){alert(ok='ok' ? 'Email address set' : 'Failed to set email address.');}, 0)
}

function editLoginInfo(providerid, argstring) {
	if(!providerid) {
		if(!confirm("This sitter has not been saved, but must be saved\nbefore a system login can be set up.\n"+
	                      "Click OK to save the sitter and continue."))
	     return;
	  else {
			checkAndSubmit('systemloginsetup');
		}
	}
	else {
		if(userid != '' && (argstring.indexOf('userid') == -1)) argstring = argstring+"&userid="+userid;
		var url = "login-creds-edit.php?"+argstring;
		openConsoleWindow('systemlogineditor', url,400,400);
	}
}

function searchForMessages() {
	searchForMessagesWithSort('');
}

function sortMessages(field, dir) {
	searchForMessagesWithSort(field+'_'+dir);
}

function checkEmail(addressField) {
	var addr;
	if(!(addr = jstrim(document.getElementById(addressField).value))) alert('Please supply an email address first.');
	else if(!validEmail(addr))  alert('The format of this email address is not valid.');
	else ajaxGetAndCallWith("ajax-email-check.php?email="+addr, postEmailCheck, addressField);	
}

function postEmailCheck(addressField, response) {
	alert(response);
}



//setPrettynames('msgsstarting,Starting date for messages,msgsending,Starting date for messages');
function searchForMessagesWithSort(sort) {
	
  if(MM_validateForm(
		  'msgsstarting', '', 'isDate',
		  'msgsending', '', 'isDate')) {
		var provider = document.getElementById('providerid').value;
		var starting = document.getElementById('msgsstarting').value;
		var ending = document.getElementById('msgsending').value;
		if(starting) starting = '&starting='+starting;
		if(ending) ending = '&ending='+ending;
		if(sort) sort = '&sort='+sort;
		var url = 'provider-comms-list.php';
    ajaxGet(url+'?id='+provider+starting+ending+sort, 'providermsgs')
	}
}

function openComposer() {
	openConsoleWindow('emailcomposer', 'comm-composer.php?provider=<?= $id ?>',500,500);
}

function openLogger(emailOrPhone) {
	openConsoleWindow('messageLogger', 'comm-logger.php?provider=<?= $id ?>&log='+emailOrPhone,500,500);
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}

function validateLogin() {
	$.fn.colorbox({href:"validate-system-login.php?role=provider&roleid=<?= $id ?>", 
									width:"500", height:"250", iframe: true, scrolling: true, opacity: "0.3"});
}

function impersonate(prov, provname) {
	if(confirm("Login as "+provname+"?"))
		document.location.href='impersonate.php?provider='+prov;
}


function getConflictingProviderNameForNickname(nickname, providerid) {
	//alert(nicknames[1].substring(0,nicknames[1].indexOf('-')));
	for(var i=0;i< nicknames.length-1;i+=2)
	  if(nicknames[i] == nickname.toUpperCase() &&
	     (providerid != nicknames[i+1].substring(0,nicknames[i+1].indexOf('-'))))
	    return nicknames[i+1].substring(nicknames[i+1].indexOf('-')+1);
	return null;
}

setPrettynames(<?= $prettyNames.", 'sittersPaidHourly', 'Sitters Paid Hourly', 'hourlyRate', 'Hourly Rate'" ?>);	
setPrettynames('sittersPaidHourly', 'Sitters Paid Hourly', 'hourlyRate', 'Hourly Rate');	
function checkAndSubmit(continueEditing) {
	if(!continueEditing || typeof continueEditing == 'undefined') 
		continueEditing = selectedTab ? selectedTab : 'basic';
	// gather TIME OFF errors...
	//alert(gatherTimeOffFields()); return;
	
	var editor = document.providereditor;
	var nicknameConflict = getConflictingProviderNameForNickname(editor.nickname.value, editor.providerid.value);
	nicknameConflict = !nicknameConflict ? '' : 'The '+prettyName('nickname')+' '+editor.nickname.value+
											' already refers to sitter '+nicknameConflict;
											
  var vArgs = [
		  nicknameConflict, '', 'MESSAGE',
		  'fname', '', 'R',
		  'lname', '', 'R',
		  'email', '', 'isEmail',
		  'hiredate','','isDate',
		  'terminationdate','','isDate'
		  <?= $serviceTypeConstraints ?>
		  <? 	if($_SESSION['preferences']['sittersPaidHourly']) 
					echo ", 'hourlyRate', '', 'UNSIGNEDFLOAT', 'travelAllowance', '', 'UNSIGNEDFLOAT'"
				?>];
	<? if(TRUE && function_exists('dumpSitterProfileValidationLines')) dumpSitterProfileValidationLines(); ?>

	for(var i=1; document.getElementById("timeoffrow_"+i); i++)
		if(document.getElementById('timeoffrow_'+i).style.display != 'none')
			if(jstrim(document.getElementById('firstdayoff_'+i).value) || // allow double blanks
			   jstrim(document.getElementById('lastdayoff_'+i).value)) {
			   vArgs = vArgs.concat(['firstdayoff_'+i, 'lastdayoff_'+i, 'inseparable',
																'firstdayoff_'+i, '', 'isDate',
																'lastdayoff_'+i, '', 'isDate',
																'firstdayoff_'+i, 'lastdayoff_'+i, 'datesInOrder']);
				 setPrettynames('firstdayoff_'+i, "Time Off Starting Date #"+i, 'lastdayoff_'+i, "Time Off Ending Date #"+i);
			}
  if(MM_validateFormArgs(vArgs)) {
		editor.continueEditing.value=continueEditing;
		if(document.getElementById('saveandadd')) 
			document.getElementById('saveandadd').disabled = true;
		document.getElementById('save').disabled = true;
		if(editor.timeoffdata) editor.timeoffdata.value=gatherTimeOffFields();
    editor.submit();
	}
}

function applyNewRates() {
	checkAndSubmit('applyNewRates');
}

function jstrim(str) {
	return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}


function addAnother(next) {
	document.getElementById("timeoffrow_"+next).style.display='<?= $_SESSION['tableRowDisplayMode'] ?>';
	document.getElementById("addanotherrow_"+next).style.display='<?= $_SESSION['tableRowDisplayMode'] ?>';
	document.getElementById("addanotherrow_"+(next-1)).style.display='none';
}

var deletions = [];
function deleteLine(number) {
	number = parseInt(number);
	// Clear line values
	if(document.getElementById("timeoffrow_"+number)) {
		document.getElementById('firstdayoff_'+number).value = '';
		document.getElementById('lastdayoff_'+number).value = '';
		if(document.getElementById('timeoff_'+number).value)
			deletions[deletions.length] = document.getElementById('timeoff_'+number).value;
		document.getElementById('timeoff_'+number).value = '';
  }
	// for number while servicecode_number(number + 1) copy line (number + 1) to number
	var lastvisibleline = number;
	for(var i=number; document.getElementById("timeoffrow_"+(i+1)); i++) {
		if(document.getElementById("timeoffrow_"+(i+1)).style.display != 'none')
		  lastvisibleline = i+1;
			document.getElementById("firstdayoff_"+i).value = document.getElementById("firstdayoff_"+(i+1)).value;
			document.getElementById("lastdayoff_"+i).value = document.getElementById("lastdayoff_"+(i+1)).value;
			document.getElementById('timeoff_'+i).value = document.getElementById("timeoff_"+(i+1)).value;
	}
	//alert(lastnumber);
	// if number is < last line then clear last line 
	if(number < lastvisibleline) {
		document.getElementById('firstdayoff_'+lastvisibleline).value = '';
		document.getElementById('lastdayoff_'+lastvisibleline).value = '';
		document.getElementById('timeoff_'+lastvisibleline).value = '';
	}		
	// set last line invisible
	if(lastvisibleline > 1) {
		document.getElementById("timeoffrow_"+lastvisibleline).style.display='none';
		document.getElementById('addanotherrow_'+(lastvisibleline)).style.display='none';
		lastvisibleline = lastvisibleline - 1;
		document.getElementById('addanotherrow_'+(lastvisibleline)).style.display='<?= $_SESSION['tableRowDisplayMode'] ?>';
	}
}

function gatherTimeOffFields() {
	var fields = 'deletions|';
	if(deletions.length > 0) fields += deletions.join('|');
	for(var i=1; document.getElementById("timeoffrow_"+i); i++)
		if(document.getElementById("timeoffrow_"+i).style.display != 'none')
			if(jstrim(document.getElementById("firstdayoff_"+i).value) && // skim out double blanks
			   jstrim(document.getElementById("lastdayoff_"+i).value)) {
				fields += ',' + document.getElementById("timeoff_"+i).value + '|' + 
								document.getElementById("firstdayoff_"+i).value + '|' +
								document.getElementById("lastdayoff_"+i).value;
				if(document.getElementById("div_timeofday_"+i))
					fields += '|'+document.getElementById("div_timeofday_"+i).innerHTML;
			}
	return fields;
	
}


function updateProviderTimeOff() {
	if(!document.getElementById('showpasttimeoff')) return;
	var showpasttimeoff = document.getElementById('showpasttimeoff').checked ? 1 : 0;
	ajaxGet('provider-time-off.php?id=<?= $id ?>&showpasttimeoff=1', 'timeoffdiv'); // +showpasttimeoff
}

function toggleOldTimeOff(el) {
	// problem: toggle() is async, so temporarily showing "Please wait..." does not work.  It's a real bitch.
	//$('#toggleTimePleaseWait').toggle();
<? if(0 & mattOnlyTEST()) { ?>
	var trs = document.getElementsByTagName('tr');
	for(var i=0; i < trs.length; i++) {
		if($(trs[i]).hasClass('oldTO'))
			$(trs[i]).toggle();
	}
	//$('.oldTO').toggle(null, null, function() {$('#toggleTimePleaseWait').toggle();});
<? } else { ?>	
	$('.oldTO').toggle();
<? } ?>	
	//$('#toggleTimePleaseWait').toggle();
}

function addAnotherZip(maxNewZips) {
	var lastVisibleZip=0;
	for(var i=1; i<=maxNewZips; i++) {
		if(document.getElementById('ziprow_'+i).style.display == 'none')
			break;
		else lastVisibleZip++;
	}
//alert(document.getElementById('newzip_'+(lastVisibleZip+1)));	
	if(lastVisibleZip == maxNewZips) {
		document.getElementById('nomore').style.display = '<?= $_SESSION['tableRowDisplayMode'] ?>';
		document.getElementById('addanother').style.display = 'none';
	}
	else document.getElementById('ziprow_'+(lastVisibleZip+1)).style.display = '<?= $_SESSION['tableRowDisplayMode'] ?>';
} 

function lookupZip(num) {
	var zip = document.getElementById('newzip_'+num).value;
	if(jstrim(zip).length < 5) {
		document.getElementById('newzip_'+num).innerHTML = '';
		return;
	}
//alert('<?= globalURL('zip-lookup-ajax.php') ?>?zip='+jstrim(zip));	
	//ajaxGet('<?= globalURL('zip-lookup-ajax.php') ?>?zip='+jstrim(zip), 'newzip_'+num);
	ajaxGetAndCallWith('<?= globalURL('zip-lookup-ajax.php') ?>?zip='+jstrim(zip), setZipCity, num);

} 

function setZipCity(num, citydata) {
	citydata = citydata.split('|');
	if(citydata.length == 2) citydata = citydata.join(', ');
//alert(citydata);	
	document.getElementById('city_'+num).innerHTML = citydata;
}


<?

dumpPopCalendarJS();
dumpClickTabJS();
dumpPhoneRowJS();
dumpTimeFramerJS('timeframer'); // SEARCH FOR timeframer

if(TRUE || $_SESSION['preferences']['donotserveenabled']) dumpBlackListJavascript();

if(function_exists('dumpZipLookupJS'))  {
	dumpZipLookupJS();
?>

function supplyLocationInfo(cityState,addressGroupId) {
	var cityState = cityState.split('|');
	if(cityState[0] && cityState[1]) {
		var city = document.getElementById('city');
		var state = document.getElementById('state');
		var needConfirmation = false;
		needConfirmation = needConfirmation || (city.value.length > 0 && (city.value.toUpperCase() != cityState[0].toUpperCase()));
		needConfirmation = needConfirmation || (state.value.length > 0 && (state.value.toUpperCase() != cityState[1].toUpperCase()));
		if(!needConfirmation || confirm("Overwrite city and state with "+cityState[0]+", "+cityState[1]+"?")) {
		  if(city.value.toUpperCase() != cityState[0].toUpperCase()) city.value = cityState[0];
		  if(state.value.toUpperCase() != cityState[1].toUpperCase()) state.value = cityState[1];
		}
	}
}

<? 
}
?>
var d = new Date();
d.setTime(d.getTime()-(30*24*3600*1000));
starting = d.getMonth()+1+'/'+d.getDate()+'/'+d.getFullYear();
updateProviderTimeOff();
if(<?= $id ? 'true' : 'false' ?>) ajaxGet('provider-comms-list.php?id=<?= $id ?>&starting='+starting, 'providermsgs');

</script>
<?
// ***************************************************************************
include "frame-end.html";
?>

