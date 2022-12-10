<?
// unassigned-visits-board-add-listing.php
require_once "prov-schedule-fns.php";

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "time-framer-mouse.php";
require_once "preference-fns.php";

locked('o-');


require "frame-bannerless.php";

if($_POST) {
	$listing = array('uvbdate'=>$_POST['date'],  
										'uvbtod'=>$_POST['timeofday'],   
										'uvbnote'=>$_POST['description'], 
										'created'=>date('Y-m-d H:i:s'),
										'createdby'=>$_SESSION['auth_user_id']);
	$uvbid = insertTable('tblunassignedboard', $listing);
	echo "<script language='javascript'>parent.publish($uvbid)</script>";
	exit;
}
makeTimeFramer('timeFramer', 'narrow');
?>
<h2>Add a listing for <?= longDayAndDate(strtotime($_GET['date'])) ?></h2>
The Unassigned Visits Board (with any changes you have made) will be saved when you add this listing.
<p>
<form method='POST' name='mainform'>
<?  
echoButton('', 'Add Listing', 'saveListing()'); 
hiddenElement('date', $_GET['date']);
?>
<table width=300>
<tr><td><b>Time of Day (optional): </b>
<? fauxLink('clear', 'document.getElementById("div_timeofday").innerHTML = ""'); ?>

</td><td>
<?
buttonDiv("div_timeofday", "timeofday", "showTimeFramer(event, \"div_timeofday\")", '');
?>
</td></tr>

<tr><td><b>Description:<b></td/tr>
</table>
<textarea name='description' id='description' cols=80 rows=15></textarea>
</form>

<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
function saveListing() {
	if(MM_validateForm('description', '', 'R')) {
			document.getElementById('timeofday').value = 
				document.getElementById('div_timeofday').innerHTML;		
			document.mainform.submit();
	}
}
<? 
dumpTimeFramerJS('timeFramer');
?>
function mouseCoords(ev){  // for pets and weekday widgets
	if(ev.pageX || ev.pageY){
		return {x:ev.pageX, y:ev.pageY};
	}
	return {
		x:ev.clientX + document.body.scrollLeft - document.body.clientLeft,
		y:ev.clientY + document.body.scrollTop  - document.body.clientTop
	};
}

</script>