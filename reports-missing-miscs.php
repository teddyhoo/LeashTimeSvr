<? // reports-missing-miscs.php
// report on businesses that perhaps SHOULD have a LeashTime service Misc charge, but do not
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "provider-fns.php";
require_once "prov-schedule-fns.php";
require_once "client-fns.php";
require_once "pet-fns.php";
require_once "preference-fns.php";
require_once "google-map-utils.php";
require_once "client-flag-fns.php";

// Determine access privs
$locked = locked('o-');

$max_rows = 100;
extract($_REQUEST);

if($_POST) {
	if(!$reason) {
		echo "Please provide a Misc charge description.";
	}
	else {
		// find all goldstar clients
		$goldstars = fetchAssociationsKeyedBy("SELECT c.clientid, c.* 
												FROM tblclientpref
												LEFT JOIN tblclient c ON clientid = clientptr
												WHERE property LIKE 'flag_%' AND value like '2|%'", 'clientid');

		foreach($goldstars as $clientid => $client) {
			// exclude free users
			if(fetchRow0Col0("SELECT clientptr 
												FROM tblclientpref 
												WHERE clientptr = $clientid AND property LIKE 'flag_%' AND value like '4|%'", 1))
				continue;
			if(fetchRow0Col0("SELECT chargeid FROM tblothercharge WHERE clientptr = $clientid AND reason = '$reason' LIMIT 1", 1))
				continue;
			else {
				$row = $client;
				$panel = clientFlagPanel($clientid, $officeOnly=false, $noEdit=true, $contentsOnly=true, $onClick=null, $includeBillingFlags=false);
				$lastPayment = fetchFirstAssoc(
					"SELECT * 
						FROM tblcredit 
						WHERE clientptr = $clientid AND payment = 1 AND voided IS NULL
						ORDER BY issuedate DESC
						LIMIT 1", 1);
				$row['fname'] .= ' '.$panel;
				$row['fname'] = "<a href=\"client-edit.php?tab=account&id=$clientid\" target=\"satellite\">{$row['fname']}</a>";
				$row['lastpaid'] = $lastPayment['issuedate'] ? date('Y-m-d', strtotime($lastPayment['issuedate'])) : '--';
				$row['lastpaidamt'] = $lastPayment['amount'];
				$row['lastcharge'] = fetchRow0Col0("SELECT reason FROM tblothercharge WHERE clientptr = $clientid ORDER BY chargeid DESC LIMIT 1", 1);
				$rows[] = $row;
			}
		}
		$columns = explodePairsLine("fname|Business||lname|Owner||lastcharge|Last Charge||lastpaidamt|Last Paid||lastpaid|On");
		//tableFrom($columns, $rows=null, $attributes="border=1", $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);
		
		if($counts = fetchKeyValuePairs("SELECT clientptr, COUNT(*) chargeid FROM tblothercharge WHERE reason = '$reason' GROUP BY clientptr", 1)) {
			foreach($counts as $clientid => $count)
				if($count > 1) {
					$dup = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = $clientid LIMIT 1");
					$dup['dups'] = $count;
					$panel = clientFlagPanel($clientid, $officeOnly=false, $noEdit=true, $contentsOnly=true, $onClick=null, $includeBillingFlags=false);
					$dup['fname'] .= ' '.$panel;
					$dup['fname'] = "<a href=\"client-edit.php?tab=account&id=$clientid\" target=\"satellite\">{$dup['fname']}</a>";
					$dups[] = $dup;
				}
			}
			if($dups) $dupColumns = explodePairsLine("fname|Business||lname|Owner||dups|# Duplicates");


	}
}

$breadcrumbs = $breadcrumbs = "<a href='reports.php'>Reports</a>";
include "frame.html";

$latestReasons = fetchKeyValuePairs("SELECT chargeid, reason FROM tblothercharge WHERE reason LIKE 'LeashTime Service%' ORDER BY chargeid DESC LIMIT 1", 1);

if($dups) echo "There are <a href='#dups'>DUPLICATE CHARGES</a>.  See below/<p>";
else if($reason) echo "There are NO DUPLICATE CHARGES labeled [$reason].";

?>
<h2>Missing Misc Charges?</h2>
<form method="POST">
Examine: <input name='reason' value = "<?= $reason ? $reason : current($latestReasons) ?>" style='width:300px;'> <input type='submit'>
</form>

<?
if($rows)
	tableFrom($columns, $rows, $attributes="border=1", $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);
if($dups) {
	echo "<h2><a name='dups'>Duplicate Charges</a></h2>";
	tableFrom($dupColumns, $dups, $attributes="border=1", $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);
}
include "frame-end.html";
