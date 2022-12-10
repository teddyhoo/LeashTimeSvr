<? // discount-edit.php
/*
id - discount id

SCRIPTVARS
properties - optional pipe-separated string to be put into a hidden element named properties
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "comm-fns.php";
require_once "client-fns.php";
require_once "js-gui-fns.php";
require_once "discount-fns.php";
require_once "service-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out


$locked = locked('o-');//locked('o-'); 

extract(extractVars('id,label,amount,ispercentage,start,end,duration,durationlimited,memberidrequired,editable,active,deleteDiscount', $_REQUEST));

if($_POST) {
//print_r($_POST);	exit;
	if($deleteDiscount) {
		deleteTable('tbldiscount', "discountid = $id", 1);
		echo "<script language='javascript'>if(window.opener.update) window.opener.update('messages', null);window.close();</script>";
		exit;
	}
	$start = $start ? date('Y-m-d', strtotime($start)) : null;
	$end = $end ? date('Y-m-d', strtotime($end)) : null;
	$editable = 1;
	$discount = array('label'=>$label, 'amount'=>$amount, 'start'=>$start, 'end'=>$end,
										'active'=>($active ? 1 : 0), 'duration'=>$duration, 'durationlimited'=>($durationlimited ? 1 : 0),
										'ispercentage'=>($ispercentage == 1 ? 1 : 0),
										'memberidrequired'=>($memberidrequired ? 1 : 0),
										'editable'=>$editable);
  $discount['unlimiteddollar'] = ($ispercentage == -1 ? 1 : 0);									
	$allDiscounts = getDiscounts();
	$oldDiscount = $allDiscounts[stripslashes($label)];
	if($oldDiscount) {
		if(!$id || $oldDiscount['discountid'] != $id)
			$errors[] = 'This discount label is already in use for another discount';
			$discount['label'] =  stripslashes($discount['label']);
	}
	
	if(!$errors) {
		if($id) {
			updateTable('tbldiscount', $discount, "discountid = $id", 1);
			$discount['discountid'] = $id;
		}
		else $discount['discountid'] = insertTable('tbldiscount', $discount, 1);
		
		$serviceTypes = array();
		foreach($_POST as $key => $val)
			if(strpos($key, 'service_') === 0)
				$serviceTypes[] = substr($key, strlen('service_'));
		replaceDiscountServices($discount['discountid'], $serviceTypes);
		echo "<script language='javascript'>if(window.opener.update) window.opener.update('messages', null);window.close();</script>";
		exit;
	}
}
else if($id) $discount = getDiscount($id);
$pageTitle = ($id ? 'Edit' : 'Create')." a Discount";

$windowTitle = $pageTitle;
$extraBodyStyle = 'padding:10px;';
require "frame-bannerless.php";

echo "<h2>$pageTitle</h2>";

if($errors) {
	echo "<font color='red'>WARNING:<ul>";
	foreach($errors as $error) echo "<li>$error";
	echo "</ul></font>";
}
?>
<form method='POST' name='discounteditor'>
<table>
<?
// labelRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false)
hiddenElement('id', $id);
hiddenElement('deleteDiscount', 0);
//countdownInputRow($maxLength, $label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null, $position='afterinput')
countdownInputRow(255, 'Label:', 'label', $discount['label'], $labelClass=null, 'verylonginput', $rowId=null,  $rowStyle=null, $onBlur=null, $position='underinput');
inputRow('Amount:', 'amount', $discount['amount']);
$typeOptions = array('percent'=>1,'fixed dollars'=>0,'unlimited dollars'=>-1);
$typeValue = $discount['ispercentage'] ? 1 : ($discount['unlimiteddollar'] ? -1 : 0);
radioButtonRow('', 'ispercentage', $typeValue, $typeOptions, $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null);
calendarRow('Starts:', 'start', $discount['start']);
calendarRow('Ends:', 'end', $discount['end']);
inputRow('Duration:', 'duration', $discount['duration']);
checkboxRow('May extend beyond end date:', 'durationlimited', $discount['durationlimited'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null);
checkboxRow('Member ID required:', 'memberidrequired', $discount['memberidrequired'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null);
checkboxRow('Active:', 'active', $discount['active'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null);
?>
</table>
<?
if(false) { // enable later for independent bizzes
?>
<h3>Eligible Service Types</h3>
<?
	$eligibleTypes = $discount['discountid'] ? getEligibleServiceTypes($discount['discountid']) : array();
	$cols = getServiceNamesById();
	$cols = array_chunk($cols, max((count($cols) / 3)+(count($cols) % 3 ? 1 : 0), 1), true);
	if($cols) {
		echo "<table><tr>";
		foreach($cols as $col) {
			echo "<td style='vertical-align:top;'>";
			foreach($col as $serviceptr => $label) {
	//labeledCheckbox($label, $name, $value=null, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=false, $noEcho=false)			
				labeledCheckBox($label, "service_$serviceptr", in_array($serviceptr, $eligibleTypes), '', '', '', 'boxfirst');
				echo "<br>";
			}
			echo "</td>";
		}
		echo "</tr></table><p>";
	}
} // end Eligible Service Types

echoButton('', 'Save Discount', 'checkAndSave()');
if($id && false) {
	echo " ";
	echoButton('', "Delete Discount", 'dropDiscount()', 'HotButton', 'HotButtonDown');
}
?>
</form>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>

setPrettynames('label',"Discount Label",'amount','Amount','duration','Duration','start','Start', 'end', 'End');
function checkAndSave() {
	var duration = jstrim(document.getElementById('duration').value);
	var durationlimitedMsg = 
		document.getElementById('durationlimited').checked 
			&& (!duration || duration == 0 || duration == '')
			? 'Duration must be specified if the discount may extend beyond the end date.'
			: '';
		
	if(MM_validateForm('label', '', 'R',
											'amount','','PERCENTORNUMBER',
											'start','', 'isDate',
											'end','', 'isDate',
											'duration','', 'UNSIGNEDINT',
											durationlimitedMsg, '', 'MESSAGE'))
  		document.discounteditor.submit();
}

function dropTemplate() {
	if(confirm('Are you sure you want to delete this email discount?')) {
			document.getElementById('deleteDiscount').value=1;
  		document.discounteditor.submit();
		
	}
}

function jstrim(str) {
	return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}

<?
dumpPopCalendarJS();
?>

</script>
<?
// ***************************************************************************
//include "frame-end.html";
?>

