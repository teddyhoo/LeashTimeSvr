<?
// timeframes.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require "timeframe-fns.php";
require "service-fns.php";
require_once "preference-fns.php";
require_once "time-framer-mouse.php";

// Determine access privs
$locked = locked('o-');

//print_r($_POST);exit;
if($_POST) {
	saveTimeframes();
	if($_POST['defaultTimeFrame'])
		setPreference('defaultTimeFrame', $_POST['defaultTimeFrame']);
		
	$_SESSION['frame_message'] = 'Named Time Frames have been saved.';
}
$pageTitle = "Named Time Frames";

include "frame.html";
?>
<style>
.defaultboxon {color:red; font-variant: small-caps; font-weight:bold;cursor:pointer;}
.defaultboxoff {color:gray; font-variant: small-caps;cursor:pointer;}
</style>
<?
// ***************************************************************************

makeTimeFramer('timeframerdiv', false, true);

?>
<style>
.shortLabelInput {width: 100px;}
</style>
<form name='timeframesform' method='POST'>
<?
echoButton('', 'Edit Display Order', 'if(checkAndSubmit())openConsoleWindow("timeframeorderEdit", "timeframe-order-edit.php",400,700)');
echo "<p>";
echoButton('', 'Save Time Frames', 'checkAndSubmit()');
echo " <span style='color:green;font-style:italic;'>To add more named time frames, save the existing time frames and more blanks will appear.</span>";
timeframeEditor();
hiddenElement('defaultTimeFrame', '');
echo "</form>";

?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>

function update() { // called by display-order-edit.php
	document.location.href='timeframes.php';
	//if(confirm("The ordering of the Client Services menu has changed.\nSave changes on this page and redisplay the list?")) {
		//checkAndSubmit();
	//}
}


function checkAndSubmit() {
	//var maxCustomFields = <?= $maxCustomFields ?>;
	var msgargs = [];
	for(var i=1; document.getElementById('label_'+i); i++) {
		document.getElementById('timeframe_'+i).value = document.getElementById('div_timeofday_'+i).innerHTML;
		var timeframe = document.getElementById('timeframe_'+i).value;
		var label = document.getElementById('label_'+i).value.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
	  if((!timeframe && label) || (!timeframe && label)) {
	    msgargs[msgargs.length] = 'Time Frame #'+i+' must have both a label and a designated time frame.';
	    msgargs[msgargs.length] = '';
	    msgargs[msgargs.length] = 'MESSAGE';
		}
	}
	msgargs[msgargs.length] = findDuplicateLabels();
	msgargs[msgargs.length] = '';
	msgargs[msgargs.length] = 'MESSAGE';
  if(!MM_validateFormArgs(msgargs)) 
		  return false;
	$('.defaultboxon').each(
		function(unused, el) {
			var index = el.id.substring(el.id.indexOf('_')+1);
			if(frameIsValid(index)) 
				document.getElementById('defaultTimeFrame').value =
					document.getElementById('div_timeofday_'+index).innerHTML;
		});
	document.timeframesform.submit();
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

<? dumpTimeFramerJS('timeframerdiv'); ?>

function mouseCoords(ev){  // for pets and weekday widgets
	if(ev.pageX || ev.pageY){
		return {x:ev.pageX, y:ev.pageY};
	}
	return {
		x:ev.clientX + document.body.scrollLeft - document.body.clientLeft,
		y:ev.clientY + document.body.scrollTop  - document.body.clientTop
	};
}

function frameIsValid(i) {
	return document.getElementById('label_'+i).value 
					&& document.getElementById('div_timeofday_'+i).innerHTML;
}

function defaultClicked(i) {
	if(!frameIsValid(i)) return;
	$('.defaultboxon').addClass('defaultboxoff');
	$('.defaultboxon').removeClass('defaultboxon');
	//$('#default_'+i).addClass();
	document.getElementById('default_'+i).className = 'defaultboxon';
}

</script>
<p><img src='art/spacer.gif' height=300>
<?
// ***************************************************************************

include "frame-end.html";
?>

