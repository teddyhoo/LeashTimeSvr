<?
// survey-nags.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "survey-fns.php";
require_once "provider-fns.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";

if(!adequateRights('#rs') && !locked('o-')) {
	include "frame.html";
	echo "<h2>Insufficient access rights</h2><img src='art/spacer.gif' height=300>";
	include "frame-end.html";
	exit;
}

if($_POST['cancel']) {
	$recipentType = $_POST['cancel'];
	$targetids = $_POST[$recipentType.'s'];
	foreach($targetids as $id) { ;
		$recipient = array($recipentType.'id' => $id);
		clearNag($recipient, 'surveynag');
	}
	$_SESSION['frame_message'] = "Deleted ".count($targetids)." $recipentType nags.";
	globalRedirect('survey-nags.php');
	exit;
}

if($surveyid = $_POST['surveyid']) { // setting nags
	foreach((array)$_POST['providers'] as $provid) {
		$recipients[] = array('providerid'=>$provid);
	}
	$target = 'sitters';
	if($_POST['client']) {
		$recipients[] = array('clientid'=>$_POST['client']);
		$target = 'client';
	}
	if($recipients) setSurveyNag($recipients, $surveyid, $_POST['nagmessage'], $linkLabel=null);
	$_SESSION['frame_message'] = "Nags set up for ".count($recipients)." $target.";
	echo "<script>parent.location.href='survey-nags.php';</script>";
}

function versionsElement($type, $selection=null) {
	$type = $type == 'provider' ? 'sitter' : $type;
	$versions = getSurveyVersions();
	foreach($versions as $i => $v) {
		$surveyid = $v['id'];
		if(!userCanSubmitSurvey($surveyid, $type)) unset($versions[$i]);
	}
	$versions = array_merge($versions);
	if(count($versions) == 1) {
		$v = $versions[0];
		if(userCanSubmitSurvey($versions[0], $addnags)) {
			echo "Survey to be Submitted: <b>{$v['name']}</b> (id: {$v['id']})";
			hiddenElement('surveyid', $v['id']);
		}
	}
	else {
		$vchoices['--Select a Survey--'] = 0;
		foreach($versions as $v) {
			$surveyid = $v['id'];
			$vchoices["{$v['name']} (id: {$v['id']})"] = $v['id'];
		}
		selectElement('Survey to be Submitted', 'surveyid', $value=$selection, $vchoices, $onChange='surveyChanged(this)', $labelClass=null, $inputClass=null, $noEcho=false, $optExtras=null, $title=null);
	}
	
}

if($addnag = $_GET['addnag']) {// used to add/edit a single provider nag 
	// addnag is client or provider or user
	// id is the recipient's ID
	if(!($id =  $_GET['id'])) ; // error
	$receipienttable = $addnag == 'staff' ? 'tbluser' : "tbl$addnag";
	$receipientidfield = $addnag == 'staff' ? 'userid' : "{$addnag}id";
	if($addnag != 'staff') {
		$recipient = fetchFirstAssoc(
			"SELECT $receipientidfield, fname, lname, CONCAT_WS(' ', fname, lname) as name 
				FROM $receipienttable
				WHERE $receipientidfield = $id LIMIT 1", 1);
	}
	$nag = getSurveyNag($recipient);
	
	$targetClass = ucwords($addnag);

	$extraHeadContent = '<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
			 <script type="text/javascript" src="colorbox/version1.3.19/jquery.colorbox-min.js"></script>';
	require_once "frame-bannerless.php";
	echo "<h2>Nag $targetClass {$recipient['name']} for Survey</h2>";
	echo "<form name='addnags' method='POST'>";
	versionsElement($addnag, $nag['surveyid']);
	hiddenElement("{$addnag}[]", $id);
	echo "<input style='display:none;' id='prov_$id' type='checkbox' name='providers[]' value='$id' class='activeprovider' CHECKED> $label</td>";
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = "class='fontSize1_2em'"; //$inputClass ? "class='$inputClass'" : "class='standardInput'";
	$rows = 5;
	$cols = 80;
	$name = 'nagmessage';
	$value = $nag['nagtext'] ? $nag['nagtext'] : "Please submit this #SURVEYLINK# at your earliest convenience";
	$value = withRawEolns($value);
	echo "<p><label $labelClass for='$name'>Nag Message</label><br><textarea $inputClass rows=$rows cols=$cols id='$name' name='$name' $maxlength>$value</textarea>\n";
	echo "<p>";
	echoButton('', 'Schedule Nag', 'setNags()');
	echo "<img src='art/spacer.gif' width=30 height=1>";
	echoButton('', 'Quit', 'parent.$.fn.colorbox.close()');
	echo "</form>";
}

if($addnags = $_GET['addnags']) {// used to add multiple nags. addnags is client or provider or user
	$targetClass = ucwords($addnags);

	$extraHeadContent = '<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
			 <script type="text/javascript" src="colorbox/version1.3.19/jquery.colorbox-min.js"></script>';
	require_once "frame-bannerless.php";
	echo "<h2>Nag for a $targetClass Survey</h2>";
	echo "<form name='addnags' method='POST'>";
	
	versionsElement($addnags);
	if($addnags == 'provider') {
		/*$targets["--Select {$targetClass}s--"] = 0;
		$targets['All Active Sitters'] = -1;
		$targets['All Sitters, including Inactive'] = -2;
		echo "<p>";
		selectElement("{$targetClass}s", "target_$addnags", $value=null, $targets, $onChange=null, $labelClass=null, $inputClass=null, $noEcho=false, $optExtras=null, $title=null);
		*/
		$activeProviders = getProviderShortNames($filter='WHERE active=1'); // id=>name, unordered
		$inactiveProviders = getProviderShortNames($filter='WHERE active=0');
		asort($activeProviders);
		asort($inactiveProviders);
		$cols = 4;
		$activeRows = array_chunk($activeProviders, $cols, $preserve_keys=true);
		$inactiveRows = array_chunk($inactiveProviders, $cols, $preserve_keys=true);

		echo "<p>Sitters: (<span id='sels'>none</span> selected) ";fauxLink('Show Sitters', 'toggleSitters(this)');
		echo "<table id='sittersTable' style='display:none;background:LemonChiffon;'>";
		$selectLinks = 
			$activeRows ? ' - '.fauxLink('Select All', 'selectAll("activeprovider", 1)', 1)
										.' - '.fauxLink('Deselect All', 'selectAll("activeprovider", 0)', 1)
			: '';
		echo "<tr><td colspan=$cols style='font-weight:bold;'>Active Sitters $selectLinks</td></tr>";
		if(!$activeRows) echo "<tr><td colspan=$cols style=''>No active sitters found.</td></tr>";
		else foreach($activeRows as $row) {
			echo "<tr>"; 
			foreach($row as $provid => $name) {
				$checked = FALSE ? 'CHECKED' : '';
				$safeLabel = safeValue($name);
				$label = "<label for='prov_$provid'> $name</label>";
				echo "<td><input id='prov_$provid' type='checkbox' name='providers[]' value='$provid' class='activeprovider' onchange='boxChecked()' $checked> $label</td>";
			}
			echo "</tr>";
		}
		$selectLinks = 
			$activeRows ? ' - '.fauxLink('Select All', 'selectAll("inactiveprovider", 1)', 1)
										.' - '.fauxLink('Deselect All', 'selectAll("inactiveprovider", 0)', 1)
			: '';
		echo "<tr><td colspan=$cols style='font-weight:bold;'>Inactive Sitters $selectLinks</td></tr>";
		if(!$inactiveRows) echo "<tr><td colspan=$cols style=''>No inactive sitters found.</td></tr>";
		foreach($inactiveRows as $row) {
			echo "<tr>";
			foreach($row as $provid => $name) {
				$checked = FALSE ? 'CHECKED' : '';
				$safeLabel = safeValue($name);
				$label = "<label for='prov_$provid'> $name";
				echo "<td><input label = '$safeLabel' id='prov_$provid' type='checkbox' name='providers[]' value='$provid' class='inactiveprovider' onchange='boxChecked()' $checked> $label</td>";
			}
			echo "</tr>";
		}
		echo "</table>";
		$labelClass = $labelClass ? "class='$labelClass'" : '';
		$inputClass = "class='fontSize1_2em'"; //$inputClass ? "class='$inputClass'" : "class='standardInput'";
		$rows = 5;
		$cols = 80;
		$name = 'nagmessage';
		$value = "Please submit this #SURVEYLINK# at your earliest convenience";
		$value = withRawEolns($value);
		echo "<label $labelClass for='$name'>Nag Message</label><br><textarea $inputClass rows=$rows cols=$cols id='$name' name='$name' $maxlength>$value</textarea>\n";
		echo "<p>";
		echoButton('', 'Schedule Nags', 'setNags()');
		echo "<img src='art/spacer.gif' width=30 height=1>";
		echoButton('', 'Quit', 'parent.$.fn.colorbox.close()');


	}
	else if($addnags == 'client') {
		//$title = $_REQUEST['title'];
		//$intro = $_REQUEST['intro'];
		//$prompt = $_REQUEST['prompt'];
		//$prompt = $prompt ? $prompt : 'Choose Client: ';
		//$update = $_REQUEST['update'];
		echo '<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" />';
		$url = "client-chooser-lightbox.php?title=Choose+a+Client&intro=Please+select+a+client+to+nag.&update=client";
		echo "<p>";
		hiddenElement('client', '');
		echoButton('', 'Choose Client', "$.fn.colorbox({href:\"$url\", width:\"400\", height:\"500\", iframe: true, scrolling: true, opacity: \"0.3\"})");
		echo "<div id='chosenclientnamediv'></div><p>";
		$labelClass = $labelClass ? "class='$labelClass'" : '';
		$inputClass = "class='fontSize1_2em'"; //$inputClass ? "class='$inputClass'" : "class='standardInput'";
		$rows = 5;
		$cols = 80;
		$name = 'nagmessage';
		$value = "Please submit this #SURVEYLINK# at your earliest convenience";
		$value = withRawEolns($value);
		echo "<label $labelClass for='$name'>Nag Message</label><br><textarea $inputClass rows=$rows cols=$cols id='$name' name='$name' $maxlength>$value</textarea>\n";
		echo "<p>";
		echoButton('', 'Schedule Nags', 'setNags()');
		echo "<img src='art/spacer.gif' width=30 height=1>";
		echoButton('', 'Quit', 'parent.$.fn.colorbox.close()');
}
	echo "</form>";
}

if($addnags || $addnag) {
?>
<script>
function update(aspect, clientIdBarClientNameBarNote) {
	var parts = clientIdBarClientNameBarNote.split('|');
	document.getElementById('client').value = parts[0];
	document.getElementById('chosenclientnamediv').innerHTML = parts[1];
}

function selectAll(boxclass, onoff) {
	$('.'+boxclass).prop( "checked", onoff);
	boxChecked();
}

function toggleSitters(el) {
	el.innerHTML = el.innerHTML == 'Show Sitters' ? 'Hide Sitters' : 'Show Sitters';
	$('#sittersTable').toggle();
}

function boxChecked() {
	var count;
	count = $('.activeprovider:checked').length + $('.inactiveprovider:checked').length;
	$('#sels').html(count > 0 ? count : 'none');
}

function surveyChanged(el) {
	var surveyid = $('#surveyid').val();
	var pattern = '/survey-form.php?id=f';
	var msg = $('#nagmessage').val();
	var start = msg.indexOf(pattern);
	if(start != -1) {
		var end = msg.indexOf('"', start+pattern.length);
		if(end == -1) end = msg.length;
		var newmsg = msg.substr(0, start+pattern.length);
		newmsg += surveyid;
		newmsg += msg.substr(end, msg.length-end);
		$('#nagmessage').val(newmsg);
	}
}

function setNags() {
	var count, noSurveyId, badMessage;
	if($('#client')[0]) {
		count = $('#client').val() ? '' : 'A client must be selected.';
	}
	else {
		count = $('.activeprovider:checked').length + $('.inactiveprovider:checked').length;
		count = count == 0 ? 'At least one sitter must be selected.' : '';
	}
  //alert(elementValue(document.getElementById('surveyid')));
	noSurveyId = $('#surveyid').val() == "0" ? "A survey must be selected." : '';
  badMessage = $('#nagmessage').val().indexOf('#SURVEYLINK#') == -1 
  							&& $('#nagmessage').val().indexOf('/survey-form.php?id=') == -1 ? 'The message must contain the #SURVEYLINK# token.' : ''; 
	if(!MM_validateForm(
		  noSurveyId, '', 'MESSAGE',
		  count, '', 'MESSAGE',
		  badMessage, '', 'MESSAGE'
		  ))
		return false;
	document.addnags.submit()
}
</script>
<script src='check-form.js'></script>
<?
	exit;
}


$locked = locked('o-,#rs');

if(!$print && !$csv) {
	
	$breadcrumbs = "<a href='reports-survey-submissions.php'>Survey Submissions</a>$settings - <a href='reports.php'>Reports</a>";	
	include "frame.html";
	// ***************************************************************************
	echo "<h2 style='text-align:center;'>Schedule Nags</h2>";
	$allNags = surveyNags();
	echo "<form name='nagsform' method='POST'>";
	hiddenElement('cancel', '');
	nagsTable('provider', 'Sitter Nags', $allNags);
	if(mattOnlyTEST()) nagsTable('client', 'Client Nags', $allNags);
	echo "</form>";
?>
<script>
function addNags(type) {
	$.fn.colorbox({href:"survey-nags.php?addnags="+type, width:"700", height:"700", scrolling: true, opacity: "0.3", iframe: "true"});
}

function editNag(type, id) {
	$.fn.colorbox({href:"survey-nags.php?addnag="+type+"&id="+id, width:"700", height:"400", scrolling: true, opacity: "0.3", iframe: "true"});
}

function cancelNags(type) {
	var count = $(' input[type="checkbox"]:checked').filter('.'+type).length;
	if(count == 0) {
		alert('Please select at least one nag to cancel.');
		return;
	}
	if(!confirm("Cancel nags for "+count+" "+type+"s?")) return;
	$('#cancel').val(type);
	document.nagsform.submit();
}

function showSurvey(id) {
		$.fn.colorbox({href:"survey-form.php?id="+id+"&showflags=1&omitsubmit=1", width:"650", height:"470", scrolling: true, iframe: true, opacity: "0.3"});	
}
	
</script>
<?
	include "frame-end.html";
}


function nagsTable($type, $label, $allNags) {
	require_once "gui-fns.php";
	echo "<h2>$label</h2>";
	echoButton('', 'Add Nags', "addNags(\"$type\")");
	echo "<img src='art/spacer.gif' width=30 height=1>";
	echoButton('', 'Cancel Nags', "cancelNags(\"$type\")", 'HotButton', 'HotButtonDown');
	echo "<p>";
	$thesenags = $allNags[$type];
//print_r($thesenags);	
	if(!$thesenags) echo "<p>No nags found.";
	else {
		$columns = explodePairsLine('cb| ||date|Date||recipient|Name||surveyname|Survey');
		foreach($thesenags as $i => $nag) {
			$nags[$i]['cb'] = "<input type='checkbox' id='{$type}_$i' name='{$type}s[]' value=$i class='$type'>";
			$setdatetime = strtotime($nag['nag']['setdate']);
			$time = date('h:i a', $setdatetime);
			$nags[$i]['date'] = "<span title='$time'>".shortDate($setdatetime)."</span>";
			$nags[$i]['recipient'] = fauxLink($nag['name'],  "editNag(\"provider\", $i)", 1, 'Edit this nag.');
			$safeTitle = safeValue($nag['nag']['title']);
			$surveyid = $nag['nag']['surveyid'];
			$nags[$i]['surveyname'] = 
				fauxLink($nag['nag']['surveyname'], "showSurvey($surveyid)", 1, "v: $surveyid - $safeTitle");
				//"<span style='cursor:pointer' title='v: $surveyid - $safeTitle' onclick='showSurvey($surveyid)'>{$nag['nag']['surveyname']}</span>";
		}
		tableFrom($columns, $nags, $attributes='width=75%', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);
	}
}

?>
