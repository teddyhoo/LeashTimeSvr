<? // mobile-settings.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "preference-fns.php";

locked('p-');

$mobileCSSKey = "mobileCSS_".hash('md5', $_SESSION['userAgent']);
$mobileCSSChoices = explodePairsLine('regular|mobile-sitter.css||bigger|mobile-sitter-size2.css');
$mobileCSSChoicesByValue = array_flip($mobileCSSChoices);
$savedMobileCSS = getUserPreference($_SESSION["auth_user_id"], $mobileCSSKey);
$mobileCSS = $_POST['mobileCSS'] ? $_REQUEST['mobileCSS'] : ($savedMobileCSS ? $savedMobileCSS : 'mobile-sitter.css');

if($_POST) {
	setUserPreference($_SESSION["auth_user_id"], $mobileCSSKey, $mobileCSSChoices[$mobileCSS]);
	$_SESSION[$mobileCSSKey] = $mobileCSSChoices[$mobileCSS];
	globalRedirect('index.php');
}

function isChecked($label) {
	global $mobileCSS, $mobileCSSChoicesByValue;
//echo "$label ($mobileCSS): {$mobileCSSChoicesByValue[$mobileCSS]}<p>";
	return $label == $mobileCSSChoicesByValue[$mobileCSS] ? 'CHECKED' : '';
}
// ==================================
require_once "mobile-frame.php";
?>
<style>
.reqularLabel {font-size: 0.85em;}
.biggerLabel {font-size: 1.4em;}
</style>
<h2>Settings</h2>
<form name='settingsform' method='POST'>
<? echoButton('', 'Save', 'document.settingsform.submit()'); ?>
<table>
<tr><td colspan=2><hr></td></tr>
<tr><td class='dataCell'>Size:</td>
<td>
<input type='radio' name= 'mobileCSS' id='mobileCSS_regular' value='regular' <?= isChecked('regular') ?>> <label for='mobileCSS_regular'><span class='reqularLabel'>regular (good for cell phones)</span></label><br>
<input type='radio' name= 'mobileCSS' id='mobileCSS_bigger' value='bigger' <?= isChecked('bigger') ?>> <label for='mobileCSS_bigger'><span class='biggerLabel'>bigger (good for tablets and tired eyes)</span></label><br>
</td></tr>
<tr><td colspan=2><hr></td></tr>
</table>

