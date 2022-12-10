<? // preference-global-individual.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";

if(userRole() == 'd') locked('d-');
else locked('o-');

extract(extractVars('type,key,label,defaultOn,applyAll', $_REQUEST));

$currentValue = $_SESSION['preferences'][$key] ? $_SESSION['preferences'][$key] : '0';

if($_POST) {
	require_once "preference-fns.php";
	setPreference($key, $defaultOn);
	if($applyAll) {
		$ids = fetchCol0("SELECT {$type}id FROM tbl{$type}");
		foreach($ids as $id) {
			if($type == 'client') setClientPreference($id, $key, null);
			else if($type == 'provider') setProviderPreference($id, $key, null);
		}
	}
	echo "<script language='javascript'>parent.updateProperty('$key', '$defaultOn');parent.$.colorbox.close();</script>";
	exit;
}
	
$typeLabel = $type == 'provider' ? 'sitter' : $type;

require_once "frame-bannerless.php";
?>
<h2><?= $label ?></h2>
<form method='POST' name='prefform'>
<input type='submit' value='Save' class='Button'>
<table>
<?
/*
$onChecked = $currentValue ? 'CHECKED' : '';
$offChecked = !$currentValue ? 'CHECKED' : '';
echo  "<tr><td><label for='defaultOn'>Default is ON for ALL {$typeLabel}s</label> <input type='radio' value='1' id='defaultOn' name='defaultOn' value='1' $onChecked></td></tr>";
echo "<tr><td><label for='defaultOff'>Default is OFF for ALL {$typeLabel}s</label> <input type='radio' value='0' id='defaultOff' name='defaultOn' value='0' $offChecked></td></tr>";
//echo "<tr><td colspan=2><hr></td></tr>";
*/
selectRow($label, 'defaultOn', $currentValue, array('yes'=>1, 'no'=>'0'), $onChange=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $extraTDs=null);
echo "<tr><td>&nbsp;</td></tr>";
echo "<tr><td class='tiplooks' style='text-align:left'>This setting may be overridden for individual {$type}s</td></tr>";
//echo "<tr><td colspan=2 style='font-weight:bold'>To change setting for all existing {$typeLabel}s</td></tr>";
echo "<tr><td><label for='apply'>Apply this default to ALL currently existing {$typeLabel}s</label> <input type='radio' value='1' name='applyAll' id='apply'></td></tr>";
echo "<tr><td><label for='dontapply'>Make no changes to currently existing {$typeLabel}s</label> <input type='radio' value='0' name='applyAll' id='dontapply' CHECKED></td></tr>";
hiddenElement('key', $key);
hiddenElement('type', $type);
?>
</table>
</form>

