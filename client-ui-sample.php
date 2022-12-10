<? // client-ui-sample.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";


if($thisBiz = $_GET['bizid']) {
	locked('z-');
	$_SESSION["uidirectory"] = "bizfiles/biz_$thisBiz/clientui/";
	if(!file_exists($_SESSION["uidirectory"].'style.css')) {
		$_SESSION["bizfiledirectory"] = "bizfiles/biz_$thisBiz/";
	}
	//$nextBizId = $thisBiz+1;
	//while($nextBizId <= 263) {
	$nextBizId = $thisBiz-1;
	while($nextBizId <= 343) {
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		require_once "common/init_db_common.php";
		$biz = fetchFirstAssoc("SELECT * FROM petcentral.tblpetbiz WHERE bizid = $nextBizId LIMIT 1");
		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1);
		if($biz['activebiz'] && strpos($biz['db'], 'fetch') === FALSE && strpos(strtolower($biz['bizname']), 'fetch') === FALSE &&
			 ($found = file_exists("bizfiles/biz_$nextBizId/clientui/Header.jpg")))
			break;
		//$nextBizId++;
		$nextBizId--;
	}
	if(!$found) {
		$nextBizId = null;
	}
}

else {
	$_SESSION["uidirectory"] = "{$_SESSION["bizfiledirectory"]}clientui/";
	locked('z-');
}


//echo "UI: ({$_SESSION["bizptr"]}) ".file_exists($_SESSION["uidirectory"].'style.css');

include "frame-client.html";
?>
                      											<h2>Home: Elroy Krum&apos;s Schedule</h2>

											<span class='pagenote' style='font-size:1.2em;display:none;'><p id='framemsg'></p></span>											<!-- div class="entry" -->

                                    

<style>

</style>

<!-- script type="text/javascript" src="jquery_1.3.2_jquery.min.js">></script -->

<script type="text/javascript" src="jquery.cycle.2.84.js"></script>

<script type="text/javascript" src="jquery.easing.1.1.1.js"></script>



<div id='clientappts'>

<form name='clientschedform'>

<table><tr><td valign=top><input type='hidden' id='client' name='client' value='47' ><label for='starting'>Starting:</label> <input class='dateInput' id='starting' name='starting'  value='06/23/2012' onChange='updateSecondDate("ending", null, document.getElementById("starting"))' onFocus='this.select();' autocomplete='off'> <img src='art/popcalendar.gif' onclick='dateButtonAction(this,document.getElementById("starting"),"1","15","2005")'>&nbsp;<img src='art/prev_day.gif' onclick='prevDay("starting")'><img src='art/next_day.gif' onclick='nextDay("starting", "ending")'>&nbsp;<label for='ending'>ending:</label> <input class='dateInput' id='ending' name='ending'  value='08/07/2012'  onFocus='this.select();' autocomplete='off'> <img src='art/popcalendar.gif' onclick='dateButtonAction(this,document.getElementById("ending"),"1","15","2005")'>&nbsp;<img src='art/prev_day.gif' onclick='prevDay("ending")'><img src='art/next_day.gif' onclick='nextDay("ending", "")'> <input type='button' id='showAppointments' name='showAppointments' value='Show' class='Button' onClick='searchForAppointments(true)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>

<td align=right width=205><div id="petphotos" class="pics"><img class="photo" src="bizfiles/biz_3/photos/pets/722.jpg" width=160 height=120 /></div></td></tr></table>

</form>

<table><tr><td style='padding-right:5px;'>78 visits found.  </td><td><table style='border-collapse: separate;'><tr>

              <td class='pagingButton'></td>

              <td class='pagingButton'></td>

              <td class='pagingButton'></td>

              <td class='pagingButton'></td>

             </tr></table></td>

        <td></tr></table>

<p>


<table id='calendarview' class='daycalendartable'>
<tr>
<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_1")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Saturday, June 23, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-1' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_1_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_1_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(138849)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(138849)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_2")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Sunday, June 24, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-2' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_2_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_2_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(138854)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(138854)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_3")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Monday, June 25, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-3' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_3_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_3_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(138878)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(138878)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(138879)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(138879)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_4")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Tuesday, June 26, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-4' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_4_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_4_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(138900)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(138900)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(138901)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(138901)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_5")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Wednesday, June 27, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-5' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_5_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_5_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(138926)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(138926)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(138927)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(138927)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_6")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Thursday, June 28, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-6' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_6_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_6_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(138948)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(138948)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(138949)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(138949)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_7")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Friday, June 29, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-7' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_7_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_7_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(138973)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(138973)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Unassigned</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(138974)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(138974)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_8")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Saturday, June 30, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-8' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_8_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_8_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(138980)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(138980)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_9")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Sunday, July 1, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-9' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_9_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_9_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(138985)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(138985)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_10")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Monday, July 2, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-10' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_10_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_10_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139009)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139009)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139010)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139010)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_11")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Tuesday, July 3, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-11' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_11_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_11_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139035)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139035)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139036)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139036)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_12")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Wednesday, July 4, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-12' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_12_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_12_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139061)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139061)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139062)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139062)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_13")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Thursday, July 5, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-13' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_13_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_13_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139083)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139083)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139084)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139084)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_14")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Friday, July 6, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-14' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_14_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_14_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139108)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139108)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139109)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139109)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_15")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Saturday, July 7, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-15' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_15_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_15_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139115)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139115)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_16")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Sunday, July 8, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-16' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_16_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_16_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139120)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139120)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_17")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Monday, July 9, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-17' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_17_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_17_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139144)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139144)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139145)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139145)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_18")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Tuesday, July 10, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-18' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_18_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_18_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139166)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139166)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139167)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139167)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_19")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Wednesday, July 11, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-19' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_19_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_19_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139192)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139192)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139193)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139193)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_20")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Thursday, July 12, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-20' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_20_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_20_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139214)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139214)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139215)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139215)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_21")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Friday, July 13, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-21' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_21_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_21_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139239)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139239)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139240)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139240)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_22")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Saturday, July 14, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-22' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_22_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_22_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139246)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139246)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_23")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Sunday, July 15, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-23' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_23_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_23_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139251)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139251)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_24")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Monday, July 16, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-24' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_24_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_24_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139275)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139275)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139276)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139276)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_25")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Tuesday, July 17, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-25' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_25_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_25_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139297)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139297)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139298)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139298)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_26")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Wednesday, July 18, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-26' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_26_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_26_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139323)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139323)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139324)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139324)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_27")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Thursday, July 19, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-27' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_27_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_27_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139345)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139345)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139346)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139346)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_28")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Friday, July 20, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-28' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_28_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_28_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139370)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139370)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139371)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139371)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_29")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Saturday, July 21, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-29' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_29_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_29_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139378)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139378)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_30")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Sunday, July 22, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-30' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_30_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_30_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139386)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139386)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_31")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Monday, July 23, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-31' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_31_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_31_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139410)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139410)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139411)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139411)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_32")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Tuesday, July 24, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-32' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_32_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_32_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139432)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139432)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139433)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139433)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_33")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Wednesday, July 25, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-33' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_33_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_33_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139458)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139458)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139459)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139459)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_34")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Thursday, July 26, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-34' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_34_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_34_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139480)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139480)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139481)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139481)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_35")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Friday, July 27, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-35' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_35_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_35_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139505)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139505)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139506)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139506)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_36")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Saturday, July 28, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-36' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_36_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_36_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139512)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139512)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_37")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Sunday, July 29, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-37' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_37_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_37_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139517)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139517)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_38")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Monday, July 30, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-38' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_38_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_38_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139541)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139541)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139542)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139542)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_39")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Tuesday, July 31, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-39' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_39_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_39_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139563)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139563)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139564)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139564)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_40")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Wednesday, August 1, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-40' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_40_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_40_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139589)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139589)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139590)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139590)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_41")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Thursday, August 2, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-41' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_41_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_41_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139612)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139612)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139613)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139613)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_42")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Friday, August 3, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-42' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_42_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_42_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139637)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139637)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139638)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139638)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_43")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Saturday, August 4, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-43' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_43_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_43_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139644)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139644)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_44")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Sunday, August 5, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-44' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_44_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_44_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139649)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139649)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_45")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Monday, August 6, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-45' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_45_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_45_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139673)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139673)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139674)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139674)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>

<tr><td class=daycalendardaterow colspan=4  onClick='toggleDate("dateappointments_46")'><table width=100%><tr><td style='text-align:center;font-weight:bold'>Tuesday, August 7, 2012</a></td>

																<td style='text-align:right;width:12px;'><img id='day-shrink-46' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'></td></tr></table></td></tr>
<tr id='dateappointments_46_headers'><th class=daycalendartodheader>Morning</th><th class=daycalendartodheader>Midday</th><th class=daycalendartodheader>Afternoon</th><th class=daycalendartodheader>Evening</th></tr><tr id='dateappointments_46_row'>
<td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Dog Walk </td></tr>

<tr><td valign='top'>9:00 am-11:00 am</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139695)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139695)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td class=daycalendartodcellFIRST /**/><table class=daycalendartodcelltable>
<tr >
<td class='daycalendarobjectcell'>	

<table class='daycalendarappointment' style='width:100%'>

<tr><td valign='top'>Brian Martinez</td><td align=right valign='top'>Pet Sit - 1 pet</td></tr>

<tr><td valign='top'>11:00 am-1:00 pm</td><td align=right valign='top'>Gilly</td></tr><tr><td style='text-align:left;'><input type='button' id='' name='' value='Cancel' class='Button' onClick='cancelAppt(139696)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td>
<td style='text-align:right;'><input type='button' id='' name='' value='Change' class='Button' onClick='changeAppt(139696)' 

						onMouseOver='this.className="ButtonDown"' onMouseOut='this.className="Button"'></td></tr>
</table></td></tr>
</table></td><td>&nbsp;</td><td>&nbsp;</td></tr>
</table> </div>

<?




if($_GET['bizid']) {
	unset($_SESSION["uidirectory"]);
	unset($_SESSION["bizfiledirectory"]);
	unset($_SESSION['bannerLogo']);
}

if($nextBizId) { 
	echo "<script language='javascript'>
				var start = 1000;
				var millisec = '{$_GET['millisec']}' == '' ? start : parseFloat('{$_GET['millisec']}');
				millisec -= Math.max((3 + ((start - millisec) / 1)));
				var min = '{$_GET['min']}' == '' ? 100 : parseFloat('{$_GET['min']}');
				millisec = Math.max(min, millisec);
				setTimeout('document.location.href=\"client-ui-sample.php?bizid=$nextBizId&min=\"+min+\"&millisec=\"+millisec', millisec);
</script>"; }

include "frame-end.html";

