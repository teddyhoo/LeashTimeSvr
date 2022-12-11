<? //client-edit.php
$scriptstarttime = microtime(1);
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "zip-lookup.php";
require_once "client-fns.php";
require_once "provider-fns.php";
include_once "vet-fns.php";
require_once "key-fns.php";
require_once "pet-fns.php";
require_once "contact-fns.php";
require_once "service-fns.php";
require_once "preference-fns.php";
require_once "custom-field-fns.php";
require_once "system-login-fns.php";
require_once "invoice-fns.php";
require_once "time-framer-mouse.php";
require_once "agreement-fns.php";
include "cc-processing-fns-11-18-22.php";


ini_set('max_file_uploads',40); // for photos of pets.  In this PHP 5.3.3, empty upload slots count towrd the total

$locked = locked('o-');

$readOnly = userRole() == 'd' && !strpos($_SESSION['rights'], '#ec');
$readOnlyVisits = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');

extract($_REQUEST);  // if POSTed from here, id will be null, but clientid may be set

if($id && "".(int)"$id" != $id)  // against injection attacks 9/8/2020
	$id = null;

if($redirectToDate) { // to set date range without locking it into the URL
	$_SESSION['clientScheduleDateRange'] =
		date('Y-m-d', strtotime("- 4 days", strtotime($redirectToDate)))
		.'|'
		.date('Y-m-d', strtotime("+ 3 days", strtotime($redirectToDate)));
	$url = substr($_SERVER["REQUEST_URI"], 1);
	list($script, $args) = explode('?', $url);
	$args = explode('&', $args);
	foreach($args as $i => $pair)
		if(strpos($pair, 'redirectToDate=') === 0)
			unset($args[$i]);
	globalRedirect($script.'?'.join('&', $args));
	exit;
}



$savedClient = $id ? getClient($id) : array();
if(userRole() != 'd' || strpos($_SESSION['rights'], '#cl')) {
	$breadcrumbs = "<a href='client-list.php'>Clients</a>";
	if($savedClient && !$savedClient['active']) 
		$breadcrumbs .= " - <a href='client-list.php?inactive=1'>Inactive Clients</a>";
}
if($id && dbTEST('leashtimecustomers') && (staffOnlyTEST())) $breadcrumbs .= " - ".fauxLink('Client Biz Details', 
																			"descriptionBox()", 1, "Show client business details.");

if($id /*&& ($_SESSION['preferences']['offerNearbySittersMap'])*/) $breadcrumbs .= " - ".fauxLink('Show Nearby Sitters', 
																			"openConsoleWindow(\"nearbysitters\", \"client-provider-map.php?pop=1&id=$id\",800,800)", 1, "Open a map")." <span style='color:red;font-variant:small-caps;'>new</span>";

if($id && (staffOnlyTEST() || $_SESSION['preferences']['offerNearbyClientsMap'])) $breadcrumbs .= " - ".fauxLink('Show Nearby Clients', 
																			"openConsoleWindow(\"nearbyclients\", \"client-cluster-map.php?pop=1&id=$id\",800,800)", 1, "Open a map")." <span style='color:red;font-variant:small-caps;'>beta</span>";
																			
if($id && $_SESSION["flags_enabled"]) {
	require_once "client-flag-fns.php";
	$flagPanel = clientFlagPanel($id, $officeOnly=false, $noEdit=false, $contentsOnly=false, $onClick=null, ($_SESSION['preferences']['betaBillingEnabled'] || $_SESSION['preferences']['betaBilling2Enabled']));
}

$pageTitle = $savedClient ? "Client: {$savedClient['fname']} {$savedClient['lname']} $flagPanel" : "New Client";
	
if($savedClient && !$savedClient['active']) {
	if(TRUE || staffOnlyTEST()) $deactivationDate = // enabled for all 11/5/2018
		fetchRow0Col0(
			"SELECT time 
				FROM tblchangelog 
				WHERE itemptr = $id AND itemtable = 'tblclient' AND note = 'Deactivated' 
				ORDER BY time desc LIMIT 1");
	if($deactivationDate) $deactivationDate = '<span style="font-size:0.7em"> - '.shortNaturalDate(strtotime($deactivationDate))."</span>";
	$pageTitle .= " <font color=red>(Inactive$deactivationDate)</font>";
}
if($id && mattOnlyTEST()) $pageTitle = "<img src='art/snapshot.gif' onclick='openConsoleWindow(\"viewclient\", \"client-view.php?id=$id\", 500, 700)' title='View this client.'> ".$pageTitle;

// We may wish to redisplay the submitted (unsaved) provider fields
if($id) {
	$scheduleUpdatesAccepted = clientAcceptsEmail($id, array('autoEmailScheduleChanges'=>true));
	$client = $savedClient ? $savedClient : array();
}
else {
	$client = array();
	if(isset($requestid)) {
		require_once "request-fns.php";
		$request = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = $requestid");
		if($request && !$request['clientptr']) {
			$client['fname'] = $request['fname'];
			$client['lname'] = $request['lname'];
			$prospectPhoneTarget = getPreference('useCellphoneForProspectPhone') ? 'cellphone' : 'homephone';
			$client[$prospectPhoneTarget] = $request['phone'];
			$client['email'] = $request['email'];
			$client['email2'] = $request['email2'];
			$client['street1'] = $request['street1'];
			$client['street2'] = $request['street2'];
			$client['city'] = $request['city'];
			$client['state'] = $request['state'];
			$client['zip'] = $request['zip'];
			$client['referralcode'] = $request['referralcode'];
			$client['referralnote'] = $request['referralnote'];
			$client['prospect'] = 1;
			//$address = explode("\n", $request['address']);
			foreach(getStandardClientExtraFields($request) as $fld => $value) {
				$client[$fld] = $value;
			}
		}
	}
	else if($_SESSION['newclient']) { // mattOnlyTEST()
		//$_SESSION['newclient'] = array('zip'=>"60542");
		$clientDetails = $_SESSION['newclient'];
		unset($_SESSION['newclient']);
		$client['zip'] = $clientDetails['zip'];
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		$cityPipeState = "";
		$cityPipeState = explode('|', "".lookUpZip($client['zip'], $noEcho=true));
		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, $force=1);
		//echo "[[[".print_r($cityPipeState, 1)."]]]";exit;
		$client['city'] = $cityPipeState[0];
		$client['state'] = $cityPipeState[1];
		
	}
}

$showAccountTab = userRole() != 'd' || strpos($_SESSION['rights'], '#ac');
$showBillingTab = userRole() != 'd' || strpos($_SESSION['rights'], '#cb');

	
// =======================================================
if($_POST && isset($clientid)) {
	if($clientid) {
		$utime = microtime(1);	
		saveClient();
		setClientPreference($clientid, 'lastSaved', date('Y-m-d H:i:s')."|{$_SESSION['auth_username']}|{$_SESSION['auth_user_id']}");
		saveClientKey($clientid);
		saveClientPets($clientid);
		saveClientContacts($clientid);
		if($showBillingTab) {
			setClientCharges($clientid);
			setClientPreference($clientid, 'noCreditCardRequired', $_POST['noCreditCardRequired']);
		}
		saveClientCustomFields($clientid, $_POST);
		saveClientPreferences($clientid, $_POST);
		saveDiscount($clientid, $_POST);
		if($active != $savedClient['active']) setUserActive($savedClient['userid'], $active);
		$oldProvider = $savedClient['defaultproviderptr'] ? $savedClient['defaultproviderptr'] : 0;


	/*if($clientid) 
		saveClient();
		saveClientKey($clientid);
		saveClientPets($clientid);
		saveClientContacts($clientid);
		if($showBillingTab) setClientCharges($clientid);
		saveClientCustomFields($clientid, $_POST);
		saveClientPreferences($clientid, $_POST);
		saveDiscount($clientid, $_POST);
		if($active != $savedClient['active']) setUserActive($savedClient['userid'], $active);
		$oldProvider = $savedClient['defaultproviderptr'] ? $savedClient['defaultproviderptr'] : 0;*/
		if($savedClient['active'] && !$active)
		  deactivateClient($clientid);
		else {
			
//echo "[$oldProvider]   [$defaultproviderptr]";exit;
//echo "[".countCurrentIncompleteClientAppointmentsWithProvider($clientid, $oldProvider)."]
//	[".countCurrentClientPackagesWithProvider($clientid, $oldProvider)."]";exit;

			if($oldProvider != $defaultproviderptr) {
				if(countCurrentIncompleteClientAppointmentsWithProvider($clientid, $oldProvider) ||
					countCurrentClientPackagesWithProvider($clientid, $oldProvider))
					$gotoServiceReassignmentDialogPage = true;
			}
		}
		if(!$savedClient['active'] && $active) $cchanges[] = 'Reactivated.';
		logChange($clientid, 'tblclient', 'm', 'Modified.'.join('', (array)$cchanges));
		
		if($continueEditing) {
			if($continueEditing == 'keyedit') $rd = globalURL("key-edit.php?client=$clientid");
			else $tab = $continueEditing;
		}
		$_SESSION['frame_message'] = "Changes saved.";
	}
	else {
}		
		saveNewClient();
		$newClientId = mysqli_insert_id();
if(mattOnlyTEST()) setClientPreference($newClientId, 'lastSaved', date('Y-m-d H:i:s')."|{$_SESSION['auth_username']}|{$_SESSION['auth_user_id']}");
		logChange($newClientId, 'tblclient', 'c', 'Created');
		saveClientKey($newClientId);
		saveClientPets($newClientId);
		saveClientContacts($newClientId);
		if($showBillingTab) setClientCharges($newClientId);
		saveClientCustomFields($newClientId, $_POST);
		saveClientPreferences($newClientId, $_POST);
		saveDiscount($newClientId, $_POST);
		// if $requestid update the request to link it to this client
		if($requestid) updateTable('tblclientrequest', array('clientptr'=>$newClientId), "requestid = $requestid", 1);
		$message = "Client {$_POST['fname']} {$_POST['lname']} has been added.";

		if($continueEditing) {
			//if($continueEditing == 'another') $client = array();
			if($continueEditing == 'another') $message .= "  You can add another now.";
			else if($continueEditing == 'systemloginsetup') $tab = 'basic';
			else if($continueEditing == 'keyedit') $rd = globalURL("key-edit.php?client=$newClientId");
			else $tab = $continueEditing;
		}
		$_SESSION['frame_message'] = $message;
	}
	
	//setClientCharges($clientid);
	
//print_r($_POST);exit;
	if($rd) {
 
		header ("Location: $rd");
		exit();
	}
		
	else if($gotoServiceReassignmentDialogPage) {
		header ("Location: client-provider-reassignment.php?client=$clientid&oldprovider=$oldProvider");
		exit();
	}
		
/*  else if(!$continueEditing) {
		$openingConfirmation = 'Client has been saved.';
		//$param = $newClientId ? "newClient=$newClientId" : "savedClient=$clientid";
		//header ("Location: client-list.php?$param");
		//exit();
	}*/
  elseif($continueEditing == 'another') {
		$message = urlencode("$message");
		header ("Location: client-edit.php?clienteditalert=$message");
		exit();
	}
  else {
  
		$targetClient = $clientid ? $clientid : $newClientId;
		header ("Location: client-edit.php?id=$targetClient&tab=$tab");
		exit();
	}
}
$message = $message ? $message : '&nbsp;';

$initializationJavascript = '';


include "frame.html";
// ***************************************************************************
makeTimeFramer('timeFramer', 'narrow');
if($id) {
	if(!$client) {
		echo "Unknown Client.";
		include "frame-end.html";
		exit;
	}
	
if($_SESSION['preferences']['enableClientProfileLastAccessNotice']) {
	$lastOpenedRaw = getClientPreference($id, 'lastOpened');
	setClientPreference($id, 'lastOpened', date('Y-m-d H:i:s')."|{$_SESSION['auth_username']}|{$_SESSION['auth_user_id']}");
	if($lastOpenedRaw) {
		$lastOpenedRaw = explode('|', $lastOpenedRaw);
		$lastOpenedAt = ago($lastOpenedRaw[0]);
		$lastOpenedBy = $lastOpenedRaw[1];
	}
	$lastSavedRaw = getClientPreference($id, 'lastSaved');
	if($lastSavedRaw) {
		$lastSavedRaw = explode('|', $lastSavedRaw);
		$lastSavedAt = ago($lastSavedRaw[0]);
		$lastSavedBy = $lastSavedRaw[1];
		$spanTitle = "title='".safeValue("Last saved $lastSavedAt by $lastSavedBy ")."'";
	}
	$lastAccessLabel = $lastOpenedAt ? "Last opened $lastOpenedAt by $lastOpenedBy" : "Last saved $lastSavedAt by $lastSavedBy ";
	$emphasizeSavedDate = TRUE;
	if($emphasizeSavedDate) {
		$lastAccessLabel = "Last saved $lastSavedAt by $lastSavedBy ";
		$spanTitle = "title='".safeValue("Last opened $lastOpenedAt by $lastOpenedBy")."'";
	}
	$lastAccessInfo = "<span class='fontSize0_8em titlehint' $spanTitle>$lastAccessLabel</span>";
}

	
	$accountBalance = getAccountBalance($id, /*includeCredits=*/true, /*allBillables*/false);
	$accountBalance = $accountBalance == 0 ? 'PAID' : ($accountBalance < 0 ? dollarAmount(abs($accountBalance)).'cr' : dollarAmount($accountBalance));
	echo "<table style='width:100%'><tr><td style='width:100% vertical-align:top;font:bold 1.2em arial,sans-serif'>";
	
	if($_SESSION['ccenabled'] && $id) {
		//if($client['clientid']) $cc = getClearPrimaryPaySource($client['clientid']);

		if(!$cc) {
			$ccDisplay = 'None on file.';
		} else if($cc['x_exp_date']) {
			$autopay = $cc['autopay'] ? ' [auto]' : '[no auto]';
			$ccCompany = $cc['company'] == "Amex" ? "American Express" : $cc['company'];
			foreach(getAllCardTypes() as $type) {
				if($type['label'] == $ccCompany) {
					$image = $type['img'];
				}
			}
			if($image) $image = $image ? "<img src='art/$image'>" : $cc['company'];
			$exp = shortExpirationDate($cc['x_exp_date']);
			$realExpDate = strtotime(date('Y-m-t', strtotime($cc['x_exp_date'])));
			$today = strtotime(date('Y-m-d'));
			if($today >= $realExpDate) $exp = "<font color='red'>$exp</font>";
			else if($realExpDate - $today < 30 * 24 * 3600) $exp = "<font color='red'>$exp</font>";
			$ccDisplay = "$image *****{$cc['last4']} Exp: $exp $autopay";
		}
		else {
			$autopay = $cc['autopay'] ? ' [auto]' : '[no auto]';
			$ccDisplay = $cc['encrypted'] && !$cc['invalid'] ? "*****{$cc['last4']}" :  $cc['acctnum'];
			$ccDisplay = "ACH: $ccDisplay $autopay";
		}
		$ccDisplay = "<img src='art/spacer.gif' width=30 height=1>E-Payment: $ccDisplay";

	}
		
	if(staffOnlyTEST()) $billableButton = "<img src='art/help.jpg' height=20 width=20 onclick='openConsoleWindow(\"billables\", \"client-unpaid-billables.php?id=$id\", 800, 300);'>";
	echo "Account Balance: <span class='accountbalancedisplay' id='globalaccountbalance'>$accountBalance</span>  $billableButton  $ccDisplay";  // for jquery update
	echo "</td><td  style='text-align:right;vertical-align:top;'>";
	//$unresolved = staffOnlyTEST() && fetchRow0Col0("SELECT count(*) FROM tblclientrequest  WHERE clientptr = $id AND resolved = 0");
	$vanLooks = $unresolved ? array('HotButton', 'HotButtonDown') :  array('Button', 'ButtonDown');
	echoButton('', 'View All Notes', 'viewNotes()', $vanLooks[0], $vanLooks[1]); 
	if($lastAccessInfo) echo "<br>$lastAccessInfo";
	echo "</td></tr></table>";
}

if($warn) echo "<script language='javascript'>alert(\"$warn\");</script>";
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
	$saveButton = $id ? echoButton('', 'Save Changes', 'checkAndSubmit("")', 'Button', 'ButtonDown', 'noEcho')
										: 	echoButton('', 'Save New Client', 'checkAndSubmit("")', 'Button', 'ButtonDown', 'noEcho');
	$saveAndAddButton = !$id ? echoButton('', 'Save & Add Another', 'checkAndSubmit("another")', 'Button', 'ButtonDown', 'noEcho') : '';
}

function customSaveButton($tabName) {
	global $saveButton;
	return str_replace('checkAndSubmit("")', "checkAndSubmit(\"$tabName\")", $saveButton);
}

$quitButton = echoButton('', 'Quit', "document.location.href=\"client-list.php?inactive=$inactive\"", null, null, 'noEcho');

/*if($id) echo $saveButton;
else echo "$saveButton  $saveAndAddButton";
echo " $quitButton"; */

?>
</td></tr></table>
<?

$rawBasicCol1Fields = 'fname,First Name,lname,Last Name,fname2,Alt First Name,lname2,Alt Last Name,email2,Alt Email,'.
                       'email,Email,active,Active,prospect,Prospect';


//street1,Address,street2,Address 2,city,City,state,State,zip,ZIP';  
$rawBasicOtherFields = 'cellphone,Cell Phone,homephone,Home Phone,workphone,Work Phone,cellphone2,Alt Phone,'.
	               'fax,FAX,pager,Pager,defaultproviderptr,Default Sitter';  
$rawServiceTypeFields = '';	               
	               
	               
	               
$requiredFields = array('fname','lname');
$redStar = '<font color=red>*</font>';

$customFields = getCustomFields('activeOnly');

$labelAndIds = array("basic"=>'Basic Info', "address"=>'Address', "pets"=>'Pets',
	 "home"=>'Home Info', "emergency"=>'Emergency'); 
if($customFields) $labelAndIds["custom"] = 'Custom';
	 
$labelAndIds = array_merge($labelAndIds, array("services"=>'Services', "billing"=>"Billing", "account"=>"Account", "communication"=>'Communication'));
if(!$showAccountTab) unset($labelAndIds['account']);
if(!$showBillingTab) unset($labelAndIds['billing']);
$initialSelection = $tab ? $tab : 'basic';
$boxHeight = 300;
echo "<form name='clienteditor' method='post' enctype='multipart/form-data'>\n";
hiddenElement('MAX_FILE_SIZE', $maxBytes); // see pet-fns.php
hiddenElement('clientid', ($id ? $id : ''));
hiddenElement('continueEditing', '');
hiddenElement('rd', ''); // redirect to... after submit

if($requestid) hiddenElement('requestid', $requestid);
$tabWidths = array('communication'=>87, '##default##'=>65);
startTabBox('clienttabbox', $labelAndIds, $initialSelection, $tabWidths);

//function endTabPage($id, &$labelAndIds, $saveButton=null, $saveAndAddButton=null, $quitButton=null, $showNavButtons=true) {
//echoButton($id, $label, $onClick='', $class='', $downClass='', $noEcho=false);


startFixedHeightTabPage('basic', $initialSelection, $labelAndIds, $boxHeight);
dumpClientBasicTab($client);
endTabPage('basic', $labelAndIds, customSaveButton('basic'), $saveAndAddButton, $quitButton, true);
startFixedHeightTabPage('address', $initialSelection, $labelAndIds, $boxHeight);
dumpAddressTab($client);
endTabPage('address', $labelAndIds, customSaveButton('address'), $saveAndAddButton, $quitButton, true);
startFixedHeightTabPage('pets', $initialSelection, $labelAndIds, $boxHeight);
if($requestid) $tablePets = array_values(getProspectPets($request));
else $tablePets = getClientPets($id);



petTable($tablePets, $client);
endTabPage('pets', $labelAndIds, customSaveButton('pets'), $saveAndAddButton, $quitButton, true);
startFixedHeightTabPage('home', $initialSelection, $labelAndIds, $boxHeight);
dumpHomeInfoTab($client);
endTabPage('home', $labelAndIds, customSaveButton('home'), $saveAndAddButton, $quitButton, true);
startFixedHeightTabPage('emergency', $initialSelection, $labelAndIds, $boxHeight);
dumpEmergencyTab($client);
endTabPage('emergency', $labelAndIds, customSaveButton('emergency'), $saveAndAddButton, $quitButton, true);
startFixedHeightTabPage('custom', $initialSelection, $labelAndIds, $boxHeight);
dumpCustomTab($client, $customFields);
endTabPage('custom', $labelAndIds, customSaveButton('custom'), $saveAndAddButton, $quitButton, true);
if($showBillingTab) {
	startFixedHeightTabPage('billing', $initialSelection, $labelAndIds, $boxHeight);
	echo "<center>";
	billingTableAndPricesTable($client, $cc);
	echo "</center>";
	endTabPage('billing', $labelAndIds, customSaveButton('billing'), $saveAndAddButton, $quitButton, true);
}
echo "</form>";
if($showAccountTab) {
	startFixedHeightTabPage('account', $initialSelection, $labelAndIds, $boxHeight);
	if($client['clientid']) {
		historySection();
		if($_SESSION['preferences']['enableValuePacks']) {
			require_once "value-pack-fns.php";
			valuepacksSection();
		}
	}
	else echo "<p><center>This section is used after the new client has been saved.</center><p>";
	endTabPage('account', $labelAndIds, customSaveButton('account'), $saveAndAddButton, $quitButton, true);
}
startFixedHeightTabPage('services', $initialSelection, $labelAndIds, $boxHeight);
//echoTabNavButtons('services', $labelAndIds, customSaveButton('services'), $saveAndAddButton, $quitButton);
echo "<p style='text-align:center;'>";
if($client['clientid']) {
	if(!$readOnlyVisits) {
		echoButton('', 'New EZ Schedule', "saveAndRedirect(\"service-irregular.php?client={$client['clientid']}\")");
		if(!$_SESSION['preferences']['hideOneDayScheduleAtTop']) {
			echo " ";
			echoButton('', 'New One-Day Schedule', "saveAndRedirect(\"service-oneday.php?client={$client['clientid']}\")");
		}
		if(!$_SESSION['preferences']['hideProScheduleAtTop']) {
			echo " ";
			echoButton('', 'New Pro Schedule', "saveAndRedirect(\"service-nonrepeating.php?client={$client['clientid']}\")");
		}
	}
	else {
		echoButton('', 'Request New Schedule', "openConsoleWindow(\"newpackage\", \"client-sched-maker.php?id={$client['clientid']}\", 900, 700)");
	}
	
	//if($_SESSION["staffuser"] && !$_REQUEST['novd']) {
		echo " ";
		
			echo "<style>.notThere {font-style:italic}</style>";
			$rawOptions = array('Options'=>'',
							'View Discounted Visits'=>'viewDiscounts',
							'Visits Details'=>'visitDetails',
							'Print Visit Sheet'=>'printVisitSheet',
							'Set Services Tab Prefs'=>'setVisitListPrefs',
							'historicalData'=>'',
							'View Visits in List'=>'visitsList',
							'View Visits in Calendar'=>'visitsCalendar',
							'Add Note to Visits' => 'addNotes');
			$rawOptions['Arrange a Meeting'] = 'arrangeMeeting';						
			if($_SESSION['preferences']['enablePrefilledIntakeForm']) $rawOptions['Print a Pre-filled Intake Form'] = 'printIntakeForm';	//intake-form-launcher.php?clientid=$id
			if(staffOnlyTEST() || getUserPreference($_SESSION['auth_user_id'], 'clientChangeHistoryEnabled'))
				$rawOptions['Client Change History'] = 'clientChangeHistory';
			if(staffOnlyTEST())
				$rawOptions['STAFF Visit Change History'] = 'staffVisitChangeHistory';							
			if(staffOnlyTEST()) 
				$rawOptions['STAFF Sitters Who Have Served'] = 'staffProvidersWhoHaveServed';
			$rawOptions['Sitters Who Will Not Serve'] = 'providersWhoWillNotServe';
						
			
			
			if(staffOnlyTEST() || dbTEST('pppvb,doggiewalkerdotcom,savinggrace,tailsontrails,tonkatest,tonkapetsitters')) $rawOptions['Monthly Billables'] = 'staffMonthlyBillables';						
			foreach($rawOptions as $label => $value) {
				$style = '';
				if(($value == 'viewDiscounts') && !$_SESSION['discountsenabled']) continue;

				if($label == 'historicalData') {
					if($_SESSION['historicaldatapresent'] && userRole() != 'c' && userRole() != 'p') 
								$hdPresent = fetchRow0Col0("SELECT appointmentid FROM tblhistoricaldata WHERE clientptr = $id LIMIT 1");
					if($hdPresent) {
						$label = 'Historical Data';
						$value = 'historicalData';
					}
					else  {
						$label = 'No Historical Data Available';
						$style = "class='notThere'";
					}

				}
				$options .= "\n<option $style value='$value'>$label\n";
			}
			selectElement('', 'optionsSelect', '', $options, 'optionsAction(this)');
		
		//else echoButton('', 'Visits Detail', "openConsoleWindow(\"visits\", \"visits-detail-viewer.php?id={$client['clientid']}\", 900, 900)");
	//}
	//echo " ";
	//echoButton('', 'Ongoing Schedule', "hideShrinkDiv(\"appointments\")");
}
echo "</p>";
echo "<table width=100%>\n";
if(!$id) {
	echo "<p><h3>Please use the button below to save this client and start creating Service Schedules.</h3>";
	echoButton('', 'Save and Continue', 'checkAndSubmit("services")');
}
else {
	appointmentsSection();
  recurringServicesSection($client);
  nonrecurringServicesSection($client);
}
echo "</table>\n";
endTabPageSansNav();
startFixedHeightTabPage('communication', $initialSelection, $labelAndIds, $boxHeight);
echo "<div id='clientmsgs'></div>";
endTabPage('communication', $labelAndIds, $saveButton, $saveAndAddButton, $quitButton, true);

endTabBox();

// ============= Functions

function nonrecurringServicesSection($client) {
	global $readOnlyVisits;
	
	startAShrinkSection("Short Term Schedules", 'nonrecurringscheds', false);
	if(!$readOnlyVisits && $client['clientid']) {
		echoButton('', 'New EZ Schedule', "saveAndRedirect(\"service-irregular.php?client={$client['clientid']}\")");
		if(!$_SESSION['preferences']['hideOneDayScheduleAtBottom']) {
			echo " ";
			echoButton('', 'New One-Day Schedule', "saveAndRedirect(\"service-oneday.php?client={$client['clientid']}\")");
		}
		if(!$_SESSION['preferences']['hideProScheduleAtBottom']) {
			echo " ";
			echoButton('', 'New Pro Schedule', "saveAndRedirect(\"service-nonrepeating.php?client={$client['clientid']}\")");
		}
	}
	else {
		echoButton('', 'Request New Schedule', "openConsoleWindow(\"newpackage\", \"client-sched-maker.php?id={$client['clientid']}\", 900, 700)");
	}
	if(staffOnlyTEST() && !$client['active']) {
		echo " ";
		echoButton('', 'View Archived Schedules', "openConsoleWindow(\"archivedschedules\", \"client-final-schedules.php?id={$client['clientid']}\", 900, 700)");
	}
	//if(TRUE || staffOnlyTEST() || dbTEST('doggiewalkerdotcom')) {
		//require_once "temp-service-fns.php";
		dumpNonRecurringSchedules2($client['clientid']);
	//}
	//else dumpNonRecurringSchedules($client['clientid']);  // service-fns.php
	endAShrinkSection();
}
function saveDiscount($clientid, $arr) {
	if(!$_SESSION['discountsenabled']) return;
	
	require_once "discount-fns.php";
	$oldDiscount = getCurrentClientDiscount($clientid);
	if($oldDiscount && !$arr['discount']) dropClientDiscount($clientid);
	if($arr['discount']) {
		$discountId = explode('|', $arr['discount']); // format: discountptr|requires_memberid
		$discount = array('discountptr'=>$discountId[0], 'clientptr'=>$clientid, 'memberid'=>$arr['memberid']);
		if($oldDiscount && $discount['discountptr'] != $oldDiscount['discountptr'])  // discount type changed
			$discount['start'] = null;  // maybe we should look for previous applications of this discount
		if($oldDiscount) updateTable('relclientdiscount', $discount, "clientptr = $clientid", 1);
		else insertTable('relclientdiscount', $discount, 1);
	}			
}
function recurringServicesSection($client) {
	global $readOnlyVisits;
	
	$schedules = getCurrentClientPackages($client['clientid'], 'tblrecurringpackage');
	
	startAShrinkSection("Ongoing Schedule", 'recurringsched', false);
	echo "<a name='recurringtanchor'></a>";
	if(!$schedules) {
		if(!$readOnlyVisits) {
			echoButton('', 'New Ongoing Per-visit Schedule', "saveAndRedirect(\"service-repeating.php?client={$client['clientid']}\")");
			echo " ";
			if($_SESSION['preferences']['monthlyServicesPrepaid'])
				echoButton('', 'New Fixed Price Monthly Schedule', "saveAndRedirect(\"service-monthly.php?client={$client['clientid']}\")");
		}
	}
	else {
		echo "<div id='recurringscheduletableDIV'>";
		dumpRecurringSchedule($schedules[0], $clientid=null);
		echo "</div>";
	}
	endAShrinkSection();
}

function historySection() {
	global $initializationJavascript;
	$initializationJavascript .= "setPrettynames('invoiceStart','Starting Date','invoiceEnd', 'Ending Date');\n";
	$paymentEditor = FALSE && mattOnlyTEST() ? "payment-edit-2stage.php" : "payment-edit.php";
  echo <<<JSCODE
<div id='clientinvoices'></div>

<script language='javascript'>

function showNonServicesChunk(n) {
	$(".nrservicemorelink_"+n).css("display:none");
	$(".nrservicesline_"+n).css("display:{$_SESSION['tableRowDisplayMode']}");
}

function openShortTermScheduleHistory(id) {
	openConsoleWindow('shorttermers', "short-term-schedule-history.php?id="+id, 700, 400);
}

function searchForInvoices() {
	searchForInvoicesWithSort('date');
}

function searchForInvoicesWithSort(sort) {
  if(MM_validateForm(
		  'invoiceStart', '', 'isDate',
		  'invoiceEnd', '', 'isDate')) {
		var client = document.getElementById('client').value;
		var starting = document.getElementById('invoiceStart').value;
		var ending = document.getElementById('invoiceEnd').value;
		var hide = document.getElementById('hidevoids')
				? (document.getElementById('hidevoids').checked ? 1 : 0)
				: '';
		hide = '&hidevoids='+hide;
		if(starting) starting = '&starting='+starting;
		if(ending) ending = '&ending='+ending;
		if(sort) sort = '&sort='+sort;
		var url = 'client-invoices-ajax.php'; 
    //ajaxGet(url+'?client='+client+starting+ending+sort, 'clientinvoices')
    ajaxGetAndCallWith(url+'?client='+client+starting+ending+sort+hide, updateAccountTab, 1);
	}
}

function updateAccountTab(unused, html) {
	document.getElementById('clientinvoices').innerHTML = html;
	var bal = document.getElementById('invoicesaccountbalance').innerHTML;
	updateAccountBalance(bal);
}

function updateAccountBalance(balance) {
	$('.accountbalancedisplay').each(
		function(index, element) {
			element.innerHTML = balance;
			if(balance.indexOf('cr') != -1 && element.parent && element.parent.style.color == green)
				element.parent.style.color == red;
		});
}



function sortInvoices(field, dir) {
	searchForInvoicesWithSort(field+'_'+dir);
}

function viewInvoice(invoiceid, email) {
	openConsoleWindow('invoiceview', 'invoice-view.php?id='+invoiceid+'&email='+email, 800, 800);
}

function payInvoice(invoiceid, email) {
	var winDims = [600,480];
	openConsoleWindow('invoiceview', 'invoice-payment.php?invoiceid='+invoiceid, winDims[0], winDims[1]);
}

function editCredit(creditid, payment) {
	var url = payment ? '$paymentEditor' : 'credit-edit.php';
	var winDims = payment ? [600,500] : [600,380];
	openConsoleWindow('editcredit', url+'?id='+creditid, winDims[0], winDims[1]);
}

function addCredit(client, payment) {
	var url = payment ? '$paymentEditor' : 'credit-edit.php';
	var winDims = payment ? [600,480] : [600,220];
	openConsoleWindow('editcredit', url+'?client='+client, winDims[0], winDims[1]);
}

function addPayment2Stage(client, payment) {
	var url = payment ? 'payment-edit-2stage.php' : 'credit-edit.php';
	var winDims = payment ? [600,480] : [600,220];
	openConsoleWindow('editcredit', url+'?client='+client, winDims[0], winDims[1]);
}

function addGratuity(client) {
	var winDims = [600,480];
	openConsoleWindow('editgratuity', 'gratuity-edit.php?client='+client, winDims[0], winDims[1]);
}

function editGratuity(client, timeOrDateTime) {
	var winDims = [600,480];
	openConsoleWindow('editgratuity', 'gratuity-edit.php?client='+client+'&issuedate='+timeOrDateTime, winDims[0], winDims[1]);
}

function addRefund(client) {
	var winDims = [600,250];
	openConsoleWindow('editcredit', 'refund-edit.php?client='+client, winDims[0], winDims[1]);
}

function editRefund(refundid) {
	var winDims = [600,250];
	openConsoleWindow('editcredit', 'refund-edit.php?id='+refundid, winDims[0], winDims[1]);
}

</script>

JSCODE;

}

function interpretInterval($intervalLabel) {
	$firstDayThisMonthInt = strtotime(date("Y-m-01"));
	
	$weekStart = $_SESSION['preferences']['calendarWeekStart'] ? 'Sunday' : 'Monday';
	$weekFinish = $weekStart == 'Sunday' ? 'Saturday' : 'Sunday';
	
	
	if($intervalLabel == 'Last Month') {
		$start = shortDate(strtotime(date("Y-m-01", strtotime("-1 month", $firstDayThisMonthInt))));
		$end = shortDate(strtotime(date("Y-m-t", strtotime("-1 month", $firstDayThisMonthInt))));
	}
	else if($intervalLabel == 'Next Month') {
		$start = shortDate(strtotime(date("Y-m-01", strtotime("+1 month", $firstDayThisMonthInt))));
		$end = shortDate(strtotime(date("Y-m-t", strtotime("+1 month", $firstDayThisMonthInt))));
	}
	else if($intervalLabel == 'This Month') {
		$start = shortDate(strtotime(date("Y-m-01")));
		$end = shortDate(strtotime(date("Y-m-t")));
	}
	else if($intervalLabel == 'Last Week') {
		$end = shortDate(strtotime("last $weekFinish"));
		$start = shortDate(strtotime("last $weekStart", strtotime($end)));
	}
	else if($intervalLabel == 'Next Week') {
		$start = shortDate(strtotime("next $weekStart"));
		$end = shortDate(strtotime("next $weekFinish", strtotime($start)));
	}
	else if($intervalLabel == 'This Week') {
		if(date('l') == "$weekStart") $start = shortDate();
		else $start = shortDate(strtotime("last $weekStart"));
		$end = shortDate(strtotime("next $weekFinish", strtotime($start)));
	}
	return "$start|$end";
}


function valuepacksSection() {  // this section is populated by ...
	global $initializationJavascript, $id;
	// WORKS IN SERVICES, NOT ACCOUNT startAShrinkSection("Value Packs", 'valuepacks', false);
	echo "<table class='shrinkBanner' style='width:100%;'><tr><td style='border-width: 0px;'>Value Packs</td></tr></table>";
	//echoButton($id, $label, $onClick='', $class='', $downClass='', $noEcho=false, $title=null)
	echo <<<JSCODE
	<div id='valuepacks'></div>
	<script language='javascript'>
	//var debug = $debug;
	function updateValuePacks() {
		ajaxGet("value-pack-list.php?id=$id", 'valuepacks');
	}
	updateValuePacks();
</script>
JSCODE;
	//endAShrinkSection();

}

function appointmentsSection() {  // this section is populated by client-schedule-list.php
	global $initializationJavascript, $id;
	foreach(array('Last Month','Last Week','This Week','This Month','Next Week', 'Next Month') as $intervalLabel)
		$intervals[] = "'$intervalLabel':'".interpretInterval($intervalLabel)."'";
	$intervals = "  var intervals = {".join(',', $intervals)."};\n";
	$initializationJavascript .= "setPrettynames('starting','Starting Date','ending', 'Ending Date');\n";
	startAShrinkSection("Visits", 'appointments', false);
	//if(mattOnlyTEST() && $_REQUEST['highlightAppt']) 
	//	$highlightApptJS = "$('#apptrow{$_REQUEST['highlightAppt']} > td').css('background-color', 'orange');";

$debug = staffOnlyTEST() ? 1 : 0;	
//if($debug) $DDDBUG = "alert(value);";

  echo <<<JSCODE
<div id='clientappts'></div>
<script language='javascript'>
var userid = '';
//var debug = $debug;
function updateSchedules(unused, resultxml) {
//if(!debug) return;	
	var root = getDocumentFromXML(resultxml).documentElement;
	if(root.tagName == 'ERROR') {
		alert(root.nodeValue);
		return;
	}
	var subject, message;
	var nodes = root.getElementsByTagName('recurring') ;
	if(nodes.length == 1 && document.getElementById('recurringscheduletableDIV'))
		document.getElementById('recurringscheduletableDIV').innerHTML = nodes[0].firstChild.nodeValue;
	if(document.getElementById('nonrecurringschedulesdiv')) {
		nodes = root.getElementsByTagName('nonrecurring') ;
		if(nodes.length == 1) {
			document.getElementById('nonrecurringschedulesdiv').innerHTML = nodes[0].firstChild.nodeValue;
			$(".nrs").css("display", "none")			
			showNRServicesChunk(0);
		}
	}
}

function getDocumentFromXML(xml) {
	try //Internet Explorer
		{
		xmlDoc=new ActiveXObject("Microsoft.XMLDOM");
		xmlDoc.async="false";
		xmlDoc.loadXML(xml);
		return xmlDoc;
		}
	catch(e)
		{
		parser=new DOMParser();
		xmlDoc=parser.parseFromString(xml,"text/xml");
		return xmlDoc;
		}
}

function toggleHideVoid() {
	document.getElementById('searchForInvoicesButton').click();	
}

function updateForSavedClient(target, value) {	
	// called by update().  This fn is available when client has been saved
	
	if(target == 'officenotes') {
		ajaxGetAndCallWith('logbook-editor.php?summaryitemtable=client-office&summaryid=$id&summarycount=3&summarytitle='
									+escape('Click to View Office Notes Log')+'&summarytotal=yes', 
									function(arg, returnText) {
										returnText = returnText.split('##ENDCOUNT##');
										document.getElementById('officenotescount').innerHTML = returnText[0];
										document.getElementById('officenotessection').innerHTML = returnText[1];
									},
									1);
	}
	else if(target == 'systemLoginButton') {
		value = value.split(',');
		document.getElementById('systemLoginButton').value=value[1];
		userid = value[0];
	}
	else if(target == 'invoices') {
		document.getElementById('searchForInvoicesButton').click();	
	}
	else if(target == 'appointments' && document.getElementById('showAppointments')) {
		document.getElementById('showAppointments').click();	
		ajaxGetAndCallWith("ajax-get-client-schedules.php?id=$id", updateSchedules, null);
//$DDDBUG		
		if(value && (typeof value == 'string') && value.indexOf('MISASSIGNED') != -1) alert('Because of scheduled time off, this visit has been marked UNASSIGNED.');
		else if(value && (typeof value == 'string') && value.indexOf('EXCLUSIVECONFLICT') != -1) alert('Because of an already scheduled exclusive visit, this visit has been marked UNASSIGNED.');
		else if(value && (typeof value == 'string') && value.indexOf('INACTIVESITTER') != -1) alert('Because the sitter is now inactive, this visit has been marked UNASSIGNED.');
	}
	else if(target == 'account' && document.getElementById('account')) 
		document.getElementById('searchForInvoicesButton').click();	
	else if(target == 'messages' && document.getElementById('showMessages')) 
		document.getElementById('showMessages').click();	
	else if(target == 'creditcard') {
		if(document.getElementById('primarypaysource_CC')) {
				document.getElementById('primarypaysource_CC').parentNode.parentNode.style.display = 
					(value ? "{$_SESSION['tableRowDisplayMode']}" : 'none');
				document.getElementById('primarypaysource_CC').checked = (value ? true : false);
		}
		if(!value) {
			value = 'No Credit Card on file.';
		}
		else {
			value = value.split('|');
			value = value[0]+" ************"+value[1]+" Exp: "+value[2]+(value[3] != '0' ? ' [auto]' : '');
		}
		
		document.getElementById('ccinfo').innerHTML = value;
	}
	else if(target == 'ach') {
if(document.getElementById('primarypaysource_ACH')) {
		if(!value) {
			value = 'No ACH Info on file.';
			document.getElementById('primarypaysource_ACH').parentNode.parentNode.style.display = 'none';
			document.getElementById('primarypaysource_ACH').checked = false;
		}
		else {
			value = value.split('|');
			//value = value[0]+" ************"+value[1]+" Exp: "+value[2]+(value[3] ? ' [auto]' : '');
			//"{$ach['bank']} {$ach['acctnum']} {$ach['accttype']}".$autopay : 'No ACH Info on file.';
			value = value[0]+" "+value[1]+" "+value[2]+(value[3] != '0' ? ' [auto]' : '');
			document.getElementById('primarypaysource_ACH').parentNode.parentNode.style.display = '{$_SESSION['tableRowDisplayMode']}';
			document.getElementById('primarypaysource_ACH').checked = true;
		}
}
		document.getElementById('achinfo').innerHTML = value;
	}
	else if(target == 'flags') $('#flagpanel').html(value);
	else if(target == 'filecustomfield') {
		//alert(target+' => '+value);
		updateFileCustomField(value);
	}
}

function showInterval(interval) {
  $intervals;
  interval = intervals[interval];
	if(interval) {
		interval = interval.split('|');
		document.getElementById('starting').value = interval[0];
		document.getElementById('ending').value = interval[1];
		document.getElementById('showAppointments').click();
	}
}

function searchForAppointments(calendarview) {
	searchForAppointmentsWithSort('', calendarview);
}

function searchForAppointmentsWithSort(sort, calendarview) { // populates clientappts (created in appointmentsSection)
  if(MM_validateForm(
		  'starting', '', 'isDate',
		  'ending', '', 'isDate')) {
		var client = document.getElementById('client').value;
		var starting = document.getElementById('starting').value;
		var ending = document.getElementById('ending').value;
		if(starting) starting = '&starting='+safeDate(starting);
		if(ending) ending = '&ending='+safeDate(ending);
		if(sort) sort = '&sort='+sort;
		var url = calendarview ? 'client-schedule-cal.php' : 'client-schedule-list.php';
    //ajaxGet(url+'?client='+client+starting+ending+sort+"&targetdiv=clientappts", 'clientappts')
    ajaxGetAndCallWith(url+'?client='+client+starting+ending+sort+"&targetdiv=clientappts", updateClientAppts, 1)
	}
}

function updateClientAppts(unused, html) {
	document.getElementById('clientappts').innerHTML = html;
	updateAccountBalance(document.getElementById('clientscheduleaccountbalance').value);
	$highlightApptJS	
}

function searchForMessages() {
	searchForMessagesWithSort('');
}

function sortMessages(field, dir) {
	searchForMessagesWithSort(field+'_'+dir);
}


//setPrettynames('msgsstarting,Starting date for messages,msgsending,Starting date for messages');
function searchForMessagesWithSort(sort) {
	
  if(MM_validateForm(
		  'msgsstarting', '', 'isDate',
		  'msgsending', '', 'isDate')) {
		var client = document.getElementById('client').value;
		var starting = document.getElementById('msgsstarting').value;
		var ending = document.getElementById('msgsending').value;
		if(starting) starting = '&starting='+starting;
		if(ending) ending = '&ending='+ending;
		if(sort) sort = '&sort='+sort;
		var url = 'client-comms-list.php';
    ajaxGet(url+'?id='+client+starting+ending+sort, 'clientmsgs')
	}
}

function sortAppointments(field, dir) {
	var calendarview = document.getElementById('calendarview') ? true : false;
	searchForAppointmentsWithSort(field+'_'+dir, calendarview);
}

function viewNotes() {
	$.fn.colorbox({href:"service-notes-ajax.php?clientid=$id", width:"750", height:"570", scrolling: true, opacity: "0.3"});	
}

function openComposer() {
	openConsoleWindow('emailcomposer', 'comm-composer.php?client=$id',680,580);
}

function openLogger(emailOrPhone) {
	openConsoleWindow('messageLogger', 'comm-logger.php?client=$id&log='+emailOrPhone,500,500);
}

function printVisitSheets() {
  if(!MM_validateForm(
		  'starting', '', 'isDate')) return;
	var starting = document.getElementById('starting').value;
	var ending = document.getElementById('ending').value;
	var message;
	if(!starting) message = "No starting date has been supplied.\\nPrint today's Visit Sheets?";
	else if(ending != starting) message = "Print Visit Sheets for "+starting+"?";
	if(message && !confirm(message)) return;
	var url = 'visit-sheets-client.php?id=$id'+'&date='+starting;
	var w = window.open("",'visitsheets',
		'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+750+',height='+700);
	w.document.location.href=url;
	if(w) w.focus();
}

function reassignJobs() {
	//var provider = document.provschedform.client.value;
	//var starting = document.provschedform.starting.value;
	//document.location.href='job-reassignment.php?fromprov='+provider+'&date='+starting;
}

</script>
JSCODE;

	endAShrinkSection();
}

function billingTableAndPricesTable($client, $cc=null) {
	echo "<table style='width: 99%;' BORDER=0 BORDERCOLOR=BLACK><tr>\n";
	echo "<td valign='top'>";
	echo "<table BORDER=0 BORDERCOLOR=BLACK style='width: 100%;padding-right:10px'>";
	echo "<tr><td colspan=2 style='font-weight:bold;font-size:1.5em'>Billing Preferences</td></tr>\n";
	$invoiceBy = isset($client['invoiceby']) ? $client['invoiceby'] : null; 
	//radioButtonRow('Invoice by:', 'invoiceby', $invoiceBy, array('E-Mail'=>'email', 'Paper Mail'=>'mail'));
	
	if($_SESSION['ccenabled']) {
		echo "<tr><td colspan=2 style='font-weight:bold;font-size:1.5em'>CLIENT ID: " . $client['clientid'] . "</td></tr>\n";
		
		if($client['clientid']) $cc = getClearCC($client['clientid']);
		if ($cc == null) {
			echo "<tr><td colspan=2 style='font-weight:bold;font-size:1.5em'>NULL</td></tr>\n";
		} else {
			foreach($cc as $k => $v) {
				echo "<tr><td colspan=2 style='font-weight:bold;font-size:1.5em'>$k -> $v</td></tr>\n";
			}
		}
		
		//print_r(fetchCol0("SHOW TABLES"));
		if($client['clientid']) {
			if($_SESSION['preferences']['enableIndividualClientCreditCardsNotRequired']) {
				echo "<tr><td colspan=2>CC GATEWAY: " .$_SESSION['preferences']['ccGateway']	." PREF: " .$_SESSION['preferences']['enableIndividualClientCreditCardsNotRequired'] . "<hr></td></tr>";
				$noCreditCardRequired = getClientPreference($client['clientid'], 'noCreditCardRequired');
				checkboxRow('No Credit Card Required', 'noCreditCardRequired', $noCreditCardRequired, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null, $extraContent=null);
			}
			$isACHenabled = testEnable();
			//if(achEnabled()) {
			//	ePaymentTableRows($client);
			//}
			//if ($isACHenabled) {
				//ePaymentTableRows($client);
			//}
			//else {				
				echo "<tr><td style='font-weight:bold;font-size:1.25em;padding-top:10px;' colspan=3>Credit Card Info</td></tr>";
				/*$autopay = $cc['autopay'] ? ' [auto]' : '';
				$cc = $cc ? "{$cc['company']} ************{$cc['last4']} Exp: ".shortExpirationDate($cc['x_exp_date']).$autopay : 'No Credit Card on file.';
				echo "<tr><td id='ccinfo' style='' colspan=3>$cc</td></tr>";
				if(adequateRights('*cm')) {
					echo "<tr><td colspan=2>".echoButton('', 'Edit Credit Card Info', 
																			"openConsoleWindow(\"cceditor\", \"cc-edit.php?client={$client['clientid']}\",500,600)", 
																			null, null, 1);
					if(false && $cc) echo " ".echoButton('', 'Credit Card Refund', 
																			"openConsoleWindow(\"ccrefund\", \"cc-refund.php?client={$client['clientid']}\",420,410)", 
																			null, null, 1, "Issue a refund to this Credit Card.");
					echo "</tr><tr><td colspan=2>".fauxLink('View Electronic Transactions', 
																			"openConsoleWindow(\"cctransreport\", \"cc-transaction-history.php?client={$client['clientid']}\",800,410)", 1)."</tr>";
				}																	
			}*/
			
			/*
			if(staffOnlyTEST()) {
				if(!adequateRights('*cm')) 
					echo "</tr><tr><td colspan=2>".fauxLink('View Electronic Transactions', 
																			"openConsoleWindow(\"cctransreport\", \"cc-transaction-history.php?client={$client['clientid']}\",800,410)", 1)."</tr>";
				echo "<tr><td colspan=2>".fauxLink('[LT STAFF] Find Transaction by Transaction ID', 'findTransactionByID()', 1)."</tr>";
			}
			*/
		}
		$pref = $client['clientid'] ? getClientPreference($client['clientid'],'autoEmailCreditReceipts') : $_SESSION['preferences']['autoEmailCreditReceipts'];
		checkBoxRow('Auto-email receipts:', 'autoEmailCreditReceipts', $pref);
	} else {
		//echo "<tr><td colspan=2 style='font-weight:bold;font-size:1.5em'>DATA</td>NOT ENABLED</tr>\n";
	}
	echo "</table>";
	echo "</td><td valign='top'>";
	$id = isset($client['clientid']) ? $client['clientid'] : null; 
	pricesTable($id);
	echo "</td></tr></table>\n";
}

function ePaymentTableRows($client) {
	if($client['clientid']) {
		echo "<tr><td colspan=2><hr></td></tr>";
		echo "<tr><td style='font-weight:bold;font-size:1.25em;padding-top:10px;' colspan=3>Credit Card Info</td></tr>";
		$cc = getClearCC($client['clientid']);
		$autopay = $cc['autopay'] ? ' [auto]' : '';
		$ccDisp = $cc ? "{$cc['company']} ************{$cc['last4']} Exp: ".shortExpirationDate($cc['x_exp_date']).$autopay : 'No Credit Card on file.';
		$ccDisp .= unusableFlag($cc);
		echo "<tr><td id='ccinfo' style='' colspan=3>$ccDisp</td></tr>";
		$primarydisplay = $cc ? $_SESSION['tableRowDisplayMode'] : 'none';
		$selectedValue = $cc['primarypaysource'] ? 'CC' : null;
		echo "<tr style='display:$primarydisplay'><td>";
		labeledRadioButton('Primary:', 'primarypaysource', 'CC', $selectedValue, $onClick='primaryClicked(this)', null, null, 'labelfirst');		
		
		echo "</td></tr>";
		//checkboxRow('Primary', "activecc_CB", $cc['primarypaysource'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle="display:$primarydisplay", "setPrimaryPaySource(this)", $rowClass=null);
		echo "<tr><td colspan=2>".echoButton('', 'Edit Credit Card Info', 
																"openConsoleWindow(\"cceditor\", \"cc-edit.php?client={$client['clientid']}\",500,600)", 
																null, null, 1);
		/*if(false && $cc) echo " ".echoButton('', 'Credit Card Refund', 
																"openConsoleWindow(\"ccrefund\", \"cc-refund.php?client={$client['clientid']}\",420,410)", 
																null, null, 1, "Issue a refund to this Credit Card.");*/
		if(getPreference('gatewayOfferACH')) {
			echo "<tr><td colspan=2><hr></td></tr>";
			echo "<tr><td style='font-weight:bold;font-size:1.25em;padding-top:10px;' colspan=3>E-check (ACH) Info</td></tr>";
			$ach = getClearACH($client['clientid'], $primaryToo=false);

			$autopay = $ach['autopay'] ? ' [auto]' : '';
			$bankDisplay = $ach['bank'] ? $ach['bank'] : "Routing #{$ach['abacode']} /";
			//if(!mattOnlyTEST() && $ach  && $_SESSION['preferences']['ccGateway'] != $ach['gateway']) {
			if($ach['invalid']) {
				$achDisp = "<font color=red>This ACH info is not valid for your gateway {$_SESSION['preferences']['ccgateway']}</font>";
			}
			else {
				$achDisp = $ach ? "$bankDisplay {$ach['acctnum']} {$ach['accttype']}".$autopay : 'No ACH Info on file.';
				$achDisp .= unusableFlag($ach);

			}
			echo "<tr><td id='achinfo' style='' colspan=3>$achDisp</td></tr>";
			$primarydisplay = $ach ? $_SESSION['tableRowDisplayMode'] : 'none';
			$selectedValue = $ach['primarypaysource'] ? 'ACH' : null;
			echo "<tr style='display:$primarydisplay'><td>";
			labeledRadioButton('Primary:', 'primarypaysource', 'ACH', $selectedValue, $onClick='primaryClicked(this)', null, null, 'labelfirst');		
			echo "</td></tr>";
			//checkboxRow('Primary', "activeach_CB", $ach['primarypaysource'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle="display:$primarydisplay", "setPrimaryPaySource(this)", $rowClass=null);
			echo "<tr><td colspan=2>".echoButton('', 'Edit ACH Info', 
																	"openConsoleWindow(\"cceditor\", \"ach-edit.php?client={$client['clientid']}\",500,600)", 
																	null, null, 1);
			echo "<tr><td colspan=2><hr></td></tr>";
		}

		echo "</tr><tr><td colspan=2>".fauxLink('View Electronic Transactions', 
																"openConsoleWindow(\"cctransreport\", \"cc-transaction-history.php?client={$client['clientid']}\",800,410)", 1)."</tr>";
		
	}
}

function unusableFlag($paymentSource) {
	if(!$paymentSource) return;
	require_once "cc-processing-fns-11-18-22.php";
	//$nmiGateways = gatewayIsNMI($_SESSION['preferences']['ccGateway']) ? 1 : 0;
	//$nmiGateways += gatewayIsNMI($paymentSource['gateway']) ? 1 : 0;
	//if($nmiGateways == 1) // if one of the gateways is NMI and the other is not...
	if(gatewayConflict($paymentSource))
		return "<br><span style='color:red;font-variant:small-caps;'>Unusable: was set up for "
					.($paymentSource['gateway'] ? $paymentSource['gateway'] : 'Another Gateway')."</span>";
}

function pricesTable($id) {
	global $rawServiceTypeFields;
	$standardTaxRate = $_SESSION['preferences']['taxRate'] ? $_SESSION['preferences']['taxRate'] : '';
	$standardRates = getStandardRates();

	$charges = getClientCharges($id, false);
	echo "<table style='width: 100%;'><tr><td colspan=3 style='text-align:center;font-weight:bold;font-size:1.5em'>Custom Service Prices</td></tr>\n";
	echo "<tr><th>&nbsp;</th><th style='text-align:right;'>Standard Price</th><th>Price</th><th>Standard Tax</th><th>Tax Rate %</th></tr>\n";
	$nowInactive = false;
	foreach($standardRates as $key => $service) {
		if(!$nowInactive && ($nowInactive = !$service['active']))
			echo "<tr><td colspan=5 class='italicized' style='border-top: solid gray 1px;text-align:center;'>
				Inactive Services $toggleThis</td></tr>\n";
		$nowInactive = !$service['active'];
		$labelLook = $service['active'] ? '' : "class='italicized'";
		$rowClass = $service['active'] ? '' : "class='INACTIVE' style='display:none'";
		$toggleThis = fauxLink('Show / Hide', '$(".INACTIVE").toggle();', 1, 2);
		//$service['defaultrate'].($service['ispercentage'] ? '%' : '');
		$stndRate = dollarAmount($service['defaultcharge']);
		$charge = !isset($charges[$key]) || $charges[$key]['charge'] < 0 ? '' : $charges[$key]['charge'];
		echo "<tr $rowClass><td $labelLook>{$service['label']}</td><td style='text-align:right;'>$stndRate</td><td>";
		labeledInput('', 'servicecharge_'.$key, $charge, null, 'dollarinput');
		$thisStandardTaxRate = $service['taxable'] ?  "$standardTaxRate %" : '';
		echo "</td><td>$thisStandardTaxRate</td><td>";
		$taxRate = $charges[$key]['taxrate'] >= 0 ? $charges[$key]['taxrate'] : '';
		labeledInput('', 'servicetax_'.$key, $taxRate, null, 'dollarinput');
		$rawServiceTypeFields = ($rawServiceTypeFields ? "$rawServiceTypeFields|||" : '').'servicecharge_'.$key.','.$service['label'];
		echo "</td></tr>\n";
	}
	echo "</table>\n";
}

function dumpCustomTab($client, $customFields) {
	$clientValues = getClientCustomFields($client['clientid']);
	$clientValues['clientid'] = $client['clientid'];
	$customFields = displayOrderCustomFields($customFields, 'custom');
	customFieldsTable($clientValues, $customFields);
}


function dumpEmergencyTab($client) {
	global $id;
	$contacts = getKeyedClientContacts($id);
	
	// two column table
	echo "<table width=100%>\n";
	echo "<tr>\n<td>";
	$contact = isset($contacts['emergency']) ? $contacts['emergency'] : array();
	contactTable($contact, 'emergency');
  echo "</td><td>\n";
	$contact = isset($contacts['neighbor']) ? $contacts['neighbor'] : array();
	contactTable($contact, 'neighbor');
  echo "</td></tr>\n";
	echo "</table>\n";
}

function dumpClientBasicTab($client) {
	global $id, $rawBasicCol1Fields, $rawBasicOtherFields, $requiredFields, $redStar;
	// two column table, two row table
	// first row: col1: names and address col2: phones, email, taxid, marital status
	// second row: double wide TD: Notes and Emergency Contact
	echo "<table width=100%>\n";
	echo "<tr><td valign=top><table width=100%>\n"; // R1C1
	$raw = explode(',', $rawBasicCol1Fields);
	for($i=0;$i < count($raw) - 1; $i+=2) $fields[$raw[$i]] = $raw[$i+1];
	foreach($fields as $key => $label) {
		if(in_array($key, $requiredFields)) $label = "$redStar $label";
		$val = isset($client[$key]) ? $client[$key] : '';
		//inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null)
		$onBlur = !$client['clientid'] && $key == 'lname' ? 'checkForDups()' : '';
		if(in_array($key, array('active', 'prospect'))) {
			if(!$id && ($key == 'active')) $val = 1;
			checkboxRow($label.':', $key, $val, $labelClass=null, $inputClass='standardInput');
		}
		else if(strpos($key,'email') === 0) {
			$checkEmail = fauxLink('Check', "checkEmail(\"$key\")", 1, 'Test this email address');
			inputRow($label./*' '.$checkEmail.*/':', $key, $val, null, 'emailInput');
		}
		else if(!$client['clientid'] && $key == 'fname') {
			echo "<tr>
			<td><label for='fname'>$label:</label></td><td><input class='standardInput' id='fname' name='fname' 
						value='".safeValue($client['fname'])."' onBlur= autocomplete='off'> 
							<div id='dupnames' style='display:inline;color:darkgreen;text-decoration:underline;cursor:pointer;'
							onclick='alert(\"Similar names:\"+String.fromCharCode(13)+this.title)'></div></td></tr>\n";

		}
		else inputRow($label.':', $key, $val, $labelClass=null, $inputClass='standardInput', null, null, $onBlur);
	}
	echo "</table></td>\n"; // end R1C1
	
	echo "<td valign=top><table width=90%>\n"; // R1C2
	echo "<tr><td>&nbsp;</td><td style=padding:0px;padding-left:7px;'><img src='art/lookdown.gif' height=10 width=20> Select the Primary Phone Number</td></tr>\n";
	$raw = explode(',', $rawBasicOtherFields);
  $fields = array();	               
	for($i=0;$i < count($raw) - 1; $i+=2) $fields[$raw[$i]] = $raw[$i+1];
	foreach($fields as $key => $label) {
		//$val = isset($client[$key]) ? htmlentities($client[$key]) : '';
		$val = isset($client[$key]) ? $client[$key] : '';
		if(in_array($key, array('cellphone','cellphone2','homephone','workphone')))
		  phoneRow($label.':', $key, $val);
		else if($key == 'defaultproviderptr') {

if($id && (TRUE || staffOnlyTEST() || $_SESSION['preferences']['enableClientSitters'])) {
	//$clientSitters = fauxLink('&#9776;', 'switchToClientSitters()', 1, 'Edit sitter preferences for this user.').' ';
	$clientSitters = "<span style='cursor:pointer;font-weight:bold;' title='Edit sitter preferences for this client.' onclick='switchToClientSitters()'>&#9776;</span> ";
}
			$options = availableProviderSelectElementOptions($client, null,  '--Select a Sitter--');
			selectRow($clientSitters.$label.':', $key, $val, $options);
			if($val && !providerInArray($val, $options)) {
				$selectProv = getProvider($val);
				$pName =  providerShortName($selectProv);
				$pName =  $pName ? $pName : "[{$val}]";
				$reason = providerNotListedReason($selectProv, $client);
				echo "<tr><td style='color:red;'colspan=2>This client is assigned to $pName but should not be because $pName $reason.</td></tr>";
			}
/*
			$activeProviders = array_merge(array('--Select a Sitter--' => ''), getActiveProviderSelections(null, $client['zip']));
			selectRow($label.':', $key, $val, $activeProviders);
			if($val && !in_array($val, $activeProviders)) {
				$oldProvider = fullname(getProvider($val));
			  echo "<tr><td colspan=2 style='color:red;font-style:italic'>Sitter <b>$oldProvider</b> is not active.</td</tr>";
			}
*/			
		}
		else inputRow($label.':', $key, $val);
	}
	echo "</table></td></tr>\n"; // end R1C2
	
	echo "<tr><td>\n"; // R2
  selectElement('Veterinary Clinic:', 'clinicptr', $client['clinicptr'], array(), 'clinicChanged(this)');  // init'd by rebuildSelectOptions
  fauxLink(' view', "viewClinic()", 0, "View the selected clinic.");
	echo "\n<td>";
  selectElement('Veterinarian:', 'vetptr', $client['vetptr'], array(), 'vetChanged(this)');  // init'd by rebuildSelectOptions
  fauxLink(' view', 'viewClinic("vet")', 0, "View the selected vet.");
	echo "</td></tr>\n"; // end R2
	
	$systemUser = $client['userid'] ? findSystemLogin($client['userid']) : null;
	if(!is_array($systemUser)) $systemUser = null;
	$args = array('roleid'=>$client['clientid'], 'target'=>'systemLoginButton', 'lname'=>$client['lname'], 'fname'=>$client['fname'], 'nickname'=>$client['nickname'], 'email'=>$client['email']);
	if($systemUser) $args['userid'] = $systemUser['userid'];
	$args['role'] = 'client';
	foreach($args as $k => $v)
	  $argstring[] = "$k=".urlencode($v);
	$argstring = join('&', $argstring);
	$systemLoginEditButton = $systemUser 
														? $systemUser['loginid'].($systemUser['active'] ? '' : " (inactive login)") 
														: 'Set Client Login';
	$systemLoginEditButton = echoButton('systemLoginButton', $systemLoginEditButton, "editLoginInfo(\"$id\", \"$argstring\")", null, null, 1);
	if($systemUser['loginid'] && staffOnlyTEST()) 
		$systemLoginEditButton .= " ".fauxLink('Validate Login', 'validateLogin()', 1, 'Make sure this client can log in.');
	echo "<tr><td><table>";
	if($systemUser['loginid'] && mattOnlyTEST()) $userPtrDisplay = " ({$systemUser['userid']})";
	labelRow("System Login$userPtrDisplay:", '', $systemLoginEditButton, null, null, null, null, 'raw');
	if($_SESSION['serviceagreementsenabled']) {
		if(!$client['userid']) $agreementSigned = "<i>No user login</i>";
		else {
			$agreementSigned = clientAgreementSigned($client['userid']);
			if(!$agreementSigned) $agreementSigned = "No";
			else {
				$agreementStyle = "style='text-decoration:underline;";
				if($_SESSION['preferences']['offerManagerLinkToClientsAgreement']) { // [BETA] Make Service Agreement in Client Profile a Link
					//$agreementClick = "onclick=\"openConsoleWindow('viewsignedagreement', 'agreement-signed-by-client.php?clientid=$id', 900, 700);\"";
					$agreementStyle = "style='text-decoration:underline;cursor:pointer;";
				}
				$agreementSigned = "<span $agreementClick title='Signed ".longestDayAndDateAndTime(strtotime($agreementSigned['agreementdate']))."'"
																	." $agreementStyle'>".($agreementSigned['agreementptr'] < 0 ? '<b>(Global)</b> ' : '')
																	.$agreementSigned['label']
																	."</span>";
			}
		}
		if($agreementSigned != "No" /* && (staffOnlyTEST() || dbTEST('happytailspetpal')) */) {
			$printerIcon = '<span class="fontSize1_2em">&#128438; </span>'; // '&#128438;' = printer icon
			$agreementSigned = fauxLink("$printerIcon$agreementSigned", "openConsoleWindow(\"viewagreement\", \"client-agreement-signed.php?id=$id\", 700, 700)", 
									$elementnoEcho=true, $elementtitle='View agreement', $elementid=null, $elementclass='', $elementstyle='cursor:pointer');
		}

		labelRow("Agreement signed:", '', $agreementSigned, null, null, null, null, 'raw');
		labelRow('Client set up:', '', ($client['setupdate'] ? shortDate(strtotime($client['setupdate'])) : 'unknown'), null, null, null, null, 'raw');
		if($id && dbTEST('leashtimecustomers') /*&& mattOnlyTEST()*/) {
			$trialDiscontinuationBizId = fetchRow0Col0("SELECT garagegatecode FROM tblclient WHERE clientid = $id");
			$trialDiscontinuationURL = !$trialDiscontinuationBizId ? 'NO GARAGE GATE CODE SET.'
																		: getTrialDiscontinuationFormURL($trialDiscontinuationBizId);
			labelRow('Deactivation Form URL', '', 
				echoButton('', 'Show Deactivation Form URL', $onClick="$(\"#trialDiscontinuationURL\").css(\"display\",\"inline\")", $class='', $downClass='', $noEcho=true, $title='Generate a URL to a Discontinue form to send to a client business.'),
					null, null, null, null, 'raw');
			echo "<tr><td colspan=2><span id='trialDiscontinuationURL' style='display:none;'>$trialDiscontinuationURL</span></td></tr>";
		}
}
	echo "</table></td><td>";
	
	echo "<table style='margin-left:-2px;'><tr><td>";
	dumpReferralEditor($client['referralcode'], $client['referralnote']);
	echo "</td></tr>";
	echo "<td>";
	if($_SESSION['discountsenabled']) {
		echo "<table border=0><tr><td valign=top>";
		dumpDiscountEditor($client);
		echo "</td><td valign=top>";
		dumpDiscountTip();
		echo "</td></tr></table>";
	}
	echo "</td></tr></table></td></tr>";
	

	
	echo "<tr>\n"; // R4
	//$notes = isset($client['notes']) ? htmlentities($client['notes']) : '';
	echo "<td valign=top>Notes:<br><textarea name='notes' cols=40 rows=3>{$client['notes']}</textarea><p>";
	
	//echo "<td valign=top>Office Notes: ";
	if($_SESSION["officenotes_logbook_enabled"]) {
		require_once "item-note-fns.php";
		$numnotes = $id ? fetchRow0Col0("SELECT count(*) FROM relitemnote WHERE itemtable = 'client-office' AND itemptr = $id") : '0';
		echo "<td valign=top>Office Notes (<span id='officenotescount'>".($numnotes ? $numnotes : '0')."</span>): ";
		if(!$id) echo "Please save this client first before entering Office Notes";
		else {
			echo "<span id='officenotessection'>";
			itemSummary($id, 'client-office', 3, 'Click to View Office Notes Log');
			echo "</span>";
		}
	}
	else {
		//$officenotes = isset($client['officenotes']) ? htmlentities($client['officenotes']) : '';
		echo "<td valign=top>Office Notes: ";
		echo "<br><textarea name='officenotes' cols=40 rows=3>{$client['officenotes']}</textarea></td>";
	}
	echo "</td>";
	
	//$officeNotesLogBook = $id && $_SESSION["officenotes_logbook_enabled"] ? fauxLink('View Office Notes Log', "viewOfficeNotesLog($id)", 'noecho') : '';
	//echo "<td valign=top>Office Notes: $officeNotesLogBook<br><textarea name='officenotes' cols=40 rows=3>$officenotes</textarea></td>";
	
	echo "</tr>\n"; // end R4
	echo "<tr><td style='color:red;'>* required field.</td></tr>\n"; // R2
	echo "</table>\n"; // Basic tab
}

function dumpDiscountTip() {
	if(!staffOnlyTEST()) return;
	$html = "<h2>Using Discounts</h2><span class=fontSize1_1em>
	When you set a discount for a client, you are indicating that visits created
	for this client from now on (limited by the details of the discount) will be automatically discounted,
	regardless of the type of the service.
	<p>
	Visits that are already on the calendar are not affected by choosing a discount here.
	<p>
	To change the discounts of existing visits, please edit the visits directly, or change the
	discount setting in the schedules to which they belong.
	<p>
	We intend to augment our discount functionality soon to make discounts easier to manage,
	so please watch this space!</span>";
	$html = str_replace("\r", ' ', $html);
	$html = str_replace("\n", ' ', $html);
	$action = "$.fn.colorbox({html:\"$html\", width:\"600\", height:\"400\", scrolling: true, opacity: \"0.3\"});";	
	echo "<img src='art/lightningheadprofile.gif' onclick='$action'>";

}

function dumpDiscountEditor($client) {
	require_once "discount-fns.php";
	$discount = $client['clientid'] ? getCurrentClientDiscount($client['clientid']) : '0';
	$discounts = array('No Discount'=>0);
	$memberIdDisplayMode = 'none';
	foreach(getDiscounts(1) as $row) {
		if($row['discountid'] == $discount['discountptr']) {
			$discountVal = $row['discountid'].'|'.$row['memberidrequired'];
			$memberIdDisplayMode = $row['memberidrequired'] ? $_SESSION['tableRowDisplayMode'] : 'none';
		}
		$discounts[$row['label']] = $row['discountid'].'|'.$row['memberidrequired'];
	}

	echo "<table style='border: solid black 1px;'>";
	selectRow('Discount:', 'discount', $discountVal, $discounts, 'discountChanged(this)', $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $extraTDs=null);
	if($discount['discountptr'] && !$discountVal) {
		$oldDiscount = getDiscount($discount['discountptr']);
		labelRow('Inactive Discount:', '', $oldDiscount['label']);
	}
	$startDate = $discount['start'] && $discount['start'][0] == '0' ? '' : $discount['start'];
	labelRow('First applied:', '', ($startDate ? shortDate(strtotime($startDate)) : '--'));
	//inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null)
	inputRow('<font color="red">*</font> Member ID:', 'memberid', $discount['memberid'], null, null, 'memberidrow', "display:$memberIdDisplayMode;");
	echo "</table>";
}

function dumpReferralEditor($referralcode, $referralnote) {
	if(!$_SESSION['referralsenabled']) return;
	require_once "referral-fns.php";
	hiddenElement('referralcode', $referralcode);
	hiddenElement('referralnote', $referralnote);
	$path = getReferralPath($referralcode);
	if($path && $path[0] == '*inactive*') {
		$inactiveReferral = true;
		unset($path[0]);
	}
	if(!$path) $path = array('-- Unspecified--');	
	$codes = array_keys($path);
	
	echo "<div id='referraldisplay' style='display:block;'><table>";
	$referralcodetitle = $inactiveReferral ? "title = 'Obselete referral category'" : '';
	$referralCodeStyle = $inactiveReferral ? "style='font-style:italic;'" : '';
	$pathLabel = "<div class='standardInputLikeBox' style='display:inline;'><span id='referralcodelabel' $referralcodetitle $referralCodeStyle style='cursor:pointer;' onClick='openReferralEditor()'>".join(' > ', $path)."</span></div>";
	$referralnotetitle = '';
	if(strlen($referralnote) > 25) {
		$referralnotetitle = "title = \"$referralnote\"";
		$referralnote = truncatedLabel($referralnote, 25);
	}
	$noteLabel = "<div class='standardInputLikeBox' style='display:inline;'><span id='referralnotelabel' $referralnotetitle style='cursor:pointer;' onClick='openReferralEditor()'>$referralnote</span></div>";
	labelRow('Referral:', '', $pathLabel.' '.$noteLabel, null, null, null, null, 'raw');
	//echo "<tr><td colspan=2><img src='art/spacer.gif' height=1></td></tr>";
	//labelRow('Note:', '', $noteLabel, null, 'standardInputLikeBox', null, null, 'raw');
	echo "</table></div>";
	echo "<div id='referraleditor' style='display:none;border:solid black 1px;'><table>";
	for($i=1; $i <= 3; $i++) {
		$label = $i==1 ? 'Referral:' : '';
		selectRow($label, "referral_$i", $codes[$i-1], array(), $onChange="updateReferralSelects(this)", $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $extraTDs=null);
	}
	inputRow('Note:', 'referralnoteinput', $referralnote, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null);
	$buttons = echoButton('', 'Done', 'closeReferralEditor(1)', 'SmallButton', 'SmallButtonDown', 1).' '.echoButton('', 'Cancel', 'closeReferralEditor(0)', 'SmallButton', 'SmallButtonDown', 1);
	labelRow('', '', $buttons, null, null, null, null, 'raw');
	
	echo "</table></div>";
}

function dumpHomeInfoTab($client) {
	// two column table
	echo "<table width=100%>\n";
	echo "<tr><td valign=top><table>\n"; //leashloc,foodloc,parkinginfo,garagegatecode,nokeyrequired table
	$pairs = 'leashloc|Leash / Pet Carrier Location||foodloc|Food Location||parkinginfo|Parking Info||garagegatecode|Garage / Gate Code';
		if($_SESSION['secureKeyEnabled']) $pairs .= '||nokeyrequired|No Key Required';
	foreach(explodePairsLine($pairs) as $key => $label) {
		if($key == 'nokeyrequired') checkboxRow($label.':', $key, $client[$key]);
		else inputRow($label.':', $key, $client[$key], null, 'streetInput');
	}
if(staffOnlyTEST() && dbTEST('dogslife')) {
	$extraCodes = explodePairsLine("outerdoorcode|Outer Door||doorcode|Door Code||lockboxcode|Lock Box Code");
	$clientProps = 
		$client['clientid'] ? getClientPreferences($client['clientid'], $keys=array_keys($extraCodes))
		: array();
	$toggles = array();
	foreach($extraCodes as $key => $val) {
		if($clientProps[$key]) inputRow($extraCodes[$key].':', "prop_$key", $val, null, 'streetInput');
//inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null, $extraContent=null, $inputCellPrepend=null)
		else {
			inputRow($extraCodes[$key].':', "prop_$key", null, null, 'streetInput', "row_$key", 'display:none');
//fauxLink($label, $onClick, $noEcho=false, $title=null, $id=null, $class=null, $style=null)
			$toggles[] = fauxLink($extraCodes[$key], 
										"this.style.display=\"none\";$(\"#row_$key\").toggle()", 
										'noEcho', !$title, "link_$key", null, "display:inline");
		}
	}
	if($toggles) {
		echo "<tr><td colspan=2>".join(' - ', $toggles)."</td></tr>";
	}
	
}
	
	
	
	
	
	echo "</table></td>\n";
	
	
	$directions = $client['directions']; // isset($client['directions']) ? htmlentities($client['directions']) : '';
	echo "\n<td valign=top colspan=2>";
	echo "Directions to Home<br><textarea name='directions' cols=48 rows=5>$directions</textarea>";
  echo "</td></tr>\n";
  
	echo "<tr>\n<td>";  // Keys
	if($_SESSION['secureKeyEnabled']) clientKeyTable($client);
  echo "</td>\n"; // End Keys
	//$raw = explode(',', 'alarmcompany,Alarm Company,alarmcophone,Alarm Company Phone,alarmpassword,Password,disarmalarm,Disarm,armalarm,Arm,alrmlocation,Location');
	$raw = explode(',', 'alarmcompany,Alarm Company,alarmcophone,Alarm Company Phone,alarminfo,Alarm Info');
	for($i=0;$i < count($raw) - 1; $i+=2) $fields[$raw[$i]] = $raw[$i+1];
	echo "<td style='vertical-align:top'><table width=100%>\n<tr><td colspan=2>Alarm</td></tr>";  // Alarm
	foreach($fields as $key => $label) {
		if($key != 'alarminfo') inputRow($label.':', $key, $client[$key]);
		else {
			echo "\n<td valign=top colspan=2>";
			echo "$label:<br><textarea name='alarminfo' cols=48 rows=5>{$client[$key]}</textarea>";
			echo "</td></tr>\n";
		}
	}
  echo "</table></td>\n"; // End Alarm
  echo "</tr>\n";
	echo "</table>\n";
}

function clientKeyTable($client) {
	// I will assume that there is only one key (with multiple copies) per client
	$keys = getClientKeys($client['clientid']);
	$key = $keys ? $keys[0] : array();
	if(false /* OLD WAY */ ) keyTable($key);
	else {
		$keyId = isset($key['keyid']) ? sprintf("%04d", $key['keyid']) : 'No Key';
		$printKeyButton = 
				"$keyId "
				.echoButton('', 'Edit / Print Labels', 'printKeyLabels()', '', '', true, 'Click here to save the client and print key labels.');
		keyTable($key, $printKeyButton);
if($client['clientid'] && $_SESSION['preferences']['enableKeyOfficeNotes']) { // added this to key-fns.php as well (fn keyTableForEditor).
		$officeOnlyKeyNotes = getClientPreference($client['clientid'], 'officeonlykeynotes');
		echo "<table>";
		textRow("Office Only Key Notes", 'officeonlykeynotes', $officeOnlyKeyNotes, $rows=3, $cols=46, $labelClass=null, $inputClass=null, $rowId=null, $rowStyle=null, $maxlength=null, $rowClass=null, $textColSpan=2);
		echo "</table>";
}		
	}
}

function dumpAddressTab($client) {
	// two column table
	echo "<table width=100%>\n";
	echo "<tr>\n<td>";
	addressTable('Home Address', '', $client, true);
  echo "</td><td>\n";
	addressTable('Mailing Address', 'mail', $client, false, true);
  echo "</td></tr>\n";
  echo "<tr><td colspan=2 class='tiplooks'>TIP: Enter the ZIP code first and you won't have to type in City or State.</td></tr>\n";
	echo "</table>\n";
}

// ============= Functions
$allRawNames = "$rawBasicCol1Fields,$rawBasicOtherFields,zip,Home Address ZIP Code";

if($rawServiceTypeFields) {
	$serviceTypePairs = explode('|||', $rawServiceTypeFields); // servicecharge_158','3 walks 60 min|||servicecharge_156','Cancelled with credit|||...
	
	foreach($serviceTypePairs as $pair) {
		$pair = explode(',', $pair);
		$prettyServices[] = $pair[0];
		$prettyServices[] = $pair[1];
	}
}
if($prettyServices) $allRawNames .= ",".join(",",array_map('addslashes', (array)$prettyServices));
$prettyNames = "'".join("','",array_map('addslashes', explode(',',$allRawNames)))."'";

$serviceTypeConstraints = '';
for($i = 0; $i < count($prettyServices); $i+=2) {
	$part = addslashes($prettyServices[$i]);
  $serviceTypeConstraints .= ", '$part','','UNSIGNEDFLOAT'\n";
}

if($_SESSION['referralsenabled']) {
	$referralCats = getReferralCategories($_SESSION['preferences']['masterPreferencesKey'], $inactiveAlso=1);  // return site's own ref cats, or master cats
	if($_SESSION['orgptr'] && ($orgReferralCats = getOrganizationReferralCategories($_SESSION['orgptr'], $inactiveAlso=1))) {
		foreach($orgReferralCats as $i => $cat) {
			$referralCats[] = externalReferralCategory($cat);
		}
	}
	$referralCats = referralCategoriesDescription($referralCats);
}
?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language="JavaScript" src='datepicker.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='referral-categories.js'></script>
<script language='javascript'>
function findTransactionByID() {
	$.fn.colorbox({href:"find-transaction.php", iframe:true,  width:"590", height:"470", scrolling: true, opacity: "0.3"});
}

function chooseBreed(breedfld, typefld) {
	var pettype = document.getElementById(typefld);
	pettype = pettype.options[pettype.selectedIndex].value;
	$.fn.colorbox({href:"breeds.php?pettype="+pettype+"&target="+breedfld, iframe:true,  width:"490", height:"470", scrolling: true, opacity: "0.3"});
}

function update(target, value) {
	if(value && (typeof value == 'string') && value.indexOf('alert') != -1) alert(value);	
	
	if(target.indexOf('breed:') == 0) {
		target = target.substring('breed:'.length);
		document.getElementById(target).value = value;
	}
	else updateForSavedClient(target, value);	
}	
<?

//if(TRUE || staffOnlyTEST() || dbTEST('doggiewalkerdotcom')) {
	if(function_exists('dumpCustomFieldJavascript')) dumpCustomFieldJavascript($id);
	if(function_exists('dumpNRSectionJS')) dumpNRSectionJS();
	echo <<<NRS
function viewNRCalendar(packageid, irreg) {
	var dest = irreg ? "calendar-package-irregular.php" : "calendar-package-nr.php";
	openConsoleWindow("viewcalendar", dest+"?packageid="+packageid, 900, 700);
}
NRS;
//}


if($id && $_SESSION["flags_enabled"]) {
	require_once "client-flag-fns.php";
	clientFlagPanelJS();
}
?>

<? if($_SESSION['referralsenabled']) { ?>
var referralCategories = <?= $referralCats ?>;
<? } ?>
var activeOnOpen = <?= $inactive ? 0 : 1 ?>;

<?= $initializationJavascript ?>

<? if(isset($clienteditalert)) echo "alert(\"$clienteditalert\");\n"; ?>

setPrettynames(<?= $prettyNames ?>);	
function checkAndSubmit(continueEditing, justcheck) {
	if(typeof justcheck=="undefined" || justcheck==null || justcheck==0 || justcheck=="") justcheck = false;
	<?  ?>
	<? \n" ?>
	$('input[type="button"]').prop('disabled', true); // prevent dup clients
	<? if($_SESSION['referralsenabled']) echo "var referralMessage = referralIsIncomplete(true);\n"; ?>
	
	var zipRequired = '';;
	var zipSel = document.getElementById('zip');
	if(zipSel.type == 'select-one') 
		zipRequired = zipSel.options[zipSel.selectedIndex].value == 0 
										&& (jstrim(document.getElementById('zip_unprotected').value) == '') ? 1 : '';
	else zipRequired = !jstrim(zipSel.value);
	if(zipRequired) zipRequired = 'Home Address ZIP Code is required.';
	
	var memberidrequired = '';
	var discountsel = null;
	if(discountsel = document.getElementById('discount')) {
		if(discountsel = discountsel.options[discountsel.selectedIndex].value)
			if(discountsel && discountsel != 0 && discountsel.split('|')[1] != 0 &&
				!jstrim(document.getElementById('memberid').value))
				memberidrequired = 'A member ID is required for the selected discount.';
	}
	
	var badPetNames = null;
	for(var i = 1; document.getElementById('name_'+i); i++)
		if(!badPetNames)
			badPetNames = petNameProblem(document.getElementById('name_'+i).value);
	document.getElementById('fname').value = jstrim(document.getElementById('fname').value);
	document.getElementById('lname').value = jstrim(document.getElementById('lname').value);
  if(!MM_validateForm(
		  'fname', '', 'R',
		  'lname', '', 'R',
		  'email', '', 'isEmail',
		  'email2', '', 'isEmail',
		  memberidrequired, '', 'MESSAGE',
		  badPetNames, '', 'MESSAGE'
		  <?= $_SESSION['preferences']['restrictTerritory'] ? ", zipRequired, '', 'MESSAGE'" : "" ?>
		  <?= $serviceTypeConstraints ?>
		  <? if($_SESSION['referralsenabled']) echo ",\n referralMessage, '', 'MESSAGE'" ?>
		  <? if($_SESSION['discountsenabled']) echo ",\n referralMessage, '', 'MESSAGE'" ?>
		  
		  )) {
		$('input[type="button"]').prop('disabled', false);
	<? \n" ?>
				
		return false;
	}
	else {
		if(justcheck) return true;
		if(activeOnOpen && !document.clienteditor.active.checked) {
		  if(!confirm("Marking this client Inactive will cause all of the client's\n"+
		              "service packages and incomplete visits to be deleted.\n\n"+
		              "Completed visits will be retained.\n\n"+
		              "Click Ok to continue or Cancel to reconsider."))
		     return;
		}
		document.clienteditor.continueEditing.value=continueEditing;
		document.clienteditor.submit();
	}
}

function emailCalendar() {
	// the email icon appears only when there are visits
	var range = "&starting="+safeDate(document.getElementById('starting').value)
							+"&ending="+safeDate(document.getElementById('ending').value);
	openConsoleWindow('emailcomposer', 'comm-visits-composer.php?client='+document.getElementById('client').value+range,640,500);
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

function viewNonRecurringServicesInvoice(starting, ending, packageptr) {
	var email = document.getElementById('email').value;
	var clientid = document.getElementById('client').value;
	if(typeof starting == 'undefined') starting = escape(document.getElementById('starting').value);
	if(typeof ending == 'undefined')  ending = escape(document.getElementById('ending').value);
	//var lookahead = document.getElementById('lookahead').value;
	var args = '&excludePriorUnpaidBillables=1&firstDay='+starting+'&lastDay='+ending
								+"&invoiceby=email&email="+email+"&packageptr="+packageptr+"&packageptr="+packageptr
								+"&literal=1";
	openConsoleWindow('invoiceview', 'billing-invoice-view.php?id='+clientid+args, 800, 800);
}

function viewServicesInvoice(starting, ending, exclude) {  // BILLING 0 (prepayments)
	var email = document.getElementById('email').value;
	var clientid = document.getElementById('client').value;
	var origincludeall = ''; //document.getElementById('origincludeall') && document.getElementById('origincludeall').value;
	if(exclude == undefined) exclude = 0;
	if(typeof starting == 'undefined') starting = escape(document.getElementById('starting').value);
	if(typeof ending == 'undefined')  ending = escape(document.getElementById('ending').value);
	//var lookahead = document.getElementById('lookahead').value;
	var args = '&excludePriorUnpaidBillables='+exclude+'&firstDay='+starting+'&lastDay='+ending
								+"&invoiceby=email&email="+email
								+(origincludeall ? '&includeall=1' : '');
	openConsoleWindow('invoiceview', 'prepayment-invoice-view.php?id='+clientid+args, 800, 800);
}

function viewBillingServicesInvoice(starting, ending, exclude) {  // BETA BILLING 1
	var email = document.getElementById('email').value;
	var clientid = document.getElementById('client').value;
	var origincludeall = ''; //document.getElementById('origincludeall') && document.getElementById('origincludeall').value;
	if(exclude == undefined) exclude = 0;
	if(typeof starting == 'undefined') starting = escape(document.getElementById('starting').value);
	if(typeof ending == 'undefined')  ending = escape(document.getElementById('ending').value);
	//var lookahead = document.getElementById('lookahead').value;
	var args = '&excludePriorUnpaidBillables='+exclude+'&firstDay='+starting+'&lastDay='+ending
								+"&invoiceby=email&email="+email
								+(origincludeall ? '&includeall=1' : '')
								+"&literal=1";

	openConsoleWindow('invoiceview', 'billing-invoice-view.php?id='+clientid+args, 800, 800);
}

function viewBillingStatement(starting, ending) {  // BETA BILLING 2
	var exclude = !confirm("Click OK to include any prior unpaid items.");
	var email = document.getElementById('email').value;
	var clientid = document.getElementById('client').value;
	var origincludeall = ''; //document.getElementById('origincludeall') && document.getElementById('origincludeall').value;
	if(exclude == undefined) exclude = 0;
	if(typeof starting == 'undefined') starting = escape(document.getElementById('starting').value);
	if(typeof ending == 'undefined')  ending = escape(document.getElementById('ending').value);
	//var lookahead = document.getElementById('lookahead').value;
	var args = '&excludePriorUnpaid='+(exclude ? 1 : 0)+'&firstDay='+starting+'&lastDay='+ending
								+"&invoiceby=email&email="+email
								+(origincludeall ? '&includeall=1' : '')
								+"&literal=1";

	openConsoleWindow('invoiceview', 'billing-statement-view.php?id='+clientid+args, 800, 800);
}


function viewNonRecurringServicesStatement(starting, ending, packageptr) { // BETA 2 EZ SCHEDULE INVOICE
	var email = document.getElementById('email').value;
	var clientid = document.getElementById('client').value;
	if(typeof starting == 'undefined') starting = escape(document.getElementById('starting').value);
	if(typeof ending == 'undefined')  ending = escape(document.getElementById('ending').value);
	//var lookahead = document.getElementById('lookahead').value;
	var args = '&excludePriorUnpaid=1&literal=1'
								+"&invoiceby=email&email="+email+"&packageptr="+packageptr;
// https://leashtime.com/billing-statement-view.php?id=2012&firstDay=2015-10-16&lookahead=15&email=matt@leashtime.com&literal=1&excludePriorUnpaid=1
	openConsoleWindow('invoiceview', 'billing-statement-view.php?id='+clientid+args, 800, 800);
}

function saveAndRedirect(redirectUrl) {
	document.clienteditor.rd.value=redirectUrl;
	if(!checkAndSubmit()) document.clienteditor.rd.value='';
}

function discountChanged(el) {
	var displayMode = el.selectedIndex == 0
											|| el.options[el.selectedIndex].value.split('|')[1] == 0
										? 'none'
										: '<?= $_SESSION['tableRowDisplayMode'] ?>';
	document.getElementById('memberidrow').style.display = displayMode;
}

function showKeyCopies(sel) {
	var num = sel.options[sel.selectedIndex].value;
	for(var i=1; i<=<?= $maxKeyCopies ?>; i++) {
		var displayMode = i <= num ? '<?= $_SESSION['tableRowDisplayMode'] ?>' : 'none';
		document.getElementById("row_possessor_"+i).style.display=displayMode;
	}
}

// $raw = explode(',', 'keyid,Key ID,locklocation,Lock Location,description,Description,bin,Bin,copies,Copies');

function ensureOneKey() {
	var arr = ['locklocation', 'description', 'bin'];
	var found = false;
	for(var i=0;i<arr.length;i++)
		if(document.getElementById(arr[i]).value.length != 0) found = true;
	if(found && document.getElementById('copies').value == 0)
	  document.getElementById('copies').value = 1;
}

function printKeyLabels() {
	var sel = document.getElementById('copies');
	var num = sel.options[sel.selectedIndex].value;
	if(false /* num == 0*/) {
		alert("There are no copies of this key registered.\nPlease register at least one copy before\nyou try to print labels.");
		return;
	}
	
	if(!confirm("This client must be saved before you print key labels.\n "+
											"Click OK to save the client and continue."))
		 return;
	else {
		checkAndSubmit('keyedit');
	}
}

function editLoginInfo(clientid, argstring) {
	if(!clientid) {
		if(!confirm("This client has not been saved, but must be saved\nbefore a system login can be set up.\n"+
	                      "Click OK to save the client and continue."))
	     return;
	  else {
			checkAndSubmit('systemloginsetup');
		}
	}
	else {
		if(userid != '' && (argstring.indexOf('userid') == -1)) argstring = argstring+"&userid="+userid;
		var url = "login-creds-edit.php?"+argstring;
		var w = window.open("",'systemlogineditor',
			'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+400+',height='+400);
		w.document.location.href=url;
		if(w) w.focus();
	}
}

function viewClinic(vet) {
	if(vet) {
		var el = document.getElementById('vetptr');
		if(el.selectedIndex == 0) alert('Please select a vet to view');
		else openConsoleWindow('clinic', 'viewVet.php?id='+el.options[el.selectedIndex].value,700,500);
	}
	else {
		var el = document.getElementById('clinicptr');
		if(el.selectedIndex == 0) alert('Please select a clinic to view');
		else openConsoleWindow('clinic', 'viewClinic.php?id='+el.options[el.selectedIndex].value,700,500);
	}
}
		

function openConsoleWindow(windowname, url,wide,high) {  //NOT USED WHEN common.js is loaded
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}

function toggleDate(rowId) {
	var el = document.getElementById(rowId+'_headers');
	el.style.display = el.style.display == 'none' ? '<?= $_SESSION['tableRowDisplayMode'] ?>' : 'none';
	var el = document.getElementById(rowId+'_row');
	el.style.display = el.style.display == 'none' ? '<?= $_SESSION['tableRowDisplayMode'] ?>' : 'none';
	var n = rowId.split('_');
	n = n[1];
	document.getElementById('day-shrink-'+n).src = (el.style.display == 'none' ? 'art/down-black.gif' : 'art/up-black.gif');
}

function primaryClicked(el) {
	var choice = el.value.substring(el.value.indexOf('_')+1);
	ajaxGetAndCallWith("cc-primary-set.php?id=<?= $id ?>&choice="+choice, 
			function(x, text) {alert(text);}, 'primary!')
}

<?


$clinicData = fetchAllClinicOptionsSelecting($client['clinicptr']);
$cptr = $client['vetptr'] && !$client['clinicptr'] ? -1 : $client['clinicptr'];
$vetData = fetchAllVetOptionsSelecting($client['vetptr'], $cptr);
dumpClinicAndVetSelectElementJS('clinicptr', 'vetptr', $clinicData, $vetData);

dumpPopCalendarJS();
dumpClickTabJS();
dumpPetJS();
dumpPrefsJS();
dumpShrinkToggleJS();
dumpPhoneRowJS();
dumpTimeFramerJS('timeFramer');

if(function_exists('dumpZipLookupJS'))  {
	dumpZipLookupJS();
?>

function mailToHomeClicked(el, prefix) {
	if(typeof el == "string") el = document.getElementById(el);
	var keys = new Array('zip','street1','street2','city','state');
	for(var i = 0 ; i < keys.length; i++)
		document.getElementById(prefix+keys[i]).disabled = el.checked;
}

// ZIP - CITY FUNCTIONS
function openCityChooser(prefix) {
	// open the city chooser for multi-town zips (see gui-fns.php > addressTable() )
	document.getElementById(prefix+'_citychoices').style.display='block';
}

function displayBlockOrNone(el, block) {
	el.style.display = block ? 'block' : 'none';
}

function chooseCity(el, block) {
	supplyLocationInfo(el.getAttribute('citystate'), el.getAttribute('addressgroupid'), true);
	displayBlockOrNone(el.parentNode.parentNode, null);
}

function supplyMultiCityInfo(cityStates,addressGroupId) {
	var listhtml = "<span class='fauxlink' onclick='displayBlockOrNone(this.parentNode, null)'>(close list)</span><p>";;
	if(cityStates != 'NO_CITIES') {
		var cityStates = cityStates.split('||');
		var choices = '';
		for(var i = 0; i < cityStates.length; i++) {
			var pair = cityStates[i].split('|');
			choices += "<span class='fauxlink' citystate='"+cityStates[i]+"' addressgroupid='"
								+addressGroupId+"' onclick='chooseCity(this)'>"+pair[0]+(ltrim(pair[1]) != '' ? ", "+pair[1] : '')
								+"</span><br>";
		}
		listhtml += choices;
	}
	document.getElementById(addressGroupId+'_citychoices').innerHTML = listhtml;
	document.getElementById(addressGroupId+'_citychoices').style.display = 'block';
}

function supplyLocationInfo(cityState,addressGroupId,noconfirmation) {
	if(cityState == 'NO_CITIES' || cityState.indexOf('||') > 0) {
		supplyMultiCityInfo(cityState,addressGroupId);
		return;
	}
	var cityState = cityState.split('|');
<? // if(staffOnlyTEST()) echo "alert(cityState);"; ?>	
	if(cityState[0] && cityState[1]) {
		if(cityState[1].length == 1) cityState[1] = ''; // for UK database, which supplies "-" for state
		var city = document.getElementById(addressGroupId+'city');
		var state = document.getElementById(addressGroupId+'state');
		var needConfirmation = false;
		if(city.type == 'text' && noconfirmation != true) {
			needConfirmation = needConfirmation || (city.value.length > 0 && (city.value.toUpperCase() != cityState[0].toUpperCase()));
			needConfirmation = needConfirmation || (state.value.length > 0 && (state.value.toUpperCase() != cityState[1].toUpperCase()));
		}
		if(!needConfirmation || confirm("Overwrite city and state with "+cityState[0]+(ltrim(cityState[1]) != '' ? ", "+cityState[1] : '')+"?")) {
		  if(city.value.toUpperCase() != cityState[0].toUpperCase()) city.value = cityState[0];
		  if(state.value.toUpperCase() != cityState[1].toUpperCase()) state.value = cityState[1];
		  if(document.getElementById('label_'+addressGroupId+'city')) 
		  	document.getElementById('label_'+addressGroupId+'city').innerHTML = cityState[0]; 
		  if(document.getElementById('label_'+addressGroupId+'state')) 
		  	document.getElementById('label_'+addressGroupId+'state').innerHTML = cityState[1]; 
		}
	}
}

// END ZIP - CITY FUNCTIONS


function cancelAppt(appt, cancelFlg, surcharge) {
<? if($readOnlyVisits) { ?>
	 var operation = cancelFlg ? 'cancel' : 'uncancel';
	 openConsoleWindow('editappt', "client-request-appointment.php?id="+appt+"&operation="+operation,530,450);
<?
			} else { 
				
	if($_SESSION['preferences']['confirmVisitCancellationInLists']) 
		echo "var action = cancelFlg ? 'Cancel' : 'Un-cancel';\n
					if(!confirm(action+' this '+(surcharge ? 'surcharge?' : 'visit?'))) {alert('Ok then.'); return;}";
				
?>
	if(surcharge) 	ajaxGetAndCallWith("surcharge-cancel.php?cancel="+cancelFlg+"&id="+appt, update, 'appointments');
	else ajaxGetAndCallWith("appointment-cancel.php?cancel="+cancelFlg+"&id="+appt, update, 'appointments');
<? }
?>}

function quickEdit(id) {
	ajaxGet('appointment-quickedit.php?id='+id, 'editor_'+id);
	document.getElementById('editor_'+id).parentNode.style.display='<?= $_SESSION['tableRowDisplayMode'] ?>';
	return true;
}
	
function updateAppointmentVals(appt) {
	var p, t, s;
	p = document.getElementById('providerptr_'+appt);
	p = p.options[p.selectedIndex].value;
	t = document.getElementById('div_timeofday_'+appt).innerHTML;
	s = document.getElementById('servicecode_'+appt);
	s = s.options[s.selectedIndex].value;
	//ajaxGet('appointment-quickedit.php?save=1&id='+appt+'&p='+p+'&t='+t+'&s='+s, 'editor_'+appt);
//alert('appointment-quickedit.php?save=1&id='+appt+'&p='+p+'&t='+t+'&s='+s);	
	ajaxGetAndCallWith('appointment-quickedit.php?save=1&id='+appt+'&p='+p+'&t='+t+'&s='+s, update, 'appointments');  // must update all appointments since provider may have changed
	document.getElementById('editor_'+appt).parentNode.style.display = 'none';
}

function editInvoice(clientptr) {
	//var asOfDate = new Date();// document.getElementById('asOfDate').value;
	//asOfDate = +'/'+asOfDate.getMonth()+'/'+asOfDate.getFullYear();
	openConsoleWindow('invoiceview', 'invoice-edit.php?client='+clientptr+'&asOfDate=', 800, 800);
}

function editCharge(chargeid) {
	/*var error = null;
	var asOfDate = new Date();// document.getElementById('asOfDate').value;
	//if(!asOfDate) error = prettyName('asOfDate')+' must be supplied first.\n';
	//if(!validateUSDate(asOfDate)) error = prettyName('asOfDate')+' must contain a date in the form MM/DD/YYYY.\n';
	//if(!isPastDate(asOfDate)) error = prettyName('asOfDate')+' must be a date before today.\n';
	if(error) {
		alert(error);
		return;
	}
	asOfDate = escape(asOfDate);*/
	//var url = 'charge-edit.php?lastday='+asOfDate+'&reason=&';
	var url = 'charge-edit.php?id='+chargeid;
	openConsoleWindow('editcharge', url, 600, 260);
}

function addMiscellaneousCharge(client) {
	var winDims = [600,480];
	openConsoleWindow('editcharge', 'charge-edit.php?client='+client, winDims[0], winDims[1]);
}


function notifyUserOfScheduleChange(packageid, silentDenial) {
	var acceptsEmail = '<?= $scheduleUpdatesAccepted ?>';
		if(acceptsEmail) {
			var url ="notify-schedule.php?packageid="+packageid+"&clientid=<?= $id ?>&newPackage=0&offerConfirmationLink=1";
			openConsoleWindow('notificationcomposer', url, 600, 600);
		}
		else if(!silentDenial) alert("Client declines to receive schedule notifications by email.");
}

function checkForDups() {
	var fname = jstrim(document.getElementById('fname').value);
	var lname = jstrim(document.getElementById('lname').value);
	if(fname && lname.length > 1) {
		ajaxGetAndCallWith('possible-duplicate-clients.php?justNames=1&fname='+escape(fname)+'&lname='+escape(lname), 
												showDups, 0)
	}
}

function showDups(unused, content) {
	var divtitle = '';
	if(content) {
		content = content.split('|');
		divtitle = content.join(', ');
		var plural = content.length == 1 ? '' : 's';
		content = content.length+' similar name'+plural+' found.';
	}
	document.getElementById('dupnames').innerHTML = content;
	document.getElementById('dupnames').title = divtitle;
}

<? 
}
$servicesListSpan = getUserPreference($_SESSION['auth_user_id'], 'servicesListSpan');
$servicesListSpan = $servicesListSpan ? $servicesListSpan : '1|3';

if($_SESSION['clientScheduleDateRange']) {
	$dateRange = explode('|', $_SESSION['clientScheduleDateRange']);
	$day1 = shortDate(strtotime($dateRange[0]));
	$dayN = shortDate(strtotime($dateRange[1]));
}
else if($servicesListSpan == 'month') {
	$day1 = shortDate(strtotime(date('Y-m-01')));
	$dayN = shortDate(strtotime("-1 day", strtotime("+ 1 month", strtotime($day1))));
}
else {
	$servicesListSpan = explode('|', $servicesListSpan);
	$priorDays = $servicesListSpan[0] * 7;
	$day1 = shortDate(strtotime("- $priorDays days"));
	$subsequentDays = $servicesListSpan[1] * 7;
	$dayN = shortDate(strtotime("+ $subsequentDays days"));
}
	
$today = shortDate();

if(tableExists('tblconfirmation') && $_GET['notifytime']) {
	if($_GET['notifyschedule']) {
		$packageid = $_GET['notifyschedule'];
		$newPackage = 0;
	}
	else {
		$packageid = $_GET['notifynewschedule'];
		$newPackage = 1;
	}
	$interval = $_SERVER['REQUEST_TIME'] - $_GET['notifytime'];
	if($interval < 2 || $_SESSION['clientEditNotifyToken'] == $_GET['notifytime']) { // avoid spurious notifications on refresh
		// confirm that notification can go out
		if($scheduleUpdatesAccepted) {
			//$url ="notify-schedule.php?packageid=$packageid&clientid=$id&newPackage=$newPackage&offerConfirmationLink=1";
			//echo "openConsoleWindow('notificationcomposer', '$url', 600, 600);";
			echo "notifyUserOfScheduleChange($packageid, 'silentDenial');";
		}
	}
	unset($_SESSION['clientEditNotifyToken']);
}
?>
if('<?= $id ?>') {
	<? if(TRUE || mattOnlyTEST()) { 
			if(mattOnlyTEST() && $_REQUEST['highlightAppt']) {
				$apptDate = fetchRow0Col0("SELECT date FROM tblappointment WHERE appointmentid = {$_REQUEST['highlightAppt']} LIMIT 1", 1);
				if($apptDate < date('Y-m-d', strtotime($day1))) {
					$day1 = $apptDate;
				}
				else if($apptDate > date('Y-m-d', strtotime($dayN))) {
					$dayN = $apptDate;
					$day1 = date('Y-m-d', strtotime("-10 days", strtotime($dayN)));
				}
			}
		
	?>
	$.ajax('client-schedule-list.php?client=<?= $id ?>&starting=<?= date('Y-m-d', strtotime($day1)) ?>&ending=<?= date('Y-m-d', strtotime($dayN)) ?>&limit=-1')
	    .done(function(x) {
							$('#clientappts').html(x);
							$('#apptrow<?= $_REQUEST['highlightAppt'] ?> > td').css('background-color', 'orange');
						});
	<? } else { ?>
	ajaxGet('client-schedule-list.php?client=<?= $id ?>&starting=<?= date('Y-m-d', strtotime($day1)) ?>&ending=<?= date('Y-m-d', strtotime($dayN)) ?>&limit=-1', 'clientappts');
	<? } ?>
}

var d = new Date();
d.setTime(d.getTime()-(90*24*3600*1000));

starting = '<?= $_REQUEST['invoiceStart'] ?>';
starting = starting ? starting : d.getMonth()+1+'/'+d.getDate()+'/'+d.getFullYear();
if(<?= $id ? $id : 0 ?>) ajaxGet('client-invoices-ajax.php?client=<?= $id ?>&starting='+starting+'&ending=<?= $today ?>', 'clientinvoices');

d = new Date();
d.setTime(d.getTime()-(30*24*3600*1000));
starting = d.getMonth()+1+'/'+d.getDate()+'/'+d.getFullYear();
if(<?= $id ? $id : 0 ?>) ajaxGet('client-comms-list.php?id=<?= $id ?>&starting='+starting, 'clientmsgs');

//setPrettynames('msgsstarting,Starting date for messages,msgsending,Starting date for messages');
mailToHomeClicked('mailtohome', 'mail');

function showNRServicesChunk(n) {
	$(".nrservicemorelink_"+n).css("display", "none");
	$(".nrservicemorelink_"+(n+1)).css("display", "<?= $_SESSION['tableRowDisplayMode'] ?>");
	$(".nrservicesection_"+n).css("display", "<?= $_SESSION['tableRowDisplayMode'] ?>");
}
$(".nrs").css("display", "none")

function validateLogin() {
	$.fn.colorbox({href:"validate-system-login.php?role=client&roleid=<?= $id ?>", 
									width:"500", height:"250", iframe: true, scrolling: true, opacity: "0.3"});
}

function optionsAction(el) {
	var action = el.options[el.selectedIndex].value;
	el.selectedIndex = 0;
	if(action == 'viewDiscounts') document.location.href="discounted-visits.php?client=<?= $id ?>";
	if(action == 'visitDetails') openConsoleWindow("visits", "visits-detail-viewer.php?id=<?= $id ?>", 900, 900);
	if(action == 'printVisitSheet') printVisitSheets();
	if(action == 'setVisitListPrefs') document.location.href="client-schedule-prefs.php?client=<?= $id ?>";
	if(action == 'historicalData') document.location.href="historical-data.php?client=<?= $id ?>";
	if(action == 'visitsList') searchForAppointments(false);
	if(action == 'visitsCalendar') searchForAppointments(true);
	if(action == 'addNotes') addNoteToAppointments();
	if(action == 'arrangeMeeting') document.location.href='client-meeting.php?clientptr=<?= $id ?>';
	if(action == 'clientChangeHistory') openConsoleWindow("changeHistory", "client-change-history.php?id=<?= $id ?>", 900, 900);
	if(action == 'printIntakeForm') openConsoleWindow("changeHistory", "intake-form-launcher.php?clientid=<?= $id ?>", 900, 900);
	if(action == 'staffVisitChangeHistory') {
		var starting = document.getElementById('starting').value;
		var ending = document.getElementById('ending').value;
		if(starting) starting = '&starting='+safeDate(starting);
		if(ending) ending = '&ending='+safeDate(ending);
		openConsoleWindow("staffVisitChangeHistory", "staff-visit-details.php?id=<?= $id ?>"+starting+ending, 900, 900);
	}
	if(action == 'staffProvidersWhoHaveServed') {
		$.fn.colorbox({href:"reports-client-sitter-visits.php?clientptr=<?= $id ?>", width:"550", height:"470", iframe: true, scrolling: true, opacity: "0.3"});
	}
	if(action == 'providersWhoWillNotServe') {
		$.fn.colorbox({href:"reports-client-do-not-serve.php?clientptr=<?= $id ?>", width:"550", height:"470", iframe: true, scrolling: true, opacity: "0.3"});
	}
	
	if(action == 'staffMonthlyBillables') {
		//var starting = document.getElementById('starting').value;
		//var ending = document.getElementById('ending').value;
		//if(starting) starting = '&starting='+safeDate(starting);
		//if(ending) ending = '&ending='+safeDate(ending); //+starting+ending
		openConsoleWindow("staffMonthlyBillables", "staff-monthly-billables.php?id=<?= $id ?>", 600, 600);
	}
}

function addNoteToAppointments() {
  if(!MM_validateForm(
		  'starting', '', 'isDate',
		  'ending', '', 'isDate')) return;
	var client = document.getElementById('client').value;
	var starting = document.getElementById('starting').value;
	var ending = document.getElementById('ending').value;
	if(starting) starting = '&starting='+safeDate(starting);
	if(ending) ending = '&ending='+safeDate(ending);
	$.fn.colorbox({href:"visit-notes-editor.php?client="+client
												+starting
												+ending, width:"700", height:"650", iframe: true, scrolling: true, opacity: "0.3"});
	
}

function viewOfficeNotesLog(id, targetnote) {
	$.fn.colorbox({href:"logbook-editor.php?itemtable=client-office&itemptr="+id
												+"&updateaspect=officenotes&&printable=1&targetid="+targetnote
												+"&title=Office Notes", width:"800", height:"650", iframe: true, scrolling: true, opacity: "0.3"});
}
<? if($id && dbTEST('leashtimecustomers')) { ?>
function descriptionBox() {
	var decriptionHREF = "https://leashtime.com/leashtime-customer-details.php?id="+ <?= $id ?>;
	$.fn.colorbox({href:decriptionHREF, width:"750", height:"470", scrolling: true, opacity: "0.3", iframe: true});
}
<? } ?>


<? if($_REQUEST['viewallnotes']) { ?>
$(document).ready(function() { 
	<?= $_REQUEST['viewallnotes'] ? "viewNotes();\n" : '' ?> 
	});
<?
}
?>

function switchToClientSitters() {
	var url = "client-providers.php?id=<?= $id ?>";
	if(confirm('Click OK to save changes before you leave this page.')) {
		if(checkAndSubmit(false, 'justcheck')) {
			document.getElementById('rd').value=url;
		}
		checkAndSubmit();
	}
	else document.location.href=url;
}

function editRecurringNote(packageid) {
	var url = "service-recurring-note-edit.php?packageid="+packageid;
	$.fn.colorbox({href:url, width:"500px", height:"300px", scrolling: true, opacity: "0.3", iframe: true});
	
}
</script>
<?
// ***************************************************************************
include "frame-end.html";
if(isset($openingConfirmation)) {
?>
<script language='javascript'>
alert('<?= $openingConfirmation ?>');
</script>
<?
}
?>
