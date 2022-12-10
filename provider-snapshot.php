<? // provider-snapshot.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "gui-fns.php";
require_once "service-fns.php";
require_once "field-utils.php";
require_once "system-login-fns.php";

locked('o-,d-');

$suppressPayTab = userRole() == 'd' && !adequateRights('#pa');

$prov = getProvider($provid = $_GET['id']);
$rights = getProviderRights($prov['userid']);
$provname = "{$prov['fname']} {$prov['lname']}";
$status = $prov['active'] ? '' 
					: " (inactive".($prov['terminationdate'] ? " as of ".shortDate(strtotime($prov['terminationdate'])) : '').")";
					
$prov['noncompetesigned'] =  $prov['noncompetesigned'] ? 'yes' : 'no'; 				
$prov['hiredate'] =  $prov['hiredate'] ? shortDate(strtotime($prov['hiredate'])) : null; 				
					
$ifNotNullFields = 'employeeid|Employee ID||jobtitle|Job Title||labortype|Labor Type||noncompetesigned|Non Compete On File||hiredate|Hired||terminationreason|Termination Reason';
$empLabels = explodePairsLine($ifNotNullFields);
foreach($empLabels as $fld =>$label) if($prov[$fld]) $dump[$fld] = $prov[$fld];
$empLabels['keyManagementRights'] = "Key Management Rights";

$dump['keyManagementRights'] = strpos($rights, 'ka') ? 'Administrator' : (strpos($rights, 'ki') ? 'Individual' : 'none');

if($dump['donotserve'] = doNotServeClientIds($provid))
	$dump['donotserve'] = 
		fetchCol0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid IN(".join(',', $dump['donotserve']).")");
if(!$dump['donotserve']) unset($dump['donotserve']);
else $empLabels['donotserve'] = 'Do Not Serve';
		
$empLabels['dailyvisitsemail'] = 'Email Daily Schedule';
$empLabels['weeklyvisitsemail'] = 'Email Weekly Schedule';

if(!isset($prov['dailyvisitsemail'])) $prov['dailyvisitsemail'] = $_SESSION['preferences']['scheduleDaily'];
if(!isset($prov['weeklyvisitsemail'])) $prov['weeklyvisitsemail'] = ($_SESSION['preferences']['scheduleDay'] ? 1 : 0);

$dump['dailyvisitsemail'] = $prov['dailyvisitsemail'] ? 'yes' : 'no';
$dump['weeklyvisitsemail'] = $prov['weeklyvisitsemail'] ? 'yes' : 'no';

if($id && $_SESSION['preferences']['reportStaleVisits'] == 2) {
	$empLabels['reportStaleVisitsForProvider'] = 'Report Overdue Visits For Provider';
	$reportStaleVisitsForProvider = getProviderPreference($id, 'reportStaleVisits', $arrayIfDefault=true);
	$reportStaleVisitsForProvider = is_array($reportStaleVisitsForProvider) ? 0 : $reportStaleVisitsForProvider;
	$dump['reportStaleVisitsForProvider'] = $reportStaleVisitsForProvider ? 'yes' : 'no';
}

$employmentInfo = $dump;

$dump = array();
$basicLabels = explodePairsLine('address|Address||nickname|Nickname');
$allPhones = array( 'homephone'=>'Home phone', 'cellphone'=>'Cell phone', 'workphone'=>'Work phone', 'cellphone2'=>'Alt phone');
$basicLabels = array_merge($basicLabels, $allPhones);
$addr = array();
foreach(explode(',', 'street1,street2,city,state,zip') as $f) $addr[] = $prov[$f];
$dump['address'] = oneLineAddress($addr);
$prime = primaryPhoneNumber($prov, $candidateFields=array_keys($allPhones));
foreach(array_keys($allPhones) as $fld) {
	if($prov[$fld]) {
		$prefix = textMessageEnabled($prov[$fld]) ? '(T) ' : '';
		$stripped = strippedPhoneNumber($prov[$fld]);
		$dump[$fld] = $stripped == $prime ? "<b>$prefix$stripped</b>" : "<b>$prefix$stripped</b>";
	}
}
	
$basicLabels['email'] = "Email";
$dump['email'] = $prov['email'] ? $prov['email'] : '--No email address--';

$basicLabels['taxid'] = getI18Property('Labels|ssnortaxid', 'SSN or Tax ID');

$dump['taxid'] = $prov['taxid'] ? $prov['taxid'] : '--No tax ID--';

$basicLabels['username'] = "System User Name";
	
$systemUser = $prov['userid'] ? findSystemLogin($prov['userid']) : null;
$dump['username'] = $systemUser 
													? $systemUser['loginid'].($systemUser['active'] ? '' : " (inactive login)") 
													: 'No System Login Username set';

$basicLabels['maritalstatus'] = "Marital Status";
$dump['maritalstatus'] = $prov['maritalstatus'] ? $prov['maritalstatus'] : '--Not specified--';

$basicLabels['emergencycontact'] = "Emergency Contact";
if($prov['emergencycontact']) $dump['emergencycontact'] = $prov['emergencycontact'];

$basicLabels['notes'] = "Notes";
if($prov['notes']) $dump['notes'] = $prov['notes'];

$basicInfo = $dump;

$dump = array();
$payLabels = explodePairsLine('paymethod|Payment Method||paynotification|Pay Notification||account|Account');
foreach($payLabels as $label =>$fld) 
	if($prov[$fld]) $dump[$fld] = $prov[$fld];
if($prov['paymethod'] == 'dd' && ($prov['ddroutingnumber'] || $prov['ddaccountnumber'])) {
	$prov['ddroutingnumber'] = $prov['ddroutingnumber'] ? $prov['ddroutingnumber'] : 'not supplied';
	$prov['ddaccountnumber'] = $prov['ddaccountnumber'] ? $prov['ddaccountnumber'] : 'not supplied';
	$prov['account'] = "Routing number: {$prov['ddroutingnumber']}  Account: {$prov['ddaccountnumber']}  Type: {$prov['ddaccounttype']}";
}

$standardRates = getStandardRates();
$rates = getProviderRates($provid);

$payInfo = $dump;



// ####################################
echo '<head><link rel="stylesheet" href="https://leashtime.com/style.css" type="text/css" />';
echo "<style>body {background:none;font-size:0.9em;padding:20px;}
.inactive {color: grey;font-style: italic;}</style></head>";
echo "<h2>$provname$status</h2>";
echo "<h3>Basic Info</h3>";
foreach($basicInfo as $k => $v) {
	$v = is_array($v) ? join(', ', $v) : $v;
	$v = trim($v);
	$multiline = strpos($v, "\n") ? "<br>" : '';
	$v = str_replace("\n", "<br>", str_replace("\n\n", "<p>", $v));
	echo "<u>{$basicLabels[$k]}:</u>$multiline $v<p>";
}

echo "<h3>Employment Info</h3>";
foreach($employmentInfo as $k => $v) {
	$v = is_array($v) ? join(', ', $v) : $v;
	$v = trim($v);
	$multiline = strpos($v, "\n") ? "<br>" : '';
	$v = str_replace("\n", "<br>", str_replace("\n\n", "<p>", $v));
	echo "<u>{$empLabels[$k]}:</u>$multiline $v<p>";
}

echo "<h3>Pay</h3>";
foreach($payInfo as $k => $v) {
	$v = is_array($v) ? join(', ', $v) : $v;
	$v = trim($v);
	$multiline = strpos($v, "\n") ? "<br>" : '';
	$v = str_replace("\n", "<br>", str_replace("\n\n", "<p>", $v));
	echo "<u>{$empLabels[$k]}:</u>$multiline $v<p>";
}
//print_r($employmentInfo);
$showAllServiceTypes = $_REQUEST['allservicetypes'];
//$showAllServiceTypes = !mattOnlyTEST();
if(!$showAllServiceTypes)
	$assignedServices = fetchCol0("SELECT DISTINCT servicecode FROM tblappointment WHERE canceled IS NOT NULL AND providerptr = $provid");

$toggle = $showAllServiceTypes
	? "All service types shown.<p>Show only service types of <a href='provider-snapshot.php?id=$provid'>uncanceled assigned visits</a>."
	: "Service types of uncanceled assigned visits only.<p>Show <a href='provider-snapshot.php?id=$provid&allservicetypes=1'>ALL service types</a>.";

//print_r($rates);
echo "$toggle<p>";
echo "<table style='Zwidth: 80%;' border=1 bordercolor=gray><tr><td colspan=3 align=center><b>Service Rates</td>$historyLink</tr>\n";
echo "<tr><th>&nbsp;</th><th>Standard Rate</th><th>Rate</th></tr>\n";
foreach($standardRates as $key => $service) {
	if(!$showAllServiceTypes && !in_array($key, $assignedServices)) continue;
	$class = $service['active'] ? '' : 'class="inactive"';
	$stndRate = !$service['defaultrate'] ? '' : ($service['ispercentage'] ? $service['defaultrate'].'%' : dollarAmount($service['defaultrate']));
	$ispercentage = isset($rates[$key]) ? $rates[$key]['ispercentage'] : $service['ispercentage'];
	$rate =!isset($rates[$key]) ? '' : ($ispercentage ? $rates[$key]['rate'].'%' : dollarAmount($rates[$key]['rate']));
	echo "<tr><td $class>{$service['label']}</td><td $class>$stndRate</td><td $class>$rate</td></tr>\n";
}
echo "</table>\n";
