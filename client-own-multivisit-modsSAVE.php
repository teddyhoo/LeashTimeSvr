<? // client-own-multivisit-mods.php
// this deals with future visits only
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "client-fns.php";
require_once "preference-fns.php";

// Determine access privs
$locked = locked('c-');

$max_rows = 100;

extract($_REQUEST);
$id = $_SESSION["clientid"];
$action = strtolower("$action");
//echo "($action): ".print_r(in_array($action, explode(',', 'cancel,uncancel,change')), 1);
if(!in_array($action, explode(',', 'cancel,uncancel,change'))) { // cancel, uncancel, change
	echo "Action required.";
	exit;
}
$prettyAction = strtoupper($action[0]).substr($action,1);

if($appt) {
	$apptid = $appt;
	$apptdate = fetchRow0Col0("SELECT date FROM tblappointment WHERE clientptr = $id AND appointmentid = $apptid LIMIT 1", 1);
	if(!$apptdate) {
		echo "Bad information supplied,";
		exit;
	}
}
if($apptdate) {
	// allow user to set an ending date and include up to 12 months prior, as long as the time frame includes $apptdate
	if($ending) {
		$ending = date("Y-m-d", min(time("+12 months", strtotime($apptdate)), strtotime($ending)));
		$starting = date("Y-m-d", max(strtotime(date("Y-m-d")), date("Y-m-d", strtotime("-12 month", strtotime($ending)))));
	}
	else {
		$starting = date("Y-m-d", max(strtotime(date("Y-m-d")), date("Y-m-d", strtotime("-6 month", strtotime($apptdate)))));

		$ending = date("Y-m-d", strtotime("+12 months", strtotime($starting)));
	}
}
else if($ending) {
	$starting = date("Y-m-d", max(strtotime(date("Y-m-d")), date("Y-m-d", strtotime("-12 month", strtotime($ending)))));
}
else {
	$starting = date("Y-m-d");
	$ending = date("Y-m-d", strtotime("+12 months", strtotime($starting)));
}
$ending = shortDate(strtotime($ending));
$DBending = date("Y-m-d", strtotime($ending));
//print_r($_REQUEST);
if($ending) {
	$filter = "clientptr = $id AND date >= '$starting' AND date <= '$DBending'";
	$client = getOneClientsDetails($id);
	$client['name'] = $client['clientname'];
	
	$sql = "SELECT appointmentid, date, timeofday, servicecode, canceled, label AS service 
			FROM tblappointment
			LEFT JOIN tblservicetype ON servicetypeid = servicecode
			WHERE $filter
			ORDER BY date, starttime";

	$visits = fetchAssociationsKeyedBy($sql, 'appointmentid', 1);
//echo print_r("$sql", 1)."<p>".print_r($visits, 1);
			
			
	//print_r($comms);
	//foreach($comms as $i=>$item)
	//	if(strpos($item['subject'], 'msgviewer') === FALSE)
	//		unset($comms[$i]);	
	$numFound = count($visits);
	$searchResults = ($numFound ? $numFound : 'No')." visit".($numFound == 1 ? '' : 's')." found.  ";

function visitsTable($visits, $action, $focus) {
	//cb| ||timeofday|Time||service|Service
	$columns = explodePairsLine("cb| ||timeofday|Time||service|Service");
	$columns = explodePairsLine("cb| ||timeofday| ||service| ");
	foreach($visits as $apptid => $appt) {
		if($appt['date'] != $lastDate) {
			if(date("F", strtotime($appt['date'])) != $lastMonth) {
				$lastMonth = date("F", strtotime($appt['date']));
				$rows[] = array('#CUSTOM_ROW#'=>"<tr><td colspan=3 class='monthcell'>".date("F Y", strtotime($appt['date']))."</td></tr>");
				$rowClasses[] = '';
			}
			$rows[] = array('#CUSTOM_ROW#'=>"<tr><td colspan=3 class='visitday'>".longDayAndDate(strtotime($appt['date']))."</td></tr>");
			$rowClasses[] = '';
		}
		$lastDate = $appt['date'];
		$cbid = ($action == 'cancel' && !$appt['canceled'])
					|| ($action == 'uncancel' && $appt['canceled'])
					|| ($action == 'change' && !$appt['canceled'])
				? "visit_$apptid"
				: '';
		$cb = $cbid
				? "<input type='checkbox' class='visitcheckbox' id='$cbid' onclick='updateSelectionCount()'>"
				: '';
		$rowClass = $appt['canceled'] ? 'canceledtask' : '';
		if(!$cbid) $rowClass .= " ineligible"; //italicized inactive greyedout
		$rowClasses[] = $rowClass;
		$rows[] = array('cb'=>$cb, 'timeofday'=>labelFor($appt['timeofday'], $cbid), 'service'=>labelFor($appt['service'], $cbid));
	}
	tableFrom($columns, $rows, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses=null, $sortClickAction=null);
}

function labelFor($text, $checkboxid) {
	return $checkboxid ? "<label for='$checkboxid' >$text</label>" : $text;
}

$pageTitle = "$prettyAction Visits";

if($_SESSION["responsiveClient"]) {
	$extraHeadContent = 
		"<style>
		body {font-size:1.2em;} 
		td.sortableListCell {padding-left:20px;} 
		td.monthcell {font-size:1.3em;font-weight:bold;text-align:center;}
		.tiplooks {font-size:14pt;}
		.dateInput {width:120px;}
		.floater {
			position: absolute;
			top: 100px;
			right: 3px;
			/*width: 150px;
			height: 100px;*/
			-webkit-transition: all 0.25s ease-in-out;
			transition: all 0.25s ease-in-out;
			z-index: 1;
		}
		.ineligible {color:gray;}
		.visitday {text-decoration:XXunderline;background:#e5e6e6;padding-left:4px;} /* very light grey */
		.canceledtask {background:#FFC0CB;} // pink
		</style>";
	$onLoadFragments[] = "initializeCalendarImageWidgets();";
	if($apptid) {
		$onLoadFragments[] = "$([document.documentElement, document.body]).animate({
        scrollTop: $('#visit_$apptid').offset().top-100
    }, 2000);";
		$onLoadFragments[] = "$('#visit_$apptid').attr( 'checked', true );";
	}
	$onLoadFragments[] = "$(window).scroll(function() {
	var winScrollTop = $(window).scrollTop();
	var winHeight = $(window).height();
	var floaterHeight = $('.floater').outerHeight(true);
	//true so the function takes margins into account
	var fromBottom = 0;20;

	var top = winScrollTop //+ winHeight - floaterHeight - fromBottom;
	$('.floater').css({'top': top + 'px'});
});";
	include "frame-client-responsive.html";
	$frameEndURL = "frame-client-responsive-end.html";
}
else if(userRole() == 'c') {
	include "frame-client.html";
	$frameEndURL = "frame-end.html";
	
}

?>
	<form name='multivisitsmodsform' method='POST'>
<? 
	hiddenElement('client', $id);
	hiddenElement('apptid', $apptid);
	hiddenElement('starting', $starting);
	echoButton('showVisits', 'Show', 'searchForVisits()');
	echo "<div class='floater'>";
	if(TRUE || mattOnlyTEST())
		//echoButton('actionButton', $prettyAction, "openRequestLightBox(\"$action\")", "BigButton", "BigButtonDown");
		echo "<input type='button' onclick='openRequestLightBox(\"$action\")' value='$prettyAction' class='btn btn-success' title=''>";
	else // old
		echoButton('actionButton', $prettyAction, "generateScheduleChangeRequest(\"$action\")", "Button", "ButtonDown");
	echo "<br><span id='selectionCount'></span>";
	echo "<br><img src='art/spacer.gif' height=300 width=2>";
	echoButton('helpButton', 'Help', "showHelp(\"$action\")", "Button", "ButtonDown");
	echo "</div>";
	echo " ";
	$ending = $ending ? $ending : date('m/d/Y');
	calendarSet('through:', 'ending', $ending, null, null, true, null, '', null, null, 'jqueryversion');
	echo " \n";

?>
	</form>
<?
	//echo "<table><tr><td style='padding-right:5px;'>$searchResults</td>";


	//echo "</tr></table><p><div style='background:white;border: solid black 1px'>";
	if($numFound) visitsTable($visits, $action, $id);
	//echo "</div>";
}


?>
<div id='clientmsgs'></div>
<img src='art/spacer.gif' height=300>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript'>


//setPrettynames('msgsstarting,Starting date for messages,msgsending,Starting date for messages');
function searchForVisits() {
	
  if(MM_validateForm(
		  'ending', '', 'isDate')) {
		//var appt = document.getElementById('apptid').value;
		//var ending = document.getElementById('ending').value;
		//if(ending) ending = '&ending='+ending;
		//var url = 'client-own-multivisit-mods.php';
		document.multivisitsmodsform.submit();
    //ajaxGet(url+'?appt='+appt+ending+'&action=<?= $action ?>', 'clientmsgs')
    
	}
}

function showHelp(action) {
	var capAction = "<?= ucfirst($action) ?>";
	var html = 
		"<h2>Help</h2>"
		+"<p>In this window you can choose one or more visits to "+action+"."
		+"<p>Tap each visit you want to "+action+" to check its box, and then tap the "+capAction+" button on the right."
		+"<p>You can "+action+" only visits with check boxes.";
	lightBoxHTML(html, 350, 200);
}

function openRequestLightBox(action) {
	var url = generateScheduleChangeRequest(action,'urlOnly');
	if(url) // did notfail form validation
		lightBoxIFrame(url+"lightbox=1", 370, 220);
}

function update(aspect, data) {
	if(aspect == 'actioncomplete') {
		lightBoxIFrameClose();
		document.location.href="index.php";
	}
}
		
function updateSelectionCount() {
	var count = $('input[type="checkbox"]').filter(':checked').length;
	var pluralizer = count == 1 ? '' : 's';
	$('#selectionCount').html(count+" visit"+pluralizer);
}
updateSelectionCount();

var mediaWidthQuery = window.matchMedia("(max-width: 600px)");
// Attach listener function on state changes
mediaWidthQuery.addListener(scoochFloater);

function scoochFloater(query) {
	var narrow = query.matches; 
	$('.floater').css('right', (narrow ? '3px' : '80px'));
}

scoochFloater(mediaWidthQuery);

<?
require_once "request-safety.php";
dumpClientScheduleChangeJS(array('backURL'=>$_SERVER["REQUEST_URI"]));
dumpJQueryDatePickerJS(); //dumpPopCalendarJS(); 
?>	


//searchForVisits();
</script>
<?
require $frameEndURL;

