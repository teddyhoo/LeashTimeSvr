<? //client-quick.php
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
require_once "custom-field-fns.php";
require_once "system-login-fns.php";

// RIGHTS: qk - rights to use quick editor for client


// Determine access privs
$locked = locked('o-');

extract($_REQUEST);  // if POSTed from here, id will be null, but clientid may be set

$id = isset($id) ? $id : null;
$savedClient = $id ? getClient($id) : array();

$breadcrumbs = "<a href='client-list.php'>Clients</a>";
$pageTitle = $savedClient ? "Client: {$savedClient['fname']} {$savedClient['lname']}" : "New Client";

// We may wish to redisplay the submitted (unsaved) provider fields
if($id) 
	$client = array_merge($savedClient);
else {
	$client = array();
	if(isset($requestid)) {
		$request = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = $requestid");
		if($request && !$request['clientptr']) {
			$client['fname'] = $request['fname'];
			$client['lname'] = $request['lname'];
			$client['homephone'] = $request['phone'];
			$client['email'] = $request['email'];
			$client['email2'] = $request['email2'];
			$client['prospect'] = 1;
			//$address = explode("\n", $request['address']);
		}
	}
}
	
// =======================================================
if($_POST && isset($clientid)) {
	$message = '';
		
	
	if($clientid) {
		saveClient();
		saveClientKey($clientid);
		saveClientPets($clientid);
		saveClientContacts($clientid);
		setClientCharges($clientid);
		saveClientCustomFields($clientid, $_POST);
		if(!isset($active) || !$active)
		  deactiveateClient($clientid);
		if($continueEditing) {
			$tab = $continueEditing;
		}
	}
	else {
		saveNewClient();
		$newClientId = mysql_insert_id();
		saveClientKey($newClientId);
		saveClientPets($newClientId);
		saveClientContacts($newClientId);
		setClientCharges($newClientId);
		saveClientCustomFields($newClientId, $_POST);
		// if $requestid update the request to link it to this client
		if($requestid) updateTable('tblclientrequest', array('clientptr'=>$newClientId), "requestid = $requestid", 1);
		$message = "Client {$_POST['fname']} {$_POST['lname']} has been added.";
		if($continueEditing) {
			if($continueEditing == 'another') $client = array();
			else if($continueEditing == 'systemloginsetup') $tab = 'basic';
			else $tab = $continueEditing;
		}
	}
	
	//setClientCharges($clientid);
	
//print_r($_POST);exit;
	if($rd) {
		header ("Location: $rd");
		exit();
	}
		
  if(!$continueEditing) {
		$param = $newClientId ? "newClient=$newClientId" : "savedClient=$clientid";
		header ("Location: client-list.php?$param");
		exit();
	}
  elseif($continueEditing == 'another') {
		$message = urlencode($message);
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
if($warn) echo "<script language='javascript'>alert(\"$warn\");</script>";
?>
<table width=700>
<tr><td><?= $message ?></td>
<td style='text-align:right'> 
<?
$inactive = $id && !$client['active'] ? 1 : 0;

$saveButton = $id ? echoButton('', 'Save Changes', 'checkAndSubmit("")', null, null, 'noEcho')
                  : 	echoButton('', 'Save New Client', 'checkAndSubmit("")', null, null, 'noEcho');
$saveAndAddButton = !$id ? echoButton('', 'Save & Add Another', 'checkAndSubmit("another")', null, null, 'noEcho') : '';
$quitButton = echoButton('', 'Quit', "document.location.href=\"client-list.php?inactive=$inactive\"", null, null, 'noEcho');

if($id) echo $saveButton;
else echo "$saveButton  $saveAndAddButton";
echo " $quitButton"; 

?>
</td></tr></table>
<?

echo "<form name='clienteditor' method='post' enctype='multipart/form-data'>\n";
hiddenElement('MAX_FILE_SIZE', $maxBytes); // see pet-fns.php
hiddenElement('clientid', ($id ? $id : ''));
hiddenElement('continueEditing', '');
hiddenElement('rd', ''); // redirect to... after submit

if($requestid) hiddenElement('requestid', $requestid);


// ============= Functions

$requiredFields = array('fname','lname');
$redStar = '<font color=red>*</font>';
$customFields = getCustomFields('activeOnly');

$rawBasicCol1Fields = 'fname,First Name,lname,Last Name,fname2,Alt First Name,lname2,Alt Last Name,email2,Alt Email,'.
                       'email,Email,active,Active,prospect,Prospect';


//street1,Address,street2,Address 2,city,City,state,State,zip,ZIP';  
$rawBasicOtherFields = 'cellphone,Cell Phone,homephone,Home Phone,workphone,Work Phone,cellphone2,Alt Phone,'.
	               'fax,FAX,pager,Pager,defaultproviderptr,Default Provider';  

//$alarmFields = 'alarmcompany,Alarm Company,alarmcophone,Alarm Company Phone,alarmpassword,Password,disarmalarm,Disarm,armalarm,Arm,alrmlocation,Location';
$alarmFields = 'alarmcompany,Alarm Company,alarmcophone,Alarm Company Phone,alarminfo,Alarm Info';

$otherFields = 'officenotes,Office Notes,notes,Notes,directions,Directions to Home,'.
								'zip,Home ZIP,street1,Home Address,street2,Home Address2,city,Home City,state,Home State,'.
								'mailzip,Mailing ZIP,mailstreet1,Mailing Address,mailstreet2,Mailing Address2,mailcity,Mailing City,mailstate,Mailing State';
								
$homeFields = 'leashloc,Leash Location,foodloc,Food Location,parkinginfo,Parking Info,garagegatecode,Garage/Gate Code,nokeyrequired,No Key Required';								

foreach(array($rawBasicCol1Fields, $rawBasicOtherFields, $alarmFields, $otherFields, $homeFields) as $str) {
	$raw = explode(',', $str);
	for($i=0;$i < count($raw) - 1; $i+=2) $allFields[$raw[$i]] = $raw[$i+1];
}

$streetInputKeys = explode('|', 'leashloc|foodloc|parkinginfo|garagegatecode'.
										'street1|street2|city|mailstreet1|mailstreet2|mailcity');
$checkboxKeys = explode('|', 'active|prospect|nokeyrequired');
										
function dumpField($key, $client) {
	global $id, $allFields, $requiredFields, $redStar, $streetInputKeys, $checkboxKeys, $customFields;
	$label = $allFields[$key];
	if(in_array($key, $requiredFields)) $label = "$redStar $label";
	$val = isset($client[$key]) ? $client[$key] : '';
	//inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null)
	if(in_array($key, $checkboxKeys)) {
		if(!$id && ($key == 'active')) $val = 1;
		checkboxRow($label.':', $key, $val, $labelClass=null, $inputClass='standardInput');
	}
	else if(strpos($key,'email') === 0) inputRow($label.':', $key, $val, null, 'emailInput');
	else if(in_array($key, array('cellphone','cellphone2','homephone','workphone')))
		phoneRow($label.':', $key, $val);
	else if($key == 'defaultproviderptr') {
		/*$activeProviders = array_merge(array('--Select a Sitter--' => ''), getActiveProviderSelections());
		selectRow($label.':', $key, $val, $activeProviders);
		if($val && !in_array($val, $activeProviders)) {
			$oldProvider = fullname(getProvider($val));
			echo "<tr><td colspan=2 style='color:red;font-style:italic'>Sitter <b>$oldProvider</b> is not active.</td</tr>";
		}*/
		
		$options = availableProviderSelectElementOptions($client, null,  '--Select a Provider--');
		selectRow($label.':', $key, $val, $options);
		if($val && !providerInArray($val, $options)) {
			$selectProv = getProvider($val);
			$pName =  providerShortName($selectProv);
			$reason = providerNotListedReason($selectProv, $client);
			echo "<tr><td style='color:red;'colspan=2>This visit is assigned to $pName but should not be because $pName $reason.</td></tr>";
		}
		
		
	}
	else if($key == 'clinicptr')
		selectRow('Veterinary Clinic:', 'clinicptr', $client['clinicptr'], array(), 'clinicChanged(this)');  // init'd by rebuildSelectOptions
	else if($key == 'vetptr')
		selectRow('Veterinarian:', 'vetptr', $client['vetptr'], array(), 'vetChanged(this)');  // init'd by rebuildSelectOptions
	else if($key == 'userid') systemLoginButton($client);
	
	else if(in_array($key, array('officenotes', 'notes', 'directions')))
		noteRow($key, $client, $allFields);
	
	else if($key == 'nokeyrequired') checkboxRow($label.':', $key, $client[$key]);
	else if(in_array($key, $streetInputKeys))
		inputRow($label.':', $key, $client[$key], null, 'streetInput');
		
	else if(in_array($key, array('zip', 'mailzip'))) {
		$prefix = $key == 'zip' ? '' : 'mail';
		inputRow($label, $key, $client[$key], $labelClass=null, $inputClass='standardInput', 
							null,  null, $onBlur="lookUpZip(this.value, \"$prefix\")");
	}
		
		
	else if($key == 'PETS') {
		echo "<tr>\n<td colspan=2>"; 
		petTable(getClientPets($id), $client);
		echo "</td>\n"; 
	}
	
	else if($key == 'KEY') {
		echo "<tr>\n<td colspan=2>";  // Keys
		clientKeyTable($client);
		echo "</td>\n"; // End Keys
	}
	else if($key == 'PRICES') {
		echo "<tr>\n<td colspan=2>"; 
		pricesTable($id);
		echo "</td>\n"; 
	}
	else if($key == 'CUSTOM') {
		echo "<tr>\n<td colspan=2>"; 
		dumpCustomTab($client, $customFields);
		echo "</td>\n"; 
	}
	
	else if($key == 'EMERGENCY') {
		echo "<tr>\n<td colspan=2>"; 
		dumpEmergencyTab();
		echo "</td>\n"; 
	}
	
	else inputRow($label.':', $key, $val, $labelClass=null, $inputClass='standardInput');
}


function noteRow($key, $client, &$fields) {
	$label = $fields[$key];
	echo "<tr>\n";
	$notes = isset($client[$key]) ? htmlentities($client[$key]) : '';
	echo "<td valign=top colspan=2>$label:<br><textarea name='$key' cols=40 rows=3>$notes</textarea></td>";
	echo "</tr>\n";
}

function systemLoginButton($client) {
	$systemUser = $client['userid'] ? findSystemLogin($client['userid']) : null;
	if(!is_array($systemUser)) $systemUser = null;
	$args = array('roleid'=>$client['clientid'], 'target'=>'systemLoginButton', 'lname'=>$client['lname'], 'fname'=>$client['fname'], 'nickname'=>$client['nickname'], 'email'=>$client['email']);
	if($systemUser) $args['userid'] = $systemUser['userid'];
	$args['role'] = 'client';
	foreach($args as $k => $v)
	  $argstring[] = "$k=".urlencode($v);
	$argstring = join('&', $argstring);
	
	$systemLoginEditButton = $systemUser ? $systemUser['loginid'] : 'No login information set';
	$systemLoginEditButton = echoButton('systemLoginButton', $systemLoginEditButton, "editLoginInfo(\"$id\", \"$argstring\")", null, null, 1);
	labelRow('System Login:', '', $systemLoginEditButton, null, null, null, null, 'raw');
}

	
function clientKeyTable($client) {
	// I will assume that there is only one key (with multiple copies) per client
	$keys = getClientKeys($client['clientid']);
	$key = $keys ? $keys[0] : array();
	keyTable($key);
}


function pricesTable($id) {
	global $rawServiceTypeFields;
	$standardRates = getStandardRates();
	$charges = getClientCharges($id);
	echo "<table style='width: 50%;'><tr><td colspan=3 style='text-align:center;font-weight:bold;font-size:1.5em'>Custom Service Prices</td></tr>\n";
	echo "<tr><th>&nbsp;</th><th style='text-align:right;'>Standard Price</th><th>Price</th></tr>\n";
	foreach($standardRates as $key => $service) {
		$stndRate = $service['defaultcharge'];
		$charge = !isset($charges[$key]) ? '' : $charges[$key]['charge'];
		//$service['defaultrate'].($service['ispercentage'] ? '%' : '');
		echo "<tr><td>{$service['label']}</td><td style='text-align:right;'>\$ $stndRate</td><td>";
		labeledInput('', 'servicecharge_'.$key, $charge);
		$rawServiceTypeFields = ($rawServiceTypeFields ? "$rawServiceTypeFields," : '').'servicecharge_'.$key.','.$service['label'];
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


function dumpEmergencyTab() {
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

$order = <<<ORDER
fname,lname,fname2,lname2,email2,email,active,prospect,cellphone,homephone,workphone,cellphone2,fax,pager,defaultproviderptr,
officenotes,notes,CUSTOM,PETS,vetptr,clinicptr,
leashloc,foodloc,parkinginfo,garagegatecode,nokeyrequired,directions,
KEY,alarmcompany,alarmcophone,alarminfo,
zip,street1,street2,city,state,mailzip,mailstreet1,mailstreet2,mailcity,mailstate,
EMERGENCY,PRICES,
ORDER;

$order = str_replace("\r\n",'',$order);
$order = explode(',',$order);

echo "<table>";
foreach($order as $field) dumpField($field, $client);
echo "</table>";
	
if($id) echo $saveButton;
else echo "$saveButton  $saveAndAddButton";
echo " $quitButton"; 




// ============= Functions
$allRawNames = "$rawBasicCol1Fields,$rawBasicOtherFields";
if($rawServiceTypeFields) $allRawNames .= ",$rawServiceTypeFields";
$prettyNames = "'".join("','",explode(',',$allRawNames))."'";
$serviceTypeConstraints = '';
$serviceTypeparts = explode(',',$rawServiceTypeFields);
for($i = 0; $i < count($serviceTypeparts); $i+=2)
  $serviceTypeConstraints .= ", '{$serviceTypeparts[$i]}','','UNSIGNEDFLOAT'\n"

?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language="JavaScript" src='datepicker.js'></script>
<script language='javascript' src='check-form.js'></script>
<script>

var activeOnOpen = <?= $inactive ? 0 : 1 ?>;

<?= $initializationJavascript ?>

<? if(isset($clienteditalert)) echo "alert(\"$clienteditalert\");\n"; ?>


setPrettynames(<?= $prettyNames ?>);	
function checkAndSubmit(continueEditing) {
	var badPetNames = 0;
	for(var i = 1; document.getElementById('name_'+i); i++)
		if(document.getElementById('name_'+i).value.indexOf(',') > -1)
			badPetNames++;
	badPetNames = badPetNames == 0 ? '' : 'Pet names may not have commas in them.';
	
  if(!MM_validateForm(
		  'fname', '', 'R',
		  'lname', '', 'R',
		  'email', '', 'isEmail',
		  'email2', '', 'isEmail',
		  badPetNames, '', 'MESSAGE'
		  <?= $serviceTypeConstraints ?>)) 
		  return false;
	else {
		if(activeOnOpen && !document.clienteditor.active.checked) {
		  if(!confirm("Marking this client Inactive will cause all of the client's\n"+
		              "appointments and service packeages to be deleted.\n\n"+
		              "Click Ok to continue or Cancel to reconsider."))
		     return;
		}
		document.clienteditor.continueEditing.value=continueEditing;
		document.clienteditor.submit();
	}
}

function saveAndRedirect(redirectUrl) {
	document.clienteditor.rd.value=redirectUrl;
	if(!checkAndSubmit()) document.clienteditor.rd.value='';
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

function openConsoleWindow(windowname, url,wide,high) {
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



<?


$clinicData = fetchAllClinicOptionsSelecting($client['clinicptr']);
$vetData = fetchAllVetOptionsSelecting($client['vetptr'], $client['clinicptr']);
dumpClinicAndVetSelectElementJS('clinicptr', 'vetptr', $clinicData, $vetData);

dumpPopCalendarJS();
dumpClickTabJS();
dumpPetJS();
dumpPrefsJS();
dumpShrinkToggleJS();
dumpPhoneRowJS();

if(function_exists('dumpZipLookupJS'))  {
	dumpZipLookupJS();
?>

function supplyLocationInfo(cityState,addressGroupId) {
	var cityState = cityState.split('|');
	if(cityState[0] && cityState[1]) {
		var city = document.getElementById(addressGroupId+'city');
		var state = document.getElementById(addressGroupId+'state');
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
$today = shortDate();
?>
ajaxGet('client-schedule-cal.php?client=<?= $id ?>&starting=<?= $today ?>&ending=<?= $today ?>', 'clientappts');
var d = new Date();
d.setTime(d.getTime()-(7*24*3600*1000));
starting = d.getMonth()+1+'/'+d.getDate()+'/'+d.getFullYear();
if(<?= $id ? $id : 0 ?>) ajaxGet('client-comms-list.php?id=<?= $id ?>&starting='+starting, 'clientmsgs');

//setPrettynames('msgsstarting,Starting date for messages,msgsending,Starting date for messages');

</script>
<?
// ***************************************************************************
include "frame-end.html";
?>

