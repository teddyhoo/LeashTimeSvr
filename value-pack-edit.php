<? // value-pack-edit.php
require_once "common/init_session.php";
include "common/init_db_petbiz.php";
include "gui-fns.php";
include "value-pack-fns.php";
locked('o-');

$clientptr = $_REQUEST['clientptr'];
$vpid = $_REQUEST['vpid'];

if($_GET['template']) {
	$template = getValuePack($_GET['template']);
	echo json_encode($template);
	exit;
}

function newValuepackEditor($clientptr) {
	// if no $vpid, allow standard vp chocie
	$standardOptions = array('Standard Value Packs'=>null);
	foreach(getStandardValuePacks(false	) as $pack) {
		$standardOptions[$pack['label']] = $pack['vpid'];
	}
	$pack = null;
	echo "<form name='standardpackeditor' method='POST'>";
	echo "<table>";
	echoButton('', "Save", "checkAndSubmit()");
	if($standardOptions) selectRow('Start with:', 'standardoptions', $value=null, $standardOptions, $onChange='pickStandardStart(this)');;
	inputRow('Value Pack: ', 'label', $pack['label'], $labelClass=null, $inputClass='input300');
	inputRow('Number of Tokens: ', 'visits', $pack['visits']);
	inputRow('Price: ', 'price', $pack['price']);
	inputRow('Refill notication: ', 'refill', $pack['refill']);
	inputRow('Expires after days: ', 'duration', $pack['duration']);
	textRow('Notes', 'notes', $pack['notes'], $rows=6, $cols=60);
	hiddenElement('clientptr', $clientptr);
	hiddenElement('save', '');
	echo "</table>";
	echo "</form>";
	$test = "decodeURIComponent('".$labels."')";
	$labels = $labels == null ? 'new Array()' : "JSON.parse(decodeURIComponent('".$labels."'.replace(/\+/g, ' ')))";
	echo <<<JS
	<script language='javascript' src='check-form.js'></script>
	<script language='javascript' src='ajax_fns.js'></script>
	<script language='javascript'>
	function pickStandardStart(el) {
		ajaxGetAndCallWith('?template='+el.options[el.selectedIndex].value, pickStandard, null);
	}
	function pickStandard(arg, json) {
		json = json ? JSON.parse(decodeURIComponent(json.replace(/\+/g, ' '))) : false;
		var fields = 'label,visits,price,refill,duration,notes'.split(',');
		for(var i=0; i<fields.length; i++) {
			document.getElementById(fields[i]).value = json ? json[fields[i]] : '';
		}
		//el.selectedIndex = 0;
	}
	function checkAndSubmit() {
		if(MM_validateForm('label', '', 'R',
												'visits', '', 'R',
												'visits', '', 'UNSIGNEDINT',
												'refill', '', 'UNSIGNEDINT',
												'price', '', 'R',
												'price', '', 'UNSIGNEDFLOAT',
												'duration', '', 'UNSIGNEDINT')) {
				document.getElementById('save').value = 1;
				document.standardpackeditor.submit();
			}
	}
</script>
JS;
}

function valuepackEditor($vpid) {
	require_once "js-gui-fns.php";
	// if no $vpid, allow standard vp chocie
	$pack = getValuePack($vpid);
	$visitsLeft = prepaidVisitsLeft($vpid);
	$packStatus = packageStatus($vpid);
	$fullyEditable = $visitsLeft == $pack['visits'] && $packStatus == 'unpaid';
	if($packStatus == 'unpaid')
		$deleteButton = echoButton('', 'Delete', "deletePack($vpid)", 'HotButton', 'HotButtonDown', 1, 'Delete this Value Pack');
	echo "<form name='standardpackeditor' method='POST'>";
	echo "<table><tr><td>";
	echoButton('', "Save", "checkAndSubmit()");
	
	
	$billable = fetchFirstAssoc(
				"SELECT * 
					FROM tblbillable 
					WHERE itemtable = 'tblvaluepack' AND itemptr = $vpid AND superseded = 0
					LIMIT 1", 1);
				
				
	echo "</td><td>$deleteButton {$billableId['billableid']}</td>";
	inputRow('Value Pack:', 'label', $pack['label'], $labelClass=null, $inputClass='input300');
	if($fullyEditable) {
		inputRow('Number of Tokens:', 'visits', $pack['visits']);
		inputRow('Price:', 'price', $pack['price']);
	}
	else {
		labelRow('Number of Tokens:', 'visitslabel', $pack['visits']);
		hiddenElement('visits', $pack['visits']);
		$tax = $billable['tax'] ? ' + '.dollarAmount($billable['tax']).' tax = '.dollarAmount($billable['charge']) : '';
		labelRow('Price:', 'pricelabel', dollarAmount($pack['price']).$tax);
		hiddenElement('price', $pack['price']);
	}
	inputRow('Refill notication: ', 'refill', $pack['refill']);
//echo "<tr><td>Status: $packStatus<td>".print_r($pack, 1);
	if($packStatus == 'unpaid') inputRow('Expires after days: ', 'duration', $pack['duration']);
	else calendarRow('Expires on:', 'expires', $pack['expires'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null, $timeAlso=null);
	
	checkboxRow('Reminder', 'reminder', $value=1, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null, $extraContent=' <i>Include in refill notifications.</i>');
	checkboxRow('Auto-bill', 'autoBill', $value=1, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null, $extraContent=' <i>Check this to indicate the client can be charged automatically in future.</i>');
	
	
	textRow('Notes', 'notes', $pack['notes'], $rows=6, $cols=60);
	hiddenElement('clientptr', $pack['clientptr']);
	hiddenElement('vpid', $pack['vpid']);
	hiddenElement('action', '');
	hiddenElement('save', '');
	echo "</table>";
	echo "</form>";
	$pack['status'] = $packStatus;
	$pack['visitsLeft'] = $visitsLeft;
	echoStatusTable($vpid, $pack);
	
	$expirationArgs = 
		$packStatus == 'Unpaid' 
		? "args[args.length] = 'duration'; args[args.length] = ''; 'UNSIGNEDINT';"
		: "args[args.length] = 'expiration'; args[args.length] = ''; args[args.length] = 'isDate';";
	$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');
	$visiteditor =  adequateRights('ea') || $roDispatcher ? 'appointment-edit.php' : 'appointment-view.php';
	$dims = $_SESSION['dims']['appointment-edit'];
	echo <<<JS
	<script language='javascript' src='check-form.js'></script>
	<script language='javascript' src='common.js'></script>
	<script language='javascript'>
	function checkAndSubmit() {
		if(checkForm()) {
				document.getElementById('save').value = 1;
				document.standardpackeditor.submit();
			}
	}
	
	function checkForm() {
		var args = new Array('label', '', 'R',
												'visits', '', 'R',
												'visits', '', 'UNSIGNEDINT',
												'refill', '', 'UNSIGNEDINT',
												'price', '', 'R',
												'price', '', 'UNSIGNEDFLOAT');
		$expirationArgs										
		return MM_validateFormArgs(args);
	}
	
	function requestPayment() {
		if(checkForm()) {
			document.getElementById('action').value='requestPayment';
			document.standardpackeditor.submit();
		}
	}
	
	function deletePack(id) {
		if(confirm('You are about to delete this Value Pack,  Continue?'))
			document.location.href='value-pack-edit.php?delete='+id;
	}
	function editVisit(id) {
		
		openConsoleWindow("editappt", "$visiteditor?id="+id, $dims);
	}
</script>
JS;
	if($packStatus != 'Unpaid') {
		echo "<script language='javascript' src='popcalendar.js'></script>";
		echo "<script language='javascript'>";
		dumpPopCalendarJS();
		echo "</script>";
	}
}


$saveThisValuePack = $_POST['save'] || $_POST['action'] == 'requestPayment';

if($_REQUEST['delete']) {
	deleteTable('tblvaluepack', "vpid={$_REQUEST['delete']}", 1);
	echo "<script language='javascript'>if(window.parent.updateValuePacks) window.parent.updateValuePacks();window.parent.$.fn.colorbox.close();</script>";
}
else if($saveThisValuePack) {
	setupValuePackTable();
	$fields = explode(',', 'vpid,clientptr,label,visits,price,refill,duration,expires,notes');
	foreach($fields as $f) 
		if(array_key_exists($f, $_POST))$pack[$f] = $_POST[$f];
	$pack['created'] = date('Y-m-d H:i:s');
	$pack['createdby'] = $_SESSION["auth_user_id"];
	//$pack['refill'] = $pack['refill'] ? $pack['refill'] : sqlVal;
	require_once "invoice-fns.php";
	if(!$pack['vpid']) {
		$vpid = insertTable('tblvaluepack', $pack, 1);
		createValuePackBillable($vpid); // checks first to avoid overwriting a pre-existing billable
		setValuepackExpirationIfPaid($vpid);
	}
	else {
		if($pack['expires']) $pack['expires'] = date('Y-m-d', strtotime($pack['expires']));
		if(!$pack['visits']) unset($pack['visits']);
		updateTable('tblvaluepack', $pack, "vpid={$pack['vpid']}", 1);
//echo print_r($pack,1).'<hr>';		
		createValuePackBillable($pack['vpid']); // checks first to avoid overwriting a pre-existing billable
		setValuepackExpirationIfPaid($pack['vpid']);
	}
	//$error = 1;
	if(!$error) 
		$closeEditor = true;
}

if($_POST['action'] == 'requestPayment') {
	echo "<script language='javascript' src='common.js'></script>";
	echo "<script language='javascript'>openConsoleWindow('requestpayment', 'valuepack-request-payment.php?id=$vpid',600,700);</script>";
}

if($closeEditor)
	echo "<script language='javascript'>if(window.parent.updateValuePacks) window.parent.updateValuePacks();window.parent.$.fn.colorbox.close();</script>";

include "frame-bannerless.php";
if($vpid) valuepackEditor($vpid);
else newValuepackEditor($clientptr);

function echoStatusTable($vpid, $pack) {
	$apptids = findMemberIds($vpid);
	if($apptids) $appts = fetchAssociations(
		"SELECT appointmentid, 
			date, timeofday, label as service, completed, canceled, 
			if(providerptr IS NULL, 'Unassigned', CONCAT_WS(' ', fname, lname)) as sitter
		 FROM tblappointment
		 LEFT JOIN tblservicetype ON servicetypeid = servicecode
		 LEFT join tblprovider ON providerid = providerptr
		 WHERE appointmentid IN (".join(',', $apptids).")
		 ORDER BY date, starttime", 1);
 			 
	if($pack['status']== 'unpaid') $stats[] = 'This Value Pack has not been paid for.';
	else if($pack['status'] == 'expired')  $stats[] = 'This Value Pack expired on '.shortDate(strtotime($pack['expires'])).'.';

	if($pack['visitsLeft'] == 0) $stats[] = 'All of the Value Pack visits have been applied.';
	else $stats[] = "There are {$pack['visitsLeft']} tokens left in this Value Pack.";
	
	echo join('<br>', $stats);
	
	if($pack['status']== 'unpaid') {
		echo "<p>";
		echoButton('', 'Charge Client &amp; Send Email', 'chargeAndSend()');
		echo "<img src='art/spacer.gif' width=20 height=1>";
		echoButton('', 'Send Email Requesting Payment', 'requestPayment()');
	}

	if(!$appts) echo "<p>None of this Value Pack's tokens have been applied.";
	else {
		echo "<p>This Value Pack has been applied to these visits:";
		foreach($appts as $i => $appt) {
			$appts[$i]['service'] = fauxLink($appt['service'], "editVisit({$appt['appointmentid']})", 1, 'Edit this visit');
			$appts[$i]['date'] = shortDate(strtotime($appt['date']));
			$appts[$i]['status'] = 
				$appt['canceled'] ? 'Canceled' : (
				$appt['completed'] ? 'Completed' :
				'Incomplete');
		}
		$columns = explodePairsLine('date|Date||timeofday| ||service|Service||status|Status||sitter|Sitter');
		tableFrom($columns, $appts, 'WIDTH=90%', null, null, null, null, $columnSorts, $rowClasses);
	}
	
}