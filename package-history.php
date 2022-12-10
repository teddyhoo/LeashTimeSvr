<? // package-history.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "service-fns.php";
require_once "gui-fns.php";

locked('+o-','+d-');

$package = getPackage($_REQUEST['id']);

$history = findPackageIdHistory($package['packageid'], $package['clientptr'], $package['recurring']);
//print_r(join(', ', $history));
$history = array_reverse($history);
$mgrs = getManagers(null, $ltStaffAlso=true);
require_once "frame-bannerless.php";

$limit = 50;
foreach($history as $packageid) {
	if(!$limit) break;
	$limit--;
	$rows[] = $package['recurring']
		? recurringPackageRow(getPackage($packageid)) 
		: nonrecurringPackageRow(getPackage($packageid));
}

$columns = $package['recurring']
	? explodePairsLine("date| ||time| ||label|Schedule||effectivedate|Effective||mgr|Manager||packageid|Package ID")
	: explodePairsLine("date| ||time| ||label|Schedule||daterange|Date Range||mgr|Manager||packageid|Package ID");
echo "<h2>Edit History</h2>";
tableFrom($columns, $rows, $attributes='border=1', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);


function recurringPackageRow($package) {
	global $mgrs;
	$row['date'] = shortDate(strtotime($package['created']));
	$row['packageid'] = $package['packageid'];
	$row['time'] = date('h:i: a', strtotime($package['created']));
	//$fontWeight = $package['current'] ? 'font-weight:bold;' : '';
	$row['label'] = $package['monthly'] ? 'Monthly' : 'Weekly';
	if($package['cancellationdate']) {
		$row['label'] .= " (canceled)";
		$row['label'] = "<span style='$fontWeight text-decoration: underline; text-decoration-style: dotted;' title='Canceled ".shortDate(strtotime($package['cancellationdate']))."'>{$row['label']}</span>";
	}
	else 
		$row['label'] = "<span style='$fontWeight'>{$row['label']}</span>";
	$row['effectivedate'] = $row['effectivedate'] ? shortDate(strtotime($package['effectivedate'])) : '--';
	$mgr = $mgrs[$package['createdby']];
	$row['mgr'] = $mgr ? $mgr['name'] : $package['createdby'];
	//$row['mgr'] = $mgrs[$package['createdby']] ? $mgrs[$package['createdby']] : print_r(getUserByID($package['createdby']), 1);
	return $row;
}

function nonrecurringPackageRow($package) {
	global $mgrs;
	$row['date'] = shortDate(strtotime($package['created']));
	$row['time'] = date('h:i: a', strtotime($package['created']));
	//$fontWeight = $package['current'] ? 'font-weight:bold;' : '';
	$row['label'] = $package['onedaypackage'] ? 'One Day' : (
									$package['irregular'] ? 'EZ Schedule' : "Pro Schedule");
	if($package['cancellationdate']) {
		$row['label'] .= " (canceled)";
		$row['label'] = "<span style='$fontWeight text-decoration: underline; text-decoration-style: dotted;' title='Canceled ".shortDate(strtotime($package['cancellationdate']))."'>{$row['label']}</span>";
	}
	else 
		$row['label'] = "<span style='$fontWeight'>{$row['label']}</span>";
		
	if($package['startdate']) $range[] = shortDate(strtotime($package['startdate']));
	if($package['enddate']) $range[] = shortDate(strtotime($package['enddate']));
	$row['daterange'] = join('-', $range);
	$mgr = $mgrs[$package['createdby']];
	$row['mgr'] = $mgr ? $mgr['name'] : $package['createdby'];
	//$row['mgr'] = $mgrs[$package['createdby']] ? $mgrs[$package['createdby']] : print_r(getUserByID($package['createdby']), 1);
	$row['packageid'] = $package['packageid'];
	return $row;
}
?>
