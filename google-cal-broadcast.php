<?// google-cal-broadcast.php
set_include_path(get_include_path().':/var/www/prod/ZendGdata-1.11.6/library:');
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "preference-fns.php";
require_once "google-cal-fns.php";
require_once "js-gui-fns.php";
set_time_limit(5 * 60);

// Determine access privs
locked('o-');
extract($_REQUEST); //filterXML,targetType,sendnow


if($filterXML) {
	$filterXMLObject = new SimpleXMLElement($filterXML);
	$ids = "$filterXMLObject->ids";
}
if($filterXML  && !$ids) $possibleTargets = array();
else {
	$targetLabel = 'Sitter';
	$ids = $filterXML  ? "WHERE providerid IN ($ids)" : "";
	$possibleTargets = fetchAssociationsKeyedBy(
		"SELECT providerid, userid, lname, fname, IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as sendname, active
			FROM tblprovider $ids ORDER BY lname, fname", 'providerid');
}
	

$pageTitle = "Sitter Calendar Broadcast ";
$finalMessage = '';

if($_POST && $sendnow) {
	foreach(array_keys($_POST) as $param)
		if(strpos($param, 'recip-') === 0) {
			$providerids[] = substr($param, strlen('recip-'));
		}
	if($providerids) {
	  $messages = updateProviderCalendarsForDates(join(',', $providerids), $_REQUEST['start'], $_REQUEST['end']);
		$finalMessage = join("<p>", $messages);
	}
	else $finalMessage .= "No provider calendars were updated.";
}

$allowedUserIds = getPreference('googleCalendarEnabledSitters');
if($allowedUserIds) $allowedUserIds = explode(',', $allowedUserIds);
$googleCreds = fetchKeyValuePairs("SELECT userptr, value FROM tbluserpref WHERE property = 'googlecreds'");

include "frame.html";
// ***************************************************************************
if($finalMessage) {
	echo $finalMessage;
	include "frame-end.html";
	exit;
}

	
echoButton('',"Update Calendars of Selected Sitters", 'updateCalendars()');
//echo '<p>';
//fauxLink('Select All', 'selectAll(1)');
echo ' - ';
fauxLink('Select All Active', 'selectAll(1, "active")');
echo ' - ';
fauxLink('Select All Inactive', 'selectAll(1, "inactive")');
echo ' - ';
fauxLink('Deselect All', 'selectAll(0)');
echo ' <div class="tiplooks" style="display:inline;padding-left:10px;" id="selectionCount"></div>';
echo '<p>';
echo "<style>.pad {padding-left: 10px;}</style>";
echo "<table>";
echo "<form name='calupdateform' method='POST'><tr><td valign='top'>";
hiddenElement('filterXML', $filterXML);
hiddenElement('sendnow', 0);
echo "<table>";
echo "<tr><td colspan=4 style='font-size:1.1em;font-weight:bold;vertical-align:top;'>";
fauxLink('Filter Sitters', 'openFilter("filter-providers.php")');
echo "</td></tr>";
$filterDesc = $filterXML && trim($filterXMLObject->ids) ? "Current Filter: $filterXMLObject->filter<br>Found: ".count(explode(',',$filterXMLObject->ids)) : '';
echo "<tr><td colspan=4 style='padding-bottom: 5px;' id='filter'>$filterDesc</td></tr>";
echo "<tr><td colspan=4 style='font-size:1.1em;font-weight:bold'>Active Sitters</td></tr>";
listTargets($possibleTargets, 1);
echo "<tr><td colspan=4 style='font-size:1.1em;font-weight:bold'>Inactive Sitters</td></tr>";
listTargets($possibleTargets, 0);
echo "</table></td>";
echo "<td style='vertical-align:top;padding-left: 10px;'>Update Sitter Calendars<br>";
calendarSet('Starting:', 'start', $start, null, null, true, 'end');
echo "<br>";
calendarSet('Ending:', 'end', $end, null, null, true);
echo "</td></tr>\n";
echo "</form></table>";


function listTargets($possibleTargets, $active) {
	global $allowedUserIds, $googleCreds;
	$n = 0;
	foreach($possibleTargets as $id => $details) {
		if($details['active'] != $active) continue;
		$n++;
		$cbid = "recip-$id";
		$isActive = $active ? 'ISACTIVE=1' : '';
		$disabledReason = !$details['userid'] ? 'No system login.' : (
												!in_array($details['userid'], $allowedUserIds) ? 'Calendar updates disabled.' : (
												!$googleCreds[$details['userid']] ? 'No Google calendar creds supplied.' :
												''));
		$checkBox = $disabledReason 
								? "<input type='checkbox' disabled>" 
								: "<input name='$cbid' id='$cbid' type='checkbox' $isActive onclick='updateSelectionCount()'>";
		$label = "{$details['fname']} {$details['lname']} ".($details['nickname'] ? "({$details['nickname']})" : '');
		echo "<tr><td>$checkBox</td><td style='font-weight:bold;' colspan=3><label for='$cbid'>$label - ".
						(!$disabledReason ? $details['email'] : "<i>$disabledReason</i>")."</label></td></tr>";
	}
	if(!$n) echo "<tr><td colspan=3 style='font-style:italic'>None found</td></tr>";
}	
?>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript'>

var templateLookup = {0:''
	<? 	if($templateOptions) echo ",";
			foreach((array)$templateOptions as $label => $id) $temps[] = "$id:'".safeValue($label)."'";
			if($templateOptions) echo join(',', $temps);
	?>

};



setPrettynames('message','Message','subject','Subject','start','Starting','end','Ending');
function selectAll(on, isactive) {
	var cbs = document.getElementsByTagName('input');
	for(var i=0;i<cbs.length;i++)
		if(cbs[i].type == 'checkbox' && !cbs[i].disabled &&
				(!isactive ||
				  (isactive == "active" && cbs[i].getAttribute('ISACTIVE')) ||
				  (isactive == "inactive" && !cbs[i].getAttribute('ISACTIVE'))
				 )
				)
			cbs[i].checked = on ? true : false;
	updateSelectionCount();
}

function updateSelectionCount() {
	var cbs = document.getElementsByTagName('input');
	var boxcount = 0;
	for(var i=0;i<cbs.length;i++)
		if(cbs[i].type == 'checkbox' && !cbs[i].disabled &&
				cbs[i].checked)
					boxcount++;
	document.getElementById('selectionCount').innerHTML = "Names selected: "+boxcount;
}

function updateCalendars() {
	var selCount = 0;
	var cbs = document.getElementsByTagName('input');
	for(var i=0;i<cbs.length;i++)
		if(cbs[i].type == 'checkbox' && !cbs[i].disabled)
			if(cbs[i].checked) selCount++;
	var noSelections = '';
	if(selCount == 0) noSelections = "Please select at least one sitter first.";
	if(MM_validateForm(noSelections, '', 'MESSAGE', 
											'start', '','R',
											'start', '', 'isDate',
											'end', '','R',
											'end', '', 'isDate'
											)) {
		document.getElementById('sendnow').value = 1;
		document.calupdateform.submit();
	}
}

function getDocumentFromXML(xml) {
	try //Internet Explorer
		{
		xmlDoc=new ActiveXObject("Microsoft.XMLDOM");
		xmlDoc.async="false";
		xmlDoc.loadXML(xml);
		return xmlDoc;
		}
	catch(e)
		{
		parser=new DOMParser();
		xmlDoc=parser.parseFromString(xml,"text/xml");
		return xmlDoc;
		}
}

function getFilter() {
	/*$result = "<root><filter>".join(' ', $filterDescription)."</filter>"
						."<ids>".join(' ', $filterDescription)."</ids>"
						."<start>$start</start>"
						."<end>$start</end>"
						."<status>$clientstatus</status>"
						."</root>"; */
	var filter = new Array('','','');
//alert(document.getElementById('filterXML'));	
	var filterXML = document.getElementById('filterXML').value;
	if(!filterXML) return filter; 
	var root = getDocumentFromXML(filterXML).documentElement;
	var nodes = root.getElementsByTagName('start') ;
	if(nodes.length == 1 && nodes[0].firstChild) filter[0] = nodes[0].firstChild.nodeValue;
	nodes = root.getElementsByTagName('end') ;
	if(nodes.length == 1 && nodes[0].firstChild) filter[1] = nodes[0].firstChild.nodeValue;
	nodes = root.getElementsByTagName('status') ;
	if(nodes.length == 1 && nodes[0].firstChild) filter[2] = nodes[0].firstChild.nodeValue;
	return filter;
}

function openFilter(scriptName) {
	var filter = getFilter();
	var url = scriptName+'?start='+filter[0]+'&end='+filter[1]+'&status='+filter[2];
	openConsoleWindow('filterwindow', url,500,270);
}

function update(aspect, data) {
	if(aspect == 'filter') {
		document.getElementById('filterXML').value = data;
		var root = getDocumentFromXML(data).documentElement;
		var nodes = root.getElementsByTagName('filter');
		if(nodes.length == 1) {
			//var desc = 'Current Filter: '+nodes[0].firstChild.nodeValue;
			//nodes = root.getElementsByTagName('ids');
			//if(nodes.length == 1) desc += "<br>Found: "+nodes[0].firstChild.nodeValue.split(',').length;
			//document.getElementById('filter').innerHTML = desc;
			document.calupdateform.submit();
		}
	}
}

<? dumpPopCalendarJS(); ?>

</script>

<?

// ***************************************************************************

include "frame-end.html";


// ******* UPCOMING SCHEDULE SUPPORT ****************************************

function idsForActiveSittersWithUpcomingVisits($start, $end) {
	return fetchCol0("SELECT distinct providerptr 
										FROM tblappointment
										LEFT JOIN tblprovider On providerid = providerptr
										WHERE active = 1 AND date >= '$start' AND date <= '$end'");
}


?>
