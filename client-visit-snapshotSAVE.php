<? // client-visit-snapshot.php
// visit snapshot viewer

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "request-fns.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "client-services-fns.php";
require_once "appointment-fns.php";
require_once "pet-fns.php";
require_once "provider-fns.php";

$locked = locked('c-');//locked('o-'); 

$errors = null;

extract($_REQUEST);
$pop = $_REQUEST['pop'];

$clientid = $_SESSION["clientid"];
$appt = getAppointment($id, $withNames=true);
if($appt['pendingchange'] && $appt['pendingchange'] < 0) $appt['pendingchangetype'] = 'cancel';
else if($appt['pendingchange']) {
	$req = fetchFirstAssoc(
		"SELECT requesttype, extrafields FROM tblclientrequest 
			WHERE requestid = ".abs($appt['pendingchange'])
			." LIMIT 1", 1);
	if($req['requesttype'] != 'schedulechange') $appt['pendingchangetype'] = $req['requesttype'];
	else { // handle new requesttype: schedulechange
		require_once "request-fns.php";
		$extras = getHiddenExtraFields($req);
		$appt['pendingchangetype'] = $extras['changetype'];
	}
}

//print_r($appt);
if($appt['clientptr'] != $clientid) $errors[] = "Visit ID incorrect.";
$client = getClient($clientid);
$futureVisit = strtotime($appt['date'].' '.$appt['starttime']) > time();

$clientServices = array_flip(getClientServices());


$service = $clientServices[$appt['servicecode']] ? $clientServices[$appt['servicecode']] : (
						($service = fetchRow0Col0("SELECT label from tblservicetype WHERE servicetypeid = '{$appt['servicecode']}' LIMIT 1", 1))
							? $service 
							: "[code: {$appt['servicecode']}]");



$noTimeFrames = $_SESSION['preferences']['suppressTimeFrameDisplayInCLientUI'];

/*
Date (centered)
Time Frame (centered)
Pets
Status Complete, Canceled, Empty (top right)

(if visit report published...)

visit report


*/
$completedBoxChar = '&#9745;'; // bold check -- extended set? &#128505;
$uncheckedBoxChar = '&#9744;';
$canceledBoxChar = '&#9746;'; // bold X -- extended set?  &#10008;
if($appt['canceled']) {
	$statusText = $canceledBoxChar;
	$statusClass = 'canceled';
	$statusTitle = 'Canceled '.shortDateAndTime(strtotime($appt['canceled']));
	$textModification = 'strikeout';
}
else if($appt['completed']) {
	$statusText = $completedBoxChar;
	$statusClass = 'completed';
	$statusTitle = 'Completed '; //.shortDateAndTime(strtotime($appt['completed']));
}
else {
	$statusText = $uncheckedBoxChar;
	$statusTitle = 'incomplete '; //.shortDateAndTime(strtotime($appt['completed']));
}



$prettyDate = longerDayAndDate(strtotime($appt['date']));
$timeframe = $noTimeFrames ? '' : "<div class='timeframe $textModification'>{$appt['timeofday']}</div>";
$serviceDiv = $noTimeFrames ? '' : "<div class='service $textModification'>$service</div>";
$petsDiv = $noTimeFrames ? '' : "<div class='pets'>".getAppointmentPetNames($id, $petnames=null, $englishList=true)."</div>";
$statusDiv = "<div class='$statusClass status' title='$statusTitle'>$statusText</div>";
$noteDiv = $appt['note'] ? "<div class='note'>{$appt['note']}</div>" : '';
$sitterDiv = getDisplayableProviderName($appt['providerptr']);
$sitterDiv = is_array($sitterDiv) ? '' : "<div class='sitterDiv'>Sitter: $sitterDiv</div>";
$actions = array();

hiddenElement('fname', $client['fname']);
hiddenElement('lname', $client['lname']);


if($futureVisit) {
	if($appt['canceled']) 
		$actions[] = "<input type='button' value='uncancel' onclick='modVisit(\"uncancel\", $id)'>";
	else 
		$actions[] = "<input type='button' class='canceled' value='cancel' onclick='modVisit(\"cancel\", $id)'>";
		
	if(!$appt['pendingchange'] && !$appt['canceled'] && !$_SESSION['preferences']['suppressChangeButtonOnVisits'])
		$actions[] = "<input type='button' value='change' onclick='modVisit(\"change\", $id)'>";

		
	$actions[] = "<input type='radio' checked id='onevisit' name='whichvisits' onclick='visitsChoiceClicked(this)'><label for='onevisit' class='visitschoice'>this visit</label>";
	$actions[] = "<input type='radio' id='multivisits' name='whichvisits' onclick='visitsChoiceClicked(this)'><label for='multivisits' class='visitschoice'>multiple visits	</label>";
//function fauxLink($label, $onClick, $noEcho=false, $title=null, $id=null, $class=null, $style=null) 
	$notesHTML = fauxLink('Notes...', '$("#note").toggle()', true, "Jot a note to go with the request.", 'notelink');
	$actions[] = "<div id='singlevisitfields'>";
	if($client['fname2'] || $client['lname2']) {
		// ask whether it is client1 or client 2 making the request
		$names = array(displayName($client['fname'], $client['lname']), displayName($client['fname2'], $client['lname2']));
		$values = array(commaName($client['fname'], $client['lname']), commaName($client['fname2'], $client['lname2']));
		$actions[] = 
			"<p class='nameschoice'>I am <span id='namespan'>{$client['fname']} {$client['lname']}</span> ".fauxLink('(Change)', 'showNames()', 1)
			."<img src='art/spacer.gif' width=10>".$notesHTML
			."</p><div id='namechoicediv' style='display:none;'>"
//labeledRadioButton($label, $name, $value=null, $selectedValue=null, $onClick=null, $labelClass=null, $inputClass=null, $labelFirst=null)
			."<input type='radio' checked id='name1' name='username' value='{$values[0]}' onclick='setName(this)'><label for='name1' class='nameschoice'>{$names[0]}</label>"
			."<input type='radio' id='name2' name='username' value='{$values[1]}' onclick='setName(this)'><label for='name2' class='nameschoice'>{$names[1]}</label>"
			."</div>";
		
	}
	$actions[] = "<textarea class='notebox' style='display:none;' id='note' name='note'></textarea>";
	$actions[] = "</div>"; //'singlevisitfields'
}

function displayName($fname, $lname) {
	return $fname.($fname ? ' ' : '').$lname;
}

function commaName($fname, $lname) {
	return "$lname,$fname";
}
if($appt['pendingchangetype']) $pendingChangeNotice = "<div class='pendingnotice'><i class=\"fa fa-adjust warning\"></i> There is a request to {$appt['pendingchangetype']} this visit pending.</div>";
if($actions)
	$actionDiv = "<div class='actions' id='actionsDiv'><hr>".join(' ', $actions)."<hr></div>";

$template = "$statusDiv<h2>$prettyDate</h2>$serviceDiv$timeframe$petsDiv$sitterDiv$noteDiv$pendingChangeNotice$actionDiv";
?>
<!DOCTYPE html>
<head>
<style>
h2 {font-size:1.4em;font-weight:bold;}
body {font-family: 'Lucida Grande', Verdana, Arial, Sans-Serif;}
.canceled {color:red;background:#FF93A5}
.completed {color:green;background:#90EE90}
.status {position:absolute; right:10px; top:10px;font-size:1.5em;}
.service {font-size:1.2em;font-weight:bold;}
.timeframe {margin-top:5px;}
.pets {margin-top:10px;}
.sitterDiv {margin-top:10px;}
.note {margin-top:10px; background:#ffffcc;padding:15px}
.actions {margin-top:25px;}
.strikeout {text-decoration:line-through;}
.warning {color: red;}
.visitschoice {font-size: 0.8em;}
.nameschoice {font-size: 0.8em;}
.fauxlink {text-decoration:underline; color:blue;}
.notebox {width=350px;height=40px;}
.thanks {padding:10px;background:PaleGreen;}
.pendingnotice {margin-top:10px; color:red;}
</style>
</head>
<body>
<?
if($errors) echo "<div class='warning'>".join("<p>", $errors)."</div>";
else echo $template;
?>
<script src="responsiveclient/assets/js/libs/jquery/jquery-1.11.2.min.js"></script> <!--  -->
<script>
function modVisit(action, id) {
	if($('#multivisits').prop('checked'))
		parent.location.href="client-own-multivisit-mods.php?action="+action+"&appt="+<?= $id ?>;
	else {
		if(action == 'change' && document.getElementById('note').value.trim().length == 0) {
			alert('Please click the Notes link to describe the change you want made to this visit first.');
			$("#note").toggle();
			return;
		}
		$.ajax({
				url: 'client-own-schedule-change-json.php',
				dataType: 'json', // comment this out to see script errors in the console
				type: 'post',
				contentType: 'application/json',
				data: JSON.stringify(buildJSONRequest(action)),
				processData: false,
				success: submitSucceeded,
				error: submitFailed // until I figure this out...Figured it out! ?>
				});
	}
}

function submitSucceeded(data, textStatus, jQxhr) {
	<?= mattOnlyTEST() ? 'console.log(data);console.log(textStatus);console.log(jQxhr);' : "console.log('schedule json submitted.');" ?>
	//document.location.href="?thankyou=1";
	//alert('Great! '+JSON.stringify(data));
	$("#actionsDiv").html("<div class='thanks'><h3>Thanks!</h3>We have received your request to "+data.changetype+" this visit.</div>");
}

function submitFailed(jqXhr, textStatus, errorThrown) {
	let message = 'Error encountered:<br>'
		+<?= mattOnlyTEST() ? 'errorThrown' : '"Please notify support."' ?>;
	console.log(message );
	<?= mattOnlyTEST() ? 'console.log("jqXhr: "+jqXhr);console.log("textStatus: "+textStatus);' : '' ?>
}



function buildJSONRequest(action) {
	// fname -- hidden
	// lname -- hidden
	// visit_9082309, value = 9082309, visit_9082310, value = 9082310, ...
	// visitnote_9082309, value = "...", visit_9082310, value = "...", ...
	var appointmentId = '<?= $id ?>';
	var request = {
		fname:$('#fname').val(), 
		lname:$('#lname').val(), 
		visits:[{id:appointmentId}], //visit_
		groupnote:$('#note').val(),
		changetype:action
	};
	//alert(JSON.stringify(request));	
	return request;
}


function visitsChoiceClicked(el) {
	$('#singlevisitfields').css('display', (el.id == 'onevisit' ? 'block' : 'none'));
}

function showNames() {
	$('#namechoicediv').toggle();
}

function setName(el) {
	//var fullname = $(el).val();
	var fullname = $('#'+el.id).val();
	//alert(fullname);
	fullname = fullname.split(",");
	$('#lname').val(fullname[0]);
	$('#fname').val(fullname[1]);
	$('#namespan').html(fullname[1]+' '+fullname[0]);
	$('#namechoicediv').toggle();
}
</script>
</body>