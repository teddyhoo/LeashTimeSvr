<? // cc-accepted-pref-edit.php
/* Params
none
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require "preference-fns.php";
require_once "cc-processing-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
locked('o-');
$failure = false;
if(!adequateRights('*cm')) { // RIGHTS: *cc - credit card processing permission (absoutely required), *cm - credit card info management permission (absoutely required)
	$failure = "Insufficient Access Rights";
}
if($failure) {
	$windowTitle = 'Insufficient Access Rights';
	require "frame-bannerless.php";	
	echo "<h2>$windowTitle</h2>";
	exit;
}

extract($_REQUEST);
$allCards = getAllCardTypes();
if($_POST) {
	$cards = array();
	if(!$_POST['cc_none'])
		foreach($_POST as $key => $val)
			if(strpos($key, 'cc_') !== FALSE)
				$cards[] = $allCards[substr($key, strlen('cc_'))]['label'];
	$cards = join(',', $cards);
	setPreference('ccAcceptedList', $cards);
	echo "<script language='javascript'>if(window.opener && window.opener.updateProperty) window.opener.updateProperty('ccAcceptedList', '$cards');window.close();</script>";
	exit;
}


$cards = explode(',', getPreference('ccAcceptedList'));

$windowTitle = "Credit Cards Accepted";;
$extraHeadContent = <<<HEADSTUFF
	<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>

HEADSTUFF;
require "frame-bannerless.php";
echo "<h2>$windowTitle</h2>";

if(!(merchantInfoSupplied()))
	echo "<p class='fontSize1_2em warning'><span class='fontSize1_2em bold'>You have no Credit Card Gateway set up.</span><br>
				Until you have a Credit Card Gateway set up, this setting will have no effect.</p>";


echo "<form method='POST' name='cclisteditor'>";
echo "<table>";
echo "<tr>";
$checked = !$cards || (isset($cards[0]) && !trim($cards[0]))  ? 'CHECKED' : ''; //
echo "<td style='padding-right:20px;'><input id='cc_none' name='cc_none' type='checkbox' $checked onclick='noneChecked(this, 1)'> 
				<label for='cc_none'>none</label></td>";

foreach($allCards as $key => $card) {
	$checked = in_array($card['label'], $cards) ? 'CHECKED' : '';
	echo "<td class='cc' style='padding-right:20px;'><input id='cc_$key' name='cc_$key' type='checkbox' $checked  onclick='noneChecked(this, 0)'> <label for='cc_$key'><img src='art/{$card['img']}'></label></td>";
}
echo "</tr>";
echo "<tr><td colspan=2>";;
echoButton('', 'Save', 'document.cclisteditor.submit();'); // ccGateway|Credit Card Gateway|picklist|,Authorize.net,SAGE
echo "</td></tr>";
echo "</table>";
echo "</form>";
?>
<script language='javascript'>

function noneChecked(el, none) {
	var sels = 0;
	if(none) {
		$(':checkbox').removeAttr('checked');
		$('#cc_none').attr('checked', true);
	}
	else if(el.checked) $('#cc_none').attr('checked', false);
	else {
		$(':checkbox').each(function(index, el) { if(el.checked) sels++; });
		if(sels == 0) $('#cc_none').attr('checked', true);
	}
}

</script>
