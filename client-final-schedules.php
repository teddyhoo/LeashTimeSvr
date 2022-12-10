<?
// client-final-schedules.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require "provider-fns.php";
require_once "service-fns.php";
require_once "gui-fns.php";

$locked = locked('o-');

$clientptr = $_REQUEST['id']; // 47 1623
if($clientptr)
	$clientname = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = $clientptr");
$packdetail = $_REQUEST['packdetail']; // 47 1623

if($packdetail) {
	require "frame-bannerless.php";
	function nrPackageCalendar($packageid) {
		require_once "appointment-calendar-fns.php";
		require_once "service-fns.php";
		require_once "client-fns.php";
		require_once "preference-fns.php";

		$_SESSION['servicenames'] = getAllServiceNamesById($refresh=1, $noInactiveLabel=true);
		getAllServiceNamesById($refresh=1, $noInactiveLabel=false);
		$package = getPackage($packageid);
		$clientid = $package['clientptr'];
		$history = findPackageIdHistory($packageid, $clientid, false);

		$history[] = $packageid;
		$history = join(',', $history);
		//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($package); exit;}
		$recurring = array_key_exists('monthly', $package);
		if($recurring) {
			$start = strtotime($package['effectivedate'] ? $package['effectivedate'] : $package['startdate']);
			$start = max($start, strtotime(date('Y-m-d')));
			$end = date('Y-m-d', strtotime($package['cancellationdate'] ? $package['cancellationdate'] : date('Y-m-d', $start+(60 * 60 * 24 * 14))));
			$start = date('Y-m-d', $start);
			$appts = fetchAssociations(
				"SELECT * 
					FROM tblappointment 
					WHERE canceled IS NULL
					AND packageptr IN ($history) 
					AND date >= '$start' AND date <= '$end'
					ORDER BY date, starttime");
			$surcharges = fetchAssociations(
				"SELECT s.*, a.servicecode
					FROM tblsurcharge s
					LEFT JOIN tblappointment a ON appointmentid = appointmentptr
					WHERE s.canceled IS NULL
					AND s.packageptr IN ($history) 
					AND s.date >= '$start' AND s.date <= '$end'
					ORDER BY s.date, s.starttime");
			if(($package['monthly'])) $priceInformation['services'] = $package['totalprice'];
			else {
				$priceInformation['services'] = calculateWeeklyCharge($package);
			}
		}
		else {
			$appts = fetchAssociations("SELECT * FROM tblappointment WHERE packageptr IN ($history) ORDER BY date, starttime");
			$surcharges = fetchAssociations(
			//"SELECT * FROM tblsurcharge WHERE canceled IS NULL AND packageptr IN ($history) ORDER BY date, starttime");
			"SELECT s.*, a.servicecode
				FROM tblsurcharge s
				LEFT JOIN tblappointment a ON appointmentid = appointmentptr
				WHERE s.canceled IS NULL
				AND s.packageptr IN ($history)
				ORDER BY s.date, s.starttime");
		}

		ob_start();
		ob_implicit_flush(0);
		dumpCalendarLooks(100, 'lightblue');

		if($appts) {
			require_once "tax-fns.php";
			foreach($appts as $appt) {
				if(!$appt['canceled'] && !$recurring) {
					$priceInformation['services'] += $appt['charge'] + $appt['adjustment'];
					$priceInformation['tax'] += figureTaxForAppointment($appt, ($recurring ? 'R' : 'N'));;
				}
				$apptIds[] = $appt['appointmentid'];
			}
			$priceInformation['discounts'] = fetchRow0Col0("SELECT sum(amount) FROM relapptdiscount WHERE appointmentptr IN (".join(",", $apptIds).')');
		}
		if($surcharges) {
			foreach($surcharges as $surcharge) {
				$surchargeids[] = $surcharge['surchargeid'];
				$priceInformation['surcharges'] += $surcharge['charge'];
				$stax = round($taxRate / 100 * $surcharge['charge'], 2);
		if(TRUE || mattOnlyTEST()) $stax = figureTaxForSurcharge($surcharge);
				$priceInformation['tax'] += $stax;
			}

			$surchargeLabels = fetchCol0("SELECT distinct label 
																		FROM tblsurcharge 
																		LEFT JOIN tblsurchargetype ON surchargetypeid = surchargecode
																		WHERE surchargeid IN (".join(',', $surchargeids).")");
			if($surchargeLabels) $surchargeLabels = " (".join(', ', $surchargeLabels).")";
		}
		$priceInformation['total'] = $priceInformation['services'] + $priceInformation['surcharges'] - $priceInformation['discounts'];

		$per = $package['monthly'] ? '(per Month)' : ($recurring ? '(per Week)' : '');

		$includeTaxLine = TRUE; // staffOnlyTEST() || dbTEST('tonkapetsitters');

		$bottomLine = $priceInformation['total'];
		if($includeTaxLine) {
			$bottomLine = $bottomLine + $priceInformation['tax'];
		}


		$priceInformation = "<b>Services: $per</b>".dollarAmount($priceInformation['services']).'<br>'
												."<b>Surcharges$surchargeLabels: </b>".dollarAmount($priceInformation['surcharges']).'<br>'
												.'<b>Discounts: </b>'.dollarAmount(0 - $priceInformation['discounts']).'<br>'
												.($priceInformation['tax'] && $includeTaxLine ? '<b>Tax: </b>'.dollarAmount($priceInformation['tax']).'<br>' : '')
												.'<b>Total: </b>'.dollarAmount($bottomLine).'<p>';
		if($_SESSION['preferences']['bottomLineOnlyInSchedNotificPriceInfo'])
			$priceInformation = '<b>Total: </b>'.dollarAmount($bottomLine).'<p>';
		//$showStats=true, $includeApptLinks=true, $surcharges=null
		//if(mattOnlyTEST()) {echo "<p>Appts include 253438: ".in_array(253438, $apptIds).'<p>'.join(', ', $apptIds); exit;}
		//if(mattOnlyTEST()) {echo "<p>Appts: <pre>".print_r($appts,1)."</pre>"; exit;}

		//$oldApplyValue = $applySitterNameConstraintsInThisContext;
		//$applySitterNameConstraintsInThisContext = true;
		$otherItems = false; //$_SESSION['preferences']['enableShowOtherVisitsOnScheduleUpdates'];
		appointmentTable($appts, $package, $editable=false, $allowSurchargeEdit=false, $showStats=true, 
							$includeApptLinks=false, $surcharges, $otherItems);
		//$applySitterNameConstraintsInThisContext = $oldApplyValue;

		if(!$appts) echo "This schedule contains no visits.";

		$appointmentTable = ob_get_contents();
		ob_end_clean();
		getServiceNamesById('refresh'); // populates $_SESSION['servicenames']
		return $appointmentTable;
	}
	echo "<div style='padding:0px;margin-right:5px;'>"
				.nrPackageCalendar($packdetail)
				."</div>";

	exit;
}


$recurring = false;
getAllServiceNamesById();
$packs = finalNonRecurringSchedules($clientptr, $recurring);
foreach((array)$packs as $pack) {
	$row = array();
	$row['start'] = $pack['startdate'] == $pack['enddate'] ? shortDate(strtotime($pack['startdate'])) : 
				shortDate(strtotime($pack['startdate'])).' - '.shortDate(strtotime($pack['enddate']));
	$packserviceTypes =array();
	$appts = getAllScheduledAppointments($pack['packageid']);
	$dates = array();
	$inRangeAppts = 0;
	foreach($appts as $appt) {
		$dates[] = $appt['date'];
		$enddate = $pack['enddate'] ? $pack['enddate'] : $pack['startdate'];
		if($appt['date'] < $pack['startdate'] || $appt['date'] > $enddate) continue;
		$inRangeAppts += 1;
		$packserviceTypes[$appt['servicecode']] = $_SESSION['allservicenames'][$appt['servicecode']];
	}
	$packserviceTypes = join(', ', $packserviceTypes);	
	$row['services'] = 
		$pack['irregular'] ==  2 ? 'Meeting' : (
		$pack['irregular'] ? 'EZ Schedule' : (
		$pack['onedaypackage'] ? ($packserviceTypes ? $packserviceTypes : 'One Day Schedule') : (
		($packserviceTypes ? $packserviceTypes : 'Pro Schedule'))));
			
	$row['services'] = fauxLink($row['services'], "viewPack({$pack['packageid']})", 1, 2);
	$row['price'] = $pack['packageprice'] ? dollarAmount($pack['packageprice']) : '--';		
	$row['duration'] = count(array_unique($dates))." days / $inRangeAppts visits";
	$data[] = $row;
}

require "frame-bannerless.php";
echo '<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
			 <script type="text/javascript" src="colorbox/version1.3.19/jquery.colorbox-min.js"></script>
			 <script type="text/javascript"">
			 function viewPack(id) {
				 $.fn.colorbox({href:"client-final-schedules.php?packdetail="+id, width:"90%", height:"90%", scrolling: true, opacity: "0.3"});
			 }
			 </script>
			 <link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" />';
dumpNRSectionStyle();
$columns = array('start'=>'Dates','services'=>'Services','price'=>'Schedule Price','duration'=>'Duration');
$colClasses = array('price'=>'dollaramountheader');

echo "<h2>Archived Schedules for $clientname</h2>";

$recurringpack = fetchFirstAssoc("SELECT * FROM tblrecurringpackage WHERE clientptr = $clientptr ORDER BY packageid DESC LIMIT 1");
if($recurringpack) {
	echo "<h3>Recurring Schedule</h3>";
	echo "<div style='background:white;margin-bottom:10px;padding-left:10px;'>";
	dumpRecurringSchedule($recurringpack, $clientptr, 'noEdit');
	echo "</div>";
}
echo "<div style='padding:0px;' id='nonrecurringschedulesdiv'>";
if($data)	{
	echo "<h3>Short Term Schedules</h3><div style='padding:0px;padding-left:10px;background:white;'>";
	tableFrom($columns, $data, "bgcolor='white' width='100%'", null, 'sortableListHeader', null, 'sortableListCell', null, $rowClasses, $colClasses);
	echo "</div>";
}
else echo "<table id='nonrecurringschedulestable'></table>";
