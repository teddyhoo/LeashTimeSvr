<? // report-email-validator.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils-fns.php";
locked('o-');

$type = $_REQUEST['clients'] ? 'client' : 'provider';
$table = "tbl$type";
$extra = $type == 'client' ? ", CONCAT_WS(' ', fname2, lname2) as name2" : '';
$correspondents = fetchAssociations("SELECT *, CONCAT_WS(' ', fname, lname) as name $extra FROM $table WHERE active = 1 ORDER BY lname, fname");
$pageTitle = "Verify email addresses for active $type".'s';
$breadcrumbs = "<a href='reports.php'>Reports</a>";	

require_once "frame.html";

$directions = "<span class=fontSize1_2em><b>Directions:</b>"
."<p> Click the <b>Test Email Addresses</b> button to test all of the email addresses shown on this page."  
."<p>Addresses that appear in red are formatted improperly and will not be tested."
."<p>While an address is being tested a blue question mark (<font color=blue><b>?</b></font>) appears next to it.  If the result of the test is uncertain, the question mark turns red  (<font color=red><b>?</b></font>)."
."<p>Any address that fails verification because the mailbox does not exist or for any other reason will be marked with a red <font color=red><b>X</b></font> and addresses that pass will get a green check mark."
."<p>You can stop the testing process at any time and resume it later; the test will resume at the place that it stopped."
."<p>You can test an email address individually by clicking on it.  You may wish to confirm failed email addresses by sending email to these persons manually."
."<p>We suggest that you contact the owner of a failed email address by phone to obtain a correct address, or you may wish to remove the email address from their profile entirely.";
if($type == 'client') $directions .= "<p>Clicking on a client&apos;s name will pop up a summary of that client&apos;s profile where you will find their contact information.";
$directions .= "<p>Bear in mind that the cause of email address unavailablity is sometimes <u>temporary</u>, so we recommend that you take care when dealing with unavailable email addresses."
							."<p>Also, please bear in mind that sometimes email sent to addresses that pass verification in this report may appear to be sent correctly, but will bounce after a few hours or a few days."
							."  This scenario is difficult to predict or test for in any email environment, so checking to make sure your most important emails have been received is always a good idea.";
$directions .= "</span>";
?>
<style>
.email {color:green;}
.invalid {color:red;}

</style>
<p>Please click here for 
<?= fauxLink('Directions', "$.fn.colorbox({html: \"$directions\", width:\"650\", height:\"470\", scrolling: \"auto\", opacity: \"0.3\"});", 1); ?>.
</p>
<p><span style='color:red'>Note:</span> AOL addresses may report a false negative.  Please send test emails to AOL addresses if you are in doubt.</p>
<?
echoButton('', 'Test Email Addresses', 'runTest();');
echo " <input id='stopNow' type='button' class='HotButton' value='Stop Test' onclick='stopTest();' style='display:none;'>";
?>
 <span id='results'></span>
<?
echo "\n<table>";
foreach($correspondents as $person) {
	$id = $person[$type.'id'];
	$email = $person['email'];
	$emailClass = isEmailValid($email) ? 'email' : 'invalid';
	if($emailClass == 'email') $email = fauxLink($email, "testOne($id)", 1, 'Click to test just this address.', null, null, 'color:darkgreen;');
	$email = $emailClass == 'email' ? "<td id='$id' class='$emailClass'>$email</td>" : '<td>--</td>';
	$personLink = $type == 'client' 
		? fauxLink($person['name'], "clientView($id)", 1)
		: $person['name'];
	echo "\n<tr><td>$personLink</td>$email<td class='result' id='result$id'></td>";
	if(trim($person['name2'])) {
		$id = $id.'_2';
		$email = $person['email2'];
		$emailClass = isEmailValid($email) ? 'email' : 'invalid';
		if($emailClass == 'email') $email = fauxLink($email, "testOne($id)", 1, 'Click to test just this address.', null, null, 'color:darkgreen;');
		$email = $emailClass == 'email' ? "<td id='$id' class='$emailClass'>$email</td>" : '<td>--</td>';
		echo "<td>{$person['name2']}</td>$email<td class='result' id='result$id'></td></tr>";
	}
	else echo "<td>&nbsp;</td><td>&nbsp;</td>";
	echo "</tr>";
}
echo "\n</table>\n";
	
?>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
//alert('hello');	
var ids = new Array();
var stopNow;
function stopTest() {
	stopNow=1;
	document.getElementById('stopNow').style.display='none';
}

function testOne(id) {
	if(document.getElementById('stopNow').style.display != 'none') {
		alert('Please stop the current test first.');
		return;
	}
	ids = new Array();
	ids[0] = id;
	update(0);	
}

function runTest() {
	document.getElementById('stopNow').style.display='inline';
	ids = new Array();
	$('.email').each(function(index, el) {
			var id = el.id;
			if(document.getElementById('result'+id).innerHTML.length == 0) 
				ids[ids.length] = id;
	});
	if(ids.length > 0) update(0);
	//ajaxGetAndCallWith('ajax-email-check.php?email='+el.innerHTML, update, id)
}

function update(index, text) {
	var testingHTML ="<span style='color:blue;font-size:12pt;font-weight:bold'>?</span>";
	var uncertainHTML ="<span style='color:red;font-size:12pt;font-weight:bold' title='Results of this test are uncertain.'>?</span>";
	var id = ids[index];
	if(typeof text != 'undefined') {
		var success = text.indexOf('Invalid') == -1;
		var emailtested = document.getElementById(id).children[0].innerHTML.toUpperCase();
		var img = success ? 'greencheck.gif' : ((emailtested.indexOf('@AOL') > -1) ? 'UNCERTAIN' : 'delete.gif');
//alert(img);		
//alert(id+': '+document.getElementById('result'+id)+'\n'+text);	
		document.getElementById('result'+id).innerHTML = (img == 'UNCERTAIN') ? uncertainHTML : "<img src='art/"+img+"'>";
		updateResults();
		index = index+1;
	}
	id = ids[index];
	if(stopNow || index >= ids.length) {
		stopNow=0;
		document.getElementById('stopNow').style.display='none';
		return;
	}
	var email = document.getElementById(id).children[0].innerHTML;
	document.getElementById('result'+id).innerHTML = testingHTML;
//alert(index+": "+email);
	ajaxGetAndCallWith('ajax-email-check.php?email='+email, update, index);	
}

var totalEmails=0, totalTested=0, totalOk=0, totalBad=0;
function updateResults() {
	totalEmails=0; totalTested=0; totalOk=0; totalBad=0;
	$('.email').each(function(index, el) {
		totalEmails++;
	});
	$('.result').each(function(index, el) {
		if(el.innerHTML.length > 0) totalTested++;
		if(el.innerHTML.indexOf('green') > 0) totalOk++;
		else if(el.innerHTML.indexOf('delete') > 0) totalBad++;
	});
	document.getElementById('results').innerHTML = 
		'Total emails: '+totalEmails+' - Tested: '+totalTested+' - Passed: '+totalOk+' - Failed: '+totalBad;
}

function clientView(id) {
	$.fn.colorbox({href: "client-view.php?nopop=1&id="+id, width:"700", height:"500", iframe: true, scrolling: "auto", opacity: "0.3"});
	
}
</script>
<? require "frame-end.html";