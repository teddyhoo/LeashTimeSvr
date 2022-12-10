<? // value-pack-fns.php

// Value Packs Templates
function OLDgetStandardValuePacks() {
	$jsons = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE property LIKE 'valuepack-%'");
	$packs = array();
	foreach($jsons as $prop=>$j) {
		$pack = json_decode($j, 'assoc');
		$pack['json'] = $j;
		$pack['prop'] = $prop;
		$packs[$pack['label']] = $pack;
	}
	ksort($packs);
	foreach($packs as $i => $pack) {
		$prop = $pack['prop'];
		unset($pack['prop']);
		$vpacks[$prop] = $pack;
	}
	return $vpacks;
}

function valuePackServiceTypes() {
	// SPEC: a servicetype may belong to up to one valuepack template
	// return an array of servicetype=>valuepack template id
	$vptypes = fetchKeyValuePairs(
		"SELECT vpid, servicetypes 
			FROM tblvaluepack 
			WHERE templateid IS NULL AND servicetypes IS NOT NULL");
	foreach($vptypes as $vpid=>$typestring)
		foreach(explode(',', $typestring) as $servicetypeid)
			$taken[$servicetypeid] = $vpid;
	return $taken;
}

function valuePackIDForServiceType($typeid) {
	$taken = valuePackServiceTypes();
	return $taken($typeid);
}

function getStandardValuePacks($activeOnly=false) {
	// use tblvaluepack
	// use clientptr==0 for inactive template, clientptr== -1  for active template
	$test = $activeOnly ? "clientptr = -1" : "clientptr < 1";
	return fetchAssociationsKeyedBy(
		"SELECT * 
			FROM tblvaluepack 
			WHERE $test 
			ORDER BY clientptr DESC, label ASC", 'vpid');
}


// Individual Value Packs

//uses existing table tblappointmentprop(appointmentptr, property, value).
// TBD: What are the rules on altering package membership for a visit?  If it is completed, does that matter?

function packageStatus($vpid) {
	// Unpaid/Active/Consumed/Expired
	$billable = fetchFirstAssoc("SELECT * FROM tblbillable WHERE itemptr = $vpid AND itemtable = 'tblvaluepack' AND superseded = 0 LIMIT 1", 1);
	if(!$billable || $billable['charge'] > $billable['paid']) return 'unpaid';
	$pack = getValuePack($vpid);
	if(!$pack) return null;
	if($pack['expires'] && strcmp($pack['expires'], date('Y-m-d')) == -1) return 'expired';
	if(prepaidVisitsLeft($vpid) == 0) return 'consumed';
	return 'active';
}

function updateExpiration($vpidOrPack, $status=null, $forceUpdate=false) {
	$vpid = is_array($vpidOrPack) ? $vpidOrPack['vpid'] : $vpidOrPack;
	$status = $status ? $status : packageStatus($vpid);
	if(in_array($status, array('unpaid','expired'))) return;
	$pack = is_array($vpidOrPack) ? $vpidOrPack : getValuePack($vpid);
	if($forceUpdate && !$pack['duration'])
		updateTable('tblvaluepack', array('expires'=>sqlVal('NULL')), "vpid = {$pack['vpid']}", 1);
	else if($pack['duration'] && (!$pack['expires'] || $forceUpdate)) {
		$pack['expires'] = date('Y-m-d', strtotime("+ {$pack['duration']} days", time()));
		updateTable('tblvaluepack', array('expires'=>$pack['expires']), "vpid = {$pack['vpid']}", 1);
	}
}
	

function findMemberIds($vpid) {
    return fetchCol0("SELECT appointmentptr FROM tblappointmentprop WHERE property = 'vpptr' AND value = $vpid");
}

function getValuePack($vpid) {
	setValuepackExpirationIfPaid($vpid);
	return fetchFirstAssoc("SELECT * FROM tblvaluepack WHERE vpid = $vpid LIMIT 1");
}

function getClientValuePacks($clientptr, $sort=null) {
	$sort = $sort ? $sort : 'label';
	$packs = fetchAssociations("SELECT * FROM tblvaluepack WHERE clientptr = $clientptr ORDER BY label");
	foreach($packs as $pack) {
		$updated = setValuepackExpirationIfPaid($pack) || $updated;
	}
	return !$updated ? $packs : fetchAssociations("SELECT * FROM tblvaluepack WHERE clientptr = $clientptr ORDER BY label");
}

function setValuepackExpirationIfPaid($vpidOrPack) {
	// return true if valuepack expiration was updated
	$pack = is_array($vpidOrPack) ? $vpidOrPack : null;
	if($pack && $pack['vpid'] && $pack['expires']) return false;
	$vpid = $pack ? $pack['vpid'] : $vpidOrPack;
	$found = fetchFirstAssoc("SELECT duration, expires FROM tblvaluepack WHERE vpid = $vpid LIMIT 1");
	if($found['expires'] || !$found['duration']) return false;
	$paidBillableId = fetchRow0Col0(
			"SELECT billableid 
				FROM tblbillable 
				WHERE itemtable = 'tblvaluepack' AND itemptr = $vpid AND superseded = 0 AND paid >= charge 
				LIMIT 1", 1);
	if($paidBillableId) {
		$creditIssuedate = fetchRow0Col0(
			"SELECT issuedate
				FROM relbillablepayment
				LEFT JOIN tblcredit ON creditid = paymentptr
				WHERE billableptr = $paidBillableId
				ORDER BY issuedate DESC
				LIMIT 1", 1);
		if(!$creditIssuedate) return false;  // WTF? should not happen
		$newExpiration = new DateTime($creditIssuedate);
		$newExpiration->add(new DateInterval("P{$found['duration']}D"));
		updateTable('tblvaluepack', array('expires'=>$newExpiration->format('Y-m-d')), "vpid = $vpid", 1);
		return true;
	}
}
	

function prepaidVisitsLeft($vpidOrPack) {
    $package =
        is_array($vpidOrPack)
            ? $vpidOrPack
            : getValuePack($vpidOrPack);
    if(!$package) return;
    return $package['visits'] - count(findMemberIds($package['vpid']));
}

function dropPackageVisit($apptid) {
    deleteTable('tblappointmentprop ', "appointmentptr = $apptid AND property = 'vpptr'", 1);
}

function addPackageVisit($vpid, $apptid) {
    replaceTable('tblappointmentprop ', array('appointmentptr'=>$apptid, 'property'=>'vpptr', 'value'=>$vpid), 1);
}

function removeToken($apptid) {
	return applyToken(null, $apptid);
}

// Billing note: invoice-fns.php>createBillablesForNonMonthlyAppts is a no-op when a visit has a token.
// createBillablesForNonMonthlyAppts is called when a visit changes to completed status

function applyToken($vpptr, $apptid) {
	// if both parts are present, link them
	if($vpptr && $apptid) {
		// first make sure valuepack is eligible
		if(packageStatus($vpptr) != 'active')
			$error = 'Value pack is ineligible.';
		// next make sure appt does not have a prior token
		else if(fetchRow0Col0("SELECT value FROM tblappointmentprop WHERE appointmentptr = $apptid AND property = 'vpptr' LIMIT 1", 1))
			$error = 'Visit already has a token.';
		if(!$error) {
			$appt = fetchFirstAssoc(
				"SELECT a.*, b.paid
					FROM tblappointment a 
					LEFT JOIN tblbillable b ON itemtable = 'tblappointment' AND itemptr = $apptid AND superseded = 0");
			if($appt['canceled']) $error = "Value pack tokens cannot be applied to canceled visits.";
			else {
				if($appt['billableid']) {
					// appt may or may not be paid
					require_once "invoice-fns.php";
					supersedeAppointmentBillable($apptid);
				}
				addPackageVisit($vpptr, $apptid);
			}
		}
	}
	// otherwise break the link
	else if($apptid){
		dropPackageVisit($apptid);
		// create a billable as appropriate
		recreateAppointmentBillable($apptid);
	}
	
	// Returns a displayable value for the appointment editor, either the number of remaining tokens or 'V' for no token applied.
	if($error) return array('error'=>$error);
	else if($vpptr && $apptid) return prepaidVisitsLeft($vpptr);
	else return 'V';
}


function setupValuePackTable() {
	doQuery(
"CREATE TABLE IF NOT EXISTS `tblvaluepack` (
  `vpid` int(11) NOT NULL AUTO_INCREMENT,
  `templateid` int(11) DEFAULT NULL,
  `clientptr` int(11) NOT NULL,
  `label` varchar(100) NOT NULL,
  `visits` int(11) NOT NULL,
  `refill` int(11) DEFAULT NULL,
  `price` float(6,2) NOT NULL,
  `notes` text,
  `expires` date DEFAULT NULL COMMENT 'set when billable is paid if duration set',
  `duration` int(11) DEFAULT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `notified` tinyint(4) DEFAULT NULL,
  `servicetypes` text,
  PRIMARY KEY (`vpid`),
  KEY `clientptr` (`clientptr`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
", 1);
}

function vpsWithCounts() { // utility
	return fetchAssociations(
		"SELECT COUNT(*) as used, v.* 
			FROM `tblvaluepack` v 
			LEFT JOIN tblappointmentprop ON value = vpid
			WHERE property = 'vpptr'
			GROUP BY vpid");
}

function valuePackRefillsNotification() {
	require_once "request-fns.php";
	$candidates = valuePackRefillCandidates($notNotifiedOnly=true);
	if($candidates) {
		$request = array();
		$request['extrafields'] = array();
		$subject = 'Value Pack Renewals';
		$request['extrafields']['subject'] = $subject;
		foreach($candidates as $vp) 
			$extraFields .= "<hidden key=\"vp_{$vp['vpid']}\"><![CDATA[{$vp['client']}]]></hidden>";
		$request['extrafields'] = "<extrafields>$extraFields</extrafields>";

		//$request['extrafields'] = $subject;
		$request['subject'] = $subject;
		$request['requesttype'] = 'ValuePackRefills';
		$request['note'] = 'The value packs listed are due for renewal';

	}
	return saveNewClientRequest($request);
}

function displayValuePackRefillsRequest($source, $updateList) {
	startForm($source, $updateList, 'Value Packs Due for Renewal');
	echo "\n<table width=100%>\n";
	labelRow('Date:', '', $source['date']);
	echo "\n</table>";
	echo "<div style='margin:5px; padding:5px; background:white;'>{$source['note']}</div><p>";
	echo "<div style='margin:5px; padding:5px; background:white;'>";
	labeledCheckbox('', 'selectorbox', $value=0, $labelClass=null, $inputClass=null, $onClick='selectToggle()', $boxFirst=true, $noEcho=false, $title=null);
	//fauxLink('Select All Value Packs', 'selectAll(1)');
	//echo " - ";
	//fauxLink('Deselect All Value Packs', 'selectAll(0)');
	echo " ";
	echoButton('', 'Remind Selected Clients', 'remindClients()');
	echo " ";
	echoButton('', 'Charge Selected Clients', 'chargeClients()');
	dumpValuePackRequestListTable($source);
	echo "</div>";
	echo <<<SCRIPT
<script language='javascript'>
function remindClients() {
	var selections = 0;
	if($('.vpcheckbox').attr('checked')) selections += 1;
	var noBoxesChecked = selections > 0 ? '' : 'Please select at least one client first.';
  if(MM_validateForm(
		  noBoxesChecked, '', 'MESSAGE')) {
		//document.getElementById('resolved').value = 1;
		//document.requesteditor.submit();
		alert('TBD');
	}
}
function selectToggle() {
	var pick = document.getElementById('selectorbox').checked // why doesn't $('#selectorbox').attr('checked') work?
	$('.vpcheckbox').attr('checked', pick==1);
}
function selectAll(pick) {
	$('.vpcheckbox').attr('checked', pick==1);
}
</script>
SCRIPT;
}


function dumpValuePackRequestListTable($requestOrRequestId) {
	// return an html table with a checkbox for each client
	$entries = valuePackRequestEntries($requestOrRequestId);
	foreach($entries as $i => $vp) {
		$entries[$i]['cb'] = labeledCheckbox('', "vp_{$vp['vpid']}", $value=null, $labelClass=null, $inputClass='vpcheckbox', $onClick=null, $boxFirst=false, $noEcho=true, $title=null);
		$entries[$i]['tokensLeft'] = prepaidVisitsLeft($vp);
		$entries[$i]['price'] = dollarAmount($vp['price']);
		if($vp['notified']) $entries[$i]['clientname'] .= "<span style='cursor:pointer' title='Already reminded'> &#10004;</span>";
		$entries[$i]['clientname'] = "<label for='vp_{$vp['vpid']}'>{$entries[$i]['clientname']}</label>";
	}
	$columns = explodePairsLine("cb| ||clientname|Client||tokensLeft|Visits Left||label|Value Pack||price|Price");
	tableFrom($columns, $entries, $attributes='border=0 width="95%"', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);
}

function valuePackRequestEntries($requestOrRequestId) {
	require_once "request-fns.php";
	$request = is_array($requestOrRequestId) ? $requestOrRequestId : getClientRequest($requestOrRequestId);
	foreach(getHiddenExtraFields($request) as $k => $clientname) {
		if(strpos($k, 'vp_') !== 0) continue;
		$vp = getValuePack(substr($k, 3));
		$vp['clientname'] = $clientname;
		$entries[] = $vp;
	}
	return $entries;
}

function valuePackRefillCandidates($notNotifiedOnly=true) {
	$today = date('Y-m-d');
	if($notNotifiedOnly) $notNotifiedOnly = "AND notified IS NULL";
	// find all non-notified, unexpired value packs for active clients
	$refills = fetchAssociationsKeyedBy(
		"SELECT vpid, visits, refill
			FROM tblvaluepack
			LEFT JOIN tblclient ON clientid = clientptr
			WHERE active = 1 AND refill IS NOT NULL $notNotifiedOnly AND (expires IS NULL OR expires > '$today')", 'vpid');
	if(!$refills) return;
	//echo "found refills.";
	// count the tokens applied from each value pack
	$used = fetchKeyValuePairs(
		"SELECT value, COUNT(*)
			FROM tblappointmentprop
			WHERE property = 'vpptr' AND value IN (".join(',', array_keys($refills)).")
			GROUP BY value", 1);
			
	// drop value packs with remaining tokens > refill
	foreach($used as $vpid => $count) {
		if($refills[$vpid]['visits'] - $count > $refills[$vpid]['refill'])
			unset($refills[$vpid]);
		else $refills[$vpid]['remaining'] = $refills[$vpid]['visits'] - $count;
	}
	if(!$refills) return;
	
	// gather full details, including client names for each candidate
	$vps = fetchAssociationsKeyedBy(
		"SELECT vp.*, fname, lname, CONCAT_WS(',', lname, fname) as sortName, CONCAT_WS(' ', fname, lname) as client
			FROM tblvaluepack vp
			LEFT JOIN tblclient ON clientid = clientptr
			WHERE vpid IN (".join(',', array_keys($refills)).")
			ORDER BY sortName", 'vpid');
	foreach($refills as $vpid => $partial) 
		$vps[$vpid]['remaining'] = $partial['remaining'];
		
	return $vps;
}

function valuePacksTable($clientptr) {
	$packs = getClientValuePacks($clientptr, $sort='created DESC');
	foreach($packs as $pack) {
		$row = array_merge($pack);
		$row['created'] = shortDate(strtotime($pack['created']));
		$row['status'] = packageStatus($pack['vpid']);
		if($pack['expires']) {
			$row['expires'] = shortDate(strtotime($pack['expires']));
			$expColor = strcmp(date('Y-m-d'), $pack['expires']) == -1 ? 'red' : '';
			if($expColor) $row['expires'] = "<span style='color=$expColor;'>{$row['expires']}</span>";
		}
		else {
			$row['expires'] = $row['duration'] ? "{$row['duration']} days after payment" : 'never';
		}
		$unusedVisits = prepaidVisitsLeft($pack['vpid']);
		$row['usedvisits'] = "".($row['visits'] - $unusedVisits);
		$row['visits'] = "{$row['usedvisits']} / {$row['visits']}";
		$row['refill'] = $pack['refill'] ? $pack['refill'].' visits' : '';
		$row['label'] = $pack['label'] ? $pack['label'] : 'A Value Pack';
		$title = safeValue(truncatedLabel("{$pack['notes']}", 100));
		$row['label'] = 
			fauxLink($row['label'],
			"$.fn.colorbox({href:\"value-pack-edit.php?vpid={$pack['vpid']}\", iframe: \"TRUE\", width:\"650\", height:\"500\", scrolling: true, opacity: \"0.3\"});", 
			1, $title);
		$refillColor = $pack['refill'] && $unusedVisits <= $pack['refill'] ? 'red' : '';
		if($refillColor) $row['refill'] = "<span style='color=$expColor;'>{$row['refill']}</span>";
		$rows[] = $row;
	}
	echoButton(null, "New Value Pack", 
		"$.fn.colorbox({href:\"value-pack-edit.php?clientptr=$clientptr\", iframe: \"TRUE\", width:\"500\", height:\"500\", scrolling: true, opacity: \"0.3\"});");
	echo "<br>";
	if($rows) {
		$columns = explodePairsLine('created|Created||label|Label||status|Status||expires|Expires||visits|Visits Used||refill|Refill Level');
		tableFrom($columns, $rows, $attributes='width=90%', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);
	}
	else echo "No value packs found for this client.";
}

function visitValuePackOptions($clientptr, $apptid=null) {
	// return a vpid=>label array of available value packs for this visit
	// include the current value pack associated with this visit, even if it is unavailable
	if($apptid) $current = fetchFirstAssoc(
		"SELECT label, vpid
			FROM tblappointmentprop
			LEFT JOIN tblvaluepack ON vpid = value
			WHERE appointmentptr = $apptid AND property = 'vpptr'
			LIMIT 1");
	$avail = fetchKeyValuePairs("SELECT vpid, label FROM tblvaluepack WHERE clientptr = $clientptr", 1);
	foreach($avail as $vpid => $label) {
		if(packageStatus($vpid) != 'active')
			unset($avail[$vpid]);
	}
	if($current && !$avail[$current['vpid']]) $avail[$current['vpid']] = $current['label'];
	asort($avail);
	//$avail = array_merge($avail);
	foreach($avail as $vpid => $label) 
		$avail[$vpid] .= " (".prepaidVisitsLeft($vpid)." avail.)";
	return $avail;
}

function valuepackDescription($vpid) {
	require_once "js-gui-fns.php";
	// if no $vpid, allow standard vp chocie
	$pack = getValuePack($vpid);
	$visitsLeft = prepaidVisitsLeft($vpid);
	$visitsUsed = $pack['visits'] - $visitsLeft ? $pack['visits'] - $visitsLeft : 'No';
	$fullyEditable = $visitsLeft == $pack['visits'];
	$packStatus = packageStatus($vpid);
	echo "<table width='100%' border=1 bordercolor=gray>";
	//echo "<tr><td></td><td>$deleteButton</td>";
	//labelRow('Value Pack:', 'label', $pack['label']);
	echo "<tr><td colspan=2>Value Pack: <span style='font-size:1.1em'>{$pack['label']}</span></td><tr>";
	echo "<tr><td>Number of Tokens: {$pack['visits']}. ($visitsUsed tokens used,  $visitsLeft left)</td><td>Price: ".dollarAmount($pack['price'])."</td><tr>";
	//labelRow('Number of Tokens:', 'visits', $pack['visits'].". $visitsUsed tokens used.");
	//labelRow('Price:', 'price', $pack['price']);
	//labelRow('Refill notication: ', 'refill', $pack['refill']);
//echo "<tr><td>Status: $packStatus<td>".print_r($pack, 1);
	//if($packStatus == 'unpaid') labelRow('Expires after days: ', 'duration', $pack['duration']);
	//else labelRow('Expires on:', 'expires', $pack['expires']);
	
	if($packStatus == 'unpaid') $expCell = "Expires: after {$pack['duration']} days";
	else {
		$expCell = "Expires on: ".shortDate(strtotime($pack['expires']));
		$datetime1 = new DateTime($pack['expires']);
		$datetime2 = new DateTime(date('Y-m-d'));
		$interval = (int)($datetime1->diff($datetime2)->format('%R%a'));
		$title = $interval < 0 ? abs($interval)." days ago" : (!$interval ? 'today' : "in $interval days");
		$expCell = "<span style='cursor:pointer' title='$title'>$expCell</span>";
	}
	
	echo "<tr><td>Send Refill notication: when {$pack['refill']} remain</td><td>$expCell</td><tr>";
	//echo "<tr><td>Notes:</td></tr>";
	echo "<tr><td colspan=2>Notes:<br>{$pack['notes']}</td></tr>";
	//textRow('Notes', 'notes', $pack['notes'], $rows=6, $cols=60);
	echo "</table>";
	echo "</form>";
	$pack['status'] = $packStatus;
	$pack['visitsLeft'] = $visitsLeft;
	echoStatusDescription($vpid, $pack);
	
	$expirationArgs = 
		$packStatus == 'Unpaid' 
		? "args[args.length] = 'duration'; args[args.length] = ''; 'UNSIGNEDINT';"
		: "args[args.length] = 'expiration'; args[args.length] = ''; args[args.length] = 'isDate';";
	$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');
	$visiteditor =  adequateRights('ea') || $roDispatcher ? 'appointment-edit.php' : 'appointment-view.php';
}
	
function echoStatusDescription($vpid, $pack) {
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

	//if($pack['visitsLeft'] == 0) $stats[] = 'All of the Value Pack visits have been applied.';
	//else $stats[] = "There are {$pack['visitsLeft']} tokens left in this Value Pack.";
	
	if($stats) echo join('<br>', $stats);

	if(!$appts) ; //echo "<p>None of this Value Pack's tokens have been applied.";
	else {
		echo "<p>";
		fauxLink('This Value Pack has been applied to..', 
				"document.getElementById(\"visitstable\").style.display = "
				."document.getElementById(\"visitstable\").style.display == \"none\" ? \"block\" : \"none\"");;
		foreach($appts as $i => $appt) {
			$appts[$i]['num'] = ($i+1).". ";
			//$appts[$i]['service'] = fauxLink($appt['service'], "editVisit({$appt['appointmentid']})", 1, 'Edit this visit');
			$appts[$i]['date'] = shortDate(strtotime($appt['date']));
			$appts[$i]['status'] = 
				$appt['canceled'] ? 'Canceled' : (
				$appt['completed'] ? 'Completed' :
				'Incomplete');
		}
		$columns = explodePairsLine('num| ||date|Date||timeofday| ||service|Service||status|Status||sitter|Sitter');
		tableFrom($columns, $appts, 'id="visitstable" style="display:none;width:90%"', null, null, null, null, $columnSorts, $rowClasses);
	}
	
}

function valuepackPicker($apptid, $clientptr=null) {
	// $apptid may represent: (null) a new unsaved visit, a visit of any status without any valuepack affiliation
	// if no visit...
	if(!$apptid) {
	}
	else {
		$appt = fetchFirstAssoc(
			"SELECT a.*, b.charge, b.paid 
				FROM tblappointment a
				LEFT JOIN tblbillable b ON itemtable = 'tblappointment' AND itemptr = appointmentid AND superseded = 0
				WHERE appointmentid = $apptid LIMIT 1", 1);
		// if visit is partially or completely paid for
		if(!$appt) { $error = 'This visit was not found [$apptid].'; }
		else if($appt['paid']) { $error = 'This visit has already been paid for.'; }
		// else if a visit is completed and unpaid
		else if($appt['completed']) { /* anything special to do here at this stage? */ }
		// else if a visit is canceled
		else if($appt['canceled']) { $error = 'This visit has been canceled.'; }
		// else if a visit is incomplete
		else {}
	}
	if($error) {
		echo "$error";
		return;
	}
	$clientptr = $clientptr 
								? $clientptr 
								: fetchRow0Col0("SELECT clientptr FROM tblappointment WHERE appointmentid = $apptid LIMIT 1", 1);
	$options = visitValuePackOptions($clientptr, $apptid);
	echo "This visit has no Value Pack token.<p>";
	if($options) {
		if(count($options) > 0) {
			echo "Review a Value Pack:<br>";
			echo "<select id='valuepacks' name='valuepacks' onchange='showValuePack(this)'>";
			echo "<option value=0>-- Choose a Value Pack --";
			foreach($options as $vpid => $label)
				echo "<option value=$vpid>$label";
			echo "</select> ";
			echoButton('applyTokenButton', 'Apply Token to This Visit', "applyToken()");
			echo "<div id='summary'></div>";
		}
		else {
		}
		?>
		<script language='javascript' src='ajax_fns.js'></script>
		<script language='javascript'>
function applyToken() {
	var el = document.getElementById('valuepacks');
	var vpid = el.options[el.selectedIndex].value;
}

function showValuePack(el){
	var vpid = el.options[el.selectedIndex].value;
	if(!vpid || vpid == 0) document.getElementById('summary').innerHTML = '';
	else {
		ajaxGet("value-pack-link.php?vpptr="+vpid, 'summary');
		document.getElementById('applyTokenButton').style.display='inline';
	}
}
document.getElementById('applyTokenButton').style.display='none';
		</script>
		<?
	}
}