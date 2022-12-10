<? // client-flags-apply.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "pet-fns.php";
require_once "client-flag-fns.php";
require_once "preference-fns.php";

//$ids = '13,29,72,107,165,198,294,367,374,404,413,417,628,653,663,745,750,751,1959';
$ids = $_REQUEST['displayedclientids'];
if(!$ids) {echo "No clients specified.";exit;}

if($_POST) {
	//echo print_r($_POST, 1).'<hr>';
	$chosenFlag = explode('_', $_POST['flagid']); // biz_4
	if($chosenFlag[0] != 'biz') {echo "wrong flag type: {$chosenFlag[0]}";exit;}
	$chosenFlagID = $chosenFlag[1];
	foreach($_POST['selectedclients'] as $id) {
		$clientFlags = getClientFlags($id); //array('flagid'=>X, 'officeOnly'=>Y, 'src'=>Z, 'title'=>AA);
		$clientFlagsById = array();
		foreach($clientFlags as $flag) $clientFlagsById[$flag['flagid']] = $flag;
		
		if($_POST['operation'] == 'apply') {
			if($clientFlagsById[$chosenFlagID]) continue;
			$newFlagPrefProperty = "flag_".(count($clientFlags)+1);
			setClientPreference($id, $newFlagPrefProperty, "$chosenFlagID|");
			$flagsAdded += 1;
		}
		else if($_POST['operation'] == 'remove') {
			if(!$clientFlagsById[$chosenFlagID]) continue;
			// REMOVE ALL FLAGS
			deleteTable('tblclientpref', "clientptr = $id AND property LIKE 'flag_%'", 1);
			// ADD ALL FLAGS BUT $chosenFlagID
			$numFlags = 1;
			foreach($clientFlagsById as $flagid => $flag) {
				if($flagid == $chosenFlagID) continue;
				setClientPreference($id, "flag_$numFlags", "$flagid|{$flag['note']}");
				$numFlags += 1;
			}
			$flagsRemoved += 1;
		}
	}
	$flagsAdded = $flagsAdded ? $flagsAdded : '0';
	$flagsRemoved = $flagsRemoved ? $flagsRemoved : '0';
	if($_POST['operation'] == 'apply') $_SESSION['frame_message'] = "Flag added to $flagsAdded clients.";
	else if($_POST['operation'] == 'remove') $_SESSION['frame_message'] = "Flag removed from $flagsRemoved clients.";
	 //[flagid] => biz_4 [operation] => apply [selectedclients]
	$ids = $_POST['displayedclientids'];
}

$extraHeadContent = '<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" />
<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
<script type="text/javascript" src="colorbox/version1.3.19/jquery.colorbox-min.js"></script>';
$customStyles = "collapsibleTable {border-collapse:collapse;}
.underline {border-bottom:1px solid blue ;}";
require_once "frame-bannerless.php";
if($_SESSION['frame_message']) {
 echo "<span class='pagenote' style='font-size:1.2em'>{$_SESSION['frame_message']}</span><p></p>";
 unset($_SESSION['frame_message']);
}

$clients = fetchAssociationsKeyedBy(
	"SELECT clientid, CONCAT_WS(' ', fname, lname) as client 
		FROM tblclient 
		WHERE clientid IN ($ids)
		ORDER BY lname, fname", 'clientid', 1);

foreach($clients as $clientid => $client) {
	$clients[$clientid]['flags'] = 
		clientFlagPanel($clientid, $officeOnly=false, $noEdit=false, $contentsOnly=false, $onClick="onclick='editFlags($clientid)", $includeBillingFlags=$_REQUEST['billingflags'], $customFlagPanelId="flags_$clientid");
	$clients[$clientid]['cb'] = "<input id='cb_$clientid' type='checkbox' value='$clientid' name='selectedclients[]'>";	
	$clients[$clientid]['client'] = "<label for='cb_$clientid'>{$client['client']}</label>";
	$clients[$clientid]['pets'] = getClientPetNames($clientid, $inactiveAlso=false, $englishList=false);
	$clients[$clientid]['pets'] = "<label for='cb_$clientid'>{$clients[$clientid]['pets']}</label>";
}
	
echo "<form id='clientlistform' name='clientlistform' method='POST'>";
hiddenElement('displayedclientids', $ids);
hiddenElement('billingflags', $_REQUEST['billingflags']);

// flagchooser
$bizFlagList = getBizFlagList();
$bizFlagList = array_chunk($bizFlagList, 1+(int)(count($bizFlagList) / 2));
$pickAFlagHTML = "<h2>Choose a flag</h2><table><tr>";
foreach($bizFlagList as $chunk) {
	$pickAFlagHTML .= "<td style='vertical-align:top;'>";
	foreach($chunk as $flag) {
		$safeTitle = safeValue((string)$flag['title']);
		$img = "<image onclick='update(\"flagchoice\", this);$.fn.colorbox.close();event.stopPropagation();' id='biz_{$flag['flagid']}' src='{$flag['src']}' title='$safeTitle'>";
		$titleSpan = "<span style='cursor:pointer;' onclick='$(\"#biz_{$flag['flagid']}\").click()'>$img $safeTitle</span>";
		$pickAFlagHTML .= "$titleSpan<br>";
	}
	echo "</td>";
}
$pickAFlagHTML .= "</tr></table>";
// END flagchooser

hiddenElement('flagid', '');
hiddenElement('operation', '');
echo "<h2>Add and remove flags</h2>";
if(!$_REQUEST['billingflags']) {
	echo "<div style='vertical-align:bottom; height:24px; border:0px solid black;'>";
	echoButton('', 'Choose a Flag', 'chooseAFlag()', $class='', $downClass='', $noEcho=false, $title='Choose a flag to apply to the selected clients clients.');
	$leftArrow = '&#129032;';
	$leftArrow = '&#11164;';
	$leftArrow = '&#8610;';
	$leftArrow = '<span class="fontSize1_1em">&#11013;</span>';
	echo "<img src='art/spacer.gif' width=10><span id='chosenFlag'>$leftArrow Start here</span>";
	echo "<img src='art/spacer.gif' width=20>";
	//echoButton('', 'Apply Flag to Selected Clients', 'applyFlag()', $class='', $downClass='', $noEcho=false, $title='Choose a flag to apply to the selected clients clients.');
	echo "<img style='vertical-align:bottom; height:18px;' src='art/plus-48.png' onclick='applyFlag()' style='cursor:pointer;' title='Add the chosen flag to the selected clients.'>";
	echo "<img src='art/spacer.gif' width=20>";
	//echoButton('', 'Remove Flag from Selected Clients', 'removeFlag()', $class='', $downClass='', $noEcho=false, $title='Choose a flag to remove from the selected clients clients.');
	echo "<img style='vertical-align:bottom; height:18px;' src='art/cancel-43.png' onclick='removeFlag()' style='cursor:pointer;' title='Remove the chosen flag from the selected clients.'>";
	echo "</div>";
	echo "<p>";
}
fauxLink('Select All', 'selectAll(10)');
echo " - ";
fauxLink('Deselect All', 'selectAll(0)');
$columns = 'cb| ||client|Client||pets|Pets||flags|Flags';
$columns = explodePairsLine($columns);
tableFrom($columns, $clients, $attributes=null, $class='collapsibleTable', $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell underline', $columnSorts=null, $rowClasses, $colClasses=null, $sortClickAction=null);
?>
<script language='javascript' src='check-form.js'></script>
<script>
var flagPanelId = "";
var chosenFlag = null;
var flagPickHTML = "<?= addslashes($pickAFlagHTML) ?>";

function chooseAFlag() {
	$.fn.colorbox({html: flagPickHTML, width:"600", height:"470", iframe:false, scrolling: "auto", opacity: "0.3"});
}

function editFlags(clientid) {
	flagPanelId = "flags_"+clientid;
	$.fn.colorbox({href: "client-flag-picker.php?withname=1&clientptr="+clientid, width:"600", height:"470", iframe:true, scrolling: "auto", opacity: "0.3"});
}
function update(aspect, data) {
	if(aspect == 'flags') {
		$('#'+flagPanelId).html(data);
	}
	if(aspect == 'flagchoice') {
		$('#chosenFlag').html("<img src='"+data.src+"'> "+data.title);
		//alert(data.id);
		$('#flagid').val(data.id); // biz_{$flag['flagid']}
	}
}

function selectAll(on) {
	var cbs = document.getElementsByTagName('input');
	for(var i=0;i<cbs.length;i++)
		if(cbs[i].type == 'checkbox' && !cbs[i].disabled)
			cbs[i].checked = on ? true : false;
	//updateSelectionCount();
}

function getSelections() {
	var cbs = document.getElementsByTagName('input');
	var sels = [];
	for(var i=0;i<cbs.length;i++)
		if(cbs[i].type == 'checkbox' && !cbs[i].disabled && cbs[i].checked)
			sels.push(cbs[i].value);
	return sels;
}

function applyFlag() {
	return checkAndSubmit('apply');
}

function removeFlag() {
	return checkAndSubmit('remove');
}

setPrettynames('flagid','Flag choice');
function checkAndSubmit(op) {
// CHECK FOR SELECTED FLAG TYPE!
	var sels = getSelections();
	var noSels = null;
	if(sels.length == 0) {
		noSels = "Please select at least one client first.";
	}
	if(!MM_validateForm(
		'flagid', '', 'R',
		noSels, '', 'MESSAGE'
		)) return;
	
	var tofrom = op == 'apply' ? 'to' : 'from';
	if(!confirm("You are about to "+op+" the designated flag "+tofrom+" "+sels.length+" clients.\n\nProceed?"))
		return;
	$('#operation').val(op);
	document.clientlistform.submit();
}

<? if($_POST && ($flagsAdded || $flagsRemoved)) echo "window.opener.update('refresh');"; ?>
</script>