<? // client-own-scheduler-responsive.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "time-framer-mouse.php";
require_once "pet-fns.php";
require_once "petpick-grid-client.php";

if(userRole() == 'c') locked('c-');
else locked('o-');

/* an alternative to the usual PC-based scheduler
   one HTML/javascript page to handle the whole process
   works well on cellphones
   all ajax/json interaction with the server
   
   Scheme:
   
   Section 1: specific page content, based on page
   
   Section 2: Common buttons: Back, Next, Submit, Quit
   
   Section 3: review calendar and description
   
   Section 4: Day detail view
   
   
*/
$clientptr = userRole() == 'c' ? $_SESSION['clientid'] : 47; // really SHOULD be userRole c
require_once "client-services-fns.php";
$globalServiceSelections = getClientServices(); // label => id

$pagemag = 4;
$mag = 1.0;

$docType = FALSE && mattOnlyTEST() && strpos($_SERVER["HTTP_USER_AGENT"], 'iPhone')
	? ""
	: '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'
	;
?>
<?= $docType ?>
<html  xmlns="http://www.w3.org/1999/xhtml">
<head>
<!-- link rel="stylesheet" href="jqueryui-com_style.css" -->


<meta http-equiv="content-type" content="text/html; charset=UTF-8"></meta>

<link rel="stylesheet" href="jquery-ui.css"></link>
<link rel="stylesheet" href="pet.css"></link>
<style type="text/css">
@media only screen and (max-width: 600px) {
  body {
    background-color: lightblue;
  }
}



body {width:100%;font-size:<?= $pagemag ?>em;}
.entryscreen {font-size:<?= $mag ?>em; width:100%;}
.entrytable td {font-size:<?= $mag ?>em; width:100%;}
/*.entrytable input:not([type=button]):not([type=password]):not([type=submit]) {font-size:1.0em; width:50%;}*/
.dateinput {font-size:1.0em; width:50%; margin-right:25px;}
.entrytable {width:99%;}
.timeframerlabel {font-size:0.7em;} 
.timeframerampmlabel {font-size:0.5em;}
.timeFramerLinkLabel {font-size:0.8em;}
.timeframerAMPM_Off {font-size:0.5em;width:60px;}
.timeframerAMPM_On {font-size:0.5em;width:60px;}
.timeFrame { /* overrides pet.css */
	font-size:0.9em;
	/* border: solid black 2px;
	padding: 5px;
	background: white; */
}
.firstdaylabel,.lastdaylabel {color:gray;}
.buttondiv {
	background:white;
	width:500px;
	height:70px;
	cursor:pointer;
	border: solid darkgrey 1px;
	padding-left: .5em;
	overflow:hidden;
}

#petpickerbox table td {font-size:0.7em;}

.ui-datepicker { width: 14em; }
/*
.ui-datepicker table {font-size: 2.0em;}
.ui-datepicker .ui-datepicker-title select {font-size: 2.0em;}
.ui-datepicker .ui-datepicker-title {font-size: 2.0em;}
*/
.ui-widget {font-size: 1.0em;}


/* Icons
----------------------------------*/

/* states and images */
.ui-icon {
	width: 16px;
	height: 16px;
}
.ui-widget-header .ui-icon {
	background-image: url("jquery-ui-1.8.20/css/ui-lightness/images/ui-icons_222222_256x240.png"); //images/ui-icons_444444_256x240.png
}

.ui-state-hover .ui-icon,
.ui-state-focus .ui-icon,
.ui-button:hover .ui-icon,
.ui-button:focus .ui-icon {
	background-image: url("jquery-ui-1.8.20/css/ui-lightness/images/ui-icons_228ef1_256x240.png"); //images/ui-icons_555555_256x240.png
}

</style>

<!-- script language='javascript' src='popcalendar.js'></script --> 
<script type="text/javascript" src='check-form.js'></script>
<script type="text/javascript" src='common.js'></script>
<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
<script type="text/javascript" src="jquery-ui-1.8.20/js/jquery-ui-1.8.20.custom.min.js"></script>
<script type="text/javascript" src="client-own-scheduler-responsive-cal.js"></script> 
<script type="text/javascript">
<?
dumpPopCalendarJS();
?>
function getTheNextDay(date) {
	var val = mdy(date);
	val = new Date(val[2], val[0]-1, val[1]);

	var usformat = !val ? false : date.indexOf('/') > -1;
	val.setTime(val.getTime()+(1000 * 60 * 60 * 24));
	
	var result = usformat ? (val.getUTCMonth()+1)+'/'+val.getUTCDate()+'/'+val.getUTCFullYear()
		: val.getUTCDate()+'.'+(val.getUTCMonth()+1)+'.'+val.getUTCFullYear();
		
	return result;
}




function countActiveScreens() {
	var count = 0;
	count = $('.entryscreen:not(.disabled)').length;
	//$('.entryscreen:not(.disabled)').each(
	//	function(num, el){if(el.getAttribute('disabled') != 1) count += 1;});
	//alert(count);
	return count;
}

function submitRequest() {
	alert('About to Submit:'+"\n"+JSON.stringify(buildRequestJSON()));
	
	$.ajax({
	    url: 'client-scheduler-json-post.php',
	    dataType: 'json', // comment this out to see script errors in the console
	    type: 'post',
	    contentType: 'application/json',
	    data: JSON.stringify(buildRequestJSON()),
	    processData: false,
	    success: submitSucceeded,
	    error: <?= mattOnlyTEST() ? 'submitFailed' : 'submitSucceeded' // until I figure this out...Figured it out! ?>
	    });
}

function submitSucceeded(data, textStatus, jQxhr) {
	<? $bizzyName = $_SESSION['preferences']['bizName'];
		 $homeLink = "<a href='index.php'>Back to $bizzyName</a>";
	?>
	let message = "<h3>Thanks!</h3>Your schedule request has been submitted.<p>We'll be getting back to you shortly.<p><?= $homeLink ?>";
	$('#finalMessage').html(message);
	$('#finalMessage').show();
<? if(TRUE || !mattOnlyTEST()) { ?>
	$('.entrytable').hide();
	$('.entryscreen').hide();
<? } ?>
	<?= mattOnlyTEST() ? 'console.log(data);console.log(textStatus);console.log(jQxhr);' : "console.log('schedule json submitted.')" ?>

}

function submitFailed(jqXhr, textStatus, errorThrown) {
	let message = 'Error encountered:<br>'
		+<?= mattOnlyTEST() ? 'errorThrown' : '"Please notify support."' ?>;
	$('#finalMessage').html(message);
	$('#finalMessage').show();
	$('.entrytable').hide();
	$('.entryscreen').hide();
	console.log(message );
	<?= mattOnlyTEST() ? 'console.log("jqXhr: "+jqXhr);console.log("textStatus: "+textStatus);' : '' ?>
}



function updateReview() {
	var review = $('#initialreview').html();
	<? /* // (a #TOTALDAYS# day period)
You have asked for #NUMVISITS# visits on #VISITDAYS# days<br>
starting #FIRSTDATE# and ending #LASTDATE#.<br>
<!-- (a #TOTALDAYS# day period).<br> -->
The service requested is<br>#SERVICENAME#<br>
and the pets to be served are<br>#PETNAMES#.
#TOTALPRICE#
<hr>#CALENDAR#<hr>
Note:<p>
#NOTE#
*/ ?>

	<? if(!$_SESSION['preferences']['suppressClientSchedulerPriceDisplay']) { ?>
	var totalpriceText = ''; // The total price is #PRICETOTAL# <== add later
	<? } else { ?>
	var totalpriceText = '';
	<? }?>
	var json = buildRequestJSON();
//scratchIt(review+'<hr>'+JSON.stringify(json)+'<hr>[[['+json.visits.length+']]]');
	review = review.replace('#TOTALPRICE#', totalpriceText);
	review = review.replace('#NUMVISITS#', json.visits.length);
	review = review.replace('#VISITDAYS#', json.visitdays);
	review = review.replace('#FIRSTDATE#', dowForDate(json.start)+' '+json.start);
	review = review.replace('#LASTDATE#', dowForDate(json.end)+' '+json.end);
	review = review.replace('#TOTALDAYS#', json.totaldays);
	review = review.replace('#SERVICENAME#', serviceName(json.servicecode));
	review = review.replace('#PETNAMES#', json.prettypets);
	review = review.replace('#NOTE#', (json.note ? json.note : '<i>No note supplied.</i>'));
	
	var calendar = calendarsForPacket(json);
	review = review.replace('#CALENDAR#', calendar.join(' '));
//alert(calendar);
//scratchIt(JSON.stringify(json)+'<hr>'+review);

	$('#review').html(review);
	$('.monthNow').unbind('click');
	$('.dayNow').unbind('click');
}

function dowForDate(date) {
	var dayNum = (new Date(Date.parse(date))).getDay();
	var days = 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday'.split(',');
	return days[dayNum-1];
}

function serviceName(servicecode) {
<? 	
foreach($globalServiceSelections as $label => $id)
	echo "if(servicecode == $id) return \"".safeValue($label)."\"\n";
?>
}

function nextScreen() {
	if(okToProceed()) {
		updateReview();
		moveScreen(1);
	}
}

setPrettynames('start','First Day of Service','end','Last Day of Service', 'servicecode', 'Service Type');	
function okToProceed() {
	if(pgnum == 1) {
		var kludge = Date.parse($('#start').val()) > Date.parse($('#end').val()) ? 'Last Day must fall on or after First Day' : '';
		var result = MM_validateForm(
				  'start', '', 'R',
				  'start', 'not', 'isPastDate',
				  'end', '', 'R',
				  kludge, '', 'MESSAGE'
				  //'start', 'end', 'datesInOrder2' <= does not work
				  );
		return result;
	}
	else if(pgnum == 2) 
		return MM_validateForm(
				  'servicecode', '', 'R',
				  'end', '', 'R');
	else if(pgnum == 4 || pgnum == 6 || pgnum == 8) {
		let message = '';
		$('#screen_'+pgnum+' div').each(function(i, el) {
			if(el.id.indexOf('timediv_') == 0 && $(el).is(":visible") && $(el).html() == "") {
				message = 'All visit times must be filled in'; //+' '+el.id+': '+$('#'+el.id).css('display')+' ['+el.innerHTML+']');
			}
		});
		if(message) {
			alert(message);
			return false;
		}

	}
				  
	return true;
}


function backScreen() {
	moveScreen(-1);
}

var pgnum = 1;
var requestJson;

function moveScreen(change) {
	//if(change == -1) alert('back '+pgnum);
	if(pgnum == countActiveScreens() && change == 1) return; // should never happen
	pgnum = pgnum + change;
	//alert('#screen_'+pgnum+' disabled: '+$('#screen_'+pgnum).hasClass('disabled'));
	//if($('#screen_'+pgnum).hasClass('disabled')) moveScreen(change);
	while($('#screen_'+pgnum).hasClass('disabled')) pgnum += change;
	//scratchIt('pgnum: '+pgnum+" page active count: "+countActiveScreens());
	hideYourPrivateBits(pgnum);
	hidePetGrid();
	hideTimeFramer();
	rejiggerNavButtons();
	window.scrollTo(0,0);
	//$('#screen_10').show();
}

function hideYourPrivateBits(pgnum) {
	for(let i=1; i <= $('.entryscreen').length; i++) {
		if(i == pgnum) $('#screen_'+i).show();
		else $('#screen_'+i).hide();
	}
}

function dateRangeDelta() {
	var start = Date.parse(document.getElementById('start').value);
	var end = Date.parse(document.getElementById('end').value);
	var timeDiff = Math.abs(end - start);
	return Math.ceil(timeDiff / (1000 * 3600 * 24));
}
	

function rangeChanged() {
	var dayDifference = dateRangeDelta();
	var firstdayquestion = $('.countquestionfirstday')[0];
	//enable ALL sections
	$('.entryscreen.daysection').removeClass('disabled');
	if(dayDifference == 0) {
		// disable last day and other days sections
		$('.lastday').addClass('disabled');
		$('.otherdays').addClass('disabled');
		// Change "How many visits the FIRST day?" => "How many visits?"
		firstdayquestion.innerHTML = "How many visits?";
	}
	else if(dayDifference == 1) {
		// disable other days section
		$('.otherdays').addClass('disabled');

		//alert('otherdays disabled: '+$('.otherdays.disabled').length);
		// Change "How many visits?" => "How many visits the FIRST day?"
		firstdayquestion.innerHTML = "How many visits the FIRST day?";
	}
	else {
		firstdayquestion.innerHTML = "How many visits the FIRST day?";
	}
	$('.firstdaylabel').html('(on '+dowForDate($('#start').val())+' '+$('#start').val()+')');
	$('.lastdaylabel').html('(on '+dowForDate($('#end').val())+' '+$('#end').val()+')');
	$('.visitcountselect').each(function(num,el) {countChanged(el);});

}

function countChanged(el) {
	if(!el) return;
	var qel = document.getElementById('visit_times_question'+el.id);
	var count = parseInt(el.value);
	var rawquestion = qel.getAttribute('rawversion');
	if(rawquestion) {
		var plural = count == 1 ? '' : 's';
		qel.innerHTML = rawquestion.replace('#PLURAL#', plural);
		//alert(el.id+': '+dateRangeDelta());
		if(el.id == 'firstday' && dateRangeDelta() == 0)
		qel.innerHTML = qel.innerHTML.replace('the FIRST day', '');
	}
	var row;
	for(var i=1;(row=document.getElementById(el.id+'_row_'+i)); i+=1) {
		if(i<=count) $('#'+row.id).show();
		else $('#'+row.id).hide();
	}
}

function rejiggerNavButtons() {
	if(pgnum == 1) $('#backbutton').hide();
	else $('#backbutton').show();
	var lastActive = $('.entryscreen:not(.disabled)').last()[0].id.split('_');
	lastActive = lastActive[1];
	if(pgnum == lastActive) {
		$('#nextbutton').hide();
		$('#submitbutton').show();
	}
	else {
		$('#submitbutton').hide();
		$('#nextbutton').show();
	}
	$('#quitbutton').show();
}

function serviceCodeChanged() {
	// so what?
}

function petsUpdated(elid) {
	//alert(elid+': '+$('#'+elid).css('height'));
	$('#'+elid).css('height', "");
	//alert(elid+': '+$('#'+elid).css('height'));
}

function buildRequestJSON() {
	var json = {};
	//alert($('#start').val());
	json.start = $('#start').val();
	json.end = $('#end').val();
	json.servicecode = $('#servicecode').val();
	json.prettypets = $('#div_pets').html();
	json.pets = json.prettypets.split(', ').join(',');
	json.visits = [];
	screenVisits('firstday')
		.forEach(function(visit, i, arr) {json.visits.push(visit);});
	screenVisits('otherdays')
		.forEach(function(visit, i, arr) {json.visits.push(visit);});
	if($('#start').val() != $('#end').val())
		screenVisits('lastday')
			.forEach(function(visit, i, arr) {json.visits.push(visit);});
	json.note = $('#note').val();
	json.totaldays = dateRangeDelta()+1;
	json.visitdays = 0;
	var prevDay;
	json.visits.forEach(function(visit, i, arr) {
		if(visit.date != prevDay) json.visitdays += 1;
		prevDay = visit.date;
	});
		

	return json;
}

function screenVisits(sectiondayclass) {
	visits = [];
	if($('.daysection.disabled.'+sectiondayclass).length == 1) return visits;
	var section = $('.daysection.'+sectiondayclass)[0];
	if(sectiondayclass == 'firstday') 
		dayVisits($('#start').val(), sectiondayclass)
			.forEach(function(visit, i, arr) {visits.push(visit); });
	else if(sectiondayclass == 'lastday') {
		dayVisits($('#end').val(), sectiondayclass)
			.forEach(function(visit, i, arr) {visits.push(visit); });
	}			
	else { // otherdays
		var lastDateTime = Date.parse(document.getElementById('end').value);
		var nextDay = $('#start').val();
		if(lastDateTime > Date.parse(document.getElementById('start').value))
			while(Date.parse(nextDay = getTheNextDay(nextDay)) != lastDateTime) {
				dayVisits(nextDay, 'otherdays')
					.forEach(function(visit, i, arr) {visits.push(visit); });
			}
	}
	return visits;
}

function dayVisits(day, daykey) {
	var visits = [];
	let visitpets = $('#div_pets').html().split(', ').join(',');
	for(var i=1; i <= $('#'+daykey).val(); i++) {
		visits.push( 
			{date: day, 
				servicecode: $('#servicecode').val(),
				timeofday: $('#timediv_'+daykey+'_'+i).html(),
				pets: visitpets
			});
	}
	return visits;
}

function scratchIt(str) {document.getElementById('scratch').innerHTML = str;}

function showVisits(thisDate) {
	var ymd = ymdDate(thisDate);
	var package = buildRequestJSON();
	var content = '';
	var visitcount = 0;
	package.visits.forEach(function(visit, num, arr) {if(ymd == ymdDate(visit.date)) visitcount += 1;});
	if(visitcount == 0) var content = 'No visits on '+dowForDate(thisDate)+' '+thisDate;
	else {
		content = "<table border=1 bordercolor='gray'><tr><th colspan=3 style='text-align:center'> Visits on "+dowForDate(thisDate)+' '+thisDate+"</th></tr>";
		content += "<tr><th>Time</th><th>Service</th><th>Pets</th></tr>";
		var prettypets = package.pets.split(',').join(', ');
		package.visits.forEach(function(visit, num, arr) {
			if(ymd == ymdDate(visit.date))
				content += "<tr><td>"+visit.timeofday+"</td><td>"+serviceName(visit.servicecode)+"</td><td>"+prettypets+"</td></tr>";
		});
		content += "<table>";
	}
	$('#daydetail').html(content);
	$('#daydetail_div').show();
	
}

function ymdDate(datestring) {
	var ymd = new Date(Date.parse(datestring));
	return ymd.getFullYear()+'/'+(ymd.getMonth()+1)+ymd.getDate()
}

</script>

</head>
<body style='background:white;  font-family:Helvetica, Arial, Sans-Serif;'>
<div id='scratch' style='font-size:50%;'></div>
<?
//makeTimeFramer($id, $narrow=false, $noNameLinks=false, $clearButton=false, $extraStyle='', $displayTimeFrames=false)

makeTimeFramer('timeFramer', 'narrow', false, false, 'width:748px;', $displayTimeFrames=true);
makePetPicker('petpickerbox',getActiveClientPets($clientptr), $petpickerOptionPrefix, 'narrow');

?>
<form name='scheduleform' id='scheduleform'>
<input type='hidden' id='pgnum' value='1'>
<!-- SECTION 1: PAGE CONTENT -->


<div id='screen_1' class='entryscreen'>
<h3>What days do you need service?</h3>
<table class='entrytable'>
<tr><td>First Day of Service: <? dateEntry('start') ?></td></tr>
<tr><td>&nbsp;</td></tr>
<tr><td>Last Day of Service:<? dateEntry('end') ?></td></tr>
</table>
</div>


<div id='screen_2' class='entryscreen'>
<h3>What kind of service would you like?</h3>
<table class='entrytable'>
<tr><td>
<?

	$serviceSelections = array_merge(array(''=>''), $globalServiceSelections);
	foreach($serviceSelections as $label=>$val) $options .= "<option title='Hello' value='$val'>$label</option>";
  
	selectElement('', "servicecode", $val=null, $serviceSelections, "serviceCodeChanged()");
	
	// PETS GO HERE
?>
</td></tr>
</table>
<h3>For which pets?</h3>
<table class='entrytable'>
<tr><td>
<?
	// PETS GO HERE
	buttonDiv("div_pets", "pets", "showPetGrid(event, \"div_pets\", !\"offset\", \"div_pets\")",
						 'All Pets', '', $extraStyle='width:90%', $petsTitle);
	
?>
</td></tr>
</table>
</div>

<div id='screen_3' class='entryscreen daysection firstday'>
<? visitCountSection('firstday', 'How many visits the FIRST day?', 5); ?>
</div>

<div id='screen_4' class='entryscreen daysection firstday'>
<? visitTimesSection('firstday','What time#PLURAL# the FIRST day?', 5); ?>
</div>

<div id='screen_5' class='entryscreen daysection lastday'>
<? visitCountSection('lastday', 'How many visits the LAST day?', 5); ?>
</div>

<div id='screen_6' class='entryscreen daysection lastday'>
<? visitTimesSection('lastday','What time#PLURAL# the LAST day?', 5); ?>
</div>

<div id='screen_7' class='entryscreen daysection otherdays'>
<? visitCountSection('otherdays', 'How many visits each day in between?', 5); ?>
</div>

<div id='screen_8' class='entryscreen daysection otherdays'>
<? visitTimesSection('otherdays','What time#PLURAL# each day in between?', 5); ?>
</div>

<div id='screen_9' class='entryscreen'>
<h3>Do you have any special instructions?</h3>
<textarea style="width:95%;height:300px;border:solid gray 1px" id='note' name='note'></textarea>
</div>

<!-- END SECTION 1: PAGE CONTENT -->

<!-- SECTION 2: NAV BUTTONS -->
<table class='entrytable'>
<tr><td colspan=3>&nbsp;</td></tr>
<tr>
	<td style='width:25%;text-align:left;'><? echoButton('backbutton','Back', 'backScreen()'); ?></td>
	<td style='width:25%;text-align:center;;'>
		<? echoButton('nextbutton','Next', 'nextScreen()', 'BigButton', 'BigButtonDown');
			 echoButton('submitbutton','Submit', 'submitRequest()', 'BigButton', 'BigButtonDown');
		?></td>
	<td style='width:25%;text-align:right;'><? echoButton('quitbutton','Quit', 'quitScheduler()', 'SmallButton', 'SmallButtonDown'); ?></td>
</tr>
</table>


<!-- END SECTION 2: NAV BUTTONS -->

<!-- SECTION 3: REVIEW -->
<?
$monthNowBackground ='#FFFFFF';
$dayNowBackground ='#FFFFFF';
$existingVisitsBackground ='#6FFF6F;';
$newVisitsBackground ='#C0FFC0;';
?>
<div id='screen_10' class='entryscreen'>
<h3>Please Review Your Request</h3>
... and then tap <b>Submit</b> if it looks right:<p>
<div id='review'></div>
<? // (a #TOTALDAYS# day period) ?>
<div id='initialreview' style='display:none;'>
<b>Visits:</b> #NUMVISITS#  <img src='art/spacer.gif' width=40><b>Days:</b> #VISITDAYS#<br>
<b>Starting:</b> #FIRSTDATE# and<br>
<b>Ending:</b> #LASTDATE#.<br>
The service requested is<br><b>#SERVICENAME#</b><br>
and the pets to be served are<br>#PETNAMES#.<br>
#TOTALPRICE#
<hr>
<div id='daydetail_div' style='display:none;border:solid gray 1px;background:white;'>
	<div style='float:right;color:black;padding-right:20px;clear:left;' onclick='$("#daydetail_div").hide();'>&#9746;</div> 
	<div id='daydetail'></div>
</div>
<table><tr>
	<td style='background-color: <?= $newVisitsBackground ?>'>Requested Visits</td>
	<td style='width:40px;'>&nbsp;</td>
	<td style='font-face:italic;color:gray;'>Tap a day for details.</td>
	<!-- td style='background-color: <?= $existingVisitsBackground ?>'>Existing Visits</td -->
	<!-- td style='background-color: <?= $monthNowBackground ?>'>Other Days</td -->
<tr></table>
#CALENDAR#
<hr>
Note:<p>
#NOTE#
</div>
</div>
<style type="text/css">
/* REVIEW CALENDAR STYLE ELEMENTS */
.monthPre{
 color: gray;
 text-align: center;
}
.monthNow{
 color: #006E6D;
 text-align: center;
 background-color: <?= $monthNowBackground ?>;

}
.dayNow{
 //border: 2px solid black;
 color: #006E6D;
 text-align: center;
 background-color: <?= $dayNowBackground ?>;
}

.visitsDay {
	background: <?= $newVisitsBackground ?>;
}

.othervisitsDay {
	background: <?= $existingVisitsBackground ?>;
}

.calendar {width:95%;}
.calendar td{
 htmlContent: 2px;
 width: 48px;
}
.monthNow th{
 .font-size:0.9em;
 background-color: #000000;
 color: #FFFFFF;
 text-align: center;
}
.dayNames{
 background: #00C0C0;
 color: #FFFFFF;
 text-align: center;
}

#daydetail table {width:95%;}
#daydetail table td {font-size:0.8em;}
 
</style> 	 


</form>
<!-- END SECTION 3: REVIEW -->

<!-- SECTION 4: THANKS -->
<div id='finalMessage' style='display:none;border:solid gray 1px;background:white;'>
</div>

<!-- END SECTION 4: THANKS -->

</body>

<script type="text/javascript">
<?
dumpPetGridJS('petpickerbox',$allPetNames);
dumpTimeFramerJS('timeFramer');


?>

$('.calendarnextdaywidget, .calendarprevdaywidget, .calendarwidget').height(72);
//$('#calendar table').width(800);
//$('#calendar table').css('font-size:40px');
//$('.calendarnextdaywidget, .calendarprevdaywidget, .calendarwidget').each(function(a,b) {alert(b);}); // , '.calendarprevdaywidget', '.calendarwidget'
$("#start").datepicker({
      showOn: "button",
      buttonImage: "art/popcalendar4X.gif",
      buttonImageOnly: true,
      buttonText: "Select date"
    });

$("#end").datepicker({
      showOn: "button",
      buttonImage: "art/popcalendar4X.gif",
      buttonImageOnly: true,
      buttonText: "Select date"
    });
rejiggerNavButtons();
hideYourPrivateBits(1);
$('.visitcountselect').each(function(num,el) {countChanged(el);});
</script>

<?

function dateEntry($name, $secondDayName=null) {
	$spacer = "<img src='art/spacer.gif' width=60>";
  //makeCalendarWidget($name, 'art/popcalendar4X.gif'); echo $spacer;
	echo "<br><input class='dateinput' id='$name' name='$name' value='$value' $onBlur autocomplete='off' readonly onchange='rangeChanged()'> ";
  echo $spacer;
  makePrevDayWidget($name, 'art/prev_day4X.gif');
  echo $spacer;
  makeNextDayWidget($name, $secondDayName, 'art/next_day4X.gif');
}

function visitCountSection($selectid, $question, $max) {
	for($i=1; $i<=$max; $i++) {
		$plural = $i == 1 ? '' : "s";
		$options .= "<option title='Hello' value='$i'>$i visit$plural</option>";
	}
	$selectElement = selectElement('', $selectid, $val=1, $options, "countChanged($selectid)", null, 'visitcountselect standardInput', 'noEcho');
	echo <<<HTML
<h3 class='countquestion$selectid'>$question</h3>
<div class='{$selectid}label'></div>
<table class='entrytable'>
<tr><td style='text-align:center;'>$selectElement</td></tr>
</table>
HTML;
}

function visitTimesSection($selectid, $question, $max) {
	ob_start();
	ob_implicit_flush(0);
	for($i=1; $i<=$max; $i++) {
		echo "<tr id=\"{$selectid}_row_$i\"><td>Visit&nbsp;$i:</td><td>";
		// function showTimeFramer(e, elId, offset) {

		buttonDiv("timediv_{$selectid}_$i", "{$selectid}_$i", 
								"showTimeFramer(event, \"timediv_{$selectid}_$i\", !\"offset\", \"{$selectid}_row_$i\")",!'time of day');
		echo "</td></tr>";
		echo "\n";
	}
	$rows = ob_get_contents();
	ob_end_clean();
	echo <<<HTML
<h3 id="visit_times_question$selectid" rawversion="$question">$question</h3>
<div class='{$selectid}label'></div>
<table class='entrytable' style="width:0%;">
$rows
</table>
HTML;

}

function buttonDiv($divid, $formelementid, $onClick, $label, $value='', $extraStyle=null, $title=null) {
	$title = $title ? "title = '$title'" : '';
	$class = $class ? "class = '$class'" : '';
	echo 
	  "\n<div id='$divid' class='buttondiv' style='$extraStyle' onClick='$onClick' $title>$label</div>";
	//hiddenElement($formelementid, $value);
}	

?>
