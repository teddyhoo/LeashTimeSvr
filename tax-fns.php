<? // tax-fns.php

function figureTaxForSurcharge($surch) {
	if(noTaxBefore($surch['date'])) return 0;
	require_once "service-fns.php";
	$client = $surch['clientptr'];
	if($surch['servicecode']) {
		$clientTaxRates = getClientTaxRates($client);
		$taxRate = $clientTaxRates[$surch['servicecode']];
	}
	else {
		$taxRate = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'taxRate'", 1);
		$taxRate = $taxRate ? $taxRate : 0;
	}
	$tax = round($taxRate / 100 * $surch['charge'], 2);;
	return $tax ? $tax : '0.0';
}

function figureTaxForAppointment($appt, $R_or_N_orNull=null) {
	if(noTaxBefore($appt['date'])) return 0;
	require_once "service-fns.php";
	$package = getPackage($appt['packageptr'], $R_or_N_orNull);
	if($package['monthly']) return 0;  // handled as a package
	$client = $appt['clientptr'];
	$clientTaxRates = getClientTaxRates($client);
	$tax = round(($appt['charge']+$appt['adjustment']) * $clientTaxRates[$appt['servicecode']]) / 100;
	return $tax ? $tax : '0.0';
}

function figureTaxForAppointmentInSet($appt, &$clientTaxRates) {
	if(noTaxBefore($appt['date'])) return 0;
	static $packages;
	if(!isset($packages[$appt['packageptr']])) {
		require_once "service-fns.php";
		$packages[$appt['packageptr']] = getPackage($appt['packageptr'], null);
	}
	$package = $packages[$appt['packageptr']];

	if($package['monthly']) return 0;  // handled as a package
		
	$client = $appt['clientptr'];
	if(!isset($clientTaxRates[$client])) $clientTaxRates[$client] = getClientTaxRates($client);
	$rates = $clientTaxRates[$client];
	$tax = round(($appt['charge']+$appt['adjustment']) * $rates[$appt['servicecode']]) / 100;
	return $tax ? $tax : '0.0';
}

function addPretaxAndPaymentsTo($billable, &$target) {  // add pretax amount, amount of pretax amount paid, and amount of tax paid to target
	if(!$billable) $billable = array('charge'=>0, 'tax'=>0);
	if($billable['owed']) $billable['charge'] = $billable['owed']+$billable['paid'];  // remember, this is just a copy of billable
	$target['pretax'] = $billable['charge'] - $billable['tax'];
	$target['pretaxpaid'] = 0;
	$target['taxpaid'] = 0;
	if($billable['paid']) {  // paid is always <= charge
		$target['pretaxpaid'] = min($target['pretax'], $billable['paid']);  // pretax is paid off first
		$target['taxpaid'] = max(0, $billable['paid'] - $target['pretax']);  // tax is paid off last
	}
}
	


function figureTaxForBillables($billables, $itemize=false) {
	if(!$billables) return '0.0';
	$tax = 0;
	if($billables) 
		foreach($billables as $billable) {
			$adjustments = array();
			addPretaxAndPaymentsTo($billable, $adjustments);
			$tax += $billable['tax'] - $adjustments['taxpaid'];
		}
	return $tax;
}

function figureMonthlyRecurringPackageTax($client, $packageid, $charge, $clientTaxRates=null) {
	$clientTaxRates = $clientTaxRates ? $clientTaxRates : getClientTaxRates($client);
	$services = fetchAssociations("SELECT daysofweek, servicecode FROM tblservice WHERE packageptr = $packageid");
	$dayCounts = array();
	$specialDayCounts = array('Every Day'=>7, 'Weekdays'=>5, 'Weekends'=>2);
	foreach($services as $service) {
		$dow = $service['daysofweek'];
		$dayCounts[$service['servicecode']] +=
			$specialDayCounts[$dow] ? $specialDayCounts[$dow] : count(explode(',',$dow));
	}
	$totalVisits = array_sum($dayCounts);
	$tax = 0;
	foreach($dayCounts as $servicecode => $count)
		$tax += round($count / $totalVisits * $charge * $clientTaxRates[$servicecode]) / 100;
	return $tax;
}

function getClientServiceTaxRate($client, $servicecode=null) {
	global $scriptPrefs;
	static $serviceTypes;
	if(!$serviceTypes) $serviceTypes = fetchAssociationsKeyedBy("SELECT servicetypeid, label, taxable FROM tblservicetype ORDER BY menuorder", 'servicetypeid');
	$serviceType = $serviceTypes[$servicecode];
	
	$prefs = isset($_SESSION['preferences']) ? $_SESSION['preferences'] : $scriptPrefs;
	$rates = array();
	$baseRate = $prefs['taxRate'] ? $prefs['taxRate'] : 0;
	$clientRate = fetchRow0Col0("SELECT taxrate FROM relclientcharge WHERE taxrate > -1 AND clientptr = $client AND servicetypeptr = $servicecode");
	$clientRate = $clientRate 
		? $clientRate 
		: ($serviceType && $serviceType['taxable'] ? $baseRate : 0);		
	return $clientRate;
}

function getClientTaxRates($client) {
	global $scriptPrefs;
	static $serviceTypes;
	if(!$serviceTypes) $serviceTypes = fetchAssociationsKeyedBy("SELECT servicetypeid, label, taxable FROM tblservicetype ORDER BY menuorder", 'servicetypeid');
	
	$prefs = isset($_SESSION['preferences']) ? $_SESSION['preferences'] : $scriptPrefs;
	$rates = array();
	$baseRate = $prefs['taxRate'] ? $prefs['taxRate'] : 0;
	$clientRates = fetchKeyValuePairs("SELECT servicetypeptr, taxrate FROM relclientcharge WHERE taxrate > -1 AND clientptr = $client");
	foreach($serviceTypes as $servicecode => $serviceType)
		$rates[$servicecode] = isset($clientRates[$servicecode]) 
			? $clientRates[$servicecode]
			: ($serviceType['taxable'] ? $baseRate : 0);		
	return $rates;
}

function noTaxBefore($date) {
//if(!mattOnlyTEST()) return false;
	global $scriptPrefs;
	$prefs = isset($_SESSION['preferences']) ? $_SESSION['preferences'] : $scriptPrefs;
	if(!$prefs['taxationStartDate']) return false;
	return strtotime($date) < strtotime($prefs['taxationStartDate']);
}