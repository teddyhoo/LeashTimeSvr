<? // visits-detail-viewer.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "invoice-gui-fns.php";
require_once "invoice-fns.php";
require_once "client-fns.php";
require_once "provider-fns.php";
require_once "discount-fns.php";
require_once "service-fns.php";
require_once "tax-fns.php";
require_once "appointment-fns.php";
require_once "js-gui-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
locked('o-');
extract(extractVars('id,start,end,action,sort', $_REQUEST));
$start = $start ? date('Y-m-d', strtotime($start)) : '';
$end = $end ? date('Y-m-d', strtotime($end)) : '';

if(!$start && !$end) {
	$start = date('Y-m-d', strtotime("-30 days"));
	$end = date('Y-m-d', strtotime("today"));
}

$client = getOneClientsDetails($id);

if($action == 'markincomplete') {
	$mods = withModificationFields(array('completed'=>null, 'canceled'=>null));
	foreach($_POST as $k => $v) {
		if(strpos($k, 'appt_') === 0)
			$ids[] = substr($k, strlen('appt_'));
	}
  if($ids) {
		updateTable('tblappointment', $mods, "appointmentid IN (".join(',', $ids).")", 1);
  	//if($_SESSION['surchargesenabled']) markAppointmentSurchargesComplete($ids);
	}
	if($ids) foreach(explode(',', $ids) as $id)
		logAppointmentStatusChange(array('appointmentid'=>$id, 'completed' => null), count($ids)." visits marked incomplete - visits details viewer.");

}
else if($action == 'change') {
	foreach($_POST as $k => $v) {
		if(in_array($k, array('charge', 'adjustment', 'bonus', 'rate', 'servicecode', 'providerptr')))
			$mods[$k] = $v;
		else if(strpos($k, 'appt_') === 0)
			$apptids[] = substr($k, strlen('appt_'));
	}
	if($mods['providerptr'] == -1) $mods['providerptr'] = 0;
	else if(!$mods['providerptr']) unset($mods['providerptr']);
	if(!$mods['servicecode']) unset($mods['servicecode']);
	$discount = $_POST['discountplus'];
	if($discount && $discount != -1) {
		$discount = explode('_', $discount);
		$discount = $discount[0];
	}
	if($apptids) {
		$allServiceNames = getAllServiceNamesById();
		foreach($apptids as $apptid) {
			$oldAppt = getAppointment($apptid);
			if($mods['providerptr']) {
				if(!isset($timesOff[$mods['providerptr']])) 
					$timesOff[$mods['providerptr']] = getProviderTimeOff($mods['providerptr']);
//echo "timesOff: ".print_r($timesOff[$mods['providerptr']], 1)."<p>providerIsOff({$mods['providerptr']}, {$oldAppt['date']}, {$oldAppt['timeofday']}, {$timesOff[$mods['providerptr']]}): ".providerIsOff($mods['providerptr'], $oldAppt['date'], $oldAppt['timeofday'], $timesOff[$mods['providerptr']]);
				if($timesOff[$mods['providerptr']] && providerIsOff($mods['providerptr'], $oldAppt['date'], $oldAppt['timeofday'], $timesOff[$mods['providerptr']])) {
					$mods['providerptr'] = '0';
					$failures[] = "{$oldAppt['date']}...{$oldAppt['timeofday']}...{$allServiceNames[$oldAppt['servicecode']]}";
				}
			}
//print_r($mods);			
			$appt = array_merge($oldAppt, $mods);
			updateTable('tblappointment', withModificationFields($mods), "appointmentid=$apptid", 1);
			$note = array('Page', 'visits-detail');
			foreach($mods as $k => $mod) {
				$note[] = $k;
				$note[] = $oldAppt[$k];
				$note[] = $mod;
			}
			if($discount) $note['discount'] = $discount;
			$note = join('|', $note);
			logChange($apptid, 'tblappointment', 'm', $note);
			
			$apptDiscount = handleDiscount($discount, $appt, $oldAppt);
			
			$testBillable = createApptBillableObject($appt, $apptDiscount['amount']);
			$oldBillable = fetchFirstAssoc(
				"SELECT *
					FROM tblbillable
					WHERE itemptr = '$apptid' AND itemtable = 'tblappointment' AND superseded = 0 LIMIT 1");
			if($oldBillable['charge'] != $testBillable['charge']) {
				recreateAppointmentBillable($apptid);
			}
		}
		$_SESSION['frame_message'] = count($apptids).' visit'.(count($apptids) == 1 ? '' : 's').' modified.';
	}
	if($failures) {
		$pname = fetchRow0Col0("SELECT IFNULL(nickname, CONCAT_WS(' ', fname, lname)) FROM tblprovider WHERE providerid = {$_POST['providerptr']} LIMIT 1");
		$failures = "<span class='pagenote' style='font-size:1.2em'>$pname is unavailable for the following visits:<ul><li>"
																.join('<li>', $failures).'</ul></span>';
	}
}

function handleDiscount($discount, $appt, $oldAppt) {  // -1 = no discount, null = no change of discount type
	global $scheduleDiscount;
	$appointmentid = $appt['appointmentid'];
	if($discount == -1)
		setAppointmentDiscounts(array($appointmentid), !'on', 'force');
	else {
		$currentDiscount = getAppointmentDiscount($appointmentid);
		$currentDiscount = $currentDiscount ? $currentDiscount['discountptr'] : null;
		if(!$discount) $discount = $currentDiscount;
//echo $memberid;exit;		
//echo "discount: [$discount] currentDiscount: [$currentDiscount]<p>";
		if($discount == $currentDiscount &&
				($oldAppt['charge']+$oldAppt['adjustment'] != $appt['charge']+$appt['adjustment'])) {
//echo "old: ".($oldAppt['charge']+$oldAppt['adjustment'])." new: ".($appt['charge']+$appt['adjustment'])			.'<p>';		
			resetAppointmentDiscountValue($appointmentid, $appt['charge']+$appt['adjustment']);
		}
		else if($discount && $discount != $currentDiscount) {
//$error = "New discount: $discount	Old: $currentDiscount	";
			$scheduleDiscount = 
				array('clientptr'=>$clientptr, 'discountptr'=>$discount, 'start'=>date('Y-m-d'), 'memberid'=>$memberid);
			$numDiscountedAppts = applyScheduleDiscountWhereNecessary((string)$appointmentid);
			if($numDiscountedAppts == 0) $error = "Your changes were saved, but that discount could not be applied.";
		}
	}
	return getAppointmentDiscount($appointmentid);
}

$windowTitle = "{$client['clientname']}'s Visits";
if($start) $windowTitle .= " from: ".shortDate(strtotime($start));
if($end) $windowTitle .= " to: ".shortDate(strtotime($end));
//$extraBodyStyle = 'padding:10px;background:white;';
require "frame-bannerless.php";
echo $failures;
echo "<h2>$windowTitle</h2>";
echo "<form name='reportform' method='POST'>";
hiddenElement('action', '');
calendarSet('Starting:', 'start', $start, null, null, true, 'end');
echo "&nbsp;";
calendarSet('end:', 'end', $end);
echo "&nbsp;";
echoButton('', 'Show', 'showVisits()');
echo '   ';
fauxLink('Select All', 'selectAll(1)');
echo ' - ';
fauxLink('Deselect All', 'selectAll(0)');
if(staffOnlyTEST()) {
	echo " ";
	echoButton('', 'Mark Incomplete', 'markIncomplete()');
}



echo "<p>";

createDetailView($start, $end, $client['clientid']);
echo "</form>";

function createDetailView($start, $end, $client) {
	global $windowTitle, $db, $sort;
	$sort = $sort ? $sort : 'date_asc';
	$sort = explode('_', $sort);
	$dir = count($sort) == 2 ? $sort[1] : 'ASC';
	$sort = $sort[0];
	if($sort == 'date') $sort = "date $dir, starttime $dir";
	if($sort == 'service') $sort = "service $dir, date ASC, starttime ASC";
	if($sort == 'provider') $sort = "sitterSortName $dir, date ASC, starttime ASC";
	
	$where[] = "tblappointment.clientptr = $client AND canceled IS NULL";
	if($start) $where[] = "date >= '$start'";
	if($end) $where[] = "date <= '$end'";
	$where = join(' AND ', $where);
	
	$sql = "SELECT tblappointment.*, tblappointment.charge as charge0, billableid, tblbillable.clientptr, itemptr, itemtable, monthyear, itemdate,
					billabledate, label as service, IFNULL(nickname, CONCAT_WS(' ', tblprovider.lname, tblprovider.lname)) as sitterSortName,
					relinvoiceitem.charge as owed, tblbillable.tax, relinvoiceitem.prepaidamount as paid, tblbillable.charge as billcharge, tblbillable.paid as billpaid, superseded
		 FROM tblappointment 
		 LEFT JOIN tblbillable ON itemptr = appointmentid AND itemtable = 'tblappointment'
		 LEFT JOIN relinvoiceitem ON billableptr = billableid
		 LEFT JOIN tblservicetype ON servicetypeid = servicecode
		 LEFT JOIN tblprovider ON providerid = providerptr
		 WHERE $where
		 ORDER BY $sort, superseded DESC";
//echo "$sql<p>";
	$billables = fetchAssociationsKeyedBy($sql, 'appointmentid');
	$provs = getProviderShortNames();
	$discountTypes = fetchAssociationsKeyedBy('SELECT * FROM tbldiscount WHERE active = 1', 'discountid');
	$totals = array();
	
	$rows[] = array('#CUSTOM_ROW#'=>
		'<tr><td colspan=6 align=right>'
		.'Svc:'.serviceSelectElement().' Sitter:'.providerSelectElement($client)
		.'</td>'
		.'<td><input size=4 id="rate" name="rate"></td><td><input size=4 id="bonus" name="bonus"></td>'
		.'<td><input size=4 id="charge" name="charge"></td><td><input size=4 id="adjustment" name="adjustment"></td>'
		.'<td>'
		.discountSelectElement()
		.'</td><td colspan=2 class="futuretaskEVEN">'.echoButton('', 'Set Values', 'setValues()', '','',1).'</td></tr>');
		
	/*
	$rows[] = array('#CUSTOM_ROW#'=>
		'<tr><td colspan=6 align=right>'.echoButton('', 'Set Values', 'setValues()', '','',1).' in all selected visits to:'
		.'</td>'
		.'<td><input size=4 id="rate" name="rate"></td><td><input size=4 id="bonus" name="bonus"></td>'
		.'<td><input size=4 id="charge" name="charge"></td><td><input size=4 id="adjustment" name="adjustment"></td>'
		.'<td>'
		.'<input size=4 id="discount" name="discount" disabled>'
		.'</td><td colspan=2 class="futuretaskEVEN"></td></tr>');
	*/
		
	hiddenElement('memberidrequired', '');
	$count = 0;
	$rowClasses[] = null;  // controls row class
	foreach($billables as $b) {
		$count++;
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') echo print_r($b,1).'<br>';
		if($b['superseded']) {
			$b['billcharge'] = null;
			$b['billpaid'] = null;
			$b['tax'] = null;
		}
		$row = $b;
		$cbid = "appt_{$b['appointmentid']}";
		if($b['billableptr'] && $b['billpaid'] > 0) $checkbox = '<div class="center" title="Already invoiced or partially paid.">X</div>';
		else $checkbox = "<input type='checkbox' name='$cbid' id='$cbid'>";
		$row['cb'] = $checkbox;
		$row['date'] = shortDate(strtotime($row['date']));
		$row['service'] = serviceLink($row);
		$row['provider'] = $provs[$row['providerptr']];
		$totals['charge0'] += $row['charge0'];
		$totals['rate'] += $row['rate'];
		$totals['bonus'] += $row['bonus'];
		$totals['adjustment'] += $row['adjustment'];
		$row['tod'] = $row['timeofday'];
		$row['recurring'] = $row['recurringpackage'] ? 'R' : '';
		$discount = fetchFirstAssoc("SELECT * FROM relapptdiscount WHERE appointmentptr = {$row['appointmentid']} LIMIT 1");
		if($discount) {
			$type = $discountTypes[$discount['discountptr']];
			$type = "{$type['label']} (".($type['ispercentage'] ? "{$type['amount']}%" : dollarAmount($type['amount'])).")";
			$row['discount'] = "<span style='text-decoration:underline;' title='$type'>{$discount['amount']}</span>";
			$totals['discount'] += $discount['amount'];
		}
		$totals['tax'] += $row['tax'];
		if(!$row['billcharge']) {
			$c = $row['charge0'] + $row['adjustment'] - $discount['amount'] + $row['tax'];
			$c = $c ? sprintf('%.2f', $c) : '';
			$row['billcharge'] = "<i>$c</i>";
		}
		$totals['billcharge'] += $row['billcharge'];
		$rows[] = $row;
		$rowClass = 'futuretask';
		if(staffOnlyTEST()) {
			if($row['canceled']) $rowClass = 'canceledtask';
			else if($row['completed']) $rowClass = 'completedtask';
			else if(appointmentFuturity($row) == -1) {
				if(!$row['completed']) $rowClass = 'noncompletedtask';
			}
			if($rowClass != 'futuretask' && !($count & 1)) $rowClass = $rowClass.'EVEN';
			}
		}
		$rowClasses[] = $rowClass;
	}
	foreach($totals as $k => $v) $totals[$k] = $v ? dollarAmount($v) : '';
	$rows[] = $totals;
//print_r($rows);	
	$columns = explodePairsLine('cb| ||date|Date||tod|Time of Day||recurring| ||service|Service||provider|Sitter||rate|Rate||bonus|Bonus||charge0|Base Charge||adjustment|Adj||discount|Discount||tax|Tax||billcharge|Final');
	$colClasses = array('charge'=>'dollaramountcell', 'provider'=>'futuretaskEVEN', 'rate'=>'futuretaskEVEN', 'bonus'=>'futuretaskEVEN');
	$columnSorts = explodePairsLine('date| ||service| ||provider| ');
	$visitCount = count($billables) ? count($billables) : '0';
	
	echo '<style>.center {text-align:center;font-weight:bold;}</style>';
	echo "Canceled visits are omitted.   Visits shown: $visitCount<p>";
	tableFrom($columns, $rows, 'WIDTH=100% border=1 class="futuretask"',null,$headerClasses,null,null,$columnSorts,$rowClasses, $colClasses, $sortClickAction=null);
//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
	
}

function serviceSelectElement() {
	$serviceSelections = array_merge(array('--No Change--'=>''), getServiceSelections());
	ob_start();
	ob_implicit_flush(0);
	selectElement('', "servicecode", '', $serviceSelections, 'updateAppointmentVals(this)');
	$el = ob_get_contents();
	//echo 'XXX: '.ob_get_contents();exit;
	ob_end_clean();
	return $el;
}
	

function discountSelectElement() {
	foreach(getDiscounts($activeOnly=true, $sort='label') as $discount)
		$discountOptions[$discount['label']] = $discount['discountid'].'_'.$discount['memberidrequired'];
	$discountOptions = array_merge(array('--No Change--'=>'', '--No Discount--'=>-1), (array)$discountOptions);
	ob_start();
	ob_implicit_flush(0);
	selectElement('', "discountplus", '', $discountOptions, 'discountSelected(this)');
	$el = ob_get_contents();
	//echo 'XXX: '.ob_get_contents();exit;
	ob_end_clean();
	return $el;
}
	
function providerSelectElement($clientid) {
	ob_start();
	ob_implicit_flush(0);
	$options = array_merge(array('--Unassigned--'=>-1), availableProviderSelectElementOptions($client, $date, '--No Change--'));
	selectElement('', 'providerptr', $choice, $options, $onchange);
	//availableProviderSelectElement($clientid, $date=null, "providerFor_$clientid", '--Unassigned--', $choice=null, "setProviderForClient(this, $clientid)");
	$element = ob_get_contents();
	ob_end_clean();
	return $element;
}
	
function serviceLink($row) {
	static $providerNames;
	if(!$providerNames) $providerNames = getProviderCompositeNames();
	$petsTitle = $row['pets'] 
	  ? htmlentities("Pets: {$row['pets']}", ENT_QUOTES)
	  : "No Pets specified.";
	$targetPage = 'appointment-view.php';
	$label = $row['custom'] ? '<b>(M)</b> ' : '';
	$label .= $_SESSION['servicenames'][$row['servicecode']];
	$sitter = $providerNames[$row['providerptr']] ? $providerNames[$row['providerptr']] : 'Unassigned';
	return "<a href='javascript:openConsoleWindow(\"editappt\", \"$targetPage?id={$row['appointmentid']}\",530,450)' 
	       title='Sitter: $sitter'
	       >$label</a>"; //title='$petsTitle'
}

function chargeLink($row, $billableptr) {
	
	return $row['comptype'] == 'adhoc' ? 'Adhoc payment' : 'Gratuity';
	
	$myTitle = $row['descr'] 
	  ? htmlentities("Pets: {$row['descr']}", ENT_QUOTES)
	  : "No note specified.";
	$targetPage = "provider-adhoc-payment-payable.php?payableptr=$payableptr";
	$label = $row['comptype'] == 'adhoc' ? 'Adhoc payment' : 'Gratuity';
	return "<a href=# 
	       onClick='openConsoleWindow(\"editappt\", \"$targetPage\",530,450)' 
	       title='$myTitle'>$label</a>";
}

?>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('start','Starting Date','end','Ending Date', 'charge', 'Charge', 'rate', 'Rate', 'adjustment', 'Adjustment', 'bonus', 'Bonus');

function markIncomplete() {
	var noselections = 'Please select at least one visit first.';
	var appts = document.getElementsByTagName('input');
	for(var i=0; i<appts.length; i++)
		if(appts[i].name && appts[i].name.indexOf('appt_') == 0 && appts[i].checked)
			noselections = null;
	if(MM_validateForm(
			noselections, '', 'MESSAGE')) {
		document.getElementById('action').value = 'markincomplete';
		document.reportform.submit();
	}
}

function setValues() {
	var noselections = 'Please select at least one visit first.';
	var appts = document.getElementsByTagName('input');
	for(var i=0; i<appts.length; i++)
		if(appts[i].name && appts[i].name.indexOf('appt_') == 0 && appts[i].checked)
			noselections = null;
	if(MM_validateForm(
			noselections, '', 'MESSAGE',
			'start', '', 'R',
			'end', '', 'R',
			'start', '', 'isDate',
			'end', '', 'isDate',
			'rate', '', 'R',
			'rate', '', 'UNSIGNEDFLOAT',
			'charge', '', 'R',
			'charge', '', 'UNSIGNEDFLOAT',
			'bonus', '', 'UNSIGNEDFLOAT',
			'adjustment', '', 'UNSIGNEDFLOAT'
			)) {
		document.getElementById('action').value = 'change';
		document.reportform.submit();
	}
}

function showVisits() {
	if(MM_validateForm(
			'start', '', 'R',
			'end', '', 'R',
			'start', '', 'isDate',
			'end', '', 'isDate')) document.reportform.submit();
}


var clientCharges = <?= getClientChargesJSArray($id) ?>;
var standardRates = <?= getStandardRateDollarsJSArray() ?>;
var providerRates = <?= getAllActiveProviderRateDollarsJSArray() ?>;
var standardCharges = <?= getStandardChargeDollarsJSArray() ?>;

function update(a, b) { // is not passed info to identify particular visit
	<? if(mattOnlyTEST()) { // problem: empty appt editor window covers the alert box ?>
	if(confirm('Refresh window?')) {
		var start = document.getElementById('start').value;
		var end = document.getElementById('end').value;
		document.location.href='visits-detail-viewer.php?id=<?= $id ?>&start='+start+'&end='+end+'&sort=<?= $sort ?>';
	}
	<? } ?>
}


function updateAppointmentVals() {
	var service = document.getElementById('servicecode').value;
	var client = <?= $id ?>;
	if(service == 0) {
		document.getElementById('rate').value = '';
		document.getElementById('charge').value = '';
	}
	else {
		// look up rate and charge
		var charge = lookUpClientServiceCharge(service, client);
		var rate = lookUpServiceRate(service, standardRates);
		if(rate[1]) rate = charge * rate[0] / 100;
		document.getElementById('rate').value = parseFloat(rate).toFixed(2);
		document.getElementById('charge').value = parseFloat(charge).toFixed(2);
	}
}

function discountSelected(el) {
	var val = el.options[el.selectedIndex].value;
	if(val && val != -1) {
		val = val.split('_');
		if(val[1] == 0) document.getElementById('memberidrequired').value = '';
		else {
			var memberid = prompt("Please supply a member id");
			if(memberid) document.getElementById('memberidrequired').value = memberid;
			else {
				if(confirm('A member id is required.  Try Again?')) discountSelected(el);
				else {
					el.selectedIndex = 0;
				}
			}
		}
	}
}

function lookUpClientServiceCharge(service, client) {
	if(client) {
		var rate = lookUpServiceRate(service, clientCharges);
		if(rate != -1) return rate[0];
	}
	rate = lookUpServiceRate(service, standardCharges);
	return rate[0];
}

function lookUpServiceRate(service, rates) {  // return [value, ispercentage]
	for(var i=0;i<rates.length;i+=3)  // servicetype,value,ispercentage
	  if(rates[i] == service)
	    return [rates[i+1],rates[i+2]];
	return -1;
}

function selectAll(on) {
	var cbs = document.getElementsByTagName('input');
	for(var i=0;i<cbs.length;i++)
		if(cbs[i].type == 'checkbox' && !cbs[i].disabled)
			cbs[i].checked = on ? true : false;
	//updateSelectionCount();
}


<? dumpPopCalendarJS(); ?>
</script>
