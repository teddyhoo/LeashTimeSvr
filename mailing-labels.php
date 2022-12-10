<? // mailing-labels.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "comm-fns.php";
require_once "js-gui-fns.php";
require_once "mailing-label-fns.php";

/* displays keys held by all providers
	displays a checkbox next to each provider with an email address.
	"Send Audit" emails a message that summarizes the keys held by each provider and
	asks the provider to respond.
*/

// Determine access privs
locked('o-');
extract($_REQUEST); //filterXML,targetType,sendnow

if($action == 'generate') {
	$_SESSION['mailingLabelClientIds'] = array();
	foreach($_POST as $k => $v)
		if(strpos($k, 'recip-') === 0)
			$_SESSION['mailingLabelClientIds'][] = substr($k, strlen('recip-'));
}
else if($action == 'go') {
	$ids = join(',', $_SESSION['mailingLabelClientIds']);
	$_SESSION['mailingLabelClientIds'] = array();
}
if($ids) {
	if($targetType != 'provider') {
		if(!$ids || $ids == 'IGNORE') {
			$ids = $_SESSION['clientListIDString'];
			unset($_SESSION['clientListIDString']);
		}
	}
		
	$persons = $targetType == 'provider' ? 'provider' : 'client';
	$persons = fetchAssociations($sql = "SELECT * FROM tbl$persons WHERE {$persons}id IN ($ids) ORDER BY lname, fname");
	$pdf = null;

	Header('Pragma: public');
	//$pdf->ezNewPage();
	$pdf = labelPagesForPersons($persons, $pdf);
	$pdf->stream();
	exit;
}


if($filterXML) {
	$filterXMLObject = new SimpleXMLElement($filterXML);
	$ids = "$filterXMLObject->ids";
	if($targetType != 'provider') {
		if(!$ids || $ids == 'IGNORE') {
			$ids = $_SESSION['clientListIDString'];
			unset($_SESSION['clientListIDString']);
		}
	}
}
if($filterXML  && !$ids) $possibleTargets = array();
else if($targetType == 'provider')	{
	$targetLabel = 'Sitter';
	$ids = $filterXML  ? "WHERE providerid IN ($ids)" : "";
	$possibleTargets = fetchAssociationsKeyedBy(
		"SELECT providerid, CONCAT_WS('', street1, street2, city, state, zip) as addr, lname, fname, IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as sendname, active
			FROM tblprovider $ids ORDER BY lname, fname", 'providerid');
	
	}
else {
	$targetLabel = 'Client';
	$ids = $filterXML  ? "WHERE clientid IN ($ids)" : "";
	$possibleTargets = fetchAssociationsKeyedBy(
		"SELECT clientid, CONCAT_WS('', street1, street2, city, state, zip) as addr, lname, fname, CONCAT_WS(' ', fname, lname) as sendname , active
			FROM tblclient $ids ORDER BY lname, fname", 'clientid');
}

$pageTitle = "$targetLabel Mailing Labels ";
$finalMessage = '';

$breadcrumbs = "<a href='reports.php'>Reports</a>";	


include "frame.html";
// ***************************************************************************
if($finalMessage) {
	echo $finalMessage;
	include "frame-end.html";
	exit;
}

echoButton('',"Print Mailing Labels for Selected $targetLabel".'s', 'mailingLabels()');
//echo '<p>';
//fauxLink('Select All', 'selectAll(1)');
echo ' - ';
fauxLink('Select All Active', 'selectAll(1, "active")');
echo ' - ';
fauxLink('Select All Inactive', 'selectAll(1, "inactive")');
echo ' - ';
fauxLink('Deselect All', 'selectAll(0)');
echo ' <div class="tiplooks" style="display:inline;padding-left:10px;" id="selectionCount"></div> ';

echo '<p>';
echo "<style>.pad {padding-left: 10px;}</style>";
echo "<table>";
echo "<form name='emailform' method='POST'><tr><td valign='top'>";
hiddenElement('filterXML', $filterXML);
hiddenElement('targetType', $targetType);
hiddenElement('action', '');
echo "<table>";
echo "<tr><td colspan=4 style='font-size:1.1em;font-weight:bold'>";
if($targetType == 'provider')	fauxLink('Filter Sitters', 'openFilter("filter-providers.php")');
else fauxLink('Filter Clients', 'openFilter("filter-clients.php")');
echo "</td></tr>";
$resultCount = $filterXML && $filterXMLObject->resultCount ? $filterXMLObject->resultCount : count(explode(',',$filterXMLObject->ids));
$filterDesc = $filterXML && trim($filterXMLObject->ids) ? "Current Filter: $filterXMLObject->filter<br>Found: $resultCount" : '';
echo "<tr><td colspan=4 style='padding-bottom: 5px;' id='filter'>$filterDesc</td></tr>";
echo "<tr><td colspan=4 style='font-size:1.1em;font-weight:bold'>Active $targetLabel"."s</td></tr>";
listTargets($possibleTargets, 1);
echo "<tr><td colspan=4 style='font-size:1.1em;font-weight:bold'>Inactive $targetLabel"."s</td></tr>";
listTargets($possibleTargets, 0);
echo "</table></td>";


echo "<td valign=top style='padding-left:20px;padding-right:20px;background:lightgrey;'>";
echo <<<DIV
<div style='display:block;border:solid black 1px;background:white;width:400px;height:150px;font-size:1.1em;padding:7px;margin-top:15px;'>
<img src='art/lightning-smile-small.jpg' style='float:right;clear:right;'>
With this page you can print out pages of mailing labels.  
Use Avery 5160 printable label sheets (or their equivalent) to print up to 30 labels per page.
<p>
The labels are produced as a PDF, so you will need Adobe Acrobat Reader to view and print them.  
<p>
When printing, make sure that page scaling is set to "None", or the labels will be printed slightly 
reduced and misaligned to the page.
</div>
DIV;
echo "</td></tr>\n";





echo "</form></table>";


function listTargets($possibleTargets, $active) {
	global $partyPoopers; // the worst kind
	$n = 0;
	foreach($possibleTargets as $id => $details) {
		if($details['active'] != $active) continue;
		$n++;
		$cbid = "recip-$id";
		$isActive = $active ? 'ISACTIVE=1' : '';
		$disabledReason = $details['addr']
											? ''
											: (!$details['addr'] ? 'No address' : '??');
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

<? if($action == 'generate') echo "openConsoleWindow('labels', 'mailing-labels.php?targetType=$targetType&action=go', 800, 800);"; ?>

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

function mailingLabels() {
	var selCount = 0;
	var cbs = document.getElementsByTagName('input');
	var ids = new Array();
	for(var i=0;i<cbs.length;i++)
		if(cbs[i].type == 'checkbox' && !cbs[i].disabled) {
			if(cbs[i].checked) {
				selCount++;
				var parts = cbs[i].id.split('-'); //"recip-$id"
				ids[ids.length] = parts[1];
			}
		}
	var noSelections = '';
	if(selCount == 0) {
		alert("Please select at least one <?= $targetLabel ?> first.");
		return;
	}
	document.getElementById('action').value='generate';
	document.emailform.submit();
	//openConsoleWindow('labels', 'mailing-labels.php?targetType=<?= $targetType ?>&ids='+ids.join(','), 800, 800);
}


function toggleStartEndDisplay(id) {
	var label = templateLookup[id];
	var display = label == '#STANDARD - Upcoming Schedule' || label == '#STANDARD - Upcoming Sitter Schedule'  ? 'block' : 'none'; 
	document.getElementById('startEndFields').style.display = display;
}

function toggleClientChooserDisplay(id) {
	var label = templateLookup[id];
	var display = label == '#STANDARD - Client Schedule to Sitters'  ? 'block' : 'none'; 
	document.getElementById('clientselector').style.display = display;
	if(display == 'block' ) document.getElementById('startEndFields').style.display = display;
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
	openConsoleWindow('filterwindow', url,500,370);
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
			document.emailform.submit();
		}
	}
}


<? dumpPopCalendarJS(); ?>

</script>

<?

// ***************************************************************************

include "frame-end.html";



?>
