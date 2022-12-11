<?
// client-services-editor.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require "client-services-fns.php";
require "service-fns.php";
}
// Determine access privs
$locked = locked('o-');

//print_r($_POST);exit;
if($_POST) {
	saveClientServiceFields();
	$this_dir = substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/"));
	//header("Location: $mein_host$this_dir/client-services-editor.php");
	$saveMessage = 'Client Scheduler Services has been saved.';
}
$pageTitle = "Services Offered in Client Scheduler";
$breadcrumbs = fauxLink('Service List', 'document.location.href="service-types.php"', 1);


include "frame.html";
// ***************************************************************************
if($saveMessage) echo "<p style='color:green;'>$saveMessage</p>";
?>
<style>
.shortLabelInput {width: 100px;}
</style>
<form name='clientschedservicesform' method='POST'>
<?
echoButton('', 'Edit Menu Order', 'if(checkAndSubmit())openConsoleWindow("serviceSorderEdit", "client-services-order-edit.php",400,700)');
echo "<p>";
echoButton('', 'Save Client Services', 'checkAndSubmit()');
echo " <span style='color:green;font-style:italic;'>To add more services, save the existing services and more blanks will appear.</span>";
clientServiceFieldEditor();
echo "</form>";

?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
function showHelp() {
	$.fn.colorbox({html:"<?= addSlashes(helpString()); ?>", width:"750", height:"600", scrolling: true, opacity: "0.3"});
}

function update() { // called by display-order-edit.php
	document.location.href='client-services-editor.php';
	//if(confirm("The ordering of the Client Services menu has changed.\nSave changes on this page and redisplay the list?")) {
		//checkAndSubmit();
	//}
}


function checkAndSubmit() {
	//var maxCustomFields = <?= $maxCustomFields ?>;
	var msgargs = [];
	for(var i=1; document.getElementById('label_'+i); i++) {
		var servicecode = document.getElementById('servicecode_'+i).selectedIndex;
		var label = document.getElementById('label_'+i).value.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
	  if((servicecode && !label) || (!servicecode && label)) {
	    msgargs[msgargs.length] = 'Client Service #'+i+' must have both a label and a designated service.';
	    msgargs[msgargs.length] = '';
	    msgargs[msgargs.length] = 'MESSAGE';
		}
	}
	msgargs[msgargs.length] = findDuplicateLabels();
	msgargs[msgargs.length] = '';
	msgargs[msgargs.length] = 'MESSAGE';
	msgargs[msgargs.length] = findDuplicateCodes();
	msgargs[msgargs.length] = '';
	msgargs[msgargs.length] = 'MESSAGE';
  if(!MM_validateFormArgs(msgargs)) 
		  return false;
	document.clientschedservicesform.submit();
	return true;
}

function findDuplicateLabels() {
	var vals = [];
	for(var i=1; document.getElementById('label_'+i); i++) {
		var v = document.getElementById('label_'+i).value;
		if(v) vals[vals.length] = v;
	}
	if(arrHasDupes(vals)) return 'Each Label must be unique.';
	return null;
}
		
function findDuplicateCodes() {
	var vals = [];
	for(var i=1; document.getElementById('servicecode_'+i); i++) {
		var v = document.getElementById('servicecode_'+i).selectedIndex;
		if(v) vals[vals.length] = v;
	}
	if(arrHasDupes(vals)) return 'Each Service must be unique.';
	return null;
}
		
function arrHasDupes( A ) {                          // finds any duplicate array elements using the fewest possible comparison
	var i, j, n;
	n=A.length;
                                                     // to ensure the fewest possible comparisons
	for (i=0; i<n; i++) {                        // outer loop uses each item i at 0 through n
		for (j=i+1; j<n; j++) {              // inner loop only compares items j at i+1 to n
			if (A[i]==A[j]) return true;
	}	}
	return false;
}

</script>
<p><img src='art/spacer.gif' height=300>
<?
// ***************************************************************************

include "frame-end.html";

function helpString() {
	$help = <<<HELP
<h2 style='text-align:center'>The Client Services List</h2>
<p>This list determines what services your clients will be offered when they request services through LeashTime.&nbsp; If no services are specified here, then the Service pulldown menu the client sees will be empty.</p>
<p>For each service you want to offer clients, supply a label and choose a service type to associate with that label.&nbsp; For example, if you have three Dog Walk service types, "Dog Walk 15 minutes", "Dog Walk 15 minutes Multiple Dogs", and "Dog Walk 15 minutes Holiday Rate", you may simply want to offer your clients "Dog Walk 15 minutes" to save them confusion.</p>
<p>The maximum allowable length of the label depends on the <strong>Days to show in Service Schedule Maker</strong> setting in ADMIN &gt; Preferences &gt; Client User Interface.&nbsp; This constraint prevents the Service pulldown menu from becoming so wide that it distorts the layout of the Client Schedule Maker page.&nbsp; These length limits are:</p>
<center>
<table border="1">
<tbody>
<tr><th>Number of Days</th><th>Max. Label Length</th></tr>
<tr><th>6 days</th><th>14 characters</th></tr>
<tr><th>5 days</th><th>20 characters</th></tr>
<tr><th>4 days</th><th>30 characters</th></tr>
</tbody>
</table>
<p>
<center>
<img src='art/client-services-list-6-days.jpg'><center><hr>
<img src='art/client-services-list-5-days.jpg'><center><hr>
<img src='art/client-services-list-4-days.jpg'>
HELP;
	return trim(str_replace("\r", "", str_replace("\n", "", $help)));
}



?>

