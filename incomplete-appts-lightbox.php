<? // incomplete-appts-lightbox.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";


// Determine access privs
if(userRole() == 'd') $locked = locked('d-');
else $locked = locked('o-');
$numIncomplete = countAllProviderIncompleteJobs();
?>
<head> 
  <meta http-equiv="Content-Type" content="text/html;charset=ISO-8859-1" />  
  <link rel="stylesheet" href="style.css" type="text/css" /> 
  <link rel="stylesheet" href="pet.css" type="text/css" /> 
</head>
<div id='section_title_incomplete'></div>
<form name='incompleteform'>
<? 
calendarSet('Starting:', 'incompletestart', shortDate(strtotime("-7 days")), null, null, true, 'incompleteend');
calendarSet('ending:', 'incompleteend', shortDate());
echo " ";
echoButton('', 'Show Incomplete', 'showIncomplete()');
echo " ";
echoButton('', 'Show All Incomplete', 'showAllIncomplete()');
echo "<div id='incomplete_list'></div>";
echo "</form>";

$showIncomplete = $_REQUEST['showIncomplete'];
?>

<script language='javascript' src='incomplete-appts.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript'>
function update() {
	pleaseWaitWhileIncompleteListIsBuilt();
	showIncomplete();
}

function selectAllIncomplete(onoff) {
	var inputs;
	inputs = document.getElementsByTagName('input');
	for(var i=0; i < inputs.length; i++)
		if(inputs[i].type=='checkbox' 
				&& (inputs[i].id.indexOf("appt_") == 0
						 || inputs[i].id.indexOf("sur_") == 0
						))
			inputs[i].checked = (onoff ? 1 : 0);
}

<? dumpPopCalendarJS(); ?>


<?
if($showIncomplete) {
	if($_REQUEST['incompletestart'])
		$incomStartDate = shortDate(strtotime($_REQUEST['incompletestart']));
	else if(strpos($showIncomplete, 'days') !== FALSE && ($daysBack = (int)substr($showIncomplete, strlen('days'))))
		// set start date and find visits
		$incomStartDate = shortDate(strtotime("- $daysBack days"));
	if($_REQUEST['incompleteend'])
		$incomEndDate = shortDate(strtotime($_REQUEST['incompleteend']));
	if($incomStartDate)
		echo "\ndocument.getElementById('incompletestart').value = '$incomStartDate';";
	if($incomEndDate)
		echo "\ndocument.getElementById('incompleteend').value = '$incomEndDate';";
	if($incomStartDate || $incomEndDate)
		echo "\npleaseWaitWhileIncompleteListIsBuilt();
						showIncomplete();\n";
}
?>
</script>