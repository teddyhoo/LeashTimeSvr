<? //monthly-service-change-reconciliation.php
/*This page is included after a monthly contract is saved if:
	- Total price has changed 
	and/or 
	- the effectivedate (or startdate) is not day 1 of next month
	
  This page will show old package price (if any) and new package price
	This page will show startdate or effective date
	
	changeStart = effectivedate ? effectivedate : startdate
	
	changeStartDayOfMonth = date('j', strtotime(changeStart))
	
	If effectivedate && startdate < effectivedate, 
		if startdate <= today
			it will show the number of days between today and the effective date
		else it will show the number of days between start date and the effective date
		
	else (If no effective date, && startdate < today), 
		show the number of days between today and the start of next month
		
	offer a [create a credit to this customer's account] button that opens a pop-up
	offer a [create a charge to this customer's account] button that opens a pop-up
	
*/
function dollars($amount) {
	$amount = $amount ? $amount : 0;
	return dollarAmount($amount, $cents=true, $nullRepresentation='', $nbsp=' ');
}

$iStartdate = strtotime($startdate);
$iToday = strtotime(date('Y-m-d'));
$iEffectivedate = max(strtotime($effectivedate), $iToday);
$iStartOfNextWholeMonthDate = date('j', $iEffectivedate) == 1 ? $iEffectivedate : strtotime("+1 month", strtotime(date('Y-m-1')));
$changeStart = $effectivedate ? $effectivedate : $startdate;

$changeStartDayOfMonth = date('j', strtotime($changeStart));

$startDateIsPast = $iStartdate <= $iToday;
$effectiveDateAfterStart = $effectivedate && $iStartdat < $iEffectivedate;
$day = 60 * 60 * 24;
echo "<span style='font-size:1.1em;'>";
if($effectivedate) echo "\nThese changes are effective ".longerDayAndDate($iEffectivedate).".\n<p>\n";
echo "\nThis package's start date is ".longerDayAndDate(strtotime($startdate)).".\n<p>";

$partialMonth = false;

$thisPackageId = isset($packageid) && $packageid ? $packageid : $newPackageId;

$tbdProportion = proportionToBeChargedFirstMonth($thisPackageId, $changeStart);

if($effectiveDateAfterStart) {
	$partialMonth = true;

	if($startDateIsPast)
		echo "There are ".(($iStartOfNextWholeMonthDate-$iEffectivedate) / $day)." days between the effective date and the start of the next whole month.";
	else echo "There are ".(($iStartdate-$iToday) / $day)." days between the start date the effective date.";
}
else {
	if($startDateIsPast) {
		echo "There are ".(date('t')-date('n')+1)." days from today to the start of next month.";
		$partialMonth = true;
	}
	else if(date('j',$iStartdate) > 1) {
		$partialMonth = true;
		echo "In the first month of service there will be ".(date('j',$iStartdate)-1)." days before the start of service.";
	}
}
echo "<p>";
$priceChanged = false;
if(isset($oldtotalprice)  && ($oldtotalprice != $totalprice)) {
	$priceChanged = true;
	echo "This contract's package price has changed from ".dollars($oldtotalprice)." to ".dollars($totalprice).".";
}
else echo "This contract's package price ".(isset($oldtotalprice) ? 'remains ' : 'is ').dollars($totalprice).".";
echo "\n<p>\n";
if($priceChanged || $partialMonth) {
	
	echo "Services representing only ".sprintf('%d', round($tbdProportion*100))."% of the package price will be performed in the first month.<p>";
	
	echo "Their estimated value is ".dollarAmount($tbdProportion*$totalprice)
				.", a difference from the monthly charge of ".dollarAmount($totalprice - $tbdProportion*$totalprice)."<p>";
	
	echo "This change in billing will be reflected in the next month's bill.  ";

	echo "\n<p><hr>\n";
	echo "If you like, you may:\n<p>";

	$clientDetails = getOneClientsDetails($client);
	echo "Create a ".echoButton('', 'Credit', "openCreditWindow($client)", null, null, true)." to {$clientDetails['clientname']}'s account.\n<p>\nor\n<p>\n";
	echo "Create a ".echoButton('', 'Charge', "openChargeWindow($client)", null, null, true)." against {$clientDetails['clientname']}'s account.\n<p>\nor\n<p>\n";
}
echo echoButton('', 'Continue', "editClient($client)", null, null, true)." without making any adjustments.\n";
echo "<hr></span>";
?>
<script language='javascript'>

function editClient() {
	document.location.href='client-edit.php?id=<?= $client ?>&tab=services';
}

function openCreditWindow(client, payment) {
	var url = payment ? 'payment-edit.php?' : 'credit-edit.php?reason=Adjustment+to+Monthly+Contract&';
	openConsoleWindow('editcredit', url+'client='+client, 600, 220);
}

function openChargeWindow(client) {
	var url = 'charge-edit.php?reason=Adjustment+to+Monthly+Contract&';
	openConsoleWindow('editcredit', url+'client='+client, 600, 220);
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}
</script>
		
<?

function proportionToBeChargedFirstMonth($packageid, $startDate) {
	$startDate = max(strtotime($startDate), time());;
	
	$dows = explodePairsLine('Mon|M||Tue|Tu||Wed|W||Thu|Th||Fri|F||Sat|Sa||Sun|Su');
	$services = getPackageServices($packageid);
	foreach($services as $service) {
		$days = $service['daysofweek'];
		if($days == 'Weekends') $days = 'Sa,Su';
		else if($days == 'Weekdays') $days = 'M,Tu,W,Th,F';
		else if($days == 'Every Day') $days = 'M,Tu,W,Th,F,Sa,Su';
		foreach(explode(',',$days) as $d)
			$monthServices[$d][] = $service;
	}
	$startDayOfMonth = date('j', $startDate);
	for($day = 1; $day < date('t', $startDate); $day++) {
		$date = date("Y-m-".sprintf('%02d', $day));
		$dow = $dows[date('D', strtotime($date))];
		$dayValue = 0;
		if($monthServices[$dow]) foreach($monthServices[$dow] as $service) 
			$dayValue += $service['charge'] + $service['adjustment'];
		$monthValue += $dayValue;
		if($day >= $startDayOfMonth)
			$tbdValue += $dayValue;
	}
	
	return $monthValue ? $tbdValue / $monthValue : $monthValue;
}